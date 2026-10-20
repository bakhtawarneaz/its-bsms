CREATE OR ALTER PROCEDURE dbo.usp_profile_get
    @user_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT
        id,
        username,
        fullname,
        user_mobile,
        email,
        user_type,
        account_type,
        balance,
        [status],
        user_expiry,
        user_creation,
        LowBalanceLimit AS low_balance_limit
    FROM dbo.users
    WHERE id = @user_id;
END;