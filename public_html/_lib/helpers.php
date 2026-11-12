<?php
/** Kleine, gedeelde hulpfuncties. */
require_once __DIR__ . '/config.php'; // upload-instellingen (CMS_UPLOAD_DIR/MAX)

/** Escape voor veilige uitvoer in HTML (tegen XSS). */
function e($s) {
    return htmlspecialchars((string) $s, ENT_QUOTES, 'UTF-8');
}

/**
 * Assetpad met cache-buster: 'assets/css/styles.css' -> 'assets/css/styles.css?v=1737123456'.
 * De ?v verandert automatisch zodra het bestand wijzigt (wijzigingsdatum van het
 * bestand), zodat browsers de nieuwe versie ophalen in plaats van een verouderde uit
 * hun cache. Bestaat het bestand niet, dan komt het pad kaal terug. De uitvoer is al
 * ge-escaped voor direct gebruik in een href/src-attribuut.
 */
function asset($pad) {
    $pad = ltrim((string) $pad, '/');
    $v   = @filemtime(dirname(__DIR__) . '/' . $pad);
    return e($v ? $pad . '?v=' . $v : $pad);
}

/** '2025-09-11' -> '11 september 2025'. Bij ongeldige invoer: onbewerkt terug. */
function nl_datum($iso) {
    $maanden = array(1 => 'januari', 'februari', 'maart', 'april', 'mei', 'juni',
                     'juli', 'augustus', 'september', 'oktober', 'november', 'december');
    $t = strtotime((string) $iso);
    if (!$t) { return (string) $iso; }
    $dag  = (int) date('j', $t);
    $mnd  = (int) date('n', $t);
    $jaar = date('Y', $t);
    return $dag . ' ' . (isset($maanden[$mnd]) ? $maanden[$mnd] : '') . ' ' . $jaar;
}

/** Compacte datum voor smalle beheertabellen: '2024-12-01' -> '01-12-2024'. */
function nl_datum_kort($iso) {
    $t = strtotime((string) $iso);
    return $t ? date('d-m-Y', $t) : (string) $iso;
}

/** Waarde uit een content-array, met terugvaloptie als de sleutel leeg/ontbreekt. */
function veld($arr, $key, $default = '') {
    return (isset($arr[$key]) && $arr[$key] !== '') ? $arr[$key] : $default;
}

/**
 * Veldwaarde waarbij de standaard alleen geldt zolang de sleutel nog nooit is
 * opgeslagen. Een beheerder kan het veld daardoor bewust leegmaken zonder dat
 * de oorspronkelijke voorbeeldtekst op de website terugkomt.
 */
function veld_of_standaard($arr, $key, $standaard = '') {
    return array_key_exists($key, (array) $arr) ? (string) $arr[$key] : (string) $standaard;
}

/**
 * Veilige opslag voor een 'link'-veld (bv. de bestemming van een knop):
 * geen HTML, geen opmaak — puur een webadres. Twee vormen toegestaan:
 *  - intern/relatief: letters, cijfers, koppeltekens, slashes en een #anker
 *    (bv. "diensten#advies", "contact" of "projecten/voorbeeld")
 *  - extern/volledig: http(s):// gevolgd door een adres, of tel:/mailto:
 * Alles wat hier niet in past (waaronder javascript: en andere gevaarlijke
 * schema's) wordt genegeerd en komt terug als lege string — nooit half
 * doorgelaten. Gebruikt door zowel het beheerformulier als de inline-editor,
 * zodat er maar één plek is die bepaalt wat een geldige link is.
 */
function veld_link_opslaan($url) {
    $url = trim((string) $url);
    if ($url === '') { return ''; }
    if (preg_match('/^[A-Za-z0-9\-\/]*(#[A-Za-z0-9\-\/_]*)?$/', $url)) { return $url; }
    if (preg_match('#^https?://[^\s<>"\']+$#i', $url)) { return $url; }
    if (preg_match('#^(tel|mailto):[^\s<>"\']+$#i', $url)) { return $url; }
    return '';
}

