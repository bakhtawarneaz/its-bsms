-- Gateway outbox export (legacy export_Admin_Gwoutbox_DAL): created-time range plus optional sender / receiver.
CREATE OR ALTER PROCEDURE dbo.usp_reports_gw_outbox_export
    @from     DATETIME,
    @to       DATETIME,
    @sender   VARCHAR(100) = NULL,
    @receiver VARCHAR(100) = NULL,
    @max_rows INT          = 500000
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (@max_rows)
           Id, UserId, Sender, Receiver,
           CONVERT(VARCHAR(19), CreatedDatetime, 20) AS sent_time,
           CONVERT(VARCHAR(19), DTN, 20)             AS network_time,
           CASE Status
               WHEN 1 THEN 'Send'                WHEN 2 THEN 'Sending'
               WHEN 3 THEN 'Deliveredtonetwork'  WHEN 4 THEN 'Deliveredtohandset'
               WHEN 5 THEN 'Not Sent'            WHEN 6 THEN 'Deliveryfailed'
               WHEN 7 THEN 'Blocked'             WHEN 8 THEN 'Stopped'
               WHEN 9 THEN 'Stopped'             WHEN 10 THEN 'Stopped'
               ELSE 'Undefined'
           END AS status,
           Cost, Operator, MsgData
    FROM ITSSMSGateway.dbo.Outbox WITH (NOLOCK)
    WHERE CreatedDatetime BETWEEN @from AND @to
      AND (@sender IS NULL   OR LOWER(Sender) = LOWER(@sender))
      AND (@receiver IS NULL OR Receiver = @receiver)
    ORDER BY Id DESC
    OPTION (RECOMPILE);
END;
GO