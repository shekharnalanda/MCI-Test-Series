<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AdmissionApplication;
use App\Models\Exam;
use App\Models\Question;
use App\Models\StudentProfile;
use App\Models\Test;
use App\Models\TestAttempt;

class DashboardController extends Controller
{
    public function index()
    {
        return view('admin.dashboard', [
            'students' => StudentProfile::count(),
            'pendingAdmissions' => AdmissionApplication::where('status', 'submitted')->count(),
            'exams' => Exam::count(),
            'questions' => Question::count(),
            'tests' => Test::count(),
            'attempts' => TestAttempt::count(),
        ]);
    }
}
