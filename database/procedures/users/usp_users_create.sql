CREATE OR ALTER PROCEDURE dbo.usp_users_create
    @creator_id        INT,
    @username          NVARCHAR(50),
    @password_hash     NVARCHAR(32),
    @fullname          NVARCHAR(50),
    @email             NVARCHAR(500),
    @mobiles           NVARCHAR(100),
    @expiry            DATE,
    @status            TINYINT,
    @customer_id       INT,
    @account_type      NVARCHAR(50),   -- prepaid | postpaid
    @balance           BIGINT,
    @user_type         NVARCHAR(13),
    @reseller_id       INT = NULL,
    @reporting_user_id INT = NULL,
    @forward_status    BIT = 0,              -- DLR Forwarding Status (users.StatusCallingAPI)
    @calling_api       NVARCHAR(MAX) = NULL  -- DLR forwarding JSON, validated in PHP (users.CallingAPI)
AS
BEGIN
    SET NOCOUNT ON;
    -- The caller wraps this procedure and the masking inserts in one transaction

    IF EXISTS (SELECT 1 FROM dbo.users WHERE username = @username)
    BEGIN
        SELECT 'USERNAME_TAKEN' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    DECLARE @creator_account NVARCHAR(50), @creator_balance BIGINT, @site_name NVARCHAR(50);

    -- Lock the creator row so two simultaneous creations cannot overspend the balance
    SELECT @creator_account = account_type, @creator_balance = balance, @site_name = site_name
    FROM dbo.users WITH (UPDLOCK, ROWLOCK)
    WHERE id = @creator_id;

    DECLARE @is_prepaid BIT = CASE WHEN @account_type = 'prepaid' THEN 1 ELSE 0 END;
    DECLARE @deduct BIT = CASE WHEN @is_prepaid = 1 AND @creator_account = 'prepaid' THEN 1 ELSE 0 END;

    IF @deduct = 1 AND @creator_balance < @balance
    BEGIN
        SELECT 'INSUFFICIENT_BALANCE' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    INSERT INTO dbo.users
        (username, fullname, [password], email, user_mobile, user_creation, user_expiry,
         site_name, CustID, [status], account_type, balance, user_type,
         otp_access, otp_account, voice_balance, StatusCallingAPI, CallingAPI)
    VALUES
        (@username, @fullname, @password_hash, @email, @mobiles, GETDATE(), @expiry,
         @site_name, @customer_id, @status, @account_type,
         CASE WHEN @is_prepaid = 1 THEN @balance ELSE 0 END, @user_type,
         0, 'postpaid', 0, ISNULL(@forward_status, 0), @calling_api);

    DECLARE @new_id INT = CAST(SCOPE_IDENTITY() AS INT);

    -- Only set ownership columns when provided, so table defaults apply otherwise
    IF @reseller_id IS NOT NULL
        UPDATE dbo.users SET reseller_id = @reseller_id WHERE id = @new_id;

    IF @reporting_user_id IS NOT NULL
        UPDATE dbo.users SET reporting_user_id = @reporting_user_id WHERE id = @new_id;

    IF @is_prepaid = 1
    BEGIN
        IF @deduct = 1
            UPDATE dbo.users SET balance = balance - @balance WHERE id = @creator_id;

        INSERT INTO dbo.user_balance_sheet (user_id, purchased, availed, avail_time, details, BalanceType)
        VALUES (@creator_id, 0, @balance, GETDATE(),
                N'Added New User - Added bal to new username ' + @username + N'. User id is ' + CAST(@new_id AS NVARCHAR(20)), 1);

        INSERT INTO dbo.user_balance_sheet (user_id, purchased, availed, avail_time, details, BalanceType)
        VALUES (@new_id, @balance, 0, GETDATE(),
                N'Add New User - Add bal new user. Id is ' + CAST(@new_id AS NVARCHAR(20)), 1);
    END;

    SELECT 'OK' AS status, @new_id AS id;
END;