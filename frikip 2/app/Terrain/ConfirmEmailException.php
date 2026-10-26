<?php

namespace App\Terrain;

use RuntimeException;

/** Inscription réussie, mais Supabase demande de confirmer l'e-mail avant la première connexion. */
class ConfirmEmailException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Confirme ton adresse e-mail grâce au lien reçu, puis connecte-toi.');
    }
}
