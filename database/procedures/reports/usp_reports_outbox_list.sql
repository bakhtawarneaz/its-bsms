-- Outbox pages (legacy view_admin_outbox / outboxarchive / outboxcamp DALs): newest first,
-- one optional field filter. @source picks a whitelisted view; no COUNT(*) on purpose,
-- the caller asks for one extra row to know if a next page exists.
CREATE OR ALTER PROCEDURE dbo.usp_reports_outbox_list
    @source VARCHAR(10)   = 'live',
    @field  VARCHAR(20)   = NULL,
    @value  NVARCHAR(500) = NULL,
    @offset INT           = 0,
    @limit  INT           = 26
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

    DECLARE @isCampaign BIT = CASE WHEN @source = 'campaign' THEN 1 ELSE 0 END;

    DECLARE @sql NVARCHAR(MAX) = N'
        SELECT id, user_id, ' + CASE WHEN @isCampaign = 1 THEN N'camp_id' ELSE N'CAST(NULL AS INT)' END + N' AS camp_id,
               outboxsmstype, sender, receiver, msgdata,
               senttime, deliveredtonetwork, outboxstatus, cost, operator
        FROM dbo.' + QUOTENAME(@view) + N' WITH (NOLOCK)
        WHERE @field IS NULL
           OR (@field = ''id''       AND id = TRY_CONVERT(BIGINT, @value))
           OR (@field = ''user_id''  AND user_id = TRY_CONVERT(INT, @value))
           OR (@field = ''sender''   AND LOWER(sender) = LOWER(@value))
           OR (@field = ''receiver'' AND receiver = @value)
           OR (@field = ''msgdata''  AND msgdata LIKE N''%'' + @value + N''%'')'
        + CASE WHEN @isCampaign = 1 THEN N'
           OR (@field = ''camp_id''  AND camp_id = TRY_CONVERT(INT, @value))' ELSE N'' END + N'
        ORDER BY id DESC
        OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
        OPTION (RECOMPILE);';

    EXEC sys.sp_executesql @sql,
        N'@field VARCHAR(20), @value NVARCHAR(500), @offset INT, @limit INT',
        @field, @value, @offset, @limit;
END;
GO