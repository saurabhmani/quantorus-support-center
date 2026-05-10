<?php
/*********************************************************************
    reports.php

    Atlas-shipped Reports landing page.
    Reuses osTicket staff session/header/footer; no schema or core
    changes. The premium Atlas shell wraps automatically via the global
    atlas-theme.* assets registered in include/staff/header.inc.php.

    For richer analytics, the dashboard at /scp/ already pulls live
    KPIs/trends/charts from MySQL. This page is a focused "Reports"
    landing surface so the Reports sidebar item maps to a real route.
**********************************************************************/
require('staff.inc.php');
require_once(INCLUDE_DIR.'class.report.php');

$nav->setTabActive('dashboard');
require(STAFFINC_DIR.'header.inc.php');

// -----------------------------------------------------------------
// Tiny live tiles (real MySQL — same helpers used by the dashboard)
// -----------------------------------------------------------------
$P = TABLE_PREFIX;
function _atl_one($sql) { $r=@db_query($sql, false); return ($r && ($x=db_fetch_array($r))) ? $x : []; }

$row = _atl_one("
  SELECT COUNT(*) total,
         SUM(CASE WHEN ts.state='open'    THEN 1 ELSE 0 END) open_cnt,
         SUM(CASE WHEN ts.state='closed'  THEN 1 ELSE 0 END) closed_cnt,
         SUM(CASE WHEN ts.state='pending' THEN 1 ELSE 0 END) pending_cnt
  FROM `{$P}ticket` t
  JOIN `{$P}ticket_status` ts ON ts.id = t.status_id");

$res = _atl_one("
  SELECT AVG(TIMESTAMPDIFF(MINUTE, created, closed)) m
  FROM `{$P}ticket`
  WHERE closed IS NOT NULL AND closed >= DATE_SUB(NOW(), INTERVAL 30 DAY)");

$resMin = isset($res['m']) ? (float)$res['m'] : 0.0;
$resTxt = $resMin > 0
    ? ($resMin < 60 ? round($resMin).'m' : round($resMin/60, 1).'h')
    : '—';
?>

<h2><?php echo __('Reports'); ?></h2>
<p class="atl-muted" style="margin:0 0 18px;color:#64748b;font-size:13px;">
  Live operational metrics across the support floor. For deep analytics, open the
  <a class="no-pjax" href="<?php echo ROOT_PATH; ?>scp/dashboard-pro.php" style="color:#1d4ed8;font-weight:600;">Dashboard</a>.
</p>

<div class="atl-grid-4" style="margin-bottom:22px;">
  <div class="atl-card">
    <div class="atl-muted" style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;">Total Tickets</div>
    <div style="font-size:26px;font-weight:700;letter-spacing:-.02em;margin-top:6px;"><?php echo number_format((int)($row['total'] ?? 0)); ?></div>
  </div>
  <div class="atl-card">
    <div class="atl-muted" style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;">Open</div>
    <div style="font-size:26px;font-weight:700;letter-spacing:-.02em;margin-top:6px;color:#1d4ed8;"><?php echo number_format((int)($row['open_cnt'] ?? 0)); ?></div>
  </div>
  <div class="atl-card">
    <div class="atl-muted" style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;">Closed</div>
    <div style="font-size:26px;font-weight:700;letter-spacing:-.02em;margin-top:6px;color:#047857;"><?php echo number_format((int)($row['closed_cnt'] ?? 0)); ?></div>
  </div>
  <div class="atl-card">
    <div class="atl-muted" style="font-size:11px;font-weight:600;letter-spacing:.06em;text-transform:uppercase;">Avg Resolution (30d)</div>
    <div style="font-size:26px;font-weight:700;letter-spacing:-.02em;margin-top:6px;"><?php echo htmlspecialchars($resTxt); ?></div>
  </div>
</div>

<h3 style="margin:24px 0 10px;"><?php echo __('Operational reports'); ?></h3>
<div class="atl-grid-3">
  <a class="atl-card no-pjax" href="<?php echo ROOT_PATH; ?>scp/dashboard-pro.php" style="text-decoration:none;color:inherit;display:block;">
    <div style="font-weight:700;font-size:14px;">Live Dashboard</div>
    <div class="atl-muted" style="font-size:12px;margin-top:4px;">KPIs · Trends · Department load · SLA gauge</div>
  </a>
  <a class="atl-card no-pjax" href="<?php echo ROOT_PATH; ?>scp/tickets.php" style="text-decoration:none;color:inherit;display:block;">
    <div style="font-weight:700;font-size:14px;">Tickets Queue</div>
    <div class="atl-muted" style="font-size:12px;margin-top:4px;">Filter · sort · export · bulk-action</div>
  </a>
  <a class="atl-card no-pjax" href="<?php echo ROOT_PATH; ?>scp/staff.php" style="text-decoration:none;color:inherit;display:block;">
    <div style="font-weight:700;font-size:14px;">Agent Performance</div>
    <div class="atl-muted" style="font-size:12px;margin-top:4px;">Workload · response · resolution metrics</div>
  </a>
</div>

<?php
include(STAFFINC_DIR.'footer.inc.php');
?>
