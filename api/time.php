<?php
/**
 * Heure serveur et état de la révélation.
 * Permet au compte à rebours de rester synchronisé sans exposer
 * d'information sensible ni dépendre de l'horloge de l'appareil.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

header('Cache-Control: no-store');

json_response([
    'now'        => now()->getTimestamp(),
    'now_ms'     => (int) round(microtime(true) * 1000),
    'reveal_at'  => reveal_at()->getTimestamp(),
    'reveal_iso' => reveal_at()->format('c'),
    'revealed'   => is_revealed(),
    'remaining'  => seconds_to_reveal(),
    'timezone'   => (string) config('app.timezone', 'Africa/Lome'),
]);
