<?php

use Illuminate\Support\Facades\Broadcast;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Public channel: any authenticated user viewing this ticket can receive live messages
Broadcast::channel('ticket.{ticketId}', function ($user, $ticketId) {
    return auth()->check(); // open to any logged-in user — adjust if you need finer control
});

