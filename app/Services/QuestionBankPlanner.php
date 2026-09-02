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
                            $existing = Question::query()
                                ->where(
                                    'subject_id',
                                    $subject->id
                                )
                                ->where(
                                    'is_active',
                                    true
                                )
                                ->whereHas(
                                    'exams',
                                    fn ($q) =>
                                        $q->where(
                                            'exams.id',
                                            $exam->id
                                        )
                                )
                                ->count();

                            $needed = max(
                                0,
                                $targetPerExamSubject
                                    - $existing
                            );

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

                                            'multi_exam_reuse' =>
                                                true,

                                            'duplicate_control' =>
                                                true,

                                            'verified_preferred' =>
                                                true,
                                        ],
                                    ]
                                );

                            if (
                                !in_array(
                                    $job->status,
                                    [
                                        'processing',
                                        'completed'
                                    ],
                                    true
                                )
                            ) {
                                $job->update([
                                    'status' => 'pending'
                                ]);
                            }

                            $created++;
                        }
                    }
                }
            );

        return $created;
    }
}
