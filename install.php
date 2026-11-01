<?php
// ELOK PANEL – instalator. Uruchom raz, potem USUŃ ten plik z serwera.
define('ELOK', 1);
require __DIR__ . '/lib.php';
header('Content-Type: text/html; charset=utf-8');

function page($body) {
    echo '<!DOCTYPE html><html lang="pl"><head><meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Instalacja ELOK</title>'
        . '<style>body{font-family:Arial,sans-serif;background:#F4F3EF;color:#111;display:flex;justify-content:center;padding:40px 16px}.b{background:#fff;border:1px solid #E7E4DC;border-radius:16px;padding:30px;max-width:440px;width:100%}'
        . 'h1{margin:0 0 6px;font-size:26px}h1 span{color:#C5A059}input{width:100%;box-sizing:border-box;padding:12px;border:1px solid #DDD;border-radius:10px;margin:6px 0 12px;font-size:15px}'
        . 'button{width:100%;padding:14px;border:0;border-radius:10px;background:#111;color:#fff;font-weight:bold;font-size:15px;cursor:pointer}.ok{color:#2E7D4F}.err{color:#B3261E}code{background:#F4F3EF;padding:2px 6px;border-radius:6px}</style></head>'
        . '<body><div class="b"><h1>ELOK<span>.</span></h1>' . $body . '</div></body></html>';
    exit;
}

try { db(); } catch (Exception $e) {
    page('<p class="err"><b>Brak połączenia z bazą.</b></p><p>Uzupełnij dane bazy w <code>config.php</code>.</p><p style="font-size:12px;color:#777">' . h($e->getMessage()) . '</p>');
}

