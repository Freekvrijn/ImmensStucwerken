<?php
/**
 * Werkt de MaxMind-database bij vanuit een cronjob (DirectAdmin » Cronjobs), bijv.
 * twee keer per week:  15 4 * * 2,5  /usr/local/bin/php /home/GEBRUIKER/domains/DOMEIN/public_html/_lib/geoip-cron.php
 * Niet nodig als u het beheer regelmatig opent: dan gebeurt het automatisch.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
require_once __DIR__ . '/geoip.php';
$fout = geoip_bijwerken();
if ($fout !== '') {
    fwrite(STDERR, 'GeoIP bijwerken mislukt: ' . $fout . "\n");
    exit(1);
}
$i = geoip_instellingen(); echo 'GeoIP bijgewerkt: ' . ($i['bron'] === 'ip2location' ? $i['i2l_pakket'] : $i['editie']) . "\n";
