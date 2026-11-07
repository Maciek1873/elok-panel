<?php
define('ELOK', 1);
require __DIR__ . '/lib.php';
start_session();
header('Content-Type: text/html; charset=utf-8');
header('X-Frame-Options: SAMEORIGIN');
$installed = false;
try { $installed = setting('admin_hash') !== ''; } catch (Exception $e) { $installed = false; }
if (!$installed) {
    echo '<!DOCTYPE html><meta charset="utf-8"><p style="font-family:Arial;padding:40px">Panel nie jest jeszcze zainstalowany. Uzupełnij <b>config.php</b> i otwórz <a href="install.php">install.php</a>.</p>';
    exit;
}
$logged = logged_in();
$v = @filemtime(__DIR__ . '/app.js');
?><!DOCTYPE html>
<html lang="pl">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="robots" content="noindex,nofollow">
<meta name="theme-color" content="#F5F4F0">
<meta name="apple-mobile-web-app-capable" content="yes">
<meta name="apple-mobile-web-app-title" content="ELOK">
<link rel="manifest" href="manifest.json">
<link rel="apple-touch-icon" href="ikona_elok.png?v=2">
<link rel="icon" href="ikona_elok.png?v=2">
<title>ELOK · Pozyskiwanie klientów</title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Outfit:wght@500;600;800&display=swap" rel="stylesheet">
<style>
:root{
  --bg:#F5F4F0;--card:#FFFFFF;--ink:#151515;--ink2:#4A4740;--muted:#8A867C;--line:#E7E4DC;--line2:#EFEDE7;
  --gold:#C5A059;--gold-d:#8B6B30;--gold-l:#F7F1E3;--ok:#2E7D4F;--ok-l:#EAF5EE;--err:#B3261E;--err-l:#FBECEA;
  --r:16px;--sh:0 1px 2px rgba(20,18,10,.04),0 8px 24px rgba(20,18,10,.05);
}
*{box-sizing:border-box}
html,body{margin:0;background:var(--bg);color:var(--ink);font-family:Inter,-apple-system,Arial,sans-serif;font-size:14px;line-height:1.5;-webkit-font-smoothing:antialiased}
a{color:inherit}
button,input,select,textarea{font:inherit;color:inherit}
h1,h2,h3{font-family:Outfit,Inter,Arial;font-weight:600;letter-spacing:-.2px;margin:0}
.logo{font-family:Outfit,Arial;font-weight:800;font-size:28px;letter-spacing:-1px}.logo span{color:var(--gold)}

/* login */
.login{min-height:100vh;display:flex;align-items:center;justify-content:center;padding:20px}
.login .box{background:var(--card);border:1px solid var(--line);border-radius:22px;box-shadow:var(--sh);padding:38px 30px;width:100%;max-width:380px;text-align:center}
.login .sub{color:var(--muted);font-size:13px;margin:4px 0 26px}
.login input{margin-bottom:10px}
.login .err{color:var(--err);font-weight:600;font-size:13px;margin-top:12px;display:none}

