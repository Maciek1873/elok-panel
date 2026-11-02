<?php
// ELOK – automat wysyłki. Ustaw w LH.pl cron co 15 minut:
// wget -q -O - "https://panel.elok-agency.pl/cron.php?key=TWOJ_KLUCZ" >/dev/null 2>&1
// Pobieranie firm w tle (opcjonalnie drugi cron co 5 minut):
// wget -q -O - "https://panel.elok-agency.pl/cron.php?key=TWOJ_KLUCZ&tylko=pobieranie" >/dev/null 2>&1
define('ELOK', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/zrodla.php';
require __DIR__ . '/skrzynka.php';
require __DIR__ . '/social.php';
header('Content-Type: text/plain; charset=utf-8');
$key = PHP_SAPI === 'cli' ? CRON_KEY : (isset($_GET['key']) ? (string)$_GET['key'] : '');
if (!hash_equals(CRON_KEY, $key)) { http_response_code(403); exit('403'); }
@set_time_limit(300);
@ignore_user_abort(true);
$tylko = PHP_SAPI === 'cli' ? (isset($argv[1]) ? $argv[1] : '') : (isset($_GET['tylko']) ? (string)$_GET['tylko'] : '');
// publikacje na FB/IG – przy każdym uruchomieniu crona (także co 5 min)
try { $sr = soc_run(); if ($sr !== 'nic do publikacji') echo date('Y-m-d H:i') . ' | publikacje: ' . $sr . "\n"; } catch (Exception $e) { echo 'publikacje: ' . $e->getMessage() . "\n"; }
// przypomnienia o konsultacjach ze strony (dzień i godzinę wcześniej)
try { require_once __DIR__ . '/strona/przypomnienia.php'; $kp = kons_przyp_run(); if ($kp !== '') echo date('Y-m-d H:i') . ' | konsultacje: ' . $kp . "\n"; } catch (Exception $e) { echo 'konsultacje: ' . $e->getMessage() . "\n"; }
if ($tylko === 'pobieranie') { echo date('Y-m-d H:i') . ' | pobieranie: ' . noc_run(230) . "\n"; exit; }
$r = run_queue(false);
setting_set('ostatni_cron', now());
echo date('Y-m-d H:i') . ' | wysłano: ' . $r['wyslano'] . ' | przypomnienia: ' . $r['przypomnienia'] . ' | błędy: ' . $r['bledy'] . ($r['info'] ? ' | ' . $r['info'] : '') . "\n";
if (setting('imap_on') === '1') { $m = mb_check(25); echo date('Y-m-d H:i') . ' | skrzynka: ' . ($m['blad'] ? $m['blad'] : 'zwroty ' . $m['zwroty'] . ', odpowiedzi ' . $m['odpowiedzi']) . "\n"; }
// codziennie rano: przypomnienie o zaplanowanych kontaktach
if ((int)date('G') >= 7 && setting('kontakt_push') !== date('Y-m-d')) {
    setting_set('kontakt_push', date('Y-m-d'));
    add_col('zgloszenia', 'kontakt_data', 'DATE NULL'); add_col('zgloszenia', 'kontakt_co', "VARCHAR(255) NOT NULL DEFAULT ''");
    $due = all("SELECT firma, telefon, kontakt_co, kontakt_data FROM zgloszenia WHERE kontakt_data IS NOT NULL AND kontakt_data<=CURDATE() AND status NOT IN('klient','odrzucone') ORDER BY kontakt_data");
    if ($due) {
        $m = array(); foreach ($due as $d) $m[] = '• ' . $d['firma'] . ($d['telefon'] ? ' (' . $d['telefon'] . ')' : '') . ($d['kontakt_co'] ? ' – ' . $d['kontakt_co'] : '') . ($d['kontakt_data'] < date('Y-m-d') ? ' [zaległe]' : '');
        push('📅 Dziś do kontaktu: ' . count($due), implode("\n", $m), BASE_URL);
    }
}
$noc = noc_get();
if (!empty($noc['on'])) echo date('Y-m-d H:i') . ' | pobieranie: ' . noc_run(200) . "\n";
