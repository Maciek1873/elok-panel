/* ELOK – pozyskiwanie klientów. Vanilla JS, bez backticków / arrow functions / optional chaining (Safari). */
(function () {
'use strict';

var BR = [];          // branże
var VIEW = '';
var F = { q: '', branza: 0, status: '', miasto: '', offset: 0, rows: [], total: 0, sel: {}, counts: {}, miasta: [] };
var ED = { branza: 0, typ: 'pierwszy', data: {}, timer: null };

var ST = {
  nowy: 'Nowy', wyslano: 'Wysłano', otworzyl: 'Otworzył', przypomnienie: 'Przypomnienie',
  zainteresowany: 'Zainteresowany', nie: 'Nie', wypisany: 'Wypisany', zwrot: 'Zwrot (zły adres)', blad: 'Błąd'
};
var ZS = { nowe: 'Nowe', zadzwonione: 'Zadzwoniłem', umowione: 'Konsultacja umówiona', klient: 'Klient', odrzucone: 'Nie / odrzucone' };
var LG = {
  wyslano: 'Wysłano 1. mail', przypomnienie: 'Wysłano przypomnienie', przypomnienie2: 'Wysłano ostatnie przypomnienie', otwarcie: 'Otworzył maila', klik: 'Kliknął „Tak”',
  zwrot: 'Zwrot – adres nie istnieje', odpowiedz: 'Odpisał mailem', autoodp: 'Autoodpowiedź (np. urlop)',
  zgloszenie: 'Wysłał zgłoszenie', wypis: 'Wypisał się', blad: 'Błąd wysyłki', test: 'Mail testowy'
};
var TYPY = { pierwszy: 'Pierwszy mail', przypomnienie: 'Przypomnienie', przypomnienie2: 'Ostatnie przypomnienie', potwierdzenie: 'Potwierdzenie' };

var IC = {
  pulpit: '<path d="M3 13h8V3H3zM13 21h8V11h-8zM3 21h8v-6H3zM13 3v6h8V3z"/>',
  firmy: '<path d="M3 21h18M5 21V7l7-4 7 4v14M9 21v-6h6v6"/>',
  szukaj: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>',
  arkusz: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="M3 10h18M3 15h18M9 4v16"/>',
  maile: '<path d="M4 5h16v14H4z"/><path d="m4 7 8 6 8-6"/>',
  zgloszenia: '<path d="M18 8a6 6 0 1 0-12 0c0 7-3 9-3 9h18s-3-2-3-9M13.7 21a2 2 0 0 1-3.4 0"/>',
  publikacje: '<rect x="3" y="4" width="18" height="16" rx="2"/><path d="m10 9 5 3-5 3z"/>',
  aktualizacje: '<path d="M21 12a9 9 0 1 1-3-6.7L21 8"/><path d="M21 3v5h-5"/>',
  www_stat: '<path d="M4 20V10M10 20V4M16 20v-7M22 20H2"/>',
  www_edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
  www_kons: '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
  www_podl: '<path d="M9 7H7a5 5 0 0 0 0 10h2M15 7h2a5 5 0 0 1 0 10h-2M8 12h8"/>',
  saas: '<rect x="3" y="4" width="18" height="14" rx="2"/><path d="M8 21h8M12 18v3"/>',
  ustawienia: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.6 1.6 0 0 0 .3 1.8l.1.1a2 2 0 1 1-2.8 2.8l-.1-.1a1.6 1.6 0 0 0-1.8-.3 1.6 1.6 0 0 0-1 1.5V21a2 2 0 1 1-4 0v-.1a1.6 1.6 0 0 0-1-1.5 1.6 1.6 0 0 0-1.8.3l-.1.1a2 2 0 1 1-2.8-2.8l.1-.1a1.6 1.6 0 0 0 .3-1.8 1.6 1.6 0 0 0-1.5-1H3a2 2 0 1 1 0-4h.1a1.6 1.6 0 0 0 1.5-1 1.6 1.6 0 0 0-.3-1.8l-.1-.1a2 2 0 1 1 2.8-2.8l.1.1a1.6 1.6 0 0 0 1.8.3H9a1.6 1.6 0 0 0 1-1.5V3a2 2 0 1 1 4 0v.1a1.6 1.6 0 0 0 1 1.5 1.6 1.6 0 0 0 1.8-.3l.1-.1a2 2 0 1 1 2.8 2.8l-.1.1a1.6 1.6 0 0 0-.3 1.8V9a1.6 1.6 0 0 0 1.5 1H21a2 2 0 1 1 0 4h-.1a1.6 1.6 0 0 0-1.5 1z"/>'
};
var NAV = [['pulpit', 'Pulpit'], ['szukaj', 'Szukaj firm'], ['arkusz', 'Arkusz'], ['firmy', 'Firmy'], ['maile', 'Maile'], ['zgloszenia', 'Zgłoszenia'], ['publikacje', 'Publikacje'], ['www_stat', 'Statystyki strony'], ['www_edit', 'Edytor strony'], ['www_kons', 'Konsultacje'], ['www_podl', 'Podłączenia'], ['aktualizacje', 'Aktualizacje'], ['ustawienia', 'Ustawienia']];

// ---------- helpers ----------
function $(id) { return document.getElementById(id); }
function esc(s) {
  return String(s === null || s === undefined ? '' : s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;').replace(/"/g, '&quot;').replace(/'/g, '&#39;');
}
function val(id) { var e = $(id); return e ? e.value : ''; }
function fd(s) {
  if (!s) return '—';
  var p = String(s).split(/[- :]/);
  return p[2] + '.' + p[1] + ' ' + p[3] + ':' + p[4];
}
function fdd(s) { if (!s) return '—'; var p = String(s).split(/[- :]/); return p[2] + '.' + p[1] + '.' + p[0]; }
function pill(st) { return '<span class="pill st-' + esc(st) + '">' + esc(ST[st] || st) + '</span>'; }
function icon(k) { return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' + IC[k] + '</svg>'; }
function pct(a, b) { return b ? Math.round(a * 100 / b) : 0; }

var toastT = null;
function toast(msg, type) {
  var t = $('toast');
  t.textContent = msg;
  t.className = 'on' + (type === 'err' ? ' err' : '');
  clearTimeout(toastT);
  toastT = setTimeout(function () { t.className = ''; }, 3200);
}
window.toast = toast;

function api(action, data, cb, errCb) {
  var x = new XMLHttpRequest();
  x.open('POST', 'api.php?a=' + encodeURIComponent(action));
  x.setRequestHeader('Content-Type', 'application/json');
  x.setRequestHeader('X-CSRF', window.CSRF);
  x.onload = function () {
    var r = null;
    try { r = JSON.parse(x.responseText); } catch (e) { toast('Błąd serwera', 'err'); if (errCb) errCb(); return; }
    if (!r.ok) {
      if (r.login && action !== 'login') { location.reload(); return; }
      toast(r.error || 'Błąd', 'err'); if (errCb) errCb(r); return;
    }
    if (cb) cb(r);
  };
  x.onerror = function () { toast('Brak połączenia', 'err'); if (errCb) errCb(); };
  x.send(JSON.stringify(data || {}));
}

function busy(btn, on) { if (!btn) return; if (on) { btn.setAttribute('disabled', '1'); btn.dataset.t = btn.innerHTML; btn.innerHTML = 'Chwila…'; } else { btn.removeAttribute('disabled'); if (btn.dataset.t) btn.innerHTML = btn.dataset.t; } }

// ---------- login ----------
window.doLogin = function (e) {
  e.preventDefault();
  var er = $('l-err'); er.style.display = 'none';
  api('login', { login: val('l-user'), pass: val('l-pass') }, function () { location.reload(); }, function (r) {
    er.textContent = (r && r.error) ? r.error : 'Błąd'; er.style.display = 'block';
  });
  return false;
};
window.logout = function () { api('logout', {}, function () { location.reload(); }); };

// ---------- modal ----------
window.openModal = function (title, body, foot, wide) {
  $('mod').style.maxWidth = wide ? '820px' : '620px';
  $('mod').innerHTML = '<div class="mh"><h3>' + esc(title) + '</h3><button class="x" onclick="window.closeModal()">×</button></div><div class="mb">' + body + '</div>' + (foot ? '<div class="mf">' + foot + '</div>' : '');
  $('ov').className = 'ov on';
};
window.closeModal = function () { $('ov').className = 'ov'; };
// Ładne okno potwierdzenia zamiast systemowego confirm()
var ASK = {};
window.ask = function (title, text, okLabel, onOk, opts) {
  opts = opts || {};
  ASK.ok = onOk; ASK.alt = opts.onAlt || null;
  var foot = '<a class="btn" onclick="window.closeModal()">' + (opts.cancel || 'Anuluj') + '</a>' +
    (opts.alt ? '<a class="btn" onclick="window.askAlt()">' + esc(opts.alt) + '</a>' : '') +
    '<a class="btn ' + (opts.danger ? 'redfill' : 'dark') + '" id="ask-ok" onclick="window.askOk()">' + esc(okLabel || 'OK') + '</a>';
  window.openModal(title, '<p style="margin:14px 0 4px;color:var(--ink2);line-height:1.6">' + text + '</p>' + (opts.input !== undefined ? '<input id="ask-in" value="' + esc(opts.input) + '" placeholder="' + esc(opts.ph || '') + '" style="margin-top:10px" onkeydown="if(event.key===\'Enter\')window.askOk()">' : ''), foot);
  $('mod').style.maxWidth = '460px';
  setTimeout(function () { var i = $('ask-in'); if (i) i.focus(); else if ($('ask-ok')) $('ask-ok').focus(); }, 50);
};
window.askOk = function () { var v = $('ask-in') ? $('ask-in').value : null; window.closeModal(); if (ASK.ok) ASK.ok(v); };
window.askAlt = function () { window.closeModal(); if (ASK.alt) ASK.alt(); };
var SURE_OK = false;
function sure(title, text, okLabel, again, danger) {
  if (SURE_OK) { SURE_OK = false; return true; }
  window.ask(title, text, okLabel, function () { SURE_OK = true; again(); }, { danger: danger });
  return false;
}
document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && $('ov')) window.closeModal(); });

// ---------- nawigacja ----------
function renderNav(badge) {
  var h = '';
  for (var i = 0; i < NAV.length; i++) {
    var k = NAV[i][0];
    h += '<a class="' + (VIEW === k ? 'on' : '') + '" onclick="window.go(\'' + k + '\')">' + icon(k) + '<span>' + NAV[i][1] + '</span>' +
      (k === 'zgloszenia' && badge ? '<span class="bdg">' + badge + '</span>' : '') + '</a>';
  }
  h += '<a class="ext" href="saas/">' + icon('saas') + '<span>Panel SaaS</span></a>';
  $('nav').innerHTML = h;
}
var BADGE = 0;
window.go = function (v) {
  if (VIEW === 'www_edit' && v !== 'www_edit' && WE.dirty) {
    window.ask('Niezapisane zmiany', 'Na stronie są zmienione teksty, które nie zostały zapisane. Wyjść bez zapisu?', 'Wyjdź bez zapisu', function () { WE.dirty = false; window.go(v); });
    if (location.hash !== '#www_edit') history.replaceState(null, '', '#www_edit');
    return;
  }
  VIEW = v;
  if (location.hash !== '#' + v) history.replaceState(null, '', '#' + v);
  renderNav(BADGE);
  window.scrollTo(0, 0);
  $('main').innerHTML = '<div class="empty">Ładowanie…</div>';
  if (v === 'pulpit') viewPulpit();
  else if (v === 'firmy') viewFirmy();
  else if (v === 'arkusz') viewArkusz();
  else if (v === 'szukaj') viewSzukaj();
  else if (v === 'maile') viewMaile();
  else if (v === 'zgloszenia') viewZgloszenia();
  else if (v === 'publikacje') viewPublikacje();
  else if (v === 'aktualizacje') viewAktualizacje();
  else if (v === 'www_stat') viewWwwStat();
  else if (v === 'www_edit') viewWwwEdit();
  else if (v === 'www_kons') viewWwwKons();
  else if (v === 'www_podl') viewWwwPodl();
  else if (v === 'ustawienia') viewUstawienia();
};
function loadBranze(cb) { api('branze', {}, function (r) { BR = r.rows; if (cb) cb(); }); }
function brOptions(sel, withAll) {
  var h = withAll ? '<option value="0">Wszystkie branże</option>' : '';
  for (var i = 0; i < BR.length; i++) h += '<option value="' + BR[i].id + '"' + (String(sel) === String(BR[i].id) ? ' selected' : '') + '>' + esc(BR[i].nazwa) + '</option>';
  return h;
}
function stOptions(sel, withAll) {
  var h = withAll ? '<option value="">Wszystkie statusy</option>' : '';
  for (var k in ST) if (ST.hasOwnProperty(k)) h += '<option value="' + k + '"' + (sel === k ? ' selected' : '') + '>' + ST[k] + '</option>';
  return h;
}

// ========== PULPIT ==========
var Q = { branza: 0, miasto: '', max: 0 };
function viewPulpit() {
  loadBranze(function () {
  api('dashboard', {}, function (d) {
    BADGE = d.zgl_nowe; renderNav(BADGE);
    var s = d.statusy, wys = 0, otw = 0, zai = 0, kl = 0;
    for (var i = 0; i < d.branze.length; i++) { wys += +d.branze[i].wyslane || 0; otw += +d.branze[i].otwarte || 0; zai += +d.branze[i].zaint || 0; kl += +d.branze[i].klik || 0; }
    var h = '<div class="head"><div><h1>Pozyskiwanie klientów</h1><p>Maile do firm idą po kolei z Twojej skrzynki, z dziennym limitem, żeby nie trafić do spamu. Odpowiedzi przychodzą na <b>' + esc(d.odpowiedzi) + '</b>.</p></div></div>';

    h += '<div class="grid g5">' +
      stat('Do wysłania', s.nowy || 0, 'czeka na 1. mail') +
      stat('Wysłane', wys, 'przypomnienia w kolejce: ' + d.przypomnienia_czekaja) +
      stat('Otworzyli', otw, pct(otw, wys) + '% wysłanych') +
      stat('Kliknęli / zainteresowani', '<span style="color:var(--ok)">' + zai + '</span>', 'kliknięć: ' + kl + ' · nowe zgłoszenia: ' + d.zgl_nowe) +
      stat('Dziś z limitu', d.dzis + '<small> / ' + d.limit + '</small>', d.dzien_roboczy ? 'dzień roboczy' : 'dziś dzień wolny', pct(d.dzis, d.limit)) + '</div>';

    h += '<div class="grid g21 sec">';
    h += '<div class="card pad"><h2 class="ct">1. Wyślij</h2>' +
      '<div class="g2f"><div><label class="f">Branża</label><select id="q-b" onchange="window.qChange()">' + brOptions(Q.branza, true) + '</select></div>' +
      '<div><label class="f">Do kogo</label><select id="q-m" onchange="window.qChange(1)"><option>Ładowanie…</option></select></div></div>' +
      '<a class="btn gold big" id="q-btn" onclick="window.sendNow(this)">Wyślij teraz</a>' +
      '<div class="info" id="q-info"></div>' +
      '<div class="autorow"><span class="dot' + (d.auto ? ' on' : '') + '"></span><div style="flex:1"><b>Automat ' + (d.auto ? 'włączony' : 'wyłączony') + '</b> <span class="mut">– pn–pt rano, bez świąt · ostatnio: ' + fd(d.ostatni_cron) + '</span></div>' +
      '<label class="sw"><input type="checkbox" ' + (d.auto ? 'checked' : '') + ' onchange="window.toggleAuto(this.checked)"><i></i></label></div>' +
      '<div class="note">Ktoś odpisał „tak” zwykłym mailem? W <a onclick="window.go(\'firmy\')">Firmach</a> kliknij przy nim <b>Zainteresowany</b> – trafi do Zgłoszeń.</div></div>';

    h += '<div class="card pad"><h2 class="ct">2. Dodaj firmy</h2>' +
      '<p class="mut" style="margin:6px 0 10px">Wklej listę – np. skopiowaną z dokumentu <b>„1. Firma - email - telefon”</b>, z Excela albo <b>Firma; email; telefon; miasto</b>. Linia z samą nazwą miasta (np. „Kraków”) ustawia miasto dla firm pod nią, a linia z nazwą branży – branżę. Duplikaty są pomijane.</p>' +
      '<textarea id="add-t" rows="7" placeholder="Hurtownie części&#10;Warszawa&#10;1. Auto Serwis Kowalski - biuro@kowalski-serwis.pl&#10;2. BMW Service Nowak - kontakt@nowak.pl - 600 100 200"></textarea>' +
      '<div class="row" style="margin-top:10px"><select id="add-b" class="grow">' + brOptions(Q.branza || (BR[0] ? BR[0].id : 0), false) + '</select><a class="btn dark" onclick="window.quickAdd(this)">Dodaj do listy</a></div>' +
      '<div class="hint">Branża domyślna – gdy w liście nie ma nagłówka z branżą.</div></div>';
    h += '</div>';

    h += '<div class="sec"><h2>Etapy</h2><div class="chips">';
    for (var k in ST) if (ST.hasOwnProperty(k)) h += '<span class="chip" onclick="window.firmyStatus(\'' + k + '\')">' + pill(k) + '<b>' + (s[k] || 0) + '</b></span>';
    h += '</div></div>';

    h += '<div class="grid g2 sec"><div class="card"><div class="pad" style="padding-bottom:6px"><h2 style="font-size:16px">Branże</h2></div><table class="tbl"><thead><tr><th>Branża</th><th class="num">Firmy</th><th class="num">Wysł.</th><th class="num">Otw.</th><th class="num">Klik.</th><th class="num">Zaint.</th></tr></thead><tbody>';
    for (i = 0; i < d.branze.length; i++) {
      var b = d.branze[i];
      h += '<tr class="click" onclick="window.firmyBranza(' + b.id + ')"><td><span class="b1">' + esc(b.nazwa) + '</span>' + (+b.aktywna ? '' : ' <span class="mut">(wstrzymana)</span>') + '</td><td class="num">' + (+b.firmy) + '</td><td class="num">' + (+b.wyslane || 0) + '</td><td class="num">' + pct(+b.otwarte, +b.wyslane) + '%</td><td class="num">' + (+b.klik || 0) + '</td><td class="num"><b>' + (+b.zaint || 0) + '</b></td></tr>';
    }
    h += '</tbody></table></div>';

    h += '<div class="card pad"><h2 style="font-size:16px;margin-bottom:6px">Ostatnia aktywność</h2><div class="feed">';
    if (!d.log.length) h += '<div class="empty" style="display:block">Jeszcze nic się nie wydarzyło</div>';
    for (i = 0; i < d.log.length; i++) {
      var l = d.log[i];
      h += '<div><span class="t">' + fd(l.created_at) + '</span><span>' + (l.fid ? '<a style="cursor:pointer;font-weight:600" onclick="window.firmaModal(' + l.fid + ')">' + esc(l.nazwa || 'Firma') + '</a> · ' : '') + esc(LG[l.typ] || l.typ) + (l.typ === 'zgloszenie' || l.typ === 'test' || l.typ === 'blad' ? ' <span class="mut">' + esc(l.info) + '</span>' : '') + '</span></div>';
    }
    h += '</div></div></div>';
    $('main').innerHTML = h;
    loadQueue();
  });
  });
}
function stat(l, n, s, bar) {
  return '<div class="card pad stat"><div class="l">' + l + '</div><div class="n">' + n + '</div><div class="s">' + s + '</div>' + (bar !== undefined ? '<div class="bar"><i style="width:' + Math.min(100, bar) + '%"></i></div>' : '') + '</div>';
}
function loadQueue() {
  api('kolejka', { branza: Q.branza }, function (r) {
    var tot = 0, i, o = '';
    for (i = 0; i < r.miasta.length; i++) tot += (+r.miasta[i].nowe || 0) + (+r.miasta[i].przyp || 0);
    o += '<option value="">Wszystkie miasta (' + tot + ' do wysłania)</option>';
    var found = false;
    for (i = 0; i < r.miasta.length; i++) {
      var m = r.miasta[i], n = (+m.nowe || 0), p = (+m.przyp || 0);
      if (!n && !p) continue;
      var nm = m.miasto || '__brak';
      if (nm === Q.miasto) found = true;
      o += '<option value="' + esc(nm) + '"' + (nm === Q.miasto ? ' selected' : '') + '>' + esc(m.miasto || '(bez miasta)') + ' – nowe: ' + n + (p ? ', przypomnienia: ' + p : '') + '</option>';
      if (nm === Q.miasto) tot = n + p;
    }
    if (!found) Q.miasto = '';
    if ($('q-m')) $('q-m').innerHTML = o;
    Q.max = Math.max(0, Math.min(r.left, 30, tot));
    var btn = $('q-btn');
    if (btn) { btn.innerHTML = 'Wyślij teraz (max ' + Q.max + ')'; if (Q.max) btn.removeAttribute('disabled'); else btn.setAttribute('disabled', '1'); }
    if ($('q-info')) $('q-info').innerHTML = 'Limit dzienny: <b>' + r.limit + '</b> (zostało dziś: <b>' + r.left + '</b>) · przypomnienie: <b>po ' + r.dni + ' dniach (raz)</b> · jednym kliknięciem max 30';
  });
}
window.qChange = function (onlyCity) {
  if (!onlyCity) { Q.branza = +val('q-b'); Q.miasto = ''; if ($('add-b') && Q.branza) $('add-b').value = Q.branza; }
  else Q.miasto = val('q-m');
  loadQueue();
};
window.quickAdd = function (btn) {
  if (!val('add-t').trim()) { toast('Wklej listę firm', 'err'); return; }
  busy(btn, true);
  api('import', { text: val('add-t'), branza_id: +val('add-b') }, function (r) {
    busy(btn, false);
    toast('Dodano: ' + r.dodano + ' · duplikaty: ' + r.duplikaty + ' · pominięte: ' + r.bledne);
    if (r.dodano) viewPulpit();
  }, function () { busy(btn, false); });
};
window.toggleAuto = function (on) {
  api('auto', { on: on ? 1 : 0 }, function () { toast(on ? 'Automat włączony' : 'Automat wyłączony'); if (VIEW === 'pulpit') viewPulpit(); });
};
window.sendNow = function (btn) {
  if (!Q.max) { toast('Nie ma do kogo wysłać albo limit na dziś wykorzystany', 'err'); return; }
  if (!sure('Wysłać teraz?', 'Panel wyśle maile do <b>' + Q.max + ' firm</b> – najpierw przypomnienia, potem nowe.', 'Wyślij', function () { window.sendNow(btn); })) return;
  busy(btn, true);
  api('wyslij_teraz', { branza: Q.branza, miasto: Q.miasto, max: Q.max }, function (r) {
    busy(btn, false);
    var x = r.r;
    toast(x.info ? x.info : ('Wysłano: ' + x.wyslano + ' · przypomnienia: ' + x.przypomnienia + (x.bledy ? ' · błędy: ' + x.bledy : '')), x.bledy ? 'err' : '');
    if (VIEW === 'pulpit') viewPulpit(); else if (VIEW === 'firmy') loadFirmy();
  }, function () { busy(btn, false); });
};
window.firmyStatus = function (st) { F.status = st; F.branza = 0; F.q = ''; F.miasto = ''; window.go('firmy'); };
window.firmyBranza = function (id) { F.branza = id; F.status = ''; F.q = ''; F.miasto = ''; window.go('firmy'); };

// ========== FIRMY ==========
function viewFirmy() {
  loadBranze(function () {
    var h = '<div class="head"><div><h1>Firmy</h1><p id="f-count">&nbsp;</p></div><div class="row">' +
      '<a class="btn" onclick="window.exportCsv()">Eksport CSV</a><a class="btn" onclick="window.importModal()">Importuj</a><a class="btn dark" onclick="window.firmaModal(0)">+ Dodaj firmę</a></div></div>' +
      '<div class="card pad" style="margin-bottom:14px"><div class="row">' +
      '<input class="grow" id="f-q" placeholder="Szukaj: nazwa, e-mail, miasto, telefon" value="' + esc(F.q) + '" oninput="window.fSearch()">' +
      '<select id="f-b" style="width:auto;min-width:200px" onchange="window.fFilter()">' + brOptions(F.branza, true) + '</select>' +
      '<select id="f-m" style="width:auto;min-width:170px" onchange="window.fFilter()"><option value="">Wszystkie miasta</option></select></div>' +
      '<div class="chips" id="f-chips" style="margin-top:12px"></div></div>' +
      '<div class="bulk" id="bulk"><b id="bulk-n"></b><span style="flex:1"></span>' +
      '<select id="bk-b"><option value="0">Przenieś do branży…</option>' + brOptions(0, false) + '</select>' +
      '<select id="bk-s"><option value="">Ustaw status…</option>' + stOptions('', false) + '</select>' +
      '<a class="btn sm gold" onclick="window.bulkApply()">Zastosuj</a><a class="btn sm red" onclick="window.bulkDelete()">Usuń</a><a class="btn sm" onclick="window.bulkClear()">×</a></div>' +
      '<div class="card" id="f-list"></div>';
    $('main').innerHTML = h;
    F.offset = 0; F.sel = {};
    loadFirmy();
  });
}
var fT = null;
window.fSearch = function () { clearTimeout(fT); fT = setTimeout(function () { F.q = val('f-q'); F.offset = 0; loadFirmy(); }, 300); };
window.fFilter = function () { var nb = +val('f-b'); if (nb !== F.branza) F.miasto = ''; else F.miasto = val('f-m'); F.branza = nb; F.offset = 0; loadFirmy(); };
window.fStatus = function (st) { F.status = st; F.offset = 0; loadFirmy(); };
function loadFirmy(more) {
  api('firmy', { q: F.q, branza: F.branza, status: F.status, miasto: F.miasto || '', offset: more ? F.offset : 0 }, function (r) {
    F.rows = more ? F.rows.concat(r.rows) : r.rows;
    F.total = r.total; F.offset = r.offset + r.rows.length; F.counts = r.counts; F.miasta = r.miasta;
    renderFirmy();
  });
}
var ST_CHIP = { nowy: 'Do wysłania', wyslano: 'Wysłano', otworzyl: 'Otworzył', przypomnienie: 'Przypomnienie', zainteresowany: 'Zainteresowany', nie: 'Nie', wypisany: 'Wypisany', zwrot: 'Zwrot', blad: 'Błąd' };
function renderFirmy() {
  var c = F.counts || {}, all = 0, k;
  for (k in c) if (c.hasOwnProperty(k) && k !== 'goracy') all += c[k];
  if ($('f-count')) $('f-count').textContent = F.total + ' ' + (F.total === 1 ? 'firma' : 'firm') + (F.q || F.branza || F.status || F.miasto ? ' (filtr)' : '');
  var ch = '<span class="chip' + (!F.status ? ' on' : '') + '" onclick="window.fStatus(\'\')">Wszystkie <b>' + all + '</b></span>';
  ch += '<span class="chip' + (F.status === 'goracy' ? ' on' : '') + '" style="border-color:#C5A059" title="Kliknęli, otworzyli 2+ razy albo odpisali – warto zadzwonić" onclick="window.fStatus(\'goracy\')">' + ic('phone', 14) + ' Do telefonu <b>' + (c.goracy || 0) + '</b></span>';
  for (k in ST_CHIP) if (ST_CHIP.hasOwnProperty(k) && (c[k] || F.status === k || k === 'nowy' || k === 'wyslano' || k === 'zainteresowany'))
    ch += '<span class="chip' + (F.status === k ? ' on' : '') + '" onclick="window.fStatus(\'' + k + '\')">' + ST_CHIP[k] + ' <b>' + (c[k] || 0) + '</b></span>';
  if ($('f-chips')) $('f-chips').innerHTML = ch;
  if ($('f-m')) {
    var o = '<option value="">Wszystkie miasta</option>';
    for (var j = 0; j < (F.miasta || []).length; j++) o += '<option value="' + esc(F.miasta[j].miasto) + '"' + (F.miasto === F.miasta[j].miasto ? ' selected' : '') + '>' + esc(F.miasta[j].miasto) + ' (' + F.miasta[j].n + ')</option>';
    $('f-m').innerHTML = o;
  }
  var el = $('f-list'); if (!el) return;
  if (!F.rows.length) {
    el.innerHTML = '<div class="empty">Brak firm' + (F.q || F.branza || F.status || F.miasto ? ' dla tego filtra' : '') + '.<br><br><a class="btn dark" onclick="window.importModal()">Importuj listę</a></div>';
    bulkUpd(); return;
  }
  var h = '<table class="tbl resp"><thead><tr><th style="width:36px"><input type="checkbox" onclick="window.selAll(this.checked)"></th><th>Firma</th><th>E-mail</th><th class="hm">Miasto · branża</th><th>Status</th><th class="hm">Wysłano / otwarto</th><th></th></tr></thead><tbody>';
  for (var i = 0; i < F.rows.length; i++) {
    var f = F.rows[i];
    var extra = '';
    if (f.otwarto_at && f.status !== 'otworzyl') extra += '<span class="pill st-otworzyl sm">otworzył' + (+f.otwarcia > 1 ? ' ' + f.otwarcia + '×' : '') + '</span>';
    else if (+f.otwarcia > 1) extra += '<span class="mut"> ' + f.otwarcia + '×</span>';
    if (f.klik_at && f.status !== 'zainteresowany') extra += '<span class="pill st-zainteresowany sm">kliknął</span>';
    if (f.przypomnienie_at && f.status !== 'przypomnienie') extra += '<span class="pill st-przypomnienie sm">przypomniano</span>';
    if (f.odpisal_at) extra += '<span class="pill st-zainteresowany sm">' + ic('chat', 12) + ' odpisał</span>';
    var act = '';
    if (f.status === 'zainteresowany') act = '<a class="btn sm" onclick="event.stopPropagation();window.go(\'zgloszenia\')">Zgłoszenie →</a>';
    else if (f.status !== 'wypisany' && f.status !== 'nie') act = '<a class="btn sm" title="Odpisał „tak” mailem" onclick="event.stopPropagation();window.markInt(' + f.id + ',this)">Zainteresowany</a>';
    h += '<tr class="click" onclick="window.firmaModal(' + f.id + ')">' +
      '<td class="c0" onclick="event.stopPropagation()"><input type="checkbox" ' + (F.sel[f.id] ? 'checked' : '') + ' onchange="window.selOne(' + f.id + ',this.checked)"></td>' +
      '<td><div class="b1">' + esc(f.nazwa || '(bez nazwy)') + '</div><div class="mut">' + (f.telefon ? '<a href="tel:' + esc(String(f.telefon).replace(/[^0-9+]/g, '')) + '" onclick="event.stopPropagation()" class="ilink">' + ic('phone', 14) + ' ' + esc(f.telefon) + '</a>' : '<span style="opacity:.6">+ telefon</span>') + '</div></td>' +
      '<td><div>' + esc(f.email) + '</div></td>' +
      '<td class="hm"><div>' + esc(f.miasto || '—') + '</div><div class="mut">' + esc(f.branza || '—') + '</div></td>' +
      '<td><div class="pills">' + pill(f.status) + extra + '</div></td>' +
      '<td class="hm"><div>' + fd(f.wyslano_at) + '</div><div class="mut">' + (f.otwarto_last ? 'otw. ' + fd(f.otwarto_last) : '') + '</div></td>' +
      '<td class="num" onclick="event.stopPropagation()">' + act + '</td></tr>';
  }
  h += '</tbody></table>';
  if (F.rows.length < F.total) h += '<div style="padding:14px;text-align:center"><a class="btn" onclick="window.moreFirmy()">Pokaż więcej (' + (F.total - F.rows.length) + ')</a></div>';
  el.innerHTML = h;
  bulkUpd();
}
window.markInt = function (id, btn) {
  busy(btn, true);
  api('firma_zainteresowany', { id: id }, function () { toast('Oznaczono – jest w Zgłoszeniach'); loadFirmy(); }, function () { busy(btn, false); });
};
window.moreFirmy = function () { loadFirmy(true); };
window.selOne = function (id, on) { if (on) F.sel[id] = 1; else delete F.sel[id]; bulkUpd(); };
window.selAll = function (on) { F.sel = {}; if (on) for (var i = 0; i < F.rows.length; i++) F.sel[F.rows[i].id] = 1; renderFirmy(); };
window.bulkClear = function () { F.sel = {}; renderFirmy(); };
function selIds() { var a = []; for (var k in F.sel) if (F.sel.hasOwnProperty(k)) a.push(+k); return a; }
function bulkUpd() { var n = selIds().length; var b = $('bulk'); if (!b) return; b.className = 'bulk' + (n ? ' on' : ''); $('bulk-n').textContent = 'Zaznaczono: ' + n; }
window.bulkApply = function () {
  var d = { ids: selIds(), branza_id: +val('bk-b'), status: val('bk-s') };
  if (!d.branza_id && !d.status) { toast('Wybierz branżę lub status', 'err'); return; }
  api('firmy_zmien', d, function (r) { toast('Zmieniono: ' + r.n); F.sel = {}; loadFirmy(); });
};
window.bulkDelete = function () {
  var ids = selIds();
  if (!sure('Usunąć firmy?', 'Usuniesz <b>' + ids.length + '</b> firm z listy. Tego nie da się cofnąć.', 'Usuń', function () { window.bulkDelete(); }, true)) return;
  api('firmy_usun', { ids: ids }, function (r) { toast('Usunięto: ' + r.n); F.sel = {}; loadFirmy(); });
};

