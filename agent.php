<?php
// ELOK – Agent Eli: sam szuka firm, którym możemy pomóc, i dokłada je do mailingu.
// Zadania: 1) nowo zarejestrowane firmy (CEIDG) – gratulacje + strona,
//          2) firmy, które szukają pracownika biurowego (ogłoszenia OLX) – automatyzacje zamiast etatu.
// Działa z crona (cron.php) i z przycisku „Uruchom teraz” w zakładce Agent Eli.
if (!defined('ELOK')) { http_response_code(403); exit; }
require_once __DIR__ . '/szablony.php';
require_once __DIR__ . '/zrodla.php';

define('AG_SCHEMA', '1');
define('AG_BR_NOWE', 'Eli – nowe firmy');
define('AG_BR_PRACA', 'Eli – szukają pracownika');

function ag_migrate_if() { if (setting('ag_schema') !== AG_SCHEMA) { ag_migrate(); setting_set('ag_schema', AG_SCHEMA); } }
function ag_migrate() {
    sz_migrate();
    db()->exec("CREATE TABLE IF NOT EXISTS ag_log (
      id INT AUTO_INCREMENT PRIMARY KEY, zad VARCHAR(12) NOT NULL DEFAULT '', typ VARCHAR(20) NOT NULL DEFAULT '', info VARCHAR(500) NOT NULL DEFAULT '',
      created_at DATETIME NOT NULL, KEY ca (created_at)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS ag_oferty (
      id INT AUTO_INCREMENT PRIMARY KEY, ext_id VARCHAR(40) NOT NULL, tytul VARCHAR(255) NOT NULL DEFAULT '', stanowisko VARCHAR(160) NOT NULL DEFAULT '',
      firma VARCHAR(255) NOT NULL DEFAULT '', konto VARCHAR(120) NOT NULL DEFAULT '', miasto VARCHAR(120) NOT NULL DEFAULT '', url VARCHAR(400) NOT NULL DEFAULT '',
      opis TEXT, www VARCHAR(255) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '', telefon VARCHAR(60) NOT NULL DEFAULT '',
      powod VARCHAR(255) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'nowa', firma_id INT NULL, ogl_at DATETIME NULL, created_at DATETIME NOT NULL,
      UNIQUE KEY ex (ext_id), KEY st (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    add_col('szukaj', 'eli', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_col('szukaj', 'eli_data', 'DATE NULL');
    add_col('firmy', 'eli_tytul', "VARCHAR(160) NOT NULL DEFAULT ''");
    ag_branze();
}

// ---------- ustawienia i stan ----------
function ag_cfg_def() {
    return array(
        'on' => 0,
        'nowe' => array('on' => 1, 'dni' => 14, 'woj' => array(), 'pkd' => '', 'dziennie' => 25, 'reczny' => 0),
        'praca' => array('on' => 1, 'frazy' => "pracownik biurowy\nwprowadzanie danych\nfakturowanie\nobsługa zamówień\nasystentka biura\nspecjalista ds. administracji", 'dziennie' => 15, 'reczny' => 1),
    );
}
function ag_cfg() {
    $d = ag_cfg_def();
    $j = json_decode((string)setting('ag_cfg'), true);
    if (!is_array($j)) return $d;
    foreach ($d as $k => $v) {
        if (!isset($j[$k])) $j[$k] = $v;
        elseif (is_array($v)) foreach ($v as $k2 => $v2) if (!isset($j[$k][$k2])) $j[$k][$k2] = $v2;
    }
    return $j;
}
function ag_cfg_save($c) { setting_set('ag_cfg', json_encode($c, JSON_UNESCAPED_UNICODE)); }
function ag_stan() { $j = json_decode((string)setting('ag_stan'), true); return is_array($j) ? $j : array(); }
function ag_stan_save($s) { setting_set('ag_stan', json_encode($s, JSON_UNESCAPED_UNICODE)); }
function ag_log($zad, $typ, $info) {
    q('INSERT INTO ag_log(zad,typ,info,created_at) VALUES(?,?,?,?)', array($zad, $typ, mb_substr($info, 0, 500), now()));
}
function ag_br_id($nazwa) { return (int)val('SELECT id FROM branze WHERE nazwa=?', array($nazwa)); }

// Branże agenta z gotowymi mailami (pierwszy + 2 przypomnienia + potwierdzenie)
function ag_branze() {
    add_col('branze', 'limit_dzienny', 'INT NOT NULL DEFAULT 0');
    $mk = function ($temat, $txt) { return array('temat' => $temat, 'bloki' => array(array('t' => 'tekst', 'text' => $txt)), 'nadtytul' => '', 'naglowek' => '', 'tresc' => '', 'przycisk' => ''); };
    $confirm = default_template_for('Inne firmy', 'potwierdzenie');
    $sets = array(
        AG_BR_NOWE => array(
            'pierwszy' => $mk('Gratulacje z okazji otwarcia firmy',
                "Dzień dobry,\n\nwidzę, że firma {firma} została niedawno zarejestrowana. Gratuluję i życzę dobrego startu.\n\nJestem {imie} z ELOK. Na początku większość klientów sprawdza firmę w Google, zanim zadzwoni. Dlatego robimy strony, które od pierwszego dnia zbierają zapytania: działają na telefonie i komputerze, mają formularz i powiadomienie na telefon. Pomagamy też ustawić automatyzacje i pierwsze reklamy, żeby papierologia nie zjadała czasu od samego początku.\n\nMogę przesłać krótką propozycję i przykłady naszych realizacji? Wystarczy odpisać „tak”."),
            'przypomnienie' => $mk('Re: Gratulacje z okazji otwarcia firmy',
                "Dzień dobry,\n\nwracam krótko do mojej wiadomości sprzed kilku dni. Jeśli strona albo uporządkowanie spraw w nowej firmie są na liście do zrobienia, chętnie przygotuję krótką propozycję.\n\nWystarczy odpisać „tak” albo zadzwonić: {moj_telefon}."),
            'przypomnienie2' => $mk('Ostatnia wiadomość ode mnie',
                "Dzień dobry,\n\nto już ostatni mail ode mnie, nie będę zaśmiecał skrzynki.\n\nGdyby kiedyś przydała się strona, reklama albo automatyzacja w firmie, wystarczy odpisać albo zadzwonić: {moj_telefon}.\n\nPowodzenia na starcie!"),
            'potwierdzenie' => $confirm,
        ),
        AG_BR_PRACA => array(
            'pierwszy' => $mk('Ogłoszenie o pracę – {stanowisko}',
                "Dzień dobry,\n\ntrafiłem na Państwa ogłoszenie o pracę na stanowisko {stanowisko}. Często część takich obowiązków, np. przepisywanie zamówień, wystawianie faktur czy wysyłanie maili do klientów, da się zautomatyzować. Wtedy nowa osoba zajmuje się ważniejszymi rzeczami albo nie trzeba jej zatrudniać na cały etat.\n\nJestem {imie} z ELOK. Robimy dla firm automatyzacje, panele i strony internetowe.\n\nMogę przesłać krótką propozycję, co dałoby się u Państwa usprawnić? Wystarczy odpisać „tak”."),
            'przypomnienie' => $mk('Re: Ogłoszenie o pracę – {stanowisko}',
                "Dzień dobry,\n\nwracam krótko do mojej wiadomości w sprawie ogłoszenia. Propozycja jest aktualna: przygotuję kilka zdań, które obowiązki da się u Państwa zautomatyzować i ile to kosztuje. Bezpłatnie i bez zobowiązań.\n\nWystarczy odpisać „tak” albo zadzwonić: {moj_telefon}."),
            'przypomnienie2' => $mk('Ostatnia wiadomość ode mnie',
                "Dzień dobry,\n\nto już ostatni mail ode mnie, nie będę zaśmiecał skrzynki.\n\nGdyby kiedyś temat automatyzacji wrócił, wystarczy odpisać albo zadzwonić: {moj_telefon}.\n\nPowodzenia w rekrutacji!"),
            'potwierdzenie' => $confirm,
        ),
    );
    foreach ($sets as $nazwa => $set) {
        if (ag_br_id($nazwa)) continue;
        q('INSERT INTO branze(nazwa,aktywna,kolejnosc,limit_dzienny) VALUES(?,1,?,?)', array($nazwa, (int)val('SELECT COALESCE(MAX(kolejnosc),0)+1 FROM branze'), 25));
        $id = (int)db()->lastInsertId();
        foreach ($set as $typ => $t) save_template($id, $typ, $t);
    }
}

// licznik dodanych dziś (dla limitu dziennego zadania)
function ag_dzis(&$s, $zad) {
    if (!isset($s['dzis']) || $s['dzis']['data'] !== date('Y-m-d')) $s['dzis'] = array('data' => date('Y-m-d'), 'nowe' => 0, 'praca' => 0);
    return (int)$s['dzis'][$zad];
}

// dodanie firmy do mailingu (Firmy) – z pominięciem duplikatów
function ag_do_firm($d, $bid) {
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return array(0, 'zły e-mail');
    $dup = firm_dup_find($d['email'], isset($d['telefon']) ? $d['telefon'] : '', $d['nazwa'], isset($d['miasto']) ? $d['miasto'] : '', isset($d['www']) ? $d['www'] : '');
    if ($dup) return array(0, 'już jest w Firmach');
    q('INSERT INTO firmy(nazwa,email,telefon,miasto,osoba,www,branza_id,status,token,dodano_at,notatka,zrodlo,eli_tytul) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array(mb_substr($d['nazwa'], 0, 255), $d['email'], mb_substr(isset($d['telefon']) ? $d['telefon'] : '', 0, 60), mb_substr(isset($d['miasto']) ? $d['miasto'] : '', 0, 120),
            mb_substr(isset($d['osoba']) ? $d['osoba'] : '', 0, 120), mb_substr(isset($d['www']) ? $d['www'] : '', 0, 255), $bid, 'nowy', token(), now(),
            mb_substr(isset($d['notatka']) ? $d['notatka'] : '', 0, 2000), isset($d['zrodlo']) ? $d['zrodlo'] : 'eli', mb_substr(isset($d['stanowisko']) ? $d['stanowisko'] : '', 0, 160)));
    return array((int)db()->lastInsertId(), '');
}

// ---------- ZADANIE 1: nowe firmy z CEIDG ----------
function ag_nowe_run($cfg, &$s, $t0, $budget) {
    $c = $cfg['nowe'];
    $bid = ag_br_id(AG_BR_NOWE);
    if (!$bid) return 'brak branży';
    $out = array();
    // a) dociągnij kontakty (e-mail, www) dla znalezionych firm
    $left = (int)val("SELECT COUNT(*) FROM szukaj WHERE eli=1 AND status IN('czeka','czeka_www')");
    if ($left > 0) {
        list($n, $left2, $err) = sz_step();
        if ($err) { ag_log('nowe', 'blad', 'CEIDG: ' . $err); return 'błąd: ' . $err; }
        if ($n) $out[] = 'sprawdzono kontakty: ' . $n;
    }
    // b) gotowe z mailem → do mailingu (albo czekają na zatwierdzenie)
    if (empty($c['reczny'])) {
        $limit = max(0, (int)$c['dziennie'] - ag_dzis($s, 'nowe'));
        if ($limit > 0) {
            foreach (all("SELECT * FROM szukaj WHERE eli=1 AND branza_id=? AND status='gotowe' ORDER BY id LIMIT " . (int)$limit, array($bid)) as $r) {
                list($fid, $why) = ag_do_firm(array('nazwa' => $r['nazwa'], 'email' => $r['email'], 'telefon' => $r['telefon'], 'miasto' => $r['miasto'], 'osoba' => $r['osoba'], 'www' => $r['www'],
                    'notatka' => 'Agent Eli – nowa firma (CEIDG, start ' . ($r['eli_data'] ?: '?') . '). ' . $r['adres'] . ($r['nip'] ? ' · NIP ' . $r['nip'] : ''), 'zrodlo' => 'ceidg'), $bid);
                q('UPDATE szukaj SET status=? WHERE id=?', array($fid ? 'dodano' : 'juz_jest', $r['id']));
                if ($fid) { $s['dzis']['nowe']++; ag_log('nowe', 'dodano', $r['nazwa'] . ' (' . $r['miasto'] . ') – ' . $r['email']); }
            }
        }
    }
    if (microtime(true) - $t0 > $budget) return implode(', ', $out);
    // c) szukanie nowych firm – raz na kilka godzin, wszystkie wybrane województwa po kolei
    $st = isset($s['nowe']) ? $s['nowe'] : array();
    $woj = !empty($c['woj']) ? array_values($c['woj']) : noc_woj_all();
    $pkd = preg_split('/[\s,;]+/', (string)$c['pkd'], -1, PREG_SPLIT_NO_EMPTY);
    $nowyCykl = empty($st['cykl_at']) || strtotime($st['cykl_at']) < time() - 6 * 3600;
    if (!isset($st['wi']) || ($st['wi'] >= count($woj) && $nowyCykl)) { $st = array('wi' => 0, 'page' => 0, 'cykl_at' => now(), 'dodano' => 0); }
    if ($st['wi'] >= count($woj)) { $s['nowe'] = $st; return implode(', ', $out) ?: 'czekam na kolejny przebieg'; }
    $od = date('Y-m-d', time() - max(1, (int)$c['dni']) * 86400);
    $extra = array('dataod' => $od, 'datado' => date('Y-m-d'));
    while (microtime(true) - $t0 < $budget && $st['wi'] < count($woj)) {
        $w = $woj[$st['wi']];
        if (!$pkd) list($n, $total, $more, $err) = ceidg_search(array(), '', $w, (int)$st['page'], $bid, $extra);
        else list($n, $total, $more, $err) = ceidg_search($pkd, '', $w, (int)$st['page'], $bid, $extra);
        if ($err) { ag_log('nowe', 'blad', 'CEIDG (' . $w . '): ' . $err); $s['nowe'] = $st; return 'błąd CEIDG: ' . $err; }
        if ($n) {
            q("UPDATE szukaj SET eli=1, eli_data=? WHERE zrodlo='ceidg' AND branza_id=? AND eli=0", array(date('Y-m-d'), $bid));
            $st['dodano'] += $n;
            ag_log('nowe', 'znaleziono', $w . ': ' . $n . ' nowych firm (strona ' . ((int)$st['page'] + 1) . ')');
        }
        if ($more && $st['page'] < 40) $st['page']++;
        else { $st['wi']++; $st['page'] = 0; }
        $s['nowe'] = $st; ag_stan_save($s);
    }
    if ($st['wi'] >= count($woj)) ag_log('nowe', 'info', 'Przeszukano wszystkie województwa – nowych firm w tym przebiegu: ' . $st['dodano']);
    $s['nowe'] = $st;
    return implode(', ', $out) ?: 'szukam';
}

// ---------- ZADANIE 2: ogłoszenia o pracę (OLX) ----------
function ag_txt($html) { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags(str_replace(array('<br', '</p>', '</li>'), array("\n<br", "\n</p>", "\n</li>"), (string)$html)), ENT_QUOTES, 'UTF-8'))); }
function ag_stanowisko($t, $miasto = '') {
    $t = preg_replace('/\([^)]*\)|\b(k\/m|m\/k)\b/iu', '', $t);
    $t = preg_replace('/^(praca|zatrudni[ęe]|zatrudnimy|szukamy|poszukujemy|oferta pracy)\s*[:\-–]?\s*/iu', '', trim($t));
    $t = preg_split('/\s+[\-–|]|\s*[|,;\/]\s*|\s-(?=\S)/u', $t)[0];
    if ($miasto !== '') { $p = mb_stripos($t, $miasto); if ($p > 3) $t = mb_substr($t, 0, $p); }
    $w = preg_split('/\s+/u', trim($t));
    if (count($w) > 5) $t = implode(' ', array_slice($w, 0, 4));
    $t = trim(preg_replace('/\s+/u', ' ', $t), " -–|,.");
    if ($t === '') $t = 'pracownik biurowy';
    return mb_strtolower($t);
}
// zwraca powód odrzucenia albo ''
function ag_odrzuc($o, $opis) {
    $t = mb_strtolower($o['title'] . ' ' . $opis . ' ' . (isset($o['user']['name']) ? $o['user']['name'] : ''));
    if (preg_match('/agencj[aię] (zatrudnienia|pracy)|nr\s*kraz|manpower|randstad|adecco|hays|grafton|work service|otto work|gi group|trenkwalder|interkadra|sedlak|leasing pracownik/u', $t)) return 'agencja pracy';
    if (preg_match('/zdaln|home office|z domu|online/u', mb_strtolower($o['title']))) return 'praca zdalna (często nie firma)';
    if (!empty($o['external_url']) && preg_match('/forms|fillout|typeform|easyapply|bit\.ly/i', $o['external_url'])) return 'zewnętrzny formularz rekrutacyjny';
    if (!preg_match('/faktur|zam[óo]wie|dokument|wprowadzan|excel|korespondenc|ofert|magazyn|cennik|rozlicze|kartotek|baz[aęy] danych|system/u', $t)) return 'brak powtarzalnych zadań biurowych w opisie';
    return '';
}
function ag_firma_z_opisu($opis) {
    $sp = '(?:sp\.\s?z\s?o\.\s?o\.|sp\.\s?j\.|sp\.\s?k\.|s\.\s?a\.)';
    if (preg_match('/(?:pracodawca|firma|sp[óo]łka)\s*:\s*([^\n;]{3,80})/iu', $opis, $m)) {
        $n = $m[1];
        if (preg_match('/^(.*?' . $sp . ')/iu', $n, $m2)) return trim($m2[1]);
        return trim(preg_replace('/\..*$/u', '', $n));
    }
    if (preg_match('/\b([A-ZŁŚŻŹĆŃÓĘĄ0-9][\w\-&\']*(?:\s[A-ZŁŚŻŹĆŃÓĘĄ0-9][\w\-&\']*){0,4}\s' . $sp . ')/u', $opis, $m)) return trim(preg_replace('/^(firma|sp[óo]łka)\s+/iu', '', $m[1]));
    if (preg_match('/\b([A-ZŁŚŻŹĆŃÓĘĄ0-9][\w\-&\']*(?:\s[A-ZŁŚŻŹĆŃÓĘĄ0-9][\w\-&\']*){0,4}\s(?:Sp\.\s?z\s?o\.\s?o\.|Sp\.\s?j\.|Sp\.\s?k\.|S\.A\.))/u', $opis, $m)) return trim(preg_replace('/^(firma|sp[óo]łka)\s+/iu', '', $m[1]));
    return '';
}
function ag_olx_get($fraza, $offset) {
    $url = 'https://www.olx.pl/api/v1/offers/?offset=' . (int)$offset . '&limit=40&category_id=4&sort_by=created_at:desc&query=' . rawurlencode($fraza);
    list($code, $body, $err) = http_req('GET', $url, array('Accept: application/json', 'Accept-Language: pl-PL'), null, 20);
    setting_set('ag_olx_last', date('Y-m-d H:i:s') . ' ' . ($code ?: $err));
    if ($code !== 200) return array(null, 'OLX: błąd ' . ($code ?: $err));
    $j = json_decode($body, true);
    if (!is_array($j) || !isset($j['data'])) return array(null, 'OLX: nieczytelna odpowiedź');
    return array($j['data'], '');
}
// próba znalezienia maila firmy dla oferty
function ag_szukaj_kontakt($r) {
    $opis = (string)$r['opis'];
    // 1) e-mail podany w ogłoszeniu
    $em = emails_in($opis);
    $em = array_values(array_filter($em, function ($e) { return !preg_match('/@(olx|gmail|wp|o2|onet|interia|op)\./', $e); }));
    if ($em) return array($em[0], '', 'e-mail z ogłoszenia');
    // 2) strona www podana w ogłoszeniu
    if (preg_match('#(?:https?://)?(?:www\.)[a-z0-9\-\.]+\.[a-z]{2,}#i', $opis, $m) || preg_match('#https?://[a-z0-9\-\.]+\.[a-z]{2,}#i', $opis, $m)) {
        $www = $m[0]; if (!preg_match('#^https?://#i', $www)) $www = 'https://' . $www;
        if (stripos($www, 'olx.') === false) { $e = site_email($www); if ($e) return array($e, $www, 'strona z ogłoszenia'); }
    }
    // 3) nazwa konta OLX jako domena (np. „elprotect” → elprotect.pl)
    $k = mb_strtolower(preg_replace('/[^a-z0-9\-]/i', '', (string)$r['konto']));
    if (strlen($k) >= 5 && !preg_match('/^(kadry|rekrutacja|hr|praca|biuro|sekretariat|firma|admin|kontakt)/', $k)) {
        foreach (array('https://' . $k . '.pl', 'https://' . $k . '.com.pl') as $www) {
            $e = site_email($www);
            if ($e && strpos($e, $k) !== false) return array($e, $www, 'strona firmy (' . $k . ')');
        }
    }
    // 4) nazwa firmy z opisu → CEIDG
    if ($r['firma'] !== '' && ceidg_token() !== '') {
        list($j, $err) = ceidg_get('/firmy?nazwa=' . rawurlencode($r['firma']) . ($r['miasto'] ? '&miasto=' . rawurlencode(ceidg_miasto($r['miasto'])) : '') . '&status=AKTYWNY&limit=5');
        if (!$err && !empty($j['firmy'][0]['id'])) {
            list($j2, $e2) = ceidg_get('/firma/' . rawurlencode($j['firmy'][0]['id']));
            $f = $e2 ? null : (isset($j2['firma']) ? (isset($j2['firma'][0]) ? $j2['firma'][0] : $j2['firma']) : $j2);
            if ($f) {
                $e = isset($f['email']) ? mb_strtolower(trim($f['email'])) : '';
                $w = isset($f['www']) ? trim($f['www']) : '';
                if (filter_var($e, FILTER_VALIDATE_EMAIL)) return array($e, $w, 'CEIDG');
                if ($w !== '') { $e = site_email($w); if ($e) return array($e, $w, 'strona z CEIDG'); }
            }
        }
    }
    return array('', '', '');
}
function ag_praca_run($cfg, &$s, $t0, $budget) {
    $c = $cfg['praca'];
    $bid = ag_br_id(AG_BR_PRACA);
    if (!$bid) return 'brak branży';
    $out = array();
    // a) pobierz nowe ogłoszenia – co 3 godziny, wszystkie frazy
    $st = isset($s['praca']) ? $s['praca'] : array();
    $frazy = array_values(array_filter(array_map('trim', preg_split('/\n/', (string)$c['frazy']))));
    if (empty($st['at']) || strtotime($st['at']) < time() - 3 * 3600) {
        $nowe = 0; $odrz = 0; $blad = '';
        foreach ($frazy as $fr) {
            if (microtime(true) - $t0 > $budget) break;
            list($list, $err) = ag_olx_get($fr, 0);
            if ($err) { $blad = $err; break; }
            foreach ($list as $o) {
                if (empty($o['id'])) continue;
                $ogl = isset($o['created_time']) ? date('Y-m-d H:i:s', strtotime($o['created_time'])) : null;
                if ($ogl && strtotime($ogl) < time() - 10 * 86400) continue; // tylko świeże ogłoszenia
                if (val('SELECT id FROM ag_oferty WHERE ext_id=?', array('olx' . $o['id']))) continue;
                $opis = ag_txt(isset($o['description']) ? $o['description'] : '');
                $powod = ag_odrzuc($o, $opis);
                $firma = ag_firma_z_opisu($opis);
                q('INSERT IGNORE INTO ag_oferty(ext_id,tytul,stanowisko,firma,konto,miasto,url,opis,powod,status,ogl_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                    array('olx' . $o['id'], mb_substr($o['title'], 0, 255), mb_substr(ag_stanowisko($o['title'], isset($o['location']['city']['name']) ? $o['location']['city']['name'] : ''), 0, 160), mb_substr($firma, 0, 255),
                        mb_substr(isset($o['user']['name']) ? $o['user']['name'] : '', 0, 120), mb_substr(isset($o['location']['city']['name']) ? $o['location']['city']['name'] : '', 0, 120),
                        mb_substr(isset($o['url']) ? $o['url'] : '', 0, 400), mb_substr($opis, 0, 6000), $powod, $powod ? 'odrzucona' : 'nowa', $ogl, now()));
                if ($powod) $odrz++; else $nowe++;
            }
        }
        if ($blad) { ag_log('praca', 'blad', $blad); $out[] = $blad; }
        else {
            $st['at'] = now(); $s['praca'] = $st;
            ag_log('praca', 'znaleziono', 'Ogłoszenia: ' . $nowe . ' pasujących, ' . $odrz . ' odrzuconych (agencje, praca zdalna, brak zadań biurowych)');
        }
    }
    // b) szukanie kontaktu dla pasujących ogłoszeń
    while (microtime(true) - $t0 < $budget) {
        $r = one("SELECT * FROM ag_oferty WHERE status='nowa' ORDER BY id LIMIT 1");
        if (!$r) break;
        q("UPDATE ag_oferty SET status='szukam' WHERE id=?", array($r['id']));
        list($e, $www, $skad) = ag_szukaj_kontakt($r);
        if ($e && firm_dup_find($e, '', $r['firma'], $r['miasto'], $www)) { q("UPDATE ag_oferty SET status='juz_jest', email=?, www=? WHERE id=?", array($e, $www, $r['id'])); continue; }
        q('UPDATE ag_oferty SET status=?, email=?, www=?, powod=? WHERE id=?', array($e ? 'gotowe' : 'brak_maila', $e, mb_substr($www, 0, 255), $e ? 'kontakt: ' . $skad : 'nie znalazłam maila', $r['id']));
        if ($e) ag_log('praca', 'kontakt', ($r['firma'] ?: $r['konto']) . ' (' . $r['miasto'] . ') – ' . $e . ' [' . $skad . ']');
    }
    q("UPDATE ag_oferty SET status='nowa' WHERE status='szukam' AND created_at < ?", array(date('Y-m-d H:i:s', time() - 600)));
    // c) gotowe → do mailingu (jeśli nie trzeba zatwierdzać)
    if (empty($c['reczny'])) {
        $limit = max(0, (int)$c['dziennie'] - ag_dzis($s, 'praca'));
        foreach (all("SELECT * FROM ag_oferty WHERE status='gotowe' ORDER BY id LIMIT " . (int)$limit) as $r) ag_praca_dodaj($r, $s);
    }
    return implode(', ', $out);
}
function ag_praca_dodaj($r, &$s) {
    $bid = ag_br_id(AG_BR_PRACA);
    $nazwa = $r['firma'] !== '' ? $r['firma'] : ($r['www'] ? dup_host($r['www']) : $r['konto']);
    list($fid, $why) = ag_do_firm(array('nazwa' => $nazwa, 'email' => $r['email'], 'miasto' => $r['miasto'], 'www' => $r['www'], 'stanowisko' => $r['stanowisko'] ?: 'pracownik biurowy',
        'notatka' => 'Agent Eli – ogłoszenie o pracę: ' . $r['tytul'] . ' ' . $r['url'], 'zrodlo' => 'olx'), $bid);
    q('UPDATE ag_oferty SET status=?, firma_id=? WHERE id=?', array($fid ? 'dodano' : 'juz_jest', $fid ?: null, $r['id']));
    if ($fid) { ag_dzis($s, 'praca'); $s['dzis']['praca']++; ag_log('praca', 'dodano', $nazwa . ' – ' . $r['email'] . ' (' . $r['stanowisko'] . ')'); }
    return $fid;
}

