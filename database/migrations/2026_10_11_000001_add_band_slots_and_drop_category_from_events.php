<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Events say how many bands will play (the host band counts as one), so only that many can be
     * booked. The category was never used for anything beyond colours, so it goes.
     */
    public function up(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->unsignedSmallInteger('band_slots')->default(1)->after('price');
        });

        // Existing events get room for the bands already on them or still deciding.
        DB::table('events')->orderBy('id')->each(function (object $event) {
            $bands = DB::table('booking_requests')
                ->join('users', 'users.id', '=', 'booking_requests.recipient_id')
                ->where('booking_requests.event_id', $event->id)
                ->where('users.role', 'band')
                ->whereIn('booking_requests.status', ['requested', 'accepted'])
                ->count();
            $hostIsBand = DB::table('users')->where('id', $event->user_id)->value('role') === 'band';

            DB::table('events')->where('id', $event->id)->update(['band_slots' => max(1, $bands + ($hostIsBand ? 1 : 0))]);
        });

        Schema::table('events', function (Blueprint $table) {
            $table->dropColumn('category');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->string('category')->default('other')->after('description');
            $table->dropColumn('band_slots');
        });
    }
};
