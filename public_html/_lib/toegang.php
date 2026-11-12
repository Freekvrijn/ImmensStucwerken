<?php
/**
 * Toegangsblokkering op IP-adres en land.
 *
 * Twee losse lijsten (data/toegang.json):
 *   - 'beheer' : wie geen toegang heeft tot /beheer/
 *   - 'site'   : wie geen toegang heeft tot de hele website
 * Elke lijst heeft 'ips' (exacte IP's of CIDR-bereiken) en 'landen' (ISO-landcodes).
 *
 * Het is een BLOKKEERlijst: wie erop staat wordt geweigerd, de rest mag erin.
 *
 * Landbepaling vereist GeoIP (Cloudflare, serverextensie of de MaxMind-database uit
 * /beheer/geoip.php). Zonder werkt IP-blokkering gewoon door; landregels
 * worden dan simpelweg genegeerd (zie geoip_beschikbaar() voor de melding in het beheer).
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/geoip.php';

/** Het IP-adres van de bezoeker. Achter Cloudflare: het echte client-IP. */
function client_ip() {
    if (defined('CMS_ACHTER_CLOUDFLARE') && CMS_ACHTER_CLOUDFLARE && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
        return trim($_SERVER['HTTP_CF_CONNECTING_IP']);
    }
    return $_SERVER['REMOTE_ADDR'] ?? '';
}

/** 2-letter landcode (hoofdletters) van de bezoeker, of '' als onbekend. */
function client_land() {
    if (defined('CMS_ACHTER_CLOUDFLARE') && CMS_ACHTER_CLOUDFLARE && !empty($_SERVER['HTTP_CF_IPCOUNTRY'])) {
        return strtoupper(trim($_SERVER['HTTP_CF_IPCOUNTRY']));
    }
    if (!empty($_SERVER['GEOIP_COUNTRY_CODE'])) {
        return strtoupper(trim($_SERVER['GEOIP_COUNTRY_CODE']));
    }
    if (function_exists('geoip_country_code_by_name')) {
        $ip = client_ip();
        if ($ip !== '') {
            $c = @geoip_country_code_by_name($ip);
            if ($c) { return strtoupper($c); }
        }
    }
    // MaxMind-database in data/geoip/ (zie _lib/geoip.php en /beheer/geoip.php).
    static $cache = null;
    if ($cache === null) { $cache = function_exists('geoip_land_voor_ip') ? geoip_land_voor_ip(client_ip()) : ''; }
    return $cache;
}

/** Kan het land van de bezoeker bepaald worden? (voor de melding in het beheer) */
function geoip_beschikbaar() {
    return (defined('CMS_ACHTER_CLOUDFLARE') && CMS_ACHTER_CLOUDFLARE && isset($_SERVER['HTTP_CF_IPCOUNTRY']))
        || !empty($_SERVER['GEOIP_COUNTRY_CODE'])
        || function_exists('geoip_country_code_by_name')
        || (function_exists('geoip_mmdb_beschikbaar') && geoip_mmdb_beschikbaar());
}

