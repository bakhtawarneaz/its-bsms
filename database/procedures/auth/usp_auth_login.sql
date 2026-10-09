CREATE OR ALTER PROCEDURE dbo.usp_auth_login
    @login         NVARCHAR(500),   -- username or email
    @password_hash NVARCHAR(32),    -- md5(md5(password)) computed in PHP
    @site_name     NVARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @user_id        INT,
            @stored_hash    NVARCHAR(32),
            @status         TINYINT,
            @expiry         DATE,
            @permanentblock BIT;

    -- Find the user on this site; prefer an exact username match over an email match
    SELECT TOP (1)
        @user_id        = id,
        @stored_hash    = [password],
        @status         = [status],
        @expiry         = user_expiry,
        @permanentblock = permanentblock
    FROM dbo.users
    WHERE site_name = @site_name
      AND (username = @login OR email = @login)
    ORDER BY CASE WHEN username = @login THEN 0 ELSE 1 END;

    DECLARE @login_status VARCHAR(20) =
        CASE
            WHEN @user_id IS NULL OR @stored_hash <> @password_hash THEN 'INVALID'
            WHEN @status = 0                         THEN 'DISABLED'
            WHEN @status = 2                         THEN 'SUSPENDED'
            WHEN @expiry < CAST(GETDATE() AS DATE)   THEN 'EXPIRED'
            WHEN @status = 3                         THEN 'BLOCKED'
            WHEN @permanentblock = 1                 THEN 'PERM_BLOCKED'
            ELSE 'OK'
        END;

    -- Return only the status when login is not allowed
    IF @login_status <> 'OK'
    BEGIN
        SELECT @login_status AS login_status;
        RETURN;
    END;

    -- Successful login: return the user profile (never the password hash)
    SELECT
        @login_status AS login_status,
        u.id,
        u.username,
        u.fullname,
        u.email,
        u.user_mobile,
        u.user_type,
        u.reporting_user_id,
        u.reseller_id,
        u.user_expiry,
        u.site_name,
        u.open_mask,
        u.account_type,
        u.balance,
        u.otp_access,
        u.IsOtpAllow,
        u.whatsapp_no,
        u.contact_updated,
        u.camp_approve
    FROM dbo.users AS u
    WHERE u.id = @user_id;
END;