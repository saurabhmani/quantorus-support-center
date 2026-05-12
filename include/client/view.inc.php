<?php
if(!defined('OSTCLIENTINC') || !$thisclient || !$ticket || !$ticket->checkUserAccess($thisclient)) die('Access Denied!');

$info=($_POST && $errors)?Format::htmlchars($_POST):array();
$type = array('type' => 'viewed');
Signal::send('object.view', $ticket, $type);

$dept = $ticket->getDept();

if ($ticket->isClosed() && !$ticket->isReopenable())
    $warn = sprintf(__('%s is marked as closed and cannot be reopened.'), __('This ticket'));

if(!$dept || !$dept->isPublic())
    $dept = $cfg->getDefaultDept();

if ($thisclient && $thisclient->isGuest() && $cfg->isClientRegistrationEnabled()) { ?>
    <div class="qs-guest-warning">
        <div class="qs-warning-content">
            <i class="icon-compass"></i>
            <div class="qs-warning-text">
                <strong><?php echo __('Looking for your other tickets?'); ?></strong>
                <p><?php echo sprintf(__(' %s Sign In %s or %s register for an account %s for the best experience.'),
                    '<a href="login.php?e='.urlencode($thisclient->getEmail()).'">','</a>',
                    '<a href="account.php?do=create">','</a>'); ?></p>
            </div>
        </div>
    </div>
<?php } ?>

<div class="ticket-view-page">
    <!-- Hero Section -->
    <header class="ticket-hero">
        <div class="hero-left">
            <div class="ticket-number-badge">#<?php echo $ticket->getNumber(); ?></div>
            <h1>
                <?php $subject_field = TicketForm::getInstance()->getField('subject');
                echo $subject_field->display($ticket->getSubject()); ?>
            </h1>
        </div>
        <div class="hero-actions">
            <a class="btn-action-outline" href="tickets.php?a=print&id=<?php echo $ticket->getId(); ?>">
                <i class="icon-print"></i> <?php echo __('Print'); ?>
            </a>
            <?php if ($ticket->hasClientEditableFields() && $thisclient->getId() == $ticket->getUserId()) { ?>
                <a class="btn-action-outline" href="tickets.php?a=edit&id=<?php echo $ticket->getId(); ?>">
                    <i class="icon-edit"></i> <?php echo __('Edit'); ?>
                </a>
            <?php } ?>
            <a class="btn-action-refresh" href="tickets.php?id=<?php echo $ticket->getId(); ?>" title="<?php echo __('Reload'); ?>">
                <i class="icon-refresh"></i>
            </a>
        </div>
    </header>

    <div class="ticket-layout">
        <!-- Main Content Area -->
        <div class="ticket-main">
            
            <!-- Information Grid -->
            <div class="ticket-info-grid">
                <div class="info-card">
                    <div class="info-card-header">
                        <i class="icon-info-sign"></i>
                        <h3><?php echo __('Ticket Details'); ?></h3>
                    </div>
                    <div class="info-details">
                        <div class="detail-row">
                            <span class="label"><?php echo __('Status');?></span>
                            <span class="value status-badge <?php echo strtolower($ticket->getStatus()); ?>">
                                <?php echo ($S = $ticket->getStatus()) ? $S->getLocalName() : ''; ?>
                            </span>
                        </div>
                        <div class="detail-row">
                            <span class="label"><?php echo __('Department');?></span>
                            <span class="value"><?php echo Format::htmlchars($dept instanceof Dept ? $dept->getName() : ''); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label"><?php echo __('Create Date');?></span>
                            <span class="value"><?php echo Format::datetime($ticket->getCreateDate()); ?></span>
                        </div>
                    </div>
                </div>

                <div class="info-card">
                    <div class="info-card-header">
                        <i class="icon-user"></i>
                        <h3><?php echo __('User Contact'); ?></h3>
                    </div>
                    <div class="info-details">
                        <div class="detail-row">
                            <span class="label"><?php echo __('Name');?></span>
                            <span class="value"><?php echo mb_convert_case(Format::htmlchars($ticket->getName()), MB_CASE_TITLE); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label"><?php echo __('Email');?></span>
                            <span class="value"><?php echo Format::htmlchars($ticket->getEmail()); ?></span>
                        </div>
                        <div class="detail-row">
                            <span class="label"><?php echo __('Phone');?></span>
                            <span class="value"><?php echo $ticket->getPhoneNumber() ?: '--'; ?></span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Custom Data Sections -->
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
            foreach ($sections as $i=>$answers) { ?>
                <div class="custom-data-card">
                    <div class="info-card-header">
                        <i class="icon-list-alt"></i>
                        <h3><?php echo $forms[$i]; ?></h3>
                    </div>
                    <div class="custom-details-grid">
                        <?php foreach ($answers as $A) { list($v, $a) = $A; ?>
                            <div class="detail-item">
                                <span class="label"><?php echo $a->getField()->get('label'); ?></span>
                                <span class="value"><?php echo $v; ?></span>
                            </div>
                        <?php } ?>
                    </div>
                </div>
            <?php } ?>

            <!-- Ticket Thread -->
            <div class="thread-container">
                <div class="thread-header">
                    <i class="icon-comments-alt"></i>
                    <h2><?php echo __('Conversation Thread'); ?></h2>
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

            <!-- Reply Form -->
            <?php
            if ($blockReply = $ticket->isChild() && $ticket->getMergeType() != 'visual')
                $warn = sprintf(__('This Ticket is Merged into another Ticket. Please go to the %s%d%s to reply.'),
                    '<a href="tickets.php?id=', $ticket->getPid(), '" style="text-decoration:underline">Parent</a>');

            if ((!$ticket->isClosed() || $ticket->isReopenable()) && !$blockReply) { ?>
                <div class="reply-box-modern">
                    <form id="reply" action="tickets.php?id=<?php echo $ticket->getId(); ?>#reply" name="reply" method="post" enctype="multipart/form-data">
                        <?php csrf_token(); ?>
                        <div class="reply-header">
                            <i class="icon-reply"></i>
                            <h2><?php echo __('Post a Reply');?></h2>
                        </div>
                        <input type="hidden" name="id" value="<?php echo $ticket->getId(); ?>">
                        <input type="hidden" name="a" value="reply">
                        
                        <div class="reply-content">
                            <p class="helper-text"><?php echo __('To best assist you, we request that you be specific and detailed'); ?></p>
                            <?php if($errors['message']) { ?>
                                <div class="error-msg"><?php echo $errors['message']; ?></div>
                            <?php } ?>
                            
                            <div class="textarea-wrapper">
                                <textarea name="<?php echo $messageField->getFormName(); ?>" id="message" cols="50" rows="9" wrap="soft"
                                    class="<?php if ($cfg->isRichTextEnabled()) echo 'richtext'; ?> draft" <?php
                                    list($draft, $attrs) = Draft::getDraftAndDataAttrs('ticket.client', $ticket->getId(), $info['message']);
                                    echo $attrs; ?>><?php echo $draft ?: $info['message']; ?></textarea>
                            </div>

                            <div class="attachment-area">
                                <?php if ($messageField->isAttachmentsEnabled()) {
                                    print $attachments->render(array('client'=>true));
                                } ?>
                            </div>

                            <?php if ($ticket->isClosed() && $ticket->isReopenable()) { ?>
                                <div class="reopen-notice">
                                    <i class="icon-warning-sign"></i>
                                    <span><?php echo __('Ticket will be reopened on message post'); ?></span>
                                </div>
                            <?php } ?>

                            <div class="form-actions">
                                <button type="submit" class="btn-primary"><?php echo __('Post Reply');?></button>
                                <button type="reset" class="btn-secondary"><?php echo __('Reset');?></button>
                            </div>
                        </div>
                    </form>
                </div>
            <?php } ?>
        </div>
    </div>
