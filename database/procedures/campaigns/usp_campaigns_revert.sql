-- Reverts the balance of a suspended campaign once (legacy USP_CampRevert): held messages
-- (status 11) are counted in outbox_Camp, then the archive, then Camp_Bulk batches not yet
-- expanded; credits = held × campaign cost. Campaign is marked StopCamp = 1 with the approver.
-- Returns OK (+ held, credits, user_id) / NOT_FOUND / NOTHING_HELD.
CREATE OR ALTER PROCEDURE dbo.usp_campaigns_revert
    @id          INT,
    @approver_id INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @user_id INT, @cost INT, @held INT, @credits INT;

    BEGIN TRANSACTION;

    SELECT @user_id = user_id, @cost = ISNULL(Cost, 1)
    FROM dbo.campaign WITH (UPDLOCK, HOLDLOCK)
    WHERE id = @id AND status_camp = 4 AND StopCamp = 0;

    IF @user_id IS NULL
    BEGIN
        ROLLBACK TRANSACTION;
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    SET @held = (SELECT COUNT(*) FROM dbo.outbox_Camp WITH (NOLOCK) WHERE camp_id = @id AND user_id = @user_id AND status = 11);

    IF @held = 0
        SET @held = (SELECT COUNT(*) FROM dbo.outbox_Camp_archive WITH (NOLOCK) WHERE camp_id = @id AND user_id = @user_id AND status = 11);

    IF @held = 0
        SET @held = ISNULL((SELECT SUM(count) FROM dbo.Camp_Bulk WITH (NOLOCK) WHERE camp_id = @id AND user_id = @user_id AND status = 11), 0);

    IF @held = 0
    BEGIN
        ROLLBACK TRANSACTION;
        SELECT 'NOTHING_HELD' AS status;
        RETURN;
    END;

    SET @credits = @held * @cost;

    UPDATE dbo.campaign SET StopCamp = 1, ApproveBy = @approver_id WHERE id = @id;
    UPDATE dbo.users SET Balance = Balance + @credits WHERE id = @user_id;

    INSERT INTO dbo.user_balance_sheet (user_id, purchased, availed, avail_time, details)
    VALUES (@user_id, @credits, 0, GETDATE(),
            'Stop Campaign SMS Balance reverted , SMSCost = ' + CONVERT(VARCHAR(4), @cost) + ', numbers =' + CONVERT(VARCHAR(12), @held));

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @held AS held, @credits AS credits, @user_id AS user_id;
END;
GO