<?php

namespace Tests\Feature;

use App\Models\ContentSource;
use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\QuestionGenerationJob;
use App\Models\Subject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProcessQuestionGenerationJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_only_complete_bilingual_questions_and_updates_the_job(): void
    {
        $category = ExamCategory::create([
            'name' => 'Library',
            'slug' => 'library',
            'is_active' => true,
        ]);
        $exam = Exam::create([
            'exam_category_id' => $category->id,
            'name' => 'Bihar Librarian Recruitment',
            'slug' => 'bihar-librarian-recruitment',
            'is_active' => true,
        ]);
        $subject = Subject::create([
            'name' => 'Library and Information Science',
            'slug' => 'library-and-information-science',
            'is_active' => true,
        ]);
        $exam->subjects()->attach($subject);

        $source = ContentSource::create([
            'name' => 'MCI Internal Verified',
            'slug' => 'mci-internal-verified',
            'source_type' => 'internal',
            'trust_score' => 100,
            'allow_question_generation' => true,
            'auto_publish_allowed' => true,
            'is_active' => true,
            'is_quarantined' => false,
        ]);

        $job = QuestionGenerationJob::create([
            'job_code' => 'QG-LIBRARIAN-TEST',
            'exam_id' => $exam->id,
            'subject_id' => $subject->id,
            'target_count' => 2,
            'difficulty' => 'mixed',
            'language' => 'bilingual',
            'status' => 'pending',
        ]);

        $valid = [
            'question_text' => 'Which law is associated with library science?',
            'question_text_hi' => 'कौन-सा नियम पुस्तकालय विज्ञान से संबंधित है?',
            'explanation' => 'The Five Laws were formulated by S. R. Ranganathan.',
            'explanation_hi' => 'पाँच नियम एस. आर. रंगनाथन ने प्रतिपादित किए थे।',
            'difficulty' => 'easy',
            'source_url' => 'https://www.indiaculture.gov.in/example',
            'source_reference' => 'Government library science reference',
            'source_published_at' => '2026-01-01',
            'options' => [
                ['option_text' => 'Five Laws', 'option_text_hi' => 'पाँच नियम', 'is_correct' => true],
                ['option_text' => 'Two Laws', 'option_text_hi' => 'दो नियम', 'is_correct' => false],
                ['option_text' => 'Seven Laws', 'option_text_hi' => 'सात नियम', 'is_correct' => false],
                ['option_text' => 'Ten Laws', 'option_text_hi' => 'दस नियम', 'is_correct' => false],
            ],
        ];

        $invalid = $valid;
        $invalid['question_text'] = 'Incomplete bilingual options';
        $invalid['options'] = array_slice($invalid['options'], 0, 3);

        $path = tempnam(sys_get_temp_dir(), 'mci-questions-');
        file_put_contents($path, json_encode([$valid, $invalid], JSON_UNESCAPED_UNICODE));

        try {
            $result = app(QuestionGenerationJobImportService::class)
                ->importJsonFile($job->job_code, $path, $source);

            $this->assertSame(1, $result['accepted']);
            $this->assertSame(1, $result['rejected']);
        } finally {
            @unlink($path);
        }

        $job->refresh();

        $this->assertSame(2, $job->generated_count);
        $this->assertSame(1, $job->accepted_count);
        $this->assertSame(1, $job->rejected_count);
        $this->assertSame('partial', $job->status);

        $this->assertDatabaseHas('questions', [
            'subject_id' => $subject->id,
            'language' => 'bilingual',
            'verification_status' => 'verified',
            'is_published' => true,
        ]);
        $this->assertDatabaseHas('exam_question', [
            'exam_id' => $exam->id,
        ]);
    }
}
