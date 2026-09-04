<?php

namespace App\Services;

use App\Models\CurrentAffairItem;
use Carbon\Carbon;

class VerifiedCurrentAffairsQuestionGenerator
{
    public function __construct(
        private readonly CurrentAffairsQuestionService $questions,
    ) {}

    public function generate(int $limit = 20, bool $dryRun = false): array
    {
        $facts = CurrentAffairItem::query()
            ->with('source')
            ->where('status', 'approved')
            ->where('question_generated', false)
            ->whereHas('source', fn ($query) => $query->where('slug', 'reserve-bank-of-india'))
            ->orderBy('published_at')
            ->orderBy('id')
            ->get()
            ->map(fn (CurrentAffairItem $item) => $this->penaltyFact($item))
            ->filter()
            ->values();

        $amounts = $facts->pluck('amount')->unique()->values();
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($facts->take(max(1, min($limit, 100))) as $fact) {
            $distractors = $amounts->reject(fn ($amount) => $amount === $fact['amount'])->take(3);

            if ($distractors->count() !== 3) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $generated++;
                continue;
            }

            $options = $distractors->push($fact['amount'])
                ->sortBy(fn ($amount) => hash('sha256', $fact['item']->id.'|'.$amount))
                ->values()
                ->map(fn ($amount) => [
                    'option_text' => $amount,
                    'option_text_hi' => $amount,
                    'is_correct' => $amount === $fact['amount'],
                ])
                ->all();

            try {
                $this->questions->createQuestion($fact['item'], [
                    'question_text' => "According to the RBI order dated {$fact['date']}, what monetary penalty was imposed on {$fact['entity']}?",
                    'question_text_hi' => "RBI के {$fact['date_hi']} के आदेश के अनुसार {$fact['entity']} पर कितनी मौद्रिक पेनल्टी लगाई गई?",
                    'explanation' => "RBI imposed a monetary penalty of {$fact['amount']} on {$fact['entity']} by its order dated {$fact['date']}.",
                    'explanation_hi' => "RBI ने {$fact['date_hi']} के आदेश द्वारा {$fact['entity']} पर {$fact['amount']} की मौद्रिक पेनल्टी लगाई।",
                    'difficulty' => 'medium',
                    'language' => 'bilingual',
                    'generation_method' => 'automated',
                    'options' => $options,
                ]);
                $generated++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return compact('generated', 'skipped', 'failed');
    }

    private function penaltyFact(CurrentAffairItem $item): ?array
    {
        if (! preg_match('/^RBI imposes monetary penalty on (.+)$/iu', trim($item->title), $entity)) {
            return null;
        }

        if (! preg_match('/order dated ([A-Z][a-z]+ \d{1,2}, \d{4}).*?penalty of (₹[\d,.]+(?:\s+(?:lakh|crore))?)/isu', (string) $item->summary, $matches)) {
            return null;
        }

        $date = Carbon::parse($matches[1]);

        return [
            'item' => $item,
            'entity' => trim($entity[1]),
            'amount' => $matches[2],
            'date' => $date->format('F j, Y'),
            'date_hi' => $date->format('d-m-Y'),
        ];
    }
}
