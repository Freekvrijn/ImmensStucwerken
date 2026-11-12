<?php
/** Formulierinstellingen, inzendingen, spamblokkade en mailafhandeling. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/cms.php';
require_once __DIR__ . '/toegang.php';

function cms_formulieren_alle() {
    $data = load_json('formulieren.json', array());
    return is_array($data) ? $data : array();
}

function cms_inzendingen_alle() {
    $data = load_json('inzendingen.json', array());
    return is_array($data) ? array_values($data) : array();
}

function cms_inzendingen_nieuw() {
    $aantal = 0;
    foreach (cms_inzendingen_alle() as $rij) {
        if (($rij['status'] ?? 'nieuw') === 'nieuw') { $aantal++; }
    }
    return $aantal;
}

function cms_formulier_ip() {
    return function_exists('client_ip') ? (string) client_ip() : (string) ($_SERVER['REMOTE_ADDR'] ?? '');
}

function cms_formulier_blokkade() {
    $data = load_json('formulier-blokkade.json', array());
    return array(
        'emails' => array_values(array_unique(array_map('strtolower', (array) ($data['emails'] ?? array())))),
        'ips' => array_values(array_unique(array_map('strval', (array) ($data['ips'] ?? array())))),
    );
}

function cms_formulier_is_geblokkeerd($email = '', $ip = null) {
    $blokkade = cms_formulier_blokkade();
    $email = strtolower(trim((string) $email));
    $ip = trim((string) ($ip === null ? cms_formulier_ip() : $ip));
    return ($email !== '' && in_array($email, $blokkade['emails'], true))
        || ($ip !== '' && in_array($ip, $blokkade['ips'], true));
}

function cms_formulier_blokkade_wijzig($email, $ip, $blokkeren = true) {
    $blokkade = cms_formulier_blokkade();
    $email = strtolower(trim((string) $email));
    $ip = trim((string) $ip);
    foreach (array('emails' => $email, 'ips' => $ip) as $soort => $waarde) {
        if ($waarde === '') { continue; }
        $blokkade[$soort] = array_values(array_diff($blokkade[$soort], array($waarde)));
        if ($blokkeren) { $blokkade[$soort][] = $waarde; }
    }
    return save_json('formulier-blokkade.json', $blokkade);
}

function cms_formulier_schoon_woordenboek(array $waarden, $maxWaarde = 10000) {
    $uit = array();
    foreach (array_slice($waarden, 0, 50, true) as $sleutel => $waarde) {
        if (!is_scalar($waarde)) { continue; }
        $sleutel = substr((string) preg_replace('/[^a-z0-9_-]/i', '', (string) $sleutel), 0, 64);
        if ($sleutel === '') { continue; }
        $uit[$sleutel] = substr(trim((string) $waarde), 0, $maxWaarde);
    }
    return $uit;
}

/**
 * Slaat één gevalideerde inzending op. Inzendingen krijgen bewust geen
 * herstelversies, omdat die persoonsgegevens onnodig zouden vermenigvuldigen.
 */
function cms_inzending_opslaan($formulier, $velden, $labels = array(), $email = '', $naam = '', $ip = null) {
    if (!is_array($velden)) { return false; }
    $formulier = preg_replace('/[^a-z0-9_-]/i', '', (string) $formulier);
    if ($formulier === '') { return false; }
    $schoon = cms_formulier_schoon_woordenboek($velden);
    $schoneLabels = cms_formulier_schoon_woordenboek(is_array($labels) ? $labels : array(), 120);
    $email = strtolower(trim((string) $email));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $email = ''; }
    $naam = substr(trim((string) $naam), 0, 160);
    $ip = trim((string) ($ip === null ? cms_formulier_ip() : $ip));
    if ($ip !== '' && !filter_var($ip, FILTER_VALIDATE_IP)) { $ip = ''; }

    $inzendingen = cms_inzendingen_alle();
    array_unshift($inzendingen, array(
        'id' => cms_nieuwe_id('inz'),
        'formulier' => $formulier,
        'ontvangen_op' => date('c'),
        'status' => 'nieuw',
        'velden' => $schoon,
        'labels' => $schoneLabels,
        'naam' => $naam,
        'email' => $email,
        'ip' => $ip,
    ));
    $inzendingen = array_slice($inzendingen, 0, 1000);
    $dagen = defined('CMS_FORMULIER_BEWAAR_DAGEN') ? max(1, (int) CMS_FORMULIER_BEWAAR_DAGEN) : 365;
    $grens = time() - ($dagen * 86400);
    $inzendingen = array_values(array_filter($inzendingen, function ($rij) use ($grens) {
        $tijd = strtotime((string) ($rij['ontvangen_op'] ?? ''));
        return !$tijd || $tijd >= $grens;
    }));
    return save_json('inzendingen.json', $inzendingen, false);
}

