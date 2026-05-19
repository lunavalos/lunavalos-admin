<?php

namespace App\Http\Controllers;

use App\Actions\Quotes\ConvertQuoteToContract;
use App\Http\Requests\ConvertQuoteRequest;
use App\Models\ClientPayment;
use App\Models\Quote;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Inertia\Inertia;

class QuoteManagementController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Cotizaciones',  only: ['show']),
            new Middleware('can:Crear Cotizaciones', only: ['convert']),
        ];
    }

    /**
     * Pantalla Inertia de gestión de una cotización post-creación.
     * Muestra detalles, estado, addons, totales fiscales y la acción de
     * "Cerrar venta → Contrato".
     */
    public function show(Quote $quote)
    {
        $quote->load([
            'addons.serviceAddon',
            'items',
            'package',
            'contract.payments' => function ($q) {
                $q->orderByRaw('COALESCE(paid_at, due_date) ASC');
            },
            'client',
        ]);

        return Inertia::render('Quotes/Manage', [
            'quote'        => $quote,
            'taxRegimes'   => config('sat.tax_regimes'),
            'paymentMethods' => ClientPayment::METHODS,
            'downPaymentPercent' => (int) config('quotes.down_payment_percent', 50),
            'canConvert'   => ! $quote->legacy
                && $quote->status === 'Aceptada'
                && ! $quote->contract,
        ]);
    }

    /**
     * Ejecuta la conversión cotización -> contrato (con cliente, usuario y anticipo).
     */
    public function convert(ConvertQuoteRequest $request, Quote $quote, ConvertQuoteToContract $action)
    {
        try {
            $contract = $action($quote, $request->validated(), $request->user());
        } catch (\DomainException $e) {
            return back()->withErrors(['conversion' => $e->getMessage()]);
        }

        return redirect()
            ->route('quotes.manage', $quote)
            ->with('success', "Venta cerrada. Contrato {$contract->contract_number} creado.");
    }
}
