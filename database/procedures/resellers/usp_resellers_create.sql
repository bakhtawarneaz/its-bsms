-- Creates a reseller account with its branding, mirroring the legacy "Add New Reseller":
-- users row (user_type reseller, site_name = domain), prepaid credits with two balance
-- sheet rows (BalanceType 1, legacy texts) and one reseller_template_info row.
-- Returns OK / USERNAME_TAKEN / DOMAIN_TAKEN / INSUFFICIENT_BALANCE / CREATOR_NOT_FOUND.
-- The caller (Laravel DB::transaction) owns the transaction.
CREATE OR ALTER PROCEDURE dbo.usp_resellers_create
    @creator_id    INT,
    @username      VARCHAR(100),
    @password_hash VARCHAR(32),
    @fullname      VARCHAR(200),
    @email         VARCHAR(500),
    @mobiles       VARCHAR(100),
    @expiry        DATE,
    @status        TINYINT,
    @customer_id   INT,
    @account_type  VARCHAR(20),
    @balance       BIGINT = 0,
    @company_name  NVARCHAR(200),
    @domain        VARCHAR(200),
    @slogan        NVARCHAR(300),
    @logo_path     VARCHAR(300),
    @first_color   VARCHAR(10),
    @second_color  VARCHAR(10),
    @third_color   VARCHAR(10),
    @fourth_color  VARCHAR(10),
    @dark_theme    BIT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @new_id INT, @creator_account VARCHAR(20), @creator_balance BIGINT;

    IF EXISTS (SELECT 1 FROM dbo.users WITH (UPDLOCK, HOLDLOCK) WHERE username = @username)
    BEGIN
        SELECT 'USERNAME_TAKEN' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    IF EXISTS (SELECT 1 FROM dbo.reseller_template_info WITH (UPDLOCK, HOLDLOCK)
               WHERE domain COLLATE DATABASE_DEFAULT = @domain COLLATE DATABASE_DEFAULT)
    BEGIN
        SELECT 'DOMAIN_TAKEN' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    SELECT @creator_account = account_type, @creator_balance = ISNULL(balance, 0)
    FROM dbo.users WITH (UPDLOCK, ROWLOCK)
    WHERE id = @creator_id;

    IF @creator_account IS NULL
    BEGIN
        SELECT 'CREATOR_NOT_FOUND' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    IF @account_type = 'prepaid' AND @creator_account = 'prepaid' AND @creator_balance < @balance
    BEGIN
        SELECT 'INSUFFICIENT_BALANCE' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    INSERT INTO dbo.users
        (username, fullname, password, email, user_mobile, user_type, reseller_id,
         user_creation, user_expiry, site_name, CustID, status, account_type, balance,
         otp_access, otp_account, voice_balance)
    VALUES
        (@username, @fullname, @password_hash, @email, @mobiles, 'reseller', @creator_id,
         GETDATE(), @expiry, @domain, @customer_id, @status, @account_type,
         CASE WHEN @account_type = 'prepaid' THEN @balance ELSE 0 END,
         0, 'postpaid', 0);

    SET @new_id = CAST(SCOPE_IDENTITY() AS INT);

    IF @account_type = 'prepaid' AND @balance > 0
    BEGIN
        IF @creator_account = 'prepaid'
            UPDATE dbo.users SET balance = balance - @balance WHERE id = @creator_id;

        INSERT INTO dbo.user_balance_sheet (user_id, purchased, availed, avail_time, details, BalanceType)
        VALUES
            (@creator_id, 0, @balance, GETDATE(),
             ' Added New Reseller - Added bal to new username ' + CAST(@balance AS VARCHAR(20)) + ' user id is ' + CAST(@new_id AS VARCHAR(12)) + ' ', 1),
            (@new_id, @balance, 0, GETDATE(),
             ' Add New Reseller - Add bal new user id is ' + CAST(@new_id AS VARCHAR(12)) + ' ', 1);
    END;

    INSERT INTO dbo.reseller_template_info
        (name, domain, slogan, favicon, first_color, second_color, third_color, fourth_color, dark_theme)
    VALUES
        (@company_name, @domain, @slogan, @logo_path, @first_color, @second_color, @third_color, @fourth_color, @dark_theme);

    SELECT 'OK' AS status, @new_id AS id;
END;
GO