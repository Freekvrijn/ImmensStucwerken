<?php
/** Zelfstandige tekstrijen. Bestaande inhoud blijft in de paginatemplates en content.json. */
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/helpers.php';

function tekstrijen_zones() {
    $uit = array();
    if (function_exists('content_paginas')) {
        foreach (content_paginas() as $sleutel => $pagina) {
            if ($sleutel === 'algemeen') { continue; }
            $uit['pagina-' . $sleutel] = 'Flexibele blokken op ' . (string) ($pagina['label'] ?? $sleutel);
        }
    }
    return $uit + tekstrijen_eigen_zones();
}

/** Elke extra pagina (Beheer » Extra pagina's) heeft een eigen zone: 'eigen-<slug>'. */
function tekstrijen_eigen_zones() {
    if (!function_exists('eigen_paginas_alle')) { require_once __DIR__ . '/eigen_paginas.php'; }
    $uit = array();
    foreach (eigen_paginas_alle() as $slug => $p) {
        $uit['eigen-' . $slug] = 'Blokken op “' . (string) ($p['titel'] ?? $slug) . '”';
    }
    return $uit;
}

/** Alleen accounts met inhoudsrechten mogen flexibele blokken bewerken. */
function tekstrijen_mag_bewerken($zone) {
    return function_exists('mag') && mag('content');
}

function tekstrijen_standaard($zone) {
    if (strpos($zone, 'eigen-') === 0) { return array(); } // extra pagina: begint zonder rijen
    return strpos($zone, 'eigen-') === 0 ? array() : array(array('id' => 'bestaand'));
}

function tekstrijen_laad($zone, $data = null) {
    if ($data === null) { $data = load_json('tekstrijen.json', array()); }
    return isset($data[$zone]) && is_array($data[$zone]) ? $data[$zone] : tekstrijen_standaard($zone);
}