// ---------- przebieg (cron / przycisk) ----------
function ag_run($budget, $manual = false) {
    ag_migrate_if();
    $cfg = ag_cfg();
    if (!$manual && empty($cfg['on'])) return 'agent wyłączony';
    if ((int)val("SELECT GET_LOCK('elok_agent', 0)") !== 1) return 'agent już pracuje';
    $t0 = microtime(true);
    $s = ag_stan(); ag_dzis($s, 'nowe');
    $r = array();
    try {
        if (!empty($cfg['nowe']['on'])) $r[] = 'nowe firmy: ' . ag_nowe_run($cfg, $s, $t0, $budget * 0.55);
        if (!empty($cfg['praca']['on'])) $r[] = 'ogłoszenia: ' . ag_praca_run($cfg, $s, $t0, $budget);
    } catch (Exception $e) { ag_log('', 'blad', $e->getMessage()); $r[] = 'błąd: ' . $e->getMessage(); }
    $s['ostatnio'] = now(); $s['wynik'] = implode(' | ', $r);
    ag_stan_save($s);
    val("SELECT RELEASE_LOCK('elok_agent')");
    // raz dziennie po 18:00 podsumowanie na telefon
    if ((int)date('G') >= 18 && setting('ag_raport') !== date('Y-m-d') && !empty($cfg['on'])) {
        setting_set('ag_raport', date('Y-m-d'));
        $d = isset($s['dzis']) ? $s['dzis'] : array('nowe' => 0, 'praca' => 0);
        $czeka = (int)val("SELECT COUNT(*) FROM ag_oferty WHERE status='gotowe'") + (!empty($cfg['nowe']['reczny']) ? (int)val("SELECT COUNT(*) FROM szukaj WHERE eli=1 AND status='gotowe'") : 0);
        push('Agent Eli – dzisiaj', 'Dodane do mailingu: nowe firmy ' . (int)$d['nowe'] . ', ogłoszenia ' . (int)$d['praca'] . ($czeka ? ' · czeka na zatwierdzenie: ' . $czeka : ''), BASE_URL . '#agent');
    }
    return $s['wynik'];
}

