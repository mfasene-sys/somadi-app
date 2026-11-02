<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;

/** Code PIN de connexion. Envoi immédiat (pas de file d'attente sur l'hébergement OVH). */
class CodeConnexionMail extends Mailable
{
    public function __construct(public User $user, public string $code, public int $minutes)
    {
    }

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre code de connexion SOMADI : '.$this->code);
    }

    public function content(): Content
    {
        return new Content(
            html: 'emails.code-connexion',
            text: 'emails.code-connexion-texte',
            with: ['heure' => now()->format('d/m/Y à H:i')],
        );
    }
}
