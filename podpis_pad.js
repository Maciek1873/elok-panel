/* ELOK – pole do podpisu palcem/myszką. Zapisuje tylko obraz (PNG), bez danych o nacisku i szybkości. */
(function () {
  window.SigPad = function (canvas) {
    var ctx = canvas.getContext('2d'), strokes = 0, len = 0, last = null, drawing = false, box = null;
    function fit() {
      var r = canvas.getBoundingClientRect(), d = window.devicePixelRatio || 1;
      var old = strokes ? canvas.toDataURL() : null;
      canvas.width = Math.max(1, Math.round(r.width * d)); canvas.height = Math.max(1, Math.round(r.height * d));
      ctx.setTransform(d, 0, 0, d, 0, 0); ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#13213f'; ctx.lineWidth = 2.6;
      if (old) { var im = new Image(); im.onload = function () { ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.drawImage(im, 0, 0, canvas.width, canvas.height); ctx.setTransform(d, 0, 0, d, 0, 0); }; im.src = old; }
    }
    function pos(e) { var r = canvas.getBoundingClientRect(); return { x: e.clientX - r.left, y: e.clientY - r.top }; }
    function grow(p) { if (!box) box = { x0: p.x, y0: p.y, x1: p.x, y1: p.y }; box.x0 = Math.min(box.x0, p.x); box.y0 = Math.min(box.y0, p.y); box.x1 = Math.max(box.x1, p.x); box.y1 = Math.max(box.y1, p.y); }
    canvas.style.touchAction = 'none';
    canvas.addEventListener('pointerdown', function (e) { e.preventDefault(); drawing = true; last = pos(e); grow(last); strokes++; ctx.beginPath(); ctx.arc(last.x, last.y, 1.2, 0, 6.3); ctx.fillStyle = '#13213f'; ctx.fill(); try { canvas.setPointerCapture(e.pointerId); } catch (x) {} if (api.onchange) api.onchange(); });
    canvas.addEventListener('pointermove', function (e) { if (!drawing) return; e.preventDefault(); var p = pos(e); ctx.beginPath(); ctx.moveTo(last.x, last.y); ctx.lineTo(p.x, p.y); ctx.stroke(); len += Math.abs(p.x - last.x) + Math.abs(p.y - last.y); last = p; grow(p); });
    function up() { drawing = false; if (api.onchange) api.onchange(); }
    canvas.addEventListener('pointerup', up); canvas.addEventListener('pointercancel', up); canvas.addEventListener('pointerleave', function () { if (drawing) up(); });
    var api = {
      clear: function () { ctx.save(); ctx.setTransform(1, 0, 0, 1, 0, 0); ctx.clearRect(0, 0, canvas.width, canvas.height); ctx.restore(); strokes = 0; len = 0; box = null; if (api.onchange) api.onchange(); },
      ok: function () { return strokes > 0 && len > 60 && box && (box.x1 - box.x0) > 40; },
      png: function () { // przycięty do podpisu, max 700 px szerokości, przezroczyste tło
        var d = window.devicePixelRatio || 1, pad = 10, x = Math.max(0, (box.x0 - pad) * d), y = Math.max(0, (box.y0 - pad) * d);
        var w = Math.min(canvas.width - x, (box.x1 - box.x0 + pad * 2) * d), h = Math.min(canvas.height - y, (box.y1 - box.y0 + pad * 2) * d);
        var sc = Math.min(1, 700 / w), o = document.createElement('canvas'); o.width = Math.round(w * sc); o.height = Math.round(h * sc);
        o.getContext('2d').drawImage(canvas, x, y, w, h, 0, 0, o.width, o.height);
        return o.toDataURL('image/png');
      },
      fit: fit, onchange: null
    };
    fit(); window.addEventListener('resize', function () { if (!drawing) fit(); });
    return api;
  };
})();
