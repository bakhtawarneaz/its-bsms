-- Gateway SMS summary export (legacy export_admin_GWuser_summary), same filters as the page.
CREATE OR ALTER PROCEDURE dbo.usp_reports_gw_summary_export
    @user_id  INT          = NULL,
    @from     DATE,
    @to       DATE,
    @sender   VARCHAR(100) = NULL,
    @operator VARCHAR(50)  = NULL,
    @sms_type VARCHAR(50)  = NULL,
    @status   INT          = NULL,
    @max_rows INT          = 500000
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (@max_rows)
           Id, CONVERT(VARCHAR(10), SummaryDate, 23) AS summary_date, Username, Sender,
           CASE Status
               WHEN 1 THEN 'Send'                WHEN 2 THEN 'Sending'
               WHEN 3 THEN 'Deliveredtonetwork'  WHEN 4 THEN 'Deliveredtohandset'
               WHEN 5 THEN 'Not Sent'            WHEN 6 THEN 'Deliveryfailed'
               WHEN 7 THEN 'Blocked'             WHEN 8 THEN 'Stopped'
               WHEN 9 THEN 'Stopped'             WHEN 10 THEN 'Stopped'
               ELSE 'Undefined'
           END AS status,
           [Count], Cost, Operator
    FROM ITSSMSGateway.dbo.ITSSMSDetailedSummary WITH (NOLOCK)
    WHERE DbName = 'ITSSMSGateway'
      AND SummaryDate BETWEEN @from AND @to
      AND (@user_id IS NULL  OR UserId = @user_id)
      AND (@sender IS NULL   OR Sender = @sender)
      AND (@operator IS NULL OR Operator LIKE '%' + @operator + '%')
      AND (@status IS NULL OR Status = @status OR (@status = 8 AND Status IN (9, 10)))
    ORDER BY Id DESC
    OPTION (RECOMPILE);
END;
GO