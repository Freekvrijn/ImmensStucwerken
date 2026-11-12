<?php /**
 * Modal voor de inline-editor op een gekoppelde publieke pagina. Hergebruikt
 * bewust dezelfde CSS-klassen als de
 * beheer-opmaakeditor (beheer/rijk-editor.css: .rijk-modal, .rijk-modal__…)
 * zodat het er identiek uitziet, maar met eigen data-inl-*-attributen zodat
 * beheer/inline-editor.js nooit met beheer/rijk-editor.js kan botsen —
 * die twee draaien toch nooit op dezelfde pagina, maar zo is het ook niet
 * per ongeluk mogelijk.
 */ ?>
<div class="inl-account-acties" data-inl-account>
  <form method="post" action="/beheer/logout.php">
    <?= csrf_field() ?>
    <input type="hidden" name="naar_site" value="1">
    <button type="submit" class="inl-uitloggen">Uitloggen</button>
  </form>
</div>
<div class="rijk-modal" data-inl-modal hidden>
  <div class="rijk-modal__scherm" data-inl-sluiten></div>
  <div class="rijk-modal__paneel" role="dialog" aria-modal="true" aria-labelledby="inl-modal-titel">
    <div class="rijk-modal__kop">
      <h3 id="inl-modal-titel" data-inl-titel>Tekst bewerken</h3>
      <button type="button" class="rijk-modal__sluit" data-inl-sluiten aria-label="Sluiten zonder op te slaan">&times;</button>
    </div>
    <div class="rijk-modal__balk">
      <select data-inl-blok title="Soort tekstblok" aria-label="Soort tekstblok" hidden>
        <option value="">Tekstsoort</option>
        <option value="p">Tekst</option>
        <option value="h2">Tussenkop (H2)</option>
        <option value="h3">Subkop (H3)</option>
      </select>
      <button type="button" data-inl-cmd="bold" title="Vet"><b>V</b></button>
      <button type="button" data-inl-cmd="italic" title="Cursief"><i>C</i></button>
      <button type="button" data-inl-cmd="underline" title="Onderstrepen"><u>O</u></button>
      <button type="button" data-inl-cmd="insertUnorderedList" title="Opsommingstekens">&bull; Lijst</button>
      <button type="button" data-inl-cmd="justifyLeft" title="Links uitlijnen">Links</button>
      <button type="button" data-inl-cmd="justifyCenter" title="Centreren">Centreren</button>
      <button type="button" data-inl-cmd="justifyRight" title="Rechts uitlijnen">Rechts</button>
      <button type="button" data-inl-link title="Link toevoegen">&#128279; Link</button>
      <span class="rijk-modal__balk-scheiding"></span>
      <label class="rijk-modal__kleurknop" title="Tekstkleur">
        <input type="color" data-inl-kleur value="#16231c">
      </label>
      <select data-inl-lettertype title="Lettertype">
        <option value="">Lettertype</option>
        <option value="Georgia">Georgia (schreef)</option>
        <option value="Arial">Arial (schreefloos)</option>
        <option value="Verdana">Verdana (schreefloos)</option>
        <option value="Courier New">Courier New (monospace)</option>
        <option value="Poppins">Poppins</option>
        <option value="Montserrat">Montserrat</option>
        <option value="Roboto">Roboto</option>
        <option value="Open Sans">Open Sans</option>
        <option value="Lato">Lato</option>
      </select>
      <select data-inl-grootte title="Tekengrootte">
        <option value="">Grootte</option>
        <option value="12px">12px</option>
        <option value="14px">14px</option>
        <option value="16px">16px (standaard)</option>
        <option value="18px">18px</option>
        <option value="20px">20px</option>
        <option value="24px">24px</option>
        <option value="28px">28px</option>
        <option value="1em">1em</option>
        <option value="1.25em">1,25em</option>
        <option value="1.5em">1,5em</option>
        <option value="2em">2em</option>
      </select>
      <button type="button" data-inl-cmd="removeFormat" title="Opmaak wissen">Wissen</button>
      <button type="button" data-inl-cmd="undo" title="Ongedaan maken">&#8634;</button>
      <button type="button" data-inl-cmd="redo" title="Opnieuw">&#8635;</button>
    </div>
    <div class="rijk-modal__link-vak" data-inl-link-vak hidden>
      <label>Webadres (https://…, of tel:/mailto:)<input type="text" data-inl-link-url placeholder="https://example.nl"></label>
      <label class="rijk-modal__link-tab"><input type="checkbox" data-inl-link-blank> Openen in een nieuw tabblad</label>
      <div class="rijk-modal__link-acties">
        <button type="button" class="btn" data-inl-link-toepassen>Link toepassen</button>
        <button type="button" class="btn ghost" data-inl-link-annuleren>Annuleren</button>
      </div>
    </div>
    <div class="rijk-modal__editor" contenteditable="true" data-inl-editor></div>
    <p class="inl-status" data-inl-status></p>
    <div class="rijk-modal__voet">
      <button type="button" class="btn btn--primary" data-inl-opslaan>Opslaan</button>
      <button type="button" class="btn btn--outline" data-inl-annuleren>Annuleren</button>
    </div>
  </div>
</div>

<div class="rijk-modal rij-modal" data-rij-modal hidden>
  <div class="rijk-modal__scherm" data-rij-sluiten></div>
  <form class="rijk-modal__paneel" data-rij-form role="dialog" aria-modal="true" aria-labelledby="rij-modal-titel">
    <div class="rijk-modal__kop">
      <h3 id="rij-modal-titel">Tekstrij toevoegen</h3>
      <button type="button" class="rijk-modal__sluit" data-rij-sluiten aria-label="Sluiten zonder opslaan">&times;</button>
    </div>
    <div data-rij-tekstvelden>
      <div class="rij-form-grid">
        <label>Indeling<select name="indeling"><option value="tekst">Tekst met optionele knop</option><option value="kader">Tekst met een kader rechts</option><option value="afbeelding">Tekst met een afbeelding</option><option value="kaarten">Blokken naast elkaar (afbeelding + link)</option></select></label>
        <label>Achtergrond<select name="achtergrond"><option value="paper">Licht</option><option value="mint">Lichtgroen</option></select></label>
      </div>
      <label>Klein kopje erboven <small>(optioneel)</small><input name="bovenkop" maxlength="120"></label>
      <div class="rij-form-grid rij-form-grid--kop">
        <label>Kop<input name="titel" maxlength="250"></label>
        <label>Soort kop<select name="titel_niveau"><option value="h2">Tussenkop (H2)</option><option value="h3">Subkop (H3)</option></select></label>
      </div>
      <label>Tekst</label>
      <div class="rij-tekstvoorbeeld" data-rij-voorbeeld="html"></div>
      <textarea name="html" hidden></textarea>
      <button type="button" class="rijk-veld__bewerk" data-rij-opmaak="html">Tekst bewerken &amp; opmaken</button>
      <div data-rij-kadervelden hidden>
        <label>Tekst in het kader rechts</label>
        <div class="rij-tekstvoorbeeld" data-rij-voorbeeld="kader_html"></div>
        <textarea name="kader_html" hidden></textarea>
        <button type="button" class="rijk-veld__bewerk" data-rij-opmaak="kader_html">Kadertekst bewerken &amp; opmaken</button>
      </div>
      <div data-rij-afbeeldingvelden hidden>
        <label>Afbeelding</label>
        <div class="rij-foto" data-rij-foto="afbeelding">
          <img alt="" data-rij-foto-voorbeeld hidden>
          <input type="hidden" name="afbeelding">
          <label class="rij-foto__kies">Afbeelding kiezen<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-rij-foto-bestand></label>
          <button type="button" class="rij-foto__weg" data-rij-foto-weg hidden>Afbeelding weghalen</button>
          <span class="rij-foto__status" data-rij-foto-status></span>
        </div>
        <div class="rij-form-grid">
          <label>Beschrijving van de afbeelding<input name="afbeelding_alt" maxlength="200" placeholder="Wat staat erop?"></label>
          <label>Plaats van de afbeelding<select name="afbeelding_positie"><option value="rechts">Rechts van de tekst</option><option value="links">Links van de tekst</option></select></label>
        </div>
      </div>
      <div data-rij-kaartvelden hidden>
        <p class="rij-hint">Vul 2 of 3 blokken in; ze komen naast elkaar te staan (op een telefoon onder elkaar). De kop en tekst hierboven verschijnen als inleiding boven de blokken.</p>
        <fieldset class="rij-kaart">
          <legend>Blok 1</legend>
          <div class="rij-foto" data-rij-foto="kaart1_afbeelding">
            <img alt="" data-rij-foto-voorbeeld hidden>
            <input type="hidden" name="kaart1_afbeelding">
            <label class="rij-foto__kies">Afbeelding kiezen<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-rij-foto-bestand></label>
            <button type="button" class="rij-foto__weg" data-rij-foto-weg hidden>Afbeelding weghalen</button>
            <span class="rij-foto__status" data-rij-foto-status></span>
          </div>
          <label>Kop<input name="kaart1_titel" maxlength="120"></label>
          <label>Korte tekst<textarea name="kaart1_tekst" maxlength="500" rows="3"></textarea></label>
          <div class="rij-form-grid">
            <label>Linktekst<input name="kaart1_link_tekst" maxlength="60" placeholder="Bijv. Lees meer"></label>
            <label>Link naar<input name="kaart1_link_url" maxlength="2000" placeholder="Bijv. diensten of https://…"></label>
          </div>
        </fieldset>
        <fieldset class="rij-kaart">
          <legend>Blok 2</legend>
          <div class="rij-foto" data-rij-foto="kaart2_afbeelding">
            <img alt="" data-rij-foto-voorbeeld hidden>
            <input type="hidden" name="kaart2_afbeelding">
            <label class="rij-foto__kies">Afbeelding kiezen<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-rij-foto-bestand></label>
            <button type="button" class="rij-foto__weg" data-rij-foto-weg hidden>Afbeelding weghalen</button>
            <span class="rij-foto__status" data-rij-foto-status></span>
          </div>
          <label>Kop<input name="kaart2_titel" maxlength="120"></label>
          <label>Korte tekst<textarea name="kaart2_tekst" maxlength="500" rows="3"></textarea></label>
          <div class="rij-form-grid">
            <label>Linktekst<input name="kaart2_link_tekst" maxlength="60" placeholder="Bijv. Lees meer"></label>
            <label>Link naar<input name="kaart2_link_url" maxlength="2000" placeholder="Bijv. diensten of https://…"></label>
          </div>
        </fieldset>
        <fieldset class="rij-kaart">
          <legend>Blok 3 <small>(optioneel)</small></legend>
          <div class="rij-foto" data-rij-foto="kaart3_afbeelding">
            <img alt="" data-rij-foto-voorbeeld hidden>
            <input type="hidden" name="kaart3_afbeelding">
            <label class="rij-foto__kies">Afbeelding kiezen<input type="file" accept="image/jpeg,image/png,image/gif,image/webp" data-rij-foto-bestand></label>
            <button type="button" class="rij-foto__weg" data-rij-foto-weg hidden>Afbeelding weghalen</button>
            <span class="rij-foto__status" data-rij-foto-status></span>
          </div>
          <label>Kop<input name="kaart3_titel" maxlength="120"></label>
          <label>Korte tekst<textarea name="kaart3_tekst" maxlength="500" rows="3"></textarea></label>
          <div class="rij-form-grid">
            <label>Linktekst<input name="kaart3_link_tekst" maxlength="60" placeholder="Bijv. Lees meer"></label>
            <label>Link naar<input name="kaart3_link_url" maxlength="2000" placeholder="Bijv. diensten of https://…"></label>
          </div>
        </fieldset>
      </div>
    </div>
    <fieldset class="rij-knopvelden">
      <legend>Knop <small>(optioneel)</small></legend>
      <label>Tekst op de knop<input name="knop_tekst" maxlength="100" placeholder="Bijvoorbeeld: Vraag een offerte aan"></label>
      <label>Bestemming<input name="knop_url" maxlength="2000" placeholder="Bijvoorbeeld: contact of https://…"></label>
      <p class="rij-hint">Laat beide velden leeg om deze knop weg te laten.</p>
    </fieldset>
    <p class="inl-status" data-rij-status role="status" aria-live="polite"></p>
    <div class="rijk-modal__voet">
      <button type="submit" class="btn btn--primary" data-rij-opslaan>Opslaan</button>
      <button type="button" class="btn btn--outline" data-rij-sluiten>Annuleren</button>
    </div>
  </form>
</div>
<script>window.CMS_BEHEER_BASE = '/beheer/';</script>
<script src="/<?= asset('beheer/tekstrijen.js') ?>" defer></script>

<!-- Klein, los popover voor het bewerken van een knop-/linkbestemming — geen
     opmaak, alleen een webadres. Bewust een ander, simpeler venster dan de
     tekst-editor hierboven — en bewust andere data-attributen (inl-btnlink-*
     i.p.v. inl-link-*) omdat die laatste al bezet zijn door de "link
     toevoegen"-knop in de tekst-editor hierboven. -->
<div class="rijk-modal inl-btnlink-modal" data-inl-btnlink-modal hidden>
  <div class="rijk-modal__scherm" data-inl-btnlink-sluiten></div>
  <div class="rijk-modal__paneel inl-btnlink-modal__paneel" role="dialog" aria-modal="true" aria-labelledby="inl-btnlink-modal-titel">
    <div class="rijk-modal__kop">
      <h3 id="inl-btnlink-modal-titel">Bestemming van deze knop</h3>
      <button type="button" class="rijk-modal__sluit" data-inl-btnlink-sluiten aria-label="Sluiten zonder op te slaan">&times;</button>
    </div>
    <label class="inl-btnlink-modal__label">
      Webadres
      <input type="text" data-inl-btnlink-input placeholder="bv. contact, diensten#reinigen, of https://…">
    </label>
    <p class="inl-status" data-inl-btnlink-status></p>
    <div class="rijk-modal__voet">
      <button type="button" class="btn" data-inl-btnlink-opslaan>Opslaan</button>
      <button type="button" class="btn ghost" data-inl-btnlink-annuleren>Annuleren</button>
    </div>
  </div>
</div>
