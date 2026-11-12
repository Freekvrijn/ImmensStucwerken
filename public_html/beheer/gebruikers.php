<?php
/**
 * Beheer » Gebruikers: afzonderlijke inlogaccounts en rollen.
 *
 * - De vaste beheerder (data/auth.json) blijft bestaan en wordt beheerd bij
 *   Inloggegevens; die staat hier alleen ter informatie in de lijst.
 * - Overige accounts staan in data/users.json, met als sleutel een
 *   gebruikersnaam (zonder @) of een e-mailadres.
 * - Rollen: beheerder, redacteur of SEO-redacteur.
 * - Elke wijziging vraagt het wachtwoord van wie ingelogd is, ter bevestiging.
 * - Je kunt je eigen account niet verwijderen, uitzetten of degraderen.
 */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
auth_boot();
require_login('users');

$ik     = huidige_gebruiker();
$ikId   = (string) ($ik['id'] ?? '');
$rollen = auth_rollen();
$msg = '';
$ok  = false;

function gebruikers_laden() {
    $u = load_json('users.json', array());
    return is_array($u) ? $u : array();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = (string) ($_POST['actie'] ?? '');
    $users = gebruikers_laden();

    if (!csrf_check()) {
        $msg = 'Sessie verlopen, probeer het opnieuw.';
    } elseif (($wait = login_blocked()) > 0) {
        $msg = 'Te veel mislukte pogingen. Probeer over ' . ceil($wait / 60) . ' minuten opnieuw.';
    } elseif (!auth_eigen_wachtwoord_klopt((string) ($_POST['bevestig'] ?? ''))) {
        login_fail();
        $msg = 'Uw eigen wachtwoord (ter bevestiging) klopt niet.';
    } elseif ($actie === 'toevoegen') {
        $sleutel = auth_sleutel_normaliseren((string) ($_POST['sleutel'] ?? ''));
        $naam    = trim(strip_tags((string) ($_POST['naam'] ?? '')));
        $rol     = (string) ($_POST['rol'] ?? '');
        $pw      = (string) ($_POST['wachtwoord'] ?? '');
        if ($sleutel === '') {
            $msg = 'Vul een geldige gebruikersnaam (3–40 tekens: letters, cijfers, punt of streepje) of een geldig e-mailadres in.';
        } elseif (auth_sleutel_bezet($sleutel)) {
            $msg = '“' . $sleutel . '” is al in gebruik.';
        } elseif (!isset($rollen[$rol])) {
            $msg = 'Kies een rol.';
        } elseif (strlen($pw) < WACHTWOORD_MIN) {
            $msg = 'Kies een wachtwoord van minstens ' . WACHTWOORD_MIN . ' tekens.';
        } else {
            $users[$sleutel] = array(
                'naam'    => $naam !== '' ? mb_substr($naam, 0, 60) : $sleutel,
                'rol'     => $rol,
                'actief'  => true,
                'hash'    => password_hash($pw, PASSWORD_DEFAULT),
                'gemaakt' => date('c'),
            );
            $ok  = save_json('users.json', $users, false);
            $msg = $ok ? 'Account “' . $sleutel . '” aangemaakt. Inloggen kan met deze ' . (strpos($sleutel, '@') !== false ? 'e-mailadres' : 'gebruikersnaam') . ' en het gekozen wachtwoord.'
                       : 'Opslaan mislukte. Is de map data/ schrijfbaar?';
        }
    } else {
        $sleutel = (string) ($_POST['sleutel'] ?? '');
        if (!isset($users[$sleutel]) || !is_array($users[$sleutel])) {
            $msg = 'Dit account bestaat niet (meer).';
        } elseif ($actie === 'wijzigen') {
            $rol    = (string) ($_POST['rol'] ?? $users[$sleutel]['rol'] ?? 'redacteur');
            $actief = !empty($_POST['actief']);
            $naam   = trim(strip_tags((string) ($_POST['naam'] ?? '')));
            $pw     = (string) ($_POST['wachtwoord'] ?? '');
            if (!isset($rollen[$rol])) {
                $msg = 'Kies een rol.';
            } elseif ($sleutel === $ikId && ($rol !== 'admin' || !$actief)) {
                $msg = 'U kunt uw eigen account niet uitzetten of de beheerdersrol ontnemen.';
            } elseif ($pw !== '' && strlen($pw) < WACHTWOORD_MIN) {
                $msg = 'Kies een wachtwoord van minstens ' . WACHTWOORD_MIN . ' tekens.';
            } else {
                $users[$sleutel]['rol']    = $rol;
                $users[$sleutel]['actief'] = $actief;
                if ($naam !== '') { $users[$sleutel]['naam'] = mb_substr($naam, 0, 60); }
                if ($pw !== '') {
                    $users[$sleutel]['hash'] = password_hash($pw, PASSWORD_DEFAULT);
                    // Andere sessies van dit account vervallen (u blijft zelf ingelogd).
                    $users[$sleutel]['sessie_versie'] = auth_nieuwe_sessie_versie($sleutel);
                }
                $ok  = save_json('users.json', $users, false);
                $msg = $ok ? 'Account “' . $sleutel . '” opgeslagen.' . ($pw !== '' ? ' Het nieuwe wachtwoord geldt per direct; wie met dit account elders nog ingelogd was, moet opnieuw inloggen.' : '')
                           : 'Opslaan mislukte. Is de map data/ schrijfbaar?';
            }
        } elseif ($actie === 'verwijderen') {
            if ($sleutel === $ikId) {
                $msg = 'U kunt uw eigen account niet verwijderen.';
            } else {
                unset($users[$sleutel]);
                $ok  = save_json('users.json', $users, false);
                $msg = $ok ? 'Account “' . $sleutel . '” verwijderd.' : 'Opslaan mislukte. Is de map data/ schrijfbaar?';
            }
        } else {
            $msg = 'Onbekende actie.';
        }
    }
    $_SESSION['gebruikers_flash'] = array('ok' => $ok, 'tekst' => $msg);
    header('Location: gebruikers.php');
    exit;
}

