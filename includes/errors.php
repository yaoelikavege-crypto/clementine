<?php
/**
 * Page d'erreur commune (aucune donnée sensible, aucune requête en base).
 */

function error_definition(int $status): array
{
    $definitions = [
        400 => ['Titre' => 'Requête invalide', 'message' => "La demande envoyée n'a pas pu être comprise.", 'eyebrow' => 'Erreur 400'],
        403 => ['Titre' => 'Accès refusé', 'message' => "Cet espace ne t'est pas accessible. Si tu penses qu'il s'agit d'une erreur, reconnecte-toi.", 'eyebrow' => 'Erreur 403'],
        404 => ['Titre' => 'Page introuvable', 'message' => "Cette page n'existe pas ou a été déplacée.", 'eyebrow' => 'Erreur 404'],
        405 => ['Titre' => 'Action non autorisée', 'message' => "Cette action doit être effectuée depuis le site, avec le formulaire prévu.", 'eyebrow' => 'Erreur 405'],
        419 => ['Titre' => 'Session expirée', 'message' => "Ta session a expiré ou la page était ouverte depuis trop longtemps. Recharge la page et recommence.", 'eyebrow' => 'Erreur 419'],
        429 => ['Titre' => 'Trop de tentatives', 'message' => "Patiente quelques minutes avant de réessayer.", 'eyebrow' => 'Erreur 429'],
        500 => ['Titre' => 'Une erreur est survenue', 'message' => "Quelque chose s'est mal passé de notre côté. Réessaie dans un instant.", 'eyebrow' => 'Erreur 500'],
        503 => ['Titre' => 'Site en préparation', 'message' => "Le site est momentanément indisponible. Reviens très vite.", 'eyebrow' => 'Erreur 503'],
    ];

    return $definitions[$status] ?? [
        'Titre' => 'Une erreur est survenue',
        'message' => "La page n'a pas pu être affichée.",
        'eyebrow' => 'Erreur ' . $status,
    ];
}

function render_error_page(int $status = 500, string $message = '', string $title = ''): void
{
    if (!headers_sent()) {
        // Apache (InfinityFree) ne connaît pas le code 419 : il le remplace
        // par un 500. On envoie donc 403 sur le réseau, en conservant la page
        // « Session expirée » — le code HTTP reste ainsi cohérent pour le
        // navigateur, les robots et les journaux de l'hébergeur.
        http_response_code($status === 419 ? 403 : $status);
        header('Content-Type: text/html; charset=utf-8');
        header('X-Robots-Tag: noindex, nofollow');
        header('X-Content-Type-Options: nosniff');
    }

    $definition = error_definition($status);
    $title      = $title !== '' ? $title : $definition['Titre'];
    $message    = $message !== '' ? $message : $definition['message'];
    $eyebrow    = $definition['eyebrow'];

    $homeUrl  = function_exists('app_url') ? app_url('') : '/';
    $loginUrl = function_exists('app_url') ? app_url('login.php') : '/login.php';
    $cssUrl   = function_exists('asset') ? asset('assets/css/main.css') : '/assets/css/main.css';

    echo '<!DOCTYPE html>';
    echo '<html lang="fr">';
    echo '<head>';
    echo '<meta charset="utf-8">';
    echo '<meta name="viewport" content="width=device-width, initial-scale=1">';
    echo '<meta name="robots" content="noindex, nofollow">';
    echo '<title>' . e($title) . ' · Clémentine</title>';
    echo '<link rel="stylesheet" href="' . e($cssUrl) . '">';
    echo '</head>';
    echo '<body class="page page--error">';
    echo '<main class="error-screen">';
    echo '<div class="error-screen__glow" aria-hidden="true"></div>';
    echo '<p class="eyebrow">' . e($eyebrow) . '</p>';
    echo '<h1 class="error-screen__title">' . e($title) . '</h1>';
    echo '<p class="error-screen__message">' . e($message) . '</p>';
    echo '<div class="error-screen__actions">';
    echo '<a class="btn btn--primary" href="' . e($homeUrl) . '">Retour à l\'accueil</a>';
    if (in_array($status, [403, 419, 429], true)) {
        echo '<a class="btn btn--ghost" href="' . e($loginUrl) . '">Se connecter</a>';
    }
    echo '</div>';
    echo '<p class="error-screen__footnote">Clémentine · 21 ans — un site privé, préparé avec soin.</p>';
    echo '</main>';
    echo '</body>';
    echo '</html>';
}

/**
 * Gestionnaire global des exceptions non attrapées.
 */
function app_exception_handler(Throwable $e): void
{
    log_event(
        'Exception non gérée : ' . get_class($e) . ' — ' . $e->getMessage()
        . ' dans ' . $e->getFile() . ':' . $e->getLine(),
        'error'
    );

    if (!headers_sent()) {
        render_error_page(500);
    }
}

/**
 * Gestionnaire des erreurs fatales (affichage sûr en production).
 */
function app_shutdown_handler(): void
{
    $error = error_get_last();

    if ($error === null || !in_array($error['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        return;
    }

    log_event('Erreur fatale : ' . $error['message'] . ' dans ' . $error['file'] . ':' . $error['line'], 'error');

    if (!headers_sent()) {
        render_error_page(500);
    }
}
