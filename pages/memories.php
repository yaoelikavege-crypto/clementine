<?php
/**
 * Galerie des souvenirs.
 * Accès : administrateur (aperçu permanent) ou visiteur authentifié
 * APRÈS la révélation. Le contrôle est refait ici, côté serveur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';
require_once __DIR__ . '/../includes/layout.php';
require_once __DIR__ . '/../includes/admin_layout.php';

require_content_access();

$isAdmin     = is_admin();
$perPage     = 12;
$page        = max(1, get_int('page', 1));
$category    = trim(input('categorie'));

$where  = $isAdmin ? '1 = 1' : 'm.is_published = 1';
$params = [];

if ($category !== '') {
    $where   .= ' AND m.category = ?';
    $params[] = $category;
}

$total = (int) db_value('SELECT COUNT(*) FROM memories m WHERE ' . $where, $params);
$pager = pagination($total, $perPage, $page);

$rows = db_fetch_all(
    'SELECT m.*,
            md.id AS media_ref_id,
            md.kind AS media_kind,
            md.source AS media_source,
            md.external_url AS media_external_url,
            md.thumbnail_path AS media_thumbnail_path,
            md.file_path AS media_file_path,
            md.width AS media_width,
            md.height AS media_height,
            md.mime_type AS media_mime
     FROM memories m
     LEFT JOIN media md ON md.id = m.media_id
     WHERE ' . $where . '
     ORDER BY m.sort_order ASC, m.id DESC
     LIMIT ' . (int) $perPage . ' OFFSET ' . (int) $pager['offset'],
    $params
);

$categories = db_fetch_all(
    'SELECT DISTINCT category FROM memories
     WHERE ' . ($isAdmin ? '1 = 1' : 'is_published = 1') . "
       AND category IS NOT NULL AND category <> ''
     ORDER BY category ASC"
);

$items = [];
foreach ($rows as $index => $row) {
    $media = null;
    if (!empty($row['media_ref_id'])) {
        $media = [
            'id'             => (int) $row['media_ref_id'],
            'kind'           => (string) $row['media_kind'],
            'source'         => (string) $row['media_source'],
            'external_url'   => (string) $row['media_external_url'],
            'thumbnail_path' => $row['media_thumbnail_path'],
            'file_path'      => $row['media_file_path'],
            'width'          => $row['media_width'],
            'height'         => $row['media_height'],
        ];
    }

    $kind   = $media['kind'] ?? 'text';
    $thumb  = $media ? media_url($media, true) : '';
    $src    = $media ? media_url($media) : '';
    $embed  = $media ? media_embed_url($media) : null;
    $isExt  = $media && $media['source'] === 'external';

    $items[] = [
        'index'       => $index,
        'kind'        => $kind,
        'title'       => (string) $row['title'],
        'description' => (string) ($row['description'] ?? ''),
        'date'        => !empty($row['memory_date']) ? fr_date((string) $row['memory_date']) : '',
        'category'    => (string) ($row['category'] ?? ''),
        'thumb'       => $thumb,
        'src'         => $src,
        'embed'       => $embed,
        'external'    => $isExt ? (string) $row['media_external_url'] : '',
        'width'       => (int) ($row['media_width'] ?? 0),
        'height'      => (int) ($row['media_height'] ?? 0),
        'draft'       => (int) $row['is_published'] !== 1,
    ];
}

$tracks = playlist_tracks('memories', true);

public_header([
    'title'  => 'Nos souvenirs',
    'active' => 'memories',
]);
?>

<section class="gallery">
    <header class="gallery__head">
        <p class="eyebrow">L'album</p>
        <h1 class="gallery__title">Nos souvenirs</h1>
        <p class="gallery__intro"><?= e(setting('gallery_intro', 'Des instants, des sourires, des fragments de nous.')) ?></p>

        <?php if ($isAdmin) : ?>
            <div class="gallery__adminbar">
                <a class="btn btn--soft btn--sm" href="<?= e(app_url('admin/memories.php')) ?>"><?= icon('edit', 15) ?> Gérer les souvenirs</a>
                <a class="btn btn--ghost btn--sm" href="<?= e(app_url('admin/media.php?kind=image')) ?>"><?= icon('image', 15) ?> Ajouter une photo</a>
                <?php if (!is_revealed()) : ?>
                    <span class="badge badge--violet">Aperçu avant révélation</span>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </header>

    <?php if ($categories) : ?>
        <nav class="gallery__filters" aria-label="Filtrer par catégorie">
            <a class="chip<?= $category === '' ? ' is-active' : '' ?>" href="<?= e(url_with(['categorie' => null, 'page' => null])) ?>">Tout</a>
            <?php foreach ($categories as $cat) : ?>
                <a class="chip<?= $category === $cat['category'] ? ' is-active' : '' ?>" href="<?= e(url_with(['categorie' => $cat['category'], 'page' => null])) ?>">
                    <?= e($cat['category']) ?>
                </a>
            <?php endforeach; ?>
        </nav>
    <?php endif; ?>

    <?php if (!$items) : ?>
        <div class="gallery__empty glass">
            <?php if ($isAdmin) : ?>
                <?= admin_empty_state('image', 'Aucun souvenir pour l\'instant', 'Créez votre premier souvenir depuis l\'administration : importez une photo ou une vidéo, puis associez-la à une carte.', '<a class="btn btn--primary btn--sm" href="' . e(app_url('admin/memories.php')) . '">Créer un souvenir</a>') ?>
            <?php else : ?>
                <?= admin_empty_state('sparkles', 'L\'album se prépare encore', 'De nouvelles pages s\'ajouteront bientôt. Reviens un peu plus tard : chaque souvenir a son heure.', '<a class="btn btn--ghost btn--sm" href="' . e(app_url('index.php')) . '">Retour à l\'accueil</a>') ?>
            <?php endif; ?>
        </div>
    <?php else : ?>
        <div class="memory-grid">
            <?php foreach ($items as $item) : ?>
                <article class="memory-card glass<?= $item['draft'] ? ' is-draft' : '' ?>">
                    <button class="memory-card__media<?= $item['thumb'] === '' ? ' is-placeholder' : '' ?>" type="button"
                            data-gallery-open="<?= (int) $item['index'] ?>"
                            aria-label="Ouvrir « <?= e($item['title']) ?> » en grand">
                        <?php if ($item['thumb'] !== '') : ?>
                            <?php if ($item['kind'] === 'image') : ?>
                                <img src="<?= e($item['thumb']) ?>" alt="<?= e($item['title']) ?>"
                                     loading="lazy" decoding="async"
                                     <?= $item['width'] > 0 ? 'width="' . (int) $item['width'] . '" height="' . (int) $item['height'] . '"' : '' ?>>
                            <?php else : ?>
                                <img src="<?= e($item['thumb']) ?>" alt="" loading="lazy" decoding="async" aria-hidden="true">
                            <?php endif; ?>
                        <?php else : ?>
                            <span class="memory-card__placeholder-art">
                                <?= icon($item['kind'] === 'video' ? 'video' : ($item['kind'] === 'image' ? 'image' : 'sparkles'), 34) ?>
                            </span>
                        <?php endif; ?>

                        <?php if ($item['kind'] === 'video') : ?>
                            <span class="memory-card__play"><?= icon('play', 22) ?></span>
                        <?php endif; ?>

                        <span class="memory-card__zoom"><?= icon('eye', 15) ?><span>Agrandir</span></span>
                        <?php if ($item['draft']) : ?>
                            <span class="memory-card__draft"><?= admin_badge('Brouillon', 'warning') ?></span>
                        <?php endif; ?>
                    </button>

                    <div class="memory-card__body">
                        <div class="memory-card__meta">
                            <?php if ($item['category'] !== '') : ?>
                                <span class="memory-card__category"><?= e($item['category']) ?></span>
                            <?php endif; ?>
                            <?php if ($item['date'] !== '') : ?>
                                <span class="memory-card__date"><?= e($item['date']) ?></span>
                            <?php endif; ?>
                        </div>
                        <h2 class="memory-card__title"><?= e($item['title']) ?></h2>
                        <?php if ($item['description'] !== '') : ?>
                            <p class="memory-card__desc"><?= e(excerpt($item['description'], 130)) ?></p>
                        <?php endif; ?>
                        <button class="memory-card__open" type="button" data-gallery-open="<?= (int) $item['index'] ?>">
                            <span>Ouvrir le souvenir</span><?= icon('arrow-right', 15) ?>
                        </button>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>

        <?php if ($pager['pages'] > 1) : ?>
            <nav class="pagination" aria-label="Pages de la galerie">
                <?php if ($pager['has_prev']) : ?>
                    <a class="pagination__link" href="<?= e(url_with(['page' => $pager['page'] - 1])) ?>" rel="prev"><?= icon('chevron-left', 16) ?> Précédent</a>
                <?php else : ?>
                    <span class="pagination__link is-disabled"><?= icon('chevron-left', 16) ?> Précédent</span>
                <?php endif; ?>

                <span class="pagination__pages">
                    page <?= (int) $pager['page'] ?> sur <?= (int) $pager['pages'] ?>
                </span>

                <?php if ($pager['has_next']) : ?>
                    <a class="pagination__link" href="<?= e(url_with(['page' => $pager['page'] + 1])) ?>" rel="next">Suivant <?= icon('chevron-right', 16) ?></a>
                <?php else : ?>
                    <span class="pagination__link is-disabled">Suivant <?= icon('chevron-right', 16) ?></span>
                <?php endif; ?>
            </nav>
        <?php endif; ?>
    <?php endif; ?>

    <?php if ($tracks) : ?>
        <div class="gallery__player">
            <?= music_player('memories', 'Musique de la galerie') ?>
        </div>
    <?php endif; ?>

    <section class="gallery-cta glass">
        <span class="gallery-cta__icon"><?= icon('letter', 24) ?></span>
        <p class="eyebrow">La suite</p>
        <h2><?= e(setting('gallery_end_cta', 'Et si tu découvrais maintenant quelques mots qui viennent du cœur ?')) ?></h2>
        <a class="btn btn--primary btn--lg" href="<?= e(app_url('pages/letter.php')) ?>">
            <?= icon('heart', 18) ?><span>Lire la lettre</span>
        </a>
    </section>

    <p class="gallery__privacy"><?= icon('lock', 14) ?> Ces souvenirs sont privés. Merci de ne pas les partager en dehors de la famille et des amis proches.</p>
</section>

<dialog class="lightbox" data-lightbox aria-labelledby="lightbox-title">
    <div class="lightbox__inner">
        <header class="lightbox__bar">
            <span class="lightbox__counter" data-lightbox-counter></span>
            <button class="lightbox__close" type="button" data-lightbox-close aria-label="Fermer la visionneuse"><?= icon('x', 22) ?></button>
        </header>

        <div class="lightbox__stage" data-lightbox-stage></div>

        <div class="lightbox__meta">
            <div class="lightbox__tags">
                <span class="memory-card__category" data-lightbox-category hidden></span>
                <span class="memory-card__date" data-lightbox-date hidden></span>
            </div>
            <h2 id="lightbox-title" class="lightbox__title" data-lightbox-title></h2>
            <p class="lightbox__desc" data-lightbox-desc></p>
        </div>

        <nav class="lightbox__nav" aria-label="Navigation dans la galerie">
            <button class="lightbox__navbtn" type="button" data-lightbox-prev aria-label="Souvenir précédent"><?= icon('chevron-left', 22) ?></button>
            <button class="lightbox__navbtn" type="button" data-lightbox-next aria-label="Souvenir suivant"><?= icon('chevron-right', 22) ?></button>
        </nav>
    </div>
</dialog>

<script type="application/json" id="gallery-data"><?= json_encode($items, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

<?php
public_footer(array_filter([
    'gallery.js',
    $tracks ? 'player.js' : null,
]));
