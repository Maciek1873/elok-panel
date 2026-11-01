<?php
// ELOK – aktualizacja bazy + (opcjonalnie) podmiana maili na gotowe. Po użyciu USUŃ ten plik.
define('ELOK', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/szablony.php';
start_session();
header('Content-Type: text/html; charset=utf-8');
$css = '<style>body{font-family:Arial,sans-serif;background:#F4F3EF;display:flex;justify-content:center;padding:40px 16px}.b{background:#fff;border:1px solid #E7E4DC;border-radius:16px;padding:30px;max-width:480px;width:100%}h1 span{color:#C5A059}button,a.btn{display:inline-block;padding:14px 20px;border:0;border-radius:10px;background:#111;color:#fff;font-weight:bold;font-size:15px;cursor:pointer;text-decoration:none;margin:4px 6px 4px 0}button.l{background:#fff;color:#111;border:1px solid #ddd}li{margin:4px 0}</style>';
echo '<!DOCTYPE html><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">' . $css . '<div class="b"><h1>ELOK<span>.</span></h1>';
if (!logged_in()) { echo '<p>Najpierw <a href="index.php">zaloguj się do panelu</a>, potem otwórz ten adres jeszcze raz.</p></div>'; exit; }
migrate();
echo '<p style="color:#2E7D4F"><b>✓ Baza zaktualizowana.</b></p>';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['csrf']) && hash_equals($_SESSION['csrf'], $_POST['csrf'])) {
    $n = 0; $out = '';
    foreach (all('SELECT id,nazwa FROM branze ORDER BY kolejnosc,id') as $b) {
        foreach (array('pierwszy', 'przypomnienie', 'potwierdzenie') as $typ) { save_template($b['id'], $typ, default_template_for($b['nazwa'], $typ)); $n++; }
        $out .= '<li>' . h($b['nazwa']) . '</li>';
    }
    echo '<p><b>Podmieniono ' . $n . ' maili:</b></p><ul>' . $out . '</ul>';
    echo '<p><b>Teraz usuń plik aktualizacja.php z serwera.</b></p><a class="btn" href="index.php#maile">Zobacz maile</a></div>';
    exit;
}
echo '<p>Chcesz też wgrać <b>nowe gotowe maile</b> (z bloczkami, ramką „Ostatnia realizacja” i przyciskiem do formularza) we wszystkich branżach? Twoje obecne treści zostaną nadpisane.</p>'
    . '<form method="post"><input type="hidden" name="csrf" value="' . h($_SESSION['csrf']) . '"><button>Tak, wgraj nowe maile</button><a class="btn" style="background:#fff;color:#111;border:1px solid #ddd" href="index.php#maile">Nie, zostaw moje</a></form>'
    . '<p style="color:#777;font-size:13px">Na koniec usuń plik aktualizacja.php z serwera.</p></div>';
