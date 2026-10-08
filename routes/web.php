<?php

use App\Livewire\Auth\Register;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

// Replaces Fortify's single-page registration (disabled in config/fortify.php).
Route::livewire('register', Register::class)->middleware('guest')->name('register');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
});

require __DIR__.'/settings.php';
