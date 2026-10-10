<?php

use App\Actions\ExpireBookingRequests;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Artisan::command('bookings:expire', function (ExpireBookingRequests $expire) {
    $this->info(trans_choice(':count booking request expired.|:count booking requests expired.', $expire->handle()));
})->purpose('Expire booking requests that were not answered in time');

Schedule::command('bookings:expire')->everyFifteenMinutes()->withoutOverlapping();
