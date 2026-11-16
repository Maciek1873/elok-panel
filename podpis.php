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
        $u = um_by_token($t);
        if (!$u || !empty($u['_expired'])) pod_json(array('ok' => false, 'error' => 'Link jest nieaktywny albo wygasł. Poproś o nowy link.'));
        $osoba = array('imie' => $s('imie', 80), 'nazwisko' => $s('nazwisko', 80), 'funkcja' => $s('funkcja', 120));
        if ($osoba['imie'] === '' || $osoba['nazwisko'] === '' || $osoba['funkcja'] === '') pod_json(array('ok' => false, 'error' => 'Podaj imię, nazwisko i funkcję.'));
        if (empty($in['upr']) || empty($in['akc'])) pod_json(array('ok' => false, 'error' => 'Zaznacz oba oświadczenia.'));
        $a = isset($_GET['a']) ? (string)$_GET['a'] : '';
        if ($a === 'kod') {
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
    <div style="margin-top:16px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <button class="btn dark" id="b-kod" disabled onclick="podKod()">Wyślij kod na e-mail</button>
      <span class="hint" id="h-scroll" style="margin:0">Przycisk włączy się po przewinięciu umowy do końca i wypełnieniu pól.</span>
    </div>
    <div class="code" id="code">
      <div style="font-weight:700">Kod wysłaliśmy na <span id="c-mail"></span></div>
      <div class="hint" style="margin-top:4px">Ważny 15 minut. Nie widzisz? Sprawdź folder SPAM albo <a href="#" onclick="podKod();return false">wyślij ponownie</a>.</div>
      <label class="f" for="p-kod">Kod (6 cyfr)</label>
      <div style="display:flex;gap:10px;flex-wrap:wrap"><input type="text" id="p-kod" inputmode="numeric" autocomplete="one-time-code" maxlength="6"><button class="btn dark" id="b-ok" onclick="podOk()">Akceptuję umowę</button></div>
    </div>
    <div class="err" id="err"></div>
    <p class="small" style="margin-top:18px">Akceptacja w tym formularzu to zawarcie umowy w formie dokumentowej (art. 77² Kodeksu cywilnego). Zapisujemy datę i godzinę, adres IP, dane osoby i skrót SHA-256 treści: <?= h($u['sha']) ?>.</p>
  </div>
  <div id="done" class="card done" style="display:none;margin-top:14px"><div class="ic">&#10003;</div><h1 style="margin:0 0 8px;font-size:22px">Gotowe – umowa jest zawarta</h1><p style="margin:0 0 18px;color:var(--ink2)">Dziękujemy. PDF z umową i kartą akceptacji wysłaliśmy na Twój e-mail.</p><a class="btn dark" href="?t=<?= h($t) ?>&amp;pdf=1">Pobierz PDF umowy</a></div>
<script>
(function(){
var N=<?= json_encode($_SESSION['pod_n']) ?>, T=<?= json_encode($t) ?>, read=false;
function $(i){return document.getElementById(i);}
function st(){var ok=read&&$('p-im').value.trim()&&$('p-na').value.trim()&&$('p-fu').value.trim()&&$('p-upr').checked&&$('p-akc').checked;$('b-kod').disabled=!ok;$('h-scroll').style.display=ok?'none':'';}
['p-im','p-na','p-fu','p-upr','p-akc'].forEach(function(i){$(i).addEventListener('input',st);$(i).addEventListener('change',st);});
function chk(){var r=$('end').getBoundingClientRect();if(r.top<=window.innerHeight+40){read=true;st();}}
window.addEventListener('scroll',chk,{passive:true});window.addEventListener('resize',chk);chk();
function err(m){var e=$('err');e.textContent=m||'';e.style.display=m?'block':'none';}
function post(a,extra,cb,btn){err('');if(btn){btn.disabled=true;btn.dataset.t=btn.textContent;btn.textContent='Chwila…';}
 var d={imie:$('p-im').value,nazwisko:$('p-na').value,funkcja:$('p-fu').value,upr:$('p-upr').checked?1:0,akc:$('p-akc').checked?1:0};for(var k in extra)d[k]=extra[k];
 var x=new XMLHttpRequest();x.open('POST','?t='+encodeURIComponent(T)+'&a='+a);x.setRequestHeader('Content-Type','application/json');x.setRequestHeader('X-Pod',N);
 x.onload=function(){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}var r={};try{r=JSON.parse(x.responseText);}catch(e){err('Błąd serwera. Spróbuj ponownie.');return;}if(!r.ok){err(r.error||'Błąd');return;}cb(r);};
 x.onerror=function(){if(btn){btn.disabled=false;btn.textContent=btn.dataset.t;}err('Brak połączenia z internetem.');};x.send(JSON.stringify(d));}
window.podKod=function(){post('kod',{},function(r){$('c-mail').textContent=r.email;$('code').style.display='block';$('p-kod').focus();},$('b-kod'));};
window.podOk=function(){var c=$('p-kod').value.replace(/\D/g,'');if(c.length!==6){err('Wpisz 6-cyfrowy kod z maila.');return;}
 post('potwierdz',{kod:c},function(){$('sign').style.display='none';$('done').style.display='block';$('done').scrollIntoView({behavior:'smooth',block:'center'});},$('b-ok'));};
$('p-kod').addEventListener('keydown',function(e){if(e.key==='Enter')window.podOk();});
})();
</script>
<?php endif; ?>
</div></body></html>
