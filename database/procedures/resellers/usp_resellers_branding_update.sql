-- Updates a reseller's branding (legacy "Edit Key User" for resellers updated the colors by domain).
-- The logo is only replaced when a new path is passed. Creates the template row when it is missing.
-- Returns OK / NOT_FOUND / NO_DOMAIN with the username. The caller owns the transaction.
CREATE OR ALTER PROCEDURE dbo.usp_resellers_branding_update
    @user_id      INT,
    @company_name NVARCHAR(200),
    @slogan       NVARCHAR(300),
    @logo_path    VARCHAR(300) = NULL,
    @first_color  VARCHAR(10),
    @second_color VARCHAR(10),
    @third_color  VARCHAR(10),
    @fourth_color VARCHAR(10),
    @dark_theme   BIT
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @domain VARCHAR(200), @username VARCHAR(100);

    SELECT @domain = site_name, @username = username
    FROM dbo.users WITH (UPDLOCK, ROWLOCK)
    WHERE id = @user_id AND user_type = 'reseller';

    IF @username IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status, CAST(NULL AS VARCHAR(100)) AS username;
        RETURN;
    END;

    IF NULLIF(LTRIM(RTRIM(@domain)), '') IS NULL
    BEGIN
        SELECT 'NO_DOMAIN' AS status, @username AS username;
        RETURN;
    END;

    IF EXISTS (SELECT 1 FROM dbo.reseller_template_info WITH (UPDLOCK, HOLDLOCK)
               WHERE domain COLLATE DATABASE_DEFAULT = @domain COLLATE DATABASE_DEFAULT)
    BEGIN
        UPDATE dbo.reseller_template_info
        SET name         = @company_name,
            slogan       = @slogan,
            favicon      = ISNULL(@logo_path, favicon),
            first_color  = @first_color,
            second_color = @second_color,
            third_color  = @third_color,
            fourth_color = @fourth_color,
            dark_theme   = @dark_theme
        WHERE domain COLLATE DATABASE_DEFAULT = @domain COLLATE DATABASE_DEFAULT;
    END
    ELSE
    BEGIN
        INSERT INTO dbo.reseller_template_info
            (name, domain, slogan, favicon, first_color, second_color, third_color, fourth_color, dark_theme)
        VALUES
            (@company_name, @domain, @slogan, @logo_path, @first_color, @second_color, @third_color, @fourth_color, @dark_theme);
    END;

    SELECT 'OK' AS status, @username AS username;
END;
GO