</div>

<style>
.ticket-view-page {
    max-width: 1100px !important;
    margin: 40px auto !important;
    animation: fadeIn 0.6s ease-out;
}

/* Guest Warning */
.qs-guest-warning {
    background: #fffbeb !important;
    border: 1px solid #fde68a !important;
    border-radius: 16px !important;
    padding: 20px 24px !important;
    margin-bottom: 32px !important;
    max-width: 1100px !important;
    margin-left: auto !important;
    margin-right: auto !important;
}

.qs-warning-content {
    display: flex !important;
    gap: 16px !important;
    align-items: flex-start !important;
}

.qs-warning-content i {
    font-size: 24px !important;
    color: #d97706 !important;
}

.qs-warning-text strong {
    display: block !important;
    color: #92400e !important;
    margin-bottom: 4px !important;
}

.qs-warning-text p {
    margin: 0 !important;
    font-size: 14px !important;
    color: #b45309 !important;
}

.qs-warning-text a {
    color: #d97706 !important;
    font-weight: 700 !important;
    text-decoration: underline !important;
}

/* Hero Section */
.ticket-hero {
    display: flex !important;
    justify-content: space-between !important;
    align-items: center !important;
    margin-bottom: 40px !important;
    padding-bottom: 24px !important;
    border-bottom: 1px solid #eef2f7 !important;
}

.hero-left h1 {
    font-size: 32px !important;
    font-weight: 800 !important;
    color: #0f172a !important;
    margin: 0 !important;
    letter-spacing: -1px !important;
}

.ticket-number-badge {
    display: inline-block !important;
    background: #f1f5f9 !important;
    color: #64748b !important;
    padding: 4px 12px !important;
    border-radius: 99px !important;
    font-size: 13px !important;
    font-weight: 700 !important;
    margin-bottom: 8px !important;
}

.hero-actions {
    display: flex !important;
    gap: 12px !important;
    align-items: center !important;
}

