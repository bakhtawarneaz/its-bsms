-- Personalized messages (legacy View_Personalized_Camp_DAL / View_ApprovedPers_Camp_DAL):
-- @pending 1 → waiting approval (status 0), 0 → already approved / suspended (status <> 0).
-- Returns @limit + 1 rows so the caller knows whether a next page exists (no COUNT on outbox).
CREATE OR ALTER PROCEDURE dbo.usp_personalized_messages
    @scope_id INT           = NULL,
    @pending  BIT           = 1,
    @camp_id  INT           = NULL,
    @field    VARCHAR(20)   = NULL,
    @value    NVARCHAR(200) = NULL,
    @offset   INT           = 0,
    @limit    INT           = 50
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @code TINYINT = ISNULL((SELECT TOP (1) smstypecode FROM dbo.outbox_sms_type WITH (NOLOCK)
                                    WHERE smstypes = 'Personalize'), 0);

    SELECT o.id, o.camp_id, o.user_id, u.username, o.sender, o.receiver, o.msgdata, o.senttime,
           o.status, o.cost, o.operator
    FROM dbo.outbox o WITH (NOLOCK)
    JOIN dbo.users u WITH (NOLOCK) ON u.id = o.user_id
    WHERE o.smstype = @code
      AND ((@pending = 1 AND o.status = 0) OR (@pending = 0 AND o.status <> 0))
      AND (@scope_id IS NULL OR (u.reseller_id = @scope_id AND u.permanentblock = 0))
      AND (@camp_id IS NULL OR o.camp_id = @camp_id)
      AND (@field IS NULL
           OR (@field = 'id'       AND o.id = TRY_CONVERT(BIGINT, @value))
           OR (@field = 'camp_id'  AND o.camp_id = TRY_CONVERT(INT, @value))
           OR (@field = 'user_id'  AND o.user_id = TRY_CONVERT(INT, @value))
           OR (@field = 'receiver' AND o.receiver = @value)
           OR (@field = 'sender'   AND LOWER(o.sender) = LOWER(@value)))
    ORDER BY o.id DESC
    OFFSET @offset ROWS FETCH NEXT (@limit + 1) ROWS ONLY
    OPTION (RECOMPILE);
END;
GO