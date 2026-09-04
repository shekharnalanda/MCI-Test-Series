<?php

use Illuminate\Support\Facades\Schedule;

/*
|--------------------------------------------------------------------------
| MCI Test Series Automated Maintenance
|--------------------------------------------------------------------------
|
| Server cron only needs to run:
|
| php artisan schedule:run
|
| every minute. Laravel controls actual execution frequency below.
|
*/

Schedule::command(
    'mci:current-affairs-maintain'
)
    ->dailyAt('02:15')
    ->withoutOverlapping();

Schedule::command(
    'mci:current-affairs-fetch --limit=100'
)
    ->hourlyAt(10)
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(45);

Schedule::command(
    'mci:current-affairs-tests daily --questions=10'
)
    ->dailyAt('06:00')
    ->withoutOverlapping();

Schedule::command(
    'mci:current-affairs-tests weekly --questions=25'
)
    ->weeklyOn(1, '06:15')
    ->withoutOverlapping();

Schedule::command(
    'mci:current-affairs-tests monthly --questions=50'
)
    ->monthlyOn(1, '06:30')
    ->withoutOverlapping();

Schedule::command(
    'question-bank:check-sources --fail-on-error'
)
    ->dailyAt('01:45')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30);

Schedule::command(
    'question-bank:audit-sources --strict'
)
    ->weeklyOn(1, '02:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30);


Schedule::command(
    'question-bank:prune-source-checks --days=90'
)
    ->weeklyOn(0, '02:20')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30);


Schedule::command(
    'question-bank:source-health-report --hours=24 --fail-on-unhealthy'
)
    ->dailyAt('02:10')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30);


Schedule::command(
    'question-bank:recover-imports --stale-minutes=30'
)
    ->everyFifteenMinutes()
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(20);


Schedule::command(
    'question-bank:audit-import-quality --hours=24 --strict'
)
    ->dailyAt('02:25')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30);


Schedule::command(
    'question-bank:audit-bilingual --strict'
)
    ->dailyAt('02:35')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(30);


Schedule::command(
    'test-series:generate --per-exam=1 --questions=25 --difficulty=mixed --type=practice'
)
    ->dailyAt('03:00')
    ->timezone('Asia/Kolkata')
    ->withoutOverlapping(45);

Schedule::command('question-bank:retry-imports --limit=10 --chunk=500 --strict')
    ->hourlyAt(35)
    ->withoutOverlapping(55)
    ->appendOutputTo(storage_path('logs/question-import-retry.log'));
