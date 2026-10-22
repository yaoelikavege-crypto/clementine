<?php
/**
 * Authentification, sessions et autorisations.
 *
 * Rôles :
 *   - admin   : les deux comptes administrateurs (Joël + développeur).
 *   - visitor : accès partagé par mot de passe commun, lecture seule.
 *
 * Le rôle est TOUJOURS déterminé côté serveur à partir de la session.
 * Aucune valeur envoyée par le navigateur n'est utilisée comme preuve.
 */

const AUTH_DUMMY_HASH = '$2y$12$.mKHPtDFgVcGvFqFX7w9c.4oMXp9rwZFdnZr1yhcxVLpGfzH0UXHK';

/* -------------------------------------------------------------------------
 *  Session
 * ---------------------------------------------------------------------- */

function auth_session_name(): string
{
    $name = preg_replace('/[^a-zA-Z0-9_-]/', '', (string) config('app.session_name', 'clementine_sid'));

    return is_string($name) && $name !== '' ? $name : 'clementine_sid';
}

function auth_start_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');

    $secure = config('app.cookie_secure', null);
    $secure = $secure === null ? is_https() : (bool) $secure;

    session_name(auth_session_name());
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => base_path() === '' ? '/' : base_path(),
        'domain'   => '',
        'secure'   => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    $_SESSION['created_at'] = $_SESSION['created_at'] ?? time();
    $_SESSION['ua_hash']    = $_SESSION['ua_hash'] ?? user_agent_hash();

    if (!hash_equals((string) $_SESSION['ua_hash'], user_agent_hash())) {
        // Empreinte de navigateur différente : session considérée comme suspecte.
        auth_clear_session();
        session_regenerate_id(true);
        $_SESSION['ua_hash'] = user_agent_hash();
    }

    auth_enforce_timeouts();
}

function auth_clear_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires'  => time() - 42000,
            'path'     => $params['path'] ?: '/',
            'domain'   => $params['domain'] ?? '',
            'secure'   => (bool) ($params['secure'] ?? false),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);
    }

    session_destroy();
}

function auth_restart_session(): void
{
    if (session_status() !== PHP_SESSION_ACTIVE) {
        auth_start_session();

        return;
    }

    session_regenerate_id(true);
    csrf_rotate();
    $_SESSION['last_seen'] = time();
    $_SESSION['regen_at']  = time();
}

function auth_expire(): void
{
    auth_clear_session();

    if (!in_array(current_script_basename(), ['login.php', 'auth.php'], true)) {
        redirect(app_url('login.php') . '?expired=1');
    }
}

function auth_enforce_timeouts(): void
{
    if (!isset($_SESSION['user']) || !is_array($_SESSION['user'])) {
        return;
    }

    $now  = time();
    $idle = max(5, (int) config('app.session_idle_minutes', 45)) * 60;
    $abs  = max(1, (int) config('app.session_absolute_hours', 12)) * 3600;

    $lastSeen = (int) ($_SESSION['last_seen'] ?? $now);
    $authTime = (int) ($_SESSION['auth_time'] ?? $now);

    if (($now - $lastSeen) > $idle || ($now - $authTime) > $abs) {
        auth_expire();

        return;
    }

    $_SESSION['last_seen'] = $now;

    if (($now - (int) ($_SESSION['regen_at'] ?? 0)) > 900) {
        session_regenerate_id(true);
        $_SESSION['regen_at'] = $now;
    }
}

/* -------------------------------------------------------------------------
 *  Utilisateur courant
 * ---------------------------------------------------------------------- */

function current_user(): ?array
{
    $user = $_SESSION['user'] ?? null;

    return is_array($user) ? $user : null;
}

function is_authenticated(): bool
{
    return current_user() !== null;
}

function is_admin(): bool
{
    $user = current_user();

    return is_array($user) && ($user['type'] ?? '') === 'admin';
}

function is_visitor(): bool
{
    $user = current_user();

    return is_array($user) && ($user['type'] ?? '') === 'visitor';
}

