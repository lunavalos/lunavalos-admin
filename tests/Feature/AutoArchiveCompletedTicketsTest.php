<?php

namespace Tests\Feature;

use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Carbon\Carbon;
use Tests\TestCase;

class AutoArchiveCompletedTicketsTest extends TestCase
{
    use RefreshDatabase;

    public function test_tickets_completed_longer_than_30_days_are_archived()
    {
        $creator = User::factory()->create();

        // 1. Ticket completed 31 days ago (should be archived)
        $archivableTicket = Ticket::create([
            'title' => 'Completed old ticket',
            'content' => 'Content',
            'status' => 'Completados',
            'creator_id' => $creator->id,
            'status_updated_at' => Carbon::now()->subDays(31),
        ]);

        // 2. Ticket completed 10 days ago (should NOT be archived)
        $recentCompletedTicket = Ticket::create([
            'title' => 'Completed recent ticket',
            'content' => 'Content',
            'status' => 'Completados',
            'creator_id' => $creator->id,
            'status_updated_at' => Carbon::now()->subDays(10),
        ]);

        // 3. Ticket active but old (should NOT be archived)
        $activeOldTicket = Ticket::create([
            'title' => 'Active old ticket',
            'content' => 'Content',
            'status' => 'En Proceso',
            'creator_id' => $creator->id,
            'status_updated_at' => Carbon::now()->subDays(31),
        ]);

        // Run the command
        $this->artisan('tickets:auto-archive-completed')
            ->expectsOutput('1 tickets completados archivados automáticamente.')
            ->assertExitCode(0);

        // Assert states
        $this->assertTrue($archivableTicket->fresh()->is_archived);
        $this->assertFalse($recentCompletedTicket->fresh()->is_archived);
        $this->assertFalse($activeOldTicket->fresh()->is_archived);
    }
}
