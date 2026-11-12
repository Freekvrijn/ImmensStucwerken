<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/cms.php';
auth_boot();
require_login('settings');
if (!cms_module_aan('redirects')) { header('Location: index.php'); exit; }

function redirect_index($items, $id) { foreach ($items as $i => $item) { if (($item['id'] ?? '') === $id) { return $i; } } return -1; }
$items = load_json('redirects.json', array());
$actie = (string) ($_GET['actie'] ?? 'lijst');
$item = null; $msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { $msg = 'Sessie verlopen, probeer het opnieuw.'; }
    else {
        $postActie = (string) ($_POST['actie'] ?? ''); $id = (string) ($_POST['id'] ?? ''); $index = redirect_index($items, $id);
        if ($postActie === 'verwijderen' && $index >= 0) { array_splice($items, $index, 1); save_json('redirects.json', array_values($items)); header('Location: redirects.php?verwijderd=1'); exit; }
        if ($postActie === 'opslaan') {
            $van = '/' . trim((string) ($_POST['van'] ?? ''), " /\t\n\r\0\x0B");
            $naar = cms_url_opschonen((string) ($_POST['naar'] ?? ''));
            $code = (int) ($_POST['code'] ?? 301); if (!in_array($code, array(301,302,307,308), true)) { $code = 301; }
            $dubbel = false; foreach ($items as $i => $rij) { if ($i !== $index && ('/' . ltrim((string) ($rij['van'] ?? ''), '/')) === $van) { $dubbel = true; } }
            if ($van === '/' || $naar === '' || $dubbel) { $msg = $dubbel ? 'Dit oude pad heeft al een redirect.' : 'Vul een oud pad en geldige bestemming in.'; $actie = 'form'; }
            else {
                $nieuw = array('id'=>$id!==''?$id:cms_nieuwe_id('redir'),'van'=>$van,'naar'=>$naar,'code'=>$code,'actief'=>!empty($_POST['actief']));
                if ($index >= 0) { $items[$index]=$nieuw; } else { $items[]=$nieuw; }
                save_json('redirects.json', array_values($items)); header('Location: redirects.php?opgeslagen=1'); exit;
            }
        }
    }
}
if ($actie === 'nieuw') { $item=array('id'=>'','van'=>'','naar'=>'','code'=>301,'actief'=>true); $actie='form'; }
elseif ($actie === 'bewerk') { $index=redirect_index($items,(string)($_GET['id']??'')); if($index<0){header('Location: redirects.php');exit;} $item=$items[$index];$actie='form'; }
$pageTitle='Redirects'; require __DIR__.'/_head.php';
if(isset($_GET['opgeslagen']))echo '<p class="msg ok">Redirect opgeslagen.</p>'; if(isset($_GET['verwijderd']))echo '<p class="msg ok">Redirect verwijderd.</p>'; if($msg)echo '<p class="msg err">'.e($msg).'</p>';
if($actie==='form'):
?>
<div class="head-row"><h1><?= empty($item['id'])?'Redirect toevoegen':'Redirect bewerken' ?></h1><a class="btn ghost" href="redirects.php">Terug</a></div>
<form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="actie" value="opslaan"><input type="hidden" name="id" value="<?= e($item['id']??'') ?>">
<label class="field"><span>Oud pad</span><input type="text" name="van" value="<?= e($item['van']??'') ?>" placeholder="/oude-pagina" required></label>
<label class="field"><span>Nieuwe bestemming</span><input type="text" name="naar" value="<?= e($item['naar']??'') ?>" placeholder="nieuwe-pagina of https://..." required></label>
<label class="field"><span>HTTP-status</span><select name="code"><?php foreach(array(301=>'301 · permanent',302=>'302 · tijdelijk',307=>'307 · tijdelijk, methode behouden',308=>'308 · permanent, methode behouden') as $code=>$label):?><option value="<?= $code ?>"<?= (int)($item['code']??301)===$code?' selected':'' ?>><?= e($label) ?></option><?php endforeach;?></select></label>
<label class="field-check"><input type="checkbox" name="actief" value="1"<?= !empty($item['actief'])?' checked':'' ?>><span>Redirect actief</span></label><div class="actions"><button class="btn">Opslaan</button><a class="btn ghost" href="redirects.php">Annuleren</a></div></form>
<?php else: ?>
<div class="head-row"><h1>Redirects</h1><a class="btn" href="?actie=nieuw">+ Redirect</a></div><p class="lead">Deze regels werken zodra <code>cms_public_boot()</code> vóór alle uitvoer wordt aangeroepen.</p>
<?php if(!$items):?><div class="info-blok"><p>Er zijn nog geen redirects.</p></div><?php else:?><table class="list"><thead><tr><th>Van</th><th>Naar</th><th>Code</th><th>Status</th><th class="right">Acties</th></tr></thead><tbody><?php foreach($items as $rij):?><tr><td><code><?= e($rij['van']??'') ?></code></td><td><?= e($rij['naar']??'') ?></td><td><?= (int)($rij['code']??301) ?></td><td><span class="badge <?= !empty($rij['actief'])?'ok':'err' ?>"><?= !empty($rij['actief'])?'actief':'uit' ?></span></td><td class="acties right"><a href="?actie=bewerk&amp;id=<?= e(urlencode($rij['id'])) ?>">Bewerken</a><form method="post" data-confirm="Deze redirect verwijderen?"><?= csrf_field() ?><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="id" value="<?= e($rij['id']) ?>"><button class="link-del">Verwijderen</button></form></td></tr><?php endforeach;?></tbody></table><?php endif; endif; require __DIR__.'/_foot.php';?>

