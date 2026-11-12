<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/data.php';
require_once __DIR__ . '/../_lib/toegang.php';
require_once __DIR__ . '/../_lib/bezoekers.php';
auth_boot();
require_login('security');

if (!defined('CMS_TOEGANG_AAN') || !CMS_TOEGANG_AAN) {
    header('Location: index.php');
    exit;
}

/** Tekstvak (één regel per waarde) -> nette array: trimmen, lege eruit, dubbele eruit. */
function regels_naar_lijst($tekst) {
    $uit = array();
    foreach (preg_split('/\r\n|\r|\n/', (string) $tekst) as $r) {
        $r = trim($r);
        if ($r !== '' && !in_array($r, $uit, true)) { $uit[] = $r; }
    }
    return $uit;
}

// MaxMind-landendatabase automatisch verversen als die ouder is dan 7 dagen
// (max. één poging per uur; kost dan een paar seconden bij het openen).
$geoipFout = '';
if ($_SERVER['REQUEST_METHOD'] !== 'POST' && geoip_bijwerken_nodig()) {
    $geoipFout = geoip_bijwerken();
}

$regels = toegang_regels();
$whitelist = toegang_whitelist();
$mijnIp   = client_ip();
$mijnLand = client_land();

$msg = '';
$ok  = false;

/* -------- filters voor het bezoekersoverzicht (GET) -------- */
$periodes = array(1 => 'Afgelopen 24 uur', 7 => 'Afgelopen 7 dagen', 30 => 'Afgelopen 30 dagen');
$fPeriode = (int) ($_GET['periode'] ?? 7);
if (!isset($periodes[$fPeriode])) { $fPeriode = 7; }
$fLand  = strtoupper(preg_replace('/[^A-Za-z]/', '', (string) ($_GET['land'] ?? '')));
$fLand  = strlen($fLand) === 2 ? $fLand : '';
$fZoek  = substr(preg_replace('/[^0-9A-Fa-f:.]/', '', (string) ($_GET['zoek'] ?? '')), 0, 45);
$fBots  = !empty($_GET['bots']);          // bots tonen?
$fStatus = in_array(($_GET['status'] ?? ''), array('', 'open', 'geblokkeerd'), true) ? (string) ($_GET['status'] ?? '') : '';

/** Querystring om na een actie naar dezelfde gefilterde weergave terug te keren. */
function toegang_filter_query($periode, $land, $zoek, $bots, $status) {
    return http_build_query(array_filter(array(
        'periode' => $periode !== 7 ? $periode : null,
        'land'    => $land !== '' ? $land : null,
        'zoek'    => $zoek !== '' ? $zoek : null,
        'bots'    => $bots ? 1 : null,
        'status'  => $status !== '' ? $status : null,
    ), function ($v) { return $v !== null; }));
}

