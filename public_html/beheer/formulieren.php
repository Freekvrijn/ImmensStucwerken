<?php
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/cms.php';
require_once __DIR__ . '/../_lib/formulieren.php';
auth_boot();
require_login('forms');
if (!cms_module_aan('formulieren')) { header('Location: index.php'); exit; }

$formulieren = cms_formulieren_alle();
$inzendingen = cms_inzendingen_alle();
$msg = '';
$ok = false;

if (($_GET['actie'] ?? '') === 'export') {
    header('Content-Type: text/csv; charset=UTF-8');
    header('Content-Disposition: attachment; filename="inzendingen-' . date('Ymd-His') . '.csv"');
    $uit = fopen('php://output', 'wb');
    fwrite($uit, "\xEF\xBB\xBF");
    fputcsv($uit, array('id', 'formulier', 'ontvangen_op', 'status', 'naam', 'email', 'ip', 'velden_json'), ';');
    foreach ($inzendingen as $rij) {
        fputcsv($uit, array($rij['id'] ?? '', $rij['formulier'] ?? '', $rij['ontvangen_op'] ?? '',
            $rij['status'] ?? '', $rij['naam'] ?? '', $rij['email'] ?? '', $rij['ip'] ?? '',
            json_encode($rij['velden'] ?? array(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)), ';');
    }
    fclose($uit);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_check()) {
        $msg = 'Sessie verlopen, probeer het opnieuw.';
    } else {
        $actie = (string) ($_POST['actie'] ?? '');
        if ($actie === 'instellingen') {
            $id = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_POST['formulier'] ?? 'contact'));
            $mailTo = trim((string) ($_POST['mail_to'] ?? ''));
            $bcc = trim((string) ($_POST['mail_bcc'] ?? ''));
            $from = trim((string) ($_POST['mail_from'] ?? ''));
            if ($id === '' || trim((string) ($_POST['naam'] ?? '')) === '') { $msg = 'Vul een formuliernaam in.'; }
            elseif ($mailTo !== '' && !filter_var($mailTo, FILTER_VALIDATE_EMAIL)) { $msg = 'Het ontvangstadres is ongeldig.'; }
            elseif ($bcc !== '' && !filter_var($bcc, FILTER_VALIDATE_EMAIL)) { $msg = 'Het BCC-adres is ongeldig.'; }
            elseif ($from !== '' && !filter_var($from, FILTER_VALIDATE_EMAIL)) { $msg = 'Het afzenderadres is ongeldig.'; }
            else {
                $formulieren[$id] = array(
                    'naam' => trim((string) $_POST['naam']), 'actief' => !empty($_POST['actief']),
                    'mail_to' => $mailTo, 'mail_bcc' => $bcc, 'mail_from' => $from,
                    'mail_from_naam' => trim((string) ($_POST['mail_from_naam'] ?? '')),
                    'subject_prefix' => trim((string) ($_POST['subject_prefix'] ?? '')),
                    'autoresponder' => !empty($_POST['autoresponder']),
                );
                $ok = save_json('formulieren.json', $formulieren);
                $msg = $ok ? 'Formulierinstellingen opgeslagen.' : 'Opslaan mislukte.';
            }
        } elseif ($actie === 'ouder') {
            $resultaat = cms_inzendingen_opruimen();
            $ok = $resultaat['ok'];
            $msg = $ok ? $resultaat['aantal'] . ' oude inzending(en) verwijderd.' : 'Opslaan mislukte.';
            $inzendingen = cms_inzendingen_alle();
        } elseif (in_array($actie, array('status', 'verwijderen', 'blokkeren', 'deblokkeren', 'spam'), true)) {
            $id = (string) ($_POST['id'] ?? '');
            $gevonden = -1;
            foreach ($inzendingen as $i => $rij) { if (($rij['id'] ?? '') === $id) { $gevonden = $i; break; } }
            $email = trim((string) ($_POST['email'] ?? ''));
            $ip = trim((string) ($_POST['ip'] ?? ''));
            if (($actie === 'blokkeren' || $actie === 'deblokkeren') && ($email !== '' || $ip !== '')) {
                $ok = cms_formulier_blokkade_wijzig($email, $ip, $actie === 'blokkeren');
                $msg = $ok ? ($actie === 'blokkeren' ? 'Afzender geblokkeerd.' : 'Blokkade opgeheven.') : 'Opslaan mislukte.';
            } elseif ($gevonden < 0) {
                $msg = 'Inzending niet gevonden.';
            } elseif ($actie === 'verwijderen') {
                array_splice($inzendingen, $gevonden, 1);
                $ok = save_json('inzendingen.json', array_values($inzendingen), false);
                $msg = $ok ? 'Inzending definitief verwijderd.' : 'Opslaan mislukte.';
            } elseif ($actie === 'status') {
                $inzendingen[$gevonden]['status'] = ($inzendingen[$gevonden]['status'] ?? 'nieuw') === 'nieuw' ? 'gelezen' : 'nieuw';
                $ok = save_json('inzendingen.json', $inzendingen, false);
                $msg = $ok ? 'Status aangepast.' : 'Opslaan mislukte.';
            } elseif ($actie === 'spam') {
                $email = strtolower((string) ($inzendingen[$gevonden]['email'] ?? $email));
                $ip = (string) ($inzendingen[$gevonden]['ip'] ?? $ip);
                cms_formulier_blokkade_wijzig($email, $ip, true);
                $inzendingen = array_values(array_filter($inzendingen, function ($rij) use ($email, $id) {
                    if (($rij['id'] ?? '') === $id) { return false; }
                    return $email === '' || strtolower((string) ($rij['email'] ?? '')) !== $email;
                }));
                $ok = save_json('inzendingen.json', $inzendingen, false);
                $msg = $ok ? 'Als spam gemarkeerd. De afzender is geblokkeerd en bijbehorende inzendingen zijn verwijderd.' : 'Opslaan mislukte.';
            }
        }
    }
}

