# Immens Stucwerken

De website gaat volledig verder met Blauwdruk. De overige ontwerpen, de keuzeoverzichtspagina en het themamenu zijn verwijderd uit broncode, CMS en statische demo.

Blauwdruk is gekozen als startpagina. De negen aangeleverde foto's uit Downloads zijn met behoud van de oorspronkelijke beeldbestanden opgenomen onder `public_html/assets/img/projecten/`, met beschrijvende bestandsnamen en metadata in het CMS. Iedere foto staat als afzonderlijk fotovak in de projectcollectie. Er zijn geen projectlocaties, opdrachtgevers of uitvoeringsdatums ingevuld. Het Blauwdruk-overzicht gebruikt drie kolommen op brede schermen, twee op middelgrote schermen en één op mobiel. De bestaande dialoog toont de volledige foto zonder uitsnede. Het raam onder de kap is de hoofdfoto; het houten gebint is de detailfoto bij de werkwijze.

## Huisstijl en ontwerpplan

Bron: `bronmateriaal/logo-origineel.pdf`. Het bestaande logo is ongewijzigd naar PNG gerenderd.
Voor blauwe achtergronden is daarnaast `public_html/assets/img/logo-immens-op-blauw.png` toegevoegd: een transparante, lichte variant, gemaakt met de ingebouwde imagegen-tool. Deze wordt gebruikt op de blauwe achtergrond van Blauwdruk. De gebruikte prompt staat in `bronmateriaal/logo-op-blauw-prompt.txt`. Beide logo's zijn te wijzigen via de CMS-pagina Algemeen.
Palet: diepblauw #174c85, helderblauw #73bdf2, marine #102e4d, zilver #b5b7b9, koelwit #f5f7f8 en wit #ffffff.

Blauwdruk gebruikt Barlow Condensed en Manrope, een breed hoofdbeeld met een zelfstandig blauw tekstvlak, een horizontale dienstenrij en een donker projectoverzicht. De aangeleverde eigen foto's vervangen de oorspronkelijke sfeerfotografie. Er zijn geen fictieve reviews, projectlocaties of opdrachtgevers toegevoegd.

Er is geen visuele beoordeling door de agent uitgevoerd; die ligt conform het centrale AGENTS.md bij de gebruiker.

## Lokaal bekijken

Vanuit deze projectmap: `php -S 127.0.0.1:8104 -t public_html router.php`.

- `/`: Blauwdruk met eigen projectfotografie
- `/blauwdruk`: alias van de homepage
- `/privacy`: privacyverklaring
- `/beheer/`: eerste eigen beheeraccount instellen

Er is geen themakeuze meer. Navigatie, logo, contactformulier en footer verwijzen naar de homepage. De oude adressen van Atelier, Galerie en Werkplaats geven een 404. De bijbehorende PHP-templates zijn verwijderd. Bij handmatige upload op bestaande PHP-hosting staan de te verwijderen bestanden in `VERWIJDER-BESTANDEN.txt`; het uploadarchief kan bestanden niet zelf verwijderen.

De lokale router schakelt alleen voor deze ontwikkelserver de onderhoudsmodule uit. De uploadbestanden houden onderhoud aan. Open de site pas na configuratie via het dashboard. Publieke pagina's staan standaard op noindex.

## Beheer

De ongewijzigde CMS-kern is afkomstig uit `../cms-template/`. Teksten en vaste fotovakken staan onder Pagina-inhoud. De collectie heet Projecten: voeg een titel, categorie, omschrijving en omslagfoto toe. Projecten verschijnen automatisch in het projectoverzicht van Blauwdruk. Extra foto's kunnen via een extra pagina met fotoblokken worden toegevoegd; gebruik als slug `project-` gevolgd door de projectslug om de projectknop naar die reportage te laten wijzen. Zonder reportage opent de projectknop de omslagfoto en projectinformatie in een dialoog. Ook het tekstveld van de collectie is daar zichtbaar.

Het contactformulier slaat proefaanvragen op in de CMS-inbox. E-mail staat uit totdat de eigenaar zijn werkelijke mailgegevens invult. Er staan geen fictieve contactgegevens, ervaringsjaren of keurmerken op de site. Bezoekerslogging en externe IP-opzoeking zijn uitgeschakeld. Authenticatie begint leeg, zonder standaardaccount.

Voor livegang zijn echte diensten, werkgebied, contactgegevens, privacytekst en e-mailconfiguratie nog door de eigenaar te bevestigen.

## Beelden en lettertypen

De negen eigen foto's staan in `assets/img/projecten/`. De JPEG-inhoud is ongewijzigd overgenomen uit Downloads; iedere foto heeft een beschrijvende bestandsnaam en CMS-metadata. De collectie bevat geen verzonnen datums of locaties. De oude tijdelijke interieurbeelden blijven als ongebruikte assets en fallback beschikbaar: Unsplash image IDs `photo-1600210492486-724fe5c67fb0`, `photo-1600607687920-4e2a09cf159d`, `photo-1600607687939-ce8a6c25118c`. Alle beelden zijn lokaal opgeslagen, zonder externe beeldrequests.

Lettertypen lokaal opgeslagen vanuit Google Fonts: Manrope en Barlow Condensed, met bijbehorende OFL-licenties in assets/fonts.

Het uploadarchief bevat alleen de websitebestanden die in de laatste wijzigingsronde zijn aangepast, met paden vanaf `public_html/`. De precieze inhoud staat in `UPLOAD-BESTANDEN.txt`. Bron-PDF, lokale router, tests, Git-placeholderbestanden en dit document zitten niet in het archief.

De foto's hebben op verzoek afgeronde hoeken van 14px op de website. Logo, knoppen, formulieren en overige vlakken houden hun oorspronkelijke vorm.

## Technische verificatie

`python3 tests/integration.py` voert 128 technische controles uit in een tijdelijke kopie. Het controleert Blauwdruk, de verwijderde ontwerpen en het ontbreken van de themakeuze, lokale assets, formulieropslag en -beveiliging, eerste beheeraccount, login en logout, rollen, uploads, projecten en reportages, flexibele blokken, navigatie, SEO, online/noindex, redirects, herstelversies, onderhoud en de afscherming van interne bestanden. Testaccounts en testinhoud blijven buiten het opgeleverde project. Ook PHP-lint, JSON-decode en JavaScript-syntax zijn gecontroleerd. De oorspronkelijke CMS-smoketest is geslaagd. Er is geen browser-, screenshot- of visuele controle uitgevoerd.