$schema = array(
"CREATE TABLE IF NOT EXISTS ustawienia (k VARCHAR(64) PRIMARY KEY, v TEXT) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS branze (
  id INT AUTO_INCREMENT PRIMARY KEY, nazwa VARCHAR(120) NOT NULL, aktywna TINYINT(1) NOT NULL DEFAULT 1, kolejnosc INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS szablony (
  id INT AUTO_INCREMENT PRIMARY KEY, branza_id INT NOT NULL, typ VARCHAR(20) NOT NULL,
  temat VARCHAR(255) NOT NULL DEFAULT '', nadtytul VARCHAR(160) NOT NULL DEFAULT '', naglowek VARCHAR(255) NOT NULL DEFAULT '', tresc TEXT, bloki MEDIUMTEXT NULL, przycisk VARCHAR(120) NOT NULL DEFAULT '', updated_at DATETIME NULL,
  UNIQUE KEY bt (branza_id, typ)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS firmy (
  id INT AUTO_INCREMENT PRIMARY KEY, nazwa VARCHAR(255) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL, telefon VARCHAR(60) NOT NULL DEFAULT '',
  miasto VARCHAR(120) NOT NULL DEFAULT '', osoba VARCHAR(120) NOT NULL DEFAULT '', www VARCHAR(255) NOT NULL DEFAULT '', branza_id INT NULL,
  status VARCHAR(20) NOT NULL DEFAULT 'nowy', token CHAR(32) NULL, notatka TEXT,
  wyslano_at DATETIME NULL, otwarto_at DATETIME NULL, otwarto_last DATETIME NULL, otwarcia INT NOT NULL DEFAULT 0, przypomnienie_at DATETIME NULL,
  klik_at DATETIME NULL, zgoda_ip VARCHAR(45) NULL, zgloszenie_at DATETIME NULL, wypis_at DATETIME NULL, dodano_at DATETIME NULL,
  UNIQUE KEY em (email), UNIQUE KEY tk (token), KEY st (status), KEY br (branza_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS zgloszenia (
  id INT AUTO_INCREMENT PRIMARY KEY, firma_id INT NULL, firma VARCHAR(255) NOT NULL DEFAULT '', osoba VARCHAR(120) NOT NULL DEFAULT '',
  telefon VARCHAR(60) NOT NULL DEFAULT '', email VARCHAR(190) NOT NULL DEFAULT '', czym TEXT, co TEXT,
  status VARCHAR(20) NOT NULL DEFAULT 'nowe', notatka TEXT, ip VARCHAR(45) NULL, created_at DATETIME NULL, KEY fi (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
"CREATE TABLE IF NOT EXISTS log (
  id INT AUTO_INCREMENT PRIMARY KEY, firma_id INT NULL, typ VARCHAR(20) NOT NULL, info VARCHAR(255) NOT NULL DEFAULT '', created_at DATETIME NOT NULL,
  KEY tc (typ, created_at), KEY fi (firma_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4",
);
foreach ($schema as $s) db()->exec($s);

// ---------- MIGRACJE ----------
migrate();

// ---------- DANE STARTOWE ----------
require __DIR__ . '/szablony.php';
if (!val('SELECT COUNT(*) FROM branze')) {
    $defaults = array(
        'nadawca_nazwa' => 'Maciek | ELOK', 'nadawca_email' => 'kontakt@elok-agency.pl', 'reply_to' => '',
        'powiadom_email' => '', 'ntfy_topic' => 'elok-' . substr(token(), 0, 12),
        'podpis_imie' => 'Maciek', 'podpis_rola' => '', 'podpis_tel' => '455 505 820', 'podpis_email' => 'kontakt@elok-agency.pl', 'podpis_www' => 'elok-agency.pl', 'pozdrowienie' => 'Z pozdrowieniami,',
        'stopka_firma' => 'ELOK – automatyzacje sprzedaży i obsługi dla firm · elok-agency.pl',
        'auto_on' => '0', 'limit_dzienny' => '25', 'porcja' => '8', 'godz_od' => '8', 'godz_do' => '11', 'dni_przypomnienia' => '5',
        'case_study' => default_case_study(),
    );
    foreach ($defaults as $k => $v) setting_set($k, $v);
    $i = 0;
    foreach (default_templates() as $nazwa => $set) {
        $i++;
        q('INSERT INTO branze(nazwa,aktywna,kolejnosc) VALUES(?,1,?)', array($nazwa, $i));
        $bid = (int)db()->lastInsertId();
        foreach ($set as $typ => $t) save_template($bid, $typ, $t);
    }
}

// ---------- KONTO ADMINA ----------
if (setting('admin_hash') !== '') {
    page('<p class="ok"><b>Panel jest już zainstalowany.</b></p><p>Usuń plik <code>install.php</code> z serwera i <a href="index.php">zaloguj się</a>.</p>');
}
$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $l = trim(isset($_POST['login']) ? $_POST['login'] : '');
    $p = isset($_POST['pass']) ? $_POST['pass'] : '';
    $e = trim(isset($_POST['email']) ? $_POST['email'] : '');
    if ($l === '' || strlen($p) < 8) $err = 'Login wymagany, hasło min. 8 znaków.';
    elseif (!filter_var($e, FILTER_VALIDATE_EMAIL)) $err = 'Podaj poprawny e-mail do powiadomień.';
    else {
        setting_set('admin_login', $l);
        setting_set('admin_hash', password_hash($p, PASSWORD_DEFAULT));
        setting_set('powiadom_email', $e);
        page('<p class="ok"><b>Gotowe!</b> Panel zainstalowany.</p><p>1. <b>Usuń plik install.php</b> z serwera.<br>2. <a href="index.php">Zaloguj się</a> loginem <b>' . h($l) . '</b>.</p>');
    }
}
page('<p style="color:#777;margin-top:0">Utwórz konto administratora</p>' . ($err ? '<p class="err">' . h($err) . '</p>' : '')
    . '<form method="post"><label>Login</label><input name="login" value="admin" autocomplete="username">'
    . '<label>Hasło (min. 8 znaków)</label><input name="pass" type="password" autocomplete="new-password">'
    . '<label>Twój e-mail (tu przyjdą zgłoszenia)</label><input name="email" type="email">'
    . '<button>Zainstaluj</button></form>');
