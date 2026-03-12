<?php

namespace App\Auth;

use App\Database\Connection;
use Google\Client as GoogleClient;

/**
 * Gestion de l'authentification OAuth2 Google.
 *
 * Supporte le multi-utilisateur : chaque user a son propre token.
 */
class GoogleOAuth
{
    private GoogleClient $client;
    private int $userId;

    /**
     * @param int|null $userId ID utilisateur (null = UserContext::id())
     */
    public function __construct(?int $userId = null)
    {
        $this->userId = $userId ?? UserContext::id();

        $this->client = new GoogleClient();
        $this->client->setClientId($_ENV['GOOGLE_CLIENT_ID']);
        $this->client->setClientSecret($_ENV['GOOGLE_CLIENT_SECRET']);
        $this->client->setRedirectUri($_ENV['GOOGLE_REDIRECT_URI']);
        $this->client->addScope('https://www.googleapis.com/auth/webmasters.readonly');
        $this->client->setAccessType('offline');
        $this->client->setPrompt('consent');
        $this->client->setIncludeGrantedScopes(true);
    }

    /** Retourne l'URL Google pour démarrer le flux OAuth. */
    public function getAuthUrl(): string
    {
        return $this->client->createAuthUrl();
    }

    /**
     * Échange le code d'autorisation contre un access + refresh token
     * et les stocke en base de données pour l'utilisateur courant.
     */
    public function handleCallback(string $code): array
    {
        $token = $this->client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new \RuntimeException('OAuth error: ' . ($token['error_description'] ?? $token['error']));
        }

        $this->saveToken($token);

        return $token;
    }

    /**
     * Retourne un GoogleClient authentifié, prêt à appeler l'API.
     * Rafraîchit le token automatiquement si expiré.
     */
    public function getAuthenticatedClient(): GoogleClient
    {
        $token = $this->loadToken();

        if (!$token) {
            throw new \RuntimeException('Aucun token OAuth enregistré. Veuillez vous authentifier.');
        }

        $this->client->setAccessToken([
            'access_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'],
            'token_type'    => $token['token_type'],
            'expires_in'    => max(0, strtotime($token['expires_at']) - time()),
            'created'       => strtotime($token['created_at']),
        ]);

        if ($this->client->isAccessTokenExpired()) {
            if (empty($token['refresh_token'])) {
                throw new \RuntimeException('Token expiré et pas de refresh_token disponible.');
            }

            $newToken = $this->client->fetchAccessTokenWithRefreshToken($token['refresh_token']);

            if (isset($newToken['error'])) {
                throw new \RuntimeException('Refresh token error: ' . ($newToken['error_description'] ?? $newToken['error']));
            }

            if (empty($newToken['refresh_token'])) {
                $newToken['refresh_token'] = $token['refresh_token'];
            }

            $this->saveToken($newToken);
        }

        return $this->client;
    }

    /** Vérifie si un token valide est disponible pour l'utilisateur courant. */
    public function hasToken(): bool
    {
        $token = $this->loadToken();
        if (!$token) {
            return false;
        }

        if (strtotime($token['expires_at']) <= time() && !empty($token['refresh_token'])) {
            try {
                $this->getAuthenticatedClient();
            } catch (\Throwable $e) {
                return false;
            }
        }

        return true;
    }

    /** Supprime les tokens de l'utilisateur courant (déconnexion). */
    public function revokeToken(): void
    {
        $token = $this->loadToken();
        if ($token) {
            try {
                $this->client->revokeToken($token['access_token']);
            } catch (\Throwable $e) {
                // Ignorer les erreurs de révocation Google
            }
        }

        $stmt = Connection::get()->prepare('DELETE FROM sc_oauth_tokens WHERE user_id = :uid');
        $stmt->execute(['uid' => $this->userId]);
    }

    // ------------------------------------------------------------------
    // Persistence — filtrée par user_id
    // ------------------------------------------------------------------

    private function saveToken(array $token): void
    {
        $db = Connection::get();

        $expiresAt = date('Y-m-d H:i:s', time() + ($token['expires_in'] ?? 3600));
        $scope = $token['scope'] ?? '';

        // Supprimer le token existant de cet utilisateur
        $stmt = $db->prepare('DELETE FROM sc_oauth_tokens WHERE user_id = :uid');
        $stmt->execute(['uid' => $this->userId]);

        $stmt = $db->prepare(
            'INSERT INTO sc_oauth_tokens (user_id, access_token, refresh_token, token_type, expires_at, scope)
             VALUES (:user_id, :access_token, :refresh_token, :token_type, :expires_at, :scope)'
        );

        $stmt->execute([
            'user_id'       => $this->userId,
            'access_token'  => $token['access_token'],
            'refresh_token' => $token['refresh_token'] ?? null,
            'token_type'    => $token['token_type'] ?? 'Bearer',
            'expires_at'    => $expiresAt,
            'scope'         => $scope,
        ]);
    }

    private function loadToken(): ?array
    {
        $stmt = Connection::get()->prepare(
            'SELECT * FROM sc_oauth_tokens WHERE user_id = :uid ORDER BY id DESC LIMIT 1'
        );
        $stmt->execute(['uid' => $this->userId]);
        $row = $stmt->fetch();

        return $row ?: null;
    }
}