$open = (string) ($_GET['id'] ?? '');
$gekozen = null;
if ($open !== '') {
    foreach ($inzendingen as &$rij) {
        if (($rij['id'] ?? '') !== $open) { continue; }
        if (($rij['status'] ?? 'nieuw') === 'nieuw') {
            $rij['status'] = 'gelezen';
            save_json('inzendingen.json', $inzendingen, false);
        }
        $gekozen = $rij;
        break;
    }
    unset($rij);
}
$filter = preg_replace('/[^a-z0-9_-]/i', '', (string) ($_GET['formulier'] ?? ''));
$blokkade = cms_formulier_blokkade();
$contact = array_merge(array('naam' => 'Contactformulier', 'actief' => false, 'mail_to' => '', 'mail_bcc' => '',
    'mail_from' => '', 'mail_from_naam' => '', 'subject_prefix' => 'Nieuw bericht via de website', 'autoresponder' => false),
    $formulieren['contact'] ?? array());
$pageTitle = 'Formulieren';
require __DIR__ . '/_head.php';
?>
<div class="head-row"><h1>Formulieren &amp; inzendingen</h1><?php if ($inzendingen): ?><a class="btn ghost" href="?actie=export">CSV exporteren</a><?php endif; ?></div>
<p class="lead">Beheer mailinstellingen en lees ontvangen formulieren terug. Inzendingen worden niet in herstelversies gekopieerd.</p>
<?php if ($msg): ?><p class="msg <?= $ok ? 'ok' : 'err' ?>"><?= e($msg) ?></p><?php endif; ?>

<?php if ($gekozen): ?>
  <?php $gEmail = (string) ($gekozen['email'] ?? ''); $gIp = (string) ($gekozen['ip'] ?? ''); $isBlok = cms_formulier_is_geblokkeerd($gEmail, $gIp); ?>
  <div class="stack">
    <h2 class="groep"><?= e($formulieren[$gekozen['formulier'] ?? '']['naam'] ?? ($gekozen['formulier'] ?? 'Inzending')) ?> · <?= e(date('d-m-Y H:i', strtotime($gekozen['ontvangen_op'] ?? 'now'))) ?></h2>
    <table class="list inzending-detail">
      <?php foreach ((array) ($gekozen['velden'] ?? array()) as $naam => $waarde): ?>
      <tr><th scope="row"><?= e((string) (($gekozen['labels'][$naam] ?? '') ?: ucfirst(str_replace('_', ' ', $naam)))) ?></th><td><?= nl2br(e((string) $waarde)) ?></td></tr>
      <?php endforeach; ?>
    </table>
    <p class="hint">IP-adres: <?= e($gIp !== '' ? $gIp : 'onbekend') ?><?= $isBlok ? ' · geblokkeerd' : '' ?></p>
    <div class="actions">
      <a class="btn ghost" href="formulieren.php">Terug naar de lijst</a>
      <form method="post"><?= csrf_field() ?><input type="hidden" name="actie" value="status"><input type="hidden" name="id" value="<?= e($gekozen['id']) ?>"><button class="btn ghost" type="submit">Markeer als ongelezen</button></form>
      <?php if ($gEmail !== '' || $gIp !== ''): ?><form method="post"<?= $isBlok ? '' : ' data-confirm="Deze afzender blokkeren?"' ?>><?= csrf_field() ?><input type="hidden" name="actie" value="<?= $isBlok ? 'deblokkeren' : 'blokkeren' ?>"><input type="hidden" name="id" value="<?= e($gekozen['id']) ?>"><input type="hidden" name="email" value="<?= e($gEmail) ?>"><input type="hidden" name="ip" value="<?= e($gIp) ?>"><button class="btn <?= $isBlok ? 'ghost' : 'blokkeer' ?>" type="submit"><?= $isBlok ? 'Blokkade opheffen' : 'Afzender blokkeren' ?></button></form><?php endif; ?>
      <?php if (!$isBlok && ($gEmail !== '' || $gIp !== '')): ?><form method="post" data-confirm="Als spam markeren en deze afzender blokkeren?"><?= csrf_field() ?><input type="hidden" name="actie" value="spam"><input type="hidden" name="id" value="<?= e($gekozen['id']) ?>"><button class="btn blokkeer" type="submit">Spam</button></form><?php endif; ?>
      <form method="post" data-confirm="Deze inzending definitief verwijderen?"><?= csrf_field() ?><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="id" value="<?= e($gekozen['id']) ?>"><button class="btn btn-danger" type="submit">Verwijderen</button></form>
    </div>
  </div>
