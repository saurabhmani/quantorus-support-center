<?php
global $cfg;
$entryTypes = ThreadEntry::getTypes();
$user = $entry->getUser() ?: $entry->getStaff();
if ($entry->staff && $cfg->hideStaffName())
    $name = __('Staff');
else
    $name = $user ? $user->getName() : $entry->poster;

$avatar = '';
if ($cfg->isAvatarsEnabled() && $user)
    $avatar = $user->getAvatar();

$type = $entryTypes[$entry->type];
$isStaff = ($entry->type == 'R'); // Response (Staff)
?>

<div class="thread-entry-wrapper mb-4 <?php echo $isStaff ? 'staff-response' : 'client-message'; ?>" data-aos="fade-up">
    <div class="d-flex <?php echo $isStaff ? 'flex-row-reverse' : 'flex-row'; ?> gap-3">
        <!-- Avatar -->
        <?php if ($avatar) { ?>
            <div class="flex-shrink-0">
                <div class="avatar-container shadow-sm rounded-circle overflow-hidden border border-2 border-white" style="width: 45px; height: 45px;">
                    <?php echo $avatar; ?>
                </div>
            </div>
        <?php } else { ?>
            <div class="flex-shrink-0">
                <div class="avatar-placeholder rounded-circle d-flex align-items-center justify-content-center shadow-sm border border-2 border-white" 
                     style="width: 45px; height: 45px; background: <?php echo $isStaff ? 'var(--primary-blue)' : '#e9ecef'; ?>; color: <?php echo $isStaff ? 'white' : '#6c757d'; ?>;">
                    <i class="fa-solid <?php echo $isStaff ? 'fa-user-tie' : 'fa-user'; ?>"></i>
                </div>
            </div>
        <?php } ?>

        <!-- Message Bubble -->
        <div class="message-bubble-container flex-grow-1" style="max-width: 85%;">
            <div class="bubble-header d-flex align-items-center justify-content-between mb-2 px-2">
                <div class="d-flex align-items-center gap-2">
                    <span class="fw-bold small text-main"><?php echo $name; ?></span>
                    <?php if ($isStaff) { ?>
                        <span class="badge bg-primary-subtle text-primary border border-primary-subtle x-small px-2 py-1">Support Team</span>
                    <?php } ?>
                </div>
                <div class="text-muted x-small">
                    <i class="fa-regular fa-clock me-1"></i>
                    <time datetime="<?php echo date(DateTime::W3C, Misc::db2gmtime($entry->created)); ?>" title="<?php echo Format::daydatetime($entry->created); ?>">
                        <?php echo Format::datetime($entry->created); ?>
                    </time>
                    <?php if ($entry->flags & ThreadEntry::FLAG_EDITED) { ?>
                        <span class="ms-2 badge bg-light text-muted fw-normal border">Edited</span>
                    <?php } ?>
                </div>
            </div>

            <div class="glass-card p-4 <?php echo $isStaff ? 'bg-primary-subtle border-primary-subtle' : 'bg-white'; ?> shadow-sm">
                <div class="thread-body" id="thread-id-<?php echo $entry->getId(); ?>">
                    <div class="message-content text-main" style="line-height: 1.6;">
                        <?php echo $entry->getBody()->toHtml(); ?>
                    </div>
                    
                    <?php if ($entry->has_attachments) { ?>
                        <div class="attachments-container mt-4 pt-3 border-top">
                            <div class="row g-2">
                                <?php foreach ($entry->attachments as $A) {
                                    if ($A->inline) continue;
                                    $size = $A->file->size ? Format::file_size($A->file->size) : '';
                                ?>
                                    <div class="col-md-6 col-lg-4">
                                        <a href="<?php echo $A->file->getDownloadUrl(['id' => $A->getId()]); ?>" 
                                           class="attachment-item d-flex align-items-center gap-2 p-2 rounded-3 bg-light border text-decoration-none transition-all hover-lift"
                                           target="_blank" download="<?php echo Format::htmlchars($A->getFilename()); ?>">
                                            <div class="icon-box bg-white mb-0" style="width: 32px; height: 32px; font-size: 0.9rem;">
                                                <i class="fa-solid fa-paperclip text-primary"></i>
                                            </div>
                                            <div class="overflow-hidden">
                                                <div class="text-main small fw-bold text-truncate" title="<?php echo Format::htmlchars($A->getFilename()); ?>">
                                                    <?php echo Format::htmlchars($A->getFilename()); ?>
                                                </div>
                                                <div class="text-muted x-small"><?php echo $size; ?></div>
                                            </div>
                                        </a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>

<?php if ($urls = $entry->getAttachmentUrls()) { ?>
    <script type="text/javascript">
        $('#thread-id-<?php echo $entry->getId(); ?>')
            .data('urls', <?php echo JsonDataEncoder::encode($urls); ?>)
            .data('id', <?php echo $entry->getId(); ?>);
    </script>
<?php } ?>