/**
 * L'accès aux contenus du jour J (galerie, lettre, finale) est-il ouvert ?
 * Les administrateurs disposent d'un aperçu permanent.
 */
function can_view_content(): bool
{
    if (is_admin()) {
        return true;
    }

    return is_visitor() && is_revealed();
}

function require_auth(): void
{
    if (!is_authenticated()) {
        flash('info', 'Connecte-toi pour continuer.');
        redirect(app_url('login.php'));
    }
}

function require_admin(): void
{
    if (!is_authenticated()) {
        flash('info', 'Connecte-toi pour accéder à l\'administration.');
        redirect(app_url('login.php'));
    }

    if (!is_admin()) {
        abort(403, "Cet espace est réservé aux administrateurs du site.");
    }
}

function require_content_access(): void
{
    require_auth();

    if (is_admin()) {
        return;
    }

    if (!is_revealed()) {
        flash('info', 'Les souvenirs se dévoilent le ' . reveal_moment_text() . '.');
        redirect(app_url('pages/countdown.php'));
    }
}

/* -------------------------------------------------------------------------
 *  Limitation des tentatives de connexion
 * ---------------------------------------------------------------------- */

function throttle_config(string $context): array
{
    if ($context === 'admin') {
        return ['max' => 5, 'window' => 900, 'hourly_max' => 20];
    }

    return ['max' => 8, 'window' => 900, 'hourly_max' => 25];
}

/**
 * @return array{blocked:bool,retry_after:int,remaining:int}
 */
function throttle_state(string $context): array
{
    $cfg    = throttle_config($context);
    $ipHash = ip_hash();
    $since  = now()->modify('-' . $cfg['window'] . ' seconds')->format('Y-m-d H:i:s');

    try {
        $recent = db_fetch(
            'SELECT COUNT(*) AS failures, MIN(attempted_at) AS first_failure
             FROM login_attempts
             WHERE context = ? AND ip_hash = ? AND success = 0 AND attempted_at >= ?',
            [$context, $ipHash, $since]
        );

        $failures = (int) ($recent['failures'] ?? 0);
        $blocked  = $failures >= $cfg['max'];
        $retry    = 0;

        if ($blocked && !empty($recent['first_failure'])) {
            try {
                $first = new DateTimeImmutable((string) $recent['first_failure'], site_timezone());
                $retry = max(0, $cfg['window'] - (now()->getTimestamp() - $first->getTimestamp()));
            } catch (Throwable $e) {
                $retry = $cfg['window'];
            }
        }

        return [
            'blocked'     => $blocked,
            'retry_after' => $retry,
            'remaining'   => max(0, $cfg['max'] - $failures),
        ];
    } catch (Throwable $e) {
        log_event('Contrôle anti-force brute indisponible : ' . $e->getMessage(), 'warning');

        return ['blocked' => false, 'retry_after' => 0, 'remaining' => $cfg['max']];
    }
}

function throttle_record(string $context, ?string $username, bool $success): void
{
    $usernameHash = $username === null || $username === ''
        ? null
        : hash_hmac('sha256', mb_strtolower($username), (string) config('app.privacy_salt', 'novatechx'));

    try {
        db_query(
            'INSERT INTO login_attempts (context, ip_hash, username_hash, success, attempted_at) VALUES (?, ?, ?, ?, ?)',
            [$context, ip_hash(), $usernameHash, $success ? 1 : 0, now()->format('Y-m-d H:i:s')]
        );

        if ($success) {
            // Une connexion réussie remet le compteur à zéro pour cette IP.
            db_query('DELETE FROM login_attempts WHERE context = ? AND ip_hash = ? AND success = 0', [$context, ip_hash()]);
        }

        if (random_int(1, 20) === 1) {
            db_query('DELETE FROM login_attempts WHERE attempted_at < ?', [now()->modify('-7 days')->format('Y-m-d H:i:s')]);
        }
    } catch (Throwable $e) {
        log_event('Échec de l\'enregistrement d\'une tentative de connexion : ' . $e->getMessage(), 'warning');
    }
}

