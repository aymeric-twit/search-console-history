<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle ?? 'Search Console Dashboard') ?></title>
    <link rel="stylesheet" href="/assets/css/app.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
</head>
<body>
    <header class="navbar">
        <h1>Search Console</h1>
        <nav>
            <a href="/" class="<?= ($currentPage ?? '') === 'dashboard' ? 'active' : '' ?>">Dashboard</a>
            <a href="/sync-status" class="<?= ($currentPage ?? '') === 'sync' ? 'active' : '' ?>">Synchronisations</a>
            <a href="/auth/logout">Déconnexion</a>
        </nav>
    </header>

    <main class="container">
        <?= $content ?? '' ?>
    </main>
</body>
</html>
