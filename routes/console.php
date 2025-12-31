<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Run daily at 1 AM to update overdue installments
Schedule::call(function () {
    $count = \App\Models\Installment::where('status', 'pending')
        ->where('due_date', '<', now()->format('Y-m-d'))
        ->update(['status' => 'overdue']);

    \Log::info("Updated {$count} overdue installments");
})->dailyAt('01:00')->name('update-overdue-installments');

// Clean old backups daily at 01:00 AM
Schedule::command('backup:clean')->daily()->at('01:00');

// Run full backup (database + files) daily at 01:30 AM
Schedule::command('backup:run')->daily()->at('01:30');
