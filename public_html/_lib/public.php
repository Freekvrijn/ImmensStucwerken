<?php
/**
 * Stabiele publieke API voor toekomstige sites.
 * Include dit bestand vóór HTML-uitvoer in de publieke bootstrap van de site.
 */
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/cms.php';
require_once __DIR__ . '/toegang.php';
require_once __DIR__ . '/paginas.php';
if (defined('CMS_MODULE_FORMULIEREN') && CMS_MODULE_FORMULIEREN) {
    require_once __DIR__ . '/formulieren.php';
}
if (defined('CMS_MODULE_TEKSTRIJEN') && CMS_MODULE_TEKSTRIJEN) {
    require_once __DIR__ . '/tekstrijen.php';
}
if (defined('CMS_MODULE_SECTIE_INDELING') && CMS_MODULE_SECTIE_INDELING) {
    require_once __DIR__ . '/sectie_indeling.php';
}

function cms_redirect_toepassen() {
    $pad = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH);
    $pad = '/' . ltrim((string) $pad, '/');
    foreach (load_json('redirects.json', array()) as $regel) {
        if (empty($regel['actief'])) { continue; }
        $van = '/' . ltrim((string) ($regel['van'] ?? ''), '/');
        if ($van !== $pad) { continue; }
        $naar = cms_url_opschonen((string) ($regel['naar'] ?? ''));
        if ($naar === '') { continue; }
        if (!preg_match('~^https?://~i', $naar)) { $naar = '/' . ltrim($naar, '/'); }
        $code = (int) ($regel['code'] ?? 301);
        if (!in_array($code, array(301, 302, 307, 308), true)) { $code = 301; }
        header('Location: ' . $naar, true, $code);
        exit;
    }
}

function cms_public_boot($paginaSleutel = '') {
    blokkeer_indien_nodig('site');
    if (defined('CMS_MODULE_BEZOEKERS') && CMS_MODULE_BEZOEKERS) {
        require_once __DIR__ . '/bezoekers.php';
        bezoek_registreren();
    }
    if (defined('CMS_MODULE_ONDERHOUD') && CMS_MODULE_ONDERHOUD) {
        require_once __DIR__ . '/onderhoud.php';
        onderhoud_toepassen();
    }
    cms_redirect_toepassen();
    if ($paginaSleutel !== '') { pagina_toegang_bewaken($paginaSleutel); }
}

function cms_content() { return load_json('content.json', array()); }

function cms_seo_voor($paginaSleutel, $standaard = array()) {
    $seo = load_json('seo.json', array());
    $paginaSeo = is_array($seo[$paginaSleutel] ?? null) ? $seo[$paginaSleutel] : array();
    unset($paginaSeo['indexeren']); // paginastatus.json is hiervoor de enige bron
    $uit = array_merge(array(
        'titel' => '', 'omschrijving' => '', 'canonical' => '',
        'og_afbeelding' => '', 'indexeren' => true,
    ), $standaard, $paginaSeo);
    $uit['indexeren'] = pagina_is_indexeerbaar($paginaSleutel);
    return $uit;
}

function cms_eigen_pagina($slug) {
    if (!defined('CMS_MODULE_EIGEN_PAGINAS') || !CMS_MODULE_EIGEN_PAGINAS) { return null; }
    require_once __DIR__ . '/eigen_paginas.php';
    return eigen_pagina($slug);
}

function cms_navigatie($locatie = 'hoofd') {
    $items = array_values(array_filter(load_json('navigatie.json', array()), function ($item) use ($locatie) {
        return !empty($item['actief']) && (($item['locatie'] ?? 'hoofd') === $locatie);
    }));
    if (defined('CMS_MODULE_EIGEN_PAGINAS') && CMS_MODULE_EIGEN_PAGINAS) {
        require_once __DIR__ . '/eigen_paginas.php';
        foreach (eigen_menu_items($locatie) as $item) {
            $bestaat = false;
            foreach ($items as $bestaand) {
                if (trim((string) ($bestaand['url'] ?? ''), '/') === $item['url']) { $bestaat = true; break; }
            }
            if (!$bestaat) { $items[] = array_merge($item, array('actief' => true, 'locatie' => $locatie)); }
        }
    }
    usort($items, function ($a, $b) { return (int) ($a['volgorde'] ?? 0) <=> (int) ($b['volgorde'] ?? 0); });
    return $items;
}

function cms_faq() { return load_json('faq.json', array()); }
function cms_collectie() { return load_json('collectie.json', array()); }
