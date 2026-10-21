<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\EmailSignature;
use App\Models\SignatureAsset;
use App\Models\SignatureTemplate;
use App\Services\Signatures\SignatureRenderer;
use App\Services\Signatures\SignatureUsage;
use App\Support\ProfilePhoto;
use Illuminate\Http\Request;
use Illuminate\Routing\Controllers\HasMiddleware;
use Illuminate\Routing\Controllers\Middleware;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

class EmailSignatureController extends Controller implements HasMiddleware
{
    public static function middleware(): array
    {
        return [
            new Middleware('can:Gestionar Firmas de Empleados'),
        ];
    }

    public function index(Request $request, SignatureUsage $usage)
    {
        $clientId = $request->integer('client_id') ?: null;

        $signatures = EmailSignature::with(['client:id,business_name,signature_template_id', 'client.signatureTemplate:id,name', 'signatureTemplate:id,name'])
            ->when($clientId, fn ($q) => $q->where('client_id', $clientId))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = '%' . $request->string('search') . '%';
                $q->where(fn ($q) => $q->where('name', 'like', $term)->orWhere('email', 'like', $term));
            })
            ->orderBy('name')
            ->get()
            ->map(fn (EmailSignature $s) => [
                'id'             => $s->id,
                'name'           => $s->name,
                'position'       => $s->position,
                'email'          => $s->email,
                'is_active'      => $s->is_active,
                'last_viewed_at' => $s->last_viewed_at?->toDateTimeString(),
                'client'         => $s->client?->business_name,
                'template'       => $s->signatureTemplate?->name ?? $s->client?->signatureTemplate?->name,
                // Propia = se eligió para este empleado; si no, hereda la de la empresa.
                'template_own'   => (bool) $s->signatureTemplate,
                'public_url'     => $s->publicUrl(),
            ]);

        // Conteo por empresa: cuántas firmas activas tiene cada cliente.
        $counts = EmailSignature::query()
            ->where('is_active', true)
            ->selectRaw('client_id, count(*) as total')
            ->groupBy('client_id')
            ->pluck('total', 'client_id');

        $clients = Client::orderBy('business_name')->get(['id', 'business_name'])
            ->map(fn (Client $c) => [
                'id'            => $c->id,
                'business_name' => $c->business_name,
                'signatures'    => (int) ($counts[$c->id] ?? 0),
            ]);

        $client = $clientId ? Client::find($clientId) : null;

        $assets = $client
            ? SignatureAsset::with('signatureTemplate:id,name')->where('client_id', $client->id)->latest('id')->get()
                ->map(fn (SignatureAsset $a) => [
                    'id'         => $a->id,
                    'slot'       => $a->slot,
                    'label'      => $a->label,
                    'template'   => $a->signatureTemplate?->name,
                    'url'        => Storage::disk('public')->url($a->file_path),
                    'link_url'   => $a->link_url,
                    'starts_at'  => $a->starts_at?->toDateString(),
                    'ends_at'    => $a->ends_at?->toDateString(),
                    'repeats_yearly' => $a->repeats_yearly,
                    // Vigente dentro de su grupo (su plantilla, o "todas").
                    'is_current' => SignatureAsset::currentInScope($client->id, $a->slot, $a->signature_template_id)?->id === $a->id,
                ])
            : [];

        return Inertia::render('EmailSignatures/Index', [
            'signatures'    => $signatures,
            'clients'       => $clients,
            'filters'       => ['client_id' => $clientId, 'search' => $request->input('search')],
            'assets'        => $assets,
            'usage'         => $client ? $usage->forClient($client) : [],
            'templates'     => SignatureTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function create(Request $request)
    {
        return Inertia::render('EmailSignatures/Form', [
            'signature'   => null,
            'clients'     => $this->clientOptions(),
            'templates'   => SignatureTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'defaultClientId' => $request->integer('client_id') ?: null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        $signature = new EmailSignature($data);
        $signature->created_by = Auth::id();

        if ($request->hasFile('photo')) {
            $signature->photo_path = ProfilePhoto::store($request->file('photo'), 'email-signatures/photos');
        }

        $signature->save();

        return redirect()->route('email-signatures.edit', $signature)
            ->with('success', 'Firma creada. Comparte el link público con el empleado.');
    }

    public function edit(EmailSignature $emailSignature, SignatureRenderer $renderer)
    {
        $emailSignature->load('client.signatureTemplate', 'signatureTemplate');

        return Inertia::render('EmailSignatures/Form', [
            'signature' => [
                'id'                    => $emailSignature->id,
                'client_id'             => $emailSignature->client_id,
                'signature_template_id' => $emailSignature->signature_template_id,
                'name'                  => $emailSignature->name,
                'position'              => $emailSignature->position,
                'email'                 => $emailSignature->email,
                'phone'                 => $emailSignature->phone,
                'social_links'          => $emailSignature->social_links ?? [],
                'is_active'             => $emailSignature->is_active,
                'photo_url'             => $emailSignature->photo_path ? Storage::disk('public')->url($emailSignature->photo_path) : null,
                'public_url'            => $emailSignature->publicUrl(),
                'last_viewed_at'        => $emailSignature->last_viewed_at?->toDateTimeString(),
                'preview_html'          => $renderer->render($emailSignature),
                'has_template'          => (bool) $emailSignature->effectiveTemplate(),
                'has_logo'              => SignatureAsset::hasLogo($emailSignature->client, $emailSignature->effectiveTemplate()?->id),
            ],
            'clients'   => $this->clientOptions(),
            'templates' => SignatureTemplate::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'defaultClientId' => null,
        ]);
    }

    public function update(Request $request, EmailSignature $emailSignature)
    {
        $data = $this->validated($request);

        if ($request->hasFile('photo')) {
            $old = $emailSignature->photo_path;
            $data['photo_path'] = ProfilePhoto::store($request->file('photo'), 'email-signatures/photos');

            if ($old) {
                Storage::disk('public')->delete($old);
            }
        } elseif ($request->boolean('remove_photo') && $emailSignature->photo_path) {
            Storage::disk('public')->delete($emailSignature->photo_path);
            $data['photo_path'] = null;
        }

        $emailSignature->update($data);

        return redirect()->route('email-signatures.edit', $emailSignature)
            ->with('success', 'Firma actualizada.');
    }

    public function destroy(EmailSignature $emailSignature)
    {
        $clientId = $emailSignature->client_id;
        $emailSignature->delete();

        return redirect()->route('email-signatures.index', ['client_id' => $clientId])
            ->with('success', 'Firma eliminada. Su link público dejó de funcionar.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'client_id'             => 'required|exists:clients,id',
            'signature_template_id' => 'nullable|exists:signature_templates,id',
            'name'                  => 'required|string|max:255',
            'position'              => 'nullable|string|max:255',
            'email'                 => 'nullable|email|max:255',
            'phone'                 => 'nullable|string|max:50',
            'photo'                 => 'nullable|image|max:5120',
            'social_links'          => 'nullable|array',
            'social_links.*'        => 'nullable|url|max:2048',
            'is_active'             => 'boolean',
        ]);

        $data['social_links'] = array_filter(
            array_intersect_key($data['social_links'] ?? [], array_flip(EmailSignature::SOCIAL_NETWORKS))
        );

        unset($data['photo']);

        return $data;
    }

    private function clientOptions()
    {
        return Client::with('signatureTemplate:id,name')
            ->orderBy('business_name')
            ->get(['id', 'business_name', 'signature_template_id'])
            ->map(fn (Client $c) => [
                'id'            => $c->id,
                'business_name' => $c->business_name,
                'template_name' => $c->signatureTemplate?->name,
            ]);
    }
}
