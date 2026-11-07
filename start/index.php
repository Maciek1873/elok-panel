<?php
// ELOK – prosty landing (link w bio) dla TikToka / Instagrama / Facebooka.
// Adres: /start/?z=tiktok  (z = źródło). Zgłoszenia → panel › Zgłoszenia. Wejścia i kliknięcia → statystyki w Zgłoszeniach.
define('ELOK', 1);
require __DIR__ . '/../lib.php';

$ZR = array('tiktok' => 'TikTok', 'instagram' => 'Instagram', 'facebook' => 'Facebook', 'inne' => 'Inne');
$z = isset($_REQUEST['z']) ? strtolower(preg_replace('/[^a-z]/i', '', (string)$_REQUEST['z'])) : 'tiktok';
if (!isset($ZR[$z])) $z = 'inne';
$ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '';
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? (string)$_SERVER['HTTP_USER_AGENT'] : '';
$isBot = $ua === '' || preg_match('/bot|crawl|spider|python|curl|wget|headless|phantom|preview|facebookexternalhit|scan|monitor/i', $ua);

function land_migrate() {
    db()->exec("CREATE TABLE IF NOT EXISTS landing_log (id INT AUTO_INCREMENT PRIMARY KEY, zrodlo VARCHAR(20) NOT NULL DEFAULT '', akcja VARCHAR(20) NOT NULL, sid VARCHAR(32) NOT NULL DEFAULT '', created_at DATETIME NOT NULL, KEY zc (zrodlo, created_at), KEY ac (akcja, created_at)) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    add_col('zgloszenia', 'zrodlo', "VARCHAR(30) NOT NULL DEFAULT ''");
}

// ---------- ŚLEDZENIE (wywoływane przez skrypt na stronie, tylko prawdziwe przeglądarki) ----------
if (isset($_GET['ev'])) {
    $ev = preg_replace('/[^a-z]/', '', (string)$_GET['ev']);
    $sid = isset($_POST['sid']) ? preg_replace('/[^a-z0-9]/', '', (string)$_POST['sid']) : '';
    if (!$isBot && in_array($ev, array('wejscie', 'formularz', 'telefon', 'strona', 'mail'), true) && strlen($sid) >= 8) {
        land_migrate();
        $sid = substr($sid, 0, 32);
        // ten sam gość + ta sama akcja liczona raz na 30 min
        $dup = (int)val('SELECT COUNT(*) FROM landing_log WHERE sid=? AND akcja=? AND created_at>=?', array($sid, $ev, date('Y-m-d H:i:s', time() - 1800)));
        if (!$dup) {
            q('INSERT INTO landing_log(zrodlo,akcja,sid,created_at) VALUES(?,?,?,?)', array($z, $ev, $sid, now()));
            // powiadomienie o wejściu (max 30 dziennie, żeby nie zasypać telefonu)
            if ($ev === 'wejscie' && setting('land_push', '1') === '1') {
                $today = (int)val("SELECT COUNT(*) FROM landing_log WHERE akcja='wejscie' AND created_at>=CURDATE()");
                if ($today <= 30) push('Wejście z ' . $ZR[$z], 'Ktoś otworzył Twój landing (dziś: ' . $today . ').', BASE_URL . '/#zgloszenia');
            }
        }
    }
    http_response_code(204); exit;
}

header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: DENY');
$tel = setting('podpis_tel');
$telLink = preg_replace('/[^0-9+]/', '', $tel);
if ($telLink !== '' && $telLink[0] !== '+') $telLink = '+48' . $telLink;
$mail = setting('podpis_email') ?: 'kontakt@elok-agency.pl';
$www = 'https://elok-agency.pl';

$sent = false; $err = ''; $pre = array('osoba' => '', 'kontakt' => '', 'firma' => '');
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $P = function ($k, $max = 190) { return isset($_POST[$k]) ? mb_substr(trim((string)$_POST[$k]), 0, $max) : ''; };
    foreach ($pre as $k => $v) $pre[$k] = $P($k);
    $bot = $P('www2') !== '' || (time() - (int)$P('ts')) < 3;
    $k = $pre['kontakt'];
    $isMail = (bool)filter_var($k, FILTER_VALIDATE_EMAIL);
    $dg = preg_replace('/[^0-9]/', '', $k);
    $isTel = strlen($dg) >= 9 && strlen($dg) <= 15;
    if ($bot) {
        $sent = true;
    } elseif ($pre['osoba'] === '' || (!$isMail && !$isTel)) {
        $err = 'Wpisz imię i telefon albo e-mail.';
    } else {
        land_migrate();
        $n = (int)val('SELECT COUNT(*) FROM zgloszenia WHERE ip=? AND created_at>=?', array($ip, date('Y-m-d H:i:s', time() - 3600)));
        if ($n < 5) {
            $firma = $pre['firma'] !== '' ? $pre['firma'] : $pre['osoba'];
            q('INSERT INTO zgloszenia(firma_id,firma,osoba,telefon,email,czym,co,status,notatka,ip,created_at,zrodlo) VALUES(NULL,?,?,?,?,?,?,?,?,?,?,?)',
                array($firma, $pre['osoba'], $isMail ? '' : $k, $isMail ? $k : '', $pre['firma'], '', 'nowe', 'Źródło: ' . $ZR[$z] . ' (landing /start)', $ip, now(), $z));
            logi(null, 'zgloszenie', $ZR[$z] . ': ' . $pre['osoba'] . ' ' . $k);
            $link = BASE_URL . '/#zgloszenia';
            push('Zgłoszenie z ' . $ZR[$z] . ': ' . $pre['osoba'], trim($k . "\n" . $pre['firma']), $link);
            notify_owner('Nowe zgłoszenie z ' . $ZR[$z] . ': ' . $pre['osoba'], array('Źródło' => $ZR[$z], 'Imię' => $pre['osoba'], 'Kontakt' => $k, 'Czym się zajmuje' => $pre['firma']), $link);
        }
        $sent = true;
    }
}
$ts = time();
function ico($n) {
    $p = array(
        'cal' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'phone' => '<path d="M5 4h4l2 5-2.5 1.5a11 11 0 0 0 5 5L15 13l5 2v4a2 2 0 0 1-2 2A16 16 0 0 1 3 6a2 2 0 0 1 2-2"/>',
        'web' => '<circle cx="12" cy="12" r="9"/><path d="M3 12h18M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'mail' => '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="M3 7l9 6 9-6"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'arrow' => '<path d="M9 6l6 6-6 6"/>',
    );
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $p[$n] . '</svg>';
}
?><!DOCTYPE html>
<html lang="pl"><head>
<meta charset="UTF-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
<title>ELOK – strony, panele i automatyzacje dla firm</title>
<meta name="description" content="Budujemy strony i systemy, które pracują za Ciebie. Bezpłatna konsultacja.">
<meta name="theme-color" content="#0B0B0B"><meta name="robots" content="noindex">
<link rel="preconnect" href="https://fonts.googleapis.com"><link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800;900&display=swap" rel="stylesheet">
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{overflow-x:hidden;min-height:100vh;background:#0B0B0B;background-image:radial-gradient(circle at 50% -10%,rgba(197,160,89,.22),transparent 55%);color:#F4F1EA;font-family:Inter,Arial,sans-serif;-webkit-font-smoothing:antialiased;line-height:1.5}
.w{max-width:480px;margin:0 auto;padding:40px 18px 36px;text-align:center}
.av{width:92px;height:92px;margin:0 auto 18px;border-radius:50%;background:#111;border:2px solid #2A2A2A;display:flex;align-items:center;justify-content:center;font-weight:900;font-size:24px;letter-spacing:-1px}
.av span,.g{color:#C5A059}
h1{font-size:30px;line-height:1.1;font-weight:900;letter-spacing:-1px;margin-bottom:10px}
.lead{font-size:15.5px;color:#B5B0A4;margin-bottom:26px}
svg{width:22px;height:22px;flex:none}
.b{display:flex;align-items:center;gap:14px;width:100%;padding:18px 20px;margin-bottom:12px;border-radius:16px;border:1px solid #2A2A2A;background:#151515;color:#F4F1EA;font:700 16.5px Inter,Arial;text-decoration:none;text-align:left;cursor:pointer}
.b .t{flex:1}.b .t small{display:block;font-weight:500;font-size:13px;color:#8F8A7E;margin-top:1px}
.b .ar{color:#6E6A60;width:20px;height:20px}
.b.main{background:#C5A059;border-color:#C5A059;color:#0B0B0B;box-shadow:0 14px 40px rgba(197,160,89,.28)}
.b.main .t small{color:#3E3218}.b.main .ar{color:#0B0B0B}
.b:active{transform:scale(.985)}
.ic{width:42px;height:42px;border-radius:12px;background:#221D13;color:#C5A059;display:flex;align-items:center;justify-content:center;flex:none}
.b.main .ic{background:rgba(0,0,0,.12);color:#0B0B0B}
#f{display:none;text-align:left;background:#F5F4F0;color:#141414;border-radius:20px;padding:22px 18px;margin:-4px 0 14px}
#f.on{display:block}
label{display:block;font-size:13px;font-weight:600;color:#5E5A51;margin:12px 0 6px}
input{width:100%;padding:15px 16px;border:1px solid #DEDAD0;border-radius:13px;font:16px Inter,Arial;background:#fff;color:#141414}
input:focus{outline:0;border-color:#C5A059;box-shadow:0 0 0 3px rgba(197,160,89,.2)}
button.s{width:100%;margin-top:16px;padding:17px;border:0;border-radius:14px;background:#141414;color:#fff;font:800 16px Inter,Arial;cursor:pointer}
.err{background:#FDECEA;color:#B3261E;border-radius:12px;padding:11px 14px;font-size:14px;margin-bottom:6px}
.priv{font-size:11.5px;color:#8A867C;margin-top:10px}
.hp{position:absolute;left:-9999px}
.ok{background:#F5F4F0;color:#141414;border-radius:20px;padding:26px 20px;margin-bottom:14px}
.ok .tk{width:60px;height:60px;margin:0 auto 12px;border-radius:50%;background:#E6F5EC;color:#1F9254;display:flex;align-items:center;justify-content:center}
.ok .tk svg{width:32px;height:32px}.ok b{display:block;font-size:20px;margin-bottom:4px}.ok p{font-size:14.5px;color:#6E6A60}
.trust{display:flex;justify-content:center;gap:16px;flex-wrap:wrap;margin-top:18px;font-size:13px;color:#8F8A7E}
.trust span{display:inline-flex;align-items:center;gap:5px}.trust svg{width:15px;height:15px;color:#C5A059}
</style></head>
<body><div class="w">
 <div class="av">ELOK<span>.</span></div>
 <h1>Strony, panele i <span class="g">automatyzacje</span> dla firm</h1>
 <p class="lead">Budujemy systemy, które pracują za Ciebie – szyte pod Twój biznes.</p>

<?php if ($sent): ?>
 <div class="ok"><div class="tk"><?php echo ico('check'); ?></div><b>Dziękujemy!</b><p>Odezwiemy się najszybciej, jak to możliwe – zwykle tego samego dnia roboczego.</p></div>
<?php else: ?>
 <a class="b main" href="#f" data-ev="formularz" onclick="return window.showF()"><span class="ic"><?php echo ico('cal'); ?></span><span class="t">Bezpłatna konsultacja<small>15 minut · bez zobowiązań</small></span><?php echo str_replace('<svg', '<svg class="ar"', ico('arrow')); ?></a>
 <form id="f" class="<?php echo $err !== '' ? 'on' : ''; ?>" method="post" action="?z=<?php echo h($z); ?>" novalidate>
  <?php if ($err !== ''): ?><div class="err"><?php echo h($err); ?></div><?php endif; ?>
  <input type="hidden" name="ts" value="<?php echo $ts; ?>"><input type="hidden" name="z" value="<?php echo h($z); ?>">
  <div class="hp"><input name="www2" tabindex="-1" autocomplete="off"></div>
  <label for="osoba">Imię *</label><input id="osoba" name="osoba" autocomplete="given-name" value="<?php echo h($pre['osoba']); ?>">
  <label for="kontakt">Telefon albo e-mail *</label><input id="kontakt" name="kontakt" autocomplete="tel" value="<?php echo h($pre['kontakt']); ?>">
  <label for="firma">Czym zajmuje się firma?</label><input id="firma" name="firma" placeholder="np. warsztat, sklep, usługi" value="<?php echo h($pre['firma']); ?>">
  <button class="s" type="submit">Wyślij – odezwiemy się</button>
  <p class="priv">Wysyłając formularz, zgadzasz się na kontakt w sprawie konsultacji. Dane wykorzystamy tylko w tym celu.</p>
 </form>
<?php endif; ?>
<?php if ($tel !== ''): ?>
 <a class="b" href="tel:<?php echo h($telLink); ?>" data-ev="telefon"><span class="ic"><?php echo ico('phone'); ?></span><span class="t">Zadzwoń<small><?php echo h($tel); ?></small></span><?php echo str_replace('<svg', '<svg class="ar"', ico('arrow')); ?></a>
<?php endif; ?>
 <a class="b" href="<?php echo h($www); ?>" data-ev="strona"><span class="ic"><?php echo ico('web'); ?></span><span class="t">Zobacz naszą stronę<small>elok-agency.pl</small></span><?php echo str_replace('<svg', '<svg class="ar"', ico('arrow')); ?></a>
 <a class="b" href="mailto:<?php echo h($mail); ?>" data-ev="mail"><span class="ic"><?php echo ico('mail'); ?></span><span class="t">Napisz maila<small><?php echo h($mail); ?></small></span><?php echo str_replace('<svg', '<svg class="ar"', ico('arrow')); ?></a>

 <div class="trust"><span><?php echo ico('check'); ?>Stała cena przed startem</span><span><?php echo ico('check'); ?>Na komputerze i w telefonie</span></div>
</div>
<script>
(function(){
  var Z='<?php echo h($z); ?>', sid='';
  try{sid=localStorage.getItem('elok_sid')||'';}catch(e){}
  if(!sid){sid=(Math.random().toString(36).slice(2)+Date.now().toString(36)).slice(0,24);try{localStorage.setItem('elok_sid',sid);}catch(e){}}
  function ev(a){
    var url='?z='+Z+'&ev='+a, body='sid='+encodeURIComponent(sid);
    try{ if(navigator.sendBeacon){var b=new Blob([body],{type:'application/x-www-form-urlencoded'}); if(navigator.sendBeacon(url,b))return;} }catch(e){}
    try{var x=new XMLHttpRequest();x.open('POST',url,true);x.setRequestHeader('Content-Type','application/x-www-form-urlencoded');x.send(body);}catch(e){}
  }
  setTimeout(function(){ if(!document.hidden) ev('wejscie'); },1500);
  var links=document.querySelectorAll('[data-ev]');
  for(var i=0;i<links.length;i++){ links[i].addEventListener('click',function(){ ev(this.getAttribute('data-ev')); }); }
  window.showF=function(){ var f=document.getElementById('f'); if(!f)return true; f.className='on'; var o=document.getElementById('osoba'); if(o)o.focus(); return false; };
})();
</script>
</body></html>
