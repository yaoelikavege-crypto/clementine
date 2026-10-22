<?php
/**
 * Assistant de première installation.
 *
 * Sécurité :
 *  - accès protégé par une clé secrète définie dans includes/config.php ;
 *  - l'assistant se verrouille définitivement après l'installation
 *    (fichier storage/installed.lock + drapeau en base) ;
 *  - aucune information de connexion MySQL n'est affichée.
 */

define('SETUP_CONTEXT', true);

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/installer.php';

if (!is_file(ROOT_PATH . '/includes/config.php')) {
    render_configuration_missing_page();
    exit;
}

/* -------------------------------------------------------------------------
 *  Assistant déjà verrouillé
 * ---------------------------------------------------------------------- */

if (installer_is_locked() && !(get_int('done') === 1 && ($_SESSION['setup_finalized'] ?? false))) {
    http_response_code(403);
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>Installation verrouillée · Clémentine</title>
    <link rel="stylesheet" href="<?= e(asset('assets/css/main.css')) ?>">
</head>
<body class="page page--setup">
<main class="setup-wrap">
    <div class="setup-panel glass">
        <p class="eyebrow">Installation terminée</p>
        <h1 class="setup-title">L'assistant est verrouillé</h1>
        <p class="setup-lead">
            Ce site a déjà été installé. Pour des raisons de sécurité, l'assistant refuse de s'exécuter une seconde fois.
        </p>
        <div class="setup-actions">
            <a class="btn btn--primary" href="<?= e(app_url('index.php')) ?>">Ouvrir le site</a>
            <a class="btn btn--ghost" href="<?= e(app_url('login.php')) ?>">Se connecter</a>
        </div>
        <div class="setup-note setup-note--warning">
            <strong>Réinstallation volontaire ?</strong>
            <span>Supprimez le fichier <code>storage/installed.lock</code>, videz les tables de la base puis rechargez cette page. Cette opération efface tous les contenus. Consultez le README (section « Réinstallation »).</span>
        </div>
    </div>
</main>
</body>
</html>
    <?php
    exit;
}

/* -------------------------------------------------------------------------
 *  Connexion base de données (test)
 * ---------------------------------------------------------------------- */

$dbError      = '';
$dbConnected  = false;

try {
    db()->query('SELECT 1');
    $dbConnected = true;
} catch (Throwable $e) {
    $dbError = installer_describe_db_error($e);
    log_event('Assistant : connexion MySQL impossible — ' . $e->getMessage(), 'error');
}

$tablesReady  = $dbConnected ? installer_tables_ready() : false;
$adminCount   = $tablesReady ? installer_admins_count() : 0;
$visitorReady = $tablesReady ? visitor_password_exists() : false;

$checks       = installer_environment_checks();
$criticalFail = false;
foreach ($checks as $check) {
    if ($check['critical'] && !$check['ok']) {
        $criticalFail = true;
    }
}

/* -------------------------------------------------------------------------
 *  État d'autorisation
 * ---------------------------------------------------------------------- */

$_SESSION['setup'] = $_SESSION['setup'] ?? [
    'authorized' => false,
    'tries'      => 0,
    'blocked_at' => 0,
];

$setup        = &$_SESSION['setup'];
$action       = post_string('action');
$errors       = [];
$installKey   = (string) config('app.install_key', '');
$keyConfigured = $installKey !== '' && !str_contains($installKey, 'REMPLACER');

/* -------------------------------------------------------------------------
 *  Traitement des formulaires (POST)
 * ---------------------------------------------------------------------- */

