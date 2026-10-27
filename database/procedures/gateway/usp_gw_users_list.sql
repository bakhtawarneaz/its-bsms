-- Paged ITSSMSGateway user directory (same SQL Server instance as ITSBSMS, like the legacy code).
-- Result set 1: page rows with their distinct masks. Result set 2: counts per state.
CREATE OR ALTER PROCEDURE dbo.usp_gw_users_list
    @search       NVARCHAR(100) = NULL,
    @state        VARCHAR(10)   = NULL,
    @user_type    VARCHAR(10)   = NULL,
    @account_type VARCHAR(10)   = NULL,
    @page         INT = 1,
    @per_page     INT = 20
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    DECLARE @term NVARCHAR(100) = NULLIF(LTRIM(RTRIM(@search)), N'');
    DECLARE @like NVARCHAR(102) = N'%' + @term + N'%';

    IF @page < 1 SET @page = 1;
    IF @per_page NOT BETWEEN 1 AND 100 SET @per_page = 20;

    SELECT u.Id, u.Username, u.Fullname, u.Email, u.Mobile, u.Balance, u.AccountType, u.UserType,
           u.ExpiryDateTime, u.Status, u.AuthorizedDomain, u.DefaultMask, u.WebUsername,
           CASE WHEN ISNULL(u.permanentblock, 0) = 1 THEN 'blocked'
                WHEN u.Status <> 1                   THEN 'disabled'
                WHEN u.ExpiryDateTime < GETDATE()    THEN 'expired'
                ELSE 'active' END AS state
    INTO #base
    FROM ITSSMSGateway.dbo.Users u
    WHERE (@term IS NULL
           OR u.Username LIKE @like OR u.Fullname LIKE @like OR u.WebUsername LIKE @like
           OR CAST(u.Id AS NVARCHAR(12)) = @term
           OR EXISTS (SELECT 1 FROM ITSSMSGateway.dbo.MaskingRoutes mr WHERE mr.UserId = u.Id AND mr.Mask LIKE @like))
      AND (@user_type IS NULL OR u.UserType = @user_type)
      AND (@account_type IS NULL OR u.AccountType = @account_type);

    -- 1) Requested page; masks are collected only for the visible rows
    SELECT b.*,
           STUFF((SELECT DISTINCT N', ' + mr.Mask
                  FROM ITSSMSGateway.dbo.MaskingRoutes mr WITH (NOLOCK)
                  WHERE mr.UserId = b.Id
                  FOR XML PATH(''), TYPE).value('.', 'NVARCHAR(MAX)'), 1, 2, N'') AS masks
    FROM #base b
    WHERE @state IS NULL OR b.state = @state
    ORDER BY b.Id DESC
    OFFSET (@page - 1) * @per_page ROWS FETCH NEXT @per_page ROWS ONLY;

    -- 2) Counts per state for the tabs
    SELECT state, COUNT(*) AS total FROM #base GROUP BY state;

    DROP TABLE #base;
END;
GO