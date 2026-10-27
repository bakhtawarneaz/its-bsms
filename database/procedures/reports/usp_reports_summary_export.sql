-- SMS summary export (legacy export_Admin_user_summary), same filters as the page.
CREATE OR ALTER PROCEDURE dbo.usp_reports_summary_export
    @user_id  INT          = NULL,
    @from     DATE,
    @to       DATE,
    @sender   VARCHAR(100) = NULL,
    @operator VARCHAR(50)  = NULL,
    @sms_type VARCHAR(50)  = NULL,
    @status   VARCHAR(50)  = NULL,
    @max_rows INT          = 500000
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (@max_rows)
           id, username, CONVERT(VARCHAR(10), summary_date, 23) AS summary_date,
           view_smstype, sender, route_name, view_status, smscount, smscost
    FROM dbo.network_summary_view WITH (NOLOCK)
    WHERE summary_date BETWEEN @from AND @to
      AND (@user_id IS NULL  OR user_id = @user_id)
      AND (@sender IS NULL   OR sender = @sender)
      AND (@operator IS NULL OR route_name = @operator)
      AND (@sms_type IS NULL OR view_smstype = @sms_type)
      AND (@status IS NULL   OR LOWER(view_status) = LOWER(@status))
    ORDER BY id DESC
    OPTION (RECOMPILE);
END;
GO