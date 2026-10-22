<?php
/**
 * Gabarit de l'espace d'administration.
 * Toutes les pages admin appellent admin_header() puis admin_footer().
 */

function admin_nav_groups(): array
{
    return [
        'Pilotage' => [
            ['key' => 'dashboard', 'label' => 'Tableau de bord', 'url' => app_url('admin/dashboard.php'), 'icon' => 'grid'],
        ],
        'Contenus' => [
            ['key' => 'memories', 'label' => 'Souvenirs & galerie', 'url' => app_url('admin/memories.php'), 'icon' => 'sparkles'],
            ['key' => 'photos', 'label' => 'Photos', 'url' => app_url('admin/media.php?kind=image'), 'icon' => 'image'],
            ['key' => 'videos', 'label' => 'Vidéos', 'url' => app_url('admin/media.php?kind=video'), 'icon' => 'video'],
            ['key' => 'musiques', 'label' => 'Bibliothèque musicale', 'url' => app_url('admin/media.php?kind=audio'), 'icon' => 'music'],
        ],
        'Expérience' => [
            ['key' => 'playlists', 'label' => 'Playlists', 'url' => app_url('admin/playlists.php'), 'icon' => 'playlist'],
            ['key' => 'letter', 'label' => 'Lettre d\'amour', 'url' => app_url('admin/letter.php'), 'icon' => 'letter'],
            ['key' => 'wishes', 'label' => 'Vœux d\'anniversaire', 'url' => app_url('admin/wishes.php'), 'icon' => 'gift'],
            ['key' => 'countdown', 'label' => 'Messages & indices', 'url' => app_url('admin/countdown.php'), 'icon' => 'message'],
        ],
        'Système' => [
            ['key' => 'credentials', 'label' => 'Mots de passe', 'url' => app_url('admin/credentials.php'), 'icon' => 'key'],
            ['key' => 'settings', 'label' => 'Paramètres généraux', 'url' => app_url('admin/settings.php'), 'icon' => 'sliders'],
        ],
    ];
}

function admin_header(string $title, string $active = 'dashboard', array $options = []): void
{
    require_page_method();

    $user      = current_user() ?? [];
    $name      = (string) ($user['display_name'] ?? 'Administrateur');
    $siteTitle = page_title($title . ' · Administration');
    $iconUrl   = asset('assets/images/favicon.svg');
    $cssMain   = asset('assets/css/main.css');
    $cssAdmin  = asset('assets/css/admin.css');
    $isPrep    = !is_revealed();
    $seconds   = seconds_to_reveal();
    $days      = (int) floor($seconds / 86400);
    ?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow, noarchive">
    <meta name="theme-color" content="#0B1026">
    <meta name="color-scheme" content="dark">
    <title><?= e($siteTitle) ?></title>
    <link rel="icon" href="<?= e($iconUrl) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="<?= e($cssMain) ?>">
    <link rel="stylesheet" href="<?= e($cssAdmin) ?>">
</head>
<body class="admin-body">
<a class="skip-link" href="#admin-main">Aller au contenu</a>

<div class="admin-shell">
    <aside class="admin-sidebar" id="admin-sidebar" data-sidebar aria-label="Navigation de l'administration">
        <div class="admin-brand">
            <span class="admin-brand__mark" aria-hidden="true">
                <svg viewBox="0 0 40 40" width="30" height="30" fill="none" aria-hidden="true">
                    <circle cx="20" cy="20" r="18" stroke="url(#adminGrad)" stroke-width="1.4" opacity=".65"/>
                    <path d="M20 8.5l2.6 8.2 8.4 2.8-8.4 2.9L20 31l-2.6-8.6-8.4-2.9 8.4-2.8z" fill="url(#adminGrad)"/>
                    <defs>
                        <linearGradient id="adminGrad" x1="6" y1="34" x2="34" y2="6" gradientUnits="userSpaceOnUse">
                            <stop stop-color="#7957FF"/><stop offset="1" stop-color="#FF6FB5"/>
                        </linearGradient>
                    </defs>
                </svg>
            </span>
            <span class="admin-brand__text">
                <strong>Clémentine</strong>
                <span>Administration · NovaTechX</span>
            </span>
            <button class="admin-sidebar__close" type="button" data-sidebar-close aria-label="Fermer le menu"><?= icon('x', 18) ?></button>
        </div>

        <nav class="admin-nav">
            <?php foreach (admin_nav_groups() as $group => $items) : ?>
                <p class="admin-nav__group"><?= e($group) ?></p>
                <?php foreach ($items as $item) : ?>
                    <a class="admin-nav__link<?= $item['key'] === $active ? ' is-active' : '' ?>"
                       href="<?= e($item['url']) ?>"<?= $item['key'] === $active ? ' aria-current="page"' : '' ?>>
                        <?= icon($item['icon'], 18) ?>
                        <span><?= e($item['label']) ?></span>
                    </a>
                <?php endforeach; ?>
            <?php endforeach; ?>
        </nav>

        <div class="admin-sidebar__foot">
            <div class="admin-state <?= $isPrep ? 'admin-state--prep' : 'admin-state--live' ?>">
                <?= icon($isPrep ? 'lock' : 'unlock', 16) ?>
                <div>
                    <strong><?= $isPrep ? 'Avant révélation' : 'Expérience ouverte' ?></strong>
                    <span><?= $isPrep ? ('J-' . $days . ' avant le 23.11.2026') : 'Les visiteurs peuvent entrer' ?></span>
                </div>
            </div>
            <a class="admin-nav__link" href="<?= e(app_url('index.php')) ?>" target="_blank" rel="noopener">
                <?= icon('external-link', 17) ?><span>Voir le site public</span>
            </a>
            <form class="admin-logout" method="post" action="<?= e(app_url('actions/logout.php')) ?>">
                <?= csrf_field() ?>
                <button class="admin-logout__btn" type="submit">
                    <?= icon('logout', 17) ?><span>Déconnexion</span>
                </button>
            </form>
        </div>
    </aside>

    <div class="admin-overlay" data-sidebar-overlay hidden></div>

    <div class="admin-main" id="admin-main">
        <header class="admin-topbar">
            <button class="admin-menu-btn" type="button" data-sidebar-open aria-label="Ouvrir le menu" aria-controls="admin-sidebar">
                <?= icon('menu', 20) ?>
            </button>
            <div class="admin-topbar__title">
                <p class="admin-topbar__eyebrow">Administration</p>
                <h1><?= e($title) ?></h1>
            </div>
            <div class="admin-topbar__actions">
                <span class="admin-user" title="Compte administrateur">
                    <?= icon('shield', 15) ?>
                    <span><?= e($name) ?></span>
                </span>
                <form class="inline-form" method="post" action="<?= e(app_url('actions/logout.php')) ?>">
                    <?= csrf_field() ?>
                    <button class="icon-btn icon-btn--ghost" type="submit" title="Se déconnecter" aria-label="Se déconnecter"><?= icon('logout', 17) ?></button>
                </form>
            </div>
        </header>

        <div class="admin-content">
            <?= render_flashes() ?>
            <?php
}

