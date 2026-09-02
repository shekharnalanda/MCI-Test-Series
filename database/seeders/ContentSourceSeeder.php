<?php

namespace Database\Seeders;

use App\Models\ContentSource;
use Illuminate\Database\Seeder;

class ContentSourceSeeder extends Seeder
{
    public function run(): void
    {
        $sources = [
            [
                'name' => 'MCI Internal Verified Question Bank',
                'slug' => 'mci-internal-verified',
                'source_type' => 'internal',
                'trust_score' => 100,
                'allow_current_affairs' => true,
                'allow_question_generation' => true,
                'auto_publish_allowed' => true,
                'license_note' => 'MCI internal/original content',
            ],
            [
                'name' => 'Government Official Source',
                'slug' => 'government-official',
                'source_type' => 'government',
                'trust_score' => 100,
                'allow_current_affairs' => true,
                'allow_question_generation' => true,
                'auto_publish_allowed' => false,
                'usage_notes' =>
                    'Registry template. Individual government sources will be registered separately.',
            ],
            [
                'name' => 'Official Examination Authority',
                'slug' => 'official-exam-authority',
                'source_type' => 'official',
                'trust_score' => 100,
                'allow_current_affairs' => false,
                'allow_question_generation' => true,
                'auto_publish_allowed' => false,
                'usage_notes' =>
                    'For official syllabus, notification and examination-pattern references.',
            ],
            [
                'name' => 'Approved Open Knowledge Source',
                'slug' => 'approved-open-knowledge',
                'source_type' => 'open_knowledge',
                'trust_score' => 80,
                'allow_current_affairs' => false,
                'allow_question_generation' => true,
                'auto_publish_allowed' => false,
                'usage_notes' =>
                    'Only approved reusable/open content should be attached to this source type.',
            ],
        ];

        foreach ($sources as $source) {
            ContentSource::updateOrCreate(
                ['slug' => $source['slug']],
                $source + ['is_active' => true]
            );
        }
    }
}
