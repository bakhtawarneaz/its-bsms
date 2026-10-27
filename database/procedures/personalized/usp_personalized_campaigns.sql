-- Personalized campaigns with messages waiting for approval (legacy View_Personalized_Camp_DAL,
-- grouped per campaign): outbox rows with smstype Personalize and status 0.
-- @scope_id: campapprover id → only its users (users.reseller_id = @scope_id, not permanently blocked).
CREATE OR ALTER PROCEDURE dbo.usp_personalized_campaigns
    @scope_id INT = NULL
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @code TINYINT = ISNULL((SELECT TOP (1) smstypecode FROM dbo.outbox_sms_type WITH (NOLOCK)
                                    WHERE smstypes = 'Personalize'), 0);

    SELECT o.camp_id, o.user_id, u.username, c.camp_name, o.sender,
           MIN(o.senttime) AS camp_time, COUNT(*) AS pending, SUM(CAST(o.cost AS INT)) AS credits
    FROM dbo.outbox o WITH (NOLOCK)
    JOIN dbo.users u WITH (NOLOCK) ON u.id = o.user_id
    LEFT JOIN dbo.campaign c WITH (NOLOCK) ON c.id = o.camp_id
    WHERE o.status = 0
      AND o.smstype = @code
      AND (@scope_id IS NULL OR (u.reseller_id = @scope_id AND u.permanentblock = 0))
    GROUP BY o.camp_id, o.user_id, u.username, c.camp_name, o.sender
    ORDER BY o.camp_id DESC;
END;
GO