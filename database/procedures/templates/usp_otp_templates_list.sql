-- OTP templates of every user, newest first: total (first result set) and one page (second).
CREATE OR ALTER PROCEDURE dbo.usp_otp_templates_list
    @user_id INT = NULL,
    @offset  INT = 0,
    @limit   INT = 25
AS
BEGIN
    SET NOCOUNT ON;

    SELECT COUNT(*) AS total_rows
    FROM dbo.otp_templates WITH (NOLOCK)
    WHERE @user_id IS NULL OR user_id = @user_id;

    SELECT t.id, t.user_id, u.username, t.sender, t.TempId AS temp_id, t.otp_template,
           CAST(t.status AS INT) AS status, CAST(t.isUnicode AS INT) AS is_unicode
    FROM dbo.otp_templates t WITH (NOLOCK)
    LEFT JOIN dbo.users u WITH (NOLOCK) ON u.id = t.user_id
    WHERE @user_id IS NULL OR t.user_id = @user_id
    ORDER BY t.id DESC
    OFFSET @offset ROWS FETCH NEXT @limit ROWS ONLY;
END;
GO