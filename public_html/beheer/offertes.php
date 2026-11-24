<?php
require_once __DIR__.'/../_lib/auth.php';
require_once __DIR__.'/../_lib/offertes.php';
auth_boot(); require_login('settings');
header('Cache-Control: private, no-store');
$config=offerte_config(); $error='';
$tab=($_GET['tab']??'')==='tarieven'?'tarieven':'offertes';
if($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if(!csrf_check()) { throw new InvalidArgumentException('Sessie verlopen. Laad de pagina opnieuw.'); }
        $action=offerte_tekst($_POST,'actie');
        if($action==='instellingen') {
            $tab='tarieven';
            $new=offerte_config_opschonen($_POST,$config);
            $lock=offerte_lock();
            try {
                if(!hash_equals(offerte_revisie(offerte_config()),offerte_tekst($_POST,'revisie',64))) { http_response_code(409); throw new InvalidArgumentException('De instellingen zijn ondertussen gewijzigd. Laad deze pagina opnieuw voordat je opslaat.'); }
                if(!save_json('offerte-instellingen.json',$new)) { throw new RuntimeException('Opslaan mislukt.'); }
            } finally { flock($lock,LOCK_UN); fclose($lock); }
            header('Location: offertes.php?tab=tarieven&opgeslagen=1',true,303);exit;
        }
        if(!in_array($action,array('status','verwijderen'),true)) { throw new InvalidArgumentException('Onbekende actie.'); }
        $id=offerte_tekst($_POST,'id');
        if(!offerte_geldige_id($id)) { throw new InvalidArgumentException('Ongeldig offertenummer.'); }
        if(!offerte_record_wijzig($id,$action,offerte_tekst($_POST,'status'))) { throw new RuntimeException('Opslaan mislukt.'); }
        header('Location: offertes.php?opgeslagen=1'.($action==='status'?'&id='.rawurlencode($id):''),true,303);exit;
    } catch(InvalidArgumentException $e) { $error=$e->getMessage(); if(http_response_code()!==409)http_response_code(422); }
      catch(RuntimeException $e) { http_response_code(503);$error='Opslaan is niet gelukt. Probeer het later opnieuw.'; }
}
$record=null;
if(isset($_GET['id'])) { $record=offerte_laad($_GET['id']); if(!$record){http_response_code(404);$error='Deze offerte bestaat niet meer.';} }
$pageTitle='Offertes & tarieven'; require __DIR__.'/_head.php';
?>
<div class="head-row"><h1>Offertes &amp; tarieven</h1><a class="btn ghost" href="../offerte" target="_blank" rel="noopener">Open offerteformulier</a></div>
<p class="lead">Bezoekers berekenen hun prijs en maken direct een voorlopige offerte. Alleen beheerders kunnen hier tarieven en offertes beheren.</p>
<p class="actions"><a class="btn<?= $tab==='offertes'?'':' ghost' ?>" href="offertes.php">Ontvangen offertes</a><a class="btn<?= $tab==='tarieven'?'':' ghost' ?>" href="offertes.php?tab=tarieven">Tarieven &amp; instellingen</a></p>
<?php if($error): ?><p class="msg err" role="alert"><?= e($error) ?></p><?php endif; ?>
<?php if(isset($_GET['opgeslagen'])): ?><p class="msg ok" role="status">Opgeslagen.</p><?php endif; ?>
<?php if($tab==='tarieven'): ?>
<form method="post" action="?tab=tarieven" class="stack">
    <?= csrf_field() ?><input type="hidden" name="actie" value="instellingen"><input type="hidden" name="revisie" value="<?= e(offerte_revisie($config)) ?>">
    <h2 class="groep">Online offertes</h2>
    <label class="field-check"><input type="checkbox" name="actief" value="1"<?= $config['actief']?' checked':'' ?>><span>Bezoekers kunnen zelf offertes aanmaken</span></label>
    <p class="hint">Starttarieven: €25 per m² voor wanden en plafonds en €150 vaste reiskosten, gebaseerd op de aangeleverde factuur. Controleer en wijzig deze bedragen voordat je ze voor alle projecten gebruikt. Alle tarieven hieronder zijn exclusief btw.</p>
    <h2 class="groep">Werkzaamheden &amp; prijzen</h2>
    <div style="overflow-x:auto"><table class="list"><thead><tr><th>Dienst</th><th>Eenheid</th><th>Tarief excl. btw (€)</th><th>Zichtbaar</th><th>Verwijderen</th></tr></thead><tbody>
    <?php $services=$config['diensten'];$services[]=array('id'=>'nieuw','naam'=>'','eenheid'=>'m²','prijs_cent'=>0,'actief'=>true); foreach($services as $service): $prefix='diensten['.$service['id'].']'; ?>
    <tr><td><input aria-label="Dienstnaam" name="<?= e($prefix) ?>[naam]" value="<?= e($service['naam']) ?>" maxlength="80"<?= $service['id']!=='nieuw'?' required':'' ?> placeholder="Nieuwe dienst toevoegen"></td><td><select aria-label="Eenheid" name="<?= e($prefix) ?>[eenheid]"><?php foreach(array('m²','stuk','uur') as $unit): ?><option<?= $unit===$service['eenheid']?' selected':'' ?>><?= e($unit) ?></option><?php endforeach; ?></select></td><td><input aria-label="Tarief exclusief btw" name="<?= e($prefix) ?>[prijs]" type="number" min="0" max="100000" step="0.01" value="<?= e(number_format($service['prijs_cent']/100,2,'.','')) ?>" required></td><td><label><input type="checkbox" name="<?= e($prefix) ?>[actief]" value="1"<?= $service['actief']?' checked':'' ?>> Zichtbaar</label></td><td><?php if($service['id']!=='nieuw'): ?><label><input type="checkbox" name="<?= e($prefix) ?>[verwijderen]" value="1"> Verwijder</label><?php endif; ?></td></tr>
    <?php endforeach; ?></tbody></table></div>
    <p class="hint">Vul de lege rij in om een dienst toe te voegen. Verborgen of verwijderde diensten blijven bewaard in eerder gemaakte offertes.</p>
    <h2 class="groep">Vaste bedragen</h2>
    <label class="field"><span>Reiskosten per offerte, excl. btw (€)</span><input name="reiskosten" type="number" min="0" max="100000" step="0.01" value="<?= e(number_format($config['reiskosten_cent']/100,2,'.','')) ?>" required></label>
    <label class="field"><span>Minimum werkbedrag excl. reiskosten en btw (€)</span><input name="minimum" type="number" min="0" max="100000" step="0.01" value="<?= e(number_format($config['minimum_cent']/100,2,'.','')) ?>" required><small class="hint">Nul betekent geen minimum. Een eventuele aanvulling wordt als aparte offertregel getoond.</small></label>
    <h2 class="groep">Btw &amp; geldigheid</h2>
    <label class="field-check"><input type="checkbox" name="woning_btw" value="1"<?= $config['woning_btw']?' checked':'' ?>><span>9% voor stucwerk aan woningen ouder dan twee jaar, anders 21%</span></label>
    <p class="hint">De bezoeker geeft het type pand aan. Controleer dit bij het opnemen van het werk. De btw-verlegging uit een zakelijke factuur wordt niet automatisch toegepast. <a href="https://www.belastingdienst.nl/wps/wcm/connect/bldcontentnl/belastingdienst/zakelijk/btw/tarieven_en_vrijstellingen/diensten_9_btw/werkzaamheden_aan_woningen/stukadoren" target="_blank" rel="noopener">Btw bij stukadoren volgens de Belastingdienst</a>.</p>
    <label class="field"><span>Vast btw-tarief wanneer bovenstaande keuze uit staat</span><select name="btw_standaard"><?php foreach(array(9,21) as $rate): ?><option value="<?= $rate ?>"<?= $rate===$config['btw_standaard']?' selected':'' ?>><?= $rate ?>%</option><?php endforeach; ?></select></label>
    <label class="field"><span>Geldigheid offerte in dagen</span><input name="geldigheid_dagen" type="number" min="1" max="365" value="<?= (int)$config['geldigheid_dagen'] ?>" required></label>
    <label class="field"><span>Voorwaarden op de offerte</span><textarea name="voorwaarden" rows="6" maxlength="3000" required><?= e($config['voorwaarden']) ?></textarea></label>
    <h2 class="groep">Bedrijfsgegevens op de PDF</h2>
    <?php foreach(array('naam'=>'Bedrijfsnaam','adres'=>'Adres','telefoon'=>'Telefoon','email'=>'Zakelijk e-mailadres','kvk'=>'KvK-nummer','btw_nummer'=>'Btw-nummer') as $key=>$label): ?><label class="field"><span><?= e($label) ?></span><input name="bedrijf[<?= e($key) ?>]" value="<?= e($config['bedrijf'][$key]) ?>" maxlength="200"<?= $key==='naam'?' required':'' ?><?= $key==='email'?' type="email"':'' ?>></label><?php endforeach; ?>
    <p class="hint">Nieuwe tarieven gelden direct voor nieuwe offertes. Bestaande offertes houden hun eigen tarieven, bedragen en voorwaarden. Vorige instellingen staan bij Versies &amp; back-up.</p>
    <div class="actions"><button class="btn" type="submit">Tarieven en instellingen opslaan</button></div>
