-- Paged reseller directory with branding and sub-user counts.
-- Result set 1: page rows. Result set 2: counts per state.
-- state: blocked > disabled > expired > active (same rules as usp_users_list).
CREATE OR ALTER PROCEDURE dbo.usp_resellers_list
    @search   NVARCHAR(100) = NULL,
    @state    VARCHAR(10)   = NULL,
    @page     INT = 1,
    @per_page INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    DECLARE @today DATE = CAST(GETDATE() AS DATE);
    DECLARE @term  NVARCHAR(100) = NULLIF(LTRIM(RTRIM(@search)), N'');
    DECLARE @like  NVARCHAR(102) = N'%' + @term + N'%';

    IF @page < 1 SET @page = 1;
    IF @per_page NOT BETWEEN 1 AND 100 SET @per_page = 20;

    SELECT u.id, u.username, u.fullname, u.email, u.user_mobile, u.account_type, u.balance,
           u.status, u.user_expiry, u.user_creation, u.site_name,
           t.name AS company_name, t.slogan, t.favicon,
           CASE WHEN ISNULL(u.permanentblock, 0) = 1 THEN 'blocked'
                WHEN u.status <> 1                   THEN 'disabled'
                WHEN u.user_expiry < @today          THEN 'expired'
                ELSE 'active' END AS state
    INTO #base
    FROM dbo.users u
    OUTER APPLY (
        SELECT TOP 1 ti.name, ti.slogan, ti.favicon
        FROM dbo.reseller_template_info ti
        WHERE ti.domain COLLATE DATABASE_DEFAULT = u.site_name COLLATE DATABASE_DEFAULT
    ) t
    WHERE u.user_type = 'reseller'
      AND (@term IS NULL
           OR u.username LIKE @like OR u.fullname LIKE @like
           OR u.site_name LIKE @like OR t.name LIKE @like
           OR CAST(u.id AS NVARCHAR(12)) = @term);

    -- 1) Requested page (sub-user counts only for visible rows)
    SELECT b.*,
           (SELECT COUNT(*) FROM dbo.users c WHERE c.reseller_id = b.id) AS users_count
    FROM #base b
    WHERE @state IS NULL OR b.state = @state
    ORDER BY b.id DESC
    OFFSET (@page - 1) * @per_page ROWS FETCH NEXT @per_page ROWS ONLY;

    -- 2) Counts per state for the tabs
    SELECT state, COUNT(*) AS total FROM #base GROUP BY state;

    DROP TABLE #base;
END;
GO