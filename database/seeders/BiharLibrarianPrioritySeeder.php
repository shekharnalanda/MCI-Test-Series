<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\QuestionGenerationJob;
use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BiharLibrarianPrioritySeeder extends Seeder
{
    public const EXAM_SLUG = 'bihar-librarian-recruitment';
    public const PLANNED_TESTS = 75;
    public const QUESTION_TARGET = 1500;

    public function run(): void
    {
        $category = ExamCategory::where('name', 'Other Government Exams')->firstOrFail();
        $subject = Subject::updateOrCreate(
            ['slug' => 'library-and-information-science'],
            ['name' => 'Library and Information Science', 'name_hi' => 'पुस्तकालय एवं सूचना विज्ञान',
                'sort_order' => 19, 'is_active' => true]
        );

        $topics = $this->topics();
        foreach ($topics as $index => [$name, $nameHi]) {
            Topic::updateOrCreate(
                ['subject_id' => $subject->id, 'slug' => Str::slug($name)],
                ['name' => $name, 'name_hi' => $nameHi, 'sort_order' => $index + 1, 'is_active' => true]
            );
        }

        $exam = Exam::updateOrCreate(
            ['slug' => self::EXAM_SLUG],
            [
                'exam_category_id' => $category->id,
                'name' => 'Bihar Librarian Recruitment',
                'name_hi' => 'बिहार लाइब्रेरियन भर्ती',
                'description' => 'Priority preparation track. Exact recruitment dates, vacancies and paper pattern remain provisional until an official Bihar notification is published.',
                'conducting_body' => 'Government of Bihar / recruiting commission to be confirmed by official notification',
                'official_url' => 'https://bssc.bihar.gov.in/NoticeBoard.htm',
                'pattern' => $this->pattern(),
                'syllabus' => ['status' => 'provisional_until_official_notification',
                    'core_subject' => collect($topics)->pluck(0)->all(),
                    'support_sections' => ['General Knowledge', 'Current Affairs', 'Hindi Language', 'Computer Knowledge']],
                'is_featured' => true,
                'is_active' => true,
            ]
        );

        $subjectIds = Subject::whereIn('name', [
            'Library and Information Science', 'General Knowledge', 'Current Affairs',
            'Hindi Language', 'Computer Knowledge',
        ])->pluck('id')->all();
        $exam->subjects()->sync($subjectIds);

        QuestionGenerationJob::updateOrCreate(
            ['job_code' => 'QG-BIHAR-LIBRARIAN-PRIORITY'],
            [
                'exam_id' => $exam->id,
                'subject_id' => $subject->id,
                'target_count' => self::QUESTION_TARGET,
                'difficulty' => 'mixed',
                'language' => 'bilingual',
                'priority' => 100,
                'status' => 'pending',
                'generation_rules' => [
                    'planned_tests' => self::PLANNED_TESTS,
                    'question_bank_target' => self::QUESTION_TARGET,
                    'difficulty_targets' => ['easy' => 450, 'medium' => 750, 'hard' => 300],
                    'verified_required' => true, 'published_required' => true,
                    'official_or_open_source_required' => true, 'duplicate_control' => true,
                    'official_pattern_override_required' => true,
                ],
            ]
        );
    }

    private function pattern(): array
    {
        return [
            'status' => 'provisional_until_official_notification',
            'planned_tests' => self::PLANNED_TESTS,
            'schedule' => [
                ['type' => 'core_topic_practice', 'count' => 50, 'questions_each' => 25],
                ['type' => 'mixed_sectional', 'count' => 15, 'questions_each' => 50],
                ['type' => 'full_mock', 'count' => 10, 'questions_each' => 100],
            ],
            'scoring_provisional' => ['positive_marks' => 1, 'negative_marks' => 0.25],
            'generation_gate' => 'Create tests only after sufficient verified bilingual questions are available.',
        ];
    }

    private function topics(): array
    {
        return [
            ['Foundations and History of Libraries', 'पुस्तकालय की आधारभूत अवधारणाएँ एवं इतिहास'],
            ['Five Laws and Library Philosophy', 'पंचसूत्र एवं पुस्तकालय दर्शन'],
            ['Library Classification DDC UDC and CC', 'पुस्तकालय वर्गीकरण: डीडीसी, यूडीसी एवं सीसी'],
            ['Cataloguing AACR2 CCC and RDA', 'सूचीकरण: एएसीआर-2, सीसीसी एवं आरडीए'],
            ['Reference and Information Sources', 'संदर्भ एवं सूचना स्रोत'],
            ['Information Services and User Studies', 'सूचना सेवाएँ एवं उपयोगकर्ता अध्ययन'],
            ['Library Management and Administration', 'पुस्तकालय प्रबंधन एवं प्रशासन'],
            ['Library Automation and ICT', 'पुस्तकालय स्वचालन एवं आईसीटी'],
            ['Digital Libraries and Repositories', 'डिजिटल पुस्तकालय एवं रिपॉजिटरी'],
            ['Research Methods and Statistics', 'अनुसंधान पद्धति एवं सांख्यिकी'],
            ['Bibliometrics Scientometrics and Informetrics', 'ग्रंथमिति, वैज्ञानिकमिति एवं सूचनामिति'],
            ['Preservation and Conservation', 'संरक्षण एवं परिरक्षण'],
            ['Library Legislation Copyright and Ethics', 'पुस्तकालय विधान, कॉपीराइट एवं नैतिकता'],
            ['Academic Public School and Special Libraries', 'शैक्षणिक, सार्वजनिक, विद्यालय एवं विशेष पुस्तकालय'],
            ['Library Networks Consortia and Resource Sharing', 'पुस्तकालय नेटवर्क, कंसोर्टियम एवं संसाधन साझाकरण'],
        ];
    }
}
