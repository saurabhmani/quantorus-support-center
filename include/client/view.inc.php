<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');

$info=($_POST && $errors)?Format::htmlchars($_POST):array();

$type = array('type' => 'viewed');
Signal::send('object.view', $ticket, $type);

$dept = $ticket->getDept();

if ($ticket->isClosed() && !$ticket->isReopenable())
    $warn = sprintf(__('%s is marked as closed and cannot be reopened.'), __('This ticket'));

//Making sure we don't leak out internal dept names
if(!$dept || !$dept->isPublic())
    $dept = $cfg->getDefaultDept();

if ($thisclient && $thisclient->isGuest()
    && $cfg->isClientRegistrationEnabled()) { ?>

<div id="msg_info">
    <i class="icon-compass icon-2x pull-left"></i>
    <strong><?php echo __('Looking for your other tickets?'); ?></strong><br />
    <a href="<?php echo ROOT_PATH; ?>login.php?e=<?php
        echo urlencode($thisclient->getEmail());
    ?>" style="text-decoration:underline"><?php echo __('Sign In'); ?></a>
    <?php echo sprintf(__('or %s register for an account %s for the best experience on our help desk.'),
        '<a href="account.php?do=create" style="text-decoration:underline">','</a>'); ?>
    </div>

<?php } ?>

<div class="glass-card p-4 mb-4" data-aos="fade-down">
    <div class="row align-items-center">
        <div class="col-md-8">
            <div class="d-flex align-items-center gap-3 mb-2">
                <span class="badge bg-primary px-3 py-2 rounded-pill">#<?php echo $ticket->getNumber(); ?></span>
                <span class="badge <?php echo ($S = $ticket->getStatus()) && $S->getState() == 'open' ? 'bg-success' : 'bg-secondary'; ?> px-3 py-2 rounded-pill">
                    <?php echo $S ? $S->getLocalName() : ''; ?>
                </span>
            </div>
            <h1 class="fw-bold h2 mb-0">
                <?php $subject_field = TicketForm::getInstance()->getField('subject');
                    echo $subject_field->display($ticket->getSubject()); ?>
            </h1>
        </div>
        <div class="col-md-4 text-md-end mt-3 mt-md-0">
            <div class="d-flex flex-wrap justify-content-md-end gap-2">
                <a class="btn btn-light border rounded-pill px-3" href="tickets.php?a=print&id=<?php echo $ticket->getId(); ?>">
                    <i class="fa-solid fa-print me-2"></i><?php echo __('Print'); ?>
                </a>
                <?php if ($ticket->hasClientEditableFields() && $thisclient->getId() == $ticket->getUserId()) { ?>
                    <a class="btn btn-primary-saas rounded-pill px-3" href="tickets.php?a=edit&id=<?php echo $ticket->getId(); ?>">
                        <i class="fa-solid fa-pen-to-square me-2"></i><?php echo __('Edit'); ?>
                    </a>
                <?php } ?>
                <a href="tickets.php?id=<?php echo $ticket->getId(); ?>" class="btn btn-light border rounded-pill px-3">
                    <i class="fa-solid fa-rotate"></i>
                </a>
            </div>
        </div>
    </div>
</div>

<?php if ($thisclient && $thisclient->isGuest() && $cfg->isClientRegistrationEnabled()) { ?>
    <div class="alert alert-info border-0 shadow-sm rounded-4 p-4 mb-4" data-aos="fade-up">
        <div class="d-flex align-items-center gap-3">
            <div class="icon-box bg-white mb-0" style="width: 50px; height: 50px; color: var(--primary-blue);">
                <i class="fa-solid fa-compass"></i>
            </div>
            <div>
                <h5 class="fw-bold mb-1"><?php echo __('Looking for your other tickets?'); ?></h5>
                <p class="mb-0 small">
                    <a href="<?php echo ROOT_PATH; ?>login.php?e=<?php echo urlencode($thisclient->getEmail()); ?>" class="fw-bold"><?php echo __('Sign In'); ?></a>
                    <?php echo sprintf(__('or %s register for an account %s for the best experience.'),
                        '<a href="account.php?do=create" class="fw-bold">','</a>'); ?>
                </p>
            </div>
        </div>
    </div>
<?php } ?>

<div class="row g-4 mb-4">
    <div class="col-lg-6" data-aos="fade-right">
        <div class="support-card p-4">
            <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="fa-solid fa-circle-info me-2 text-primary"></i><?php echo __('Ticket Details'); ?></h5>
            <div class="d-flex flex-column gap-3">
                <div class="d-flex justify-content-between">
                    <span class="text-muted small"><?php echo __('Department');?>:</span>
                    <span class="fw-semibold"><?php echo Format::htmlchars($dept instanceof Dept ? $dept->getName() : ''); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small"><?php echo __('Create Date');?>:</span>
                    <span class="fw-semibold"><?php echo Format::datetime($ticket->getCreateDate()); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small"><?php echo __('Last Update');?>:</span>
                    <span class="fw-semibold"><?php echo Format::datetime($ticket->getLastMsgDate()); ?></span>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-6" data-aos="fade-left">
        <div class="support-card p-4">
            <h5 class="fw-bold mb-4 border-bottom pb-2"><i class="fa-solid fa-user me-2 text-primary"></i><?php echo __('Requester'); ?></h5>
            <div class="d-flex flex-column gap-3">
                <div class="d-flex justify-content-between">
                    <span class="text-muted small"><?php echo __('Name');?>:</span>
                    <span class="fw-semibold"><?php echo mb_convert_case(Format::htmlchars($ticket->getName()), MB_CASE_TITLE); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small"><?php echo __('Email');?>:</span>
                    <span class="fw-semibold"><?php echo Format::htmlchars($ticket->getEmail()); ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span class="text-muted small"><?php echo __('Phone');?>:</span>
                    <span class="fw-semibold"><?php echo $ticket->getPhoneNumber(); ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Custom Data -->