.btn-action-outline {
    height: 44px !important;
    padding: 0 20px !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    background: #fff !important;
    color: #475569 !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    display: flex !important;
    align-items: center !important;
    gap: 8px !important;
    text-decoration: none !important;
    transition: all 0.2s !important;
}

.btn-action-outline:hover {
    background: #f8fafc !important;
    border-color: #cbd5e1 !important;
}

.btn-action-refresh {
    width: 44px !important;
    height: 44px !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 12px !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #64748b !important;
    background: #fff !important;
    text-decoration: none !important;
}

/* Info Cards */
.ticket-info-grid {
    display: grid !important;
    grid-template-columns: 1fr 1fr !important;
    gap: 24px !important;
    margin-bottom: 32px !important;
}

.info-card, .custom-data-card {
    background: #fff !important;
    border: 1px solid #eef2f7 !important;
    border-radius: 20px !important;
    padding: 24px !important;
    box-shadow: 0 4px 12px rgba(15,23,42,0.02) !important;
}

.info-card-header {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    margin-bottom: 20px !important;
    padding-bottom: 16px !important;
    border-bottom: 1px solid #f8fafc !important;
}

.info-card-header i {
    color: #2563eb !important;
    font-size: 18px !important;
}

.info-card-header h3 {
    font-size: 18px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    margin: 0 !important;
}

.detail-row {
    display: flex !important;
    justify-content: space-between !important;
    padding: 10px 0 !important;
}

.detail-row .label {
    font-size: 14px !important;
    color: #64748b !important;
    font-weight: 500 !important;
}

.detail-row .value {
    font-size: 14px !important;
    color: #0f172a !important;
    font-weight: 600 !important;
}

.status-badge {
    padding: 4px 12px !important;
    border-radius: 99px !important;
    text-transform: capitalize !important;
    background: #f1f5f9 !important;
    color: #475569 !important;
}

.status-badge.open { background: #dcfce7 !important; color: #166534 !important; }
.status-badge.closed { background: #f1f5f9 !important; color: #475569 !important; }

/* Custom Details Grid */
.custom-data-card { margin-bottom: 32px !important; }
.custom-details-grid {
    display: grid !important;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr)) !important;
    gap: 20px !important;
}

.detail-item {
    display: flex !important;
    flex-direction: column !important;
    gap: 4px !important;
}

.detail-item .label { font-size: 13px !important; color: #64748b !important; font-weight: 600 !important; }
.detail-item .value { font-size: 15px !important; color: #0f172a !important; font-weight: 500 !important; }

/* Thread Styling */
.thread-container {
    background: #fff !important;
    border: 1px solid #eef2f7 !important;
    border-radius: 20px !important;
    padding: 32px !important;
    margin-bottom: 32px !important;
}

.thread-header {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    margin-bottom: 24px !important;
}

.thread-header i { font-size: 20px !important; color: #2563eb !important; }
.thread-header h2 { font-size: 20px !important; font-weight: 700 !important; color: #0f172a !important; margin: 0 !important; }

/* Reply Box */
.reply-box-modern {
    background: #fff !important;
    border: 1px solid #eef2f7 !important;
    border-radius: 24px !important;
    padding: 32px !important;
    box-shadow: 0 10px 30px rgba(15,23,42,0.04) !important;
}

.reply-header {
    display: flex !important;
    align-items: center !important;
    gap: 12px !important;
    margin-bottom: 24px !important;
}

.reply-header i { font-size: 20px !important; color: #2563eb !important; }
.reply-header h2 { font-size: 20px !important; font-weight: 700 !important; color: #0f172a !important; margin: 0 !important; }

.helper-text { font-size: 14px !important; color: #64748b !important; margin-bottom: 16px !important; }

.textarea-wrapper { margin-bottom: 20px !important; }

.reopen-notice {
    background: #fff7ed !important;
    border-radius: 12px !important;
    padding: 12px 16px !important;
    display: flex !important;
    align-items: center !important;
    gap: 10px !important;
    margin-bottom: 24px !important;
    color: #c2410c !important;
    font-size: 14px !important;
    font-weight: 600 !important;
}

.form-actions { display: flex !important; gap: 12px !important; margin-top: 24px !important; }

.btn-primary {
    height: 54px !important;
    padding: 0 32px !important;
    background: #2563eb !important;
    color: #fff !important;
    border-radius: 14px !important;
    border: none !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    cursor: pointer !important;
    transition: all 0.2s !important;
}

.btn-primary:hover { background: #1d4ed8 !important; transform: translateY(-1px) !important; }

.btn-secondary {
    height: 54px !important;
    padding: 0 24px !important;
    background: #f1f5f9 !important;
    color: #475569 !important;
    border-radius: 14px !important;
    border: none !important;
    font-size: 14px !important;
    font-weight: 600 !important;
    cursor: pointer !important;
}

@media (max-width: 768px) {
    .ticket-info-grid { grid-template-columns: 1fr !important; }
    .hero-actions { display: none !important; }
    .ticket-view-page { padding: 0 20px !important; }
}
</style>

<script type="text/javascript">
<?php
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
