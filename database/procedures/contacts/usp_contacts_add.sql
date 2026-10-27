-- Adds a contact for the user (legacy Add_contact_DAL), optionally linked to one of the
-- user's groups. The number must be unique among the user's contacts.
-- Returns OK (+ id) / NUMBER_TAKEN / GROUP_NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_contacts_add
    @user_id  INT,
    @fullname NVARCHAR(150),
    @number   VARCHAR(20),
    @email    NVARCHAR(150) = NULL,
    @option1  NVARCHAR(250) = NULL,
    @option2  NVARCHAR(250) = NULL,
    @option3  NVARCHAR(250) = NULL,
    @group_id INT           = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    IF EXISTS (SELECT 1 FROM dbo.contacts WITH (NOLOCK) WHERE user_id = @user_id AND numbers = @number)
    BEGIN
        SELECT 'NUMBER_TAKEN' AS status;
        RETURN;
    END;

    IF @group_id IS NOT NULL AND NOT EXISTS (SELECT 1 FROM dbo.[groups] WHERE id = @group_id AND user_id = @user_id)
    BEGIN
        SELECT 'GROUP_NOT_FOUND' AS status;
        RETURN;
    END;

    DECLARE @id INT;

    BEGIN TRANSACTION;

    INSERT INTO dbo.contacts (user_id, fullname, numbers, emails, option1, option2, option3)
    VALUES (@user_id, @fullname, @number, @email, @option1, @option2, @option3);

    SET @id = CAST(SCOPE_IDENTITY() AS INT);

    IF @group_id IS NOT NULL
        INSERT INTO dbo.user_groups_contacts (user_id, group_id, contact_id) VALUES (@user_id, @group_id, @id);

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @id AS id;
END;
GO