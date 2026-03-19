<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ticket;
use App\Models\TicketMessage;
use Carbon\Carbon;

class AutoCloseInReviewTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:auto-close-in-review';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Cierra automáticamente los tickets en revisión por más de 5 días sin respuesta';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $tickets = Ticket::where('status', 'En Revisión')
            ->where('status_updated_at', '<=', Carbon::now()->subDays(5))
            ->get();

        $count = 0;
        foreach ($tickets as $ticket) {
            $ticket->status = 'Completados';
            $ticket->save();

            TicketMessage::create([
                'ticket_id' => $ticket->id,
                'user_id' => null,
                'message' => 'No se recibió respuesta del cliente.',
            ]);

            $count++;
        }

        $this->info("{$count} tickets cerrados automáticamente por inactividad.");
    }
}
