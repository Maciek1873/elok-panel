<?php
// ELOK – API modułu Umowy (wywoływane z api.php po zalogowaniu; akcje „um_…”)
if (!defined('ELOK')) { http_response_code(403); exit; }

function um_kl_clean() {
    $f = array('nazwa' => 255, 'siedziba' => 120, 'adres' => 255, 'nip' => 20, 'regon' => 20, 'rejestr' => 120, 'r_imie' => 80, 'r_nazwisko' => 80,
        'r_funkcja' => 120, 'email' => 190, 'telefon' => 60, 'kontakt' => 160);
    $d = array();
    foreach ($f as $k => $max) $d[$k] = gs($k, $max);
    $d['nip'] = preg_replace('/[^0-9A-Za-z]/', '', $d['nip']);
    $d['notatki'] = mb_substr(trim((string)g('notatki')), 0, 5000);
    return $d;
}
function um_list_row($r) {
    $p = json_decode((string)$r['pola'], true);
    $r['abonament'] = is_array($p) && isset($p['abonament']) ? um_num($p['abonament']) : null;
    $r['kwota'] = is_array($p) && isset($p['kwota']) ? um_num($p['kwota']) : null;
    unset($r['pola']);
    return $r;
}
function um_full($id) {
    $u = um_get_row($id);
    if (!$u) fail('Nie ma takiej umowy.');
    $k = um_klient($u['klient_id']);
    $vals = um_values($u);
    $fields = array();
    $p = um_pola($u);
    foreach (um_fields($u['tresc']) as $f) { $f['val'] = $vals[$f['key']]; $f['set'] = array_key_exists($f['key'], $p); $fields[] = $f; }
    $builtin = array();
    foreach (um_builtin_used($u['tresc']) as $b) $builtin[] = array('key' => $b, 'val' => isset($vals[$b]) ? $vals[$b] : '');
    $pow = $u['powiazana_id'] ? one('SELECT id,numer,szablon_nazwa,status FROM um_umowy WHERE id=?', array((int)$u['powiazana_id'])) : null;
    $dzieci = all('SELECT id,numer,szablon_nazwa,status FROM um_umowy WHERE powiazana_id=? ORDER BY id', array((int)$id));
    $html = $u['status'] === 'szkic' ? um_body($u, 'draft') : ($u['html'] ? $u['html'] : um_body($u, 'final'));
    $out = array(
        'u' => array_diff_key($u, array('html' => 1, 'token_hash' => 1, 'kod_hash' => 1, 'pola' => 1, 'tresc' => 1)),
        'tresc' => $u['tresc'], 'klient' => $k, 'fields' => $fields, 'builtin' => $builtin, 'html' => $html,
        'powiazana' => $pow, 'dzieci' => $dzieci,
        'log' => all('SELECT typ,info,ip,created_at FROM um_log WHERE umowa_id=? ORDER BY id DESC', array((int)$id)),
        'akc' => all('SELECT wersja,strona,imie,nazwisko,funkcja,firma,email,ip,sha,created_at FROM um_akceptacje WHERE umowa_id=? ORDER BY id', array((int)$id)),
        'link_aktywny' => $u['status'] === 'wyslana' && $u['token_exp'] && strtotime($u['token_exp']) >= time(),
        'pdf_final' => $u['pdf_plik'] !== '' ? 1 : 0,
    );
    return $out;
}

