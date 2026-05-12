<?php
if(!defined('OSTCLIENTINC') || !is_object($thisclient) || !$thisclient->isValid()) die('Access Denied');

$settings = &$_SESSION['client:Q'];

// Unpack search, filter, and sort requests
if (isset($_REQUEST['clear']))
    $settings = array();
if (isset($_REQUEST['keywords'])) {
    $settings['keywords'] = $_REQUEST['keywords'];
}
if (isset($_REQUEST['topic_id'])) {
    $settings['topic_id'] = $_REQUEST['topic_id'];
}
if (isset($_REQUEST['status'])) {
    $settings['status'] = $_REQUEST['status'];
}

$org_tickets = $thisclient->canSeeOrgTickets();
if ($settings['keywords']) {
    // Don't show stat counts for searches
    $openTickets = $closedTickets = -1;
}
elseif ($settings['topic_id']) {
    $openTickets = $thisclient->getNumTopicTicketsInState($settings['topic_id'],
        'open', $org_tickets);
    $closedTickets = $thisclient->getNumTopicTicketsInState($settings['topic_id'],
        'closed', $org_tickets);
}
else {
    $openTickets = $thisclient->getNumOpenTickets($org_tickets);
    $closedTickets = $thisclient->getNumClosedTickets($org_tickets);
}

$tickets = Ticket::objects();

$qs = array();
$status=null;

$sortOptions=array('id'=>'number', 'subject'=>'cdata__subject',
                    'status'=>'status__name', 'dept'=>'dept__name','date'=>'created');
$orderWays=array('DESC'=>'-','ASC'=>'');
//Sorting options...
$order_by=$order=null;
$sort=($_REQUEST['sort'] && $sortOptions[strtolower($_REQUEST['sort'])])?strtolower($_REQUEST['sort']):'date';
if($sort && $sortOptions[$sort])
    $order_by =$sortOptions[$sort];

$order_by=$order_by ?: $sortOptions['date'];
if ($_REQUEST['order'] && !is_null($orderWays[strtoupper($_REQUEST['order'])]))
    $order = $orderWays[strtoupper($_REQUEST['order'])];
else
    $order = $orderWays['DESC'];

$x=$sort.'_sort';
$$x=' class="'.strtolower($_REQUEST['order'] ?: 'desc').'" ';

$basic_filter = Ticket::objects();
if ($settings['topic_id']) {
    $basic_filter = $basic_filter->filter(array('topic_id' => $settings['topic_id']));
}

if ($settings['status'])
    $status = strtolower($settings['status']);
    switch ($status) {
    default:
        $status = 'open';
    case 'open':
    case 'closed':
		$results_type = ($status == 'closed') ? __('Closed Tickets') : __('Open Tickets');
        $basic_filter->filter(array('status__state' => $status));
        break;
}

// Add visibility constraints — use a union query to use multiple indexes,
// use UNION without "ALL" (false as second parameter to union()) to imply
// unique values
$visibility = $basic_filter->copy()
    ->values_flat('ticket_id')
    ->filter(array('user_id' => $thisclient->getId()));

// Add visibility of Tickets where the User is a Collaborator if enabled
if ($cfg->collaboratorTicketsVisibility())
    $visibility = $visibility
    ->union($basic_filter->copy()
        ->values_flat('ticket_id')
        ->filter(array('thread__collaborators__user_id' => $thisclient->getId()))
    , false);

if ($thisclient->canSeeOrgTickets()) {
    $visibility = $visibility->union(
        $basic_filter->copy()->values_flat('ticket_id')
            ->filter(array('user__org_id' => $thisclient->getOrgId()))
    , false);
}

// Perform basic search
if ($settings['keywords']) {
    $q = trim($settings['keywords']);
    if (is_numeric($q)) {
        $tickets->filter(array('number__startswith'=>$q));
    } elseif (strlen($q) > 2) { //Deep search!
        // Use the search engine to perform the search
        $tickets = $ost->searcher->find($q, $tickets);
    }
}

$tickets->distinct('ticket_id');

TicketForm::ensureDynamicDataView();

$total=$visibility->count();
$page=($_GET['p'] && is_numeric($_GET['p']))?$_GET['p']:1;
$pageNav=new Pagenate($total, $page, PAGE_LIMIT);
$qstr = '&amp;'. Http::build_query($qs);
$qs += array('sort' => $_REQUEST['sort'], 'order' => $_REQUEST['order']);
$pageNav->setURL('tickets.php', $qs);
$tickets->filter(array('ticket_id__in' => $visibility));
$pageNav->paginate($tickets);

