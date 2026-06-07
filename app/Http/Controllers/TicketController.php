<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Client;
use App\Models\ClientService;
use App\Models\Setting;
use App\Events\TicketMessageSent;
use App\Mail\TeamTicketReportMail;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use App\Notifications\TicketNotification;
use Carbon\Carbon;

class TicketController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Ticket::with(['creator.client', 'assigned', 'messages.user', 'client', 'clientService'])
            ->support()
            ->where('is_archived', false); // Kanban de soporte: solo tickets sueltos, no entregables recurrentes.

        if ($user->hasRole('Cliente')) {
            $clientId = $user->client?->id;
            $tickets = Ticket::support()
                ->where('is_archived', false)
                ->where(function ($q) use ($user, $clientId) {
                    $q->where('creator_id', $user->id)
                      ->orWhere(function ($q2) use ($clientId) {
                          $q2->where('visible_to_client', true)
                             ->where('client_id', $clientId);
                      });
                })
                ->with(['assigned', 'messages', 'clientService'])
                ->latest()
                ->get();

            // Load the client's active services for the services summary widget
            $clientServices = collect();
            if ($user->client) {
                $clientServices = $user->client->services()
                    ->where('status', 'active')
                    ->orderBy('renewal_date')
                    ->get(['id', 'service_name', 'billing_type', 'renewal_date', 'renewal_amount']);
            }

            return Inertia::render('Tickets/ClientIndex', [
                'tickets'        => $tickets,
                'clientServices' => $clientServices,
            ]);
        }

        $tickets = $query->latest()->get();

        // Solo usuarios internos pueden ser asignados — los clientes usan ClientIndex.vue
        $assignableUsers = User::staff()
            ->orderBy('name')
            ->get();

        // Fetch clients with their active services for the dropdown
        $clients = Client::with(['services' => function ($q) {
            $q->where('status', 'active')->orderBy('service_name');
        }])->orderBy('business_name')->get();

        return Inertia::render('Tickets/Index', [
            'tickets'         => $tickets,
            'assignableUsers' => $assignableUsers,
            'clients'         => $clients,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title'             => 'required|string|max:255',
            'priority'          => 'required|string',
            'content'           => 'nullable|string',
            'assigned_id'       => 'nullable|exists:users,id',
            'due_date'          => 'nullable|date',
            'client_id'         => 'nullable|exists:clients,id',
            'client_service_id' => 'nullable|exists:client_services,id',
            'files.*'           => 'nullable|file|max:10240',
        ]);

        $clientId = $request->client_id;
        if (!$clientId && Auth::user()->hasRole('Cliente')) {
            $clientId = Auth::user()->client?->id;
        }

        $ticket = Ticket::create([
            'title'             => $request->title,
            'priority'          => $request->priority,
            'content'           => $request->input('content'),
            'creator_id'        => Auth::id(),
            'assigned_id'       => $request->assigned_id,
            'client_id'         => $clientId,
            'client_service_id' => $request->client_service_id,
            'source_type'       => Ticket::SOURCE_SUPPORT,
            'due_date'          => $request->due_date,
            'status'            => $request->assigned_id ? 'En Proceso' : 'Nuevos',
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('tickets/attachments', 'public');
                $ticket->attachments()->create([
                    'file_path' => $path,
                    'file_name' => $file->getClientOriginalName(),
                ]);
            }
        }

        // Si el creador es un cliente, notificar a todos los administradores
        if (Auth::user()->hasRole('Cliente')) {
            $admins = User::admins()->get();
            $notifMsg = '📩 Nuevo ticket de ' . Auth::user()->name . ': ' . $ticket->title;
            foreach ($admins as $admin) {
                $admin->notify(new TicketNotification($ticket, 'created', $notifMsg));
            }
        }

        // Notificar solo al usuario asignado (si existe y es distinto al creador)
        if ($ticket->assigned_id && $ticket->assigned_id !== Auth::id()) {
            $ticket->assigned->notify(new TicketNotification(
                $ticket,
                'assigned',
                'Te han asignado un nuevo ticket: ' . $ticket->title
            ));
        }

        return redirect()->back()->with('success', 'Ticket creado correctamente.');
    }

    public function updateStatus(Request $request, Ticket $ticket)
    {
        $user = Auth::user();
        if ($user->hasRole('Cliente') && !$this->clientCanViewTicket($user, $ticket)) abort(403);

        $request->validate([
            'status' => 'required|string',
            'due_date' => 'nullable|date',
        ]);

        $oldStatus = $ticket->status;
        $ticket->status = $request->status;
        if ($request->has('due_date')) {
            $ticket->due_date = $request->due_date;
        }

        // Auto-reassign to creator if it's a client and moving to "En Revisión"
        if ($ticket->status === 'En Revisión' && $ticket->creator->hasRole('Cliente')) {
            $ticket->assigned_id = $ticket->creator_id;
        }

        // Automatically record work_finished_at when moving to "Completados"
        if ($ticket->status === 'Completados' && $oldStatus !== 'Completados') {
            if ($ticket->work_started_at && !$ticket->work_finished_at) {
                $ticket->work_finished_at = now();
            }
        }

        $ticket->save();

        if ($oldStatus !== $ticket->status) {
            $message = "El ticket #{$ticket->id} cambió a: {$ticket->status}";

            // Log system message in the conversation
            $statusEmojis = [
                'Nuevos'      => '📥',
                'En Proceso'  => '⚡',
                'En Revisión' => '🔍',
                'Ajustes'     => '🔧',
                'Completados' => '✅',
            ];
            $emoji = $statusEmojis[$ticket->status] ?? '🔄';
            $systemMsg = $ticket->messages()->create([
                'user_id' => null,
                'message' => "{$emoji} " . Auth::user()->name . " cambió el estatus a: <strong>{$ticket->status}</strong>",
            ]);
            broadcast(new TicketMessageSent($systemMsg))->toOthers();

            // Notify participants about the status change
            $this->notifyTicketParticipants($ticket, 'status_changed', $message);
        }


        return redirect()->back()->with('success', 'Estatus actualizado.');
    }

    public function assign(Request $request, Ticket $ticket)
    {
        $request->validate([
            'assigned_id' => 'required|exists:users,id',
        ]);

        $oldStatus = $ticket->status;
        $ticket->assigned_id = $request->assigned_id;

        // If the ticket is New, move it to In Process upon assignment
        if ($ticket->status === 'Nuevos') {
            $ticket->status = 'En Proceso';
        }

        $ticket->save();

        // Notify newly assigned user
        if ($ticket->assigned_id !== Auth::id()) {
            $ticket->assigned->notify(new TicketNotification($ticket, 'assigned', 'Te han asignado un ticket: ' . $ticket->title));
        }

        // Notify creator about assignment or status change
        if ($ticket->creator_id !== Auth::id()) {
            $msg = "El ticket #{$ticket->id} ha sido asignado a {$ticket->assigned->name}";
            if ($oldStatus !== $ticket->status) {
                $msg .= " y pasó a estatus: {$ticket->status}";
            }
            $ticket->creator->notify(new TicketNotification($ticket, 'status_changed', $msg));
        }

        return redirect()->back()->with('success', 'Usuario asignado.');
    }

    public function addMessage(Request $request, Ticket $ticket, \App\Services\WhatsApp\WhatsAppService $whatsapp)
    {
        $user = Auth::user();
        if ($user->hasRole('Cliente') && !$this->clientCanViewTicket($user, $ticket)) abort(403);

        $request->validate([
            'message' => 'required|string',
            'file' => 'nullable|file|max:10240', // 10MB limit
            'change_status' => 'nullable|string',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('tickets/attachments', 'public');
        }

        $newMsg = $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => $request->message,
            'file_path' => $filePath,
        ]);

        // Si la conversación vive en WhatsApp, la respuesta del staff también
        // se reenvía al cliente por ese canal — el ticket es el "frontend" del chat.
        if ($ticket->channel === Ticket::CHANNEL_WHATSAPP && $ticket->whatsapp_wa_id) {
            $waMessageId = $whatsapp->sendText($ticket->whatsapp_wa_id, $request->message);
            $newMsg->update([
                'channel'       => Ticket::CHANNEL_WHATSAPP,
                'direction'     => TicketMessage::DIRECTION_OUT,
                'wa_message_id' => $waMessageId,
            ]);
        }

        broadcast(new TicketMessageSent($newMsg))->toOthers();

        $oldStatus = $ticket->status;
        if ($request->change_status && $request->change_status !== $oldStatus) {
            $ticket->status = $request->change_status;

            // Auto-reassign to creator if it's a client and moving to "En Revisión"
            if ($ticket->status === 'En Revisión' && $ticket->creator->hasRole('Cliente')) {
                $ticket->assigned_id = $ticket->creator_id;
            }

            // Automatically record work_finished_at when moving to "Completados"
            if ($ticket->status === 'Completados' && $oldStatus !== 'Completados') {
                if ($ticket->work_started_at && !$ticket->work_finished_at) {
                    $ticket->work_finished_at = now();
                }
            }

            $ticket->save();

            // Log system message in the conversation for the status change
            $statusEmojis = [
                'Nuevos'      => '📥',
                'En Proceso'  => '⚡',
                'En Revisión' => '🔍',
                'Ajustes'     => '🔧',
                'Completados' => '✅',
            ];
            $emoji = $statusEmojis[$ticket->status] ?? '🔄';
            $sysMsg2 = $ticket->messages()->create([
                'user_id' => null,
                'message' => "{$emoji} " . Auth::user()->name . " cambió el estatus a: <strong>{$ticket->status}</strong>",
            ]);
            broadcast(new TicketMessageSent($sysMsg2))->toOthers();

            // Notify about status change — notify everyone involved except the sender
            $statusMsg = "El ticket #{$ticket->id} cambió a: {$ticket->status}";
            $this->notifyTicketParticipants($ticket, 'status_changed', $statusMsg);
        }

        // Notify about the new message — notify everyone involved except the sender
        $notifMessage = "Nuevo mensaje en el ticket #{$ticket->id} de: " . Auth::user()->name;
        $this->notifyTicketParticipants($ticket, 'new_message', $notifMessage);

        return redirect()->back()->with('success', 'Mensaje enviado.');
    }

    private function clientCanViewTicket($user, Ticket $ticket): bool
    {
        $clientId = $user->client?->id;

        // Always allowed: tickets the client created themselves
        if ($ticket->creator_id === $user->id) return true;

        // Recurring tickets: client owns them via client_id
        if ($ticket->source_type === Ticket::SOURCE_RECURRING) {
            return $ticket->client_id === $clientId;
        }

        // Support tickets: only if explicitly made visible
        return $ticket->visible_to_client && $ticket->client_id === $clientId;
    }

    /**
     * Notify all relevant participants of a ticket (except the current sender).
     * Participants: creator, assigned user, and the client's linked user account.
     */
    private function notifyTicketParticipants(Ticket $ticket, string $type, string $message): void
    {
        $senderId = Auth::id();
        $notified = [];

        // Helper to send notification without duplicating
        $notify = function ($user) use ($ticket, $type, $message, $senderId, &$notified) {
            if ($user && $user->id !== $senderId && !in_array($user->id, $notified)) {
                $user->notify(new TicketNotification($ticket, $type, $message));
                $notified[] = $user->id;
            }
        };

        // Notify creator
        if ($ticket->creator) {
            $notify($ticket->creator);
        }

        // Notify assigned user
        if ($ticket->assigned) {
            $notify($ticket->assigned);
        }

        // If no assigned user yet, try to notify the internal team via the ticket's client user link
        // (in case creator is admin and client is not yet assigned)
        if (!$ticket->assigned && $ticket->client && $ticket->client->user_id) {
            $clientUser = User::find($ticket->client->user_id);
            $notify($clientUser);
        }
    }


    public function show(Ticket $ticket)
    {
        $user = Auth::user();

        if ($user->hasRole('Cliente')) {
            if (!$this->clientCanViewTicket($user, $ticket)) abort(403);
        }

        $ticket->load([
            'creator.client.assets',
            'assigned',
            'messages.user',
            'attachments',
            'client.services',
            'client.assets.creator',
            'clientService',
            'canvasItems.pins.user',
            'canvasItems.uploader',
            'canvasItems.children.pins.user',
            'canvasItems.children.uploader',
        ]);

        $assignableUsers = User::staff()->get();

        // If the ticket creator is a client, add them to the assignable list so they can be manually assigned for review/status tracking
        if ($ticket->creator && $ticket->creator->hasRole('Cliente')) {
            $assignableUsers->push($ticket->creator);
        }

        // Servicios activos del cliente vinculado (para el selector inline)
        $clientServices = [];
        if ($ticket->client) {
            $clientServices = $ticket->client->services()
                ->where('status', 'active')
                ->orderBy('service_name')
                ->get();
        }

        return Inertia::render('Tickets/Show', [
            'ticket'          => $ticket,
            'assignableUsers' => $assignableUsers,
            'clientServices'  => $clientServices,
        ]);
    }

    public function destroy(Ticket $ticket)
    {
        $user = Auth::user();

        // Admin can soft-delete anytime
        $isAdmin = $user->isAdmin();

        // Client can only delete their own tickets AND if not assigned yet
        $isOwner = $ticket->creator_id === $user->id;
        $isNotAssigned = is_null($ticket->assigned_id);

        if ($isAdmin || ($isOwner && $isNotAssigned)) {
            $ticket->delete(); // SoftDelete: moves to trash
            return redirect()->route('tickets.index')->with('success', 'Ticket movido a la papelera.');
        }

        return redirect()->back()->with('error', 'No tienes permisos para eliminar este ticket o ya ha sido asignado.');
    }

    public function startWork(Ticket $ticket)
    {
        $user = Auth::user();

        // Only the assigned user can start the work timer
        if ($ticket->assigned_id !== $user->id) {
            return redirect()->back()->with('error', 'Solo el usuario asignado puede iniciar el temporizador de trabajo.');
        }

        // Only set once — don't allow resetting if already started
        if ($ticket->work_started_at) {
            return redirect()->back()->with('error', 'El trabajo ya fue iniciado previamente.');
        }

        $ticket->work_started_at = now();
        $ticket->saveQuietly(); // avoid triggering status_updated_at observer

        // Log a system message in the conversation
        $ticket->messages()->create([
            'user_id' => null,
            'message' => '⏱️ ' . $user->name . ' inició el trabajo en este ticket.',
        ]);

        return redirect()->back()->with('success', 'Trabajo iniciado.');
    }

    public function updateService(Request $request, Ticket $ticket)
    {
        $request->validate([
            'client_service_id' => 'nullable|exists:client_services,id',
        ]);

        $ticket->client_service_id = $request->client_service_id;
        $ticket->save();

        return redirect()->back()->with('success', 'Servicio vinculado correctamente.');
    }

    public function trash()
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        if (!$isAdmin) {
            return redirect()->route('tickets.index')->with('error', 'No tienes permisos para ver la papelera.');
        }

        $trashedTickets = Ticket::onlyTrashed()
            ->with(['creator.client', 'assigned', 'client'])
            ->latest('deleted_at')
            ->get();

        return Inertia::render('Tickets/Trash', [
            'trashedTickets' => $trashedTickets,
        ]);
    }

    public function restore($id)
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'No tienes permisos para restaurar tickets.');
        }

        $ticket = Ticket::onlyTrashed()->findOrFail($id);
        $ticket->restore();

        return redirect()->back()->with('success', 'Ticket restaurado correctamente.');
    }

    public function emptyTrash()
    {
        $user = Auth::user();
        $isAdmin = $user->isAdmin();

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'No tienes permisos para vaciar la papelera.');
        }

        Ticket::onlyTrashed()->forceDelete();

        return redirect()->back()->with('success', 'Papelera vaciada correctamente.');
    }

    /**
     * Send a ticket summary email (per non-client user) to the company's main email.
     * Only admins can trigger this.
     */
    public function sendTeamReport(Request $request)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }

        $request->validate([
            'user_id'   => 'required|exists:users,id',
            'date_from' => 'required|date',
            'date_to'   => 'required|date|after_or_equal:date_from',
        ]);

        $companyEmail = Setting::where('key', 'company_email')->value('value');
        if (!$companyEmail) {
            return back()->with('error', 'No hay un email principal configurado en Ajustes.');
        }

        $teamMember = User::findOrFail($request->user_id);

        // Block sending reports about client users
        if ($teamMember->hasRole('Cliente')) {
            return back()->with('error', 'No se pueden generar reportes de usuarios cliente.');
        }

        $dateFrom = Carbon::parse($request->date_from)->startOfDay();
        $dateTo   = Carbon::parse($request->date_to)->endOfDay();

        $tickets = Ticket::with(['creator.client', 'client', 'clientService'])
            ->where('assigned_id', $teamMember->id)
            ->whereBetween('created_at', [$dateFrom, $dateTo])
            ->latest()
            ->get();

        $companyName = Setting::where('key', 'company_commercial_name')->value('value') ?? 'LunAvalos';

        Mail::to($companyEmail)->send(new TeamTicketReportMail(
            $teamMember,
            $tickets,
            $request->date_from,
            $request->date_to,
            $companyName
        ));

        return back()->with('success', "Reporte de tickets de {$teamMember->name} enviado a {$companyEmail}.");
    }

    /**
     * Edit a ticket message.
     * Rules:
     *  - Clients can NEVER edit messages.
     *  - Non-client staff: can only edit their OWN message IF it is the very last message in the conversation.
     *  - Admins: can edit ANY message EXCEPT messages sent by clients.
     */
    public function updateMessage(Request $request, TicketMessage $message)
    {
        $user = Auth::user();

        // Clients cannot edit messages at all
        if ($user->hasRole('Cliente')) {
            abort(403, 'Los clientes no pueden editar mensajes.');
        }

        $request->validate([
            'message' => 'required|string',
        ]);

        $isAdmin = $user->isAdmin();

        // Check if the message author is a client — nobody can edit client messages
        $messageAuthor = $message->user;
        if ($messageAuthor && $messageAuthor->hasRole('Cliente')) {
            abort(403, 'No se pueden editar mensajes de clientes.');
        }

        if ($isAdmin) {
            // Admins can edit any non-client message freely
            $message->message = $request->message;
            $message->save();

            return redirect()->back()->with('success', 'Mensaje actualizado.');
        }

        // Non-admin staff: can only edit their OWN last message
        if ($message->user_id !== $user->id) {
            abort(403, 'Solo puedes editar tus propios mensajes.');
        }

        $ticket = $message->ticket;

        // Determine the last real (non-system) message in the conversation
        $lastUserMessage = $ticket->messages()
            ->whereNotNull('user_id')
            ->latest()
            ->first();

        if (!$lastUserMessage || $lastUserMessage->id !== $message->id) {
            abort(403, 'Solo puedes editar el último mensaje de la conversación.');
        }

        $message->message = $request->message;
        $message->save();

        return redirect()->back()->with('success', 'Mensaje actualizado.');
    }

    public function archiveList()
    {
        $user = Auth::user();
        if ($user->hasRole('Cliente')) {
            $archivedTickets = Ticket::where('creator_id', $user->id)
                ->where('is_archived', true)
                ->with(['assigned', 'messages', 'client', 'clientService'])
                ->latest()
                ->get();
        } else {
            $archivedTickets = Ticket::where('is_archived', true)
                ->with(['creator.client', 'assigned', 'client', 'clientService'])
                ->latest()
                ->get();
        }

        return Inertia::render('Tickets/Archive', [
            'archivedTickets' => $archivedTickets,
        ]);
    }

    public function toggleArchive(Ticket $ticket)
    {
        $user = Auth::user();
        if ($user->hasRole('Cliente')) {
            abort(403, 'No tienes permisos para realizar esta acción.');
        }

        $ticket->update([
            'is_archived' => !$ticket->is_archived
        ]);

        $message = $ticket->is_archived ? 'Ticket archivado correctamente.' : 'Ticket desarchivado correctamente.';
        return redirect()->back()->with('success', $message);
    }

    public function toggleClientVisibility(Ticket $ticket)
    {
        $user = Auth::user();
        if ($user->hasRole('Cliente')) {
            abort(403);
        }

        $ticket->update(['visible_to_client' => !$ticket->visible_to_client]);

        $label = $ticket->visible_to_client ? 'visible' : 'oculto';
        return redirect()->back()->with('success', "Ticket ahora es {$label} para el cliente.");
    }

    public function generatePublicLink(Ticket $ticket)
    {
        if (Auth::user()->hasRole('Cliente')) abort(403);

        $ticket->update([
            'public_token'            => \Illuminate\Support\Str::random(48),
            'public_token_expires_at' => now()->addDays(7),
        ]);

        return redirect()->back()->with('success', 'Enlace público generado. Válido por 7 días.');
    }

    public function revokePublicLink(Ticket $ticket)
    {
        if (Auth::user()->hasRole('Cliente')) abort(403);

        $ticket->update([
            'public_token'            => null,
            'public_token_expires_at' => null,
        ]);

        return redirect()->back()->with('success', 'Enlace público revocado.');
    }

    public function publicShow(string $token)
    {
        $ticket = Ticket::where('public_token', $token)
            ->where('public_token_expires_at', '>', now())
            ->with([
                'messages.user',
                'canvasItems.pins.user',
                'canvasItems.uploader',
                'canvasItems.children.pins.user',
                'canvasItems.children.uploader',
            ])
            ->firstOrFail();

        // Mask internal staff names
        foreach ($ticket->messages as $message) {
            if ($message->user && !$message->user->hasRole(config('roles.client', 'Cliente'))) {
                $message->user->name = 'LunAvalos';
                $message->user->profile_photo_url = null;
            }
        }

        return Inertia::render('Tickets/PublicShow', [
            'ticket'    => $ticket,
            'expiresAt' => $ticket->public_token_expires_at->toDateString(),
        ]);
    }
}

