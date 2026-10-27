-- Adds or deducts credits on a prepaid gateway user (legacy Add_GWUsers_bal_DAL).
-- No key-account deduction and no balance sheet in the gateway, like the legacy flow.
-- Returns status, username and the balance (after the change when OK).
CREATE OR ALTER PROCEDURE dbo.usp_gw_users_balance_adjust
    @user_id INT,
    @amount  BIGINT,
    @action  VARCHAR(6)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @username VARCHAR(100), @account VARCHAR(20), @balance BIGINT;

    SELECT @username = Username, @account = AccountType, @balance = ISNULL(Balance, 0)
    FROM ITSSMSGateway.dbo.Users WITH (UPDLOCK, ROWLOCK)
    WHERE Id = @user_id;

    IF @username IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status, CAST(NULL AS VARCHAR(100)) AS username, CAST(NULL AS BIGINT) AS balance;
        RETURN;
    END;

    IF @amount IS NULL OR @amount <= 0 OR @action NOT IN ('add', 'deduct')
    BEGIN
        SELECT 'INVALID' AS status, @username AS username, @balance AS balance;
        RETURN;
    END;

    IF LOWER(LTRIM(RTRIM(@account))) <> 'prepaid'
    BEGIN
        SELECT 'NOT_PREPAID' AS status, @username AS username, @balance AS balance;
        RETURN;
    END;

    IF @action = 'deduct' AND @balance < @amount
    BEGIN
        SELECT 'USER_INSUFFICIENT' AS status, @username AS username, @balance AS balance;
        RETURN;
    END;

    UPDATE ITSSMSGateway.dbo.Users
    SET Balance = ISNULL(Balance, 0) + CASE WHEN @action = 'add' THEN @amount ELSE -@amount END
    WHERE Id = @user_id;

    SELECT 'OK' AS status, @username AS username, Balance AS balance
    FROM ITSSMSGateway.dbo.Users
    WHERE Id = @user_id;
END;
GO