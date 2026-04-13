<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketMessage;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use App\Notifications\TicketNotification;
use Illuminate\Support\Facades\Notification;

class TicketController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $query = Ticket::with(['creator', 'assigned', 'messages.user']);

        if ($user->hasRole('Cliente')) {
            $tickets = Ticket::where('creator_id', $user->id)
                ->with(['assigned', 'messages'])
                ->latest()
                ->get();

            return Inertia::render('Tickets/ClientIndex', [
                'tickets' => $tickets
            ]);
        }

        $tickets = $query->latest()->get();

        // Prepare users who can be assigned (Admins and Employees)
        $assignableUsers = User::role(['Administrador', 'Administrador Master', 'Web Developer', 'RRHH', 'Designer'])->get();

        return Inertia::render('Tickets/Index', [
            'tickets' => $tickets,
            'assignableUsers' => $assignableUsers,
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'priority' => 'required|string',
            'content' => 'nullable|string',
            'assigned_id' => 'nullable|exists:users,id',
            'due_date' => 'nullable|date',
            'files.*' => 'nullable|file|max:10240', // 10MB per file
        ]);

        $ticket = Ticket::create([
            'title' => $request->title,
            'priority' => $request->priority,
            'content' => $request->input('content'),
            'creator_id' => Auth::id(),
            'assigned_id' => $request->assigned_id,
            'due_date' => $request->due_date,
            'status' => $request->assigned_id ? 'En Proceso' : 'Nuevos',
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

        // Notify Admins
        $admins = User::role(['Administrador', 'Administrador Master'])->get();
        Notification::send($admins, new TicketNotification($ticket, 'created', 'Se ha creado un nuevo ticket: ' . $ticket->title));

        // Notify Assigned User
        if ($ticket->assigned_id && $ticket->assigned_id !== Auth::id()) {
            $ticket->assigned->notify(new TicketNotification($ticket, 'assigned', 'Te han asignado un nuevo ticket: ' . $ticket->title));
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
        $ticket->save();

        if ($oldStatus !== $ticket->status) {
            $message = "El ticket #{$ticket->id} cambió a: {$ticket->status}";

            // Notify participants
            if ($ticket->creator_id !== Auth::id()) {
                $ticket->creator->notify(new TicketNotification($ticket, 'status_changed', $message));
            }
            if ($ticket->assigned_id && $ticket->assigned_id !== Auth::id()) {
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
            $ticket->save();

            // Notify status change separately
            $statusMsg = "El ticket #{$ticket->id} cambió a: {$ticket->status}";
            if ($ticket->creator_id !== Auth::id()) {
                $ticket->creator->notify(new TicketNotification($ticket, 'status_changed', $statusMsg));
            }
            if ($ticket->assigned_id && $ticket->assigned_id !== Auth::id()) {
                $ticket->assigned->notify(new TicketNotification($ticket, 'status_changed', $statusMsg));
            }
        }

        $notifMessage = "Nuevo mensaje en ticket #{$ticket->id}: " . Auth::user()->name;

        // Notify about the message itself
        if ($ticket->creator_id !== Auth::id()) {
            $ticket->creator->notify(new TicketNotification($ticket, 'new_message', $notifMessage));
        }
        if ($ticket->assigned_id && $ticket->assigned_id !== Auth::id()) {
            $ticket->assigned->notify(new TicketNotification($ticket, 'new_message', $notifMessage));
        }

        return redirect()->back()->with('success', 'Mensaje enviado.');
    }

    public function show(Ticket $ticket)
    {
        $ticket->load(['creator', 'assigned', 'messages.user', 'attachments']);

        return Inertia::render('Tickets/Show', [
            'ticket' => $ticket,
            'assignableUsers' => User::role(['Administrador', 'Administrador Master', 'Web Developer', 'RRHH', 'Designer'])->get(),
        ]);
    }

    public function destroy(Ticket $ticket)
    {
        $user = Auth::user();

        // Admin can delete anytime
        $isAdmin = $user->hasAnyRole(['Administrador', 'Administrador Master']);

        // Client can only delete their own tickets AND if not assigned yet
        $isOwner = $ticket->creator_id === $user->id;
        $isNotAssigned = is_null($ticket->assigned_id);

        if ($isAdmin || ($isOwner && $isNotAssigned)) {
            $ticket->delete();
            return redirect()->route('tickets.index')->with('success', 'Ticket eliminado correctamente.');
        }

        return redirect()->back()->with('error', 'No tienes permisos para eliminar este ticket o ya ha sido asignado.');
    }
}
