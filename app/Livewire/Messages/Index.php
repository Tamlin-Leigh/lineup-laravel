<?php

namespace App\Livewire\Messages;

use App\Actions\SendMessage;
use App\Models\Conversation;
use App\Models\Message;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Private booking chats: the user's chats on the left, the open one on the right. New messages
 * arrive over Reverb (Echo), so the page updates without refreshing.
 *
 * @property-read Collection<int, Conversation> $conversations
 * @property-read Conversation|null $active
 * @property-read Collection<int, Message> $messages
 */
class Index extends Component
{
    /** Messages one person can send per minute, across all their chats. */
    public const MESSAGES_PER_MINUTE = 20;

    /** How much of a long chat to show at once. */
    private const HISTORY = 200;

    #[Locked]
    public ?int $conversationId = null;

    public string $body = '';

    public function mount(?Conversation $conversation = null): void
    {
        if ($conversation?->exists) {
            $this->authorize('view', $conversation);

            $this->conversationId = $conversation->id;
            $conversation->markReadFor($this->user());
        }
    }

    /**
     * Listen for new messages in any of the user's chats (to refresh the list) and in the open one.
     *
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        $listeners = ['echo-private:users.'.$this->user()->id.',MessageSent' => 'refreshList'];

        if ($this->conversationId !== null) {
            $listeners['echo-private:conversations.'.$this->conversationId.',MessageSent'] = 'messageReceived';
        }

        return $listeners;
    }

    public function refreshList(): void
    {
        unset($this->conversations);
    }

    /**
     * A message arrived in the open chat: show it, and count it as read since it's on screen.
     */
    public function messageReceived(): void
    {
        $this->active?->markReadFor($this->user());

        unset($this->messages, $this->conversations);
        $this->dispatch('messages-read');
    }

    public function send(SendMessage $sendMessage): void
    {
        $conversation = $this->active;
        abort_if($conversation === null, 404);

        $this->authorize('send', $conversation);

        $this->validate(['body' => ['required', 'string', 'max:'.Message::MAX_LENGTH]], attributes: ['body' => __('message')]);

        $key = 'messages:'.$this->user()->id;

        if (RateLimiter::tooManyAttempts($key, self::MESSAGES_PER_MINUTE)) {
            throw ValidationException::withMessages(['body' => __('You are sending messages very quickly. Please wait a moment.')]);
        }

        RateLimiter::hit($key, 60);
        $sendMessage->handle($conversation, $this->user(), $this->body);

        $this->reset('body');
        unset($this->messages, $this->conversations);
    }

    /**
     * The user's chats, most recently active first.
     *
     * @return Collection<int, Conversation>
     */
    #[Computed]
    public function conversations(): Collection
    {
        return Conversation::forUser($this->user())
            ->with([
                'booking.event',
                'owner.artistProfile', 'owner.organiserProfile',
                'recipient.artistProfile', 'recipient.venueProfile',
                'latestMessage',
            ])
            ->get();
    }

    #[Computed]
    public function active(): ?Conversation
    {
        return $this->conversationId !== null
            ? $this->conversations->firstWhere('id', $this->conversationId)
            : null;
    }

    /**
     * The open chat's latest messages, oldest first.
     *
     * @return Collection<int, Message>
     */
    #[Computed]
    public function messages(): Collection
    {
        if ($this->active === null) {
            return new Collection;
        }

        return $this->active->messages()
            ->with('sender.artistProfile', 'sender.organiserProfile')
            ->latest('id')
            ->limit(self::HISTORY)
            ->get()
            ->reverse()
            ->values();
    }

    public function render(): mixed
    {
        return view('livewire.messages.index')->title(__('Messages'));
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
