CREATE OR ALTER PROCEDURE dbo.usp_profile_change_password
    @user_id      INT,
    @current_hash NVARCHAR(32),
    @new_hash     NVARCHAR(32)
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @stored NVARCHAR(32) = (SELECT [password] FROM dbo.users WHERE id = @user_id);

    IF @stored IS NULL OR @stored <> @current_hash
    BEGIN
        SELECT 'WRONG_PASSWORD' AS result;
        RETURN;
    END;

    IF @stored = @new_hash
    BEGIN
        SELECT 'SAME_PASSWORD' AS result;
        RETURN;
    END;

    UPDATE dbo.users SET [password] = @new_hash WHERE id = @user_id;

    SELECT 'OK' AS result;
END;