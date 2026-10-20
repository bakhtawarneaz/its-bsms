-- Replaces all routes of a masking, like the legacy "Edit Masking Routes":
-- old routes are deleted, new ones start inactive (status 0) and masking.status is reset to 0.
-- Also covers maskings that have no routes yet (legacy "Add Existing Mask Routes").
-- The caller owns the transaction.
CREATE OR ALTER PROCEDURE dbo.usp_maskings_routes_save
    @masking_id       INT,
    @promotional      BIT,
    @jazz_route       INT,
    @jazz_operator    VARCHAR(100),
    @zong_route       INT,
    @zong_operator    VARCHAR(100),
    @ufone_route      INT,
    @ufone_operator   VARCHAR(100),
    @telenor_route    INT,
    @telenor_operator VARCHAR(100),
    @scom_route       INT,
    @scom_operator    VARCHAR(100)
AS
BEGIN
    SET NOCOUNT ON;
    SET XACT_ABORT ON;

    DECLARE @mask VARCHAR(100);

    SELECT @mask = masking
    FROM dbo.masking WITH (UPDLOCK, ROWLOCK)
    WHERE id = @masking_id;

    IF @mask IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status, CAST(NULL AS VARCHAR(100)) AS masking;
        RETURN;
    END;

    DELETE FROM dbo.masking_routes WHERE mask = @mask;

    INSERT INTO dbo.masking_routes (mask, network, route, Operator, Promotional, Status)
    SELECT @mask, v.network, v.route, v.operator, @promotional, 0
    FROM (VALUES
        ('9230', @jazz_route,    @jazz_operator),
        ('9232', @jazz_route,    @jazz_operator),
        ('9233', @ufone_route,   @ufone_operator),
        ('9231', @zong_route,    @zong_operator),
        ('9234', @telenor_route, @telenor_operator),
        ('9235', @scom_route,    @scom_operator)
    ) AS v (network, route, operator)
    WHERE v.route IS NOT NULL;

    UPDATE dbo.masking SET status = 0 WHERE id = @masking_id;

    SELECT 'OK' AS status, @mask AS masking;
END;
GO