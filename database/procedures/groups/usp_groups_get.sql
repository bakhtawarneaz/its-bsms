-- One of the user's groups with its member count.
CREATE OR ALTER PROCEDURE dbo.usp_groups_get
    @user_id INT,
    @id      INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, groupname, description, crtime, total_contact
    FROM dbo.groups_view_count WITH (NOLOCK)
    WHERE user_id = @user_id AND id = @id;
END;
GO