<?php
/** Inlog, sessie, CSRF-bescherming en eenvoudige rate-limiting. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/helpers.php'; // csrf_field() gebruikt e()
require_once __DIR__ . '/toegang.php';

/** Start de sessie met veilige cookie-instellingen en zorgt voor een CSRF-token. */
function auth_boot() {
    // Geblokkeerde IP's/landen weren van het beheer — vóór er iets anders gebeurt.
    blokkeer_indien_nodig('beheer');

    if (session_status() === PHP_SESSION_NONE) {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (($_SERVER['SERVER_PORT'] ?? '') == 443);
        session_set_cookie_params(array(
            'lifetime' => 0,
            'path'     => '/',
            'httponly' => true,
            'samesite' => 'Lax',
            'secure'   => $https,
        ));
        session_start();
    }
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
}

/** Verborgen CSRF-veld voor in formulieren. */
function csrf_field() {
    return '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf'] ?? '') . '">';
}

/** Controleert het CSRF-token van een POST. */
function csrf_check() {
    return isset($_POST['csrf'])
        && is_string($_POST['csrf'])
        && hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf']);
}

/* -------- accounts en wachtwoorden --------

   Het oorspronkelijke beheeraccount blijft bewust in auth.json staan. Daardoor
   blijft het bestaande wachtwoord werken zonder een risicovolle migratie; het
   logt in met een eigen gebruikersnaam (ook in auth.json).
   Afzonderlijke accounts staan in users.json en loggen in met gebruikersnaam
   of e-mailadres plus wachtwoord. Rollen en rechten blijven generiek zodat dit
   CMS zonder klant-specifieke aannames kan worden hergebruikt. */

/** Minimale lengte van een nieuw wachtwoord (bestaande, kortere wachtwoorden blijven werken). */
const WACHTWOORD_MIN = 12;

function password_is_set() {
    $a = load_json('auth.json', array());
    return !empty($a['hash']);
}

function set_password($plain) {
    $data = load_json('auth.json', array());
    $data['hash'] = password_hash($plain, PASSWORD_DEFAULT);
    $data['sessie_versie'] = auth_nieuwe_sessie_versie('beheerder');
    return save_json('auth.json', $data, false);
}

function verify_password($plain) {
    $a = load_json('auth.json', array());
    if (empty($a['hash'])) { return false; }
    return password_verify((string) $plain, $a['hash']);
}

/* -------- gebruikersnaam van de vaste beheerder --------

   De vaste beheerder (auth.json) krijgt een eigen gebruikersnaam, los van de
   aanvullende accounts in users.json. Een gebruikersnaam mag
   geen @ bevatten, zodat hij nooit kan botsen met een e-mailaccount.
   Zolang er nog geen gebruikersnaam is ingesteld, werkt de oude manier
   (veld leeg laten of "beheerder") nog, zodat niemand buitengesloten raakt. */

/** Ingestelde gebruikersnaam van de vaste beheerder ('' = nog niet ingesteld). */
function beheerder_gebruikersnaam() {
    $a = load_json('auth.json', array());
    return trim((string) ($a['gebruikersnaam'] ?? ''));
}

/** Controleert een gewenste gebruikersnaam; geeft een foutmelding of '' terug. */
function gebruikersnaam_fout($naam) {
    $naam = trim((string) $naam);
    if ($naam === '') {
        return 'Vul een gebruikersnaam in.';
    }
    if (strlen($naam) < 3 || strlen($naam) > 40) {
        return 'De gebruikersnaam moet 3 tot 40 tekens lang zijn.';
    }
    if (!preg_match('/^[A-Za-z0-9._-]+$/', $naam)) {
        return 'Gebruik alleen letters, cijfers, punt, streepje of liggend streepje (geen spaties of @).';
    }
    return '';
}

/**
 * Slaat gebruikersnaam en (optioneel) nieuw wachtwoord van de vaste beheerder
 * in één keer op. $plain = null laat het bestaande wachtwoord ongemoeid.
 */
