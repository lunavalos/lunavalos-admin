<?php

namespace App\Events;

use App\Models\ConversationMessage;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class ConversationMessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public ConversationMessage $message;

    public function __construct(ConversationMessage $message)
    {
        $this->message = $message->load('user');
    }

    /**
     * Canal privado, no público como el de tickets: aquí viajan mensajes de
     * clientes finales de terceros. Quién puede escuchar se decide en
     * routes/channels.php.
     */
    public function broadcastOn(): Channel
    {
        return new \Illuminate\Broadcasting\PrivateChannel(
            'conversation.' . $this->message->conversation_id
        );
    }

    public function broadcastAs(): string
    {
        return 'message.sent';
    }

    public function broadcastWith(): array
    {
        return [
            'id'              => $this->message->id,
            'conversation_id' => $this->message->conversation_id,
            'author_type'     => $this->message->author_type,
            'direction'       => $this->message->direction,
            'body'            => $this->message->body,
            'type'            => $this->message->type,
            'delivery_status' => $this->message->delivery_status,
            'delivery_error'  => $this->message->delivery_error,
            'created_at'      => $this->message->created_at->toISOString(),
            'user'            => $this->message->user?->only(['id', 'name']),
        ];
    }
}
