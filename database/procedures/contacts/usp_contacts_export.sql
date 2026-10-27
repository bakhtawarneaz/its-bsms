-- Contacts export, one row per contact with its groups comma-joined.
--   @group_id set      → that group of @user_id
--   @user_id set       → all contacts of @user_id
--   both NULL          → every user's contacts, filtered like the contacts page (@field / @value)
-- Newest first, capped at @max_rows; ids are collected first so the big table is read by index.
CREATE OR ALTER PROCEDURE dbo.usp_contacts_export
    @user_id  INT           = NULL,
    @group_id INT           = NULL,
    @field    VARCHAR(20)   = NULL,
    @value    NVARCHAR(200) = NULL,
    @max_rows INT           = 250000
AS
BEGIN
    SET NOCOUNT ON;

    CREATE TABLE #ids (id INT PRIMARY KEY);

    IF @group_id IS NOT NULL
        INSERT INTO #ids (id)
        SELECT TOP (@max_rows) d.contact_id
        FROM (SELECT DISTINCT gc.contact_id
              FROM dbo.user_groups_contacts gc WITH (NOLOCK)
              WHERE gc.group_id = @group_id AND gc.user_id = @user_id) d
        ORDER BY d.contact_id DESC;
    ELSE IF @user_id IS NOT NULL
        INSERT INTO #ids (id)
        SELECT TOP (@max_rows) id
        FROM dbo.contacts WITH (NOLOCK)
        WHERE user_id = @user_id
        ORDER BY id DESC;
    ELSE IF @field = 'groupname'
        INSERT INTO #ids (id)
        SELECT TOP (@max_rows) c.id
        FROM dbo.contacts c WITH (NOLOCK)
        WHERE c.id IN (SELECT gc.contact_id
                       FROM dbo.user_groups_contacts gc WITH (NOLOCK)
                       INNER JOIN dbo.[groups] g WITH (NOLOCK) ON g.id = gc.group_id
                       WHERE g.groupname = @value)
        ORDER BY c.id DESC
        OPTION (RECOMPILE);
    ELSE
        INSERT INTO #ids (id)
        SELECT TOP (@max_rows) c.id
        FROM dbo.contacts c WITH (NOLOCK)
        WHERE @field IS NULL
           OR (@field = 'id'       AND c.id = TRY_CONVERT(INT, @value))
           OR (@field = 'user_id'  AND c.user_id = TRY_CONVERT(INT, @value))
           OR (@field = 'fullname' AND c.fullname LIKE @value + N'%')
           OR (@field = 'numbers'  AND c.numbers = @value)
           OR (@field = 'option1'  AND c.option1 = @value)
           OR (@field = 'option2'  AND c.option2 = @value)
           OR (@field = 'option3'  AND c.option3 = @value)
        ORDER BY c.id DESC
        OPTION (RECOMPILE);

    SELECT c.id, c.user_id, c.fullname, c.numbers, c.emails,
           STUFF((
               SELECT N', ' + g.groupname
               FROM dbo.user_groups_contacts gc WITH (NOLOCK)
               INNER JOIN dbo.[groups] g WITH (NOLOCK) ON g.id = gc.group_id
               WHERE gc.contact_id = c.id
               ORDER BY g.groupname
               FOR XML PATH(''), TYPE
           ).value('.', 'NVARCHAR(MAX)'), 1, 2, N'') AS groupnames,
           c.option1, c.option2, c.option3
    FROM #ids i
    INNER JOIN dbo.contacts c WITH (NOLOCK) ON c.id = i.id
    ORDER BY c.id DESC;
END;
GO