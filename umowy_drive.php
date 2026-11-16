<?php
// ELOK – połączenie modułu Umowy z Google Drive (OAuth). Zakres drive.file: panel widzi tylko pliki, które sam utworzył.
define('ELOK', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/umowy.php';
start_session();
header('Cache-Control: no-store');
if (!logged_in()) { http_response_code(403); exit('Zaloguj się w panelu.'); }

$cid = trim(setting('um_gd_client_id'));
$sec = trim(setting('um_gd_secret'));
$back = BASE_URL . '/#umowy';
function gd_page($title, $msg, $ok) {
    header('Content-Type: text/html; charset=utf-8');
    echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Google Drive · ELOK</title>'
        . '<body style="margin:0;background:#F5F4F0;font-family:Arial,sans-serif;color:#151515"><div style="max-width:520px;margin:60px auto;background:#fff;border:1px solid #E7E4DC;border-radius:16px;padding:28px">'
        . '<div style="font-weight:800;font-size:24px">ELOK<span style="color:#C5A059">.</span></div><h1 style="font-size:20px;margin:16px 0 8px">' . h($title) . '</h1>'
        . '<p style="color:#4A4740;line-height:1.6">' . $msg . '</p><a href="' . h(BASE_URL) . '/#umowy" style="display:inline-block;margin-top:10px;background:#151515;color:#fff;padding:12px 18px;border-radius:10px;text-decoration:none;font-weight:bold">Wróć do panelu</a>'
        . ($ok ? '' : '') . '</div></body>';
    exit;
}
if ($cid === '' || $sec === '') gd_page('Brak danych aplikacji Google', 'Najpierw wpisz <b>Client ID</b> i <b>Client secret</b> w Umowy → Ustawienia → Google Drive.', false);

// krok 1: przekierowanie do Google
if (isset($_GET['start'])) {
    $_SESSION['gd_state'] = token();
    header('Location: https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query(array(
        'client_id' => $cid, 'redirect_uri' => um_gd_redirect(), 'response_type' => 'code', 'access_type' => 'offline', 'prompt' => 'consent',
        'scope' => 'https://www.googleapis.com/auth/drive.file openid email', 'state' => $_SESSION['gd_state'])));
    exit;
}
// krok 2: powrót z Google
if (isset($_GET['error'])) gd_page('Nie połączono', 'Google zwrócił: ' . h($_GET['error']) . '. Spróbuj jeszcze raz.', false);
if (!isset($_GET['code'], $_GET['state']) || empty($_SESSION['gd_state']) || !hash_equals($_SESSION['gd_state'], (string)$_GET['state'])) gd_page('Nie połączono', 'Sesja połączenia wygasła. Kliknij „Połącz z Google Drive” jeszcze raz.', false);
unset($_SESSION['gd_state']);
try {
    list($code, $j) = um_http('POST', 'https://oauth2.googleapis.com/token', http_build_query(array(
        'code' => (string)$_GET['code'], 'client_id' => $cid, 'client_secret' => $sec, 'redirect_uri' => um_gd_redirect(), 'grant_type' => 'authorization_code')),
        array('Content-Type: application/x-www-form-urlencoded'));
    if ($code !== 200 || empty($j['refresh_token'])) gd_page('Nie połączono', 'Google nie wydał stałego dostępu (' . h(isset($j['error_description']) ? $j['error_description'] : (isset($j['error']) ? $j['error'] : $code)) . ').', false);
    setting_set('um_gd_refresh', $j['refresh_token']);
    $email = '';
    if (!empty($j['id_token'])) { $p = explode('.', $j['id_token']); if (isset($p[1])) { $pl = json_decode(base64_decode(strtr($p[1], '-_', '+/')), true); if (!empty($pl['email'])) $email = $pl['email']; } }
    setting_set('um_gd_konto', $email);
    setting_set('um_gd_folder', '');
    $fid = um_gd_folder();
    gd_page('Połączono z Google Drive', 'Kopie umów będą zapisywane w folderze <b>ELOK Umowy</b>' . ($email ? ' na koncie <b>' . h($email) . '</b>' : '') . '. Każda zawarta umowa trafi tam automatycznie, a co tydzień pełna kopia modułu.', true);
} catch (Exception $e) {
    gd_page('Nie połączono', h($e->getMessage()), false);
}
