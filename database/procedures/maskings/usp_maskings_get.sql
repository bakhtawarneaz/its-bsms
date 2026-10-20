-- Loads one masking for the edit routes page.
-- Result set 1: masking row. Result set 2: its masking_routes rows.
CREATE OR ALTER PROCEDURE dbo.usp_maskings_get
    @masking_id INT
AS
BEGIN
    SET NOCOUNT ON;

    SELECT id, masking, crtime, status
    FROM dbo.masking
    WHERE id = @masking_id;

    SELECT CAST(r.network AS VARCHAR(10)) AS network, r.route, r.Operator AS operator,
           CAST(r.Status AS INT) AS status, CAST(ISNULL(r.Promotional, 0) AS INT) AS promotional
    FROM dbo.masking_routes r
    JOIN dbo.masking m ON m.masking = r.mask
    WHERE m.id = @masking_id;
END;
GO