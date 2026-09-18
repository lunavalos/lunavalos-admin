<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\WhatsAppAccount;
use App\Services\WhatsApp\WhatsAppOnboardingService;
use Illuminate\Console\Command;
use RuntimeException;

/**
 * Da de alta una WABA de nuestro portfolio y le asigna sus números a un cliente.
 *
 * Es el alta que de verdad usamos y la que no tenía camino. Los otros dos
 * comandos no cubren el caso:
 *
 *   - Embedded Signup (pantalla de Conectar) exige un negocio ajeno. Verificado
 *     contra el panel el 2026-09-18: el diálogo pinta LunAvalos Manager en gris
 *     con el motivo «This Meta Business Account owns the app». No hay nada que
 *     conceder.
 *   - `whatsapp:adoptar-waba-propia` solo sabe de la WABA de configuración.
 *
 * El modelo que queda: una WABA por cliente, creada dentro de nuestro portfolio
 * verificado. El cliente no tramita business verification ni toca Facebook, y
 * como el pago y las plantillas son por WABA, conserva su factura y no ve las
 * plantillas de los demás.
 */
class AdoptWhatsAppWaba extends Command
{
    protected $signature = 'whatsapp:adoptar-waba
        {waba_id : El ID de la WABA en Meta}
        {--cliente= : ID del cliente dueño de los números. Sin esto quedan como propios}
        {--numero=* : phone_number_id a asignarle. Sin esto los lista y pregunta}
        {--dry-run : Muestra lo que haría sin escribir nada}';

    protected $description = 'Da de alta una WABA del portfolio propio y asigna sus números a un cliente';

    public function handle(WhatsAppOnboardingService $onboarding): int
    {
        $wabaId = (string) $this->argument('waba_id');

        if ((string) config('services.whatsapp.token') === '') {
            $this->error('Falta WHATSAPP_TOKEN en la configuración.');

            return self::FAILURE;
        }

        $cliente = null;

        if ($this->option('cliente') !== null) {
            $cliente = Client::find((int) $this->option('cliente'));

            if (!$cliente) {
                $this->error("No existe el cliente {$this->option('cliente')}.");

                return self::FAILURE;
            }
        }

        try {
            $disponibles = $onboarding->numerosDe($wabaId);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());
            // El fallo casi siempre es el mismo y el mensaje de Meta no lo dice.
            $this->line('Si Meta habla de permisos: falta darle acceso al system user');
            $this->line('sobre esta WABA en el panel. El token no la ve hasta entonces.');

            return self::FAILURE;
        }

        if ($disponibles === []) {
            $this->error('La WABA no devolvió números.');

            return self::FAILURE;
        }

        $this->line('Números en la WABA:');

        foreach ($disponibles as $n) {
            $this->line(sprintf(
                '  %-18s %-22s %-12s %s',
                $n['id'],
                $n['display_phone_number'] ?? '?',
                $n['status'] ?? 'sin estado',
                $n['verified_name'] ?? '',
            ));
        }

        $this->newLine();

        $numeros = $this->numerosElegidos($disponibles, $cliente);

        if ($numeros === null) {
            return self::FAILURE;
        }

        if ($this->option('dry-run')) {
            $existente = WhatsAppAccount::where('waba_id', $wabaId)->first();

            $this->line($existente
                ? "WABA ya registrada (id {$existente->id}). Se refrescaría."
                : 'WABA no registrada todavía. Se crearía.');

            $this->line($cliente
                ? 'Se asignarían a ' . $cliente->business_name . ': ' . implode(', ', $numeros)
                : 'Los números quedarían como propios de LunAvalos.');

            $this->comment('Nada escrito: --dry-run.');

            return self::SUCCESS;
        }

        try {
            $cuenta = $onboarding->adoptarWaba($wabaId, $cliente, $numeros);
        } catch (RuntimeException $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->info("WABA registrada: {$cuenta->name} ({$cuenta->waba_id})");

        foreach ($cuenta->numbers()->with('client')->get() as $numero) {
            $this->line(sprintf(
                '  · %-22s %-28s %s',
                $numero->display_phone_number,
                $numero->client?->business_name ?? 'LunAvalos (propio)',
                $numero->is_active ? '' : '[inactivo]',
            ));

            // El registro no tumba la conexión, así que sin esto el fallo solo
            // se vería después, como "los envíos no salen".
            if ($numero->registration_error) {
                $this->warn("    registro: {$numero->registration_error}");
            }
        }

        $this->newLine();
        $this->info('App suscrita al webhook de esta WABA.');

        return self::SUCCESS;
    }

    /**
     * Qué números son del cliente.
     *
     * No se asumen todos: Meta regala un número de prueba (+1 555…) a cada WABA
     * nueva, y colárselo al cliente lo deja con dos números activos, que es el
     * caso en el que la API de plataforma falla en vez de adivinar desde cuál
     * enviar. Ya pasó una vez con el +1 555 628-6220 (§10 del plan).
     *
     * @param  array<int, array<string, mixed>>  $disponibles
     * @return string[]|null  null si hay que abortar
     */
    private function numerosElegidos(array $disponibles, ?Client $cliente): ?array
    {
        $ids = array_column($disponibles, 'id');
        $numeros = array_values(array_filter((array) $this->option('numero')));

        if ($numeros !== []) {
            $desconocidos = array_diff($numeros, $ids);

            if ($desconocidos !== []) {
                $this->error('Esta WABA no tiene el número ' . implode(', ', $desconocidos) . '.');

                return null;
            }

            return $numeros;
        }

        // Sin cliente no hay nada que elegir: los números quedan como propios.
        if ($cliente === null) {
            return [];
        }

        if (!$this->input->isInteractive()) {
            $this->error('Indica qué números son del cliente con --numero=<phone_number_id>.');
            $this->line('Sin esto se le asignarían todos, incluido el de prueba de Meta.');

            return null;
        }

        $opciones = [];

        foreach ($disponibles as $n) {
            $opciones[$n['id']] = ($n['display_phone_number'] ?? $n['id'])
                . ' — ' . ($n['verified_name'] ?? 'sin nombre');
        }

        $elegidas = (array) $this->choice(
            "¿Qué números son de {$cliente->business_name}?",
            $opciones,
            null,
            null,
            true,
        );

        // `choice` sobre un array asociativo devuelve la CLAVE, que aquí ya es
        // el phone_number_id. Se traduce igual desde la etiqueta por si alguna
        // versión de Symfony devuelve eso: equivocarse aquí asigna el número
        // que no era, en silencio.
        $porEtiqueta = array_flip($opciones);

        return array_values(array_filter(
            array_map(
                fn ($elegida) => $porEtiqueta[$elegida] ?? $elegida,
                $elegidas,
            ),
            fn ($id) => in_array($id, $ids, true),
        ));
    }
}
