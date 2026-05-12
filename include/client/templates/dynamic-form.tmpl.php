<?php
// Return if no visible fields
global $thisclient;
if (!$form->hasAnyVisibleFields($thisclient))
    return;

$isCreate = (isset($options['mode']) && $options['mode'] == 'create');
?>
<div class="dynamic-form-container mt-4">
    <div class="form-header mb-4">
        <h3 class="fw-bold h5 text-primary mb-2"><?php echo Format::htmlchars($form->getTitle()); ?></h3>
        <?php if ($form->getInstructions()) { ?>
            <div class="text-muted small"><?php echo Format::display($form->getInstructions()); ?></div>
        <?php } ?>
    </div>

    <div class="row g-4">
    <?php
    foreach ($form->getFields() as $field) {
        try {
            if (!$field->isEnabled())
                continue;
        }
        catch (Exception $e) {}

        if ($isCreate) {
            if (!$field->isVisibleToUsers() && !$field->isRequiredForUsers())
                continue;
        } elseif (!$field->isVisibleToUsers()) {
            continue;
        }
        ?>
        <div class="col-12">
            <div class="form-group mb-2">
                <?php if (!$field->isBlockLevel()) { ?>
                    <label class="form-label fw-semibold small mb-2 d-block <?php if ($field->isRequiredForUsers()) echo 'required'; ?>" for="<?php echo $field->getFormName(); ?>">
                        <?php echo Format::htmlchars($field->getLocal('label')); ?>
                        <?php if ($field->isRequiredForUsers() && ($field->isEditableToUsers() || $isCreate)) { ?>
                            <span class="text-danger">*</span>
                        <?php } ?>
                        
                        <?php if ($field->get('hint')) { ?>
                            <div class="text-muted fw-normal x-small" style="font-size: 0.75rem; margin-top: 2px;">
                                <?php echo Format::viewableImages($field->getLocal('hint')); ?>
                            </div>
                        <?php } ?>
                    </label>
                <?php } ?>

                <div class="modern-field-container">
                <?php
                if ($field->isEditableToUsers() || $isCreate) {
                    $field->render(array('client'=>true));
                    
                    foreach ($field->errors() as $e) { ?>
                        <div class="text-danger small mt-1"><i class="fa-solid fa-circle-exclamation me-1"></i><?php echo $e; ?></div>
                    <?php }
                    $field->renderExtras(array('client'=>true));
                } else {
                    $val = '';
                    if ($field->value)
                        $val = $field->display($field->value);
                    elseif (($a=$field->getAnswer()))
                        $val = $a->display();

                    echo sprintf('<div class="p-3 bg-light rounded-3 border fw-medium">%s</div>', $val);
                }
                ?>
                </div>
            </div>
        </div>
        <?php
    }
    ?>
    </div>
</div>
<hr class="my-5 opacity-25">
