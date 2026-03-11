<?php $prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : ''; ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Connexion — Search Console</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="<?= $prefix ?>/assets/css/app.css">
</head>
<body>
    <div class="auth-page">
        <div class="auth-card">
            <h2>Search Console Dashboard</h2>

            <?php if (!empty($hasToken)): ?>
                <p>Vous êtes connecté à Google Search Console.</p>
                <a href="<?= $prefix ?>/" class="btn btn-success">Accéder au dashboard</a>
                <br><br>
                <a href="<?= $prefix ?>/auth/logout" class="btn btn-danger">Déconnexion</a>
            <?php else: ?>
                <p>Connectez-vous avec votre compte Google pour accéder aux données Search Console.</p>
                <a href="<?= $prefix ?>/auth/login" class="btn">Se connecter avec Google</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
