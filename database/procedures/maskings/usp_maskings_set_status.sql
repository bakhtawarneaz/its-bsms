-- Turns networks on/off for one masking and sets its type (legacy "Change Mask Status").
-- Jazz also drives Warid (9232). A blocked route (route 0) always stays off.
CREATE OR ALTER PROCEDURE dbo.usp_maskings_set_status
    @masking_id  INT,
    @promotional BIT,
    @jazz        BIT,
    @zong        BIT,
    @ufone       BIT,
    @telenor     BIT,
    @scom        BIT
AS
BEGIN
    SET NOCOUNT ON;

    DECLARE @mask VARCHAR(100), @rows INT;

    SELECT @mask = masking FROM dbo.masking WHERE id = @masking_id;

    IF @mask IS NULL
    BEGIN
        SELECT 'NOT_FOUND' AS status, CAST(NULL AS VARCHAR(100)) AS masking, 0 AS updated;
        RETURN;
    END;

    UPDATE dbo.masking_routes
    SET Promotional = @promotional,
        Status = CASE
                     WHEN route = 0 THEN 0
                     ELSE CASE CAST(network AS VARCHAR(10))
                              WHEN '9230' THEN @jazz
                              WHEN '9232' THEN @jazz
                              WHEN '9231' THEN @zong
                              WHEN '9233' THEN @ufone
                              WHEN '9234' THEN @telenor
                              WHEN '9235' THEN @scom
                              ELSE CAST(Status AS INT)
                          END
                 END
    WHERE mask = @mask;

    SET @rows = @@ROWCOUNT;

    SELECT CASE WHEN @rows = 0 THEN 'NO_ROUTES' ELSE 'OK' END AS status, @mask AS masking, @rows AS updated;
END;
GO