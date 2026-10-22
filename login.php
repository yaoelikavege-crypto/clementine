<?php
/**
 * Connexion : deux profils (administrateur / visiteur).
 * Le rôle réel est déterminé par le serveur dans actions/auth.php.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

if (is_authenticated()) {
    if (is_admin()) {
        redirect(app_url('admin/dashboard.php'));
    }

    redirect(is_revealed() ? app_url('pages/memories.php') : app_url('pages/countdown.php'));
}

$type    = strtolower(input('type'));
$type    = in_array($type, ['admin', 'visitor'], true) ? $type : '';
$expired = get_int('expired') === 1;

public_header([
    'title'       => 'Connexion',
    'description' => 'Accès privé au site d\'anniversaire de Clémentine.',
    'active'      => 'login',
]);
?>

<section class="auth">
    <div class="auth__panel glass">
        <div class="auth__intro">
            <p class="eyebrow">Espace privé</p>
            <h1 class="auth__title">Entre dans<br><span>l'expérience</span></h1>
            <p class="auth__lead">
                Ce site est réservé à Clémentine et à ses proches.
                Choisis ton profil pour continuer.
            </p>
        </div>

        <?php if ($expired) : ?>
            <div class="notice notice--warning" role="status">
                <?= icon('clock', 17) ?>
                <span>Ta session a expiré pour des raisons de sécurité. Connecte-toi à nouveau.</span>
            </div>
        <?php endif; ?>

        <?= render_flashes() ?>

        <div class="auth__tabs" role="tablist" aria-label="Choix du profil">
            <a class="auth-tab<?= $type === 'admin' ? ' is-active' : '' ?>" href="<?= e(app_url('login.php?type=admin')) ?>" role="tab" aria-selected="<?= $type === 'admin' ? 'true' : 'false' ?>">
                <?= icon('shield', 19) ?>
                <span class="auth-tab__title">Administrateur</span>
                <span class="auth-tab__hint">Joël · KavegeElis</span>
            </a>
            <a class="auth-tab<?= $type === 'visitor' ? ' is-active' : '' ?>" href="<?= e(app_url('login.php?type=visitor')) ?>" role="tab" aria-selected="<?= $type === 'visitor' ? 'true' : 'false' ?>">
                <?= icon('user', 19) ?>
                <span class="auth-tab__title">Visiteur</span>
                <span class="auth-tab__hint">Clémentine &amp; ses camarades</span>
            </a>
        </div>

        <?php if ($type === 'admin') : ?>
            <form class="auth__form" method="post" action="<?= e(app_url('actions/auth.php')) ?>" autocomplete="on">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="admin">

                <label class="field">
                    <span class="field__label">Identifiant</span>
                    <input class="field__input" type="text" name="username" required maxlength="50" autocomplete="username" autofocus>
                </label>

                <label class="field">
                    <span class="field__label">Mot de passe</span>
                    <span class="field__password">
                        <input class="field__input" type="password" name="password" required autocomplete="current-password" data-password-input>
                        <button class="field__eye" type="button" data-password-toggle aria-label="Afficher le mot de passe"><?= icon('eye', 18) ?></button>
                    </span>
                </label>

                <button class="btn btn--primary btn--block" type="submit">
                    <?= icon('log-in', 18) ?><span>Ouvrir l'administration</span>
                </button>

                <p class="auth__note">
                    Les deux comptes administrateurs disposent des mêmes droits.
                    En cas d'oubli, la procédure de réinitialisation est décrite dans le README.
                </p>
            </form>

        <?php elseif ($type === 'visitor') : ?>
            <form class="auth__form" method="post" action="<?= e(app_url('actions/auth.php')) ?>" autocomplete="on">
                <?= csrf_field() ?>
                <input type="hidden" name="type" value="visitor">

                <label class="field">
                    <span class="field__label">Mot de passe commun</span>
                    <span class="field__password">
                        <input class="field__input" type="password" name="password" required autocomplete="current-password" data-password-input autofocus>
                        <button class="field__eye" type="button" data-password-toggle aria-label="Afficher le mot de passe"><?= icon('eye', 18) ?></button>
                    </span>
                </label>

                <button class="btn btn--primary btn--block" type="submit">
                    <?= icon('heart', 18) ?><span>Entrer</span>
                </button>

                <p class="auth__note">
                    <?= e(setting('visitor_hint', 'Le mot de passe te sera communiqué par Joël.')) ?>
                    <?php if (!is_revealed()) : ?>
                        <br>L'accès aux souvenirs s'ouvrira le <strong><?= e(reveal_moment_text()) ?></strong>.
                    <?php endif; ?>
                </p>
            </form>

        <?php else : ?>
            <div class="auth__choose">
                <a class="btn btn--soft btn--block" href="<?= e(app_url('login.php?type=visitor')) ?>">
                    <?= icon('user', 18) ?><span>Je suis Clémentine ou une invitée</span>
                </a>
                <a class="btn btn--ghost btn--block" href="<?= e(app_url('login.php?type=admin')) ?>">
                    <?= icon('shield', 18) ?><span>Je suis administrateur</span>
                </a>
            </div>
        <?php endif; ?>

        <p class="auth__back">
            <a href="<?= e(app_url('index.php')) ?>"><?= icon('arrow-left', 15) ?> Retour à l'accueil</a>
        </p>
    </div>

    <aside class="auth__aside" aria-hidden="true">
        <div class="auth__orb"></div>
        <div class="auth__aside-card glass">
            <span class="auth__aside-icon"><?= icon('sparkles', 22) ?></span>
            <p>« Certaines surprises méritent qu'on attende minuit. »</p>
        </div>
    </aside>
</section>

<?php
public_footer();
