<?php
// ELOK PANEL – wspólne funkcje (PHP 7.4)
if (!defined('ELOK')) define('ELOK', 1);
// Wersja testowa (test-panel.elok-agency.pl): ta sama baza co prawdziwy panel, config.php brany z panelu głównego.
$__host = isset($_SERVER['HTTP_HOST']) ? strtolower((string)$_SERVER['HTTP_HOST']) : '';
if (!defined('ELOK_TEST')) define('ELOK_TEST', strpos($__host, 'test-panel.') === 0 || basename(__DIR__) === 'test-panel.elok-agency.pl');
if (ELOK_TEST && !defined('BASE_URL')) define('BASE_URL', 'https://test-panel.elok-agency.pl');
if (is_file(__DIR__ . '/config.php')) { if (ELOK_TEST) @require_once __DIR__ . '/config.php'; else require_once __DIR__ . '/config.php'; }
else @require_once dirname(__DIR__) . '/panel.elok-agency.pl/config.php';
date_default_timezone_set('Europe/Warsaw');
mb_internal_encoding('UTF-8');
error_reporting(E_ALL);
ini_set('display_errors', '0');

// ---------- BAZA ----------
function db() {
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4', DB_USER, DB_PASS, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ));
        $pdo->exec("SET time_zone='" . date('P') . "'");
    }
    return $pdo;
}
function q($sql, $p = array()) { $s = db()->prepare($sql); $s->execute($p); return $s; }
function one($sql, $p = array()) { $r = q($sql, $p)->fetch(); return $r ? $r : null; }
function all($sql, $p = array()) { return q($sql, $p)->fetchAll(); }
function val($sql, $p = array()) { return q($sql, $p)->fetchColumn(); }
function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
function now() { return date('Y-m-d H:i:s'); }
function token() { return bin2hex(random_bytes(16)); }

// ---------- USTAWIENIA ----------
$GLOBALS['_ST'] = null;
function setting($k, $def = '') {
    if ($GLOBALS['_ST'] === null) {
        $GLOBALS['_ST'] = array();
        foreach (all('SELECT k,v FROM ustawienia') as $r) $GLOBALS['_ST'][$r['k']] = $r['v'];
    }
    return array_key_exists($k, $GLOBALS['_ST']) ? $GLOBALS['_ST'][$k] : $def;
}
function setting_set($k, $v) {
    q('INSERT INTO ustawienia(k,v) VALUES(?,?) ON DUPLICATE KEY UPDATE v=VALUES(v)', array($k, (string)$v));
    $GLOBALS['_ST'] = null;
}

function logi($firma_id, $typ, $info = '') {
    q('INSERT INTO log(firma_id,typ,info,created_at) VALUES(?,?,?,?)', array($firma_id ?: null, $typ, mb_substr($info, 0, 250), now()));
}

// ---------- DNI ROBOCZE (PL) ----------
function easter_ts($y) {
    $a = $y % 19; $b = intdiv($y, 100); $c = $y % 100; $d = intdiv($b, 4); $e = $b % 4;
    $f = intdiv($b + 8, 25); $g = intdiv($b - $f + 1, 3); $h = (19 * $a + $b - $d - $g + 15) % 30;
    $i = intdiv($c, 4); $k = $c % 4; $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
    $m = intdiv($a + 11 * $h + 22 * $l, 451);
    $mo = intdiv($h + $l - 7 * $m + 114, 31); $da = (($h + $l - 7 * $m + 114) % 31) + 1;
    return mktime(12, 0, 0, $mo, $da, $y);
}
function is_workday($ts = null) {
    if ($ts === null) $ts = time();
    if ((int)date('N', $ts) > 5) return false;
    $md = date('m-d', $ts);
    $fixed = array('01-01', '01-06', '05-01', '05-03', '08-15', '11-01', '11-11', '12-24', '12-25', '12-26');
    if (in_array($md, $fixed, true)) return false;
    $e = easter_ts((int)date('Y', $ts));
    $d = date('Y-m-d', $ts);
    if ($d === date('Y-m-d', $e + 86400) || $d === date('Y-m-d', $e + 60 * 86400)) return false; // Pon. Wielkanocny, Boże Ciało
    return true;
}

// ---------- SZABLONY MAILI ----------
// ---------- JĘZYK MAILI (pl / en – ustawiany wg branży) ----------
function lang() { return isset($GLOBALS['LANG']) && $GLOBALS['LANG'] === 'en' ? 'en' : 'pl'; }
function set_lang_branza($bid) {
    $GLOBALS['LANG'] = 'pl';
    if ($bid) { try { $j = val('SELECT jezyk FROM branze WHERE id=?', array((int)$bid)); if ($j === 'en') $GLOBALS['LANG'] = 'en'; } catch (Exception $e) {} }
    return $GLOBALS['LANG'];
}
function my_tel() { $t = trim(setting('podpis_tel')); if (lang() === 'en' && $t !== '' && $t[0] !== '+') $t = '+48 ' . $t; return $t; }
function firm_vars($f) {
    return array(
        '{firma}' => isset($f['nazwa']) ? $f['nazwa'] : '',
        '{miasto}' => isset($f['miasto']) ? $f['miasto'] : '',
        '{osoba}' => isset($f['osoba']) ? $f['osoba'] : '',
        '{moj_telefon}' => my_tel(),
        '{imie}' => strtok(trim(setting('podpis_imie')) . ' ', ' '),
    );
}
function apply_vars($s, $vars) { return strtr((string)$s, $vars); }
// Usuwa linie z {moj_telefon}, gdy telefon nie jest ustawiony
function drop_empty_phone($txt) {
    if (setting('podpis_tel') !== '') return $txt;
    return preg_replace('/^.*\{moj_telefon\}.*$\n?/mu', '', $txt);
}

function inline_fmt($escaped) {
    // **pogrubienie**
    return preg_replace('/\*\*(.+?)\*\*/u', '<strong style="color:#111111;">$1</strong>', $escaped);
}

