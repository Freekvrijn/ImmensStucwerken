<?php
// Uitsluitend voor de lokale PHP-server, niet opnemen in public_html.zip.
if (PHP_SAPI !== 'cli-server') { http_response_code(404); exit; }
define('IMMENS_LOCAL_PREVIEW', true);
$root = __DIR__ . '/public_html';
$path = rawurldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '/');
if (preg_match('~(?:^|/)(?:data|_lib|themas)(?:/|$)|(?:^|/)\.|\.php/|\x00~i', $path)) {
    http_response_code(403); exit('Niet toegankelijk.');
}
$file = realpath($root . $path);
if ($file && strpos($file, realpath($root) . DIRECTORY_SEPARATOR) === 0 && is_file($file)) {
    if (preg_match('~^/assets/img/uploads/.*\.(?:php[0-9]?|phtml|phar)$~i', $path)) { http_response_code(403); exit; }
    if (substr($file, -4) === '.php') { require $file; return true; }
    return false;
}
if ($path === '/beheer/' || $path === '/beheer') { require $root . '/beheer/index.php'; return true; }
if ($path === '/') { require $root . '/index.php'; return true; }
if (preg_match('~^/blauwdruk/?$~', $path)) {
    require $root . '/index.php'; return true;
}
if (preg_match('~^/([a-z0-9]+(?:-[a-z0-9]+)*)/?$~', $path, $match)) {
    $_GET['p'] = $match[1]; require $root . '/eigen-pagina.php'; return true;
}
http_response_code(404); require $root . '/404.php';
