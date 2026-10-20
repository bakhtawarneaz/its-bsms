-- Pending masking requests (approval = 0) sent by admins/resellers, newest first.
-- Result set 1: page rows. Result set 2: total pending.
CREATE OR ALTER PROCEDURE dbo.usp_masking_requests_list
    @page     INT = 1,
    @per_page INT = 25
AS
BEGIN
    SET NOCOUNT ON;
    SET TRANSACTION ISOLATION LEVEL READ UNCOMMITTED;

    IF @page < 1 SET @page = 1;
    IF @per_page NOT BETWEEN 1 AND 100 SET @per_page = 25;

    SELECT r.id, r.reseller_id, u.username AS requested_by,
           r.fullname, r.nic_no, r.c_numbers, r.masking_name, r.masking_letter, r.crtime,
                      CASE WHEN EXISTS (SELECT 1 FROM dbo.masking m
                             WHERE m.masking = r.masking_name COLLATE SQL_Latin1_General_CP1_CS_AS) THEN 1 ELSE 0 END AS exists_now
    FROM dbo.masking_request r
    LEFT JOIN dbo.users u ON u.id = r.reseller_id
    WHERE r.approval = 0
    ORDER BY r.id DESC
    OFFSET (@page - 1) * @per_page ROWS FETCH NEXT @per_page ROWS ONLY;

    SELECT COUNT(*) AS total FROM dbo.masking_request WHERE approval = 0;
END;
GO