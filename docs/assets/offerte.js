(() => {
  const form = document.getElementById('offerteformulier');
  const source = document.getElementById('offerte-config');
  const output = document.getElementById('offerte-berekening');
  if (!form || !source || !output) return;
  const config = JSON.parse(source.textContent);
  const euros = (cents) => new Intl.NumberFormat('nl-NL', {style: 'currency', currency: 'EUR'}).format(cents / 100);
  function decimal(value) {
    const match = String(value || '0').trim().match(/^(\d{1,8})(?:[.,](\d{1,2}))?$/);
    if (!match) throw new Error('Gebruik een positief getal met maximaal twee decimalen.');
    const number = Number(match[1]) * 100 + Number((match[2] || '').padEnd(2, '0'));
    if (number > 1000000) throw new Error('Vul per dienst maximaal 10.000 in.');
    return number;
  }
  function update() {
    try {
      let work = 0;
      const rows = [];
      let selected = false;
      config.diensten.filter((item) => item.actief).forEach((item) => {
        const field = Array.from(form.querySelectorAll('[data-dienst]')).find((input) => input.dataset.dienst === item.id);
        const quantity = decimal(field.value);
        if (!quantity) return;
        selected = true;
        const total = Math.floor((quantity * item.prijs_cent + 50) / 100);
        work += total;
        rows.push([`${item.naam} (${quantity / 100} ${item.eenheid})`, euros(total)]);
      });
      if (!selected) {
        output.replaceChildren(Object.assign(document.createElement('p'), {textContent: 'Vul een hoeveelheid in om de prijs te zien.'}));
        return;
      }
      const minimum = Math.max(0, config.minimum_cent - work);
      if (minimum) rows.push(['Aanvulling tot minimum werkbedrag', euros(minimum)]);
      if (config.reiskosten_cent) rows.push(['Reiskosten', euros(config.reiskosten_cent)]);
      const subtotal = work + minimum + config.reiskosten_cent;
      const rate = config.woning_btw ? (form.elements.pand.value === 'woning-oud' ? 9 : 21) : config.btw_standaard;
      const vat = Math.floor((subtotal * rate + 50) / 100);
      rows.push(['Subtotaal excl. btw', euros(subtotal)], [`Btw (${rate}%)`, euros(vat)], ['Totaal incl. btw', euros(subtotal + vat)]);
      const list = document.createElement('dl');
      list.className = 'offerte-totalen';
      rows.forEach(([label, amount], index) => {
        const row = document.createElement('div');
        if (index === rows.length - 1) row.className = 'offerte-eindtotaal';
        row.append(Object.assign(document.createElement('dt'), {textContent: label}), Object.assign(document.createElement('dd'), {textContent: amount}));
        list.append(row);
      });
      output.replaceChildren(list);
    } catch (error) {
      output.replaceChildren(Object.assign(document.createElement('p'), {textContent: error.message}));
    }
  }
  form.addEventListener('input', (event) => {
    if (event.target.matches('[data-dienst], [name="pand"]')) update();
  });
  form.addEventListener('change', update);
  form.addEventListener('submit', (event) => {
    if (form.hasAttribute('data-demo-form')) event.preventDefault();
  });
  update();
})();
