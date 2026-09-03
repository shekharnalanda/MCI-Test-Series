<?php

namespace Tests\Feature;

use App\Models\Question;
use App\Services\CurrentAffairsTestGenerator;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CurrentAffairsQuestionRotationTest extends TestCase
{
    use RefreshDatabase;

    public function test_current_affairs_questions_rotate_without_repeat(): void
    {
        $this->seed(DatabaseSeeder::class);

        $seriesIds = DB::table('test_series')
            ->where('slug', 'like', 'current-affairs-%')
            ->pluck('id');

        $testIds = DB::table('tests')
            ->whereIn('test_series_id', $seriesIds)
            ->pluck('id');

        DB::table('question_test')
            ->whereIn('test_id', $testIds)
            ->delete();

        DB::table('tests')
            ->whereIn('id', $testIds)
            ->delete();

        Question::query()->update(['usage_count' => 0]);

        for ($index = 1; $index <= 4; $index++) {
            Question::query()->create([
                'question_text' =>
                    "Current affairs rotation question {$index}",
                'question_text_hi' =>
                    "0 +G/0M8 0KG6( *M06M( {$index}",
                'is_current_affairs' => true,
                'current_affair_date' => now()->toDateString(),
                'verification_status' => 'verified',
                'verified_at' => now(),
                'freshness_score' => 100,
                'usage_count' => 0,
                'is_published' => true,
                'is_active' => true,
                'content_hash' => hash(
                    'sha256',
                    "current-affairs-rotation-{$index}"
                ),
            ]);
        }

        $eligible = Question::query()
            ->where('is_current_affairs', true)
            ->where('is_active', true)
            ->where('is_published', true)
            ->where('verification_status', 'verified')
            ->where(
                'current_affair_date',
                '>=',
                now()->subDays(7)->toDateString()
            )
            ->count();

        $this->assertGreaterThanOrEqual(4, $eligible);

        $generator = app(CurrentAffairsTestGenerator::class);
        $first = $generator->generate('weekly', 2);
        $second = $generator->generate('weekly', 2);

        $firstIds = $first->questions->modelKeys();
        $secondIds = $second->questions->modelKeys();
        $selectedIds = array_merge($firstIds, $secondIds);

        $this->assertCount(2, $firstIds);
        $this->assertCount(2, $secondIds);
        $this->assertSame(
            [],
            array_values(array_intersect($firstIds, $secondIds))
        );
        $this->assertSame(
            4,
            Question::query()
                ->whereIn('id', $selectedIds)
                ->where('usage_count', 1)
                ->count()
        );
    }
}
