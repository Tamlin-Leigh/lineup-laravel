<?php

use App\Actions\FetchGoogleSheet;
use App\Actions\ImportAvailability;
use App\Livewire\Events\AvailabilityImport;
use App\Livewire\Events\Calendar;
use App\Models\Availability;
use App\Models\BookingRequest;
use App\Models\Event;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

beforeEach(function () {
    // Thursday 8 October 2026.
    Carbon::setTestNow('2026-10-08 09:00');
    $this->action = app(ImportAvailability::class);
    $this->user = User::factory()->band()->create();
});

afterEach(function () {
    Carbon::setTestNow();
});

/**
 * @return list<string>
 */
function importedFreeDates(User $user): array
{
    return $user->availabilities()->orderBy('date')->pluck('date')->map->format('Y-m-d')->all();
}

function csvUpload(string $contents, string $name = 'free-days.csv'): UploadedFile
{
    return UploadedFile::fake()->createWithContent($name, $contents);
}

test('dates are read in several formats, with a header, notes and duplicates', function () {
    $parsed = $this->action->parse("\xEF\xBB\xBFDate,Notes\r\n2026-10-16,Pub gig\r\n17/10/2026\r\n\r\n,23-10-2026\r\n24 Oct 2026\r\nSat 31 Oct 2026\r\n16/10/26\r\n2026-10-16\r\n");

    expect($parsed['dates'])->toBe(['2026-10-16', '2026-10-17', '2026-10-23', '2026-10-24', '2026-10-31'])
        ->and($parsed['invalid'])->toBe([])
        ->and($parsed['tooLong'])->toBeFalse();
});

test('rows that are not real dates are reported with their line numbers', function () {
    $parsed = $this->action->parse("2026-10-16\n31/02/2026\nnext friday\n10/16/2026\n");

    expect($parsed['dates'])->toBe(['2026-10-16'])
        ->and($parsed['invalid'])->toBe([
            ['line' => 2, 'value' => '31/02/2026'],
            ['line' => 3, 'value' => 'next friday'],
            ['line' => 4, 'value' => '10/16/2026'],
        ]);
});

test('files with too many rows are rejected', function () {
    $parsed = $this->action->parse(str_repeat("2026-10-16\n", ImportAvailability::MAX_ROWS + 1));

    expect($parsed['tooLong'])->toBeTrue()->and($parsed['dates'])->toBe([]);
});

test('the file replaces free days between its first and last date and leaves the rest alone', function () {
    foreach (['2026-10-09', '2026-10-17', '2026-10-20', '2026-11-06'] as $date) {
        Availability::factory()->for($this->user)->create(['date' => $date]);
    }

    $preview = $this->action->preview($this->user, ['2026-10-16', '2026-10-17', '2026-10-24']);

    expect($preview->from->toDateString())->toBe('2026-10-16')
        ->and($preview->to->toDateString())->toBe('2026-10-24')
        ->and($preview->add)->toBe(['2026-10-16', '2026-10-24'])
        ->and($preview->remove)->toBe(['2026-10-20'])
        ->and($preview->unchanged)->toBe(['2026-10-17']);

    expect($this->action->apply($this->user, ['2026-10-16', '2026-10-17', '2026-10-24']))->toBe(['removed' => 1, 'added' => 2])
        ->and(importedFreeDates($this->user))->toBe(['2026-10-09', '2026-10-16', '2026-10-17', '2026-10-24', '2026-11-06']);
});

test('past, far-off and event days are skipped', function () {
    Event::factory()->for($this->user, 'owner')->create(['starts_at' => '2026-10-17 20:00']);

    $preview = $this->action->preview($this->user, ['2026-10-01', '2026-10-16', '2026-10-17', '2029-01-01']);

    expect($preview->add)->toBe(['2026-10-16'])
        ->and($preview->busy)->toBe(['2026-10-17'])
        ->and($preview->outOfRange)->toBe(['2026-10-01', '2029-01-01']);

    $this->action->apply($this->user, ['2026-10-01', '2026-10-16', '2026-10-17', '2029-01-01']);

    expect(importedFreeDates($this->user))->toBe(['2026-10-16']);
});

