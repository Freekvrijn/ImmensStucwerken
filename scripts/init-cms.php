<?php
// Maak uitsluitend ontbrekende lege runtimebestanden aan. Bestaande data blijft staan.
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__);
foreach (glob($root . '/configuratie/cms-startbestanden/*.json') as $source) {
    $target = $root . '/public_html/data/' . basename($source);
    if (!file_exists($target) && !copy($source, $target)) { fwrite(STDERR, 'Initialiseren mislukt.'); exit(1); }
}
echo "Ontbrekende CMS-startbestanden aangevuld. Bestaande bestanden behouden.\n";
