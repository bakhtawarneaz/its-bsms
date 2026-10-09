<?php

namespace App\Logging;

use DateTimeZone;
use Monolog\Formatter\LineFormatter;
use Monolog\Level;
use Monolog\Logger;
use Monolog\LogRecord;

/**
 * Factory for "custom" log channels that use the year/month/date folder layout.
 */
class CreateDailyDirectoryLogger
{
    public function __invoke(array $config): Logger
    {
        $handler = new DailyDirectoryHandler(
            storage_path('logs/' . $config['folder']),
            Level::fromName($config['level'] ?? 'debug'),
        );

        // Format: [2026-10-09 10:42:39 AM UTC | 2026-10-09 3:42:39 PM PKT] INFO: message {context} {extra}
        $handler->setFormatter(new LineFormatter(
            "[%extra.timestamp%] %level_name%: %message% %context% %extra%\n",
            null,
            true,
            true,
        ));

        $logger = new Logger($config['folder'], [$handler]);
        $logger->pushProcessor($this->dualTimezoneTimestamp(...));

        return $logger;
    }

    /**
     * Add a timestamp showing both UTC and Pakistan time to every record.
     */
    private function dualTimezoneTimestamp(LogRecord $record): LogRecord
    {
        $utc = $record->datetime->setTimezone(new DateTimeZone('UTC'));
        $pkt = $record->datetime->setTimezone(new DateTimeZone('Asia/Karachi'));

        $record->extra['timestamp'] = $utc->format('Y-m-d g:i:s A') . ' UTC | '
            . $pkt->format('Y-m-d g:i:s A') . ' PKT';

        return $record;
    }
}