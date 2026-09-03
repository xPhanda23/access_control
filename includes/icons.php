<?php
// Ícones SVG inline (contorno, 24x24, herdam a cor do texto via currentColor).
// Substitui o uso de emoji como ícone em todo o site. Uso: chamar icon('home') dentro do HTML.

function icon($name, $class = '') {
    static $paths = [
        'home'        => '<path d="M3 10.5 12 3l9 7.5"/><path d="M5 9.5V21h14V9.5"/><path d="M9 21v-6h6v6"/>',
        'credit-card' => '<rect x="2" y="5" width="20" height="14" rx="2"/><line x1="2" y1="10" x2="22" y2="10"/>',
        'wifi'        => '<path d="M2 8.5a16 16 0 0 1 20 0"/><path d="M5 12a11 11 0 0 1 14 0"/><path d="M8.5 15.5a6 6 0 0 1 7 0"/><circle cx="12" cy="19" r="1" fill="currentColor" stroke="none"/>',
        'users'       => '<circle cx="9" cy="8" r="3.2"/><path d="M3.5 20a5.6 5.6 0 0 1 11 0"/><circle cx="17" cy="9" r="2.6"/><path d="M15.3 13.2a4.7 4.7 0 0 1 5.2 4.6"/>',
        'user'        => '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
        'file-text'   => '<path d="M6 2h9l5 5v15H6z"/><path d="M15 2v5h5"/><line x1="9" y1="13" x2="17" y2="13"/><line x1="9" y1="17" x2="17" y2="17"/>',
        'terminal'    => '<rect x="2" y="4" width="20" height="16" rx="2"/><polyline points="6 9 10 12 6 15"/><line x1="12" y1="15" x2="16" y2="15"/>',
        'log-out'     => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'lock'        => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 10 0v3"/>',
        'unlock'      => '<rect x="4" y="10" width="16" height="11" rx="2"/><path d="M7 10V7a5 5 0 0 1 9.5-2.2"/>',
        'calendar'    => '<rect x="3" y="5" width="18" height="16" rx="2"/><line x1="3" y1="10" x2="21" y2="10"/><line x1="8" y1="3" x2="8" y2="7"/><line x1="16" y1="3" x2="16" y2="7"/>',
        'clock'       => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 16 14"/>',
        'bar-chart'   => '<line x1="5" y1="21" x2="5" y2="11"/><line x1="12" y1="21" x2="12" y2="6"/><line x1="19" y1="21" x2="19" y2="14"/>',
        'map-pin'     => '<path d="M20 10c0 5.5-8 12-8 12S4 15.5 4 10a8 8 0 0 1 16 0z"/><circle cx="12" cy="10" r="2.7"/>',
        'check'       => '<polyline points="4 12 9.5 18 20 6"/>',
        'x'           => '<line x1="5" y1="5" x2="19" y2="19"/><line x1="19" y1="5" x2="5" y2="19"/>',
        'key'         => '<circle cx="7.5" cy="14.5" r="4"/><path d="M10.6 11.4 20 2"/><path d="M17 5l2 2"/><path d="M14 8l2 2"/>',
        'tag'         => '<path d="M20 12.5 12.5 20 3 10.5V3h7.5z"/><circle cx="8" cy="8" r="1.3" fill="currentColor" stroke="none"/>',
        'plus'        => '<line x1="12" y1="4" x2="12" y2="20"/><line x1="4" y1="12" x2="20" y2="12"/>',
        'save'        => '<path d="M5 3h11l5 5v13H5z"/><path d="M8 3v6h8V3"/><path d="M8 21v-7h8v7"/>',
        'edit'        => '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
        'refresh-cw'  => '<polyline points="17 2 21 6 17 10"/><path d="M21 6H9a6 6 0 0 0-6 6"/><polyline points="7 22 3 18 7 14"/><path d="M3 18h12a6 6 0 0 0 6-6"/>',
        'copy'        => '<rect x="9" y="9" width="12" height="12" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
        'trash'       => '<polyline points="4 7 20 7"/><path d="M6 7V5a2 2 0 0 1 2-2h8a2 2 0 0 1 2 2v2"/><path d="M19 7l-1 13a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 7"/><line x1="10" y1="11" x2="10" y2="17"/><line x1="14" y1="11" x2="14" y2="17"/>',
        'search'      => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.2" y2="16.2"/>',
        'shield'      => '<path d="M12 2 4 5v6c0 5 3.4 8.7 8 10 4.6-1.3 8-5 8-10V5z"/>',
        'alert'       => '<path d="M12 3 22 20H2z"/><line x1="12" y1="9" x2="12" y2="14"/><line x1="12" y1="17" x2="12" y2="17.01"/>',
        'chevron'     => '<polyline points="6 9 12 15 18 9"/>',
        'filter'      => '<polygon points="4 4 20 4 14 12 14 19 10 21 10 12"/>',
        'dot'         => '<circle cx="12" cy="12" r="6" fill="currentColor" stroke="none"/>',
        'ban'         => '<circle cx="12" cy="12" r="9"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'info'        => '<circle cx="12" cy="12" r="9"/><line x1="12" y1="10" x2="12" y2="16"/><line x1="12" y1="7" x2="12" y2="7.01"/>',
    ];

    if (!isset($paths[$name])) return '';

    $cls = trim('icon ' . $class);
    return '<svg class="' . htmlspecialchars($cls) . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $paths[$name] . '</svg>';
}
?>