/** Is een regel een geldig IP-adres of CIDR-bereik (IPv4 of IPv6)? */
function geldig_ip_of_cidr($regel) {
    $regel = trim($regel);
    if ($regel === '') { return false; }
    if (strpos($regel, '/') === false) {
        return filter_var($regel, FILTER_VALIDATE_IP) !== false;
    }
    list($net, $bits) = explode('/', $regel, 2);
    if (filter_var($net, FILTER_VALIDATE_IP) === false) { return false; }
    if (!ctype_digit($bits)) { return false; }
    $bits = (int) $bits;
    $max  = (filter_var($net, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false) ? 128 : 32;
    return $bits >= 0 && $bits <= $max;
}

/** Valt $ip binnen een regel (exact IP of CIDR)? Werkt voor IPv4 en IPv6. */
function ip_matcht($ip, $regel) {
    $regel = trim($regel);
    if ($regel === '' || $ip === '') { return false; }

    if (strpos($regel, '/') === false) {
        $a = @inet_pton($ip);
        $b = @inet_pton($regel);
        return $a !== false && $b !== false && $a === $b;
    }

    list($net, $bits) = explode('/', $regel, 2);
    $ipBin  = @inet_pton($ip);
    $netBin = @inet_pton($net);
    if ($ipBin === false || $netBin === false) { return false; }
    if (strlen($ipBin) !== strlen($netBin)) { return false; } // IPv4 vs IPv6

    $bits  = (int) $bits;
    $bytes = intdiv($bits, 8);
    $rest  = $bits % 8;
    if ($bytes > 0 && substr($ipBin, 0, $bytes) !== substr($netBin, 0, $bytes)) { return false; }
    if ($rest > 0) {
        $mask = chr((0xff << (8 - $rest)) & 0xff);
        if ((ord($ipBin[$bytes]) & ord($mask)) !== (ord($netBin[$bytes]) & ord($mask))) { return false; }
    }
    return true;
}

/** De regels per scope. Altijd de vorm ['beheer'=>['ips'=>[],'landen'=>[]], 'site'=>[...]]. */
function toegang_regels() {
    $d = load_json('toegang.json', array());
    $leeg = array('ips' => array(), 'landen' => array());
    $uit = array();
    foreach (array('beheer', 'site') as $scope) {
        $ips    = isset($d[$scope]['ips'])    && is_array($d[$scope]['ips'])    ? $d[$scope]['ips']    : array();
        $landen = isset($d[$scope]['landen']) && is_array($d[$scope]['landen']) ? $d[$scope]['landen'] : array();
        $uit[$scope] = array('ips' => $ips, 'landen' => $landen);
    }
    return $uit;
}

/** Staat het opgegeven IP op de IP-lijst van $scope? */
function ip_op_lijst($ip, $scope) {
    $regels = toegang_regels();
    if (!isset($regels[$scope])) { return false; }
    foreach ($regels[$scope]['ips'] as $r) {
        if (ip_matcht($ip, $r)) { return true; }
    }
    return false;
}

/**
 * De whitelist: IP's/CIDR-bereiken die ALTIJD toegang houden, ongeacht welke
 * blokkades er staan. Eén globale lijst (geldt voor beheer én site).
 */
function toegang_whitelist() {
    $d = load_json('toegang.json', array());
    return (isset($d['whitelist']) && is_array($d['whitelist'])) ? array_values($d['whitelist']) : array();
}

/** Staat het opgegeven IP op de whitelist? */
function ip_op_whitelist($ip) {
    if ($ip === '') { return false; }
    foreach (toegang_whitelist() as $r) {
        if (ip_matcht($ip, $r)) { return true; }
    }
    return false;
}

/** Is de huidige bezoeker geblokkeerd voor $scope ('beheer' of 'site')? */
function is_geblokkeerd($scope) {
    // De whitelist wint altijd: een IP op de witte lijst wordt nooit geweigerd,
    // wat er ook geblokkeerd is. Zo kan de beheerder zichzelf niet buitensluiten.
    if (ip_op_whitelist(client_ip())) { return false; }

    if (ip_op_lijst(client_ip(), $scope)) { return true; }

    $land = client_land();
    if ($land !== '') {
        $regels = toegang_regels();
        foreach ($regels[$scope]['landen'] as $l) {
            if (strtoupper(trim($l)) === $land) { return true; }
        }
    }
    return false;
}

/** Weigert het verzoek met een nette 403 als de bezoeker geblokkeerd is voor $scope. */
function blokkeer_indien_nodig($scope) {
    if (!defined('CMS_TOEGANG_AAN') || !CMS_TOEGANG_AAN) { return; }
    if (!is_geblokkeerd($scope)) { return; }

    // Geweigerde pogingen ook in het bezoekersoverzicht zetten.
    if ((!defined('CMS_MODULE_BEZOEKERS') || CMS_MODULE_BEZOEKERS)) {
        if (!function_exists('bezoek_registreren')) { require_once __DIR__ . '/bezoekers.php'; }
        bezoek_registreren(true);
    }

    if (!headers_sent()) {
        header('HTTP/1.1 403 Forbidden');
        header('Content-Type: text/html; charset=UTF-8');
        header('X-Robots-Tag: noindex, nofollow');
    }
    echo '<!doctype html><html lang="nl"><meta charset="utf-8">'
       . '<meta name="viewport" content="width=device-width, initial-scale=1">'
       . '<title>Geen toegang</title>'
       . '<body style="font-family:system-ui,-apple-system,Arial,sans-serif;max-width:520px;'
       . 'margin:12vh auto;padding:0 1.2rem;color:#1a1a1a;line-height:1.6">'
       . '<h1 style="font-size:1.4rem">Geen toegang</h1>'
       . '<p>U heeft geen toegang tot deze pagina.</p></body></html>';
    exit;
}
