<?php
// ELOK – czytanie skrzynki (IMAP): zwroty (adres nie istnieje) i odpowiedzi od firm.
// Własny mini-klient IMAP po SSL (nie wymaga rozszerzenia php-imap). Skrzynka otwierana tylko do odczytu (EXAMINE + BODY.PEEK).
if (!defined('ELOK')) { http_response_code(403); exit; }

function mb_imap_open(&$err) {
    $host = trim(setting('imap_host', 'mail19.lh.pl')) ?: 'mail19.lh.pl';
    $port = (int)setting('imap_port', '993') ?: 993;
    $user = trim(setting('imap_user')) ?: trim(setting('nadawca_email'));
    $pass = (string)setting('imap_pass');
    if ($user === '' || $pass === '') { $err = 'Wpisz login i hasło skrzynki w Ustawieniach.'; return null; }
    $ctx = stream_context_create(array('ssl' => array('verify_peer' => true, 'verify_peer_name' => true, 'SNI_enabled' => true)));
    $fp = @stream_socket_client('ssl://' . $host . ':' . $port, $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    if (!$fp) { // awaryjnie bez weryfikacji nazwy certyfikatu (np. gdy host to alias)
        $ctx = stream_context_create(array('ssl' => array('verify_peer' => false, 'verify_peer_name' => false)));
        $fp = @stream_socket_client('ssl://' . $host . ':' . $port, $en, $es, 15, STREAM_CLIENT_CONNECT, $ctx);
    }
    if (!$fp) { $err = 'Brak połączenia z ' . $host . ':' . $port . ' (' . $es . ')'; return null; }
    stream_set_timeout($fp, 20);
    fgets($fp, 4096); // powitanie
    $c = array('fp' => $fp, 'n' => 0);
    $r = mb_imap_cmd($c, 'LOGIN ' . mb_imap_q($user) . ' ' . mb_imap_q($pass));
    if (!$r['ok']) { fclose($fp); $err = 'Skrzynka odrzuciła logowanie – sprawdź login i hasło.'; return null; }
    $r = mb_imap_cmd($c, 'EXAMINE INBOX');
    if (!$r['ok']) { fclose($fp); $err = 'Nie mogę otworzyć skrzynki odbiorczej.'; return null; }
    return $c;
}
function mb_imap_q($s) { return '"' . str_replace(array('\\', '"'), array('\\\\', '\\"'), $s) . '"'; }
// wysyła komendę, zwraca linie i literały {n}
function mb_imap_cmd(&$c, $cmd) {
    $tag = 'A' . (++$c['n']);
    fwrite($c['fp'], $tag . ' ' . $cmd . "\r\n");
    $lines = array(); $lits = array(); $ok = false;
    while (true) {
        $l = fgets($c['fp'], 65536);
        if ($l === false) break;
        if (preg_match('/\{(\d+)\}\r\n$/', $l, $m)) {
            $n = (int)$m[1]; $buf = '';
            while (strlen($buf) < $n) { $chunk = fread($c['fp'], min(65536, $n - strlen($buf))); if ($chunk === false || $chunk === '') break; $buf .= $chunk; }
            $lits[] = $buf; $lines[] = $l; continue;
        }
        $lines[] = $l;
        if (strpos($l, $tag . ' ') === 0) { $ok = (bool)preg_match('/^' . $tag . ' OK/i', $l); break; }
    }
    return array('ok' => $ok, 'lines' => $lines, 'lits' => $lits);
}
function mb_imap_close(&$c) { if ($c) { @mb_imap_cmd($c, 'LOGOUT'); @fclose($c['fp']); } }

function mb_hdr($headers, $name) {
    $h = preg_replace("/\r?\n[ \t]+/", ' ', $headers);
    if (preg_match('/^' . preg_quote($name, '/') . ':\s*(.*)$/mi', $h, $m)) return trim($m[1]);
    return '';
}
function mb_decode_hdr($s) { return function_exists('iconv_mime_decode') ? (string)@iconv_mime_decode($s, ICONV_MIME_DECODE_CONTINUE_ON_ERROR, 'UTF-8') : $s; }
function mb_addr($s) { return preg_match('/[A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,}/i', $s, $m) ? mb_strtolower($m[0]) : ''; }

// Główna funkcja: przegląda nowe wiadomości, oznacza zwroty i odpowiedzi. Zwraca statystyki.
function mb_check($budget = 25) {
    $res = array('przejrzane' => 0, 'zwroty' => 0, 'odpowiedzi' => 0, 'auto' => 0, 'blad' => '');
    add_col('firmy', 'odpisal_at', 'DATETIME NULL');
    if ((int)val("SELECT GET_LOCK('elok_imap', 0)") !== 1) { $res['blad'] = 'Sprawdzanie już trwa'; return $res; }
    $t0 = microtime(true);
    $err = '';
    $c = mb_imap_open($err);
    if (!$c) { val("SELECT RELEASE_LOCK('elok_imap')"); $res['blad'] = $err; setting_set('imap_info', date('Y-m-d H:i') . ' · ' . $err); return $res; }
    $last = (int)setting('imap_last_uid', '0');
    if ($last > 0) {
        $r = mb_imap_cmd($c, 'UID SEARCH UID ' . ($last + 1) . ':*');
    } else { // pierwszy raz – od dnia pierwszej wysyłki
        $od = (string)val('SELECT MIN(wyslano_at) FROM firmy');
        $d = $od ? strtotime($od) : time() - 86400;
        $r = mb_imap_cmd($c, 'UID SEARCH SINCE ' . date('d-M-Y', $d));
    }
    $uids = array();
    foreach ($r['lines'] as $l) if (stripos($l, '* SEARCH') === 0) foreach (preg_split('/\s+/', trim(substr($l, 8))) as $u) if (ctype_digit($u) && (int)$u > $last) $uids[] = (int)$u;
    sort($uids);
    $self = array_filter(array(mb_strtolower(trim(setting('nadawca_email'))), mb_strtolower(trim(setting('imap_user'))), mb_strtolower(trim(setting('reply_to')))));
    foreach ($uids as $uid) {
        if (microtime(true) - $t0 > $budget) break;
        $f = mb_imap_cmd($c, 'UID FETCH ' . $uid . ' (BODY.PEEK[HEADER] BODY.PEEK[TEXT]<0.40000>)');
        $hdr = isset($f['lits'][0]) ? $f['lits'][0] : '';
        $body = isset($f['lits'][1]) ? $f['lits'][1] : '';
        $res['przejrzane']++;
        mb_handle($hdr, $body, $self, $res);
        setting_set('imap_last_uid', (string)$uid);
    }
    mb_imap_close($c);
    val("SELECT RELEASE_LOCK('elok_imap')");
    setting_set('imap_info', date('Y-m-d H:i') . ' · przejrzano ' . $res['przejrzane'] . ', zwroty ' . $res['zwroty'] . ', odpowiedzi ' . $res['odpowiedzi']);
    return $res;
}

function mb_handle($hdr, $body, $self, &$res) {
    $from = mb_strtolower(mb_hdr($hdr, 'From'));
    $subj = mb_decode_hdr(mb_hdr($hdr, 'Subject'));
    $ctype = mb_strtolower(mb_hdr($hdr, 'Content-Type'));
    $isBounce = strpos($ctype, 'report-type=delivery-status') !== false
        || preg_match('/mailer-daemon|postmaster@|mail delivery (system|subsystem)/i', $from)
        || preg_match('/undeliver|delivery status notification|delivery fail|returned mail|niedostarcz|nie mo[żz]na dostarczy|failure notice/i', $subj);
    if ($isBounce) {
        // tymczasowe opóźnienia pomijamy – liczą się tylko trwałe błędy (5.x.x)
        if (preg_match('/Action:\s*delayed/i', $body) || preg_match('/delay|warning|op[óo][źz]nion/i', $subj)) return;
        $hard = preg_match('/Status:\s*5\.\d+\.\d+|Action:\s*failed|\b55[0-4]\b|\b5\.\d\.\d\b|user unknown|does not exist|no such user|mailbox unavailable|address rejected|nie istnieje|mailbox not found|invalid recipient/i', $body);
        if (!$hard) return;
        $cand = array();
        if (preg_match_all('/(?:Final|Original)-Recipient:\s*rfc822;\s*<?([^\s>;]+)/i', $body, $m)) foreach ($m[1] as $a) $cand[] = mb_strtolower($a);
        if (preg_match_all('/<([A-Z0-9._%+\-]+@[A-Z0-9.\-]+\.[A-Z]{2,})>:\s/i', $body, $m)) foreach ($m[1] as $a) $cand[] = mb_strtolower($a);
        if (!$cand && preg_match_all('/^To:\s*(.+)$/mi', $body, $m)) foreach ($m[1] as $a) { $x = mb_addr($a); if ($x) $cand[] = $x; }
        $done = array();
        foreach (array_unique($cand) as $a) {
            if (in_array($a, $self, true) || isset($done[$a])) continue;
            $fi = one("SELECT id, status FROM firmy WHERE email=? AND wyslano_at IS NOT NULL", array($a));
            if (!$fi || $fi['status'] === 'zwrot') continue;
            q("UPDATE firmy SET status='zwrot', notatka=TRIM(CONCAT(COALESCE(notatka,''), ?)) WHERE id=?", array("\nZwrot " . date('d.m') . ': adres nie istnieje / skrzynka odrzuciła maila.', $fi['id']));
            logi($fi['id'], 'zwrot', $a);
            $done[$a] = 1; $res['zwroty']++;
        }
        return;
    }
    // odpowiedź od firmy?
    $addr = mb_addr($from);
    if ($addr === '' || in_array($addr, $self, true)) return;
    $fi = one("SELECT id, nazwa, status FROM firmy WHERE email=? AND wyslano_at IS NOT NULL", array($addr));
    if (!$fi) return;
    $auto = mb_hdr($hdr, 'Auto-Submitted');
    $isAuto = ($auto !== '' && stripos($auto, 'no') !== 0) || mb_hdr($hdr, 'X-Autoreply') !== '' || mb_hdr($hdr, 'X-Autorespond') !== ''
        || preg_match('/auto_reply|bulk|junk/i', mb_hdr($hdr, 'Precedence'))
        || preg_match('/auto.?reply|automatyczn|autoodpowied|nieobecn|out of office|urlop|vacation|potwierdzenie (otrzymania|przyjęcia)|zgłoszenie nr|ticket|\bNR-\d+/iu', $subj);
    if ($isAuto) { logi($fi['id'], 'autoodp', mb_substr($subj, 0, 200)); $res['auto']++; return; }
    if (in_array($fi['status'], array('wypisany'), true)) return;
    q('UPDATE firmy SET odpisal_at=COALESCE(odpisal_at, ?) WHERE id=?', array(now(), $fi['id']));
    logi($fi['id'], 'odpowiedz', mb_substr($subj, 0, 200));
    $res['odpowiedzi']++;
    push('💬 Odpisał: ' . $fi['nazwa'], ($subj !== '' ? $subj . "\n" : '') . 'Sprawdź skrzynkę ' . setting('nadawca_email') . ' i oznacz firmę w panelu.', BASE_URL);
}
