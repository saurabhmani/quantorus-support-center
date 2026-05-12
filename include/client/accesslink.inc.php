<?php
if(!defined('OSTCLIENTINC')) die('Access Denied');

$email=Format::input($_POST['lemail']?$_POST['lemail']:$_GET['e']);
$ticketid=Format::input($_POST['lticket']?$_POST['lticket']:$_GET['t']);

if ($cfg->isClientEmailVerificationRequired())
    $button = __("Email Access Link");
else
    $button = __("View Ticket");
?>
<div class="qs-status-page">

    <!-- Airy Hero Section -->
    <header class="qs-hero">
        <h1><?php echo __('Check Ticket Status'); ?></h1>
        <p><?php
        echo __('Please provide your email address and a ticket number.');
        if ($cfg->isClientEmailVerificationRequired())
            echo '<br>' . __('An access link will be emailed to you securely.');
        ?></p>
    </header>

    <!-- Main Card (1120px) -->
    <main class="qs-status-card">
        <!-- Left Side: Form Panel -->
        <section class="qs-status-form">
            <div class="form-header">
                <h2><?php echo __("Email Access Link"); ?></h2>
                <p><?php echo __('Enter your details below to receive a secure link to your ticket dashboard.'); ?></p>
            </div>

            <?php if ($errors['login']) { ?>
                <div class="qs-error-box">
                    <i class="icon-warning-sign"></i>
                    <span><?php echo Format::htmlchars($errors['login']); ?></span>
                </div>
            <?php } ?>

            <form action="login.php" method="post" id="clientLogin">
                <?php csrf_token(); ?>
                
                <div class="qs-input-wrapper">
                    <div class="qs-input-inner">
                        <i class="icon-envelope"></i>
                        <input id="email" placeholder="<?php echo __('Email Address'); ?>" type="text"
                            name="lemail" value="<?php echo $email; ?>" class="nowarn">
                    </div>
                </div>

                <div class="qs-input-wrapper">
                    <div class="qs-input-inner">
                        <i class="icon-tag"></i>
                        <input id="ticketno" type="text" name="lticket" placeholder="<?php echo __('Ticket Number'); ?>"
                            value="<?php echo $ticketid; ?>" class="nowarn">
                    </div>
                </div>

                <div class="qs-form-footer">
                    <button type="submit" class="qs-btn-primary">
                        <span><?php echo $button; ?></span>
                        <i class="icon-arrow-right"></i>
                    </button>
                </div>
            </form>
        </section>

        <!-- Right Side: Account Panel (Institutional White) -->
        <aside class="qs-status-side">
            <div class="lock-icon-badge">
                <i class="icon-lock"></i>
            </div>
            
            <h3><?php echo __('Have an account?'); ?></h3>
            <p><?php echo __('Sign in or register for an account to track all your active support requests in one place.'); ?></p>

            <div class="qs-side-actions">
                <a href="login.php" class="qs-btn-outline-pill"><?php echo __('Sign In'); ?></a>
                
                <?php if ($cfg && $cfg->getClientRegistrationMode() !== 'disabled' && $cfg->isClientRegistrationEnabled()) { ?>
                    <a href="account.php?do=create" class="qs-register-link"><?php echo __('Register for an account'); ?></a>
                <?php } ?>
            </div>
        </aside>
    </main>

</div>
