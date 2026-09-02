<?php

namespace App\Console\Commands;

use App\Models\Exam;
use App\Models\Question;
use App\Models\QuestionGenerationJob;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class QuestionBankStats extends Command
{
    protected $signature =
        'mci:question-bank-stats';

    protected $description =
        'Display central question bank coverage and automation statistics';

    public function handle(): int
    {
        $this->info(
            'MCI CENTRAL QUESTION BANK'
        );

        $this->table(
            ['Metric','Count'],
            [
                [
                    'Exams',
                    Exam::where(
                        'is_active',
                        true
                    )->count()
                ],
                [
                    'Subjects',
                    Subject::where(
                        'is_active',
                        true
                    )->count()
                ],
                [
                    'Topics',
                    Topic::where(
                        'is_active',
                        true
                    )->count()
                ],
                [
                    'Total Questions',
                    Question::count()
                ],
                [
                    'Published',
                    Question::where(
                        'is_published',
                        true
                    )->count()
                ],
                [
                    'Verified',
                    Question::where(
                        'verification_status',
                        'verified'
                    )->count()
                ],
                [
                    'Current Affairs',
                    Question::where(
                        'is_current_affairs',
                        true
                    )->count()
                ],
                [
                    'Pending Generation Jobs',
                    QuestionGenerationJob::where(
                        'status',
                        'pending'
                    )->count()
                ],
            ]
        );

        $mappingCount = DB::table(
            'exam_question'
        )->count();

        $uniqueMappedQuestions = DB::table(
            'exam_question'
        )
            ->distinct()
            ->count('question_id');

        $this->line(
            "Exam-question mappings: {$mappingCount}"
        );

        $this->line(
            "Unique mapped questions: {$uniqueMappedQuestions}"
        );

        return self::SUCCESS;
    }
}
