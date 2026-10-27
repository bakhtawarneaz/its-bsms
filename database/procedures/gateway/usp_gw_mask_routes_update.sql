-- Updates one MaskingRoutes row (legacy Super_edit_GwMasking_route_DAL):
-- route 0 means blocked (Status 0), anything else is active (Status 1).
-- The row must belong to the given user and the exact mask. Returns OK / NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_gw_mask_routes_update
    @id          INT,
    @user_id     INT,
    @mask        VARCHAR(100),
    @route       INT,
    @operator    VARCHAR(100),
    @promotional BIT,
    @rate        DECIMAL(10, 2)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @affected INT;

    UPDATE ITSSMSGateway.dbo.MaskingRoutes
    SET Status      = CASE WHEN @route = 0 THEN 0 ELSE 1 END,
        Route       = @route,
        Operator    = @operator,
        Promotional = @promotional,
        Rate        = @rate
    WHERE Id = @id AND UserId = @user_id
      AND Mask = @mask AND DATALENGTH(Mask) = DATALENGTH(@mask);

    SET @affected = @@ROWCOUNT;

    SELECT CASE WHEN @affected = 0 THEN 'NOT_FOUND' ELSE 'OK' END AS status;
END;
GO