<?php

namespace App\Http\Controllers;

use App\Models\Ticket;
use App\Models\TicketCanvasItem;
use App\Models\TicketCanvasPin;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class TicketCanvasController extends Controller
{
    private function denyClient(): void
    {
        /** @var User|null $user */
        $user = Auth::user();
        if ($user?->hasRole('Cliente')) {
            abort(403, 'Los clientes solo pueden aprobar y comentar el lienzo.');
        }
    }

    public function store(Request $request, Ticket $ticket)
    {
        $this->denyClient();
        $data = $request->validate([
            'caption'   => 'nullable|string|max:255',
            'url'       => 'nullable|url|max:2048',
            'file'      => 'nullable|file|max:102400', // 100 MB for video
            'parent_id' => 'nullable|integer|exists:ticket_canvas_items,id',
        ]);

        if (!$request->hasFile('file') && empty($data['url'])) {
            return back()->withErrors(['file' => 'Sube un archivo o pega una URL.']);
        }

        $parentId = $data['parent_id'] ?? null;
        // Si parent, validar que pertenezca al ticket
        if ($parentId) {
            $parent = TicketCanvasItem::where('id', $parentId)->where('ticket_id', $ticket->id)->first();
            if (!$parent) {
                return back()->withErrors(['parent_id' => 'Frame padre inválido.']);
            }
        }

        $item = new TicketCanvasItem([
            'ticket_id'      => $ticket->id,
            'parent_id'      => $parentId,
            'caption'        => $data['caption'] ?? null,
            'position'       => $parentId
                ? 0
                : (($ticket->canvasItems()->max('position') ?? -1) + 1),
            'stack_position' => $parentId
                ? (TicketCanvasItem::where('parent_id', $parentId)->max('stack_position') + 1)
                : 0,
            'uploaded_by'    => Auth::id(),
        ]);

        if ($request->hasFile('file')) {
            $file = $request->file('file');
            $mime = $file->getMimeType();
            $item->file_path = $file->store('ticket-canvas/' . $ticket->id, 'public');
            $item->file_name = $file->getClientOriginalName();
            $item->mime      = $mime;
            $item->type      = $this->detectType($mime);
        } else {
            $item->url  = $data['url'];
            $item->type = 'url';
        }

        $item->save();

        return back()->with('success', 'Frame agregado al lienzo.');
    }

    public function update(Request $request, TicketCanvasItem $item)
    {
        $data = $request->validate([
            'caption'         => 'nullable|string|max:255',
            'approval_status' => 'nullable|string|in:pending,approved,changes_requested',
            'approval_note'   => 'nullable|string|max:2000',
        ]);

        // Clientes solo pueden mover el estado de aprobación / nota; no editar caption.
        /** @var User|null $user */
        $user = Auth::user();
        if ($user?->hasRole('Cliente')) {
            unset($data['caption']);
        }

        $item->fill(array_filter($data, fn ($v) => $v !== null));
        $item->save();

        return back()->with('success', 'Frame actualizado.');
    }

    public function reorder(Request $request, Ticket $ticket)
    {
        $this->denyClient();
        $data = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:ticket_canvas_items,id',
        ]);

        foreach ($data['order'] as $position => $id) {
            TicketCanvasItem::where('id', $id)
                ->where('ticket_id', $ticket->id)
                ->update(['position' => $position]);
        }

        return back();
    }

    public function reorderStack(Request $request, TicketCanvasItem $item)
    {
        $this->denyClient();
        $data = $request->validate([
            'order'   => 'required|array',
            'order.*' => 'integer|exists:ticket_canvas_items,id',
        ]);

        foreach ($data['order'] as $position => $id) {
            TicketCanvasItem::where('id', $id)
                ->where('parent_id', $item->id)
                ->update(['stack_position' => $position]);
        }

        return back();
    }

    public function destroy(TicketCanvasItem $item)
    {
        $this->denyClient();
        // Borrar archivos de hijos en disco (cascade en DB se encarga de las filas)
        foreach ($item->children()->whereNotNull('file_path')->pluck('file_path') as $childPath) {
            Storage::disk('public')->delete($childPath);
        }
        if ($item->file_path) {
            Storage::disk('public')->delete($item->file_path);
        }
        $item->delete();

        return back()->with('success', 'Frame eliminado.');
    }

    public function addPin(Request $request, TicketCanvasItem $item)
    {
        $data = $request->validate([
            'x_pct'   => 'required|numeric|between:0,100',
            'y_pct'   => 'required|numeric|between:0,100',
            'comment' => 'required|string|max:2000',
        ]);

        TicketCanvasPin::create(array_merge($data, [
            'canvas_item_id' => $item->id,
            'user_id'        => Auth::id(),
        ]));

        return back()->with('success', 'Comentario agregado.');
    }

    public function togglePin(TicketCanvasPin $pin)
    {
        $pin->resolved = !$pin->resolved;
        $pin->save();
        return back();
    }

    public function destroyPin(TicketCanvasPin $pin)
    {
        // Cliente solo puede eliminar sus propios pines.
        /** @var User|null $user */
        $user = Auth::user();
        if ($user?->hasRole('Cliente') && $pin->user_id !== Auth::id()) {
            abort(403);
        }
        $pin->delete();
        return back();
    }

    private function detectType(?string $mime): string
    {
        if (!$mime) return 'image';
        if (str_starts_with($mime, 'image/')) return 'image';
        if (str_starts_with($mime, 'video/')) return 'video';
        if ($mime === 'application/pdf') return 'pdf';
        return 'image';
    }
}
