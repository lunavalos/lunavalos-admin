<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TicketNotification extends Notification implements ShouldBroadcast, ShouldQueue
{
    use Queueable;

    protected $ticket;
    protected $message;
    protected $type; // 'created', 'assigned', 'new_message', 'status_changed'

    public function __construct($ticket, $type, $message)
    {
        $this->ticket = $ticket;
        $this->type = $type;
        $this->message = $message;
    }

    public function via(object $notifiable): array
    {
        $channels = ['database'];
        
        // Habilitamos el canal de broadcast si hay una conexión configurada (Reverb, Pusher, etc)
        if (config('broadcasting.default') !== 'null' && config('broadcasting.default') !== 'log') {
            $channels[] = 'broadcast';
        }
        
        return $channels;
    }

    public function toArray(object $notifiable): array
    {
        return [
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'type' => $this->type,
            'message' => $this->message,
            'url' => route('tickets.show', $this->ticket->id),
        ];
    }

    /**
     * Get the broadcastable representation of the notification.
     */
    public function toBroadcast(object $notifiable): \Illuminate\Notifications\Messages\BroadcastMessage
    {
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'ticket_id' => $this->ticket->id,
            'title' => $this->ticket->title,
            'type' => $this->type,
            'message' => $this->message,
            'url' => route('tickets.show', $this->ticket->id),
        ]);
    }
}
