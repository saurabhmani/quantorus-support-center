/* ============================================================
   atlas-tickets.js
   Premium UX layer for the osTicket Tickets page.
   - Auto-pills status / priority / SLA cells from existing text
   - Adds a live-count chip next to the queue title
   - Floating bulk-action bar with selection counter
   - Highlights selected rows
   No mutation of osTicket's underlying form action — bulk submit
   still posts via the original `do=` hidden field, so AJAX,
   permissions, and PJAX flow are untouched.
   ============================================================ */
(function () {
  'use strict';

  function $(sel, root) { return (root || document).querySelector(sel); }
  function $$(sel, root) { return Array.prototype.slice.call((root || document).querySelectorAll(sel)); }

  function isTicketsPage() {
    return !!$('form#tickets table.list.queue.tickets');
  }

  // ============================================================
  // NEW-TICKET CREATION PAGE — pure CSS scope + a richer header.
  //
  // The page is osTicket's `ticket-open.inc.php` rendered inside
  //   <form action="tickets.php?a=open" class="save"> … </form>
  // We add `body.atl-new-ticket` so atlas-tickets.css can target
  // every rule unambiguously, then inject a premium page-header
  // card (icon tile + breadcrumb + description) above the form
  // without touching any PHP/markup that osTicket emits.
  // ============================================================
  // ============================================================
  // TRUE HARD RESET: REBUILD TICKET FORM DOM
  // We physically dismantle the legacy osTicket table structure
  // and rebuild a clean SaaS card layout dynamically, ensuring
  // we only extract the active, needed elements and leave all
  // duplicates, spacers, and ghosts entombed in the hidden table.
  // ============================================================
  function rebuildTicketForm() {
    var form = document.querySelector('form.save[action="tickets.php?a=open"], form.save[action$="tickets.php?a=open"]');
    if (!form || form.hasAttribute('data-atl-rebuilt')) return;
    document.body.classList.add('atl-new-ticket');

    // Wait for osTicket's Redactor and other JS to finish mounting.
    // Since the legacy table is hidden by CSS, the user sees nothing
    // until we assemble the clean cards.
    setTimeout(function() {
      if (form.hasAttribute('data-atl-rebuilt')) return;
      form.setAttribute('data-atl-rebuilt', 'true');

      var rootScp = (function () {
        var p = location.pathname; var i = p.lastIndexOf('/scp/');
        return i >= 0 ? p.slice(0, i + 5) : '/scp/';
      })();

      // 1. PAGE HEADER
      var legacy = form.querySelector(':scope > div[style*="margin-bottom"]');
      var titleText = 'Create new ticket';
      var legacyH2 = legacy && legacy.querySelector('h2');
      if (legacyH2) titleText = (legacyH2.textContent || titleText).trim();

      var header = document.createElement('div');
      header.className = 'atl-page-header';
      header.innerHTML =
        '<div class="atl-page-header-crumbs">' +
          '<a class="no-pjax" data-no-pjax="1" href="' + rootScp + 'tickets.php">Tickets</a>' +
          '<span class="atl-crumb-sep">/</span>' +
          '<span class="atl-crumb-current">New ticket</span>' +
        '</div>' +
        '<div class="atl-page-header-row">' +
          '<div class="atl-page-header-ico" aria-hidden="true">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">' +
              '<path d="M22 12h-6l-2 3h-4l-2-3H2"/>' +
              '<path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>' +
              '<line x1="9" y1="9" x2="15" y2="9"/>' +
            '</svg>' +
          '</div>' +
          '<div class="atl-page-header-text">' +
            '<h1 class="atl-page-header-title">' + escapeHTML(titleText) + '</h1>' +
            '<p class="atl-page-header-desc">Capture a new customer request. Help topic, department, SLA and assignment can all be set before you send.</p>' +
          '</div>' +
          '<a class="atl-page-header-back no-pjax" data-no-pjax="1" href="' + rootScp + 'tickets.php" title="Back to ticket queue">' +
            '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">' +
              '<polyline points="15 18 9 12 15 6"/>' +
            '</svg>' +
            '<span>Back to queue</span>' +
          '</a>' +
        '</div>';

      if (legacy) {
        legacy.replaceWith(header);
      } else {
        form.insertBefore(header, form.firstChild);
      }

      var layout = document.createElement('div');
      layout.className = 'atl-form-layout';

      function buildCard(title, contentRows) {
        if (!contentRows || contentRows.length === 0) return null;
        var card = document.createElement('div');
        card.className = 'atl-card';
        if (title) {
          var hdr = document.createElement('div');
          hdr.className = 'atl-card-header';
          hdr.textContent = title;
          card.appendChild(hdr);
        }
        var body = document.createElement('div');
        body.className = 'atl-card-body';
        
        var grid = document.createElement('div');
        grid.className = 'atl-grid-row';

        contentRows.forEach(function(row) {
          if (!row) return;
          var group = document.createElement('div');
          group.className = 'atl-form-group';
          
          var labelCell = row.querySelector('td:first-child') || row.querySelector('th');
          var inputCell = row.querySelector('td:last-child');
          
          if (labelCell && inputCell && labelCell !== inputCell) {
            var label = document.createElement('div');
            label.className = 'atl-form-label';
            label.innerHTML = labelCell.innerHTML;
            group.appendChild(label);
            
            var wrapper = document.createElement('div');
            wrapper.className = 'atl-form-control-wrapper';
            while (inputCell.firstChild) {
              wrapper.appendChild(inputCell.firstChild);
            }
            group.appendChild(wrapper);
            grid.appendChild(group);
          } else if (labelCell && !inputCell) {
             // Sub-header row
             var subHdr = document.createElement('div');
             subHdr.className = 'atl-card-header';
             subHdr.innerHTML = labelCell.innerHTML;
             body.appendChild(subHdr);
          } else {
             while (row.firstChild) group.appendChild(row.firstChild);
             grid.appendChild(group);
          }
        });
        
        body.appendChild(grid);
        card.appendChild(body);
        return card;
      }

      // 2. TICKET INFORMATION CARD
      var coreRows = [];
      var coreTbody = form.querySelector('tbody#dynamic-form') || form.querySelector('tbody');
      if (coreTbody) {
        Array.from(coreTbody.querySelectorAll(':scope > tr')).forEach(function(tr) {
           var txt = tr.textContent || '';
           if (!txt.includes('Response') && !txt.includes('Internal Note')) {
               coreRows.push(tr);
           }
        });
      }
      var coreCard = buildCard('Ticket Information', coreRows);
      if (coreCard) layout.appendChild(coreCard);

      // Helper to cleanly extract a single Redactor editor
      function extractEditor(taName, cardTitle) {
        var ta = form.querySelector('textarea[name="' + taName + '"]');
        if (!ta) return null;
        
        var card = document.createElement('div');
        card.className = 'atl-card atl-card-editor';
        var hdr = document.createElement('div');
        hdr.className = 'atl-card-header';
        hdr.textContent = cardTitle;
        card.appendChild(hdr);
        var body = document.createElement('div');
        body.className = 'atl-card-body';

        // Wait, did redactor wrap it?
        var redactorBox = null;
        var boxes = Array.from(form.querySelectorAll('.redactor-box'));
        boxes.forEach(function(box) {
           if (box.contains(ta)) redactorBox = box;
        });
        if (!redactorBox && ta.previousElementSibling && ta.previousElementSibling.classList.contains('redactor-box')) {
           redactorBox = ta.previousElementSibling;
        }

        if (redactorBox) {
           // DESTROY extra toolbars inside this box
           var toolbars = Array.from(redactorBox.querySelectorAll('.redactor-toolbar'));
           if (toolbars.length > 1) {
             toolbars.slice(1).forEach(function(tb) { tb.remove(); });
           }
           body.appendChild(redactorBox);
           if (!redactorBox.contains(ta)) body.appendChild(ta);
        } else {
           body.appendChild(ta);
        }

        // Check for canned responses or attachments related to this textarea
        var canned = form.querySelector('select[name="cannedResp"]');
        if (taName === 'response' && canned) {
           var crWrap = canned.closest('div') || canned.parentNode;
           body.insertBefore(crWrap, body.firstChild);
        }
        if (taName === 'response') {
           var attach = form.querySelector('.attachments');
           if (attach) body.appendChild(attach);
        }

        card.appendChild(body);
        return card;
      }

      // 3. RESPONSE CARD
      var respCard = extractEditor('response', 'Response');
      if (respCard) layout.appendChild(respCard);

      // 4. INTERNAL NOTE CARD
      var noteCard = extractEditor('note', 'Internal Note');
      if (noteCard) layout.appendChild(noteCard);

      // 5. STATUS & SIGNATURE CARD
      var statusSelect = form.querySelector('select[name="statusId"]');
      var sigRadios = form.querySelectorAll('input[name="signature"]');
      var metaRows = [];
      if (statusSelect) {
         var sTr = statusSelect.closest('tr');
         if (sTr) metaRows.push(sTr);
      }
      if (sigRadios.length > 0) {
         var sigTr = sigRadios[0].closest('tr');
         if (sigTr && !metaRows.includes(sigTr)) metaRows.push(sigTr);
      }
      var metaCard = buildCard('Status & Signature', metaRows);
      if (metaCard) layout.appendChild(metaCard);

      // 6. ACTION BAR
      var cancelBtn = form.querySelector('input[name="cancel"], input[type="button"][value="Cancel"]');
      var resetBtn = form.querySelector('input[name="reset"], input[type="reset"]');
      var submitBtn = form.querySelector('input[name="submit"], input[type="submit"]');
      
      var actionBar = document.createElement('div');
      actionBar.className = 'atl-action-bar';
      
      if (cancelBtn) { cancelBtn.className = 'atl-btn atl-btn-secondary'; actionBar.appendChild(cancelBtn); }
      if (resetBtn) { resetBtn.className = 'atl-btn atl-btn-secondary'; actionBar.appendChild(resetBtn); }
      if (submitBtn) { submitBtn.className = 'atl-btn atl-btn-primary'; actionBar.appendChild(submitBtn); }
      
      layout.appendChild(actionBar);

      // Finally, attach the modern layout
      form.appendChild(layout);

    }, 150); // wait 150ms for redactor to settle before extracting
  }

  // ============================================================
  // Neutralise the legacy jb-overflowmenu widget on this page.
  //
  // scp.js calls `$('#customQ_nav').overflowmenu({...})` which
  //   - adds `.jb-overflowmenu-helper-postion` to the inner <ul>,
  //     making it position:absolute inside a 35px-tall wrapper
  //     (`.jb-overflowmenu` from scp.css) → tabs clip to a strip
  //   - moves any items it judges to "overflow" into a secondary
  //     <ul> we hide via CSS, so those items become invisible.
  //
  // We:
  //   1) move any moved-out items back into the primary <ul>
  //   2) call `.overflowmenu('destroy')` to stop it watching resize
  //   3) keep a MutationObserver alive in case the plugin fires
  //      again after PJAX or CSS class race conditions.
  // ============================================================
  function tameOverflowMenu() {
    var nav = document.getElementById('customQ_nav');
    if (!nav) return;

    function restoreItems() {
      var primary = nav.querySelector('#sub_nav, ul.jb-overflowmenu-menu-primary');
      if (!primary) return;
      // Pull every <li> out of the secondary menu back into primary.
      Array.prototype.forEach.call(
        nav.querySelectorAll('.jb-overflowmenu-menu-secondary > li'),
        function (li) { primary.appendChild(li); }
      );
    }

    restoreItems();

    // Destroy the jQuery UI widget instance if jQuery + plugin loaded.
    if (window.jQuery) {
      try {
        var $nav = window.jQuery(nav);
        // Newer jQuery UI uses `data('jb-overflowmenu')`, older versions vary
        if ($nav.overflowmenu && (
              $nav.data('jb-overflowmenu') ||
              $nav.data('overflowmenu') ||
              $nav.data('jbOverflowmenu')
            )) {
          $nav.overflowmenu('destroy');
        }
        // Strip ONLY the helper-postion class (the absolute-positioning
        // trap). KEEP `.jb-overflowmenu-menu-primary` — the legacy
        // selector ".jb-overflowmenu-menu-primary div.customQ-dropdown"
        // depends on it for popup positioning. Removing it would make
        // the sub-queue dropdowns render inline instead of as popups.
        $nav.find('.jb-overflowmenu-helper-postion')
            .removeClass('jb-overflowmenu-helper-postion');
      } catch (e) { /* plugin may not be loaded — that's fine */ }
    }

    // Belt-and-braces: if anything (PJAX swap, late script) re-creates the
    // secondary menu and moves items into it, immediately move them back.
    if (!nav._atlObs && window.MutationObserver) {
      nav._atlObs = new MutationObserver(function () {
        restoreItems();
      });
      nav._atlObs.observe(nav, { childList: true, subtree: true });
    }
  }

  // Run early AND late — scp.js initializes the widget on
  // jQuery's $(document).ready, so we run after that has had
  // a chance to fire. Multiple calls are safe and idempotent.
  function tameOverflowMenuRepeatedly() {
    tameOverflowMenu();
    setTimeout(tameOverflowMenu, 100);
    setTimeout(tameOverflowMenu, 400);
    setTimeout(tameOverflowMenu, 1000);
  }

  // ============================================================
  // Click-to-toggle for the queue sub-nav dropdowns.
  // CSS shows the popup on :hover for mouse users; on touch /
  // keyboard, this handler toggles an `.is-open` class on the
  // parent <li>. Click outside (or on another tab) closes it.
  // ============================================================
  function wireSubNavDropdowns() {
    var nav = document.getElementById('customQ_nav');
    if (!nav || nav._atlDdWired) return;
    nav._atlDdWired = true;

    function closeAll(except) {
      Array.prototype.forEach.call(
        nav.querySelectorAll('li.is-open'),
        function (li) { if (li !== except) li.classList.remove('is-open'); }
      );
    }

    nav.addEventListener('click', function (e) {
      // 1) Caret tap → toggle the dropdown. Don't navigate.
      var caret = e.target.closest && e.target.closest('i.icon-sort-down');
      if (caret) {
        var li = caret.closest('li.item, li.top-queue');
        if (li) {
          var dd = li.querySelector(':scope > .customQ-dropdown');
          if (dd) {
            e.preventDefault();
            e.stopPropagation();
            var willOpen = !li.classList.contains('is-open');
            closeAll(willOpen ? li : null);
            li.classList.toggle('is-open', willOpen);
            return;
          }
        }
      }
      // 2) Click on ANY anchor inside an open dropdown → let the click
      //    proceed (no preventDefault), but close the dropdown right
      //    after the synchronous handlers run. Two upsides:
      //      • the user gets immediate visual feedback
      //      • the legacy <a onclick="…$.dialog(…)"> and href-less
      //        data-dialog links still fire normally
      var dropdownLink = e.target.closest && e.target.closest('.customQ-dropdown a');
      if (dropdownLink) {
        // Defer to next tick so the legacy onclick / data-dialog
        // handlers run first against the original DOM.
        setTimeout(function () { closeAll(null); }, 0);
      }
    });

    // Click outside closes
    document.addEventListener('click', function (e) {
      if (nav.contains(e.target)) return;
      closeAll(null);
    });

    // Esc closes
    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') closeAll(null);
    });
  }

  // ---------- 1. Live count chip + status pill in sticky bar ----------
  function injectCountChip() {
    var stickyTitle = $('.sticky.bar.opaque .pull-left h2');
    if (!stickyTitle) return;
    // Always inject a Live status pill — operational signal even on empty queues
    if (!stickyTitle.querySelector('.atl-live-pill')) {
      var live = document.createElement('span');
      live.className = 'atl-live-pill';
      live.innerHTML = '<i></i><span>Live</span>';
      live.title = 'Queue is auto-refreshing in real time';
      stickyTitle.appendChild(live);
    }
    if (stickyTitle.querySelector('.atl-count')) return;
    var rows = $$('form#tickets table.list.queue.tickets tbody tr').filter(function (r) {
      return !r.classList.contains('no-rows') && !!r.querySelector('input[type="checkbox"]');
    });
    if (!rows.length) return;
    var pageNav = $('.pageNav, #pageNav, .pgnv-info');
    var totalText = pageNav && pageNav.textContent.match(/of\s+(\d[\d,]*)/i);
    var label = totalText ? totalText[1] : rows.length.toString();
    var chip = document.createElement('span');
    chip.className = 'atl-count';
    chip.textContent = label + (label === '1' ? ' ticket' : ' tickets');
    stickyTitle.appendChild(chip);
  }

  // ---------- 2. Auto-pill status / priority / SLA cells ----------
  function pillify() {
    var statusMap = {
      open: 'atl-st-open', pending: 'atl-st-pending',
      closed: 'atl-st-closed', resolved: 'atl-st-closed',
      overdue: 'atl-st-overdue', archived: 'atl-st-archived',
      onhold: 'atl-st-onhold', 'on hold': 'atl-st-onhold'
    };
    var priMap = {
      emergency: 'atl-pri-emergency', urgent: 'atl-pri-urgent',
      high: 'atl-pri-high', normal: 'atl-pri-normal', low: 'atl-pri-low'
    };

    $$('form#tickets table.list.queue.tickets tbody tr').forEach(function (tr) {
      $$('td', tr).forEach(function (td) {
        var raw = (td.textContent || '').trim();
        if (!raw || raw.length > 32) return;
        // skip cells with anchors/forms — they hold ticket numbers/subjects
        if (td.querySelector('a, form, input')) return;
        if (td.querySelector('.atl-pill, .atl-sla, .atl-asg')) return;

        var key = raw.toLowerCase();

        // SLA hint: "2h overdue", "12m left", "Breached"
        if (/(overdue|breached|past due)/i.test(raw)) {
          td.innerHTML = '<span class="atl-sla atl-sla-breach">' + escapeHTML(raw) + '</span>';
          return;
        }
        if (/^[<-]?\s*\d+[hms]\b.*\b(left|remain|due)/i.test(raw)) {
          td.innerHTML = '<span class="atl-sla atl-sla-risk">' + escapeHTML(raw) + '</span>';
          return;
        }

        // Status
        if (statusMap[key]) {
          td.innerHTML = '<span class="atl-pill ' + statusMap[key] + '">' + escapeHTML(raw) + '</span>';
          return;
        }
        // Priority
        if (priMap[key]) {
          td.innerHTML = '<span class="atl-pill ' + priMap[key] + '">' + escapeHTML(raw) + '</span>';
          return;
        }
      });
    });
  }

  // ---------- 3. Assignee avatar synthesis ----------
  function avatarize() {
    // osTicket emits assignee names in plain text inside <td>. We detect cells
    // that look like "Firstname Lastname" or "Team / Person" and prepend an avatar.
    var assigneeRe = /^[A-Z][\p{L}'\-]{1,}\s+[A-Z][\p{L}'\-]{1,}$/u;
    $$('form#tickets table.list.queue.tickets tbody tr').forEach(function (tr) {
      $$('td', tr).forEach(function (td) {
        if (td.querySelector('a, form, input, .atl-pill, .atl-sla, .atl-asg')) return;
        var raw = (td.textContent || '').trim();
        if (!raw) return;
        if (raw.length > 50) return;
        if (assigneeRe.test(raw)) {
          var parts = raw.split(/\s+/);
          var init = (parts[0][0] + (parts[1] ? parts[1][0] : '')).toUpperCase();
          td.innerHTML =
            '<span class="atl-asg">' +
            '  <span class="atl-asg-av" style="background:' + colorFor(raw) + '">' + escapeHTML(init) + '</span>' +
            '  <span class="atl-asg-name">' + escapeHTML(raw) + '</span>' +
            '</span>';
        } else if (/^(unassigned|—|none)$/i.test(raw)) {
          td.innerHTML =
            '<span class="atl-asg atl-asg-unassigned">' +
            '  <span class="atl-asg-av">?</span>' +
            '  <span class="atl-asg-name">Unassigned</span>' +
            '</span>';
        }
      });
    });
  }

  // ---------- 3.5. Empty-state DOM injection ----------
  // osTicket renders "<i>Query returned 0 results.</i>" inside the
  // tfoot of an empty queue. Replace that with a clean structured
  // card so we don't have to play CSS pseudo-element games (which
  // were leaking shadows/gradients into other rows previously).
  function decorateEmptyTfoot() {
    var table = $('form#tickets table.list.queue.tickets');
    if (!table) return;
    var tfootCell = table.querySelector('tfoot td');
    if (!tfootCell) return;
    // Already decorated? Bail.
    if (tfootCell.classList.contains('atl-empty-host')) return;
    // Empty queue is signalled by a single direct <i> child holding
    // the "Query returned 0 results." text (osTicket emits it that
    // way — see queue-tickets.tmpl.php). If there are <a> elements
    // for Select All / None / Toggle then it's a populated queue
    // and we must leave the tfoot strictly alone.
    var directI = tfootCell.querySelector(':scope > i');
    if (!directI) return;
    if (tfootCell.querySelector('a, input, form')) return;

    // Build the new DOM
    var ico = ''
      + '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" '
      + 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'
      + '<path d="M22 12h-6l-2 3h-4l-2-3H2"/>'
      + '<path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>'
      + '</svg>';
    var title = (directI.textContent || '').trim();
    // Prefer our copy over the literal "Query returned 0 results."
    if (!title || /query\s+returned\s+0\s+results/i.test(title)) {
      title = 'No tickets found';
    }
    tfootCell.classList.add('atl-empty-host');
    // Mark the whole table so the CSS can hide the now-meaningless
    // <thead> column-header row (no rows = nothing to label).
    table.classList.add('atl-empty-table');

    // Resolve /scp/-relative URLs for the CTAs without hard-coding ROOT_PATH
    var scpRoot = (function () {
      var p = location.pathname; var i = p.lastIndexOf('/scp/');
      return i >= 0 ? p.slice(0, i + 5) : '/scp/';
    })();

    var plus = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>';
    var grid = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/></svg>';

    tfootCell.innerHTML =
      '<div class="atl-empty" role="status">' +
        '<div class="atl-empty-ico">' + ico + '</div>' +
        '<div class="atl-empty-title">' + escapeHTML(title) + '</div>' +
        '<div class="atl-empty-meta">Your queue is currently clear. New customer requests will appear here instantly. Try switching queues from the tabs above or open a new ticket.</div>' +
        '<div class="atl-empty-actions">' +
          '<a class="atl-empty-cta atl-empty-cta-primary no-pjax" data-no-pjax="1" href="' + scpRoot + 'tickets.php?a=open">' + plus + '<span>New ticket</span></a>' +
          '<a class="atl-empty-cta atl-empty-cta-ghost no-pjax" data-no-pjax="1" href="' + scpRoot + 'tickets.php">' + grid + '<span>View all queues</span></a>' +
        '</div>' +
      '</div>';
  }

  // ---------- 4. Selection counter + floating bulk-action bar ----------
  function buildBulkBar() {
    if ($('.atl-bulk-bar')) return;
    var bar = document.createElement('div');
    bar.className = 'atl-bulk-bar';
    bar.innerHTML =
      '<span class="atl-bulk-count"><strong data-count>0</strong> selected</span>' +
      '<span class="atl-bulk-divider"></span>' +
      '<button type="button" data-action="reopen">Reopen</button>' +
      '<button type="button" data-action="close">Close</button>' +
      '<button type="button" data-action="overdue">Mark overdue</button>' +
      '<button type="button" data-action="changedept">Move dept</button>' +
      '<button type="button" data-action="merge">Merge</button>' +
      '<span class="atl-bulk-divider"></span>' +
      '<button type="button" class="is-danger" data-action="delete">Delete</button>' +
      '<button type="button" class="atl-bulk-close" aria-label="Clear selection">✕</button>';
    document.body.appendChild(bar);

    bar.addEventListener('click', function (e) {
      var btn = e.target.closest('button[data-action], .atl-bulk-close');
      if (!btn) return;
      if (btn.classList.contains('atl-bulk-close')) {
        $$('form#tickets input[type="checkbox"]').forEach(function (cb) { cb.checked = false; });
        updateSelection();
        return;
      }
      var action = btn.getAttribute('data-action');
      // Drive osTicket's existing form submission flow:
      //   <input name="a" value="mass_process"> + <input id="action" name="do" value="...">
      var form = $('form#tickets');
      var hidden = $('#action', form);
      if (!form || !hidden) return;
      hidden.value = action;
      // Use osTicket's confirm dialog if present, otherwise plain confirm
      var label = btn.textContent.trim();
      if (!window.confirm('Apply "' + label + '" to selected tickets?')) return;
      form.submit();
    });
  }

  function updateSelection() {
    var checked = $$('form#tickets table.list.queue.tickets tbody input[type="checkbox"]:checked');
    var bar = $('.atl-bulk-bar');
    if (!bar) return;
    var counter = bar.querySelector('[data-count]');
    counter.textContent = checked.length;
    bar.classList.toggle('is-open', checked.length > 0);

    // Highlight selected rows
    $$('form#tickets table.list.queue.tickets tbody tr').forEach(function (tr) {
      var cb = tr.querySelector('input[type="checkbox"]');
      tr.classList.toggle('atl-row-selected', !!(cb && cb.checked));
    });
  }

  function wireSelection() {
    var table = $('form#tickets table.list.queue.tickets');
    if (!table) return;

    var lastChecked = null;
    table.addEventListener('click', function (e) {
      var t = e.target;
      if (!(t && t.matches && t.matches('input[type="checkbox"]'))) return;
      // shift-click range select
      if (e.shiftKey && lastChecked && lastChecked !== t) {
        var boxes = $$('tbody input[type="checkbox"]', table);
        var a = boxes.indexOf(lastChecked), b = boxes.indexOf(t);
        if (a > -1 && b > -1) {
          var lo = Math.min(a, b), hi = Math.max(a, b);
          for (var i = lo; i <= hi; i++) boxes[i].checked = t.checked;
        }
      }
      lastChecked = t;
      updateSelection();
    });

    // Header "select all" checkbox
    var headerCb = table.querySelector('thead input[type="checkbox"], thead .checkall');
    if (headerCb) {
      headerCb.addEventListener('click', function () {
        // Wait one tick — osTicket may also have its own handler that flips boxes
        setTimeout(updateSelection, 0);
      });
    }
  }

  // ---------- helpers ----------
  function escapeHTML(s) {
    return String(s).replace(/[&<>"']/g, function (c) {
      return { '&':'&amp;', '<':'&lt;', '>':'&gt;', '"':'&quot;', "'":'&#39;' }[c];
    });
  }
  function colorFor(seed) {
    var palette = ['#1d4ed8','#0ea5e9','#7c3aed','#10b981','#f59e0b','#06b6d4','#ef4444','#8b5cf6'];
    var hash = 0;
    for (var i = 0; i < seed.length; i++) hash = ((hash << 5) - hash + seed.charCodeAt(i)) | 0;
    return palette[Math.abs(hash) % palette.length];
  }

  // ============================================================
  // ⌘K / Ctrl+K → focus the basic-search input
  // ============================================================
  function wireSearchShortcut() {
    if (window._atlSearchKeyWired) return;
    window._atlSearchKeyWired = true;
    document.addEventListener('keydown', function (e) {
      var k = (e.key || '').toLowerCase();
      if ((e.metaKey || e.ctrlKey) && k === 'k') {
        var input = document.querySelector('#basic_search input.basic-search');
        if (input) { e.preventDefault(); input.focus(); input.select(); }
      }
    });
  }

  // ---------- init ----------
  function init() {
    wireSearchShortcut();
    
    // Hard reset the layout for tickets.php?a=open
    var form = document.querySelector('form.save[action="tickets.php?a=open"], form.save[action$="tickets.php?a=open"]');
    if (form) rebuildTicketForm();

    // Sub-nav fix runs anywhere the legacy widget is mounted
    if (document.getElementById('customQ_nav')) {
      tameOverflowMenuRepeatedly();
      wireSubNavDropdowns();
    }
    
    // Explicitly abort if we are on the New Ticket page so we NEVER inject bulk actions
    if (document.body.classList.contains('atl-new-ticket')) return;

    if (!isTicketsPage()) return;
    pillify();
    avatarize();
    decorateEmptyTfoot();
    injectCountChip();
    buildBulkBar();
    wireSelection();
    updateSelection();
  }

  // Re-run after PJAX swap (osTicket uses pjax — pjax:end fires after HTML lands)
  if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', init);
  else init();
  document.addEventListener('pjax:end', init);
})();
