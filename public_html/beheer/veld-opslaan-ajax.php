<?php
/**
 * Slaat precies één veld op vanuit de inline-editor op de publieke pagina
 * Zelfde opslagpad als kernteksten.php — dezelfde sanitizer en content.json —
 * maar dan voor één sleutel per verzoek, zonder de rest van het formulier.
 */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/velden.php';
require_once __DIR__ . '/../_lib/inline_editor.php';

// Dezelfde sessie als op de publieke pagina (geen /beheer/-blokkeercontrole,
// zie inline_editor.php voor waarom).
sessie_boot_publiek();

header('Content-Type: application/json; charset=UTF-8');

function inline_json_uit($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inline_json_uit(array('ok' => false, 'fout' => 'Ongeldige methode.'), 405);
}

if (!mag('content')) {
    inline_json_uit(array('ok' => false, 'fout' => 'Niet (meer) ingelogd. Log opnieuw in bij /beheer/.'), 401);
}

if (!csrf_check()) {
    inline_json_uit(array('ok' => false, 'fout' => 'Sessie verlopen. Herlaad de pagina en probeer het opnieuw.'), 403);
}

$sleutel = isset($_POST['veld']) ? (string) $_POST['veld'] : '';
$html    = isset($_POST['html']) ? (string) $_POST['html'] : '';

if (!inline_veld_toegestaan($sleutel)) {
    inline_json_uit(array('ok' => false, 'fout' => 'Dit veld is niet bewerkbaar via de pagina.'), 400);
}

$content = load_json('content.json', array());
$content[$sleutel] = trim(veld_html_opslaan($html));

if (!save_json('content.json', $content)) {
    inline_json_uit(array('ok' => false, 'fout' => 'Opslaan mislukte. Is de map data/ schrijfbaar?'));
}

inline_json_uit(array('ok' => true, 'html' => veld_html($content, $sleutel, '')));
