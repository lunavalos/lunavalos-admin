<?php

namespace App\Http\Controllers;

use App\Actions\Recurring\OpenBillingCycle;
use App\Models\BillingCycle;
use App\Models\Client;
use App\Models\Contract;
use App\Models\ContractService;
use App\Models\DeliverableCredit;
use App\Models\SocialAccount;
use App\Models\SocialPost;
use App\Models\Ticket;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class RecurringClientController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Recurrentes', only: ['show']),
            new Middleware('can:Gestionar Recurrentes', except: ['show']),
        ];
    }
    /**
     * Vista 360° del cliente recurrente:
     *   - resumen de créditos del ciclo seleccionado (`?month=YYYY-MM`)
     *   - Kanban filtrado a ese ciclo
     *   - posts sociales del mismo mes
     *   - permite navegar a meses pasados y futuros (planificación)
     */
    public function show(Client $client, Request $request)
    {
        if ($request->user()->isClient() && $request->user()->client_id !== $client->id) {
            abort(403, 'Acceso denegado.');
        }

        $contract = Contract::query()
            ->where('client_id', $client->id)
            ->where('status', 'signed')
            ->whereHas('contractServices')
            ->with(['contractServices'])
            ->latest('id')
            ->firstOrFail();

        // Mes seleccionado: query string o el ciclo activo o el mes actual.
        $activeCycle = $contract->billingCycles()
            ->where('status', 'active')
            ->latest('period_start')
            ->first();

        $monthParam = $request->input('month');
        if ($monthParam) {
            $periodStart = CarbonImmutable::parse($monthParam . '-01')->startOfMonth();
        } elseif ($activeCycle) {
            $periodStart = CarbonImmutable::parse($activeCycle->period_start)->startOfMonth();
        } else {
            $periodStart = CarbonImmutable::now()->startOfMonth();
        }
        $periodEnd = $periodStart->endOfMonth();

        // Ciclo del mes seleccionado (puede no existir todavía => planificación futura).
        $cycle = BillingCycle::where('contract_id', $contract->id)
            ->whereDate('period_start', $periodStart->toDateString())
            ->with('credits.contractService')
            ->first();

        $tickets = Ticket::recurring()
            ->where('client_id', $client->id)
            ->when($cycle, fn ($q) => $q->where('billing_cycle_id', $cycle->id))
            ->when(!$cycle, fn ($q) => $q->whereRaw('1 = 0')) // sin ciclo => sin tickets
            ->with(['assigned', 'deliverableCredit.contractService'])
            ->latest('id')
            ->get();

        $credits = ($cycle?->credits ?? collect())->map(function (DeliverableCredit $credit) use ($tickets) {
            $cs = $credit->contractService;
            $serviceTickets = $tickets->where('deliverable_credit_id', $credit->id);

            return [
                'id'           => $credit->id,
                'name'         => $cs?->name,
                'prefix'       => $cs?->prefix,
                'color'        => $cs?->color,
                'unit_type'    => $cs?->unit_type,
                'is_unlimited' => (bool) $credit->is_unlimited,
                'total'        => (int) $credit->total,
                'rolled_over'  => (int) $credit->rolled_over,
                'capacity'     => (int) $credit->total + (int) $credit->rolled_over,
                'consumed'     => (int) $credit->consumed,
                'remaining'    => $credit->is_unlimited
                    ? null
                    : max(0, ($credit->total + $credit->rolled_over) - $credit->consumed),
                'by_status'    => [
                    'Nuevos'      => $serviceTickets->where('status', 'Nuevos')->count(),
                    'En Proceso'  => $serviceTickets->where('status', 'En Proceso')->count(),
                    'En Revisión' => $serviceTickets->where('status', 'En Revisión')->count(),
                    'Ajustes'     => $serviceTickets->where('status', 'Ajustes')->count(),
                    'Completados' => $serviceTickets->where('status', 'Completados')->count(),
                ],
            ];
        })->values();

        // Historial breve de ciclos (últimos 12)
        $history = $contract->billingCycles()
            ->withCount(['tickets', 'tickets as completed_count' => fn ($q) => $q->where('status', 'Completados')])
            ->orderByDesc('period_start')
            ->limit(12)
            ->get(['id', 'period_start', 'period_end', 'status'])
            ->map(fn ($c) => [
                'id'              => $c->id,
                'month'           => $c->period_start->format('Y-m'),
                'label'           => $c->period_start->format('M Y'),
                'status'          => $c->status,
                'tickets_count'   => (int) $c->tickets_count,
                'completed_count' => (int) $c->completed_count,
            ]);

        // Social: cuentas + posts del mes (scheduled o published dentro del rango)
        $socialAccounts = $client->socialAccounts()
            ->orderBy('provider')
            ->get(['id', 'provider', 'name', 'avatar_url', 'status']);

        $socialPosts = SocialPost::where('client_id', $client->id)
            ->with(['targets:id,social_post_id,social_account_id,provider,status'])
            ->where(function ($q) use ($periodStart, $periodEnd) {
                $q->whereBetween('scheduled_at', [$periodStart, $periodEnd])
                  ->orWhereBetween('published_at', [$periodStart, $periodEnd])
                  ->orWhere(function ($q2) use ($periodStart, $periodEnd) {
                      $q2->whereNull('scheduled_at')
                         ->whereNull('published_at')
                         ->whereBetween('created_at', [$periodStart, $periodEnd]);
                  });
            })
            ->orderBy('scheduled_at')
            ->get();

        // --- Analytics del mes ---------------------------------------------
        $analytics = $this->buildAnalytics($client, $periodStart, $periodEnd, $socialAccounts->pluck('id')->all());

        return Inertia::render('Recurring/ClientShow', [
            'client'   => [
                'id'            => $client->id,
                'business_name' => $client->business_name,
                'contact_name'  => $client->contact_name,
                'email'         => $client->email,
            ],
            'contract' => [
                'id'              => $contract->id,
                'contract_number' => $contract->contract_number,
                'start_date'      => $contract->start_date,
                'end_date'        => $contract->end_date,
                'monthly_amount'  => $contract->monthly_amount,
            ],
            'month'          => $periodStart->format('Y-m'),
            'monthLabel'     => $periodStart->locale('es')->isoFormat('MMMM YYYY'),
            'prevMonth'      => $periodStart->subMonth()->format('Y-m'),
            'nextMonth'      => $periodStart->addMonth()->format('Y-m'),
            'isFutureMonth'  => $periodStart->greaterThan(CarbonImmutable::now()->startOfMonth()),
            'isCurrentMonth' => $periodStart->equalTo(CarbonImmutable::now()->startOfMonth()),
            'cycle' => $cycle ? [
                'id'           => $cycle->id,
                'period_start' => $cycle->period_start->toDateString(),
                'period_end'   => $cycle->period_end->toDateString(),
                'label'        => $cycle->period_start->format('M Y'),
                'status'       => $cycle->status,
            ] : null,
            'credits'        => $credits,
            'tickets'        => $tickets,
            'history'        => $history,
            'socialAccounts' => $socialAccounts,
            'socialPosts'    => $socialPosts,
            'availableSocialProviders' => SocialAccount::PROVIDERS,
            'analytics'      => $analytics,
        ]);
    }

    /**
     * Abre un ciclo para el contrato. Acepta `?month=YYYY-MM` para abrir un mes futuro.
     */
    public function openCycle(Client $client, Request $request, OpenBillingCycle $openCycle)
    {
        if ($request->user()->isClient() && $request->user()->client_id !== $client->id) {
            abort(403, 'Acceso denegado.');
        }

        $request->validate(['month' => 'nullable|regex:/^\d{4}-\d{2}$/']);

        $contract = Contract::query()
            ->where('client_id', $client->id)
            ->where('status', 'signed')
            ->whereHas('contractServices')
            ->latest('id')
            ->firstOrFail();

        $periodStart = $request->filled('month')
            ? CarbonImmutable::parse($request->input('month') . '-01')->startOfMonth()
            : null;

        $cycle = $openCycle($contract, $periodStart);

        return redirect()
            ->route('recurring.clients.show', ['client' => $client->id, 'month' => $cycle->period_start->format('Y-m')])
            ->with('success', sprintf('Ciclo %s abierto.', $cycle->period_start->format('M Y')));
    }

    /**
     * Crea un ticket on-demand. Acepta `billing_cycle_id` para planificar
     * en un ciclo futuro distinto al activo.
     */
    public function createDeliverable(Request $request, Client $client)
    {
        if ($request->user()->isClient() && $request->user()->client_id !== $client->id) {
            abort(403, 'Acceso denegado.');
        }

        $data = $request->validate([
            'title'                 => 'required|string|max:255',
            'priority'              => 'required|string|in:Baja,Media,Alta,Urgente',
            'content'               => 'nullable|string',
            'due_date'              => 'nullable|date',
            'billing_cycle_id'      => 'nullable|exists:billing_cycles,id',
            'deliverable_credit_id' => 'nullable|exists:deliverable_credits,id',
            'assigned_id'           => 'nullable|exists:users,id',
        ]);

        $contract = Contract::query()
            ->where('client_id', $client->id)
            ->where('status', 'signed')
            ->whereHas('contractServices')
            ->latest('id')
            ->firstOrFail();

        // Ciclo destino: explícito (planificación futura) o el activo.
        if (!empty($data['billing_cycle_id'])) {
            $cycle = BillingCycle::where('contract_id', $contract->id)
                ->where('status', '!=', 'locked')
                ->findOrFail($data['billing_cycle_id']);
        } else {
            $cycle = $contract->billingCycles()
                ->where('status', 'active')
                ->latest('period_start')
                ->first();
        }

        abort_if(!$cycle, 422, 'No hay un ciclo destino. Abre el ciclo antes de crear entregables.');

        return DB::transaction(function () use ($data, $client, $cycle) {
            $credit   = null;
            $sequence = null;

            if (!empty($data['deliverable_credit_id'])) {
                $credit = DeliverableCredit::with('contractService')
                    ->where('billing_cycle_id', $cycle->id)
                    ->findOrFail($data['deliverable_credit_id']);

                if (!$credit->hasCapacity()) {
                    abort(422, 'Este crédito ya no tiene capacidad disponible este mes.');
                }

                $sequence = Ticket::where('deliverable_credit_id', $credit->id)->max('sequence_number') + 1;
            }

            Ticket::create([
                'title'                 => $data['title'],
                'priority'              => $data['priority'],
                'content'               => $data['content'] ?? null,
                'status'                => 'Nuevos',
                'source_type'           => Ticket::SOURCE_RECURRING,
                'creator_id'            => Auth::id(),
                'assigned_id'           => $data['assigned_id'] ?? null,
                'client_id'             => $client->id,
                'billing_cycle_id'      => $cycle->id,
                'deliverable_credit_id' => $credit?->id,
                'sequence_number'       => $sequence,
                'due_date'              => $data['due_date'] ?? null,
            ]);

            return back()->with('success', 'Entregable creado.');
        });
    }

    /**
     * Forzar sincronización de métricas sociales del cliente (botón "Sincronizar ahora").
     */
    public function syncAnalytics(Client $client, Request $request)
    {
        if ($request->user()->isClient() && $request->user()->client_id !== $client->id) {
            abort(403, 'Acceso denegado.');
        }

        \Illuminate\Support\Facades\Artisan::call('social:fetch-insights', [
            '--client' => $client->id,
            '--days'   => 120,
        ]);
        \Illuminate\Support\Facades\Artisan::call('social:fetch-account-stats', [
            '--client' => $client->id,
        ]);

        return back()->with('success', 'Métricas sincronizadas.');
    }

    /**
     * Calcula KPIs + breakdown del mes para el tab Analytics.
     */
    private function buildAnalytics(Client $client, CarbonImmutable $start, CarbonImmutable $end, array $accountIds): array
    {
        $prevStart = $start->subMonth();
        $prevEnd   = $start->subDay()->endOfDay();

        $kpiCurr = $this->aggregateMetrics($client->id, $start, $end);
        $kpiPrev = $this->aggregateMetrics($client->id, $prevStart, $prevEnd);

        // Por red social
        $perProvider = DB::table('social_post_metrics as m')
            ->join('social_post_targets as t', 't.id', '=', 'm.social_post_target_id')
            ->join('social_posts as p', 'p.id', '=', 't.social_post_id')
            ->where('p.client_id', $client->id)
            ->whereBetween('t.published_at', [$start, $end])
            ->groupBy('m.provider')
            ->selectRaw('m.provider,
                COUNT(*) as posts,
                COALESCE(SUM(m.impressions),0)  as impressions,
                COALESCE(SUM(m.reach),0)        as reach,
                COALESCE(SUM(m.likes),0)        as likes,
                COALESCE(SUM(m.comments),0)     as comments,
                COALESCE(SUM(m.shares),0)       as shares,
                COALESCE(SUM(m.saves),0)        as saves,
                COALESCE(SUM(m.clicks),0)       as clicks,
                COALESCE(SUM(m.video_views),0)  as video_views,
                COALESCE(AVG(m.engagement_rate),0) as avg_engagement')
            ->get()
            ->keyBy('provider');

        // Top posts del mes (max 5)
        $topPosts = DB::table('social_post_metrics as m')
            ->join('social_post_targets as t', 't.id', '=', 'm.social_post_target_id')
            ->join('social_posts as p', 'p.id', '=', 't.social_post_id')
            ->where('p.client_id', $client->id)
            ->whereBetween('t.published_at', [$start, $end])
            ->orderByDesc(DB::raw('m.likes + m.comments + m.shares + m.saves'))
            ->limit(5)
            ->select('p.id', 'p.title', 'p.body', 't.provider', 't.platform_url',
                'm.impressions', 'm.reach', 'm.likes', 'm.comments', 'm.shares',
                'm.saves', 'm.engagement_rate')
            ->get();

        // Crecimiento de followers (última muestra del mes vs primera)
        $followersTrend = [];
        if (!empty($accountIds)) {
            $followersTrend = DB::table('social_account_daily_stats as s')
                ->join('social_accounts as a', 'a.id', '=', 's.social_account_id')
                ->whereIn('s.social_account_id', $accountIds)
                ->whereBetween('s.day', [$start->toDateString(), $end->toDateString()])
                ->orderBy('s.day')
                ->select('s.day', 'a.provider', 's.followers')
                ->get()
                ->groupBy('provider')
                ->map(function ($rows) {
                    $first = $rows->first();
                    $last  = $rows->last();
                    return [
                        'first'   => (int) $first->followers,
                        'last'    => (int) $last->followers,
                        'delta'   => (int) $last->followers - (int) $first->followers,
                        'series'  => $rows->map(fn ($r) => ['day' => $r->day, 'followers' => (int) $r->followers])->values(),
                    ];
                });
        }

        // ¿Hay alguna métrica registrada?
        $hasData = $kpiCurr['posts'] > 0;

        return [
            'has_data'        => $hasData,
            'kpis'            => $kpiCurr,
            'previous'        => $kpiPrev,
            'delta'           => [
                'impressions' => $this->pctDelta($kpiCurr['impressions'], $kpiPrev['impressions']),
                'reach'       => $this->pctDelta($kpiCurr['reach'],       $kpiPrev['reach']),
                'engagement'  => $this->pctDelta($kpiCurr['engagement'],  $kpiPrev['engagement']),
                'posts'       => $this->pctDelta($kpiCurr['posts'],       $kpiPrev['posts']),
            ],
            'per_provider'    => $perProvider,
            'top_posts'       => $topPosts,
            'followers_trend' => $followersTrend,
        ];
    }

    private function aggregateMetrics(int $clientId, CarbonImmutable $start, CarbonImmutable $end): array
    {
        $row = DB::table('social_post_metrics as m')
            ->join('social_post_targets as t', 't.id', '=', 'm.social_post_target_id')
            ->join('social_posts as p', 'p.id', '=', 't.social_post_id')
            ->where('p.client_id', $clientId)
            ->whereBetween('t.published_at', [$start, $end])
            ->selectRaw('
                COUNT(*) as posts,
                COALESCE(SUM(m.impressions),0)  as impressions,
                COALESCE(SUM(m.reach),0)        as reach,
                COALESCE(SUM(m.likes),0)        as likes,
                COALESCE(SUM(m.comments),0)     as comments,
                COALESCE(SUM(m.shares),0)       as shares,
                COALESCE(SUM(m.saves),0)        as saves,
                COALESCE(SUM(m.clicks),0)       as clicks,
                COALESCE(SUM(m.video_views),0)  as video_views,
                COALESCE(AVG(m.engagement_rate),0) as engagement
            ')
            ->first();

        return [
            'posts'         => (int)   ($row->posts        ?? 0),
            'impressions'   => (int)   ($row->impressions  ?? 0),
            'reach'         => (int)   ($row->reach        ?? 0),
            'likes'         => (int)   ($row->likes        ?? 0),
            'comments'      => (int)   ($row->comments     ?? 0),
            'shares'        => (int)   ($row->shares       ?? 0),
            'saves'         => (int)   ($row->saves        ?? 0),
            'clicks'        => (int)   ($row->clicks       ?? 0),
            'video_views'   => (int)   ($row->video_views  ?? 0),
            'interactions'  => (int) (($row->likes ?? 0) + ($row->comments ?? 0) + ($row->shares ?? 0) + ($row->saves ?? 0)),
            'engagement'    => (float) ($row->engagement   ?? 0),
        ];
    }

    private function pctDelta(float $curr, float $prev): ?float
    {
        if ($prev == 0.0) {
            return $curr > 0 ? null : 0.0; // null = nuevo / sin comparable
        }
        return round((($curr - $prev) / $prev) * 100, 1);
    }
}
