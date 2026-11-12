(function () {
  var modal        = document.querySelector('[data-rijk-modal]');
  if (!modal) { return; }
  var editor         = modal.querySelector('[data-rijk-editor]');
  var voorbeeldvak    = modal.querySelector('[data-rijk-voorbeeldvak]');
  var titel           = modal.querySelector('[data-rijk-titel]');
  var balk            = modal.querySelector('[data-rijk-balk]');
  var linkVak         = modal.querySelector('[data-rijk-link-vak]');
  var linkUrl         = modal.querySelector('[data-rijk-link-url]');
  var linkBlank       = modal.querySelector('[data-rijk-link-blank]');
  var voorbeeldKnop    = modal.querySelector('[data-rijk-voorbeeld-knop]');
  var kleurInput       = modal.querySelector('[data-rijk-kleur]');
  var lettertypeSelect = modal.querySelector('[data-rijk-lettertype]');
  var grootteSelect    = modal.querySelector('[data-rijk-grootte]');
  var huidigVeld       = null; // de .rijk-veld die nu open staat
  var huidigOpslag      = null; // de bijbehorende verborgen <textarea>
  var huidigVoorbeeld    = null; // de bijbehorende leesbare voorbeeld-<div>
  var oorspronkelijkeHtml = '';
  var opgeslagenSelectie   = null;

  // Als door een verouderde/gedeeltelijke upload één van de verwachte
  // elementen ontbreekt, willen we dat niet stilletjes de HELE werkbalk laten
  // vastlopen (dan werkt zelfs "Vet" niet meer). Voer de rest alleen uit als
  // echt alles gevonden is.
  var vereist = [editor, voorbeeldvak, titel, balk, linkVak, linkUrl, linkBlank, voorbeeldKnop, kleurInput, lettertypeSelect, grootteSelect];
  if (vereist.indexOf(null) !== -1) {
    console.error('Opmaak-editor: niet alle verwachte elementen gevonden, werkbalk wordt niet geactiveerd.');
    return;
  }

  function rijkOpenen(veld) {
    huidigVeld    = veld;
    huidigOpslag   = veld.querySelector('[data-rijk-opslag]');
    huidigVoorbeeld = veld.querySelector('[data-rijk-voorbeeld]');
    oorspronkelijkeHtml = huidigOpslag.value;
    editor.innerHTML = oorspronkelijkeHtml;
    titel.textContent = veld.querySelector('[data-rijk-open]').getAttribute('data-rijk-label') || 'Tekst bewerken';
    toonEditor();
    modal.hidden = false;
    editor.focus();
  }

  function sluiten() {
    modal.hidden = true;
    huidigVeld = null;
  }

  function toonEditor() {
    voorbeeldvak.hidden = true;
    editor.hidden = false;
    linkVak.hidden = true;
    balk.hidden = false;
    voorbeeldKnop.textContent = 'Voorbeeld';
  }

  function toonVoorbeeld() {
    voorbeeldvak.innerHTML = editor.innerHTML.trim() !== '' ? editor.innerHTML : '<span style="color:#9aa39c;font-style:italic">(leeg)</span>';
    voorbeeldvak.hidden = false;
    editor.hidden = true;
    linkVak.hidden = true;
    balk.hidden = true; // werkbalk weg tijdens voorbeeld, zodat het verschil duidelijk zichtbaar is
    voorbeeldKnop.textContent = 'Terug naar bewerken';
  }

  document.querySelectorAll('[data-rijk-open]').forEach(function (knop) {
    knop.addEventListener('click', function () {
      rijkOpenen(knop.closest('[data-rijk-veld]'));
    });
  });

  modal.querySelectorAll('[data-rijk-sluiten]').forEach(function (el) {
    el.addEventListener('click', sluiten);
  });

  modal.querySelector('[data-rijk-annuleren]').addEventListener('click', function () {
    editor.innerHTML = oorspronkelijkeHtml; // ongedaan maken van deze sessie
    sluiten();
  });

  modal.querySelector('[data-rijk-opslaan]').addEventListener('click', function () {
    if (!huidigVeld) { return; }
    var html = editor.innerHTML.trim();
    huidigOpslag.value = html;
    huidigVoorbeeld.innerHTML = html !== '' ? html : '<span class="rijk-veld__leeg">(leeg)</span>';
    sluiten();
  });

  voorbeeldKnop.addEventListener('click', function () {
    if (voorbeeldvak.hidden) { toonVoorbeeld(); } else { toonEditor(); }
  });

  modal.querySelectorAll('[data-rijk-cmd]').forEach(function (knop) {
    knop.addEventListener('click', function () {
      editor.focus();
      document.execCommand(knop.getAttribute('data-rijk-cmd'), false, null);
    });
  });

  // Link toevoegen: eigen klein vakje i.p.v. document.execCommand('createLink'),
  // zodat we ook "open in nieuw tabblad" kunnen aanbieden.
  modal.querySelector('[data-rijk-link]').addEventListener('click', function () {
    var selectie = window.getSelection();
    if (selectie.rangeCount > 0) { opgeslagenSelectie = selectie.getRangeAt(0); }
    linkUrl.value = '';
    linkBlank.checked = false;
    linkVak.hidden = false;
    linkUrl.focus();
  });

  modal.querySelector('[data-rijk-link-annuleren]').addEventListener('click', function () {
    linkVak.hidden = true;
    editor.focus();
  });

  modal.querySelector('[data-rijk-link-toepassen]').addEventListener('click', function () {
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
        var el = selectie.anchorNode.nodeType === 1 ? selectie.anchorNode : selectie.anchorNode.parentElement;
        var a = el ? el.closest('a') : null;
        if (a) { a.setAttribute('target', '_blank'); a.setAttribute('rel', 'noopener noreferrer'); }
      }
    }
    linkVak.hidden = true;
  });

  document.addEventListener('keydown', function (ev) {
    if (!modal.hidden && ev.key === 'Escape') { sluiten(); }
  });

  // Tekstkleur en tekengrootte: het klikken/openen van deze invoervelden
  // neemt de focus (en dus de tekstselectie) van de editor over, dus we
  // bewaren de selectie vlak vóórdat dat gebeurt en zetten 'm terug voordat
  // we de opmaak toepassen — hetzelfde patroon als bij de linkknop hierboven.
  var opgeslagenSelectieOpmaak = null;
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

  kleurInput.addEventListener('mousedown', bewaarSelectieVoorOpmaak);
  kleurInput.addEventListener('input', function () {
    herstelSelectieVoorOpmaak();
    document.execCommand('foreColor', false, kleurInput.value);
  });

  lettertypeSelect.addEventListener('mousedown', bewaarSelectieVoorOpmaak);
  lettertypeSelect.addEventListener('change', function () {
    var waarde = lettertypeSelect.value;
    lettertypeSelect.value = ''; // toont daarna weer het label "Lettertype"
    if (waarde === '') { return; }
    herstelSelectieVoorOpmaak();
    document.execCommand('fontName', false, waarde);
    // Sommige browsers gebruiken hiervoor <font face="…">; die zetten we om
    // naar <span style="font-family:…">, net als bij de tekengrootte.
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
    grootteSelect.value = ''; // toont daarna weer het label "Grootte"
    if (waarde === '') { return; }
    herstelSelectieVoorOpmaak();
    // document.execCommand('fontSize', ...) kent alleen de oude schaal 1–7,
    // geen px/em. Truc: toepassen met een unieke sentinelwaarde (7), daarna
    // de ontstane <font size="7">-tags vervangen door <span style="font-size:...">
    // met de daadwerkelijk gekozen waarde.
    document.execCommand('fontSize', false, '7');
    editor.querySelectorAll('font[size="7"]').forEach(function (f) {
      var span = document.createElement('span');
      span.style.fontSize = waarde;
      while (f.firstChild) { span.appendChild(f.firstChild); }
      f.parentNode.replaceChild(span, f);
    });
  });
})();