<?php
$sections = $forms = array();
foreach (DynamicFormEntry::forTicket($ticket->getId()) as $i=>$form) {
    $answers = $form->getAnswers()->exclude(Q::any(array(
        'field__flags__hasbit' => DynamicFormField::FLAG_EXT_STORED,
        'field__name__in' => array('subject', 'priority'),
        Q::not(array('field__flags__hasbit' => DynamicFormField::FLAG_CLIENT_VIEW)),
    )));
    foreach ($answers as $j=>$a) {
        if ($v = $a->display())
            $sections[$i][$j] = array($v, $a);
    }
    $forms[$i] = $form->getTitle();
}
if (count($sections)) { ?>
    <div class="glass-card p-4 mb-4" data-aos="fade-up">
        <?php foreach ($sections as $i=>$answers) { ?>
            <h5 class="fw-bold mb-4 border-bottom pb-2"><?php echo $forms[$i]; ?></h5>
            <div class="row g-3 mb-4">
                <?php foreach ($answers as $A) { list($v, $a) = $A; ?>
                    <div class="col-md-6">
                        <div class="d-flex justify-content-between p-2 bg-light rounded-3 border-white border">
                            <span class="text-muted small"><?php echo $a->getField()->get('label'); ?>:</span>
                            <span class="fw-semibold"><?php echo $v; ?></span>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
<?php } ?>

<div class="ticket-thread mt-5" data-aos="fade-up">
    <div class="d-flex align-items-center gap-3 mb-4">
        <div style="width: 40px; height: 4px; background: var(--primary-blue); border-radius: 10px;"></div>
        <h2 class="fw-bold h4 mb-0"><?php echo __('Conversation History'); ?></h2>
    </div>
    
    <?php
    $email = $thisclient->getUserName();
    $clientId = TicketUser::lookupByEmail($email)->getId();
    $ticket->getThread()->render(array('M', 'R', 'user_id' => $clientId), array(
        'mode' => Thread::MODE_CLIENT,
        'html-id' => 'ticketThread')
    );
    ?>
</div>

<?php if ((!$ticket->isClosed() || $ticket->isReopenable()) && !$blockReply) { ?>
    <div class="glass-card p-5 mt-5 border-primary border-top border-4" id="reply-section" data-aos="fade-up">
        <form id="reply" action="tickets.php?id=<?php echo $ticket->getId(); ?>#reply" name="reply" method="post" enctype="multipart/form-data">
            <?php csrf_token(); ?>
            <div class="d-flex align-items-center gap-3 mb-4">
                <div class="icon-box mb-0" style="width: 45px; height: 45px; background: rgba(43, 179, 243, 0.1); color: var(--primary-blue);">
                    <i class="fa-solid fa-reply"></i>
                </div>
                <h2 class="fw-bold h4 mb-0"><?php echo __('Post a Reply');?></h2>
            </div>
            
            <input type="hidden" name="id" value="<?php echo $ticket->getId(); ?>">
            <input type="hidden" name="a" value="reply">
            
            <div class="mb-4">
                <p class="text-muted small mb-3">
                    <i class="fa-solid fa-circle-info me-2"></i>
                    <?php echo __('To best assist you, we request that you be specific and detailed'); ?>
                </p>
                <div class="modern-editor-wrapper rounded-4 overflow-hidden border">
                    <textarea name="<?php echo $messageField->getFormName(); ?>" id="message" cols="50" rows="9" wrap="soft"
                        class="form-control border-0 shadow-none <?php if ($cfg->isRichTextEnabled()) echo 'richtext'; ?> draft" <?php
                        list($draft, $attrs) = Draft::getDraftAndDataAttrs('ticket.client', $ticket->getId(), $info['message']);
                        echo $attrs; ?>><?php echo $draft ?: $info['message']; ?></textarea>
                </div>
                <div class="text-danger small mt-1"><?php echo $errors['message']; ?></div>
                
                <?php if ($messageField->isAttachmentsEnabled()) { ?>
                    <div class="mt-4 p-4 bg-light rounded-4 border border-dashed text-center">
                        <?php print $attachments->render(array('client'=>true)); ?>
                    </div>
                <?php } ?>
            </div>

            <?php if ($ticket->isClosed() && $ticket->isReopenable()) { ?>
                <div class="alert alert-warning rounded-pill px-4 py-2 small mb-4">
                    <i class="fa-solid fa-triangle-exclamation me-2"></i>
                    <?php echo __('Ticket will be reopened on message post'); ?>
                </div>
            <?php } ?>

            <div class="d-flex flex-wrap gap-3">
                <button type="submit" class="btn btn-primary-saas px-5 py-3">
                    <i class="fa-solid fa-paper-plane me-2"></i>
                    <?php echo __('Post Reply');?>
                </button>
                <button type="reset" class="btn btn-light rounded-pill px-4"><?php echo __('Reset');?></button>
                <button type="button" class="btn btn-outline-secondary rounded-pill px-4" onClick="history.go(-1)"><?php echo __('Cancel');?></button>
            </div>
        </form>
    </div>
<?php } ?>
<script type="text/javascript">
<?php
// Hover support for all inline images
$urls = array();
foreach (AttachmentFile::objects()->filter(array(
    'attachments__thread_entry__thread__id' => $ticket->getThreadId(),
    'attachments__inline' => true,
)) as $file) {
    $urls[strtolower($file->getKey())] = array(
        'download_url' => $file->getDownloadUrl(['type' => 'H']),
        'filename' => $file->name,
    );
} ?>
showImagesInline(<?php echo JsonDataEncoder::encode($urls); ?>);
</script>
