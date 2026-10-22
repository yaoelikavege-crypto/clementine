<?php
/**
 * Assistant d'installation : vérifications, création du schéma,
 * données initiales, comptes administrateurs, verrouillage final.
 *
 * Ce fichier n'est utilisé que par /setup/index.php et par les outils CLI.
 */

function installer_schema(): array
{
    static $schema = null;

    if ($schema === null) {
        $schema = require __DIR__ . '/schema.php';
    }

    return $schema;
}

/**
 * @return array{ok:bool,error:string,count:int}
 */
function installer_run_schema(PDO $pdo): array
{
    $schema = installer_schema();
    $count  = 0;

    foreach ($schema['tables'] as $table => $sql) {
        try {
            $pdo->exec($sql);
            $count++;
        } catch (Throwable $e) {
            log_event('Échec de création de la table ' . $table . ' : ' . $e->getMessage(), 'error');

            return ['ok' => false, 'error' => installer_describe_db_error($e), 'count' => $count];
        }
    }

    return ['ok' => true, 'error' => '', 'count' => $count];
}

/**
 * @return array{ok:bool,error:string,count:int}
 */
function installer_run_seeds(PDO $pdo): array
{
    $schema = installer_schema();
    $total  = 0;

    foreach ($schema['seeds'] as $table => $spec) {
        try {
            if (!empty($spec['only_if_empty'])) {
                $existing = (int) $pdo->query('SELECT COUNT(*) FROM `' . $table . '`')->fetchColumn();
                if ($existing > 0) {
                    continue;
                }
            }

            $columns = $spec['columns'];
            $columnSql = '`' . implode('`, `', $columns) . '`';
            $placeholders = implode(', ', array_fill(0, count($columns), '?'));
            $verb = !empty($spec['unique']) ? 'INSERT IGNORE' : 'INSERT';

            $stmt = $pdo->prepare($verb . ' INTO `' . $table . '` (' . $columnSql . ') VALUES (' . $placeholders . ')');

            foreach ($spec['rows'] as $row) {
                $stmt->execute(array_values($row));
                $total++;
            }
        } catch (Throwable $e) {
            log_event('Échec du chargement des données initiales (' . $table . ') : ' . $e->getMessage(), 'error');

            return ['ok' => false, 'error' => installer_describe_db_error($e), 'count' => $total];
        }
    }

    return ['ok' => true, 'error' => '', 'count' => $total];
}

function installer_admins_count(): int
{
    try {
        return (int) db_value('SELECT COUNT(*) FROM admins');
    } catch (Throwable $e) {
        return 0;
    }
}