/* layout */
.app{display:flex;min-height:100vh}
.side{width:236px;flex-shrink:0;background:var(--card);border-right:1px solid var(--line);padding:24px 14px;display:flex;flex-direction:column;position:sticky;top:0;height:100vh}
.side .logo{padding:0 12px 4px}
.side .tag{padding:0 12px 24px;font-size:11.5px;color:var(--muted)}
.nav a{display:flex;align-items:center;gap:11px;padding:10px 12px;border-radius:11px;text-decoration:none;color:var(--ink2);font-weight:500;margin-bottom:2px;cursor:pointer}
.nav a:hover{background:var(--bg)}
.nav a.on{background:var(--ink);color:#fff}
.nav svg{width:18px;height:18px;flex-shrink:0}
.nav .bdg{margin-left:auto;background:var(--gold);color:#fff;font-size:11px;font-weight:700;border-radius:20px;padding:1px 7px}
.side .bottom{margin-top:auto}
main{flex:1;min-width:0;padding:32px 36px 80px;max-width:1280px}
.head{display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:22px;flex-wrap:wrap}
.head h1{font-size:28px}
.head p{margin:2px 0 0;color:var(--muted)}

/* elements */
.card{background:var(--card);border:1px solid var(--line);border-radius:var(--r);box-shadow:var(--sh)}
.pad{padding:20px 22px}
.grid{display:grid;gap:14px}
.g4{grid-template-columns:repeat(4,1fr)}
.g2{grid-template-columns:1fr 1fr}
.stat .l{font-size:11.5px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--muted)}
.stat .n{font-family:Outfit,Arial;font-size:34px;font-weight:600;line-height:1.1;margin-top:8px}
.stat .s{color:var(--muted);font-size:12.5px;margin-top:2px}
.stat .n small{font-size:16px;color:var(--muted);font-weight:500}
.sec{margin-top:22px}
.sec>h2{font-size:16px;margin:0 0 10px}
.btn{display:inline-flex;align-items:center;justify-content:center;gap:8px;height:40px;padding:0 16px;border-radius:11px;border:1px solid var(--line);background:var(--card);font-weight:600;font-size:13.5px;cursor:pointer;white-space:nowrap;text-decoration:none}
.btn:hover{border-color:#CFCABF}
.btn.dark{background:var(--ink);border-color:var(--ink);color:#fff}
.btn.dark:hover{background:#000}
.btn.gold{background:var(--gold);border-color:var(--gold);color:#fff}
.btn.red{color:var(--err)}
.btn.redfill{background:var(--err);border-color:var(--err);color:#fff}
.btn.sm{height:32px;padding:0 11px;font-size:12.5px;border-radius:9px}
.btn[disabled]{opacity:.5;pointer-events:none}
input,select,textarea{width:100%;height:42px;padding:0 13px;border:1px solid #DEDAD0;border-radius:11px;background:#FCFBF9;outline:0}
@media(max-width:820px){input,select,textarea,.bb input,.bb textarea,.ark input,.ark select{font-size:16px!important}.grid{grid-template-columns:minmax(0,1fr)!important}.grid>*{min-width:0}#main .card{max-width:100%;overflow-x:auto;-webkit-overflow-scrolling:touch}html,body{overflow-x:hidden}}
textarea{height:auto;padding:11px 13px;resize:vertical;line-height:1.55}
input:focus,select:focus,textarea:focus{border-color:var(--gold);background:#fff;box-shadow:0 0 0 3px rgba(197,160,89,.18)}
label.f{display:block;font-size:12px;font-weight:600;color:#5E5A51;margin:12px 0 5px}
.hint{font-size:12px;color:var(--muted);margin-top:4px}
.row{display:flex;gap:12px;align-items:center;flex-wrap:wrap}
.row>.grow{flex:1;min-width:160px}
.tbl{width:100%;border-collapse:collapse}
.tbl th{font-size:11px;font-weight:600;letter-spacing:.6px;text-transform:uppercase;color:var(--muted);text-align:left;padding:12px 14px;border-bottom:1px solid var(--line)}
.tbl td{padding:12px 14px;border-bottom:1px solid var(--line2);vertical-align:middle}
.tbl tr:last-child td{border-bottom:0}
.tbl tbody tr.click{cursor:pointer}
.tbl tbody tr.click:hover{background:#FBFAF7}
.tbl .num{text-align:right;font-variant-numeric:tabular-nums}
.b1{font-weight:600}
.mut{color:var(--muted);font-size:12.5px}
.pill{display:inline-block;padding:3px 10px;border-radius:20px;font-size:11.5px;font-weight:600;white-space:nowrap}
.st-nowy{background:#F0EEE9;color:#6B675E}.st-wyslano{background:#EAF0FB;color:#2F5BA8}.st-otworzyl{background:var(--gold-l);color:var(--gold-d)}
.st-przypomnienie{background:#FDF0E2;color:#A65A12}.st-zainteresowany{background:var(--ok-l);color:var(--ok)}.st-nie{background:#F3EFEF;color:#8A5A5A}
.st-wypisany{background:#ECEBE8;color:#55524B}.st-zwrot{background:var(--err-l);color:var(--err)}.st-blad{background:var(--err-l);color:var(--err)}
.z-nowe{background:var(--gold-l);color:var(--gold-d)}.z-zadzwonione{background:#EAF0FB;color:#2F5BA8}.z-umowione{background:#FDF0E2;color:#A65A12}.z-klient{background:var(--ok-l);color:var(--ok)}.z-odrzucone{background:#ECEBE8;color:#55524B}
.chips{display:flex;gap:8px;flex-wrap:wrap}
.chip{border:1px solid var(--line);background:var(--card);border-radius:20px;padding:6px 12px;font-size:12.5px;font-weight:500;cursor:pointer;display:inline-flex;gap:7px;align-items:center}
.chip b{font-weight:700}
.chip:hover{border-color:#CFCABF}
.chip.on{background:var(--ink);color:#fff;border-color:var(--ink)}
.empty{padding:40px 20px;text-align:center;color:var(--muted)}
.auto{display:flex;align-items:center;gap:16px;flex-wrap:wrap}
.auto .txt{flex:1;min-width:220px}
.auto .txt b{font-size:15px}
.sw{position:relative;width:46px;height:26px;flex-shrink:0;cursor:pointer}
.sw input{display:none}
.sw i{position:absolute;inset:0;background:#DAD6CC;border-radius:20px;transition:.2s}
.sw i:after{content:"";position:absolute;width:20px;height:20px;left:3px;top:3px;background:#fff;border-radius:50%;transition:.2s;box-shadow:0 1px 3px rgba(0,0,0,.2)}
.sw input:checked+i{background:var(--ok)}
.sw input:checked+i:after{left:23px}
.dot{width:9px;height:9px;border-radius:50%;display:inline-block;background:#CFCABF}
.dot.on{background:var(--ok);box-shadow:0 0 0 4px var(--ok-l)}
.feed div{display:flex;gap:10px;padding:9px 0;border-bottom:1px solid var(--line2);font-size:13px}
.feed div:last-child{border-bottom:0}
.feed .t{color:var(--muted);width:92px;flex-shrink:0;font-size:12px;padding-top:1px}
.bar{height:6px;background:var(--line2);border-radius:6px;overflow:hidden;margin-top:12px}
.bar i{display:block;height:100%;background:var(--gold);border-radius:6px}

/* pulpit jak TO */
.g5{grid-template-columns:repeat(5,1fr)}
.g21{grid-template-columns:1.35fr 1fr;align-items:start}
.ct{font-size:19px}
.btn.big{width:100%;height:52px;font-size:15px;border-radius:13px;margin-top:14px}
.btn.gold[disabled]{opacity:.45}
.info{font-size:13px;color:var(--ink2);margin-top:12px;line-height:1.6}
.autorow{display:flex;align-items:center;gap:12px;margin-top:14px;padding:12px 14px;background:var(--bg);border-radius:12px;font-size:13px}
.note{border-top:1px solid var(--line);margin-top:14px;padding-top:12px;font-size:13px;color:var(--ink2)}
.note a{cursor:pointer;text-decoration:underline}
.pills{display:flex;gap:5px;flex-wrap:wrap;align-items:center}
.pill.sm{font-size:10.5px;padding:2px 8px}
.mini{display:grid;grid-template-columns:repeat(6,1fr);gap:8px;margin-bottom:14px}
.mini div{background:var(--bg);border-radius:11px;padding:9px 10px;text-align:center}
.mini b{display:block;font-family:Outfit,Arial;font-size:19px;font-weight:600}
.mini span{font-size:11px;color:var(--muted)}
.testbox{background:var(--bg);border-radius:13px;padding:12px 14px}
.chip.big{padding:9px 15px;font-size:13.5px;font-weight:600}
.chip.big b{color:var(--muted);font-weight:600}
.chip.big.on b{color:#C9C3B6}
.chip.off{text-decoration:line-through;opacity:.6}
.chip.add{border-style:dashed;color:var(--ink2)}
.nav a.ext{margin-top:14px;border:1px dashed var(--line)}
.nav a.m-more,#msheet{display:none}
#msheet.open{display:block;position:fixed;inset:0;z-index:60;background:rgba(23,20,14,.45)}
#msheet .ms-in{position:absolute;left:0;right:0;bottom:0;background:var(--card,#fff);border-radius:22px 22px 0 0;padding:16px 16px calc(18px + env(safe-area-inset-bottom));max-height:85vh;overflow:auto}
#msheet .ms-top{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;font-size:17px}
#msheet .ms-grid{display:grid;grid-template-columns:repeat(3,1fr);gap:8px}
#msheet .ms-grid a{display:flex;flex-direction:column;align-items:center;gap:6px;padding:14px 6px;border-radius:14px;background:var(--bg);text-decoration:none;color:var(--ink);font-size:13px;font-weight:600;text-align:center;position:relative;cursor:pointer}
#msheet .ms-grid a.on{background:var(--ink);color:#fff}
#msheet .ms-grid svg{width:22px;height:22px}
#msheet .bdg{position:absolute;top:6px;right:10px;background:var(--gold);color:#fff;font-size:11px;font-weight:700;border-radius:20px;padding:1px 7px}

/* edytor bloków */
.pvcol{position:sticky;top:16px;align-self:start}
.blk{border:1px solid var(--line);border-radius:13px;background:#fff;margin-bottom:10px;transition:border-color .15s,box-shadow .15s}
.blk.over{border-color:var(--gold);box-shadow:0 0 0 3px rgba(197,160,89,.22)}
.blk.drag{opacity:.35}
.blk.flash{animation:fl 1.2s}
@keyframes fl{0%{box-shadow:0 0 0 4px rgba(197,160,89,.45)}100%{box-shadow:none}}
.bh{display:flex;align-items:center;gap:8px;padding:7px 8px 7px 6px;background:var(--bg);border-radius:12px 12px 0 0;font-size:12.5px}
.grip{cursor:grab;color:#A8A397;font-size:17px;letter-spacing:-3px;padding:0 6px 0 4px;user-select:none;-webkit-user-select:none}
.grip:active{cursor:grabbing}
.bi{width:22px;height:22px;border-radius:6px;background:#fff;display:inline-flex;align-items:center;justify-content:center;font-size:12px;color:var(--gold-d)}
.ib{border:0;background:transparent;cursor:pointer;width:28px;height:28px;border-radius:7px;font-size:13px;color:var(--ink2)}
.ib:hover{background:#fff}.ib[disabled]{opacity:.25;cursor:default}.ib.del:hover{color:var(--err)}
.bb{padding:4px 12px 12px}
.fl{display:block;font-size:11.5px;font-weight:600;color:#7A766C;margin:8px 0 4px}
.bb input,.bb textarea{font-size:14px}
.bb textarea.auto{overflow:hidden;resize:none;min-height:46px}
.pal{display:flex;flex-wrap:wrap;gap:6px;margin:6px 0 4px;padding:12px;border:1px dashed var(--line);border-radius:13px}
.pal a{display:inline-flex;align-items:center;gap:6px;padding:7px 11px;border:1px solid var(--line);border-radius:9px;background:#fff;font-size:12.5px;font-weight:600;cursor:pointer}
.pal a:hover{border-color:var(--gold)}
.pal a i{font-style:normal;color:var(--gold-d)}
.tabs.top{max-width:640px;margin-bottom:16px}.tabs.top a{padding:10px 12px;font-size:13.5px}
.ftbl th{padding:6px 6px;font-size:10px}.ftbl td{padding:5px 4px}.ftbl input[type=text],.ftbl td input:not([type=checkbox]){height:36px;font-size:13px;padding:0 9px}
.warn{background:#FDF0E2;color:#8A4B0F;border-radius:11px;padding:10px 12px;font-size:13px;margin-bottom:10px}

/* szukaj */
.szgrid{display:grid;grid-template-columns:1.1fr .8fr 1.1fr 1fr;gap:12px}
.szprog{margin-top:12px;background:#EAF0FB;border-color:#D5E1F5;color:#2F5BA8}
.spin{display:inline-block;width:12px;height:12px;border:2px solid #2F5BA8;border-right-color:transparent;border-radius:50%;animation:sp 1s linear infinite;margin-right:8px;vertical-align:-1px}
@keyframes sp{to{transform:rotate(360deg)}}
@media (max-width:1100px){.szgrid{grid-template-columns:1fr 1fr}}
@media (max-width:600px){.szgrid{grid-template-columns:1fr}}
/* arkusz */
.arbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:12px;padding:12px 16px}
.arbar select,.arbar input{height:36px;width:auto;min-width:160px}
.arwrap{overflow-x:auto}
.ark{width:100%;border-collapse:collapse;min-width:760px}
.chip b.dr{background:var(--gold-l);color:var(--gold-d);border-radius:10px;padding:1px 7px;font-size:11px}.chip.on b.dr{background:#333;color:#E9D9B5}
.ark th{font-size:11px;font-weight:600;letter-spacing:.5px;text-transform:uppercase;color:var(--muted);text-align:left;padding:10px 8px;border-bottom:1px solid var(--line);background:#FBFAF7;position:sticky;top:0}
.ark td{border-bottom:1px solid var(--line2);border-right:1px solid var(--line2);padding:0}
.ark th:nth-child(2),.ark th:nth-child(3){width:30%}.ark th:nth-child(4),.ark th:nth-child(5){width:15%}
.ark td.n,.ark th.n{width:40px;text-align:center;color:var(--muted);font-size:12px;background:#FBFAF7}
.ark input,.ark select{border:0;border-radius:0;height:38px;background:transparent;padding:0 10px;font-size:13.5px;box-shadow:none}
.ark input:focus,.ark select:focus{background:#FFFCF3;box-shadow:inset 0 0 0 2px var(--gold)}
.ark input.bad{color:var(--err);background:var(--err-l)}
.ark select{min-width:190px;cursor:pointer}
.ark select.auto{color:var(--gold-d);font-style:italic}
.ark select.none{color:var(--muted)}
.ark td.st{border-right:0;white-space:nowrap;padding:0 6px;text-align:right;min-width:150px}
.ark td.st span{font-size:11.5px;font-weight:600;margin-right:6px;display:inline-block;max-width:260px;overflow:hidden;text-overflow:ellipsis;vertical-align:middle;white-space:nowrap}
.ark td.st .er{color:var(--err)}.ark td.st .ok{color:var(--ok)}
.ark tr.err td{background:#FFF8F7}

/* bulk */
.bulk{position:sticky;top:10px;z-index:5;display:none;background:var(--ink);color:#fff;border-radius:14px;padding:10px 14px;margin-bottom:12px;align-items:center;gap:10px;flex-wrap:wrap}
.bulk.on{display:flex}
.bulk select{height:34px;width:auto;background:#262626;border-color:#333;color:#fff}
.bulk .btn{height:34px}
input[type=checkbox]{width:17px;height:17px;accent-color:var(--ink);cursor:pointer}

/* editor */
.ed{display:grid;grid-template-columns:250px 1fr;gap:16px;align-items:start}
.blist a{display:flex;align-items:center;gap:10px;padding:11px 14px;border-bottom:1px solid var(--line2);text-decoration:none;cursor:pointer}
.blist a:last-child{border-bottom:0}
.blist a.on{background:var(--gold-l)}
.blist a .nm{flex:1;font-weight:600}
.blist a.off .nm{color:var(--muted);text-decoration:line-through}
.tabs{display:flex;gap:4px;background:var(--bg);border-radius:12px;padding:4px;margin-bottom:14px;flex-wrap:wrap}
.tabs a{flex:1;text-align:center;padding:8px 10px;border-radius:9px;font-weight:600;font-size:13px;cursor:pointer;color:var(--ink2);white-space:nowrap}
.tabs a.on{background:var(--card);color:var(--ink);box-shadow:0 1px 3px rgba(0,0,0,.08)}
.edwrap{display:grid;grid-template-columns:1.1fr 1fr;gap:20px}
.vars{display:flex;gap:6px;flex-wrap:wrap;margin-top:8px}
.vars span{font-family:ui-monospace,Menlo,monospace;font-size:11.5px;background:var(--gold-l);color:var(--gold-d);border-radius:7px;padding:3px 8px;cursor:pointer}
.prev{border:1px solid var(--line);border-radius:14px;overflow:hidden;background:#F4F3EF}
.prev .sb{padding:10px 14px;border-bottom:1px solid var(--line);background:var(--card);font-size:13px}
.prev iframe{width:100%;height:860px;border:0;display:block;background:#F4F3EF}

/* zgłoszenia */
.zg{padding:18px 20px;display:grid;grid-template-columns:1.1fr 1.3fr .9fr;gap:18px}
.zg+.zg{border-top:1px solid var(--line2)}
.zg .nm{font-size:16px;font-weight:600;font-family:Outfit,Arial}
.zg .tel{display:inline-flex;margin-top:8px}
.zg .q{font-size:12px;color:var(--muted);font-weight:600;margin-top:6px}
.zg .a{white-space:pre-wrap}
.zgnote{max-height:160px;overflow:auto;background:#FAF8F3;border:1px solid var(--line2);border-radius:10px;padding:8px 10px;font-size:13.5px;margin-top:4px}
.zgk{margin-top:10px;padding:8px 10px;border-radius:10px;background:#F4F1EA;font-size:13.5px;line-height:1.5}.zgk.due{background:#FDECEA;color:#9B2C22}

/* modal */
.ov{position:fixed;inset:0;background:rgba(21,21,21,.38);z-index:50;display:none;align-items:flex-start;justify-content:center;padding:6vh 14px;overflow:auto;-webkit-backdrop-filter:blur(3px);backdrop-filter:blur(3px)}
.ov.on{display:flex}
.mod{background:var(--card);border-radius:20px;width:100%;max-width:620px;box-shadow:0 30px 80px rgba(0,0,0,.18)}
.mod .mh{display:flex;align-items:center;justify-content:space-between;padding:18px 22px;border-bottom:1px solid var(--line)}
.mod .mh h3{font-size:18px}
.mod .mb{padding:6px 22px 20px}
.mod .mf{padding:14px 22px;border-top:1px solid var(--line);display:flex;gap:8px;justify-content:flex-end;flex-wrap:wrap}
.x{border:0;background:var(--bg);width:34px;height:34px;border-radius:10px;cursor:pointer;font-size:18px;line-height:1}
.g2f{display:grid;grid-template-columns:1fr 1fr;gap:0 12px}
.hist{max-height:180px;overflow:auto;margin-top:6px;border:1px solid var(--line2);border-radius:12px;padding:4px 12px}

/* toast */
#toast{position:fixed;left:50%;bottom:26px;transform:translateX(-50%) translateY(20px);background:var(--ink);color:#fff;padding:12px 18px;border-radius:12px;font-weight:600;font-size:13.5px;opacity:0;transition:.25s;z-index:99;pointer-events:none;max-width:90vw;text-align:center}
#toast.on{opacity:1;transform:translateX(-50%) translateY(0)}
#toast.err{background:var(--err)}

/* mobile */
@media (max-width:1300px){.g5{grid-template-columns:repeat(3,1fr)}.g21{grid-template-columns:1fr}}
@media (max-width:1100px){.g4{grid-template-columns:repeat(2,1fr)}.mini{grid-template-columns:repeat(3,1fr)}.edwrap{grid-template-columns:1fr}.prev iframe{height:520px}}
@media (max-width:820px){
  .pvcol{position:static}
  .app{display:block}
  .side{position:fixed;bottom:0;top:auto;left:0;right:0;height:auto;width:auto;flex-direction:row;padding:6px 6px calc(6px + env(safe-area-inset-bottom));border-right:0;border-top:1px solid var(--line);z-index:40}
  .side .logo,.side .tag{display:none}
  .nav{display:flex;width:100%}
  .nav a{flex:1;flex-direction:column;gap:3px;font-size:10.5px;padding:7px 2px;text-align:center;position:relative}
  .nav a .bdg{position:absolute;top:2px;right:18%;margin:0}
  .side .bottom{display:none}
  main{padding:20px 14px 110px}
  .head h1{font-size:24px}
  .g2,.g2f{grid-template-columns:1fr}
  .g5{grid-template-columns:1fr 1fr}
  .nav a.ext{margin-top:0;border:0}
  .nav a.m-hide{display:none}
  .nav a.m-more{display:flex}
  .nav a span:not(.bdg){max-width:100%;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
  .row>select{width:100%!important}
  .ed{grid-template-columns:1fr}
  .zg{grid-template-columns:1fr;gap:10px}
  .tbl.resp thead{display:none}
  .tbl.resp tr{display:grid;grid-template-columns:auto 1fr;gap:4px 12px;padding:12px 14px;border-bottom:1px solid var(--line2)}
  .tbl.resp td{border:0;padding:0;grid-column:2;min-width:0;overflow-wrap:anywhere}
  .tbl.resp td.c0{grid-column:1;grid-row:1 / span 3}
  .tbl.resp td.hm{display:none}
  .mod{border-radius:18px}
}
@media (max-width:480px){.g4{grid-template-columns:1fr 1fr}.stat .n{font-size:28px}.pad{padding:16px}}
.nocbox{margin-top:16px;border:1px dashed #D9CBA6;background:#FBF8F1;border-radius:14px;padding:16px}
.nochead{display:flex;gap:10px;align-items:baseline;flex-wrap:wrap}.nochead b{font-size:15px}
.nocgrid{display:grid;grid-template-columns:repeat(auto-fill,minmax(170px,1fr));gap:4px 12px}
.nocw{display:flex;gap:8px;align-items:center;cursor:pointer;font-size:14px;padding:3px 0}
.nocst{display:flex;gap:12px;align-items:flex-start;margin-top:10px;line-height:1.6}.nocst .spin{margin-top:5px;flex:none}
.nocwarn{margin-top:12px;background:#FFF4E5;border:1px solid #F1D3A3;border-radius:10px;padding:10px 12px;font-size:13px;line-height:1.6}
.nocwarn code{font-size:11px;word-break:break-all;background:#fff;padding:2px 6px;border-radius:6px}

.ico{display:inline-block;vertical-align:-3px;flex:none}
.ilink{display:inline-flex;align-items:center;gap:6px;color:inherit;text-decoration:none;border-bottom:1px dashed #CFC8B8}.ilink:hover{color:#8C6B2C}.ilink.due{color:#B3261E;font-weight:600;border:0}
.btn .ico{margin-right:2px}
.zgt td{vertical-align:middle}
.zgd{display:grid;grid-template-columns:1.25fr 1fr;gap:14px;align-items:start}
.h2{font-size:15px;margin:0 0 12px;display:flex;gap:8px;align-items:center}
.kv{display:flex;justify-content:space-between;gap:12px;padding:9px 0;border-top:1px solid var(--line2);font-size:14px}.kv:first-of-type{border-top:0}
.kv span{color:var(--muted)}.kv b{font-weight:500;text-align:right;white-space:pre-wrap;word-break:break-word}
.kv.col{flex-direction:column;gap:4px}.kv.col b{text-align:left}
.zgn{padding:10px 0;border-top:1px solid var(--line2)}.zgn:first-child{border-top:0;padding-top:0}
.zgn-t{font-size:12px;color:var(--muted);font-weight:600;margin-bottom:3px}.zgn-x{white-space:pre-wrap;font-size:14px;line-height:1.55}
.zgnext .big{font-family:Outfit,Arial;font-size:22px;font-weight:600;margin:6px 0 4px;display:flex;align-items:center;gap:8px}
.zgnext.due{border-color:#F1C4BF;background:#FFF6F5}
.wl{padding:10px 0;border-top:1px solid var(--line2)}.wl:first-child{border-top:0;padding-top:0}
.wd{display:grid;grid-template-columns:1fr 1fr;gap:16px}
.wd-r iframe{width:100%;height:560px;border:1px solid var(--line2);border-radius:12px;background:#F4F3EF}
.wdb{position:relative;border:1px solid var(--line2);border-radius:12px;padding:10px;margin-top:8px;background:#fff}
.wdb textarea{border:0;padding:4px 70px 4px 2px;min-height:90px;background:transparent}
.wdb.img img{display:block;max-width:100%;max-height:220px;border-radius:8px}
.pill.gold{background:#F7F0E2;color:#8C6B2C}
.wdl{font-size:11px;font-weight:700;letter-spacing:.06em;text-transform:uppercase;color:#A39E92;margin:0 0 4px 2px}
.wdh{font-size:17px;font-weight:600}
.wdt{position:absolute;top:6px;right:6px;display:flex;gap:2px;background:#fff;border-radius:8px;z-index:1}
.wdt a{cursor:pointer;padding:4px;border-radius:6px;color:#6B675E;display:flex}.wdt a:hover{background:#F2EFE8;color:#111}
@media(max-width:900px){.zgd,.wd{grid-template-columns:1fr}.wd-r iframe{height:420px}}
</style>
</head>
<body>
<?php if (!$logged): ?>
<div class="login">
  <div class="box">
    <div class="logo">ELOK<span>.</span></div>
    <div class="sub">Pozyskiwanie klientów</div>
    <form onsubmit="return window.doLogin(event)">
      <input id="l-user" placeholder="Login" autocomplete="username" autocapitalize="off" value="">
      <input id="l-pass" type="password" placeholder="Hasło" autocomplete="current-password">
      <button class="btn dark" style="width:100%;height:46px;margin-top:6px">Zaloguj się</button>
    </form>
    <div class="err" id="l-err"></div>
  </div>
</div>
<?php else: ?>
<div class="app">
  <aside class="side">
    <div class="logo">ELOK<span>.</span></div>
    <div class="tag">Pozyskiwanie klientów</div>
    <nav class="nav" id="nav"></nav>
    <div class="bottom"><a class="btn sm" style="width:100%" onclick="window.logout()">Wyloguj</a></div>
  </aside>
  <main id="main"></main>
</div>
<div class="ov" id="ov" onclick="if(event.target===this)window.closeModal()"><div class="mod" id="mod"></div></div>
<?php endif; ?>
<div id="toast"></div>
<script>if ('serviceWorker' in navigator) { navigator.serviceWorker.getRegistrations().then(function (rs) { rs.forEach(function (r) { var a = r.active || r.waiting || r.installing; if (!a || a.scriptURL.indexOf('push-sw.js') < 0) r.unregister(); }); }); if (window.caches) caches.keys().then(function (ks) { ks.forEach(function (k) { caches.delete(k); }); }); }</script>
<script>window.CSRF = <?= json_encode($_SESSION['csrf']) ?>; window.ELOK_TEST = <?= ELOK_TEST ? 'true' : 'false' ?>;</script>
<script src="app.js?v=<?= (int)$v ?>"></script>
</body>
</html>
