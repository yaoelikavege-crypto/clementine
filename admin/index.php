<?php
/**
 * Point d'entrée de l'administration : redirige vers le tableau de bord.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

redirect(app_url('admin/dashboard.php'));
