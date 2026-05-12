<div class="row g-4">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <div>
                <h2 class="fw-bold h4 mb-1"><?php echo __('Search Results');?></h2>
                <p class="text-muted small mb-0"><?php echo sprintf(__('%d FAQs matched your search criteria.'), $faqs->count()); ?></p>
            </div>
            <a href="faq.php" class="btn btn-light btn-sm rounded-pill border px-3"><i class="fa-solid fa-arrow-left me-1"></i><?php echo __('Back to KB'); ?></a>
        </div>

        <?php if ($faqs->exists(true)) { ?>
            <div class="faq-results">
                <?php foreach ($faqs as $F) { ?>
                    <div class="glass-card mb-3 p-4 transition-all hover-lift shadow-sm">
                        <div class="d-flex align-items-center gap-3">
                            <div class="icon-box bg-primary-subtle text-primary mb-0" style="width: 40px; height: 40px; font-size: 1rem;">
                                <i class="fa-solid fa-file-lines"></i>
                            </div>
                            <div class="flex-grow-1">
                                <h3 class="fw-bold h6 mb-1">
                                    <a href="faq.php?id=<?php echo $F->getId(); ?>" class="text-decoration-none text-main stretched-link">
                                        <?php echo $F->getLocalQuestion(); ?>
                                    </a>
                                </h3>
                                <div class="text-muted x-small"><?php echo $F->getVisibilityDescription(); ?></div>
                            </div>
                            <div class="text-muted x-small">
                                <i class="fa-solid fa-chevron-right"></i>
                            </div>
                        </div>
                    </div>
                <?php } ?>
            </div>
        <?php } else { ?>
            <div class="glass-card text-center py-5 shadow-sm">
                <div class="icon-box mx-auto mb-4" style="width: 70px; height: 70px; font-size: 1.5rem;">
                    <i class="fa-solid fa-magnifying-glass text-muted"></i>
                </div>
                <h3 class="fw-bold h5 mb-2"><?php echo __('No Results Found'); ?></h3>
                <p class="text-muted mb-0"><?php echo __('The search did not match any FAQs.'); ?></p>
            </div>
        <?php } ?>
    </div>

    <div class="col-lg-4">
        <div class="sticky-top" style="top: 100px;">
            <div class="support-card p-4 mb-4">
                <h4 class="fw-bold h6 mb-3 text-primary"><i class="fa-solid fa-magnifying-glass me-2"></i><?php echo __('Search Again'); ?></h4>
                <form method="get" action="faq.php">
                    <input type="hidden" name="a" value="search"/>
                    <div class="input-group">
                        <input type="text" name="q" class="form-control modern-form-control border-end-0" placeholder="<?php echo __('Search keywords...'); ?>" value="<?php echo Format::htmlchars($_REQUEST['q']); ?>"/>
                        <button class="btn btn-outline-primary border-start-0" type="submit"><i class="fa-solid fa-search"></i></button>
                    </div>
                </form>
            </div>

            <div class="support-card p-4 mb-4">
                <h4 class="fw-bold h6 mb-3 text-primary"><i class="fa-solid fa-tags me-2"></i><?php echo __('Help Topics'); ?></h4>
                <div class="d-flex flex-column gap-2">
                    <?php
                    foreach (Topic::objects()->annotate(array('faqs_count'=>SqlAggregate::COUNT('faqs')))->filter(array('faqs_count__gt'=>0)) as $t) { ?>
                        <a href="?topicId=<?php echo urlencode($t->getId()); ?>" class="text-decoration-none text-muted small hover-primary p-2 rounded-3 bg-light-hover border border-transparent d-flex justify-content-between align-items-center">
                            <span><?php echo $t->getFullName(); ?></span>
                            <span class="badge bg-light text-muted border fw-normal x-small"><?php echo $t->faqs_count; ?></span>
                        </a>
                    <?php } ?>
                </div>
            </div>

            <div class="support-card p-4">
                <h4 class="fw-bold h6 mb-3 text-primary"><i class="fa-solid fa-folder me-2"></i><?php echo __('Categories'); ?></h4>
                <div class="d-flex flex-column gap-2">
                    <?php
                    foreach (Category::objects()->exclude(Q::any(array('ispublic'=>Category::VISIBILITY_PRIVATE)))->annotate(array('faqs_count'=>SqlAggregate::COUNT('faqs')))->filter(array('faqs_count__gt'=>0)) as $C) { ?>
                        <a href="?cid=<?php echo urlencode($C->getId()); ?>" class="text-decoration-none text-muted small hover-primary p-2 rounded-3 bg-light-hover border border-transparent d-flex justify-content-between align-items-center">
                            <span><?php echo $C->getLocalName(); ?></span>
                            <span class="badge bg-light text-muted border fw-normal x-small"><?php echo $C->faqs_count; ?></span>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
