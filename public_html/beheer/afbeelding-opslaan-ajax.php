<?php
/**
 * Vervangt precies één afbeelding vanuit de inline-editor op de publieke
 * pagina. Hergebruikt verwerk_upload() (validatie, veilige bestandsnaam,
 * verkleinen/comprimeren) — exact dezelfde route als een foto-upload in het
 * gewone beheerformulier, alleen dan voor één veld per verzoek.
 */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/velden.php';
require_once __DIR__ . '/../_lib/inline_editor.php';

sessie_boot_publiek();

header('Content-Type: application/json; charset=UTF-8');

function inline_afbeelding_json_uit($data, $status = 200) {
    http_response_code($status);
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    inline_afbeelding_json_uit(array('ok' => false, 'fout' => 'Ongeldige methode.'), 405);
}
if (!mag('content')) {
    inline_afbeelding_json_uit(array('ok' => false, 'fout' => 'Niet (meer) ingelogd. Log opnieuw in bij /beheer/.'), 401);
}
if (!csrf_check()) {
    inline_afbeelding_json_uit(array('ok' => false, 'fout' => 'Sessie verlopen. Herlaad de pagina en probeer het opnieuw.'), 403);
}

$sleutel = isset($_POST['veld']) ? (string) $_POST['veld'] : '';
if (!inline_veld_afbeelding_toegestaan($sleutel)) {
    inline_afbeelding_json_uit(array('ok' => false, 'fout' => 'Dit veld is geen bewerkbare afbeelding.'), 400);
}

$up = verwerk_upload($_FILES['bestand'] ?? null);
if (!$up['ok']) {
    inline_afbeelding_json_uit(array('ok' => false, 'fout' => $up['fout'] !== '' ? $up['fout'] : 'Uploaden mislukte.'));
}
if ($up['pad'] === '') {
    inline_afbeelding_json_uit(array('ok' => false, 'fout' => 'Er is geen bestand ontvangen.'));
}

$content = load_json('content.json', array());
$content[$sleutel] = $up['pad'];

if (!save_json('content.json', $content)) {
    inline_afbeelding_json_uit(array('ok' => false, 'fout' => 'Opslaan mislukte. Is de map data/ schrijfbaar?'));
}

inline_afbeelding_json_uit(array('ok' => true, 'pad' => $up['pad']));