$flash = $_SESSION['gebruikers_flash'] ?? null;
unset($_SESSION['gebruikers_flash']);
$users = gebruikers_laden();
ksort($users);
$vasteNaam = beheerder_gebruikersnaam();

$pageTitle = 'Gebruikers';
require __DIR__ . '/_head.php';
?>
<div class="head-row">
  <h1>Gebruikers</h1>
</div>
<p class="lead">
  Geef iedereen een eigen inlog. Een <strong>beheerder</strong> kan alles, een <strong>redacteur</strong> beheert inhoud, media en formulieren,
  en een <strong>SEO-redacteur</strong> beheert alleen vindbaarheid. Inloggen gaat met de gebruikersnaam
  (of het e-mailadres) en het eigen wachtwoord. Bij elke wijziging vraagt het beheer <em>uw eigen</em> wachtwoord ter bevestiging.
</p>
<?php if ($flash && $flash['tekst'] !== ''): ?>
  <p class="msg <?= !empty($flash['ok']) ? 'ok' : 'err' ?>"><?= e($flash['tekst']) ?></p>
<?php endif; ?>

<table class="list">
  <thead><tr><th>Inlognaam</th><th>Naam</th><th>Rol</th><th>Status</th><th class="right"><span class="sr-only">Acties</span></th></tr></thead>
  <tbody>
    <tr>
      <td><code><?= e($vasteNaam !== '' ? $vasteNaam : '(leeg of “beheerder”)') ?></code><?= $ikId === 'beheerder' ? ' <span class="badge volgt">u</span>' : '' ?></td>
      <td>Vaste beheerder</td>
      <td>Beheerder</td>
      <td><span class="badge volgt">actief</span></td>
      <td class="right"><?php if ($ikId === 'beheerder'): ?><a class="btn mini ghost" href="wachtwoord-instellen.php">Inloggegevens</a><?php else: ?><span class="hint">beheer via het vaste account</span><?php endif; ?></td>
    </tr>
    <?php foreach ($users as $sleutel => $rec):
        if (!is_array($rec)) { continue; }
        $rol = (string) ($rec['rol'] ?? 'redacteur');
        $actief = !empty($rec['actief']); ?>
    <tr>
      <td><code><?= e($sleutel) ?></code><?= $sleutel === $ikId ? ' <span class="badge volgt">u</span>' : '' ?></td>
      <td><?= e((string) ($rec['naam'] ?? '')) ?></td>
      <td><?= e($rollen[$rol] ?? 'Onbekend') ?></td>
      <td><span class="badge <?= $actief ? 'volgt' : 'wacht' ?>"><?= $actief ? 'actief' : 'uit' ?></span></td>
      <td class="right">
        <details class="gebruiker-details">
          <summary class="btn mini ghost">Wijzigen&hellip;</summary>
          <form method="post" class="stack gebruiker-form">
            <?= csrf_field() ?>
            <input type="hidden" name="actie" value="wijzigen"><input type="hidden" name="sleutel" value="<?= e($sleutel) ?>">
            <label class="field"><span>Naam</span><input type="text" name="naam" maxlength="60" value="<?= e((string) ($rec['naam'] ?? '')) ?>"></label>
            <label class="field"><span>Rol</span>
              <select name="rol"<?= $sleutel === $ikId ? ' disabled' : '' ?>>
                <?php foreach ($rollen as $code => $label): ?><option value="<?= e($code) ?>"<?= $rol === $code ? ' selected' : '' ?>><?= e($label) ?></option><?php endforeach; ?>
              </select>
              <?php if ($sleutel === $ikId): ?><input type="hidden" name="rol" value="admin"><?php endif; ?>
            </label>
            <label class="field-check"><input type="checkbox" name="actief" value="1"<?= $actief ? ' checked' : '' ?><?= $sleutel === $ikId ? ' disabled' : '' ?>><span>Account actief (mag inloggen)</span></label>
            <?php if ($sleutel === $ikId): ?><input type="hidden" name="actief" value="1"><?php endif; ?>
            <label class="field"><span>Nieuw wachtwoord <small>(leeg = ongewijzigd)</small></span><input type="password" name="wachtwoord" minlength="<?= WACHTWOORD_MIN ?>" autocomplete="new-password"></label>
            <label class="field"><span>Uw eigen wachtwoord (ter bevestiging)</span><input type="password" name="bevestig" autocomplete="current-password" required></label>
            <div class="actions"><button class="btn" type="submit">Opslaan</button></div>
          </form>
          <?php if ($sleutel !== $ikId): ?>
          <form method="post" class="stack gebruiker-form" data-confirm="Het account “<?= e($sleutel) ?>” verwijderen? Deze persoon kan daarna niet meer inloggen.">
            <?= csrf_field() ?>
            <input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="sleutel" value="<?= e($sleutel) ?>">
            <label class="field"><span>Uw eigen wachtwoord (ter bevestiging)</span><input type="password" name="bevestig" autocomplete="current-password" required></label>
            <div class="actions"><button class="btn mini btn-danger" type="submit">Account verwijderen</button></div>
          </form>
          <?php endif; ?>
        </details>
      </td>
    </tr>
    <?php endforeach; ?>
  </tbody>
