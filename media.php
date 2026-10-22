<?php
/**
 * Diffuseur de médias protégé.
 *
 * Aucun fichier de /uploads n'est accessible directement : chaque requête
 * passe par ce contrôleur, qui vérifie la session, le rôle et la date de
 * révélation avant de diffuser le flux (avec support des plages HTTP).
 */

require_once __DIR__ . '/includes/bootstrap.php';

$id      = get_int('id');
$variant = input('variant');

if ($id <= 0) {
    abort(404, 'Média introuvable.');
}

$media = db_fetch('SELECT * FROM media WHERE id = ? LIMIT 1', [$id]);

if (!$media) {
    abort(404, 'Média introuvable.');
}

if (($media['source'] ?? 'local') !== 'local') {
    abort(404, 'Ce média est hébergé à l\'extérieur du site.');
}

if (!user_can_access_media($media)) {
    log_event('Tentative d\'accès à un média protégé refusée (id ' . $id . ').', 'warning');
    abort(403, 'Ce souvenir est encore scellé. Il se dévoilera le ' . reveal_moment_text() . '.');
}

$isThumb = $variant === 'thumb';

if ($isThumb && $media['kind'] === 'audio') {
    abort(404, 'Aucune miniature pour ce média.');
}

$path = media_absolute_path($media, $isThumb);

if ($path === null) {
    abort(404, 'Fichier introuvable sur le serveur.');
}

$mime = $isThumb ? 'image/jpeg' : media_mime($media);

if ($isThumb) {
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $detected = (string) $finfo->file($path);
    if (str_starts_with($detected, 'image/')) {
        $mime = $detected;
    }
}

$extension = pathinfo($path, PATHINFO_EXTENSION) ?: 'bin';
$baseName  = preg_replace('/[^A-Za-z0-9_-]+/', '-', (string) ($media['title'] ?? 'souvenir')) ?: 'souvenir';
$filename  = $baseName . ($isThumb ? '-apercu' : '') . '.' . $extension;

$cache = max(0, (int) config('app.media_cache_seconds', 3600));

stream_media_file($path, $mime, $filename, $cache);
