<?php
/**
 * Souvenirs et galerie : création, modification, ordre, publication, suppression.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();
require_post();
require_csrf();

$action = post_string('action');
$id     = post_int('id');

/**
 * Normalise les positions 1..N selon l'ordre actuel (sort_order, id).
 */
function memories_normalize(): void
{
    $rows = db_fetch_all('SELECT id FROM memories ORDER BY sort_order ASC, id ASC');
    $position = 1;
    foreach ($rows as $row) {
        db_query('UPDATE memories SET sort_order = ? WHERE id = ?', [$position, (int) $row['id']]);
        $position++;
    }
}

if ($action === 'create' || $action === 'update') {
    $title       = post_string('title');
    $description = post_string('description');
    $category    = post_string('category');
    $memoryDate  = post_string('memory_date');
    $mediaId     = post_int('media_id', 0);
    $sort        = post_int('sort_order', 0);
    $published   = checked('is_published') ? 1 : 0;
    $errors      = [];

    if (mb_strlen($title) < 2 || mb_strlen($title) > 150) {
        $errors[] = 'Le titre doit contenir entre 2 et 150 caractères.';
    }

    if (mb_strlen($description) > 3000) {
        $errors[] = 'La description ne doit pas dépasser 3 000 caractères.';
    }

    if (mb_strlen($category) > 80) {
        $errors[] = 'La catégorie ne doit pas dépasser 80 caractères.';
    }

    $dateSql = null;
    if ($memoryDate !== '') {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $memoryDate, site_timezone());
        if (!$date instanceof DateTimeImmutable) {
            $errors[] = 'La date du souvenir est invalide (format attendu : AAAA-MM-JJ).';
        } else {
            $dateSql = $date->format('Y-m-d');
        }
    }

    if ($mediaId > 0) {
        $media = db_fetch('SELECT id, kind FROM media WHERE id = ?', [$mediaId]);
        if (!$media || !in_array($media['kind'], ['image', 'video'], true)) {
            $errors[] = 'Le média sélectionné est introuvable ou n\'est pas une photo/vidéo.';
        }
    }

    if ($action === 'update' && $id <= 0) {
        $errors[] = 'Souvenir introuvable.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        redirect(app_url('admin/memories.php' . ($action === 'update' ? '?edit=' . $id : '')));
    }

    $payload = [
        $title,
        $description !== '' ? $description : null,
        $category !== '' ? $category : null,
        $dateSql,
        $mediaId > 0 ? $mediaId : null,
        max(0, min(9999, $sort)),
        $published,
        now()->format('Y-m-d H:i:s'),
    ];

    if ($action === 'create') {
        db_query(
            'INSERT INTO memories (title, description, category, memory_date, media_id, sort_order, is_published, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            $payload
        );
        audit('souvenir_cree', 'Souvenir « ' . $title . ' »');
        flash('success', 'Le souvenir a été créé.');
    } else {
        $exists = db_value('SELECT COUNT(*) FROM memories WHERE id = ?', [$id]);
        if ((int) $exists === 0) {
            flash('error', 'Ce souvenir n\'existe pas.');
            redirect(app_url('admin/memories.php'));
        }

        db_query(
            'UPDATE memories SET title = ?, description = ?, category = ?, memory_date = ?, media_id = ?, sort_order = ?, is_published = ?, updated_at = ?
             WHERE id = ?',
            array_merge($payload, [$id])
        );
        audit('souvenir_modifie', 'Souvenir #' . $id . ' « ' . $title . ' »');
        flash('success', 'Le souvenir a été mis à jour.');
    }

    redirect(app_url('admin/memories.php'));
}

if ($action === 'delete') {
    if ($id <= 0 || !checked('confirm_delete')) {
        flash('error', 'Suppression non confirmée.');
        redirect(app_url('admin/memories.php'));
    }

    $memory = db_fetch('SELECT title FROM memories WHERE id = ?', [$id]);
    if (!$memory) {
        flash('error', 'Ce souvenir n\'existe pas.');
        redirect(app_url('admin/memories.php'));
    }

    db_query('DELETE FROM memories WHERE id = ?', [$id]);
    audit('souvenir_supprime', 'Souvenir #' . $id . ' « ' . $memory['title'] . ' »');
    flash('success', 'Le souvenir a été supprimé. Le média associé reste dans la bibliothèque.');
    redirect(app_url('admin/memories.php'));
}

if ($action === 'toggle') {
    $memory = db_fetch('SELECT is_published, title FROM memories WHERE id = ?', [$id]);
    if (!$memory) {
        flash('error', 'Ce souvenir n\'existe pas.');
        redirect(app_url('admin/memories.php'));
    }

    $newState = (int) $memory['is_published'] === 1 ? 0 : 1;
    db_query('UPDATE memories SET is_published = ?, updated_at = ? WHERE id = ?', [$newState, now()->format('Y-m-d H:i:s'), $id]);
    audit('souvenir_visibilite', 'Souvenir #' . $id . ' → ' . ($newState ? 'publié' : 'brouillon'));
    flash('success', $newState ? 'Le souvenir est maintenant visible dans la galerie.' : 'Le souvenir est passé en brouillon.');
    redirect(app_url('admin/memories.php'));
}

if ($action === 'move') {
    $direction = post_string('direction');

    if (!in_array($direction, ['up', 'down'], true)) {
        flash('error', 'Direction invalide.');
        redirect(app_url('admin/memories.php'));
    }

    memories_normalize();

    $rows = db_fetch_all('SELECT id FROM memories ORDER BY sort_order ASC, id ASC');
    $ids  = array_map(static fn ($row) => (int) $row['id'], $rows);
    $index = array_search($id, $ids, true);

    if ($index === false) {
        flash('error', 'Souvenir introuvable.');
        redirect(app_url('admin/memories.php'));
    }

    $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

    if ($swapWith < 0 || $swapWith >= count($ids)) {
        flash('info', 'Ce souvenir est déjà à sa place dans l\'ordre.');
        redirect(app_url('admin/memories.php'));
    }

    $a = $ids[$index];
    $b = $ids[$swapWith];

    db_query('UPDATE memories SET sort_order = ? WHERE id = ?', [$swapWith + 1, $a]);
    db_query('UPDATE memories SET sort_order = ? WHERE id = ?', [$index + 1, $b]);

    audit('souvenir_ordre', 'Souvenir #' . $id . ' déplacé (' . $direction . ')');
    flash('success', 'Ordre d\'affichage mis à jour.');
    redirect(app_url('admin/memories.php'));
}

flash('error', 'Action inconnue.');
redirect(app_url('admin/memories.php'));
