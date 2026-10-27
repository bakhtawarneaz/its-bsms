-- Creates an ITSSMSGateway user with its default mask routes, mirroring the legacy "Add New Gateway User".
-- Passwords are stored as HASHBYTES('MD5', ...) like the legacy form. Routes start active (Status 1).
-- Returns OK / USERNAME_TAKEN / WEB_USERNAME_TAKEN and the new id. The caller owns the transaction.
CREATE OR ALTER PROCEDURE dbo.usp_gw_users_create
    @username         VARCHAR(100),
    @password         VARCHAR(100),
    @web_username     VARCHAR(100),
    @web_password     VARCHAR(100),
    @fullname         NVARCHAR(200),
    @email            VARCHAR(500),
    @mobile           VARCHAR(200),
    @expiry           DATETIME,
    @authorized_ips   VARCHAR(500),
    @default_mask     VARCHAR(50),
    @account_type     VARCHAR(10),
    @balance          BIGINT,
    @reseller_id      INT,
    @user_type        VARCHAR(10),
    @customer_id      INT,
    @status           TINYINT,
    @jazz_route       INT, @jazz_operator    VARCHAR(100),
    @zong_route       INT, @zong_operator    VARCHAR(100),
    @warid_route      INT, @warid_operator   VARCHAR(100),
    @ufone_route      INT, @ufone_operator   VARCHAR(100),
    @telenor_route    INT, @telenor_operator VARCHAR(100),
    @scom_route       INT, @scom_operator    VARCHAR(100)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF EXISTS (SELECT 1 FROM ITSSMSGateway.dbo.Users WITH (UPDLOCK, HOLDLOCK) WHERE Username = @username)
    BEGIN
        SELECT 'USERNAME_TAKEN' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    IF EXISTS (SELECT 1 FROM ITSSMSGateway.dbo.Users WITH (UPDLOCK, HOLDLOCK) WHERE WebUsername = @web_username)
    BEGIN
        SELECT 'WEB_USERNAME_TAKEN' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    INSERT INTO ITSSMSGateway.dbo.Users
        (Username, Fullname, Password, Email, Mobile, CreatedDatetime, ExpiryDateTime, AuthorizedDomain,
         DefaultMask, AccountType, ResellerId, UserType, IsOpenMask, IsFixedMask, SessionsAllowed, CheckHLR,
         SMPPTPS, CustID, WebUsername, WebPassword, Status, Balance)
    VALUES
        (@username, @fullname, HASHBYTES('MD5', @password), @email, @mobile, CAST(GETDATE() AS DATE), @expiry, @authorized_ips,
         @default_mask, @account_type, @reseller_id, @user_type, 0, 1, 1, 1,
         '0', @customer_id, @web_username, HASHBYTES('MD5', @web_password), @status,
         CASE WHEN @account_type = 'PrePaid' THEN @balance ELSE 0 END);

    DECLARE @id INT = CAST(SCOPE_IDENTITY() AS INT);

    INSERT INTO ITSSMSGateway.dbo.MaskingRoutes (UserId, Mask, Network, Status, Operator, Route, CreatedBy)
    VALUES
        (@id, @default_mask, 9230, 1, @jazz_operator,    @jazz_route,    @reseller_id),
        (@id, @default_mask, 9231, 1, @zong_operator,    @zong_route,    @reseller_id),
        (@id, @default_mask, 9232, 1, @warid_operator,   @warid_route,   @reseller_id),
        (@id, @default_mask, 9233, 1, @ufone_operator,   @ufone_route,   @reseller_id),
        (@id, @default_mask, 9234, 1, @telenor_operator, @telenor_route, @reseller_id),
        (@id, @default_mask, 9235, 1, @scom_operator,    @scom_route,    @reseller_id);

    SELECT 'OK' AS status, @id AS id;
END;
GO