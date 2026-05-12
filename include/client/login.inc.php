<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');

$email=Format::input($_POST['luser']?:$_GET['e']);
$passwd = '';

$content = Page::lookupByType('banner-client');
if ($content) {
    list($title, $body) = $ost->replaceTemplateVariables(
        array($content->getLocalName(), $content->getLocalBody()));
} else {
    $title = __('Welcome Back');
    $body = __('Sign in to your enterprise support dashboard');
}
?>

<div class="login-wrapper">
    <!-- TOP ENTERPRISE BRANDING -->
    <div class="login-branding" data-aos="fade-down">
        <div class="brand-icon-hub">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 class="brand-title"><?php echo $ost->getConfig()->getTitle(); ?></h1>
        <p class="brand-subtitle"></p>
    </div>

    <!-- PREMIUM GLASS LOGIN CARD -->
    <div class="login-card-glass" data-aos="zoom-in" data-aos-delay="100">
        <div class="card-header">
            <h2><?php echo Format::display($title); ?></h2>
            <p><?php echo Format::display($body); ?></p>
        </div>

        <form action="login.php" method="post" id="clientLogin">
            <?php csrf_token(); ?>
            
            <?php if ($errors['login']) { ?>
                <div class="login-error-alert">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo Format::htmlchars($errors['login']); ?>
                </div>
            <?php } ?>

            <div class="input-stack">
                <div class="input-group-premium">
                    <div class="input-icon"><i class="fa-solid fa-user-tie"></i></div>
                    <input id="username" placeholder="<?php echo __('Email or Username'); ?>" type="text" name="luser" value="<?php echo $email; ?>" class="nowarn" required>
                </div>

                <div class="input-group-premium">
                    <div class="input-icon"><i class="fa-solid fa-lock-keyhole"></i></div>
                    <input id="passwd" placeholder="<?php echo __('Password'); ?>" type="password" name="lpasswd" maxlength="128" value="" class="nowarn" required>
                </div>
            </div>

            <div class="form-options">
                <?php if ($suggest_pwreset) { ?>
                    <a href="pwreset.php" class="forgot-link"><?php echo __('Forgot Password?'); ?></a>
                <?php } ?>
            </div>

            <button class="btn-login-enterprise" type="submit">
                <span><?php echo __('Log In'); ?></span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>

            <?php
            $ext_bks = array();
            foreach (UserAuthenticationBackend::allRegistered() as $bk)
                if ($bk instanceof ExternalAuthentication)
                    $ext_bks[] = $bk;

            if (count($ext_bks)) {
                echo '<div class="external-auth-premium">';
                foreach ($ext_bks as $bk) { 
                    $bk->renderExternalLink(); 
                }
                echo '</div>';
            }
            ?>

            <div class="card-footer-divider">
                <?php if ($cfg && $cfg->isClientRegistrationEnabled()) { ?>
                    <span><?php echo __('New to our platform?'); ?></span>
                    <a href="account.php?do=create" class="create-account-link"><?php echo __('Create Account'); ?></a>
                <?php } else { ?>
                    <span><?php echo __('Need support?'); ?></span>
                <?php } ?>
            </div>
            
            <?php
            if ($cfg->getClientRegistrationMode() != 'disabled' || !$cfg->isClientLoginRequired()) {
                echo '<div style="margin-top: 15px; text-align: center; font-size: 0.9em;">';
                echo sprintf(__('If this is your first time contacting us or you\'ve lost the ticket number, please %s open a new ticket %s'), '<a href="open.php" class="forgot-link">', '</a>');
                echo '</div>';
            } 
            ?>
        </form>
    </div>

</div>

<script>
document.querySelector('#clientLogin')?.addEventListener('submit', () => {
    console.log('[CLIENT AUTH] Native login submit');
});
</script>
