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
<div class="open-ticket-page">
    <div class="page-header-block">
        <h1><?php echo __('Open a New Ticket');?></h1>
        <p><?php echo __('Please fill in the form below to open a new ticket. Our enterprise support team will review your request and get back to you shortly.');?></p>
    </div>

    <div class="main-form-card">
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
                        <div style="display:none;">
                            <?php
                            $uform = UserForm::getUserForm()->getForm($_POST);
                            $uform->render(array('staff' => false, 'mode' => 'create'));
                            ?>
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
