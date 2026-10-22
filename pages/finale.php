<?php
/**
 * Scène finale : la bougie d'anniversaire.
 * Animation 100 % CSS/JS, sans vidéo ni bibliothèque externe.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';

require_content_access();

$tracks      = playlist_tracks('finale', true);
$whatsappUrl = (string) config('app.developer_whatsapp', 'https://wa.me/message/P65Q2OW5AOTTP1');

public_header([
    'title'  => 'Le grand moment',
    'active' => 'finale',
]);
?>

<section class="finale" data-finale>
    <div class="finale__stars" aria-hidden="true"></div>

    <header class="finale__head">
        <p class="eyebrow">Le <?= e(reveal_date_text()) ?></p>
        <h1 class="finale__title">Pour toi, <span>Clémentine</span></h1>
        <div class="finale__invite-wrap">
            <p class="finale__invite" id="finale-hint" data-finale-hint>
                <?= e(setting('finale_invite', 'Ferme les yeux, fais un vœu… puis souffle sur la bougie.')) ?>
            </p>
            <p class="visually-hidden" data-finale-blow hidden aria-live="polite"></p>
        </div>
    </header>

    <div class="finale__stage">
        <canvas class="finale__sparks" data-finale-sparks aria-hidden="true" width="0" height="0"></canvas>

        <button class="candle" type="button" data-candle aria-describedby="finale-hint" aria-label="Souffler la bougie d'anniversaire">
            <span class="candle__flame-wrap" aria-hidden="true">
                <span class="candle__glow"></span>
                <span class="candle__flame"><span class="candle__flame-core"></span></span>
            </span>
            <span class="candle__wick" aria-hidden="true"></span>
            <span class="candle__body" aria-hidden="true">
                <span class="candle__wax"></span>
                <span class="candle__number">21</span>
                <span class="candle__shine"></span>
            </span>
            <span class="candle__plate" aria-hidden="true"></span>
        </button>

        <div class="finale__wish" data-finale-message hidden>
            <p class="eyebrow">Ton vœu s'envole</p>
            <p class="finale__wish-title">Joyeux anniversaire<br><span><?= e(setting('finale_message', 'Clémentine')) ?></span></p>
            <p class="finale__wish-note"><?= e(setting('finale_note', "21 ans de lumière, et ce n'est que le début.")) ?></p>
            <p class="finale__wish-sign">Joël</p>
        </div>
    </div>

    <div class="finale__below">
        <button class="btn btn--ghost btn--sm finale__replay" type="button" data-finale-replay hidden>
            <?= icon('refresh', 16) ?><span>Rejouer l'instant</span>
        </button>
        <p class="finale__hint-sm" data-finale-tip>
            <?= icon('sparkles', 14) ?> Astuce : active le son pour une ambiance complète.
        </p>
    </div>

    <?php if ($tracks) : ?>
        <div class="finale__player">
            <?= music_player('finale', 'Musique de la finale') ?>
        </div>
    <?php endif; ?>
</section>

<section class="credits" aria-label="Signature du développeur">
    <div class="credits__inner glass">
        <div class="credits__brand">
            <span class="credits__mark" aria-hidden="true">N</span>
            <div class="credits__id">
                <strong>KavegeElis</strong>
                <span>Développeur du site · <span class="credits__nova">NovaTechX</span></span>
            </div>
        </div>

        <p class="credits__text">Une remarque, une idée, un petit mot ?</p>

        <a class="btn btn--whatsapp" href="<?= e($whatsappUrl) ?>" target="_blank" rel="noopener noreferrer">
            <?= icon('whatsapp', 18) ?><span>Contacter le développeur</span>
        </a>

        <p class="credits__note">Le lien ouvre WhatsApp sur téléphone et ordinateur. Si WhatsApp n'est pas disponible, contacte Joël pour transmettre le message.</p>
    </div>
</section>

<?php
public_footer(array_filter([
    'candle.js',
    $tracks ? 'player.js' : null,
]));
