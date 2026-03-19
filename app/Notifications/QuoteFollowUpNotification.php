<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Notifications\Notification;

class QuoteFollowUpNotification extends Notification implements ShouldBroadcast
{
    use Queueable;

    protected $quote;

    public function __construct($quote)
    {
        $this->quote = $quote;
    }

    public function via(object $notifiable): array
    {
        return ['database', 'broadcast'];
    }

    public function toArray(object $notifiable): array
    {
        return [
            'quote_id' => $this->quote->id,
            'client_name' => $this->quote->client_name,
            'type' => 'quote_follow_up',
            'message' => 'La cotización para ' . $this->quote->client_name . ' cumple 7 días. ¡Es momento de dar seguimiento!',
            'url' => route('quotes.edit', $this->quote->id),
        ];
    }

    public function toBroadcast(object $notifiable): \Illuminate\Notifications\Messages\BroadcastMessage
    {
        return new \Illuminate\Notifications\Messages\BroadcastMessage([
            'quote_id' => $this->quote->id,
            'client_name' => $this->quote->client_name,
            'type' => 'quote_follow_up',
            'message' => 'Seguimiento de cotización: ' . $this->quote->client_name,
            'url' => route('quotes.edit', $this->quote->id),
        ]);
    }
}
