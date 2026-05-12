<?php
if(!defined('OSTCLIENTINC')) die('Access Denied!');
$info=array();
if($thisclient && $thisclient->isValid()) {
    $info=array('name'=>$thisclient->getName(),
                'email'=>$thisclient->getEmail(),
                'phone'=>$thisclient->getPhoneNumber());
}

$info=($_POST && $errors)?Format::htmlchars($_POST):$info;

$form = null;
if (!$info['topicId']) {
    if (array_key_exists('topicId',$_GET) && preg_match('/^\d+$/',$_GET['topicId']) && Topic::lookup($_GET['topicId']))
        $info['topicId'] = intval($_GET['topicId']);
    else
        $info['topicId'] = $cfg->getDefaultTopicId();
}

$forms = array();
if ($info['topicId'] && ($topic=Topic::lookup($info['topicId']))) {
    foreach ($topic->getForms() as $F) {
        if (!$F->hasAnyVisibleFields())
            continue;
        if ($_POST) {
            $F = $F->instanciate();
            $F->isValidForClient();
        }
        $forms[] = $F->getForm();
    }
}
?>

<?php if (isset($ticket) && $ticket instanceof Ticket): ?>
    <div id="atlas_saas_portal">
        <div class="success-page">
            <div class="success-shell">
                <div class="success-icon-container">
                    <div class="success-icon-inner">
                        <i class="fa-solid fa-check"></i>
                    </div>
                </div>

                <div class="success-badge-institutional">
                    SUPPORT REQUEST RECEIVED
                </div>

                <h1 class="success-title-main">
                    Ticket Successfully Created
                </h1>

                <p class="success-description-text">
                    Your support request has been submitted successfully. Our engineering team will review your ticket shortly and provide a resolution within our standard service tier.
                </p>

                <div class="success-meta-grid">
                    <div class="meta-card-item">
                        <span class="meta-card-label">Response Time</span>
                        <span class="meta-card-value">Under 2 Hours</span>
                    </div>

                    <div class="meta-card-item">
                        <span class="meta-card-label">Ticket Status</span>
                        <span class="meta-card-value status-active">Active</span>
                    </div>
                </div>

                <div class="success-action-container">
                    <a href="index.php" class="btn-success-institutional-primary">
                        Return to Support Center
                    </a>
                    <a href="view.php" class="btn-success-institutional-secondary">
                        Track Ticket
                    </a>
                </div>
            </div>
        </div>
    </div>
<?php else: ?>

