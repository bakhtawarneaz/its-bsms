-- Loads one user for the edit page.
-- Result set 1: user row (with admin / reporting usernames). Result set 2: masking ids.
CREATE OR ALTER PROCEDURE dbo.usp_users_get
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT u.id, u.username, u.fullname, u.email, u.user_mobile, u.user_type, u.account_type,
           u.balance, u.status, u.user_expiry, u.user_creation, u.CustID AS customer_id,
           u.reseller_id, u.reporting_user_id, ISNULL(u.permanentblock, 0) AS permanentblock,
           a.username AS admin_username, r.username AS reporting_username
    FROM dbo.users u
    LEFT JOIN dbo.users a ON a.id = u.reseller_id
    LEFT JOIN dbo.users r ON r.id = u.reporting_user_id
    WHERE u.id = @user_id AND u.user_type <> 'superadmin';

    SELECT masking_id
    FROM dbo.user_maskings
    WHERE user_id = @user_id;
END;
GO