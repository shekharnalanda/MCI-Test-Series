<?php

namespace App\Services;

use App\Models\CurrentAffairItem;
use App\Models\Question;

class CurrentAffairsMaintenanceService
{
    public function expireOldItems(
        int $days = 365
    ): int {
        return CurrentAffairItem::whereIn(
            'status',
            ['pending', 'approved']
        )
            ->whereNotNull('published_at')
            ->where(
                'published_at',
                '<',
                now()->subDays($days)
            )
            ->update([
                'status' => 'expired',
            ]);
    }

    public function refreshQuestionFreshness(): int
    {
        $count = 0;

        Question::where(
            'is_current_affairs',
            true
        )
            ->whereNotNull(
                'current_affair_date'
            )
            ->chunkById(
                500,
                function ($questions) use (&$count) {
                    foreach ($questions as $question) {

                        $days = now()
                            ->startOfDay()
                            ->diffInDays(
                                $question
                                    ->current_affair_date
                                    ->copy()
                                    ->startOfDay(),
                                true
                            );

                        $score = match (true) {
                            $days <= 7 => 100,
                            $days <= 30 => 90,
                            $days <= 90 => 70,
                            $days <= 180 => 50,
                            $days <= 365 => 30,
                            default => 10,
                        };

                        $question->update([
                            'freshness_score' => $score,
                        ]);

                        $count++;
                    }
                }
            );

        return $count;
    }
}
