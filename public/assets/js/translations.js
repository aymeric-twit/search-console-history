var TRANSLATIONS = {
    fr: {
        // ── Navbar ────────────────────────────────────────────────────────
        'nav.dashboard':            'Dashboard',
        'nav.synchronisations':     'Synchronisations',

        // ── Dashboard ─ Connexion GSC ─────────────────────────────────────
        'gsc.titre':                'Google Search Console',
        'gsc.verification':         'Vérification...',
        'gsc.connecte':             'Connecté',
        'gsc.nonConnecte':          'Non connecté',
        'gsc.connecter':            'Connecter Google Search Console',
        'gsc.deconnecter':          'Déconnecter',
        'gsc.connexion':            'Connexion...',
        'gsc.erreurConnexion':      'Erreur de connexion',
        'gsc.erreurReseau':         'Erreur réseau',
        'gsc.nonConfigure':         'La connexion Google Search Console n\u2019est pas disponible. Contactez l\u2019administrateur.',

        // ── Dashboard ─ Filtres ───────────────────────────────────────────
        'filtres.propriete':        'Propriété GSC',
        'filtres.dateDebut':        'Date de début',
        'filtres.dateFin':          'Date de fin',
        'filtres.appareil':         'Appareil',
        'filtres.tous':             'Tous',
        'filtres.pays':             'Pays',
        'filtres.avances':          'Filtres avancés',
        'filtres.requete':          'Requête',
        'filtres.requetePlaceholder': 'Filtrer par mot-clé...',
        'filtres.page':             'Page',
        'filtres.pagePlaceholder':  '/chemin...',
        'filtres.filtrer':          'Filtrer',
        'filtres.aucunSite':        'Aucun site importé.',
        'filtres.lancerSync':       'Lancez une synchronisation',
        'filtres.pourImporter':     'pour importer vos propriétés.',

        // ── Dashboard ─ Aide ──────────────────────────────────────────────
        'aide.commentCaMarche':     'Comment ça marche',
        'aide.connexionOauth':      'Connexion OAuth',
        'aide.connexionOauthDesc':  'connectez votre compte Google pour accéder à Search Console.',
        'aide.synchronisation':     'Synchronisation',
        'aide.synchronisationDesc': 'les données sont synchronisées automatiquement.',
        'aide.tendances':           'Tendances',
        'aide.tendancesDesc':       'consultez les tendances quotidiennes (clics, impressions, CTR, position).',
        'aide.segments':            'Segments',
        'aide.segmentsDesc':        'filtrez par requête, page, appareil et pays.',
        'aide.comparaison':         'Comparaison',
        'aide.comparaisonDesc':     'comparez deux périodes pour détecter les évolutions.',
        'aide.quota':               'Quota',
        'aide.quotaDesc':           'Aucun quota — synchronisation illimitée.',

        // ── Dashboard ─ KPI ───────────────────────────────────────────────
        'kpi.clicks':               'Clicks',
        'kpi.impressions':          'Impressions',
        'kpi.ctrMoyen':             'CTR moyen',
        'kpi.positionMoyenne':      'Position moyenne',
        'kpi.vsPeriodePrec':        'vs période préc.',

        // ── Dashboard ─ Graphiques ────────────────────────────────────────
        'graphique.tendance':       'Tendance quotidienne',
        'graphique.appareils':      'Répartition par appareil',
        'graphique.topPays':        'Top pays',

        // ── Dashboard ─ Tableaux ──────────────────────────────────────────
        'tableau.topRequetes':      'Top requêtes',
        'tableau.topPages':         'Top pages',
        'tableau.requete':          'Requête',
        'tableau.page':             'Page',
        'tableau.clicks':           'Clicks',
        'tableau.impressions':      'Impressions',
        'tableau.ctr':              'CTR',
        'tableau.position':         'Position',
        'tableau.chargement':       'Chargement...',
        'tableau.aucuneDonnee':     'Aucune donnée',

        // ── Sync Status ───────────────────────────────────────────────────
        'sync.titre':               'Historique des synchronisations',
        'sync.tousLesSites':        'Tous les sites',
        'sync.lancer':              'Lancer la synchronisation',
        'sync.aucunSiteImporte':    'Aucun site importé — la première sync importera vos sites depuis Google.',
        'sync.enCours':             'Synchronisation en cours',
        'sync.demarrage':           'Démarrage...',
        'sync.enAttente':           'En attente de démarrage...',
        'sync.syncEnCours':         'Synchronisation en cours...',
        'sync.taches':              'tâches',
        'sync.terminee':            'Synchronisation terminée ! Rechargement dans 3s...',
        'sync.erreur':              'Erreur :',
        'sync.inconnue':            'inconnue',
        'sync.syncEnCoursBtn':      'Sync en cours...',
        'sync.vide':                'vide',
        'sync.lignes':              'lignes',

        // ── Sync Status ─ Tableau ─────────────────────────────────────────
        'syncTab.id':               'ID',
        'syncTab.site':             'Site',
        'syncTab.type':             'Type',
        'syncTab.periode':          'Période',
        'syncTab.lignes':           'Lignes',
        'syncTab.inserees':         'Insérées',
        'syncTab.new':              'New',
        'syncTab.maj':              'Maj',
        'syncTab.duree':            'Durée',
        'syncTab.statut':           'Statut',
        'syncTab.erreur':           'Erreur',
        'syncTab.date':             'Date',
        'syncTab.ok':               'OK',
        'syncTab.vide':             'Vide',
        'syncTab.erreurBadge':      'Erreur',
        'syncTab.enCours':          'En cours',
        'syncTab.aucuneSync':       'Aucune synchronisation enregistrée',

        // ── Erreurs API ───────────────────────────────────────────────────
        'api.nonAuthentifie':       'Non authentifié',
        'api.oauthNonConfigure':    'OAuth non configuré',
        'api.siteNonTrouve':        'Site non trouvé ou accès refusé',
        'api.jobIntrouvable':       'Job introuvable',
        'api.siteIdRequis':         'site_id requis',
        'api.pageNonTrouvee':       'Page non trouvée',

        // ── Chart.js labels ───────────────────────────────────────────────
        'chart.clicks':             'Clicks',
        'chart.impressions':        'Impressions',
    },
    en: {
        // ── Navbar ────────────────────────────────────────────────────────
        'nav.dashboard':            'Dashboard',
        'nav.synchronisations':     'Synchronizations',

        // ── Dashboard ─ Connexion GSC ─────────────────────────────────────
        'gsc.titre':                'Google Search Console',
        'gsc.verification':         'Checking...',
        'gsc.connecte':             'Connected',
        'gsc.nonConnecte':          'Not connected',
        'gsc.connecter':            'Connect Google Search Console',
        'gsc.deconnecter':          'Disconnect',
        'gsc.connexion':            'Connecting...',
        'gsc.erreurConnexion':      'Connection error',
        'gsc.erreurReseau':         'Network error',
        'gsc.nonConfigure':         'Google Search Console connection is not available. Contact the administrator.',

        // ── Dashboard ─ Filtres ───────────────────────────────────────────
        'filtres.propriete':        'GSC Property',
        'filtres.dateDebut':        'Start date',
        'filtres.dateFin':          'End date',
        'filtres.appareil':         'Device',
        'filtres.tous':             'All',
        'filtres.pays':             'Country',
        'filtres.avances':          'Advanced filters',
        'filtres.requete':          'Query',
        'filtres.requetePlaceholder': 'Filter by keyword...',
        'filtres.page':             'Page',
        'filtres.pagePlaceholder':  '/path...',
        'filtres.filtrer':          'Filter',
        'filtres.aucunSite':        'No site imported.',
        'filtres.lancerSync':       'Run a synchronization',
        'filtres.pourImporter':     'to import your properties.',

        // ── Dashboard ─ Aide ──────────────────────────────────────────────
        'aide.commentCaMarche':     'How it works',
        'aide.connexionOauth':      'OAuth Connection',
        'aide.connexionOauthDesc':  'connect your Google account to access Search Console.',
        'aide.synchronisation':     'Synchronization',
        'aide.synchronisationDesc': 'data is synchronized automatically.',
        'aide.tendances':           'Trends',
        'aide.tendancesDesc':       'view daily trends (clicks, impressions, CTR, position).',
        'aide.segments':            'Segments',
        'aide.segmentsDesc':        'filter by query, page, device and country.',
        'aide.comparaison':         'Comparison',
        'aide.comparaisonDesc':     'compare two periods to detect changes.',
        'aide.quota':               'Quota',
        'aide.quotaDesc':           'No quota — unlimited synchronization.',

        // ── Dashboard ─ KPI ───────────────────────────────────────────────
        'kpi.clicks':               'Clicks',
        'kpi.impressions':          'Impressions',
        'kpi.ctrMoyen':             'Avg. CTR',
        'kpi.positionMoyenne':      'Avg. Position',
        'kpi.vsPeriodePrec':        'vs prev. period',

        // ── Dashboard ─ Graphiques ────────────────────────────────────────
        'graphique.tendance':       'Daily trend',
        'graphique.appareils':      'Device breakdown',
        'graphique.topPays':        'Top countries',

        // ── Dashboard ─ Tableaux ──────────────────────────────────────────
        'tableau.topRequetes':      'Top queries',
        'tableau.topPages':         'Top pages',
        'tableau.requete':          'Query',
        'tableau.page':             'Page',
        'tableau.clicks':           'Clicks',
        'tableau.impressions':      'Impressions',
        'tableau.ctr':              'CTR',
        'tableau.position':         'Position',
        'tableau.chargement':       'Loading...',
        'tableau.aucuneDonnee':     'No data',

        // ── Sync Status ───────────────────────────────────────────────────
        'sync.titre':               'Synchronization history',
        'sync.tousLesSites':        'All sites',
        'sync.lancer':              'Run synchronization',
        'sync.aucunSiteImporte':    'No site imported — the first sync will import your sites from Google.',
        'sync.enCours':             'Synchronization in progress',
        'sync.demarrage':           'Starting...',
        'sync.enAttente':           'Waiting to start...',
        'sync.syncEnCours':         'Synchronization in progress...',
        'sync.taches':              'tasks',
        'sync.terminee':            'Synchronization complete! Reloading in 3s...',
        'sync.erreur':              'Error:',
        'sync.inconnue':            'unknown',
        'sync.syncEnCoursBtn':      'Sync in progress...',
        'sync.vide':                'empty',
        'sync.lignes':              'rows',

        // ── Sync Status ─ Tableau ─────────────────────────────────────────
        'syncTab.id':               'ID',
        'syncTab.site':             'Site',
        'syncTab.type':             'Type',
        'syncTab.periode':          'Period',
        'syncTab.lignes':           'Rows',
        'syncTab.inserees':         'Inserted',
        'syncTab.new':              'New',
        'syncTab.maj':              'Updated',
        'syncTab.duree':            'Duration',
        'syncTab.statut':           'Status',
        'syncTab.erreur':           'Error',
        'syncTab.date':             'Date',
        'syncTab.ok':               'OK',
        'syncTab.vide':             'Empty',
        'syncTab.erreurBadge':      'Error',
        'syncTab.enCours':          'Running',
        'syncTab.aucuneSync':       'No synchronization recorded',

        // ── Erreurs API ───────────────────────────────────────────────────
        'api.nonAuthentifie':       'Not authenticated',
        'api.oauthNonConfigure':    'OAuth not configured',
        'api.siteNonTrouve':        'Site not found or access denied',
        'api.jobIntrouvable':       'Job not found',
        'api.siteIdRequis':         'site_id required',
        'api.pageNonTrouvee':       'Page not found',

        // ── Chart.js labels ───────────────────────────────────────────────
        'chart.clicks':             'Clicks',
        'chart.impressions':        'Impressions',
    }
};

