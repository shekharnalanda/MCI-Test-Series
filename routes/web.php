<?php

use App\Http\Controllers\Admin\AdmissionController as AdminAdmissionController;
use App\Http\Controllers\Admin\ContentController;
use App\Http\Controllers\Admin\CurrentAffairsController as AdminCurrentAffairsController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\OperationsController;
use App\Http\Controllers\AdminPasswordResetController;
use App\Http\Controllers\AdmissionController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PasswordController;
use App\Http\Controllers\PlanController;
use App\Http\Controllers\Student\DashboardController as StudentDashboardController;
use App\Http\Controllers\Student\TestController as StudentTestController;
use Illuminate\Support\Facades\Route;

require __DIR__.'/mci-account-recovery.php';

Route::get('/', HomeController::class)->name('home');
Route::get('/plans', PlanController::class)->name('plans.index');

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

Route::get('/free-demo', [DemoController::class, 'create'])
    ->name('demo.create');
Route::post('/free-demo/send-otp', [DemoController::class, 'sendOtp'])
    ->middleware('throttle:5,2')
    ->name('demo.send-otp');
Route::post('/free-demo/verify-otp', [DemoController::class, 'verifyOtp'])
    ->middleware('throttle:10,10')
    ->name('demo.verify-otp');
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

        Route::post('/tests/{test}/select', [StudentTestController::class, 'select'])
            ->name('tests.select');
        Route::delete('/tests/{test}/select', [StudentTestController::class, 'unselect'])
            ->name('tests.unselect');

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
    Route::get('/account/password', [PasswordController::class, 'edit'])
        ->name('password.edit');
    Route::put('/account/password', [PasswordController::class, 'update'])
        ->name('password.update');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/operations', [OperationsController::class, 'index'])->name('operations.index');
    Route::post('/operations/students', [OperationsController::class, 'storeStudent'])->name('operations.students.store');
    Route::patch('/operations/students/{user}/toggle', [OperationsController::class, 'toggleStudent'])->name('operations.students.toggle');
    Route::post('/operations/students/{profile}/package', [OperationsController::class, 'assignPackage'])->name('operations.students.package');
    Route::post('/operations/packages', [OperationsController::class, 'storePackage'])->name('operations.packages.store');
    Route::put('/operations/packages/{package}', [OperationsController::class, 'updatePackage'])->name('operations.packages.update');
    Route::patch('/operations/packages/{package}/toggle', [OperationsController::class, 'togglePackage'])->name('operations.packages.toggle');
    Route::post('/operations/exams', [OperationsController::class, 'storeExam'])->name('operations.exams.store');
    Route::patch('/operations/exams/{exam}/toggle', [OperationsController::class, 'toggleExam'])->name('operations.exams.toggle');
});

Route::middleware(['auth', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/content', [ContentController::class, 'index'])->name('content.index');
    Route::post('/content/subjects', [ContentController::class, 'storeSubject'])->name('content.subjects.store');
    Route::post('/content/topics', [ContentController::class, 'storeTopic'])->name('content.topics.store');
    Route::post('/content/questions', [ContentController::class, 'storeQuestion'])->name('content.questions.store');
    Route::post('/content/generate', [ContentController::class, 'generate'])->name('content.generate');
    Route::patch('/content/tests/{test}/toggle', [ContentController::class, 'toggleTest'])->name('content.tests.toggle');
    Route::put('/content/tests/{test}/answer-visibility', [ContentController::class, 'updateAnswerVisibility'])->name('content.tests.answer-visibility');
    Route::patch('/content/series/{series}/toggle', [ContentController::class, 'toggleSeries'])->name('content.series.toggle');
});

Route::middleware(['auth', 'role:admin'])->group(function () {
    Route::get('/admin/change-password', [App\Http\Controllers\Admin\PasswordController::class, 'edit'])
        ->name('admin.password.edit');
    Route::put('/admin/change-password', [App\Http\Controllers\Admin\PasswordController::class, 'update'])
        ->name('admin.password.update');
});

Route::middleware('guest')->group(function () {
    Route::get('/admin/forgot-password', [AdminPasswordResetController::class, 'create'])->name('admin.password.forgot');
    Route::post('/admin/forgot-password/send-otp', [AdminPasswordResetController::class, 'sendOtp'])->middleware('throttle:3,10')->name('admin.password.forgot.send');
    Route::post('/admin/forgot-password/verify-otp', [AdminPasswordResetController::class, 'verifyOtp'])->middleware('throttle:10,10')->name('admin.password.forgot.verify');
    Route::put('/admin/forgot-password/reset', [AdminPasswordResetController::class, 'reset'])->middleware('throttle:5,10')->name('admin.password.forgot.reset');
});
