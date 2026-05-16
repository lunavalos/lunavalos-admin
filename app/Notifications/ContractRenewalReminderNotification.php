<?php

namespace App\Notifications;

use App\Models\Contract;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ContractRenewalReminderNotification extends Notification
{
    use Queueable;

    public function __construct(public Contract $contract, public int $daysRemaining)
    {
    }

    public function via($notifiable): array
    {
        return ['mail'];
    }

    public function toMail($notifiable): MailMessage
    {
        $contract = $this->contract;
        $days = $this->daysRemaining;
        $when = $days <= 0
            ? 'venció ' . abs($days) . ' día(s)'
            : "vence en {$days} día(s)";

        $mail = (new MailMessage())
            ->subject("Renovación de contrato {$contract->contract_number} — {$when}")
            ->greeting('Hola,')
            ->line("Tu contrato **{$contract->contract_number}** {$when} (fecha fin: " . $contract->end_date?->format('d/m/Y') . ').')
            ->line('Monto mensual: $' . number_format((float) $contract->monthly_amount, 2))
            ->line('Si deseas continuar el servicio, contáctanos para iniciar la renovación.')
            ->action('Ver contrato', url("/contratodeservicio/{$contract->token}"))
            ->line('Gracias por confiar en nosotros.');

        if ($cc = config('quotes.renewals.admin_cc')) {
            $mail->cc($cc);
        }

        return $mail;
    }
}
