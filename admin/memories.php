<?php
/**
 * Administration des souvenirs (cartes de la galerie).
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$editId   = get_int('edit');
$editing  = null;

if ($editId > 0) {
    $editing = db_fetch('SELECT * FROM memories WHERE id = ? LIMIT 1', [$editId]);
    if (!$editing) {
        flash('error', 'Ce souvenir est introuvable.');
        redirect(app_url('admin/memories.php'));
    }
}

$mediaOptions = db_fetch_all("SELECT id, kind, title, is_protected FROM media WHERE kind IN ('image','video') ORDER BY created_at DESC, id DESC");
$memories     = db_fetch_all(
    'SELECT m.*, md.kind AS media_kind, md.source AS media_source, md.thumbnail_path, md.file_path, md.external_url, md.id AS media_ref_id
     FROM memories m
     LEFT JOIN media md ON md.id = m.media_id
     ORDER BY m.sort_order ASC, m.id ASC'
);

$suggestedCategories = array_filter(array_map('trim', explode(',', (string) setting('gallery_categories', ''))));
foreach ($memories as $memory) {
    if (!empty($memory['category'])) {
        $suggestedCategories[] = $memory['category'];
    }
}
$suggestedCategories = array_values(array_unique($suggestedCategories));

$nextSort = 1;
foreach ($memories as $memory) {
    $nextSort = max($nextSort, (int) $memory['sort_order'] + 1);
}

$formValues = [
    'title'       => (string) ($editing['title'] ?? ''),
    'description' => (string) ($editing['description'] ?? ''),
    'category'    => (string) ($editing['category'] ?? ''),
    'memory_date' => (string) ($editing['memory_date'] ?? ''),
    'media_id'    => (int) ($editing['media_id'] ?? 0),
    'sort_order'  => (int) ($editing['sort_order'] ?? $nextSort),
    'is_published'=> $editing ? (int) $editing['is_published'] === 1 : true,
];

admin_header($editing ? 'Modifier un souvenir' : 'Souvenirs & galerie', 'memories');
?>

<?= admin_page_intro(
    'Souvenirs & galerie',
    'Chaque carte affiche un média (photo ou vidéo), un titre et une description. L\'ordre le plus petit apparaît en premier.',
    '<a class="btn btn--soft btn--sm" href="' . e(app_url('pages/memories.php')) . '" target="_blank" rel="noopener">' . icon('eye', 15) . ' Aperçu de la galerie</a>'
) ?>

<div class="admin-grid admin-grid--sidebar">
    <section class="panel" id="form">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow"><?= $editing ? 'Édition' : 'Nouveau' ?></p>
                <h2><?= $editing ? 'Modifier « ' . e(excerpt($formValues['title'], 40)) . ' »' : 'Créer un souvenir' ?></h2>
            </div>
            <?php if ($editing) : ?>
                <a class="panel__link" href="<?= e(app_url('admin/memories.php')) ?>">Annuler</a>
            <?php endif; ?>
        </header>

        <?php if (!$mediaOptions) : ?>
            <div class="notice notice--warning">
                <?= icon('alert-triangle', 17) ?>
                <span>Aucun média disponible. <a href="<?= e(app_url('admin/media.php?kind=image')) ?>">Importe d\'abord une photo</a> ou une vidéo ; tu pourras ensuite la relier à ce souvenir.</span>
            </div>
        <?php endif; ?>

        <form method="post" action="<?= e(app_url('actions/memories.php')) ?>" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing) : ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

            <div class="form-grid">
                <label class="field">
                    <span class="field__label">Titre du souvenir *</span>
                    <input class="field__input" type="text" name="title" required minlength="2" maxlength="150" value="<?= e($formValues['title']) ?>">
                </label>

                <label class="field">
                    <span class="field__label">Catégorie</span>
                    <input class="field__input" type="text" name="category" maxlength="80" list="category-list" value="<?= e($formValues['category']) ?>">
                    <datalist id="category-list">
                        <?php foreach ($suggestedCategories as $category) : ?>
                            <option value="<?= e($category) ?>"></option>
                        <?php endforeach; ?>
                    </datalist>
                </label>

                <label class="field field--wide">
                    <span class="field__label">Média associé</span>
                    <select class="field__input" name="media_id">
                        <option value="0">— Aucun média (carte texte) —</option>
                        <?php foreach ($mediaOptions as $media) : ?>
                            <option value="<?= (int) $media['id'] ?>"<?= $formValues['media_id'] === (int) $media['id'] ? ' selected' : '' ?>>
                                <?= $media['kind'] === 'image' ? 'Photo' : 'Vidéo' ?> · <?= e($media['title']) ?><?= (int) $media['is_protected'] === 1 ? ' (protégée)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>

                <label class="field">
                    <span class="field__label">Date du souvenir</span>
                    <input class="field__input" type="date" name="memory_date" value="<?= e($formValues['memory_date']) ?>">
                </label>

                <label class="field">
                    <span class="field__label">Ordre d'affichage</span>
                    <input class="field__input" type="number" name="sort_order" min="0" max="9999" value="<?= (int) $formValues['sort_order'] ?>">
                </label>

                <label class="field field--wide">
                    <span class="field__label">Description</span>
                    <textarea class="field__input field__input--area" name="description" rows="4" maxlength="3000" placeholder="Un souvenir, une anecdote, quelques mots…"><?= e($formValues['description']) ?></textarea>
                </label>
            </div>

            <label class="check">
                <input type="checkbox" name="is_published" value="1"<?= $formValues['is_published'] ? ' checked' : '' ?>>
                <span>Publier dans la galerie (décoche pour garder en brouillon)</span>
            </label>

            <div class="form-actions">
                <button class="btn btn--primary" type="submit"><?= icon($editing ? 'check' : 'plus', 17) ?> <?= $editing ? 'Enregistrer les modifications' : 'Créer le souvenir' ?></button>
                <?php if ($editing) : ?>
                    <a class="btn btn--ghost" href="<?= e(app_url('admin/memories.php')) ?>">Annuler</a>
                <?php endif; ?>
            </div>
        </form>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Galerie</p>
                <h2><?= count($memories) ?> souvenir<?= count($memories) > 1 ? 's' : '' ?></h2>
            </div>
        </header>

        <?php if (!$memories) : ?>
            <?= admin_empty_state('sparkles', 'Aucun souvenir', 'Le premier souvenir donnera le ton de la galerie.', '') ?>
        <?php else : ?>
            <ul class="memory-admin-list">
                <?php foreach ($memories as $index => $memory) : ?>
                    <?php
                    $thumb = '';
                    if (!empty($memory['media_ref_id'])) {
                        $thumb = media_url([
                            'id'             => (int) $memory['media_ref_id'],
                            'kind'           => (string) $memory['media_kind'],
                            'source'         => (string) $memory['media_source'],
                            'external_url'   => (string) $memory['external_url'],
                            'thumbnail_path' => $memory['thumbnail_path'],
                            'file_path'      => $memory['file_path'],
                        ], true);
                    }
                    ?>
                    <li class="memory-admin<?= (int) $memory['is_published'] === 1 ? '' : ' is-draft' ?>">
                        <span class="memory-admin__thumb">
                            <?php if ($thumb !== '') : ?>
                                <img src="<?= e($thumb) ?>" alt="" loading="lazy">
                            <?php else : ?>
                                <?= icon('sparkles', 20) ?>
                            <?php endif; ?>
                        </span>

                        <div class="memory-admin__body">
                            <div class="memory-admin__title-row">
                                <strong><?= e($memory['title']) ?></strong>
                                <?php if ((int) $memory['is_published'] === 1) : ?>
                                    <?= admin_badge('Publié', 'success') ?>
                                <?php else : ?>
                                    <?= admin_badge('Brouillon', 'warning') ?>
                                <?php endif; ?>
                            </div>
                            <p class="memory-admin__meta">
                                <?= $memory['category'] ? e($memory['category']) . ' · ' : '' ?>
                                <?= $memory['media_kind'] ? ($memory['media_kind'] === 'image' ? 'Photo' : 'Vidéo') . ' · ' : 'Sans média · ' ?>
                                ordre <?= (int) $memory['sort_order'] ?>
                            </p>
                        </div>

                        <div class="memory-admin__actions">
                            <form method="post" action="<?= e(app_url('actions/memories.php')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="id" value="<?= (int) $memory['id'] ?>">
                                <input type="hidden" name="direction" value="up">
                                <button class="icon-btn icon-btn--ghost" type="submit" title="Monter" aria-label="Monter ce souvenir"<?= $index === 0 ? ' disabled' : '' ?>><?= icon('arrow-up', 15) ?></button>
                            </form>
                            <form method="post" action="<?= e(app_url('actions/memories.php')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="id" value="<?= (int) $memory['id'] ?>">
                                <input type="hidden" name="direction" value="down">
                                <button class="icon-btn icon-btn--ghost" type="submit" title="Descendre" aria-label="Descendre ce souvenir"<?= $index === count($memories) - 1 ? ' disabled' : '' ?>><?= icon('arrow-down', 15) ?></button>
                            </form>
                            <a class="icon-btn icon-btn--ghost" href="<?= e(app_url('admin/memories.php?edit=' . (int) $memory['id'])) ?>#form" title="Modifier" aria-label="Modifier"><?= icon('edit', 15) ?></a>

                            <form method="post" action="<?= e(app_url('actions/memories.php')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $memory['id'] ?>">
                                <button class="icon-btn icon-btn--ghost" type="submit" title="<?= (int) $memory['is_published'] === 1 ? 'Masquer' : 'Publier' ?>" aria-label="<?= (int) $memory['is_published'] === 1 ? 'Masquer' : 'Publier' ?>">
                                    <?= icon((int) $memory['is_published'] === 1 ? 'eye-off' : 'eye', 15) ?>
                                </button>
                            </form>

                            <form method="post" action="<?= e(app_url('actions/memories.php')) ?>" class="inline-form" data-confirm="Supprimer le souvenir « <?= e($memory['title']) ?> » ? Le média restera dans la bibliothèque.">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $memory['id'] ?>">
                                <?= delete_confirm_field() ?>
                                <button class="icon-btn icon-btn--danger" type="submit" title="Supprimer" aria-label="Supprimer"><?= icon('trash', 15) ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>
    </section>
</div>

<?php
admin_footer();
