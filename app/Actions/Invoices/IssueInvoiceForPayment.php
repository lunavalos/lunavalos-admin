<?php

namespace App\Actions\Invoices;

use App\Models\ClientPayment;
use App\Models\Invoice;
use App\Models\User;
use App\Services\Facturama\FacturamaClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * Emite un CFDI 4.0 de Ingreso (PUE) a partir de un ClientPayment.
 * Persiste el resultado en `invoices` y guarda XML/PDF en el disco 'public'.
 */
class IssueInvoiceForPayment
{
    public function __construct(protected FacturamaClient $facturama) {}

    public function __invoke(ClientPayment $payment, ?User $actor = null): Invoice
    {
        if ($payment->status === 'cancelado') {
            throw new \DomainException('No se puede timbrar un pago cancelado.');
        }
        if (Invoice::where('client_payment_id', $payment->id)->where('status', 'issued')->exists()) {
            throw new \DomainException('Este pago ya tiene un CFDI emitido vigente.');
        }

        $client = $payment->client;
        if (! $client || ! $client->rfc || ! $client->tax_zip_code || ! $client->tax_regime) {
            throw new \DomainException('El cliente no tiene datos fiscales completos (RFC, código postal, régimen).');
        }

        $payload = $this->buildPayload($payment, $client);

        return DB::transaction(function () use ($payment, $client, $payload, $actor) {
            try {
                $response = $this->facturama->issueCfdi($payload);
            } catch (\Throwable $e) {
                Invoice::create([
                    'client_id'         => $client->id,
                    'contract_id'       => $payment->contract_id,
                    'client_payment_id' => $payment->id,
                    'issued_by_user_id' => $actor?->id,
                    'cfdi_type'         => 'I',
                    'payment_method'    => 'PUE',
                    'currency'          => $payment->currency ?? 'MXN',
                    'subtotal'          => $payload['Items'][0]['Subtotal'] ?? 0,
                    'taxes'             => $payload['Items'][0]['TaxObject'] === '02'
                        ? array_sum(array_column($payload['Items'][0]['Taxes'] ?? [], 'Total'))
                        : 0,
                    'total'             => $payload['Items'][0]['Total'] ?? $payment->amount,
                    'status'            => 'error',
                    'request_snapshot'  => $payload,
                    'error_message'     => substr($e->getMessage(), 0, 1000),
                ]);
                throw $e;
            }

            // Persistir XML + PDF.
            $facturamaId = $response['Id'] ?? null;
            $uuid        = $response['Complement']['TaxStamp']['Uuid'] ?? ($response['Uuid'] ?? null);

            $xmlPath = null;
            $pdfPath = null;

            if ($facturamaId) {
                try {
                    $xml = $this->facturama->downloadXml($facturamaId);
                    $pdf = $this->facturama->downloadPdf($facturamaId);
                    $dir = "invoices/{$client->id}";
                    $xmlPath = "{$dir}/{$facturamaId}.xml";
                    $pdfPath = "{$dir}/{$facturamaId}.pdf";
                    Storage::disk('public')->put($xmlPath, $xml);
                    Storage::disk('public')->put($pdfPath, $pdf);
                } catch (\Throwable $e) {
                    // No abortamos si solo falla la descarga; queda el id Facturama.
                }
            }

            $invoice = Invoice::create([
                'client_id'         => $client->id,
                'contract_id'       => $payment->contract_id,
                'client_payment_id' => $payment->id,
                'issued_by_user_id' => $actor?->id,
                'facturama_id'      => $facturamaId,
                'uuid'              => $uuid,
                'series'            => $response['Serie']  ?? null,
                'folio'             => $response['Folio']  ?? null,
                'cfdi_type'         => 'I',
                'payment_method'    => 'PUE',
                'payment_form'      => $payload['PaymentForm'] ?? null,
                'cfdi_use'          => $payload['CfdiUse'] ?? null,
                'currency'          => $payload['Currency'] ?? 'MXN',
                'subtotal'          => $response['Subtotal'] ?? $payload['Items'][0]['Subtotal'],
                'discount'          => $response['Discount'] ?? 0,
                'taxes'             => array_sum(array_column($response['Complement']['TaxStamp'] ?? [], 'TotalTaxes')) ?: ($payload['Items'][0]['Total'] - $payload['Items'][0]['Subtotal']),
                'total'             => $response['Total'] ?? $payment->amount,
                'status'            => 'issued',
                'issued_at'         => now(),
                'xml_path'          => $xmlPath,
                'pdf_path'          => $pdfPath,
                'request_snapshot'  => $payload,
                'response_snapshot' => $response,
            ]);

            // Marcar el pago como facturado.
            $payment->update(['status' => 'facturado']);

            return $invoice;
        });
    }

    protected function buildPayload(ClientPayment $payment, $client): array
    {
        $issuer    = config('facturama.issuer');
        $defaults  = config('facturama.defaults');
        $amount    = (float) $payment->amount;

        // Reverso del IVA 16%: subtotal = total / 1.16, IVA = total - subtotal.
        $subtotal = round($amount / 1.16, 2);
        $iva      = round($amount - $subtotal, 2);

        return [
            'NameId'        => '1',                 // 1 = Factura
            'CfdiType'      => 'I',                 // Ingreso
            'PaymentMethod' => 'PUE',
            'PaymentForm'   => $this->mapPaymentForm($payment->payment_method),
            'Currency'      => $payment->currency ?: 'MXN',
            'ExpeditionPlace' => $issuer['tax_zip_code'] ?? '00000',
            'Folio'         => 'P' . $payment->id,
            'CfdiUse'       => $client->cfdi_use ?? 'G03',

            'Issuer' => [
                'Rfc'              => $issuer['rfc'],
                'Name'             => $issuer['name'],
                'FiscalRegime'     => $issuer['fiscal_regime'] ?? '601',
            ],

            'Receiver' => [
                'Rfc'              => strtoupper($client->rfc),
                'Name'             => strtoupper($client->legal_name ?? $client->business_name),
                'CfdiUse'          => $client->cfdi_use ?? 'G03',
                'FiscalRegime'     => (string) $client->tax_regime,
                'TaxZipCode'       => $client->tax_zip_code,
            ],

            'Items' => [[
                'ProductCode'   => $defaults['product_code'],
                'IdentificationNumber' => substr('PAY-' . $payment->id, 0, 100),
                'Description'   => $payment->concept,
                'Unit'          => $defaults['unit'],
                'UnitCode'      => $defaults['unit_code'],
                'UnitPrice'     => $subtotal,
                'Quantity'      => 1,
                'Subtotal'      => $subtotal,
                'TaxObject'     => '02',
                'Taxes' => [[
                    'Total'        => $iva,
                    'Name'         => 'IVA',
                    'Base'         => $subtotal,
                    'Rate'         => 0.16,
                    'IsRetention'  => false,
                ]],
                'Total'         => $amount,
            ]],
        ];
    }

    /**
     * Mapea nuestros métodos internos al catálogo SAT c_FormaPago.
     */
    protected function mapPaymentForm(?string $method): string
    {
        return match ($method) {
            'efectivo'      => '01',
            'cheque'        => '02',
            'transferencia' => '03',
            'tarjeta'       => '04',
            default         => '03',
        };
    }
}
