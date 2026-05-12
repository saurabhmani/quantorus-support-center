<?php
require('client.inc.php');
require_once INCLUDE_DIR . 'class.page.php';

$section = 'home';
require(CLIENTINC_DIR.'header.inc.php');
?>

<div id="atlas_saas_portal">
    
    <!-- HERO SECTION -->
    <div class="hero-wrapper">
        <div class="container-enterprise">
            <div class="hero-panel">
                <!-- Left: Messaging -->
                <div class="hero-left">
                    <div class="hero-badge">
                        <i class="fas fa-shield-check"></i> CUSTOMER SUPPORT PORTAL
                    </div>
                    <h1 class="hero-title">
                        Welcome to the <br>
                        <span>Support Center</span>
                    </h1>
                    <p class="hero-description">
                        Our professional engineering team is ready to assist you. Access documentation, track nodes, and resolve technical issues with ease.
                    </p>
                    <div class="info-strip">
                        <div class="status-dot"></div>
                        Real-time platform availability active
                    </div>
                </div>

                <!-- Right: Actions -->
                <div class="action-stack">
                    <a href="open.php" class="support-action action-blue">
                        <div class="action-icon-box">
                            <i class="fas fa-plus"></i>
                        </div>
                        <div class="action-info">
                            <h3>Open a New Ticket</h3>
                            <p>Submit a request to our engineers</p>
                        </div>
                    </a>

                    <a href="view.php" class="support-action action-green">
                        <div class="action-icon-box">
                            <i class="fas fa-fingerprint"></i>
                        </div>
                        <div class="action-info">
                            <h3>Check Ticket Status</h3>
                            <p>Track your active support nodes</p>
                        </div>
                    </a>
                </div>
            </div>
        </div>
    </div>

    <div class="framework-section">
        <div class="container-enterprise">
            <div class="framework-card">
                <div class="framework-header">
                </div>
                <div class="framework-grid">
                    <!-- Item 1 -->
                    <div class="framework-item">
                        <div class="item-icon"><i class="fas fa-paper-plane"></i></div>
                        <div class="item-content">
                            <h3>Submit Tickets</h3>
                            <p>Direct entry into our engineering resolution pipeline for fast fixes.</p>
                        </div>
                    </div>
                    <!-- Item 2 -->
                    <div class="framework-item">
                        <div class="item-icon"><i class="fas fa-chart-line"></i></div>
                        <div class="item-content">
                            <h3>Track Progress</h3>
                            <p>Real-time visibility into the current stage of your support request.</p>
                        </div>
                    </div>
                    <!-- Item 3 -->
                    <div class="framework-item">
                        <div class="item-icon"><i class="fas fa-history"></i></div>
                        <div class="item-content">
                            <h3>View History</h3>
                            <p>Access your full repository of past resolutions and interactions.</p>
                        </div>
                    </div>
                    <!-- Item 4 -->
                    <div class="framework-item">
                        <div class="item-icon"><i class="fas fa-lock"></i></div>
                        <div class="item-content">
                            <h3>Secure Support</h3>
                            <p>Enterprise-grade encryption for all shared data and attachments.</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- FEATURE GRID SECTION -->
    <div class="features-wrapper">
        <div class="container-enterprise">
            <div class="section-head">
                <h2>Everything you need for fast resolution</h2>
                <p>Our professional support framework ensures you have all the tools for a seamless experience.</p>
            </div>
            
            <div class="features-grid">
                <!-- Helpful Docs -->
                <a href="kb/index.php" class="feature-card">
                    <div class="feature-icon icon-blue"><i class="fas fa-book-open"></i></div>
                    <h3>Helpful Docs</h3>
                    <p>Search our comprehensive knowledge base for instant answers and technical guides.</p>
                </a>

                <!-- Community -->
                <a href="#" class="feature-card">
                    <div class="feature-icon icon-green"><i class="fas fa-users"></i></div>
                    <h3>Community</h3>
                    <p>Join the conversation with other users and developers in our official hub.</p>
                </a>

                <!-- System Status -->
                <a href="#" class="feature-card">
                    <div class="feature-icon icon-purple"><i class="fas fa-signal"></i></div>
                    <h3>System Status</h3>
                    <p>Real-time updates on our global infrastructure and service availability.</p>
                </a>
            </div>
        </div>
    </div>

    <!-- ASSISTANCE SECTION -->
    <div class="assistance-section">
        <div class="container-enterprise">
            <div class="assistance-card">
                <h2>Still need assistance?</h2>
                <p>Our support team is available 24/7 to help you with any technical challenges or account inquiries.</p>
                <a href="open.php" class="btn-priority">
                    Get Priority Support
                </a>
            </div>
        </div>
    </div>

</div>

<?php require(CLIENTINC_DIR.'footer.inc.php'); ?>
