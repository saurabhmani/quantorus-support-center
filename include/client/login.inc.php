<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');

$email=Format::input($_POST['luser']?:$_GET['e']);
$passwd=Format::input($_POST['lpasswd']?:$_GET['t']);

$content = Page::lookupByType('banner-client');

if ($content) {
    list($title, $body) = $ost->replaceTemplateVariables(
        array($content->getLocalName(), $content->getLocalBody()));
} else {
    $title = __('Sign In');
    $body = __('To better serve you, we encourage our clients to register for an account and verify the email address we have on record.');
}

?>

<div class="qs-login-page">
    <div class="qs-login-container">

        <div class="qs-login-hero">
    <h1><?php echo __('Check Ticket Status'); ?></h1>

    <p>
        <?php echo __('Please provide your email address and ticket number.'); ?>
        <br>
        <?php echo __('An access link will be emailed to you securely.'); ?>
    </p>
</div>

        <!-- Main Shell -->
        <div class="qs-login-shell">
            <!-- Main Card -->
            <div class="qs-login-card">

                <!-- LEFT SIDE: Form Panel -->
                <div class="qs-login-left">
                    <h2><?php echo __('Client Sign In'); ?></h2>
                    <p class="qs-subtext"><?php echo __('Enter your credentials below to continue.'); ?></p>

                    <?php if ($errors['login']) { ?>
                        <div class="qs-login-error">
                            <i class="icon-warning-sign"></i>
                            <span><?php echo Format::htmlchars($errors['login']); ?></span>
                        </div>
                    <?php } ?>

                    <form action="login.php" method="post" id="clientLogin">
                        <?php csrf_token(); ?>
                        
                        <div class="qs-input-group">
                            <label for="username"><?php echo __('Email or Username'); ?></label>
                            <input id="username" placeholder="<?php echo __('e.g. john.doe@company.com'); ?>" type="text" name="luser" value="<?php echo $email; ?>" class="nowarn">
                        </div>

                        <div class="qs-input-group">
                            <label for="passwd"><?php echo __('Password'); ?></label>
                            <input id="passwd" placeholder="<?php echo __('••••••••'); ?>" type="password" name="lpasswd" maxlength="128" value="<?php echo $passwd; ?>" class="nowarn">
                        </div>

                        <div class="qs-form-actions">
                            <input class="btn" type="submit" value="<?php echo __('Sign In'); ?>">
                            
                            <?php if ($suggest_pwreset) { ?>
                                <div class="qs-pwreset-link">
                                    <a href="pwreset.php"><?php echo __('Forgot My Password?'); ?></a>
                                </div>
                            <?php } ?>
                        </div>
                    </form>
                </div>

                <!-- RIGHT SIDE: Info Panel -->
                <div class="qs-login-right">
                    <div class="qs-lock-icon">
                        <i class="icon-lock"></i>
                    </div>

                    <h3><?php echo __('Enterprise Secure Access'); ?></h3>

                    <p>
                        <?php echo __('Your support portal is protected with enterprise-grade authentication and secure ticket encryption.'); ?>
                    </p>

                    <div class="qs-side-links">
                        <?php
                        $ext_bks = array();
                        foreach (UserAuthenticationBackend::allRegistered() as $bk)
                            if ($bk instanceof ExternalAuthentication)
                                $ext_bks[] = $bk;

                        if (count($ext_bks)) {
                            foreach ($ext_bks as $bk) { ?>
                                <div class="external-auth"><?php $bk->renderExternalLink(); ?></div><?php
                            }
                        }
                        if ($cfg && $cfg->isClientRegistrationEnabled()) { ?>
                            <div class="qs-reg-prompt">
                                <?php echo __('Not yet registered?'); ?> 
                                <a href="account.php?do=create"><?php echo __('Create an account'); ?></a>
                            </div>
                        <?php } ?>

                        <div class="qs-agent-link">
                            <b><?php echo __("I'm an agent"); ?></b> —
                            <a href="<?php echo ROOT_PATH; ?>scp/"><?php echo __('sign in here'); ?></a>
                        </div>
                    </div>
                </div>

            </div>
        </div>

        <!-- Bottom Help -->
        <div class="qs-login-help">
            <?php
            if ($cfg->getClientRegistrationMode() != 'disabled' || !$cfg->isClientLoginRequired()) {
                echo sprintf(__('If this is your first time contacting us or you\'ve lost your ticket number, %s open a new ticket %s'),
                    '<a href="open.php">', '</a>');
            } ?>
        </div>

    </div>
</div>