/**
 * Veilige mini-opmaak voor teksten uit het beheer: **vet** wordt <strong>vet</strong>.
 *
 * Escapet éérst alles (dus de klant kan geen HTML injecteren) en staat daarná
 * uitsluitend deze ene opmaak toe. Zo houdt de klant de nadruk in bijvoorbeeld
 * de USP-balk, zonder het ontwerp te kunnen slopen.
 */
function veld_opmaak($tekst) {
    $veilig = e($tekst);
    return preg_replace('/\*\*(.+?)\*\*/s', '<strong>$1</strong>', $veilig);
}

/**
 * Zet de HTML uit de opmaak-editor (in het beheer) om naar een veilige
 * deelverzameling, vóór het opslaan in content.json.
 *
 * Werkwijze: elke gevonden tag wordt beoordeeld op een vaste toegestane lijst
 * (<strong>, <em>, <u>, <a>, <ul>, <ol>, <li>, <br>, <div>, <span>). Staat een
 * tag niet op die lijst — een <script>, een event-attribuut, wat dan ook —
 * dan verdwijnt hij volledig; de tekst eromheen blijft gewoon staan.
 *
 * Voor style-attributen (<div> voor uitlijnen, <span> voor kleur/grootte)
 * wordt de waarde NOOIT in zijn geheel overgenomen: elke afzonderlijke
 * CSS-eigenschap wordt individueel tegen een vaste toegestane vorm getoetst
 * en het resultaat wordt opnieuw opgebouwd. Zo kan een geldige eigenschap
 * (bijv. kleur) nooit een ongeldige (bijv. een achtergrondafbeelding)
 * meesmokkelen in dezelfde style-waarde.
 *
 * Bij <a> wordt alleen een href met een veilig schema (http(s), mailto, tel,
 * of een relatief pad) overgenomen, plus optioneel target="_blank" (met de
 * bijbehorende rel="noopener noreferrer" tegen tabnabbing).
 *
 * Belangrijk: dit filtert de HTML zelf, dus roep dit uitsluitend aan op tekst
 * die uit de editor komt — nooit op tekst die je zelf al vertrouwt of die al
 * eerder is ge-escaped, anders verdubbelt de opmaak niet, maar verdwijnt hij.
 */
