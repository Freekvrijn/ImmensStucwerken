<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/velden.php';
require_once __DIR__ . '/../_lib/paginas.php';
require_once __DIR__ . '/../_lib/cms.php';
auth_boot();
require_login('content');

// De pagina-indeling staat in _lib/velden.php — de enige plek die je per website
// aanpast. Deze pagina hoef je niet te wijzigen.
$paginas = content_paginas();

// Welke pagina bewerken we? Standaard de eerste. Alleen een geldige sleutel telt.
$actief = isset($_GET['pagina']) ? (string) $_GET['pagina'] : '';
if (!isset($paginas[$actief])) { $actief = (string) array_key_first($paginas); }

$content = load_json('content.json', array());

// Bekende afbeeldingen worden alleen gebruikt om een bewaard huidig pad te
// controleren. In het formulier kiest de beheerder uitsluitend een nieuwe upload.
$afbeeldingen = beschikbare_afbeeldingen();

$msg = '';
$ok  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // De pagina waarvoor werd opgeslagen komt uit het formulier zelf.
    $postPagina = isset($_POST['_pagina']) ? (string) $_POST['_pagina'] : $actief;
    if (!isset($paginas[$postPagina])) { $postPagina = $actief; }

    if (!csrf_check()) {
        $msg = 'Sessie verlopen, probeer het opnieuw.';
    } else {
        $uploadFout = '';

        // Alléén de velden van déze pagina overschrijven; de rest blijft staan.
        foreach (velden_van_pagina($postPagina) as $key => $def) {

            if (($def['type'] ?? 'text') === 'afbeelding') {
                // Geen beeldbankkeuze meer: behoud de huidige foto totdat er een
                // nieuwe upload is gekozen. Het verborgen pad wordt wel getoetst
                // aan de bekende afbeeldingen, zodat het geen vrije bestandsinvoer is.
                $gekozen = (string) ($content[$key] ?? '');
                $bewaard = trim((string) ($_POST[$key . '_bewaard'] ?? ''));
                if ($bewaard !== '' && array_key_exists($bewaard, $afbeeldingen)) {
                    $gekozen = $bewaard;
                }

                // Een verse upload vervangt de huidige afbeelding.
                $up = verwerk_upload($_FILES[$key . '_upload'] ?? null);
                if (!$up['ok']) {
                    $uploadFout = $up['fout'];
                } elseif ($up['pad'] !== '') {
                    $gekozen      = $up['pad'];
                    $afbeeldingen = beschikbare_afbeeldingen();
                }
                $content[$key] = $gekozen;
                continue;
            }

            if (($def['type'] ?? 'text') === 'link') {
                $content[$key] = veld_link_opslaan((string) ($_POST[$key] ?? ''));
                continue;
            }

            $content[$key] = (($def['type'] ?? 'text') !== 'afbeelding' && empty($def['plat']))
                ? trim(veld_html_opslaan((string) ($_POST[$key] ?? '')))
                : trim((string) ($_POST[$key] ?? ''));
        }

        // Een mislukte upload mag de rest NIET tegenhouden: anders raakt iemand die
        // een te grote foto koos ook al zijn typewerk op deze pagina kwijt. De
        // foto blijft dan gewoon staan zoals hij stond, en we zeggen wat er misging.
        if (save_json('content.json', $content)) {
            $ok     = ($uploadFout === '');
            $msg    = $uploadFout !== ''
                    ? 'Teksten opgeslagen, maar de afbeelding niet: ' . $uploadFout
                    : 'Teksten opgeslagen.';
            $actief = $postPagina;

        } else {
            $msg = 'Opslaan mislukte. Is de map data/ schrijfbaar?';
        }
    }
}

$paginaDef = $paginas[$actief];
$bekijkUrl = cms_publieke_link($paginaDef['url'] !== '' ? $paginaDef['url'] : '');

$pageTitle = 'Teksten | ' . $paginaDef['label'];
require __DIR__ . '/_head.php';
?>
<div class="head-row">
  <h1>Paginainhoud aanpassen</h1>
  <a class="btn ghost" href="paginas.php">Alle pagina&rsquo;s</a>
</div>
<p class="lead">Kies een websitepagina en pas de gekoppelde teksten, links en afbeeldingen aan.</p>

