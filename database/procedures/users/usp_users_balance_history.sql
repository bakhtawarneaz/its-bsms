-- Latest credit movements of one user for the edit page.
CREATE OR ALTER PROCEDURE dbo.usp_users_balance_history
    @user_id INT,
    @limit   INT = 10
AS
BEGIN
    SET NOCOUNT ON;

    IF @limit NOT BETWEEN 1 AND 100 SET @limit = 10;

    SELECT TOP (@limit) id, purchased, availed, avail_time, details, ISNULL(BalanceType, 0) AS balance_type
    FROM dbo.user_balance_sheet WITH (NOLOCK)
    WHERE user_id = @user_id
    ORDER BY id DESC;
END;
GO