<?php
require_once __DIR__ . '/../_lib/inline_editor.php';
require_once __DIR__ . '/../_lib/tekstrijen.php';
sessie_boot_publiek();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function rij_antwoord($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}
function rij_invoer($naam) {
    if (isset($_POST[$naam]) && !is_string($_POST[$naam])) {
        rij_antwoord(array('ok' => false, 'fout' => 'Ongeldige invoer.'), 400);
    }
    return trim($_POST[$naam] ?? '');
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { rij_antwoord(array('ok' => false, 'fout' => 'Alleen opslaan via POST.'), 405); }
if (!csrf_check()) { rij_antwoord(array('ok' => false, 'fout' => 'Sessie verlopen. Vernieuw de pagina.'), 403); }
$zone = rij_invoer('zone');
// Beheerder: alle zones. SEO-account: alleen de zones van extra pagina's.
if (!tekstrijen_mag_bewerken($zone)) { rij_antwoord(array('ok' => false, 'fout' => 'Je bent uitgelogd of hebt geen beheerrechten.'), 401); }
$actie = rij_invoer('actie');
$id = rij_invoer('id');
if (!isset(tekstrijen_zones()[$zone])) { rij_antwoord(array('ok' => false, 'fout' => 'Hier kunnen geen tekstrijen worden aangepast.'), 400); }
if (!in_array($actie, array('opslaan', 'verwijderen', 'herstellen'), true)) { rij_antwoord(array('ok' => false, 'fout' => 'Onbekende actie.'), 400); }

// Vergrendel de hele lees/wijzig/schrijf-actie. Een verouderd tabblad mag een
// recentere wijziging niet ongemerkt overschrijven.
$slot = fopen(data_path('tekstrijen.lock'), 'c');
if (!$slot || !flock($slot, LOCK_EX)) { rij_antwoord(array('ok' => false, 'fout' => 'Opslaan lukt nu niet. Probeer het opnieuw.'), 500); }
$data = load_json('tekstrijen.json', array());
$rijen = tekstrijen_laad($zone, $data);
if (!hash_equals(tekstrijen_revisie($rijen), rij_invoer('revisie'))) {
    rij_antwoord(array('ok' => false, 'fout' => 'Deze rijen zijn ondertussen gewijzigd. Vernieuw de pagina en probeer opnieuw.'), 409);
}
$index = array_search($id, array_column($rijen, 'id'), true);
if ($actie === 'herstellen') {
    if (!tekstrijen_standaard($zone) || in_array('bestaand', array_column($rijen, 'id'), true)) {
        rij_antwoord(array('ok' => false, 'fout' => 'Er is geen oorspronkelijke rij om terug te zetten.'), 400);
    }
    array_unshift($rijen, array('id' => 'bestaand'));
    $id = 'bestaand';
} elseif ($actie === 'verwijderen') {
    if ($index === false) { rij_antwoord(array('ok' => false, 'fout' => 'Deze rij bestaat niet meer.'), 404); }
    array_splice($rijen, $index, 1);
    $id = '';
} else {
    if ($id !== '' && $index === false) { rij_antwoord(array('ok' => false, 'fout' => 'Deze rij bestaat niet meer.'), 404); }
    $rij = array('id' => $id ?: 'rij-' . bin2hex(random_bytes(8)));
    $rij['knop_tekst'] = rij_invoer('knop_tekst');
    $url = rij_invoer('knop_url');
    $rij['knop_url'] = veld_link_opslaan($url);
    if (mb_strlen($rij['knop_tekst']) > 100 || strlen($url) > 2000 || ($url !== '' && $rij['knop_url'] === '') || (($url === '') !== ($rij['knop_tekst'] === ''))) {
        rij_antwoord(array('ok' => false, 'fout' => 'Vul voor de knop een korte tekst én een geldig webadres in, of laat beide leeg.'), 400);
    }
    if ($id !== 'bestaand') {
        foreach (array('bovenkop' => 120, 'titel' => 250, 'html' => 30000, 'kader_html' => 10000) as $veld => $max) {
            $waarde = rij_invoer($veld);
            if (mb_strlen($waarde) > $max) { rij_antwoord(array('ok' => false, 'fout' => 'De tekst is te lang.'), 400); }
            // Rijtekst mag tussenkoppen (H2/H3) en alinea's bevatten.
            $rij[$veld] = strpos($veld, 'html') !== false ? veld_html_opslaan($waarde, true) : $waarde;
        }
        $rij['titel_niveau'] = rij_invoer('titel_niveau') === 'h3' ? 'h3' : 'h2';
        $rij['indeling'] = rij_invoer('indeling');
        $rij['achtergrond'] = rij_invoer('achtergrond');
        if (!in_array($rij['indeling'], array('tekst', 'kader', 'afbeelding', 'kaarten'), true) || !in_array($rij['achtergrond'], array('paper', 'mint'), true)) {
            rij_antwoord(array('ok' => false, 'fout' => 'Kies een geldige indeling en achtergrond.'), 400);
        }
        if ($rij['indeling'] === 'afbeelding') {
            $rij['afbeelding'] = tekstrij_afbeelding_veilig(rij_invoer('afbeelding'));
            $rij['afbeelding_alt'] = mb_substr(strip_tags(rij_invoer('afbeelding_alt')), 0, 200);
            $rij['afbeelding_positie'] = rij_invoer('afbeelding_positie') === 'links' ? 'links' : 'rechts';
            if ($rij['afbeelding'] === '') { rij_antwoord(array('ok' => false, 'fout' => 'Kies een afbeelding voor deze rij.'), 400); }
        } elseif ($rij['indeling'] === 'kaarten') {
            if (strlen(rij_invoer('kaarten')) > 20000) { rij_antwoord(array('ok' => false, 'fout' => 'De tekst is te lang.'), 400); }
            list($kaarten, $kaartFout) = tekstrij_kaarten_invoer(rij_invoer('kaarten'));
            if ($kaartFout !== '') { rij_antwoord(array('ok' => false, 'fout' => $kaartFout), 400); }
            if (!$kaarten) { rij_antwoord(array('ok' => false, 'fout' => 'Vul minstens één blok in (bij voorkeur 2 of 3).'), 400); }
            $rij['kaarten'] = $kaarten;
        } elseif ($rij['titel'] === '' && trim(strip_tags($rij['html'])) === '') {
            rij_antwoord(array('ok' => false, 'fout' => 'Vul een kop of tekst in.'), 400);
        }
    }
    if ($index !== false) {
        $rijen[$index] = $rij;
    } else {
        if (count($rijen) >= 40) { rij_antwoord(array('ok' => false, 'fout' => 'Er kunnen hier maximaal 40 rijen staan.'), 400); }
        $na = rij_invoer('na');
        $positie = $na === '' ? -1 : array_search($na, array_column($rijen, 'id'), true);
        if ($positie === false) { rij_antwoord(array('ok' => false, 'fout' => 'De rij waar je onder wilt toevoegen bestaat niet meer.'), 409); }
        array_splice($rijen, $positie + 1, 0, array($rij));
    }
    $id = $rij['id'];
}
// Ook de allereerste wijziging kan via Versies & back-up worden teruggedraaid.
if (!is_file(data_path('tekstrijen.json')) && !save_json('tekstrijen.json', array(), false)) {
    rij_antwoord(array('ok' => false, 'fout' => 'De map data is niet schrijfbaar.'), 500);
}
$data[$zone] = array_values($rijen);
if (!save_json('tekstrijen.json', $data)) { rij_antwoord(array('ok' => false, 'fout' => 'Opslaan mislukte.'), 500); }
flock($slot, LOCK_UN);
fclose($slot);
rij_antwoord(array('ok' => true, 'id' => $id));
