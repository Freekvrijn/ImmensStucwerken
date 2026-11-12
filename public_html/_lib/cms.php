<?php
/** Algemene CMS-functies die beheer en publieke integratie delen. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';

/** Waarde uit instellingen.json; lege waarden mogen bewust leeg blijven. */
function cms_instelling($sleutel, $standaard = '') {
    $instellingen = load_json('instellingen.json', array());
    return array_key_exists($sleutel, $instellingen) ? $instellingen[$sleutel] : $standaard;
}

function cms_site_naam() {
    $naam = trim((string) cms_instelling('site_naam', CMS_SITE_NAME));
    return $naam !== '' ? $naam : CMS_SITE_NAME;
}

function cms_site_url() {
    $url = trim((string) cms_instelling('site_url', CMS_SITE_URL));
    return $url === '' ? '' : rtrim($url, '/') . '/';
}

/** Featureflag uitlezen zonder op dynamische constants te vertrouwen. */
function cms_module_aan($module) {
    $kaart = array(
        'paginas'     => CMS_MODULE_PAGINAS,
        'eigen_paginas' => CMS_MODULE_EIGEN_PAGINAS,
        'tekstrijen'  => CMS_MODULE_TEKSTRIJEN,
        'navigatie'   => CMS_MODULE_NAVIGATIE,
        'seo'         => CMS_MODULE_SEO,
        'media'       => CMS_MODULE_MEDIA,
        'redirects'   => CMS_MODULE_REDIRECTS,
        'faq'         => CMS_MODULE_FAQ,
        'collectie'   => CMS_MODULE_COLLECTIE,
        'formulieren' => CMS_MODULE_FORMULIEREN,
        'toegang'     => CMS_MODULE_TOEGANG,
        'bezoekers'   => CMS_MODULE_BEZOEKERS,
        'geoip'       => CMS_MODULE_GEOIP,
        'onderhoud'   => defined('CMS_MODULE_ONDERHOUD') && CMS_MODULE_ONDERHOUD,
        'sectie_indeling' => defined('CMS_MODULE_SECTIE_INDELING') && CMS_MODULE_SECTIE_INDELING,
    );
    return !empty($kaart[$module]);
}

/** Veilige, stabiele id voor nieuwe JSON-records. */
function cms_nieuwe_id($voorvoegsel) {
    try {
        return $voorvoegsel . '-' . bin2hex(random_bytes(8));
    } catch (Exception $e) {
        return uniqid($voorvoegsel . '-', true);
    }
}

/** Relatieve URL's en https/http-links zijn toegestaan; scripts en control chars niet. */
function cms_url_opschonen($url, $ankerToestaan = true) {
    $url = trim((string) $url);
    if ($url === '') { return ''; }
    if (preg_match('/[\x00-\x1F\x7F]/', $url)) { return ''; }
    if ($ankerToestaan && substr($url, 0, 1) === '#') { return $url; }
    if (preg_match('~^https?://~i', $url)) {
        return filter_var($url, FILTER_VALIDATE_URL) ? $url : '';
    }
    if (preg_match('~^(?:mailto:|tel:)~i', $url)) { return $url; }
    if (substr($url, 0, 2) === '//' || preg_match('~^[a-z][a-z0-9+.-]*:~i', $url)) { return ''; }
    return ltrim($url, '/');
}

function cms_publieke_link($pad = '') {
    if (!CMS_PUBLIEKE_SITE_AAN) { return ''; }
    $basis = cms_site_url();
    if ($basis !== '') { return $basis . ltrim((string) $pad, '/'); }
    return '../' . ltrim((string) $pad, '/');
}
