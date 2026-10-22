<?php
/**
 * Gabarit public : en-tête, navigation, lecteur musical, pied de page.
 * Le rendu est identique pour toutes les pages publiques ; les pages
 * d'administration utilisent admin_layout.php.
 */

function public_header(array $options = []): void
{
    require_page_method();

    $title       = (string) ($options['title'] ?? '');
    $description = (string) ($options['description'] ?? 'Un site privé, préparé avec soin pour les 21 ans de Clémentine.');
    $active      = (string) ($options['active'] ?? 'home');
    $bodyClass   = 'page page--' . preg_replace('/[^a-z0-9_-]/', '', $active);

    $siteTitle = page_title($title);
    $iconUrl   = asset('assets/images/favicon.svg');
    $cssUrl    = asset('assets/css/main.css');
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#0B1026">
    <meta name="color-scheme" content="dark">
    <meta name="description" content="<?= e($description) ?>">
    <title><?= e($siteTitle) ?></title>
    <link rel="icon" href="<?= e($iconUrl) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($cssUrl) ?>">
</head>
<body class="<?= e($bodyClass) ?>">
<a class="skip-link" href="#main">Aller au contenu principal</a>

<div class="ambient" aria-hidden="true">
    <canvas class="starfield" data-starfield></canvas>
    <div class="aurora aurora--violet"></div>
    <div class="aurora aurora--rose"></div>
    <div class="grain"></div>
</div>

<header class="site-header" data-header>
    <div class="site-header__inner">
        <a class="brand" href="<?= e(app_url('index.php')) ?>" aria-label="Clémentine — accueil">
            <span class="brand__mark" aria-hidden="true">
                <svg viewBox="0 0 40 40" width="34" height="34" fill="none" aria-hidden="true">
                    <circle cx="20" cy="20" r="18" stroke="url(#brandGrad)" stroke-width="1.4" opacity=".7"/>
                    <path d="M20 8.5l2.6 8.2 8.4 2.8-8.4 2.9L20 31l-2.6-8.6-8.4-2.9 8.4-2.8z" fill="url(#brandGrad)" opacity=".95"/>
                    <circle cx="30.5" cy="10.5" r="2" fill="#FF6FB5"/>
                    <defs>
                        <linearGradient id="brandGrad" x1="6" y1="34" x2="34" y2="6" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#7957FF"/><stop offset="1" stop-color="#FF6FB5"/>
                        </linearGradient>
                    </defs>
                </svg>
            </span>
            <span class="brand__text">
                <span class="brand__name">Clémentine</span>
                <span class="brand__sub">21 ans · 23.11.2026</span>
            </span>
        </a>

        <nav class="site-nav" id="site-nav" aria-label="Navigation principale">
            <?= public_navigation($active) ?>
        </nav>

        <div class="site-header__actions">
            <?= public_user_area() ?>
            <button class="nav-toggle" type="button" data-nav-toggle aria-expanded="false" aria-controls="site-nav" aria-label="Ouvrir le menu">
                <span class="nav-toggle__bars" aria-hidden="true"><span></span><span></span><span></span></span>
            </button>
        </div>
    </div>
    <?= admin_preview_banner() ?>
</header>

<main id="main" class="site-main">
    <?php
}

