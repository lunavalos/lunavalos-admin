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

    /**
     * Para servicios mensuales, la renewal_date almacena la fecha límite anual del contrato.
     * El cobro ocurre cada mes en el mismo día (día de renewal_date), hasta llegar a esa fecha.
     * Este helper calcula la próxima fecha de cobro mensual a partir de hoy.
     */
    private function getNextMonthlyOccurrence(\Carbon\Carbon $renewalDate): ?\Carbon\Carbon
    {
        $today       = Carbon::today();
        $dayOfMonth  = (int) $renewalDate->format('d');

        // Si el contrato anual ya venció, no hay próxima ocurrencia
        if ($renewalDate->lt($today)) {
            return null;
        }

        // Intentar en el mes actual
        $candidate = Carbon::today()->startOfMonth()->addDays($dayOfMonth - 1);
        // Si ya pasó en este mes, saltar al próximo
        if ($candidate->lt($today)) {
            $candidate->addMonth();
        }
        // Asegurarse de que la ocurrencia no supere la fecha de renovación anual
        if ($candidate->gt($renewalDate)) {
            return $renewalDate;
        }

        return $candidate;
    }

    public function index(Request $request)
    {
        $range = $request->query('range', 'default');
        $today = Carbon::today();

        // Cargar todos los servicios activos con renewal_date para procesarlos en PHP
        // (los mensuales necesitan cálculo de próxima ocurrencia, no filtro SQL directo)
        $allActiveServices = \App\Models\ClientService::
            with('client')
            ->whereNotNull('renewal_date')
            ->where('status', 'active')
            ->whereHas('client', fn($q) => $q->where('is_historical', false))
            ->get();

        // Enriquecer cada servicio con su próxima fecha de cobro efectiva
        $enriched = $allActiveServices->map(function ($cs) {
            $next = null;
            if ($cs->billing_type === 'monthly') {
                $next = $this->getNextMonthlyOccurrence($cs->renewal_date);
            } else {
                $next = $cs->renewal_date;
            }
            $cs->next_billing_date = $next; // propiedad virtual
            return $cs;
        })->filter(fn($cs) => $cs->next_billing_date !== null);

        // Filtrar según el rango solicitado
        if ($range === 'this_month') {
            $start = Carbon::now()->startOfMonth();
            $end   = Carbon::now()->endOfMonth();
            $filtered = $enriched->filter(fn($cs) => $cs->next_billing_date->between($start, $end));
        } elseif ($range === 'next_30_days') {
            $filtered = $enriched->filter(fn($cs) => $cs->next_billing_date->between($today, $today->copy()->addDays(30)));
        } elseif ($range === 'next_month') {
            $start = Carbon::now()->addMonth()->startOfMonth();
            $end   = Carbon::now()->addMonth()->endOfMonth();
            $filtered = $enriched->filter(fn($cs) => $cs->next_billing_date->between($start, $end));
        } elseif ($range === 'all') {
            $filtered = $enriched;
        } else {
            // Default: -15 días a +45 días
            $filtered = $enriched->filter(fn($cs) => $cs->next_billing_date->between($today->copy()->subDays(15), $today->copy()->addDays(45)));
        }

        $upcomingServices = $filtered->sortBy(fn($cs) => $cs->next_billing_date)->values();

        $splitRenewals = $upcomingServices->map(function ($cs) {
            return [
                'client_id'        => $cs->client_id,
                'business_name'    => $cs->client->business_name ?? 'N/A',
                'contact_name'     => $cs->client->contact_name ?? 'N/A',
                'email'            => $cs->client->email ?? 'N/A',
                'service_name'     => $cs->service_name,
                'next_renewal_date'=> $cs->next_billing_date,
                'renewal_amount'   => (float)$cs->renewal_amount,
                'billing_type'     => $cs->billing_type ?? 'renewable',
            ];
        })->toArray();

        // ─── KPIs ────────────────────────────────────────────────────────────────
        // Reutilizamos $enriched que ya tiene next_billing_date calculado
        $allServices  = $enriched; // colección ya enriquecida con next_billing_date

        $startOfMonth = Carbon::now()->startOfMonth();
        $endOfMonth   = Carbon::now()->endOfMonth();

        // Ingresos del mes en curso (usando próxima fecha de cobro efectiva)
        $incomeThisMonth = $allServices->filter(function ($s) use ($startOfMonth, $endOfMonth) {
            return $s->next_billing_date->between($startOfMonth, $endOfMonth);
        })->sum('renewal_amount');

        // Ingresos próximos 30 días
        $incomeNext30 = $allServices->filter(function ($s) use ($today) {
            return $s->next_billing_date->between($today, $today->copy()->addDays(30));
        })->sum('renewal_amount');

        // Servicios activos (próxima fecha de cobro futura o presente)
        $activeServicesCount = $allServices->filter(function ($s) use ($today) {
            return $s->next_billing_date->gte($today);
        })->count();

        // Servicios vencidos (aquellos cuya renewal_date original ya pasó y no son mensuales)
        $overdueServicesCount = \App\Models\ClientService::whereNotNull('renewal_date')
            ->where('status', 'active')
            ->where('billing_type', '!=', 'monthly')
            ->where('renewal_date', '<', $today)
            ->whereHas('client', fn($q) => $q->where('is_historical', false))
            ->count();

        // Ingreso anual proyectado basado en frecuencia
        $annualProjected = $allServices->sum(function ($s) {
            return match ($s->billing_type) {
                'monthly' => (float)$s->renewal_amount * 12,
                'annual'  => (float)$s->renewal_amount,
                'once'    => 0, // Pagos únicos no son recurrentes proyectados
                default   => (float)$s->renewal_amount,
            };
        });

        // Costos internos totales anualizados
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
        // Para la gráfica necesitamos considerar que los servicios mensuales
        // tienen un cobro por cada mes dentro del rango (hasta renewal_date)
        $allRawServices = \App\Models\ClientService::whereNotNull('renewal_date')
            ->where('status', 'active')
            ->whereHas('client', fn($q) => $q->where('is_historical', false))
            ->get();

        $monthlyIncome = [];
        for ($i = -11; $i <= 5; $i++) {
            $month        = Carbon::now()->addMonths($i);
            $label        = ucfirst($month->translatedFormat('M Y'));
            $monthStart   = $month->copy()->startOfMonth();
            $monthEnd     = $month->copy()->endOfMonth();

            $income = $allRawServices->sum(function ($s) use ($monthStart, $monthEnd) {
                if ($s->billing_type === 'monthly') {
                    // El servicio mensual cobra este mes si:
                    // 1. El contrato (renewal_date) aún no ha vencido al inicio del mes
                    // 2. El mes está dentro del periodo del contrato
                    $dayOfMonth = (int) $s->renewal_date->format('d');
                    $billingDay = $monthStart->copy()->addDays($dayOfMonth - 1);
                    // Ajustar si el día no existe en ese mes
                    if ($billingDay->month !== $monthStart->month) {
                        $billingDay = $monthEnd->copy();
                    }
                    if ($billingDay->between($monthStart, $monthEnd) && $billingDay->lte($s->renewal_date)) {
                        return (float) $s->renewal_amount;
                    }
                    return 0;
                }
                // Para servicios no mensuales, usar renewal_date directamente
                return $s->renewal_date->between($monthStart, $monthEnd) ? (float) $s->renewal_amount : 0;
            });

            $monthlyIncome[] = [
                'label'     => $label,
                'income'    => round((float) $income, 2),
                'isPast'    => $i < 0,
                'isCurrent' => $i === 0,
            ];
        }

        // ─── GRÁFICA 2: Distribución por servicio ────────────────────────────────
        $serviceDistribution = $allServices->groupBy('service_name')
            ->map(function ($group) {
                return $group->sum('renewal_amount');
            })->toArray();
        arsort($serviceDistribution);

        // ─── GRÁFICA 3: Ingresos vs Costos por mes (últimos 12 meses) ────────────
        $costVsIncomeChart = [];
        for ($i = -11; $i <= 0; $i++) {
            $month      = Carbon::now()->addMonths($i);
            $label      = ucfirst($month->translatedFormat('M Y'));
            $monthStart = $month->copy()->startOfMonth();
            $monthEnd   = $month->copy()->endOfMonth();

            $income = $allRawServices->sum(function ($s) use ($monthStart, $monthEnd) {
                if ($s->billing_type === 'monthly') {
                    $dayOfMonth = (int) $s->renewal_date->format('d');
                    $billingDay = $monthStart->copy()->addDays($dayOfMonth - 1);
                    if ($billingDay->month !== $monthStart->month) {
                        $billingDay = $monthEnd->copy();
                    }
                    if ($billingDay->between($monthStart, $monthEnd) && $billingDay->lte($s->renewal_date)) {
                        return (float) $s->renewal_amount;
                    }
                    return 0;
                }
                return $s->renewal_date->between($monthStart, $monthEnd) ? (float) $s->renewal_amount : 0;
            });

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

        // ─── GRÁFICA 4: Estado de servicios ───────────────────────────────────────
        $soonThreshold = Carbon::today()->addDays(30);
        $clientStatus  = [
            'Al corriente'       => $allServices->filter(fn($s) => $s->next_billing_date->gt($soonThreshold))->count(),
            'Por vencer (30d)'   => $allServices->filter(fn($s) => $s->next_billing_date->between($today, $soonThreshold))->count(),
            'Vencidos'           => $overdueServicesCount,
        ];

        return Inertia::render('Finances/Index', [
            'upcomingRenewals'    => $splitRenewals,
            'currentRange'        => $range,
            'kpis'                => [
                'income_this_month'    => round((float) $incomeThisMonth, 2),
                'income_next_30'       => round((float) $incomeNext30, 2),
                'annual_projected'     => round((float) $annualProjected, 2),
                'active_clients'       => $activeServicesCount, // Keep the key name for frontend compatibility
                'overdue_clients'      => $overdueServicesCount,
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
        $groupByDate = filter_var($request->query('group_by_date', false), FILTER_VALIDATE_BOOLEAN);
        $groupDate   = $request->query('date');

        if ($groupByDate && $groupDate) {
            $services = $this->findServicesForDateGroup($client, $groupDate);
            $total     = $services->sum('renewal_amount');

            $receiptData = [
                'client'       => $client,
                'services'     => $services->toArray(),
                'total_amount' => $total,
                'group_date'   => $groupDate,
                'receipt_date' => $groupDate,
            ];
        } else {
            $serviceName   = $request->query('service', $client->package_services ?: 'Renovación de Cuenta');
            $serviceAmount = floatval($request->query('amount', $client->renewal_amount));
            $billingType   = $request->query('type', 'unique');

            $clientService = \App\Models\ClientService::with('service')
                ->where('client_id', $client->id)
                ->where('service_name', $serviceName)
                ->first();
            $serviceDescription = $clientService?->service?->description ?? null;
            $receiptDate = $clientService ? $this->getNextBillingDate($clientService) : ($request->query('date') ? \Carbon\Carbon::parse($request->query('date')) : now());

            $receiptData = [
                'client'              => $client,
                'service_name'        => $serviceName,
                'amount'              => $serviceAmount,
                'billing_type'        => $billingType,
                'service_description' => $serviceDescription,
                'receipt_date'       => $receiptDate?->format('Y-m-d'),
            ];
        }

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

        $groupByDate = filter_var($request->input('group_by_date', false), FILTER_VALIDATE_BOOLEAN);
        $groupDate   = $request->input('date');
        $serviceAmount = 0;

        if ($groupByDate && $groupDate) {
            $services      = $this->findServicesForDateGroup($client, $groupDate);
            $totalAmount   = $services->sum('renewal_amount');
            $serviceLabel  = 'Servicio(s) de renovación ' . \Carbon\Carbon::parse($groupDate)->translatedFormat('d / m / Y');
            $billingType   = 'grouped';

            $receiptData = [
                'client'       => $client,
                'services'     => $services->toArray(),
                'total_amount' => $totalAmount,
                'group_date'   => $groupDate,
                'grouped'      => true,
                'receipt_date' => $groupDate,
            ];
        } else {
            $serviceName   = $request->input('service', $client->package_services ?: 'Renovación de Cuenta');
            $serviceAmount = floatval($request->input('amount', $client->renewal_amount));
            $billingType   = $request->input('type', 'unique');

            $clientService = \App\Models\ClientService::with('service')
                ->where('client_id', $client->id)
                ->where('service_name', $serviceName)
                ->first();
            $serviceDescription = $clientService?->service?->description ?? null;

            $receiptDate = $clientService ? $this->getNextBillingDate($clientService) : ($request->input('date') ? \Carbon\Carbon::parse($request->input('date')) : now());

            $receiptData = [
                'client'              => $client,
                'service_name'        => $serviceName,
                'amount'              => $serviceAmount,
                'billing_type'        => $billingType,
                'service_description' => $serviceDescription,
                'receipt_date'       => $receiptDate?->format('Y-m-d'),
            ];
        }

        if (!class_exists(\Barryvdh\DomPDF\Facade\Pdf::class)) {
            return back()->with('error', 'No se ha podido generar el PDF del recibo asociado.');
        }

        $pdfContent = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.receipt', $receiptData)->output();

        try {
            \Illuminate\Support\Facades\Mail::to($client->email)
                ->cc('ventas@lunavalos.com')
                ->send(new \App\Mail\RenewalReceiptMail(
                    $client,
                    $request->input('service', $groupByDate ? 'Recibo de Servicios Agrupados' : 'Recibo de Servicios'),
                    $groupByDate ? $totalAmount : $serviceAmount,
                    $request->input('type', $billingType ?? 'unique'),
                    $pdfContent
                ));

            if (!empty($services)) {
                \App\Models\ClientService::where('client_id', $client->id)
                    ->whereIn('id', $services->pluck('id')->toArray())
                    ->update(['renewal_email_sent_at' => now()]);
            } elseif (!empty($clientService)) {
                \App\Models\ClientService::where('client_id', $client->id)
                    ->where('service_name', $serviceName)
                    ->update(['renewal_email_sent_at' => now()]);
            }

            return back()->with('message', '¡Recibo enviado a ' . $client->email . ' con éxito!');
        } catch (\Exception $e) {
            return back()->with('error', 'Fallo de conexión SMTP o al enviar: ' . $e->getMessage());
        }
    }

    private function findServicesForDateGroup(Client $client, string $date)
    {
        $target = \Carbon\Carbon::parse($date)->startOfDay();

        $services = \App\Models\ClientService::with('service')
            ->where('client_id', $client->id)
            ->where('status', 'active')
            ->whereNotNull('renewal_date')
            ->get()
            ->filter(function ($service) use ($target) {
                $nextDate = $this->getNextBillingDate($service);
                return $nextDate && $nextDate->startOfDay()->eq($target);
            });

        return $services->map(function ($service) {
            return [
                'id'                  => $service->id,
                'service_name'        => $service->service_name,
                'renewal_amount'      => (float) $service->renewal_amount,
                'billing_type'        => $service->billing_type ?? 'unique',
                'service_description' => $service->service?->description,
                'next_renewal_date'   => optional($this->getNextBillingDate($service))->format('Y-m-d'),
            ];
        });
    }

    private function getNextBillingDate(\App\Models\ClientService $service): ?\Carbon\Carbon
    {
        if (!$service->renewal_date) {
            return null;
        }

        if ($service->billing_type === 'monthly') {
            return $this->getNextMonthlyOccurrence($service->renewal_date);
        }

        return $service->renewal_date;
    }
}
