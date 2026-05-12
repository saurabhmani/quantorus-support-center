<div class="row g-4">
    <div class="col-lg-8">
        <div class="d-flex align-items-center justify-content-between mb-4">
            <h2 class="fw-bold h4 mb-0"><i class="fa-solid fa-book-open me-2 text-primary"></i><?php echo __('Browse Knowledge Base'); ?></h2>
            <div class="text-muted small"><?php echo __('Click on a category to explore'); ?></div>
        </div>

        <?php
        $categories = Category::objects()
            ->exclude(Q::any(array(
                'ispublic'=>Category::VISIBILITY_PRIVATE,
                Q::all(array(
                    'faqs__ispublished'=>FAQ::VISIBILITY_PRIVATE,
                    'children__ispublic' => Category::VISIBILITY_PRIVATE,
                    'children__faqs__ispublished'=>FAQ::VISIBILITY_PRIVATE,
                ))
            )))
            ->annotate(array('faq_count' => SqlAggregate::COUNT(
                SqlCase::N()->when(array('faqs__ispublished__gt'=> FAQ::VISIBILITY_PRIVATE), 1)->otherwise(null)
            )))
            ->annotate(array('children_faq_count' => SqlAggregate::COUNT(
                SqlCase::N()->when(array('children__faqs__ispublished__gt'=> FAQ::VISIBILITY_PRIVATE), 1)->otherwise(null)
            )));

        if ($categories->exists(true)) {
            foreach ($categories as $C) {
                if (($p=$C->parent) && ($categories->findFirst(array('category_id' => $p->getId()))))
                    continue;

                $count = $C->faq_count + $C->children_faq_count;
                ?>
                <div class="glass-card mb-4 p-4 transition-all hover-lift shadow-sm">
                    <div class="d-flex gap-3">
                        <div class="icon-box bg-primary-subtle text-primary mb-0" style="width: 45px; height: 45px; font-size: 1.2rem;">
                            <i class="fa-solid fa-folder-open"></i>
                        </div>
                        <div class="flex-grow-1">
                            <h3 class="fw-bold h5 mb-2">
                                <a href="faq.php?cid=<?php echo $C->getId(); ?>" class="text-decoration-none text-main">
                                    <?php echo Format::htmlchars($C->getLocalName()); ?>
                                    <?php if ($count) { ?>
                                        <span class="badge bg-light text-muted border fw-normal ms-2" style="font-size: 0.75rem;"><?php echo $count; ?> FAQs</span>
                                    <?php } ?>
                                </a>
                            </h3>
                            <div class="text-muted small mb-3">
                                <?php echo Format::safe_html($C->getLocalDescriptionWithImages()); ?>
                            </div>

                            <?php if (($subs=$C->getPublicSubCategories())) { ?>
                                <div class="subcategories d-flex flex-wrap gap-2 mb-3">
                                    <?php foreach ($subs as $c) { ?>
                                        <a href="faq.php?cid=<?php echo $c->getId(); ?>" class="btn btn-light btn-sm border rounded-pill px-3 py-1 x-small text-muted">
                                            <i class="fa-solid fa-folder me-1 opacity-50"></i><?php echo $c->getLocalName(); ?> (<?php echo $c->faq_count; ?>)
                                        </a>
                                    <?php } ?>
                                </div>
                            <?php } ?>

                            <div class="faq-list">
                                <?php foreach ($C->faqs->exclude(array('ispublished'=>FAQ::VISIBILITY_PRIVATE))->limit(5) as $F) { ?>
                                    <div class="mb-2">
                                        <a href="faq.php?id=<?php echo $F->getId(); ?>" class="text-decoration-none text-muted small d-flex align-items-center gap-2 hover-primary">
                                            <i class="fa-solid fa-file-lines opacity-25"></i>
                                            <span><?php echo $F->getLocalQuestion() ?: $F->getQuestion(); ?></span>
                                        </a>
                                    </div>
                                <?php } ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php }
        } else { ?>
            <div class="glass-card text-center py-5">
                <i class="fa-solid fa-circle-info fa-3x text-muted mb-3"></i>
                <p class="text-muted"><?php echo __('No FAQs found'); ?></p>
            </div>
        <?php } ?>
    </div>

    <div class="col-lg-4">
        <div class="sticky-top" style="top: 100px;">
            <div class="support-card p-4 mb-4">
                <h4 class="fw-bold h6 mb-3 text-primary"><i class="fa-solid fa-magnifying-glass me-2"></i><?php echo __("Browse by Topic"); ?></h4>
                <form method="get" action="faq.php">
                    <input type="hidden" name="a" value="search"/>
                    <select name="topicId" class="form-select modern-form-control" onchange="javascript:this.form.submit();">
                        <option value="">—<?php echo __("Select a Topic"); ?>—</option>
                        <?php
                        $topics = Topic::objects()->annotate(array('has_faqs'=>SqlAggregate::COUNT('faqs')))->filter(array('has_faqs__gt'=>0));
                        foreach ($topics as $T) { ?>
                            <option value="<?php echo $T->getId(); ?>"><?php echo $T->getFullName(); ?></option>
                        <?php } ?>
                    </select>
                </form>
            </div>

            <div class="support-card p-4">
                <h4 class="fw-bold h6 mb-3 text-primary"><i class="fa-solid fa-link me-2"></i><?php echo __('Other Resources'); ?></h4>
                <div class="d-flex flex-column gap-2">
                    <?php 
                    $resources = Page::getActivePages()->filter(array('type'=>'other'));
                    foreach ($resources as $page) { ?>
                        <a href="<?php echo ROOT_PATH; ?>pages/<?php echo $page->getNameAsSlug(); ?>" class="text-decoration-none text-muted small hover-primary p-2 rounded-3 bg-light-hover border border-transparent">
                            <i class="fa-solid fa-chevron-right me-2 x-small opacity-50"></i><?php echo $page->getLocalName(); ?>
                        </a>
                    <?php } ?>
                </div>
            </div>
        </div>
    </div>
</div>
