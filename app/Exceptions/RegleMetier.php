<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Refus d'une règle de gestion. Le message est destiné à l'utilisateur : la transaction est annulée
 * et le formulaire est réaffiché avec ce message.
 */
class RegleMetier extends RuntimeException
{
}
