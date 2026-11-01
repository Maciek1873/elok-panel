<?php
// ELOK – publiczne linki z maili: piksel otwarć, formularz "Tak", wypisanie
define('ELOK', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/formularz.php';

$a = isset($_GET['a']) ? $_GET['a'] : '';
$t = isset($_GET['t']) ? preg_replace('/[^a-f0-9]/', '', $_GET['t']) : '';
$f = (strlen($t) === 32) ? one('SELECT * FROM firmy WHERE token=?', array($t)) : null;
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

// czas ostatniej wysyłki do firmy (1. mail albo przypomnienie)
function last_send($f) {
    $t = 0;
    foreach (array('wyslano_at', 'przypomnienie_at', 'przypomnienie2_at') as $k) if (!empty($f[$k])) $t = max($t, strtotime($f[$k]));
    return $t;
}
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
$isBotUa = $ua === '' || preg_match('/bot|crawl|spider|python|curl|wget|java\/|go-http|okhttp|scan|proofpoint|mimecast|barracuda|symantec|forcepoint|trend ?micro|sophos|fortinet|safelinks|linkscanner|headless|phantom|preview|validator|monitor/i', $ua);

// ---------- PIKSEL ----------
// Liczymy tylko "prawdziwe" otwarcia: nie w 1. minucie po wysyłce (filtry/boty) i max 1 na godzinę.
if ($a === 'o') {
    if ($f && !$isBotUa) {
        $ls = last_send($f);
        $tooSoon = $ls && (time() - $ls) < 60;
        $recent = !empty($f['otwarto_last']) && (time() - strtotime($f['otwarto_last'])) < 3600;
        if (!$tooSoon && !$recent) {
            q("UPDATE firmy SET otwarcia=otwarcia+1, otwarto_at=COALESCE(otwarto_at,?), otwarto_last=?, status=IF(status='wyslano','otworzyl',status) WHERE id=?", array(now(), now(), $f['id']));
            if (!$f['otwarto_at']) logi($f['id'], 'otwarcie', '');
        } elseif (!$tooSoon) {
            q('UPDATE firmy SET otwarto_last=? WHERE id=?', array(now(), $f['id']));
        }
    }
    header('Content-Type: image/gif');
    header('Cache-Control: no-store, no-cache, must-revalidate');
    echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7');
    exit;
}

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');

function pub_page($title, $body) { echo pub_html($title, $body); exit; }
if ($f) set_lang_branza($f['branza_id']);
$EN = lang() === 'en';

// ---------- WYPISANIE ----------
if ($a === 'wypis') {
    $oneClick = isset($_POST['List-Unsubscribe']) && $_POST['List-Unsubscribe'] === 'One-Click';
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($oneClick || isset($_POST['potwierdz']))) {
        if ($f && $f['status'] !== 'wypisany') {
            q("UPDATE firmy SET status='wypisany', wypis_at=? WHERE id=?", array(now(), $f['id']));
            logi($f['id'], 'wypis', $oneClick ? 'one-click' : 'strona');
        }
        if ($oneClick) { http_response_code(200); exit; }
        if ($EN) pub_page('Unsubscribed', '<div class="ok">&#10003;</div><h1>Done</h1><p>Your address has been removed. We will not email you again.</p>');
        pub_page('Wypisano', '<div class="ok">&#10003;</div><h1>Gotowe</h1><p>Adres został usunięty z listy. Nie wyślemy więcej wiadomości.</p>');
    }
    if ($EN) pub_page('Unsubscribe', '<h1>Unsubscribe from our emails?</h1><p>After confirming you will not receive any more emails from us' . ($f ? ' at <b>' . h($f['email']) . '</b>' : '') . '.</p>'
        . '<form method="post"><input type="hidden" name="potwierdz" value="1"><button>Yes, unsubscribe me</button></form>');
    pub_page('Wypisz się', '<h1>Wypisać się z wiadomości?</h1><p>Po potwierdzeniu nie otrzymasz od nas więcej e-maili' . ($f ? ' na adres <b>' . h($f['email']) . '</b>' : '') . '.</p>'
        . '<form method="post"><input type="hidden" name="potwierdz" value="1"><button>Tak, wypisz mnie</button></form>');
}

