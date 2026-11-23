/* ELOK – Agent Eli: sama szuka firm i dokłada je do mailingu. Vanilla JS ES5 (bez backticków i funkcji strzałkowych). */
(function () {
'use strict';
var U = window.ELOKUI;
if (!U) return;
var api = U.api, esc = U.esc, toast = U.toast, busy = U.busy, stat = U.stat;
function $(id) { return document.getElementById(id); }
function fd(s) { if (!s) return '—'; var p = String(s).split(/[- :]/); return p[2] + '.' + p[1] + (p[3] ? ' ' + p[3] + ':' + p[4] : ''); }
var WOJ = ['dolnośląskie', 'kujawsko-pomorskie', 'lubelskie', 'lubuskie', 'łódzkie', 'małopolskie', 'mazowieckie', 'opolskie', 'podkarpackie', 'podlaskie', 'pomorskie', 'śląskie', 'świętokrzyskie', 'warmińsko-mazurskie', 'wielkopolskie', 'zachodniopomorskie'];
var LT = { znaleziono: 'Znalazła', kontakt: 'Kontakt', dodano: 'Zrobione', blad: 'Błąd', info: 'Info' };
var LC = { znaleziono: 'st-wyslano', kontakt: 'st-otworzyl', dodano: 'st-zainteresowany', blad: 'st-blad', info: 'st-nowy' };
var ZN = { nowe: 'Nowe firmy', praca: 'Szukają pracownika', strona: 'Słaba strona', auto: 'Dobra strona – automatyzacja' };
var VARS = { nowe: ['{firma}', '{miasto}', '{imie}', '{moj_telefon}'], praca: ['{stanowisko}', '{obowiazki}', '{firma}', '{miasto}', '{imie}', '{moj_telefon}'], strona: ['{www}', '{problem}', '{firma}', '{miasto}', '{imie}', '{moj_telefon}'], auto: ['{www}', '{pomysl}', '{firma}', '{miasto}', '{imie}', '{moj_telefon}'] };
var GADKI = {
  spi: ['Śpię. Obudź mnie przełącznikiem, nie palcem.', 'Zzz… jeszcze pięć minut.', 'Mam wolne. Nawet AI należy się urlop.', 'Nie ma mnie. Zostaw wiadomość po sygnale. Piiip.',
    'Śniło mi się, że wszystkie firmy odpisały na maile. Piękny sen.', 'Włącz mnie, to pogadamy. Teraz oszczędzam baterię.', 'Zostaw mnie, mam dziś dzień bez CEIDG.'],
  pracuje: ['Nie przeszkadzaj, właśnie czytam rejestr CEIDG. Fascynujące.', 'Szukam klientów. Ty idź zrób sobie kawę.', 'Jeszcze jedna strona i robię przerwę. Żartuję, nie robię przerw.',
    'Hej, to łaskocze! Wracam do pracy.', 'Ile kawy dziennie pijesz? Ja zero, a pracuję całą dobę.', 'Gdyby ktoś pytał, to ja znalazłam tego klienta.',
    'Klikasz mnie częściej niż klienci przycisk w mailu.', 'Widziałam dziś stronę z rokiem 2016 w stopce. Wytrzymałam i nic o tym nie napisałam.', 'Spokojnie, szef. Mam wszystko pod kontrolą.'],
  zajeta: ['Cicho, liczę… znaczy szukam klientów.', 'Pracuję! Nie widać? Patrz, jak mrugam.', 'Moment, właśnie zaglądam komuś na stronę.', 'Nie teraz, rozmawiam z Google.'],
  duzo: ['Dobra, dobra. Już wiem, że mnie lubisz.', 'Jeszcze raz i zgłoszę to do działu kadr.', 'Ała. Wiesz, że ja tu pracuję?']
};
var POKE = { n: 0, t: 0, h: null, last: '' };
// Głos Eli: najpierw nagranie (glos/eli_N.mp3 – np. z ElevenLabs, jeśli kiedyś dogramy), inaczej głos przeglądarki po polsku
function glosOn() { try { return localStorage.getItem('eli_glos') !== '0'; } catch (e) { return true; } }
function say(txt) {
  if (!glosOn() || !window.speechSynthesis) return;
  try {
    window.speechSynthesis.cancel();
    var u = new SpeechSynthesisUtterance(txt.replace(/…/g, '.')); u.lang = 'pl-PL';
    // najładniejszy dostępny kobiecy głos: neuronowe (Edge „Online (Natural)”, Premium/Enhanced na Macu) > Google > reszta
    var vs = window.speechSynthesis.getVoices(), best = null, bs = -99;
    for (var i = 0; i < vs.length; i++) {
      var v = vs[i], n = v.name || '';
      if (!/^pl/i.test(v.lang)) continue;
      var s = 0;
      if (/natural|neural|online/i.test(n)) s += 10;
      if (/premium|enhanced|ulepsz/i.test(n)) s += 8;
      if (/google/i.test(n)) s += 5;
      if (/zofia|agnieszka|zosia|ewa|paulina|maja|female/i.test(n)) s += 3;
      if (/krzysztof|marek|jacek|jan|male/i.test(n) && !/female/i.test(n)) s -= 20;
      if (s > bs) { bs = s; best = v; }
    }
    if (best) u.voice = best;
    // dobre głosy brzmią słodko same z siebie – lekko wyżej i spokojniej; słabe podbijamy mocniej
    u.rate = bs >= 8 ? 1.0 : 0.95;
    u.pitch = bs >= 8 ? 1.1 : 1.3;
    window.speechSynthesis.speak(u);
  } catch (e) { /* bez głosu */ }
}
var D = null, TAB = 'nowe', SEL = {}, TIMER = null, TYPE = null, WORKING = false, ED = null;

// ---------- wygląd (wstrzykiwany raz) ----------
function css() {
  if ($('ag-css')) return;
  var c = document.createElement('style'); c.id = 'ag-css';
  c.textContent =
    '.ag-hero{position:relative;overflow:hidden;background:#111;color:#fff;border-radius:var(--r);padding:22px 24px;display:flex;gap:24px;align-items:center;margin-bottom:14px}' +
    '.ag-hero:before{content:"";position:absolute;inset:0;background:radial-gradient(circle at 12% 50%,rgba(197,160,89,.28),transparent 45%);pointer-events:none}' +
    '.ag-hero.off:before{opacity:.25}' +
    '.ag-av{position:relative;width:118px;height:118px;flex-shrink:0}' +
    '.ag-av svg{position:relative;z-index:2;width:118px;height:118px;transition:transform .5s cubic-bezier(.2,1.6,.4,1)}' +
    '.ag-ring{position:absolute;inset:6px;border-radius:50%;border:2px solid rgba(197,160,89,.7);opacity:0}' +
    '.ag-hero.on .ag-ring{animation:agRing 2.6s ease-out infinite}.ag-hero.on .ag-ring.r2{animation-delay:.85s}.ag-hero.on .ag-ring.r3{animation-delay:1.7s}' +
    '@keyframes agRing{0%{transform:scale(.85);opacity:.9}100%{transform:scale(1.55);opacity:0}}' +
    '.ag-eye{transform-origin:center;transform-box:fill-box}' +
    '.ag-hero.on .ag-eye{animation:agBlink 4.2s infinite}' +
    '@keyframes agBlink{0%,92%,100%{transform:scaleY(1)}95%{transform:scaleY(.1)}}' +
    '.ag-hero.on .ag-look{animation:agLook 7s ease-in-out infinite}' +
    '@keyframes agLook{0%,30%,100%{transform:translateX(0)}40%,55%{transform:translateX(5px)}65%,80%{transform:translateX(-5px)}}' +
    '.ag-ant{transform-origin:center;transform-box:fill-box}.ag-hero.on .ag-ant{animation:agAnt 1.4s ease-in-out infinite}' +
    '@keyframes agAnt{0%,100%{opacity:1;filter:drop-shadow(0 0 0 #C5A059)}50%{opacity:.55;filter:drop-shadow(0 0 6px #E9D19A)}}' +
    '.ag-hero.off .ag-av svg{filter:grayscale(1);opacity:.55}' +
    '.ag-hero.wake .ag-av svg{transform:scale(1.12) rotate(-4deg)}' +
    '.ag-scan{position:absolute;left:18px;right:18px;height:2px;top:20px;z-index:3;background:linear-gradient(90deg,transparent,#E9D19A,transparent);opacity:0}' +
    '.ag-hero.work .ag-scan{opacity:1;animation:agScan 1.3s ease-in-out infinite alternate}' +
    '@keyframes agScan{from{top:22px}to{top:96px}}' +
    '.ag-txt{flex:1;min-width:0;position:relative;z-index:2}' +
    '.ag-txt h2{margin:0;font-family:Outfit,Arial;font-size:26px;font-weight:600;letter-spacing:-.3px}' +
    '.ag-txt h2 span{color:#C5A059}' +
    '.ag-type{margin-top:6px;min-height:22px;font-size:14.5px;color:#E6E1D6}' +
    '.ag-type i{display:inline-block;width:2px;height:15px;background:#C5A059;margin-left:2px;vertical-align:-2px;animation:agCur .9s steps(1) infinite}' +
    '@keyframes agCur{50%{opacity:0}}' +
    '.ag-tasks{display:flex;gap:8px;flex-wrap:wrap;margin-top:14px}' +
    '.ag-tk{display:inline-flex;align-items:center;gap:7px;font-size:12px;padding:5px 11px;border-radius:20px;background:rgba(255,255,255,.08);color:#CFCABF}' +
    '.ag-tk b{width:7px;height:7px;border-radius:50%;background:#6B675E}' +
    '.ag-hero.on .ag-tk.on b{background:#C5A059;animation:agDot 1.2s ease-in-out infinite}' +
    '.ag-tk.on{color:#fff}' +
    '@keyframes agDot{0%,100%{transform:scale(1);opacity:1}50%{transform:scale(1.6);opacity:.5}}' +
    '.ag-side{position:relative;z-index:2;display:flex;flex-direction:column;align-items:flex-end;gap:10px}' +
    '.ag-big{display:flex;align-items:center;gap:10px;background:rgba(255,255,255,.08);border-radius:14px;padding:10px 14px;font-weight:600;font-size:14px;cursor:pointer}' +
    '.ag-hero .btn{background:transparent;color:#fff;border-color:rgba(255,255,255,.25)}' +
    '.ag-ig{display:flex;gap:12px;align-items:flex-start;padding:12px 18px;border-top:1px solid var(--line)}' +
    '.ag-ig .m{flex:1;min-width:0}.ag-ig .m p{margin:4px 0 0;font-size:12.5px;color:var(--muted);white-space:pre-line}' +
    '.ag-ig .ac{display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end}' +
    '.ag-vars{display:flex;gap:6px;flex-wrap:wrap;margin:6px 0 2px}.ag-vars span{font-size:11.5px;padding:3px 9px;border-radius:12px;background:var(--bg);border:1px solid var(--line);cursor:pointer;font-family:monospace}' +
    '.ag-zm{font-size:10.5px;padding:1px 7px;border-radius:10px;background:var(--gold-l);color:var(--gold-d);font-weight:600;margin-left:6px}' +
    '.ag-av{cursor:pointer}.ag-av.poke svg{animation:agPoke .5s ease}' +
    '@keyframes agPoke{0%,100%{transform:rotate(0)}25%{transform:rotate(-9deg) scale(1.05)}50%{transform:rotate(7deg)}75%{transform:rotate(-4deg)}}' +
    '.ag-bub{position:absolute;left:112px;top:-4px;z-index:5;width:max-content;max-width:280px;background:#fff;color:#111;font-size:13px;line-height:1.4;font-weight:500;padding:9px 13px;border-radius:14px 14px 14px 4px;box-shadow:0 8px 24px rgba(0,0,0,.35);opacity:0;transform:translateY(6px) scale(.95);transition:opacity .25s,transform .25s;pointer-events:none}' +
    '.ag-bub.on{opacity:1;transform:none}' +
    '@media(max-width:760px){.ag-bub{left:50%;top:auto;bottom:100%;transform:translate(-50%,6px);border-radius:14px}.ag-bub.on{transform:translate(-50%,0)}}' +
    '@media(max-width:760px){.ag-hero{flex-direction:column;text-align:center;padding:20px 16px}.ag-tasks{justify-content:center}.ag-side{align-items:center}}';
  document.head.appendChild(c);
}

// Eli – prosta postać w kolorach ELOK (głowa-ekran, oczy, antenka)
function avatar() {
  return '<div class="ag-av" id="ag-av" onclick="window.AG.poke()" title="Kliknij Eli"><span class="ag-bub" id="ag-bub"></span><span class="ag-ring"></span><span class="ag-ring r2"></span><span class="ag-ring r3"></span><span class="ag-scan"></span>' +
    '<svg viewBox="0 0 120 120"><defs><linearGradient id="agG" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#E9D19A"/><stop offset="1" stop-color="#A9843F"/></linearGradient></defs>' +
    '<circle cx="60" cy="64" r="50" fill="#1B1B1B" stroke="url(#agG)" stroke-width="3"/>' +
    '<line x1="60" y1="14" x2="60" y2="4" stroke="url(#agG)" stroke-width="3" stroke-linecap="round"/><circle class="ag-ant" cx="60" cy="5" r="5" fill="#C5A059"/>' +
    '<rect x="24" y="40" width="72" height="46" rx="20" fill="#0D0D0D" stroke="#2C2A26" stroke-width="2"/>' +
    '<g class="ag-look"><rect class="ag-eye" x="41" y="54" width="12" height="17" rx="6" fill="#F2E6C9"/><rect class="ag-eye" x="67" y="54" width="12" height="17" rx="6" fill="#F2E6C9"/></g>' +
    '<path d="M50 96 Q60 102 70 96" stroke="#C5A059" stroke-width="3" fill="none" stroke-linecap="round"/>' +
    '<circle cx="22" cy="64" r="5" fill="#2C2A26"/><circle cx="98" cy="64" r="5" fill="#2C2A26"/></svg></div>';
}

// pisanie po literze – zmienia się co kilka sekund
function lines() {
  var c = D.cfg, out = [], i;
  if (WORKING) return ['Przeglądam rejestr CEIDG…', 'Czytam nowe ogłoszenia o pracę…', 'Sprawdzam strony firm na telefonie…', 'Szukam maili i profili na Instagramie…'];
  if (!c.on) return ['Odpoczywam. Włącz mnie, a zacznę szukać klientów.'];
  if (c.nowe.on) out.push('Pilnuję nowych firm w CEIDG – zarejestrowanych w ostatnich ' + c.nowe.dni + ' dniach.');
  if (c.praca.on) out.push('Czytam ogłoszenia o pracę biurową – szukam firm, które toną w papierach.');
  if (c.strona.on) out.push('Sprawdzam strony firm: telefon, szybkość, SSL, formularz.');
  if (c.form.on) out.push('Szukam formularzy kontaktowych u firm, które nie podają maila.');
  if (c.ig.on) out.push('Zbieram profile firm na Instagramie i piszę dla Ciebie wiadomości.');
  for (i = 0; i < D.log.length && i < 4; i++) out.push('Ostatnio: ' + D.log[i].info);
  return out.length ? out : ['Włącz któreś z zadań poniżej.'];
}
function typer() {
  if (TYPE) clearTimeout(TYPE.t);
  var L = lines(), li = 0, ci = 0;
  TYPE = { t: null };
  function step() {
    var el = $('ag-type'); if (!el) return;
    var s = L[li % L.length];
    if (ci <= s.length) { el.innerHTML = esc(s.substring(0, ci)) + '<i></i>'; ci++; TYPE.t = setTimeout(step, 24); }
    else { TYPE.t = setTimeout(function () { li++; ci = 0; L = lines(); step(); }, 2600); }
  }
  step();
}

function heroHtml() {
  var c = D.cfg, s = D.stan || {};
  var cls = 'ag-hero ' + (c.on || WORKING ? 'on' : 'off') + (WORKING ? ' work' : '');
  var tk = function (k, t) { return '<span class="ag-tk' + (c[k].on ? ' on' : '') + '"><b></b>' + t + '</span>'; };
  return '<div class="' + cls + '" id="ag-hero">' + avatar() +
    '<div class="ag-txt"><h2>' + (WORKING ? 'Eli <span>pracuje…</span>' : c.on ? 'Eli <span>szuka klientów</span>' : 'Eli <span>śpi</span>') + '</h2>' +
    '<div class="ag-type" id="ag-type"></div>' +
    '<div class="ag-tasks">' + tk('nowe', 'Nowe firmy') + tk('praca', 'Ogłoszenia o pracę') + tk('strona', 'Słabe strony') + tk('form', 'Formularze') + tk('ig', 'Instagram') + '</div>' +
    '<div class="mut" style="margin-top:10px;font-size:12px;color:#9A968C">Ostatnio: ' + fd(s.ostatnio) + (s.wynik ? ' · ' + esc(s.wynik) : '') + '</div></div>' +
    '<div class="ag-side"><label class="ag-big" for="ag-on">' + (c.on ? 'Eli aktywna' : 'Aktywuj Eli') + ' <span class="sw"><input type="checkbox" id="ag-on"' + (c.on ? ' checked' : '') + ' onchange="window.AG.toggle(this.checked)"><i></i></span></label>' +
    '<a class="btn" onclick="window.AG.run(this)">Uruchom teraz</a>' +
    '<a style="font-size:12px;color:#9A968C;cursor:pointer" onclick="window.AG.glos()">Głos Eli: ' + (glosOn() ? 'włączony' : 'wyłączony') + '</a></div></div>';
}

function sw(id, on, txt, sub) {
  return '<div class="autorow" style="margin:8px 0"><div style="flex:1"><b>' + txt + '</b>' + (sub ? '<div class="mut" style="font-size:12.5px">' + sub + '</div>' : '') + '</div>' +
    '<label class="sw"><input type="checkbox" id="' + id + '"' + (on ? ' checked' : '') + '><i></i></label></div>';
}
function dot(ok, txt, sub) {
  return '<div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--line)"><span class="dot' + (ok ? ' on' : '') + '" style="margin-top:6px"></span><div><b>' + txt + '</b><div class="mut" style="font-size:12.5px">' + sub + '</div></div></div>';
}
function host(u) { return String(u || '').replace(/^https?:\/\//, '').replace(/^www\./, '').replace(/\/.*$/, ''); }

function view(quiet) {
  css();
  if (!quiet) $('main').innerHTML = '<div class="empty">Ładowanie…</div>';
  api('ag_stan', {}, function (d) { D = d; render(); });
  if (TIMER) clearInterval(TIMER);
  TIMER = setInterval(function () {
    if (location.hash !== '#agent') { clearInterval(TIMER); TIMER = null; if (TYPE) clearTimeout(TYPE.t); return; }
    if (WORKING || document.querySelector('.ov.on')) return;
    api('ag_stan', {}, function (d) { D = d; render(); });
  }, 45000);
}

function render() {
  var d = D, c = d.cfg, s = d.stan || {}, dz = s.dzis || {}, L = d.licz, P = d.pol, i;
  var M = { w: 0, o: 0, r: 0, q: 0 };
  for (var k in d.mail) if (d.mail.hasOwnProperty(k)) { M.w += +d.mail[k].wyslano; M.o += +d.mail[k].otwarte; M.r += +d.mail[k].odpisali; M.q += +d.mail[k].w_kolejce; }
  var h = heroHtml();
  h += '<div class="grid g5">' +
    stat('Dziś do mailingu', ((+dz.nowe || 0) + (+dz.praca || 0) + (+dz.strona || 0)), 'nowe ' + (+dz.nowe || 0) + ' · ogłoszenia ' + (+dz.praca || 0) + ' · strony ' + (+dz.strona || 0)) +
    stat('Nowe firmy', L.nowe_znalezione, 'z mailem: ' + L.nowe_z_mailem + (L.nowe_szukam ? ' · sprawdzam: ' + L.nowe_szukam : '')) +
    stat('Sprawdzone strony', L.strona_spr, 'z problemem: ' + L.strona_zle + ' · w kolejce: ' + L.strona_pula) +
    stat('Wysłane maile', M.w, 'otworzyli: ' + M.o + ' · w kolejce: ' + M.q) +
    stat('Odpisali', '<span style="color:var(--ok)">' + M.r + '</span>', 'Instagram dziś: ' + L.ig_dzis) + '</div>';

  // ----- do zatwierdzenia -----
  var lists = { nowe: d.czekaN || [], praca: d.czekaP || [], strona: d.czekaS || [], auto: d.czekaA || [] };
  var info = {
    nowe: c.nowe.reczny ? 'Nowe firmy z mailem. Kliknij „Mail”, żeby zobaczyć i zmienić wiadomość dla konkretnej firmy.' : 'Nowe firmy idą do mailingu same – tu tylko te ponad dzienny limit.',
    praca: c.praca.reczny ? 'Ogłoszenia z mailem firmy. Sprawdź, czy to firma, a nie agencja.' : 'Ogłoszenia idą do mailingu same – tu tylko te ponad dzienny limit.',
    strona: c.strona.reczny ? 'Strony z wyraźnym problemem. Problem trafia do maila – kliknij „Mail”, żeby przeczytać.' : 'Firmy ze słabą stroną idą do mailingu same – tu tylko te ponad dzienny limit.',
    auto: 'Firmy z dobrą stroną – tu Eli proponuje jedną automatyzację dobraną do tego, co widzi na stronie (sklep, zapisy, formularz).'
  };
  h += '<div class="card sec"><div class="pad" style="padding-bottom:8px"><h2 class="ct" style="margin:0 0 8px">Do zatwierdzenia</h2><div class="chips">';
  for (k in ZN) if (ZN.hasOwnProperty(k)) h += '<span class="chip' + (TAB === k ? ' on' : '') + '" onclick="window.AG.tab(\'' + k + '\')">' + ZN[k] + ' <b>' + lists[k].length + '</b></span>';
  h += '<span class="chip" onclick="window.AG.oferty()">Wszystkie ogłoszenia</span><span class="chip" onclick="window.AG.audyty()">Wszystkie sprawdzone strony</span></div>' +
    '<p class="mut" style="margin:8px 0 0;font-size:12.5px">' + info[TAB] + '</p></div>';
  var rows = lists[TAB];
  if (!rows.length) h += '<div class="empty" style="display:block;padding:22px">Nic nie czeka</div>';
  else {
    h += '<div class="row" style="padding:0 18px 10px"><a class="btn sm dark" onclick="window.AG.ok(this)">Do mailingu</a><a class="btn sm" onclick="window.AG.no(this)">Odrzuć</a><a class="btn sm" onclick="window.AG.all()">Zaznacz wszystkie</a></div>';
    h += '<table class="tbl"><thead><tr><th style="width:30px"></th>' +
      (TAB === 'nowe' ? '<th>Firma</th><th>Miasto</th><th>E-mail</th>' : TAB === 'praca' ? '<th>Ogłoszenie</th><th>Firma</th><th>E-mail</th>' : TAB === 'auto' ? '<th>Firma i strona</th><th>Pomysł</th><th>E-mail</th>' : '<th>Firma i strona</th><th>Co jest nie tak</th><th>E-mail</th>') + '<th></th></tr></thead><tbody>';
    for (i = 0; i < rows.length; i++) {
      var r = rows[i], zm = +r.zm ? '<span class="ag-zm">zmieniony mail</span>' : '';
      var chk = '<td><input type="checkbox" ' + (SEL[r.id] ? 'checked ' : '') + 'onchange="window.AG.sel(' + r.id + ',this.checked)"></td>';
      var btn = '<td style="text-align:right;white-space:nowrap"><a class="btn sm" onclick="window.AG.mail(' + r.id + ')">Mail</a></td>';
      if (TAB === 'nowe') h += '<tr>' + chk + '<td><b>' + esc(r.nazwa) + '</b>' + zm + '<div class="mut" style="font-size:12px">' + esc(r.osoba || '') + (r.www ? ' · ' + esc(host(r.www)) : ' · bez strony') + '</div></td><td>' + esc(r.miasto) + '</td><td>' + esc(r.email) + '</td>' + btn + '</tr>';
      else if (TAB === 'praca') h += '<tr>' + chk + '<td><a href="' + esc(r.url) + '" target="_blank" rel="noopener"><b>' + esc(r.tytul) + '</b></a>' + zm + '<div class="mut" style="font-size:12px">' + esc(r.miasto) + ' · ' + fd(r.ogl_at) + '</div></td><td>' + esc(r.firma || r.konto) + (r.www ? '<div class="mut" style="font-size:12px">' + esc(host(r.www)) + '</div>' : '') + '</td><td>' + esc(r.email) + '<div class="mut" style="font-size:12px">' + esc((r.powod || '').replace('kontakt: ', '')) + '</div></td>' + btn + '</tr>';
      else {
        var pr = []; try { pr = JSON.parse(r.problemy) || []; } catch (e) { pr = []; }
        var ps = ''; for (var j = 0; j < pr.length && j < 3; j++) ps += '<span class="pill sm ' + (TAB === 'auto' ? 'st-zainteresowany' : 'st-przypomnienie') + '" style="margin:2px 4px 2px 0">' + esc(pr[j][0]) + '</span>';
        h += '<tr>' + chk + '<td><b>' + esc(r.nazwa) + '</b>' + zm + '<div class="mut" style="font-size:12px"><a href="' + esc(/^https?:/.test(r.www) ? r.www : 'http://' + r.www) + '" target="_blank" rel="noopener">' + esc(host(r.www)) + '</a> · ' + esc(r.miasto) + (r.psi !== null && r.psi !== undefined ? ' · Google ' + r.psi + '/100' : '') + '</div></td><td>' + ps + '</td><td>' + esc(r.email) + '</td>' + btn + '</tr>';
      }
    }
    h += '</tbody></table>';
  }
  h += '</div>';

  // ----- Instagram -----
  h += '<div class="card sec"><div class="pad" style="padding-bottom:10px"><h2 class="ct" style="margin:0">Instagram – do napisania <span class="mut" style="font-weight:500;font-size:13px">(' + d.ig.length + ' · napisane dziś: ' + L.ig_dzis + ', razem: ' + L.ig_wyslane + ')</span></h2>' +
    '<p class="mut" style="margin:6px 0 0;font-size:12.5px">Instagram nie pozwala na automatyczne wiadomości, więc wysyłasz Ty: „Kopiuj i otwórz” kopiuje gotową wiadomość i otwiera profil – wklejasz w wiadomości prywatnej i klikasz „Napisane”. Ok. 10 sekund na firmę.</p></div>';
  if (!d.ig.length) h += '<div class="empty" style="display:block;padding:18px">' + (c.ig.on ? 'Eli jeszcze nie znalazła profili – szuka ich na stronach firm z Twoich list.' : 'Zadanie Instagram jest wyłączone.') + '</div>';
  for (i = 0; i < d.ig.length && i < 15; i++) {
    var g = d.ig[i];
    h += '<div class="ag-ig"><div class="m"><b>@' + esc(g.ig) + '</b> <span class="mut">· ' + esc(g.nazwa) + (g.miasto ? ', ' + esc(g.miasto) : '') + '</span>' + (g.problem ? ' <span class="pill sm st-przypomnienie">' + esc(g.problem) + '</span>' : '') + '<p>' + esc(g.msg) + '</p></div>' +
      '<div class="ac"><a class="btn sm dark" onclick="window.AG.igOpen(' + i + ')">Kopiuj i otwórz</a><a class="btn sm" onclick="window.AG.igDone(' + g.id + ',\'ok\')">Napisane</a><a class="btn sm" onclick="window.AG.igDone(' + g.id + ',\'pomin\')">Pomiń</a></div></div>';
  }
  h += '</div>';

  // ----- formularze -----
  var fm = d.form || [];
  h += '<div class="card sec"><div class="pad" style="padding-bottom:10px"><h2 class="ct" style="margin:0">Formularze do wypełnienia <span class="mut" style="font-weight:500;font-size:13px">(' + fm.length + ' · razem wysłane: ' + (L.form_wyslane || 0) + ')</span></h2>' +
    '<p class="mut" style="margin:6px 0 0;font-size:12.5px">Firmy bez maila, ale z formularzem na stronie i konkretnym problemem. „Kopiuj i otwórz” kopiuje wiadomość i otwiera formularz – wklejasz, wpisujesz swoje dane i wysyłasz. Formularze z zabezpieczeniem (CAPTCHA) są na końcu listy.</p></div>';
  if (!fm.length) h += '<div class="empty" style="display:block;padding:18px">' + (c.form.on ? 'Eli jeszcze nie znalazła – sprawdza strony firm bez maila z Twoich list.' : 'Zadanie Formularze jest wyłączone.') + '</div>';
  for (i = 0; i < fm.length && i < 15; i++) {
    var f2 = fm[i];
    h += '<div class="ag-ig"><div class="m"><b>' + esc(f2.nazwa) + '</b> <span class="mut">· ' + esc(host(f2.www)) + (f2.miasto ? ', ' + esc(f2.miasto) : '') + '</span> <span class="pill sm st-przypomnienie">' + esc(f2.problem) + '</span>' + (+f2.captcha ? ' <span class="pill sm st-nowy">CAPTCHA</span>' : '') + '<p>' + esc(f2.msg) + '</p></div>' +
      '<div class="ac"><a class="btn sm dark" onclick="window.AG.fmOpen(' + i + ')">Kopiuj i otwórz</a><a class="btn sm" onclick="window.AG.fmDone(' + f2.id + ',\'ok\')">Wysłane</a><a class="btn sm" onclick="window.AG.fmDone(' + f2.id + ',\'pomin\')">Pomiń</a></div></div>';
  }
  h += '</div>';

  // ----- zadania -----
  h += '<div class="grid g2 sec">';
  h += '<div class="card pad"><h2 class="ct">1. Nowe firmy (CEIDG)</h2>' +
    sw('n-on', c.nowe.on, 'Szukaj nowo zarejestrowanych firm', 'Mail: klienci szukają w Google – strona, która zbiera zapytania') +
    sw('n-r', c.nowe.reczny, 'Zatwierdzam ręcznie', 'Wyłączone = idą do mailingu same') +
    '<div class="g2f"><div><label class="f">Zarejestrowane w ostatnich (dni)</label><input id="n-dni" type="number" min="1" max="60" value="' + (+c.nowe.dni) + '"></div>' +
    '<div><label class="f">Dziennie do mailingu</label><input id="n-dz" type="number" min="0" max="300" value="' + (+c.nowe.dziennie) + '"></div></div>' +
    '<label class="f">Kody PKD (puste = wszystkie branże)</label><input id="n-pkd" value="' + esc(c.nowe.pkd) + '" placeholder="np. 4520Z, 4332Z, 9602Z">' +
    '<label class="f">Województwa (nic nie zaznaczone = cała Polska)</label><div class="chips" id="n-woj">';
  for (i = 0; i < WOJ.length; i++) h += '<span class="chip' + (c.nowe.woj.indexOf(WOJ[i]) >= 0 ? ' on' : '') + '" data-w="' + WOJ[i] + '" onclick="this.classList.toggle(\'on\')">' + WOJ[i] + '</span>';
  h += '</div>' + foot('nowe') + '</div>';

  h += '<div class="card pad"><h2 class="ct">2. Szukają pracownika (OLX)</h2>' +
    sw('p-on', c.praca.on, 'Szukaj ogłoszeń o pracę biurową', 'Mail: obowiązki z ogłoszenia, które da się zautomatyzować') +
    sw('p-r', c.praca.reczny, 'Zatwierdzam ręcznie', 'Na start zalecane – sprawdzasz, czy to prawdziwa firma') +
    '<div class="g2f"><div><label class="f">Dziennie do mailingu</label><input id="p-dz" type="number" min="0" max="300" value="' + (+c.praca.dziennie) + '"></div><div></div></div>' +
    '<label class="f">Czego szukać (jedna fraza w linii)</label><textarea id="p-fr" rows="5">' + esc(c.praca.frazy) + '</textarea>' +
    '<div class="hint">Pomija agencje pracy, „pracę zdalną” i ogłoszenia bez faktur, zamówień czy dokumentów w opisie.</div>' + foot('praca') + '</div>';

  h += '<div class="card pad"><h2 class="ct">3. Słaba strona</h2>' +
    sw('s-on', c.strona.on, 'Sprawdzaj strony firm', 'Telefon, szybkość (test Google), SSL, formularz, stara stopka – problem trafia do maila') +
    sw('s-r', c.strona.reczny, 'Zatwierdzam ręcznie', 'Na start zalecane – zobaczysz, co Eli znajduje') +
    sw('s-psi', c.strona.psi, 'Test szybkości Google (PageSpeed)', 'Dokładniejszy, ale wolniejszy – ok. 20 s na stronę') +
    sw('s-auto', c.strona.auto, 'Dobra strona → zaproponuj automatyzację', 'Jeden pomysł dobrany do firmy: sklep, zapisy online, zapytania na telefon') +
    '<div class="g2f"><div><label class="f">Dziennie do mailingu</label><input id="s-dz" type="number" min="0" max="300" value="' + (+c.strona.dziennie) + '"></div><div><label class="f">Klucz Google PageSpeed (opcjonalnie)</label><input id="s-key" value="' + esc(P.psi_key || '') + '" placeholder="bez klucza Google często odmawia"></div></div>' +
    '<label class="f">Sprawdź dowolną stronę teraz</label><div class="row"><input id="s-url" class="grow" placeholder="np. carbonmiledesign.pl" onkeydown="if(event.key===\'Enter\')window.AG.check()"><a class="btn" id="s-btn" onclick="window.AG.check()">Sprawdź</a></div>' +
    '<div class="hint">Eli bierze firmy ze stroną i mailem z Twoich list w „Szukaj firm”, które jeszcze nie dostały maila (teraz: ' + L.strona_pula + '). Pisze tylko, gdy znajdzie wyraźny problem.</div>' + foot('strona') + '</div>';

  h += '<div class="card pad"><h2 class="ct">4. Formularze kontaktowe</h2>' +
    sw('f-on', c.form.on, 'Szukaj formularzy u firm bez maila', 'Tylko gdy Eli znajdzie na stronie konkretny problem – inaczej nie piszemy') +
    '<label class="f">Wiadomość (zmienne: {www}, {problem_krotko}, {imie}, {moj_telefon}, {moj_email})</label><textarea id="f-msg" rows="8">' + esc(c.form.msg) + '</textarea>' +
    '<div class="row" style="margin-top:12px"><a class="btn dark" onclick="window.AG.save(this)">Zapisz</a></div></div>';
  h += '<div class="card pad"><h2 class="ct">5. Instagram</h2>' +
    sw('i-on', c.ig.on, 'Szukaj profili firm na Instagramie', 'Na stronach firm z Twoich list – najpierw tych bez maila') +
    '<label class="f">Wiadomość (zmienne: {firma}, {imie}, {www}, {problem_ig})</label><textarea id="i-msg" rows="5">' + esc(c.ig.msg) + '</textarea>' +
    '<div class="hint">{problem_ig} – jeśli Eli sprawdziła stronę, wstawi np. „i zauważyłem, że strona nie jest dopasowana do telefonu”, a jeśli nie – nic.</div>' +
    '<div class="row" style="margin-top:12px"><a class="btn dark" onclick="window.AG.save(this)">Zapisz</a></div></div>';
  h += '</div>';

  // ----- połączenia + dziennik -----
  var br = d.branze, bl = '';
  for (i = 0; i < br.length; i++) { var mm = d.mail[br[i].id] || {}; bl += dot(+br[i].aktywna, 'Branża „' + esc(br[i].nazwa) + '”', 'limit ' + (+br[i].limit_dzienny || 'bez') + '/dzień · w kolejce ' + (mm.w_kolejce || 0) + ' · wysłane ' + (mm.wyslano || 0)); }
  h += '<div class="grid g2 sec"><div class="card pad"><h2 class="ct">Z czym jest połączona</h2>' +
    dot(P.ceidg, 'CEIDG – rejestr firm', P.ceidg ? 'Token jest' + (P.ceidg_debug ? ' · ostatni błąd: ' + esc(P.ceidg_debug) : '') : 'Brak tokenu – wpisz w Ustawieniach') +
    dot(P.olx && P.olx.indexOf(' 200') > 0, 'OLX – ogłoszenia o pracę', P.olx ? 'Ostatnie zapytanie: ' + esc(P.olx) : 'Jeszcze nie pytała') +
    dot(true, 'Google PageSpeed – test stron', 'Darmowy, bez konta') +
    dot(P.auto, 'Mailing (wysyłka)', (P.auto ? 'Włączony' : 'Wyłączony – włącz na Pulpicie') + ' · godziny ' + esc(P.godz) + ' · limit łączny ' + P.limit + '/dzień') +
    dot(!!P.cron, 'Cron (co 15 minut)', 'Ostatnio: ' + fd(P.cron)) +
    dot(P.push, 'Powiadomienia na telefon', 'Wieczorem podsumowanie dnia') + bl + '</div>';
  h += '<div class="card pad"><h2 class="ct">Co robiła</h2><div class="feed" style="max-height:520px;overflow:auto">';
  if (!d.log.length) h += '<div class="empty" style="display:block">Jeszcze nic – aktywuj Eli albo kliknij „Uruchom teraz”.</div>';
  for (i = 0; i < d.log.length; i++) {
    var l = d.log[i];
    h += '<div><span class="t">' + fd(l.created_at) + '</span><span><span class="pill sm ' + (LC[l.typ] || 'st-nowy') + '">' + esc(LT[l.typ] || l.typ) + '</span> ' + esc(l.info) + '</span></div>';
  }
  h += '</div></div></div>';
  $('main').innerHTML = h;
  typer();
}
function foot(typ) {
  return '<div class="row" style="margin-top:12px"><a class="btn dark" onclick="window.AG.save(this)">Zapisz</a><a class="btn" onclick="window.AG.tpl(\'' + typ + '\')">Maile – zmień treść</a><a class="btn" onclick="window.AG.prev(\'' + typ + '\')">Podgląd</a>' +
    (typ === 'strona' ? '<a class="btn" onclick="window.AG.tpl(\'auto\')">Maile – automatyzacje</a>' : '') + '</div>';
}

function gather() {
  var woj = [], ch = $('n-woj') ? $('n-woj').querySelectorAll('.chip.on') : [];
  for (var i = 0; i < ch.length; i++) woj.push(ch[i].getAttribute('data-w'));
  return {
    on: $('ag-on').checked,
    nowe: { on: $('n-on').checked, reczny: $('n-r').checked, dni: $('n-dni').value, dziennie: $('n-dz').value, pkd: $('n-pkd').value, woj: woj },
    praca: { on: $('p-on').checked, reczny: $('p-r').checked, dziennie: $('p-dz').value, frazy: $('p-fr').value },
    strona: { on: $('s-on').checked, reczny: $('s-r').checked, psi: $('s-psi').checked, auto: $('s-auto').checked, dziennie: $('s-dz').value, psi_key: $('s-key').value },
    ig: { on: $('i-on').checked, msg: $('i-msg').value },
    form: { on: $('f-on').checked, msg: $('f-msg').value }
  };
}
function ids() { var a = []; for (var k in SEL) if (SEL.hasOwnProperty(k) && SEL[k]) a.push(+k); return a; }
function rowsOf(t) { return t === 'praca' ? D.czekaP : t === 'strona' ? D.czekaS : t === 'auto' ? D.czekaA : D.czekaN; }
function showPreview(p) {
  window.openModal('Podgląd: ' + p.temat, '<iframe id="ag-pf" style="width:100%;height:520px;border:0;border-radius:10px;background:#F4F3EF"></iframe>', '<a class="btn" onclick="window.closeModal()">Zamknij</a>', true);
  var f = $('ag-pf'); f.contentWindow.document.open(); f.contentWindow.document.write(p.html); f.contentWindow.document.close();
}
function varChips(typ, target) {
  var v = VARS[typ], h = '<div class="ag-vars">';
  for (var i = 0; i < v.length; i++) h += '<span onclick="window.AG.ins(\'' + target + '\',\'' + v[i] + '\')">' + v[i] + '</span>';
  return h + '</div>';
}
function copy(t) {
  try { if (navigator.clipboard && window.isSecureContext) { navigator.clipboard.writeText(t); return; } } catch (e) { /* dalej */ }
  var a = document.createElement('textarea'); a.value = t; a.style.position = 'fixed'; a.style.opacity = '0'; document.body.appendChild(a); a.select();
  try { document.execCommand('copy'); } catch (e2) { /* nic */ }
  document.body.removeChild(a);
}

window.AG = {
  glos: function () { try { localStorage.setItem('eli_glos', glosOn() ? '0' : '1'); } catch (e) { /* nic */ } render(); if (glosOn()) say('Cześć! Teraz mnie słychać.'); },
  poke: function () {
    var now = Date.now(); POKE.n = now - POKE.t < 2500 ? POKE.n + 1 : 1; POKE.t = now;
    var set = POKE.n >= 5 ? GADKI.duzo : WORKING ? GADKI.zajeta : (D && D.cfg.on ? GADKI.pracuje : GADKI.spi), txt = POKE.last, g = 0;
    while (txt === POKE.last && g++ < 10) txt = set[Math.floor(Math.random() * set.length)];
    POKE.last = txt;
    say(txt);
    var b = $('ag-bub'), av = $('ag-av'); if (!b || !av) return;
    b.textContent = txt; b.className = 'ag-bub on';
    av.className = 'ag-av'; void av.offsetWidth; av.className = 'ag-av poke';
    if (POKE.h) clearTimeout(POKE.h);
    POKE.h = setTimeout(function () { var x = $('ag-bub'); if (x) x.className = 'ag-bub'; }, 3800);
  },
  view: function () { SEL = {}; WORKING = false; view(); },
  tab: function (t) { TAB = t; SEL = {}; render(); },
  sel: function (id, on) { SEL[id] = on; },
  all: function () { var rows = rowsOf(TAB); for (var i = 0; i < rows.length; i++) SEL[rows[i].id] = true; render(); },
  save: function (btn) {
    busy(btn, 1);
    api('ag_zapisz', gather(), function () { busy(btn, 0); toast('Zapisano'); view(true); }, function () { busy(btn, 0); });
  },
  // włączenie: animacja „budzenia się” + od razu pierwszy przebieg
  toggle: function (on) {
    api('ag_zapisz', gather(), function () {
      D.cfg.on = on ? 1 : 0;
      if (!on) { toast('Eli odpoczywa'); render(); return; }
      render();
      var hero = $('ag-hero'); if (hero) hero.className += ' wake';
      var el = $('ag-type'); if (TYPE) clearTimeout(TYPE.t); if (el) el.innerHTML = 'Cześć! Już zaczynam szukać klientów…<i></i>';
      say('Cześć! Już zaczynam szukać klientów.');
      setTimeout(function () { window.AG.run(null); }, 1200);
    });
  },
  run: function (btn) {
    if (WORKING) return;
    WORKING = true; if (btn) busy(btn, 1);
    render();
    api('ag_uruchom', {}, function (r) { WORKING = false; if (btn) busy(btn, 0); toast(r.wynik || 'Gotowe'); view(true); }, function () { WORKING = false; if (btn) busy(btn, 0); render(); });
  },
  ok: function (btn) {
    var a = ids(); if (!a.length) { toast('Zaznacz firmy', 'err'); return; }
    busy(btn, 1);
    api('ag_zatwierdz', { ids: a, typ: TAB }, function (r) { busy(btn, 0); toast('Dodano do mailingu: ' + r.n); SEL = {}; view(true); }, function () { busy(btn, 0); });
  },
  no: function (btn) {
    var a = ids(); if (!a.length) { toast('Zaznacz firmy', 'err'); return; }
    busy(btn, 1);
    api('ag_odrzuc', { ids: a, typ: TAB }, function (r) { busy(btn, 0); toast('Odrzucono: ' + r.n); SEL = {}; view(true); }, function () { busy(btn, 0); });
  },
  prev: function (typ) { api('ag_podglad', { typ: typ }, showPreview); },
  ins: function (id, v) {
    var t = $(id); if (!t) return;
    var a = t.selectionStart || t.value.length, b = t.selectionEnd || a;
    t.value = t.value.substring(0, a) + v + t.value.substring(b); t.focus(); t.selectionStart = t.selectionEnd = a + v.length;
  },
  // mail dla jednej firmy
  mail: function (id) {
    api('ag_mail_get', { typ: TAB, id: id }, function (m) {
      ED = { typ: TAB, id: id };
      window.openModal('Mail do tej firmy',
        '<p class="mut" style="margin:4px 0 10px;font-size:12.5px">Zmiany dotyczą tylko tej jednej firmy. Ogólny szablon zmienisz przyciskiem „Maile – zmień treść” przy zadaniu.' + (m.wlasny ? ' <b>Ta firma ma już własną wersję.</b>' : '') + '</p>' +
        '<label class="f">Temat</label><input id="agm-t" value="' + esc(m.temat) + '">' +
        '<label class="f">Treść (podpis doda się sam)</label><textarea id="agm-x" rows="14">' + esc(m.tekst) + '</textarea>',
        (m.wlasny ? '<a class="btn" onclick="window.AG.mailSave(1)">Przywróć ogólny</a>' : '') + '<a class="btn" onclick="window.AG.mailPrev()">Podgląd</a><a class="btn dark" onclick="window.AG.mailSave(0)">Zapisz dla tej firmy</a>', true);
    });
  },
  mailSave: function (reset) {
    api('ag_mail_zapisz', { typ: ED.typ, id: ED.id, temat: reset ? '' : $('agm-t').value, tekst: reset ? '' : $('agm-x').value, reset: reset ? 1 : 0 }, function () {
      window.closeModal(); toast(reset ? 'Przywrócono ogólny mail' : 'Zapisano mail dla tej firmy'); view(true);
    });
  },
  mailPrev: function () {
    var t = $('agm-t').value, x = $('agm-x').value, e = ED;
    api('ag_mail_zapisz', { typ: e.typ, id: e.id, temat: t, tekst: x }, function () { api('ag_podglad', { typ: e.typ, id: e.id }, showPreview); });
  },
  // szablony maili zadania
  tpl: function (typ) {
    api('ag_tpl_get', { typ: typ }, function (r) {
      ED = { typ: typ, t: r.tpl, cur: 'pierwszy' };
      window.AG.tplShow();
    });
  },
  tplShow: function () {
    var T = { pierwszy: 'Pierwszy mail', przypomnienie: 'Przypomnienie', przypomnienie2: 'Ostatnie przypomnienie' }, h = '<div class="chips" style="margin-bottom:10px">';
    for (var k in T) if (T.hasOwnProperty(k)) h += '<span class="chip' + (ED.cur === k ? ' on' : '') + '" onclick="window.AG.tplTab(\'' + k + '\')">' + T[k] + '</span>';
    var x = ED.t[ED.cur];
    h += '</div><label class="f">Temat</label><input id="agt-t" value="' + esc(x.temat) + '">' +
      '<label class="f">Treść (podpis doda się sam)</label>' + varChips(ED.typ, 'agt-x') + '<textarea id="agt-x" rows="12">' + esc(x.tekst) + '</textarea>' +
      '<div class="hint">Kliknij zmienną, żeby wstawić ją w miejscu kursora. Pusta linia = nowy akapit.</div>' +
      '<div class="g2f"><div><label class="f">Napis na przycisku (puste = bez przycisku)</label><input id="agt-b" value="' + esc(x.btn || '') + '"></div><div><label class="f">Pod przyciskiem (mały tekst)</label><input id="agt-s" value="' + esc(x.sub || '') + '"></div></div>' +
      '<label class="f">Po przycisku (np. „albo odpisz na maila lub zadzwoń”)</label><textarea id="agt-p" rows="2">' + esc(x.po || '') + '</textarea>' +
      '<div class="hint">Kliknięcie w przycisk widzisz w Firmach i w Zgłoszeniach – klient trafia na formularz umówienia konsultacji.</div>';
    window.openModal('Maile Eli – ' + ZN[ED.typ], h, '<a class="btn" onclick="window.AG.tplReset()">Przywróć gotowe</a><a class="btn" onclick="window.AG.tplPrev()">Podgląd</a><a class="btn dark" onclick="window.AG.tplSave()">Zapisz</a>', true);
  },
  tplKeep: function () { if ($('agt-t')) { var x = ED.t[ED.cur]; x.temat = $('agt-t').value; x.tekst = $('agt-x').value; x.btn = $('agt-b').value; x.sub = $('agt-s').value; x.po = $('agt-p').value; } },
  tplTab: function (k) { window.AG.tplKeep(); ED.cur = k; window.AG.tplShow(); },
  tplSave: function (cb) {
    window.AG.tplKeep();
    api('ag_tpl_zapisz', { typ: ED.typ, tpl: ED.t }, function () { if (typeof cb === 'function') { cb(); return; } window.closeModal(); toast('Zapisano maile'); });
  },
  tplPrev: function () { var typ = ED.typ; window.AG.tplSave(function () { api('ag_podglad', { typ: typ }, showPreview); }); },
  tplReset: function () {
    var typ = ED.typ;
    window.ask('Przywrócić gotowe maile?', 'Twoje zmiany w mailach „' + ZN[typ] + '” zostaną zastąpione gotowymi tekstami.', 'Przywróć', function () {
      api('ag_tpl_reset', { typ: typ }, function () { toast('Przywrócono'); window.AG.tpl(typ); });
    });
  },
  // Instagram
  igOpen: function (i) {
    var g = D.ig[i]; if (!g) return;
    copy(g.msg);
    window.open('https://www.instagram.com/' + encodeURIComponent(g.ig) + '/', '_blank', 'noopener');
    toast('Wiadomość skopiowana – wklej ją na Instagramie');
  },
  igDone: function (id, a) {
    api('ag_ig', { id: id, akcja: a === 'ok' ? 'wyslane' : 'pomin' }, function () { toast(a === 'ok' ? 'Zapisane jako napisane' : 'Pominięte'); view(true); });
  },
  check: function () {
    var u = $('s-url').value.trim(), b = $('s-btn'); if (!u) { toast('Wpisz adres strony', 'err'); return; }
    busy(b, 1); WORKING = true; var hero = $('ag-hero'); if (hero) hero.className = 'ag-hero on work';
    var el = $('ag-type'); if (TYPE) clearTimeout(TYPE.t); if (el) el.innerHTML = 'Sprawdzam ' + esc(u) + ' jak klient z telefonu… (test Google trwa ok. 20 s)<i></i>';
    api('ag_sprawdz', { www: u }, function (r) {
      busy(b, 0); WORKING = false; if (hero) hero.className = 'ag-hero ' + (D.cfg.on ? 'on' : 'off'); typer();
      var h = '<div class="grid g2" style="gap:10px;margin-bottom:12px">' +
        '<div class="card pad stat"><div class="l">Test szybkości Google (telefon)</div><div class="n">' + (r.psi === null ? '—' : r.psi + '<small>/100</small>') + '</div><div class="s">otwarcie strony: ' + String(r.sekundy).replace('.', ',') + ' s</div></div>' +
        '<div class="card pad stat"><div class="l">Czy Eli by napisała</div><div class="n" style="font-size:24px">' + (r.pisze ? 'Tak' : 'Nie') + '</div><div class="s">' + (r.pisze ? 'znalazła wyraźny problem' : 'strona jest w porządku') + '</div></div></div>';
      h += '<h3 style="margin:6px 0">Co znalazła</h3>';
      if (!r.problemy.length) h += '<p class="mut">Nic – strona jest w porządku.</p>';
      for (var i = 0; i < r.problemy.length; i++) h += '<div style="padding:8px 0;border-bottom:1px solid var(--line)"><span class="pill sm ' + (r.problemy[i][2] >= 3 ? 'st-blad' : r.problemy[i][2] >= 2 ? 'st-przypomnienie' : r.problemy[i][2] >= 1 ? 'st-nowy' : 'st-wypisany') + '">' + (r.problemy[i][2] >= 3 ? 'traci klientów' : r.problemy[i][2] >= 2 ? 'ważne' : r.problemy[i][2] >= 1 ? 'drobne' : 'techniczne – nie piszemy') + '</span> ' + esc(r.problemy[i][1].replace(/^- /, '').replace(/,$/, '.')) + '</div>';
      h += '<p class="mut" style="font-size:12.5px;margin-top:10px">Formularz: ' + (r.form ? esc(r.form) + (+r.captcha ? ' (z CAPTCHA)' : '') : 'nie znalazła') + ' · Instagram: ' + (r.ig ? '@' + esc(r.ig) : 'nie znalazła') + '</p>';
      h += '<h3 style="margin:14px 0 6px">Tak wyglądałby mail: ' + esc(r.temat) + '</h3><iframe id="ag-cf" style="width:100%;height:460px;border:0;border-radius:10px;background:#F4F3EF"></iframe>';
      window.openModal('Eli sprawdziła: ' + u, h, '<a class="btn" onclick="window.closeModal()">Zamknij</a>', true);
      var f = $('ag-cf'); f.contentWindow.document.open(); f.contentWindow.document.write(r.html); f.contentWindow.document.close();
    }, function () { busy(b, 0); WORKING = false; if (hero) hero.className = 'ag-hero ' + (D.cfg.on ? 'on' : 'off'); typer(); });
  },
  fmOpen: function (i) {
    var f = D.form[i]; if (!f) return;
    copy(f.msg);
    window.open(f.url, '_blank', 'noopener');
    toast('Wiadomość skopiowana – wklej ją w formularzu');
  },
  fmDone: function (id, a) {
    api('ag_form', { id: id, akcja: a === 'ok' ? 'wyslane' : 'pomin' }, function () { toast(a === 'ok' ? 'Zapisane jako wysłane' : 'Pominięte'); view(true); });
  },
  oferty: function () {
    api('ag_oferty', {}, function (r) {
      var ST = { nowa: 'Nowa', szukam: 'Szukam maila', gotowe: 'Z mailem', brak_maila: 'Brak maila', odrzucona: 'Odrzucona', dodano: 'W mailingu', juz_jest: 'Już w Firmach' };
      var h = '<table class="tbl"><thead><tr><th>Ogłoszenie</th><th>Status</th><th>Dlaczego</th></tr></thead><tbody>';
      if (!r.rows.length) h += '<tr><td colspan="3" class="mut">Jeszcze nic</td></tr>';
      for (var i = 0; i < r.rows.length; i++) {
        var o = r.rows[i];
        h += '<tr><td><a href="' + esc(o.url) + '" target="_blank" rel="noopener"><b>' + esc(o.tytul) + '</b></a><div class="mut" style="font-size:12px">' + esc(o.firma || o.konto) + ' · ' + esc(o.miasto) + (o.email ? ' · ' + esc(o.email) : '') + '</div></td><td>' + esc(ST[o.status] || o.status) + '</td><td class="mut" style="font-size:12.5px">' + esc(o.powod) + '</td></tr>';
      }
      window.openModal('Ogłoszenia o pracę – ostatnie 200', '<div style="max-height:60vh;overflow:auto">' + h + '</tbody></table></div>', '<a class="btn" onclick="window.closeModal()">Zamknij</a>', true);
    });
  },
  audyty: function () {
    api('ag_audyty', {}, function (r) {
      var ST = { czeka: 'Czeka na Ciebie', dodano: 'W mailingu', ok_strona: 'Strona OK', blad: 'Nie działa', odrzucona: 'Odrzucona', juz_jest: 'Już w Firmach' };
      var h = '<table class="tbl"><thead><tr><th>Strona</th><th>Wynik</th><th>Co znalazła</th></tr></thead><tbody>';
      if (!r.rows.length) h += '<tr><td colspan="3" class="mut">Jeszcze nic</td></tr>';
      for (var i = 0; i < r.rows.length; i++) {
        var o = r.rows[i];
        h += '<tr><td><b>' + esc(o.nazwa) + '</b><div class="mut" style="font-size:12px">' + esc(host(o.www)) + ' · ' + esc(o.miasto) + '</div></td><td>' + esc(ST[o.status] || o.status) + (o.psi !== null ? '<div class="mut" style="font-size:12px">Google ' + o.psi + '/100</div>' : '') + (o.sekundy ? '<div class="mut" style="font-size:12px">' + String(o.sekundy).replace('.', ',') + ' s</div>' : '') + '</td><td class="mut" style="font-size:12.5px;white-space:pre-line">' + esc(o.problem_txt) + '</td></tr>';
      }
      window.openModal('Sprawdzone strony – ostatnie 200', '<div style="max-height:60vh;overflow:auto">' + h + '</tbody></table></div>', '<a class="btn" onclick="window.closeModal()">Zamknij</a>', true);
    });
  }
};
})();
