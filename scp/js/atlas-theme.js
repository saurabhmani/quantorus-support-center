/* ============================================================
   atlas-theme.js
   Tiny JS enhancements for the global Atlas theme.
   - Restores light/dark theme from localStorage
   - Adds Shift+click range-select on table.list checkboxes
   - Smooth-scroll anchor links inside #content
   - Optional ?atl-debug=1 console diagnostics
   No jQuery dependency; works alongside osTicket's existing JS.
   ============================================================ */
(function () {
  'use strict';

  var THEME_KEY = 'atl.theme';

  function applyTheme() {
    try {
      var t = localStorage.getItem(THEME_KEY);
      if (t === 'dark' || t === 'light') {
        document.body.setAttribute('data-osp-theme', t);
      }
    } catch (e) {}
  }

  // Public toggler — call from a button with onclick="window.atlToggleTheme()"
  window.atlToggleTheme = function () {
    var next = document.body.getAttribute('data-osp-theme') === 'dark' ? 'light' : 'dark';
    document.body.setAttribute('data-osp-theme', next);
    try { localStorage.setItem(THEME_KEY, next); } catch (e) {}
    return next;
  };

  // Range-select on table.list checkboxes (Shift+click)
  function wireTableShiftRange() {
    document.querySelectorAll('table.list').forEach(function (table) {
      var lastChecked = null;
      table.addEventListener('click', function (e) {
        var t = e.target;
        if (!(t && t.matches && t.matches('input[type="checkbox"]'))) return;
        if (e.shiftKey && lastChecked && lastChecked !== t) {
          var boxes = Array.prototype.slice.call(table.querySelectorAll('tbody input[type="checkbox"]'));
          var a = boxes.indexOf(lastChecked), b = boxes.indexOf(t);
          if (a > -1 && b > -1) {
            var lo = Math.min(a, b), hi = Math.max(a, b);
            for (var i = lo; i <= hi; i++) boxes[i].checked = t.checked;
          }
        }
        lastChecked = t;
      });
    });
  }

  // Smooth-scroll for in-page anchors
  function wireSmoothAnchors() {
    document.body.addEventListener('click', function (e) {
      var a = e.target && e.target.closest && e.target.closest('a[href^="#"]');
      if (!a) return;
      var href = a.getAttribute('href');
      if (!href || href.length < 2 || href === '#') return;
      var target = document.querySelector(href);
      if (target) { e.preventDefault(); target.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
    });
  }

  // ============================================================
  // GLOBAL APP SHELL — fixed sidebar + top bar on every page
  // (Skipped on dashboard-pro which has its own .osp-sidebar)
  // ============================================================
  var SVG = {
    grid:     '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
    inbox:    '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>',
    check:    '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
    users:    '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    team:     '<circle cx="12" cy="8" r="3"/><circle cx="5"  cy="11" r="2.5"/><circle cx="19" cy="11" r="2.5"/><path d="M3 19c0-2.2 2-3.5 4-3.5M21 19c0-2.2-2-3.5-4-3.5M7 21c0-3 2.5-5 5-5s5 2 5 5"/>',
    building: '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/>',
    tower:    '<rect x="3" y="9" width="18" height="11" rx="2"/><path d="M7 9V5a2 2 0 0 1 2-2h6a2 2 0 0 1 2 2v4"/><line x1="9" y1="14" x2="9" y2="14.01"/><line x1="15" y1="14" x2="15" y2="14.01"/>',
    book:     '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
    pie:      '<path d="M21 12A9 9 0 1 1 12 3v9Z"/><path d="M21 12c0-1.66-.4-3.22-1.1-4.6L12 12"/>',
    file:     '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/>',
    settings: '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82 1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>',
    shield:   '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z"/>',
    sliders:  '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
    columns:  '<rect x="3" y="3" width="7" height="18" rx="1.5"/><rect x="14" y="3" width="7" height="18" rx="1.5"/>',
    pulse:    '<polyline points="3 12 7 12 10 4 14 20 17 12 21 12"/>',
    mail:     '<rect x="3" y="5" width="18" height="14" rx="2"/><polyline points="3 7 12 13 21 7"/>',
    bell:     '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
    search:   '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    sparkle:  '<path d="M12 3l1.9 5.6L19.5 10l-5.6 1.4L12 17l-1.9-5.6L4.5 10l5.6-1.4z"/>',
    plus:     '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
    menu:     '<line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="18" x2="21" y2="18"/>',
    flame:    '<path d="M12 2c2 4 6 6 6 11a6 6 0 0 1-12 0c0-3 2-4 3-7 0 2 1 3 3 3-1-3 0-5 0-7Z"/>',
    clock:    '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
    home:     '<path d="M3 12l9-9 9 9"/><path d="M5 10v10h14V10"/>',
    chevronL: '<polyline points="15 18 9 12 15 6"/>',
    chevronR: '<polyline points="9 18 15 12 9 6"/>',
    sun:      '<circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.93" y1="4.93" x2="6.81" y2="6.81"/><line x1="17.19" y1="17.19" x2="19.07" y2="19.07"/><line x1="4.93" y1="19.07" x2="6.81" y2="17.19"/><line x1="17.19" y1="6.81" x2="19.07" y2="4.93"/>',
    moon:     '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/>'
  };

  function icon(name) {
    var body = SVG[name] || SVG.grid;
    return '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="atl-nav-ico" aria-hidden="true">' + body + '</svg>';
  }

  // The canonical staff-panel modules — every href below is a verified
  // existing /scp/*.php file in standard osTicket OSS.
  var NAV = [
    { section: 'Workspace' },
    { label: 'Dashboard',     href: '',                    icon: 'home',     match: ['/scp/?$', '/scp/index\\.php$', 'dashboard-pro\\.php', 'dashboard\\.php$'] },
    { label: 'Tickets',       href: 'tickets.php',         icon: 'inbox',    match: ['tickets\\.php'] },
    { label: 'Tasks',         href: 'tasks.php',           icon: 'check',    match: ['tasks\\.php'] },
    { label: 'Users',         href: 'users.php',           icon: 'users',    match: ['users\\.php'] },
    { label: 'Organizations', href: 'orgs.php',            icon: 'building', match: ['orgs\\.php'] },
    { label: 'Knowledge Base',href: 'kb.php',              icon: 'book',     match: ['kb\\.php', 'faq\\.php', 'canned\\.php'] },

    { section: 'Operations' },
    { label: 'Agents',        href: 'staff.php',           icon: 'users',    match: ['staff\\.php', 'directory\\.php'] },
    { label: 'Teams',         href: 'teams.php',           icon: 'team',     match: ['teams\\.php'] },
    { label: 'Departments',   href: 'departments.php',     icon: 'tower',    match: ['departments\\.php'] },
    { label: 'SLA Plans',     href: 'slas.php',            icon: 'shield',   match: ['slas?\\.php'] },
    { label: 'Filters',       href: 'filters.php',         icon: 'sliders',  match: ['filters\\.php'] },
    { label: 'Queues',        href: 'queues.php',          icon: 'columns',  match: ['queues\\.php'] },

    { section: 'Insights' },
    { label: 'Analytics',     href: 'dashboard-pro.php',   icon: 'pie',      match: ['dashboard-pro\\.php'] },
    { label: 'Reports',       href: 'reports.php',         icon: 'file',     match: ['reports\\.php'] },

    { section: 'Configuration' },
    { label: 'Emails',        href: 'emails.php',          icon: 'mail',     match: ['emails\\.php', 'emailsettings\\.php'] },
    { label: 'Forms & Lists', href: 'forms.php',           icon: 'file',     match: ['forms\\.php', 'lists\\.php'] },
    { label: 'System Logs',   href: 'logs.php',            icon: 'pulse',    match: ['logs\\.php', 'audits\\.php'] },
    { label: 'Settings',      href: 'settings.php',        icon: 'settings', match: ['settings\\.php', 'admin\\.php', 'system\\.php'] }
  ];

  function isActive(item) {
    if (!item) return false;
    var url = location.pathname + location.search;
    return item.match.some(function (rx) { return new RegExp(rx).test(url); });
  }

  function rootPath() {
    // The /scp/ prefix; works whether osTicket is at /osticket/scp/ or /scp/
    var p = location.pathname;
    var i = p.lastIndexOf('/scp/');
    return i >= 0 ? p.slice(0, i + 5) : '/scp/';
  }

  // Build a real, never-empty href from a NAV item.
  // - Empty href is treated as "Dashboard root" (rootPath()).
  // - Strips any leading slash so rootPath() doesn't double up.
  // - GUARANTEES a non-"#" URL. If item is malformed, falls back to root.
  function buildHref(item) {
    var base = rootPath();
    if (!item || typeof item !== 'object') return base;
    var h = item.href;
    if (h === undefined || h === null) return base;       // "Dashboard"
    if (typeof h !== 'string')         return base;
    h = h.replace(/^\/+/, '');                            // strip leading /
    return base + h;                                       // always real URL
  }

  function renderSidebar() {
    var aside = document.createElement('aside');
    aside.id = 'atl-sidebar';
    aside.className = 'no-pjax';   // root-level PJAX exemption

    var html = ''
      + '<div class="atl-brand no-pjax">'
      +   '<div class="atl-brand-mark">' + icon('sparkle') + '</div>'
      +   '<div class="atl-brand-text">'
      +     '<span class="atl-brand-name">osTicket</span>'
      +     '<span class="atl-brand-sub">Support Operations</span>'
      +   '</div>'
      +   '<button class="atl-collapse" type="button" aria-label="Collapse sidebar" data-atl-collapse>' + icon('chevronL') + '</button>'
      + '</div>'
      + '<div class="atl-side-body">';

    var inSection = false;
    var diag = [];                          // collected for ?atl-debug=1
    NAV.forEach(function (item) {
      if (item && item.section) {
        if (inSection) html += '</nav>';
        html += '<div class="atl-nav-label">' + escapeHTML(item.section) + '</div><nav class="atl-nav no-pjax">';
        inSection = true;
        return;
      }
      if (!item || !item.label) return;     // skip malformed entries — never emit "#"

      var url    = buildHref(item);
      var active = isActive(item);

      diag.push({ label: item.label, href: url, active: active });

      html += '<a href="' + url + '" class="atl-nav-item no-pjax' + (active ? ' is-active' : '') + '" '
            + 'data-no-pjax="1" data-tip="' + escapeHTML(item.label) + '">'
            + '<span class="atl-rail"></span>'
            + icon(item.icon || 'grid')
            + '<span class="atl-nav-text">' + escapeHTML(item.label) + '</span>'
            + '</a>';
    });
    if (inSection) html += '</nav>';
    html += '</div>';

    html += '<div class="atl-side-foot">'
          + '<div class="atl-status">'
          +   '<span class="atl-status-dot"></span>'
          +   '<span class="atl-status-label">All systems operational</span>'
          + '</div>'
          + '<div class="atl-status-meta">Atlas Theme · v1.1</div>'
          + '</div>';

    aside.innerHTML = html;

    // Debug: dump the rendered route map so devtools can confirm
    if (/[?&]atl-debug=1\b/.test(location.search)) {
      console.group('[atl] sidebar route map');
      console.table(diag);
      console.groupEnd();
    }

    return aside;
  }

  function renderTopbar() {
    // Move the welcome / profile / logout links from osTicket's #header p#info
    // into a new top bar so users keep their actions.
    var info = document.querySelector('#header #info');
    var welcome = info ? info.innerHTML : '';

    var bar = document.createElement('header');
    bar.id = 'atl-topbar';
    bar.className = 'no-pjax';   // sibling-level PJAX exemption for every link inside

    // Build a tasteful breadcrumb from <h2> or page title
    var firstH2 = document.querySelector('#content h2');
    var crumbCurrent = firstH2 ? firstH2.textContent.trim() : (document.title.split('::').pop() || 'Workspace').trim();

    bar.innerHTML = ''
      + '<button class="atl-icon-btn atl-mobile-toggle no-pjax" type="button" aria-label="Open navigation" data-atl-mobile-toggle>'
      +   icon('menu')
      + '</button>'
      + '<div class="atl-crumbs no-pjax">'
      +   '<a class="no-pjax" data-no-pjax="1" href="' + rootPath() + '">Home</a>'
      +   '<span class="atl-sep">/</span>'
      +   '<span class="atl-current">' + escapeHTML(crumbCurrent) + '</span>'
      + '</div>'
      + '<div class="atl-top-right no-pjax">'
      +   '<a class="atl-btn atl-btn-primary no-pjax atl-newticket" data-no-pjax="1" '
      +     'href="' + rootPath() + 'tickets.php?a=open" title="Open a new ticket">'
      +     icon('plus') + '<span class="atl-newticket-label">New ticket</span>'
      +   '</a>'
      +   '<button class="atl-icon-btn no-pjax" type="button" aria-label="Toggle theme" onclick="window.atlToggleTheme()">'
      +     '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" class="atl-nav-ico"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/></svg>'
      +   '</button>'
      +   '<button class="atl-icon-btn atl-bell-btn no-pjax" type="button" aria-label="Notifications" data-atl-bell>'
      +     icon('bell')
      +     '<span class="atl-bell-dot" hidden></span>'
      +     '<span class="atl-bell-badge" hidden>0</span>'
      +   '</button>'
      +   '<a class="atl-user no-pjax" data-no-pjax="1" href="' + rootPath() + 'profile.php">'
      +     '<span class="atl-avatar">' + extractInitials() + '</span>'
      +     '<span class="atl-user-name">' + extractStaffName() + '</span>'
      +   '</a>'
      + '</div>';
    bar._welcomeHTML = welcome;  // kept for reference
    return bar;
  }

  function extractStaffName() {
    var info = document.querySelector('#header #info strong');
    return info ? escapeHTML(info.textContent.trim()) : 'Staff';
  }
  function extractInitials() {
    var name = extractStaffName();
    var parts = name.split(/\s+/);
    var s = (parts[0][0] || 'S') + (parts[1] ? parts[1][0] : '');
    return s.toUpperCase();
  }
  function escapeHTML(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
    });
  }

  function buildShell() {
    if (document.querySelector('.osp-dashboard')) return;
    if (document.body.classList.contains('atl-shell')) return;
    if (!document.querySelector('#container')) return;

    var aside  = renderSidebar();
    var topbar = renderTopbar();
    document.body.insertBefore(topbar, document.body.firstChild);
    document.body.insertBefore(aside,  document.body.firstChild);
    document.body.classList.add('atl-shell');

    // Restore collapsed state
    try {
      if (localStorage.getItem('atl.collapsed') === '1') {
        document.body.classList.add('atl-collapsed');
      }
    } catch (e) {}

    // Wire collapse toggle
    var btn = aside.querySelector('[data-atl-collapse]');
    if (btn) {
      btn.addEventListener('click', function () {
        var next = !document.body.classList.contains('atl-collapsed');
        document.body.classList.toggle('atl-collapsed', next);
        try { localStorage.setItem('atl.collapsed', next ? '1' : '0'); } catch (e) {}
      });
    }
  }

  // ============================================================
  // NOTIFICATION BELL — polls atlas-notifications.php for the
  // unread count and a feed of recent ticket activity. Renders a
  // compact dropdown panel below the bell. Works for any element
  // marked [data-atl-bell] (Atlas topbar AND the dashboard topbar).
  // ============================================================
  var BELL_POLL_MS    = 8000;           // 8 seconds — feels live, low load
  var BELL_FEED_PATH  = 'atlas-notifications.php';
  var BELL_PATH       = null;           // resolved on first call
  var BELL_LAST_VER   = null;           // last server version hash (for early-out)
  var BELL_SEEN       = Object.create(null); // ticket numbers we've shown this session
  var BELL_BACKOFF    = 0;              // grows on errors
  var BELL_TIMER      = null;

  function notifPath() {
    if (BELL_PATH) return BELL_PATH;
    BELL_PATH = rootPath() + BELL_FEED_PATH;
    return BELL_PATH;
  }

  function bellSeverityClass(sev) {
    if (sev === 'breach')   return 'atl-notif-breach';
    if (sev === 'assigned') return 'atl-notif-assigned';
    if (sev === 'reply')    return 'atl-notif-reply';
    return 'atl-notif-info';
  }
  function bellSeverityIcon(sev) {
    if (sev === 'breach')   return icon('flame');
    if (sev === 'assigned') return icon('check');
    if (sev === 'reply')    return icon('mail');
    return icon('inbox');
  }

  function ensureBellPanel(btn) {
    var panel = btn._atlPanel;
    if (panel && document.body.contains(panel)) return panel;

    panel = document.createElement('div');
    panel.className = 'atl-notif-panel no-pjax';
    panel.setAttribute('role', 'dialog');
    panel.innerHTML = ''
      + '<div class="atl-notif-head">'
      +   '<div class="atl-notif-titles">'
      +     '<div class="atl-notif-title">Notifications</div>'
      +     '<div class="atl-notif-sub" data-atl-notif-sub>Loading…</div>'
      +   '</div>'
      + '</div>'
      + '<div class="atl-notif-body" data-atl-notif-body>'
      +   '<div class="atl-notif-empty">No recent ticket activity</div>'
      + '</div>'
      + '<div class="atl-notif-foot">'
      +   '<a class="no-pjax" data-no-pjax="1" href="' + rootPath() + 'tickets.php">Open ticket queue →</a>'
      + '</div>';

    document.body.appendChild(panel);
    btn._atlPanel = panel;

    // Click anywhere on a notification → optimistic mark-read + navigate
    var body = panel.querySelector('[data-atl-notif-body]');
    body.addEventListener('click', function (e) {
      var item = e.target && e.target.closest && e.target.closest('.atl-notif-item');
      if (!item) return;
      var tid = item.getAttribute('data-ticket-id');
      if (!tid) return;
      // Optimistic DOM update — instant feedback even before navigation
      if (item.classList.contains('is-unread')) {
        item.classList.remove('is-unread');
        item.classList.add('is-just-read');
        var current = countUnreadInPanel(panel);
        updateBellBadge(current);
      }
      // Fire-and-forget; keepalive lets the request survive page navigation
      try {
        fetch(notifPath() + '?read=' + encodeURIComponent(tid), {
          method: 'POST',
          credentials: 'same-origin',
          keepalive: true,
          headers: { 'Accept': 'application/json' }
        }).catch(function () {});
      } catch (err) { /* ignore — navigation still proceeds */ }
      // Native <a> navigation continues — no preventDefault.
    });

    return panel;
  }

  function countUnreadInPanel(panel) {
    return panel.querySelectorAll('.atl-notif-item.is-unread').length;
  }

  function positionBellPanel(btn, panel) {
    var rect = btn.getBoundingClientRect();
    var pw = 360;
    var top = rect.bottom + window.scrollY + 8;
    // Right-align so panel sits under the bell, never overflows viewport
    var maxLeft = window.innerWidth - pw - 12;
    var left = Math.min(maxLeft, rect.right + window.scrollX - pw);
    if (left < 12) left = 12;
    panel.style.top  = top + 'px';
    panel.style.left = left + 'px';
    panel.style.width = Math.min(pw, window.innerWidth - 24) + 'px';
  }

  function updateBellBadge(count) {
    document.querySelectorAll('[data-atl-bell]').forEach(function (b) {
      var dot   = b.querySelector('.atl-bell-dot');
      var badge = b.querySelector('.atl-bell-badge');
      if (count > 0) {
        if (dot)   dot.hidden = false;
        if (badge) { badge.hidden = false; badge.textContent = count > 99 ? '99+' : String(count); }
        b.classList.add('has-unread');
      } else {
        if (dot)   dot.hidden = true;
        if (badge) { badge.hidden = true; badge.textContent = '0'; }
        b.classList.remove('has-unread');
      }
    });
  }

  function buildItemEl(it) {
    var a = document.createElement('a');
    a.className = 'atl-notif-item no-pjax ' + bellSeverityClass(it.sev) + (it.unread ? ' is-unread' : '');
    a.setAttribute('data-no-pjax', '1');
    a.setAttribute('data-ticket-id', String(it.ticket_id || it.number));
    a.setAttribute('data-version-key', String(it.ticket_id || it.number) + ':' + (it.unread ? 'u' : 'r'));
    a.href = it.url;
    a.innerHTML = ''
      + '<span class="atl-notif-ico">' + bellSeverityIcon(it.sev) + '</span>'
      + '<span class="atl-notif-meta">'
      +   '<span class="atl-notif-row1"><strong>#' + escapeHTML(it.number) + '</strong> '
      +     '<span class="atl-notif-text">' + escapeHTML(it.title || '') + '</span></span>'
      +   '<span class="atl-notif-row2">' + escapeHTML(it.meta || '') + ' · ' + escapeHTML(it.time || '') + '</span>'
      + '</span>';
    return a;
  }

  // Diff-merge: keeps existing DOM nodes when unchanged, replaces only
  // the rows whose content/unread state changed, prepends new ones with
  // an entrance animation, removes ones that fell off the feed.
  function renderBellItems(panel, data) {
    var body = panel.querySelector('[data-atl-notif-body]');
    var sub  = panel.querySelector('[data-atl-notif-sub]');
    if (!body) return;
    var items = (data && data.items) || [];

    if (sub) {
      var c = (data && typeof data.count === 'number') ? data.count : 0;
      sub.textContent = c
        ? (c + ' unread')
        : 'You’re all caught up';
    }

    // Empty state
    if (!items.length) {
      body.innerHTML = '<div class="atl-notif-empty">No recent ticket activity</div>';
      return;
    }
    if (body.querySelector('.atl-notif-empty')) body.innerHTML = '';

    // Index existing nodes by ticket id
    var existing = {};
    Array.prototype.forEach.call(body.querySelectorAll('.atl-notif-item'), function (el) {
      existing[el.getAttribute('data-ticket-id')] = el;
    });

    var prevScroll = body.scrollTop;
    var frag = document.createDocumentFragment();
    var seenNow = {};

    items.forEach(function (it) {
      var tid = String(it.ticket_id || it.number);
      seenNow[tid] = true;
      var versionKey = tid + ':' + (it.unread ? 'u' : 'r');
      var prev = existing[tid];

      if (prev && prev.getAttribute('data-version-key') === versionKey) {
        // Refresh the relative-time text only (cheap, no flicker)
        var t2 = prev.querySelector('.atl-notif-row2');
        if (t2) t2.textContent = (it.meta || '') + ' · ' + (it.time || '');
        frag.appendChild(prev);
        return;
      }

      var node = buildItemEl(it);
      // Brand-new tickets (never seen this session) get a brief highlight
      if (!BELL_SEEN[tid] && it.unread) node.classList.add('is-new');
      BELL_SEEN[tid] = true;
      frag.appendChild(node);
      // Strip the entrance class shortly after so subsequent polls
      // don't keep flashing it.
      if (node.classList.contains('is-new')) {
        setTimeout(function () { node.classList.remove('is-new'); }, 2400);
      }
    });

    // Remove rows the server no longer returns
    Object.keys(existing).forEach(function (tid) {
      if (!seenNow[tid]) {
        var el = existing[tid];
        if (el && el.parentNode === body) el.parentNode.removeChild(el);
      }
    });

    // Single DOM swap (no flicker)
    body.innerHTML = '';
    body.appendChild(frag);
    // Restore scroll so polling never jumps the user
    body.scrollTop = prevScroll;
  }

  function fetchBellFeed(panel) {
    return fetch(notifPath(), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
      .then(function (data) {
        BELL_BACKOFF = 0;
        if (!data) return;
        BELL_LAST_VER = data.version || null;
        if (panel) renderBellItems(panel, data);
        updateBellBadge((data && data.count) || 0);
        return data;
      })
      .catch(function () { BELL_BACKOFF = Math.min(BELL_BACKOFF + 1, 4); });
  }

  function fetchBellCount() {
    return fetch(notifPath() + '?count=1', { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
      .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
      .then(function (data) {
        BELL_BACKOFF = 0;
        if (!data) return;
        // Version hash unchanged AND panel is closed → early-out (no DOM work)
        if (BELL_LAST_VER && data.version === BELL_LAST_VER) {
          updateBellBadge((data && data.count) || 0);
          return;
        }
        // Version moved — refresh the open panel (if any) so badge & dropdown match
        BELL_LAST_VER = data.version || BELL_LAST_VER;
        var openPanel = document.querySelector('.atl-notif-panel.is-open');
        if (openPanel) {
          fetchBellFeed(openPanel);
        } else {
          updateBellBadge((data && data.count) || 0);
        }
      })
      .catch(function () { BELL_BACKOFF = Math.min(BELL_BACKOFF + 1, 4); });
  }

  function wireBell() {
    var bells = document.querySelectorAll('[data-atl-bell]');
    if (!bells.length) return;

    bells.forEach(function (btn) {
      if (btn._atlWired) return;
      btn._atlWired = true;

      btn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        var panel = ensureBellPanel(btn);
        var open = panel.classList.toggle('is-open');
        if (open) {
          positionBellPanel(btn, panel);
          fetchBellFeed(panel);
        }
      });
    });

    // Click outside / Esc to close
    if (!document._atlBellGlobal) {
      document._atlBellGlobal = true;
      document.addEventListener('click', function (e) {
        document.querySelectorAll('.atl-notif-panel.is-open').forEach(function (p) {
          if (!p.contains(e.target)) p.classList.remove('is-open');
        });
      });
      document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
          document.querySelectorAll('.atl-notif-panel.is-open').forEach(function (p) { p.classList.remove('is-open'); });
        }
      });
      window.addEventListener('resize', function () {
        document.querySelectorAll('[data-atl-bell]').forEach(function (b) {
          var p = b._atlPanel;
          if (p && p.classList.contains('is-open')) positionBellPanel(b, p);
        });
      });
    }
  }

  function scheduleNextPoll() {
    if (BELL_TIMER) clearTimeout(BELL_TIMER);
    var delay = BELL_POLL_MS * Math.pow(2, BELL_BACKOFF); // 8s, 16s, 32s, 64s, 128s
    BELL_TIMER = setTimeout(tickBellPoll, delay);
  }

  function tickBellPoll() {
    if (document.hidden) { scheduleNextPoll(); return; }
    var openPanel = document.querySelector('.atl-notif-panel.is-open');
    var p = openPanel ? fetchBellFeed(openPanel) : fetchBellCount();
    Promise.resolve(p).then(scheduleNextPoll, scheduleNextPoll);
  }

  function startBellPolling() {
    if (window._atlBellPoll) return;
    window._atlBellPoll = true;
    // Prime immediately so the badge isn't blank for 8s on page load.
    fetchBellCount().then(scheduleNextPoll, scheduleNextPoll);
    document.addEventListener('visibilitychange', function () {
      if (!document.hidden) {
        // Tab refocused → poll right away, then resume normal cadence
        if (BELL_TIMER) clearTimeout(BELL_TIMER);
        BELL_BACKOFF = 0;
        tickBellPoll();
      }
    });
  }

  // ============================================================
  // MOBILE SIDEBAR TOGGLE — opens off-canvas drawer on phones/tablets
  // ============================================================
  function wireMobileNav() {
    var btn = document.querySelector('[data-atl-mobile-toggle]');
    if (!btn || btn._atlWired) return;
    btn._atlWired = true;

    function close() { document.body.classList.remove('atl-side-open'); }

    btn.addEventListener('click', function (e) {
      e.preventDefault();
      e.stopPropagation();
      document.body.classList.toggle('atl-side-open');
    });

    // Close when tapping outside the sidebar
    document.addEventListener('click', function (e) {
      if (!document.body.classList.contains('atl-side-open')) return;
      var aside = document.getElementById('atl-sidebar');
      if (!aside) return;
      if (aside.contains(e.target) || btn.contains(e.target)) return;
      close();
    });

    // Close when a sidebar link is clicked (so route change feels right)
    var aside = document.getElementById('atl-sidebar');
    if (aside) {
      aside.addEventListener('click', function (e) {
        var a = e.target && e.target.closest && e.target.closest('a.atl-nav-item');
        if (a) close();
      });
    }

    // Close on Esc
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') close();
    });
  }

  function init() {
    applyTheme();
    buildShell();
    wireTableShiftRange();
    wireSmoothAnchors();
    wireBell();
    wireMobileNav();
    startBellPolling();
    if (/[?&]atl-debug=1\b/.test(location.search)) console.log('[atl] theme', document.body.getAttribute('data-osp-theme') || 'light', '· shell:', document.body.classList.contains('atl-shell'));
  }

  // Apply theme ASAP (before paint) and wire up after DOM is parsed.
  applyTheme();
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  // Re-run on PJAX so the sidebar/topbar persist across queue swaps
  document.addEventListener('pjax:end', function () {
    // Sidebar/topbar are outside #pjax-container so they survive automatically;
    // we only need to refresh active state and re-resolve breadcrumb.
    var aside = document.getElementById('atl-sidebar');
    if (aside) {
      // Only the navigable entries (skip section dividers)
      var navItems = NAV.filter(function (n) { return n && !n.section; });
      Array.prototype.forEach.call(aside.querySelectorAll('.atl-nav-item'), function (a, i) {
        var item = navItems[i];
        a.classList.toggle('is-active', !!(item && isActive(item)));
      });
    }
    var topbar = document.getElementById('atl-topbar');
    var firstH2 = document.querySelector('#content h2');
    if (topbar && firstH2) {
      var current = topbar.querySelector('.atl-current');
      if (current) current.textContent = firstH2.textContent.trim();
    }
    // Re-wire any new bells/toggles the PJAX response brought in
    wireBell();
    wireMobileNav();
  });
})();
