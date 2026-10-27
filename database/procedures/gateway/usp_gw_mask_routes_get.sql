-- Route rows of one exact mask for one gateway user (first result set),
-- and the active route catalogue (second result set).
CREATE OR ALTER PROCEDURE dbo.usp_gw_mask_routes_get
    @mask    VARCHAR(100),
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT Id, Network, Route, Operator, CAST(Status AS INT) AS Status,
           CAST(Promotional AS INT) AS Promotional, Rate
    FROM ITSSMSGateway.dbo.MaskingRoutes WITH (NOLOCK)
    WHERE Mask = @mask AND DATALENGTH(Mask) = DATALENGTH(@mask) AND UserId = @user_id
    ORDER BY Id;

    SELECT Route, Routename, Network
    FROM ITSSMSGateway.dbo.RouteInfo WITH (NOLOCK)
    WHERE Status = 1
    ORDER BY Network, Route;
END;
GO