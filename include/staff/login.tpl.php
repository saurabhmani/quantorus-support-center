<?php
include_once(INCLUDE_DIR.'staff/login.header.php');
$info = ($_POST && $errors)?Format::htmlchars($_POST):array();

if ($thisstaff && $thisstaff->is2FAPending())
    $msg = "2FA Pending";

// Resolve Dynamic Branding Logo with Cache-Busting
$branding_logo = 'logo.php?login'; // Default Fallback
$branding_extensions = ['png', 'svg', 'webp', 'jpg'];
foreach ($branding_extensions as $ext) {
    $file_path = ROOT_DIR . 'assets/login-branding/logo.' . $ext;
    if (file_exists($file_path)) {
        $v = filemtime($file_path); // Versioning for cache-busting
        $branding_logo = ROOT_PATH . 'assets/login-branding/logo.' . $ext . '?v=' . $v;
        break;
    }
}
?>
<!-- Modern Login Styles -->
<link rel="stylesheet" href="css/modern-login.css" type="text/css" />
<script>
    document.body.id = 'login-page';
</script>

<!-- Dynamic Background System -->
<div id="login-bg-container"></div>
<div id="login-overlay"></div>

<div class="modern-login-wrapper">
    <div class="login-card">
        <!-- Secured Badge -->
        <div class="secured-badge">
            <i class="icon-lock"></i> Secured Enterprise Access
        </div>

        <!-- Branding -->
        <div class="login-card-logo">
            <a href="index.php">
                <img src="<?php echo $branding_logo; ?>" alt="Branding" />
            </a>
        </div>

        <!-- Header -->
        <div class="login-card-header">
            <h2>Welcome Back</h2>
            <p>Please enter your credentials to access the support dashboard</p>
        </div>

        <!-- Error/Notice Messages -->
        <div id="login-message"><?php echo Format::htmlchars($msg); ?></div>

        <!-- Loading Overlay -->
        <div id="loading" style="display:none;" class="dialog">
            <h1><i class="icon-spinner icon-spin"></i> <?php echo __('Verifying');?></h1>
        </div>

        <!-- Authentication Form -->
        <div id="login-form-container">
            <form action="login.php" method="post" id="login">
                <?php csrf_token();
                if ($thisstaff
                        &&  $thisstaff->is2FAPending()
                        && ($bk=$thisstaff->get2FABackend())
                        && ($form=$bk->getInputForm($_POST))) {
                    // Render 2FA input form
                    include STAFFINC_DIR . 'templates/dynamic-form-simple.tmpl.php';
                    ?>
                    <fieldset style="padding-top:10px;">
                        <input type="hidden" name="do" value="2fa">
                        <button class="btn-login" type="submit" name="submit">
                            <i class="icon-signin"></i> <?php echo __('Verify'); ?>
                        </button>
                    </fieldset>
                <?php
                } else { ?>
                    <input type="hidden" name="do" value="scplogin">
                    <fieldset>
                        <div class="input-group">
                            <i class="icon-user"></i>
                            <input type="text" name="userid" id="name" value="<?php
                                echo $info['userid'] ?? null; ?>" placeholder="<?php echo __('Email or Username'); ?>"
                                autofocus autocorrect="off" autocapitalize="off" required>
                        </div>
                        <div class="input-group">
                            <i class="icon-lock"></i>
                            <input type="password" name="passwd" id="pass" maxlength="128" placeholder="<?php echo __('Password'); ?>" autocorrect="off" autocapitalize="off" required>
                        </div>
                        
                        <div class="login-options">
                            <a id="reset-link" class="<?php
                                if (!$show_reset || !$cfg->allowPasswordReset()) echo 'hidden';
                                ?>" href="pwreset.php"><?php echo __('Forgot My Password'); ?></a>
                        </div>

                        <button class="btn-login" type="submit" name="submit">
                            <i class="icon-signin"></i> <?php echo __('Log In'); ?>
                        </button>
                    </fieldset>
                <?php
                } ?>
            </form>
        </div>

        <?php
        if (($bks=StaffAuthenticationBackend::getExternal())) { ?>
            <div class="or" style="margin: 20px 0; color: #999; font-size: 12px; text-transform: uppercase; letter-spacing: 1px;">
                <span><?php echo __('or'); ?></span>
            </div>
            <?php
            foreach ($bks as $bk) { ?>
                <div class="external-auth"><?php $bk->renderExternalLink(); ?></div>
            <?php
            }
        } ?>

        <!-- Footer -->
        <div class="login-card-footer">
            &copy; <?php echo date('Y'); ?> <?php echo Format::htmlchars($ost->company) ?: 'Quantorus Inc.'; ?> 
            <br/> <a href="../"><?php echo __('Back to Portal'); ?></a>
        </div>
    </div>
</div>

<!-- Bottom Right Area -->
<div class="bottom-right-area">
    <div class="glass-pill">
        <i class="icon-globe"></i> English (US)
    </div>
    <div class="glass-pill">
        <i class="icon-question-sign"></i> Support
    </div>
</div>

<?php
// Resolve Dynamic Backgrounds from Assets Folder
$bg_dir = ROOT_DIR . 'assets/login-backgrounds/';
$bg_web_path = ROOT_PATH . 'assets/login-backgrounds/';
$bg_list = [];

if (is_dir($bg_dir)) {
    $files = glob($bg_dir . "*.{jpg,jpeg,png,webp}", GLOB_BRACE);
    foreach ($files as $file) {
        $v = filemtime($file); // Versioning for cache-busting
        $bg_list[] = $bg_web_path . basename($file) . '?v=' . $v;
    }
}

// Fallback if no images found
if (empty($bg_list)) {
    $bg_list[] = 'https://images.unsplash.com/photo-1497366216548-37526070297c?auto=format&fit=crop&q=80&w=1920';
}
?>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Dynamic Background Loader with Admin Management
    const bgContainer = document.getElementById('login-bg-container');
    const backgrounds = <?php echo json_encode($bg_list); ?>;
    
    if (bgContainer && backgrounds.length > 0) {
        const randomBg = backgrounds[Math.floor(Math.random() * backgrounds.length)];
        bgContainer.style.backgroundImage = `url('${randomBg}')`;
    }

    // Handle Form Submit (Native Redirect Support)
    const loginForm = document.getElementById('login');
    if (loginForm) {
        loginForm.addEventListener('submit', function() {
            const loading = document.getElementById('loading');
            if (loading) loading.style.display = 'flex';
            // Do NOT disable button here as it may prevent the button name/value from being sent
            return true;
        });
    }
});
</script>
<script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
</body>
</html>



