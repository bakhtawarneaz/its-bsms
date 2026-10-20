-- Updates editable fields of a user. Password changes only when a hash is passed.
-- Returns OK / NOT_FOUND / HAS_USERS (admin with sub users cannot change type).
CREATE OR ALTER PROCEDURE dbo.usp_users_update
    @user_id           INT,
    @fullname          VARCHAR(200),
    @email             VARCHAR(500),
    @mobiles           VARCHAR(100),
    @expiry            DATE,
    @status            TINYINT,
    @customer_id       INT = NULL,
    @user_type         VARCHAR(13),
    @reseller_id       INT = NULL,
    @reporting_user_id INT = NULL,
    @password_hash     VARCHAR(32) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @current_type VARCHAR(13);

    SELECT @current_type = user_type
    FROM dbo.users WITH (UPDLOCK, ROWLOCK)
    WHERE id = @user_id AND user_type <> 'superadmin';

    IF @current_type IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    -- An admin that still owns users cannot be turned into another type
    IF @current_type IN ('admin', 'reseller') AND @user_type <> @current_type
       AND EXISTS (SELECT 1 FROM dbo.users WHERE reseller_id = @user_id)
    BEGIN
        SELECT 'HAS_USERS' AS status;
        RETURN;
    END;

    UPDATE dbo.users
    SET fullname          = @fullname,
        email             = @email,
        user_mobile       = @mobiles,
        user_expiry       = @expiry,
        status            = @status,
        CustID            = @customer_id,
        user_type         = @user_type,
        reseller_id       = CASE WHEN @reseller_id IS NOT NULL THEN @reseller_id ELSE reseller_id END,
        reporting_user_id = CASE WHEN @user_type = 'reporting' THEN @reporting_user_id ELSE reporting_user_id END,
        password          = CASE WHEN @password_hash IS NOT NULL THEN @password_hash ELSE password END
    WHERE id = @user_id;

    SELECT 'OK' AS status;
END;
GO