function tekstrijen_revisie($rijen) {
    return hash('sha256', json_encode($rijen, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
}

/** Vangt alleen expliciet aangewezen tekstsecties op; geen foto's, formulieren of navigatie. */
function tekstrij_begin($zone) {
    $GLOBALS['tekstrij_zone'] = $zone;
    ob_start();
}

function tekstrij_einde() {
    $html = ob_get_clean();
    tekstrijen_toon($GLOBALS['tekstrij_zone'], $html);
    unset($GLOBALS['tekstrij_zone']);
}

/** Alleen afbeeldingen uit de eigen assets/img-map (ook uploads) zijn toegestaan in rijen. */
function tekstrij_afbeelding_veilig($pad) {
    $pad = trim((string) $pad);
    if ($pad === '' || strpos($pad, '..') !== false) { return ''; }
    return preg_match('~^/?assets/img/[A-Za-z0-9/_.-]+\.(jpe?g|png|gif|webp)$~i', $pad) ? $pad : '';
}

/** Leest de kaarten (blokken naast elkaar) uit JSON en maakt ze veilig. Geeft [kaarten, fout]. */
function tekstrij_kaarten_invoer($json) {
    $ruw = json_decode((string) $json, true);
    if (!is_array($ruw)) { return array(array(), ''); }
    $uit = array();
    foreach (array_slice(array_values($ruw), 0, 3) as $k) {
        if (!is_array($k)) { continue; }
        $kaart = array(
            'afbeelding' => tekstrij_afbeelding_veilig($k['afbeelding'] ?? ''),
            'titel'      => trim(strip_tags((string) ($k['titel'] ?? ''))),
            'tekst'      => trim(strip_tags((string) ($k['tekst'] ?? ''))),
            'link_tekst' => trim(strip_tags((string) ($k['link_tekst'] ?? ''))),
            'link_url'   => '',
        );
        $url = trim((string) ($k['link_url'] ?? ''));
        if ($url !== '') {
            $kaart['link_url'] = veld_link_opslaan($url);
            if ($kaart['link_url'] === '') { return array(array(), 'De link bij blok “' . ($kaart['titel'] ?: count($uit) + 1) . '” is geen geldig webadres.'); }
        }
        if (mb_strlen($kaart['titel']) > 120 || mb_strlen($kaart['tekst']) > 500 || mb_strlen($kaart['link_tekst']) > 60) {
            return array(array(), 'Een van de blokken heeft een te lange tekst.');
        }
        if ($kaart['titel'] === '' && $kaart['tekst'] === '' && $kaart['afbeelding'] === '') { continue; }
        $uit[] = $kaart;
    }
    return array($uit, '');
}

/** Kop van een rij: standaard H2, of H3 als die rij onder een andere tussenkop valt. */
function tekstrij_kop($rij) {
    if (empty($rij['titel'])) { return ''; }
    $tag = ($rij['titel_niveau'] ?? 'h2') === 'h3' ? 'h3' : 'h2';
    return '<' . $tag . ' class="tekstrij-kop">' . e($rij['titel']) . '</' . $tag . '>';
}

function tekstrij_knop($rij) {
    if (empty($rij['knop_tekst']) || empty($rij['knop_url'])) { return ''; }
    return '<div class="tekstrij-knop"><a class="btn btn--primary" href="' . e($rij['knop_url']) . '">' . e($rij['knop_tekst']) . '</a></div>';
}

function tekstrijen_toon($zone, $bestaandHtml = '') {
    $zones = tekstrijen_zones();
    if (!isset($zones[$zone])) { return; }
    $rijen = tekstrijen_laad($zone);
    $bewerken = tekstrijen_mag_bewerken($zone);
    if (!$bewerken && !$rijen) { return; }

    echo '<div class="tekstrijen-zone"';
    if ($bewerken) {
        echo ' data-rij-zone="' . e($zone) . '" data-rij-revisie="' . e(tekstrijen_revisie($rijen)) . '"';
    }
    echo '>';
    if ($bewerken) {
        echo '<div class="container rij-invoegen"><span>' . e($zones[$zone]) . '</span><button type="button" data-rij-toevoegen="">+ Tekstrij toevoegen</button>';
        if ($bestaandHtml !== '' && !in_array('bestaand', array_column($rijen, 'id'), true)) {
            echo '<button type="button" data-rij-herstellen>Oorspronkelijke rij terugzetten</button>';
        }
        echo '<p class="rij-zone-status" role="status"></p></div>';
    }

    foreach ($rijen as $rij) {
        $bestaand = $rij['id'] === 'bestaand';
        echo '<div class="tekstrij" id="tekstrij-' . e($zone . '-' . $rij['id']) . '"';
        if ($bewerken) { echo ' data-rij-id="' . e($rij['id']) . '" data-rij-inhoud="' . e(json_encode($rij, JSON_UNESCAPED_UNICODE)) . '"'; }
        echo '>';
        if ($bewerken) {
            echo '<div class="container rij-acties"><span>' . e($bestaand ? $zones[$zone] : ($rij['titel'] ?: 'Tekstrij')) . '</span>';
            echo '<button type="button" data-rij-bewerken>' . ($bestaand ? (empty($rij['knop_tekst']) ? 'Knop toevoegen' : 'Knop aanpassen') : 'Rij bewerken') . '</button>';
            echo '<button type="button" data-rij-toevoegen="' . e($rij['id']) . '">+ Rij eronder</button>';
            echo '<button type="button" class="rij-verwijderen" data-rij-verwijderen>Rij verwijderen</button></div>';
        }
        if ($bestaand) {
            echo str_replace('<!-- tekstrij-knop -->', tekstrij_knop($rij), $bestaandHtml);
        } elseif (($rij['indeling'] ?? '') === 'afbeelding') {
            // Tekst met een afbeelding ernaast.
            $achtergrond = ($rij['achtergrond'] ?? 'paper') === 'mint' ? 'bg-mint' : 'bg-paper';
            $links = ($rij['afbeelding_positie'] ?? 'rechts') === 'links';
            $src = tekstrij_afbeelding_veilig($rij['afbeelding'] ?? '');
            echo '<section class="section ' . $achtergrond . '"><div class="container tekstrij-met-afbeelding' . ($links ? ' tekstrij-met-afbeelding--links' : '') . '"><div class="section-head tekstrij-inhoud">';
            if (!empty($rij['bovenkop'])) { echo '<span class="eyebrow">' . e($rij['bovenkop']) . '</span>'; }
            echo tekstrij_kop($rij);
            echo '<div class="tekstrij-tekst">' . $rij['html'] . '</div>' . tekstrij_knop($rij) . '</div>';
            if ($src !== '') {
                echo '<figure class="tekstrij-afbeelding"><img src="' . e($src) . '" alt="' . e($rij['afbeelding_alt'] ?? '') . '" loading="lazy" decoding="async"></figure>';
            }
            echo '</div></section>';
        } elseif (($rij['indeling'] ?? '') === 'kaarten') {
            // Blokken naast elkaar, elk met afbeelding, kop, tekst en link.
            $achtergrond = ($rij['achtergrond'] ?? 'paper') === 'mint' ? 'bg-mint' : 'bg-paper';
            $kaarten = is_array($rij['kaarten'] ?? null) ? $rij['kaarten'] : array();
            echo '<section class="section ' . $achtergrond . '"><div class="container">';
            if (!empty($rij['bovenkop']) || !empty($rij['titel']) || trim(strip_tags((string) ($rij['html'] ?? ''))) !== '') {
                echo '<div class="section-head tekstrij-inhoud">';
                if (!empty($rij['bovenkop'])) { echo '<span class="eyebrow">' . e($rij['bovenkop']) . '</span>'; }
                echo tekstrij_kop($rij);
                if (trim(strip_tags((string) ($rij['html'] ?? ''))) !== '') { echo '<div class="tekstrij-tekst">' . $rij['html'] . '</div>'; }
                echo '</div>';
            }
            echo '<div class="tekstrij-kaarten tekstrij-kaarten--' . max(1, min(3, count($kaarten))) . '">';
            foreach ($kaarten as $k) {
                $src = tekstrij_afbeelding_veilig($k['afbeelding'] ?? '');
                $url = (string) ($k['link_url'] ?? '');
                echo '<article class="tekstrij-kaart">';
                if ($src !== '') {
                    $img = '<img src="' . e($src) . '" alt="' . e($k['titel'] ?? '') . '" loading="lazy" decoding="async">';
                    echo '<div class="tekstrij-kaart__foto">' . ($url !== '' ? '<a href="' . e($url) . '" tabindex="-1" aria-hidden="true">' . $img . '</a>' : $img) . '</div>';
                }
                echo '<div class="tekstrij-kaart__tekst">';
                if (!empty($k['titel'])) { echo '<h3>' . ($url !== '' ? '<a href="' . e($url) . '">' . e($k['titel']) . '</a>' : e($k['titel'])) . '</h3>'; }
                if (!empty($k['tekst'])) { echo '<p>' . nl2br(e($k['tekst'])) . '</p>'; }
                if ($url !== '') { echo '<a class="tekstrij-kaart__link" href="' . e($url) . '">' . e(($k['link_tekst'] ?? '') !== '' ? $k['link_tekst'] : 'Lees meer') . ' <span aria-hidden="true">&rarr;</span></a>'; }
                echo '</div></article>';
            }
            echo '</div>' . tekstrij_knop($rij) . '</div></section>';
        } else {
            $kader = ($rij['indeling'] ?? 'tekst') === 'kader';
            $achtergrond = ($rij['achtergrond'] ?? 'paper') === 'mint' ? 'bg-mint' : 'bg-paper';
            echo '<section class="section ' . $achtergrond . '"><div class="container' . ($kader ? ' tekstrij-kolommen' : '') . '"><div class="section-head tekstrij-inhoud">';
            if (!empty($rij['bovenkop'])) { echo '<span class="eyebrow">' . e($rij['bovenkop']) . '</span>'; }
            echo tekstrij_kop($rij);
            echo '<div class="tekstrij-tekst">' . $rij['html'] . '</div>';
            if (!$kader) { echo tekstrij_knop($rij); }
            echo '</div>';
            if ($kader) {
                echo '<aside class="tekstrij-kader"><div class="tekstrij-tekst">' . $rij['kader_html'] . '</div>' . tekstrij_knop($rij) . '</aside>';
            }
            echo '</div></section>';
        }
        echo '</div>';
    }
    echo '</div>';
}
