<?php
/**
 * Gestion des vœux d'anniversaire (ajout, modification, publication, suppression).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();
require_post();
require_csrf();

$action = post_string('action');
$id     = post_int('id');

function wish_validate(): array
{
    $author  = post_string('author');
    $message = post_string('message');
    $sort    = post_int('sort_order', 0);
    $errors  = [];

    if (mb_strlen($author) < 2 || mb_strlen($author) > 100) {
        $errors[] = 'L\'auteur doit contenir entre 2 et 100 caractères.';
    }

    if (mb_strlen($message) < 5 || mb_strlen($message) > 1500) {
        $errors[] = 'Le message doit contenir entre 5 et 1 500 caractères.';
    }

    return [$author, $message, max(0, min(9999, $sort)), $errors];
}

if ($action === 'create' || $action === 'update') {
    [$author, $message, $sort, $errors] = wish_validate();
    $published = checked('is_published') ? 1 : 0;

    if ($action === 'update' && $id <= 0) {
        $errors[] = 'Vœu introuvable.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        redirect(app_url('admin/wishes.php' . ($action === 'update' ? '?edit=' . $id : '')));
    }

    if ($action === 'create') {
        db_query(
            'INSERT INTO wishes (author, message, sort_order, is_published, created_at) VALUES (?, ?, ?, ?, ?)',
            [$author, $message, $sort, $published, now()->format('Y-m-d H:i:s')]
        );
        audit('voeu_cree', 'Vœu de ' . $author);
        flash('success', 'Le vœu a été ajouté.');
    } else {
        $exists = db_value('SELECT COUNT(*) FROM wishes WHERE id = ?', [$id]);
        if ((int) $exists === 0) {
            flash('error', 'Ce vœu n\'existe pas.');
            redirect(app_url('admin/wishes.php'));
        }

        db_query(
            'UPDATE wishes SET author = ?, message = ?, sort_order = ?, is_published = ?, updated_at = ? WHERE id = ?',
            [$author, $message, $sort, $published, now()->format('Y-m-d H:i:s'), $id]
        );
        audit('voeu_modifie', 'Vœu #' . $id . ' de ' . $author);
        flash('success', 'Le vœu a été mis à jour.');
    }

    redirect(app_url('admin/wishes.php'));
}

if ($action === 'delete') {
    if ($id <= 0 || !checked('confirm_delete')) {
        flash('error', 'Suppression non confirmée.');
        redirect(app_url('admin/wishes.php'));
    }

    $wish = db_fetch('SELECT author FROM wishes WHERE id = ?', [$id]);
    if (!$wish) {
        flash('error', 'Ce vœu n\'existe pas.');
        redirect(app_url('admin/wishes.php'));
    }

    db_query('DELETE FROM wishes WHERE id = ?', [$id]);
    audit('voeu_supprime', 'Vœu #' . $id . ' de ' . $wish['author']);
    flash('success', 'Le vœu a été supprimé.');
    redirect(app_url('admin/wishes.php'));
}

if ($action === 'toggle') {
    $wish = db_fetch('SELECT is_published FROM wishes WHERE id = ?', [$id]);
    if (!$wish) {
        flash('error', 'Ce vœu n\'existe pas.');
        redirect(app_url('admin/wishes.php'));
    }

    $newState = (int) $wish['is_published'] === 1 ? 0 : 1;
    db_query('UPDATE wishes SET is_published = ?, updated_at = ? WHERE id = ?', [$newState, now()->format('Y-m-d H:i:s'), $id]);
    audit('voeu_visibilite', 'Vœu #' . $id . ' → ' . ($newState ? 'publié' : 'masqué'));
    flash('success', $newState ? 'Le vœu est maintenant visible.' : 'Le vœu est désormais masqué.');
    redirect(app_url('admin/wishes.php'));
}

flash('error', 'Action inconnue.');
redirect(app_url('admin/wishes.php'));
