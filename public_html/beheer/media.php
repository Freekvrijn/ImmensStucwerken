<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/cms.php';
auth_boot();
require_login('media');
if (!cms_module_aan('media')) { header('Location: index.php'); exit; }

function media_wordt_gebruikt($pad) {
    foreach (array('content.json','seo.json','collectie.json','navigatie.json','instellingen.json') as $bestand) {
        $ruw = @file_get_contents(data_path($bestand));
        if ($ruw !== false && strpos($ruw, '"' . $pad . '"') !== false) { return $bestand; }
    }
    return '';
}

$metadata = load_json('media.json', array());
$msg = ''; $ok = false;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { $msg='Sessie verlopen, probeer het opnieuw.'; }
    else {
        $actie=(string)($_POST['actie']??'');
        if($actie==='upload'){
            $up=verwerk_upload($_FILES['bestand']??null);
            if(!$up['ok']){$msg=$up['fout'];}
            elseif($up['pad']===''){$msg='Kies een afbeelding.';}
            else{$metadata[$up['pad']]=array('alt'=>trim((string)($_POST['alt']??'')),'titel'=>trim((string)($_POST['titel']??'')),'geupload_op'=>date('c'));save_json('media.json',$metadata);$ok=true;$msg='Afbeelding geüpload.';}
        }
        if($actie==='metadata'){
            $pad=(string)($_POST['pad']??'');$beelden=beschikbare_afbeeldingen();
            if(!isset($beelden[$pad])||$pad===''){$msg='Onbekende afbeelding.';}
            else{$metadata[$pad]=array_merge($metadata[$pad]??array(),array('alt'=>trim((string)($_POST['alt']??'')),'titel'=>trim((string)($_POST['titel']??''))));save_json('media.json',$metadata);$ok=true;$msg='Beschrijving opgeslagen.';}
        }
        if($actie==='verwijderen'){
            $pad=(string)($_POST['pad']??'');$prefix=rtrim(CMS_UPLOAD_DIR,'/').'/';$beelden=beschikbare_afbeeldingen();$gebruikt=media_wordt_gebruikt($pad);
            if($pad===''||strpos($pad,$prefix)!==0||!isset($beelden[$pad])){$msg='Alleen bestanden uit de uploadmap kunnen hier worden verwijderd.';}
            elseif($gebruikt!==''){$msg='Deze afbeelding wordt nog gebruikt in '.$gebruikt.'. Vervang haar daar eerst.';}
            else{$volledig=dirname(__DIR__).'/'.$pad;if(is_file($volledig)&&@unlink($volledig)){unset($metadata[$pad]);save_json('media.json',$metadata);$ok=true;$msg='Afbeelding verwijderd.';}else{$msg='Verwijderen mislukte.';}}
        }
    }
}
$beelden=beschikbare_afbeeldingen();unset($beelden['']);
$pageTitle='Media';require __DIR__.'/_head.php';
?>
<div class="head-row"><h1>Media</h1></div><p class="lead">Centrale beeldbank. Uploads worden gecontroleerd, veilig hernoemd en waar mogelijk verkleind tot maximaal <?= (int)CMS_UPLOAD_MAX_AFMETING ?> pixels.</p>
<?php if($msg):?><p class="msg <?= $ok?'ok':'err' ?>"><?= e($msg) ?></p><?php endif;?>
<form method="post" enctype="multipart/form-data" class="stack"><?= csrf_field() ?><input type="hidden" name="actie" value="upload"><h2 class="groep">Afbeelding uploaden</h2>
<label class="field"><span>Bestand</span><input type="file" name="bestand" accept="image/jpeg,image/png,image/gif,image/webp" required><small class="hint">JPG, PNG, GIF of WEBP, max. <?= (int)CMS_UPLOAD_MAX_MB ?> MB.</small></label>
<label class="field"><span>Alternatieve tekst</span><input type="text" name="alt"><small class="hint">Beschrijf wat relevant is voor bezoekers die de afbeelding niet kunnen zien.</small></label>
<label class="field"><span>Interne titel</span><input type="text" name="titel"></label><div class="actions"><button class="btn">Uploaden</button></div></form>

<h2 class="dashboard-group">Beeldbank</h2>
<?php if(!$beelden):?><div class="info-blok"><p>Er zijn nog geen afbeeldingen.</p></div><?php else:?><div class="cards">
<?php foreach($beelden as $pad=>$label):$meta=$metadata[$pad]??array();$volledig=dirname(__DIR__).'/'.$pad;$info=@getimagesize($volledig);?>
<article class="card"><a href="../<?= e($pad) ?>" target="_blank" rel="noopener"><img class="preview" src="../<?= e($pad) ?>" alt="<?= e($meta['alt']??'') ?>"></a><h2><?= e($meta['titel']??$label) ?></h2><p class="hint"><code><?= e($pad) ?></code><?= $info?' · '.(int)$info[0].'×'.(int)$info[1]:'' ?></p>
<form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="actie" value="metadata"><input type="hidden" name="pad" value="<?= e($pad) ?>"><label class="field"><span>Alt-tekst</span><input type="text" name="alt" value="<?= e($meta['alt']??'') ?>"></label><label class="field"><span>Interne titel</span><input type="text" name="titel" value="<?= e($meta['titel']??'') ?>"></label><button class="btn ghost">Beschrijving opslaan</button></form>
<?php if(strpos($pad,rtrim(CMS_UPLOAD_DIR,'/').'/')===0):?><form method="post" data-confirm="Deze afbeelding definitief verwijderen? Dit kan alleen als zij nergens meer wordt gebruikt."><?= csrf_field() ?><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="pad" value="<?= e($pad) ?>"><button class="link-del">Verwijderen</button></form><?php endif;?></article>
<?php endforeach;?></div><?php endif;require __DIR__.'/_foot.php';?>

