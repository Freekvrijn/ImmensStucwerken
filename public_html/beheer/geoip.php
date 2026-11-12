<?php
/** Beheer » Toegang » GeoIP: MaxMind-gegevens invullen en de landendatabase bijwerken. */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/toegang.php';
require_once __DIR__ . '/../_lib/geoip.php';
auth_boot();
require_login('security');

if (!defined('CMS_MODULE_GEOIP') || !CMS_MODULE_GEOIP) {
    header('Location: toegang.php');
    exit;
}

$msg = '';
$ok  = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $msg = 'Sessie verlopen, probeer het opnieuw.';
    } elseif (($_POST['actie'] ?? '') === 'bijwerken') {
        $fout = geoip_bijwerken();
        $ok   = $fout === '';
        $msg  = $ok ? 'De landendatabase is gedownload en actief.' : $fout;
    } elseif (($_POST['actie'] ?? '') === 'opslaan') {
        $huidig = geoip_instellingen();
        $bron   = (string) ($_POST['bron'] ?? '');
        $nieuw  = array('bron' => $bron, 'poging' => 0);
        $fout   = '';

        if (!array_key_exists($bron, GEOIP_BRONNEN)) {
            $fout = 'Kies een bron.';
        } elseif ($bron === 'ip2location') {
            $token  = trim((string) ($_POST['i2l_token'] ?? ''));
            $pakket = (string) ($_POST['i2l_pakket'] ?? '');
            if ($token !== '' && !preg_match('/^[A-Za-z0-9]{16,100}$/', $token)) {
                $fout = 'Deze download-token ziet er niet geldig uit.';
            } elseif (!array_key_exists($pakket, GEOIP_I2L_PAKKETTEN)) {
                $fout = 'Kies een databasepakket.';
            } else {
                $nieuw['i2l_token']  = $token !== '' ? $token : $huidig['i2l_token'];
                $nieuw['i2l_pakket'] = $pakket;
            }
        } else {
            $account = trim((string) ($_POST['account'] ?? ''));
            $sleutel = trim((string) ($_POST['sleutel'] ?? ''));
            $editie  = (string) ($_POST['editie'] ?? '');
            if (!preg_match('/^\d{1,12}$/', $account)) {
                $fout = 'Het account-ID bestaat alleen uit cijfers (te vinden rechtsboven in uw MaxMind-account).';
            } elseif ($sleutel === '' && $huidig['sleutel'] === '') {
                $fout = 'Vul de licentiesleutel in.';
            } elseif ($sleutel !== '' && !preg_match('/^[A-Za-z0-9_]{10,80}$/', $sleutel)) {
                $fout = 'Deze licentiesleutel ziet er niet geldig uit.';
            } elseif (!array_key_exists($editie, GEOIP_EDITIES)) {
                $fout = 'Kies een database-editie.';
            } else {
                $nieuw['account'] = $account;
                $nieuw['sleutel'] = $sleutel !== '' ? $sleutel : $huidig['sleutel'];
                $nieuw['editie']  = $editie;
            }
        }

        if ($fout !== '') {
            $msg = $fout;
        } else {
            geoip_instellingen_opslaan($nieuw);
            $naOpslaan = geoip_instellingen();
            if ($bron === 'ip2location' && $naOpslaan['i2l_token'] === '' && geoip_mmdb_beschikbaar()) {
                $ok = true;
                $msg = 'Opgeslagen. De meegeleverde lokale database blijft actief. Vul een eigen download-token in voor maandelijkse updates.';
            } else {
                // Meteen downloaden, zodat direct zichtbaar is of de gegevens kloppen.
                $fout = geoip_bijwerken();
                $ok   = $fout === '';
                $msg  = $ok ? 'Opgeslagen. De landendatabase is gedownload en landblokkering werkt nu.'
                            : 'Gegevens opgeslagen, maar downloaden lukte niet: ' . $fout;
            }
        }
    } elseif (($_POST['actie'] ?? '') === 'test') {
        $ip = trim((string) ($_POST['test_ip'] ?? ''));
        if (filter_var($ip, FILTER_VALIDATE_IP) === false) {
            $msg = 'Vul een geldig IP-adres in om te testen.';
        } else {
            $land = geoip_land_voor_ip($ip);
            $ok   = $land !== '';
            $msg  = $ok ? $ip . ' → ' . $land . ' (' . (class_exists('Locale') ? Locale::getDisplayRegion('-' . $land, 'nl') : $land) . ')'
                        : 'Voor ' . $ip . ' is geen land gevonden' . (geoip_mmdb_beschikbaar() ? ' (bijv. een intern adres).' : ': er is nog geen database.');
        }
    }
}

