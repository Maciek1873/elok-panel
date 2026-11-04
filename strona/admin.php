<?php
// ELOK – zaplecze zakładek „Strona www” w panelu (Statystyki strony, Edytor strony, Podłączenia, Konsultacje).
// Widoki są w app.js panelu; ten plik zwraca dane (JSON), zapisuje zmiany i pokazuje podgląd strony do edycji.
define('ELOK', 1);
require __DIR__ . '/../lib.php';
start_session();
header('X-Frame-Options: SAMEORIGIN');
header('Cache-Control: no-store');

if (!logged_in()) {
    if (isset($_GET['t']) && $_GET['t'] === 'podglad') { http_response_code(403); exit('Zaloguj się do panelu.'); }
    if (!isset($_GET['j']) && $_SERVER['REQUEST_METHOD'] !== 'POST') { header('Location: ' . BASE_URL . '/#www_stat'); exit; }
    header('Content-Type: application/json; charset=utf-8'); echo json_encode(array('ok' => false, 'login' => true, 'error' => 'Zaloguj się')); exit;
}

// ---------- gdzie leży strona ----------
function sa_dir() {
    $site = ELOK_TEST ? 'test.elok-agency.pl' : 'elok-agency.pl';
    $d = ELOK_TEST ? '' : trim(setting('strona_dir'));
    if ($d !== '' && is_dir($d)) return rtrim($d, '/');
    foreach (array(__DIR__ . '/../../' . $site, __DIR__ . '/../../../' . $site) as $c) { $r = realpath($c); if ($r && is_file($r . '/index.html')) return $r; }
    return '';
}
function sa_url() { if (ELOK_TEST) return 'https://test.elok-agency.pl'; $u = trim(setting('strona_url')); return $u !== '' ? rtrim($u, '/') : 'https://elok-agency.pl'; }
// Zmiany z edytora zapisujemy też w GitHub (repo elok-strona), żeby następne wgranie kodu ich nie nadpisało.
function sa_gh_put($file, $content, $msg) {
    $tok = trim(setting('gh_token')); if ($tok === '' || !function_exists('curl_init')) return false;
    $branch = ELOK_TEST ? 'test' : 'main';
    $url = 'https://api.github.com/repos/Maciek1873/elok-strona/contents/' . implode('/', array_map('rawurlencode', explode('/', $file)));
    $call = function ($m, $u, $body) use ($tok) {
        $c = curl_init($u); $h = array('Authorization: Bearer ' . $tok, 'Accept: application/vnd.github+json', 'User-Agent: ELOK-Panel', 'X-GitHub-Api-Version: 2022-11-28');
        if ($body !== null) { $h[] = 'Content-Type: application/json'; curl_setopt($c, CURLOPT_POSTFIELDS, json_encode($body)); }
        curl_setopt_array($c, array(CURLOPT_CUSTOMREQUEST => $m, CURLOPT_HTTPHEADER => $h, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 20));
        $r = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
        return array($code, $r ? json_decode($r, true) : null);
    };
    list($code, $j) = $call('GET', $url . '?ref=' . $branch, null);
    $body = array('message' => 'Panel: ' . $msg, 'content' => base64_encode($content), 'branch' => $branch);
    if ($code === 200 && isset($j['sha'])) $body['sha'] = $j['sha'];
    list($code2) = $call('PUT', $url, $body);
    return $code2 === 200 || $code2 === 201;
}
function sa_file($f) {
    $f = ltrim(str_replace('\\', '/', (string)$f), '/');
    $d = sa_dir(); if ($d === '') return '';
    // landing page w podkatalogu: <nazwa>/index.html (rozpoznawany po lp.css)
    if (preg_match('#^([a-z0-9-]+)/index\.html$#', $f, $m)) { $p = $d . '/' . $m[1] . '/index.html'; return (is_file($p) && strpos((string)@file_get_contents($p, false, null, 0, 4000), 'lp.css') !== false) ? $p : ''; }
    $f = basename($f);
    if (!preg_match('/^[a-z0-9-]+\.html$/', $f) || $f === '404.html' || $f === 'demo.html') return '';
    return is_file($d . '/' . $f) ? $d . '/' . $f : '';
}
function sa_rel($path) { $d = sa_dir(); return ($d !== '' && strpos($path, $d . '/') === 0) ? substr($path, strlen($d) + 1) : basename($path); }
function sa_key($rel) { return str_replace(array('/', '.html'), array('--', ''), $rel); }
function sa_unkey($k) { return str_replace('--', '/', $k) . '.html'; }
function sa_pages() {
    $d = sa_dir(); if ($d === '') return array();
    $o = array();
    foreach (glob($d . '/*.html') as $p) { $b = basename($p); if ($b === '404.html' || $b === 'demo.html') continue; $o[] = $b; }
    usort($o, function ($a, $b) { $w = array('index.html' => 0, 'automatyzacje.html' => 1, 'strony.html' => 2, 'marketing.html' => 3, 'realizacje.html' => 4, 'cennik.html' => 5, 'o-nas.html' => 6, 'kontakt.html' => 7, 'konsultacja.html' => 8);
        $x = isset($w[$a]) ? $w[$a] : (strpos($a, 'realizacja-') === 0 ? 20 : (strpos($a, 'blog') === 0 ? 30 : 40)); $y = isset($w[$b]) ? $w[$b] : (strpos($b, 'realizacja-') === 0 ? 20 : (strpos($b, 'blog') === 0 ? 30 : 40));
        return $x === $y ? strcmp($a, $b) : $x - $y; });
    foreach (glob($d . '/*/index.html') as $p) { $r = basename(dirname($p)) . '/index.html'; if (sa_file($r) !== '') $o[] = $r; }
    return $o;
}
function sa_title($html) { return preg_match('#<title>(.*?)</title>#s', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : ''; }
function sa_desc($html) { return preg_match('#<meta name="description" content="(.*?)">#s', $html, $m) ? html_entity_decode($m[1], ENT_QUOTES, 'UTF-8') : ''; }

// ---------- teksty: podział strony na kawałki tekstu ----------
// Ten sam podział używany jest do podglądu i do zapisu, więc numer kawałka zawsze wskazuje ten sam tekst.
function sa_tokens($html) {
    return preg_split('#(<!--.*?-->|<script\b.*?</script>|<style\b.*?</style>|<svg\b.*?</svg>|<title>.*?</title>|<textarea\b.*?</textarea>|<[^>]+>)#si', $html, -1, PREG_SPLIT_DELIM_CAPTURE);
}
function sa_editable($tok, $inBody) { return $inBody && trim(str_replace('&nbsp;', ' ', $tok)) !== ''; }
function sa_annotate($html) {
    $t = sa_tokens($html); $in = false; $n = 0;
    foreach ($t as $k => $s) {
        if ($k % 2 === 1) { if (stripos($s, '<body') === 0) $in = true; continue; }
        if (sa_editable($s, $in)) { $t[$k] = '<x-t data-i="' . $n . '">' . $s . '</x-t>'; $n++; }
    }
    return implode('', $t);
}
function sa_apply($html, $zm) {
    $t = sa_tokens($html); $in = false; $n = 0; $c = 0;
    foreach ($t as $k => $s) {
        if ($k % 2 === 1) { if (stripos($s, '<body') === 0) $in = true; continue; }
        if (sa_editable($s, $in)) {
            if (isset($zm[(string)$n])) {
                $new = trim(preg_replace('/\s+/u', ' ', (string)$zm[(string)$n]));
                if ($new !== '') {
                    preg_match('/^\s*/u', $s, $l); preg_match('/\s*$/u', $s, $r);
                    $t[$k] = $l[0] . htmlspecialchars($new, ENT_NOQUOTES, 'UTF-8') . $r[0]; $c++;
                }
            }
            $n++;
        }
    }
    return array(implode('', $t), $c);
}
function sa_backup($path) {
    $d = __DIR__ . '/kopie'; if (!is_dir($d)) { @mkdir($d, 0755, true); @file_put_contents($d . '/.htaccess', "Require all denied\n"); }
    $b = sa_key(sa_rel($path));
    $name = $b . '__' . date('Y-m-d_H-i-s') . '.html';
    @copy($path, $d . '/' . $name);
    $all = glob($d . '/' . $b . '__*.html'); rsort($all);
    foreach (array_slice($all, 50) as $old) { @unlink($old); @unlink(substr($old, 0, -5) . '.json'); }
    return $name;
}
function sa_backups($f) { $b = sa_key($f); $all = glob(__DIR__ . '/kopie/' . $b . '__*.html'); rsort($all); return array_slice($all, 0, 50); }
// opis zapisu: co zmieniono (było → jest), zapisany obok kopii sprzed zapisu
function sa_txt($s) { return trim(preg_replace('/\s+/u', ' ', html_entity_decode(strip_tags((string)$s), ENT_QUOTES, 'UTF-8'))); }
function sa_diff($html, $zm) {
    $t = sa_tokens($html); $in = false; $n = 0; $out = array();
    foreach ($t as $k => $s) {
        if ($k % 2 === 1) { if (stripos($s, '<body') === 0) $in = true; continue; }
        if (sa_editable($s, $in)) { if (isset($zm[(string)$n])) { $a = sa_txt($s); $b = sa_txt($zm[(string)$n]); if ($b !== '' && $a !== $b) $out[] = array(mb_substr($a, 0, 300), mb_substr($b, 0, 300)); } $n++; }
    }
    return $out;
}
function sa_note($backup, $info) { if ($backup) @file_put_contents(__DIR__ . '/kopie/' . substr($backup, 0, -5) . '.json', json_encode($info, JSON_UNESCAPED_UNICODE)); }
function sa_info($bn) { $f = __DIR__ . '/kopie/' . substr($bn, 0, -5) . '.json'; $j = is_file($f) ? json_decode((string)file_get_contents($f), true) : null; return is_array($j) ? $j : array(); }
function sa_kiedy($bn) { return preg_match('/__(\d{4})-(\d\d)-(\d\d)_(\d\d)-(\d\d)/', $bn, $m) ? $m[3] . '.' . $m[2] . '.' . $m[1] . ' ' . $m[4] . ':' . $m[5] : ''; }
function sa_write($path, $html) { $tmp = $path . '.tmp' . mt_rand(); if (@file_put_contents($tmp, $html) === false) return false; return @rename($tmp, $path); }

// ---------- podłączenia: ustawienia.js + kody weryfikacyjne ----------
$INTEG = array(
    'clarity' => array('Microsoft Clarity – ID projektu', '/^[a-z0-9]{6,20}$/', 'clarity.microsoft.com → Twój projekt → Settings → Overview → Project ID (np. abcd1234ef)'),
    'ga4' => array('Google Analytics 4 – identyfikator pomiaru', '/^G-[A-Z0-9]{4,16}$/', 'analytics.google.com → Administracja → Strumienie danych → Twoja strona → „Identyfikator pomiaru” (G-XXXXXXX)'),
    'gads' => array('Google Ads – identyfikator konwersji', '/^AW-[0-9]{6,15}$/', 'Google Ads → Cele → Konwersje → Nowa → Witryna → „Zainstaluj tag ręcznie” → AW-XXXXXXXXX'),
    'gadsLabel' => array('Google Ads – etykieta konwersji (rezerwacja)', '/^[A-Za-z0-9_-]{4,40}$/', 'Z tego samego miejsca: send_to: AW-XXX/<b>TA_CZĘŚĆ</b>'),
    'metaPixel' => array('Meta (Facebook/Instagram) – ID piksela', '/^[0-9]{8,20}$/', 'business.facebook.com → Menedżer zdarzeń → Źródła danych → Twój piksel → ID (same cyfry)'),
    'tiktokPixel' => array('TikTok – ID piksela', '/^[A-Z0-9]{10,30}$/', 'ads.tiktok.com → Narzędzia → Zdarzenia → Zdarzenia w witrynie → Twój piksel → ID (np. C9ABCDEF1234567890)'),
);
$VERIF = array(
    'google-site-verification' => array('Google Search Console – kod weryfikacyjny', '/^[A-Za-z0-9_-]{20,80}$/', 'search.google.com/search-console → Dodaj usługę → „Prefiks URL” https://elok-agency.pl → metoda „Tag HTML” → skopiuj tylko to, co jest w content="…"'),
    'facebook-domain-verification' => array('Meta – weryfikacja domeny', '/^[a-z0-9]{20,40}$/', 'business.facebook.com → Ustawienia firmy → Bezpieczeństwo marki → Domeny → Dodaj → „Metatag” → skopiuj to, co jest w content="…"'),
);
function sa_sync() {
    global $INTEG, $VERIF;
    $d = sa_dir(); if ($d === '') return 'Nie znaleziono folderu strony.';
    $cfg = array(); foreach ($INTEG as $k => $x) $cfg[$k] = setting('int_' . $k);
    $js = "// Generowane przez panel ELOK (Strona → Podłączenia). Nie edytuj ręcznie.\nwindow.ELOK_CFG=" . json_encode($cfg, JSON_UNESCAPED_SLASHES) . ";\n";
    if (@file_get_contents($d . '/ustawienia.js') !== $js && @file_put_contents($d . '/ustawienia.js', $js) === false) return 'Brak prawa zapisu w folderze strony.';
    $err = '';
    foreach (sa_pages() as $p) {
        $path = $d . '/' . $p; $h = file_get_contents($path); $o = $h;
        if (strpos($h, 'src="ustawienia.js') === false && strpos($h, '<script src="site.js"></script>') !== false) $h = str_replace('<script src="site.js"></script>', '<script src="ustawienia.js"></script><script src="site.js"></script>', $h);
        if ($p === 'index.html') {
            foreach ($VERIF as $name => $x) {
                $v = setting('ver_' . $name);
                $h = preg_replace('#<meta name="' . preg_quote($name, '#') . '" content="[^"]*">#', '', $h);
                if ($v !== '') $h = preg_replace('#<head>#', '<head><meta name="' . $name . '" content="' . h($v) . '">', $h, 1);
            }
        }
        if ($h !== $o) { if (!sa_write($path, $h)) $err = 'Nie udało się zapisać ' . $p; else sa_gh_put($p, $h, 'podłączenia (' . $p . ')'); }
    }
    return $err;
}

// ---------- źródła ruchu ----------
function sa_source($s, $m, $r) {
    $s = strtolower($s); $r = strtolower($r);
    if ($s !== '') {
        if (preg_match('/^(fb|facebook|meta)/', $s)) return (preg_match('/cpc|paid|ads/', $m) || $s === 'facebook') ? 'Facebook (reklama)' : 'Facebook';
        if (preg_match('/^(ig|instagram)/', $s)) return 'Instagram';
        if (preg_match('/^tiktok/', $s)) return 'TikTok';
        if ($s === 'google') return preg_match('/cpc|paid|ads/', $m) ? 'Google Ads' : 'Google';
        if (preg_match('/mail|newsletter/', $s . $m)) return 'E-mail';
        return ucfirst($s);
    }
    if ($r === '') return 'Wejście bezpośrednie';
    if (preg_match('/(^|\.)google\./', $r)) return 'Google (wyszukiwarka)';
    if (preg_match('/facebook\.com$|fb\.com$|fb\.me$/', $r)) return 'Facebook';
    if (preg_match('/instagram\.com$/', $r)) return 'Instagram';
    if (preg_match('/tiktok\.com$/', $r)) return 'TikTok';
    if (preg_match('/bing\.com$/', $r)) return 'Bing';
    if (preg_match('/chatgpt\.com$|openai\.com$|perplexity\.ai$|claude\.ai$/', $r)) return 'Czat AI (' . $r . ')';
    if (preg_match('/linkedin\.com$|lnkd\.in$/', $r)) return 'LinkedIn';
    if (preg_match('/elok-agency\.pl$/', $r)) return 'Panel / e-mail ELOK';
    return $r;
}
$PNAME = array('/index.html' => 'Strona główna', '/strona-dla-firmy/index.html' => 'Landing: strona dla firmy', '/sklep-internetowy/index.html' => 'Landing: sklep internetowy', '/automatyzacja-ai/index.html' => 'Landing: automatyzacja i AI', '/automatyzacje.html' => 'Automatyzacje', '/strony.html' => 'Strony i sklepy', '/marketing.html' => 'Marketing', '/realizacje.html' => 'Case studies', '/cennik.html' => 'Cennik', '/o-nas.html' => 'O nas', '/kontakt.html' => 'Kontakt', '/konsultacja.html' => 'Konsultacja', '/blog.html' => 'Blog', '/realizacja-serwis.html' => 'Case: serwis', '/realizacja-sklep.html' => 'Case: sklep', '/realizacja-panel.html' => 'Case: panel', '/polityka-prywatnosci.html' => 'Polityka prywatności');
function st_kiedy($ts) { $D = array('nd', 'pon', 'wt', 'śr', 'czw', 'pt', 'sob'); return $D[(int)date('w', $ts)] . ' ' . date('d.m', $ts) . ', ' . date('G:i', $ts); }
function pname($p) { global $PNAME; return isset($PNAME[$p]) ? $PNAME[$p] : preg_replace('#^/|\.html$#', '', $p); }

// ================= AKCJE (POST, JSON) =================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $in = json_decode(file_get_contents('php://input'), true); if (!is_array($in)) $in = $_POST;
    $fail = function ($m) { echo json_encode(array('ok' => false, 'error' => $m), JSON_UNESCAPED_UNICODE); exit; };
    $tok = isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : (isset($in['csrf']) ? (string)$in['csrf'] : '');
    if (!hash_equals($_SESSION['csrf'], $tok)) $fail('Sesja wygasła – odśwież stronę.');
    $act = isset($in['a']) ? $in['a'] : (isset($_GET['a']) ? $_GET['a'] : '');
    if ($act === 'zapisz') {
        $path = sa_file(isset($in['f']) ? $in['f'] : ''); if ($path === '') $fail('Nie ma takiej strony.');
        $h = file_get_contents($path);
        $zm = isset($in['zm']) && is_array($in['zm']) ? $in['zm'] : array();
        $res = sa_apply($h, $zm); $h2 = $res[0]; $c = $res[1];
        if (isset($in['title'])) { $t = trim((string)$in['title']); if ($t !== '' && $t !== sa_title($h2)) { $e = h($t); $h2 = preg_replace('#<title>.*?</title>#s', '<title>' . $e . '</title>', $h2, 1); $h2 = preg_replace('#<meta property="og:title" content="[^"]*">#', '<meta property="og:title" content="' . $e . '">', $h2, 1); $c++; } }
        if (isset($in['desc'])) { $t = trim(preg_replace('/\s+/u', ' ', (string)$in['desc'])); if ($t !== sa_desc($h2)) { $e = h($t);
            if (preg_match('#<meta name="description" content="[^"]*">#', $h2)) $h2 = preg_replace('#<meta name="description" content="[^"]*">#', '<meta name="description" content="' . $e . '">', $h2, 1); else $h2 = preg_replace('#</title>#', '</title><meta name="description" content="' . $e . '">', $h2, 1);
            $h2 = preg_replace('#<meta property="og:description" content="[^"]*">#', '<meta property="og:description" content="' . $e . '">', $h2, 1); $c++; } }
        if ($h2 === $h) { echo json_encode(array('ok' => true, 'zmiany' => 0)); exit; }
        $diff = sa_diff($h, $zm);
        if (sa_title($h2) !== sa_title($h)) $diff[] = array('Tytuł w Google: ' . sa_title($h), 'Tytuł w Google: ' . sa_title($h2));
        if (sa_desc($h2) !== sa_desc($h)) $diff[] = array('Opis w Google: ' . sa_desc($h), 'Opis w Google: ' . sa_desc($h2));
        sa_note(sa_backup($path), array('typ' => 'zapis', 'zm' => $diff, 'ip' => isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : ''));
        if (!sa_write($path, $h2)) $fail('Nie udało się zapisać pliku (brak uprawnień?).');
        logi(null, 'strona', 'Edycja ' . sa_rel($path) . ' (' . $c . ' zmian)');
        sa_gh_put(sa_rel($path), $h2, 'edycja tekstów ' . sa_rel($path));
        echo json_encode(array('ok' => true, 'zmiany' => $c)); exit;
    }
    if ($act === 'przywroc') {
        $path = sa_file(isset($in['f']) ? $in['f'] : ''); if ($path === '') $fail('Nie ma takiej strony.');
        $k = basename((string)(isset($in['k']) ? $in['k'] : '')); $src = __DIR__ . '/kopie/' . $k;
        if (!preg_match('/^[a-z0-9-]+__[0-9_-]+\.html$/', $k) || strpos($k, sa_key(sa_rel($path)) . '__') !== 0 || !is_file($src)) $fail('Nie ma takiej kopii.');
        sa_note(sa_backup($path), array('typ' => 'przywrocenie', 'z' => sa_kiedy($k))); $hh = file_get_contents($src); if (!sa_write($path, $hh)) $fail('Nie udało się przywrócić.');
        sa_gh_put(sa_rel($path), $hh, 'przywrócona wersja ' . sa_rel($path));
        echo json_encode(array('ok' => true)); exit;
    }
    if ($act === 'kons_przyp') { // ręcznie: wyślij teraz przypomnienie z pytaniem Meet czy telefon
        require_once __DIR__ . '/przypomnienia.php';
        add_col('rezerwacje', 'forma', "VARCHAR(10) NOT NULL DEFAULT ''"); add_col('rezerwacje', 'przyp1', 'TINYINT NOT NULL DEFAULT 0');
        $r = one('SELECT * FROM rezerwacje WHERE id=?', array((int)(isset($in['id']) ? $in['id'] : 0)));
        if (!$r || strtotime($r['termin']) < time()) $fail('Nie ma takiej nadchodzącej konsultacji.');
        if (!kp_send1($r, st_kiedy(strtotime($r['termin'])), setting('podpis_tel') ?: '455 505 820')) $fail('Nie udało się wysłać maila.');
        q('UPDATE rezerwacje SET przyp1=1 WHERE id=?', array($r['id']));
        logi(null, 'strona', 'Przypomnienie Meet/telefon: ' . $r['email']);
        echo json_encode(array('ok' => true)); exit;
    }
    if ($act === 'podlaczenia') {
        $v = isset($in['v']) && is_array($in['v']) ? $in['v'] : array();
        foreach ($INTEG as $k => $x) { $s = trim((string)(isset($v[$k]) ? $v[$k] : '')); if ($s !== '' && !preg_match($x[1], $s)) $fail('Sprawdź pole: ' . $x[0]); setting_set('int_' . $k, $s); }
        foreach ($VERIF as $k => $x) { $s = trim((string)(isset($v[$k]) ? $v[$k] : '')); if (preg_match('/content="([^"]+)"/', $s, $m)) $s = $m[1]; if ($s !== '' && !preg_match($x[1], $s)) $fail('Sprawdź pole: ' . $x[0]); setting_set('ver_' . $k, $s); }
        $e = sa_sync(); if ($e !== '') $fail($e);
        echo json_encode(array('ok' => true)); exit;
    }
    if ($act === 'konsultacje') {
        $l = trim((string)(isset($in['link']) ? $in['link'] : '')); if ($l !== '' && !preg_match('#^https://#', $l)) $fail('Link musi zaczynać się od https://');
        $w = array(); foreach (preg_split('/[\s,;]+/', (string)(isset($in['wolne']) ? $in['wolne'] : '')) as $x) { $x = trim($x); if ($x === '') continue; if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $x)) $fail('Daty wpisz jako RRRR-MM-DD, np. 2026-12-24'); $w[] = $x; }
        setting_set('kons_link', $l); setting_set('kons_wolne', implode(',', $w));
        echo json_encode(array('ok' => true)); exit;
    }
    if ($act === 'folder') {
        $d = rtrim(trim((string)(isset($in['dir']) ? $in['dir'] : '')), '/');
        if ($d !== '' && !is_file($d . '/index.html')) $fail('W tym folderze nie ma index.html.');
        setting_set('strona_dir', $d); echo json_encode(array('ok' => true)); exit;
    }
    $fail('Nieznana akcja');
}

