-- Inbox page (legacy view_admin_inbox_DAL): newest first, one optional field filter.
CREATE OR ALTER PROCEDURE dbo.usp_reports_inbox_list
    @field  VARCHAR(20)   = NULL,
    @value  NVARCHAR(500) = NULL,
    @offset INT           = 0,
    @limit  INT           = 26
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, user_id, sender, prefix, receiver, msgdata, senttime, operator
    FROM dbo.inbox_view WITH (NOLOCK)
    WHERE @field IS NULL
       OR (@field = 'id'       AND id = TRY_CONVERT(BIGINT, @value))
       OR (@field = 'user_id'  AND user_id = TRY_CONVERT(INT, @value))
       OR (@field = 'prefix'   AND prefix = @value)
       OR (@field = 'sender'   AND sender = @value)
       OR (@field = 'receiver' AND receiver = @value)
       OR (@field = 'msgdata'  AND msgdata LIKE N'%' + @value + N'%')
       OR (@field = 'operator' AND operator = @value)
    ORDER BY id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
    OPTION (RECOMPILE);
END;
GO