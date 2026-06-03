<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Ticket;
use Carbon\Carbon;

class AutoArchiveCompletedTickets extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'tickets:auto-archive-completed';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Archiva automáticamente los tickets completados por más de 30 días';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $count = Ticket::where('status', 'Completados')
            ->where('is_archived', false)
            ->where('status_updated_at', '<=', Carbon::now()->subDays(30))
            ->update(['is_archived' => true]);

        $this->info("{$count} tickets completados archivados automáticamente.");
    }
}