$t = isset($_GET['t']) ? $_GET['t'] : 'statystyki';

// ================= PODGLĄD DO EDYCJI (w ramce) =================
if ($t === 'podglad') {
    $path = sa_file(isset($_GET['f']) ? $_GET['f'] : ''); if ($path === '') { http_response_code(404); exit('Brak strony'); }
    header('Content-Type: text/html; charset=utf-8');
    $h = sa_annotate(file_get_contents($path));
    $h = preg_replace('#<script\b[^>]*src="/?(site|ustawienia|lp)\.js[^"]*"[^>]*></script>#', '', $h);
    $h = preg_replace('#<head>#', '<head><base href="' . h(sa_url()) . '/">', $h, 1);
    $css = @file_get_contents(sa_dir() . '/site.css');
    if ($css !== false) $h = preg_replace('#<link rel="stylesheet" href="site\.css[^"]*">#', '<style>' . str_replace(array('\\', '$'), array('\\\\', '\\$'), $css) . '</style>', $h, 1);
    $css = '<style>x-t{outline:1px dashed rgba(207,168,94,.0);border-radius:3px;transition:outline-color .15s,background .15s;cursor:text}body.ed x-t:hover{outline-color:rgba(207,168,94,.7)}x-t:focus{outline:2px solid #CFA85E!important;background:rgba(207,168,94,.12)}x-t.zm{background:rgba(91,196,138,.16);outline:1px solid rgba(91,196,138,.7)}.rv,.rv *{opacity:1!important;transform:none!important}.nav{position:absolute!important}.cookie{display:none!important}</style>';
    $js = '<script>(function(){document.body.classList.add("ed");var L=[].slice.call(document.querySelectorAll("x-t"));var pt=true;try{var d=document.createElement("div");d.contentEditable="plaintext-only";pt=d.contentEditable==="plaintext-only";}catch(e){pt=false;}
L.forEach(function(x){x.contentEditable=pt?"plaintext-only":"true";x.setAttribute("data-o",x.textContent);x.addEventListener("input",function(){x.classList.toggle("zm",x.textContent!==x.getAttribute("data-o"));if(parent.edChanged)parent.edChanged();});
x.addEventListener("keydown",function(e){if(e.key==="Enter"){e.preventDefault();x.blur();}});x.addEventListener("paste",function(e){if(pt)return;e.preventDefault();var t=(e.clipboardData||window.clipboardData).getData("text");document.execCommand("insertText",false,t);});});
document.addEventListener("click",function(e){var a=e.target.closest("a,button,label");if(a){e.preventDefault();}var x=e.target.closest("x-t");if(x)x.focus();},true);
document.addEventListener("submit",function(e){e.preventDefault();},true);
window.edGet=function(){var o={},n=0;L.forEach(function(x){if(x.textContent!==x.getAttribute("data-o")){o[x.getAttribute("data-i")]=x.textContent;n++;}});return {zm:o,n:n};};})();</script>';
    $h = str_replace('</head>', $css . '</head>', $h);
    $h = preg_replace('#</body>#', $js . '</body>', $h, 1);
    echo $h; exit;
}

