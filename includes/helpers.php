<?php
/**
 * Fonctions utilitaires partagées par toute l'application.
 * Aucune de ces fonctions n'affiche de donnée sensible.
 */

/* -------------------------------------------------------------------------
 *  Configuration & URL
 * ---------------------------------------------------------------------- */

function config(string $path, $default = null)
{
    $value = $GLOBALS['APP_CONFIG'] ?? [];

    foreach (explode('.', $path) as $segment) {
        if (!is_array($value) || !array_key_exists($segment, $value)) {
            return $default;
        }
        $value = $value[$segment];
    }

    return $value;
}

/**
 * Préfixe d'URL de l'application.
 *
 *  - si `app.base_url` est renseigné dans includes/config.php, il est utilisé ;
 *  - sinon le préfixe est DÉTECTÉ automatiquement : le site fonctionne aussi
 *    bien à la racine du domaine (htdocs/) que dans un sous-dossier
 *    (htdocs/clementine-birthday/), ce qui évite les pages « sans CSS ».
 */
function base_path(): string
{
    static $base = null;

    if ($base !== null) {
        return $base;
    }

    // 1. Valeur explicite dans includes/config.php (prioritaire).
    $configured = trim((string) config('app.base_url', ''));

    if ($configured !== '' && $configured !== '/') {
        return $base = '/' . trim($configured, '/');
    }

    $root = rtrim(str_replace('\\', '/', (string) (realpath(ROOT_PATH) ?: ROOT_PATH)), '/');
    $scriptName = str_replace('\\', '/', (string) ($_SERVER['SCRIPT_NAME'] ?? ''));

    // Présent uniquement hors ligne de commande.
    if ($root === '' || $scriptName === '' || $scriptName[0] !== '/') {
        return $base = '';
    }

    // 2. Méthode universelle : on retire du nom d'URL du script sa position
    //    réelle sur le disque relative à la racine du projet. Le reste est le
    //    préfixe d'URL sous lequel le projet est installé.
    //    Fonctionne à la racine du domaine comme dans /htdocs/sous-dossier/,
    //    et même si la racine web est un lien symbolique.
    $candidates = [];

    foreach ([$_SERVER['SCRIPT_FILENAME'] ?? '', $_SERVER['PATH_TRANSLATED'] ?? ''] as $raw) {
        $raw = str_replace('\\', '/', (string) $raw);

        if ($raw === '') {
            continue;
        }

        $candidates[] = $raw;

        $real = realpath($raw);

        if ($real !== false) {
            $candidates[] = str_replace('\\', '/', $real);
        }
    }

    foreach (array_unique($candidates) as $scriptFile) {
        if (!str_starts_with($scriptFile, $root . '/')) {
            continue;
        }

        $inside = substr($scriptFile, strlen($root)); // ex. « /admin/dashboard.php »

        if ($inside === '' || $inside === '/') {
            continue;
        }

        if (!str_ends_with($scriptName, $inside)) {
            continue;
        }

        $prefix = substr($scriptName, 0, strlen($scriptName) - strlen($inside));
        $prefix = rtrim($prefix, '/');

        // Sécurité : le préfixe doit ressembler à un chemin d'URL simple.
        if ($prefix === '' || preg_match('#^/[A-Za-z0-9._~!$&\'()*+,;=:@%/-]*$#', $prefix)) {
            return $base = $prefix;
        }
    }

    // 3. Repli : comparaison directe avec la racine web du serveur.
    $documentRoot = str_replace('\\', '/', (string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));

    if ($documentRoot !== '') {
        $docRoot = rtrim((string) (realpath($documentRoot) ?: $documentRoot), '/');

        if ($docRoot !== '' && str_starts_with($root . '/', $docRoot . '/')) {
            $relative = trim(substr($root, strlen($docRoot)), '/');

            return $base = $relative === '' ? '' : '/' . $relative;
        }
    }

    return $base = '';
}

function app_url(string $path = ''): string
{
    $base = base_path();
    $path = ltrim($path, '/');

    if ($path === '') {
        return $base === '' ? '/' : $base . '/';
    }

    return ($base === '' ? '' : $base) . '/' . $path;
}

function asset(string $path): string
{
    $path  = ltrim($path, '/');
    $full  = ROOT_PATH . '/' . $path;
    $url   = app_url($path);

    if (is_file($full)) {
        $url .= '?v=' . filemtime($full);
    }

    return $url;
}

