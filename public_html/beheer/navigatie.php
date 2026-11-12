<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/cms.php';
auth_boot();
require_login('content');
if (!cms_module_aan('navigatie')) { header('Location: index.php'); exit; }

function navigatie_index($items, $id) {
    foreach ($items as $index => $item) { if (($item['id'] ?? '') === $id) { return $index; } }
    return -1;
}

$items = load_json('navigatie.json', array());
$actie = (string) ($_GET['actie'] ?? 'lijst');
$item = null;
$msg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { $msg = 'Sessie verlopen, probeer het opnieuw.'; }
    else {
        $postActie = (string) ($_POST['actie'] ?? '');
        $id = (string) ($_POST['id'] ?? '');
        $index = navigatie_index($items, $id);
        if ($postActie === 'verwijderen' && $index >= 0) {
            array_splice($items, $index, 1);
            save_json('navigatie.json', array_values($items));
            header('Location: navigatie.php?verwijderd=1'); exit;
        }
        if ($postActie === 'verplaatsen' && $index >= 0) {
            $richting = (string) ($_POST['richting'] ?? '');
            $doel = $richting === 'omhoog' ? $index - 1 : $index + 1;
            if (isset($items[$doel])) { $tmp = $items[$index]; $items[$index] = $items[$doel]; $items[$doel] = $tmp; }
            foreach ($items as $positie => &$rij) { $rij['volgorde'] = ($positie + 1) * 10; } unset($rij);
            save_json('navigatie.json', array_values($items));
            header('Location: navigatie.php'); exit;
        }
        if ($postActie === 'opslaan') {
            $label = trim((string) ($_POST['label'] ?? ''));
            $url = cms_url_opschonen((string) ($_POST['url'] ?? ''));
            $locatie = in_array(($_POST['locatie'] ?? ''), array('hoofd', 'footer'), true) ? (string) $_POST['locatie'] : 'hoofd';
            if ($label === '' || $url === '') { $msg = 'Vul een label en geldige bestemming in.'; $actie = 'form'; }
            else {
                $nieuw = array(
                    'id' => $id !== '' ? $id : cms_nieuwe_id('nav'),
                    'label' => $label, 'url' => $url, 'locatie' => $locatie,
                    'nieuw_venster' => !empty($_POST['nieuw_venster']),
                    'actief' => !empty($_POST['actief']),
                    'volgorde' => $index >= 0 ? (int) ($items[$index]['volgorde'] ?? 0) : (count($items) + 1) * 10,
                );
                if ($index >= 0) { $items[$index] = $nieuw; } else { $items[] = $nieuw; }
                save_json('navigatie.json', array_values($items));
                header('Location: navigatie.php?opgeslagen=1'); exit;
            }
        }
    }
}

if ($actie === 'nieuw') { $item = array('id'=>'','label'=>'','url'=>'','locatie'=>'hoofd','nieuw_venster'=>false,'actief'=>true); $actie = 'form'; }
elseif ($actie === 'bewerk') {
    $index = navigatie_index($items, (string) ($_GET['id'] ?? ''));
    if ($index < 0) { header('Location: navigatie.php'); exit; }
    $item = $items[$index]; $actie = 'form';
}

$pageTitle = 'Navigatie';
require __DIR__ . '/_head.php';
if (isset($_GET['opgeslagen'])) { echo '<p class="msg ok">Menu-item opgeslagen.</p>'; }
if (isset($_GET['verwijderd'])) { echo '<p class="msg ok">Menu-item verwijderd.</p>'; }
if ($msg) { echo '<p class="msg err">' . e($msg) . '</p>'; }
if ($actie === 'form'):
?>
<div class="head-row"><h1><?= empty($item['id']) ? 'Menu-item toevoegen' : 'Menu-item bewerken' ?></h1><a class="btn ghost" href="navigatie.php">Terug</a></div>
<form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="actie" value="opslaan"><input type="hidden" name="id" value="<?= e($item['id'] ?? '') ?>">
  <label class="field"><span>Label</span><input type="text" name="label" value="<?= e($item['label'] ?? '') ?>" required></label>
  <label class="field"><span>Bestemming</span><input type="text" name="url" value="<?= e($item['url'] ?? '') ?>" placeholder="contact of https://voorbeeld.nl" required><small class="hint">Een relatief pad, #anker of volledige https-link.</small></label>
  <label class="field"><span>Locatie</span><select name="locatie"><option value="hoofd"<?= ($item['locatie'] ?? '') === 'hoofd' ? ' selected' : '' ?>>Hoofdmenu</option><option value="footer"<?= ($item['locatie'] ?? '') === 'footer' ? ' selected' : '' ?>>Footer</option></select></label>
  <label class="field-check"><input type="checkbox" name="actief" value="1"<?= !empty($item['actief']) ? ' checked' : '' ?>><span>Zichtbaar</span></label>
  <label class="field-check"><input type="checkbox" name="nieuw_venster" value="1"<?= !empty($item['nieuw_venster']) ? ' checked' : '' ?>><span>Open in een nieuw venster</span></label>
  <div class="actions"><button class="btn" type="submit">Opslaan</button><a class="btn ghost" href="navigatie.php">Annuleren</a></div>
</form>
<?php else: ?>
<div class="head-row"><h1>Navigatie</h1><a class="btn" href="navigatie.php?actie=nieuw">+ Menu-item</a></div>
<p class="lead">De openbare site leest deze items met <code>cms_navigatie('hoofd')</code> of <code>cms_navigatie('footer')</code>.</p>
<?php if (!$items): ?><div class="info-blok"><p>Er zijn nog geen menu-items. Dit is bewust leeg totdat het CMS aan een site wordt gekoppeld.</p></div><?php else: ?>
<table class="list"><thead><tr><th>Label</th><th>Bestemming</th><th>Locatie</th><th>Status</th><th class="right">Acties</th></tr></thead><tbody>
<?php foreach ($items as $positie => $rij): ?>
<tr><td><strong><?= e($rij['label'] ?? '') ?></strong></td><td><code><?= e($rij['url'] ?? '') ?></code></td><td><?= ($rij['locatie'] ?? '') === 'footer' ? 'Footer' : 'Hoofdmenu' ?></td><td><span class="badge <?= !empty($rij['actief']) ? 'ok' : 'err' ?>"><?= !empty($rij['actief']) ? 'zichtbaar' : 'verborgen' ?></span></td>
<td class="acties right"><form method="post"><?= csrf_field() ?><input type="hidden" name="actie" value="verplaatsen"><input type="hidden" name="id" value="<?= e($rij['id']) ?>"><button class="volgorde-knop" name="richting" value="omhoog"<?= $positie === 0 ? ' disabled' : '' ?>>↑</button><button class="volgorde-knop" name="richting" value="omlaag"<?= $positie === count($items)-1 ? ' disabled' : '' ?>>↓</button></form><a href="?actie=bewerk&amp;id=<?= e(urlencode($rij['id'])) ?>">Bewerken</a><form method="post" data-confirm="Dit menu-item verwijderen?"><?= csrf_field() ?><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="id" value="<?= e($rij['id']) ?>"><button class="link-del" type="submit">Verwijderen</button></form></td></tr>
<?php endforeach; ?></tbody></table>
<?php endif; endif; require __DIR__ . '/_foot.php'; ?>