window.firmaModal = function (id) {
  function show(f) {
    var b = '<div class="g2f">' +
      '<div><label class="f">Nazwa firmy</label><input id="m-nazwa" value="' + esc(f.nazwa) + '"></div>' +
      '<div><label class="f">E-mail *</label><input id="m-email" type="email" value="' + esc(f.email) + '"></div>' +
      '<div><label class="f">Telefon</label><input id="m-telefon" value="' + esc(f.telefon) + '"></div>' +
      '<div><label class="f">Miasto</label><input id="m-miasto" value="' + esc(f.miasto) + '"></div>' +
      '<div><label class="f">Osoba kontaktowa</label><input id="m-osoba" value="' + esc(f.osoba) + '"></div>' +
      '<div><label class="f">Strona www</label><input id="m-www" value="' + esc(f.www) + '"></div>' +
      '<div><label class="f">Branża</label><select id="m-branza">' + brOptions(f.branza_id || (F.branza || (BR[0] ? BR[0].id : 0)), false) + '</select></div>' +
      '<div><label class="f">Status</label><select id="m-status">' + stOptions(f.status || 'nowy', false) + '</select></div></div>' +
      '<label class="f">Notatka</label><textarea id="m-notatka" rows="3">' + esc(f.notatka) + '</textarea>';
    if (f.id) {
      if (f.telefon) b += '<div style="margin-top:12px"><a class="btn sm" href="tel:' + esc(String(f.telefon).replace(/[^0-9+]/g, '')) + '">' + ic('phone', 14) + ' Zadzwoń ' + esc(f.telefon) + '</a></div>';
      b += '<label class="f">Historia</label><div class="hist feed">';
      if (!f.log.length) b += '<div class="mut">Brak – firma czeka na pierwszy mail</div>';
      for (var i = 0; i < f.log.length; i++) b += '<div><span class="t">' + fd(f.log[i].created_at) + '</span><span>' + esc(LG[f.log[i].typ] || f.log[i].typ) + (f.log[i].typ === 'klik' || f.log[i].typ === 'zgloszenie' ? ' <span class="mut">' + esc(f.log[i].info) + '</span>' : '') + '</span></div>';
      b += '</div>';
    }
    var ft = (f.id ? '<a class="btn red" style="margin-right:auto" onclick="window.firmaDel(' + f.id + ')">Usuń</a>' +
      '<a class="btn" onclick="window.firmaSend(' + f.id + ',\'pierwszy\',this)">Wyślij 1. mail</a>' +
      '<a class="btn" onclick="window.firmaSend(' + f.id + ',\'przypomnienie\',this)">Wyślij przypomnienie</a>' : '') +
      '<a class="btn dark" onclick="window.firmaSave(' + (f.id || 0) + ',this)">Zapisz</a>';
    window.openModal(f.id ? (f.nazwa || f.email) : 'Nowa firma', b, ft);
  }
  function go() { if (!id) { show({ status: 'nowy' }); return; } api('firma', { id: id }, function (r) { show(r.firma); }); }
  if (!BR.length) loadBranze(go); else go();
};
window.firmaSave = function (id, btn) {
  var d = { id: id, nazwa: val('m-nazwa'), email: val('m-email'), telefon: val('m-telefon'), miasto: val('m-miasto'), osoba: val('m-osoba'), www: val('m-www'), branza_id: +val('m-branza'), status: val('m-status'), notatka: val('m-notatka') };
  busy(btn, true);
  api('firma_zapisz', d, function () { window.closeModal(); toast('Zapisano'); if (VIEW === 'firmy') loadFirmy(); else window.go(VIEW); }, function (r) {
    busy(btn, false);
    if (r && r.dup) window.ask('Możliwy duplikat', esc(r.error) + '<br><br>Dodać mimo to?', 'Dodaj mimo to', function () {
      d.wymus = 1; api('firma_zapisz', d, function () { toast('Zapisano'); if (VIEW === 'firmy') loadFirmy(); else window.go(VIEW); });
    });
  });
};
window.firmaDel = function (id) {
  if (!sure('Usunąć firmę?', 'Firma zniknie z listy razem z historią.', 'Usuń', function () { window.firmaDel(id); }, true)) return;
  api('firmy_usun', { ids: [id] }, function () { window.closeModal(); toast('Usunięto'); if (VIEW === 'firmy') loadFirmy(); });
};
window.firmaSend = function (id, typ, btn) {
  if (!sure('Wysłać teraz?', 'Wyślesz ' + (typ === 'pierwszy' ? 'pierwszy mail' : 'przypomnienie') + ' do tej firmy.', 'Wyślij', function () { window.firmaSend(id, typ, btn); })) return;
  busy(btn, true);
  api('firma_wyslij', { id: id, typ: typ }, function () { toast('Wysłano'); window.firmaModal(id); if (VIEW === 'firmy') loadFirmy(); }, function () { busy(btn, false); });
};

window.importModal = function () {
  function show() {
    var b = '<p class="mut" style="margin-top:12px">Wklej listę z Excela/Google Sheets albo wybierz plik CSV. Kolumny w kolejności:<br><b>nazwa ; email ; telefon ; miasto ; branża ; www</b> – albo pierwszy wiersz z nagłówkami (kolejność dowolna). Duplikaty e-maili są pomijane.</p>' +
      '<label class="f">Plik CSV (opcjonalnie)</label><input type="file" accept=".csv,.txt,text/csv" onchange="window.readCsv(this)" style="padding:9px 12px;height:auto">' +
      '<label class="f">Dane</label><textarea id="imp-t" rows="9" placeholder="Auto-Serwis Kowalski;biuro@autoserwis.pl;600100200;Warszawa;Warsztaty samochodowe"></textarea>' +
      '<label class="f">Branża domyślna (gdy w wierszu nie ma branży)</label><select id="imp-b">' + brOptions(F.branza || (BR[0] ? BR[0].id : 0), false) + '</select>' +
      '<p class="hint">Plik .xlsx? W Excelu: Plik → Zapisz jako → CSV UTF-8.</p>';
    window.openModal('Import firm', b, '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn dark" onclick="window.doImport(this)">Importuj</a>');
  }
  if (!BR.length) loadBranze(show); else show();
};
window.readCsv = function (inp) {
  var file = inp.files && inp.files[0]; if (!file) return;
  var r = new FileReader();
  r.onload = function () {
    var t = String(r.result);
    if (t.indexOf('�') >= 0) { // pewnie Windows-1250 z polskiego Excela
      var r2 = new FileReader();
      r2.onload = function () { $('imp-t').value = String(r2.result); };
      r2.readAsText(file, 'windows-1250');
      return;
    }
    $('imp-t').value = t.replace(/^﻿/, '');
  };
  r.readAsText(file, 'UTF-8');
};
window.doImport = function (btn) {
  busy(btn, true);
  api('import', { text: val('imp-t'), branza_id: +val('imp-b') }, function (r) {
    window.closeModal();
    toast('Dodano: ' + r.dodano + ' · duplikaty: ' + r.duplikaty + ' · błędne: ' + r.bledne);
    if (VIEW === 'firmy') loadFirmy();
  }, function () { busy(btn, false); });
};
window.exportCsv = function () {
  api('eksport', {}, function (r) {
    var blob = new Blob(['﻿' + r.csv], { type: 'text/csv;charset=utf-8' });
    var a = document.createElement('a');
    a.href = URL.createObjectURL(blob); a.download = 'firmy-elok.csv';
    document.body.appendChild(a); a.click(); document.body.removeChild(a);
  });
};

// ========== SZUKAJ FIRM (CEIDG / Google) ==========
var SZ = { zr: '', hasC: false, hasG: false, osmTags: {}, br: 0, filtr: 'mail', page: 0, token: '', sel: {}, rows: [], loop: false, pkd: {} };
var WOJ = ['', 'dolnośląskie', 'kujawsko-pomorskie', 'lubelskie', 'lubuskie', 'łódzkie', 'małopolskie', 'mazowieckie', 'opolskie', 'podkarpackie', 'podlaskie', 'pomorskie', 'śląskie', 'świętokrzyskie', 'warmińsko-mazurskie', 'wielkopolskie', 'zachodniopomorskie'];
function szPkd(brId) {
  var n = ''; for (var i = 0; i < BR.length; i++) if (+BR[i].id === +brId) n = String(BR[i].nazwa).toLowerCase();
  if (n.indexOf('stacj') >= 0 || n.indexOf('skp') >= 0) return '7120B';
  if (n.indexOf('warsztat') >= 0) return '4520Z';
  if (n.indexOf('hurtown') >= 0) return '4531Z';
  if (n.indexOf('częśc') >= 0 || n.indexOf('czesc') >= 0) return '4532Z';
  if (n.indexOf('sklep') >= 0 || n.indexOf('allegro') >= 0) return '4791Z';
  return '';
}
function szOsmDef(brId) {
  var n = ''; for (var i = 0; i < BR.length; i++) if (+BR[i].id === +brId) n = String(BR[i].nazwa).toLowerCase();
  if (n.indexOf('stacj') >= 0 || n.indexOf('skp') >= 0) return 'vehicle_inspection';
  if (n.indexOf('hurtown') >= 0 || n.indexOf('sklep') >= 0 || n.indexOf('allegro') >= 0) return 'car_parts';
  return 'car_repair';
}
function szFraza(brId) {
  var n = ''; for (var i = 0; i < BR.length; i++) if (+BR[i].id === +brId) n = String(BR[i].nazwa).toLowerCase();
  if (n.indexOf('stacj') >= 0 || n.indexOf('skp') >= 0) return 'stacja kontroli pojazdów';
  if (n.indexOf('warsztat') >= 0) return 'warsztat samochodowy';
  if (n.indexOf('hurtown') >= 0) return 'hurtownia części samochodowych';
  if (n.indexOf('sklep') >= 0) return 'sklep internetowy';
  return '';
}
function viewSzukaj() {
  loadBranze(function () {
    if (!SZ.br && BR.length) SZ.br = +BR[0].id;
    var h = '<div class="head"><div><h1>Szukaj firm</h1><p>Znajdź firmy automatycznie i jednym kliknięciem dodaj je do Arkusza albo prosto do Firm.</p></div></div>' +
      '<div class="tabs top" id="sz-tabs"></div>' +
      '<div class="card pad" id="sz-form"><div class="empty">Ładowanie…</div></div>' +
      '<div class="card pad szprog" id="sz-prog" style="display:none"></div>' +
      '<div class="sec"><div class="row" style="justify-content:space-between"><div class="chips" id="sz-chips"></div><div class="row" style="gap:8px"><a class="btn sm" onclick="window.szHideNo()">Usuń bez maila</a><a class="btn sm" onclick="window.szNoAso()">Usuń ASO z wyników</a><a class="btn sm red" onclick="window.szClearAll()">Wyczyść wyniki</a></div></div>' +
      '<div class="row szfl" style="gap:8px;margin-top:10px;flex-wrap:wrap"><select id="sz-fz" style="width:auto" onchange="window.szFl()"><option value="">Wszystkie źródła</option><option value="ceidg">CEIDG</option><option value="osm">OpenStreetMap</option><option value="google">Google</option><option value="uk">UK</option></select>' +
      '<select id="sz-fm" style="width:auto;max-width:240px" onchange="window.szFl()"><option value="">Wszystkie miasta</option></select>' +
      '<input id="sz-fq" placeholder="Szukaj: nazwa, e-mail…" style="flex:1;min-width:160px" oninput="window.szFlQ()"><a class="btn sm" onclick="window.szFlReset()">Wyczyść filtry</a></div>' +
      '<div class="bulk" id="sz-bulk"><b id="sz-n"></b><span style="flex:1"></span><span>do branży:</span><select id="sz-tb">' + brOptions(SZ.br, false) + '</select>' +
      '<a class="btn sm gold" onclick="window.szMove(\'arkusz\')">Do Arkusza</a><a class="btn sm" style="background:#fff;color:#111" onclick="window.szMove(\'firmy\')">Prosto do Firm</a><a class="btn sm red" onclick="window.szDel()">Usuń z listy</a></div>' +
      '<div class="card" id="sz-list" style="margin-top:10px"><div class="empty">Ładowanie…</div></div></div>';
    $('main').innerHTML = h;
    szLoad(false, true);
  });
}
function szTabs() {
  var t = [['ceidg', 'CEIDG – rejestr firm (e-mail właściciela)'], ['osm', 'OpenStreetMap (darmowe)'], ['uk', 'Wielka Brytania (UK)']];
  if (SZ.hasG) t.push(['google', 'Google Maps']);
  var h = ''; for (var i = 0; i < t.length; i++) h += '<a class="' + (SZ.zr === t[i][0] ? 'on' : '') + '" onclick="window.szZr(\'' + t[i][0] + '\')">' + t[i][1] + '</a>';
  if ($('sz-tabs')) $('sz-tabs').innerHTML = h;
}
window.szZr = function (z) {
  SZ.zr = z; SZ.page = 0; SZ.token = '';
  if (z === 'uk') { for (var i = 0; i < BR.length; i++) if (String(BR[i].nazwa).toLowerCase().indexOf('uk') === 0) SZ.br = +BR[i].id; }
  szTabs(); szForm(); if ($('sz-res')) $('sz-res').textContent = ''; szLoad(); };
