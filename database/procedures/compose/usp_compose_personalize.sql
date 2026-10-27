-- Personalized campaign (legacy Add_Personalized_Msg_DAL + insert_PersCampaign + InsertOutboxPersonalizeXML):
--  * campaign name unique per user; sender = any active mask of the user (promotional not required)
--  * one outbox row per contact, each with its own text; route per network from masking_routes
--  * route 0 / '0000' or inactive route → status 7; unknown network → operator 'block'
--  * approval accounts: outbox status 0 + campaign status 2, else 1 / 1
--  * prepaid: users.balance checked and deducted in the same transaction (no balance sheet, legacy)
-- @rows: <rows><r n="923001234567" w="9230" c="1">message text</r>...</rows>  (n receiver, w network, c parts)
-- @camp_cost: legacy stores the parts of the last message as campaign.Cost.
CREATE OR ALTER PROCEDURE dbo.usp_compose_personalize
    @user_id      BIGINT,
    @camp_name    NVARCHAR(100),
    @sender       NVARCHAR(12),
    @message      NVARCHAR(MAX),
    @camp_time    DATETIME2      = NULL,
    @camp_cost    TINYINT        = 1,
    @prepaid      BIT            = 0,
    @camp_approve BIT            = 0,
    @user_otp     BIT            = 0,
    @remote_ip    VARCHAR(50)    = NULL,
    @rows         XML
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @camp_time IS NULL SET @camp_time = GETDATE();

    IF EXISTS (SELECT 1 FROM dbo.campaign WITH (NOLOCK) WHERE user_id = @user_id AND camp_name = @camp_name)
    BEGIN
        SELECT 'NAME_TAKEN' AS status;
        RETURN;
    END;

    IF NOT EXISTS (SELECT 1 FROM dbo.all_masking WITH (NOLOCK)
                   WHERE user_id = @user_id AND masking = @sender AND Status = 1)
    BEGIN
        SELECT 'MASK_NOT_FOUND' AS status;
        RETURN;
    END;

    IF NOT EXISTS (SELECT 1 FROM dbo.masking_routes WITH (NOLOCK) WHERE mask = @sender)
    BEGIN
        SELECT 'NO_ROUTES' AS status;
        RETURN;
    END;

    CREATE TABLE #r (
        receiver VARCHAR(12)   NOT NULL,
        network  VARCHAR(50)   NULL,
        cost     TINYINT       NOT NULL,
        msgdata  NVARCHAR(MAX) NOT NULL
    );

    INSERT INTO #r (receiver, network, cost, msgdata)
    SELECT T.r.value('@n', 'varchar(12)'),
           T.r.value('@w', 'varchar(50)'),
           T.r.value('@c', 'tinyint'),
           ISNULL(T.r.value('text()[1]', 'nvarchar(max)'), N'')
    FROM @rows.nodes('/rows/r') AS T(r);

    DECLARE @count INT = (SELECT COUNT(*) FROM #r);
    DECLARE @credits INT = (SELECT ISNULL(SUM(cost), 0) FROM #r);

    IF @count = 0
    BEGIN
        SELECT 'EMPTY' AS status;
        RETURN;
    END;

    DECLARE @smstype TINYINT = ISNULL((SELECT TOP (1) smstypecode FROM dbo.outbox_sms_type WITH (NOLOCK)
                                       WHERE smstypes = 'Personalize'), 0);
    DECLARE @outbox_status TINYINT = CASE WHEN @camp_approve = 1 THEN 0 ELSE 1 END;
    DECLARE @camp_status   TINYINT = CASE WHEN @camp_approve = 1 THEN 2 ELSE 1 END;
    DECLARE @camp_id INT, @balance DECIMAL(18, 2), @inserted INT;

    BEGIN TRANSACTION;

    SELECT @balance = balance FROM dbo.users WITH (UPDLOCK, ROWLOCK) WHERE id = @user_id;

    IF @prepaid = 1 AND ISNULL(@balance, 0) < @credits
    BEGIN
        ROLLBACK TRANSACTION;
        SELECT 'LOW_BALANCE' AS status, ISNULL(@balance, 0) AS balance;
        RETURN;
    END;

    INSERT INTO dbo.campaign (user_id, camp_name, sender, status_camp, msgdata, camp_time, camp_smstype, total_sms, Cost)
    VALUES (@user_id, @camp_name, @sender, @camp_status, @message, @camp_time, @smstype, @count, @camp_cost);

    SET @camp_id = CAST(SCOPE_IDENTITY() AS INT);

    INSERT INTO dbo.outbox (camp_id, user_id, sender, receiver, msgdata, senttime, status, route, cost, smstype, operator, isotpallow, remoteip)
    SELECT @camp_id, @user_id, @sender, r.receiver, r.msgdata, @camp_time,
           CASE WHEN ISNULL(TRY_CONVERT(INT, mr.route), 0) > 0 AND ISNULL(mr.Status, 0) > 0
                THEN @outbox_status ELSE 7 END,
           ISNULL(TRY_CONVERT(SMALLINT, mr.route), 0),
           r.cost, @smstype,
           ISNULL(mr.Operator, 'block'),
           CASE WHEN mr.IsOtpAllow = 1 AND @user_otp = 1 THEN 1 ELSE 0 END,
           @remote_ip
    FROM #r r
    OUTER APPLY (SELECT TOP (1) m.route, m.Operator, m.IsOtpAllow, m.Status
                 FROM dbo.masking_routes m WITH (NOLOCK)
                 WHERE m.mask = @sender AND m.network = r.network) mr;

    SET @inserted = @@ROWCOUNT;

    IF @prepaid = 1
    BEGIN
        UPDATE dbo.users SET balance = balance - @credits WHERE id = @user_id;
        SET @balance = @balance - @credits;
    END;

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @camp_id AS camp_id, @inserted AS inserted, @credits AS credits,
           ISNULL(@balance, 0) AS balance, @camp_approve AS needs_approval;
END;