<?php

namespace App\Support;

use App\Exceptions\RegleMetier;

/**
 * Empreinte d'un enregistrement (verrou optimiste) : le formulaire transporte l'empreinte lue à l'ouverture ;
 * à l'envoi, l'enregistrement relu sous verrou doit avoir la même empreinte, sinon la saisie est périmée.
 */
class Empreinte
{
    public static function de(object|array|null $ligne): string
    {
        $donnees = (array) $ligne;
        ksort($donnees);

        return hash('sha256', json_encode($donnees, JSON_UNESCAPED_UNICODE | JSON_PARTIAL_OUTPUT_ON_ERROR));
    }

    public static function verifier(object|array|null $ligne, ?string $attendue, string $message = 'Cet enregistrement a changé depuis l’ouverture de la page. Rechargez-la avant de recommencer.'): void
    {
        if (! is_string($attendue) || ! hash_equals(self::de($ligne), $attendue)) {
            throw new RegleMetier($message);
        }
    }
}
