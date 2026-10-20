-- Removes one masking from a user (used when maskings are edited).
CREATE OR ALTER PROCEDURE dbo.usp_user_masking_remove
    @user_id    INT,
    @masking_id INT
AS
BEGIN
    SET NOCOUNT ON;

    DELETE FROM dbo.user_maskings
    WHERE user_id = @user_id AND masking_id = @masking_id;

    SELECT @@ROWCOUNT AS removed;
END;
GO