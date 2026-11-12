<?php sitekop(); ?>
<main id="inhoud">
    <section class="blauwdruk-hero"><figure><?php foto('home_afbeelding','','Wooninterieur als sfeerbeeld',true); sfeerbijschrift(); ?></figure><div class="blauwdruk-intro"><p class="vakregel">Immens Stucwerken</p><h1 data-veld="blauwdruk_titel"><?= tekst('blauwdruk_titel') ?></h1><p class="intro" data-veld="home_intro"><?= tekst('home_intro') ?></p><a class="knop" data-veld-link="home_cta_link" href="<?= e(veld($content,'home_cta_link')) ?>" data-veld="contact_knop"><?= tekst('contact_knop') ?></a></div><a class="blauwdruk-verder" href="#stucwerk">Van ondergrond tot afwerking <span aria-hidden="true">↓</span></a></section>
    <section class="blauwdruk-diensten breed sectie" id="stucwerk"><div class="sectiekop"><h2 data-veld="stucwerk_titel"><?= tekst('stucwerk_titel') ?></h2><p data-veld="stucwerk_intro"><?= tekst('stucwerk_intro') ?></p></div><?php diensten(); ?></section>
    <section class="blauwdruk-projecten sectie" id="projecten"><div class="breed"><div class="sectiekop"><h2 data-veld="projecten_titel"><?= tekst('projecten_titel') ?></h2><p data-veld="projecten_intro"><?= tekst('projecten_intro') ?></p></div><div class="blauwdruk-projectlijst"><?php foreach($projecten as $project) projectkaart($project); ?></div></div></section>
    <section class="blauwdruk-aanpak breed sectie"><figure><?php foto('detail_afbeelding','','Detail van een interieur'); sfeerbijschrift('detail_afbeelding'); ?></figure><div><?php werkwijze(); ?></div></section>
    <div class="breed"><?php tekstrijen_toon('pagina-home'); ?></div>
    <div class="contactvlak"><div class="breed"><?php contactblok(); ?></div></div>
</main>
<?php sitevoet(); ?>
