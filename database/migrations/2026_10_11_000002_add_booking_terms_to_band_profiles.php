<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A band's usual terms, shown to hosts when they pick bands: a set booking fee and/or whether
     * they'll play for a share of the door or ticket sales. Both optional for now (pending market
     * research on how bands are paid).
     */
    public function up(): void
    {
        Schema::table('band_profiles', function (Blueprint $table) {
            $table->unsignedInteger('booking_fee')->nullable()->after('bio');
            $table->boolean('accepts_door_split')->default(false)->after('booking_fee');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('band_profiles', function (Blueprint $table) {
            $table->dropColumn(['booking_fee', 'accepts_door_split']);
        });
    }
};