function beheerder_inlog_opslaan($gebruikersnaam, $plain = null) {
    $data = load_json('auth.json', array());
    $data['gebruikersnaam'] = trim((string) $gebruikersnaam);
    if ($plain !== null) {
        $data['hash'] = password_hash((string) $plain, PASSWORD_DEFAULT);
        $data['sessie_versie'] = auth_nieuwe_sessie_versie('beheerder');
    }
    return save_json('auth.json', $data, false);
}

/** Sessiedata van de vaste beheerder. */
function beheerder_sessiedata() {
    $naam = beheerder_gebruikersnaam();
    return array(
        'id'    => 'beheerder',
        'email' => '',
        'naam'  => $naam !== '' ? $naam : 'Beheerder',
        'rol'   => 'admin',
    );
}

/** Normaliseert een e-mailadres voor gebruik als stabiele accountsleutel. */
function auth_email_normaliseren($email) {
    $email = strtolower(trim((string) $email));
    return filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : '';
}

function auth_rollen() {
    return array(
        'admin' => 'Beheerder',
        'redacteur' => 'Redacteur',
        'seo' => 'SEO-redacteur',
    );
}

function auth_capabilities($rol) {
    $rechten = array(
        'admin' => array('dashboard', 'content', 'seo', 'media', 'forms', 'settings', 'users', 'security', 'backup'),
        'redacteur' => array('dashboard', 'content', 'seo', 'media', 'forms'),
        'seo' => array('dashboard', 'seo'),
    );
    return $rechten[$rol] ?? array();
}

/**
 * Sleutel van een account in users.json: een e-mailadres
 * of een gebruikersnaam zonder @ (bijv. "richard"), altijd in kleine letters.
 * Geeft '' bij ongeldige invoer.
 */
function auth_sleutel_normaliseren($invoer) {
    $invoer = trim((string) $invoer);
    if (strpos($invoer, '@') !== false) { return auth_email_normaliseren($invoer); }
    return gebruikersnaam_fout($invoer) === '' ? strtolower($invoer) : '';
}

/** Maakt van een veilig opgeslagen gebruikersrecord sessiedata zonder hash. */
function auth_gebruiker_uit_record($email, $record) {
    if (!is_array($record) || empty($record['actief']) || empty($record['hash'])) {
        return null;
    }

    $rol = (string) ($record['rol'] ?? 'redacteur');
    if (!array_key_exists($rol, auth_rollen())) {
        return null;
    }

    return array(
        'id'    => $email,
        'email' => strpos($email, '@') !== false ? $email : '',
        'naam'  => trim((string) ($record['naam'] ?? $email)) ?: $email,
        'rol'   => $rol,
    );
}

/**
 * Controleert een login en geeft veilige sessiedata terug.
 * - Invoer met @ is een e-mailaccount uit users.json.
 * - Invoer zonder @ is de gebruikersnaam van de vaste beheerder.
 * - Zolang die gebruikersnaam nog niet is ingesteld, werkt leeg of "beheerder".
 */
function auth_login_controleren($invoer, $plain) {
    $ingevuld = trim((string) $invoer);

    if (strpos($ingevuld, '@') === false) {
        $ingesteld = beheerder_gebruikersnaam();
        if ($ingesteld !== '') {
            $naamKlopt = hash_equals(strtolower($ingesteld), strtolower($ingevuld));
        } else {
            $naamKlopt = ($ingevuld === '' || strtolower($ingevuld) === 'beheerder');
        }
        if ($naamKlopt) {
            // Wachtwoord altijd controleren, zodat de responstijd niet verraadt
            // of de gebruikersnaam klopte.
            return verify_password($plain) ? beheerder_sessiedata() : null;
        }
        // Geen vaste beheerder: misschien een eigen gebruikersnaam uit users.json.
    }

    $genormaliseerd = auth_sleutel_normaliseren($ingevuld);
    if ($genormaliseerd === '') { return null; }

    $gebruikers = load_json('users.json', array());
    $record = $gebruikers[$genormaliseerd] ?? null;
    $gebruiker = auth_gebruiker_uit_record($genormaliseerd, $record);
    if (!$gebruiker || !password_verify((string) $plain, (string) $record['hash'])) {
        return null;
    }

    return $gebruiker;
}