function veld_html_opslaan($html, $koppen = false) {
    $html = (string) $html;
    $toegestaan = array('strong', 'b', 'em', 'i', 'u', 'ul', 'ol', 'li', 'br', 'a', 'div', 'span');
    if ($koppen) {
        $toegestaan = array_merge($toegestaan, array('h2', 'h3', 'p'));
        $html = preg_replace('~<(/?)h1(?=[\s>])~i', '<$1h2', $html);
    }

    // Inhoud van <script>/<style> altijd volledig weg, voor het geval dat ooit
    // via plakken of een import binnenkomt.
    $html = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', $html);

    $resultaat = preg_replace_callback('/<\/?([a-zA-Z0-9]+)([^>]*)>/', function ($m) use ($toegestaan) {
        $tag     = strtolower($m[1]);
        $isSluit = (strpos($m[0], '</') === 0);

        if (!in_array($tag, $toegestaan, true)) {
            return ''; // niet-toegestane tag: volledig weg, geen zichtbare rommel
        }
        // <b>/<i> normaliseren naar <strong>/<em>, want dat leveren de knoppen.
        if ($tag === 'b') { $tag = 'strong'; }
        if ($tag === 'i') { $tag = 'em'; }

        if ($isSluit) { return '</' . $tag . '>'; }

        if ($tag === 'a') {
            $href = '';
            if (preg_match('/href\s*=\s*"([^"]*)"/i', $m[2], $hm)
                || preg_match("/href\\s*=\\s*'([^']*)'/i", $m[2], $hm)) {
                $kandidaat = trim($hm[1]);
                $veiligRelatief = preg_match('#^(/|\.|[a-z0-9][a-z0-9/_-]*(?:\#[a-z0-9/_-]+)?)#i', $kandidaat)
                    && !preg_match('#^[a-z][a-z0-9+.-]*:#i', $kandidaat);
                $veiligSchema = (bool) preg_match('#^(https?://|mailto:|tel:)#i', $kandidaat);
                if ($veiligRelatief || $veiligSchema) {
                    $href = $kandidaat;
                }
            }
            $nieuweTab = (bool) preg_match('/target\s*=\s*"_blank"/i', $m[2]);
            $attrs = ' href="' . e($href) . '"';
            if ($nieuweTab) { $attrs .= ' target="_blank" rel="noopener noreferrer"'; }
            return '<a' . $attrs . '>';
        }

        if ($tag === 'div') {
            $stijl = '';
            if (preg_match('/style\s*=\s*"([^"]*)"/i', $m[2], $sm)) {
                $genormaliseerd = strtolower(preg_replace('/\s+/', '', rtrim(trim($sm[1]), ';')));
                $toegestaneStijlen = array('text-align:left', 'text-align:right', 'text-align:center');
                if (in_array($genormaliseerd, $toegestaneStijlen, true)) {
                    $stijl = ' style="' . $genormaliseerd . '"';
                }
            }
            return '<div' . $stijl . '>';
        }

        if ($tag === 'span') {
            $stijl = veld_html_span_stijl_filteren($m[2]);
            return '<span' . $stijl . '>';
        }

        return '<' . $tag . '>';
    }, $html);

    // Als er, los van opmaaktags, geen zichtbare tekst overblijft — bijvoorbeeld
    // een kale <br> die de editor achterlaat nadat iemand een veld volledig
    // leegt — dan bewust een echte lege string opslaan. Anders blijft er op de
    // site onterecht een regel witruimte staan waar het veld leeg hoort te zijn
    // (zie [data-veld]:empty in assets/css/styles.css, dat alleen een écht lege
    // waarde herkent).
    $zonderOpmaak = str_replace(array('&nbsp;', "\xc2\xa0"), ' ', $resultaat);
    if (trim(strip_tags($zonderOpmaak)) === '') {
        return '';
    }

    return $resultaat;
}

/**
 * Filtert het style-attribuut van een <span> (tekstkleur/tekengrootte uit de
 * editor) tot uitsluitend geldige, losse eigenschappen. Elke deeldeclaratie
 * (gescheiden door ';') wordt apart getoetst; ongeldige delen vallen weg
 * zonder de rest te raken. Geeft een kant-en-klare ' style="..."'-string
 * terug, of een lege string als er niets geldigs overbleef.
 */
