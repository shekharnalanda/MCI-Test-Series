<?php

use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Admin\AdmissionController as AdminAdmissionController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\CurrentAffairsController as AdminCurrentAffairsController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\TestController as StudentTestController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login'])
        ->middleware('throttle:10,1')
        ->name('login.submit');

    Route::get('/admission', [AdmissionController::class, 'create'])
        ->name('admission.create');

    Route::post('/admission/send-otp', [AdmissionController::class, 'sendOtp'])
        ->middleware('throttle:3,10')
        ->name('admission.send-otp');

    Route::post('/admission/verify-otp', [AdmissionController::class, 'verifyOtp'])
        ->middleware('throttle:10,10')
        ->name('admission.verify-otp');

    Route::post('/admission', [AdmissionController::class, 'store'])
        ->name('admission.store');

    Route::get(
        '/admission/success/{application}',
        [AdmissionController::class, 'success']
    )->name('admission.success');
});

Route::post('/logout', [AuthController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

Route::middleware(['auth', 'role:student'])
    ->prefix('student')
    ->name('student.')
    ->group(function () {
        Route::get('/dashboard', [StudentDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get('/tests', [StudentTestController::class, 'index'])
            ->name('tests.index');

        Route::post('/tests/{test}/start', [StudentTestController::class, 'start'])
            ->name('tests.start');

        Route::get('/attempts/{attempt}', [StudentTestController::class, 'show'])
            ->name('attempts.show');

        Route::post('/attempts/{attempt}/answer', [StudentTestController::class, 'answer'])
            ->name('attempts.answer');

        Route::post('/attempts/{attempt}/submit', [StudentTestController::class, 'submit'])
            ->name('attempts.submit');

        Route::get('/attempts/{attempt}/result', [StudentTestController::class, 'result'])
            ->name('attempts.result');
    });

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/dashboard', [AdminDashboardController::class, 'index'])
            ->name('dashboard');

        Route::get(
            '/current-affairs',
            [AdminCurrentAffairsController::class, 'index']
        )->name('current-affairs.index');

        Route::get(
            '/current-affairs/{currentAffair}',
            [AdminCurrentAffairsController::class, 'show']
        )->name('current-affairs.show');

        Route::post(
            '/current-affairs/{currentAffair}/approve',
            [AdminCurrentAffairsController::class, 'approve']
        )->name('current-affairs.approve');

        Route::post(
            '/current-affairs/{currentAffair}/reject',
            [AdminCurrentAffairsController::class, 'reject']
        )->name('current-affairs.reject');

        Route::get('/admissions', [AdminAdmissionController::class, 'index'])
            ->name('admissions.index');

        Route::get('/admissions/{application}', [AdminAdmissionController::class, 'show'])
            ->name('admissions.show');

        Route::post(
            '/admissions/{application}/approve',
            [AdminAdmissionController::class, 'approve']
        )->name('admissions.approve');

        Route::post(
            '/admissions/{application}/reject',
            [AdminAdmissionController::class, 'reject']
        )->name('admissions.reject');
    });


Route::middleware('auth')->group(function () {
    Route::get('/account/password', [\App\Http\Controllers\PasswordController::class, 'edit'])
        ->name('password.edit');
    Route::put('/account/password', [\App\Http\Controllers\PasswordController::class, 'update'])
        ->name('password.update');
});


Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/operations', [\App\Http\Controllers\Admin\OperationsController::class, 'index'])->name('operations.index');
    Route::post('/operations/students', [\App\Http\Controllers\Admin\OperationsController::class, 'storeStudent'])->name('operations.students.store');
    Route::patch('/operations/students/{user}/toggle', [\App\Http\Controllers\Admin\OperationsController::class, 'toggleStudent'])->name('operations.students.toggle');
    Route::post('/operations/students/{profile}/package', [\App\Http\Controllers\Admin\OperationsController::class, 'assignPackage'])->name('operations.students.package');
    Route::post('/operations/packages', [\App\Http\Controllers\Admin\OperationsController::class, 'storePackage'])->name('operations.packages.store');
    Route::patch('/operations/packages/{package}/toggle', [\App\Http\Controllers\Admin\OperationsController::class, 'togglePackage'])->name('operations.packages.toggle');
    Route::post('/operations/exams', [\App\Http\Controllers\Admin\OperationsController::class, 'storeExam'])->name('operations.exams.store');
    Route::patch('/operations/exams/{exam}/toggle', [\App\Http\Controllers\Admin\OperationsController::class, 'toggleExam'])->name('operations.exams.toggle');
});


Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/content', [\App\Http\Controllers\Admin\ContentController::class, 'index'])->name('content.index');
    Route::post('/content/subjects', [\App\Http\Controllers\Admin\ContentController::class, 'storeSubject'])->name('content.subjects.store');
    Route::post('/content/topics', [\App\Http\Controllers\Admin\ContentController::class, 'storeTopic'])->name('content.topics.store');
    Route::post('/content/questions', [\App\Http\Controllers\Admin\ContentController::class, 'storeQuestion'])->name('content.questions.store');
    Route::post('/content/generate', [\App\Http\Controllers\Admin\ContentController::class, 'generate'])->name('content.generate');
    Route::patch('/content/tests/{test}/toggle', [\App\Http\Controllers\Admin\ContentController::class, 'toggleTest'])->name('content.tests.toggle');
    Route::patch('/content/series/{series}/toggle', [\App\Http\Controllers\Admin\ContentController::class, 'toggleSeries'])->name('content.series.toggle');
});