</form>
<?php elseif($record): $customer=$record['klant'];$calc=$record['berekening']; ?>
    <div class="head-row"><h2><?= e($record['id']) ?></h2><a class="btn" href="../offerte-pdf.php?id=<?= e($record['id']) ?>">Download PDF</a></div>
    <p>Gemaakt: <?= e(date('d-m-Y H:i',strtotime($record['gemaakt_op']))) ?>. Geldig t/m <?= e(date('d-m-Y',strtotime($record['geldig_tot']))) ?>.</p>
    <table class="list"><tbody><?php foreach(array('naam'=>'Naam','email'=>'E-mail','telefoon'=>'Telefoon','adres'=>'Adres','postcode'=>'Postcode','plaats'=>'Plaats','opmerking'=>'Toelichting') as $key=>$label): ?><tr><th scope="row"><?= e($label) ?></th><td><?= nl2br(e($customer[$key])) ?></td></tr><?php endforeach; ?></tbody></table>
    <h2 class="dashboard-group">Berekening bij aanmaken</h2>
    <table class="list"><thead><tr><th>Werkzaamheid</th><th>Aantal</th><th>Tarief excl. btw</th><th>Bedrag excl. btw</th></tr></thead><tbody><?php foreach($calc['regels'] as $row): ?><tr><td><?= e($row['naam']) ?></td><td><?= e(offerte_aantal($row['aantal_honderdste']).' '.$row['eenheid']) ?></td><td><?= e(offerte_euro($row['prijs_cent'])) ?></td><td><?= e(offerte_euro($row['totaal_cent'])) ?></td></tr><?php endforeach; ?></tbody></table>
    <p>Subtotaal: <?= e(offerte_euro($calc['subtotaal_cent'])) ?>. Btw (<?= (int)$calc['btw_percentage'] ?>%): <?= e(offerte_euro($calc['btw_cent'])) ?>.</p><p><strong>Totaal incl. btw: <?= e(offerte_euro($calc['totaal_cent'])) ?></strong></p>
    <p><?= nl2br(e($record['instellingen']['voorwaarden'])) ?></p>
    <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="actie" value="status"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><label class="field"><span>Status</span><select name="status"><?php foreach(offerte_statussen() as $key=>$label): ?><option value="<?= e($key) ?>"<?= $record['status']===$key?' selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label><div class="actions"><button class="btn">Status opslaan</button><a class="btn ghost" href="offertes.php">Terug naar overzicht</a></div></form>
    <form method="post" data-confirm="Deze offerte met persoonsgegevens definitief verwijderen?"><?= csrf_field() ?><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="id" value="<?= e($record['id']) ?>"><button class="btn btn-danger">Offerte verwijderen</button></form>
