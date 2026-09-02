<?php

namespace Database\Seeders;

use App\Models\Subject;
use App\Models\Topic;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class TopicTaxonomySeeder extends Seeder
{
    public function run(): void
    {
        $taxonomy = [

            'General Knowledge' => [
                ['India', 'भारत'],
                ['World', 'विश्व'],
                ['Important Days', 'महत्वपूर्ण दिवस'],
                ['Awards and Honours', 'पुरस्कार एवं सम्मान'],
                ['Books and Authors', 'पुस्तकें एवं लेखक'],
                ['Sports', 'खेल'],
                ['Organizations', 'संगठन'],
                ['First in India', 'भारत में प्रथम'],
                ['First in World', 'विश्व में प्रथम'],
                ['Important Places', 'महत्वपूर्ण स्थान'],
            ],

            'Current Affairs' => [
                ['National Current Affairs', 'राष्ट्रीय करेंट अफेयर्स'],
                ['International Current Affairs', 'अंतरराष्ट्रीय करेंट अफेयर्स'],
                ['Economy and Banking', 'अर्थव्यवस्था एवं बैंकिंग'],
                ['Science and Technology', 'विज्ञान एवं प्रौद्योगिकी'],
                ['Sports Current Affairs', 'खेल करेंट अफेयर्स'],
                ['Awards and Appointments', 'पुरस्कार एवं नियुक्तियाँ'],
                ['Government Schemes', 'सरकारी योजनाएँ'],
                ['Defence Current Affairs', 'रक्षा करेंट अफेयर्स'],
                ['Environment Current Affairs', 'पर्यावरण करेंट अफेयर्स'],
                ['Reports and Indexes', 'रिपोर्ट एवं सूचकांक'],
            ],

            'Mathematics' => [
                ['Number System', 'संख्या पद्धति'],
                ['Simplification', 'सरलीकरण'],
                ['LCM and HCF', 'लघुत्तम एवं महत्तम समापवर्तक'],
                ['Ratio and Proportion', 'अनुपात एवं समानुपात'],
                ['Percentage', 'प्रतिशत'],
                ['Profit and Loss', 'लाभ एवं हानि'],
                ['Simple Interest', 'साधारण ब्याज'],
                ['Compound Interest', 'चक्रवृद्धि ब्याज'],
                ['Average', 'औसत'],
                ['Time and Work', 'समय एवं कार्य'],
                ['Pipes and Cisterns', 'पाइप एवं टंकी'],
                ['Time Speed and Distance', 'समय गति एवं दूरी'],
                ['Train Problems', 'रेलगाड़ी संबंधी प्रश्न'],
                ['Boat and Stream', 'नाव एवं धारा'],
                ['Mensuration', 'क्षेत्रमिति'],
                ['Algebra', 'बीजगणित'],
                ['Geometry', 'ज्यामिति'],
                ['Trigonometry', 'त्रिकोणमिति'],
                ['Data Interpretation', 'आँकड़ा विश्लेषण'],
            ],

            'Quantitative Aptitude' => [
                ['Arithmetic', 'अंकगणित'],
                ['Percentage', 'प्रतिशत'],
                ['Ratio', 'अनुपात'],
                ['Average', 'औसत'],
                ['Profit Loss Discount', 'लाभ हानि छूट'],
                ['Interest', 'ब्याज'],
                ['Time and Work', 'समय एवं कार्य'],
                ['Speed Distance', 'गति एवं दूरी'],
                ['Mixture and Alligation', 'मिश्रण'],
                ['Data Interpretation', 'डेटा इंटरप्रिटेशन'],
            ],

            'Reasoning' => [
                ['Analogy', 'सादृश्य'],
                ['Classification', 'वर्गीकरण'],
                ['Series', 'श्रृंखला'],
                ['Coding Decoding', 'कोडिंग डिकोडिंग'],
                ['Blood Relation', 'रक्त संबंध'],
                ['Direction Test', 'दिशा परीक्षण'],
                ['Ranking', 'क्रम एवं रैंकिंग'],
                ['Syllogism', 'न्यायवाक्य'],
                ['Statement and Conclusion', 'कथन एवं निष्कर्ष'],
                ['Puzzle', 'पहेली'],
                ['Seating Arrangement', 'बैठक व्यवस्था'],
                ['Calendar', 'कैलेंडर'],
                ['Clock', 'घड़ी'],
                ['Non Verbal Reasoning', 'अशाब्दिक तर्क'],
            ],

            'English Language' => [
                ['Grammar', 'व्याकरण'],
                ['Vocabulary', 'शब्दावली'],
                ['Synonyms', 'पर्यायवाची'],
                ['Antonyms', 'विलोम'],
                ['One Word Substitution', 'एक शब्द प्रतिस्थापन'],
                ['Idioms and Phrases', 'मुहावरे एवं वाक्यांश'],
                ['Error Detection', 'त्रुटि पहचान'],
                ['Sentence Improvement', 'वाक्य सुधार'],
                ['Cloze Test', 'क्लोज टेस्ट'],
                ['Reading Comprehension', 'पठन बोध'],
                ['Active Passive Voice', 'कर्तृवाच्य कर्मवाच्य'],
                ['Direct Indirect Speech', 'प्रत्यक्ष अप्रत्यक्ष कथन'],
            ],

            'Hindi Language' => [
                ['हिन्दी व्याकरण', 'हिन्दी व्याकरण'],
                ['संधि', 'संधि'],
                ['समास', 'समास'],
                ['उपसर्ग एवं प्रत्यय', 'उपसर्ग एवं प्रत्यय'],
                ['पर्यायवाची शब्द', 'पर्यायवाची शब्द'],
                ['विलोम शब्द', 'विलोम शब्द'],
                ['मुहावरे एवं लोकोक्तियाँ', 'मुहावरे एवं लोकोक्तियाँ'],
                ['वाक्य शुद्धि', 'वाक्य शुद्धि'],
                ['अनेक शब्दों के लिए एक शब्द', 'अनेक शब्दों के लिए एक शब्द'],
                ['गद्यांश', 'गद्यांश'],
            ],

            'History' => [
                ['Ancient India', 'प्राचीन भारत'],
                ['Medieval India', 'मध्यकालीन भारत'],
                ['Modern India', 'आधुनिक भारत'],
                ['Freedom Struggle', 'स्वतंत्रता संग्राम'],
                ['World History', 'विश्व इतिहास'],
                ['Bihar History', 'बिहार का इतिहास'],
            ],

            'Geography' => [
                ['Physical Geography', 'भौतिक भूगोल'],
                ['Indian Geography', 'भारत का भूगोल'],
                ['World Geography', 'विश्व भूगोल'],
                ['Bihar Geography', 'बिहार का भूगोल'],
                ['Climate', 'जलवायु'],
                ['Rivers', 'नदियाँ'],
                ['Agriculture', 'कृषि'],
                ['Minerals and Resources', 'खनिज एवं संसाधन'],
            ],

            'Indian Polity' => [
                ['Constitution', 'संविधान'],
                ['Fundamental Rights', 'मौलिक अधिकार'],
                ['Directive Principles', 'नीति निदेशक तत्व'],
                ['Parliament', 'संसद'],
                ['President and Vice President', 'राष्ट्रपति एवं उपराष्ट्रपति'],
                ['Prime Minister and Council', 'प्रधानमंत्री एवं मंत्रिपरिषद'],
                ['Supreme Court', 'सर्वोच्च न्यायालय'],
                ['High Court', 'उच्च न्यायालय'],
                ['Election Commission', 'निर्वाचन आयोग'],
                ['Local Government', 'स्थानीय शासन'],
                ['Constitutional Bodies', 'संवैधानिक निकाय'],
            ],

            'Economics' => [
                ['Basic Economics', 'मूल अर्थशास्त्र'],
                ['Indian Economy', 'भारतीय अर्थव्यवस्था'],
                ['Banking', 'बैंकिंग'],
                ['Budget', 'बजट'],
                ['Taxation', 'कराधान'],
                ['Inflation', 'मुद्रास्फीति'],
                ['National Income', 'राष्ट्रीय आय'],
                ['Economic Planning', 'आर्थिक नियोजन'],
            ],

            'General Science' => [
                ['Physics Basics', 'भौतिक विज्ञान आधार'],
                ['Chemistry Basics', 'रसायन विज्ञान आधार'],
                ['Biology Basics', 'जीव विज्ञान आधार'],
                ['Everyday Science', 'दैनिक जीवन विज्ञान'],
                ['Scientific Instruments', 'वैज्ञानिक उपकरण'],
                ['Discoveries and Inventions', 'खोज एवं आविष्कार'],
            ],

            'Physics' => [
                ['Units and Measurements', 'मात्रक एवं मापन'],
                ['Motion', 'गति'],
                ['Force and Laws', 'बल एवं नियम'],
                ['Work Energy Power', 'कार्य ऊर्जा शक्ति'],
                ['Gravitation', 'गुरुत्वाकर्षण'],
                ['Heat', 'ऊष्मा'],
                ['Sound', 'ध्वनि'],
                ['Light', 'प्रकाश'],
                ['Electricity', 'विद्युत'],
                ['Magnetism', 'चुंबकत्व'],
            ],

            'Chemistry' => [
                ['Matter', 'पदार्थ'],
                ['Atomic Structure', 'परमाणु संरचना'],
                ['Periodic Table', 'आवर्त सारणी'],
                ['Chemical Reactions', 'रासायनिक अभिक्रियाएँ'],
                ['Acids Bases Salts', 'अम्ल क्षार लवण'],
                ['Metals and Non Metals', 'धातु एवं अधातु'],
                ['Organic Chemistry', 'कार्बनिक रसायन'],
            ],

            'Biology' => [
                ['Cell', 'कोशिका'],
                ['Human Body', 'मानव शरीर'],
                ['Nutrition', 'पोषण'],
                ['Diseases', 'रोग'],
                ['Plant Biology', 'वनस्पति विज्ञान'],
                ['Genetics', 'आनुवंशिकी'],
                ['Ecology', 'पारिस्थितिकी'],
            ],

            'Computer Knowledge' => [
                ['Computer Fundamentals', 'कंप्यूटर मूलभूत'],
                ['Hardware', 'हार्डवेयर'],
                ['Software', 'सॉफ्टवेयर'],
                ['Operating System', 'ऑपरेटिंग सिस्टम'],
                ['MS Office', 'एमएस ऑफिस'],
                ['Internet', 'इंटरनेट'],
                ['Networking', 'नेटवर्किंग'],
                ['Cyber Security', 'साइबर सुरक्षा'],
                ['Database', 'डेटाबेस'],
                ['Computer Abbreviations', 'कंप्यूटर संक्षिप्त रूप'],
            ],

            'Environment & Ecology' => [
                ['Ecosystem', 'पारिस्थितिकी तंत्र'],
                ['Biodiversity', 'जैव विविधता'],
                ['Pollution', 'प्रदूषण'],
                ['Climate Change', 'जलवायु परिवर्तन'],
                ['Protected Areas', 'संरक्षित क्षेत्र'],
                ['Environmental Conventions', 'पर्यावरण सम्मेलन'],
            ],

            'Static GK' => [
                ['Countries Capitals Currencies', 'देश राजधानी मुद्रा'],
                ['National Symbols', 'राष्ट्रीय प्रतीक'],
                ['Dams', 'बाँध'],
                ['National Parks', 'राष्ट्रीय उद्यान'],
                ['Important Institutions', 'महत्वपूर्ण संस्थान'],
                ['Classical Dances', 'शास्त्रीय नृत्य'],
                ['Festivals', 'त्योहार'],
                ['Monuments', 'स्मारक'],
            ],
        ];

        foreach ($taxonomy as $subjectName => $topics) {

            $subject = Subject::where(
                'name',
                $subjectName
            )->first();

            if (!$subject) {
                continue;
            }

            foreach ($topics as $index => [$name, $nameHi]) {

                Topic::updateOrCreate(
                    [
                        'subject_id' => $subject->id,
                        'slug' => Str::slug($name),
                    ],
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
}