// Zamienia prosty tekst na bloki HTML. Pusta linia = nowy akapit, "- " = punkt listy.
function text_blocks_html($txt, $vars, $button_html, $case_html) {
    $txt = drop_empty_phone(str_replace("\r", '', (string)$txt));
    $blocks = preg_split("/\n\s*\n/", trim($txt));
    $out = '';
    $btn_used = false;
    foreach ($blocks as $b) {
        $b = trim($b);
        if ($b === '') continue;
        if ($b === '{przycisk}') { $out .= $button_html; $btn_used = true; continue; }
        if ($b === '{case_study}') { $out .= $case_html; continue; }
        $lines = explode("\n", $b);
        // ramka: każda linia zaczyna się od "> "
        $isBox = true;
        foreach ($lines as $l) { if (!preg_match('/^\s*>\s?/u', $l)) { $isBox = false; break; } }
        if ($isBox) {
            $inner = '';
            foreach ($lines as $i => $l) {
                $l = preg_replace('/^\s*>\s?/u', '', $l);
                $inner .= '<div style="font-size:14px;line-height:1.55;color:#4A4740;' . ($i ? 'margin-top:4px;' : '') . '">' . inline_fmt(h(apply_vars($l, $vars))) . '</div>';
            }
            $out .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px 0;"><tr>'
                . '<td style="background:#F7F4EC;border-left:3px solid #C5A059;border-radius:8px;padding:14px 18px;font-family:Arial,Helvetica,sans-serif;">' . $inner . '</td></tr></table>';
            continue;
        }
        $isList = true;
        foreach ($lines as $l) { if (!preg_match('/^\s*[-•]\s+/u', $l)) { $isList = false; break; } }
        if ($isList) {
            $out .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px 0;">';
            foreach ($lines as $l) {
                $l = preg_replace('/^\s*[-•]\s+/u', '', $l);
                $parts = preg_split('/\s+\|\s+/u', $l, 2);
                $ico = '<td valign="top" width="36" style="padding:2px 12px 12px 0;"><div style="width:24px;height:24px;border-radius:12px;background:#111111;color:#C5A059;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;line-height:24px;text-align:center;">&#10003;</div></td>';
                if (count($parts) === 2) {
                    $cell = '<div style="font-size:15px;font-weight:bold;line-height:1.4;color:#111111;">' . inline_fmt(h(apply_vars($parts[0], $vars))) . '</div>'
                        . '<div style="font-size:14px;line-height:1.5;color:#5E5A51;margin-top:2px;">' . inline_fmt(h(apply_vars($parts[1], $vars))) . '</div>';
                } else {
                    $cell = '<div style="font-size:15px;line-height:1.55;color:#333333;padding-top:2px;">' . inline_fmt(h(apply_vars($l, $vars))) . '</div>';
                }
                $out .= '<tr>' . $ico . '<td valign="top" style="padding:0 0 12px 0;font-family:Arial,Helvetica,sans-serif;">' . $cell . '</td></tr>';
            }
            $out .= '</table>';
        } else {
            $html = inline_fmt(nl2br(h(apply_vars($b, $vars)), false));
            $out .= '<p style="margin:0 0 18px 0;font-size:15px;line-height:1.6;color:#333333;">' . $html . '</p>';
        }
    }
    if (!$btn_used && $button_html !== '') $out .= $button_html;
    return $out;
}

function text_plain($txt, $vars, $btn_label, $btn_url, $case_txt) {
    $t = apply_vars(drop_empty_phone(str_replace("\r", '', (string)$txt)), $vars);
    $t = str_replace('{case_study}', apply_vars($case_txt, $vars), $t);
    $btnLine = $btn_url ? ($btn_label . ': ' . $btn_url) : '';
    if (strpos($t, '{przycisk}') !== false) $t = str_replace('{przycisk}', $btnLine, $t);
    elseif ($btnLine) $t .= "\n\n" . $btnLine;
    $t = preg_replace('/\*\*(.+?)\*\*/u', '$1', $t);
    $t = preg_replace('/\s+\|\s+/u', ' – ', $t);
    $t = preg_replace('/^[ \t]*>[ \t]?/mu', '', $t);
    return trim($t);
}

// ---------- PODPIS ----------
function signature_html() {
    $en = lang() === 'en';
    $n = setting('podpis_imie'); if ($en && setting('podpis_imie_en') !== '') $n = setting('podpis_imie_en');
    $rola = $en ? setting('podpis_rola_en') : setting('podpis_rola'); $tel = my_tel();
    $mail = setting('podpis_email'); $www = preg_replace('#^https?://#', '', setting('podpis_www'));
    $greet = $en ? (setting('pozdrowienie_en') ?: 'Kind regards,') : setting('pozdrowienie', 'Z pozdrowieniami,');
    $c = array();
    if ($tel) $c[] = '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $tel)) . '" style="color:#333333;text-decoration:none;">' . h($tel) . '</a>';
    if ($mail) $c[] = '<a href="mailto:' . h($mail) . '" style="color:#333333;text-decoration:none;">' . h($mail) . '</a>';
    if ($www) $c[] = '<a href="https://' . h($www) . '" style="color:#8B6B30;text-decoration:none;font-weight:bold;">' . h($www) . '</a>';
    return '<p style="margin:28px 0 14px 0;font-size:15px;line-height:1.55;color:#333333;">' . h($greet) . '</p>'
        . '<table role="presentation" cellpadding="0" cellspacing="0"><tr><td style="border-left:3px solid #C5A059;padding:2px 0 2px 14px;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="font-size:16px;font-weight:bold;color:#111111;line-height:1.35;">' . h($n) . '</div>'
        . ($rola ? '<div style="font-size:13px;color:#77746C;line-height:1.4;margin-top:1px;">' . h($rola) . '</div>' : '')
        . ($c ? '<div style="font-size:13px;color:#333333;line-height:1.5;margin-top:7px;">' . implode(' &nbsp;·&nbsp; ', $c) . '</div>' : '')
        . '</td></tr></table>';
}
function signature_plain() {
    $en = lang() === 'en';
    $s = ($en ? (setting('pozdrowienie_en') ?: 'Kind regards,') : setting('pozdrowienie', 'Z pozdrowieniami,')) . "\n\n" . ($en && setting('podpis_imie_en') !== '' ? setting('podpis_imie_en') : setting('podpis_imie'));
    $rola = $en ? setting('podpis_rola_en') : setting('podpis_rola');
    if ($rola) $s .= "\n" . $rola;
    if (my_tel()) $s .= "\n" . my_tel();
    foreach (array('podpis_email', 'podpis_www') as $k) if (setting($k)) $s .= "\n" . setting($k);
    return $s;
}

