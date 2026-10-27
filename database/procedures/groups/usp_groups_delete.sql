-- Deletes a group together with its contacts (legacy Del_Group_DAL). Links of those
-- contacts in other groups are removed too so no orphan rows remain.
-- Returns OK (+ groupname, contacts_deleted) / NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_groups_delete
    @user_id INT,
    @id      INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @name NVARCHAR(100) = (SELECT groupname FROM dbo.[groups] WHERE id = @id AND user_id = @user_id);

    IF @name IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    DECLARE @contacts TABLE (contact_id INT PRIMARY KEY);
    DECLARE @deleted INT;

    BEGIN TRANSACTION;

    INSERT INTO @contacts (contact_id)
    SELECT DISTINCT contact_id
    FROM dbo.user_groups_contacts
    WHERE group_id = @id AND user_id = @user_id;

    DELETE FROM dbo.user_groups_contacts
    WHERE user_id = @user_id
      AND (group_id = @id OR contact_id IN (SELECT contact_id FROM @contacts));

    DELETE FROM dbo.contacts
    WHERE user_id = @user_id AND id IN (SELECT contact_id FROM @contacts);

    SET @deleted = @@ROWCOUNT;

    DELETE FROM dbo.[groups] WHERE id = @id AND user_id = @user_id;

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @name AS groupname, @deleted AS contacts_deleted;
END;
GO