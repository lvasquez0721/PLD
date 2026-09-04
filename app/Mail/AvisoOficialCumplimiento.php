<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvisoOficialCumplimiento extends Mailable
{
    use Queueable, SerializesModels;

    public array $datos;

    /**
     * Create a new message instance.
     */
    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->datos['asunto'] ?? 'Detección en listas negras / PPE - PLD',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.aviso-oficial-cumplimiento',
            with: [
                'datos' => $this->datos,
            ],
        );
    }
}
