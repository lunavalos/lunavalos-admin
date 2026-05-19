<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Client;
use App\Models\QuoteItem;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        $isAdmin = $user->isAdmin();

        if ($isAdmin) {
            // Stats for Admin
            $clientsCount = Client::count();
            $monthlyRevenue = \App\Models\ClientService::whereMonth('renewal_date', Carbon::now()->month)
                ->whereYear('renewal_date', Carbon::now()->year)
                ->where('status', 'active')
                ->sum('renewal_amount');

            // Proyección Cotizaciones (Únicos y Mensuales)
            $projectedUnique = QuoteItem::where('billing_type', 'unique')
                ->orWhereNull('billing_type') // Just in case
                ->get()->sum(function ($item) {
                    return $item->quantity * $item->unit_price;
                });

            $projectedMonthly = QuoteItem::where('billing_type', 'monthly')
                ->get()->sum(function ($item) {
                    return $item->quantity * $item->unit_price;
                });

            $pendingQuotes = \App\Models\Quote::where('status', 'Pendiente')->latest()->get();

            return Inertia::render('Dashboard', [
                'stats' => [
                    'clients_count' => $clientsCount,
                    'monthly_revenue' => $monthlyRevenue,
                    'projected_unique' => $projectedUnique,
                    'projected_monthly' => $projectedMonthly,
                ],
                'lists' => [
                    'pending_quotes' => $pendingQuotes,
                    'recent_tickets' => \App\Models\Ticket::with(['assigned', 'messages', 'creator'])
                        ->latest()
                        ->take(5)
                        ->get(),
                    'my_tickets' => \App\Models\Ticket::where('assigned_id', $user->id)
                        ->orWhere('creator_id', $user->id)
                        ->with(['assigned', 'messages', 'creator'])
                        ->latest()
                        ->take(5)
                        ->get(),
                ]
            ]);

        } else {
            $tickets = [];
            $planDetails = null;
            
            if ($user->hasRole('Cliente')) {
                // Get tickets
                $tickets = \App\Models\Ticket::where('creator_id', $user->id)
                    ->with(['assigned', 'messages'])
                    ->latest()
                    ->take(5)
                    ->get();

                // Get plan details (from quote)
                $client = \App\Models\Client::where('user_id', $user->id)
                    ->with(['quote.items.service'])
                    ->first();
                
                if ($client) {
                    if ($client->quote && $client->quote->items->count() > 0) {
                        // Load active client services for ID lookup
                        $client->load('services');
                        $planDetails = [
                            'items' => $client->quote->items->map(function($item) use ($client) {
                                // Try to find a matching ClientService by service name
                                $cs = $client->services->firstWhere('service_name', $item->concept);
                                return [
                                    'id'                => $item->id,
                                    'client_service_id' => $cs?->id,
                                    'concept'           => $item->concept,
                                    'description'       => $item->description ?: ($item->service ? $item->service->description : null),
                                    'billing_type'      => $item->billing_type ?? $cs?->billing_type,
                                    'unit_price'        => $item->unit_price,
                                    'renewal_price'     => $item->service ? $item->service->renewal_price : null
                                ];
                            }),
                            'business_name'     => $client->business_name,
                            'total_initial'     => $client->initial_price,
                            'total_renewal'     => $client->renewal_amount,
                            'next_renewal_date' => $client->next_renewal_date
                        ];
                    } else {
                        // Fallback: Try to find a service that matches the package_services name
                        $client->load('services.service');
                        
                        $servicesItems = $client->services->map(function($cs) {
                            return [
                                'id'                => $cs->id,
                                'client_service_id' => $cs->id,
                                'concept'           => $cs->service_name,
                                'description'       => $cs->service->description ?? 'Servicio contratado.',
                                'is_fallback'       => false,
                                'billing_type'      => $cs->billing_type,
                                'initial_price'     => 0,
                                'renewal_price'     => $cs->renewal_amount,
                                'renewal_date'      => $cs->renewal_date
                            ];
                        });

                        $planDetails = [
                            'items' => $servicesItems->toArray(),
                            'business_name' => $client->business_name,
                            'total_initial' => $client->initial_price,
                            'total_renewal' => $client->services->sum('renewal_amount'),
                            'next_renewal_date' => $client->services->sortBy('renewal_date')->first()?->renewal_date ?: $client->next_renewal_date
                        ];
                    }
                }
            }

            if (!$user->hasRole('Cliente')) {
                // For other roles (Employee, Designer, etc.)
                $myTickets = \App\Models\Ticket::where('assigned_id', $user->id)
                    ->orWhere('creator_id', $user->id)
                    ->with(['assigned', 'messages', 'creator'])
                    ->latest()
                    ->take(5)
                    ->get();
            }

            return Inertia::render('Dashboard', [
                'lists' => [
                    'tickets'           => $tickets,
                    'my_tickets'        => $myTickets ?? [],
                    'plan_details'      => $planDetails,
                    'upcoming_renewals' => $user->hasRole('Cliente') && isset($client) && $client
                        ? \App\Models\ClientService::with('service')
                            ->where('client_id', $client->id)
                            ->where('status', 'active')
                            ->whereNotNull('renewal_date')
                            ->where('renewal_date', '>=', Carbon::today())
                            ->where('renewal_date', '<=', Carbon::today()->addDays(60))
                            ->orderBy('renewal_date')
                            ->get()
                            ->map(fn($cs) => [
                                'id'             => $cs->id,
                                'client_id'      => $client->id,
                                'service_name'   => $cs->service_name,
                                'renewal_amount' => (float) $cs->renewal_amount,
                                'renewal_date'   => $cs->renewal_date->toDateString(),
                                'billing_type'   => $cs->billing_type,
                                'days_until'     => (int) Carbon::today()->diffInDays($cs->renewal_date),
                            ])
                            ->values()
                        : [],
                ],
                'two_factor_notice' => ($user->hasRole('Cliente') && !$user->hasTwoFactorEnabled() && $user->login_count <= 3) 
                    ? "Le quedan " . (4 - $user->login_count) . " inicios de sesión antes de que la configuración de 2FA sea obligatoria. Por favor, actívela en su perfil lo antes posible." 
                    : null,
            ]);
        }
    }

    public function markNotificationsAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
        return back();
    }

    public function markOneNotificationAsRead(Request $request, $id)
    {
        $notification = $request->user()->notifications()->where('id', $id)->first();
        if ($notification && !$notification->read_at) {
            $notification->markAsRead();
        }
        return back();
    }

    public function clientEmails(Request $request)
    {
        $user = $request->user();
        $client = $user->client;

        if (!$client || !$client->has_custom_email_config) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('ClientPanel/Emails', [
            'client' => $client
        ]);
    }

    public function clientBriefing(Request $request)
    {
        $user   = $request->user();
        $client = \App\Models\Client::where('user_id', $user->id)->first()
                ?? $user->client; // fallback por client_id en users

        if (!$client) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('ClientPanel/Briefing', [
            'briefing_details' => [
                'briefing_context'         => $client->briefing_context,
                'briefing_target_audience' => $client->briefing_target_audience,
                'briefing_competitors'     => $client->briefing_competitors,
                'briefing_references'      => $client->briefing_references,
                'briefing_contact_methods' => $client->briefing_contact_methods,
                'briefing_current_emails'  => $client->briefing_current_emails,
            ]
        ]);
    }

    public function updateBriefing(Request $request)
    {
        $user   = $request->user();
        $client = \App\Models\Client::where('user_id', $user->id)->first()
                ?? $user->client;

        if (!$client) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'briefing_context'         => 'nullable|string',
            'briefing_target_audience' => 'nullable|string',
            'briefing_competitors'     => 'nullable|string',
            'briefing_references'      => 'nullable|string',
            'briefing_contact_methods' => 'nullable|string',
            'briefing_current_emails'  => 'nullable|string',
        ]);

        $client->update($validated);

        return redirect()->back()->with('message', '¡Briefing creativo guardado correctamente!');
    }

    public function clientMailConfig(Request $request)
    {
        $user   = $request->user();
        $client = \App\Models\Client::where('user_id', $user->id)->first()
                ?? $user->client;

        if (!$client) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('ClientPanel/MailConfig', [
            'mail_config' => [
                'imap_host' => $client->imap_host,
                'imap_port' => $client->imap_port,
                'imap_tls'  => $client->imap_tls,
                'smtp_host' => $client->smtp_host,
                'smtp_port' => $client->smtp_port,
                'smtp_tls'  => $client->smtp_tls,
                'pop_host'  => $client->pop_host,
                'pop_port'  => $client->pop_port,
                'pop_tls'   => $client->pop_tls,
            ],
        ]);
    }
}

