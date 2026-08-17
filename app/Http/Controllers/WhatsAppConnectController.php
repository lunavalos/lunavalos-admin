<?php

namespace App\Http\Controllers;

use App\Models\Client;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use RuntimeException;

class WhatsAppConnectController extends Controller
{
    /**
     * Mismo criterio que SocialController y ConversationController: un usuario
     * amarrado a un cliente solo opera ese cliente.
     */
    private function autorizar(Client $client): void
    {
        $propio = request()->user()?->client_id;

        abort_if($propio !== null && $propio !== $client->id, 403, 'Acceso denegado.');
    }

    public function show(Client $client)
    {
        $this->autorizar($client);

        $numeros = $client->whatsappNumbers()->with('account')->get()->map(fn ($n) => [
            'id'                   => $n->id,
            'phone_number_id'      => $n->phone_number_id,
            'display_phone_number' => $n->display_phone_number,
            'verified_name'        => $n->verified_name,
            'quality_rating'       => $n->quality_rating,
            'is_active'            => $n->is_active,
            'waba_id'              => $n->account?->waba_id,
            'account_id'           => $n->account?->id,
            'account_status'       => $n->account?->status,
        ]);

        return Inertia::render('WhatsApp/Connect', [
            'client'   => $client->only(['id', 'business_name']),
            'numeros'  => $numeros,
            // El SDK del navegador necesita ambos. El App Secret nunca sale de
            // aquí: el canje del code ocurre en el servidor.
            'appId'    => config('services.whatsapp.app_id'),
            'configId' => config('services.whatsapp.embedded_signup_config_id'),
        ]);
    }

    /**
     * Cierra el flujo con el code que devolvió el SDK.
     */
    public function store(Request $request, Client $client, WhatsAppOnboardingService $onboarding)
    {
        $this->autorizar($client);

        $datos = $request->validate([
            'code'    => 'required|string',
            'waba_id' => 'nullable|string',
        ]);

        try {
            $onboarding->conectar($client, $datos['code'], $datos['waba_id'] ?? null);
        } catch (RuntimeException $e) {
            return back()->withErrors(['code' => $e->getMessage()]);
        }

        return back()->with('success', 'WhatsApp conectado.');
    }

    public function destroy(Client $client, WhatsAppAccount $account, WhatsAppOnboardingService $onboarding)
    {
        $this->autorizar($client);

        // La cuenta tiene que pertenecer a este cliente: sin esta comprobación,
        // un id en la URL bastaría para desconectar la WABA de otro.
        abort_unless(
            $account->numbers()->where('client_id', $client->id)->exists(),
            403,
            'Esa cuenta no pertenece a este cliente.'
        );

        $onboarding->desconectar($account);

        return back()->with('success', 'WhatsApp desconectado.');
    }
}
