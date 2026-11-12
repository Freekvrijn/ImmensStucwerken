<?php
header('Location: paginas.php', true, 302);
exit;
/* Oude route blijft als compatibele doorverwijzing. */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/paginas.php';
auth_boot();
require_login('content');

$register = site_pagina_register();
$status = pagina_status_alles();
$msg = '';
$ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { $msg = 'Sessie verlopen, probeer het opnieuw.'; }
    else {
        $sleutel = (string) ($_POST['pagina'] ?? '');
        if (!isset($register[$sleutel])) { $msg = 'Onbekende pagina.'; }
        elseif (!empty($register[$sleutel]['kern'])) { $msg = 'Deze kernpagina staat altijd aan.'; }
        else {
            $status[$sleutel]['actief'] = !pagina_is_actief($sleutel);
            if (save_json('paginastatus.json', $status)) {
                $ok = true;
                $msg = !empty($status[$sleutel]['actief']) ? 'Pagina staat weer aan.' : 'Pagina staat nu uit.';
            } else { $msg = 'Opslaan mislukte. Is de map data/ schrijfbaar?'; }
        }
    }
}

$pageTitle = 'Zichtbaarheid pagina\'s';
require __DIR__ . '/_head.php';
?>
<div class="head-row"><h1>Zichtbaarheid pagina&rsquo;s</h1><a class="btn ghost" href="kernteksten.php">Teksten &amp; foto&rsquo;s</a></div>
<p class="lead">Zet geregistreerde pagina&rsquo;s aan of uit. De publieke template moet <code>cms_public_boot('paginasleutel')</code> gebruiken om deze status af te dwingen.</p>
<?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>
<table class="paginastatus-tabel">
  <thead><tr><th>Pagina</th><th>Adres</th><th>Status</th><th></th></tr></thead>
  <tbody>
  <?php foreach ($register as $sleutel => $pagina): $actief = pagina_is_actief($sleutel); $kern = !empty($pagina['kern']); ?>
    <tr>
      <td><strong><?= e($pagina['label']) ?></strong></td>
      <td><code><?= e($pagina['url'] === '' ? '/' : '/' . ltrim($pagina['url'], '/')) ?></code></td>
      <td><span class="badge <?= $actief ? 'ok' : 'err' ?>"><?= $kern ? 'altijd actief' : ($actief ? 'actief' : 'uit') ?></span></td>
      <td class="paginastatus-acties">
        <?php if ($kern): ?><span class="hint">kernpagina</span><?php else: ?>
        <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="pagina" value="<?= e($sleutel) ?>"><button class="btn ghost" type="submit"><?= $actief ? 'Zet uit' : 'Zet aan' ?></button></form>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
<?php require __DIR__ . '/_foot.php'; ?>
