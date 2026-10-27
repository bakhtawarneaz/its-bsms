-- Adds (@id NULL) or edits a group. The name must be unique per user; on edit an empty
-- description keeps the old one (legacy). Returns OK (+ id) / NAME_TAKEN / NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_groups_save
    @user_id     INT,
    @id          INT           = NULL,
    @name        NVARCHAR(100),
    @description NVARCHAR(500) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF EXISTS (SELECT 1 FROM dbo.[groups] WITH (UPDLOCK, HOLDLOCK)
               WHERE user_id = @user_id AND groupname = @name AND (@id IS NULL OR id <> @id))
    BEGIN
        SELECT 'NAME_TAKEN' AS status;
        RETURN;
    END;

    IF @id IS NULL
    BEGIN
        INSERT INTO dbo.[groups] (user_id, groupname, description)
        VALUES (@user_id, @name, @description);

        SELECT 'OK' AS status, CAST(SCOPE_IDENTITY() AS INT) AS id;
        RETURN;
    END;

    UPDATE dbo.[groups]
    SET groupname   = @name,
        description = COALESCE(@description, description)
    WHERE id = @id AND user_id = @user_id;

    SELECT CASE WHEN @@ROWCOUNT = 0 THEN 'NOT_FOUND' ELSE 'OK' END AS status, @id AS id;
END;
GO