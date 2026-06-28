<?php

namespace App\Mail;

use App\Models\Alerta;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;

class AlertaDescuento extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public readonly Alerta $alerta,
        public readonly Collection|bool $resultados = false,
    ) {}

    public function envelope(): Envelope
    {
        $label = $this->alerta->tipoLabel();
        return new Envelope(
            subject: "🔔 Nuevos descuentos en {$label}: {$this->alerta->valor}",
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'emails.alerta_descuento',
        );
    }
}
