<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Venues and artists say which region they're in, and events which region to book artists from.
     * Region is nullable because existing accounts and events don't have one yet; a venue's stage
     * size (in metres) is optional.
     */
    public function up(): void
    {
        Schema::table('venue_profiles', function (Blueprint $table) {
            $table->string('region')->nullable()->index()->after('location');
            $table->decimal('stage_width', 4, 1)->nullable()->after('capacity');
            $table->decimal('stage_depth', 4, 1)->nullable()->after('stage_width');
        });

        Schema::table('artist_profiles', function (Blueprint $table) {
            $table->string('region')->nullable()->index()->after('genre');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->string('region')->nullable()->after('location');
        });
    }

    public function down(): void
    {
        Schema::table('venue_profiles', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropColumn(['region', 'stage_width', 'stage_depth']);
        });

        Schema::table('artist_profiles', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropColumn('region');
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('region');
        });
    }
};
