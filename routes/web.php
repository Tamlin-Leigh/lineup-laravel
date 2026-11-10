<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\OpenBookingChatController;
use App\Http\Controllers\PublicProfileController;
use App\Http\Controllers\VerifyEmailController;
use App\Livewire\Auth\Register;
use App\Livewire\Bookings\Index as BookingIndex;
use App\Livewire\Discover;
use App\Livewire\Events\Calendar;
use App\Livewire\Events\Index as EventIndex;
use App\Livewire\Events\ManageEvent;
use App\Livewire\Events\Show as EventShow;
use App\Livewire\Messages\Index as MessagesIndex;
use App\Livewire\Profile\EditProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Like the prototype, the root has no page of its own: guests log in, everyone else goes to their dashboard.
Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'))->name('home');

// Replaces Fortify's single-page registration (disabled in config/fortify.php).
Route::livewire('register', Register::class)->middleware('guest')->name('register');

// Overrides Fortify's route of the same URI and name, so expired or wrong-account links redirect
// with a message instead of a 403. The signature is checked in the controller rather than by middleware.
Route::get('email/verify/{id}/{hash}', VerifyEmailController::class)
    ->middleware(['auth', 'throttle:6,1'])
    ->name('verification.verify');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::livewire('profile', EditProfile::class)->name('profile.show');
    Route::get('profiles/{user}', PublicProfileController::class)->name('profiles.show');

    Route::middleware('role:venue,artist,organiser')->group(function () {
        Route::livewire('events', EventIndex::class)->name('events.index');
        Route::livewire('events/create', ManageEvent::class)->name('events.create');
        Route::livewire('events/{event}', EventShow::class)->whereNumber('event')->name('events.show');
        Route::livewire('events/{event}/edit', ManageEvent::class)->name('events.edit');
        Route::livewire('calendar', Calendar::class)->name('calendar');
        Route::livewire('discover', Discover::class)->name('discover');
        Route::livewire('bookings', BookingIndex::class)->name('bookings.index');
        Route::get('bookings/{booking}/chat', OpenBookingChatController::class)->name('bookings.chat');
        Route::livewire('messages', MessagesIndex::class)->name('messages.index');
        Route::livewire('messages/{conversation}', MessagesIndex::class)->name('messages.show');
    });

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';
