<?php

namespace App\Console\Commands;

use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Registra la WABA propia de LunAvalos en la base de datos.
 *
 * Existe porque Embedded Signup no puede hacerlo: ese flujo sirve para que un
 * negocio ajeno nos comparta su cuenta, y cuando el portfolio dueño de la app
 * es el mismo, Meta ni siquiera ofrece la WABA en la lista. Sin esta fila el
 * webhook descarta nuestros propios mensajes por venir de una "WABA
 * desconocida", y la pantalla de plantillas no tiene contra qué trabajar.
 */
class AdoptWhatsAppOwnWaba extends Command
{
    protected $signature = 'whatsapp:adoptar-waba-propia {--dry-run : Muestra lo que haría sin escribir nada}';

    protected $description = 'Registra la WABA propia (WHATSAPP_BUSINESS_ACCOUNT_ID) y suscribe la app a su webhook';

    public function handle(WhatsAppOnboardingService $onboarding): int
    {
        $wabaId = (string) config('services.whatsapp.business_account_id');

        if ($wabaId === '' || (string) config('services.whatsapp.token') === '') {
            $this->error('Faltan WHATSAPP_BUSINESS_ACCOUNT_ID y/o WHATSAPP_TOKEN.');

            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $existente = WhatsAppAccount::where('waba_id', $wabaId)->first();

            $this->line("WABA: {$wabaId}");
            $this->line($existente
                ? "Ya registrada (id {$existente->id}, {$existente->numbers()->count()} números). Se refrescaría."
                : 'No registrada todavía. Se crearía.');
            $this->comment('Nada escrito: --dry-run.');

            return self::SUCCESS;
        }

        try {
            $cuenta = $onboarding->adoptarWabaPropia();
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $numeros = $cuenta->numbers()->get();

        $this->info("WABA registrada: {$cuenta->name} ({$cuenta->waba_id})");

        foreach ($numeros as $numero) {
            $this->line("  · {$numero->display_phone_number}  [{$numero->phone_number_id}]"
                . "  calidad: " . ($numero->quality_rating ?? 'sin dato'));
        }

        if ($numeros->isEmpty()) {
            $this->warn('La WABA no devolvió números. Revisa que el token tenga acceso.');
        }

        $this->info('App suscrita al webhook de esta WABA.');

        return self::SUCCESS;
    }
}
