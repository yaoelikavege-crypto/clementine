<?php
/**
 * Bibliothèque multimédia : validation, stockage, suppression, diffusion.
 *
 * Les fichiers sont stockés hors du contrôleur PHP et ne sont jamais servis
 * directement : /media.php vérifie la session puis diffuse le fichier.
 * Le dossier /uploads est en plus interdit d'accès par .htaccess.
 */

/* -------------------------------------------------------------------------
 *  Chemins & configuration
 * ---------------------------------------------------------------------- */

function uploads_dir(): string
{
    $custom = config('media.uploads_dir', null);

    if (is_string($custom) && $custom !== '') {
        return rtrim($custom, '/\\');
    }

    return ROOT_PATH . '/uploads';
}

function media_type_config(): array
{
    return [
        'image' => [
            'label'     => 'Photo',
            'max_mb'    => (int) config('media.max_image_mb', 6),
            'mimes'     => [
                'image/jpeg' => 'jpg',
                'image/png'  => 'png',
                'image/webp' => 'webp',
            ],
            'directory' => 'images',
        ],
        'video' => [
            'label'     => 'Vidéo',
            'max_mb'    => (int) config('media.max_video_mb', 8),
            'mimes'     => [
                'video/mp4'  => 'mp4',
                'video/webm' => 'webm',
            ],
            'directory' => 'videos',
        ],
        'audio' => [
            'label'     => 'Musique',
            'max_mb'    => (int) config('media.max_audio_mb', 8),
            'mimes'     => [
                'audio/mpeg'   => 'mp3',
                'audio/mp4'    => 'm4a',
                'audio/x-m4a'  => 'm4a',
                'audio/aac'    => 'aac',
                'audio/ogg'    => 'ogg',
                'audio/wav'    => 'wav',
                'audio/x-wav'  => 'wav',
            ],
            'directory' => 'audio',
        ],
    ];
}

function media_uploader_limits(): array
{
    $parse = static function (string $value): int {
        $value = trim($value);
        if ($value === '' || $value === '-1') {
            return -1;
        }
        $unit = strtolower(substr($value, -1));
        $num  = (float) $value;

        switch ($unit) {
            case 'g':
                $num *= 1024;
                // no break
            case 'm':
                $num *= 1024;
                // no break
            case 'k':
                $num *= 1024;
        }

        return (int) $num;
    };

    return [
        'upload_max_filesize' => $parse((string) ini_get('upload_max_filesize')),
        'post_max_size'       => $parse((string) ini_get('post_max_size')),
        'memory_limit'        => $parse((string) ini_get('memory_limit')),
    ];
}

function upload_error_message(int $code): string
{
    switch ($code) {
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'Le fichier dépasse la limite autorisée par le serveur.';
        case UPLOAD_ERR_PARTIAL:
            return 'Le téléversement a été interrompu. Réessaie avec une connexion plus stable.';
        case UPLOAD_ERR_NO_FILE:
            return 'Aucun fichier n\'a été sélectionné.';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'Le dossier temporaire du serveur est indisponible.';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Le serveur n\'a pas pu écrire le fichier sur le disque.';
        case UPLOAD_ERR_EXTENSION:
            return 'Le téléversement a été bloqué par une extension du serveur.';
        default:
            return 'Le téléversement a échoué.';
    }
}

function ensure_upload_directories(): bool
{
    $base = uploads_dir();
    foreach (['images', 'videos', 'audio', 'thumbnails'] as $dir) {
        $path = $base . '/' . $dir;
        if (!is_dir($path) && !@mkdir($path, 0755, true)) {
            return false;
        }
    }

    return is_writable($base);
}

/* -------------------------------------------------------------------------
 *  Traitement des images (GD)
 * ---------------------------------------------------------------------- */

function gd_load_image(string $path, string $mime)
{
    switch ($mime) {
        case 'image/jpeg':
            return function_exists('imagecreatefromjpeg') ? @imagecreatefromjpeg($path) : null;
        case 'image/png':
            return function_exists('imagecreatefrompng') ? @imagecreatefrompng($path) : null;
        case 'image/webp':
            return function_exists('imagecreatefromwebp') ? @imagecreatefromwebp($path) : null;
    }

    return null;
}

