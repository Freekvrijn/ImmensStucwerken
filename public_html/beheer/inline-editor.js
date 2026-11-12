/**
 * Inline bewerken op een gekoppelde publieke CMS-pagina.
 *
 * Werking: elk element met data-veld="sleutel" krijgt een potloodje. Klikken
 * opent dezelfde soort opmaak-editor als in het beheer, gevuld met de huidige
 * inhoud van dát element. "Opslaan" stuurt alleen déze ene sleutel naar
 * beheer/veld-opslaan-ajax.php; bij succes wordt het element direct
 * bijgewerkt — geen paginaherlaad nodig.
 *
 * Bewust een eigen, kleine implementatie i.p.v. hergebruik van
 * beheer/rijk-editor.js: dat bestand verwacht de structuur van het
 * beheerformulier (verborgen <textarea>, los voorbeeldblok, formulier-post).
 * Hier bewerken we het echte, live element. Beide scripts draaien nooit op
 * dezelfde pagina, dus er is ook geen risico dat ze elkaar in de weg zitten.
 */
(function () {
  function beheerUrl(bestand) {
    var basis = window.CMS_BEHEER_BASE || '/beheer/';
    return basis.replace(/\/?$/, '/') + bestand;
  }
  var toggle = document.querySelector('[data-inl-toggle]');
  var modal  = document.querySelector('[data-inl-modal]');
  if (!toggle || !modal) { return; }
  var account = document.querySelector('[data-inl-account]');
  if (account) { account.prepend(toggle); }

  var editor            = modal.querySelector('[data-inl-editor]');
  var titel             = modal.querySelector('[data-inl-titel]');
  var linkVak           = modal.querySelector('[data-inl-link-vak]');
  var linkUrl           = modal.querySelector('[data-inl-link-url]');
  var linkBlank         = modal.querySelector('[data-inl-link-blank]');
  var kleurInput        = modal.querySelector('[data-inl-kleur]');
  var lettertypeSelect  = modal.querySelector('[data-inl-lettertype]');
  var grootteSelect     = modal.querySelector('[data-inl-grootte]');
  var statusRegel       = modal.querySelector('[data-inl-status]');
  var opslaanKnop       = modal.querySelector('[data-inl-opslaan]');
  // Alleen bij de tekst van tekstrijen: kiezen tussen tekst, H2 en H3.
  // Losse tekstvelden staan al in een kop of alinea en krijgen hem niet.
  var blokSelect        = modal.querySelector('[data-inl-blok]');

  var vereist = [editor, titel, linkVak, linkUrl, linkBlank, kleurInput, lettertypeSelect, grootteSelect, statusRegel, opslaanKnop];
  if (vereist.indexOf(null) !== -1) {
    console.error('Inline-editor: niet alle verwachte elementen gevonden, wordt niet geactiveerd.');
    return;
  }

  var huidigElement = null;
  var huidigSleutel = '';
  var opgeslagenSelectie = null;
  var opgeslagenSelectieOpmaak = null;
  var externeEditor = null;

  function csrfToken() {
    return document.body.getAttribute('data-inl-csrf') || '';
  }

  function potloodjeSvg() {
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z"/></svg>';
  }

  function potloodjeMaken(sleutel) {
    var knop = document.createElement('button');
    knop.type = 'button';
    knop.className = 'inl-potlood';
    knop.setAttribute('aria-label', 'Bewerken');
    knop.innerHTML = potloodjeSvg();
    knop.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      var el = document.querySelector('[data-veld="' + sleutel + '"]');
      if (el) { openen(el, sleutel); }
    });
    return knop;
  }

  // Een integratie kan bewerken beperken tot velden met een bepaald voorvoegsel
  // (body data-inl-alleen="eigen:"); andere velden, zoals de footer, krijgen dan geen potlood.
  var alleenVelden = document.body.getAttribute('data-inl-alleen') || '';
  function veldToegestaan(sleutel) { return !alleenVelden || String(sleutel).indexOf(alleenVelden) === 0; }

  document.querySelectorAll('[data-veld]').forEach(function (el) {
    if (!veldToegestaan(el.getAttribute('data-veld'))) { return; }
    el.classList.add('inl-veld');
    el.appendChild(potloodjeMaken(el.getAttribute('data-veld')));
  });

  function openen(el, sleutel) {
    huidigElement = el;
    huidigSleutel = sleutel;

    // Kopie zonder het potloodje zelf, zodat dat niet in de editor belandt.
    var kopie = el.cloneNode(true);
    var potlood = kopie.querySelector('.inl-potlood');
    if (potlood) { potlood.remove(); }

    editor.innerHTML = kopie.innerHTML.trim();
    titel.textContent = el.getAttribute('data-veld-label') || 'Tekst bewerken';
    statusRegel.textContent = '';
    toonEditorStand();
    modal.hidden = false;
    editor.focus();
  }

  function toonEditorStand(koppen) {
    linkVak.hidden = true;
    opslaanKnop.disabled = false;
    if (blokSelect) { blokSelect.hidden = !koppen; blokSelect.value = ''; }
  }

  function sluiten() {
    modal.hidden = true;
    huidigElement = null;
    huidigSleutel = '';
    var extern = externeEditor;
    externeEditor = null;
    if (extern && extern.sluiten) { extern.sluiten(); }
  }

  // De rij-editor gebruikt dezelfde opmaakbalk, maar slaat de tekst pas op
  // wanneer de gebruiker de hele rij bevestigt.
  window.cmsTekstEditor = function (opties) {
    externeEditor = opties;
    huidigElement = null;
    huidigSleutel = '';
    opgeslagenSelectie = null;
    opgeslagenSelectieOpmaak = null;
    editor.innerHTML = opties.html || '';
    titel.textContent = opties.titel || 'Tekst bewerken';
    statusRegel.textContent = '';
    toonEditorStand(!!opties.koppen);
    modal.hidden = false;
    editor.focus();
  };

  modal.querySelectorAll('[data-inl-sluiten]').forEach(function (el) {
    el.addEventListener('click', sluiten);
  });
  modal.querySelector('[data-inl-annuleren]').addEventListener('click', sluiten);

  document.addEventListener('keydown', function (ev) {
    if (!modal.hidden && ev.key === 'Escape') { ev.preventDefault(); ev.stopImmediatePropagation(); sluiten(); }
  });

  modal.querySelectorAll('[data-inl-cmd]').forEach(function (knop) {
    knop.addEventListener('click', function () {
      editor.focus();
      document.execCommand(knop.getAttribute('data-inl-cmd'), false, null);
    });
  });

  // Link toevoegen — eigen klein vakje i.p.v. document.execCommand('createLink'),
  // zodat "open in nieuw tabblad" ook kan.
  modal.querySelector('[data-inl-link]').addEventListener('click', function () {
    var selectie = window.getSelection();
    if (selectie.rangeCount > 0) { opgeslagenSelectie = selectie.getRangeAt(0); }
    linkUrl.value = '';
    linkBlank.checked = false;
    linkVak.hidden = false;
    linkUrl.focus();
  });
  modal.querySelector('[data-inl-link-annuleren]').addEventListener('click', function () {
    linkVak.hidden = true;
    editor.focus();
  });
  modal.querySelector('[data-inl-link-toepassen]').addEventListener('click', function () {
    var url = linkUrl.value.trim();
    if (url === '') { linkVak.hidden = true; return; }
    editor.focus();
    if (opgeslagenSelectie) {
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(opgeslagenSelectie);
    }
    document.execCommand('createLink', false, url);
    if (linkBlank.checked) {
      var selectie = window.getSelection();
      if (selectie.anchorNode) {
        var elx = selectie.anchorNode.nodeType === 1 ? selectie.anchorNode : selectie.anchorNode.parentElement;
        var a = elx ? elx.closest('a') : null;
        if (a) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener noreferrer'); }
      }
    }
    linkVak.hidden = true;
  });

  function bewaarSelectieVoorOpmaak() {
    var sel = window.getSelection();
    if (sel.rangeCount > 0 && editor.contains(sel.anchorNode)) {
      opgeslagenSelectieOpmaak = sel.getRangeAt(0);
    }
  }
  function herstelSelectieVoorOpmaak() {
    editor.focus();
    if (opgeslagenSelectieOpmaak) {
      var sel = window.getSelection();
      sel.removeAllRanges();
      sel.addRange(opgeslagenSelectieOpmaak);
    }
  }

  if (blokSelect) {
    blokSelect.addEventListener('mousedown', bewaarSelectieVoorOpmaak);
    blokSelect.addEventListener('change', function () {
      var waarde = blokSelect.value;
      blokSelect.value = '';
      if (waarde === '') { return; }
      herstelSelectieVoorOpmaak();
      document.execCommand('formatBlock', false, waarde);
    });
  }

  kleurInput.addEventListener('mousedown', bewaarSelectieVoorOpmaak);
  kleurInput.addEventListener('input', function () {
    herstelSelectieVoorOpmaak();
    document.execCommand('foreColor', false, kleurInput.value);
  });

  lettertypeSelect.addEventListener('mousedown', bewaarSelectieVoorOpmaak);
  lettertypeSelect.addEventListener('change', function () {
    var waarde = lettertypeSelect.value;
    lettertypeSelect.value = '';
    if (waarde === '') { return; }
    herstelSelectieVoorOpmaak();
    document.execCommand('fontName', false, waarde);
    editor.querySelectorAll('font[face]').forEach(function (f) {
      var span = document.createElement('span');
      span.style.fontFamily = f.getAttribute('face');
      while (f.firstChild) { span.appendChild(f.firstChild); }
      f.parentNode.replaceChild(span, f);
    });
  });

  grootteSelect.addEventListener('mousedown', bewaarSelectieVoorOpmaak);
  grootteSelect.addEventListener('change', function () {
    var waarde = grootteSelect.value;
    grootteSelect.value = '';
    if (waarde === '') { return; }
    herstelSelectieVoorOpmaak();
    document.execCommand('fontSize', false, '7');
    editor.querySelectorAll('font[size="7"]').forEach(function (f) {
      var span = document.createElement('span');
      span.style.fontSize = waarde;
      while (f.firstChild) { span.appendChild(f.firstChild); }
      f.parentNode.replaceChild(span, f);
    });
  });

  opslaanKnop.addEventListener('click', function () {
    if (externeEditor) {
      externeEditor.opslaan(editor.innerHTML.trim());
      sluiten();
      return;
    }
    if (!huidigElement || !huidigSleutel) { return; }
    var html = editor.innerHTML.trim();
    opslaanKnop.disabled = true;
    statusRegel.textContent = 'Opslaan…';

    var data = new FormData();
    data.append('veld', huidigSleutel);
    data.append('html', html);
    data.append('csrf', csrfToken());

    // Let op: dit script wordt geladen vanaf een publieke pagina (bv.
    // "site.nl/diensten"), dus het relatieve pad wordt bepaald door dié URL,
    // niet door de locatie van dit script zelf — vandaar de "beheer/"-prefix.
    fetch(beheerUrl('veld-opslaan-ajax.php'), { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) {
        return r.json().catch(function () { return null; });
      })
      .then(function (json) {
        opslaanKnop.disabled = false;
        if (!json || json.ok !== true) {
          statusRegel.textContent = (json && json.fout) ? json.fout : 'Opslaan mislukte.';
          return;
        }
        var potlood = huidigElement.querySelector('.inl-potlood');
        huidigElement.innerHTML = json.html || '';
        if (potlood) { huidigElement.appendChild(potlood); }
        sluiten();
      })
      .catch(function () {
        opslaanKnop.disabled = false;
        statusRegel.textContent = 'Opslaan mislukte (controleer de verbinding).';
      });
  });

  toggle.addEventListener('click', function () {
    var aan = document.body.classList.toggle('inl-bewerkmodus-aan');
    toggle.classList.toggle('is-actief', aan);
    toggle.setAttribute('aria-pressed', aan ? 'true' : 'false');
    toggle.querySelector('span').textContent = aan ? ' Bewerken: aan' : ' Bewerken';
  });

  // ---- Afbeeldingen -------------------------------------------------------
  // Los van de tekst-editor hierboven: geen opmaak-modal, gewoon een
  // bestandskiezer die direct uploadt en de <img> ter plekke vervangt.

  function fotoKnopMaken(sleutel) {
    var input = document.createElement('input');
    input.type = 'file';
    input.accept = 'image/jpeg,image/png,image/gif,image/webp';
    input.className = 'inl-foto-input';
    input.hidden = true;
    input.addEventListener('click', function (ev) { ev.stopPropagation(); });
    input.addEventListener('change', function () {
      var bestand = input.files && input.files[0];
      input.value = '';
      if (!bestand) { return; }
      fotoUploaden(sleutel, bestand, knop);
    });

    var knop = document.createElement('button');
    knop.type = 'button';
    knop.className = 'inl-foto-knop';
    knop.setAttribute('aria-label', 'Andere foto kiezen');
    knop.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" ' +
      'stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' +
      '<path d="M4 7h3l2-2h6l2 2h3v13H4z"/><circle cx="12" cy="13" r="3.5"/></svg>';
    knop.addEventListener('click', function (ev) {
      ev.preventDefault();
      ev.stopPropagation();
      input.click();
    });

    return { knop: knop, input: input };
  }

  function fotoUploaden(sleutel, bestand, knop) {
    var wrapper = document.querySelector('[data-veld-afbeelding="' + sleutel + '"]');
    if (!wrapper) { return; }
    var img = wrapper.querySelector('img');

    var status = document.createElement('span');
    status.className = 'inl-foto-status';
    status.textContent = 'Uploaden…';
    wrapper.appendChild(status);
    knop.disabled = true;

    var data = new FormData();
    data.append('veld', sleutel);
    data.append('bestand', bestand);
    data.append('csrf', csrfToken());

    fetch(beheerUrl('afbeelding-opslaan-ajax.php'), { method: 'POST', body: data, credentials: 'same-origin' })
      .then(function (r) { return r.json().catch(function () { return null; }); })
      .then(function (json) {
        knop.disabled = false;
        if (!json || json.ok !== true) {
          status.textContent = (json && json.fout) ? json.fout : 'Uploaden mislukte.';
          setTimeout(function () { status.remove(); }, 4000);
          return;
        }
        // Cache-buster: zonder ?v=... blijft de browser de oude foto tonen.
        if (img) { img.src = json.pad + '?v=' + Date.now(); }
        status.remove();
      })
      .catch(function () {
        knop.disabled = false;
        status.textContent = 'Uploaden mislukte (controleer de verbinding).';
        setTimeout(function () { status.remove(); }, 4000);
      });
  }

  document.querySelectorAll('[data-veld-afbeelding]').forEach(function (wrapper) {
    if (!veldToegestaan(wrapper.getAttribute('data-veld-afbeelding'))) { return; }
    wrapper.classList.add('inl-veld-afb');
    var paar = fotoKnopMaken(wrapper.getAttribute('data-veld-afbeelding'));
    wrapper.appendChild(paar.knop);
    wrapper.appendChild(paar.input);
  });

  // ---- Knop-/linkbestemmingen ----------------------------------------------
  // Simpeler dan de tekst-editor: geen opmaak, alleen een webadres. Het
  // potloodje wordt IN het <a>-element zelf gezet, dus de klik moet de
  // navigatie van die link tegenhouden (preventDefault/stopPropagation),
  // net als bij de foto-knoppen op een omklikbare afbeelding.
  var btnlinkModal = document.querySelector('[data-inl-btnlink-modal]');
  if (btnlinkModal) {
    var btnlinkInput   = btnlinkModal.querySelector('[data-inl-btnlink-input]');
    var btnlinkStatus  = btnlinkModal.querySelector('[data-inl-btnlink-status]');
    var btnlinkOpslaan = btnlinkModal.querySelector('[data-inl-btnlink-opslaan]');
    var huidigLinkElement = null;
    var huidigLinkSleutel = '';

    function linkPotloodjeMaken(sleutel) {
      var knop = document.createElement('span');
      knop.className = 'inl-potlood inl-potlood--link';
      knop.setAttribute('role', 'button');
      knop.setAttribute('tabindex', '0');
      knop.setAttribute('aria-label', 'Bestemming van deze knop bewerken');
      knop.innerHTML = potloodjeSvg();
      function openenViaKnop(ev) {
        ev.preventDefault();
        ev.stopPropagation();
        var el = document.querySelector('[data-veld-link="' + sleutel + '"]');
        if (el) { linkModalOpenen(el, sleutel); }
      }
      knop.addEventListener('click', openenViaKnop);
      knop.addEventListener('keydown', function (ev) {
        if (ev.key === 'Enter' || ev.key === ' ') { openenViaKnop(ev); }
      });
      return knop;
    }

    function linkModalOpenen(el, sleutel) {
      huidigLinkElement = el;
      huidigLinkSleutel = sleutel;
      btnlinkInput.value = el.getAttribute('href') || '';
      btnlinkStatus.textContent = '';
      btnlinkModal.hidden = false;
      btnlinkInput.focus();
    }

    function linkModalSluiten() {
      btnlinkModal.hidden = true;
      huidigLinkElement = null;
      huidigLinkSleutel = '';
    }

    btnlinkModal.querySelectorAll('[data-inl-btnlink-sluiten]').forEach(function (elx) {
      elx.addEventListener('click', linkModalSluiten);
    });
    btnlinkModal.querySelector('[data-inl-btnlink-annuleren]').addEventListener('click', linkModalSluiten);
    document.addEventListener('keydown', function (ev) {
      if (!btnlinkModal.hidden && ev.key === 'Escape') { linkModalSluiten(); }
    });

    btnlinkOpslaan.addEventListener('click', function () {
      if (!huidigLinkElement || !huidigLinkSleutel) { return; }
      var url = btnlinkInput.value.trim();
      btnlinkOpslaan.disabled = true;
      btnlinkStatus.textContent = 'Opslaan…';

      var data = new FormData();
      data.append('veld', huidigLinkSleutel);
      data.append('url', url);
      data.append('csrf', csrfToken());

      fetch(beheerUrl('link-opslaan-ajax.php'), { method: 'POST', body: data, credentials: 'same-origin' })
        .then(function (r) { return r.json().catch(function () { return null; }); })
        .then(function (json) {
          btnlinkOpslaan.disabled = false;
          if (!json || json.ok !== true) {
            btnlinkStatus.textContent = (json && json.fout) ? json.fout : 'Opslaan mislukte.';
            return;
          }
          // Sommige links staan dubbel op de pagina (bv. een link die zowel op
          // mobiel als desktop apart in de HTML staat, voor twee verschillende
          // lay-outs). Die delen dezelfde veldsleutel, dus werk ze allemaal in
          // één keer bij.
          document.querySelectorAll('[data-veld-link="' + huidigLinkSleutel + '"]').forEach(function (elx) {
            elx.setAttribute('href', json.url);
          });
          linkModalSluiten();
        })
        .catch(function () {
          btnlinkOpslaan.disabled = false;
          btnlinkStatus.textContent = 'Opslaan mislukte (controleer de verbinding).';
        });
    });

    document.querySelectorAll('[data-veld-link]').forEach(function (el) {
      el.classList.add('inl-veld-link');
      el.appendChild(linkPotloodjeMaken(el.getAttribute('data-veld-link')));
    });
  }
})();
