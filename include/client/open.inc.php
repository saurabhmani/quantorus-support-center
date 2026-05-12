<?php
if(!defined('OSTCLIENTINC')) die('Access Denied!');
$info=array();
if($thisclient && $thisclient->isValid()) {
    $info=array('name'=>$thisclient->getName(),
                'email'=>$thisclient->getEmail(),
                'phone'=>$thisclient->getPhoneNumber());
}

$info=($_POST && $errors)?Format::htmlchars($_POST):$info;

$form = null;
if (!$info['topicId']) {
    if (array_key_exists('topicId',$_GET) && preg_match('/^\d+$/',$_GET['topicId']) && Topic::lookup($_GET['topicId']))
        $info['topicId'] = intval($_GET['topicId']);
    else
        $info['topicId'] = $cfg->getDefaultTopicId();
}

$forms = array();
if ($info['topicId'] && ($topic=Topic::lookup($info['topicId']))) {
    foreach ($topic->getForms() as $F) {
        if (!$F->hasAnyVisibleFields())
            continue;
        if ($_POST) {
            $F = $F->instanciate();
            $F->isValidForClient();
        }
        $forms[] = $F->getForm();
    }
}

?>
<style>
.hidden-user-form-fields {
    position: absolute !important;
    width: 1px !important;
    height: 1px !important;
    padding: 0 !important;
    margin: -1px !important;
    overflow: hidden !important;
    clip: rect(0, 0, 0, 0) !important;
    white-space: nowrap !important;
    border: 0 !important;
}
.open-ticket-page .dropzone {
    cursor: pointer !important;
}
/* Native File Input Overlay Fix */
/* This makes the actual hidden input cover the entire custom UI zone */
.open-ticket-page .filedrop {
    position: relative !important;
}
.open-ticket-page .filedrop input[type="file"] {
    position: absolute !important;
    top: 0 !important;
    left: 0 !important;
    width: 100% !important;
    height: 100% !important;
    opacity: 0 !important;
    cursor: pointer !important;
    z-index: 999 !important;
    display: block !important;
}
/* Ensure previews and list items remain clickable/removable */
.open-ticket-page .filedrop .files,
.open-ticket-page .filedrop .files * {
    position: relative !important;
    z-index: 1000 !important;
}
</style>
<div class="open-ticket-page">
    <div class="page-header-block">
        <h1><?php echo __('Open a New Ticket');?></h1>
        <p><?php echo __('Please fill in the form below to open a new ticket. Our enterprise support team will review your request and get back to you shortly.');?></p>
    </div>

    <div class="main-form-card">
        <?php if ($errors) { ?>
            <pre style="background:#fff3f3;padding:20px;border:1px solid #ffb3b3;margin-bottom:20px;border-radius:8px;font-family:monospace;font-size:12px;color:#c00;">
<?php print_r($errors); ?>
            </pre>
        <?php } ?>
        <form id="ticketForm" method="post" action="open.php" enctype="multipart/form-data">
            <?php csrf_token(); ?>
            <input type="hidden" name="a" value="open">

            <!-- Section 1: Contact Information -->
            <div class="form-section-block">
                <div class="section-header-row">
                    <i class="icon-user"></i>
                    <h3><?php echo __('Contact Information'); ?></h3>
                </div>
                <div class="section-divider"></div>
                <div class="section-content-grid">
                    <?php if (!$thisclient) { ?>
                        <div class="form-field-group">
                            <label><?php echo __('Email Address'); ?> <span class="error">*</span></label>
                            <input type="email" name="email" value="<?php echo $info['email']; ?>" placeholder="alex@company.com">
                            <?php if ($errors['email']) { ?><div class="error-msg"><?php echo $errors['email']; ?></div><?php } ?>
                        </div>
                        <div class="form-field-group">
                            <label><?php echo __('Full Name'); ?> <span class="error">*</span></label>
                            <input type="text" name="name" value="<?php echo $info['name']; ?>" placeholder="Alex Johnson">
                            <?php if ($errors['name']) { ?><div class="error-msg"><?php echo $errors['name']; ?></div><?php } ?>
                        </div>
                        <div class="form-field-group">
                            <label><?php echo __('Phone Number'); ?></label>
                            <input type="tel" name="phone" value="<?php echo $info['phone']; ?>" placeholder="+1 (555) 000-0000">
                        </div>
                        <div class="form-field-group">
                            <label><?php echo __('Extension'); ?></label>
                            <input type="text" name="phone_ext" value="<?php echo $_POST['phone_ext']; ?>" placeholder="ext. 123">
                        </div>
                        <div class="hidden-user-form-fields">
                            <?php
                            $userForm = UserForm::getUserForm();
                            if ($userForm) {
                                $uform = $userForm->getForm($_POST);
                                // Map UserForm fields for JS synchronization
                                $userFieldMap = array();
                                foreach ($uform->getFields() as $f) {
                                    if ($f->get('name') == 'email' || (method_exists($f, 'getContactType') && $f->getContactType() == 'email'))
                                        $userFieldMap['email'] = $f->getFormName();
                                    elseif ($f->get('name') == 'name' || (method_exists($f, 'getContactType') && $f->getContactType() == 'name'))
                                        $userFieldMap['name'] = $f->getFormName();
                                    elseif ($f->get('name') == 'phone' || (method_exists($f, 'getContactType') && $f->getContactType() == 'phone'))
                                        $userFieldMap['phone'] = $f->getFormName();
                                }
                                $uform->render(array('staff' => false, 'mode' => 'create'));
                                ?>
                                <script>var userFieldMap = <?php echo json_encode($userFieldMap); ?>;</script>
                            <?php } ?>
                        </div>
                    <?php } else { ?>
                        <div class="form-field-group">
                            <label><?php echo __('Email Address'); ?></label>
                            <input type="text" value="<?php echo $thisclient->getEmail(); ?>" readonly class="readonly-input">
                        </div>
                        <div class="form-field-group">
                            <label><?php echo __('Full Name'); ?></label>
                            <input type="text" value="<?php echo Format::htmlchars($thisclient->getName()); ?>" readonly class="readonly-input">
                        </div>
                    <?php } ?>
                </div>
            </div>

            <!-- Section 2: Help Topic -->
            <div class="form-section-block">
                <div class="section-header-row">
                    <i class="icon-list-ul"></i>
                    <h3><?php echo __('Help Topic'); ?></h3>
                </div>
                <div class="section-divider"></div>
                <div class="form-field-group full-width">
                    <label for="topicId"><?php echo __('What can we help you with?'); ?> <span class="error">*</span></label>
                    <select id="topicId" name="topicId" onchange="javascript:
                            var data = $(':input[name]', '#dynamic-form').serialize();
                            $.ajax(
                              'ajax.php/form/help-topic/' + this.value,
                              {
                                data: data,
                                dataType: 'json',
                                success: function(json) {
                                  $('#dynamic-form').empty().append(json.html);
                                  $(document.head).append(json.media);
                                  // Reinitialize editor for dynamic form
                                  if (typeof initEnterpriseEditor === 'function') {
                                      initEnterpriseEditor();
                                  }
                                }
                              });">
                        <option value="" selected="selected">&mdash; <?php echo __('Select a Help Topic');?> &mdash;</option>
                        <?php
                        if($topics=Topic::getPublicHelpTopics()) {
                            foreach($topics as $id =>$name) {
                                echo sprintf('<option value="%d" %s>%s</option>',
                                        $id, ($info['topicId']==$id)?'selected="selected"':'', $name);
                            }
                        } ?>
                    </select>
                    <?php if ($errors['topicId']) { ?><div class="error-msg"><?php echo $errors['topicId']; ?></div><?php } ?>
                </div>
            </div>

            <!-- Section 3: Ticket Details -->
            <div class="form-section-block" id="ticket-details-container">
                <div class="section-header-row">
                    <i class="icon-file-text"></i>
                    <h3><?php echo __('Ticket Details'); ?></h3>
                </div>
                <div class="section-divider"></div>
                <div id="dynamic-form" class="dynamic-form-modern">
                    <?php
                    $options = array('mode' => 'create');
                    foreach ($forms as $form) {
                        include(CLIENTINC_DIR . 'templates/dynamic-form.tmpl.php');
                    } ?>
                </div>
            </div>

            <?php if($cfg && $cfg->isCaptchaEnabled() && (!$thisclient || !$thisclient->isValid())) { ?>
                <div class="form-section-block">
                    <div class="section-header-row">
                        <i class="icon-shield"></i>
                        <h3><?php echo __('Security Verification'); ?></h3>
                    </div>
                    <div class="section-divider"></div>
                    <div class="captcha-grid">
                        <div class="captcha-img-box">
                            <img src="captcha.php" border="0" align="left">
                        </div>
                        <div class="form-field-group">
                            <label><?php echo __('Verification Code'); ?> <span class="error">*</span></label>
                            <input id="captcha" type="text" name="captcha" size="6" autocomplete="off" placeholder="Enter code">
                            <?php if ($errors['captcha']) { ?><div class="error-msg"><?php echo $errors['captcha']; ?></div><?php } ?>
                        </div>
                    </div>
                </div>
            <?php } ?>

            <div class="form-action-footer">
                <button type="submit" class="btn-submit-premium"><?php echo __('Create Ticket');?></button>
                <button type="button" class="btn-cancel-premium" onclick="javascript:
                    $('.richtext').each(function() {
                        var redactor = $(this).data('redactor');
                        if (redactor && redactor.opts.draftDelete)
                            redactor.plugin.draft.deleteDraft();
                    });
                    window.location.href='index.php';">
                    <?php echo __('Cancel'); ?>
                </button>
            </div>
        </form>
    </div>
