-- Gateway users for the gateway summary account filter (legacy: not permanently blocked).
CREATE OR ALTER PROCEDURE dbo.usp_reports_gw_summary_users
AS
BEGIN
    SET NOCOUNT ON;

    SELECT Id AS id, Username AS username
    FROM ITSSMSGateway.dbo.Users WITH (NOLOCK)
    WHERE permanentblock = 0
    ORDER BY Username;
END;
GO