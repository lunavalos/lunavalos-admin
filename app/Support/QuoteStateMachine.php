<?php

namespace App\Support;

use App\Models\Quote;
use InvalidArgumentException;

/**
 * Pequeña máquina de estados para Quote.
 * Las transiciones permitidas se configuran en config/quotes.php.
 *
 * Estados terminales: Convertida, Rechazada.
 */
class QuoteStateMachine
{
    public static function statuses(): array
    {
        return (array) config('quotes.statuses', []);
    }

    public static function transitions(): array
    {
        return (array) config('quotes.transitions', []);
    }

    public static function canTransition(string $from, string $to): bool
    {
        $allowed = self::transitions()[$from] ?? [];
        return in_array($to, $allowed, true);
    }

    public static function assertCanTransition(string $from, string $to): void
    {
        if (! self::canTransition($from, $to)) {
            throw new InvalidArgumentException(
                "Transición no permitida: '{$from}' → '{$to}'."
            );
        }
    }

    public static function isTerminal(string $status): bool
    {
        return empty(self::transitions()[$status] ?? []);
    }

    public static function transition(Quote $quote, string $to, ?int $userId = null): Quote
    {
        self::assertCanTransition($quote->status, $to);

        $payload = ['status' => $to];

        if ($to === 'Convertida') {
            $payload['converted_at']          = now();
            $payload['converted_by_user_id']  = $userId;
        }

        $quote->update($payload);

        return $quote->refresh();
    }
}
