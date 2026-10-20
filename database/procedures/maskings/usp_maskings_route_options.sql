-- Active routes for the masking route pickers (grouped by network_name in PHP).
CREATE OR ALTER PROCEDURE dbo.usp_maskings_route_options
AS
BEGIN
    SET NOCOUNT ON;

    SELECT DISTINCT id, route_name, network_name
    FROM dbo.routes_info WITH (NOLOCK)
    WHERE Status = 1
    ORDER BY id;
END;
GO