<?php

use App\Console\Commands\MatchOrdersCommand;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command(MatchOrdersCommand::class, ['--all' => true, '--max' => 100])
    ->everyMinute()
    ->withoutOverlapping()
    ->runInBackground();

Schedule::command('queue:monitor redis:high --max=1000')
    ->everyFiveMinutes()
    ->runInBackground();

Schedule::command('queue:restart')
    ->dailyAt('04:00');