<nav class="paginakiezer" aria-label="Kies een pagina">
  <?php foreach ($paginas as $key => $def): ?>
    <a href="?pagina=<?= e($key) ?>" class="<?= $key === $actief ? 'actief' : '' ?>"><?= e($def['label']) ?><?= ($key !== 'algemeen' && function_exists('pagina_is_actief') && !pagina_is_actief($key)) ? ' <span class="badge err">uit</span>' : '' ?></a>
  <?php endforeach; ?>
</nav>

<?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>

<form method="post" class="stack" enctype="multipart/form-data">
  <?= csrf_field() ?>
  <input type="hidden" name="_pagina" value="<?= e($actief) ?>">
  <?php foreach ($paginaDef['groepen'] as $groep => $velden): ?>
    <h2 class="groep"><?= e($groep) ?></h2>
    <?php foreach ($velden as $key => $def): ?>
      <label class="field">
        <span>
          <?= e($def['label']) ?>
        </span>
        <?php $type = $def['type'] ?? 'text'; ?>
        <?php $plat = !empty($def['plat']); ?>
        <?php if (!$plat && in_array($type, array('text', 'textarea'), true)): ?>
          <?php $huidigeHtml = isset($def['standaard']) ? veld_of_standaard($content, $key, $def['standaard']) : (string) veld($content, $key, ''); ?>
          <div class="rijk-veld" data-rijk-veld>
            <div class="rijk-veld__voorbeeld" data-rijk-voorbeeld><?= $huidigeHtml !== '' ? $huidigeHtml : '<span class="rijk-veld__leeg">(leeg)</span>' ?></div>
            <textarea name="<?= e($key) ?>" class="rijk-veld__opslag" data-rijk-opslag hidden><?= e($huidigeHtml) ?></textarea>
            <button type="button" class="rijk-veld__bewerk" data-rijk-open data-rijk-label="<?= e($def['label']) ?>">
              <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>
              Bewerken &amp; opmaken
            </button>
          </div>

        <?php elseif ($type === 'textarea'): ?>
          <textarea name="<?= e($key) ?>" rows="3"><?= e(isset($def['standaard']) ? veld_of_standaard($content, $key, $def['standaard']) : veld($content, $key)) ?></textarea>

        <?php elseif ($type === 'afbeelding'): ?>
          <?php $huidig = (string) veld($content, $key, ''); ?>
          <div class="fotoveld">
            <?php if ($huidig !== '' && is_file(dirname(__DIR__) . '/' . $huidig)): ?>
              <img class="preview" src="../<?= e($huidig) ?>" alt="">
            <?php else: ?>
              <div class="preview preview--leeg">geen foto</div>
            <?php endif; ?>
            <div class="fotoveld__keuze">
              <input type="hidden" name="<?= e($key) ?>_bewaard" value="<?= e($huidig) ?>">
              <input type="file" name="<?= e($key) ?>_upload" accept="image/jpeg,image/png,image/gif,image/webp">
              <small class="hint"><?= $huidig !== '' ? 'Kies alleen een bestand als u de huidige foto wilt vervangen. ' : '' ?>JPG, PNG, GIF of WEBP, max. <?= e(CMS_UPLOAD_MAX_MB) ?>&nbsp;MB.</small>
            </div>
          </div>

        <?php else: ?>
          <input type="text" name="<?= e($key) ?>" value="<?= e(isset($def['standaard']) ? veld_of_standaard($content, $key, $def['standaard']) : veld($content, $key)) ?>">
        <?php endif; ?>
        <?php if (!$plat && in_array($type, array('text', 'textarea'), true)): ?>
          <small class="hint">Klik op "Bewerken &amp; opmaken" voor vet, cursief, onderstrepen, een link of een lijst. Lettertype en -grootte zijn bedoeld als uitzondering op de sitebrede instelling; voor de meeste tekst is dat niet nodig.</small>
        <?php endif; ?>
        <?php if (!empty($def['opmaak'])): ?>
          <small class="hint">Tip: zet <code>**sterretjes**</code> om een woord om het <strong>vet</strong> te maken.</small>
        <?php endif; ?>
        <?php if (!empty($def['hint'])): ?>
          <small class="hint"><?= e($def['hint']) ?></small>
        <?php endif; ?>
      </label>
    <?php endforeach; ?>
  <?php endforeach; ?>
  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
    <?php if ($bekijkUrl !== ''): ?><a class="btn ghost" href="<?= e($bekijkUrl) ?>" target="_blank" rel="noopener">Bekijk deze pagina &uarr;</a><?php endif; ?>
  </div>
</form>

