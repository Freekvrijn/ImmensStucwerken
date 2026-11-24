# Immens Stucwerken

De website gebruikt uitsluitend het ontwerp Blauwdruk, met het bestaande logo en de blauwe huisstijl. De andere ontwerpvarianten en het themamenu zijn verwijderd, ook uit het CMS en de statische demo. Oude adressen van de vervallen ontwerpen geven een 404. `/blauwdruk` blijft als alias van de homepage beschikbaar.

Alle negen aangeleverde projectfoto's staan in de CMS-collectie en zijn afzonderlijk te openen. Twee van deze foto's worden ook als hoofd- en detailfoto gebruikt. Foto's hebben afgeronde hoeken; knoppen en overige vlakken behouden hun vorm.

Online demo: https://freekvrijn.github.io/ImmensStucwerken/

## GitHub Pages

Publicatiebron: branch `main`, map `/docs`. Deze map bevat uitsluitend statische HTML en publieke assets. Het formulier is expliciet uitgeschakeld en het beheer toont uitleg over de demo. Navigatie en projectfoto-dialogen blijven werken. Geen PHP-bestanden, CMS-data of sessietokens worden naar de demo gekopieerd.

Na wijzigingen de demo opnieuw genereren en samen met de broncode pushen:

```sh
php scripts/init-cms.php
python3 scripts/build-demo.py
git add .
git commit -m "Werk Blauwdruk en projectfotografie bij"
git push origin main
```

De export controleert routes, ankers, assets, uitgeschakelde formulieren en het ontbreken van backendbestanden. Er wordt geen visuele beoordeling uitgevoerd.

## Volledige PHP-website met CMS

De volledige broncode staat in `public_html/`, inclusief het gedeelde CMS voor projecten, foto's, teksten en pagina's. GitHub Pages voert dit CMS niet uit. Dit is bewust een online ontwerpdemonstratie; voor de definitieve website is PHP-hosting nodig.

Na een nieuwe checkout:

```sh
php scripts/init-cms.php
php -S 127.0.0.1:8104 -t public_html router.php
```

Open lokaal `/beheer/` om zelf het eerste account in te stellen. De initialisatie maakt alleen ontbrekende lege runtimebestanden aan en overschrijft geen bestaande data. Accounts, formulierinzendingen, uploads, bezoekerslogs en GeoIP-tokens worden niet gecommit. De voorbeeldbestanden in `configuratie/cms-startbestanden/` bevatten uitsluitend lege beginwaarden.

Op echte PHP-hosting blijft onderhoud aan totdat de eigenaar het via het dashboard uitzet. Bezoekerslogging en externe IP-opzoeking zijn uitgeschakeld. Contactgegevens, definitieve diensten, privacytekst en e-mailinstellingen moeten voor de definitieve livegang nog worden ingevuld. De aangeleverde foto's staan onder `assets/img/projecten/`; de oorspronkelijke sfeerbeelden blijven alleen beschikbaar als oude assets en als fallback bij een lege projectcollectie.

Technische CMS-controle: `python3 tests/integration.py`. Meer ontwerp- en beheerinformatie staat in [ONTWERP.md](ONTWERP.md).
