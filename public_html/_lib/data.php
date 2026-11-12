<?php
/** Lezen/schrijven van de JSON-databestanden (met vergrendeling en veilige schrijfactie). */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/versies.php'; // snapshot vóór elke opslag

function data_path($name) {
    return CMS_DATA_DIR . '/' . $name;
}

/**
 * Waarde uit instellingen.json (bv. de contactformulier-instellingen die de klant
 * in het beheer beheert), met terugval op $default als de sleutel leeg is/ontbreekt.
 * Leest het bestand telkens vers, zodat de waarde altijd klopt (het bestandje is
 * klein en wordt maar een handvol keer per verzoek opgevraagd).
 */
function instelling($key, $default = '') {
    $data = load_json('instellingen.json', array());
    return (isset($data[$key]) && $data[$key] !== '') ? $data[$key] : $default;
}

/** Laadt een JSON-bestand als array; geeft $default terug als het ontbreekt of leeg is. */
function load_json($name, $default = array()) {
    $path = data_path($name);
    if (!is_file($path)) { return $default; }
    $raw = file_get_contents($path);
    if ($raw === false || $raw === '') { return $default; }
    // Een UTF-8 BOM (bv. van een teksteditor die het bestand opsloeg) laat
    // json_decode falen. Weghalen zodat een handmatige edit de site niet sloopt.
    if (substr($raw, 0, 3) === "\xEF\xBB\xBF") { $raw = substr($raw, 3); }
    $data = json_decode($raw, true);
    return is_array($data) ? $data : $default;
}

/**
 * Schrijft een array als nette JSON. Atomisch (temp + rename) met vergrendeling.
 *
 * Bewaart éérst de huidige inhoud als snapshot (zie _lib/versies.php), zodat elke
 * wijziging terug te draaien is. Alleen voor de bestanden die versiebeheer krijgen;
 * auth.json, users.json en loginlog.json worden overgeslagen.
 *
 * $maak_versie = false slaat het snapshot over (gebruikt bij het terugzetten zelf,
 * dat zijn eigen snapshot al heeft gemaakt).
 */
function save_json($name, $data, $maak_versie = true) {
    $path = data_path($name);
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($json === false) { return false; } // o.a. bij ongeldige UTF-8 in de invoer
    $json .= "\n"; // tekstbestanden eindigen op een newline

    if ($maak_versie) { versie_maak($name); }

    $tmp = $path . '.tmp';
    $fp = fopen($tmp, 'wb');
    if (!$fp) { return false; }

    $ok = false;
    if (flock($fp, LOCK_EX)) {
        fwrite($fp, $json);
        fflush($fp);
        flock($fp, LOCK_UN);
        $ok = true;
    }
    fclose($fp);

    if (!$ok) { @unlink($tmp); return false; }
    return rename($tmp, $path);
}
