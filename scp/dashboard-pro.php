<?php
/*********************************************************************
    dashboard-pro.php

    Premium custom dashboard for osTicket (open-source).
    - Reuses osTicket staff auth/session via staff.inc.php
    - Reuses osTicket global header/footer (top nav, profile, logout)
    - Renders the new dashboard in include/staff/dashboard-pro.inc.php

    Original scp/dashboard.php is intentionally left untouched as a backup.
**********************************************************************/
require('staff.inc.php');

// Mark the dashboard tab active in osTicket's top nav so context is preserved
$nav->setTabActive('dashboard');

// Pull in our scoped CSS (loaded only on this page)
$ost->addExtraHeader(
    '<link rel="stylesheet" type="text/css" href="css/custom-dashboard.css"/>'
);

require(STAFFINC_DIR.'header.inc.php');
require_once(STAFFINC_DIR.'dashboard-pro.inc.php');
include(STAFFINC_DIR.'footer.inc.php');
?>
