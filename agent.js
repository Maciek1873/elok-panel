/* ELOK – Agent Eli: sam szuka firm (nowe firmy z CEIDG, ogłoszenia o pracę) i dokłada je do mailingu. Vanilla JS ES5. */
(function () {
'use strict';
var U = window.ELOKUI;
if (!U) return;
var api = U.api, esc = U.esc, toast = U.toast, busy = U.busy, stat = U.stat;
function $(id) { return document.getElementById(id); }
function fd(s) { if (!s) return '—'; var p = String(s).split(/[- :]/); return p[2] + '.' + p[1] + (p[3] ? ' ' + p[3] + ':' + p[4] : ''); }
var WOJ = ['dolnośląskie', 'kujawsko-pomorskie', 'lubelskie', 'lubuskie', 'łódzkie', 'małopolskie', 'mazowieckie', 'opolskie', 'podkarpackie', 'podlaskie', 'pomorskie', 'śląskie', 'świętokrzyskie', 'warmińsko-mazurskie', 'wielkopolskie', 'zachodniopomorskie'];
var LT = { znaleziono: 'Znalazła', kontakt: 'Kontakt', dodano: 'Do mailingu', blad: 'Błąd', info: 'Info' };
var LC = { znaleziono: 'st-wyslano', kontakt: 'st-otworzyl', dodano: 'st-zainteresowany', blad: 'st-blad', info: 'st-nowy' };
var D = null, TAB = 'nowe', SEL = {};

function sw(id, on, txt, sub) {
  return '<div class="autorow" style="margin:8px 0"><div style="flex:1"><b>' + txt + '</b>' + (sub ? '<div class="mut" style="font-size:12.5px">' + sub + '</div>' : '') + '</div>' +
    '<label class="sw"><input type="checkbox" id="' + id + '"' + (on ? ' checked' : '') + '><i></i></label></div>';
}
function dot(ok, txt, sub) {
  return '<div style="display:flex;gap:10px;align-items:flex-start;padding:8px 0;border-bottom:1px solid var(--line)"><span class="dot' + (ok ? ' on' : '') + '" style="margin-top:6px"></span><div><b>' + txt + '</b><div class="mut" style="font-size:12.5px">' + sub + '</div></div></div>';
}

function view() {
  $('main').innerHTML = '<div class="empty">Ładowanie…</div>';
  api('ag_stan', {}, function (d) { D = d; render(); });
}
function render() {
  var d = D, c = d.cfg, s = d.stan || {}, dz = s.dzis || { nowe: 0, praca: 0 }, L = d.licz, P = d.pol;
  var bN = null, bP = null;
  for (var i = 0; i < d.branze.length; i++) { if (d.branze[i].nazwa.indexOf('nowe') >= 0) bN = d.branze[i]; else bP = d.branze[i]; }
  var mN = bN ? d.mail[bN.id] : {}, mP = bP ? d.mail[bP.id] : {};
  var h = '<div class="head"><div><h1>Agent Eli</h1><p>Eli sama szuka firm, którym możemy pomóc: <b>nowo zarejestrowanych</b> (gratulacje + strona) i takich, które <b>szukają pracownika biurowego</b> (automatyzacje zamiast etatu). Znalezione firmy trafiają do mailingu, z dziennym limitem.</p></div>' +
    '<div class="row"><a class="btn" onclick="window.AG.run(this)">Uruchom teraz</a></div></div>';

  h += '<div class="card pad" style="margin-bottom:14px"><div class="autorow" style="margin:0"><span class="dot' + (c.on ? ' on' : '') + '"></span><div style="flex:1"><b>Agent ' + (c.on ? 'pracuje' : 'wyłączony') + '</b> <span class="mut">– sprawdza co 15 minut razem z wysyłką · ostatnio: ' + fd(s.ostatnio) + '</span>' +
    (s.wynik ? '<div class="mut" style="font-size:12.5px;margin-top:2px">' + esc(s.wynik) + '</div>' : '') + '</div>' +
    '<label class="sw"><input type="checkbox" id="ag-on"' + (c.on ? ' checked' : '') + ' onchange="window.AG.save()"><i></i></label></div></div>';

  h += '<div class="grid g5">' +
    stat('Dziś do mailingu', (+dz.nowe + +dz.praca), 'nowe firmy ' + (+dz.nowe) + ' · ogłoszenia ' + (+dz.praca)) +
    stat('Nowe firmy', L.nowe_znalezione, 'z mailem: ' + L.nowe_z_mailem + (L.nowe_szukam ? ' · sprawdzam: ' + L.nowe_szukam : '')) +
    stat('Ogłoszenia o pracę', L.praca_ogl, 'odrzucone: ' + L.praca_odrz + ' · bez maila: ' + L.praca_brak) +
    stat('Wysłane maile', (+(mN.wyslano || 0) + +(mP.wyslano || 0)), 'otworzyli: ' + (+(mN.otwarte || 0) + +(mP.otwarte || 0))) +
    stat('Odpisali', '<span style="color:var(--ok)">' + (+(mN.odpisali || 0) + +(mP.odpisali || 0)) + '</span>', 'w kolejce: ' + (+(mN.w_kolejce || 0) + +(mP.w_kolejce || 0))) + '</div>';

  // czeka na zatwierdzenie
  var cN = d.czekaN || [], cP = d.czekaP || [];
  h += '<div class="card sec"><div class="pad" style="padding-bottom:8px"><h2 class="ct" style="margin:0 0 8px">Do zatwierdzenia</h2><div class="chips">' +
    '<span class="chip' + (TAB === 'nowe' ? ' on' : '') + '" onclick="window.AG.tab(\'nowe\')">Nowe firmy <b>' + cN.length + '</b></span>' +
    '<span class="chip' + (TAB === 'praca' ? ' on' : '') + '" onclick="window.AG.tab(\'praca\')">Szukają pracownika <b>' + cP.length + '</b></span>' +
    '<span class="chip" onclick="window.AG.oferty()">Wszystkie ogłoszenia</span></div>' +
    '<p class="mut" style="margin:8px 0 0;font-size:12.5px">' + (TAB === 'nowe' ? (c.nowe.reczny ? 'Tu trafiają nowe firmy z mailem. Zaznacz i kliknij „Do mailingu”.' : 'Nowe firmy idą do mailingu same (tryb automatyczny) – tu pojawiają się tylko, gdy przekroczony jest dzienny limit.') : (c.praca.reczny ? 'Ogłoszenia, przy których Eli znalazła maila. Sprawdź, czy to firma, a nie agencja, i kliknij „Do mailingu”.' : 'Ogłoszenia idą do mailingu same – tu tylko te ponad dzienny limit.')) + '</p></div>';
  var rows = TAB === 'nowe' ? cN : cP;
  if (!rows.length) h += '<div class="empty" style="display:block;padding:22px">Nic nie czeka</div>';
  else {
    h += '<div class="row" style="padding:0 18px 10px"><a class="btn sm dark" onclick="window.AG.ok(this)">Do mailingu</a><a class="btn sm" onclick="window.AG.no(this)">Odrzuć</a><a class="btn sm" onclick="window.AG.all()">Zaznacz wszystkie</a><a class="btn sm" onclick="window.AG.prev()">Podgląd maila</a></div>';
    h += '<table class="tbl"><thead><tr><th style="width:30px"></th>' + (TAB === 'nowe' ? '<th>Firma</th><th>Miasto</th><th>E-mail</th><th>Strona</th>' : '<th>Ogłoszenie</th><th>Firma</th><th>E-mail</th><th>Skąd</th>') + '</tr></thead><tbody>';
    for (i = 0; i < rows.length; i++) {
      var r = rows[i], chk = '<td><input type="checkbox" ' + (SEL[r.id] ? 'checked ' : '') + 'onchange="window.AG.sel(' + r.id + ',this.checked)"></td>';
      if (TAB === 'nowe') h += '<tr>' + chk + '<td><b>' + esc(r.nazwa) + '</b><div class="mut" style="font-size:12px">' + esc(r.osoba || '') + '</div></td><td>' + esc(r.miasto) + '</td><td>' + esc(r.email) + '</td><td>' + (r.www ? '<a href="' + esc(r.www) + '" target="_blank" rel="noopener">' + esc(r.www.replace(/^https?:\/\//, '')) + '</a>' : '<span class="mut">brak – dobry kandydat</span>') + '</td></tr>';
      else h += '<tr>' + chk + '<td><a href="' + esc(r.url) + '" target="_blank" rel="noopener"><b>' + esc(r.tytul) + '</b></a><div class="mut" style="font-size:12px">' + esc(r.miasto) + ' · ' + fd(r.ogl_at) + '</div></td><td>' + esc(r.firma || r.konto) + (r.www ? '<div class="mut" style="font-size:12px">' + esc(r.www.replace(/^https?:\/\//, '')) + '</div>' : '') + '</td><td>' + esc(r.email) + '</td><td class="mut" style="font-size:12.5px">' + esc((r.powod || '').replace('kontakt: ', '')) + '</td></tr>';
    }
    h += '</tbody></table>';
  }
  h += '</div>';

  // zadania + połączenia
  h += '<div class="grid g2 sec">';
  h += '<div class="card pad"><h2 class="ct">1. Nowe firmy (CEIDG)</h2>' +
    sw('n-on', c.nowe.on, 'Szukaj nowo zarejestrowanych firm', 'Mail: gratulacje + propozycja strony i automatyzacji') +
    sw('n-r', c.nowe.reczny, 'Zatwierdzam ręcznie', 'Wyłączone = idą do mailingu same') +
    '<div class="g2f"><div><label class="f">Zarejestrowane w ostatnich (dni)</label><input id="n-dni" type="number" min="1" max="60" value="' + (+c.nowe.dni) + '"></div>' +
    '<div><label class="f">Dziennie do mailingu</label><input id="n-dz" type="number" min="0" max="300" value="' + (+c.nowe.dziennie) + '"></div></div>' +
    '<label class="f">Kody PKD (puste = wszystkie branże)</label><input id="n-pkd" value="' + esc(c.nowe.pkd) + '" placeholder="np. 4520Z, 4332Z, 9602Z">' +
    '<label class="f">Województwa (nic nie zaznaczone = cała Polska)</label><div class="chips" id="n-woj">';
  for (i = 0; i < WOJ.length; i++) h += '<span class="chip' + (c.nowe.woj.indexOf(WOJ[i]) >= 0 ? ' on' : '') + '" data-w="' + WOJ[i] + '" onclick="this.classList.toggle(\'on\')">' + WOJ[i] + '</span>';
  h += '</div><div class="row" style="margin-top:12px"><a class="btn dark" onclick="window.AG.save(this)">Zapisz</a><a class="btn" onclick="window.AG.prev(\'nowe\')">Podgląd maila</a>' + (bN ? '<a class="btn" onclick="window.AG.edit(' + bN.id + ')">Edytuj maile</a>' : '') + '</div></div>';

  h += '<div class="card pad"><h2 class="ct">2. Szukają pracownika (OLX)</h2>' +
    sw('p-on', c.praca.on, 'Szukaj ogłoszeń o pracę biurową', 'Mail: zamiast etatu – automatyzacje') +
    sw('p-r', c.praca.reczny, 'Zatwierdzam ręcznie', 'Na start zalecane – sprawdzasz, czy to prawdziwa firma') +
    '<div class="g2f"><div><label class="f">Dziennie do mailingu</label><input id="p-dz" type="number" min="0" max="300" value="' + (+c.praca.dziennie) + '"></div><div></div></div>' +
    '<label class="f">Czego szukać (jedna fraza w linii)</label><textarea id="p-fr" rows="6">' + esc(c.praca.frazy) + '</textarea>' +
    '<div class="hint">Eli pomija agencje pracy, „pracę zdalną” i ogłoszenia bez zadań typu faktury, zamówienia, dokumenty. Maila szuka w ogłoszeniu, na stronie firmy i w CEIDG.</div>' +
    '<div class="row" style="margin-top:12px"><a class="btn dark" onclick="window.AG.save(this)">Zapisz</a><a class="btn" onclick="window.AG.prev(\'praca\')">Podgląd maila</a>' + (bP ? '<a class="btn" onclick="window.AG.edit(' + bP.id + ')">Edytuj maile</a>' : '') + '</div></div>';
  h += '</div>';

  h += '<div class="grid g2 sec"><div class="card pad"><h2 class="ct">Z czym jest połączona</h2>' +
    dot(P.ceidg, 'CEIDG – rejestr firm', P.ceidg ? 'Token jest' + (P.ceidg_debug ? ' · ostatni błąd: ' + esc(P.ceidg_debug) : '') : 'Brak tokenu – wpisz w Ustawieniach') +
    dot(P.olx && P.olx.indexOf(' 200') > 0, 'OLX – ogłoszenia o pracę', P.olx ? 'Ostatnie zapytanie: ' + esc(P.olx) : 'Jeszcze nie pytała') +
    dot(P.auto, 'Mailing (wysyłka)', (P.auto ? 'Włączony' : 'Wyłączony – włącz na Pulpicie') + ' · godziny ' + esc(P.godz) + ' · limit łączny ' + P.limit + '/dzień') +
    dot(!!P.cron, 'Cron (co 15 minut)', 'Ostatnio: ' + fd(P.cron)) +
    dot(P.push, 'Powiadomienia na telefon', 'Wieczorem podsumowanie dnia') +
    (bN ? dot(+bN.aktywna, 'Branża „' + esc(bN.nazwa) + '”', 'limit ' + (+bN.limit_dzienny || 'bez') + '/dzień · w kolejce ' + (mN.w_kolejce || 0)) : '') +
    (bP ? dot(+bP.aktywna, 'Branża „' + esc(bP.nazwa) + '”', 'limit ' + (+bP.limit_dzienny || 'bez') + '/dzień · w kolejce ' + (mP.w_kolejce || 0)) : '') +
    '</div>';
  h += '<div class="card pad"><h2 class="ct">Co robiła</h2><div class="feed">';
  if (!d.log.length) h += '<div class="empty" style="display:block">Jeszcze nic – włącz agenta albo kliknij „Uruchom teraz”.</div>';
  for (i = 0; i < d.log.length; i++) {
    var l = d.log[i];
    h += '<div><span class="t">' + fd(l.created_at) + '</span><span><span class="pill sm ' + (LC[l.typ] || 'st-nowy') + '">' + esc(LT[l.typ] || l.typ) + '</span> ' + esc(l.info) + '</span></div>';
  }
  h += '</div></div></div>';
  $('main').innerHTML = h;
}

function gather() {
  var woj = [], ch = $('n-woj') ? $('n-woj').querySelectorAll('.chip.on') : [];
  for (var i = 0; i < ch.length; i++) woj.push(ch[i].getAttribute('data-w'));
  return {
    on: $('ag-on').checked,
    nowe: { on: $('n-on').checked, reczny: $('n-r').checked, dni: $('n-dni').value, dziennie: $('n-dz').value, pkd: $('n-pkd').value, woj: woj },
    praca: { on: $('p-on').checked, reczny: $('p-r').checked, dziennie: $('p-dz').value, frazy: $('p-fr').value }
  };
}
function ids() { var a = []; for (var k in SEL) if (SEL.hasOwnProperty(k) && SEL[k]) a.push(+k); return a; }

window.AG = {
  view: function () { SEL = {}; view(); },
  tab: function (t) { TAB = t; SEL = {}; render(); },
  sel: function (id, on) { SEL[id] = on; },
  all: function () { var rows = TAB === 'nowe' ? D.czekaN : D.czekaP; for (var i = 0; i < rows.length; i++) SEL[rows[i].id] = true; render(); },
  save: function (btn) {
    busy(btn, 1);
    api('ag_zapisz', gather(), function () { busy(btn, 0); toast('Zapisano'); view(); }, function () { busy(btn, 0); });
  },
  run: function (btn) {
    busy(btn, 1);
    api('ag_uruchom', {}, function (r) { busy(btn, 0); toast(r.wynik || 'Gotowe'); view(); }, function () { busy(btn, 0); });
  },
  ok: function (btn) {
    var a = ids(); if (!a.length) { toast('Zaznacz firmy', 'err'); return; }
    busy(btn, 1);
    api('ag_zatwierdz', { ids: a, typ: TAB }, function (r) { busy(btn, 0); toast('Dodano do mailingu: ' + r.n); SEL = {}; view(); }, function () { busy(btn, 0); });
  },
  no: function (btn) {
    var a = ids(); if (!a.length) { toast('Zaznacz firmy', 'err'); return; }
    busy(btn, 1);
    api('ag_odrzuc', { ids: a, typ: TAB }, function (r) { busy(btn, 0); toast('Odrzucono: ' + r.n); SEL = {}; view(); }, function () { busy(btn, 0); });
  },
  prev: function (typ) {
    typ = typ || TAB;
    var rows = typ === 'nowe' ? D.czekaN : D.czekaP, r = rows && rows[0] ? rows[0] : {};
    var a = ids(); for (var i = 0; i < rows.length; i++) if (a.length && rows[i].id === a[0]) r = rows[i];
    api('ag_podglad', { typ: typ, nazwa: r.nazwa || r.firma || r.konto || '', miasto: r.miasto || '', stanowisko: r.stanowisko || '' }, function (p) {
      window.openModal('Podgląd: ' + p.temat, '<iframe id="ag-pf" style="width:100%;height:520px;border:0;border-radius:10px;background:#F4F3EF"></iframe>', '<a class="btn" onclick="window.closeModal()">Zamknij</a>', true);
      var f = $('ag-pf'); f.contentWindow.document.open(); f.contentWindow.document.write(p.html); f.contentWindow.document.close();
    });
  },
  edit: function (bid) { if (window.edBranza) window.edBranza(bid); else window.go('maile'); },
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
  }
};
})();
