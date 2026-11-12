<?php sitekop(); ?>
<main id="inhoud">
    <section class="atelier-hero breed">
        <div class="atelier-intro"><p class="vakregel">Stucwerk voor jouw ruimte</p><h1 data-veld="atelier_titel"><?= tekst('atelier_titel') ?></h1><p class="intro" data-veld="home_intro"><?= tekst('home_intro') ?></p><div class="hero-acties"><a class="knop" data-veld-link="home_cta_link" href="<?= e(veld($content,'home_cta_link')) ?>" data-veld="contact_knop"><?= tekst('contact_knop') ?></a><a class="tekstlink" href="#projecten">Bekijk de projecten</a></div><div class="materiaalregel"><span>Wanden</span><span>Plafonds</span><span>Renovatie</span></div></div>
        <figure class="atelier-hoofdfoto"><?php foto('home_afbeelding', '', 'Lichte woonruimte als sfeerbeeld', true); sfeerbijschrift(); ?></figure>
    </section>
    <section class="atelier-stucwerk breed sectie" id="stucwerk"><figure><?php foto('detail_afbeelding','','Interieurdetail als sfeerbeeld'); sfeerbijschrift('detail_afbeelding'); ?></figure><div><h2 data-veld="stucwerk_titel"><?= tekst('stucwerk_titel') ?></h2><p data-veld="stucwerk_intro"><?= tekst('stucwerk_intro') ?></p><?php diensten(); ?></div></section>
    <section class="projectsectie sectie" id="projecten"><div class="breed"><div class="sectiekop"><h2 data-veld="projecten_titel"><?= tekst('projecten_titel') ?></h2><p data-veld="projecten_intro"><?= tekst('projecten_intro') ?></p></div><div class="projecten-raster"><?php foreach($projecten as $project) projectkaart($project); ?></div></div></section>
    <div class="breed sectie"><?php werkwijze(); tekstrijen_toon('pagina-home'); ?></div>
    <div class="contactvlak"><div class="breed"><?php contactblok(); ?></div></div>
</main>
<?php sitevoet(); ?>
