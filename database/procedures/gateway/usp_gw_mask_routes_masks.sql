-- Distinct gateway masks with how many users carry each one.
-- Grouped on the raw bytes so masks differing only by leading/trailing spaces stay separate.
CREATE OR ALTER PROCEDURE dbo.usp_gw_mask_routes_masks
AS
BEGIN
    SET NOCOUNT ON;

    SELECT CONVERT(VARCHAR(100), g.raw_mask) AS mask, g.users
    FROM (
        SELECT CONVERT(VARBINARY(200), Mask) AS raw_mask, COUNT(DISTINCT UserId) AS users
        FROM ITSSMSGateway.dbo.MaskingRoutes WITH (NOLOCK)
        WHERE Mask IS NOT NULL AND Mask <> ''
        GROUP BY CONVERT(VARBINARY(200), Mask)
    ) g
    ORDER BY LTRIM(CONVERT(VARCHAR(100), g.raw_mask));
END;
GO