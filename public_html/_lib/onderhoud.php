<?php
/** Onderhoudsmodus voor de publieke website. */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';

function onderhoud_stand() {
    $stand = load_json('onderhoud.json', array());
    return array(
        'actief' => array_key_exists('actief', $stand)
            ? (bool) $stand['actief']
            : (defined('CMS_ONDERHOUD_STANDAARD') && CMS_ONDERHOUD_STANDAARD),
        'gewijzigd' => (string) ($stand['gewijzigd'] ?? ''),
        'door' => (string) ($stand['door'] ?? ''),
    );
}

function onderhoud_actief() {
    if (!defined('CMS_MODULE_ONDERHOUD') || !CMS_MODULE_ONDERHOUD) { return false; }
    $stand = onderhoud_stand();
    return $stand['actief'];
}

function onderhoud_zetten($aan, $door = '') {
    return save_json('onderhoud.json', array(
        'actief' => (bool) $aan,
        'gewijzigd' => date('c'),
        'door' => substr(trim((string) $door), 0, 120),
    ), false);
}

/**
 * Start alleen een bestaande beheersessie. Gewone bezoekers krijgen hierdoor
 * geen sessiecookie en worden niet onnodig gevolgd.
 */
function onderhoud_beheerder_ingelogd() {
    if (session_status() === PHP_SESSION_NONE) {
        $cookieNaam = session_name();
        if ($cookieNaam === '' || empty($_COOKIE[$cookieNaam])) { return false; }
        require_once __DIR__ . '/inline_editor.php';
        sessie_boot_publiek();
    } else {
        require_once __DIR__ . '/auth.php';
    }
    return function_exists('is_logged_in') && is_logged_in();
}

/** Stopt een publiek verzoek met een correcte tijdelijke 503-respons. */
function onderhoud_toepassen() {
    if (!onderhoud_actief() || onderhoud_beheerder_ingelogd()) { return; }

    http_response_code(503);
    header('Retry-After: 3600');
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('X-Robots-Tag: noindex, nofollow');

    $template = defined('CMS_ONDERHOUD_TEMPLATE') ? trim((string) CMS_ONDERHOUD_TEMPLATE) : '';
    if ($template !== '') {
        $root = dirname(__DIR__);
        $pad = $root . '/' . ltrim($template, '/');
        if (is_file($pad) && strpos(realpath($pad), realpath($root) . DIRECTORY_SEPARATOR) === 0) {
            require $pad;
            exit;
        }
    }

    $naam = defined('CMS_SITE_NAME') ? CMS_SITE_NAME : 'Website';
    ?><!doctype html>
<html lang="nl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1">
  <meta name="robots" content="noindex,nofollow">
  <title>Even niet beschikbaar | <?= e($naam) ?></title>
  <style>body{margin:0;min-height:100vh;display:grid;place-items:center;background:#f5f5f2;color:#20221f;font:16px/1.6 system-ui,sans-serif}main{width:min(34rem,calc(100% - 3rem));padding:2rem;background:#fff;border:1px solid #deded8;border-radius:12px}h1{margin:0 0 .6rem;font-size:clamp(1.8rem,5vw,2.6rem);line-height:1.15}p{margin:0;color:#5d625b}</style>
</head>
<body><main><h1>We zijn zo terug</h1><p>Aan deze website wordt momenteel gewerkt. Probeer het later nog eens.</p></main></body>
</html><?php
    exit;
}
