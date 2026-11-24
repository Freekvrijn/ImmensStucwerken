<?php sitekop(); ?>
<main id="inhoud" class="blauwdruk-home">
    <section class="blauwdruk-hero" aria-labelledby="home-titel">
        <figure><?php foto('home_afbeelding','','Stucwerk rondom een raam onder een schuin dak',true); sfeerbijschrift(); ?></figure>
        <div class="blauwdruk-intro">
            <p class="vakregel">Immens Stucwerken</p>
            <h1 id="home-titel" data-veld="blauwdruk_titel"><?= tekst('blauwdruk_titel') ?></h1>
            <p class="intro" data-veld="home_intro"><?= tekst('home_intro') ?></p>
            <div class="blauwdruk-hero-acties"><a class="knop" data-veld-link="home_cta_link" href="<?= e(veld($content,'home_cta_link')) ?>" data-veld="contact_knop"><?= tekst('contact_knop') ?></a><a class="tekstlink" href="#projecten">Bekijk ons werk</a></div>
        </div>
    </section>
    <section class="blauwdruk-welkom sectie" id="over-immens" aria-labelledby="intro-titel">
        <div class="breed blauwdruk-welkom-binnen">
            <figure><?php foto('intro_afbeelding','','Gestucte wand met diepe raamopeningen'); ?></figure>
            <div><p class="vakregel">Aangenaam, Immens Stucwerken</p><h2 id="intro-titel" data-veld="intro_titel"><?= tekst('intro_titel') ?></h2><p class="welkom-tekst" data-veld="intro_tekst"><?= tekst('intro_tekst') ?></p>
                <ul class="blauwdruk-kenmerken"><li data-veld="intro_punt1"><?= tekst('intro_punt1') ?></li><li data-veld="intro_punt2"><?= tekst('intro_punt2') ?></li><li data-veld="intro_punt3"><?= tekst('intro_punt3') ?></li></ul>
                <a class="tekstlink" href="#werkwijze">Zo pakken we je project aan</a>
            </div>
        </div>
    </section>
    <section class="blauwdruk-diensten sectie" id="stucwerk" aria-labelledby="diensten-titel">
        <div class="breed"><div class="sectiekop"><div><p class="vakregel">Onze werkzaamheden</p><h2 id="diensten-titel" data-veld="stucwerk_titel"><?= tekst('stucwerk_titel') ?></h2></div><p data-veld="stucwerk_intro"><?= tekst('stucwerk_intro') ?></p></div><?php diensten(false, true); ?></div>
    </section>
    <section class="blauwdruk-offerteband" aria-labelledby="offerteband-titel"><div class="breed"><div><p class="vakregel">Van vierkante meters naar een prijs</p><h2 id="offerteband-titel" data-veld="offerteband_titel"><?= tekst('offerteband_titel') ?></h2><p data-veld="offerteband_tekst"><?= tekst('offerteband_tekst') ?></p></div><a class="knop" href="/offerte">Maak je offerte</a></div></section>
    <section class="blauwdruk-projecten sectie" id="projecten" aria-labelledby="projecten-titel"><div class="breed"><div class="sectiekop"><div><p class="vakregel">Een kijkje op het werk</p><h2 id="projecten-titel" data-veld="projecten_titel"><?= tekst('projecten_titel') ?></h2></div><p data-veld="projecten_intro"><?= tekst('projecten_intro') ?></p></div><p class="projecten-bladerhint">Veeg door de foto’s. Tik op een foto om deze te vergroten.</p><div class="blauwdruk-projectlijst" role="region" aria-label="Projectfoto’s, gebruik op mobiel de veegbeweging of de pijltjestoetsen om te bladeren" tabindex="0"><?php foreach($projecten as $project) projectkaart($project); ?></div></div></section>
    <section class="blauwdruk-aanpak-sectie sectie"><div class="blauwdruk-aanpak breed"><figure><?php foto('detail_afbeelding','','Wandafwerking tussen de houten balken onder de kap'); sfeerbijschrift('detail_afbeelding'); ?></figure><div><?php werkwijze(); ?></div></div></section>
    <div class="breed"><?php tekstrijen_toon('pagina-home'); ?></div>
    <div class="contactvlak"><div class="breed"><?php contactblok(); ?></div></div>
</main>
<?php sitevoet(); ?>
