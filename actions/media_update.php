<?php
/**
 * Modification ou suppression d'un média existant.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();
require_post();
require_csrf();

$action = post_string('action');
$id     = post_int('id');

$media = $id > 0 ? db_fetch('SELECT * FROM media WHERE id = ? LIMIT 1', [$id]) : null;

if (!$media) {
    flash('error', 'Média introuvable.');
    redirect(app_url('admin/media.php?kind=image'));
}

$back = app_url('admin/media.php?kind=' . $media['kind']);

if ($action === 'toggle') {
    $newState = (int) $media['is_protected'] === 1 ? 0 : 1;
    db_query('UPDATE media SET is_protected = ?, updated_at = ? WHERE id = ?', [$newState, now()->format('Y-m-d H:i:s'), $id]);
    audit('media_protection', 'Média #' . $id . ' → ' . ($newState ? 'protégé' : 'accessible'));
    flash('success', $newState ? 'Ce média est de nouveau protégé jusqu\'à la révélation.' : 'Ce média est désormais accessible sans attendre la révélation.');
    redirect($back);
}

if ($action === 'delete') {
    if (!checked('confirm_delete')) {
        flash('error', 'Suppression non confirmée.');
        redirect($back);
    }

    $result = delete_media_record($id);

    if (!$result['ok']) {
        flash('error', $result['error']);
    } else {
        audit('media_supprime', ucfirst((string) $media['kind']) . ' #' . $id . ' « ' . $media['title'] . ' »');
        flash('success', 'Le média a été supprimé.');
    }

    redirect($back);
}

if ($action === 'update') {
    $title       = post_string('title');
    $description = post_string('description');
    $artist      = post_string('artist');
    $isProtected = post_string('is_protected', '1') === '1' ? 1 : 0;
    $errors      = [];

    if (mb_strlen($title) < 2 || mb_strlen($title) > 150) {
        $errors[] = 'Le titre doit contenir entre 2 et 150 caractères.';
    }

    if (mb_strlen($description) > 2000) {
        $errors[] = 'La description ne doit pas dépasser 2 000 caractères.';
    }

    if (mb_strlen($artist) > 120) {
        $errors[] = 'Le nom de l\'artiste ne doit pas dépasser 120 caractères.';
    }

    $externalUrl = $media['external_url'];

    if ($media['source'] === 'external') {
        $parsed = parse_external_video_url(post_string('external_url'));
        if (!$parsed['ok']) {
            $errors[] = $parsed['error'];
        } else {
            $externalUrl = $parsed['normalized'];
        }
    }

    $thumbnailPath = $media['thumbnail_path'];

    if ($media['kind'] === 'video' && isset($_FILES['thumbnail']) && (int) ($_FILES['thumbnail']['error'] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_NO_FILE) {
        $thumb = store_thumbnail_upload($_FILES['thumbnail']);

        if (!$thumb['ok']) {
            $errors[] = 'Miniature : ' . $thumb['error'];
        } elseif ($thumb['path'] !== null) {
            if (!empty($thumbnailPath)) {
                delete_media_files(['file_path' => null, 'thumbnail_path' => $thumbnailPath]);
            }
            $thumbnailPath = $thumb['path'];
        }
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        redirect(app_url('admin/media.php?kind=' . $media['kind'] . '&edit=' . $id));
    }

    db_query(
        'UPDATE media SET title = ?, description = ?, artist = ?, external_url = ?, thumbnail_path = ?, is_protected = ?, updated_at = ? WHERE id = ?',
        [
            $title,
            $description !== '' ? $description : null,
            $artist !== '' ? $artist : null,
            $externalUrl,
            $thumbnailPath,
            $isProtected,
            now()->format('Y-m-d H:i:s'),
            $id,
        ]
    );

    audit('media_modifie', ucfirst((string) $media['kind']) . ' #' . $id . ' « ' . $title . ' »');
    flash('success', 'Le média a été mis à jour.');

    redirect($back);
}

flash('error', 'Action inconnue.');
redirect($back);