<?php else: $records=offerte_lijst(); ?>
    <p class="hint">Offertes blijven maximaal 90 dagen beschikbaar. Alleen de aanvrager in dezelfde browsersessie en de beheerder kunnen de PDF openen. Download offertes die je langer wilt bewaren.</p>
    <?php if(!$records): ?><div class="info-blok"><p>Er zijn nog geen offertes aangemaakt.</p></div><?php else: ?>
    <table class="list"><thead><tr><th>Nummer</th><th>Aangemaakt</th><th>Klant</th><th>Totaal incl. btw</th><th>Status</th><th></th></tr></thead><tbody><?php foreach($records as $row): ?><tr><td><?= e($row['id']) ?></td><td><?= e(date('d-m-Y H:i',strtotime($row['gemaakt_op']))) ?></td><td><?= e($row['klant']['naam']) ?><br><small><?= e($row['klant']['plaats']) ?></small></td><td><?= e(offerte_euro($row['berekening']['totaal_cent'])) ?></td><td><?= e(offerte_statussen()[$row['status']]??$row['status']) ?></td><td><a class="btn mini" href="?id=<?= e($row['id']) ?>">Openen</a></td></tr><?php endforeach; ?></tbody></table>
    <?php endif; ?>
<?php endif; ?>
<?php require __DIR__.'/_foot.php'; ?>
