<?php

namespace App\Mail;

use App\Alerts\AlertMessage;
use App\Models\Incident;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

class IncidentAlert extends Mailable
{
    public function __construct(public Incident $incident, public string $kind) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: '[NetWatch] '.AlertMessage::subject($this->incident, $this->kind));
    }

    public function content(): Content
    {
        return new Content(htmlString: nl2br(e(AlertMessage::body($this->incident, $this->kind))));
    }
}
