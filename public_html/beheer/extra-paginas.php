<?php
/** Nieuwe en bewerkbare JSON-pagina's; publieke vormgeving blijft site-eigen. */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/cms.php';
require_once __DIR__ . '/../_lib/eigen_paginas.php';
require_once __DIR__ . '/../_lib/paginas.php';
if (defined('CMS_MODULE_SECTIE_INDELING') && CMS_MODULE_SECTIE_INDELING) { require_once __DIR__ . '/../_lib/sectie_indeling.php'; }
auth_boot();
require_login('content');
if (!cms_module_aan('eigen_paginas')) { header('Location: paginas.php'); exit; }

$nieuw = isset($_GET['nieuw']);
$bewerk = isset($_GET['bewerk']) ? (string) $_GET['bewerk'] : '';
if (!$nieuw && $bewerk === '') { header('Location: paginas.php'); exit; }

$pagina = $nieuw ? eigen_pagina_aanvullen('', array()) : eigen_pagina($bewerk);
if (!$pagina) { http_response_code(404); exit('Deze extra pagina bestaat niet.'); }
$msg = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $invoer = $_POST;
    $invoer['afbeelding'] = (string) ($pagina['afbeelding'] ?? '');
    if (!empty($_POST['afbeelding_verwijderen'])) { $invoer['afbeelding'] = ''; }
    $upload = verwerk_upload($_FILES['afbeelding_upload'] ?? null);
    if (!$upload['ok']) {
        $msg = $upload['fout'];
    } else {
        if ($upload['pad'] !== '') { $invoer['afbeelding'] = $upload['pad']; }
        $resultaat = eigen_pagina_opslaan($nieuw ? null : $bewerk, $invoer, huidige_gebruiker(), is_admin());
        if ($resultaat['ok']) {
            sitemap_regenereren();
            $_SESSION['extra_flash'] = $nieuw
                ? (!empty($resultaat['concept']) ? 'Pagina aangemaakt als concept.' : 'Pagina aangemaakt en online gezet.')
                : 'Pagina opgeslagen.';
            header('Location: extra-paginas.php?bewerk=' . rawurlencode($resultaat['slug']));
            exit;
        }
        $msg = implode(' ', $resultaat['fouten']);
        $pagina = array_merge($pagina, $resultaat['data'], array('slug' => $invoer['slug'] ?? $bewerk));
    }
}
if (!empty($_SESSION['extra_flash'])) {
    $msg = (string) $_SESSION['extra_flash'];
    $ok = true;
    unset($_SESSION['extra_flash']);
}

$pageTitle = $nieuw ? 'Nieuwe pagina' : 'Pagina bewerken';
$pagina['html'] = eigen_html_filteren($pagina['html']);
$eigenSectieZone = function_exists('cms_sectie_eigen_zone') ? cms_sectie_eigen_zone() : '';
$eigenSectieDef = $eigenSectieZone !== '' ? cms_sectie_zone($eigenSectieZone) : null;
$eigenSectieIndeling = $eigenSectieDef ? cms_eigen_sectie_indeling($pagina) : null;
require __DIR__ . '/_head.php';
?>
<p><a href="paginas.php">&larr; Alle pagina&rsquo;s</a></p>
<div class="head-row">
  <h1><?= $nieuw ? 'Nieuwe pagina' : e($pagina['titel']) ?></h1>
  <?php if (!$nieuw && ($link = cms_publieke_link($bewerk)) !== ''): ?><a class="btn ghost" href="<?= e($link) ?>" target="_blank" rel="noopener">Bekijk pagina &uarr;</a><?php endif; ?>
</div>
<p class="lead">De inhoud wordt veilig in JSON opgeslagen. De gekoppelde website toont deze pagina met haar eigen vormgeving.</p>
<?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>

