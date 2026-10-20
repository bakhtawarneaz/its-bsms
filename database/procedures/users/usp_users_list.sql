-- Paged user directory for the superadmin users page.
-- Result set 1: page rows. Result set 2: counts per state (other filters applied).
-- state: blocked > disabled > expired > active (first match wins).
CREATE OR ALTER PROCEDURE dbo.usp_users_list
    @search       NVARCHAR(100) = NULL,
    @user_type    VARCHAR(13)   = NULL,
    @state        VARCHAR(10)   = NULL,
    @account_type VARCHAR(20)   = NULL,
    @page         INT = 1,
    @per_page     INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    DECLARE @today DATE = CAST(GETDATE() AS DATE);
    DECLARE @term  NVARCHAR(100) = NULLIF(LTRIM(RTRIM(@search)), N'');
    DECLARE @like  NVARCHAR(102) = N'%' + @term + N'%';

    IF @page < 1 SET @page = 1;
    IF @per_page NOT BETWEEN 1 AND 100 SET @per_page = 20;

    SELECT u.id, u.username, u.fullname, u.email, u.user_mobile, u.user_type,
           u.account_type, u.balance, u.status, u.user_expiry, u.user_creation,
           u.reseller_id, a.username AS admin_username,
           CASE WHEN ISNULL(u.permanentblock, 0) = 1 THEN 'blocked'
                WHEN u.status <> 1                   THEN 'disabled'
                WHEN u.user_expiry < @today          THEN 'expired'
                ELSE 'active' END AS state
    INTO #base
    FROM dbo.users u
    LEFT JOIN dbo.users a ON a.id = u.reseller_id
    WHERE u.user_type <> 'superadmin'
      AND (@user_type IS NULL OR u.user_type = @user_type)
      AND (@account_type IS NULL OR u.account_type = @account_type)
      AND (@term IS NULL
           OR u.username LIKE @like OR u.fullname LIKE @like
           OR u.email LIKE @like OR u.user_mobile LIKE @like
           OR CAST(u.id AS NVARCHAR(12)) = @term);

    -- 1) Requested page
    SELECT *
    FROM #base
    WHERE @state IS NULL OR state = @state
    ORDER BY id DESC
    OFFSET (@page - 1) * @per_page ROWS FETCH NEXT @per_page ROWS ONLY;

    -- 2) Counts per state for the tabs
    SELECT state, COUNT(*) AS total FROM #base GROUP BY state;

    DROP TABLE #base;
END;
GO