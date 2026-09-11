<?php

namespace App\Console\Commands;

use App\Models\ContentSource;
use App\Services\QuestionGenerationJobImportService;
use Illuminate\Console\Command;

class ProcessQuestionGenerationJob extends Command
{
    protected $signature = 'question-bank:process-job
        {job_code : Question generation job code}
        {file : JSON question file}
        {--source=mci-internal-verified : Trusted content source slug}
        {--chunk=500 : Questions per ingestion batch}';

    protected $description = 'Validate and import a bilingual question file against a queued generation job';

    public function handle(QuestionGenerationJobImportService $service): int
    {
        $source = ContentSource::query()
            ->where('slug', (string) $this->option('source'))
            ->where('is_active', true)
            ->first();

        if (! $source) {
            $this->error('Active content source not found.');

            return self::FAILURE;
        }

        try {
            $result = $service->importJsonFile(
                (string) $this->argument('job_code'),
                (string) $this->argument('file'),
                $source,
                (int) $this->option('chunk')
            );
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info('Generation Job Import Complete');

        foreach ($result as $key => $value) {
            $this->line(ucfirst(str_replace('_', ' ', $key)).": {$value}");
        }

        return self::SUCCESS;
    }
}
