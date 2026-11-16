<?php
// ELOK – publiczna strona akceptacji umowy (bez logowania, dostęp tylko przez jednorazowy link z tokenem)
define('ELOK', 1);
require __DIR__ . '/lib.php';
require __DIR__ . '/umowy.php';
start_session();
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('X-Content-Type-Options: nosniff');
header('Cache-Control: no-store');
header('X-Robots-Tag: noindex, nofollow');

$t = isset($_GET['t']) ? (string)$_GET['t'] : '';
if (empty($_SESSION['pod_n'])) $_SESSION['pod_n'] = token();

function pod_json($a, $code = 200) { http_response_code($code); header('Content-Type: application/json; charset=utf-8'); echo json_encode($a, JSON_UNESCAPED_UNICODE); exit; }

// ---------- AKCJE (POST) ----------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $hdr = isset($_SERVER['HTTP_X_POD']) ? (string)$_SERVER['HTTP_X_POD'] : '';
    if (!hash_equals($_SESSION['pod_n'], $hdr)) pod_json(array('ok' => false, 'error' => 'Sesja wygasła – odśwież stronę.'));
    $in = json_decode(file_get_contents('php://input'), true); if (!is_array($in)) $in = array();
    $s = function ($k, $max = 120) use ($in) { return mb_substr(trim(isset($in[$k]) ? (string)$in[$k] : ''), 0, $max); };
    try {
        um_migrate_if();
        $u = um_by_token($t);
        if (!$u || !empty($u['_expired'])) pod_json(array('ok' => false, 'error' => 'Link jest nieaktywny albo wygasł. Poproś o nowy link.'));
        $a = isset($_GET['a']) ? (string)$_GET['a'] : '';
        // telefon po zeskanowaniu QR: zapis podpisu
        if ($a === 'rys_zapisz') {
            um_rys_save($u, isset($in['r']) ? (string)$in['r'] : '', isset($in['png']) ? (string)$in['png'] : '');
            pod_json(array('ok' => true));
        }
        if ($a === 'rys_status') pod_json(array('ok' => true, 'png' => um_rys_status($u, $_SESSION['pod_n'])));
        $osoba = array('imie' => $s('imie', 80), 'nazwisko' => $s('nazwisko', 80), 'funkcja' => $s('funkcja', 120));
        if ($osoba['imie'] === '' || $osoba['nazwisko'] === '' || $osoba['funkcja'] === '') pod_json(array('ok' => false, 'error' => 'Podaj imię, nazwisko i funkcję.'));
        if (empty($in['upr']) || empty($in['akc'])) pod_json(array('ok' => false, 'error' => 'Zaznacz oba oświadczenia.'));
        if ($a === 'rys_start') {
            $raw = um_rys_start($u, $_SESSION['pod_n']);
            um_log($u['id'], 'podpis_start', 'Rozpoczęto składanie podpisu (' . trim($osoba['imie'] . ' ' . $osoba['nazwisko']) . ')');
            pod_json(array('ok' => true, 'r' => $raw, 'url' => um_link($t) . '&r=' . $raw));
        }
        if ($a === 'kod') {
            if (!um_rys_done($u)) pod_json(array('ok' => false, 'error' => 'Najpierw złóż podpis (przycisk „Podpisz”).'));
            $mail = um_send_code($u);
            pod_json(array('ok' => true, 'email' => $mail));
        }
        if ($a === 'potwierdz') {
            um_confirm($u, $s('kod', 12), $osoba);
            pod_json(array('ok' => true));
        }
        pod_json(array('ok' => false, 'error' => 'Nieznana akcja.'));
    } catch (Exception $e) {
        if (db()->inTransaction()) db()->rollBack();
        pod_json(array('ok' => false, 'error' => $e->getMessage()));
    }
}

