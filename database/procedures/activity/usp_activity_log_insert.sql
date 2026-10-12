CREATE OR ALTER PROCEDURE dbo.usp_activity_log_insert
    @user_id        BIGINT,
    @activity       NVARCHAR(MAX),
    @ip             NVARCHAR(20),
    @login_location NVARCHAR(MAX) = NULL
AS
BEGIN
    SET NOCOUNT ON;

    INSERT INTO dbo.activity_log (activity, user_id, ip, act_time, login_location)
    VALUES (@activity, @user_id, @ip, GETDATE(), @login_location);

    SELECT CAST(SCOPE_IDENTITY() AS BIGINT) AS id;
END;