function szForm() {
  var h = '<div class="szgrid"><div><label class="f" style="margin-top:0">Branża (do której trafią)</label><select id="sz-br" onchange="window.szBrCh()">' + brOptions(SZ.br, false) + '</select></div>';
  if (SZ.zr === 'ceidg') {
    var wo = ''; for (var i = 0; i < WOJ.length; i++) wo += '<option value="' + WOJ[i] + '">' + (WOJ[i] || 'całe województwo – dowolne') + '</option>';
    h += '<div><label class="f" style="margin-top:0">Kod PKD</label><input id="sz-pkd" value="' + esc(SZ.pkd[SZ.br] || szPkd(SZ.br)) + '" placeholder="np. 4520Z"></div>' +
      '<div><label class="f" style="margin-top:0">Miasto</label><input id="sz-m" placeholder="np. Radom (puste = całe województwo)"></div>' +
      '<div><label class="f" style="margin-top:0">Województwo</label><select id="sz-w">' + wo + '</select></div></div>' +
      '<div class="row" style="margin-top:14px"><a class="btn dark" id="sz-go" onclick="window.szGo(this,0)">Szukaj w CEIDG</a><a class="btn" id="sz-more" style="display:none" onclick="SZ.autoStop=false;window.szGo(this,1)">Pobierz dalej</a><span class="mut" id="sz-res"></span></div>' +
      '<p class="hint">Kody PKD: <b>4520Z</b> naprawa pojazdów (warsztaty) · <b>4531Z</b> hurt części · <b>4532Z</b> detal części · <b>4791Z</b> sprzedaż przez internet · <b>4540Z</b> motocykle · <b>7120B</b> stacje kontroli pojazdów (SKP). Kilka kodów rozdziel przecinkiem. ' +
      'CEIDG to jednoosobowe firmy – e-mail jest zwykle właściciela. Kontakty pobierają się po kolei (limit CEIDG ok. 15 firm/min), możesz w tym czasie robić co innego w panelu.</p>' +
      '<div id="sz-noc" class="nocbox"></div>';
  } else if (SZ.zr === 'uk') {
    var tu = ''; for (var tk2 in SZ.osmTags) if (SZ.osmTags.hasOwnProperty(tk2)) tu += '<option value="' + tk2 + '"' + (tk2 === 'car_repair' ? ' selected' : '') + '>' + esc(SZ.osmTags[tk2]) + '</option>';
    h += '<div><label class="f" style="margin-top:0">Rodzaj firm</label><select id="sz-tag">' + tu + '</select></div>' +
      '<div><label class="f" style="margin-top:0">Miasto w UK</label><input id="sz-m" list="uk-cities" placeholder="wybierz z listy albo wpisz"><datalist id="uk-cities">' + UKC.map(function (c) { return '<option value="' + c + '">'; }).join('') + '</datalist></div>' +
      '<div><label class="f" style="margin-top:0">Promień</label><select id="sz-km"><option value="10">10 km</option><option value="15" selected>15 km</option><option value="25">25 km</option><option value="40">40 km</option></select></div></div>' +
      '<div class="row" style="margin-top:12px;gap:18px"><label class="row" style="gap:8px;cursor:pointer"><input type="checkbox" id="sz-aso" checked> Pomiń dealerów marek (main dealer)</label>' +
      '<label class="row" style="gap:8px;cursor:pointer"><input type="checkbox" id="sz-siec" checked> Pomiń sieci (Kwik Fit, Halfords, ATS…)</label></div>' +
      '<div class="row" style="margin-top:14px"><a class="btn dark" id="sz-go" onclick="window.szGo(this,0)">Szukaj w tym mieście</a><a class="btn gold" id="uk-all" onclick="window.ukAll(this)">Szukaj we wszystkich ' + UKC.length + ' miastach</a><a class="btn red sm" id="uk-stop" style="display:none" onclick="window.ukStop()">Zatrzymaj</a><a class="btn" id="sz-more" style="display:none"></a><span class="mut" id="sz-res"></span></div>' +
      (SZ.hasCH ? '' : '<p class="hint" style="color:var(--err)"><b>Najpierw wpisz klucz Companies House w Ustawieniach</b> – bez niego panel nie sprawdzi, czy firma to spółka (Ltd), więc nie dopuści jej do wysyłki.</p>') +
      '<p class="hint"><b>„Szukaj we wszystkich miastach”</b> przechodzi po kolei przez ' + UKC.length + ' największych miast UK – zostaw kartę otwartą (ok. 15–30 min). Firmy, które już są, się nie dublują. Firmy z otwartej mapy (nazwa, strona, telefon). Maila panel szuka na stronie firmy, a potem sprawdza w brytyjskim rejestrze <b>Companies House</b>, czy to spółka (Ltd/PLC/LLP). W UK zimny mail B2B można wysłać tylko do spółek – jednoosobowe firmy (sole traders) panel sam pomija. Przypisuje też imię i nazwisko dyrektora.</p>';
  } else if (SZ.zr === 'osm') {
    var to = '', def = szOsmDef(SZ.br);
    for (var tk in SZ.osmTags) if (SZ.osmTags.hasOwnProperty(tk)) to += '<option value="' + tk + '"' + (tk === def ? ' selected' : '') + '>' + esc(SZ.osmTags[tk]) + '</option>';
    var wo2 = ''; for (var j = 0; j < WOJ.length; j++) wo2 += '<option value="' + WOJ[j] + '">' + (WOJ[j] || '— wybierz, jeśli bez miasta —') + '</option>';
    h += '<div><label class="f" style="margin-top:0">Rodzaj firm</label><select id="sz-tag">' + to + '</select></div>' +
      '<div><label class="f" style="margin-top:0">Miasto</label><div class="row" style="gap:6px;flex-wrap:nowrap"><input id="sz-m" placeholder="np. Warszawa" style="flex:1;min-width:0">' +
      '<select id="sz-km" style="width:auto"><option value="0">tylko miasto</option><option value="15">+ okolice 15 km</option><option value="25" selected>+ okolice 25 km</option><option value="40">+ okolice 40 km</option></select></div></div>' +
      '<div><label class="f" style="margin-top:0">albo całe województwo</label><select id="sz-w">' + wo2 + '</select></div></div>' +
      '<div class="row" style="margin-top:12px;gap:18px"><label class="row" style="gap:8px;cursor:pointer"><input type="checkbox" id="sz-aso" ' + (SZ.bezAso === false ? '' : 'checked') + ' onchange="SZ_set(\'bezAso\',this.checked)"> Pomiń ASO i salony marek</label>' +
      '<label class="row" style="gap:8px;cursor:pointer"><input type="checkbox" id="sz-siec" ' + (SZ.bezSieci ? 'checked' : '') + ' onchange="SZ_set(\'bezSieci\',this.checked)"> Pomiń sieci (Bosch Car Service, ProfiAuto, Euromaster…)</label></div>' +
      '<div class="row" style="margin-top:14px"><a class="btn dark" id="sz-go" onclick="window.szGo(this,0)">Szukaj na mapie</a><a class="btn" id="sz-more" style="display:none"></a><span class="mut" id="sz-res"></span></div>' +
      '<p class="hint">Darmowe, bez klucza i bez karty. Dane z otwartej mapy OpenStreetMap: nazwa, telefon, strona, czasem e-mail. Gdy maila nie ma, panel sam szuka go na stronie firmy. W mniejszych miastach firm może być mało – wtedy wybierz całe województwo.</p>';
  } else {
    h += '<div><label class="f" style="margin-top:0">Czego szukać</label><input id="sz-q" value="' + esc(szFraza(SZ.br)) + '" placeholder="np. warsztat samochodowy, mechanik BMW"></div>' +
      '<div><label class="f" style="margin-top:0">Miasto</label><input id="sz-m" placeholder="np. Radom"></div><div></div></div>' +
      '<div class="row" style="margin-top:14px"><a class="btn dark" id="sz-go" onclick="window.szGo(this,0)">Szukaj w Google</a><a class="btn" id="sz-more" style="display:none" onclick="window.szGo(this,1)">Kolejne 20</a><span class="mut" id="sz-res"></span></div>' +
      '<p class="hint">Google daje nazwę, telefon i stronę. E-mail panel sam szuka na stronie firmy (strona główna i „Kontakt”). Nie każda firma ma maila na stronie. Klucz Google wpisujesz w Ustawieniach.</p>';
  }
  $('sz-form').innerHTML = h;
  if (SZ.zr === 'ceidg') nocLoad();
}
// ---- POBIERANIE W TLE (cron na serwerze – działa przy zamkniętym komputerze) ----
var NOC = { t: null, d: null, sel: null };
function nocLoad() {
  clearTimeout(NOC.t);
  api('noc_stan', {}, function (r) {
    NOC.d = r;
    if (!NOC.sel) { NOC.sel = {}; for (var i = 0; i < r.woj.length; i++) NOC.sel[r.woj[i]] = 1; }
    nocRender();
    if (VIEW === 'szukaj' && SZ.zr === 'ceidg') NOC.t = setTimeout(function () { if ($('sz-noc')) nocLoad(); }, 30000);
  });
}
function nocRender() {
  var el = $('sz-noc'), r = NOC.d; if (!el || !r) return;
  var z = r.z || {}, c = r.licz || {}, h = '<div class="nochead"><b>' + ic('moon', 16) + ' Pobieranie w tle</b><span class="mut">działa na serwerze – możesz zamknąć komputer, rano masz listę</span></div>';
  if (z.on) {
    var w = r.woj || [], wi = Math.min(+z.wi || 0, w.length - 1);
    h += '<div class="nocst"><span class="spin" style="border-color:#C5A059;border-right-color:transparent"></span><div><b>Trwa pobieranie</b> · PKD ' + esc(z.pkd) + (z.godz === 'noc' ? ' · tylko 22:00–7:00' : '') + '<br>' +
      'Województwo: <b>' + esc(w[wi] || '') + '</b> (' + (wi + 1) + '/' + w.length + ')' + ((+z.seg >= 0 && z.seg !== undefined) ? ', część ' + (+z.seg + 1) + '/10' : '') + ', strona ' + ((+z.page || 0) + 1) + (z.razem_w ? ' z ok. ' + Math.ceil(z.razem_w / 25) : '') + '<br>' +
      'Nowych firm: <b>' + (c.wszystkie || 0) + '</b> · z mailem: <b style="color:var(--ok,#2E7D4F)">' + (c.gotowe || 0) + '</b> · czeka na kontakt: ' + (c.czeka || 0) + ' · bez maila: ' + (c.brak || 0) +
      '<br><span class="mut">Ostatni przebieg: ' + esc(r.ostatnio || 'jeszcze nie było') + (z.info ? ' · ' + esc(z.info) : '') + '</span>' +
      (z.info && z.info.indexOf('timed out') >= 0 ? '<br><span class="mut">CEIDG odpowiada wolno – panel sam ponawia co 5 min, a duże województwo dzieli na mniejsze części. Nic nie klikaj.</span>' : '') +
      (z.pominiete && z.pominiete.length ? '<br><span class="mut">Pominięte (CEIDG nie odpowiadał): ' + esc(z.pominiete.join(', ')) + '</span>' : '') + '</div></div>' +
      '<div class="row" style="margin-top:10px"><a class="btn sm red" onclick="window.nocStop(this)">Zatrzymaj</a><a class="btn sm" onclick="nocLoad()">Odśwież</a><a class="btn sm" onclick="window.ceidgTest(this)">Test CEIDG</a></div><div id="ceidg-test"></div>';
  } else {
    var all = r.woj_all || [], g = '';
    for (var i = 0; i < all.length; i++) g += '<label class="nocw"><input type="checkbox" ' + (NOC.sel[all[i]] ? 'checked' : '') + ' onchange="window.nocW(\'' + all[i] + '\',this.checked)"> ' + esc(all[i]) + '</label>';
    var canRes = z.pkd && z.koniec === undefined && (+z.wi > 0 || +z.page > 0);
    if (z.info) h += '<div class="mut" style="margin:6px 0">Ostatnio (PKD ' + esc(z.pkd || '') + '): ' + esc(z.info) + (c.wszystkie ? ' · pobrano ' + c.wszystkie + ' firm' : '') + '</div>';
    if (z.pominiete && z.pominiete.length) h += '<div class="nocwarn" style="margin:8px 0"><b>CEIDG nie odpowiedział dla ' + z.pominiete.length + ' części</b> – tych firm brakuje: <span class="mut">' + esc(z.pominiete.join(', ')) + '</span><br><a class="btn sm" style="margin-top:8px" onclick="window.nocStart(this,0,1)">Pobierz pominięte jeszcze raz</a></div>';
    h += '<div class="row" style="gap:10px;margin:8px 0"><a class="btn sm" onclick="window.nocAll(1)">Cała Polska</a><a class="btn sm" onclick="window.nocAll(0)">Odznacz wszystkie</a></div>' +
      '<div class="nocgrid">' + g + '</div>' +
      '<div class="row" style="gap:14px;margin-top:12px;flex-wrap:wrap"><select id="noc-g" style="width:auto"><option value="zawsze">Pobieraj od razu, aż skończy</option><option value="noc">Tylko w nocy (22:00–7:00)</option></select>' +
      '<label class="row" style="gap:8px;cursor:pointer"><input type="checkbox" id="noc-u" checked> Od razu usuwaj firmy bez maila</label></div>' +
      '<div class="row" style="margin-top:12px"><a class="btn gold" onclick="window.nocStart(this,0)">Włącz pobieranie w tle</a>' + (canRes ? '<a class="btn" onclick="window.nocStart(this,1)">Wznów poprzednie</a>' : '') +
      '<span class="mut">Użyje kodu PKD i branży z formularza wyżej.</span></div>';
  }
  if (!r.cron_ok) h += '<div class="nocwarn"><b>Ważne – raz ustaw cron w LH.pl:</b> Panel LH.pl → Zadania cron → Dodaj → co <b>5 minut</b>, polecenie:<br><code id="noc-cmd">wget -q -O - "' + esc(r.cron_url) + '" >/dev/null 2>&1</code> <a class="btn sm" onclick="window.nocCopy()">Kopiuj</a><br><span class="mut">Bez tego pobieranie ruszy tylko przy zwykłym cronie (co 15 min) – wolniej.</span></div>';
  el.innerHTML = h;
}
window.ceidgTest = function (btn) {
  busy(btn, true);
  if ($('ceidg-test')) $('ceidg-test').innerHTML = '<p class="mut" style="margin-top:10px"><span class="spin" style="border-color:#8A867C;border-right-color:transparent"></span>Testuję 8 zapytań do CEIDG – to potrwa do 3 minut, nie zamykaj karty…</p>';
  api('ceidg_test', {}, function (r) {
    busy(btn, false); clearTimeout(NOC.t);
    var h = '<table class="tbl" style="margin-top:10px;font-size:13px"><thead><tr><th>Zapytanie</th><th>Kod</th><th>Czas</th><th>Wynik</th></tr></thead><tbody>';
    for (var i = 0; i < r.wyniki.length; i++) { var x = r.wyniki[i]; h += '<tr><td>' + esc(x[0]) + '</td><td><b style="color:' + (x[1] == 200 ? '#2E7D4F' : '#B3261E') + '">' + (x[1] || '—') + '</b></td><td>' + x[2] + ' s</td><td>' + esc(x[3]) + '</td></tr>'; }
    $('ceidg-test').innerHTML = h + '</tbody></table><p class="mut">Zrób screena tej tabelki i wyślij.</p>';
  }, function () { busy(btn, false); });
};
window.nocW = function (w, on) { if (on) NOC.sel[w] = 1; else delete NOC.sel[w]; };
window.nocAll = function (on) { NOC.sel = {}; if (on) for (var i = 0; i < NOC.d.woj_all.length; i++) NOC.sel[NOC.d.woj_all[i]] = 1; nocRender(); };
window.nocCopy = function () { var t = $('noc-cmd') ? $('noc-cmd').textContent : ''; try { navigator.clipboard.writeText(t); toast('Skopiowano'); } catch (e) { toast('Zaznacz i skopiuj ręcznie', 'err'); } };
window.nocStart = function (btn, wznow, pomin) {
  var woj = []; var all = NOC.d.woj_all; for (var i = 0; i < all.length; i++) if (NOC.sel[all[i]]) woj.push(all[i]);
  if (!woj.length && !pomin) { toast('Zaznacz województwa', 'err'); return; }
  busy(btn, true);
  api('noc_start', { pkd: val('sz-pkd'), branza_id: +val('sz-br'), woj: woj, godz: val('noc-g'), auto_usun: $('noc-u').checked ? 1 : 0, wznow: wznow ? 1 : 0, pominiete: pomin ? 1 : 0 }, function () {
    toast('Pobieranie włączone – ruszy przy najbliższym cronie'); nocLoad();
  }, function () { busy(btn, false); });
};
window.nocStop = function (btn) {
  if (!sure('Zatrzymać pobieranie?', 'Pobrane firmy zostają na liście. Możesz potem wznowić od tego miejsca.', 'Zatrzymaj', function () { window.nocStop(btn); }, true)) return;
  api('noc_stop', {}, function () { toast('Zatrzymano'); nocLoad(); });
};
window.szBrCh = function () {
  if ($('sz-pkd')) SZ.pkd[SZ.br] = val('sz-pkd');
  SZ.br = +val('sz-br'); SZ.page = 0; SZ.token = '';
  if ($('sz-pkd')) $('sz-pkd').value = SZ.pkd[SZ.br] || szPkd(SZ.br);
  if ($('sz-q')) $('sz-q').value = szFraza(SZ.br);
  if ($('sz-tag')) $('sz-tag').value = szOsmDef(SZ.br);
  if ($('sz-tb')) $('sz-tb').value = SZ.br;
  szLoad();
};
window.szGo = function (btn, more) {
  var d = { zrodlo: SZ.zr, branza_id: SZ.br, miasto: val('sz-m').trim() };
  if (SZ.zr === 'uk') { d.zrodlo = 'osm'; d.kraj = 'gb'; d.tag = val('sz-tag'); d.woj = ''; d.km = +val('sz-km'); d.bez_aso = $('sz-aso').checked ? 1 : 0; d.bez_sieci = $('sz-siec').checked ? 1 : 0; if (!d.miasto) { toast('Wpisz miasto w UK', 'err'); return; } if (!SZ.hasCH) { toast('Najpierw wpisz klucz Companies House w Ustawieniach', 'err'); return; } }
  else if (SZ.zr === 'osm') { d.tag = val('sz-tag'); d.woj = val('sz-w'); d.km = +val('sz-km'); d.bez_aso = $('sz-aso').checked ? 1 : 0; d.bez_sieci = $('sz-siec').checked ? 1 : 0; if (!d.miasto && !d.woj) { toast('Wpisz miasto albo wybierz województwo', 'err'); return; } }
  else if (SZ.zr === 'ceidg') { d.pkd = val('sz-pkd'); d.woj = val('sz-w'); SZ.page = more ? SZ.page + 1 : 0; d.page = SZ.page; SZ.pkd[SZ.br] = d.pkd; if (!d.miasto && !d.woj && !sure('Cała Polska?', 'Nie wpisałeś miasta ani województwa – wyszukam w całej Polsce.', 'Szukaj', function () { window.szGo(btn, more); })) return; }
  else { d.fraza = val('sz-q'); d.token = more ? SZ.token : ''; }
  busy(btn, true);
  var spin = '<span class="spin" style="border-color:#8A867C;border-right-color:transparent"></span>';
  var infos = [], round = 0;
  var run = function () { api('sz_szukaj', d, function (r) {
    if (r.retry) {
      infos.push(r.info);
      if (r.srv < r.ile) {
        if ($('sz-res')) $('sz-res').innerHTML = spin + 'Serwer zajęty (' + esc(r.info) + ') – próbuję zapasowy ' + (r.srv + 1) + '/' + r.ile + '…';
        d.srv = r.srv; setTimeout(run, 800); return;
      }
      // wszystkie serwery zajęte – odczekaj i spróbuj ponownie (max 3 rundy)
      round++;
      if (round > 3) { busy(btn, false); if ($('sz-res')) $('sz-res').innerHTML = '<span style="color:var(--err)">Serwery OpenStreetMap nie odpowiadają. Spróbuj za 10–15 minut.</span> <span class="mut">(' + esc(infos.slice(-4).join(' · ')) + ')</span>'; return; }
      var left = 60;
      var tick = function () {
        if ($('sz-res')) $('sz-res').innerHTML = spin + 'Wszystkie serwery mapy są zajęte – ponawiam za <b>' + left + ' s</b> (próba ' + (round + 1) + '/4) <span class="mut">(' + esc(infos.slice(-4).join(' · ')) + ')</span>';
        if (left-- <= 0) { d.srv = 0; run(); return; }
        setTimeout(tick, 1000);
      };
      tick(); return;
    }
    busy(btn, false);
    if (SZ.zr === 'osm' || SZ.zr === 'uk') { $('sz-res').innerHTML = 'Znaleziono na mapie: ' + r.razem + ' · nowych: ' + r.dodano + (r.pominiete ? ' · odfiltrowane ASO/sieci: ' + r.pominiete + ' (zobacz w „Odfiltrowane ASO”)' : ''); }
    else if (SZ.zr === 'ceidg') {
      if (!more && d.page === 0) { SZ.autoN = 0; SZ.autoStop = false; }
      SZ.autoN = (SZ.autoN || 0) + r.dodano;
      $('sz-more').style.display = 'none';
      if (r.wiecej && !SZ.autoStop && VIEW === 'szukaj') {
        $('sz-res').innerHTML = spin + 'Strona ' + (d.page + 1) + ' · nowych firm: <b>' + SZ.autoN + '</b>' + (r.razem ? ' z ' + r.razem : '') + ' · pobieram kolejne… <a style="text-decoration:underline;cursor:pointer;margin-left:6px" onclick="window.szAutoStop()">zatrzymaj</a>';
        busy(btn, true);
        SZ.page = d.page + 1; d.page = SZ.page;
        setTimeout(run, 1500);
        szLoad(true); szLoop();
        return;
      }
      $('sz-res').innerHTML = (SZ.autoStop ? 'Zatrzymano. ' : '✓ Gotowe – pobrano wszystkie strony. ') + 'Nowych firm: <b>' + SZ.autoN + '</b>' + (r.razem ? ' · w CEIDG łącznie: ' + r.razem : '') + (r.wiecej ? ' ' : '');
      $('sz-more').style.display = r.wiecej ? 'inline-flex' : 'none';
    }
    else { SZ.token = r.token || ''; $('sz-res').textContent = 'Nowych firm: ' + r.dodano; $('sz-more').style.display = SZ.token ? 'inline-flex' : 'none'; }
    SZ.filtr = 'wszystkie'; szLoad(); szLoop();
  }, function () { busy(btn, false); if ($('sz-res')) $('sz-res').textContent = ''; }); };
  if (SZ.zr === 'osm' || SZ.zr === 'uk') { d.srv = 0; if ($('sz-res')) $('sz-res').innerHTML = '<span class="spin" style="border-color:#8A867C;border-right-color:transparent"></span>Szukam na mapie… (do 30 s)'; }
  run();
};
// ---- UK: wszystkie większe miasta po kolei (karta musi być otwarta) ----
var UKC = ['London', 'Birmingham', 'Manchester', 'Leeds', 'Liverpool', 'Sheffield', 'Bristol', 'Newcastle upon Tyne', 'Nottingham', 'Leicester', 'Coventry', 'Bradford', 'Glasgow', 'Edinburgh', 'Cardiff', 'Belfast', 'Southampton', 'Portsmouth', 'Plymouth', 'Stoke-on-Trent', 'Wolverhampton', 'Derby', 'Reading', 'Milton Keynes', 'Northampton', 'Luton', 'Norwich', 'Swansea', 'Aberdeen', 'Brighton', 'Kingston upon Hull', 'Bournemouth', 'Middlesbrough', 'Sunderland', 'Peterborough', 'Oxford', 'Cambridge', 'York', 'Exeter', 'Preston', 'Blackpool', 'Bolton', 'Huddersfield', 'Swindon', 'Ipswich', 'Gloucester', 'Doncaster', 'Warrington', 'Dundee', 'Telford'];
var UKA = { on: false, i: 0, n: 0 };
window.ukStop = function () { UKA.on = false; toast('Zatrzymam po bieżącym mieście'); };
window.ukAll = function (btn) {
  if (!SZ.hasCH) { toast('Najpierw wpisz klucz Companies House w Ustawieniach', 'err'); return; }
  UKA = { on: true, i: 0, n: 0, btn: btn };
  busy(btn, true); $('uk-stop').style.display = 'inline-flex';
  ukNext(0, 0);
};
function ukNext(srv, round) {
  var spin = '<span class="spin" style="border-color:#8A867C;border-right-color:transparent"></span>';
  var done = function (msg) { UKA.on = false; busy(UKA.btn, false); if ($('uk-stop')) $('uk-stop').style.display = 'none'; if ($('sz-res')) $('sz-res').innerHTML = msg; szLoad(); szLoop(); };
  if (!UKA.on) { done('Zatrzymano po ' + UKA.i + ' z ' + UKC.length + ' miast · nowych firm: <b>' + UKA.n + '</b>'); return; }
  if (UKA.i >= UKC.length) { done('✓ Gotowe – przeszukano ' + UKC.length + ' miast · nowych firm: <b>' + UKA.n + '</b>. Maile i Companies House dociągają się dalej.'); return; }
  var city = UKC[UKA.i];
  var km = city === 'London' ? 10 : +val('sz-km');
  if ($('sz-res')) $('sz-res').innerHTML = spin + 'Miasto ' + (UKA.i + 1) + '/' + UKC.length + ': <b>' + esc(city) + '</b> · nowych firm: <b>' + UKA.n + '</b>';
  var d = { zrodlo: 'osm', kraj: 'gb', branza_id: +val('sz-br'), miasto: city, tag: val('sz-tag'), woj: '', km: km, bez_aso: $('sz-aso').checked ? 1 : 0, bez_sieci: $('sz-siec').checked ? 1 : 0, srv: srv };
  api('sz_szukaj', d, function (r) {
    if (r.retry) {
      if (r.srv < r.ile) { setTimeout(function () { ukNext(r.srv, round); }, 800); return; }
      if (round >= 2) { UKA.i++; setTimeout(function () { ukNext(0, 0); }, 1000); return; } // pomiń to miasto
      if ($('sz-res')) $('sz-res').innerHTML = spin + 'Serwery mapy zajęte – czekam 60 s (' + esc(city) + ')…';
      setTimeout(function () { ukNext(0, round + 1); }, 60000); return;
    }
    UKA.n += +r.dodano || 0; UKA.i++;
    szLoad(true); szLoop();
    setTimeout(function () { ukNext(0, 0); }, 2500);
  }, function () { UKA.i++; setTimeout(function () { ukNext(0, 0); }, 3000); });
}
window.szAutoStop = function () { SZ.autoStop = true; toast('Zatrzymam po bieżącej stronie'); };
function szLoop() {
  if (SZ.loop) return;
  SZ.loop = true;
  var step = function () {
    api('sz_dalej', {}, function (r) {
      var p = $('sz-prog');
      if (r.blad) { SZ.loop = false; if (p) { p.style.display = 'block'; p.innerHTML = '<b style="color:var(--err)">' + esc(r.blad) + '</b> <a class="btn sm" style="margin-left:10px" onclick="window.szResume()">Spróbuj dalej</a>'; } szLoad(); return; }
      if (p) { p.style.display = r.zostalo ? 'block' : 'none'; p.innerHTML = '<span class="spin"></span> Pobieram kontakty… zostało <b>' + r.zostalo + '</b> firm. Możesz w tym czasie korzystać z panelu na tej karcie.'; }
      if (VIEW === 'szukaj') szLoad(true);
      if (r.zostalo > 0) setTimeout(step, 1500); else SZ.loop = false;
    }, function () { SZ.loop = false; });
  };
  step();
}
window.szResume = function () { SZ.loop = false; szLoop(); };
function szLoad(quiet, first) {
  api('sz_lista', { branza_id: SZ.br, filtr: SZ.filtr, zrodlo: SZ.fz || '', miasto: SZ.fm || '', q: SZ.fq || '' }, function (r) {
    if ($('sz-fm')) { var mo = '<option value="">Wszystkie miasta</option>', ms = r.miasta || [], jest = false; for (var j = 0; j < ms.length; j++) { if (ms[j][0] === SZ.fm) jest = true; mo += '<option value="' + esc(ms[j][0]) + '"' + (ms[j][0] === SZ.fm ? ' selected' : '') + '>' + esc(ms[j][0]) + ' (' + ms[j][1] + ')</option>'; } if (SZ.fm && !jest) mo += '<option selected value="' + esc(SZ.fm) + '">' + esc(SZ.fm) + '</option>'; $('sz-fm').innerHTML = mo; }
    if ($('sz-fz')) $('sz-fz').value = SZ.fz || '';
    SZ.rows = r.rows; SZ.hasC = r.ma_ceidg; SZ.hasG = r.ma_google; SZ.hasCH = r.ma_ch; SZ.osmTags = r.osm_tags || {};
    if (!SZ.zr || (SZ.zr === 'google' && !SZ.hasG)) SZ.zr = SZ.hasC ? 'ceidg' : 'osm';
    if (first) { szTabs(); szForm(); }
    if (!quiet) SZ.sel = {};
    var c = r.counts, ch = '';
    var F = [['mail', 'Z mailem', (c.gotowe || 0)], ['czeka', 'Szukam kontaktu', (c.czeka || 0) + (c.czeka_www || 0) + (c.szukam || 0) + (c.czeka_ch || 0)], ['brak', 'Bez maila', c.brak_maila || 0], ['jest', 'Już w Firmach', (c.juz_jest || 0) + (c.dodano || 0)], ['wszystkie', 'Wszystkie', 0], ['aso', 'Odfiltrowane ASO', c.aso || 0]];
    if (c.nie_ltd || SZ.filtr === 'nieltd') F.push(['nieltd', 'UK: nie Ltd (pominięte)', c.nie_ltd || 0]);
    var tot = 0; for (var k in c) if (c.hasOwnProperty(k) && k !== 'ukryty' && k !== 'aso' && k !== 'nie_ltd') tot += c[k]; F[4][2] = tot;
    for (var i = 0; i < F.length; i++) ch += '<span class="chip' + (SZ.filtr === F[i][0] ? ' on' : '') + '" onclick="window.szF(\'' + F[i][0] + '\')">' + F[i][1] + ' <b>' + F[i][2] + '</b></span>';
    $('sz-chips').innerHTML = ch;
    if (!r.ma_ceidg && SZ.zr === 'ceidg' && $('sz-res') && !$('sz-res').textContent) $('sz-res').innerHTML = '<span style="color:var(--err)">Brak tokenu CEIDG – <a style="text-decoration:underline;cursor:pointer" onclick="window.go(\'ustawienia\')">wpisz go w Ustawieniach</a></span>';
    if (!r.ma_google && SZ.zr === 'google' && $('sz-res') && !$('sz-res').textContent) $('sz-res').innerHTML = '<span style="color:var(--err)">Brak klucza Google – <a style="text-decoration:underline;cursor:pointer" onclick="window.go(\'ustawienia\')">wpisz go w Ustawieniach</a></span>';
    szRender();
    if (((c.czeka || 0) + (c.czeka_www || 0) + (c.czeka_ch || 0)) > 0 && !SZ.loop) szLoop();
  });
}
window.szF = function (f) { SZ.filtr = f; szLoad(); };
window.szFl = function () { SZ.fz = val('sz-fz'); SZ.fm = val('sz-fm'); szLoad(); };
window.szFlQ = function () { clearTimeout(SZ.qt); SZ.qt = setTimeout(function () { SZ.fq = val('sz-fq').trim(); szLoad(); }, 350); };
window.szFlReset = function () { SZ.fz = ''; SZ.fm = ''; SZ.fq = ''; if ($('sz-fq')) $('sz-fq').value = ''; szLoad(); };
var SZST = { aso: ['ASO / sieć', 'st-wypisany'], gotowe: ['Z mailem', 'st-zainteresowany'], czeka: ['Pobieram…', 'st-wyslano'], czeka_www: ['Szukam na stronie…', 'st-wyslano'], szukam: ['Szukam na stronie…', 'st-wyslano'], czeka_ch: ['Sprawdzam w Companies House…', 'st-wyslano'], nie_ltd: ['Nie Ltd – pominięte', 'st-wypisany'], brak_maila: ['Brak maila', 'st-nie'], juz_jest: ['Już w Firmach', 'st-wypisany'], dodano: ['Dodano', 'st-otworzyl'] };
function szRender() {
  var el = $('sz-list'); if (!el) return;
  if (!SZ.rows.length) { el.innerHTML = '<div class="empty">Brak wyników' + (SZ.filtr !== 'wszystkie' ? ' w tym filtrze' : '') + '. Wpisz kryteria wyżej i kliknij Szukaj.</div>'; szBulk(); return; }
  var h = '<table class="tbl resp"><thead><tr><th style="width:36px"><input type="checkbox" onclick="window.szAll(this.checked)"></th><th>Firma</th><th>E-mail</th><th class="hm">Telefon</th><th class="hm">Miasto</th><th>Status</th></tr></thead><tbody>';
  for (var i = 0; i < SZ.rows.length; i++) {
    var r = SZ.rows[i], st = SZST[r.status] || [r.status, ''];
    var can = r.status === 'gotowe';
    h += '<tr><td class="c0">' + (can ? '<input type="checkbox" ' + (SZ.sel[r.id] ? 'checked' : '') + ' onchange="window.szSel(' + r.id + ',this.checked)">' : '') + '</td>' +
      '<td><div class="b1">' + esc(r.nazwa) + '</div><div class="mut">' + esc(r.osoba || '') + (r.www ? (r.osoba ? ' · ' : '') + '<a href="' + esc(r.www) + '" target="_blank" rel="noopener" style="text-decoration:underline">www</a>' : '') + ' · ' + (r.zrodlo === 'ceidg' ? 'CEIDG' : (r.zrodlo === 'osm' ? 'OpenStreetMap' : (r.zrodlo === 'uk' ? 'UK' + (r.nip ? ' · Companies House ' + esc(r.nip) : '') : 'Google'))) + '</div></td>' +
      '<td>' + (r.email ? esc(r.email) : '<span class="mut">—</span>') + '</td><td class="hm">' + esc(r.telefon || '—') + '</td><td class="hm">' + esc(r.miasto || '') + '</td>' +
      '<td><span class="pill ' + st[1] + '">' + st[0] + '</span>' + (r.status === 'aso' ? ' <a class="btn sm" style="margin-left:6px" onclick="window.szRestore(' + r.id + ',this)">Przywróć</a>' : '') + '</td></tr>';
  }
  el.innerHTML = h + '</tbody></table>';
  szBulk();
}
function szIds() { var a = []; for (var k in SZ.sel) if (SZ.sel.hasOwnProperty(k)) a.push(+k); return a; }
function szBulk() { var n = szIds().length; var b = $('sz-bulk'); if (!b) return; b.className = 'bulk' + (n ? ' on' : ''); $('sz-n').textContent = 'Zaznaczono: ' + n; }
window.szSel = function (id, on) { if (on) SZ.sel[id] = 1; else delete SZ.sel[id]; szBulk(); };
window.szAll = function (on) { SZ.sel = {}; if (on) for (var i = 0; i < SZ.rows.length; i++) if (SZ.rows[i].status === 'gotowe') SZ.sel[SZ.rows[i].id] = 1; szRender(); };
window.szMove = function (gdzie) {
  var ids = szIds(); if (!ids.length) return;
  api('sz_przenies', { ids: ids, gdzie: gdzie, branza_id: +val('sz-tb') }, function (r) {
    toast((gdzie === 'arkusz' ? 'Dodano do Arkusza: ' : 'Dodano do Firm: ') + r.n + (r.duplikaty ? ' · pominięte duplikaty: ' + r.duplikaty : ''));
    SZ.sel = {}; szLoad();
  });
};
window.szDel = function () { var ids = szIds(); if (!ids.length) return; api('sz_usun', { ids: ids }, function () { SZ.sel = {}; szLoad(); }); };
window.SZ_set = function (k, v) { SZ[k] = v; };
window.szRestore = function (id, btn) { busy(btn, true); api('sz_przywroc', { id: id }, function () { toast('Przywrócono'); szLoad(); szLoop(); }, function () { busy(btn, false); }); };
window.szHideNo = function () {
  if (!sure('Usunąć firmy bez maila?', 'Znikną z listy i <b>nie wrócą</b> przy kolejnych wyszukiwaniach (panel pamięta je, żeby nie dodać ich drugi raz).', 'Usuń', function () { window.szHideNo(); }, true)) return;
  api('sz_ukryj_bez', {}, function (r) { toast('Usunięto bez maila: ' + r.n); SZ.filtr = 'mail'; szLoad(); });
};
window.szNoAso = function () {
  var go = function (siec) { api('sz_usun_aso', { sieci: siec ? 1 : 0 }, function (r) { toast('Usunięto z wyników: ' + r.n); szLoad(); }); };
  window.ask('Usunąć ASO z wyników?', 'Usunę autoryzowane serwisy i salony marek. Możesz od razu usunąć też sieci (Bosch Car Service, ProfiAuto, Euromaster…).', 'Tylko ASO', function () { go(false); }, { alt: 'ASO + sieci', onAlt: function () { go(true); } });
};
window.szClearAll = function () { if (!sure('Wyczyścić wyniki?', 'Usunę wszystkie wyniki wyszukiwania. Firm w Arkuszu i w Firmach to nie rusza.', 'Wyczyść', function () { window.szClearAll(); }, true)) return; api('sz_usun', { wszystko: 1 }, function () { szLoad(); }); };

// ========== ARKUSZ (osobny dla każdej branży) ==========
var AR = { rows: [], br: 0, szkice: {}, t: null };
var AR_COLS = ['nazwa', 'email', 'telefon', 'miasto'];
function arEmpty() { return { nazwa: '', email: '', telefon: '', miasto: '', msg: '' }; }
function arFilled(r) { return (r.nazwa + r.email + r.telefon + r.miasto).replace(/\s/g, '') !== ''; }
function arEmailOk(e) { return /^[^\s@]+@[^\s@]+\.[a-z]{2,}$/i.test(String(e).trim()); }
function arPad() { var n = 0; for (var i = 0; i < AR.rows.length; i++) if (!arFilled(AR.rows[i])) n++; while (n < 10 || AR.rows.length < 15) { AR.rows.push(arEmpty()); n++; } }
function arStore() {
  clearTimeout(AR.t);
  var br = AR.br, rows = [];
  for (var i = 0; i < AR.rows.length; i++) if (arFilled(AR.rows[i])) rows.push(AR.rows[i]);
  AR.szkice[br] = rows.length; arChips();
  if ($('ar-sv')) $('ar-sv').textContent = 'zapisywanie szkicu…';
  AR.t = setTimeout(function () { api('arkusz_szkic', { branza_id: br, rows: rows }, function () { if ($('ar-sv')) $('ar-sv').textContent = '✓ szkic zapisany'; }); }, 900);
}
function viewArkusz() {
  loadBranze(function () {
    if (!AR.br && BR.length) AR.br = +BR[0].id;
    var ok = false; for (var i = 0; i < BR.length; i++) if (+BR[i].id === AR.br) ok = true;
    if (!ok && BR.length) AR.br = +BR[0].id;
    var h = '<div class="head"><div><h1>Arkusz</h1><p>Wybierz branżę i wpisuj firmy jak w Excelu. Na koniec kliknij <b>Dodaj do Firm</b> – trafią do tej branży.</p></div></div>' +
      '<div class="chips" id="ar-chips" style="margin-bottom:14px"></div>' +
      '<div class="card pad arbar"><b id="ar-title"></b><span class="mut" id="ar-info"></span><span style="flex:1"></span>' +
      '<input id="ar-fm" placeholder="miasto dla pustych…" style="width:190px"><a class="btn sm" onclick="window.arFill()">Uzupełnij</a>' +
      '<a class="btn sm" onclick="window.arAdd(10)">+ 10 wierszy</a><a class="btn sm red" onclick="window.arClear()">Wyczyść</a></div>' +
      '<div class="card arwrap"><table class="ark"><thead><tr><th class="n">#</th><th>Nazwa firmy</th><th>E-mail *</th><th>Telefon <span class="mut">(opcj.)</span></th><th>Miasto</th><th></th></tr></thead><tbody id="ar-body"><tr><td colspan="6" class="empty">Ładowanie…</td></tr></tbody></table></div>' +
      '<div class="row" style="margin-top:12px"><a class="btn dark big" id="ar-save" style="width:auto;margin:0" onclick="window.arSave(this)">Dodaj do Firm</a><a class="btn" onclick="window.arAdd(10)">+ 10 wierszy</a><span class="mut" id="ar-count"></span><span class="mut" id="ar-sv" style="margin-left:auto"></span></div>' +
      '<p class="hint">Enter = wiersz niżej · wklejasz z Excela? Kliknij w komórkę i Cmd+V – wiersze i kolumny same się rozłożą (działa też lista „1. Firma - mail - telefon”).</p>';
    $('main').innerHTML = h;
    arLoad();
  });
}
function arLoad() {
  api('arkusz_get', { branza_id: AR.br }, function (r) {
    AR.rows = r.rows || []; AR.szkice = r.szkice || {};
    arPad(); arChips(); arRender();
  });
}
function arChips() {
  var h = '';
  for (var i = 0; i < BR.length; i++) {
    var b = BR[i], n = AR.szkice[+b.id] || 0;
    h += '<span class="chip big' + (+b.id === AR.br ? ' on' : '') + '" onclick="window.arPick(' + b.id + ')">' + esc(b.nazwa) + (n ? ' <b class="dr">' + n + ' w arkuszu</b>' : '') + '</span>';
  }
  if ($('ar-chips')) $('ar-chips').innerHTML = h;
  var cb = null; for (i = 0; i < BR.length; i++) if (+BR[i].id === AR.br) cb = BR[i];
  if (cb && $('ar-title')) { $('ar-title').textContent = cb.nazwa; $('ar-info').innerHTML = '· w Firmach: <a style="text-decoration:underline;cursor:pointer" onclick="window.firmyBranza(' + cb.id + ')">' + (+cb.firmy || 0) + '</a>'; }
}
window.arPick = function (id) { AR.br = +id; arLoad(); };
function arRender() {
  var h = '';
  for (var i = 0; i < AR.rows.length; i++) h += arRowHtml(i);
  $('ar-body').innerHTML = h;
  arCount();
}
function arRowHtml(i) {
  var r = AR.rows[i], h = '<tr id="ar-r' + i + '" class="' + (r.msg ? 'err' : '') + '"><td class="n">' + (i + 1) + '</td>';
  for (var c = 0; c < AR_COLS.length; c++) {
    var k = AR_COLS[c];
    var cls = (k === 'email' && r.email && !arEmailOk(r.email)) ? ' class="bad"' : '';
    h += '<td><input id="ar-' + i + '-' + c + '"' + cls + ' value="' + esc(r[k]) + '" oninput="window.arIn(' + i + ',' + c + ',this)" onkeydown="window.arKey(event,' + i + ',' + c + ')" onpaste="window.arPaste(event,' + i + ',' + c + ')"' + (k === 'email' ? ' type="email" autocapitalize="off"' : '') + '></td>';
  }
  h += '<td class="st">' + (r.msg ? '<span class="er" title="' + esc(r.msg) + '">' + esc(r.msg) + '</span>' : '') + (r.msg && r.msg.indexOf('duplikat') === 0 ? '<a class="btn sm" style="height:26px;margin-right:4px" onclick="window.arForce(' + i + ')">Dodaj mimo to</a>' : '') + '<button class="ib del" title="Usuń wiersz" onclick="window.arDel(' + i + ')">✕</button></td></tr>';
  return h;
}
function arCount() {
  var n = 0, b = 0;
  for (var i = 0; i < AR.rows.length; i++) if (arFilled(AR.rows[i])) { n++; if (!arEmailOk(AR.rows[i].email)) b++; }
  if ($('ar-count')) $('ar-count').textContent = 'Wypełnione: ' + n + (b ? ' · bez poprawnego e-maila: ' + b : '');
  if ($('ar-save')) $('ar-save').innerHTML = 'Dodaj do Firm' + (n ? ' (' + n + ')' : '');
}
window.arIn = function (i, c, el) {
  var r = AR.rows[i]; r[AR_COLS[c]] = el.value; r.msg = '';
  if (AR_COLS[c] === 'email') el.className = (el.value && !arEmailOk(el.value)) ? 'bad' : '';
  if (i === AR.rows.length - 1 && el.value) { AR.rows.push(arEmpty()); var tb = document.createElement('tbody'); tb.innerHTML = arRowHtml(AR.rows.length - 1); $('ar-body').appendChild(tb.firstChild); }
  arStore(); arCount();
};
window.arKey = function (e, i, c) {
  var t = null;
  if (e.key === 'Enter' || e.key === 'ArrowDown') t = i + 1; else if (e.key === 'ArrowUp') t = i - 1;
  if (t === null) return;
  e.preventDefault();
  if (t >= AR.rows.length) window.arAdd(5);
  var el = $('ar-' + t + '-' + c); if (el) { el.focus(); el.select(); }
};
window.arForce = function (i) { AR.rows[i].wymus = true; AR.rows[i].msg = ''; arRender(); toast('Ten wiersz zostanie dodany przy zapisie'); };
window.arAdd = function (n) { for (var i = 0; i < n; i++) AR.rows.push(arEmpty()); arRender(); };
window.arDel = function (i) { AR.rows.splice(i, 1); arPad(); arRender(); arStore(); };
window.arClear = function () { if (!sure('Wyczyścić arkusz?', 'Usunę wszystkie wiersze z arkusza tej branży.', 'Wyczyść', function () { window.arClear(); }, true)) return; AR.rows = []; arPad(); arRender(); arStore(); };
window.arFill = function () {
  var m = val('ar-fm').trim(), n = 0; if (!m) { toast('Wpisz miasto', 'err'); return; }
  for (var i = 0; i < AR.rows.length; i++) { var r = AR.rows[i]; if (arFilled(r) && !r.miasto) { r.miasto = m; n++; } }
  arRender(); arStore(); toast('Uzupełniono miasto w ' + n + ' wierszach');
};
function arParseFree(line) {
  var r = { nazwa: '', email: '', telefon: '', miasto: '' };
  var m = line.match(/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/);
  if (m) { r.email = m[0].toLowerCase(); line = line.replace(m[0], ' '); }
  var p = line.match(/(\+?48[\s-]?)?(\d[\s-]?){8}\d/);
  if (p) { r.telefon = p[0].trim(); line = line.replace(p[0], ' '); }
  line = line.replace(/^\s*\d+[.)]\s*/, '').replace(/\s*[-–—|;,:]+\s*/g, ' – ').replace(/^[\s–]+|[\s–]+$/g, '');
  var parts = line.split(' – ');
  r.nazwa = (parts[0] || '').trim();
  if (parts[1] && parts[1].length <= 40) r.miasto = parts[1].trim();
  return r;
}
window.arPaste = function (e, i, c) {
  var txt = '';
  try { txt = (e.clipboardData || window.clipboardData).getData('text'); } catch (x) { return; }
  if (!txt || (txt.indexOf('\t') < 0 && txt.indexOf('\n') < 0)) return;
  e.preventDefault();
  var lines = txt.replace(/\r/g, '').split('\n'), row = i, added = 0;
  for (var l = 0; l < lines.length; l++) {
    var line = lines[l];
    if (!line.trim()) continue;
    if (/^(nazwa|firma)\b/i.test(line) && /mail/i.test(line)) continue;
    while (row >= AR.rows.length) AR.rows.push(arEmpty());
    var r = AR.rows[row];
    if (line.indexOf('\t') >= 0) {
      var cells = line.split('\t');
      for (var k = 0; k < cells.length && c + k < AR_COLS.length; k++) r[AR_COLS[c + k]] = cells[k].trim();
    } else if (line.indexOf('@') >= 0 && c === 0) {
      var f = arParseFree(line); r.nazwa = f.nazwa; r.email = f.email; if (f.telefon) r.telefon = f.telefon; if (f.miasto) r.miasto = f.miasto;
    } else r[AR_COLS[c]] = line.trim();
    r.msg = ''; row++; added++;
  }
  arPad(); arRender(); arStore();
  toast('Wklejono wierszy: ' + added);
};
window.arSave = function (btn) {
  var send = [], idx = [];
  for (var i = 0; i < AR.rows.length; i++) { var r = AR.rows[i]; if (!arFilled(r)) continue; send.push({ nazwa: r.nazwa, email: r.email, telefon: r.telefon, miasto: r.miasto, osoba: r.osoba || '', www: r.www || '', zrodlo: r.zrodlo || '', wymus: r.wymus ? 1 : 0, branza_id: AR.br }); idx.push(i); }
  if (!send.length) { toast('Arkusz jest pusty', 'err'); return; }
  busy(btn, true);
  api('arkusz_zapisz', { rows: send }, function (res) {
    busy(btn, false);
    var keep = [], bad = 0, okm = {};
    for (var j = 0; j < res.res.length; j++) { var r = AR.rows[idx[j]]; if (res.res[j].ok) okm[idx[j]] = 1; else { r.msg = res.res[j].msg; bad++; } }
    for (i = 0; i < AR.rows.length; i++) if (!okm[i] && arFilled(AR.rows[i])) keep.push(AR.rows[i]);
    AR.rows = keep; arPad(); arRender(); arStore();
    loadBranze(arChips);
    toast('Dodano do Firm: ' + res.dodano + (bad ? ' · do poprawy: ' + bad + ' (na czerwono)' : ''), bad ? 'err' : '');
  }, function () { busy(btn, false); });
};

