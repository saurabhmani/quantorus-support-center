<?php
/*********************************************************************
    index.php

    Helpdesk landing page. Please customize it to fit your needs.

    Peter Rotich <peter@osticket.com>
    Copyright (c)  2006-2013 osTicket
    http://www.osticket.com

    Released under the GNU General Public License WITHOUT ANY WARRANTY.
    See LICENSE.TXT for details.

    vim: expandtab sw=4 ts=4 sts=4:
**********************************************************************/
require('client.inc.php');

require_once INCLUDE_DIR . 'class.page.php';

$section = 'home';
require(CLIENTINC_DIR.'header.inc.php');
?>
<div class="quantorus-homepage qs-home-wrapper">
    <!-- Pixel-Perfect Hero Section -->
    <section class="modern-hero qs-home-hero">
        <div class="hero-left">
            <h1><?php echo __('Welcome to the'); ?><br/><?php echo __('Support Center'); ?></h1>
            <p><?php echo __('Our enterprise support ticket system is designed to provide you with expert assistance and real-time tracking for every request.'); ?></p>
            <a href="kb/index.php" class="kb-link">
                <?php echo __('View our knowledge base'); ?> <i class="icon-arrow-right"></i>
            </a>
        </div>
        <div class="hero-right">
            <div class="hero-buttons-stack">
                <a href="open.php" class="btn-main-saas primary">
                    <span><?php echo __('Open a New Ticket'); ?></span>
                    <i class="icon-plus-circle"></i>
                </a>
                <a href="view.php" class="btn-main-saas success">
                    <span><?php echo __('Check Ticket Status'); ?></span>
                    <i class="icon-search"></i>
                </a>
                <div class="hero-status-card">
                    <?php echo __('Response time is typically under 24 hours during business days.'); ?>
                </div>
            </div>
        </div>
    </section>

    <!-- Premium Feature Strip -->
    <section class="feature-strip qs-feature-strip">
        <div class="feature-item">
            <div class="f-icon-box blue-grad">
                <i class="icon-plus-sign"></i>
            </div>
            <div class="f-content">
                <h4><?php echo __('Submit Tickets'); ?></h4>
                <p><?php echo __('Fast processing'); ?></p>
            </div>
        </div>
        <div class="feature-item">
            <div class="f-icon-box green-grad">
                <i class="icon-refresh"></i>
            </div>
            <div class="f-content">
                <h4><?php echo __('Track Progress'); ?></h4>
                <p><?php echo __('Real-time updates'); ?></p>
            </div>
        </div>
        <div class="feature-item">
            <div class="f-icon-box purple-grad">
                <i class="icon-time"></i>
            </div>
            <div class="f-content">
                <h4><?php echo __('View History'); ?></h4>
                <p><?php echo __('Audit logs'); ?></p>
            </div>
        </div>
        <div class="feature-item">
            <div class="f-icon-box sky-grad">
                <i class="icon-envelope"></i>
            </div>
            <div class="f-content">
                <h4><?php echo __('Email Support'); ?></h4>
                <p><?php echo __('Secure communication'); ?></p>
            </div>
        </div>
    </section>

    <!-- Resource Section Header -->
    <div class="resource-section-header">
        <span class="section-label"><?php echo __('SUPPORT RESOURCES'); ?></span>
        <h2><?php echo __('Everything you need for fast resolution'); ?></h2>
        <p><?php echo __('Browse platform resources, live system updates, and community support tools designed for enterprise users.'); ?></p>
        <div class="header-glow"></div>
    </div>

    <!-- Premium Resource Cards -->
    <section class="info-grid qs-card-grid">
        <a href="#" class="info-card">
            <div class="card-badge live"><?php echo __('LIVE'); ?></div>
            <div class="card-icon-container blue-grad">
                <i class="icon-signal"></i>
            </div>
            <h3><?php echo __('System Status'); ?></h3>
            <p><?php echo __('Monitor real-time system performance and uptime metrics.'); ?></p>
            <div class="card-cta">
                <span><?php echo __('View Status'); ?></span>
                <i class="icon-arrow-right"></i>
            </div>
        </a>
        <a href="#" class="info-card">
            <div class="card-badge updated"><?php echo __('UPDATED'); ?></div>
            <div class="card-icon-container purple-grad">
                <i class="icon-bullhorn"></i>
            </div>
            <h3><?php echo __('Recent Updates'); ?></h3>
            <p><?php echo __('Stay informed about our latest features and service improvements.'); ?></p>
            <div class="card-cta">
                <span><?php echo __('Read Updates'); ?></span>
                <i class="icon-arrow-right"></i>
            </div>
        </a>
        <a href="#" class="info-card">
            <div class="card-badge community"><?php echo __('COMMUNITY'); ?></div>
            <div class="card-icon-container cyan-grad">
                <i class="icon-group"></i>
            </div>
            <h3><?php echo __('Community Hub'); ?></h3>
            <p><?php echo __('Connect with other users and share best practices.'); ?></p>
            <div class="card-cta">
                <span><?php echo __('Join Community'); ?></span>
                <i class="icon-arrow-right"></i>
            </div>
        </a>
    </section>

    <?php if($cfg && $cfg->isKnowledgebaseEnabled()){ ?>
    <div class="featured-articles">
        <?php
        $cats = Category::getFeatured();
        if ($cats->all()) { ?>
            <h2 style="font-size: 28px; font-weight: 800; margin-bottom: 40px; color: #000;"><?php echo __('Featured Knowledge Base'); ?></h2>
            <div class="featured-grid" style="display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 32px;">
                <?php foreach ($cats as $C) { ?>
                    <div class="featured-category" style="background: #fff; padding: 40px; border-radius: 20px; border: 1px solid #edf2f7;">
                        <h4 style="font-size: 17px; font-weight: 700; color: var(--primary-blue); margin-bottom: 24px;"><?php echo $C->getName(); ?></h4>
                        <ul style="list-style: none; padding: 0;">
                            <?php foreach ($C->getTopArticles() as $F) { ?>
                                <li style="margin-bottom: 14px;">
                                    <a style="color: var(--text-secondary); text-decoration: none; font-size: 15px; font-weight: 500;" href="<?php echo ROOT_PATH; ?>kb/faq.php?id=<?php echo $F->getId(); ?>">
                                        <?php echo $F->getQuestion(); ?>
                                    </a>
                                </li>
                            <?php } ?>
                        </ul>
                    </div>
                <?php } ?>
            </div>
        <?php } ?>
    </div>
    <?php } ?>
</div>




<?php require(CLIENTINC_DIR.'footer.inc.php'); ?>