test('clearing a day with a booking request waiting is flagged', function () {
    Availability::factory()->for($this->user)->create(['date' => '2026-10-20']);
    $event = Event::factory()->for(User::factory()->venue(), 'owner')->create(['title' => 'Tuesday Jam', 'starts_at' => '2026-10-20 19:00']);
    BookingRequest::factory()->for($event)->for($this->user, 'recipient')->create();

    $preview = $this->action->preview($this->user, ['2026-10-16', '2026-10-24']);

    expect($preview->remove)->toBe(['2026-10-20'])
        ->and($preview->pendingClashes)->toBe(['2026-10-20' => ['Tuesday Jam']]);
});

test('importing records when availability was last updated', function () {
    expect($this->user->availability_updated_at)->toBeNull();

    $this->action->apply($this->user, ['2026-10-16']);

    expect($this->user->fresh()->availability_updated_at->toDateTimeString())->toBe('2026-10-08 09:00:00');
});

test('uploading a file shows a preview and nothing is saved until it is confirmed', function () {
    Availability::factory()->for($this->user)->create(['date' => '2026-10-20']);

    $component = Livewire::actingAs($this->user)
        ->test(AvailabilityImport::class)
        ->set('file', csvUpload("date\n2026-10-16\n2026-10-24\nnot a date\n"))
        ->assertHasNoErrors()
        ->assertSet('dates', ['2026-10-16', '2026-10-24'])
        ->assertSee('2 days will be marked free')
        ->assertSee('1 free day will be cleared')
        ->assertSee('row 4 (&quot;not a date&quot;)', false);

    expect(importedFreeDates($this->user))->toBe(['2026-10-20']);

    $component->call('import')
        ->assertDispatched('availability-updated')
        ->assertSet('dates', []);

    expect(importedFreeDates($this->user))->toBe(['2026-10-16', '2026-10-24']);
});

test('the upload warns about booking requests on days it will clear', function () {
    Availability::factory()->for($this->user)->create(['date' => '2026-10-20']);
    $event = Event::factory()->for(User::factory()->venue(), 'owner')->create(['title' => 'Tuesday Jam', 'starts_at' => '2026-10-20 19:00']);
    BookingRequest::factory()->for($event)->for($this->user, 'recipient')->create();

    Livewire::actingAs($this->user)
        ->test(AvailabilityImport::class)
        ->set('file', csvUpload("2026-10-16\n2026-10-24\n"))
        ->assertSeeHtml('data-test="import-pending-warning"')
        ->assertSee('Tue 20 Oct 2026: Tuesday Jam');
});

test('files without any dates, or of the wrong type, are rejected', function () {
    Livewire::actingAs($this->user)
        ->test(AvailabilityImport::class)
        ->set('file', csvUpload("name\nnothing here\n"))
        ->assertHasErrors('file')
        ->assertSet('dates', [])
        ->set('file', UploadedFile::fake()->image('days.png'))
        ->assertHasErrors('file');
});

test('the example file can be downloaded', function () {
    Livewire::actingAs($this->user)
        ->test(AvailabilityImport::class)
        ->call('downloadTemplate')
        ->assertFileDownloaded('bandlink-free-days.csv', "date\n2026-10-09\n2026-10-10\n2026-10-16\n2026-10-17\n2026-10-23\n2026-10-24\n");
});

test('the calendar offers the import and asks before clearing a day with a request waiting', function () {
    Availability::factory()->for($this->user)->create(['date' => '2026-10-20']);
    $event = Event::factory()->for(User::factory()->venue(), 'owner')->create(['title' => 'Tuesday Jam', 'starts_at' => '2026-10-20 19:00']);
    BookingRequest::factory()->for($event)->for($this->user, 'recipient')->create();

    Livewire::actingAs($this->user)
        ->test(Calendar::class)
        ->set('markingAvailability', true)
        ->assertSeeHtml('data-test="import-availability-button"')
        ->assertSee('You have a booking request waiting for your reply on Tue 20 Oct (Tuesday Jam)')
        ->assertSee('1 of them has a booking request waiting for your reply.');
});

