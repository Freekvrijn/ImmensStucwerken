<?php
/**
 * Bezoekerslog voor het beheer (Toegang & blokkering).
 *
 * Elk paginaverzoek op de publieke site wordt als één regel toegevoegd aan
 * data/bezoekers/JJJJ-MM-DD.log (tijd, IP, land, pagina, user-agent, geblokkeerd).
 * Alleen toevoegen, nooit herschrijven: dat is snel en kan niet botsen bij
 * gelijktijdige bezoekers. Het beheer telt de regels op tot één rij per IP.
 *
 * Landcode: eerst via de server (Cloudflare/GeoIP/MaxMind-database, zie client_land()).
 * Zonder eigen database zoekt het beheer ontbrekende landen op bij api.country.is — alleen
 * wanneer de beheerpagina geopend wordt, dus nooit tijdens een gewoon bezoek.
 * Resultaten worden bewaard in data/bezoekers-land.json.
 *
 * Privacy (AVG): IP-adressen zijn persoonsgegevens. Logbestanden ouder dan
 * BEZOEKERS_BEWAAR_DAGEN worden automatisch verwijderd.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/toegang.php';

const BEZOEKERS_BEWAAR_DAGEN = 30;
const BEZOEKERS_MAX_DAGBESTAND = 20971520; // 20 MB: daarboven stopt het loggen die dag (bescherming tegen vollopen)
const BEZOEKERS_LAND_OPNIEUW_SEC = 86400;  // niet-gevonden land na een dag opnieuw proberen

/** Staat het bezoekerslog aan? (uit te zetten met define('CMS_BEZOEKERS_LOG', false) in config.php) */
function bezoekers_log_aan() {
    return !defined('CMS_BEZOEKERS_LOG') || CMS_BEZOEKERS_LOG;
}

function bezoekers_map() {
    return CMS_DATA_DIR . '/bezoekers';
}

/** Tekst veilig maken voor één tab-gescheiden logregel. */
function bezoekers_schoon($tekst, $max) {
    $tekst = preg_replace('/[\x00-\x1F\x7F]+/', ' ', (string) $tekst);
    if (function_exists('mb_substr')) {
        return mb_substr($tekst, 0, $max, 'UTF-8');
    }
    return substr($tekst, 0, $max);
}

/** Legt het huidige verzoek vast. $geblokkeerd = true als de bezoeker net geweigerd is. */
function bezoek_registreren($geblokkeerd = false) {
    if (!bezoekers_log_aan()) { return; }
    $ip = client_ip();
    if ($ip === '' || filter_var($ip, FILTER_VALIDATE_IP) === false) { return; }

    $map = bezoekers_map();
    if (!is_dir($map)) {
        if (!@mkdir($map, 0755, true)) { return; }
        @file_put_contents($map . '/.htaccess', "Require all denied\n");
    }

    $bestand = $map . '/' . date('Y-m-d') . '.log';
    if (is_file($bestand) && filesize($bestand) > BEZOEKERS_MAX_DAGBESTAND) { return; }

    $pad = parse_url((string) ($_SERVER['REQUEST_URI'] ?? '/'), PHP_URL_PATH);
    $regel = implode("\t", array(
        time(),
        $ip,
        bezoekers_schoon(client_land(), 2),
        bezoekers_schoon($pad ?: '/', 150),
        bezoekers_schoon($_SERVER['HTTP_USER_AGENT'] ?? '', 200),
        $geblokkeerd ? '1' : '0',
    )) . "\n";
    @file_put_contents($bestand, $regel, FILE_APPEND | LOCK_EX);

    // Af en toe oude logbestanden opruimen.
    if (mt_rand(1, 200) === 1) { bezoekers_opruimen(); }
}

/** Verwijdert logbestanden ouder dan de bewaartermijn. */
function bezoekers_opruimen() {
    $grens = date('Y-m-d', time() - BEZOEKERS_BEWAAR_DAGEN * 86400);
    foreach ((array) glob(bezoekers_map() . '/*.log') as $f) {
        if (basename($f, '.log') < $grens) { @unlink($f); }
    }
}

/** Wist het complete bezoekerslog (knop in het beheer). */
function bezoekers_wissen() {
    foreach ((array) glob(bezoekers_map() . '/*.log') as $f) { @unlink($f); }
}

/** Lijkt deze user-agent op een zoekmachine of andere bot? */
function bezoekers_is_bot($ua) {
    if (trim($ua) === '') { return true; }
    return (bool) preg_match('/bot|crawl|spider|slurp|scan|fetch|curl|wget|python|go-http|java\/|php\/|headless|lighthouse|facebookexternalhit|preview|monitor|uptime|httpclient|axios|okhttp/i', $ua);
}

/**
 * Telt de logregels van de afgelopen $dagen op tot één rij per IP.
 * Resultaat: [ip => [ip, land, aantal, geblokkeerd, eerste, laatste, pad, ua, bot]],
 * gesorteerd op laatst gezien (nieuwste eerst).
 */
