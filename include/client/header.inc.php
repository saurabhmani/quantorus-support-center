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

// Dropped IE Support Warning
if (osTicket::is_ie())
    $ost->setWarning(__('osTicket no longer supports Internet Explorer.'));
?>>
<head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">
    <title><?php echo Format::htmlchars($title); ?></title>
    <meta name="description" content="customer support platform">
    <meta name="keywords" content="osTicket, Customer support system, support ticket system">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php
    if (!defined('ASSETS_PATH'))
        define('ASSETS_PATH', ROOT_PATH . 'assets/default/');
    ?>
	<link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/osticket.css" media="screen">
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/theme.css" media="screen">
    <link rel="stylesheet" href="<?php echo ASSETS_PATH; ?>css/print.css" media="print">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/typeahead.css"
         media="screen" />
    <link type="text/css" href="<?php echo ROOT_PATH; ?>css/ui-lightness/jquery-ui-1.13.2.custom.min.css"
        rel="stylesheet" media="screen" />
    <link rel="stylesheet" href="<?php echo ROOT_PATH ?>css/jquery-ui-timepicker-addon.css" media="all">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/thread.css" media="screen">
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/redactor.css" media="screen">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/font-awesome.min.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/flags.css">
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/rtl.css"/>
    <link type="text/css" rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/select2.min.css">
    <!-- Favicons -->
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-32x32.png" sizes="32x32" />
    <link rel="icon" type="image/png" href="<?php echo ROOT_PATH ?>images/oscar-favicon-16x16.png" sizes="16x16" />
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-3.7.0.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-1.13.2.custom.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/jquery-ui-timepicker-addon.js"></script>
    <script src="<?php echo ROOT_PATH; ?>js/osticket.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/filedrop.field.js"></script>
    <script src="<?php echo ROOT_PATH; ?>js/bootstrap-typeahead.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor.min.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-plugins.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/redactor-osticket.js"></script>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>js/select2.min.js"></script>
    <?php
    if($ost && ($headers=$ost->getExtraHeaders())) {
        echo "\n\t".implode("\n\t", $headers)."\n";
    }

    // Offer alternate links for search engines
    // @see https://support.google.com/webmasters/answer/189077?hl=en
    if (($all_langs = Internationalization::getConfiguredSystemLanguages())
        && (count($all_langs) > 1)
    ) {
        $langs = Internationalization::rfc1766(array_keys($all_langs));
        $qs = array();
        parse_str($_SERVER['QUERY_STRING'], $qs);
        foreach ($langs as $L) {
            $qs['lang'] = $L; ?>
        <link rel="alternate" href="//<?php echo $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']); ?>?<?php
            echo http_build_query($qs); ?>" hreflang="<?php echo $L; ?>" />
<?php
        } ?>
        <link rel="alternate" href="//<?php echo $_SERVER['HTTP_HOST'] . htmlspecialchars($_SERVER['REQUEST_URI']); ?>"
            hreflang="x-default" />
<?php
    }
    ?>
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/modern-register.css?v=7">
    <!-- Modern Enterprise UI Styles (Load Last for Priority) -->
    <link rel="stylesheet" href="<?php echo ROOT_PATH; ?>css/modern-client.css?v=<?php echo time(); ?>" media="screen">
</head>
<body class="modern-client-ui">
    <div id="container">
        <?php
        if($ost->getError())
            echo sprintf('<div class="error_bar">%s</div>', $ost->getError());
        elseif($ost->getWarning())
            echo sprintf('<div class="warning_bar">%s</div>', $ost->getWarning());
        elseif($ost->getNotice())
            echo sprintf('<div class="notice_bar">%s</div>', $ost->getNotice());
        ?>
    <header class="qs-navbar">
        <div class="qs-navbar-inner">
            <a class="qs-navbar-brand" href="<?php echo ROOT_PATH; ?>index.php">
                <?php if ($cfg && $cfg->getClientLogoId()) { ?>
                    <img
                        src="<?php echo ROOT_PATH; ?>logo.php"
                        alt="<?php echo Format::htmlchars($cfg->getTitle()); ?>"
                        class="qs-navbar-logo"
                    >
                <?php } else { ?>
                    <div class="qs-navbar-fallback">
                        <span class="qs-navbar-dot"></span>
                        <span class="qs-navbar-title"><?php echo Format::htmlchars($cfg->getTitle()); ?></span>
                    </div>
                <?php } ?>
            </a>

            <nav class="qs-nav-links">
                <ul id="nav">
                    <li><a href="index.php" class="<?php echo ($section=='home')?'active':''; ?>"><?php echo __('Support Center Home'); ?></a></li>
                    <li><a href="open.php" class="<?php echo ($section=='open')?'active':''; ?>"><?php echo __('Open a New Ticket'); ?></a></li>
                    <li><a href="tickets.php" class="<?php echo ($section=='tickets')?'active':''; ?>"><?php echo __('Check Ticket Status'); ?></a></li>
                </ul>
            </nav>
            
            <div class="auth-area">
                <?php if($thisclient && $thisclient->isValid()) {
                    echo sprintf(__('Welcome, %s'), '<strong>'.$thisclient->getName().'</strong>');
                    ?> | <a href="<?php echo $signout_url; ?>"><?php echo __('Sign Out'); ?></a>
                <?php } else {
                    if($cfg->getClientRegistrationMode() != 'disabled') { ?>
                        <span class="qs-guest-label"><?php echo __('Guest User'); ?></span>
                        <a href="<?php echo $signin_url; ?>" class="btn-signin"><?php echo __('Sign In'); ?></a>
                    <?php }
                } ?>
            </div>
        </div>
    </header>
        
    <main class="qs-main">

         <?php if(isset($errors['err']) && $errors['err']) { ?>
            <div id="msg_error"><?php echo $errors['err']; ?></div>
         <?php }elseif(isset($msg) && $msg) { ?>
            <div id="msg_notice"><?php echo $msg; ?></div>
         <?php }elseif(isset($warn) && $warn) { ?>
            <div id="msg_warning"><?php echo $warn; ?></div>
         <?php } ?>
