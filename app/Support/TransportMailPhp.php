<?php

namespace App\Support;

use Symfony\Component\Mailer\SentMessage;
use Symfony\Component\Mailer\Transport\AbstractTransport;

/**
 * Envoi par la fonction PHP mail(), seul moyen d'envoi autorisé sur l'hébergement mutualisé OVH :
 * le SMTP sortant y est refusé et l'appel direct à sendmail aussi. OVH comptabilise ces envois
 * dans l'onglet « Scripts e-mail » de l'hébergement.
 *
 * Le message est entièrement préparé par Symfony (en-têtes encodés, multipart HTML + texte) ;
 * on en retire seulement To et Subject, que mail() ajoute lui-même.
 */
class TransportMailPhp extends AbstractTransport
{
    protected function doSend(SentMessage $message): void
    {
        $brut = $message->toString();
        $separation = strpos($brut, "\r\n\r\n");
        if ($separation === false) {
            throw new \RuntimeException('Message mal formé.');
        }
        $entetes = substr($brut, 0, $separation);
        $corps = substr($brut, $separation + 4);

        // En-têtes repliés (lignes de continuation) remis sur une ligne, pour pouvoir les trier.
        $lignes = preg_split("/\r\n(?![ \t])/", $entetes);
        $destinataires = null;
        $sujet = '';
        $autres = [];
        foreach ($lignes as $ligne) {
            $ligne = preg_replace("/\r\n[ \t]+/", ' ', $ligne);
            if (stripos($ligne, 'To:') === 0) {
                $destinataires = trim(substr($ligne, 3));
            } elseif (stripos($ligne, 'Subject:') === 0) {
                $sujet = trim(substr($ligne, 8));
            } else {
                $autres[] = $ligne;
            }
        }
        $destinataires ??= implode(', ', array_map(fn ($a) => $a->toString(), $message->getEnvelope()->getRecipients()));

        if (! mail($destinataires, $sujet, $corps, implode("\r\n", $autres))) {
            throw new \RuntimeException('La fonction mail() de l’hébergement a refusé l’envoi (vérifiez l’onglet « Scripts e-mail » dans l’espace client OVH).');
        }
    }

    public function __toString(): string
    {
        return 'phpmail://default';
    }
}
