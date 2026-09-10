<?php

namespace App\Console\Commands;

use App\Services\QuestionGenerationBriefService;
use Illuminate\Console\Command;

class ExportQuestionGenerationBriefs extends Command
{
    protected $signature = 'question-bank:export-briefs
        {--difficulty=hard : easy, medium, hard, or all}
        {--limit=25 : Maximum queued targets to export}';

    protected $description = 'Export prioritized, read-only question-generation briefs as JSON';

    public function handle(QuestionGenerationBriefService $service): int
    {
        $difficulty = strtolower((string) $this->option('difficulty'));
        $limit = (int) $this->option('limit');

        if (! in_array($difficulty, ['easy', 'medium', 'hard', 'all'], true)) {
            $this->error('Difficulty must be easy, medium, hard, or all.');

            return self::FAILURE;
        }

        if ($limit < 1 || $limit > 1000) {
            $this->error('Limit must be between 1 and 1000.');

            return self::FAILURE;
        }

        $briefs = $service->pending($difficulty, $limit);

        $this->line((string) json_encode([
            'schema_version' => 1,
            'generated_at' => now()->toIso8601String(),
            'read_only' => true,
            'difficulty' => $difficulty,
            'brief_count' => $briefs->count(),
            'briefs' => $briefs->all(),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));

        return self::SUCCESS;
    }
}
