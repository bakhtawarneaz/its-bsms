-- Gateway SMS summary (legacy view_SuperAdmin_gwuser_summary): totals (first result set)
-- and one page of rows (second). @status 8 means stopped (8, 9, 10). @sms_type is unused here.
CREATE OR ALTER PROCEDURE dbo.usp_reports_gw_summary_list
    @user_id  INT          = NULL,
    @from     DATE,
    @to       DATE,
    @sender   VARCHAR(100) = NULL,
    @operator VARCHAR(50)  = NULL,
    @sms_type VARCHAR(50)  = NULL,
    @status   INT          = NULL,
    @offset   INT          = 0,
    @limit    INT          = 25
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS total_rows,
           SUM(CAST([Count] AS BIGINT)) AS total_sms,
           SUM(CAST(Cost AS FLOAT))     AS total_cost
    FROM ITSSMSGateway.dbo.ITSSMSDetailedSummary WITH (NOLOCK)
    WHERE DbName = 'ITSSMSGateway'
      AND SummaryDate BETWEEN @from AND @to
      AND (@user_id IS NULL  OR UserId = @user_id)
      AND (@sender IS NULL   OR Sender = @sender)
      AND (@operator IS NULL OR Operator LIKE '%' + @operator + '%')
      AND (@status IS NULL OR Status = @status OR (@status = 8 AND Status IN (9, 10)))
    OPTION (RECOMPILE);

    SELECT Id AS id, SummaryDate AS summary_date, Username AS username, Sender AS sender,
           [Count] AS sms_count, Cost AS cost,
           CASE Status
               WHEN 1 THEN 'Send'                WHEN 2 THEN 'Sending'
               WHEN 3 THEN 'Deliveredtonetwork'  WHEN 4 THEN 'Deliveredtohandset'
               WHEN 5 THEN 'Not Sent'            WHEN 6 THEN 'Deliveryfailed'
               WHEN 7 THEN 'Blocked'             WHEN 8 THEN 'Stopped'
               WHEN 9 THEN 'Stopped'             WHEN 10 THEN 'Stopped'
               ELSE 'Undefined'
           END AS status,
           Operator AS operator
    FROM ITSSMSGateway.dbo.ITSSMSDetailedSummary WITH (NOLOCK)
    WHERE DbName = 'ITSSMSGateway'
      AND SummaryDate BETWEEN @from AND @to
      AND (@user_id IS NULL  OR UserId = @user_id)
      AND (@sender IS NULL   OR Sender = @sender)
      AND (@operator IS NULL OR Operator LIKE '%' + @operator + '%')
      AND (@status IS NULL OR Status = @status OR (@status = 8 AND Status IN (9, 10)))
    ORDER BY Id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
    OPTION (RECOMPILE);
END;
GO