function bezoekers_overzicht($dagen) {
    $dagen = max(1, (int) $dagen);
    $vanaf = time() - $dagen * 86400;
    $grens = date('Y-m-d', $vanaf);
    $rijen = array();

    foreach ((array) glob(bezoekers_map() . '/*.log') as $f) {
        if (basename($f, '.log') < $grens) { continue; }
        $fp = @fopen($f, 'rb');
        if (!$fp) { continue; }
        while (($regel = fgets($fp)) !== false) {
            $d = explode("\t", rtrim($regel, "\n"));
            if (count($d) < 6) { continue; }
            list($t, $ip, $land, $pad, $ua, $gb) = $d;
            $t = (int) $t;
            if ($t < $vanaf) { continue; }

            if (!isset($rijen[$ip])) {
                $rijen[$ip] = array('ip' => $ip, 'land' => '', 'aantal' => 0, 'geblokkeerd' => 0,
                    'eerste' => $t, 'laatste' => 0, 'pad' => '', 'ua' => '', 'bot' => false);
            }
            $r =& $rijen[$ip];
            $r['aantal']++;
            if ($gb === '1') { $r['geblokkeerd']++; }
            if ($t < $r['eerste']) { $r['eerste'] = $t; }
            if ($t >= $r['laatste']) {
                $r['laatste'] = $t;
                $r['pad'] = $pad;
                $r['ua'] = $ua;
                $r['bot'] = bezoekers_is_bot($ua); // op basis van het laatste verzoek
            }
            if ($land !== '') { $r['land'] = strtoupper($land); }
            unset($r);
        }
        fclose($fp);
    }

    // Landen die bij het bezoek onbekend waren aanvullen: eerst uit de eigen
    // MaxMind-database (lokaal, niets naar buiten), anders uit de opzoekcache.
    $cache = load_json('bezoekers-land.json', array());
    $mmdb = function_exists('geoip_mmdb_beschikbaar') && geoip_mmdb_beschikbaar();
    foreach ($rijen as $ip => $r) {
        if ($r['land'] !== '') { continue; }
        if ($mmdb && ($l = geoip_land_voor_ip($ip)) !== '') {
            $rijen[$ip]['land'] = $l;
        } elseif (!empty($cache[$ip]['land'])) {
            $rijen[$ip]['land'] = $cache[$ip]['land'];
        }
    }

    uasort($rijen, function ($a, $b) { return $b['laatste'] - $a['laatste']; });
    return $rijen;
}

/**
 * Zoekt landen op voor IP's zonder landcode (max. 100 per keer, één verzoek
 * naar api.country.is). Werkt de cache bij en geeft het aantal nieuw gevonden terug.
 */
function bezoekers_landen_aanvullen(array $rijen) {
    if (defined('CMS_BEZOEKERS_LAND_OPZOEKEN') && !CMS_BEZOEKERS_LAND_OPZOEKEN) { return 0; }
    // Met een eigen MaxMind-database is de externe dienst niet nodig.
    if (function_exists('geoip_mmdb_beschikbaar') && geoip_mmdb_beschikbaar()) { return 0; }
    if (!function_exists('curl_init')) { return 0; }

    $cache = load_json('bezoekers-land.json', array());
    $nu = time();
    // Na een mislukte verbinding een uur wachten, zodat het beheer niet steeds traag wordt.
    if (($nu - (int) ($cache['_pauze'] ?? 0)) < 3600) { return 0; }
    unset($cache['_pauze']);
    $zoeken = array();
    foreach ($rijen as $ip => $r) {
        if ($r['land'] !== '') { continue; }
        if (isset($cache[$ip]) && ($nu - (int) ($cache[$ip]['t'] ?? 0)) < BEZOEKERS_LAND_OPNIEUW_SEC) { continue; }
        // Interne/privé-adressen hebben geen land.
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) { continue; }
        $zoeken[] = $ip;
        if (count($zoeken) >= 100) { break; }
    }
    if (!$zoeken) { return 0; }

    $ch = curl_init('https://api.country.is/');
    curl_setopt_array($ch, array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => json_encode(array_values($zoeken)),
        CURLOPT_HTTPHEADER     => array('Content-Type: application/json', 'Accept: application/json'),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 3,
        CURLOPT_TIMEOUT        => 5,
        CURLOPT_FOLLOWLOCATION => false,
    ));
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $gevonden = array();
    if ($status === 200 && is_string($body)) {
        $data = json_decode($body, true);
        if (is_array($data) && isset($data['ip'])) { $data = array($data); } // enkel antwoord
        foreach ((array) $data as $item) {
            if (is_array($item) && isset($item['ip'], $item['country'])
                && preg_match('/^[A-Za-z]{2}$/', (string) $item['country'])) {
                $gevonden[(string) $item['ip']] = strtoupper($item['country']);
            }
        }
    } elseif ($status === 0 || $status >= 500 || $status === 429) {
        // Geen verbinding of dienst overbelast: niets als "niet gevonden" markeren, over een uur opnieuw.
        $cache['_pauze'] = $nu;
        save_json('bezoekers-land.json', $cache, false);
        return 0;
    }

    foreach ($zoeken as $ip) {
        $cache[$ip] = array('land' => $gevonden[$ip] ?? '', 't' => $nu);
    }

    // Cache klein houden: alleen IP's die nog in het log staan.
    $bekend = bezoekers_overzicht_ips();
    foreach (array_keys($cache) as $ip) {
        if (!isset($bekend[$ip])) { unset($cache[$ip]); }
    }
    save_json('bezoekers-land.json', $cache, false);
    return count($gevonden);
}

