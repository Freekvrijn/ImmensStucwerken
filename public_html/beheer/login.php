<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
auth_boot();

// Nog geen wachtwoord ingesteld? Stuur door naar de eenmalige setup.
if (!password_is_set()) {
    header('Location: wachtwoord-instellen.php');
    exit;
}
if (is_logged_in()) {
    header('Location: ' . login_bestemming());
    exit;
}

$error = '';
// 'gebruiker' is het nieuwe veld; 'email' blijft werken voor oude formulieren/wachtwoordmanagers.
$invoer = (string) ($_POST['gebruiker'] ?? $_POST['email'] ?? '');
$naamIngesteld = beheerder_gebruikersnaam() !== '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $error = 'Sessie verlopen, probeer het opnieuw.';
    } else {
        $wait = login_blocked();
        if ($wait > 0) {
            $error = 'Te veel mislukte pogingen. Probeer over ' . ceil($wait / 60) . ' minuten opnieuw.';
        } elseif ($gebruiker = auth_login_controleren($invoer, $_POST['wachtwoord'] ?? '')) {
            login_success($gebruiker);
            header('Location: ' . login_bestemming());
            exit;
        } else {
            login_fail();
            $error = 'Onjuiste inloggegevens.';
        }
    }
}

$pageTitle = 'Inloggen';
require __DIR__ . '/_head.php';
?>
<div class="login-card">
  <h1>Beheer: inloggen</h1>
  <?php if ($error): ?><p class="msg err"><?= e($error) ?></p><?php endif; ?>
  <form method="post">
    <?= csrf_field() ?>
    <label>Gebruikersnaam of e-mailadres
      <input type="text" name="gebruiker" value="<?= e($invoer) ?>" autocomplete="username"
             autocapitalize="none" spellcheck="false" <?= $naamIngesteld ? 'required ' : '' ?>autofocus>
      <?php if (!$naamIngesteld): ?>
        <span class="hint">Nog geen gebruikersnaam ingesteld: de beheerder kan dit veld voorlopig leeg laten.</span>
      <?php endif; ?>
    </label>
    <label>Wachtwoord
      <input type="password" name="wachtwoord" autocomplete="current-password" required>
    </label>
    <button type="submit" class="btn">Inloggen</button>
  </form>
</div>
<?php require __DIR__ . '/_foot.php'; ?>
