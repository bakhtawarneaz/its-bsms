-- A user's contact groups with member counts (legacy view_group_DAL):
-- total (first result set) and one page, newest first (second).
CREATE OR ALTER PROCEDURE dbo.usp_groups_list
    @user_id INT,
    @search  NVARCHAR(100) = NULL,
    @offset  INT           = 0,
    @limit   INT           = 25
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS total_rows
    FROM dbo.groups_view_count WITH (NOLOCK)
    WHERE user_id = @user_id
      AND (@search IS NULL OR groupname LIKE N'%' + @search + N'%');

    SELECT id, groupname, description, crtime, total_contact
    FROM dbo.groups_view_count WITH (NOLOCK)
    WHERE user_id = @user_id
      AND (@search IS NULL OR groupname LIKE N'%' + @search + N'%')
    ORDER BY id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY;
END;
GO