<?php
// ELOK – klient wybiera formę rozmowy (link z maila z przypomnieniem): Google Meet albo telefon.
// Zapis w rezerwacji i w zgłoszeniu + powiadomienie na Twój telefon. Strona dla klienta, bez logowania.
define('ELOK', 1);
require __DIR__ . '/../lib.php';
require_once __DIR__ . '/przypomnienia.php';
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex');

function wb_page($tytul, $tresc) {
    echo '<!doctype html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex"><title>ELOK · ' . h($tytul) . '</title>'
        . '<style>body{margin:0;background:#F4F3EF;font-family:Arial,Helvetica,sans-serif;color:#17140E}.c{max-width:520px;margin:40px auto;padding:0 16px}.b{background:#fff;border:1px solid #E7E4DC;border-radius:16px;padding:30px}'
        . '.lg{font-size:24px;font-weight:900;letter-spacing:-.5px;margin-bottom:20px}.lg span{color:#B08A3E}h1{font-size:22px;margin:0 0 10px}p{font-size:16px;line-height:1.6;color:#3A3832;margin:0 0 14px}'
        . '.btn{display:inline-block;background:#111;color:#fff;text-decoration:none;font-weight:bold;border-radius:10px;padding:14px 22px;border:0;font-size:16px;cursor:pointer}.btn.l{background:#fff;color:#111;border:1px solid #111}'
        . 'input{display:block;width:100%;box-sizing:border-box;font-size:17px;padding:13px 14px;border:1px solid #D6CAB4;border-radius:10px;margin:0 0 12px}.t{background:#F7F4EC;border-radius:12px;padding:14px 18px;margin:0 0 16px}.t small{display:block;font-size:12px;letter-spacing:1px;color:#8B6B30;text-transform:uppercase}.t b{font-size:17px}</style></head>'
        . '<body><div class="c"><div class="b"><div class="lg">ELOK<span>.</span></div><h1>' . h($tytul) . '</h1>' . $tresc . '</div></div></body></html>';
    exit;
}

$id = (int)(isset($_REQUEST['r']) ? $_REQUEST['r'] : 0);
$k = (string)(isset($_REQUEST['k']) ? $_REQUEST['k'] : '');
$w = (string)(isset($_REQUEST['w']) ? $_REQUEST['w'] : '');
add_col('rezerwacje', 'forma', "VARCHAR(10) NOT NULL DEFAULT ''");
$r = $id ? one('SELECT * FROM rezerwacje WHERE id=?', array($id)) : null;
if (!$r || !hash_equals(kp_key($id), $k)) wb_page('Link jest nieaktualny', '<p>Ten link nie działa. Napisz do nas albo zadzwoń: <b>455 505 820</b>.</p>');

$D = array('niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota');
$M = array(1 => 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia');
$ts = strtotime($r['termin']);
$kiedy = $D[(int)date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $M[(int)date('n', $ts)] . ', godz. ' . date('G:i', $ts);
$box = '<div class="t"><small>Termin rozmowy</small><b>' . h($kiedy) . '</b></div>';
$self = 'wybor.php?r=' . $id . '&k=' . urlencode($k);

// zapis wyboru (jeden raz na zmianę, bez powtarzania powiadomień przy ponownym kliknięciu)
function wb_zapisz($r, $forma, $tel, $kiedy) {
    $zmiana = $r['forma'] !== $forma || ($tel !== '' && $tel !== $r['telefon']);
    if (!$zmiana) return;
    q('UPDATE rezerwacje SET forma=?' . ($tel !== '' ? ', telefon=?' : '') . ' WHERE id=?', $tel !== '' ? array($forma, $tel, $r['id']) : array($forma, $r['id']));
    $opis = $forma === 'tel' ? 'telefon' . ($tel !== '' ? ' ' . $tel : ($r['telefon'] !== '' ? ' ' . $r['telefon'] : '')) : 'Google Meet';
    if ($r['zgloszenie_id']) {
        $z = one('SELECT notatka, telefon FROM zgloszenia WHERE id=?', array($r['zgloszenie_id']));
        if ($z) q('UPDATE zgloszenia SET notatka=?' . ($tel !== '' && $z['telefon'] === '' ? ', telefon=?' : '') . ' WHERE id=?',
            $tel !== '' && $z['telefon'] === '' ? array(trim($z['notatka'] . "\n" . date('d.m H:i') . ' – klient wybrał: ' . $opis), $tel, $r['zgloszenie_id']) : array(trim($z['notatka'] . "\n" . date('d.m H:i') . ' – klient wybrał: ' . $opis), $r['zgloszenie_id']));
    }
    push('Forma rozmowy: ' . $opis, $r['imie'] . ' · ' . $kiedy, BASE_URL . '/#www_kons');
}

$meet = trim(setting('kons_link'));
if ($w === 'meet') {
    wb_zapisz($r, 'meet', '', $kiedy);
    wb_page('Dziękujemy, rozmawiamy przez Google Meet', $box . '<p>Godzinę przed rozmową wyślemy maila z przyciskiem do dołączenia. Kamera nie jest potrzebna, wystarczy mikrofon.</p>'
        . ($meet !== '' ? '<p>Link do rozmowy możesz też zapisać już teraz:</p><p><a class="btn" href="' . h($meet) . '">Link do Google Meet</a></p>' : '')
        . '<p style="font-size:14px;color:#6B675E">Wolisz jednak telefon? <a href="' . h($self) . '&w=tel" style="color:#8B6B30">Kliknij tutaj</a>.</p>');
}
if ($w === 'tel') {
    $nr = isset($_POST['tel']) ? mb_substr(trim(preg_replace('/[^0-9+ ()-]/', '', (string)$_POST['tel'])), 0, 30) : '';
    if ($nr !== '' && strlen(preg_replace('/\D/', '', $nr)) >= 9) {
        wb_zapisz($r, 'tel', $nr, $kiedy);
        wb_page('Dziękujemy, zadzwonimy', $box . '<p>Zadzwonimy na numer <b>' . h($nr) . '</b> o umówionej godzinie.</p>');
    }
    if ($r['telefon'] !== '' && !isset($_GET['zmien'])) {
        wb_zapisz($r, 'tel', '', $kiedy);
        wb_page('Dziękujemy, zadzwonimy', $box . '<p>Zadzwonimy na numer <b>' . h($r['telefon']) . '</b> o umówionej godzinie.</p><p style="font-size:14px;color:#6B675E"><a href="' . h($self) . '&w=tel&zmien=1" style="color:#8B6B30">Podaj inny numer</a> · <a href="' . h($self) . '&w=meet" style="color:#8B6B30">wolę Google Meet</a></p>');
    }
    wb_page('Pod jaki numer zadzwonić?', $box . ($nr !== '' ? '<p style="color:#9E2F1C">Sprawdź numer – powinien mieć co najmniej 9 cyfr.</p>' : '')
        . '<form method="post" action="' . h($self) . '&w=tel"><input name="tel" type="tel" inputmode="tel" autocomplete="tel" placeholder="np. 600 123 456" value="' . h($r['telefon']) . '" required><button class="btn" type="submit">Zapisz numer</button></form>');
}
wb_page('Jak wolisz porozmawiać?', $box . '<p><a class="btn" href="' . h($self) . '&w=meet">Google Meet</a> &nbsp; <a class="btn l" href="' . h($self) . '&w=tel">Telefon</a></p>');
