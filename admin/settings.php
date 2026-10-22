<?php
/**
 * Paramètres généraux : textes du site et mode maintenance.
 * La date de révélation n'est volontairement pas modifiable ici.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$limits   = media_uploader_limits();
$lockFile = installation_lock_path();

admin_header('Paramètres généraux', 'settings');
?>

<?= admin_page_intro(
    'Paramètres généraux',
    'Personnalise les textes visibles par Clémentine et gère le mode maintenance.',
    ''
) ?>

<form method="post" action="<?= e(app_url('actions/settings.php')) ?>" class="admin-form settings-form">
    <?= csrf_field() ?>

    <div class="admin-grid admin-grid--2">
        <section class="panel">
            <header class="panel__head">
                <div>
                    <p class="panel__eyebrow">Accueil</p>
                    <h2>Identité du site</h2>
                </div>
            </header>

            <label class="field">
                <span class="field__label">Titre du site</span>
                <input class="field__input" type="text" name="site_title" maxlength="120" value="<?= e(setting('site_title', 'Clémentine · 21 ans')) ?>" required>
                <span class="field__hint">Utilisé dans l'onglet du navigateur.</span>
            </label>

            <label class="field">
                <span class="field__label">Sur-titre de l'accueil</span>
                <input class="field__input" type="text" name="hero_kicker" maxlength="120" value="<?= e(setting('hero_kicker', '')) ?>" required>
            </label>

            <label class="field">
                <span class="field__label">Prénom mis en avant</span>
                <input class="field__input" type="text" name="hero_title" maxlength="60" value="<?= e(setting('hero_title', 'Clémentine')) ?>" required>
            </label>

            <label class="field">
                <span class="field__label">Message principal</span>
                <textarea class="field__input field__input--area" name="hero_message" rows="3" maxlength="400"><?= e(setting('hero_message', '')) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Sous-message</span>
                <textarea class="field__input field__input--area" name="hero_submessage" rows="3" maxlength="500"><?= e(setting('hero_submessage', '')) ?></textarea>
            </label>
        </section>

        <section class="panel">
            <header class="panel__head">
                <div>
                    <p class="panel__eyebrow">Avant minuit</p>
                    <h2>Période d'attente</h2>
                </div>
            </header>

            <label class="field">
                <span class="field__label">Titre du compte à rebours</span>
                <input class="field__input" type="text" name="countdown_title" maxlength="120" value="<?= e(setting('countdown_title', '')) ?>" required>
            </label>

            <label class="field">
                <span class="field__label">Note sous le compte à rebours</span>
                <textarea class="field__input field__input--area" name="countdown_note" rows="3" maxlength="400"><?= e(setting('countdown_note', '')) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Indication sur la page de connexion visiteur</span>
                <textarea class="field__input field__input--area" name="visitor_hint" rows="2" maxlength="300"><?= e(setting('visitor_hint', '')) ?></textarea>
            </label>

            <div class="notice notice--info notice--sm">
                <?= icon('info', 15) ?>
                <span>Les messages dévoilés au fil des jours se gèrent dans <a href="<?= e(app_url('admin/countdown.php')) ?>">Messages & indices</a>.</span>
            </div>
        </section>
    </div>

    <div class="admin-grid admin-grid--2">
        <section class="panel">
            <header class="panel__head">
                <div>
                    <p class="panel__eyebrow">Le jour J</p>
                    <h2>Révélation & galerie</h2>
                </div>
            </header>

            <label class="field">
                <span class="field__label">Titre de la révélation</span>
                <input class="field__input" type="text" name="reveal_title" maxlength="120" value="<?= e(setting('reveal_title', '')) ?>">
            </label>

            <label class="field">
                <span class="field__label">Message de révélation</span>
                <textarea class="field__input field__input--area" name="reveal_message" rows="3" maxlength="600"><?= e(setting('reveal_message', '')) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Introduction de la galerie</span>
                <textarea class="field__input field__input--area" name="gallery_intro" rows="2" maxlength="400"><?= e(setting('gallery_intro', '')) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Phrase du bouton de fin de galerie</span>
                <textarea class="field__input field__input--area" name="gallery_end_cta" rows="2" maxlength="250"><?= e(setting('gallery_end_cta', '')) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Catégories suggérées</span>
                <input class="field__input" type="text" name="gallery_categories" maxlength="400" value="<?= e(setting('gallery_categories', '')) ?>">
                <span class="field__hint">Séparées par des virgules. Elles servent de suggestions dans le formulaire des souvenirs.</span>
            </label>
        </section>

        <section class="panel">
            <header class="panel__head">
                <div>
                    <p class="panel__eyebrow">Émotion</p>
                    <h2>Lettre & finale</h2>
                </div>
            </header>

            <label class="field">
                <span class="field__label">Introduction de la lettre</span>
                <textarea class="field__input field__input--area" name="letter_intro" rows="2" maxlength="400"><?= e(setting('letter_intro', '')) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Invitation de la bougie</span>
                <textarea class="field__input field__input--area" name="finale_invite" rows="2" maxlength="300"><?= e(setting('finale_invite', '')) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Nom affiché dans le message final</span>
                <input class="field__input" type="text" name="finale_message" maxlength="120" value="<?= e(setting('finale_message', 'Clémentine')) ?>" required>
                <span class="field__hint">« Joyeux anniversaire » est ajouté automatiquement devant ce nom, après avoir soufflé la bougie.</span>
            </label>

            <label class="field">
                <span class="field__label">Petite phrase sous le message final</span>
                <textarea class="field__input field__input--area" name="finale_note" rows="2" maxlength="300"><?= e(setting('finale_note', '')) ?></textarea>
            </label>

            <label class="field">
                <span class="field__label">Signature du pied de page</span>
                <input class="field__input" type="text" name="footer_signature" maxlength="160" value="<?= e(setting('footer_signature', '')) ?>">
            </label>
        </section>
    </div>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Exceptionnel</p>
                <h2>Mode maintenance</h2>
            </div>
            <?= admin_badge(setting('maintenance_mode', '0') === '1' ? 'Actif' : 'Inactif', setting('maintenance_mode', '0') === '1' ? 'warning' : 'success') ?>
        </header>

        <label class="check">
            <input type="checkbox" name="maintenance_mode" value="1"<?= setting('maintenance_mode', '0') === '1' ? ' checked' : '' ?>>
            <span>Fermer temporairement le site aux visiteurs (les administrateurs et la page de connexion restent accessibles)</span>
        </label>

        <label class="field">
            <span class="field__label">Message affiché pendant la maintenance</span>
            <textarea class="field__input field__input--area" name="maintenance_message" rows="2" maxlength="500"><?= e(setting('maintenance_message', '')) ?></textarea>
        </label>
    </section>

    <div class="form-actions form-actions--sticky">
        <button class="btn btn--primary btn--lg" type="submit"><?= icon('check', 18) ?> Enregistrer tous les paramètres</button>
        <span class="form-actions__hint">Les modifications sont immédiatement visibles sur le site.</span>
    </div>
</form>

<div class="admin-grid admin-grid--2">
    <section class="panel panel--aside">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Vérifié & fixé</p>
                <h2>Réglages non modifiables</h2>
            </div>
        </header>

        <dl class="state-list">
            <div><dt>Date de révélation</dt><dd><?= e(fr_date(reveal_at(), true)) ?></dd></div>
            <div><dt>Fuseau horaire</dt><dd><?= e((string) config('app.timezone', 'Africa/Lome')) ?></dd></div>
            <div><dt>Pistes par playlist</dt><dd><?= (int) playlist_max_tracks() ?> maximum</dd></div>
            <div><dt>Version de PHP</dt><dd><?= e(PHP_VERSION) ?></dd></div>
            <div><dt>HTTPS</dt><dd><?= is_https() ? 'Actif' : 'Inactif' ?></dd></div>
            <div><dt>Mode debug</dt><dd><?= config('app.debug', false) ? 'Activé' : 'Désactivé' ?></dd></div>
            <div><dt>Verrou d'installation</dt><dd><?= is_file($lockFile) ? 'Présent' : 'Absent' ?></dd></div>
        </dl>

        <p class="panel__empty">
            La date de révélation est définie dans <code>includes/config.php</code> (<code>reveal_at</code>) : elle ne peut pas être modifiée depuis le tableau de bord, par sécurité.
        </p>
    </section>

    <section class="panel panel--aside">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Hébergement</p>
                <h2>Limites des médias</h2>
            </div>
        </header>

        <dl class="state-list">
            <div><dt>Photo</dt><dd><?= (int) config('media.max_image_mb', 6) ?> Mo (config)</dd></div>
            <div><dt>Vidéo locale</dt><dd><?= (int) config('media.max_video_mb', 8) ?> Mo (config)</dd></div>
            <div><dt>Musique</dt><dd><?= (int) config('media.max_audio_mb', 8) ?> Mo (config)</dd></div>
            <div><dt>PHP par fichier</dt><dd><?= e(format_bytes($limits['upload_max_filesize'])) ?></dd></div>
            <div><dt>PHP par requête</dt><dd><?= e(format_bytes($limits['post_max_size'])) ?></dd></div>
            <div><dt>Dossier médias</dt><dd><?= is_writable(uploads_dir()) ? 'Inscriptible' : 'Non inscriptible' ?></dd></div>
        </dl>

        <p class="panel__empty">
            Les limites applicatives se règlent dans <code>includes/config.php</code>. Les limites PHP dépendent de l'hébergeur ; utilisez des vidéos externes si elles sont trop basses.
        </p>
    </section>
</div>

<?php
admin_footer();