// ---------- BLOKI MAILA ----------
// typy: tekst{text}, naglowek{nad,text}, ramka{title,text}, korzysci{text: "Tytuł | opis" w liniach},
//       przycisk{text,sub}, obrazek{url,link}, linia{}
function tpl_blocks($tpl) {
    if (!empty($tpl['bloki'])) {
        $b = is_array($tpl['bloki']) ? $tpl['bloki'] : json_decode((string)$tpl['bloki'], true);
        if (is_array($b)) return $b;
    }
    return text_to_blocks($tpl);
}
// stary format tekstowy -> bloki
function text_to_blocks($tpl) {
    $out = array();
    if (trim(isset($tpl['naglowek']) ? $tpl['naglowek'] : '') !== '') $out[] = array('t' => 'naglowek', 'nad' => isset($tpl['nadtytul']) ? $tpl['nadtytul'] : '', 'text' => $tpl['naglowek']);
    $txt = str_replace("\r", '', (string)(isset($tpl['tresc']) ? $tpl['tresc'] : ''));
    $buf = array();
    $flush = function () use (&$buf, &$out) { if ($buf) { $out[] = array('t' => 'tekst', 'text' => implode("\n\n", $buf)); $buf = array(); } };
    $hasBtn = false;
    foreach (preg_split("/\n\s*\n/", trim($txt)) as $b) {
        $b = trim($b); if ($b === '') continue;
        $lines = explode("\n", $b);
        if ($b === '{przycisk}') { $flush(); $out[] = array('t' => 'przycisk', 'text' => isset($tpl['przycisk']) ? $tpl['przycisk'] : '', 'sub' => ''); $hasBtn = true; continue; }
        if ($b === '{case_study}') { $flush(); $out[] = array('t' => 'ramka', 'title' => 'Ostatnia realizacja', 'text' => trim(preg_replace('/\*\*|^[ \t]*-[ \t]*/mu', '', setting('case_study')))); continue; }
        if (preg_match('/^[ \t]*>/u', $lines[0])) {
            $flush();
            $l = array_map(function ($x) { return preg_replace('/^[ \t]*>[ \t]?/u', '', $x); }, $lines);
            $title = trim(str_replace('**', '', array_shift($l)));
            $out[] = array('t' => 'ramka', 'title' => $title, 'text' => implode("\n", $l));
            continue;
        }
        $isList = true; foreach ($lines as $l) if (!preg_match('/^\s*[-•]\s+/u', $l)) { $isList = false; break; }
        if ($isList) { $flush(); $out[] = array('t' => 'korzysci', 'text' => implode("\n", array_map(function ($x) { return preg_replace('/^\s*[-•]\s+/u', '', $x); }, $lines))); continue; }
        $buf[] = $b;
    }
    $flush();
    if (!$hasBtn && trim(isset($tpl['przycisk']) ? $tpl['przycisk'] : '') !== '') $out[] = array('t' => 'przycisk', 'text' => $tpl['przycisk'], 'sub' => '');
    return $out;
}
function bv($b, $k) { return isset($b[$k]) ? (string)$b[$k] : ''; }
function btxt($s, $vars) { return inline_fmt(nl2br(h(apply_vars(drop_empty_phone($s), $vars)), false)); }

