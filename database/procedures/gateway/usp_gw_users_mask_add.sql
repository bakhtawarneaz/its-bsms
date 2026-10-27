-- Adds one mask with its six network routes to a gateway user (legacy Add_GWUsers_masking_DAL).
-- Routes start active (Status 1) and CreatedBy is 3, exactly like the legacy code.
-- Returns OK / EXISTS / NOT_FOUND. The caller owns the transaction.
CREATE OR ALTER PROCEDURE dbo.usp_gw_users_mask_add
    @user_id          INT,
    @mask             VARCHAR(50),
    @jazz_route       INT, @jazz_operator    VARCHAR(100),
    @zong_route       INT, @zong_operator    VARCHAR(100),
    @warid_route      INT, @warid_operator   VARCHAR(100),
    @ufone_route      INT, @ufone_operator   VARCHAR(100),
    @telenor_route    INT, @telenor_operator VARCHAR(100),
    @scom_route       INT, @scom_operator    VARCHAR(100)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF NOT EXISTS (SELECT 1 FROM ITSSMSGateway.dbo.Users WHERE Id = @user_id)
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    IF EXISTS (SELECT 1 FROM ITSSMSGateway.dbo.MaskingRoutes WITH (UPDLOCK, HOLDLOCK) WHERE UserId = @user_id AND Mask = @mask)
    BEGIN
        SELECT 'EXISTS' AS status;
        RETURN;
    END;

    INSERT INTO ITSSMSGateway.dbo.MaskingRoutes (UserId, Mask, Network, Status, Operator, Route, CreatedBy)
    VALUES
        (@user_id, @mask, 9230, 1, @jazz_operator,    @jazz_route,    3),
        (@user_id, @mask, 9231, 1, @zong_operator,    @zong_route,    3),
        (@user_id, @mask, 9232, 1, @warid_operator,   @warid_route,   3),
        (@user_id, @mask, 9233, 1, @ufone_operator,   @ufone_route,   3),
        (@user_id, @mask, 9234, 1, @telenor_operator, @telenor_route, 3),
        (@user_id, @mask, 9235, 1, @scom_operator,    @scom_route,    3);

    SELECT 'OK' AS status;
END;
GO