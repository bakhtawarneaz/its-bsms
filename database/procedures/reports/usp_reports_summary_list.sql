-- SMS summary (legacy view_Admin_user_summary): totals for the filters (first result set)
-- and one page of rows, newest first (second result set).
CREATE OR ALTER PROCEDURE dbo.usp_reports_summary_list
    @user_id  INT         = NULL,
    @from     DATE,
    @to       DATE,
    @sender   VARCHAR(100) = NULL,
    @operator VARCHAR(50)  = NULL,
    @sms_type VARCHAR(50)  = NULL,
    @status   VARCHAR(50)  = NULL,
    @offset   INT          = 0,
    @limit    INT          = 25
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS total_rows,
           SUM(CAST(smscount AS BIGINT)) AS total_sms,
           SUM(CAST(smscost AS FLOAT))   AS total_cost
    FROM dbo.network_summary_view WITH (NOLOCK)
    WHERE summary_date BETWEEN @from AND @to
      AND (@user_id IS NULL  OR user_id = @user_id)
      AND (@sender IS NULL   OR sender = @sender)
      AND (@operator IS NULL OR route_name = @operator)
      AND (@sms_type IS NULL OR view_smstype = @sms_type)
      AND (@status IS NULL   OR LOWER(view_status) = LOWER(@status))
    OPTION (RECOMPILE);

    SELECT id, username, summary_date, view_smstype AS sms_type, sender,
           route_name AS operator, view_status AS status, smscount AS sms_count, smscost AS cost
    FROM dbo.network_summary_view WITH (NOLOCK)
    WHERE summary_date BETWEEN @from AND @to
      AND (@user_id IS NULL  OR user_id = @user_id)
      AND (@sender IS NULL   OR sender = @sender)
      AND (@operator IS NULL OR route_name = @operator)
      AND (@sms_type IS NULL OR view_smstype = @sms_type)
      AND (@status IS NULL   OR LOWER(view_status) = LOWER(@status))
    ORDER BY id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
    OPTION (RECOMPILE);
END;
GO