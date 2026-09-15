<?php

namespace App\Observers;

use App\Jobs\NotifyTicketUpdate;
use App\Models\Ticket;

/**
 * Avisa por WhatsApp cuando un ticket cambia de verdad.
 *
 * Va en un observer y no en el controlador porque "actualizar un ticket" son
 * seis endpoints distintos (`updateStatus`, `assign`, `updateService`,
 * `updateCycle`, `startWork`, `update`) más lo que toque el Kanban al
 * arrastrar. Colgarlo de cada uno garantizaba olvidarse de alguno.
 *
 * **Solo estado y responsable.** Notificar cualquier `updated` convertiría el
 * aviso en ruido —cada guardado de título, cada recálculo de crédito, cada
 * `status_updated_at`— y en WhatsApp el ruido no se ignora: se silencia el
 * número entero, y de paso cada aviso es una conversación de utilidad que se
 * paga.
 */
class TicketObserver
{
    public function updated(Ticket $ticket): void
    {
        // El job también lo comprueba, pero hacerlo aquí evita encolar miles
        // de jobs que solo van a devolverse a sí mismos. Apagado es el estado
        // por defecto, incluido el de los tests.
        if (!$this->encendido()) {
            return;
        }

        $cambios = [];

        if ($ticket->wasChanged('status')) {
            $antes = $ticket->getOriginal('status') ?: 'sin estado';
            $cambios[] = "Pasó de {$antes} a {$ticket->status}";
        }

        if ($ticket->wasChanged('assigned_id')) {
            $cambios[] = $ticket->assigned_id
                ? 'Asignado a ' . ($ticket->assigned?->name ?? "usuario {$ticket->assigned_id}")
                : 'Quedó sin responsable';
        }

        if (!$cambios) {
            return;
        }

        NotifyTicketUpdate::dispatch(
            $ticket->id,
            implode('. ', $cambios),
            // Sin sesión —una consola, un job, el webhook— el cambio no lo
            // hizo nadie a mano, y decir "sistema" es más honesto que dejarlo
            // vacío o atribuírselo a quien tocó el ticket la vez anterior.
            auth()->user()?->name ?? 'el sistema',
        );
    }

    private function encendido(): bool
    {
        return filled(config('services.whatsapp.ticket_alerts.to'))
            && filled(config('services.whatsapp.ticket_alerts.template'));
    }
}
