<?php

use App\Actions\AnswerBookingRequest;
use App\Actions\CancelBookings;
use App\Actions\SendMessage;
use App\Enums\BookingStatus;
use App\Enums\EventStatus;
use App\Events\MessageSent;
use App\Jobs\AlertUnreadMessages;
use App\Livewire\Messages\Index;
use App\Livewire\Messages\SidebarLink;
use App\Models\BookingRequest;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use App\Notifications\UnreadMessages;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Event as EventFacade;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Livewire;

beforeEach(function () {
    Carbon::setTestNow('2026-10-08 09:00');
    Notification::fake();
});

afterEach(function () {
    Carbon::setTestNow();
});

function chat(): Conversation
{
    return Conversation::factory()->create();
}

test('accepting a booking opens a private chat with a note from LineUp', function () {
    $booking = BookingRequest::factory()->create();

    app(AnswerBookingRequest::class)->accept($booking);

    $conversation = Conversation::where('booking_request_id', $booking->id)->sole();

    expect($conversation->owner_id)->toBe($booking->event->user_id)
        ->and($conversation->recipient_id)->toBe($booking->recipient_id)
        ->and($conversation->messages()->sole()->isSystem())->toBeTrue()
        ->and($conversation->messages()->sole()->body)->toContain('accepted the booking request')
        // The band accepted, so only the host has something new to read.
        ->and($booking->event->owner->unreadMessagesCount())->toBe(1)
        ->and($booking->recipient->unreadMessagesCount())->toBe(0);
});

test('only the two people on the booking can open its chat', function (string $who, int $status) {
    $conversation = chat();
    $user = match ($who) {
        'owner' => $conversation->owner,
        'recipient' => $conversation->recipient,
        default => User::factory()->band()->create(),
    };

    $this->actingAs($user)->get(route('messages.show', $conversation))->assertStatus($status);
})->with([
    'host' => ['owner', 200],
    'band' => ['recipient', 200],
    'anyone else' => ['stranger', 403],
]);

test('sending a message stores it, pushes it live and counts as unread for the other side', function () {
    EventFacade::fake([MessageSent::class]);
    Queue::fake();
    $conversation = chat();

    Livewire::actingAs($conversation->recipient)
        ->test(Index::class, ['conversation' => $conversation])
        ->set('body', "Can we soundcheck at 4?\nThanks!")
        ->call('send')
        ->assertHasNoErrors()
        ->assertSet('body', '')
        ->assertSee('Can we soundcheck at 4?');

    $message = $conversation->messages()->sole();

    expect($message->user_id)->toBe($conversation->recipient_id)
        ->and($conversation->owner->unreadMessagesCount())->toBe(1)
        ->and($conversation->recipient->unreadMessagesCount())->toBe(0);

    EventFacade::assertDispatched(MessageSent::class, fn (MessageSent $event) => $event->message->is($message));
    Queue::assertPushed(AlertUnreadMessages::class, fn (AlertUnreadMessages $job) => $job->user->is($conversation->owner));
});

test('messages are broadcast to the chat and to both people', function () {
    $message = Message::factory()->create();
    $conversation = $message->conversation;

    $channels = array_map(fn ($channel) => $channel->name, (new MessageSent($message))->broadcastOn());

    expect($channels)->toBe([
        'private-conversations.'.$conversation->id,
        'private-users.'.$conversation->owner_id,
        'private-users.'.$conversation->recipient_id,
    ]);
});

test('opening a chat marks it read', function () {
    $conversation = chat();
    Message::factory()->for($conversation)->count(2)->create(['user_id' => $conversation->recipient_id]);

    expect($conversation->owner->unreadMessagesCount())->toBe(2);

    Livewire::actingAs($conversation->owner)->test(Index::class, ['conversation' => $conversation]);

    expect($conversation->owner->unreadMessagesCount())->toBe(0);
});

test('a message that arrives while the chat is open is read straight away', function () {
    $conversation = chat();
    $component = Livewire::actingAs($conversation->owner)->test(Index::class, ['conversation' => $conversation]);

    Message::factory()->for($conversation)->create(['user_id' => $conversation->recipient_id, 'body' => 'Live one']);

    $component->call('messageReceived')->assertSee('Live one')->assertDispatched('messages-read');

    expect($conversation->owner->unreadMessagesCount())->toBe(0);
});

test('the chat list shows each chat with its unread count', function () {
    $conversation = chat();
    Message::factory()->for($conversation)->create(['user_id' => $conversation->recipient_id, 'body' => 'See you there']);
    chat();

    $component = Livewire::actingAs($conversation->owner)->test(Index::class);

    expect($component->get('conversations')->pluck('id')->all())->toBe([$conversation->id]);
    $component->assertSee($conversation->recipient->displayName())
        ->assertSee($conversation->booking->event->title)
        ->assertSee('See you there');
});

test('messages must have something in them and not be too long', function (string $body) {
    $conversation = chat();

    Livewire::actingAs($conversation->owner)
        ->test(Index::class, ['conversation' => $conversation])
        ->set('body', $body)
        ->call('send')
        ->assertHasErrors(['body']);

    expect($conversation->messages()->count())->toBe(0);
})->with([
    'empty' => [''],
    'too long' => [str_repeat('a', Message::MAX_LENGTH + 1)],
]);