$showing =$total ? $pageNav->showing() : "";
if(!$results_type)
{
	$results_type=ucfirst($status).' '.__('Tickets');
}
$showing.=($status)?(' '.$results_type):' '.__('All Tickets');
if($search)
    $showing=__('Search Results').": $showing";

$negorder=$order=='-'?'ASC':'DESC'; //Negate the sorting

$tickets->order_by($order.$order_by);
$tickets->values(
    'ticket_id', 'number', 'created', 'isanswered', 'source', 'status_id',
    'status__state', 'status__name', 'cdata__subject', 'dept_id',
    'dept__name', 'dept__ispublic', 'user__default_email__address', 'user_id'
);

?>
<div class="glass-card p-4 mb-4" data-aos="fade-down">
    <div class="row align-items-center">
        <div class="col-md-6 mb-3 mb-md-0">
            <h1 class="fw-bold h3 mb-0">
                <a href="<?php echo Http::refresh_url(); ?>" class="text-decoration-none text-main">
                    <i class="fa-solid fa-rotate me-2 text-primary"></i>
                    <?php echo __('My Tickets'); ?>
                </a>
            </h1>
        </div>
        <div class="col-md-6 text-md-end">
            <div class="d-flex flex-wrap justify-content-md-end gap-2">
                <?php if ($openTickets) { ?>
                    <a class="btn <?php echo ($status == 'open') ? 'btn-primary-saas' : 'btn-light rounded-pill border'; ?> px-4 py-2"
                        href="?<?php echo Http::build_query(array('a' => 'search', 'status' => 'open')); ?>">
                        <?php echo __('Open'); if ($openTickets > 0) echo sprintf(' (%d)', $openTickets); ?>
                    </a>
                <?php } ?>
                <?php if ($closedTickets) { ?>
                    <a class="btn <?php echo ($status == 'closed') ? 'btn-primary-saas' : 'btn-light rounded-pill border'; ?> px-4 py-2"
                        href="?<?php echo Http::build_query(array('a' => 'search', 'status' => 'closed')); ?>">
                        <?php echo __('Closed'); if ($closedTickets > 0) echo sprintf(' (%d)', $closedTickets); ?>
                    </a>
                <?php } ?>
            </div>
        </div>
    </div>

    <div class="mt-4 pt-4 border-top">
        <form action="tickets.php" method="get" id="ticketSearchForm">
            <input type="hidden" name="a"  value="search">
            <div class="row g-3">
                <div class="col-lg-5 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-magnifying-glass text-muted"></i></span>
                        <input type="text" name="keywords" class="form-control modern-form-control border-start-0 ps-0" placeholder="<?php echo __('Search by ticket # or keywords...'); ?>" value="<?php echo Format::htmlchars($settings['keywords']); ?>">
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-white border-end-0"><i class="fa-solid fa-filter text-muted"></i></span>
                        <select name="topic_id" class="form-select modern-form-control border-start-0 ps-0" onchange="javascript: this.form.submit(); ">
                            <option value="">&mdash; <?php echo __('All Help Topics');?> &mdash;</option>
                            <?php
                            foreach (Topic::getHelpTopics(true) as $id=>$name) {
                                $count = $thisclient->getNumTopicTickets($id, $org_tickets);
                                if ($count == 0) continue;
                                ?>
                                <option value="<?php echo $id; ?>" <?php if ($settings['topic_id'] == $id) echo 'selected="selected"'; ?>><?php echo sprintf('%s (%d)', Format::htmlchars($name), $count); ?></option>
                            <?php } ?>
                        </select>
                    </div>
                </div>
                <div class="col-lg-3">
                    <button type="submit" class="btn btn-primary-saas w-100 py-2"><?php echo __('Search');?></button>
                </div>
            </div>
            <?php if ($settings['keywords'] || $settings['topic_id'] || $_REQUEST['sort']) { ?>
                <div class="mt-3">
                    <a href="?clear" class="text-danger small text-decoration-none fw-bold">
                        <i class="fa-solid fa-circle-xmark me-1"></i>
                        <?php echo __('Clear all filters and sort'); ?>
                    </a>
                </div>
            <?php } ?>
        </form>
    </div>