if (is_post()) {
    require_csrf();

    $action = post_string('action');

    if ($action === 'auth') {
        $provided = post_string('install_key');

        if (($setup['blocked_at'] ?? 0) > time()) {
            $errors[] = 'Trop de tentatives. Patientez quelques minutes.';
        } elseif (!$keyConfigured) {
            $errors[] = 'Définissez d\'abord une clé d\'installation personnalisée dans includes/config.php.';
        } elseif (hash_equals($installKey, $provided)) {
            $setup['authorized'] = true;
            $setup['tries'] = 0;
            flash('success', 'Accès à l\'assistant autorisé.');
            redirect(app_url('setup/index.php'));
        } else {
            $setup['tries'] = (int) ($setup['tries'] ?? 0) + 1;
            if ($setup['tries'] >= 5) {
                $setup['blocked_at'] = time() + 900;
            }
            sleep(1);
            $errors[] = 'Clé d\'installation incorrecte.';
            log_event('Échec d\'autorisation de l\'assistant d\'installation.', 'warning');
        }
    } elseif (!($setup['authorized'] ?? false)) {
        $errors[] = 'Autorisation requise avant toute action.';
    } elseif ($action === 'create_tables') {
        if (!$dbConnected) {
            $errors[] = 'La base de données est injoignable.';
        } elseif ($criticalFail) {
            $errors[] = 'Corrigez les contrôles techniques bloquants avant de continuer.';
        } else {
            $schemaResult = installer_run_schema(db());
            if (!$schemaResult['ok']) {
                $errors[] = $schemaResult['error'];
            } else {
                $seedResult = installer_run_seeds(db());
                if (!$seedResult['ok']) {
                    $errors[] = $seedResult['error'];
                } else {
                    flash('success', 'Tables créées et contenus de base installés.');

                    // Comptes de première connexion définis dans includes/config.php.
                    $seed = installer_seed_default_accounts();
                    if ($seed['created'] !== [] || $seed['visitor']) {
                        flash('info', 'Comptes par défaut créés : ' . implode(', ', $seed['created'])
                            . ($seed['visitor'] ? ' + accès visiteur' : '') . '. Changez ces mots de passe après la première connexion.');
                    }
                    foreach ($seed['errors'] as $seedError) {
                        flash('warning', $seedError);
                    }

                    redirect(app_url('setup/index.php'));
                }
            }
        }
    } elseif ($action === 'create_admin') {
        if (!$tablesReady) {
            $errors[] = 'Créez d\'abord les tables.';
        } else {
            $password = (string) ($_POST['password'] ?? '');
            $confirm  = (string) ($_POST['password_confirm'] ?? '');

            if ($password !== $confirm) {
                $errors[] = 'Les deux mots de passe ne correspondent pas.';
            } else {
                $result = installer_create_admin(post_string('username'), post_string('display_name'), $password);

                if (!$result['ok']) {
                    $errors[] = $result['error'];
                } else {
                    flash('success', 'Compte administrateur « ' . post_string('username') . ' » créé.');
                    redirect(app_url('setup/index.php'));
                }
            }
        }
    } elseif ($action === 'set_visitor') {
        if (!$tablesReady) {
            $errors[] = 'Créez d\'abord les tables.';
        } elseif ($visitorReady) {
            $errors[] = 'Le mot de passe visiteur est déjà configuré.';
        } else {
            $password = (string) ($_POST['password'] ?? '');
            $confirm  = (string) ($_POST['password_confirm'] ?? '');
            $policy   = password_policy_error($password, 'visitor');

            if ($policy !== null) {
                $errors[] = $policy;
            } elseif ($password !== $confirm) {
                $errors[] = 'Les deux mots de passe ne correspondent pas.';
            } else {
                set_visitor_password($password);
                flash('success', 'Mot de passe commun des visiteurs enregistré.');
                redirect(app_url('setup/index.php'));
            }
        }
    } elseif ($action === 'seed_defaults') {
        if (!$tablesReady) {
            $errors[] = 'Créez d\'abord les tables.';
        } else {
            $seed = installer_seed_default_accounts();

            if (!$seed['ok']) {
                foreach ($seed['errors'] as $seedError) {
                    $errors[] = $seedError;
                }
            } else {
                if ($seed['created'] !== []) {
                    flash('success', 'Comptes administrateurs créés : ' . implode(', ', $seed['created']) . '.');
                }
                if ($seed['skipped'] !== []) {
                    flash('info', 'Comptes déjà présents (ignorés) : ' . implode(', ', $seed['skipped']) . '.');
                }
                if ($seed['visitor']) {
                    flash('success', 'Mot de passe visiteur enregistré.');
                }
                flash('warning', 'Ces mots de passe sont des mots de passe de première connexion : changez-les dans Administration → Mots de passe.');
                redirect(app_url('setup/index.php'));
            }
        }
    } elseif ($action === 'finalize') {
        if ($adminCount < 2) {
            $errors[] = 'Créez les deux comptes administrateurs avant de finaliser.';
        } elseif (!$visitorReady) {
            $errors[] = 'Définissez le mot de passe commun des visiteurs avant de finaliser.';
        } else {
            $result = installer_finalize();
            if (!$result['ok']) {
                $errors[] = $result['error'];
            } else {
                if (!$result['locked']) {
                    flash('warning', 'Le fichier de verrouillage n\'a pas pu être écrit. Le site reste protégé par la base, mais créez manuellement un fichier vide storage/installed.lock.');
                }
                $_SESSION['setup_finalized'] = true;
                redirect(app_url('setup/index.php?done=1'));
            }
        }
    }
}

