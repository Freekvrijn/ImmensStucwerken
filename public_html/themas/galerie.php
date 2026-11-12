<header class="galerie-kop"><div class="galerie-koprij"><nav aria-label="Navigatie links"><?php menulinks(0, 2); ?></nav><?php merk(true); ?><nav aria-label="Navigatie rechts"><?php menulinks(2); ?><a href="#contact">Contact</a></nav></div></header>
<main id="inhoud">
    <section class="galerie-hero"><div class="galerie-aanhef"><h1 data-veld="galerie_titel"><?= tekst('galerie_titel') ?></h1><p data-veld="home_intro"><?= tekst('home_intro') ?></p><a class="tekstlink" data-veld-link="home_cta_link" href="<?= e(veld($content,'home_cta_link')) ?>" data-veld="contact_knop"><?= tekst('contact_knop') ?></a></div><figure class="galerie-panorama"><?php foto('home_afbeelding','','Woonruimte met rustige wanden, sfeerbeeld',true); sfeerbijschrift(); ?></figure></section>
    <section class="galerie-stucwerk breed sectie" id="stucwerk"><div><h2 data-veld="stucwerk_titel"><?= tekst('stucwerk_titel') ?></h2><p data-veld="stucwerk_intro"><?= tekst('stucwerk_intro') ?></p></div><?php diensten(true); ?></section>
    <section class="galerie-projecten breed sectie" id="projecten"><div class="galerie-projectkop"><h2 data-veld="projecten_titel"><?= tekst('projecten_titel') ?></h2><p data-veld="projecten_intro"><?= tekst('projecten_intro') ?></p></div><div class="galerie-reportages"><?php foreach($projecten as $project): ?><div class="galerie-reportage"><?php projectkaart($project); ?><div class="reportage-tekst"><span><?= e($project['categorie'] ?? '') ?></span><h3><?= e($project['titel']) ?></h3><p><?= e($project['samenvatting'] ?? '') ?></p></div></div><?php endforeach; ?></div></section>
    <div class="galerie-werkwijze"><div class="breed sectie"><?php werkwijze(); ?></div></div>
    <div class="breed"><?php tekstrijen_toon('pagina-home'); ?></div>
    <div class="contactvlak"><div class="breed"><?php contactblok(); ?></div></div>
</main>
<?php sitevoet(); ?>