<?php else: ?>
  <form method="post" class="stack"><?= csrf_field() ?><input type="hidden" name="actie" value="instellingen"><input type="hidden" name="formulier" value="contact"><h2 class="groep">Contactformulier</h2>
    <label class="field"><span>Naam</span><input type="text" name="naam" value="<?= e($contact['naam']) ?>" required></label>
    <label class="field-check"><input type="checkbox" name="actief" value="1"<?= !empty($contact['actief']) ? ' checked' : '' ?>><span>Formulier actief</span></label>
    <label class="field"><span>Ontvangstadres</span><input type="email" name="mail_to" value="<?= e($contact['mail_to']) ?>"><small class="hint">Leeg betekent alleen opslaan in het CMS.</small></label>
    <label class="field"><span>BCC, optioneel</span><input type="email" name="mail_bcc" value="<?= e($contact['mail_bcc']) ?>"></label>
    <label class="field"><span>Afzenderadres</span><input type="email" name="mail_from" value="<?= e($contact['mail_from']) ?>"></label>
    <label class="field"><span>Afzendernaam</span><input type="text" name="mail_from_naam" value="<?= e($contact['mail_from_naam']) ?>"></label>
    <label class="field"><span>Onderwerp-prefix</span><input type="text" name="subject_prefix" value="<?= e($contact['subject_prefix']) ?>"></label>
    <label class="field-check"><input type="checkbox" name="autoresponder" value="1"<?= !empty($contact['autoresponder']) ? ' checked' : '' ?>><span>Bevestigingsmail naar de bezoeker</span></label>
    <div class="actions"><button class="btn">Instellingen opslaan</button></div>
  </form>

  <h2 class="dashboard-group">Inzendingen</h2>
  <?php if (count($formulieren) > 1): ?><p><a class="btn<?= $filter === '' ? '' : ' ghost' ?>" href="formulieren.php">Alle</a> <?php foreach ($formulieren as $sleutel => $def): ?><a class="btn<?= $filter === $sleutel ? '' : ' ghost' ?>" href="?formulier=<?= e(rawurlencode($sleutel)) ?>"><?= e($def['naam'] ?? $sleutel) ?></a> <?php endforeach; ?></p><?php endif; ?>
  <?php if (!$inzendingen): ?><div class="info-blok"><p>Er zijn nog geen inzendingen.</p></div><?php else: ?>
  <table class="list inzendingen-lijst"><thead><tr><th>Ontvangen</th><th>Formulier</th><th>Aanvrager</th><th>Status</th><th></th></tr></thead><tbody>
  <?php foreach ($inzendingen as $rij): if ($filter !== '' && ($rij['formulier'] ?? '') !== $filter) { continue; } ?>
    <tr class="<?= ($rij['status'] ?? 'nieuw') === 'nieuw' ? 'is-nieuw' : '' ?>"><td><?= e(date('d-m-Y H:i', strtotime($rij['ontvangen_op'] ?? 'now'))) ?></td><td><?= e($formulieren[$rij['formulier'] ?? '']['naam'] ?? ($rij['formulier'] ?? '')) ?></td><td><?= e(($rij['naam'] ?? '') !== '' ? $rij['naam'] : ($rij['email'] ?? '')) ?></td><td><span class="badge <?= ($rij['status'] ?? 'nieuw') === 'nieuw' ? 'wacht' : 'volgt' ?>"><?= e($rij['status'] ?? 'nieuw') ?></span></td><td><a class="btn mini" href="?id=<?= e(rawurlencode($rij['id'] ?? '')) ?>">Openen</a></td></tr>
  <?php endforeach; ?></tbody></table>
  <form method="post" data-confirm="Alle inzendingen ouder dan de bewaartermijn verwijderen?" style="margin-top:1rem"><?= csrf_field() ?><input type="hidden" name="actie" value="ouder"><button class="btn ghost" type="submit">Oude inzendingen verwijderen</button></form>
  <?php endif; ?>

  <?php if ($blokkade['emails'] || $blokkade['ips']): ?><h2 class="dashboard-group">Geblokkeerde afzenders</h2><table class="list"><?php foreach (array('emails' => 'E-mailadres', 'ips' => 'IP-adres') as $soort => $label): foreach ($blokkade[$soort] as $waarde): ?><tr><td><?= e($label) ?></td><td><?= e($waarde) ?></td><td><form method="post"><?= csrf_field() ?><input type="hidden" name="actie" value="deblokkeren"><input type="hidden" name="<?= $soort === 'emails' ? 'email' : 'ip' ?>" value="<?= e($waarde) ?>"><button class="btn mini ghost" type="submit">Opheffen</button></form></td></tr><?php endforeach; endforeach; ?></table><?php endif; ?>
<?php endif; ?>
<?php require __DIR__ . '/_foot.php'; ?>
