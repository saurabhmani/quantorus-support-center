<div class="knowledgebase-wrapper" data-aos="fade-up">
    <div class="d-flex align-items-center gap-3 mb-5">
        <div style="width: 40px; height: 4px; background: var(--primary-blue); border-radius: 10px;"></div>
        <h1 class="fw-bold h2 mb-0"><?php echo __('Knowledge Base'); ?></h1>
    </div>

    <?php
    if($_REQUEST['q'] || $_REQUEST['cid'] || $_REQUEST['topicId']) { //Search
        $faqs = FAQ::allPublic()
            ->annotate(array(
                'attachment_count'=>SqlAggregate::COUNT('attachments'),
                'topic_count'=>SqlAggregate::COUNT('topics')
            ))
            ->order_by('question');

        if ($_REQUEST['cid'])
            $faqs->filter(array('category_id'=>$_REQUEST['cid']));

        if ($_REQUEST['topicId'])
            $faqs->filter(array('topics__topic_id'=>$_REQUEST['topicId']));

        if ($_REQUEST['q'])
            $faqs->filter(Q::all(array(
                Q::ANY(array(
                    'question__contains'=>$_REQUEST['q'],
                    'answer__contains'=>$_REQUEST['q'],
                    'keywords__contains'=>$_REQUEST['q'],
                    'category__name__contains'=>$_REQUEST['q'],
                    'category__description__contains'=>$_REQUEST['q'],
                ))
            )));

        echo '<div class="glass-card p-4">';
        include CLIENTINC_DIR . 'kb-search.inc.php';
        echo '</div>';

    } else { //Category Listing.
        echo '<div class="kb-categories-modern">';
        include CLIENTINC_DIR . 'kb-categories.inc.php';
        echo '</div>';
    }
    ?>
</div>
