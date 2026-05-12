    <footer class="footer-enterprise">
        <div class="container-enterprise">
            <div class="footer-top">
                <!-- Brand Info -->
                <div class="footer-brand">
                    <h2><?php echo $ost->getConfig()->getTitle(); ?></h2>
                    <p>Providing enterprise-grade technical support and resolution services for global infrastructure.</p>
                </div>

                <!-- Support Links -->
                <div class="footer-col">
                    <h4>Support Resources</h4>
                    <div class="footer-links-list">
                        <a href="<?php echo ROOT_PATH; ?>index.php">Support Home</a>
                        <a href="<?php echo ROOT_PATH; ?>kb/index.php">Knowledge Base</a>
                        <a href="<?php echo ROOT_PATH; ?>open.php">Open Ticket</a>
                    </div>
                </div>

                <!-- Legal -->
                <div class="footer-col">
                    <h4>Company</h4>
                    <div class="footer-links-list">
                        <a href="#">Privacy Policy</a>
                        <a href="#">Terms of Service</a>
                        <a href="#">Security Nodes</a>
                    </div>
                </div>
            </div>

            <div class="footer-bottom">
                <p>© <?php echo date('Y'); ?> <?php echo Format::htmlchars((string) $ost->company ?: 'osTicket.com'); ?>. All rights reserved.</p>
                <p>Powered by osTicket</p>
            </div>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    
    <script type="text/javascript">
        // osTicket Global Config
        if (typeof getConfig === 'function') {
            getConfig().resolve(<?php
                include INCLUDE_DIR . 'ajax.config.php';
                $api = new ConfigAjaxAPI();
                print $api->client(false);
            ?>);
        }
    </script>
</body>
</html>