function blocks_html($blocks, $vars, $btn_url) {
    $o = '';
    foreach ($blocks as $b) {
        $t = bv($b, 't');
        if ($t === 'tekst') {
            foreach (preg_split("/\n\s*\n/", trim(bv($b, 'text'))) as $p) {
                if (trim($p) === '') continue;
                $o .= '<p style="margin:0 0 18px 0;font-size:15px;line-height:1.6;color:#333333;">' . btxt(trim($p), $vars) . '</p>';
            }
        } elseif ($t === 'naglowek') {
            $o .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 22px 0;"><tr><td style="background:#111111;border-radius:12px;padding:24px 26px;font-family:Arial,Helvetica,sans-serif;">'
                . (trim(bv($b, 'nad')) !== '' ? '<div style="font-size:11px;font-weight:bold;letter-spacing:2px;text-transform:uppercase;color:#C5A059;margin-bottom:9px;">' . h(apply_vars(bv($b, 'nad'), $vars)) . '</div>' : '')
                . '<div style="font-size:23px;font-weight:bold;line-height:1.25;color:#FFFFFF;">' . nl2br(h(apply_vars(bv($b, 'text'), $vars)), false) . '</div></td></tr></table>';
        } elseif ($t === 'ramka') {
            $o .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:0 0 18px 0;"><tr><td style="background:#F7F4EC;border-left:3px solid #C5A059;border-radius:8px;padding:15px 18px;font-family:Arial,Helvetica,sans-serif;">'
                . (trim(bv($b, 'title')) !== '' ? '<div style="font-size:15px;font-weight:bold;color:#111111;line-height:1.4;margin-bottom:4px;">' . h(apply_vars(bv($b, 'title'), $vars)) . '</div>' : '')
                . '<div style="font-size:14px;line-height:1.55;color:#4A4740;">' . btxt(bv($b, 'text'), $vars) . '</div></td></tr></table>';
        } elseif ($t === 'korzysci') {
            $o .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 14px 0;">';
            foreach (explode("\n", bv($b, 'text')) as $l) {
                $l = trim(preg_replace('/^\s*[-•]\s*/u', '', $l)); if ($l === '') continue;
                $pp = preg_split('/\s*\|\s*/u', $l, 2);
                $cell = '<div style="font-size:15px;font-weight:bold;line-height:1.4;color:#111111;">' . btxt($pp[0], $vars) . '</div>'
                    . (isset($pp[1]) && $pp[1] !== '' ? '<div style="font-size:14px;line-height:1.5;color:#5E5A51;margin-top:2px;">' . btxt($pp[1], $vars) . '</div>' : '');
                $o .= '<tr><td valign="top" width="36" style="padding:1px 12px 12px 0;"><div style="width:24px;height:24px;border-radius:12px;background:#111111;color:#C5A059;font-family:Arial,Helvetica,sans-serif;font-size:13px;font-weight:bold;line-height:24px;text-align:center;">&#10003;</div></td>'
                    . '<td valign="top" style="padding:0 0 12px 0;font-family:Arial,Helvetica,sans-serif;">' . $cell . '</td></tr>';
            }
            $o .= '</table>';
        } elseif ($t === 'przycisk') {
            if (!$btn_url || trim(bv($b, 'text')) === '') continue;
            $o .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:6px 0 ' . (trim(bv($b, 'sub')) !== '' ? '8' : '22') . 'px 0;"><tr><td style="background:#111111;border-radius:11px;">'
                . '<a href="' . h($btn_url) . '" style="display:inline-block;padding:16px 28px;font-family:Arial,Helvetica,sans-serif;font-size:15px;font-weight:bold;color:#FFFFFF;text-decoration:none;border-radius:11px;">' . h(apply_vars(bv($b, 'text'), $vars)) . ' &rarr;</a></td></tr></table>'
                . (trim(bv($b, 'sub')) !== '' ? '<p style="margin:0 0 22px 0;font-size:12.5px;line-height:1.5;color:#8A867C;">' . btxt(bv($b, 'sub'), $vars) . '</p>' : '');
        } elseif ($t === 'obrazek') {
            $u = trim(bv($b, 'url'));
            if (!preg_match('#^https?://#i', $u)) continue;
            $img = '<img src="' . h($u) . '" alt="" width="100%" style="display:block;width:100%;max-width:540px;height:auto;border:0;border-radius:10px;">';
            $lk = trim(bv($b, 'link'));
            if (preg_match('#^https?://#i', $lk)) $img = '<a href="' . h($lk) . '">' . $img . '</a>';
            $o .= '<div style="margin:0 0 18px 0;">' . $img . '</div>';
        } elseif ($t === 'tytul') {
            if (trim(bv($b, 'text')) === '') continue;
            $o .= '<p style="margin:6px 0 12px 0;font-size:19px;line-height:1.3;font-weight:bold;color:#111111;">' . h(apply_vars(bv($b, 'text'), $vars)) . '</p>';
        } elseif ($t === 'punkty') {
            $li = '';
            foreach (explode("\n", bv($b, 'text')) as $l) { $l = trim(preg_replace('/^\s*[-•*]\s*/u', '', $l)); if ($l !== '') $li .= '<tr><td valign="top" style="padding:0 10px 8px 2px;font-size:15px;line-height:1.6;color:#C5A059;font-weight:bold;">•</td><td style="padding:0 0 8px 0;font-size:15px;line-height:1.6;color:#333333;">' . btxt($l, $vars) . '</td></tr>'; }
            if ($li !== '') $o .= '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:0 0 18px 0;">' . $li . '</table>';
        } elseif ($t === 'linia') {
            $o .= '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin:4px 0 22px 0;"><tr><td style="border-top:1px solid #E7E4DC;font-size:1px;line-height:1px;">&nbsp;</td></tr></table>';
        }
    }
    return $o;
}
function blocks_plain($blocks, $vars, $btn_url) {
    $o = array();
    $cl = function ($s) use ($vars) { return trim(preg_replace('/\*\*(.+?)\*\*/u', '$1', apply_vars(drop_empty_phone($s), $vars))); };
    foreach ($blocks as $b) {
        $t = bv($b, 't');
        if ($t === 'tekst') $o[] = $cl(bv($b, 'text'));
        elseif ($t === 'naglowek') $o[] = trim($cl(bv($b, 'nad')) . "\n" . $cl(bv($b, 'text')));
        elseif ($t === 'ramka') $o[] = trim($cl(bv($b, 'title')) . "\n" . $cl(bv($b, 'text')));
        elseif ($t === 'korzysci') { $ls = array(); foreach (explode("\n", bv($b, 'text')) as $l) if (trim($l) !== '') $ls[] = '- ' . preg_replace('/\s*\|\s*/u', ' – ', $cl(preg_replace('/^\s*[-•]\s*/u', '', $l))); $o[] = implode("\n", $ls); }
        elseif ($t === 'przycisk' && $btn_url && trim(bv($b, 'text')) !== '') $o[] = $cl(bv($b, 'text')) . ': ' . $btn_url . (trim(bv($b, 'sub')) !== '' ? "\n" . $cl(bv($b, 'sub')) : '');
        elseif ($t === 'tytul') $o[] = mb_strtoupper($cl(bv($b, 'text')));
        elseif ($t === 'punkty') { $ls = array(); foreach (explode("\n", bv($b, 'text')) as $l) if (trim($l) !== '') $ls[] = '- ' . $cl(preg_replace('/^\s*[-•*]\s*/u', '', $l)); $o[] = implode("\n", $ls); }
        elseif ($t === 'linia') $o[] = '---';
    }
    return implode("\n\n", array_filter($o, 'strlen'));
}

// Renderuje maila: zwraca array(subject, html, text)
// $opts: btn_url, unsub_url, pixel_url
function render_mail($tpl, $firm, $opts) {
    $vars = firm_vars($firm);
    $subject = trim(apply_vars($tpl['temat'], $vars));
    $btn_url = isset($opts['btn_url']) ? $opts['btn_url'] : '';
    $blocks = tpl_blocks($tpl);
    $body = blocks_html($blocks, $vars, $btn_url);

    $foot = '';
    if (!empty($opts['unsub_url'])) {
        if (lang() === 'en') {
            $foot = '<tr><td style="padding:18px 8px 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.6;color:#9A968C;text-align:center;">'
                . nl2br(h(setting('stopka_en') ?: 'ELOK · business automation & web development · elok-agency.pl'), false) . '<br>We contacted you because your business email address is publicly listed. Prefer not to hear from us? <a href="' . h($opts['unsub_url']) . '" style="color:#9A968C;">Unsubscribe</a>.'
                . '</td></tr>';
        } else
        $foot = '<tr><td style="padding:18px 8px 0 8px;font-family:Arial,Helvetica,sans-serif;font-size:11px;line-height:1.6;color:#9A968C;text-align:center;">'
            . h(setting('stopka_firma')) . (isset($firm['zrodlo']) && $firm['zrodlo'] === 'ceidg' ? '<br>Adres e-mail pochodzi z publicznego rejestru CEIDG.' : '') . '<br>Nie chcesz więcej wiadomości? <a href="' . h($opts['unsub_url']) . '" style="color:#9A968C;">Wypisz się</a>.'
            . '</td></tr>';
    }
    $pixel = !empty($opts['pixel_url']) ? '<img src="' . h($opts['pixel_url']) . '" width="1" height="1" alt="" style="display:block;border:0;width:1px;height:1px;">' : '';
    $logo = '<tr><td align="left" style="padding:0 4px 14px 4px;font-family:Arial,Helvetica,sans-serif;font-size:22px;font-weight:900;letter-spacing:-.5px;color:#111111;">ELOK<span style="color:#C5A059;">.</span></td></tr>';

    $html = '<!DOCTYPE html><html lang="' . lang() . '"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>' . h($subject) . '</title></head>'
        . '<body style="margin:0;padding:0;background:#F4F3EF;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#F4F3EF;"><tr><td align="center" style="padding:28px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;">' . $logo
        . '<tr><td style="background:#FFFFFF;border:1px solid #E7E4DC;border-radius:14px;padding:32px 30px;font-family:Arial,Helvetica,sans-serif;">'
        . $body . signature_html()
        . '</td></tr>' . $foot . '</table>' . $pixel
        . '</td></tr></table></body></html>';

    $text = blocks_plain($blocks, $vars, $btn_url) . "\n\n" . signature_plain();
    if (!empty($opts['unsub_url']) && lang() === 'en') $text .= "\n\n--\n" . (setting('stopka_en') ?: 'ELOK · business automation & web development · elok-agency.pl') . "\nWe contacted you because your business email address is publicly listed.\nUnsubscribe: " . $opts['unsub_url'];
    elseif (!empty($opts['unsub_url'])) $text .= "\n\n--\n" . setting('stopka_firma') . (isset($firm['zrodlo']) && $firm['zrodlo'] === 'ceidg' ? "\nAdres e-mail pochodzi z publicznego rejestru CEIDG." : '') . "\nWypisz się: " . $opts['unsub_url'];
    return array($subject, $html, $text);
}