/* -------- sessiestatus -------- */

/* -------- sessieversie: na een wachtwoordwijziging vervallen andere sessies --------

   Elk account heeft een 'sessie_versie' (auth.json resp. users.json). Bij het
   inloggen gaat die mee in de sessie; wordt het wachtwoord gewijzigd, dan krijgt
   het account een nieuwe versie en is elke oudere sessie van dat account
   ongeldig: die moet opnieuw inloggen. Wie zelf het wachtwoord wijzigt, krijgt
   de nieuwe versie direct in de eigen sessie en blijft dus ingelogd. */

/** Account-id van de sessie ('beheerder' of de sleutel uit users.json). */
function auth_sessie_id() {
    $sessie = $_SESSION['auth_user'] ?? null;
    return is_array($sessie) && ($sessie['id'] ?? '') !== '' ? (string) $sessie['id'] : 'beheerder';
}

/** Huidige sessieversie van een account ('' = nog nooit gewijzigd). */
function auth_sessie_versie($id) {
    if ($id === 'beheerder') {
        $a = load_json('auth.json', array());
        return (string) ($a['sessie_versie'] ?? '');
    }
    $users = load_json('users.json', array());
    return (string) ($users[$id]['sessie_versie'] ?? '');
}

/**
 * Maakt een nieuwe sessieversie voor een account (om op te slaan bij het
 * account). Is dat het account van de huidige sessie, dan krijgt die sessie de
 * nieuwe versie ook, zodat de gebruiker zelf ingelogd blijft.
 */
function auth_nieuwe_sessie_versie($id) {
    $versie = bin2hex(random_bytes(8));
    if (!empty($_SESSION['auth']) && auth_sessie_id() === $id) {
        $_SESSION['auth_versie'] = $versie;
    }
    return $versie;
}

function is_logged_in() {
    if (empty($_SESSION['auth'])) { return false; }
    // Wachtwoord van dit account intussen gewijzigd? Dan is deze sessie verlopen.
    if (!hash_equals(auth_sessie_versie(auth_sessie_id()), (string) ($_SESSION['auth_versie'] ?? ''))) {
        unset($_SESSION['auth'], $_SESSION['auth_user'], $_SESSION['auth_versie']);
        return false;
    }
    return true;
}

/** Huidige gebruiker; oude sessies zonder accountdata blijven beheerder. */
function huidige_gebruiker() {
    if (!is_logged_in()) { return null; }

    $sessie = $_SESSION['auth_user'] ?? null;
    if (!is_array($sessie) || ($sessie['id'] ?? '') === 'beheerder') {
        return beheerder_sessiedata();
    }

    $email = auth_sleutel_normaliseren($sessie['id'] ?? $sessie['email'] ?? '');
    if ($email === '') { return null; }
    $gebruikers = load_json('users.json', array());
    return auth_gebruiker_uit_record($email, $gebruikers[$email] ?? null);
}

function is_admin() {
    $gebruiker = huidige_gebruiker();
    return is_array($gebruiker) && ($gebruiker['rol'] ?? '') === 'admin';
}

/** Accounts met SEO-rechten mogen zoekmachine-instellingen beheren. */
function mag_seo_bewerken() {
    return mag('seo');
}

/** Bestemming na inloggen op basis van de rol. */
function login_bestemming() {
    return mag('dashboard') ? 'index.php' : (mag('seo') ? 'paginas.php' : 'login.php');
}

function require_seo_login() {
    require_login('seo');
}

function mag($recht) {
    $gebruiker = huidige_gebruiker();
    return is_array($gebruiker) && in_array($recht, auth_capabilities($gebruiker['rol'] ?? ''), true);
}

