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
        Schema::create('booking_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')->constrained()->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->text('message')->nullable();
            $table->dateTime('expires_at');
            $table->dateTime('responded_at')->nullable();
            $table->timestamps();

            // Each account is asked once per event.
            $table->unique(['event_id', 'recipient_id']);
            // The Bookings page lists the requests an account has received by status.
            $table->index(['recipient_id', 'status']);
            // The scheduled expiry finds unanswered requests past their deadline.
            $table->index(['status', 'expires_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('booking_requests');
    }
};
