<?php

namespace App\Console\Commands;

use App\Models\Client;
use App\Models\Conversation;
use App\Models\WhatsAppNumber;
use Illuminate\Console\Command;

/**
 * Asigna un número de WhatsApp a un cliente, o se lo quita.
 *
 * Hace falta para el modelo de WABA compartida (§4 del plan): con Standard
 * Access se pueden dar de alta hasta 20 números de clientes bajo la WABA de
 * LunAvalos, y es el **número** —no la WABA— lo que determina de quién es cada
 * conversación.
 *
 * Sin esto, un número dado de alta bajo nuestra WABA se queda con `client_id`
 * null, o sea "de LunAvalos": sus conversaciones no las ve el cliente en el
 * portal y el agente de IA que responde es el nuestro, con el prompt
 * equivocado.
 */
class AssignWhatsAppNumber extends Command
{
    protected $signature = 'whatsapp:asignar-numero
        {numero : phone_number_id, o el número tal como se muestra}
        {cliente? : ID del cliente. Sin esto lo deja como número propio de LunAvalos}
        {--migrar : Reasigna también las conversaciones que ya cuelgan del número}';

    protected $description = 'Asigna un número de WhatsApp a un cliente';

    public function handle(): int
    {
        $buscado = (string) $this->argument('numero');

        $numero = WhatsAppNumber::where('phone_number_id', $buscado)
            ->orWhere('display_phone_number', $buscado)
            ->first();

        if (!$numero) {
            $this->error("No hay ningún número «{$buscado}».");
            $this->newLine();
            $this->line('Números registrados:');

            foreach (WhatsAppNumber::with('client')->get() as $n) {
                $this->line(sprintf(
                    '  %-18s %-22s %s',
                    $n->phone_number_id,
                    $n->display_phone_number,
                    $n->client?->business_name ?? 'LunAvalos (propio)',
                ));
            }

            return self::FAILURE;
        }

        $clienteId = $this->argument('cliente') ? (int) $this->argument('cliente') : null;
        $cliente   = null;

        if ($clienteId !== null) {
            $cliente = Client::find($clienteId);

            if (!$cliente) {
                $this->error("No existe el cliente {$clienteId}.");

                return self::FAILURE;
            }
        }

        $antes = $numero->client?->business_name ?? 'LunAvalos (propio)';

        $numero->update(['client_id' => $clienteId]);

        $this->info(sprintf(
            'Número %s: %s → %s',
            $numero->display_phone_number,
            $antes,
            $cliente?->business_name ?? 'LunAvalos (propio)',
        ));

        // Las conversaciones guardan su propio client_id, copiado del número en
        // el momento de crearse. Reasignar el número no las arrastra solo, y
        // dejarlas atrás significa que el cliente no ve su historial y que el
        // agente de IA sigue respondiendo con el prompt del dueño anterior.
        $colgando = Conversation::where('whatsapp_number_id', $numero->id)
            ->where(fn ($q) => $clienteId === null
                ? $q->whereNotNull('client_id')
                : $q->where('client_id', '!=', $clienteId)->orWhereNull('client_id'))
            ->count();

        if ($colgando === 0) {
            return self::SUCCESS;
        }

        if (!$this->option('migrar')) {
            $this->newLine();
            $this->warn("Hay {$colgando} conversación(es) de este número que siguen con el dueño anterior.");
            $this->line('El cliente no las verá y el agente de IA usará el prompt equivocado.');
            $this->line('Corre otra vez con --migrar para reasignarlas.');

            return self::SUCCESS;
        }

        $migradas = Conversation::where('whatsapp_number_id', $numero->id)
            ->update(['client_id' => $clienteId]);

        $this->info("{$migradas} conversación(es) reasignadas.");

        return self::SUCCESS;
    }
}
