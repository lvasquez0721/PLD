<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class AvisoAlertaConsolidada extends Mailable
{
    use Queueable, SerializesModels;

    public array $datos;

    public function __construct(array $datos)
    {
        $this->datos = $datos;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: $this->datos['asunto'] ?? 'Alerta PLD generada - Tláloc Seguros',
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alerta-consolidada',
            with: [
                'datos' => $this->datos,
            ],
        );
    }
}
