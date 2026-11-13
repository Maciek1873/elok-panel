<?php
// ELOK PANEL – API (tylko po zalogowaniu)
define('ELOK', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/szablony.php';
require __DIR__ . '/formularz.php';
require __DIR__ . '/zrodla.php';
require __DIR__ . '/skrzynka.php';
require __DIR__ . '/social.php';
header('Content-Type: application/json; charset=utf-8');
header('X-Content-Type-Options: nosniff');

function out($a) { $a['ok'] = true; echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function fail($msg, $extra = array()) { $extra['ok'] = false; $extra['error'] = $msg; echo json_encode($extra, JSON_UNESCAPED_UNICODE); exit; }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') fail('Zła metoda');
start_session();
$hdr = isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '';
if (!hash_equals($_SESSION['csrf'], $hdr)) fail('Sesja wygasła – odśwież stronę', array('login' => true));

$a = isset($_GET['a']) ? $_GET['a'] : '';
$in = json_decode(file_get_contents('php://input'), true);
if (!is_array($in)) $in = array();
function g($k, $d = '') { global $in; return isset($in[$k]) ? $in[$k] : $d; }
function gs($k, $max = 255) { return mb_substr(trim((string)g($k)), 0, $max); }

function dup_msg($d) { $n = (string)val('SELECT nazwa FROM firmy WHERE id=?', array($d[0])); return 'duplikat: ' . $d[1] . ($n !== '' ? ' („' . mb_substr($n, 0, 40) . '”)' : ''); }

function clean_blocks($in) {
    $ok = array('tekst' => array('text'), 'naglowek' => array('nad', 'text'), 'ramka' => array('title', 'text'), 'korzysci' => array('text'), 'przycisk' => array('text', 'sub'), 'obrazek' => array('url', 'link'), 'linia' => array());
    $out = array();
    foreach ((array)$in as $b) {
        if (!is_array($b) || !isset($b['t']) || !isset($ok[$b['t']])) continue;
        $c = array('t' => $b['t']);
        foreach ($ok[$b['t']] as $f) $c[$f] = mb_substr(isset($b[$f]) ? (string)$b[$f] : '', 0, 8000);
        $out[] = $c;
        if (count($out) >= 40) break;
    }
    return $out;
}

$STATUSY = array('nowy', 'wyslano', 'otworzyl', 'przypomnienie', 'zainteresowany', 'nie', 'wypisany', 'zwrot', 'blad');

try {
    // ---------- LOGOWANIE ----------
    if ($a === 'login') {
        if (!empty($_SESSION['fails']) && $_SESSION['fails'] >= 5 && time() - $_SESSION['fail_t'] < 300) fail('Za dużo prób. Odczekaj 5 minut.');
        $ok = hash_equals((string)setting('admin_login'), gs('login')) && password_verify((string)g('pass'), (string)setting('admin_hash'));
        if (!$ok) {
            $_SESSION['fails'] = (isset($_SESSION['fails']) ? $_SESSION['fails'] : 0) + 1; $_SESSION['fail_t'] = time();
            sleep(1);
            fail('Nieprawidłowy login lub hasło');
        }
        session_regenerate_id(true);
        $_SESSION['uid'] = 1; $_SESSION['fails'] = 0;
        out(array());
    }
    if (!logged_in()) fail('Zaloguj się', array('login' => true));
    if (empty($_SESSION['mig_v20'])) { migrate(); sz_migrate(); skp_migrate(); sklepy_migrate(); uk_migrate(); test4_migrate(); test4_teksty2(); $_SESSION['mig_v20'] = 1; }

    switch ($a) {
    case 'logout':
        $_SESSION = array(); session_destroy(); out(array());

    // ---------- PULPIT ----------
    case 'dashboard':
        $c = array();
        foreach (all('SELECT status, COUNT(*) n FROM firmy GROUP BY status') as $r) $c[$r['status']] = (int)$r['n'];
        $per = all("SELECT b.id,b.nazwa,b.aktywna,
            COUNT(f.id) firmy,
            SUM(f.status='nowy') nowe,
            SUM(f.wyslano_at IS NOT NULL) wyslane,
            SUM(f.otwarto_at IS NOT NULL) otwarte,
            SUM(f.klik_at IS NOT NULL) klik,
            SUM(f.status='zainteresowany') zaint
            FROM branze b LEFT JOIN firmy f ON f.branza_id=b.id GROUP BY b.id ORDER BY b.kolejnosc,b.id");
        $log = all('SELECT l.typ,l.info,l.created_at,f.nazwa,f.id fid FROM log l LEFT JOIN firmy f ON f.id=l.firma_id ORDER BY l.id DESC LIMIT 25');
        $days = max(1, (int)setting('dni_przypomnienia', '5'));
        $remDue = (int)val("SELECT COUNT(*) FROM firmy WHERE status IN('wyslano','otworzyl') AND przypomnienie_at IS NULL AND klik_at IS NULL AND wyslano_at<=?", array(date('Y-m-d H:i:s', time() - $days * 86400)));
        out(array(
            'statusy' => $c,
            'razem' => (int)val('SELECT COUNT(*) FROM firmy'),
            'dzis' => sent_today(), 'limit' => (int)setting('limit_dzienny', '25'),
            'auto' => setting('auto_on') === '1', 'ostatni_cron' => setting('ostatni_cron'),
            'zgl_nowe' => (int)val("SELECT COUNT(*) FROM zgloszenia WHERE status='nowe' OR (kontakt_data IS NOT NULL AND kontakt_data<=CURDATE() AND status NOT IN('klient','odrzucone'))"),
            'przypomnienia_czekaja' => $remDue,
            'branze' => $per, 'log' => $log,
            'dzien_roboczy' => is_workday(),
            'odpowiedzi' => setting('reply_to') ?: setting('nadawca_email'),
        ));

    // ---------- FIRMY ----------
    case 'firmy':
        $cond = array(); // [warunek, parametry, czy_status]
        if ((int)g('branza')) $cond[] = array('f.branza_id=?', array((int)g('branza')), false);
        $GOR = "(f.klik_at IS NOT NULL OR f.otwarcia>=2 OR f.odpisal_at IS NOT NULL) AND f.status NOT IN('zainteresowany','nie','wypisany','zwrot')";
        if (g('status') === 'goracy') $cond[] = array($GOR, array(), true);
        elseif (g('status') && in_array(g('status'), $STATUSY, true)) $cond[] = array('f.status=?', array(g('status')), true);
        if (gs('miasto') !== '') $cond[] = array('f.miasto=?', array(gs('miasto')), false);
        if (gs('q') !== '') {
            $s = '%' . gs('q') . '%';
            // telefon: szukaj po samych cyfrach (bez spacji, myślników, +48), żeby „600 123 456” znalazło „+48600123456”
            $dg = preg_replace('/\D/', '', gs('q')); if (strlen($dg) > 9 && substr($dg, 0, 2) === '48') $dg = substr($dg, 2);
            if (strlen($dg) >= 6) $cond[] = array("(f.nazwa LIKE ? OR f.email LIKE ? OR f.miasto LIKE ? OR REPLACE(REPLACE(REPLACE(REPLACE(REPLACE(f.telefon,' ',''),'-',''),'(',''),')',''),'+','') LIKE ?)", array($s, $s, $s, '%' . $dg . '%'), false);
            else $cond[] = array('(f.nazwa LIKE ? OR f.email LIKE ? OR f.miasto LIKE ? OR f.telefon LIKE ?)', array($s, $s, $s, $s), false);
        }
        $w = array('1=1'); $p = array(); $wc = array('1=1'); $pc = array();
        foreach ($cond as $c) {
            $w[] = $c[0]; $p = array_merge($p, $c[1]);
            if (!$c[2]) { $wc[] = $c[0]; $pc = array_merge($pc, $c[1]); }
        }
        $counts = array();
        foreach (all('SELECT f.status, COUNT(*) n FROM firmy f WHERE ' . implode(' AND ', $wc) . ' GROUP BY f.status', $pc) as $r) $counts[$r['status']] = (int)$r['n'];
        $counts['goracy'] = (int)val('SELECT COUNT(*) FROM firmy f WHERE ' . implode(' AND ', $wc) . ' AND ' . $GOR, $pc);
        $where = implode(' AND ', $w);
        $total = (int)val("SELECT COUNT(*) FROM firmy f WHERE $where", $p);
        $lim = 100; $off = max(0, (int)g('offset'));
        $rows = all("SELECT f.id,f.nazwa,f.email,f.telefon,f.miasto,f.osoba,f.branza_id,f.status,f.wyslano_at,f.otwarto_at,f.otwarto_last,f.otwarcia,f.przypomnienie_at,f.klik_at,f.zgloszenie_at,f.dodano_at,f.odpisal_at,b.nazwa branza
            FROM firmy f LEFT JOIN branze b ON b.id=f.branza_id WHERE $where ORDER BY " . (g('sort') === 'wyslane' ? "GREATEST(COALESCE(f.wyslano_at,'1970-01-01'),COALESCE(f.przypomnienie_at,'1970-01-01')) DESC, f.id DESC" : (g('sort') === 'otwarte' ? "COALESCE(f.otwarto_last,f.otwarto_at,'1970-01-01') DESC, f.id DESC" : (g('status') === 'goracy' ? '(f.klik_at IS NOT NULL) DESC, (f.odpisal_at IS NOT NULL) DESC, f.otwarcia DESC, f.otwarto_last DESC' : 'f.id DESC'))) . " LIMIT $lim OFFSET $off", $p);
        $miasta = all("SELECT miasto, COUNT(*) n FROM firmy WHERE miasto<>''" . ((int)g('branza') ? ' AND branza_id=' . (int)g('branza') : '') . " GROUP BY miasto ORDER BY n DESC, miasto LIMIT 300");
        out(array('rows' => $rows, 'total' => $total, 'offset' => $off, 'lim' => $lim, 'counts' => $counts, 'miasta' => $miasta));

    case 'kolejka':
        // ile czeka do wysłania (nowe + przypomnienia) wg branży i miast
        $bid = (int)g('branza');
        $days = max(1, (int)setting('dni_przypomnienia', '5'));
        $border = date('Y-m-d H:i:s', time() - $days * 86400);
        $bw = $bid ? ' AND f.branza_id=' . $bid : ' AND b.aktywna=1';
        $rows = all("SELECT f.miasto, SUM(f.status='nowy') nowe,
            SUM(f.status IN('wyslano','otworzyl') AND f.przypomnienie_at IS NULL AND f.klik_at IS NULL AND f.wyslano_at<=?) przyp
            FROM firmy f JOIN branze b ON b.id=f.branza_id WHERE 1=1 $bw GROUP BY f.miasto ORDER BY nowe DESC", array($border));
        $left = max(0, (int)setting('limit_dzienny', '25') - sent_today());
        out(array('miasta' => $rows, 'left' => $left, 'limit' => (int)setting('limit_dzienny', '25'), 'auto' => setting('auto_on') === '1', 'dni' => $days));

    case 'firma_zainteresowany':
        $f = one('SELECT * FROM firmy WHERE id=?', array((int)g('id')));
        if (!$f) fail('Nie ma takiej firmy');
        q("UPDATE firmy SET status='zainteresowany', zgloszenie_at=COALESCE(zgloszenie_at,?) WHERE id=?", array(now(), $f['id']));
        if (!(int)val('SELECT COUNT(*) FROM zgloszenia WHERE firma_id=?', array($f['id']))) {
            q('INSERT INTO zgloszenia(firma_id,firma,osoba,telefon,email,czym,co,status,notatka,ip,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
                array($f['id'], $f['nazwa'], $f['osoba'], $f['telefon'], $f['email'], '', '', 'nowe', 'Odpisał mailowo „tak” (oznaczone ręcznie)', '', now()));
        }
        logi($f['id'], 'zgloszenie', 'oznaczone ręcznie');
        out(array());

    case 'firma':
        $f = one('SELECT * FROM firmy WHERE id=?', array((int)g('id')));
        if (!$f) fail('Nie ma takiej firmy');
        unset($f['token']);
        $f['log'] = all('SELECT typ,info,created_at FROM log WHERE firma_id=? ORDER BY id DESC LIMIT 50', array($f['id']));
        $f['zgloszenia'] = all('SELECT * FROM zgloszenia WHERE firma_id=? ORDER BY id DESC', array($f['id']));
        out(array('firma' => $f));

    case 'firma_zapisz':
        $id = (int)g('id');
        $email = mb_strtolower(gs('email', 190));
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) fail('Niepoprawny e-mail');
        $dup = val('SELECT id FROM firmy WHERE email=? AND id<>?', array($email, $id));
        if ($dup) fail('Firma z tym e-mailem już jest na liście');
        if (!$id && !g('wymus')) {
            $dd = firm_dup_find($email, gs('telefon', 60), gs('nazwa'), gs('miasto', 120), gs('www'));
            if ($dd) fail('Ta firma chyba już jest na liście – ' . dup_msg($dd), array('dup' => 1));
        }
        $status = in_array(g('status'), $STATUSY, true) ? g('status') : 'nowy';
        $d = array(gs('nazwa'), $email, gs('telefon', 60), gs('miasto', 120), gs('osoba', 120), gs('www'), (int)g('branza_id') ?: null, $status, mb_substr((string)g('notatka'), 0, 5000));
        if ($id) {
            $d[] = $id;
            q('UPDATE firmy SET nazwa=?,email=?,telefon=?,miasto=?,osoba=?,www=?,branza_id=?,status=?,notatka=? WHERE id=?', $d);
        } else {
            $d[] = token(); $d[] = now();
            q('INSERT INTO firmy(nazwa,email,telefon,miasto,osoba,www,branza_id,status,notatka,token,dodano_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)', $d);
            $id = (int)db()->lastInsertId();
        }
        out(array('id' => $id));

    case 'firmy_usun':
        $ids = array_map('intval', (array)g('ids'));
        if (!$ids) fail('Nic nie zaznaczono');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        q("DELETE FROM firmy WHERE id IN($ph)", $ids);
        q("DELETE FROM log WHERE firma_id IN($ph)", $ids);
        out(array('n' => count($ids)));

    case 'firmy_zmien':
        $ids = array_map('intval', (array)g('ids'));
        if (!$ids) fail('Nic nie zaznaczono');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        if ((int)g('branza_id')) q("UPDATE firmy SET branza_id=? WHERE id IN($ph)", array_merge(array((int)g('branza_id')), $ids));
        if (g('status') && in_array(g('status'), $STATUSY, true)) q("UPDATE firmy SET status=? WHERE id IN($ph)", array_merge(array(g('status')), $ids));
        out(array('n' => count($ids)));

    case 'firma_wyslij':
        $f = one('SELECT * FROM firmy WHERE id=?', array((int)g('id')));
        if (!$f) fail('Nie ma takiej firmy');
        $typ = in_array(g('typ'), array('przypomnienie', 'przypomnienie2'), true) ? g('typ') : 'pierwszy';
        $r = send_to_firm($f, $typ);
        if ($r !== 'ok') fail('Nie wysłano: ' . $r);
        out(array());

    case 'import':
        $txt = str_replace("\r", '', (string)g('text'));
        $defB = (int)g('branza_id');
        $lines = array_values(array_filter(explode("\n", $txt), function ($l) { return trim($l) !== ''; }));
        if (!$lines) fail('Brak danych');
        $branze = array();
        foreach (all('SELECT id,nazwa FROM branze') as $b) $branze[mb_strtolower($b['nazwa'])] = (int)$b['id'];
        $findBr = function ($name) use ($branze) {
            $n = mb_strtolower(trim($name, " \t:-–"));
            if ($n === '') return 0;
            foreach ($branze as $bn => $id) if ($bn === $n || mb_strpos($bn, $n) === 0 || mb_strpos($n, $bn) === 0) return $id;
            if (mb_strlen($n) >= 4) foreach ($branze as $bn => $id) if (mb_strpos($bn, $n) !== false) return $id;
            return 0;
        };
        // tryb tabeli z nagłówkiem?
        $first = $lines[0];
        $sep = null;
        foreach (array("\t", ';', ',') as $c) if (substr_count($first, $c) >= 1 && ($sep === null || substr_count($first, $c) > substr_count($first, $sep))) $sep = $c;
        $map = null;
        $fl = mb_strtolower($first);
        if ($sep !== null && strpos($first, '@') === false && (strpos($fl, 'mail') !== false)) {
            $head = array_map(function ($x) { return mb_strtolower(trim($x, " \"'")); }, str_getcsv($first, $sep));
            $alias = array('nazwa' => array('nazwa', 'firma', 'nazwa firmy', 'name'), 'email' => array('email', 'e-mail', 'mail', 'adres e-mail'),
                'telefon' => array('telefon', 'tel', 'tel.', 'phone', 'numer'), 'miasto' => array('miasto', 'miejscowość', 'city', 'lokalizacja'),
                'branza' => array('branża', 'branza', 'kategoria'), 'www' => array('www', 'strona', 'website', 'url'), 'osoba' => array('osoba', 'kontakt', 'imię', 'imie'));
            $map = array();
            foreach ($alias as $k => $names) foreach ($head as $i => $hname) if (in_array($hname, $names, true)) { $map[$k] = $i; break; }
            array_shift($lines);
        }
        $added = 0; $dups = 0; $bad = 0; $curCity = ''; $curBr = $defB; $seen = array();
        foreach ($lines as $l) {
            $l = trim($l);
            $rec = array('nazwa' => '', 'email' => '', 'telefon' => '', 'miasto' => '', 'branza' => '', 'www' => '', 'osoba' => '');
            if (!preg_match('/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/u', $l, $mm)) {
                // linia bez e-maila = nagłówek sekcji: branża albo miasto
                $clean = trim(preg_replace('/^[#\d.)\s]+/u', '', $l), " \t:-–");
                $bb = $findBr($clean);
                if ($bb) { $curBr = $bb; continue; }
                if ($clean !== '' && mb_strlen($clean) <= 40 && !preg_match('/\d{3}/', $clean)) { $curCity = $clean; continue; }
                $bad++; continue;
            }
            $email = mb_strtolower($mm[0]);
            if ($map !== null) {
                $c = str_getcsv($l, $sep);
                foreach ($map as $k => $i) if (isset($c[$i])) $rec[$k] = trim($c[$i]);
            } elseif ($sep !== null && count(str_getcsv($l, $sep)) >= 3 && strpos($l, ' - ') === false) {
                $c = array_map('trim', str_getcsv($l, $sep));
                $keys = array('nazwa', 'email', 'telefon', 'miasto', 'branza', 'www', 'osoba');
                foreach ($keys as $i => $k) if (isset($c[$i])) $rec[$k] = $c[$i];
            } else {
                // dowolny tekst: "1. Firma - email - telefon"
                $rest = str_replace($mm[0], ' ', $l);
                if (preg_match('/(\+?48[\s-]?)?(\d[\s-]?){8}\d/u', $rest, $pm)) { $rec['telefon'] = trim($pm[0]); $rest = str_replace($pm[0], ' ', $rest); }
                $rest = preg_replace('/^\s*\d+[.)]\s*/u', '', $rest);
                $rest = preg_replace('/(https?:\/\/)?(www\.)[^\s]+/iu', ' ', $rest);
                $rest = trim(preg_replace('/\s*[-–—|;,:]+\s*$/u', '', preg_replace('/(\s*[-–—|;,:]\s*)+/u', ' – ', trim($rest))), " \t-–—|;,:");
                $parts = array_values(array_filter(array_map('trim', explode(' – ', $rest)), 'strlen'));
                $rec['nazwa'] = isset($parts[0]) ? $parts[0] : '';
                if (isset($parts[1]) && mb_strlen($parts[1]) <= 40) $rec['miasto'] = $parts[1];
            }
            $rec['email'] = $email;
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $bad++; continue; }
            if ($rec['miasto'] === '') $rec['miasto'] = $curCity;
            $keys = dup_keys($email, $rec['telefon'], $rec['nazwa'], $rec['miasto'], $rec['www']);
            $rep = false; foreach ($keys as $k) if (isset($seen[$k])) $rep = true;
            if ($rep || firm_dup_find($email, $rec['telefon'], $rec['nazwa'], $rec['miasto'], $rec['www'])) { $dups++; continue; }
            foreach ($keys as $k) $seen[$k] = 1;
            $bid = $rec['branza'] !== '' ? ($findBr($rec['branza']) ?: $curBr) : $curBr;
            q('INSERT INTO firmy(nazwa,email,telefon,miasto,osoba,www,branza_id,status,token,dodano_at,notatka) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
                array(mb_substr($rec['nazwa'], 0, 255), $email, mb_substr($rec['telefon'], 0, 60), mb_substr($rec['miasto'], 0, 120), mb_substr($rec['osoba'], 0, 120), mb_substr($rec['www'], 0, 255), $bid ?: null, 'nowy', token(), now(), ''));
            $added++;
        }
        out(array('dodano' => $added, 'duplikaty' => $dups, 'bledne' => $bad));

    // ---------- SZUKAJ FIRM ----------
    case 'sz_szukaj':
        @set_time_limit(90);
        $bid = (int)g('branza_id');
        if (g('zrodlo') === 'osm') {
            $srv = (int)g('srv');
            $res = osm_search(gs('tag', 30), gs('miasto', 120), gs('woj', 40), $bid, $srv, (bool)g('bez_aso'), (bool)g('bez_sieci'), (int)g('km'), g('kraj') === 'gb' ? 'gb' : 'pl');
            $n = $res[0]; $found = $res[1]; $err = $res[2]; $skip = isset($res[3]) ? $res[3] : 0;
            if (strpos($err, 'RETRY:') === 0) out(array('retry' => 1, 'srv' => $srv + 1, 'ile' => count(osm_bases()), 'info' => substr($err, 6)));
            if ($err) fail($err);
            out(array('dodano' => $n, 'razem' => $found, 'pominiete' => $skip));
        }
        if (g('zrodlo') === 'google') {
            $qq = trim(gs('fraza') . ' ' . gs('miasto'));
            if ($qq === '') fail('Wpisz czego szukać');
            list($n, $next, $err) = google_search($qq, gs('token', 2000), $bid);
            if ($err) fail($err);
            out(array('dodano' => $n, 'token' => $next));
        }
        $pk = preg_split('/[\s,;]+/', gs('pkd'), -1, PREG_SPLIT_NO_EMPTY);
        list($n, $total, $more, $err) = ceidg_search($pk, gs('miasto', 120), gs('woj', 40), max(0, (int)g('page')), $bid);
        if ($err) fail($err);
        out(array('dodano' => $n, 'razem' => $total, 'wiecej' => $more));

    case 'branze_limity':
        add_col('branze', 'limit_dzienny', 'INT NOT NULL DEFAULT 0');
        add_col('branze', 'jezyk', "VARCHAR(2) NOT NULL DEFAULT 'pl'");
        $rows = all("SELECT b.id,b.nazwa,b.aktywna,b.limit_dzienny,b.jezyk, SUM(f.status='nowy') nowe FROM branze b LEFT JOIN firmy f ON f.branza_id=b.id GROUP BY b.id ORDER BY b.kolejnosc,b.id");
        foreach ($rows as &$r) $r['dzis'] = sent_today_branza($r['id']);
        unset($r);
        out(array('rows' => $rows, 'dzis' => sent_today(), 'runs' => runs_left()));

    case 'branze_limity_zapisz':
        add_col('branze', 'limit_dzienny', 'INT NOT NULL DEFAULT 0');
        foreach ((array)g('rows') as $r) {
            if (!is_array($r) || empty($r['id'])) continue;
            q('UPDATE branze SET aktywna=?, limit_dzienny=?, jezyk=? WHERE id=?', array(!empty($r['aktywna']) ? 1 : 0, max(0, min(1000, (int)(isset($r['limit']) ? $r['limit'] : 0))), (isset($r['jezyk']) && $r['jezyk'] === 'en') ? 'en' : 'pl', (int)$r['id']));
        }
        out(array());

    case 'imap_test':
        @set_time_limit(90);
        $m = mb_check(50);
        if ($m['blad']) fail($m['blad']);
        out($m);

    case 'noc_stan':
        $j = noc_get();
        $woj = !empty($j['woj']) ? $j['woj'] : noc_woj_all();
        $last = setting('noc_ostatnio');
        out(array('z' => $j, 'woj_all' => noc_woj_all(), 'woj' => $woj, 'licz' => noc_counts($j), 'ostatnio' => $last,
            'cron_ok' => $last !== '' && strtotime($last) > time() - 1200, 'cron_url' => BASE_URL . '/cron.php?key=' . CRON_KEY . '&tylko=pobieranie', 'ma_ceidg' => ceidg_token() !== ''));

    case 'noc_start':
        if (ceidg_token() === '') fail('Najpierw wpisz token CEIDG w Ustawieniach.');
        $pk = trim(gs('pkd', 200)); if ($pk === '') fail('Podaj kod PKD');
        $bid = (int)g('branza_id'); if (!val('SELECT id FROM branze WHERE id=?', array($bid))) fail('Wybierz branżę');
        $all = noc_woj_all(); $woj = array();
        foreach ((array)g('woj') as $w) if (in_array($w, $all, true)) $woj[] = $w;
        $old0 = noc_get(); $segMode = 0;
        if (g('pominiete')) { // ponów tylko województwa z pominiętymi częściami, od razu w małych częściach
            $woj = array();
            foreach ((array)(isset($old0['pominiete']) ? $old0['pominiete'] : array()) as $pm) { $w = trim(preg_replace('/\s\d{4}-.*$/u', '', $pm)); if (in_array($w, $all, true) && !in_array($w, $woj, true)) $woj[] = $w; }
            if (!$woj) fail('Nie ma pominiętych części');
            $segMode = 1;
        }
        if (!$woj) fail('Zaznacz co najmniej jedno województwo');
        $j = array('on' => 1, 'pkd' => $pk, 'branza_id' => $bid, 'woj' => $woj, 'wi' => 0, 'page' => 0, 'dodano' => 0, 'godz' => g('godz') === 'noc' ? 'noc' : 'zawsze',
            'auto_usun' => g('auto_usun') ? 1 : 0, 'start' => now(), 'info' => '', 'bledy' => 0, 'seg_mode' => $segMode, 'seg' => $segMode ? 0 : -1);
        if ($segMode && !empty($old0['pkd'])) { $j['pkd'] = $old0['pkd']; $j['branza_id'] = (int)$old0['branza_id']; }
        $old = noc_get(); // wznowienie tego samego zadania – zachowaj postęp
        if (g('wznow') && !empty($old['pkd']) && $old['pkd'] === $pk && isset($old['woj']) && $old['woj'] === $woj) { $j['wi'] = (int)$old['wi']; $j['page'] = (int)$old['page']; $j['seg'] = isset($old['seg']) ? (int)$old['seg'] : -1; $j['dodano'] = (int)$old['dodano']; $j['start'] = $old['start']; }
        noc_save($j);
        out(array());

    case 'ceidg_test':
        @set_time_limit(240);
        $tok = ceidg_token(); if ($tok === '') fail('Brak tokenu CEIDG');
        $cases = array(
            'Konstancin-Jeziorna (mazowieckie)' => 'pkd=4520Z&miasto=Konstancin-Jeziorna&wojewodztwo=mazowieckie&status=AKTYWNY&limit=25&page=0',
            'Wrocław (dolnośląskie)' => 'pkd=4520Z&miasto=Wroc%C5%82aw&wojewodztwo=dolno%C5%9Bl%C4%85skie&status=AKTYWNY&limit=25&page=0',
            'Wrocław (bez województwa)' => 'pkd=4520Z&miasto=Wroc%C5%82aw&status=AKTYWNY&limit=25&page=0',
            'całe dolnośląskie' => 'pkd=4520Z&wojewodztwo=dolno%C5%9Bl%C4%85skie&status=AKTYWNY&limit=25&page=0',
            'całe dolnośląskie, bez statusu' => 'pkd=4520Z&wojewodztwo=dolno%C5%9Bl%C4%85skie&limit=10&page=0',
            'dolnośląskie WIELKIMI' => 'pkd=4520Z&wojewodztwo=DOLNO%C5%9AL%C4%84SKIE&status=AKTYWNY&limit=25&page=0',
            'powiat wrocławski' => 'pkd=4520Z&powiat=wroc%C5%82awski&status=AKTYWNY&limit=25&page=0',
            'całe lubelskie' => 'pkd=4520Z&wojewodztwo=lubelskie&status=AKTYWNY&limit=25&page=0',
        );
        $out = array();
        foreach ($cases as $nm => $qs) {
            $w = ceidg_wait(); if ($w) { $out[] = array($nm, 0, 0, $w); continue; }
            $t = microtime(true);
            list($code, $body, $err) = http_req('GET', ceidg_base() . '/firmy?' . $qs, array('Authorization: Bearer ' . $tok, 'Accept: application/json'), null, 25);
            $sec = round(microtime(true) - $t, 1);
            $j = json_decode($body, true);
            $info = $err ? $err : ($code == 200 ? 'firm: ' . (isset($j['count']) ? $j['count'] : '?') . ', na stronie: ' . (isset($j['firmy']) ? count($j['firmy']) : 0) : mb_substr(trim(strip_tags((string)$body)), 0, 120));
            $out[] = array($nm, $code, $sec, $info);
            if ($code === 429) break;
        }
        out(array('wyniki' => $out));

    case 'noc_stop':
        $j = noc_get(); $j['on'] = 0; $j['info'] = 'Zatrzymane ręcznie.'; noc_save($j);
        out(array());

    case 'sz_dalej':
        @set_time_limit(60);
        list($n, $left, $err) = sz_step();
        out(array('zrobione' => $n, 'zostalo' => $left, 'blad' => $err));

    case 'sz_lista':
        $w = array('1=1'); $p = array();
        if ((int)g('branza_id')) { $w[] = 'branza_id=?'; $p[] = (int)g('branza_id'); }
        $miasta = array();
        foreach (all("SELECT miasto, COUNT(*) n FROM szukaj WHERE " . implode(' AND ', $w) . " AND status NOT IN('ukryty','aso') AND miasto<>'' GROUP BY miasto ORDER BY n DESC, miasto LIMIT 300", $p) as $r) $miasta[] = array($r['miasto'], (int)$r['n']);
        $zr = (string)g('zrodlo'); if (in_array($zr, array('ceidg', 'osm', 'google', 'uk'), true)) { $w[] = 'zrodlo=?'; $p[] = $zr; }
        $fm = trim((string)g('miasto')); if ($fm !== '') { $w[] = 'miasto=?'; $p[] = $fm; }
        $fq = trim((string)g('q')); if ($fq !== '') { $w[] = '(nazwa LIKE ? OR email LIKE ? OR osoba LIKE ?)'; $lk = '%' . str_replace(array('%', '_'), array('\\%', '\\_'), $fq) . '%'; $p[] = $lk; $p[] = $lk; $p[] = $lk; }
        $f = g('filtr');
        $cnt = array();
        foreach (all('SELECT status, COUNT(*) n FROM szukaj WHERE ' . implode(' AND ', $w) . ' GROUP BY status', $p) as $r) $cnt[$r['status']] = (int)$r['n'];
        $w[] = "status<>'ukryty'";
        if ($f !== 'aso') $w[] = "status<>'aso'";
        if ($f !== 'nieltd') $w[] = "status<>'nie_ltd'";
        if ($f === 'mail') $w[] = "status='gotowe'";
        elseif ($f === 'brak') $w[] = "status='brak_maila'";
        elseif ($f === 'jest') $w[] = "status IN('juz_jest','dodano')";
        elseif ($f === 'czeka') $w[] = "status IN('czeka','czeka_www','szukam','czeka_ch')";
        elseif ($f === 'nieltd') $w[] = "status='nie_ltd'";
        elseif ($f === 'aso') $w[] = "status='aso'";
        $rows = all('SELECT id,zrodlo,nazwa,osoba,email,telefon,www,miasto,adres,nip,branza_id,status FROM szukaj WHERE ' . implode(' AND ', $w) . ' ORDER BY id DESC LIMIT 2000', $p);
        $tags = array(); foreach (osm_tags() as $k => $v) $tags[$k] = $v[0];
        out(array('rows' => $rows, 'counts' => $cnt, 'ma_ceidg' => trim(setting('ceidg_token')) !== '', 'ma_google' => trim(setting('google_key')) !== '', 'ma_ch' => trim(setting('ch_key')) !== '', 'osm_tags' => $tags, 'miasta' => $miasta));

    case 'sz_przenies':
        $ids = array_map('intval', (array)g('ids'));
        if (!$ids) fail('Nic nie zaznaczono');
        $bid = (int)g('branza_id');
        if (!val('SELECT id FROM branze WHERE id=?', array($bid))) fail('Wybierz branżę');
        $ph = implode(',', array_fill(0, count($ids), '?'));
        $rows = all("SELECT * FROM szukaj WHERE id IN($ph) AND status='gotowe'", $ids);
        $n = 0;
        if (g('gdzie') === 'arkusz') {
            $cur = json_decode((string)setting('arkusz_' . $bid), true); if (!is_array($cur)) $cur = array();
            $have = array();
            foreach ($cur as $c) foreach (dup_keys(isset($c['email']) ? $c['email'] : '', isset($c['telefon']) ? $c['telefon'] : '', isset($c['nazwa']) ? $c['nazwa'] : '', isset($c['miasto']) ? $c['miasto'] : '', isset($c['www']) ? $c['www'] : '') as $k) $have[$k] = 1;
            $pominiete = 0;
            foreach ($rows as $r) {
                $keys = dup_keys($r['email'], $r['telefon'], $r['nazwa'], $r['miasto'], $r['www']);
                $rep = false; foreach ($keys as $k) if (isset($have[$k])) $rep = true;
                if ($rep || firm_dup_find($r['email'], $r['telefon'], $r['nazwa'], $r['miasto'], $r['www'])) { $pominiete++; q("UPDATE szukaj SET status='juz_jest' WHERE id=?", array($r['id'])); continue; }
                $cur[] = array('nazwa' => $r['nazwa'], 'email' => $r['email'], 'telefon' => $r['telefon'], 'miasto' => $r['miasto'], 'msg' => '', 'osoba' => $r['osoba'], 'www' => $r['www'], 'zrodlo' => $r['zrodlo']);
                foreach ($keys as $k) $have[$k] = 1;
                $n++;
            }
            setting_set('arkusz_' . $bid, json_encode($cur, JSON_UNESCAPED_UNICODE));
        } else {
            $have = array(); $pominiete = 0;
            foreach ($rows as $r) {
                $keys = dup_keys($r['email'], $r['telefon'], $r['nazwa'], $r['miasto'], $r['www']);
                $rep = false; foreach ($keys as $k) if (isset($have[$k])) $rep = true;
                if ($rep || firm_dup_find($r['email'], $r['telefon'], $r['nazwa'], $r['miasto'], $r['www'])) { $pominiete++; q("UPDATE szukaj SET status='juz_jest' WHERE id=?", array($r['id'])); continue; }
                foreach ($keys as $k) $have[$k] = 1;
                q('INSERT INTO firmy(nazwa,email,telefon,miasto,osoba,www,branza_id,status,token,dodano_at,notatka,zrodlo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                    array($r['nazwa'], $r['email'], $r['telefon'], $r['miasto'], mb_substr($r['osoba'], 0, 120), $r['www'], $bid, 'nowy', token(), now(), $r['adres'] . ($r['nip'] ? ' · NIP ' . $r['nip'] : ''), $r['zrodlo']));
                $n++;
            }
        }
        q("UPDATE szukaj SET status='dodano' WHERE id IN($ph) AND status='gotowe'", $ids);
        out(array('n' => $n, 'duplikaty' => $pominiete));

    case 'sz_przywroc':
        $r = one('SELECT * FROM szukaj WHERE id=?', array((int)g('id')));
        if (!$r) fail('Nie ma takiej pozycji');
        $st = $r['email'] ? 'gotowe' : ($r['www'] ? 'czeka_www' : 'brak_maila');
        if ($r['zrodlo'] === 'ceidg' && !$r['email'] && !$r['telefon']) $st = 'czeka';
        if ($r['email'] && firm_dup_find($r['email'], $r['telefon'], $r['nazwa'], $r['miasto'], $r['www'])) $st = 'juz_jest';
        q('UPDATE szukaj SET status=? WHERE id=?', array($st, $r['id']));
        out(array('status' => $st));

    case 'sz_ukryj_bez':
        $n = q("UPDATE szukaj SET status='ukryty' WHERE status='brak_maila'")->rowCount();
        out(array('n' => $n));

    case 'sz_usun_aso':
        $n = 0;
        foreach (all("SELECT id,nazwa,email,miasto FROM szukaj WHERE status NOT IN('dodano','aso','ukryty')") as $r) {
            $k = firm_kind($r['nazwa'], '', $r['email'], $r['miasto']);
            if ($k === 'aso' || ($k === 'siec' && g('sieci'))) { q("UPDATE szukaj SET status='aso' WHERE id=?", array($r['id'])); $n++; }
        }
        out(array('n' => $n));

    case 'sz_usun':
        if (g('wszystko')) { db()->exec('DELETE FROM szukaj'); out(array()); }
        $ids = array_map('intval', (array)g('ids'));
        if ($ids) q('DELETE FROM szukaj WHERE id IN(' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        out(array());

    case 'arkusz_get':
        $bid = (int)g('branza_id');
        $d = json_decode((string)setting('arkusz_' . $bid), true);
        $cnt = array();
        foreach (all("SELECT k,v FROM ustawienia WHERE k LIKE 'arkusz\\_%'") as $r) {
            $rows = json_decode((string)$r['v'], true); $n = 0;
            if (is_array($rows)) foreach ($rows as $x) if (is_array($x) && trim(implode('', array_map('strval', array_intersect_key($x, array('nazwa' => 1, 'email' => 1, 'telefon' => 1, 'miasto' => 1))))) !== '') $n++;
            $cnt[(int)substr($r['k'], 7)] = $n;
        }
        out(array('rows' => is_array($d) ? $d : array(), 'szkice' => $cnt));

    case 'arkusz_szkic':
        $rows = array();
        foreach (array_slice((array)g('rows'), 0, 2000) as $r) {
            if (!is_array($r)) continue;
            $rows[] = array('nazwa' => mb_substr((string)(isset($r['nazwa']) ? $r['nazwa'] : ''), 0, 255), 'email' => mb_substr((string)(isset($r['email']) ? $r['email'] : ''), 0, 190),
                'telefon' => mb_substr((string)(isset($r['telefon']) ? $r['telefon'] : ''), 0, 60), 'miasto' => mb_substr((string)(isset($r['miasto']) ? $r['miasto'] : ''), 0, 120),
                'msg' => mb_substr((string)(isset($r['msg']) ? $r['msg'] : ''), 0, 60),
                'osoba' => mb_substr((string)(isset($r['osoba']) ? $r['osoba'] : ''), 0, 120), 'www' => mb_substr((string)(isset($r['www']) ? $r['www'] : ''), 0, 255), 'zrodlo' => mb_substr((string)(isset($r['zrodlo']) ? $r['zrodlo'] : ''), 0, 10));
        }
        setting_set('arkusz_' . (int)g('branza_id'), json_encode($rows, JSON_UNESCAPED_UNICODE));
        out(array());

    case 'arkusz_zapisz':
        $rows = (array)g('rows');
        $valid = array();
        foreach (all('SELECT id FROM branze') as $b) $valid[(int)$b['id']] = 1;
        $res = array(); $seen = array(); $added = 0;
        foreach ($rows as $i => $r) {
            if (!is_array($r)) { $res[] = array('ok' => 0, 'msg' => 'pusty'); continue; }
            $email = mb_strtolower(trim(isset($r['email']) ? (string)$r['email'] : ''));
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) { $res[] = array('ok' => 0, 'msg' => 'zły e-mail'); continue; }
            $rt = trim(isset($r['telefon']) ? (string)$r['telefon'] : ''); $rn = trim(isset($r['nazwa']) ? (string)$r['nazwa'] : ''); $rm = trim(isset($r['miasto']) ? (string)$r['miasto'] : ''); $rw = trim(isset($r['www']) ? (string)$r['www'] : '');
            $keys = dup_keys($email, $rt, $rn, $rm, $rw);
            $rep = false; foreach ($keys as $k) if (isset($seen[$k])) $rep = true;
            if ($rep) { $res[] = array('ok' => 0, 'msg' => 'powtórzony w arkuszu'); continue; }
            foreach ($keys as $k) $seen[$k] = 1;
            $dd = firm_dup_find($email, $rt, $rn, $rm, $rw);
            if ($dd && !(isset($r['wymus']) && $r['wymus'])) { $res[] = array('ok' => 0, 'msg' => dup_msg($dd)); continue; }
            $bid = isset($r['branza_id']) ? (int)$r['branza_id'] : 0;
            if (!isset($valid[$bid])) { $res[] = array('ok' => 0, 'msg' => 'wybierz branżę'); continue; }
            $S = function ($k, $max) use ($r) { return mb_substr(trim(isset($r[$k]) ? (string)$r[$k] : ''), 0, $max); };
            q('INSERT INTO firmy(nazwa,email,telefon,miasto,osoba,www,branza_id,status,token,dodano_at,notatka,zrodlo) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                array($S('nazwa', 255), $email, $S('telefon', 60), $S('miasto', 120), $S('osoba', 120), $S('www', 255), $bid, 'nowy', token(), now(), '', in_array($S('zrodlo', 10), array('ceidg', 'google', 'osm'), true) ? $S('zrodlo', 10) : ''));
            $added++;
            $res[] = array('ok' => 1, 'msg' => 'dodano');
        }
        out(array('res' => $res, 'dodano' => $added));

    case 'eksport':
        $rows = all('SELECT f.nazwa,f.email,f.telefon,f.miasto,b.nazwa branza,f.www,f.osoba,f.status,f.wyslano_at,f.otwarto_at,f.przypomnienie_at FROM firmy f LEFT JOIN branze b ON b.id=f.branza_id ORDER BY f.id');
        $csv = "nazwa;email;telefon;miasto;branza;www;osoba;status;wyslano;otwarto;przypomnienie\n";
        foreach ($rows as $r) $csv .= implode(';', array_map(function ($v) { return '"' . str_replace('"', '""', (string)$v) . '"'; }, $r)) . "\n";
        out(array('csv' => $csv));

    // ---------- BRANŻE I SZABLONY ----------
    case 'branze':
        out(array('rows' => all("SELECT b.*, COUNT(f.id) firmy, SUM(f.wyslano_at IS NOT NULL) wyslane, SUM(f.otwarto_at IS NOT NULL) otwarte, SUM(f.klik_at IS NOT NULL) klik, SUM(f.status='zainteresowany') zaint, SUM(f.status='nowy') nowe
            FROM branze b LEFT JOIN firmy f ON f.branza_id=b.id GROUP BY b.id ORDER BY b.kolejnosc,b.id")));

    case 'branza_zapisz':
        $n = gs('nazwa', 120);
        if ($n === '') fail('Podaj nazwę branży');
        $id = (int)g('id');
        if ($id) {
            q('UPDATE branze SET nazwa=?, aktywna=? WHERE id=?', array($n, g('aktywna') ? 1 : 0, $id));
        } else {
            q('INSERT INTO branze(nazwa,aktywna,kolejnosc) VALUES(?,1,?)', array($n, (int)val('SELECT COALESCE(MAX(kolejnosc),0)+1 FROM branze')));
            $id = (int)db()->lastInsertId();
            // gotowe szablony dopasowane po nazwie (inaczej ogólne)
            foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2', 'potwierdzenie') as $t) save_template($id, $t, default_template_for($n, $t));
        }
        out(array('id' => $id));

    case 'branza_usun':
        $id = (int)g('id');
        if ((int)val('SELECT COUNT(*) FROM branze') <= 1) fail('Musi zostać co najmniej jedna branża');
        if ((int)val('SELECT COUNT(*) FROM firmy WHERE branza_id=?', array($id))) fail('W tej branży są firmy – najpierw przenieś je do innej');
        q('DELETE FROM szablony WHERE branza_id=?', array($id));
        q('DELETE FROM branze WHERE id=?', array($id));
        out(array());

    case 'szablony':
        $bid = (int)g('branza_id');
        if ($bid && !val("SELECT id FROM szablony WHERE branza_id=? AND typ='przypomnienie2'", array($bid))) { // nowy typ – wstaw gotowy tekst
            $bn = (string)val('SELECT nazwa FROM branze WHERE id=?', array($bid));
            if ($bn !== '') save_template($bid, 'przypomnienie2', default_template_for($bn, 'przypomnienie2'));
        }
        $rows = all('SELECT typ,temat,nadtytul,naglowek,tresc,przycisk,bloki,updated_at FROM szablony WHERE branza_id=?', array($bid));
        $o = array();
        foreach ($rows as $r) { $o[$r['typ']] = array('temat' => $r['temat'], 'bloki' => tpl_blocks($r), 'updated_at' => $r['updated_at']); }
        out(array('szablony' => $o));

    case 'szablon_zapisz':
        $typ = g('typ');
        if (!in_array($typ, array('pierwszy', 'przypomnienie', 'przypomnienie2', 'potwierdzenie'), true)) fail('Zły typ');
        save_template((int)g('branza_id'), $typ, array('temat' => gs('temat'), 'bloki' => clean_blocks(g('bloki'))));
        out(array('updated_at' => now()));

    case 'szablon_domyslny':
        $typ = g('typ');
        if (!in_array($typ, array('pierwszy', 'przypomnienie', 'przypomnienie2', 'potwierdzenie'), true)) fail('Zły typ');
        $bn = (string)val('SELECT nazwa FROM branze WHERE id=?', array((int)g('branza_id')));
        out(array('t' => default_template_for($bn, $typ)));

    case 'form_get':
        out(array('cfg' => form_cfg(), 'def' => form_defaults()));

    case 'form_podglad':
        $c = form_cfg((array)g('cfg'));
        $pre = array('firma' => 'Auto-Serwis Kowalski', 'telefon' => '600 100 200', 'email' => 'biuro@kowalski.pl');
        out(array('html' => pub_html($c['tytul'], g('view') === 'dzieki' ? thanks_body($c, 'biuro@kowalski.pl') : form_body($c, $pre, ''))));

    case 'form_zapisz':
        setting_set('formularz', json_encode(form_cfg((array)g('cfg')), JSON_UNESCAPED_UNICODE));
        out(array());

    case 'podglad':
    case 'test':
        set_lang_branza((int)g('branza_id'));
        // podgląd z niezapisanym podpisem (zakładka Podpis)
        if (is_array(g('podpis'))) {
            setting('x');
            foreach (array('pozdrowienie', 'podpis_imie', 'podpis_rola', 'podpis_tel', 'podpis_email', 'podpis_www', 'stopka_firma') as $k) {
                $pp = g('podpis'); if (isset($pp[$k])) $GLOBALS['_ST'][$k] = mb_substr(trim((string)$pp[$k]), 0, 300);
            }
        }
        $tpl = array('temat' => gs('temat'), 'bloki' => clean_blocks(g('bloki')));
        $sample = array('nazwa' => 'Auto-Serwis Kowalski', 'miasto' => 'Warszawa', 'osoba' => 'Jan', 'token' => 'test');
        $links = g('typ') === 'potwierdzenie' ? array() : array('btn_url' => BASE_URL . '/p.php?a=tak&t=test', 'unsub_url' => BASE_URL . '/p.php?a=wypis&t=test');
        list($subj, $html, $text) = render_mail($tpl, $sample, $links);
        if ($a === 'podglad') out(array('temat' => $subj, 'html' => $html));
        $to = gs('to', 190) !== '' ? gs('to', 190) : setting('powiadom_email');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) fail('Podaj poprawny adres do testu');
        if (!send_mail($to, '[TEST] ' . $subj, $html, $text, $links)) fail('Serwer nie wysłał maila – sprawdź adres nadawcy w Ustawieniach');
        logi(null, 'test', 'Test do ' . $to);
        out(array('to' => $to));

    // ---------- ZGŁOSZENIA ----------
    case 'zgloszenia':
        out(array('rows' => all('SELECT z.*, b.nazwa branza FROM zgloszenia z LEFT JOIN firmy f ON f.id=z.firma_id LEFT JOIN branze b ON b.id=f.branza_id ORDER BY z.id DESC LIMIT 2000')));

    // ---------- PUBLIKACJE (FB / IG / TikTok) ----------
    case 'soc_lista':
        soc_migrate();
        $rows = all('SELECT * FROM social_posty ORDER BY (kiedy IS NULL) DESC, kiedy DESC, id DESC LIMIT 300');
        foreach ($rows as &$r) { $r['url'] = $r['plik'] !== '' ? soc_url($r['plik']) : ''; } unset($r);
        out(array('rows' => $rows, 'meta' => array('ok' => meta_ok(), 'page' => setting('meta_page_name'), 'ig' => setting('meta_ig_name'), 'ig_id' => setting('meta_ig_id'))));

    case 'soc_zapisz':
        soc_migrate();
        $id = (int)g('id'); $akcja = gs('akcja', 20);
        $plik = preg_replace('/[^A-Za-z0-9_.\-]/', '', gs('plik', 190));
        if ($plik !== '' && !is_file(soc_dir() . '/' . $plik)) fail('Nie ma takiego pliku – wgraj filmik jeszcze raz');
        $opis = mb_substr((string)g('opis'), 0, 2200);
        $fb = g('fb') ? 1 : 0; $ig = g('ig') ? 1 : 0; $tt = g('tt') ? 1 : 0;
        $kiedy = gs('kiedy', 20);
        $kiedy = $kiedy !== '' && strtotime($kiedy) ? date('Y-m-d H:i:s', strtotime($kiedy)) : null;
        if ($akcja !== 'szkic') {
            if ($plik === '') fail('Wgraj filmik');
            if (!$fb && !$ig && !$tt) fail('Zaznacz przynajmniej jedno miejsce publikacji');
            if (($fb || $ig) && !meta_ok()) fail('Najpierw połącz Facebooka i Instagrama (przycisk na górze)');
            if ($ig && setting('meta_ig_id') === '') fail('Do strony na Facebooku nie jest podpięte konto Instagram (firmowe)');
        }
        if (!$id && g('hurt') && gs('tytul', 160) !== '' && (int)val("SELECT COUNT(*) FROM social_posty WHERE tytul=? AND stan<>'blad'", array(gs('tytul', 160)))) {
            if ($plik !== '' && !(int)val('SELECT COUNT(*) FROM social_posty WHERE plik=?', array($plik))) @unlink(soc_dir() . '/' . $plik);
            out(array('pominieto' => true));
        }
        if ($akcja === 'teraz') $kiedy = now();
        if ($akcja === 'zaplanuj' && !$kiedy) fail('Wybierz datę i godzinę');
        $stan = $akcja === 'szkic' ? 'szkic' : 'zaplanowany';
        if ($id) {
            $old = one('SELECT * FROM social_posty WHERE id=?', array($id)); if (!$old) fail('Nie ma takiej publikacji');
            if (in_array($old['stan'], array('w_toku', 'opublikowany'), true)) fail('Ta publikacja już poszła – utwórz nową');
            q("UPDATE social_posty SET tytul=?, opis=?, plik=?, kiedy=?, stan=?, fb=?, ig=?, tt=?, fb_status='', fb_err='', ig_status='', ig_container='', ig_err='', ig_start=NULL, tt_status='' WHERE id=?",
                array(gs('tytul', 160), $opis, $plik, $kiedy, $stan, $fb, $ig, $tt, $id));
        } else {
            q('INSERT INTO social_posty(tytul,opis,plik,kiedy,stan,fb,ig,tt,created_at) VALUES(?,?,?,?,?,?,?,?,?)', array(gs('tytul', 160), $opis, $plik, $kiedy, $stan, $fb, $ig, $tt, now()));
            $id = (int)db()->lastInsertId();
        }
        $info = '';
        if ($akcja === 'teraz') $info = soc_run($id);
        out(array('id' => $id, 'info' => $info, 'row' => one('SELECT * FROM social_posty WHERE id=?', array($id))));

    // pliki wrzucone FileZillą do uploads/social/wrzuc/ – import bez trzymania otwartej strony
    case 'soc_inbox':
        $d = soc_dir() . '/wrzuc'; if (!is_dir($d)) @mkdir($d, 0755, true);
        $out = array();
        foreach ((array)glob($d . '/*') as $f) {
            if (!is_file($f) || !preg_match('/\.(mp4|mov)$/i', $f)) continue;
            $out[] = array('name' => basename($f), 'size' => filesize($f));
        }
        usort($out, function ($a, $b) { return strnatcasecmp($a['name'], $b['name']); });
        out(array('files' => $out));

    case 'soc_inbox_take':
        $n = basename(gs('name', 190));
        $src = soc_dir() . '/wrzuc/' . $n;
        if ($n === '' || !is_file($src) || !preg_match('/\.(mp4|mov)$/i', $n)) fail('Nie ma pliku ' . $n . ' w folderze wrzuc');
        $fh = fopen($src, 'rb'); $head = fread($fh, 12); fclose($fh);
        if (substr($head, 4, 4) !== 'ftyp') fail('Plik ' . $n . ' nie wygląda na wideo MP4/MOV');
        $ext = strtolower(pathinfo($n, PATHINFO_EXTENSION));
        $name = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 10) . '.' . $ext;
        if (!@rename($src, soc_dir() . '/' . $name)) fail('Nie mogę przenieść pliku ' . $n);
        out(array('plik' => $name, 'url' => soc_url($name)));

    case 'soc_odswiez': // ręczne „sprawdź teraz” (np. Instagram jeszcze przetwarza)
        out(array('info' => soc_run((int)g('id'))));

    case 'soc_ponow':
        soc_migrate();
        $p = one('SELECT * FROM social_posty WHERE id=?', array((int)g('id'))); if (!$p) fail('Nie ma takiej publikacji');
        q("UPDATE social_posty SET stan='zaplanowany', kiedy=NOW(), fb_status=IF(fb_status='blad','',fb_status), fb_err='', ig_status=IF(ig_status='blad','',ig_status), ig_container=IF(ig_status='blad','',ig_container), ig_err='' WHERE id=?", array($p['id']));
        out(array('info' => soc_run($p['id'])));

    case 'soc_tt':
        soc_migrate();
        q("UPDATE social_posty SET tt_status=? WHERE id=?", array(g('done') ? 'gotowe' : 'przypomniano', (int)g('id')));
        out(array());

    case 'soc_usun':
        soc_migrate();
        $p = one('SELECT * FROM social_posty WHERE id=?', array((int)g('id'))); if (!$p) fail('Nie ma takiej publikacji');
        $inne = $p['plik'] !== '' ? (int)val('SELECT COUNT(*) FROM social_posty WHERE plik=? AND id<>?', array($p['plik'], $p['id'])) : 1;
        if (!$inne && $p['plik'] !== '') @unlink(soc_dir() . '/' . $p['plik']);
        q('DELETE FROM social_posty WHERE id=?', array($p['id']));
        out(array());

    case 'soc_usun_wiele': // masowo: zaznaczone albo wszystkie jeszcze nieopublikowane
        soc_migrate();
        if (g('wszystkie')) $rows = all("SELECT * FROM social_posty WHERE stan IN('szkic','zaplanowany','blad')");
        else {
            $ids = array_values(array_filter(array_map('intval', (array)g('ids'))));
            if (!$ids) fail('Nic nie zaznaczono');
            $rows = all('SELECT * FROM social_posty WHERE id IN(' . implode(',', array_fill(0, count($ids), '?')) . ") AND stan<>'w_toku'", $ids);
        }
        $n = 0;
        foreach ($rows as $p) {
            q('DELETE FROM social_posty WHERE id=?', array($p['id']));
            if ($p['plik'] !== '' && !(int)val('SELECT COUNT(*) FROM social_posty WHERE plik=?', array($p['plik']))) @unlink(soc_dir() . '/' . $p['plik']);
            $n++;
        }
        out(array('n' => $n));

    case 'meta_polacz':
        $app = preg_replace('/[^0-9]/', '', gs('app_id', 40)); $sec = gs('secret', 80); $tok = gs('token', 600);
        if ($app === '' || $sec === '' || $tok === '') fail('Wpisz ID aplikacji, klucz tajny i token');
        list($pages, $e) = meta_connect($app, $sec, $tok);
        if ($e) fail($e);
        if (count($pages) === 1) { meta_set_page($pages[0]); out(array('done' => true, 'page' => $pages[0]['name'], 'ig' => $pages[0]['ig_name'])); }
        $list = array(); foreach ($pages as $p) $list[] = array('id' => $p['id'], 'name' => $p['name'], 'ig' => $p['ig_name']);
        out(array('done' => false, 'pages' => $list));

    case 'meta_wybierz':
        list($pages, $e) = meta_pages(setting('meta_user_token'));
        if ($e) fail($e);
        foreach ($pages as $p) if ($p['id'] === gs('page_id', 40)) { meta_set_page($p); out(array('page' => $p['name'], 'ig' => $p['ig_name'])); }
        fail('Nie znalazłem tej strony');

    case 'meta_odswiez_ig': // np. po podpięciu Instagrama do strony już po połączeniu
        list($pages, $e) = meta_pages(setting('meta_user_token'));
        if ($e) fail($e);
        foreach ($pages as $p) if ($p['id'] === setting('meta_page_id')) { meta_set_page($p); out(array('page' => $p['name'], 'ig' => $p['ig_name'])); }
        fail('Nie widzę już tej strony – połącz ponownie');

    case 'meta_rozlacz':
        foreach (array('meta_page_id', 'meta_page_name', 'meta_page_token', 'meta_ig_id', 'meta_ig_name', 'meta_user_token') as $k) setting_set($k, '');
        out(array());

    // ---------- LANDING /start – statystyki wejść ----------
    case 'landing_stats':
        db()->exec("CREATE TABLE IF NOT EXISTS landing_log (id INT AUTO_INCREMENT PRIMARY KEY, zrodlo VARCHAR(20) NOT NULL DEFAULT '', akcja VARCHAR(20) NOT NULL, sid VARCHAR(32) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, KEY zc (zrodlo, created_at), KEY ac (akcja, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        add_col('zgloszenia', 'zrodlo', "VARCHAR(30) NOT NULL DEFAULT ''");
        $res = array();
        foreach (array('dzis' => 'CURDATE()', 'd7' => 'CURDATE() - INTERVAL 6 DAY', 'd30' => 'CURDATE() - INTERVAL 29 DAY') as $k => $od) {
            $r = array('wejscie' => 0, 'formularz' => 0, 'telefon' => 0, 'strona' => 0, 'mail' => 0, 'zgloszenia' => 0, 'zrodla' => array());
            foreach (all("SELECT akcja, COUNT(DISTINCT sid) n FROM landing_log WHERE created_at >= $od GROUP BY akcja") as $x) $r[$x['akcja']] = (int)$x['n'];
            foreach (all("SELECT zrodlo, COUNT(DISTINCT sid) n FROM landing_log WHERE akcja='wejscie' AND created_at >= $od GROUP BY zrodlo") as $x) $r['zrodla'][$x['zrodlo']] = (int)$x['n'];
            $r['zgloszenia'] = (int)val("SELECT COUNT(*) FROM zgloszenia WHERE zrodlo<>'' AND created_at >= $od");
            $res[$k] = $r;
        }
        $res['push'] = setting('land_push', '1');
        out($res);

    case 'landing_push':
        setting_set('land_push', g('on') === '1' ? '1' : '0');
        out(array('push' => setting('land_push')));

    case 'zgloszenie_edytuj':
        $id = (int)g('id');
        if (!val('SELECT id FROM zgloszenia WHERE id=?', array($id))) fail('Nie ma takiego zgłoszenia');
        $st = g('status'); if (!in_array($st, array('nowe', 'zadzwonione', 'umowione', 'klient', 'odrzucone'), true)) $st = 'nowe';
        $em = gs('email', 190); if ($em !== '' && !filter_var($em, FILTER_VALIDATE_EMAIL)) fail('Niepoprawny e-mail');
        if (gs('firma') === '') fail('Podaj nazwę firmy');
        add_col('zgloszenia', 'kiedy', "VARCHAR(120) NOT NULL DEFAULT ''");
        add_col('zgloszenia', 'kontakt_data', 'DATE NULL'); add_col('zgloszenia', 'kontakt_co', "VARCHAR(255) NOT NULL DEFAULT ''");
        $kd = gs('kontakt_data', 10); $kd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $kd) ? $kd : null;
        q('UPDATE zgloszenia SET firma=?, osoba=?, telefon=?, email=?, czym=?, co=?, kiedy=?, status=?, notatka=?, kontakt_data=?, kontakt_co=? WHERE id=?',
            array(gs('firma'), gs('osoba', 120), gs('telefon', 60), $em, gs('czym', 2000), gs('co', 3000), gs('kiedy', 120), $st, mb_substr((string)g('notatka'), 0, 20000), $kd, gs('kontakt_co', 255), $id));
        if ($st === 'odrzucone') { $fid = (int)val('SELECT firma_id FROM zgloszenia WHERE id=?', array($id)); if ($fid) q("UPDATE firmy SET status='nie' WHERE id=?", array($fid)); }
        out(array());

    case 'zgloszenie_wpis': // szybki wpis po rozmowie: notatka z datą + następny kontakt + status
        $id = (int)g('id');
        $z = one('SELECT * FROM zgloszenia WHERE id=?', array($id)); if (!$z) fail('Nie ma takiego zgłoszenia');
        add_col('zgloszenia', 'kontakt_data', 'DATE NULL'); add_col('zgloszenia', 'kontakt_co', "VARCHAR(255) NOT NULL DEFAULT ''");
        $txt = trim((string)g('tekst'));
        $not = (string)$z['notatka'];
        if ($txt !== '') $not = date('d.m.Y H:i') . "\n" . $txt . ($not !== '' ? "\n\n" . $not : '');
        $st = g('status'); if (!in_array($st, array('nowe', 'zadzwonione', 'umowione', 'klient', 'odrzucone'), true)) $st = $z['status'];
        $kd = gs('kontakt_data', 10); $kd = preg_match('/^\d{4}-\d{2}-\d{2}$/', $kd) ? $kd : null;
        q('UPDATE zgloszenia SET notatka=?, status=?, kontakt_data=?, kontakt_co=? WHERE id=?', array(mb_substr($not, 0, 20000), $st, $kd, gs('kontakt_co', 255), $id));
        if ($st === 'odrzucone' && $z['firma_id']) q("UPDATE firmy SET status='nie' WHERE id=?", array($z['firma_id']));
        out(array());

    case 'zgloszenie_get':
        wiad_migrate();
        $z = one('SELECT z.*, b.nazwa branza FROM zgloszenia z LEFT JOIN firmy f ON f.id=z.firma_id LEFT JOIN branze b ON b.id=f.branza_id WHERE z.id=?', array((int)g('id')));
        if (!$z) fail('Nie ma takiego zgłoszenia');
        $f = $z['firma_id'] ? one('SELECT id,nazwa,www,miasto,wyslano_at,otwarto_at,otwarcia,klik_at,przypomnienie_at FROM firmy WHERE id=?', array($z['firma_id'])) : null;
        $w = all('SELECT id,temat,bloki,wyslano_at,otwarcia,otwarto_at,otwarto_last,klik_at,kliki FROM wiadomosci WHERE zgloszenie_id=? ORDER BY (wyslano_at IS NULL) DESC, id DESC', array($z['id']));
        out(array('z' => $z, 'firma' => $f, 'wiad' => $w));

    case 'wiad_upload': // zdjęcie jako data URL (przeskalowane w przeglądarce)
        $d = (string)g('data');
        if (!preg_match('#^data:image/(jpeg|png|webp);base64,(.+)$#', $d, $m)) fail('Nieobsługiwany plik – tylko JPG, PNG, WEBP');
        $bin = base64_decode($m[2], true);
        if ($bin === false || strlen($bin) > 6000000) fail('Plik za duży (max 6 MB)');
        $info = @getimagesizefromstring($bin);
        if (!$info || !in_array($info[2], array(IMAGETYPE_JPEG, IMAGETYPE_PNG, IMAGETYPE_WEBP), true)) fail('To nie jest obrazek');
        $dir = __DIR__ . '/uploads/maile';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $ext = $m[1] === 'jpeg' ? 'jpg' : $m[1];
        $name = date('Ymd') . '_' . bin2hex(random_bytes(8)) . '.' . $ext;
        if (@file_put_contents($dir . '/' . $name, $bin) === false) fail('Nie udało się zapisać pliku na serwerze');
        out(array('url' => BASE_URL . '/uploads/maile/' . $name));

    case 'wiad_podglad':
    case 'wiad_test':
    case 'wiad_szkic':
    case 'wiad_wyslij':
        wiad_migrate();
        $z = one('SELECT * FROM zgloszenia WHERE id=?', array((int)g('zgloszenie_id')));
        if (!$z) fail('Nie ma takiego zgłoszenia');
        $temat = gs('temat', 255); if ($temat === '' && $a !== 'wiad_podglad') fail('Wpisz temat');
        $bl = array();
        foreach ((array)g('bloki') as $b) {
            if (!is_array($b) || empty($b['t'])) continue;
            if ($b['t'] === 'tekst' && trim((string)$b['text']) !== '') $bl[] = array('t' => 'tekst', 'text' => mb_substr((string)$b['text'], 0, 20000));
            elseif ($b['t'] === 'obrazek' && preg_match('#^https?://#i', (string)$b['url'])) $bl[] = array('t' => 'obrazek', 'url' => (string)$b['url']);
            elseif (in_array($b['t'], array('tytul', 'punkty'), true) && trim((string)$b['text']) !== '') $bl[] = array('t' => $b['t'], 'text' => mb_substr((string)$b['text'], 0, 5000));
            elseif ($b['t'] === 'ramka' && trim((string)(isset($b['title']) ? $b['title'] : '') . (isset($b['text']) ? $b['text'] : '')) !== '') $bl[] = array('t' => 'ramka', 'title' => mb_substr((string)$b['title'], 0, 200), 'text' => mb_substr((string)$b['text'], 0, 3000));
            elseif ($b['t'] === 'linia') $bl[] = array('t' => 'linia');
            elseif ($b['t'] === 'przycisk' && preg_match('#^https?://#i', (string)$b['url']) && trim((string)$b['text']) !== '') $bl[] = array('t' => 'przycisk', 'text' => mb_substr((string)$b['text'], 0, 80), 'url' => (string)$b['url']);
        }
        if (!$bl && $a !== 'wiad_podglad' && $a !== 'wiad_szkic') fail('Wiadomość jest pusta');
        $wid = (int)g('id');
        $draft = $wid ? one('SELECT * FROM wiadomosci WHERE id=? AND zgloszenie_id=? AND wyslano_at IS NULL', array($wid, $z['id'])) : null;
        if ($a === 'wiad_szkic') {
            $bj = json_encode($bl, JSON_UNESCAPED_UNICODE);
            if ($draft) q('UPDATE wiadomosci SET temat=?, bloki=? WHERE id=?', array($temat, $bj, $draft['id']));
            else { q('INSERT INTO wiadomosci(zgloszenie_id,firma_id,email,temat,bloki,token) VALUES(?,?,?,?,?,?)', array($z['id'], $z['firma_id'], (string)$z['email'], $temat, $bj, token())); $wid = (int)db()->lastInsertId(); }
            out(array('id' => $draft ? (int)$draft['id'] : $wid));
        }
        $to = $z['email'];
        if ($a === 'wiad_wyslij' && !filter_var($to, FILTER_VALIDATE_EMAIL)) fail('Zgłoszenie nie ma poprawnego e-maila');
        $w = array('temat' => $temat, 'bloki' => json_encode($bl, JSON_UNESCAPED_UNICODE), 'token' => $a === 'wiad_wyslij' ? ($draft ? $draft['token'] : token()) : str_repeat('0', 32));
        $firm = array('nazwa' => $z['firma'], 'miasto' => '', 'osoba' => $z['osoba'], 'token' => '');
        list($subj, $html, $text) = wiad_render($w, $firm);
        if ($a === 'wiad_podglad') out(array('temat' => $subj, 'html' => $html));
        if ($a === 'wiad_test') {
            $tt = setting('powiadom_email') ?: setting('nadawca_email');
            if (!send_mail($tt, $subj, $html, $text, array())) fail('Serwer nie wysłał maila');
            out(array('to' => $tt));
        }
        if (!send_mail($to, $subj, $html, $text, array())) fail('Serwer nie wysłał maila');
        if ($draft) q('UPDATE wiadomosci SET email=?, temat=?, bloki=?, wyslano_at=? WHERE id=?', array($to, $subj, $w['bloki'], now(), $draft['id']));
        else q('INSERT INTO wiadomosci(zgloszenie_id,firma_id,email,temat,bloki,token,wyslano_at) VALUES(?,?,?,?,?,?,?)', array($z['id'], $z['firma_id'], $to, $subj, $w['bloki'], $w['token'], now()));
        $not = date('d.m.Y H:i') . "\nWysłano wiadomość: " . $subj . ($z['notatka'] !== '' ? "\n\n" . $z['notatka'] : '');
        q('UPDATE zgloszenia SET notatka=? WHERE id=?', array(mb_substr($not, 0, 20000), $z['id']));
        if ($z['firma_id']) logi($z['firma_id'], 'wiadomosc', mb_substr($subj, 0, 200));
        out(array());

    case 'wiad_usun':
        q('DELETE FROM wiadomosci WHERE id=? AND wyslano_at IS NULL', array((int)g('id')));
        out(array());

    case 'zgloszenie_usun':
        $ids = array_map('intval', (array)g('ids'));
        if ($ids) q('DELETE FROM zgloszenia WHERE id IN(' . implode(',', array_fill(0, count($ids), '?')) . ')', $ids);
        out(array('n' => count($ids)));

    case 'zgloszenie_zapisz':
        $st = in_array(g('status'), array('nowe', 'zadzwonione', 'umowione', 'klient', 'odrzucone'), true) ? g('status') : 'nowe';
        q('UPDATE zgloszenia SET status=?, notatka=? WHERE id=?', array($st, mb_substr((string)g('notatka'), 0, 5000), (int)g('id')));
        if ($st === 'odrzucone') { $fid = (int)val('SELECT firma_id FROM zgloszenia WHERE id=?', array((int)g('id'))); if ($fid) q("UPDATE firmy SET status='nie' WHERE id=?", array($fid)); }
        out(array());

    // ---------- USTAWIENIA ----------
    case 'ustawienia':
        $keys = array('nadawca_nazwa', 'nadawca_email', 'reply_to', 'powiadom_email', 'ntfy_topic', 'podpis_imie', 'podpis_rola', 'podpis_tel', 'podpis_email', 'podpis_www', 'pozdrowienie', 'stopka_firma',
            'auto_on', 'porcja_auto', 'limit_dzienny', 'porcja', 'godz_od', 'godz_do', 'dni_przypomnienia', 'przyp2_on', 'dni_przypomnienia2', 'przyp2_kto', 'case_study', 'admin_login', 'ostatni_cron', 'ceidg_token', 'google_key', 'ch_key', 'stopka_en', 'podpis_rola_en', 'podpis_imie_en', 'pozdrowienie_en', 'imap_on', 'imap_host', 'imap_port', 'imap_user', 'imap_info');
        $o = array();
        foreach ($keys as $k) $o[$k] = setting($k);
        $o['imap_pass_set'] = setting('imap_pass') !== '';
        $o['cron_url'] = BASE_URL . '/cron.php?key=' . CRON_KEY;
        $o['case_default'] = default_case_study();
        out(array('u' => $o));

    case 'ustawienia_zapisz':
        $u = (array)g('u');
        $txt = array('nadawca_nazwa', 'nadawca_email', 'reply_to', 'powiadom_email', 'ntfy_topic', 'podpis_imie', 'podpis_rola', 'podpis_tel', 'podpis_email', 'podpis_www', 'pozdrowienie', 'stopka_firma', 'case_study', 'ceidg_token', 'google_key', 'ch_key', 'stopka_en', 'podpis_rola_en', 'podpis_imie_en', 'pozdrowienie_en');
        $num = array('limit_dzienny' => array(1, 1000), 'porcja' => array(1, 50), 'godz_od' => array(0, 23), 'godz_do' => array(1, 24), 'dni_przypomnienia' => array(1, 60), 'dni_przypomnienia2' => array(1, 60));
        foreach (array('nadawca_email', 'reply_to', 'powiadom_email', 'podpis_email') as $k) {
            if (isset($u[$k]) && trim($u[$k]) !== '' && !filter_var(trim($u[$k]), FILTER_VALIDATE_EMAIL)) fail('Niepoprawny e-mail: ' . $k);
        }
        foreach ($txt as $k) if (isset($u[$k])) setting_set($k, mb_substr(trim((string)$u[$k]), 0, 5000));
        foreach ($num as $k => $r) if (isset($u[$k])) setting_set($k, max($r[0], min($r[1], (int)$u[$k])));
        if (isset($u['auto_on'])) setting_set('auto_on', $u['auto_on'] ? '1' : '0');
        if (isset($u['porcja_auto'])) setting_set('porcja_auto', $u['porcja_auto'] ? '1' : '0');
        if (isset($u['imap_on'])) setting_set('imap_on', $u['imap_on'] ? '1' : '0');
        foreach (array('imap_host', 'imap_user') as $k) if (isset($u[$k])) setting_set($k, mb_substr(trim((string)$u[$k]), 0, 190));
        if (isset($u['imap_port'])) setting_set('imap_port', (string)max(1, min(65535, (int)$u['imap_port'] ?: 993)));
        if (isset($u['imap_pass']) && (string)$u['imap_pass'] !== '') setting_set('imap_pass', (string)$u['imap_pass']);
        if (isset($u['przyp2_on'])) setting_set('przyp2_on', $u['przyp2_on'] ? '1' : '0');
        if (isset($u['przyp2_kto'])) setting_set('przyp2_kto', $u['przyp2_kto'] === 'otwarte' ? 'otwarte' : 'wszyscy');
        out(array());

    case 'auto':
        setting_set('auto_on', g('on') ? '1' : '0'); out(array());

    case 'haslo':
        if (!password_verify((string)g('stare'), (string)setting('admin_hash'))) fail('Stare hasło się nie zgadza');
        if (strlen((string)g('nowe')) < 8) fail('Nowe hasło: min. 8 znaków');
        setting_set('admin_hash', password_hash((string)g('nowe'), PASSWORD_DEFAULT));
        out(array());

    case 'wyslij_teraz':
        @set_time_limit(300);
        out(array('r' => run_queue(true, array('branza' => (int)g('branza'), 'miasto' => gs('miasto', 120), 'max' => min(30, max(1, (int)g('max') ?: 30))))));

    case 'push_test':
        require_once __DIR__ . '/webpush.php';
        $n = wp_send_all('Test ELOK', 'Powiadomienia działają.');
        $nt = push_ntfy('Test ELOK', 'Powiadomienia działają.');
        if (!$n && !$nt) fail('Nie udało się wysłać – włącz powiadomienia na telefonie przyciskiem powyżej');
        out(array('urzadzenia' => $n));

    case 'push_key':
        require_once __DIR__ . '/webpush.php'; wp_migrate();
        list($pub, ) = wp_keys();
        if ($pub === '') fail('Serwer nie obsługuje szyfrowania powiadomień (OpenSSL)');
        out(array('key' => $pub, 'ile' => (int)val('SELECT COUNT(*) FROM push_subs')));

    case 'push_sub':
        require_once __DIR__ . '/webpush.php'; wp_migrate();
        $ep = (string)g('endpoint'); $pk = (string)g('p256dh'); $au = (string)g('auth');
        if (!preg_match('~^https://~', $ep) || strlen($ep) > 700 || strlen(wp_b64d($pk)) !== 65 || strlen(wp_b64d($au)) < 16) fail('Złe dane subskrypcji');
        q('DELETE FROM push_subs WHERE endpoint=?', array($ep));
        q('INSERT INTO push_subs(endpoint,p256dh,auth,urzadzenie,created_at) VALUES(?,?,?,?,?)', array($ep, $pk, $au, mb_substr((string)g('urzadzenie'), 0, 120), now()));
        $code = wp_send_one(array('endpoint' => $ep, 'p256dh' => $pk, 'auth' => $au), json_encode(array('title' => 'ELOK', 'body' => 'Powiadomienia włączone na tym urządzeniu ', 'url' => BASE_URL), JSON_UNESCAPED_UNICODE));
        out(array('ile' => (int)val('SELECT COUNT(*) FROM push_subs'), 'kod' => $code));

    case 'push_unsub':
        require_once __DIR__ . '/webpush.php'; wp_migrate();
        q('DELETE FROM push_subs WHERE endpoint=?', array((string)g('endpoint')));
        out(array());

    default:
        fail('Nieznana akcja');
    }
} catch (Exception $e) {
    error_log('ELOK api: ' . $e->getMessage());
    fail('Błąd serwera: ' . $e->getMessage());
}