/* -------- acties uit het bezoekersoverzicht (blokkeren / deblokkeren / wissen) -------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bezoeker_actie'])) {
    $actie = (string) $_POST['bezoeker_actie'];
    $ip    = trim((string) ($_POST['ip'] ?? ''));
    $flash = array('ok' => false, 'tekst' => 'Sessie verlopen, probeer het opnieuw.');
    if (csrf_check()) {
        if ($actie === 'blokkeer' || $actie === 'deblokkeer') {
            $fout = toegang_ip_instellen($ip, $actie === 'blokkeer');
            $flash = $fout === ''
                ? array('ok' => true, 'tekst' => $actie === 'blokkeer'
                    ? 'IP-adres ' . $ip . ' is geblokkeerd voor de website én het beheer.'
                    : 'IP-adres ' . $ip . ' is gedeblokkeerd.')
                : array('ok' => false, 'tekst' => $fout);
        } elseif ($actie === 'wissen') {
            bezoekers_wissen();
            $flash = array('ok' => true, 'tekst' => 'Het bezoekersoverzicht is gewist.');
        } else {
            $flash = array('ok' => false, 'tekst' => 'Onbekende actie.');
        }
    }
    $_SESSION['toegang_flash'] = $flash;
    // Post/Redirect/Get: vernieuwen herhaalt de actie niet, filters blijven staan.
    $q = toegang_filter_query($fPeriode, $fLand, $fZoek, $fBots, $fStatus);
    header('Location: toegang.php' . ($q !== '' ? '?' . $q : '') . '#bezoekers');
    exit;
}
$bezoekerMsg = $_SESSION['toegang_flash'] ?? null;
unset($_SESSION['toegang_flash']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $msg = 'Sessie verlopen, probeer het opnieuw.';
    } else {
        $nieuw  = array();
        $fouten = array();

        foreach (array('beheer' => 'Beheer', 'site' => 'Website') as $scope => $naam) {
            $ips    = regels_naar_lijst($_POST[$scope . '_ips'] ?? '');
            $landen = regels_naar_lijst($_POST[$scope . '_landen'] ?? '');

            // IP's / CIDR valideren
            foreach ($ips as $r) {
                if (!geldig_ip_of_cidr($r)) { $fouten[] = "$naam: “" . $r . "” is geen geldig IP-adres of bereik."; }
            }
            // Landcodes valideren (2 letters) en normaliseren naar hoofdletters
            $landenNorm = array();
            foreach ($landen as $l) {
                if (!preg_match('/^[A-Za-z]{2}$/', $l)) { $fouten[] = "$naam: “" . $l . "” is geen geldige landcode (2 letters, bijv. NL)."; }
                else { $landenNorm[] = strtoupper($l); }
            }

            $nieuw[$scope] = array('ips' => $ips, 'landen' => $landenNorm);
        }

        // Whitelist (globaal): IP's die altijd toegang houden. Valideren als IP/CIDR.
        $wl = regels_naar_lijst($_POST['whitelist'] ?? '');
        foreach ($wl as $r) {
            if (!geldig_ip_of_cidr($r)) { $fouten[] = 'Whitelist: “' . $r . '” is geen geldig IP-adres of bereik.'; }
        }
        $nieuw['whitelist'] = $wl;
        $whitelist = $wl; // toon de ingevoerde waarden terug, wat er ook gebeurt

        // Anti-buitensluiten: je eigen IP mag niet op de BEHEER-lijst komen.
        if ($mijnIp !== '') {
            foreach ($nieuw['beheer']['ips'] as $r) {
                if (ip_matcht($mijnIp, $r)) {
                    $fouten[] = 'Uw eigen IP-adres (' . $mijnIp . ') staat op de beheerblokkade. Daarmee sluit u uzelf buiten. Het adres is verwijderd om dit te voorkomen.';
                    // laat de regel weg zodat opslaan alsnog kan
                    $nieuw['beheer']['ips'] = array_values(array_filter(
                        $nieuw['beheer']['ips'],
                        function ($x) use ($mijnIp) { return !ip_matcht($mijnIp, $x); }
                    ));
                }
            }
        }
        // En als je eigen land op de beheer-lijst staat (met werkende GeoIP): ook weigeren.
        if ($mijnLand !== '' && in_array($mijnLand, $nieuw['beheer']['landen'], true)) {
            $fouten[] = 'Uw eigen land (' . $mijnLand . ') staat op de beheerblokkade. Dat sluit u mogelijk buiten, daarom is het verwijderd.';
            $nieuw['beheer']['landen'] = array_values(array_diff($nieuw['beheer']['landen'], array($mijnLand)));
        }

        // Alleen echte fouten (ongeldige invoer) blokkeren het opslaan; de
        // lock-out-correcties zijn waarschuwingen — we slaan de gecorrigeerde lijst op.
        $harde_fouten = array_filter($fouten, function ($f) {
            return strpos($f, 'geen geldig') !== false || strpos($f, 'geldige landcode') !== false;
        });

        if ($harde_fouten) {
            $msg = implode(' ', $fouten);
            $regels = $nieuw; // toon de ingevoerde waarden terug
        } elseif (save_json('toegang.json', $nieuw)) {
            $regels = $nieuw;
            $ok  = true;
            $msg = $fouten ? ('Opgeslagen. ' . implode(' ', $fouten)) : 'Toegangsregels opgeslagen.';
        } else {
            $msg = 'Opslaan mislukte. Is de map data/ schrijfbaar?';
        }
    }
}

/* -------- bezoekersoverzicht opbouwen -------- */
$alleBezoekers = bezoekers_overzicht($fPeriode);
if (bezoekers_landen_aanvullen($alleBezoekers) > 0) {
    $alleBezoekers = bezoekers_overzicht($fPeriode); // opnieuw, nu met de gevonden landen
}
$whitelistNu = toegang_whitelist();
$regelsNu    = toegang_regels();
$landenInLog = array();
$totVerzoeken = 0; $totGeweigerd = 0;
foreach ($alleBezoekers as $ip => $b) {
    $alleBezoekers[$ip]['status'] = toegang_ip_status($ip, $b['land'], $mijnIp, $regelsNu, $whitelistNu);
    if ($b['land'] !== '') { $landenInLog[$b['land']] = ($landenInLog[$b['land']] ?? 0) + 1; }
    $totVerzoeken += $b['aantal'];
    $totGeweigerd += $b['geblokkeerd'];
}
ksort($landenInLog);