// ---------- MIGRACJE ----------
function add_col($t, $c, $def) {
    if (!val('SELECT COUNT(*) FROM information_schema.COLUMNS WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=? AND COLUMN_NAME=?', array($t, $c))) db()->exec("ALTER TABLE $t ADD COLUMN $c $def");
}
function migrate() {
    add_col('szablony', 'nadtytul', "VARCHAR(160) NOT NULL DEFAULT ''");
    add_col('szablony', 'naglowek', "VARCHAR(255) NOT NULL DEFAULT ''");
    add_col('szablony', 'bloki', 'MEDIUMTEXT NULL');
    add_col('firmy', 'otwarto_last', 'DATETIME NULL');
    add_col('zgloszenia', 'kiedy', "VARCHAR(120) NOT NULL DEFAULT ''");
    add_col('firmy', 'przypomnienie2_at', 'DATETIME NULL');
    add_col('firmy', 'odpisal_at', 'DATETIME NULL');
    add_col('branze', 'limit_dzienny', 'INT NOT NULL DEFAULT 0');
    add_col('zgloszenia', 'kontakt_data', 'DATE NULL');
    add_col('zgloszenia', 'kontakt_co', "VARCHAR(255) NOT NULL DEFAULT ''");
    wiad_migrate();
    if (setting('klik_fix') !== '1') { // kliknięcia w 2 min od wysyłki bez zgłoszenia = skaner, nie człowiek
        q("UPDATE firmy SET klik_at=NULL WHERE klik_at IS NOT NULL AND zgloszenie_at IS NULL AND wyslano_at IS NOT NULL AND TIMESTAMPDIFF(SECOND, wyslano_at, klik_at) BETWEEN 0 AND 120");
        setting_set('klik_fix', '1');
    }
    $def = array('porcja_auto' => '1','imap_on' => '0', 'imap_host' => 'mail19.lh.pl', 'imap_port' => '993','przyp2_on' => '1', 'dni_przypomnienia2' => '7', 'przyp2_kto' => 'wszyscy','pozdrowienie' => 'Z pozdrowieniami,', 'podpis_rola' => '', 'podpis_email' => setting('nadawca_email'));
    foreach ($def as $k => $v) if (setting($k, null) === null) setting_set($k, $v);
    if (mb_stripos(setting('podpis_rola'), 'założyciel') !== false) setting_set('podpis_rola', '');
    if (trim(setting('podpis_tel')) === '') setting_set('podpis_tel', '455 505 820');
}

// ---------- WYSYŁKA ----------
function enc_header($s) { return '=?UTF-8?B?' . base64_encode($s) . '?='; }

function send_mail($to, $subject, $html, $text, $opts = array()) {
    $from = setting('nadawca_email');
    $fromName = setting('nadawca_nazwa');
    $reply = setting('reply_to') ?: $from;
    if (!$from || !filter_var($to, FILTER_VALIDATE_EMAIL)) return false;
    $b = 'b' . token();
    $dom = substr(strrchr($from, '@'), 1);
    $headers = array();
    $headers[] = 'From: ' . enc_header($fromName) . ' <' . $from . '>';
    $headers[] = 'Reply-To: ' . $reply;
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Message-ID: <' . token() . '@' . $dom . '>';
    $headers[] = 'Date: ' . date('r');
    if (!empty($opts['unsub_url'])) {
        $headers[] = 'List-Unsubscribe: <' . $opts['unsub_url'] . '>, <mailto:' . $reply . '?subject=wypisz>';
        $headers[] = 'List-Unsubscribe-Post: List-Unsubscribe=One-Click';
    }
    $headers[] = 'Content-Type: multipart/alternative; boundary="' . $b . '"';
    $msg = "--$b\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($text))
        . "--$b\r\nContent-Type: text/html; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n" . chunk_split(base64_encode($html))
        . "--$b--";
    if (defined('MAIL_DRY_RUN') && MAIL_DRY_RUN) { // tryb testowy (lokalnie)
        @file_put_contents(__DIR__ . '/mail_test.log', "TO: $to\nSUBJ: $subject\n$text\n=====\n", FILE_APPEND);
        return true;
    }
    return @mail($to, enc_header($subject), $msg, implode("\r\n", $headers), '-f' . $from);
}

function tpl_for($branza_id, $typ) {
    $t = one('SELECT * FROM szablony WHERE branza_id=? AND typ=?', array((int)$branza_id, $typ));
    if (!$t) $t = one('SELECT s.* FROM szablony s JOIN branze b ON b.id=s.branza_id WHERE s.typ=? ORDER BY b.kolejnosc,b.id LIMIT 1', array($typ));
    if (!$t) { // brak w bazie (np. nowy typ maila) – zapisz gotowy szablon dla tej branży
        require_once __DIR__ . '/szablony.php';
        $bn = (string)val('SELECT nazwa FROM branze WHERE id=?', array((int)$branza_id));
        save_template((int)$branza_id, $typ, default_template_for($bn, $typ));
        $t = one('SELECT * FROM szablony WHERE branza_id=? AND typ=?', array((int)$branza_id, $typ));
    }
    return $t;
}

function links_for($f) {
    return array(
        'btn_url' => BASE_URL . '/p.php?a=tak&t=' . $f['token'],
        'unsub_url' => BASE_URL . '/p.php?a=wypis&t=' . $f['token'],
        'pixel_url' => BASE_URL . '/p.php?a=o&t=' . $f['token'],
    );
}

