-- Gateway inbox page (legacy view_Admin_gwinbox_DAL): newest first, one optional field filter.
CREATE OR ALTER PROCEDURE dbo.usp_reports_gw_inbox_list
    @field  VARCHAR(20)   = NULL,
    @value  NVARCHAR(500) = NULL,
    @offset INT           = 0,
    @limit  INT           = 26
AS
BEGIN
    SET NOCOUNT ON;

    SELECT Id AS id, UserId AS user_id, Sender AS sender, Receiver AS receiver, MsgData AS msgdata,
           CreatedDateTime AS senttime, SentDateTime AS network_time, Cost AS cost, Operator AS operator
    FROM ITSSMSGateway.dbo.Inbox WITH (NOLOCK)
    WHERE @field IS NULL
       OR (@field = 'id'       AND Id = TRY_CONVERT(BIGINT, @value))
       OR (@field = 'user_id'  AND UserId = TRY_CONVERT(INT, @value))
       OR (@field = 'sender'   AND Sender = @value)
       OR (@field = 'receiver' AND Receiver = @value)
       OR (@field = 'msgdata'  AND MsgData LIKE N'%' + @value + N'%')
    ORDER BY Id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
    OPTION (RECOMPILE);
END;
GO