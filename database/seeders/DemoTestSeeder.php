<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Test;
use App\Models\TestSeries;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DemoTestSeeder extends Seeder
{
    public function run(): void
    {
        $subject = Subject::where('name', 'General Knowledge')->firstOrFail();

        $exam = Exam::where('name', 'General Competitive Examination')
            ->firstOrFail();

        $series = TestSeries::updateOrCreate(
            ['slug' => 'free-general-competition-demo'],
            [
                'exam_id' => $exam->id,
                'name' => 'Free General Competition Demo',
                'name_hi' => 'निःशुल्क सामान्य प्रतियोगिता डेमो',
                'description' => 'Professional MCI Test Series demonstration test.',
                'series_type' => 'demo',
                'price' => 0,
                'validity_days' => null,
                'test_limit' => null,
                'is_free' => true,
                'is_featured' => true,
                'is_active' => true,
            ]
        );

        $items = [
            [
                'q' => 'What is the capital of India?',
                'hi' => 'भारत की राजधानी क्या है?',
                'options' => [
                    ['New Delhi', 'नई दिल्ली', true],
                    ['Mumbai', 'मुंबई', false],
                    ['Kolkata', 'कोलकाता', false],
                    ['Chennai', 'चेन्नई', false],
                ],
                'explanation' => 'New Delhi is the capital of India.',
            ],
            [
                'q' => 'Which planet is known as the Red Planet?',
                'hi' => 'किस ग्रह को लाल ग्रह कहा जाता है?',
                'options' => [
                    ['Venus', 'शुक्र', false],
                    ['Mars', 'मंगल', true],
                    ['Jupiter', 'बृहस्पति', false],
                    ['Mercury', 'बुध', false],
                ],
                'explanation' => 'Mars is commonly known as the Red Planet.',
            ],
            [
                'q' => 'The Constitution of India came into effect on which date?',
                'hi' => 'भारत का संविधान किस तिथि को लागू हुआ?',
                'options' => [
                    ['15 August 1947', '15 अगस्त 1947', false],
                    ['26 January 1950', '26 जनवरी 1950', true],
                    ['26 November 1949', '26 नवंबर 1949', false],
                    ['2 October 1950', '2 अक्टूबर 1950', false],
                ],
                'explanation' => 'The Constitution came into effect on 26 January 1950.',
            ],
            [
                'q' => 'Which is the largest planet in the Solar System?',
                'hi' => 'सौरमंडल का सबसे बड़ा ग्रह कौन सा है?',
                'options' => [
                    ['Earth', 'पृथ्वी', false],
                    ['Saturn', 'शनि', false],
                    ['Jupiter', 'बृहस्पति', true],
                    ['Mars', 'मंगल', false],
                ],
                'explanation' => 'Jupiter is the largest planet in the Solar System.',
            ],
            [
                'q' => 'Who wrote the Indian National Anthem?',
                'hi' => 'भारत का राष्ट्रगान किसने लिखा?',
                'options' => [
                    ['Rabindranath Tagore', 'रवीन्द्रनाथ टैगोर', true],
                    ['Bankim Chandra Chattopadhyay', 'बंकिम चंद्र चट्टोपाध्याय', false],
                    ['Mahatma Gandhi', 'महात्मा गांधी', false],
                    ['Sarojini Naidu', 'सरोजिनी नायडू', false],
                ],
                'explanation' => 'Rabindranath Tagore wrote Jana Gana Mana.',
            ],
        ];

        $questionIds = [];

        foreach ($items as $item) {

            $hash = hash(
                'sha256',
                mb_strtolower(trim($item['q']))
            );

            $question = Question::updateOrCreate(
                ['content_hash' => $hash],
                [
                    'subject_id' => $subject->id,
                    'question_text' => $item['q'],
                    'question_text_hi' => $item['hi'],
                    'explanation' => $item['explanation'],
                    'question_type' => 'single_choice',
                    'difficulty' => 'easy',
                    'language' => 'bilingual',
                    'verification_status' => 'verified',
                    'generation_method' => 'manual',
                    'source_name' => 'MCI Demo Verified Content',
                    'source_confidence' => 100,
                    'is_published' => true,
                    'is_active' => true,
                ]
            );

            $question->options()->delete();

            foreach ($item['options'] as $index => $option) {
                $question->options()->create([
                    'option_text' => $option[0],
                    'option_text_hi' => $option[1],
                    'is_correct' => $option[2],
                    'sort_order' => $index + 1,
                ]);
            }

            $question->exams()->syncWithoutDetaching([
                $exam->id => ['relevance_score' => 100]
            ]);

            $questionIds[] = $question->id;
        }

        $test = Test::updateOrCreate(
            ['title' => 'MCI Free Demo Test - General Competition'],
            [
                'test_series_id' => $series->id,
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'title_hi' => 'एमसीआई निःशुल्क डेमो टेस्ट - सामान्य प्रतियोगिता',
                'instructions' => 'Select the correct option. The test is timed and automatically evaluated.',
                'test_type' => 'demo',
                'total_questions' => count($questionIds),
                'duration_minutes' => 10,
                'positive_marks' => 1,
                'negative_marks' => 0.25,
                'randomize_questions' => true,
                'randomize_options' => true,
                'auto_generated' => false,
                'is_demo' => true,
                'is_active' => true,
            ]
        );

        $sync = [];

        foreach ($questionIds as $index => $id) {
            $sync[$id] = [
                'sort_order' => $index + 1,
                'marks' => 1,
                'negative_marks' => 0.25,
            ];
        }

        $test->questions()->sync($sync);
    }
}
