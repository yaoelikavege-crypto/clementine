<?php
/**
 * Gestion des vœux d'anniversaire.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$editId  = get_int('edit');
$editing = null;

if ($editId > 0) {
    $editing = db_fetch('SELECT * FROM wishes WHERE id = ? LIMIT 1', [$editId]);
    if (!$editing) {
        flash('error', 'Ce vœu est introuvable.');
        redirect(app_url('admin/wishes.php'));
    }
}

$wishes = db_fetch_all('SELECT * FROM wishes ORDER BY sort_order ASC, id ASC');

$values = [
    'author'       => (string) ($editing['author'] ?? ''),
    'message'      => (string) ($editing['message'] ?? ''),
    'sort_order'   => (int) ($editing['sort_order'] ?? (count($wishes) + 1)),
    'is_published' => $editing ? (int) $editing['is_published'] === 1 : true,
];

admin_header('Vœux d\'anniversaire', 'wishes');
?>

<?= admin_page_intro(
    'Vœux d\'anniversaire',
    'Les messages publiés apparaissent sous la lettre. Ils ne sont visibles qu\'après la révélation.',
    '<a class="btn btn--soft btn--sm" href="' . e(app_url('pages/letter.php')) . '" target="_blank" rel="noopener">' . icon('eye', 15) . ' Aperçu</a>'
) ?>

<div class="admin-grid admin-grid--sidebar">
    <section class="panel" id="form">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow"><?= $editing ? 'Édition' : 'Nouveau' ?></p>
                <h2><?= $editing ? 'Modifier le vœu' : 'Ajouter un vœu' ?></h2>
            </div>
            <?php if ($editing) : ?>
                <a class="panel__link" href="<?= e(app_url('admin/wishes.php')) ?>">Annuler</a>
            <?php endif; ?>
        </header>

        <form method="post" action="<?= e(app_url('actions/wishes.php')) ?>" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing) : ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

            <label class="field">
                <span class="field__label">Auteur du message *</span>
                <input class="field__input" type="text" name="author" required minlength="2" maxlength="100" value="<?= e($values['author']) ?>" placeholder="Joël, tes camarades, la famille…">
            </label>

            <label class="field">
                <span class="field__label">Message *</span>
                <textarea class="field__input field__input--area" name="message" rows="4" required maxlength="1500" data-char-count="wish-count"><?= e($values['message']) ?></textarea>
                <span class="field__hint"><span data-char-target="wish-count"><?= mb_strlen($values['message']) ?></span> / 1 500 caractères.</span>
            </label>

            <div class="form-grid">
                <label class="field">
                    <span class="field__label">Ordre d'affichage</span>
                    <input class="field__input" type="number" name="sort_order" min="0" max="9999" value="<?= (int) $values['sort_order'] ?>">
                </label>

                <div class="field">
                    <label class="check">
                        <input type="checkbox" name="is_published" value="1"<?= $values['is_published'] ? ' checked' : '' ?>>
                        <span>Publier ce vœu</span>
                    </label>
                </div>
            </div>

            <div class="form-actions">
                <button class="btn btn--primary" type="submit"><?= icon($editing ? 'check' : 'plus', 17) ?> <?= $editing ? 'Enregistrer' : 'Ajouter le vœu' ?></button>
            </div>
        </form>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Publiés & brouillons</p>
                <h2><?= count($wishes) ?> vœu<?= count($wishes) > 1 ? 'x' : '' ?></h2>
            </div>
        </header>

        <?php if (!$wishes) : ?>
            <?= admin_empty_state('gift', 'Aucun vœu pour l\'instant', 'Ajoute quelques messages pour accompagner la lettre.', '') ?>
        <?php else : ?>
            <ul class="wish-admin-list">
                <?php foreach ($wishes as $wish) : ?>
                    <li class="wish-admin<?= (int) $wish['is_published'] === 1 ? '' : ' is-off' ?>">
                        <div class="wish-admin__body">
                            <div class="wish-admin__head">
                                <strong><?= e($wish['author']) ?></strong>
                                <?php if ((int) $wish['is_published'] === 1) : ?>
                                    <?= admin_badge('Publié', 'success') ?>
                                <?php else : ?>
                                    <?= admin_badge('Masqué', 'warning') ?>
                                <?php endif; ?>
                                <span class="wish-admin__order">ordre <?= (int) $wish['sort_order'] ?></span>
                            </div>
                            <p><?= e(excerpt($wish['message'], 180)) ?></p>
                        </div>

                        <div class="wish-admin__actions">
                            <a class="icon-btn icon-btn--ghost" href="<?= e(app_url('admin/wishes.php?edit=' . (int) $wish['id'])) ?>#form" title="Modifier" aria-label="Modifier"><?= icon('edit', 15) ?></a>

                            <form method="post" action="<?= e(app_url('actions/wishes.php')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $wish['id'] ?>">
                                <button class="icon-btn icon-btn--ghost" type="submit" title="<?= (int) $wish['is_published'] === 1 ? 'Masquer' : 'Publier' ?>"><?= icon((int) $wish['is_published'] === 1 ? 'eye-off' : 'eye', 15) ?></button>
                            </form>

                            <form method="post" action="<?= e(app_url('actions/wishes.php')) ?>" class="inline-form" data-confirm="Supprimer le vœu de <?= e($wish['author']) ?> ?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $wish['id'] ?>">
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
