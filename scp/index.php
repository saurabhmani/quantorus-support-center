<?php
/*********************************************************************
    index.php
    
    Future site for helpdesk summary aka Dashboard.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
// /scp/  →  premium operational dashboard.
//
// URL canonicalisation: if the browser URL is /scp/index.php (no params,
// GET request), 301-redirect to /scp/ so the cleaner URL is the only
// public surface. POST-backs and any querystring requests pass through.
if ($_SERVER['REQUEST_METHOD'] === 'GET'
    && empty($_SERVER['QUERY_STRING'])
    && substr($_SERVER['REQUEST_URI'], -10) === '/index.php') {
    $clean = substr($_SERVER['REQUEST_URI'], 0, -9);  // strip "index.php"
    header('Location: ' . $clean, true, 301);
    exit;
}

require('dashboard-pro.php');
?>
