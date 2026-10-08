<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('venue_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('manager_name');
            $table->string('manager_phone', 20);
            $table->string('manager_email');
            $table->unsignedInteger('capacity');
            $table->string('location');
            $table->unsignedInteger('min_door_charge');
            $table->string('sound_engineer');
            $table->string('sound_engineer_name')->nullable();
            $table->boolean('has_own_equipment');
            $table->text('equipment_details')->nullable();
            $table->json('event_days');
            $table->timestamps();
        });

        Schema::create('band_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('manager_name');
            $table->string('manager_phone', 20);
            $table->string('manager_email');
            $table->string('band_name');
            $table->unsignedSmallInteger('member_count');
            $table->text('member_names');
            $table->string('genre');
            $table->text('bio')->nullable();
            $table->timestamps();
        });

        Schema::create('organiser_profiles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('org_name');
            $table->string('contact_number', 20);
            $table->string('contact_email');
            $table->timestamps();
        });

        Schema::create('links', function (Blueprint $table) {
            $table->id();
            $table->morphs('linkable');
            $table->string('kind');
            $table->string('platform');
            $table->string('url');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('links');
        Schema::dropIfExists('organiser_profiles');
        Schema::dropIfExists('band_profiles');
        Schema::dropIfExists('venue_profiles');
    }
};
