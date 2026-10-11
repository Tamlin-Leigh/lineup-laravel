<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A private chat for each accepted booking, between the event's owner and the band or venue
     * that accepted. The two participants are stored on the conversation (rather than looked up
     * through the booking) so listing someone's chats and counting unread messages stay simple.
     */
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('booking_request_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('recipient_id')->constrained('users')->cascadeOnDelete();
            // When each side last opened the chat (unread counts) and was last emailed about it.
            $table->dateTime('owner_read_at')->nullable();
            $table->dateTime('recipient_read_at')->nullable();
            $table->dateTime('owner_alerted_at')->nullable();
            $table->dateTime('recipient_alerted_at')->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->timestamps();

            $table->index(['owner_id', 'last_message_at']);
            $table->index(['recipient_id', 'last_message_at']);
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('conversation_id')->constrained()->cascadeOnDelete();
            // Null for notes BandLink posts itself, e.g. "The Doloress accepted".
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['conversation_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('conversations');
    }
};
