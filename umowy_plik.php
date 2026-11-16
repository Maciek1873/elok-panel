<?php
// ELOK – pliki umów (tylko po zalogowaniu): pobieranie PDF i wgrywanie PDF z podpisem kwalifikowanym
define('ELOK', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/umowy.php';
start_session();
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
if (!logged_in()) { http_response_code(403); exit('Zaloguj się w panelu.'); }

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$u = um_get_row($id);

// ---------- WGRANIE PDF Z PODPISEM KWALIFIKOWANYM ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json; charset=utf-8');
    $res = function ($ok, $msg = '') { echo json_encode($ok ? array('ok' => true) : array('ok' => false, 'error' => $msg), JSON_UNESCAPED_UNICODE); exit; };
    $hdr = isset($_SERVER['HTTP_X_CSRF']) ? $_SERVER['HTTP_X_CSRF'] : '';
    if (!hash_equals($_SESSION['csrf'], $hdr)) $res(false, 'Sesja wygasła – odśwież stronę.');
    if (!$u) $res(false, 'Nie ma takiej umowy.');
    if (!in_array($u['status'], array('szkic', 'wyslana'), true)) $res(false, 'Plik z podpisem kwalifikowanym można dodać do szkicu albo umowy czekającej na podpis.');
    if (empty($_FILES['plik']) || $_FILES['plik']['error'] !== UPLOAD_ERR_OK) $res(false, 'Nie udało się wgrać pliku (maks. ' . ini_get('upload_max_filesize') . ').');
    $tmp = $_FILES['plik']['tmp_name'];
    if (filesize($tmp) > 25 * 1048576) $res(false, 'Plik jest za duży (maks. 25 MB).');
    $head = (string)file_get_contents($tmp, false, null, 0, 5);
    if ($head !== '%PDF-') $res(false, 'To nie jest plik PDF.');
    $data = file_get_contents($tmp);
    $rel = 'pliki/' . (int)$u['id'] . '/' . preg_replace('/[^A-Za-z0-9_-]+/', '_', $u['numer']) . '_kwalifikowany_' . date('Ymd_His') . '.pdf';
    um_dir(dirname($rel));
    if (!move_uploaded_file($tmp, um_dir() . '/' . $rel)) $res(false, 'Nie udało się zapisać pliku na serwerze.');
    $sha = hash('sha256', $data);
    $k = um_klient($u['klient_id']);
    q('UPDATE um_umowy SET status=?, metoda=?, zawarta_at=?, pdf_plik=?, pdf_sha=?, token_hash=NULL, token_exp=NULL, kod_hash=NULL,
        klient_json=COALESCE(klient_json, ?), firma_json=COALESCE(firma_json, ?), updated_at=? WHERE id=?',
        array('zawarta', 'kwalifikowany', now(), $rel, $sha, json_encode(um_klient_vals($k), JSON_UNESCAPED_UNICODE), json_encode(um_firma(), JSON_UNESCAPED_UNICODE), now(), (int)$u['id']));
    um_log($u['id'], 'kwalifikowany', 'Wgrano PDF podpisany kwalifikowanym podpisem elektronicznym („' . mb_substr($_FILES['plik']['name'], 0, 120) . '”), SHA-256 pliku ' . $sha . '. Plik zapisany bez zmian.');
    um_kopia_dodaj($u['id'], 'umowa', $rel, um_pdf_name($u, '_kwalifikowany'));
    $res(true);
}

// ---------- POBIERANIE ----------
if (!$u) { http_response_code(404); exit('Nie ma takiej umowy.'); }
$typ = isset($_GET['typ']) ? (string)$_GET['typ'] : 'pdf';
try {
    if ($typ === 'final' || ($typ === 'pdf' && in_array($u['status'], array('zawarta', 'rozwiazana'), true) && $u['pdf_plik'] !== '')) {
        $pdf = um_pdf_final($u);
        if ($pdf === null) { http_response_code(404); exit('Brak pliku PDF tej umowy.'); }
        $name = um_pdf_name($u, $u['metoda'] === 'kwalifikowany' ? '_kwalifikowany' : '_zawarta');
    } else {
        $pdf = um_pdf_tresc($u);
        $name = um_pdf_name($u, $u['status'] === 'szkic' ? '_szkic' : '');
    }
} catch (Exception $e) { http_response_code(500); exit('Nie udało się przygotować PDF: ' . h($e->getMessage())); }
header('Content-Type: application/pdf');
header('Content-Disposition: ' . (isset($_GET['pobierz']) ? 'attachment' : 'inline') . '; filename="' . $name . '"');
echo $pdf;
