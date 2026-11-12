<?php
/** Onderhoudsmodus aan- of uitzetten vanaf het dashboard. */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/onderhoud.php';
auth_boot();
require_login('settings');

if (!defined('CMS_MODULE_ONDERHOUD') || !CMS_MODULE_ONDERHOUD) {
    header('Location: index.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php#onderhoud');
    exit;
}

if (!csrf_check()) {
    $_SESSION['onderhoud_melding'] = array('ok' => false, 'tekst' => 'Sessie verlopen, probeer het opnieuw.');
} else {
    $aan = (($_POST['onderhoud'] ?? '') === 'aan');
    $gebruiker = huidige_gebruiker();
    $door = is_array($gebruiker) ? (string) ($gebruiker['naam'] ?? '') : '';
    $ok = onderhoud_zetten($aan, $door);
    $_SESSION['onderhoud_melding'] = array(
        'ok' => $ok,
        'tekst' => !$ok ? 'Opslaan is niet gelukt. Controleer of data/ schrijfbaar is.'
            : ($aan ? 'De onderhoudsmodus staat aan.' : 'De website staat open voor bezoekers.'),
    );
}
header('Location: index.php#onderhoud');
exit;
