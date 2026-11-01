<?php
// ELOK – odbiór formularzy ze strony elok-agency.pl (rezerwacja konsultacji + kontakt).
// Adres: https://panel.elok-agency.pl/strona/
//   GET  ?a=zajete              -> zajęte terminy (najbliższe 70 dni)
//   POST a=rezerwuj             -> rezerwacja konsultacji (zapis w Zgłoszeniach, push, mail do Ciebie i do klienta)
//   POST a=kontakt              -> zwykłe zapytanie
//   POST a=ev                   -> statystyki strony (wejścia, kliknięcia) – podgląd w /strona/admin.php
define('ELOK', 1);
require __DIR__ . '/../lib.php';

// Ustawienia konsultacji zmieniasz w panelu: /strona/admin.php -> Konsultacje
$KONS_LINK = trim(setting('kons_link'));
$KONS_WOLNE = array_values(array_filter(array_map('trim', explode(',', setting('kons_wolne', '2026-11-11,2026-12-24,2026-12-25,2026-12-26,2026-12-31,2027-01-01,2027-01-06')))));

$ORIG = array('https://elok-agency.pl', 'https://www.elok-agency.pl');
$o = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
if (in_array($o, $ORIG, true) || (defined('MAIL_DRY_RUN') && MAIL_DRY_RUN)) {
    header('Access-Control-Allow-Origin: ' . ($o ?: '*'));
    header('Vary: Origin');
}
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { header('Access-Control-Allow-Methods: GET, POST'); http_response_code(204); exit; }

