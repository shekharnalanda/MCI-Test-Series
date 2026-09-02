<?php

namespace App\Console\Commands;

use App\Models\ContentSource;
use App\Services\BulkQuestionImportService;
use Illuminate\Console\Command;

class ImportQuestionBank extends Command
{
    protected $signature =
        'mci:question-bank-import
        {file : JSON question file}
        {--source=mci-internal-verified : Content source slug}
        {--chunk=500 : Questions per ingestion batch}';

    protected $description =
        'Bulk import questions into the MCI central question bank';

    public function handle(
        BulkQuestionImportService $service
    ): int {
        $source = ContentSource::where(
            'slug',
            (string) $this->option('source')
        )
            ->where('is_active', true)
            ->first();

        if (!$source) {
            $this->error(
                'Active content source not found.'
            );

            return self::FAILURE;
        }

        try {
            $result =
                $service->importJsonFile(
                    (string) $this->argument('file'),
                    $source,
                    (int) $this->option('chunk')
                );

            $this->info(
                'Question Bank Import Complete'
            );

            foreach ($result as $key => $value) {
                $this->line(
                    ucfirst($key).": {$value}"
                );
            }

            return self::SUCCESS;

        } catch (\Throwable $e) {
            $this->error(
                $e->getMessage()
            );

            return self::FAILURE;
        }
    }
}