// ================= DANE DLA ZAKŁADEK (JSON) =================
header('Content-Type: application/json; charset=utf-8');
function jout($a) { $a['ok'] = true; echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
$j = isset($_GET['j']) ? $_GET['j'] : '';
$dir = sa_dir();
if ($dir === '') jout(array('nodir' => true, 'panel' => realpath(__DIR__ . '/..'), 'zgad' => realpath(__DIR__ . '/../..') . '/elok-agency.pl', 'url' => sa_url()));
sa_sync();

if ($j === 'stat') {
    $dni = isset($_GET['d']) ? max(1, min(400, (int)$_GET['d'])) : 30;
    $od = date('Y-m-d H:i:s', strtotime('today') - ($dni - 1) * 86400);
    $prevOd = date('Y-m-d H:i:s', strtotime($od) - $dni * 86400);
    db()->exec("CREATE TABLE IF NOT EXISTS strona_log (id INT AUTO_INCREMENT PRIMARY KEY, t DATETIME NOT NULL, ev VARCHAR(30) NOT NULL, path VARCHAR(120) NOT NULL DEFAULT '', sid VARCHAR(40) NOT NULL DEFAULT '', src VARCHAR(60) NOT NULL DEFAULT '', medium VARCHAR(60) NOT NULL DEFAULT '', kampania VARCHAR(80) NOT NULL DEFAULT '', ref VARCHAR(120) NOT NULL DEFAULT '', wejscie VARCHAR(120) NOT NULL DEFAULT '', urzadzenie VARCHAR(12) NOT NULL DEFAULT '', x VARCHAR(80) NOT NULL DEFAULT '', KEY te (t, ev), KEY sd (sid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    db()->exec("CREATE TABLE IF NOT EXISTS rezerwacje (id INT AUTO_INCREMENT PRIMARY KEY, termin DATETIME NOT NULL, zgloszenie_id INT NULL, imie VARCHAR(120) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '', telefon VARCHAR(60) NOT NULL DEFAULT '', opis TEXT, status VARCHAR(20) NOT NULL DEFAULT 'aktywna', ip VARCHAR(45) NULL, created_at DATETIME NOT NULL, UNIQUE KEY ut (termin)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $S = all("SELECT sid, MIN(t) t0, MAX(src) s, MAX(medium) m, MAX(kampania) c, MAX(ref) r, MAX(wejscie) l, MAX(urzadzenie) d, SUM(ev='wejscie') pv, SUM(ev='rezerwacja') rez, SUM(ev='telefon') tel, SUM(ev='mail') ml, SUM(ev='klik_konsultacja') kk FROM strona_log WHERE t>=? GROUP BY sid", array($od));
    $prev = (int)val("SELECT COUNT(DISTINCT sid) FROM strona_log WHERE t>=? AND t<? AND ev='wejscie'", array($prevOd, $od));
    $sess = 0; $pv = 0; $rez = 0; $tel = 0; $ml = 0; $kk = 0; $src = array(); $land = array(); $dev = array(); $day = array(); $kamp = array();
    for ($i = 0; $i < $dni; $i++) $day[date('Y-m-d', strtotime($od) + $i * 86400 + 3600)] = 0;
    foreach ($S as $r) {
        if ((int)$r['pv'] === 0 && (int)$r['rez'] === 0) continue;
        $sess++; $pv += $r['pv']; $rez += ($r['rez'] > 0 ? 1 : 0); $tel += ($r['tel'] > 0 ? 1 : 0); $ml += ($r['ml'] > 0 ? 1 : 0); $kk += ($r['kk'] > 0 ? 1 : 0);
        $kontakt = ($r['rez'] > 0 || $r['tel'] > 0 || $r['ml'] > 0) ? 1 : 0;
        $sn = sa_source($r['s'], $r['m'], $r['r']);
        if (!isset($src[$sn])) $src[$sn] = array(0, 0, 0); $src[$sn][0]++; $src[$sn][1] += ($r['rez'] > 0 ? 1 : 0); $src[$sn][2] += $kontakt;
        $ln = $r['l'] !== '' ? $r['l'] : '/index.html'; if (!isset($land[$ln])) $land[$ln] = array(0, 0, 0); $land[$ln][0]++; $land[$ln][1] += ($r['rez'] > 0 ? 1 : 0); $land[$ln][2] += $kontakt;
        $dv = $r['d'] !== '' ? $r['d'] : 'inne'; if (!isset($dev[$dv])) $dev[$dv] = array(0, 0); $dev[$dv][0]++; $dev[$dv][1] += $kontakt;
        if ($r['c'] !== '') { $kn = $r['c'] . ' · ' . $sn; if (!isset($kamp[$kn])) $kamp[$kn] = array(0, 0); $kamp[$kn][0]++; $kamp[$kn][1] += $kontakt; }
        $dd = substr($r['t0'], 0, 10); if (isset($day[$dd])) $day[$dd]++;
    }
    $rezT = (int)val('SELECT COUNT(*) FROM rezerwacje WHERE created_at>=?', array($od)); if ($rezT > $rez) $rez = $rezT;
    $pages = all("SELECT path, COUNT(*) pv, COUNT(DISTINCT sid) u FROM strona_log WHERE t>=? AND ev='wejscie' GROUP BY path ORDER BY pv DESC LIMIT 25", array($od));
    $scr = array(); foreach (all("SELECT path, COUNT(DISTINCT sid) u FROM strona_log WHERE t>=? AND ev='przewiniecie_75' GROUP BY path", array($od)) as $r) $scr[$r['path']] = (int)$r['u'];
    foreach ($pages as $k => $r) { $pages[$k]['nazwa'] = pname($r['path']); $pages[$k]['doczyt'] = isset($scr[$r['path']]) ? $scr[$r['path']] : 0; }
    $ctas = all("SELECT x, path, COUNT(*) n FROM strona_log WHERE t>=? AND ev='klik_konsultacja' GROUP BY x, path ORDER BY n DESC LIMIT 12", array($od));
    foreach ($ctas as $k => $r) $ctas[$k]['nazwa'] = pname($r['path']);
    $kd = array(); foreach (all("SELECT ev, COUNT(*) n FROM strona_log WHERE t>=? AND ev IN('telefon','mail','kopiuj','demo','social','klik_konsultacja') GROUP BY ev", array($od)) as $r) $kd[$r['ev']] = (int)$r['n'];
    $KL = array('klik_konsultacja' => 'Kliknięcia „Umów konsultację”', 'telefon' => 'Kliknięcia w telefon', 'mail' => 'Kliknięcia w e-mail', 'kopiuj' => 'Skopiowany numer / mail', 'demo' => 'Otwarcia demo panelu', 'social' => 'Przejścia na TikTok / Instagram');
    $klik = array(); foreach ($KL as $k => $v) $klik[] = array($v, isset($kd[$k]) ? $kd[$k] : 0);
    uasort($src, function ($a, $b) { return $b[0] - $a[0]; }); uasort($land, function ($a, $b) { return $b[0] - $a[0]; }); uasort($kamp, function ($a, $b) { return $b[0] - $a[0]; });
    $ins = array();
    $best = null; foreach ($src as $k => $v) if ($v[0] >= 5 && ($best === null || $v[2] / $v[0] > $best[1])) $best = array($k, $v[2] / $v[0]);
    if ($best && $best[1] > 0) $ins[] = 'Najlepiej konwertuje <b>' . h($best[0]) . '</b>: ' . round($best[1] * 100, 1) . '% odwiedzających się kontaktuje (rezerwacja, telefon lub mail).';
    $bl = null; foreach ($land as $k => $v) if ($v[0] >= 5 && ($bl === null || $v[2] / $v[0] > $bl[1])) $bl = array($k, $v[2] / $v[0]);
    if ($bl && $bl[1] > 0) $ins[] = 'Najskuteczniejsza strona wejścia: <b>' . h(pname($bl[0])) . '</b> (' . round($bl[1] * 100, 1) . '% kontaktów).';
    if ($sess >= 10 && isset($dev['telefon'])) $ins[] = '<b>' . round($dev['telefon'][0] / $sess * 100) . '%</b> odwiedzających jest na telefonie.';
    if ($sess >= 20 && $kk > 0 && $rez === 0) $ins[] = 'Ludzie klikają „Umów konsultację” (' . $kk . '), ale nikt nie dokończył rezerwacji – warto uprościć stronę konsultacji albo wyżej pokazać telefon.';
    if ($sess >= 20 && $kk === 0) $ins[] = 'Nikt nie kliknął w konsultację – przyciski mogą być za słabo widoczne albo ruch jest przypadkowy.';
    if ($prev > 0) { $ch = round(($sess - $prev) / $prev * 100); $ins[] = 'Ruch ' . ($ch >= 0 ? 'wzrósł o <b>' . $ch . '%</b>' : 'spadł o <b>' . abs($ch) . '%</b>') . ' względem poprzednich ' . $dni . ' dni.'; }
    if (!$ins) $ins[] = 'Za mało danych na wnioski. Pierwsze sensowne wnioski po ok. 50–100 odwiedzinach.';
    $L = function ($arr, $n, $nm) { $o = array(); foreach (array_slice($arr, 0, $n, true) as $k => $v) $o[] = array_merge(array($nm ? pname($k) : $k), $v); return $o; };
    $dayO = array(); foreach ($day as $d => $v) $dayO[] = array(date('d.m', strtotime($d)), $v);
    jout(array('dni' => $dni, 'sesje' => $sess, 'prev' => $prev, 'pv' => $pv, 'rez' => $rez, 'tel' => $tel, 'ml' => $ml, 'kk' => $kk, 'dzien' => $dayO, 'ins' => $ins,
        'src' => $L($src, 15, false), 'land' => $L($land, 12, true), 'dev' => $L($dev, 5, false), 'kamp' => $L($kamp, 12, false), 'pages' => $pages, 'ctas' => $ctas, 'klik' => $klik, 'url' => sa_url()));
}
if ($j === 'strony') {
    $o = array(); foreach (sa_pages() as $p) { $h = file_get_contents($dir . '/' . $p); $o[] = array('f' => $p, 'nazwa' => pname('/' . $p), 'title' => sa_title($h), 'desc' => sa_desc($h)); }
    jout(array('strony' => $o, 'url' => sa_url()));
}
if ($j === 'strona') {
    $path = sa_file(isset($_GET['f']) ? $_GET['f'] : ''); if ($path === '') { echo json_encode(array('ok' => false, 'error' => 'Nie ma takiej strony')); exit; }
    $h = file_get_contents($path); $k = array();
    foreach (sa_backups(sa_rel($path)) as $b) { $bn = basename($b); $kd = sa_kiedy($bn); if ($kd !== '') $k[] = array('k' => $bn, 'kiedy' => $kd, 'info' => sa_info($bn)); }
    jout(array('f' => sa_rel($path), 'title' => sa_title($h), 'desc' => sa_desc($h), 'kopie' => $k));
}
if ($j === 'historia') {
    $all = glob(__DIR__ . '/kopie/*__*.html'); usort($all, function ($a, $b) { return strcmp(substr(basename($b), strpos(basename($b), '__')), substr(basename($a), strpos(basename($a), '__'))); });
    $o = array(); foreach (array_slice($all, 0, 60) as $b) { $bn = basename($b); $f = sa_unkey(substr($bn, 0, strpos($bn, '__'))); $o[] = array('k' => $bn, 'f' => $f, 'nazwa' => pname('/' . $f), 'kiedy' => sa_kiedy($bn), 'info' => sa_info($bn)); }
    jout(array('h' => $o));
}
if ($j === 'podl') {
    $a = array(); foreach ($INTEG as $k => $x) $a[] = array('k' => $k, 'label' => $x[0], 'hint' => $x[2], 'v' => setting('int_' . $k));
    $b = array(); foreach ($VERIF as $k => $x) $b[] = array('k' => $k, 'label' => $x[0], 'hint' => $x[2], 'v' => setting('ver_' . $k));
    jout(array('integ' => $a, 'verif' => $b, 'url' => sa_url()));
}
if ($j === 'kons') {
    db()->exec("CREATE TABLE IF NOT EXISTS rezerwacje (id INT AUTO_INCREMENT PRIMARY KEY, termin DATETIME NOT NULL, zgloszenie_id INT NULL, imie VARCHAR(120) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '', telefon VARCHAR(60) NOT NULL DEFAULT '', opis TEXT, status VARCHAR(20) NOT NULL DEFAULT 'aktywna', ip VARCHAR(45) NULL, created_at DATETIME NOT NULL, UNIQUE KEY ut (termin)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    add_col('rezerwacje', 'forma', "VARCHAR(10) NOT NULL DEFAULT ''"); add_col('rezerwacje', 'przyp1', 'TINYINT NOT NULL DEFAULT 0');
    $R = all("SELECT r.id, r.forma, r.przyp1, r.przyp2, r.termin, r.imie, r.email, r.telefon, r.opis, r.zgloszenie_id, z.status zst FROM rezerwacje r LEFT JOIN zgloszenia z ON z.id=r.zgloszenie_id WHERE r.termin>=? ORDER BY r.termin LIMIT 100", array(date('Y-m-d 00:00:00')));
    foreach ($R as $k => $r) { $R[$k]['kiedy'] = st_kiedy(strtotime($r['termin'])); $R[$k]['odwolana'] = ($r['zst'] === 'odrzucone' || $r['zst'] === null); }
    jout(array('rows' => $R, 'link' => setting('kons_link'), 'wolne' => str_replace(',', ', ', setting('kons_wolne', '2026-11-11,2026-12-24,2026-12-25,2026-12-26,2026-12-31,2027-01-01,2027-01-06'))));
}
echo json_encode(array('ok' => false, 'error' => 'Nieznane zapytanie'));
