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

        // Las plantillas privadas solo son visibles para el cliente al que están asignadas.
        $templates = SignatureTemplate::where('is_active', true)
            ->where(function ($query) use ($client) {
                $query->where('is_private', false);

                if ($client) {
                    $query->orWhere('id', $client->signature_template_id);
                }
            })
            ->get();

        return Inertia::render('ClientPanel/Signatures', [
            'templates'          => $templates,
            'assignedTemplate'   => $assignedTemplate,
            'signatureDefaults'  => $signatureDefaults,
        ]);
    }
}
