<?php
/**
 * Gestion des mots de passe :
 *  - le compte administrateur connecté modifie son propre mot de passe ;
 *  - il peut réinitialiser l'autre administrateur (mot de passe temporaire) ;
 *  - il peut modifier le mot de passe commun des visiteurs.
 *
 * Aucun mot de passe n'est journalisé ni affiché en clair, hormis le mot de
 * passe temporaire généré, montré une seule fois dans le message de retour.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();
require_post();
require_csrf();

$action = post_string('action');
$me     = current_user();
$meId   = (int) ($me['id'] ?? 0);

$myRow = db_fetch('SELECT * FROM admins WHERE id = ?', [$meId]);
if (!$myRow) {
    flash('error', 'Compte administrateur introuvable.');
    redirect(app_url('admin/credentials.php'));
}

if ($action === 'change_own') {
    $current = (string) ($_POST['current_password'] ?? '');
    $new     = (string) ($_POST['new_password'] ?? '');
    $confirm = (string) ($_POST['new_password_confirm'] ?? '');

    if (!password_verify($current, $myRow['password_hash'])) {
        flash('error', 'Le mot de passe actuel est incorrect.');
        redirect(app_url('admin/credentials.php'));
    }

    if ($new !== $confirm) {
        flash('error', 'Les deux nouveaux mots de passe ne correspondent pas.');
        redirect(app_url('admin/credentials.php'));
    }

    if ($new === $current) {
        flash('error', 'Le nouveau mot de passe doit être différent de l\'ancien.');
        redirect(app_url('admin/credentials.php'));
    }

    $policy = password_policy_error($new, 'admin', (string) $myRow['username']);
    if ($policy !== null) {
        flash('error', $policy);
        redirect(app_url('admin/credentials.php'));
    }

    set_admin_password($meId, $new);
    session_regenerate_id(true);
    audit('mot_de_passe_admin', 'Modification de son propre mot de passe');
    flash('success', 'Ton mot de passe a été modifié. Les autres sessions de ton compte restent ouvertes jusqu\'à expiration.');
    redirect(app_url('admin/credentials.php'));
}

if ($action === 'reset_other') {
    $otherId = post_int('other_id');
    $own     = (string) ($_POST['own_password'] ?? '');

    if (!password_verify($own, $myRow['password_hash'])) {
        flash('error', 'Confirme ton propre mot de passe pour réinitialiser l\'autre compte.');
        redirect(app_url('admin/credentials.php'));
    }

    if ($otherId === $meId || $otherId <= 0) {
        flash('error', 'Compte cible invalide.');
        redirect(app_url('admin/credentials.php'));
    }

    $other = db_fetch('SELECT * FROM admins WHERE id = ?', [$otherId]);
    if (!$other) {
        flash('error', 'L\'autre compte administrateur est introuvable.');
        redirect(app_url('admin/credentials.php'));
    }

    $temporary = 'Nvx-' . bin2hex(random_bytes(4)) . '-' . random_int(1000, 9999) . '!a';

    set_admin_password($otherId, $temporary);

    audit('mot_de_passe_admin_reinitialise', 'Réinitialisation du compte ' . $other['username']);
    flash('success', 'Nouveau mot de passe temporaire pour « ' . $other['username'] . ' » : ' . $temporary . ' — note-le maintenant, il ne sera plus affiché. Demande à cette personne de le changer dès sa prochaine connexion.');
    redirect(app_url('admin/credentials.php'));
}

if ($action === 'change_visitor') {
    $new     = (string) ($_POST['visitor_password'] ?? '');
    $confirm = (string) ($_POST['visitor_password_confirm'] ?? '');

    if ($new !== $confirm) {
        flash('error', 'Les deux mots de passe visiteurs ne correspondent pas.');
        redirect(app_url('admin/credentials.php'));
    }

    $policy = password_policy_error($new, 'visitor');
    if ($policy !== null) {
        flash('error', $policy);
        redirect(app_url('admin/credentials.php'));
    }

    set_visitor_password($new);
    audit('mot_de_passe_visiteur', 'Modification du mot de passe commun des visiteurs');
    flash('success', 'Le mot de passe commun des visiteurs a été modifié. Pense à le transmettre à Clémentine.');
    redirect(app_url('admin/credentials.php'));
}

if ($action === 'update_profile' || $action === 'update_other') {
    $targetId = $action === 'update_other' ? post_int('other_id') : $meId;

    if ($targetId <= 0) {
        flash('error', 'Compte cible invalide.');
        redirect(app_url('admin/credentials.php'));
    }

    $target = db_fetch('SELECT * FROM admins WHERE id = ?', [$targetId]);
    if (!$target) {
        flash('error', 'Compte administrateur introuvable.');
        redirect(app_url('admin/credentials.php'));
    }

    $display = trim(post_string('display_name'));

    if (mb_strlen($display) < 2 || mb_strlen($display) > 100) {
        flash('error', 'Le nom affiché doit compter entre 2 et 100 caractères.');
        redirect(app_url('admin/credentials.php'));
    }

    if ($action === 'update_other') {
        db_query('UPDATE admins SET display_name = ?, updated_at = ? WHERE id = ?', [
            $display,
            now()->format('Y-m-d H:i:s'),
            $targetId,
        ]);
        audit('profil_admin', 'Nom affiché du compte ' . $target['username'] . ' modifié');
        flash('success', 'Le nom affiché de « ' . $target['username'] . ' » a été mis à jour.');
        redirect(app_url('admin/credentials.php'));
    }

    // update_profile : identifiant + nom affiché du compte connecté.
    $username = mb_strtolower(trim(post_string('username')));

    if (!preg_match('/^[a-z0-9._-]{3,50}$/', $username)) {
        flash('error', "L'identifiant doit compter 3 à 50 caractères : minuscules sans accent, chiffres, point, tiret ou underscore.");
        redirect(app_url('admin/credentials.php'));
    }

    if ($username !== $target['username']) {
        $taken = (int) db_value('SELECT COUNT(*) FROM admins WHERE username = ? AND id <> ?', [$username, $targetId]);
        if ($taken > 0) {
            flash('error', "Cet identifiant est déjà utilisé par l'autre administrateur.");
            redirect(app_url('admin/credentials.php'));
        }
    }

    db_query('UPDATE admins SET username = ?, display_name = ?, updated_at = ? WHERE id = ?', [
        $username,
        $display,
        now()->format('Y-m-d H:i:s'),
        $targetId,
    ]);

    // La session porte une copie de l'identité : on la synchronise.
    $_SESSION['user']['username']     = $username;
    $_SESSION['user']['display_name'] = $display;
    refresh_default_password_flag();
    audit('profil_admin', 'Profil personnel mis à jour');
    flash('success', 'Ton profil a été mis à jour.');
    redirect(app_url('admin/credentials.php'));
}

flash('error', 'Action inconnue.');
redirect(app_url('admin/credentials.php'));
