<?php
/**
 * Landbepaling met een eigen landendatabase, zonder serverextensie.
 * Twee bronnen, te kiezen in /beheer/geoip.php:
 *   - MaxMind (.mmdb)      — account-ID + licentiesleutel
 *   - IP2Location (.BIN)   — download-token (LITE DB1 is gratis)
 *
 * - Instellingen staan in data/geoip.json.
 *   Die map is via .htaccess afgeschermd; zet een GeoIP.conf dus NOOIT los in
 *   public_html, want dan is uw licentiesleutel via de browser op te vragen.
 * - De database wordt gedownload naar data/geoip/<editie>.mmdb, via
 *   https://download.maxmind.com (die stuurt door naar MaxMinds Cloudflare R2-opslag).
 * - Uitlezen: MaxMinds eigen pure-PHP-lezer (_lib/maxmind/, Apache-2.0) of de
 *   eigen IP2Location-lezer (_lib/ip2location.php, werkt zonder bcmath).
 * - Bijwerken: automatisch als de database ouder is dan GEOIP_MAX_LEEFTIJD en
 *   iemand het beheer (Toegang) opent, via de knop in het beheer, of via cron:
 *   php /pad/naar/public_html/_lib/geoip-cron.php
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/ip2location.php';

const GEOIP_MAX_LEEFTIJD = 604800;       // MaxMind: 7 dagen (MaxMind ververst 2x per week)
const GEOIP_I2L_MAX_LEEFTIJD = 2592000;  // IP2Location: 30 dagen (nieuwe versie begin van elke maand)
const GEOIP_BRONNEN = array(
    'maxmind'     => 'MaxMind',
    'ip2location' => 'IP2Location',
);
const GEOIP_I2L_PAKKETTEN = array(
    'DB1LITEBINIPV6' => 'LITE DB1, IPv4 + IPv6 (gratis, aanbevolen)',
    'DB1LITEBIN'     => 'LITE DB1, alleen IPv4 (gratis, kleiner)',
    'DB1BINIPV6'     => 'DB1 commercieel, IPv4 + IPv6',
    'DB1BIN'         => 'DB1 commercieel, alleen IPv4',
);
const GEOIP_EDITIES = array(
    'GeoLite2-Country' => 'GeoLite2 Country (gratis)',
    'GeoIP2-Country'   => 'GeoIP2 Country (betaald, nauwkeuriger)',
);

function geoip_instellingen() {
    $d = load_json('geoip.json', array());
    $editie = (string) ($d['editie'] ?? 'GeoLite2-Country');
    $bron   = (string) ($d['bron'] ?? 'maxmind');
    $pakket = (string) ($d['i2l_pakket'] ?? 'DB1LITEBINIPV6');
    return array(
        'bron'         => array_key_exists($bron, GEOIP_BRONNEN) ? $bron : 'maxmind',
        'i2l_token'    => (string) ($d['i2l_token'] ?? ''),
        'i2l_pakket'   => array_key_exists($pakket, GEOIP_I2L_PAKKETTEN) ? $pakket : 'DB1LITEBINIPV6',
        'account'      => (string) ($d['account'] ?? ''),
        'sleutel'      => (string) ($d['sleutel'] ?? ''),
        'editie'       => array_key_exists($editie, GEOIP_EDITIES) ? $editie : 'GeoLite2-Country',
        'bijgewerkt'   => (int) ($d['bijgewerkt'] ?? 0),
        'poging'       => (int) ($d['poging'] ?? 0),
        'laatste_fout' => (string) ($d['laatste_fout'] ?? ''),
    );
}

function geoip_instellingen_opslaan(array $nieuw) {
    $d = array_merge(load_json('geoip.json', array()), $nieuw);
    return save_json('geoip.json', $d, false);
}

function geoip_map() {
    return CMS_DATA_DIR . '/geoip';
}

/** Bestandsnaam waarin de database van de gekozen bron wordt opgeslagen. */
function geoip_doelbestand(array $inst) {
    return $inst['bron'] === 'ip2location'
        ? geoip_map() . '/ip2location-' . $inst['i2l_pakket'] . '.bin'
        : geoip_map() . '/' . $inst['editie'] . '.mmdb';
}

