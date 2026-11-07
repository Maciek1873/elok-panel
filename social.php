<?php
// ELOK – Publikacje: jeden filmik + opis → Facebook (Reels), Instagram (Reels) i przypomnienie o TikToku.
// Publikacja przez Meta Graph API. Filmik leży w uploads/social/ (publiczny adres – Meta pobiera go sama).
if (!defined('ELOK')) { http_response_code(403); exit; }

function soc_migrate() {
    db()->exec("CREATE TABLE IF NOT EXISTS social_posty (
      id INT AUTO_INCREMENT PRIMARY KEY, tytul VARCHAR(160) NOT NULL DEFAULT '', opis TEXT, plik VARCHAR(190) NOT NULL DEFAULT '',
      kiedy DATETIME NULL, stan VARCHAR(20) NOT NULL DEFAULT 'szkic',
      fb TINYINT NOT NULL DEFAULT 0, fb_status VARCHAR(20) NOT NULL DEFAULT '', fb_id VARCHAR(64) NOT NULL DEFAULT '', fb_err VARCHAR(500) NOT NULL DEFAULT '',
      ig TINYINT NOT NULL DEFAULT 0, ig_status VARCHAR(20) NOT NULL DEFAULT '', ig_container VARCHAR(64) NOT NULL DEFAULT '', ig_id VARCHAR(64) NOT NULL DEFAULT '', ig_err VARCHAR(500) NOT NULL DEFAULT '', ig_start DATETIME NULL,
      tt TINYINT NOT NULL DEFAULT 0, tt_status VARCHAR(20) NOT NULL DEFAULT '',
      created_at DATETIME NOT NULL, KEY sk (stan, kiedy)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
}
function soc_dir() { $d = __DIR__ . '/uploads/social'; if (!is_dir($d)) @mkdir($d, 0755, true); return $d; }
function soc_url($plik) { return rtrim(BASE_URL, '/') . '/uploads/social/' . rawurlencode($plik); }

// ---------- Meta Graph API ----------
function meta_ver() { return setting('meta_ver', 'v25.0') ?: 'v25.0'; }
function meta_base() { return defined('META_URL') ? META_URL : 'https://graph.facebook.com'; }
function meta_rup() { return defined('META_RUP_URL') ? META_RUP_URL : 'https://rupload.facebook.com'; }
// $method GET/POST, $path np. "123/video_reels"; zwraca [tablica_odpowiedzi, kod_http, błąd]
function meta_call($method, $path, $params = array(), $headers = array(), $url = null) {
    $u = $url ? $url : meta_base() . '/' . meta_ver() . '/' . ltrim($path, '/');
    $c = curl_init();
    if ($method === 'GET') { $u .= (strpos($u, '?') === false ? '?' : '&') . http_build_query($params); }
    else { curl_setopt($c, CURLOPT_POST, true); curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($params)); }
    curl_setopt_array($c, array(CURLOPT_URL => $u, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 60, CURLOPT_CONNECTTIMEOUT => 15, CURLOPT_HTTPHEADER => $headers));
    $raw = curl_exec($c); $code = (int)curl_getinfo($c, CURLINFO_HTTP_CODE); $cerr = curl_error($c); curl_close($c);
    if ($raw === false) return array(null, 0, 'Brak połączenia z Facebookiem: ' . $cerr);
    $j = json_decode($raw, true);
    if (!is_array($j)) return array(null, $code, 'Dziwna odpowiedź Facebooka (HTTP ' . $code . ')');
    if (isset($j['error'])) {
        $m = isset($j['error']['message']) ? $j['error']['message'] : 'błąd';
        if (isset($j['error']['error_user_msg'])) $m .= ' – ' . $j['error']['error_user_msg'];
        $ec = isset($j['error']['code']) ? (int)$j['error']['code'] : 0;
        if ($ec === 190 || $ec === 102 || stripos($m, 'impersonating') !== false || stripos($m, 'access token') !== false || stripos($m, 'session has expired') !== false)
            return array($j, $code, 'Połączenie z Facebookiem wygasło. W Publikacjach kliknij „zmień”, połącz ponownie i kliknij „Spróbuj ponownie”.');
        return array($j, $code, 'Facebook: ' . $m);
    }
    return array($j, $code, '');
}
function meta_ok() { return setting('meta_page_id') !== '' && setting('meta_page_token') !== ''; }