$bezoekers = array_filter($alleBezoekers, function ($b) use ($fLand, $fZoek, $fBots, $fStatus) {
    if (!$fBots && $b['bot']) { return false; }
    if ($fLand !== '' && $b['land'] !== $fLand) { return false; }
    if ($fZoek !== '' && stripos($b['ip'], $fZoek) === false) { return false; }
    $geblokkeerd = in_array($b['status'][0], array('ip', 'bereik', 'land'), true);
    if ($fStatus === 'open' && $geblokkeerd) { return false; }
    if ($fStatus === 'geblokkeerd' && !$geblokkeerd) { return false; }
    return true;
});
$aantalGefilterd = count($bezoekers);
$maxRijen = 300;
$bezoekers = array_slice($bezoekers, 0, $maxRijen, true);
$aantalBots = count(array_filter($alleBezoekers, function ($b) { return $b['bot']; }));
$terugQuery = toegang_filter_query($fPeriode, $fLand, $fZoek, $fBots, $fStatus);

/** Tijdstip in Nederlandse tijd, kort. */
function toegang_tijd($t) {
    $d = new DateTime('@' . (int) $t);
    $d->setTimezone(new DateTimeZone('Europe/Amsterdam'));
    $vandaag = new DateTime('now', new DateTimeZone('Europe/Amsterdam'));
    if ($d->format('Y-m-d') === $vandaag->format('Y-m-d')) { return 'vandaag ' . $d->format('H:i'); }
    $gisteren = (clone $vandaag)->modify('-1 day');
    if ($d->format('Y-m-d') === $gisteren->format('Y-m-d')) { return 'gisteren ' . $d->format('H:i'); }
    return $d->format('d-m-Y H:i');
}

$pageTitle = 'Toegang';
require __DIR__ . '/_head.php';
?>
<h1>Toegang &amp; blokkering</h1>
<p class="lead">
  Weiger bepaalde IP-adressen of landen. U kunt het beheer (<code>/beheer/</code>) en de hele
  website los instellen. Wie op de lijst staat wordt geweigerd; de rest heeft gewoon toegang.
</p>

<div class="info-blok">
  <div><strong>Uw huidige IP-adres:</strong> <code><?= e($mijnIp !== '' ? $mijnIp : 'onbekend') ?></code></div>
  <div><strong>Gedetecteerd land:</strong>
    <?php if ($mijnLand !== ''): ?><code><?= e($mijnLand) ?></code>
    <?php else: ?><span class="hint">onbekend<?= geoip_beschikbaar() ? '' : ' (geen GeoIP beschikbaar)' ?></span><?php endif; ?>
  </div>
  <div><a href="#bezoekers">Naar het bezoekersoverzicht &darr;</a></div>
  <div><strong>Landbepaling:</strong>
    <?php if (geoip_mmdb_beschikbaar()): ?>eigen landendatabase actief<?php elseif (geoip_beschikbaar()): ?>via de server<?php else: ?><span class="hint">niet actief</span><?php endif; ?>
    · <a href="geoip.php">GeoIP-instellingen</a>
  </div>
  <?php if (!geoip_beschikbaar()): ?>
    <div class="waarschuwing">Landblokkering werkt op deze server (nog) niet: er is geen GeoIP.
      IP-blokkering werkt wél. Vul bij <a href="geoip.php">GeoIP-instellingen</a> uw MaxMind- of IP2Location-gegevens in
      om op land te kunnen blokkeren.</div>
  <?php endif; ?>
  <?php if ($geoipFout !== ''): ?>
    <div class="waarschuwing">Automatisch bijwerken van de GeoIP-database mislukte: <?= e($geoipFout) ?></div>
  <?php endif; ?>
