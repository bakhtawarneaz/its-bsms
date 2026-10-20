-- Adds one masking with its network routes, exactly like the legacy "Add Masking and Routes":
-- masking and routes start inactive (status 0); Jazz is written for 9230 and Warid 9232.
-- Returns DUPLICATE when the masking already exists. The caller owns the transaction.
CREATE OR ALTER PROCEDURE dbo.usp_maskings_create
    @mask             VARCHAR(50),
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

    IF EXISTS (SELECT 1 FROM dbo.masking WITH (UPDLOCK, HOLDLOCK) WHERE masking = @mask)
    BEGIN
        SELECT 'DUPLICATE' AS status, CAST(NULL AS INT) AS id;
        RETURN;
    END;

    INSERT INTO dbo.masking (masking, status) VALUES (@mask, 0);

    DECLARE @id INT = CAST(SCOPE_IDENTITY() AS INT);

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

    SELECT 'OK' AS status, @id AS id;
END;
GO