<div id="atlas_saas_portal">
    <div class="container-main">
        
        <!-- PAGE HEADER & HERO -->
        <div style="margin-bottom: 54px;">
            <h1 style="font-size: 54px; font-weight: 800; letter-spacing: -0.04em; color: #0F172A; margin-bottom: 24px;">
                Open a New Ticket
            </h1>
            <div class="info-alert-saas" style="background: #F0F9FF; border: 1px solid rgba(56, 189, 248, 0.15); border-radius: 16px; padding: 24px; display: flex; align-items: flex-start; gap: 16px;">
                <div style="width: 40px; height: 40px; background: #38BDF8; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: #fff; flex-shrink: 0;">
                    <i class="fas fa-info" style="font-size: 14px;"></i>
                </div>
                <div>
                    <p style="margin: 0; color: #475569; font-size: 15px; line-height: 1.6; font-weight: 500;">
                        Please fill in the form below to open a new ticket. Our professional support framework ensures your technical and account queries are handled with enterprise-grade efficiency.
                    </p>
                </div>
            </div>
        </div>

        <form id="ticketForm" method="post" action="open.php" enctype="multipart/form-data">
            <?php csrf_token(); ?>
            <input type="hidden" name="a" value="open">

            <!-- 1. CONTACT INFORMATION -->
            <div style="margin-bottom: 60px;">
                <h2 style="font-size: 24px; font-weight: 700; color: #0F172A; margin-bottom: 32px; display: flex; align-items: center; gap: 12px;">
                    <span style="width: 32px; height: 32px; background: #F1F5F9; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #64748B;">01</span>
                    Contact Information
                </h2>
                
                <?php if (!$thisclient) { ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-bottom: 28px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Email Address</label>
                            <input type="email" name="email" placeholder="e.g. name@company.com" value="<?php echo $info['email']; ?>">
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Full Name</label>
                            <input type="text" name="name" placeholder="e.g. John Doe" value="<?php echo $info['name']; ?>">
                        </div>
                    </div>
                    <div class="phone-row">
                        <div class="phone-group">
                            <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Phone Number</label>
                            <input type="tel" name="phone" placeholder="+1 (555) 000-0000" value="<?php echo $info['phone']; ?>">
                        </div>
                        <div class="ext-group">
                            <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Ext</label>
                            <input type="text" name="phone_ext" placeholder="123" value="<?php echo $info['phone_ext']; ?>">
                        </div>
                    </div>
                <?php } else { ?>
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 28px; margin-bottom: 28px;">
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Email Address</label>
                            <input type="text" value="<?php echo $thisclient->getEmail(); ?>" disabled style="background: #F8FAFC !important; color: #94A3B8 !important; cursor: not-allowed;">
                        </div>
                        <div>
                            <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Full Name</label>
                            <input type="text" value="<?php echo Format::htmlchars($thisclient->getName()); ?>" disabled style="background: #F8FAFC !important; color: #94A3B8 !important; cursor: not-allowed;">
                        </div>
                    </div>
                    <div class="phone-row">
                        <div class="phone-group">
                            <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Phone Number</label>
                            <input type="text" value="<?php echo $thisclient->getPhoneNumber(); ?>" placeholder="+1 (555) 000-0000" disabled style="background: #F8FAFC !important; color: #94A3B8 !important; cursor: not-allowed;">
                        </div>
                        <div class="ext-group">
                            <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Ext</label>
                            <input type="text" value="" placeholder="123" disabled style="background: #F8FAFC !important; color: #94A3B8 !important; cursor: not-allowed;">
                        </div>
                    </div>
                <?php } ?>
            </div>

            <!-- 2. SUPPORT CATEGORY -->
            <div style="margin-bottom: 60px;">
                <h2 style="font-size: 24px; font-weight: 700; color: #0F172A; margin-bottom: 32px; display: flex; align-items: center; gap: 12px;">
                    <span style="width: 32px; height: 32px; background: #F1F5F9; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #64748B;">02</span>
                    Support Category
                </h2>
                <div style="max-width: 500px;">
                    <label for="topicId" style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 10px;">Help Topic</label>
                    <select id="topicId" name="topicId" onchange="javascript:
                            var data = $(':input[name]', '#dynamic-form').serialize();
                            $.ajax(
                              'ajax.php/form/help-topic/' + this.value,
                              {
                                data: data,
                                dataType: 'json',
                                success: function(json) {
                                  $('#dynamic-form').empty().append(json.html);
                                  $(document.head).append(json.media);
                                }
                              });">
                        <option value="" selected="selected">&mdash; Select a Help Topic &mdash;</option>
                        <?php
                        if($topics=Topic::getPublicHelpTopics()) {
                            foreach($topics as $id =>$name) {
                                echo sprintf('<option value="%d" %s>%s</option>',
                                        $id, ($info['topicId']==$id)?'selected="selected"':'', $name);
                            }
                        } ?>
                    </select>
                </div>
            </div>

            <!-- 3. TICKET DETAILS -->
            <div style="margin-bottom: 60px;">
                <h2 style="font-size: 24px; font-weight: 700; color: #0F172A; margin-bottom: 32px; display: flex; align-items: center; gap: 12px;">
                    <span style="width: 32px; height: 32px; background: #F1F5F9; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 14px; color: #64748B;">03</span>
                    Ticket Details
                </h2>
                <div id="dynamic-form">
                    <?php
                    foreach ($forms as $form) {
                        include(CLIENTINC_DIR . 'templates/dynamic-form.tmpl.php');
                    } ?>
                </div>
            </div>

            <!-- 4. SECURITY -->
            <?php if($cfg->isCaptchaEnabled() && (!$thisclient || !$thisclient->isValid())) { ?>
                <div style="margin-bottom: 60px; padding: 32px; background: #F8FAFC; border-radius: 20px; border: 1px solid #E2E8F0;">
                    <label style="display: block; font-size: 14px; font-weight: 600; color: #64748B; margin-bottom: 16px;">Security Verification</label>
                    <div style="display: flex; align-items: center; gap: 24px;">
                        <div style="background: #fff; padding: 10px; border-radius: 12px; border: 1px solid #E2E8F0;">
                            <img src="captcha.php" border="0" style="border-radius: 6px;">
                        </div>
                        <input id="captcha" type="text" name="captcha" size="6" placeholder="Enter code" style="max-width: 200px;">
                    </div>
                </div>
            <?php } ?>

            <!-- ACTION BUTTONS -->
            <div style="display: flex; align-items: center; gap: 20px; padding-top: 20px; border-top: 1px solid #F1F5F9;">
                <button type="submit" class="btn-submit-enterprise" style="height: 58px; padding: 0 40px; border-radius: 14px; background: linear-gradient(135deg, #38BDF8, #2563EB); color: #fff; font-weight: 700; border: none; cursor: pointer; box-shadow: 0 10px 25px rgba(37, 99, 235, 0.2);">
                    Submit Ticket
                </button>
                <button type="reset" style="height: 58px; padding: 0 32px; border-radius: 14px; background: #fff; border: 1px solid #E2E8F0; color: #64748B; font-weight: 600; cursor: pointer;">
                    Reset Form
                </button>
                <button type="button" onclick="window.location.href='index.php'" style="background: none; border: none; color: #94A3B8; font-weight: 600; cursor: pointer; padding: 0 20px;">
                    Cancel
                </button>
            </div>

        </form>
    </div>
