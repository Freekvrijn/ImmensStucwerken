<?php
/**
 * Slaat de bestemming van één knop/link op vanuit de inline-editor. Gebruikt
 * veld_link_opslaan() (_lib/helpers.php) als enige poortwachter voor wat een
 * geldige link is — dezelfde functie die ook het beheerformulier gebruikt
 * (beheer/kernteksten.php), zodat er geen tweede, afwijkende regelset bestaat.
 */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/velden.php';
require_once __DIR__ . '/../_lib/inline_editor.php';

sessie_boot_publiek();

header('Content-Type: application/json; charset=UTF-8');

function inline_link_json_uit($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inline_link_json_uit(array('ok' => false, 'fout' => 'Ongeldige methode.'), 405);
}
if (!mag('content')) {
    inline_link_json_uit(array('ok' => false, 'fout' => 'Niet (meer) ingelogd. Log opnieuw in bij /beheer/.'), 401);
}
if (!csrf_check()) {
    inline_link_json_uit(array('ok' => false, 'fout' => 'Sessie verlopen. Herlaad de pagina en probeer het opnieuw.'), 403);
}

$sleutel = isset($_POST['veld']) ? (string) $_POST['veld'] : '';
$ruw     = isset($_POST['url']) ? (string) $_POST['url'] : '';

if (!inline_veld_link_toegestaan($sleutel)) {
    inline_link_json_uit(array('ok' => false, 'fout' => 'Dit veld is geen bewerkbare link.'), 400);
}

$schoon = veld_link_opslaan($ruw);
if (trim($ruw) !== '' && $schoon === '') {
    inline_link_json_uit(array(
        'ok'   => false,
        'fout' => 'Dit webadres wordt niet herkend. Gebruik een paginanaam (bv. "contact"), een #anker, of een volledige link die met https:// begint.',
    ), 400);
}

$content = load_json('content.json', array());
$content[$sleutel] = $schoon;

if (!save_json('content.json', $content)) {
    inline_link_json_uit(array('ok' => false, 'fout' => 'Opslaan mislukte. Is de map data/ schrijfbaar?'));
}

inline_link_json_uit(array('ok' => true, 'url' => $schoon));
