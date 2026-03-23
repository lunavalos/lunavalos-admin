<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Queue\SerializesModels;

class RenewalReceiptMail extends Mailable
{
    use Queueable, SerializesModels;

    public $client;
    public $serviceName;
    public $amount;
    public $billingType;
    public $pdfContent;

    /**
     * Create a new message instance.
     */
    public function __construct($client, $serviceName, $amount, $billingType, $pdfContent)
    {
        $this->client = $client;
        $this->serviceName = $serviceName;
        $this->amount = $amount;
        $this->billingType = $billingType;
        $this->pdfContent = $pdfContent;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Recordatorio de Renovación de Servicio - LunAvalos',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.receipt',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [
            Attachment::fromData(fn () => $this->pdfContent, 'Recibo_Pago_'.$this->client->business_name.'.pdf')
                    ->withMime('application/pdf'),
        ];
    }
}
