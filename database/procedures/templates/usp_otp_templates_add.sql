-- Adds an OTP template (legacy Add_OTP_Template_DAL): the sender must be an active masking,
-- TempId is the user's next number, status starts at 1.
-- Returns OK (+ id, temp_id) / USER_NOT_FOUND / MASK_NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_otp_templates_add
    @user_id    INT,
    @sender     VARCHAR(50),
    @message    NVARCHAR(1000),
    @is_unicode BIT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF NOT EXISTS (SELECT 1 FROM dbo.users WITH (NOLOCK) WHERE id = @user_id)
    BEGIN
        SELECT 'USER_NOT_FOUND' AS status;
        RETURN;
    END;

    IF NOT EXISTS (SELECT 1 FROM dbo.masking WITH (NOLOCK) WHERE masking = @sender AND status <> 0)
    BEGIN
        SELECT 'MASK_NOT_FOUND' AS status;
        RETURN;
    END;

    BEGIN TRANSACTION;

    DECLARE @temp_id INT = ISNULL((SELECT MAX(TempId) FROM dbo.otp_templates WITH (UPDLOCK, HOLDLOCK)
                                   WHERE user_id = @user_id), 0) + 1;

    INSERT INTO dbo.otp_templates (user_id, sender, TempId, otp_template, status, isUnicode)
    VALUES (@user_id, @sender, @temp_id, @message, 1, @is_unicode);

    DECLARE @id INT = CAST(SCOPE_IDENTITY() AS INT);

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @id AS id, @temp_id AS temp_id;
END;
GO