<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database with one log-in-able account per role.
     * Every account's password is "password".
     */
    public function run(): void
    {
        User::factory()->venue()->create(['name' => 'Demo Venue', 'email' => 'venue@example.com']);
        User::factory()->band()->create(['name' => 'Demo Band', 'email' => 'band@example.com']);
        User::factory()->organiser()->create(['name' => 'Demo Organiser', 'email' => 'organiser@example.com']);
        User::factory()->admin()->create(['name' => 'Admin', 'email' => 'admin@example.com']);
    }
}
