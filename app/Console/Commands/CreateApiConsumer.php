<?php

namespace App\Console\Commands;

use App\Models\ApiConsumer;
use App\Models\Client;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

/**
 * Da de alta un sistema externo y emite su token.
 *
 * Va por consola y no por pantalla a propósito: el token en claro se ve UNA
 * vez —Sanctum solo guarda su hash— y una pantalla web lo dejaría en el
 * historial del navegador y en el payload de Inertia. Aquí sale en la terminal
 * de quien lo crea y nada más.
 */
class CreateApiConsumer extends Command
{
    protected $signature = 'api:consumidor
        {nombre : Nombre del sistema, p. ej. "klwebapp"}
        {--client= : ID del cliente al que queda atado. Sin esto es interno de LunAvalos y podrá operar sobre cualquiera}
        {--webhook= : URL a la que entregarle los mensajes entrantes}
        {--permisos=* : Habilidades del token. Por omisión todas}
        {--expira= : Días de vigencia del token. Por omisión no caduca}';

    protected $description = 'Crea una integración de la API de plataforma y emite su token';

    public function handle(): int
    {
        $nombre = $this->argument('nombre');
        $slug   = Str::slug($nombre);

        if (ApiConsumer::where('slug', $slug)->exists()) {
            $this->error("Ya existe una integración con el slug «{$slug}».");
            $this->line('Para emitirle otro token, usa api:token.');

            return self::FAILURE;
        }

        $clientId = $this->option('client') ? (int) $this->option('client') : null;

        if ($clientId !== null && !Client::whereKey($clientId)->exists()) {
            $this->error("No existe el cliente {$clientId}.");

            return self::FAILURE;
        }

        $permisos = $this->option('permisos') ?: ApiConsumer::ABILITIES;

        if ($invalidos = array_diff($permisos, ApiConsumer::ABILITIES)) {
            $this->error('Permisos desconocidos: ' . implode(', ', $invalidos));
            $this->line('Disponibles: ' . implode(', ', ApiConsumer::ABILITIES));

            return self::FAILURE;
        }

        $consumidor = ApiConsumer::create([
            'name'        => $nombre,
            'slug'        => $slug,
            'client_id'   => $clientId,
            'webhook_url' => $this->option('webhook'),
            // Se genera aquí y se enseña una vez: es la llave con la que el
            // receptor verifica que la entrega salió de nosotros.
            'webhook_secret' => $this->option('webhook') ? Str::random(64) : null,
            'status'      => ApiConsumer::STATUS_ACTIVE,
        ]);

        $expira = $this->option('expira')
            ? now()->addDays((int) $this->option('expira'))
            : null;

        $token = $consumidor->createToken($slug . '-' . now()->format('Ymd'), $permisos, $expira);

        $this->info("Integración «{$nombre}» creada.");
        $this->newLine();

        $this->table(['Campo', 'Valor'], array_filter([
            ['Slug',    $slug],
            ['Alcance', $clientId
                ? "cliente {$clientId} (atado)"
                : 'interno de LunAvalos — debe mandar client_id en cada petición'],
            ['Permisos', implode(', ', $permisos)],
            ['Expira',   $expira?->toDateString() ?? 'nunca'],
            $this->option('webhook') ? ['Webhook', $this->option('webhook')] : null,
        ]));

        $this->newLine();
        $this->warn('Esto se muestra UNA sola vez. Cópialo ahora:');
        $this->newLine();
        $this->line('  Authorization: Bearer ' . $token->plainTextToken);

        if ($this->option('webhook')) {
            $this->newLine();
            $this->line('  Secreto del webhook: ' . $consumidor->webhook_secret);
            $this->line('  Verificar con: hash_hmac("sha256", $cuerpoCrudo, $secreto)');
            $this->line('  y comparar contra la cabecera X-LunAvalos-Signature.');
        }

        return self::SUCCESS;
    }
}
