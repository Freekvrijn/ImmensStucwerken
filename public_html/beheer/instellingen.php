<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/cms.php';
auth_boot();
require_login('settings');

$standaard = array(
    'site_naam'=>CMS_SITE_NAME, 'site_url'=>CMS_SITE_URL, 'bedrijfsnaam'=>'',
    'email'=>'', 'telefoon'=>'', 'adres'=>'', 'facebook'=>'', 'instagram'=>'', 'linkedin'=>'',
);
$waarden = array_merge($standaard, load_json('instellingen.json', array()));
$msg = ''; $ok = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) { $msg = 'Sessie verlopen, probeer het opnieuw.'; }
    else {
        $siteUrlRuw = trim((string) ($_POST['site_url'] ?? ''));
        $siteUrl = $siteUrlRuw === '' ? '' : cms_url_opschonen($siteUrlRuw, false);
        $email = trim((string) ($_POST['email'] ?? ''));
        $socials = array();
        $socialOngeldig = false;
        foreach (array('facebook','instagram','linkedin') as $sociaal) {
            $ruw = trim((string) ($_POST[$sociaal] ?? ''));
            $socials[$sociaal] = $ruw === '' ? '' : cms_url_opschonen($ruw, false);
            if ($ruw !== '' && ($socials[$sociaal] === '' || !preg_match('~^https?://~i', $socials[$sociaal]))) { $socialOngeldig = true; }
        }
        if (trim((string) ($_POST['site_naam'] ?? '')) === '') { $msg = 'Vul een sitenaam in.'; }
        elseif ($siteUrlRuw !== '' && ($siteUrl === '' || !preg_match('~^https?://~i', $siteUrl))) { $msg = 'Vul een volledige geldige site-URL in.'; }
        elseif ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) { $msg = 'Het e-mailadres is ongeldig.'; }
        elseif ($socialOngeldig) { $msg = 'Een sociale link is ongeldig.'; }
        else {
            $waarden = array(
                'site_naam'=>trim((string)$_POST['site_naam']), 'site_url'=>$siteUrl,
                'bedrijfsnaam'=>trim((string)($_POST['bedrijfsnaam']??'')), 'email'=>$email,
                'telefoon'=>trim((string)($_POST['telefoon']??'')), 'adres'=>trim((string)($_POST['adres']??'')),
                'facebook'=>$socials['facebook'], 'instagram'=>$socials['instagram'], 'linkedin'=>$socials['linkedin'],
            );
            if (save_json('instellingen.json',$waarden)) { $ok=true;$msg='Instellingen opgeslagen.'; }
            else { $msg='Opslaan mislukte. Is de map data/ schrijfbaar?'; }
        }
    }
}
$pageTitle='Site-instellingen'; require __DIR__.'/_head.php';
?>
<h1>Site-instellingen</h1><p class="lead">Gegevens die op meerdere plekken in een toekomstige website kunnen worden gebruikt.</p>
<?php if($msg):?><p class="msg <?= $ok?'ok':'err' ?>"><?= e($msg) ?></p><?php endif;?>
<form method="post" class="stack"><?= csrf_field() ?>
<h2 class="groep">Website</h2>
<label class="field"><span>Sitenaam</span><input type="text" name="site_naam" value="<?= e($waarden['site_naam']) ?>" required></label>
<label class="field"><span>Definitieve site-URL</span><input type="url" name="site_url" value="<?= e($waarden['site_url']) ?>" placeholder="https://www.voorbeeld.nl/"><small class="hint">Mag leeg blijven zolang het CMS nog niet is gekoppeld.</small></label>
<h2 class="groep">Organisatie</h2>
<label class="field"><span>Bedrijfsnaam</span><input type="text" name="bedrijfsnaam" value="<?= e($waarden['bedrijfsnaam']) ?>"></label>
<label class="field"><span>E-mailadres</span><input type="email" name="email" value="<?= e($waarden['email']) ?>"></label>
<label class="field"><span>Telefoonnummer</span><input type="text" name="telefoon" value="<?= e($waarden['telefoon']) ?>"></label>
<label class="field"><span>Adres</span><textarea name="adres" rows="3"><?= e($waarden['adres']) ?></textarea></label>
<h2 class="groep">Social media</h2>
<?php foreach(array('facebook'=>'Facebook','instagram'=>'Instagram','linkedin'=>'LinkedIn') as $key=>$label):?><label class="field"><span><?= e($label) ?></span><input type="url" name="<?= e($key) ?>" value="<?= e($waarden[$key]) ?>" placeholder="https://..."></label><?php endforeach;?>
<div class="actions"><button class="btn" type="submit">Opslaan</button></div></form>
<?php require __DIR__.'/_foot.php';?>
