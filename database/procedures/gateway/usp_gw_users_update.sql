-- Updates the editable fields of a gateway user (legacy "Edit Gateway User").
-- Passwords change only when a new value is passed (stored as HASHBYTES('MD5', ...)).
CREATE OR ALTER PROCEDURE dbo.usp_gw_users_update
    @user_id        INT,
    @fullname       NVARCHAR(200),
    @email          VARCHAR(500),
    @mobile         VARCHAR(200),
    @expiry         DATETIME,
    @authorized_ips VARCHAR(500),
    @status         TINYINT,
    @password       VARCHAR(100) = NULL,
    @web_password   VARCHAR(100) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE ITSSMSGateway.dbo.Users
    SET Fullname         = @fullname,
        Email            = @email,
        Mobile           = @mobile,
        ExpiryDateTime   = @expiry,
        AuthorizedDomain = @authorized_ips,
        Status           = @status,
        Password         = CASE WHEN @password IS NULL THEN Password ELSE HASHBYTES('MD5', @password) END,
        WebPassword      = CASE WHEN @web_password IS NULL THEN WebPassword ELSE HASHBYTES('MD5', @web_password) END
    WHERE Id = @user_id;

    SELECT CASE WHEN @@ROWCOUNT = 0 THEN 'NOT_FOUND' ELSE 'OK' END AS status;
END;
GO