function public_navigation(string $active): string
{
    $links = [];

    if (is_admin()) {
        $links = [
            ['label' => 'Tableau de bord', 'url' => app_url('admin/dashboard.php'), 'key' => 'admin', 'icon' => 'grid'],
            ['label' => 'Souvenirs', 'url' => app_url('pages/memories.php'), 'key' => 'memories', 'icon' => 'image'],
            ['label' => 'Lettre', 'url' => app_url('pages/letter.php'), 'key' => 'letter', 'icon' => 'letter'],
            ['label' => 'Finale', 'url' => app_url('pages/finale.php'), 'key' => 'finale', 'icon' => 'sparkles'],
        ];
    } elseif (is_visitor()) {
        $links[] = ['label' => 'Accueil', 'url' => app_url('index.php'), 'key' => 'home', 'icon' => 'home'];

        if (is_revealed()) {
            $links[] = ['label' => 'Souvenirs', 'url' => app_url('pages/memories.php'), 'key' => 'memories', 'icon' => 'image'];
            $links[] = ['label' => 'Lettre', 'url' => app_url('pages/letter.php'), 'key' => 'letter', 'icon' => 'letter'];
            $links[] = ['label' => 'Finale', 'url' => app_url('pages/finale.php'), 'key' => 'finale', 'icon' => 'sparkles'];
        } else {
            $links[] = ['label' => 'Compte à rebours', 'url' => app_url('pages/countdown.php'), 'key' => 'countdown', 'icon' => 'clock'];
        }
    } else {
        $links[] = ['label' => 'Accueil', 'url' => app_url('index.php'), 'key' => 'home', 'icon' => 'home'];
    }

    $html = '';
    foreach ($links as $link) {
        $isActive = $link['key'] === $active;
        $html .= '<a class="site-nav__link' . ($isActive ? ' is-active' : '') . '" href="' . e($link['url']) . '"' . ($isActive ? ' aria-current="page"' : '') . '>'
            . icon($link['icon'], 17) . '<span>' . e($link['label']) . '</span></a>';
    }

    return $html;
}

function public_user_area(): string
{
    if (!is_authenticated()) {
        return '<a class="btn btn--ghost btn--sm site-header__login" href="' . e(app_url('login.php')) . '">'
            . icon('log-in', 16) . '<span>Se connecter</span></a>';
    }

    $user = current_user() ?? [];
    $isAdmin = is_admin();
    $label = $isAdmin ? (string) ($user['display_name'] ?? 'Admin') : 'Visiteur';

    $html = '<span class="user-chip' . ($isAdmin ? ' user-chip--admin' : '') . '" title="' . ($isAdmin ? 'Compte administrateur' : 'Accès visiteur') . '">'
        . icon($isAdmin ? 'shield' : 'user', 15)
        . '<span class="user-chip__label">' . e($label) . '</span>'
        . '</span>';

    $html .= '<form class="inline-form" method="post" action="' . e(app_url('actions/logout.php')) . '">'
        . csrf_field()
        . '<button class="icon-btn icon-btn--ghost" type="submit" title="Se déconnecter" aria-label="Se déconnecter">' . icon('logout', 17) . '</button>'
        . '</form>';

    return $html;
}

function admin_preview_banner(): string
{
    if (!is_admin() || is_revealed()) {
        return '';
    }

    return '<div class="preview-banner" role="status">'
        . icon('lock', 15)
        . '<span><strong>Aperçu administrateur</strong> — le site public est encore verrouillé jusqu\'au ' . e(reveal_moment_text()) . '.</span>'
        . '<a href="' . e(app_url('admin/dashboard.php')) . '">Tableau de bord</a>'
        . '</div>';
}

function public_footer(array $scripts = []): void
{
    $signature = setting('footer_signature', 'Avec tout mon amour, Joël');
    $coreJs    = asset('assets/js/core.js');
    ?>
</main>

<footer class="site-footer">
    <div class="site-footer__inner">
        <p class="site-footer__signature"><?= e($signature) ?></p>
        <p class="site-footer__meta">Clémentine · <?= e(reveal_date_text()) ?> · site privé</p>
        <p class="site-footer__credit">
            Conception <strong>KavegeElis</strong>
            <span aria-hidden="true">·</span>
            <span class="site-footer__brand">NovaTechX</span>
        </p>
    </div>
</footer>

<div class="toasts" data-toasts aria-live="polite" aria-atomic="false"></div>

<script src="<?= e($coreJs) ?>" defer></script>
<?php foreach ($scripts as $script) : ?>
    <script src="<?= e(asset('assets/js/' . basename($script))) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
    <?php
}

/**
 * Lecteur audio commun aux pages de l'expérience.
 * Affiche uniquement les pistes auxquelles la session en cours a droit.
 */
