-- Gateway inbox export (legacy export_Admin_Gwinbox_DAL): created-time range plus optional sender / receiver.
CREATE OR ALTER PROCEDURE dbo.usp_reports_gw_inbox_export
    @from     DATETIME,
    @to       DATETIME,
    @sender   VARCHAR(100) = NULL,
    @receiver VARCHAR(100) = NULL,
    @max_rows INT          = 500000
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (@max_rows)
           Id, UserId, Sender, Receiver, MsgData,
           CONVERT(VARCHAR(19), CreatedDateTime, 20) AS sent_time,
           CONVERT(VARCHAR(19), SentDateTime, 20)    AS network_time,
           Cost, Operator
    FROM ITSSMSGateway.dbo.Inbox WITH (NOLOCK)
    WHERE CreatedDateTime BETWEEN @from AND @to
      AND (@sender IS NULL   OR Sender = @sender)
      AND (@receiver IS NULL OR Receiver = @receiver)
    ORDER BY Id DESC
    OPTION (RECOMPILE);
END;
GO