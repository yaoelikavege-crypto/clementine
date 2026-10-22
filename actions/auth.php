<?php
/**
 * Traitement de la connexion (POST uniquement).
 * Le type de profil choisi n'est qu'une préférence d'interface :
 * les droits réels viennent du compte authentifié côté serveur.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_post();
require_csrf();

$type = post_string('type');

if ($type === 'admin') {
    $result = auth_admin_login(post_string('username'), (string) ($_POST['password'] ?? ''));

    if ($result['ok']) {
        flash('success', 'Bienvenue dans l\'administration.');
        redirect(app_url('admin/dashboard.php'));
    }

    flash('error', $result['error']);
    redirect(app_url('login.php?type=admin'));
}

if ($type === 'visitor') {
    $result = auth_visitor_login((string) ($_POST['password'] ?? ''));

    if ($result['ok']) {
        flash('success', 'Bienvenue, Clémentine.');

        if (is_revealed()) {
            redirect(app_url('pages/memories.php'));
        }

        redirect(app_url('pages/countdown.php'));
    }

    flash('error', $result['error']);
    redirect(app_url('login.php?type=visitor'));
}

flash('error', 'Choisis un profil pour te connecter.');
redirect(app_url('login.php'));
