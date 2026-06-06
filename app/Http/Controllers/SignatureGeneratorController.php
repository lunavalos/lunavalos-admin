<?php

namespace App\Http\Controllers;

use App\Models\SignatureTemplate;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;

class SignatureGeneratorController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $client = $user->client ?? null;

        $assignedTemplate = $client?->signatureTemplate;
        $signatureDefaults = $client?->signature_defaults ?? [];

        return Inertia::render('ClientPanel/Signatures', [
            'templates'          => SignatureTemplate::where('is_active', true)->get(),
            'assignedTemplate'   => $assignedTemplate,
            'signatureDefaults'  => $signatureDefaults,
        ]);
    }
}
