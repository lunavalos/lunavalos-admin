<?php

namespace App\Jobs;

use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * El doble check azul sale de una llamada a Graph, y Meta espera un 200 rápido
 * del webhook: si tarda, reintenta el evento. Por eso la llamada no puede vivir
 * dentro del request.
 */
class MarkWhatsAppMessageRead implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $waMessageId,
        public string $phoneNumberId,
        public ?string $token = null,
    ) {}

    public function handle(WhatsAppService $whatsapp): void
    {
        $whatsapp->markAsRead($this->waMessageId, $this->phoneNumberId, $this->token);
    }
}
