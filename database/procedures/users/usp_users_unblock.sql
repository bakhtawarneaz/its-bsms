-- Clears the permanent login lock set after repeated failed sign-ins.
CREATE OR ALTER PROCEDURE dbo.usp_users_unblock
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.users
    SET permanentblock = 0
    WHERE id = @user_id AND user_type <> 'superadmin';

    SELECT @@ROWCOUNT AS affected,
           (SELECT username FROM dbo.users WHERE id = @user_id) AS username;
END;
GO