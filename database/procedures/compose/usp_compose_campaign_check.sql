-- Pre-checks before a campaign OTP is sent: unique name per user and a promotional
-- active mask (legacy Check_Masking_byid_DAL with Promotional = 1).
-- Returns OK / NAME_TAKEN / MASK_NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_compose_campaign_check
    @user_id   INT,
    @camp_name NVARCHAR(100),
    @sender    VARCHAR(15)
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (SELECT 1 FROM dbo.campaign WITH (NOLOCK) WHERE user_id = @user_id AND camp_name = @camp_name)
    BEGIN
        SELECT 'NAME_TAKEN' AS status;
        RETURN;
    END;

    IF NOT EXISTS (SELECT 1 FROM dbo.all_masking WITH (NOLOCK)
                   WHERE user_id = @user_id AND Status = 1 AND Promotional = 1 AND masking = @sender)
    BEGIN
        SELECT 'MASK_NOT_FOUND' AS status;
        RETURN;
    END;

    SELECT 'OK' AS status;
END;
GO