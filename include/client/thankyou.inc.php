<?php
if(!defined('OSTCLIENTINC') || !$ticket || !$page) die('Access Denied!');
?>
<div class="thank-you-page">
    <div class="thank-you-card">
        <div class="success-icon-wrapper">
            <div class="success-icon">
                <i class="icon-ok"></i>
            </div>
        </div>
        
        <div class="thank-you-header">
            <h1><?php echo __('Ticket Successfully Submitted'); ?></h1>
            <p class="subheading"><?php echo __('Our support specialists have received your request and will review it shortly.'); ?></p>
        </div>

        <div class="divider"></div>
        
        <div class="confirmation-content">
            <?php 
            echo Format::viewableImages(
                $ticket->replaceVars($page->getLocalBody()),
                ['type' => 'P']
            ); 
            ?>
        </div>
        
        <div class="info-box">
            <i class="icon-envelope"></i>
            <span><?php echo __('Confirmation details have also been sent to your email.'); ?></span>
        </div>
        
        <div class="action-buttons">
            <a href="view.php?t=<?php echo $ticket->getNumber(); ?>" class="btn-primary-pill">
                <?php echo __('View Ticket Status'); ?>
            </a>
            <a href="index.php" class="btn-secondary-pill">
                <?php echo __('Back to Support Home'); ?>
            </a>
        </div>
    </div>
</div>

<style>
.thank-you-page {
    padding: 100px 24px !important;
    background: #f8fafc !important;
    min-height: 80vh !important;
    display: flex !important;
    justify-content: center !important;
    align-items: flex-start !important;
}

.thank-you-card {
    max-width: 760px !important;
    width: 100% !important;
    background: #ffffff !important;
    border: 1px solid #e5e7eb !important;
    border-radius: 28px !important;
    padding: 60px 48px !important;
    box-shadow: 0 20px 50px rgba(15, 23, 42, 0.04) !important;
    text-align: center !important;
    animation: slideUp 0.6s ease-out;
}

.success-icon-wrapper {
    display: flex !important;
    justify-content: center !important;
    margin-bottom: 32px !important;
}

.success-icon {
    width: 80px !important;
    height: 80px !important;
    background: rgba(37, 99, 235, 0.08) !important;
    border-radius: 50% !important;
    display: flex !important;
    align-items: center !important;
    justify-content: center !important;
    color: #2563eb !important;
    font-size: 36px !important;
}

.thank-you-header h1 {
    font-size: 42px !important;
    font-weight: 800 !important;
    color: #0f172a !important;
    margin-bottom: 16px !important;
    letter-spacing: -1px !important;
}

.thank-you-header .subheading {
    font-size: 18px !important;
    color: #64748b !important;
    line-height: 1.6 !important;
    max-width: 500px !important;
    margin: 0 auto !important;
}

.divider {
    height: 1px !important;
    background: #f1f5f9 !important;
    margin: 40px 0 !important;
    width: 100% !important;
}

.confirmation-content {
    text-align: left !important;
    font-size: 16px !important;
    line-height: 1.8 !important;
    color: #334155 !important;
    margin-bottom: 40px !important;
    padding: 0 20px !important;
}

.confirmation-content h3 {
    font-size: 20px !important;
    font-weight: 700 !important;
    color: #0f172a !important;
    margin-bottom: 12px !important;
}

.info-box {
    background: #f8fafc !important;
    border-radius: 16px !important;
    padding: 16px 24px !important;
    display: inline-flex !important;
    align-items: center !important;
    gap: 12px !important;
    font-size: 14px !important;
    color: #64748b !important;
    font-weight: 500 !important;
    margin-bottom: 40px !important;
}

.info-box i {
    color: #94a3b8 !important;
    font-size: 16px !important;
}

.action-buttons {
    display: flex !important;
    justify-content: center !important;
    gap: 16px !important;
}

.btn-primary-pill {
    height: 54px !important;
    padding: 0 32px !important;
    background: #2563eb !important;
    color: #ffffff !important;
    border-radius: 999px !important;
    display: flex !important;
    align-items: center !important;
    text-decoration: none !important;
    font-size: 15px !important;
    font-weight: 700 !important;
    transition: all 0.2s ease !important;
    box-shadow: 0 8px 20px rgba(37, 99, 235, 0.15) !important;
}

.btn-primary-pill:hover {
    transform: translateY(-2px) !important;
    box-shadow: 0 12px 24px rgba(37, 99, 235, 0.25) !important;
    background: #1d4ed8 !important;
}

.btn-secondary-pill {
    height: 54px !important;
    padding: 0 32px !important;
    background: #ffffff !important;
    color: #475569 !important;
    border: 1px solid #e2e8f0 !important;
    border-radius: 999px !important;
    display: flex !important;
    align-items: center !important;
    text-decoration: none !important;
    font-size: 15px !important;
    font-weight: 600 !important;
    transition: all 0.2s ease !important;
}

.btn-secondary-pill:hover {
    background: #f8fafc !important;
    color: #0f172a !important;
    border-color: #cbd5e1 !important;
}

@keyframes slideUp {
    from { opacity: 0; transform: translateY(30px); }
    to { opacity: 1; transform: translateY(0); }
}

@media (max-width: 640px) {
    .thank-you-card { padding: 40px 24px !important; }
    .thank-you-header h1 { font-size: 32px !important; }
    .action-buttons { flex-direction: column !important; }
    .btn-primary-pill, .btn-secondary-pill { width: 100% !important; justify-content: center !important; }
}
</style>
