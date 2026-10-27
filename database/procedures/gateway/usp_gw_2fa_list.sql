-- Two-factor contacts of a gateway user (read only; the legacy panel had no list).
CREATE OR ALTER PROCEDURE dbo.usp_gw_2fa_list
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT Id, Name, MobileNo, Email, CreatedDate, CAST(Status AS INT) AS status, LoginDatetime
    FROM ITSSMSGateway.dbo.User2FA WITH (NOLOCK)
    WHERE UserId = @user_id
    ORDER BY Id DESC;
END;
GO