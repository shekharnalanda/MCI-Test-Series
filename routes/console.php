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
