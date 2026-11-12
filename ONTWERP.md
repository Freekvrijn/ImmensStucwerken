# Immens Stucwerken

Vier lokale, afzonderlijk opgebouwde ontwerpvoorstellen. Nog geen publicatie.

## Huisstijl en ontwerpplan

Bron: `bronmateriaal/logo-origineel.pdf`. Het bestaande logo is ongewijzigd naar PNG gerenderd.
Palet: diepblauw #174c85, helderblauw #73bdf2, marine #102e4d, zilver #b5b7b9, koelwit #f5f7f8 en wit #ffffff.

| Richting | Typografie | Indeling | Kenmerk |
| --- | --- | --- | --- |
| Atelier | Manrope | Tekst links, hoge foto rechts; diensten naast een detailfoto; rustige projectrij | Licht, precisie, binnenarchitectuur |
| Blauwdruk | Barlow Condensed + Manrope | Breed interieurbeeld met zelfstandig blauw tekstvlak; horizontale diensten; donker projectarchief | Stevig en ruimtelijk vanuit het blauwe beeldmerk |
| Galerie | Libre Baskerville + Manrope | Gecentreerde aanhef, panorama, verspringende fotoreportage, compacte diensten | Wooninterieur en afwerking als portfolio |
| Werkplaats | Barlow Condensed + Manrope | Vaste linkernavigatie; compacte werkkop; grote projecttegel naast dienstenlijst | Praktisch, herkenbaar vakbedrijf |

Schetsen:

```text
Atelier        Blauwdruk       Galerie         Werkplaats
logo    menu   logo    menu    menu logo menu  logo | kop
tekst | foto   foto [tekst]    gecentreerd     nav  | foto | diensten
foto | werk    dienstenrij    breed beeld     nav  | projectraster
projectrij    fotoarchief     foto   tekst    nav  | aanpak/contact
contact       contact        tekst   foto
                             contact
```

De voorstellen delen inhoud, merk en beheer, maar hebben verschillende paginatemplates, verhoudingen, navigatie en projectpresentaties. Geen generieke statistieken, reviews, fictieve gerealiseerde werken, decoratieve nummering of effecten. De fotografie is als sfeerbeeld gemarkeerd en is geen werk van Immens. Er is nog geen visuele beoordeling uitgevoerd; die ligt conform het centrale AGENTS.md bij de gebruiker.

## Lokaal bekijken

Vanuit deze projectmap: `php -S 127.0.0.1:8104 -t public_html router.php`.

- `/`: keuzeoverzicht
- `/atelier`, `/blauwdruk`, `/galerie`, `/werkplaats`: complete voorstellen
- `/beheer/`: eerste eigen beheeraccount instellen

De lokale router schakelt alleen voor deze ontwikkelserver de onderhoudsmodule uit. De uploadbestanden houden onderhoud aan. Open de site pas na configuratie via het dashboard. Publieke pagina's staan standaard op noindex.

## Beheer

De ongewijzigde CMS-kern is afkomstig uit `../cms-template/`. Teksten en vaste fotovakken staan onder Pagina-inhoud. De collectie heet Projecten: voeg een titel, categorie, omschrijving en omslagfoto toe. Projecten verschijnen automatisch in alle vier de voorstellen. Extra foto's kunnen via een extra pagina met fotoblokken worden toegevoegd; gebruik als slug `project-` gevolgd door de projectslug om de projectknop naar die reportage te laten wijzen. Zonder reportage opent de projectknop de omslagfoto en projectinformatie in een dialoog. Ook het tekstveld van de collectie is daar zichtbaar.

Het contactformulier slaat proefaanvragen op in de CMS-inbox. E-mail staat uit totdat de eigenaar zijn werkelijke mailgegevens invult. Er staan geen fictieve contactgegevens, ervaringsjaren of keurmerken op de site. Bezoekerslogging en externe IP-opzoeking zijn uitgeschakeld. Authenticatie begint leeg, zonder standaardaccount.

Voor livegang zijn echte diensten, werkgebied, contactgegevens, eigen foto's, privacytekst en e-mailconfiguratie nog door de eigenaar te bevestigen.

## Beelden en lettertypen

Tijdelijke interieurbeelden: Unsplash image IDs `photo-1600210492486-724fe5c67fb0`, `photo-1600607687920-4e2a09cf159d`, `photo-1600607687939-ce8a6c25118c`. Lokaal opgeslagen, geen externe beeldrequests. De eerste foto is gebruikt in het hoofdvak; de overige in project- en detailvakken. Dit zijn sfeerbeelden, geen portfolio van Immens.

Lettertypen lokaal opgeslagen vanuit Google Fonts: Manrope, Barlow Condensed en Libre Baskerville, met bijbehorende OFL-licenties in assets/fonts.

Het uploadarchief bevat alleen de websitebestanden die in de laatste wijzigingsronde zijn aangepast, met paden vanaf `public_html/`. De precieze inhoud staat in `UPLOAD-BESTANDEN.txt`. Bron-PDF, lokale router, tests, Git-placeholderbestanden en dit document zitten niet in het archief.

De foto's hebben op verzoek afgeronde hoeken van 14px in alle vier de ontwerpen en in het keuzeoverzicht. Logo, knoppen, formulieren en overige vlakken houden hun oorspronkelijke vorm.

## Technische verificatie

`python3 tests/integration.py` voert 138 controles uit in een tijdelijke kopie. Het controleert de vier ontwerpen, lokale assets, formulieropslag en -beveiliging, eerste beheeraccount, login en logout, rollen, uploads, projecten en reportages, flexibele blokken, navigatie, SEO, online/noindex, redirects, herstelversies, onderhoud en de afscherming van interne bestanden. Testaccounts en testinhoud blijven buiten het opgeleverde project. Ook PHP-lint, JSON-decode en JavaScript-syntax zijn gecontroleerd. De oorspronkelijke CMS-smoketest is geslaagd. Er is geen browser-, screenshot- of visuele controle uitgevoerd.
