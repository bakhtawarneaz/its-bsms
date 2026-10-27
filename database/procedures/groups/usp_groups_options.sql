-- The user's groups for pickers, by name.
CREATE OR ALTER PROCEDURE dbo.usp_groups_options
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, groupname
    FROM dbo.[groups] WITH (NOLOCK)
    WHERE user_id = @user_id
    ORDER BY groupname;
END;
GO