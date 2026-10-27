-- Dropdown data for the Add Gateway User form.
-- Result set 1: active routes (Network = RouteInfo network group). Result set 2: gateway resellers (legacy: Id 3 only).
CREATE OR ALTER PROCEDURE dbo.usp_gw_users_form_options
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    SELECT CAST(Network AS VARCHAR(10)) AS network, Route AS route, Routename AS route_name
    FROM ITSSMSGateway.dbo.RouteInfo
    WHERE Status = 1
    ORDER BY Route;

    SELECT Id AS value, Username AS label
    FROM ITSSMSGateway.dbo.Users
    WHERE Id = 3 AND ExpiryDateTime > CURRENT_TIMESTAMP AND ISNULL(permanentblock, 0) = 0;
END;
GO