// Połączenie: krótki token użytkownika → długi → lista stron (z tokenami stron, które nie wygasają)
function meta_connect($appId, $secret, $userToken) {
    list($j, , $e) = meta_call('GET', 'oauth/access_token', array('grant_type' => 'fb_exchange_token', 'client_id' => $appId, 'client_secret' => $secret, 'fb_exchange_token' => $userToken));
    if ($e) return array(null, $e);
    $long = isset($j['access_token']) ? $j['access_token'] : '';
    if ($long === '') return array(null, 'Facebook nie zwrócił tokenu');
    setting_set('meta_app_id', $appId); setting_set('meta_app_secret', $secret); setting_set('meta_user_token', $long);
    return meta_pages($long);
}
function meta_pages($long) {
    list($j, , $e) = meta_call('GET', 'me/accounts', array('fields' => 'id,name,access_token,instagram_business_account{id,username}', 'limit' => 50, 'access_token' => $long));
    if ($e) return array(null, $e);
    $out = array();
    foreach ((array)(isset($j['data']) ? $j['data'] : array()) as $p) {
        $out[] = array('id' => $p['id'], 'name' => $p['name'], 'token' => $p['access_token'],
            'ig_id' => isset($p['instagram_business_account']['id']) ? $p['instagram_business_account']['id'] : '',
            'ig_name' => isset($p['instagram_business_account']['username']) ? $p['instagram_business_account']['username'] : '');
    }
    if (!$out) return array(null, 'Nie widzę żadnej strony na Facebooku. Przy tworzeniu tokenu zaznacz swoją stronę ELOK.');
    return array($out, '');
}
function meta_set_page($p) {
    setting_set('meta_page_id', $p['id']); setting_set('meta_page_name', $p['name']); setting_set('meta_page_token', $p['token']);
    setting_set('meta_ig_id', $p['ig_id']); setting_set('meta_ig_name', $p['ig_name']);
}