$inst = geoip_instellingen();
$info = geoip_db_info();
$testLand = geoip_land_voor_ip('8.8.8.8');

/** Datum/tijd in Nederlandse tijd. */
function geoip_datum($t) {
    if (!$t) { return 'nog niet'; }
    $d = new DateTime('@' . (int) $t);
    $d->setTimezone(new DateTimeZone('Europe/Amsterdam'));
    return $d->format('d-m-Y H:i');
}

$pageTitle = 'GeoIP';
require __DIR__ . '/_head.php';
?>
<p><a href="toegang.php">&larr; Terug naar Toegang &amp; blokkering</a></p>
<h1>GeoIP (landbepaling)</h1>
<p class="lead">
  Voor blokkeren op land moet de website weten uit welk land een bezoeker komt. Daarvoor gebruikt
  het CMS een landendatabase van MaxMind of IP2Location, die met uw account automatisch wordt
  gedownload en ververst. Er gaat daarbij niets over uw bezoekers naar buiten: het opzoeken gebeurt op de eigen server.
</p>

<?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>

<div class="info-blok">
  <?php if ($info): ?>
    <div><strong>Status:</strong> <span class="badge volgt">Actief</span></div>
    <div><strong>Database:</strong> <?= e($info['bron']) ?> · <code><?= e($info['editie']) ?></code>, versie van <?= e(geoip_datum($info['build'])) ?>
      (<?= e(number_format($info['bytes'] / 1048576, 1, ',', '.')) ?> MB)</div>
    <div><strong>Laatst gedownload:</strong> <?= e(geoip_datum($inst['bijgewerkt'])) ?></div>
  <?php else: ?>
    <div><strong>Status:</strong> <span class="badge wacht">Nog niet actief</span> Er is nog geen landendatabase.</div>
  <?php endif; ?>
  <?php if ($inst['laatste_fout'] !== ''): ?>
    <div class="waarschuwing">Laatste poging mislukt: <?= e($inst['laatste_fout']) ?></div>
  <?php endif; ?>
</div>
<?php if ($inst['bron'] === 'ip2location'): ?><p class="hint">Neem op de publieke website de verplichte bronvermelding op. Gebruik daarvoor <code>geoip_attributie_html()</code>. Iedere website gebruikt voor updates een eigen gratis account en download-token.</p><?php endif; ?>