// Wysyła do firmy mail typu 'pierwszy' lub 'przypomnienie'
function send_to_firm($f, $typ) {
    if (in_array($f['status'], array('wypisany', 'nie', 'zainteresowany', 'zwrot'), true)) return 'pominięto (status: ' . $f['status'] . ')';
    if (!$f['token']) { $f['token'] = token(); q('UPDATE firmy SET token=? WHERE id=?', array($f['token'], $f['id'])); }
    set_lang_branza($f['branza_id']);
    $tpl = tpl_for($f['branza_id'], $typ);
    if (!$tpl) return 'brak szablonu';
    $links = links_for($f);
    list($subj, $html, $text) = render_mail($tpl, $f, $links);
    $ok = send_mail($f['email'], $subj, $html, $text, $links);
    if (!$ok) {
        q("UPDATE firmy SET status='blad' WHERE id=?", array($f['id']));
        logi($f['id'], 'blad', 'Nie wysłano: ' . $typ);
        return 'błąd wysyłki';
    }
    if ($typ === 'pierwszy') {
        q("UPDATE firmy SET status=IF(status IN('nowy','blad'),'wyslano',status), wyslano_at=? WHERE id=?", array(now(), $f['id']));
        logi($f['id'], 'wyslano', $subj);
    } elseif ($typ === 'przypomnienie2') {
        q("UPDATE firmy SET status=IF(status='blad','przypomnienie',status), przypomnienie2_at=? WHERE id=?", array(now(), $f['id']));
        logi($f['id'], 'przypomnienie2', $subj);
    } else {
        q("UPDATE firmy SET status=IF(status IN('wyslano','otworzyl','blad'),'przypomnienie',status), przypomnienie_at=? WHERE id=?", array(now(), $f['id']));
        logi($f['id'], 'przypomnienie', $subj);
    }
    return 'ok';
}

function sent_today() {
    return (int)val("SELECT COUNT(*) FROM log WHERE typ IN('wyslano','przypomnienie','przypomnienie2') AND created_at>=?", array(date('Y-m-d 00:00:00')));
}

// Kolejka: najpierw przypomnienia, potem nowe firmy. $manual = z przycisku (pomija godziny/dni, ale trzyma limit dzienny)
function sent_today_branza($bid) {
    return (int)val("SELECT COUNT(*) FROM log l JOIN firmy f ON f.id=l.firma_id WHERE l.typ IN('wyslano','przypomnienie','przypomnienie2') AND l.created_at>=? AND f.branza_id=?", array(date('Y-m-d 00:00:00'), (int)$bid));
}
// ile uruchomień crona (co 15 min) zostało do końca godzin wysyłki
function runs_left() {
    $end = (int)setting('godz_do', '15') * 60;
    $now = (int)date('G') * 60 + (int)date('i');
    return max(1, (int)ceil(($end - $now) / 15));
}
// następna firma do wysłania w danej branży: najpierw przypomnienia, potem ostatnie przypomnienia, potem nowe
function queue_next($bid, $flt) {
    $fw = ' AND f.branza_id=' . (int)$bid; $fp = array();
    if (isset($flt['miasto']) && $flt['miasto'] !== '') { $fw .= ' AND f.miasto=?'; $fp[] = $flt['miasto'] === '__brak' ? '' : (string)$flt['miasto']; }
    $border = date('Y-m-d H:i:s', time() - max(1, (int)setting('dni_przypomnienia', '5')) * 86400);
    $f = one("SELECT f.* FROM firmy f WHERE f.status IN('wyslano','otworzyl') AND f.przypomnienie_at IS NULL AND f.klik_at IS NULL AND f.odpisal_at IS NULL AND f.wyslano_at<=?" . $fw . " ORDER BY f.wyslano_at LIMIT 1", array_merge(array($border), $fp));
    if ($f) return array($f, 'przypomnienie');
    if (setting('przyp2_on', '1') === '1') {
        $b2 = date('Y-m-d H:i:s', time() - max(1, (int)setting('dni_przypomnienia2', '7')) * 86400);
        $kto = setting('przyp2_kto', 'wszyscy') === 'otwarte' ? ' AND f.otwarcia>0' : '';
        $f = one("SELECT f.* FROM firmy f WHERE f.status IN('przypomnienie','otworzyl','wyslano') AND f.przypomnienie_at IS NOT NULL AND f.przypomnienie_at<=? AND f.przypomnienie2_at IS NULL AND f.klik_at IS NULL AND f.odpisal_at IS NULL" . $kto . $fw . " ORDER BY f.przypomnienie_at LIMIT 1", array_merge(array($b2), $fp));
        if ($f) return array($f, 'przypomnienie2');
    }
    $f = one("SELECT f.* FROM firmy f WHERE f.status='nowy'" . $fw . " ORDER BY f.id LIMIT 1", $fp);
    if ($f) return array($f, 'pierwszy');
    return array(null, '');
}

