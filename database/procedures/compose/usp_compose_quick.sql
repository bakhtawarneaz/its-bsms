-- Quick SMS (legacy Add_Qucik_Msg_DAL + USP_GetMNPOperator), set-based:
--  * sender must be one of the user's active masks
--  * network from ITSMNP.dbo.hlrlookup (ported numbers), else the number prefix; 9237 counts as Zong
--  * route/operator from masking_routes for that mask + network; none or 0 → route 0, operator BLOCKED
--  * prepaid: balance checked and deducted (receivers × cost) in the same transaction as the insert
-- @receivers is a comma list of valid, de-duplicated numbers (max 1000, checked by the caller).
-- Returns OK (+ sent, balance) / MASK_NOT_FOUND / LOW_BALANCE (+ balance).
CREATE OR ALTER PROCEDURE dbo.usp_compose_quick
    @user_id   INT,
    @sender    VARCHAR(20),
    @message   NVARCHAR(MAX),
    @cost      INT,
    @receivers VARCHAR(MAX),
    @remote_ip VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF NOT EXISTS (SELECT 1 FROM dbo.all_masking WITH (NOLOCK)
                   WHERE user_id = @user_id AND Status = 1 AND masking = @sender)
    BEGIN
        SELECT 'MASK_NOT_FOUND' AS status;
        RETURN;
    END;

    DECLARE @prepaid BIT, @otp_allow INT;

    SELECT @prepaid   = CASE WHEN LOWER(account_type) = 'prepaid' THEN 1 ELSE 0 END,
           @otp_allow = ISNULL(IsOtpAllow, 0)
    FROM dbo.users WITH (NOLOCK)
    WHERE id = @user_id;

    CREATE TABLE #r (
        receiver   VARCHAR(20) PRIMARY KEY,
        network    INT         NULL,
        route      INT         NULL,
        operator   VARCHAR(50) NULL,
        isotpallow INT         NULL
    );

    DECLARE @list XML = CAST('<i>' + REPLACE(@receivers, ',', '</i><i>') + '</i>' AS XML);

    INSERT INTO #r (receiver)
    SELECT DISTINCT n.v.value('.', 'VARCHAR(20)')
    FROM @list.nodes('/i') AS n(v);

    -- Ported numbers (skipped when the MNP database is not present, e.g. locally).
    IF DB_ID('ITSMNP') IS NOT NULL
        EXEC sys.sp_executesql N'
            UPDATE r
            SET network = CASE h.Operator WHEN 1 THEN 9230 WHEN 3 THEN 9233 WHEN 4 THEN 9231
                                          WHEN 6 THEN 9234 WHEN 7 THEN 9232 ELSE 0 END
            FROM #r r
            CROSS APPLY (SELECT TOP 1 Operator FROM ITSMNP.dbo.hlrlookup WITH (NOLOCK)
                         WHERE MSISDN = r.receiver) h;';

    UPDATE #r SET network = TRY_CONVERT(INT, LEFT(receiver, 4)) WHERE network IS NULL;
    UPDATE #r SET network = 9231 WHERE network = 9237;

    UPDATE r
    SET route = mr.Route, operator = mr.Operator, isotpallow = mr.isotpallow
    FROM #r r
    CROSS APPLY (SELECT TOP 1 Route, Operator, isotpallow
                 FROM dbo.masking_routes WITH (NOLOCK)
                 WHERE Mask = @sender AND Network = r.network AND Status = 1) mr;

    UPDATE #r SET route = 0, operator = 'BLOCKED', isotpallow = 0
    WHERE route IS NULL OR route = 0;

    DECLARE @count INT = (SELECT COUNT(*) FROM #r);
    DECLARE @total INT = @count * @cost;
    DECLARE @sms_type INT = ISNULL((SELECT TOP 1 smstypecode FROM dbo.outbox_sms_type WITH (NOLOCK)
                                    WHERE smstypes = 'QuickSMS'), 0);
    DECLARE @now DATETIME = GETDATE();

    BEGIN TRANSACTION;

    IF @prepaid = 1
    BEGIN
        UPDATE dbo.users SET balance = balance - @total
        WHERE id = @user_id AND balance >= @total;

        IF @@ROWCOUNT = 0
        BEGIN
            ROLLBACK TRANSACTION;
            SELECT 'LOW_BALANCE' AS status, (SELECT balance FROM dbo.users WHERE id = @user_id) AS balance;
            RETURN;
        END;
    END;

    INSERT INTO dbo.outbox (user_id, camp_id, sender, receiver, msgdata, senttime, status, cost, route, smstype, operator, isotpallow, remoteip)
    SELECT @user_id, 0, @sender, receiver, @message, @now, 1, @cost, route, @sms_type, operator,
           CASE WHEN isotpallow = 1 AND @otp_allow = 1 THEN 1 ELSE 0 END, @remote_ip
    FROM #r;

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @count AS sent, (SELECT balance FROM dbo.users WHERE id = @user_id) AS balance;
END;
GO