<?php

namespace App\Mail;

use App\Models\Submission;
use App\Models\SubmissionDocument;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Address;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/**
 * Correo al estudiante con el documento que subió un área (Matrícula, Programa, Facultad...).
 *
 * El documento va ADJUNTO: el estudiante lo descarga directo desde Gmail.
 *
 * Remitente:
 *  - Área con su correo conectado (Organización → Correos) → sale desde su cuenta y queda en sus "Enviados".
 *  - MAIL_FROM_AREA=true  → se envía DESDE el correo del área (requiere que tu servidor de correo
 *                           permita enviar como esa dirección, p. ej. Google Workspace de la UDH).
 *  - MAIL_FROM_AREA=false → se envía desde MAIL_FROM_ADDRESS con el nombre del área,
 *                           y las respuestas del estudiante llegan al correo del área.
 */
class DocumentoTramiteMail extends Mailable
{
    public function __construct(
        public Submission $submission,
        public SubmissionDocument $document,
        public string $areaName,
        public ?string $areaEmail,
        public bool $isFinal,
        public bool $ownAccount = false,
    ) {}

    public function envelope(): Envelope
    {
        // Cuenta propia del área: el remitente ES el área. Si no, la cuenta general con el nombre del área.
        $fromArea = $this->areaEmail && ($this->ownAccount || config('mail.from_area'));

        return new Envelope(
            from: new Address($fromArea ? $this->areaEmail : config('mail.from.address'), $this->areaName),
            replyTo: $this->areaEmail ? [new Address($this->areaEmail, $this->areaName)] : [],
            subject: ($this->isFinal ? 'Tu documento está listo' : 'Nuevo documento en tu trámite')
                ." · {$this->submission->procedure_name} · {$this->submission->code} (adjunto)",
        );
    }

    public function content(): Content
    {
        return new Content(view: 'mail.documento');
    }

    public function attachments(): array
    {
        return [
            Attachment::fromStorageDisk('local', $this->document->path)
                ->as($this->document->name)
                ->withMime($this->document->mime ?: 'application/octet-stream'),
        ];
    }
}
