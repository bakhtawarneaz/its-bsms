-- Activity logs (legacy view_super_admin_user_activitylogs_DAL): total for the filters
-- (first result set) and one page of rows, newest first (second result set).
CREATE OR ALTER PROCEDURE dbo.usp_reports_activity_list
    @from    DATETIME,
    @to      DATETIME,
    @user_id INT           = NULL,
    @ip      VARCHAR(50)   = NULL,
    @search  NVARCHAR(200) = NULL,
    @offset  INT           = 0,
    @limit   INT           = 25
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS total_rows
    FROM dbo.activitylogs_view WITH (NOLOCK)
    WHERE activity_time BETWEEN @from AND @to
      AND (@user_id IS NULL OR user_id = @user_id)
      AND (@ip IS NULL      OR ipaddress = @ip)
      AND (@search IS NULL  OR activity LIKE N'%' + @search + N'%')
    OPTION (RECOMPILE);

    SELECT id, activity_time, [user] AS username, ipaddress, activity
    FROM dbo.activitylogs_view WITH (NOLOCK)
    WHERE activity_time BETWEEN @from AND @to
      AND (@user_id IS NULL OR user_id = @user_id)
      AND (@ip IS NULL      OR ipaddress = @ip)
      AND (@search IS NULL  OR activity LIKE N'%' + @search + N'%')
    ORDER BY id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
    OPTION (RECOMPILE);
END;
GO