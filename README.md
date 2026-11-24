# Immens Stucwerken

De website gebruikt uitsluitend het ontwerp Blauwdruk, met het bestaande logo en de blauwe huisstijl. De andere ontwerpvarianten en het themamenu zijn verwijderd, ook uit het CMS en de statische demo. Oude adressen van de vervallen ontwerpen geven een 404. `/blauwdruk` blijft als alias van de homepage beschikbaar.

Alle negen aangeleverde projectfoto's staan in de CMS-collectie en zijn afzonderlijk te openen. Twee van deze foto's worden ook als hoofd- en detailfoto gebruikt. Foto's hebben afgeronde hoeken; knoppen en overige vlakken behouden hun vorm.

De homepage heeft een directe contactbalk, een bedrijfsintroductie, drie diensten met eigen foto’s, een offertebalk en een uitgebreide footer, met inspiratie uit de opbouw van [Pijnappels](https://pijnappels-stukadoors.nl/). Alle nieuwe introductieteksten en dienstfoto’s zijn via het CMS aanpasbaar.

Online demo: https://freekvrijn.github.io/ImmensStucwerken/

## Lokaal offertesysteem

Het volledige systeem werkt op PHP 7.4 of nieuwer met de bestaande CMS-login. Er zijn geen nieuwe serverafhankelijkheden, database of maildienst nodig. Start de lokale server met het commando hieronder.

- `/offerte`: directe prijsberekening, klant- en projectgegevens, opslaan en PDF-download.
- `/beheer/offertes.php`: ontvangen offertes, status, verwijderen en PDF-download, alleen voor beheerders.
- `/beheer/offertes.php?tab=tarieven`: diensten toevoegen, verbergen of verwijderen; tarieven, vaste reiskosten, minimum werkbedrag, btw, geldigheid, voorwaarden en bedrijfsgegevens wijzigen.
- `/beheer/`: bij de eerste installatie zelf een gebruikersnaam en een wachtwoord van minimaal twaalf tekens instellen. Er is geen standaardwachtwoord. Bestaande accounts blijven behouden.

Starttarieven: €25/m² voor wanden en plafonds en €150 vaste reiskosten, exclusief btw. Stucwerk aan een door de bezoeker opgegeven woning ouder dan twee jaar wordt berekend met 9%, de andere pandkeuzes met 21%. De beheerder kan deze keuze uitschakelen en een vast tarief kiezen. De btw-verlegging uit de aangeleverde zakelijke factuur geldt niet automatisch voor consumentenoffertes. Er is geen automatische mailverzending of opdrachtbevestiging.

De server rekent met gehele centen en controleert hoeveelheden opnieuw. Een oude formulierpagina wordt geweigerd na een prijswijziging. Offertes bewaren de oorspronkelijke tarieven en voorwaarden; nieuwe prijzen veranderen eerdere offertes niet. Het nummer is doorlopend per jaar. Een aanvrager kan een offerte alleen in de eigen browsersessie downloaden; een beheerder kan alle offertes openen. Dubbel verzenden maakt geen tweede offerte en er geldt een limiet van vijf nieuwe offertes per IP-hash per uur.

Offertes staan privé in `public_html/data/offertes/` en blijven maximaal 90 dagen toegankelijk. Verlopen records worden bij het aanmaken of beheren van offertes opgeschoond. Offertes, teller, snelheidslog en inloggegevens zijn uitgesloten van Git en CMS-herstelversies. De instellingen hebben wel herstelversies. De bronfactuur en klantgegevens daaruit worden niet in de site opgenomen.

Voor latere plaatsing op een domein is PHP-hosting nodig. Gebruik HTTPS en zorg dat `data/`, `data/offertes/` en `data/versies/` schrijfbaar zijn voor PHP. Behoud bestaande accounts en offertedata bij updates. Vul de bedrijfs- en contactgegevens aan, controleer de starttarieven en voorwaarden en zet de site via het dashboard open. GitHub Pages kan alleen de calculator demonstreren; opslaan, PDF-uitgifte en het prijsbeheer vereisen de PHP-website.

Technische controles:

```sh
python3 tests/integration.py
php tests/offerte-rekenen.php
node tests/offerte-rekenen.mjs
```

## GitHub Pages

Publicatiebron: branch `main`, map `/docs`. Deze map bevat uitsluitend statische HTML en publieke assets. Het formulier is expliciet uitgeschakeld en het beheer toont uitleg over de demo. Navigatie, projectfoto-dialogen en de offerte-prijsberekening blijven werken. Offertes opslaan en PDF-download zijn in de statische demo uitgeschakeld. Geen PHP-bestanden, CMS-data of sessietokens worden naar de demo gekopieerd.

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
