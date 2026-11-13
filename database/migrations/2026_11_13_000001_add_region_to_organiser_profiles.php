<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Organisers say which region they're based in too, like venues and artists. Nullable because
     * existing organisers don't have one yet.
     */
    public function up(): void
    {
        Schema::table('organiser_profiles', function (Blueprint $table) {
            $table->string('region')->nullable()->index()->after('contact_email');
        });
    }

    public function down(): void
    {
        Schema::table('organiser_profiles', function (Blueprint $table) {
            $table->dropIndex(['region']);
            $table->dropColumn('region');
        });
    }
};
