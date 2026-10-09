CREATE OR ALTER PROCEDURE dbo.usp_auth_update_contact
    @user_id  INT,
    @fullname NVARCHAR(50),
    @mobiles  NVARCHAR(100),   -- comma-separated, normalized numbers
    @emails   NVARCHAR(500)    -- comma-separated, validated emails
AS
BEGIN
    SET NOCOUNT ON;

    -- Only users who have not yet confirmed their contact info can use this path
    UPDATE dbo.users
    SET fullname        = @fullname,
        user_mobile     = @mobiles,
        email           = @emails,
        contact_updated = 1
    WHERE id = @user_id
      AND contact_updated = 0;

    SELECT @@ROWCOUNT AS updated;
END;