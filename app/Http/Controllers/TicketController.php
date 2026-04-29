<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use App\Models\Client;
use App\Models\ClientService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Notifications\TicketNotification;

class TicketController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Ticket::with(['creator.client', 'assigned', 'messages.user', 'client', 'clientService']);

        if ($user->hasRole('Cliente')) {
            $tickets = Ticket::where('creator_id', $user->id)
                ->with(['assigned', 'messages', 'clientService'])
                ->latest()
                ->get();

            return Inertia::render('Tickets/ClientIndex', [
                'tickets' => $tickets
            ]);
        }

        $tickets = $query->latest()->get();

        // Solo usuarios internos pueden ser asignados — los clientes usan ClientIndex.vue
        $assignableUsers = User::role(['Administrador', 'Administrador Master', 'Web Developer', 'RRHH', 'Designer'])
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
            $ticket->messages()->create([
                'user_id' => null,
                'message' => "{$emoji} " . Auth::user()->name . " cambió el estatus a: <strong>{$ticket->status}</strong>",
            ]);

            // Notify participants
            if ($ticket->creator_id !== Auth::id()) {
                $ticket->creator->notify(new TicketNotification($ticket, 'status_changed', $message));
            }
            // Only notify assignee if they are different from the creator
            if ($ticket->assigned_id && $ticket->assigned_id !== Auth::id() && $ticket->assigned_id !== $ticket->creator_id) {
                $ticket->assigned->notify(new TicketNotification($ticket, 'status_changed', $message));
            }
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

    public function addMessage(Request $request, Ticket $ticket)
    {
        $request->validate([
            'message' => 'required|string',
            'file' => 'nullable|file|max:10240', // 10MB limit
            'change_status' => 'nullable|string',
        ]);

        $filePath = null;
        if ($request->hasFile('file')) {
            $filePath = $request->file('file')->store('tickets/attachments', 'public');
        }

        $ticket->messages()->create([
            'user_id' => Auth::id(),
            'message' => $request->message,
            'file_path' => $filePath,
        ]);

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
            $ticket->messages()->create([
                'user_id' => null,
                'message' => "{$emoji} " . Auth::user()->name . " cambió el estatus a: <strong>{$ticket->status}</strong>",
            ]);

            // Notify status change separately
            $statusMsg = "El ticket #{$ticket->id} cambió a: {$ticket->status}";
            if ($ticket->creator_id !== Auth::id()) {
                $ticket->creator->notify(new TicketNotification($ticket, 'status_changed', $statusMsg));
            }
            // Only notify assignee if they are different from the creator
            if ($ticket->assigned_id && $ticket->assigned_id !== Auth::id() && $ticket->assigned_id !== $ticket->creator_id) {
                $ticket->assigned->notify(new TicketNotification($ticket, 'status_changed', $statusMsg));
            }
        }

        $notifMessage = "Nuevo mensaje en ticket #{$ticket->id}: " . Auth::user()->name;

        // Notify about the message itself
        if ($ticket->creator_id !== Auth::id()) {
            $ticket->creator->notify(new TicketNotification($ticket, 'new_message', $notifMessage));
        }
        // Only notify assignee if they are different from the creator
        if ($ticket->assigned_id && $ticket->assigned_id !== Auth::id() && $ticket->assigned_id !== $ticket->creator_id) {
            $ticket->assigned->notify(new TicketNotification($ticket, 'new_message', $notifMessage));
        }

        return redirect()->back()->with('success', 'Mensaje enviado.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['creator.client', 'assigned', 'messages.user', 'attachments', 'client.services', 'clientService']);

        $assignableUsers = User::role(['Administrador', 'Administrador Master', 'Web Developer', 'RRHH', 'Designer'])->get();

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
        $isAdmin = $user->hasAnyRole(['Administrador', 'Administrador Master']);

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
        $isAdmin = $user->hasAnyRole(['Administrador', 'Administrador Master']);

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
        $isAdmin = $user->hasAnyRole(['Administrador', 'Administrador Master']);

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
        $isAdmin = $user->hasAnyRole(['Administrador', 'Administrador Master']);

        if (!$isAdmin) {
            return redirect()->back()->with('error', 'No tienes permisos para vaciar la papelera.');
        }

        Ticket::onlyTrashed()->forceDelete();

        return redirect()->back()->with('success', 'Papelera vaciada correctamente.');
    }
}

