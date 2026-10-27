-- Loads one gateway user for the edit page.
-- Result set 1: user row. Result set 2: its MaskingRoutes rows.
CREATE OR ALTER PROCEDURE dbo.usp_gw_users_get
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT Id, Username, WebUsername, Fullname, Email, Mobile, ExpiryDateTime, AuthorizedDomain, DefaultMask,
           AccountType, Balance, UserType, ResellerId, CustID, Status, CreatedDatetime,
           ISNULL(permanentblock, 0) AS permanentblock
    FROM ITSSMSGateway.dbo.Users
    WHERE Id = @user_id;

    SELECT Mask AS mask, CAST(Network AS VARCHAR(10)) AS network, Route AS route, Operator AS operator,
           CAST(Status AS INT) AS status
    FROM ITSSMSGateway.dbo.MaskingRoutes WITH (NOLOCK)
    WHERE UserId = @user_id
    ORDER BY Mask, Network;
END;
GO