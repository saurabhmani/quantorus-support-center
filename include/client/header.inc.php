<?php
$title=($cfg && is_object($cfg) && $cfg->getTitle())
    ? $cfg->getTitle() : 'osTicket :: '.__('Support Ticket System');
$signin_url = ROOT_PATH . "login.php"
    . ($thisclient ? "?e=".urlencode($thisclient->getEmail()) : "");
$signout_url = ROOT_PATH . "logout.php?auth=".$ost->getLinkToken();

header("Content-Type: text/html; charset=UTF-8");
header("Content-Security-Policy: frame-ancestors ".$cfg->getAllowIframes()."; script-src 'self' 'unsafe-inline'; object-src 'none'");

if (($lang = Internationalization::getCurrentLanguage())) {
    $langs = array_unique(array($lang, $cfg->getPrimaryLanguage()));
    $langs = Internationalization::rfc1766($langs);
    header("Content-Language: ".implode(', ', $langs));
}
?>
<!DOCTYPE html>
<html<?php
if ($lang
        && ($info = Internationalization::getLanguageInfo($lang))
        && (@$info['direction'] == 'rtl'))
    echo ' dir="rtl" class="rtl"';
if ($lang) {
    echo ' lang="' . $lang . '"';
}
?>>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title><?php echo Format::htmlchars($title); ?></title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>include/css/custom.css">

    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-3.7.0.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
    <script src="<?php echo ROOT_PATH; ?>js/osticket.js"></script>

    <?php
    if($ost && ($headers=$ost->getExtraHeaders())) {
        echo "\n\t".implode("\n\t", $headers)."\n";
    }
    ?>
    <!-- FINAL HIGH-PRIORITY ENTERPRISE OVERRIDE LAYER -->
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>scp/css/atlas-refine.css?v=99">
</head>
<?php
$is_login_page = (basename($_SERVER['PHP_SELF']) == 'login.php');
?>
<body class="modern-body <?php echo $is_login_page ? 'login-page-active' : ''; ?>">
<?php if ($is_login_page) { ?>
    <!-- INSTITUTIONAL DYNAMIC BACKGROUND ENGINE -->
    <div id="dynamic-bg-container">
        <div class="bg-overlay"></div>
        <!-- Video fallback will be injected or active if file exists -->
        <video id="bg-video" autoplay muted loop playsinline style="display:none;">
            <source src="<?php echo ROOT_PATH; ?>assets/login-bg/background-video.mp4" type="video/mp4">
        </video>
    </div>
    <script src="<?php echo ROOT_PATH; ?>js/login-background.js"></script>
<?php } ?>
<header class="header-enterprise">
    <div class="container-enterprise" style="width: 100%;">
        <div class="nav-inner">
            <!-- Left: Logo & Brand -->
            <a class="d-flex align-items-center" href="<?php echo ROOT_PATH; ?>index.php" style="text-decoration: none;">
                <div style="background: #2EA8FF; width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; margin-right: 14px; box-shadow: 0 4px 12px rgba(46, 168, 255, 0.2);">
                    <i class="fa-solid fa-headset" style="color: white; font-size: 1.05rem;"></i>
                </div>
                <span style="font-weight: 800; color: #0F172A; font-size: 1.2rem; letter-spacing: -0.03em;"><?php echo $ost->getConfig()->getTitle(); ?></span>
            </a>

            <!-- Center: Primary Navigation -->
            <nav class="nav-links d-none d-lg-flex">
                <a href="<?php echo ROOT_PATH; ?>index.php" class="<?php echo ($section=='home'?'active':''); ?>">Support Center Home</a>
                <a href="<?php echo ROOT_PATH; ?>open.php" class="<?php echo ($section=='tickets' && !$_SESSION['_client']['key']?'active':''); ?>">Open a New Ticket</a>
                <a href="<?php echo ROOT_PATH; ?>view.php" class="<?php echo ($section=='tickets' && $_SESSION['_client']['key']?'active':''); ?>">Check Ticket Status</a>
            </nav>

            <!-- Right: Auth Actions -->
            <div class="auth-links">
                <?php if ($thisclient && is_object($thisclient) && $thisclient->isValid()) { ?>
                    <span style="color: #64748B; font-weight: 500;"><?php echo Format::htmlchars($thisclient->getName()); ?></span>
                    <a href="<?php echo $signout_url; ?>" style="color: #EF4444; font-weight: 700;">Sign Out</a>
                <?php } else { ?>
                    <span style="color: #64748B; font-weight: 500;">Guest User</span>
                    <a href="<?php echo $signin_url; ?>" style="color: #2EA8FF; font-weight: 700;">Sign In</a>
                <?php } ?>
            </div>
        </div>
    </div>
</header>
<main>
