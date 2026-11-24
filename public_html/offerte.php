<?php
require_once __DIR__ . '/_lib/site.php';
require_once __DIR__ . '/_lib/offertes.php';
$thema='blauwdruk'; $content=cms_content();
cms_public_boot('offerte');
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
$config=offerte_config(); $error=''; $record=null;
if (isset($_GET['id'])) {
    $record=offerte_laad($_GET['id']);
    if (!$record || !offerte_toegang($record)) { require __DIR__.'/404.php'; exit; }
}
if ($_SERVER['REQUEST_METHOD']==='POST') {
    try {
        if (!csrf_check()) { throw new InvalidArgumentException('Je sessie is verlopen. Controleer je invoer en probeer het opnieuw.'); }
        if (offerte_tekst($_POST,'website')!=='') { throw new InvalidArgumentException('Ongeldige inzending.'); }
        $nonce=offerte_tekst($_POST,'offerte_nonce',64);
        if (isset($_SESSION['offerte_ingediend'][$nonce])) {
            header('Location: /offerte?id='.rawurlencode($_SESSION['offerte_ingediend'][$nonce]),true,303); exit;
        }
        if (!preg_match('/^[a-f0-9]{32}$/D',$nonce) || !isset($_SESSION['offerte_nonces'][$nonce])) { throw new InvalidArgumentException('Laad het offerteformulier opnieuw en probeer het nogmaals.'); }
        if (!hash_equals(offerte_revisie($config),offerte_tekst($_POST,'revisie',64))) { http_response_code(409); throw new InvalidArgumentException('De tarieven zijn bijgewerkt. Controleer de nieuwe berekening hieronder en maak je offerte opnieuw aan.'); }
        $calculation=offerte_bereken($config,$_POST);
        $customer=offerte_klant($_POST);
        $record=offerte_aanmaken($config,$calculation,$customer);
        $_SESSION['offerte_ingediend'][$nonce]=$record['id'];
        $_SESSION['offerte_ingediend']=array_slice($_SESSION['offerte_ingediend'],-20,null,true);
        unset($_SESSION['offerte_nonces'][$nonce]);
        header('Location: /offerte?id='.rawurlencode($record['id']),true,303); exit;
    } catch (InvalidArgumentException $e) { $error=$e->getMessage(); if(http_response_code()!==409){http_response_code(422);} }
      catch (RuntimeException $e) { http_response_code(503); $error='De offerte kon niet worden aangemaakt. Probeer het later opnieuw of neem rechtstreeks contact op.'; }
}
$nonce=bin2hex(random_bytes(16));
$_SESSION['offerte_nonces'][$nonce]=time();
$_SESSION['offerte_nonces']=array_slice($_SESSION['offerte_nonces']??array(),-10,null,true);
function offerte_invoer($key) { return e(offerte_tekst($_POST,$key,2000)); }
documentkop('offerte','Zelf je offerte maken | Immens Stucwerken','Bereken direct de prijs van je stucwerk en download je voorlopige offerte.');
sitekop();
?>
<main class="breed sectie offerte-pagina" id="inhoud">
<?php if($record): $calc=$record['berekening']; ?>
    <div class="offerte-aanhef"><p class="vakregel">Jouw stucwerk</p><h1>Je offerte staat klaar.</h1><p>Download de offerte en bewaar deze voor het bespreken van je project. Je hoeft hiervoor geen account aan te maken.</p></div>
    <section class="offerte-document" aria-labelledby="offertetitel">
        <div class="offerte-documentkop"><div><h2 id="offertetitel">Voorlopige offerte</h2><p><?= e($record['id']) ?><br>Opgemaakt op <?= e(date('d-m-Y',strtotime($record['gemaakt_op']))) ?>, geldig t/m <?= e(date('d-m-Y',strtotime($record['geldig_tot']))) ?></p></div><strong><?= e($record['instellingen']['bedrijf']['naam']) ?></strong></div>
        <p><strong><?= e($record['klant']['naam']) ?></strong><br><?= e($record['klant']['adres']) ?><br><?= e($record['klant']['postcode'].' '.$record['klant']['plaats']) ?><br><?= e($record['klant']['email']) ?></p>
        <div class="offerte-tabel-scroll"><table class="offerte-tabel"><thead><tr><th>Werkzaamheden</th><th>Aantal</th><th>Tarief excl. btw</th><th>Bedrag excl. btw</th></tr></thead><tbody>
        <?php foreach($calc['regels'] as $row): ?><tr><td><?= e($row['naam']) ?></td><td><?= e(offerte_aantal($row['aantal_honderdste']).' '.$row['eenheid']) ?></td><td><?= e(offerte_euro($row['prijs_cent'])) ?></td><td><?= e(offerte_euro($row['totaal_cent'])) ?></td></tr><?php endforeach; ?>
        </tbody></table></div>
        <dl class="offerte-totalen"><div><dt>Subtotaal excl. btw</dt><dd><?= e(offerte_euro($calc['subtotaal_cent'])) ?></dd></div><div><dt>Btw (<?= (int)$calc['btw_percentage'] ?>%)</dt><dd><?= e(offerte_euro($calc['btw_cent'])) ?></dd></div><div class="offerte-eindtotaal"><dt>Totaal incl. btw</dt><dd><?= e(offerte_euro($calc['totaal_cent'])) ?></dd></div></dl>
        <h3>Voorwaarden</h3><p class="offerte-voorwaarden"><?= nl2br(e($record['instellingen']['voorwaarden'])) ?></p>
        <?php if($record['klant']['opmerking']!==''): ?><h3>Jouw toelichting</h3><p><?= nl2br(e($record['klant']['opmerking'])) ?></p><?php endif; ?>
        <div class="offerte-acties"><a class="knop" href="/offerte-pdf.php?id=<?= e($record['id']) ?>">Download offerte als PDF</a><a class="knop" href="/offerte">Nieuwe offerte maken</a></div>
        <?php $business=$record['instellingen']['bedrijf']; if($business['email']!==''): ?><p>Je project bespreken? Mail <a href="mailto:<?= e($business['email']) ?>"><?= e($business['email']) ?></a> en vermeld <?= e($record['id']) ?>.</p><?php endif; ?>
    </section>
