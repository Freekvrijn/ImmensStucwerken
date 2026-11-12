<?php
/**
 * =============================================================================
 *  INLINE BEWERKEN OP EEN GEKOPPELDE PUBLIEKE PAGINA
 * =============================================================================
 *  Doel: op de live pagina zelf een potloodje tonen naast elk bewerkbaar
 *  tekstblok, dat dezelfde opmaak-editor opent als in het beheer
 *  (beheer/kernteksten.php) — maar dan direct op de plek waar de tekst ook
 *  staat, in plaats van via een apart formulier.
 *
 *  Dit bestand hergebruikt bewust dezelfde bouwstenen als het bestaande
 *  beheer: _lib/velden.php (welke velden bestaan er en mogen ze opmaak?) en
 *  veld_html_opslaan() uit _lib/helpers.php (dezelfde sanitizer, dus geen
 *  aparte/zwakkere XSS-controle voor deze route).
 *
 *  Integratie: dit bestand is pas actief op een pagina die het zelf include't én
 *  sessie_boot_publiek() aanroept vóór partials/head.php. Andere pagina's
 *  merken er niets van.
 * =============================================================================
 */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/velden.php';
if (defined('CMS_MODULE_TEKSTRIJEN') && CMS_MODULE_TEKSTRIJEN) {
    require_once __DIR__ . '/tekstrijen.php';
}

/**
 * Start (indien nodig) een sessie met dezelfde veilige cookie-instellingen als
 * auth_boot(), maar ZONDER de /beheer/-toegangsblokkade te toetsen.
 *
 * Waarom niet gewoon auth_boot() gebruiken? Die roept ook
 * blokkeer_indien_nodig('beheer') aan — bedoeld om vreemden van het
 * beheerformulier te weren. Op een publieke pagina zou dat een gewone
 * bezoeker kunnen blokkeren om een reden die niets met hém te maken heeft.
 * Deze functie start dus alleen de sessie, zodat we via is_logged_in() kunnen
 * zien of er al ergens (in /beheer/) is ingelogd.
 */
function sessie_boot_publiek() {
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

/**
 * Mag deze veldsleutel via de inline-editor bewerkt worden? Precies dezelfde
 * voorwaarde als "krijgt dit veld de opmaak-editor in het beheerformulier":
 * bekend in velden.php, geen afbeelding, en niet 'plat' => true.
 *
 * Dit is de servervoorwaarde — de uiteindelijke poortwachter. Het feit dat een
 * <span data-veld="..."> op de pagina staat is geen bewijs dat opslaan ook
 * mag; die aanname zou een gemanipuleerd verzoek de kans geven een willekeurige
 * sleutel te overschrijven.
 */
function inline_veld_toegestaan($sleutel) {
    $velden = kernteksten_velden();
    if (!isset($velden[$sleutel])) { return false; }
    $def  = $velden[$sleutel];
    $type = $def['type'] ?? 'text';
    if (!empty($def['plat'])) { return false; }
    return in_array($type, array('text', 'textarea'), true);
}

/**
 * Mag deze veldsleutel via de inline-editor een nieuwe foto krijgen? Alleen
 * velden die in velden.php als 'afbeelding' staan — dezelfde velden die in
 * het beheerformulier een bestandskiezer krijgen.
 */
function inline_veld_afbeelding_toegestaan($sleutel) {
    $velden = kernteksten_velden();
    if (!isset($velden[$sleutel])) { return false; }
    return ($velden[$sleutel]['type'] ?? 'text') === 'afbeelding';
}

/**
 * Mag deze veldsleutel via de inline-editor een nieuwe bestemming (link)
 * krijgen? Alleen velden die in velden.php als 'link' staan.
 */
function inline_veld_link_toegestaan($sleutel) {
    $velden = kernteksten_velden();
    if (!isset($velden[$sleutel])) { return false; }
    return ($velden[$sleutel]['type'] ?? 'text') === 'link';
}
