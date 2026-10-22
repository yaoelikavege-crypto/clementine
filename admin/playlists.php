<?php
/**
 * Gestion des quatre playlists de l'expérience.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$playlists = db_fetch_all('SELECT * FROM playlists ORDER BY sort_order ASC, id ASC');
$maxTracks = playlist_max_tracks();

$audioMedia = db_fetch_all('SELECT * FROM media WHERE kind = \'audio\' ORDER BY title ASC');

$playlistMeta = [
    'countdown' => ['icon' => 'clock', 'role' => 'Diffusée sur l\'accueil pendant l\'attente. Les pistes doivent être accessibles avant la révélation.'],
    'memories'  => ['icon' => 'image', 'role' => 'Accompagne la galerie de souvenirs.'],
    'letter'    => ['icon' => 'letter', 'role' => 'Accompagne la lecture de la lettre et des vœux.'],
    'finale'    => ['icon' => 'sparkles', 'role' => 'Se lance avec la bougie, pour le grand moment.'],
];

$tracksByPlaylist = [];
foreach ($playlists as $playlist) {
    $tracksByPlaylist[(int) $playlist['id']] = db_fetch_all(
        'SELECT pt.*, m.title, m.artist, m.is_protected, m.source
         FROM playlist_tracks pt
         INNER JOIN media m ON m.id = pt.media_id
         WHERE pt.playlist_id = ?
         ORDER BY pt.position ASC, pt.id ASC',
        [(int) $playlist['id']]
    );
}

admin_header('Playlists', 'playlists');
?>

<?= admin_page_intro(
    'Playlists',
    'Quatre ambiances pour quatre moments. Jusqu\'à ' . $maxTracks . ' pistes actives par playlist ; la bibliothèque musicale, elle, n\'est pas limitée.',
    '<a class="btn btn--soft btn--sm" href="' . e(app_url('admin/media.php?kind=audio')) . '">' . icon('music', 15) . ' Bibliothèque musicale</a>'
) ?>

<?php if (!$audioMedia) : ?>
    <div class="notice notice--warning">
        <?= icon('alert-triangle', 17) ?>
        <span>Aucune musique dans la bibliothèque. <a href="<?= e(app_url('admin/media.php?kind=audio')) ?>">Importe une première piste</a> pour pouvoir composer les playlists.</span>
    </div>
<?php endif; ?>

<div class="playlist-grid">
    <?php foreach ($playlists as $playlist) : ?>
        <?php
        $pid        = (int) $playlist['id'];
        $tracks     = $tracksByPlaylist[$pid] ?? [];
        $activeCount = count(array_filter($tracks, static fn ($t) => (int) $t['is_active'] === 1));
        $meta       = $playlistMeta[$playlist['slug']] ?? ['icon' => 'playlist', 'role' => ''];
        $usedIds    = array_map(static fn ($t) => (int) $t['media_id'], $tracks);
        $available  = array_values(array_filter($audioMedia, static fn ($m) => !in_array((int) $m['id'], $usedIds, true)));
        $full       = $activeCount >= $maxTracks;
        $hasProtected = false;
        foreach ($tracks as $t) {
            if ((int) $t['is_active'] === 1 && (int) $t['is_protected'] === 1) {
                $hasProtected = true;
            }
        }
        ?>
        <section class="panel playlist-card">
            <header class="panel__head">
                <div class="playlist-card__title">
                    <span class="playlist-card__icon"><?= icon($meta['icon'], 20) ?></span>
                    <div>
                        <h2><?= e($playlist['name']) ?></h2>
                        <p class="panel__eyebrow"><code><?= e($playlist['slug']) ?></code></p>
                    </div>
                </div>
                <span class="playlist-card__count<?= $full ? ' is-full' : '' ?>"><?= (int) $activeCount ?>/<?= (int) $maxTracks ?></span>
            </header>

            <p class="playlist-card__role"><?= e($meta['role']) ?></p>

            <?php if ($playlist['slug'] === 'countdown' && $hasProtected) : ?>
                <div class="notice notice--warning notice--sm">
                    <?= icon('lock', 15) ?>
                    <span>Une piste protégée ne sera pas diffusée avant la révélation. Rends-la accessible depuis <a href="<?= e(app_url('admin/media.php?kind=audio')) ?>">la bibliothèque</a>.</span>
                </div>
            <?php endif; ?>

            <?php if (!$tracks) : ?>
                <p class="panel__empty">Aucune piste dans cette playlist.</p>
            <?php else : ?>
                <ol class="track-list">
                    <?php foreach ($tracks as $index => $track) : ?>
                        <li class="track-item<?= (int) $track['is_active'] === 1 ? '' : ' is-off' ?>">
                            <span class="track-item__pos"><?= (int) ($index + 1) ?></span>
                            <div class="track-item__body">
                                <strong><?= e($track['title']) ?></strong>
                                <span>
                                    <?= $track['artist'] ? e($track['artist']) : 'Artiste non renseigné' ?>
                                    <?php if ((int) $track['is_protected'] === 1) : ?>
                                        · <?= admin_badge('Protégée', 'violet') ?>
                                    <?php endif; ?>
                                    <?php if ((int) $track['is_active'] !== 1) : ?>
                                        · <?= admin_badge('Inactive', 'neutral') ?>
                                    <?php endif; ?>
                                </span>
                            </div>

                            <div class="track-item__actions">
                                <form method="post" action="<?= e(app_url('actions/playlists.php')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move">
                                    <input type="hidden" name="track_id" value="<?= (int) $track['id'] ?>">
                                    <input type="hidden" name="direction" value="up">
                                    <button class="icon-btn icon-btn--ghost" type="submit" title="Monter" aria-label="Monter la piste"<?= $index === 0 ? ' disabled' : '' ?>><?= icon('arrow-up', 14) ?></button>
                                </form>
                                <form method="post" action="<?= e(app_url('actions/playlists.php')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="move">
                                    <input type="hidden" name="track_id" value="<?= (int) $track['id'] ?>">
                                    <input type="hidden" name="direction" value="down">
                                    <button class="icon-btn icon-btn--ghost" type="submit" title="Descendre" aria-label="Descendre la piste"<?= $index === count($tracks) - 1 ? ' disabled' : '' ?>><?= icon('arrow-down', 14) ?></button>
                                </form>
                                <form method="post" action="<?= e(app_url('actions/playlists.php')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="track_id" value="<?= (int) $track['id'] ?>">
                                    <button class="icon-btn icon-btn--ghost" type="submit" title="<?= (int) $track['is_active'] === 1 ? 'Désactiver' : 'Activer' ?>" aria-label="<?= (int) $track['is_active'] === 1 ? 'Désactiver' : 'Activer' ?>">
                                        <?= icon((int) $track['is_active'] === 1 ? 'eye-off' : 'eye', 14) ?>
                                    </button>
                                </form>
                                <form method="post" action="<?= e(app_url('actions/playlists.php')) ?>" class="inline-form" data-confirm="Retirer « <?= e($track['title']) ?> » de la playlist ? Le fichier restera dans la bibliothèque.">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="track_id" value="<?= (int) $track['id'] ?>">
                                    <button class="icon-btn icon-btn--danger" type="submit" title="Retirer" aria-label="Retirer"><?= icon('x', 14) ?></button>
                                </form>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ol>
            <?php endif; ?>

            <?php if ($available && !$full) : ?>
                <form method="post" action="<?= e(app_url('actions/playlists.php')) ?>" class="playlist-add">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="playlist_id" value="<?= $pid ?>">
                    <label class="field field--inline">
                        <span class="visually-hidden">Ajouter une piste</span>
                        <select class="field__input" name="media_id" required>
                            <option value="">Choisir une piste…</option>
                            <?php foreach ($available as $media) : ?>
                                <option value="<?= (int) $media['id'] ?>">
                                    <?= e($media['title']) ?><?= $media['artist'] ? ' — ' . e($media['artist']) : '' ?><?= (int) $media['is_protected'] === 1 ? ' (protégée)' : '' ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                    <button class="btn btn--primary btn--sm" type="submit"><?= icon('plus', 15) ?> Ajouter</button>
                </form>
            <?php else : ?>
                <p class="playlist-add__note">
                    <?php if ($full) : ?>
                        Playlist complète (<?= (int) $maxTracks ?> pistes actives). Retire ou désactive une piste pour en ajouter une autre.
                    <?php elseif (!$audioMedia) : ?>
                        Importe une musique pour composer cette playlist.
                    <?php else : ?>
                        Toutes les pistes disponibles sont déjà dans cette playlist.
                    <?php endif; ?>
                </p>
            <?php endif; ?>
        </section>
    <?php endforeach; ?>
</div>

<?php
admin_footer();
