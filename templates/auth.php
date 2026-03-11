<?php
/**
 * Template auth.php — redirige vers le dashboard qui gère l'état de connexion.
 * Conservé pour rétrocompatibilité uniquement.
 */
$prefix = defined('MODULE_URL_PREFIX') ? MODULE_URL_PREFIX : '';
header('Location: ' . $prefix . '/');
exit;
?>