// Kolejka: branże po kolei (każda ze swoim limitem dziennym), w każdej najpierw przypomnienia, potem nowe firmy.
// $manual = z przycisku (pomija godziny/dni, ale trzyma limity)
function run_queue($manual = false, $flt = array()) {
    $res = array('wyslano' => 0, 'przypomnienia' => 0, 'bledy' => 0, 'info' => '');
    if (!$manual) {
        if (setting('auto_on') !== '1') { $res['info'] = 'Automat wyłączony'; return $res; }
        if (!is_workday()) { $res['info'] = 'Dzień wolny'; return $res; }
        $hNow = (int)date('G');
        if ($hNow < (int)setting('godz_od', '8') || $hNow >= (int)setting('godz_do', '11')) { $res['info'] = 'Poza godzinami wysyłki'; return $res; }
    }
    $lock = val("SELECT GET_LOCK('elok_queue',0)");
    if (!$lock) { $res['info'] = 'Wysyłka już trwa'; return $res; }
    add_col('firmy', 'odpisal_at', 'DATETIME NULL');
    add_col('firmy', 'przypomnienie2_at', 'DATETIME NULL');
    add_col('branze', 'limit_dzienny', 'INT NOT NULL DEFAULT 0');

    $left = max(0, (int)setting('limit_dzienny', '25')) - sent_today(); // limit łączny
    if ($left <= 0) { $res['info'] = 'Dzienny limit wykorzystany'; val("SELECT RELEASE_LOCK('elok_queue')"); return $res; }
    $br = !empty($flt['branza']) ? all('SELECT * FROM branze WHERE id=?', array((int)$flt['branza'])) : all('SELECT * FROM branze WHERE aktywna=1 ORDER BY kolejnosc,id');
    $bl = array(); $sumLeft = 0; $inf = false;
    foreach ($br as $b) {
        $lim = (int)$b['limit_dzienny'];
        $bl[$b['id']] = $lim > 0 ? max(0, $lim - sent_today_branza($b['id'])) : PHP_INT_MAX;
        if ($lim > 0) $sumLeft += $bl[$b['id']]; else $inf = true;
    }
    $eff = $inf ? $left : min($left, $sumLeft);
    if ($manual) $batch = min($eff, max(1, isset($flt['max']) ? (int)$flt['max'] : 30));
    elseif (setting('porcja_auto', '1') === '1') $batch = min($eff, max(1, min(20, (int)ceil($eff / runs_left())))); // równo do końca godzin
    else $batch = min($eff, max(1, (int)setting('porcja', '8')));
    $pause = $manual ? 1 : 2;
    $done = array(); $guard = 0;
    while ($batch > 0 && $guard++ < 500) {
        $any = false;
        foreach ($br as $b) {
            if ($batch <= 0) break;
            $id = $b['id'];
            if (!empty($done[$id]) || $bl[$id] <= 0) continue;
            list($f, $typ) = queue_next($id, $flt);
            if (!$f) { $done[$id] = 1; continue; }
            $any = true;
            $r = send_to_firm($f, $typ);
            if ($r === 'ok') { if ($typ === 'pierwszy') $res['wyslano']++; else $res['przypomnienia']++; $bl[$id]--; $batch--; }
            else {
                $res['bledy']++; $batch--;
                if ($typ === 'przypomnienie2') q('UPDATE firmy SET przypomnienie2_at=? WHERE id=? AND przypomnienie2_at IS NULL', array(now(), $f['id']));
                elseif ($typ === 'przypomnienie') q('UPDATE firmy SET przypomnienie_at=? WHERE id=? AND przypomnienie_at IS NULL', array(now(), $f['id']));
            }
            if ($batch > 0) sleep($pause);
        }
        if (!$any) break;
    }
    val("SELECT RELEASE_LOCK('elok_queue')");
    setting_set('ostatni_cron', now());
    return $res;
}

// ---------- POWIADOMIENIA PUSH (ntfy.sh) ----------
function push($title, $msg, $click = '') {
    require_once __DIR__ . '/webpush.php';
    $web = 0;
    if (!(defined('MAIL_DRY_RUN') && MAIL_DRY_RUN)) $web = wp_send_all($title, $msg, $click);
    $nt = push_ntfy($title, $msg, $click);
    return $web > 0 || $nt;
}
function push_ntfy($title, $msg, $click = '') {
    $topic = trim(setting('ntfy_topic'));
    if ($topic === '') return false;
    $payload = json_encode(array('topic' => $topic, 'title' => $title, 'message' => $msg, 'click' => $click ?: BASE_URL, 'priority' => 4, 'tags' => array('bell')), JSON_UNESCAPED_UNICODE);
    if (defined('MAIL_DRY_RUN') && MAIL_DRY_RUN) { @file_put_contents(__DIR__ . '/mail_test.log', "PUSH: $payload\n=====\n", FILE_APPEND); return true; }
    if (function_exists('curl_init')) {
        $c = curl_init('https://ntfy.sh/');
        curl_setopt_array($c, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $payload, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 8, CURLOPT_HTTPHEADER => array('Content-Type: application/json')));
        $r = curl_exec($c); $code = curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
        return $code >= 200 && $code < 300;
    }
    $ctx = stream_context_create(array('http' => array('method' => 'POST', 'header' => "Content-Type: application/json\r\n", 'content' => $payload, 'timeout' => 8)));
    return @file_get_contents('https://ntfy.sh/', false, $ctx) !== false;
}

// Proste powiadomienie mailowe do właściciela
function notify_owner($subject, $rows, $link) {
    $to = setting('powiadom_email');
    if (!$to) return false;
    $tr = '';
    $txt = '';
    foreach ($rows as $k => $v) {
        if ($v === '' || $v === null) continue;
        $vv = h($v);
        if ($k === 'Telefon') $vv = '<a href="tel:' . h(preg_replace('/[^0-9+]/', '', $v)) . '" style="color:#111111;font-weight:bold;">' . h($v) . '</a>';
        if ($k === 'E-mail') $vv = '<a href="mailto:' . h($v) . '" style="color:#111111;">' . h($v) . '</a>';
        $tr .= '<tr><td style="padding:8px 12px 8px 0;color:#77746C;font-size:13px;white-space:nowrap;vertical-align:top;">' . h($k) . '</td><td style="padding:8px 0;color:#111111;font-size:14px;line-height:1.5;">' . nl2br($vv, false) . '</td></tr>';
        $txt .= $k . ': ' . $v . "\n";
    }
    $html = '<!DOCTYPE html><html lang="pl"><body style="margin:0;background:#F4F3EF;"><table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center" style="padding:24px 12px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:560px;background:#FFFFFF;border:1px solid #E7E4DC;border-radius:14px;"><tr><td style="padding:26px;font-family:Arial,Helvetica,sans-serif;">'
        . '<div style="font-size:12px;letter-spacing:1px;color:#8B6B30;font-weight:bold;text-transform:uppercase;margin-bottom:8px;">ELOK · pozyskiwanie klientów</div>'
        . '<div style="font-size:20px;font-weight:bold;color:#111111;margin-bottom:16px;">' . h($subject) . '</div>'
        . '<table role="presentation" cellpadding="0" cellspacing="0">' . $tr . '</table>'
        . '<table role="presentation" cellpadding="0" cellspacing="0" style="margin-top:20px;"><tr><td style="background:#111111;border-radius:10px;"><a href="' . h($link) . '" style="display:inline-block;padding:13px 22px;color:#FFFFFF;font-size:14px;font-weight:bold;text-decoration:none;">Otwórz w panelu &rarr;</a></td></tr></table>'
        . '</td></tr></table></td></tr></table></body></html>';
    return send_mail($to, $subject, $html, $txt . "\n" . $link);
}

// ---------- SESJA / LOGOWANIE ----------
function start_session() {
    if (session_status() === PHP_SESSION_ACTIVE) return;
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    session_name('elok_s');
    session_set_cookie_params(array('lifetime' => 60 * 60 * 24 * 30, 'path' => '/', 'secure' => $secure, 'httponly' => true, 'samesite' => 'Lax'));
    ini_set('session.gc_maxlifetime', (string)(60 * 60 * 24 * 30));
    session_start();
    if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = token();
}
function logged_in() { return !empty($_SESSION['uid']); }

