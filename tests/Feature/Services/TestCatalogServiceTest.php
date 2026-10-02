<?php

namespace Tests\Feature\Services;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Test;
use App\Models\Topic;
use App\Services\TestCatalogService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TestCatalogServiceTest extends TestCase
{
    use RefreshDatabase;

    private function exam(string $name, string $categoryName): Exam
    {
        $category = ExamCategory::firstOrCreate(['slug' => str($categoryName)->slug()->toString()], ['name' => $categoryName]);

        return Exam::create(['name' => $name, 'slug' => str($name)->slug()->toString(), 'exam_category_id' => $category->id]);
    }

    private function paper(Exam $exam, string $type = 'practice', array $attributes = []): Test
    {
        return Test::create(array_merge(['exam_id' => $exam->id, 'title' => $exam->name.' Set', 'test_type' => $type], $attributes));
    }

    private function question(Test $test, string $subjectName, string $topicName, array $attributes = []): Question
    {
        $subject = Subject::firstOrCreate(['slug' => str($subjectName)->slug()->toString()], ['name' => $subjectName]);
        $topic = Topic::firstOrCreate(['subject_id' => $subject->id, 'slug' => str($topicName)->slug()->toString()], ['name' => $topicName]);
        $question = Question::create(array_merge([
            'subject_id' => $subject->id, 'topic_id' => $topic->id, 'question_text' => $topicName,
            'content_hash' => hash('sha256', $test->id.$topicName), 'is_active' => true, 'is_published' => true,
        ], $attributes));
        $test->questions()->attach($question);

        return $question;
    }

    private function browse(array $filters = []): array
    {
        return app(TestCatalogService::class)->browse(Test::where('is_active', true), $filters);
    }

    public function test_category_limits_exams_tests_and_types_and_discards_a_stale_exam(): void
    {
        $ssc = $this->exam('SSC CGL', 'SSC');
        $police = $this->exam('Bihar Police SI', 'Bihar Police');
        $sscTest = $this->paper($ssc);
        $this->paper($police, 'full_mock');

        $catalog = $this->browse(['category' => $ssc->exam_category_id, 'exam' => $police->id, 'subject' => 999, 'topic' => 999, 'type' => 'full_mock']);

        $this->assertSame([$ssc->id], $catalog['exams']->pluck('id')->all());
        $this->assertSame([$sscTest->id], $catalog['tests']->pluck('id')->all());
        $this->assertSame(['practice'], $catalog['testTypes']->all());
        $this->assertArrayNotHasKey('exam', $catalog['filters']);
        $this->assertArrayNotHasKey('subject', $catalog['filters']);
        $this->assertArrayNotHasKey('topic', $catalog['filters']);
        $this->assertArrayNotHasKey('type', $catalog['filters']);
    }

    public function test_initial_catalog_waits_for_parent_choices_and_direct_exam_links_infer_category(): void
    {
        $ssc = $this->exam('SSC CHSL', 'SSC');
        $paper = $this->paper($ssc);
        $this->question($paper, 'Mathematics', 'Percentage');

        $initial = $this->browse();
        $direct = $this->browse(['exam' => $ssc->id]);

        $this->assertSame([], $initial['exams']->all());
        $this->assertSame([], $initial['subjects']->all());
        $this->assertSame([], $initial['topics']->all());
        $this->assertSame($ssc->exam_category_id, $direct['filters']['category']);
        $this->assertSame(['Mathematics'], $direct['subjects']->pluck('name')->all());
        $this->assertSame([], $direct['topics']->all());
    }

    public function test_exam_subject_topic_filters_use_questions_in_available_tests_only(): void
    {
        $ssc = $this->exam('SSC CGL', 'SSC');
        $police = $this->exam('Bihar Police SI', 'Bihar Police');
        $percentage = $this->paper($ssc);
        $profit = $this->paper($ssc);
        $other = $this->paper($police);
        $q = $this->question($percentage, 'Mathematics', 'Percentage');
        $this->question($profit, 'Mathematics', 'Profit and Loss');
        $this->question($other, 'Law', 'Police Law');
        $this->question($profit, 'Draft Subject', 'Draft Topic', ['is_published' => false]);
        $this->question($profit, 'Inactive Subject', 'Inactive Topic', ['is_active' => false]);

        $catalog = $this->browse(['category' => $ssc->exam_category_id, 'exam' => $ssc->id, 'subject' => $q->subject_id, 'topic' => $q->topic_id]);

        $this->assertSame(['Mathematics'], $catalog['subjects']->pluck('name')->all());
        $this->assertSame(['Percentage', 'Profit and Loss'], $catalog['topics']->pluck('name')->all());
        $this->assertSame([$percentage->id], $catalog['tests']->pluck('id')->all());
    }

    public function test_topic_from_another_subject_is_reset_without_leaking_other_tests(): void
    {
        $exam = $this->exam('SSC CGL', 'SSC');
        $math = $this->paper($exam);
        $law = $this->paper($exam);
        $mathQuestion = $this->question($math, 'Mathematics', 'Percentage');
        $lawQuestion = $this->question($law, 'Law', 'Constitution');

        $catalog = $this->browse(['exam' => $exam->id, 'subject' => $mathQuestion->subject_id, 'topic' => $lawQuestion->topic_id]);

        $this->assertSame(['Percentage'], $catalog['topics']->pluck('name')->all());
        $this->assertSame([$math->id], $catalog['tests']->pluck('id')->all());
        $this->assertArrayNotHasKey('topic', $catalog['filters']);
    }

    public function test_dedicated_test_metadata_is_available_without_loading_question_content(): void
    {
        $exam = $this->exam('SSC CGL', 'SSC');
        $subject = Subject::create(['name' => 'Reasoning', 'slug' => 'reasoning']);
        $topic = Topic::create(['subject_id' => $subject->id, 'name' => 'Series', 'slug' => 'series']);
        $paper = $this->paper($exam, 'topic', ['subject_id' => $subject->id, 'topic_id' => $topic->id]);

        $catalog = $this->browse(['exam' => $exam->id, 'subject' => $subject->id, 'topic' => $topic->id, 'type' => 'topic']);

        $this->assertSame([$subject->id], $catalog['subjects']->pluck('id')->all());
        $this->assertSame([$topic->id], $catalog['topics']->pluck('id')->all());
        $this->assertSame([$paper->id], $catalog['tests']->pluck('id')->all());
    }

    public function test_access_scope_applies_to_all_options_even_for_a_forged_exam_link(): void
    {
        $ssc = $this->exam('SSC CGL', 'SSC');
        $police = $this->exam('Bihar Police SI', 'Bihar Police');
        $this->question($this->paper($ssc), 'Mathematics', 'Percentage');
        $this->question($this->paper($police), 'Law', 'Police Law');

        $catalog = app(TestCatalogService::class)->browse(Test::where('exam_id', $ssc->id), ['exam' => $police->id]);

        $this->assertSame([$ssc->exam_category_id], $catalog['categories']->pluck('id')->all());
        $this->assertSame([], $catalog['exams']->all());
        $this->assertSame([], $catalog['subjects']->all());
        $this->assertSame(0, $catalog['tests']->total());
    }

    public function test_search_and_type_stay_inside_the_selected_category_and_unknown_category_is_empty(): void
    {
        $ssc = $this->exam('SSC CGL', 'SSC');
        $bssc = $this->exam('BSSC CGL', 'Bihar');
        $paper = $this->paper($ssc, 'full_mock');
        $this->paper($ssc, 'practice');
        $this->paper($bssc, 'full_mock');

        $search = $this->browse(['category' => $ssc->exam_category_id, 'q' => 'SSC CGL', 'type' => 'full_mock']);
        $unknown = $this->browse(['category' => 999999]);

        $this->assertSame([$paper->id], $search['tests']->pluck('id')->all());
        $this->assertSame(0, $unknown['tests']->total());
        $this->assertSame([], $unknown['exams']->all());
    }
}
