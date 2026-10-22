<?php
/**
 * Messages et indices dévoilés pendant la période d'attente.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_admin();
require_once __DIR__ . '/../includes/admin_layout.php';

$editId  = get_int('edit');
$editing = null;

if ($editId > 0) {
    $editing = db_fetch('SELECT * FROM countdown_messages WHERE id = ? LIMIT 1', [$editId]);
    if (!$editing) {
        flash('error', 'Ce message est introuvable.');
        redirect(app_url('admin/countdown.php'));
    }
}

$messages = db_fetch_all('SELECT * FROM countdown_messages ORDER BY sort_order ASC, id ASC');

$values = [
    'title'      => (string) ($editing['title'] ?? ''),
    'message'    => (string) ($editing['message'] ?? ''),
    'reveal_at'  => datetime_input_value($editing['reveal_at'] ?? null),
    'sort_order' => (int) ($editing['sort_order'] ?? (count($messages) + 1)),
    'is_active'  => $editing ? (int) $editing['is_active'] === 1 : true,
];

admin_header('Messages & indices', 'countdown');
?>

<?= admin_page_intro(
    'Messages & indices',
    'Ces phrases s\'affichent sur l\'accueil au fil des jours, avant la révélation. Laisse la date vide pour un message visible immédiatement.',
    '<a class="btn btn--soft btn--sm" href="' . e(app_url('index.php')) . '" target="_blank" rel="noopener">' . icon('eye', 15) . ' Voir l\'accueil</a>'
) ?>

<div class="admin-grid admin-grid--sidebar">
    <section class="panel" id="form">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow"><?= $editing ? 'Édition' : 'Nouveau' ?></p>
                <h2><?= $editing ? 'Modifier le message' : 'Ajouter un message' ?></h2>
            </div>
            <?php if ($editing) : ?>
                <a class="panel__link" href="<?= e(app_url('admin/countdown.php')) ?>">Annuler</a>
            <?php endif; ?>
        </header>

        <form method="post" action="<?= e(app_url('actions/countdown.php')) ?>" class="admin-form">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="<?= $editing ? 'update' : 'create' ?>">
            <?php if ($editing) : ?>
                <input type="hidden" name="id" value="<?= (int) $editing['id'] ?>">
            <?php endif; ?>

            <label class="field">
                <span class="field__label">Titre (facultatif)</span>
                <input class="field__input" type="text" name="title" maxlength="120" value="<?= e($values['title']) ?>" placeholder="Un secret bien gardé">
            </label>

            <label class="field">
                <span class="field__label">Message *</span>
                <textarea class="field__input field__input--area" name="message" rows="4" required maxlength="1200" data-char-count="message-count"><?= e($values['message']) ?></textarea>
                <span class="field__hint"><span data-char-target="message-count"><?= mb_strlen($values['message']) ?></span> / 1 200 caractères.</span>
            </label>

            <div class="form-grid">
                <label class="field">
                    <span class="field__label">Dévoilement (heure du Togo)</span>
                    <input class="field__input" type="datetime-local" name="reveal_at" value="<?= e($values['reveal_at']) ?>">
                    <span class="field__hint">Vide = visible dès maintenant.</span>
                </label>

                <label class="field">
                    <span class="field__label">Ordre d'affichage</span>
                    <input class="field__input" type="number" name="sort_order" min="0" max="9999" value="<?= (int) $values['sort_order'] ?>">
                </label>
            </div>

            <label class="check">
                <input type="checkbox" name="is_active" value="1"<?= $values['is_active'] ? ' checked' : '' ?>>
                <span>Activer ce message</span>
            </label>

            <div class="form-actions">
                <button class="btn btn--primary" type="submit"><?= icon($editing ? 'check' : 'plus', 17) ?> <?= $editing ? 'Enregistrer' : 'Ajouter' ?></button>
            </div>
        </form>
    </section>

    <section class="panel">
        <header class="panel__head">
            <div>
                <p class="panel__eyebrow">Programmation</p>
                <h2><?= count($messages) ?> message<?= count($messages) > 1 ? 's' : '' ?></h2>
            </div>
        </header>

        <?php if (!$messages) : ?>
            <?= admin_empty_state('message', 'Aucun message', 'Ajoute des indices pour entretenir le mystère avant le 23 novembre.', '') ?>
        <?php else : ?>
            <ul class="message-admin-list">
                <?php foreach ($messages as $message) : ?>
                    <?php
                    $isActive = (int) $message['is_active'] === 1;
                    $revealAt = $message['reveal_at'];
                    $visibleNow = $revealAt === null || $revealAt <= now()->format('Y-m-d H:i:s');
                    ?>
                    <li class="message-admin<?= $isActive ? '' : ' is-off' ?>">
                        <div class="message-admin__body">
                            <div class="message-admin__head">
                                <?php if (!empty($message['title'])) : ?>
                                    <strong><?= e($message['title']) ?></strong>
                                <?php else : ?>
                                    <strong>Sans titre</strong>
                                <?php endif; ?>
                                <?php if (!$isActive) : ?>
                                    <?= admin_badge('Inactif', 'neutral') ?>
                                <?php elseif ($visibleNow) : ?>
                                    <?= admin_badge('Visible', 'success') ?>
                                <?php else : ?>
                                    <?= admin_badge('Programmé', 'violet') ?>
                                <?php endif; ?>
                                <span class="message-admin__order">ordre <?= (int) $message['sort_order'] ?></span>
                            </div>
                            <p><?= e(excerpt($message['message'], 180)) ?></p>
                            <span class="message-admin__date">
                                <?= icon('clock', 13) ?>
                                <?= $revealAt ? 'Dévoilé le ' . e(fr_date($revealAt, true)) : 'Visible immédiatement' ?>
                            </span>
                        </div>

                        <div class="message-admin__actions">
                            <a class="icon-btn icon-btn--ghost" href="<?= e(app_url('admin/countdown.php?edit=' . (int) $message['id'])) ?>#form" title="Modifier" aria-label="Modifier"><?= icon('edit', 15) ?></a>

                            <form method="post" action="<?= e(app_url('actions/countdown.php')) ?>" class="inline-form">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="toggle">
                                <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
                                <button class="icon-btn icon-btn--ghost" type="submit" title="<?= $isActive ? 'Désactiver' : 'Activer' ?>"><?= icon($isActive ? 'eye-off' : 'eye', 15) ?></button>
                            </form>

                            <form method="post" action="<?= e(app_url('actions/countdown.php')) ?>" class="inline-form" data-confirm="Supprimer ce message ?">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="delete">
                                <input type="hidden" name="id" value="<?= (int) $message['id'] ?>">
                                <?= delete_confirm_field() ?>
                                <button class="icon-btn icon-btn--danger" type="submit" title="Supprimer" aria-label="Supprimer"><?= icon('trash', 15) ?></button>
                            </form>
                        </div>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <div class="notice notice--info notice--sm">
            <?= icon('info', 15) ?>
            <span>Après le 23 novembre à minuit, ces messages cèdent la place au message de révélation (modifiable dans les paramètres généraux).</span>
        </div>
    </section>
</div>

<?php
admin_footer();
