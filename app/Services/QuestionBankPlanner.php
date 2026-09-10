<?php

namespace App\Services;

use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionGenerationJob;

class QuestionBankPlanner
{
    public function buildJobs(
        int $targetPerExamSubject = 500
    ): int {
        if (
            $targetPerExamSubject < 1 ||
            $targetPerExamSubject > 100000
        ) {
            throw new \InvalidArgumentException(
                'Target must be between 1 and 100000.'
            );
        }

        $created = 0;

        Exam::where('is_active', true)
            ->with('subjects')
            ->chunkById(
                25,
                function ($exams) use (
                    $targetPerExamSubject,
                    &$created
                ) {
                    foreach ($exams as $exam) {

                        foreach (
                            $exam->subjects
                            as $subject
                        ) {
                            /*
                             * A reusable question receives credit
                             * for every exam to which it is mapped.
                             *
                             * We therefore do NOT create separate
                             * copies of the same concept merely
                             * because multiple examinations use it.
                             */
                            $baseQuery = Question::query()
                                ->where(
                                    'subject_id',
                                    $subject->id
                                )
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->where('is_published', true)
                                ->where(
                                    'verification_status',
                                    'verified'
                                )
                                ->whereHas(
                                    'exams',
                                    fn ($q) =>
                                        $q->where(
                                            'exams.id',
                                            $exam->id
                                        )
                                );

                            $existingByDifficulty = (clone $baseQuery)
                                ->selectRaw(
                                    'difficulty, COUNT(*) AS aggregate'
                                )
                                ->groupBy('difficulty')
                                ->pluck('aggregate', 'difficulty');

                            $difficultyTargets = $this
                                ->difficultyTargets($targetPerExamSubject);
                            $difficultyDeficits = [];

                            foreach ($difficultyTargets as $band => $target) {
                                $difficultyDeficits[$band] = max(
                                    0,
                                    $target - (int) (
                                        $existingByDifficulty[$band] ?? 0
                                    )
                                );
                            }

                            $existing = (int) $existingByDifficulty->sum();

                            $needed = array_sum($difficultyDeficits);

                            $key =
                                $exam->id.'|'.
                                $subject->id.'|'.
                                $targetPerExamSubject;

                            $jobCode =
                                'QG-'.
                                strtoupper(
                                    substr(
                                        hash(
                                            'sha256',
                                            $key
                                        ),
                                        0,
                                        16
                                    )
                                );

                            if ($needed === 0) {
                                QuestionGenerationJob::where(
                                    'job_code',
                                    $jobCode
                                )
                                    ->whereIn(
                                        'status',
                                        [
                                            'pending',
                                            'partial'
                                        ]
                                    )
                                    ->update([
                                        'status' =>
                                            'completed',

                                        'completed_at' =>
                                            now(),
                                    ]);

                                continue;
                            }

                            $job =
                                QuestionGenerationJob::updateOrCreate(
                                    [
                                        'job_code' =>
                                            $jobCode
                                    ],
                                    [
                                        'exam_id' =>
                                            $exam->id,

                                        'subject_id' =>
                                            $subject->id,

                                        'target_count' =>
                                            $needed,

                                        'difficulty' =>
                                            'mixed',

                                        'language' =>
                                            'bilingual',

                                        'priority' =>
                                            $exam->is_featured
                                                ? 90
                                                : 50,

                                        'generation_rules' => [
                                            'target_per_exam_subject' =>
                                                $targetPerExamSubject,

                                            'existing_questions' =>
                                                $existing,

                                            'required_questions' =>
                                                $needed,

                                            'difficulty_targets' =>
                                                $difficultyTargets,

                                            'existing_by_difficulty' => [
                                                'easy' => (int) (
                                                    $existingByDifficulty['easy'] ?? 0
                                                ),
                                                'medium' => (int) (
                                                    $existingByDifficulty['medium'] ?? 0
                                                ),
                                                'hard' => (int) (
                                                    $existingByDifficulty['hard'] ?? 0
                                                ),
                                            ],

                                            'difficulty_deficits' =>
                                                $difficultyDeficits,

                                            'difficulty_priority' =>
                                                $this->difficultyPriority(
                                                    $difficultyDeficits
                                                ),

                                            'multi_exam_reuse' =>
                                                true,

                                            'duplicate_control' =>
                                                true,

                                            'verified_preferred' =>
                                                true,

                                            'verified_required' =>
                                                true,

                                            'published_required' =>
                                                true,
                                        ],
                                    ]
                                );

                            if (
                                $job->status !== 'processing'
                            ) {
                                $retryState = [
                                    'status' => 'pending'
                                ];

                                if (
                                    in_array(
                                        $job->status,
                                        ['partial', 'failed', 'completed'],
                                        true
                                    )
                                ) {
                                    $retryState += [
                                        'generated_count' => 0,
                                        'accepted_count' => 0,
                                        'duplicate_count' => 0,
                                        'rejected_count' => 0,
                                        'started_at' => null,
                                        'completed_at' => null,
                                        'error_message' => null
                                    ];
                                }

                                $job->update($retryState);
                            }

                            $created++;
                        }
                    }
                }
            );

        return $created;
    }

    private function difficultyTargets(int $total): array
    {
        $easy = (int) floor($total * 0.30);
        $hard = (int) floor($total * 0.20);

        return [
            'easy' => $easy,
            'medium' => $total - $easy - $hard,
            'hard' => $hard,
        ];
    }

    private function difficultyPriority(array $deficits): array
    {
        arsort($deficits);

        return array_keys(
            array_filter(
                $deficits,
                fn (int $count): bool => $count > 0
            )
        );
    }
}
