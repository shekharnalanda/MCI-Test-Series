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
        $eligibleItems = CurrentAffairItem::query()
            ->with('source')
            ->where('status', 'approved')
            ->where('question_generated', false)
            ->whereHas('source', fn ($query) => $query->where('slug', 'reserve-bank-of-india'))
            ->orderBy('published_at')
            ->orderBy('id')
            ->get();

        $amounts = CurrentAffairItem::query()
            ->with('source')
            ->whereIn('status', ['approved', 'processed'])
            ->whereHas('source', fn ($query) => $query->where('slug', 'reserve-bank-of-india'))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CurrentAffairItem $item) => $this->penaltyFact($item))
            ->filter()
            ->pluck('amount')
            ->unique()
            ->values();
        $generated = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($eligibleItems->take(max(1, min($limit, 100))) as $item) {
            $payload = $this->questionPayload($item, $amounts);

            if ($payload === null) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $generated++;
                continue;
            }

            try {
                $this->questions->createQuestion($item, $payload);
                $generated++;
            } catch (\Throwable) {
                $failed++;
            }
        }

        return compact('generated', 'skipped', 'failed');
    }

    private function questionPayload(CurrentAffairItem $item, $amounts): ?array
    {
        if ($fact = $this->penaltyFact($item)) {
            $distractors = $amounts->reject(fn ($amount) => $amount === $fact['amount'])->take(3);

            if ($distractors->count() !== 3) {
                return null;
            }

            return $this->payload(
                $item,
                "According to the RBI order dated {$fact['date']}, what monetary penalty was imposed on {$fact['entity']}?",
                "RBI के {$fact['date_hi']} के आदेश के अनुसार {$fact['entity']} पर कितनी मौद्रिक पेनल्टी लगाई गई?",
                "RBI imposed a monetary penalty of {$fact['amount']} on {$fact['entity']} by its order dated {$fact['date']}.",
                "RBI ने {$fact['date_hi']} के आदेश द्वारा {$fact['entity']} पर {$fact['amount']} की मौद्रिक पेनल्टी लगाई।",
                $distractors->push($fact['amount'])->map(fn ($amount) => [$amount, $amount, $amount === $fact['amount']])->all(),
            );
        }

        return $this->knownReleasePayload($item);
    }

    private function knownReleasePayload(CurrentAffairItem $item): ?array
    {
        $title = trim($item->title);

        if (preg_match('/^Inclusion of [“"](.+?)[”"] in the (First|Second|Third|Fourth) Schedule to the Reserve Bank of India Act, 1934$/u', $title, $matches)) {
            $schedule = $matches[2];
            $labels = ['First' => 'प्रथम', 'Second' => 'द्वितीय', 'Third' => 'तृतीय', 'Fourth' => 'चतुर्थ'];

            return $this->payload(
                $item,
                "In which Schedule to the Reserve Bank of India Act, 1934 was {$matches[1]} included?",
                "{$matches[1]} को भारतीय रिज़र्व बैंक अधिनियम, 1934 की किस अनुसूची में शामिल किया गया?",
                "RBI announced the inclusion of {$matches[1]} in the {$schedule} Schedule to the Reserve Bank of India Act, 1934.",
                "RBI ने {$matches[1]} को भारतीय रिज़र्व बैंक अधिनियम, 1934 की {$labels[$schedule]} अनुसूची में शामिल करने की घोषणा की।",
                collect($labels)->map(fn ($hi, $en) => ["{$en} Schedule", "{$hi} अनुसूची", $en === $schedule])->values()->all(),
            );
        }

        if (preg_match('/^Result of the (?:Second )?(\d+)-day Variable Rate Reverse Repo \(VRRR\) auction held on (.+)$/i', $title, $matches)) {
            $tenor = (int) $matches[1];

            if (! in_array($tenor, [3, 7, 14, 30], true)) {
                return null;
            }

            return $this->payload(
                $item,
                "What was the tenor of the RBI Variable Rate Reverse Repo (VRRR) auction held on {$matches[2]}?",
                "{$matches[2]} को आयोजित RBI Variable Rate Reverse Repo (VRRR) नीलामी की अवधि कितनी थी?",
                "The RBI result refers to a {$tenor}-day Variable Rate Reverse Repo (VRRR) auction held on {$matches[2]}.",
                "RBI का परिणाम {$matches[2]} को आयोजित {$tenor}-दिवसीय Variable Rate Reverse Repo (VRRR) नीलामी से संबंधित है।",
                collect([3, 7, 14, 30])->map(fn ($days) => ["{$days} days", "{$days} दिन", $days === $tenor])->all(),
            );
        }

        if (preg_match('/^RBI to conduct (?:Second )?(\d+)-day Variable Rate Reverse Repo \(VRRR\) auction under LAF(?: on (.+))?$/i', $title, $matches)) {
            $tenor = (int) $matches[1];

            if (! in_array($tenor, [3, 7, 14, 30], true)) {
                return null;
            }

            $date = isset($matches[2]) && trim($matches[2]) !== '' ? trim($matches[2]) : null;
            $context = $date ? " scheduled for {$date}" : ' announced under LAF';
            $contextHi = $date ? "{$date} के लिए निर्धारित" : 'LAF के अंतर्गत घोषित';

            return $this->payload(
                $item,
                "What tenor did RBI announce for the VRRR auction{$context}?",
                "RBI ने {$contextHi} VRRR नीलामी के लिए कितनी अवधि घोषित की?",
                "RBI announced a {$tenor}-day Variable Rate Reverse Repo (VRRR) auction{$context}.",
                "RBI ने {$contextHi} {$tenor}-दिवसीय Variable Rate Reverse Repo (VRRR) नीलामी घोषित की।",
                collect([3, 7, 14, 30])->map(fn ($days) => ["{$days} days", "{$days} दिन", $days === $tenor])->all(),
            );
        }

        if (preg_match('/^Premature redemption under Sovereign Gold Bond \(SGB\) Scheme - Redemption Price for premature redemption of (SGB .+?) due on (.+)$/i', $title, $matches)) {
            $series = $matches[1];
            $options = ['SGB 2021-22 Series IV', 'SGB 2021-22 Series V', 'SGB 2021-22 Series VI', 'SGB 2021-22 Series VII'];

            if (! in_array($series, $options, true)) {
                return null;
            }

            return $this->payload(
                $item,
                "Which Sovereign Gold Bond series was due for premature redemption on {$matches[2]}?",
                "{$matches[2]} को समयपूर्व मोचन के लिए कौन-सी Sovereign Gold Bond श्रृंखला देय थी?",
                "The RBI release specifies {$series} for premature redemption on {$matches[2]}.",
                "RBI की विज्ञप्ति में {$matches[2]} को समयपूर्व मोचन के लिए {$series} निर्दिष्ट है।",
                collect($options)->map(fn ($option) => [$option, $option, $option === $series])->all(),
            );
        }

        if ($title === 'Auction of 91-Day, 182-Day and 364-Day Treasury Bills') {
            $correct = '91-day, 182-day and 364-day';

            return $this->payload(
                $item,
                'Which Treasury Bill maturities were included in the RBI auction announcement?',
                'RBI की नीलामी घोषणा में Treasury Bills की कौन-सी परिपक्वता अवधियाँ शामिल थीं?',
                'The announcement covered 91-day, 182-day and 364-day Treasury Bills.',
                'घोषणा में 91-दिवसीय, 182-दिवसीय और 364-दिवसीय Treasury Bills शामिल थे।',
                [
                    [$correct, '91-दिवसीय, 182-दिवसीय और 364-दिवसीय', true],
                    ['30-day, 60-day and 90-day', '30-दिवसीय, 60-दिवसीय और 90-दिवसीय', false],
                    ['100-day, 200-day and 300-day', '100-दिवसीय, 200-दिवसीय और 300-दिवसीय', false],
                    ['180-day, 270-day and 365-day', '180-दिवसीय, 270-दिवसीय और 365-दिवसीय', false],
                ],
            );
        }

        return null;
    }

    private function payload(CurrentAffairItem $item, string $question, string $questionHi, string $explanation, string $explanationHi, array $options): array
    {
        return [
            'question_text' => $question,
            'question_text_hi' => $questionHi,
            'explanation' => $explanation,
            'explanation_hi' => $explanationHi,
            'difficulty' => 'medium',
            'language' => 'bilingual',
            'generation_method' => 'automated',
            'options' => collect($options)
                ->sortBy(fn ($option) => hash('sha256', $item->id.'|'.$option[0]))
                ->values()
                ->map(fn ($option) => [
                    'option_text' => $option[0],
                    'option_text_hi' => $option[1],
                    'is_correct' => $option[2],
                ])
                ->all(),
        ];
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
