<?php

namespace App\Http\Controllers;

use App\Events\ConversationMessageSent;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\Ticket;
use App\Models\WhatsAppTemplate;
use App\Services\WhatsApp\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class ConversationController extends Controller implements HasMiddleware
{
    /**
     * Aquí viven los mensajes de los clientes finales de terceros, así que el
     * módulo va cerrado por permiso además del scoping.
     *
     * Hacían falta los dos: `autorizar()` compara contra `users.client_id`, y
     * un usuario interno lo tiene null, así que pasaba de largo y veía TODAS
     * las conversaciones de todos los clientes escribiendo la URL. El enlace
     * del sidebar sí consultaba `Ver Conversaciones`, pero la ruta no validaba
     * nada.
     *
     * Mismo problema que ya se corrigió en SocialController,
     * WhatsAppConnectController y WhatsAppTemplateController.
     *
     * El corte entre los dos permisos es leer contra escribir: `Ver` abre la
     * bandeja, `Responder` cubre todo lo que sale hacia el contacto o cambia
     * la conversación.
     */
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Conversaciones', only: ['index', 'show']),
            new Middleware('can:Responder Conversaciones', only: [
                'reply', 'replyTemplate', 'assign', 'updateStatus',
                'toggleAi', 'createTicket',
            ]),
        ];
    }

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
                // Fuera de la ventana de 24 h es la única salida que hay, así
                // que la UI necesita las plantillas para poder ofrecerlas.
                'plantillas'     => $this->plantillasDisponibles($conversation),
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

    /**
     * Solo las APPROVED de la WABA por la que va esta conversación. Ofrecer una
     * PENDING sería ofrecer un envío que Meta va a rechazar.
     */
    private function plantillasDisponibles(Conversation $conversation): array
    {
        $cuenta = $conversation->number?->account;

        if (!$cuenta) {
            return [];
        }

        return $cuenta->templates()
            ->where('status', WhatsAppTemplate::STATUS_APPROVED)
            ->orderBy('name')
            ->get()
            ->map(fn (WhatsAppTemplate $p) => [
                'id'             => $p->id,
                'name'           => $p->name,
                'language'       => $p->language,
                'body'           => $p->cuerpo(),
                'body_variables' => $p->body_variables,
            ])
            ->all();
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

    /**
     * Responder con una plantilla aprobada.
     *
     * Es el camino que sí funciona con la ventana cerrada, y también sirve
     * dentro de ella. El mensaje se guarda con el texto ya sustituido: en el
     * hilo tiene que leerse lo que recibió el contacto, no `pedido_listo`.
     */
    public function replyTemplate(Request $request, Conversation $conversation, WhatsAppService $whatsapp)
    {
        $this->autorizar($conversation);

        $datos = $request->validate([
            'template_id' => 'required|integer',
            'parametros'  => 'array',
            'parametros.*' => 'required|string|max:1024',
        ]);

        $numero = $conversation->number;

        // La plantilla tiene que ser de la WABA por la que va esta
        // conversación: un id en el body no puede alcanzar la de otro cliente.
        $plantilla = WhatsAppTemplate::where('id', $datos['template_id'])
            ->where('whatsapp_account_id', $numero?->whatsapp_account_id)
            ->first();

        if (!$plantilla || !$plantilla->estaAprobada()) {
            return back()->withErrors([
                'template_id' => 'Esa plantilla no está disponible para esta conversación.',
            ]);
        }

        $parametros = array_values($datos['parametros'] ?? []);

        if (count($parametros) !== $plantilla->body_variables) {
            return back()->withErrors([
                'template_id' => "La plantilla necesita exactamente {$plantilla->body_variables} valor(es).",
            ]);
        }

        $waMessageId = $whatsapp->sendTemplate(
            $conversation->contact_wa_id,
            $plantilla->name,
            $plantilla->language,
            $parametros,
            $numero?->phone_number_id,
            $numero?->tokenParaEnviar(),
        );

        $mensaje = $conversation->messages()->create([
            'user_id'         => Auth::id(),
            'author_type'     => ConversationMessage::AUTHOR_STAFF,
            'direction'       => ConversationMessage::DIRECTION_OUT,
            'wa_message_id'   => $waMessageId,
            'type'            => 'template',
            'body'            => $plantilla->previsualizar($parametros),
            'delivery_status' => $waMessageId
                ? ConversationMessage::DELIVERY_SENT
                : ConversationMessage::DELIVERY_FAILED,
            'delivery_error'  => $waMessageId ? null : 'Meta no aceptó la plantilla. Revisa el log.',
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
