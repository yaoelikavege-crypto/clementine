<?php
/**
 * Tableau de bord : état du site, statistiques, raccourcis, activité.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$photos   = media_count_by_kind('image');
$videos   = media_count_by_kind('video');
$audios   = media_count_by_kind('audio');
$memories = db_count('memories');
$publishedMemories = db_count('memories', 'is_published = 1');
$wishes   = db_count('wishes');
$letter   = db_fetch('SELECT title, is_published, updated_at FROM love_letter WHERE id = 1 LIMIT 1');

$revealed  = is_revealed();
$parts     = countdown_parts();
$days      = $parts['days'];
$hours     = $parts['hours'];
$minutes   = $parts['minutes'];

$recentMedia = db_fetch_all('SELECT * FROM media ORDER BY created_at DESC, id DESC LIMIT 4');
$recentAudit = db_fetch_all(
    'SELECT a.action, a.details, a.created_at, ad.username
     FROM audit_log a LEFT JOIN admins ad ON ad.id = a.admin_id
     ORDER BY a.id DESC LIMIT 6'
);

$letterReady  = $letter && mb_strlen((string) $letter['title']) > 0;
$tracksTotal  = (int) db_value('SELECT COUNT(*) FROM playlist_tracks WHERE is_active = 1');
$setupLocked  = is_file(installation_lock_path());
$limits       = media_uploader_limits();

$checklist = [
    ['label' => 'Ajouter au moins une photo', 'done' => $photos > 0, 'url' => app_url('admin/media.php?kind=image'), 'cta' => 'Photos'],
    ['label' => 'Créer au moins un souvenir', 'done' => $memories > 0, 'url' => app_url('admin/memories.php'), 'cta' => 'Souvenirs'],
    ['label' => 'Écrire et publier la lettre', 'done' => $letterReady && (int) ($letter['is_published'] ?? 0) === 1, 'url' => app_url('admin/letter.php'), 'cta' => 'Lettre'],
    ['label' => 'Publier au moins un vœu', 'done' => $wishes > 0, 'url' => app_url('admin/wishes.php'), 'cta' => 'Vœux'],
    ['label' => 'Ajouter de la musique', 'done' => $audios > 0, 'url' => app_url('admin/media.php?kind=audio'), 'cta' => 'Musique'],
    ['label' => 'Configurer les playlists', 'done' => $tracksTotal > 0, 'url' => app_url('admin/playlists.php'), 'cta' => 'Playlists'],
    ['label' => 'Définir le mot de passe visiteur', 'done' => visitor_password_exists(), 'url' => app_url('admin/credentials.php'), 'cta' => 'Accès'],
];

$doneCount = count(array_filter($checklist, static fn ($item) => $item['done']));
$progress  = (int) round($doneCount / max(1, count($checklist)) * 100);

admin_header('Tableau de bord', 'dashboard');
?>

<?= admin_page_intro(
    'Bonjour ' . (current_user()['display_name'] ?? '') . ' 👋',
    'Tout ce qui concerne le site des 21 ans de Clémentine, au même endroit.',
    '<a class="btn btn--soft btn--sm" href="' . e(app_url('index.php')) . '" target="_blank" rel="noopener">' . icon('external-link', 15) . ' Voir le site</a>'
) ?>

<?php $defaultWarnings = default_passwords_remaining(); ?>
<?php if ($defaultWarnings) : ?>
    <div class="notice notice--warning notice--wide" role="alert">
        <?= icon('alert-triangle', 18) ?>
        <span>
            <strong>Comptes de première connexion encore actifs :</strong>
            <?= e(implode(', ', array_map(static fn (array $item): string => $item['label'] . ' (' . $item['username'] . ')', $defaultWarnings))) ?>.
            <a href="<?= e(app_url('admin/credentials.php')) ?>">Change ces mots de passe maintenant</a>
            — ils figurent en clair dans <code>includes/config.php</code>.
        </span>
    </div>
<?php endif; ?>

<section class="stats-grid">
    <?= admin_stat_card('Photos', (string) $photos, 'image', '', app_url('admin/media.php?kind=image')) ?>
    <?= admin_stat_card('Vidéos', (string) $videos, 'video', '', app_url('admin/media.php?kind=video')) ?>
    <?= admin_stat_card('Musiques', (string) $audios, 'music', '', app_url('admin/media.php?kind=audio')) ?>
    <?= admin_stat_card('Souvenirs', (string) $memories, 'sparkles', $publishedMemories . ' publié(s)', app_url('admin/memories.php')) ?>
</section>

<div class="admin-grid admin-grid--2">
    <section class="panel panel--state">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">État du site</p>
                <h2><?= $revealed ? 'Expérience ouverte' : 'Avant la révélation' ?></h2>
            </div>
            <span class="status-dot status-dot--<?= $revealed ? 'live' : 'prep' ?>"></span>
        </header>

        <div class="state-countdown">
            <?php if ($revealed) : ?>
                <p class="state-countdown__big">C'est ouvert</p>
                <p class="state-countdown__label">Les visiteurs authentifiés accèdent aux souvenirs depuis le <?= e(reveal_date_text()) ?>.</p>
            <?php else : ?>
                <p class="state-countdown__big">J-<?= (int) $days ?></p>
                <p class="state-countdown__label"><?= (int) $hours ?> h <?= str_pad((string) $minutes, 2, '0', STR_PAD_LEFT) ?> min avant minuit</p>
            <?php endif; ?>
        </div>

        <dl class="state-list">
            <div><dt>Révélation</dt><dd><?= e(fr_date(reveal_at(), true)) ?></dd></div>
            <div><dt>Fuseau horaire</dt><dd><?= e((string) config('app.timezone', 'Africa/Lome')) ?></dd></div>
            <div><dt>Heure serveur</dt><dd><?= e(now()->format('d/m/Y H:i:s')) ?></dd></div>
            <div><dt>Verrou d'installation</dt><dd><?= $setupLocked ? 'Présent' : 'Absent (protection BDD active)' ?></dd></div>
        </dl>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Préparation</p>
                <h2><?= $doneCount ?>/<?= count($checklist) ?> étapes prêtes</h2>
            </div>
            <span class="progress-ring" style="--progress: <?= (int) $progress; ?>%"><?= (int) $progress ?>%</span>
        </header>

        <ul class="checklist">
            <?php foreach ($checklist as $item) : ?>
                <li class="checklist__item<?= $item['done'] ? ' is-done' : '' ?>">
                    <span class="checklist__icon"><?= icon($item['done'] ? 'check' : 'circle', 15) ?></span>
                    <span class="checklist__label"><?= e($item['label']) ?></span>
                    <?php if (!$item['done']) : ?>
                        <a class="checklist__link" href="<?= e($item['url']) ?>"><?= e($item['cta']) ?> <?= icon('arrow-right', 13) ?></a>
                    <?php endif; ?>
                </li>
            <?php endforeach; ?>
        </ul>
    </section>
</div>

<div class="admin-grid admin-grid--2">
    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Raccourcis</p>
                <h2>Actions fréquentes</h2>
            </div>
        </header>

        <div class="quick-grid">
            <a class="quick-card" href="<?= e(app_url('admin/media.php?kind=image')) ?>">
                <?= icon('upload', 20) ?><span>Importer une photo</span>
            </a>
            <a class="quick-card" href="<?= e(app_url('admin/media.php?kind=video')) ?>">
                <?= icon('video', 20) ?><span>Ajouter une vidéo</span>
            </a>
            <a class="quick-card" href="<?= e(app_url('admin/media.php?kind=audio')) ?>">
                <?= icon('music', 20) ?><span>Ajouter une musique</span>
            </a>
            <a class="quick-card" href="<?= e(app_url('admin/memories.php')) ?>">
                <?= icon('sparkles', 20) ?><span>Créer un souvenir</span>
            </a>
            <a class="quick-card" href="<?= e(app_url('admin/letter.php')) ?>">
                <?= icon('letter', 20) ?><span>Modifier la lettre</span>
            </a>
            <a class="quick-card" href="<?= e(app_url('admin/wishes.php')) ?>">
                <?= icon('gift', 20) ?><span>Publier un vœu</span>
            </a>
        </div>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Derniers médias</p>
                <h2>Ajouts récents</h2>
            </div>
            <a class="panel__link" href="<?= e(app_url('admin/media.php?kind=image')) ?>">Bibliothèque <?= icon('arrow-right', 13) ?></a>
        </header>

        <?php if (!$recentMedia) : ?>
            <?= admin_empty_state('image', 'Aucun média', 'La bibliothèque est vide pour le moment.', '<a class="btn btn--primary btn--sm" href="' . e(app_url('admin/media.php?kind=image')) . '">Importer</a>') ?>
        <?php else : ?>
            <div class="media-strip">
                <?php foreach ($recentMedia as $media) : ?>
                    <?php $thumb = media_url($media, true); ?>
                    <a class="media-strip__item" href="<?= e(app_url('admin/media.php?kind=' . $media['kind'] . '&edit=' . (int) $media['id'])) ?>">
                        <?php if ($thumb !== '') : ?>
                            <img src="<?= e($thumb) ?>" alt="" loading="lazy">
                        <?php else : ?>
                            <span class="media-strip__icon"><?= icon($media['kind'] === 'audio' ? 'music' : ($media['kind'] === 'video' ? 'video' : 'image'), 22) ?></span>
                        <?php endif; ?>
                        <span class="media-strip__label"><?= e(excerpt($media['title'], 34)) ?></span>
                    </a>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>
</div>

<div class="admin-grid admin-grid--2">
    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Journal</p>
                <h2>Activité récente</h2>
            </div>
        </header>

        <?php if (!$recentAudit) : ?>
            <p class="panel__empty">Aucune action enregistrée pour l'instant.</p>
        <?php else : ?>
            <ul class="audit-list">
                <?php foreach ($recentAudit as $entry) : ?>
                    <li class="audit-list__item">
                        <span class="audit-list__dot"></span>
                        <div>
                            <p><strong><?= e($entry['action']) ?></strong><?= $entry['details'] ? ' — ' . e($entry['details']) : '' ?></p>
                            <span><?= e(fr_date($entry['created_at'], true)) ?><?= $entry['username'] ? ' · ' . e($entry['username']) : '' ?></span>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Environnement</p>
                <h2>Configuration</h2>
            </div>
        </header>

        <dl class="state-list">
            <div><dt>PHP</dt><dd><?= e(PHP_VERSION) ?></dd></div>
            <div><dt>HTTPS</dt><dd><?= is_https() ? 'Actif' : 'Inactif (à activer)' ?></dd></div>
            <div><dt>Mode debug</dt><dd><?= config('app.debug', false) ? 'Activé (à désactiver)' : 'Désactivé' ?></dd></div>
            <div><dt>Limite PHP par fichier</dt><dd><?= e(format_bytes($limits['upload_max_filesize'])) ?></dd></div>
            <div><dt>Limite PHP par requête</dt><dd><?= e(format_bytes($limits['post_max_size'])) ?></dd></div>
            <div><dt>Dossier médias</dt><dd><?= is_writable(uploads_dir()) ? 'Accessible en écriture' : 'Non inscriptible' ?></dd></div>
        </dl>

        <div class="panel__foot">
            <a class="btn btn--ghost btn--sm" href="<?= e(app_url('admin/settings.php')) ?>"><?= icon('sliders', 15) ?> Paramètres</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(app_url('admin/credentials.php')) ?>"><?= icon('key', 15) ?> Mots de passe</a>
        </div>
    </section>
</div>

<?php
admin_footer();
