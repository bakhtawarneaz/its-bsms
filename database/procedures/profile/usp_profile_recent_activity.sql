CREATE OR ALTER PROCEDURE dbo.usp_profile_recent_activity
    @user_id INT,
    @limit   INT = 6
AS
BEGIN
    SET NOCOUNT ON;

    SELECT TOP (@limit) activity, ip, act_time
    FROM dbo.activity_log
    WHERE user_id = @user_id
    ORDER BY id DESC;
END;