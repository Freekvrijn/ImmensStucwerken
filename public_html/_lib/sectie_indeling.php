<?php
/** Optionele volgorde en zichtbaarheid van vaste, door de site geregistreerde secties. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/velden.php';

function cms_sectie_zones() {
    if (!defined('CMS_MODULE_SECTIE_INDELING') || !CMS_MODULE_SECTIE_INDELING || !function_exists('site_sectie_register')) { return array(); }
    $ruw = site_sectie_register();
    if (!is_array($ruw)) { return array(); }
    $uit = array();
    foreach ($ruw as $zone => $definitie) {
        if (!preg_match('/^[a-z0-9_-]+$/', (string) $zone) || !is_array($definitie) || empty($definitie['secties']) || !is_array($definitie['secties'])) { continue; }
        $secties = array();
        foreach ($definitie['secties'] as $sleutel => $sectie) {
            if (!preg_match('/^[a-z0-9_-]+$/', (string) $sleutel)) { continue; }
            if (!is_array($sectie)) { $sectie = array('label' => (string) $sectie); }
            $secties[$sleutel] = array(
                'label' => trim((string) ($sectie['label'] ?? $sleutel)),
                'standaard_aan' => !array_key_exists('standaard_aan', $sectie) || (bool) $sectie['standaard_aan'],
            );
        }
        if (!$secties) { continue; }
        $uit[$zone] = array(
            'label' => trim((string) ($definitie['label'] ?? $zone)),
            'eigen_paginas' => !empty($definitie['eigen_paginas']),
            'inhoud_label' => trim((string) ($definitie['inhoud_label'] ?? 'Eigen inhoud van deze pagina')),
            'secties' => $secties,
        );
    }
    return $uit;
}

function cms_sectie_zone($zone) {
    $zones = cms_sectie_zones();
    return $zones[$zone] ?? null;
}

function cms_sectie_eigen_zone() {
    foreach (cms_sectie_zones() as $zone => $definitie) {
        if (!empty($definitie['eigen_paginas'])) { return $zone; }
    }
    return '';
}

function cms_sectie_indeling_normaliseer($zone, array $opslag, $metInhoud = false) {
    $definitie = cms_sectie_zone($zone);
    if (!$definitie) { return array('volgorde' => array(), 'aan' => array()); }
    $standaard = array_keys($definitie['secties']);
    if ($metInhoud) { array_unshift($standaard, 'inhoud'); }

    $volgorde = array();
    foreach ((array) ($opslag['volgorde'] ?? array()) as $sleutel) {
        $sleutel = (string) $sleutel;
        if (in_array($sleutel, $standaard, true) && !in_array($sleutel, $volgorde, true)) { $volgorde[] = $sleutel; }
    }
    foreach ($standaard as $sleutel) { if (!in_array($sleutel, $volgorde, true)) { $volgorde[] = $sleutel; } }

    if (isset($opslag['aan']) && is_array($opslag['aan'])) {
        $aan = array_values(array_intersect($volgorde, array_map('strval', $opslag['aan'])));
    } else {
        $aan = array();
        foreach ($definitie['secties'] as $sleutel => $sectie) { if ($sectie['standaard_aan']) { $aan[] = $sleutel; } }
    }
    if ($metInhoud && !in_array('inhoud', $aan, true)) { $aan[] = 'inhoud'; }
    return array('volgorde' => $volgorde, 'aan' => array_values(array_intersect($volgorde, $aan)));
}

function cms_sectie_indeling($zone) {
    $alle = load_json('sectie-indeling.json', array());
    return cms_sectie_indeling_normaliseer($zone, is_array($alle[$zone] ?? null) ? $alle[$zone] : array());
}

function cms_sectie_indeling_opslaan($zone, array $volgorde, array $aan) {
    $definitie = cms_sectie_zone($zone);
    if (!$definitie || !empty($definitie['eigen_paginas'])) { return false; }
    $alle = load_json('sectie-indeling.json', array());
    $alle[$zone] = cms_sectie_indeling_normaliseer($zone, array('volgorde' => $volgorde, 'aan' => $aan));
    return save_json('sectie-indeling.json', $alle);
}

/** Publieke API: alleen de ingeschakelde secties, in beheer-volgorde. */
function cms_secties($zone) {
    $indeling = cms_sectie_indeling($zone);
    return array_values(array_intersect($indeling['volgorde'], $indeling['aan']));
}

function cms_eigen_sectie_indeling(array $pagina) {
    $zone = cms_sectie_eigen_zone();
    if ($zone === '') { return array('zone' => '', 'volgorde' => array('inhoud'), 'aan' => array('inhoud')); }
    $opslag = array(
        'volgorde' => is_array($pagina['sectie_volgorde'] ?? null) ? $pagina['sectie_volgorde'] : array(),
        'aan' => is_array($pagina['secties'] ?? null) ? $pagina['secties'] : null,
    );
    return array_merge(array('zone' => $zone), cms_sectie_indeling_normaliseer($zone, $opslag, true));
}

/** Publieke API voor extra pagina's. De marker 'inhoud' staat voor de eigen pagina-inhoud. */
function cms_eigen_secties(array $pagina) {
    $indeling = cms_eigen_sectie_indeling($pagina);
    return array_values(array_intersect($indeling['volgorde'], $indeling['aan']));
}