/** Pad van de actieve database (of '' als er nog geen is). */
function geoip_db_pad() {
    $inst = geoip_instellingen();
    $pad = geoip_doelbestand($inst);
    if (is_file($pad)) { return $pad; }
    if ($inst['bron'] === 'ip2location') {
        foreach (array_keys(GEOIP_I2L_PAKKETTEN) as $p) {
            if (is_file(geoip_map() . '/ip2location-' . $p . '.bin')) { return geoip_map() . '/ip2location-' . $p . '.bin'; }
        }
        return '';
    }
    if (is_file($pad)) { return $pad; }
    // Terugval: een andere editie die er al staat.
    foreach (array_keys(GEOIP_EDITIES) as $e) {
        if (is_file(geoip_map() . '/' . $e . '.mmdb')) { return geoip_map() . '/' . $e . '.mmdb'; }
    }
    return '';
}

function geoip_mmdb_beschikbaar() {
    return geoip_db_pad() !== '';
}

/** Verplichte bronvermelding wanneer de meegeleverde IP2Location LITE-data wordt gebruikt. */
function geoip_attributie_html($siteNaam = '') {
    $inst = geoip_instellingen();
    if (($inst['bron'] ?? '') !== 'ip2location' && strpos(basename(geoip_db_pad()), 'ip2location-') !== 0) { return ''; }
    $siteNaam = trim((string) $siteNaam);
    if ($siteNaam === '') { $siteNaam = defined('CMS_SITE_NAME') ? CMS_SITE_NAME : 'Deze website'; }
    return e($siteNaam) . ' uses the IP2Location LITE database for '
        . '<a href="https://www.ip2location.com" target="_blank" rel="noopener noreferrer">IP geolocation</a>.';
}

/** Gedeelde lezer (één keer openen per verzoek). */
function geoip_lezer() {
    static $lezer = null, $geprobeerd = false;
    if ($geprobeerd) { return $lezer; }
    $geprobeerd = true;
    $pad = geoip_db_pad();
    if ($pad === '') { return null; }
    if (substr($pad, -4) === '.bin') {
        $db = i2l_openen($pad);
        $lezer = $db ? array('soort' => 'i2l', 'db' => $db) : null;
        if (!$db) { error_log('GeoIP: IP2Location-bestand niet leesbaar: ' . $pad); }
        return $lezer;
    }
    try {
        geoip_lezer_laden();
        $lezer = array('soort' => 'mmdb', 'lezer' => new \MaxMind\Db\Reader($pad));
    } catch (\Throwable $e) {
        error_log('GeoIP: database niet leesbaar: ' . $e->getMessage());
        $lezer = null;
    }
    return $lezer;
}

function geoip_lezer_laden() {
    if (class_exists('\MaxMind\Db\Reader', false)) { return; }
    require_once __DIR__ . '/maxmind/Reader/InvalidDatabaseException.php';
    require_once __DIR__ . '/maxmind/Reader/Util.php';
    require_once __DIR__ . '/maxmind/Reader/Decoder.php';
    require_once __DIR__ . '/maxmind/Reader/Metadata.php';
    require_once __DIR__ . '/maxmind/Reader.php';
}

/** Landcode (2 letters) voor een IP via de .mmdb, of ''. */
function geoip_land_voor_ip($ip) {
    if (filter_var($ip, FILTER_VALIDATE_IP) === false) { return ''; }
    $lezer = geoip_lezer();
    if (!$lezer) { return ''; }
    if ($lezer['soort'] === 'i2l') { return i2l_land($lezer['db'], $ip); }
    try {
        $r = $lezer['lezer']->get($ip);
    } catch (\Throwable $e) {
        return '';
    }
    if (!is_array($r)) { return ''; }
    $code = $r['country']['iso_code'] ?? $r['registered_country']['iso_code'] ?? '';
    return preg_match('/^[A-Z]{2}$/', (string) $code) ? (string) $code : '';
}

