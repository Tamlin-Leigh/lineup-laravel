<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The product now calls bands "artists": the profile table and its columns, the event slot
     * count, and the values stored for the role and the polymorphic link owner.
     */
    public function up(): void
    {
        $this->rename('band', 'artist');
    }

    public function down(): void
    {
        $this->rename('artist', 'band');
    }

    private function rename(string $from, string $to): void
    {
        // The key and index names include the table name, so they're dropped first and recreated
        // once the table has its new name.
        Schema::table("{$from}_profiles", function (Blueprint $table) {
            $table->dropForeign(['user_id']);
            $table->dropUnique(['user_id']);
        });

        Schema::rename("{$from}_profiles", "{$to}_profiles");

        Schema::table("{$to}_profiles", function (Blueprint $table) use ($from, $to) {
            $table->renameColumn("{$from}_name", "{$to}_name");
            $table->unique('user_id');
            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
        });

        Schema::table('events', function (Blueprint $table) use ($from, $to) {
            $table->renameColumn("{$from}_slots", "{$to}_slots");
        });

        DB::table('users')->where('role', $from)->update(['role' => $to]);
        DB::table('links')->where('linkable_type', "{$from}_profile")->update(['linkable_type' => "{$to}_profile"]);
    }
};
