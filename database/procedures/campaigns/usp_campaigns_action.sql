-- Approve / stop / play / suspend / reschedule a campaign (legacy Super_Admin_*_Campaign_DAL and
-- Campapprover_*_DAL), in one transaction over campaign, Camp_Bulk and outbox_Camp.
-- Allowed only while SMS remain:
--   approve    approval (2)          → campaign 1, batches 0 → 1
--   stop       running (1) / approval (2) → campaign 0, batches 0/1 → 12
--   play       stopped (0)           → campaign 1, batches 12 → 1
--   suspend    stopped (0) / approval (2) → campaign 4 + end time, batches 0/12 → 11
--   reschedule stopped (0), @at in future → camp_time / senttime of held batches
-- Personalized campaigns keep their messages in outbox: approve 0 → 1, suspend 0 → 11 there too.
-- @scope_id: campapprover id → only campaigns of its users.
-- Returns OK / NOT_FOUND / FINISHED / NOT_ALLOWED.
CREATE OR ALTER PROCEDURE dbo.usp_campaigns_action
    @id       INT,
    @action   VARCHAR(12),
    @at       DATETIME2 = NULL,
    @scope_id INT       = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @user_id INT, @status INT, @remaining INT;

    SELECT @user_id = user_id, @status = status_camp, @remaining = ISNULL(total_sms, 0) - ISNULL(SUM(ob_starts), 0)
    FROM dbo.campain_manag WITH (NOLOCK)
    WHERE id = @id
    GROUP BY user_id, status_camp, total_sms;

    IF @user_id IS NULL
       OR (@scope_id IS NOT NULL AND NOT EXISTS (
            SELECT 1 FROM dbo.users WITH (NOLOCK) WHERE id = @user_id AND reseller_id = @scope_id AND permanentblock = 0))
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    IF @remaining = 0 AND @status <> 2
    BEGIN
        SELECT 'FINISHED' AS status;
        RETURN;
    END;

    IF NOT ((@action = 'approve' AND @status = 2)
         OR (@action = 'stop' AND @status IN (1, 2))
         OR (@action = 'play' AND @status = 0)
         OR (@action = 'suspend' AND @status IN (0, 2))
         OR (@action = 'reschedule' AND @status = 0 AND @at > GETDATE()))
    BEGIN
        SELECT 'NOT_ALLOWED' AS status;
        RETURN;
    END;

    DECLARE @personalized BIT = CASE WHEN EXISTS (
        SELECT 1 FROM dbo.campaign c WITH (NOLOCK)
        JOIN dbo.outbox_sms_type t WITH (NOLOCK) ON t.smstypecode = c.camp_smstype AND t.smstypes = 'Personalize'
        WHERE c.id = @id) THEN 1 ELSE 0 END;

    BEGIN TRANSACTION;

    IF @action = 'approve'
    BEGIN
        UPDATE dbo.campaign SET status_camp = 1 WHERE id = @id;
        UPDATE dbo.Camp_Bulk SET status = 1 WHERE camp_id = @id AND user_id = @user_id AND status = 0;
        UPDATE dbo.outbox_Camp SET status = 1 WHERE camp_id = @id AND user_id = @user_id AND status = 0;
        IF @personalized = 1
            UPDATE dbo.outbox SET status = 1 WHERE camp_id = @id AND user_id = @user_id AND status = 0;
    END
    ELSE IF @action = 'stop'
    BEGIN
        UPDATE dbo.campaign SET status_camp = 0 WHERE id = @id;
        UPDATE dbo.Camp_Bulk SET status = 12 WHERE camp_id = @id AND user_id = @user_id AND status IN (0, 1);
        UPDATE dbo.outbox_Camp SET status = 12 WHERE camp_id = @id AND user_id = @user_id AND status IN (0, 1);
    END
    ELSE IF @action = 'play'
    BEGIN
        UPDATE dbo.campaign SET status_camp = 1 WHERE id = @id;
        UPDATE dbo.Camp_Bulk SET status = 1 WHERE camp_id = @id AND user_id = @user_id AND status = 12;
        UPDATE dbo.outbox_Camp SET status = 1 WHERE camp_id = @id AND user_id = @user_id AND status = 12;
    END
    ELSE IF @action = 'suspend'
    BEGIN
        UPDATE dbo.campaign SET status_camp = 4, camp_end_time = CURRENT_TIMESTAMP WHERE id = @id;
        UPDATE dbo.Camp_Bulk SET status = 11 WHERE camp_id = @id AND user_id = @user_id AND status IN (0, 12);
        UPDATE dbo.outbox_Camp SET status = 11 WHERE camp_id = @id AND user_id = @user_id AND status IN (0, 12);
        IF @personalized = 1
            UPDATE dbo.outbox SET status = 11 WHERE camp_id = @id AND user_id = @user_id AND status = 0;
    END
    ELSE
    BEGIN
        UPDATE dbo.campaign SET camp_time = @at WHERE id = @id;
        UPDATE dbo.Camp_Bulk SET senttime = @at WHERE camp_id = @id AND user_id = @user_id AND status = 12;
        UPDATE dbo.outbox_Camp SET senttime = @at WHERE camp_id = @id AND user_id = @user_id AND status = 12;
    END;

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @user_id AS user_id;
END;
GO