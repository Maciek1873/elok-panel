<?php
// ELOK – automatyczne przypomnienia o konsultacjach (wywoływane z cron.php)
//  - dzień wcześniej: mail do klienta z terminem, linkiem do rozmowy i pytaniami na przygotowanie
//  - godzinę wcześniej: mail z linkiem do rozmowy + powiadomienie na Twój telefon
if (!defined('ELOK')) exit;

function kp_mail($to, $subject, $tytul, $tresc, $kiedy, $meet, $tel) {
    $btn = $meet !== '' ? '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:18px 0 6px;"><tr><td style="background:#111111;border-radius:10px;"><a href="' . h($meet) . '" style="display:inline-block;padding:14px 22px;color:#FFFFFF;font-size:15px;font-weight:bold;text-decoration:none;">Dołącz do rozmowy</a></td></tr></table><div style="font-size:13px;color:#77746C;">' . h($meet) . '</div>' : '';
    $html = '<!DOCTYPE html><html lang="pl"><body style="margin:0;background:#F4F3EF;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #E7E4DC;border-radius:14px;"><tr><td style="padding:28px;font-family:Arial,Helvetica,sans-serif;color:#111111;">'
        . '<div style="font-size:22px;font-weight:900;letter-spacing:-0.5px;margin-bottom:18px;">ELOK<span style="color:#B08A3E;">.</span></div>'
        . '<div style="font-size:20px;font-weight:bold;margin-bottom:10px;">' . h($tytul) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F7F4EC;border-radius:12px;margin:8px 0 6px;"><tr><td style="padding:14px 18px;"><div style="font-size:12px;letter-spacing:1px;color:#8B6B30;font-weight:bold;text-transform:uppercase;">Termin</div><div style="font-size:18px;font-weight:bold;">' . h($kiedy) . '</div></td></tr></table>'
        . $btn
        . '<div style="font-size:15px;line-height:1.6;color:#3A3832;margin-top:16px;">' . $tresc . '</div>'
        . '<div style="font-size:14px;line-height:1.6;color:#3A3832;margin-top:16px;">Zmiana terminu: odpisz na tę wiadomość albo zadzwoń: <b>' . h($tel) . '</b>.</div>'
        . '<div style="font-size:14px;color:#3A3832;margin-top:20px;">Do usłyszenia,<br><b>Zespół ELOK</b><br><a href="https://elok-agency.pl" style="color:#8B6B30;">elok-agency.pl</a></div>'
        . '</td></tr></table></td></tr></table></body></html>';
    $txt = $tytul . "\n\nTermin: " . $kiedy . "\n" . ($meet !== '' ? "Link do rozmowy: " . $meet . "\n" : '') . "\n" . strip_tags(str_replace(array('<br>', '</li>'), "\n", $tresc)) . "\n\nZmiana terminu: odpisz lub zadzwoń " . $tel . "\n\nZespół ELOK\nhttps://elok-agency.pl";
    return send_mail($to, $subject, $html, $txt);
}

function kp_key($id) { $k = setting('kp_secret'); if ($k === '') { $k = bin2hex(random_bytes(16)); setting_set('kp_secret', $k); } return substr(hash_hmac('sha256', 'r' . (int)$id, $k), 0, 24); }
function kp_wybor_url($id, $w) { return BASE_URL . '/strona/wybor.php?r=' . (int)$id . '&k=' . kp_key($id) . '&w=' . $w; }
function kp_btns($id) {
    $b = function ($url, $t, $dark) { return '<td style="padding:0 8px 8px 0;"><table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="background:' . ($dark ? '#111111' : '#FFFFFF') . ';border:1px solid #111111;border-radius:10px;"><a href="' . h($url) . '" style="display:inline-block;padding:13px 20px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:' . ($dark ? '#FFFFFF' : '#111111') . ';text-decoration:none;">' . $t . '</a></td></tr></table></td>'; };
    return '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:14px 0 4px;"><tr>' . $b(kp_wybor_url($id, 'meet'), 'Google Meet', true) . $b(kp_wybor_url($id, 'tel'), 'Rozmowa telefoniczna', false) . '</tr></table>';
}
// mail dzień wcześniej: przypomnienie + pytanie, jak woli rozmawiać (Google Meet czy telefon)
function kp_send1($r, $kiedy, $tel) {
    $im = explode(' ', trim($r['imie'])); $im = $im[0];
    $tresc = '<p style="margin:0 0 4px;"><b>Jak woli Pan/Pani porozmawiać?</b> Wystarczy kliknąć jeden przycisk.</p>' . kp_btns($r['id'])
        . '<p style="margin:10px 0 0;color:#6B675E;font-size:14px;">Google Meet działa w przeglądarce, bez instalowania programów. Przy rozmowie telefonicznej zadzwonimy o umówionej godzinie.</p>';
    return kp_mail($r['email'], 'Przypomnienie: rozmowa ' . $kiedy, 'Dzień dobry ' . $im . ', przypominamy o naszej rozmowie.', $tresc, $kiedy, '', $tel);
}