function require_login($recht = null) {
    if (!is_logged_in()) {
        header('Location: login.php');
        exit;
    }
    if ($recht !== null && !mag($recht)) {
        http_response_code(403);
        header('Cache-Control: no-store');
        header('Content-Type: text/html; charset=UTF-8');
        $g = huidige_gebruiker();
        $naam = is_array($g) ? (string) ($g['naam'] ?? '') : '';
        exit('<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<meta name="robots" content="noindex,nofollow"><title>Geen toegang</title>'
            . '<style>body{font-family:system-ui,sans-serif;max-width:34rem;margin:12vh auto;padding:0 1.2rem;line-height:1.55;color:#1a1a1a}'
            . 'a.knop{display:inline-block;margin:.3rem .5rem 0 0;padding:.6rem 1rem;border-radius:8px;background:#1a1a1a;color:#fff;text-decoration:none}'
            . 'a.knop.licht{background:#eee;color:#1a1a1a}</style></head><body>'
            . '<h1 style="font-size:1.4rem">Geen toegang tot dit deel van het beheer</h1>'
            . '<p>U bent ingelogd als <strong>' . e($naam !== '' ? $naam : 'een beperkt account') . '</strong>. Dit account heeft geen rechten voor dit onderdeel.</p>'
            . '<p><a class="knop" href="logout.php">Uitloggen en als beheerder inloggen</a>'
            . '<a class="knop licht" href="index.php">Naar overzicht</a></p></body></html>');
    }
}

function require_admin() { require_login('users'); }

/* -------- rate-limiting per IP (bescherming tegen wachtwoord raden) -------- */

const LOGIN_MAX_POGINGEN = 5;
const LOGIN_VENSTER_SEC   = 900; // 15 minuten

/** Geeft het aantal resterende seconden dat dit IP geblokkeerd is (0 = niet geblokkeerd). */
function login_blocked() {
    $log = load_json('loginlog.json', array());
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'onbekend';
    if (empty($log[$ip])) { return 0; }
    $rec = $log[$ip];
    $first = $rec['first'] ?? 0;
    if ($first + LOGIN_VENSTER_SEC < time()) { return 0; } // venster verlopen
    if (($rec['count'] ?? 0) >= LOGIN_MAX_POGINGEN) {
        return ($first + LOGIN_VENSTER_SEC) - time();
    }
    return 0;
}

function login_fail() {
    $log = load_json('loginlog.json', array());
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'onbekend';
    if (empty($log[$ip]) || ($log[$ip]['first'] ?? 0) + LOGIN_VENSTER_SEC < time()) {
        $log[$ip] = array('count' => 1, 'first' => time());
    } else {
        $log[$ip]['count'] = ($log[$ip]['count'] ?? 0) + 1;
    }
    save_json('loginlog.json', $log);
}

function login_success($gebruiker = null) {
    $log = load_json('loginlog.json', array());
    $ip  = $_SERVER['REMOTE_ADDR'] ?? 'onbekend';
    unset($log[$ip]);
    save_json('loginlog.json', $log);
    session_regenerate_id(true);
    $_SESSION['auth'] = true;
    $_SESSION['auth_user'] = is_array($gebruiker) ? $gebruiker : beheerder_sessiedata();
    $_SESSION['auth_versie'] = auth_sessie_versie(auth_sessie_id());
}

/* -------- accounts beheren (Beheer » Gebruikers) -------- */

/** Klopt het wachtwoord van de ingelogde gebruiker zelf? (bevestiging bij gevoelige acties) */
function auth_eigen_wachtwoord_klopt($plain) {
    $g = huidige_gebruiker();
    if (!$g) { return false; }
    if (($g['id'] ?? '') === 'beheerder') { return verify_password($plain); }
    $users = load_json('users.json', array());
    $rec = $users[$g['id']] ?? null;
    return is_array($rec) && !empty($rec['hash']) && password_verify((string) $plain, (string) $rec['hash']);
}

/** Is deze gebruikersnaam/e-mail al in gebruik (vaste beheerder of users.json)? */
function auth_sleutel_bezet($sleutel) {
    $vast = strtolower(beheerder_gebruikersnaam());
    if ($sleutel === 'beheerder' || ($vast !== '' && $sleutel === $vast)) { return true; }
    $users = load_json('users.json', array());
    return isset($users[$sleutel]);
}
