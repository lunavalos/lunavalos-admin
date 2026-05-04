<?php

namespace App\Events;

use App\Models\TicketMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class TicketMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public TicketMessage $ticketMessage;

    public function __construct(TicketMessage $ticketMessage)
    {
        // Eager-load the user relation so it's included in the broadcast payload
        $this->ticketMessage = $ticketMessage->load('user');
    }

    /**
     * Broadcast on a public channel scoped to the ticket.
     * Any authenticated user watching that ticket page can listen.
     */
    public function broadcastOn(): Channel
    {
        return new Channel('ticket.' . $this->ticketMessage->ticket_id);
    }

    /**
     * Name of the client-side event.
     */
    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    /**
     * Data sent to the client.
     */
    public function broadcastWith(): array
    {
        return [
            'id'         => $this->ticketMessage->id,
            'ticket_id'  => $this->ticketMessage->ticket_id,
            'user_id'    => $this->ticketMessage->user_id,
            'message'    => $this->ticketMessage->message,
            'file_path'  => $this->ticketMessage->file_path,
            'created_at' => $this->ticketMessage->created_at->toISOString(),
            'user'       => $this->ticketMessage->user ? [
                'id'                  => $this->ticketMessage->user->id,
                'name'                => $this->ticketMessage->user->name,
                'profile_photo_url'   => $this->ticketMessage->user->profile_photo_url ?? null,
            ] : null,
        ];
    }
}
