-- Sender context for compose pages: account flags (first result set) and the user's
-- active masks with their promotional flag (second result set).
CREATE OR ALTER PROCEDURE dbo.usp_compose_context
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT account_type, balance, ISNULL(allow_int, 0) AS allow_int, ISNULL(IsOtpAllow, 0) AS otp_allow,
           ISNULL(camp_approve, 0) AS camp_approve
    FROM dbo.users WITH (NOLOCK)
    WHERE id = @user_id;

    SELECT masking, MAX(CAST(Promotional AS INT)) AS promotional
    FROM dbo.all_masking WITH (NOLOCK)
    WHERE user_id = @user_id AND Status = 1
    GROUP BY masking
    ORDER BY masking;
END;
GO