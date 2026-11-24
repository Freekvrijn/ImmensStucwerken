<?php
/** Automatische JSON-snapshots vóór iedere inhoudelijke opslag. */
require_once __DIR__ . '/config.php';

function versiebare_bestanden() {
    return array(
        'content.json'      => 'Paginainhoud',
        'tekstrijen.json'   => 'Flexibele contentblokken',
        'paginas-eigen.json'=> 'Extra pagina\'s',
        'paginastatus.json' => 'Zichtbaarheid pagina\'s',
        'navigatie.json'    => 'Navigatie',
        'seo.json'          => 'SEO',
        'redirects.json'    => 'Redirects',
        'media.json'        => 'Media-metadata',
        'faq.json'          => 'Veelgestelde vragen',
        'collectie.json'    => defined('CMS_COLLECTIE_LABEL') ? CMS_COLLECTIE_LABEL : 'Collectie',
        'formulieren.json'  => 'Formulierinstellingen',
        'sectie-indeling.json' => 'Pagina-indeling',
        'instellingen.json' => 'Algemene instellingen',
        'offerte-instellingen.json' => 'Offertetarieven en voorwaarden',
        'toegang.json'      => 'Toegang',
    );
}

function is_versiebaar($naam) {
    return array_key_exists($naam, versiebare_bestanden());
}

function versies_dir() {
    return CMS_DATA_DIR . '/versies';
}

const VERSIES_MAX = 20;

function versie_maak($naam) {
    if (!is_versiebaar($naam)) { return ''; }
    $bron = CMS_DATA_DIR . '/' . $naam;
    if (!is_file($bron)) { return ''; }
    $inhoud = @file_get_contents($bron);
    if ($inhoud === false || trim($inhoud) === '') { return ''; }
    $dir = versies_dir();
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) { return ''; }
    $basis = pathinfo($naam, PATHINFO_FILENAME);
    $stamp = date('Ymd-His');
    $doel = $basis . '--' . $stamp . '.json';
    $nummer = 2;
    while (file_exists($dir . '/' . $doel)) {
        $doel = $basis . '--' . $stamp . '-' . $nummer . '.json';
        $nummer++;
    }
    if (@file_put_contents($dir . '/' . $doel, $inhoud, LOCK_EX) === false) { return ''; }
    @chmod($dir . '/' . $doel, 0644);
    versies_opschonen($naam);
    return $doel;
}

function versies_lijst($naam) {
    if (!is_versiebaar($naam) || !is_dir(versies_dir())) { return array(); }
    $basis = pathinfo($naam, PATHINFO_FILENAME);
    $uit = array();
    foreach (scandir(versies_dir()) as $bestand) {
        if (!preg_match('/^' . preg_quote($basis, '/') . '--(\d{8})-(\d{6})(?:-\d+)?\.json$/', $bestand, $m)) { continue; }
        $tijd = strtotime($m[1] . ' ' . substr($m[2], 0, 2) . ':' . substr($m[2], 2, 2) . ':' . substr($m[2], 4, 2));
        $uit[] = array(
            'bestand' => $bestand,
            'tijd' => $tijd ? $tijd : (int) filemtime(versies_dir() . '/' . $bestand),
            'bytes' => (int) filesize(versies_dir() . '/' . $bestand),
        );
    }
    usort($uit, function ($a, $b) { return $b['tijd'] <=> $a['tijd']; });
    return $uit;
}

function versies_opschonen($naam, $max = VERSIES_MAX) {
    $lijst = versies_lijst($naam);
    foreach (array_slice($lijst, $max) as $oud) { @unlink(versies_dir() . '/' . $oud['bestand']); }
}

function versie_lees($naam, $bestand) {
    if (!is_versiebaar($naam)) { return null; }
    $geldig = false;
    foreach (versies_lijst($naam) as $versie) {
        if ($versie['bestand'] === $bestand) { $geldig = true; break; }
    }
    if (!$geldig) { return null; }
    $ruw = @file_get_contents(versies_dir() . '/' . $bestand);
    if ($ruw === false) { return null; }
    $data = json_decode($ruw, true);
    return is_array($data) ? $data : null;
}
