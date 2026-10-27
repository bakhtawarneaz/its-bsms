-- Contacts page for the superadmin (all users). Reads one page straight from dbo.contacts
-- (no COUNT over ~150M rows; the caller asks for one extra row to know if a next page exists),
-- then adds each contact's groups as "id:name" items separated by U+2016.
CREATE OR ALTER PROCEDURE dbo.usp_contacts_list
    @field  VARCHAR(20)   = NULL,
    @value  NVARCHAR(200) = NULL,
    @offset INT           = 0,
    @limit  INT           = 26
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @page TABLE (
        id INT PRIMARY KEY, user_id INT, fullname NVARCHAR(MAX), numbers NVARCHAR(50),
        emails NVARCHAR(MAX), option1 NVARCHAR(MAX), option2 NVARCHAR(MAX), option3 NVARCHAR(MAX)
    );

    IF @field = 'groupname'
        INSERT INTO @page
        SELECT c.id, c.user_id, c.fullname, c.numbers, c.emails, c.option1, c.option2, c.option3
        FROM dbo.contacts c WITH (NOLOCK)
        WHERE c.id IN (SELECT gc.contact_id
                       FROM dbo.user_groups_contacts gc WITH (NOLOCK)
                       INNER JOIN dbo.[groups] g WITH (NOLOCK) ON g.id = gc.group_id
                       WHERE g.groupname = @value)
        ORDER BY c.id DESC
        OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
        OPTION (RECOMPILE);
    ELSE
        INSERT INTO @page
        SELECT c.id, c.user_id, c.fullname, c.numbers, c.emails, c.option1, c.option2, c.option3
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
        OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY
        OPTION (RECOMPILE);

    SELECT p.id, p.user_id, p.fullname, p.numbers, p.emails, p.option1, p.option2, p.option3,
           STUFF((
               SELECT NCHAR(8214) + CAST(g.id AS NVARCHAR(12)) + N':' + g.groupname
               FROM dbo.user_groups_contacts gc WITH (NOLOCK)
               INNER JOIN dbo.[groups] g WITH (NOLOCK) ON g.id = gc.group_id
               WHERE gc.contact_id = p.id
               ORDER BY g.groupname
               FOR XML PATH(''), TYPE
           ).value('.', 'NVARCHAR(MAX)'), 1, 1, N'') AS group_list
    FROM @page p
    ORDER BY p.id DESC;
END;
GO