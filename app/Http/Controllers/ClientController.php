<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\User;
use Illuminate\Http\Request;
use Carbon\Carbon;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;

class ClientController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Ver Clientes', only: ['index', 'show']),
            new Middleware('can:Crear Clientes', only: ['create', 'store', 'importView', 'importBulk']),
            new Middleware('can:Editar Clientes', only: ['edit', 'update', 'renew']),
            new Middleware('can:Eliminar Clientes', only: ['destroy']),
        ];
    }

    public function index()
    {
        $clients = Client::orderBy('created_at', 'desc')->get();
        return \Inertia\Inertia::render('Clients/Index', [
            'clients' => $clients
        ]);
    }

    public function importView()
    {
        return \Inertia\Inertia::render('Clients/Import');
    }

    public function importBulk(Request $request)
    {
        $request->validate([
            'clients' => 'required|array',
            'clients.*.business_name' => 'required|string|max:255',
            'truncate_clients' => 'nullable|boolean',
        ]);

        if ($request->truncate_clients) {
            DB::statement('SET FOREIGN_KEY_CHECKS=0;');
            Client::truncate();
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $count = 0;
        foreach ($request->clients as $clientData) {
            if (empty($clientData['renewal_amount'])) {
                $clientData['renewal_amount'] = null;
            }
            if (empty($clientData['contract_date'])) {
                $clientData['contract_date'] = null;
            }
            if (empty($clientData['next_renewal_date'])) {
                $clientData['next_renewal_date'] = null;
            }

            // Robust date cleaning to prevent 500 error on invalid formats
            $dateFields = ['contract_date', 'next_renewal_date'];
            foreach ($dateFields as $field) {
                if (!empty($clientData[$field])) {
                    $rawDate = trim($clientData[$field]);

                    if (strlen($rawDate) == 7 && preg_match('/^\d{4}-\d{2}$/', $rawDate)) {
                        $rawDate .= '-01';
                    }

                    try {
                        $clientData[$field] = \Carbon\Carbon::parse($rawDate)->toDateString();
                    } catch (\Exception $e) {
                        // Try common alternative d/m/Y format (Excel often exports this in Spanish regions)
                        try {
                            if (strpos($rawDate, '/') !== false) {
                                $clientData[$field] = \Carbon\Carbon::createFromFormat('d/m/Y', $rawDate)->toDateString();
                            } else {
                                // If completely unreadable (like "jueves, 2 de agosto..."), fallback to null
                                $clientData[$field] = null;
                            }
                        } catch (\Exception $innerE) {
                            $clientData[$field] = null;
                        }
                    }
                }
            }
            // Parse email_accounts from text format: email|pass|user|phone separated by lines or commas
            if (!empty($clientData['email_accounts']) && is_string($clientData['email_accounts'])) {
                $rawAccounts = trim($clientData['email_accounts']);
                $parsedAccounts = [];
                // Split by line breaks, and optionally by commas if they used them (assume linebreaks for Excel cells)
                $lines = preg_split('/[\r\n]+/', $rawAccounts);
                
                foreach ($lines as $line) {
                    $line = trim($line);
                    if (empty($line)) continue;
                    
                    $parts = explode('|', $line);
                    if (count($parts) >= 1 && !empty(trim($parts[0]))) {
                        $parsedAccounts[] = [
                            'email' => trim($parts[0] ?? ''),
                            'password' => trim($parts[1] ?? ''),
                            'username' => trim($parts[2] ?? ''),
                            'phone' => trim($parts[3] ?? ''),
                        ];
                    }
                }
                $clientData['email_accounts'] = $parsedAccounts;
            } else {
                $clientData['email_accounts'] = null;
            }

            // User creation logic mapping dynamically
            $userId = null;
            if (!empty($clientData['login_email']) && !empty($clientData['login_password'])) {
                $user = User::firstOrCreate(
                    ['email' => trim($clientData['login_email'])],
                    [
                        'name' => trim($clientData['contact_name'] ?? $clientData['business_name']),
                        'password' => Hash::make(trim($clientData['login_password'])),
                    ]
                );

                // Ensure Customer Role exists, then assign
                $role = Role::firstOrCreate(['name' => 'Cliente']);
                $user->assignRole($role);
                $userId = $user->id;
            }

            // Remove non-client columns and set user_id
            unset($clientData['login_email']);
            unset($clientData['login_password']);
            $clientData['user_id'] = $userId;

            Client::create($clientData);
            $count++;
        }

        return redirect()->route('clients.index')->with('message', '¡Éxito! Se importaron ' . $count . ' clientes correctamente' . ($request->truncate_clients ? ' eliminando primero los anteriores' : '') . '.');
    }

    public function create(Request $request)
    {
        $quote_data = $request->only(['quote_id', 'business_name', 'contact_name', 'email', 'phone', 'package_services']);

        if ($request->has('quote_id')) {
            $quote = \App\Models\Quote::with(['items', 'contract'])->find($request->quote_id);
            if ($quote) {
                if (empty($quote_data['business_name'])) {
                    // Prioritize legal name from signed contract if available
                    $quote_data['business_name'] = ($quote->contract && $quote->contract->legal_name)
                        ? $quote->contract->legal_name
                        : $quote->client_name;
                }

                if (empty($quote_data['contact_name']))
                    $quote_data['contact_name'] = $quote->contact_name;
                if (empty($quote_data['email']))
                    $quote_data['email'] = $quote->email;
                if (empty($quote_data['phone']))
                    $quote_data['phone'] = $quote->phone;

                // Pre-fill notes with fiscal info if contract exists
                if ($quote->contract && $quote->contract->tax_id) {
                    $fiscalInfo = "\n--- Datos Fiscales del Contrato ---\n";
                    $fiscalInfo .= "RFC: " . $quote->contract->tax_id . "\n";
                    $fiscalInfo .= "Dirección: " . $quote->contract->fiscal_address . " (CP " . $quote->contract->postal_code . ")\n";
                    $fiscalInfo .= "Representante: " . ($quote->contract->legal_representative ?: 'N/A') . "\n";

                    $quote_data['notes'] = ($quote_data['notes'] ?? '') . $fiscalInfo;
                }

                $services = collect($quote->items)->map(function ($item) {
                    return $item->concept;
                })->filter()->implode(' + ');
                if (empty($quote_data['package_services']))
                    $quote_data['package_services'] = $services;

                $initialPrice = collect($quote->items)->where('billing_type', 'unique')->sum(function ($item) {
                    return $item->unit_price * $item->quantity;
                });
                $renewalAmount = null;

                $quote_data['initial_price'] = $initialPrice;
                $quote_data['renewal_amount'] = $renewalAmount;
            }
        }

        return \Inertia\Inertia::render('Clients/Create', [
            'quote_data' => $quote_data,
            'services' => \App\Models\Service::all(),
            'agencies' => \App\Models\Agency::orderBy('name')->get()
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'agency_source' => 'nullable|string|max:255',
            'contract_date' => 'nullable|date',
            'initial_price' => 'nullable|numeric|min:0',
            'next_renewal_date' => 'nullable|date',
            'renewal_amount' => 'nullable|numeric|min:0',
            'package_services' => 'nullable|string|max:255',
            'auto_renew_notice' => 'boolean',
            'domain_names' => 'nullable|string|max:255',
            'domain_provider' => 'nullable|string|max:255',
            'hosting_provider' => 'nullable|string|max:255',
            'email_provider' => 'nullable|string|max:255',
            'login_credentials' => 'nullable|string',
            'notes' => 'nullable|string',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|string|max:5',
            'imap_tls' => 'boolean',
            'pop_host' => 'nullable|string|max:255',
            'pop_port' => 'nullable|string|max:5',
            'pop_tls' => 'boolean',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|string|max:5',
            'smtp_tls' => 'boolean',
            'has_custom_email_config' => 'boolean',
            'login_email' => 'nullable|email|max:255',
            'login_password' => 'nullable|string|min:6',
            'email_accounts' => 'nullable|array',
            'quote_id' => 'nullable|exists:quotes,id',
            'quote_file' => 'nullable|file|mimes:pdf,doc,docx,zip|max:10240',
            'contract_file' => 'nullable|file|mimes:pdf,doc,docx,zip|max:10240',
            'branding_file' => 'nullable|file|mimes:zip,rar,pdf,jpg,png|max:20480',
            'receipt_file' => 'nullable|file|mimes:pdf,jpg,png|max:10240',
        ]);

        $userId = null;
        if (!empty($validated['login_email']) && !empty($validated['login_password'])) {
            $user = User::firstOrCreate(
                ['email' => trim($validated['login_email'])],
                [
                    'name' => trim($validated['contact_name'] ?? $validated['business_name']),
                    'password' => Hash::make(trim($validated['login_password'])),
                ]
            );
            $role = Role::firstOrCreate(['name' => 'Cliente']);
            $user->assignRole($role);
            $userId = $user->id;
        }

        unset($validated['login_email'], $validated['login_password']);
        $validated['user_id'] = $userId;

        if ($request->hasFile('quote_file')) {
            $validated['quote_file_path'] = $request->file('quote_file')->store('client-files', 'public');
        } elseif (!empty($validated['quote_id'])) {
            $quote = \App\Models\Quote::with('contract', 'items')->find($validated['quote_id']);
            if ($quote) {
                // Auto-generar y adjuntar el PDF de la cotización
                $pdf = \Barryvdh\DomPDF\Facade\Pdf::loadView('pdf.quote', compact('quote'));
                $path = 'client-files/cotizacion_auto_' . $quote->id . '_' . time() . '.pdf';
                \Illuminate\Support\Facades\Storage::disk('public')->put($path, $pdf->output());
                $validated['quote_file_path'] = $path;

                // Si hay contrato firmado, adjuntar CSF y quizá el contrato en sí
                if ($quote->contract && $quote->contract->status === 'signed') {
                    // La CSF la guardamos en el espacio de branding_file si no se subió una
                    if (!$request->hasFile('branding_file') && $quote->contract->csf_file_path) {
                        $validated['branding_file_path'] = $quote->contract->csf_file_path;
                    }
                }
            }
        }

        if ($request->hasFile('contract_file')) {
            $validated['contract_file_path'] = $request->file('contract_file')->store('client-files', 'public');
        }
        if ($request->hasFile('branding_file')) {
            $validated['branding_file_path'] = $request->file('branding_file')->store('client-files', 'public');
        }
        if ($request->hasFile('receipt_file')) {
            $validated['receipt_file_path'] = $request->file('receipt_file')->store('client-files', 'public');
        }

        // Clean up from validated array to avoid fillable issues if they aren't fillable
        unset($validated['quote_file'], $validated['contract_file'], $validated['branding_file'], $validated['receipt_file']);

        $client = Client::create($validated);

        if (!empty($validated['quote_id'])) {
            $quote = \App\Models\Quote::with('items.costs')->find($validated['quote_id']);
            if ($quote) {
                $quote->update(['status' => 'Completada']);
                
                // Copiar costos de la cotización al nuevo cliente
                foreach ($quote->items as $item) {
                    foreach ($item->costs as $cost) {
                        \App\Models\ClientCost::create([
                            'client_id' => $client->id,
                            'concept' => $cost->title . ' (' . $item->concept . ')',
                            // Calculate total cost for that item's row based on quantity (quantity * price) if needed, or just price.
                            // The cost relation has: title, quantity, price.
                            'amount' => $cost->price * $cost->quantity,
                            'billing_frequency' => $item->billing_type
                        ]);
                    }
                }
            }
        }

        return redirect()->route('clients.index')->with('message', 'Cliente creado exitosamente.');
    }

    public function show(Client $client)
    {
        $client->load('costs');
        return \Inertia\Inertia::render('Clients/Show', [
            'client' => $client
        ]);
    }

    public function edit(Client $client)
    {
        $client->load('costs');
        return \Inertia\Inertia::render('Clients/Edit', [
            'client' => $client,
            'services' => \App\Models\Service::all(),
            'agencies' => \App\Models\Agency::orderBy('name')->get()
        ]);
    }

    public function update(Request $request, Client $client)
    {
        $validated = $request->validate([
            'business_name' => 'required|string|max:255',
            'contact_name' => 'nullable|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'city' => 'nullable|string|max:255',
            'agency_source' => 'nullable|string|max:255',
            'contract_date' => 'nullable|date',
            'initial_price' => 'nullable|numeric|min:0',
            'next_renewal_date' => 'nullable|date',
            'renewal_amount' => 'nullable|numeric|min:0',
            'package_services' => 'nullable|string|max:255',
            'auto_renew_notice' => 'boolean',
            'domain_names' => 'nullable|string|max:255',
            'domain_provider' => 'nullable|string|max:255',
            'hosting_provider' => 'nullable|string|max:255',
            'email_provider' => 'nullable|string|max:255',
            'login_credentials' => 'nullable|string',
            'notes' => 'nullable|string',
            'imap_host' => 'nullable|string|max:255',
            'imap_port' => 'nullable|string|max:5',
            'imap_tls' => 'boolean',
            'pop_host' => 'nullable|string|max:255',
            'pop_port' => 'nullable|string|max:5',
            'pop_tls' => 'boolean',
            'smtp_host' => 'nullable|string|max:255',
            'smtp_port' => 'nullable|string|max:5',
            'smtp_tls' => 'boolean',
            'has_custom_email_config' => 'boolean',
            'login_email' => 'nullable|email|max:255',
            'login_password' => 'nullable|string|min:6',
            'email_accounts' => 'nullable|array',
            'quote_id' => 'nullable|exists:quotes,id',
            'quote_file' => 'nullable|file|mimes:pdf,doc,docx,zip|max:10240',
            'contract_file' => 'nullable|file|mimes:pdf,doc,docx,zip|max:10240',
            'branding_file' => 'nullable|file|mimes:zip,rar,pdf,jpg,png|max:20480',
            'receipt_file' => 'nullable|file|mimes:pdf,jpg,png|max:10240',
        ]);

        if (!empty($validated['login_email']) && !empty($validated['login_password']) && !$client->user_id) {
            $user = User::firstOrCreate(
                ['email' => trim($validated['login_email'])],
                [
                    'name' => trim($validated['contact_name'] ?? $validated['business_name']),
                    'password' => Hash::make(trim($validated['login_password'])),
                ]
            );
            $role = Role::firstOrCreate(['name' => 'Cliente']);
            $user->assignRole($role);
            $validated['user_id'] = $user->id;
        }

        unset($validated['login_email'], $validated['login_password']);

        if ($request->hasFile('quote_file')) {
            if ($client->quote_file_path)
                \Illuminate\Support\Facades\Storage::disk('public')->delete($client->quote_file_path);
            $validated['quote_file_path'] = $request->file('quote_file')->store('client-files', 'public');
        }
        if ($request->hasFile('contract_file')) {
            if ($client->contract_file_path)
                \Illuminate\Support\Facades\Storage::disk('public')->delete($client->contract_file_path);
            $validated['contract_file_path'] = $request->file('contract_file')->store('client-files', 'public');
        }
        if ($request->hasFile('branding_file')) {
            if ($client->branding_file_path)
                \Illuminate\Support\Facades\Storage::disk('public')->delete($client->branding_file_path);
            $validated['branding_file_path'] = $request->file('branding_file')->store('client-files', 'public');
        }
        if ($request->hasFile('receipt_file')) {
            if ($client->receipt_file_path)
                \Illuminate\Support\Facades\Storage::disk('public')->delete($client->receipt_file_path);
            $validated['receipt_file_path'] = $request->file('receipt_file')->store('client-files', 'public');
        }

        unset($validated['quote_file'], $validated['contract_file'], $validated['branding_file'], $validated['receipt_file']);

        $client->update($validated);

        return redirect()->route('clients.index')->with('message', 'Cliente actualizado exitosamente.');
    }

    public function destroy(Client $client)
    {
        $client->delete();
        return redirect()->route('clients.index')->with('message', 'Cliente eliminado exitosamente.');
    }

    public function renew(Client $client)
    {
        if ($client->next_renewal_date) {
            $client->update([
                'next_renewal_date' => \Carbon\Carbon::parse($client->next_renewal_date)->addYear()
            ]);
            return redirect()->route('clients.index')->with('message', '¡Renovación exitosa! Se ha sumado 1 año a la vigencia del cliente.');
        }

        return redirect()->back()->with('error', 'El cliente no tiene una fecha configurada para renovar.');
    }
}
