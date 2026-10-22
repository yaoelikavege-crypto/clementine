<?php
/**
 * Déconnexion (compatibilité).
 * Les formulaires du site pointent vers actions/logout.php, qui applique
 * exactement la même logique : POST obligatoire + jeton CSRF.
 */

require_once __DIR__ . '/includes/bootstrap.php';

require_post();
require_csrf();

auth_logout();

redirect(app_url('index.php'));