test('the calendar shows when free days were last updated and nudges when they look stale', function () {
    Availability::factory()->for($this->user)->create(['date' => '2026-10-20']);
    $this->user->forceFill(['availability_updated_at' => now()->subDays(45)])->save();

    Livewire::actingAs($this->user)
        ->test(Calendar::class)
        ->assertSee('Last updated 1 month ago')
        ->assertSeeHtml('data-test="stale-availability"');

    $this->user->forceFill(['availability_updated_at' => now()->subDays(3)])->save();

    Livewire::actingAs($this->user->fresh())
        ->test(Calendar::class)
        ->assertSee('Last updated 3 days ago')
        ->assertDontSeeHtml('data-test="stale-availability"');
});

test('profiles show when availability was last updated', function () {
    $this->user->forceFill(['availability_updated_at' => now()->subDays(2)])->save();

    $this->actingAs(User::factory()->venue()->create())
        ->get(route('profiles.show', $this->user))
        ->assertSee('Last updated 2 days ago');
});

test('google sheets links are reduced to the sheet and tab, and anything else is refused', function () {
    $fetch = app(FetchGoogleSheet::class);
    $id = '1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789_-abcd';

    expect($fetch->parseUrl("https://docs.google.com/spreadsheets/d/{$id}/edit?usp=sharing"))->toBe(['id' => $id, 'gid' => null])
        ->and($fetch->parseUrl("https://docs.google.com/spreadsheets/d/{$id}/edit#gid=12345"))->toBe(['id' => $id, 'gid' => '12345'])
        ->and($fetch->parseUrl("https://docs.google.com/spreadsheets/d/{$id}/edit?gid=7#gid=7"))->toBe(['id' => $id, 'gid' => '7'])
        ->and($fetch->parseUrl("http://docs.google.com/spreadsheets/d/{$id}/edit"))->toBeNull()
        ->and($fetch->parseUrl("https://docs.google.com.evil.test/spreadsheets/d/{$id}/edit"))->toBeNull()
        ->and($fetch->parseUrl("https://docs.google.com/document/d/{$id}/edit"))->toBeNull()
        ->and($fetch->parseUrl('not a link'))->toBeNull();
});

test('a shared google sheet is loaded into the preview', function () {
    Http::fake(['docs.google.com/*' => Http::response("Date\n2026-10-16\n17/10/2026\n", 200, ['Content-Type' => 'text/csv'])]);

    Livewire::actingAs($this->user)
        ->test(AvailabilityImport::class)
        ->set('sheetUrl', 'https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789/edit#gid=42')
        ->call('loadSheet')
        ->assertHasNoErrors()
        ->assertSet('dates', ['2026-10-16', '2026-10-17']);

    Http::assertSent(fn ($request) => $request->url() === 'https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789/export?format=csv&gid=42');
});

test('a private google sheet explains how to share it', function () {
    Http::fake(['docs.google.com/*' => Http::response('<html>Sign in</html>', 200, ['Content-Type' => 'text/html; charset=utf-8'])]);

    Livewire::actingAs($this->user)
        ->test(AvailabilityImport::class)
        ->set('sheetUrl', 'https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789/edit')
        ->call('loadSheet')
        ->assertHasErrors('sheetUrl')
        ->assertSee('Anyone with the link')
        ->assertSet('dates', []);
});

test('links that are not google sheets are never fetched', function () {
    Http::fake();

    Livewire::actingAs($this->user)
        ->test(AvailabilityImport::class)
        ->set('sheetUrl', 'https://example.com/free-days.csv')
        ->call('loadSheet')
        ->assertHasErrors('sheetUrl');

    Http::assertNothingSent();
});

test('loading google sheets is rate limited', function () {
    Http::fake(['docs.google.com/*' => Http::response("2026-10-16\n", 200, ['Content-Type' => 'text/csv'])]);
    $component = Livewire::actingAs($this->user)
        ->test(AvailabilityImport::class)
        ->set('sheetUrl', 'https://docs.google.com/spreadsheets/d/1AbCdEfGhIjKlMnOpQrStUvWxYz0123456789/edit');

    foreach (range(1, 10) as $attempt) {
        $component->call('loadSheet')->assertHasNoErrors();
    }

    $component->call('loadSheet')->assertHasErrors('sheetUrl');
    Http::assertSentCount(10);
});
