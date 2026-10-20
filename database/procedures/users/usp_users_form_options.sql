-- Returns all dropdown data for the Add User wizard in 4 result sets:
-- 1) maskings, 2) admins/resellers, 3) users for reporting, 4) ERP customers.
-- The ERP call is guarded so environments without ITS_ERP still work.
CREATE OR ALTER PROCEDURE dbo.usp_users_form_options
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    -- 1) Maskings (sender IDs)
    SELECT id AS value, masking AS label
    FROM dbo.masking
    ORDER BY masking;

    -- 2) Active admins / resellers that a new user can belong to
    SELECT id AS value, username AS label
    FROM dbo.users
    WHERE user_type IN ('admin', 'reseller')
      AND user_expiry >= CAST(GETDATE() AS DATE)
      AND ISNULL(permanentblock, 0) = 0
    ORDER BY username;

    -- 3) Active sub users that a reporting user can be linked to
    SELECT id AS value, username AS label
    FROM dbo.users
    WHERE user_type = 'user'
      AND user_expiry >= CAST(GETDATE() AS DATE)
      AND ISNULL(permanentblock, 0) = 0
    ORDER BY username;

    -- 4) Customer master from ERP (Id, Name). Empty set when ITS_ERP is absent.
    IF DB_ID(N'ITS_ERP') IS NOT NULL
        EXEC (N'EXEC ITS_ERP.dbo.INV_WEB_GetAllCustomerMaster;');
    ELSE
        SELECT CAST(NULL AS INT) AS Id, CAST(NULL AS NVARCHAR(200)) AS Name
        WHERE 1 = 0;
END;
GO