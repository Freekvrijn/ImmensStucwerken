<?php
/**
 * Compacte lezer voor IP2Location BIN-databases (landcode), in pure PHP.
 *
 * Bewust eigen code in plaats van de officiële module: die heeft voor IPv6 de
 * extensie bcmath nodig, en die ontbreekt op veel shared hosting. Hier worden
 * IPv6-adressen als 16-byte binaire strings vergeleken; dat werkt overal.
 * Uitgetest tegen de officiële module (IP2LOCATION-LITE-DB1 en DB1.IPV6).
 *
 * Werkt met elk DBx-bestand (landcode staat altijd in kolom 2), LITE of commercieel.
 */

/** Opent een BIN-bestand. Geeft een handle-array terug, of null als het geen geldig bestand is. */
function i2l_openen($pad) {
    $fp = @fopen($pad, 'rb');
    if (!$fp) { return null; }
    $kop = fread($fp, 64);
    if (strlen($kop) < 32) { fclose($fp); return null; }

    $kolommen = ord($kop[1]);
    $db = array(
        'fp'        => $fp,
        'type'      => ord($kop[0]),
        'kolommen'  => $kolommen,
        'datum'     => sprintf('%04d-%02d-%02d', 2000 + ord($kop[2]), ord($kop[3]), ord($kop[4])),
        'breedte4'  => $kolommen * 4,
        'breedte6'  => $kolommen * 4 + 12,
        'aantal4'   => unpack('V', substr($kop, 5, 4))[1],
        'basis4'    => unpack('V', substr($kop, 9, 4))[1],
        'aantal6'   => unpack('V', substr($kop, 13, 4))[1],
        'basis6'    => unpack('V', substr($kop, 17, 4))[1],
        'index4'    => unpack('V', substr($kop, 21, 4))[1],
        'index6'    => unpack('V', substr($kop, 25, 4))[1],
    );
    // Eenvoudige plausibiliteitscontrole (bijv. als er een HTML-foutpagina is gedownload).
    if ($kolommen < 2 || $db['aantal4'] < 1 || $db['basis4'] < 32 || ord($kop[3]) < 1 || ord($kop[3]) > 12) {
        fclose($fp);
        return null;
    }
    return $db;
}

function i2l_sluiten(array $db) {
    if (is_resource($db['fp'])) { fclose($db['fp']); }
}

function i2l_lees(array $db, $positie, $lengte) {
    fseek($db['fp'], $positie);
    $data = fread($db['fp'], $lengte);
    return $data === false ? '' : $data;
}

/**
 * Zet een IP om naar [versie, sleutel]. Sleutel is een 32-bit getal (IPv4) of een
 * 16-byte big-endian string (IPv6). IPv4-in-IPv6, 6to4 en Teredo worden als IPv4 behandeld.
 */
function i2l_ip_sleutel($ip) {
    $bin = @inet_pton($ip);
    if ($bin === false) { return array(0, null); }
    if (strlen($bin) === 4) {
        return array(4, unpack('N', $bin)[1]);
    }
    // ::ffff:a.b.c.d
    if (substr($bin, 0, 12) === str_repeat("\0", 10) . "\xff\xff") {
        return array(4, unpack('N', substr($bin, 12, 4))[1]);
    }
    // 6to4: 2002:AABB:CCDD::/48
    if (substr($bin, 0, 2) === "\x20\x02") {
        return array(4, unpack('N', substr($bin, 2, 4))[1]);
    }
    // Teredo: 2001:0000::/32, client-IPv4 = laatste 32 bits omgekeerd
    if (substr($bin, 0, 4) === "\x20\x01\x00\x00" && substr($bin, 12, 4) !== "\0\0\0\0") {
        return array(4, (~unpack('N', substr($bin, 12, 4))[1]) & 0xFFFFFFFF);
    }
    return array(6, $bin);
}

/** Landcode (2 letters) voor een IP, of '' als onbekend of niet in de database. */
function i2l_land(array $db, $ip) {
    list($versie, $sleutel) = i2l_ip_sleutel($ip);
    if ($versie === 0) { return ''; }
    if ($versie === 6 && $db['aantal6'] < 1) { return ''; } // IPv4-only bestand

    $basis   = $db['basis' . $versie];
    $breedte = $db['breedte' . $versie];
    $laag    = 0;
    $hoog    = $db['aantal' . $versie];
    $index   = $db['index' . $versie];

    // Index op de eerste 16 bits verkleint het zoekgebied sterk.
    if ($index > 1) {
        $n = $versie === 4 ? ($sleutel >> 16) : unpack('n', substr($sleutel, 0, 2))[1];
        $sectie = i2l_lees($db, $index - 1 + ($n << 3), 8);
        if (strlen($sectie) === 8) {
            $laag = unpack('V', substr($sectie, 0, 4))[1];
            $hoog = unpack('V', substr($sectie, 4, 4))[1];
        }
    }
    if ($versie === 4 && $sleutel >= 4294967295) { $sleutel = 4294967294; }

    $ipLen = $versie === 4 ? 4 : 16;
    while ($laag <= $hoog) {
        $mid = $laag + (($hoog - $laag) >> 1);
        $pos = $basis + $breedte * $mid - 1;
        $blok = i2l_lees($db, $pos, $breedte + $ipLen);
        if (strlen($blok) < $breedte + $ipLen) { return ''; }

        if ($versie === 4) {
            $van = unpack('V', substr($blok, 0, 4))[1];
            $tot = unpack('V', substr($blok, $breedte, 4))[1];
            $cmpVan = $sleutel <=> $van;
            $cmpTot = $sleutel <=> $tot;
        } else {
            $van = strrev(substr($blok, 0, 16));          // little-endian -> big-endian
            $tot = strrev(substr($blok, $breedte, 16));
            $cmpVan = strcmp($sleutel, $van) <=> 0;
            $cmpTot = strcmp($sleutel, $tot) <=> 0;
        }

        if ($cmpVan >= 0 && $cmpTot < 0) {
            // Kolom 2 = verwijzing naar de landcode (lengtebyte + 2 tekens).
            $ptr = unpack('V', substr($blok, $ipLen, 4))[1];
            $code = i2l_lees($db, $ptr + 1, 2);
            return preg_match('/^[A-Z]{2}$/', $code) ? $code : '';
        }
        if ($cmpVan < 0) { $hoog = $mid - 1; } else { $laag = $mid + 1; }
    }
    return '';
}