function veld_html_span_stijl_filteren($attrsRuw) {
    if (!preg_match('/style\s*=\s*"([^"]*)"/i', $attrsRuw, $sm)) {
        return '';
    }
    $delen = explode(';', $sm[1]);
    $geldig = array();
    foreach ($delen as $deel) {
        $deel = strtolower(trim($deel));
        if ($deel === '') { continue; }
        if (preg_match('/^color:\s*(#[0-9a-f]{6}|#[0-9a-f]{3}|rgb\(\s*\d{1,3}\s*,\s*\d{1,3}\s*,\s*\d{1,3}\s*\))$/', $deel, $km)) {
            $geldig[] = 'color:' . preg_replace('/\s+/', '', $km[1]);
            continue;
        }
        if (preg_match('/^font-size:\s*(\d{1,2}(?:\.\d+)?)(px|em)$/', $deel, $gm)) {
            $getal = (float) $gm[1];
            $eenheid = $gm[2];
            $binnenBereik = ($eenheid === 'px' && $getal >= 8 && $getal <= 72)
                         || ($eenheid === 'em' && $getal >= 0.5 && $getal <= 4);
            if ($binnenBereik) {
                $geldig[] = 'font-size:' . $gm[1] . $eenheid;
            }
            continue;
        }
        if (preg_match('/^font-family:\s*(.+)$/', $deel, $fm)) {
            // Alleen de eerste naam bekijken (vóór een eventuele komma), quotes
            // en spaties eraf — en dan tegen een vaste, veilige lijst toetsen.
            // De uitvoer komt altijd uit ónze eigen lijst, nooit letterlijk van
            // de browser: zo maakt het niet uit hoe elke browser het intern
            // opschrijft (met/zonder quotes, met/zonder fallback-stack).
            $eersteNaam = trim(explode(',', $fm[1])[0]);
            $eersteNaam = trim($eersteNaam, "\"' ");
            $veiligeLettertypes = array(
                'georgia'     => 'Georgia,serif',
                'arial'       => 'Arial,sans-serif',
                'verdana'     => 'Verdana,sans-serif',
                'courier new' => '"Courier New",monospace',
                'poppins'     => '"Poppins",sans-serif',
                'montserrat'  => '"Montserrat",sans-serif',
                'roboto'      => '"Roboto",sans-serif',
                'open sans'   => '"Open Sans",sans-serif',
                'lato'        => '"Lato",sans-serif',
            );
            if (isset($veiligeLettertypes[$eersteNaam])) {
                $geldig[] = 'font-family:' . $veiligeLettertypes[$eersteNaam];
            }
            continue;
        }
        // alles anders (background, position, url(), expression(), enz.) — genegeerd
    }
    return $geldig ? ' style="' . e(implode(';', $geldig)) . '"' : '';
}

/**
 * Toont een opmaak-veld op de website. De waarde is al gefilterd door
 * veld_html_opslaan() vóórdat hij werd opgeslagen, dus hier géén tweede keer
 * escapen — dat zou de opmaaktags juist zichtbaar maken in plaats van ze toe
 * te passen. Gebruik deze functie daarom uitsluitend voor velden die via de
 * opmaak-editor worden ingevuld, nooit voor platte velden.
 */
function veld_html($arr, $key, $default = '') {
    return (isset($arr[$key]) && $arr[$key] !== '') ? $arr[$key] : $default;
}

/**
 * Maakt van een telefoonnummer het nummer voor een wa.me-link (WhatsApp):
 * alleen cijfers, met landcode, zonder plus. '+31 6 10396878' -> '31610396878'
 */
function wa_nummer($telefoon) {
    return preg_replace('/\D+/', '', tel_href($telefoon));
}

/**
 * Maakt van een weergegeven telefoonnummer een tel:-link.
 * '0223 - 521027' -> '+31223521027'  (Nederlands: leidende 0 wordt +31).
 * Al internationaal ingevoerd (+..) blijft ongewijzigd.
 */
function tel_href($telefoon) {
    $t = trim((string) $telefoon);
    $plus = (strpos($t, '+') === 0);
    $d = preg_replace('/\D+/', '', $t);
    if ($d === '') { return ''; }
    if ($plus)            { return '+' . $d; }
    if ($d[0] === '0')    { return '+31' . substr($d, 1); }
    return '+' . $d;
}

/**
 * Maakt bekende telefoonnummers in een beheerbare tekst klikbaar.
 * De tekst wordt eerst volledig ge-escaped; alleen de expliciet meegegeven
 * nummers worden daarna vervangen door een veilige tel-link.
 */
function telefoon_links_in_tekst($tekst, $nummers) {
    $html = e($tekst);
    foreach ((array) $nummers as $nummer) {
        $nummer = trim((string) $nummer);
        $href = tel_href($nummer);
        if ($nummer === '' || $href === '') { continue; }
        $veiligNummer = e($nummer);
        $link = '<a class="tel-inline" href="tel:' . e($href) . '">' . $veiligNummer . '</a>';
        $html = str_replace($veiligNummer, $link, $html);
    }
    return $html;
}

