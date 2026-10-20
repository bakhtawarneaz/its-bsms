<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Throwable;

class DeployProcedures extends Command
{
    protected $signature = 'db:procedures {--filter= : Only deploy files whose path contains this text}';

    protected $description = 'Create or update all stored procedures from database/procedures';

    public function handle(): int
    {
        $files = collect(File::allFiles(database_path('procedures')))
            ->filter(fn ($file) => $file->getExtension() === 'sql')
            ->filter(fn ($file) => ! $this->option('filter') || str_contains($file->getRelativePathname(), $this->option('filter')))
            ->sortBy(fn ($file) => $file->getRelativePathname());

        if ($files->isEmpty()) {
            $this->warn('No procedure files found.');

            return self::SUCCESS;
        }

        $failed = 0;

        foreach ($files as $file) {
            $name = $file->getRelativePathname();

            try {
                // Support optional "GO" batch separators in the files
                $sql = $this->sqlServer2014Compatible(File::get($file->getPathname()));
                $batches = preg_split('/^\s*GO\s*;?\s*$/mi', $sql);

                foreach (array_filter(array_map('trim', $batches)) as $batch) {
                    DB::unprepared($batch);
                }

                $this->components->task($name);
            } catch (Throwable $e) {
                $failed++;
                $this->components->task($name, fn () => false);
                $this->components->error($e->getMessage());
            }
        }

        return $failed ? self::FAILURE : self::SUCCESS;
    }

        /**
     * Production runs SQL Server 2014, which has no CREATE OR ALTER.
     * Create a stub when the procedure is missing, then ALTER it (keeps permissions).
     */
    private function sqlServer2014Compatible(string $sql): string
    {
        return preg_replace_callback(
            '/CREATE\s+OR\s+ALTER\s+PROCEDURE\s+([\w\.\[\]]+)/i',
            fn ($match) => "IF OBJECT_ID(N'{$match[1]}', N'P') IS NULL EXEC(N'CREATE PROCEDURE {$match[1]} AS RETURN 0');\nGO\nALTER PROCEDURE {$match[1]}",
            $sql,
        );
    }
}