<?php
$pageTitle   = 'Search Console — Connexion';
$currentPage = 'dashboard';
$prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
ob_start();
?>

<div class="auth-section">
    <div class="auth-card">
        <div class="auth-header">
            <h2>Google Search Console</h2>
            <span class="gsc-status-badge gsc-status-deconnecte">
                <i>&#9679;</i> Non connecté
            </span>
        </div>

        <p class="auth-description">
            Connectez-vous avec votre compte Google pour synchroniser et visualiser
            vos données Search Console (clics, impressions, positions, requêtes).
        </p>

        <a href="<?= htmlspecialchars($authUrl) ?>" class="btn">
            Se connecter avec Google
        </a>

        <div class="auth-info">
            <small>
                Scope demandé : <code>webmasters.readonly</code> (lecture seule).
                Vos identifiants ne sont jamais stockés.
            </small>
        </div>
    </div>
</div>

<?php
$content = ob_get_clean();
require __DIR__ . '/layout.php';
?>
