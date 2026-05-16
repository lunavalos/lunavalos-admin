<?php

namespace App\Http\Controllers;

use App\Actions\Invoices\IssueInvoiceForPayment;
use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Services\Facturama\FacturamaClient;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class InvoiceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Facturas',    only: ['index', 'download']),
            new Middleware('can:Emitir Facturas', only: ['issueForPayment', 'cancel']),
        ];
    }

    public function index(Request $request)
    {
        $invoices = Invoice::query()
            ->with(['client:id,business_name', 'payment:id,concept'])
            ->when($request->status, fn ($q, $v) => $q->where('status', $v))
            ->when($request->q,      fn ($q, $v) => $q->where(fn ($w) => $w->where('uuid', 'like', "%{$v}%")->orWhere('folio', 'like', "%{$v}%")))
            ->orderByDesc('issued_at')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('Invoices/Index', [
            'invoices' => $invoices,
            'filters'  => $request->only(['status', 'q']),
            'configured' => app(FacturamaClient::class)->isConfigured(),
        ]);
    }

    public function issueForPayment(Request $request, ClientPayment $payment, IssueInvoiceForPayment $action)
    {
        try {
            $invoice = $action($payment, $request->user());
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        return back()->with('success', "CFDI emitido: UUID {$invoice->uuid}");
    }

    public function cancel(Request $request, Invoice $invoice, FacturamaClient $facturama)
    {
        if ($invoice->status !== 'issued' || ! $invoice->facturama_id) {
            return back()->withErrors(['invoice' => 'No se puede cancelar esta factura.']);
        }

        $motive = $request->input('motive', '02');

        try {
            $result = $facturama->cancelCfdi($invoice->facturama_id, $motive);
        } catch (\Throwable $e) {
            return back()->withErrors(['invoice' => $e->getMessage()]);
        }

        $invoice->update([
            'status'              => 'canceled',
            'cancellation_status' => $result['Status'] ?? 'requested',
            'canceled_at'         => now(),
            'response_snapshot'   => array_merge($invoice->response_snapshot ?? [], ['cancellation' => $result]),
        ]);

        return back()->with('success', 'Solicitud de cancelación enviada al SAT.');
    }

    public function download(Invoice $invoice, string $type)
    {
        $path = $type === 'pdf' ? $invoice->pdf_path : $invoice->xml_path;
        abort_unless($path && Storage::disk('public')->exists($path), 404);

        return Storage::disk('public')->download(
            $path,
            "CFDI-{$invoice->uuid}.{$type}"
        );
    }
}
