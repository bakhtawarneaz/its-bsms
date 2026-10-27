-- The user's groups with member counts for the campaign audience picker.
CREATE OR ALTER PROCEDURE dbo.usp_compose_groups
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, groupname, total_contact
    FROM dbo.groups_view_count WITH (NOLOCK)
    WHERE user_id = @user_id
    ORDER BY groupname;
END;
GO