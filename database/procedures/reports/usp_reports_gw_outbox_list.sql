-- Gateway outbox page (legacy view_Admin_gwoutbox_DAL): newest first, one optional field filter.
-- Status codes are returned as the legacy labels.
CREATE OR ALTER PROCEDURE dbo.usp_reports_gw_outbox_list
    @field  VARCHAR(20)   = NULL,
    @value  NVARCHAR(500) = NULL,
    @offset INT           = 0,
    @limit  INT           = 26
AS
BEGIN
    SET NOCOUNT ON;

    SELECT Id AS id, UserId AS user_id, Sender AS sender, Receiver AS receiver, MsgData AS msgdata,
           CreatedDatetime AS senttime, DTN AS network_time,
           CASE Status
               WHEN 1 THEN 'Send'                WHEN 2 THEN 'Sending'
               WHEN 3 THEN 'Deliveredtonetwork'  WHEN 4 THEN 'Deliveredtohandset'
               WHEN 5 THEN 'Not Sent'            WHEN 6 THEN 'Deliveryfailed'
               WHEN 7 THEN 'Blocked'             WHEN 8 THEN 'Stopped'
               WHEN 9 THEN 'Stopped'             WHEN 10 THEN 'Stopped'
               ELSE 'Undefined'
           END AS status,
           Cost AS cost, Operator AS operator
    FROM ITSSMSGateway.dbo.Outbox WITH (NOLOCK)
    WHERE @field IS NULL
       OR (@field = 'id'       AND Id = TRY_CONVERT(BIGINT, @value))
       OR (@field = 'user_id'  AND UserId = TRY_CONVERT(INT, @value))
       OR (@field = 'sender'   AND LOWER(Sender) = LOWER(@value))
       OR (@field = 'receiver' AND Receiver = @value)
       OR (@field = 'status'   AND Status = TRY_CONVERT(INT, @value))
       OR (@field = 'msgdata'  AND MsgData LIKE N'%' + @value + N'%')
       OR (@field = 'operator' AND Operator = @value)
    ORDER BY Id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
    OPTION (RECOMPILE);
END;
GO