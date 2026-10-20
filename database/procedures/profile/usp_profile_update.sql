CREATE OR ALTER PROCEDURE dbo.usp_profile_update
    @user_id           INT,
    @fullname          NVARCHAR(50),
    @mobiles           NVARCHAR(100),   -- comma-separated, normalized numbers
    @emails            NVARCHAR(500),   -- comma-separated, validated emails
    @low_balance_limit BIGINT = NULL    -- NULL keeps the current limit
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @previous TABLE (fullname NVARCHAR(50), user_mobile NVARCHAR(100), email NVARCHAR(500));

    UPDATE dbo.users
    SET fullname        = @fullname,
        user_mobile     = @mobiles,
        email           = @emails,
        contact_updated = 1,
        LowBalanceLimit = ISNULL(@low_balance_limit, LowBalanceLimit)
    OUTPUT deleted.fullname, deleted.user_mobile, deleted.email INTO @previous
    WHERE id = @user_id;

    -- Return the old values so the caller can notify the account owner
    SELECT fullname, user_mobile, email FROM @previous;
END;