function throttle_message(int $seconds): string
{
    if ($seconds <= 0) {
        return 'Trop de tentatives. Réessaie dans quelques minutes.';
    }

    $minutes = (int) ceil($seconds / 60);

    return 'Trop de tentatives. Réessaie dans ' . $minutes . ' ' . plural_fr($minutes, 'minute', 'minutes') . '.';
}

/* -------------------------------------------------------------------------
 *  Politique de mot de passe
 * ---------------------------------------------------------------------- */

function password_policy_error(string $password, string $role, string $username = ''): ?string
{
    $min = $role === 'admin' ? 10 : 8;
    $len = mb_strlen($password, 'UTF-8');

    if ($len < $min) {
        return 'Le mot de passe doit contenir au moins ' . $min . ' caractères.';
    }

    if (strlen($password) > 72) {
        return 'Le mot de passe ne doit pas dépasser 72 octets.';
    }

    if (!preg_match('/[A-Za-zÀ-ÖØ-öø-ÿ]/u', $password) || !preg_match('/\d/', $password)) {
        return 'Le mot de passe doit contenir au moins une lettre et un chiffre.';
    }

    if ($username !== '' && mb_strtolower($password) === mb_strtolower($username)) {
        return 'Le mot de passe ne peut pas être identique à l\'identifiant.';
    }

    $weak = ['password', 'motdepasse', 'azerty', 'qwerty', '12345678', '123456789', 'clementine'];
    foreach ($weak as $needle) {
        if (mb_stripos($password, $needle) !== false) {
            return 'Ce mot de passe est trop prévisible. Choisissez une phrase secrète plus originale.';
        }
    }

    return null;
}

/* -------------------------------------------------------------------------
 *  Comptes par défaut (première installation)
 * ---------------------------------------------------------------------- */

/**
 * Normalise la liste des comptes par défaut définie dans includes/config.php.
 *
 * @return array{admins: array<int, array{username:string, display_name:string, password:string}>, visitor: ?array{display_name:string, password:string}}
 */
function default_accounts_config(): array
{
    $config = [
        'admins'  => [],
        'visitor' => null,
    ];

    if (!config('app.seed_default_accounts', true)) {
        return $config;
    }

    // Tolérance : si le bloc a été collé au mauvais niveau dans config.php
    // (hors du tableau « app »), on le retrouve quand même.
    $raw = config('app.default_accounts', config('default_accounts', []));
    if (!is_array($raw)) {
        return $config;
    }

    foreach (($raw['admins'] ?? []) as $admin) {
        if (!is_array($admin)) {
            continue;
        }

        $username = mb_strtolower(trim((string) ($admin['username'] ?? '')));
        $display  = trim((string) ($admin['display_name'] ?? ''));
        $password = (string) ($admin['password'] ?? '');

        if ($username === '' || $password === '') {
            continue;
        }

        $config['admins'][] = [
            'username'     => $username,
            'display_name' => $display !== '' ? $display : ucfirst($username),
            'password'     => $password,
        ];
    }

    $visitor = $raw['visitor'] ?? null;
    if (is_array($visitor) && (string) ($visitor['password'] ?? '') !== '') {
        $config['visitor'] = [
            'display_name' => trim((string) ($visitor['display_name'] ?? 'Clémentine')),
            'password'     => (string) $visitor['password'],
        ];
    }

    return $config;
}

/**
 * Comptes configurés qui utilisent encore leur mot de passe par défaut.
 *
 * @return array<int, array{type:string, label:string, username:string}>
 */