$done = get_int('done') === 1 || (installer_is_locked());

/* -------------------------------------------------------------------------
 *  Affichage
 * ---------------------------------------------------------------------- */

$step = 'auth';
if ($setup['authorized'] ?? false) {
    if (!$tablesReady) {
        $step = 'tables';
    } elseif ($adminCount < 2) {
        $step = 'admins';
    } elseif (!$visitorReady) {
        $step = 'visitor';
    } else {
        $step = 'finalize';
    }
}

$cssUrl = asset('assets/css/main.css');
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="theme-color" content="#0B1026">
    <title>Installation · Clémentine</title>
    <link rel="icon" href="<?= e(asset('assets/images/favicon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($cssUrl) ?>">
</head>
<body class="page page--setup">
<div class="ambient" aria-hidden="true">
    <div class="aurora aurora--violet"></div>
    <div class="aurora aurora--rose"></div>
    <div class="grain"></div>
</div>

<main class="setup-wrap">
    <header class="setup-head">
        <p class="eyebrow">NovaTechX · Installation</p>
        <h1 class="setup-title">Mise en route de l'expérience</h1>
        <p class="setup-lead">Trois minutes pour préparer le site d'anniversaire de Clémentine.</p>
    </header>

    <?php if ($errors) : ?>
        <div class="setup-errors" role="alert">
            <?php foreach ($errors as $error) : ?>
                <p><?= icon('alert-triangle', 17) ?><span><?= e($error) ?></span></p>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>

    <?= render_flashes() ?>

    <?php if ($done) : ?>

        <?php $_SESSION['setup_finalized'] = false; ?>
        <section class="setup-panel glass setup-panel--success">
            <span class="setup-success-icon"><?= icon('check-circle', 34) ?></span>
            <h2>Installation terminée</h2>
            <p>Le site est prêt. L'assistant est désormais verrouillé et ne peut plus être rejoué.</p>

            <ul class="setup-checklist">
                <li><?= icon('check', 16) ?><span>Vérifie que le site s'ouvre en <strong>HTTPS</strong> (cadenas du navigateur).</span></li>
                <li><?= icon('check', 16) ?><span>Connecte-toi à l'administration avec tes identifiants et change immédiatement le mot de passe visiteur si nécessaire.</span></li>
                <li><?= icon('check', 16) ?><span>Conserve une sauvegarde de <code>includes/config.php</code> et de la base de données.</span></li>
                <li><?= icon('check', 16) ?><span>Le dossier <code>setup/</code> peut rester en place : il est verrouillé côté serveur.</span></li>
            </ul>

            <div class="setup-actions">
                <a class="btn btn--primary" href="<?= e(app_url('index.php')) ?>">Voir le site</a>
                <a class="btn btn--ghost" href="<?= e(app_url('login.php')) ?>">Se connecter</a>
            </div>

            <p class="setup-fineprint">Développé par KavegeElis · NovaTechX</p>
        </section>

    <?php else : ?>

        <ol class="setup-steps" aria-label="Étapes de l'installation">
            <li class="<?= $step === 'auth' ? 'is-current' : ($setup['authorized'] ? 'is-done' : '') ?>"><span>1</span>Autorisation</li>
            <li class="<?= $step === 'tables' ? 'is-current' : ($tablesReady ? 'is-done' : '') ?>"><span>2</span>Base de données</li>
            <li class="<?= $step === 'admins' ? 'is-current' : ($adminCount >= 2 ? 'is-done' : '') ?>"><span>3</span>Administrateurs</li>
            <li class="<?= $step === 'visitor' ? 'is-current' : ($visitorReady ? 'is-done' : '') ?>"><span>4</span>Accès visiteur</li>
            <li class="<?= $step === 'finalize' ? 'is-current' : '' ?>"><span>5</span>Finalisation</li>
        </ol>

        <?php if ($step === 'auth') : ?>
            <section class="setup-panel glass">
                <h2>1. Autorisation</h2>
                <p>
                    L'assistant est protégé par la clé définie dans <code>includes/config.php</code>
                    (paramètre <code>app.install_key</code>). Elle ne doit jamais être partagée.
                </p>

                <?php if (!$keyConfigured) : ?>
                    <div class="setup-note setup-note--warning">
                        La clé d'installation n'est pas encore personnalisée. Ouvrez <code>includes/config.php</code>
                        et remplacez la valeur de <code>install_key</code> par une phrase longue et aléatoire.
                    </div>
                <?php else : ?>
                    <form method="post" class="setup-form">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="auth">
                        <label class="field">
                            <span class="field__label">Clé d'installation</span>
                            <input class="field__input" type="password" name="install_key" autocomplete="off" required autofocus>
                        </label>
                        <button class="btn btn--primary" type="submit">Déverrouiller l'assistant</button>
                    </form>
                <?php endif; ?>
            </section>

        <?php else : ?>

            <section class="setup-panel glass">
                <h2>2. Vérifications & base de données</h2>

                <?php if (!$dbConnected) : ?>
                    <div class="setup-note setup-note--error">
                        <strong>Connexion MySQL impossible.</strong>
                        <span><?= e($dbError) ?></span>
                    </div>
                <?php else : ?>
                    <p class="setup-ok"><?= icon('check-circle', 18) ?> Connexion MySQL réussie.</p>
                <?php endif; ?>

                <ul class="setup-checks">
                    <?php foreach ($checks as $check) : ?>
                        <li class="setup-check <?= $check['ok'] ? 'is-ok' : 'is-ko' ?>">
                            <?= icon($check['ok'] ? 'check-circle' : 'alert-triangle', 18) ?>
                            <span>
                                <strong><?= e($check['label']) ?></strong>
                                <em><?= e($check['detail']) ?></em>
                            </span>
                        </li>
                    <?php endforeach; ?>
                </ul>

                <?php if ($tablesReady) : ?>
                    <p class="setup-ok"><?= icon('check-circle', 18) ?> Les tables existent déjà (<?= e((string) $adminCount) ?> administrateur(s) enregistré(s)).</p>
                <?php elseif ($dbConnected) : ?>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="create_tables">
                        <p class="setup-hint">Cette étape crée les 12 tables, les 4 playlists, les textes de départ et la lettre d'amour.</p>
                        <button class="btn btn--primary" type="submit" <?= ($criticalFail || !$dbConnected) ? 'disabled' : '' ?>>
                            Créer la structure et les contenus de base
                        </button>
                    </form>
                <?php endif; ?>
            </section>

            <?php if ($tablesReady) : ?>
                <section class="setup-panel glass">
                    <h2>3. Comptes administrateurs</h2>
                    <p>
                        Deux comptes indépendants, avec les mêmes droits : le développeur et Joël.
                        Vous pouvez les créer automatiquement depuis <code>includes/config.php</code>
                        ou les saisir à la main.
                    </p>

                    <?php $defaults = default_accounts_config(); ?>
                    <?php if (($defaults['admins'] !== [] || $defaults['visitor'] !== null) && $adminCount < 2) : ?>
                        <div class="setup-note setup-note--info">
                            <strong>Comptes par défaut disponibles</strong>
                            <span>
                                Définis dans <code>includes/config.php</code> (paramètre <code>app.default_accounts</code>).
                                Ils sont créés en un clic, sans passer par la politique de mot de passe.
                            </span>
                        </div>

                        <ul class="setup-admins setup-admins--defaults">
                            <?php foreach ($defaults['admins'] as $index => $defaultAdmin) : ?>
                                <li>
                                    <?= icon('shield', 16) ?>
                                    <span>
                                        <strong><?= e($defaultAdmin['username']) ?></strong> — <?= e($defaultAdmin['display_name']) ?>
                                        <em>mot de passe : <code><?= e($defaultAdmin['password']) ?></code></em>
                                    </span>
                                </li>
                            <?php endforeach; ?>
                            <?php if ($defaults['visitor'] !== null) : ?>
                                <li>
                                    <?= icon('users', 16) ?>
                                    <span>
                                        <strong>Visiteur</strong> — <?= e($defaults['visitor']['display_name']) ?>
                                        <em>mot de passe : <code><?= e($defaults['visitor']['password']) ?></code></em>
                                    </span>
                                </li>
                            <?php endif; ?>
                        </ul>

                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="seed_defaults">
                            <button class="btn btn--primary" type="submit"><?= icon('check', 17) ?> Créer ces comptes maintenant</button>
                        </form>

                        <p class="setup-hint">
                            Ces mots de passe apparaissent en clair : changez-les dès la première connexion,
                            dans <strong>Administration → Mots de passe</strong>.
                        </p>
                    <?php endif; ?>

                    <?php
                    $existing = db_fetch_all('SELECT username, display_name FROM admins ORDER BY id');
                    ?>
                    <?php if ($existing) : ?>
                        <ul class="setup-admins">
                            <?php foreach ($existing as $admin) : ?>
                                <li><?= icon('shield', 16) ?><span><strong><?= e($admin['username']) ?></strong> — <?= e($admin['display_name']) ?></span></li>
                            <?php endforeach; ?>
                        </ul>
                    <?php endif; ?>

                    <?php if ($adminCount < 2) : ?>
                        <details class="setup-manual"<?= $defaults['admins'] === [] ? ' open' : '' ?>>
                        <summary>Créer un compte à la main</summary>
                        <form method="post" class="setup-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="create_admin">
                            <p class="setup-hint">Création du compte administrateur n°<?= e((string) ($adminCount + 1)) ?></p>
                            <label class="field">
                                <span class="field__label">Identifiant (sans espace)</span>
                                <input class="field__input" type="text" name="username" required minlength="3" maxlength="50" pattern="[A-Za-z0-9._\-]+" autocomplete="off">
                            </label>
                            <label class="field">
                                <span class="field__label">Nom affiché</span>
                                <input class="field__input" type="text" name="display_name" required minlength="2" maxlength="100" placeholder="Joël, KavegeElis…">
                            </label>
                            <label class="field">
                                <span class="field__label">Mot de passe (10 caractères minimum, lettres et chiffres)</span>
                                <input class="field__input" type="password" name="password" required minlength="10" autocomplete="new-password">
                            </label>
                            <label class="field">
                                <span class="field__label">Confirmation du mot de passe</span>
                                <input class="field__input" type="password" name="password_confirm" required minlength="10" autocomplete="new-password">
                            </label>
                            <button class="btn btn--primary" type="submit">Créer ce compte</button>
                        </form>
                        </details>
                    <?php else : ?>
                        <p class="setup-ok"><?= icon('check-circle', 18) ?> Les deux comptes administrateurs sont créés.</p>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($tablesReady && $adminCount >= 2) : ?>
                <section class="setup-panel glass">
                    <h2>4. Mot de passe commun des visiteurs</h2>
                    <p>
                        Ce mot de passe sera transmis à Clémentine et à ses camarades. Il ne donne accès
                        qu'à la lecture des souvenirs, après la révélation du <?= e(reveal_date_text()) ?>.
                    </p>

                    <?php if ($visitorReady) : ?>
                        <p class="setup-ok"><?= icon('check-circle', 18) ?> Mot de passe visiteur configuré.</p>
                    <?php else : ?>
                        <form method="post" class="setup-form">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="set_visitor">
                            <label class="field">
                                <span class="field__label">Mot de passe commun (8 caractères minimum, lettres et chiffres)</span>
                                <input class="field__input" type="password" name="password" required minlength="8" autocomplete="new-password">
                            </label>
                            <label class="field">
                                <span class="field__label">Confirmation</span>
                                <input class="field__input" type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
                            </label>
                            <button class="btn btn--primary" type="submit">Enregistrer</button>
                        </form>
                    <?php endif; ?>
                </section>
            <?php endif; ?>

            <?php if ($tablesReady && $adminCount >= 2 && $visitorReady) : ?>
                <section class="setup-panel glass setup-panel--final">
                    <h2>5. Finalisation</h2>
                    <p>
                        Dernière étape : elle verrouille définitivement l'assistant. Vérifiez une dernière fois
                        que les deux administrateurs et le mot de passe visiteur sont corrects.
                    </p>
                    <form method="post">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="finalize">
                        <button class="btn btn--primary btn--lg" type="submit">Terminer l'installation</button>
                    </form>
                </section>
            <?php endif; ?>

        <?php endif; ?>

        <p class="setup-fineprint">
            Assistant d'installation · Aucun identifiant de base de données n'est affiché sur cette page.
            En cas de blocage, consultez le fichier DEPLOYMENT.md.
        </p>

    <?php endif; ?>
</main>
</body>
</html>
