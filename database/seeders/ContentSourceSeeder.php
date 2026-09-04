<?php

namespace Database\Seeders;

use App\Models\ContentSource;
use Illuminate\Database\Seeder;

class ContentSourceSeeder extends Seeder
{
    public function run(): void
    {
        $officialUsage = 'Use verified facts and create original bilingual questions. Do not reproduce protected text verbatim.';

        $sources = [
            [
                'name' => 'MCI Internal Verified Question Bank',
                'slug' => 'mci-internal-verified',
                'source_type' => 'internal',
                'base_url' => null,
                'feed_url' => null,
                'trust_score' => 100,
                'allow_current_affairs' => false,
                'allow_question_generation' => true,
                'auto_publish_allowed' => false,
                'license_note' => 'Original MCI-owned or separately licensed content.',
                'usage_notes' => 'Primary source for reviewed and approved MCI questions.',
                'is_active' => true,
            ],
            [
                'name' => 'Press Information Bureau',
                'slug' => 'press-information-bureau',
                'source_type' => 'government',
                'base_url' => 'https://pib.gov.in',
                'feed_url' => 'https://pib.gov.in/RssMain.aspx?ModId=6&Lang=1&Regid=1',
                'trust_score' => 100,
                'allow_current_affairs' => true,
                'allow_question_generation' => true,
                'auto_publish_allowed' => true,
                'license_note' => 'Official Government of India public information; facts only, with original paraphrasing.',
                'usage_notes' => $officialUsage,
                'is_active' => true,
            ],
            [
                'name' => 'Reserve Bank of India',
                'slug' => 'reserve-bank-of-india',
                'source_type' => 'government',
                'base_url' => 'https://www.rbi.org.in',
                'feed_url' => 'https://rbi.org.in/pressreleases_rss.xml',
                'trust_score' => 100,
                'allow_current_affairs' => true,
                'allow_question_generation' => true,
                'auto_publish_allowed' => true,
                'license_note' => 'Official RBI public releases and factual notices; original paraphrasing required.',
                'usage_notes' => $officialUsage,
                'is_active' => true,
            ],
            [
                'name' => 'Union Public Service Commission',
                'slug' => 'upsc',
                'source_type' => 'official',
                'base_url' => 'https://upsc.gov.in',
                'feed_url' => null,
                'trust_score' => 100,
                'allow_current_affairs' => false,
                'allow_question_generation' => true,
                'auto_publish_allowed' => false,
                'license_note' => 'Official examination notifications, syllabus and answer-key facts.',
                'usage_notes' => $officialUsage,
                'is_active' => true,
            ],
            [
                'name' => 'Staff Selection Commission',
                'slug' => 'ssc',
                'source_type' => 'official',
                'base_url' => 'https://ssc.gov.in',
                'feed_url' => null,
                'trust_score' => 100,
                'allow_current_affairs' => false,
                'allow_question_generation' => true,
                'auto_publish_allowed' => false,
                'license_note' => 'Official examination notifications, syllabus and answer-key facts.',
                'usage_notes' => $officialUsage,
                'is_active' => true,
            ],
            [
                'name' => 'National Testing Agency',
                'slug' => 'nta',
                'source_type' => 'official',
                'base_url' => 'https://nta.ac.in',
                'feed_url' => null,
                'trust_score' => 100,
                'allow_current_affairs' => false,
                'allow_question_generation' => true,
                'auto_publish_allowed' => false,
                'license_note' => 'Official examination notifications, syllabus and answer-key facts.',
                'usage_notes' => $officialUsage,
                'is_active' => true,
            ],
            [
                'name' => 'National Council of Educational Research and Training',
                'slug' => 'ncert',
                'source_type' => 'institutional',
                'base_url' => 'https://ncert.nic.in',
                'feed_url' => null,
                'trust_score' => 98,
                'allow_current_affairs' => false,
                'allow_question_generation' => true,
                'auto_publish_allowed' => false,
                'license_note' => 'Official educational reference; use factual concepts with original question wording.',
                'usage_notes' => $officialUsage,
                'is_active' => true,
            ],
            [
                'name' => 'Open Knowledge Candidate',
                'slug' => 'open-knowledge-candidate',
                'source_type' => 'open_knowledge',
                'base_url' => null,
                'feed_url' => null,
                'trust_score' => 0,
                'allow_current_affairs' => false,
                'allow_question_generation' => false,
                'auto_publish_allowed' => false,
                'license_note' => null,
                'usage_notes' => 'Inactive template. Activate only after URL, license and trust review.',
                'is_active' => false,
            ],
        ];

        foreach ($sources as $source) {
            ContentSource::updateOrCreate(
                ['slug' => $source['slug']],
                $source
            );
        }
    }
}
