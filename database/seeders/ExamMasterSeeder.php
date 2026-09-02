<?php

namespace Database\Seeders;

use App\Models\ExamCategory;
use App\Models\Subject;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ExamMasterSeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['General Competition', 'सामान्य प्रतियोगिता'],
            ['SSC', 'कर्मचारी चयन आयोग'],
            ['Railway', 'रेलवे'],
            ['Banking', 'बैंकिंग'],
            ['UPSC', 'संघ लोक सेवा आयोग'],
            ['BPSC', 'बिहार लोक सेवा आयोग'],
            ['Bihar SSC', 'बिहार कर्मचारी चयन आयोग'],
            ['Bihar Police', 'बिहार पुलिस'],
            ['Teaching Exams', 'शिक्षक भर्ती परीक्षाएँ'],
            ['Defence', 'रक्षा परीक्षाएँ'],
            ['Police & Paramilitary', 'पुलिस एवं अर्धसैनिक बल'],
            ['Engineering Entrance', 'इंजीनियरिंग प्रवेश परीक्षा'],
            ['Medical Entrance', 'मेडिकल प्रवेश परीक्षा'],
            ['University Entrance', 'विश्वविद्यालय प्रवेश परीक्षा'],
            ['Management / Law Entrance', 'मैनेजमेंट / लॉ प्रवेश परीक्षा'],
            ['Technical Exams', 'तकनीकी परीक्षाएँ'],
            ['Other Government Exams', 'अन्य सरकारी परीक्षाएँ'],
        ];

        foreach ($categories as $index => [$name, $nameHi]) {
            ExamCategory::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'name_hi' => $nameHi,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }

        $subjects = [
            ['General Knowledge', 'सामान्य ज्ञान'],
            ['Current Affairs', 'समसामयिकी'],
            ['General Science', 'सामान्य विज्ञान'],
            ['Mathematics', 'गणित'],
            ['Quantitative Aptitude', 'मात्रात्मक योग्यता'],
            ['Reasoning', 'तार्किक क्षमता'],
            ['English Language', 'अंग्रेजी भाषा'],
            ['Hindi Language', 'हिन्दी भाषा'],
            ['Computer Knowledge', 'कंप्यूटर ज्ञान'],
            ['History', 'इतिहास'],
            ['Geography', 'भूगोल'],
            ['Indian Polity', 'भारतीय राजव्यवस्था'],
            ['Economics', 'अर्थशास्त्र'],
            ['Physics', 'भौतिक विज्ञान'],
            ['Chemistry', 'रसायन विज्ञान'],
            ['Biology', 'जीव विज्ञान'],
            ['Environment & Ecology', 'पर्यावरण एवं पारिस्थितिकी'],
            ['Static GK', 'स्थैतिक सामान्य ज्ञान'],
        ];

        foreach ($subjects as $index => [$name, $nameHi]) {
            Subject::updateOrCreate(
                ['slug' => Str::slug($name)],
                [
                    'name' => $name,
                    'name_hi' => $nameHi,
                    'sort_order' => $index + 1,
                    'is_active' => true,
                ]
            );
        }
    }
}
