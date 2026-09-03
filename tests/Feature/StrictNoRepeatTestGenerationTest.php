<?php

namespace Tests\Feature;

use App\Models\Exam;
use App\Services\AutomaticTestGenerator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StrictNoRepeatTestGenerationTest extends TestCase
{
    use RefreshDatabase;

    public function test_questions_do_not_repeat_while_unused_exam_pool_is_sufficient(): void
    {
        $this->seed(DatabaseSeeder::class);

        $exam = Exam::query()
            ->whereHas('questions', function ($query): void {
                $query
                    ->where('is_active', true)
                    ->where('is_published', true)
                    ->where('verification_status', 'verified');
            }, '>=', 4)
            ->firstOrFail();

        $existingTestIds = DB::table('tests')
            ->where('exam_id', $exam->id)
            ->pluck('id');

        DB::table('question_test')->whereIn('test_id', $existingTestIds)->delete();
        DB::table('tests')->whereIn('id', $existingTestIds)->delete();
        DB::table('questions')->update(['usage_count' => 0]);

        $generator = app(AutomaticTestGenerator::class);
        $first = $generator->generate($exam, 2);
        $second = $generator->generate($exam, 2);

        $firstIds = $first->questions->modelKeys();
        $secondIds = $second->questions->modelKeys();
        $selectedIds = array_merge($firstIds, $secondIds);

        $this->assertCount(2, $firstIds);
        $this->assertCount(2, $secondIds);
        $this->assertSame([], array_values(array_intersect($firstIds, $secondIds)));
        $this->assertSame(
            4,
            DB::table('questions')
                ->whereIn('id', $selectedIds)
                ->where('usage_count', 1)
                ->count()
        );
    }
}
