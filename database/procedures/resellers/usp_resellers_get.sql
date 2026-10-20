-- Loads one reseller for the branding page.
-- Result set 1: account summary. Result set 2: its reseller_template_info row (may be empty).
CREATE OR ALTER PROCEDURE dbo.usp_resellers_get
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT u.id, u.username, u.fullname, u.site_name, u.account_type, u.balance, u.status,
           u.user_expiry, u.user_creation, ISNULL(u.permanentblock, 0) AS permanentblock,
           (SELECT COUNT(*) FROM dbo.users c WHERE c.reseller_id = u.id) AS users_count
    FROM dbo.users u
    WHERE u.id = @user_id AND u.user_type = 'reseller';

    SELECT TOP 1 t.name, t.slogan, t.favicon, t.first_color, t.second_color, t.third_color, t.fourth_color,
           CAST(ISNULL(t.dark_theme, 0) AS INT) AS dark_theme
    FROM dbo.reseller_template_info t
    JOIN dbo.users u ON t.domain COLLATE DATABASE_DEFAULT = u.site_name COLLATE DATABASE_DEFAULT
    WHERE u.id = @user_id AND u.user_type = 'reseller';
END;
GO