function gd_apply_orientation($image, string $tmpPath, string $mime)
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return $image;
    }

    $exif = @exif_read_data($tmpPath);
    $orientation = (int) ($exif['Orientation'] ?? 0);

    $angles = [3 => 180, 6 => -90, 8 => 90];
    if (isset($angles[$orientation])) {
        $rotated = @imagerotate($image, $angles[$orientation], 0);
        if ($rotated !== false) {
            imagedestroy($image);

            return $rotated;
        }
    }

    return $image;
}

function gd_save_resized($source, int $srcW, int $srcH, int $maxSize, string $destination, string $outputMime): bool
{
    $scale = min(1, $maxSize / max($srcW, $srcH));
    $dstW  = max(1, (int) round($srcW * $scale));
    $dstH  = max(1, (int) round($srcH * $scale));

    $dst = imagecreatetruecolor($dstW, $dstH);
    if ($dst === false) {
        return false;
    }

    if ($outputMime === 'image/png' || $outputMime === 'image/webp') {
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        $transparent = imagecolorallocatealpha($dst, 0, 0, 0, 127);
        imagefilledrectangle($dst, 0, 0, $dstW, $dstH, $transparent);
    }

    imagecopyresampled($dst, $source, 0, 0, 0, 0, $dstW, $dstH, $srcW, $srcH);

    $ok = false;
    switch ($outputMime) {
        case 'image/png':
            $ok = imagepng($dst, $destination, 7);
            break;
        case 'image/webp':
            $ok = function_exists('imagewebp') ? imagewebp($dst, $destination, 84) : false;
            break;
        default:
            $ok = imagejpeg($dst, $destination, 86);
            break;
    }

    imagedestroy($dst);

    return (bool) $ok;
}

/**
 * Ré-encode une image (supprime les métadonnées, limite les dimensions)
 * et génère sa miniature.
 *
 * @return array{ok:bool,error:string,data:array}
 */
function process_image_upload(string $tmpPath, string $mime): array
{
    if (!function_exists('imagecreatetruecolor')) {
        return ['ok' => false, 'error' => 'L\'extension GD de PHP est requise pour traiter les images. Contactez l\'hébergeur.', 'data' => []];
    }

    $info = @getimagesize($tmpPath);
    if (!is_array($info) || empty($info[0]) || empty($info[1])) {
        return ['ok' => false, 'error' => 'Ce fichier n\'est pas une image valide.', 'data' => []];
    }

    [$width, $height] = [(int) $info[0], (int) $info[1]];

    if ($width < 10 || $height < 10) {
        return ['ok' => false, 'error' => 'L\'image est trop petite (minimum 10 × 10 pixels).', 'data' => []];
    }

    if ($width > 10000 || $height > 10000 || ($width * $height) > 40000000) {
        return ['ok' => false, 'error' => 'L\'image est trop grande. Réduis-la avant de la téléverser (max 40 mégapixels).', 'data' => []];
    }

    $source = gd_load_image($tmpPath, $mime);
    if (!$source) {
        return ['ok' => false, 'error' => 'Impossible de lire cette image (format corrompu ou non pris en charge).', 'data' => []];
    }

    $source = gd_apply_orientation($source, $tmpPath, $mime);
    $srcW = imagesx($source);
    $srcH = imagesy($source);

    // Format de sortie : PNG si transparence, sinon JPEG (WebP conservé si disponible).
    $outputMime = 'image/jpeg';
    $extension  = 'jpg';

    if ($mime === 'image/png') {
        $outputMime = 'image/png';
        $extension  = 'png';
    } elseif ($mime === 'image/webp' && function_exists('imagewebp')) {
        $outputMime = 'image/webp';
        $extension  = 'webp';
    }

    $base     = random_token(16);
    $mainRel  = 'images/' . $base . '.' . $extension;
    $thumbRel = 'thumbnails/' . $base . '.jpg';
    $mainAbs  = uploads_dir() . '/' . $mainRel;
    $thumbAbs = uploads_dir() . '/' . $thumbRel;

    $okMain = gd_save_resized($source, $srcW, $srcH, 2200, $mainAbs, $outputMime);

    // La miniature est toujours en JPEG, sauf PNG transparent.
    $thumbMime = $outputMime === 'image/png' ? 'image/png' : 'image/jpeg';
    if ($thumbMime === 'image/png') {
        $thumbRel = 'thumbnails/' . $base . '.png';
        $thumbAbs = uploads_dir() . '/' . $thumbRel;
    }
    $okThumb = gd_save_resized($source, $srcW, $srcH, 900, $thumbAbs, $thumbMime);

    imagedestroy($source);

    if (!$okMain || !$okThumb) {
        @unlink($mainAbs);
        @unlink($thumbAbs);

        return ['ok' => false, 'error' => 'L\'image n\'a pas pu être enregistrée sur le serveur.', 'data' => []];
    }

    @chmod($mainAbs, 0644);
    @chmod($thumbAbs, 0644);

    $finalInfo = @getimagesize($mainAbs);

    return [
        'ok'    => true,
        'error' => '',
        'data'  => [
            'file_path'      => $mainRel,
            'thumbnail_path' => $thumbRel,
            'mime_type'      => $outputMime,
            'file_size'      => (int) @filesize($mainAbs),
            'width'          => (int) ($finalInfo[0] ?? $srcW),
            'height'         => (int) ($finalInfo[1] ?? $srcH),
        ],
    ];
}