/** Alle IP's binnen de bewaartermijn (alleen voor het opschonen van de cache). */
function bezoekers_overzicht_ips() {
    $ips = array();
    foreach ((array) glob(bezoekers_map() . '/*.log') as $f) {
        $fp = @fopen($f, 'rb');
        if (!$fp) { continue; }
        while (($regel = fgets($fp)) !== false) {
            $d = explode("\t", $regel, 3);
            if (isset($d[1])) { $ips[$d[1]] = true; }
        }
        fclose($fp);
    }
    return $ips;
}

/** Landnaam in het Nederlands (via intl als dat er is), anders de code. */
function bezoekers_landnaam($code) {
    if ($code === '') { return ''; }
    if (class_exists('Locale')) {
        $naam = Locale::getDisplayRegion('-' . $code, 'nl');
        if ($naam && $naam !== $code) { return $naam; }
    }
    return $code;
}

/** Vlag-emoji bij een landcode. */
function bezoekers_vlag($code) {
    if (!preg_match('/^[A-Z]{2}$/', $code) || !function_exists('mb_chr')) { return ''; }
    return mb_chr(0x1F1E6 + ord($code[0]) - 65, 'UTF-8') . mb_chr(0x1F1E6 + ord($code[1]) - 65, 'UTF-8');
}

/* -------- blokkeren / deblokkeren vanuit de lijst -------- */

/** Staat dit exacte IP (geen bereik) op de lijst van $scope? */
function toegang_ip_exact_op_lijst($ip, $scope) {
    $regels = toegang_regels();
    foreach ($regels[$scope]['ips'] as $r) {
        if (strpos($r, '/') === false && ip_matcht($ip, $r)) { return true; }
    }
    return false;
}

/**
 * Blokkeert (of deblokkeert) één IP voor zowel de website als het beheer.
 * Geeft '' bij succes, anders een foutmelding.
 */
function toegang_ip_instellen($ip, $blokkeren) {
    $ip = trim((string) $ip);
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
        return 'Ongeldig IP-adres.';
    }
    if ($blokkeren) {
        if (ip_op_whitelist($ip)) {
            return 'Dit IP-adres staat op de whitelist en kan niet geblokkeerd worden.';
        }
        if (ip_matcht(client_ip(), $ip)) {
            return 'Dit is uw eigen IP-adres. Dat kunt u niet blokkeren.';
        }
    }

    $d = load_json('toegang.json', array());
    foreach (array('site', 'beheer') as $scope) {
        $ips = (isset($d[$scope]['ips']) && is_array($d[$scope]['ips'])) ? $d[$scope]['ips'] : array();
        // Eerst alle exacte vermeldingen van dit IP weghalen (ook andere schrijfwijzen van IPv6).
        $ips = array_values(array_filter($ips, function ($r) use ($ip) {
            return strpos($r, '/') !== false || !ip_matcht($ip, $r);
        }));
        if ($blokkeren) { $ips[] = $ip; }
        if (!isset($d[$scope]) || !is_array($d[$scope])) { $d[$scope] = array('landen' => array()); }
        $d[$scope]['ips'] = $ips;
    }
    return save_json('toegang.json', $d) ? '' : 'Opslaan mislukte. Is de map data/ schrijfbaar?';
}

/**
 * Status van een IP ten opzichte van de toegangsregels, voor de lijst.
 * $regels en $whitelist komen uit toegang_regels() / toegang_whitelist(), één keer
 * geladen door de aanroeper (scheelt honderden bestandslezingen bij een lange lijst).
 * Geeft [code, tekst]: eigen | whitelist | ip | bereik | land | land-beheer | open
 */
function toegang_ip_status($ip, $land, $mijnIp, array $regels, array $whitelist) {
    if ($mijnIp !== '' && ip_matcht($mijnIp, $ip)) { return array('eigen', 'Uw IP-adres'); }
    foreach ($whitelist as $r) {
        if (ip_matcht($ip, $r)) { return array('whitelist', 'Whitelist'); }
    }
    $bereik = false;
    foreach (array('site', 'beheer') as $scope) {
        foreach ($regels[$scope]['ips'] as $r) {
            if (!ip_matcht($ip, $r)) { continue; }
            if (strpos($r, '/') === false) { return array('ip', 'Geblokkeerd'); }
            $bereik = true;
        }
    }
    if ($bereik) { return array('bereik', 'Geblokkeerd (bereik)'); }
    if ($land !== '') {
        if (in_array($land, $regels['site']['landen'], true)) { return array('land', 'Land geblokkeerd'); }
        if (in_array($land, $regels['beheer']['landen'], true)) { return array('land-beheer', 'Toegang (beheer geblokkeerd)'); }
    }
    return array('open', 'Toegang');
}
