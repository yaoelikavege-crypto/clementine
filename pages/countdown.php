<?php
/**
 * Salle d'attente des visiteurs : compte à rebours et indices dévoilés.
 * Accessible après connexion, avant la date de révélation.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_auth();

$revealed = is_revealed();

if ($revealed && !is_admin()) {
    redirect(app_url('pages/memories.php'));
}

$messages = db_fetch_all(
    'SELECT title, message, reveal_at FROM countdown_messages
     WHERE is_active = 1 AND (reveal_at IS NULL OR reveal_at <= ?)
     ORDER BY sort_order ASC, id ASC',
    [now()->format('Y-m-d H:i:s')]
);

$tracks = playlist_tracks('countdown', true);

public_header([
    'title'  => 'Compte à rebours',
    'active' => 'countdown',
]);
?>

<section class="countdown-page">
    <div class="countdown-page__halo" aria-hidden="true"></div>

    <div class="countdown-page__intro">
        <p class="eyebrow"><?= is_admin() ? 'Aperçu administrateur' : 'Coucou Clémentine' ?></p>
        <h1 class="countdown-page__title"><?= e(setting('countdown_title', 'Le grand jour approche')) ?></h1>
        <p class="countdown-page__lead">
            <?= e(setting('countdown_note', 'Reviens le 23 novembre à minuit. Le reste, c\'est une surprise.')) ?>
        </p>
    </div>

    <?= countdown_markup('large') ?>

    <p class="countdown-page__date">
        <?= icon('calendar', 17) ?>
        <span>Rendez-vous le <strong><?= e(fr_date(reveal_at(), true)) ?></strong> — heure du Togo</span>
    </p>

    <?php if (is_admin()) : ?>
        <div class="notice notice--info">
            <?= icon('shield', 17) ?>
            <span>Tu es connecté en administrateur : tu peux préparer le site et prévisualiser les pages. Les visiteurs, eux, ne voient encore que cette attente.</span>
        </div>
        <div class="countdown-page__admin">
            <a class="btn btn--soft btn--sm" href="<?= e(app_url('admin/dashboard.php')) ?>"><?= icon('grid', 16) ?> Tableau de bord</a>
            <a class="btn btn--ghost btn--sm" href="<?= e(app_url('pages/memories.php')) ?>"><?= icon('image', 16) ?> Prévisualiser la galerie</a>
        </div>
    <?php endif; ?>

    <?php if ($messages) : ?>
        <section class="countdown-page__notes">
            <h2 class="section__title section__title--sm">Les indices déjà dévoilés</h2>
            <div class="note-grid note-grid--compact">
                <?php foreach ($messages as $message) : ?>
                    <article class="note-card glass">
                        <span class="note-card__spark" aria-hidden="true"><?= icon('sparkles', 16) ?></span>
                        <?php if (!empty($message['title'])) : ?>
                            <h3><?= e($message['title']) ?></h3>
                        <?php endif; ?>
                        <p><?= e($message['message']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($tracks) : ?>
        <div class="countdown-page__player">
            <?= music_player('countdown', 'Musique du compte à rebours') ?>
        </div>
    <?php endif; ?>

    <div class="countdown-page__teaser">
        <p><?= icon('lock', 16) ?> Après minuit, tout s'ouvrira : les souvenirs, la lettre, la bougie.</p>
    </div>
</section>

<script type="application/json" id="countdown-config"><?= json_encode([
    'revealAt'  => reveal_at()->getTimestamp() * 1000,
    'serverNow' => (int) round(microtime(true) * 1000),
    'revealed'  => $revealed,
    'timeApi'   => app_url('api/time.php'),
    'loginUrl'  => app_url('login.php'),
], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php
public_footer(array_filter([
    'countdown.js',
    $tracks ? 'player.js' : null,
]));