<?php elseif(empty($config['actief'])): ?>
    <div class="offerte-aanhef"><h1>Even persoonlijk afstemmen.</h1><p>Online offertes zijn tijdelijk uitgeschakeld. Vertel ons over je project, dan kijken we samen naar de mogelijkheden.</p><a class="knop" href="/#contact">Bespreek je project</a></div>
<?php else: ?>
    <div class="offerte-aanhef"><p class="vakregel">Van plan naar prijs</p><h1 data-veld="offerte_titel"><?= tekst('offerte_titel') ?></h1><p data-veld="offerte_intro"><?= tekst('offerte_intro') ?></p></div>
    <?php if($error!==''): ?><div class="offerte-fout" role="alert" tabindex="-1"><?= e($error) ?></div><?php endif; ?>
    <form method="post" action="/offerte" class="offerte-formulier" id="offerteformulier">
        <?= csrf_field() ?><input type="hidden" name="offerte_nonce" value="<?= e($nonce) ?>"><input type="hidden" name="revisie" value="<?= e(offerte_revisie($config)) ?>">
        <div class="offerte-layout"><div class="offerte-velden">
            <fieldset><legend>Wat wil je laten stucen?</legend><p class="offerte-hulp">Vul de netto oppervlakte in, zonder ramen en deuren. Laat werkzaamheden die je niet nodig hebt op nul staan.</p>
                <?php foreach($config['diensten'] as $service): if(empty($service['actief']))continue; $values=is_array($_POST['aantallen']??null)?$_POST['aantallen']:array(); $value=$values[$service['id']]??'0'; if(!is_string($value))$value='0'; ?>
                <label class="offerte-dienst"><span><strong><?= e($service['naam']) ?></strong><small><?= e(offerte_euro($service['prijs_cent'])) ?> per <?= e($service['eenheid']) ?> excl. btw</small></span><span class="offerte-invoer"><input type="number" name="aantallen[<?= e($service['id']) ?>]" data-dienst="<?= e($service['id']) ?>" value="<?= e($value) ?>" min="0" max="10000" step="0.01" inputmode="decimal" aria-label="<?= e($service['naam'].' in '.$service['eenheid']) ?>"><span><?= e($service['eenheid']) ?></span></span></label>
                <?php endforeach; ?>
                <label class="offerte-label"><span>In wat voor pand wordt het werk uitgevoerd?</span><select name="pand" required><option value="onbekend">Ik weet de ouderdom of het gebruik nog niet</option><?php foreach(array('woning-oud'=>'Woning ouder dan twee jaar','woning-nieuw'=>'Woning van twee jaar of jonger','overig'=>'Ander gebouw of bedrijfsruimte') as $value=>$label): ?><option value="<?= e($value) ?>"<?= offerte_tekst($_POST,'pand')===$value?' selected':'' ?>><?= e($label) ?></option><?php endforeach; ?></select></label>
                <?php if($config['woning_btw']): ?><p class="offerte-hulp">Voor stucwerk aan een woning ouder dan twee jaar rekenen we 9% btw. Voor de andere keuzes rekenen we 21%. De ouderdom en het gebruik worden bij het bespreken van je project gecontroleerd.</p><?php endif; ?>
            </fieldset>
            <fieldset><legend>Op welk adres is je project?</legend>
                <div class="offerte-formgrid"><label class="offerte-label offerte-vol"><span>Straat en huisnummer</span><input name="adres" value="<?= offerte_invoer('adres') ?>" autocomplete="street-address" maxlength="160" required></label><label class="offerte-label"><span>Postcode</span><input name="postcode" value="<?= offerte_invoer('postcode') ?>" autocomplete="postal-code" maxlength="10" required placeholder="5271 KE"></label><label class="offerte-label"><span>Plaats</span><input name="plaats" value="<?= offerte_invoer('plaats') ?>" autocomplete="address-level2" maxlength="100" required></label></div>
            </fieldset>
            <fieldset><legend>Voor wie is de offerte?</legend>
                <div class="offerte-formgrid"><label class="offerte-label offerte-vol"><span>Je naam</span><input name="naam" value="<?= offerte_invoer('naam') ?>" autocomplete="name" maxlength="120" required></label><label class="offerte-label"><span>E-mailadres</span><input type="email" name="email" value="<?= offerte_invoer('email') ?>" autocomplete="email" maxlength="254" required></label><label class="offerte-label"><span>Telefoonnummer, optioneel</span><input type="tel" name="telefoon" value="<?= offerte_invoer('telefoon') ?>" autocomplete="tel" maxlength="40"></label><label class="offerte-label offerte-vol"><span>Toelichting, optioneel</span><textarea name="opmerking" maxlength="2000" rows="4" placeholder="Bijvoorbeeld de ondergrond, gewenste afwerking of planning."><?= offerte_invoer('opmerking') ?></textarea></label></div>
            </fieldset>
            <div class="honeypot" aria-hidden="true"><label>Website<input name="website" tabindex="-1" autocomplete="off"></label></div>
            <p class="offerte-hulp">Je gegevens worden gebruikt voor deze offerte en het contact over je project. Lees onze <a href="/privacy">privacyverklaring</a>.</p>
            <button class="knop" type="submit">Maak mijn offerte en PDF</button>
            <p class="offerte-hulp">Je offerte verschijnt direct op het scherm. Er wordt geen e-mail verstuurd. Bewaar de PDF zelf.</p>
        </div>
        <aside class="offerte-overzicht" aria-labelledby="berekening-titel"><h2 id="berekening-titel">Jouw berekening</h2><div id="offerte-berekening" aria-live="polite"><p>Vul een hoeveelheid in om de prijs te zien.</p></div><p class="offerte-voorwaarden"><?= nl2br(e($config['voorwaarden'])) ?></p><noscript><p>Vul het formulier in om je offerte door de website te laten berekenen.</p></noscript></aside></div>
    </form>
    <script type="application/json" id="offerte-config"><?= json_encode(array_intersect_key($config,array_flip(array('diensten','reiskosten_cent','minimum_cent','btw_standaard','woning_btw'))), JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE) ?></script>
    <script src="/<?= e(asset('assets/offerte.js')) ?>" defer></script>
<?php endif; ?>
</main>
<?php sitevoet(); documentslot(); ?>
