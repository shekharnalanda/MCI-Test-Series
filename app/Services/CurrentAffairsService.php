<?php

namespace App\Services;

use App\Models\ContentSource;
use App\Models\CurrentAffairItem;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CurrentAffairsService
{
    public function ingest(
        ContentSource $source,
        array $items
    ): array {
        $accepted = 0;
        $duplicates = 0;
        $rejected = 0;

        if (
            !$source->is_active ||
            !$source->allow_current_affairs
        ) {
            return compact(
                'accepted',
                'duplicates',
                'rejected'
            );
        }

        foreach ($items as $item) {

            $title = trim(
                (string) ($item['title'] ?? '')
            );

            if ($title === '') {
                $rejected++;
                continue;
            }

            $summary = trim(
                (string) ($item['summary'] ?? '')
            );

            $publishedAt = !empty($item['published_at'])
                ? Carbon::parse($item['published_at'])
                : null;

            $hash = hash(
                'sha256',
                mb_strtolower(
                    preg_replace(
                        '/\s+/u',
                        ' ',
                        $title
                    )
                )
            );

            if (
                CurrentAffairItem::where(
                    'content_hash',
                    $hash
                )->exists()
            ) {
                $duplicates++;
                continue;
            }

            $freshness = $this->freshnessScore(
                $publishedAt
            );

            $quality = $this->qualityScore(
                $title,
                $summary,
                $item
            );

            $trust = (int) $source->trust_score;

            /*
             * Auto approval is intentionally strict.
             * Source must explicitly allow auto-publish,
             * trust must be very high and content must
             * meet freshness/quality thresholds.
             */
            $autoApprove =
                $source->auto_publish_allowed &&
                $trust >= 90 &&
                $quality >= 80 &&
                $freshness >= 70;

            CurrentAffairItem::create([
                'content_source_id' => $source->id,

                'external_reference' =>
                    $item['external_reference'] ?? null,

                'title' => $title,
                'summary' => $summary ?: null,

                'source_url' =>
                    $item['source_url'] ?? null,

                'published_at' => $publishedAt,
                'fetched_at' => now(),

                'content_hash' => $hash,

                'trust_score' => $trust,
                'freshness_score' => $freshness,
                'quality_score' => $quality,

                'status' =>
                    $autoApprove
                        ? 'approved'
                        : 'pending',

                'auto_approved' => $autoApprove,

                'metadata' =>
                    $item['metadata'] ?? null,
            ]);

            $accepted++;
        }

        $source->update([
            'last_checked_at' => now(),
            'last_success_at' => now(),
        ]);

        return compact(
            'accepted',
            'duplicates',
            'rejected'
        );
    }

    public function freshnessScore(
        ?Carbon $publishedAt
    ): int {
        if (!$publishedAt) {
            return 20;
        }

        $days = $publishedAt
            ->copy()
            ->startOfDay()
            ->diffInDays(
                now()->startOfDay(),
                true
            );

        return match (true) {
            $days <= 1 => 100,
            $days <= 7 => 95,
            $days <= 30 => 85,
            $days <= 90 => 65,
            $days <= 180 => 40,
            default => 15,
        };
    }

    private function qualityScore(
        string $title,
        string $summary,
        array $item
    ): int {
        $score = 40;

        if (mb_strlen($title) >= 20) {
            $score += 15;
        }

        if (mb_strlen($summary) >= 50) {
            $score += 20;
        }

        if (!empty($item['source_url'])) {
            $score += 10;
        }

        if (!empty($item['published_at'])) {
            $score += 15;
        }

        return min(100, $score);
    }
}
