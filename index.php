<?php
/**
 * Page d'accueil publique : compte à rebours, messages d'attente,
 * accès à l'expérience et musique d'ambiance.
 */

require_once __DIR__ . '/includes/bootstrap.php';
require_once __DIR__ . '/includes/layout.php';

$revealed = is_revealed();
$parts    = countdown_parts();

$messages = [];
if (!$revealed) {
    $messages = db_fetch_all(
        'SELECT title, message, reveal_at FROM countdown_messages
         WHERE is_active = 1 AND (reveal_at IS NULL OR reveal_at <= ?)
         ORDER BY sort_order ASC, id ASC',
        [now()->format('Y-m-d H:i:s')]
    );
}

$playlistTracks = playlist_tracks('countdown', true);

public_header([
    'title'       => $revealed ? 'Le grand jour' : 'Rendez-vous le 23 novembre',
    'description' => 'Clémentine fête ses 21 ans le ' . reveal_date_text() . '. Un site privé, préparé avec amour.',
    'active'      => 'home',
]);
?>

<section class="hero">
    <div class="hero__inner">
        <p class="eyebrow hero__kicker"><?= e(setting('hero_kicker', reveal_date_text() . ' · 21 ans')) ?></p>

        <h1 class="hero__title">
            <span class="hero__title-glow" aria-hidden="true"></span>
            <span class="hero__title-text"><?= e(setting('hero_title', 'Clémentine')) ?></span>
        </h1>

        <p class="hero__message"><?= e(setting('hero_message', 'Il y a des personnes qui rendent le monde plus doux simplement en y étant. Toi, tu es de celles-là.')) ?></p>
        <p class="hero__submessage"><?= e(setting('hero_submessage', "Un petit coin d'internet s'illumine rien que pour toi.")) ?></p>

        <div class="hero__stage<?= $revealed ? ' is-revealed' : '' ?>" data-reveal-stage>
            <div class="hero__state hero__state--waiting js-pre-reveal"<?= $revealed ? ' hidden' : '' ?>>
                <p class="hero__countdown-title"><?= e(setting('countdown_title', 'Le grand jour approche')) ?></p>
                <?= countdown_markup('hero') ?>
                <p class="hero__note"><?= e(setting('countdown_note', 'Reviens le 23 novembre à minuit.')) ?></p>
            </div>

            <div class="hero__state hero__state--open js-post-reveal"<?= $revealed ? '' : ' hidden' ?>>
                <p class="hero__reveal-eyebrow"><?= icon('sparkles', 18) ?> Le moment est arrivé</p>
                <p class="hero__reveal-title"><?= e(setting('reveal_title', 'Minuit a sonné.')) ?></p>
                <p class="hero__reveal-text"><?= e(setting('reveal_message', "L'attente prend fin : ton album de souvenirs est ouvert.")) ?></p>
            </div>

            <div class="hero__actions">
                <?php if (!is_authenticated()) : ?>
                    <a class="btn btn--primary btn--lg" href="<?= e(app_url('login.php')) ?>">
                        <?= icon('log-in', 18) ?><span><?= $revealed ? 'Entrer dans l\'expérience' : 'Se connecter' ?></span>
                    </a>
                    <?php if (!$revealed) : ?>
                        <a class="btn btn--ghost" href="#attente">Découvrir ce qui se prépare</a>
                    <?php endif; ?>
                <?php elseif (is_admin()) : ?>
                    <a class="btn btn--primary btn--lg" href="<?= e(app_url('admin/dashboard.php')) ?>">
                        <?= icon('grid', 18) ?><span>Tableau de bord</span>
                    </a>
                    <a class="btn btn--ghost" href="<?= e(app_url('pages/memories.php')) ?>">Aperçu des souvenirs</a>
                <?php elseif ($revealed) : ?>
                    <a class="btn btn--primary btn--lg" href="<?= e(app_url('pages/memories.php')) ?>">
                        <?= icon('image', 18) ?><span>Ouvrir mes souvenirs</span>
                    </a>
                <?php else : ?>
                    <a class="btn btn--primary btn--lg" href="<?= e(app_url('pages/countdown.php')) ?>">
                        <?= icon('clock', 18) ?><span>Voir mon compte à rebours</span>
                    </a>
                <?php endif; ?>
            </div>
        </div>

        <p class="hero__signature">Avec tout mon amour, Joël</p>
    </div>

    <div class="hero__scroll" aria-hidden="true">
        <span></span>
    </div>
