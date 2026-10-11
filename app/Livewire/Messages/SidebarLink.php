<?php

namespace App\Livewire\Messages;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The sidebar's Messages item. Its unread badge updates live: on any new message in the user's
 * chats (over Reverb) and when the Messages page marks a chat as read.
 */
class SidebarLink extends Component
{
    /**
     * @return array<string, string>
     */
    public function getListeners(): array
    {
        return ['echo-private:users.'.$this->user()->id.',MessageSent' => '$refresh'];
    }

    #[On('messages-read')]
    public function refreshCount(): void
    {
        // Re-render with the new count.
    }

    public function render(): mixed
    {
        return view('livewire.messages.sidebar-link', [
            'unread' => $this->user()->unreadMessagesCount(),
        ]);
    }

    private function user(): User
    {
        /** @var User */
        return Auth::user();
    }
}
