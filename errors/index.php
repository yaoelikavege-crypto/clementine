<?php
/**
 * Page d'erreur générique : /errors/index.php?code=403
 * Seuls les codes connus sont acceptés.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

$allowed = [400, 403, 404, 405, 419, 429, 500, 503];
$code    = get_int('code', 404);

render_error_page(in_array($code, $allowed, true) ? $code : 404);
