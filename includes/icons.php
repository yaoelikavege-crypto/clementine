<?php
/**
 * Jeu d'icônes SVG embarquées (aucune dépendance externe, aucun appel réseau).
 * Usage : icon('heart', 20, 'ma-classe')
 */

function icon(string $name, int $size = 20, string $class = ''): string
{
    static $icons = [
        'home'         => '<path d="M3 9.5 12 3l9 6.5V21a1 1 0 0 1-1 1h-5v-7H9v7H4a1 1 0 0 1-1-1z"/>',
        'image'        => '<rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.6"/><path d="m21 15-4.5-4.5L5 21"/>',
        'video'        => '<rect x="2" y="5" width="14" height="14" rx="3"/><path d="m16 10 6-3v10l-6-3z"/>',
        'music'        => '<path d="M9 18V6l11-2v12"/><circle cx="6" cy="18" r="3"/><circle cx="17" cy="16" r="3"/>',
        'playlist'     => '<path d="M3 6h10M3 12h10M3 18h6"/><path d="M17 5v11"/><circle cx="19" cy="17" r="2.5"/>',
        'letter'       => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
        'sparkles'     => '<path d="M11 3.5 12.6 8 17 9.6 12.6 11.2 11 15.7 9.4 11.2 5 9.6 9.4 8z"/><path d="M18 14l.9 2.1L21 17l-2.1.9L18 20l-.9-2.1L15 17l2.1-.9z"/>',
        'gift'         => '<rect x="3" y="8" width="18" height="13" rx="1.5"/><path d="M12 8v13M3 12.5h18"/><path d="M12 8S9.5 3 7.2 3.8 6 8 8.8 8zM12 8s2.5-5 4.8-4.2S18 8 15.2 8z"/>',
        'lock'         => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.8a4 4 0 0 1 8 0v2.7"/>',
        'unlock'       => '<rect x="4.5" y="10.5" width="15" height="10" rx="2"/><path d="M8 10.5V7.8a4 4 0 0 1 7.4-2"/>',
        'sliders'      => '<path d="M4 6h9M17 6h3M4 12h5M13 12h7M4 18h11M19 18h1"/><circle cx="15" cy="6" r="2"/><circle cx="11" cy="12" r="2"/><circle cx="17" cy="18" r="2"/>',
        'key'          => '<circle cx="8" cy="15" r="4"/><path d="m11 12 9-9M17 3h4v4M14 6l3 3"/>',
        'logout'       => '<path d="M15 4h3a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-3"/><path d="m10 17 5-5-5-5M15 12H3"/>',
        'grid'         => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'calendar'     => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M8 3v4M16 3v4M3 10h18"/>',
        'message'      => '<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.5L3 21l2-5.4A8.5 8.5 0 1 1 21 11.5z"/>',
        'shield'       => '<path d="M12 3l8 3v6c0 5-3.4 8.2-8 9-4.6-.8-8-4-8-9V6z"/><path d="m8.8 12 2.2 2.2 4.2-4.4"/>',
        'users'        => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><path d="M16 5.4a3.2 3.2 0 0 1 0 5.2M17.5 14.8c2.1.6 3.5 2.4 3.5 5.2"/>',
        'user'         => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
        'plus'         => '<path d="M12 5v14M5 12h14"/>',
        'edit'         => '<path d="M4 20h4L20 8a2.8 2.8 0 0 0-4-4L4 16z"/><path d="m14.5 5.5 4 4"/>',
        'trash'        => '<path d="M4 7h16M9 7V4h6v3M6 7l1 13h10l1-13"/><path d="M10 11v5M14 11v5"/>',
        'eye'          => '<path d="M2.5 12S6 5.5 12 5.5 21.5 12 21.5 12 18 18.5 12 18.5 2.5 12 2.5 12z"/><circle cx="12" cy="12" r="3"/>',
        'eye-off'      => '<path d="M2.5 12S6 5.5 12 5.5c1.8 0 3.4.7 4.8 1.6M21.5 12S18 18.5 12 18.5c-1.8 0-3.4-.7-4.8-1.6"/><path d="m4 4 16 16"/><path d="M9.9 9.9a3 3 0 0 0 4.2 4.2"/>',
        'play'         => '<path d="M8 5v14l11-7z" fill="currentColor" stroke="none"/>',
        'pause'        => '<path d="M8 5v14M16 5v14" stroke-width="3"/>',
        'prev'         => '<path d="M19 6v12l-9-6z" fill="currentColor" stroke="none"/><path d="M6 5v14"/>',
        'next'         => '<path d="M5 6v12l9-6z" fill="currentColor" stroke="none"/><path d="M18 5v14"/>',
        'volume'       => '<path d="M4 10v4h4l5 4V6L8 10z"/><path d="M16.5 8.5a5 5 0 0 1 0 7M19 6a8.5 8.5 0 0 1 0 12"/>',
        'volume-x'     => '<path d="M4 10v4h4l5 4V6L8 10z"/><path d="m16.5 9.5 5 5M21.5 9.5l-5 5"/>',
        'x'            => '<path d="m5 5 14 14M19 5 5 19"/>',
        'arrow-left'   => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'arrow-right'  => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'chevron-left' => '<path d="m15 5-7 7 7 7"/>',
        'chevron-right'=> '<path d="m9 5 7 7-7 7"/>',
        'chevron-down' => '<path d="m5 9 7 7 7-7"/>',
        'arrow-up'     => '<path d="M12 19V5M6 11l6-6 6 6"/>',
        'arrow-down'   => '<path d="M12 5v14M18 13l-6 6-6-6"/>',
        'check'        => '<path d="m5 13 4 4L19 7"/>',
        'check-circle' => '<circle cx="12" cy="12" r="9"/><path d="m8 12 3 3 5-6"/>',
        'alert-triangle' => '<path d="M12 3 2.5 20h19z"/><path d="M12 9v5M12 17h.01"/>',
        'info'         => '<circle cx="12" cy="12" r="9"/><path d="M12 11v5M12 8h.01"/>',
        'external-link'=> '<path d="M14 4h6v6M20 4 11 13"/><path d="M20 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V5a1 1 0 0 1 1-1h5"/>',
        'upload'       => '<path d="M12 16V4M7 9l5-5 5 5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'download'     => '<path d="M12 4v12M7 11l5 5 5-5"/><path d="M4 16v3a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-3"/>',
        'search'       => '<circle cx="11" cy="11" r="7"/><path d="m20 20-3.5-3.5"/>',
        'filter'       => '<path d="M3 5h18l-7 8v6l-4 2v-8z"/>',
        'refresh'      => '<path d="M20 6v5h-5"/><path d="M4 18v-5h5"/><path d="M5.5 9a7 7 0 0 1 11.8-2.5L20 11M4 13l2.7 4.5A7 7 0 0 0 18.5 15"/>',
        'heart'        => '<path d="M12 20s-7-4.4-7-9a4 4 0 0 1 7-2.6A4 4 0 0 1 19 11c0 4.6-7 9-7 9z"/>',
        'star'         => '<path d="m12 3 2.7 5.6 6.1.8-4.5 4.3 1.2 6L12 16.8 6.5 19.7l1.2-6-4.5-4.3 6.1-.8z"/>',
        'menu'         => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'clock'        => '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
        'whatsapp'     => '<path d="M21 11.5a8.5 8.5 0 0 1-12.6 7.5L3 21l2-5.4A8.5 8.5 0 1 1 21 11.5z"/><path d="M8.5 8.8c0 3 2.7 5.7 5.7 5.7l1.3-1.3-2-1.2-1 .6c-.9-.4-1.7-1.2-2.1-2.1l.6-1-1.2-2z"/>',
        'log-in'       => '<path d="M14 4h4a2 2 0 0 1 2 2v12a2 2 0 0 1-2 2h-4"/><path d="m9 16 4-4-4-4M13 12H3"/>',
        'image-off'    => '<path d="M3 3l18 18"/><path d="M21 15V5a2 2 0 0 0-2-2H8"/><path d="M3 16V5a2 2 0 0 1 2-2"/><path d="M3 21h14a2 2 0 0 0 2-2"/><path d="m5 21 5-6"/>',
        'send'         => '<path d="M21 3 3 10.5l7 3 3 7z"/><path d="M21 3 10 13.5"/>',
        'circle'       => '<circle cx="12" cy="12" r="9"/>',
    ];

    $path = $icons[$name] ?? $icons['circle'];
    $attr = $class !== '' ? ' class="' . e($class) . '"' : '';

    return '<svg' . $attr . ' width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . $path . '</svg>';
}
