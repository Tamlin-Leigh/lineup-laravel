<?php

namespace App\Notifications;

use App\Models\Conversation;
use App\Models\User;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * An email about messages left unread in a booking chat. Sent from the AlertUnreadMessages job,
 * which already runs on the queue, so this one goes out straight away.
 */
class UnreadMessages extends Notification
{
    public function __construct(public Conversation $conversation, public int $count) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(User $notifiable): MailMessage
    {
        $from = $this->conversation->otherParticipant($notifiable)->displayName();
        $event = $this->conversation->booking->event->title;
        $latest = $this->conversation->messages()->whereNotNull('user_id')->where('user_id', '!=', $notifiable->id)->latest('id')->first();

        $mail = (new MailMessage)
            ->subject(trans_choice(':from sent you a message about :event|:from sent you :count messages about :event', $this->count, ['from' => $from, 'event' => $event, 'count' => $this->count]))
            ->greeting(__('Hi :name,', ['name' => $notifiable->displayName()]))
            ->line(trans_choice('You have an unread message from :from about **:event**.|You have :count unread messages from :from about **:event**.', $this->count, ['from' => $from, 'event' => $event, 'count' => $this->count]));

        if ($latest !== null) {
            $mail->line('“'.str($latest->body)->limit(200).'”');
        }

        return $mail->action(__('Reply'), route('messages.show', $this->conversation));
    }
}
