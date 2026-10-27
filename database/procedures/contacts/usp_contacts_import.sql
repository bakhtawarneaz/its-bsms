-- Bulk import of the user's contacts (legacy InsertUserContactsXML / InsertContactsFromXML):
-- every row is inserted (no duplicate check against existing contacts, as before) and, when
-- @group_id is given, the new contacts are linked to that group.
-- XML shape: <contacts><contact><numbers/><fullname/><emails/><option1/><option2/><option3/></contact>…</contacts>
-- Returns OK (+ inserted) / GROUP_NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_contacts_import
    @user_id  INT,
    @group_id INT = NULL,
    @contacts XML
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF @group_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.[groups] WHERE id = @group_id AND user_id = @user_id)
    BEGIN
        SELECT 'GROUP_NOT_FOUND' AS status, 0 AS inserted;
        RETURN;
    END;

    DECLARE @now DATETIME = GETDATE();
    DECLARE @new TABLE (id INT PRIMARY KEY);

    INSERT INTO dbo.contacts (user_id, fullname, numbers, emails, option1, option2, option3, crtime)
    OUTPUT inserted.id INTO @new (id)
    SELECT @user_id,
           ISNULL(c.value('(fullname/text())[1]', 'NVARCHAR(50)'), N''),
           c.value('(numbers/text())[1]', 'NVARCHAR(15)'),
           ISNULL(c.value('(emails/text())[1]', 'NVARCHAR(50)'), N''),
           ISNULL(c.value('(option1/text())[1]', 'NVARCHAR(50)'), N''),
           ISNULL(c.value('(option2/text())[1]', 'NVARCHAR(50)'), N''),
           ISNULL(c.value('(option3/text())[1]', 'NVARCHAR(50)'), N''),
           @now
    FROM @contacts.nodes('/contacts/contact') AS t(c);

    IF @group_id IS NOT NULL
        INSERT INTO dbo.user_groups_contacts (user_id, group_id, contact_id)
        SELECT @user_id, @group_id, id FROM @new;

    SELECT 'OK' AS status, (SELECT COUNT(*) FROM @new) AS inserted;
END;
GO