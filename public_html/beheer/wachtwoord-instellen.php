<?php
/**
 * Inloggegevens van de vaste beheerder: gebruikersnaam en wachtwoord.
 * - Eerste keer (nog geen wachtwoord): gebruikersnaam + wachtwoord kiezen.
 * - Daarna: alleen voor een ingelogde beheerder, en altijd met het huidige
 *   wachtwoord ter bevestiging. Accounts uit users.json
 *   staan hier los van en worden hier niet gewijzigd.
 */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
auth_boot();

$alreadySet = password_is_set();
if ($alreadySet && !is_admin()) {
    header('Location: login.php');
    exit;
}
// Ingelogd met een eigen account (Beheer » Gebruikers)? Dan wijzigt u uw eigen
// wachtwoord daar; dit scherm is alleen voor het vaste beheerdersaccount.
if ($alreadySet && (huidige_gebruiker()['id'] ?? '') !== 'beheerder') {
    header('Location: gebruikers.php');
    exit;
}

$huidigeNaam = beheerder_gebruikersnaam();
$msg = '';
$ok  = false;
$naamInvoer = $huidigeNaam;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $naamInvoer = trim((string) ($_POST['gebruikersnaam'] ?? ''));
    $pw   = (string) ($_POST['wachtwoord'] ?? '');
    $pw2  = (string) ($_POST['herhaal'] ?? '');
    $huidig = (string) ($_POST['huidig'] ?? '');

    if (!csrf_check()) {
        $msg = 'Sessie verlopen, probeer het opnieuw.';
    } elseif ($alreadySet && ($wait = login_blocked()) > 0) {
        $msg = 'Te veel mislukte pogingen. Probeer over ' . ceil($wait / 60) . ' minuten opnieuw.';
    } elseif ($alreadySet && !verify_password($huidig)) {
        login_fail();
        $msg = 'Het huidige wachtwoord klopt niet.';
    } elseif (($fout = gebruikersnaam_fout($naamInvoer)) !== '') {
        $msg = $fout;
    } elseif (array_key_exists(strtolower($naamInvoer), load_json('users.json', array()))) {
        $msg = 'Deze gebruikersnaam is al in gebruik bij een ander account (Beheer » Gebruikers).';
    } elseif ((!$alreadySet || $pw !== '' || $pw2 !== '') && strlen($pw) < WACHTWOORD_MIN) {
        $msg = 'Kies een wachtwoord van minstens ' . WACHTWOORD_MIN . ' tekens.';
    } elseif ($pw !== $pw2) {
        $msg = 'De twee wachtwoorden zijn niet gelijk.';
    } elseif (beheerder_inlog_opslaan($naamInvoer, $pw !== '' ? $pw : null)) {
        $ok  = true;
        $huidigeNaam = $naamInvoer;
        if ($alreadySet && is_logged_in() && (huidige_gebruiker()['id'] ?? '') === 'beheerder') {
            $_SESSION['auth_user'] = beheerder_sessiedata();
        }
        $msg = $alreadySet
            ? 'Inloggegevens opgeslagen. Gebruik voortaan gebruikersnaam "' . $naamInvoer . '" om in te loggen.'
            : 'Inloggegevens ingesteld. U kunt nu inloggen met gebruikersnaam "' . $naamInvoer . '".';
    } else {
        $msg = 'Opslaan mislukte. Controleer of de map data/ schrijfbaar is.';
    }
}

$pageTitle = 'Inloggegevens';
require __DIR__ . '/_head.php';
?>
<div class="login-card">
  <h1><?= $alreadySet ? 'Inloggegevens beheerder' : 'Inloggegevens instellen' ?></h1>
  <?php if (!$alreadySet): ?>
    <p class="hint">Dit is de eerste keer. Kies een gebruikersnaam en wachtwoord voor het beheerpaneel.</p>
  <?php elseif ($huidigeNaam === ''): ?>
    <p class="msg err">Er is nog geen gebruikersnaam ingesteld. Kies er hieronder een; daarna is die verplicht bij het inloggen.</p>
  <?php else: ?>
    <p class="hint">Dit zijn de gegevens van de vaste beheerder. Aanvullende accounts staan hier los van.</p>
  <?php endif; ?>
  <?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>
  <?php if (!$ok): ?>
  <form method="post" autocomplete="off">
    <?= csrf_field() ?>
    <label>Gebruikersnaam
      <input type="text" name="gebruikersnaam" value="<?= e($naamInvoer) ?>" minlength="3" maxlength="40"
             pattern="[A-Za-z0-9._\-]+" autocomplete="username" autocapitalize="none" spellcheck="false" required autofocus>
      <span class="hint">3–40 tekens: letters, cijfers, punt, - of _. Hoofdletters maken bij inloggen niet uit.</span>
    </label>
    <label><?= $alreadySet ? 'Nieuw wachtwoord (leeg laten = ongewijzigd)' : 'Wachtwoord' ?>
      <input type="password" name="wachtwoord" minlength="<?= WACHTWOORD_MIN ?>" autocomplete="new-password"<?= $alreadySet ? '' : ' required' ?>>
    </label>
    <label>Herhaal <?= $alreadySet ? 'nieuw ' : '' ?>wachtwoord
      <input type="password" name="herhaal" minlength="<?= WACHTWOORD_MIN ?>" autocomplete="new-password"<?= $alreadySet ? '' : ' required' ?>>
    </label>
    <?php if ($alreadySet): ?>
    <label>Huidig wachtwoord (ter bevestiging)
      <input type="password" name="huidig" autocomplete="current-password" required>
    </label>
    <?php endif; ?>
    <button type="submit" class="btn">Opslaan</button>
  </form>
  <?php elseif (!$alreadySet): ?>
  <p><a class="btn" href="login.php">Naar inloggen &rarr;</a></p>
  <?php else: ?>
  <p><a class="btn" href="index.php">Terug naar overzicht &rarr;</a></p>
  <?php endif; ?>
</div>
<?php require __DIR__ . '/_foot.php'; ?>