</table>

<h2 class="groep" style="margin-top:2rem">Nieuw account</h2>
<form method="post" class="stack" autocomplete="off">
  <?= csrf_field() ?>
  <input type="hidden" name="actie" value="toevoegen">
  <label class="field"><span>Gebruikersnaam of e-mailadres</span>
    <input type="text" name="sleutel" maxlength="120" required autocapitalize="none" spellcheck="false" placeholder="bijv. richard">
    <small class="hint">Een gebruikersnaam: 3–40 tekens, letters, cijfers, punt of streepje, geen spaties. Hoofdletters maken bij het inloggen niet uit.</small>
  </label>
  <label class="field"><span>Naam (zoals die in het beheer verschijnt)</span><input type="text" name="naam" maxlength="60" placeholder="bijv. Websitebeheerder"></label>
  <label class="field"><span>Rol</span>
    <select name="rol">
      <?php foreach ($rollen as $code => $label): ?><option value="<?= e($code) ?>"><?= e($label) ?></option><?php endforeach; ?>
    </select>
  </label>
  <label class="field"><span>Wachtwoord (minstens <?= WACHTWOORD_MIN ?> tekens)</span><input type="password" name="wachtwoord" minlength="<?= WACHTWOORD_MIN ?>" required autocomplete="new-password"></label>
  <label class="field"><span>Uw eigen wachtwoord (ter bevestiging)</span><input type="password" name="bevestig" required autocomplete="current-password"></label>
  <div class="actions"><button class="btn" type="submit">Account aanmaken</button></div>
</form>
<?php require __DIR__ . '/_foot.php'; ?>
