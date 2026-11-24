<?php
require_once __DIR__ . '/_lib/site.php';
require_once __DIR__ . '/_lib/offertes.php';
require_once __DIR__ . '/_lib/offerte-pdf.php';
cms_public_boot('offerte');
header('Cache-Control: private, no-store');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
$record=offerte_laad($_GET['id'] ?? null);
if (!$record || !offerte_toegang($record)) { http_response_code(404); exit('Deze offerte is niet beschikbaar.'); }
header('Content-Type: application/pdf');
header('Content-Disposition: attachment; filename="offerte-'.$record['id'].'.pdf"');
echo offerte_pdf($record);
