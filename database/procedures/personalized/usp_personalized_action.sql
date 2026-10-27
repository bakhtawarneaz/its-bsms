-- Approve or suspend personalized messages (legacy Campapprover_Play_Message_DAL /
-- Campapprover_Suspend_Message_DAL): status 0 → 1 (approve) or 11 (suspend),
-- for a whole campaign (@camp_id) or selected message ids (@ids, comma separated).
-- A campaign with nothing left to approve leaves status 2: running (1) if anything was approved,
-- else suspended (4) with an end time.
-- @scope_id: campapprover id → only messages of its users.
CREATE OR ALTER PROCEDURE dbo.usp_personalized_action
    @scope_id INT          = NULL,
    @action   VARCHAR(10),
    @camp_id  INT          = NULL,
    @ids      VARCHAR(MAX) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @code TINYINT = ISNULL((SELECT TOP (1) smstypecode FROM dbo.outbox_sms_type WITH (NOLOCK)
                                    WHERE smstypes = 'Personalize'), 0);
    DECLARE @new TINYINT = CASE @action WHEN 'approve' THEN 1 WHEN 'suspend' THEN 11 END;
    DECLARE @updated INT;

    IF @new IS NULL OR (@camp_id IS NULL AND NULLIF(@ids, '') IS NULL)
    BEGIN
        SELECT 'INVALID' AS status, 0 AS updated;
        RETURN;
    END;

    CREATE TABLE #ids (id BIGINT PRIMARY KEY);
    CREATE TABLE #touched (camp_id INT NULL);

    IF @camp_id IS NULL
    BEGIN
        DECLARE @xml XML = CAST('<i>' + REPLACE(@ids, ',', '</i><i>') + '</i>' AS XML);

        INSERT INTO #ids (id)
        SELECT DISTINCT TRY_CONVERT(BIGINT, T.i.value('.', 'varchar(20)'))
        FROM @xml.nodes('/i') AS T(i)
        WHERE TRY_CONVERT(BIGINT, T.i.value('.', 'varchar(20)')) IS NOT NULL;
    END;

    BEGIN TRANSACTION;

    UPDATE o
    SET o.status = @new
    OUTPUT inserted.camp_id INTO #touched (camp_id)
    FROM dbo.outbox o
    JOIN dbo.users u WITH (NOLOCK) ON u.id = o.user_id
    WHERE o.status = 0
      AND o.smstype = @code
      AND (@scope_id IS NULL OR (u.reseller_id = @scope_id AND u.permanentblock = 0))
      AND ((@camp_id IS NOT NULL AND o.camp_id = @camp_id)
           OR (@camp_id IS NULL AND o.id IN (SELECT id FROM #ids)));

    SET @updated = @@ROWCOUNT;

    -- Campaigns that no longer have messages waiting leave the approval state.
    UPDATE c
    SET c.status_camp = CASE WHEN EXISTS (SELECT 1 FROM dbo.outbox x WITH (NOLOCK)
                                          WHERE x.camp_id = c.id AND x.status = 1) THEN 1 ELSE 4 END,
        c.camp_end_time = CASE WHEN EXISTS (SELECT 1 FROM dbo.outbox x WITH (NOLOCK)
                                            WHERE x.camp_id = c.id AND x.status = 1) THEN c.camp_end_time ELSE CURRENT_TIMESTAMP END
    FROM dbo.campaign c
    WHERE c.id IN (SELECT DISTINCT camp_id FROM #touched WHERE camp_id IS NOT NULL)
      AND c.status_camp = 2
      AND NOT EXISTS (SELECT 1 FROM dbo.outbox p WITH (NOLOCK) WHERE p.camp_id = c.id AND p.status = 0);

    COMMIT TRANSACTION;

    SELECT CASE WHEN @updated > 0 THEN 'OK' ELSE 'NOTHING' END AS status, @updated AS updated;
END;
GO