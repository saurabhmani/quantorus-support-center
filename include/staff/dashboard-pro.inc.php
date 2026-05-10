<?php
/*********************************************************************
    dashboard-pro.inc.php

    Premium dashboard markup for osTicket.
    Everything is wrapped in .osp-dashboard so the styling does not leak
    into any other osTicket page.

    HOW THIS FILE IS ORGANIZED:
      1) DUMMY DATA block (replace each variable with the SQL in the
         README / section 7 of the design doc to go live).
      2) Tiny render helpers.
      3) Markup, in the order it appears on screen.
**********************************************************************/

global $thisstaff, $cfg;

// =====================================================================
// 1) LIVE DATA  —  every block below is sourced from real osTicket
//    MySQL tables via db_query() + TABLE_PREFIX.
//    Each query is wrapped in graceful fallbacks so a missing table
//    or empty install never breaks the dashboard render.
// =====================================================================

// ---- helpers --------------------------------------------------------
if (!function_exists('osp_q_one')) {
    function osp_q_one($sql) {
        $r = @db_query($sql, false);
        return ($r && ($row = db_fetch_array($r))) ? $row : [];
    }
}
if (!function_exists('osp_q_all')) {
    function osp_q_all($sql) {
        $out = []; $r = @db_query($sql, false);
        if ($r) while ($row = db_fetch_array($r)) $out[] = $row;
        return $out;
    }
}
if (!function_exists('osp_n')) { function osp_n($v, $d = 0)   { return is_numeric($v) ? (int)$v : $d; } }
if (!function_exists('osp_f')) { function osp_f($v, $d = 0.0) { return is_numeric($v) ? (float)$v : $d; } }
if (!function_exists('osp_fmt_minutes')) {
    function osp_fmt_minutes($m) {
        if ($m === null || $m === '' || $m <= 0) return '—';
        $m = (int)round($m);
        if ($m < 60)    return $m . 'm';
        $h = intdiv($m, 60); $mr = $m % 60;
        if ($h < 24)    return $h . 'h ' . str_pad($mr, 2, '0', STR_PAD_LEFT) . 'm';
        $d = intdiv($h, 24); $hr = $h % 24;
        return $d . 'd ' . $hr . 'h';
    }
}
if (!function_exists('osp_relative')) {
    function osp_relative($dt) {
        if (!$dt) return '—';
        $s = strtotime($dt); if (!$s) return '—';
        $delta = max(0, time() - $s);
        if ($delta < 60)    return $delta . 's ago';
        if ($delta < 3600)  return floor($delta/60)   . 'm ago';
        if ($delta < 86400) return floor($delta/3600) . 'h ago';
        return floor($delta/86400) . 'd ago';
    }
}
if (!function_exists('osp_pct_delta')) {
    function osp_pct_delta($now, $prev) {
        $now = (int)$now; $prev = (int)$prev;
        if ($prev <= 0) return $now > 0 ? 100.0 : 0.0;
        return round(100 * ($now - $prev) / $prev, 1);
    }
}
if (!function_exists('osp_plural')) {
    // English-correct plural with optional formatted number.
    //   osp_plural(1, 'ticket')    => '1 ticket'
    //   osp_plural(3, 'ticket')    => '3 tickets'
    //   osp_plural(2, 'reply', 'replies') => '2 replies'
    function osp_plural($n, $singular, $plural = null) {
        $n = (int)$n;
        $word = ($n === 1) ? $singular : ($plural !== null ? $plural : ($singular . 's'));
        return number_format($n) . ' ' . $word;
    }
}
if (!function_exists('osp_safe_text')) {
    // Defensive output sanitizer — never lets the literal "#-",
    // "null", "undefined", or "0" leak into user-visible copy.
    function osp_safe_text($v, $fallback = '—') {
        if ($v === null || $v === false) return $fallback;
        $s = trim((string)$v);
        if ($s === '' || $s === '#' || $s === '#-' || $s === '-' ||
            strtolower($s) === 'null' || strtolower($s) === 'undefined') return $fallback;
        return $s;
    }
}

$P = TABLE_PREFIX;

