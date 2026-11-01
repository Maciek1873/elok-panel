<?php
// Stary panel SaaS – API zabezpieczone sesją panelu ELOK
define('ELOK', 1);
require __DIR__ . '/../lib.php';
start_session();
header('Content-Type: application/json; charset=utf-8');
if (!logged_in()) { http_response_code(403); echo json_encode(array('error' => 'Zaloguj się do panelu')); exit; }
if (!isset($_POST['action'])) { echo json_encode(array('error' => 'Brak akcji')); exit; }

$action = $_POST['action'];
$db_file = __DIR__ . '/baza.json';

if ($action === 'loadDB') {
    if (file_exists($db_file)) echo file_get_contents($db_file);
    else echo json_encode(array('clients' => array(), 'archive' => array(), 'trash' => array(), 'team' => array(), 'general_chat' => array()));
    exit;
}

if ($action === 'saveDB') {
    $data = isset($_POST['data']) ? $_POST['data'] : '';
    if ($data === '' || json_decode($data) === null) { echo json_encode(array('status' => 'error', 'msg' => 'Złe dane')); exit; }
    // kopia zapasowa przed każdym zapisem (ostatnie 20)
    $bdir = __DIR__ . '/backup';
    if (!is_dir($bdir)) @mkdir($bdir, 0755, true);
    if (file_exists($db_file)) @copy($db_file, $bdir . '/baza_' . date('Ymd_His') . '.json');
    $old = glob($bdir . '/baza_*.json');
    if ($old && count($old) > 20) { sort($old); foreach (array_slice($old, 0, count($old) - 20) as $o) @unlink($o); }
    file_put_contents($db_file, $data, LOCK_EX);
    echo json_encode(array('status' => 'success'));
    exit;
}

if ($action === 'uploadFile') {
    if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) { echo json_encode(array('status' => 'error')); exit; }
    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));
    $allowed = array('jpg', 'jpeg', 'png', 'webp', 'gif', 'pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt', 'zip');
    if (!in_array($ext, $allowed, true) || $_FILES['file']['size'] > 20 * 1024 * 1024) { echo json_encode(array('status' => 'error', 'msg' => 'Niedozwolony plik')); exit; }
    $dir = __DIR__ . '/../uploads/';
    if (!is_dir($dir)) @mkdir($dir, 0755, true);
    $base = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($_FILES['file']['name'], PATHINFO_FILENAME));
    $filename = time() . '_' . substr($base, 0, 60) . '.' . $ext;
    if (move_uploaded_file($_FILES['file']['tmp_name'], $dir . $filename)) {
        echo json_encode(array('status' => 'success', 'link' => BASE_URL . '/uploads/' . $filename, 'name' => $_FILES['file']['name']));
    } else echo json_encode(array('status' => 'error'));
    exit;
}

if ($action === 'sendMail') {
    $to = isset($_POST['to']) ? trim($_POST['to']) : '';
    $subject = isset($_POST['subject']) ? $_POST['subject'] : '';
    $msg = isset($_POST['msg']) ? $_POST['msg'] : '';
    if (!filter_var($to, FILTER_VALIDATE_EMAIL)) { echo json_encode(array('status' => 'error')); exit; }
    $html = '<!DOCTYPE html><html lang="pl"><body style="background:#000;color:#fff;font-family:Arial,sans-serif;margin:0;padding:30px 10px;">'
        . '<table width="100%" cellpadding="0" cellspacing="0" style="max-width:500px;margin:0 auto;background:#0a0a0a;border:1px solid #1a1a1a;border-radius:20px;">'
        . '<tr><td style="background:#111;padding:30px;text-align:center;"><h1 style="margin:0;font-size:36px;font-weight:900;color:#fff;">ELOK<span style="color:#C5A059;">.</span></h1></td></tr>'
        . '<tr><td style="padding:40px 30px;font-size:15px;line-height:1.6;color:#ccc;text-align:center;"><strong style="color:#fff;font-size:18px;">Nowe powiadomienie z systemu</strong><br><br>' . $msg
        . '<br><br><a href="' . BASE_URL . '/saas/" style="background:#C5A059;color:#000;padding:16px 32px;text-decoration:none;border-radius:12px;font-weight:800;display:inline-block;font-size:12px;">PRZEJDŹ DO PANELU</a></td></tr>'
        . '<tr><td style="background:#050505;padding:20px;text-align:center;font-size:11px;color:#555;">&copy; ' . date('Y') . ' ELOK Agency.</td></tr></table></body></html>';
    send_mail($to, $subject, $html, strip_tags(str_replace('<br>', "\n", $msg)));
    echo json_encode(array('status' => 'success'));
    exit;
}
echo json_encode(array('error' => 'Nieprawidłowa akcja'));
