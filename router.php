<?php
/**
 * Routeur de compatibilité pour le serveur de développement intégré à PHP.
 *
 * Il n'est PAS nécessaire sur InfinityFree (Apache) : ce fichier sert
 * uniquement aux tests locaux avec :
 *
 *     php -S 0.0.0.0:8080 router.php
 *
 * Rôle : reproduire le comportement d'Apache décrit par le .htaccess racine,
 * c'est-à-dire renvoyer une vraie page 404 pour toute URL inexistante, et
 * interdire l'affichage des fichiers sensibles (.md, .sql, .log, .htaccess…).
 */

$root = __DIR__;
$path = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
$path = is_string($path) ? rawurldecode($path) : '/';
$relative = ltrim($path, '/');
$target = $relative === '' ? $root . '/index.php' : $root . '/' . $relative;

// Répertoire sans slash final : redirection, comme le ferait Apache.
if (is_dir($target) && substr($path, -1) !== '/') {
    header('Location: ' . $path . '/', true, 301);
    exit;
}

if (is_dir($target)) {
    $target = rtrim($target, '/') . '/index.php';
    $relative = rtrim($relative, '/') . '/index.php';
}

$blocked = (bool) preg_match('/\.(md|sql|log|lock|ini|sh|bak|swp|example\.php)$/i', $relative);
$hidden  = (bool) preg_match('#(^|/)\.#', $relative);

// Dossiers privés : jamais servis directement (équivalent des .htaccess).
$private = (bool) preg_match('#^(uploads|storage|sql|tools)(/|$)#i', $relative);

if ($blocked || $hidden || $private) {
    http_response_code(403);
    require $root . '/errors/403.php';
    exit;
}

if (is_file($target)) {
    if (substr($target, -4) === '.php') {
        return false; // laisse PHP exécuter le script demandé
    }

    return false; // fichier statique servi normalement
}

// URL inexistante : vraie 404, comme ErrorDocument 404 du .htaccess.
http_response_code(404);
require $root . '/errors/404.php';
