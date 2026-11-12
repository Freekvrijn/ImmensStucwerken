<?php
/** Eén overzicht voor vaste en via het CMS aangemaakte pagina's. */
require_once __DIR__ . '/../_lib/auth.php';
require_once __DIR__ . '/../_lib/helpers.php';
require_once __DIR__ . '/../_lib/cms.php';
require_once __DIR__ . '/../_lib/velden.php';
require_once __DIR__ . '/../_lib/paginas.php';
require_once __DIR__ . '/../_lib/eigen_paginas.php';
auth_boot();
require_login();
if (!mag('content') && !mag('seo')) { http_response_code(403); exit('Geen toegang.'); }

$register = site_pagina_register();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $actie = (string) ($_POST['actie'] ?? '');
    $sleutel = (string) ($_POST['pagina'] ?? '');
    $flash = array('ok' => false, 'tekst' => '');
    if (!csrf_check()) {
        $flash['tekst'] = 'Sessie verlopen, probeer het opnieuw.';
    } elseif (!isset($register[$sleutel])) {
        $flash['tekst'] = 'Onbekende pagina.';
    } elseif ($actie === 'google' && mag('seo')) {
        $status = pagina_status_alles();
        $wasIndexeerbaar = pagina_is_indexeerbaar($sleutel);
        if ($wasIndexeerbaar) { $status[$sleutel]['noindex'] = true; } else { unset($status[$sleutel]['noindex']); }
        if (empty($status[$sleutel])) { unset($status[$sleutel]); }
        if (save_json('paginastatus.json', $status)) {
            sitemap_regenereren();
            $flash = array('ok' => true, 'tekst' => $wasIndexeerbaar ? 'Pagina is verborgen voor zoekmachines.' : 'Pagina is weer indexeerbaar.');
        } else { $flash['tekst'] = 'Opslaan mislukte.'; }
    } elseif (!is_admin()) {
        $flash['tekst'] = 'Alleen een beheerder kan pagina’s aan- of uitzetten en verwijderen.';
    } elseif (!empty($register[$sleutel]['kern'])) {
        $flash['tekst'] = 'Deze kernpagina staat altijd online.';
    } elseif ($actie === 'online') {
        $status = pagina_status_alles();
        $status[$sleutel]['actief'] = !pagina_is_actief($sleutel);
        if (save_json('paginastatus.json', $status)) {
            sitemap_regenereren();
            $flash = array('ok' => true, 'tekst' => !empty($status[$sleutel]['actief']) ? 'Pagina staat online.' : 'Pagina staat uit.');
        } else { $flash['tekst'] = 'Opslaan mislukte.'; }
    } elseif ($actie === 'verwijderen' && !empty($register[$sleutel]['eigen'])) {
        $slug = (string) $register[$sleutel]['url'];
        if (pagina_is_actief($sleutel)) {
            $flash['tekst'] = 'Zet de pagina eerst uit.';
        } elseif (trim((string) ($_POST['bevestig'] ?? '')) !== $slug) {
            $flash['tekst'] = 'Typ de exacte URL-naam ter bevestiging.';
        } else {
            $fout = eigen_pagina_verwijderen($slug);
            $flash = array('ok' => $fout === '', 'tekst' => $fout === '' ? 'Pagina verwijderd; een back-up staat in data/verwijderde-paginas/.' : $fout);
            sitemap_regenereren();
        }
    } else {
        $flash['tekst'] = 'Onbekende of niet toegestane actie.';
    }
    $_SESSION['paginas_flash'] = $flash;
    header('Location: paginas.php' . ($sleutel !== '' ? '#p-' . rawurlencode($sleutel) : ''));
    exit;
}
$flash = $_SESSION['paginas_flash'] ?? null;
unset($_SESSION['paginas_flash']);

$pageTitle = 'Pagina\'s';
require __DIR__ . '/_head.php';
?>
<section class="paginas-breed">
<div class="head-row">
  <h1>Pagina&rsquo;s</h1>
  <div class="paginas-knoppen">
    <?php if (mag('content')): ?><a class="btn ghost" href="kernteksten.php?pagina=algemeen">Algemene teksten</a><?php endif; ?>
    <?php if (mag('content') && cms_module_aan('navigatie')): ?><a class="btn ghost" href="navigatie.php">Menu indelen</a><?php endif; ?>
    <?php if (mag('content') && cms_module_aan('eigen_paginas')): ?><a class="btn" href="extra-paginas.php?nieuw=1">+ Nieuwe pagina</a><?php endif; ?>
  </div>
