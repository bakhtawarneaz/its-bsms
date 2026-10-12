CREATE OR ALTER PROCEDURE dbo.usp_dashboard_summary
    @from        DATE,
    @to          DATE,
    @user_id     BIGINT = NULL,   -- limit to one account
    @reseller_id BIGINT = NULL    -- limit to a reseller and its users
AS
BEGIN
    SET NOCOUNT ON;
    -- Dashboards tolerate dirty reads (same as the legacy NOLOCK reads) and must not block sending
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    DECLARE @days       INT  = DATEDIFF(DAY, @from, @to) + 1;
    DECLARE @prev_to    DATE = DATEADD(DAY, -1, @from);
    DECLARE @prev_from  DATE = DATEADD(DAY, -@days, @from);
    DECLARE @month_from DATE = DATEADD(MONTH, -11, DATEFROMPARTS(YEAR(@to), MONTH(@to), 1));

    CREATE TABLE #scope (id BIGINT PRIMARY KEY);

    IF @reseller_id IS NOT NULL
        INSERT INTO #scope (id)
        SELECT id FROM dbo.users
        WHERE (id = @reseller_id OR reseller_id = @reseller_id) AND permanentblock = 0;

    -- Normalize the summary rows for the period once, then aggregate them several ways
    SELECT
        summary_date,
        CAST(smscount AS BIGINT) AS smscount,
        CAST(smscost  AS BIGINT) AS smscost,
        CASE
            WHEN route_name IN ('Mobilink', 'Warid', 'Jazz')      THEN 'Jazz'
            WHEN route_name IN ('Zong', 'Ufone', 'Telenor')       THEN route_name
            ELSE 'Other'
        END AS operator,
        CASE
            WHEN status_name = 'Delivered'                        THEN 'delivered'
            WHEN status_name = 'Delivery Failed'                  THEN 'failed'
            WHEN status_name IN ('Pending', 'Send', 'Sending')    THEN 'pending'
            ELSE 'stopped'
        END AS status_group,
        ISNULL(NULLIF(view_smstype, ''), 'Other') AS sms_type,
        ISNULL(NULLIF(sender, ''), 'Unknown')     AS sender,
        ISNULL(username, 'Unknown')               AS username
    INTO #rows
    FROM dbo.network_summary_view
    WHERE summary_date BETWEEN @from AND @to
      AND (@user_id IS NULL OR user_id = @user_id)
      AND (@reseller_id IS NULL OR user_id IN (SELECT id FROM #scope))
    OPTION (RECOMPILE);

    -- 1. Totals
    SELECT
        ISNULL(SUM(smscount), 0)                                                  AS messages,
        ISNULL(SUM(smscost), 0)                                                   AS credits,
        ISNULL(SUM(CASE WHEN status_group = 'delivered' THEN smscount END), 0)    AS delivered,
        ISNULL(SUM(CASE WHEN status_group = 'failed'    THEN smscount END), 0)    AS failed,
        ISNULL(SUM(CASE WHEN status_group = 'pending'   THEN smscount END), 0)    AS pending
    FROM #rows;

    -- 2. Per day
    SELECT
        summary_date                                                        AS day,
        SUM(smscount)                                                       AS messages,
        SUM(smscost)                                                        AS credits,
        SUM(CASE WHEN status_group = 'delivered' THEN smscount ELSE 0 END)  AS delivered,
        SUM(CASE WHEN status_group = 'failed'    THEN smscount ELSE 0 END)  AS failed
    FROM #rows
    GROUP BY summary_date
    ORDER BY summary_date;

    -- 3. Per operator
    SELECT
        operator,
        SUM(smscount)                                                       AS messages,
        SUM(CASE WHEN status_group = 'delivered' THEN smscount ELSE 0 END)  AS delivered,
        SUM(CASE WHEN status_group = 'failed'    THEN smscount ELSE 0 END)  AS failed
    FROM #rows
    GROUP BY operator;

    -- 4. Per delivery status
    SELECT status_group, SUM(smscount) AS messages
    FROM #rows
    GROUP BY status_group;

    -- 5. Per channel
    SELECT sms_type, SUM(smscount) AS messages
    FROM #rows
    GROUP BY sms_type
    ORDER BY messages DESC;

    -- 6. Last 12 months ending at @to (independent of the selected range)
    SELECT
        CONVERT(CHAR(7), summary_date, 120) AS month,
        SUM(CAST(smscount AS BIGINT))        AS messages
    FROM dbo.network_summary_view
    WHERE summary_date BETWEEN @month_from AND @to
      AND (@user_id IS NULL OR user_id = @user_id)
      AND (@reseller_id IS NULL OR user_id IN (SELECT id FROM #scope))
    GROUP BY CONVERT(CHAR(7), summary_date, 120)
    OPTION (RECOMPILE);

    -- 7. Incoming SMS per operator
    SELECT
        CASE
            WHEN operator IN ('Mobilink', 'Warid', 'Jazz')   THEN 'Jazz'
            WHEN operator IN ('Zong', 'Ufone', 'Telenor')    THEN operator
            ELSE 'Other'
        END            AS operator,
        COUNT_BIG(*)   AS messages
    FROM dbo.inbox_view
    WHERE senttime >= @from
      AND senttime < DATEADD(DAY, 1, @to)
      AND (@user_id IS NULL OR user_id = @user_id)
      AND (@reseller_id IS NULL OR user_id IN (SELECT id FROM #scope))
    GROUP BY
        CASE
            WHEN operator IN ('Mobilink', 'Warid', 'Jazz')   THEN 'Jazz'
            WHEN operator IN ('Zong', 'Ufone', 'Telenor')    THEN operator
            ELSE 'Other'
        END
    OPTION (RECOMPILE);

    -- 8. Previous period of the same length, for comparison
    SELECT
        ISNULL(SUM(CAST(smscount AS BIGINT)), 0)                                                    AS messages,
        ISNULL(SUM(CAST(smscost AS BIGINT)), 0)                                                     AS credits,
        ISNULL(SUM(CASE WHEN status_name = 'Delivered' THEN CAST(smscount AS BIGINT) END), 0)       AS delivered,
        ISNULL(SUM(CASE WHEN status_name = 'Delivery Failed' THEN CAST(smscount AS BIGINT) END), 0) AS failed,
        0                                                                                           AS pending
    FROM dbo.network_summary_view
    WHERE summary_date BETWEEN @prev_from AND @prev_to
      AND (@user_id IS NULL OR user_id = @user_id)
      AND (@reseller_id IS NULL OR user_id IN (SELECT id FROM #scope))
    OPTION (RECOMPILE);

    -- 9. Top sender IDs
    SELECT TOP (5) sender AS name, SUM(smscount) AS messages
    FROM #rows
    GROUP BY sender
    ORDER BY messages DESC;

    -- 10. Top accounts
    SELECT TOP (5) username AS name, SUM(smscount) AS messages
    FROM #rows
    GROUP BY username
    ORDER BY messages DESC;
END;