function cms_inzendingen_opruimen($dagen = null) {
    $dagen = $dagen === null && defined('CMS_FORMULIER_BEWAAR_DAGEN') ? CMS_FORMULIER_BEWAAR_DAGEN : $dagen;
    $dagen = max(1, (int) $dagen);
    $grens = time() - ($dagen * 86400);
    $lijst = cms_inzendingen_alle();
    $nieuw = array_values(array_filter($lijst, function ($rij) use ($grens) {
        $tijd = strtotime((string) ($rij['ontvangen_op'] ?? ''));
        return !$tijd || $tijd >= $grens;
    }));
    return array('aantal' => count($lijst) - count($nieuw), 'ok' => save_json('inzendingen.json', $nieuw, false));
}

/**
 * Verwerkt een publieke formulierpost. $opties ondersteunt optioneel 'labels'
 * en 'naam'. De eerste vier argumenten blijven achterwaarts compatibel.
 */
function cms_formulier_verwerken($formulier, $velden, $bezoekerEmail = '', $honeypot = '', $opties = array()) {
    if (trim((string) $honeypot) !== '') { return array('ok' => false, 'fout' => 'Ongeldige inzending.'); }
    $configuraties = cms_formulieren_alle();
    $config = $configuraties[$formulier] ?? null;
    if (!is_array($config) || empty($config['actief'])) { return array('ok' => false, 'fout' => 'Dit formulier is niet actief.'); }

    $ip = cms_formulier_ip();
    if (cms_formulier_is_geblokkeerd($bezoekerEmail, $ip)) {
        return array('ok' => true, 'fout' => '');
    }

    // Maximaal vijf inzendingen per uur per IP. De snelheidslog bewaart alleen een hash.
    $log = load_json('formulierlog.json', array());
    $ipHash = hash('sha256', $ip !== '' ? $ip : 'onbekend');
    $grens = time() - 3600;
    foreach ($log as $hash => $tijden) {
        $actueel = array_values(array_filter((array) $tijden, function ($tijd) use ($grens) { return (int) $tijd >= $grens; }));
        if ($actueel) { $log[$hash] = $actueel; } else { unset($log[$hash]); }
    }
    $pogingen = $log[$ipHash] ?? array();
    if (count($pogingen) >= 5) { return array('ok' => false, 'fout' => 'Te veel inzendingen. Probeer het later opnieuw.'); }
    $pogingen[] = time();
    $log[$ipHash] = $pogingen;
    save_json('formulierlog.json', $log, false);

    $labels = is_array($opties) && isset($opties['labels']) && is_array($opties['labels']) ? $opties['labels'] : array();
    $naam = is_array($opties) ? (string) ($opties['naam'] ?? '') : '';
    if (!cms_inzending_opslaan($formulier, $velden, $labels, $bezoekerEmail, $naam, $ip)) {
        return array('ok' => false, 'fout' => 'De inzending kon niet worden opgeslagen.');
    }

    $naar = trim((string) ($config['mail_to'] ?? ''));
    $van = trim((string) ($config['mail_from'] ?? ''));
    if ($naar !== '' && filter_var($naar, FILTER_VALIDATE_EMAIL) && $van !== '' && filter_var($van, FILTER_VALIDATE_EMAIL)) {
        $onderwerp = trim(str_replace(array("\r", "\n"), ' ', (string) ($config['subject_prefix'] ?? 'Nieuw websitebericht')));
        $regels = array();
        foreach ($velden as $sleutel => $waarde) {
            if (!is_scalar($waarde)) { continue; }
            $label = (string) ($labels[$sleutel] ?? ucfirst(str_replace('_', ' ', (string) $sleutel)));
            $regels[] = $label . ': ' . trim((string) $waarde);
        }
        $afzenderNaam = trim(str_replace(array("\r", "\n"), ' ', (string) ($config['mail_from_naam'] ?? cms_site_naam())));
        $headers = array('From: ' . $afzenderNaam . ' <' . $van . '>', 'Content-Type: text/plain; charset=UTF-8');
        $bcc = trim((string) ($config['mail_bcc'] ?? ''));
        if ($bcc !== '' && filter_var($bcc, FILTER_VALIDATE_EMAIL)) { $headers[] = 'Bcc: ' . $bcc; }
        @mail($naar, $onderwerp, implode("\n\n", $regels), implode("\r\n", $headers));
        if (!empty($config['autoresponder']) && filter_var($bezoekerEmail, FILTER_VALIDATE_EMAIL)) {
            @mail($bezoekerEmail, 'Bevestiging: ' . $onderwerp,
                "Bedankt voor uw bericht. We hebben uw inzending ontvangen.\n",
                'From: ' . $afzenderNaam . ' <' . $van . ">\r\nContent-Type: text/plain; charset=UTF-8");
        }
    }
    return array('ok' => true, 'fout' => '');
}
