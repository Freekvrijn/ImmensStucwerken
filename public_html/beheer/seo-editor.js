(function () {
  var form = document.querySelector('[data-seo-form]');
  var editor = document.querySelector('[data-seo-editor]');
  var opslag = document.querySelector('[data-seo-opslag]');
  if (!form || !editor || !opslag) { return; }

  function opdracht(naam, waarde) {
    editor.focus();
    document.execCommand(naam, false, waarde || null);
  }

  document.querySelectorAll('[data-seo-cmd]').forEach(function (knop) {
    knop.addEventListener('click', function () {
      opdracht(knop.getAttribute('data-seo-cmd'));
    });
  });

  var blok = document.querySelector('[data-seo-blok]');
  if (blok) {
    blok.addEventListener('change', function () {
      opdracht('formatBlock', blok.value);
      blok.value = 'p';
    });
  }

  var link = document.querySelector('[data-seo-link]');
  if (link) {
    link.addEventListener('click', function () {
      var url = window.prompt('Webadres (https://…, /pagina, tel: of mailto:)');
      if (url) { opdracht('createLink', url.trim()); }
    });
  }

  var afbeelding = document.querySelector('[data-seo-afbeelding]');
  if (afbeelding) {
    afbeelding.addEventListener('click', function () {
      var url = window.prompt('HTTPS-adres van de afbeelding');
      if (!url) { return; }
      var alt = window.prompt('Korte beschrijving van de afbeelding') || '';
      editor.focus();
      document.execCommand('insertHTML', false, '<img src="' + url.replace(/"/g, '&quot;') + '" alt="' + alt.replace(/"/g, '&quot;') + '">');
    });
  }

  // ---- HTML-code bekijken en plakken ---------------------------------------
  // Wisselt tussen de opmaakeditor en een tekstvak met de HTML. Wat hier wordt
  // ingevoerd, gaat bij het opslaan door hetzelfde serverfilter als de editor;
  // na het opslaan ziet de gebruiker wat er is weggehaald.
  var htmlKnop = document.querySelector('[data-seo-html-toggle]');
  var htmlVak = document.querySelector('[data-seo-html-vak]');
  var balk = document.querySelector('.seo-editor__balk');
  var htmlStand = false;

  // Leesbare code: elk blok op een eigen regel.
  function netjes(html) {
    return html
      .replace(/\s*(<\/(?:h[1-6]|p|ul|ol|li|div|figure|blockquote)>)/gi, '$1')
      .replace(/(<\/(?:h[1-6]|p|ul|ol|div|figure|blockquote)>)\s*/gi, '$1\n\n')
      .replace(/(<\/li>)\s*/gi, '$1\n')
      .replace(/(<(?:ul|ol)>)\s*/gi, '$1\n')
      .replace(/(<img\b[^>]*>)\s*/gi, '$1\n')
      .replace(/\n{3,}/g, '\n\n')
      .trim();
  }

  // Terug naar de editor: eerst ontdoen van alles wat in de editor zelf al
  // zou kunnen uitvoeren (scripts, onerror=… e.d.). DOMParser voert niets uit.
  function veiligVoorEditor(html) {
    var doc = new DOMParser().parseFromString('<body>' + html + '</body>', 'text/html');
    doc.body.querySelectorAll('script,style,iframe,object,embed,form,link,meta,base,svg,math').forEach(function (el) { el.remove(); });
    doc.body.querySelectorAll('*').forEach(function (el) {
      Array.prototype.slice.call(el.attributes).forEach(function (a) {
        var naam = a.name.toLowerCase();
        var waarde = String(a.value).replace(/\s+/g, '').toLowerCase();
        if (naam.indexOf('on') === 0 || ((naam === 'href' || naam === 'src') && waarde.indexOf('javascript:') === 0)) {
          el.removeAttribute(a.name);
        }
      });
    });
    return doc.body.innerHTML;
  }

  function zetStand(html) {
    htmlStand = html;
    htmlKnop.setAttribute('aria-pressed', html ? 'true' : 'false');
    htmlKnop.classList.toggle('is-actief', html);
    htmlKnop.innerHTML = html ? '&#9998; Terug naar opmaak' : '&lt;/&gt; HTML-code';
    editor.hidden = html;
    htmlVak.hidden = !html;
    balk.querySelectorAll('button, select').forEach(function (el) {
      if (el !== htmlKnop) { el.disabled = html; }
    });
  }

  if (htmlKnop && htmlVak && balk) {
    htmlKnop.addEventListener('click', function () {
      if (!htmlStand) {
        htmlVak.value = netjes(editor.innerHTML);
        zetStand(true);
        htmlVak.style.height = Math.max(520, htmlVak.scrollHeight + 10) + 'px';
        htmlVak.focus();
      } else {
        editor.innerHTML = veiligVoorEditor(htmlVak.value);
        zetStand(false);
        editor.focus();
      }
    });
  }

  form.addEventListener('submit', function () {
    opslag.value = htmlStand ? htmlVak.value.trim() : editor.innerHTML.trim();
  });
}());

// Opmaak "landingspagina": de headerfoto-instellingen alleen tonen als die opmaak gekozen is.
(function () {
  var keuze = document.querySelector('[data-opmaak-keuze]');
  var blok = document.querySelector('[data-opmaak-landing]');
  if (!keuze || !blok) { return; }
  keuze.addEventListener('change', function () { blok.hidden = keuze.value !== 'landing'; });
}());

// Headerfoto-velden alleen tonen als het blok "Header" is aangevinkt.
(function () {
  var vinkje = document.querySelector('[data-blok-header]');
  var velden = document.querySelector('[data-headerfoto-velden]');
  if (!vinkje || !velden) { return; }
  vinkje.addEventListener('change', function () { velden.hidden = !vinkje.checked; });
}());
