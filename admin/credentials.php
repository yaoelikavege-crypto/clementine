<?php
/**
 * Gestion des mots de passe (administrateurs et visiteur).
 * Aucun hachage n'est affiché.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$me     = current_user();
$meId   = (int) ($me['id'] ?? 0);
$myRow  = db_fetch('SELECT id, username, display_name, last_login_at FROM admins WHERE id = ?', [$meId]);
$others = db_fetch_all('SELECT id, username, display_name FROM admins WHERE id <> ? ORDER BY id ASC', [$meId]);
$visitor = db_fetch('SELECT password_updated_at, updated_at FROM visitor_credentials WHERE id = 1 LIMIT 1');
$defaults = default_accounts_config();
$remainingDefaults = default_passwords_remaining();

admin_header('Mots de passe', 'credentials');
?>

<?= admin_page_intro(
    'Mots de passe',
    'Mot de passe personnel, réinitialisation de l\'autre administrateur et accès commun des visiteurs.',
    '<span class="badge badge--violet">' . icon('shield', 13) . ' Hachage bcrypt</span>'
) ?>

<?php if ($remainingDefaults) : ?>
    <div class="notice notice--warning notice--wide" role="alert">
        <?= icon('alert-triangle', 18) ?>
        <span>
            <strong>Mots de passe par défaut encore actifs :</strong>
            <?= e(implode(', ', array_map(static fn (array $item): string => $item['label'] . ' (' . $item['username'] . ')', $remainingDefaults))) ?>.
            Change-les maintenant : tant qu'ils sont actifs, toute personne connaissant le site peut se connecter.
        </span>
    </div>
<?php endif; ?>

<div class="admin-grid admin-grid--2">
    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Mon compte · <?= e($myRow['username'] ?? '') ?></p>
                <h2>Changer mon mot de passe</h2>
            </div>
        </header>

        <form method="post" action="<?= e(app_url('actions/credentials.php')) ?>" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_own">

            <label class="field">
                <span class="field__label">Mot de passe actuel *</span>
                <input class="field__input" type="password" name="current_password" required autocomplete="current-password">
            </label>

            <label class="field">
                <span class="field__label">Nouveau mot de passe *</span>
                <input class="field__input" type="password" name="new_password" required minlength="10" autocomplete="new-password">
                <span class="field__hint">10 caractères minimum, au moins une lettre et un chiffre.</span>
            </label>

            <label class="field">
                <span class="field__label">Confirmation *</span>
                <input class="field__input" type="password" name="new_password_confirm" required minlength="10" autocomplete="new-password">
            </label>

            <div class="form-actions">
                <button class="btn btn--primary" type="submit"><?= icon('key', 17) ?> Mettre à jour</button>
            </div>
        </form>

        <?php if ($myRow && !empty($myRow['last_login_at'])) : ?>
            <p class="panel__empty">Dernière connexion : <?= e(fr_date($myRow['last_login_at'], true)) ?></p>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Identité</p>
                <h2>Mon profil</h2>
            </div>
        </header>

        <form method="post" action="<?= e(app_url('actions/credentials.php')) ?>" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="update_profile">

            <label class="field">
                <span class="field__label">Identifiant de connexion *</span>
                <input class="field__input" type="text" name="username" required minlength="3" maxlength="50"
                       pattern="[A-Za-z0-9._\-]+" value="<?= e($myRow['username'] ?? '') ?>" autocomplete="username">
                <span class="field__hint">Minuscules sans accent, chiffres, point, tiret ou underscore.</span>
            </label>

            <label class="field">
                <span class="field__label">Nom affiché *</span>
                <input class="field__input" type="text" name="display_name" required minlength="2" maxlength="100"
                       value="<?= e($myRow['display_name'] ?? '') ?>">
                <span class="field__hint">Il apparaît dans l'en-tête de l'administration et dans le journal d'activité.</span>
            </label>

            <div class="form-actions">
                <button class="btn btn--soft" type="submit"><?= icon('check', 17) ?> Enregistrer mon profil</button>
            </div>
        </form>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Visiteurs</p>
                <h2>Mot de passe commun</h2>
            </div>
            <?php if ($visitor) : ?>
                <?= admin_badge('Configuré', 'success') ?>
            <?php else : ?>
                <?= admin_badge('Non défini', 'warning') ?>
            <?php endif; ?>
        </header>

        <p class="panel__text">
            Ce mot de passe unique est transmis à Clémentine et à ses camarades.
            Il ne donne accès qu'à la consultation des souvenirs, et seulement après la révélation.
        </p>

        <?php if ($visitor && !empty($visitor['password_updated_at'])) : ?>
            <p class="panel__empty">Dernière modification : <?= e(fr_date($visitor['password_updated_at'], true)) ?></p>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('actions/credentials.php')) ?>" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="change_visitor">

            <label class="field">
                <span class="field__label">Nouveau mot de passe visiteur *</span>
                <input class="field__input" type="password" name="visitor_password" required minlength="8" autocomplete="new-password">
                <span class="field__hint">8 caractères minimum, au moins une lettre et un chiffre.</span>
            </label>

            <label class="field">
                <span class="field__label">Confirmation *</span>
                <input class="field__input" type="password" name="visitor_password_confirm" required minlength="8" autocomplete="new-password">
            </label>

            <div class="form-actions">
                <button class="btn btn--primary" type="submit"><?= icon('users', 17) ?> Modifier l'accès visiteur</button>
            </div>
        </form>
    </section>
</div>

<div class="admin-grid admin-grid--2">
    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Comptes administrateurs</p>
                <h2>Réinitialiser l'autre compte</h2>
            </div>
        </header>

        <p class="panel__text">
            En cas d'oubli, un administrateur peut générer un mot de passe temporaire pour l'autre compte.
            Il devra être changé dès la prochaine connexion.
        </p>

        <?php if (!$others) : ?>
            <p class="panel__empty">Aucun autre compte administrateur.</p>
        <?php else : ?>
            <form method="post" action="<?= e(app_url('actions/credentials.php')) ?>" class="admin-form" data-confirm="Générer un nouveau mot de passe temporaire pour cet administrateur ? Son ancien mot de passe ne fonctionnera plus.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="reset_other">

                <label class="field">
                    <span class="field__label">Compte à réinitialiser</span>
                    <select class="field__input" name="other_id" required>
                        <?php foreach ($others as $other) : ?>
                            <option value="<?= (int) $other['id'] ?>"><?= e($other['display_name']) ?> (<?= e($other['username']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Confirme ton propre mot de passe *</span>
                    <input class="field__input" type="password" name="own_password" required autocomplete="current-password">
                </label>

                <div class="form-actions">
                    <button class="btn btn--danger" type="submit"><?= icon('refresh', 17) ?> Générer un mot de passe temporaire</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <?php foreach ($others as $other) : ?>
        <section class="panel">
            <header class="panel__head">
                <div>
                    <p class="panel__eyebrow">Compte administrateur</p>
                    <h2><?= e($other['display_name']) ?></h2>
                </div>
                <?= admin_badge($other['username'], 'violet') ?>
            </header>

            <form method="post" action="<?= e(app_url('actions/credentials.php')) ?>" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_other">
                <input type="hidden" name="other_id" value="<?= (int) $other['id'] ?>">

                <label class="field">
                    <span class="field__label">Nom affiché *</span>
                    <input class="field__input" type="text" name="display_name" required minlength="2" maxlength="100"
                           value="<?= e($other['display_name']) ?>">
                </label>

                <div class="form-actions">
                    <button class="btn btn--soft" type="submit"><?= icon('check', 17) ?> Mettre à jour</button>
                </div>
            </form>

            <?php
            $otherDefault = null;
            foreach ($defaults['admins'] as $candidate) {
                if ($candidate['username'] === $other['username']) {
                    $otherDefault = $candidate['password'];
                }
            }
            ?>
            <?php if ($otherDefault !== null) : ?>
                <p class="panel__empty">
                    <?= icon('alert-triangle', 14) ?>
                    Ce compte utilise encore le mot de passe par défaut.
                </p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>

    <aside class="panel panel--aside">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Sécurité</p>
                <h2>Bon à savoir</h2>
            </div>
        </header>

        <ul class="tip-list">
            <li><?= icon('shield', 16) ?><span>Les mots de passe sont hachés avec <code>password_hash()</code> : personne, pas même toi, ne peut les lire dans la base.</span></li>
            <li><?= icon('clock', 16) ?><span>Cinq échecs de connexion bloquent temporairement les tentatives depuis une même adresse.</span></li>
            <li><?= icon('key', 16) ?><span>Procédure d'urgence : voir la section « Récupération d'accès » du README (SQL ou script CLI).</span></li>
            <li><?= icon('lock', 16) ?><span>Ne communique le mot de passe visiteur que par un canal privé (oral, message direct).</span></li>
        </ul>
    </aside>
</div>

<?php
admin_footer();
