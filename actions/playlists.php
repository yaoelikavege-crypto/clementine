<?php
/**
 * Gestion des playlists : ajout, retrait, réordonnancement, activation.
 */

require_once __DIR__ . '/../includes/bootstrap.php';

require_admin();
require_post();
require_csrf();

$action     = post_string('action');
$playlistId = post_int('playlist_id');
$trackId    = post_int('track_id');
$mediaId    = post_int('media_id');

/**
 * Réécrit les positions de 1 à N pour une playlist.
 */
function playlist_normalize(int $playlistId): void
{
    $rows = db_fetch_all('SELECT id FROM playlist_tracks WHERE playlist_id = ? ORDER BY position ASC, id ASC', [$playlistId]);
    $position = 1;

    foreach ($rows as $row) {
        db_query('UPDATE playlist_tracks SET position = ? WHERE id = ?', [$position, (int) $row['id']]);
        $position++;
    }
}

$playlist = $playlistId > 0 ? db_fetch('SELECT * FROM playlists WHERE id = ?', [$playlistId]) : null;

if ($action === 'add') {
    if (!$playlist) {
        flash('error', 'Playlist introuvable.');
        redirect(app_url('admin/playlists.php'));
    }

    $media = db_fetch('SELECT * FROM media WHERE id = ? LIMIT 1', [$mediaId]);

    if (!$media || $media['kind'] !== 'audio') {
        flash('error', 'Choisis une piste audio valide.');
        redirect(app_url('admin/playlists.php'));
    }

    $duplicate = (int) db_value('SELECT COUNT(*) FROM playlist_tracks WHERE playlist_id = ? AND media_id = ?', [$playlistId, $mediaId]);
    if ($duplicate > 0) {
        flash('error', 'Cette piste est déjà présente dans la playlist.');
        redirect(app_url('admin/playlists.php'));
    }

    $activeTracks = playlist_tracks_count($playlistId, true);
    $max = playlist_max_tracks();

    if ($activeTracks >= $max) {
        flash('error', 'Cette playlist contient déjà ' . $max . ' pistes actives (maximum). Retire ou désactive une piste avant d\'en ajouter une autre.');
        redirect(app_url('admin/playlists.php'));
    }

    $nextPosition = (int) db_value('SELECT COALESCE(MAX(position), 0) FROM playlist_tracks WHERE playlist_id = ?', [$playlistId]) + 1;

    db_query(
        'INSERT INTO playlist_tracks (playlist_id, media_id, position, is_active, created_at) VALUES (?, ?, ?, 1, ?)',
        [$playlistId, $mediaId, $nextPosition, now()->format('Y-m-d H:i:s')]
    );

    if ($playlist['slug'] === 'countdown' && (int) $media['is_protected'] === 1) {
        flash('warning', 'Attention : cette piste est protégée jusqu\'à la révélation. Elle ne sera pas jouée sur la page publique avant le 23 novembre. Rends-la accessible depuis la bibliothèque musicale si nécessaire.');
    }

    audit('playlist_piste_ajoutee', $playlist['name'] . ' ← « ' . $media['title'] . ' »');
    flash('success', 'Piste ajoutée à la playlist « ' . $playlist['name'] . ' ».');
    redirect(app_url('admin/playlists.php'));
}

if ($action === 'remove') {
    $track = $trackId > 0 ? db_fetch('SELECT * FROM playlist_tracks WHERE id = ?', [$trackId]) : null;

    if (!$track) {
        flash('error', 'Piste introuvable.');
        redirect(app_url('admin/playlists.php'));
    }

    db_query('DELETE FROM playlist_tracks WHERE id = ?', [$trackId]);
    playlist_normalize((int) $track['playlist_id']);
    audit('playlist_piste_retiree', 'Piste #' . $trackId);
    flash('success', 'La piste a été retirée de la playlist.');
    redirect(app_url('admin/playlists.php'));
}

if ($action === 'move') {
    $track = $trackId > 0 ? db_fetch('SELECT * FROM playlist_tracks WHERE id = ?', [$trackId]) : null;
    $direction = post_string('direction');

    if (!$track || !in_array($direction, ['up', 'down'], true)) {
        flash('error', 'Déplacement invalide.');
        redirect(app_url('admin/playlists.php'));
    }

    $playlistId = (int) $track['playlist_id'];
    playlist_normalize($playlistId);

    $rows = db_fetch_all('SELECT id FROM playlist_tracks WHERE playlist_id = ? ORDER BY position ASC, id ASC', [$playlistId]);
    $ids  = array_map(static fn ($row) => (int) $row['id'], $rows);
    $index = array_search($trackId, $ids, true);

    if ($index === false) {
        flash('error', 'Piste introuvable.');
        redirect(app_url('admin/playlists.php'));
    }

    $swapWith = $direction === 'up' ? $index - 1 : $index + 1;

    if ($swapWith < 0 || $swapWith >= count($ids)) {
        flash('info', 'Cette piste est déjà à sa place.');
        redirect(app_url('admin/playlists.php'));
    }

    $a = $ids[$index];
    $b = $ids[$swapWith];

    db_query('UPDATE playlist_tracks SET position = ? WHERE id = ?', [$swapWith + 1, $a]);
    db_query('UPDATE playlist_tracks SET position = ? WHERE id = ?', [$index + 1, $b]);

    audit('playlist_ordre', 'Piste #' . $trackId . ' déplacée (' . $direction . ')');
    flash('success', 'Ordre de la playlist mis à jour.');
    redirect(app_url('admin/playlists.php'));
}

if ($action === 'toggle') {
    $track = $trackId > 0 ? db_fetch('SELECT * FROM playlist_tracks WHERE id = ?', [$trackId]) : null;

    if (!$track) {
        flash('error', 'Piste introuvable.');
        redirect(app_url('admin/playlists.php'));
    }

    $newState = (int) $track['is_active'] === 1 ? 0 : 1;

    if ($newState === 1) {
        $activeTracks = playlist_tracks_count((int) $track['playlist_id'], true);
        if ($activeTracks >= playlist_max_tracks()) {
            flash('error', 'Maximum de ' . playlist_max_tracks() . ' pistes actives atteint pour cette playlist.');
            redirect(app_url('admin/playlists.php'));
        }
    }

    db_query('UPDATE playlist_tracks SET is_active = ? WHERE id = ?', [$newState, $trackId]);
    audit('playlist_piste_visibilite', 'Piste #' . $trackId . ' → ' . ($newState ? 'active' : 'inactive'));
    flash('success', $newState ? 'Piste activée.' : 'Piste désactivée.');
    redirect(app_url('admin/playlists.php'));
}

flash('error', 'Action inconnue.');
redirect(app_url('admin/playlists.php'));