// ---------- publikacja ----------
function soc_fb_rup_bin($vid, $tok, $path) {
    if (!is_file($path)) return 'Facebook: brak pliku na serwerze';
    $size = filesize($path); $data = file_get_contents($path);
    $c = curl_init(meta_rup() . '/video-upload/' . meta_ver() . '/' . $vid);
    curl_setopt_array($c, array(CURLOPT_POST => true, CURLOPT_POSTFIELDS => $data, CURLOPT_RETURNTRANSFER => true, CURLOPT_TIMEOUT => 600, CURLOPT_CONNECTTIMEOUT => 20,
        CURLOPT_HTTPHEADER => array('Authorization: OAuth ' . $tok, 'offset: 0', 'file_size: ' . $size, 'Content-Type: application/octet-stream')));
    $raw = curl_exec($c); $cerr = curl_error($c); curl_close($c); unset($data);
    if ($raw === false) return 'Facebook: wysyłka pliku przerwana (' . $cerr . ')';
    $j = json_decode($raw, true);
    if (!is_array($j)) return 'Facebook: dziwna odpowiedź przy wysyłce pliku';
    if (isset($j['error'])) return 'Facebook: ' . (isset($j['error']['message']) ? $j['error']['message'] : 'błąd wysyłki');
    if (isset($j['debug_info']['message'])) return 'Facebook: ' . $j['debug_info']['message'];
    if (isset($j['success']) && !$j['success']) return 'Facebook: plik nie został przyjęty';
    return '';
}
function soc_fb_publish($p) {
    $page = setting('meta_page_id'); $tok = setting('meta_page_token');
    list($j, , $e) = meta_call('POST', $page . '/video_reels', array('upload_phase' => 'start', 'access_token' => $tok));
    if ($e) return array('', $e);
    $vid = isset($j['video_id']) ? $j['video_id'] : '';
    if ($vid === '') return array('', 'Facebook: brak video_id');
    // 1) wysyłamy plik bezpośrednio z serwera (pewniejsze niż każdorazowe pobieranie przez Facebooka z linku)
    $e = soc_fb_rup_bin($vid, $tok, soc_dir() . '/' . $p['plik']);
    if ($e) { // 2) awaryjnie: Facebook sam pobiera plik z linku
        list($j, , $e2) = meta_call('POST', '', array(), array('Authorization: OAuth ' . $tok, 'file_url: ' . soc_url($p['plik'])), meta_rup() . '/video-upload/' . meta_ver() . '/' . $vid);
        if ($e2) return array('', $e . ' / ' . $e2);
    }
    sleep(3);
    list($j, , $e) = meta_call('POST', $page . '/video_reels', array('upload_phase' => 'finish', 'video_id' => $vid, 'video_state' => 'PUBLISHED', 'description' => (string)$p['opis'], 'access_token' => $tok));
    if ($e) return array('', $e);
    return array($vid, '');
}
function soc_ig_create($p) {
    list($j, , $e) = meta_call('POST', setting('meta_ig_id') . '/media', array('media_type' => 'REELS', 'video_url' => soc_url($p['plik']), 'caption' => mb_substr((string)$p['opis'], 0, 2200), 'share_to_feed' => 'true', 'access_token' => setting('meta_page_token')));
    if ($e) return array('', $e);
    return array(isset($j['id']) ? $j['id'] : '', isset($j['id']) ? '' : 'Instagram: brak id kontenera');
}
function soc_ig_step($p) { // zwraca [status, ig_id, błąd]; status: 'czeka' | 'gotowe' | 'blad'
    $tok = setting('meta_page_token');
    list($j, , $e) = meta_call('GET', $p['ig_container'], array('fields' => 'status_code,status', 'access_token' => $tok));
    if ($e) return array('blad', '', $e);
    $sc = isset($j['status_code']) ? $j['status_code'] : '';
    if ($sc === 'ERROR' || $sc === 'EXPIRED') return array('blad', '', 'Instagram nie przyjął filmiku (' . (isset($j['status']) ? $j['status'] : $sc) . ')');
    if ($sc !== 'FINISHED') {
        if ($p['ig_start'] && time() - strtotime($p['ig_start']) > 3600) return array('blad', '', 'Instagram przetwarzał filmik ponad godzinę – spróbuj ponownie');
        return array('czeka', '', '');
    }
    list($j, , $e) = meta_call('POST', setting('meta_ig_id') . '/media_publish', array('creation_id' => $p['ig_container'], 'access_token' => $tok));
    if ($e) return array('blad', '', $e);
    return array('gotowe', isset($j['id']) ? $j['id'] : '', '');
}