<form method="post" class="stack" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="actie" value="opslaan">

  <h2 class="groep">Bron</h2>
  <div class="geoip-bronnen">
    <?php foreach (GEOIP_BRONNEN as $code => $label): ?>
      <label class="field-check">
        <input type="radio" name="bron" value="<?= e($code) ?>"<?= $inst['bron'] === $code ? ' checked' : '' ?>>
        <span><strong><?= e($label) ?></strong>
          <?= $code === 'ip2location' ? ': met een download-token (LITE DB1 is gratis)' : ': met account-ID en licentiesleutel' ?></span>
      </label>
    <?php endforeach; ?>
  </div>
  <p class="hint" style="margin-top:-.4rem">Alleen de gegevens van de gekozen bron hoeven ingevuld te zijn.</p>

  <h2 class="groep">IP2Location</h2>
  <label class="field">
    <span>Download-token</span>
    <input type="password" name="i2l_token" value="" placeholder="<?= $inst['i2l_token'] !== '' ? '•••••••• (ingesteld, leeg laten = ongewijzigd)' : 'uw download token' ?>">
    <small class="hint">IP2Location-account » <em>Download</em> (dezelfde token als in de WordPress-plugin). Wordt afgeschermd opgeslagen in <code>data/</code> en nooit getoond.</small>
  </label>
  <label class="field">
    <span>Databasepakket</span>
    <select name="i2l_pakket">
      <?php foreach (GEOIP_I2L_PAKKETTEN as $code => $label): ?>
        <option value="<?= e($code) ?>"<?= $code === $inst['i2l_pakket'] ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <small class="hint">Alleen landen zijn nodig (DB1). IPv4 + IPv6 is aanbevolen: veel mobiele bezoekers hebben een IPv6-adres.</small>
  </label>

  <h2 class="groep">MaxMind</h2>
  <label class="field">
    <span>Account-ID</span>
    <input type="text" name="account" value="<?= e($inst['account']) ?>" inputmode="numeric" placeholder="bijv. 1234567">
    <small class="hint">Staat in uw MaxMind-account (en in GeoIP.conf als <code>AccountID</code>).</small>
  </label>
  <label class="field">
    <span>Licentiesleutel</span>
    <input type="password" name="sleutel" value="" placeholder="<?= $inst['sleutel'] !== '' ? '•••••••• (ingesteld, leeg laten = ongewijzigd)' : 'uw license key' ?>">
    <small class="hint">MaxMind-account » <em>Manage License Keys</em> (in GeoIP.conf: <code>LicenseKey</code>). Wordt afgeschermd opgeslagen in <code>data/</code> en nooit getoond.</small>
  </label>
  <label class="field">
    <span>Database-editie</span>
    <select name="editie">
      <?php foreach (GEOIP_EDITIES as $code => $label): ?>
        <option value="<?= e($code) ?>"<?= $code === $inst['editie'] ? ' selected' : '' ?>><?= e($label) ?></option>
      <?php endforeach; ?>
    </select>
    <small class="hint">Kies de editie die bij uw abonnement hoort (in GeoIP.conf: <code>EditionIDs</code>).</small>
  </label>

  <div class="actions"><button class="btn" type="submit">Opslaan &amp; downloaden</button></div>
</form>

<?php if (geoip_gegevens_ingevuld($inst)): ?>
<form method="post" class="actions" style="margin-top:1rem">
  <?= csrf_field() ?>
  <input type="hidden" name="actie" value="bijwerken">
  <button class="btn ghost" type="submit">Nu bijwerken</button>
  <span class="hint">Gebeurt ook automatisch als u de pagina Toegang opent en de database ouder is dan
    <?= $inst['bron'] === 'ip2location' ? '30 dagen (IP2Location brengt elke maand een nieuwe versie uit)' : '7 dagen' ?>.</span>
</form>
<?php endif; ?>

<?php if ($info): ?>
<form method="post" class="stack" style="margin-top:1.5rem">
  <?= csrf_field() ?>
  <input type="hidden" name="actie" value="test">
  <h2 class="groep">Testen</h2>
  <label class="field">
    <span>IP-adres opzoeken</span>
    <input type="text" name="test_ip" value="<?= e(client_ip()) ?>">
  </label>
  <div class="actions"><button class="btn ghost" type="submit">Land opzoeken</button></div>
</form>
<?php endif; ?>
<?php if ($testLand !== ''): ?><p class="msg ok">Lokale landherkenning werkt. Technische test: 8.8.8.8 wordt herkend als <?= e($testLand) ?>.</p><?php else: ?><p class="msg err">De lokale database is niet bruikbaar. Download de database opnieuw of controleer de schrijfrechten van <code>data/geoip/</code>.</p><?php endif; ?>

<p class="hint" style="margin-top:1.5rem">
  Optioneel: bijwerken via een cronjob in DirectAdmin, bijv.
  <?= $inst['bron'] === 'ip2location' ? 'de 3e van elke maand om 04:15' : 'dinsdag en vrijdag 04:15' ?>:<br>
  <code><?= $inst['bron'] === 'ip2location' ? '15 4 3 * *' : '15 4 * * 2,5' ?> /usr/local/bin/php <?= e(realpath(__DIR__ . '/../_lib/geoip-cron.php') ?: '…/public_html/_lib/geoip-cron.php') ?></code>
</p>
<?php require __DIR__ . '/_foot.php'; ?>