/**
 * Verwerkt een geüploade afbeelding uit $_FILES.
 * Controleert op fouten, grootte, toegestane soort én of het écht een afbeelding is,
 * en slaat op onder een eigen, veilige bestandsnaam.
 *
 * Geeft terug: array('ok' => bool, 'pad' => 'assets/img/uploads/...', 'fout' => '...')
 * Bij 'geen bestand gekozen' -> ok = true en pad = '' (niets aan de hand).
 */
function verwerk_upload($file) {
    $leeg = array('ok' => true, 'pad' => '', 'fout' => '');
    if (!is_array($file) || !isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return $leeg;
    }
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $meldingen = array(
            UPLOAD_ERR_INI_SIZE   => 'Het bestand is groter dan de server toestaat.',
            UPLOAD_ERR_FORM_SIZE  => 'Het bestand is te groot.',
            UPLOAD_ERR_PARTIAL    => 'Het uploaden is halverwege afgebroken.',
            UPLOAD_ERR_NO_TMP_DIR => 'Serverfout: geen tijdelijke map beschikbaar.',
            UPLOAD_ERR_CANT_WRITE => 'Serverfout: kon niet naar schijf schrijven.',
            UPLOAD_ERR_EXTENSION  => 'De upload is door de server geblokkeerd.',
        );
        $m = isset($meldingen[$file['error']]) ? $meldingen[$file['error']] : 'Uploaden mislukte.';
        return array('ok' => false, 'pad' => '', 'fout' => $m);
    }
    if (!is_uploaded_file($file['tmp_name'])) {
        return array('ok' => false, 'pad' => '', 'fout' => 'Ongeldige upload.');
    }
    if ($file['size'] > CMS_UPLOAD_MAX) {
        $mb = round(CMS_UPLOAD_MAX / (1024 * 1024), 1);
        return array('ok' => false, 'pad' => '', 'fout' => "De afbeelding is te groot (max. {$mb} MB).");
    }

    // Is het écht een afbeelding? (beschermt tegen een script met .jpg-naam)
    $info = @getimagesize($file['tmp_name']);
    if ($info === false) {
        return array('ok' => false, 'pad' => '', 'fout' => 'Dit bestand is geen geldige afbeelding.');
    }
    $toegestaan = array(
        IMAGETYPE_JPEG => 'jpg',
        IMAGETYPE_PNG  => 'png',
        IMAGETYPE_GIF  => 'gif',
        IMAGETYPE_WEBP => 'webp',
    );
    if (!isset($toegestaan[$info[2]])) {
        return array('ok' => false, 'pad' => '', 'fout' => 'Alleen JPG, PNG, GIF of WEBP zijn toegestaan.');
    }
    $ext = $toegestaan[$info[2]];

    // Eigen, veilige bestandsnaam op basis van de originele naam.
    $basis = pathinfo((string) $file['name'], PATHINFO_FILENAME);
    $basis = strtolower($basis);
    $basis = preg_replace('/[^a-z0-9]+/', '-', $basis);
    $basis = trim((string) $basis, '-');
    if ($basis === '') { $basis = 'afbeelding'; }
    $basis = substr($basis, 0, 60);

    $root = dirname(__DIR__);
    $dir  = $root . '/' . CMS_UPLOAD_DIR;
    if (!is_dir($dir) && !@mkdir($dir, 0755, true)) {
        return array('ok' => false, 'pad' => '', 'fout' => 'De uploadmap kon niet worden aangemaakt.');
    }
    if (!is_writable($dir)) {
        return array('ok' => false, 'pad' => '', 'fout' => 'De map ' . CMS_UPLOAD_DIR . '/ is niet schrijfbaar.');
    }

    $naam = $basis . '.' . $ext;
    $i = 2;
    while (file_exists($dir . '/' . $naam)) {
        $naam = $basis . '-' . $i . '.' . $ext;
        $i++;
    }

    if (!@move_uploaded_file($file['tmp_name'], $dir . '/' . $naam)) {
        return array('ok' => false, 'pad' => '', 'fout' => 'Het bestand kon niet worden opgeslagen.');
    }
    @chmod($dir . '/' . $naam, 0644);

    // Rechtstreeks van een telefoon geüploade foto's zijn vaak 3-12 MB en
    // veel breder dan ze ooit op de site getoond worden. Zonder deze stap
    // blijft zo'n foto op volle grootte staan en vertraagt hij elke pagina
    // waar hij op staat. We verkleinen daarom altijd tot maximaal
    // CMS_UPLOAD_MAX_AFMETING breed/hoog (met behoud van beeldverhouding) en
    // comprimeren opnieuw. Lukt dat om wat voor reden dan ook niet (bv. een
    // ongebruikelijk bestandstype), dan blijft het origineel gewoon staan —
    // dit mag de upload zelf nooit laten mislukken.
    verwerk_upload_verklein($dir . '/' . $naam, $info[2]);

    return array('ok' => true, 'pad' => CMS_UPLOAD_DIR . '/' . $naam, 'fout' => '');
}