</div>

<script type="text/javascript">
(function() {
    // NATIVE PREVIEW ENGINE: Replaces broken filedropbox plugin
    window.addEventListener('DOMContentLoaded', function() {
        const portal = document.querySelector('#atlas_saas_portal');
        if (!portal) return;

        // 1. STABLE UPLOAD TRIGGER (No Page Jump)
        portal.addEventListener('click', function(e) {
            const filedrop = e.target.closest('.filedrop');
            const removeBtn = e.target.closest('.remove-preview');

            if (removeBtn) {
                e.preventDefault();
                e.stopPropagation();
                const card = removeBtn.closest('.upload-preview-card');
                if (card) card.remove();
                return;
            }

            if (filedrop) {
                // Ignore clicks on internal inputs or previews
                if (e.target.tagName === 'INPUT' || e.target.closest('.preview-grid')) return;

                e.preventDefault();
                e.stopPropagation();

                const input = filedrop.querySelector('input[type="file"]');
                if (input) {
                    input.click();
                }
            }
        });

        // 2. NATIVE PREVIEW RENDERER
        $(document).on('change', '#atlas_saas_portal input[type="file"]', function(e) {
            const files = this.files;
            const container = this.closest('.filedrop');
            if (!container || !files.length) return;

            // Clear previous errors
            const oldError = container.querySelector('.upload-error');
            if (oldError) oldError.remove();

            Array.from(files).forEach(file => {
                try {
                    const isImage = file.type.startsWith('image/');
                    const previewUrl = isImage ? URL.createObjectURL(file) : '';
                    
                    // Ensure preview grid exists
                    let grid = container.querySelector('.preview-grid');
                    if (!grid) {
                        grid = document.createElement('div');
                        grid.className = 'preview-grid';
                        grid.style.cssText = 'display:grid; grid-template-columns:repeat(auto-fill, minmax(240px, 1fr)); gap:16px; width:100%; margin-top:20px;';
                        container.appendChild(grid);
                    }

                    const card = document.createElement('div');
                    card.className = 'upload-preview-card';
                    card.innerHTML = `
                        ${isImage ? `<img src="${previewUrl}" class="preview-image" style="width:64px; height:64px; border-radius:12px; object-fit:cover;">` : `<div style="width:64px; height:64px; background:#F1F5F9; border-radius:12px; display:flex; align-items:center; justify-content:center; color:#64748B; font-size:24px;">📄</div>`}
                        <div class="preview-meta" style="flex:1;">
                            <div class="preview-name" style="font-size:14px; font-weight:600; color:#0F172A; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; max-width:150px;">${file.name}</div>
                            <div class="upload-success" style="font-size:12px; color:#10B981; font-weight:500;">✅ Successfully uploaded</div>
                        </div>
                        <button type="button" class="remove-preview" style="width:32px; height:32px; background:#FEF2F2; color:#EF4444; border-radius:8px; border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:14px;"><i class="fa-solid fa-trash-can"></i></button>
                    `;
                    card.style.cssText = 'background:#FFFFFF; border:1px solid #E2E8F0; border-radius:16px; padding:12px; display:flex; align-items:center; gap:12px; box-shadow:0 4px 10px rgba(0,0,0,0.03); transition:all 0.2s ease;';
                    
                    grid.appendChild(card);
                } catch (err) {
                    console.error("[UPLOAD ERROR]", err);
                    const errorDiv = document.createElement('div');
                    errorDiv.className = 'upload-error';
                    errorDiv.style.cssText = 'color:#EF4444; font-size:13px; font-weight:500; margin-top:10px;';
                    errorDiv.innerHTML = `❌ Upload failed for ${file.name}. Please try again.`;
                    container.appendChild(errorDiv);
                }
            });
        });

        // 3. STABILITY FOR DRAG & DROP
        $(document).on('dragover', '#atlas_saas_portal .filedrop', function() {
            $(this).css({ 'border-color': '#38BDF8', 'background': '#EFF6FF' });
        }).on('dragleave drop', '#atlas_saas_portal .filedrop', function() {
            $(this).css({ 'border-color': '#93C5FD', 'background': '#F8FAFC' });
        });

        // 4. DEEP SUBMISSION DEBUGGING
        const ticketForm = portal.querySelector('form');
        if (ticketForm) {
            ticketForm.addEventListener('submit', function(e) {
                const fd = new FormData(this);
            });
        }
    });
})();
</script>
<?php endif; ?>