/* -------------------------------------------------------------------------
 *  Téléversements
 * ---------------------------------------------------------------------- */

/**
 * Valide et stocke un fichier téléversé.
 *
 * @return array{ok:bool,error:string,data:array}
 */
function store_uploaded_file(string $kind, array $file): array
{
    $configs = media_type_config();

    if (!isset($configs[$kind])) {
        return ['ok' => false, 'error' => 'Type de média inconnu.', 'data' => []];
    }

    $spec = $configs[$kind];

    if (!isset($file['error']) || is_array($file['error'])) {
        return ['ok' => false, 'error' => 'Requête de téléversement invalide.', 'data' => []];
    }

    $errorCode = (int) $file['error'];
    if ($errorCode !== UPLOAD_ERR_OK) {
        return ['ok' => false, 'error' => upload_error_message($errorCode), 'data' => []];
    }

    $tmpName = (string) ($file['tmp_name'] ?? '');
    if ($tmpName === '' || !is_uploaded_file($tmpName)) {
        return ['ok' => false, 'error' => 'Fichier temporaire introuvable.', 'data' => []];
    }

    $size = (int) ($file['size'] ?? 0);
    if ($size <= 0) {
        return ['ok' => false, 'error' => 'Le fichier est vide.', 'data' => []];
    }

    $maxBytes = $spec['max_mb'] * 1024 * 1024;
    if ($size > $maxBytes) {
        return [
            'ok'    => false,
            'error' => 'Fichier trop volumineux : ' . format_bytes($size) . ' pour un maximum de ' . $spec['max_mb'] . ' Mo. '
                . 'Sur un hébergement limité, préfère une vidéo externe (YouTube, Vimeo) ou compresse le fichier.',
            'data'  => [],
        ];
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = (string) $finfo->file($tmpName);

    // Certains serveurs détectent les fichiers .m4a comme du MP4 vidéo.
    if ($kind === 'audio' && $mime === 'video/mp4') {
        $mime = 'audio/mp4';
    }

    if (!isset($spec['mimes'][$mime])) {
        return ['ok' => false, 'error' => 'Format non autorisé (' . e($mime) . '). Formats acceptés : ' . implode(', ', array_keys($spec['mimes'])) . '.', 'data' => []];
    }

    if (!ensure_upload_directories()) {
        return ['ok' => false, 'error' => 'Le dossier de stockage des médias n\'est pas accessible en écriture.', 'data' => []];
    }

    if ($kind === 'image') {
        return process_image_upload($tmpName, $mime);
    }

    $extension = $spec['mimes'][$mime];
    $relative  = $spec['directory'] . '/' . random_token(16) . '.' . $extension;
    $absolute  = uploads_dir() . '/' . $relative;

    if (!@move_uploaded_file($tmpName, $absolute)) {
        return ['ok' => false, 'error' => 'Le fichier n\'a pas pu être déplacé vers le dossier de stockage.', 'data' => []];
    }

    @chmod($absolute, 0644);

    return [
        'ok'    => true,
        'error' => '',
        'data'  => [
            'file_path'      => $relative,
            'thumbnail_path' => null,
            'mime_type'      => $mime,
            'file_size'      => (int) @filesize($absolute),
            'width'          => null,
            'height'         => null,
        ],
    ];
}

/**
 * Traite une miniature personnalisée (facultative) pour une vidéo.
 *
 * @return array{ok:bool,error:string,path:?string}
 */
function store_thumbnail_upload(array $file): array
{
    if ((int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        return ['ok' => true, 'error' => '', 'path' => null];
    }

    $result = store_uploaded_file('image', $file);
    if (!$result['ok']) {
        return ['ok' => false, 'error' => $result['error'], 'path' => null];
    }

    // L'image complète n'est pas nécessaire : on ne conserve que la miniature.
    $main = uploads_dir() . '/' . $result['data']['file_path'];
    @unlink($main);

    return ['ok' => true, 'error' => '', 'path' => $result['data']['thumbnail_path']];
}

/* -------------------------------------------------------------------------
 *  Vidéos externes
 * ---------------------------------------------------------------------- */

/**
 * @return array{ok:bool,error:string,provider:string,external_id:?string,embed_url:?string,thumbnail_url:?string,normalized:string}
 */
function parse_external_video_url(string $url): array
{
    $fail = static function (string $message): array {
        return [
            'ok' => false, 'error' => $message, 'provider' => 'unknown',
            'external_id' => null, 'embed_url' => null, 'thumbnail_url' => null, 'normalized' => '',
        ];
    };

    $url = trim($url);
    if ($url === '' || strlen($url) > 500) {
        return $fail('L\'adresse de la vidéo est vide ou trop longue.');
    }

    $parts = parse_url($url);
    if (!is_array($parts) || empty($parts['host'])) {
        return $fail('Adresse invalide. Colle une URL complète commençant par https://');
    }

    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    if ($scheme !== 'https') {
        return $fail('Seules les adresses HTTPS sont acceptées.');
    }

    if (!empty($parts['user']) || !empty($parts['pass'])) {
        return $fail('Les adresses contenant des identifiants sont refusées.');
    }

    $host = strtolower((string) $parts['host']);
    $host = preg_replace('/^www\./', '', $host) ?? $host;
    $path = (string) ($parts['path'] ?? '');

    // Refus des adresses locales / privées (protection SSRF et réseau interne).
    if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.internal')) {
        return $fail('Les adresses locales ne sont pas autorisées.');
    }

    if (filter_var($host, FILTER_VALIDATE_IP)) {
        if (!filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
            return $fail('Les adresses IP privées ne sont pas autorisées.');
        }
    }

    $normalized = 'https://' . $host . $path . (empty($parts['query']) ? '' : '?' . $parts['query']);

    // YouTube
    if (in_array($host, ['youtube.com', 'm.youtube.com', 'youtube-nocookie.com', 'youtu.be'], true)) {
        $id = null;
        if ($host === 'youtu.be') {
            $id = trim($path, '/');
        } else {
            if (preg_match('~^/watch$~', $path) && !empty($parts['query'])) {
                parse_str((string) $parts['query'], $query);
                $id = $query['v'] ?? null;
            } elseif (preg_match('~^/(?:shorts|embed|live)/([A-Za-z0-9_-]+)~', $path, $m)) {
                $id = $m[1];
            }
        }

        if (!is_string($id) || !preg_match('/^[A-Za-z0-9_-]{6,20}$/', $id)) {
            return $fail('Lien YouTube reconnu, mais l\'identifiant de la vidéo est introuvable.');
        }

        return [
            'ok' => true, 'error' => '', 'provider' => 'youtube', 'external_id' => $id,
            'embed_url' => 'https://www.youtube-nocookie.com/embed/' . $id . '?rel=0',
            'thumbnail_url' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
            'normalized' => 'https://www.youtube.com/watch?v=' . $id,
        ];
    }

    // Vimeo
    if (in_array($host, ['vimeo.com', 'player.vimeo.com'], true)) {
        if (!preg_match('~(?:/video)?/(\d{5,12})~', $path, $m)) {
            return $fail('Lien Vimeo reconnu, mais l\'identifiant de la vidéo est introuvable.');
        }

        return [
            'ok' => true, 'error' => '', 'provider' => 'vimeo', 'external_id' => $m[1],
            'embed_url' => 'https://player.vimeo.com/video/' . $m[1] . '?dnt=1',
            'thumbnail_url' => null,
            'normalized' => 'https://vimeo.com/' . $m[1],
        ];
    }

    // Autres domaines : lien sortant autorisé, intégration non garantie.
    $domains = (array) config('media.external_video_domains', []);
    $known   = false;
    foreach ($domains as $domain) {
        if ($host === strtolower((string) $domain)) {
            $known = true;
            break;
        }
    }

    return [
        'ok' => true, 'error' => '', 'provider' => $known ? 'external' : 'link',
        'external_id' => null, 'embed_url' => null, 'thumbnail_url' => null,
        'normalized' => $normalized,
    ];
}

/* -------------------------------------------------------------------------
 *  Diffusion protégée
 * ---------------------------------------------------------------------- */

function media_absolute_path(array $media, bool $thumb = false): ?string
{
    $relative = $thumb
        ? ($media['thumbnail_path'] ?? null)
        : ($media['file_path'] ?? null);

    if (!is_string($relative) || $relative === '') {
        return null;
    }

    $base = realpath(uploads_dir());
    if ($base === false) {
        return null;
    }

    $full = realpath($base . '/' . ltrim($relative, '/\\'));
    if ($full === false || !str_starts_with($full, $base . DIRECTORY_SEPARATOR)) {
        return null;
    }

    return is_file($full) ? $full : null;
}

function media_mime(array $media): string
{
    $mime = (string) ($media['mime_type'] ?? '');

    if ($mime !== '') {
        return $mime;
    }

    $map = ['image' => 'image/jpeg', 'video' => 'video/mp4', 'audio' => 'audio/mpeg'];

    return $map[$media['kind'] ?? 'image'] ?? 'application/octet-stream';
}

/**
 * L'utilisateur connecté peut-il accéder à ce média ?
 */
function user_can_access_media(array $media): bool
{
    if ((int) ($media['is_protected'] ?? 1) !== 1) {
        return true;
    }

    return can_view_content();
}

function media_url(array $media, bool $thumb = false): string
{
    if ($thumb && !media_has_thumbnail($media)) {
        return '';
    }

    if (($media['source'] ?? 'local') === 'external') {
        if ($thumb) {
            $parsed = parse_external_video_url((string) ($media['external_url'] ?? ''));
            if (!empty($parsed['thumbnail_url'])) {
                return (string) $parsed['thumbnail_url'];
            }
            if (!empty($media['thumbnail_path'])) {
                return app_url('media.php') . '?id=' . (int) $media['id'] . '&variant=thumb';
            }

            return '';
        }

        return (string) ($media['external_url'] ?? '');
    }

    $url = app_url('media.php') . '?id=' . (int) $media['id'];
    if ($thumb) {
        $url .= '&variant=thumb';
    }

    return $url;
}

function media_embed_url(array $media): ?string
{
    if (($media['source'] ?? 'local') !== 'external') {
        return null;
    }

    $parsed = parse_external_video_url((string) ($media['external_url'] ?? ''));

    return $parsed['ok'] ? $parsed['embed_url'] : null;
}

function media_has_thumbnail(array $media): bool
{
    if (!empty($media['thumbnail_path'])) {
        return true;
    }

    if (($media['source'] ?? 'local') === 'external') {
        $parsed = parse_external_video_url((string) ($media['external_url'] ?? ''));

        return !empty($parsed['thumbnail_url']);
    }

    return false;
}

/**
 * Diffuse un fichier avec support des requêtes HTTP Range (vidéo/audio).
 */
function stream_media_file(string $path, string $mime, string $filename, int $cacheSeconds = 0): void
{
    if (!is_file($path) || !is_readable($path)) {
        abort(404, 'Le fichier demandé est introuvable.');
    }

    $size  = (int) filesize($path);
    $start = 0;
    $end   = $size - 1;
    $status = 200;

    $range = $_SERVER['HTTP_RANGE'] ?? '';
    if (is_string($range) && preg_match('/bytes=(\d*)-(\d*)/', $range, $m)) {
        if ($m[1] === '' && $m[2] !== '') {
            $suffix = (int) $m[2];
            $start  = max(0, $size - $suffix);
        } else {
            $start = (int) $m[1];
            if ($m[2] !== '') {
                $end = min((int) $m[2], $size - 1);
            }
        }

        if ($start > $end || $start >= $size) {
            header('Content-Range: bytes */' . $size);
            http_response_code(416);
            exit;
        }

        $status = 206;
    }

    $length = $end - $start + 1;

    if (session_status() === PHP_SESSION_ACTIVE) {
        session_write_close();
    }

    while (ob_get_level() > 0) {
        ob_end_clean();
    }

    $safeName = preg_replace('/[^A-Za-z0-9._-]+/', '-', $filename) ?: 'media';

    http_response_code($status);
    header('Content-Type: ' . $mime);
    header('Content-Length: ' . $length);
    header('Accept-Ranges: bytes');
    header('Content-Disposition: inline; filename="' . $safeName . '"');
    header('X-Content-Type-Options: nosniff');
    header('Content-Security-Policy: default-src \'none\'; sandbox');
    header('Cache-Control: private, max-age=' . max(0, $cacheSeconds) . ', no-transform');

    if ($status === 206) {
        header('Content-Range: bytes ' . $start . '-' . $end . '/' . $size);
    }

    $handle = fopen($path, 'rb');
    if ($handle === false) {
        exit;
    }

    fseek($handle, $start);
    $remaining = $length;

    while ($remaining > 0 && !feof($handle)) {
        $chunk = fread($handle, min(65536, $remaining));
        if ($chunk === false) {
            break;
        }
        echo $chunk;
        $remaining -= strlen($chunk);
        if (connection_aborted()) {
            break;
        }
    }

    fclose($handle);
    exit;
}

/* -------------------------------------------------------------------------
 *  Suppression
 * ---------------------------------------------------------------------- */

function delete_media_files(array $media): void
{
    foreach (['file_path', 'thumbnail_path'] as $field) {
        $relative = $media[$field] ?? null;
        if (!is_string($relative) || $relative === '') {
            continue;
        }

        $base = realpath(uploads_dir());
        if ($base === false) {
            continue;
        }

        $full = realpath($base . '/' . ltrim($relative, '/\\'));
        if ($full !== false && str_starts_with($full, $base . DIRECTORY_SEPARATOR) && is_file($full)) {
            @unlink($full);
        }
    }
}

/**
 * @return array{ok:bool,error:string}
 */
function delete_media_record(int $id): array
{
    $media = db_fetch('SELECT * FROM media WHERE id = ? LIMIT 1', [$id]);
    if (!$media) {
        return ['ok' => false, 'error' => 'Ce média n\'existe pas.'];
    }

    $used = (int) db_value('SELECT COUNT(*) FROM memories WHERE media_id = ?', [$id]);
    if ($used > 0) {
        return [
            'ok' => false,
            'error' => 'Ce média est utilisé par ' . $used . ' ' . plural_fr($used, 'souvenir', 'souvenirs')
                . '. Modifie ou supprime d\'abord ' . ($used > 1 ? 'ces souvenirs' : 'ce souvenir') . '.',
        ];
    }

    db_query('DELETE FROM media WHERE id = ?', [$id]);
    delete_media_files($media);

    return ['ok' => true, 'error' => ''];
}

/* -------------------------------------------------------------------------
 *  Playlists
 * ---------------------------------------------------------------------- */

function playlist_by_slug(string $slug): ?array
{
    return db_fetch('SELECT * FROM playlists WHERE slug = ? LIMIT 1', [$slug]);
}

/**
 * Pistes actives d'une playlist, filtrées selon les droits du visiteur.
 *
 * @return array<int,array>
 */
function playlist_tracks(string $slug, bool $onlyAccessible = true): array
{
    $rows = db_fetch_all(
        'SELECT pt.id AS track_id, pt.position, pt.is_active AS track_active, m.*
         FROM playlist_tracks pt
         INNER JOIN playlists p ON p.id = pt.playlist_id
         INNER JOIN media m ON m.id = pt.media_id
         WHERE p.slug = ? AND p.is_active = 1 AND pt.is_active = 1 AND m.kind = \'audio\'
         ORDER BY pt.position ASC, pt.id ASC',
        [$slug]
    );

    $tracks = [];
    foreach ($rows as $row) {
        if ($onlyAccessible && !user_can_access_media($row)) {
            continue;
        }

        $tracks[] = [
            'id'       => (int) $row['id'],
            'title'    => (string) $row['title'],
            'artist'   => (string) ($row['artist'] ?? ''),
            'url'      => media_url($row),
            'protected'=> (int) $row['is_protected'] === 1,
        ];
    }

    return $tracks;
}

function playlist_tracks_count(int $playlistId, bool $activeOnly = true): int
{
    $sql = 'SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id = ?';
    if ($activeOnly) {
        $sql .= ' AND is_active = 1';
    }

    return (int) db_value($sql, [$playlistId]);
}

function playlist_max_tracks(): int
{
    return max(1, (int) config('app.playlist_max_tracks', 5));
}

/* -------------------------------------------------------------------------
 *  Statistiques
 * ---------------------------------------------------------------------- */

function media_count_by_kind(string $kind): int
{
    return (int) db_value('SELECT COUNT(*) FROM media WHERE kind = ?', [$kind]);
}
