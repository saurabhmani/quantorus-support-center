/* ============================================================
   custom-dashboard.js
   Chart.js bootstrapping + small UI interactions for
   include/staff/dashboard-pro.inc.php
   ============================================================ */
(function () {
  'use strict';

  var THEME_KEY = 'osp.theme';
  var charts = [];

  // Diagnostic logger — gated on ?debug=1 OR localStorage['osp.debug']='1'
  var DEBUG = /[?&]debug=1\b/.test(location.search);
  try { if (localStorage.getItem('osp.debug') === '1') DEBUG = true; } catch (e) {}
  function log()  { if (DEBUG) console.log.apply(console, ['[osp]'].concat([].slice.call(arguments))); }
  function warn() { console.warn.apply(console, ['[osp]'].concat([].slice.call(arguments))); }
  function err()  { console.error.apply(console, ['[osp]'].concat([].slice.call(arguments))); }

  // Wait up to ~2s for Chart.js to appear on window — handles slow parses
  // and dev-server glitches without giving up after a single tick.
  function whenChartReady(cb) {
    if (typeof window.Chart !== 'undefined') return cb(true);
    var tries = 0, max = 40;          // 40 × 50ms = 2s
    var iv = setInterval(function () {
      if (typeof window.Chart !== 'undefined') { clearInterval(iv); cb(true); }
      else if (++tries >= max)               { clearInterval(iv); cb(false); }
    }, 50);
  }

  function showChartFallback(root, reason) {
    root.querySelectorAll('canvas').forEach(function (c) {
      var msg = document.createElement('div');
      msg.style.cssText = 'display:grid;place-items:center;height:100%;color:#94a3b8;font-size:12px;text-align:center;padding:8px;';
      msg.innerHTML = '<div><strong>Chart library unavailable</strong><br><span style="font-size:10px">' + reason + '</span></div>';
      c.replaceWith(msg);
    });
  }

  function bootstrap() {
    var root = document.querySelector('.osp-dashboard');
    if (!root) { warn('no .osp-dashboard root found'); return; }
    log('boot: root mounted');

    // ---- Restore persisted theme BEFORE charts paint ---------
    var savedTheme = null;
    try { savedTheme = localStorage.getItem(THEME_KEY); } catch (e) {}
    if (savedTheme === 'dark' || savedTheme === 'light') {
      root.setAttribute('data-osp-theme', savedTheme);
    }
    log('theme:', root.getAttribute('data-osp-theme'));

    // ---- Read PHP-provided dataset ---------------------------
    var data = {};
    var raw = root.getAttribute('data-osp-bootstrap') || '{}';
    try { data = JSON.parse(raw); log('bootstrap parsed:', Object.keys(data)); }
    catch (e) { err('bootstrap JSON parse failed', e, raw.slice(0, 120) + '…'); }

    whenChartReady(function (ok) {
      if (!ok) {
        err('Chart.js missing after 2s — most common cause: osTicket CSP blocks the CDN. ' +
            'Verify scp/js/chart.umd.min.js exists and the inc.php loads it via ROOT_PATH.');
        showChartFallback(root, 'Chart.js did not load — check console for CSP / 404');
        wireInteractions(root);
        startCountdowns(root);
        return;
      }
      log('Chart.js v' + (Chart.version || 'unknown') + ' loaded');
      try { init(root, data); }
      catch (e) {
        err('init() threw — falling back', e);
        showChartFallback(root, e.message || 'init failed');
        wireInteractions(root);
        startCountdowns(root);
      }
    });
  }

  function init(root, data) {

    // ---- Chart.js global defaults ----------------------------
    Chart.defaults.font.family = '"Inter", ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, sans-serif';
    Chart.defaults.font.size = 11;
    Chart.defaults.font.weight = '500';
    Chart.defaults.color = '#64748b';
    Chart.defaults.borderColor = '#eef2f7';
    Chart.defaults.plugins.legend.display = false;
    Chart.defaults.plugins.tooltip = Object.assign({}, Chart.defaults.plugins.tooltip, {
      backgroundColor: 'rgba(15,23,42,.96)',
      borderColor: 'rgba(37,99,235,.35)',
      borderWidth: 1,
      padding: 10,
      cornerRadius: 8,
      titleColor: '#fff',
      bodyColor: '#e2e8f0',
      displayColors: true,
      boxPadding: 4
    });

    // ---- 1) Trend chart (created vs resolved vs breaches) ----
    var trendEl = document.getElementById('ospTrend');
    if (trendEl && data.trend) {
      var ctx = trendEl.getContext('2d');
      var gradCreated  = makeFill(ctx, 'rgba(37,99,235,.28)',  'rgba(37,99,235,.00)');
      var gradResolved = makeFill(ctx, 'rgba(16,185,129,.28)', 'rgba(16,185,129,.00)');

      charts.push(new Chart(trendEl, {
        type: 'line',
        data: {
          labels: data.trend.labels,
          datasets: [
            line('Created',  data.trend.created,  '#2563eb', gradCreated, true),
            line('Resolved', data.trend.resolved, '#10b981', gradResolved, true),
            line('Breaches', data.trend.breached, '#ef4444', null,         false)
          ]
        },
        options: lineOpts()
      }));
    }

    // ---- 2) Priority donut -----------------------------------
    var priEl = document.getElementById('ospPriority');
    if (priEl && data.priority && data.priority.length) {
      charts.push(new Chart(priEl, {
        type: 'doughnut',
        data: {
          labels: data.priority.map(function (p) { return p.label; }),
          datasets: [{
            data:            data.priority.map(function (p) { return p.value; }),
            backgroundColor: data.priority.map(function (p) { return p.color; }),
            borderColor: '#ffffff',
            borderWidth: 3,
            hoverOffset: 6
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          cutout: '72%',
          plugins: {
            tooltip: { callbacks: { label: function (c) { return c.label + ': ' + fmt(c.parsed); } } }
          }
        }
      }));
    }

    // ---- 3) Sources horizontal bar ---------------------------
    var srcEl = document.getElementById('ospSources');
    if (srcEl && data.sources && data.sources.length) {
      charts.push(new Chart(srcEl, {
        type: 'bar',
        data: {
          labels: data.sources.map(function (s) { return s.label; }),
          datasets: [{
            data: data.sources.map(function (s) { return s.value; }),
            backgroundColor: data.sources.map(function (s) { return s.color; }),
            borderRadius: 6,
            borderSkipped: false,
            barThickness: 14,
            maxBarThickness: 16
          }]
        },
        options: {
          indexAxis: 'y',
          responsive: true,
          maintainAspectRatio: false,
          layout: { padding: { right: 12 } },
          scales: {
            x: { display: false, grid: { display: false } },
            y: {
              grid: { display: false, drawBorder: false },
              ticks: { color: '#475569', font: { weight: '600', size: 11 } }
            }
          },
          plugins: {
            tooltip: { callbacks: { label: function (c) { return ' ' + fmt(c.parsed.x); } } }
          }
        }
      }));
    }

    applyChartTheme(root);
    wireInteractions(root);
    startCountdowns(root);
  }

  // ---- Re-tint chart axes/gridlines when theme changes -------
  function applyChartTheme(root) {
    var dark = root.getAttribute('data-osp-theme') === 'dark';
    var grid  = dark ? 'rgba(255,255,255,.06)' : 'rgba(15,23,42,.05)';
    var tick  = dark ? '#94a3b8' : '#94a3b8';
    var border= dark ? '#1e2541' : '#eef2f7';
    Chart.defaults.borderColor = border;
    charts.forEach(function (ch) {
      if (!ch || !ch.options) return;
      if (ch.options.scales) {
        if (ch.options.scales.x && ch.options.scales.x.ticks) ch.options.scales.x.ticks.color = tick;
        if (ch.options.scales.y) {
          if (ch.options.scales.y.ticks) ch.options.scales.y.ticks.color = tick;
          if (ch.options.scales.y.grid)  ch.options.scales.y.grid.color = grid;
        }
      }
      // Donut: white separator → match surface
      if (ch.config && ch.config.type === 'doughnut' && ch.data && ch.data.datasets[0]) {
        ch.data.datasets[0].borderColor = dark ? '#111527' : '#ffffff';
      }
      ch.update('none');
    });
  }

  // ---- Live ticking countdowns -------------------------------
  function startCountdowns(root) {
    var nodes = Array.prototype.slice.call(root.querySelectorAll('[data-osp-countdown]'));
    if (!nodes.length) return;

    function pad(n) { return (n < 10 ? '0' : '') + n; }
    function fmtT(secs) {
      var sign = secs < 0 ? '-' : '';
      var s = Math.abs(secs);
      var h = Math.floor(s / 3600);
      var m = Math.floor((s % 3600) / 60);
      var ss = s % 60;
      return sign + pad(h) + ':' + pad(m) + ':' + pad(ss);
    }
    function tick() {
      nodes.forEach(function (n) {
        var v = parseInt(n.getAttribute('data-osp-countdown'), 10);
        if (isNaN(v)) return;
        v -= 1;
        n.setAttribute('data-osp-countdown', v);
        n.textContent = fmtT(v);
        n.classList.remove('osp-cd-breach','osp-cd-risk','osp-cd-ok');
        n.classList.add(v < 0 ? 'osp-cd-breach' : (v < 1800 ? 'osp-cd-risk' : 'osp-cd-ok'));
      });
    }
    tick();
    setInterval(tick, 1000);
  }

  // ---------- helpers ------------------------------------------
  function fmt(n) { return (typeof n === 'number') ? n.toLocaleString() : n; }

  function makeFill(ctx, top, bot) {
    var g = ctx.createLinearGradient(0, 0, 0, 280);
    g.addColorStop(0, top);
    g.addColorStop(1, bot);
    return g;
  }

  function line(label, dataArr, color, fillGrad, fill) {
    return {
      label: label,
      data: dataArr,
      borderColor: color,
      backgroundColor: fillGrad || color,
      borderWidth: 2,
      tension: 0.38,
      pointRadius: 0,
      pointHoverRadius: 5,
      pointHoverBackgroundColor: color,
      pointHoverBorderColor: '#fff',
      pointHoverBorderWidth: 2,
      fill: fill ? 'origin' : false
    };
  }

  function lineOpts() {
    return {
      responsive: true,
      maintainAspectRatio: false,
      interaction: { mode: 'index', intersect: false },
      layout: { padding: { top: 8, right: 6, bottom: 0, left: 0 } },
      scales: {
        x: {
          grid: { display: false, drawBorder: false },
          ticks: { color: '#94a3b8', font: { size: 10 } }
        },
        y: {
          beginAtZero: true,
          grid: { color: 'rgba(15,23,42,.05)', drawBorder: false },
          ticks: {
            color: '#94a3b8',
            font: { size: 10 },
            callback: function (v) { return v >= 1000 ? (v / 1000) + 'k' : v; },
            maxTicksLimit: 5
          }
        }
      },
      plugins: {
        tooltip: { callbacks: { label: function (c) { return ' ' + c.dataset.label + ': ' + fmt(c.parsed.y); } } }
      }
    };
  }

  // Tab switches, mini-tabs, sidebar active-state, alert dismiss.
  function wireInteractions(root) {
    // Title-row time tabs
    root.querySelectorAll('.osp-tabs .osp-tab').forEach(function (btn) {
      btn.addEventListener('click', function () {
        root.querySelectorAll('.osp-tabs .osp-tab').forEach(function (b) { b.classList.remove('is-active'); });
        btn.classList.add('is-active');
      });
    });

    // Mini-tabs above the tickets table
    root.querySelectorAll('.osp-mini-tabs').forEach(function (group) {
      group.querySelectorAll('button').forEach(function (btn) {
        btn.addEventListener('click', function () {
          group.querySelectorAll('button').forEach(function (b) { b.classList.remove('is-active'); });
          btn.classList.add('is-active');
        });
      });
    });

    // Sidebar nav (purely visual until linked to real osTicket pages)
    root.querySelectorAll('.osp-nav-item').forEach(function (item) {
      item.addEventListener('click', function (e) {
        if (item.getAttribute('href') === '#') e.preventDefault();
        root.querySelectorAll('.osp-nav-item').forEach(function (i) { i.classList.remove('is-active'); });
        item.classList.add('is-active');
      });
    });

    // Alert action button is now a real <a> link that navigates the
    // user to the affected ticket / queue. No visual-collapse handler
    // needed — and we don't want to interfere with navigation.

    // ⌘K / Ctrl+K → focus search
    document.addEventListener('keydown', function (e) {
      var key = (e.key || '').toLowerCase();
      if ((e.metaKey || e.ctrlKey) && key === 'k') {
        var input = root.querySelector('.osp-search input');
        if (input) { e.preventDefault(); input.focus(); }
      }
    });

    // Theme toggle (persisted)
    var toggle = root.querySelector('[data-osp-theme-toggle]');
    if (toggle) {
      toggle.addEventListener('click', function () {
        var next = root.getAttribute('data-osp-theme') === 'dark' ? 'light' : 'dark';
        root.setAttribute('data-osp-theme', next);
        try { localStorage.setItem(THEME_KEY, next); } catch (e) {}
        applyChartTheme(root);
      });
    }

    // Workspace switcher click — visual only for now
    var ws = root.querySelector('.osp-ws');
    if (ws) {
      ws.addEventListener('click', function () {
        ws.style.transition = 'transform .12s ease';
        ws.style.transform = 'scale(.98)';
        setTimeout(function () { ws.style.transform = ''; }, 120);
      });
    }
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', bootstrap);
  } else {
    bootstrap();
  }
})();
