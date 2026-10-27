-- Campaign management list (legacy View_Super_Admin_Camp_DAL / View_Approved_Camp_DAL on campain_manag):
-- counts per state (first result set) and one page, newest first (second).
-- state: completed (nothing remaining) > running (1) > stopped (0) > approval (2) > suspended.
-- @scope_id: campapprover id → only its users (users.reseller_id = @scope_id, not permanently blocked).
CREATE OR ALTER PROCEDURE dbo.usp_campaigns_list
    @field    VARCHAR(20)   = NULL,
    @value    NVARCHAR(200) = NULL,
    @state    VARCHAR(12)   = NULL,
    @offset   INT           = 0,
    @limit    INT           = 25,
    @scope_id INT           = NULL
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, username, user_id, camp_name, sender, camp_time, camp_end_time, status_camp,
           camp_smstypes, camp_total_sms, total_sms, StopCamp, SUM(ob_starts) AS total_sent
    INTO #c
    FROM dbo.campain_manag WITH (NOLOCK)
    WHERE (@scope_id IS NULL
           OR user_id IN (SELECT u.id FROM dbo.users u WITH (NOLOCK) WHERE u.reseller_id = @scope_id AND u.permanentblock = 0))
      AND (@field IS NULL
           OR (@field = 'id'        AND id = TRY_CONVERT(INT, @value))
           OR (@field = 'user_id'   AND user_id = TRY_CONVERT(INT, @value))
           OR (@field = 'username'  AND username = @value)
           OR (@field = 'camp_name' AND camp_name LIKE N'%' + @value + N'%')
           OR (@field = 'sender'    AND LOWER(sender) = LOWER(@value)))
    GROUP BY id, username, user_id, camp_name, sender, camp_time, camp_end_time, status_camp,
             camp_smstypes, camp_total_sms, total_sms, StopCamp
    OPTION (RECOMPILE);

    ALTER TABLE #c ADD remaining INT NULL, state VARCHAR(12) NULL;

    UPDATE #c
    SET remaining = ISNULL(total_sms, 0) - ISNULL(total_sent, 0),
        state = CASE
            WHEN status_camp = 2 THEN 'approval'
            WHEN ISNULL(total_sms, 0) - ISNULL(total_sent, 0) = 0 THEN 'completed'
            WHEN status_camp = 1 THEN 'running'
            WHEN status_camp = 0 THEN 'stopped'
            ELSE 'suspended'
        END;

    SELECT state, COUNT(*) AS total FROM #c GROUP BY state;

    SELECT id, username, user_id, camp_name, sender, camp_time, camp_end_time, status_camp,
           camp_smstypes, camp_total_sms, total_sms, total_sent, remaining, state, StopCamp AS stop_camp
    FROM #c
    WHERE @state IS NULL OR state = @state
    ORDER BY id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY;
END;
GO