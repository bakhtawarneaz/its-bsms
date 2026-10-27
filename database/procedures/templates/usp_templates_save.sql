-- Adds a template (@id NULL; name must be unique per user) or updates the message of an
-- existing one (legacy: the name is not editable). Returns OK / NAME_TAKEN / NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_templates_save
    @user_id INT,
    @id      INT            = NULL,
    @name    NVARCHAR(100)  = NULL,
    @message NVARCHAR(2000)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @id IS NULL
    BEGIN
        IF EXISTS (SELECT 1 FROM dbo.users_template WITH (UPDLOCK, HOLDLOCK)
                   WHERE user_id = @user_id AND template_name = @name)
        BEGIN
            SELECT 'NAME_TAKEN' AS status;
            RETURN;
        END;

        INSERT INTO dbo.users_template (user_id, template_name, msgdata)
        VALUES (@user_id, @name, @message);

        SELECT 'OK' AS status, CAST(SCOPE_IDENTITY() AS INT) AS id;
        RETURN;
    END;

    UPDATE dbo.users_template
    SET msgdata = @message
    WHERE id = @id AND user_id = @user_id;

    SELECT CASE WHEN @@ROWCOUNT = 0 THEN 'NOT_FOUND' ELSE 'OK' END AS status, @id AS id;
END;
GO