// ---- 1.1 KPI tiles (real counts + 7d delta) -------------------------
$row = osp_q_one("
    SELECT COUNT(*) AS total,
           SUM(CASE WHEN ts.state = 'open'    THEN 1 ELSE 0 END) AS open_cnt,
           SUM(CASE WHEN ts.state = 'pending' THEN 1 ELSE 0 END) AS pending_cnt,
           SUM(CASE WHEN ts.state = 'closed'  THEN 1 ELSE 0 END) AS closed_cnt
    FROM `{$P}ticket` t
    JOIN `{$P}ticket_status` ts ON ts.id = t.status_id
");

$over = osp_q_one("
    SELECT COUNT(*) c
    FROM `{$P}ticket` t
    JOIN `{$P}ticket_status` ts ON ts.id = t.status_id
    WHERE ts.state IN ('open','pending')
      AND ((t.duedate IS NOT NULL AND t.duedate < NOW()) OR t.isoverdue = 1)
");

$breach24 = osp_q_one("
    SELECT COUNT(*) c
    FROM `{$P}ticket` t
    JOIN `{$P}ticket_status` ts ON ts.id = t.status_id
    WHERE ts.state IN ('open','pending')
      AND t.duedate IS NOT NULL AND t.duedate < NOW()
      AND t.duedate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
");

// Avg first response time (minutes) — last 30 days
$frt = osp_q_one("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created, fr.first_response)) m
    FROM `{$P}ticket` t
    JOIN `{$P}thread` th       ON th.object_id = t.ticket_id AND th.object_type = 'T'
    JOIN (
        SELECT te.thread_id, MIN(te.created) first_response
        FROM `{$P}thread_entry` te
        WHERE te.type = 'R' AND te.staff_id > 0
        GROUP BY te.thread_id
    ) fr ON fr.thread_id = th.id
    WHERE t.created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

// Avg resolution time (minutes) — last 30 days
$res = osp_q_one("
    SELECT AVG(TIMESTAMPDIFF(MINUTE, t.created, t.closed)) m
    FROM `{$P}ticket` t
    WHERE t.closed IS NOT NULL
      AND t.closed >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");

// 7-day vs prior 7-day deltas (single combined query for 4 metrics)
$d7 = osp_q_one("
    SELECT
      SUM(CASE WHEN created >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) c_now,
      SUM(CASE WHEN created BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) c_prev,
      SUM(CASE WHEN closed IS NOT NULL AND closed >= DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) cl_now,
      SUM(CASE WHEN closed IS NOT NULL AND closed BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 1 ELSE 0 END) cl_prev,
      SUM(CASE WHEN duedate IS NOT NULL AND duedate < NOW() AND duedate >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) br_now,
      SUM(CASE WHEN duedate IS NOT NULL AND duedate >= DATE_SUB(NOW(), INTERVAL 48 HOUR) AND duedate < DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) br_prev
    FROM `{$P}ticket`
");

$total   = osp_n($row['total']);
$openC   = osp_n($row['open_cnt']);
$pendC   = osp_n($row['pending_cnt']);
$closedC = osp_n($row['closed_cnt']);
$overC   = osp_n($over['c']);
$brC     = osp_n($breach24['c']);
$frtMin  = osp_f($frt['m']);
$resMin  = osp_f($res['m']);

$pct = function ($n, $t) { return $t ? min(100, (int)round(100 * $n / $t)) : 0; };
$dCreated = osp_pct_delta($d7['c_now'],  $d7['c_prev']);
$dClosed  = osp_pct_delta($d7['cl_now'], $d7['cl_prev']);
$dBreach  = osp_pct_delta($d7['br_now'], $d7['br_prev']);

$kpis = [
    ['label'=>'Total Tickets',      'value'=>number_format($total),        'delta'=>$dCreated, 'tone'=>$dCreated>=0?'up':'down', 'caption'=>'all-time',          'progress'=>100,                  'icon'=>'inbox'],
    ['label'=>'Open Tickets',       'value'=>number_format($openC),        'delta'=>$dCreated, 'tone'=>'up',                     'caption'=>'currently active',  'progress'=>$pct($openC,$total),  'icon'=>'circle-dot'],
    ['label'=>'Pending Tickets',    'value'=>number_format($pendC),        'delta'=>0.0,       'tone'=>'down',                   'caption'=>'awaiting customer', 'progress'=>$pct($pendC,$total),  'icon'=>'clock'],
    ['label'=>'Closed Tickets',     'value'=>number_format($closedC),      'delta'=>$dClosed,  'tone'=>$dClosed>=0?'up':'down',  'caption'=>'this period',       'progress'=>$pct($closedC,$total),'icon'=>'check'],
    ['label'=>'Overdue',            'value'=>number_format($overC),        'delta'=>0.0,       'tone'=>'bad',                    'caption'=>'past due date',     'progress'=>$pct($overC,$total),  'icon'=>'alert'],
    ['label'=>'SLA Breached',       'value'=>number_format($brC),          'delta'=>$dBreach,  'tone'=>$dBreach<=0?'down':'bad', 'caption'=>'last 24 hours',     'progress'=>$pct($brC,max(1,$total)), 'icon'=>'shield'],
    ['label'=>'Avg First Response', 'value'=>osp_fmt_minutes($frtMin),     'delta'=>0.0,       'tone'=>'down',                   'caption'=>'rolling 30-day',    'progress'=>$frtMin?max(20,100-min(80,(int)($frtMin/2))):0, 'icon'=>'reply'],
    ['label'=>'Avg Resolution Time','value'=>osp_fmt_minutes($resMin),     'delta'=>0.0,       'tone'=>'down',                   'caption'=>'rolling 30-day',    'progress'=>$resMin?max(20,100-min(80,(int)($resMin/60))):0,'icon'=>'timer'],
];

// ---- 1.2 Trend (last 12 weeks: created / resolved / breached) -------
$createdRows  = osp_q_all("SELECT YEARWEEK(created,3) yw, COUNT(*) c FROM `{$P}ticket` WHERE created >= DATE_SUB(NOW(), INTERVAL 12 WEEK) GROUP BY yw");
$resolvedRows = osp_q_all("SELECT YEARWEEK(closed,3)  yw, COUNT(*) c FROM `{$P}ticket` WHERE closed  IS NOT NULL AND closed  >= DATE_SUB(NOW(), INTERVAL 12 WEEK) GROUP BY yw");
$breachedRows = osp_q_all("SELECT YEARWEEK(duedate,3) yw, COUNT(*) c FROM `{$P}ticket`
                           WHERE duedate IS NOT NULL
                             AND duedate >= DATE_SUB(NOW(), INTERVAL 12 WEEK)
                             AND duedate <  COALESCE(closed, NOW())
                           GROUP BY yw");

$buckets = [];
for ($i = 11; $i >= 0; $i--) {
    $ts  = strtotime("-{$i} week");
    $yw  = (int)(date('o', $ts) * 100 + (int)date('W', $ts));
    $buckets[$yw] = ['label'=>'W'.date('W',$ts), 'created'=>0, 'resolved'=>0, 'breached'=>0];
}
foreach ($createdRows  as $r) if (isset($buckets[(int)$r['yw']])) $buckets[(int)$r['yw']]['created']  = (int)$r['c'];
foreach ($resolvedRows as $r) if (isset($buckets[(int)$r['yw']])) $buckets[(int)$r['yw']]['resolved'] = (int)$r['c'];
foreach ($breachedRows as $r) if (isset($buckets[(int)$r['yw']])) $buckets[(int)$r['yw']]['breached'] = (int)$r['c'];

$trend_labels = $trend_created = $trend_resolved = $trend_breached = [];
foreach ($buckets as $b) {
    $trend_labels[]   = $b['label'];
    $trend_created[]  = $b['created'];
    $trend_resolved[] = $b['resolved'];
    $trend_breached[] = $b['breached'];
}

// ---- 1.3 Priority distribution (active tickets) ---------------------
$priority = [];
foreach (osp_q_all("
    SELECT p.priority_desc AS label, p.priority_color AS color, COUNT(*) AS value
    FROM `{$P}ticket` t
    JOIN `{$P}ticket__cdata`  cd ON cd.ticket_id  = t.ticket_id
    JOIN `{$P}ticket_priority` p ON p.priority_id = cd.priority
    JOIN `{$P}ticket_status`  ts ON ts.id         = t.status_id
    WHERE ts.state IN ('open','pending')
    GROUP BY p.priority_id
    ORDER BY p.priority_urgency
") as $r) {
    $priority[] = [
        'label' => $r['label'] ?: 'Unset',
        'value' => (int)$r['value'],
        'color' => $r['color'] ? (strpos($r['color'], '#') === 0 ? $r['color'] : '#'.$r['color']) : '#2563eb',
    ];
}
$priorityEmpty = !$priority;
// Render a 1-segment grey ring as a visual placeholder so the donut still
// draws — the empty-state copy is shown in the side legend.
if ($priorityEmpty) $priority = [['label'=>'No active tickets','value'=>1,'color'=>'#e6eaf2']];

// ---- 1.4 Department load (top 6 by open volume) ---------------------
$departments = [];
$dRows = osp_q_all("
    SELECT d.name, COUNT(*) c
    FROM `{$P}ticket` t
    JOIN `{$P}department`     d  ON d.id  = t.dept_id
    JOIN `{$P}ticket_status`  ts ON ts.id = t.status_id
    WHERE ts.state IN ('open','pending')
    GROUP BY d.id
    ORDER BY c DESC
    LIMIT 6
");
$maxOpen = 0;
foreach ($dRows as $r) $maxOpen = max($maxOpen, (int)$r['c']);
foreach ($dRows as $r) {
    $c = (int)$r['c'];
    $departments[] = ['name'=>$r['name'], 'open'=>$c, 'pct'=>$maxOpen ? (int)round($c/$maxOpen*100) : 0];
}
$departmentsEmpty = !$departments;

// ---- 1.5 Source / channel breakdown (last 30d) ----------------------
$palette = ['#2563eb','#0ea5e9','#06b6d4','#8b5cf6','#10b981','#f59e0b'];
$sources = [];
foreach (osp_q_all("
    SELECT t.source AS label, COUNT(*) AS value
    FROM `{$P}ticket` t
    WHERE t.created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY t.source
    ORDER BY value DESC
") as $i => $r) {
    $sources[] = [
        'label' => $r['label'] ?: 'Other',
        'value' => (int)$r['value'],
        'color' => $palette[$i % count($palette)],
    ];
}
$sourcesEmpty = !$sources;

// ---- 1.7 SLA compliance (30 day window) -----------------------------
// Compliance is measured ONLY against tickets that actually had an SLA
// duedate. The previous query divided by all tickets — so an OSS install
// with NULL duedates always showed ~100%. A ticket counts as compliant
// when:
//   • it was closed on or before its duedate, OR
//   • it is still open and the duedate has not yet passed.
$slaRow = osp_q_one("
    SELECT
      SUM(CASE WHEN t.duedate IS NOT NULL THEN 1 ELSE 0 END) AS sla_total,
      SUM(CASE WHEN t.duedate IS NOT NULL
                AND ((t.closed IS NOT NULL AND t.closed <= t.duedate)
                  OR (t.closed IS NULL AND t.duedate >= NOW()))
               THEN 1 ELSE 0 END) AS sla_ok,
      SUM(CASE WHEN t.duedate IS NOT NULL AND t.duedate < NOW() AND t.closed IS NULL THEN 1 ELSE 0 END) breaches,
      SUM(CASE WHEN t.duedate IS NOT NULL AND t.duedate BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 1 HOUR) THEN 1 ELSE 0 END) at_risk
    FROM `{$P}ticket` t
    WHERE t.created >= DATE_SUB(NOW(), INTERVAL 30 DAY)
");
$slaTotal = osp_n($slaRow['sla_total']);
$slaOk    = osp_n($slaRow['sla_ok']);
$sla = [
    'compliance' => $slaTotal > 0 ? round(100 * $slaOk / $slaTotal, 1) : null,   // null = no SLA configured
    'goal'       => 95.0,
    'breaches'   => osp_n($slaRow['breaches']),
    'at_risk'    => osp_n($slaRow['at_risk']),
    'sample'     => $slaTotal,
];

// Week-over-week SLA compliance delta (real, not "+1.2pt" hardcoded)
$slaPrevRow = osp_q_one("
    SELECT
      SUM(CASE WHEN t.duedate IS NOT NULL THEN 1 ELSE 0 END) AS sla_total,
      SUM(CASE WHEN t.duedate IS NOT NULL
                AND ((t.closed IS NOT NULL AND t.closed <= t.duedate)
                  OR (t.closed IS NULL AND t.duedate >= DATE_SUB(NOW(), INTERVAL 7 DAY)))
               THEN 1 ELSE 0 END) AS sla_ok
    FROM `{$P}ticket` t
    WHERE t.created BETWEEN DATE_SUB(NOW(), INTERVAL 14 DAY) AND DATE_SUB(NOW(), INTERVAL 7 DAY)
");
$slaPrevTotal = osp_n($slaPrevRow['sla_total']);
$slaPrevOk    = osp_n($slaPrevRow['sla_ok']);
$slaPrev      = $slaPrevTotal > 0 ? round(100 * $slaPrevOk / $slaPrevTotal, 1) : null;
$sla['wow']   = ($sla['compliance'] !== null && $slaPrev !== null)
                 ? round($sla['compliance'] - $slaPrev, 1) : null;

// ---- 1.8 Critical alerts (built from real signals) ------------------
$alerts = [];
$alert_countdowns = [];

// (1) Top 2 SLA breaches by oldest duedate
foreach (osp_q_all("
    SELECT t.ticket_id, t.number id, cd.subject, p.priority_desc, t.duedate, d.name dept
    FROM `{$P}ticket` t
    JOIN `{$P}ticket__cdata`  cd ON cd.ticket_id  = t.ticket_id
    LEFT JOIN `{$P}ticket_priority` p ON p.priority_id = cd.priority
    JOIN `{$P}department` d ON d.id = t.dept_id
    WHERE t.duedate IS NOT NULL AND t.duedate < NOW() AND t.closed IS NULL
    ORDER BY t.duedate ASC
    LIMIT 2
") as $r) {
    $alerts[] = [
        'kind'  => 'breach',
        'title' => 'SLA breach — Ticket #' . $r['id'],
        'meta'  => $r['dept'].' · '.($r['priority_desc'] ?: 'Normal').' · '.osp_relative($r['duedate']),
        'actor' => 'SLA Monitor',
        'url'   => ROOT_PATH . 'scp/tickets.php?id=' . (int)$r['ticket_id'],
        'cta'   => 'View',
    ];
    $alert_countdowns[] = strtotime($r['duedate']) - time();   // negative => overdue seconds
}

// (2) Largest overdue cluster by department
$cluster = osp_q_one("
    SELECT d.name, COUNT(*) c, MIN(t.duedate) oldest
    FROM `{$P}ticket` t
    JOIN `{$P}department` d ON d.id = t.dept_id
    WHERE t.duedate IS NOT NULL AND t.duedate < NOW() AND t.closed IS NULL
    GROUP BY d.id ORDER BY c DESC LIMIT 1
");
if ($cluster && (int)$cluster['c'] > 0) {
    $alerts[] = [
        'kind'  => 'overdue',
        'title' => $cluster['c'].' overdue tickets in '.$cluster['name'],
        'meta'  => 'Oldest '.osp_relative($cluster['oldest']),
        'actor' => $cluster['name'],
        'url'   => ROOT_PATH . 'scp/tickets.php?status=overdue',
        'cta'   => 'Triage',
    ];
    $alert_countdowns[] = strtotime($cluster['oldest']) - time();
}

// (3) Unassigned high-urgency tickets
$un = osp_q_one("
    SELECT COUNT(*) c
    FROM `{$P}ticket` t
    JOIN `{$P}ticket__cdata`  cd ON cd.ticket_id  = t.ticket_id
    JOIN `{$P}ticket_priority` p ON p.priority_id = cd.priority
    JOIN `{$P}ticket_status`  ts ON ts.id         = t.status_id
    WHERE t.staff_id = 0 AND t.team_id = 0
      AND ts.state IN ('open','pending')
      AND p.priority_urgency <= 2
");
if ($un && (int)$un['c'] > 0) {
    $alerts[]            = [
        'kind'=>'unassign',
        'title'=>$un['c'].' unassigned high-priority tickets',
        'meta'=>'Awaiting routing',
        'actor'=>'Routing engine',
        'url' => ROOT_PATH . 'scp/tickets.php?status=open',
        'cta' => 'Assign',
    ];
    $alert_countdowns[]  = null;
}

// (4) High-priority tickets created in last 6h with no staff reply
$resp = osp_q_one("
    SELECT COUNT(*) c
    FROM `{$P}ticket` t
    JOIN `{$P}ticket__cdata`  cd ON cd.ticket_id  = t.ticket_id
    JOIN `{$P}ticket_priority` p ON p.priority_id = cd.priority
    JOIN `{$P}ticket_status`  ts ON ts.id         = t.status_id
    JOIN `{$P}thread`         th ON th.object_id  = t.ticket_id AND th.object_type = 'T'
    WHERE ts.state IN ('open','pending')
      AND p.priority_urgency <= 2
      AND t.created >= DATE_SUB(NOW(), INTERVAL 6 HOUR)
      AND NOT EXISTS (
          SELECT 1 FROM `{$P}thread_entry` te
          WHERE te.thread_id = th.id AND te.staff_id > 0 AND te.type = 'R'
      )
");
if ($resp && (int)$resp['c'] > 0) {
    $alerts[]           = [
        'kind'=>'escalate',
        'title'=>$resp['c'].' high-priority tickets awaiting first response',
        'meta'=>'Created in the last 6 hours',
        'actor'=>'Response monitor',
        'url' => ROOT_PATH . 'scp/tickets.php?status=open',
        'cta' => 'Respond',
    ];
    $alert_countdowns[] = null;
}

if (!$alerts) {
    $alerts[]           = [
        'kind'=>'health',
        'title'=>'All clear — no SLA breaches detected',
        'meta'=>'Healthy queues across all departments',
        'actor'=>'Service health',
        'url' => null,
        'cta' => null,
    ];
    $alert_countdowns[] = null;
}

// ---- 1.9 Recent tickets (table) -------------------------------------
$tickets = [];
$priColors = ['#1d4ed8','#0ea5e9','#7c3aed','#10b981','#f59e0b','#06b6d4'];
foreach (osp_q_all("
    SELECT t.number id, cd.subject, t.updated,
           u.name AS requester,
           COALESCE(o.name, '—') AS company,
           d.name AS dept,
           p.priority_desc AS priority,
           CASE WHEN s.staff_id IS NULL THEN 'Unassigned'
                ELSE CONCAT(COALESCE(s.firstname,''),' ',COALESCE(s.lastname,'')) END AS agent_name,
           ts.state AS status,
           CASE
                WHEN t.duedate IS NULL THEN 'none'
                WHEN t.closed IS NOT NULL AND t.closed <= t.duedate THEN 'on_track'
                WHEN t.closed IS NOT NULL AND t.closed > t.duedate THEN 'breach'
                WHEN t.duedate < NOW() THEN 'breach'
                WHEN t.duedate < DATE_ADD(NOW(), INTERVAL 1 HOUR) THEN 'risk'
                ELSE 'on_track'
           END AS sla
    FROM `{$P}ticket` t
    JOIN `{$P}ticket__cdata`  cd ON cd.ticket_id  = t.ticket_id
    LEFT JOIN `{$P}user`           u ON u.id = t.user_id
    LEFT JOIN `{$P}organization`   o ON o.id = u.org_id
    JOIN `{$P}department`     d  ON d.id  = t.dept_id
    LEFT JOIN `{$P}ticket_priority` p ON p.priority_id = cd.priority
    LEFT JOIN `{$P}staff`     s  ON s.staff_id = t.staff_id
    JOIN `{$P}ticket_status`  ts ON ts.id = t.status_id
    ORDER BY t.updated DESC
    LIMIT 8
") as $i => $r) {
    $agentName = trim($r['agent_name']);
    if ($agentName === '' || $agentName === 'Unassigned') {
        $agentDisplay = 'Unassigned';
        $agentColor   = '#cbd5e1';
    } else {
        $agentDisplay = $agentName;
        $agentColor   = $priColors[$i % count($priColors)];
    }
    $tickets[] = [
        'id'        => $r['id'],
        'subject'   => $r['subject'] !== '' ? $r['subject'] : '(No subject)',
        'requester' => $r['requester'] !== '' ? $r['requester'] : 'Guest requester',
        'company'   => ($r['company'] && $r['company'] !== '—') ? $r['company'] : '',
        'dept'      => $r['dept'],
        'priority'  => $r['priority'] ?: 'Normal',
        'agent'     => [$agentDisplay, $agentColor],
        'sla'       => $r['sla'],
        'updated'   => osp_relative($r['updated']),
        'status'    => ucfirst($r['status']),
    ];
}
$ticketsEmpty = !count($tickets);

// ---- 1.10 Top agents (last 30 days) ---------------------------------
$agents       = [];
$agentColors  = ['#1d4ed8','#0ea5e9','#7c3aed','#10b981','#f59e0b','#06b6d4'];
$aRows = osp_q_all("
    SELECT s.staff_id,
           CONCAT(COALESCE(s.firstname,''),' ',COALESCE(s.lastname,'')) AS name,
           COALESCE(d.name,'Agent') AS role,
           SUM(CASE WHEN t.closed IS NOT NULL AND t.closed >= DATE_SUB(NOW(),INTERVAL 30 DAY) THEN 1 ELSE 0 END) AS closed_cnt,
           ROUND(AVG(CASE WHEN t.closed IS NOT NULL AND t.closed >= DATE_SUB(NOW(),INTERVAL 30 DAY)
                          THEN TIMESTAMPDIFF(MINUTE, t.created, t.closed) END)) AS avg_min,
           (SELECT COUNT(*) FROM `{$P}ticket` o
              JOIN `{$P}ticket_status` os ON os.id = o.status_id
              WHERE o.staff_id = s.staff_id AND os.state IN ('open','pending')) AS workload
    FROM `{$P}staff` s
    LEFT JOIN `{$P}ticket`     t ON t.staff_id = s.staff_id
    LEFT JOIN `{$P}department` d ON d.id = s.dept_id
    WHERE s.isactive = 1
    GROUP BY s.staff_id
    HAVING closed_cnt > 0 OR workload > 0
    ORDER BY closed_cnt DESC, workload DESC
    LIMIT 6
");
$maxLoad = 1;
foreach ($aRows as $r) $maxLoad = max($maxLoad, (int)$r['workload']);
foreach ($aRows as $i => $r) {
    $name  = trim($r['name']) ?: 'Agent';
    $parts = preg_split('/\s+/', $name);
    $init  = strtoupper(substr($parts[0],0,1) . (isset($parts[1]) ? substr($parts[1],0,1) : ''));
    $agents[] = [
        'name'     => $name,
        'role'     => $r['role'],
        'closed'   => (int)$r['closed_cnt'],
        'response' => $r['avg_min'] ? osp_fmt_minutes((int)$r['avg_min']) : '—',
        'open'     => (int)$r['workload'],
        'load'     => $maxLoad ? min(100, (int)round((int)$r['workload'] / $maxLoad * 100)) : 0,
        'color'    => $agentColors[$i % count($agentColors)],
        'init'     => $init ?: 'AG',
    ];
}
// Empty state is handled in the markup so we render a card-shaped panel
// rather than a fake "phantom" agent row.
$agentsEmpty = !count($agents);

// ---- 1.11 Sidebar nav (with REAL counts) ----------------------------
$openTotal = osp_q_one("SELECT COUNT(*) c FROM `{$P}ticket` t JOIN `{$P}ticket_status` ts ON ts.id=t.status_id WHERE ts.state='open'");
$staffCnt  = osp_q_one("SELECT COUNT(*) c FROM `{$P}staff` WHERE isactive = 1");
$deptCnt   = osp_q_one("SELECT COUNT(*) c FROM `{$P}department`");
// 5th element = real /scp/-relative URL.  Empty string == /scp/  (Dashboard root).
$nav_items = [
    ['Dashboard',       'grid',      null,                                          true,  ''],
    ['Tickets',         'inbox',     number_format(osp_n($openTotal['c'])),         false, 'tickets.php'],
    ['Tasks',           'check',     null,                                          false, 'tasks.php'],
    ['Users',           'users',     null,                                          false, 'users.php'],
    ['Agents',          'users',     number_format(osp_n($staffCnt['c'])),          false, 'staff.php'],
    ['Departments',     'building',  number_format(osp_n($deptCnt['c'])),           false, 'departments.php'],
    ['SLA Policies',    'shield',    null,                                          false, 'slas.php'],
    ['Knowledge Base',  'book',      null,                                          false, 'kb.php'],
    ['Analytics',       'pie',       null,                                          false, 'dashboard-pro.php'],
    ['Reports',         'file-text', null,                                          false, 'reports.php'],
    ['System Settings', 'settings',  null,                                          false, 'settings.php'],
];

// ---- 1.12 Team presence (active agents from staff table) ------------
$presence = [];
$onlineRows = osp_q_all("
    SELECT s.staff_id, s.firstname, s.lastname
    FROM `{$P}staff` s
    WHERE s.isactive = 1 AND s.onvacation = 0
    ORDER BY s.lastlogin DESC
    LIMIT 5
");
$presenceColors = ['#1d4ed8','#0ea5e9','#7c3aed','#10b981','#f59e0b'];
foreach ($onlineRows as $i => $r) {
    $init = strtoupper(substr($r['firstname'],0,1) . substr($r['lastname'],0,1));
    $presence[] = ['init'=>$init ?: 'AG', 'color'=>$presenceColors[$i % count($presenceColors)]];
}
$online_count = osp_n($staffCnt['c']);

// ---- 1.13 Activity stream — unified live feed ----------------------
//      Combines structured ticket events (created / assigned / closed /
//      reopened / overdue / transferred / referred) from ost_thread_event
//      with staff replies from ost_thread_entry. Both sources are sorted
//      by timestamp DESC and merged in PHP to a single 10-item feed.
$activity = [];

// Structured events
$evRows = osp_q_all("
    SELECT te.timestamp AS ts,
           e.name       AS event_name,
           t.number     AS tk_num,
           t.ticket_id  AS tk_id,
           te.dept_id,
           d.name       AS dept_name,
           CONCAT(COALESCE(s.firstname,''),' ',COALESCE(s.lastname,'')) AS staff_name,
           te.username  AS fallback_name
      FROM `{$P}thread_event` te
      JOIN `{$P}event`      e  ON e.id  = te.event_id
      JOIN `{$P}thread`     th ON th.id = te.thread_id AND th.object_type='T'
      JOIN `{$P}ticket`      t ON t.ticket_id = th.object_id
      LEFT JOIN `{$P}department` d ON d.id = te.dept_id
      LEFT JOIN `{$P}staff`      s ON s.staff_id = te.staff_id
     WHERE te.annulled = 0
       AND e.name IN ('created','assigned','closed','reopened','overdue','transferred','referred','edited')
     ORDER BY te.timestamp DESC
     LIMIT 14
");

$verbMap = [
    'created'      => ['verb'=>'opened',          'kind'=>'create',   'icon'=>'plus'],
    'assigned'     => ['verb'=>'assigned',        'kind'=>'assign',   'icon'=>'users'],
    'closed'       => ['verb'=>'resolved',        'kind'=>'close',    'icon'=>'check'],
    'reopened'     => ['verb'=>'reopened',        'kind'=>'reopen',   'icon'=>'reply'],
    'overdue'      => ['verb'=>'flagged overdue', 'kind'=>'breach',   'icon'=>'flame'],
    'transferred'  => ['verb'=>'transferred',     'kind'=>'transfer', 'icon'=>'arrow-right'],
    'referred'     => ['verb'=>'referred',        'kind'=>'refer',    'icon'=>'arrow-right'],
    'edited'       => ['verb'=>'updated',         'kind'=>'edit',     'icon'=>'sliders'],
];

foreach ($evRows as $r) {
    $name = $r['event_name'];
    if (!isset($verbMap[$name])) continue;
    $who = trim($r['staff_name']) ?: trim($r['fallback_name']);
    if ($who === '' || strtoupper($who) === 'SYSTEM') $who = 'osTicket';
    $activity[] = [
        'ts_epoch' => strtotime($r['ts']),
        'kind'     => $verbMap[$name]['kind'],
        'icon'     => $verbMap[$name]['icon'],
        'who'      => $who,
        'verb'     => $verbMap[$name]['verb'],
        'tk_num'   => $r['tk_num'],
        'tk_id'    => (int)$r['tk_id'],
        'dept'     => $r['dept_name'],
        'time'     => osp_relative($r['ts']),
    ];
}

// Staff replies (not present in thread_event)
$replyRows = osp_q_all("
    SELECT te.created AS ts, te.type, te.staff_id,
           t.number    AS tk_num,
           t.ticket_id AS tk_id,
           CONCAT(COALESCE(s.firstname,''),' ',COALESCE(s.lastname,'')) AS staff_name
      FROM `{$P}thread_entry` te
      JOIN `{$P}thread`     th ON th.id = te.thread_id AND th.object_type='T'
      JOIN `{$P}ticket`      t ON t.ticket_id = th.object_id
      LEFT JOIN `{$P}staff`  s ON s.staff_id = te.staff_id
     WHERE te.staff_id > 0
       AND te.type IN ('R','N')
     ORDER BY te.created DESC
     LIMIT 14
");
foreach ($replyRows as $r) {
    $activity[] = [
        'ts_epoch' => strtotime($r['ts']),
        'kind'     => $r['type'] === 'N' ? 'note'  : 'reply',
        'icon'     => $r['type'] === 'N' ? 'file-text' : 'reply',
        'who'      => trim($r['staff_name']) ?: 'Agent',
        'verb'     => $r['type'] === 'N' ? 'added an internal note on' : 'replied on',
        'tk_num'   => $r['tk_num'],
        'tk_id'    => (int)$r['tk_id'],
        'dept'     => null,
        'time'     => osp_relative($r['ts']),
    ];
}

// Merge & dedupe (same staff, same ticket, within 90s — keep newest)
usort($activity, function ($a, $b) { return $b['ts_epoch'] - $a['ts_epoch']; });
$seen = []; $pruned = [];
foreach ($activity as $a) {
    $key = $a['who'].'|'.$a['tk_num'].'|'.$a['kind'];
    if (isset($seen[$key]) && abs($seen[$key] - $a['ts_epoch']) < 90) continue;
    $seen[$key] = $a['ts_epoch'];
    $pruned[] = $a;
    if (count($pruned) >= 10) break;
}
$activity = $pruned;

// ---- CSAT — osTicket OSS has no native CSAT data source.
//      We render an explicit empty state in the markup when responses=0,
//      rather than a fake "0%" tile.
$csat = ['score'=>null, 'responses'=>0, 'positive'=>0, 'neutral'=>0, 'negative'=>0, 'delta'=>null];

// ---- Operations Insights — built ONLY from live signals.
//      Copy is grammatically correct, operationally specific, and tagged
//      with severity ("breach", "watch", "ok") so the UI can color-key.
$insights = [];

if ($brC > 0) {
    $insights[] = [
        'icon'  => 'shield-x',
        'title' => osp_plural($brC, 'SLA breach', 'SLA breaches') . ' detected in the last 24 hours.',
        'meta'  => 'Open tickets past their duedate within the rolling 24-hour window.',
        'sev'   => 'breach',
    ];
}
if ($overC > 0 && $overC !== $brC) {
    $insights[] = [
        'icon'  => 'flame',
        'title' => osp_plural($overC, 'overdue ticket') . ' currently open across all queues.',
        'meta'  => 'Past duedate, no resolution yet — escalate or extend SLA.',
        'sev'   => 'breach',
    ];
}
if ($pendC > 0) {
    $insights[] = [
        'icon'  => 'clock',
        'title' => osp_plural($pendC, 'ticket') . ' awaiting customer or agent action.',
        'meta'  => 'Currently in pending state — review for stalled conversations.',
        'sev'   => 'watch',
    ];
}
if ($d7 && $d7['c_now'] !== null && $d7['cl_now'] !== null) {
    $cNow  = (int)$d7['c_now'];
    $clNow = (int)$d7['cl_now'];
    $delta7 = $cNow - $clNow;
    if ($delta7 > 0) {
        $insights[] = [
            'icon'  => 'trend-up',
            'title' => 'Inbound volume exceeded resolution throughput by ' . osp_plural($delta7, 'ticket') . ' in the last 7 days.',
            'meta'  => $cNow . ' created · ' . $clNow . ' resolved — backlog growing.',
            'sev'   => 'watch',
        ];
    } elseif ($delta7 < 0) {
        $insights[] = [
            'icon'  => 'trend-dn',
            'title' => 'Resolution throughput exceeded inbound by ' . osp_plural(abs($delta7), 'ticket') . ' in the last 7 days.',
            'meta'  => $cNow . ' created · ' . $clNow . ' resolved — backlog shrinking.',
            'sev'   => 'ok',
        ];
    } else {
        $insights[] = [
            'icon'  => 'pulse',
            'title' => 'Inbound and resolution remained balanced across the last 7 days.',
            'meta'  => $cNow . ' created · ' . $clNow . ' resolved — steady state.',
            'sev'   => 'ok',
        ];
    }
}
if ($frtMin > 0) {
    $insights[] = [
        'icon'  => 'reply',
        'title' => 'Average first response time is ' . osp_fmt_minutes($frtMin) . '.',
        'meta'  => 'Rolling 30-day average across all departments.',
        'sev'   => $frtMin <= 60 ? 'ok' : ($frtMin <= 240 ? 'watch' : 'breach'),
    ];
}
if ($sla['compliance'] !== null && $sla['wow'] !== null && abs($sla['wow']) >= 1) {
    $up = $sla['wow'] >= 0;
    $insights[] = [
        'icon'  => $up ? 'trend-up' : 'trend-dn',
        'title' => 'SLA compliance ' . ($up ? 'improved' : 'declined') . ' by ' . number_format(abs($sla['wow']), 1) . ' points week-over-week.',
        'meta'  => 'Currently at ' . number_format($sla['compliance'], 1) . '% against a ' . (int)$sla['goal'] . '% goal.',
        'sev'   => $up ? 'ok' : 'watch',
    ];
}

// Calm-state insight only when nothing else surfaces
if (!$insights) {
    $insights[] = [
        'icon'  => 'pulse',
        'title' => 'Queues remain healthy with stable response throughput.',
        'meta'  => 'No SLA breaches, overdue tickets, or backlog pressure detected.',
        'sev'   => 'ok',
    ];
}

// ---- Service Health — last 9 days created counts as the bar heights -
$healthBars = [];
$healthRows = osp_q_all("
    SELECT DATE(created) d, COUNT(*) c
      FROM `{$P}ticket`
     WHERE created >= DATE_SUB(CURDATE(), INTERVAL 8 DAY)
     GROUP BY DATE(created)
");
$byDay = [];
foreach ($healthRows as $r) $byDay[$r['d']] = (int)$r['c'];
$maxDay = $byDay ? max($byDay) : 1;
for ($i = 8; $i >= 0; $i--) {
    $key = date('Y-m-d', strtotime("-{$i} day"));
    $val = isset($byDay[$key]) ? $byDay[$key] : 0;
    $healthBars[] = $maxDay > 0 ? max(8, (int)round($val / $maxDay * 100)) : 8;
}
// Show SLA compliance if SLAs are configured, else show open/total ratio
$healthScore  = $sla['compliance']; // null when no SLA configured
$healthMeta   = $healthScore !== null
                ? 'SLA compliance · 30 days'
                : 'No SLA policies configured';

// ---- Today's Throughput — created / replied / closed in last 24h ---
$todayRow = osp_q_one("
    SELECT
      SUM(CASE WHEN created >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS created24,
      SUM(CASE WHEN closed  IS NOT NULL AND closed >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS closed24,
      SUM(CASE WHEN reopened IS NOT NULL AND reopened >= DATE_SUB(NOW(), INTERVAL 24 HOUR) THEN 1 ELSE 0 END) AS reopened24
    FROM `{$P}ticket`
");
$repliesRow = osp_q_one("
    SELECT COUNT(*) c
      FROM `{$P}thread_entry` te
     WHERE te.created >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
       AND te.staff_id > 0
       AND te.type = 'R'
");
$throughput = [
    'created'  => osp_n($todayRow['created24']),
    'closed'   => osp_n($todayRow['closed24']),
    'replies'  => osp_n($repliesRow['c']),
    'reopened' => osp_n($todayRow['reopened24']),
];

// ---- Status Breakdown — donut data (open/pending/closed) -----------
//      Reuses $openC/$pendC/$closedC already loaded for the KPI tiles.
$statusBreakdown = [
    ['label'=>'Open',    'value'=>$openC,   'color'=>'#2563eb'],
    ['label'=>'Pending', 'value'=>$pendC,   'color'=>'#f59e0b'],
    ['label'=>'Closed',  'value'=>$closedC, 'color'=>'#10b981'],
];
$statusTotal = $openC + $pendC + $closedC;

// ---- Real total count for the recent-tickets table footer ---------
$tableTotalRow = osp_q_one("SELECT COUNT(*) c FROM `{$P}ticket`");
$tableTotal    = osp_n($tableTotalRow['c']);

// ---- Real reply count per ticket (for the 8 in the recent table) --
$replyCountByTicket = [];
if ($tickets) {
    $tnums = array_filter(array_map(function($t){ return preg_replace('/\D+/','',$t['id']); }, $tickets));
    if ($tnums) {
        $tlist = "'" . implode("','", array_map(function($n){ return db_real_escape($n, false); }, $tnums)) . "'";
        $rcRows = osp_q_all("
            SELECT t.number AS num, COUNT(te.id) c
              FROM `{$P}ticket` t
              JOIN `{$P}thread` th       ON th.object_id = t.ticket_id AND th.object_type='T'
              LEFT JOIN `{$P}thread_entry` te ON te.thread_id = th.id AND te.type IN ('M','R')
             WHERE t.number IN ($tlist)
             GROUP BY t.number
        ");
        foreach ($rcRows as $r) $replyCountByTicket[$r['num']] = (int)$r['c'];
    }
}

// ---- Status counts for the mini-tabs above the recent-tickets table -
$breachedNow = osp_n((osp_q_one(
    "SELECT COUNT(*) c FROM `{$P}ticket` t
       JOIN `{$P}ticket_status` ts ON ts.id=t.status_id
      WHERE ts.state IN ('open','pending')
        AND ((t.duedate IS NOT NULL AND t.duedate < NOW()) OR t.isoverdue=1)"
))['c']);
$miniTabCounts = [
    'all'     => $tableTotal,
    'open'    => $openC,
    'pending' => $pendC,
    'breach'  => $breachedNow,
];

// ---- Real "online" presence from staff.lastlogin (last 15 minutes) -
$onlineNowRow = osp_q_one("
    SELECT COUNT(*) c
      FROM `{$P}staff`
     WHERE isactive = 1
       AND lastlogin IS NOT NULL
       AND lastlogin >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
");
$online_now = osp_n($onlineNowRow['c']);
$presenceLive = [];
foreach (osp_q_all("
    SELECT staff_id, firstname, lastname
      FROM `{$P}staff`
     WHERE isactive = 1
       AND lastlogin IS NOT NULL
       AND lastlogin >= DATE_SUB(NOW(), INTERVAL 15 MINUTE)
     ORDER BY lastlogin DESC
     LIMIT 5
") as $i => $r) {
    $init = strtoupper(substr($r['firstname'],0,1) . substr($r['lastname'],0,1));
    $presenceLive[] = ['init'=>$init ?: 'AG', 'color'=>$presenceColors[$i % count($presenceColors)]];
}

// ---- Heatmap — last 7 days × top 6 departments ----------------------
$heatmap_days = ['Mon','Tue','Wed','Thu','Fri','Sat','Sun'];
$heatmap = [];
$topDeptIds = [];
foreach (osp_q_all("
    SELECT d.id, d.name, COUNT(*) c
    FROM `{$P}ticket` t JOIN `{$P}department` d ON d.id = t.dept_id
    WHERE t.created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY d.id ORDER BY c DESC LIMIT 6
") as $r) $topDeptIds[(int)$r['id']] = $r['name'];

if ($topDeptIds) {
    $idsList = implode(',', array_map('intval', array_keys($topDeptIds)));
    $rowsHM = osp_q_all("
        SELECT t.dept_id,
               (DAYOFWEEK(t.created) + 5) % 7 AS dow,    -- 0=Mon..6=Sun
               COUNT(*) c
        FROM `{$P}ticket` t
        WHERE t.created >= DATE_SUB(NOW(), INTERVAL 7 DAY)
          AND t.dept_id IN ({$idsList})
        GROUP BY t.dept_id, dow
    ");
    $maxCell = 1;
    $matrix  = [];
    foreach ($rowsHM as $r) {
        $matrix[(int)$r['dept_id']][(int)$r['dow']] = (int)$r['c'];
        $maxCell = max($maxCell, (int)$r['c']);
    }
    foreach ($topDeptIds as $id => $name) {
        $row = [];
        for ($d = 0; $d < 7; $d++) {
            $v = isset($matrix[$id][$d]) ? $matrix[$id][$d] : 0;
            $row[] = round($v / $maxCell, 2);
        }
        $heatmap[$name] = $row;
    }
}
$heatmapEmpty = !$heatmap;

// ---- Logged-in staff (real osTicket session data) -------------------
$staff_name  = $thisstaff ? $thisstaff->getName()->getFull() : 'Staff Member';
$staff_role  = $thisstaff && $thisstaff->isAdmin() ? 'Administrator' : 'Support Agent';
$staff_init  = $thisstaff ? strtoupper(substr($thisstaff->getName()->getFirst(),0,1).substr($thisstaff->getName()->getLast(),0,1)) : 'ST';

// ---- Workspace card — real osTicket helpdesk identity --------------
$workspaceName = ($cfg && method_exists($cfg, 'getTitle')) ? trim((string)$cfg->getTitle()) : '';
if ($workspaceName === '') $workspaceName = 'osTicket';
$workspaceMark = strtoupper(substr(preg_replace('/[^A-Za-z]/','',$workspaceName), 0, 2)) ?: 'OT';
$workspaceUrl  = ($cfg && method_exists($cfg, 'getUrl')) ? (string)$cfg->getUrl() : '';
$workspaceEnv  = $workspaceUrl ? parse_url($workspaceUrl, PHP_URL_HOST) : '';
if (!$workspaceEnv) $workspaceEnv = 'Helpdesk';

// ---- Greeting subtitle — real local datetime (not hardcoded) -------
$greetingDate = date('l, F j · H:i');

// --------------------------------------------------------------------
// 2) Tiny render helpers
// --------------------------------------------------------------------
function osp_icon($name, $cls = '') {
    // Lucide-style 24px stroke icons, inlined to avoid extra HTTP requests.
    static $svg = [
        'grid'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'inbox'     => '<path d="M22 12h-6l-2 3h-4l-2-3H2"/><path d="M5.45 5.11 2 12v6a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-6l-3.45-6.89A2 2 0 0 0 16.76 4H7.24a2 2 0 0 0-1.79 1.11Z"/>',
        'users'     => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'building'  => '<rect x="4" y="2" width="16" height="20" rx="2"/><path d="M9 22v-4h6v4"/><path d="M8 6h.01M16 6h.01M8 10h.01M16 10h.01M8 14h.01M16 14h.01"/>',
        'shield'    => '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z"/><path d="m9 12 2 2 4-4"/>',
        'book'      => '<path d="M4 19.5v-15A2.5 2.5 0 0 1 6.5 2H20v20H6.5a2.5 2.5 0 0 1 0-5H20"/>',
        'pie'       => '<path d="M21 12A9 9 0 1 1 12 3v9Z"/><path d="M21 12c0-1.66-.4-3.22-1.1-4.6L12 12"/>',
        'zap'       => '<polygon points="13 2 3 14 12 14 11 22 21 10 12 10 13 2"/>',
        'file-text' => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'settings'  => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06A1.65 1.65 0 0 0 4.6 15a1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06A1.65 1.65 0 0 0 9 4.6a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09A1.65 1.65 0 0 0 15 4.6a1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82 1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1Z"/>',
        'circle-dot'=> '<circle cx="12" cy="12" r="9"/><circle cx="12" cy="12" r="3"/>',
        'clock'     => '<circle cx="12" cy="12" r="9"/><polyline points="12 7 12 12 15 14"/>',
        'check'     => '<path d="M5 12l4 4L19 7"/>',
        'alert'     => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0Z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'reply'     => '<polyline points="9 17 4 12 9 7"/><path d="M20 18v-2a4 4 0 0 0-4-4H4"/>',
        'timer'     => '<line x1="10" y1="2" x2="14" y2="2"/><line x1="12" y1="14" x2="15" y2="11"/><circle cx="12" cy="14" r="8"/>',
        'search'    => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'bell'      => '<path d="M6 8a6 6 0 0 1 12 0c0 7 3 9 3 9H3s3-2 3-9"/><path d="M10 21a2 2 0 0 0 4 0"/>',
        'plus'      => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'trend-up'  => '<polyline points="22 7 13.5 15.5 8.5 10.5 2 17"/><polyline points="16 7 22 7 22 13"/>',
        'trend-dn'  => '<polyline points="22 17 13.5 8.5 8.5 13.5 2 7"/><polyline points="16 17 22 17 22 11"/>',
        'sparkle'   => '<path d="M12 3l1.9 5.6L19.5 10l-5.6 1.4L12 17l-1.9-5.6L4.5 10l5.6-1.4z"/><path d="M19 18l.7 2 2 .7-2 .7-.7 2-.7-2-2-.7 2-.7z"/>',
        'arrow-right'=>'<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'flame'     => '<path d="M8.5 14.5A2.5 2.5 0 0 0 11 12c0-1.38-.5-2-1-3-1.072-2.143-.224-4.054 2-6 .5 2.5 2 4.9 4 6.5 2 1.6 3 3.5 3 5.5a7 7 0 1 1-14 0c0-1.153.433-2.294 1-3a2.5 2.5 0 0 0 2.5 2.5z"/>',
        'sliders'   => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'chevron-d' => '<polyline points="6 9 12 15 18 9"/>',
        'message'   => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'shield-x'  => '<path d="M20 13c0 5-3.5 7.5-8 9-4.5-1.5-8-4-8-9V5l8-3 8 3Z"/><line x1="9" y1="10" x2="15" y2="14"/><line x1="15" y1="10" x2="9" y2="14"/>',
        'globe'     => '<circle cx="12" cy="12" r="9"/><line x1="3" y1="12" x2="21" y2="12"/><path d="M12 3a14 14 0 0 1 0 18M12 3a14 14 0 0 0 0 18"/>',
        'pulse'     => '<polyline points="3 12 7 12 10 4 14 20 17 12 21 12"/>',
    ];
    $body = isset($svg[$name]) ? $svg[$name] : '<circle cx="12" cy="12" r="3"/>';
    return '<svg class="osp-icon '.$cls.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.$body.'</svg>';
}

function osp_priority_pill($p) {
    $map = [
        'Urgent' => ['#fef2f2','#b91c1c'],
        'High'   => ['#fff7ed','#c2410c'],
        'Normal' => ['#eff6ff','#1d4ed8'],
        'Low'    => ['#ecfdf5','#047857'],
    ];
    $c = isset($map[$p]) ? $map[$p] : ['#f1f5f9','#475569'];
    return '<span class="osp-pill" style="background:'.$c[0].';color:'.$c[1].'">'.htmlspecialchars($p).'</span>';
}

function osp_status_pill($s) {
    $map = [
        'Open'    => ['#eff6ff','#1d4ed8','#2563eb'],
        'Pending' => ['#fff7ed','#c2410c','#f59e0b'],
        'Closed'  => ['#ecfdf5','#047857','#10b981'],
    ];
    $c = isset($map[$s]) ? $map[$s] : ['#f1f5f9','#475569','#64748b'];
    return '<span class="osp-status"><i style="background:'.$c[2].'"></i><span style="color:'.$c[1].'">'.htmlspecialchars($s).'</span></span>';
}

function osp_sla_chip($k) {
    $map = [
        'on_track' => ['On track','#ecfdf5','#047857'],
        'risk'     => ['At risk', '#fffbeb','#92400e'],
        'breach'   => ['Breached','#fef2f2','#b91c1c'],
        'none'     => ['No SLA',  '#f1f5f9','#64748b'],
    ];
    $c = isset($map[$k]) ? $map[$k] : ['—','#f1f5f9','#64748b'];
    return '<span class="osp-chip" style="background:'.$c[1].';color:'.$c[2].'">'.htmlspecialchars($c[0]).'</span>';
}

function osp_alert_color($kind) {
    $m = [
      'breach'   => '#ef4444',
      'overdue'  => '#f59e0b',
      'unassign' => '#0ea5e9',
      'escalate' => '#8b5cf6',
      'ai'       => '#2563eb',
      'health'   => '#10b981',
    ];
    return isset($m[$kind]) ? $m[$kind] : '#64748b';
}

// Pre-encode data for the JS layer
$jsData = [
    'trend'    => ['labels'=>$trend_labels,'created'=>$trend_created,'resolved'=>$trend_resolved,'breached'=>$trend_breached],
    'priority' => $priority,
    'sources'  => $sources,
    'departments' => $departments,
    'csat'     => $csat,
    'sla'      => $sla,
];
?>
<!-- ================================================================
     3) MARKUP
     ================================================================ -->
<div class="osp-dashboard" data-osp-theme="light" data-osp-bootstrap='<?php echo htmlspecialchars(json_encode($jsData), ENT_QUOTES); ?>'>

  <!-- ============== SIDEBAR ============== -->
  <aside class="osp-sidebar">
    <div class="osp-brand">
      <div class="osp-brand-mark"><?php echo osp_icon('sparkle'); ?></div>
      <div class="osp-brand-text">
        <span class="osp-brand-name">osTicket Pro</span>
        <span class="osp-brand-sub">Support Operations</span>
      </div>
    </div>

    <a class="osp-ws no-pjax" href="<?php echo ROOT_PATH; ?>scp/settings.php" data-no-pjax="1" title="Helpdesk settings">
      <div class="osp-ws-mark"><?php echo htmlspecialchars($workspaceMark); ?></div>
      <div class="osp-ws-text">
        <span class="osp-ws-name"><?php echo htmlspecialchars($workspaceName); ?></span>
        <span class="osp-ws-env"><?php echo htmlspecialchars($workspaceEnv); ?></span>
      </div>
      <span class="osp-ws-chev"><?php echo osp_icon('chevron-d'); ?></span>
    </a>

    <div class="osp-nav-label">Workspace</div>
    <nav class="osp-nav">
      <?php foreach ($nav_items as $i):
            list($lbl,$ic,$count,$active,$url) = array_pad($i,5,false);
            // Build a real, absolute URL — never "#".
            $href = ROOT_PATH . 'scp/' . ($url ?: '');
      ?>
        <a class="osp-nav-item no-pjax<?php echo $active ? ' is-active' : ''; ?>"
           href="<?php echo htmlspecialchars($href); ?>"
           data-no-pjax="1">
          <?php echo osp_icon($ic, 'osp-nav-ico'); ?>
          <span class="osp-nav-text"><?php echo htmlspecialchars($lbl); ?></span>
          <?php if ($count): ?><span class="osp-nav-badge"><?php echo htmlspecialchars($count); ?></span><?php endif; ?>
        </a>
      <?php endforeach; ?>
    </nav>

    <div class="osp-presence">
      <div class="osp-presence-head">
        <span>Team online</span>
        <?php if ($online_now > 0): ?>
          <span class="osp-presence-count"><i></i><?php echo (int)$online_now; ?> live</span>
        <?php else: ?>
          <span class="osp-presence-count" style="opacity:.7"><i style="background:#94a3b8"></i>idle</span>
        <?php endif; ?>
      </div>
      <?php if ($presenceLive): ?>
        <div class="osp-presence-stack">
          <?php foreach ($presenceLive as $p): ?>
            <span class="osp-avatar" style="background:<?php echo $p['color']; ?>"><?php echo $p['init']; ?></span>
          <?php endforeach; ?>
          <?php if ($online_now > count($presenceLive)): ?>
            <span class="osp-presence-more">+<?php echo $online_now - count($presenceLive); ?></span>
          <?php endif; ?>
        </div>
      <?php else: ?>
        <div class="osp-presence-empty">No agents signed in within the last 15 min</div>
      <?php endif; ?>
    </div>

    <div class="osp-health-card">
      <div class="osp-health-head">
        <span class="osp-dot <?php echo $healthScore !== null ? 'osp-dot-pulse' : ''; ?>"
              <?php if ($healthScore === null) echo 'style="background:#94a3b8"'; ?>></span>
        <span class="osp-health-title">Service Health</span>
      </div>
      <?php if ($healthScore !== null): ?>
        <div class="osp-health-score"><?php echo number_format($healthScore, 2); ?><span>%</span></div>
      <?php else: ?>
        <div class="osp-health-score" style="font-size:18px;line-height:1.2;letter-spacing:0">No SLA<br><span>policies</span></div>
      <?php endif; ?>
      <div class="osp-health-meta"><?php echo htmlspecialchars($healthMeta); ?></div>
      <div class="osp-health-bars">
        <?php foreach ($healthBars as $h): ?>
          <span style="height:<?php echo (int)$h; ?>%"></span>
        <?php endforeach; ?>
      </div>
    </div>
  </aside>

  <!-- ============== MAIN ============== -->
  <main class="osp-main">

    <!-- Header -->
    <header class="osp-header">
      <div class="osp-crumbs">
        <span class="osp-crumb-muted">Dashboard</span>
        <span class="osp-crumb-sep">/</span>
        <span class="osp-crumb-current">Overview</span>
      </div>

      <div class="osp-header-right">
        <div class="osp-search">
          <?php echo osp_icon('search'); ?>
          <input type="text" placeholder="Search tickets, customers, IDs…" />
          <kbd>⌘K</kbd>
        </div>

        <button class="osp-btn osp-btn-ghost osp-btn-range" type="button"><?php echo osp_icon('sliders'); ?><span>Last 7 days</span><?php echo osp_icon('chevron-d'); ?></button>

        <button class="osp-theme-toggle" aria-label="Toggle theme" data-osp-theme-toggle>
          <svg class="osp-icon osp-icon-sun"  viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><circle cx="12" cy="12" r="4"/><line x1="12" y1="2" x2="12" y2="5"/><line x1="12" y1="19" x2="12" y2="22"/><line x1="2" y1="12" x2="5" y2="12"/><line x1="19" y1="12" x2="22" y2="12"/><line x1="4.93" y1="4.93" x2="6.81" y2="6.81"/><line x1="17.19" y1="17.19" x2="19.07" y2="19.07"/><line x1="4.93" y1="19.07" x2="6.81" y2="17.19"/><line x1="17.19" y1="6.81" x2="19.07" y2="4.93"/></svg>
          <svg class="osp-icon osp-icon-moon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79Z"/></svg>
        </button>

        <button class="osp-icon-btn osp-bell-btn" type="button" aria-label="Notifications" data-atl-bell>
          <?php echo osp_icon('bell'); ?>
          <span class="osp-notif-dot atl-bell-dot" hidden></span>
          <span class="atl-bell-badge" hidden>0</span>
        </button>

        <a class="osp-btn osp-btn-primary no-pjax" data-no-pjax="1"
           href="<?php echo ROOT_PATH; ?>scp/tickets.php?a=open"
           title="Open a new ticket"><?php echo osp_icon('plus'); ?><span>New ticket</span></a>

        <div class="osp-profile">
          <div class="osp-avatar" style="background:linear-gradient(135deg,#2563eb,#7c3aed)"><?php echo htmlspecialchars($staff_init); ?></div>
          <div class="osp-profile-text">
            <span class="osp-profile-name"><?php echo htmlspecialchars($staff_name); ?></span>
            <span class="osp-profile-role"><?php echo htmlspecialchars($staff_role); ?></span>
          </div>
        </div>
      </div>
    </header>

    <!-- Page title row -->
    <section class="osp-titlebar">
      <div>
        <h1 class="osp-h1">Good day, <?php echo htmlspecialchars($thisstaff ? $thisstaff->getName()->getFirst() : 'there'); ?>.</h1>
        <p class="osp-subtle">Here is what your support floor looks like right now — <?php echo htmlspecialchars($greetingDate); ?></p>
      </div>
      <div class="osp-tabs">
        <button class="osp-tab is-active">Today</button>
        <button class="osp-tab">7 days</button>
        <button class="osp-tab">30 days</button>
        <button class="osp-tab">Quarter</button>
      </div>
    </section>

    <!-- ============== KPI GRID ============== -->
    <section class="osp-kpis">
      <?php foreach ($kpis as $k):
          $tone = $k['tone']; // up | down | bad
          $deltaClass = $tone === 'up' ? 'osp-up' : ($tone === 'bad' ? 'osp-bad' : 'osp-down');
          $deltaIcon  = ($k['delta'] >= 0) ? 'trend-up' : 'trend-dn';
      ?>
      <div class="osp-kpi">
        <div class="osp-kpi-head">
          <div class="osp-kpi-ico"><?php echo osp_icon($k['icon']); ?></div>
          <div class="osp-kpi-delta <?php echo $deltaClass; ?>">
            <?php echo osp_icon($deltaIcon); ?>
            <span><?php echo ($k['delta'] >= 0 ? '+' : '').number_format($k['delta'], 1); ?>%</span>
          </div>
        </div>
        <div class="osp-kpi-label"><?php echo htmlspecialchars($k['label']); ?></div>
        <div class="osp-kpi-value"><?php echo htmlspecialchars($k['value']); ?></div>
        <div class="osp-kpi-progress"><span style="width:<?php echo (int)$k['progress']; ?>%"></span></div>
        <div class="osp-kpi-caption"><?php echo htmlspecialchars($k['caption']); ?></div>
      </div>
      <?php endforeach; ?>
    </section>

    <!-- ============== ROW: Trend + Priority ============== -->
    <section class="osp-row osp-row-12">
      <article class="osp-card osp-span-8">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Ticket Status Trend</h2>
            <p class="osp-card-sub">Created vs resolved · last 12 weeks</p>
          </div>
          <div class="osp-legend">
            <span><i style="background:#2563eb"></i>Created</span>
            <span><i style="background:#10b981"></i>Resolved</span>
            <span><i style="background:#ef4444"></i>SLA breaches</span>
          </div>
        </header>
        <div class="osp-chart-wrap" style="height:280px"><canvas id="ospTrend"></canvas></div>
      </article>

      <article class="osp-card osp-span-4">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Priority Distribution</h2>
            <p class="osp-card-sub">Active tickets right now</p>
          </div>
        </header>
        <div class="osp-donut-wrap">
          <div class="osp-chart-wrap" style="height:200px"><canvas id="ospPriority"></canvas></div>
          <div class="osp-donut-center">
            <?php if ($priorityEmpty): ?>
              <div class="osp-donut-num" style="font-size:18px;letter-spacing:0">—</div>
              <div class="osp-donut-label">No active</div>
            <?php else: ?>
              <div class="osp-donut-num"><?php echo number_format(array_sum(array_column($priority,'value'))); ?></div>
              <div class="osp-donut-label">Active</div>
            <?php endif; ?>
          </div>
        </div>
        <?php if ($priorityEmpty): ?>
          <div class="osp-empty-state" style="padding:8px 8px 0">
            <div class="osp-empty-title">No tickets in active states</div>
            <div class="osp-empty-meta">Open or pending tickets — grouped by priority — will appear here as they are created.</div>
          </div>
        <?php else: ?>
          <ul class="osp-stack-list">
            <?php $sum = array_sum(array_column($priority,'value')); foreach ($priority as $p): $pct = $sum ? round($p['value']/$sum*100) : 0; ?>
              <li>
                <span class="osp-dot-color" style="background:<?php echo $p['color']; ?>"></span>
                <span class="osp-stack-label"><?php echo htmlspecialchars($p['label']); ?></span>
                <span class="osp-stack-val"><?php echo number_format($p['value']); ?></span>
                <span class="osp-stack-pct"><?php echo $pct; ?>%</span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </article>
    </section>

    <!-- ============== ROW: Departments + Sources + CSAT ============== -->
    <section class="osp-row osp-row-12">
      <article class="osp-card osp-span-5">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Department Load</h2>
            <p class="osp-card-sub">Open tickets by department</p>
          </div>
          <a class="osp-link no-pjax" href="<?php echo ROOT_PATH; ?>scp/departments.php">View all <?php echo osp_icon('arrow-right'); ?></a>
        </header>
        <?php if ($departmentsEmpty): ?>
          <div class="osp-empty-state" style="padding:24px 8px">
            <div class="osp-empty-ico"><?php echo osp_icon('building'); ?></div>
            <div class="osp-empty-title">No active queues</div>
            <div class="osp-empty-meta">No departments currently hold open or pending tickets. Department load will populate as tickets flow in.</div>
          </div>
        <?php else: ?>
          <ul class="osp-bars">
            <?php $maxOpen = max(array_column($departments,'open')) ?: 1; foreach ($departments as $d): $w = round($d['open']/$maxOpen*100); ?>
              <li>
                <div class="osp-bars-row">
                  <span class="osp-bars-name"><?php echo htmlspecialchars($d['name']); ?></span>
                  <span class="osp-bars-val"><?php echo number_format($d['open']); ?></span>
                </div>
                <div class="osp-bars-track"><span style="width:<?php echo $w; ?>%"></span></div>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </article>

      <article class="osp-card osp-span-4">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Ticket Sources</h2>
            <p class="osp-card-sub">Channel breakdown</p>
          </div>
        </header>
        <?php if ($sourcesEmpty): ?>
          <div class="osp-empty-state" style="padding:30px 12px">
            <div class="osp-empty-ico"><?php echo osp_icon('globe'); ?></div>
            <div class="osp-empty-title">No tickets received in the last 30 days</div>
            <div class="osp-empty-meta">Channel breakdown shows volume by source (web, email, phone, API) once tickets are submitted.</div>
          </div>
        <?php else: ?>
          <div class="osp-chart-wrap" style="height:170px"><canvas id="ospSources"></canvas></div>
          <ul class="osp-channel-list">
            <?php foreach ($sources as $s): ?>
              <li>
                <span class="osp-dot-color" style="background:<?php echo $s['color']; ?>"></span>
                <span class="osp-channel-label"><?php echo htmlspecialchars($s['label']); ?></span>
                <span class="osp-channel-val"><?php echo number_format($s['value']); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
        <?php endif; ?>
      </article>

      <article class="osp-card osp-span-3">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">CSAT</h2>
            <p class="osp-card-sub">Customer satisfaction</p>
          </div>
        </header>
        <?php if ((int)$csat['responses'] > 0 && $csat['score'] !== null): ?>
          <div class="osp-csat-num"><?php echo number_format($csat['score'], 1); ?><span>%</span></div>
          <?php if ($csat['delta'] !== null): ?>
            <div class="osp-csat-delta <?php echo $csat['delta'] >= 0 ? 'osp-up' : 'osp-bad'; ?>">
              <?php echo osp_icon($csat['delta'] >= 0 ? 'trend-up' : 'trend-dn'); ?>
              <?php echo ($csat['delta'] >= 0 ? '+' : ''); ?><?php echo number_format($csat['delta'], 1); ?>% this week
            </div>
          <?php endif; ?>
          <div class="osp-csat-bar">
            <span style="flex:<?php echo (int)$csat['positive']; ?>;background:#10b981"></span>
            <span style="flex:<?php echo (int)$csat['neutral'];  ?>;background:#f59e0b"></span>
            <span style="flex:<?php echo (int)$csat['negative']; ?>;background:#ef4444"></span>
          </div>
          <ul class="osp-csat-legend">
            <li><i style="background:#10b981"></i>Positive <b><?php echo number_format($csat['positive']); ?></b></li>
            <li><i style="background:#f59e0b"></i>Neutral <b><?php echo number_format($csat['neutral']); ?></b></li>
            <li><i style="background:#ef4444"></i>Negative <b><?php echo number_format($csat['negative']); ?></b></li>
          </ul>
          <div class="osp-csat-foot"><?php echo number_format($csat['responses']); ?> responses</div>
        <?php else: ?>
          <div class="osp-empty-state">
            <div class="osp-empty-ico"><?php echo osp_icon('message'); ?></div>
            <div class="osp-empty-title">No CSAT responses yet</div>
            <div class="osp-empty-meta">osTicket OSS does not collect satisfaction feedback natively. Connect a survey integration to populate this card.</div>
          </div>
        <?php endif; ?>
      </article>
    </section>

    <!-- ============== ROW: Alerts (left, wide) + SLA Risk Radar (right) ============== -->
    <section class="osp-row osp-row-12">
      <article class="osp-card osp-span-8 osp-alerts-card">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">
              <span class="osp-pulse"></span>
              Critical Alerts &amp; Intelligence
            </h2>
            <p class="osp-card-sub">Live signals across SLA, queues, sentiment and AI triage</p>
          </div>
          <a class="osp-btn osp-btn-ghost-sm no-pjax" data-no-pjax="1"
             href="<?php echo ROOT_PATH; ?>scp/tickets.php">View all (<?php echo (int)$breachedNow + (int)$overC; ?>)</a>
        </header>
        <ul class="osp-alerts">
          <?php foreach ($alerts as $idx => $a): $c = osp_alert_color($a['kind']);
                $cd = isset($alert_countdowns[$idx]) ? $alert_countdowns[$idx] : null; ?>
            <li class="osp-alert">
              <span class="osp-alert-rail" style="background:<?php echo $c; ?>"></span>
              <div class="osp-alert-ico" style="color:<?php echo $c; ?>;background:<?php echo $c; ?>14">
                <?php
                  $iconMap = ['breach'=>'shield-x','overdue'=>'clock','unassign'=>'users','escalate'=>'flame','ai'=>'sparkle','health'=>'pulse'];
                  echo osp_icon($iconMap[$a['kind']]);
                ?>
              </div>
              <div class="osp-alert-body">
                <div class="osp-alert-title"><?php echo htmlspecialchars($a['title']); ?></div>
                <div class="osp-alert-meta"><?php echo htmlspecialchars($a['meta']); ?> · <span><?php echo htmlspecialchars($a['actor']); ?></span></div>
              </div>
              <?php if ($cd !== null): ?>
                <span class="osp-countdown <?php echo $cd < 0 ? 'osp-cd-breach' : ($cd < 1800 ? 'osp-cd-risk' : 'osp-cd-ok'); ?>"
                      data-osp-countdown="<?php echo (int)$cd; ?>">--:--:--</span>
              <?php else: ?>
                <span></span>
              <?php endif; ?>
              <?php if (!empty($a['url'])): ?>
                <a class="osp-btn osp-btn-ghost-sm no-pjax" data-no-pjax="1" href="<?php echo htmlspecialchars($a['url']); ?>"><?php echo htmlspecialchars($a['cta'] ?: 'View'); ?></a>
              <?php else: ?>
                <span></span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
        </ul>
      </article>

      <article class="osp-card osp-span-4">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">SLA Risk Radar</h2>
            <p class="osp-card-sub"><?php echo $sla['compliance'] !== null
              ? 'Compliance vs goal · last 30 days'
              : 'No SLA policies on tracked tickets'; ?></p>
          </div>
        </header>
        <?php if ($sla['compliance'] !== null): ?>
          <div class="osp-gauge">
            <svg viewBox="0 0 120 70" class="osp-gauge-svg" aria-hidden="true">
              <defs>
                <linearGradient id="ospGauge" x1="0" x2="1">
                  <stop offset="0" stop-color="#ef4444"/>
                  <stop offset=".5" stop-color="#f59e0b"/>
                  <stop offset="1" stop-color="#10b981"/>
                </linearGradient>
              </defs>
              <path d="M10 60 A50 50 0 0 1 110 60" stroke="#eef2f7" stroke-width="10" fill="none" stroke-linecap="round"/>
              <path d="M10 60 A50 50 0 0 1 110 60" stroke="url(#ospGauge)" stroke-width="10" fill="none" stroke-linecap="round"
                    stroke-dasharray="<?php echo round(min(100, max(0, (float)$sla['compliance'])) * 1.57); ?>,200"/>
            </svg>
            <div class="osp-gauge-num"><?php echo number_format($sla['compliance'], 1); ?><span>%</span></div>
            <div class="osp-gauge-cap">SLA Compliance · goal <?php echo (int)$sla['goal']; ?>% · n=<?php echo (int)$sla['sample']; ?></div>
          </div>
          <div class="osp-sla-stats">
            <div><span class="osp-sla-num" style="color:#b91c1c"><?php echo (int)$sla['breaches']; ?></span><span>Breached</span></div>
            <div><span class="osp-sla-num" style="color:#c2410c"><?php echo (int)$sla['at_risk']; ?></span><span>At risk</span></div>
            <?php if ($sla['wow'] !== null):
              $wowColor = $sla['wow'] >= 0 ? '#047857' : '#b91c1c';
              $wowSign  = $sla['wow'] >= 0 ? '+' : '';
            ?>
              <div><span class="osp-sla-num" style="color:<?php echo $wowColor; ?>"><?php echo $wowSign . number_format($sla['wow'], 1); ?>pt</span><span>WoW</span></div>
            <?php else: ?>
              <div><span class="osp-sla-num" style="color:#94a3b8">—</span><span>WoW</span></div>
            <?php endif; ?>
          </div>
        <?php else: ?>
          <div class="osp-empty-state" style="padding:30px 12px">
            <div class="osp-empty-ico"><?php echo osp_icon('shield'); ?></div>
            <div class="osp-empty-title">No SLA breaches detected</div>
            <div class="osp-empty-meta">Configure SLA plans under Admin → Manage → SLA Plans, then attach them to help topics or departments to start tracking compliance.</div>
          </div>
        <?php endif; ?>
      </article>
    </section>

    <!-- ============== ROW: AI Insights + Live Activity ============== -->
    <section class="osp-row osp-row-12">
      <article class="osp-card osp-span-6 osp-card-ai">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">
              <?php echo osp_icon('pulse'); ?>
              Operations Insights
            </h2>
            <p class="osp-card-sub">Live signals derived from your ticket database</p>
          </div>
          <span class="osp-ai-badge"><i></i>Live</span>
        </header>

        <ul class="osp-insights">
          <?php foreach ($insights as $ins):
            $sev = isset($ins['sev']) ? $ins['sev'] : 'ok';
            $sevLabel = ['breach'=>'Action','watch'=>'Watch','ok'=>'Healthy'][$sev] ?? 'Live';
          ?>
            <li class="osp-insight osp-insight-<?php echo htmlspecialchars($sev); ?>">
              <div class="osp-insight-ico"><?php echo osp_icon($ins['icon']); ?></div>
              <div class="osp-insight-body">
                <div class="osp-insight-title"><?php echo htmlspecialchars($ins['title']); ?></div>
                <div class="osp-insight-meta"><?php echo htmlspecialchars($ins['meta']); ?></div>
              </div>
              <span class="osp-insight-conf"><?php echo htmlspecialchars($sevLabel); ?></span>
            </li>
          <?php endforeach; ?>
        </ul>
      </article>

      <article class="osp-card osp-span-6">
        <header class="osp-card-head osp-activity-head">
          <div>
            <h2 class="osp-card-title">Live Activity</h2>
            <p class="osp-card-sub">Real-time stream from your support floor</p>
          </div>
          <span class="osp-live-pill"><i></i>Streaming</span>
        </header>

        <?php if ($activity): ?>
          <ul class="osp-stream">
            <?php foreach ($activity as $ev): ?>
              <li data-kind="<?php echo htmlspecialchars($ev['kind']); ?>">
                <span class="osp-stream-ico osp-stream-ico-<?php echo htmlspecialchars($ev['kind']); ?>"><?php echo osp_icon($ev['icon']); ?></span>
                <div class="osp-stream-title">
                  <b><?php echo htmlspecialchars($ev['who']); ?></b>
                  <?php echo htmlspecialchars($ev['verb']); ?>
                  <a class="no-pjax" data-no-pjax="1"
                     href="<?php echo ROOT_PATH; ?>scp/tickets.php?id=<?php echo (int)$ev['tk_id']; ?>">#<?php echo htmlspecialchars($ev['tk_num']); ?></a><?php
                  if (!empty($ev['dept'])): ?> · <span class="osp-stream-dept"><?php echo htmlspecialchars($ev['dept']); ?></span><?php endif; ?>
                </div>
                <span class="osp-stream-time"><?php echo htmlspecialchars($ev['time']); ?></span>
              </li>
            <?php endforeach; ?>
          </ul>
          <div class="osp-stream-fade"></div>
        <?php else: ?>
          <div class="osp-empty-state" style="padding:48px 16px">
            <div class="osp-empty-ico"><?php echo osp_icon('pulse'); ?></div>
            <div class="osp-empty-title">Live activity will appear here</div>
            <div class="osp-empty-meta">No ticket events recorded yet. Once your team opens, replies to, or resolves tickets the stream will populate in real time.</div>
          </div>
        <?php endif; ?>
      </article>
    </section>

    <!-- ============== ROW: Department Heatmap + Automation Savings + Sentiment ============== -->
    <section class="osp-row osp-row-12">
      <article class="osp-card osp-span-6">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Department Heatmap</h2>
            <p class="osp-card-sub">Volume intensity by day</p>
          </div>
        </header>
        <?php if ($heatmapEmpty): ?>
          <div class="osp-empty-state" style="padding:36px 16px">
            <div class="osp-empty-ico"><?php echo osp_icon('grid'); ?></div>
            <div class="osp-empty-title">No ticket activity in the last 7 days</div>
            <div class="osp-empty-meta">Volume intensity by department and weekday will appear here once tickets are created across your departments.</div>
          </div>
        <?php else: ?>
          <div class="osp-heatmap">
            <div class="osp-heatmap-head">
              <span></span>
              <?php foreach ($heatmap_days as $d): ?><span><?php echo $d; ?></span><?php endforeach; ?>
            </div>
            <?php foreach ($heatmap as $dept => $cells): ?>
              <div class="osp-heatmap-row">
                <span class="osp-heatmap-label"><?php echo htmlspecialchars($dept); ?></span>
                <?php foreach ($cells as $v): $alpha = max(.05, min(.92, $v)); ?>
                  <span class="osp-heatmap-cell" style="background:rgba(37,99,235,<?php echo $alpha; ?>)" title="<?php echo round($v*100); ?>% of peak"></span>
                <?php endforeach; ?>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="osp-heatmap-foot"><span>Less</span><i class="osp-h-l1"></i><i class="osp-h-l2"></i><i class="osp-h-l3"></i><i class="osp-h-l4"></i><i class="osp-h-l5"></i><span>More</span></div>
        <?php endif; ?>
      </article>

      <article class="osp-card osp-span-3">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Today's Throughput</h2>
            <p class="osp-card-sub">Activity in the last 24 hours</p>
          </div>
        </header>
        <div class="osp-savings">
          <div class="osp-savings-num"><?php echo number_format($throughput['replies']); ?><span> replies</span></div>
          <div class="osp-savings-cap">staff replies sent</div>
          <ul class="osp-savings-list">
            <li><span>New tickets</span><b><?php echo number_format($throughput['created']); ?></b></li>
            <li><span>Closed</span><b><?php echo number_format($throughput['closed']); ?></b></li>
            <li><span>Reopened</span><b><?php echo number_format($throughput['reopened']); ?></b></li>
            <li><span>Net change</span><b><?php
              $net = (int)$throughput['created'] - (int)$throughput['closed'];
              echo ($net > 0 ? '+' : '') . number_format($net);
            ?></b></li>
          </ul>
        </div>
      </article>

      <article class="osp-card osp-span-3">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Status Breakdown</h2>
            <p class="osp-card-sub">All tickets by state</p>
          </div>
        </header>
        <?php if ($statusTotal > 0): ?>
          <div class="osp-sentiment">
            <?php foreach ($statusBreakdown as $s):
              $pct = $statusTotal ? round($s['value'] / $statusTotal * 100, 1) : 0; ?>
              <div class="osp-sentiment-row">
                <span class="osp-dot-color" style="background:<?php echo $s['color']; ?>;width:10px;height:10px;display:inline-block;border-radius:50%"></span>
                <div class="osp-sent-track"><span style="width:<?php echo (float)$pct; ?>%;background:<?php echo $s['color']; ?>"></span></div>
                <span class="osp-sent-val"><?php echo number_format($s['value']); ?></span>
              </div>
            <?php endforeach; ?>
          </div>
          <div class="osp-sentiment-foot"><?php echo number_format($statusTotal); ?> tickets across all states</div>
        <?php else: ?>
          <div class="osp-empty-state">
            <div class="osp-empty-ico"><?php echo osp_icon('inbox'); ?></div>
            <div class="osp-empty-title">No tickets in the system yet</div>
          </div>
        <?php endif; ?>
      </article>
    </section>

    <!-- ============== RECENT TICKETS TABLE ============== -->
    <section class="osp-row">
      <article class="osp-card osp-span-12">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Recent Tickets</h2>
            <p class="osp-card-sub">Updated in the last hour</p>
          </div>
          <div class="osp-table-actions">
            <div class="osp-mini-tabs">
              <a class="is-active no-pjax" href="<?php echo ROOT_PATH; ?>scp/tickets.php" data-no-pjax="1">All <span class="osp-mini-count"><?php echo number_format($miniTabCounts['all']); ?></span></a>
              <a class="no-pjax" href="<?php echo ROOT_PATH; ?>scp/tickets.php?status=open" data-no-pjax="1">Open <span class="osp-mini-count"><?php echo number_format($miniTabCounts['open']); ?></span></a>
              <a class="no-pjax" href="<?php echo ROOT_PATH; ?>scp/tickets.php?status=pending" data-no-pjax="1">Pending <span class="osp-mini-count"><?php echo number_format($miniTabCounts['pending']); ?></span></a>
              <a class="no-pjax" href="<?php echo ROOT_PATH; ?>scp/tickets.php?status=overdue" data-no-pjax="1">Breached <span class="osp-mini-count"><?php echo number_format($miniTabCounts['breach']); ?></span></a>
            </div>
            <a class="osp-btn osp-btn-ghost-sm no-pjax" href="<?php echo ROOT_PATH; ?>scp/tickets.php" data-no-pjax="1"><?php echo osp_icon('sliders'); ?>Filter</a>
          </div>
        </header>

        <div class="osp-table-wrap">
          <table class="osp-table">
            <thead>
              <tr>
                <th class="osp-th-check"><input type="checkbox" /></th>
                <th>Ticket</th>
                <th>Subject</th>
                <th>Requester</th>
                <th>Department</th>
                <th>Priority</th>
                <th>Assignee</th>
                <th>SLA</th>
                <th>Updated</th>
                <th>Status</th>
                <th></th>
              </tr>
            </thead>
            <tbody>
              <?php if ($ticketsEmpty): ?>
                <tr>
                  <td colspan="11" style="padding:0">
                    <div class="osp-empty-state" style="padding:40px 16px">
                      <div class="osp-empty-ico"><?php echo osp_icon('inbox'); ?></div>
                      <div class="osp-empty-title">No tickets in the system yet</div>
                      <div class="osp-empty-meta" style="max-width:46ch">
                        Once tickets arrive via web form, email, or API, they will appear here sorted by most-recent activity.
                        Configure inbound email under <a class="no-pjax" data-no-pjax="1" href="<?php echo ROOT_PATH; ?>scp/emails.php" style="color:inherit;text-decoration:underline">Admin&nbsp;→&nbsp;Emails</a>.
                      </div>
                    </div>
                  </td>
                </tr>
              <?php else: foreach ($tickets as $t):
                // Initials: take first letter of first word + first letter of last word
                $nameParts = preg_split('/\s+/', trim($t['agent'][0]));
                $first = $nameParts[0][0] ?? '';
                $last  = (count($nameParts) > 1) ? ($nameParts[count($nameParts)-1][0] ?? '') : '';
                $initials = strtoupper($first . $last) ?: '?';
                $rc = isset($replyCountByTicket[$t['id']]) ? (int)$replyCountByTicket[$t['id']] : 0;
              ?>
              <tr>
                <td><input type="checkbox" /></td>
                <td class="osp-mono">#<?php echo htmlspecialchars($t['id']); ?></td>
                <td>
                  <a class="osp-tk-subject no-pjax" data-no-pjax="1"
                     href="<?php echo ROOT_PATH; ?>scp/tickets.php?number=<?php echo urlencode($t['id']); ?>"
                     style="color:inherit;text-decoration:none"><?php echo htmlspecialchars($t['subject']); ?></a>
                  <div class="osp-tk-sub"><?php echo osp_icon('message','osp-ico-12'); ?> <?php echo osp_plural($rc, 'message'); ?></div>
                </td>
                <td>
                  <div class="osp-tk-req"><?php echo htmlspecialchars($t['requester']); ?></div>
                  <?php if ($t['company']): ?>
                    <div class="osp-tk-sub"><?php echo htmlspecialchars($t['company']); ?></div>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($t['dept'] ?: '—'); ?></td>
                <td><?php echo osp_priority_pill($t['priority']); ?></td>
                <td>
                  <div class="osp-assignee">
                    <span class="osp-avatar osp-avatar-sm" style="background:<?php echo $t['agent'][1]; ?>"><?php echo $initials; ?></span>
                    <span><?php echo htmlspecialchars($t['agent'][0]); ?></span>
                  </div>
                </td>
                <td><?php echo osp_sla_chip($t['sla']); ?></td>
                <td class="osp-tk-time"><?php echo htmlspecialchars($t['updated']); ?></td>
                <td><?php echo osp_status_pill($t['status']); ?></td>
                <td>
                  <a class="osp-icon-btn osp-icon-btn-sm no-pjax" data-no-pjax="1" aria-label="Open ticket"
                     href="<?php echo ROOT_PATH; ?>scp/tickets.php?number=<?php echo urlencode($t['id']); ?>">
                    <?php echo osp_icon('arrow-right'); ?>
                  </a>
                </td>
              </tr>
              <?php endforeach; endif; ?>
            </tbody>
          </table>
        </div>

        <footer class="osp-table-foot">
          <?php if ($ticketsEmpty): ?>
            <span>Awaiting first ticket</span>
          <?php else: ?>
            <span>Showing 1–<?php echo count($tickets); ?> of <?php echo number_format($tableTotal); ?></span>
          <?php endif; ?>
          <a class="osp-link no-pjax" data-no-pjax="1" href="<?php echo ROOT_PATH; ?>scp/tickets.php">
            Open full ticket queue <?php echo osp_icon('arrow-right'); ?>
          </a>
        </footer>
      </article>
    </section>

    <!-- ============== AGENT PERFORMANCE ============== -->
    <section class="osp-row">
      <article class="osp-card osp-span-12">
        <header class="osp-card-head">
          <div>
            <h2 class="osp-card-title">Top Agent Performance</h2>
            <p class="osp-card-sub">Tickets closed, response time, CSAT and current workload</p>
          </div>
          <a class="osp-link no-pjax" href="<?php echo ROOT_PATH; ?>scp/staff.php">View leaderboard <?php echo osp_icon('arrow-right'); ?></a>
        </header>

        <?php if ($agentsEmpty): ?>
          <div class="osp-empty-state" style="padding:36px 16px">
            <div class="osp-empty-ico"><?php echo osp_icon('users'); ?></div>
            <div class="osp-empty-title">No agent activity in the last 30 days</div>
            <div class="osp-empty-meta" style="max-width:46ch">
              Agent performance insights will appear here automatically once
              ticket ownership and replies are recorded. Add agents under
              <a class="no-pjax" data-no-pjax="1" href="<?php echo ROOT_PATH; ?>scp/staff.php" style="color:inherit;text-decoration:underline">Admin&nbsp;→&nbsp;Agents</a>
              and assign them to departments to start seeing leaderboard data.
            </div>
          </div>
        <?php else: ?>
        <div class="osp-agents">
          <?php foreach ($agents as $i => $a): ?>
            <div class="osp-agent">
              <div class="osp-agent-head">
                <div class="osp-avatar osp-avatar-md" style="background:<?php echo $a['color']; ?>"><?php echo $a['init']; ?></div>
                <div>
                  <div class="osp-agent-name"><?php echo htmlspecialchars($a['name']); ?></div>
                  <div class="osp-agent-role"><?php echo htmlspecialchars($a['role']); ?></div>
                </div>
                <span class="osp-rank">#<?php echo $i+1; ?></span>
              </div>

              <div class="osp-agent-stats">
                <div><span class="osp-agent-stat-v"><?php echo (int)$a['closed']; ?></span><span class="osp-agent-stat-l">Closed 30d</span></div>
                <div><span class="osp-agent-stat-v"><?php echo htmlspecialchars($a['response']); ?></span><span class="osp-agent-stat-l">Avg resp.</span></div>
                <div><span class="osp-agent-stat-v"><?php echo (int)$a['open']; ?></span><span class="osp-agent-stat-l">Open</span></div>
              </div>

              <div class="osp-agent-load">
                <div class="osp-agent-load-row">
                  <span>Workload</span>
                  <span><?php echo $a['load']; ?>%</span>
                </div>
                <div class="osp-agent-load-track">
                  <span style="width:<?php echo $a['load']; ?>%;background:<?php echo $a['color']; ?>"></span>
                </div>
              </div>
            </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </article>
    </section>

    <footer class="osp-foot">
      <?php
        // Compose the system-status pill from real signals.
        if ($brC > 0)        { $sysClass = 'osp-foot-bad';   $sysLabel = osp_plural($brC, 'active SLA breach', 'active SLA breaches'); }
        elseif ($overC > 0)  { $sysClass = 'osp-foot-warn';  $sysLabel = osp_plural($overC, 'overdue ticket') . ' open'; }
        else                 { $sysClass = 'osp-foot-ok';    $sysLabel = 'All systems operational'; }
        $envBadge   = $workspaceEnv ?: 'Helpdesk';
        $buildVer   = defined('GIT_VERSION') ? GIT_VERSION : (defined('THIS_VERSION') ? THIS_VERSION : '');
        $renderTime = number_format((microtime(true) - (defined('OSP_T0') ? OSP_T0 : microtime(true))) * 1000, 0);
      ?>
      <div class="osp-foot-left">
        <span class="osp-foot-status <?php echo $sysClass; ?>">
          <i></i><?php echo htmlspecialchars($sysLabel); ?>
        </span>
        <span class="osp-foot-sep">·</span>
        <span class="osp-foot-env"><?php echo htmlspecialchars($envBadge); ?></span>
      </div>
      <div class="osp-foot-right">
        <span><?php echo htmlspecialchars($workspaceName); ?></span>
        <span class="osp-foot-sep">·</span>
        <?php if ($buildVer): ?>
          <span title="Build version">build <?php echo htmlspecialchars(substr($buildVer, 0, 8)); ?></span>
          <span class="osp-foot-sep">·</span>
        <?php endif; ?>
        <span title="Server time"><?php echo date('Y-m-d H:i'); ?></span>
      </div>
    </footer>

  </main>
</div>

<!-- Chart.js (self-hosted — osTicket CSP blocks external CDNs by default) -->
<script src="<?php echo ROOT_PATH; ?>scp/js/chart.umd.min.js"></script>
<script src="<?php echo ROOT_PATH; ?>scp/js/custom-dashboard.js"></script>
