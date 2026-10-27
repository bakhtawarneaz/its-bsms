-- Deletes one of the user's templates. Returns OK / NOT_FOUND and the deleted name.
CREATE OR ALTER PROCEDURE dbo.usp_templates_delete
    @user_id INT,
    @id      INT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @deleted TABLE (template_name NVARCHAR(100));

    DELETE FROM dbo.users_template
    OUTPUT deleted.template_name INTO @deleted
    WHERE id = @id AND user_id = @user_id;

    IF NOT EXISTS (SELECT 1 FROM @deleted)
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    SELECT 'OK' AS status, template_name FROM @deleted;
END;
GO