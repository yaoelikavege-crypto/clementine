<?php
/**
 * Lettre d'amour et vœux d'anniversaire.
 * Contenus administrés ; visiteurs authentifiés après révélation.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/admin_layout.php';

require_content_access();

$isAdmin = is_admin();
$letter  = db_fetch('SELECT * FROM love_letter WHERE id = 1 LIMIT 1');

$canReadLetter = $letter && ((int) $letter['is_published'] === 1 || $isAdmin);

$wishes = db_fetch_all(
    'SELECT * FROM wishes ' . ($isAdmin ? '' : 'WHERE is_published = 1 ') . 'ORDER BY sort_order ASC, id ASC'
);

$tracks = playlist_tracks('letter', true);

public_header([
    'title'  => 'La lettre',
    'active' => 'letter',
]);
?>

<section class="letter-page">
    <header class="letter-page__head">
        <p class="eyebrow">Du cœur, sans filtre</p>
        <h1 class="letter-page__title"><?= e($letter['title'] ?? 'Une lettre pour toi') ?></h1>
        <p class="letter-page__intro"><?= e(setting('letter_intro', 'Quelques mots posés avec le cœur.')) ?></p>
    </header>

    <?php if (!$canReadLetter) : ?>
        <div class="letter-empty glass">
            <?= admin_empty_state('letter', 'La lettre n\'est pas encore publiée', 'Joël peaufine encore ses mots. Reviens bientôt : elle apparaîtra ici, rien que pour toi.', '<a class="btn btn--ghost btn--sm" href="' . e(app_url('index.php')) . '">Retour à l\'accueil</a>') ?>
        </div>
    <?php else : ?>
        <?php if ($isAdmin && (int) $letter['is_published'] !== 1) : ?>
            <div class="notice notice--warning">
                <?= icon('eye', 17) ?>
                <span>Aperçu administrateur : cette lettre est encore en <strong>brouillon</strong> et n'est pas visible des visiteurs.</span>
            </div>
        <?php endif; ?>

        <article class="letter-card">
            <div class="letter-card__border" aria-hidden="true"></div>
            <div class="letter-card__seal" aria-hidden="true"><?= icon('heart', 18) ?></div>

            <div class="letter-card__body">
                <?= nl2p((string) $letter['body']) ?>

                <p class="letter-card__signature"><?= e($letter['signature'] ?: 'Joël') ?></p>
                <p class="letter-card__date">Le <?= e(reveal_date_text()) ?></p>
            </div>

            <div class="letter-card__footer">
                <?php if (!empty($letter['updated_at'])) : ?>
                    <span>Dernière touche apportée le <?= e(fr_date($letter['updated_at'], true)) ?></span>
                <?php endif; ?>
                <?php if ($isAdmin) : ?>
                    <a class="letter-card__edit" href="<?= e(app_url('admin/letter.php')) ?>"><?= icon('edit', 14) ?> Modifier</a>
                <?php endif; ?>
            </div>
        </article>
    <?php endif; ?>

    <section class="wishes">
        <header class="wishes__head">
            <p class="eyebrow">Les vœux</p>
            <h2 class="section__title">Ce que l'on te souhaite</h2>
            <p class="section__lead">Quelques messages choisis pour accompagner cette nouvelle année.</p>
        </header>

        <?php if (!$wishes) : ?>
            <?= admin_empty_state('gift', 'Les vœux arrivent bientôt', 'Les messages d\'anniversaire seront publiés ici très prochainement.', '') ?>
        <?php else : ?>
            <div class="wish-grid">
                <?php foreach ($wishes as $wish) : ?>
                    <article class="wish-card glass<?= (int) $wish['is_published'] !== 1 ? ' is-muted' : '' ?>">
                        <span class="wish-card__quote" aria-hidden="true">“</span>
                        <p class="wish-card__message"><?= nl2br(e($wish['message'])) ?></p>
                        <footer class="wish-card__foot">
                            <span class="wish-card__author">— <?= e($wish['author']) ?></span>
                            <?php if ($isAdmin && (int) $wish['is_published'] !== 1) : ?>
                                <?= admin_badge('Masqué', 'warning') ?>
                            <?php endif; ?>
                        </footer>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </section>

    <?php if ($tracks) : ?>
        <div class="letter-page__player">
            <?= music_player('letter', 'Musique de la lettre') ?>
        </div>
    <?php endif; ?>

    <section class="letter-cta glass">
        <span class="letter-cta__icon"><?= icon('sparkles', 24) ?></span>
        <h2>Il reste une dernière chose…</h2>
        <p>Une bougie t'attend pour souffler tes 21 ans.</p>
        <a class="btn btn--primary btn--lg" href="<?= e(app_url('pages/finale.php')) ?>">
            <?= icon('gift', 18) ?><span>Souffler la bougie</span>
        </a>
    </section>
</section>

<?php
public_footer(array_filter([$tracks ? 'player.js' : null]));
