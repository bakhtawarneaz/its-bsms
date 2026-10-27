-- Users for the SMS summary account filter.
CREATE OR ALTER PROCEDURE dbo.usp_reports_summary_users
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, username
    FROM dbo.users WITH (NOLOCK)
    ORDER BY username;
END;
GO