// ========== MAILE / BRANŻE ==========
function viewMaile() {
  loadBranze(function () {
    if (!ED.branza && BR.length) ED.branza = +BR[0].id;
    var exists = false; for (var i = 0; i < BR.length; i++) if (+BR[i].id === ED.branza) exists = true;
    if (!exists && BR.length) ED.branza = +BR[0].id;
    if (!ED.mode) ED.mode = 'maile';
    var M = { maile: 'Maile branż', podpis: 'Podpis i stopka', formularz: 'Formularz zgłoszenia' };
    var h = '<div class="head"><div><h1>Maile</h1><p>Maile dla każdej branży, wspólny podpis i strona formularza, którą widzi klient po kliknięciu przycisku.</p></div></div><div class="tabs top">';
    for (var k in M) if (M.hasOwnProperty(k)) h += '<a class="' + (ED.mode === k ? 'on' : '') + '" onclick="window.edMode(\'' + k + '\')">' + M[k] + '</a>';
    h += '</div>';
    if (ED.mode === 'maile') h += '<div class="chips blist" id="blist" style="margin-bottom:14px"></div>';
    h += '<div class="card pad" id="edp"><div class="empty">Ładowanie…</div></div>';
    $('main').innerHTML = h;
    var go = function () {
      if (ED.mode === 'maile') { renderBlist(); loadTpl(); }
      else if (ED.mode === 'podpis') viewPodpis();
      else viewForm();
    };
    if (ED.testTo === undefined) api('ustawienia', {}, function (r) { ED.testTo = r.u.powiadom_email; ED.u = r.u; go(); }); else go();
  });
}
window.edMode = function (m) { ED.mode = m; viewMaile(); };
window.goPodpis = function () { ED.mode = 'podpis'; window.go('maile'); };

// ---- Podpis i stopka ----
var PK = [['pozdrowienie', 'Pozdrowienie', 'np. Z pozdrowieniami, / Pozdrawiam serdecznie,'], ['podpis_imie', 'Imię i nazwisko', 'Pierwsze słowo trafia do {imie}'], ['podpis_rola', 'Druga linia pod nazwiskiem (opcjonalnie)', 'np. ELOK – automatyzacje dla firm; puste = brak'],
  ['podpis_tel', 'Telefon', ''], ['podpis_email', 'E-mail', ''], ['podpis_www', 'Strona', 'np. elok-agency.pl; puste = brak'], ['stopka_firma', 'Stopka na dole maila (mała szara)', 'np. dane firmy']];
function viewPodpis() {
  api('ustawienia', {}, function (r) {
    ED.u = r.u;
    api('szablony', { branza_id: ED.branza }, function (s) {
      ED.pbloki = s.szablony.pierwszy ? s.szablony.pierwszy.bloki : [];
      var h = '<div class="edwrap"><div class="pvcol"><div class="prev"><div class="sb"><span class="mut">Podgląd na przykładzie maila:</span> <b>' + esc((curBr() || {}).nazwa || '') + '</b></div><iframe id="pv" title="Podgląd"></iframe></div></div><div>' +
        '<h2 style="font-size:17px">Podpis i stopka</h2><p class="mut" style="margin:4px 0 6px">Wspólne dla wszystkich maili i formularza. Puste pole = nie pokazuje się.</p>';
      for (var i = 0; i < PK.length; i++) h += '<label class="f">' + PK[i][1] + '</label><input id="pp-' + PK[i][0] + '" value="' + esc(ED.u[PK[i][0]]) + '" oninput="window.ppChange()" placeholder="' + esc(PK[i][2]) + '">';
      h += '<div class="row" style="margin-top:16px"><a class="btn dark" onclick="window.ppSave(this)">Zapisz podpis</a></div></div></div>';
      $('edp').innerHTML = h;
      ppPreview();
    });
  });
}
function ppData() { var o = {}; for (var i = 0; i < PK.length; i++) o[PK[i][0]] = val('pp-' + PK[i][0]); return o; }
var ppT = null;
window.ppChange = function () { clearTimeout(ppT); ppT = setTimeout(ppPreview, 350); };
function ppPreview() {
  api('podglad', { branza_id: ED.branza, typ: 'pierwszy', temat: '', bloki: ED.pbloki || [], podpis: ppData() }, function (r) { var f = $('pv'); if (f) f.srcdoc = r.html; });
}
window.ppSave = function (btn) {
  busy(btn, true);
  api('ustawienia_zapisz', { u: ppData() }, function () { busy(btn, false); toast('Podpis zapisany'); }, function () { busy(btn, false); });
};

// ---- Formularz zgłoszenia ----
var FP = [['firma', 'Firma'], ['osoba', 'Imię i nazwisko'], ['telefon', 'Telefon'], ['email', 'E-mail'], ['czym', 'Czym się zajmują'], ['co', 'Co chcą usprawnić'], ['dzien', 'Dzień (lista)'], ['kiedy', 'Godzina (lista)']];
var FV = 'form';
function viewForm() {
  api('form_get', {}, function (r) {
    ED.form = r.cfg; ED.formDef = r.def;
    renderForm();
  });
}
function renderForm() {
  var c = ED.form;
  function fi(k, label, ta, ph) {
    var ev = ' oninput="window.fSet(\'' + k + '\',this.value)"';
    return '<label class="f">' + label + '</label>' + (ta ? '<textarea rows="3"' + ev + ' placeholder="' + esc(ph || '') + '">' + esc(c[k]) + '</textarea>' : '<input value="' + esc(c[k]) + '"' + ev + ' placeholder="' + esc(ph || '') + '">');
  }
  var h = '<div class="edwrap"><div class="pvcol"><div class="tabs" style="margin-bottom:10px"><a class="' + (FV === 'form' ? 'on' : '') + '" onclick="window.fView(\'form\')">Formularz</a><a class="' + (FV === 'dzieki' ? 'on' : '') + '" onclick="window.fView(\'dzieki\')">Po wysłaniu</a></div>' +
    '<div class="prev"><iframe id="pv" title="Podgląd" style="height:900px"></iframe></div><p class="hint">Tak widzi to klient po kliknięciu przycisku w mailu. <a href="p.php?a=tak" target="_blank" style="text-decoration:underline">Otwórz prawdziwą stronę</a></p></div><div>' +
    '<h2 style="font-size:17px">Formularz zgłoszenia</h2>' +
    fi('kicker', 'Nadtytuł (złoty, małe litery)', false, 'puste = brak') + fi('tytul', 'Tytuł') + fi('opis', 'Opis pod tytułem', true) +
    fi('perks', 'Plakietki ✓ (jedna w linii)', true, 'Bezpłatnie') +
    '<label class="f">Pola formularza</label><table class="tbl ftbl"><thead><tr><th>Pole</th><th>Nazwa pola</th><th>Podpowiedź w polu</th><th>Widoczne</th><th>Wymagane</th></tr></thead><tbody>';
  for (var i = 0; i < FP.length; i++) {
    var k = FP[i][0], p = c.pola[k], lock = (k === 'firma' || k === 'email');
    h += '<tr><td class="mut">' + FP[i][1] + '</td><td><input value="' + esc(p.label) + '" oninput="window.fPole(\'' + k + '\',\'label\',this.value)"></td>' +
      '<td><input value="' + esc(p.ph) + '" oninput="window.fPole(\'' + k + '\',\'ph\',this.value)"></td>' +
      '<td style="text-align:center"><input type="checkbox" ' + (p.show ? 'checked' : '') + (lock ? ' disabled' : '') + ' onchange="window.fPole(\'' + k + '\',\'show\',this.checked?1:0)"></td>' +
      '<td style="text-align:center"><input type="checkbox" ' + (p.req ? 'checked' : '') + (lock ? ' disabled' : '') + ' onchange="window.fPole(\'' + k + '\',\'req\',this.checked?1:0)"></td></tr>';
  }
  h += '</tbody></table><div class="hint">Firma i e-mail są zawsze wymagane – na e-mail idzie potwierdzenie.</div>' +
    fi('dzien_opcje', 'Dni do wyboru (jedna w linii)', true) +
    fi('kiedy_opcje', 'Godziny do wyboru (jedna w linii)', true) +
    fi('przycisk', 'Tekst przycisku') + fi('nota', 'Mały tekst pod przyciskiem', true) +
    '<label class="row" style="margin-top:12px;cursor:pointer;gap:10px"><input type="checkbox" ' + (c.podpis ? 'checked' : '') + ' onchange="window.fSet(\'podpis\',this.checked?1:0)"> <span>Pokaż mój podpis (imię, telefon) pod formularzem</span></label>' +
    '<h2 style="font-size:15px;margin-top:20px">Po wysłaniu</h2>' + fi('dzieki_tytul', 'Tytuł') + fi('dzieki_tekst', 'Tekst ({email} = adres klienta)', true) +
    '<div class="row" style="margin-top:16px"><a class="btn dark" onclick="window.fSave(this)">Zapisz formularz</a><a class="btn" onclick="window.fReset()">Przywróć domyślny</a></div></div></div>';
  $('edp').innerHTML = h;
  fPreview();
}
var fpT = null;
window.fSet = function (k, v) { ED.form[k] = v; clearTimeout(fpT); fpT = setTimeout(fPreview, 350); };
window.fPole = function (k, f, v) { ED.form.pola[k][f] = v; clearTimeout(fpT); fpT = setTimeout(fPreview, 350); };
window.fView = function (v) { FV = v; renderForm(); };
function fPreview() { api('form_podglad', { cfg: ED.form, view: FV }, function (r) { var f = $('pv'); if (f) f.srcdoc = r.html; }); }
window.fSave = function (btn) { busy(btn, true); api('form_zapisz', { cfg: ED.form }, function () { busy(btn, false); toast('Formularz zapisany'); }, function () { busy(btn, false); }); };
window.fReset = function () { if (!sure('Przywrócić domyślny formularz?', 'Teksty wrócą do domyślnych. Zapisze się dopiero po kliknięciu „Zapisz formularz”.', 'Przywróć', function () { window.fReset(); })) return; ED.form = JSON.parse(JSON.stringify(ED.formDef)); renderForm(); };
function renderBlist() {
  var h = '';
  for (var i = 0; i < BR.length; i++) {
    var b = BR[i];
    h += '<span class="chip big' + (+b.id === ED.branza ? ' on' : '') + (+b.aktywna ? '' : ' off') + '" onclick="window.pickBranza(' + b.id + ')">' + esc(b.nazwa) + ' <b>' + b.firmy + '</b></span>';
  }
  h += '<span class="chip big add" onclick="window.branzaAdd()">+ Nowa branża</span>';
  $('blist').innerHTML = h;
}
function curBr() { for (var i = 0; i < BR.length; i++) if (+BR[i].id === ED.branza) return BR[i]; return null; }
window.pickBranza = function (id) { ED.branza = +id; renderBlist(); loadTpl(); };
function loadTpl() {
  api('szablony', { branza_id: ED.branza }, function (r) { ED.data = r.szablony; renderEd(); });
}
var BT = {
  tekst: { l: 'Tekst', i: '¶', d: { text: '' } },
  naglowek: { l: 'Nagłówek (czarny pasek)', i: '▀', d: { nad: '', text: 'Nagłówek' } },
  ramka: { l: 'Ramka – np. ostatnia realizacja', i: '▍', d: { title: 'Ostatnia realizacja', text: '' } },
  korzysci: { l: 'Lista korzyści ✓', i: '✓', d: { text: 'Tytuł | krótki opis' } },
  przycisk: { l: 'Przycisk → formularz', i: '▭', d: { text: 'Umów bezpłatną rozmowę', sub: 'Krótki formularz – zajmie 30 sekund.' } },
  obrazek: { l: 'Obrazek', i: '▣', d: { url: '', link: '' } },
  linia: { l: 'Linia oddzielająca', i: '—', d: {} }
};
function renderEd() {
  var b = curBr(); if (!b) { $('edp').innerHTML = '<div class="empty">Dodaj branżę</div>'; return; }
  var t = ED.data[ED.typ] || { temat: '', bloki: [] };
  ED.blocks = JSON.parse(JSON.stringify(t.bloki || []));
  var wys = +b.wyslane || 0;
  var h = '<div class="row" style="margin-bottom:12px"><input class="grow" id="br-n" value="' + esc(b.nazwa) + '" style="font-weight:600">' +
    '<label class="row" style="gap:8px;cursor:pointer"><span class="mut">Wysyłka</span><span class="sw"><input type="checkbox" id="br-a" ' + (+b.aktywna ? 'checked' : '') + '><i></i></span></label>' +
    '<a class="btn sm" onclick="window.branzaSave()">Zapisz nazwę</a><a class="btn sm red" onclick="window.branzaDel()">Usuń</a></div>' +
    '<div class="mini">' +
      '<div><b>' + (+b.firmy || 0) + '</b><span>firm</span></div><div><b>' + (+b.nowe || 0) + '</b><span>do wysłania</span></div>' +
      '<div><b>' + wys + '</b><span>wysłane</span></div><div><b>' + pct(+b.otwarte, wys) + '%</b><span>otworzyło</span></div>' +
      '<div><b>' + (+b.klik || 0) + '</b><span>kliknęło</span></div><div><b style="color:var(--ok)">' + (+b.zaint || 0) + '</b><span>zainteresowani</span></div></div>';
  h += '<div class="tabs">';
  for (var k in TYPY) if (TYPY.hasOwnProperty(k)) h += '<a class="' + (ED.typ === k ? 'on' : '') + '" onclick="window.pickTyp(\'' + k + '\')">' + TYPY[k] + '</a>';
  h += '</div>';
  var info = { pierwszy: 'Idzie do nowych firm z tej branży (automat pn–pt rano albo „Wyślij teraz”).', przypomnienie: 'Idzie raz, po X dniach (Ustawienia) – tylko do firm, które nie kliknęły.', przypomnienie2: 'Ostatni mail – idzie X dni po pierwszym przypomnieniu, tylko do firm, które nie kliknęły (włączasz w Ustawieniach).', potwierdzenie: 'Idzie automatycznie do firmy zaraz po wysłaniu formularza.' };
  h += '<p class="mut" style="margin:-4px 0 12px">' + info[ED.typ] + '</p>';
  h += '<div class="edwrap"><div class="pvcol"><div class="prev"><div class="sb"><span class="mut">Temat:</span> <b id="pv-s"></b></div><iframe id="pv" title="Podgląd"></iframe></div><p class="hint">Podgląd z przykładową firmą „Auto-Serwis Kowalski”. Logo, podpis i „wypisz się” dodają się same.</p></div><div>' +
    '<div class="testbox"><label class="f" style="margin-top:0">Wyślij sobie test</label><div class="row"><input class="grow" id="t-to" type="email" value="' + esc(ED.testTo || '') + '"><a class="btn dark" onclick="window.tplTest(this)">Wyślij test</a></div></div>' +
    '<label class="f">Temat maila (neutralny, bez nazwy ELOK)</label><input id="t-temat" value="' + esc(t.temat) + '" oninput="window.edChange()" onfocus="window.lastF=this">' +
    '<div class="row" style="justify-content:space-between;margin-top:16px"><label class="f" style="margin:0">Bloki maila <span class="mut" style="font-weight:400">– przeciągnij za ⋮⋮, żeby zmienić kolejność</span></label></div>' +
    '<div id="blks"></div>' +
    '<div class="pal"><span class="mut" style="font-size:12px;width:100%">Dodaj blok:</span>';
  for (k in BT) if (BT.hasOwnProperty(k) && !(ED.typ === 'potwierdzenie' && k === 'przycisk')) h += '<a onclick="window.bAdd(\'' + k + '\')"><i>' + BT[k].i + '</i>' + BT[k].l.split(' (')[0].split(' –')[0] + '</a>';
  h += '</div>' +
    '<div class="vars"><span class="mut" style="background:none;cursor:default;padding:3px 0">Wstaw do pola:</span>' + varChip('{firma}') + varChip('{miasto}') + varChip('{osoba}') + varChip('{imie}') + varChip('{moj_telefon}') + '</div>' +
    '<p class="hint">**tekst** = pogrubienie · pusta linia w bloku tekstu = nowy akapit · w liście: jedna korzyść w linii, „Tytuł | opis”.</p>' +
    '<div class="row" style="margin-top:16px"><a class="btn dark" onclick="window.tplSave(this)">Zapisz mail</a><a class="btn" onclick="window.tplReset()">Przywróć gotowy szablon</a>' +
    '<span class="mut" id="t-saved">' + (t.updated_at ? 'zapisano ' + fd(t.updated_at) : '') + '</span></div>' +
    '<p class="hint" style="margin-top:14px">Prawo: pierwszy mail nie jest ofertą – tylko pyta o rozmowę. Każdy mail ma link „wypisz się”, wypisani nie dostaną już nic.</p></div></div>';
  $('edp').innerHTML = h;
  renderBlocks();
  preview();
}
function fld(i, f, label, type, ph) {
  var v = esc(ED.blocks[i][f] || '');
  var ev = ' oninput="window.bSet(' + i + ',\'' + f + '\',this.value)" onfocus="window.lastF=this"';
  if (type === 'ta') return '<label class="fl">' + label + '</label><textarea class="auto" rows="2"' + ev.replace('oninput="', 'oninput="window.grow(this);') + ' placeholder="' + esc(ph || '') + '">' + v + '</textarea>';
  return '<label class="fl">' + label + '</label><input value="' + v + '"' + ev + ' placeholder="' + esc(ph || '') + '">';
}
function renderBlocks() {
  var el = $('blks'); if (!el) return;
  var h = '', hasBtn = false;
  for (var i = 0; i < ED.blocks.length; i++) {
    var b = ED.blocks[i], d = BT[b.t]; if (!d) continue;
    if (b.t === 'przycisk') hasBtn = true;
    h += '<div class="blk" id="blk' + i + '" ondragstart="window.bDragStart(event,' + i + ')" ondragover="window.bDragOver(event,' + i + ')" ondragleave="window.bDragLeave(' + i + ')" ondrop="window.bDrop(event,' + i + ')" ondragend="window.bDragEnd(' + i + ')">' +
      '<div class="bh"><span class="grip" draggable="true" title="Przeciągnij, żeby przenieść">⋮⋮</span><span class="bi">' + d.i + '</span><b>' + d.l + '</b><span style="flex:1"></span>' +
      '<button class="ib" title="Wyżej" onclick="window.bMove(' + i + ',-1)"' + (i === 0 ? ' disabled' : '') + '>↑</button>' +
      '<button class="ib" title="Niżej" onclick="window.bMove(' + i + ',1)"' + (i === ED.blocks.length - 1 ? ' disabled' : '') + '>↓</button>' +
      '<button class="ib" title="Duplikuj" onclick="window.bDup(' + i + ')">⧉</button>' +
      '<button class="ib del" title="Usuń" onclick="window.bDel(' + i + ')">✕</button></div><div class="bb">';
    if (b.t === 'tekst') h += fld(i, 'text', 'Treść', 'ta', 'Wpisz tekst…');
    else if (b.t === 'naglowek') h += fld(i, 'nad', 'Nadtytuł (złoty, małe litery) – opcjonalnie', 'in', 'np. Automatyzacje dla warsztatów') + fld(i, 'text', 'Nagłówek', 'ta');
    else if (b.t === 'ramka') h += fld(i, 'title', 'Tytuł ramki', 'in') + fld(i, 'text', 'Opis', 'ta');
    else if (b.t === 'korzysci') h += fld(i, 'text', 'Korzyści – jedna w linii: Tytuł | opis', 'ta');
    else if (b.t === 'przycisk') h += fld(i, 'text', 'Tekst przycisku', 'in') + fld(i, 'sub', 'Mały tekst pod przyciskiem – opcjonalnie', 'in', 'np. Krótki formularz – 30 sekund') + '<div class="hint">Przycisk otwiera formularz zgłoszenia (firma, osoba, telefon, czym się zajmują, co usprawnić).</div>';
    else if (b.t === 'obrazek') h += fld(i, 'url', 'Link do obrazka (https://…)', 'in', 'https://elok-agency.pl/obrazek.jpg') + fld(i, 'link', 'Po kliknięciu otwórz (opcjonalnie)', 'in', 'https://…');
    else if (b.t === 'linia') h += '<div class="mut" style="font-size:12px">Cienka linia oddzielająca sekcje.</div>';
    h += '</div></div>';
  }
  if (!ED.blocks.length) h = '<div class="empty" style="padding:24px">Brak bloków – dodaj pierwszy poniżej.</div>';
  if (ED.typ !== 'potwierdzenie' && !hasBtn) h += '<div class="warn">' + ic('alert', 15) + ' Brak przycisku – firma nie będzie mogła wypełnić formularza. Dodaj blok „Przycisk”.</div>';
  el.innerHTML = h;
  var tas = el.querySelectorAll('textarea.auto');
  for (var j = 0; j < tas.length; j++) window.grow(tas[j]);
}
window.grow = function (t) { t.style.height = 'auto'; t.style.height = (t.scrollHeight + 2) + 'px'; };
window.bSet = function (i, f, v) { if (ED.blocks[i]) { ED.blocks[i][f] = v; window.edChange(); } };
window.bAdd = function (t) {
  var b = JSON.parse(JSON.stringify(BT[t].d)); b.t = t;
  var pos = ED.blocks.length;
  for (var i = ED.blocks.length - 1; i >= 0; i--) if (ED.blocks[i].t === 'przycisk' && t !== 'przycisk') { pos = i; break; } // wstaw przed przyciskiem
  ED.blocks.splice(pos, 0, b);
  renderBlocks(); preview();
  var el = $('blk' + pos); if (el) { el.scrollIntoView({ behavior: 'smooth', block: 'center' }); var f = el.querySelector('textarea,input'); if (f) f.focus(); el.className += ' flash'; }
};
window.bDel = function (i) { ED.blocks.splice(i, 1); renderBlocks(); preview(); };
window.bDup = function (i) { ED.blocks.splice(i + 1, 0, JSON.parse(JSON.stringify(ED.blocks[i]))); renderBlocks(); preview(); };
window.bMove = function (i, d) {
  var j = i + d; if (j < 0 || j >= ED.blocks.length) return;
  var x = ED.blocks[i]; ED.blocks[i] = ED.blocks[j]; ED.blocks[j] = x;
  renderBlocks(); preview();
};
var DRAG = -1;
window.bDragStart = function (e, i) {
  var tg = e.target;
  if (!tg || !tg.className || String(tg.className).indexOf('grip') < 0) { return; }
  DRAG = i; e.dataTransfer.effectAllowed = 'move';
  try { e.dataTransfer.setData('text/plain', String(i)); } catch (x) {}
  try { e.dataTransfer.setDragImage($('blk' + i), 30, 20); } catch (x) {}
  setTimeout(function () { var el = $('blk' + i); if (el) el.className += ' drag'; }, 0);
};
window.bDragOver = function (e, i) { if (DRAG < 0) return; e.preventDefault(); var el = $('blk' + i); if (el && el.className.indexOf(' over') < 0 && i !== DRAG) el.className += ' over'; };
window.bDragLeave = function (i) { var el = $('blk' + i); if (el) el.className = el.className.replace(' over', ''); };
window.bDrop = function (e, i) {
  e.preventDefault();
  if (DRAG < 0 || DRAG === i) return;
  var x = ED.blocks.splice(DRAG, 1)[0];
  ED.blocks.splice(i, 0, x);
  DRAG = -1; renderBlocks(); preview();
};
window.bDragEnd = function () { DRAG = -1; renderBlocks(); };
function varChip(v) { return '<span onmousedown="event.preventDefault()" onclick="window.insVar(\'' + v + '\')" title="Wstaw w miejsce kursora">' + v + '</span>'; }
window.insVar = function (v) {
  var ta = window.lastF;
  if (!ta || !document.body.contains(ta)) { toast('Kliknij najpierw w pole, do którego chcesz wstawić', 'err'); return; }
  var s = ta.selectionStart || 0, e = ta.selectionEnd || 0;
  ta.value = ta.value.slice(0, s) + v + ta.value.slice(e);
  ta.focus(); ta.selectionStart = ta.selectionEnd = s + v.length;
  var ev = document.createEvent('Event'); ev.initEvent('input', true, true); ta.dispatchEvent(ev);
};
window.pickTyp = function (t) { ED.typ = t; renderEd(); };
window.edChange = function () { clearTimeout(ED.timer); ED.timer = setTimeout(preview, 350); };
function tplData() { return { branza_id: ED.branza, typ: ED.typ, temat: val('t-temat'), bloki: ED.blocks }; }
function preview() {
  api('podglad', tplData(), function (r) {
    if ($('pv-s')) $('pv-s').textContent = r.temat;
    var f = $('pv'); if (f) f.srcdoc = r.html;
  });
}
window.tplSave = function (btn) {
  var d = tplData(); busy(btn, true);
  api('szablon_zapisz', d, function (r) {
    busy(btn, false); toast('Mail zapisany');
    ED.data[ED.typ] = { temat: d.temat, bloki: JSON.parse(JSON.stringify(d.bloki)), updated_at: r.updated_at };
    if ($('t-saved')) $('t-saved').textContent = 'zapisano ' + fd(r.updated_at);
  }, function () { busy(btn, false); });
};
window.tplReset = function () {
  if (!sure('Wstawić gotowy szablon?', 'Obecne bloki zostaną zastąpione. Zapisze się dopiero po kliknięciu „Zapisz mail”.', 'Wstaw', function () { window.tplReset(); })) return;
  api('szablon_domyslny', { branza_id: ED.branza, typ: ED.typ }, function (r) {
    $('t-temat').value = r.t.temat; ED.blocks = r.t.bloki || [];
    renderBlocks(); preview(); toast('Wstawiono gotowy szablon – kliknij „Zapisz mail”');
  });
};
window.tplTest = function (btn) {
  var d = tplData(); d.to = val('t-to'); ED.testTo = d.to;
  busy(btn, true);
  api('test', d, function (r) { busy(btn, false); toast('Test wysłany na ' + r.to); }, function () { busy(btn, false); });
};
window.branzaSave = function () {
  api('branza_zapisz', { id: ED.branza, nazwa: val('br-n'), aktywna: $('br-a').checked ? 1 : 0 }, function () { toast('Zapisano branżę'); loadBranze(renderBlist); });
};
window.branzaAdd = function () {
  window.ask('Nowa branża', 'Podaj nazwę – dostanie od razu gotowe maile do dopasowania.', 'Dodaj', function (n) { if (!n || !n.trim()) return; window.branzaAddGo(n.trim()); }, { input: '', ph: 'np. Stacje kontroli pojazdów' });
};
window.branzaAddGo = function (n) {
  api('branza_zapisz', { nazwa: n, aktywna: 1 }, function (r) { ED.branza = r.id; toast('Dodano branżę z gotowymi mailami – dopasuj je'); loadBranze(function () { renderBlist(); loadTpl(); }); });
};
window.branzaDel = function () {
  if (!sure('Usunąć branżę?', 'Branża zniknie razem z jej mailami. Firmy z tej branży trzeba najpierw przenieść.', 'Usuń', function () { window.branzaDel(); }, true)) return;
  api('branza_usun', { id: ED.branza }, function () { ED.branza = 0; toast('Usunięto'); viewMaile(); });
};