// ---------- FORMULARZ "TAK" ----------
if ($a === 'tak') {
    $C = form_cfg(null, lang());
    $err = '';
    $pre = array();
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $P = function ($k, $max = 255) { return isset($_POST[$k]) ? mb_substr(trim((string)$_POST[$k]), 0, $max) : ''; };
        $tooFast = (time() - (int)$P('ts')) < 3;
        if ($P('www2') !== '' || $tooFast) pub_page($EN ? 'Thank you' : 'Dziękujemy', thanks_body($C, ''));
        $firma = $P('firma'); $osoba = $P('osoba', 120); $tel = $P('telefon', 60); $email = $P('email', 190);
        $czym = $P('czym', 2000); $co = $P('co', 3000); $kiedy = $P('kiedy', 80); $dzien = $P('dzien', 40);
        $pre = array('firma' => $firma, 'osoba' => $osoba, 'telefon' => $tel, 'email' => $email, 'czym' => $czym, 'co' => $co, 'kiedy' => $kiedy, 'dzien' => $dzien);
        $missing = false;
        foreach ($C['pola'] as $k => $pc) if ($pc['show'] && $pc['req'] && trim($pre[$k]) === '') $missing = true;
        if ($missing || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $err = $EN ? 'Please fill in the fields marked * and enter a valid email.' : 'Uzupełnij pola oznaczone * i podaj poprawny e-mail.';
        } else {
            // limit: max 5 zgłoszeń z jednego IP na godzinę
            $n = (int)val('SELECT COUNT(*) FROM zgloszenia WHERE ip=? AND created_at>=?', array($ip, date('Y-m-d H:i:s', time() - 3600)));
            if ($n < 5) {
                add_col('zgloszenia', 'kiedy', "VARCHAR(120) NOT NULL DEFAULT ''");
                // dzień + godzina w jednym polu, np. "Wtorek, Rano (8:00–10:00)"
                $obo = function ($x) { return $x === '' || mb_stripos($x, 'obojętnie') === 0 || mb_stripos($x, 'any ') === 0; };
                if (!$obo($dzien) && !$obo($kiedy)) $kiedy = $dzien . ', ' . $kiedy;
                elseif (!$obo($dzien)) $kiedy = $dzien . ($EN ? ' (any time)' : ' (godzina dowolna)');
                elseif ($obo($kiedy) && $kiedy === '' && $dzien !== '') $kiedy = $dzien;
                q('INSERT INTO zgloszenia(firma_id,firma,osoba,telefon,email,czym,co,kiedy,status,notatka,ip,created_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',
                    array($f ? $f['id'] : null, $firma, $osoba, $tel, $email, $czym, $co, $kiedy, 'nowe', '', $ip, now()));
                if ($f) {
                    q("UPDATE firmy SET status='zainteresowany', zgloszenie_at=?, telefon=IF(telefon='',?,telefon), osoba=IF(osoba='',?,osoba) WHERE id=?", array(now(), $tel, $osoba, $f['id']));
                    logi($f['id'], 'zgloszenie', $osoba . ' ' . $tel);
                } else {
                    logi(null, 'zgloszenie', $firma . ' ' . $tel);
                }
                $branza = $f ? (string)val('SELECT nazwa FROM branze WHERE id=?', array($f['branza_id'])) : '';
                $link = BASE_URL . '/#zgloszenia';
                push('🔥 Zgłoszenie: ' . $firma, trim($osoba . ' · ' . $tel . ($kiedy !== '' ? "\nDzwonić: " . $kiedy : '') . "\n" . $co), $link);
                notify_owner('Nowe zgłoszenie: ' . $firma, array('Firma' => $firma, 'Osoba' => $osoba, 'Telefon' => $tel, 'E-mail' => $email, 'Branża' => $branza, 'Kiedy dzwonić' => $kiedy, 'Czym się zajmują' => $czym, 'Co usprawnić' => $co), $link);
                // potwierdzenie do firmy
                $tpl = tpl_for($f ? $f['branza_id'] : 0, 'potwierdzenie');
                if ($tpl) {
                    list($subj, $html, $text) = render_mail($tpl, array('nazwa' => $firma, 'miasto' => $f ? $f['miasto'] : '', 'osoba' => $osoba), array());
                    send_mail($email, $subj, $html, $text);
                }
            }
            pub_page($C['dzieki_tytul'], thanks_body($C, $email));
        }
    } else {
        // Kliknięcie NIE jest zapisywane tutaj (skanery antywirusowe też "klikają" linki).
        // Zapisuje je dopiero mały skrypt na stronie – uruchamia go prawdziwa przeglądarka po 2 sekundach.
        if ($f) $pre = array('firma' => $f['nazwa'], 'osoba' => $f['osoba'], 'telefon' => $f['telefon'], 'email' => $f['email']);
        $beacon = ($f && !$f['klik_at']) ? '<script>setTimeout(function(){try{var x=new XMLHttpRequest();x.open("POST","p.php?a=k&t=' . $f['token'] . '",true);x.setRequestHeader("Content-Type","application/x-www-form-urlencoded");x.send("h="+(document.hidden?0:1)+"&w="+screen.width);}catch(e){}},2000);</script>' : '';
        pub_page($C['tytul'], form_body($C, $pre, $err) . $beacon);
    }
    pub_page($C['tytul'], form_body($C, $pre, $err));
}