</div>

<?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>

<form method="post" class="stack">
  <?= csrf_field() ?>

  <?php
  // Staat mijn huidige IP al op de (ingevoerde/opgeslagen) whitelist?
  $ipInWl = false;
  if ($mijnIp !== '') {
      foreach ($whitelist as $r) { if (ip_matcht($mijnIp, $r)) { $ipInWl = true; break; } }
  }
  ?>
  <h2 class="groep">Altijd toegang (whitelist)</h2>
  <p class="hint" style="margin-top:-.4rem">Deze IP-adressen worden <strong>nooit</strong> geblokkeerd, ook niet
    door de blokkades hieronder. Zet hier uw eigen IP neer, dan kunt u zichzelf niet per ongeluk buitensluiten.
    De whitelist wint altijd.</p>
  <label class="field">
    <span>Altijd toegestane IP-adressen</span>
    <textarea name="whitelist" rows="3" placeholder="Eén per regel. Bijv. 203.0.113.5 of een bereik 203.0.113.0/24"><?= e(implode("\n", $whitelist)) ?></textarea>
    <small class="hint">
      <?php if ($mijnIp === ''): ?>
        Eén IP-adres of bereik (CIDR) per regel. Werkt voor IPv4 en IPv6.
      <?php elseif ($ipInWl): ?>
        Uw huidige IP (<code><?= e($mijnIp) ?></code>) staat op de whitelist. U kunt uzelf niet buitensluiten. Eén IP of bereik per regel.
      <?php else: ?>
        Tip: uw huidige IP is <code><?= e($mijnIp) ?></code>. Zet dat hier neer om uzelf altijd toegang te geven. Eén IP of bereik per regel.
      <?php endif; ?>
    </small>
  </label>

  <?php
  $secties = array(
      'beheer' => array('Beheer (/beheer/)', 'Wie hier op staat kan niet in het beheerpaneel. Uw eigen IP wordt automatisch beschermd tegen buitensluiten.'),
      'site'   => array('Hele website', 'Wie hier op staat krijgt de héle site niet te zien (403). Wees voorzichtig: te breed blokkeren weert ook echte bezoekers.'),
  );
  foreach ($secties as $scope => $info): ?>
    <h2 class="groep"><?= e($info[0]) ?></h2>
    <p class="hint" style="margin-top:-.4rem"><?= e($info[1]) ?></p>
    <label class="field">
      <span>Geblokkeerde IP-adressen</span>
      <textarea name="<?= $scope ?>_ips" rows="4" placeholder="Eén per regel. Bijv. 203.0.113.5 of een bereik 203.0.113.0/24"><?= e(implode("\n", $regels[$scope]['ips'])) ?></textarea>
      <small class="hint">Eén IP-adres of bereik (CIDR) per regel. Werkt voor IPv4 en IPv6.</small>
    </label>
    <label class="field">
      <span>Geblokkeerde landen</span>
      <textarea name="<?= $scope ?>_landen" rows="2" placeholder="Landcodes, één per regel. Bijv. RU, CN, KP"><?= e(implode("\n", $regels[$scope]['landen'])) ?></textarea>
      <small class="hint">ISO-landcodes van 2 letters, één per regel (NL = Nederland, DE = Duitsland, enz.).</small>
    </label>
  <?php endforeach; ?>

  <div class="actions">
    <button class="btn" type="submit">Opslaan</button>
  </div>
</form>

