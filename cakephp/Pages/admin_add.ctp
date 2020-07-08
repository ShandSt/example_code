<div class="pages form">
    <div class="row">
        <div class="col-md-12">
            <ul class="nav nav-pills pull-right">
                <li>
                    <?= $this->Html->link(
                        __('<span class="glyphicon glyphicon-list"></span> List Pages'),
                        array('action' => 'index'),
                        array('escape' => false)) ?>
                </li>
                <li>
                    <?= $this->Html->link(
                        __('<span class="glyphicon glyphicon-list"></span> List Domains'),
                        array('controller' => 'domains', 'action' => 'index'),
                        array('escape' => false)) ?>
                </li>
                <li>
                    <?= $this->Html->link(
                        __('<span class="glyphicon glyphicon-plus"></span> New Domain'),
                        array('controller' => 'domains', 'action' => 'add'),
                        array('escape' => false)) ?>
                </li>
            </ul>
            <div class="page-header">
                <h1><?= __('Admin Edit Page') ?></h1>
            </div>
        </div>
    </div>
    <div class="row">
        <div class="col-md-12">
            <?= $this->Form->create('Page', array('role' => 'form')) ?>

            <div class="row">
                <div class="col-sm-3">
                    <?= $this->Form->input('domain_id', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-3">
                    <?= $this->Form->input('parent_id', array(
                        'class' => 'form-control',
                        'div' => 'form-group',
                        'empty' => '-'
                    )) ?>
                </div>
                <div class="col-sm-3">
                    <?= $this->Form->input('created', array(
                        'type' => 'text',
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-3">
                    <?= $this->Form->input('page_category_id', array(
                        'class' => 'form-control',
                        'div' => 'form-group',
                        'empty' => '-'
                    )) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $this->Form->input('title', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-6">
                    <?= $this->Form->input('alias', array(
                        'class' => 'form-control input-alias',
                        'div' => 'form-group'
                    )) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $this->Form->input('h1', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-6">
                    <?= $this->Form->input('anchor_title', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
            </div>
            <div class="form-group">
                <label for="PageMetaTitle">Meta Title</label>
                <?
                echo $this->Form->input('meta_title', array(
                    'class' => 'form-control',
                    'div' => 'input-group',
                    'after' => "<span id=\"titleCount\" class=\"input-group-addon\">0</span>",
                    'label' => false
                ))
                ?>
            </div>
            <div class="form-group">
                <label for="PageMetaTitle">Meta Description</label>
                <?
                echo $this->Form->input('meta_description', array(
                    'class' => 'form-control',
                    'div' => 'input-group',
                    'after' => "<span id=\"descriptionCount\" class=\"input-group-addon\">0</span>",
                    'label' => false,
                    'rows' => 2
                ))
                ?>
            </div>
            <? if ( Configure::read('html_view') ): ?>
                <?= $this->Form->input('text', array('type' => 'hidden')) ?>
                <div class="form-group">
                    <label>Text</label>
                    <div id="editor"></div>
                </div>
            <? else: ?>
                <?= $this->Form->input('text', array(
                    'class' => 'form-control ckeditor',
                    'div' => 'form-group'
                )) ?>
            <? endif ?>
            <?
            echo $this->Form->input('sitemap', array(
                'div' => 'form-group checkbox',
                'label' => 'Show in sitemap'
            ));
            ?>
            <?
            echo $this->Form->input('show_best_offers', array(
                'div' => 'form-group checkbox',
                'label' => 'Show best offers'
            ));
            ?>
            <?
            echo $this->Form->input('noindex_best_offers', array(
                'div' => 'form-group checkbox',
                'label' => 'Noindex best offers'
            ));
            ?>
            <div class="row">
                <div class="col-sm-8">
                    <?
                    echo $this->Form->input('sort', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    ));

                    echo $this->Form->input('meta_keywords', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    ));

                    echo $this->Form->input('info', array(
                        'class' => 'form-control',
                        'div' => 'form-group',
                        'rows' => 2
                    ));


                    echo $this->Form->input('info_widget_ids', array(
                    'class' => 'form-control',
                    'div' => 'form-group',
                    'placeholder' => '1;3;4'
                    ));

                    echo $this->Form->input('first_company_id', array(
                    'empty' => '-',
                    'class' => 'form-control',
                    'div' => 'form-group',
                    ));
                    echo $this->Form->input('second_company_id', array(
                    'empty' => '-',
                    'class' => 'form-control',
                    'div' => 'form-group',
                    ));

                    ?>
                </div>
            </div>


            <?

            echo $this->Form->submit(__('Submit'), array('class' => 'btn btn-default'));
            echo $this->Form->end();
            ?>
        </div><!-- end col md 12 -->
    </div><!-- end row -->
</div>
<script>
    $('#PageMetaDescription').keyup(function() {
        var count = $(this).val().length;
        $('#descriptionCount').text(count);
    });
    $('#PageMetaTitle').keyup(function() {
        var count = $(this).val().length;
        $('#titleCount').text(count);
    })
</script>
<? if ( Configure::read('html_view') ) { ?>
    <?= $this->element('ace_initialize'); ?>
<? } ?>