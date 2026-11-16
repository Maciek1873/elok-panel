<?php
// ELOK – moduł Umowy: klienci, szablony, umowy, podpis online (forma dokumentowa), PDF, kopie.
// Wspólne funkcje dla api.php, podpis.php, umowy_plik.php, umowy_drive.php i cron.php.
if (!defined('ELOK')) { http_response_code(403); exit; }

// ---------- TABELE ----------
function um_migrate() {
    db()->exec("CREATE TABLE IF NOT EXISTS um_klienci (
        id INT AUTO_INCREMENT PRIMARY KEY, nazwa VARCHAR(255) NOT NULL DEFAULT '', siedziba VARCHAR(120) NOT NULL DEFAULT '',
        adres VARCHAR(255) NOT NULL DEFAULT '', nip VARCHAR(20) NOT NULL DEFAULT '', regon VARCHAR(20) NOT NULL DEFAULT '',
        rejestr VARCHAR(120) NOT NULL DEFAULT '', r_imie VARCHAR(80) NOT NULL DEFAULT '', r_nazwisko VARCHAR(80) NOT NULL DEFAULT '',
        r_funkcja VARCHAR(120) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '', telefon VARCHAR(60) NOT NULL DEFAULT '',
        kontakt VARCHAR(160) NOT NULL DEFAULT '', notatki TEXT NULL, zgloszenie_id INT NULL, firma_id INT NULL,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, KEY nz (nazwa(40))
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS um_szablony (
        id INT AUTO_INCREMENT PRIMARY KEY, nazwa VARCHAR(160) NOT NULL DEFAULT '', typ VARCHAR(20) NOT NULL DEFAULT 'inne',
        prefiks VARCHAR(40) NOT NULL DEFAULT 'ELOK/U', tresc MEDIUMTEXT NULL, kolejnosc INT NOT NULL DEFAULT 0,
        created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS um_umowy (
        id INT AUTO_INCREMENT PRIMARY KEY, numer VARCHAR(60) NOT NULL DEFAULT '', prefiks VARCHAR(40) NOT NULL DEFAULT '', rok SMALLINT NOT NULL DEFAULT 0,
        nr INT NOT NULL DEFAULT 0, szablon_id INT NULL, szablon_nazwa VARCHAR(160) NOT NULL DEFAULT '', typ VARCHAR(20) NOT NULL DEFAULT 'inne',
        klient_id INT NOT NULL, powiazana_id INT NULL, tresc MEDIUMTEXT NULL, pola MEDIUMTEXT NULL, data_umowy DATE NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'szkic', wersja INT NOT NULL DEFAULT 0, html MEDIUMTEXT NULL, sha CHAR(64) NULL,
        klient_json TEXT NULL, firma_json TEXT NULL, metoda VARCHAR(20) NOT NULL DEFAULT '',
        token_hash CHAR(64) NULL, token_exp DATETIME NULL, kod_hash CHAR(64) NULL, kod_exp DATETIME NULL, kod_proby INT NOT NULL DEFAULT 0,
        kod_at DATETIME NULL, kod_dzis INT NOT NULL DEFAULT 0, kod_dzien DATE NULL,
        wyslana_at DATETIME NULL, otwarta_at DATETIME NULL, zawarta_at DATETIME NULL,
        pdf_plik VARCHAR(255) NOT NULL DEFAULT '', pdf_sha CHAR(64) NULL,
        anul_at DATETIME NULL, anul_powod VARCHAR(255) NOT NULL DEFAULT '', rozw_data DATE NULL, rozw_powod VARCHAR(255) NOT NULL DEFAULT '',
        wydruk_at DATETIME NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
        UNIQUE KEY num (numer), KEY kl (klient_id), KEY st (status), UNIQUE KEY tk (token_hash)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS um_log (
        id INT AUTO_INCREMENT PRIMARY KEY, umowa_id INT NOT NULL, typ VARCHAR(30) NOT NULL, info VARCHAR(500) NOT NULL DEFAULT '',
        ip VARCHAR(45) NOT NULL DEFAULT '', ua VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, KEY um (umowa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS um_akceptacje (
        id INT AUTO_INCREMENT PRIMARY KEY, umowa_id INT NOT NULL, wersja INT NOT NULL, strona VARCHAR(10) NOT NULL,
        imie VARCHAR(80) NOT NULL, nazwisko VARCHAR(80) NOT NULL, funkcja VARCHAR(120) NOT NULL DEFAULT '', firma VARCHAR(255) NOT NULL DEFAULT '',
        email VARCHAR(190) NOT NULL DEFAULT '', ip VARCHAR(45) NOT NULL DEFAULT '', ua VARCHAR(255) NOT NULL DEFAULT '', sha CHAR(64) NOT NULL,
        created_at DATETIME NOT NULL, KEY um (umowa_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS um_wersje (
        id INT AUTO_INCREMENT PRIMARY KEY, umowa_id INT NOT NULL, wersja INT NOT NULL, html MEDIUMTEXT NOT NULL, sha CHAR(64) NOT NULL,
        created_at DATETIME NOT NULL, UNIQUE KEY uw (umowa_id, wersja)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS um_kopie (
        id INT AUTO_INCREMENT PRIMARY KEY, umowa_id INT NULL, rodzaj VARCHAR(20) NOT NULL, plik VARCHAR(255) NOT NULL, nazwa VARCHAR(255) NOT NULL,
        drive_id VARCHAR(120) NOT NULL DEFAULT '', drive_err VARCHAR(255) NOT NULL DEFAULT '', drive_proby INT NOT NULL DEFAULT 0,
        mail_at DATETIME NULL, created_at DATETIME NOT NULL, KEY dr (drive_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $def = array(
        'um_f_nazwa' => 'Carbonmiledesign sp. z o.o.', 'um_f_siedziba' => '', 'um_f_adres' => '', 'um_f_krs' => '', 'um_f_nip' => '', 'um_f_regon' => '',
        'um_f_r_imie' => '', 'um_f_r_nazwisko' => '', 'um_f_r_funkcja' => 'Prezes Zarządu', 'um_f_email' => '',
        'um_link_dni' => '7', 'um_nadawca_email' => '', 'um_nadawca_nazwa' => 'ELOK – umowy', 'um_archiwum_email' => '',
        'um_gd_client_id' => '', 'um_gd_secret' => '', 'um_gd_refresh' => '', 'um_gd_folder' => '', 'um_gd_konto' => '',
    );
    foreach ($def as $k => $v) if (setting($k, null) === null) setting_set($k, $v);
    if (setting('um_secret') === '') setting_set('um_secret', bin2hex(random_bytes(32)));
    if (!(int)val('SELECT COUNT(*) FROM um_szablony')) um_seed_templates();
}

function um_seed_templates() {
    $d = __DIR__ . '/umowy_szablony/';
    $list = array(
        array('1-wdrozenie.md', 'Umowa o wykonanie i wdrożenie oprogramowania', 'wdrozenie', 'ELOK/W', 1),
        array('2-utrzymanie.md', 'Umowa o świadczenie usług utrzymania (abonament)', 'utrzymanie', 'ELOK/S', 2),
        array('3-protokol.md', 'Protokół odbioru', 'protokol', 'ELOK/P', 3),
    );
    foreach ($list as $t) {
        if (!is_file($d . $t[0])) continue;
        q('INSERT INTO um_szablony(nazwa,typ,prefiks,tresc,kolejnosc,created_at,updated_at) VALUES(?,?,?,?,?,?,?)',
            array($t[1], $t[2], $t[3], file_get_contents($d . $t[0]), $t[4], now(), now()));
    }
}

// ---------- POMOCNICZE ----------
$GLOBALS['UM_STATUSY'] = array('szkic' => 'Szkic', 'wyslana' => 'Wysłana do podpisu', 'zawarta' => 'Zawarta', 'anulowana' => 'Anulowana', 'rozwiazana' => 'Rozwiązana');
$GLOBALS['UM_TYPY'] = array('wdrozenie' => 'Wdrożenie', 'utrzymanie' => 'Utrzymanie (abonament)', 'protokol' => 'Protokół odbioru', 'inne' => 'Inna umowa');

function um_ip() { return isset($_SERVER['REMOTE_ADDR']) ? substr((string)$_SERVER['REMOTE_ADDR'], 0, 45) : ''; }
function um_ua() { return isset($_SERVER['HTTP_USER_AGENT']) ? mb_substr((string)$_SERVER['HTTP_USER_AGENT'], 0, 255) : ''; }
function um_log($uid, $typ, $info = '') {
    q('INSERT INTO um_log(umowa_id,typ,info,ip,ua,created_at) VALUES(?,?,?,?,?,?)', array((int)$uid, $typ, mb_substr((string)$info, 0, 500), um_ip(), um_ua(), now()));
}
function um_get_row($id) { return one('SELECT * FROM um_umowy WHERE id=?', array((int)$id)); }
function um_klient($id) { return one('SELECT * FROM um_klienci WHERE id=?', array((int)$id)); }
function um_pola($u) { $p = json_decode((string)$u['pola'], true); return is_array($p) ? $p : array(); }
function um_data_pl($ymd) { if (!$ymd) return ''; $t = strtotime($ymd); return $t ? date('d.m.Y', $t) . ' r.' : ''; }
function um_dt_pl($dt) { if (!$dt) return ''; $t = strtotime($dt); return $t ? date('d.m.Y, H:i:s', $t) : ''; }
function um_num($s) { $s = str_replace(array(' ', "\xc2\xa0"), '', (string)$s); $s = str_replace(',', '.', $s); return is_numeric($s) ? (float)$s : 0.0; }
function um_dot($s) { $s = (string)$s; return $s . (substr(rtrim(strip_tags($s)), -1) === '.' ? '' : '.'); }
function um_kwota($f) { return number_format((float)$f, 2, ',', ' '); }
function um_mask_email($e) {
    $p = explode('@', (string)$e); if (count($p) !== 2) return $e;
    $n = $p[0]; $m = mb_strlen($n) <= 2 ? mb_substr($n, 0, 1) . '*' : mb_substr($n, 0, 2) . str_repeat('*', max(1, mb_strlen($n) - 2));
    return $m . '@' . $p[1];
}

// katalog na pliki umów – poza katalogiem strony, wspólny dla panelu i wersji testowej
function um_dir($sub = '') {
    static $base = null;
    if ($base === null) {
        $cands = array(dirname(__DIR__) . '/elok-umowy-dane', __DIR__ . '/umowy_dane');
        foreach ($cands as $c) {
            if (!is_dir($c)) @mkdir($c, 0750, true);
            if (is_dir($c) && is_writable($c)) { $base = $c; break; }
        }
        if ($base === null) throw new Exception('Brak zapisu do katalogu na umowy (elok-umowy-dane).');
        if (!is_file($base . '/.htaccess')) @file_put_contents($base . '/.htaccess', "Require all denied\nDeny from all\n");
        if (!is_file($base . '/index.html')) @file_put_contents($base . '/index.html', '');
    }
    $d = $base . ($sub !== '' ? '/' . trim($sub, '/') : '');
    if (!is_dir($d)) @mkdir($d, 0750, true);
    return $d;
}

// ---------- DANE STRON ----------
function um_firma() {
    $r = trim(setting('um_f_r_imie') . ' ' . setting('um_f_r_nazwisko'));
    $fn = trim(setting('um_f_r_funkcja'));
    return array(
        'nazwa' => setting('um_f_nazwa'), 'siedziba' => setting('um_f_siedziba'), 'adres' => setting('um_f_adres'),
        'krs' => setting('um_f_krs'), 'nip' => setting('um_f_nip'), 'regon' => setting('um_f_regon'),
        'reprezentant' => $r . ($r !== '' && $fn !== '' ? ' – ' . $fn : ''), 'r_imie' => setting('um_f_r_imie'), 'r_nazwisko' => setting('um_f_r_nazwisko'),
        'r_funkcja' => $fn, 'email' => setting('um_f_email'),
    );
}
function um_klient_vals($k) {
    if (!$k) return array();
    $r = trim($k['r_imie'] . ' ' . $k['r_nazwisko']);
    $kontakt = trim((string)$k['kontakt']);
    $ktxt = $kontakt !== '' ? $kontakt : $r;
    $extra = array();
    if (trim($k['telefon']) !== '') $extra[] = 'tel. ' . trim($k['telefon']);
    if (trim($k['email']) !== '') $extra[] = 'e-mail ' . trim($k['email']);
    if ($extra) $ktxt = ($ktxt !== '' ? $ktxt . ', ' : '') . implode(', ', $extra);
    return array(
        'nazwa' => $k['nazwa'], 'siedziba' => $k['siedziba'], 'adres' => $k['adres'], 'rejestr' => $k['rejestr'], 'nip' => $k['nip'],
        'regon' => $k['regon'], 'reprezentant' => $r . ($r !== '' && trim($k['r_funkcja']) !== '' ? ' – ' . trim($k['r_funkcja']) : ''),
        'email' => $k['email'], 'kontakt' => $ktxt,
    );
}

// ---------- POLA W SZABLONIE ----------
define('UM_FIELD_RE', '/\{\{\s*([A-Za-z0-9_.\-]+)\s*(?:\|([^|}]*))?(?:\|([^}]*))?\}\}/u');
function um_builtin($key) { return (bool)preg_match('/^(firma|klient)\./', $key) || $key === 'umowa.numer' || $key === 'umowa.data'; }
function um_fields($tresc) {
    $out = array();
    if (preg_match_all(UM_FIELD_RE, (string)$tresc, $m, PREG_SET_ORDER)) {
        foreach ($m as $x) {
            $k = $x[1];
            if (um_builtin($k) || isset($out[$k])) continue;
            $out[$k] = array('key' => $k, 'label' => isset($x[2]) && trim($x[2]) !== '' ? trim($x[2]) : $k, 'def' => isset($x[3]) ? trim($x[3]) : '');
        }
    }
    return array_values($out);
}
function um_builtin_used($tresc) {
    $out = array();
    if (preg_match_all(UM_FIELD_RE, (string)$tresc, $m)) foreach ($m[1] as $k) if (um_builtin($k)) $out[$k] = 1;
    return array_keys($out);
}

// wartości wszystkich pól dla umowy (szkic: dane bieżące; po wysłaniu i tak liczy się zamrożony HTML)
function um_values($u, $klient = null, $firma = null) {
    if ($firma === null) $firma = !empty($u['firma_json']) && $u['status'] !== 'szkic' ? json_decode($u['firma_json'], true) : um_firma();
    if ($klient === null) $klient = !empty($u['klient_json']) && $u['status'] !== 'szkic' ? json_decode($u['klient_json'], true) : um_klient_vals(um_klient($u['klient_id']));
    $v = array();
    foreach ((array)$firma as $k => $x) $v['firma.' . $k] = (string)$x;
    foreach ((array)$klient as $k => $x) $v['klient.' . $k] = (string)$x;
    $v['umowa.numer'] = (string)$u['numer'];
    $v['umowa.data'] = um_data_pl($u['data_umowy']);
    $p = um_pola($u);
    foreach (um_fields($u['tresc']) as $f) {
        $k = $f['key'];
        if (array_key_exists($k, $p)) $v[$k] = (string)$p[$k];
        elseif ($k === 'umowa.powiazana' && !empty($u['powiazana_id'])) $v[$k] = (string)val('SELECT numer FROM um_umowy WHERE id=?', array((int)$u['powiazana_id']));
        else $v[$k] = $f['def'];
    }
    return $v;
}

// ---------- MARKDOWN → HTML ----------
// $mode: 'draft' (pola podświetlone, puste pokazane jako [Etykieta]) albo 'final' (czysty tekst)
function um_inline($s) {
    $s = h($s);
    $s = preg_replace('/\*\*(.+?)\*\*/u', '<strong>$1</strong>', $s);
    return $s;
}
function um_render($tresc, $vals, $mode = 'final') {
    $tokens = array();
    $txt = preg_replace_callback(UM_FIELD_RE, function ($m) use (&$tokens) {
        $tokens[] = array('key' => $m[1], 'label' => isset($m[2]) && trim($m[2]) !== '' ? trim($m[2]) : $m[1], 'def' => isset($m[3]) ? trim($m[3]) : '');
        return "\x01" . (count($tokens) - 1) . "\x02";
    }, str_replace("\r", '', (string)$tresc));
    $lines = explode("\n", $txt);
    $html = ''; $para = array(); $i = 0; $n = count($lines);
    $flush = function () use (&$para, &$html) {
        if ($para) { $html .= '<p>' . um_inline(implode(' ', $para)) . "</p>\n"; $para = array(); }
    };
    while ($i < $n) {
        $ln = rtrim($lines[$i]);
        $t = trim($ln);
        if ($t === '') { $flush(); $i++; continue; }
        if (preg_match('/^-{3,}$/', $t)) { $flush(); $html .= "<hr>\n"; $i++; continue; }
        if (preg_match('/^(#{1,4})\s+(.*)$/', $t, $m)) {
            $flush(); $lv = min(4, strlen($m[1]) + 0); $tag = $lv <= 2 ? 'h2' : ($lv === 3 ? 'h3' : 'h4');
            $html .= '<' . $tag . '>' . um_inline($m[2]) . '</' . $tag . ">\n"; $i++; continue;
        }
        if ($t[0] === '|') {
            $flush(); $rows = array();
            while ($i < $n && strlen(trim($lines[$i])) && trim($lines[$i])[0] === '|') { $rows[] = trim($lines[$i]); $i++; }
            $html .= um_table($rows); continue;
        }
        if (preg_match('/^(\s*)(\d+[.)]|[-*])\s+/', $ln)) {
            $flush(); $block = array();
            while ($i < $n) {
                $l2 = rtrim($lines[$i]);
                if (trim($l2) === '') { // pusta linia kończy listę, chyba że dalej jest kolejny punkt
                    if ($i + 1 < $n && preg_match('/^(\s*)(\d+[.)]|[-*])\s+/', $lines[$i + 1])) { $i++; continue; }
                    break;
                }
                if (preg_match('/^(\s*)(\d+[.)]|[-*])\s+(.*)$/', $l2, $mm)) $block[] = array(strlen(str_replace("\t", '    ', $mm[1])), ctype_digit(rtrim($mm[2], '.)')) ? 'ol' : 'ul', $mm[3]);
                elseif ($block && preg_match('/^\s+\S/', $l2)) $block[count($block) - 1][2] .= ' ' . trim($l2); // ciąg dalszy punktu
                else break;
                $i++;
            }
            $html .= um_list($block); continue;
        }
        $para[] = $t; $i++;
    }
    $flush();
    // podstawienie pól
    $html = preg_replace_callback("/\x01(\d+)\x02/", function ($m) use ($tokens, $vals, $mode) {
        $tk = $tokens[(int)$m[1]];
        $v = array_key_exists($tk['key'], $vals) ? (string)$vals[$tk['key']] : $tk['def'];
        if ($mode === 'draft') {
            if (trim($v) === '') return '<span class="um-f um-empty" data-k="' . h($tk['key']) . '">[' . h($tk['label']) . ']</span>';
            return '<span class="um-f" data-k="' . h($tk['key']) . '">' . nl2br(h($v), false) . '</span>';
        }
        return trim($v) === '' ? '…………………' : nl2br(h($v), false);
    }, $html);
    return $html;
}
function um_table($rows) {
    $cells = function ($r) { $r = trim($r); $r = trim($r, '|'); return array_map('trim', explode('|', $r)); };
    $head = $cells($rows[0]); $start = 1;
    if (isset($rows[1]) && preg_match('/^\|?\s*:?-{2,}/', $rows[1])) $start = 2; else { $head = null; $start = 0; }
    $h = '<table class="um-t">';
    if ($head) { $h .= '<thead><tr>'; foreach ($head as $c) $h .= '<th>' . um_inline($c) . '</th>'; $h .= '</tr></thead>'; }
    $h .= '<tbody>';
    for ($i = $start; $i < count($rows); $i++) { $h .= '<tr>'; foreach ($cells($rows[$i]) as $c) $h .= '<td>' . um_inline($c) . '</td>'; $h .= '</tr>'; }
    return $h . "</tbody></table>\n";
}
function um_list($items) {
    if (!$items) return '';
    $base = $items[0][0]; $out = ''; $stack = array();
    foreach ($items as $it) {
        list($ind, $tag, $txt) = $it;
        $d = $ind - $base; $lvl = $d <= 0 ? 0 : max(1, (int)round($d / 4));
        if ($lvl > count($stack)) $lvl = count($stack); // nie przeskakuj poziomów
        while (count($stack) > $lvl + 1) { $out .= '</li></' . array_pop($stack) . '>'; }
        if (count($stack) === $lvl + 1) $out .= '</li>';
        while (count($stack) < $lvl + 1) { $out .= '<' . $tag . '>'; $stack[] = $tag; }
        $out .= '<li>' . um_inline($txt);
    }
    while ($stack) $out .= '</li></' . array_pop($stack) . '>';
    return $out . "\n";
}

// treść umowy jako HTML (szkic albo wersja ostateczna)
function um_body($u, $mode = 'final') { return um_render($u['tresc'], um_values($u), $mode); }

// ---------- NUMERACJA ----------
function um_next_number($prefiks, $rok) {
    $nr = (int)val('SELECT COALESCE(MAX(nr),0) FROM um_umowy WHERE prefiks=? AND rok=?', array($prefiks, $rok)) + 1;
    return array($nr, $prefiks . '/' . sprintf('%03d', $nr) . '/' . $rok);
}
function um_create($klient_id, $sz, $powiazana_id = null, $pola = array(), $tresc = null) {
    $rok = (int)date('Y');
    for ($try = 0; $try < 5; $try++) {
        list($nr, $numer) = um_next_number($sz['prefiks'], $rok);
        try {
            q('INSERT INTO um_umowy(numer,prefiks,rok,nr,szablon_id,szablon_nazwa,typ,klient_id,powiazana_id,tresc,pola,data_umowy,status,created_at,updated_at)
               VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)', array($numer, $sz['prefiks'], $rok, $nr, $sz['id'], $sz['nazwa'], $sz['typ'], (int)$klient_id,
                $powiazana_id ? (int)$powiazana_id : null, $tresc !== null ? $tresc : $sz['tresc'], json_encode($pola, JSON_UNESCAPED_UNICODE), date('Y-m-d'), 'szkic', now(), now()));
            return (int)db()->lastInsertId();
        } catch (PDOException $e) { if ($e->getCode() !== '23000') throw $e; }
    }
    throw new Exception('Nie udało się nadać numeru umowy – spróbuj jeszcze raz.');
}

// ---------- DOKUMENT (podgląd / PDF) ----------
function um_css() {
    return '@page{margin:24mm 20mm 22mm 20mm}
body{font-family:"DejaVu Sans",sans-serif;font-size:9.4pt;line-height:1.42;color:#1b1a17}
.hdr{position:fixed;top:-15mm;left:0;right:0;height:9mm;border-bottom:0.6pt solid #d9d4c7;font-size:8pt;color:#7a766c}
.hdr .lg{font-size:13pt;font-weight:bold;color:#151515;letter-spacing:-0.3pt}.hdr .lg span{color:#B08A3E}
.hdr .nr{position:absolute;right:0;top:3mm}
h2{font-size:12.5pt;text-align:center;margin:0 0 4mm;line-height:1.3}
h3{font-size:10.4pt;margin:6mm 0 2mm;page-break-after:avoid}
h4{font-size:9.8pt;margin:4mm 0 1.5mm}
p{margin:0 0 2.6mm;text-align:justify}
ol,ul{margin:0 0 2.6mm;padding-left:6mm}ol ol,ol ul{margin:1mm 0 1mm}
li{margin:0 0 1.4mm;text-align:justify}
hr{border:0;border-top:0.6pt solid #d9d4c7;margin:6mm 0;page-break-after:always;visibility:hidden}
table.um-t{width:100%;border-collapse:collapse;margin:1.5mm 0 4mm;font-size:9pt}
table.um-t th,table.um-t td{border:0.6pt solid #cfc9bb;padding:1.8mm 2.4mm;text-align:left;vertical-align:top}
table.um-t th{background:#f3f0e8;font-weight:bold}
.karta{page-break-before:always}
.karta h2{text-align:left}
.karta table{width:100%;border-collapse:collapse;margin:3mm 0 5mm;font-size:8.8pt}
.karta td{border:0.6pt solid #cfc9bb;padding:1.8mm 2.4mm;vertical-align:top}
.karta td.k{width:34%;background:#f7f5ef;color:#4a4740}
.karta .sha{font-family:"DejaVu Sans",sans-serif;font-size:7.6pt;word-break:break-all}
.karta .note{font-size:8pt;color:#5e5a51}';
}
function um_doc($u, $body, $karta = '') {
    return '<!DOCTYPE html><html lang="pl"><head><meta charset="utf-8"><title>' . h($u['numer']) . '</title><style>' . um_css() . '</style></head><body>'
        . '<div class="hdr"><span class="lg">ELOK<span>.</span></span><span class="nr">' . h($u['numer']) . '</span></div>'
        . $body . $karta . '</body></html>';
}
function um_karta_html($u) {
    $rows = all('SELECT * FROM um_akceptacje WHERE umowa_id=? AND wersja=? ORDER BY id', array((int)$u['id'], (int)$u['wersja']));
    $h = '<div class="karta"><h2>Karta akceptacji umowy nr ' . h($u['numer']) . '</h2>'
        . '<p class="note">Umowa zawarta w formie dokumentowej (art. 77² Kodeksu cywilnego) przez akceptację treści w panelu ELOK, na zasadach opisanych w Zasadach zawarcia umowy online. Skrót SHA-256 potwierdza, że zaakceptowano dokładnie tę treść umowy.</p>';
    foreach ($rows as $a) {
        $h .= '<table><tr><td class="k" colspan="2" style="background:#efe9da;color:#151515;font-weight:bold">' . ($a['strona'] === 'elok' ? 'Wykonawca' : 'Zamawiający') . ': ' . h($a['firma']) . '</td></tr>'
            . '<tr><td class="k">Osoba akceptująca</td><td>' . h(trim($a['imie'] . ' ' . $a['nazwisko'])) . '</td></tr>'
            . '<tr><td class="k">Funkcja</td><td>' . h($a['funkcja']) . '</td></tr>'
            . '<tr><td class="k">Data i godzina akceptacji</td><td>' . h(um_dt_pl($a['created_at'])) . ' (czas polski)</td></tr>'
            . '<tr><td class="k">Adres IP</td><td>' . h($a['ip']) . '</td></tr>'
            . '<tr><td class="k">' . ($a['strona'] === 'elok' ? 'Adres e-mail' : 'Adres e-mail, na który wysłano kod') . '</td><td>' . h($a['email']) . '</td></tr>'
            . '<tr><td class="k">Skrót SHA-256 treści</td><td class="sha">' . h($a['sha']) . '</td></tr></table>';
    }
    $h .= '<p class="note">Wersja treści: ' . (int)$u['wersja'] . '. Treść umowy objęta skrótem to dokument HTML zapisany w panelu ELOK w chwili wysłania umowy do akceptacji.</p></div>';
    return $h;
}

// ---------- PDF ----------
function um_pdf_from_html($html, $numer) {
    require_once __DIR__ . '/vendor/autoload.php';
    $tmp = um_dir('tmp');
    $opt = new \Dompdf\Options();
    $opt->set('defaultFont', 'DejaVu Sans');
    $opt->set('isRemoteEnabled', false);
    $opt->set('isPhpEnabled', false);
    $opt->set('tempDir', $tmp);
    $opt->set('fontCache', $tmp);
    $opt->set('chroot', __DIR__);
    $pdf = new \Dompdf\Dompdf($opt);
    $pdf->loadHtml($html, 'UTF-8');
    $pdf->setPaper('A4', 'portrait');
    $pdf->render();
    $cv = $pdf->getCanvas();
    $fm = $pdf->getFontMetrics();
    $font = $fm->getFont('DejaVu Sans', 'normal');
    $txt = 'Umowa nr ' . $numer . '  ·  strona {PAGE_NUM} z {PAGE_COUNT}';
    $w = $cv->get_width(); $hgt = $cv->get_height();
    $cv->page_text(56.7, $hgt - 36, $txt, $font, 7.5, array(0.45, 0.43, 0.39));
    $cv->page_text($w - 56.7 - 70, $hgt - 36, 'elok-agency.pl', $font, 7.5, array(0.45, 0.43, 0.39));
    return $pdf->output();
}
// PDF bieżącej treści (szkic albo zamrożona wersja do podpisu, bez karty)
function um_pdf_tresc($u) {
    $body = $u['status'] === 'szkic' || empty($u['html']) ? um_body($u, 'final') : $u['html'];
    return um_pdf_from_html(um_doc($u, $body), $u['numer']);
}
// PDF ostateczny: zamrożona treść + karta akceptacji; zapisywany na dysku bez późniejszych zmian
function um_pdf_final_make($u) {
    $pdf = um_pdf_from_html(um_doc($u, $u['html'], um_karta_html($u)), $u['numer']);
    $rel = 'pliki/' . (int)$u['id'] . '/' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $u['numer']) . '_v' . (int)$u['wersja'] . '_zawarta.pdf';
    um_dir(dirname($rel));
    file_put_contents(um_dir() . '/' . $rel, $pdf);
    q('UPDATE um_umowy SET pdf_plik=?, pdf_sha=? WHERE id=?', array($rel, hash('sha256', $pdf), (int)$u['id']));
    return array($rel, $pdf);
}
function um_pdf_final($u) {
    if (!empty($u['pdf_plik']) && is_file(um_dir() . '/' . $u['pdf_plik'])) return file_get_contents(um_dir() . '/' . $u['pdf_plik']);
    if ($u['status'] === 'zawarta' && $u['metoda'] === 'online') { list(, $pdf) = um_pdf_final_make($u); return $pdf; }
    return null;
}
function um_pdf_name($u, $suffix = '') {
    $k = um_klient($u['klient_id']);
    $n = preg_replace('/[^A-Za-z0-9_-]+/', '_', $u['numer']) . ($k ? '_' . preg_replace('/[^A-Za-z0-9]+/', '_', iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', mb_substr($k['nazwa'], 0, 40))) : '');
    return trim($n, '_') . $suffix . '.pdf';
}

// ---------- MAILE ----------
function um_from() {
    $f = trim(setting('um_nadawca_email'));
    if ($f === '') $f = setting('nadawca_email');
    return array($f, setting('um_nadawca_nazwa') ?: 'ELOK');
}
function um_mail($to, $subject, $html, $text, $att = array(), $replyTo = '') {
    list($from, $fromName) = um_from();
    if (!$from || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $dom = substr(strrchr($from, '@'), 1);
    $reply = $replyTo ?: (setting('um_f_email') ?: $from);
    $alt = 'a' . token(); $mix = 'm' . token();
    $headers = array('From: ' . enc_header($fromName) . ' <' . $from . '>', 'Reply-To: ' . $reply, 'MIME-Version: 1.0',
        'Message-ID: <' . token() . '@' . $dom . '>', 'Date: ' . date('r'));
    $altPart = "--$alt\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$alt\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html)) . "--$alt--\r\n";
    if ($att) {
        $headers[] = 'Content-Type: multipart/mixed; boundary="' . $mix . '"';
        $msg = "--$mix\r\nContent-Type: multipart/alternative; boundary=\"$alt\"\r\n\r\n" . $altPart;
        foreach ($att as $a) {
            $nm = str_replace('"', '', $a[0]);
            $msg .= "--$mix\r\nContent-Type: " . $a[2] . '; name="' . $nm . "\"\r\nContent-Transfer-Encoding: base64\r\nContent-Disposition: attachment; filename=\"" . $nm . "\"\r\n\r\n" . chunk_split(base64_encode($a[1]));
        }
        $msg .= "--$mix--";
    } else {
        $headers[] = 'Content-Type: multipart/alternative; boundary="' . $alt . '"';
        $msg = $altPart;
    }
    if (defined('MAIL_DRY_RUN') && MAIL_DRY_RUN) {
        @file_put_contents(__DIR__ . '/mail_test.log', "TO: $to\nSUBJ: $subject\nATT: " . implode(',', array_map(function ($a) { return $a[0] . ' (' . strlen($a[1]) . ' B)'; }, $att)) . "\n$text\n=====\n", FILE_APPEND);
        return true;
    }
    return @mail($to, enc_header($subject), $msg, implode("\r\n", $headers), '-f' . $from);
}
// prosty, jasny mail w tabelach (Gmail)
function um_mail_html($title, $paras, $btnLabel = '', $btnUrl = '', $small = '') {
    $p = '';
    foreach ($paras as $x) $p .= '<p style="margin:0 0 14px;font-size:15px;line-height:1.6;color:#2b2925">' . $x . '</p>';
    $btn = $btnUrl ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:8px 0 6px"><tr><td style="background:#151515;border-radius:10px"><a href="' . h($btnUrl) . '" style="display:inline-block;padding:14px 24px;color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;font-family:Arial,sans-serif">' . h($btnLabel) . '</a></td></tr></table>' : '';
    return '<!DOCTYPE html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width"></head><body style="margin:0;background:#F5F4F0">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F5F4F0"><tr><td align="center" style="padding:28px 12px">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#ffffff;border:1px solid #E7E4DC;border-radius:16px">'
        . '<tr><td style="padding:26px 30px 6px;font-family:Arial,sans-serif"><div style="font-size:22px;font-weight:bold;color:#151515;letter-spacing:-0.5px">ELOK<span style="color:#C5A059">.</span></div></td></tr>'
        . '<tr><td style="padding:12px 30px 26px;font-family:Arial,sans-serif"><h1 style="margin:0 0 16px;font-size:20px;line-height:1.3;color:#151515">' . h($title) . '</h1>' . $p . $btn
        . ($small ? '<p style="margin:18px 0 0;font-size:12.5px;line-height:1.55;color:#8A867C">' . $small . '</p>' : '') . '</td></tr></table>'
        . '<div style="font-family:Arial,sans-serif;font-size:11.5px;color:#9A968C;margin-top:14px">' . h(setting('um_f_nazwa')) . ' · marka ELOK · elok-agency.pl</div>'
        . '</td></tr></table></body></html>';
}
function um_mail_text($title, $paras, $btnLabel = '', $btnUrl = '') {
    $t = $title . "\n\n";
    foreach ($paras as $x) $t .= trim(html_entity_decode(strip_tags($x), ENT_QUOTES, 'UTF-8')) . "\n\n";
    if ($btnUrl) $t .= $btnLabel . ': ' . $btnUrl . "\n";
    return $t . "\n--\nELOK · " . setting('um_f_nazwa');
}

// ---------- WYSŁANIE DO PODPISU ----------
function um_link($raw) { return BASE_URL . '/podpis.php?t=' . $raw; }
function um_new_token($uid) {
    $raw = bin2hex(random_bytes(24));
    $dni = max(1, min(60, (int)setting('um_link_dni', '7')));
    q('UPDATE um_umowy SET token_hash=?, token_exp=?, kod_hash=NULL, kod_exp=NULL, kod_proby=0 WHERE id=?', array(hash('sha256', $raw), date('Y-m-d H:i:s', time() + $dni * 86400), (int)$uid));
    return $raw;
}
function um_send_link_mail($u, $raw) {
    $k = json_decode((string)$u['klient_json'], true);
    $f = json_decode((string)$u['firma_json'], true);
    $to = isset($k['email']) ? $k['email'] : '';
    $exp = one('SELECT token_exp FROM um_umowy WHERE id=?', array((int)$u['id']));
    $title = 'Umowa do akceptacji: ' . $u['numer'];
    $paras = array(
        'Dzień dobry,',
        'przesyłamy do akceptacji dokument <b>' . h($u['szablon_nazwa']) . '</b> nr <b>' . h($u['numer']) . '</b> dla firmy ' . um_dot('<b>' . h(isset($k['nazwa']) ? $k['nazwa'] : '') . '</b>'),
        'Po kliknięciu przycisku wyświetli się pełna treść umowy z możliwością pobrania PDF. Akceptacja zajmuje minutę: wystarczy podać imię, nazwisko i funkcję, a na ten adres e-mail przyjdzie jednorazowy kod potwierdzający.',
    );
    $small = 'Link jest jednorazowy i ważny do ' . h(um_dt_pl($exp['token_exp'])) . '. W razie pytań do treści wystarczy odpisać na tę wiadomość.';
    $ok = um_mail($to, $title, um_mail_html($title, $paras, 'Przejdź do umowy', um_link($raw), $small), um_mail_text($title, $paras, 'Przejdź do umowy', um_link($raw)));
    um_log($u['id'], $ok ? 'link_wyslany' : 'blad_maila', ($ok ? 'Link do podpisu wysłany na ' : 'Nie udało się wysłać linku na ') . $to);
    return $ok;
}
function um_freeze_and_send($id, $elok) {
    $u = um_get_row($id);
    if (!$u) throw new Exception('Nie ma takiej umowy.');
    if ($u['status'] !== 'szkic') throw new Exception('Wysłać do podpisu można tylko szkic.');
    $k = um_klient($u['klient_id']);
    if (!$k) throw new Exception('Brak klienta.');
    if (!filter_var(trim($k['email']), FILTER_VALIDATE_EMAIL)) throw new Exception('Klient nie ma poprawnego adresu e-mail – uzupełnij go w karcie klienta.');
    $f = um_firma();
    foreach (array('nazwa', 'nip', 'email') as $x) if (trim($f[$x]) === '') throw new Exception('Uzupełnij dane firmy w Umowy → Ustawienia (brakuje: ' . $x . ').');
    $vals = um_values($u, um_klient_vals($k), $f);
    $miss = array();
    foreach (um_fields($u['tresc']) as $fl) if (trim($vals[$fl['key']]) === '') $miss[] = $fl['label'];
    foreach (um_builtin_used($u['tresc']) as $b) if (isset($vals[$b]) && trim($vals[$b]) === '') $miss[] = $b;
    if ($miss) throw new Exception('Uzupełnij puste pola: ' . implode(', ', array_slice($miss, 0, 6)) . (count($miss) > 6 ? '…' : ''));
    $html = um_render($u['tresc'], $vals, 'final');
    $sha = hash('sha256', $html);
    $wersja = (int)$u['wersja'] + 1;
    $kj = um_klient_vals($k);
    db()->beginTransaction();
    q('UPDATE um_umowy SET status=?, wersja=?, html=?, sha=?, klient_json=?, firma_json=?, wyslana_at=?, metoda=?, updated_at=? WHERE id=? AND status=?',
        array('wyslana', $wersja, $html, $sha, json_encode($kj, JSON_UNESCAPED_UNICODE), json_encode($f, JSON_UNESCAPED_UNICODE), now(), 'online', now(), (int)$id, 'szkic'));
    q('INSERT INTO um_wersje(umowa_id,wersja,html,sha,created_at) VALUES(?,?,?,?,?)', array((int)$id, $wersja, $html, $sha, now()));
    q('INSERT INTO um_akceptacje(umowa_id,wersja,strona,imie,nazwisko,funkcja,firma,email,ip,ua,sha,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
        array((int)$id, $wersja, 'elok', $elok['imie'], $elok['nazwisko'], $elok['funkcja'], $f['nazwa'], $f['email'], um_ip(), um_ua(), $sha, now()));
    um_log($id, 'zamrozona', 'Wersja ' . $wersja . ' zamrożona, SHA-256 ' . $sha);
    um_log($id, 'akcept_elok', 'Akceptacja Wykonawcy: ' . trim($elok['imie'] . ' ' . $elok['nazwisko']) . ', ' . $elok['funkcja']);
    db()->commit();
    $raw = um_new_token($id);
    um_send_link_mail(um_get_row($id), $raw);
    return $raw;
}

// ---------- PODPIS KLIENTA ----------
function um_by_token($raw, $allowZawarta = false) {
    if (!preg_match('/^[a-f0-9]{48}$/', (string)$raw)) return null;
    $u = one('SELECT * FROM um_umowy WHERE token_hash=?', array(hash('sha256', $raw)));
    if (!$u) return null;
    if ($u['status'] === 'wyslana' && strtotime($u['token_exp']) >= time()) return $u;
    if ($allowZawarta && $u['status'] === 'zawarta' && $u['metoda'] === 'online' && strtotime($u['zawarta_at']) > time() - 30 * 86400) return $u;
    return $u ? array('_expired' => 1, 'status' => $u['status'], 'numer' => $u['numer']) : null;
}
function um_code_hash($uid, $code) { return hash_hmac('sha256', (int)$uid . ':' . $code, setting('um_secret')); }
function um_send_code($u) {
    if (!empty($u['kod_at']) && time() - strtotime($u['kod_at']) < 45) throw new Exception('Kod został właśnie wysłany. Sprawdź skrzynkę (także SPAM) albo poczekaj chwilę.');
    $dzis = date('Y-m-d');
    $ile = $u['kod_dzien'] === $dzis ? (int)$u['kod_dzis'] : 0;
    if ($ile >= 8) throw new Exception('Wysłano już dziś zbyt wiele kodów. Spróbuj jutro albo odpisz na maila z umową.');
    $code = str_pad((string)random_int(0, 999999), 6, '0', STR_PAD_LEFT);
    $k = json_decode((string)$u['klient_json'], true);
    $to = isset($k['email']) ? $k['email'] : '';
    q('UPDATE um_umowy SET kod_hash=?, kod_exp=?, kod_proby=0, kod_at=?, kod_dzis=?, kod_dzien=? WHERE id=?',
        array(um_code_hash($u['id'], $code), date('Y-m-d H:i:s', time() + 15 * 60), now(), $ile + 1, $dzis, (int)$u['id']));
    $title = 'Kod do akceptacji umowy ' . $u['numer'];
    $paras = array('Kod potwierdzający akceptację umowy nr <b>' . h($u['numer']) . '</b>:',
        '<span style="display:inline-block;font-size:30px;letter-spacing:8px;font-weight:bold;color:#151515;background:#F5F4F0;border-radius:10px;padding:12px 18px">' . $code . '</span>',
        'Kod jest ważny 15 minut. Należy go wpisać na stronie umowy, aby ją zaakceptować.');
    $ok = um_mail($to, $title, um_mail_html($title, $paras, '', '', 'Jeśli nikt z Państwa firmy nie prosił o kod, prosimy zignorować tę wiadomość – bez kodu umowa nie zostanie zaakceptowana.'), um_mail_text($title, array_merge(array_slice($paras, 0, 1), array($code), array_slice($paras, 2))));
    um_log($u['id'], $ok ? 'kod_wyslany' : 'blad_maila', ($ok ? 'Kod wysłany na ' : 'Nie udało się wysłać kodu na ') . $to);
    if (!$ok) throw new Exception('Nie udało się wysłać kodu. Spróbuj ponownie za chwilę.');
    return um_mask_email($to);
}
function um_confirm($u, $code, $osoba) {
    if (empty($u['kod_hash']) || empty($u['kod_exp'])) throw new Exception('Najpierw wyślij kod.');
    if (strtotime($u['kod_exp']) < time()) throw new Exception('Kod wygasł. Wyślij nowy kod.');
    if ((int)$u['kod_proby'] >= 5) throw new Exception('Za dużo błędnych prób. Wyślij nowy kod.');
    $code = preg_replace('/\D/', '', (string)$code);
    if (!hash_equals($u['kod_hash'], um_code_hash($u['id'], $code))) {
        q('UPDATE um_umowy SET kod_proby=kod_proby+1 WHERE id=?', array((int)$u['id']));
        um_log($u['id'], 'kod_bledny', 'Błędny kod (próba ' . ((int)$u['kod_proby'] + 1) . ' z 5)');
        $left = 4 - (int)$u['kod_proby'];
        throw new Exception($left > 0 ? 'Nieprawidłowy kod. Zostało prób: ' . $left . '.' : 'Nieprawidłowy kod. Wyślij nowy kod.');
    }
    $k = json_decode((string)$u['klient_json'], true);
    db()->beginTransaction();
    $n = q('UPDATE um_umowy SET status=?, zawarta_at=?, kod_hash=NULL, kod_exp=NULL, updated_at=? WHERE id=? AND status=? AND wersja=?',
        array('zawarta', now(), now(), (int)$u['id'], 'wyslana', (int)$u['wersja']))->rowCount();
    if ($n !== 1) { db()->rollBack(); throw new Exception('Umowa nie czeka już na akceptację.'); }
    q('INSERT INTO um_akceptacje(umowa_id,wersja,strona,imie,nazwisko,funkcja,firma,email,ip,ua,sha,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
        array((int)$u['id'], (int)$u['wersja'], 'klient', $osoba['imie'], $osoba['nazwisko'], $osoba['funkcja'], isset($k['nazwa']) ? $k['nazwa'] : '', isset($k['email']) ? $k['email'] : '', um_ip(), um_ua(), $u['sha'], now()));
    um_log($u['id'], 'podpisana', 'Zaakceptowana przez ' . trim($osoba['imie'] . ' ' . $osoba['nazwisko']) . ' (' . $osoba['funkcja'] . '), oświadczenia: uprawniony do reprezentacji, zapoznał się i akceptuje');
    db()->commit();
    um_after_signed((int)$u['id']);
}
// po zawarciu: PDF z kartą, maile do obu stron, kopie
function um_after_signed($id) {
    $u = um_get_row($id);
    try { list($rel, $pdf) = um_pdf_final_make($u); }
    catch (Exception $e) { um_log($id, 'blad_pdf', 'PDF: ' . $e->getMessage()); return; }
    um_log($id, 'pdf', 'Utworzono PDF z kartą akceptacji, SHA-256 pliku ' . hash('sha256', $pdf));
    um_send_signed_mails(um_get_row($id), $pdf);
    um_kopia_dodaj($id, 'umowa', $rel, um_pdf_name($u));
}
function um_send_signed_mails($u, $pdf) {
    $k = json_decode((string)$u['klient_json'], true); $f = json_decode((string)$u['firma_json'], true);
    $att = array(array(um_pdf_name($u), $pdf, 'application/pdf'));
    $title = 'Umowa ' . $u['numer'] . ' została zawarta';
    $paras = array('Dzień dobry,', 'dziękujemy – umowa nr <b>' . h($u['numer']) . '</b> (' . h($u['szablon_nazwa']) . ') została zaakceptowana przez obie strony i jest zawarta.',
        'W załączniku jest plik PDF z pełną treścią umowy i kartą akceptacji. Prosimy zachować go w dokumentacji firmy.');
    $okK = um_mail(isset($k['email']) ? $k['email'] : '', $title, um_mail_html($title, $paras), um_mail_text($title, $paras), $att);
    um_log($u['id'], $okK ? 'pdf_wyslany' : 'blad_maila', ($okK ? 'PDF wysłany do Zamawiającego: ' : 'Nie wysłano PDF do Zamawiającego: ') . (isset($k['email']) ? $k['email'] : ''));
    $kn = isset($k['nazwa']) ? $k['nazwa'] : '';
    $t2 = 'Zawarta: ' . $u['numer'] . ' – ' . $kn;
    $p2 = array('Klient <b>' . h($kn) . '</b> zaakceptował umowę nr <b>' . h($u['numer']) . '</b>.', 'PDF z kartą akceptacji w załączniku. Umowa jest też w panelu.');
    $okF = um_mail(isset($f['email']) ? $f['email'] : '', $t2, um_mail_html($t2, $p2, 'Otwórz w panelu', BASE_URL . '/#umowy'), um_mail_text($t2, $p2), $att);
    um_log($u['id'], $okF ? 'pdf_wyslany' : 'blad_maila', ($okF ? 'PDF wysłany do Wykonawcy: ' : 'Nie wysłano PDF do Wykonawcy: ') . (isset($f['email']) ? $f['email'] : ''));
    if (function_exists('push')) @push('Umowa zawarta', $u['numer'] . ' – ' . $kn, BASE_URL . '/#umowy');
}

// ---------- KOPIE: archiwum mailowe + Google Drive ----------
function um_kopia_dodaj($uid, $rodzaj, $rel, $nazwa) {
    q('INSERT INTO um_kopie(umowa_id,rodzaj,plik,nazwa,created_at) VALUES(?,?,?,?,?)', array($uid ?: null, $rodzaj, $rel, $nazwa, now()));
    $kid = (int)db()->lastInsertId();
    um_kopia_run($kid);
    return $kid;
}
function um_kopia_run($kid) {
    $k = one('SELECT * FROM um_kopie WHERE id=?', array((int)$kid));
    if (!$k) return;
    $path = um_dir() . '/' . $k['plik'];
    if (!is_file($path)) { q('UPDATE um_kopie SET drive_err=? WHERE id=?', array('brak pliku', $kid)); return; }
    $arch = trim(setting('um_archiwum_email'));
    if ($arch !== '' && empty($k['mail_at']) && filesize($path) <= 15 * 1048576) {
        $u = $k['umowa_id'] ? um_get_row($k['umowa_id']) : null;
        $title = $k['rodzaj'] === 'tydzien' ? 'Kopia tygodniowa umów ELOK: ' . $k['nazwa'] : 'Archiwum umów ELOK: ' . ($u ? $u['numer'] : $k['nazwa']);
        $paras = $k['rodzaj'] === 'tydzien' ? array('W załączniku cotygodniowa kopia modułu Umowy (baza + pliki PDF). Nie usuwaj tych wiadomości – to kopia poza serwerem.')
            : array('Kopia archiwalna umowy <b>' . h($u ? $u['numer'] : '') . '</b> (' . h($u ? $u['szablon_nazwa'] : '') . '). Nie usuwaj tej wiadomości – to kopia poza serwerem, przechowuj min. 6 lat.');
        $mime = substr($k['nazwa'], -4) === '.zip' ? 'application/zip' : 'application/pdf';
        if (um_mail($arch, $title, um_mail_html($title, $paras), um_mail_text($title, $paras), array(array($k['nazwa'], file_get_contents($path), $mime)))) {
            q('UPDATE um_kopie SET mail_at=? WHERE id=?', array(now(), $kid));
            if ($k['umowa_id']) um_log($k['umowa_id'], 'kopia_mail', 'Kopia wysłana na skrzynkę archiwum ' . $arch);
        }
    }
    if (um_gd_ready() && $k['drive_id'] === '' && (int)$k['drive_proby'] < 20) {
        try {
            $mime = substr($k['nazwa'], -4) === '.zip' ? 'application/zip' : 'application/pdf';
            $fid = um_gd_upload($k['nazwa'], file_get_contents($path), $mime);
            q('UPDATE um_kopie SET drive_id=?, drive_err=?, drive_proby=drive_proby+1 WHERE id=?', array($fid, '', $kid));
            if ($k['umowa_id']) um_log($k['umowa_id'], 'kopia_drive', 'Kopia zapisana na Google Drive');
        } catch (Exception $e) {
            q('UPDATE um_kopie SET drive_err=?, drive_proby=drive_proby+1 WHERE id=?', array(mb_substr($e->getMessage(), 0, 250), $kid));
        }
    }
}
// cron: ponowienie nieudanych kopii + kopia tygodniowa
function um_cron() {
    $out = array();
    foreach (all("SELECT id FROM um_kopie WHERE (drive_id='' AND drive_proby<20) OR mail_at IS NULL ORDER BY id LIMIT 10") as $r) um_kopia_run($r['id']);
    // przypomnienie o linkach, które wygasły
    foreach (all("SELECT id, numer FROM um_umowy WHERE status='wyslana' AND token_exp < NOW() AND token_exp > NOW() - INTERVAL 1 DAY") as $r) {
        if (!val("SELECT COUNT(*) FROM um_log WHERE umowa_id=? AND typ='link_wygasl'", array($r['id']))) { um_log($r['id'], 'link_wygasl', 'Link do podpisu wygasł'); $out[] = 'wygasł link ' . $r['numer']; }
    }
    $wk = date('o-W');
    if (setting('um_backup_week') !== $wk && (int)date('N') >= 1 && (int)date('G') >= 3) {
        if ((int)val('SELECT COUNT(*) FROM um_umowy')) { $z = um_backup_zip(); if ($z) $out[] = 'kopia tygodniowa ' . $z[1]; }
        setting_set('um_backup_week', $wk);
    }
    return implode(', ', $out);
}
function um_backup_zip() {
    if (!class_exists('ZipArchive')) return null;
    $dump = array('utworzono' => now(), 'tabele' => array());
    foreach (array('um_klienci', 'um_szablony', 'um_umowy', 'um_wersje', 'um_log', 'um_akceptacje') as $t) $dump['tabele'][$t] = all('SELECT * FROM ' . $t);
    $name = 'ELOK_umowy_kopia_' . date('Y-m-d') . '.zip';
    $rel = 'kopie/' . $name;
    um_dir('kopie');
    $zip = new ZipArchive();
    if ($zip->open(um_dir() . '/' . $rel, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) return null;
    $zip->addFromString('baza.json', json_encode($dump, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    $zip->addFromString('CZYTAJ.txt', "Kopia modułu Umowy panelu ELOK z " . now() . ".\nbaza.json – klienci, szablony, umowy (z zamrożoną treścią HTML i skrótem SHA-256), logi zdarzeń, akceptacje.\npliki/ – PDF zawartych umów (z kartą akceptacji) i PDF z podpisem kwalifikowanym.\n");
    foreach (all("SELECT pdf_plik FROM um_umowy WHERE pdf_plik<>''") as $r) {
        $p = um_dir() . '/' . $r['pdf_plik'];
        if (is_file($p)) $zip->addFile($p, $r['pdf_plik']);
    }
    $zip->close();
    um_kopia_dodaj(null, 'tydzien', $rel, $name);
    // stare kopie lokalne: zostaw 8 ostatnich
    $f = glob(um_dir('kopie') . '/ELOK_umowy_kopia_*.zip'); sort($f);
    while (count($f) > 8) @unlink(array_shift($f));
    return array($rel, $name);
}

// ---------- GOOGLE DRIVE (OAuth, zakres drive.file) ----------
function um_gd_ready() { return trim(setting('um_gd_refresh')) !== '' && trim(setting('um_gd_client_id')) !== ''; }
function um_gd_redirect() { return BASE_URL . '/umowy_drive.php'; }
function um_http($method, $url, $body = null, $headers = array()) {
    $c = curl_init($url);
    curl_setopt_array($c, array(CURLOPT_CUSTOMREQUEST => $method, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_HTTPHEADER => $headers));
    if ($body !== null) curl_setopt($c, CURLOPT_POSTFIELDS, $body);
    $raw = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $err = curl_error($c); curl_close($c);
    if ($raw === false) throw new Exception('Brak połączenia: ' . $err);
    return array($code, json_decode($raw, true), $raw);
}
function um_gd_token() {
    static $tok = null;
    if ($tok) return $tok;
    list($code, $j) = um_http('POST', 'https://oauth2.googleapis.com/token', http_build_query(array(
        'client_id' => setting('um_gd_client_id'), 'client_secret' => setting('um_gd_secret'), 'refresh_token' => setting('um_gd_refresh'), 'grant_type' => 'refresh_token')),
        array('Content-Type: application/x-www-form-urlencoded'));
    if ($code !== 200 || empty($j['access_token'])) throw new Exception('Google Drive: nie udało się odświeżyć dostępu (' . (isset($j['error']) ? $j['error'] : $code) . '). Połącz ponownie w Ustawieniach umów.');
    return $tok = $j['access_token'];
}
function um_gd_folder() {
    $id = trim(setting('um_gd_folder'));
    if ($id !== '') return $id;
    list($code, $j) = um_http('POST', 'https://www.googleapis.com/drive/v3/files?fields=id', json_encode(array('name' => 'ELOK Umowy', 'mimeType' => 'application/vnd.google-apps.folder')),
        array('Authorization: Bearer ' . um_gd_token(), 'Content-Type: application/json'));
    if ($code >= 300 || empty($j['id'])) throw new Exception('Google Drive: nie udało się utworzyć folderu (' . $code . ').');
    setting_set('um_gd_folder', $j['id']);
    return $j['id'];
}
function um_gd_upload($name, $data, $mime) {
    $b = 'g' . token();
    $meta = json_encode(array('name' => $name, 'parents' => array(um_gd_folder())));
    $body = "--$b\r\nContent-Type: application/json; charset=UTF-8\r\n\r\n$meta\r\n--$b\r\nContent-Type: $mime\r\n\r\n$data\r\n--$b--";
    list($code, $j, $raw) = um_http('POST', 'https://www.googleapis.com/upload/drive/v3/files?uploadType=multipart&fields=id', $body,
        array('Authorization: Bearer ' . um_gd_token(), 'Content-Type: multipart/related; boundary=' . $b));
    if ($code === 404) { setting_set('um_gd_folder', ''); throw new Exception('Google Drive: folder nie istnieje – zostanie utworzony ponownie.'); }
    if ($code >= 300 || empty($j['id'])) throw new Exception('Google Drive: błąd wysyłki (' . $code . ') ' . mb_substr((string)$raw, 0, 120));
    return $j['id'];
}

// ---------- NIP: Biała lista VAT (Ministerstwo Finansów) ----------
function um_nip_lookup($nip) {
    $nip = preg_replace('/\D/', '', (string)$nip);
    if (strlen($nip) !== 10) throw new Exception('NIP musi mieć 10 cyfr.');
    $w = array(6, 5, 7, 2, 3, 4, 5, 6, 7); $s = 0; for ($i = 0; $i < 9; $i++) $s += $w[$i] * (int)$nip[$i];
    if ($s % 11 !== (int)$nip[9]) throw new Exception('Ten NIP ma błędną sumę kontrolną – sprawdź cyfry.');
    list($code, $j) = um_http('GET', 'https://wl-api.mf.gov.pl/api/search/nip/' . $nip . '?date=' . date('Y-m-d'), null, array('Accept: application/json'));
    if ($code !== 200) throw new Exception('Biała lista VAT nie odpowiedziała (' . $code . '). Wpisz dane ręcznie.');
    $s = isset($j['result']['subject']) ? $j['result']['subject'] : null;
    if (!$s) throw new Exception('Nie znaleziono firmy w wykazie podatników VAT (może nie być czynnym podatnikiem VAT). Wpisz dane ręcznie.');
    $adr = !empty($s['workingAddress']) ? $s['workingAddress'] : (isset($s['residenceAddress']) ? $s['residenceAddress'] : '');
    $miasto = '';
    if (preg_match('/\b\d{2}-\d{3}\s+(.+)$/u', (string)$adr, $m)) $miasto = trim($m[1]);
    $rep = array(); foreach (array('representatives', 'authorizedClerks') as $kk) if (!empty($s[$kk])) foreach ($s[$kk] as $r) $rep[] = trim((isset($r['firstName']) ? $r['firstName'] : '') . ' ' . (isset($r['lastName']) ? $r['lastName'] : ''));
    $nazwa = isset($s['name']) ? $s['name'] : '';
    return array(
        'nazwa' => $nazwa, 'nip' => $nip, 'regon' => isset($s['regon']) ? (string)$s['regon'] : '', 'adres' => um_adres_ladny($adr), 'siedziba' => um_ladne_miasto($miasto),
        'rejestr' => !empty($s['krs']) ? 'wpisaną do rejestru przedsiębiorców KRS pod nr ' . $s['krs'] : 'wpisaną do CEIDG',
        'reprezentanci' => array_values(array_filter($rep)), 'status_vat' => isset($s['statusVat']) ? $s['statusVat'] : '',
    );
}
function um_ladne_miasto($s) { return mb_convert_case(mb_strtolower((string)$s), MB_CASE_TITLE); }
function um_adres_ladny($a) {
    $a = trim((string)$a); if ($a === '' || mb_strtoupper($a) !== $a) return $a;
    $a = mb_convert_case(mb_strtolower($a), MB_CASE_TITLE);
    return preg_replace_callback('/\b(Ul|Al|Os|Pl)\.?\s/u', function ($m) { return mb_strtolower($m[1]) . '. '; }, $a);
}
