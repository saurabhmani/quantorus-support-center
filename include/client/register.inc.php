<?php
$info = $_POST;
if (!isset($info['timezone']))
    $info += array(
        'backend' => null,
    );
if (isset($user) && $user instanceof ClientCreateRequest) {
    $bk = $user->getBackend();
    $info = array_merge($info, array(
        'backend' => $bk->getBkId(),
        'username' => $user->getUsername(),
    ));
}
$info = Format::htmlchars(($errors && $_POST)?$_POST:$info);
?>

<div class="qs-onboarding-wrapper">
    
    <!-- Dynamic Enterprise Branding -->
    <div class="qs-register-brand">
        <?php
        $logoUrl = ROOT_PATH . 'logo.php';
        // Check if a logo is actually configured in the backend
        if ($cfg && $cfg->getClientLogoId()) { ?>
            <a href="index.php">
                <img src="<?php echo $logoUrl; ?>" alt="Support Portal">
            </a>
        <?php } else { ?>
            <div class="qs-text-logo">
                Quantorus365 Support
            </div>
        <?php } ?>
    </div>

    <!-- Ultra-Premium Hero -->
    <div class="qs-onboarding-hero">
        <h1><?php echo __('Create your support account'); ?></h1>
        <p><?php echo __('Join our enterprise support ecosystem to securely track tickets, access high-priority communication channels, and manage your technical service requests.'); ?></p>
    </div>

    <!-- Main Enterprise Card -->
    <div class="qs-onboarding-shell">
        <div class="qs-onboarding-card">

            <!-- Left: Strategic Form Panel -->
            <div class="qs-onboarding-left">
                <form action="account.php" method="post">
                    <?php csrf_token(); ?>
                    <input type="hidden" name="do" value="<?php echo Format::htmlchars($_REQUEST['do']
                        ?: ($info['backend'] ? 'import' :'create')); ?>" />

                    <!-- Identity Section -->
                    <div class="qs-form-section">
                        <h2 class="qs-section-main-title"><?php echo __('Account Information'); ?></h2>
                        <div class="qs-dynamic-form">
                            <?php
                                $cf = $user_form ?: UserForm::getInstance();
                                $cf->render(array('staff' => false, 'mode' => 'create'));
                            ?>
                        </div>
                    </div>

                    <!-- Localization Section -->
                    <div class="qs-form-section">
                        <h3 class="qs-section-sub-title"><?php echo __('Localization'); ?></h3>
                        <div class="qs-input-group">
                            <label><?php echo __('Regional Time Zone');?>:</label>
                            <div class="qs-timezone-unified-control">
                                <?php
                                $TZ_NAME = 'timezone';
                                $TZ_TIMEZONE = $info['timezone'];
                                include INCLUDE_DIR.'staff/templates/timezone.tmpl.php'; ?>
                            </div>
                            <?php if ($errors['timezone']) { ?>
                                <div class="qs-error-message"><?php echo $errors['timezone']; ?></div>
                            <?php } ?>
                        </div>
                    </div>

                    <!-- Security Section -->
                    <div class="qs-form-section">
                        <h3 class="qs-section-sub-title"><?php echo __('Access & Security'); ?></h3>
                        <?php if ($info['backend']) { ?>
                            <div class="qs-input-group">
                                <label><?php echo __('Identity Provider'); ?>:</label>
                                <input type="hidden" name="backend" value="<?php echo $info['backend']; ?>"/>
                                <input type="hidden" name="username" value="<?php echo $info['username']; ?>"/>
                                <div class="qs-auth-provider-badge">
                                    <i class="icon-lock"></i>
                                    <?php foreach (UserAuthenticationBackend::allRegistered() as $bk) {
                                        if ($bk->getBkId() == $info['backend']) {
                                            echo $bk->getName();
                                            break;
                                        }
                                    } ?>
                                </div>
                            </div>
                        <?php } else { ?>
                            <div class="qs-input-row">
                                <div class="qs-input-group">
                                    <label><?php echo __('Security Password'); ?>:</label>
                                    <input type="password" name="passwd1" id="passwd1" class="qs-premium-input" placeholder="<?php echo __('Minimum 8 characters'); ?>" maxlength="128" value="<?php echo $info['passwd1']; ?>">
                                    <?php if ($errors['passwd1']) { ?>
                                        <div class="qs-error-message"><?php echo $errors['passwd1']; ?></div>
                                    <?php } ?>
                                </div>
                                <div class="qs-input-group">
                                    <label><?php echo __('Confirm Password'); ?>:</label>
                                    <input type="password" name="passwd2" id="passwd2" class="qs-premium-input" placeholder="<?php echo __('Repeat your password'); ?>" maxlength="128" value="<?php echo $info['passwd2']; ?>">
                                    <?php if ($errors['passwd2']) { ?>
                                        <div class="qs-error-message"><?php echo $errors['passwd2']; ?></div>
                                    <?php } ?>
                                </div>
                            </div>
                        <?php } ?>
                    </div>

                    <!-- Actions -->
                    <div class="qs-action-hub">
                        <button type="submit" class="qs-btn-primary"><?php echo __('Register Account'); ?></button>
                        <a href="index.php" class="qs-btn-secondary"><?php echo __('Cancel'); ?></a>
                    </div>
                </form>
            </div>

            <!-- Right: Feature Trust Panel -->
            <div class="qs-onboarding-right">
                <div class="qs-panel-glow"></div>
                <div class="qs-trust-content">
                    <div class="qs-feature-icon">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M12 3l7 4v5c0 5-3.5 8-7 9-3.5-1-7-4-7-9V7l7-4z"/>
                        </svg>
                    </div>

                    <h3 class="qs-panel-heading">Enterprise Security</h3>
                    <p class="qs-panel-desc"><?php echo __('Your account provides high-fidelity access to professional support workflows and secure communication infrastructure.'); ?></p>

                    <div class="qs-feature-checklist">
                        <div class="qs-feature-item">
                            <div class="qs-check-circle"><i class="icon-check"></i></div>
                            <span>Secure Ticket Access Gateway</span>
                        </div>
                        <div class="qs-feature-item">
                            <div class="qs-check-circle"><i class="icon-check"></i></div>
                            <span>Real-Time Support Pipeline</span>
                        </div>
                        <div class="qs-feature-item">
                            <div class="qs-check-circle"><i class="icon-check"></i></div>
                            <span>Enterprise Communication History</span>
                        </div>
                        <div class="qs-feature-item">
                            <div class="qs-check-circle"><i class="icon-check"></i></div>
                            <span>High-Priority Queue Access</span>
                        </div>
                    </div>
                    
                    <div class="qs-panel-footer">
                        <p><?php echo __('Trusted by enterprise teams worldwide.'); ?></p>
                    </div>
                </div>
            </div>

        </div>
    </div>

</div>

<?php if (!isset($info['timezone'])) { ?>
<!-- Advanced Client-Side Timezone Detection -->
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jstz.min.js"></script>
<script type="text/javascript">
$(function() {
    var zone = jstz.determine();
    $('#timezone-dropdown').val(zone.name()).trigger('change');
});
</script>
<?php } ?>
