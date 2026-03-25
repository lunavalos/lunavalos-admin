<?php

namespace App\Http\Controllers;

use App\Models\Quote;
use App\Models\QuoteItem;
use App\Models\Service;
use App\Models\Client;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Illuminate\Support\Facades\DB;

class QuoteController extends Controller
{
    public function index()
    {
        $quotes = Quote::with(['items.costs', 'contract', 'client'])->orderBy('created_at', 'desc')->get();
        return Inertia::render('Quotes/Index', [
            'quotes' => $quotes
        ]);
    }

    public function create()
    {
        // Traemos todos los servicios y sus sub-servicios en caso de ser paquete
        $services = Service::with('costs')->get();
        $clients = Client::select('id', 'business_name', 'contact_name', 'email', 'phone')->get();

        return Inertia::render('Quotes/Create', [
            'services' => $services,
            'clients' => $clients
        ]);
    }

    public function store(Request $request)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:Pendiente,Aceptada,Rechazada,Completada,Contrato Firmado',
            'issue_date' => 'required|date|before_or_equal:today',
            'valid_until' => 'nullable|date|after_or_equal:issue_date',
            'duration' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'include_payment_terms' => 'boolean',
            'is_multiple_choice' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.concept' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.billing_type' => 'required|in:unique,monthly,annual',
            'items.*.service_id' => 'nullable|exists:services,id',
            'items.*.costs' => 'nullable|array',
            'items.*.costs.*.title' => 'required|string',
            'items.*.costs.*.quantity' => 'required|integer|min:1',
            'items.*.costs.*.price' => 'required|numeric|min:0',
        ]);

        $quote = DB::transaction(function () use ($request) {
            $quote = Quote::create([
                'client_name' => $request->client_name,
                'contact_name' => $request->contact_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'status' => $request->status ?? 'Pendiente',
                'issue_date' => $request->issue_date,
                'valid_until' => $request->valid_until,
                'duration' => $request->duration,
                'notes' => $request->notes,
                'include_payment_terms' => $request->include_payment_terms ?? false,
                'is_multiple_choice' => $request->is_multiple_choice ?? false,
            ]);

            foreach ($request->items as $item) {
                $quoteItem = QuoteItem::create([
                    'quote_id' => $quote->id,
                    'concept' => $item['concept'],
                    'description' => $item['description'] ?? null,
                    'quantity' => $item['quantity'],
                    'unit_price' => $item['unit_price'],
                    'billing_type' => $item['billing_type'],
                    'service_id' => $item['service_id'] ?? null,
                ]);

                if (isset($item['costs']) && is_array($item['costs'])) {
                    foreach ($item['costs'] as $cost) {
                        $quoteItem->costs()->create([
                            'title' => $cost['title'],
                            'quantity' => $cost['quantity'],
                            'price' => $cost['price'],
                        ]);
                    }
                }
            }

            return $quote;
        });

        return redirect()->route('quotes.edit', $quote->id)->with('message', 'Cotización generada exitosamente. Aquí puedes ver el PDF.');
    }

    public function show(Quote $quote)
    {
        $quote->load('items');

        $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.quote', compact('quote'));

        return $pdf->stream('Cotizacion_LunAvalos_' . $quote->id . '.pdf');
    }

    public function edit(Quote $quote)
    {
        $quote->load('items.costs');
        $services = Service::with('costs')->get();
        $clients = Client::select('id', 'business_name', 'contact_name', 'email', 'phone')->get();

        return Inertia::render('Quotes/Edit', [
            'quote' => $quote,
            'services' => $services,
            'clients' => $clients
        ]);
    }

    public function update(Request $request, Quote $quote)
    {
        $request->validate([
            'client_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'phone' => 'nullable|string|max:50',
            'email' => 'nullable|email|max:255',
            'status' => 'nullable|string|in:Pendiente,Aceptada,Rechazada,Completada,Contrato Firmado',
            'issue_date' => 'required|date|before_or_equal:today',
            'valid_until' => 'nullable|date|after_or_equal:issue_date',
            'duration' => 'nullable|string|max:255',
            'notes' => 'nullable|string',
            'include_payment_terms' => 'boolean',
            'is_multiple_choice' => 'boolean',
            'items' => 'required|array|min:1',
            'items.*.id' => 'nullable|exists:quote_items,id',
            'items.*.concept' => 'required|string|max:255',
            'items.*.description' => 'nullable|string',
            'items.*.quantity' => 'required|integer|min:1',
            'items.*.unit_price' => 'required|numeric|min:0',
            'items.*.billing_type' => 'required|in:unique,monthly,annual',
            'items.*.service_id' => 'nullable|exists:services,id',
            'items.*.costs' => 'nullable|array',
            'items.*.costs.*.title' => 'required|string',
            'items.*.costs.*.quantity' => 'required|integer|min:1',
            'items.*.costs.*.price' => 'required|numeric|min:0',
        ]);

        DB::transaction(function () use ($request, $quote) {
            $quote->update([
                'client_name' => $request->client_name,
                'contact_name' => $request->contact_name,
                'phone' => $request->phone,
                'email' => $request->email,
                'status' => $request->status ?? 'Pendiente',
                'issue_date' => $request->issue_date,
                'valid_until' => $request->valid_until,
                'duration' => $request->duration,
                'notes' => $request->notes,
                'include_payment_terms' => $request->include_payment_terms ?? false,
                'is_multiple_choice' => $request->is_multiple_choice ?? false,
            ]);

            // Eliminar items que no vengan en el Request nuevo
            $currentItemsIds = collect($request->items)->pluck('id')->filter()->toArray();
            $quote->items()->whereNotIn('id', $currentItemsIds)->delete();

            // Actualizar o Crear los nuevos Items
            foreach ($request->items as $item) {
                if (isset($item['id'])) {
                    $quoteItem = QuoteItem::find($item['id']);
                    $quoteItem->update([
                        'concept' => $item['concept'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'billing_type' => $item['billing_type'],
                        'service_id' => $item['service_id'] ?? null,
                    ]);

                    // Sync costs
                    $quoteItem->costs()->delete();
                    if (isset($item['costs']) && is_array($item['costs'])) {
                        foreach ($item['costs'] as $cost) {
                            $quoteItem->costs()->create([
                                'title' => $cost['title'],
                                'quantity' => $cost['quantity'],
                                'price' => $cost['price'],
                            ]);
                        }
                    }
                } else {
                    $quoteItem = QuoteItem::create([
                        'quote_id' => $quote->id,
                        'concept' => $item['concept'],
                        'description' => $item['description'] ?? null,
                        'quantity' => $item['quantity'],
                        'unit_price' => $item['unit_price'],
                        'billing_type' => $item['billing_type'],
                        'service_id' => $item['service_id'] ?? null,
                    ]);

                    if (isset($item['costs']) && is_array($item['costs'])) {
                        foreach ($item['costs'] as $cost) {
                            $quoteItem->costs()->create([
                                'title' => $cost['title'],
                                'quantity' => $cost['quantity'],
                                'price' => $cost['price'],
                            ]);
                        }
                    }
                }
            }
        });

        return redirect()->route('quotes.index')->with('message', 'Cotización actualizada exitosamente.');
    }

    public function updateStatus(Request $request, Quote $quote)
    {
        $request->validate([
            'status' => 'required|in:Pendiente,Aceptada,Rechazada,Completada,Contrato Firmado'
        ]);

        $quote->update(['status' => $request->status]);

        return redirect()->back()->with('message', 'Estado de la cotización actualizado.');
    }

    public function destroy(Quote $quote)
    {
        $quote->delete();

        return redirect()->route('quotes.index')->with('message', 'Cotización eliminada exitosamente.');
    }
}
