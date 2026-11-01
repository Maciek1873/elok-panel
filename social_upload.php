<?php
// ELOK – wgrywanie filmików do Publikacji (kawałkami po kilka MB, żeby ominąć limity hostingu)
define('ELOK', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/social.php';
header('Content-Type: application/json; charset=utf-8');
function jout($a) { echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') jout(array('ok' => false, 'error' => 'Zła metoda'));
start_session();
$hdr = isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '';
if (!logged_in() || empty($_SESSION['csrf']) || !hash_equals($_SESSION['csrf'], $hdr)) jout(array('ok' => false, 'error' => 'Zaloguj się ponownie'));

$uid = isset($_GET['uid']) ? preg_replace('/[^a-z0-9]/', '', (string)$_GET['uid']) : '';
$part = isset($_GET['part']) ? (int)$_GET['part'] : -1;
$total = isset($_GET['total']) ? (int)$_GET['total'] : 0;
$ext = isset($_GET['ext']) ? strtolower(preg_replace('/[^a-z0-9]/i', '', (string)$_GET['ext'])) : 'mp4';
if (strlen($uid) < 8 || strlen($uid) > 40 || $part < 0 || $total < 1 || $total > 400 || $part >= $total) jout(array('ok' => false, 'error' => 'Złe dane wysyłki'));
if (!in_array($ext, array('mp4', 'mov'), true)) jout(array('ok' => false, 'error' => 'Dozwolone pliki: MP4 lub MOV'));

$dir = soc_dir();
$tmp = $dir . '/tmp_' . $uid . '.part';
$data = file_get_contents('php://input');
if ($data === false || $data === '') jout(array('ok' => false, 'error' => 'Pusty kawałek pliku'));
if ($part === 0 && is_file($tmp)) @unlink($tmp);
if ($part > 0 && !is_file($tmp)) jout(array('ok' => false, 'error' => 'Wysyłka przerwana – spróbuj jeszcze raz'));
file_put_contents($tmp, $data, FILE_APPEND | LOCK_EX);
if (filesize($tmp) > 300 * 1024 * 1024) { @unlink($tmp); jout(array('ok' => false, 'error' => 'Plik za duży (max 300 MB)')); }
if ($part < $total - 1) jout(array('ok' => true, 'part' => $part));

// ostatni kawałek – sprawdź, czy to naprawdę wideo (nagłówek "ftyp")
$fh = fopen($tmp, 'rb'); $head = fread($fh, 12); fclose($fh);
if (substr($head, 4, 4) !== 'ftyp') { @unlink($tmp); jout(array('ok' => false, 'error' => 'To nie wygląda na plik wideo MP4/MOV')); }
$name = date('Ymd_His') . '_' . substr(bin2hex(random_bytes(6)), 0, 10) . '.' . $ext;
rename($tmp, $dir . '/' . $name);
// sprzątanie porzuconych kawałków starszych niż 1 dzień
foreach ((array)glob($dir . '/tmp_*.part') as $f) if (is_file($f) && filemtime($f) < time() - 86400) @unlink($f);
jout(array('ok' => true, 'plik' => $name, 'url' => soc_url($name), 'mb' => round(filesize($dir . '/' . $name) / 1048576, 1)));
