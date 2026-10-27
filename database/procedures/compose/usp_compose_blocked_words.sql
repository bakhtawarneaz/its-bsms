-- Words that may not appear in a message (legacy Check_Blocked_Text_DAL).
CREATE OR ALTER PROCEDURE dbo.usp_compose_blocked_words
AS
BEGIN
    SET NOCOUNT ON;

    SELECT blocked_word FROM dbo.blocked_text WITH (NOLOCK);
END;
GO