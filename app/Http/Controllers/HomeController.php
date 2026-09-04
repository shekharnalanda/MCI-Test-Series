<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Test;
use App\Models\Topic;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(): View
    {
        $featuredExams = Exam::query()->with('category')->where('is_active', true)
            ->orderByDesc('is_featured')->orderBy('name')->limit(12)->get();

        $demoTests = Test::query()->with('exam')->where('is_active', true)
            ->where('is_demo', true)->latest('id')->limit(3)->get();

        $stats = [
            'exams' => Exam::where('is_active', true)->count(),
            'subjects' => Subject::count(),
            'topics' => Topic::count(),
            'questions' => Question::where('is_published', true)->count(),
        ];

        return view('home', compact('featuredExams', 'demoTests', 'stats'));
    }
}
