<?php
/**
 * Protection CSRF.
 * Un jeton est stocké en session et vérifié côté serveur pour toute action
 * sensible (POST). Il est renouvelé à chaque authentification.
 */

function csrf_token(): string
{
    if (empty($_SESSION['csrf_token']) || !is_string($_SESSION['csrf_token']) || strlen($_SESSION['csrf_token']) < 32) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function csrf_field(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrf_token()) . '">';
}

function csrf_rotate(): void
{
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Vérifie le jeton. En cas d'échec, affiche la page 419 et arrête tout.
 */
function require_csrf(): void
{
    if (!is_post()) {
        abort(405);
    }

    $token = $_POST['csrf_token'] ?? '';
    $valid = is_string($token) && $token !== '' && hash_equals(csrf_token(), $token);

    if (!$valid) {
        log_event('Jeton CSRF invalide ou expiré (' . current_script_basename() . ').', 'warning');
        abort(419, "Votre session a peut-être expiré ou la page a été ouverte trop longtemps. Rechargez la page puis réessayez.");
    }
}
