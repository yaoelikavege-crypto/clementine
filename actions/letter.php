<?php
/**
 * Enregistrement de la lettre d'amour.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();
require_post();
require_csrf();

$title     = post_string('title');
$body      = post_string('body');
$signature = post_string('signature', 'Joël');
$published = checked('is_published') ? 1 : 0;

$errors = [];

if (mb_strlen($title) < 2 || mb_strlen($title) > 150) {
    $errors[] = 'Le titre doit contenir entre 2 et 150 caractères.';
}

if (mb_strlen($body) < 20) {
    $errors[] = 'La lettre est trop courte (20 caractères minimum).';
} elseif (mb_strlen($body) > 20000) {
    $errors[] = 'La lettre ne doit pas dépasser 20 000 caractères.';
}

if (mb_strlen($signature) < 1 || mb_strlen($signature) > 100) {
    $errors[] = 'La signature doit contenir entre 1 et 100 caractères.';
}

if ($errors) {
    foreach ($errors as $error) {
        flash('error', $error);
    }
    redirect(app_url('admin/letter.php'));
}

$user      = current_user();
$adminId   = (int) ($user['id'] ?? 0);
$timestamp = now()->format('Y-m-d H:i:s');

$exists = (int) db_value('SELECT COUNT(*) FROM love_letter WHERE id = 1') > 0;

if ($exists) {
    db_query(
        'UPDATE love_letter SET title = ?, body = ?, signature = ?, is_published = ?, updated_by = ?, updated_at = ? WHERE id = 1',
        [$title, $body, $signature, $published, $adminId, $timestamp]
    );
} else {
    db_query(
        'INSERT INTO love_letter (id, title, body, signature, is_published, updated_by, updated_at) VALUES (1, ?, ?, ?, ?, ?, ?)',
        [$title, $body, $signature, $published, $adminId, $timestamp]
    );
}

audit('lettre_mise_a_jour', 'Lettre « ' . $title . ' » — ' . ($published ? 'publiée' : 'brouillon'));
flash('success', 'La lettre a été enregistrée.');

redirect(app_url('admin/letter.php'));
