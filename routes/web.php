<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\DashboardController;
use App\Livewire\Auth\Register;
use App\Livewire\Profile\EditProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Like the prototype, the root has no page of its own: guests log in, everyone else goes to their dashboard.
Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'))->name('home');

// Replaces Fortify's single-page registration (disabled in config/fortify.php).
Route::livewire('register', Register::class)->middleware('guest')->name('register');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', DashboardController::class)->name('dashboard');
    Route::livewire('profile', EditProfile::class)->name('profile.show');

    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/', AdminDashboardController::class)->name('dashboard');
    });
});

require __DIR__.'/settings.php';
