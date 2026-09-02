<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\Test;
use App\Models\User;
use App\Services\ExamEngineService;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class ExamEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_exam_engine_can_start_save_and_evaluate_attempt(): void
    {
        $this->seed(DatabaseSeeder::class);

        $user = User::create([
            'name' => 'Demo Student',
            'email' => 'student@example.test',
            'password' => Hash::make('password'),
            'role' => 'student',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);

        $student = StudentProfile::create([
            'user_id' => $user->id,
            'student_code' => 'MCITEST001',
            'phone' => '9999999999',
            'photo_path' => 'test.jpg',
            'status' => 'active',
            'email_verified_at' => now(),
            'admission_approved_at' => now(),
        ]);

        $test = Test::where('is_demo', true)->firstOrFail();

        $engine = app(ExamEngineService::class);

        $attempt = $engine->start($test, $student);

        $this->assertEquals('started', $attempt->status);
        $this->assertEquals(5, $attempt->attemptQuestions()->count());

        $firstSnapshot = $attempt
            ->attemptQuestions()
            ->with('question.options')
            ->orderBy('question_order')
            ->firstOrFail();

        $correct = $firstSnapshot
            ->question
            ->options
            ->firstWhere('is_correct', true);

        $engine->saveAnswer(
            $attempt,
            $firstSnapshot->question_id,
            $correct->id,
            false
        );

        $secondSnapshot = $attempt
            ->attemptQuestions()
            ->with('question.options')
            ->orderBy('question_order')
            ->skip(1)
            ->firstOrFail();

        $wrong = $secondSnapshot
            ->question
            ->options
            ->firstWhere('is_correct', false);

        $engine->saveAnswer(
            $attempt,
            $secondSnapshot->question_id,
            $wrong->id,
            true
        );

        $result = $engine->submit($attempt);

        $this->assertEquals('evaluated', $result->status);
        $this->assertEquals(2, $result->attempted_questions);
        $this->assertEquals(1, $result->correct_answers);
        $this->assertEquals(1, $result->wrong_answers);
        $this->assertEquals(3, $result->unanswered);

        // 1 correct = +1, 1 wrong = -0.25
        $this->assertEquals(0.75, (float) $result->obtained_marks);
        $this->assertEquals(15.0, (float) $result->percentage);
        $this->assertEquals(
            50.0,
            (float) data_get($result->analytics, 'accuracy')
        );
    }
}
