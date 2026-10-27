-- Gateway users that have route rows for an exact mask (spaces included, as in the legacy lookup).
CREATE OR ALTER PROCEDURE dbo.usp_gw_mask_routes_users
    @mask VARCHAR(100)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT u.Id AS user_id, u.Username AS username, COUNT(*) AS routes
    FROM ITSSMSGateway.dbo.MaskingRoutes mr WITH (NOLOCK)
    INNER JOIN ITSSMSGateway.dbo.Users u WITH (NOLOCK) ON u.Id = mr.UserId
    WHERE mr.Mask = @mask AND DATALENGTH(mr.Mask) = DATALENGTH(@mask)
    GROUP BY u.Id, u.Username
    ORDER BY u.Username;
END;
GO