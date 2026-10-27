-- Routes of a mask per network for campaigns (legacy Get_Masking_routs_byid_DAL).
-- masking_routes has no Rate column; the legacy code fell back to 1.00 for every route.
CREATE OR ALTER PROCEDURE dbo.usp_compose_mask_routes
    @sender VARCHAR(15)
AS
BEGIN
    SET NOCOUNT ON;

    SELECT network, route, Operator AS operator, ISNULL(IsOtpAllow, 0) AS otp_allow, CAST(1.00 AS DECIMAL(6, 2)) AS rate
    FROM dbo.masking_routes WITH (NOLOCK)
    WHERE mask = @sender;
END;
GO