<?php
/**
 * Extra pagina's die volledig in JSON leven.
 *
 * Het beheer schrijft nooit PHP. Een doelsite koppelt één eigen publiek
 * paginasjabloon aan deze records; zie integratie/eigen-pagina-template.php.
 */
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/data.php';
require_once __DIR__ . '/helpers.php';
if (defined('CMS_MODULE_SECTIE_INDELING') && CMS_MODULE_SECTIE_INDELING) {
    require_once __DIR__ . '/sectie_indeling.php';
}

const EIGEN_MENU_PLEKKEN = array(
    'geen'   => 'Niet in het menu',
    'hoofd'  => 'Hoofdmenu',
    'footer' => 'Footermenu',
);

function eigen_paginas_alle() {
    $data = load_json('paginas-eigen.json', array());
    return is_array($data) ? $data : array();
}

function eigen_register_sleutel($slug) { return 'eigen:' . $slug; }

function eigen_pagina_aanvullen($slug, array $pagina) {
    return array_merge(array(
        'slug' => $slug,
        'titel' => '',
        'intro' => '',
        'seo_titel' => '',
        'omschrijving' => '',
        'html' => '',
        'afbeelding' => '',
        'afbeelding_alt' => '',
        'menu' => 'geen',
        'menu_label' => '',
        'volgorde' => 50,
        'secties' => null,
        'sectie_volgorde' => array(),
        'gemaakt' => '',
        'gemaakt_door' => '',
        'bijgewerkt' => '',
        'bijgewerkt_door' => '',
    ), $pagina, array('slug' => $slug));
}

function eigen_pagina($slug) {
    $alle = eigen_paginas_alle();
    return isset($alle[$slug]) && is_array($alle[$slug])
        ? eigen_pagina_aanvullen($slug, $alle[$slug]) : null;
}

function eigen_lengte($tekst) {
    return function_exists('mb_strlen') ? mb_strlen((string) $tekst, 'UTF-8') : strlen((string) $tekst);
}

function eigen_plat($tekst) {
    $tekst = preg_replace('#<(script|style)\b[^>]*>.*?</\1>#is', '', (string) $tekst);
    return trim(preg_replace('/\s+/u', ' ', strip_tags($tekst)));
}

function eigen_slug_maken($tekst) {
    $tekst = (string) $tekst;
    if (function_exists('transliterator_transliterate')) {
        $ascii = @transliterator_transliterate('Any-Latin; Latin-ASCII', $tekst);
        if (is_string($ascii)) { $tekst = $ascii; }
    } elseif (function_exists('iconv')) {
        $ascii = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $tekst);
        if (is_string($ascii)) { $tekst = $ascii; }
    }
    $tekst = strtolower($tekst);
    return substr(trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', $tekst)), '-'), 0, 80);
}

function eigen_slug_gereserveerd() {
    $lijst = array('beheer', 'assets', 'data', 'partials', 'templates', '_lib', 'lib',
        'eigen-pagina', 'index', 'sitemap', 'robots', 'feed', 'admin', 'login',
        'wp-admin', 'wp-login', 'wp-content', 'wp-includes', 'wp-json');
    $root = dirname(__DIR__);
    foreach ((array) @scandir($root) as $bestand) {
        if ($bestand === '.' || $bestand === '..') { continue; }
        $lijst[] = strtolower(preg_replace('/\.php$/', '', $bestand));
    }
    if (function_exists('content_paginas')) {
        foreach (content_paginas() as $pagina) {
            if (($pagina['url'] ?? '') !== '') { $lijst[] = strtolower((string) $pagina['url']); }
        }
    }
    return array_values(array_unique($lijst));
}

function eigen_slug_fout($slug) {
    if ($slug === '') { return 'Vul een URL-naam in.'; }
    if (strlen($slug) < 3 || strlen($slug) > 80) { return 'De URL-naam moet 3 tot 80 tekens lang zijn.'; }
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $slug)) {
        return 'Gebruik alleen kleine letters, cijfers en streepjes in de URL-naam.';
    }
    if (in_array($slug, eigen_slug_gereserveerd(), true)) { return 'De URL /' . $slug . ' is al in gebruik.'; }
    if (eigen_pagina($slug) !== null) { return 'Er bestaat al een extra pagina met deze URL.'; }
    return '';
}

