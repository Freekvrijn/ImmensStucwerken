<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/versies.php';
require_once __DIR__ . '/../_lib/cms.php';
auth_boot();require_login('backup');
$bestanden=versiebare_bestanden();$msg='';
if(($_GET['actie']??'')==='download'){
    $backup=array('gemaakt_op'=>date('c'),'site'=>cms_site_naam(),'formaat'=>'cms-template-v1','inhoud'=>array());
    foreach($bestanden as $bestand=>$label){$backup['inhoud'][$bestand]=load_json($bestand,array());}
    $slug=strtolower((string)preg_replace('/[^a-z0-9]+/i','-',cms_site_naam()));$slug=trim($slug,'-');if($slug==='')$slug='site';
    header('Content-Type: application/json; charset=UTF-8');header('Content-Disposition: attachment; filename="'.$slug.'-backup-'.date('Ymd-His').'.json"');echo json_encode($backup,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'){
if(!csrf_check()){$msg='Sessie verlopen, probeer het opnieuw.';}elseif(($_POST['actie']??'')==='terugzetten'){$naam=(string)($_POST['naam']??'');$bestand=(string)($_POST['bestand']??'');$data=versie_lees($naam,$bestand);if($data===null){$msg='Deze versie kon niet worden gelezen.';}elseif(save_json($naam,$data)){header('Location: versies.php?hersteld='.urlencode($bestanden[$naam]??$naam));exit;}else{$msg='Terugzetten mislukte.';}}
}
$bekijk=null;if(($_GET['actie']??'')==='bekijk'){$naam=(string)($_GET['naam']??'');$bestand=(string)($_GET['bestand']??'');$data=versie_lees($naam,$bestand);if($data!==null)$bekijk=array('naam'=>$naam,'bestand'=>$bestand,'data'=>$data);else$msg='Deze versie kon niet worden gelezen.';}
function cms_versie_tijd($ts){return nl_datum(date('Y-m-d',$ts)).' om '.date('H:i',$ts);}
$pageTitle='Versies & back-up';require __DIR__.'/_head.php';if(isset($_GET['hersteld']))echo '<p class="msg ok">'.e($_GET['hersteld']).' is teruggezet.</p>';if($msg)echo '<p class="msg err">'.e($msg).'</p>';
if($bekijk):?>
<div class="head-row"><h1>Versie bekijken: <?= e($bestanden[$bekijk['naam']]??$bekijk['naam']) ?></h1><a class="btn ghost" href="versies.php">Terug</a></div><p class="lead">Technische JSON-weergave van deze herstelversie.</p><pre class="info-blok" style="white-space:pre-wrap;overflow:auto"><?= e(json_encode($bekijk['data'],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES)) ?></pre><form method="post" data-confirm="Deze versie terugzetten? De huidige inhoud wordt eerst bewaard."><?= csrf_field() ?><input type="hidden" name="actie" value="terugzetten"><input type="hidden" name="naam" value="<?= e($bekijk['naam']) ?>"><input type="hidden" name="bestand" value="<?= e($bekijk['bestand']) ?>"><div class="actions"><button class="btn">Deze versie terugzetten</button><a class="btn ghost" href="versies.php">Annuleren</a></div></form>
<?php else:?>
<div class="head-row"><h1>Versies &amp; back-up</h1><a class="btn ghost" href="?actie=download">↓ Back-up downloaden</a></div><p class="lead">Voor iedere inhoudelijke wijziging bewaren we automatisch de vorige staat. De laatste <?= VERSIES_MAX ?> versies per onderdeel blijven staan.</p>
<?php foreach($bestanden as $bestand=>$label):$lijst=versies_lijst($bestand);?><h2 class="dashboard-group"><?= e($label) ?></h2><?php if(!$lijst):?><p class="hint">Nog geen eerdere versies.</p><?php else:?><table class="list"><thead><tr><th>Van vóór de wijziging op</th><th class="right">Acties</th></tr></thead><tbody><?php foreach($lijst as $versie):?><tr><td><?= e(cms_versie_tijd($versie['tijd'])) ?></td><td class="acties right"><a href="?actie=bekijk&amp;naam=<?= e(urlencode($bestand)) ?>&amp;bestand=<?= e(urlencode($versie['bestand'])) ?>">Bekijken</a><form method="post" data-confirm="Deze versie terugzetten? De huidige inhoud wordt eerst bewaard."><?= csrf_field() ?><input type="hidden" name="actie" value="terugzetten"><input type="hidden" name="naam" value="<?= e($bestand) ?>"><input type="hidden" name="bestand" value="<?= e($versie['bestand']) ?>"><button class="link-terug">Terugzetten</button></form></td></tr><?php endforeach;?></tbody></table><?php endif;endforeach;endif;require __DIR__.'/_foot.php';?>