function st_ok($a = array()) { $a['ok'] = true; echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
function st_err($m, $code = 400) { http_response_code($code); echo json_encode(array('ok' => false, 'error' => $m), JSON_UNESCAPED_UNICODE); exit; }
function st_p($k, $max = 190) { return isset($_POST[$k]) ? mb_substr(trim(str_replace(array("\r", "\0"), '', (string)$_POST[$k])), 0, $max) : ''; }

function st_migrate() {
    db()->exec("CREATE TABLE IF NOT EXISTS rezerwacje (id INT AUTO_INCREMENT PRIMARY KEY, termin DATETIME NOT NULL, zgloszenie_id INT NULL, imie VARCHAR(120) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '', telefon VARCHAR(60) NOT NULL DEFAULT '', opis TEXT, status VARCHAR(20) NOT NULL DEFAULT 'aktywna', ip VARCHAR(45) NULL, created_at DATETIME NOT NULL, UNIQUE KEY ut (termin)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    add_col('zgloszenia', 'zrodlo', "VARCHAR(30) NOT NULL DEFAULT ''");
    add_col('zgloszenia', 'kiedy', "VARCHAR(120) NOT NULL DEFAULT ''");
    add_col('zgloszenia', 'kontakt_data', 'DATE NULL');
    add_col('zgloszenia', 'kontakt_co', "VARCHAR(255) NOT NULL DEFAULT ''");
}

$DNI = array('niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota');
$MIES = array(1 => 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia');
function st_fmt($ts) { global $DNI, $MIES; return $DNI[(int)date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $MIES[(int)date('n', $ts)] . ', godz. ' . date('G:i', $ts); }

$a = isset($_REQUEST['a']) ? (string)$_REQUEST['a'] : '';
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';

// ---------- statystyki strony (bez cookies, bez IP) ----------
function st_log_migrate() {
    db()->exec("CREATE TABLE IF NOT EXISTS strona_log (id INT AUTO_INCREMENT PRIMARY KEY, t DATETIME NOT NULL, ev VARCHAR(30) NOT NULL, path VARCHAR(120) NOT NULL DEFAULT '', sid VARCHAR(40) NOT NULL DEFAULT '', src VARCHAR(60) NOT NULL DEFAULT '', medium VARCHAR(60) NOT NULL DEFAULT '', kampania VARCHAR(80) NOT NULL DEFAULT '', ref VARCHAR(120) NOT NULL DEFAULT '', wejscie VARCHAR(120) NOT NULL DEFAULT '', urzadzenie VARCHAR(12) NOT NULL DEFAULT '', x VARCHAR(80) NOT NULL DEFAULT '', KEY te (t, ev), KEY sd (sid)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
if ($a === 'ev') {
    $ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
    $ev = preg_replace('/[^a-z0-9_]/', '', st_p('ev', 30));
    $okEv = array('wejscie', 'telefon', 'mail', 'klik_konsultacja', 'demo', 'kopiuj', 'social', 'przewiniecie_75', 'rezerwacja');
    if ($ua !== '' && !preg_match('/bot|crawl|spider|slurp|headless|preview|lighthouse|python|curl|wget|facebookexternalhit|monitor|scan/i', $ua) && in_array($ev, $okEv, true)) {
        $sid = preg_replace('/[^a-z0-9]/', '', st_p('sid', 40));
        if (strlen($sid) >= 8) {
            st_log_migrate();
            $path = preg_replace('#[^a-z0-9/._-]#i', '', st_p('p', 120)); if ($path === '' || $path === '/') $path = '/index.html';
            $wej = preg_replace('#[^a-z0-9/._-]#i', '', st_p('l', 120)); if ($wej === '/') $wej = '/index.html';
            $dup = $ev === 'wejscie' ? (int)val('SELECT COUNT(*) FROM strona_log WHERE sid=? AND ev=? AND path=? AND t>=?', array($sid, $ev, $path, date('Y-m-d H:i:s', time() - 20))) : 0;
            if (!$dup && (int)val('SELECT COUNT(*) FROM strona_log WHERE sid=? AND t>=?', array($sid, date('Y-m-d H:i:s', time() - 3600))) < 300) {
                $dev = st_p('d', 12); if (!in_array($dev, array('telefon', 'tablet', 'komputer'), true)) $dev = '';
                q('INSERT INTO strona_log(t,ev,path,sid,src,medium,kampania,ref,wejscie,urzadzenie,x) VALUES(?,?,?,?,?,?,?,?,?,?,?)',
                    array(now(), $ev, $path, $sid, mb_strtolower(st_p('s', 60)), mb_strtolower(st_p('m', 60)), st_p('c', 80), mb_strtolower(st_p('r', 120)), $wej, $dev, st_p('x', 80)));
                if (mt_rand(1, 500) === 1) q('DELETE FROM strona_log WHERE t < ?', array(date('Y-m-d H:i:s', time() - 400 * 86400)));
            }
        }
    }
    http_response_code(204); exit;
}

// ---------- zajęte terminy ----------
if ($a === 'zajete') {
    st_migrate();
    // termin zwalnia się sam, gdy w panelu usuniesz zgłoszenie albo dasz mu status „Odrzucone”
    $rows = all("SELECT r.termin FROM rezerwacje r JOIN zgloszenia z ON z.id=r.zgloszenie_id WHERE r.status='aktywna' AND z.status<>'odrzucone' AND r.termin>=NOW() AND r.termin<=DATE_ADD(NOW(), INTERVAL 70 DAY)");
    $out = array();
    foreach ($rows as $r) $out[] = date('Y-m-d G:i', strtotime($r['termin']));
    st_ok(array('zajete' => $out, 'wolne_dni' => $KONS_WOLNE));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') st_err('Zła metoda', 405);

// ochrona przed botami: ukryte pole + minimum 3 s na wypełnienie
$bot = st_p('www2') !== '' || (time() - (int)st_p('ts')) < 3;
if ($bot) st_ok(array('termin' => ''));

$imie = st_p('imie', 120);
$email = st_p('email', 190);
$tel = st_p('telefon', 60);
$opis = st_p('opis', 3000);
if ($imie === '') st_err('Wpisz imię i nazwisko.');
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) st_err('Wpisz poprawny adres e-mail.');

st_migrate();
$n = (int)val('SELECT COUNT(*) FROM zgloszenia WHERE ip=? AND created_at>=?', array($ip, date('Y-m-d H:i:s', time() - 3600)));
if ($n >= 5) st_err('Za dużo zgłoszeń z tego adresu. Zadzwoń: 455 505 820.', 429);
$link = BASE_URL . '/#zgloszenia';

// ---------- zwykłe zapytanie ----------
if ($a === 'kontakt') {
    q('INSERT INTO zgloszenia(firma_id,firma,osoba,telefon,email,czym,co,status,notatka,ip,created_at,zrodlo) VALUES(NULL,?,?,?,?,?,?,?,?,?,?,?)',
        array(st_p('firma', 255) ?: $imie, $imie, $tel, $email, '', $opis, 'nowe', 'Źródło: formularz na elok-agency.pl', $ip, now(), 'strona'));
    logi(null, 'zgloszenie', 'Strona: ' . $imie . ' ' . $email);
    push('Zapytanie ze strony: ' . $imie, trim($email . "\n" . mb_substr($opis, 0, 120)), $link);
    notify_owner('Nowe zapytanie ze strony: ' . $imie, array('Imię' => $imie, 'E-mail' => $email, 'Telefon' => $tel, 'Wiadomość' => $opis), $link);
    st_ok();
}

if ($a !== 'rezerwuj') st_err('Nieznana akcja');

// ---------- rezerwacja ----------
$data = st_p('data', 10); $godz = st_p('godz', 5);
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $data) || !preg_match('/^(\d{1,2}):(00|30)$/', $godz, $gm)) st_err('Wybierz dzień i godzinę.');
$h = (int)$gm[1];
$ts = strtotime($data . ' ' . sprintf('%02d:%s:00', $h, $gm[2]));
$w = (int)date('w', $ts);
if (!$ts || $w === 0 || $w === 6 || $h < 9 || $h > 16) st_err('Ten termin jest niedostępny. Wybierz inny.');
if ($ts < time() + 3600 || $ts > time() + 62 * 86400) st_err('Ten termin jest niedostępny. Wybierz inny.');
if (in_array($data, $KONS_WOLNE, true)) st_err('W tym dniu nie prowadzimy konsultacji. Wybierz inny dzień.');
$termin = date('Y-m-d H:i:s', $ts);
$kiedy = st_fmt($ts);

// zwolnij termin po anulowanej rezerwacji (zgłoszenie usunięte lub odrzucone)
q("DELETE r FROM rezerwacje r LEFT JOIN zgloszenia z ON z.id=r.zgloszenie_id WHERE r.termin=? AND r.created_at<? AND (z.id IS NULL OR z.status='odrzucone')", array($termin, date('Y-m-d H:i:s', time() - 120)));
try {
    q("INSERT INTO rezerwacje(termin,imie,email,telefon,opis,status,ip,created_at) VALUES(?,?,?,?,?,'aktywna',?,?)", array($termin, $imie, $email, $tel, $opis, $ip, now()));
} catch (Exception $e) {
    st_err('Ktoś właśnie zajął ten termin. Wybierz inną godzinę.', 409);
}
$rid = (int)db()->lastInsertId();
q('INSERT INTO zgloszenia(firma_id,firma,osoba,telefon,email,czym,co,status,notatka,ip,created_at,zrodlo,kiedy,kontakt_data,kontakt_co) VALUES(NULL,?,?,?,?,?,?,?,?,?,?,?,?,?,?)',
    array($imie, $imie, $tel, $email, '', $opis, 'umowione', 'Bezpłatna konsultacja online: ' . $kiedy . "\nŹródło: elok-agency.pl/konsultacja", $ip, now(), 'konsultacja', $kiedy, $data, 'Konsultacja online ' . date('G:i', $ts)));
$zid = (int)db()->lastInsertId();
q('UPDATE rezerwacje SET zgloszenie_id=? WHERE id=?', array($zid, $rid));
logi(null, 'zgloszenie', 'Konsultacja: ' . $imie . ' ' . $kiedy);

push('Nowa konsultacja: ' . $imie, $kiedy . "\n" . $email . ($tel !== '' ? ' · ' . $tel : ''), $link);
notify_owner('Nowa konsultacja: ' . $kiedy, array('Termin' => $kiedy, 'Imię' => $imie, 'E-mail' => $email, 'Telefon' => $tel, 'Temat' => $opis), $link);

// potwierdzenie dla klienta
$meet = trim($KONS_LINK);
$tel_firmy = setting('podpis_tel') ?: '455 505 820';
$gcal = 'https://calendar.google.com/calendar/render?action=TEMPLATE&text=' . rawurlencode('Konsultacja z ELOK')
    . '&dates=' . gmdate('Ymd\THis\Z', $ts) . '/' . gmdate('Ymd\THis\Z', $ts + 1800)
    . '&details=' . rawurlencode('Bezpłatna konsultacja online z ELOK (30 min).' . ($meet !== '' ? "\nLink: " . $meet : ''))
    . ($meet !== '' ? '&location=' . rawurlencode($meet) : '');
$btn = function ($href, $txt, $dark) {
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 8px 8px 0;display:inline-table;"><tr><td style="background:' . ($dark ? '#111111' : '#FFFFFF') . ';border:1px solid #111111;border-radius:10px;"><a href="' . h($href) . '" style="display:inline-block;padding:13px 20px;color:' . ($dark ? '#FFFFFF' : '#111111') . ';font-size:14px;font-weight:bold;text-decoration:none;">' . h($txt) . '</a></td></tr></table>';
};
$im = explode(' ', $imie); $im = $im[0];
$html = '<!DOCTYPE html><html lang="pl"><body style="margin:0;background:#F4F3EF;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #E7E4DC;border-radius:14px;"><tr><td style="padding:28px;font-family:Arial,Helvetica,sans-serif;color:#111111;">'
    . '<div style="font-size:22px;font-weight:900;letter-spacing:-0.5px;margin-bottom:18px;">ELOK<span style="color:#B08A3E;">.</span></div>'
    . '<div style="font-size:20px;font-weight:bold;margin-bottom:10px;">Dzień dobry ' . h($im) . ', termin potwierdzony.</div>'
    . '<div style="font-size:15px;line-height:1.6;color:#3A3832;margin-bottom:18px;">Dziękujemy za rezerwację bezpłatnej konsultacji online (30 minut).</div>'
    . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F4EC;border-radius:12px;margin-bottom:20px;"><tr><td style="padding:16px 18px;font-size:15px;line-height:1.6;">'
    . '<div style="font-size:12px;letter-spacing:1px;color:#8B6B30;font-weight:bold;text-transform:uppercase;">Termin</div><div style="font-size:18px;font-weight:bold;">' . h($kiedy) . '</div>'
    . ($meet !== '' ? '<div style="margin-top:8px;">Link do rozmowy: <a href="' . h($meet) . '" style="color:#111111;font-weight:bold;">' . h($meet) . '</a></div>' : '<div style="margin-top:8px;color:#3A3832;">Link do rozmowy wyślemy na ten adres przed spotkaniem.</div>')
    . '</td></tr></table>'
    . ($meet !== '' ? $btn($meet, 'Dołącz do rozmowy', true) : '') . $btn($gcal, 'Dodaj do kalendarza Google', $meet === '')
    . '<div style="font-size:14px;line-height:1.6;color:#3A3832;margin-top:14px;">Żeby rozmowa była konkretna, możesz odpisać na tego maila i napisać w 2–3 zdaniach, co dziś zajmuje Wam najwięcej czasu.</div>'
    . '<div style="font-size:14px;line-height:1.6;color:#3A3832;margin-top:14px;">Chcesz zmienić termin? Odpisz na tę wiadomość albo zadzwoń: <a href="tel:' . h(preg_replace('/[^0-9+]/', '', $tel_firmy)) . '" style="color:#111111;font-weight:bold;">' . h($tel_firmy) . '</a>.</div>'
    . '<div style="font-size:14px;color:#3A3832;margin-top:22px;">Do usłyszenia,<br><b>Zespół ELOK</b><br><a href="https://elok-agency.pl" style="color:#8B6B30;">elok-agency.pl</a></div>'
    . '</td></tr></table></td></tr></table></body></html>';
$txt = "Dzień dobry $im,\n\ntermin bezpłatnej konsultacji online (30 min) potwierdzony:\n$kiedy\n\n"
    . ($meet !== '' ? "Link do rozmowy: $meet\n\n" : "Link do rozmowy wyślemy przed spotkaniem.\n\n")
    . "Dodaj do kalendarza: $gcal\n\nZmiana terminu: odpisz na tę wiadomość lub zadzwoń $tel_firmy.\n\nZespół ELOK\nhttps://elok-agency.pl";
send_mail($email, 'Potwierdzenie konsultacji: ' . $kiedy, $html, $txt);

st_ok(array('termin' => $kiedy));
