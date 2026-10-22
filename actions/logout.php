<?php
/**
 * Déconnexion : POST + jeton CSRF obligatoires.
 * La session est entièrement détruite puis recréée pour le message de retour.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_csrf();

auth_logout();

redirect(app_url('index.php'));
