CREATE OR ALTER PROCEDURE dbo.usp_auth_block_user
    @login     NVARCHAR(500),   -- username or email
    @site_name NVARCHAR(50)
AS
BEGIN
    SET NOCOUNT ON;

    -- Block only active users so disabled/suspended statuses are preserved
    UPDATE dbo.users
    SET [status] = 3
    WHERE site_name = @site_name
      AND (username = @login OR email = @login)
      AND [status] = 1;

    SELECT @@ROWCOUNT AS blocked;
END;