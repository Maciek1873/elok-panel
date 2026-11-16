/* ELOK – moduł Umowy (klienci, szablony, umowy, podpis online). Vanilla JS ES5, bez backticków i funkcji strzałkowych. */
(function () {
'use strict';
var U = window.ELOKUI;
if (!U) return;
var api = U.api, esc = U.esc, toast = U.toast, busy = U.busy;
function $(id) { return document.getElementById(id); }
function val(id) { var e = $(id); return e ? e.value : ''; }
function fd(s) { if (!s) return '—'; var p = String(s).split(/[- :]/); return p[2] + '.' + p[1] + '.' + p[0] + (p[3] ? ' ' + p[3] + ':' + p[4] : ''); }
function fdd(s) { if (!s) return '—'; var p = String(s).split(/[- :]/); return p[2] + '.' + p[1] + '.' + p[0]; }
function zl(n) { n = +n || 0; return n.toFixed(2).replace('.', ',').replace(/\B(?=(\d{3})+(?!\d))/g, ' ') + ' zł'; }
function ico(p, sz) { sz = sz || 16; return '<svg width="' + sz + '" height="' + sz + '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" style="flex-shrink:0">' + p + '</svg>'; }
var I = {
  plus: '<path d="M12 5v14M5 12h14"/>', back: '<path d="m15 18-6-6 6-6"/>', send: '<path d="M22 2 11 13M22 2l-7 20-4-9-9-4z"/>',
  pdf: '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><path d="M14 2v6h6M9 15h6M9 11h2"/>',
  copy: '<rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/>',
  undo: '<path d="M3 7v6h6"/><path d="M21 17a9 9 0 0 0-15-6.7L3 13"/>', x: '<path d="M18 6 6 18M6 6l12 12"/>', trash: '<path d="M3 6h18M8 6V4h8v2M19 6l-1 14H6L5 6"/>',
  upl: '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4M17 8l-5-5-5 5M12 3v12"/>', print: '<path d="M6 9V2h12v7M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><path d="M6 14h12v8H6z"/>',
  link: '<path d="M10 13a5 5 0 0 0 7.5.5l3-3a5 5 0 0 0-7-7l-1.7 1.7"/><path d="M14 11a5 5 0 0 0-7.5-.5l-3 3a5 5 0 0 0 7 7l1.7-1.7"/>',
  edit: '<path d="M12 20h9"/><path d="M16.5 3.5a2.1 2.1 0 0 1 3 3L7 19l-4 1 1-4z"/>', user: '<circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/>',
  check: '<path d="M20 6 9 17l-5-5"/>', search: '<circle cx="11" cy="11" r="7"/><path d="m20 20-4-4"/>', end: '<path d="M18.4 5.6a9 9 0 1 1-12.8 0M12 2v10"/>',
  drive: '<path d="M8 3h8l6 10-4 7H6l-4-7z"/><path d="M8 3l6 10M16 3l-6 10M2 13h20"/>', mail: '<path d="M4 5h16v14H4z"/><path d="m4 7 8 6 8-6"/>'
};
var STA = { szkic: 'Szkic', wyslana: 'Czeka na podpis', zawarta: 'Zawarta', anulowana: 'Anulowana', rozwiazana: 'Rozwiązana' };
var TYPY = { wdrozenie: 'Wdrożenie', utrzymanie: 'Utrzymanie (abonament)', protokol: 'Protokół odbioru', inne: 'Inna umowa' };
var LOGT = {
  utworzona: 'Utworzona', tresc_zmieniona: 'Zmieniono treść', zamrozona: 'Treść zamrożona', akcept_elok: 'Akceptacja ELOK', link_wyslany: 'Link wysłany',
  link_nowy: 'Nowy link', otwarta: 'Klient otworzył', pdf_pobrany: 'Pobrano PDF', kod_wyslany: 'Kod wysłany', kod_bledny: 'Błędny kod',
  podpisana: 'Podpisana przez klienta', pdf: 'PDF z kartą', pdf_wyslany: 'PDF wysłany', cofnieta: 'Cofnięta do szkicu', anulowana: 'Anulowana',
  rozwiazana: 'Rozwiązana', kwalifikowany: 'Podpis kwalifikowany', kopia_mail: 'Kopia na maila', kopia_drive: 'Kopia na Google Drive',
  wydruk: 'Wydruk', blad_maila: 'Błąd maila', blad_pdf: 'Błąd PDF', link_wygasl: 'Link wygasł'
};
function pillU(st) { return '<span class="pill um-st-' + esc(st) + '">' + esc(STA[st] || st) + '</span>'; }

var UM = { tab: 'umowy', st: '', q: '', rows: [], counts: {}, cur: null, timer: null, saving: false, sz: null, kl: null, back: null };
window.UM = UM;

function tabs() {
  var t = [['umowy', 'Umowy'], ['klienci', 'Klienci'], ['szablony', 'Szablony'], ['ustawienia', 'Ustawienia']], h = '<div class="um-tabs">';
  for (var i = 0; i < t.length; i++) h += '<a class="' + (UM.tab === t[i][0] ? 'on' : '') + '" onclick="window.umTab(\'' + t[i][0] + '\')">' + t[i][1] + '</a>';
  return h + '</div>';
}
UM.view = function () { window.umTab(UM.tab || 'umowy'); };
window.umTab = function (t) {
  UM.tab = t; UM.cur = null; UM.sz = null; clearTimeout(UM.timer);
  $('main').innerHTML = '<div class="empty">Ładowanie…</div>';
  if (t === 'klienci') klList(); else if (t === 'szablony') szList(); else if (t === 'ustawienia') ustView(); else umList();
};

// ================= LISTA UMÓW =================
function umList() {
  api('um_lista', { status: UM.st, q: UM.q }, function (r) {
    UM.rows = r.rows; UM.counts = r.counts;
    var c = r.counts, h = '<div class="head"><div><h1>Umowy</h1><p>Umowy z klientami: szablony, podgląd, podpis online i PDF w jednym miejscu.</p></div>' +
      '<div class="row" style="gap:8px"><a class="btn dark" onclick="window.umNowa()">' + ico(I.plus) + ' Nowa umowa</a></div></div>' + tabs();
    if (!r.ustawienia_ok) h += '<div class="card pad um-warn">' + ico('<path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="10"/>', 18) + '<div><b>Uzupełnij dane firmy do umów</b><div class="mut">Bez NIP-u, e-maila do umów i reprezentanta nie wyślesz umowy do podpisu.</div></div><a class="btn sm" onclick="window.umTab(\'ustawienia\')">Ustawienia</a></div>';
    h += '<div class="grid g4" style="margin-bottom:14px">' +
      U.stat('Szkice', c.szkic || 0, 'w przygotowaniu') + U.stat('Czeka na podpis', c.wyslana || 0, 'wysłane do klienta') +
      U.stat('Zawarte', c.zawarta || 0, r.do_druku ? r.do_druku + ' do wydruku' : 'wszystkie wydrukowane') +
      U.stat('Abonamenty / mies.', zl(r.abonamenty).replace(' zł', '<small> zł</small>'), r.abonamenty_n + ' umów utrzymania, netto') + '</div>';
    var ch = [['', 'Wszystkie'], ['szkic', 'Szkice'], ['wyslana', 'Czeka na podpis'], ['zawarta', 'Zawarte'], ['do_druku', 'Do wydruku'], ['anulowana', 'Anulowane'], ['rozwiazana', 'Rozwiązane']];
    h += '<div class="row" style="margin-bottom:12px;gap:10px"><div class="chips">';
    for (var i = 0; i < ch.length; i++) {
      var n = ch[i][0] === '' ? null : (ch[i][0] === 'do_druku' ? r.do_druku : (c[ch[i][0]] || 0));
      h += '<span class="chip' + (UM.st === ch[i][0] ? ' on' : '') + '" onclick="window.umSt(\'' + ch[i][0] + '\')">' + ch[i][1] + (n !== null ? ' <b>' + n + '</b>' : '') + '</span>';
    }
    h += '</div><div class="grow" style="max-width:320px;margin-left:auto"><input id="um-q" placeholder="Szukaj: numer, klient, NIP" value="' + esc(UM.q) + '" onkeydown="if(event.key===\'Enter\')window.umQ()"></div></div>';
    if (!r.rows.length) h += '<div class="card empty">' + (UM.q || UM.st ? 'Brak umów dla tych filtrów.' : 'Nie ma jeszcze umów. Kliknij „Nowa umowa” – najpierw wybierzesz klienta, potem szablon.') + '</div>';
    else {
      h += '<div class="card"><table class="tbl resp"><thead><tr><th>Numer</th><th>Klient</th><th>Rodzaj</th><th>Status</th><th class="hm">Kwota</th><th>Ostatnio</th></tr></thead><tbody>';
      for (i = 0; i < r.rows.length; i++) {
        var u = r.rows[i], kw = u.abonament !== null ? zl(u.abonament) + '/mies.' : (u.kwota !== null ? zl(u.kwota) : '—');
        var ost = u.status === 'zawarta' ? 'zawarta ' + fdd(u.zawarta_at) : (u.status === 'wyslana' ? (u.otwarta_at ? 'otwarta ' + fd(u.otwarta_at) : 'wysłana ' + fd(u.wyslana_at)) : fd(u.updated_at));
        h += '<tr class="click" onclick="window.umOpen(' + u.id + ')"><td class="b1" style="white-space:nowrap">' + esc(u.numer) + '</td><td>' + esc(u.klient || '—') + '</td><td class="mut">' + esc(u.szablon_nazwa) + '</td>' +
          '<td>' + pillU(u.status) + (u.status === 'zawarta' && !u.wydruk_at ? ' <span class="pill um-st-druk" title="Nie oznaczono wydruku">do wydruku</span>' : '') + (u.metoda === 'kwalifikowany' ? ' <span class="pill um-st-kw">kwalifikowany</span>' : '') + '</td>' +
          '<td class="hm num">' + kw + '</td><td class="mut">' + ost + '</td></tr>';
      }
      h += '</tbody></table></div>';
    }
    $('main').innerHTML = h;
  });
}
window.umSt = function (s) { UM.st = s; umList(); };
window.umQ = function () { UM.q = val('um-q'); umList(); };

// ================= NOWA UMOWA =================
var NW = { kl: [], sz: [], klient: 0, szablon: 0, powiazana: 0, um: [] };
window.umNowa = function (klientId, powiazanaId, typ) {
  NW.klient = klientId || 0; NW.szablon = 0; NW.powiazana = powiazanaId || 0; NW.typ = typ || '';
  api('um_klienci', {}, function (a) {
    api('um_szablony', {}, function (b) {
      NW.kl = a.rows; NW.sz = b.rows;
      if (NW.typ) for (var i = 0; i < NW.sz.length; i++) if (NW.sz[i].typ === NW.typ) { NW.szablon = NW.sz[i].id; break; }
      nwRender();
    });
  });
};
function nwRender() {
  var o = '<option value="0">— wybierz klienta —</option>';
  for (var i = 0; i < NW.kl.length; i++) o += '<option value="' + NW.kl[i].id + '"' + (+NW.klient === +NW.kl[i].id ? ' selected' : '') + '>' + esc(NW.kl[i].nazwa) + (NW.kl[i].nip ? ' · NIP ' + esc(NW.kl[i].nip) : '') + '</option>';
  var s = '';
  for (i = 0; i < NW.sz.length; i++) s += '<label class="um-pick' + (+NW.szablon === +NW.sz[i].id ? ' on' : '') + '"><input type="radio" name="nw-sz" value="' + NW.sz[i].id + '"' + (+NW.szablon === +NW.sz[i].id ? ' checked' : '') + ' onchange="window.nwSz(' + NW.sz[i].id + ')"><div><b>' + esc(NW.sz[i].nazwa) + '</b><div class="mut">' + esc(TYPY[NW.sz[i].typ] || '') + ' · numeracja ' + esc(NW.sz[i].prefiks) + '/001/' + new Date().getFullYear() + '</div></div></label>';
  var b = '<label class="f">1. Klient</label><div class="row" style="gap:8px;flex-wrap:nowrap"><select id="nw-kl" onchange="window.nwKl(this.value)">' + o + '</select><a class="btn" onclick="window.klForm(0,null,function(id){window.closeModal();window.umNowa(id,' + (+NW.powiazana) + ',\'' + esc(NW.typ) + '\')})">' + ico(I.plus) + ' Nowy</a></div>' +
    '<label class="f">2. Szablon</label><div class="um-picks">' + (s || '<div class="mut">Brak szablonów – dodaj je w zakładce Szablony.</div>') + '</div>' +
    '<div id="nw-pw"></div>';
  window.openModal('Nowa umowa', b, '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn dark" onclick="window.nwGo(this)">Utwórz szkic</a>');
  nwPow();
}
window.nwKl = function (v) { NW.klient = +v; nwPow(); };
window.nwSz = function (v) { NW.szablon = +v; var l = document.querySelectorAll('.um-pick'); for (var i = 0; i < l.length; i++) l[i].className = 'um-pick' + (l[i].querySelector('input').checked ? ' on' : ''); nwPow(); };
function nwPow() {
  var el = $('nw-pw'); if (!el) return;
  if (!NW.klient) { el.innerHTML = ''; return; }
  api('um_klient', { id: NW.klient }, function (r) {
    var t = null; for (var i = 0; i < NW.sz.length; i++) if (+NW.sz[i].id === +NW.szablon) t = NW.sz[i];
    var o = '<option value="0">— bez powiązania —</option>', any = false;
    for (i = 0; i < r.umowy.length; i++) { var u = r.umowy[i]; if (u.status === 'anulowana') continue; any = true; o += '<option value="' + u.id + '"' + (+NW.powiazana === +u.id || (!NW.powiazana && t && t.typ !== 'wdrozenie' && u.typ === 'wdrozenie' && u.status === 'zawarta') ? ' selected' : '') + '>' + esc(u.numer) + ' – ' + esc(u.szablon_nazwa) + ' (' + esc(STA[u.status] || u.status) + ')</option>'; }
    el.innerHTML = any ? '<label class="f">3. Powiązana umowa (opcjonalnie)</label><select id="nw-pow">' + o + '</select><div class="hint">Utrzymanie i protokół odbioru wskazują umowę wdrożeniową – jej numer wstawi się sam.</div>' : '';
  });
}
window.nwGo = function (btn) {
  NW.klient = +val('nw-kl');
  if (!NW.klient) { toast('Wybierz klienta', 'err'); return; }
  if (!NW.szablon) { toast('Wybierz szablon', 'err'); return; }
  busy(btn, true);
  api('um_nowa', { klient: NW.klient, szablon: NW.szablon, powiazana: +val('nw-pow') || 0 }, function (r) { window.closeModal(); UM.tab = 'umowy'; window.umOpen(r.id); }, function () { busy(btn, false); });
};

// ================= EDYTOR / KARTA UMOWY =================
window.umOpen = function (id) {
  clearTimeout(UM.timer);
  $('main').innerHTML = '<div class="empty">Ładowanie…</div>';
  api('um_get', { id: id }, function (r) { UM.cur = r; umRender(); window.scrollTo(0, 0); });
};
function umActions(u, r) {
  var a = [], pdfUrl = 'umowy_plik.php?id=' + u.id;
  if (u.status === 'szkic') {
    a.push('<a class="btn gold" onclick="window.umSend()">' + ico(I.send) + ' Wyślij do podpisu</a>');
    a.push('<a class="btn" href="' + pdfUrl + '" target="_blank" rel="noopener">' + ico(I.pdf) + ' Podgląd PDF</a>');
  } else if (u.status === 'wyslana') {
    a.push('<a class="btn" onclick="window.umResend(this)">' + ico(I.mail) + ' Wyślij link ponownie</a>');
    a.push('<a class="btn" onclick="window.umCopyLink(this)">' + ico(I.link) + ' Skopiuj nowy link</a>');
    a.push('<a class="btn" href="' + pdfUrl + '" target="_blank" rel="noopener">' + ico(I.pdf) + ' PDF</a>');
  } else if (u.status === 'zawarta' || u.status === 'rozwiazana') {
    a.push('<a class="btn dark" href="' + pdfUrl + '&typ=final" target="_blank" rel="noopener">' + ico(I.pdf) + ' PDF umowy</a>');
    if (u.status === 'zawarta' && u.metoda === 'online') a.push('<a class="btn" onclick="window.umPdfAgain(this)">' + ico(I.mail) + ' Wyślij PDF ponownie</a>');
  } else a.push('<a class="btn" href="' + pdfUrl + '" target="_blank" rel="noopener">' + ico(I.pdf) + ' PDF</a>');
  var more = [];
  if (u.status === 'szkic' || u.status === 'wyslana') more.push(['upl', 'Wgraj PDF z podpisem kwalifikowanym', 'window.umUpload()']);
  if (u.status === 'wyslana') more.push(['undo', 'Cofnij do szkicu (zmiana treści)', 'window.umBack()']);
  if (u.status === 'zawarta' && (u.typ === 'wdrozenie')) { more.push(['plus', 'Nowa umowa utrzymania do tej umowy', 'window.umNowa(' + u.klient_id + ',' + u.id + ',\'utrzymanie\')']); more.push(['plus', 'Nowy protokół odbioru do tej umowy', 'window.umNowa(' + u.klient_id + ',' + u.id + ',\'protokol\')']); }
  more.push(['copy', 'Duplikuj', 'window.umDup()']);
  if (u.status === 'zawarta') more.push(['end', 'Rozwiąż umowę', 'window.umTerminate()']);
  if (u.status === 'szkic' && +u.wersja === 0) more.push(['trash', 'Usuń szkic', 'window.umDel()']);
  else if (u.status === 'szkic' || u.status === 'wyslana') more.push(['x', 'Anuluj umowę', 'window.umCancel()']);
  var m = '<div class="um-more"><a class="btn" onclick="window.umMore(event)">Więcej</a><div class="um-menu" id="um-menu">';
  for (var i = 0; i < more.length; i++) m += '<a onclick="window.umMore();' + more[i][2] + '">' + ico(I[more[i][0]]) + ' ' + esc(more[i][1]) + '</a>';
  return a.join('') + m + '</div></div>';
}
window.umMore = function (e) { var m = $('um-menu'); if (!m) return; if (e) { e.stopPropagation(); m.className = m.className.indexOf('on') >= 0 ? 'um-menu' : 'um-menu on'; } else m.className = 'um-menu'; };
document.addEventListener('click', function () { var m = $('um-menu'); if (m) m.className = 'um-menu'; });

function umRender() {
  var r = UM.cur, u = r.u, k = r.klient || {}, h;
  h = '<div class="head"><div><a class="btn sm" onclick="window.umTab(\'umowy\')">' + ico(I.back, 14) + ' Wszystkie umowy</a>' +
    '<h1 style="margin-top:14px">' + esc(u.numer) + ' ' + pillU(u.status) + (u.metoda === 'kwalifikowany' ? ' <span class="pill um-st-kw">podpis kwalifikowany</span>' : '') + '</h1>' +
    '<p>' + esc(u.szablon_nazwa) + ' · <a class="um-a" onclick="window.klOpen(' + u.klient_id + ')">' + esc(k.nazwa || '—') + '</a>' + (r.powiazana ? ' · do umowy <a class="um-a" onclick="window.umOpen(' + r.powiazana.id + ')">' + esc(r.powiazana.numer) + '</a>' : '') + '</p></div>' +
    '<div class="row" style="gap:8px">' + umActions(u, r) + '</div></div>';
  if (u.status === 'szkic') h += umEditor(r);
  else h += umFrozen(r);
  $('main').innerHTML = h;
  if (u.status === 'szkic') umBindEditor();
}
// --- szkic: formularz + podgląd na żywo ---
function umEditor(r) {
  var u = r.u, k = r.klient || {}, f = '<div class="card pad"><h2 class="h2">Dane umowy</h2>';
  f += '<div class="g2f"><div><label class="f">Data zawarcia (w treści)</label><input type="date" id="ue-data" value="' + esc(u.data_umowy || '') + '"></div>' +
    '<div><label class="f">Numer</label><input value="' + esc(u.numer) + '" disabled></div></div>';
  for (var i = 0; i < r.fields.length; i++) {
    var x = r.fields[i], v = x.val || '', id = 'uf-' + i, long = (x.def || '').length > 46 || v.length > 46;
    f += '<label class="f" for="' + id + '">' + esc(x.label) + '</label>' +
      (long ? '<textarea id="' + id + '" data-k="' + esc(x.key) + '" class="uf" rows="2">' + esc(v) + '</textarea>' : '<input id="' + id + '" data-k="' + esc(x.key) + '" class="uf" value="' + esc(v) + '">') +
      (x.def && x.def !== v ? '<div class="hint um-def">domyślnie: ' + esc(x.def) + ' <a onclick="window.ufDef(\'' + id + '\',' + i + ')">przywróć</a></div>' : '');
  }
  if (!r.fields.length) f += '<div class="mut" style="margin-top:12px">Ten szablon nie ma pól do wpisania.</div>';
  f += '</div>';
  // pola wbudowane
  var miss = [];
  for (i = 0; i < r.builtin.length; i++) if (!String(r.builtin[i].val || '').trim()) miss.push(r.builtin[i].key);
  f += '<div class="card pad" style="margin-top:14px"><h2 class="h2">Uzupełnia się samo</h2><div class="mut" style="margin-bottom:8px">Dane stron biorą się z karty klienta i z Ustawień. Po wysłaniu do podpisu zostaną zamrożone.</div>' +
    '<div class="kv"><span>Klient</span><b><a class="um-a" onclick="window.klForm(' + u.klient_id + ',null,function(){window.closeModal();window.umOpen(' + u.id + ')})">' + esc(k.nazwa || '—') + ' ' + ico(I.edit, 13) + '</a></b></div>' +
    '<div class="kv"><span>E-mail do podpisu</span><b>' + esc(k.email || '— brak —') + '</b></div>' +
    (miss.length ? '<div class="um-miss">' + ico('<path d="M12 9v4M12 17h.01"/><circle cx="12" cy="12" r="10"/>', 15) + ' Puste: ' + esc(miss.join(', ')) + '</div>' : '<div class="um-okb">' + ico(I.check, 15) + ' Wszystkie dane stron są uzupełnione</div>') + '</div>';
  f += '<div class="card pad" style="margin-top:14px"><label class="row" style="gap:10px;cursor:pointer"><input type="checkbox" id="ue-own" onchange="window.ueOwn(this.checked)"> <b>Zmień treść tylko tej umowy</b></label><div class="hint">Szablon zostaje bez zmian. Używaj do wyjątków ustalonych z klientem.</div>' +
    '<textarea id="ue-tresc" class="um-code" rows="18" style="display:none;margin-top:10px">' + esc(r.tresc) + '</textarea></div>';
  return '<div class="um-ed"><div>' + f + '</div><div class="um-pv"><div class="um-pvbar"><span id="ue-st" class="mut">Zapisano</span><span class="mut">Podgląd A4 · pola wypełnione są podświetlone</span></div><div class="um-a4" id="ue-a4">' + r.html + '</div></div></div>';
}
function umBindEditor() {
  var els = document.querySelectorAll('.uf, #ue-data, #ue-tresc');
  for (var i = 0; i < els.length; i++) els[i].addEventListener('input', ueChanged);
  var a4 = $('ue-a4');
  a4.addEventListener('click', function (e) { var t = e.target.closest ? e.target.closest('.um-f') : null; if (!t) return; var inp = document.querySelector('.uf[data-k="' + t.getAttribute('data-k') + '"]'); if (inp) { inp.focus(); inp.scrollIntoView({ block: 'center', behavior: 'smooth' }); } });
}
window.ueOwn = function (on) { $('ue-tresc').style.display = on ? 'block' : 'none'; };
window.ufDef = function (id, i) { var e = $(id); e.value = UM.cur.fields[i].def; ueChanged(); };
function ueChanged() { $('ue-st').textContent = 'Zapisuję…'; clearTimeout(UM.timer); UM.timer = setTimeout(ueSave, 600); }
function ueSave(cb) {
  if (!UM.cur || UM.cur.u.status !== 'szkic') return;
  var pola = {}, els = document.querySelectorAll('.uf');
  for (var i = 0; i < els.length; i++) pola[els[i].getAttribute('data-k')] = els[i].value;
  var d = { id: UM.cur.u.id, pola: pola, data_umowy: val('ue-data') };
  if ($('ue-own') && $('ue-own').checked) d.tresc = val('ue-tresc');
  api('um_zapisz', d, function (r) {
    var a4 = $('ue-a4'); if (!a4) return;
    var y = a4.scrollTop; a4.innerHTML = r.html; a4.scrollTop = y;
    UM.cur.html = r.html;
    var structChanged = r.fields.length !== UM.cur.fields.length; UM.cur.fields = r.fields; UM.cur.builtin = r.builtin;
    if (d.tresc !== undefined) UM.cur.tresc = d.tresc;
    $('ue-st').textContent = 'Zapisano';
    if (structChanged) toast('Zmieniła się lista pól – odśwież kartę umowy, żeby je zobaczyć');
    if (cb) cb();
  }, function () { $('ue-st').textContent = 'Nie zapisano'; });
}
// --- po wysłaniu: zamrożona treść + historia ---
function umFrozen(r) {
  var u = r.u, k = r.klient || {}, s = '';
  if (u.status === 'wyslana') s += '<div class="card pad um-info"><b>Czeka na akceptację klienta</b><div class="mut">Link wysłany ' + fd(u.wyslana_at) + ' na ' + esc((r.akc.length && k.email) ? k.email : (k.email || '')) + '. ' + (r.link_aktywny ? 'Ważny do ' + fd(u.token_exp) + '.' : '<b style="color:var(--err)">Link wygasł – wyślij ponownie.</b>') + (u.otwarta_at ? ' Klient otworzył: ' + fd(u.otwarta_at) + '.' : ' Klient jeszcze nie otworzył.') + '</div></div>';
  if (u.status === 'zawarta') s += '<div class="card pad um-info ok"><b>Umowa zawarta ' + fd(u.zawarta_at) + '</b><div class="mut">' + (u.metoda === 'kwalifikowany' ? 'Wgrany PDF z podpisem kwalifikowanym (plik bez zmian).' : 'Akceptacja online obu stron, PDF z kartą akceptacji wysłany obu stronom.') + '</div>' +
    '<label class="row" style="gap:10px;margin-top:10px;cursor:pointer"><input type="checkbox" ' + (u.wydruk_at ? 'checked' : '') + ' onchange="window.umPrint(this.checked)"> Wydrukowana i w segregatorze' + (u.wydruk_at ? ' <span class="mut">(' + fd(u.wydruk_at) + ')</span>' : '') + '</label></div>';
  if (u.status === 'anulowana') s += '<div class="card pad um-info"><b>Anulowana ' + fd(u.anul_at) + '</b>' + (u.anul_powod ? '<div class="mut">' + esc(u.anul_powod) + '</div>' : '') + '</div>';
  if (u.status === 'rozwiazana') s += '<div class="card pad um-info"><b>Rozwiązana z dniem ' + fdd(u.rozw_data) + '</b><div class="mut">' + esc(u.rozw_powod) + '</div></div>';
  // akceptacje
  var ac = '';
  for (var i = 0; i < r.akc.length; i++) { var a = r.akc[i]; if (+a.wersja !== +u.wersja) continue;
    ac += '<div class="um-acc"><b>' + (a.strona === 'elok' ? 'ELOK' : 'Klient') + ': ' + esc(a.imie + ' ' + a.nazwisko) + '</b> <span class="mut">' + esc(a.funkcja) + '</span><div class="mut">' + fd(a.created_at) + ' · IP ' + esc(a.ip) + ' · ' + esc(a.email) + '</div></div>'; }
  if (u.sha) ac += '<div class="mut um-sha">Wersja ' + u.wersja + ' · SHA-256 ' + esc(u.sha) + '</div>';
  if (ac) s += '<div class="card pad" style="margin-top:14px"><h2 class="h2">Akceptacje</h2>' + ac + '</div>';
  // powiązane
  if (r.dzieci.length) { var d = ''; for (i = 0; i < r.dzieci.length; i++) d += '<div class="kv"><span><a class="um-a" onclick="window.umOpen(' + r.dzieci[i].id + ')">' + esc(r.dzieci[i].numer) + '</a> ' + esc(r.dzieci[i].szablon_nazwa) + '</span><b>' + pillU(r.dzieci[i].status) + '</b></div>'; s += '<div class="card pad" style="margin-top:14px"><h2 class="h2">Powiązane umowy</h2>' + d + '</div>'; }
  // historia
  var lg = '';
  for (i = 0; i < r.log.length; i++) lg += '<div><span class="t">' + fd(r.log[i].created_at) + '</span><span><b>' + esc(LOGT[r.log[i].typ] || r.log[i].typ) + '</b> ' + esc(r.log[i].info) + (r.log[i].ip ? ' <span class="mut">· IP ' + esc(r.log[i].ip) + '</span>' : '') + '</span></div>';
  s += '<div class="card pad" style="margin-top:14px"><h2 class="h2">Historia zdarzeń</h2><div class="feed um-feed">' + (lg || '<div class="mut">Brak zdarzeń.</div>') + '</div><div class="hint">Historii nie da się edytować ani usuwać.</div></div>';
  var right = u.metoda === 'kwalifikowany' && (u.status === 'zawarta' || u.status === 'rozwiazana') ? '<div class="um-a4 um-empty-a4"><p>Treścią tej umowy jest wgrany plik PDF z podpisem kwalifikowanym.</p><a class="btn dark" href="umowy_plik.php?id=' + u.id + '&typ=final" target="_blank" rel="noopener">' + ico(I.pdf) + ' Otwórz PDF</a></div>'
    : '<div class="um-a4 um-frozen">' + (r.html || '<p class="mut">Brak zamrożonej treści.</p>') + '</div>';
  return '<div class="um-ed"><div>' + s + '</div><div class="um-pv"><div class="um-pvbar"><span class="mut">' + (u.status === 'anulowana' ? 'Treść anulowanej umowy' : 'Treść zamrożona – nie da się jej edytować') + '</span></div>' + right + '</div></div>';
}

function kjEmail(u, k) { try { var j = JSON.parse(u.klient_json || '{}'); if (j.email) return j.email; } catch (e) {} return k.email || ''; }
// --- akcje ---
window.umSend = function () {
  clearTimeout(UM.timer);
  ueSave(function () {
    api('um_ust', {}, function (s) {
      var u = UM.cur.u, k = UM.cur.klient || {};
      var b = '<p style="margin:12px 0;color:var(--ink2);line-height:1.6">Treść zostanie zamrożona (skrót SHA-256), a klient dostanie e-mail z linkiem na <b>' + esc(k.email || '— brak adresu —') + '</b>. Najpierw akceptujesz umowę w imieniu ELOK.</p>' +
        '<div class="g2f"><div><label class="f">Imię</label><input id="se-im" value="' + esc(s.um_f_r_imie) + '"></div><div><label class="f">Nazwisko</label><input id="se-na" value="' + esc(s.um_f_r_nazwisko) + '"></div></div>' +
        '<label class="f">Funkcja</label><input id="se-fu" value="' + esc(s.um_f_r_funkcja) + '">' +
        '<label class="row" style="gap:10px;margin-top:14px;cursor:pointer;align-items:flex-start"><input type="checkbox" id="se-ak" style="margin-top:3px"> <span>Akceptuję treść umowy nr <b>' + esc(u.numer) + '</b> w imieniu ' + esc(s.um_f_nazwa) + ' (ELOK) i jestem uprawniony do reprezentacji.</span></label>';
      window.openModal('Wyślij do podpisu', b, '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn gold" onclick="window.umSendGo(this)">' + ico(I.send) + ' Akceptuję i wysyłam</a>');
    });
  });
};
window.umSendGo = function (btn) {
  if (!$('se-ak').checked) { toast('Zaznacz akceptację', 'err'); return; }
  busy(btn, true);
  api('um_wyslij', { id: UM.cur.u.id, imie: val('se-im'), nazwisko: val('se-na'), funkcja: val('se-fu'), akcept: 1 }, function (r) { window.closeModal(); UM.cur = r; umRender(); toast('Wysłano do podpisu'); }, function () { busy(btn, false); });
};
window.umResend = function (btn) { busy(btn, true); api('um_link_ponow', { id: UM.cur.u.id }, function (r) { UM.cur = r; umRender(); toast('Wysłano nowy link – poprzedni już nie działa'); }, function () { busy(btn, false); }); };
window.umCopyLink = function () {
  window.ask('Skopiować nowy link?', 'Wygeneruję nowy link do podpisu (np. do wysłania przez WhatsApp). Poprzedni link z maila przestanie działać.', 'Generuj link', function () {
    api('um_link_kopiuj', { id: UM.cur.u.id }, function (r) {
      window.openModal('Link do podpisu', '<p class="mut" style="margin:12px 0 8px">Ważny tak samo jak link z maila. Kod potwierdzający i tak przyjdzie na e-mail klienta z umowy.</p><input id="lk" value="' + esc(r.link) + '" readonly onclick="this.select()">', '<a class="btn" onclick="window.closeModal()">Zamknij</a><a class="btn dark" onclick="window.umCopy()">' + ico(I.copy) + ' Kopiuj</a>');
      window.umOpenSilent();
    });
  });
};
window.umCopy = function () { var e = $('lk'); e.select(); try { navigator.clipboard.writeText(e.value).then(function () { toast('Skopiowano'); }, function () { document.execCommand('copy'); toast('Skopiowano'); }); } catch (x) { document.execCommand('copy'); toast('Skopiowano'); } };
window.umOpenSilent = function () { var id = UM.cur.u.id; api('um_get', { id: id }, function (r) { UM.cur = r; }); };
window.umBack = function () {
  window.ask('Cofnąć do szkicu?', 'Link u klienta przestanie działać, a treść będzie znowu edytowalna. Po zmianach wyślesz umowę ponownie – z nowym skrótem SHA-256. Historia zostaje.', 'Cofnij do szkicu', function () {
    api('um_cofnij', { id: UM.cur.u.id }, function (r) { UM.cur = r; umRender(); toast('Cofnięto do szkicu'); });
  });
};
window.umCancel = function () {
  window.ask('Anulować umowę?', 'Umowa zostanie oznaczona jako anulowana, a link u klienta przestanie działać. Możesz podać powód.', 'Anuluj umowę', function (v) {
    api('um_anuluj', { id: UM.cur.u.id, powod: v || '' }, function (r) { UM.cur = r; umRender(); toast('Anulowano'); });
  }, { danger: true, input: '', ph: 'Powód (opcjonalnie)', cancel: 'Wróć' });
};
window.umDel = function () {
  window.ask('Usunąć szkic?', 'Szkic nigdy nie był wysłany, więc zniknie całkowicie. Numer nie zostanie użyty ponownie.', 'Usuń', function () {
    api('um_usun', { id: UM.cur.u.id }, function () { toast('Usunięto'); window.umTab('umowy'); });
  }, { danger: true });
};
window.umDup = function () { api('um_duplikuj', { id: UM.cur.u.id }, function (r) { toast('Utworzono kopię jako nowy szkic'); window.umOpen(r.id); }); };
window.umPdfAgain = function (btn) { busy(btn, true); api('um_pdf_ponow', { id: UM.cur.u.id }, function (r) { UM.cur = r; umRender(); toast('PDF wysłany obu stronom'); }, function () { busy(btn, false); }); };
window.umPrint = function (on) { api('um_wydruk', { id: UM.cur.u.id, on: on ? 1 : 0 }, function () { toast(on ? 'Oznaczono jako wydrukowaną' : 'Cofnięto oznaczenie'); window.umOpen(UM.cur.u.id); }); };
window.umTerminate = function () {
  var t = new Date(), d = t.getFullYear() + '-' + ('0' + (t.getMonth() + 1)).slice(-2) + '-' + ('0' + t.getDate()).slice(-2);
  window.openModal('Rozwiąż umowę', '<label class="f">Data rozwiązania</label><input type="date" id="rz-d" value="' + d + '"><label class="f">Powód</label><input id="rz-p" placeholder="np. wypowiedzenie przez klienta z dnia …">',
    '<a class="btn" onclick="window.closeModal()">Wróć</a><a class="btn redfill" onclick="window.umTermGo(this)">Rozwiąż</a>');
};
window.umTermGo = function (btn) { busy(btn, true); api('um_rozwiaz', { id: UM.cur.u.id, data: val('rz-d'), powod: val('rz-p') }, function (r) { window.closeModal(); UM.cur = r; umRender(); toast('Umowa rozwiązana'); }, function () { busy(btn, false); }); };
window.umUpload = function () {
  window.openModal('PDF z podpisem kwalifikowanym', '<p class="mut" style="margin:12px 0">Jeśli obie strony podpisały umowę podpisem kwalifikowanym (np. mSzafir, Autenti z QES), wgraj gotowy PDF. Plik zostanie zapisany bez zmian, a umowa dostanie status „Zawarta”.</p><input type="file" id="up-f" accept="application/pdf,.pdf" style="height:auto;padding:10px">',
    '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn dark" onclick="window.umUpGo(this)">' + ico(I.upl) + ' Wgraj</a>');
};
window.umUpGo = function (btn) {
  var f = $('up-f').files[0]; if (!f) { toast('Wybierz plik PDF', 'err'); return; }
  var fdt = new FormData(); fdt.append('plik', f);
  busy(btn, true);
  var x = new XMLHttpRequest(); x.open('POST', 'umowy_plik.php?id=' + UM.cur.u.id); x.setRequestHeader('X-CSRF', window.CSRF);
  x.onload = function () { busy(btn, false); var r = {}; try { r = JSON.parse(x.responseText); } catch (e) { toast('Błąd serwera', 'err'); return; } if (!r.ok) { toast(r.error || 'Błąd', 'err'); return; } window.closeModal(); toast('Zapisano – umowa zawarta'); window.umOpen(UM.cur.u.id); };
  x.onerror = function () { busy(btn, false); toast('Brak połączenia', 'err'); };
  x.send(fdt);
};

// ================= KLIENCI =================
function klList() {
  api('um_klienci', { q: UM.kq || '' }, function (r) {
    var h = '<div class="head"><div><h1>Umowy</h1><p>Klienci, z którymi zawierasz umowy.</p></div><div class="row" style="gap:8px"><a class="btn dark" onclick="window.klForm(0,null,function(id){window.closeModal();window.klOpen(id)})">' + ico(I.plus) + ' Nowy klient</a></div></div>' + tabs();
    h += '<div class="row" style="margin-bottom:12px"><div class="grow" style="max-width:360px"><input id="kl-q" placeholder="Szukaj: nazwa, NIP, e-mail, miasto" value="' + esc(UM.kq || '') + '" onkeydown="if(event.key===\'Enter\'){window.UM.kq=this.value;window.umTab(\'klienci\')}"></div></div>';
    if (!r.rows.length) h += '<div class="card empty">' + (UM.kq ? 'Brak klientów dla tego wyszukiwania.' : 'Nie ma jeszcze klientów. Dodaj klienta ręcznie (z pobraniem danych po NIP) albo kliknij „Zrób klienta” przy zgłoszeniu.') + '</div>';
    else {
      h += '<div class="card"><table class="tbl resp"><thead><tr><th>Firma</th><th>NIP</th><th class="hm">Miasto</th><th class="hm">E-mail</th><th>Umowy</th></tr></thead><tbody>';
      for (var i = 0; i < r.rows.length; i++) { var k = r.rows[i];
        h += '<tr class="click" onclick="window.klOpen(' + k.id + ')"><td class="b1">' + esc(k.nazwa) + '</td><td class="mut">' + esc(k.nip || '—') + '</td><td class="hm">' + esc(k.siedziba || '—') + '</td><td class="hm mut">' + esc(k.email || '—') + '</td><td>' + (+k.umowy ? k.umowy + (+k.zawarte ? ' <span class="mut">(' + k.zawarte + ' zawarte)</span>' : '') : '<span class="mut">0</span>') + '</td></tr>'; }
      h += '</tbody></table></div>';
    }
    $('main').innerHTML = h;
  });
}
window.klOpen = function (id) {
  UM.tab = 'klienci';
  api('um_klient', { id: id }, function (r) {
    var k = r.k, row = function (l, v) { return '<div class="kv"><span>' + l + '</span><b>' + (v ? esc(v) : '<span class="mut">—</span>') + '</b></div>'; };
    var h = '<div class="head"><div><a class="btn sm" onclick="window.umTab(\'klienci\')">' + ico(I.back, 14) + ' Wszyscy klienci</a><h1 style="margin-top:14px">' + esc(k.nazwa) + '</h1><p>' + esc([k.siedziba, k.nip ? 'NIP ' + k.nip : ''].filter(Boolean).join(' · ')) + '</p></div>' +
      '<div class="row" style="gap:8px"><a class="btn" onclick="window.klForm(' + k.id + ',null,function(){window.closeModal();window.klOpen(' + k.id + ')})">' + ico(I.edit) + ' Edytuj</a><a class="btn dark" onclick="window.umNowa(' + k.id + ')">' + ico(I.plus) + ' Nowa umowa dla klienta</a></div></div>';
    h += '<div class="um-ed um-ed2"><div class="card pad"><h2 class="h2">Dane do umów</h2>' + row('Nazwa', k.nazwa) + row('Siedziba (miasto)', k.siedziba) + row('Adres', k.adres) + row('NIP', k.nip) + row('REGON', k.regon) + row('Rejestr', k.rejestr) +
      row('Reprezentant', [k.r_imie, k.r_nazwisko].join(' ').trim() + (k.r_funkcja ? ' – ' + k.r_funkcja : '')) + row('E-mail (umowy, kody)', k.email) + row('Telefon', k.telefon) + row('Osoba kontaktowa', k.kontakt) +
      (k.notatki ? '<div class="note" style="white-space:pre-wrap">' + esc(k.notatki) + '</div>' : '') + '</div>';
    var u = '';
    for (var i = 0; i < r.umowy.length; i++) { var x = r.umowy[i]; u += '<tr class="click" onclick="window.umOpen(' + x.id + ')"><td class="b1">' + esc(x.numer) + '</td><td class="mut">' + esc(x.szablon_nazwa) + '</td><td>' + pillU(x.status) + '</td></tr>'; }
    h += '<div class="card"><div class="pad" style="padding-bottom:0"><h2 class="h2">Umowy klienta</h2></div>' + (u ? '<table class="tbl"><tbody>' + u + '</tbody></table>' : '<div class="empty">Brak umów. Kliknij „Nowa umowa dla klienta”.</div>') + '</div></div>';
    $('main').innerHTML = h;
  });
};
var KF = {};
window.klForm = function (id, pre, done) {
  KF.done = done; KF.id = id; KF.pre = pre || {};
  var go = function (k) {
    k = k || {}; var p = KF.pre; for (var x in p) if (p.hasOwnProperty(x) && !k[x]) k[x] = p[x];
    var inp = function (key, label, ph, type) { return '<div><label class="f">' + label + '</label><input id="kf-' + key + '" value="' + esc(k[key] || '') + '" placeholder="' + esc(ph || '') + '"' + (type ? ' type="' + type + '"' : '') + '></div>'; };
    var b = '<label class="f">NIP</label><div class="row" style="gap:8px;flex-wrap:nowrap"><input id="kf-nip" value="' + esc(k.nip || '') + '" placeholder="10 cyfr" onkeydown="if(event.key===\'Enter\')window.kfNip()"><a class="btn" onclick="window.kfNip(this)">' + ico(I.search) + ' Pobierz dane</a></div>' +
      '<div class="hint">Dane z Białej listy VAT (Ministerstwo Finansów). Sprawdź je przed zapisem.</div><div id="kf-rep"></div>' +
      inp('nazwa', 'Pełna nazwa firmy', 'np. Auto-Serwis Kowalski sp. z o.o.') +
      '<div class="g2f">' + inp('siedziba', 'Siedziba (miasto)', 'np. Warszawa') + inp('regon', 'REGON', '') + '</div>' +
      inp('adres', 'Adres', 'ul. Przykładowa 1, 00-001 Warszawa') +
      inp('rejestr', 'Rejestr (tak jak ma być w umowie)', 'np. wpisaną do rejestru przedsiębiorców KRS pod nr 0000123456 albo wpisaną do CEIDG') +
      '<div class="g2f">' + inp('r_imie', 'Reprezentant – imię', '') + inp('r_nazwisko', 'Reprezentant – nazwisko', '') + '</div>' +
      inp('r_funkcja', 'Funkcja reprezentanta', 'np. Właściciel, Prezes Zarządu') +
      '<div class="g2f">' + inp('email', 'E-mail (link do podpisu i kod)', 'biuro@firma.pl', 'email') + inp('telefon', 'Telefon', '') + '</div>' +
      inp('kontakt', 'Osoba kontaktowa', 'imię i nazwisko (jeśli inna niż reprezentant)') +
      '<label class="f">Notatki</label><textarea id="kf-notatki" rows="3">' + esc(k.notatki || '') + '</textarea>';
    window.openModal(id ? 'Edytuj klienta' : 'Nowy klient', b, (id ? '<a class="btn red" style="margin-right:auto" onclick="window.kfDel()">Usuń</a>' : '') + '<a class="btn" onclick="window.closeModal()">Anuluj</a><a class="btn dark" onclick="window.kfSave(this)">Zapisz</a>', true);
  };
  if (id) api('um_klient', { id: id }, function (r) { go(r.k); }); else go({});
};
window.kfNip = function (btn) {
  busy(btn, true);
  api('um_nip', { nip: val('kf-nip') }, function (r) {
    busy(btn, false); var d = r.d;
    var set = function (k, v) { if (v && $('kf-' + k)) $('kf-' + k).value = v; };
    set('nip', d.nip); set('nazwa', d.nazwa); set('siedziba', d.siedziba); set('adres', d.adres); set('regon', d.regon); set('rejestr', d.rejestr);
    var rep = '';
    if (d.reprezentanci && d.reprezentanci.length) { rep = '<div class="um-rep">Reprezentanci w rejestrze: '; for (var i = 0; i < d.reprezentanci.length; i++) rep += '<a class="chip" onclick="window.kfRep(\'' + esc(d.reprezentanci[i]).replace(/'/g, '') + '\')">' + esc(d.reprezentanci[i]) + '</a> '; rep += '</div>'; }
    $('kf-rep').innerHTML = rep + (d.status_vat ? '<div class="hint">Status VAT: ' + esc(d.status_vat) + '</div>' : '');
    toast('Pobrano dane z Białej listy');
  }, function () { busy(btn, false); });
};
window.kfRep = function (n) { var p = n.split(' '); $('kf-r_nazwisko').value = p.pop(); $('kf-r_imie').value = p.join(' '); };
window.kfSave = function (btn, force) {
  var d = { id: KF.id, zgloszenie_id: KF.pre.zgloszenie_id || 0, firma_id: KF.pre.firma_id || 0 };
  var f = ['nip', 'nazwa', 'siedziba', 'regon', 'adres', 'rejestr', 'r_imie', 'r_nazwisko', 'r_funkcja', 'email', 'telefon', 'kontakt', 'notatki'];
  for (var i = 0; i < f.length; i++) d[f[i]] = val('kf-' + f[i]);
  if (force) d.wymus = 1;
  KF.last = d;
  busy(btn, true);
  api('um_klient_zapisz', d, function (r) { busy(btn, false); toast('Zapisano klienta'); if (KF.done) KF.done(r.id); else window.closeModal(); }, function (r) {
    busy(btn, false);
    if (r && r.dup) window.ask('Taki NIP już jest', esc(r.error) + '. Otworzyć istniejącego klienta czy zapisać nowego?', 'Otwórz istniejącego', function () { window.klOpen(r.dup); }, { alt: 'Zapisz mimo to', onAlt: function () { window.kfSaveForce(); } });
  });
};
window.kfSaveForce = function () {
  var d = KF.last; if (!d) return; d.wymus = 1;
  api('um_klient_zapisz', d, function (r) { toast('Zapisano klienta'); if (KF.done) KF.done(r.id); else window.closeModal(); });
};
window.kfDel = function () {
  window.ask('Usunąć klienta?', 'Klienta z umowami nie da się usunąć.', 'Usuń', function () { api('um_klient_usun', { id: KF.id }, function () { toast('Usunięto'); window.umTab('klienci'); }); }, { danger: true });
};
// z leada (zgłoszenie / firma z mailingu)
window.umZLeada = function (zgl, firma) {
  api('um_z_leada', { zgloszenie: zgl || 0, firma: firma || 0 }, function (r) {
    var go = function () { window.klForm(0, r.d, function (id) { window.closeModal(); window.go('umowy'); setTimeout(function () { window.klOpen(id); }, 50); }); };
    if (r.istnieje) window.ask('Ten klient już jest', 'Klient z tym e-mailem jest już w Umowach: <b>' + esc(r.istnieje.nazwa) + '</b>.', 'Otwórz klienta', function () { window.go('umowy'); setTimeout(function () { window.klOpen(r.istnieje.id); }, 50); }, { alt: 'Dodaj nowego', onAlt: go });
    else go();
  });
};

// ================= SZABLONY =================
function szList() {
  api('um_szablony', {}, function (r) {
    var h = '<div class="head"><div><h1>Umowy</h1><p>Szablony umów. Zmiana szablonu nie zmienia umów, które już utworzyłeś.</p></div><div class="row" style="gap:8px"><a class="btn dark" onclick="window.szOpen(0)">' + ico(I.plus) + ' Nowy szablon</a></div></div>' + tabs();
    h += '<div class="card"><table class="tbl resp"><thead><tr><th>Szablon</th><th>Rodzaj</th><th>Numeracja</th><th class="hm">Umowy</th><th class="hm">Zmieniony</th></tr></thead><tbody>';
    for (var i = 0; i < r.rows.length; i++) { var s = r.rows[i];
      h += '<tr class="click" onclick="window.szOpen(' + s.id + ')"><td class="b1">' + esc(s.nazwa) + '</td><td class="mut">' + esc(TYPY[s.typ] || s.typ) + '</td><td>' + esc(s.prefiks) + '/001/' + new Date().getFullYear() + '</td><td class="hm">' + s.umowy + '</td><td class="hm mut">' + fd(s.updated_at) + '</td></tr>'; }
    h += '</tbody></table>' + (r.rows.length ? '' : '<div class="empty">Brak szablonów.</div>') + '</div>';
    h += '<div class="card pad" style="margin-top:14px"><h2 class="h2">Jak pisać szablon</h2><div class="mut" style="line-height:1.7">Zwykły tekst w formacie markdown: <code>##</code> tytuł, <code>###</code> paragraf, <code>**pogrubienie**</code>, listy <code>1.</code> (podpunkty wcięte 4 spacjami), tabele <code>| a | b |</code>, <code>---</code> oddziela załączniki.<br>Pole do wypełnienia: <code>{{klucz|Etykieta|wartość domyślna}}</code>, np. <code>{{kwota|Wynagrodzenie (zł netto)|2000,00}}</code>. Pole <code>abonament</code> liczy się do sumy abonamentów, <code>kwota</code> pokazuje się na liście.<br>Wstawiają się same: <code>{{firma.nazwa}}</code>, <code>{{firma.siedziba}}</code>, <code>{{firma.adres}}</code>, <code>{{firma.krs}}</code>, <code>{{firma.nip}}</code>, <code>{{firma.regon}}</code>, <code>{{firma.reprezentant}}</code>, <code>{{firma.email}}</code>, <code>{{klient.nazwa}}</code>, <code>{{klient.siedziba}}</code>, <code>{{klient.adres}}</code>, <code>{{klient.rejestr}}</code>, <code>{{klient.nip}}</code>, <code>{{klient.reprezentant}}</code>, <code>{{klient.email}}</code>, <code>{{klient.kontakt}}</code>, <code>{{umowa.numer}}</code>, <code>{{umowa.data}}</code>. Numer powiązanej umowy: <code>{{umowa.powiazana|Numer umowy wdrożeniowej|}}</code>.</div></div>';
    $('main').innerHTML = h;
  });
}
window.szOpen = function (id) {
  var go = function (s, fields) {
    UM.sz = s;
    var o = ''; for (var k in TYPY) if (TYPY.hasOwnProperty(k)) o += '<option value="' + k + '"' + (s.typ === k ? ' selected' : '') + '>' + TYPY[k] + '</option>';
    var h = '<div class="head"><div><a class="btn sm" onclick="window.umTab(\'szablony\')">' + ico(I.back, 14) + ' Wszystkie szablony</a><h1 style="margin-top:14px">' + esc(s.id ? s.nazwa : 'Nowy szablon') + '</h1><p>Zapisanie szablonu nie zmienia istniejących umów.</p></div>' +
      '<div class="row" style="gap:8px">' + (s.id ? '<a class="btn" onclick="window.szDup()">' + ico(I.copy) + ' Duplikuj</a><a class="btn red" onclick="window.szDel()">' + ico(I.trash) + ' Usuń</a>' : '') + '<a class="btn dark" onclick="window.szSave(this)">Zapisz szablon</a></div></div>';
    h += '<div class="card pad"><div class="um-g3"><div><label class="f">Nazwa</label><input id="sz-n" value="' + esc(s.nazwa || '') + '"></div><div><label class="f">Rodzaj</label><select id="sz-t">' + o + '</select></div><div><label class="f">Prefiks numeracji</label><input id="sz-p" value="' + esc(s.prefiks || 'ELOK/U') + '" oninput="window.szPv()"><div class="hint" id="sz-ex"></div></div></div></div>';
    h += '<div class="um-ed um-szed" style="margin-top:14px"><div><textarea id="sz-tr" class="um-code" rows="30" oninput="window.szPv()">' + esc(s.tresc || '') + '</textarea><div class="card pad" style="margin-top:14px"><h2 class="h2">Wykryte pola</h2><div id="sz-f"></div></div></div>' +
      '<div class="um-pv"><div class="um-pvbar"><span class="mut">Podgląd na żywo (dane ELOK z Ustawień, dane klienta puste)</span></div><div class="um-a4" id="sz-a4"></div></div></div>';
    $('main').innerHTML = h;
    window.szPv(true);
  };
  if (id) api('um_szablon', { id: id }, function (r) { go(r.s, r.fields); });
  else go({ id: 0, nazwa: '', typ: 'inne', prefiks: 'ELOK/U', tresc: '## UMOWA NR {{umowa.numer}}\n\nzawarta w dniu {{umowa.data}} pomiędzy:\n\n**{{firma.nazwa}}** … a **{{klient.nazwa}}** …\n\n### § 1. Przedmiot umowy\n\n1. …\n' });
};
var SZT = null;
window.szPv = function (now) {
  var p = val('sz-p') || 'ELOK/U'; if ($('sz-ex')) $('sz-ex').textContent = 'np. ' + p + '/001/' + new Date().getFullYear();
  clearTimeout(SZT);
  SZT = setTimeout(function () {
    api('um_szablon_podglad', { tresc: val('sz-tr'), prefiks: p }, function (r) {
      if (!$('sz-a4')) return; $('sz-a4').innerHTML = r.html;
      var f = ''; for (var i = 0; i < r.fields.length; i++) f += '<div class="kv"><span><code>' + esc(r.fields[i].key) + '</code> ' + esc(r.fields[i].label) + '</span><b class="mut">' + esc(r.fields[i].def || '—') + '</b></div>';
      f += '<div class="hint" style="margin-top:8px">Wbudowane: ' + esc(r.builtin.join(', ') || '—') + '</div>';
      $('sz-f').innerHTML = r.fields.length ? f : '<div class="mut">Brak pól do wpisania.</div>' + f;
    });
  }, now === true ? 0 : 450);
};
window.szSave = function (btn) {
  busy(btn, true);
  api('um_szablon_zapisz', { id: UM.sz.id, nazwa: val('sz-n'), typ: val('sz-t'), prefiks: val('sz-p'), tresc: val('sz-tr') }, function (r) { busy(btn, false); toast('Zapisano szablon'); UM.sz.id = r.id; window.szOpen(r.id); }, function () { busy(btn, false); });
};
window.szDup = function () { api('um_szablon_duplikuj', { id: UM.sz.id }, function (r) { toast('Utworzono kopię'); window.szOpen(r.id); }); };
window.szDel = function () { window.ask('Usunąć szablon?', 'Umowy utworzone z tego szablonu zostają – mają własną kopię treści.', 'Usuń', function () { api('um_szablon_usun', { id: UM.sz.id }, function () { toast('Usunięto'); window.umTab('szablony'); }); }, { danger: true }); };

// ================= USTAWIENIA =================
function ustView() {
  api('um_ust', {}, function (s) {
    var inp = function (k, l, ph, t) { return '<div><label class="f">' + l + '</label><input id="us-' + k + '" value="' + esc(s[k] || '') + '" placeholder="' + esc(ph || '') + '"' + (t ? ' type="' + t + '"' : '') + '></div>'; };
    var h = '<div class="head"><div><h1>Umowy</h1><p>Dane ELOK do umów, maile i kopie zapasowe.</p></div><div class="row" style="gap:8px"><a class="btn dark" onclick="window.usSave(this)">Zapisz ustawienia</a></div></div>' + tabs();
    h += '<div class="grid g2" style="align-items:start"><div class="card pad"><h2 class="h2">Dane firmy w umowach (Wykonawca)</h2>' +
      inp('um_f_nazwa', 'Nazwa', 'Carbonmiledesign sp. z o.o.') + '<div class="g2f">' + inp('um_f_siedziba', 'Siedziba (miasto)', 'np. Warszawa') + inp('um_f_krs', 'KRS', '0000……') + '</div>' +
      inp('um_f_adres', 'Adres', 'ul. …, 00-000 Miasto') + '<div class="g2f">' + inp('um_f_nip', 'NIP', '') + inp('um_f_regon', 'REGON', '') + '</div>' +
      '<div class="g2f">' + inp('um_f_r_imie', 'Reprezentant – imię', '') + inp('um_f_r_nazwisko', 'Reprezentant – nazwisko', '') + '</div>' + inp('um_f_r_funkcja', 'Funkcja', 'Prezes Zarządu') +
      inp('um_f_email', 'E-mail do umów (zgłoszenia, PDF do ELOK)', 'umowy@elok-agency.pl', 'email') + '</div>';
    h += '<div><div class="card pad"><h2 class="h2">Maile i link do podpisu</h2>' + inp('um_nadawca_email', 'Adres nadawcy', s.nadawca_domyslny || 'umowy@elok-agency.pl', 'email') +
      '<div class="hint">Musi istnieć jako skrzynka na elok-agency.pl w LH.pl. Puste = ' + esc(s.nadawca_domyslny || 'adres z mailingu') + '.</div>' +
      inp('um_nadawca_nazwa', 'Nazwa nadawcy', 'ELOK – umowy') + inp('um_link_dni', 'Ważność linku do podpisu (dni)', '7', 'number') +
      '<div class="row" style="gap:8px;margin-top:14px;flex-wrap:nowrap"><input id="us-test" placeholder="adres do testu" value="' + esc(s.um_f_email || '') + '"><a class="btn" onclick="window.usTest(this)">' + ico(I.mail) + ' Wyślij testowy e-mail</a></div><div class="hint">Test sprawdza też PDF z polskimi znakami (w załączniku).</div></div>';
    h += '<div class="card pad" style="margin-top:14px"><h2 class="h2">Kopie zapasowe (min. 6 lat)</h2>' +
      '<div class="um-kop"><b>1. Skrzynka archiwum</b><div class="mut">Każda zawarta umowa (PDF z kartą) i co tydzień pełna kopia (ZIP) idą na ten adres. Najlepiej osobny Gmail tylko na archiwum.</div>' + inp('um_archiwum_email', 'E-mail archiwum', 'np. elok.archiwum.umow@gmail.com', 'email') + '</div>' +
      '<div class="um-kop"><b>2. Google Drive</b> ' + (s.gd_ok ? '<span class="pill um-st-zawarta">połączony' + (s.um_gd_konto ? ': ' + esc(s.um_gd_konto) : '') + '</span>' : '<span class="pill um-st-szkic">niepołączony</span>') +
        '<div class="mut">Pliki trafiają do folderu „ELOK Umowy”. Panel ma dostęp tylko do plików, które sam tam wgrał.</div>' +
        '<div class="g2f">' + inp('um_gd_client_id', 'Client ID (Google Cloud)', '…apps.googleusercontent.com') + '<div><label class="f">Client secret</label><input id="us-um_gd_secret" type="password" placeholder="' + (s.um_gd_secret_set ? 'zapisany – wpisz, żeby zmienić' : '') + '"></div></div>' +
        '<div class="hint">Adres przekierowania do wpisania w Google Cloud: <code id="gd-r">' + esc(s.gd_redirect) + '</code></div>' +
        '<div class="row" style="gap:8px;margin-top:10px">' + (s.gd_ok ? '<a class="btn" onclick="window.usGdOff()">Rozłącz</a>' : '') + '<a class="btn dark" onclick="window.usGd()">' + ico(I.drive) + (s.gd_ok ? ' Połącz ponownie' : ' Połącz z Google Drive') + '</a></div></div>' +
      '<div class="um-kop"><b>3. Wydruk</b><div class="mut">Każdą zawartą umowę oznacz w panelu jako „Wydrukowana i w segregatorze”. Filtr „Do wydruku” na liście pokazuje, czego jeszcze brakuje w segregatorze.</div></div>' +
      '<div class="row" style="gap:8px;margin-top:12px"><a class="btn" onclick="window.usKopia(this)">Zrób pełną kopię teraz</a><span class="mut">Ostatnia kopia tygodniowa: ' + esc(s.ostatnia_kopia || '—') + '</span></div>';
    if (s.kopie && s.kopie.length) { h += '<div class="feed" style="margin-top:12px">'; for (var i = 0; i < s.kopie.length; i++) { var k = s.kopie[i]; h += '<div><span class="t">' + fd(k.created_at) + '</span><span>' + esc(k.numer || k.nazwa) + ' · mail: ' + (k.mail_at ? 'tak' : 'nie') + ' · Drive: ' + (k.drive_id ? 'tak' : (k.drive_err ? '<span style="color:var(--err)">' + esc(k.drive_err) + '</span>' : 'nie')) + '</span></div>'; } h += '</div>'; }
    h += '<div class="hint">Pliki na serwerze: ' + esc(s.katalog || '') + '</div></div></div></div>';
    $('main').innerHTML = h;
  });
}
var US_KEYS = ['um_f_nazwa', 'um_f_siedziba', 'um_f_krs', 'um_f_adres', 'um_f_nip', 'um_f_regon', 'um_f_r_imie', 'um_f_r_nazwisko', 'um_f_r_funkcja', 'um_f_email', 'um_nadawca_email', 'um_nadawca_nazwa', 'um_link_dni', 'um_archiwum_email', 'um_gd_client_id', 'um_gd_secret'];
function usData() { var d = {}; for (var i = 0; i < US_KEYS.length; i++) d[US_KEYS[i]] = val('us-' + US_KEYS[i]); return d; }
window.usSave = function (btn, cb) { busy(btn, true); api('um_ust_zapisz', usData(), function () { busy(btn, false); toast('Zapisano ustawienia'); if (cb) cb(); }, function () { busy(btn, false); }); };
window.usTest = function (btn) { window.usSave(null, function () { busy(btn, true); api('um_test_mail', { to: val('us-test') }, function (r) { busy(btn, false); toast('Wysłano test z ' + r.from + ' na ' + r.to); }, function () { busy(btn, false); }); }); };
window.usGd = function () { window.usSave(null, function () { location.href = 'umowy_drive.php?start=1'; }); };
window.usGdOff = function () { window.ask('Rozłączyć Google Drive?', 'Nowe kopie nie będą trafiać na Dysk. Pliki, które już tam są, zostają.', 'Rozłącz', function () { api('um_gd_rozlacz', {}, function () { toast('Rozłączono'); ustView(); }); }); };
window.usKopia = function (btn) { busy(btn, true); api('um_kopia_teraz', {}, function (r) { busy(btn, false); toast('Zrobiono kopię: ' + r.nazwa); ustView(); }, function () { busy(btn, false); }); };
})();