/**
 * Verkleint en comprimeert een net geüploade afbeelding op zijn plek, als hij
 * groter is dan CMS_UPLOAD_MAX_AFMETING. Zelfde aanpak als de heroafbeeldingen
 * in veel beeldbeheerders: Imagick met automatische EXIF-oriëntatie, of anders GD.
 * Faalt dit (ontbrekende GD/Imagick-ondersteuning, beschadigd bestand), dan
 * blijft gewoon het origineel staan — geen foutmelding richting de gebruiker.
 */
function verwerk_upload_verklein($pad, $gdType) {
    $max = CMS_UPLOAD_MAX_AFMETING;

    if (class_exists('Imagick')) {
        try {
            $beeld = new Imagick($pad);
            if ($beeld->getNumberImages() > 1) {
                // Animatie (bv. GIF): niet aankomen, verkleinen zou 'm breken.
                $beeld->clear();
                $beeld->destroy();
                return;
            }
            if (method_exists($beeld, 'autoOrient')) { $beeld->autoOrient(); }
            elseif (method_exists($beeld, 'autoOrientImage')) { $beeld->autoOrientImage(); }
            $iw = (int) $beeld->getImageWidth();
            $ih = (int) $beeld->getImageHeight();
            if ($iw > $max || $ih > $max) {
                $beeld->resizeImage($max, $max, Imagick::FILTER_LANCZOS, 1, true);
            }
            $beeld->stripImage();
            $formaat = strtolower($beeld->getImageFormat());
            if ($formaat === 'jpeg' || $formaat === 'jpg') {
                $beeld->setImageCompressionQuality(82);
            } elseif ($formaat === 'webp') {
                $beeld->setImageCompressionQuality(82);
            }
            $beeld->writeImage($pad);
            $beeld->clear();
            $beeld->destroy();
            return;
        } catch (Exception $e) {
            // Terugval op GD hieronder.
        }
    }

    if (!function_exists('imagecreatetruecolor')) { return; }
    if ($gdType === IMAGETYPE_JPEG) { $beeld = @imagecreatefromjpeg($pad); }
    elseif ($gdType === IMAGETYPE_PNG) { $beeld = @imagecreatefrompng($pad); }
    elseif ($gdType === IMAGETYPE_WEBP && function_exists('imagecreatefromwebp')) { $beeld = @imagecreatefromwebp($pad); }
    else { return; } // GIF (mogelijk animatie) en onbekende typen: ongemoeid laten.
    if (!$beeld) { return; }

    if ($gdType === IMAGETYPE_JPEG && function_exists('exif_read_data')) {
        $exif = @exif_read_data($pad);
        $orientatie = isset($exif['Orientation']) ? (int) $exif['Orientation'] : 1;
        if ($orientatie === 3) { $beeld = imagerotate($beeld, 180, 0); }
        elseif ($orientatie === 6) { $beeld = imagerotate($beeld, -90, 0); }
        elseif ($orientatie === 8) { $beeld = imagerotate($beeld, 90, 0); }
    }

    $iw = imagesx($beeld);
    $ih = imagesy($beeld);
    if ($iw > $max || $ih > $max) {
        $factor = min($max / $iw, $max / $ih);
        $nieuwW = max(1, (int) round($iw * $factor));
        $nieuwH = max(1, (int) round($ih * $factor));
        $uit = imagecreatetruecolor($nieuwW, $nieuwH);
        if ($gdType === IMAGETYPE_PNG) {
            imagealphablending($uit, false);
            imagesavealpha($uit, true);
        }
        imagecopyresampled($uit, $beeld, 0, 0, 0, 0, $nieuwW, $nieuwH, $iw, $ih);
        imagedestroy($beeld);
        $beeld = $uit;
    }

    if ($gdType === IMAGETYPE_JPEG) { imagejpeg($beeld, $pad, 82); }
    elseif ($gdType === IMAGETYPE_PNG) { imagepng($beeld, $pad, 6); }
    elseif ($gdType === IMAGETYPE_WEBP && function_exists('imagewebp')) { imagewebp($beeld, $pad, 82); }
    imagedestroy($beeld);
}