function music_player(string $playlistSlug, string $label = 'Ambiance musicale'): string
{
    $tracks = playlist_tracks($playlistSlug, true);

    if (!$tracks) {
        return '';
    }

    $dataId = 'player-data-' . preg_replace('/[^a-z0-9-]/', '-', strtolower($playlistSlug));
    $first  = $tracks[0];
    $count  = count($tracks);

    ob_start();
    ?>
<section class="player-shell" aria-label="<?= e($label) ?>">
    <div class="player glass" data-player data-tracks-id="<?= e($dataId) ?>">
        <audio data-player-audio preload="none"></audio>

        <button class="player__art" type="button" data-player-toggle aria-label="Lancer la musique">
            <span class="player__art-icon player__art-icon--play"><?= icon('play', 20) ?></span>
            <span class="player__art-icon player__art-icon--pause"><?= icon('pause', 18) ?></span>
            <span class="player__rings" aria-hidden="true"></span>
        </button>

        <div class="player__body">
            <div class="player__head">
                <span class="player__title" data-player-title><?= e($first['title']) ?></span>
                <span class="player__artist" data-player-artist><?= $first['artist'] !== '' ? e($first['artist']) : 'Clémentine · 21 ans' ?></span>
            </div>
            <div class="player__bar" data-player-seek role="slider" tabindex="0" aria-label="Progression de la lecture" aria-valuemin="0" aria-valuemax="100" aria-valuenow="0">
                <span class="player__bar-fill" data-player-progress></span>
            </div>
            <div class="player__meta">
                <span data-player-elapsed>0:00</span>
                <span class="player__count"><?= e((string) $count) ?> <?= $count > 1 ? 'pistes' : 'piste' ?></span>
                <span data-player-duration>0:00</span>
            </div>
        </div>

        <div class="player__controls">
            <?php if ($count > 1) : ?>
                <button class="icon-btn" type="button" data-player-prev aria-label="Piste précédente"<?= $count < 2 ? ' disabled' : '' ?>><?= icon('prev', 16) ?></button>
            <?php endif; ?>
            <button class="icon-btn icon-btn--accent" type="button" data-player-toggle aria-label="Lecture ou pause">
                <span class="icon-btn__play"><?= icon('play', 16) ?></span>
                <span class="icon-btn__pause"><?= icon('pause', 16) ?></span>
            </button>
            <?php if ($count > 1) : ?>
                <button class="icon-btn" type="button" data-player-next aria-label="Piste suivante"><?= icon('next', 16) ?></button>
            <?php endif; ?>
            <button class="icon-btn" type="button" data-player-mute aria-label="Couper le son"><?= icon('volume', 16) ?></button>
            <label class="player__volume" aria-label="Volume">
                <input type="range" min="0" max="100" step="1" value="70" data-player-volume>
            </label>
        </div>
        <span class="visually-hidden" data-player-status role="status" aria-live="polite"></span>
    </div>

    <div class="player-unlock" data-player-unlock hidden>
        <button class="btn btn--soft btn--sm" type="button" data-player-start>
            <?= icon('music', 16) ?><span>Activer la musique</span>
        </button>
        <span class="player-unlock__hint">Ton navigateur a bloqué la lecture automatique.</span>
    </div>

    <script type="application/json" id="<?= e($dataId) ?>"><?= json_encode($tracks, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
</section>
    <?php
    return (string) ob_get_clean();
}

/**
 * Compte à rebours rendu côté serveur (valeurs exactes, même sans JS).
 */
function countdown_markup(string $variant = 'hero'): string
{
    $parts = countdown_parts();

    $cells = [
        ['value' => $parts['days'], 'label' => 'jours', 'key' => 'days'],
        ['value' => $parts['hours'], 'label' => 'heures', 'key' => 'hours'],
        ['value' => $parts['minutes'], 'label' => 'minutes', 'key' => 'minutes'],
        ['value' => $parts['seconds'], 'label' => 'secondes', 'key' => 'seconds'],
    ];

    $html = '<div class="countdown countdown--' . e($variant) . '" data-countdown role="timer" aria-label="Compte à rebours avant le ' . e(reveal_moment_text()) . '">';
    foreach ($cells as $cell) {
        $html .= '<div class="countdown__cell">'
            . '<span class="countdown__value" data-countdown-value="' . e($cell['key']) . '">' . e(str_pad((string) $cell['value'], 2, '0', STR_PAD_LEFT)) . '</span>'
            . '<span class="countdown__label">' . e($cell['label']) . '</span>'
            . '</div>';
    }
    $html .= '</div>';

    return $html;
}
