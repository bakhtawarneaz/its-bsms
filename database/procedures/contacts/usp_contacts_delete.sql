-- Deletes a contact and its group links (legacy Del_Contact_DAL; superadmin: any user's).
-- Returns OK (+ numbers) / NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_contacts_delete
    @id INT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @number VARCHAR(20) = (SELECT numbers FROM dbo.contacts WHERE id = @id);

    IF @number IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    BEGIN TRANSACTION;

    DELETE FROM dbo.user_groups_contacts WHERE contact_id = @id;
    DELETE FROM dbo.contacts WHERE id = @id;

    COMMIT TRANSACTION;

    SELECT 'OK' AS status, @number AS numbers;
END;
GO