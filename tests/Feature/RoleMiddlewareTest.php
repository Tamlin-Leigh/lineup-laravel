<?php

use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    Route::middleware(['web', 'auth', 'role:band,venue'])->get('/_test/bands-and-venues', fn () => 'ok');
});

test('users with a listed role are let through', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/_test/bands-and-venues')
        ->assertOk()
        ->assertSee('ok');
})->with(['band', 'venue']);

test('users without a listed role are forbidden', function (string $state) {
    $this->actingAs(User::factory()->{$state}()->create())
        ->get('/_test/bands-and-venues')
        ->assertForbidden();
})->with(['organiser', 'admin']);