function current_path(): string
{
    $uri  = (string) ($_SERVER['REQUEST_URI'] ?? '/');
    $path = parse_url($uri, PHP_URL_PATH);

    return is_string($path) && $path !== '' ? $path : '/';
}

function url_with(array $params): string
{
    $query = $_GET;
    foreach ($params as $key => $value) {
        if ($value === null) {
            unset($query[$key]);
        } else {
            $query[$key] = $value;
        }
    }

    $qs = http_build_query($query);

    return current_path() . ($qs !== '' ? '?' . $qs : '');
}

function redirect(string $path, int $status = 302): void
{
    if (preg_match('~^https?://~i', $path)) {
        header('Location: ' . $path, true, $status);
        exit;
    }

    $url = str_starts_with($path, '/') ? $path : app_url($path);

    header('Location: ' . $url, true, $status);
    exit;
}

function is_https(): bool
{
    if (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off') {
        return true;
    }
    if ((int) ($_SERVER['SERVER_PORT'] ?? 0) === 443) {
        return true;
    }
    if (strtolower((string) ($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '')) === 'https') {
        return true;
    }

    return false;
}

function absolute_url(string $path = ''): string
{
    $host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');

    return (is_https() ? 'https://' : 'http://') . $host . app_url($path);
}

/* -------------------------------------------------------------------------
 *  Sortie / échappement
 * ---------------------------------------------------------------------- */

function e($value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function json_response(array $data, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('X-Content-Type-Options: nosniff');
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

/* -------------------------------------------------------------------------
 *  Entrées HTTP
 * ---------------------------------------------------------------------- */

function is_post(): bool
{
    return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
}

function require_post(): void
{
    if (!is_post()) {
        abort(405, "Cette action doit être envoyée par formulaire (méthode POST).");
    }
}

function input(string $key, string $default = ''): string
{
    $raw = $_POST[$key] ?? $_GET[$key] ?? $default;

    if (is_array($raw)) {
        return $default;
    }

    return trim((string) $raw);
}

function post_string(string $key, string $default = ''): string
{
    $raw = $_POST[$key] ?? $default;

    return is_array($raw) ? $default : trim((string) $raw);
}

function post_int(string $key, int $default = 0): int
{
    $raw = $_POST[$key] ?? null;

    return is_numeric($raw) ? (int) $raw : $default;
}

function get_int(string $key, int $default = 0): int
{
    $raw = $_GET[$key] ?? null;

    return is_numeric($raw) ? (int) $raw : $default;
}

function checked(string $key): bool
{
    return isset($_POST[$key]) && (string) $_POST[$key] !== '';
}

/* -------------------------------------------------------------------------
 *  Messages flash (retour visuel après redirection)
 * ---------------------------------------------------------------------- */

function flash(string $type, string $message): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        $_SESSION['flashes'][] = ['type' => $type, 'text' => $message];
    }
}

function flashes(): array
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        return [];
    }

    $items = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);

    return is_array($items) ? $items : [];
}

function render_flashes(): string
{
    $html = '';
    foreach (flashes() as $flash) {
        $type = in_array($flash['type'] ?? '', ['success', 'error', 'warning', 'info'], true) ? $flash['type'] : 'info';
        $html .= '<div class="flash flash--' . e($type) . '" role="status">'
            . '<span class="flash__icon">' . icon($type === 'success' ? 'check-circle' : ($type === 'error' ? 'alert-triangle' : ($type === 'warning' ? 'alert-triangle' : 'info')), 18) . '</span>'
            . '<span>' . e($flash['text'] ?? '') . '</span>'
            . '</div>';
    }

    return $html;
}

/* -------------------------------------------------------------------------
 *  Dates & fuseau horaire
 * ---------------------------------------------------------------------- */

function site_timezone(): DateTimeZone
{
    static $tz = null;

    if (!$tz instanceof DateTimeZone) {
        try {
            $tz = new DateTimeZone((string) config('app.timezone', 'Africa/Lome'));
        } catch (Throwable $e) {
            $tz = new DateTimeZone('Africa/Lome');
        }
    }

    return $tz;
}

function now(): DateTimeImmutable
{
    return new DateTimeImmutable('now', site_timezone());
}

