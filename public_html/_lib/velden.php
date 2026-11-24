<?php
// Site-adapter: Blauwdruk met vaste inhoud en projectfotografie.
function content_paginas() { return array('algemeen' => array('label' => 'Algemeen','url' => '','kern' => true,'groepen' => array('Bedrijf' => array('bedrijfsnaam' => array('label' => 'Bedrijfsnaam','type' => 'text','plat' => true),'logo' => array('label' => 'Logo','type' => 'afbeelding','plat' => true),'logo_op_blauw' => array('label' => 'Logo voor blauwe achtergrond','type' => 'afbeelding','plat' => true),'telefoon' => array('label' => 'Telefoon','type' => 'text','plat' => true),'email' => array('label' => 'E-mail','type' => 'text','plat' => true),'adres' => array('label' => 'Adres','type' => 'text','plat' => true)))),'home' => array('label' => 'Homepage','url' => '','kern' => true,'groepen' => array('Introductie' => array('blauwdruk_titel' => array('label' => 'Hoofdtitel','type' => 'text'),'home_intro' => array('label' => 'Introductie','type' => 'textarea'),'home_afbeelding' => array('label' => 'Hoofdfoto','type' => 'afbeelding'),'detail_afbeelding' => array('label' => 'Detailfoto','type' => 'afbeelding'),'home_cta_link' => array('label' => 'Bestemming projectknop','type' => 'link')),'Over Immens' => array('intro_titel' => array('label' => 'Titel introductie','type' => 'text'),'intro_tekst' => array('label' => 'Tekst introductie','type' => 'textarea'),'intro_afbeelding' => array('label' => 'Foto introductie','type' => 'afbeelding'),'intro_punt1' => array('label' => 'Kenmerk 1','type' => 'text'),'intro_punt2' => array('label' => 'Kenmerk 2','type' => 'text'),'intro_punt3' => array('label' => 'Kenmerk 3','type' => 'text'),'offerteband_titel' => array('label' => 'Titel offertebalk','type' => 'text'),'offerteband_tekst' => array('label' => 'Tekst offertebalk','type' => 'textarea')),'Stucwerk' => array('stucwerk_titel' => array('label' => 'Titel','type' => 'text'),'stucwerk_intro' => array('label' => 'Inleiding','type' => 'textarea'),'dienst1_afbeelding' => array('label' => 'Foto dienst 1','type' => 'afbeelding'),'dienst1_titel' => array('label' => 'Dienst 1: titel','type' => 'text'),'dienst1_tekst' => array('label' => 'Dienst 1: tekst','type' => 'textarea'),'dienst2_afbeelding' => array('label' => 'Foto dienst 2','type' => 'afbeelding'),'dienst2_titel' => array('label' => 'Dienst 2: titel','type' => 'text'),'dienst2_tekst' => array('label' => 'Dienst 2: tekst','type' => 'textarea'),'dienst3_afbeelding' => array('label' => 'Foto dienst 3','type' => 'afbeelding'),'dienst3_titel' => array('label' => 'Dienst 3: titel','type' => 'text'),'dienst3_tekst' => array('label' => 'Dienst 3: tekst','type' => 'textarea')),'Over en projecten' => array('over_titel' => array('label' => 'Titel werkwijze','type' => 'text'),'over_tekst' => array('label' => 'Tekst werkwijze','type' => 'textarea'),'projecten_titel' => array('label' => 'Titel projecten','type' => 'text'),'projecten_intro' => array('label' => 'Inleiding projecten','type' => 'textarea')),'Contact' => array('contact_titel' => array('label' => 'Titel','type' => 'text'),'contact_intro' => array('label' => 'Inleiding','type' => 'textarea'),'contact_knop' => array('label' => 'Knoptekst','type' => 'text')))),'offerte' => array('label' => 'Offerte maken','url' => 'offerte','groepen' => array('Introductie offerte' => array('offerte_titel' => array('label' => 'Hoofdtitel','type' => 'text'),'offerte_intro' => array('label' => 'Introductie','type' => 'textarea')))),'privacy' => array('label' => 'Privacy','url' => 'privacy','groepen' => array('Inhoud' => array('privacy_titel' => array('label' => 'Titel','type' => 'text'),'privacy_tekst' => array('label' => 'Privacytekst','type' => 'textarea'))))); }

function kernteksten_velden() {
    $uit = array();
    foreach (content_paginas() as $pagina) {
        foreach (($pagina['groepen'] ?? array()) as $velden) {
            foreach ($velden as $sleutel => $definitie) { $uit[$sleutel] = $definitie; }
        }
    }
    return $uit;
}

function velden_van_pagina($paginaSleutel) {
    $paginas = content_paginas();
    if (empty($paginas[$paginaSleutel]['groepen'])) { return array(); }
    $uit = array();
    foreach ($paginas[$paginaSleutel]['groepen'] as $velden) {
        foreach ($velden as $sleutel => $definitie) { $uit[$sleutel] = $definitie; }
    }
    return $uit;
}

function kernteksten_groepen() {
    $uit = array();
    foreach (content_paginas() as $pagina) {
        foreach (($pagina['groepen'] ?? array()) as $groep => $velden) {
            $uit[$groep] = isset($uit[$groep]) ? array_merge($uit[$groep], $velden) : $velden;
        }
    }
    return $uit;
}

/**
 * Optionele vaste siteonderdelen die een beheerder mag rangschikken.
 *
 * Een integratie kan bijvoorbeeld teruggeven:
 * array(
 *   'home' => array('label' => 'Homepage', 'secties' => array(
 *       'diensten' => array('label' => 'Diensten', 'standaard_aan' => true),
 *       'reviews'  => array('label' => 'Beoordelingen', 'standaard_aan' => true),
 *   )),
 *   'extra' => array('label' => 'Extra pagina's', 'eigen_paginas' => true,
 *       'secties' => array('reviews' => array('label' => 'Beoordelingen'))),
 * );
 *
 * De publieke adapter koppelt sleutels via een vaste whitelist aan partials.
 * Gebruik nooit een beheersleutel rechtstreeks als bestandsnaam in include().
 */
function site_sectie_register() { return array(); }

/** Register voor zichtbaarheid, SEO en navigatie. Algemeen is geen publieke pagina. */
function site_pagina_register() {
    $uit = array();
    foreach (content_paginas() as $sleutel => $pagina) {
        if ($sleutel === 'algemeen') { continue; }
        $uit[$sleutel] = array(
            'label' => (string) ($pagina['label'] ?? $sleutel),
            'url'   => (string) ($pagina['url'] ?? $sleutel),
            'kern'  => !empty($pagina['kern']),
        );
    }
    if (defined('CMS_MODULE_EIGEN_PAGINAS') && CMS_MODULE_EIGEN_PAGINAS) {
        require_once __DIR__ . '/eigen_paginas.php';
        foreach (eigen_paginas_alle() as $slug => $pagina) {
            $uit[eigen_register_sleutel($slug)] = array(
                'label' => (string) ($pagina['titel'] ?? $slug),
                'url' => $slug,
                'kern' => false,
                'eigen' => true,
            );
        }
    }
    return $uit;
}