<!-- Eén gedeelde pop-up voor alle opmaakvelden op deze pagina. -->
<div class="rijk-modal" data-rijk-modal hidden>
  <div class="rijk-modal__scherm" data-rijk-sluiten></div>
  <div class="rijk-modal__paneel" role="dialog" aria-modal="true" aria-labelledby="rijk-modal-titel">
    <div class="rijk-modal__kop">
      <h3 id="rijk-modal-titel" data-rijk-titel>Tekst bewerken</h3>
      <button type="button" class="rijk-modal__sluit" data-rijk-sluiten aria-label="Sluiten zonder op te slaan">&times;</button>
    </div>
    <div class="rijk-modal__balk" data-rijk-balk>
      <button type="button" data-rijk-cmd="bold" title="Vet"><b>V</b></button>
      <button type="button" data-rijk-cmd="italic" title="Cursief"><i>C</i></button>
      <button type="button" data-rijk-cmd="underline" title="Onderstrepen"><u>O</u></button>
      <button type="button" data-rijk-cmd="insertUnorderedList" title="Opsommingstekens">&bull; Lijst</button>
      <button type="button" data-rijk-cmd="justifyLeft" title="Links uitlijnen">Links</button>
      <button type="button" data-rijk-cmd="justifyCenter" title="Centreren">Centreren</button>
      <button type="button" data-rijk-cmd="justifyRight" title="Rechts uitlijnen">Rechts</button>
      <button type="button" data-rijk-link title="Link toevoegen">&#128279; Link</button>
      <span class="rijk-modal__balk-scheiding"></span>
      <label class="rijk-modal__kleurknop" title="Tekstkleur">
        <input type="color" data-rijk-kleur value="#16231c">
      </label>
      <select data-rijk-lettertype title="Lettertype">
        <option value="">Lettertype</option>
        <option value="Georgia">Georgia (schreef)</option>
        <option value="Arial">Arial (schreefloos)</option>
        <option value="Verdana">Verdana (schreefloos)</option>
        <option value="Courier New">Courier New (monospace)</option>
		<option value="Poppins">Poppins</option>
        <option value="Montserrat">Montserrat</option>
        <option value="Roboto">Roboto</option>
        <option value="Open Sans">Open Sans</option>
        <option value="Lato">Lato</option>
      </select>
      <select data-rijk-grootte title="Tekengrootte">
        <option value="">Grootte</option>
        <option value="12px">12px</option>
        <option value="14px">14px</option>
        <option value="16px">16px (standaard)</option>
        <option value="18px">18px</option>
        <option value="20px">20px</option>
        <option value="24px">24px</option>
        <option value="28px">28px</option>
        <option value="1em">1em</option>
        <option value="1.25em">1,25em</option>
        <option value="1.5em">1,5em</option>
        <option value="2em">2em</option>
      </select>
      <button type="button" data-rijk-cmd="removeFormat" title="Opmaak wissen">Wissen</button>
      <button type="button" data-rijk-cmd="undo" title="Ongedaan maken">&#8634;</button>
      <button type="button" data-rijk-cmd="redo" title="Opnieuw">&#8635;</button>
    </div>
    <div class="rijk-modal__voorbeeldrij">
      <button type="button" data-rijk-voorbeeld-knop title="Voorbeeld tonen">Voorbeeld</button>
    </div>
    <div class="rijk-modal__link-vak" data-rijk-link-vak hidden>
      <label>Webadres (https://…, of tel:/mailto:)<input type="text" data-rijk-link-url placeholder="https://example.nl"></label>
      <label class="rijk-modal__link-tab"><input type="checkbox" data-rijk-link-blank> Openen in een nieuw tabblad</label>
      <div class="rijk-modal__link-acties">
        <button type="button" class="btn" data-rijk-link-toepassen>Link toepassen</button>
        <button type="button" class="btn ghost" data-rijk-link-annuleren>Annuleren</button>
      </div>
    </div>
    <div class="rijk-modal__editor" contenteditable="true" data-rijk-editor></div>
    <div class="rijk-modal__voorbeeld" data-rijk-voorbeeldvak hidden></div>
    <div class="rijk-modal__voet">
      <button type="button" class="btn" data-rijk-opslaan>Opslaan</button>
      <button type="button" class="btn ghost" data-rijk-annuleren>Annuleren (ongedaan maken)</button>
    </div>
  </div>
</div>

<link rel="stylesheet" href="rijk-editor.css">

<script src="rijk-editor.js"></script>

<?php require __DIR__ . '/_foot.php'; ?>
