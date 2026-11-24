<?php
require_once __DIR__ . '/public.php';
require_once __DIR__ . '/inline_editor.php';
sessie_boot_publiek();

function tekst($sleutel) { global $content; return veld_html($content, $sleutel); }
function fotopad($pad) {
    return is_string($pad) && preg_match('~^/?assets/img/[a-zA-Z0-9_./-]+\.(?:jpg|jpeg|png|webp|gif)$~', $pad) && strpos($pad, '..') === false ? '/' . ltrim($pad, '/') : '';
}
function foto($veld, $klasse = '', $alt = '', $eager = false) {
    global $content;
    $src = fotopad(veld($content, $veld));
    if ($src === '') { return; }
    echo '<img class="' . e($klasse) . '" src="' . e($src) . '" alt="' . e($alt) . '" data-veld-afbeelding="' . e($veld) . '" loading="' . ($eager ? 'eager' : 'lazy') . '" decoding="async"' . ($eager ? ' fetchpriority="high"' : '') . '>';
}
function sfeerbijschrift($veld = 'home_afbeelding') {
    global $content;
    if (in_array(veld($content, $veld), array('assets/img/woonruimte.jpg','assets/img/interieur.jpg','assets/img/detail.jpg'), true)) {
        echo '<figcaption class="sfeer">Sfeerbeeld. Ruimte voor eigen projectfotografie.</figcaption>';
    }
}
function merk($groot = false, $opBlauw = false) {
    global $content, $thema;
    $logo = $opBlauw ? veld($content, 'logo_op_blauw', 'assets/img/logo-immens-op-blauw.png') : veld($content, 'logo');
    echo '<a class="merk' . ($groot ? ' merk-groot' : '') . ($opBlauw ? ' merk-op-blauw' : '') . '" href="/" aria-label="Immens Stucwerken, naar boven">';
    echo '<img src="' . e(fotopad($logo)) . '" width="1600" height="1067" alt="Immens Stucwerken">';
    echo '</a>';
}
function menulinks($vanaf = 0, $aantal = null) {
    global $thema;
    foreach (array_slice(cms_navigatie('hoofd'), $vanaf, $aantal) as $item) {
        $url = $item['url'] ?? '';
        if (strpos($url, '#') === 0) { $url = '/' . $url; }
        elseif ($url !== '' && !preg_match('~^(?:https?://|/|mailto:|tel:)~i', $url)) { $url = '/' . $url; }
        echo '<a href="' . e($url) . '"' . (!empty($item['nieuw_venster']) ? ' target="_blank" rel="noopener noreferrer"' : '') . '>' . e($item['label'] ?? '') . '</a>';
    }
}
function site_contactgegevens() {
    global $content;
    $offerte = load_json('offerte-instellingen.json', array());
    $bedrijf = is_array($offerte['bedrijf'] ?? null) ? $offerte['bedrijf'] : array();
    $uit = array();
    foreach (array('telefoon','email','adres') as $sleutel) {
        $waarde = cms_instelling($sleutel) ?: veld($content, $sleutel);
        $uit[$sleutel] = trim((string) ($waarde ?: ($bedrijf[$sleutel] ?? '')));
    }
    if (!filter_var($uit['email'], FILTER_VALIDATE_EMAIL)) { $uit['email'] = ''; }
    return $uit;
}
function sitekop() {
    global $thema;
    $contact = site_contactgegevens();
    echo '<div class="contactbalk"><div class="breed"><span>Immens Stucwerken · Wanden &amp; plafonds</span><div>';
    if ($contact['email'] !== '') { echo '<a href="mailto:' . e($contact['email']) . '">Stuur een e-mail</a>'; }
    if ($contact['telefoon'] !== '') { echo '<a href="tel:' . e(preg_replace('/[^+0-9]/','',$contact['telefoon'])) . '">Bel ' . e($contact['telefoon']) . '</a>'; }
    echo '</div></div></div>';
    echo '<header class="sitekop"><div class="kop-binnen">'; merk(false, $thema === 'blauwdruk');
    echo '<button class="menu-toggle" type="button" aria-controls="hoofdnavigatie" aria-expanded="false">Menu</button>';
    echo '<nav class="hoofdnavigatie" id="hoofdnavigatie" aria-label="Hoofdnavigatie">'; menulinks();
    echo '<a class="contactlink" href="/offerte">Maak je offerte</a></nav></div></header>';
}
function diensten($compact = false, $metFotos = false) {
    echo '<div class="diensten' . ($compact ? ' diensten-compact' : '') . ($metFotos ? ' diensten-met-fotos' : '') . '">';
    for ($i = 1; $i <= 3; $i++) {
        echo '<article class="dienst">';
        if ($metFotos) { echo '<figure>'; foto('dienst' . $i . '_afbeelding', '', array(1=>'Eigen stucwerk bij raamopeningen',2=>'Stucwerk onder de kap met houten balken',3=>'Detail van een gestucte wand met afgeronde hoek')[$i]); echo '</figure><div class="dienst-inhoud">'; }
        echo '<h3 data-veld="dienst' . $i . '_titel">' . tekst('dienst' . $i . '_titel') . '</h3><p data-veld="dienst' . $i . '_tekst">' . tekst('dienst' . $i . '_tekst') . '</p>';
        if ($metFotos) { echo '<a class="knop" href="' . ($i === 3 ? '/#contact' : '/offerte') . '">' . ($i === 3 ? 'Bespreek herstelwerk' : 'Bereken je offerte') . '</a></div>'; }
        echo '</article>';
    }
    echo '</div>';
}
function projecten_laden() {
    $items = array_values(array_filter(cms_collectie(), function($item) { return !empty($item['actief']); }));
    if ($items) { return $items; }
    return array_map(function($rij) {
        return array('id' => $rij[0], 'titel' => $rij[1], 'categorie' => 'Fotovak voor eigen werk', 'afbeelding' => 'assets/img/' . $rij[2] . '.jpg', 'samenvatting' => 'Dit sfeerbeeld laat zien waar straks een eigen project komt. Voeg in het beheer een project met een foto en beschrijving toe om deze voorbeeldvakken te vervangen.', 'tekst' => '', 'voorbeeld' => true);
    }, array(array('voorbeeld-wand','Wandafwerking','woonruimte'),array('voorbeeld-plafond','Plafond & ruimte','interieur'),array('voorbeeld-detail','Afwerking in detail','detail')));
}
function projectkaart($project, $klasse = '') {
    global $thema;
    $detail = !empty($project['slug']) ? cms_eigen_pagina('project-' . $project['slug']) : null;
    echo '<article class="project ' . e($klasse) . '">';
    if ($detail) { echo '<a class="project-open" href="/project-' . e($project['slug']) . '">'; }
    else { echo '<button class="project-open" type="button" data-project="' . e($project['id']) . '" aria-haspopup="dialog">'; }
    $src = fotopad($project['afbeelding'] ?? '');
    echo '<div class="project-beeld">';
    if ($src) { echo '<img src="' . e($src) . '" alt="' . e($project['titel'] ?? '') . '" loading="lazy" decoding="async">'; }
    else { echo '<span class="foto-ontbreekt">Projectfoto volgt</span>'; }
    if (!empty($project['voorbeeld'])) { echo '<span class="beeldlabel">Sfeerbeeld</span>'; }
    echo '<span class="project-bekijken">' . (!empty($project['voorbeeld']) ? 'Bekijk fotovak' : 'Bekijk project') . '</span></div>';
    echo '<div class="project-onderschrift"><h3>' . e($project['titel'] ?? '') . '</h3><span>' . e($project['categorie'] ?? '') . '</span></div>';
    echo $detail ? '</a>' : '</button>';
    echo '</article>';
}
function projectdialogen() {
    global $projecten;
    foreach ($projecten as $project) {
        echo '<dialog class="projectdialoog" data-project-dialog="' . e($project['id']) . '" aria-label="' . e($project['titel']) . '"><button class="dialoog-sluiten" type="button" data-close>Sluiten <span aria-hidden="true">×</span></button>';
        $src = fotopad($project['afbeelding'] ?? '');
        if ($src) { echo '<img src="' . e($src) . '" alt="' . e($project['titel']) . '" loading="lazy">'; }
        echo '<div class="dialoog-tekst"><p class="project-categorie">' . e(!empty($project['voorbeeld']) ? 'Sfeerbeeld, geen uitgevoerd werk van Immens' : ($project['categorie'] ?? '')) . '</p><h2>' . e($project['titel']) . '</h2><p>' . e($project['samenvatting'] ?? '') . '</p><div>' . veld_html($project, 'tekst') . '</div></div></dialog>';
    }
}
function werkwijze() {
    echo '<section class="werkwijze" id="werkwijze"><div class="werkwijze-intro"><h2 data-veld="over_titel">' . tekst('over_titel') . '</h2><p data-veld="over_tekst">' . tekst('over_tekst') . '</p></div><ol class="stappen"><li><h3>Je plannen bespreken</h3><p>Wat wil je aanpakken en welke afwerking past bij je ruimte?</p></li><li><h3>Het werk afstemmen</h3><p>De ondergrond bekijken, de aanpak bepalen en afspraken maken.</p></li><li><h3>Aan de slag</h3><p>Voorbereiden, stucen en samen de afwerking nalopen.</p></li></ol></section>';
}
function contact_verwerken() {
    global $thema;
    if ($_SERVER['REQUEST_METHOD'] !== 'POST' || ($_POST['formulier'] ?? '') !== 'contact') { return; }
    $fout = '';
    $naam = trim(is_string($_POST['naam'] ?? null) ? $_POST['naam'] : '');
    $email = trim(is_string($_POST['email'] ?? null) ? $_POST['email'] : '');
    $bericht = trim(is_string($_POST['bericht'] ?? null) ? $_POST['bericht'] : '');
    $plaats = trim(is_string($_POST['plaats'] ?? null) ? $_POST['plaats'] : '');
    if (!csrf_check()) { $fout = 'Je sessie is verlopen. Laad de pagina opnieuw en probeer het nogmaals.'; }
    elseif ($naam === '' || $bericht === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) { $fout = 'Vul je naam, een geldig e-mailadres en een omschrijving in.'; }
    elseif (mb_strlen($naam) > 150 || mb_strlen($email) > 254 || mb_strlen($bericht) > 5000 || mb_strlen($plaats) > 150) { $fout = 'Je bericht is te lang. Gebruik maximaal 5.000 tekens voor de omschrijving.'; }
    else {
        $result = cms_formulier_verwerken('contact', array('naam'=>$naam,'email'=>$email,'plaats'=>$plaats,'bericht'=>$bericht), $email, is_string($_POST['website'] ?? null) ? $_POST['website'] : 'ongeldig', array('naam'=>$naam,'labels'=>array('naam'=>'Naam','email'=>'E-mailadres','plaats'=>'Plaats','bericht'=>'Projectomschrijving')));
        if (!$result['ok']) { $fout = $result['fout']; }
    }
    if ($fout === '') {
        $_SESSION['contact_succes'] = true;
        header('Location: /#contact', true, 303); exit;
    }
    $GLOBALS['contact_fout'] = $fout;
}
function contactblok() {
    global $content, $thema, $contact_fout;
    $succes = !empty($_SESSION['contact_succes']); unset($_SESSION['contact_succes']);
    echo '<section class="contactblok" id="contact"><div class="contact-inleiding"><h2 data-veld="contact_titel">' . tekst('contact_titel') . '</h2><p data-veld="contact_intro">' . tekst('contact_intro') . '</p>';
    $contact = site_contactgegevens(); $telefoon = $contact['telefoon']; $email = $contact['email'];
    if ($telefoon) { echo '<a class="contact-direct" href="tel:' . e(preg_replace('/[^+0-9]/','',$telefoon)) . '">' . e($telefoon) . '</a>'; }
    if (filter_var($email, FILTER_VALIDATE_EMAIL)) { echo '<a class="contact-direct" href="mailto:' . e($email) . '">' . e($email) . '</a>'; }
    echo '<p class="ontwerpnoot">Dit is een ontwerpvoorbeeld. Verstuur nog geen echte aanvraag.</p></div><div class="formulier-vak">';
    if ($succes) { echo '<p class="formulier-melding" role="status">Je proefaanvraag is opgeslagen. In deze ontwerpversie wordt geen e-mail verstuurd.</p>'; }
    if (!empty($contact_fout)) { echo '<p class="formulier-fout" role="alert">' . e($contact_fout) . '</p>'; }
    echo '<form class="contactformulier" method="post" action="/#contact">' . csrf_field() . '<input type="hidden" name="formulier" value="contact"><div class="formulier-rij">';
    foreach (array('naam'=>array('Je naam','text','name',150),'email'=>array('E-mailadres','email','email',254),'plaats'=>array('Plaats (optioneel)','text','address-level2',150)) as $key=>$def) {
        echo '<label>' . e($def[0]) . '<input type="' . e($def[1]) . '" name="' . e($key) . '" autocomplete="' . e($def[2]) . '" maxlength="' . $def[3] . '"' . ($key !== 'plaats' ? ' required' : '') . ' value="' . e(!empty($contact_fout) && is_string($_POST[$key] ?? null) ? $_POST[$key] : '') . '"></label>';
    }
    echo '</div><label>Vertel over je project<textarea name="bericht" rows="4" maxlength="5000" required placeholder="Bijvoorbeeld: wanden en plafond in de woonkamer, circa 40 m².">' . e(!empty($contact_fout) && is_string($_POST['bericht'] ?? null) ? $_POST['bericht'] : '') . '</textarea></label><div class="honeypot" aria-hidden="true"><label>Website<input type="text" name="website" tabindex="-1" autocomplete="off"></label></div><div class="formulier-voet"><small>Lees hoe we omgaan met je gegevens in de <a href="/privacy">privacyverklaring</a>.</small><button class="knop" type="submit">Verstuur proefaanvraag</button></div></form></div></section>';
}
function sitevoet() {
    $contact = site_contactgegevens();
    echo '<footer class="bedrijfsvoet"><div class="breed"><div class="bedrijfsvoet-grid"><div class="bedrijfsvoet-merk">'; merk(false, true);
    echo '<p>Stucwerk dat de ruimte afmaakt.<br>Aandacht voor de afwerking.</p></div><nav aria-label="Navigatie onderaan"><h2>Immens Stucwerken</h2>';
    menulinks();
    echo '<a href="/offerte">Zelf je offerte maken</a></nav><div class="bedrijfsvoet-contact"><h2>Neem contact op</h2>';
    if ($contact['telefoon'] !== '') { echo '<a class="bedrijfsvoet-telefoon" href="tel:' . e(preg_replace('/[^+0-9]/','',$contact['telefoon'])) . '">' . e($contact['telefoon']) . '</a>'; }
    if ($contact['email'] !== '') { echo '<a href="mailto:' . e($contact['email']) . '">' . e($contact['email']) . '</a>'; }
    if ($contact['adres'] !== '') { echo '<p>' . e($contact['adres']) . '</p>'; }
    echo '</div></div><div class="bedrijfsvoet-onder"><small>© ' . date('Y') . ' Immens Stucwerken</small><div><a href="/privacy">Privacy</a>';
    foreach (cms_navigatie('footer') as $item) { echo '<a href="' . e('/' . ltrim($item['url'] ?? '', '/')) . '">' . e($item['label'] ?? '') . '</a>'; }
    echo '<a href="/beheer/">Beheer</a></div></div></div></footer>';
}
function documentkop($page, $titel, $omschrijving = '') {
    global $thema;
    $seo = cms_seo_voor($page, array('titel'=>$titel,'omschrijving'=>$omschrijving));
    echo '<!doctype html><html lang="nl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . e($seo['titel']) . '</title><meta name="description" content="' . e($seo['omschrijving']) . '"><meta name="robots" content="' . ($seo['indexeren'] ? 'index,follow' : 'noindex,nofollow') . '"><meta name="theme-color" content="#174c85"><meta property="og:title" content="' . e($seo['titel']) . '"><meta property="og:description" content="' . e($seo['omschrijving']) . '"><meta property="og:type" content="website">';
    if (!empty($seo['canonical'])) { echo '<link rel="canonical" href="' . e($seo['canonical']) . '">'; }
    if (!empty($seo['og_afbeelding'])) { echo '<meta property="og:image" content="' . e($seo['og_afbeelding']) . '">'; }
    echo '<link rel="icon" href="/assets/img/favicon.svg" type="image/svg+xml"><link rel="stylesheet" href="/' . asset('assets/site.css') . '"><link rel="stylesheet" href="/' . asset('assets/tekstrijen.css') . '">';
    echo '<link rel="stylesheet" href="/' . asset('assets/blauwdruk.css') . '">';
    if ($page === 'offerte') { echo '<link rel="stylesheet" href="/' . asset('assets/offerte.css') . '">'; }
    if (mag('content')) { echo '<link rel="stylesheet" href="/beheer/rijk-editor.css"><link rel="stylesheet" href="/beheer/inline-editor.css">'; }
    echo '<script src="/' . asset('assets/site.js') . '" defer></script></head><body class="thema-' . e($thema) . '" data-inl-csrf="' . e($_SESSION['csrf'] ?? '') . '"><a class="skiplink" href="#inhoud">Naar de inhoud</a>';
}
function documentslot() {
    if (mag('content')) { require __DIR__ . '/inline-fragment.php'; echo '<script src="/beheer/inline-editor.js" defer></script>'; }
    echo '</body></html>';
}
