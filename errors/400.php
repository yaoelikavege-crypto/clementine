<?php
/**
 * Page d'erreur 400 — accessible directement et via ErrorDocument.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

render_error_page(400);
