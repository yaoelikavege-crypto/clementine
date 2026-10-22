<?php
/**
 * Page d'erreur 405 — accessible directement et via ErrorDocument.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

render_error_page(405);
