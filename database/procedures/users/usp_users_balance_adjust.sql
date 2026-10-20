-- Adds or deducts SMS credits on a prepaid user, mirroring the legacy key-account flow:
-- add    -> a prepaid editor pays from its own balance; the user is credited.
-- deduct -> the user is debited; a prepaid editor gets the credits back.
-- Writes two user_balance_sheet rows (BalanceType = 2) with the legacy detail texts.
-- Returns status, username and the user's balance (after the change when OK).
-- The caller (Laravel DB::transaction) owns the transaction.
CREATE OR ALTER PROCEDURE dbo.usp_users_balance_adjust
    @editor_id INT,
    @user_id   INT,
    @amount    BIGINT,
    @action    VARCHAR(6)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @editor_name VARCHAR(100), @editor_account VARCHAR(20), @editor_balance BIGINT,
            @user_name VARCHAR(100), @user_account VARCHAR(20), @user_balance BIGINT,
            @now DATETIME = GETDATE();

    IF @amount IS NULL OR @amount <= 0 OR @action NOT IN ('add', 'deduct')
    BEGIN
        SELECT 'INVALID' AS status, CAST(NULL AS VARCHAR(100)) AS username, CAST(NULL AS BIGINT) AS balance;
        RETURN;
    END;

    SELECT @editor_name = username, @editor_account = account_type, @editor_balance = ISNULL(balance, 0)
    FROM dbo.users WITH (UPDLOCK, ROWLOCK)
    WHERE id = @editor_id;

    SELECT @user_name = username, @user_account = account_type, @user_balance = ISNULL(balance, 0)
    FROM dbo.users WITH (UPDLOCK, ROWLOCK)
    WHERE id = @user_id AND user_type <> 'superadmin';

    IF @editor_name IS NULL OR @user_name IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status, @user_name AS username, CAST(NULL AS BIGINT) AS balance;
        RETURN;
    END;

    IF @user_account <> 'prepaid'
    BEGIN
        SELECT 'NOT_PREPAID' AS status, @user_name AS username, @user_balance AS balance;
        RETURN;
    END;

    IF @action = 'add' AND @editor_account = 'prepaid' AND @editor_balance < @amount
    BEGIN
        SELECT 'INSUFFICIENT_BALANCE' AS status, @user_name AS username, @user_balance AS balance;
        RETURN;
    END;

    IF @action = 'deduct' AND @user_balance < @amount
    BEGIN
        SELECT 'USER_INSUFFICIENT' AS status, @user_name AS username, @user_balance AS balance;
        RETURN;
    END;

    IF @action = 'add'
    BEGIN
        IF @editor_account = 'prepaid'
            UPDATE dbo.users SET balance = balance - @amount WHERE id = @editor_id;

        UPDATE dbo.users SET balance = balance + @amount WHERE id = @user_id;

        INSERT INTO dbo.user_balance_sheet (user_id, purchased, availed, avail_time, details, BalanceType)
        VALUES
            (@editor_id, 0, @amount, @now,
             'Add balance from key account user ( ' + @editor_name + ' ) to user account ( ' + @user_name + ' )', 2),
            (@user_id, @amount, 0, @now,
             'Add balance to user account ( ' + @user_name + ' ) by key account user ( ' + @editor_name + ' )', 2);
    END
    ELSE
    BEGIN
        IF @editor_account = 'prepaid'
            UPDATE dbo.users SET balance = balance + @amount WHERE id = @editor_id;

        UPDATE dbo.users SET balance = balance - @amount WHERE id = @user_id;

        INSERT INTO dbo.user_balance_sheet (user_id, purchased, availed, avail_time, details, BalanceType)
        VALUES
            (@editor_id, @amount, 0, @now,
             'Deduct balance by key account user ( ' + @editor_name + ' ) from user account ( ' + @user_name + ' )', 2),
            (@user_id, 0, @amount, @now,
             'Deduct balance from user account ( ' + @user_name + ' ) by key account user ( ' + @editor_name + ' )', 2);
    END;

    SELECT 'OK' AS status, @user_name AS username, balance
    FROM dbo.users
    WHERE id = @user_id;
END;
GO