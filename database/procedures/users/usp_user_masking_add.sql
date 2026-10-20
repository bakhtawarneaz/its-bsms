CREATE OR ALTER PROCEDURE dbo.usp_user_masking_add
    @user_id     INT,
    @masking_id  INT,
    @reseller_id INT
AS
BEGIN
    SET NOCOUNT ON;

    IF NOT EXISTS (SELECT 1 FROM dbo.user_maskings WHERE user_id = @user_id AND masking_id = @masking_id)
        INSERT INTO dbo.user_maskings (user_id, masking_id, reseller_id)
        VALUES (@user_id, @masking_id, @reseller_id);

    SELECT 1 AS done;
END;