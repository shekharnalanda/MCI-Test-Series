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
