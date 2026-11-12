<?php
/**
 * Centrale configuratie van de herbruikbare CMS-template.
 *
 * Houd dit bestand vrij van wachtwoorden en sitespecifieke geheime sleutels.
 * De eerste beheerder stelt na installatie zelf een wachtwoord in via /beheer/.
 */

define('CMS_SITE_NAME', 'Immens Stucwerken');
define('CMS_SITE_URL', '');
define('CMS_DATA_DIR', dirname(__DIR__) . '/data');

// Zolang het CMS nog niet aan een website is gekoppeld, blijft de publieke link uit.
define('CMS_PUBLIEKE_SITE_AAN', true);

// Beheermodules. Een LLM kan ongebruikte branchespecifieke modules hier uitschakelen.
define('CMS_MODULE_PAGINAS', true);
define('CMS_MODULE_EIGEN_PAGINAS', true);
define('CMS_MODULE_TEKSTRIJEN', true);
define('CMS_MODULE_NAVIGATIE', true);
define('CMS_MODULE_SEO', true);
define('CMS_MODULE_MEDIA', true);
define('CMS_MODULE_REDIRECTS', true);
define('CMS_MODULE_FAQ', true);
define('CMS_MODULE_COLLECTIE', true);
define('CMS_MODULE_FORMULIEREN', true);
define('CMS_MODULE_TOEGANG', true);
define('CMS_MODULE_BEZOEKERS', false);
define('CMS_MODULE_GEOIP', true);
define('CMS_MODULE_ONDERHOUD', !defined('IMMENS_LOCAL_PREVIEW'));
define('CMS_MODULE_SECTIE_INDELING', true);

// De generieke collectie kan per site bijvoorbeeld Projecten, Diensten of Cases heten.
define('CMS_COLLECTIE_LABEL', 'Projecten');
define('CMS_COLLECTIE_ITEM_LABEL', 'project');

// Uploads: dezelfde bewezen techniek en limieten als het bron-CMS.
define('CMS_UPLOAD_DIR', 'assets/img/uploads');
define('CMS_UPLOAD_MAX_MB', 25);
define('CMS_UPLOAD_MAX', CMS_UPLOAD_MAX_MB * 1024 * 1024);
define('CMS_UPLOAD_MAX_AFMETING', 1600);

// Toegangsblokkering. Landherkenning werkt achter Cloudflare via CF-IPCountry.
define('CMS_TOEGANG_AAN', CMS_MODULE_TOEGANG);
define('CMS_ACHTER_CLOUDFLARE', false);

// Privacy: bewaart IP, land, pad en user-agent maximaal 30 dagen. Omdat deze
// template nog geen privacyverklaring heeft, moet een integrerende LLM dit
// expliciet controleren. Externe landopzoeking staat standaard uit; een lokale
// MaxMind/IP2Location-database of serverheader werkt wel.
define('CMS_BEZOEKERS_LOG', CMS_MODULE_BEZOEKERS);
define('CMS_BEZOEKERS_LAND_OPZOEKEN', false);

// Formulierinzendingen bevatten persoonsgegevens. Verwijder ze automatisch na
// maximaal deze termijn en neem ze nooit op in herstelversies of JSON-back-ups.
define('CMS_FORMULIER_BEWAAR_DAGEN', 90);

// Nieuwe losse installaties beginnen veilig in onderhoud. Bij integratie in een
// bestaande site moet data/onderhoud.json bewust op de bestaande livestatus
// worden gezet, zodat een update de website nooit onverwacht afsluit.
define('CMS_ONDERHOUD_STANDAARD', true);
// Een doelsite kan hier een eigen PHP-template definiëren. Leeg gebruikt de
// rustige ingebouwde onderhoudspagina uit _lib/onderhoud.php.
define('CMS_ONDERHOUD_TEMPLATE', '');

// Loginbeveiliging.
define('CMS_LOGIN_MAX_POGINGEN', 5);
define('CMS_LOGIN_VENSTER_SEC', 900);
