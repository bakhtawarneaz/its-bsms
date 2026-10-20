-- Paged masking directory with the route and status of every network.
-- Result set 1: page rows. Result set 2: counts per state (other filters applied).
-- state: unrouted (no masking_routes rows) / active (any network on) / inactive.
CREATE OR ALTER PROCEDURE dbo.usp_maskings_list
    @search      NVARCHAR(100) = NULL,
    @promotional TINYINT       = NULL,
    @state       VARCHAR(10)   = NULL,
    @page        INT = 1,
    @per_page    INT = 25
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    DECLARE @term NVARCHAR(100) = NULLIF(LTRIM(RTRIM(@search)), N'');
    DECLARE @like NVARCHAR(102) = N'%' + @term + N'%';

    IF @page < 1 SET @page = 1;
    IF @per_page NOT BETWEEN 1 AND 100 SET @per_page = 25;

    -- One row per mask. Warid (9232) mirrors Jazz (9230), so it is not shown separately.
    SELECT r.mask,
           MAX(CASE WHEN r.network = '9230' THEN r.route END)               AS jazz_route,
           MAX(CASE WHEN r.network = '9230' THEN r.Operator END)            AS jazz_operator,
           MAX(CASE WHEN r.network = '9230' THEN CAST(r.Status AS INT) END) AS jazz_status,
           MAX(CASE WHEN r.network = '9231' THEN r.route END)               AS zong_route,
           MAX(CASE WHEN r.network = '9231' THEN r.Operator END)            AS zong_operator,
           MAX(CASE WHEN r.network = '9231' THEN CAST(r.Status AS INT) END) AS zong_status,
           MAX(CASE WHEN r.network = '9233' THEN r.route END)               AS ufone_route,
           MAX(CASE WHEN r.network = '9233' THEN r.Operator END)            AS ufone_operator,
           MAX(CASE WHEN r.network = '9233' THEN CAST(r.Status AS INT) END) AS ufone_status,
           MAX(CASE WHEN r.network = '9234' THEN r.route END)               AS telenor_route,
           MAX(CASE WHEN r.network = '9234' THEN r.Operator END)            AS telenor_operator,
           MAX(CASE WHEN r.network = '9234' THEN CAST(r.Status AS INT) END) AS telenor_status,
           MAX(CASE WHEN r.network = '9235' THEN r.route END)               AS scom_route,
           MAX(CASE WHEN r.network = '9235' THEN r.Operator END)            AS scom_operator,
           MAX(CASE WHEN r.network = '9235' THEN CAST(r.Status AS INT) END) AS scom_status,
           MAX(CAST(ISNULL(r.Promotional, 0) AS INT))                       AS promotional,
           SUM(CASE WHEN CAST(r.Status AS INT) = 1 THEN 1 ELSE 0 END)        AS active_routes
    INTO #routes
    FROM dbo.masking_routes r
    GROUP BY r.mask;

    SELECT m.id, m.masking, m.crtime,
           rt.jazz_route, rt.jazz_operator, rt.jazz_status,
           rt.zong_route, rt.zong_operator, rt.zong_status,
           rt.ufone_route, rt.ufone_operator, rt.ufone_status,
           rt.telenor_route, rt.telenor_operator, rt.telenor_status,
           rt.scom_route, rt.scom_operator, rt.scom_status,
           rt.promotional,
           CASE WHEN rt.mask IS NULL THEN 'unrouted'
                WHEN rt.active_routes > 0 THEN 'active'
                ELSE 'inactive' END AS state
    INTO #base
    FROM dbo.masking m
    LEFT JOIN #routes rt ON rt.mask = m.masking
    WHERE (@term IS NULL OR m.masking LIKE @like OR CAST(m.id AS NVARCHAR(12)) = @term)
      AND (@promotional IS NULL OR rt.promotional = @promotional);

    -- 1) Requested page (user counts only for the visible rows)
    SELECT b.*,
           (SELECT COUNT(*) FROM dbo.user_maskings um WHERE um.masking_id = b.id) AS users_count
    FROM #base b
    WHERE @state IS NULL OR b.state = @state
    ORDER BY b.id DESC
    OFFSET (@page - 1) * @per_page ROWS FETCH NEXT @per_page ROWS ONLY;

    -- 2) Counts per state for the tabs
    SELECT state, COUNT(*) AS total FROM #base GROUP BY state;

    DROP TABLE #routes;
    DROP TABLE #base;
END;
GO