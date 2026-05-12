<?php
include_once(INCLUDE_DIR.'staff/login.header.php');
$info = ($_POST && $errors)?Format::htmlchars($_POST):array();


if ($thisstaff && $thisstaff->is2FAPending())
    $msg = "2FA Pending";

?>
<div class="login-wrapper">
    <!-- TOP ENTERPRISE BRANDING -->
    <div class="login-branding" data-aos="fade-down">
        <div class="brand-icon-hub">
            <i class="fa-solid fa-shield-halved"></i>
        </div>
        <h1 class="brand-title"><?php echo $ost->getConfig()->getTitle(); ?></h1>
        <p class="brand-subtitle">Agent Control Panel</p>
    </div>

    <!-- PREMIUM GLASS LOGIN CARD -->
    <div class="login-card-glass" data-aos="zoom-in" data-aos-delay="100">
        <div class="card-header">
            <h2><?php echo __('Welcome Back'); ?></h2>
            <p><?php echo ($content) ? Format::display($content->getLocalBody()) : __('Sign in to your agent dashboard'); ?></p>
        </div>

        <form action="login.php" method="post" id="login">
            <?php csrf_token(); ?>
            <input type="hidden" name="do" value="scplogin">
            
            <?php if ($msg) { ?>
                <div class="login-error-alert" id="login-message">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php echo Format::htmlchars($msg); ?>
                </div>
            <?php } ?>

            <div class="input-stack">
                <div class="input-group-premium">
                    <div class="input-icon"><i class="fa-solid fa-user-shield"></i></div>
                    <input id="name" placeholder="<?php echo __('Email or Username'); ?>" type="text" name="userid" value="<?php echo $info['userid'] ?? null; ?>" class="nowarn" required autofocus>
                </div>

                <div class="input-group-premium">
                    <div class="input-icon"><i class="fa-solid fa-lock-keyhole"></i></div>
                    <input id="pass" placeholder="<?php echo __('Password'); ?>" type="password" name="passwd" maxlength="128" class="nowarn" required>
                </div>
            </div>

            <div class="form-options">
                <?php if ($show_reset && $cfg->allowPasswordReset()) { ?>
                    <a href="pwreset.php" id="reset-link" class="forgot-link"><?php echo __('Forgot My Password'); ?></a>
                <?php } ?>
            </div>

            <button class="btn-login-enterprise" type="submit">
                <span><?php echo __('Log In'); ?></span>
                <i class="fa-solid fa-arrow-right"></i>
            </button>

            <div class="card-footer-divider">
                <span><?php echo __('Technical Issue?'); ?></span>
                <a href="<?php echo ROOT_PATH; ?>" class="create-account-link"><?php echo __('Return to Site'); ?></a>
            </div>
        </form>
    </div>
</div>

<script>
    const loginForm = document.getElementById('login');
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            // Native submission
        });
    }
</script>
</body>
</html>
