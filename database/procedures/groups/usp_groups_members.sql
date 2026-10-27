-- Contacts inside a group (legacy View_Contact_Group_DAL): total and one page by name.
-- @search matches name or email; @number matches the mobile exactly.
CREATE OR ALTER PROCEDURE dbo.usp_groups_members
    @user_id  INT,
    @group_id INT,
    @search   NVARCHAR(100) = NULL,
    @number   VARCHAR(20)   = NULL,
    @offset   INT           = 0,
    @limit    INT           = 25
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS total_rows
    FROM dbo.groupsandcontacts WITH (NOLOCK)
    WHERE user_id = @user_id AND group_id = @group_id
      AND (@search IS NULL OR fullname LIKE N'%' + @search + N'%' OR emails LIKE N'%' + @search + N'%')
      AND (@number IS NULL OR numbers = @number);

    SELECT id, fullname, numbers, emails
    FROM dbo.groupsandcontacts WITH (NOLOCK)
    WHERE user_id = @user_id AND group_id = @group_id
      AND (@search IS NULL OR fullname LIKE N'%' + @search + N'%' OR emails LIKE N'%' + @search + N'%')
      AND (@number IS NULL OR numbers = @number)
    ORDER BY fullname
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY;
END;
GO