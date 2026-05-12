    </main>
    <div id="footer">
    <div class="footer-wrapper">
        <div class="footer-brand">
            <span>Quantorus365 Support</span>
        </div>
        <div class="footer-copyright">
            &copy; <?php echo date('Y'); ?> <?php echo $ost->getConfig()->getTitle(); ?>. <?php echo __('All rights reserved.'); ?>
        </div>
        <div class="footer-powered">
            <?php echo __('Powered by'); ?> osTicket
        </div>
    </div>
</div>
<div id="overlay"></div>
<div id="loading">
    <h4><?php echo __('Please Wait!');?></h4>
    <p><?php echo __('Please wait... it will take a second!');?></p>
</div>
<?php
if (($lang = Internationalization::getCurrentLanguage()) && $lang != 'en_US') { ?>
    <script type="text/javascript" src="<?php echo ROOT_PATH; ?>ajax.php/i18n/<?php
        echo $lang; ?>/js"></script>
<?php } ?>
<script type="text/javascript">
    getConfig().resolve(<?php
        include INCLUDE_DIR . 'ajax.config.php';
        $api = new ConfigAjaxAPI();
        print $api->client(false);
    ?>);
</script>
</body>
</html>