</section>

<?php if (!$revealed && $messages) : ?>
    <section class="section" id="attente">
        <div class="section__head">
            <p class="eyebrow">En attendant minuit</p>
            <h2 class="section__title">Des mots, au compte-gouttes</h2>
            <p class="section__lead">Trois petites phrases se dévoilent au fil des jours, comme des indices.</p>
        </div>

        <div class="note-grid">
            <?php foreach ($messages as $index => $message) : ?>
                <article class="note-card glass" style="--note-delay: <?= e((string) ($index * 80)) ?>ms">
                    <span class="note-card__spark" aria-hidden="true"><?= icon('sparkles', 16) ?></span>
                    <?php if (!empty($message['title'])) : ?>
                        <h3><?= e($message['title']) ?></h3>
                    <?php endif; ?>
                    <p><?= e($message['message']) ?></p>
                    <footer>
                        <?php if (!empty($message['reveal_at'])) : ?>
                            Dévoilé le <?= e(fr_date($message['reveal_at'])) ?>
                        <?php else : ?>
                            Toujours visible
                        <?php endif; ?>
                    </footer>
                </article>
            <?php endforeach; ?>
        </div>
    </section>
<?php endif; ?>

<section class="section section--teasers">
    <div class="section__head">
        <p class="eyebrow">Le programme</p>
        <h2 class="section__title">Ce qui t'attend</h2>
        <p class="section__lead">Trois étapes, un seul fil : toi.</p>
    </div>

    <div class="teaser-grid">
        <article class="teaser glass">
            <span class="teaser__icon"><?= icon('image', 22) ?></span>
            <h3>Un album de souvenirs</h3>
            <p>Des photos, des vidéos et des instants choisis, gardés au chaud pour toi.</p>
            <span class="teaser__lock"><?= icon($revealed ? 'unlock' : 'lock', 14) ?><?= $revealed ? 'Ouvert' : 'Scellé jusqu\'au 23.11' ?></span>
        </article>

        <article class="teaser glass">
            <span class="teaser__icon"><?= icon('letter', 22) ?></span>
            <h3>Une lettre du cœur</h3>
            <p>Quelques mots sincères, ceux que l'on garde pour les grandes occasions.</p>
            <span class="teaser__lock"><?= icon($revealed ? 'unlock' : 'lock', 14) ?><?= $revealed ? 'Ouverte' : 'Scellée jusqu\'au 23.11' ?></span>
        </article>

        <article class="teaser glass">
            <span class="teaser__icon"><?= icon('sparkles', 22) ?></span>
            <h3>Une dernière surprise</h3>
            <p>Une bougie, un vœu, et un moment qui n'appartient qu'à toi.</p>
            <span class="teaser__lock"><?= icon($revealed ? 'unlock' : 'lock', 14) ?><?= $revealed ? 'Prête' : 'Le jour J' ?></span>
        </article>
    </div>
</section>

<?php if ($playlistTracks) : ?>
    <section class="section section--music">
        <div class="section__head">
            <p class="eyebrow">Ambiance</p>
            <h2 class="section__title">La musique de l'attente</h2>
            <p class="section__lead">Un fond sonore doux, à activer quand tu veux.</p>
        </div>
        <div class="player-zone">
            <?= music_player('countdown', "Musique du compte à rebours") ?>
        </div>
    </section>
<?php endif; ?>

<section class="section section--quote">
    <blockquote class="quote">
        <p>« Le plus beau des cadeaux, ce n'est pas ce qu'on offre : c'est la personne pour qui on le prépare. »</p>
        <footer>— Joël, pour Clémentine</footer>
    </blockquote>
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
    !$revealed ? 'countdown.js' : null,
    $playlistTracks ? 'player.js' : null,
]));
