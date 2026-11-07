<?php
// ELOK – wyszukiwanie firm: CEIDG (rejestr firm) i Google Maps (Places API) + szukanie maila na stronie www
if (!defined('ELOK')) { http_response_code(403); exit; }

function ceidg_base() { return defined('CEIDG_URL') ? CEIDG_URL : 'https://dane.biznes.gov.pl/api/ceidg/v3'; }
function google_base() { return defined('GOOGLE_PLACES_URL') ? GOOGLE_PLACES_URL : 'https://places.googleapis.com/v1/places:searchText'; }

function sz_migrate() {
    db()->exec("CREATE TABLE IF NOT EXISTS szukaj (
      id INT AUTO_INCREMENT PRIMARY KEY, zrodlo VARCHAR(10) NOT NULL, ext_id VARCHAR(190) NOT NULL,
      nazwa VARCHAR(255) NOT NULL DEFAULT '', osoba VARCHAR(160) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '',
      telefon VARCHAR(60) NOT NULL DEFAULT '', www VARCHAR(255) NOT NULL DEFAULT '', miasto VARCHAR(120) NOT NULL DEFAULT '',
      adres VARCHAR(255) NOT NULL DEFAULT '', nip VARCHAR(20) NOT NULL DEFAULT '', branza_id INT NULL,
      status VARCHAR(20) NOT NULL DEFAULT 'czeka', created_at DATETIME NULL,
      UNIQUE KEY ze (zrodlo, ext_id), KEY st (status), KEY br (branza_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    add_col('firmy', 'zrodlo', "VARCHAR(10) NOT NULL DEFAULT ''");
}

// ---------- HTTP ----------
function http_req($method, $url, $headers = array(), $body = null, $timeout = 15, $maxBytes = 3000000) {
    if (!function_exists('curl_init')) return array(0, '', 'Brak rozszerzenia curl na serwerze');
    $c = curl_init($url);
    $opt = array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 6, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 4,
        CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ELOK-panel/1.0; +https://elok-agency.pl)', CURLOPT_ENCODING => '');
    if ($method === 'POST') { $opt[CURLOPT_POST] = true; $opt[CURLOPT_POSTFIELDS] = $body; }
    curl_setopt_array($c, $opt);
    $r = curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE);
    $err = curl_error($c);
    curl_close($c);
    if ($r === false) return array(0, '', $err);
    if (strlen($r) > $maxBytes) $r = substr($r, 0, $maxBytes);
    return array($code, $r, '');
}

// ---------- CEIDG ----------
function ceidg_wait() {
    $until = (int)setting('ceidg_blok_do', '0');
    if ($until > time()) return 'CEIDG chwilowo blokuje zapytania (limit). Spróbuj za ' . ($until - time()) . ' s.';
    $last = (float)setting('ceidg_last', '0');
    $d = microtime(true) - $last;
    if ($d < 3.7) usleep((int)((3.7 - $d) * 1000000));
    setting_set('ceidg_last', (string)microtime(true));
    return '';
}
function ceidg_token() {
    $t = trim((string)setting('ceidg_token'));
    $t = preg_replace('/^(authorization:\s*)?bearer\s+/i', '', $t); // ktoś wkleił "Bearer eyJ..."
    return preg_replace('/\s+/', '', trim($t, " \t\n\r\"'"));
}
// zwraca też kod HTTP i fragment odpowiedzi (do diagnozy błędu 400)
function ceidg_get($path, &$code = null, &$info = null) {
    $tok = ceidg_token();
    if ($tok === '') return array(null, 'Brak tokenu CEIDG – wpisz go w Ustawieniach.');
    $w = ceidg_wait(); if ($w) return array(null, $w);
    list($code, $body, $err) = http_req('GET', ceidg_base() . $path, array('Authorization: Bearer ' . $tok, 'Accept: application/json'), null, 45);
    $info = '';
    if ($code >= 400) {
        $jj = json_decode($body, true);
        if (is_array($jj)) { foreach (array('message', 'msg', 'error_description', 'error', 'detail', 'title', 'opis') as $k) if (!empty($jj[$k]) && is_string($jj[$k])) { $info = $jj[$k]; break; } if ($info === '') $info = json_encode($jj, JSON_UNESCAPED_UNICODE); }
        else $info = trim(strip_tags((string)$body));
        $info = mb_substr(preg_replace('/\s+/', ' ', $info), 0, 220);
        setting_set('ceidg_debug', date('Y-m-d H:i:s') . ' ' . $code . ' ' . $path . ' → ' . $info);
    }
    if ($code === 429) { setting_set('ceidg_blok_do', (string)(time() + 200)); return array(null, 'Za dużo zapytań do CEIDG – odczekaj ok. 3 minuty.'); }
    if ($code === 204) return array(array(), '');
    if ($code === 401 || $code === 403) return array(null, 'CEIDG odrzucił token (' . $code . '). Sprawdź token w Ustawieniach.');
    if ($code !== 200) return array(null, 'CEIDG: błąd ' . ($code ?: $err) . ($info !== '' ? ' – ' . $info : ''));
    $j = json_decode($body, true);
    return array(is_array($j) ? $j : array(), '');
}
function ceidg_miasto($m) {
    $m = mb_convert_case(mb_strtolower(trim(preg_replace('/\s+/', ' ', $m))), MB_CASE_TITLE);
    return preg_replace_callback('/(?<=\s)(Nad|Pod|Przy|W|We|Z|Ze|Na|Koło)(?=\s)/u', function ($x) { return mb_strtolower($x[1]); }, $m);
}
function ceidg_pkd_norm($p) { return strtoupper(preg_replace('/[^0-9A-Za-z]/', '', $p)); }

// zwraca array(dodane, razem_w_ceidg, jest_nastepna, blad)
function ceidg_search($pkd_list, $miasto, $woj, $page, $branza_id, $extra = array()) {
    $base = array();
    foreach ($pkd_list as $p) { $p = ceidg_pkd_norm($p); if ($p !== '') $base[] = 'pkd=' . rawurlencode($p); }
    if (!$base) return array(0, 0, false, 'Podaj kod PKD');
    // CEIDG rozróżnia wielkość liter – "konstancin-jeziorna" → "Konstancin-Jeziorna"
    if ($miasto !== '') $base[] = 'miasto=' . rawurlencode(ceidg_miasto($miasto));
    if ($woj !== '') $base[] = 'wojewodztwo=' . rawurlencode(mb_strtolower(trim($woj)));
    foreach ($extra as $k => $v) $base[] = $k . '=' . rawurlencode($v);
    // Kilka wariantów zapytania – jeśli CEIDG zwróci 400, próbujemy prostszego (zapamiętujemy, który zadziałał)
    $wars = array(
        'a' => array('status=AKTYWNY', 'limit=50', 'page=' . (int)$page),
        'b' => array('status=AKTYWNY', 'limit=25', 'page=' . ((int)$page + 1)),
        'c' => array('status=AKTYWNY', 'page=' . ((int)$page + 1)),
        'd' => array('limit=25', 'page=' . ((int)$page + 1)),
        'e' => $page ? array('page=' . ((int)$page + 1)) : array(),
    );
    $ok = setting('ceidg_wariant'); 
    $order = array_keys($wars);
    if ($ok !== '' && isset($wars[$ok])) { $order = array_values(array_diff($order, array($ok))); array_unshift($order, $ok); }
    $j = null; $err = ''; $first = '';
    foreach ($order as $k) {
        $code = 0; $info = '';
        list($j, $err) = ceidg_get('/firmy?' . implode('&', array_merge($base, $wars[$k])), $code, $info);
        if (!$err) { if ($ok !== $k) setting_set('ceidg_wariant', $k); break; }
        if ($first === '') $first = $err;
        if ($code !== 400) break;
    }
    if ($err) return array(0, 0, false, $first !== '' ? $first : $err);
    $list = isset($j['firmy']) && is_array($j['firmy']) ? $j['firmy'] : array();
    $added = 0;
    foreach ($list as $f) {
        if (empty($f['id'])) continue;
        $a = isset($f['adresDzialalnosci']) ? $f['adresDzialalnosci'] : array();
        $w = isset($f['wlasciciel']) ? $f['wlasciciel'] : array();
        $adres = trim((isset($a['ulica']) ? $a['ulica'] : '') . ' ' . (isset($a['budynek']) ? $a['budynek'] : '') . (isset($a['lokal']) && $a['lokal'] !== '' ? '/' . $a['lokal'] : '') . ', ' . (isset($a['kod']) ? $a['kod'] : '') . ' ' . (isset($a['miasto']) ? $a['miasto'] : ''), ' ,');
        $osoba = trim((isset($w['imie']) ? mb_convert_case(mb_strtolower($w['imie']), MB_CASE_TITLE) : '') . ' ' . (isset($w['nazwisko']) ? mb_convert_case(mb_strtolower($w['nazwisko']), MB_CASE_TITLE) : ''));
        $nm = trim((string)(isset($f['nazwa']) ? $f['nazwa'] : ''));
        if ($nm !== '' && mb_strtoupper($nm) === $nm) $nm = mb_convert_case(mb_strtolower($nm), MB_CASE_TITLE); // CEIDG często WIELKIMI LITERAMI
        $s = q('INSERT IGNORE INTO szukaj(zrodlo,ext_id,nazwa,osoba,miasto,adres,nip,branza_id,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
            array('ceidg', (string)$f['id'], mb_substr($nm, 0, 255), mb_substr($osoba, 0, 160), mb_substr((string)(isset($a['miasto']) ? $a['miasto'] : ''), 0, 120), mb_substr($adres, 0, 255), (string)(isset($w['nip']) ? $w['nip'] : ''), $branza_id ?: null, 'czeka', now()));
        $added += $s->rowCount();
    }
    $total = isset($j['count']) ? (int)$j['count'] : count($list);
    $next = !empty($j['links']['next']) || count($list) >= 25;
    return array($added, $total, $next, '');
}

// szczegóły (e-mail, telefon, www) dla kilku firm naraz; zwraca liczbę zaktualizowanych lub błąd
function ceidg_details_batch($rows) {
    if (!$rows) return array(0, '');
    $byId = array(); foreach ($rows as $r) $byId[$r['ext_id']] = $r;
    $max = max(1, (int)setting('ceidg_max_ids', '10'));
    if (count($byId) > $max) $byId = array_slice($byId, 0, $max, true);
    if ($max === 1) {
        foreach ($byId as $id => $r) {
            list($j1, $err1) = ceidg_get('/firma/' . rawurlencode($id));
            if ($err1) return array(0, $err1);
            $f = isset($j1['firma']) ? (isset($j1['firma'][0]) ? $j1['firma'][0] : $j1['firma']) : $j1;
            sz_apply_ceidg($r, is_array($f) ? $f : array());
        }
        return array(count($byId), '');
    }
    $qs = array(); foreach ($byId as $id => $r) $qs[] = 'ids=' . rawurlencode($id);
    $code = 0; $info = '';
    list($j, $err) = ceidg_get('/firma?' . implode('&', $qs), $code, $info);
    if ($err && $code === 400) {
        // "Maksymalna ilość identyfikatorów wpisów to N" – zapamiętaj N i spróbuj w następnym kroku
        $n = preg_match('/(\d+)/', $info, $mm) ? (int)$mm[1] : 0;
        $n = ($n >= 1 && $n < count($byId)) ? $n : 1;
        setting_set('ceidg_max_ids', (string)$n);
        return array(0, '');
    }
    if ($err) return array(0, $err);
    $items = array();
    if (isset($j['firma'])) $items = isset($j['firma'][0]) ? $j['firma'] : array($j['firma']);
    elseif (isset($j['firmy'])) $items = $j['firmy'];
    $done = 0;
    foreach ($items as $f) {
        if (empty($f['id']) || !isset($byId[$f['id']])) continue;
        sz_apply_ceidg($byId[$f['id']], $f);
        unset($byId[$f['id']]);
        $done++;
    }
    // jeśli API nie obsłużyło kilku ID naraz – pobierz pojedynczo (max 2 na raz, reszta w kolejnym kroku)
    if ($done === 0 && $byId) {
        $k = 0;
        foreach ($byId as $id => $r) {
            if ($k++ >= 2) break;
            list($j1, $err1) = ceidg_get('/firma/' . rawurlencode($id));
            if ($err1) return array($done, $err1);
            $f = isset($j1['firma']) ? (isset($j1['firma'][0]) ? $j1['firma'][0] : $j1['firma']) : $j1;
            sz_apply_ceidg($r, is_array($f) ? $f : array());
            $done++;
        }
    } elseif ($byId) {
        foreach ($byId as $r) q("UPDATE szukaj SET status='brak_maila' WHERE id=?", array($r['id']));
    }
    return array($done, '');
}
function sz_apply_ceidg($row, $f) {
    $email = mb_strtolower(trim((string)(isset($f['email']) ? $f['email'] : '')));
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
    $tel = trim((string)(isset($f['telefon']) ? $f['telefon'] : ''));
    $www = trim((string)(isset($f['www']) ? $f['www'] : ''));
    if ($www !== '' && !preg_match('#^https?://#i', $www)) $www = 'http://' . $www;
    $status = $email ? 'gotowe' : ($www ? 'czeka_www' : 'brak_maila');
    if ($email && firm_dup_find($email, $tel, '', '', $www)) $status = 'juz_jest';
    q('UPDATE szukaj SET email=?, telefon=?, www=?, status=? WHERE id=?', array($email, mb_substr($tel, 0, 60), mb_substr($www, 0, 255), $status, $row['id']));
}

// ---------- GOOGLE PLACES ----------
// zwraca array(dodane, next_token, blad)
function google_search($query, $token, $branza_id) {
    $key = trim(setting('google_key'));
    if ($key === '') return array(0, '', 'Brak klucza Google – wpisz go w Ustawieniach.');
    $body = array('textQuery' => $query, 'languageCode' => 'pl', 'regionCode' => 'PL', 'pageSize' => 20);
    if ($token) $body['pageToken'] = $token;
    list($code, $resp, $err) = http_req('POST', google_base(), array('Content-Type: application/json', 'X-Goog-Api-Key: ' . $key,
        'X-Goog-FieldMask: places.id,places.displayName,places.formattedAddress,places.nationalPhoneNumber,places.websiteUri,places.addressComponents,nextPageToken'), json_encode($body, JSON_UNESCAPED_UNICODE), 20);
    $j = json_decode($resp, true);
    if ($code !== 200) {
        $m = isset($j['error']['message']) ? $j['error']['message'] : ($err ?: 'HTTP ' . $code);
        return array(0, '', 'Google: ' . $m);
    }
    $added = 0;
    foreach ((isset($j['places']) ? $j['places'] : array()) as $p) {
        if (empty($p['id'])) continue;
        $miasto = '';
        foreach ((isset($p['addressComponents']) ? $p['addressComponents'] : array()) as $ac) if (in_array('locality', isset($ac['types']) ? $ac['types'] : array(), true)) $miasto = isset($ac['longText']) ? $ac['longText'] : '';
        $www = isset($p['websiteUri']) ? (string)$p['websiteUri'] : '';
        $s = q('INSERT IGNORE INTO szukaj(zrodlo,ext_id,nazwa,telefon,www,miasto,adres,branza_id,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)',
            array('google', (string)$p['id'], mb_substr((string)(isset($p['displayName']['text']) ? $p['displayName']['text'] : ''), 0, 255), mb_substr((string)(isset($p['nationalPhoneNumber']) ? $p['nationalPhoneNumber'] : ''), 0, 60),
                mb_substr($www, 0, 255), mb_substr($miasto, 0, 120), mb_substr((string)(isset($p['formattedAddress']) ? $p['formattedAddress'] : ''), 0, 255), $branza_id ?: null, $www ? 'czeka_www' : 'brak_maila', now()));
        $added += $s->rowCount();
    }
    return array($added, isset($j['nextPageToken']) ? (string)$j['nextPageToken'] : '', '');
}

// ---------- FILTR: ASO / salony marek / sieci ----------
function car_brands_re() { return 'toyota|lexus|bmw|mini|audi|mercedes|volkswagen|vw|skoda|škoda|seat|cupra|ford|opel|renault|dacia|peugeot|citro[eë]n|ds automobiles|kia|hyundai|mazda|honda|nissan|volvo|fiat|alfa romeo|jeep|porsche|suzuki|mitsubishi|subaru|tesla|land rover|jaguar|mg motor|byd|polestar|infiniti|chevrolet|smart|lancia'; }
// 'aso' = autoryzowane serwisy / salony marek, 'siec' = sieci warsztatów
function firm_kind($name, $brand = '', $email = '', $miasto = '') {
    $n = ' ' . mb_strtolower($name) . ' ';
    $b = mb_strtolower(trim($brand));
    $br = car_brands_re();
    // 1) wyraźne ASO / dealer
    if (preg_match('/\b(aso|autoryzowan\w*|dealer\w*|salon\w*|autohaus|main dealer|approved used|showroom)\b/u', $n)) return 'aso';
    // 2) tag marki samochodów na mapie
    if ($b !== '' && preg_match('/^(' . $br . ')\b/u', $b)) return 'aso';
    // 3) marka w domenie maila (np. @hyundai.krakow.pl, @bmw-dobrzanski.pl) – zwykle dealer
    $dom = $email !== '' ? mb_strtolower(substr(strrchr($email, '@'), 1)) : '';
    if ($dom !== '' && preg_match('/(^|[.\-])(' . $br . ')([.\-])/u', $dom) && !preg_match('/(serwis|service|specjal|naprawa|tuning|garage|warsztat)/u', $dom)) return 'aso';
    // 4) marka w nazwie: ASO tylko gdy poza marką są same ogólne słowa ("Serwis Hyundai", "Toyota Auto Park Kraków").
    //    Gdy jest własna nazwa ("Hypertech BMW Serwis", "ServiceMile BMW") – to niezależny specjalista.
    if (preg_match('/\b(' . $br . ')\b/u', $n)) {
        $rest = preg_replace('/\b(' . $br . ')\b/u', ' ', $n);
        $generic = 'benz|serwis|service|servis|centrum|center|centre|auto|autoserwis|car|cars|motors?|park|group|grupa|polska|poland|pl|sp|spółka|z|o|oo|sa|sc|i|oraz|and|samochod\w*|obsługa|osobowe|dostawcze|nowe|używane|stacja|punkt|oddział|plaza|premium|selection|bemowo|mokotów|wola|ursynów|praga|targówek|wawer|białołęka|bielany|ochota|żoliborz|wilanów|włochy|ursus|rembertów|wesoła|śródmieście|warszawa|kraków|wrocław|poznań|gdańsk|gdynia|sopot|łódź|katowice|lublin|szczecin|bydgoszcz|toruń|rzeszów|kielce|radom|olsztyn|opole|białystok|częstochowa|piaseczno|konstancin';
        if ($miasto !== '') $generic .= '|' . preg_quote(mb_strtolower($miasto), '/');
        $rest = preg_replace('/\b(' . $generic . ')\b/u', ' ', $rest);
        $rest = preg_replace('/[^\p{L}]+/u', ' ', $rest);
        $words = array_filter(explode(' ', $rest), function ($w) { return mb_strlen($w) >= 3; });
        if (!$words) return 'aso';
    }
    // 5) sieci warsztatów
    if (preg_match('/(bosch car service|profiauto|profi auto|q service|euromaster|norauto|midas|feu vert|speedy|point s|first stop|vianor|premio|moto profil|eurowarsztat|motointegrator|oponeo|mobil 1 center|fast fit|kwik fit|kwik-fit|halfords|formula one autocentre|f1 autocentre|national tyres|protyre|mr clutch|ats euromaster|setyres|lookers|arnold clark|evans halshaw)/u', $n)) return 'siec';
    if ($b !== '') return 'siec';
    return '';
}

// ---------- OPENSTREETMAP (Overpass API, darmowe) ----------
function osm_bases() {
    if (defined('OVERPASS_URL')) return (array)OVERPASS_URL;
    return array('https://overpass-api.de/api/interpreter', 'https://overpass.kumi.systems/api/interpreter', 'https://maps.mail.ru/osm/tools/overpass/api/interpreter', 'https://overpass.private.coffee/api/interpreter');
}
function osm_tags() {
    return array(
        'car_repair' => array('Warsztaty samochodowe', '["shop"="car_repair"]'),
        'tyres' => array('Opony / wulkanizacja', '["shop"="tyres"]'),
        'car_parts' => array('Części samochodowe (sklepy, hurtownie)', '["shop"="car_parts"]'),
        'car' => array('Salony / komisy aut', '["shop"="car"]'),
        'motorcycle' => array('Motocykle', '["shop"="motorcycle"]'),
        'car_wash' => array('Myjnie / detailing', '["amenity"="car_wash"]'),
        'vehicle_inspection' => array('Stacje kontroli pojazdów (SKP)', '["amenity"="vehicle_inspection"]'),
    );
}
// zwraca array(dodane, znalezione, blad)
function osm_search($tag, $miasto, $woj, $branza_id, $srv = 0, $skipAso = true, $skipSiec = false, $km = 0, $kraj = 'pl') {
    $T = osm_tags();
    if (!isset($T[$tag])) return array(0, 0, 'Wybierz rodzaj firm');
    $esc = function ($x) { return str_replace(array('\\', '"'), array('\\\\', '\\"'), $x); };
    $uk = $kraj === 'gb';
    if ($uk) { // Wielka Brytania: miasto + promień (nazwy miast mogą się powtarzać, więc szukamy tylko w granicach UK)
        if ($miasto === '') return array(0, 0, 'Wpisz miasto, np. Manchester');
        $km = max(3, min(50, (int)$km ?: 15));
        $ql = '[out:json][timeout:25];node["name"="' . $esc($miasto) . '"]["place"~"^(city|town)$"](49.8,-8.7,60.9,1.8)->.c;'
            . '(nwr' . $T[$tag][1] . '(around.c:' . ($km * 1000) . '););out center tags 600;';
    }
    if ($uk) { $area = ''; }
    elseif ($miasto !== '') $area = 'area["name"="' . $esc($miasto) . '"]["boundary"="administrative"]["admin_level"~"^(6|7|8|9)$"]->.a;';
    elseif ($woj !== '') $area = 'area["name"="województwo ' . $esc($woj) . '"]["admin_level"="4"]->.a;';
    else return array(0, 0, 'Podaj miasto albo województwo');
    if (!$uk) $km = max(0, min(50, (int)$km));
    if ($uk) { /* $ql gotowe wyżej */ }
    elseif ($miasto !== '' && $km > 0) {
        // miasto + okolice: granice miasta oraz promień od centrum miasta
        $ql = '[out:json][timeout:25];' . $area . 'node["name"="' . $esc($miasto) . '"]["place"~"^(city|town|village)$"]->.c;'
            . '(nwr' . $T[$tag][1] . '(area.a);nwr' . $T[$tag][1] . '(around.c:' . ($km * 1000) . '););out center tags 600;';
    } else {
        $ql = '[out:json][timeout:25];' . $area . '(nwr' . $T[$tag][1] . '(area.a););out center tags 600;';
    }
    $from = setting('nadawca_email') ?: 'kontakt@elok-agency.pl';
    $hdr = array('Content-Type: application/x-www-form-urlencoded', 'Accept: application/json',
        'User-Agent: ELOK-panel/1.0 (+' . BASE_URL . '; ' . $from . ')', 'Referer: ' . BASE_URL . '/');
    // jeden serwer na jedno zapytanie (hosting przerywa długie żądania) – panel sam przechodzi do kolejnego
    $bases = osm_bases();
    $srv = max(0, (int)$srv);
    if ($srv >= count($bases)) $srv = 0;
    list($code, $body, $err) = http_req('POST', $bases[$srv], $hdr, 'data=' . rawurlencode($ql), 35);
    if ($code !== 200 || strpos(ltrim($body), '{') !== 0) {
        return array(0, 0, 'RETRY:' . parse_url($bases[$srv], PHP_URL_HOST) . ' ' . ($code ?: 'brak odpowiedzi'));
    }
    $j = json_decode($body, true);
    $els = isset($j['elements']) && is_array($j['elements']) ? $j['elements'] : array();
    $added = 0; $found = 0; $skipped = 0;
    foreach ($els as $e) {
        $t = isset($e['tags']) ? $e['tags'] : array();
        $name = trim(isset($t['name']) ? $t['name'] : (isset($t['brand']) ? $t['brand'] : ''));
        if ($name === '') continue;
        $kind = firm_kind($name, isset($t['brand']) ? $t['brand'] : '', isset($t['email']) ? $t['email'] : (isset($t['contact:email']) ? $t['contact:email'] : ''), $miasto);
        $filtered = ($kind === 'aso' && $skipAso) || ($kind === 'siec' && $skipSiec);
        if ($filtered) $skipped++;
        $found++;
        $g = function ($keys) use ($t) { foreach ($keys as $k) if (!empty($t[$k])) return trim(explode(';', $t[$k])[0]); return ''; };
        $email = mb_strtolower($g(array('email', 'contact:email')));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $email = '';
        $www = $g(array('website', 'contact:website', 'url'));
        if ($www !== '' && !preg_match('#^https?://#i', $www)) $www = 'http://' . $www;
        $city = $g(array('addr:city', 'addr:place'));
        if ($city === '') $city = $miasto;
        $adres = trim($g(array('addr:street')) . ' ' . $g(array('addr:housenumber')) . ($city ? ', ' . $city : ''), ' ,');
        $status = $email ? ($uk ? 'czeka_ch' : 'gotowe') : ($www ? 'czeka_www' : 'brak_maila');
        if (($email || $www) && firm_dup_find($email, $g(array('phone', 'contact:phone', 'contact:mobile')), $name, $city, $www)) $status = 'juz_jest';
        if ($filtered) $status = 'aso'; // odfiltrowane – do podglądu i przywrócenia
        if ($uk) $adres = trim($adres . ' ' . $g(array('addr:postcode')));
        $s = q('INSERT IGNORE INTO szukaj(zrodlo,ext_id,nazwa,email,telefon,www,miasto,adres,branza_id,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
            array($uk ? 'uk' : 'osm', $e['type'] . '/' . $e['id'], mb_substr($name, 0, 255), $email, mb_substr($g(array('phone', 'contact:phone', 'contact:mobile')), 0, 60), mb_substr($www, 0, 255), mb_substr($city, 0, 120), mb_substr($adres, 0, 255), $branza_id ?: null, $status, now()));
        $added += $s->rowCount();
    }
    return array($added, $found, '', $skipped);
}

// ---------- E-MAIL ZE STRONY WWW ----------
function emails_in($html) {
    $html = str_ireplace(array('&#64;', '&#x40;', '%40', '[at]', '(at)', ' [małpa] '), '@', $html);
    $html = html_entity_decode($html, ENT_QUOTES, 'UTF-8');
    preg_match_all('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9\-]+(\.[A-Za-z0-9\-]+)*\.[A-Za-z]{2,}/', $html, $m);
    $out = array();
    foreach ($m[0] as $e) {
        $e = mb_strtolower(trim($e, '.'));
        if (preg_match('/\.(png|jpe?g|gif|webp|svg|css|js)$/i', $e)) continue;
        if (preg_match('/(sentry|wixpress|example\.|domain\.|email\.com|yourname|twojemail|@2x|u00)/i', $e)) continue;
        if (!filter_var($e, FILTER_VALIDATE_EMAIL)) continue;
        $out[$e] = 1;
    }
    return array_keys($out);
}
function best_email($list, $www) {
    if (!$list) return '';
    $host = preg_replace('/^www\./', '', strtolower((string)parse_url($www, PHP_URL_HOST)));
    $score = function ($e) use ($host) {
        $s = 0; $d = substr(strrchr($e, '@'), 1);
        if ($host && ($d === $host || substr($host, -strlen($d)) === $d)) $s += 10;
        if (preg_match('/^(biuro|kontakt|info|serwis|warsztat|sklep|office|hello|zamowienia|sprzedaz)@/', $e)) $s += 5;
        if (preg_match('/^(noreply|no-reply|rodo|iod|abuse|webmaster|admin)@/', $e)) $s -= 20;
        return $s;
    };
    usort($list, function ($a, $b) use ($score) { return $score($b) - $score($a); });
    return $score($list[0]) < -5 ? '' : $list[0];
}
function site_email($www) {
    if (!preg_match('#^https?://#i', $www)) $www = 'http://' . $www;
    list($code, $html) = http_req('GET', $www, array('Accept: text/html'), null, 8, 800000);
    if ($code < 200 || $code >= 400 || $html === '') return '';
    $found = emails_in($html);
    if (!$found || !best_email($found, $www)) {
        // szukaj podstrony kontaktowej
        $links = array();
        if (preg_match_all('/href=["\']([^"\'#]*(kontakt|contact|o-nas|about)[^"\']*)["\']/i', $html, $mm)) $links = array_slice(array_unique($mm[1]), 0, 2);
        if (!$links) $links = array('/kontakt', '/contact');
        foreach ($links as $l) {
            if (stripos($l, 'mailto:') === 0) continue;
            if (!preg_match('#^https?://#i', $l)) {
                $p = parse_url($www);
                $base = (isset($p['scheme']) ? $p['scheme'] : 'http') . '://' . (isset($p['host']) ? $p['host'] : '') . (isset($p['port']) ? ':' . $p['port'] : '');
                if ($l !== '' && $l[0] !== '/') { $dir = isset($p['path']) ? rtrim(preg_replace('#/[^/]*$#', '/', $p['path']), '/') : ''; $l = $dir . '/' . $l; }
                $l = $base . '/' . ltrim($l, '/');
            }
            list($c2, $h2) = http_req('GET', $l, array('Accept: text/html'), null, 8, 800000);
            if ($c2 >= 200 && $c2 < 400) $found = array_merge($found, emails_in($h2));
            if (best_email($found, $www)) break;
        }
    }
    return best_email(array_values(array_unique($found)), $www);
}

// Krok pobierania kontaktów (wywoływany w pętli z panelu). Zwraca array(zrobione, zostalo, blad)
function sz_step() {
    $done = 0; $err = '';
    $t0 = microtime(true);
    // 1) CEIDG – szczegóły w paczkach po 10
    for ($i = 0; $i < 2 && microtime(true) - $t0 < 15; $i++) {
        $rows = all("SELECT id, ext_id FROM szukaj WHERE zrodlo='ceidg' AND status='czeka' ORDER BY id LIMIT " . max(1, min(10, (int)setting('ceidg_max_ids', '10'))));
        if (!$rows) break;
        list($n, $e) = ceidg_details_batch($rows);
        $done += $n;
        if ($e) { $err = $e; break; }
    }
    // 2) strony www – szukanie maila
    while (microtime(true) - $t0 < 18) {
        $r = one("SELECT id, www, zrodlo FROM szukaj WHERE status='czeka_www' ORDER BY id LIMIT 1");
        if (!$r) break;
        q("UPDATE szukaj SET status='szukam' WHERE id=?", array($r['id']));
        $email = site_email($r['www']);
        $st = $email ? ($r['zrodlo'] === 'uk' ? 'czeka_ch' : 'gotowe') : 'brak_maila';
        if ($email && firm_dup_find($email, '', '', '', $r['www'])) $st = 'juz_jest';
        q('UPDATE szukaj SET email=?, status=? WHERE id=?', array($email, $st, $r['id']));
        $done++;
    }
    // 3) UK: sprawdzenie w Companies House – piszemy tylko do spółek (Ltd/PLC/LLP), nie do jednoosobowych firm
    while (microtime(true) - $t0 < 24) {
        $r = one("SELECT id, nazwa, miasto, adres FROM szukaj WHERE status='czeka_ch' ORDER BY id LIMIT 1");
        if (!$r) break;
        list($st, $nr, $osoba, $e2) = ch_verify($r['nazwa'], $r['adres']);
        if ($e2) { $err = $e2; break; }
        q('UPDATE szukaj SET status=?, nip=?, osoba=IF(?<>\'\',?,osoba) WHERE id=?', array($st, $nr, $osoba, $osoba, $r['id']));
        $done++;
    }
    // odblokuj zawieszone (np. po przerwanym żądaniu)
    q("UPDATE szukaj SET status='czeka_www' WHERE status='szukam' AND created_at < ?", array(date('Y-m-d H:i:s', time() - 120)));
    $left = (int)val("SELECT COUNT(*) FROM szukaj WHERE status IN('czeka','czeka_www','czeka_ch')");
    return array($done, $left, $err);
}

// ---------- POBIERANIE W TLE (cron) – np. wszystkie warsztaty z Polski przez noc ----------
// przedziały daty rozpoczęcia działalności – mniejsze zapytania, gdy całe województwo się nie mieści w czasie
function noc_segs() {
    return array(array('1900-01-01', '1995-12-31'), array('1996-01-01', '2002-12-31'), array('2003-01-01', '2007-12-31'), array('2008-01-01', '2011-12-31'),
        array('2012-01-01', '2014-12-31'), array('2015-01-01', '2017-12-31'), array('2018-01-01', '2019-12-31'), array('2020-01-01', '2021-12-31'),
        array('2022-01-01', '2023-12-31'), array('2024-01-01', date('Y-12-31')));
}
function noc_woj_all() { return array('dolnośląskie', 'kujawsko-pomorskie', 'lubelskie', 'lubuskie', 'łódzkie', 'małopolskie', 'mazowieckie', 'opolskie', 'podkarpackie', 'podlaskie', 'pomorskie', 'śląskie', 'świętokrzyskie', 'warmińsko-mazurskie', 'wielkopolskie', 'zachodniopomorskie'); }
function noc_get() {
    $j = json_decode((string)setting('noc_zadanie'), true);
    return is_array($j) ? $j : array('on' => 0);
}
function noc_save($j) { setting_set('noc_zadanie', json_encode($j, JSON_UNESCAPED_UNICODE)); }
// czy w międzyczasie kliknięto "Zatrzymaj" albo uruchomiono inne pobieranie (inny start)
function noc_stopped($j) {
    setting('noc_zadanie'); // upewnij się, że ustawienia są wczytane
    $GLOBALS['_ST']['noc_zadanie'] = (string)val('SELECT v FROM ustawienia WHERE k=?', array('noc_zadanie')); // świeży odczyt z bazy
    $cur = noc_get();
    return empty($cur['on']) || (isset($cur['start'], $j['start']) && $cur['start'] !== $j['start']);
}
// zapis postępu tylko, gdy to pobieranie nadal jest aktywne – nie nadpisuje "Zatrzymaj"
function noc_save_run($j) { if (noc_stopped($j)) return; noc_save($j); }
function noc_end($msg) { val("SELECT RELEASE_LOCK('elok_noc')"); return $msg; }
function noc_in_hours($j) {
    if (empty($j['godz']) || $j['godz'] === 'zawsze') return true;
    $h = (int)date('G');
    return $h >= 22 || $h < 7;
}
function noc_counts($j) {
    $since = isset($j['start']) ? $j['start'] : '2000-01-01';
    $r = array('gotowe' => 0, 'czeka' => 0, 'brak' => 0, 'wszystkie' => 0);
    foreach (all("SELECT status, COUNT(*) n FROM szukaj WHERE zrodlo='ceidg' AND created_at >= ? GROUP BY status", array($since)) as $x) {
        $n = (int)$x['n']; $r['wszystkie'] += $n;
        if ($x['status'] === 'gotowe') $r['gotowe'] += $n;
        elseif (in_array($x['status'], array('czeka', 'czeka_www', 'szukam'), true)) $r['czeka'] += $n;
        elseif (in_array($x['status'], array('brak_maila', 'ukryty'), true)) $r['brak'] += $n;
    }
    return $r;
}
// jeden przebieg (wołany z crona); $budget = ile sekund wolno pracować
function noc_run($budget) {
    $j = noc_get();
    if (empty($j['on'])) return 'pobieranie wyłączone';
    if (!noc_in_hours($j)) return 'poza godzinami pobierania';
    if ((int)val("SELECT GET_LOCK('elok_noc', 0)") !== 1) return 'już działa';
    $t0 = microtime(true); $info = '';
    setting_set('noc_ostatnio', now());
    $woj = isset($j['woj']) && $j['woj'] ? $j['woj'] : noc_woj_all();
    $pkd = preg_split('/[\s,;]+/', (string)$j['pkd'], -1, PREG_SPLIT_NO_EMPTY);
    while (microtime(true) - $t0 < $budget) {
        if (noc_stopped($j)) return noc_end('zatrzymane z panelu');
        // najpierw dociągnij kontakty firm, które już są na liście
        $left = (int)val("SELECT COUNT(*) FROM szukaj WHERE status IN('czeka','czeka_www')");
        if ($left > 0) {
            list($n, $left, $err) = sz_step();
            if ($err) { $info = $err; if (stripos($err, 'limit') !== false || stripos($err, 'za dużo') !== false || stripos($err, 'blokuje') !== false) break; $j['bledy'] = (isset($j['bledy']) ? $j['bledy'] : 0) + 1; if ($j['bledy'] > 20) { $j['on'] = 0; $j['koniec'] = now(); $j['info'] = 'Zatrzymano – powtarzające się błędy: ' . $err; noc_save_run($j); push('Pobieranie zatrzymane', $err); break; } }
            continue;
        }
        if ((int)$j['wi'] >= count($woj)) {
            $j['on'] = 0; $j['koniec'] = now(); $c = noc_counts($j);
            $j['info'] = 'Gotowe – przeszukano wszystkie województwa.';
            noc_save_run($j);
            push('Pobieranie zakończone', 'Nowych firm: ' . $c['wszystkie'] . ' · z mailem: ' . $c['gotowe'], BASE_URL);
            break;
        }
        $w = $woj[(int)$j['wi']];
        $segs = noc_segs(); $seg = isset($j['seg']) ? (int)$j['seg'] : -1;
        $extra = $seg >= 0 ? array('dataod' => $segs[$seg][0], 'datado' => $segs[$seg][1]) : array();
        list($n, $total, $more, $err) = ceidg_search($pkd, '', $w, (int)$j['page'], (int)$j['branza_id'], $extra);
        if ($err) {
            $info = $err;
            // timeout / brak połączenia – CEIDG chwilowo wolny: NIE pomijaj niczego, spróbuj przy następnym cronie
            $siec = stripos($err, 'timed out') !== false || stripos($err, 'timeout') !== false || stripos($err, 'błąd 5') !== false || stripos($err, 'resolve') !== false || stripos($err, 'connect') !== false || preg_match('/błąd 0\b/u', $err);
            if (!$siec) {
                $j['bledy_w'] = (isset($j['bledy_w']) ? $j['bledy_w'] : 0) + 1;
                if ($j['bledy_w'] >= 5) { $j['page']++; $j['bledy_w'] = 0; } // pomiń tylko tę jedną stronę
            } else {
                // CEIDG nie wyrabia z dużym zapytaniem – dziel województwo na mniejsze części (wg daty założenia firmy)
                $j['to'] = (isset($j['to']) ? (int)$j['to'] : 0) + 1;
                if ($seg < 0 && $j['to'] >= 2) { $j['seg'] = 0; $j['page'] = 0; $j['to'] = 0; }
                elseif ($seg >= 0 && $j['to'] >= 6) { // ta część nie chce przejść – zanotuj i idź dalej
                    if (!isset($j['pominiete'])) $j['pominiete'] = array();
                    $j['pominiete'][] = $w . ' ' . $segs[$seg][0] . '–' . $segs[$seg][1] . ' (od strony ' . ((int)$j['page'] + 1) . ')';
                    $j['to'] = 0; $j['page'] = 0; $j['seg'] = $seg + 1;
                    if ($j['seg'] >= count($segs)) { $j['seg'] = !empty($j['seg_mode']) ? 0 : -1; $j['wi']++; }
                }
            }
            noc_save_run($j);
            break;
        }
        $j['bledy_w'] = 0; $j['to'] = 0;
        $j['dodano'] = (isset($j['dodano']) ? $j['dodano'] : 0) + $n;
        $j['razem_w'] = $total;
        if ($more && $j['page'] < 2000) $j['page']++;
        else {
            $j['page'] = 0;
            if ($seg >= 0 && $seg + 1 < count($segs)) $j['seg'] = $seg + 1;
            else { $j['seg'] = !empty($j['seg_mode']) ? 0 : -1; $j['wi']++; }
        }
        noc_save_run($j);
    }
    if (!empty($j['auto_usun'])) q("UPDATE szukaj SET status='ukryty' WHERE status='brak_maila' AND zrodlo='ceidg'");
    $j2 = noc_get(); // mogło zostać zatrzymane w międzyczasie z panelu
    if (!empty($j2['on']) && !empty($j['on'])) { $j['info'] = $info; noc_save_run($j); }
    // poranne podsumowanie (raz dziennie po 7:00)
    if ((int)date('G') >= 7 && setting('noc_raport') !== date('Y-m-d') && !empty($j['start']) && strtotime($j['start']) < strtotime(date('Y-m-d 07:00:00'))) {
        setting_set('noc_raport', date('Y-m-d'));
        $c = noc_counts($j);
        push('Nocne pobieranie', 'Nowych firm: ' . $c['wszystkie'] . ' · z mailem: ' . $c['gotowe'] . (!empty($j['on']) ? ' · trwa dalej (' . $woj[min((int)$j['wi'], count($woj) - 1)] . ')' : ''), BASE_URL);
    }
    val("SELECT RELEASE_LOCK('elok_noc')");
    return 'ok ' . round(microtime(true) - $t0) . ' s' . ($info ? ' | ' . $info : '');
}


// ---------- WIELKA BRYTANIA: Companies House (darmowe API, klucz w Ustawieniach) ----------
function ch_base() { return defined('CH_URL') ? CH_URL : 'https://api.company-information.service.gov.uk'; }
function ch_get($path) {
    $key = trim((string)setting('ch_key'));
    if ($key === '') return array(null, 'Brak klucza Companies House – wpisz go w Ustawieniach.');
    list($code, $body, $err) = http_req('GET', ch_base() . $path, array('Authorization: Basic ' . base64_encode($key . ':'), 'Accept: application/json'), null, 20);
    if ($code === 429) return array(null, 'Companies House: za dużo zapytań – panel spróbuje za chwilę.');
    if ($code === 401 || $code === 403) return array(null, 'Companies House odrzucił klucz (' . $code . '). Sprawdź klucz w Ustawieniach.');
    if ($code === 404) return array(array(), '');
    if ($code !== 200) return array(null, 'Companies House: błąd ' . ($code ?: $err));
    $j = json_decode($body, true);
    return array(is_array($j) ? $j : array(), '');
}
function ch_norm($n) {
    $n = mb_strtolower(html_entity_decode($n, ENT_QUOTES, 'UTF-8'));
    $n = str_replace(array('&', '+'), ' and ', $n);
    $n = preg_replace('/\b(limited|ltd|plc|llp|lp|the|company|co|uk|services?|garage|centre|center)\b/u', ' ', $n);
    return trim(preg_replace('/[^a-z0-9]+/', ' ', $n));
}
// zwraca array(status, numer_firmy, osoba, blad); status: gotowe | nie_ltd
function ch_verify($name, $adres) {
    list($j, $e) = ch_get('/search/companies?items_per_page=8&q=' . rawurlencode($name));
    if ($e) return array('czeka_ch', '', '', $e);
    $want = ch_norm($name);
    $pc = preg_match('/\b([A-Z]{1,2}\d[A-Z\d]?)\s*\d[A-Z]{2}\b/i', (string)$adres, $m) ? strtoupper($m[1]) : '';
    $best = null; $bestScore = 0;
    foreach ((array)(isset($j['items']) ? $j['items'] : array()) as $it) {
        if (empty($it['company_status']) || $it['company_status'] !== 'active') continue;
        if (!in_array(isset($it['company_type']) ? $it['company_type'] : '', array('ltd', 'plc', 'llp', 'private-limited-guarant-nsc', 'private-limited-guarant-nsc-limited-exemption', 'private-unlimited'), true)) continue;
        $got = ch_norm(isset($it['title']) ? $it['title'] : '');
        if ($got === '' || $want === '') continue;
        similar_text($want, $got, $pct);
        if (strpos($got, $want) !== false || strpos($want, $got) !== false) $pct = max($pct, 90);
        $addr = isset($it['address_snippet']) ? strtoupper($it['address_snippet']) : '';
        if ($pc !== '' && strpos(str_replace(' ', '', $addr), $pc) !== false) $pct += 10;
        if ($pct > $bestScore) { $bestScore = $pct; $best = $it; }
    }
    if (!$best || $bestScore < 75) return array('nie_ltd', '', '', '');
    $nr = (string)$best['company_number'];
    $osoba = '';
    list($o, $e2) = ch_get('/company/' . rawurlencode($nr) . '/officers?items_per_page=10&register_type=directors');
    if (!$e2 && !empty($o['items'])) foreach ($o['items'] as $of) {
        if (!empty($of['resigned_on']) || (isset($of['officer_role']) && $of['officer_role'] !== 'director')) continue;
        $nm = isset($of['name']) ? $of['name'] : ''; // "SMITH, John Paul"
        if (strpos($nm, ',') !== false) { list($sur, $first) = array_map('trim', explode(',', $nm, 2)); $first = strtok($first, ' '); $osoba = mb_convert_case(mb_strtolower($first . ' ' . $sur), MB_CASE_TITLE); }
        break;
    }
    return array('gotowe', $nr, $osoba, '');
}