/** Alleen afbeeldingen uit assets/img zijn toegestaan. */
function eigen_afbeelding_veilig($pad) {
    $pad = ltrim(trim((string) $pad), '/');
    if ($pad === '' || strpos($pad, '..') !== false) { return ''; }
    return preg_match('~^assets/img/[A-Za-z0-9/_.-]+\.(jpe?g|png|gif|webp)$~i', $pad) ? $pad : '';
}

/** Veilige editor-HTML met H2/H3, lijsten, links en lokaal geüploade beelden. */
function eigen_html_filteren($html) {
    $html = preg_replace('~<(/?)h[1](?=[\s>])~i', '<$1h2', (string) $html);
    $html = preg_replace('~<(/?)h[4-6](?=[\s>])~i', '<$1h3', $html);
    $beelden = array();
    $html = preg_replace_callback('~<img\b[^>]*>~i', function ($match) use (&$beelden) {
        $src = '';
        $alt = '';
        if (preg_match('~\bsrc\s*=\s*(["\'])(.*?)\1~i', $match[0], $m)) { $src = eigen_afbeelding_veilig($m[2]); }
        if (preg_match('~\balt\s*=\s*(["\'])(.*?)\1~i', $match[0], $m)) {
            $alt = eigen_plat(html_entity_decode($m[2], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        if ($src === '') { return ''; }
        $token = 'CMSAFBEELDING' . count($beelden) . 'TOKEN';
        $beelden[$token] = '<img src="/' . e($src) . '" alt="' . e(substr($alt, 0, 250)) . '" loading="lazy" decoding="async">';
        return $token;
    }, $html);
    $html = veld_html_opslaan($html, true);
    return trim(strtr($html, $beelden));
}

function eigen_pagina_opslaan($slug, array $invoer, array $gebruiker, $magMenu) {
    $alle = eigen_paginas_alle();
    $nieuw = $slug === null;
    $fouten = array();
    if ($nieuw) {
        $slug = eigen_slug_maken($invoer['slug'] ?? '');
        if ($slug === '') { $slug = eigen_slug_maken($invoer['titel'] ?? ''); }
        $fout = eigen_slug_fout($slug);
        if ($fout !== '') { $fouten[] = $fout; }
        $oud = eigen_pagina_aanvullen($slug, array());
    } elseif (!isset($alle[$slug])) {
        return array('ok' => false, 'fouten' => array('Deze pagina bestaat niet meer.'), 'slug' => $slug, 'data' => array());
    } else {
        $oud = eigen_pagina_aanvullen($slug, (array) $alle[$slug]);
    }

    $data = $oud;
    $data['titel'] = eigen_plat($invoer['titel'] ?? '');
    $data['intro'] = eigen_plat($invoer['intro'] ?? '');
    $data['seo_titel'] = eigen_plat($invoer['seo_titel'] ?? '');
    $data['omschrijving'] = eigen_plat($invoer['omschrijving'] ?? '');
    $data['html'] = eigen_html_filteren($invoer['html'] ?? '');
    $data['afbeelding'] = eigen_afbeelding_veilig($invoer['afbeelding'] ?? $oud['afbeelding']);
    $data['afbeelding_alt'] = substr(eigen_plat($invoer['afbeelding_alt'] ?? $oud['afbeelding_alt']), 0, 250);
    if ($magMenu) {
        $menu = (string) ($invoer['menu'] ?? 'geen');
        $data['menu'] = array_key_exists($menu, EIGEN_MENU_PLEKKEN) ? $menu : 'geen';
        $data['menu_label'] = substr(eigen_plat($invoer['menu_label'] ?? ''), 0, 40);
        $data['volgorde'] = max(0, min(999, (int) ($invoer['volgorde'] ?? 50)));
    }
    if (!empty($invoer['secties_verzonden']) && function_exists('cms_sectie_eigen_zone')) {
        $sectieZone = cms_sectie_eigen_zone();
        if ($sectieZone !== '') {
            $indeling = cms_sectie_indeling_normaliseer($sectieZone, array(
                'volgorde' => is_array($invoer['sectie_volgorde'] ?? null) ? $invoer['sectie_volgorde'] : array(),
                'aan' => is_array($invoer['secties'] ?? null) ? $invoer['secties'] : array(),
            ), true);
            $data['sectie_volgorde'] = $indeling['volgorde'];
            $data['secties'] = $indeling['aan'];
        }
    }

    if ($data['titel'] === '') { $fouten[] = 'Vul een paginatitel in.'; }
    elseif (eigen_lengte($data['titel']) > 120) { $fouten[] = 'De paginatitel is te lang.'; }
    if (eigen_lengte($data['intro']) > 600) { $fouten[] = 'De introductie is te lang.'; }
    if ($data['seo_titel'] === '') { $data['seo_titel'] = $data['titel'] . ' | ' . CMS_SITE_NAME; }
    if (eigen_lengte($data['seo_titel']) > 160) { $fouten[] = 'De SEO-titel is te lang.'; }
    if ($data['omschrijving'] === '') { $fouten[] = 'Vul een meta-omschrijving in.'; }
    elseif (eigen_lengte($data['omschrijving']) > 320) { $fouten[] = 'De meta-omschrijving is te lang.'; }
    if ($fouten) { return array('ok' => false, 'fouten' => $fouten, 'slug' => $slug, 'data' => $data); }

    $door = trim((string) ($gebruiker['naam'] ?? $gebruiker['email'] ?? 'beheerder'));
    if ($nieuw) {
        $data['gemaakt'] = date('c');
        $data['gemaakt_door'] = $door;
        $status = load_json('paginastatus.json', array());
        unset($status[eigen_register_sleutel($slug)]);
        if (!$magMenu) { $status[eigen_register_sleutel($slug)] = array('actief' => false); }
        save_json('paginastatus.json', $status);
    }
    $data['bijgewerkt'] = date('c');
    $data['bijgewerkt_door'] = $door;
    unset($data['slug']);
    $alle[$slug] = $data;
    if (!save_json('paginas-eigen.json', $alle)) {
        return array('ok' => false, 'fouten' => array('Opslaan mislukte. Is data/ schrijfbaar?'), 'slug' => $slug, 'data' => $data);
    }
    return array('ok' => true, 'fouten' => array(), 'slug' => $slug, 'data' => eigen_pagina_aanvullen($slug, $data), 'concept' => $nieuw && !$magMenu);
}

function eigen_pagina_verwijderen($slug) {
    $alle = eigen_paginas_alle();
    if (!isset($alle[$slug])) { return 'Deze pagina bestaat niet meer.'; }
    $archief = CMS_DATA_DIR . '/verwijderde-paginas';
    if (!is_dir($archief) && !@mkdir($archief, 0755, true)) { return 'De back-upmap kon niet worden gemaakt.'; }
    $rijen = load_json('tekstrijen.json', array());
    $zone = 'eigen-' . $slug;
    $kopie = json_encode(array($slug => $alle[$slug], 'tekstrijen' => $rijen[$zone] ?? array()), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if (@file_put_contents($archief . '/eigen-' . $slug . '--' . date('Ymd-His') . '.json', $kopie, LOCK_EX) === false) {
        return 'Kon geen back-up maken; de pagina is niet verwijderd.';
    }
    unset($alle[$slug]);
    if (!save_json('paginas-eigen.json', $alle)) { return 'Opslaan mislukte.'; }
    $status = load_json('paginastatus.json', array());
    unset($status[eigen_register_sleutel($slug)]);
    save_json('paginastatus.json', $status);
    if (isset($rijen[$zone])) { unset($rijen[$zone]); save_json('tekstrijen.json', $rijen); }
    return '';
}

function eigen_menu_items($plek) {
    $uit = array();
    foreach (eigen_paginas_alle() as $slug => $record) {
        $pagina = eigen_pagina_aanvullen($slug, (array) $record);
        if ($pagina['menu'] !== $plek) { continue; }
        if (function_exists('pagina_is_actief') && !pagina_is_actief(eigen_register_sleutel($slug))) { continue; }
        $uit[] = array('slug' => $slug, 'url' => $slug,
            'label' => $pagina['menu_label'] !== '' ? $pagina['menu_label'] : $pagina['titel'],
            'volgorde' => (int) $pagina['volgorde']);
    }
    usort($uit, function ($a, $b) { return $a['volgorde'] <=> $b['volgorde'] ?: strcasecmp($a['label'], $b['label']); });
    return $uit;
}
