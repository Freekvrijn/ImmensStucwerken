(function () {
  function beheerUrl(bestand) {
    var basis = window.CMS_BEHEER_BASE || '/beheer/';
    return basis.replace(/\/?$/, '/') + bestand;
  }
  document.addEventListener('DOMContentLoaded', function () {
    var modal = document.querySelector('[data-rij-modal]');
    if (!modal) { return; }
    var form = modal.querySelector('[data-rij-form]');
    var status = modal.querySelector('[data-rij-status]');
    var opslaan = modal.querySelector('[data-rij-opslaan]');
    var zone = null;
    var rijId = '';
    var naId = '';
    var terugFocus = null;
    var bezig = false;
    var opslagSleutel = 'cms-rij-herladen:' + location.pathname + location.search;

    function veld(naam) { return form.elements.namedItem(naam); }
    function voorbeeld(naam) {
      var vak = modal.querySelector('[data-rij-voorbeeld="' + naam + '"]');
      vak.innerHTML = veld(naam).value;
      if (!vak.textContent.trim()) { vak.textContent = 'Nog geen tekst ingevuld'; }
    }
    function indelingTonen() {
      var indeling = veld('indeling').value;
      modal.querySelector('[data-rij-kadervelden]').hidden = indeling !== 'kader';
      modal.querySelector('[data-rij-afbeeldingvelden]').hidden = indeling !== 'afbeelding';
      modal.querySelector('[data-rij-kaartvelden]').hidden = indeling !== 'kaarten';
    }

    // --- Afbeeldingen (tekst met afbeelding / blokken naast elkaar) ---------
    var KAART_VELDEN = ['afbeelding', 'titel', 'tekst', 'link_tekst', 'link_url'];
    function fotoTonen(vak) {
      var pad = vak.querySelector('input[type=hidden]').value;
      var img = vak.querySelector('[data-rij-foto-voorbeeld]');
      img.hidden = !pad;
      if (pad) { img.src = pad; }
      vak.querySelector('[data-rij-foto-weg]').hidden = !pad;
      vak.querySelector('[data-rij-foto-status]').textContent = '';
    }
    function alleFotosTonen() { modal.querySelectorAll('[data-rij-foto]').forEach(fotoTonen); }
    modal.querySelectorAll('[data-rij-foto]').forEach(function (vak) {
      var invoer = vak.querySelector('[data-rij-foto-bestand]');
      var melding = vak.querySelector('[data-rij-foto-status]');
      invoer.addEventListener('change', async function () {
        var bestand = invoer.files && invoer.files[0];
        invoer.value = '';
        if (!bestand || !zone) { return; }
        melding.textContent = 'Uploaden…';
        opslaan.disabled = true;
        var data = new FormData();
        data.append('csrf', document.body.getAttribute('data-inl-csrf') || '');
        data.append('zone', zone.getAttribute('data-rij-zone'));
        data.append('bestand', bestand);
        try {
          var response = await fetch(beheerUrl('rij-afbeelding-ajax.php'), { method: 'POST', body: data, credentials: 'same-origin' });
          var json = await response.json();
          if (!response.ok || !json.ok) { throw new Error(json.fout || 'Uploaden mislukte.'); }
          vak.querySelector('input[type=hidden]').value = json.pad;
          fotoTonen(vak);
        } catch (error) {
          melding.textContent = error.message || 'Uploaden mislukte.';
        }
        opslaan.disabled = false;
      });
      vak.querySelector('[data-rij-foto-weg]').addEventListener('click', function () {
        vak.querySelector('input[type=hidden]').value = '';
        fotoTonen(vak);
      });
    });
    function openen(knop, bestaandeRij) {
      zone = knop.closest('[data-rij-zone]');
      terugFocus = knop;
      form.reset();
      rijId = bestaandeRij ? bestaandeRij.id : '';
      naId = knop.getAttribute('data-rij-toevoegen') || '';
      var data = bestaandeRij || { indeling: 'tekst', achtergrond: 'paper', html: '', kader_html: '' };
      ['bovenkop', 'titel', 'html', 'kader_html', 'knop_tekst', 'knop_url'].forEach(function (naam) { veld(naam).value = data[naam] || ''; });
      veld('indeling').value = data.indeling || 'tekst';
      veld('achtergrond').value = data.achtergrond || 'paper';
      veld('titel_niveau').value = data.titel_niveau === 'h3' ? 'h3' : 'h2';
      veld('afbeelding').value = data.afbeelding || '';
      veld('afbeelding_alt').value = data.afbeelding_alt || '';
      veld('afbeelding_positie').value = data.afbeelding_positie || 'rechts';
      var kaarten = Array.isArray(data.kaarten) ? data.kaarten : [];
      for (var k = 1; k <= 3; k++) {
        KAART_VELDEN.forEach(function (naam) { veld('kaart' + k + '_' + naam).value = (kaarten[k - 1] && kaarten[k - 1][naam]) || ''; });
      }
      alleFotosTonen();
      modal.querySelector('[data-rij-tekstvelden]').hidden = rijId === 'bestaand';
      modal.querySelector('#rij-modal-titel').textContent = rijId === 'bestaand' ? 'Knop bij deze tekstrij' : (rijId ? 'Tekstrij bewerken' : 'Tekstrij toevoegen');
      status.textContent = '';
      indelingTonen();
      voorbeeld('html');
      voorbeeld('kader_html');
      modal.hidden = false;
      (rijId === 'bestaand' ? veld('knop_tekst') : veld('titel')).focus();
    }
    function sluiten() {
      if (bezig) { return; }
      modal.hidden = true;
      if (terugFocus) { terugFocus.focus(); }
    }

    async function wijzigen(gekozenZone, waarden, statusVak) {
      if (bezig) { return; }
      bezig = true;
      opslaan.disabled = true;
      statusVak.textContent = 'Opslaan…';
      var data = new FormData();
      data.append('csrf', document.body.getAttribute('data-inl-csrf') || '');
      data.append('zone', gekozenZone.getAttribute('data-rij-zone'));
      data.append('revisie', gekozenZone.getAttribute('data-rij-revisie'));
      Object.keys(waarden).forEach(function (key) { data.append(key, waarden[key]); });
      try {
        var response = await fetch(beheerUrl('rij-opslaan-ajax.php'), { method: 'POST', body: data, credentials: 'same-origin' });
        var json = await response.json();
        if (!response.ok || !json.ok) { throw new Error(json.fout || 'Opslaan mislukte.'); }
        try {
          sessionStorage.setItem(opslagSleutel, JSON.stringify({
            zone: gekozenZone.getAttribute('data-rij-zone'), id: json.id, scroll: window.scrollY
          }));
        } catch (_) { /* Opslaan blijft werken als browseropslag is uitgeschakeld. */ }
        location.reload();
      } catch (error) {
        statusVak.textContent = error.message || 'Opslaan mislukte. Controleer de verbinding.';
        bezig = false;
        opslaan.disabled = false;
      }
    }

    document.addEventListener('click', function (event) {
      var knop = event.target.closest('[data-rij-toevoegen], [data-rij-bewerken], [data-rij-verwijderen], [data-rij-herstellen]');
      if (!knop || bezig) { return; }
      var gekozenZone = knop.closest('[data-rij-zone]');
      if (!gekozenZone) { return; }
      var rij = knop.closest('[data-rij-id]');
      if (knop.hasAttribute('data-rij-toevoegen')) { openen(knop); }
      if (knop.hasAttribute('data-rij-bewerken')) { openen(knop, JSON.parse(rij.getAttribute('data-rij-inhoud'))); }
      if (knop.hasAttribute('data-rij-verwijderen')) {
        var naam = rij.querySelector('.rij-acties > span').textContent;
        if (!window.confirm('De tekstrij “' + naam + '” verwijderen? Je kunt dit terugdraaien via Beheer → Versies & back-up.')) { return; }
        wijzigen(gekozenZone, { actie: 'verwijderen', id: rij.getAttribute('data-rij-id') }, gekozenZone.querySelector('.rij-zone-status'));
      }
      if (knop.hasAttribute('data-rij-herstellen')) {
        wijzigen(gekozenZone, { actie: 'herstellen' }, gekozenZone.querySelector('.rij-zone-status'));
      }
    });
    veld('indeling').addEventListener('change', indelingTonen);
    modal.querySelectorAll('[data-rij-sluiten]').forEach(function (knop) { knop.addEventListener('click', sluiten); });
    modal.querySelectorAll('[data-rij-opmaak]').forEach(function (knop) {
      knop.addEventListener('click', function () {
        var naam = knop.getAttribute('data-rij-opmaak');
        modal.hidden = true;
        window.cmsTekstEditor({
          titel: naam === 'html' ? 'Tekst van de rij' : 'Tekst in het kader',
          html: veld(naam).value,
          koppen: true,
          opslaan: function (html) { veld(naam).value = html; voorbeeld(naam); },
          sluiten: function () { modal.hidden = false; knop.focus(); }
        });
      });
    });
    form.addEventListener('submit', function (event) {
      event.preventDefault();
      var waarden = { actie: 'opslaan', id: rijId, na: naId };
      ['bovenkop', 'titel', 'titel_niveau', 'html', 'kader_html', 'knop_tekst', 'knop_url', 'indeling', 'achtergrond',
        'afbeelding', 'afbeelding_alt', 'afbeelding_positie'].forEach(function (naam) { waarden[naam] = veld(naam).value; });
      var kaarten = [];
      for (var k = 1; k <= 3; k++) {
        var kaart = {};
        KAART_VELDEN.forEach(function (naam) { kaart[naam] = veld('kaart' + k + '_' + naam).value.trim(); });
        if (KAART_VELDEN.some(function (naam) { return kaart[naam] !== ''; })) { kaarten.push(kaart); }
      }
      waarden.kaarten = JSON.stringify(kaarten);
      wijzigen(zone, waarden, status);
    });
    document.addEventListener('keydown', function (event) {
      if (modal.hidden) { return; }
      if (event.key === 'Escape') { event.preventDefault(); sluiten(); }
      if (event.key === 'Tab') {
        var knoppen = Array.from(form.querySelectorAll('input, select, button, [tabindex]')).filter(function (el) { return !el.disabled && el.getClientRects().length; });
        var eerste = knoppen[0];
        var laatste = knoppen[knoppen.length - 1];
        if (event.shiftKey && document.activeElement === eerste) { event.preventDefault(); laatste.focus(); }
        if (!event.shiftKey && document.activeElement === laatste) { event.preventDefault(); eerste.focus(); }
      }
    });

    // Na het opslaan blijft de gebruiker bij dezelfde rij in de bewerkstand.
    try {
      var herstel = JSON.parse(sessionStorage.getItem(opslagSleutel) || 'null');
      sessionStorage.removeItem(opslagSleutel);
      if (herstel) {
        document.querySelector('[data-inl-toggle]').click();
        requestAnimationFrame(function () {
          var doel = document.getElementById('tekstrij-' + herstel.zone + '-' + herstel.id);
          if (doel) { doel.scrollIntoView({ block: 'center' }); }
          else { window.scrollTo(0, herstel.scroll); }
        });
      }
    } catch (_) { /* De editor blijft zonder browseropslag bruikbaar. */ }
  });
})();
