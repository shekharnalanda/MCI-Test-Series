<?php

use App\Http\Controllers\Admin\LibraryPracticeReportController;
use App\Http\Controllers\LibraryPracticeController;
use App\Http\Middleware\EnsureLibraryPracticeSession;
use Illuminate\Support\Facades\Route;

Route::middleware('web')->prefix('library-practice')->name('library-practice.')->group(function () {
    Route::get('/enter', [LibraryPracticeController::class, 'enter'])->middleware('throttle:20,1')->name('enter');
    Route::middleware(EnsureLibraryPracticeSession::class)->group(function () {
        Route::get('/', [LibraryPracticeController::class, 'index'])->name('index');
        Route::post('/tests/{test}/select', [LibraryPracticeController::class, 'select'])->middleware('throttle:30,1')->name('select');
        Route::delete('/tests/{test}/select', [LibraryPracticeController::class, 'unselect'])->middleware('throttle:30,1')->name('unselect');
        Route::post('/tests/{test}/start', [LibraryPracticeController::class, 'start'])->middleware('throttle:20,1')->name('start');
        Route::get('/attempts/{attempt}', [LibraryPracticeController::class, 'show'])->name('attempts.show');
        Route::post('/attempts/{attempt}/answer', [LibraryPracticeController::class, 'answer'])->name('attempts.answer');
        Route::post('/attempts/{attempt}/submit', [LibraryPracticeController::class, 'submit'])->name('attempts.submit');
        Route::get('/attempts/{attempt}/result', [LibraryPracticeController::class, 'result'])->name('attempts.result');
        Route::post('/logout', [LibraryPracticeController::class, 'logout'])->name('logout');
    });
});

Route::middleware(['web', 'auth', 'role:admin'])->get('/admin/library-practice', LibraryPracticeReportController::class)->name('admin.library-practice');
