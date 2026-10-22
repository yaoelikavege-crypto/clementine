<?php
/**
 * Téléversement d'un média (photo, vidéo locale, vidéo externe, musique).
 *
 * Contrôles : session administrateur, CSRF, taille, type MIME réel,
 * ré-encodage des images, noms aléatoires, stockage hors exécution.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();

$kind = post_string('kind');
$kind = in_array($kind, ['image', 'video', 'audio'], true) ? $kind : '';
$back = app_url('admin/media.php?kind=' . ($kind !== '' ? $kind : 'image'));

// Cas particulier : le corps de la requête dépasse post_max_size,
// PHP vide alors $_POST et $_FILES (le jeton CSRF disparaît avec eux).
if (is_post() && empty($_POST) && empty($_FILES) && (int) ($_SERVER['CONTENT_LENGTH'] ?? 0) > 0) {
    $limits = media_uploader_limits();
    flash('error', 'Le fichier est trop volumineux pour ce serveur (limite PHP : ' . format_bytes($limits['post_max_size']) . '). Compresse-le ou utilise une vidéo externe.');
    redirect($back);
}

require_post();
require_csrf();

if ($kind === '') {
    flash('error', 'Type de média invalide.');
    redirect(app_url('admin/media.php?kind=image'));
}

$title       = post_string('title');
$description = post_string('description');
$artist      = post_string('artist');
$isProtected = post_string('is_protected', $kind === 'audio' ? '0' : '1') === '1' ? 1 : 0;
$errors      = [];

if (mb_strlen($title) < 2 || mb_strlen($title) > 150) {
    $errors[] = 'Le titre doit contenir entre 2 et 150 caractères.';
}

if (mb_strlen($description) > 2000) {
    $errors[] = 'La description ne doit pas dépasser 2 000 caractères.';
}

if ($kind === 'audio' && mb_strlen($artist) > 120) {
    $errors[] = 'Le nom de l\'artiste ne doit pas dépasser 120 caractères.';
}

$source       = 'local';
$filePath     = null;
$thumbnailPath = null;
$externalUrl  = null;
$mime         = null;
$size         = null;
$width        = null;
$height       = null;

if ($kind === 'video' && post_string('video_source', 'local') === 'external') {
    $source  = 'external';
    $rawUrl  = post_string('external_url');
    $parsed  = parse_external_video_url($rawUrl);

    if (!$parsed['ok']) {
        $errors[] = $parsed['error'];
    } else {
        $externalUrl = $parsed['normalized'];
    }
} else {
    $file = $_FILES['file'] ?? null;

    if (!is_array($file) || (int) ($file['error'] ?? UPLOAD_ERR_NO_FILE) === UPLOAD_ERR_NO_FILE) {
        $errors[] = 'Sélectionne un fichier à téléverser.';
    } else {
        $stored = store_uploaded_file($kind, $file);

        if (!$stored['ok']) {
            $errors[] = $stored['error'];
        } else {
            $filePath      = $stored['data']['file_path'];
            $thumbnailPath = $stored['data']['thumbnail_path'] ?? null;
            $mime          = $stored['data']['mime_type'];
            $size          = $stored['data']['file_size'];
            $width         = $stored['data']['width'];
            $height        = $stored['data']['height'];
        }
    }
}

// Miniature facultative (vidéos locales et externes).
if ($kind === 'video' && isset($_FILES['thumbnail']) && (int) ($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
    $thumb = store_thumbnail_upload($_FILES['thumbnail']);

    if (!$thumb['ok']) {
        flash('warning', 'Miniature ignorée : ' . $thumb['error']);
    } elseif ($thumb['path'] !== null) {
        // Si une miniature avait déjà été créée (impossible pour une vidéo locale,
        // mais possible après un précédent envoi), on nettoie l'ancienne.
        if ($thumbnailPath !== null) {
            delete_media_files(['file_path' => null, 'thumbnail_path' => $thumbnailPath]);
        }
        $thumbnailPath = $thumb['path'];
    }
}

if ($errors) {
    // Nettoyage des fichiers déjà stockés pendant cette requête.
    if ($filePath !== null || $thumbnailPath !== null) {
        delete_media_files(['file_path' => $filePath, 'thumbnail_path' => $thumbnailPath]);
    }

    foreach ($errors as $error) {
        flash('error', $error);
    }

    redirect($back);
}

$user    = current_user();
$adminId = (int) ($user['id'] ?? 0);

try {
    db_query(
        'INSERT INTO media
            (kind, source, file_path, external_url, thumbnail_path, title, description, artist,
             mime_type, file_size, width, height, duration_seconds, is_protected, created_by, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NULL, ?, ?, ?)',
        [
            $kind,
            $source,
            $filePath,
            $externalUrl,
            $thumbnailPath,
            $title,
            $description !== '' ? $description : null,
            $artist !== '' ? $artist : null,
            $mime,
            $size,
            $width,
            $height,
            $isProtected,
            $adminId,
            now()->format('Y-m-d H:i:s'),
        ]
    );
} catch (Throwable $e) {
    delete_media_files(['file_path' => $filePath, 'thumbnail_path' => $thumbnailPath]);
    log_event('Insertion média impossible : ' . $e->getMessage(), 'error');
    flash('error', 'Le média n\'a pas pu être enregistré en base de données. Le fichier a été supprimé.');
    redirect($back);
}

audit('media_ajoute', ucfirst($kind) . ' « ' . $title . ' »');
flash('success', 'Média ajouté : « ' . $title . ' ».');

redirect($back);
