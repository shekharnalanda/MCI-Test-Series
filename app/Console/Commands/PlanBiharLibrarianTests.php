<?php

namespace App\Console\Commands;

use App\Models\Exam;
use Database\Seeders\BiharLibrarianPrioritySeeder;
use Illuminate\Console\Command;

class PlanBiharLibrarianTests extends Command
{
    protected $signature = 'mci:bihar-librarian-plan';
    protected $description = 'Create the priority Bihar Librarian exam, syllabus taxonomy and 75-test schedule';

    public function handle(): int
    {
        $this->call('db:seed', ['--class' => BiharLibrarianPrioritySeeder::class, '--force' => true]);
        $exam = Exam::where('slug', BiharLibrarianPrioritySeeder::EXAM_SLUG)->firstOrFail();
        $this->info(sprintf('Bihar Librarian priority plan ready: tests=%d question_target=%d subjects=%d',
            BiharLibrarianPrioritySeeder::PLANNED_TESTS,
            BiharLibrarianPrioritySeeder::QUESTION_TARGET,
            $exam->subjects()->count()
        ));
        $this->warn('Recruitment dates, vacancies and final paper pattern will remain provisional until an official notification is published.');

        return self::SUCCESS;
    }
}
