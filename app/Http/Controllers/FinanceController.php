<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use Carbon\Carbon;
use Inertia\Inertia;

class FinanceController extends Controller
{
    public function index(Request $request)
    {
        $range = $request->query('range', 'default');
        
        $query = Client::whereNotNull('next_renewal_date');

        if ($range === 'this_month') {
            $query->whereMonth('next_renewal_date', Carbon::now()->month)
                  ->whereYear('next_renewal_date', Carbon::now()->year);
        } elseif ($range === 'next_30_days') {
            $query->whereBetween('next_renewal_date', [Carbon::today(), Carbon::today()->addDays(30)]);
        } elseif ($range === 'next_month') {
            $query->whereMonth('next_renewal_date', Carbon::now()->addMonth()->month)
                  ->whereYear('next_renewal_date', Carbon::now()->addMonth()->year);
        } elseif ($range === 'all') {
            // No additional date filters, just everything that has a next_renewal_date
        } else {
            // Default: roughly recent past and near future 
            $query->whereBetween('next_renewal_date', [Carbon::today()->subDays(15), Carbon::today()->addDays(45)]);
        }

        $upcomingRenewals = $query->orderBy('next_renewal_date', 'asc')->get();

        $splitRenewals = [];

        // Calculate dynamic payments based on text string (package_services)
        foreach ($upcomingRenewals as $client) {
            if ($client->package_services) {
                $servicesList = array_map('trim', explode('+', $client->package_services));
                
                foreach ($servicesList as $sName) {
                    $srv = \App\Models\Service::where('name', $sName)->first();
                    
                    $rowAmount = 0;
                    if ($srv) {
                        $rowAmount = ($srv->billing_type === 'monthly') ? $srv->price : $srv->renewal_price;
                    } else {
                        // If no service match, just carry over the basic renewal_amount
                        $rowAmount = count($servicesList) == 1 ? $client->renewal_amount : 0;
                    }

                    $splitRenewals[] = [
                        'client_id' => $client->id,
                        'business_name' => $client->business_name,
                        'contact_name' => $client->contact_name,
                        'email' => $client->email,
                        'service_name' => collect([$sName])->filter()->first() ?: 'Servicio General',
                        'next_renewal_date' => $client->next_renewal_date,
                        'renewal_amount' => $rowAmount,
                        'billing_type' => $srv ? $srv->billing_type : 'unique'
                    ];
                }
            } else {
                $splitRenewals[] = [
                    'client_id' => $client->id,
                    'business_name' => $client->business_name,
                    'contact_name' => $client->contact_name,
                    'email' => $client->email,
                    'service_name' => 'Renovación de Cuenta',
                    'next_renewal_date' => $client->next_renewal_date,
                    'renewal_amount' => $client->renewal_amount,
                    'billing_type' => 'unique'
                ];
            }
        }

        // Sort by date again after split
        usort($splitRenewals, function($a, $b) {
            return Carbon::parse($a['next_renewal_date'])->diffInDays(Carbon::parse($b['next_renewal_date']), false);
        });

        return Inertia::render('Finances/Index', [
            'upcomingRenewals' => $splitRenewals,
            'currentRange' => $range,
        ]);
    }

    public function receipt(Request $request, Client $client)
    {
        $serviceName = $request->query('service', $client->package_services ?: 'Renovación de Cuenta');
        $serviceAmount = floatval($request->query('amount', $client->renewal_amount));
        $billingType = $request->query('type', 'unique');

        $receiptData = [
            'client' => $client,
            'service_name' => $serviceName,
            'amount' => $serviceAmount,
            'billing_type' => $billingType
        ];

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', $receiptData);
            return $pdf->stream('Recibo_Pago_'.$client->business_name.'.pdf');
        }

        return view('pdf.receipt', $receiptData);
    }

    public function sendReceipt(Request $request, Client $client)
    {
        if (!$client->email) {
            return back()->with('error', 'El cliente no tiene un correo electrónico configurado para enviar el recibo.');
        }

        $serviceName = $request->input('service', $client->package_services ?: 'Renovación de Cuenta');
        $serviceAmount = floatval($request->input('amount', $client->renewal_amount));
        $billingType = $request->input('type', 'unique');

        $receiptData = [
            'client' => $client,
            'service_name' => $serviceName,
            'amount' => $serviceAmount,
            'billing_type' => $billingType
        ];

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'No se ha podido generar el PDF del recibo asociado.');
        }

        $pdfContent = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', $receiptData)->output();

        try {
            \Illuminate\Support\Facades\Mail::to($client->email)
                ->send(new \App\Mail\RenewalReceiptMail($client, $serviceName, $serviceAmount, $billingType, $pdfContent));
            
            return back()->with('message', '¡Recibo de ' . $serviceName . ' enviado a ' . $client->email . ' con éxito!');
        } catch (\Exception $e) {
            return back()->with('error', 'Fallo de conexión SMTP o al enviar: ' . $e->getMessage());
        }
    }
}