function default_passwords_remaining(): array
{
    $remaining = [];
    $defaults  = default_accounts_config();

    // On compare les mots de passe, pas les identifiants : un compte renommé
    // reste détecté tant que son mot de passe n'a pas changé.
    try {
        $rows = db_fetch_all('SELECT username, display_name, password_hash FROM admins ORDER BY id ASC');
    } catch (Throwable $e) {
        $rows = [];
    }

    foreach ($rows as $row) {
        foreach ($defaults['admins'] as $admin) {
            if (password_verify($admin['password'], (string) $row['password_hash'])) {
                $remaining[] = [
                    'type'     => 'admin',
                    'label'    => (string) $row['display_name'],
                    'username' => (string) $row['username'],
                ];
                break;
            }
        }
    }

    if ($defaults['visitor'] !== null) {
        try {
            $hash = db_value('SELECT password_hash FROM visitor_credentials WHERE id = 1 LIMIT 1');
        } catch (Throwable $e) {
            $hash = null;
        }

        if ($hash !== null && password_verify($defaults['visitor']['password'], (string) $hash)) {
            $remaining[] = [
                'type'     => 'visitor',
                'label'    => 'Accès visiteur',
                'username' => $defaults['visitor']['display_name'],
            ];
        }
    }

    return $remaining;
}

/**
 * Vérifie réellement les mots de passe et mémorise le résultat dans les
 * réglages : le tableau de bord peut alors afficher une alerte sans refaire
 * de vérification bcrypt à chaque page.
 */
function refresh_default_password_flag(): bool
{
    $remaining = default_passwords_remaining();
    $active    = $remaining !== [];

    try {
        setting_set('default_passwords_active', $active ? '1' : '0');
        setting_set('default_passwords_list', implode(', ', array_map(
            static fn (array $item): string => $item['label'] . ' (' . $item['username'] . ')',
            $remaining
        )));
    } catch (Throwable $e) {
        log_event('Mise à jour du drapeau « mots de passe par défaut » impossible : ' . $e->getMessage(), 'warning');
    }

    return $active;
}

function default_passwords_active(): bool
{
    return setting('default_passwords_active', '0') === '1';
}

/* -------------------------------------------------------------------------
 *  Connexion / déconnexion
 * ---------------------------------------------------------------------- */

/**
 * @return array{ok:bool,error:string,retry_after:int}
 */
function auth_admin_login(string $username, string $password): array
{
    $username = mb_strtolower(trim($username));

    $state = throttle_state('admin');
    if ($state['blocked']) {
        return ['ok' => false, 'error' => throttle_message($state['retry_after']), 'retry_after' => $state['retry_after']];
    }

    if ($username === '' || $password === '') {
        return ['ok' => false, 'error' => 'Renseigne ton identifiant et ton mot de passe.', 'retry_after' => 0];
    }

    try {
        $admin = db_fetch('SELECT id, username, display_name, password_hash, is_active FROM admins WHERE username = ? LIMIT 1', [$username]);
    } catch (Throwable $e) {
        log_event('Erreur de connexion administrateur (BDD) : ' . $e->getMessage(), 'error');

        return ['ok' => false, 'error' => 'Une erreur technique est survenue. Réessaie dans un instant.', 'retry_after' => 0];
    }

    $hash  = $admin['password_hash'] ?? AUTH_DUMMY_HASH;
    $valid = password_verify($password, $hash);

    if (!$admin || !$valid || (int) $admin['is_active'] !== 1) {
        throttle_record('admin', $username, false);
        log_event('Échec de connexion administrateur.', 'warning');

        return ['ok' => false, 'error' => 'Identifiant ou mot de passe incorrect.', 'retry_after' => 0];
    }

    if (password_needs_rehash($admin['password_hash'], PASSWORD_DEFAULT)) {
        db_query('UPDATE admins SET password_hash = ?, updated_at = ? WHERE id = ?', [
            password_hash($password, PASSWORD_DEFAULT),
            now()->format('Y-m-d H:i:s'),
            (int) $admin['id'],
        ]);
    }

    auth_restart_session();
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'type'         => 'admin',
        'id'           => (int) $admin['id'],
        'username'     => (string) $admin['username'],
        'display_name' => (string) $admin['display_name'],
    ];
    $_SESSION['auth_time'] = time();
    $_SESSION['last_seen'] = time();
    $_SESSION['regen_at']  = time();

    db_query('UPDATE admins SET last_login_at = ? WHERE id = ?', [now()->format('Y-m-d H:i:s'), (int) $admin['id']]);
    throttle_record('admin', $username, true);
    audit('connexion_admin', 'Connexion réussie de ' . $admin['username']);

    return ['ok' => true, 'error' => '', 'retry_after' => 0];
}

