<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Setting;

class TeamTicketReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $teamMember;    // User model
    public $tickets;       // Collection of tickets assigned to this user
    public $dateFrom;      // string: Y-m-d
    public $dateTo;        // string: Y-m-d
    public $companyName;   // string: company commercial name from settings
    public $logoFilePath;  // string|null: absolute filesystem path to the logo (for CID embedding)

    public function __construct($teamMember, $tickets, string $dateFrom, string $dateTo, string $companyName)
    {
        $this->teamMember  = $teamMember;
        $this->tickets     = $tickets;
        $this->dateFrom    = $dateFrom;
        $this->dateTo      = $dateTo;
        $this->companyName = $companyName;

        // Resolve logo as absolute filesystem path for CID embedding
        // (works in all email clients regardless of APP_URL)
        $logoPath = Setting::where('key', 'company_logo')->value('value');
        if ($logoPath) {
            $fullPath = storage_path('app/public/' . $logoPath);
            $this->logoFilePath = file_exists($fullPath) ? $fullPath : null;
        }
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "Reporte de Tickets — {$this->teamMember->name} ({$this->dateFrom} al {$this->dateTo})",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.team_ticket_report',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
