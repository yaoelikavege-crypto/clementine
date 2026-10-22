<?php
/**
 * Bibliothèque multimédia : photos, vidéos et musiques.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$kind = input('kind');
$kind = in_array($kind, ['image', 'video', 'audio'], true) ? $kind : 'image';

$labels = [
    'image' => ['Photos', 'image', 'Photo'],
    'video' => ['Vidéos', 'video', 'Vidéo'],
    'audio' => ['Bibliothèque musicale', 'music', 'Musique'],
];

$configs = media_type_config();
$spec    = $configs[$kind];
$limits  = media_uploader_limits();

$editId = get_int('edit');
$editing = null;

if ($editId > 0) {
    $editing = db_fetch(
        'SELECT m.*, (SELECT COUNT(*) FROM memories mem WHERE mem.media_id = m.id) AS memory_uses
         FROM media m WHERE m.id = ? LIMIT 1',
        [$editId]
    );
    if (!$editing || $editing['kind'] !== $kind) {
        flash('error', 'Ce média est introuvable dans cette catégorie.');
        redirect(app_url('admin/media.php?kind=' . $kind));
    }
}

$page  = max(1, get_int('page', 1));
$total = (int) db_value('SELECT COUNT(*) FROM media WHERE kind = ?', [$kind]);
$pager = pagination($total, 20, $page);

$items = db_fetch_all(
    'SELECT m.*, (SELECT COUNT(*) FROM memories mem WHERE mem.media_id = m.id) AS memory_uses
     FROM media m
     WHERE m.kind = ?
     ORDER BY m.created_at DESC, m.id DESC
     LIMIT 20 OFFSET ' . (int) $pager['offset'],
    [$kind]
);

$maxConfigMb   = (int) $spec['max_mb'];
$phpMaxFileMb  = $limits['upload_max_filesize'] > 0 ? (int) floor($limits['upload_max_filesize'] / 1048576) : 0;
$phpPostMaxMb  = $limits['post_max_size'] > 0 ? (int) floor($limits['post_max_size'] / 1048576) : 0;

admin_header($labels[$kind][0], ['image' => 'photos', 'video' => 'videos', 'audio' => 'musiques'][$kind]);
?>

<?= admin_page_intro(
    $labels[$kind][0],
    'Importe, décris et protège tes médias. Les fichiers restent privés : ils sont servis uniquement aux personnes autorisées.',
    '<a class="btn btn--soft btn--sm" href="' . e(app_url('admin/memories.php')) . '">' . icon('sparkles', 15) . ' Relier aux souvenirs</a>'
) ?>

<nav class="media-tabs" aria-label="Catégories de médias">
    <a class="media-tab<?= $kind === 'image' ? ' is-active' : '' ?>" href="<?= e(app_url('admin/media.php?kind=image')) ?>"><?= icon('image', 17) ?> Photos <span><?= media_count_by_kind('image') ?></span></a>
    <a class="media-tab<?= $kind === 'video' ? ' is-active' : '' ?>" href="<?= e(app_url('admin/media.php?kind=video')) ?>"><?= icon('video', 17) ?> Vidéos <span><?= media_count_by_kind('video') ?></span></a>
    <a class="media-tab<?= $kind === 'audio' ? ' is-active' : '' ?>" href="<?= e(app_url('admin/media.php?kind=audio')) ?>"><?= icon('music', 17) ?> Musiques <span><?= media_count_by_kind('audio') ?></span></a>
</nav>

<?php if ($maxConfigMb > $phpMaxFileMb && $phpMaxFileMb > 0) : ?>
    <div class="notice notice--warning">
        <?= icon('alert-triangle', 17) ?>
        <span>La limite configurée (<?= (int) $maxConfigMb ?> Mo) dépasse la limite PHP du serveur (<?= (int) $phpMaxFileMb ?> Mo par fichier). Les fichiers seront refusés au-delà de <?= (int) $phpMaxFileMb ?> Mo : réduis <code>media.max_<?= e($kind) ?>_mb</code> dans <code>includes/config.php</code> ou optimise tes fichiers.</span>
    </div>
<?php endif; ?>

<div class="admin-grid admin-grid--sidebar">
    <section class="panel" id="edit">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow"><?= $editing ? 'Édition' : 'Import' ?></p>
                <h2><?= $editing ? 'Modifier « ' . e(excerpt($editing['title'], 36)) . ' »' : 'Ajouter ' . mb_strtolower($labels[$kind][2]) ?></h2>
            </div>
            <?php if ($editing) : ?>
                <a class="panel__link" href="<?= e(app_url('admin/media.php?kind=' . $kind)) ?>">Annuler</a>
            <?php endif; ?>
        </header>

        <?php if ($editing) : ?>
            <form method="post" action="<?= e(app_url('actions/media_update.php')) ?>" class="admin-form" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">

                <label class="field">
                    <span class="field__label">Titre *</span>
                    <input class="field__input" type="text" name="title" required minlength="2" maxlength="150" value="<?= e($editing['title']) ?>">
                </label>

                <?php if ($editing['kind'] === 'audio') : ?>
                    <label class="field">
                        <span class="field__label">Artiste</span>
                        <input class="field__input" type="text" name="artist" maxlength="120" value="<?= e($editing['artist'] ?? '') ?>">
                    </label>
                <?php endif; ?>

                <label class="field">
                    <span class="field__label">Description</span>
                    <textarea class="field__input field__input--area" name="description" rows="3" maxlength="2000"><?= e($editing['description'] ?? '') ?></textarea>
                </label>

                <?php if ($editing['source'] === 'external') : ?>
                    <label class="field">
                        <span class="field__label">URL de la vidéo externe</span>
                        <input class="field__input" type="url" name="external_url" required maxlength="500" value="<?= e($editing['external_url'] ?? '') ?>">
                        <span class="field__hint">YouTube, Vimeo ou lien HTTPS direct. La lecture intégrée dépend du fournisseur.</span>
                    </label>
                <?php endif; ?>

                <?php if ($editing['kind'] === 'video') : ?>
                    <label class="field">
                        <span class="field__label">Remplacer la miniature</span>
                        <input class="field__input" type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp">
                        <span class="field__hint">Facultatif. JPEG, PNG ou WebP, 6 Mo maximum.</span>
                    </label>
                <?php endif; ?>

                <input type="hidden" name="is_protected" value="0">
                <label class="check">
                    <input type="checkbox" name="is_protected" value="1"<?= (int) $editing['is_protected'] === 1 ? ' checked' : '' ?>>
                    <span>Protéger jusqu'à la révélation du <?= e(reveal_date_text()) ?></span>
                </label>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit"><?= icon('check', 17) ?> Enregistrer</button>
                    <a class="btn btn--ghost" href="<?= e(app_url('admin/media.php?kind=' . $kind)) ?>">Annuler</a>
                </div>
            </form>

            <form method="post" action="<?= e(app_url('actions/media_update.php')) ?>" class="danger-zone" data-confirm="Supprimer définitivement « <?= e($editing['title']) ?> » ? Cette action est irréversible.">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
                <?= delete_confirm_field() ?>
                <div>
                    <strong>Supprimer ce média</strong>
                    <span><?= (int) ($editing['memory_uses'] ?? 0) > 0 ? 'Ce média est utilisé par un souvenir : détache-le d\'abord.' : 'Le fichier sera aussi effacé du serveur.' ?></span>
                </div>
                <button class="btn btn--danger btn--sm" type="submit"><?= icon('trash', 15) ?> Supprimer</button>
            </form>

        <?php else : ?>
            <form method="post" action="<?= e(app_url('actions/media_upload.php')) ?>" enctype="multipart/form-data" class="admin-form">
                <?= csrf_field() ?>
                <input type="hidden" name="kind" value="<?= e($kind) ?>">

                <?php if ($kind === 'video') : ?>
                    <fieldset class="field field--wide">
                        <legend class="field__label">Origine de la vidéo</legend>
                        <label class="radio">
                            <input type="radio" name="video_source" value="local" checked data-video-source>
                            <span>Fichier hébergé sur le site (MP4 ou WebM, <?= (int) $spec['max_mb'] ?> Mo max.)</span>
                        </label>
                        <label class="radio">
                            <input type="radio" name="video_source" value="external" data-video-source>
                            <span>Lien externe (YouTube, Vimeo…)</span>
                        </label>
                    </fieldset>

                    <label class="field" data-video-local>
                        <span class="field__label">Fichier vidéo</span>
                        <input class="field__input" type="file" name="file" accept="video/mp4,video/webm">
                        <span class="field__hint">Pour les fichiers volumineux, préfère un lien externe : l'hébergement supprime tout fichier dépassant 10 Mo.</span>
                    </label>

                    <label class="field" data-video-external hidden>
                        <span class="field__label">URL de la vidéo</span>
                        <input class="field__input" type="url" name="external_url" maxlength="500" placeholder="https://www.youtube.com/watch?v=…">
                        <span class="field__hint">Lien HTTPS uniquement. Une miniature et un lien direct sont prévus si l'intégration est interdite.</span>
                    </label>

                    <label class="field">
                        <span class="field__label">Miniature (facultative)</span>
                        <input class="field__input" type="file" name="thumbnail" accept="image/jpeg,image/png,image/webp">
                    </label>

                <?php else : ?>
                    <label class="field">
                        <span class="field__label">Fichier <?= $kind === 'image' ? 'photo' : 'audio' ?> *</span>
                        <input class="field__input" type="file" name="file" required
                               accept="<?= $kind === 'image' ? 'image/jpeg,image/png,image/webp' : 'audio/mpeg,audio/mp4,audio/ogg,audio/wav,audio/x-wav' ?>">
                        <span class="field__hint">
                            <?php if ($kind === 'image') : ?>
                                JPEG, PNG ou WebP — <?= (int) $spec['max_mb'] ?> Mo max. L'image est ré-encodée (métadonnées supprimées) et réduite à 2 200 px maximum.
                            <?php else : ?>
                                MP3, M4A, OGG ou WAV — <?= (int) $spec['max_mb'] ?> Mo max.
                            <?php endif; ?>
                        </span>
                    </label>
                <?php endif; ?>

                <label class="field">
                    <span class="field__label">Titre *</span>
                    <input class="field__input" type="text" name="title" required minlength="2" maxlength="150" placeholder="<?= $kind === 'image' ? 'Notre premier été' : ($kind === 'video' ? 'Un fou rire en vidéo' : 'Nom de la musique') ?>">
                </label>

                <?php if ($kind === 'audio') : ?>
                    <label class="field">
                        <span class="field__label">Artiste</span>
                        <input class="field__input" type="text" name="artist" maxlength="120">
                    </label>
                <?php endif; ?>

                <label class="field">
                    <span class="field__label">Description</span>
                    <textarea class="field__input field__input--area" name="description" rows="2" maxlength="2000"></textarea>
                </label>

                <input type="hidden" name="is_protected" value="0">
                <label class="check">
                    <input type="checkbox" name="is_protected" value="1"<?= $kind !== 'audio' ? ' checked' : '' ?>>
                    <span>Protéger jusqu'à la révélation du <?= e(reveal_date_text()) ?></span>
                </label>
                <?php if ($kind === 'audio') : ?>
                    <p class="field__hint">Décoche pour une musique du compte à rebours, audible avant minuit.</p>
                <?php endif; ?>

                <p class="field__hint">
                    Limite serveur : <?= e(format_bytes($limits['upload_max_filesize'])) ?> par fichier,
                    <?= e(format_bytes($limits['post_max_size'])) ?> par envoi.
                </p>

                <div class="form-actions">
                    <button class="btn btn--primary" type="submit"><?= icon('upload', 17) ?> Téléverser</button>
                </div>
            </form>
        <?php endif; ?>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Bibliothèque</p>
                <h2><?= (int) $total ?> <?= $total > 1 ? 'éléments' : 'élément' ?></h2>
            </div>
            <span class="panel__link"><?= e(format_bytes((int) db_value('SELECT COALESCE(SUM(file_size),0) FROM media WHERE kind = ?', [$kind]))) ?> au total</span>
        </header>

        <?php if (!$items) : ?>
            <?= admin_empty_state($labels[$kind][1], 'Bibliothèque vide', 'Importe ton premier fichier avec le formulaire ci-contre. Chaque média pourra ensuite être associé à un souvenir.', '') ?>
        <?php elseif ($kind === 'image') : ?>
            <div class="media-grid">
                <?php foreach ($items as $media) : ?>
                    <article class="media-tile<?= (int) $media['is_protected'] === 1 ? ' is-protected' : '' ?>">
                        <a class="media-tile__preview" href="<?= e(app_url('admin/media.php?kind=image&edit=' . (int) $media['id'])) ?>#edit" aria-label="Modifier <?= e($media['title']) ?>">
                            <img src="<?= e(media_url($media, true)) ?>" alt="<?= e($media['title']) ?>" loading="lazy">
                            <?php if ((int) $media['is_protected'] === 1) : ?>
                                <span class="media-tile__lock"><?= icon('lock', 13) ?></span>
                            <?php endif; ?>
                        </a>
                        <div class="media-tile__body">
                            <strong><?= e(excerpt($media['title'], 42)) ?></strong>
                            <span><?= (int) $media['width'] ?>×<?= (int) $media['height'] ?> · <?= e(format_bytes((int) $media['file_size'])) ?></span>
                            <div class="media-tile__actions">
                                <a class="icon-btn icon-btn--ghost" href="<?= e(app_url('admin/media.php?kind=image&edit=' . (int) $media['id'])) ?>#edit" title="Modifier"><?= icon('edit', 15) ?></a>
                                <form method="post" action="<?= e(app_url('actions/media_update.php')) ?>" class="inline-form">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="toggle">
                                    <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
                                    <button class="icon-btn icon-btn--ghost" type="submit" title="<?= (int) $media['is_protected'] === 1 ? 'Rendre accessible' : 'Protéger' ?>"><?= icon((int) $media['is_protected'] === 1 ? 'unlock' : 'lock', 15) ?></button>
                                </form>
                                <form method="post" action="<?= e(app_url('actions/media_update.php')) ?>" class="inline-form" data-confirm="Supprimer la photo « <?= e($media['title']) ?> » ?">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
                                    <?= delete_confirm_field() ?>
                                    <button class="icon-btn icon-btn--danger" type="submit" title="Supprimer"><?= icon('trash', 15) ?></button>
                                </form>
                            </div>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>

        <?php else : ?>
            <ul class="media-rows">
                <?php foreach ($items as $media) : ?>
                    <?php $thumb = media_url($media, true); ?>
                    <li class="media-row">
                        <span class="media-row__thumb">
                            <?php if ($thumb !== '') : ?>
                                <img src="<?= e($thumb) ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <?= icon($kind === 'audio' ? 'music' : 'video', 20) ?>
                            <?php endif; ?>
                        </span>

                        <div class="media-row__body">
                            <div class="media-row__title">
                                <strong><?= e($media['title']) ?></strong>
                                <?php if ((int) $media['is_protected'] === 1) : ?>
                                    <?= admin_badge('Protégé', 'violet') ?>
                                <?php else : ?>
                                    <?= admin_badge('Accessible', 'success') ?>
                                <?php endif; ?>
                                <?php if ($media['source'] === 'external') : ?>
                                    <?= admin_badge('Externe', 'neutral') ?>
                                <?php endif; ?>
                            </div>
                            <span class="media-row__meta">
                                <?= $media['artist'] ? e($media['artist']) . ' · ' : '' ?>
                                <?= $media['file_size'] ? e(format_bytes((int) $media['file_size'])) . ' · ' : '' ?>
                                <?= (int) $media['memory_uses'] ?> souvenir(s) lié(s)
                            </span>
                        </div>

                        <div class="media-row__actions">
                            <a class="icon-btn icon-btn--ghost" href="<?= e(app_url('admin/media.php?kind=' . $kind . '&edit=' . (int) $media['id'])) ?>#edit" title="Modifier"><?= icon('edit', 16) ?></a>
                            <form method="post" action="<?= e(app_url('actions/media_update.php')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
                                <button class="icon-btn icon-btn--ghost" type="submit" title="<?= (int) $media['is_protected'] === 1 ? 'Rendre accessible' : 'Protéger' ?>"><?= icon((int) $media['is_protected'] === 1 ? 'unlock' : 'lock', 16) ?></button>
                            </form>
                            <form method="post" action="<?= e(app_url('actions/media_update.php')) ?>" class="inline-form" data-confirm="Supprimer « <?= e($media['title']) ?> » ?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $media['id'] ?>">
                                <?= delete_confirm_field() ?>
                                <button class="icon-btn icon-btn--danger" type="submit" title="Supprimer"><?= icon('trash', 16) ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <?php if ($pager['pages'] > 1) : ?>
            <nav class="pagination" aria-label="Pages de la bibliothèque">
                <?php if ($pager['has_prev']) : ?>
                    <a class="pagination__link" href="<?= e(url_with(['page' => $pager['page'] - 1])) ?>" rel="prev"><?= icon('chevron-left', 16) ?> Précédent</a>
                <?php else : ?>
                    <span class="pagination__link is-disabled"><?= icon('chevron-left', 16) ?> Précédent</span>
                <?php endif; ?>
                <span class="pagination__pages">page <?= (int) $pager['page'] ?> sur <?= (int) $pager['pages'] ?></span>
                <?php if ($pager['has_next']) : ?>
                    <a class="pagination__link" href="<?= e(url_with(['page' => $pager['page'] + 1])) ?>" rel="next">Suivant <?= icon('chevron-right', 16) ?></a>
                <?php else : ?>
                    <span class="pagination__link is-disabled">Suivant <?= icon('chevron-right', 16) ?></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    </section>
</div>

<?php
admin_footer();
