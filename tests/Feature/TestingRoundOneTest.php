<?php

use App\Livewire\Auth\Register;
use App\Models\User;
use Illuminate\Support\Facades\Route;
use Livewire\Livewire;

/*
 * Regressions for the first round of manual testing (branch phase-1-testing-1).
 */

test('the auth banner image is served by the app, not left to the Vite dev server', function () {
    $this->get(route('login'))
        ->assertOk()
        ->assertSee("background-image: url('".asset('images/SignUpBanner.jpg')."')", false);
});

test('picking a role only updates the browser until Continue is pressed', function () {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->assertSeeHtml('$wire.$set(&#039;role&#039;, &#039;venue&#039;, false)')
        ->assertDontSeeHtml("wire:click=\"\$set('role'");
});

test('the chosen role is still shown as selected after a server round trip', function () {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->set(['role' => 'artist', 'form.name' => ''])
        ->call('next')
        ->assertHasErrors('form.name')
        ->assertSeeHtml('aria-pressed="true"');
});

test('auth screens use the large shared submit button', function () {
    $this->get(route('login'))->assertSee('h-12 w-full shrink-0 text-base! font-bold', false);
    $this->get(route('register'))->assertSee('h-12 w-full shrink-0 text-base! font-bold', false);
});

test('the app is always light: no dark class and no appearance switcher', function () {
    $this->actingAs(User::factory()->artist()->create())
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('<html lang="en" class="dark">', false)
        ->assertDontSee('flux.appearance', false);

    expect(Route::has('appearance.edit'))->toBeFalse();
});

test('phone numbers with letters are rejected instead of quietly cleaned up', function (string $field, string $role) {
    $component = Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->set(['form.name' => 'Sam', 'form.phone' => '0821234567', 'role' => $role])
        ->call('next')
        ->assertHasNoErrors();

    $component->set($field, '0610430484ww')->call('next')->assertHasErrors([$field => 'regex']);
})->with([
    'manager phone' => ['form.manager_phone', 'venue'],
    'contact number' => ['form.contact_number', 'organiser'],
]);

test('your own phone number cannot contain letters or a second plus', function (string $phone) {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->set(['form.name' => 'Sam', 'form.phone' => $phone, 'role' => 'artist'])
        ->call('next')
        // This step reports role and phone errors together, so check the field rather than the rule.
        ->assertHasErrors(['form.phone'])
        ->assertSet('step', 1);
})->with(['082abc4567', '++27821234567', '+27+821234567']);

test('common phone formatting is still accepted', function () {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->set(['form.name' => 'Sam', 'form.phone' => '+27 (82) 123-4567', 'role' => 'artist'])
        ->call('next')
        ->assertHasNoErrors()
        ->assertSet('form.phone', '+27821234567');
});

test('phone fields only accept digits and a leading plus while typing', function () {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->assertSeeHtml('inputmode="tel"')
        ->assertSeeHtml("replace(/[^\d+]/g, '')");
});

test('emails need a proper domain', function (string $email) {
    Livewire::test(Register::class)
        ->set(['email' => $email, 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->assertHasErrors(['email' => 'email']);
})->with(['ryan@gmail', 'ryan@@gmail.com', 'ryan gmail.com', 'ryan@gmail..com']);

test('manager and contact emails need a proper domain too', function (string $field, string $role) {
    Livewire::test(Register::class)
        ->set(['email' => 'sam@example.com', 'password' => 'password', 'password_confirmation' => 'password'])
        ->call('next')
        ->set(['form.name' => 'Sam', 'form.phone' => '0821234567', 'role' => $role])
        ->call('next')
        ->set($field, 'ryan@gmail')
        ->call('next')
        ->assertHasErrors([$field => 'email']);
})->with([
    'manager email' => ['form.manager_email', 'artist'],
    'contact email' => ['form.contact_email', 'organiser'],
]);
