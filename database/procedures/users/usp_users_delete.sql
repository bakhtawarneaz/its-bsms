-- Hard-deletes a user only when nothing depends on it.
-- Returns status: OK / NOT_FOUND / HAS_USERS / HAS_CREDITS / HAS_HISTORY, plus username and detail.
-- History = any row in any base table with a user_id / userid column, except the tables cleaned up here.
-- The caller (Laravel DB::transaction) owns the transaction.
CREATE OR ALTER PROCEDURE dbo.usp_users_delete
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @username VARCHAR(100), @balance BIGINT, @account VARCHAR(20);

    SELECT @username = username, @balance = ISNULL(balance, 0), @account = account_type
    FROM dbo.users WITH (UPDLOCK, ROWLOCK)
    WHERE id = @user_id AND user_type <> 'superadmin';

    IF @username IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status, CAST(NULL AS VARCHAR(100)) AS username, CAST(NULL AS NVARCHAR(300)) AS detail;
        RETURN;
    END;

    -- Other accounts point at this one (sub users or reporting users)
    IF EXISTS (SELECT 1 FROM dbo.users WHERE reseller_id = @user_id OR reporting_user_id = @user_id)
    BEGIN
        SELECT 'HAS_USERS' AS status, @username AS username, CAST(NULL AS NVARCHAR(300)) AS detail;
        RETURN;
    END;

    -- Unused prepaid credits would be lost
    IF @account = 'prepaid' AND @balance > 0
    BEGIN
        SELECT 'HAS_CREDITS' AS status, @username AS username, CAST(@balance AS NVARCHAR(300)) AS detail;
        RETURN;
    END;

    -- Billing beyond the opening entry written by usp_users_create
    IF EXISTS (SELECT 1 FROM dbo.user_balance_sheet
               WHERE user_id = @user_id AND ISNULL(BalanceType, 0) <> 1)
    BEGIN
        SELECT 'HAS_HISTORY' AS status, @username AS username, N'user_balance_sheet' AS detail;
        RETURN;
    END;

    -- Any other table that references the user (outbox, contacts, campaigns, ...).
    -- The real column name is kept because some tables use "userid" instead of "user_id".
    DECLARE @tables TABLE (id INT IDENTITY(1, 1), table_name NVARCHAR(300), column_name NVARCHAR(130));
    DECLARE @i INT = 1, @count INT, @table NVARCHAR(300), @column NVARCHAR(130), @sql NVARCHAR(MAX), @found BIT;

    INSERT INTO @tables (table_name, column_name)
    SELECT QUOTENAME(c.TABLE_SCHEMA) + N'.' + QUOTENAME(c.TABLE_NAME), QUOTENAME(c.COLUMN_NAME)
    FROM INFORMATION_SCHEMA.COLUMNS c
    JOIN INFORMATION_SCHEMA.TABLES t
      ON t.TABLE_SCHEMA = c.TABLE_SCHEMA AND t.TABLE_NAME = c.TABLE_NAME AND t.TABLE_TYPE = 'BASE TABLE'
    WHERE c.COLUMN_NAME IN ('user_id', 'userid')
      AND c.DATA_TYPE IN ('int', 'bigint', 'smallint', 'numeric', 'decimal', 'varchar', 'nvarchar')
      AND c.TABLE_NAME NOT IN ('users', 'user_maskings', 'user_balance_sheet', 'activity_log');

    SET @count = @@ROWCOUNT;

    WHILE @i <= @count
    BEGIN
        SELECT @table = table_name, @column = column_name FROM @tables WHERE id = @i;
        SET @found = 0;
        SET @sql = N'IF EXISTS (SELECT 1 FROM ' + @table + N' WITH (NOLOCK) WHERE ' + @column
                 + N' = CAST(@uid AS NVARCHAR(20))) SET @found = 1;';
        EXEC sp_executesql @sql, N'@uid INT, @found BIT OUTPUT', @uid = @user_id, @found = @found OUTPUT;

        IF @found = 1
        BEGIN
            SELECT 'HAS_HISTORY' AS status, @username AS username, @table AS detail;
            RETURN;
        END;

        SET @i += 1;
    END;

    -- Nothing depends on the user: remove it with its own setup rows
    DELETE FROM dbo.user_maskings WHERE user_id = @user_id;
    DELETE FROM dbo.user_balance_sheet WHERE user_id = @user_id;
    DELETE FROM dbo.users WHERE id = @user_id;

    SELECT 'OK' AS status, @username AS username, CAST(NULL AS NVARCHAR(300)) AS detail;
END;
GO