/** Metadata van de actieve database voor het beheer: [editie, builddatum] of null. */
function geoip_db_info() {
    $lezer = geoip_lezer();
    if (!$lezer) { return null; }
    if ($lezer['soort'] === 'i2l') {
        $naam = basename(geoip_db_pad(), '.bin');
        return array(
            'bron'   => 'IP2Location',
            'editie' => GEOIP_I2L_PAKKETTEN[substr($naam, 12)] ?? $naam,
            'build'  => (int) strtotime($lezer['db']['datum'] . ' 00:00:00 UTC'),
            'bytes'  => (int) @filesize(geoip_db_pad()),
        );
    }
    $m = $lezer['lezer']->metadata();
    return array(
        'bron'   => 'MaxMind',
        'editie' => (string) $m->databaseType,
        'build'  => (int) $m->buildEpoch,
        'bytes'  => (int) @filesize(geoip_db_pad()),
    );
}

/* -------- downloaden en bijwerken -------- */

/** HTTP GET met basic-auth; volgt de doorverwijzing naar R2 (zonder de inlog mee te sturen). */
function geoip_http_get($url, $account, $sleutel, $doelBestand = null) {
    if (!function_exists('curl_init')) {
        return array(0, '', 'cURL ontbreekt op deze server.');
    }
    $ch = curl_init($url);
    $fp = null;
    $opties = array(
        CURLOPT_USERPWD           => $account . ':' . $sleutel,
        CURLOPT_HTTPAUTH          => CURLAUTH_BASIC,
        CURLOPT_FOLLOWLOCATION    => true,
        CURLOPT_MAXREDIRS         => 3,
        CURLOPT_UNRESTRICTED_AUTH => false, // inlog NIET naar de R2-opslag sturen
        CURLOPT_CONNECTTIMEOUT    => 10,
        CURLOPT_TIMEOUT           => 120,
        CURLOPT_USERAGENT         => 'CMS-GeoIP-bijwerker/1.0',
    );
    if (defined('CURLOPT_PROTOCOLS')) {
        $opties[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        $opties[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    if ($doelBestand !== null) {
        $fp = fopen($doelBestand, 'wb');
        if (!$fp) { return array(0, '', 'Kan tijdelijk bestand niet schrijven in data/geoip/.'); }
        $opties[CURLOPT_FILE] = $fp;
    } else {
        $opties[CURLOPT_RETURNTRANSFER] = true;
    }
    curl_setopt_array($ch, $opties);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fout = curl_error($ch);
    curl_close($ch);
    if ($fp) { fclose($fp); }
    return array($status, is_string($body) ? $body : '', $fout);
}

/** Haalt uit een tar(.gz) het eerste .mmdb-bestand; geeft de inhoud of null. */
function geoip_mmdb_uit_tar($tarGz) {
    $tar = @gzdecode($tarGz);
    if ($tar === false) { return null; }
    $pos = 0;
    $lengte = strlen($tar);
    while ($pos + 512 <= $lengte) {
        $kop = substr($tar, $pos, 512);
        if (trim($kop, "\0") === '') { break; } // einde archief
        $naam  = rtrim(substr($kop, 0, 100), "\0");
        $prefix = rtrim(substr($kop, 345, 155), "\0");
        if ($prefix !== '') { $naam = $prefix . '/' . $naam; }
        $grootte = octdec(trim(substr($kop, 124, 12), "\0 "));
        $type = $kop[156];
        $pos += 512;
        if (($type === '0' || $type === "\0") && substr($naam, -5) === '.mmdb') {
            return substr($tar, $pos, $grootte);
        }
        $pos += (int) (ceil($grootte / 512) * 512);
    }
    return null;
}

/**
 * Downloadt de database opnieuw. Geeft '' bij succes, anders een foutmelding.
 * Controleert de SHA-256 als MaxMind die aanbiedt, en test de nieuwe database
 * voordat de oude wordt vervangen.
 */
function geoip_bijwerken() {
    $inst = geoip_instellingen();
    @set_time_limit(180);
    $map = geoip_map();
    if (!is_dir($map) && !@mkdir($map, 0755, true)) {
        return 'Kan de map data/geoip/ niet aanmaken.';
    }
    if (!is_file($map . '/.htaccess')) { @file_put_contents($map . '/.htaccess', "Require all denied\n"); }
    return $inst['bron'] === 'ip2location' ? geoip_bijwerken_ip2location($inst) : geoip_bijwerken_maxmind($inst);
}

/** MaxMind-download (zie geoip_bijwerken). */
function geoip_bijwerken_maxmind(array $inst) {
    if ($inst['account'] === '' || $inst['sleutel'] === '') {
        return 'Vul eerst uw MaxMind account-ID en licentiesleutel in.';
    }
    $map = geoip_map();

    geoip_instellingen_opslaan(array('poging' => time()));
    $basis = 'https://download.maxmind.com/geoip/databases/' . rawurlencode($inst['editie']) . '/download?suffix=';
    $tmpGz = $map . '/download.tar.gz.tmp';

    list($status, , $fout) = geoip_http_get($basis . 'tar.gz', $inst['account'], $inst['sleutel'], $tmpGz);
    if ($status !== 200) {
        @unlink($tmpGz);
        $melding = $status === 401 ? 'MaxMind weigert de inlog (401): controleer account-ID en licentiesleutel.'
                 : ($status === 403 ? 'Geen toegang tot deze database-editie (403). Controleer of uw abonnement deze editie omvat.'
                 : ($status === 0   ? 'Geen verbinding met MaxMind: ' . $fout . ' (staat uitgaand verkeer naar download.maxmind.com en *.r2.cloudflarestorage.com open?)'
                 : 'Download mislukt (HTTP ' . $status . ').'));
        geoip_instellingen_opslaan(array('laatste_fout' => $melding));
        return $melding;
    }
    $gz = (string) @file_get_contents($tmpGz);
    @unlink($tmpGz);

    // SHA-256 controleren (als beschikbaar).
    list($sStatus, $sBody) = geoip_http_get($basis . 'tar.gz.sha256', $inst['account'], $inst['sleutel']);
    if ($sStatus === 200 && preg_match('/^([a-f0-9]{64})/i', trim($sBody), $m)) {
        if (!hash_equals(strtolower($m[1]), hash('sha256', $gz))) {
            $melding = 'Controlegetal (SHA-256) klopt niet; de download is niet gebruikt.';
            geoip_instellingen_opslaan(array('laatste_fout' => $melding));
            return $melding;
        }
    }

    $mmdb = geoip_mmdb_uit_tar($gz);
    if ($mmdb === null || strlen($mmdb) < 1024) {
        $melding = 'Het gedownloade archief bevat geen geldige .mmdb.';
        geoip_instellingen_opslaan(array('laatste_fout' => $melding));
        return $melding;
    }

    $doel = $map . '/' . $inst['editie'] . '.mmdb';
    $tmp  = $doel . '.tmp';
    if (@file_put_contents($tmp, $mmdb) === false) {
        return 'Kan de database niet opslaan in data/geoip/.';
    }
    // Eerst testen, dan pas vervangen.
    try {
        geoip_lezer_laden();
        $test = new \MaxMind\Db\Reader($tmp);
        $test->get('8.8.8.8');
        $test->close();
    } catch (\Throwable $e) {
        @unlink($tmp);
        $melding = 'Nieuwe database is onleesbaar: ' . $e->getMessage();
        geoip_instellingen_opslaan(array('laatste_fout' => $melding));
        return $melding;
    }
    if (!@rename($tmp, $doel)) {
        @unlink($tmp);
        return 'Kan de database niet vervangen in data/geoip/.';
    }
    geoip_instellingen_opslaan(array('bijgewerkt' => time(), 'laatste_fout' => ''));
    return '';
}

/** Moet de database (automatisch) worden bijgewerkt? Max. één poging per uur. */
function geoip_bijwerken_nodig() {
    $inst = geoip_instellingen();
    if (!geoip_gegevens_ingevuld($inst)) { return false; }
    $i2l = $inst['bron'] === 'ip2location';
    // IP2Location beperkt het aantal downloads per dag: na een poging langer wachten.
    if (time() - $inst['poging'] < ($i2l ? 21600 : 3600)) { return false; }
    $pad = geoip_doelbestand($inst);
    return !is_file($pad) || (time() - (int) filemtime($pad)) > ($i2l ? GEOIP_I2L_MAX_LEEFTIJD : GEOIP_MAX_LEEFTIJD);
}

/** Zijn de inloggegevens voor de gekozen bron ingevuld? */
function geoip_gegevens_ingevuld(array $inst) {
    return $inst['bron'] === 'ip2location'
        ? $inst['i2l_token'] !== ''
        : ($inst['account'] !== '' && $inst['sleutel'] !== '');
}

/* -------- IP2Location -------- */

/** HTTP POST (formulier) naar ip2location.com; schrijft naar $doelBestand of geeft de body terug. */
function geoip_http_post($url, array $velden, $doelBestand = null) {
    if (!function_exists('curl_init')) {
        return array(0, '', 'cURL ontbreekt op deze server.');
    }
    $ch = curl_init($url);
    $fp = null;
    $opties = array(
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => http_build_query($velden),
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_MAXREDIRS      => 3,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_TIMEOUT        => 180,
        CURLOPT_USERAGENT      => 'CMS-GeoIP-bijwerker/1.0',
    );
    if (defined('CURLOPT_PROTOCOLS')) {
        $opties[CURLOPT_PROTOCOLS] = CURLPROTO_HTTPS;
        $opties[CURLOPT_REDIR_PROTOCOLS] = CURLPROTO_HTTPS;
    }
    if ($doelBestand !== null) {
        $fp = fopen($doelBestand, 'wb');
        if (!$fp) { return array(0, '', 'Kan tijdelijk bestand niet schrijven in data/geoip/.'); }
        $opties[CURLOPT_FILE] = $fp;
    } else {
        $opties[CURLOPT_RETURNTRANSFER] = true;
    }
    curl_setopt_array($ch, $opties);
    $body = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $fout = curl_error($ch);
    curl_close($ch);
    if ($fp) { fclose($fp); }
    return array($status, is_string($body) ? $body : '', $fout);
}

/**
 * Haalt het eerste .BIN-bestand uit een zip. Gebruikt ZipArchive als dat er is,
 * anders een eenvoudige eigen uitpakker (stored/deflate), zodat er geen extensie nodig is.
 */
function geoip_bin_uit_zip($zipPad) {
    if (class_exists('ZipArchive')) {
        $zip = new ZipArchive();
        if ($zip->open($zipPad) === true) {
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $naam = (string) $zip->getNameIndex($i);
                if (strtoupper(substr($naam, -4)) === '.BIN') {
                    $data = $zip->getFromIndex($i);
                    $zip->close();
                    return $data === false ? null : $data;
                }
            }
            $zip->close();
        }
        return null;
    }

    $zip = (string) @file_get_contents($zipPad);
    $eocd = strrpos($zip, "PK\x05\x06");
    if ($eocd === false) { return null; }
    $aantal = unpack('v', substr($zip, $eocd + 10, 2))[1];
    $pos    = unpack('V', substr($zip, $eocd + 16, 4))[1];
    for ($i = 0; $i < $aantal; $i++) {
        if (substr($zip, $pos, 4) !== "PK\x01\x02") { return null; }
        $methode = unpack('v', substr($zip, $pos + 10, 2))[1];
        $gecompr = unpack('V', substr($zip, $pos + 20, 4))[1];
        $nLen    = unpack('v', substr($zip, $pos + 28, 2))[1];
        $eLen    = unpack('v', substr($zip, $pos + 30, 2))[1];
        $cLen    = unpack('v', substr($zip, $pos + 32, 2))[1];
        $lokaal  = unpack('V', substr($zip, $pos + 42, 4))[1];
        $naam    = substr($zip, $pos + 46, $nLen);
        $pos    += 46 + $nLen + $eLen + $cLen;
        if (strtoupper(substr($naam, -4)) !== '.BIN') { continue; }

        $lnLen = unpack('v', substr($zip, $lokaal + 26, 2))[1];
        $leLen = unpack('v', substr($zip, $lokaal + 28, 2))[1];
        $ruw   = substr($zip, $lokaal + 30 + $lnLen + $leLen, $gecompr);
        if ($methode === 0) { return $ruw; }
        if ($methode === 8) { $data = @gzinflate($ruw); return $data === false ? null : $data; }
        return null;
    }
    return null;
}

/** IP2Location-download (zie geoip_bijwerken). */
function geoip_bijwerken_ip2location(array $inst) {
    if ($inst['i2l_token'] === '') {
        return 'Vul eerst uw IP2Location download-token in.';
    }
    geoip_instellingen_opslaan(array('poging' => time()));
    $map = geoip_map();
    $fout = function ($melding) {
        geoip_instellingen_opslaan(array('laatste_fout' => $melding));
        return $melding;
    };

    // 1. Token en pakket controleren (zelfde werkwijze als IP2Locations eigen downloader).
    list($status, $info, $netFout) = geoip_http_post('https://www.ip2location.com/download-info',
        array('token' => $inst['i2l_token'], 'package' => $inst['i2l_pakket']));
    if ($status === 0) {
        return $fout('Geen verbinding met ip2location.com: ' . $netFout);
    }
    $delen = explode(';', trim($info));
    if (($delen[0] ?? '') === 'EXPIRED') {
        return $fout('Het IP2Location-abonnement is verlopen' . (!empty($delen[1]) ? ' sinds ' . $delen[1] : '') . '.');
    }
    if (($delen[0] ?? '') === 'NOPERMISSION') {
        return $fout('Deze token mag dit pakket (' . $inst['i2l_pakket'] . ') niet downloaden. Kies een LITE-pakket of controleer de token.');
    }
    if (($delen[0] ?? '') !== 'OK') {
        return $fout('IP2Location gaf een onverwacht antwoord: ' . substr(trim(strip_tags($info)), 0, 120));
    }
    $verwacht = (int) ($delen[3] ?? 0);

    // 2. Zip downloaden.
    $tmpZip = $map . '/ip2location-download.zip.tmp';
    list($status, , $netFout) = geoip_http_post('https://www.ip2location.com/download',
        array('token' => $inst['i2l_token'], 'file' => $inst['i2l_pakket']), $tmpZip);
    $grootte = (int) @filesize($tmpZip);
    if ($status !== 200 || $grootte < 1024) {
        $tekst = $grootte > 0 && $grootte < 1024 ? trim(strip_tags((string) @file_get_contents($tmpZip))) : '';
        @unlink($tmpZip);
        if ($status === 0) { return $fout('Download mislukt: ' . $netFout); }
        return $fout('Download mislukt' . ($tekst !== '' ? ': ' . substr($tekst, 0, 160) : ' (HTTP ' . $status . ').')
            . (stripos($tekst, 'TIMES') !== false ? ' (Downloadlimiet bereikt; probeer het later opnieuw.)' : ''));
    }
    if ($verwacht > 0 && $grootte !== $verwacht) {
        @unlink($tmpZip);
        return $fout('Download onvolledig (' . $grootte . ' van ' . $verwacht . ' bytes).');
    }

    // 3. Uitpakken, testen, pas dan vervangen.
    $bin = geoip_bin_uit_zip($tmpZip);
    @unlink($tmpZip);
    if ($bin === null || strlen($bin) < 1024) {
        return $fout('Het gedownloade archief bevat geen geldig .BIN-bestand.');
    }
    $doel = geoip_doelbestand($inst);
    $tmp  = $doel . '.tmp';
    if (@file_put_contents($tmp, $bin) === false) {
        return $fout('Kan de database niet opslaan in data/geoip/.');
    }
    $test = i2l_openen($tmp);
    $ok = $test && i2l_land($test, '8.8.8.8') !== '';
    if ($test) { i2l_sluiten($test); }
    if (!$ok) {
        @unlink($tmp);
        return $fout('Nieuwe IP2Location-database is onleesbaar.');
    }
    if (!@rename($tmp, $doel)) {
        @unlink($tmp);
        return $fout('Kan de database niet vervangen in data/geoip/.');
    }
    // Bestanden van een ander pakket opruimen.
    foreach ((array) glob($map . '/ip2location-*.bin') as $f) {
        if ($f !== $doel) { @unlink($f); }
    }
    geoip_instellingen_opslaan(array('bijgewerkt' => time(), 'laatste_fout' => ''));
    return '';
}