<section id="bezoekers" class="bezoekers">
  <h2>Bezoekers</h2>
  <p class="lead">
    Welke IP-adressen de website bezochten, met hun land. Met <strong>Blokkeren</strong> weigert u dat
    IP-adres voor de hele website én het beheer; het adres komt dan in de lijsten hierboven te staan.
    Gegevens worden <?= (int) BEZOEKERS_BEWAAR_DAGEN ?> dagen bewaard.
  </p>

  <?php if ($bezoekerMsg): ?>
    <p class="msg <?= !empty($bezoekerMsg['ok']) ? 'ok' : 'err' ?>"><?= e($bezoekerMsg['tekst']) ?></p>
  <?php endif; ?>

  <form method="get" action="toegang.php#bezoekers" class="bezoekers-filter">
    <label>Periode
      <select name="periode">
        <?php foreach ($periodes as $waarde => $label): ?>
          <option value="<?= $waarde ?>"<?= $waarde === $fPeriode ? ' selected' : '' ?>><?= e($label) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Land
      <select name="land">
        <option value="">Alle landen</option>
        <?php foreach ($landenInLog as $code => $n): ?>
          <option value="<?= e($code) ?>"<?= $code === $fLand ? ' selected' : '' ?>><?= e($code . ' – ' . bezoekers_landnaam($code)) ?> (<?= (int) $n ?>)</option>
        <?php endforeach; ?>
      </select>
    </label>
    <label>Status
      <select name="status">
        <option value=""<?= $fStatus === '' ? ' selected' : '' ?>>Alles</option>
        <option value="open"<?= $fStatus === 'open' ? ' selected' : '' ?>>Met toegang</option>
        <option value="geblokkeerd"<?= $fStatus === 'geblokkeerd' ? ' selected' : '' ?>>Geblokkeerd</option>
      </select>
    </label>
    <label>IP bevat
      <input type="text" name="zoek" value="<?= e($fZoek) ?>" placeholder="bijv. 185.220" inputmode="decimal">
    </label>
    <label class="bezoekers-check">
      <input type="checkbox" name="bots" value="1"<?= $fBots ? ' checked' : '' ?>> Bots tonen
    </label>
    <button type="submit" class="btn ghost">Filteren</button>
  </form>

  <p class="bezoekers-samenvatting">
    <strong><?= count($alleBezoekers) ?></strong> IP-adressen ·
    <strong><?= $totVerzoeken ?></strong> paginaverzoeken ·
    <strong><?= $totGeweigerd ?></strong> geweigerd
    <?php if ($aantalBots && !$fBots): ?> · <?= $aantalBots ?> bot(s) verborgen<?php endif; ?>
    <?php if ($aantalGefilterd !== count($alleBezoekers)): ?> · <?= $aantalGefilterd ?> getoond na filter<?php endif; ?>
  </p>

  <?php if (!$bezoekers): ?>
    <div class="info-blok"><span class="hint">
      <?= $alleBezoekers ? 'Geen bezoekers die aan dit filter voldoen.' : 'Nog geen bezoeken vastgelegd in deze periode. Het overzicht vult zich vanzelf zodra er bezoekers op de website komen.' ?>
    </span></div>
  <?php else: ?>
  <div class="tabel-scroll">
  <table class="list bezoekers-lijst">
    <thead>
      <tr>
        <th>IP-adres</th>
        <th>Land</th>
        <th class="right">Aantal</th>
        <th>Laatst gezien</th>
        <th>Status</th>
        <th class="right"><span class="sr-only">Actie</span></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($bezoekers as $b):
        list($statusCode, $statusTekst) = $b['status'];
        $geblokkeerd = in_array($statusCode, array('ip', 'bereik', 'land'), true); ?>
      <tr class="<?= $geblokkeerd ? 'is-geblokkeerd' : '' ?>">
        <td>
          <code class="ip"><?= e($b['ip']) ?></code>
          <?php if ($b['bot']): ?><span class="badge bot" title="<?= e($b['ua']) ?>">bot</span><?php endif; ?>
          <?php if ($b['ua'] !== '' && !$b['bot']): ?><span class="ua" title="<?= e($b['ua']) ?>"><?= e(mb_substr($b['ua'], 0, 60, 'UTF-8')) ?><?= mb_strlen($b['ua'], 'UTF-8') > 60 ? '…' : '' ?></span><?php endif; ?>
        </td>
        <td class="nowrap land">
          <?php if ($b['land'] !== ''): ?>
            <span aria-hidden="true"><?= bezoekers_vlag($b['land']) ?></span>
            <strong><?= e($b['land']) ?></strong>
            <span class="landnaam"><?= e(bezoekers_landnaam($b['land'])) ?></span>
          <?php else: ?><span class="hint">onbekend</span><?php endif; ?>
        </td>
        <td class="right"><span class="aantal"><?= (int) $b['aantal'] ?></span><?php if ($b['geblokkeerd']): ?><span class="geweigerd" title="Aantal geweigerde verzoeken"><?= (int) $b['geblokkeerd'] ?> geweigerd</span><?php endif; ?></td>
        <td class="gezien" title="Eerst gezien: <?= e(toegang_tijd($b['eerste'])) ?>">
          <span class="tijd"><?= e(toegang_tijd($b['laatste'])) ?></span>
          <span class="pad" title="<?= e($b['pad']) ?>"><?= e($b['pad']) ?></span>
        </td>
        <td><span class="badge status-<?= e($statusCode) ?>"><?= e($statusTekst) ?></span></td>
        <td class="right">
          <?php if ($statusCode === 'eigen' || $statusCode === 'whitelist'): ?>
            <span class="hint">—</span>
          <?php elseif ($statusCode === 'ip'): ?>
            <form method="post" action="toegang.php?<?= e($terugQuery) ?>">
              <?= csrf_field() ?>
              <input type="hidden" name="bezoeker_actie" value="deblokkeer">
              <input type="hidden" name="ip" value="<?= e($b['ip']) ?>">
              <button type="submit" class="btn mini ghost">Deblokkeren</button>
            </form>
          <?php elseif ($statusCode === 'bereik'): ?>
            <span class="hint" title="Valt onder een IP-bereik. Pas dat bereik hierboven aan om te deblokkeren.">via bereik</span>
          <?php else: ?>
            <form method="post" action="toegang.php?<?= e($terugQuery) ?>" data-confirm="IP-adres <?= e($b['ip']) ?> blokkeren voor de website én het beheer?">
              <?= csrf_field() ?>
              <input type="hidden" name="bezoeker_actie" value="blokkeer">
              <input type="hidden" name="ip" value="<?= e($b['ip']) ?>">
              <button type="submit" class="btn mini blokkeer">Blokkeren</button>
            </form>
          <?php endif; ?>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
  </div>
  <?php if ($aantalGefilterd > $maxRijen): ?>
    <p class="hint">Alleen de <?= $maxRijen ?> meest recente van <?= $aantalGefilterd ?> worden getoond. Gebruik de filters om te zoeken.</p>
  <?php endif; ?>
  <?php endif; ?>

  <?php if ($alleBezoekers): ?>
  <form method="post" action="toegang.php" class="bezoekers-wissen" data-confirm="Het complete bezoekersoverzicht wissen? Blokkades blijven gewoon staan.">
    <?= csrf_field() ?>
    <input type="hidden" name="bezoeker_actie" value="wissen">
    <button type="submit" class="link-del">Bezoekersoverzicht wissen</button>
  </form>
  <?php endif; ?>
  <?php if (!geoip_mmdb_beschikbaar() && CMS_BEZOEKERS_LAND_OPZOEKEN): ?>
  <p class="hint">Zolang er geen lokale GeoIP-database is, worden onbekende landen bij het openen van deze pagina opgezocht via api.country.is (alleen het IP-adres wordt verstuurd).</p>
  <?php elseif (!geoip_mmdb_beschikbaar()): ?>
  <p class="hint">Externe landopzoeking staat uit. Gebruik een lokale GeoIP-database of zet deze privacykeuze bewust aan in <code>_lib/config.php</code>.</p>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/_foot.php'; ?>