// ---------- TELEFON: strona do podpisu palcem (po zeskanowaniu QR) ----------
if (isset($_GET['r']) && !isset($_GET['pdf'])) {
    um_migrate_if();
    $u = um_by_token($t);
    $r = ($u && empty($u['_expired'])) ? um_rys_get($u, (string)$_GET['r']) : null;
    header('Content-Type: text/html; charset=utf-8');
    $k = $r ? json_decode((string)$u['klient_json'], true) : array();
    ?><!DOCTYPE html><html lang="pl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no"><meta name="robots" content="noindex,nofollow"><title>Podpis · ELOK</title><link rel="icon" href="ikona_elok.png?v=2">
<style>*{box-sizing:border-box}html,body{margin:0;height:100%;background:#F5F4F0;color:#151515;font-family:Inter,-apple-system,Segoe UI,Arial,sans-serif;-webkit-font-smoothing:antialiased;overscroll-behavior:none}
.w{display:flex;flex-direction:column;height:100%;padding:14px 14px calc(14px + env(safe-area-inset-bottom))}.logo{font-weight:800;font-size:22px;letter-spacing:-1px}.logo span{color:#C5A059}
h1{font-size:18px;margin:10px 0 2px}.m{color:#8A867C;font-size:13px}.pad{flex:1;min-height:200px;margin:12px 0;background:#fff;border:2px dashed #D9D2C2;border-radius:16px;position:relative}
.pad canvas{position:absolute;inset:0;width:100%;height:100%;border-radius:16px}.pad .ln{position:absolute;left:8%;right:8%;bottom:28%;border-top:1px solid #CFC8B8;pointer-events:none}.pad .hint{position:absolute;left:0;right:0;bottom:calc(28% - 26px);text-align:center;color:#B9B4A8;font-size:13px;pointer-events:none}
.row{display:flex;gap:10px}.b{flex:1;height:52px;border-radius:13px;border:1px solid #E7E4DC;background:#fff;font:inherit;font-weight:700;font-size:16px}.b.d{background:#151515;color:#fff;border-color:#151515}.b[disabled]{opacity:.35}
.done{flex:1;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center}.ic{width:64px;height:64px;border-radius:50%;background:#EAF5EE;color:#2E7D4F;font-size:30px;font-weight:800;display:flex;align-items:center;justify-content:center;margin-bottom:12px}.err{color:#B3261E;font-weight:600;font-size:14px;min-height:18px}
@media (orientation:landscape) and (max-height:500px){.pad{min-height:150px}h1{display:none}}</style></head><body><div class="w">
<div class="logo">ELOK<span>.</span></div>
<?php if (!$r): ?><div class="done"><div class="ic" style="background:#F3EFEF;color:#8A5A5A">!</div><b>Kod QR wygasł albo został już użyty</b><p class="m">Wróć do komputera i kliknij „Podpisz” jeszcze raz.</p></div>
<?php elseif ($r['done_at']): ?><div class="done"><div class="ic">&#10003;</div><b>Podpis zapisany</b><p class="m">Wróć do komputera i dokończ akceptację kodem z maila.</p></div>
<?php else: ?>
<h1>Podpisz palcem</h1><div class="m">Umowa nr <?= h($u['numer']) ?> · <?= h(isset($k['nazwa']) ? $k['nazwa'] : '') ?>. Najlepiej obróć telefon poziomo.</div>
<div class="pad" id="pad"><canvas id="cv"></canvas><div class="ln"></div><div class="hint">podpis</div></div>
<div class="err" id="err"></div>
<div class="row"><button class="b" onclick="P.clear()">Wyczyść</button><button class="b d" id="ok" disabled onclick="zapisz()">Zatwierdź podpis</button></div>
<div id="fin" class="done" style="display:none"><div class="ic">&#10003;</div><b>Podpis zapisany</b><p class="m">Wróć do komputera – zobaczysz tam swój podpis.<br>Teraz potwierdzisz akceptację kodem z maila.</p></div>
<script src="podpis_pad.js?v=<?= (int)@filemtime(__DIR__ . '/podpis_pad.js') ?>"></script>
<script>
var P=SigPad(document.getElementById('cv')),N=<?= json_encode($_SESSION['pod_n']) ?>,T=<?= json_encode($t) ?>,R=<?= json_encode((string)$_GET['r']) ?>;
P.onchange=function(){document.getElementById('ok').disabled=!P.ok();};
function zapisz(){var b=document.getElementById('ok');b.disabled=true;b.textContent='Zapisuję…';
 var x=new XMLHttpRequest();x.open('POST','podpis.php?t='+encodeURIComponent(T)+'&a=rys_zapisz');x.setRequestHeader('Content-Type','application/json');x.setRequestHeader('X-Pod',N);
 x.onload=function(){var r={};try{r=JSON.parse(x.responseText);}catch(e){}if(!r.ok){document.getElementById('err').textContent=r.error||'Błąd – spróbuj ponownie.';b.disabled=false;b.textContent='Zatwierdź podpis';return;}
  ['pad','err'].forEach(function(i){document.getElementById(i).style.display='none';});document.querySelector('.row').style.display='none';document.querySelector('h1').style.display='none';document.getElementById('fin').style.display='flex';};
 x.onerror=function(){document.getElementById('err').textContent='Brak internetu – spróbuj ponownie.';b.disabled=false;b.textContent='Zatwierdź podpis';};
 x.send(JSON.stringify({r:R,png:P.png()}));}
</script>
<?php endif; ?>
</div></body></html><?php
    exit;
}

// ---------- PDF ----------
$u = um_by_token($t, true);
if ($u && empty($u['_expired']) && isset($_GET['pdf'])) {
    try {
        $pdf = $u['status'] === 'zawarta' ? um_pdf_final($u) : um_pdf_tresc($u);
        um_log($u['id'], 'pdf_pobrany', 'Klient pobrał PDF ' . ($u['status'] === 'zawarta' ? '(zawarta, z kartą)' : '(do akceptacji)'));
        header('Content-Type: application/pdf');
        header('Content-Disposition: attachment; filename="' . um_pdf_name($u, $u['status'] === 'zawarta' ? '_zawarta' : '') . '"');
        echo $pdf; exit;
    } catch (Exception $e) { http_response_code(500); echo 'Nie udało się przygotować PDF.'; exit; }
}

// zapis otwarcia (bez powtórzeń z tego samego IP przez 20 minut)
if ($u && empty($u['_expired']) && $u['status'] === 'wyslana') {
    if (empty($u['otwarta_at'])) q('UPDATE um_umowy SET otwarta_at=? WHERE id=?', array(now(), (int)$u['id']));
    if (!val("SELECT COUNT(*) FROM um_log WHERE umowa_id=? AND typ='otwarta' AND ip=? AND created_at > NOW() - INTERVAL 20 MINUTE", array((int)$u['id'], um_ip())))
        um_log($u['id'], 'otwarta', 'Klient otworzył stronę umowy');
}
header('Content-Type: text/html; charset=utf-8');
$ok = $u && empty($u['_expired']);
$k = $ok ? json_decode((string)$u['klient_json'], true) : array();
$f = $ok ? json_decode((string)$u['firma_json'], true) : array();
$elok = $ok ? one("SELECT * FROM um_akceptacje WHERE umowa_id=? AND wersja=? AND strona='elok' ORDER BY id DESC LIMIT 1", array((int)$u['id'], (int)$u['wersja'])) : null;
$kli = $ok && $u['status'] === 'zawarta' ? one("SELECT * FROM um_akceptacje WHERE umowa_id=? AND wersja=? AND strona='klient' ORDER BY id DESC LIMIT 1", array((int)$u['id'], (int)$u['wersja'])) : null;
?><!DOCTYPE html>
<html lang="pl"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><meta name="robots" content="noindex,nofollow">
<title><?= $ok ? 'Umowa ' . h($u['numer']) . ' · ELOK' : 'Umowa · ELOK' ?></title>
<link rel="icon" href="ikona_elok.png?v=2">
<style>
:root{--bg:#F5F4F0;--ink:#151515;--ink2:#4A4740;--mut:#8A867C;--line:#E7E4DC;--gold:#C5A059;--gold-d:#8B6B30;--ok:#2E7D4F;--okl:#EAF5EE;--err:#B3261E}
*{box-sizing:border-box}html,body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,-apple-system,Segoe UI,Arial,sans-serif;font-size:15px;line-height:1.55;-webkit-font-smoothing:antialiased}
.top{max-width:860px;margin:0 auto;padding:22px 18px 6px;display:flex;align-items:center;justify-content:space-between;gap:12px}
.logo{font-weight:800;font-size:26px;letter-spacing:-1px}.logo span{color:var(--gold)}
.wrap{max-width:860px;margin:0 auto;padding:6px 18px 60px}
.card{background:#fff;border:1px solid var(--line);border-radius:18px;box-shadow:0 1px 2px rgba(20,18,10,.04),0 10px 30px rgba(20,18,10,.06)}
.intro{padding:22px 24px}.intro h1{font-size:22px;margin:0 0 6px;letter-spacing:-.3px}.intro p{margin:0;color:var(--ink2)}
.meta{display:flex;flex-wrap:wrap;gap:8px 18px;margin-top:12px;font-size:13px;color:var(--mut)}.meta b{color:var(--ink)}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:46px;padding:0 20px;border-radius:12px;border:1px solid var(--line);background:#fff;font-weight:700;font-size:15px;cursor:pointer;text-decoration:none;color:var(--ink);font-family:inherit}
.btn.dark{background:var(--ink);border-color:var(--ink);color:#fff}.btn[disabled]{opacity:.38;pointer-events:none}.btn.sm{height:38px;font-size:13.5px;padding:0 14px}
.doc{margin-top:14px;padding:44px 52px;font-family:Georgia,"Times New Roman",serif;font-size:15.5px;line-height:1.65;color:#1b1a17}
.doc h2{font-family:Inter,Arial,sans-serif;font-size:19px;text-align:center;margin:0 0 18px;letter-spacing:-.2px}
.doc h3{font-family:Inter,Arial,sans-serif;font-size:15.5px;margin:26px 0 8px}.doc h4{font-family:Inter,Arial,sans-serif;font-size:14.5px;margin:18px 0 6px}
.doc p{margin:0 0 11px}.doc ol,.doc ul{margin:0 0 11px;padding-left:24px}.doc li{margin:0 0 6px}.doc hr{border:0;border-top:1px solid var(--line);margin:30px 0}
.doc table{width:100%;border-collapse:collapse;margin:6px 0 16px;font-size:14px;font-family:Inter,Arial,sans-serif}.doc th,.doc td{border:1px solid #DDD8CB;padding:8px 10px;text-align:left;vertical-align:top}.doc th{background:#F5F2EA}
.sign{margin-top:14px;padding:24px}.sign h2{font-size:19px;margin:0 0 4px}.sign .lead{color:var(--ink2);margin:0 0 16px}
label.f{display:block;font-size:12.5px;font-weight:700;color:#5E5A51;margin:12px 0 5px}
input[type=text]{width:100%;height:48px;padding:0 14px;border:1px solid #DEDAD0;border-radius:12px;background:#FCFBF9;font:inherit;font-size:16px;outline:0}
input[type=text]:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(197,160,89,.18)}
.g3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:12px}
.chk{display:flex;gap:12px;align-items:flex-start;margin-top:14px;padding:14px;border:1px solid var(--line);border-radius:12px;cursor:pointer;background:#FCFBF9}
.chk input{width:20px;height:20px;margin-top:2px;accent-color:var(--ink);flex-shrink:0}
.hint{font-size:13px;color:var(--mut);margin-top:10px}
.sig{display:none;margin-top:14px;padding:14px;border:1px solid var(--line);border-radius:12px;background:#FCFBF9;align-items:center;gap:16px;flex-wrap:wrap}.sig.on{display:flex}.sig img{height:70px;max-width:280px;background:#fff;border-radius:8px;padding:4px 10px;border:1px solid var(--line)}.sig-l{font-weight:700;font-size:13px;color:#5E5A51}
.ovl{display:none;position:fixed;inset:0;background:rgba(21,21,21,.5);z-index:50;align-items:center;justify-content:center;padding:16px}.ovl.on{display:flex}.ovl-in{background:#fff;border-radius:20px;padding:24px;max-width:520px;width:100%;text-align:center}.ovl h2{font-size:19px;margin:0 0 6px}
.qr{display:inline-block;padding:12px;background:#fff;border:1px solid var(--line);border-radius:14px;margin:12px 0 8px}.qr img,.qr svg{display:block;width:220px;height:220px}.o-alt{margin-top:14px;font-size:13px}.o-alt a{color:var(--gold-d)}
.pad{position:relative;height:220px;border:2px dashed #D9D2C2;border-radius:14px;background:#fff}.pad canvas{position:absolute;inset:0;width:100%;height:100%;border-radius:14px}.pad .ln{position:absolute;left:8%;right:8%;bottom:30%;border-top:1px solid #CFC8B8;pointer-events:none}
.code{display:none;margin-top:18px;padding:18px;border-radius:14px;background:#F7F1E3}
.code input{max-width:220px;font-size:24px;letter-spacing:8px;text-align:center;font-weight:700}
.err{color:var(--err);font-weight:600;margin-top:12px;display:none}
.done{padding:34px 26px;text-align:center}.done .ic{width:64px;height:64px;border-radius:50%;background:var(--okl);color:var(--ok);display:flex;align-items:center;justify-content:center;margin:0 auto 14px;font-size:30px;font-weight:800}
.small{font-size:12.5px;color:var(--mut);word-break:break-all}
.bar{position:sticky;bottom:0;margin-top:14px;background:rgba(245,244,240,.94);backdrop-filter:blur(6px);padding:10px 0;display:none}
@media(max-width:700px){.doc{padding:26px 20px;font-size:15px}.g3{grid-template-columns:1fr}.intro,.sign{padding:18px}.top{padding:16px 16px 4px}.wrap{padding:6px 12px 50px}}
</style></head><body>
<div class="top"><div class="logo">ELOK<span>.</span></div><?php if ($ok): ?><a class="btn sm" href="?t=<?= h($t) ?>&amp;pdf=1">Pobierz PDF</a><?php endif; ?></div>
<div class="wrap">
<?php if (!$ok): ?>
  <div class="card done"><div class="ic" style="background:#F3EFEF;color:#8A5A5A">!</div>
    <h1 style="margin:0 0 8px;font-size:21px">Link jest nieaktywny</h1>
    <p style="margin:0;color:var(--ink2)"><?php
      if ($u && $u['status'] === 'zawarta') echo 'Ta umowa została już zaakceptowana. PDF z umową i kartą akceptacji przyszedł na Twój adres e-mail.';
      elseif ($u && $u['status'] === 'wyslana') echo 'Link wygasł. Odpisz na maila z umową, a wyślemy nowy link.';
      else echo 'Link wygasł albo umowa została zmieniona. Odpisz na maila z umową, a wyślemy aktualny link.'; ?></p>
  </div>
<?php elseif ($u['status'] === 'zawarta'): ?>
  <div class="card done"><div class="ic">&#10003;</div>
    <h1 style="margin:0 0 8px;font-size:22px">Umowa <?= h($u['numer']) ?> jest zawarta</h1>
    <p style="margin:0 0 18px;color:var(--ink2)">Zaakceptowano <?= h(um_dt_pl($u['zawarta_at'])) ?><?= $kli ? ' przez ' . h($kli['imie'] . ' ' . $kli['nazwisko']) : '' ?>. PDF z treścią i kartą akceptacji wysłaliśmy e-mailem.</p>
    <a class="btn dark" href="?t=<?= h($t) ?>&amp;pdf=1">Pobierz PDF umowy</a>
    <p class="small" style="margin-top:18px">SHA-256 treści: <?= h($u['sha']) ?></p>
  </div>
<?php else: ?>
  <div class="card intro">
    <h1><?= h($u['szablon_nazwa']) ?></h1>
    <p>Umowa nr <b><?= h($u['numer']) ?></b> między <b><?= h(isset($f['nazwa']) ? $f['nazwa'] : '') ?></b> (ELOK) a <?= um_dot('<b>' . h(isset($k['nazwa']) ? $k['nazwa'] : '') . '</b>') ?> Przeczytaj treść – formularz akceptacji jest na końcu strony.</p>
    <div class="meta">
      <?php if ($elok): ?><span>Wykonawca zaakceptował: <b><?= h($elok['imie'] . ' ' . $elok['nazwisko']) ?></b>, <?= h(um_dt_pl($elok['created_at'])) ?></span><?php endif; ?>
      <span>Link ważny do: <b><?= h(um_dt_pl($u['token_exp'])) ?></b></span>
    </div>
  </div>
  <div class="card doc" id="doc"><?= $u['html'] ?></div>
  <div id="end"></div>
  <div class="card sign" id="sign">
    <h2>Akceptacja umowy</h2>
    <p class="lead">Wpisz dane osoby, która akceptuje umowę w imieniu <?= um_dot('<b>' . h(isset($k['nazwa']) ? $k['nazwa'] : '') . '</b>') ?> Na adres <b><?= h(um_mask_email(isset($k['email']) ? $k['email'] : '')) ?></b> wyślemy kod potwierdzający.</p>
    <div class="g3">
      <div><label class="f" for="p-im">Imię</label><input type="text" id="p-im" autocomplete="given-name"></div>
      <div><label class="f" for="p-na">Nazwisko</label><input type="text" id="p-na" autocomplete="family-name"></div>
      <div><label class="f" for="p-fu">Funkcja</label><input type="text" id="p-fu" placeholder="np. Właściciel, Prezes Zarządu"></div>
    </div>
    <label class="chk"><input type="checkbox" id="p-upr"><span>Jestem uprawniony/uprawniona do reprezentowania <?= um_dot('<b>' . h(isset($k['nazwa']) ? $k['nazwa'] : '') . '</b>') ?></span></label>
    <label class="chk"><input type="checkbox" id="p-akc"><span>Zapoznałem/zapoznałam się z treścią umowy nr <?= h($u['numer']) ?> i ją akceptuję.</span></label>
    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center" id="r-btns">
      <button class="btn dark" id="b-pod" disabled onclick="podStart()">Podpisz</button>
      <span class="hint" id="h-scroll" style="margin:0">Przycisk włączy się po przewinięciu umowy do końca i wypełnieniu pól.</span>
    </div>
    <div class="sig" id="sig"><div class="sig-l">Twój podpis</div><img id="sig-img" alt="Podpis"><a href="#" onclick="podStart();return false" class="small">Podpisz ponownie</a></div>
    <div id="k-btns" style="display:none;margin-top:16px"><button class="btn dark" id="b-kod" onclick="podKod()">Wyślij kod na e-mail</button></div>
    <div class="code" id="code">
      <div style="font-weight:700">Kod wysłaliśmy na <span id="c-mail"></span></div>
      <div class="hint" style="margin-top:4px">Ważny 15 minut. Nie widzisz? Sprawdź folder SPAM albo <a href="#" onclick="podKod();return false">wyślij ponownie</a>.</div>
      <label class="f" for="p-kod">Kod (6 cyfr)</label>
      <div style="display:flex;gap:10px;flex-wrap:wrap"><input type="text" id="p-kod" inputmode="numeric" autocomplete="one-time-code" maxlength="6"><button class="btn dark" id="b-ok" onclick="podOk()">Akceptuję umowę</button></div>
    </div>
    <div class="err" id="err"></div>
    <p class="small" style="margin-top:18px">Akceptacja w tym formularzu to zawarcie umowy w formie dokumentowej (art. 77² Kodeksu cywilnego). Zapisujemy datę i godzinę, adres IP i urządzenie, obraz podpisu, dane osoby i skrót SHA-256 treści: <?= h($u['sha']) ?>.</p>
  </div>
  <div class="ovl" id="ovl"><div class="ovl-in">
    <div id="o-qr"><h2>Zeskanuj telefonem i podpisz palcem</h2><p class="lead">Otwórz aparat w telefonie, nakieruj na kod i podpisz się na ekranie. Ta strona sama zobaczy podpis.</p>
      <div id="qr" class="qr"></div><div class="hint" id="o-wait">Czekam na podpis z telefonu…</div>
      <div class="o-alt"><a href="#" onclick="podMysz();return false">Nie mam telefonu pod ręką – podpiszę myszką tutaj</a></div></div>
    <div id="o-pad" style="display:none"><h2>Podpisz się</h2><p class="lead" id="o-padl">Złóż podpis w ramce poniżej.</p>
      <div class="pad"><canvas id="cv"></canvas><div class="ln"></div></div>
      <div style="display:flex;gap:10px;margin-top:12px"><button class="btn" onclick="PP.clear()">Wyczyść</button><button class="btn dark" id="b-pz" disabled onclick="podPadSave()">Zatwierdź podpis</button></div></div>
    <div style="margin-top:14px"><a href="#" onclick="podClose();return false" class="small">Zamknij</a></div>
  </div></div>
  <div id="done" class="card done" style="display:none;margin-top:14px"><div class="ic">&#10003;</div><h1 style="margin:0 0 8px;font-size:22px">Gotowe – umowa jest zawarta</h1><p style="margin:0 0 18px;color:var(--ink2)">Dziękujemy. PDF z umową i kartą akceptacji wysłaliśmy na Twój e-mail.</p><a class="btn dark" href="?t=<?= h($t) ?>&amp;pdf=1">Pobierz PDF umowy</a></div>
<script src="qrcode.js?v=1"></script>
<script src="podpis_pad.js?v=<?= (int)@filemtime(__DIR__ . '/podpis_pad.js') ?>"></script>
<script>
(function(){
var N=<?= json_encode($_SESSION['pod_n']) ?>, T=<?= json_encode($t) ?>, read=false, signed=false, poll=null, PP=null;
function $(i){return document.getElementById(i);}
function valid(){return read&&$('p-im').value.trim()&&$('p-na').value.trim()&&$('p-fu').value.trim()&&$('p-upr').checked&&$('p-akc').checked;}
function st(){var ok=valid();$('b-pod').disabled=!ok;$('h-scroll').style.display=ok?'none':'';$('b-kod').disabled=!(ok&&signed);}
['p-im','p-na','p-fu','p-upr','p-akc'].forEach(function(i){$(i).addEventListener('input',st);$(i).addEventListener('change',st);});
function chk(){var r=$('end').getBoundingClientRect();if(r.top<=window.innerHeight+40){read=true;st();}}
window.addEventListener('scroll',chk,{passive:true});window.addEventListener('resize',chk);chk();
function err(m){var e=$('err');e.textContent=m||'';e.style.display=m?'block':'none';}
function post(a,extra,cb,btn,quiet){if(!quiet)err('');if(btn){btn.disabled=true;btn.dataset.t=btn.textContent;btn.textContent='Chwila…';}
 var d={imie:$('p-im').value,nazwisko:$('p-na').value,funkcja:$('p-fu').value,upr:$('p-upr').checked?1:0,akc:$('p-akc').checked?1:0};for(var k in extra)d[k]=extra[k];
 var x=new XMLHttpRequest();x.open('POST','?t='+encodeURIComponent(T)+'&a='+a);x.setRequestHeader('Content-Type','application/json');x.setRequestHeader('X-Pod',N);
 x.onload=function(){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}var r={};try{r=JSON.parse(x.responseText);}catch(e){if(!quiet)err('Błąd serwera. Spróbuj ponownie.');return;}if(!r.ok){if(!quiet)err(r.error||'Błąd');return;}cb(r);};
 x.onerror=function(){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}if(!quiet)err('Brak połączenia z internetem.');};x.send(JSON.stringify(d));}
var touch=window.matchMedia&&window.matchMedia('(pointer:coarse)').matches, R=null;
function showPad(label){$('o-qr').style.display='none';$('o-pad').style.display='block';$('o-padl').textContent=label;if(!PP){PP=SigPad($('cv'));PP.onchange=function(){$('b-pz').disabled=!PP.ok();};}PP.fit();PP.clear();}
window.podStart=function(){post('rys_start',{},function(r){R=r.r;$('ovl').className='ovl on';
  if(touch){showPad('Podpisz się palcem w ramce poniżej.');return;}
  $('o-qr').style.display='block';$('o-pad').style.display='none';$('o-wait').textContent='Czekam na podpis z telefonu…';
  var q=qrcode(0,'M');q.addData(r.url);q.make();$('qr').innerHTML=q.createSvgTag(5,0);
  clearInterval(poll);poll=setInterval(function(){post('rys_status',{},function(s){if(s.png)gotSig(s.png);},null,true);},2000);
 },$('b-pod'));};
window.podMysz=function(){clearInterval(poll);showPad('Podpisz się myszką w ramce poniżej (przytrzymaj przycisk myszy).');};
window.podPadSave=function(){var b=$('b-pz');b.disabled=true;var png=PP.png();
 var x=new XMLHttpRequest();x.open('POST','?t='+encodeURIComponent(T)+'&a=rys_zapisz');x.setRequestHeader('Content-Type','application/json');x.setRequestHeader('X-Pod',N);
 x.onload=function(){var r={};try{r=JSON.parse(x.responseText);}catch(e){}if(!r.ok){alertErr(r.error);b.disabled=false;return;}gotSig(png);};x.send(JSON.stringify({r:R,png:png}));};
function alertErr(m){$('o-padl').textContent=m||'Błąd – spróbuj ponownie.';}
function gotSig(png){clearInterval(poll);signed=true;$('sig-img').src=png;$('sig').className='sig on';$('ovl').className='ovl';$('r-btns').style.display='none';$('k-btns').style.display='block';st();$('sig').scrollIntoView({behavior:'smooth',block:'center'});}
window.podClose=function(){clearInterval(poll);$('ovl').className='ovl';};
window.podKod=function(){post('kod',{},function(r){$('c-mail').textContent=r.email;$('code').style.display='block';$('p-kod').focus();},$('b-kod'));};
window.podOk=function(){var c=$('p-kod').value.replace(/\D/g,'');if(c.length!==6){err('Wpisz 6-cyfrowy kod z maila.');return;}
 post('potwierdz',{kod:c},function(){$('sign').style.display='none';$('done').style.display='block';$('done').scrollIntoView({behavior:'smooth',block:'center'});},$('b-ok'));};
$('p-kod').addEventListener('keydown',function(e){if(e.key==='Enter')window.podOk();});
})();
</script>
<?php endif; ?>
</div></body></html>
