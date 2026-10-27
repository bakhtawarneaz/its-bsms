-- Inbox export (legacy export_Admin_Inbox_DAL): received-time range plus optional sender / receiver.
CREATE OR ALTER PROCEDURE dbo.usp_reports_inbox_export
    @from     DATETIME,
    @to       DATETIME,
    @sender   VARCHAR(100) = NULL,
    @receiver VARCHAR(100) = NULL,
    @max_rows INT          = 500000
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (@max_rows)
           id, user_id, sender, prefix, receiver, msgdata,
           CONVERT(VARCHAR(19), senttime, 20) AS senttime, operator
    FROM dbo.inbox_view WITH (NOLOCK)
    WHERE senttime BETWEEN @from AND @to
      AND (@sender IS NULL   OR sender = @sender)
      AND (@receiver IS NULL OR receiver = @receiver)
    ORDER BY id DESC
    OPTION (RECOMPILE);
END;
GO