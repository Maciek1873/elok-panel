<?php
// ELOK – powiadomienia Web Push (bez dodatkowych aplikacji; iPhone: panel dodany do ekranu początkowego)
if (!defined('ELOK')) exit;

function wp_b64u($s) { return rtrim(strtr(base64_encode($s), '+/', '-_'), '='); }
function wp_b64d($s) { $s = strtr($s, '-_', '+/'); return base64_decode($s . str_repeat('=', (4 - strlen($s) % 4) % 4)); }
function wp_migrate() {
    static $done = false; if ($done) return; $done = true;
    db()->exec("CREATE TABLE IF NOT EXISTS push_subs (id INT AUTO_INCREMENT PRIMARY KEY, endpoint VARCHAR(700) NOT NULL, p256dh VARCHAR(200) NOT NULL, auth VARCHAR(100) NOT NULL, urzadzenie VARCHAR(120) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, UNIQUE KEY ep (endpoint(255))) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function wp_ec_new() {
    $cfg = array('curve_name' => 'prime256v1', 'private_key_type' => OPENSSL_KEYTYPE_EC);
    $k = @openssl_pkey_new($cfg);
    if (!$k) { // niektóre hostingi nie mają domyślnego openssl.cnf
        foreach (array('/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf', '/etc/pki/tls/openssl.cnf') as $f) if (is_file($f)) { $cfg['config'] = $f; $k = @openssl_pkey_new($cfg); if ($k) break; }
    }
    return $k;
}
function wp_ec_raw_pub($k) {
    $d = openssl_pkey_get_details($k);
    return "\x04" . str_pad($d['ec']['x'], 32, "\0", STR_PAD_LEFT) . str_pad($d['ec']['y'], 32, "\0", STR_PAD_LEFT);
}
function wp_pub_pem($raw) {
    $der = hex2bin('3059301306072a8648ce3d020106082a8648ce3d030107034200') . $raw;
    return "-----BEGIN PUBLIC KEY-----\n" . chunk_split(base64_encode($der), 64, "\n") . "-----END PUBLIC KEY-----\n";
}
// klucze VAPID – tworzone raz i trzymane w ustawieniach
function wp_keys() {
    $pub = setting('vapid_pub'); $priv = setting('vapid_priv');
    if ($pub !== '' && $priv !== '') return array($pub, $priv);
    $k = wp_ec_new(); if (!$k) return array('', '');
    $pem = '';
    if (!@openssl_pkey_export($k, $pem)) foreach (array('/etc/ssl/openssl.cnf', '/usr/lib/ssl/openssl.cnf', '/etc/pki/tls/openssl.cnf') as $f) if (is_file($f) && @openssl_pkey_export($k, $pem, null, array('config' => $f))) break;
    if ($pem === '') return array('', '');
    $pub = wp_b64u(wp_ec_raw_pub($k));
    setting_set('vapid_pub', $pub); setting_set('vapid_priv', $pem);
    return array($pub, $pem);
}
function wp_der2raw($der) {
    $o = 2; if (ord($der[1]) & 0x80) $o += ord($der[1]) & 0x7f;
    $rl = ord($der[$o + 1]); $r = substr($der, $o + 2, $rl); $o += 2 + $rl;
    $sl = ord($der[$o + 1]); $s = substr($der, $o + 2, $sl);
    $r = str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT); $s = str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    return $r . $s;
}
function wp_jwt($aud, $priv) {
    $sub = setting('powiadom_email'); $sub = $sub ? 'mailto:' . $sub : 'mailto:kontakt@elok-agency.pl';
    $d = wp_b64u('{"typ":"JWT","alg":"ES256"}') . '.' . wp_b64u(json_encode(array('aud' => $aud, 'exp' => time() + 12 * 3600, 'sub' => $sub), JSON_UNESCAPED_SLASHES));
    $sig = ''; if (!openssl_sign($d, $sig, $priv, OPENSSL_ALGO_SHA256)) return '';
    return $d . '.' . wp_b64u(wp_der2raw($sig));
}
// szyfrowanie treści (RFC 8291, aes128gcm)
function wp_encrypt($payload, $uaPubB64, $authB64) {
    $ua = wp_b64d($uaPubB64); $auth = wp_b64d($authB64);
    if (strlen($ua) !== 65 || strlen($auth) < 16) return false;
    $eph = wp_ec_new(); if (!$eph) return false;
    $as = wp_ec_raw_pub($eph);
    $shared = openssl_pkey_derive(openssl_pkey_get_public(wp_pub_pem($ua)), $eph, 32);
    if ($shared === false) return false;
    $ikm = hash_hkdf('sha256', $shared, 32, "WebPush: info\0" . $ua . $as, $auth);
    $salt = random_bytes(16);
    $cek = hash_hkdf('sha256', $ikm, 16, "Content-Encoding: aes128gcm\0", $salt);
    $nonce = hash_hkdf('sha256', $ikm, 12, "Content-Encoding: nonce\0", $salt);
    $tag = '';
    $ct = openssl_encrypt($payload . "\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    if ($ct === false) return false;
    return $salt . pack('N', 4096) . chr(65) . $as . $ct . $tag;
}
// wysyła do jednego urządzenia; zwraca kod HTTP (0 = błąd po naszej stronie)
function wp_send_one($sub, $payload) {
    list($pub, $priv) = wp_keys(); if ($pub === '') return 0;
    $u = parse_url($sub['endpoint']); if (empty($u['host'])) return 0;
    $jwt = wp_jwt($u['scheme'] . '://' . $u['host'], $priv); if ($jwt === '') return 0;
    $body = wp_encrypt($payload, $sub['p256dh'], $sub['auth']); if ($body === false) return 0;
    $c = curl_init($sub['endpoint']);
    curl_setopt_array($c, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $body, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 10, CURLOPT_CONNECTTIMEOUT => 6,
        CURLOPT_HTTPHEADER => array('Authorization: vapid t=' . $jwt . ', k=' . $pub, 'Content-Encoding: aes128gcm', 'Content-Type: application/octet-stream', 'TTL: 86400', 'Urgency: high')));
    curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); curl_close($c);
    return $code;
}
// wysyła do wszystkich zapisanych urządzeń; zwraca liczbę udanych
function wp_send_all($title, $msg, $click = '') {
    if (!function_exists('curl_init') || !function_exists('openssl_pkey_derive')) return 0;
    try { wp_migrate(); $subs = all('SELECT * FROM push_subs'); } catch (Exception $e) { return 0; }
    $payload = json_encode(array('title' => $title, 'body' => $msg, 'url' => $click ?: BASE_URL), JSON_UNESCAPED_UNICODE);
    $ok = 0;
    foreach ($subs as $s) {
        $code = wp_send_one($s, $payload);
        if ($code >= 200 && $code < 300) $ok++;
        elseif ($code === 404 || $code === 410) q('DELETE FROM push_subs WHERE id=?', array($s['id'])); // urządzenie wyłączyło powiadomienia
    }
    return $ok;
}
