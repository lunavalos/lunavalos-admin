<?php

namespace App\Http\Controllers;

use App\Models\Contract;
use App\Models\Quote;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;

class ContractController extends Controller
{
    /**
     * Generate a new contract for the given quote.
     */
    public function generate(Request $request, Quote $quote)
    {
        // Don't generate if one already exists
        if ($quote->contract) {
            return back()->with('error', 'El contrato ya fue generado.');
        }

        $contract = $quote->contract()->create([
            'token' => Str::random(32),
            'status' => 'pending',
        ]);

        return back()->with('success', 'Enlace de contrato generado correctamente.');
    }

    /**
     * Show the public contract view to the client.
     */
    public function show($token)
    {
        $contract = Contract::where('token', $token)->with('quote.items')->firstOrFail();
        $settings = Setting::pluck('value', 'key')->toArray();

        return Inertia::render('Contracts/Show', [
            'contract' => $contract,
            'settings' => $settings,
        ]);
    }

    /**
     * Process contract signing from the client.
     */
    public function sign(Request $request, $token)
    {
        $contract = Contract::where('token', $token)->firstOrFail();

        if ($contract->status === 'signed') {
            return back()->with('error', 'El contrato ya ha sido firmado.');
        }

        $validated = $request->validate([
            'legal_name' => 'required|string|max:255',
            'tax_id' => 'required|string|max:255',
            'fiscal_address' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'legal_representative' => 'nullable|string|max:255',
            'csf_file' => 'required|file|mimes:pdf,jpg,png|max:10240',
            'accept_terms' => 'required|accepted',
        ]);

        // store csf
        if ($request->hasFile('csf_file')) {
            $contract->csf_file_path = $request->file('csf_file')->store('contracts', 'public');
        }

        $contract->legal_name = $validated['legal_name'];
        $contract->tax_id = $validated['tax_id'];
        $contract->fiscal_address = $validated['fiscal_address'];
        $contract->postal_code = $validated['postal_code'];
        $contract->legal_representative = $validated['legal_representative'];
        $contract->signed_at = now();
        $contract->signature_ip = $request->ip();
        $contract->status = 'signed';
        $contract->save();

        // Update quote status
        $contract->quote->update([
            'status' => 'Contrato Firmado'
        ]);

        // In a real scenario, you'd generate the final PDF here and shoot an email.
        // For now, we just redirect back with a success message.
        return back()->with('success', 'Contrato firmado legalmente.');
    }
}
