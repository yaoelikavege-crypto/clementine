<?php
/**
 * Amorçage de l'application.
 * Chargé par toutes les pages publiques, l'administration, les actions,
 * l'assistant d'installation et les pages d'erreur.
 */

if (!defined('ROOT_PATH')) {
    define('ROOT_PATH', dirname(__DIR__));
}

if (!defined('APP_VERSION')) {
    define('APP_VERSION', '1.0.0');
}

/* -------------------------------------------------------------------------
 *  Configuration
 * ---------------------------------------------------------------------- */

$configFile = ROOT_PATH . '/includes/config.php';

if (!is_file($configFile)) {
    if (defined('SETUP_CONTEXT')) {
        $GLOBALS['APP_CONFIG'] = [];
    } else {
        render_configuration_missing_page();
        exit;
    }
} else {
    $GLOBALS['APP_CONFIG'] = require $configFile;
    if (!is_array($GLOBALS['APP_CONFIG'])) {
        $GLOBALS['APP_CONFIG'] = [];
    }
}

require_once __DIR__ . '/helpers.php';

/* -------------------------------------------------------------------------
 *  PHP : réglages, fuseau horaire, journalisation
 * ---------------------------------------------------------------------- */

$debug = (bool) config('app.debug', false);

error_reporting(E_ALL);
ini_set('display_errors', $debug ? '1' : '0');
ini_set('log_errors', '1');
ini_set('error_log', ROOT_PATH . '/storage/logs/php-error.log');

date_default_timezone_set((string) config('app.timezone', 'Africa/Lome'));

require_once __DIR__ . '/csrf.php';
require_once __DIR__ . '/icons.php';
require_once __DIR__ . '/database.php';
require_once __DIR__ . '/settings.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/media_library.php';
require_once __DIR__ . '/errors.php';

if (!$debug) {
    set_exception_handler('app_exception_handler');
    register_shutdown_function('app_shutdown_handler');
}

/* -------------------------------------------------------------------------
 *  Sessions sécurisées
 * ---------------------------------------------------------------------- */

if (!is_cli()) {
    ini_set('session.gc_maxlifetime', '86400');
    auth_start_session();
}

/* -------------------------------------------------------------------------
 *  En-têtes de sécurité (défense en profondeur, en plus du .htaccess)
 * ---------------------------------------------------------------------- */

if (!headers_sent() && !is_cli()) {
    header_remove('X-Powered-By');

    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: SAMEORIGIN');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=(), usb=()');
    header('X-Robots-Tag: noindex, nofollow, noarchive');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');

    header(
        "Content-Security-Policy: default-src 'self'; "
        . "base-uri 'none'; "
        . "form-action 'self'; "
        . "frame-ancestors 'self'; "
        . "script-src 'self'; "
        . "style-src 'self' 'unsafe-inline'; "
        . "img-src 'self' data: blob: https:; "
        . "media-src 'self' blob: https:; "
        . "font-src 'self' data:; "
        . "connect-src 'self'; "
        . "frame-src https://www.youtube.com https://www.youtube-nocookie.com https://player.vimeo.com"
    );

    if (is_https()) {
        header('Strict-Transport-Security: max-age=15552000; includeSubDomains');
    }
}

/* -------------------------------------------------------------------------
 *  État du site : installation, base de données, maintenance
 * ---------------------------------------------------------------------- */

function installation_lock_path(): string
{
    return ROOT_PATH . '/storage/installed.lock';
}

function app_is_installed(): bool
{
    static $installed = null;

    if ($installed !== null) {
        return $installed;
    }

    if (is_file(installation_lock_path())) {
        $installed = true;

        return true;
    }

    try {
        $flag = db_value("SELECT setting_value FROM site_settings WHERE setting_key = 'installed' LIMIT 1");
        if ((string) $flag === '1') {
            $installed = true;

            return true;
        }
    } catch (Throwable $e) {
        // Tables absentes : le site n'est pas encore installé.
    }

    $installed = false;

    return false;
}

if (!defined('SETUP_CONTEXT')) {

    if (!app_is_installed()) {
        redirect(app_url('setup/index.php'));
    }

    try {
        db()->query('SELECT 1');
    } catch (Throwable $e) {
        log_event('Base de données injoignable : ' . $e->getMessage(), 'error');
        render_error_page(503, 'Le site ne parvient pas à joindre sa base de données. Si le problème persiste, contacte l\'administrateur.');
        exit;
    }

    // Mode maintenance : seuls les administrateurs et la page de connexion passent.
    $script = current_script_basename();
    $allowedDuringMaintenance = ['login.php', 'auth.php', 'time.php'];
    $inMaintenance = setting('maintenance_mode', '0') === '1';

    if ($inMaintenance && !is_admin() && !in_array($script, $allowedDuringMaintenance, true) && !str_contains(current_path(), '/errors/')) {
        render_error_page(503, setting('maintenance_message', 'Le site est en préparation. Reviens très vite.'));
        exit;
    }
}

/* -------------------------------------------------------------------------
 *  Page affichée lorsque includes/config.php est absent
 * ---------------------------------------------------------------------- */

function render_configuration_missing_page(): void
{
    $cssUrl = '/assets/css/main.css';
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Configuration requise · Clémentine</title>
    <link rel="stylesheet" href="<?= htmlspecialchars($cssUrl, ENT_QUOTES, 'UTF-8') ?>">
</head>
<body class="page page--error">
<main class="error-screen error-screen--wide">
    <p class="eyebrow">Installation</p>
    <h1 class="error-screen__title">Une étape de configuration est nécessaire</h1>
    <p class="error-screen__message">
        Le fichier <code>includes/config.php</code> est absent. Il contient les identifiants MySQL du site
        et doit être créé manuellement, sans jamais être partagé.
    </p>
    <ol class="error-screen__steps">
        <li>Dans le gestionnaire de fichiers de l'hébergeur, ouvre le dossier <code>includes/</code>.</li>
        <li>Duplique <code>config.example.php</code> et renomme la copie en <code>config.php</code>.</li>
        <li>Renseigne les accès MySQL, une <code>install_key</code> longue et aléatoire, puis un <code>privacy_salt</code>.</li>
        <li>Recharge cette page, puis lance l'assistant d'installation : <a href="setup/index.php">setup/index.php</a>.</li>
    </ol>
    <p class="error-screen__footnote">NovaTechX · KavegeElis</p>
</main>
</body>
</html>
    <?php
}