// ========== ZGŁOSZENIA ==========
// ---------- ikonki (zamiast emoji) ----------
var ICO = {
  phone: '<path d="M22 16.9v3a2 2 0 0 1-2.2 2 19.8 19.8 0 0 1-8.6-3.1 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.1 4.2 2 2 0 0 1 4.1 2h3a2 2 0 0 1 2 1.7c.1.9.4 1.8.7 2.7a2 2 0 0 1-.5 2.1L8 9.8a16 16 0 0 0 6 6l1.3-1.3a2 2 0 0 1 2.1-.4c.9.3 1.8.6 2.7.7a2 2 0 0 1 1.7 2z"/>',
  mail: '<rect x="3" y="5" width="18" height="14" rx="2"/><path d="m3 7 9 6 9-6"/>',
  cal: '<rect x="3" y="4" width="18" height="18" rx="2"/><path d="M16 2v4M8 2v4M3 10h18"/>',
  edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>',
  trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
  send: '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
  img: '<rect x="3" y="3" width="18" height="18" rx="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-5-5L5 21"/>',
  back: '<path d="M19 12H5M12 19l-7-7 7-7"/>',
  eye: '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8S1 12 1 12z"/><circle cx="12" cy="12" r="3"/>',
  click: '<path d="m9 9 5 12 1.8-5.2L21 14z"/><path d="M7.2 2.2 8 5.1M5.1 8l-2.9-.8M14 4.1 12 6M6 12l-1.9 2"/>',
  note: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M8 13h8M8 17h5"/>',
  chat: '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
  moon: '<path d="M21 12.8A9 9 0 1 1 11.2 3a7 7 0 0 0 9.8 9.8z"/>',
  alert: '<path d="M10.3 3.9 1.8 18a2 2 0 0 0 1.7 3h17a2 2 0 0 0 1.7-3L13.7 3.9a2 2 0 0 0-3.4 0zM12 9v4M12 17h.01"/>',
  check: '<path d="M20 6 9 17l-5-5"/>', download: '<path d="M12 3v12M7 10l5 5 5-5M5 21h14"/>', upload: '<path d="M12 21V9M7 14l5-5 5 5M5 3h14"/>',
  up: '<path d="m18 15-6-6-6 6"/>', down: '<path d="m6 9 6 6 6-6"/>', x: '<path d="M18 6 6 18M6 6l12 12"/>', link: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>'
};
function ic(n, sz) { sz = sz || 16; return '<svg class="ico" width="' + sz + '" height="' + sz + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' + (ICO[n] || '') + '</svg>'; }
window.ic = ic;

// ========== ZGŁOSZENIA ==========
var ZG = { rows: [], f: 'wszystkie', sort: 'nowe', q: '', sel: {}, open: 0 };
function zgToday() { var t = new Date(); return t.getFullYear() + '-' + ('0' + (t.getMonth() + 1)).slice(-2) + '-' + ('0' + t.getDate()).slice(-2); }
function zgDate(d) { var p = String(d || '').split('-'); return p.length === 3 ? p[2] + '.' + p[1] + '.' + p[0] : (d || ''); }
window.zgPlus = function (days) { var t = new Date(); t.setDate(t.getDate() + days); return t.getFullYear() + '-' + ('0' + (t.getMonth() + 1)).slice(-2) + '-' + ('0' + t.getDate()).slice(-2); };
function zgTel(t) { return String(t || '').replace(/[^0-9+]/g, ''); }
function viewZgloszenia() {
  if (ZG.open) { zgOpen(ZG.open); return; }
  api('zgloszenia', {}, function (r) {
    ZG.rows = r.rows; ZG.sel = {}; ZG.td = zgToday();
    var n = 0; for (var i = 0; i < r.rows.length; i++) { var z0 = r.rows[i]; if (z0.status === 'nowe' || (z0.kontakt_data && z0.kontakt_data <= ZG.td && z0.status !== 'klient' && z0.status !== 'odrzucone')) n++; }
    BADGE = n; renderNav(BADGE);
    var so = [['nowe', 'Najnowsze'], ['kontakt', 'Najbliższy kontakt'], ['stare', 'Najstarsze'], ['firma', 'Firma A–Z'], ['status', 'Wg statusu']], sh = '';
    for (i = 0; i < so.length; i++) sh += '<option value="' + so[i][0] + '"' + (ZG.sort === so[i][0] ? ' selected' : '') + '>' + so[i][1] + '</option>';
    var h = '<div class="head"><div><h1>Zgłoszenia</h1><p>Firmy, które chcą bezpłatnej konsultacji. Kliknij zgłoszenie, żeby otworzyć szczegóły.</p></div></div>' +
      '<div class="card" id="zg-land" style="margin-bottom:14px;padding:16px 18px"></div>' +
      '<div class="chips" id="zg-chips" style="margin-bottom:12px"></div>' +
      '<div class="row" style="gap:8px;margin-bottom:12px;flex-wrap:wrap"><input id="zg-q" placeholder="Szukaj: firma, osoba, telefon, e-mail…" style="flex:1;min-width:200px" value="' + esc(ZG.q) + '" oninput="window.zgQ(this.value)">' +
      '<select id="zg-s" style="width:auto" onchange="window.zgSort(this.value)">' + sh + '</select></div>' +
      '<div class="bulk" id="zg-bulk"><b id="zg-n"></b><span style="flex:1"></span><a class="btn sm red" onclick="window.zgDelSel()">' + ic('trash', 14) + ' Usuń zaznaczone</a></div>' +
      '<div class="card" id="zg-list"></div>';
    $('main').innerHTML = h;
    zgRender();
    zgLand();
  });
}
var ZL = { p: 'd7' };
function zgLand() {
  api('landing_stats', {}, function (r) { ZL.r = r; zgLandRender(); });
}
window.zgLandP = function (p) { ZL.p = p; zgLandRender(); };
window.zgLandPush = function (on) { api('landing_push', { on: on ? '1' : '0' }, function (r) { ZL.r.push = r.push; toast(on ? 'Powiadomienia o wejściach włączone' : 'Powiadomienia o wejściach wyłączone'); }); };
function zgLandRender() {
  var el = $('zg-land'); if (!el || !ZL.r) return;
  var d = ZL.r[ZL.p], tabs = [['dzis', 'Dziś'], ['d7', '7 dni'], ['d30', '30 dni']], th = '', i;
  for (i = 0; i < tabs.length; i++) th += '<span class="chip' + (ZL.p === tabs[i][0] ? ' on' : '') + '" onclick="window.zgLandP(\'' + tabs[i][0] + '\')">' + tabs[i][1] + '</span>';
  var kp = [['wejscie', 'Wejścia'], ['formularz', 'Otworzyli formularz'], ['zgloszenia', 'Zgłoszenia'], ['telefon', 'Kliknęli „Zadzwoń”'], ['strona', 'Przeszli na stronę'], ['mail', 'Kliknęli „Mail”']], kh = '';
  for (i = 0; i < kp.length; i++) kh += '<div style="flex:1;min-width:110px;background:#F7F5EF;border-radius:12px;padding:10px 12px"><div style="font-size:24px;font-weight:800">' + (d[kp[i][0]] || 0) + '</div><div class="mut" style="font-size:12.5px">' + kp[i][1] + '</div></div>';
  var zr = [], k; for (k in d.zrodla) if (d.zrodla.hasOwnProperty(k)) zr.push(esc(ZRD[k] || k) + ': <b>' + d.zrodla[k] + '</b>');
  var conv = d.wejscie ? Math.min(100, Math.round(d.zgloszenia * 1000 / d.wejscie) / 10) : 0;
  el.innerHTML = '<div class="row" style="justify-content:space-between;align-items:center;flex-wrap:wrap;gap:8px;margin-bottom:10px"><div><b>Landing /start</b> <span class="mut" style="font-size:13px">– ruch z TikToka i social mediów</span></div><div class="chips">' + th + '</div></div>' +
    '<div class="row" style="gap:8px;flex-wrap:wrap">' + kh + '</div>' +
    '<div class="mut" style="font-size:12.5px;margin-top:10px">' + (zr.length ? 'Wejścia wg źródła – ' + zr.join(' · ') + ' · ' : '') + 'Skuteczność: <b>' + conv + '%</b> wejść kończy się zgłoszeniem' +
    ' · <label style="display:inline-flex;align-items:center;gap:6px;cursor:pointer"><input type="checkbox" style="width:auto" ' + (ZL.r.push === '1' ? 'checked' : '') + ' onchange="window.zgLandPush(this.checked)"> powiadomienie na telefon przy każdym wejściu</label>' +
    ' · <a class="ilink" href="start/?z=tiktok" target="_blank">otwórz landing</a></div>';
}
function zgIsOpen(z) { return z.kontakt_data && z.status !== 'klient' && z.status !== 'odrzucone'; }
var ZRD = { tiktok: 'TikTok', instagram: 'Instagram', facebook: 'Facebook', inne: 'Inne' };
function zgRender() {
  var c = { wszystkie: ZG.rows.length }, i, z, nDue = 0, nPlan = 0;
  for (i = 0; i < ZG.rows.length; i++) { z = ZG.rows[i]; c[z.status] = (c[z.status] || 0) + 1; if (zgIsOpen(z)) { nPlan++; if (z.kontakt_data <= ZG.td) nDue++; } }
  var ch = '<span class="chip' + (ZG.f === 'wszystkie' ? ' on' : '') + '" onclick="window.zgF(\'wszystkie\')">Wszystkie <b>' + c.wszystkie + '</b></span>' +
    '<span class="chip' + (ZG.f === 'dzis' ? ' on' : '') + '" style="border-color:#C5A059" onclick="window.zgF(\'dzis\')">' + ic('cal', 14) + ' Do kontaktu dziś <b>' + nDue + '</b></span>' +
    '<span class="chip' + (ZG.f === 'plan' ? ' on' : '') + '" onclick="window.zgF(\'plan\')">Zaplanowane <b>' + nPlan + '</b></span>';
  for (var k in ZS) if (ZS.hasOwnProperty(k)) ch += '<span class="chip' + (ZG.f === k ? ' on' : '') + '" onclick="window.zgF(\'' + k + '\')">' + ZS[k] + ' <b>' + (c[k] || 0) + '</b></span>';
  $('zg-chips').innerHTML = ch;
  var q = ZG.q.toLowerCase(), list = [];
  for (i = 0; i < ZG.rows.length; i++) {
    z = ZG.rows[i];
    if (ZG.f === 'dzis') { if (!zgIsOpen(z) || z.kontakt_data > ZG.td) continue; }
    else if (ZG.f === 'plan') { if (!zgIsOpen(z)) continue; }
    else if (ZG.f !== 'wszystkie' && z.status !== ZG.f) continue;
    if (q && (z.firma + ' ' + z.osoba + ' ' + z.telefon + ' ' + z.email + ' ' + (z.notatka || '')).toLowerCase().indexOf(q) < 0) continue;
    list.push(z);
  }
  var ord = { nowe: 0, zadzwonione: 1, umowione: 2, klient: 3, odrzucone: 4 };
  list.sort(function (a, b) {
    if (ZG.sort === 'kontakt' || ZG.f === 'dzis' || ZG.f === 'plan') return String(a.kontakt_data || '9999').localeCompare(String(b.kontakt_data || '9999')) || (b.id - a.id);
    if (ZG.sort === 'stare') return a.id - b.id;
    if (ZG.sort === 'firma') return String(a.firma).localeCompare(String(b.firma), 'pl');
    if (ZG.sort === 'status') return ((ord[a.status] || 0) - (ord[b.status] || 0)) || (b.id - a.id);
    return b.id - a.id;
  });
  if (!list.length) { $('zg-list').innerHTML = '<div class="empty">' + (ZG.rows.length ? 'Brak zgłoszeń w tym filtrze.' : 'Brak zgłoszeń. Pojawią się tu, gdy firma kliknie przycisk w mailu i wyśle formularz.') + '</div>'; zgBulk(); return; }
  var h = '<table class="tbl resp zgt"><thead><tr><th style="width:36px"></th><th>Firma</th><th class="hm">Telefon</th><th>Status</th><th>Następny kontakt</th><th class="hm">Zgłoszenie</th></tr></thead><tbody>';
  for (i = 0; i < list.length; i++) {
    z = list[i];
    var due = zgIsOpen(z) && z.kontakt_data <= ZG.td;
    h += '<tr class="click" onclick="window.zgOpen(' + z.id + ')"><td class="c0" onclick="event.stopPropagation()"><input type="checkbox" ' + (ZG.sel[z.id] ? 'checked' : '') + ' onchange="window.zgSel(' + z.id + ',this.checked)"></td>' +
      '<td><div class="b1">' + esc(z.firma) + (z.zrodlo ? ' <span class="pill gold" style="font-size:11px;padding:2px 8px">' + esc(ZRD[z.zrodlo] || z.zrodlo) + '</span>' : '') + '</div><div class="mut">' + esc(z.osoba || z.email || '') + (z.branza ? ' · ' + esc(z.branza) : '') + '</div></td>' +
      '<td class="hm">' + (z.telefon ? '<a class="ilink" href="tel:' + esc(zgTel(z.telefon)) + '" onclick="event.stopPropagation()">' + ic('phone', 14) + ' ' + esc(z.telefon) + '</a>' : '<span class="mut">—</span>') + '</td>' +
      '<td><span class="pill z-' + esc(z.status) + '">' + esc(ZS[z.status] || z.status) + '</span></td>' +
      '<td>' + (z.kontakt_data ? '<span class="ilink' + (due ? ' due' : '') + '">' + ic('cal', 14) + ' ' + esc(zgDate(z.kontakt_data)) + '</span>' + (z.kontakt_co ? '<div class="mut" style="font-size:12.5px">' + esc(z.kontakt_co) + '</div>' : '') : '<span class="mut">—</span>') + '</td>' +
      '<td class="hm mut">' + fd(z.created_at) + '</td></tr>';
  }
  $('zg-list').innerHTML = h + '</tbody></table>';
  zgBulk();
}
function zgIds() { var a = []; for (var k in ZG.sel) if (ZG.sel.hasOwnProperty(k)) a.push(+k); return a; }
function zgBulk() { var n = zgIds().length, b = $('zg-bulk'); if (!b) return; b.className = 'bulk' + (n ? ' on' : ''); $('zg-n').textContent = 'Zaznaczono: ' + n; }
window.zgSel = function (id, on) { if (on) ZG.sel[id] = 1; else delete ZG.sel[id]; zgBulk(); };
window.zgF = function (f) { ZG.f = f; zgRender(); };
window.zgSort = function (v) { ZG.sort = v; zgRender(); };
window.zgQ = function (v) { ZG.q = v; clearTimeout(ZG.t); ZG.t = setTimeout(zgRender, 200); };
window.zgDelSel = function () {
  var ids = zgIds(); if (!ids.length) return;
  window.ask('Usunąć zaznaczone?', 'Usunę <b>' + ids.length + '</b> zgłoszeń. Firmy zostają w Firmach.', 'Usuń', function () {
    api('zgloszenie_usun', { ids: ids }, function () { toast('Usunięto: ' + ids.length); viewZgloszenia(); });
  }, { danger: true });
};

// ---------- szczegóły zgłoszenia ----------
var ZD = null;
window.zgOpen = function (id) {
  ZG.open = +id;
  api('zgloszenie_get', { id: id }, function (r) { ZD = r; zgDetail(); }, function () { ZG.open = 0; });
};
window.zgBack = function () { ZG.open = 0; ZD = null; viewZgloszenia(); };
function zgDetail() {
  var z = ZD.z, f = ZD.firma, td = zgToday();
  var opts = ''; for (var k in ZS) if (ZS.hasOwnProperty(k)) opts += '<option value="' + k + '"' + (z.status === k ? ' selected' : '') + '>' + ZS[k] + '</option>';
  var due = zgIsOpen(z) && z.kontakt_data <= td;
  var h = '<div class="head"><div><a class="btn sm" onclick="window.zgBack()">' + ic('back', 14) + ' Wszystkie zgłoszenia</a>' +
    '<h1 style="margin-top:14px">' + esc(z.firma) + '</h1><p>' + esc(z.osoba || '') + (z.branza ? (z.osoba ? ' · ' : '') + esc(z.branza) : '') + ' · zgłoszenie ' + fd(z.created_at) + '</p></div>' +
    '<div class="row" style="gap:8px">' + (z.telefon ? '<a class="btn dark" href="tel:' + esc(zgTel(z.telefon)) + '">' + ic('phone') + ' ' + esc(z.telefon) + '</a>' : '') +
    '<a class="btn gold" onclick="window.wiadNew()">' + ic('send') + ' Napisz wiadomość</a></div></div>' +
    '<div class="zgd">' +
    // lewa kolumna
    '<div>' +
      '<div class="card pad"><h2 class="h2">Po rozmowie</h2>' +
        '<textarea id="zd-t" rows="4" placeholder="Co ustaliliście? Zapisze się z datą i godziną."></textarea>' +
        '<div class="g2f"><div><label class="f">Status</label><select id="zd-s">' + opts + '</select></div>' +
        '<div><label class="f">Następny kontakt</label><input id="zd-d" type="date" value="' + esc(z.kontakt_data || '') + '"></div></div>' +
        '<div class="row" style="gap:6px;margin-top:6px;flex-wrap:wrap"><a class="btn sm" onclick="window.zdPlus(1)">jutro</a><a class="btn sm" onclick="window.zdPlus(3)">za 3 dni</a><a class="btn sm" onclick="window.zdPlus(7)">za tydzień</a><a class="btn sm" onclick="window.zdPlus(14)">za 2 tygodnie</a><a class="btn sm" onclick="document.getElementById(\'zd-d\').value=\'\'">brak</a></div>' +
        '<label class="f">Co wtedy zrobić?</label><input id="zd-c" value="' + esc(z.kontakt_co || '') + '" placeholder="np. Zadzwonić i pokazać makietę">' +
        '<div class="row" style="margin-top:12px"><a class="btn dark" onclick="window.zdSave(this)">Zapisz</a><span class="hint" style="margin:0">Rano w dniu kontaktu dostaniesz push na telefon.</span></div></div>' +
      '<div class="card pad" style="margin-top:14px"><h2 class="h2">' + ic('note') + ' Historia i notatki</h2>' + (z.notatka ? zgNotes(z.notatka) : '<div class="mut">Brak notatek.</div>') + '</div>' +
    '</div>' +
    // prawa kolumna
    '<div>' +
      '<div class="card pad zgnext' + (due ? ' due' : '') + '"><div class="mut" style="font-size:12px;font-weight:600">NASTĘPNY KONTAKT</div>' +
        (z.kontakt_data ? '<div class="big">' + ic('cal', 18) + ' ' + esc(zgDate(z.kontakt_data)) + (due ? ' <span class="pill st-blad sm">dziś / zaległe</span>' : '') + '</div><div>' + esc(z.kontakt_co || '') + '</div>' : '<div class="mut" style="margin-top:6px">Nie zaplanowano.</div>') + '</div>' +
      '<div class="card pad" style="margin-top:14px"><h2 class="h2">Dane</h2>' +
        '<div class="kv"><span>Status</span><b><span class="pill z-' + esc(z.status) + '">' + esc(ZS[z.status] || z.status) + '</span></b></div>' +
        '<div class="kv"><span>E-mail</span><b>' + (z.email ? '<a class="ilink" href="mailto:' + esc(z.email) + '">' + ic('mail', 14) + ' ' + esc(z.email) + '</a>' : '—') + '</b></div>' +
        '<div class="kv"><span>Telefon</span><b>' + (z.telefon ? '<a class="ilink" href="tel:' + esc(zgTel(z.telefon)) + '">' + ic('phone', 14) + ' ' + esc(z.telefon) + '</a>' : '—') + '</b></div>' +
        (z.kiedy ? '<div class="kv"><span>Kiedy dzwonić</span><b>' + esc(z.kiedy) + '</b></div>' : '') +
        (f && f.www ? '<div class="kv"><span>Strona</span><b><a class="ilink" href="' + esc(f.www) + '" target="_blank" rel="noopener">' + ic('link', 14) + ' ' + esc(String(f.www).replace(/^https?:\/\//, '')) + '</a></b></div>' : '') +
        '<div class="kv col"><span>Czym się zajmują</span><b>' + esc(z.czym || '—') + '</b></div>' +
        '<div class="kv col"><span>Co chcą usprawnić</span><b>' + esc(z.co || '—') + '</b></div>' +
        '<div class="row" style="gap:6px;margin-top:12px"><a class="btn sm" onclick="window.zgEdit(' + z.id + ')">' + ic('edit', 14) + ' Edytuj dane</a><a class="btn sm red" onclick="window.zgDel(' + z.id + ')">' + ic('trash', 14) + ' Usuń</a></div></div>' +
      '<div class="card pad" style="margin-top:14px"><h2 class="h2">' + ic('mail') + ' Wysłane wiadomości</h2>' + wiadList() + '</div>' +
    '</div></div>';
  $('main').innerHTML = h;
}
function zgNotes(t) {
  var parts = String(t).split(/\n\n(?=\d{2}\.\d{2}\.\d{4} \d{2}:\d{2}\n)/), h = '';
  for (var i = 0; i < parts.length; i++) {
    var m = parts[i].match(/^(\d{2}\.\d{2}\.\d{4} \d{2}:\d{2})\n([\s\S]*)$/);
    h += '<div class="zgn">' + (m ? '<div class="zgn-t">' + esc(m[1]) + '</div><div class="zgn-x">' + esc(m[2]) + '</div>' : '<div class="zgn-x">' + esc(parts[i]) + '</div>') + '</div>';
  }
  return h;
}
function wiadList() {
  var w = ZD.wiad || [];
  if (!w.length) return '<div class="mut">Jeszcze nic nie wysłano. Kliknij „Napisz wiadomość” – zobaczysz, kiedy klient ją otworzy.</div>';
  var h = '';
  for (var i = 0; i < w.length; i++) {
    var x = w[i];
    if (!x.wyslano_at) { h += '<div class="wl"><div class="b1">' + esc(x.temat || '(bez tematu)') + ' <span class="pill gold sm" style="margin-left:4px">szkic</span></div><div class="mut">Zapisany, jeszcze nie wysłany</div>' +
      '<div class="row" style="gap:6px;margin-top:6px"><a class="btn sm dark" onclick="window.wiadEdit(' + x.id + ')">' + ic('edit', 14) + ' Otwórz i wyślij</a><a class="btn sm red" onclick="window.wiadDel(' + x.id + ')">' + ic('trash', 14) + ' Usuń szkic</a></div></div>'; continue; }
    h += '<div class="wl"><div class="b1">' + esc(x.temat) + '</div><div class="mut">Wysłano ' + fd(x.wyslano_at) + '</div>' +
      '<div class="row" style="gap:8px;margin-top:6px;flex-wrap:wrap">' +
      (+x.otwarcia ? '<span class="pill st-zainteresowany sm">' + ic('eye', 12) + ' otworzył ' + x.otwarcia + '×</span><span class="mut" style="font-size:12.5px">pierwszy raz ' + fd(x.otwarto_at) + (x.otwarto_last && x.otwarto_last !== x.otwarto_at ? ', ostatnio ' + fd(x.otwarto_last) : '') + '</span>' : '<span class="pill sm">jeszcze nie otworzył</span>') +
      (+x.kliki ? '<span class="pill st-otworzyl sm">' + ic('click', 12) + ' kliknął link ' + fd(x.klik_at) + '</span>' : '') +
      '</div></div>';
  }
  return h;
}
window.zdPlus = function (d) { $('zd-d').value = window.zgPlus(d); };
window.zdSave = function (btn) {
  busy(btn, true);
  api('zgloszenie_wpis', { id: ZD.z.id, tekst: val('zd-t'), status: val('zd-s'), kontakt_data: val('zd-d'), kontakt_co: val('zd-c') }, function () { toast('Zapisano'); window.zgOpen(ZD.z.id); }, function () { busy(btn, false); });
};
function zgFind(id) { if (ZD && +ZD.z.id === +id) return ZD.z; for (var i = 0; i < ZG.rows.length; i++) if (+ZG.rows[i].id === +id) return ZG.rows[i]; return null; }
window.zgEdit = function (id) {
  var z = zgFind(id); if (!z) return;
  var f = function (k, l, ta) { return '<label class="f">' + l + '</label>' + (ta ? '<textarea id="ze-' + k + '" rows="3">' + esc(z[k] || '') + '</textarea>' : '<input id="ze-' + k + '" value="' + esc(z[k] || '') + '">'); };
  var b = f('firma', 'Firma') + '<div class="g2f"><div>' + f('osoba', 'Imię i nazwisko') + '</div><div>' + f('telefon', 'Telefon') + '</div></div>' + f('email', 'E-mail') +
    f('czym', 'Czym się zajmują', true) + f('co', 'Co chcą usprawnić', true) + f('kiedy', 'Kiedy dzwonić') + f('notatka', 'Notatki (cała historia)', true);
  window.openModal('Edytuj dane zgłoszenia', b, '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn dark" onclick="window.zgEditSave(' + id + ',this)">Zapisz</a>');
};
window.zgEditSave = function (id, btn) {
  var z = zgFind(id), d = { id: id, status: z.status, kontakt_data: z.kontakt_data || '', kontakt_co: z.kontakt_co || '' }, ks = ['firma', 'osoba', 'telefon', 'email', 'czym', 'co', 'kiedy', 'notatka'];
  for (var i = 0; i < ks.length; i++) d[ks[i]] = val('ze-' + ks[i]);
  busy(btn, true);
  api('zgloszenie_edytuj', d, function () { window.closeModal(); toast('Zapisano'); viewZgloszenia(); }, function () { busy(btn, false); });
};
window.zgDel = function (id) {
  var z = zgFind(id);
  window.ask('Usunąć zgłoszenie?', 'Usunę zgłoszenie <b>' + esc(z ? z.firma : '') + '</b>. Firma zostaje w Firmach.', 'Usuń', function () {
    api('zgloszenie_usun', { ids: [id] }, function () { toast('Usunięto'); ZG.open = 0; viewZgloszenia(); });
  }, { danger: true });
};

// ---------- kreator wiadomości 1:1 ----------
var WD = { bl: [] };
window.wiadNew = function () {
  var z = ZD.z;
  WD = { temat: 'Po naszej rozmowie – ' + z.firma, bl: [{ t: 'tekst', text: 'Dzień dobry,\n\ndziękuję za rozmowę. ' }] };
  wiadModal();
};
window.wiadEdit = function (id) {
  var x = null; for (var i = 0; i < ZD.wiad.length; i++) if (+ZD.wiad[i].id === +id) x = ZD.wiad[i];
  if (!x) return;
  var bl = []; try { bl = JSON.parse(x.bloki) || []; } catch (e) {}
  WD = { id: +x.id, temat: x.temat, bl: bl.length ? bl : [{ t: 'tekst', text: '' }] };
  wiadModal();
};
window.wiadDel = function (id) {
  window.ask('Usunąć szkic?', 'Szkic zostanie usunięty.', 'Usuń', function () { api('wiad_usun', { id: id }, function () { toast('Usunięto'); window.zgOpen(ZD.z.id); }); }, { danger: true });
};
window.wdSave = function (btn) {
  busy(btn, true);
  api('wiad_szkic', wdData(), function (r) { busy(btn, false); WD.id = r.id; toast('Szkic zapisany – wyślesz go później ze zgłoszenia'); window.closeModal(); window.zgOpen(ZD.z.id); }, function () { busy(btn, false); });
};
function wiadModal() {
  var z = ZD.z;
  var b = '<div class="wd"><div class="wd-l">' +
    '<div class="mut" style="margin:4px 0 8px">Do: <b>' + esc(z.email || 'brak e-maila') + '</b> · podpis i logo dodadzą się same</div>' +
    '<label class="f">Temat</label><input id="wd-temat" value="' + esc(WD.temat) + '" oninput="window.wdTemat(this.value)">' +
    '<label class="f">Treść</label><div id="wd-bl"></div>' +
    '<div class="row" style="gap:6px;margin-top:8px;flex-wrap:wrap"><a class="btn sm" onclick="window.wdAdd(\'tytul\')"><b>H</b> Nagłówek</a><a class="btn sm" onclick="window.wdAdd(\'tekst\')">' + ic('note', 14) + ' Akapit</a><a class="btn sm" onclick="window.wdAdd(\'punkty\')">• Lista</a><a class="btn sm" onclick="window.wdAdd(\'ramka\')">▍ Ramka</a><a class="btn sm" onclick="window.wdAdd(\'linia\')">— Linia</a>' +
    '<label class="btn sm" style="cursor:pointer">' + ic('img', 14) + ' Zdjęcia<input type="file" accept="image/*" multiple style="display:none" onchange="window.wdFiles(this)"></label>' +
    '<a class="btn sm" onclick="window.wdAdd(\'przycisk\')">' + ic('link', 14) + ' Przycisk z linkiem</a></div>' +
    '<p class="hint">Zdjęcia zmniejszą się same. Otwarcie i kliknięcie w przycisk zobaczysz w zgłoszeniu (i dostaniesz push).</p></div>' +
    '<div class="wd-r"><div class="mut" style="font-size:12px;font-weight:600;margin-bottom:6px">PODGLĄD</div><iframe id="wd-prev" title="Podgląd"></iframe></div></div>';
  window.openModal('Nowa wiadomość do ' + z.firma, b, '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn" onclick="window.wdSave(this)">Zapisz szkic</a><a class="btn" onclick="window.wdTest(this)">Wyślij test do siebie</a><a class="btn dark" onclick="window.wdSend(this)">' + ic('send', 14) + ' Wyślij</a>');
  $('mod').style.maxWidth = '1100px';
  wdRender(); wiadPrev();
}
function wdRender() {
  var h = '';
  for (var i = 0; i < WD.bl.length; i++) {
    var b = WD.bl[i];
    var tools = '<span class="wdt"><a title="W górę" onclick="window.wdMv(' + i + ',-1)">' + ic('up', 14) + '</a><a title="W dół" onclick="window.wdMv(' + i + ',1)">' + ic('down', 14) + '</a><a title="Usuń" onclick="window.wdDel(' + i + ')">' + ic('x', 14) + '</a></span>';
    var lab = '<div class="wdl">' + ({ tekst: 'Akapit', tytul: 'Nagłówek', punkty: 'Lista punktów', ramka: 'Ramka', obrazek: 'Zdjęcie', przycisk: 'Przycisk', linia: 'Linia' }[b.t] || '') + '</div>';
    tools = lab + tools;
    if (b.t === 'tekst') h += '<div class="wdb">' + tools + '<textarea rows="4" placeholder="Tekst. Pusta linia = nowy akapit, **tekst** = pogrubienie" oninput="window.wdSet(' + i + ',\'text\',this.value)">' + esc(b.text) + '</textarea></div>';
    else if (b.t === 'tytul') h += '<div class="wdb">' + tools + '<input class="wdh" placeholder="Nagłówek, np. Jak to może wyglądać u Pana" value="' + esc(b.text) + '" oninput="window.wdSet(' + i + ',\'text\',this.value)"></div>';
    else if (b.t === 'punkty') h += '<div class="wdb">' + tools + '<textarea rows="4" placeholder="Jeden punkt w linii" oninput="window.wdSet(' + i + ',\'text\',this.value)">' + esc(b.text) + '</textarea></div>';
    else if (b.t === 'ramka') h += '<div class="wdb">' + tools + '<input placeholder="Tytuł ramki" value="' + esc(b.title || '') + '" oninput="window.wdSet(' + i + ',\'title\',this.value)" style="margin-bottom:6px"><textarea rows="3" placeholder="Tekst w ramce" oninput="window.wdSet(' + i + ',\'text\',this.value)">' + esc(b.text || '') + '</textarea></div>';
    else if (b.t === 'linia') h += '<div class="wdb">' + tools + '<hr style="border:0;border-top:1px solid #E7E4DC;margin:8px 0"></div>';
    else if (b.t === 'obrazek') h += '<div class="wdb img">' + tools + (b.url ? '<img src="' + esc(b.url) + '" alt="">' : '<div class="mut">Wysyłam zdjęcie…</div>') + '</div>';
    else if (b.t === 'przycisk') h += '<div class="wdb">' + tools + '<div class="g2f" style="margin:0"><input placeholder="Tekst przycisku, np. Zobacz makietę" value="' + esc(b.text || '') + '" oninput="window.wdSet(' + i + ',\'text\',this.value)"><input placeholder="https://…" value="' + esc(b.url || '') + '" oninput="window.wdSet(' + i + ',\'url\',this.value)"></div></div>';
  }
  $('wd-bl').innerHTML = h || '<div class="mut">Dodaj tekst albo zdjęcia.</div>';
}
window.wdSet = function (i, k, v) { if (WD.bl[i]) { WD.bl[i][k] = v; wiadPrev(); } };
window.wdTemat = function (v) { WD.temat = v; wiadPrev(); };
window.wdAdd = function (t) { WD.bl.push(t === 'przycisk' ? { t: 'przycisk', text: '', url: '' } : (t === 'ramka' ? { t: 'ramka', title: '', text: '' } : { t: t, text: '' })); wdRender(); var el = document.querySelectorAll('#wd-bl .wdb'), last = el[el.length - 1]; if (last) { var f = last.querySelector('input,textarea'); if (f) f.focus(); } wiadPrev(); };
window.wdDel = function (i) { WD.bl.splice(i, 1); wdRender(); wiadPrev(); };
window.wdMv = function (i, d) { var j = i + d; if (j < 0 || j >= WD.bl.length) return; var x = WD.bl[i]; WD.bl[i] = WD.bl[j]; WD.bl[j] = x; wdRender(); wiadPrev(); };
window.wdFiles = function (inp) {
  var files = inp.files; if (!files || !files.length) return;
  for (var i = 0; i < files.length; i++) (function (file) {
    var blk = { t: 'obrazek', url: '' }; WD.bl.push(blk); wdRender();
    var rd = new FileReader();
    rd.onload = function () {
      var im = new Image();
      im.onload = function () {
        var max = 1200, w = im.width, h = im.height; if (w > max) { h = Math.round(h * max / w); w = max; }
        var cv = document.createElement('canvas'); cv.width = w; cv.height = h;
        var cx = cv.getContext('2d'); cx.fillStyle = '#fff'; cx.fillRect(0, 0, w, h); cx.drawImage(im, 0, 0, w, h);
        api('wiad_upload', { data: cv.toDataURL('image/jpeg', 0.85) }, function (r) { blk.url = r.url; wdRender(); wiadPrev(); }, function () { var k = WD.bl.indexOf(blk); if (k >= 0) WD.bl.splice(k, 1); wdRender(); });
      };
      im.onerror = function () { toast('Nie mogę odczytać zdjęcia', 'err'); var k = WD.bl.indexOf(blk); if (k >= 0) WD.bl.splice(k, 1); wdRender(); };
      im.src = rd.result;
    };
    rd.readAsDataURL(file);
  })(files[i]);
  inp.value = '';
};
function wdData() { return { zgloszenie_id: ZD.z.id, id: WD.id || 0, temat: WD.temat, bloki: WD.bl }; }
var wpT = null;
function wiadPrev() {
  clearTimeout(wpT);
  wpT = setTimeout(function () {
    var seq = (WD.seq = (WD.seq || 0) + 1);
    api('wiad_podglad', wdData(), function (r) { if (seq !== WD.seq) return; var f = $('wd-prev'); if (!f) return; var d = f.contentDocument || f.contentWindow.document; d.open(); d.write(r.html); d.close(); }, function () {});
  }, 400);
}
window.wiadPrev = wiadPrev;
window.wdSend = function (btn) {
  if (!ZD.z.email) { toast('Zgłoszenie nie ma e-maila – dodaj go w „Edytuj dane”', 'err'); return; }
  busy(btn, true);
  api('wiad_wyslij', wdData(), function () { window.closeModal(); toast('Wysłano'); window.zgOpen(ZD.z.id); }, function () { busy(btn, false); });
};
window.wdTest = function (btn) {
  var d = wdData(); d.temat = '[TEST] ' + d.temat;
  busy(btn, true);
  api('wiad_test', d, function (r) { busy(btn, false); toast('Test wysłany na ' + r.to); }, function () { busy(btn, false); });
};

// ========== USTAWIENIA ==========
function viewUstawienia() {
  api('ustawienia', {}, function (r) {
    var u = r.u;
    function inp(k, label, type, hint) { return '<div><label class="f">' + label + '</label><input id="u-' + k + '" type="' + (type || 'text') + '" value="' + esc(u[k]) + '">' + (hint ? '<div class="hint">' + hint + '</div>' : '') + '</div>'; }
    var h = '<div class="head"><div><h1>Ustawienia</h1><p>Wysyłka, nadawca, powiadomienia</p></div><a class="btn dark" onclick="window.uSave(this)">Zapisz ustawienia</a></div><div class="grid g2">';
    h += '<div class="card pad"><h2 style="font-size:16px">Automat wysyłki</h2>' +
      '<label class="row" style="margin-top:12px;cursor:pointer"><span class="sw"><input type="checkbox" id="u-auto_on" ' + (u.auto_on === '1' ? 'checked' : '') + '><i></i></span><b>Wysyłaj automatycznie (pn–pt, bez świąt)</b></label>' +
      '<div class="g2f">' + inp('limit_dzienny', 'Limit łączny dziennie (wszystkie branże)', 'number', 'Bezpiecznik – więcej nie wyjdzie, nawet jeśli branże mają wyższe limity') +
      inp('godz_od', 'Wysyłaj od godziny', 'number') + inp('godz_do', 'Do godziny', 'number') + inp('dni_przypomnienia', 'Przypomnienie po (dniach)', 'number') + '</div>' +
      '<label class="row" style="margin-top:14px;cursor:pointer"><span class="sw"><input type="checkbox" id="u-porcja_auto" ' + (u.porcja_auto !== '0' ? 'checked' : '') + ' onchange="document.getElementById(\'u-porcja-w\').style.display=this.checked?\'none\':\'block\'"><i></i></span><b>Rozkładaj maile równo do końca godzin wysyłki</b></label>' +
      '<div id="u-porcja-w" style="display:' + (u.porcja_auto !== '0' ? 'none' : 'block') + '">' + inp('porcja', 'Porcja na 1 uruchomienie (co 15 min)', 'number') + '</div>' +
      '<label class="f" style="margin-top:16px">Limity branż (dziennie)</label><div id="u-branze" class="mut">Ładowanie…</div>' +
      '<label class="row" style="margin-top:14px;cursor:pointer"><span class="sw"><input type="checkbox" id="u-przyp2_on" ' + (u.przyp2_on !== '0' ? 'checked' : '') + '><i></i></span><b>Ostatnie przypomnienie (drugie)</b></label>' +
      '<div class="g2f">' + inp('dni_przypomnienia2', 'Ile dni po 1. przypomnieniu', 'number') +
      '<div><label class="f">Do kogo</label><select id="u-przyp2_kto"><option value="wszyscy"' + (u.przyp2_kto !== 'otwarte' ? ' selected' : '') + '>Do wszystkich, którzy nie kliknęli</option><option value="otwarte"' + (u.przyp2_kto === 'otwarte' ? ' selected' : '') + '>Tylko do tych, którzy otworzyli maila</option></select></div></div>' +
      '<label class="f">Adres crona (LH.pl → Cron, co 15 minut)</label><input readonly value="wget -q -O - &quot;' + esc(u.cron_url) + '&quot; >/dev/null 2>&1" onclick="this.select()">' +
      '<div class="hint">Ostatnie uruchomienie: ' + fd(u.ostatni_cron) + '</div></div>';
    h += '<div class="card pad"><h2 style="font-size:16px">Skrzynka – zwroty i odpowiedzi</h2>' +
      '<p class="hint" style="margin-top:4px">Panel co 15 min czyta skrzynkę (tylko odczyt, nic nie kasuje). Zwrot „adres nie istnieje” → firma dostaje status <b>Zwrot</b> i nie dostaje przypomnień. Ktoś odpisał → push na telefon, znaczek „odpisał” w Firmach i stop przypomnień. Autoodpowiedzi (urlop, nr zgłoszenia) są pomijane.</p>' +
      '<label class="row" style="margin-top:12px;cursor:pointer"><span class="sw"><input type="checkbox" id="u-imap_on" ' + (u.imap_on === '1' ? 'checked' : '') + '><i></i></span><b>Sprawdzaj skrzynkę automatycznie</b></label>' +
      '<div class="g2f">' + inp('imap_host', 'Serwer IMAP', 'text', 'Dla LH.pl: mail19.lh.pl (jak w rekordach DNS)') + inp('imap_port', 'Port', 'number', 'Zwykle 993 (SSL)') + '</div>' +
      inp('imap_user', 'Login (adres skrzynki)', 'email', 'Puste = adres nadawcy') +
      '<div><label class="f">Hasło do skrzynki</label><input id="u-imap_pass" type="password" autocomplete="new-password" placeholder="' + (u.imap_pass_set ? '••••••  zapisane – wpisz, żeby zmienić' : 'hasło do ' + esc(u.imap_user || u.nadawca_email || 'skrzynki')) + '"></div>' +
      '<div class="row" style="margin-top:12px"><a class="btn" onclick="window.imapTest(this)">Zapisz i sprawdź teraz</a><span class="mut" id="imap-res">' + esc(u.imap_info || '') + '</span></div></div>';
    h += '<div class="card pad"><h2 style="font-size:16px">Nadawca maili</h2>' +
      inp('nadawca_nazwa', 'Nazwa nadawcy', 'text', 'Np. „Maciek | ELOK” – wygląda jak od człowieka') +
      inp('nadawca_email', 'E-mail nadawcy', 'email', 'Musi być skrzynką na Twoim hostingu LH.pl (inaczej spam)') +
      inp('reply_to', 'Odpowiedzi na adres (opcjonalnie)', 'email') +
      '<div class="hint" style="margin-top:14px">Podpis, stopkę i formularz edytujesz w <a style="text-decoration:underline;cursor:pointer" onclick="window.goPodpis()">Maile → Podpis i stopka</a>.</div></div>';
    h += '<div class="card pad"><h2 style="font-size:16px">Powiadomienia o zgłoszeniach</h2>' +
      inp('powiadom_email', 'Twój e-mail', 'email', 'Tu przychodzą zgłoszenia i maile testowe') +
      '<label class="f">Powiadomienia na telefon</label><div class="hint" id="push-st">Sprawdzam…</div>' +
      '<div style="display:flex;gap:8px;flex-wrap:wrap;margin-top:10px"><a class="btn sm dark" id="push-on" onclick="window.pushOn(this)">Włącz na tym urządzeniu</a><a class="btn sm" onclick="window.pushTest(this)">Wyślij test</a></div>' +
      '<details style="margin-top:14px"><summary class="hint" style="cursor:pointer">Zaawansowane: aplikacja ntfy (opcjonalnie)</summary>' +
      inp('ntfy_topic', 'Kanał ntfy', 'text', 'Niepotrzebne, jeśli włączyłeś powiadomienia przyciskiem wyżej. Zostaw puste, żeby wyłączyć.') + '</details></div>';
    h += '<div class="card pad"><h2 style="font-size:16px">Źródła firm (Szukaj firm)</h2>' +
      '<label class="f">Token CEIDG</label><textarea id="u-ceidg_token" rows="3" placeholder="wklej token z maila od CEIDG">' + esc(u.ceidg_token) + '</textarea>' +
      '<div class="hint">Darmowy. Wniosek: dane.biznes.gov.pl → „Hurtownia danych CEIDG” → dostęp do API. Token przychodzi mailem.</div>' +
      '<label class="f">Klucz Google (Places API)</label><input id="u-google_key" value="' + esc(u.google_key) + '" placeholder="AIza…">' +
      '<div class="hint">console.cloud.google.com → nowy projekt → włącz „Places API (New)” → Dane logowania → Klucz API. Wymaga podpięcia karty (jest darmowy limit miesięczny).</div>' +
      '<label class="f">Klucz Companies House (UK)</label><input id="u-ch_key" value="' + esc(u.ch_key) + '" placeholder="np. 1a2b3c4d-…">' +
      '<div class="hint">Darmowy. developer.company-information.service.gov.uk → Register → Your applications → Create an application (Live) → Create new key → <b>REST</b>. Skopiuj klucz tutaj.</div></div>';
    h += '<div class="card pad"><h2 style="font-size:16px">Maile po angielsku (UK)</h2><div class="hint" style="margin-top:4px">Używane w branżach z językiem EN. Telefon w podpisie dostaje automatycznie +48.</div>' +
      inp('podpis_imie_en', 'Imię i nazwisko w podpisie', 'text', 'Puste = jak po polsku, np. Maciej Odolinski') +
      inp('podpis_rola_en', 'Stanowisko (opcjonalnie)', 'text', 'np. Founder, ELOK') +
      inp('pozdrowienie_en', 'Pozdrowienie', 'text', 'Puste = Kind regards,') +
      '<label class="f">Stopka (dane firmy – w UK wymagane)</label><textarea id="u-stopka_en" rows="3" placeholder="ELOK · [pełna nazwa firmy] · [adres], Poland · elok-agency.pl">' + esc(u.stopka_en) + '</textarea>' +
      '<div class="hint">Wpisz pełną nazwę firmy i adres – odbiorca musi wiedzieć, kto pisze.</div></div>';
    h += '<div class="card pad"><h2 style="font-size:16px">Zmiana hasła</h2><div class="hint">Login: <b>' + esc(u.admin_login) + '</b></div>' +
      '<label class="f">Stare hasło</label><input id="p-old" type="password" autocomplete="current-password"><label class="f">Nowe hasło (min. 8 znaków)</label><input id="p-new" type="password" autocomplete="new-password">' +
      '<a class="btn sm" style="margin-top:12px" onclick="window.passSave()">Zmień hasło</a></div>';
    h += '</div>';
    $('main').innerHTML = h; window.pushStatus();
    blLoad();
  });
}
function blLoad() {
  api('branze_limity', {}, function (r) {
    var h = '<table class="tbl" style="font-size:14px"><thead><tr><th style="width:40px">Wł.</th><th>Branża</th><th style="width:76px">Język</th><th style="width:110px">Limit / dzień</th><th>Dziś</th><th>Czeka</th></tr></thead><tbody>';
    for (var i = 0; i < r.rows.length; i++) {
      var b = r.rows[i];
      h += '<tr><td><input type="checkbox" class="bl-on" data-id="' + b.id + '" ' + (+b.aktywna ? 'checked' : '') + '></td><td>' + esc(b.nazwa) + '</td>' +
        '<td><select class="bl-lang" data-id="' + b.id + '" style="padding:6px 8px"><option value="pl"' + (b.jezyk !== 'en' ? ' selected' : '') + '>PL</option><option value="en"' + (b.jezyk === 'en' ? ' selected' : '') + '>EN</option></select></td>' +
        '<td><input type="number" min="0" class="bl-lim" data-id="' + b.id + '" value="' + (+b.limit_dzienny || '') + '" placeholder="bez" style="padding:6px 8px"></td>' +
        '<td>' + (+b.dzis || 0) + (+b.limit_dzienny ? ' / ' + b.limit_dzienny : '') + '</td><td>' + (+b.nowe || 0) + '</td></tr>';
    }
    $('u-branze').innerHTML = h + '</tbody></table><div class="hint">Puste = bez osobnego limitu (bierze z limitu łącznego). Odznaczona branża = automat do niej nie wysyła. Branże idą na zmianę, mail po mailu.</div>';
  });
}
window.uSave = function (btn) {
  var keys = ['nadawca_nazwa', 'nadawca_email', 'reply_to', 'powiadom_email', 'ntfy_topic', 'ceidg_token', 'google_key', 'ch_key', 'stopka_en', 'podpis_rola_en', 'podpis_imie_en', 'pozdrowienie_en', 'limit_dzienny', 'porcja', 'godz_od', 'godz_do', 'dni_przypomnienia', 'dni_przypomnienia2'];
  var u = {}; for (var i = 0; i < keys.length; i++) u[keys[i]] = val('u-' + keys[i]);
  u.auto_on = $('u-auto_on').checked ? 1 : 0;
  u.porcja_auto = $('u-porcja_auto').checked ? 1 : 0;
  var bl = [], ons = document.querySelectorAll('.bl-on');
  for (var j = 0; j < ons.length; j++) { var id = ons[j].getAttribute('data-id'), li = document.querySelector('.bl-lim[data-id="' + id + '"]'); var lg = document.querySelector('.bl-lang[data-id="' + id + '"]'); bl.push({ id: +id, aktywna: ons[j].checked ? 1 : 0, limit: li ? +li.value || 0 : 0, jezyk: lg ? lg.value : 'pl' }); }
  if (bl.length) api('branze_limity_zapisz', { rows: bl }, function () {});
  u.imap_on = $('u-imap_on').checked ? 1 : 0; u.imap_host = val('u-imap_host'); u.imap_port = val('u-imap_port'); u.imap_user = val('u-imap_user'); u.imap_pass = val('u-imap_pass');
  u.przyp2_on = $('u-przyp2_on').checked ? 1 : 0; u.przyp2_kto = val('u-przyp2_kto');
  busy(btn, true);
  api('ustawienia_zapisz', { u: u }, function () { busy(btn, false); toast('Zapisano ustawienia'); }, function () { busy(btn, false); });
};
window.imapTest = function (btn) {
  var u = { imap_on: $('u-imap_on').checked ? 1 : 0, imap_host: val('u-imap_host'), imap_port: val('u-imap_port'), imap_user: val('u-imap_user'), imap_pass: val('u-imap_pass') };
  busy(btn, true); if ($('imap-res')) $('imap-res').textContent = 'Łączę ze skrzynką…';
  api('ustawienia_zapisz', { u: u }, function () {
    api('imap_test', {}, function (r) {
      busy(btn, false);
      $('imap-res').innerHTML = '<b style="color:var(--ok)">✓ Połączono.</b> Przejrzano: ' + r.przejrzane + ' · zwroty: <b>' + r.zwroty + '</b> · odpowiedzi: <b>' + r.odpowiedzi + '</b> · autoodpowiedzi: ' + r.auto;
    }, function (r) { busy(btn, false); $('imap-res').innerHTML = '<span style="color:var(--err)">' + esc((r && r.error) || 'Błąd połączenia') + '</span>'; });
  }, function () { busy(btn, false); });
};
window.pushTest = function (btn) {
  busy(btn, true);
  api('ustawienia_zapisz', { u: { ntfy_topic: val('u-ntfy_topic') } }, function () {
    api('push_test', {}, function (r) { busy(btn, false); toast(r.urzadzenia ? 'Wysłano na ' + r.urzadzenia + ' urządz. – sprawdź telefon' : 'Wysłano przez ntfy'); }, function () { busy(btn, false); });
  }, function () { busy(btn, false); });
};
function pushIOS() { return /iPhone|iPad|iPod/.test(navigator.userAgent); }
function pushStandalone() { return (window.matchMedia && window.matchMedia('(display-mode: standalone)').matches) || window.navigator.standalone === true; }
function pushB64(s) { var p = '='.repeat((4 - s.length % 4) % 4); var b = atob((s + p).replace(/-/g, '+').replace(/_/g, '/')); var a = new Uint8Array(b.length); for (var i = 0; i < b.length; i++) a[i] = b.charCodeAt(i); return a; }
function pushAb(buf) { var b = new Uint8Array(buf), s = ''; for (var i = 0; i < b.length; i++) s += String.fromCharCode(b[i]); return btoa(s).replace(/\+/g, '-').replace(/\//g, '_').replace(/=+$/, ''); }
window.pushStatus = function () {
  var el = $('push-st'); if (!el) return;
  var ok = ('serviceWorker' in navigator) && ('PushManager' in window) && ('Notification' in window);
  if (!ok) {
    el.innerHTML = pushIOS() && !pushStandalone() ? 'Na iPhonie powiadomienia działają tylko z ikonki: w Safari kliknij <b>Udostępnij → Do ekranu początkowego</b>, otwórz panel z ikonki ELOK i wróć tutaj.' : 'Ta przeglądarka nie obsługuje powiadomień. Otwórz panel w Safari (iPhone) albo Chrome (Android).';
    return;
  }
  api('push_key', {}, function (r) {
    navigator.serviceWorker.getRegistration('./').then(function (reg) {
      if (!reg) return null; return reg.pushManager.getSubscription();
    }).then(function (sub) {
      var tu = sub && Notification.permission === 'granted';
      el.innerHTML = (tu ? '✅ <b>Włączone na tym urządzeniu.</b> ' : (Notification.permission === 'denied' ? '⛔ Zablokowane w ustawieniach telefonu (Ustawienia → Powiadomienia → ELOK). ' : 'Wyłączone na tym urządzeniu. ')) + 'Urządzeń z powiadomieniami: ' + r.ile + '.';
      if ($('push-on')) $('push-on').textContent = tu ? 'Włącz ponownie' : 'Włącz na tym urządzeniu';
    }).catch(function () { el.textContent = 'Urządzeń z powiadomieniami: ' + r.ile + '.'; });
  }, function () { el.textContent = 'Nie udało się sprawdzić powiadomień.'; });
};
window.pushOn = function (btn) {
  if (!('serviceWorker' in navigator) || !('PushManager' in window)) { window.pushStatus(); toast('Tu nie da się włączyć – zobacz opis wyżej', 'err'); return; }
  busy(btn, true);
  var stop = function (m) { busy(btn, false); if (m) toast(m, 'err'); window.pushStatus(); };
  Notification.requestPermission().then(function (perm) {
    if (perm !== 'granted') { stop('Nie zezwolono na powiadomienia'); return; }
    api('push_key', {}, function (r) {
      navigator.serviceWorker.register('push-sw.js', { scope: './' }).then(function () { return navigator.serviceWorker.ready; }).then(function (reg) {
        return reg.pushManager.getSubscription().then(function (old) {
          if (old) return old.unsubscribe().then(function () { return reg; });
          return reg;
        });
      }).then(function (reg) {
        return reg.pushManager.subscribe({ userVisibleOnly: true, applicationServerKey: pushB64(r.key) });
      }).then(function (sub) {
        var dev = (pushIOS() ? 'iPhone' : (/Android/.test(navigator.userAgent) ? 'Android' : 'Komputer'));
        api('push_sub', { endpoint: sub.endpoint, p256dh: pushAb(sub.getKey('p256dh')), auth: pushAb(sub.getKey('auth')), urzadzenie: dev }, function (x) {
          busy(btn, false); toast(x.kod >= 200 && x.kod < 300 ? 'Włączone! Za chwilę przyjdzie testowe powiadomienie' : 'Zapisane, ale test nie doszedł (kod ' + x.kod + ')'); window.pushStatus();
        }, function () { stop(''); });
      }).catch(function (e) { stop('Nie udało się: ' + ((e && e.message) || e)); });
    }, function () { stop(''); });
  });
};
window.passSave = function () {
  api('haslo', { stare: val('p-old'), nowe: val('p-new') }, function () { toast('Hasło zmienione'); $('p-old').value = ''; $('p-new').value = ''; });
};

// ========== PUBLIKACJE (TikTok / Instagram / Facebook) ==========
var PB = { rows: [], meta: {}, ed: null };
var PBS = { szkic: 'Szkic', zaplanowany: 'Zaplanowana', w_toku: 'W toku', opublikowany: 'Opublikowana', blad: 'Błąd' };
function pbWhen(s) { if (!s) return '—'; var d = new Date(String(s).replace(' ', 'T')); var dn = ['ndz', 'pn', 'wt', 'śr', 'czw', 'pt', 'sob']; return dn[d.getDay()] + ' ' + fd(s); }
function pbLocal(s) { return s ? String(s).replace(' ', 'T').slice(0, 16) : ''; }

// ========== AKTUALIZACJE (GitHub: test → prawdziwa wersja) ==========
(function () {
  if (!window.ELOK_TEST || document.getElementById('test-bar')) return;
  var b = document.createElement('div'); b.id = 'test-bar';
  b.style.cssText = 'position:fixed;left:0;right:0;top:0;z-index:60;background:#C5A059;color:#151515;font:600 13px Inter,Arial;text-align:center;padding:6px 10px';
  b.innerHTML = 'WERSJA TESTOWA panelu – te same dane co prawdziwy panel. Zmiany zatwierdzasz w prawdziwym panelu → Aktualizacje.';
  document.body.appendChild(b); document.body.style.paddingTop = '30px';
})();
function aapi(a, data, cb, errCb) {
  var x = new XMLHttpRequest(); x.open(data ? 'POST' : 'GET', 'aktualizacje.php?a=' + a);
  x.setRequestHeader('X-CSRF', window.CSRF); if (data) x.setRequestHeader('Content-Type', 'application/json');
  x.onload = function () { var r = null; try { r = JSON.parse(x.responseText); } catch (e) { toast('Błąd serwera', 'err'); if (errCb) errCb(); return; }
    if (!r.ok) { if (r.login) { location.reload(); return; } toast(r.error || 'Błąd', 'err'); if (errCb) errCb(r); return; } cb(r); };
  x.onerror = function () { toast('Brak połączenia', 'err'); if (errCb) errCb(); };
  x.send(data ? JSON.stringify(data) : null);
}
function akRun(r, label) {
  if (!r) return '<span class="mut">' + label + ': brak wgrań</span>';
  var t = r.status !== 'completed' ? '<span style="color:var(--gold-d)">wgrywa się…</span>' : (r.ok ? '<span style="color:var(--ok)">wgrane ' + esc(r.kiedy) + '</span>' : '<span style="color:var(--err)">błąd wgrania ' + esc(r.kiedy) + '</span>');
  return '<span class="mut">' + label + ':</span> ' + t;
}
function viewAktualizacje() {
  aapi('lista', null, function (r) {
    var h = '<div class="head"><div><h1>Aktualizacje</h1><p>Każdą zmianę wgrywam najpierw na wersję testową. Sprawdzasz ją, a tutaj zatwierdzasz – wtedy sama trafia na prawdziwy panel i stronę.</p></div>' + (r.brak_tokenu ? '' : '<a class="btn" onclick="viewAktualizacje()">Odśwież</a>') + '</div>';
    if (r.brak_tokenu) {
      h += '<div class="card pad" style="max-width:720px"><h2 class="ct">Połącz z GitHub (jednorazowo)</h2><div class="info">1. github.com → zdjęcie profilu → <b>Settings → Developer settings → Personal access tokens → Fine-grained tokens → Generate new token</b>.<br>2. Nazwa: ELOK panel, ważność: najdłuższa możliwa.<br>3. Repository access: <b>Only select repositories</b> → <b>elok-panel</b> i <b>elok-strona</b>.<br>4. Permissions → Repository permissions: <b>Contents: Read and write</b>, <b>Actions: Read-only</b>.<br>5. Generate token, skopiuj i wklej poniżej.</div>' +
        '<label class="f">Token GitHub</label><input id="ak-t" placeholder="github_pat_…" autocomplete="off"><a class="btn dark" style="margin-top:12px" onclick="window.akToken(this)">Zapisz token</a></div>';
      $('main').innerHTML = h; return;
    }
    for (var i = 0; i < r.repos.length; i++) {
      var x = r.repos[i], z = '';
      if (x.err) z = '<div class="info" style="color:var(--err)">' + esc(x.err) + '</div>';
      else if (!x.zmiany.length) z = '<div class="info">Brak nowych zmian – wersja testowa i prawdziwa są takie same.</div>';
      else { z = '<table class="tbl"><tbody>'; for (var k = 0; k < x.zmiany.length; k++) z += '<tr><td>' + esc(x.zmiany[k].msg) + '</td><td class="mut" style="white-space:nowrap;text-align:right">' + esc(x.zmiany[k].kiedy) + '</td></tr>'; z += '</tbody></table>'; }
      h += '<div class="card sec"><div class="pad"><div style="display:flex;justify-content:space-between;gap:12px;flex-wrap:wrap;align-items:center"><div><h2 class="ct" style="margin:0">' + esc(x.nazwa) + (x.zmiany.length ? ' <span class="pill z-nowe">' + x.zmiany.length + ' do zatwierdzenia</span>' : '') + '</h2>' +
        '<div style="margin-top:6px;font-size:13px">' + akRun(x.run_test, 'Test') + ' &nbsp;·&nbsp; ' + akRun(x.run_main, 'Prawdziwa') + '</div></div>' +
        '<div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" target="_blank" rel="noopener" href="' + esc(x.test_url) + '">Zobacz wersję testową</a>' +
        (x.zmiany.length && !r.test ? '<a class="btn gold" onclick="window.akOk(this,\'' + esc(x.repo) + '\',\'' + esc(x.nazwa) + '\')">Zatwierdź i wgraj</a>' : '') + '</div></div></div>' + z + '</div>';
    }
    if (r.test) h += '<div class="note">To jest panel testowy – zatwierdzasz w prawdziwym panelu.</div>';
    h += '<div class="note" style="border:0">Po zatwierdzeniu wgrywanie trwa ok. 1–2 minut. Odśwież tę stronę, żeby zobaczyć „wgrane”.</div>';
    $('main').innerHTML = h;
  });
}
window.akToken = function (b) { busy(b, true); aapi('token', { token: val('ak-t') }, function () { busy(b, false); toast('Połączono z GitHub'); viewAktualizacje(); }, function () { busy(b, false); }); };
window.akOk = function (b, repo, nazwa) {
  window.ask('Zatwierdzić zmiany?', 'Zmiany z wersji testowej (' + esc(nazwa) + ') trafią na prawdziwą wersję. Wgranie trwa ok. 1–2 minut.', 'Zatwierdź i wgraj', function () {
    busy(b, true); aapi('zatwierdz', { repo: repo }, function () { toast('Zatwierdzone – wgrywa się'); setTimeout(viewAktualizacje, 2500); }, function () { busy(b, false); });
  });
};

// ========== STRONA WWW (elok-agency.pl): statystyki, edytor, podłączenia, konsultacje ==========
(function () {
  if (document.getElementById('www-css')) return;
  var st = document.createElement('style'); st.id = 'www-css';
  st.textContent = '.wchart{display:flex;align-items:flex-end;gap:3px;height:150px;margin-top:14px}.wchart div{flex:1;background:var(--gold);border-radius:4px 4px 0 0;min-height:2px;position:relative;opacity:.85}.wchart div:hover{opacity:1}' +
    '.wchart div span{display:none;position:absolute;bottom:100%;left:50%;transform:translateX(-50%);background:var(--ink);color:#fff;padding:3px 8px;border-radius:6px;font-size:12px;white-space:nowrap;margin-bottom:4px}.wchart div:hover span{display:block}' +
    '.wchx{display:flex;justify-content:space-between;color:var(--muted);font-size:12px;margin-top:6px}.wbar{display:block;height:5px;background:var(--line2);border-radius:4px;margin-top:6px;overflow:hidden}.wbar i{display:block;height:100%;background:var(--gold)}' +
    '.wins{display:grid;gap:8px}.wins div{background:var(--gold-l);border-radius:12px;padding:11px 14px;font-size:14px;line-height:1.5}.tbl td.r,.tbl th.r{text-align:right;white-space:nowrap}' +
    '.wfr{margin-top:14px;border:1px solid var(--line);border-radius:16px;overflow:hidden;background:#08080A}.wfr iframe{display:block;width:100%;height:calc(100vh - 230px);min-height:560px;border:0;margin:0 auto}' +
    '.wgp{background:#fff;border:1px solid var(--line);border-radius:12px;padding:14px 16px;font-family:Arial;margin-top:12px}.wgp .u{color:#4d5156;font-size:13px}.wgp .t{color:#1a0dab;font-size:19px;line-height:1.3;margin:3px 0}.wgp .d{color:#4d5156;font-size:14px;line-height:1.45}' +
    '.wcnt{float:right;font-weight:500;font-size:12px;color:var(--muted)}.wcnt.ok{color:var(--ok)}.wcnt.bad{color:var(--err)}.wsel{display:flex;gap:6px;flex-wrap:wrap}' +
    '.wtop{display:grid;grid-template-columns:240px 1fr 1fr;gap:12px;align-items:end}@media(max-width:1000px){.wtop{grid-template-columns:1fr}.wfr iframe{height:70vh}}';
  document.head.appendChild(st);
})();
function sapi(q, data, cb, errCb) {
  var x = new XMLHttpRequest();
  x.open(data ? 'POST' : 'GET', 'strona/admin.php' + (q ? '?' + q : ''));
  x.setRequestHeader('X-CSRF', window.CSRF);
  if (data) x.setRequestHeader('Content-Type', 'application/json');
  x.onload = function () {
    var r = null; try { r = JSON.parse(x.responseText); } catch (e) { toast('Błąd serwera', 'err'); if (errCb) errCb(); return; }
    if (!r.ok) { if (r.login) { location.reload(); return; } toast(r.error || 'Błąd', 'err'); if (errCb) errCb(r); return; }
    if (r.nodir) { wwwNoDir(r); return; }
    cb(r);
  };
  x.onerror = function () { toast('Brak połączenia', 'err'); if (errCb) errCb(); };
  x.send(data ? JSON.stringify(data) : null);
}
function wwwNoDir(r) {
  $('main').innerHTML = '<div class="head"><div><h1>Strona www</h1><p>Panel nie ma dostępu do folderu strony.</p></div></div>' +
    '<div class="card pad" style="max-width:760px"><h2 class="ct">Jak to naprawić (2 minuty)</h2>' +
    '<div class="info" style="font-size:14.5px">1. Zaloguj się do <b>LH.pl</b> → <b>Serwery → Strony WWW</b>.<br>2. Kliknij <b>panel.elok-agency.pl</b>.<br>3. W kafelku <b>Separacja strony</b> wybierz <b>Wyłączona</b> i kliknij <b>Zapisz</b>.<br>4. Odczekaj minutę i odśwież tę zakładkę.</div>' +
    '<div class="note">Separacja zamyka panel w jego własnym folderze, więc nie może zmieniać plików strony elok-agency.pl.</div>' +
    '<details style="margin-top:14px"><summary class="hint" style="cursor:pointer">Zaawansowane: wpisz ścieżkę ręcznie</summary><label class="f">Folder strony</label><input id="w-dir" value="' + esc(r.zgad || '') + '"><a class="btn sm" style="margin-top:10px" onclick="window.wDir(this)">Zapisz</a></details></div>';
}
window.wDir = function (b) { busy(b, true); sapi('', { a: 'folder', dir: val('w-dir') }, function () { busy(b, false); window.go(VIEW); }, function () { busy(b, false); }); };
function wbar(v, m) { return '<span class="wbar"><i style="width:' + (m ? Math.max(2, Math.round(v / m * 100)) : 0) + '%"></i></span>'; }
function wpct(a, b) { return b ? (Math.round(a / b * 1000) / 10) + '%' : '–'; }
function wnum(n) { return String(n).replace(/\B(?=(\d{3})+(?!\d))/g, ' '); }

// ---- Statystyki strony ----
var WWW_D = 30;
window.wD = function (d) { WWW_D = d; viewWwwStat(); };
function viewWwwStat() {
  sapi('j=stat&d=' + WWW_D, null, function (r) {
    var h = '<div class="head"><div><h1>Statystyki strony</h1><p>elok-agency.pl · bez cookies i bez zapisywania adresów IP, boty odfiltrowane.</p></div><div class="wsel">';
    var P = [[1, 'Dziś'], [7, '7 dni'], [30, '30 dni'], [90, '90 dni'], [365, 'Rok']];
    for (var i = 0; i < P.length; i++) h += '<a class="chip' + (WWW_D === P[i][0] ? ' on' : '') + '" onclick="window.wD(' + P[i][0] + ')">' + P[i][1] + '</a>';
    h += '</div></div>';
    h += '<div class="grid g4">' + stat('Odwiedzający', wnum(r.sesje), r.prev ? 'poprzednio: ' + r.prev : 'osoby (sesje)') + stat('Wyświetlenia stron', wnum(r.pv), r.sesje ? (Math.round(r.pv / r.sesje * 10) / 10) + ' strony na osobę' : '') +
      stat('Rezerwacje konsultacji', '<span style="color:var(--ok)">' + r.rez + '</span>', 'konwersja ' + wpct(r.rez, r.sesje)) + stat('Telefon + e-mail', r.tel + r.ml, 'kliknęli: ' + r.tel + ' tel. · ' + r.ml + ' mail') + '</div>';
    var mx = 1; for (i = 0; i < r.dzien.length; i++) if (r.dzien[i][1] > mx) mx = r.dzien[i][1];
    var ch = ''; for (i = 0; i < r.dzien.length; i++) ch += '<div style="height:' + Math.round(r.dzien[i][1] / mx * 100) + '%"><span>' + r.dzien[i][0] + ': ' + r.dzien[i][1] + '</span></div>';
    var ins = ''; for (i = 0; i < r.ins.length; i++) ins += '<div>' + r.ins[i] + '</div>';
    h += '<div class="grid g2 sec"><div class="card pad"><h2 class="ct">Odwiedzający dzień po dniu</h2><div class="wchart">' + ch + '</div><div class="wchx"><span>' + (r.dzien.length ? r.dzien[0][0] : '') + '</span><span>dziś</span></div></div>' +
      '<div class="card pad"><h2 class="ct">Co działa najlepiej</h2><div class="wins">' + ins + '</div></div></div>';
    function tb(head, rows, fn) { if (!rows.length) return '<div class="empty" style="padding:20px">Brak danych – zbierają się od wgrania nowej strony.</div>'; var t = '<table class="tbl"><thead><tr>' + head + '</tr></thead><tbody>'; for (var k = 0; k < rows.length; k++) t += fn(rows[k]); return t + '</tbody></table>'; }
    var m1 = r.src.length ? r.src[0][1] : 1, m2 = r.land.length ? r.land[0][1] : 1, m3 = r.pages.length ? +r.pages[0].pv : 1;
    h += '<div class="grid g2 sec"><div class="card"><div class="pad" style="padding-bottom:0"><h2 class="ct">Skąd przychodzą</h2></div>' + tb('<th>Źródło</th><th class="r">Osoby</th><th class="r">Kontakt</th><th class="r">Rezerw.</th>', r.src, function (x) { return '<tr><td>' + esc(x[0]) + wbar(x[1], m1) + '</td><td class="r">' + x[1] + '</td><td class="r">' + x[3] + ' <span class="mut">(' + wpct(x[3], x[1]) + ')</span></td><td class="r">' + x[2] + '</td></tr>'; }) + '</div>' +
      '<div class="card"><div class="pad" style="padding-bottom:0"><h2 class="ct">Strony wejścia</h2><div class="hint">Gdzie ktoś zaczął wizytę.</div></div>' + tb('<th>Strona</th><th class="r">Osoby</th><th class="r">Kontakt</th>', r.land, function (x) { return '<tr><td>' + esc(x[0]) + wbar(x[1], m2) + '</td><td class="r">' + x[1] + '</td><td class="r">' + x[3] + ' <span class="mut">(' + wpct(x[3], x[1]) + ')</span></td></tr>'; }) + '</div></div>';
    var cta = r.ctas.length ? '<h2 class="ct" style="margin-top:18px">Które przyciski „konsultacja” działają</h2><table class="tbl"><tbody>' : '';
    for (i = 0; i < r.ctas.length; i++) cta += '<tr><td>' + esc(r.ctas[i].x || '–') + '</td><td class="mut">' + esc(r.ctas[i].nazwa) + '</td><td class="r">' + r.ctas[i].n + '</td></tr>';
    if (r.ctas.length) cta += '</tbody></table>';
    var kl = '<table class="tbl"><tbody>'; for (i = 0; i < r.klik.length; i++) kl += '<tr><td>' + r.klik[i][0] + '</td><td class="r"><b>' + r.klik[i][1] + '</b></td></tr>'; kl += '</tbody></table>';
    h += '<div class="grid g2 sec"><div class="card"><div class="pad" style="padding-bottom:0"><h2 class="ct">Najczęściej oglądane</h2><div class="hint">„Doczytało” = przewinęło co najmniej 3/4 strony.</div></div>' + tb('<th>Strona</th><th class="r">Wyśw.</th><th class="r">Osoby</th><th class="r">Doczytało</th>', r.pages, function (x) { return '<tr><td>' + esc(x.nazwa) + wbar(+x.pv, m3) + '</td><td class="r">' + x.pv + '</td><td class="r">' + x.u + '</td><td class="r">' + wpct(+x.doczyt, +x.u) + '</td></tr>'; }) + '</div>' +
      '<div class="card pad"><h2 class="ct">Kliknięcia</h2>' + kl + cta + '</div></div>';
    h += '<div class="grid g2 sec"><div class="card"><div class="pad" style="padding-bottom:0"><h2 class="ct">Urządzenia</h2></div>' + tb('<th>Urządzenie</th><th class="r">Osoby</th><th class="r">Kontakt</th>', r.dev, function (x) { return '<tr><td>' + esc(x[0].charAt(0).toUpperCase() + x[0].slice(1)) + '</td><td class="r">' + x[1] + ' <span class="mut">(' + wpct(x[1], r.sesje) + ')</span></td><td class="r">' + x[2] + '</td></tr>'; }) + '</div>' +
      '<div class="card"><div class="pad" style="padding-bottom:0"><h2 class="ct">Kampanie reklamowe</h2></div>' + (r.kamp.length ? tb('<th>Kampania</th><th class="r">Osoby</th><th class="r">Kontakt</th>', r.kamp, function (x) { return '<tr><td>' + esc(x[0]) + '</td><td class="r">' + x[1] + '</td><td class="r">' + x[2] + '</td></tr>'; }) : '<div class="pad info">Żeby widzieć tu swoje reklamy, dodawaj na końcu linku w reklamie np. <b>?utm_source=facebook&amp;utm_medium=cpc&amp;utm_campaign=warsztaty</b>. Kliknięcia z reklam Google i Facebooka rozpoznaję też bez tego.</div>') + '</div></div>';
    h += '<div class="card pad sec"><h2 class="ct">Nie licz moich wejść</h2><div class="info">Otwórz ten link raz na każdym swoim telefonie i komputerze – Twoje wejścia przestaną zawyżać statystyki.</div><div class="row" style="margin-top:12px;gap:10px;display:flex;flex-wrap:wrap"><a class="btn" target="_blank" rel="noopener" href="' + esc(r.url) + '/?nielicz=1">Nie licz mnie na tym urządzeniu</a><a class="btn" target="_blank" rel="noopener" href="' + esc(r.url) + '/?nielicz=0">Cofnij</a></div></div>';
    $('main').innerHTML = h;
  });
}

// ---- Edytor strony ----
var WE = { f: 'index.html', dirty: false };
window.edChanged = function () { WE.dirty = true; var n = 0; try { n = $('w-ifr').contentWindow.edGet().n; } catch (e) {} var z = $('w-zm'); if (z) z.textContent = n ? 'zmienione teksty: ' + n : 'niezapisane zmiany'; };
window.wCnt = function () {
  var t = $('w-t'), d = $('w-d'); if (!t) return; var a = t.value.length, b = d.value.length;
  $('w-ct').textContent = a + ' znaków'; $('w-ct').className = 'wcnt ' + (a >= 30 && a <= 60 ? 'ok' : 'bad');
  $('w-cd').textContent = b + ' znaków'; $('w-cd').className = 'wcnt ' + (b >= 70 && b <= 155 ? 'ok' : 'bad');
  $('w-gt').textContent = a > 62 ? t.value.slice(0, 60) + '…' : t.value; $('w-gd').textContent = b > 158 ? d.value.slice(0, 155) + '…' : d.value;
};
window.wPage = function (f) {
  if (WE.dirty) { window.ask('Niezapisane zmiany', 'Masz niezapisane zmiany na tej stronie. Przejść bez zapisu?', 'Przejdź bez zapisu', function () { WE.dirty = false; WE.f = f; viewWwwEdit(); }); var s = $('w-sel'); if (s) s.value = WE.f; return; }
  WE.f = f; viewWwwEdit();
};
window.wDev = function (a, w) { var p = a.parentNode.children; for (var i = 0; i < p.length; i++) p[i].className = 'chip'; a.className = 'chip on'; a.style.cssText = ''; $('w-ifr').style.width = w; };
function viewWwwEdit() {
  sapi('j=strony', null, function (L) {
    var ok = false, i; for (i = 0; i < L.strony.length; i++) if (L.strony[i].f === WE.f) ok = true; if (!ok) WE.f = 'index.html';
    sapi('j=strona&f=' + encodeURIComponent(WE.f), null, function (r) {
      WE.dirty = false;
      var opt = ''; for (i = 0; i < L.strony.length; i++) opt += '<option value="' + esc(L.strony[i].f) + '"' + (L.strony[i].f === WE.f ? ' selected' : '') + '>' + esc(L.strony[i].nazwa) + '</option>';
      var h = '<div class="head"><div><h1>Edytor strony</h1><p>Kliknij w dowolny tekst na podglądzie i pisz. Zmienione teksty świecą na zielono. Na koniec „Zapisz”.</p></div>' +
        '<div style="display:flex;gap:10px;align-items:center"><span class="mut" id="w-zm"></span><a class="btn" target="_blank" rel="noopener" href="' + esc(L.url) + '/' + (WE.f === 'index.html' ? '' : esc(WE.f)) + '">Otwórz stronę</a><a class="btn gold" id="w-save" onclick="window.wSave(this)">Zapisz zmiany</a></div></div>';
      h += '<div class="card pad"><div class="wtop"><div><label class="f" style="margin-top:0">Strona</label><select id="w-sel" onchange="window.wPage(this.value)">' + opt + '</select></div>' +
        '<div><label class="f" style="margin-top:0">Tytuł w Google <span class="wcnt" id="w-ct"></span></label><input id="w-t" value="' + esc(r.title) + '" oninput="window.wCnt();window.edChanged()"></div>' +
        '<div><label class="f" style="margin-top:0">Opis w Google <span class="wcnt" id="w-cd"></span></label><input id="w-d" value="' + esc(r.desc) + '" oninput="window.wCnt();window.edChanged()"></div></div>' +
        '<div class="hint">Tytuł: 30–60 znaków, najważniejsza fraza na początku. Opis: 70–155 znaków, zachęta do kliknięcia. Tak to wygląda w Google:</div>' +
        '<div class="wgp"><div class="u">elok-agency.pl' + (WE.f === 'index.html' ? '' : ' › ' + esc(WE.f.replace('/index.html', '').replace('.html', ''))) + '</div><div class="t" id="w-gt"></div><div class="d" id="w-gd"></div></div></div>';
      h += '<div style="display:flex;justify-content:space-between;align-items:center;margin-top:14px;gap:10px;flex-wrap:wrap"><div class="chips"><a class="chip on" onclick="window.wDev(this,\'100%\')">Komputer</a><a class="chip" onclick="window.wDev(this,\'390px\')">Telefon</a></div><span class="hint" style="margin:0">Enter = koniec edycji tekstu. Linki i przyciski w podglądzie nie działają, żeby dało się je edytować.</span></div>';
      h += '<div class="wfr"><iframe id="w-ifr" src="strona/admin.php?t=podglad&amp;f=' + encodeURIComponent(WE.f) + '" title="Podgląd strony do edycji"></iframe></div>';
      var kp = '';
      for (i = 0; i < r.kopie.length; i++) kp += wHistRow(r.kopie[i], false);
      h += '<div class="card sec"><div class="pad" style="padding-bottom:' + (kp ? '0' : '20px') + '"><h2 class="ct">Historia zmian tej strony</h2><div class="hint">Każdy zapis jest tu z opisem, co zmieniono. „Cofnij” przywraca stronę do stanu sprzed tego zapisu, a obecna wersja też trafia do historii, więc nic nie zginie. Trzymam 50 ostatnich zapisów.</div></div>' + (kp ? '<table class="tbl"><tbody>' + kp + '</tbody></table>' : '') + '</div>';
      h += '<div class="card sec" id="w-all"><div class="pad"><h2 class="ct">Ostatnie zmiany na całej stronie</h2><div class="hint">Wszystkie podstrony razem, od najnowszych.</div><div class="mut" style="margin-top:10px">Ładowanie…</div></div></div>';
      $('main').innerHTML = h; window.wCnt();
      sapi('j=historia', null, function (x) {
        var t = '', el = $('w-all'); if (!el) return;
        for (var j = 0; j < x.h.length; j++) t += wHistRow(x.h[j], true);
        el.innerHTML = '<div class="pad" style="padding-bottom:' + (t ? '0' : '20px') + '"><h2 class="ct">Ostatnie zmiany na całej stronie</h2><div class="hint">Wszystkie podstrony razem, od najnowszych. Kliknij nazwę strony, żeby ją otworzyć w edytorze.</div>' + (t ? '' : '<div class="mut" style="margin-top:10px">Jeszcze nic nie zapisano.</div>') + '</div>' + (t ? '<table class="tbl"><tbody>' + t + '</tbody></table>' : '');
      });
    });
  });
}
window.wSave = function (b) {
  var g = { zm: {}, n: 0 }; try { g = $('w-ifr').contentWindow.edGet(); } catch (e) {}
  busy(b, true);
  sapi('', { a: 'zapisz', f: WE.f, zm: g.zm, title: val('w-t'), desc: val('w-d') }, function (r) { busy(b, false); WE.dirty = false; toast(r.zmiany ? 'Zapisane – już widać na stronie' : 'Brak zmian'); if (r.zmiany) viewWwwEdit(); }, function () { busy(b, false); });
};
function wHistRow(k, all) {
  var inf = k.info || {}, d = '';
  if (inf.typ === 'przywrocenie') d = '<div class="mut">Przywrócenie wersji z ' + esc(inf.z || '') + '</div>';
  else if (inf.zm && inf.zm.length) {
    for (var i = 0; i < inf.zm.length && i < 4; i++) d += '<div style="margin-top:4px;font-size:14px"><span class="mut" style="text-decoration:line-through">' + esc(inf.zm[i][0].slice(0, 90)) + '</span> <b>→</b> ' + esc(inf.zm[i][1].slice(0, 90)) + '</div>';
    if (inf.zm.length > 4) d += '<div class="mut" style="font-size:13px;margin-top:4px">i jeszcze ' + (inf.zm.length - 4) + ' zmian</div>';
  } else d = '<div class="mut" style="font-size:13px">Zapis sprzed włączenia opisów zmian</div>';
  var name = all ? '<a style="cursor:pointer;text-decoration:underline" onclick="window.wPage(\'' + esc(k.f) + '\')">' + esc(k.nazwa) + '</a> · ' : '';
  return '<tr><td><b>' + name + 'Zapis ' + esc(k.kiedy) + '</b>' + d + '</td><td class="r" style="vertical-align:top">' + (all ? '' : '<a class="btn sm" onclick="window.wRestore(\'' + esc(k.k) + '\')">Cofnij</a>') + '</td></tr>';
}
window.addEventListener('beforeunload', function (e) { if (VIEW === 'www_edit' && WE.dirty) { e.preventDefault(); e.returnValue = ''; } });
window.wRestore = function (k) {
  window.ask('Cofnąć ten zapis?', 'Strona wróci do stanu sprzed tego zapisu. Obecna wersja też zostanie zapisana w historii, więc nic nie stracisz.', 'Cofnij', function () {
    sapi('', { a: 'przywroc', f: WE.f, k: k }, function () { toast('Przywrócone'); viewWwwEdit(); });
  });
};

// ---- Podłączenia ----
function viewWwwPodl() {
  sapi('j=podl', null, function (r) {
    var i, h = '<div class="head"><div><h1>Podłączenia</h1><p>Wklej identyfikatory – strona sama doda kody. Pole puste = narzędzie wyłączone. Gdy coś jest wpisane, na stronie pokaże się baner zgody na cookies (wymaga tego prawo).</p></div><a class="btn gold" onclick="window.wPodlSave(this)">Zapisz i wgraj na stronę</a></div>';
    h += '<div class="grid g2"><div class="card pad"><h2 class="ct">Statystyki i reklamy</h2>';
    for (i = 0; i < r.integ.length; i++) h += '<label class="f">' + esc(r.integ[i].label) + '</label><input class="w-iv" data-k="' + r.integ[i].k + '" value="' + esc(r.integ[i].v) + '" autocomplete="off" spellcheck="false"><div class="hint">' + r.integ[i].hint + '</div>';
    h += '<div class="note">Rezerwacja konsultacji wysyła się jako konwersja: w Meta „Schedule”, w Google „generate_lead” (+ konwersja Ads), w TikToku „SubmitForm”. Reklamy same uczą się szukać ludzi, którzy rezerwują.</div></div>';
    h += '<div class="card pad"><h2 class="ct">Weryfikacja strony</h2>';
    for (i = 0; i < r.verif.length; i++) h += '<label class="f">' + esc(r.verif[i].label) + '</label><input class="w-iv" data-k="' + r.verif[i].k + '" value="' + esc(r.verif[i].v) + '" autocomplete="off" spellcheck="false" placeholder="możesz wkleić cały tag &lt;meta …&gt;"><div class="hint">' + r.verif[i].hint + '</div>';
    h += '<div class="note"><b>Po weryfikacji w Google Search Console:</b> w „Mapy witryn” dodaj <b>sitemap.xml</b>.<br><br><b>Wizytówka Google</b> (business.google.com): strona <b>https://elok-agency.pl</b>, link do rezerwacji <b>https://elok-agency.pl/konsultacja.html</b>.</div></div></div>';
    $('main').innerHTML = h;
  });
}
window.wPodlSave = function (b) {
  var v = {}, L = document.querySelectorAll('.w-iv'); for (var i = 0; i < L.length; i++) v[L[i].getAttribute('data-k')] = L[i].value;
  busy(b, true); sapi('', { a: 'podlaczenia', v: v }, function () { busy(b, false); toast('Zapisane – kody są już na stronie'); }, function () { busy(b, false); });
};

// ---- Konsultacje ----
function viewWwwKons() {
  sapi('j=kons', null, function (r) {
    var i, h = '<div class="head"><div><h1>Konsultacje</h1><p>Rezerwacje z elok-agency.pl/konsultacja. Każda jest też w Zgłoszeniach. Żeby odwołać i zwolnić termin, daj zgłoszeniu status „Odrzucone”.</p></div></div>';
    var t = '';
    for (i = 0; i < r.rows.length; i++) { var x = r.rows[i]; t += '<tr><td style="white-space:nowrap"><b>' + esc(x.kiedy) + '</b></td><td>' + esc(x.imie) + '<div class="mut"><a href="mailto:' + esc(x.email) + '">' + esc(x.email) + '</a>' + (x.telefon ? ' · <a href="tel:' + esc(x.telefon.replace(/[^0-9+]/g, '')) + '">' + esc(x.telefon) + '</a>' : '') + '</div>' + (x.opis ? '<div class="mut">' + esc(x.opis.slice(0, 160)) + '</div>' : '') + '</td><td>' + (x.odwolana ? '<span class="pill st-nie">odwołana</span>' : '<span class="pill z-umowione">aktywna</span>') + '</td></tr>'; }
    h += '<div class="grid g21"><div class="card">' + (t ? '<table class="tbl"><thead><tr><th>Termin</th><th>Kto</th><th>Status</th></tr></thead><tbody>' + t + '</tbody></table>' : '<div class="empty">Brak zaplanowanych konsultacji.</div>') + '</div>';
    h += '<div class="card pad"><h2 class="ct">Ustawienia</h2><label class="f">Stały link do rozmowy (opcjonalnie)</label><input id="w-kl" value="' + esc(r.link) + '" placeholder="https://meet.google.com/abc-defg-hij"><div class="hint">Trafia do maila z potwierdzeniem. Puste = w mailu piszemy, że link wyślesz przed spotkaniem.</div>' +
      '<label class="f">Dni bez konsultacji</label><textarea id="w-kw" rows="3">' + esc(r.wolne) + '</textarea><div class="hint">Daty RRRR-MM-DD po przecinku. Te dni są wyszarzone w kalendarzu. Godziny: pon–pt 9:00–16:30.</div>' +
      '<a class="btn dark" style="margin-top:14px" onclick="window.wKonsSave(this)">Zapisz</a></div></div>';
    $('main').innerHTML = h;
  });
}
window.wKonsSave = function (b) { busy(b, true); sapi('', { a: 'konsultacje', link: val('w-kl'), wolne: val('w-kw') }, function () { busy(b, false); toast('Zapisane'); }, function () { busy(b, false); }); };

function viewPublikacje() {
  api('soc_lista', {}, function (r) {
    PB.rows = r.rows; PB.meta = r.meta;
    var m = r.meta, conn = m.ok
      ? '<span class="pill" style="background:#E6F5EC;color:#1F7A4D">' + ic('check', 13) + ' Facebook: ' + esc(m.page) + '</span> ' + (m.ig ? '<span class="pill" style="background:#E6F5EC;color:#1F7A4D">' + ic('check', 13) + ' Instagram: @' + esc(m.ig) + '</span>' : '<span class="pill" style="background:#FDECEA;color:#B3261E">Instagram niepodpięty</span> <a class="ilink" onclick="window.pbIgRefresh(this)">sprawdź ponownie</a>') + ' <a class="ilink" onclick="window.pbConnect()">zmień</a>'
      : '<span class="pill" style="background:#FDECEA;color:#B3261E">Facebook i Instagram niepołączone</span> <a class="btn sm dark" onclick="window.pbConnect()">Połącz</a>';
    var h = '<div class="head"><div><h1>Publikacje</h1><p>Wrzucasz filmik i opis raz – panel publikuje na Facebooku i Instagramie, a o TikToku przypomina Ci pushem.</p></div>' +
      '<div style="display:flex;gap:8px;flex-wrap:wrap"><a class="btn" onclick="window.pbBulk()">' + ic('upload', 14) + ' Dodaj wiele naraz</a><a class="btn dark" onclick="window.pbNew()">+ Nowa publikacja</a></div></div>' +
      '<div class="card" style="padding:14px 18px;margin-bottom:14px;display:flex;gap:8px;flex-wrap:wrap;align-items:center">' + conn + '</div>' +
      '<div class="bulk" id="pb-bulk" style="display:none"><b id="pb-bn"></b><span style="flex:1"></span><a class="btn sm red" onclick="window.pbDelSel()">' + ic('trash', 14) + ' Usuń zaznaczone</a></div>' +
      '<div class="card" id="pb-list"></div>' +
      '<div style="margin-top:12px;text-align:right"><a class="btn sm red" onclick="window.pbDelAll()">' + ic('trash', 13) + ' Usuń wszystkie zaplanowane i szkice</a></div>';
    $('main').innerHTML = h; pbRender();
  });
}
function pbPl(on, st, label, err) {
  if (!on) return '';
  var c = { '': ['#F1EEE6', '#77746C', 'czeka'], czeka: ['#FBF1E1', '#A86A0A', 'przetwarza'], gotowe: ['#E6F5EC', '#1F7A4D', 'OK'], blad: ['#FDECEA', '#B3261E', 'błąd'], przypomniano: ['#FBF1E1', '#A86A0A', 'do wrzucenia'] }[st || ''] || ['#F1EEE6', '#77746C', st];
  return '<span class="pill" title="' + esc(err || '') + '" style="background:' + c[0] + ';color:' + c[1] + ';margin:2px 4px 2px 0">' + label + ': ' + c[2] + '</span>';
}
function pbRender() {
  if (!PB.rows.length) { $('pb-list').innerHTML = '<div class="empty">Nie masz jeszcze publikacji. Kliknij „+ Nowa publikacja”, wrzuć filmik i opis.</div>'; return; }
  PB.sel = PB.sel || {};
  var h = '<table class="tbl resp"><thead><tr><th style="width:36px"><input type="checkbox" style="width:auto" onclick="event.stopPropagation()" onchange="window.pbSelAll(this.checked)"></th><th style="width:84px"></th><th>Publikacja</th><th>Kiedy</th><th>Gdzie</th><th></th></tr></thead><tbody>';
  for (var i = 0; i < PB.rows.length; i++) {
    var p = PB.rows[i], ed = p.stan === 'szkic' || p.stan === 'zaplanowany' || p.stan === 'blad';
    var errs = (p.fb_err ? '<div class="mut" style="color:var(--err);font-size:12.5px">FB: ' + esc(p.fb_err) + '</div>' : '') + (p.ig_err ? '<div class="mut" style="color:var(--err);font-size:12.5px">IG: ' + esc(p.ig_err) + '</div>' : '');
    var act = '';
    if (p.tt && p.tt_status !== 'gotowe' && p.plik) act += '<a class="btn sm" href="' + esc(p.url) + '" download>' + ic('download', 13) + ' Wideo</a> <a class="btn sm" onclick="window.pbCopy(' + p.id + ')">Kopiuj opis</a> <a class="btn sm" onclick="window.pbTt(' + p.id + ',1)">TikTok wrzucony ✓</a> ';
    if (p.stan === 'w_toku') act += '<a class="btn sm" onclick="window.pbCheck(' + p.id + ',this)">Sprawdź status</a> ';
    if (p.stan === 'blad') act += '<a class="btn sm dark" onclick="window.pbRetry(' + p.id + ',this)">Spróbuj ponownie</a> ';
    if (ed) act += '<a class="btn sm" onclick="window.pbEdit(' + p.id + ')">Edytuj</a> ';
    act += '<a class="btn sm red" onclick="window.pbDel(' + p.id + ')">' + ic('trash', 13) + '</a>';
    h += '<tr class="click" onclick="window.pbView(' + p.id + ')"><td onclick="event.stopPropagation()">' + (p.stan !== 'w_toku' ? '<input type="checkbox" style="width:auto" ' + (PB.sel[p.id] ? 'checked' : '') + ' onchange="window.pbSel(' + p.id + ',this.checked)">' : '') + '</td><td>' + (p.url ? '<video src="' + esc(p.url) + '#t=0.5" muted playsinline preload="metadata" style="width:72px;height:96px;object-fit:cover;border-radius:10px;background:#111;pointer-events:none"></video>' : '<div style="width:72px;height:96px;border-radius:10px;background:#F1EEE6"></div>') + '</td>' +
      '<td><div class="b1">' + esc(p.tytul || '(bez tytułu)') + ' <span class="pill" style="font-size:11px;padding:2px 8px">' + esc(PBS[p.stan] || p.stan) + '</span></div><div class="mut" style="font-size:13px;max-width:420px;white-space:pre-line;max-height:3.9em;overflow:hidden">' + esc(p.opis || '') + '</div>' + errs + '</td>' +
      '<td style="white-space:nowrap">' + esc(pbWhen(p.kiedy)) + '</td>' +
      '<td>' + pbPl(+p.fb, p.fb_status, 'FB', p.fb_err) + pbPl(+p.ig, p.ig_status, 'IG', p.ig_err) + pbPl(+p.tt, p.tt_status, 'TikTok') + '</td>' +
      '<td style="text-align:right" onclick="event.stopPropagation()"><div style="display:flex;gap:6px;flex-wrap:wrap;justify-content:flex-end">' + act + '</div></td></tr>';
  }
  $('pb-list').innerHTML = h + '</tbody></table>';
  pbBulkBar();
}
function pbBulkBar() { var n = 0; for (var k in PB.sel) if (PB.sel[k]) n++; var b = $('pb-bulk'); if (!b) return; b.style.display = n ? 'flex' : 'none'; $('pb-bn').textContent = 'Zaznaczono: ' + n; }
window.pbSel = function (id, on) { PB.sel[id] = on; pbBulkBar(); };
window.pbSelAll = function (on) { PB.sel = {}; if (on) for (var i = 0; i < PB.rows.length; i++) if (PB.rows[i].stan !== 'w_toku') PB.sel[PB.rows[i].id] = true; pbRender(); };
window.pbDelSel = function () {
  var ids = []; for (var k in PB.sel) if (PB.sel[k]) ids.push(+k);
  if (!ids.length) return;
  if (!sure('Usunąć ' + ids.length + ' publikacji?', 'Znikną z panelu razem z filmikami. To, co już poszło na Facebooka i Instagrama, tam zostaje.', 'Usuń', function () { window.pbDelSel(); }, true)) return;
  api('soc_usun_wiele', { ids: ids }, function (r) { PB.sel = {}; toast('Usunięto: ' + r.n); viewPublikacje(); });
};
window.pbDelAll = function () {
  if (!sure('Usunąć wszystkie zaplanowane i szkice?', 'Opublikowane zostaną na liście. Potem możesz wgrać wszystko jeszcze raz.', 'Usuń wszystkie', function () { window.pbDelAll(); }, true)) return;
  api('soc_usun_wiele', { wszystkie: 1 }, function (r) { PB.sel = {}; toast('Usunięto: ' + r.n); viewPublikacje(); });
};

window.pbView = function (id) {
  var p = pbFind(id); if (!p) return;
  var ed = p.stan === 'szkic' || p.stan === 'zaplanowany' || p.stan === 'blad';
  var gdzie = pbPl(+p.fb, p.fb_status, 'Facebook', p.fb_err) + pbPl(+p.ig, p.ig_status, 'Instagram', p.ig_err) + pbPl(+p.tt, p.tt_status, 'TikTok');
  var b = '<div style="display:flex;gap:20px;flex-wrap:wrap;align-items:flex-start">' +
    '<div style="flex:none;width:250px;max-width:100%">' + (p.url ? '<video src="' + esc(p.url) + '" controls playsinline autoplay muted style="width:100%;aspect-ratio:9/16;object-fit:cover;border-radius:16px;background:#111"></video>' : '<div class="empty">Brak filmiku</div>') + '</div>' +
    '<div style="flex:1;min-width:220px"><div class="mut" style="font-size:12.5px">Kiedy</div><div class="b1" style="margin-bottom:10px">' + esc(pbWhen(p.kiedy)) + ' · ' + esc(PBS[p.stan] || p.stan) + '</div>' +
    '<div class="mut" style="font-size:12.5px">Gdzie</div><div style="margin-bottom:10px">' + gdzie + '</div>' +
    '<div class="mut" style="font-size:12.5px">Opis</div><div style="white-space:pre-line;background:#F7F5EF;border-radius:12px;padding:12px 14px;font-size:14px;max-height:340px;overflow:auto">' + esc(p.opis || '(brak opisu)') + '</div></div></div>';
  var foot = '<a class="btn" onclick="window.closeModal()">Zamknij</a>' + (p.opis ? '<a class="btn" onclick="window.pbCopy(' + p.id + ')">Kopiuj opis</a>' : '') + (ed ? '<a class="btn dark" onclick="window.closeModal();window.pbEdit(' + p.id + ')">Edytuj</a>' : '');
  window.openModal(p.tytul || 'Podgląd publikacji', b, foot, true);
};
function pbFind(id) { for (var i = 0; i < PB.rows.length; i++) if (+PB.rows[i].id === +id) return PB.rows[i]; return null; }
window.pbNew = function () { PB.ed = { id: 0, tytul: '', opis: '', plik: '', url: '', kiedy: '', fb: 1, ig: 1, tt: 1 }; pbModal(); };
window.pbEdit = function (id) { var p = pbFind(id); if (!p) return; PB.ed = { id: p.id, tytul: p.tytul, opis: p.opis, plik: p.plik, url: p.url, kiedy: p.kiedy, fb: +p.fb, ig: +p.ig, tt: +p.tt }; pbModal(); };
function pbModal() {
  var e = PB.ed, m = PB.meta;
  var def = e.kiedy ? pbLocal(e.kiedy) : (function () { var d = new Date(); d.setDate(d.getDate() + (d.getHours() >= 19 ? 1 : 0)); d.setHours(19, 0, 0, 0); return d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2) + 'T19:00'; })();
  var b = '<label>Filmik (MP4, pionowy 9:16)</label>' +
    '<div id="pb-vid">' + pbVidBox() + '</div>' +
    '<input type="file" id="pb-file" accept="video/mp4,video/quicktime,.mp4,.mov" style="display:none" onchange="window.pbUpload(this)">' +
    '<label>Tytuł (tylko dla Ciebie)</label><input id="pb-t" value="' + esc(e.tytul) + '" placeholder="np. TikTok 11 – szybki test">' +
    '<label>Opis (ten sam na wszystkie platformy) <span class="mut" id="pb-n" style="font-weight:400"></span></label><textarea id="pb-o" rows="7" oninput="window.pbCount()" placeholder="Wklej opis z hasztagami">' + esc(e.opis) + '</textarea>' +
    '<label>Gdzie publikować</label><div class="row" style="gap:18px;flex-wrap:wrap">' +
    '<label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600"><input type="checkbox" id="pb-fb" style="width:auto"' + (e.fb ? ' checked' : '') + '> Facebook (Reels)</label>' +
    '<label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600"><input type="checkbox" id="pb-ig" style="width:auto"' + (e.ig ? ' checked' : '') + '> Instagram (Reels)</label>' +
    '<label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600"><input type="checkbox" id="pb-tt" style="width:auto"' + (e.tt ? ' checked' : '') + '> TikTok (przypomnienie)</label></div>' +
    (m.ok ? '' : '<div class="mut" style="font-size:12.5px;margin-top:6px;color:var(--err)">Facebook i Instagram nie są jeszcze połączone – możesz zapisać szkic albo zaplanować sam TikTok.</div>') +
    '<label>Kiedy</label><input type="datetime-local" id="pb-k" value="' + esc(def) + '">' +
    '<div class="mut" style="font-size:12.5px;margin-top:6px">Panel sprawdza zaplanowane publikacje co 5 minut. TikToka wrzucasz sam – o tej godzinie dostaniesz pusha z opisem.</div>';
  var foot = '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn" onclick="window.pbSave(\'szkic\',this)">Zapisz szkic</a><a class="btn" onclick="window.pbSave(\'teraz\',this)">Opublikuj teraz</a><a class="btn dark" onclick="window.pbSave(\'zaplanuj\',this)">Zaplanuj</a>';
  window.openModal(e.id ? 'Edytuj publikację' : 'Nowa publikacja', b, foot);
  window.pbCount();
}
function pbVidBox() {
  var e = PB.ed;
  if (e.url) return '<div class="row" style="gap:14px;align-items:center"><video src="' + esc(e.url) + '#t=0.5" controls playsinline preload="metadata" style="width:120px;height:213px;object-fit:cover;border-radius:12px;background:#111"></video><div><div class="b1">Filmik wgrany ✓</div><a class="btn sm" style="margin-top:8px" onclick="$(\'pb-file\').click()">Zmień filmik</a></div></div>';
  return '<a class="btn" onclick="document.getElementById(\'pb-file\').click()">' + ic('upload', 14) + ' Wybierz filmik z dysku</a><div id="pb-prog" class="mut" style="margin-top:8px;font-size:13px"></div>';
}
window.pbCount = function () { var o = $('pb-o'); if (!o) return; var n = o.value.length; $('pb-n').textContent = n + ' / 2200'; $('pb-n').style.color = n > 2200 ? 'var(--err)' : ''; };
window.pbUpload = function (inp) {
  var f = inp.files && inp.files[0]; if (!f) return;
  var ext = (f.name.split('.').pop() || 'mp4').toLowerCase();
  if (ext !== 'mp4' && ext !== 'mov') { toast('Wybierz plik MP4 albo MOV', 'err'); return; }
  if (f.size > 300 * 1024 * 1024) { toast('Plik za duży (max 300 MB)', 'err'); return; }
  var CH = 4 * 1024 * 1024, total = Math.max(1, Math.ceil(f.size / CH)), uid = (Math.random().toString(36).slice(2) + Date.now().toString(36)).slice(0, 20), part = 0;
  $('pb-vid').innerHTML = '<div class="mut" id="pb-prog">Wgrywam… 0%</div><div style="height:8px;border-radius:4px;background:#EEE;margin-top:8px;overflow:hidden"><i id="pb-bar" style="display:block;height:100%;width:0;background:#C5A059"></i></div>';
  function next() {
    var x = new XMLHttpRequest();
    x.open('POST', 'social_upload.php?uid=' + uid + '&part=' + part + '&total=' + total + '&ext=' + ext);
    x.setRequestHeader('X-CSRF', window.CSRF);
    x.setRequestHeader('Content-Type', 'application/octet-stream');
    x.onload = function () {
      var r = null; try { r = JSON.parse(x.responseText); } catch (e) { }
      if (!r || !r.ok) { toast((r && r.error) || 'Błąd wgrywania', 'err'); PB.ed.url = ''; $('pb-vid').innerHTML = pbVidBox(); return; }
      part++;
      var pc = Math.round(part * 100 / total); if ($('pb-prog')) $('pb-prog').textContent = 'Wgrywam… ' + pc + '%'; if ($('pb-bar')) $('pb-bar').style.width = pc + '%';
      if (part < total) { next(); return; }
      PB.ed.plik = r.plik; PB.ed.url = r.url; $('pb-vid').innerHTML = pbVidBox(); toast('Filmik wgrany (' + r.mb + ' MB)');
      if (!val('pb-t')) $('pb-t').value = f.name.replace(/\.[^.]+$/, '');
    };
    x.onerror = function () { toast('Brak połączenia – spróbuj jeszcze raz', 'err'); PB.ed.url = ''; $('pb-vid').innerHTML = pbVidBox(); };
    x.send(f.slice(part * CH, Math.min(f.size, (part + 1) * CH)));
  }
  next();
};

// ---------- import wielu filmików + plik TXT z opisami ----------
// Format TXT: bloki zaczynające się od linii "=== 11 ===" (numer = numer w nazwie pliku, np. elok-tiktok-11.mp4).
// Kolejność bloków w pliku = kolejność publikacji.
var PBB = { files: [], opisy: [], plan: [] };
function pbNum(name) { var m = String(name).replace(/\.[^.]+$/, '').match(/(\d+)(?!.*\d)/); return m ? parseInt(m[1], 10) : null; }
function pbParseTxt(t) {
  var out = [], lines = String(t).replace(/\r/g, '').split('\n'), cur = null;
  for (var i = 0; i < lines.length; i++) {
    var m = lines[i].match(/^\s*={2,}\s*(?:[A-Za-zĄĆĘŁŃÓŚŹŻąćęłńóśźż ]*?)?(\d+)\b.*?={2,}\s*$/);
    if (m) { if (cur) out.push(cur); cur = { n: parseInt(m[1], 10), t: [] }; continue; }
    if (cur) cur.t.push(lines[i]);
  }
  if (cur) out.push(cur);
  for (i = 0; i < out.length; i++) out[i].t = out[i].t.join('\n').replace(/^\s+|\s+$/g, '');
  return out;
}
window.pbBulk = function () {
  PBB = { files: [], opisy: [], plan: [] };
  var d = new Date(); d.setDate(d.getDate() + 1);
  var ds = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2);
  var b = '<div class="mut" style="font-size:13.5px;line-height:1.6">Wybierz <b>wszystkie filmiki naraz</b> i <b>plik TXT z opisami</b>. Panel dopasuje opis do filmiku po numerze (np. <code>elok-tiktok-11.mp4</code> ↔ <code>=== 11 ===</code>) i rozplanuje je po kolei – w kolejności z pliku TXT.</div>' +
    '<label>1. Skąd filmiki</label><div class="row" style="gap:18px;flex-wrap:wrap;margin-bottom:6px">' +
    '<label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600"><input type="radio" name="pbb-src" style="width:auto" checked onchange="window.pbbSrc(0)"> Z dysku (trzymasz stronę otwartą)</label>' +
    '<label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600"><input type="radio" name="pbb-src" style="width:auto" onchange="window.pbbSrc(1)"> Z serwera (wrzucone FileZillą)</label></div>' +
    '<div id="pbb-src0"><input type="file" id="pbb-v" multiple accept="video/mp4,video/quicktime,.mp4,.mov" onchange="window.pbbFiles(this)"></div>' +
    '<div id="pbb-src1" style="display:none"><div class="mut" style="font-size:13px;line-height:1.6">Wrzuć filmiki FileZillą do folderu panelu <code>uploads/social/wrzuc/</code> (FileZilla wgrywa w tle – możesz robić co innego). Potem kliknij „Odśwież”.</div><a class="btn sm" style="margin-top:6px" onclick="window.pbbInbox()">Odśwież listę z serwera</a> <span id="pbb-inb" class="mut" style="font-size:13px"></span></div>' +
    '<label>2. Plik TXT z opisami</label><input type="file" id="pbb-t" accept=".txt,text/plain" onchange="window.pbbTxt(this)">' +
    '<div class="row" style="gap:12px;flex-wrap:wrap"><div style="flex:1;min-width:150px"><label>Pierwszy dzień</label><input type="date" id="pbb-d" value="' + ds + '" onchange="window.pbbPlan()"></div>' +
    '<div style="flex:1;min-width:110px"><label>Godzina</label><input type="time" id="pbb-h" value="19:00" onchange="window.pbbPlan()"></div>' +
    '<div style="flex:1;min-width:110px"><label>Co ile dni</label><input type="number" id="pbb-n" value="1" min="1" max="14" onchange="window.pbbPlan()"></div></div>' +
    '<label>Gdzie publikować</label><div class="row" style="gap:18px;flex-wrap:wrap">' +
    '<label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600"><input type="checkbox" id="pbb-fb" style="width:auto" checked> Facebook</label>' +
    '<label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600"><input type="checkbox" id="pbb-ig" style="width:auto" checked> Instagram</label>' +
    '<label style="display:flex;gap:8px;align-items:center;margin:0;font-weight:600"><input type="checkbox" id="pbb-tt" style="width:auto" checked> TikTok (przypomnienie)</label></div>' +
    '<div id="pbb-prev" style="margin-top:14px"></div>';
  window.openModal('Dodaj wiele publikacji naraz', b, '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn dark" id="pbb-go" onclick="window.pbbGo(this)">Wgraj i zaplanuj</a>', true);
};
window.pbbSrc = function (k) { $('pbb-src0').style.display = k ? 'none' : ''; $('pbb-src1').style.display = k ? '' : 'none'; PBB.files = []; if (k) window.pbbInbox(); else window.pbbPlan(); };
window.pbbInbox = function () {
  $('pbb-inb').textContent = 'Sprawdzam…';
  api('soc_inbox', {}, function (r) {
    PBB.files = []; for (var i = 0; i < r.files.length; i++) PBB.files.push({ name: r.files[i].name, size: r.files[i].size, server: true });
    $('pbb-inb').textContent = r.files.length ? 'Na serwerze: ' + r.files.length + ' filmików' : 'Folder wrzuc jest pusty';
    window.pbbPlan();
  });
};
window.pbbFiles = function (inp) { PBB.files = []; for (var i = 0; i < inp.files.length; i++) PBB.files.push(inp.files[i]); window.pbbPlan(); };
window.pbbTxt = function (inp) {
  var f = inp.files && inp.files[0]; if (!f) return;
  var r = new FileReader(); r.onload = function () { PBB.opisy = pbParseTxt(r.result); if (!PBB.opisy.length) toast('Nie widzę bloków „=== numer ===” w pliku', 'err'); window.pbbPlan(); }; r.readAsText(f, 'UTF-8');
};
window.pbbPlan = function () {
  var byNum = {}, i, used = {}, plan = [];
  for (i = 0; i < PBB.files.length; i++) { var n = pbNum(PBB.files[i].name); if (n !== null) byNum[n] = PBB.files[i]; }
  // kolejność: najpierw wg pliku TXT, potem filmiki bez opisu (rosnąco)
  for (i = 0; i < PBB.opisy.length; i++) { var o = PBB.opisy[i]; plan.push({ n: o.n, f: byNum[o.n] || null, opis: o.t }); used[o.n] = 1; }
  var rest = []; for (var k in byNum) if (byNum.hasOwnProperty(k) && !used[k]) rest.push(+k); rest.sort(function (a, b) { return a - b; });
  for (i = 0; i < rest.length; i++) plan.push({ n: rest[i], f: byNum[rest[i]], opis: '' });
  for (i = 0; i < PBB.files.length; i++) if (pbNum(PBB.files[i].name) === null) plan.push({ n: null, f: PBB.files[i], opis: '' });
  var d0 = val('pbb-d'), hh = val('pbb-h') || '19:00', step = Math.max(1, parseInt(val('pbb-n'), 10) || 1), j = 0;
  // już dodane w panelu (po numerze w tytule) – pomijamy, ale zostawiamy im ich dzień w kolejce
  var have = {};
  for (i = 0; i < PB.rows.length; i++) { var rn = pbNum(PB.rows[i].tytul); if (rn !== null && PB.rows[i].stan !== 'blad') have[rn] = PB.rows[i]; }
  for (i = 0; i < plan.length; i++) {
    plan[i].jest = plan[i].n !== null && have[plan[i].n] ? have[plan[i].n] : null;
    if (!plan[i].f && !plan[i].jest) { plan[i].kiedy = ''; continue; }
    var d = new Date(d0 + 'T' + hh + ':00'); d.setDate(d.getDate() + j * step); j++;
    plan[i].kiedy = d.getFullYear() + '-' + ('0' + (d.getMonth() + 1)).slice(-2) + '-' + ('0' + d.getDate()).slice(-2) + ' ' + hh + ':00';
  }
  PBB.plan = plan;
  if (!plan.length) { $('pbb-prev').innerHTML = ''; return; }
  var h = '<table class="tbl"><thead><tr><th>#</th><th>Filmik</th><th>Opis</th><th>Kiedy</th></tr></thead><tbody>';
  for (i = 0; i < plan.length; i++) {
    var p = plan[i];
    if (p.jest) { h += '<tr style="opacity:.55"><td>' + p.n + '</td><td colspan="2">' + esc(p.jest.tytul) + ' – <b>już jest w panelu</b>, pomijam</td><td style="white-space:nowrap">' + esc(pbWhen(p.jest.kiedy)) + '</td></tr>'; continue; }
    h += '<tr><td>' + (p.n === null ? '—' : p.n) + '</td><td>' + (p.f ? esc(p.f.name) + ' <span class="mut">(' + Math.round(p.f.size / 104857.6) / 10 + ' MB)</span>' : '<span style="color:var(--err)">brak filmiku – pominę</span>') + '</td>' +
      '<td class="mut" style="font-size:12.5px;max-width:280px">' + (p.opis ? esc(p.opis.slice(0, 70)) + (p.opis.length > 70 ? '…' : '') : '<span style="color:var(--err)">brak opisu</span>') + '</td>' +
      '<td style="white-space:nowrap" id="pbb-s' + i + '">' + (p.kiedy ? esc(pbWhen(p.kiedy)) : '—') + '</td></tr>';
  }
  $('pbb-prev').innerHTML = h + '</tbody></table>';
};
// wgrywanie jednego pliku kawałkami; cb(err, wynik)
function pbUp(f, onProg, cb) {
  var ext = (f.name.split('.').pop() || 'mp4').toLowerCase();
  if (ext !== 'mp4' && ext !== 'mov') { cb('Plik ' + f.name + ' nie jest MP4/MOV'); return; }
  var CH = 2 * 1024 * 1024, total = Math.max(1, Math.ceil(f.size / CH)), uid = (Math.random().toString(36).slice(2) + Date.now().toString(36)).slice(0, 20), part = 0, tries = 0;
  function retry(msg) { tries++; if (tries > 3 || part === 0 && tries > 3) { cb(msg); return; } onProg(-1); setTimeout(function () { if (part === 0) next(); else { /* kawałek mógł nie dojść – zaczynamy plik od nowa */ part = 0; uid = (Math.random().toString(36).slice(2) + Date.now().toString(36)).slice(0, 20); next(); } }, 2000); }
  function next() {
    var x = new XMLHttpRequest();
    x.open('POST', 'social_upload.php?uid=' + uid + '&part=' + part + '&total=' + total + '&ext=' + ext);
    x.timeout = 90000;
    x.setRequestHeader('X-CSRF', window.CSRF); x.setRequestHeader('Content-Type', 'application/octet-stream');
    x.onload = function () { var r = null; try { r = JSON.parse(x.responseText); } catch (e) { } if (!r || !r.ok) { if (r && r.error) { cb(r.error); return; } retry('Błąd wgrywania'); return; } part++; onProg(Math.round(part * 100 / total)); if (part < total) next(); else cb(null, r); };
    x.onerror = function () { retry('Brak połączenia'); };
    x.ontimeout = function () { retry('Za wolne połączenie'); };
    x.send(f.slice(part * CH, Math.min(f.size, (part + 1) * CH)));
  }
  next();
}
window.pbbGo = function (btn) {
  var todo = []; for (var i = 0; i < PBB.plan.length; i++) if (PBB.plan[i].f && !PBB.plan[i].jest) todo.push(i);
  if (!todo.length) { toast(PBB.plan.length ? 'Wszystkie te filmiki już są w panelu' : 'Wybierz filmiki', 'err'); return; }
  var fb = $('pbb-fb').checked ? 1 : 0, ig = $('pbb-ig').checked ? 1 : 0, tt = $('pbb-tt').checked ? 1 : 0;
  if (!fb && !ig && !tt) { toast('Zaznacz, gdzie publikować', 'err'); return; }
  if ((fb || ig) && !PB.meta.ok) { toast('Najpierw połącz Facebooka i Instagrama', 'err'); return; }
  btn.setAttribute('disabled', '1'); var k = 0, ok = 0, errs = 0;
  function one() {
    if (k >= todo.length) { btn.removeAttribute('disabled'); window.closeModal(); toast('Zaplanowano: ' + ok + (errs ? ' · błędy: ' + errs : '')); viewPublikacje(); return; }
    var idx = todo[k], p = PBB.plan[idx], cell = $('pbb-s' + idx);
    btn.innerHTML = 'Wgrywam ' + (k + 1) + ' / ' + todo.length + '…';
    var upl = p.f.server ? function (f, prog, cb) { prog(50); api('soc_inbox_take', { name: f.name }, function (r) { cb(null, r); }, function (e) { cb((e && e.error) || 'Błąd'); }); } : pbUp;
    upl(p.f, function (pc) { if (cell) cell.innerHTML = pc < 0 ? '<span class="mut">ponawiam…</span>' : '<b>' + pc + '%</b>'; }, function (err, r) {
      if (err) { errs++; if (cell) cell.innerHTML = '<span style="color:var(--err)">' + esc(err) + '</span>'; k++; one(); return; }
      api('soc_zapisz', { id: 0, hurt: 1, akcja: 'zaplanuj', tytul: p.f.name.replace(/\.[^.]+$/, ''), opis: p.opis, plik: r.plik, fb: fb, ig: ig, tt: tt, kiedy: p.kiedy }, function (rr) {
        if (rr.pominieto) { if (cell) cell.innerHTML = '<span class="mut">już był – pominięto</span>'; k++; one(); return; }
        ok++; if (cell) cell.innerHTML = '<span style="color:var(--ok)">✓ ' + esc(pbWhen(p.kiedy)) + '</span>'; k++; one();
      }, function () { errs++; if (cell) cell.innerHTML = '<span style="color:var(--err)">błąd zapisu</span>'; k++; one(); });
    });
  }
  one();
};
window.pbSave = function (akcja, btn) {
  var e = PB.ed;
  var d = { id: e.id, akcja: akcja, tytul: val('pb-t'), opis: val('pb-o'), plik: e.plik, fb: $('pb-fb').checked ? 1 : 0, ig: $('pb-ig').checked ? 1 : 0, tt: $('pb-tt').checked ? 1 : 0, kiedy: val('pb-k').replace('T', ' ') };
  e.tytul = d.tytul; e.opis = d.opis; e.fb = d.fb; e.ig = d.ig; e.tt = d.tt; e.kiedy = d.kiedy;
  if (akcja === 'teraz') { window.ask('Opublikować teraz?', 'Filmik od razu pójdzie na zaznaczone platformy.', 'Tak, publikuj', function () { pbSend(d, akcja, null); }); return; }
  pbSend(d, akcja, btn);
};
function pbSend(d, akcja, btn) {
  busy(btn, true);
  if (!btn) toast('Publikuję…');
  api('soc_zapisz', d, function (r) {
    window.closeModal();
    toast(akcja === 'szkic' ? 'Szkic zapisany' : akcja === 'teraz' ? 'Publikuję – ' + (r.info || 'gotowe') : 'Zaplanowano');
    viewPublikacje();
  }, function () { busy(btn, false); if (!btn) pbModal(); });
}
window.pbDel = function (id) { if (!sure('Usunąć publikację?', 'Usuniesz ją z panelu (z Facebooka i Instagrama nie znika).', 'Usuń', function () { window.pbDel(id); }, true)) return; api('soc_usun', { id: id }, function () { toast('Usunięto'); viewPublikacje(); }); };
window.pbTt = function (id, done) { api('soc_tt', { id: id, done: done }, function () { toast('Oznaczono TikTok jako wrzucony'); viewPublikacje(); }); };
window.pbCheck = function (id, btn) { busy(btn, true); api('soc_odswiez', { id: id }, function (r) { toast(r.info); viewPublikacje(); }, function () { busy(btn, false); }); };
window.pbRetry = function (id, btn) { busy(btn, true); api('soc_ponow', { id: id }, function (r) { toast(r.info); viewPublikacje(); }, function () { busy(btn, false); }); };
window.pbCopy = function (id) {
  var p = pbFind(id); if (!p) return;
  var t = document.createElement('textarea'); t.value = p.opis || ''; document.body.appendChild(t); t.select();
  try { document.execCommand('copy'); toast('Opis skopiowany'); } catch (e) { toast('Nie udało się skopiować', 'err'); }
  document.body.removeChild(t);
};
window.pbIgRefresh = function (a) { api('meta_odswiez_ig', {}, function (r) { toast(r.ig ? 'Instagram podpięty: @' + r.ig : 'Nadal nie widzę Instagrama przy tej stronie'); viewPublikacje(); }); };
window.pbConnect = function () {
  var b = '<div class="mut" style="font-size:13.5px;line-height:1.6">' +
    '<b>1.</b> Wejdź na <a class="ilink" href="https://developers.facebook.com/apps" target="_blank">developers.facebook.com/apps</a> → <b>Utwórz aplikację</b> (typ: Firma / „Inne”).<br>' +
    '<b>2.</b> W aplikacji: <b>Ustawienia → Podstawowe</b> – skopiuj <b>Identyfikator aplikacji</b> i <b>Klucz tajny</b>.<br>' +
    '<b>3.</b> Otwórz <a class="ilink" href="https://developers.facebook.com/tools/explorer/" target="_blank">Graph API Explorer</a>, wybierz swoją aplikację i dodaj uprawnienia: <code>pages_show_list</code>, <code>pages_read_engagement</code>, <code>pages_manage_posts</code>, <code>business_management</code>, <code>instagram_basic</code>, <code>instagram_content_publish</code>.<br>' +
    '<b>4.</b> Kliknij <b>Generate Access Token</b>, zaznacz stronę ELOK (i konto Instagram) i skopiuj token.<br>' +
    '<b>5.</b> Wklej wszystko poniżej i kliknij Połącz – panel sam zamieni token na stały.</div>' +
    '<label>Identyfikator aplikacji (App ID)</label><input id="mc-app" inputmode="numeric">' +
    '<label>Klucz tajny aplikacji (App Secret)</label><input id="mc-sec" type="password" autocomplete="off">' +
    '<label>Token z Graph API Explorer</label><textarea id="mc-tok" rows="3"></textarea><div id="mc-pages"></div>' +
    '<div class="mut" style="font-size:12px;margin-top:8px">Instagram musi być kontem <b>firmowym</b> albo <b>twórcy</b> i być połączony z Twoją stroną na Facebooku (w aplikacji Instagram: Ustawienia → Konto → Udostępnianie / Centrum kont).</div>';
  window.openModal('Połącz Facebook i Instagram', b, '<a class="btn" onclick="window.closeModal()">Anuluj</a>' + (PB.meta.ok ? '<a class="btn red" onclick="window.pbDisc()">Rozłącz</a>' : '') + '<a class="btn dark" onclick="window.pbConnGo(this)">Połącz</a>');
};
window.pbConnGo = function (btn) {
  busy(btn, true);
  api('meta_polacz', { app_id: val('mc-app'), secret: val('mc-sec'), token: val('mc-tok') }, function (r) {
    busy(btn, false);
    if (r.done) { window.closeModal(); toast('Połączono: ' + r.page + (r.ig ? ' + @' + r.ig : '')); viewPublikacje(); return; }
    var h = '<label>Wybierz stronę</label>'; for (var i = 0; i < r.pages.length; i++) h += '<a class="btn" style="display:block;margin-bottom:6px;text-align:left" onclick="window.pbPick(\'' + esc(r.pages[i].id) + '\')">' + esc(r.pages[i].name) + (r.pages[i].ig ? ' · @' + esc(r.pages[i].ig) : ' · (bez Instagrama)') + '</a>';
    $('mc-pages').innerHTML = h;
  }, function () { busy(btn, false); });
};
window.pbPick = function (id) { api('meta_wybierz', { page_id: id }, function (r) { window.closeModal(); toast('Połączono: ' + r.page + (r.ig ? ' + @' + r.ig : '')); viewPublikacje(); }); };
window.pbDisc = function () { if (!sure('Rozłączyć?', 'Panel przestanie publikować na Facebooku i Instagramie.', 'Rozłącz', function () { window.pbDisc(); }, true)) return; api('meta_rozlacz', {}, function () { window.closeModal(); toast('Rozłączono'); viewPublikacje(); }); };

// ---------- start ----------
if ($('main')) {
  var start = (location.hash || '#pulpit').slice(1);
  var ok = false; for (var i = 0; i < NAV.length; i++) if (NAV[i][0] === start) ok = true;
  window.go(ok ? start : 'pulpit');
  window.addEventListener('hashchange', function () { var v = location.hash.slice(1); if (v && v !== VIEW) window.go(v); });
}
})();
