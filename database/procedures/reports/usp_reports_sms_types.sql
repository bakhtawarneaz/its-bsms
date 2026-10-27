-- SMS types for report filters.
CREATE OR ALTER PROCEDURE dbo.usp_reports_sms_types
AS
BEGIN
    SET NOCOUNT ON;

    SELECT smstypecode AS code, smstypes AS name
    FROM dbo.outbox_sms_type WITH (NOLOCK)
    ORDER BY smstypes;
END;
GO