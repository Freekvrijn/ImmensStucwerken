<header class="keuze-kop"><?php merk(); ?><span>Vier richtingen voor Immens Stucwerken</span><a href="/beheer/">Naar het beheer</a></header>
<main class="keuze-main" id="inhoud">
    <div class="keuze-intro"><h1>Hetzelfde vakwerk.<br>Vier eigen gezichten.</h1><p>Ontdek welke uitstraling bij Immens past. Elk voorstel heeft een eigen opbouw, met vaste ruimte voor projecten en foto’s.</p></div>
    <div class="ontwerpkeuzes">
        <?php foreach (ontwerpen() as $key => $ontwerp): ?>
        <a class="ontwerpkeuze keuze-<?= e($key) ?>" href="/<?= e($key) ?>">
            <div class="ontwerp-schets" aria-hidden="true">
                <div class="schets-kop"><span>IMMENS</span><i></i><i></i><i></i></div>
                <div class="schets-binnen"><div class="schets-tekst"><b></b><b></b><em></em><em></em><span></span></div><img src="/assets/img/<?= $key === 'galerie' ? 'interieur' : 'woonruimte' ?>.jpg" alt="" loading="lazy"></div>
                <div class="schets-voet"><i></i><i></i><i></i></div>
            </div>
            <div class="keuze-tekst"><div><h2><?= e($ontwerp['naam']) ?></h2><span><?= e($ontwerp['karakter']) ?></span></div><p><?= e($ontwerp['tekst']) ?></p><strong>Bekijk dit ontwerp <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M4 12h15m-6-6 6 6-6 6"/></svg></strong></div>
        </a>
        <?php endforeach; ?>
    </div>
    <div class="keuze-toelichting"><p><strong>Al voorbereid op eigen werk.</strong> Projecten, foto’s en teksten worden vanuit één beheeromgeving bijgehouden.</p><p>De gebruikte interieurfoto’s zijn tijdelijke sfeerbeelden. Ze worden vervangen door foto’s van Immens.</p></div>
</main>
<footer class="keuze-voet"><span>Immens Stucwerken</span><span>Diepblauw, helderblauw en zilver uit het eigen logo.</span></footer>