function reveal_at(): DateTimeImmutable
{
    static $reveal = null;

    if ($reveal instanceof DateTimeImmutable) {
        return $reveal;
    }

    try {
        $reveal = new DateTimeImmutable((string) config('app.reveal_at', '2026-11-23 00:00:00'), site_timezone());
    } catch (Throwable $e) {
        log_event('Date de révélation invalide dans la configuration.', 'error');
        $reveal = new DateTimeImmutable('2026-11-23 00:00:00', site_timezone());
    }

    return $reveal;
}

function is_revealed(?DateTimeImmutable $at = null): bool
{
    $at = $at ?? now();

    return $at->getTimestamp() >= reveal_at()->getTimestamp();
}

function seconds_to_reveal(?DateTimeImmutable $at = null): int
{
    $at = $at ?? now();

    return max(0, reveal_at()->getTimestamp() - $at->getTimestamp());
}

/**
 * @return array{days:int,hours:int,minutes:int,seconds:int,total:int}
 */
function countdown_parts(?DateTimeImmutable $at = null): array
{
    $total = seconds_to_reveal($at);

    return [
        'total'   => $total,
        'days'    => intdiv($total, 86400),
        'hours'   => intdiv($total % 86400, 3600),
        'minutes' => intdiv($total % 3600, 60),
        'seconds' => $total % 60,
    ];
}

/**
 * Libellé de la date de révélation, issu de app.reveal_at.
 * Aucun gabarit ne doit écrire « 23 novembre 2026 » en dur : la date reste
 * centralisée dans includes/config.php.
 */
function reveal_date_text(): string
{
    return fr_date(reveal_at());
}

/**
 * « 23 novembre 2026 à minuit » si la révélation tombe à 00 h 00,
 * sinon « 23 novembre 2026 à 21 h 30 ».
 */
function reveal_moment_text(): string
{
    $reveal = reveal_at();

    return reveal_date_text() . ($reveal->format('H:i') === '00:00'
        ? ' à minuit'
        : ' à ' . $reveal->format('H\hi'));
}

function fr_date($value = null, bool $withTime = false): string
{
    $months = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];

    if ($value instanceof DateTimeInterface) {
        $dt = $value instanceof DateTimeImmutable ? $value : DateTimeImmutable::createFromInterface($value);
    } elseif (is_string($value) && trim($value) !== '') {
        try {
            $dt = new DateTimeImmutable($value, site_timezone());
        } catch (Throwable $e) {
            return $value;
        }
    } else {
        $dt = now();
    }

    $dt = $dt->setTimezone(site_timezone());

    // En français, le premier du mois s'écrit « 1er ».
    $day  = $dt->format('j');
    $text = ($day === '1' ? '1er' : $day) . ' ' . $months[(int) $dt->format('n')] . ' ' . $dt->format('Y');

    if ($withTime) {
        $text .= ' à ' . $dt->format('H') . ' h ' . $dt->format('i');
    }

    return $text;
}

function fr_date_short($value): string
{
    if (empty($value)) {
        return '';
    }

    $full = fr_date((string) $value);

    return preg_replace('/\s\d{4}$/', '', $full) ?? $full;
}

function parse_datetime_input(string $value): ?string
{
    $value = trim($value);
    if ($value === '') {
        return null;
    }

    foreach (['Y-m-d\TH:i', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d'] as $format) {
        $dt = DateTimeImmutable::createFromFormat('!' . $format, $value, site_timezone());
        if ($dt instanceof DateTimeImmutable) {
            $errors = DateTimeImmutable::getLastErrors();
            if ($errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0)) {
                return $dt->format('Y-m-d H:i:s');
            }
        }
    }

    return null;
}

function datetime_input_value(?string $sqlDate): string
{
    if (empty($sqlDate)) {
        return '';
    }

    try {
        return (new DateTimeImmutable((string) $sqlDate, site_timezone()))->format('Y-m-d\TH:i');
    } catch (Throwable $e) {
        return '';
    }
}

/* -------------------------------------------------------------------------
 *  Texte
 * ---------------------------------------------------------------------- */

function excerpt(string $text, int $length = 170): string
{
    $text = trim(preg_replace('/\s+/u', ' ', strip_tags($text)) ?? '');

    if (mb_strlen($text, 'UTF-8') <= $length) {
        return $text;
    }

    return rtrim(mb_substr($text, 0, $length, 'UTF-8')) . '…';
}

function nl2p(string $text): string
{
    $paragraphs = preg_split('/\R{2,}/u', trim($text)) ?: [];

    $html = '';
    foreach ($paragraphs as $paragraph) {
        $paragraph = trim($paragraph);
        if ($paragraph !== '') {
            $html .= '<p>' . nl2br(e($paragraph)) . '</p>';
        }
    }

    return $html;
}

