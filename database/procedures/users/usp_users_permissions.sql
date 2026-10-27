-- Legacy per-account permission flags (were read from the users row kept in the session).
CREATE OR ALTER PROCEDURE dbo.usp_users_permissions
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT ISNULL(EditUsers, 0)    AS EditUsers,
           ISNULL(MaskApproval, 0) AS MaskApproval,
           ISNULL(GWUserCreate, 0) AS GWUserCreate
    FROM dbo.users
    WHERE id = @user_id;
END;
GO