<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/velden.php';
require_once __DIR__ . '/../_lib/cms.php';
require_once __DIR__ . '/../_lib/paginas.php';
auth_boot();
require_login('seo');
if (!cms_module_aan('seo')) { header('Location: index.php'); exit; }

$paginas = site_pagina_register();
$sleutel = (string) ($_GET['pagina'] ?? '');
if (!isset($paginas[$sleutel])) { $sleutel = (string) array_key_first($paginas); }
$seo = load_json('seo.json', array());
$waarden = array_merge(array('titel'=>'','omschrijving'=>'','canonical'=>'','og_afbeelding'=>''), $seo[$sleutel] ?? array());
$waarden['indexeren'] = pagina_is_indexeerbaar($sleutel);
$msg = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $postSleutel = (string) ($_POST['pagina'] ?? '');
    if (isset($paginas[$postSleutel])) { $sleutel = $postSleutel; }
    if (!csrf_check()) { $msg = 'Sessie verlopen, probeer het opnieuw.'; }
    else {
        $canonicalRuw = trim((string) ($_POST['canonical'] ?? ''));
        $canonical = $canonicalRuw === '' ? '' : cms_url_opschonen($canonicalRuw, false);
        $bewaardAfbeelding = trim((string) ($_POST['og_afbeelding_bewaard'] ?? ''));
        $bekendeAfbeeldingen = beschikbare_afbeeldingen();
        if ($bewaardAfbeelding !== '' && !isset($bekendeAfbeeldingen[$bewaardAfbeelding])) { $bewaardAfbeelding = ''; }
        $waarden = array(
            'titel' => trim((string) ($_POST['titel'] ?? '')),
            'omschrijving' => trim((string) ($_POST['omschrijving'] ?? '')),
            'canonical' => $canonical,
            'og_afbeelding' => $bewaardAfbeelding,
        );
        $waarden['indexeren'] = !empty($_POST['indexeren']);
        $up = verwerk_upload($_FILES['og_afbeelding_upload'] ?? null);
        if (!$up['ok']) { $msg = $up['fout']; }
        elseif ($canonicalRuw !== '' && $canonical === '') { $msg = 'De canonical-URL is ongeldig.'; }
        else {
            if ($up['pad'] !== '') { $waarden['og_afbeelding'] = $up['pad']; }
            $seo[$sleutel] = $waarden;
            unset($seo[$sleutel]['indexeren']);
            $status = pagina_status_alles();
            if (!empty($_POST['indexeren'])) { unset($status[$sleutel]['noindex']); }
            else { $status[$sleutel]['noindex'] = true; }
            if (empty($status[$sleutel])) { unset($status[$sleutel]); }
            if (save_json('seo.json', $seo) && save_json('paginastatus.json', $status)) { sitemap_regenereren(); $ok = true; $msg = 'SEO-instellingen opgeslagen.'; }
            else { $msg = 'Opslaan mislukte. Is de map data/ schrijfbaar?'; }
        }
    }
}

$pageTitle = 'SEO | ' . ($paginas[$sleutel]['label'] ?? 'Pagina');
require __DIR__ . '/_head.php';
?>
<div class="head-row"><h1>SEO per pagina</h1><a class="btn ghost" href="paginas.php">Alle pagina&rsquo;s</a></div>
<p class="lead">Beheer zoekresultaatinformatie en social sharing. De publieke site leest dit via <code>cms_seo_voor()</code>.</p>
<nav class="paginakiezer" aria-label="Kies een pagina"><?php foreach ($paginas as $key => $pagina): ?><a href="?pagina=<?= e($key) ?>" class="<?= $key === $sleutel ? 'actief' : '' ?>"><?= e($pagina['label']) ?></a><?php endforeach; ?></nav>
<?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>
<form method="post" class="stack" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="pagina" value="<?= e($sleutel) ?>">
  <label class="field"><span>SEO-titel</span><input type="text" name="titel" maxlength="160" value="<?= e($waarden['titel']) ?>"><small class="hint">Aanbevolen: ongeveer 50–60 tekens.</small></label>
  <label class="field"><span>Meta-omschrijving</span><textarea name="omschrijving" rows="4" maxlength="320"><?= e($waarden['omschrijving']) ?></textarea><small class="hint">Aanbevolen: ongeveer 140–160 tekens.</small></label>
  <label class="field"><span>Canonical-URL</span><input type="url" name="canonical" value="<?= e($waarden['canonical']) ?>" placeholder="https://voorbeeld.nl/pagina"><small class="hint">Leeg = de publieke template bepaalt de standaard-URL.</small></label>
  <label class="field"><span>Social-afbeelding</span><?php if (!empty($waarden['og_afbeelding'])): ?><img class="preview" src="../<?= e($waarden['og_afbeelding']) ?>" alt=""><?php endif; ?><input type="hidden" name="og_afbeelding_bewaard" value="<?= e($waarden['og_afbeelding']) ?>"><input type="file" name="og_afbeelding_upload" accept="image/jpeg,image/png,image/gif,image/webp"></label>
  <label class="field-check"><input type="checkbox" name="indexeren" value="1"<?= !empty($waarden['indexeren']) ? ' checked' : '' ?>><span>Zoekmachines mogen deze pagina indexeren</span></label>
  <div class="actions"><button class="btn" type="submit">Opslaan</button></div>
</form>
<?php require __DIR__ . '/_foot.php'; ?>
