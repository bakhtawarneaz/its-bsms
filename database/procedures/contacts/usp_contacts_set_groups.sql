-- Sets which of the user's groups a contact belongs to: ticked groups are linked, unticked
-- ones are unlinked. @group_ids is a comma list ('' clears all). SQL Server 2014 safe.
-- Returns OK (+ added, removed) / NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_contacts_set_groups
    @user_id    INT,
    @contact_id INT,
    @group_ids  VARCHAR(MAX) = ''
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF NOT EXISTS (SELECT 1 FROM dbo.contacts WHERE id = @contact_id AND user_id = @user_id)
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    DECLARE @wanted TABLE (group_id INT PRIMARY KEY);
    DECLARE @added INT, @removed INT;

    IF LEN(@group_ids) > 0
    BEGIN
        DECLARE @ids XML = CAST('<i>' + REPLACE(@group_ids, ',', '</i><i>') + '</i>' AS XML);

        INSERT INTO @wanted (group_id)
        SELECT DISTINCT g.id
        FROM dbo.[groups] g
        WHERE g.user_id = @user_id
          AND g.id IN (SELECT n.v.value('.', 'INT') FROM @ids.nodes('/i') AS n(v));
    END;

    BEGIN TRANSACTION;

    DELETE gc
    FROM dbo.user_groups_contacts gc
    INNER JOIN dbo.[groups] g ON g.id = gc.group_id
    WHERE gc.contact_id = @contact_id
      AND g.user_id = @user_id
      AND gc.group_id NOT IN (SELECT group_id FROM @wanted);

    SET @removed = @@ROWCOUNT;

    INSERT INTO dbo.user_groups_contacts (user_id, group_id, contact_id)
    SELECT @user_id, w.group_id, @contact_id
    FROM @wanted w
    WHERE NOT EXISTS (SELECT 1 FROM dbo.user_groups_contacts gc
                      WHERE gc.group_id = w.group_id AND gc.contact_id = @contact_id);

    SET @added = @@ROWCOUNT;

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @added AS added, @removed AS removed;
END;
GO