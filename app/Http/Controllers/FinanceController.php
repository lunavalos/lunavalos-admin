<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Client;
use App\Models\ClientCost;
use App\Models\Employee;
use Carbon\Carbon;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\DB;

class FinanceController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Finanzas', only: ['index', 'receipt']),
            new Middleware('can:Editar Finanzas', only: ['sendReceipt']),
        ];
    }

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
            // No additional date filters
        } else {
            $query->whereBetween('next_renewal_date', [Carbon::today()->subDays(15), Carbon::today()->addDays(45)]);
        }

        $upcomingRenewals = $query->orderBy('next_renewal_date', 'asc')->get();

        $splitRenewals = [];

        foreach ($upcomingRenewals as $client) {
            if ($client->package_services) {
                $servicesList = array_map('trim', explode('+', $client->package_services));

                foreach ($servicesList as $sName) {
                    $srv = \App\Models\Service::where('name', $sName)->first();

                    $rowAmount = 0;
                    if ($srv) {
                        $rowAmount = ($srv->billing_type === 'monthly') ? $srv->price : $srv->renewal_price;
                    } else {
                        $rowAmount = count($servicesList) == 1 ? $client->renewal_amount : 0;
                    }

                    $splitRenewals[] = [
                        'client_id'        => $client->id,
                        'business_name'    => $client->business_name,
                        'contact_name'     => $client->contact_name,
                        'email'            => $client->email,
                        'service_name'     => collect([$sName])->filter()->first() ?: 'Servicio General',
                        'next_renewal_date'=> $client->next_renewal_date,
                        'renewal_amount'   => $rowAmount,
                        'billing_type'     => $srv ? $srv->billing_type : 'unique',
                    ];
                }
            } else {
                $splitRenewals[] = [
                    'client_id'        => $client->id,
                    'business_name'    => $client->business_name,
                    'contact_name'     => $client->contact_name,
                    'email'            => $client->email,
                    'service_name'     => 'Renovación de Cuenta',
                    'next_renewal_date'=> $client->next_renewal_date,
                    'renewal_amount'   => $client->renewal_amount,
                    'billing_type'     => 'unique',
                ];
            }
        }

        usort($splitRenewals, function ($a, $b) {
            return Carbon::parse($a['next_renewal_date'])->diffInDays(Carbon::parse($b['next_renewal_date']), false);
        });

        // ─── KPIs ────────────────────────────────────────────────────────────────
        $allClients = Client::whereNotNull('next_renewal_date')->get();

        $today        = Carbon::today();
        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        // Ingresos del mes en curso
        $incomeThisMonth = $allClients->filter(function ($c) use ($startOfMonth, $endOfMonth) {
            $d = Carbon::parse($c->next_renewal_date);
            return $d->between($startOfMonth, $endOfMonth);
        })->sum('renewal_amount');

        // Ingresos próximos 30 días
        $incomeNext30 = $allClients->filter(function ($c) use ($today) {
            $d = Carbon::parse($c->next_renewal_date);
            return $d->between($today, $today->copy()->addDays(30));
        })->sum('renewal_amount');

        // Clientes activos (renovación futura o presente)
        $activeClients = $allClients->filter(function ($c) use ($today) {
            return Carbon::parse($c->next_renewal_date)->gte($today);
        })->count();

        // Clientes vencidos
        $overdueClients = $allClients->filter(function ($c) use ($today) {
            return Carbon::parse($c->next_renewal_date)->lt($today);
        })->count();

        // Ingreso anual proyectado (anualizar mensuales x12, únicos x1)
        $annualProjected = 0;
        foreach ($allClients as $client) {
            if ($client->package_services) {
                $servicesList = array_map('trim', explode('+', $client->package_services));
                foreach ($servicesList as $sName) {
                    $srv = \App\Models\Service::where('name', $sName)->first();
                    if ($srv) {
                        $annualProjected += ($srv->billing_type === 'monthly')
                            ? $srv->price * 12
                            : $srv->renewal_price;
                    }
                }
            } else {
                $annualProjected += $client->renewal_amount ?? 0;
            }
        }

        // Costos internos totales anualizados (servicios contratados por cliente)
        $allCosts = ClientCost::all();
        $totalServiceCostsAnnual = $allCosts->sum(function ($cost) {
            return match ($cost->billing_frequency) {
                'monthly'  => $cost->amount * 12,
                'annual'   => $cost->amount,
                'one_time' => 0,
                default    => $cost->amount,
            };
        });

        // Nómina mensual de empleados activos
        $monthlyPayroll  = Employee::where('status', 'Activo')->sum('current_salary');
        $annualPayroll   = $monthlyPayroll * 12;

        $totalCostsAnnual   = $totalServiceCostsAnnual + $annualPayroll;
        $netProfitEstimated = $annualProjected - $totalCostsAnnual;

        // ─── GRÁFICA 1: Ingresos mensuales (últimos 12 meses + próximos 6) ──────
        $monthlyIncome = [];
        for ($i = -11; $i <= 5; $i++) {
            $month        = Carbon::now()->addMonths($i);
            $label        = ucfirst($month->translatedFormat('M Y'));
            $monthStart   = $month->copy()->startOfMonth();
            $monthEnd     = $month->copy()->endOfMonth();

            $income = $allClients->filter(function ($c) use ($monthStart, $monthEnd) {
                $d = Carbon::parse($c->next_renewal_date);
                return $d->between($monthStart, $monthEnd);
            })->sum('renewal_amount');

            $monthlyIncome[] = [
                'label'  => $label,
                'income' => round((float) $income, 2),
                'isPast' => $i < 0,
                'isCurrent' => $i === 0,
            ];
        }

        // ─── GRÁFICA 2: Distribución por servicio ────────────────────────────────
        $serviceDistribution = [];
        foreach ($allClients as $client) {
            if ($client->package_services) {
                $servicesList = array_map('trim', explode('+', $client->package_services));
                foreach ($servicesList as $sName) {
                    $srv       = \App\Models\Service::where('name', $sName)->first();
                    $amount    = 0;
                    if ($srv) {
                        $amount = ($srv->billing_type === 'monthly') ? $srv->price : $srv->renewal_price;
                    }
                    $key = trim($sName) ?: 'Sin servicio';
                    $serviceDistribution[$key] = ($serviceDistribution[$key] ?? 0) + $amount;
                }
            } else {
                $key = 'Renovación General';
                $serviceDistribution[$key] = ($serviceDistribution[$key] ?? 0) + ($client->renewal_amount ?? 0);
            }
        }
        arsort($serviceDistribution);

        // ─── GRÁFICA 3: Ingresos vs Costos por mes (últimos 12 meses) ────────────
        $costVsIncomeChart = [];
        for ($i = -11; $i <= 0; $i++) {
            $month      = Carbon::now()->addMonths($i);
            $label      = ucfirst($month->translatedFormat('M Y'));
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd   = $month->copy()->endOfMonth();

            $income = $allClients->filter(function ($c) use ($monthStart, $monthEnd) {
                $d = Carbon::parse($c->next_renewal_date);
                return $d->between($monthStart, $monthEnd);
            })->sum('renewal_amount');

            // Costos de servicios internos mensualizados
            $costsServices = $allCosts->sum(function ($cost) {
                return match ($cost->billing_frequency) {
                    'monthly'  => $cost->amount,
                    'annual'   => $cost->amount / 12,
                    default    => 0,
                };
            });

            $totalCosts = $costsServices + $monthlyPayroll;

            $costVsIncomeChart[] = [
                'label'         => $label,
                'income'        => round((float) $income, 2),
                'costs'         => round((float) $totalCosts, 2),
                'costsServices' => round((float) $costsServices, 2),
                'costsPayroll'  => round((float) $monthlyPayroll, 2),
                'profit'        => round((float) ($income - $totalCosts), 2),
            ];
        }

        // ─── GRÁFICA 4: Estado de clientes ───────────────────────────────────────
        $soonThreshold = Carbon::today()->addDays(30);
        $clientStatus  = [
            'Al corriente'       => $allClients->filter(fn($c) => Carbon::parse($c->next_renewal_date)->gt($soonThreshold))->count(),
            'Por vencer (30d)'   => $allClients->filter(fn($c) => Carbon::parse($c->next_renewal_date)->between($today, $soonThreshold))->count(),
            'Vencidos'           => $overdueClients,
        ];

        return Inertia::render('Finances/Index', [
            'upcomingRenewals'    => $splitRenewals,
            'currentRange'        => $range,
            'kpis'                => [
                'income_this_month'    => round((float) $incomeThisMonth, 2),
                'income_next_30'       => round((float) $incomeNext30, 2),
                'annual_projected'     => round((float) $annualProjected, 2),
                'active_clients'       => $activeClients,
                'overdue_clients'      => $overdueClients,
                'net_profit_estimated' => round((float) $netProfitEstimated, 2),
                'monthly_payroll'      => round((float) $monthlyPayroll, 2),
            ],
            'monthlyIncomeChart'  => $monthlyIncome,
            'serviceDistribution' => $serviceDistribution,
            'costVsIncomeChart'   => $costVsIncomeChart,
            'clientStatusChart'   => $clientStatus,
        ]);
    }

    public function receipt(Request $request, Client $client)
    {
        $serviceName   = $request->query('service', $client->package_services ?: 'Renovación de Cuenta');
        $serviceAmount = floatval($request->query('amount', $client->renewal_amount));
        $billingType   = $request->query('type', 'unique');

        $receiptData = [
            'client'       => $client,
            'service_name' => $serviceName,
            'amount'       => $serviceAmount,
            'billing_type' => $billingType,
        ];

        if (class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', $receiptData);
            return $pdf->stream('Recibo_Pago_' . $client->business_name . '.pdf');
        }

        return view('pdf.receipt', $receiptData);
    }

    public function sendReceipt(Request $request, Client $client)
    {
        if (!$client->email) {
            return back()->with('error', 'El cliente no tiene un correo electrónico configurado para enviar el recibo.');
        }

        $serviceName   = $request->input('service', $client->package_services ?: 'Renovación de Cuenta');
        $serviceAmount = floatval($request->input('amount', $client->renewal_amount));
        $billingType   = $request->input('type', 'unique');

        $receiptData = [
            'client'       => $client,
            'service_name' => $serviceName,
            'amount'       => $serviceAmount,
            'billing_type' => $billingType,
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
