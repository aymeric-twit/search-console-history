<?php

namespace App\Controller;

use App\Auth\GoogleOAuth;

/**
 * Gère les routes d'authentification OAuth2 Google.
 */
class AuthController
{
    private GoogleOAuth $oauth;

    public function __construct()
    {
        $this->oauth = new GoogleOAuth();
    }

    /** Redirige vers Google pour lancer le flux OAuth. */
    public function login(): void
    {
        $url = $this->oauth->getAuthUrl();
        header('Location: ' . $url);
        exit;
    }

    /** Callback OAuth : échange le code et ferme la popup. */
    public function callback(): void
    {
        if (empty($_GET['code'])) {
            $this->fermerPopup(false, 'Paramètre "code" manquant.');
            return;
        }

        try {
            $this->oauth->handleCallback($_GET['code']);
            $this->fermerPopup(true);
        } catch (\Throwable $e) {
            $this->fermerPopup(false, $e->getMessage());
        }
    }

    /** Révoque le token et redirige. */
    public function logout(): void
    {
        try {
            $this->oauth->revokeToken();
        } catch (\Throwable $e) {
            // Ignore les erreurs de révocation
        }

        $prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
        header('Location: ' . $prefix . '/');
        exit;
    }

    /**
     * Ferme la popup OAuth et notifie la fenêtre parente via postMessage.
     * Fallback : redirige si la popup n'a pas de window.opener.
     */
    private function fermerPopup(bool $succes, string $erreur = ''): void
    {
        $donnees = $succes
            ? json_encode(['succes' => true])
            : json_encode(['succes' => false, 'erreur' => $erreur]);

        $prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
        $fallbackUrl = $prefix . '/';

        echo '<!DOCTYPE html><html><head><title>OAuth</title></head><body><script>';
        echo 'if(window.opener){window.opener.postMessage(' . $donnees . ',"*");window.close();}';
        echo 'else{window.location.href="' . htmlspecialchars($fallbackUrl) . '";}';
        echo '</script></body></html>';
        exit;
    }
}
