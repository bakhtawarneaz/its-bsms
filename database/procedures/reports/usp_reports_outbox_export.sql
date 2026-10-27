-- Outbox export (legacy export_Admin_Outbox / OutboxArchive / OutboxCamp DALs):
-- sent-time range plus optional sender / receiver / SMS type, capped at @max_rows.
CREATE OR ALTER PROCEDURE dbo.usp_reports_outbox_export
    @source   VARCHAR(10)  = 'live',
    @from     DATETIME,
    @to       DATETIME,
    @sender   VARCHAR(100) = NULL,
    @receiver VARCHAR(20)  = NULL,
    @sms_type VARCHAR(20)  = NULL,
    @max_rows INT          = 500000
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @view SYSNAME = CASE @source
        WHEN 'live'     THEN N'outbox_view'
        WHEN 'archive'  THEN N'outbox_archive_view'
        WHEN 'campaign' THEN N'outbox_Camp_view'
    END;

    IF @view IS NULL
    BEGIN
        RAISERROR('Unknown outbox source.', 16, 1);
        RETURN;
    END;

    DECLARE @sql NVARCHAR(MAX) = N'
        SELECT TOP (@max_rows)
               id, user_id, ' + CASE WHEN @source = 'campaign' THEN N'camp_id, ' ELSE N'' END + N'
               outboxsmstype, sender, receiver,
               CONVERT(VARCHAR(19), senttime, 20)           AS senttime,
               CONVERT(VARCHAR(19), deliveredtonetwork, 20) AS network_time,
               outboxstatus, cost, operator, msgdata
        FROM dbo.' + QUOTENAME(@view) + N' WITH (NOLOCK)
        WHERE senttime BETWEEN @from AND @to
          AND (@sender IS NULL   OR LOWER(sender) = LOWER(@sender))
          AND (@receiver IS NULL OR receiver = @receiver)
          AND (@sms_type IS NULL OR smstype = @sms_type)
        ORDER BY id DESC
        OPTION (RECOMPILE);';

    EXEC sys.sp_executesql @sql,
        N'@from DATETIME, @to DATETIME, @sender VARCHAR(100), @receiver VARCHAR(20), @sms_type VARCHAR(20), @max_rows INT',
        @from, @to, @sender, @receiver, @sms_type, @max_rows;
END;
GO