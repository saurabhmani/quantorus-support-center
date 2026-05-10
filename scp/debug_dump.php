<?php
define('OSTSCPINC', true);
require('main.inc.php');
// Bypass login check for debug
$thisstaff = StaffSession::lookup(1); // Assuming admin is ID 1
$_SESSION['_staff'] = array('userID' => 1);
require_once(INCLUDE_DIR.'class.staff.php');
require_once(INCLUDE_DIR.'class.ticket.php');

// mock canCreateTickets
if(!method_exists($thisstaff, 'canCreateTickets')) {
    // maybe we can't mock easily, let's just bypass it in the file? 
    // better to just use a fake class but $thisstaff is an object.
}

ob_start();
include(STAFFINC_DIR . 'ticket-open.inc.php');
$html = ob_get_clean();
echo $html;
?>
