</main>
<footer class="admin-foot">
  <span>Beheer &middot; <?= e(function_exists('cms_site_naam') ? cms_site_naam() : CMS_SITE_NAME) ?></span>
</footer>
<script src="beheer.js?v=<?= (int) @filemtime(__DIR__ . '/beheer.js') ?>"></script>
</body>
</html>
