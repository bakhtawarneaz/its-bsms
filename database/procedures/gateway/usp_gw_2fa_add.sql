-- Adds one two-factor contact to a gateway user, mirroring the legacy UAT_CreateU2FA:
-- a mobile number can be registered only once per user; new rows start active (Status 1).
-- Returns OK / EXISTS / NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_gw_2fa_add
    @user_id    INT,
    @name       VARCHAR(100),
    @mobile     VARCHAR(13),
    @email      VARCHAR(200),
    @created_by INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF NOT EXISTS (SELECT 1 FROM ITSSMSGateway.dbo.Users WHERE Id = @user_id)
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    IF EXISTS (SELECT 1 FROM ITSSMSGateway.dbo.User2FA WITH (UPDLOCK, HOLDLOCK)
               WHERE MobileNo = @mobile AND UserId = @user_id)
    BEGIN
        SELECT 'EXISTS' AS status;
        RETURN;
    END;

    INSERT INTO ITSSMSGateway.dbo.User2FA (UserId, Name, Email, MobileNo, CreatedDate, CreatedBy, Status)
    VALUES (@user_id, @name, @email, @mobile, GETDATE(), @created_by, 1);

    SELECT 'OK' AS status;
END;
GO