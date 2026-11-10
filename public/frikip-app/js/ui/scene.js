/*
 * PROTOTYPE SEULEMENT : les vraies vidéos sont remplacées par des scènes de foot animées
 * (terrain vu d'en haut, dribble entre les plots, but, freestyle, exercice, tribune).
 * Une fois l'API branchée, le fil affiche les vraies vidéos (<video src="...">) et ce fichier ne sert plus.
 */
window.Frikip = window.Frikip || {};
(function () {
  function rng(seed) { var s = 0; for (var i = 0; i < seed.length; i++) s = (s * 31 + seed.charCodeAt(i)) >>> 0; return function () { s = (s * 1664525 + 1013904223) >>> 0; return s / 4294967296; }; }
  function hexA(hex, a) { var n = parseInt(hex.slice(1), 16); return 'rgba(' + (n >> 16) + ',' + ((n >> 8) & 255) + ',' + (n & 255) + ',' + a + ')'; }

  function fit(canvas) {
    var r = canvas.getBoundingClientRect(), dpr = Math.min(window.devicePixelRatio || 1, 2);
    var w = Math.max(1, Math.round(r.width * dpr)), h = Math.max(1, Math.round(r.height * dpr));
    if (canvas.width !== w || canvas.height !== h) { canvas.width = w; canvas.height = h; }
    return { w: w, h: h, k: dpr };
  }

  function ball(ctx, x, y, r, rot) {
    ctx.save(); ctx.translate(x, y); ctx.rotate(rot || 0);
    ctx.fillStyle = '#fff'; ctx.beginPath(); ctx.arc(0, 0, r, 0, Math.PI * 2); ctx.fill();
    ctx.fillStyle = '#151a17'; ctx.beginPath();
    for (var i = 0; i < 5; i++) { var a = -Math.PI / 2 + i * 2 * Math.PI / 5; ctx.lineTo(Math.cos(a) * r * .38, Math.sin(a) * r * .38); }
    ctx.closePath(); ctx.fill();
    for (var j = 0; j < 5; j++) {
      var b = -Math.PI / 2 + j * 2 * Math.PI / 5;
      ctx.beginPath(); ctx.arc(Math.cos(b) * r * .95, Math.sin(b) * r * .95, r * .3, 0, Math.PI * 2); ctx.fill();
    }
    ctx.restore();
    ctx.strokeStyle = 'rgba(0,0,0,.25)'; ctx.lineWidth = r * .08; ctx.beginPath(); ctx.arc(x, y, r, 0, Math.PI * 2); ctx.stroke();
  }
  function night(ctx, w, h, hue) {
    var g = ctx.createLinearGradient(0, 0, 0, h); g.addColorStop(0, '#07110c'); g.addColorStop(1, '#010302'); ctx.fillStyle = g; ctx.fillRect(0, 0, w, h);
    var rg = ctx.createRadialGradient(w * .5, h * .18, 0, w * .5, h * .18, w * .9); rg.addColorStop(0, hexA(hue, .28)); rg.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = rg; ctx.fillRect(0, 0, w, h);
  }
  function lights(ctx, w, h, t) {
    [[.12, .06], [.88, .06]].forEach(function (p, i) {
      var x = w * p[0], y = h * p[1], a = .55 + .1 * Math.sin(t * 2 + i);
      var g = ctx.createRadialGradient(x, y, 0, x, y, w * .35); g.addColorStop(0, 'rgba(255,255,240,' + a + ')'); g.addColorStop(.15, 'rgba(255,255,230,.18)'); g.addColorStop(1, 'rgba(255,255,230,0)');
      ctx.fillStyle = g; ctx.fillRect(0, 0, w, h);
    });
  }
  function turf(ctx, w, h, y0) {   // pelouse en perspective à partir de la ligne d'horizon y0
    var stripes = 9;
    for (var i = 0; i < stripes; i++) {
      var a = y0 + (h - y0) * Math.pow(i / stripes, 1.6), b = y0 + (h - y0) * Math.pow((i + 1) / stripes, 1.6);
      ctx.fillStyle = i % 2 ? '#0f5a2b' : '#137033'; ctx.fillRect(0, a, w, b - a + 1);
    }
    var fog = ctx.createLinearGradient(0, y0, 0, y0 + (h - y0) * .35); fog.addColorStop(0, 'rgba(2,6,4,.85)'); fog.addColorStop(1, 'rgba(2,6,4,0)'); ctx.fillStyle = fog; ctx.fillRect(0, y0, w, h - y0);
  }
  function persp(w, h, y0, x, z) { var k = .25 + .75 * z; return { x: w / 2 + (x - .5) * w * 1.6 * k, y: y0 + (h - y0) * Math.pow(z, 1.6), s: k }; }

  var scenes = {
    pitch: function (ctx, w, h, t, hue, R) {   // terrain vu d'en haut, actions de jeu
      for (var i = 0; i < 12; i++) { ctx.fillStyle = i % 2 ? '#0f5a2b' : '#137033'; ctx.fillRect(0, h * i / 12, w, h / 12 + 1); }
      var m = w * .07; ctx.strokeStyle = 'rgba(255,255,255,.75)'; ctx.lineWidth = Math.max(2, w * .006);
      ctx.strokeRect(m, m, w - 2 * m, h - 2 * m);
      ctx.beginPath(); ctx.moveTo(m, h / 2); ctx.lineTo(w - m, h / 2); ctx.stroke();
      ctx.beginPath(); ctx.arc(w / 2, h / 2, w * .15, 0, Math.PI * 2); ctx.stroke();
      ctx.strokeRect(w * .25, m, w * .5, h * .14); ctx.strokeRect(w * .25, h - m - h * .14, w * .5, h * .14);
      ctx.strokeRect(w * .38, m, w * .24, h * .05); ctx.strokeRect(w * .38, h - m - h * .05, w * .24, h * .05);
      var home = [], away = [];
      for (var p = 0; p < 7; p++) {
        home.push([.2 + .6 * R(), .25 + .6 * R(), R() * 6, .5 + R()]);
        away.push([.2 + .6 * R(), .15 + .6 * R(), R() * 6, .5 + R()]);
      }
      var pos = function (pl) { return [w * (pl[0] + .06 * Math.sin(t * pl[3] + pl[2])), h * (pl[1] + .04 * Math.cos(t * pl[3] * .8 + pl[2]))]; };
      var seg = Math.floor(t / 1.2), f = (t / 1.2) % 1, A = pos(home[seg % 7]), B = pos(home[(seg + 3) % 7]);
      var e = f < .5 ? 2 * f * f : 1 - Math.pow(-2 * f + 2, 2) / 2;
      var bx = A[0] + (B[0] - A[0]) * e, by = A[1] + (B[1] - A[1]) * e;
      ctx.strokeStyle = hexA(hue, .55); ctx.lineWidth = w * .008; ctx.setLineDash([w * .02, w * .015]); ctx.beginPath(); ctx.moveTo(A[0], A[1]); ctx.lineTo(B[0], B[1]); ctx.stroke(); ctx.setLineDash([]);
      away.forEach(function (pl) { var q = pos(pl); ctx.fillStyle = 'rgba(0,0,0,.35)'; ctx.beginPath(); ctx.arc(q[0] + 3, q[1] + 4, w * .032, 0, 7); ctx.fill(); ctx.fillStyle = '#f4f7f5'; ctx.beginPath(); ctx.arc(q[0], q[1], w * .03, 0, 7); ctx.fill(); });
      home.forEach(function (pl) { var q = pos(pl); ctx.fillStyle = 'rgba(0,0,0,.35)'; ctx.beginPath(); ctx.arc(q[0] + 3, q[1] + 4, w * .032, 0, 7); ctx.fill(); ctx.fillStyle = hue; ctx.beginPath(); ctx.arc(q[0], q[1], w * .03, 0, 7); ctx.fill(); });
      ball(ctx, bx, by, w * .018, t * 6);
    },
    skills: function (ctx, w, h, t, hue) {   // dribble entre les plots
      night(ctx, w, h, hue); lights(ctx, w, h, t); var y0 = h * .38; turf(ctx, w, h, y0);
      for (var i = 0; i < 6; i++) {
        var z = .25 + i * .13, c = persp(w, h, y0, .5 + (i % 2 ? .12 : -.12), z), s = w * .05 * c.s;
        ctx.fillStyle = '#FF7A1A'; ctx.beginPath(); ctx.moveTo(c.x, c.y - s * 1.4); ctx.lineTo(c.x - s * .7, c.y); ctx.lineTo(c.x + s * .7, c.y); ctx.closePath(); ctx.fill();
        ctx.fillStyle = '#fff'; ctx.fillRect(c.x - s * .45, c.y - s * .7, s * .9, s * .18);
      }
      var u = (t * .32) % 1, zb = .2 + u * .78, xb = .5 + .2 * Math.sin(u * Math.PI * 6), bp = persp(w, h, y0, xb, zb), r = w * .045 * bp.s;
      var hop = Math.abs(Math.sin(t * 7)) * r * .6;
      ctx.fillStyle = 'rgba(0,0,0,.45)'; ctx.beginPath(); ctx.ellipse(bp.x, bp.y, r * 1.1, r * .35, 0, 0, 7); ctx.fill();
      ctx.strokeStyle = hexA(hue, .6); ctx.lineWidth = r * .35; ctx.beginPath();
      for (var k = 0; k <= 20; k++) { var uu = Math.max(0, u - .12 + k * .006), q = persp(w, h, y0, .5 + .2 * Math.sin(uu * Math.PI * 6), .2 + uu * .78); if (k) ctx.lineTo(q.x, q.y); else ctx.moveTo(q.x, q.y); }
      ctx.stroke();
      ball(ctx, bp.x, bp.y - r - hop, r, t * 9);
    },
    goal: function (ctx, w, h, t, hue) {   // frappe en pleine lucarne
      night(ctx, w, h, hue); lights(ctx, w, h, t); var y0 = h * .52; turf(ctx, w, h, y0);
      var gx = w * .1, gw = w * .8, gy = h * .3, gh = h * .22;
      var cyc = (t % 3) / 3, hit = cyc > .62, bulge = hit ? Math.sin(Math.min(1, (cyc - .62) / .2) * Math.PI) * w * .05 : 0;
      ctx.strokeStyle = 'rgba(255,255,255,.28)'; ctx.lineWidth = 1.5;
      for (var i = 0; i <= 14; i++) { var x = gx + gw * i / 14; ctx.beginPath(); ctx.moveTo(x, gy); ctx.quadraticCurveTo(x + (x > gx + gw * .7 ? bulge * .6 : 0), gy + gh / 2 - (x > gx + gw * .7 ? bulge : 0), x, gy + gh); ctx.stroke(); }
      for (var j = 0; j <= 7; j++) { var y = gy + gh * j / 7; ctx.beginPath(); ctx.moveTo(gx, y); ctx.lineTo(gx + gw, y); ctx.stroke(); }
      ctx.strokeStyle = '#fff'; ctx.lineWidth = w * .016; ctx.beginPath(); ctx.moveTo(gx, gy + gh); ctx.lineTo(gx, gy); ctx.lineTo(gx + gw, gy); ctx.lineTo(gx + gw, gy + gh); ctx.stroke();
      var kx = w * .45, ky = h * .9, tx = gx + gw * .9, ty = gy + gh * .18, p = Math.min(1, cyc / .62);
      var bx = kx + (tx - kx) * p + Math.sin(p * Math.PI) * w * .16, by = ky + (ty - ky) * p - Math.sin(p * Math.PI) * h * .08, r = w * (.05 - .025 * p);
      if (!hit) {
        ctx.strokeStyle = hexA(hue, .7); ctx.lineWidth = r * .7; ctx.lineCap = 'round'; ctx.beginPath();
        for (var s = Math.max(0, p - .25); s <= p; s += .02) { var xx = kx + (tx - kx) * s + Math.sin(s * Math.PI) * w * .16, yy = ky + (ty - ky) * s - Math.sin(s * Math.PI) * h * .08; if (s === Math.max(0, p - .25)) ctx.moveTo(xx, yy); else ctx.lineTo(xx, yy); }
        ctx.stroke(); ball(ctx, bx, by, r, t * 12);
      } else {
        ball(ctx, tx + bulge * .3, ty + h * .02, w * .025, 0);
        var fl = 1 - (cyc - .62) / .38; ctx.fillStyle = hexA(hue, .22 * fl); ctx.fillRect(0, 0, w, h);
      }
    },
    freestyle: function (ctx, w, h, t, hue) {   // jongles sur la corniche
      var g = ctx.createLinearGradient(0, 0, 0, h); g.addColorStop(0, '#0b1020'); g.addColorStop(.55, '#1a1430'); g.addColorStop(.56, '#0a2a33'); g.addColorStop(1, '#030807'); ctx.fillStyle = g; ctx.fillRect(0, 0, w, h);
      var sun = ctx.createRadialGradient(w * .7, h * .5, 0, w * .7, h * .5, w * .6); sun.addColorStop(0, hexA(hue, .45)); sun.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = sun; ctx.fillRect(0, 0, w, h);
      for (var i = 0; i < 8; i++) { ctx.strokeStyle = 'rgba(255,255,255,' + (.05 + .03 * (i % 3)) + ')'; ctx.beginPath(); var y = h * (.58 + i * .025); ctx.moveTo(0, y + Math.sin(t + i) * 3); ctx.lineTo(w, y + Math.cos(t + i) * 3); ctx.stroke(); }
      ctx.fillStyle = '#06100b'; ctx.fillRect(0, h * .8, w, h * .2);
      var cx = w * .5, base = h * .74, jh = h * .26, ph = (t * 1.6) % 1, y2 = base - jh * 4 * ph * (1 - ph), r = w * .06;
      ctx.strokeStyle = hexA(hue, .8); ctx.lineWidth = w * .012; ctx.beginPath(); ctx.ellipse(cx, y2, r * 2.2, r * .7, 0, t * 4, t * 4 + Math.PI * 1.3); ctx.stroke();
      ctx.fillStyle = 'rgba(0,0,0,.5)'; ctx.beginPath(); ctx.ellipse(cx, h * .81, r * (1.4 - .6 * (base - y2) / jh), r * .3, 0, 0, 7); ctx.fill();
      ball(ctx, cx, y2, r, t * 5);
      for (var k = 0; k < 18; k++) { var a = k / 18 * Math.PI * 2 + t * .3; ctx.fillStyle = 'rgba(255,255,255,' + (.25 + .25 * Math.sin(t * 3 + k)) + ')'; ctx.fillRect(w * .5 + Math.cos(a) * w * .45, h * .2 + Math.sin(a) * h * .08, 2, 2); }
    },
    training: function (ctx, w, h, t, hue) {   // passes en triangle
      for (var i = 0; i < 10; i++) { ctx.fillStyle = i % 2 ? '#0f5a2b' : '#137033'; ctx.fillRect(0, h * i / 10, w, h / 10 + 1); }
      ctx.strokeStyle = 'rgba(255,255,255,.6)'; ctx.lineWidth = Math.max(2, w * .005); ctx.strokeRect(w * .06, h * .06, w * .88, h * .88);
      var pts = [[.5, .28], [.22, .7], [.78, .7]];
      pts.forEach(function (p) { var x = w * p[0], y = h * p[1], s = w * .035; ctx.fillStyle = '#FF7A1A'; ctx.beginPath(); ctx.moveTo(x, y - s); ctx.lineTo(x - s * .8, y + s * .5); ctx.lineTo(x + s * .8, y + s * .5); ctx.closePath(); ctx.fill(); });
      var seg = Math.floor(t / 1.1), f = (t / 1.1) % 1, dir = Math.floor(t / 6.6) % 2 ? -1 : 1;
      var a = pts[((seg * dir) % 3 + 3) % 3], b = pts[(((seg + 1) * dir) % 3 + 3) % 3];
      pts.forEach(function (p, i) {
        var x = w * p[0] + w * .07, y = h * p[1] + Math.sin(t * 3 + i) * 3;
        ctx.fillStyle = 'rgba(0,0,0,.35)'; ctx.beginPath(); ctx.arc(x + 3, y + 4, w * .04, 0, 7); ctx.fill();
        ctx.fillStyle = hue; ctx.beginPath(); ctx.arc(x, y, w * .038, 0, 7); ctx.fill();
        ctx.fillStyle = '#fff'; ctx.font = '900 ' + Math.round(w * .036) + 'px Figtree, sans-serif'; ctx.textAlign = 'center'; ctx.textBaseline = 'middle'; ctx.fillText(String(i + 1), x, y + 1);
      });
      ctx.strokeStyle = 'rgba(255,255,255,.35)'; ctx.setLineDash([6, 6]); ctx.lineWidth = 2; ctx.beginPath(); pts.forEach(function (p, i) { var x = w * p[0], y = h * p[1]; if (i) ctx.lineTo(x, y); else ctx.moveTo(x, y); }); ctx.closePath(); ctx.stroke(); ctx.setLineDash([]);
      ball(ctx, w * (a[0] + (b[0] - a[0]) * f), h * (a[1] + (b[1] - a[1]) * f) - Math.sin(f * Math.PI) * h * .015, w * .022, t * 8);
    },
    crowd: function (ctx, w, h, t, hue, R) {   // tribune, tifo, fumigènes
      night(ctx, w, h, hue);
      for (var b = 0; b < 4; b++) { var x = w * (.15 + b * .23), a = Math.sin(t * .7 + b) * .25; ctx.save(); ctx.translate(x, 0); ctx.rotate(a); var lg = ctx.createLinearGradient(0, 0, 0, h * .9); lg.addColorStop(0, hexA(hue, .35)); lg.addColorStop(1, 'rgba(0,0,0,0)'); ctx.fillStyle = lg; ctx.beginPath(); ctx.moveTo(-w * .02, 0); ctx.lineTo(w * .02, 0); ctx.lineTo(w * .14, h * .9); ctx.lineTo(-w * .14, h * .9); ctx.fill(); ctx.restore(); }
      var rows = 22, cols = 16;
      for (var r = 0; r < rows; r++) for (var c = 0; c < cols; c++) {
        var y = h * .3 + r * h * .03, x = (c + .5 + (r % 2) * .5) * w / cols, j = R(), jump = Math.max(0, Math.sin(t * 6 + c * .7 + r * .3)) * h * .006;
        var tifo = r > 6 && r < 15 && c > 2 && c < 13;
        ctx.fillStyle = tifo ? (((c + r) % 3) ? hue : '#ffffff') : (j > .5 ? '#2a3a32' : '#1b2721');
        ctx.beginPath(); ctx.arc(x, y - jump, w * .022, 0, 7); ctx.fill();
      }
      for (var f = 0; f < 3; f++) {
        var fx = w * (.2 + f * .3), fy = h * .25;
        ctx.fillStyle = f === 1 ? '#fff' : hue; ctx.beginPath(); ctx.moveTo(fx, fy);
        for (var s = 0; s <= 10; s++) ctx.lineTo(fx + s * w * .02, fy + Math.sin(t * 5 + s * .6 + f) * h * .01);
        for (var s2 = 10; s2 >= 0; s2--) ctx.lineTo(fx + s2 * w * .02, fy + h * .06 + Math.sin(t * 5 + s2 * .6 + f) * h * .01);
        ctx.fill(); ctx.fillStyle = '#ccc'; ctx.fillRect(fx - 2, fy, 3, h * .12);
      }
      for (var p = 0; p < 30; p++) { var py = h * (1 - ((t * .08 + p / 30) % 1)), px = w * ((p * 37 % 100) / 100) + Math.sin(t + p) * 10; ctx.fillStyle = 'rgba(255,90,60,' + (.25 * (py / h)) + ')'; ctx.beginPath(); ctx.arc(px, py, w * .03 * (1 - py / h + .3), 0, 7); ctx.fill(); }
    }
  };

  function render(canvas, v, t) {
    var ctx = canvas.getContext('2d'), s = fit(canvas);
    var fn = scenes[v.scene] || scenes.pitch, R = rng(v.id);
    ctx.save(); fn(ctx, s.w, s.h, t, v.hue || '#1FE064', R); ctx.restore();
  }

  // Une seule boucle d'animation pour la scène visible ; les autres restent figées.
  var active = null, raf = 0, start = 0;
  function loop(ts) {
    if (!active) return;
    if (!start) start = ts;
    var t = (ts - start) / 1000 + active.offset;
    render(active.canvas, active.video, t);
    if (active.onTime) active.onTime(t);
    raf = requestAnimationFrame(loop);
  }
  var reduce = window.matchMedia && matchMedia('(prefers-reduced-motion: reduce)').matches;

  Frikip.scene = {
    still: function (canvas, v, t) { requestAnimationFrame(function () { render(canvas, v, t == null ? 1.7 : t); }); },
    play: function (canvas, v, onTime) {
      Frikip.scene.stop();
      if (reduce) { render(canvas, v, 1.7); return; }
      active = { canvas: canvas, video: v, onTime: onTime, offset: 0 }; start = 0; raf = requestAnimationFrame(loop);
    },
    pause: function () { if (active && start) { active.offset += (performance.now() - start) / 1000; } cancelAnimationFrame(raf); raf = 0; start = 0; },
    resume: function () { if (active && !raf && !reduce) raf = requestAnimationFrame(loop); },
    stop: function () { cancelAnimationFrame(raf); raf = 0; active = null; },
    isPlaying: function () { return !!raf; }
  };
})();