// ---------- WIADOMOŚCI 1:1 – otwarcie i kliknięcie ----------
if ($a === 'wo' || $a === 'wl') {
    $w = (strlen($t) === 32) ? one('SELECT w.*, z.firma FROM wiadomosci w LEFT JOIN zgloszenia z ON z.id=w.zgloszenie_id WHERE w.token=?', array($t)) : null;
    if ($a === 'wo') {
        if ($w && !$isBotUa && (time() - strtotime($w['wyslano_at'])) >= 60) {
            $recent = !empty($w['otwarto_last']) && (time() - strtotime($w['otwarto_last'])) < 3600;
            if (!$recent) {
                q('UPDATE wiadomosci SET otwarcia=otwarcia+1, otwarto_at=COALESCE(otwarto_at,?), otwarto_last=? WHERE id=?', array(now(), now(), $w['id']));
                if (!$w['otwarto_at']) push('Otworzył wiadomość: ' . $w['firma'], $w['temat'], BASE_URL);
            } else q('UPDATE wiadomosci SET otwarto_last=? WHERE id=?', array(now(), $w['id']));
        }
        header('Content-Type: image/gif'); header('Cache-Control: no-store, no-cache, must-revalidate');
        echo base64_decode('R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7'); exit;
    }
    $url = BASE_URL;
    if ($w) {
        foreach ((array)json_decode((string)$w['bloki'], true) as $b) if (isset($b['t']) && $b['t'] === 'przycisk' && !empty($b['url'])) $url = $b['url'];
        if (!$isBotUa && (time() - strtotime($w['wyslano_at'])) >= 60) {
            q('UPDATE wiadomosci SET kliki=kliki+1, klik_at=COALESCE(klik_at,?) WHERE id=?', array(now(), $w['id']));
            if (!$w['klik_at']) push('Kliknął link: ' . $w['firma'], $w['temat'], BASE_URL);
        }
    }
    header('Location: ' . $url, true, 302); exit;
}

// ---------- KLIKNIĘCIE (potwierdzone przez przeglądarkę) ----------
if ($a === 'k') {
    header('Content-Type: text/plain');
    if ($f && !$f['klik_at'] && $_SERVER['REQUEST_METHOD'] === 'POST' && !$isBotUa && (int)(isset($_POST['w']) ? $_POST['w'] : 0) > 0) {
        $ls = last_send($f);
        if ($ls && (time() - $ls) < 60) { logi($f['id'], 'klik_bot', 'zbyt szybko po wysyłce'); exit('ok'); }
        q('UPDATE firmy SET klik_at=?, zgoda_ip=? WHERE id=? AND klik_at IS NULL', array(now(), $ip, $f['id']));
        logi($f['id'], 'klik', 'otworzył formularz, IP ' . $ip);
        push('Kliknięcie: ' . $f['nazwa'], 'Otworzył formularz – jeszcze nie wysłał', BASE_URL);
    }
    exit('ok');
}

http_response_code(404);
pub_page('Nie znaleziono', '<h1>Nie znaleziono strony</h1>');