// ---------- DUPLIKATY FIRM ----------
function dup_free_domain($d) { return (bool)preg_match('/^(gmail|googlemail|wp|o2|onet|op|interia|poczta|vp|tlen|gazeta|yahoo|outlook|hotmail|live|icloud|me|proton|protonmail|int|autograf|go2|buziaczek|spoko|onet\.eu)\./', $d . '.') || in_array($d, array('wp.pl', 'o2.pl', 'op.pl', 'onet.pl', 'onet.eu', 'interia.pl', 'interia.eu', 'poczta.fm', 'vp.pl', 'tlen.pl', 'gazeta.pl', 'gmail.com', 'yahoo.com', 'outlook.com', 'hotmail.com', 'icloud.com', 'int.pl', 'go2.pl', 'autograf.pl'), true); }
function dup_phone($t) { $d = preg_replace('/\D+/', '', (string)$t); if (strlen($d) > 9 && substr($d, 0, 2) === '48') $d = substr($d, 2); return strlen($d) >= 9 ? substr($d, -9) : ''; }
function dup_host($www) { $h = strtolower((string)parse_url(preg_match('#^https?://#i', $www) ? $www : 'http://' . $www, PHP_URL_HOST)); return preg_replace('/^www\./', '', $h); }
function dup_name($n) {
    $n = mb_strtolower(trim($n));
    $n = preg_replace('/\b(sp\.?\s*z\s*o\.?\s*o\.?|s\.?\s*c\.?|s\.?\s*a\.?|sp\.?\s*j\.?|sp\.?\s*k\.?|spółka\s+\w+|p\.?h\.?u\.?|f\.?h\.?u\.?|p\.?p\.?h\.?u\.?|zakład|firma|usługi)\b/u', ' ', $n);
    $n = preg_replace('/[^\p{L}\p{N}]+/u', '', $n);
    return $n;
}
// Szuka istniejącej firmy, która wygląda na tę samą. Zwraca array(id, powód) albo null.
function firm_dup_find($email, $telefon = '', $nazwa = '', $miasto = '', $www = '', $skipId = 0) {
    $email = mb_strtolower(trim($email));
    if ($email !== '') { $id = (int)val('SELECT id FROM firmy WHERE email=? AND id<>?', array($email, $skipId)); if ($id) return array($id, 'ten sam e-mail'); }
    $ph = dup_phone($telefon);
    if ($ph !== '') {
        foreach (all("SELECT id, telefon FROM firmy WHERE telefon<>'' AND id<>? AND REPLACE(REPLACE(REPLACE(REPLACE(telefon,' ',''),'-',''),'(',''),')','') LIKE ?", array($skipId, '%' . substr($ph, -6))) as $r)
            if (dup_phone($r['telefon']) === $ph) return array((int)$r['id'], 'ten sam telefon');
    }
    $dom = $email !== '' ? substr(strrchr($email, '@'), 1) : '';
    if ($dom !== '' && !dup_free_domain($dom)) {
        $id = (int)val('SELECT id FROM firmy WHERE email LIKE ? AND id<>? LIMIT 1', array('%@' . $dom, $skipId));
        if ($id) return array($id, 'ta sama domena (@' . $dom . ')');
    }
    $host = $www !== '' ? dup_host($www) : '';
    if ($host !== '' && !dup_free_domain($host)) {
        $id = (int)val('SELECT id FROM firmy WHERE (www LIKE ? OR email LIKE ?) AND id<>? LIMIT 1', array('%' . $host . '%', '%@' . $host, $skipId));
        if ($id) return array($id, 'ta sama strona www');
    }
    $nn = dup_name($nazwa);
    if (mb_strlen($nn) >= 4 && trim($miasto) !== '') {
        foreach (all('SELECT id, nazwa FROM firmy WHERE miasto=? AND id<>?', array(trim($miasto), $skipId)) as $r)
            if (dup_name($r['nazwa']) === $nn) return array((int)$r['id'], 'ta sama nazwa i miasto');
    }
    return null;
}
// Klucze do wykrywania powtórek w jednej paczce (np. w arkuszu)
function dup_keys($email, $telefon, $nazwa, $miasto, $www = '') {
    $k = array();
    $email = mb_strtolower(trim($email));
    if ($email !== '') { $k[] = 'e:' . $email; $d = substr(strrchr($email, '@'), 1); if ($d && !dup_free_domain($d)) $k[] = 'd:' . $d; }
    if (($p = dup_phone($telefon)) !== '') $k[] = 'p:' . $p;
    if ($www !== '' && ($h = dup_host($www)) !== '' && !dup_free_domain($h)) $k[] = 'd:' . $h;
    $nn = dup_name($nazwa); if (mb_strlen($nn) >= 4 && trim($miasto) !== '') $k[] = 'n:' . $nn . '|' . mb_strtolower(trim($miasto));
    return $k;
}

// ---------- WIADOMOŚCI 1:1 (do zgłoszeń, ze śledzeniem otwarć) ----------
function wiad_migrate() {
    db()->exec("CREATE TABLE IF NOT EXISTS wiadomosci (
      id INT AUTO_INCREMENT PRIMARY KEY, zgloszenie_id INT NULL, firma_id INT NULL, email VARCHAR(190) NOT NULL DEFAULT '',
      temat VARCHAR(255) NOT NULL DEFAULT '', bloki MEDIUMTEXT NULL, token CHAR(32) NOT NULL,
      wyslano_at DATETIME NULL, otwarcia INT NOT NULL DEFAULT 0, otwarto_at DATETIME NULL, otwarto_last DATETIME NULL, klik_at DATETIME NULL, kliki INT NOT NULL DEFAULT 0,
      UNIQUE KEY tk (token), KEY zg (zgloszenie_id)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
// $w = wiersz z tabeli wiadomosci (bloki jako JSON); zwraca [temat, html, text]
function wiad_render($w, $firm) {
    $bl = json_decode((string)$w['bloki'], true); if (!is_array($bl)) $bl = array();
    $btn = '';
    $test = trim((string)$w['token'], '0') === ''; // podgląd / test – link prosto do celu, bez liczenia kliknięć
    foreach ($bl as $b) if (isset($b['t']) && $b['t'] === 'przycisk' && !empty($b['url'])) $btn = $test ? (string)$b['url'] : BASE_URL . '/p.php?a=wl&t=' . $w['token'];
    $tpl = array('temat' => $w['temat'], 'bloki' => $bl);
    return render_mail($tpl, $firm, array('btn_url' => $btn, 'pixel_url' => $test ? '' : BASE_URL . '/p.php?a=wo&t=' . $w['token']));
}