function admin_footer(array $scripts = []): void
{
    $coreJs  = asset('assets/js/core.js');
    $adminJs = asset('assets/js/admin.js');
    ?>
        </div>

        <footer class="admin-footer">
            <span>Clémentine · 21 ans — espace d'administration</span>
            <span>Conception KavegeElis · NovaTechX</span>
        </footer>
    </div>
</div>

<div class="toasts" data-toasts aria-live="polite"></div>

<script src="<?= e($coreJs) ?>" defer></script>
<script src="<?= e($adminJs) ?>" defer></script>
<?php foreach ($scripts as $script) : ?>
    <script src="<?= e(asset('assets/js/' . basename($script))) ?>" defer></script>
<?php endforeach; ?>
</body>
</html>
    <?php
}

/* -------------------------------------------------------------------------
 *  Petits composants d'interface
 * ---------------------------------------------------------------------- */

function admin_page_intro(string $title, string $text = '', string $actionsHtml = ''): string
{
    return '<div class="admin-page-intro">'
        . '<div><h2>' . e($title) . '</h2>'
        . ($text !== '' ? '<p>' . e($text) . '</p>' : '')
        . '</div>'
        . ($actionsHtml !== '' ? '<div class="admin-page-intro__actions">' . $actionsHtml . '</div>' : '')
        . '</div>';
}

function admin_stat_card(string $label, string $value, string $iconName, string $hint = '', string $href = ''): string
{
    $inner = '<span class="stat-card__icon">' . icon($iconName, 20) . '</span>'
        . '<div class="stat-card__body">'
        . '<span class="stat-card__value">' . e($value) . '</span>'
        . '<span class="stat-card__label">' . e($label) . '</span>'
        . ($hint !== '' ? '<span class="stat-card__hint">' . e($hint) . '</span>' : '')
        . '</div>';

    if ($href !== '') {
        return '<a class="stat-card" href="' . e($href) . '">' . $inner . '</a>';
    }

    return '<div class="stat-card">' . $inner . '</div>';
}

function admin_empty_state(string $iconName, string $title, string $text, string $actionsHtml = ''): string
{
    return '<div class="empty-state">'
        . '<span class="empty-state__icon">' . icon($iconName, 26) . '</span>'
        . '<h3>' . e($title) . '</h3>'
        . '<p>' . e($text) . '</p>'
        . $actionsHtml
        . '</div>';
}

function admin_badge(string $text, string $tone = 'neutral'): string
{
    return '<span class="badge badge--' . e($tone) . '">' . e($text) . '</span>';
}

/**
 * Champ <input type="hidden"> confirmant une action destructrice.
 */
function delete_confirm_field(): string
{
    return '<input type="hidden" name="confirm_delete" value="1">';
}
