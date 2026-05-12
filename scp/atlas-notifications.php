<?php
/*********************************************************************
    atlas-notifications.php

    Real-time JSON feed for the Atlas premium topbar bell.

    Notifications are TICKET-ONLY:
       - new ticket
       - ticket assigned to me
       - ticket reply (new thread entry)
       - SLA breach / overdue
       - status update (implicit via lastupdate)

    Per-ticket read state is persisted in ost_config so the unread
    badge survives logout. State is namespaced per-staff and pruned
    (14-day window, hard cap of 500 entries).

    GET  /scp/atlas-notifications.php             -> full feed
    GET  /scp/atlas-notifications.php?count=1     -> count only
    POST /scp/atlas-notifications.php?read=<tid>  -> mark one ticket read
    POST /scp/atlas-notifications.php?read=1,2,3  -> mark several
**********************************************************************/

function staffLoginPage($msg='Unauthorized') {
    if (!headers_sent()) header('HTTP/1.1 403 Forbidden');
    echo json_encode(['error' => 'Unauthorized', 'msg' => $msg]);
    exit;
}

define('AJAX_REQUEST', 1);
require('staff.inc.php');
ini_set('display_errors', '0');
ini_set('display_startup_errors', '0');

if (!$thisstaff || !$thisstaff->getId()) {
    header('HTTP/1.1 403 Forbidden');
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'no_session']);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('X-Content-Type-Options: nosniff');

$staffId = (int) $thisstaff->getId();

// ============================================================
// Read-state persistence (ost_config; no schema changes)
// ============================================================
function _atl_ns($staffId) { return 'atl.notif.' . (int)$staffId; }

function _atl_load_read_state($staffId) {
    $ns = _atl_ns($staffId);
    $sql = sprintf(
        "SELECT `value` FROM %s WHERE `namespace`='%s' AND `key`='read_state' LIMIT 1",
        CONFIG_TABLE, db_real_escape($ns, false)
    );
    $r = @db_query($sql, false);
    if (!$r) return [];
    $row = db_fetch_array($r);
    if (!$row) return [];
    $decoded = json_decode($row['value'], true);
    return is_array($decoded) ? $decoded : [];
}

function _atl_save_read_state($staffId, array $state) {
    // Prune anything older than 14 days
    $cutoff = time() - 1209600;
    foreach ($state as $tid => $when)
        if ((int)$when < $cutoff) unset($state[$tid]);
    // Hard cap (most-recently-read kept)
    if (count($state) > 500) {
        arsort($state);
        $state = array_slice($state, 0, 500, true);
    }
    $ns  = _atl_ns($staffId);
    $val = db_real_escape(json_encode($state, JSON_UNESCAPED_SLASHES), true);
    $nsE = db_real_escape($ns, true);
    $sql = "INSERT INTO " . CONFIG_TABLE
         . " (`namespace`, `key`, `value`) VALUES ($nsE, 'read_state', $val) "
         . "ON DUPLICATE KEY UPDATE `value`=VALUES(`value`), `updated`=CURRENT_TIMESTAMP";
    @db_query($sql, false);
    return $state;
}

// ============================================================
// POST: mark specific tickets read
//   Accepts: ?read=123  OR  ?read=123,456,789
// ============================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_GET['read'])) {
    $raw = (string) $_GET['read'];
    $ids = array_filter(array_map('intval', explode(',', $raw)));
    $state = _atl_load_read_state($staffId);
    $now = time();
    foreach ($ids as $id) $state[(string)$id] = $now;
    $state = _atl_save_read_state($staffId, $state);
    echo json_encode(['ok' => true, 'marked' => count($ids), 'read_count' => count($state)]);
    exit;
}

// ============================================================
// Helpers
// ============================================================
function _atl_safe_query($sql) {
    try { return @db_query($sql, false) ?: null; }
    catch (Throwable $e) { return null; }
    catch (Exception $e) { return null; }
}
function _atl_rows($res) {
    $out = [];
    if (!$res) return $out;
    while ($r = db_fetch_array($res)) $out[] = $r;
    return $out;
}
function _atl_relative_time($mysqlDt) {
    if (!$mysqlDt) return '';
    $t = strtotime($mysqlDt);
    if (!$t) return '';
    $d = max(0, time() - $t);
    if ($d < 60)     return $d . 's ago';
    if ($d < 3600)   return floor($d/60) . 'm ago';
    if ($d < 86400)  return floor($d/3600) . 'h ago';
    if ($d < 604800) return floor($d/86400) . 'd ago';
    return date('M j', $t);
}

$root = defined('ROOT_PATH') ? ROOT_PATH : '/';

// ============================================================
// Build the canonical event list for this staff
// One entry per ticket; severity & type are derived from the
// ticket's current state so click-to-read collapses neatly.
// ============================================================
$staffIdSql = (int) $staffId;

// 1) Recent tickets — created OR updated in last 14 days
//    Includes lastupdate (for replies) and assignment context.
$sqlTickets = sprintf(
    "SELECT t.ticket_id,
            t.number,
            t.staff_id,
            t.team_id,
            t.dept_id,
            t.created,
            t.lastupdate,
            t.duedate,
            t.est_duedate,
            t.isoverdue,
            t.isanswered,
            COALESCE(c.subject,'') AS subject,
            s.name  AS status_name,
            s.state AS status_state,
            CASE WHEN t.lastupdate > t.created THEN 1 ELSE 0 END AS has_activity
       FROM %s t
       LEFT JOIN %s c ON c.ticket_id = t.ticket_id
       LEFT JOIN %s s ON s.id = t.status_id
      WHERE (t.created    >= (NOW() - INTERVAL 14 DAY)
          OR t.lastupdate >= (NOW() - INTERVAL 14 DAY))
        AND (s.state IS NULL OR s.state IN ('open','closed'))
      ORDER BY GREATEST(t.lastupdate, t.created) DESC
      LIMIT 25",
    TICKET_TABLE, TICKET_CDATA_TABLE, TICKET_STATUS_TABLE
);
$tickets = _atl_rows(_atl_safe_query($sqlTickets));

