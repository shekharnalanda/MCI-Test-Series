<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Models\QuestionGenerationJob;
use App\Models\Subject;
use Database\Seeders\BiharLibrarianPrioritySeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class BiharLibrarianPrioritySeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_idempotent_75_test_priority_plan(): void
    {
        $this->seed();
        $this->seed(BiharLibrarianPrioritySeeder::class);

        $exam = Exam::where('slug', BiharLibrarianPrioritySeeder::EXAM_SLUG)->firstOrFail();
        $subject = Subject::where('slug', 'library-and-information-science')->firstOrFail();
        $job = QuestionGenerationJob::where('job_code', 'QG-BIHAR-LIBRARIAN-PRIORITY')->firstOrFail();

        $this->assertSame(75, $exam->pattern['planned_tests']);
        $this->assertSame(75, collect($exam->pattern['schedule'])->sum('count'));
        $this->assertSame('provisional_until_official_notification', $exam->pattern['status']);
        $this->assertSame(15, $subject->topics()->count());
        $this->assertSame(5, $exam->subjects()->count());
        $this->assertSame(1500, $job->target_count);
        $this->assertSame(100, $job->priority);
        $this->assertSame(1, Exam::where('slug', BiharLibrarianPrioritySeeder::EXAM_SLUG)->count());
    }
}
