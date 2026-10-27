-- Edits a contact (superadmin may edit any user's contact). The number may change too, but
-- must stay unique among that owner's contacts. NULL optional fields keep their old value.
-- Returns OK / NUMBER_TAKEN / NOT_FOUND.
CREATE OR ALTER PROCEDURE dbo.usp_contacts_update
    @id       INT,
    @number   VARCHAR(20)   = NULL,
    @fullname NVARCHAR(150),
    @email    NVARCHAR(150) = NULL,
    @option1  NVARCHAR(250) = NULL,
    @option2  NVARCHAR(250) = NULL,
    @option3  NVARCHAR(250) = NULL
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @owner INT = (SELECT user_id FROM dbo.contacts WHERE id = @id);

    IF @owner IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status;
        RETURN;
    END;

    IF @number IS NOT NULL
       AND @number <> (SELECT numbers FROM dbo.contacts WHERE id = @id)
       AND EXISTS (SELECT 1 FROM dbo.contacts WITH (NOLOCK)
                   WHERE user_id = @owner AND numbers = @number AND id <> @id)
    BEGIN
        SELECT 'NUMBER_TAKEN' AS status;
        RETURN;
    END;

    UPDATE dbo.contacts
    SET numbers  = COALESCE(@number, numbers),
        fullname = @fullname,
        emails   = COALESCE(@email, emails),
        option1  = COALESCE(@option1, option1),
        option2  = COALESCE(@option2, option2),
        option3  = COALESCE(@option3, option3)
    WHERE id = @id;

    SELECT 'OK' AS status;
END;
GO