<form method="post" class="stack seo-form" data-seo-form enctype="multipart/form-data">
  <?= csrf_field() ?>
  <h2 class="groep">Pagina</h2>
  <label class="field"><span>Paginatitel (H1)</span><input type="text" name="titel" maxlength="120" value="<?= e($pagina['titel']) ?>" required></label>
  <?php if ($nieuw): ?>
  <label class="field"><span>Adres van de pagina</span><input type="text" name="slug" maxlength="80" value="<?= e($pagina['slug']) ?>" placeholder="leeg = automatisch uit de titel" pattern="[a-z0-9\-]*" autocapitalize="none" spellcheck="false"><small class="hint">Alleen kleine letters, cijfers en streepjes. Na aanmaken blijft dit adres vast.</small></label>
  <?php else: ?>
  <div class="info-blok"><strong>Adres</strong><code>/<?= e($bewerk) ?></code></div>
  <?php endif; ?>
  <label class="field"><span>Introductie</span><textarea name="intro" rows="3" maxlength="600"><?= e($pagina['intro']) ?></textarea></label>

  <h2 class="groep">Zoekresultaat</h2>
  <label class="field"><span>SEO-titel</span><input type="text" name="seo_titel" maxlength="160" value="<?= e($pagina['seo_titel']) ?>" placeholder="Leeg = paginatitel | sitenaam"></label>
  <label class="field"><span>Meta-omschrijving</span><textarea name="omschrijving" rows="3" maxlength="320" required><?= e($pagina['omschrijving']) ?></textarea></label>

  <h2 class="groep">Inhoud</h2>
  <div class="seo-editor" data-seo-editor-wrap>
    <div class="seo-editor__balk" role="toolbar" aria-label="Tekstopmaak">
      <select data-seo-blok aria-label="Tekstsoort"><option value="p">Tekst</option><option value="h2">Tussenkop (H2)</option><option value="h3">Subkop (H3)</option></select>
      <button type="button" data-seo-cmd="bold"><strong>Vet</strong></button>
      <button type="button" data-seo-cmd="italic"><em>Cursief</em></button>
      <button type="button" data-seo-cmd="insertUnorderedList">Lijst</button>
      <button type="button" data-seo-link>Link</button>
      <button type="button" data-seo-cmd="removeFormat">Opmaak wissen</button>
      <button type="button" class="seo-editor__html-knop" data-seo-html-toggle aria-pressed="false">&lt;/&gt; HTML-code</button>
    </div>
    <div class="seo-editor__inhoud" contenteditable="true" data-seo-editor data-placeholder="Schrijf hier de pagina-inhoud."><?= $pagina['html'] ?></div>
    <textarea class="seo-editor__html" data-seo-html-vak hidden spellcheck="false" aria-label="HTML-code"></textarea>
    <textarea name="html" data-seo-opslag hidden><?= e($pagina['html']) ?></textarea>
  </div>
  <small class="hint">H1 wordt H2; scripts, formulieren, onbekende stijlen en onveilige links worden verwijderd.</small>

  <?php if ($eigenSectieDef && $eigenSectieIndeling): ?>
  <h2 class="groep">Pagina-indeling</h2>
  <input type="hidden" name="secties_verzonden" value="1">
  <fieldset class="sectie-indeling"><legend>Onderdelen op deze pagina</legend><ol class="sectie-indeling__lijst" data-sectie-lijst>
    <?php foreach ($eigenSectieIndeling['volgorde'] as $sleutel): ?>
    <li class="sectie-indeling__item<?= $sleutel === 'inhoud' ? ' is-inhoud' : '' ?>" data-sectie-item><input type="hidden" name="sectie_volgorde[]" value="<?= e($sleutel) ?>">
      <?php if ($sleutel === 'inhoud'): ?><span class="sectie-indeling__inhoud"><?= e($eigenSectieDef['inhoud_label']) ?></span>
      <?php else: ?><label class="field-check"><input type="checkbox" name="secties[]" value="<?= e($sleutel) ?>"<?= in_array($sleutel, $eigenSectieIndeling['aan'], true) ? ' checked' : '' ?>><span><?= e($eigenSectieDef['secties'][$sleutel]['label']) ?></span></label><?php endif; ?>
      <span class="sectie-indeling__knoppen"><button class="btn mini ghost" type="button" data-sectie-op aria-label="Omhoog">&#9650;</button><button class="btn mini ghost" type="button" data-sectie-neer aria-label="Omlaag">&#9660;</button></span>
    </li><?php endforeach; ?>
  </ol><small class="hint">De eigen inhoud blijft altijd zichtbaar, maar kan wel tussen de geregistreerde siteonderdelen worden geplaatst.</small></fieldset>
  <?php endif; ?>

  <h2 class="groep">Afbeelding</h2>
  <?php if ($pagina['afbeelding'] !== ''): ?><img class="preview" src="../<?= e($pagina['afbeelding']) ?>" alt=""><?php endif; ?>
  <label class="field"><span>Nieuwe afbeelding</span><input type="file" name="afbeelding_upload" accept="image/jpeg,image/png,image/gif,image/webp"></label>
  <label class="field"><span>Beschrijving</span><input type="text" name="afbeelding_alt" maxlength="250" value="<?= e($pagina['afbeelding_alt']) ?>"></label>
  <?php if ($pagina['afbeelding'] !== ''): ?><label class="field-check"><input type="checkbox" name="afbeelding_verwijderen" value="1"><span>Huidige afbeelding weghalen</span></label><?php endif; ?>

  <?php if (is_admin()): ?>
  <h2 class="groep">Menu</h2>
  <label class="field"><span>Plek in het menu</span><select name="menu"><?php foreach (EIGEN_MENU_PLEKKEN as $code => $label): ?><option value="<?= e($code) ?>"<?= $pagina['menu'] === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
  <label class="field"><span>Menutekst</span><input type="text" name="menu_label" maxlength="40" value="<?= e($pagina['menu_label']) ?>" placeholder="Leeg = paginatitel"></label>
  <label class="field"><span>Volgorde</span><input type="number" name="volgorde" min="0" max="999" value="<?= (int) $pagina['volgorde'] ?>"></label>
  <?php endif; ?>
  <div class="actions"><button class="btn" type="submit"><?= $nieuw ? 'Pagina aanmaken' : 'Opslaan' ?></button></div>
</form>
<script src="seo-editor.js?v=<?= (int) @filemtime(__DIR__ . '/seo-editor.js') ?>"></script>
<?php require __DIR__ . '/_foot.php'; ?>
