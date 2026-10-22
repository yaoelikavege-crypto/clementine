<?php
/**
 * Mise à jour des paramètres généraux (textes du site, maintenance).
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();
require_post();
require_csrf();

$limits = [
    'site_title'         => 120,
    'hero_kicker'        => 120,
    'hero_title'         => 60,
    'hero_message'       => 400,
    'hero_submessage'    => 500,
    'countdown_title'    => 120,
    'countdown_note'     => 400,
    'reveal_title'       => 120,
    'reveal_message'     => 600,
    'gallery_intro'      => 400,
    'gallery_end_cta'    => 250,
    'gallery_categories' => 400,
    'letter_intro'       => 400,
    'finale_invite'      => 300,
    'finale_message'     => 120,
    'finale_note'        => 300,
    'footer_signature'   => 160,
    'maintenance_message'=> 500,
    'visitor_hint'       => 300,
];

$required = ['site_title', 'hero_kicker', 'hero_title', 'countdown_title', 'finale_message'];
$errors   = [];

// 1. Validation : aucun écrit tant que la totalité du formulaire n'est pas valide.
//    (Sinon un seul champ fautif enregistrait quand même tous les autres.)
$values = [];

foreach ($limits as $key => $max) {
    $value = post_string($key);

    if (mb_strlen($value) > $max) {
        $errors[] = 'Le champ « ' . $key . ' » dépasse ' . $max . ' caractères.';
        continue;
    }

    if ($value === '' && in_array($key, $required, true)) {
        $errors[] = 'Le champ « ' . $key . ' » ne peut pas être vide.';
        continue;
    }

    $values[$key] = $value;
}

if ($errors) {
    foreach ($errors as $error) {
        flash('error', $error);
    }

    flash('warning', 'Aucune modification n\'a été enregistrée : corrige les champs signalés puis valide à nouveau.');

    redirect(app_url('admin/settings.php'));
}

// 2. Écriture : tout est valide, on enregistre en une fois.
foreach ($values as $key => $value) {
    setting_set($key, $value);
}

$maintenance = checked('maintenance_mode') ? '1' : '0';
setting_set('maintenance_mode', $maintenance);

flash('success', 'Paramètres enregistrés.');
audit('parametres_mis_a_jour', 'Textes du site et mode maintenance (' . $maintenance . ')');

redirect(app_url('admin/settings.php'));