/**
 * @return array{ok:bool,error:string,retry_after:int}
 */
function auth_visitor_login(string $password): array
{
    $state = throttle_state('visitor');
    if ($state['blocked']) {
        return ['ok' => false, 'error' => throttle_message($state['retry_after']), 'retry_after' => $state['retry_after']];
    }

    if ($password === '') {
        return ['ok' => false, 'error' => 'Renseigne le mot de passe commun.', 'retry_after' => 0];
    }

    try {
        $row = db_fetch('SELECT password_hash FROM visitor_credentials WHERE id = 1 LIMIT 1');
    } catch (Throwable $e) {
        log_event('Erreur de connexion visiteur (BDD) : ' . $e->getMessage(), 'error');

        return ['ok' => false, 'error' => 'Une erreur technique est survenue. Réessaie dans un instant.', 'retry_after' => 0];
    }

    $hash  = $row['password_hash'] ?? AUTH_DUMMY_HASH;
    $valid = password_verify($password, $hash);

    if (!$row || !$valid) {
        throttle_record('visitor', null, false);
        log_event('Échec de connexion visiteur.', 'warning');

        return ['ok' => false, 'error' => 'Mot de passe incorrect.', 'retry_after' => 0];
    }

    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db_query('UPDATE visitor_credentials SET password_hash = ?, updated_at = ? WHERE id = 1', [
            password_hash($password, PASSWORD_DEFAULT),
            now()->format('Y-m-d H:i:s'),
        ]);
    }

    auth_restart_session();
    session_regenerate_id(true);

    $_SESSION['user'] = [
        'type'         => 'visitor',
        'id'           => 0,
        'username'     => 'visiteur',
        'display_name' => 'Visiteur',
    ];
    $_SESSION['auth_time'] = time();
    $_SESSION['last_seen'] = time();
    $_SESSION['regen_at']  = time();

    throttle_record('visitor', null, true);

    return ['ok' => true, 'error' => '', 'retry_after' => 0];
}

function auth_logout(): void
{
    auth_clear_session();
    auth_start_session();
    session_regenerate_id(true);
    flash('success', 'Tu es bien déconnecté(e). À très vite.');
}

function visitor_password_exists(): bool
{
    try {
        return (int) db_value('SELECT COUNT(*) FROM visitor_credentials WHERE id = 1') > 0;
    } catch (Throwable $e) {
        return false;
    }
}

function set_admin_password(int $adminId, string $plainPassword): void
{
    db_query('UPDATE admins SET password_hash = ?, updated_at = ? WHERE id = ?', [
        password_hash($plainPassword, PASSWORD_DEFAULT),
        now()->format('Y-m-d H:i:s'),
        $adminId,
    ]);

    // Met à jour le drapeau « mots de passe par défaut encore actifs ».
    refresh_default_password_flag();
}

function set_visitor_password(string $plainPassword): void
{
    db_query(
        'INSERT INTO visitor_credentials (id, password_hash, password_updated_at, updated_at)
         VALUES (1, ?, ?, ?)
         ON DUPLICATE KEY UPDATE password_hash = VALUES(password_hash),
             password_updated_at = VALUES(password_updated_at),
             updated_at = VALUES(updated_at)',
        [
            password_hash($plainPassword, PASSWORD_DEFAULT),
            now()->format('Y-m-d H:i:s'),
            now()->format('Y-m-d H:i:s'),
        ]
    );

    refresh_default_password_flag();
}