// Uruchamiane z crona (co 5–15 min) i przyciskiem „Opublikuj teraz”
function soc_run($onlyId = 0) {
    soc_migrate();
    if ((int)val("SELECT GET_LOCK('elok_soc', " . ($onlyId ? 8 : 0) . ")") !== 1) return 'publikacja już trwa – za chwilę';
    try { $r = soc_run_inner($onlyId); } catch (Exception $e) { val("SELECT RELEASE_LOCK('elok_soc')"); throw $e; }
    val("SELECT RELEASE_LOCK('elok_soc')");
    return $r;
}
function soc_run_inner($onlyId) {
    $where = $onlyId ? 'id=' . (int)$onlyId : "stan='zaplanowany' AND kiedy<=NOW()";
    $rows = all("SELECT * FROM social_posty WHERE $where AND stan IN('zaplanowany','w_toku') ORDER BY kiedy LIMIT 5");
    if (!$onlyId) $rows = array_merge($rows, all("SELECT * FROM social_posty WHERE stan='w_toku' AND id NOT IN(" . ($rows ? implode(',', array_map(function ($r) { return (int)$r['id']; }, $rows)) : '0') . ") LIMIT 5"));
    $log = array();
    foreach ($rows as $p) {
        $tag = mb_substr($p['tytul'] !== '' ? $p['tytul'] : mb_substr((string)$p['opis'], 0, 40), 0, 60);
        if ($p['stan'] === 'zaplanowany') { q("UPDATE social_posty SET stan='w_toku' WHERE id=?", array($p['id'])); $p['stan'] = 'w_toku'; }
        // Facebook
        if ($p['fb'] && $p['fb_status'] === '') {
            if (!meta_ok()) { $p['fb_status'] = 'blad'; $p['fb_err'] = 'Nie połączono z Facebookiem (Ustawienia → Facebook i Instagram)'; }
            else { list($id, $e) = soc_fb_publish($p); $p['fb_status'] = $e ? 'blad' : 'gotowe'; $p['fb_id'] = $id; $p['fb_err'] = mb_substr($e, 0, 500); }
            q('UPDATE social_posty SET fb_status=?, fb_id=?, fb_err=? WHERE id=?', array($p['fb_status'], $p['fb_id'], $p['fb_err'], $p['id']));
            $log[] = 'FB ' . $p['fb_status'];
        }
        // Instagram (dwa etapy: kontener → po przetworzeniu publikacja)
        if ($p['ig'] && in_array($p['ig_status'], array('', 'czeka'), true)) {
            if (!meta_ok() || setting('meta_ig_id') === '') { $p['ig_status'] = 'blad'; $p['ig_err'] = 'Brak konta Instagram połączonego ze stroną'; }
            elseif ($p['ig_container'] === '') {
                list($cid, $e) = soc_ig_create($p);
                if ($e) { $p['ig_status'] = 'blad'; $p['ig_err'] = mb_substr($e, 0, 500); }
                else { $p['ig_status'] = 'czeka'; $p['ig_container'] = $cid; $p['ig_start'] = now(); }
            } else {
                list($st, $mid, $e) = soc_ig_step($p);
                $p['ig_status'] = $st; if ($mid) $p['ig_id'] = $mid; if ($e) $p['ig_err'] = mb_substr($e, 0, 500);
            }
            q('UPDATE social_posty SET ig_status=?, ig_container=?, ig_id=?, ig_err=?, ig_start=? WHERE id=?', array($p['ig_status'], $p['ig_container'], $p['ig_id'], $p['ig_err'], $p['ig_start'], $p['id']));
            $log[] = 'IG ' . $p['ig_status'];
        }
        // TikTok – przypomnienie z opisem (publikujesz ręcznie)
        if ($p['tt'] && $p['tt_status'] === '') {
            push('Dziś na TikToka: ' . $tag, mb_substr((string)$p['opis'], 0, 900), BASE_URL . '/#publikacje');
            $p['tt_status'] = 'przypomniano';
            q("UPDATE social_posty SET tt_status='przypomniano' WHERE id=?", array($p['id']));
        }
        // koniec?
        $wait = ($p['ig'] && $p['ig_status'] === 'czeka');
        if (!$wait) {
            $err = ($p['fb'] && $p['fb_status'] === 'blad') || ($p['ig'] && $p['ig_status'] === 'blad');
            q('UPDATE social_posty SET stan=? WHERE id=?', array($err ? 'blad' : 'opublikowany', $p['id']));
            $ok = array(); if ($p['fb'] && $p['fb_status'] === 'gotowe') $ok[] = 'Facebook'; if ($p['ig'] && $p['ig_status'] === 'gotowe') $ok[] = 'Instagram';
            if ($err) push('Publikacja z błędem: ' . $tag, trim(($p['fb_err'] ? 'FB: ' . $p['fb_err'] . "\n" : '') . ($p['ig_err'] ? 'IG: ' . $p['ig_err'] : '')), BASE_URL . '/#publikacje');
            elseif ($ok) push('Opublikowano: ' . $tag, implode(' + ', $ok), BASE_URL . '/#publikacje');
        }
    }
    return $log ? implode(', ', $log) : 'nic do publikacji';
}
