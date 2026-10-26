<?php

use App\Models\User;

test('guests visiting the home page are sent to log in', function () {
    $this->get(route('home'))->assertRedirect(route('login'));
});

test('signed in users visiting the home page are sent to their dashboard', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('home'))
        ->assertRedirect(route('dashboard'));
});

test('the log in page uses the LineUp layout', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee('Welcome Back')
        ->assertSee('SignUpBanner.jpg');
});
