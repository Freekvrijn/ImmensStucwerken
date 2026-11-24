<?php
require_once __DIR__ . '/_lib/site.php';
http_response_code(404);
$thema = 'blauwdruk'; $content = cms_content();
cms_public_boot();
documentkop('home','Pagina niet gevonden | Immens Stucwerken');
?>
<main class="breed sectie foutpagina" id="inhoud"><h1>Deze pagina is er niet.</h1><p>Ga terug naar de startpagina om verder te kijken.</p><a class="knop" href="/">Naar de startpagina</a></main>
<?php documentslot(); ?>
