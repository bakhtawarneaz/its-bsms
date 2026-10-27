-- Creates a campaign in one transaction (legacy USP_InsertCamp + USP_InsertSMSCamp_Bulk +
-- USP_InsertBalance_Log): campaign row (camp_smstype 4), one Camp_Bulk row per route batch,
-- balance deduction (Balance by credits, BalanceAmount by rate) and a BalanceType 4 sheet entry.
-- Users with camp_approve wait for approval (campaign status 2, batches status 0).
-- @batches: <batches><b route="" operator="" otp="0|1" rate="" count="">923…,923…</b>…</batches>
-- Returns OK (+ camp_id, balance) / NAME_TAKEN / MASK_NOT_FOUND / LOW_BALANCE (+ balance).
CREATE OR ALTER PROCEDURE dbo.usp_compose_campaign
    @user_id    INT,
    @camp_name  NVARCHAR(100),
    @sender     VARCHAR(15),
    @message    NVARCHAR(MAX),
    @cost       INT,
    @camp_time  DATETIME2,
    @total_sms  INT,
    @total_rate DECIMAL(10, 2),
    @remote_ip  VARCHAR(25),
    @batches    XML
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF EXISTS (SELECT 1 FROM dbo.campaign WITH (NOLOCK) WHERE user_id = @user_id AND camp_name = @camp_name)
    BEGIN
        SELECT 'NAME_TAKEN' AS status;
        RETURN;
    END;

    IF NOT EXISTS (SELECT 1 FROM dbo.all_masking WITH (NOLOCK)
                   WHERE user_id = @user_id AND Status = 1 AND Promotional = 1 AND masking = @sender)
    BEGIN
        SELECT 'MASK_NOT_FOUND' AS status;
        RETURN;
    END;

    DECLARE @prepaid BIT, @approve BIT;

    SELECT @prepaid = CASE WHEN LOWER(account_type) = 'prepaid' THEN 1 ELSE 0 END,
           @approve = CASE WHEN ISNULL(camp_approve, 0) = 1 THEN 1 ELSE 0 END
    FROM dbo.users WITH (NOLOCK)
    WHERE id = @user_id;

    DECLARE @total_cost INT = @total_sms * @cost;
    DECLARE @camp_id INT;

    BEGIN TRANSACTION;

    IF @prepaid = 1 AND (SELECT balance FROM dbo.users WITH (UPDLOCK) WHERE id = @user_id) < @total_cost
    BEGIN
        ROLLBACK TRANSACTION;
        SELECT 'LOW_BALANCE' AS status, (SELECT balance FROM dbo.users WHERE id = @user_id) AS balance;
        RETURN;
    END;

    INSERT INTO dbo.campaign (user_id, camp_name, sender, status_camp, msgdata, camp_smstype, camp_time, add_time, total_sms, Cost, TotalRate)
    VALUES (@user_id, @camp_name, @sender, CASE WHEN @approve = 1 THEN 2 ELSE 1 END, @message, 4, @camp_time, GETDATE(), @total_sms, @cost, @total_rate);

    SET @camp_id = CAST(SCOPE_IDENTITY() AS INT);

    INSERT INTO dbo.Camp_Bulk (user_id, camp_id, sender, route, operator, receivers, senttime, isotpallow, RemoteIP, cost, count, status, Rate)
    SELECT @user_id, @camp_id, @sender,
           b.value('@route', 'SMALLINT'),
           b.value('@operator', 'VARCHAR(30)'),
           b.value('.', 'VARCHAR(MAX)'),
           @camp_time,
           b.value('@otp', 'BIT'),
           @remote_ip,
           @cost,
           b.value('@count', 'INT'),
           CASE WHEN @approve = 1 THEN 0 ELSE 1 END,
           b.value('@rate', 'DECIMAL(6,2)')
    FROM @batches.nodes('/batches/b') AS t(b);

    UPDATE dbo.users
    SET Balance = Balance - @total_cost, BalanceAmount = BalanceAmount - @total_rate
    WHERE id = @user_id;

    INSERT INTO dbo.user_balance_sheet (user_id, purchased, availed, SMSRate, avail_time, details, BalanceType)
    VALUES (@user_id, 0, @total_cost, @total_rate, GETDATE(),
            N' Campaign SMS , SMSCost = ' + CAST(@cost AS NVARCHAR(10)) + N', numbers = ' + CAST(@total_sms AS NVARCHAR(12)), 4);

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @camp_id AS camp_id, (SELECT balance FROM dbo.users WHERE id = @user_id) AS balance,
           @approve AS needs_approval;
END;
GO