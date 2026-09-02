<?php

namespace Database\Seeders;

use App\Models\Exam;
use App\Models\ExamCategory;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ImportantExamSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'General Competition' => [
                ['General Competitive Examination', 'सामान्य प्रतियोगिता परीक्षा'],
                ['General Practice Test', 'सामान्य अभ्यास परीक्षा'],
                ['Current Affairs Test', 'करेंट अफेयर्स टेस्ट'],
            ],

            'SSC' => [
                ['SSC CGL', 'एसएससी सीजीएल'],
                ['SSC CHSL', 'एसएससी सीएचएसएल'],
                ['SSC MTS', 'एसएससी एमटीएस'],
                ['SSC GD Constable', 'एसएससी जीडी कांस्टेबल'],
                ['SSC CPO', 'एसएससी सीपीओ'],
                ['SSC Stenographer', 'एसएससी स्टेनोग्राफर'],
                ['SSC Selection Post', 'एसएससी सिलेक्शन पोस्ट'],
            ],

            'Railway' => [
                ['RRB NTPC', 'आरआरबी एनटीपीसी'],
                ['RRB Group D', 'आरआरबी ग्रुप डी'],
                ['RRB ALP', 'आरआरबी एएलपी'],
                ['RRB Technician', 'आरआरबी टेक्नीशियन'],
                ['RRB JE', 'आरआरबी जेई'],
                ['RPF Constable', 'आरपीएफ कांस्टेबल'],
                ['RPF SI', 'आरपीएफ एसआई'],
            ],

            'Banking' => [
                ['IBPS PO', 'आईबीपीएस पीओ'],
                ['IBPS Clerk', 'आईबीपीएस क्लर्क'],
                ['IBPS RRB PO', 'आईबीपीएस आरआरबी पीओ'],
                ['IBPS RRB Clerk', 'आईबीपीएस आरआरबी क्लर्क'],
                ['SBI PO', 'एसबीआई पीओ'],
                ['SBI Clerk', 'एसबीआई क्लर्क'],
                ['RBI Grade B', 'आरबीआई ग्रेड बी'],
                ['RBI Assistant', 'आरबीआई असिस्टेंट'],
                ['NABARD Grade A', 'नाबार्ड ग्रेड ए'],
            ],

            'UPSC' => [
                ['UPSC Civil Services', 'यूपीएससी सिविल सेवा'],
                ['UPSC CDS', 'यूपीएससी सीडीएस'],
                ['UPSC NDA & NA', 'यूपीएससी एनडीए एवं एनए'],
                ['UPSC CAPF AC', 'यूपीएससी सीएपीएफ एसी'],
                ['UPSC Engineering Services', 'यूपीएससी इंजीनियरिंग सेवा'],
                ['UPSC Combined Medical Services', 'यूपीएससी संयुक्त चिकित्सा सेवा'],
            ],

            'BPSC' => [
                ['BPSC Combined Competitive Examination', 'बीपीएससी संयुक्त प्रतियोगिता परीक्षा'],
                ['BPSC Teacher Recruitment', 'बीपीएससी शिक्षक भर्ती'],
                ['BPSC Assistant', 'बीपीएससी सहायक'],
                ['BPSC Assistant Engineer', 'बीपीएससी सहायक अभियंता'],
            ],

            'Bihar SSC' => [
                ['BSSC CGL', 'बीएसएससी सीजीएल'],
                ['BSSC Inter Level', 'बीएसएससी इंटर स्तरीय'],
                ['BSSC Office Attendant', 'बीएसएससी कार्यालय परिचारी'],
            ],

            'Bihar Police' => [
                ['Bihar Police Constable', 'बिहार पुलिस कांस्टेबल'],
                ['Bihar Police SI', 'बिहार पुलिस अवर निरीक्षक'],
                ['Bihar Police Prohibition Constable', 'बिहार मद्य निषेध कांस्टेबल'],
                ['Bihar Police Driver Constable', 'बिहार पुलिस चालक कांस्टेबल'],
            ],

            'Teaching Exams' => [
                ['CTET', 'सीटेट'],
                ['Bihar STET', 'बिहार एसटीईटी'],
                ['Bihar Teacher Recruitment', 'बिहार शिक्षक भर्ती'],
                ['UGC NET', 'यूजीसी नेट'],
                ['KVS Recruitment', 'केवीएस भर्ती'],
                ['NVS Recruitment', 'एनवीएस भर्ती'],
            ],

            'Defence' => [
                ['NDA', 'एनडीए'],
                ['CDS', 'सीडीएस'],
                ['AFCAT', 'एएफकैट'],
                ['Indian Army Agniveer', 'भारतीय सेना अग्निवीर'],
                ['Indian Navy Agniveer', 'भारतीय नौसेना अग्निवीर'],
                ['Indian Air Force Agniveervayu', 'भारतीय वायुसेना अग्निवीरवायु'],
            ],

            'Police & Paramilitary' => [
                ['CAPF', 'केंद्रीय सशस्त्र पुलिस बल'],
                ['CRPF Recruitment', 'सीआरपीएफ भर्ती'],
                ['BSF Recruitment', 'बीएसएफ भर्ती'],
                ['CISF Recruitment', 'सीआईएसएफ भर्ती'],
                ['ITBP Recruitment', 'आईटीबीपी भर्ती'],
                ['SSB Recruitment', 'एसएसबी भर्ती'],
            ],

            'Engineering Entrance' => [
                ['JEE Main', 'जेईई मेन'],
                ['JEE Advanced', 'जेईई एडवांस्ड'],
                ['BCECE Engineering', 'बीसीईसीई इंजीनियरिंग'],
            ],

            'Medical Entrance' => [
                ['NEET UG', 'नीट यूजी'],
                ['NEET PG', 'नीट पीजी'],
            ],

            'University Entrance' => [
                ['CUET UG', 'सीयूईटी यूजी'],
                ['CUET PG', 'सीयूईटी पीजी'],
                ['BHU Entrance', 'बीएचयू प्रवेश परीक्षा'],
            ],

            'Management / Law Entrance' => [
                ['CAT', 'कैट'],
                ['MAT', 'मैट'],
                ['CMAT', 'सीमैट'],
                ['CLAT', 'क्लैट'],
                ['AILET', 'एआईलेट'],
            ],

            'Technical Exams' => [
                ['GATE', 'गेट'],
                ['ITI Competitive Examination', 'आईटीआई प्रतियोगिता परीक्षा'],
                ['Polytechnic Entrance', 'पॉलिटेक्निक प्रवेश परीक्षा'],
                ['Junior Engineer Examination', 'जूनियर इंजीनियर परीक्षा'],
            ],

            'Other Government Exams' => [
                ['LIC AAO', 'एलआईसी एएओ'],
                ['LIC Assistant', 'एलआईसी असिस्टेंट'],
                ['EPFO Examination', 'ईपीएफओ परीक्षा'],
                ['ESIC Recruitment', 'ईएसआईसी भर्ती'],
                ['India Post Recruitment', 'इंडिया पोस्ट भर्ती'],
                ['FCI Recruitment', 'एफसीआई भर्ती'],
            ],
        ];

        foreach ($data as $categoryName => $exams) {
            $category = ExamCategory::where('name', $categoryName)->firstOrFail();

            foreach ($exams as [$name, $nameHi]) {
                Exam::updateOrCreate(
                    ['slug' => Str::slug($name)],
                    [
                        'exam_category_id' => $category->id,
                        'name' => $name,
                        'name_hi' => $nameHi,
                        'is_featured' => in_array($name, [
                            'SSC CGL',
                            'RRB NTPC',
                            'IBPS PO',
                            'SBI PO',
                            'UPSC Civil Services',
                            'BPSC Combined Competitive Examination',
                            'Bihar Police Constable',
                            'CTET',
                            'JEE Main',
                            'NEET UG',
                        ]),
                        'is_active' => true,
                    ]
                );
            }
        }

        /*
         * Common subject mapping.
         * Detailed syllabus/topic mapping will be layered on top later.
         */
        $common = Subject::whereIn('name', [
            'General Knowledge',
            'Current Affairs',
            'General Science',
            'Mathematics',
            'Quantitative Aptitude',
            'Reasoning',
            'English Language',
            'Hindi Language',
            'Computer Knowledge',
            'History',
            'Geography',
            'Indian Polity',
            'Economics',
            'Environment & Ecology',
            'Static GK',
        ])->pluck('id')->all();

        Exam::query()->each(function (Exam $exam) use ($common) {
            $exam->subjects()->syncWithoutDetaching($common);
        });

        $science = Subject::whereIn('name', [
            'Physics',
            'Chemistry',
            'Biology',
        ])->pluck('id')->all();

        Exam::whereHas('category', fn ($q) =>
            $q->whereIn('name', [
                'Engineering Entrance',
                'Medical Entrance',
                'Technical Exams',
            ])
        )->each(function (Exam $exam) use ($science) {
            $exam->subjects()->syncWithoutDetaching($science);
        });
    }
}