function format_bytes(?int $bytes): string
{
    if ($bytes === null || $bytes <= 0) {
        return '—';
    }

    $units = ['o', 'Ko', 'Mo', 'Go'];
    $index = 0;
    $value = (float) $bytes;

    while ($value >= 1024 && $index < count($units) - 1) {
        $value /= 1024;
        $index++;
    }

    return number_format($value, $index === 0 ? 0 : 1, ',', ' ') . ' ' . $units[$index];
}

function format_duration(?int $seconds): string
{
    if ($seconds === null || $seconds <= 0) {
        return '';
    }

    $minutes = intdiv($seconds, 60);
    $secs    = $seconds % 60;

    return $minutes . ':' . str_pad((string) $secs, 2, '0', STR_PAD_LEFT);
}

function plural_fr(int $count, string $singular, string $plural): string
{
    return $count > 1 ? $plural : $singular;
}

function random_token(int $bytes = 16): string
{
    return bin2hex(random_bytes($bytes));
}

function page_title(string $title): string
{
    $site = (string) setting('site_title', (string) config('app.name', 'Clémentine'));

    return $title === '' ? $site : $title . ' · ' . $site;
}

/* -------------------------------------------------------------------------
 *  Pagination
 * ---------------------------------------------------------------------- */

function pagination(int $total, int $perPage, int $page): array
{
    $perPage = max(1, $perPage);
    $pages   = max(1, (int) ceil($total / $perPage));
    $page    = min(max(1, $page), $pages);

    return [
        'total'    => $total,
        'per_page' => $perPage,
        'pages'    => $pages,
        'page'     => $page,
        'offset'   => ($page - 1) * $perPage,
        'has_prev' => $page > 1,
        'has_next' => $page < $pages,
    ];
}

/* -------------------------------------------------------------------------
 *  Journalisation & audit
 * ---------------------------------------------------------------------- */

function log_event(string $message, string $level = 'info'): void
{
    $dir = ROOT_PATH . '/storage/logs';
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }

    $line = sprintf(
        "[%s] [%s] %s%s",
        now()->format('Y-m-d H:i:s'),
        strtoupper($level),
        str_replace(["\r", "\n"], ' ', $message),
        PHP_EOL
    );

    @file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

function client_ip(): string
{
    // On ne fait pas confiance aux en-têtes X-Forwarded-For fournis par le client.
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

function ip_hash(): string
{
    return hash_hmac('sha256', client_ip(), (string) config('app.privacy_salt', 'novatechx'));
}

function user_agent_hash(): string
{
    return hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'unknown'));
}

function audit(string $action, string $details = ''): void
{
    $user = current_user();
    if (!is_array($user) || ($user['type'] ?? '') !== 'admin') {
        return;
    }

    try {
        db_query(
            'INSERT INTO audit_log (admin_id, action, details, ip_hash, created_at) VALUES (?, ?, ?, ?, ?)',
            [(int) $user['id'], mb_substr($action, 0, 80), mb_substr($details, 0, 500), ip_hash(), now()->format('Y-m-d H:i:s')]
        );
    } catch (Throwable $e) {
        log_event('Échec de journalisation d\'audit : ' . $e->getMessage(), 'warning');
    }
}

/* -------------------------------------------------------------------------
 *  Divers
 * ---------------------------------------------------------------------- */

function is_cli(): bool
{
    return PHP_SAPI === 'cli';
}

function current_script_basename(): string
{
    return basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
}

/**
 * Les pages d'affichage (hors formulaires) ne doivent être atteintes qu'en GET.
 * Un POST sur une page en lecture seule est une erreur d'utilisation ou une
 * tentative de contournement : on répond 405 plutôt que d'afficher la page.
 */
function require_page_method(): void
{
    if (!is_post()) {
        return;
    }

    if (function_exists('log_event')) {
        log_event('POST refusé sur une page en lecture seule (' . current_script_basename() . ').', 'warning');
    }

    if (!headers_sent()) {
        header('Allow: GET, HEAD');
    }

    abort(405, "Cette page ne reçoit pas de formulaire. Utilise les boutons du site pour agir.");
}

function abort(int $status, string $message = '', string $title = ''): void
{
    render_error_page($status, $message, $title);
    exit;
}
