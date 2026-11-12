<?php
/**
 * Uploadt één afbeelding voor een tekstrij (indeling "Tekst met een afbeelding"
 * of "Blokken naast elkaar"). Geeft alleen het pad terug; de rij zelf wordt pas
 * bij "Opslaan" in het rijvenster bewaard (beheer/rij-opslaan-ajax.php).
 */
require_once __DIR__ . '/../_lib/inline_editor.php';
require_once __DIR__ . '/../_lib/tekstrijen.php';
require_once __DIR__ . '/../_lib/helpers.php';
sessie_boot_publiek();
header('Content-Type: application/json; charset=UTF-8');
header('Cache-Control: no-store');

function rij_foto_antwoord($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { rij_foto_antwoord(array('ok' => false, 'fout' => 'Alleen uploaden via POST.'), 405); }
if (!csrf_check()) { rij_foto_antwoord(array('ok' => false, 'fout' => 'Sessie verlopen. Vernieuw de pagina.'), 403); }
$zone = is_string($_POST['zone'] ?? null) ? $_POST['zone'] : '';
if (!tekstrijen_mag_bewerken($zone)) { rij_foto_antwoord(array('ok' => false, 'fout' => 'Je bent uitgelogd of hebt geen beheerrechten.'), 401); }
if (!isset(tekstrijen_zones()[$zone])) { rij_foto_antwoord(array('ok' => false, 'fout' => 'Hier kunnen geen tekstrijen worden aangepast.'), 400); }

$up = verwerk_upload($_FILES['bestand'] ?? null);
if (!$up['ok'] || $up['pad'] === '') {
    rij_foto_antwoord(array('ok' => false, 'fout' => $up['fout'] !== '' ? $up['fout'] : 'Geen afbeelding ontvangen.'), 400);
}
rij_foto_antwoord(array('ok' => true, 'pad' => $up['pad']));
