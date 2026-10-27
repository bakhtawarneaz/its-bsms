-- Delivery logs of a campaign (legacy Logs_Download_Campaign_DAL / CheckCamp_Exist_Table):
-- reads the live view when it holds the campaign, otherwise the archive view.
-- Queued rows (status 1) are left out.
CREATE OR ALTER PROCEDURE dbo.usp_campaigns_logs
    @id INT
AS
BEGIN
    SET NOCOUNT ON;

    IF EXISTS (SELECT 1 FROM dbo.outbox_Camp_view WITH (NOLOCK) WHERE camp_id = @id AND status <> 1)
        SELECT sender, receiver, msgdata, CONVERT(VARCHAR(19), senttime, 20) AS senttime,
               CONVERT(VARCHAR(19), deliveredtonetwork, 20) AS deliveredtonetwork, outboxstatus
        FROM dbo.outbox_Camp_view WITH (NOLOCK)
        WHERE camp_id = @id AND status <> 1;
    ELSE
        SELECT sender, receiver, msgdata, CONVERT(VARCHAR(19), senttime, 20) AS senttime,
               CONVERT(VARCHAR(19), deliveredtonetwork, 20) AS deliveredtonetwork, outboxstatus
        FROM dbo.outbox_Camp_archive_view WITH (NOLOCK)
        WHERE camp_id = @id AND status <> 1;
END;
GO