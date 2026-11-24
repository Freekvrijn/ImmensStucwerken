<?php
/** Offerteberekening in gehele centen. De server bepaalt altijd de opgeslagen prijs. */
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/formulieren.php';

function offerte_config() {
    $config = load_json('offerte-instellingen.json', array());
    if (!isset($config['diensten']) || !is_array($config['diensten'])) { throw new RuntimeException('Offerte-instellingen ontbreken.'); }
    return $config;
}
function offerte_revisie($config) { return hash('sha256', json_encode($config, JSON_UNESCAPED_UNICODE)); }
function offerte_tekst($data, $key, $max = 200) {
    if (!isset($data[$key]) || !is_string($data[$key])) { return ''; }
    return mb_substr(trim(str_replace(array("\0", "\r"), '', $data[$key])), 0, $max);
}
/** Een getal met maximaal twee decimalen, geschaald met 100. Geen exponenten of negatieve waarden. */
function offerte_decimal($value, $max = 1000000) {
    if (!is_string($value) && !is_int($value)) { throw new InvalidArgumentException('Vul een geldig getal in.'); }
    $value = trim((string) $value);
    if (!preg_match('/^([0-9]{1,8})(?:[.,]([0-9]{1,2}))?$/D', $value, $m)) { throw new InvalidArgumentException('Gebruik een positief getal met maximaal twee decimalen.'); }
    $number = (int) $m[1] * 100 + (int) str_pad($m[2] ?? '', 2, '0');
    if ($number > $max) { throw new InvalidArgumentException('Het ingevulde getal is te groot.'); }
    return $number;
}
function offerte_euro($cent) { return '€ ' . number_format($cent / 100, 2, ',', '.'); }
function offerte_aantal($units) { return rtrim(rtrim(number_format($units / 100, 2, ',', ''), '0'), ','); }
function offerte_config_opschonen($post, $current) {
    $config = $current;
    $services = $post['diensten'] ?? null;
    if (!is_array($services) || count($services) > 21) { throw new InvalidArgumentException('Gebruik maximaal twintig diensten.'); }
    $config['diensten'] = array();
    $seen = array();
    foreach ($services as $id => $row) {
        if (!is_array($row)) { throw new InvalidArgumentException('Ongeldige dienst.'); }
        $name = offerte_tekst($row, 'naam', 80);
        if ($id === 'nieuw' && $name === '') { continue; }
        if (!empty($row['verwijderen'])) { continue; }
        if ($name === '') { throw new InvalidArgumentException('Geef iedere dienst een naam.'); }
        if ($id === 'nieuw') { $id = 'dienst-' . bin2hex(random_bytes(6)); }
        if (!is_string($id) || !preg_match('/^[a-z][a-z0-9-]{0,40}$/D', $id) || isset($seen[$id])) { throw new InvalidArgumentException('Ongeldige dienstcode.'); }
        $seen[$id] = true;
        $unit = offerte_tekst($row, 'eenheid');
        if (!in_array($unit, array('m²', 'stuk', 'uur'), true)) { throw new InvalidArgumentException('Kies een geldige eenheid.'); }
        $config['diensten'][] = array('id'=>$id, 'naam'=>$name, 'eenheid'=>$unit, 'prijs_cent'=>offerte_decimal($row['prijs'] ?? '', 10000000), 'actief'=>!empty($row['actief']));
    }
    if (!$config['diensten']) { throw new InvalidArgumentException('Voeg ten minste één dienst toe.'); }
    if (count($config['diensten']) > 20) { throw new InvalidArgumentException('Gebruik maximaal twintig diensten.'); }
    $config['actief'] = !empty($post['actief']);
    if ($config['actief'] && !array_filter($config['diensten'], function($row) { return $row['actief']; })) { throw new InvalidArgumentException('Zet ten minste één dienst aan.'); }
    $config['reiskosten_cent'] = offerte_decimal($post['reiskosten'] ?? '', 10000000);
    $config['minimum_cent'] = offerte_decimal($post['minimum'] ?? '', 10000000);
    if (!in_array($post['btw_standaard'] ?? null, array('9', '21'), true)) { throw new InvalidArgumentException('Kies 9% of 21% btw.'); }
    $config['btw_standaard'] = (int) $post['btw_standaard'];
    $config['woning_btw'] = !empty($post['woning_btw']);
    $days = $post['geldigheid_dagen'] ?? '';
    if (!is_string($days) || !ctype_digit($days) || (int) $days < 1 || (int) $days > 365) { throw new InvalidArgumentException('De geldigheid moet 1 tot 365 dagen zijn.'); }
    $config['geldigheid_dagen'] = (int) $days;
    $config['voorwaarden'] = offerte_tekst($post, 'voorwaarden', 3000);
    if ($config['voorwaarden'] === '') { throw new InvalidArgumentException('Vul de offertevoorwaarden in.'); }
    $business = is_array($post['bedrijf'] ?? null) ? $post['bedrijf'] : array();
    foreach (array('naam','adres','email','telefoon','kvk','btw_nummer') as $field) { $config['bedrijf'][$field] = offerte_tekst($business, $field, 200); }
    if ($config['bedrijf']['naam'] === '') { throw new InvalidArgumentException('Vul de bedrijfsnaam in.'); }
    if ($config['bedrijf']['email'] !== '' && !filter_var($config['bedrijf']['email'], FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Het zakelijke e-mailadres is ongeldig.'); }
    return $config;
}
function offerte_bereken($config, $input) {
    if (empty($config['actief'])) { throw new InvalidArgumentException('Online offertes zijn tijdelijk uitgeschakeld.'); }
    $amounts = $input['aantallen'] ?? array();
    if (!is_array($amounts)) { throw new InvalidArgumentException('Vul geldige hoeveelheden in.'); }
    $rows = array(); $work = 0;
    $allowed = array();
    foreach ($config['diensten'] as $service) {
        if (empty($service['actief'])) { continue; }
        $allowed[] = $service['id'];
        $value = $amounts[$service['id']] ?? '0';
        if ($value === '') { $value = '0'; }
        $quantity = offerte_decimal($value);
        if ($quantity === 0) { continue; }
        $price = (int) $service['prijs_cent'];
        $total = intdiv($quantity * $price + 50, 100);
        $rows[] = array('naam'=>$service['naam'], 'eenheid'=>$service['eenheid'], 'aantal_honderdste'=>$quantity, 'prijs_cent'=>$price, 'totaal_cent'=>$total);
        $work += $total;
    }
    if (array_diff(array_keys($amounts), $allowed)) { throw new InvalidArgumentException('Een gekozen dienst is niet meer beschikbaar.'); }
    if (!$rows) { throw new InvalidArgumentException('Vul bij minstens één dienst een hoeveelheid groter dan nul in.'); }
    $minimum = max(0, (int) $config['minimum_cent'] - $work);
    if ($minimum) { $rows[] = array('naam'=>'Aanvulling tot minimum werkbedrag', 'eenheid'=>'stuk', 'aantal_honderdste'=>100, 'prijs_cent'=>$minimum, 'totaal_cent'=>$minimum); }
    $travel = (int) $config['reiskosten_cent'];
    if ($travel) { $rows[] = array('naam'=>'Reiskosten', 'eenheid'=>'stuk', 'aantal_honderdste'=>100, 'prijs_cent'=>$travel, 'totaal_cent'=>$travel); }
    $kind = offerte_tekst($input, 'pand');
    if (!in_array($kind, array('woning-oud','woning-nieuw','overig','onbekend'), true)) { throw new InvalidArgumentException('Kies het soort pand.'); }
    $rate = !empty($config['woning_btw']) ? ($kind === 'woning-oud' ? 9 : 21) : (int) $config['btw_standaard'];
    $subtotal = $work + $minimum + $travel;
    $vat = intdiv($subtotal * $rate + 50, 100);
    return array('regels'=>$rows, 'subtotaal_cent'=>$subtotal, 'btw_percentage'=>$rate, 'btw_cent'=>$vat, 'totaal_cent'=>$subtotal+$vat, 'pand'=>$kind);
}
function offerte_klant($post) {
    $customer = array();
    foreach (array('naam'=>120, 'email'=>254, 'telefoon'=>40, 'adres'=>160, 'postcode'=>10, 'plaats'=>100, 'opmerking'=>2000) as $field=>$max) {
        $customer[$field] = offerte_tekst($post, $field, $max);
    }
    foreach (array('naam','email','adres','postcode','plaats') as $field) { if ($customer[$field] === '') { throw new InvalidArgumentException('Vul je naam, e-mail en het volledige projectadres in.'); } }
    if (!filter_var($customer['email'], FILTER_VALIDATE_EMAIL)) { throw new InvalidArgumentException('Vul een geldig e-mailadres in.'); }
    if (!preg_match('/^[1-9][0-9]{3}\s?[a-zA-Z]{2}$/D', $customer['postcode'])) { throw new InvalidArgumentException('Vul een Nederlandse postcode in, bijvoorbeeld 5271 KE.'); }
    $customer['postcode'] = strtoupper(preg_replace('/\s/', '', $customer['postcode']));
    return $customer;
}
function offerte_dir() { return CMS_DATA_DIR . '/offertes'; }
function offerte_geldige_id($id) { return is_string($id) && preg_match('/^IMM-[0-9]{4}-[0-9]{6}$/D', $id); }
function offerte_lock() {
    $file = fopen(data_path('offertes.lock'), 'c');
    if (!$file || !flock($file, LOCK_EX)) { throw new RuntimeException('De offerteopslag is tijdelijk niet beschikbaar.'); }
    return $file;
}
function offerte_write($id, $data) {
    $path = offerte_dir() . '/' . $id . '.json';
    $tmp = tempnam(offerte_dir(), '.offerte-');
    if (!$tmp) { return false; }
    $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    $ok = $json !== false && file_put_contents($tmp, $json . "\n") !== false;
    if ($ok) { @chmod($tmp, 0600); $ok = rename($tmp, $path); }
    if (!$ok) { @unlink($tmp); }
    return $ok;
}
function offerte_laad($id) {
    if (!offerte_geldige_id($id)) { return null; }
    $path = offerte_dir() . '/' . $id . '.json';
    if (!is_file($path)) { return null; }
    // Offertes met persoonsgegevens blijven maximaal 90 dagen beschikbaar.
    if (filemtime($path) < time() - 90 * 86400) { return null; }
    $record = json_decode((string) file_get_contents($path), true);
    if (!is_array($record) || strtotime($record['gemaakt_op'] ?? '') < time() - 90 * 86400) { return null; }
    return $record;
}
function offerte_toegang($record) {
    if (mag('settings')) { return true; }
    $secret = $_SESSION['offerte_toegang'][$record['id']] ?? '';
    return is_string($secret) && $secret !== '' && hash_equals($record['toegang_hash'], hash('sha256', $secret));
}
function offerte_opruimen() {
    foreach (glob(offerte_dir() . '/*.json') ?: array() as $path) {
        $data = json_decode((string) file_get_contents($path), true);
        if (is_array($data) && strtotime($data['gemaakt_op'] ?? '') < time() - 90 * 86400) { @unlink($path); }
    }
}
function offerte_aanmaken($config, $calculation, $customer) {
    $lock = offerte_lock();
    try {
        if (!hash_equals(offerte_revisie(offerte_config()), offerte_revisie($config))) { throw new InvalidArgumentException('De tarieven zijn bijgewerkt. Laad de pagina opnieuw en controleer de nieuwe berekening.'); }
        offerte_opruimen();
        if (count(glob(offerte_dir() . '/*.json') ?: array()) >= 2000) { throw new RuntimeException('Het maximale aantal offertes is bereikt. Neem rechtstreeks contact op.'); }
        $ip = client_ip();
        if (cms_formulier_is_geblokkeerd($customer['email'], $ip)) { throw new InvalidArgumentException('De offerte kan niet worden aangemaakt. Neem rechtstreeks contact op.'); }
        $log = load_json('offertelog.json', array()); $cutoff = time() - 3600;
        foreach ($log as $hash=>$times) {
            $log[$hash] = array_values(array_filter((array) $times, function($time) use ($cutoff) { return is_int($time) && $time > $cutoff; }));
            if (!$log[$hash]) { unset($log[$hash]); }
        }
        $ipHash = hash('sha256', $ip);
        if (count($log[$ipHash] ?? array()) >= 5) { throw new InvalidArgumentException('Je kunt maximaal vijf offertes per uur aanmaken. Probeer het later opnieuw.'); }
        $counter = load_json('offerte-teller.json', array()); $year = date('Y');
        $next = (int) ($counter[$year] ?? 0) + 1;
        while ($next <= 999999 && is_file(offerte_dir() . '/IMM-' . $year . '-' . sprintf('%06d', $next) . '.json')) { $next++; }
        if ($next > 999999) { throw new RuntimeException('Er kan momenteel geen offertenummer worden uitgegeven.'); }
        $id = 'IMM-' . $year . '-' . sprintf('%06d', $next);
        $counter[$year] = $next;
        $log[$ipHash][] = time();
        if (!save_json('offerte-teller.json', $counter, false) || !save_json('offertelog.json', $log, false)) { throw new RuntimeException('Het offertenummer kon niet worden opgeslagen.'); }
        $secret = bin2hex(random_bytes(32));
        $record = array('id'=>$id, 'gemaakt_op'=>date('c'), 'geldig_tot'=>date('Y-m-d', strtotime('+' . (int) $config['geldigheid_dagen'] . ' days')), 'status'=>'nieuw', 'klant'=>$customer, 'berekening'=>$calculation, 'instellingen'=>$config, 'toegang_hash'=>hash('sha256',$secret));
        if (!offerte_write($id, $record)) { throw new RuntimeException('De offerte kon niet worden opgeslagen. Probeer het opnieuw.'); }
        $_SESSION['offerte_toegang'][$id] = $secret;
        $_SESSION['offerte_toegang'] = array_slice($_SESSION['offerte_toegang'], -20, null, true);
        return $record;
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
function offerte_lijst() {
    $lock = offerte_lock();
    try { offerte_opruimen(); } finally { flock($lock, LOCK_UN); fclose($lock); }
    $records = array();
    foreach (glob(offerte_dir() . '/*.json') ?: array() as $path) {
        $record = offerte_laad(basename($path, '.json'));
        if ($record) { $records[] = $record; }
    }
    usort($records, function($a,$b) { return strcmp($b['id'],$a['id']); });
    return $records;
}
function offerte_statussen() { return array('nieuw'=>'Nieuw', 'in-behandeling'=>'In behandeling', 'afgerond'=>'Afgerond'); }
function offerte_record_wijzig($id, $action, $status = '') {
    $lock = offerte_lock();
    try {
        $record = offerte_laad($id);
        if (!$record) { throw new InvalidArgumentException('Deze offerte bestaat niet meer.'); }
        if ($action === 'verwijderen') { return unlink(offerte_dir() . '/' . $id . '.json'); }
        if (!isset(offerte_statussen()[$status])) { throw new InvalidArgumentException('Kies een geldige status.'); }
        $record['status'] = $status;
        return offerte_write($id, $record);
    } finally { flock($lock, LOCK_UN); fclose($lock); }
}
