-- A user's message templates (legacy view_template_DAL): total (first result set)
-- and one page, newest first (second). Search by name or message text.
CREATE OR ALTER PROCEDURE dbo.usp_templates_list
    @user_id INT,
    @search  NVARCHAR(200) = NULL,
    @offset  INT           = 0,
    @limit   INT           = 25
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS total_rows
    FROM dbo.users_template WITH (NOLOCK)
    WHERE user_id = @user_id
      AND (@search IS NULL OR template_name LIKE N'%' + @search + N'%' OR msgdata LIKE N'%' + @search + N'%');

    SELECT id, template_name, msgdata, crtime
    FROM dbo.users_template WITH (NOLOCK)
    WHERE user_id = @user_id
      AND (@search IS NULL OR template_name LIKE N'%' + @search + N'%' OR msgdata LIKE N'%' + @search + N'%')
    ORDER BY id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY;
END;
GO