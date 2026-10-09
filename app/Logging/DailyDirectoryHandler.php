<?php

namespace App\Logging;

use DateTimeInterface;
use Monolog\Handler\StreamHandler;
use Monolog\Level;
use Monolog\LogRecord;

/**
 * Writes logs to {base}/{year}/{month-number}-{month-name}/{date}.log
 * and switches to a new file automatically when the date changes.
 */
class DailyDirectoryHandler extends StreamHandler
{
    public function __construct(
        private readonly string $basePath,
        Level $level = Level::Debug,
    ) {
        parent::__construct($this->pathFor(now()), $level);
    }

    protected function write(LogRecord $record): void
    {
        $path = $this->pathFor($record->datetime);

        // Long-running processes (queues, schedulers) must roll over at midnight
        if ($path !== $this->url) {
            $this->close();
            $this->url = $path;
        }

        parent::write($record);
    }

    private function pathFor(DateTimeInterface $date): string
    {
        return sprintf(
            '%s/%s/%s/%s.log',
            $this->basePath,
            $date->format('Y'),
            $date->format('m-F'),
            $date->format('Y-m-d'),
        );
    }
}