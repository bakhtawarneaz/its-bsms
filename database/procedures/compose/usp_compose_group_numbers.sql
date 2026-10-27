-- Numbers of the chosen groups (legacy get_user_groups_DAL, also stamps groups.last_used)
-- or of all the user's contacts (legacy `allcontact`). @group_ids is a comma list.
CREATE OR ALTER PROCEDURE dbo.usp_compose_group_numbers
    @user_id   INT,
    @group_ids VARCHAR(MAX) = '',
    @all       BIT          = 0
AS
BEGIN
    SET NOCOUNT ON;

    IF @all = 1
    BEGIN
        SELECT numbers FROM dbo.contacts WITH (NOLOCK) WHERE user_id = @user_id;
        RETURN;
    END;

    IF LEN(@group_ids) = 0
        RETURN;

    DECLARE @ids XML = CAST('<i>' + REPLACE(@group_ids, ',', '</i><i>') + '</i>' AS XML);
    DECLARE @groups TABLE (id INT PRIMARY KEY);

    INSERT INTO @groups (id)
    SELECT DISTINCT g.id
    FROM dbo.[groups] g
    WHERE g.user_id = @user_id
      AND g.id IN (SELECT n.v.value('.', 'INT') FROM @ids.nodes('/i') AS n(v));

    UPDATE dbo.[groups] SET last_used = GETDATE() WHERE id IN (SELECT id FROM @groups);

    SELECT gc.numbers
    FROM dbo.groupsandcontacts gc WITH (NOLOCK)
    WHERE gc.user_id = @user_id AND gc.group_id IN (SELECT id FROM @groups);
END;
GO