<?php
/**
 * Messages et indices de la période d'attente.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();
require_post();
require_csrf();

$action = post_string('action');
$id     = post_int('id');

if ($action === 'create' || $action === 'update') {
    $title    = post_string('title');
    $message  = post_string('message');
    $revealAt = post_string('reveal_at');
    $sort     = post_int('sort_order', 0);
    $active   = checked('is_active') ? 1 : 0;
    $errors   = [];

    if (mb_strlen($title) > 120) {
        $errors[] = 'Le titre ne doit pas dépasser 120 caractères.';
    }

    if (mb_strlen($message) < 5 || mb_strlen($message) > 1200) {
        $errors[] = 'Le message doit contenir entre 5 et 1 200 caractères.';
    }

    $revealSql = null;
    if ($revealAt !== '') {
        $revealSql = parse_datetime_input($revealAt);
        if ($revealSql === null) {
            $errors[] = 'La date de dévoilement est invalide.';
        }
    }

    if ($action === 'update' && $id <= 0) {
        $errors[] = 'Message introuvable.';
    }

    if ($errors) {
        foreach ($errors as $error) {
            flash('error', $error);
        }
        redirect(app_url('admin/countdown.php' . ($action === 'update' ? '?edit=' . $id : '')));
    }

    if ($action === 'create') {
        db_query(
            'INSERT INTO countdown_messages (title, message, reveal_at, is_active, sort_order, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [$title !== '' ? $title : null, $message, $revealSql, $active, max(0, min(9999, $sort)), now()->format('Y-m-d H:i:s')]
        );
        audit('message_cree', 'Message de compte à rebours');
        flash('success', 'Le message a été ajouté.');
    } else {
        $exists = db_value('SELECT COUNT(*) FROM countdown_messages WHERE id = ?', [$id]);
        if ((int) $exists === 0) {
            flash('error', 'Ce message n\'existe pas.');
            redirect(app_url('admin/countdown.php'));
        }

        db_query(
            'UPDATE countdown_messages SET title = ?, message = ?, reveal_at = ?, is_active = ?, sort_order = ? WHERE id = ?',
            [$title !== '' ? $title : null, $message, $revealSql, $active, max(0, min(9999, $sort)), $id]
        );
        audit('message_modifie', 'Message #' . $id);
        flash('success', 'Le message a été mis à jour.');
    }

    redirect(app_url('admin/countdown.php'));
}

if ($action === 'delete') {
    if ($id <= 0 || !checked('confirm_delete')) {
        flash('error', 'Suppression non confirmée.');
        redirect(app_url('admin/countdown.php'));
    }

    $exists = db_value('SELECT COUNT(*) FROM countdown_messages WHERE id = ?', [$id]);
    if ((int) $exists === 0) {
        flash('error', 'Ce message n\'existe pas.');
        redirect(app_url('admin/countdown.php'));
    }

    db_query('DELETE FROM countdown_messages WHERE id = ?', [$id]);
    audit('message_supprime', 'Message #' . $id);
    flash('success', 'Le message a été supprimé.');
    redirect(app_url('admin/countdown.php'));
}

if ($action === 'toggle') {
    $msg = db_fetch('SELECT is_active FROM countdown_messages WHERE id = ?', [$id]);
    if (!$msg) {
        flash('error', 'Ce message n\'existe pas.');
        redirect(app_url('admin/countdown.php'));
    }

    $newState = (int) $msg['is_active'] === 1 ? 0 : 1;
    db_query('UPDATE countdown_messages SET is_active = ? WHERE id = ?', [$newState, $id]);
    audit('message_visibilite', 'Message #' . $id . ' → ' . ($newState ? 'actif' : 'inactif'));
    flash('success', $newState ? 'Message activé.' : 'Message désactivé.');
    redirect(app_url('admin/countdown.php'));
}

flash('error', 'Action inconnue.');
redirect(app_url('admin/countdown.php'));
