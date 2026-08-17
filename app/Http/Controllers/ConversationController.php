<?php

namespace App\Http\Controllers;

use App\Events\ConversationMessageSent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Ticket;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ConversationController extends Controller
{
    /**
     * Un usuario amarrado a un cliente (`users.client_id`) solo ve las
     * conversaciones de ese cliente. Mismo criterio que SocialController.
     *
     * Las conversaciones del número propio de LunAvalos tienen client_id null,
     * así que quedan fuera del alcance de cualquier usuario de portal.
     */
    private function autorizar(Conversation $conversation): void
    {
        $propio = request()->user()?->client_id;

        abort_if($propio !== null && $propio !== $conversation->client_id, 403, 'Acceso denegado.');
    }

    public function index(Request $request)
    {
        return Inertia::render('Conversations/Index', [
            'conversations' => $this->bandeja($request),
            'conversation'  => null,
            'filtros'       => ['status' => $request->input('status', 'open')],
        ]);
    }

    public function show(Request $request, Conversation $conversation)
    {
        $this->autorizar($conversation);

        // Abrir la conversación es leerla: el contador solo sirve si se limpia.
        $conversation->forceFill(['unread_count' => 0])->save();

        $conversation->load(['number.account', 'client:id,business_name', 'assignee:id,name']);

        return Inertia::render('Conversations/Index', [
            'conversations' => $this->bandeja($request),
            'filtros'       => ['status' => $request->input('status', 'open')],
            'conversation'  => [
                'id'             => $conversation->id,
                'contact_wa_id'  => $conversation->contact_wa_id,
                'contact_name'   => $conversation->contact_name,
                'status'         => $conversation->status,
                'ai_enabled'     => $conversation->ai_enabled,
                'ventana_abierta' => $conversation->ventanaAbierta(),
                'last_inbound_at' => $conversation->last_inbound_at?->toISOString(),
                'client'         => $conversation->client?->only(['id', 'business_name']),
                'assignee'       => $conversation->assignee?->only(['id', 'name']),
                'numero'         => $conversation->number?->display_phone_number,
                'tickets'        => $conversation->tickets()->get(['id', 'title', 'status']),
                'messages'       => $conversation->messages()
                    ->with('user:id,name')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (ConversationMessage $m) => [
                        'id'              => $m->id,
                        'author_type'     => $m->author_type,
                        'direction'       => $m->direction,
                        'body'            => $m->body,
                        'type'            => $m->type,
                        'delivery_status' => $m->delivery_status,
                        'delivery_error'  => $m->delivery_error,
                        'user'            => $m->user?->only(['id', 'name']),
                        'created_at'      => $m->created_at->toISOString(),
                    ]),
            ],
        ]);
    }

    private function bandeja(Request $request)
    {
        $propio = $request->user()?->client_id;
        $status = $request->input('status', 'open');

        return Conversation::query()
            ->when($propio, fn ($q) => $q->where('client_id', $propio))
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->with(['client:id,business_name', 'assignee:id,name'])
            ->orderByDesc('last_message_at')
            ->limit(100)
            ->get()
            ->map(fn (Conversation $c) => [
                'id'            => $c->id,
                'contact_wa_id' => $c->contact_wa_id,
                'contact_name'  => $c->contact_name,
                'status'        => $c->status,
                'unread_count'  => $c->unread_count,
                'client'        => $c->client?->only(['id', 'business_name']),
                'assignee'      => $c->assignee?->only(['id', 'name']),
                'ventana_abierta' => $c->ventanaAbierta(),
                'last_message_at' => $c->last_message_at?->toISOString(),
            ]);
    }

    /**
     * Responder al contacto.
     *
     * Fuera de la ventana de 24 h Meta rechaza el texto libre con 131047. Antes
     * el mensaje se guardaba igual y nadie se enteraba de que el contacto nunca
     * lo recibió; ahora se bloquea antes de intentarlo.
     */
    public function reply(Request $request, Conversation $conversation, WhatsAppService $whatsapp)
    {
        $this->autorizar($conversation);

        $request->validate(['body' => 'required|string|max:4096']);

        if (!$conversation->ventanaAbierta()) {
            return back()->withErrors([
                'body' => 'La ventana de 24 horas está cerrada. Meta no entrega texto libre; '
                    . 'hace falta una plantilla aprobada.',
            ]);
        }

        $numero = $conversation->number;

        $waMessageId = $whatsapp->sendText(
            $conversation->contact_wa_id,
            $request->body,
            $numero?->phone_number_id,
            $numero?->tokenParaEnviar(),
        );

        $mensaje = $conversation->messages()->create([
            'user_id'         => Auth::id(),
            'author_type'     => ConversationMessage::AUTHOR_STAFF,
            'direction'       => ConversationMessage::DIRECTION_OUT,
            'wa_message_id'   => $waMessageId,
            'body'            => $request->body,
            // Sin wamid Meta no lo aceptó. Se guarda como fallido en vez de
            // fingir que salió: el equipo tiene que poder verlo.
            'delivery_status' => $waMessageId
                ? ConversationMessage::DELIVERY_SENT
                : ConversationMessage::DELIVERY_FAILED,
            'delivery_error'  => $waMessageId ? null : 'Meta no aceptó el envío. Revisa el log.',
        ]);

        $conversation->registrarSaliente();

        broadcast(new ConversationMessageSent($mensaje))->toOthers();

        return back();
    }

    public function assign(Request $request, Conversation $conversation)
    {
        $this->autorizar($conversation);

        $request->validate(['assigned_id' => 'nullable|exists:users,id']);

        $conversation->update(['assigned_id' => $request->assigned_id]);

        return back();
    }

    public function updateStatus(Request $request, Conversation $conversation)
    {
        $this->autorizar($conversation);

        $request->validate([
            'status' => 'required|in:' . implode(',', [
                Conversation::STATUS_OPEN,
                Conversation::STATUS_SNOOZED,
                Conversation::STATUS_ARCHIVED,
            ]),
        ]);

        $conversation->update(['status' => $request->status]);

        return back();
    }

    public function toggleAi(Conversation $conversation)
    {
        $this->autorizar($conversation);

        $conversation->update(['ai_enabled' => !$conversation->ai_enabled]);

        return back();
    }

    /**
     * Escalar a ticket. La conversación sigue viva: el ticket solo rastrea el
     * trabajo que salió de ella.
     */
    public function createTicket(Request $request, Conversation $conversation)
    {
        $this->autorizar($conversation);

        $request->validate(['title' => 'required|string|max:255']);

        $ticket = Ticket::create([
            'title'             => $request->title,
            'content'           => $conversation->messages()->latest('id')->first()?->body ?? '',
            'priority'          => 'Media',
            'status'            => 'Nuevos',
            'source_type'       => Ticket::SOURCE_SUPPORT,
            'channel'           => Ticket::CHANNEL_WHATSAPP,
            'creator_id'        => Auth::id(),
            'client_id'         => $conversation->client_id,
            'conversation_id'   => $conversation->id,
            'visible_to_client' => (bool) $conversation->client_id,
        ]);

        return redirect()->route('tickets.show', $ticket->id);
    }
}
