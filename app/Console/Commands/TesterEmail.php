<?php

namespace App\Console\Commands;

use App\Mail\CodeConnexionMail;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

/** Envoie un e-mail d'essai (même modèle que le code de connexion) pour valider la configuration SMTP. */
class TesterEmail extends Command
{
    protected $signature = 'somadi:tester-email {email}';

    protected $description = 'Envoie un e-mail d\'essai pour vérifier la configuration d\'envoi (codes de connexion)';

    public function handle(): int
    {
        $email = (string) $this->argument('email');
        if (! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Adresse e-mail invalide.');

            return self::FAILURE;
        }
        $user = new User(['email' => $email, 'nom' => 'Essai', 'prenom' => 'Test']);
        try {
            Mail::to($email)->send(new CodeConnexionMail($user, '000000', 10));
        } catch (\Throwable $e) {
            $this->error('Envoi impossible : '.$e->getMessage());
            $this->line('Sur l\'hébergement mutualisé OVH, la console SSH n\'a pas le droit d\'envoyer d\'e-mails : testez depuis le site,');
            $this->line('Administration → Utilisateurs → « Tester l\'envoi d\'e-mail » (avec MAIL_MAILER=phpmail).');

            return self::FAILURE;
        }
        $this->info('E-mail d\'essai envoyé à '.$email.' via « '.config('mail.default').' » (code fictif 000000). Vérifiez la réception et les courriers indésirables.');

        return self::SUCCESS;
    }
}