</div>
<p class="lead">Bewerk inhoud, zet pagina&rsquo;s online of uit en regel indexering vanuit één overzicht. Vaste pagina&rsquo;s blijven bestanden van de website; alleen extra CMS-pagina&rsquo;s kunnen hier worden verwijderd.</p>
<?php if ($flash): ?><p class="msg <?= !empty($flash['ok']) ? 'ok' : 'err' ?>"><?= e($flash['tekst']) ?></p><?php endif; ?>

<table class="list paginas-tabel">
  <thead><tr><th>Pagina</th><th>Inhoud</th><th>Online</th><th>Zoekmachines</th><th>Menu / verwijderen</th></tr></thead>
  <tbody>
  <?php foreach ($register as $sleutel => $pagina):
      $extra = !empty($pagina['eigen']);
      $online = pagina_is_actief($sleutel);
      $indexeerbaar = pagina_is_indexeerbaar($sleutel);
      $eigen = $extra ? eigen_pagina($pagina['url']) : null;
      $publiekeLink = cms_publieke_link($pagina['url']); ?>
    <tr id="p-<?= e($sleutel) ?>" class="<?= $online ? '' : 'is-uit' ?>">
      <td><strong><?= e($pagina['label']) ?></strong><?php if ($extra): ?> <span class="badge categorie">extra</span><?php endif; ?><br><code>/<?= e($pagina['url']) ?></code><?php if ($publiekeLink !== ''): ?> <a class="hint" href="<?= e($publiekeLink) ?>" target="_blank" rel="noopener">bekijk &uarr;</a><?php endif; ?></td>
      <td class="paginas-inhoud" data-kop="Inhoud">
        <?php if ($extra && mag('content')): ?><a class="btn mini ghost" href="extra-paginas.php?bewerk=<?= e(rawurlencode($pagina['url'])) ?>">Bewerken</a>
        <?php elseif (mag('content')): ?><a class="btn mini ghost" href="kernteksten.php?pagina=<?= e(rawurlencode($sleutel)) ?>">Teksten</a><?php endif; ?>
        <?php if (mag('seo')): ?><a class="btn mini ghost" href="seo.php?pagina=<?= e(rawurlencode($sleutel)) ?>">SEO</a><?php endif; ?>
      </td>
      <td data-kop="Online">
        <span class="badge <?= $online ? 'volgt' : 'wacht' ?>"><?= !empty($pagina['kern']) ? 'altijd' : ($online ? 'online' : 'uit') ?></span>
        <?php if (is_admin() && empty($pagina['kern'])): ?><form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="actie" value="online"><input type="hidden" name="pagina" value="<?= e($sleutel) ?>"><button class="btn mini ghost" type="submit"><?= $online ? 'Zet uit' : 'Zet online' ?></button></form><?php endif; ?>
      </td>
      <td data-kop="Zoekmachines">
        <span class="badge <?= $indexeerbaar ? 'volgt' : 'wacht' ?>"><?= $indexeerbaar ? 'indexeren' : 'noindex' ?></span>
        <?php if (mag('seo')): ?><form method="post" class="inline-form"<?= $sleutel === 'home' && $indexeerbaar ? ' data-confirm="De homepage verbergen voor zoekmachines?"' : '' ?>><?= csrf_field() ?><input type="hidden" name="actie" value="google"><input type="hidden" name="pagina" value="<?= e($sleutel) ?>"><button class="btn mini ghost" type="submit"><?= $indexeerbaar ? 'Verbergen' : 'Indexeren' ?></button></form><?php endif; ?>
      </td>
      <td data-kop="Menu / verwijderen">
        <span class="hint"><?= $eigen ? e(EIGEN_MENU_PLEKKEN[$eigen['menu']] ?? 'Niet in menu') : 'via Navigatie' ?></span>
        <?php if (is_admin() && $extra && !$online): ?>
        <details class="verwijder-details"><summary class="btn mini ghost btn-danger">Verwijderen&hellip;</summary><form method="post" class="verwijder-form"><?= csrf_field() ?><input type="hidden" name="actie" value="verwijderen"><input type="hidden" name="pagina" value="<?= e($sleutel) ?>"><label>Typ <code><?= e($pagina['url']) ?></code><input type="text" name="bevestig" required></label><button class="btn mini btn-danger" type="submit">Definitief verwijderen</button></form></details>
        <?php endif; ?>
      </td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</section>
<?php require __DIR__ . '/_foot.php'; ?>