function installer_tables_ready(): bool
{
    try {
        $rows = db_fetch_all("SHOW TABLES LIKE 'admins'");

        return count($rows) > 0;
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * @return array{ok:bool,error:string,id:int}
 */
function installer_create_admin(string $username, string $displayName, string $password, bool $trusted = false): array
{
    $username = mb_strtolower(trim($username));

    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
        return ['ok' => false, 'error' => 'L\'identifiant doit contenir entre 3 et 50 caractères : lettres non accentuées, chiffres, point, tiret ou underscore.', 'id' => 0];
    }

    $displayName = trim($displayName);
    if (mb_strlen($displayName) < 2 || mb_strlen($displayName) > 100) {
        return ['ok' => false, 'error' => 'Le nom affiché doit contenir entre 2 et 100 caractères.', 'id' => 0];
    }

    // `$trusted` n'est utilisé que pour les comptes par défaut définis dans
    // includes/config.php : ils sont créés tels quels, puis signalés comme
    // « mots de passe par défaut » jusqu'à leur changement.
    if (!$trusted) {
        $policyError = password_policy_error($password, 'admin', $username);
        if ($policyError !== null) {
            return ['ok' => false, 'error' => $policyError, 'id' => 0];
        }
    }

    try {
        $exists = db_value('SELECT COUNT(*) FROM admins WHERE username = ?', [$username]);
        if ((int) $exists > 0) {
            return ['ok' => false, 'error' => 'Cet identifiant est déjà utilisé.', 'id' => 0];
        }

        db_query(
            'INSERT INTO admins (username, display_name, password_hash, is_active, created_at) VALUES (?, ?, ?, 1, ?)',
            [$username, $displayName, password_hash($password, PASSWORD_DEFAULT), now()->format('Y-m-d H:i:s')]
        );

        return ['ok' => true, 'error' => '', 'id' => (int) db()->lastInsertId()];
    } catch (Throwable $e) {
        log_event('Création administrateur impossible : ' . $e->getMessage(), 'error');

        return ['ok' => false, 'error' => installer_describe_db_error($e), 'id' => 0];
    }
}

/**
 * Crée les comptes définis dans includes/config.php (app.default_accounts).
 * Les comptes déjà présents sont ignorés : la fonction peut être rejouée.
 *
 * @return array{ok:bool, created:array<int,string>, skipped:array<int,string>, visitor:bool, errors:array<int,string>}
 */
function installer_seed_default_accounts(): array
{
    $result = ['ok' => true, 'created' => [], 'skipped' => [], 'visitor' => false, 'errors' => []];
    $defaults = default_accounts_config();

    if ($defaults['admins'] === [] && $defaults['visitor'] === null) {
        $result['ok'] = false;
        $result['errors'][] = "Aucun compte par défaut n'est défini dans includes/config.php (app.default_accounts).";
        return $result;
    }

    foreach ($defaults['admins'] as $admin) {
        $exists = (int) db_value('SELECT COUNT(*) FROM admins WHERE username = ?', [$admin['username']]);

        if ($exists > 0) {
            $result['skipped'][] = $admin['username'];
            continue;
        }

        $created = installer_create_admin($admin['username'], $admin['display_name'], $admin['password'], true);

        if (!$created['ok']) {
            $result['ok'] = false;
            $result['errors'][] = $created['error'];
            continue;
        }

        $result['created'][] = $admin['username'];
    }

    if ($defaults['visitor'] !== null && !visitor_password_exists()) {
        set_visitor_password($defaults['visitor']['password']);
        $result['visitor'] = true;
    }

    if ($result['created'] !== [] || $result['visitor']) {
        refresh_default_password_flag();
        audit('comptes_par_defaut', 'Création automatique : ' . implode(', ', $result['created'])
            . ($result['visitor'] ? ' + accès visiteur' : ''));
    }

    return $result;
}

/**
 * @return array{ok:bool,error:string,locked:bool}
 */
function installer_finalize(): array
{
    $timestamp = now()->format('Y-m-d H:i:s');
    $locked    = false;

    try {
        db_query(
            'INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (\'installed\', \'1\', ?)
             ON DUPLICATE KEY UPDATE setting_value = \'1\', updated_at = VALUES(updated_at)',
            [$timestamp]
        );
        db_query(
            'INSERT INTO site_settings (setting_key, setting_value, updated_at) VALUES (\'installed_at\', ?, ?)
             ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value), updated_at = VALUES(updated_at)',
            [$timestamp, $timestamp]
        );
    } catch (Throwable $e) {
        log_event('Finalisation impossible : ' . $e->getMessage(), 'error');

        return ['ok' => false, 'error' => installer_describe_db_error($e), 'locked' => false];
    }

    $lockPath = installation_lock_path();

    if (!is_dir(dirname($lockPath))) {
        @mkdir(dirname($lockPath), 0755, true);
    }

    $payload = json_encode([
        'app'          => 'clementine-birthday',
        'version'      => defined('APP_VERSION') ? APP_VERSION : '1.0.0',
        'installed_at' => $timestamp,
        'php'          => PHP_VERSION,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

    if (@file_put_contents($lockPath, (string) $payload, LOCK_EX) !== false) {
        @chmod($lockPath, 0644);
        $locked = true;
    } else {
        log_event('Le verrou d\'installation n\'a pas pu être écrit (storage non inscriptible).', 'warning');
    }

    return ['ok' => true, 'error' => '', 'locked' => $locked];
}

function installer_is_locked(): bool
{
    if (is_file(installation_lock_path())) {
        return true;
    }

    try {
        $flag = db_value("SELECT setting_value FROM site_settings WHERE setting_key = 'installed' LIMIT 1");

        return (string) $flag === '1';
    } catch (Throwable $e) {
        return false;
    }
}

/**
 * Traduit une erreur PDO en message compréhensible, sans jamais révéler
 * d'identifiant ni de mot de passe.
 */
function installer_describe_db_error(Throwable $e): string
{
    $message = $e->getMessage();

    if (str_contains($message, '1045') || stripos($message, 'Access denied') !== false) {
        return 'Connexion refusée par MySQL : vérifiez l\'utilisateur et le mot de passe dans includes/config.php.';
    }
    if (str_contains($message, '1049') || stripos($message, 'Unknown database') !== false) {
        return 'La base de données indiquée n\'existe pas. Créez-la depuis le panneau InfinityFree puis recopiez son nom exact.';
    }
    if (str_contains($message, '2002') || stripos($message, 'Connection refused') !== false || stripos($message, 'getaddrinfo') !== false) {
        return 'Le serveur MySQL est injoignable. Vérifiez le nom d\'hôte fourni par l\'hébergeur (ex. sqlXXX.infinityfree.com).';
    }
    if (str_contains($message, '1044') || stripos($message, 'Access denied for user') !== false) {
        return 'L\'utilisateur MySQL n\'a pas les droits suffisants sur cette base.';
    }

    return 'Une erreur technique est survenue lors de cette étape. Consultez le journal (storage/logs/app.log) ou le README.';
}

/**
 * Contrôles affichés par l'assistant.
 *
 * @return array<int,array{label:string,ok:bool,detail:string,critical:bool}>
 */
function installer_environment_checks(): array
{
    $checks = [];

    $checks[] = [
        'label'    => 'Version de PHP 8.0 ou supérieure',
        'ok'       => version_compare(PHP_VERSION, '8.0.0', '>='),
        'detail'   => 'Version détectée : ' . PHP_VERSION,
        'critical' => true,
    ];

    $extensions = [
        'pdo_mysql' => 'PDO MySQL (base de données)',
        'mbstring'  => 'mbstring (textes accentués)',
        'gd'        => 'GD (miniatures et traitement des photos)',
        'fileinfo'  => 'fileinfo (contrôle réel du type des fichiers)',
        'json'      => 'JSON',
        'openssl'   => 'OpenSSL (hachage et HTTPS)',
    ];

    foreach ($extensions as $extension => $label) {
        $checks[] = [
            'label'    => 'Extension ' . $label,
            'ok'       => extension_loaded($extension),
            'detail'   => extension_loaded($extension) ? 'Disponible' : 'Absente — à activer auprès de l\'hébergeur',
            'critical' => true,
        ];
    }

    $uploads = uploads_dir();
    $checks[] = [
        'label'    => 'Dossier des médias accessible en écriture',
        'ok'       => is_dir($uploads) && is_writable($uploads),
        'detail'   => $uploads,
        'critical' => true,
    ];

    $checks[] = [
        'label'    => 'Dossier storage inscriptible (journaux, verrou)',
        'ok'       => is_dir(ROOT_PATH . '/storage') && is_writable(ROOT_PATH . '/storage'),
        'detail'   => ROOT_PATH . '/storage',
        'critical' => true,
    ];

    $installKey = (string) config('app.install_key', '');
    $checks[] = [
        'label'    => 'Clé d\'installation personnalisée',
        'ok'       => $installKey !== '' && !str_contains($installKey, 'REMPLACER'),
        'detail'   => 'Modifiez app.install_key dans includes/config.php',
        'critical' => true,
    ];

    $checks[] = [
        'label'    => 'Mode debug désactivé',
        'ok'       => !config('app.debug', false),
        'detail'   => 'app.debug doit valoir false en production',
        'critical' => false,
    ];

    $checks[] = [
        'label'    => 'HTTPS actif',
        'ok'       => is_https(),
        'detail'   => is_https() ? 'Connexion sécurisée' : 'Activez le certificat SSL gratuit dans InfinityFree',
        'critical' => false,
    ];

    try {
        $tz = new DateTimeZone((string) config('app.timezone', 'Africa/Lome'));
        $ok = $tz->getName() === 'Africa/Lome';
    } catch (Throwable $e) {
        $ok = false;
    }

    $checks[] = [
        'label'    => 'Fuseau horaire Africa/Lome',
        'ok'       => $ok,
        'detail'   => (string) config('app.timezone', 'Africa/Lome'),
        'critical' => true,
    ];

    return $checks;
}
