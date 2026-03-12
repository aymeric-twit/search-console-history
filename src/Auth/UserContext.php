<?php

namespace App\Auth;

/**
 * Helper centralisé pour accéder à l'ID utilisateur courant.
 *
 * En mode plateforme (PLATFORM_EMBEDDED), utilise \Auth::id().
 * En standalone, retourne 0 (utilisateur par défaut).
 */
class UserContext
{
    /** Retourne l'ID utilisateur plateforme ou 0 en standalone. */
    public static function id(): int
    {
        if (defined('PLATFORM_EMBEDDED') && class_exists('\\Auth')) {
            return \Auth::id() ?? 0;
        }

        return 0;
    }
}