function um_api($a) {
    try {
    switch ($a) {

    // ---------- PULPIT UMÓW ----------
    case 'um_lista':
        $w = array('1=1'); $p = array();
        $st = gs('status');
        if ($st !== '' && isset($GLOBALS['UM_STATUSY'][$st])) { $w[] = 'u.status=?'; $p[] = $st; }
        if ($st === 'do_druku') { $w[] = "u.status='zawarta' AND u.wydruk_at IS NULL"; }
        if ((int)g('klient')) { $w[] = 'u.klient_id=?'; $p[] = (int)g('klient'); }
        if (gs('q') !== '') { $s = '%' . gs('q') . '%'; $w[] = '(u.numer LIKE ? OR k.nazwa LIKE ? OR k.nip LIKE ? OR u.szablon_nazwa LIKE ?)'; array_push($p, $s, $s, $s, $s); }
        $rows = all('SELECT u.id,u.numer,u.szablon_nazwa,u.typ,u.status,u.data_umowy,u.wyslana_at,u.zawarta_at,u.otwarta_at,u.token_exp,u.metoda,u.wydruk_at,u.pola,u.updated_at,u.created_at,u.powiazana_id,
            k.nazwa klient, k.id klient_id FROM um_umowy u LEFT JOIN um_klienci k ON k.id=u.klient_id WHERE ' . implode(' AND ', $w) . ' ORDER BY u.updated_at DESC, u.id DESC LIMIT 500', $p);
        $c = array();
        foreach (all('SELECT status, COUNT(*) n FROM um_umowy GROUP BY status') as $r) $c[$r['status']] = (int)$r['n'];
        $abo = 0.0; $abo_n = 0;
        foreach (all("SELECT pola FROM um_umowy WHERE status='zawarta'") as $r) { $pp = json_decode((string)$r['pola'], true); if (is_array($pp) && isset($pp['abonament'])) { $abo += um_num($pp['abonament']); $abo_n++; } }
        out(array('rows' => array_map('um_list_row', $rows), 'counts' => $c, 'abonamenty' => $abo, 'abonamenty_n' => $abo_n,
            'do_druku' => (int)val("SELECT COUNT(*) FROM um_umowy WHERE status='zawarta' AND wydruk_at IS NULL"),
            'ustawienia_ok' => trim(setting('um_f_nip')) !== '' && trim(setting('um_f_email')) !== '' && trim(setting('um_f_r_imie')) !== ''));

    case 'um_get':
        out(um_full((int)g('id')));

    case 'um_nowa':
        $k = um_klient((int)g('klient'));
        $sz = one('SELECT * FROM um_szablony WHERE id=?', array((int)g('szablon')));
        if (!$k || !$sz) fail('Wybierz klienta i szablon.');
        $pow = (int)g('powiazana') ? um_get_row((int)g('powiazana')) : null;
        $id = um_create($k['id'], $sz, $pow ? $pow['id'] : null);
        um_log($id, 'utworzona', 'Utworzona z szablonu „' . $sz['nazwa'] . '”' . ($pow ? ', powiązana z ' . $pow['numer'] : ''));
        out(array('id' => $id));

    case 'um_zapisz': // autozapis szkicu: pola, data, (opcjonalnie) własna treść
        $u = um_get_row((int)g('id'));
        if (!$u) fail('Nie ma takiej umowy.');
        if ($u['status'] !== 'szkic') fail('Treść jest zamrożona. Żeby zmienić umowę, cofnij ją do szkicu.');
        $pola = array();
        foreach ((array)g('pola') as $k => $v) if (is_string($k) && preg_match('/^[A-Za-z0-9_.\-]+$/', $k) && !um_builtin($k)) $pola[$k] = mb_substr((string)$v, 0, 4000);
        $data = preg_match('/^\d{4}-\d{2}-\d{2}$/', gs('data_umowy')) ? gs('data_umowy') : $u['data_umowy'];
        $tresc = g('tresc', null) !== null ? mb_substr((string)g('tresc'), 0, 400000) : $u['tresc'];
        $pow = g('powiazana', null) !== null ? ((int)g('powiazana') ?: null) : $u['powiazana_id'];
        q('UPDATE um_umowy SET pola=?, data_umowy=?, tresc=?, powiazana_id=?, updated_at=? WHERE id=?', array(json_encode($pola, JSON_UNESCAPED_UNICODE), $data, $tresc, $pow, now(), (int)$u['id']));
        if ($tresc !== $u['tresc'] && !val("SELECT COUNT(*) FROM um_log WHERE umowa_id=? AND typ='tresc_zmieniona' AND created_at > NOW() - INTERVAL 30 MINUTE", array((int)$u['id']))) um_log($u['id'], 'tresc_zmieniona', 'Zmieniono treść tej umowy (inna niż w szablonie)');
        $f = um_full((int)$u['id']);
        out(array('html' => $f['html'], 'fields' => $f['fields'], 'builtin' => $f['builtin']));

    case 'um_wyslij':
        $im = gs('imie', 80); $na = gs('nazwisko', 80); $fu = gs('funkcja', 120);
        if ($im === '' || $na === '' || $fu === '') fail('Podaj imię, nazwisko i funkcję osoby akceptującej w imieniu ELOK.');
        if (!g('akcept')) fail('Zaznacz akceptację treści w imieniu ELOK.');
        um_freeze_and_send((int)g('id'), array('imie' => $im, 'nazwisko' => $na, 'funkcja' => $fu));
        out(um_full((int)g('id')));

    case 'um_link_ponow':
        $u = um_get_row((int)g('id'));
        if (!$u || $u['status'] !== 'wyslana') fail('Link można wysłać ponownie tylko dla umowy czekającej na podpis.');
        $raw = um_new_token($u['id']);
        um_log($u['id'], 'link_nowy', 'Wygenerowano nowy link (poprzedni przestał działać)');
        if (!um_send_link_mail(um_get_row($u['id']), $raw)) fail('Nie udało się wysłać maila. Sprawdź adres nadawcy w Ustawieniach umów.');
        out(um_full($u['id']));

    case 'um_link_kopiuj': // nowy link do skopiowania (np. na WhatsApp) – poprzedni przestaje działać
        $u = um_get_row((int)g('id'));
        if (!$u || $u['status'] !== 'wyslana') fail('Umowa nie czeka na podpis.');
        $raw = um_new_token($u['id']);
        um_log($u['id'], 'link_nowy', 'Wygenerowano nowy link do skopiowania (poprzedni przestał działać)');
        out(array('link' => um_link($raw)));

    case 'um_cofnij':
        $u = um_get_row((int)g('id'));
        if (!$u || $u['status'] !== 'wyslana') fail('Cofnąć do szkicu można tylko umowę czekającą na podpis.');
        q('UPDATE um_umowy SET status=?, token_hash=NULL, token_exp=NULL, kod_hash=NULL, kod_exp=NULL, html=NULL, sha=NULL, updated_at=? WHERE id=?', array('szkic', now(), (int)$u['id']));
        um_log($u['id'], 'cofnieta', 'Cofnięta do szkicu (wersja ' . (int)$u['wersja'] . ', SHA-256 ' . $u['sha'] . ' wycofana, link nieaktywny; treść tej wersji zostaje w historii wersji)');
        out(um_full($u['id']));

    case 'um_anuluj':
        $u = um_get_row((int)g('id'));
        if (!$u || !in_array($u['status'], array('szkic', 'wyslana'), true)) fail('Anulować można szkic albo umowę czekającą na podpis.');
        q('UPDATE um_umowy SET status=?, anul_at=?, anul_powod=?, token_hash=NULL, token_exp=NULL, kod_hash=NULL, updated_at=? WHERE id=?', array('anulowana', now(), gs('powod'), now(), (int)$u['id']));
        um_log($u['id'], 'anulowana', 'Anulowana' . (gs('powod') !== '' ? ': ' . gs('powod') : ''));
        out(um_full($u['id']));

    case 'um_rozwiaz':
        $u = um_get_row((int)g('id'));
        if (!$u || $u['status'] !== 'zawarta') fail('Rozwiązać można tylko zawartą umowę.');
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', gs('data'))) fail('Podaj datę rozwiązania.');
        if (gs('powod') === '') fail('Podaj powód rozwiązania.');
        q('UPDATE um_umowy SET status=?, rozw_data=?, rozw_powod=?, updated_at=? WHERE id=?', array('rozwiazana', gs('data'), gs('powod'), now(), (int)$u['id']));
        um_log($u['id'], 'rozwiazana', 'Rozwiązana z dniem ' . um_data_pl(gs('data')) . ': ' . gs('powod'));
        out(um_full($u['id']));

    case 'um_duplikuj':
        $u = um_get_row((int)g('id'));
        if (!$u) fail('Nie ma takiej umowy.');
        $sz = one('SELECT * FROM um_szablony WHERE id=?', array((int)$u['szablon_id']));
        if (!$sz) $sz = array('id' => null, 'nazwa' => $u['szablon_nazwa'], 'typ' => $u['typ'], 'prefiks' => $u['prefiks'], 'tresc' => $u['tresc']);
        $kl = (int)g('klient') ?: (int)$u['klient_id'];
        $id = um_create($kl, $sz, $u['powiazana_id'], um_pola($u), $u['tresc']);
        um_log($id, 'utworzona', 'Duplikat umowy ' . $u['numer']);
        out(array('id' => $id));

    case 'um_usun':
        $u = um_get_row((int)g('id'));
        if (!$u || $u['status'] !== 'szkic' || (int)$u['wersja'] > 0) fail('Usunąć można tylko szkic, który nigdy nie był wysłany. Wysłaną umowę możesz anulować.');
        q('DELETE FROM um_log WHERE umowa_id=?', array((int)$u['id']));
        q('DELETE FROM um_umowy WHERE id=?', array((int)$u['id']));
        out(array());

    case 'um_pdf_ponow':
        $u = um_get_row((int)g('id'));
        if (!$u || $u['status'] !== 'zawarta') fail('PDF wysyłamy tylko dla zawartej umowy.');
        $pdf = um_pdf_final($u);
        if (!$pdf) fail('Brak pliku PDF tej umowy.');
        um_send_signed_mails($u, $pdf);
        out(um_full($u['id']));

    case 'um_wydruk':
        $u = um_get_row((int)g('id'));
        if (!$u) fail('Nie ma takiej umowy.');
        q('UPDATE um_umowy SET wydruk_at=? WHERE id=?', array(g('on') ? now() : null, (int)$u['id']));
        um_log($u['id'], 'wydruk', g('on') ? 'Oznaczono: wydrukowana i odłożona do segregatora' : 'Cofnięto oznaczenie wydruku');
        out(array());

    // ---------- KLIENCI ----------
    case 'um_klienci':
        $w = '1=1'; $p = array();
        if (gs('q') !== '') { $s = '%' . gs('q') . '%'; $w = '(k.nazwa LIKE ? OR k.nip LIKE ? OR k.email LIKE ? OR k.siedziba LIKE ?)'; $p = array($s, $s, $s, $s); }
        out(array('rows' => all("SELECT k.id,k.nazwa,k.siedziba,k.nip,k.email,k.telefon,k.r_imie,k.r_nazwisko,k.updated_at,
            (SELECT COUNT(*) FROM um_umowy u WHERE u.klient_id=k.id) umowy,
            (SELECT COUNT(*) FROM um_umowy u WHERE u.klient_id=k.id AND u.status='zawarta') zawarte
            FROM um_klienci k WHERE $w ORDER BY k.nazwa LIMIT 1000", $p)));

    case 'um_klient':
        $k = um_klient((int)g('id'));
        if (!$k) fail('Nie ma takiego klienta.');
        $um = all('SELECT id,numer,szablon_nazwa,typ,status,data_umowy,zawarta_at,updated_at,pola FROM um_umowy WHERE klient_id=? ORDER BY id DESC', array((int)$k['id']));
        out(array('k' => $k, 'umowy' => array_map('um_list_row', $um)));

    case 'um_klient_zapisz':
        $d = um_kl_clean();
        if ($d['nazwa'] === '') fail('Podaj nazwę firmy.');
        if ($d['email'] !== '' && !filter_var($d['email'], FILTER_VALIDATE_EMAIL)) fail('Adres e-mail wygląda na błędny.');
        $id = (int)g('id');
        if (!$id && $d['nip'] !== '' && ($dup = one('SELECT id,nazwa FROM um_klienci WHERE nip=?', array($d['nip']))) && !g('wymus')) fail('Klient z tym NIP już jest: ' . $dup['nazwa'], array('dup' => (int)$dup['id']));
        if ($id) {
            $sets = array(); $p = array();
            foreach ($d as $k => $v) { $sets[] = "$k=?"; $p[] = $v; }
            $p[] = now(); $p[] = $id;
            q('UPDATE um_klienci SET ' . implode(',', $sets) . ', updated_at=? WHERE id=?', $p);
        } else {
            $d['zgloszenie_id'] = (int)g('zgloszenie_id') ?: null; $d['firma_id'] = (int)g('firma_id') ?: null;
            $d['created_at'] = now(); $d['updated_at'] = now();
            q('INSERT INTO um_klienci(' . implode(',', array_keys($d)) . ') VALUES(' . implode(',', array_fill(0, count($d), '?')) . ')', array_values($d));
            $id = (int)db()->lastInsertId();
            if ($d['zgloszenie_id']) {
                $z = one('SELECT notatka FROM zgloszenia WHERE id=?', array($d['zgloszenie_id']));
                if ($z !== null) q('UPDATE zgloszenia SET notatka=? WHERE id=?', array(trim((string)$z['notatka'] . "\n[" . date('Y-m-d H:i') . '] Dodano jako klienta w module Umowy.'), $d['zgloszenie_id']));
            }
        }
        out(array('id' => $id));

    case 'um_klient_usun':
        $id = (int)g('id');
        if ((int)val('SELECT COUNT(*) FROM um_umowy WHERE klient_id=?', array($id))) fail('Ten klient ma umowy – nie można go usunąć.');
        q('DELETE FROM um_klienci WHERE id=?', array($id));
        out(array());

    case 'um_nip':
        out(array('d' => um_nip_lookup(gs('nip'))));

    case 'um_z_leada': // dane do formularza klienta ze zgłoszenia albo firmy z mailingu
        $d = array('nazwa' => '', 'email' => '', 'telefon' => '', 'kontakt' => '', 'siedziba' => '', 'notatki' => '', 'zgloszenie_id' => 0, 'firma_id' => 0);
        if ((int)g('zgloszenie')) {
            $z = one('SELECT * FROM zgloszenia WHERE id=?', array((int)g('zgloszenie')));
            if ($z) {
                $d = array_merge($d, array('nazwa' => $z['firma'], 'email' => $z['email'], 'telefon' => $z['telefon'], 'kontakt' => $z['osoba'], 'zgloszenie_id' => (int)$z['id'], 'firma_id' => (int)$z['firma_id']));
                $d['notatki'] = trim('Ze zgłoszenia z ' . substr((string)$z['created_at'], 0, 10) . ($z['czym'] ? "\nCzym się zajmuje: " . $z['czym'] : '') . ($z['co'] ? "\nCzego potrzebuje: " . $z['co'] : ''));
                if ($z['firma_id']) { $f = one('SELECT * FROM firmy WHERE id=?', array((int)$z['firma_id'])); if ($f) { $d['siedziba'] = $f['miasto']; if (!$d['email']) $d['email'] = $f['email']; } }
            }
        } elseif ((int)g('firma')) {
            $f = one('SELECT * FROM firmy WHERE id=?', array((int)g('firma')));
            if ($f) $d = array_merge($d, array('nazwa' => $f['nazwa'], 'email' => $f['email'], 'telefon' => $f['telefon'], 'kontakt' => $f['osoba'], 'siedziba' => $f['miasto'], 'firma_id' => (int)$f['id']));
        }
        $ex = null;
        if ($d['email'] !== '') $ex = one('SELECT id,nazwa FROM um_klienci WHERE email=? LIMIT 1', array($d['email']));
        out(array('d' => $d, 'istnieje' => $ex));

    // ---------- SZABLONY ----------
    case 'um_szablony':
        $rows = all('SELECT id,nazwa,typ,prefiks,kolejnosc,updated_at,(SELECT COUNT(*) FROM um_umowy u WHERE u.szablon_id=s.id) umowy FROM um_szablony s ORDER BY kolejnosc, id');
        out(array('rows' => $rows));

    case 'um_szablon':
        $s = one('SELECT * FROM um_szablony WHERE id=?', array((int)g('id')));
        if (!$s) fail('Nie ma takiego szablonu.');
        out(array('s' => $s, 'fields' => um_fields($s['tresc']), 'builtin' => um_builtin_used($s['tresc'])));

    case 'um_szablon_podglad':
        $tr = (string)g('tresc');
        $demo = array('umowa.numer' => (gs('prefiks') ?: 'ELOK/U') . '/001/' . date('Y'), 'umowa.data' => um_data_pl(date('Y-m-d')));
        foreach (um_firma() as $k => $v) $demo['firma.' . $k] = $v;
        out(array('html' => um_render($tr, $demo, 'draft'), 'fields' => um_fields($tr), 'builtin' => um_builtin_used($tr)));

    case 'um_szablon_zapisz':
        $nazwa = gs('nazwa', 160); $pref = preg_replace('/[^A-Za-z0-9\/_-]/', '', gs('prefiks', 40)); $typ = gs('typ', 20);
        if ($nazwa === '') fail('Podaj nazwę szablonu.');
        if ($pref === '') fail('Podaj prefiks numeracji, np. ELOK/W.');
        if (!isset($GLOBALS['UM_TYPY'][$typ])) $typ = 'inne';
        $tr = mb_substr((string)g('tresc'), 0, 400000);
        if ((int)g('id')) q('UPDATE um_szablony SET nazwa=?, prefiks=?, typ=?, tresc=?, updated_at=? WHERE id=?', array($nazwa, trim($pref, '/'), $typ, $tr, now(), (int)g('id')));
        else { q('INSERT INTO um_szablony(nazwa,typ,prefiks,tresc,kolejnosc,created_at,updated_at) VALUES(?,?,?,?,?,?,?)', array($nazwa, $typ, trim($pref, '/'), $tr, 99, now(), now())); }
        out(array('id' => (int)g('id') ?: (int)db()->lastInsertId()));

    case 'um_szablon_duplikuj':
        $s = one('SELECT * FROM um_szablony WHERE id=?', array((int)g('id')));
        if (!$s) fail('Nie ma takiego szablonu.');
        q('INSERT INTO um_szablony(nazwa,typ,prefiks,tresc,kolejnosc,created_at,updated_at) VALUES(?,?,?,?,?,?,?)', array($s['nazwa'] . ' (kopia)', $s['typ'], $s['prefiks'], $s['tresc'], (int)$s['kolejnosc'] + 1, now(), now()));
        out(array('id' => (int)db()->lastInsertId()));

    case 'um_szablon_usun':
        q('DELETE FROM um_szablony WHERE id=?', array((int)g('id'))); // umowy mają własną kopię treści
        out(array());

    // ---------- USTAWIENIA ----------
    case 'um_ust':
        $keys = array('um_f_nazwa', 'um_f_siedziba', 'um_f_adres', 'um_f_krs', 'um_f_nip', 'um_f_regon', 'um_f_r_imie', 'um_f_r_nazwisko', 'um_f_r_funkcja', 'um_f_email',
            'um_link_dni', 'um_nadawca_email', 'um_nadawca_nazwa', 'um_archiwum_email', 'um_gd_client_id', 'um_gd_konto');
        $o = array(); foreach ($keys as $k) $o[$k] = setting($k);
        $o['um_gd_secret_set'] = setting('um_gd_secret') !== '';
        $o['gd_ok'] = um_gd_ready(); $o['gd_redirect'] = um_gd_redirect();
        $o['nadawca_domyslny'] = setting('nadawca_email');
        $o['kopie'] = all('SELECT k.id,k.rodzaj,k.nazwa,k.drive_id,k.drive_err,k.mail_at,k.created_at,u.numer FROM um_kopie k LEFT JOIN um_umowy u ON u.id=k.umowa_id ORDER BY k.id DESC LIMIT 15');
        try { $o['katalog'] = um_dir(); } catch (Exception $e) { $o['katalog'] = 'BŁĄD: ' . $e->getMessage(); }
        $o['ostatnia_kopia'] = setting('um_backup_week');
        out($o);

    case 'um_ust_zapisz':
        $keys = array('um_f_nazwa' => 255, 'um_f_siedziba' => 120, 'um_f_adres' => 255, 'um_f_krs' => 40, 'um_f_nip' => 20, 'um_f_regon' => 20, 'um_f_r_imie' => 80,
            'um_f_r_nazwisko' => 80, 'um_f_r_funkcja' => 120, 'um_f_email' => 190, 'um_nadawca_email' => 190, 'um_nadawca_nazwa' => 120, 'um_archiwum_email' => 190, 'um_gd_client_id' => 255);
        foreach (array('um_f_email', 'um_nadawca_email', 'um_archiwum_email') as $e) if (gs($e) !== '' && !filter_var(gs($e), FILTER_VALIDATE_EMAIL)) fail('Błędny adres e-mail: ' . gs($e));
        foreach ($keys as $k => $max) setting_set($k, gs($k, $max));
        setting_set('um_link_dni', (string)max(1, min(60, (int)g('um_link_dni') ?: 7)));
        if (gs('um_gd_secret') !== '') setting_set('um_gd_secret', gs('um_gd_secret', 255));
        out(array());

    case 'um_test_mail':
        $to = gs('to') ?: setting('um_f_email');
        if (!filter_var($to, FILTER_VALIDATE_EMAIL)) fail('Podaj adres, na który wysłać test.');
        $t = 'Test maili z modułu Umowy';
        $p = array('To jest testowa wiadomość z modułu Umowy panelu ELOK.', 'Jeśli ją widzisz w skrzynce odbiorczej (nie w SPAM-ie), maile do klientów z linkiem do podpisu i kodem też dojdą.');
        list($from) = um_from();
        if (!um_mail($to, $t, um_mail_html($t, $p, 'Otwórz panel', BASE_URL . '/#umowy'), um_mail_text($t, $p), array(array('test.pdf', um_pdf_from_html('<html><body><p>Test PDF – polskie znaki: zażółć gęślą jaźń ĄĆĘŁŃÓŚŹŻ.</p></body></html>', 'TEST'), 'application/pdf'))))
            fail('Serwer nie przyjął maila. Sprawdź adres nadawcy (' . $from . ') – musi to być istniejąca skrzynka na domenie elok-agency.pl.');
        out(array('to' => $to, 'from' => $from));

    case 'um_gd_rozlacz':
        setting_set('um_gd_refresh', ''); setting_set('um_gd_konto', '');
        out(array());

    case 'um_kopia_teraz':
        $z = um_backup_zip();
        if (!$z) fail('Na serwerze brakuje modułu ZIP (ZipArchive).');
        out(array('nazwa' => $z[1]));

    default:
        fail('Nieznana akcja');
    }
    } catch (Exception $e) {
        if (db()->inTransaction()) db()->rollBack();
        error_log('ELOK umowy: ' . $e->getMessage());
        fail($e->getMessage());
    }
}