function kons_przyp_run() {
    db()->exec("CREATE TABLE IF NOT EXISTS rezerwacje (id INT AUTO_INCREMENT PRIMARY KEY, termin DATETIME NOT NULL, zgloszenie_id INT NULL, imie VARCHAR(120) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '', telefon VARCHAR(60) NOT NULL DEFAULT '', opis TEXT, status VARCHAR(20) NOT NULL DEFAULT 'aktywna', ip VARCHAR(45) NULL, created_at DATETIME NOT NULL, UNIQUE KEY ut (termin)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    add_col('rezerwacje', 'przyp1', 'TINYINT NOT NULL DEFAULT 0');
    add_col('rezerwacje', 'przyp2', 'TINYINT NOT NULL DEFAULT 0');
    add_col('rezerwacje', 'forma', "VARCHAR(10) NOT NULL DEFAULT ''");
    $meet = trim(setting('kons_link'));
    $tel = setting('podpis_tel') ?: '455 505 820';
    $D = array('niedziela', 'poniedziałek', 'wtorek', 'środa', 'czwartek', 'piątek', 'sobota');
    $M = array(1 => 'stycznia', 'lutego', 'marca', 'kwietnia', 'maja', 'czerwca', 'lipca', 'sierpnia', 'września', 'października', 'listopada', 'grudnia');
    $fmt = function ($ts) use ($D, $M) { return $D[(int)date('w', $ts)] . ', ' . date('j', $ts) . ' ' . $M[(int)date('n', $ts)] . ', godz. ' . date('G:i', $ts); };
    $n1 = 0; $n2 = 0;
    $rows = all("SELECT r.* FROM rezerwacje r JOIN zgloszenia z ON z.id=r.zgloszenie_id WHERE r.status='aktywna' AND z.status<>'odrzucone' AND r.termin>NOW() AND r.termin<=DATE_ADD(NOW(), INTERVAL 26 HOUR)");
    foreach ($rows as $r) {
        $ts = strtotime($r['termin']); $left = $ts - time();
        $im = explode(' ', trim($r['imie'])); $im = $im[0];
        // dzień wcześniej (gdy do rozmowy zostało 3–26 h)
        if (!(int)$r['przyp1'] && $left > 3 * 3600 && time() - strtotime($r['created_at']) < 6 * 3600) { q('UPDATE rezerwacje SET przyp1=1 WHERE id=?', array($r['id'])); }
        elseif (!(int)$r['przyp1'] && $left > 3 * 3600) {
            if (kp_send1($r, $fmt($ts), $tel)) $n1++;
            q('UPDATE rezerwacje SET przyp1=1 WHERE id=?', array($r['id']));
        }
        // godzinę wcześniej
        if (!(int)$r['przyp2'] && $left <= 75 * 60) {
            if ($r['forma'] === 'tel') kp_mail($r['email'], 'Za godzinę: rozmowa z ELOK', 'Dzień dobry ' . $im . ', rozmawiamy za około godzinę.', 'Zadzwonimy o umówionej godzinie' . ($r['telefon'] !== '' ? ' na numer <b>' . h($r['telefon']) . '</b>' : '') . '. Wolisz jednak Google Meet? <a href="' . h(kp_wybor_url($r['id'], 'meet')) . '" style="color:#8B6B30;">Kliknij tutaj</a>.', $fmt($ts), '', $tel);
            elseif ($meet !== '') kp_mail($r['email'], 'Za godzinę: rozmowa z ELOK', 'Dzień dobry ' . $im . ', widzimy się za około godzinę.', 'Wystarczy kliknąć przycisk powyżej o umówionej godzinie. Działa w przeglądarce, bez instalowania programów.', $fmt($ts), $meet, $tel);
            push('Konsultacja za godzinę: ' . $r['imie'], date('G:i', $ts) . ' · ' . ($r['forma'] === 'tel' ? 'TELEFON ' . $r['telefon'] : ($r['forma'] === 'meet' ? 'Google Meet' : 'forma nie wybrana')) . ($r['opis'] ? "\n" . mb_substr($r['opis'], 0, 120) : ''), BASE_URL . '/#www_kons');
            q('UPDATE rezerwacje SET przyp2=1, przyp1=1 WHERE id=?', array($r['id']));
            $n2++;
        }
    }
    return $n1 || $n2 ? ('przypomnienia dzień wcześniej: ' . $n1 . ', godzinę wcześniej: ' . $n2) : '';
}
