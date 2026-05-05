<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use App\Models\Setting;

class TeamTicketReportMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public $teamMember;   // User model
    public $tickets;      // Collection of tickets assigned to this user
    public $dateFrom;     // string: Y-m-d
    public $dateTo;       // string: Y-m-d
    public $companyName;  // string: company commercial name from settings
    public $logoUrl;      // string|null: public URL to the company logo

    public function __construct($teamMember, $tickets, string $dateFrom, string $dateTo, string $companyName)
    {
        $this->teamMember  = $teamMember;
        $this->tickets     = $tickets;
        $this->dateFrom    = $dateFrom;
        $this->dateTo      = $dateTo;
        $this->companyName = $companyName;

        // Resolve logo as a publicly accessible URL (email clients block data URIs)
        $logoPath = Setting::where('key', 'company_logo')->value('value');
        $this->logoUrl = $logoPath ? Storage::disk('public')->url($logoPath) : null;
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
