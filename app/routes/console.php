<?php

use App\Models\Setting;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// التذكير الدوري التلقائي - يومياً في الساعة المحدّدة
Schedule::command('reminders:dispatch')
    ->dailyAt(
        sprintf(
            '%02d:%02d',
            (int) Setting::get('trigger_hour', 9),
            (int) Setting::get('trigger_minute', 5)
        )
    )
    ->timezone(config('app.timezone'))
    ->onOneServer();
