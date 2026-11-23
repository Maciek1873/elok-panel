<?php
// ELOK – Agent Eli: sam szuka firm, którym możemy pomóc, i dokłada je do mailingu.
// Zadania: 1) nowo zarejestrowane firmy (CEIDG) – gratulacje + strona,
//          2) firmy, które szukają pracownika biurowego (ogłoszenia OLX) – automatyzacje zamiast etatu,
//          3) firmy ze słabą stroną – Eli sprawdza stronę i wpisuje do maila konkretny problem,
//          4) Instagram – lista firm z profilem i gotową wiadomością (wysyłasz ręcznie, Instagram nie pozwala na automat).
// Działa z crona (cron.php) i z przycisku „Uruchom teraz” w zakładce Agent Eli.
if (!defined('ELOK')) { http_response_code(403); exit; }
require_once __DIR__ . '/szablony.php';
require_once __DIR__ . '/zrodla.php';

define('AG_SCHEMA', '4');
define('AG_BR_NOWE', 'Eli – nowe firmy');
define('AG_BR_PRACA', 'Eli – szukają pracownika');
define('AG_BR_STRONA', 'Eli – słaba strona');
define('AG_BR_AUTO', 'Eli – dobra strona, automatyzacje');

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
    add_col('firmy', 'eli_problem', "VARCHAR(800) NOT NULL DEFAULT ''");
    add_col('firmy', 'wlasny', 'MEDIUMTEXT NULL');
    add_col('szukaj', 'wlasny', 'TEXT NULL');
    add_col('szukaj', 'audyt', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_col('szukaj', 'ig_chk', 'TINYINT(1) NOT NULL DEFAULT 0');
    add_col('ag_oferty', 'wlasny', 'TEXT NULL');
    add_col('ag_oferty', 'obowiazki', "VARCHAR(255) NOT NULL DEFAULT ''");
    add_col('szukaj', 'form_chk', 'TINYINT(1) NOT NULL DEFAULT 0');
    db()->exec("CREATE TABLE IF NOT EXISTS ag_form (
      id INT AUTO_INCREMENT PRIMARY KEY, szukaj_id INT NOT NULL, nazwa VARCHAR(255) NOT NULL DEFAULT '', miasto VARCHAR(120) NOT NULL DEFAULT '',
      www VARCHAR(255) NOT NULL DEFAULT '', url VARCHAR(400) NOT NULL DEFAULT '', problem VARCHAR(255) NOT NULL DEFAULT '', captcha TINYINT(1) NOT NULL DEFAULT 0,
      status VARCHAR(20) NOT NULL DEFAULT 'do_wyslania', created_at DATETIME NOT NULL, akcja_at DATETIME NULL, UNIQUE KEY sz (szukaj_id), KEY st (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS ag_audyt (
      id INT AUTO_INCREMENT PRIMARY KEY, szukaj_id INT NOT NULL, nazwa VARCHAR(255) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '',
      telefon VARCHAR(60) NOT NULL DEFAULT '', osoba VARCHAR(160) NOT NULL DEFAULT '', miasto VARCHAR(120) NOT NULL DEFAULT '', www VARCHAR(255) NOT NULL DEFAULT '',
      problemy TEXT, problem_txt TEXT, wynik INT NOT NULL DEFAULT 0, psi INT NULL, sekundy DECIMAL(5,1) NULL, status VARCHAR(20) NOT NULL DEFAULT 'czeka', wlasny TEXT NULL,
      firma_id INT NULL, created_at DATETIME NOT NULL, UNIQUE KEY sz (szukaj_id), KEY st (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS ag_ig (
      id INT AUTO_INCREMENT PRIMARY KEY, ig VARCHAR(80) NOT NULL, nazwa VARCHAR(255) NOT NULL DEFAULT '', miasto VARCHAR(120) NOT NULL DEFAULT '',
      www VARCHAR(255) NOT NULL DEFAULT '', problem VARCHAR(160) NOT NULL DEFAULT '', status VARCHAR(20) NOT NULL DEFAULT 'do_wyslania',
      created_at DATETIME NOT NULL, akcja_at DATETIME NULL, UNIQUE KEY ig (ig), KEY st (status)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    ag_branze();
    // nowa wersja maila do nowych firm (v2) – podmień, jeśli to wciąż pierwsza wersja
    $bN = ag_br_id(AG_BR_NOWE);
    if ($bN && setting('ag_nowe_v2') !== '1') {
        $t = one("SELECT temat FROM szablony WHERE branza_id=? AND typ='pierwszy'", array($bN));
        if (!$t || $t['temat'] === 'Gratulacje z okazji otwarcia firmy') { $set = ag_sets(); save_template($bN, 'pierwszy', $set[AG_BR_NOWE]['pierwszy']); }
        setting_set('ag_nowe_v2', '1');
    }
    // v3: maile „jeden problem → jedno rozwiązanie” – podmień tylko gotowe teksty, których nikt nie zmieniał
    if (setting('ag_teksty_v3') !== '1') {
        $stare = array('Gratulacje i krótkie pytanie', 'Re: Gratulacje i krótkie pytanie', 'Gratulacje z okazji otwarcia firmy', 'Re: Gratulacje z okazji otwarcia firmy', 'Ogłoszenie o pracę – {stanowisko}', 'Re: Ogłoszenie o pracę – {stanowisko}', 'Państwa strona {www}', 'Re: Państwa strona {www}', 'Ostatnia wiadomość ode mnie');
        foreach (ag_sets() as $nazwa => $set) {
            $bid = ag_br_id($nazwa); if (!$bid) continue;
            foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2') as $typ) {
                $t = one('SELECT temat FROM szablony WHERE branza_id=? AND typ=?', array($bid, $typ));
                if (!$t || in_array($t['temat'], $stare, true)) save_template($bid, $typ, $set[$typ]);
            }
        }
        $c = ag_cfg(); $c['ig']['msg'] = ag_cfg_def()['ig']['msg']; ag_cfg_save($c);
        setting_set('ag_teksty_v3', '1');
    }
    // v4: bez „wystarczy odpisać tak” i bez cen; przycisk do umówienia konsultacji – podmień, jeśli tekst jest wciąż gotowy
    if (setting('ag_teksty_v4') !== '1') {
        foreach (ag_sets() as $nazwa => $set) {
            $bid = ag_br_id($nazwa); if (!$bid) continue;
            foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2') as $typ) {
                $t = one('SELECT temat,bloki,tresc FROM szablony WHERE branza_id=? AND typ=?', array($bid, $typ));
                $raw = $t ? (string)$t['bloki'] . (string)$t['tresc'] : '';
                if (!$t || mb_strpos($raw, 'odpisać „tak”') !== false || mb_strpos($raw, 'odpisać \u201etak') !== false || mb_strpos($raw, 'ile to kosztuje') !== false || mb_strpos($raw, 'jedno słowo') !== false || mb_strpos($raw, 'ostatni mail ode mnie') !== false) save_template($bid, $typ, $set[$typ]);
            }
        }
        $c = ag_cfg(); $d = ag_cfg_def();
        if (mb_strpos($c['form']['msg'], 'ile to kosztuje') !== false) $c['form']['msg'] = $d['form']['msg'];
        ag_cfg_save($c);
        setting_set('ag_teksty_v4', '1');
    }
}

// ---------- ustawienia i stan ----------
function ag_cfg_def() {
    return array(
        'on' => 0,
        'nowe' => array('on' => 1, 'dni' => 14, 'woj' => array(), 'pkd' => '', 'dziennie' => 25, 'reczny' => 0),
        'praca' => array('on' => 1, 'frazy' => "pracownik biurowy\nwprowadzanie danych\nfakturowanie\nobsługa zamówień\nasystentka biura\nspecjalista ds. administracji", 'dziennie' => 15, 'reczny' => 1),
        'strona' => array('on' => 1, 'dziennie' => 20, 'reczny' => 1, 'psi' => 1, 'min' => 2, 'auto' => 1),
        'ig' => array('on' => 1, 'msg' => "Dzień dobry! Tu {imie} z ELOK.\nZajrzałem na stronę {firma}{problem_ig}. Przez takie rzeczy część klientów wychodzi ze strony, zanim zadzwoni.\nMogę podesłać, co konkretnie poprawić? Bez zobowiązań."),
        'form' => array('on' => 1, 'msg' => "Dzień dobry,\n\nzajrzałem na stronę {www} tak, jak robi to klient z telefonu, i zauważyłem, że {problem_krotko}. Przez takie rzeczy część klientów wychodzi ze strony, zanim zadzwoni.\n\nJestem {imie} z ELOK. Pomagamy firmom poprawić takie rzeczy: doradzimy, co zmienić, a jeśli Państwo zechcą, zrobimy to. Chętnie porozmawiam 15 minut, bezpłatnie i bez zobowiązań. Najprościej odpisać na maila albo zadzwonić.\n\n{imie}, ELOK\n{moj_telefon} · {moj_email}"),
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

// Gotowe maile agenta (pierwszy + 2 przypomnienia + potwierdzenie) – każdy da się zmienić w zakładce Agent Eli
// mail = treść + przycisk „Umów bezpłatną konsultację” (kliknięcie widać w panelu) + inne sposoby kontaktu
function ag_build($temat, $tekst, $btn, $sub, $po) {
    $bl = array(array('t' => 'tekst', 'text' => $tekst));
    if (trim($btn) !== '') $bl[] = array('t' => 'przycisk', 'text' => $btn, 'sub' => $sub);
    if (trim($po) !== '') $bl[] = array('t' => 'tekst', 'text' => $po);
    return array('temat' => $temat, 'bloki' => $bl, 'nadtytul' => '', 'naglowek' => '', 'tresc' => '', 'przycisk' => '');
}
// rozbicie szablonu na części do edycji: treść, napis na przycisku, podpis pod przyciskiem, tekst po przycisku
function ag_parts($tpl) {
    $o = array('tekst' => '', 'btn' => '', 'sub' => '', 'po' => ''); $after = false; $pre = array(); $post = array();
    foreach (tpl_blocks($tpl) as $x) {
        $t = isset($x['t']) ? $x['t'] : '';
        if ($t === 'przycisk') { $o['btn'] = (string)$x['text']; $o['sub'] = isset($x['sub']) ? (string)$x['sub'] : ''; $after = true; continue; }
        if (isset($x['text']) && in_array($t, array('tekst', 'ramka'), true)) { if ($after) $post[] = $x['text']; else $pre[] = $x['text']; }
    }
    $o['tekst'] = implode("\n\n", $pre); $o['po'] = implode("\n\n", $post);
    return $o;
}
// podmiana tylko treści głównej (własna wersja maila dla jednej firmy) – przycisk i dopisek zostają
function ag_tpl_with_text($tpl, $temat, $tekst) {
    $p = ag_parts($tpl);
    $n = ag_build($temat, $tekst, $p['btn'], $p['sub'], $p['po']);
    $tpl['temat'] = $temat; $tpl['bloki'] = json_encode($n['bloki'], JSON_UNESCAPED_UNICODE);
    return $tpl;
}
function ag_sets() {
    // Zasada: jeden realny problem klienta → jedno rozwiązanie. Bez cen i bez „wystarczy odpisać tak”.
    // Kończymy propozycją bezpłatnej konsultacji: przycisk (widać, kto kliknął) albo odpowiedź na maila / telefon.
    $btn = 'Umów bezpłatną konsultację'; $sub = 'Wybierz dogodny termin – zajmie to 30 sekund.';
    $alt = 'Wolą Państwo inaczej? Wystarczy odpowiedzieć na tego maila albo zadzwonić: {moj_telefon}.';
    $alt2 = 'Można też po prostu odpowiedzieć na tego maila albo zadzwonić: {moj_telefon}.';
    $m = function ($temat, $txt, $b = null, $s2 = null, $po = null) use ($btn, $sub, $alt) { return ag_build($temat, $txt, $b === null ? $btn : $b, $s2 === null ? $sub : $s2, $po === null ? $alt : $po); };
    $confirm = default_template_for('Inne firmy', 'potwierdzenie');
    return array(
        AG_BR_NOWE => array(
            'pierwszy' => $m('Gratulacje – pytanie o pierwszych klientów',
                "Dzień dobry,\n\ngratuluję założenia firmy!\n\nZanim klient zadzwoni, zwykle wpisuje usługę w Google i wybiera spośród firm, które tam znajdzie. Nowa firma bez strony w tym momencie po prostu nie istnieje, a zlecenia trafiają do konkurencji.\n\nJestem {imie} z ELOK. Pomagamy nowym firmom zdobyć pierwszych klientów z internetu: doradzimy, od czego zacząć, i przygotujemy stronę, przez którą klient od razu zostawia zapytanie, a Państwo dostają powiadomienie na telefon.\n\nChętnie porozmawiam 15 minut i podpowiem, co ma sens na start. Bezpłatnie i bez zobowiązań."),
            'przypomnienie' => $m('Re: Gratulacje – pytanie o pierwszych klientów',
                "Dzień dobry,\n\nwracam krótko do mojej wiadomości. Jeśli pierwsi klienci z internetu to temat na teraz, chętnie podpowiem, od czego zacząć. Rozmowa jest bezpłatna i do niczego nie zobowiązuje.", 'Umów rozmowę', '', $alt2),
            'przypomnienie2' => $m('Ostatnia wiadomość ode mnie',
                "Dzień dobry,\n\nto już ostatni mail ode mnie, nie będę zaśmiecał skrzynki.\n\nGdyby kiedyś przydała się pomoc ze stroną albo z pozyskiwaniem klientów, jestem pod telefonem: {moj_telefon}.\n\nPowodzenia na starcie!", '', '', ''),
            'potwierdzenie' => $confirm,
        ),
        AG_BR_PRACA => array(
            'pierwszy' => $m('Ogłoszenie – {stanowisko}',
                "Dzień dobry,\n\nwidzę, że szukają Państwo osoby na stanowisko {stanowisko}. W ogłoszeniu jest m.in. {obowiazki}.\n\nTo zadania, które da się ustawić tak, żeby robiły się same: zamówienie samo trafia do systemu, faktura wystawia się po opłaceniu, a klient dostaje maila bez niczyjego udziału. Wtedy nie trzeba zatrudniać kogoś do przepisywania danych, a jeśli nowa osoba i tak dojdzie, zajmie się klientami, a nie papierami.\n\nJestem {imie} z ELOK. Doradzamy i wdrażamy takie automatyzacje w firmach. Chętnie w 15 minut podpowiem, co z tej listy da się u Państwa zautomatyzować. Bezpłatnie i bez zobowiązań."),
            'przypomnienie' => $m('Re: Ogłoszenie – {stanowisko}',
                "Dzień dobry,\n\nwracam krótko do mojej wiadomości w sprawie ogłoszenia. Chętnie pokażę, które z tych obowiązków mogą robić się same. Rozmowa jest bezpłatna i do niczego nie zobowiązuje.", 'Umów rozmowę', '', $alt2),
            'przypomnienie2' => $m('Ostatnia wiadomość ode mnie',
                "Dzień dobry,\n\nto już ostatni mail ode mnie, nie będę zaśmiecał skrzynki.\n\nGdyby temat przepisywania danych i dokumentów wrócił, jestem pod telefonem: {moj_telefon}.\n\nPowodzenia w rekrutacji!", '', '', ''),
            'potwierdzenie' => $confirm,
        ),
        AG_BR_STRONA => array(
            'pierwszy' => $m('Pytanie o stronę {www}',
                "Dzień dobry,\n\nzajrzałem na stronę {www} tak, jak robi to klient z telefonu, i zauważyłem:\n\n{problem}\n\nKażda taka rzecz to klienci, którzy wchodzą na stronę, ale nie dzwonią, tylko wracają do Google i wybierają kolejną firmę.\n\nJestem {imie} z ELOK. Pomagamy firmom poprawić strony tak, żeby przynosiły zapytania: doradzimy, co zmienić, a jeśli Państwo zechcą, zrobimy to za Państwa. Chętnie pokażę to na krótkiej konsultacji. Bezpłatnie i bez zobowiązań."),
            'przypomnienie' => $m('Re: Pytanie o stronę {www}',
                "Dzień dobry,\n\nwracam krótko do mojej wiadomości o stronie {www}. Chętnie pokażę, co konkretnie poprawić. Nawet jeśli zrobią to Państwo sami albo z kimś innym, wskazówki się przydadzą.", 'Umów rozmowę', '', $alt2),
            'przypomnienie2' => $m('Ostatnia wiadomość ode mnie',
                "Dzień dobry,\n\nto już ostatni mail ode mnie w sprawie strony, nie będę zaśmiecał skrzynki.\n\nGdyby temat wrócił, jestem pod telefonem: {moj_telefon}.\n\nPozdrawiam i powodzenia!", '', '', ''),
            'potwierdzenie' => $confirm,
        ),
        AG_BR_AUTO => array(
            'pierwszy' => $m('Pomysł dla {firma}',
                "Dzień dobry,\n\nzajrzałem na stronę {www}. Jest zrobiona porządnie, więc nie piszę w sprawie strony.\n\n{pomysl}\n\nJestem {imie} z ELOK. Doradzamy firmom, co ma sens zautomatyzować, i wdrażamy to. Chętnie w 15 minut podpowiem, co da się usprawnić u Państwa. Bezpłatnie i bez zobowiązań."),
            'przypomnienie' => $m('Re: Pomysł dla {firma}',
                "Dzień dobry,\n\nwracam krótko do mojej wiadomości. Chętnie pokażę na przykładzie, jak to działa w podobnej firmie. Rozmowa jest bezpłatna i do niczego nie zobowiązuje.", 'Umów rozmowę', '', $alt2),
            'przypomnienie2' => $m('Ostatnia wiadomość ode mnie',
                "Dzień dobry,\n\nto już ostatni mail ode mnie, nie będę zaśmiecał skrzynki.\n\nGdyby temat automatyzacji wrócił, jestem pod telefonem: {moj_telefon}.\n\nPozdrawiam!", '', '', ''),
            'potwierdzenie' => $confirm,
        ),
    );
}
function ag_branze() {
    add_col('branze', 'limit_dzienny', 'INT NOT NULL DEFAULT 0');
    foreach (ag_sets() as $nazwa => $set) {
        if (ag_br_id($nazwa)) continue;
        q('INSERT INTO branze(nazwa,aktywna,kolejnosc,limit_dzienny) VALUES(?,1,?,?)', array($nazwa, (int)val('SELECT COALESCE(MAX(kolejnosc),0)+1 FROM branze'), 25));
        $id = (int)db()->lastInsertId();
        foreach ($set as $typ => $t) save_template($id, $typ, $t);
    }
}

// licznik dodanych dziś (dla limitu dziennego zadania)
function ag_dzis(&$s, $zad) {
    if (!isset($s['dzis']) || $s['dzis']['data'] !== date('Y-m-d')) $s['dzis'] = array('data' => date('Y-m-d'), 'nowe' => 0, 'praca' => 0, 'strona' => 0, 'audyt' => 0);
    foreach (array('nowe', 'praca', 'strona', 'audyt') as $k) if (!isset($s['dzis'][$k])) $s['dzis'][$k] = 0;
    return (int)$s['dzis'][$zad];
}

// dodanie firmy do mailingu (Firmy) – z pominięciem duplikatów
function ag_do_firm($d, $bid) {
    if (!filter_var($d['email'], FILTER_VALIDATE_EMAIL)) return array(0, 'zły e-mail');
    $dup = firm_dup_find($d['email'], isset($d['telefon']) ? $d['telefon'] : '', $d['nazwa'], isset($d['miasto']) ? $d['miasto'] : '', isset($d['www']) ? $d['www'] : '');
    if ($dup) return array(0, 'już jest w Firmach');
    q('INSERT INTO firmy(nazwa,email,telefon,miasto,osoba,www,branza_id,status,token,dodano_at,notatka,zrodlo,eli_tytul,eli_problem,wlasny) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
        array(mb_substr($d['nazwa'], 0, 255), $d['email'], mb_substr(isset($d['telefon']) ? $d['telefon'] : '', 0, 60), mb_substr(isset($d['miasto']) ? $d['miasto'] : '', 0, 120),
            mb_substr(isset($d['osoba']) ? $d['osoba'] : '', 0, 120), mb_substr(isset($d['www']) ? $d['www'] : '', 0, 255), $bid, 'nowy', token(), now(),
            mb_substr(isset($d['notatka']) ? $d['notatka'] : '', 0, 2000), isset($d['zrodlo']) ? $d['zrodlo'] : 'eli', mb_substr(isset($d['stanowisko']) ? $d['stanowisko'] : '', 0, 160),
            mb_substr(isset($d['problem']) ? $d['problem'] : '', 0, 800), !empty($d['wlasny']) ? $d['wlasny'] : null));
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
                    'notatka' => 'Agent Eli – nowa firma (CEIDG, start ' . ($r['eli_data'] ?: '?') . '). ' . $r['adres'] . ($r['nip'] ? ' · NIP ' . $r['nip'] : ''), 'zrodlo' => 'ceidg', 'wlasny' => $r['wlasny']), $bid);
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
// obowiązki z opisu ogłoszenia, które da się zautomatyzować (do maila: „W ogłoszeniu jest m.in. …”)
function ag_obowiazki($opis) {
    $t = mb_strtolower($opis);
    $map = array('faktur' => 'wystawianie faktur', 'zam[óo]wie' => 'przyjmowanie i przepisywanie zamówień', 'ofert' => 'przygotowywanie ofert', 'wprowadzan|baz[aęy] danych|uzupe[łl]nian' => 'wprowadzanie danych',
        'magazyn' => 'dokumenty magazynowe', 'korespondenc|maili|wiadomo[śs]ci' => 'odpisywanie na maile i wiadomości', 'cennik|zmiany cen' => 'aktualizacja cenników', 'reklamac' => 'obsługa reklamacji',
        'rozlicze' => 'rozliczenia', 'umów|um[óo]w|protok' => 'przygotowywanie umów i protokołów', 'grafik|termin|rezerwac' => 'pilnowanie terminów i rezerwacji');
    $o = array(); foreach ($map as $re => $txt) if (preg_match('/' . $re . '/u', $t)) $o[] = $txt;
    $o = array_slice($o, 0, 3);
    if (!$o) return '';
    if (count($o) === 1) return $o[0];
    $last = array_pop($o);
    return implode(', ', $o) . ' i ' . $last;
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
                q('INSERT IGNORE INTO ag_oferty(ext_id,tytul,stanowisko,firma,konto,miasto,url,opis,powod,status,ogl_at,created_at,obowiazki) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?)',
                    array('olx' . $o['id'], mb_substr($o['title'], 0, 255), mb_substr(ag_stanowisko($o['title'], isset($o['location']['city']['name']) ? $o['location']['city']['name'] : ''), 0, 160), mb_substr($firma, 0, 255),
                        mb_substr(isset($o['user']['name']) ? $o['user']['name'] : '', 0, 120), mb_substr(isset($o['location']['city']['name']) ? $o['location']['city']['name'] : '', 0, 120),
                        mb_substr(isset($o['url']) ? $o['url'] : '', 0, 400), mb_substr($opis, 0, 6000), $powod, $powod ? 'odrzucona' : 'nowa', $ogl, now(), mb_substr(ag_obowiazki($opis), 0, 255)));
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
        'notatka' => 'Agent Eli – ogłoszenie o pracę: ' . $r['tytul'] . ' ' . $r['url'], 'zrodlo' => 'olx', 'wlasny' => $r['wlasny'], 'problem' => $r['obowiazki']), $bid);
    q('UPDATE ag_oferty SET status=?, firma_id=? WHERE id=?', array($fid ? 'dodano' : 'juz_jest', $fid ?: null, $r['id']));
    if ($fid) { ag_dzis($s, 'praca'); $s['dzis']['praca']++; ag_log('praca', 'dodano', $nazwa . ' – ' . $r['email'] . ' (' . $r['stanowisko'] . ')'); }
    return $fid;
}

// ---------- ZADANIE 3: firmy ze słabą stroną ----------
// pobranie strony z pomiarem czasu i końcowym adresem (po przekierowaniach)
function ag_fetch($url, $timeout = 15) {
    if (!function_exists('curl_init')) return array(0, '', $url, 0);
    $c = curl_init($url);
    curl_setopt_array($c, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => $timeout, CURLOPT_CONNECTTIMEOUT => 7, CURLOPT_FOLLOWLOCATION => true, CURLOPT_MAXREDIRS => 5,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (Linux; Android 13; Pixel 7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120 Mobile Safari/537.36', CURLOPT_ENCODING => '',
        CURLOPT_HTTPHEADER => array('Accept: text/html', 'Accept-Language: pl-PL')));
    $html = curl_exec($c);
    $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $final = (string)curl_getinfo($c, CURLINFO_EFFECTIVE_URL); $t = (float)curl_getinfo($c, CURLINFO_TOTAL_TIME);
    curl_close($c);
    if ($html === false) $html = '';
    return array($code, substr($html, 0, 1500000), $final ?: $url, $t);
}
function ag_ig_z_html($html) {
    if (preg_match_all('#instagram\.com/([A-Za-z0-9_.]{2,30})#i', $html, $m)) {
        foreach ($m[1] as $h) { $h = rtrim($h, '.'); if (!in_array(strtolower($h), array('p', 'reel', 'explore', 'accounts', 'stories', 'tv', 'about', 'developer', 'instagram', 'legal', 'direct', 'sharer'), true)) return $h; }
    }
    return '';
}
function ag_psi($url) {
    $k = trim((string)setting('psi_key'));
    list($code, $body) = http_req('GET', 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed?strategy=mobile&category=performance&url=' . rawurlencode($url) . ($k ? '&key=' . rawurlencode($k) : ''), array('Accept: application/json'), null, 55);
    if ($code !== 200) { setting_set('ag_psi_blad', date('Y-m-d H:i') . ' ' . ($code ?: 'brak połączenia')); return array(null, null); }
    $j = json_decode($body, true);
    $sc = isset($j['lighthouseResult']['categories']['performance']['score']) ? (int)round($j['lighthouseResult']['categories']['performance']['score'] * 100) : null;
    $lcp = isset($j['lighthouseResult']['audits']['largest-contentful-paint']['numericValue']) ? round($j['lighthouseResult']['audits']['largest-contentful-paint']['numericValue'] / 1000, 1) : null;
    return array($sc, $lcp);
}
function ag_num($x) { return str_replace('.', ',', (string)$x); }
// sprawdza stronę tak, jak widzi ją klient z telefonu
// zwraca array(problemy[], psi, sekundy, ig, blad, form_url, captcha); problem: array(krótko – z podmiotem, zdanie do maila, waga)
function ag_audyt($www, $psi_on) {
    if (!preg_match('#^https?://#i', $www)) $www = 'http://' . $www;
    list($code, $html, $final, $t) = ag_fetch($www);
    if ($code === 0 || $code >= 500) { list($code, $html, $final, $t) = ag_fetch($www, 20); } // druga próba
    if ($code === 0 || $code >= 400 || $html === '') return array(array(), null, null, '', 'strona nie odpowiada (' . ($code ?: 'brak połączenia') . ')', '', 0, array());
    $sklep = (bool)preg_match('/woocommerce|shoper|idosell|iai-shop|prestashop|shopify|add-to-cart|do koszyka|dodaj do koszyka/i', $html);
    $kto = $sklep ? 'sklep' : 'strona';
    $p = array();
    if (stripos($final, 'https://') !== 0) $p[] = array($kto . ' nie ma certyfikatu SSL', '- ' . $kto . ' nie ma certyfikatu SSL, więc przeglądarka pokazuje przy adresie „Niezabezpieczona”. Wielu klientów w tym momencie wychodzi' . ($sklep ? ', a już na pewno nie wpisze danych do zamówienia' : '') . ',', 3);
    if (!preg_match('/<meta[^>]+name=["\']?viewport/i', $html)) $p[] = array($kto . ' nie jest dopasowana do telefonu', '- ' . $kto . ' nie jest dopasowan' . ($sklep ? 'y' : 'a') . ' do telefonu, na komórce trzeba ' . ($sklep ? 'go' : 'ją') . ' powiększać palcami, a dziś większość klientów szuka właśnie z telefonu,', 3);
    $psi = null; $lcp = null;
    if ($psi_on) list($psi, $lcp) = ag_psi($final);
    $koszt = $sklep ? ' W sklepie każda sekunda czekania to mniej zamówień,' : ' Po 3 sekundach czekania sporo osób wraca do wyników Google,';
    if ($psi !== null && $psi < 40) $p[] = array($kto . ' ma ' . $psi . '/100 w teście szybkości Google', '- Google ocenia szybkość ' . ($sklep ? 'sklepu' : 'strony') . ' na telefonie na ' . $psi . '/100' . ($lcp ? ', a główna treść pojawia się dopiero po ok. ' . ag_num($lcp) . ' s' : '') . '.' . $koszt, $psi < 30 ? 3 : 2);
    elseif ($t > 4) $p[] = array($kto . ' ładuje się ok. ' . ag_num(round($t, 1)) . ' s', '- ' . $kto . ' otwiera się ok. ' . ag_num(round($t, 1)) . ' s.' . $koszt, 2);
    // formularz – na stronie głównej albo na podstronie kontaktowej
    $form_url = ''; $captcha = 0; $hk = $html;
    if (stripos($html, '<form') !== false && preg_match('/<(textarea|input[^>]+type=["\']?email)/i', $html)) $form_url = $final;
    if (!$form_url && preg_match('/href=["\']([^"\'#]*(kontakt|contact|napisz|zapytanie)[^"\']*)["\']/i', $html, $mm) && stripos($mm[1], 'mailto:') !== 0) {
        $l = $mm[1];
        if (!preg_match('#^https?://#i', $l)) { $pu = parse_url($final); $l = (isset($pu['scheme']) ? $pu['scheme'] : 'http') . '://' . (isset($pu['host']) ? $pu['host'] : '') . '/' . ltrim($l, '/'); }
        list($c2, $h2) = ag_fetch($l, 10);
        if ($c2 >= 200 && $c2 < 400) { $hk .= $h2; if (stripos($h2, '<form') !== false && preg_match('/<(textarea|input[^>]+type=["\']?email)/i', $h2)) { $form_url = $l; $html2 = $h2; } }
    }
    if ($form_url) $captcha = preg_match('/recaptcha|hcaptcha|turnstile|captcha/i', isset($html2) ? $html2 : $html) ? 1 : 0;
    if (!$form_url && !$sklep) $p[] = array('na stronie nie ma formularza kontaktowego', '- nie ma formularza, przez który klient może od razu zostawić zapytanie, musi szukać telefonu albo maila,', 2);
    if (!preg_match('/href=["\']tel:/i', $hk) && preg_match('/(\+48[\s\-]?)?\b\d{3}[\s\-]\d{3}[\s\-]\d{3}\b/', strip_tags($hk)))
        $p[] = array('numeru telefonu nie da się kliknąć na komórce', '- numeru telefonu nie da się kliknąć na komórce, klient musi go przepisywać, a część po prostu tego nie robi,', 2);
    // --- sprzedaż: czy strona prowadzi klienta do kontaktu / zakupu ---
    $bi = stripos($html, '<body'); $body = $bi !== false ? substr($html, $bi) : $html;
    $top = substr($body, 0, (int)(strlen($body) * 0.25));
    $txt = preg_replace('/\s+/u', ' ', strip_tags(preg_replace('#<(script|style)[^>]*>.*?</\1>#is', ' ', $body)));
    $ctaRe = $sklep ? '(kup|do koszyka|zamów|zamow|sklep|zobacz produkt|kolekcj|sprawdź|bestseller|nowości)' : '(zadzwo|umów|umow|zamów|zamow|wycen|rezerw|zapytaj|skontaktuj|napisz|zapisz się|sprawdź termin)';
    if (!preg_match('/<(a|button)[^>]*>[^<]{0,80}' . $ctaRe . '/iu', $top) && !preg_match('/href=["\']tel:/i', $top))
        $p[] = array($sklep ? 'na pierwszym ekranie sklepu nie ma wyraźnego przycisku do zakupów' : 'na pierwszym ekranie nie ma przycisku do kontaktu',
            $sklep ? '- na pierwszym ekranie nie ma wyraźnego przycisku typu „Kup” czy „Zobacz produkty”, klient musi sam szukać, gdzie kliknąć,' : '- na pierwszym ekranie nie ma przycisku typu „Zadzwoń” czy „Umów termin”, klient musi przewijać i szukać, jak się z Państwem skontaktować,', 2);
    if (!preg_match('/opini|recenzj|gwiazdk|★|testimonial|ocen[ay] klient|polecaj/iu', $txt))
        $p[] = array('na stronie głównej nie ma opinii klientów', '- na stronie głównej nie ma opinii klientów, a to one najbardziej przekonują nowych' . ($sklep ? ' do zakupu' : ' do telefonu') . ',', 1);
    $wizyty = (bool)preg_match('/wizyt|termin|zabieg|gabinet|salon|fryzjer|kosmetycz|fizjoterap|masaż|szkoła jazdy|serwis|przegląd/iu', $txt);
    $online = (bool)preg_match('/booksy|calendly|reservio|znanylekarz|versum|moment\.pl|rezerwuj online|zarezerwuj online|umów (się )?online|kalendarz rezerwacji|wybierz termin/iu', $hk);
    if (!$sklep && $wizyty && !$online)
        $p[] = array('klient nie może umówić się online', '- termin można umówić tylko telefonicznie. Kto chce się zapisać wieczorem albo wtedy, gdy Państwo pracują i nie odbierają, często wybiera firmę, gdzie zrobi to od razu online,', 2);
    if (!$sklep && !preg_match('/realizacj|portfolio|galeri|nasze prace|efekty|przed i po|metamorfoz|case stud/iu', $txt))
        $p[] = array('na stronie nie widać Państwa prac', '- na stronie nie widać zdjęć Państwa prac ani realizacji, a klient przed telefonem chce zobaczyć, że robią Państwo to dobrze,', 1);
    if (!$sklep && !preg_match('/\d\s?zł|cennik|PLN|od \d+/iu', $txt))
        $p[] = array('na stronie nie ma żadnych cen', '- nie ma żadnych cen ani cennika, część klientów nie dzwoni, bo boi się kwoty,', 1);
    $nsc = preg_match_all('/<script/i', $html); $ncss = preg_match_all('/<link[^>]+stylesheet/i', $html); $kb = (int)round(strlen($html) / 1024);
    if ($psi === null && ($nsc > 40 || $kb > 300))
        $p[] = array($kto . ' ładuje aż ' . $nsc . ' skryptów', '- ' . $kto . ' przy otwarciu ładuje aż ' . $nsc . ' skryptów i ' . $ncss . ' plików stylów (' . $kb . ' KB samego kodu strony), przez co na telefonie startuje wolno,', 0);
    $nimg = preg_match_all('/<img/i', $body); $nlazy = preg_match_all('/loading=["\']?lazy|data-src|lazyload/i', $body);
    if ($nimg >= 8 && $nlazy === 0) $p[] = array('wszystkie zdjęcia ładują się od razu', '- wszystkie zdjęcia (' . $nimg . ') ładują się od razu, nawet te na dole, co spowalnia otwieranie na telefonie,', 0);
    $title = preg_match('/<title[^>]*>([^<]*)/i', $html, $mt) ? trim(html_entity_decode($mt[1], ENT_QUOTES, 'UTF-8')) : '';
    if ($title === '' || preg_match('/^(strona główna|home|start|główna|witamy|index)\b/iu', $title))
        $p[] = array('w Google strona wyświetla się jako „' . ($title ?: 'bez tytułu') . '”', '- w Google strona wyświetla się jako „' . ($title ?: 'bez tytułu') . '”, bez nazwy usługi i miasta, więc trudniej ją znaleźć,', 0);
    elseif (!preg_match('/<meta[^>]+name=["\']?description/i', $html))
        $p[] = array('strona nie ma opisu dla Google', '- strona nie ma opisu dla Google, więc w wynikach wyszukiwania wyświetla się przypadkowy fragment tekstu,', 0);
    if (!preg_match('/googletagmanager|gtag\(|google-analytics|fbq\(|connect\.facebook\.net|clarity\.ms/i', $html))
        $p[] = array('na stronie nie ma żadnej analityki', '- ' . $kto . ' nie ma żadnej analityki (Google Analytics ani piksela Meta), więc nie wiadomo, skąd przychodzą klienci i które reklamy się opłacają,', 0);
    if (preg_match('/(?:©|&copy;|copyright)\s*(?:\d{4}\s*[-–]\s*)?(20[0-2]\d)\b/iu', $html, $my) && (int)$my[1] <= (int)date('Y') - 3 && !preg_match('/(?:©|&copy;|copyright)[^<]{0,20}20[0-2]\d\s*[-–]\s*20[2-9]\d/iu', $html))
        $p[] = array('w stopce strony jest rok ' . $my[1], '- w stopce widnieje rok ' . $my[1] . ', więc ' . $kto . ' wygląda na dawno nieaktualizowan' . ($sklep ? 'y' : 'ą') . ',', 0);
    usort($p, function ($a, $b) { return $b[2] - $a[2]; });
    $sig = array('sklep' => $sklep, 'form' => (bool)$form_url, 'allegro' => (bool)preg_match('/allegro\.pl/i', $hk),
        'rezerwacje' => (bool)preg_match('/booksy|calendly|reservio|znanylekarz|versum|moment\.pl|rezerwuj online|zarezerwuj online|umów (się )?online|kalendarz rezerwacji/iu', $hk),
        'wizyty' => (bool)preg_match('/wizyt|termin|zabieg|gabinet|salon|serwis|naprawa|przegląd/iu', $txt));
    return array($p, $psi, round($t, 1), ag_ig_z_html($hk), '', $form_url, $captcha, $sig);
}
// dobra strona → jeden konkretny pomysł na automatyzację, dobrany do tego, co widać na stronie
function ag_pomysl($sig) {
    if (!empty($sig['sklep'])) return array('sklep: zamówienia, faktury i wysyłki same',
        'Widzę, że prowadzą Państwo sklep internetowy. W wielu sklepach sporo czasu schodzi na rzeczy, które powtarzają się przy każdym zamówieniu: faktura, etykieta kurierska, mail do klienta, aktualizacja stanów' . (!empty($sig['allegro']) ? ', a do tego osobno zamówienia z Allegro' : '') . '. To da się ustawić tak, żeby po opłaceniu zamówienia wszystko działo się samo, a Państwo tylko pakowali paczki.');
    if (!empty($sig['wizyty']) && empty($sig['rezerwacje'])) return array('zapisy online i przypomnienia SMS',
        'Widzę, że klienci umawiają się u Państwa telefonicznie albo przez formularz. Da się to ustawić tak, żeby klient sam wybierał wolny termin, a dzień wcześniej dostawał SMS z przypomnieniem. Mniej telefonów w trakcie pracy i mniej klientów, którzy zapominają o wizycie.');
    if (!empty($sig['rezerwacje'])) return array('stali klienci wracają sami',
        'Widzę, że mają Państwo zapisy online. Kolejnym krokiem bywa automatyczne przypomnienie stałym klientom o następnej wizycie i prośba o opinię w Google po usłudze. To zwykle najtańszy sposób na więcej powracających klientów i lepsze miejsce w Google.');
    return array('zapytania od razu na telefon, oferty same',
        'Zapytanie z formularza może od razu trafiać na Państwa telefon, a klient w tej samej minucie dostaje odpowiedź z najważniejszymi informacjami. Do tego oferty i faktury mogą przygotowywać się same. Kto odpowie pierwszy, ten zwykle dostaje zlecenie.');
}
function ag_problem_txt($p) {
    $l = array(); foreach ($p as $x) { if ($x[2] > 0 && count($l) < 2) $l[] = $x[1]; }
    $t = implode("\n", $l);
    return rtrim($t, ',') . '.';
}
function ag_strona_run($cfg, &$s, $t0, $budget) {
    $c = $cfg['strona'];
    $bid = ag_br_id(AG_BR_STRONA);
    if (!$bid) return 'brak branży';
    $done = 0; $zle = 0;
    while (microtime(true) - $t0 < $budget && ag_dzis($s, 'audyt') < 120) {
        // firmy ze strony i z mailem, z list w „Szukaj firm”, które jeszcze nie trafiły do mailingu
        $r = one("SELECT * FROM szukaj WHERE audyt=0 AND www<>'' AND email<>'' AND status='gotowe' AND eli=0 ORDER BY id DESC LIMIT 1");
        if (!$r) { if (!$done) return 'brak firm ze stroną do sprawdzenia – dodaj listę w „Szukaj firm”'; break; }
        q('UPDATE szukaj SET audyt=1 WHERE id=?', array($r['id']));
        $psi = !empty($c['psi']) && (microtime(true) - $t0) < $budget - 50;
        list($p, $sc, $sek, $ig, $err, $furl, $cap, $sig) = ag_audyt($r['www'], $psi);
        $s['dzis']['audyt']++; $done++;
        if ($ig) q('INSERT IGNORE INTO ag_ig(ig,nazwa,miasto,www,problem,created_at) VALUES(?,?,?,?,?,?)', array($ig, mb_substr($r['nazwa'], 0, 255), mb_substr($r['miasto'], 0, 120), mb_substr($r['www'], 0, 255), $p && $p[0][2] > 0 ? mb_substr($p[0][0], 0, 160) : '', now()));
        $waga = 0; foreach ($p as $x) $waga += $x[2];
        $status = $err ? 'blad' : ($waga >= max(1, (int)$c['min']) ? 'czeka' : (!empty($c['auto']) ? 'czeka_auto' : 'ok_strona'));
        $ptxt = $p ? ag_problem_txt($p) : $err;
        if ($status === 'czeka_auto') { $pm = ag_pomysl($sig); $p = array(array($pm[0], '', 0)); $ptxt = $pm[1]; }
        q('INSERT IGNORE INTO ag_audyt(szukaj_id,nazwa,email,telefon,osoba,miasto,www,problemy,problem_txt,wynik,psi,sekundy,status,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
            array($r['id'], $r['nazwa'], $r['email'], $r['telefon'], $r['osoba'], $r['miasto'], $r['www'], json_encode($p, JSON_UNESCAPED_UNICODE), $ptxt, $waga, $sc, $sek, $status, now()));
        if ($status === 'czeka') { $zle++; ag_log('strona', 'kontakt', $r['nazwa'] . ' – ' . dup_host($r['www']) . ': ' . implode(', ', array_map(function ($x) { return $x[0]; }, array_slice($p, 0, 3)))); }
    }
    if ($done) ag_log('strona', 'znaleziono', 'Sprawdzone strony: ' . $done . ', z wyraźnym problemem: ' . $zle);
    if (empty($c['reczny'])) {
        $limit = max(0, (int)$c['dziennie'] - ag_dzis($s, 'strona'));
        foreach (all("SELECT * FROM ag_audyt WHERE status IN('czeka','czeka_auto') ORDER BY status, wynik DESC, id LIMIT " . (int)$limit) as $a) ag_strona_dodaj($a, $s);
    }
    return 'sprawdzono ' . $done;
}
function ag_strona_dodaj($a, &$s) {
    $auto = $a['status'] === 'czeka_auto';
    $bid = ag_br_id($auto ? AG_BR_AUTO : AG_BR_STRONA);
    list($fid) = ag_do_firm(array('nazwa' => $a['nazwa'], 'email' => $a['email'], 'telefon' => $a['telefon'], 'miasto' => $a['miasto'], 'osoba' => $a['osoba'], 'www' => $a['www'],
        'problem' => $a['problem_txt'], 'wlasny' => $a['wlasny'], 'notatka' => 'Agent Eli – ' . ($auto ? 'dobra strona, pomysł na automatyzację: ' : 'słaba strona: ') . str_replace("\n", ' ', $a['problem_txt']), 'zrodlo' => 'audyt'), $bid);
    q('UPDATE ag_audyt SET status=?, firma_id=? WHERE id=?', array($fid ? 'dodano' : 'juz_jest', $fid ?: null, $a['id']));
    q("UPDATE szukaj SET status=IF(?,'dodano','juz_jest') WHERE id=?", array($fid ? 1 : 0, $a['szukaj_id']));
    if ($fid) { ag_dzis($s, 'strona'); $s['dzis']['strona']++; ag_log('strona', 'dodano', $a['nazwa'] . ' – ' . $a['email']); }
    return $fid;
}

// ---------- ZADANIE 4: Instagram (lista do ręcznego wysłania) ----------
function ag_ig_run($cfg, &$s, $t0, $budget) {
    $n = 0; $found = 0;
    while (microtime(true) - $t0 < $budget && $n < 25) {
        // strony firm bez maila (tu Instagram jest jedynym kontaktem) i reszta, której jeszcze nie sprawdzano
        $r = one("SELECT id,nazwa,miasto,www FROM szukaj WHERE ig_chk=0 AND www<>'' AND status IN('brak_maila','juz_jest','dodano') ORDER BY (status='brak_maila') DESC, id DESC LIMIT 1");
        if (!$r) break;
        q('UPDATE szukaj SET ig_chk=1 WHERE id=?', array($r['id']));
        $www = preg_match('#^https?://#i', $r['www']) ? $r['www'] : 'http://' . $r['www'];
        list($code, $html) = ag_fetch($www, 10);
        $n++;
        $ig = $code >= 200 && $code < 400 ? ag_ig_z_html($html) : '';
        if ($ig && q('INSERT IGNORE INTO ag_ig(ig,nazwa,miasto,www,created_at) VALUES(?,?,?,?,?)', array($ig, mb_substr($r['nazwa'], 0, 255), mb_substr($r['miasto'], 0, 120), mb_substr($r['www'], 0, 255), now()))->rowCount()) $found++;
    }
    if ($found) ag_log('ig', 'znaleziono', 'Instagram: ' . $found . ' nowych profili firm do napisania');
    return 'sprawdzono stron: ' . $n . ', profile: ' . $found;
}
function ag_ig_msg($cfg, $r) {
    $p = trim((string)$r['problem']);
    return strtr((string)$cfg['ig']['msg'], array('{imie}' => strtok(trim(setting('podpis_imie')) . ' ', ' '), '{firma}' => $r['nazwa'], '{miasto}' => $r['miasto'],
        '{www}' => dup_host($r['www']), '{problem_ig}' => $p !== '' ? ' i zauważyłem, że ' . $p : '', '{moj_telefon}' => my_tel()));
}

// ---------- ZADANIE 5: formularze kontaktowe (firmy bez maila) ----------
// Eli znajduje formularz i przygotowuje wiadomość z konkretnym problemem strony. Wysyłasz Ty (kopiuj → otwórz → wklej),
// bo formularze mają zabezpieczenia przed automatami (CAPTCHA), a automatyczne wysyłanie to spam.
function ag_form_run($cfg, &$s, $t0, $budget) {
    $n = 0; $found = 0;
    while (microtime(true) - $t0 < $budget && $n < 10) {
        $r = one("SELECT id,nazwa,miasto,www FROM szukaj WHERE form_chk=0 AND www<>'' AND status='brak_maila' ORDER BY id DESC LIMIT 1");
        if (!$r) break;
        q('UPDATE szukaj SET form_chk=1, ig_chk=1 WHERE id=?', array($r['id']));
        list($p, $sc, $sek, $ig, $err, $furl, $cap, $sig) = ag_audyt($r['www'], false);
        $n++;
        if ($ig) q('INSERT IGNORE INTO ag_ig(ig,nazwa,miasto,www,problem,created_at) VALUES(?,?,?,?,?,?)', array($ig, mb_substr($r['nazwa'], 0, 255), mb_substr($r['miasto'], 0, 120), mb_substr($r['www'], 0, 255), $p && $p[0][2] > 0 ? mb_substr($p[0][0], 0, 160) : '', now()));
        if ($err || !$furl || !$p || $p[0][2] < 2) continue; // bez formularza albo bez realnego problemu biznesowego – nie piszemy
        q('INSERT IGNORE INTO ag_form(szukaj_id,nazwa,miasto,www,url,problem,captcha,created_at) VALUES(?,?,?,?,?,?,?,?)', array($r['id'], mb_substr($r['nazwa'], 0, 255), mb_substr($r['miasto'], 0, 120), mb_substr($r['www'], 0, 255), mb_substr($furl, 0, 400), mb_substr($p[0][0], 0, 255), $cap, now()));
        $found++;
    }
    if ($found) ag_log('form', 'znaleziono', 'Formularze: ' . $found . ' firm bez maila, z formularzem i konkretnym problemem na stronie');
    return 'sprawdzono ' . $n . ', formularze: ' . $found;
}
function ag_form_msg($cfg, $r) {
    return strtr((string)$cfg['form']['msg'], array('{imie}' => strtok(trim(setting('podpis_imie')) . ' ', ' '), '{firma}' => $r['nazwa'], '{miasto}' => $r['miasto'],
        '{www}' => dup_host($r['www']), '{problem_krotko}' => $r['problem'], '{moj_telefon}' => my_tel(), '{moj_email}' => setting('podpis_email')));
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
        if (!empty($cfg['nowe']['on'])) $r[] = 'nowe firmy: ' . ag_nowe_run($cfg, $s, $t0, $budget * 0.3);
        if (!empty($cfg['praca']['on'])) $r[] = 'ogłoszenia: ' . ag_praca_run($cfg, $s, $t0, $budget * 0.5);
        if (!empty($cfg['strona']['on'])) $r[] = 'strony: ' . ag_strona_run($cfg, $s, $t0, $budget * 0.9);
        if (!empty($cfg['form']['on'])) $r[] = 'formularze: ' . ag_form_run($cfg, $s, $t0, $budget * 0.95);
        if (!empty($cfg['ig']['on'])) $r[] = 'instagram: ' . ag_ig_run($cfg, $s, $t0, $budget);
    } catch (Exception $e) { ag_log('', 'blad', $e->getMessage()); $r[] = 'błąd: ' . $e->getMessage(); }
    $s['ostatnio'] = now(); $s['wynik'] = implode(' | ', $r);
    ag_stan_save($s);
    val("SELECT RELEASE_LOCK('elok_agent')");
    // raz dziennie po 18:00 podsumowanie na telefon
    if ((int)date('G') >= 18 && setting('ag_raport') !== date('Y-m-d') && !empty($cfg['on'])) {
        setting_set('ag_raport', date('Y-m-d'));
        $d = $s['dzis'];
        $czeka = (int)val("SELECT COUNT(*) FROM ag_oferty WHERE status='gotowe'") + (int)val("SELECT COUNT(*) FROM ag_audyt WHERE status='czeka'") + (!empty($cfg['nowe']['reczny']) ? (int)val("SELECT COUNT(*) FROM szukaj WHERE eli=1 AND status='gotowe'") : 0);
        $ig = (int)val("SELECT COUNT(*) FROM ag_ig WHERE status='do_wyslania'");
        push('Agent Eli – dzisiaj', 'Do mailingu: nowe firmy ' . (int)$d['nowe'] . ', ogłoszenia ' . (int)$d['praca'] . ', słabe strony ' . (int)$d['strona'] . ($czeka ? ' · czeka na Ciebie: ' . $czeka : '') . ($ig ? ' · Instagram do napisania: ' . $ig : ''), BASE_URL . '#agent');
    }
    return $s['wynik'];
}

// ---------- API (akcje „ag_…”, wywoływane z api.php) ----------
function ag_bid_typ($typ) { return ag_br_id($typ === 'praca' ? AG_BR_PRACA : ($typ === 'strona' ? AG_BR_STRONA : ($typ === 'auto' ? AG_BR_AUTO : AG_BR_NOWE))); }
// wiersz „do zatwierdzenia” jako firma (do podstawienia zmiennych w mailu)
function ag_item($typ, $id) {
    if ($typ === 'praca') { $r = one('SELECT * FROM ag_oferty WHERE id=?', array((int)$id)); if (!$r) return null; return array($r, array('nazwa' => $r['firma'] ?: ($r['www'] ? dup_host($r['www']) : $r['konto']), 'miasto' => $r['miasto'], 'osoba' => '', 'www' => $r['www'], 'eli_tytul' => $r['stanowisko'], 'eli_problem' => $r['obowiazki'], 'zrodlo' => 'olx')); }
    if ($typ === 'strona' || $typ === 'auto') { $r = one('SELECT * FROM ag_audyt WHERE id=?', array((int)$id)); if (!$r) return null; return array($r, array('nazwa' => $r['nazwa'], 'miasto' => $r['miasto'], 'osoba' => $r['osoba'], 'www' => $r['www'], 'eli_problem' => $r['problem_txt'], 'zrodlo' => 'audyt')); }
    $r = one('SELECT * FROM szukaj WHERE id=? AND eli=1', array((int)$id)); if (!$r) return null;
    return array($r, array('nazwa' => $r['nazwa'], 'miasto' => $r['miasto'], 'osoba' => $r['osoba'], 'www' => $r['www'], 'zrodlo' => 'ceidg'));
}
function ag_tbl($typ) { return $typ === 'praca' ? 'ag_oferty' : ($typ === 'strona' || $typ === 'auto' ? 'ag_audyt' : 'szukaj'); }
function ag_tpl_txt($tpl) { $b = tpl_blocks($tpl); $o = array(); foreach ($b as $x) if (isset($x['text']) && in_array($x['t'], array('tekst', 'ramka'), true)) $o[] = $x['text']; return implode("\n\n", $o); }

function ag_api($a) {
    ag_migrate_if();
    $typ = in_array(g('typ'), array('nowe', 'praca', 'strona', 'auto'), true) ? g('typ') : 'nowe';
    if ($a === 'ag_stan') {
        $cfg = ag_cfg(); $s = ag_stan(); ag_dzis($s, 'nowe');
        $ids = array(ag_br_id(AG_BR_NOWE), ag_br_id(AG_BR_PRACA), ag_br_id(AG_BR_STRONA), ag_br_id(AG_BR_AUTO));
        $br = all('SELECT id,nazwa,aktywna,limit_dzienny FROM branze WHERE id IN(?,?,?,?) ORDER BY id', $ids);
        $mail = array();
        foreach ($ids as $b) $mail[$b] = array(
            'w_kolejce' => (int)val("SELECT COUNT(*) FROM firmy WHERE branza_id=? AND status='nowy'", array($b)),
            'wyslano' => (int)val("SELECT COUNT(*) FROM firmy WHERE branza_id=? AND wyslano_at IS NOT NULL", array($b)),
            'otwarte' => (int)val("SELECT COUNT(*) FROM firmy WHERE branza_id=? AND otwarcia>0", array($b)),
            'odpisali' => (int)val("SELECT COUNT(*) FROM firmy WHERE branza_id=? AND (odpisal_at IS NOT NULL OR zgloszenie_at IS NOT NULL)", array($b)),
        );
        $ig = all("SELECT * FROM ag_ig WHERE status='do_wyslania' ORDER BY id DESC LIMIT 60");
        $fm = all("SELECT * FROM ag_form WHERE status='do_wyslania' ORDER BY captcha, id DESC LIMIT 60");
        foreach ($fm as $k => $r) $fm[$k]['msg'] = ag_form_msg($cfg, $r);
        foreach ($ig as $k => $r) $ig[$k]['msg'] = ag_ig_msg($cfg, $r);
        $licz = array(
            'nowe_znalezione' => (int)val('SELECT COUNT(*) FROM szukaj WHERE eli=1'),
            'nowe_z_mailem' => (int)val("SELECT COUNT(*) FROM szukaj WHERE eli=1 AND status IN('gotowe','dodano')"),
            'nowe_szukam' => (int)val("SELECT COUNT(*) FROM szukaj WHERE eli=1 AND status IN('czeka','czeka_www','szukam')"),
            'praca_ogl' => (int)val('SELECT COUNT(*) FROM ag_oferty'),
            'praca_odrz' => (int)val("SELECT COUNT(*) FROM ag_oferty WHERE status='odrzucona'"),
            'praca_brak' => (int)val("SELECT COUNT(*) FROM ag_oferty WHERE status='brak_maila'"),
            'strona_spr' => (int)val('SELECT COUNT(*) FROM ag_audyt'),
            'strona_zle' => (int)val("SELECT COUNT(*) FROM ag_audyt WHERE status IN('czeka','dodano')"),
            'strona_pula' => (int)val("SELECT COUNT(*) FROM szukaj WHERE audyt=0 AND www<>'' AND email<>'' AND status='gotowe' AND eli=0"),
            'ig_wyslane' => (int)val("SELECT COUNT(*) FROM ag_ig WHERE status='wyslane'"),
            'ig_dzis' => (int)val("SELECT COUNT(*) FROM ag_ig WHERE status='wyslane' AND akcja_at>=?", array(date('Y-m-d 00:00:00'))),
        );
        $licz['form_wyslane'] = (int)val("SELECT COUNT(*) FROM ag_form WHERE status='wyslane'");
        out(array('cfg' => $cfg, 'stan' => $s, 'branze' => $br, 'mail' => $mail, 'licz' => $licz, 'ig' => $ig, 'form' => $fm,
            'czekaN' => all("SELECT id,nazwa,osoba,email,telefon,www,miasto,adres,eli_data,(wlasny IS NOT NULL AND wlasny<>'') AS zm FROM szukaj WHERE eli=1 AND status='gotowe' ORDER BY id DESC LIMIT 100"),
            'czekaP' => all("SELECT id,tytul,stanowisko,firma,konto,miasto,url,email,www,powod,ogl_at,(wlasny IS NOT NULL AND wlasny<>'') AS zm FROM ag_oferty WHERE status='gotowe' ORDER BY id DESC LIMIT 100"),
            'czekaS' => all("SELECT id,nazwa,email,miasto,www,problemy,problem_txt,wynik,psi,sekundy,(wlasny IS NOT NULL AND wlasny<>'') AS zm FROM ag_audyt WHERE status='czeka' ORDER BY wynik DESC, id DESC LIMIT 100"),
            'czekaA' => all("SELECT id,nazwa,email,miasto,www,problemy,problem_txt,wynik,psi,sekundy,(wlasny IS NOT NULL AND wlasny<>'') AS zm FROM ag_audyt WHERE status='czeka_auto' ORDER BY id DESC LIMIT 100"),
            'pol' => array(
                'ceidg' => ceidg_token() !== '', 'ceidg_debug' => setting('ceidg_debug'),
                'olx' => setting('ag_olx_last'),
                'cron' => setting('ostatni_cron'), 'auto' => setting('auto_on') === '1',
                'godz' => setting('godz_od', '8') . '–' . setting('godz_do', '11'), 'limit' => (int)setting('limit_dzienny', '25'),
                'push' => setting('ntfy_topic') !== '', 'psi_key' => setting('psi_key'), 'psi_blad' => setting('ag_psi_blad'),
            ),
            'log' => all('SELECT zad,typ,info,created_at FROM ag_log ORDER BY id DESC LIMIT 80')));
    }
    if ($a === 'ag_zapisz') {
        $c = ag_cfg();
        $c['on'] = g('on') ? 1 : 0;
        $n = (array)g('nowe'); $p = (array)g('praca'); $st = (array)g('strona'); $ig = (array)g('ig'); $fo = (array)g('form');
        $c['form']['on'] = !empty($fo['on']) ? 1 : 0;
        if (isset($fo['msg']) && trim($fo['msg']) !== '') $c['form']['msg'] = mb_substr(trim((string)$fo['msg']), 0, 1500);
        $num = function ($arr, $k, $def, $min, $max) { return max($min, min($max, (int)(isset($arr[$k]) ? $arr[$k] : $def))); };
        $c['nowe']['on'] = !empty($n['on']) ? 1 : 0;
        $c['nowe']['dni'] = $num($n, 'dni', 14, 1, 60);
        $c['nowe']['dziennie'] = $num($n, 'dziennie', 25, 0, 300);
        $c['nowe']['reczny'] = !empty($n['reczny']) ? 1 : 0;
        $c['nowe']['pkd'] = mb_substr(trim((string)(isset($n['pkd']) ? $n['pkd'] : '')), 0, 500);
        $woj = array(); foreach ((array)(isset($n['woj']) ? $n['woj'] : array()) as $w) if (in_array($w, noc_woj_all(), true)) $woj[] = $w;
        $c['nowe']['woj'] = $woj;
        $c['praca']['on'] = !empty($p['on']) ? 1 : 0;
        $c['praca']['dziennie'] = $num($p, 'dziennie', 15, 0, 300);
        $c['praca']['reczny'] = !empty($p['reczny']) ? 1 : 0;
        $c['praca']['frazy'] = mb_substr(trim((string)(isset($p['frazy']) ? $p['frazy'] : '')), 0, 1000);
        $c['strona']['on'] = !empty($st['on']) ? 1 : 0;
        $c['strona']['dziennie'] = $num($st, 'dziennie', 20, 0, 300);
        $c['strona']['reczny'] = !empty($st['reczny']) ? 1 : 0;
        $c['strona']['psi'] = !empty($st['psi']) ? 1 : 0;
        $c['strona']['auto'] = !empty($st['auto']) ? 1 : 0;
        if (isset($st['psi_key'])) setting_set('psi_key', trim(mb_substr((string)$st['psi_key'], 0, 80)));
        $c['ig']['on'] = !empty($ig['on']) ? 1 : 0;
        if (isset($ig['msg']) && trim($ig['msg']) !== '') $c['ig']['msg'] = mb_substr(trim((string)$ig['msg']), 0, 1000);
        ag_cfg_save($c);
        $s = ag_stan(); unset($s['nowe']); ag_stan_save($s); // nowe ustawienia → nowy przebieg
        ag_log('', 'info', $c['on'] ? 'Zmieniono ustawienia agenta' : 'Agent wyłączony');
        out(array());
    }
    if ($a === 'ag_uruchom') { @set_time_limit(60); out(array('wynik' => ag_run(24, true))); }
    if ($a === 'ag_zatwierdz' || $a === 'ag_odrzuc') {
        $ids = array_map('intval', (array)g('ids'));
        if (!$ids) fail('Nic nie zaznaczono');
        $s = ag_stan(); ag_dzis($s, 'nowe'); $n = 0;
        foreach ($ids as $id) {
            if ($typ === 'praca') {
                $r = one("SELECT * FROM ag_oferty WHERE id=? AND status='gotowe'", array($id)); if (!$r) continue;
                if ($a === 'ag_odrzuc') { q("UPDATE ag_oferty SET status='odrzucona', powod='odrzucone ręcznie' WHERE id=?", array($id)); $n++; continue; }
                if (ag_praca_dodaj($r, $s)) $n++;
            } elseif ($typ === 'strona' || $typ === 'auto') {
                $r = one("SELECT * FROM ag_audyt WHERE id=? AND status=?", array($id, $typ === 'auto' ? 'czeka_auto' : 'czeka')); if (!$r) continue;
                if ($a === 'ag_odrzuc') { q("UPDATE ag_audyt SET status='odrzucona' WHERE id=?", array($id)); $n++; continue; }
                if (ag_strona_dodaj($r, $s)) $n++;
            } else {
                $r = one("SELECT * FROM szukaj WHERE id=? AND eli=1 AND status='gotowe'", array($id)); if (!$r) continue;
                if ($a === 'ag_odrzuc') { q("UPDATE szukaj SET status='ukryty' WHERE id=?", array($id)); $n++; continue; }
                list($fid) = ag_do_firm(array('nazwa' => $r['nazwa'], 'email' => $r['email'], 'telefon' => $r['telefon'], 'miasto' => $r['miasto'], 'osoba' => $r['osoba'], 'www' => $r['www'],
                    'notatka' => 'Agent Eli – nowa firma (CEIDG). ' . $r['adres'] . ($r['nip'] ? ' · NIP ' . $r['nip'] : ''), 'zrodlo' => 'ceidg', 'wlasny' => $r['wlasny']), ag_br_id(AG_BR_NOWE));
                q('UPDATE szukaj SET status=? WHERE id=?', array($fid ? 'dodano' : 'juz_jest', $id));
                if ($fid) { $n++; $s['dzis']['nowe']++; ag_log('nowe', 'dodano', $r['nazwa'] . ' – ' . $r['email'] . ' (zatwierdzone)'); }
            }
        }
        ag_stan_save($s);
        out(array('n' => $n));
    }
    // mail dla jednej firmy z listy: odczyt (gotowy tekst) i zapis własnej wersji
    if ($a === 'ag_mail_get') {
        $it = ag_item($typ, (int)g('id')); if (!$it) fail('Nie ma takiej pozycji');
        list($r, $firm) = $it;
        $w = !empty($r['wlasny']) ? json_decode($r['wlasny'], true) : null;
        if (is_array($w)) out(array('temat' => $w['temat'], 'tekst' => $w['tekst'], 'wlasny' => true));
        $tpl = tpl_for(ag_bid_typ($typ), 'pierwszy'); $v = firm_vars($firm);
        $pp = ag_parts($tpl);
        out(array('temat' => apply_vars($tpl['temat'], $v), 'tekst' => apply_vars($pp['tekst'], $v), 'wlasny' => false));
    }
    if ($a === 'ag_mail_zapisz') {
        $id = (int)g('id'); if (!ag_item($typ, $id)) fail('Nie ma takiej pozycji');
        $w = g('reset') ? null : json_encode(array('temat' => gs('temat', 255), 'tekst' => mb_substr(trim((string)g('tekst')), 0, 8000)), JSON_UNESCAPED_UNICODE);
        q('UPDATE ' . ag_tbl($typ) . ' SET wlasny=? WHERE id=?', array($w, $id));
        out(array());
    }
    // szablony maili Eli (pierwszy, przypomnienie, ostatnie przypomnienie) – prosta edycja tekstu
    if ($a === 'ag_tpl_get') {
        $bid = ag_bid_typ($typ); $o = array();
        foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2') as $t) { $tpl = tpl_for($bid, $t); $o[$t] = array_merge(array('temat' => $tpl['temat']), ag_parts($tpl)); }
        out(array('tpl' => $o, 'bid' => $bid));
    }
    if ($a === 'ag_tpl_zapisz') {
        $bid = ag_bid_typ($typ); $t = (array)g('tpl');
        foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2') as $k) {
            if (!isset($t[$k]['temat'], $t[$k]['tekst']) || trim($t[$k]['tekst']) === '') continue;
            $x = $t[$k]; $f = function ($k2, $max) use ($x) { return isset($x[$k2]) ? mb_substr(trim((string)$x[$k2]), 0, $max) : ''; };
            save_template($bid, $k, ag_build($f('temat', 255), $f('tekst', 8000), $f('btn', 120), $f('sub', 255), $f('po', 2000)));
        }
        ag_log('', 'info', 'Zmieniono maile: ' . ($typ === 'praca' ? 'szukają pracownika' : ($typ === 'strona' ? 'słaba strona' : ($typ === 'auto' ? 'dobra strona – automatyzacje' : 'nowe firmy'))));
        out(array());
    }
    if ($a === 'ag_tpl_reset') {
        $sets = ag_sets(); $n = $typ === 'praca' ? AG_BR_PRACA : ($typ === 'strona' ? AG_BR_STRONA : ($typ === 'auto' ? AG_BR_AUTO : AG_BR_NOWE));
        foreach (array('pierwszy', 'przypomnienie', 'przypomnienie2') as $k) save_template(ag_bid_typ($typ), $k, $sets[$n][$k]);
        out(array());
    }
    if ($a === 'ag_ig') {
        $id = (int)g('id'); $st = g('akcja') === 'pomin' ? 'pominiete' : 'wyslane';
        q('UPDATE ag_ig SET status=?, akcja_at=? WHERE id=?', array($st, now(), $id));
        if ($st === 'wyslane') { $r = one('SELECT nazwa,ig FROM ag_ig WHERE id=?', array($id)); if ($r) ag_log('ig', 'dodano', 'Instagram: napisano do @' . $r['ig'] . ' (' . $r['nazwa'] . ')'); }
        out(array());
    }
    if ($a === 'ag_form') {
        $id = (int)g('id'); $st = g('akcja') === 'pomin' ? 'pominiete' : 'wyslane';
        q('UPDATE ag_form SET status=?, akcja_at=? WHERE id=?', array($st, now(), $id));
        if ($st === 'wyslane') { $r = one('SELECT nazwa,www FROM ag_form WHERE id=?', array($id)); if ($r) ag_log('form', 'dodano', 'Formularz: napisano do ' . $r['nazwa'] . ' (' . dup_host($r['www']) . ')'); }
        out(array());
    }
    if ($a === 'ag_oferty') {
        $st = gs('status', 20);
        $w = $st !== '' ? ' WHERE status=?' : ''; $p = $st !== '' ? array($st) : array();
        out(array('rows' => all('SELECT id,tytul,stanowisko,firma,konto,miasto,url,email,www,powod,status,ogl_at FROM ag_oferty' . $w . ' ORDER BY id DESC LIMIT 200', $p)));
    }
    // ręczne sprawdzenie dowolnej strony (np. własnej) – wynik + jak wyglądałby mail
    if ($a === 'ag_sprawdz') {
        @set_time_limit(90);
        $www = trim(gs('www', 255)); if ($www === '') fail('Wpisz adres strony');
        list($p, $sc, $sek, $ig, $err, $furl, $cap, $sig) = ag_audyt($www, true);
        if ($err) fail('Eli nie mogła otworzyć strony: ' . $err);
        $waga = 0; foreach ($p as $x) $waga += $x[2];
        $slaba = $waga >= max(1, (int)ag_cfg()['strona']['min']); $pm = ag_pomysl($sig);
        $firm = array('nazwa' => dup_host($www), 'miasto' => '', 'osoba' => '', 'www' => $www, 'eli_problem' => $slaba ? ag_problem_txt($p) : $pm[1], 'zrodlo' => 'audyt', 'token' => str_repeat('0', 32));
        $tpl = tpl_for(ag_br_id($slaba ? AG_BR_STRONA : AG_BR_AUTO), 'pierwszy');
        list($subj, $html) = render_mail($tpl, $firm, array('btn_url' => BASE_URL . '/p.php?a=tak&t=podglad'));
        ag_log('strona', 'info', 'Ręczne sprawdzenie ' . dup_host($www) . ': ' . ($p ? implode(', ', array_map(function ($x) { return $x[0]; }, $p)) : 'bez problemów'));
        out(array('problemy' => $p, 'psi' => $sc, 'sekundy' => $sek, 'ig' => $ig, 'form' => $furl, 'captcha' => $cap, 'waga' => $waga, 'pisze' => $waga >= max(1, (int)ag_cfg()['strona']['min']), 'temat' => $subj, 'html' => $html, 'psi_blad' => $sc === null ? setting('ag_psi_blad') : '', 'pomysl' => $slaba ? '' : $pm[0]));
    }
    if ($a === 'ag_audyty') {
        out(array('rows' => all('SELECT id,nazwa,www,miasto,problem_txt,wynik,psi,sekundy,status,created_at FROM ag_audyt ORDER BY id DESC LIMIT 200')));
    }
    if ($a === 'ag_podglad') {
        $id = (int)g('id');
        $it = $id ? ag_item($typ, $id) : null;
        $firm = $it ? $it[1] : array('nazwa' => 'Przykładowa Firma', 'miasto' => 'Warszawa', 'osoba' => '', 'www' => 'https://przyklad.pl', 'eli_tytul' => 'pracownik biurowy',
            'eli_problem' => "- strona nie jest dopasowana do telefonu, na komórce trzeba ' . ($sklep ? 'go' : 'ją') . ' powiększać palcami,\n- nie ma formularza, przez który klient może od razu zostawić zapytanie.", 'zrodlo' => 'ceidg');
        $firm['token'] = str_repeat('0', 32);
        $tpl = tpl_for(ag_bid_typ($typ), 'pierwszy');
        if ($it && !empty($it[0]['wlasny'])) { $w = json_decode($it[0]['wlasny'], true); if (is_array($w)) $tpl = ag_tpl_with_text($tpl, $w['temat'], $w['tekst']); }
        list($subj, $html) = render_mail($tpl, $firm, array('btn_url' => BASE_URL . '/p.php?a=tak&t=podglad'));
        out(array('temat' => $subj, 'html' => $html));
    }
    fail('Nieznana akcja agenta');
}
