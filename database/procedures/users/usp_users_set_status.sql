-- Turns sign-in on (1) or off (0) for a user. Returns the affected username.
CREATE OR ALTER PROCEDURE dbo.usp_users_set_status
    @user_id INT,
    @status  TINYINT
AS
BEGIN
    SET NOCOUNT ON;

    UPDATE dbo.users
    SET status = @status
    WHERE id = @user_id AND user_type <> 'superadmin';

    SELECT @@ROWCOUNT AS affected,
           (SELECT username FROM dbo.users WHERE id = @user_id) AS username;
END;
GO