<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/cms.php';
require_once __DIR__ . '/../_lib/sectie_indeling.php';
auth_boot();
require_login('content');
if (!cms_module_aan('sectie_indeling')) { header('Location: index.php'); exit; }

$zones = array_filter(cms_sectie_zones(), function ($definitie) { return empty($definitie['eigen_paginas']); });
if (!$zones) { header('Location: index.php'); exit; }
$zone = (string) ($_GET['zone'] ?? array_key_first($zones));
if (!isset($zones[$zone])) { $zone = (string) array_key_first($zones); }
$msg = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { $msg = 'Sessie verlopen, probeer het opnieuw.'; }
    else {
        $actie = (string) ($_POST['actie'] ?? 'opslaan');
        $volgorde = $actie === 'standaard' ? array_keys($zones[$zone]['secties']) : (array) ($_POST['volgorde'] ?? array());
        $aan = $actie === 'standaard' ? array_keys(array_filter($zones[$zone]['secties'], function ($def) { return $def['standaard_aan']; })) : (array) ($_POST['aan'] ?? array());
        $ok = cms_sectie_indeling_opslaan($zone, $volgorde, $aan);
        $msg = $ok ? ($actie === 'standaard' ? 'De standaardindeling is hersteld.' : 'De indeling is opgeslagen.') : 'Opslaan is niet gelukt.';
    }
}
$indeling = cms_sectie_indeling($zone);
$pageTitle = 'Pagina-indeling';
require __DIR__ . '/_head.php';
?>
<h1>Pagina-indeling</h1>
<p class="lead">Zet geregistreerde websiteonderdelen aan of uit en bepaal hun volgorde. De inhoud van een uitgezet onderdeel blijft bewaard.</p>
<?php if (count($zones) > 1): ?><nav class="paginakiezer"><?php foreach ($zones as $sleutel => $def): ?><a href="?zone=<?= e($sleutel) ?>" class="<?= $zone === $sleutel ? 'actief' : '' ?>"><?= e($def['label']) ?></a><?php endforeach; ?></nav><?php endif; ?>
<?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>
<form method="post" class="stack"><input type="hidden" name="actie" value="opslaan"><?= csrf_field() ?>
  <fieldset class="sectie-indeling"><legend><?= e($zones[$zone]['label']) ?></legend><ol class="sectie-indeling__lijst" data-sectie-lijst>
  <?php foreach ($indeling['volgorde'] as $sleutel): ?><li class="sectie-indeling__item" data-sectie-item><input type="hidden" name="volgorde[]" value="<?= e($sleutel) ?>"><label class="field-check"><input type="checkbox" name="aan[]" value="<?= e($sleutel) ?>"<?= in_array($sleutel, $indeling['aan'], true) ? ' checked' : '' ?>><span><?= e($zones[$zone]['secties'][$sleutel]['label']) ?></span></label><span class="sectie-indeling__knoppen"><button class="btn mini ghost" type="button" data-sectie-op aria-label="Omhoog">&#9650;</button><button class="btn mini ghost" type="button" data-sectie-neer aria-label="Omlaag">&#9660;</button></span></li><?php endforeach; ?>
  </ol></fieldset><div class="actions"><button class="btn" type="submit">Opslaan</button></div>
</form>
<form method="post" data-confirm="De standaardindeling herstellen?"><?= csrf_field() ?><input type="hidden" name="actie" value="standaard"><button class="btn ghost" type="submit">Standaardindeling herstellen</button></form>
<?php require __DIR__ . '/_foot.php'; ?>