/**
 * Lijst met bruikbare afbeeldingen voor de fotokiezer (lijst-module en velden met
 * 'type' => 'afbeelding'). Sleutel = pad t.o.v. de sitewortel, waarde = label.
 * Begint met '(geen afbeelding)'.
 *
 * Doorzoekt assets/img/ ÉN alle submappen daarvan. Dat "alle submappen" is niet
 * netheid maar noodzaak: deze lijst is tegelijk de TOEGESTANE-WAARDENLIJST bij het
 * opslaan. Een pad dat hier niet in staat wordt bij opslaan leeggemaakt — zo hoort
 * het, want anders is een fotoveld een vrije tekstinvoer die naar elk bestand op de
 * server kan wijzen. Maar het betekent ook: staat een foto die de pagina wél
 * gebruikt niet in deze lijst, dan wist één klik op Opslaan hem. Sites zetten hun
 * beeld in submappen (assets/img/projecten/…), dus hier niet recursief zoeken was
 * een stille wisser.
 */
function beschikbare_afbeeldingen() {
    $root  = dirname(__DIR__); // sitewortel (map boven _lib)
    $basis = 'assets/img';
    $exts  = array('jpg', 'jpeg', 'png', 'webp', 'gif');
    $lijst = array('' => '(geen afbeelding)');

    $dir = $root . '/' . $basis;
    if (!is_dir($dir)) { return $lijst; }

    // Breedte-eerst door de mappen, zodat assets/img/foo.jpg vóór
    // assets/img/projecten/bar.jpg staat: de losse beelden bovenaan, de
    // verzamelmappen daaronder.
    $tedoen = array($basis);
    $gevonden = array();

    while ($tedoen) {
        $map = array_shift($tedoen);
        $pad = $root . '/' . $map;
        $items = @scandir($pad);
        if ($items === false) { continue; }
        sort($items);

        foreach ($items as $f) {
            if ($f === '.' || $f === '..' || $f[0] === '.') { continue; }
            $vol = $pad . '/' . $f;
            if (is_dir($vol)) { $tedoen[] = $map . '/' . $f; continue; }
            $ext = strtolower(pathinfo($f, PATHINFO_EXTENSION));
            if (!in_array($ext, $exts, true)) { continue; }

            // Label: bestandsnaam, met de submap ervoor als hij er een heeft.
            // Twee foto's mogen dezelfde naam hebben in verschillende mappen;
            // zonder de map erbij zijn ze in de keuzelijst niet te onderscheiden.
            $relatief = substr($map . '/' . $f, strlen($basis) + 1);
            $gevonden[$map . '/' . $f] = $relatief;
        }
    }

    return $lijst + $gevonden;
}
