/* Kleine beheerinteracties, zonder dependencies of inline scripts. */
(function () {
  var menuKnop = document.querySelector('.admin-menu-toggle');
  var menu = document.getElementById('adminMenu');
  if (menuKnop && menu) {
    var meerMenus = menu.querySelectorAll('.admin-more');
    function menuSluiten() {
      document.body.classList.remove('admin-menu-open');
      menuKnop.setAttribute('aria-expanded', 'false');
      menuKnop.setAttribute('aria-label', 'Beheermenu openen');
      meerMenus.forEach(function (item) { item.removeAttribute('open'); });
    }
    menuKnop.addEventListener('click', function () {
      var open = document.body.classList.toggle('admin-menu-open');
      menuKnop.setAttribute('aria-expanded', open ? 'true' : 'false');
      menuKnop.setAttribute('aria-label', open ? 'Beheermenu sluiten' : 'Beheermenu openen');
    });
    menu.querySelectorAll('a').forEach(function (link) { link.addEventListener('click', menuSluiten); });
    document.addEventListener('keydown', function (event) { if (event.key === 'Escape') { menuSluiten(); } });
    document.addEventListener('click', function (event) {
      meerMenus.forEach(function (item) {
        if (item.hasAttribute('open') && !item.contains(event.target)) { item.removeAttribute('open'); }
      });
    });
  }

  document.querySelectorAll('form[data-confirm]').forEach(function (form) {
    form.addEventListener('submit', function (event) {
      if (!window.confirm(form.getAttribute('data-confirm'))) { event.preventDefault(); }
    });
  });
})();

/* Geregistreerde paginaonderdelen met toetsenbordvriendelijke knoppen verplaatsen. */
(function () {
  document.querySelectorAll('[data-sectie-lijst]').forEach(function (lijst) {
    function bijwerken() {
      var items = lijst.querySelectorAll('[data-sectie-item]');
      items.forEach(function (item, index) {
        item.querySelector('[data-sectie-op]').disabled = index === 0;
        item.querySelector('[data-sectie-neer]').disabled = index === items.length - 1;
      });
    }
    lijst.addEventListener('click', function (event) {
      var knop = event.target.closest('[data-sectie-op],[data-sectie-neer]');
      if (!knop) { return; }
      event.preventDefault();
      var item = knop.closest('[data-sectie-item]');
      var omhoog = knop.hasAttribute('data-sectie-op');
      var buur = omhoog ? item.previousElementSibling : item.nextElementSibling;
      if (!buur) { return; }
      if (omhoog) { lijst.insertBefore(item, buur); } else { lijst.insertBefore(buur, item); }
      bijwerken();
      item.querySelector(omhoog ? '[data-sectie-op]' : '[data-sectie-neer]').focus();
    });
    bijwerken();
  });
}());
