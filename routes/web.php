<?php

use App\Livewire\Auth\Register;
use App\Livewire\Profile\EditProfile;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

// Like the prototype, the root has no page of its own: guests log in, everyone else goes to their dashboard.
Route::get('/', fn () => redirect()->route(Auth::check() ? 'dashboard' : 'login'))->name('home');

// Replaces Fortify's single-page registration (disabled in config/fortify.php).
Route::livewire('register', Register::class)->middleware('guest')->name('register');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::livewire('profile', EditProfile::class)->name('profile.show');
});

require __DIR__.'/settings.php';
