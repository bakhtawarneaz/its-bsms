-- Removes one mask (all its network routes) from a gateway user (legacy Del_GWUsers_masking_DAL).
CREATE OR ALTER PROCEDURE dbo.usp_gw_users_mask_delete
    @user_id INT,
    @mask    VARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    DELETE FROM ITSSMSGateway.dbo.MaskingRoutes
    WHERE UserId = @user_id AND Mask = @mask;

    SELECT @@ROWCOUNT AS deleted;
END;
GO