// 2) Most-recent thread-entry author per ticket — to label "reply"
//    notifications (and avoid surfacing tickets where I'm the only poster).
$ticketIds = array_map(function($t){ return (int)$t['ticket_id']; }, $tickets);
$lastEntryByTicket = [];
if ($ticketIds) {
    // Map ticket_id → thread.id (object_type='T') so we can group entries
    $idsList = implode(',', array_map('intval', $ticketIds));
    $sqlEntries = sprintf(
        "SELECT th.object_id AS ticket_id,
                e.staff_id   AS entry_staff_id,
                e.user_id    AS entry_user_id,
                e.type       AS entry_type,
                e.created    AS entry_created
           FROM %s e
           JOIN %s th ON th.id = e.thread_id AND th.object_type='T'
          WHERE th.object_id IN (%s)
            AND e.created >= (NOW() - INTERVAL 14 DAY)
          ORDER BY e.created DESC",
        THREAD_ENTRY_TABLE, THREAD_TABLE, $idsList
    );
    foreach (_atl_rows(_atl_safe_query($sqlEntries)) as $row) {
        $tid = (int) $row['ticket_id'];
        if (!isset($lastEntryByTicket[$tid])) $lastEntryByTicket[$tid] = $row;
    }
}

// 3) Read state for this staff
$readState = _atl_load_read_state($staffId);

// ============================================================
// Compose the feed: one item per ticket, derive type + severity
// ============================================================
$items = [];
foreach ($tickets as $t) {
    $tid = (int) $t['ticket_id'];
    $isMine = ((int)$t['staff_id'] === $staffIdSql);
    $entry = isset($lastEntryByTicket[$tid]) ? $lastEntryByTicket[$tid] : null;
    $entryByOther = $entry
        && ((int)$entry['entry_staff_id']) !== $staffIdSql
        && in_array($entry['entry_type'], ['M','R','N']); // Message, Response, Note

    // Decide type
    $type = 'ticket';
    $meta = 'New ticket';
    $effectiveTime = $t['created'];
    $sev = 'info';

    if (!empty($t['isoverdue']) && ($t['status_state'] ?? '') === 'open') {
        $type = 'breach';
        $sev  = 'breach';
        $meta = 'SLA breach / overdue';
        $effectiveTime = $t['lastupdate'] ?: $t['created'];
    }
    elseif ($isMine && $entryByOther
            && strtotime($entry['entry_created']) > strtotime($t['created'])) {
        $type = 'reply';
        $sev  = 'reply';
        $meta = ($entry['entry_user_id'] && !$entry['entry_staff_id'])
              ? 'Customer replied' : 'New reply';
        $effectiveTime = $entry['entry_created'];
    }
    elseif ($isMine && (int)strtotime($t['lastupdate']) > (int)strtotime($t['created'])) {
        $type = 'assignment';
        $sev  = 'assigned';
        $meta = 'Assigned to you · ' . ($t['status_name'] ?: 'open');
        $effectiveTime = $t['lastupdate'];
    }
    elseif ($isMine) {
        $type = 'assignment';
        $sev  = 'assigned';
        $meta = 'Assigned to you';
    }
    else {
        $type = 'ticket';
        $sev  = 'info';
        $meta = 'New ticket' . ($t['status_name'] ? (' · ' . $t['status_name']) : '');
    }

    // Unread = item has been touched after the user's last read for this ticket
    $readAt = isset($readState[(string)$tid]) ? (int) $readState[(string)$tid] : 0;
    $effEpoch = (int) strtotime($effectiveTime);
    $unread = ($effEpoch > $readAt);

    $items[] = [
        'ticket_id' => $tid,
        'number'    => $t['number'],
        'type'      => $type,
        'sev'       => $sev,
        'unread'    => $unread,
        'title'     => $t['subject'] !== '' ? $t['subject'] : ('Ticket #' . $t['number']),
        'meta'      => $meta,
        'time'      => _atl_relative_time($effectiveTime),
        'ts'        => $effEpoch,
        'url'       => $root . 'scp/tickets.php?id=' . $tid,
    ];
}

// Sort: unread breaches → unread → read breaches → read (newest first within each)
usort($items, function ($a, $b) {
    $rank = function ($x) {
        if ($x['unread'] && $x['sev'] === 'breach')  return 0;
        if ($x['unread'])                             return 1;
        if ($x['sev'] === 'breach')                   return 2;
        return 3;
    };
    $ra = $rank($a); $rb = $rank($b);
    if ($ra !== $rb) return $ra - $rb;
    return $b['ts'] - $a['ts']; // newest first inside the bucket
});

$items = array_slice($items, 0, 12);
$unreadCount = 0;
foreach ($items as $i) if ($i['unread']) $unreadCount++;

// Lightweight version hash so JS can early-out when nothing changed
$versionParts = [];
foreach ($items as $i) $versionParts[] = $i['ticket_id'] . ':' . $i['ts'] . ':' . ($i['unread'] ? 'u' : 'r');
$version = substr(md5(implode('|', $versionParts)), 0, 12);

// Count-only (cheap polling) shortcut
if (isset($_GET['count'])) {
    echo json_encode([
        'count'   => $unreadCount,
        'version' => $version,
    ]);
    exit;
}

echo json_encode([
    'count'   => $unreadCount,
    'version' => $version,
    'items'   => $items,
    'now'     => date('Y-m-d H:i:s'),
]);
