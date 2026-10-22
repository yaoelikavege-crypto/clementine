<?php
/**
 * Édition de la lettre d'amour.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$letter = db_fetch('SELECT * FROM love_letter WHERE id = 1 LIMIT 1');

$values = [
    'title'        => (string) ($letter['title'] ?? 'Pour toi, Clémentine'),
    'body'         => (string) ($letter['body'] ?? ''),
    'signature'    => (string) ($letter['signature'] ?? 'Joël'),
    'is_published' => $letter ? (int) $letter['is_published'] === 1 : true,
];

admin_header('Lettre d\'amour', 'letter');
?>

<?= admin_page_intro(
    'Lettre d\'amour',
    'Cette lettre est affichée après la galerie. Aère ton texte avec des sauts de ligne : chaque bloc devient un paragraphe.',
    '<a class="btn btn--soft btn--sm" href="' . e(app_url('pages/letter.php')) . '" target="_blank" rel="noopener">' . icon('eye', 15) . ' Aperçu</a>'
) ?>

<div class="admin-grid admin-grid--sidebar">
    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Contenu</p>
                <h2><?= $letter ? 'Modifier la lettre' : 'Créer la lettre' ?></h2>
            </div>
            <?php if ($letter && (int) $letter['is_published'] !== 1) : ?>
                <?= admin_badge('Brouillon', 'warning') ?>
            <?php endif; ?>
        </header>

        <form method="post" action="<?= e(app_url('actions/letter.php')) ?>" class="admin-form">
            <?= csrf_field() ?>

            <label class="field">
                <span class="field__label">Titre de la lettre *</span>
                <input class="field__input" type="text" name="title" required minlength="2" maxlength="150" value="<?= e($values['title']) ?>">
            </label>

            <label class="field">
                <span class="field__label">Corps de la lettre *</span>
                <textarea class="field__input field__input--area field__input--letter" name="body" rows="20" required maxlength="20000" data-char-count="letter-count"><?= e($values['body']) ?></textarea>
                <span class="field__hint"><span data-char-target="letter-count"><?= mb_strlen($values['body']) ?></span> caractères · les lignes vides séparent les paragraphes.</span>
            </label>

            <label class="field">
                <span class="field__label">Signature *</span>
                <input class="field__input" type="text" name="signature" required maxlength="100" value="<?= e($values['signature']) ?>">
            </label>

            <label class="check">
                <input type="checkbox" name="is_published" value="1"<?= $values['is_published'] ? ' checked' : '' ?>>
                <span>Publier la lettre (visible des visiteurs après la révélation)</span>
            </label>

            <div class="form-actions">
                <button class="btn btn--primary" type="submit"><?= icon('check', 17) ?> Enregistrer la lettre</button>
            </div>
        </form>
    </section>

    <aside class="panel panel--aside">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Repères</p>
                <h2>Quelques conseils</h2>
            </div>
        </header>

        <ul class="tip-list">
            <li><?= icon('sparkles', 16) ?><span>Une lettre sincère et personnelle touche plus qu'une lettre parfaite.</span></li>
            <li><?= icon('letter', 16) ?><span>Structure suggérée : un souvenir doux, ce que tu admires, un souhait pour l'année à venir, ta signature.</span></li>
            <li><?= icon('lock', 16) ?><span>Les visiteurs ne voient la lettre qu'après la révélation du <?= e(reveal_date_text()) ?>, et seulement après connexion.</span></li>
            <li><?= icon('shield', 16) ?><span>La lettre est enregistrée en base, jamais dans un fichier public.</span></li>
        </ul>

        <?php if ($letter && !empty($letter['updated_at'])) : ?>
            <p class="panel__empty">Dernière modification : <?= e(fr_date($letter['updated_at'], true)) ?><?= $letter['updated_by'] ? ' (admin #' . (int) $letter['updated_by'] . ')' : '' ?></p>
        <?php endif; ?>
    </aside>
</div>

<?php
admin_footer();