// ── Détection de la langue ────────────────────────────────────────────────
var langueActuelle = (function () {
    if (typeof window.PLATFORM_LANG === 'string' && window.PLATFORM_LANG) return window.PLATFORM_LANG;
    try { var p = new URLSearchParams(window.location.search).get('lg'); if (p) return p; } catch (_) {}
    try { var s = localStorage.getItem('lang'); if (s) return s; } catch (_) {}
    return 'fr';
})();

/**
 * Renvoie la traduction pour la clé donnée, ou la clé elle-même si absente.
 */
function t(cle, remplacements) {
    var dict = TRANSLATIONS[langueActuelle] || TRANSLATIONS['fr'];
    var texte = dict[cle] || TRANSLATIONS['fr'][cle] || cle;
    if (remplacements) {
        Object.keys(remplacements).forEach(function (k) {
            texte = texte.replace(new RegExp('\\{' + k + '\\}', 'g'), remplacements[k]);
        });
    }
    return texte;
}

/**
 * Traduit tous les éléments du DOM portant l'attribut data-i18n.
 */
function traduirePage() {
    document.querySelectorAll('[data-i18n]').forEach(function (el) {
        var cle = el.getAttribute('data-i18n');
        if (!cle) return;
        el.textContent = t(cle);
    });
    document.querySelectorAll('[data-i18n-placeholder]').forEach(function (el) {
        var cle = el.getAttribute('data-i18n-placeholder');
        if (!cle) return;
        el.setAttribute('placeholder', t(cle));
    });
    document.querySelectorAll('[data-i18n-html]').forEach(function (el) {
        var cle = el.getAttribute('data-i18n-html');
        if (!cle) return;
        el.innerHTML = t(cle);
    });
}

/**
 * Initialise le sélecteur de langue (mode standalone uniquement).
 */
function initLangueSelect() {
    var sel = document.getElementById('lang-select');
    if (!sel) return;
    sel.value = langueActuelle;
    sel.addEventListener('change', function () {
        changerLangue(this.value);
    });
}

/**
 * Change la langue active, persiste le choix et retraduit la page.
 */
function changerLangue(lg) {
    langueActuelle = lg;
    try { localStorage.setItem('lang', lg); } catch (_) {}
    traduirePage();
}

// Traduction initiale au chargement du DOM
document.addEventListener('DOMContentLoaded', function () {
    traduirePage();
    initLangueSelect();
});
