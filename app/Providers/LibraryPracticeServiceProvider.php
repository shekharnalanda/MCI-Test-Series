<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class LibraryPracticeServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->loadRoutesFrom(base_path('routes/library-practice.php'));
    }
}
