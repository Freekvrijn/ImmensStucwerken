<?php
require_once __DIR__ . '/../_lib/auth.php';
auth_boot();

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !csrf_check()) {
    http_response_code(403);
    exit('Sessie verlopen. Vernieuw de pagina en probeer opnieuw.');
}
$naarSite = $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['naar_site'] ?? '') === '1';

$_SESSION = array();
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
}
session_destroy();

header('Cache-Control: no-store');
header('Location: ' . ($naarSite ? '../' : 'login.php'));
exit;
