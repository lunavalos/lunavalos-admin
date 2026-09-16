<?php

namespace App\Observers;

use App\Jobs\NotifyTicketUpdate;
use App\Models\Ticket;
use App\Models\User;

/**
 * Avisa por WhatsApp a la persona afectada cuando un ticket cambia de verdad.
 *
 * Va en un observer y no en el controlador porque "actualizar un ticket" son
 * seis endpoints (`updateStatus`, `assign`, `updateService`, `updateCycle`,
 * `startWork`, `update`) más lo que toque el Kanban al arrastrar. Colgarlo de
 * cada uno era garantía de olvidarse de alguno.
 *
 * **Solo estado y responsable.** Notificar cualquier `updated` convertiría el
 * aviso en ruido —cada guardado de título, cada recálculo de crédito, cada
 * `status_updated_at` que el propio modelo toca en `boot()`— y en WhatsApp el
 * ruido no se ignora: se silencia el número entero, y entonces no llega
 * tampoco lo que sí importaba.
 */
class TicketObserver
{
    public function updated(Ticket $ticket): void
    {
        // El job también lo comprueba, pero hacerlo aquí evita encolar jobs
        // que solo van a devolverse a sí mismos. Apagado es el estado por
        // defecto, incluido el de los tests.
        if (!$this->encendido()) {
            return;
        }

        $actor = auth()->user();

        // Sin sesión el cambio no lo hizo una persona: es el scheduler, un
        // comando o un job. `tickets:auto-close-in-review` corre `->daily()`,
        // que con `APP_TIMEZONE=UTC` cae a las 18:00 de Saltillo, y cierra en
        // un bucle todos los tickets con 5 días en revisión — un save() por
        // ticket, o sea una ráfaga diaria de WhatsApps a las 6 de la tarde.
        //
        // Un cierre automático no tiene dueño ni urgencia, y cada aviso es una
        // conversación de utilidad que se factura. Nadie necesita enterarse.
        if (!$actor) {
            return;
        }

        $cambios = [];

        if ($ticket->wasChanged('status')) {
            $antes = $ticket->getOriginal('status') ?: 'sin estado';
            $cambios[] = "Pasó de {$antes} a {$ticket->status}";
        }

        if ($ticket->wasChanged('assigned_id')) {
            $cambios[] = $ticket->assigned_id
                ? 'Te lo asignaron'
                : 'Quedó sin responsable';
        }

        if (!$cambios) {
            return;
        }

        $destinatario = $this->destinatario($ticket, $actor);

        if (!$destinatario) {
            return;
        }

        NotifyTicketUpdate::dispatch(
            $ticket->id,
            implode('. ', $cambios),
            $actor->name,
            $destinatario->id,
        );
    }

    /**
     * A quién le importa este cambio: al responsable del ticket.
     *
     * **Nunca a quien lo hizo.** Si arrastras una tarjeta tú misma, ya sabes
     * que la arrastraste; el WhatsApp sobra y cuesta. El aviso existe para la
     * persona que *no* estaba mirando la pantalla.
     *
     * Se lee `assigned_id` en vez de la relación `assigned` para no depender de
     * si estaba cargada antes del cambio: tras reasignar, una relación ya
     * cargada devolvería al responsable anterior y el aviso le llegaría a quien
     * acaba de dejar de tenerlo.
     *
     * Sin responsable no hay a quién avisar, y está bien: un ticket que no es
     * de nadie no le quita el sueño a nadie.
     */
    private function destinatario(Ticket $ticket, User $actor): ?User
    {
        if (!$ticket->assigned_id || $ticket->assigned_id === $actor->id) {
            return null;
        }

        return User::find($ticket->assigned_id);
    }

    private function encendido(): bool
    {
        return filled(config('services.whatsapp.ticket_alerts.template'));
    }
}
