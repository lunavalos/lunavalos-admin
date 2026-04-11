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

        $isAdmin = $user->hasAnyRole(['Administrador', 'Administrador Master']);

        if ($isAdmin) {
            // Stats for Admin
            $clientsCount = Client::count();
            $monthlyRevenue = Client::whereMonth('next_renewal_date', Carbon::now()->month)
                ->whereYear('next_renewal_date', Carbon::now()->year)
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
                        $planDetails = [
                            'items' => $client->quote->items->map(function($item) {
                                return [
                                    'id' => $item->id,
                                    'concept' => $item->concept,
                                    'description' => $item->description ?: ($item->service ? $item->service->description : null),
                                    'billing_type' => $item->billing_type,
                                    'unit_price' => $item->unit_price,
                                    'renewal_price' => $item->service ? $item->service->renewal_price : null
                                ];
                            }),
                            'business_name' => $client->business_name,
                            'total_initial' => $client->initial_price,
                            'total_renewal' => $client->renewal_amount,
                            'next_renewal_date' => $client->next_renewal_date
                        ];
                    } else {
                        // Fallback: Try to find a service that matches the package_services name
                        $fallbackDescription = $client->notes;
                        if (empty($fallbackDescription) && !empty($client->package_services)) {
                            $matchingService = \App\Models\Service::where('name', $client->package_services)->first();
                            if ($matchingService) {
                                $fallbackDescription = $matchingService->description;
                            }
                        }

                        $planDetails = [
                            'items' => [
                                [
                                    'id' => 'fallback',
                                    'concept' => 'Plan de Servicios: ' . ($client->package_services ?: 'Servicios Contratados'),
                                    'description' => $fallbackDescription ?: 'Servicios incluidos según contrato firmado.',
                                    'is_fallback' => true,
                                    'initial_price' => $client->initial_price,
                                    'renewal_price' => $client->renewal_amount
                                ]
                            ],
                            'business_name' => $client->business_name,
                            'total_initial' => $client->initial_price,
                            'total_renewal' => $client->renewal_amount,
                            'next_renewal_date' => $client->next_renewal_date
                        ];
                    }
                }
            }

            return Inertia::render('Dashboard', [
                'lists' => [
                    'tickets' => $tickets,
                    'plan_details' => $planDetails,
                ]
            ]);
        }
    }

    public function markNotificationsAsRead(Request $request)
    {
        $request->user()->unreadNotifications->markAsRead();
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
        $user = $request->user();
        $client = $user->client;

        if (!$client) {
            return redirect()->route('dashboard');
        }

        return Inertia::render('ClientPanel/Briefing', [
            'briefing_details' => [
                'briefing_context' => $client->briefing_context,
                'briefing_target_audience' => $client->briefing_target_audience,
                'briefing_competitors' => $client->briefing_competitors,
                'briefing_references' => $client->briefing_references,
                'briefing_contact_methods' => $client->briefing_contact_methods,
                'briefing_current_emails' => $client->briefing_current_emails,
            ]
        ]);
    }

    public function updateBriefing(Request $request)
    {
        $user = $request->user();
        $client = $user->client;

        if (!$client) {
            return redirect()->route('dashboard');
        }

        $validated = $request->validate([
            'briefing_context' => 'nullable|string',
            'briefing_target_audience' => 'nullable|string',
            'briefing_competitors' => 'nullable|string',
            'briefing_references' => 'nullable|string',
            'briefing_contact_methods' => 'nullable|string',
            'briefing_current_emails' => 'nullable|string',
        ]);

        $client->update($validated);

        return redirect()->back()->with('message', '¡Briefing creativo guardado correctamente!');
    }
}