test('messages are shown as plain text', function () {
    $conversation = chat();
    Message::factory()->for($conversation)->create(['body' => '<script>alert(1)</script>']);

    Livewire::actingAs($conversation->recipient)
        ->test(Index::class, ['conversation' => $conversation])
        ->assertDontSeeHtml('<script>alert(1)</script>')
        ->assertSee('<script>alert(1)</script>');
});

test('sending is rate limited', function () {
    $conversation = chat();
    RateLimiter::increment('messages:'.$conversation->owner_id, 60, Index::MESSAGES_PER_MINUTE);

    Livewire::actingAs($conversation->owner)
        ->test(Index::class, ['conversation' => $conversation])
        ->set('body', 'One more')
        ->call('send')
        ->assertHasErrors(['body']);
});

test('the chat turns read-only once the booking or event is off, or the event has happened', function (Closure $end, string $reason) {
    $conversation = chat();
    $end($conversation);

    Livewire::actingAs($conversation->owner)
        ->test(Index::class, ['conversation' => $conversation->fresh()])
        ->assertSee($reason)
        ->assertDontSeeHtml('data-test="send-message"')
        ->set('body', 'Hello?')
        ->call('send')
        ->assertForbidden();
})->with([
    'booking cancelled' => [fn (Conversation $c) => $c->booking->update(['status' => BookingStatus::Cancelled]), 'booking was cancelled'],
    'event cancelled' => [fn (Conversation $c) => $c->booking->event->update(['status' => EventStatus::Cancelled]), 'event was cancelled'],
    'event happened' => [fn (Conversation $c) => $c->booking->event->update(['starts_at' => now()->subDay()]), 'event has happened'],
]);

test('cancelling the event leaves a note in its chats', function () {
    $conversation = chat();

    app(CancelBookings::class)->handle($conversation->booking->event);

    expect($conversation->messages()->latest('id')->first())
        ->isSystem()->toBeTrue()
        ->body->toContain('cancelled');
});

test('the message button opens the booking chat for the two people on it', function () {
    $booking = BookingRequest::factory()->accepted()->create();

    $this->actingAs($booking->recipient)
        ->get(route('bookings.chat', $booking))
        ->assertRedirect(route('messages.show', Conversation::where('booking_request_id', $booking->id)->sole()));

    $this->actingAs(User::factory()->band()->create())->get(route('bookings.chat', $booking))->assertForbidden();
});

test('there is no chat until the booking is accepted', function () {
    $booking = BookingRequest::factory()->create();

    $this->actingAs($booking->event->owner)->get(route('bookings.chat', $booking))->assertForbidden();

    expect(Conversation::count())->toBe(0);
});

test('confirmed bookings have a message button', function () {
    $booking = BookingRequest::factory()->accepted()->create();

    Livewire::actingAs($booking->recipient)
        ->test(App\Livewire\Bookings\Index::class)
        ->assertSeeHtml('data-test="message-booking-'.$booking->id.'"');
});

test('the sidebar shows how many messages are unread', function () {
    $conversation = chat();
    Message::factory()->for($conversation)->count(3)->create(['user_id' => $conversation->recipient_id]);

    Livewire::actingAs($conversation->owner)->test(SidebarLink::class)->assertSee('3');
});

test('unread messages are emailed once, bundled, and not at all once read', function () {
    $conversation = chat();
    $owner = $conversation->owner;
    Message::factory()->for($conversation)->count(2)->create(['user_id' => $conversation->recipient_id]);

    (new AlertUnreadMessages($conversation, $owner))->handle();

    Notification::assertSentTo($owner, UnreadMessages::class, fn (UnreadMessages $mail) => $mail->count === 2);

    // Nothing new since that email: no second one.
    (new AlertUnreadMessages($conversation, $owner))->handle();
    Notification::assertSentToTimes($owner, UnreadMessages::class, 1);

    // A message the owner has already read doesn't trigger one either.
    Carbon::setTestNow(now()->addMinute());
    Message::factory()->for($conversation)->create(['user_id' => $conversation->recipient_id]);
    Carbon::setTestNow(now()->addMinute());
    $conversation->markReadFor($owner);

    (new AlertUnreadMessages($conversation, $owner))->handle();
    Notification::assertSentToTimes($owner, UnreadMessages::class, 1);
});

test('the alert email names the sender and event and links to the chat', function () {
    $conversation = chat();
    Message::factory()->for($conversation)->create(['user_id' => $conversation->recipient_id, 'body' => 'Parking at the back?']);

    $mail = (new UnreadMessages($conversation, 1))->toMail($conversation->owner);

    expect($mail->subject)->toContain($conversation->recipient->displayName())
        ->toContain($conversation->booking->event->title)
        ->and($mail->actionUrl)->toBe(route('messages.show', $conversation))
        ->and(implode(' ', $mail->introLines))->toContain('Parking at the back?');
});

test('the alert waits before it is sent', function () {
    Queue::fake();
    $conversation = chat();

    app(SendMessage::class)->handle($conversation, $conversation->owner, 'Hi');

    Queue::assertPushed(AlertUnreadMessages::class, fn (AlertUnreadMessages $job) => $job->delay !== null);
});