</div>

<script type="text/javascript">
$(function() {
    // osTicket Rich Editor Sync Fix
    $('#ticketForm').on('submit', function(e) {
        // --- SYNC CUSTOM FIELDS TO NATIVE USERFORM ---
        if (window.userFieldMap) {
            console.log('Syncing custom fields to native UserForm fields...');
            if (userFieldMap.email) $('[name="' + userFieldMap.email + '"]').val($('input[name="email"]').val());
            if (userFieldMap.name) $('[name="' + userFieldMap.name + '"]').val($('input[name="name"]').val());
            if (userFieldMap.phone) $('[name="' + userFieldMap.phone + '"]').val($('input[name="phone"]').val());
        }

        // --- DEBUG START ---
        console.log('--- Ticket Submission Debug ---');
        console.log('Form Action:', $(this).attr('action'));
        
        // Log hidden attachment inputs
        var attachmentInputs = $(this).find('input[type="hidden"][name^="attach"]');
        console.log('Hidden Attachment Tokens (IDs):', attachmentInputs.length);
        attachmentInputs.each(function() {
            console.log('Attachment ID:', $(this).val());
        });

        // Log files in the .files containers
        console.log('Files in UI list:', $('.filedrop .files .file').length);
        
        // Log FormData entries for multipart verification
        if (window.FormData) {
            var formData = new FormData(this);
            console.log('FormData Entries for Submission:');
            for (var pair of formData.entries()) {
                console.log(pair[0] + ': ' + (pair[1] instanceof File ? 'File: ' + pair[1].name : pair[1]));
            }
        }
        console.log('--- Debug End ---');
        // --- DEBUG END ---

        // Sync Redactor instances
        $('.richtext').each(function() {
            var redactor = $(this).data('redactor');
            if (redactor && redactor.source && typeof redactor.source.sync === 'function') {
                redactor.source.sync();
            } else if ($.isFunction($(this).redactor)) {
                try { $(this).redactor('source.sync'); } catch (e) {}
            }
        });
        
        // Sync TinyMCE instances
        if (window.tinymce && typeof tinymce.triggerSave === 'function') {
            tinymce.triggerSave();
        }
        
        return true;
    });

    // --- NATIVE UPLOAD DETECTION ---
    $(document).on('change', 'input[type="file"]', function() {
        console.log('--- File Selection Detected ---');
        console.log('Input ID:', this.id);
        console.log('Files selected:', this.files.length);
        for(var i=0; i<this.files.length; i++) {
            console.log(' - File:', this.files[i].name, '(' + this.files[i].size + ' bytes)');
        }
    });

    // Monitor AJAX upload behavior for debugging
    $(document).on('change', 'input[type="file"]', function() {
        console.log('Native input change detected:', this.id, 'Files:', this.files.length);
    });

    // Log when attachment tokens are injected into the form by the AJAX uploader
    var observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            for (var i = 0; i < mutation.addedNodes.length; i++) {
                var node = mutation.addedNodes[i];
                if (node.nodeType === 1 && $(node).is('input[type="hidden"][name^="attach"]')) {
                    console.log('AJAX Upload Success: New attachment ID received:', $(node).val());
                }
            }
        });
    });
    
    var ticketForm = document.getElementById('ticketForm');
    if (ticketForm) {
        observer.observe(ticketForm, { childList: true, subtree: true });
    }
});
</script>