</div>

<!-- Ticket List Grid -->
<div class="ticket-grid mt-4" data-aos="fade-up" data-aos-delay="200">
    <?php
    $subject_field = TicketForm::objects()->one()->getField('subject');
    $defaultDept = Dept::getDefaultDeptName();
    
    if ($tickets->exists(true)) {
        foreach ($tickets as $T) {
            $dept = $T['dept__ispublic'] ? Dept::getLocalById($T['dept_id'], 'name', $T['dept__name']) : $defaultDept;
            $subject = $subject_field->display($subject_field->to_php($T['cdata__subject']) ?: $T['cdata__subject']);
            $status_name = TicketStatus::getLocalById($T['status_id'], 'value', $T['status__name']);
            $ticketNumber = $T['number'];
            $isAnswered = ($T['isanswered'] && !strcasecmp($T['status__state'], 'open'));
            $thisclient->getId() != $T['user_id'] ? $isCollab = true : $isCollab = false;
            ?>
            <div class="glass-card mb-3 p-4 border-start border-4 <?php echo $isAnswered ? 'border-primary' : 'border-light'; ?> transition-all hover-lift shadow-sm">
                <div class="row align-items-center">
                    <div class="col-lg-2 col-md-3 mb-3 mb-md-0">
                        <div class="small text-muted mb-1 x-small"><?php echo __('Ticket #'); ?></div>
                        <a href="tickets.php?id=<?php echo $T['ticket_id']; ?>" class="fw-bold h5 text-primary text-decoration-none">
                            #<?php echo $ticketNumber; ?>
                        </a>
                    </div>
                    <div class="col-lg-5 col-md-9 mb-3 mb-md-0">
                        <div class="d-flex align-items-center gap-2 mb-1">
                            <?php if ($isCollab) { ?> <i class="fa-solid fa-users-viewfinder text-muted small" title="Collaborator"></i> <?php } ?>
                            <span class="badge rounded-pill <?php echo (strtolower($T['status__state']) == 'open') ? 'bg-success-subtle text-success border border-success-subtle' : 'bg-secondary-subtle text-secondary border border-secondary-subtle'; ?> px-3 py-1 small">
                                <?php echo $status_name; ?>
                            </span>
                            <span class="text-muted x-small ms-2"><i class="fa-regular fa-calendar-alt me-1"></i><?php echo Format::date($T['created']); ?></span>
                        </div>
                        <a href="tickets.php?id=<?php echo $T['ticket_id']; ?>" class="text-decoration-none text-main fw-bold d-block text-truncate h5 mb-0">
                            <?php echo $subject; ?>
                        </a>
                    </div>
                    <div class="col-lg-3 col-md-6 mb-3 mb-md-0">
                        <div class="small text-muted mb-1 x-small"><?php echo __('Department'); ?></div>
                        <span class="badge bg-light text-dark border fw-normal px-3 py-2">
                            <i class="fa-solid fa-building-user me-2 text-muted"></i><?php echo $dept; ?>
                        </span>
                    </div>
                    <div class="col-lg-2 col-md-6 text-md-end">
                        <a href="tickets.php?id=<?php echo $T['ticket_id']; ?>" class="btn btn-primary-saas rounded-pill px-4 btn-sm">
                            <?php echo __('View'); ?>
                            <i class="fa-solid fa-chevron-right ms-1"></i>
                        </a>
                    </div>
                </div>
            </div>
        <?php }
    } else { ?>
        <div class="glass-card text-center py-5 shadow-sm">
            <div class="py-4">
                <div class="icon-box mx-auto mb-4" style="width: 80px; height: 80px; font-size: 2rem;">
                    <i class="fa-solid fa-folder-open text-muted"></i>
                </div>
                <h3 class="fw-bold h4 mb-2"><?php echo __('No Tickets Found'); ?></h3>
                <p class="text-muted mb-4"><?php echo __('Your query did not match any records'); ?></p>
                <a href="?clear" class="btn btn-primary-saas px-4"><?php echo __('Clear all filters'); ?></a>
            </div>
        </div>
    <?php } ?>
</div>

<?php if ($total && ($total > PAGE_LIMIT)) { ?>
    <div class="mt-5 d-flex justify-content-center">
        <div class="pagination-wrapper">
            <?php echo $pageNav->getPageLinks(); ?>
        </div>
    </div>
<?php } ?>

