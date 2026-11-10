<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('the migration carries existing band data over to artists and can be reversed', function () {
    $artist = User::factory()->artist()->create();
    $profile = $artist->artistProfile;
    DB::table('links')->insert([
        'linkable_type' => 'artist_profile',
        'linkable_id' => $profile->id,
        'kind' => 'music',
        'platform' => 'bandcamp',
        'url' => 'https://example.bandcamp.com',
    ]);

    $migration = require database_path('migrations/2026_11_10_000001_rename_bands_to_artists.php');

    $migration->down();

    expect(Schema::hasTable('band_profiles'))->toBeTrue()
        ->and(Schema::hasTable('artist_profiles'))->toBeFalse()
        ->and(Schema::hasColumn('band_profiles', 'band_name'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'band_slots'))->toBeTrue()
        ->and(DB::table('users')->where('id', $artist->id)->value('role'))->toBe('band')
        ->and(DB::table('links')->value('linkable_type'))->toBe('band_profile');

    $migration->up();

    expect(Schema::hasTable('artist_profiles'))->toBeTrue()
        ->and(Schema::hasTable('band_profiles'))->toBeFalse()
        ->and(Schema::hasColumn('artist_profiles', 'artist_name'))->toBeTrue()
        ->and(Schema::hasColumn('events', 'artist_slots'))->toBeTrue()
        ->and(DB::table('users')->where('id', $artist->id)->value('role'))->toBe('artist')
        ->and(DB::table('links')->value('linkable_type'))->toBe('artist_profile')
        ->and(DB::table('links')->value('platform'))->toBe('bandcamp')
        ->and($artist->fresh()->artistProfile->is($profile))->toBeTrue();
});