// ---------- API (akcje „ag_…”, wywoływane z api.php) ----------
function ag_api($a) {
    ag_migrate_if();
    if ($a === 'ag_stan') {
        $cfg = ag_cfg(); $s = ag_stan(); ag_dzis($s, 'nowe');
        $bN = ag_br_id(AG_BR_NOWE); $bP = ag_br_id(AG_BR_PRACA);
        $br = all('SELECT id,nazwa,aktywna,limit_dzienny FROM branze WHERE id IN(?,?)', array($bN, $bP));
        $mail = array();
        foreach (array($bN, $bP) as $b) $mail[$b] = array(
            'w_kolejce' => (int)val("SELECT COUNT(*) FROM firmy WHERE branza_id=? AND status='nowy'", array($b)),
            'wyslano' => (int)val("SELECT COUNT(*) FROM firmy WHERE branza_id=? AND wyslano_at IS NOT NULL", array($b)),
            'otwarte' => (int)val("SELECT COUNT(*) FROM firmy WHERE branza_id=? AND otwarcia>0", array($b)),
            'odpisali' => (int)val("SELECT COUNT(*) FROM firmy WHERE branza_id=? AND (odpisal_at IS NOT NULL OR zgloszenie_at IS NOT NULL)", array($b)),
        );
        $czekaN = all("SELECT id,nazwa,osoba,email,telefon,www,miasto,adres,eli_data FROM szukaj WHERE eli=1 AND status='gotowe' ORDER BY id DESC LIMIT 100");
        $czekaP = all("SELECT id,tytul,stanowisko,firma,konto,miasto,url,email,www,powod,ogl_at FROM ag_oferty WHERE status='gotowe' ORDER BY id DESC LIMIT 100");
        $licz = array(
            'nowe_znalezione' => (int)val('SELECT COUNT(*) FROM szukaj WHERE eli=1'),
            'nowe_z_mailem' => (int)val("SELECT COUNT(*) FROM szukaj WHERE eli=1 AND status IN('gotowe','dodano')"),
            'nowe_szukam' => (int)val("SELECT COUNT(*) FROM szukaj WHERE eli=1 AND status IN('czeka','czeka_www','szukam')"),
            'praca_ogl' => (int)val('SELECT COUNT(*) FROM ag_oferty'),
            'praca_odrz' => (int)val("SELECT COUNT(*) FROM ag_oferty WHERE status='odrzucona'"),
            'praca_brak' => (int)val("SELECT COUNT(*) FROM ag_oferty WHERE status='brak_maila'"),
            'praca_dodane' => (int)val("SELECT COUNT(*) FROM ag_oferty WHERE status='dodano'"),
        );
        out(array('cfg' => $cfg, 'stan' => $s, 'branze' => $br, 'mail' => $mail, 'czekaN' => $czekaN, 'czekaP' => $czekaP, 'licz' => $licz,
            'pol' => array(
                'ceidg' => ceidg_token() !== '', 'ceidg_debug' => setting('ceidg_debug'),
                'olx' => setting('ag_olx_last'),
                'cron' => setting('ostatni_cron'), 'auto' => setting('auto_on') === '1',
                'godz' => setting('godz_od', '8') . '–' . setting('godz_do', '11'), 'limit' => (int)setting('limit_dzienny', '25'),
                'push' => setting('ntfy_topic') !== '',
            ),
            'log' => all('SELECT zad,typ,info,created_at FROM ag_log ORDER BY id DESC LIMIT 80')));
    }
    if ($a === 'ag_zapisz') {
        $c = ag_cfg();
        $c['on'] = g('on') ? 1 : 0;
        $n = (array)g('nowe'); $p = (array)g('praca');
        $c['nowe']['on'] = !empty($n['on']) ? 1 : 0;
        $c['nowe']['dni'] = max(1, min(60, (int)(isset($n['dni']) ? $n['dni'] : 14)));
        $c['nowe']['dziennie'] = max(0, min(300, (int)(isset($n['dziennie']) ? $n['dziennie'] : 25)));
        $c['nowe']['reczny'] = !empty($n['reczny']) ? 1 : 0;
        $c['nowe']['pkd'] = mb_substr(trim((string)(isset($n['pkd']) ? $n['pkd'] : '')), 0, 500);
        $woj = array(); foreach ((array)(isset($n['woj']) ? $n['woj'] : array()) as $w) if (in_array($w, noc_woj_all(), true)) $woj[] = $w;
        $c['nowe']['woj'] = $woj;
        $c['praca']['on'] = !empty($p['on']) ? 1 : 0;
        $c['praca']['dziennie'] = max(0, min(300, (int)(isset($p['dziennie']) ? $p['dziennie'] : 15)));
        $c['praca']['reczny'] = !empty($p['reczny']) ? 1 : 0;
        $c['praca']['frazy'] = mb_substr(trim((string)(isset($p['frazy']) ? $p['frazy'] : '')), 0, 1000);
        ag_cfg_save($c);
        $s = ag_stan(); unset($s['nowe']); ag_stan_save($s); // nowe ustawienia → nowy przebieg
        ag_log('', 'info', 'Zmieniono ustawienia agenta' . ($c['on'] ? '' : ' (agent wyłączony)'));
        out(array());
    }
    if ($a === 'ag_uruchom') { @set_time_limit(60); out(array('wynik' => ag_run(22, true))); }
    if ($a === 'ag_zatwierdz' || $a === 'ag_odrzuc') {
        $ids = array_map('intval', (array)g('ids')); $typ = g('typ') === 'praca' ? 'praca' : 'nowe';
        if (!$ids) fail('Nic nie zaznaczono');
        $s = ag_stan(); ag_dzis($s, 'nowe'); $n = 0;
        foreach ($ids as $id) {
            if ($typ === 'praca') {
                $r = one("SELECT * FROM ag_oferty WHERE id=? AND status='gotowe'", array($id)); if (!$r) continue;
                if ($a === 'ag_odrzuc') { q("UPDATE ag_oferty SET status='odrzucona', powod='odrzucone ręcznie' WHERE id=?", array($id)); $n++; continue; }
                if (ag_praca_dodaj($r, $s)) $n++;
            } else {
                $r = one("SELECT * FROM szukaj WHERE id=? AND eli=1 AND status='gotowe'", array($id)); if (!$r) continue;
                if ($a === 'ag_odrzuc') { q("UPDATE szukaj SET status='ukryty' WHERE id=?", array($id)); $n++; continue; }
                list($fid) = ag_do_firm(array('nazwa' => $r['nazwa'], 'email' => $r['email'], 'telefon' => $r['telefon'], 'miasto' => $r['miasto'], 'osoba' => $r['osoba'], 'www' => $r['www'],
                    'notatka' => 'Agent Eli – nowa firma (CEIDG). ' . $r['adres'] . ($r['nip'] ? ' · NIP ' . $r['nip'] : ''), 'zrodlo' => 'ceidg'), ag_br_id(AG_BR_NOWE));
                q('UPDATE szukaj SET status=? WHERE id=?', array($fid ? 'dodano' : 'juz_jest', $id));
                if ($fid) { $n++; $s['dzis']['nowe']++; ag_log('nowe', 'dodano', $r['nazwa'] . ' – ' . $r['email'] . ' (zatwierdzone)'); }
            }
        }
        ag_stan_save($s);
        out(array('n' => $n));
    }
    if ($a === 'ag_oferty') {
        $st = gs('status', 20);
        $w = $st !== '' ? ' WHERE status=?' : ''; $p = $st !== '' ? array($st) : array();
        out(array('rows' => all('SELECT id,tytul,stanowisko,firma,konto,miasto,url,email,www,powod,status,ogl_at FROM ag_oferty' . $w . ' ORDER BY id DESC LIMIT 200', $p)));
    }
    if ($a === 'ag_podglad') {
        $typ = g('typ') === 'praca' ? AG_BR_PRACA : AG_BR_NOWE;
        $tpl = tpl_for(ag_br_id($typ), 'pierwszy');
        $firm = array('nazwa' => gs('nazwa') ?: 'Przykładowa Firma', 'miasto' => gs('miasto'), 'osoba' => '', 'eli_tytul' => gs('stanowisko') ?: 'pracownik biurowy', 'token' => str_repeat('0', 32), 'zrodlo' => 'ceidg');
        list($subj, $html) = render_mail($tpl, $firm, array('preview' => true));
        out(array('temat' => $subj, 'html' => $html));
    }
    fail('Nieznana akcja agenta');
}
