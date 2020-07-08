<div class="pages index">
    <div class="row">
        <div class="col-md-12">
            <div class="page-header pull-left">
                <h1><?= __('Pages') ?></h1>
            </div>
            <div class="pull-right">
                <ul class="nav nav-pills">
                    <li>
                        <?= $this->Html->link(
                            __('<span class="glyphicon glyphicon-refresh"></span> Reorder'),
                            array('action' => 'reorder'),
                            array('escape' => false)) ?>
                    </li>
                    <li>
                        <?= $this->Html->link(
                            __('<span class="glyphicon glyphicon-plus"></span> New Page'),
                            array('action' => 'add', '?' => array(
                                'domain_id' => $currentDomain
                            )),
                            array('escape' => false)) ?>
                    </li>
                </ul>
            </div>
            <div class="search-query pull-right">
                <form method="GET">

                </form>
            </div>
            <div class="domain-select pull-right">
                <form class="submit-on-change form-inline" method="GET" action="/admin/pages/index">
                    <?= $this->Form->input('domain_id', array(
                        'type' => 'select',
                        'class' => 'form-control',
                        'label' => false,
                        'name' => 'domain_id',
                        'options' => $domains,
                        'value' => $currentDomain,
                        'div' => false
                    )) ?>
                    <div class="input-group" style="width: 300px">
                        <input type="text" name="query" class="form-control" value="<?= $query ?>"
                               placeholder="Поиск по заголовку">
                        <span class="input-group-btn">
							<button class="btn btn-default" type="submit">Искать</button>
						</span>
                    </div>
                </form>
            </div>
        </div><!-- end col md 12 -->
    </div><!-- end row -->
    <div class="row">
        <div class="col-md-12">
            <div class="table-responsive">
                <table class="table table-striped">
                    <thead>
                    <tr>
                        <th><?= $this->Paginator->sort('id') ?></th>
                        <th><?= $this->Paginator->sort('title') ?></th>
                        <th><?= $this->Paginator->sort('alias') ?></th>
                        <th align="center"><?= $this->Paginator->sort('h1_count', 'H1') ?></th>
                        <th align="center"><?= $this->Paginator->sort('anchor_title_count', 'Anchor') ?></th>
                        <th align="center"><?= $this->Paginator->sort('meta_title_count', 'Title') ?></th>
                        <th align="center"><?= $this->Paginator->sort('meta_description_count', 'Desc') ?></th>
                        <th align="center"><?= $this->Paginator->sort('info_count', 'Info') ?></th>
                        <th align="center"><?= $this->Paginator->sort('page_category_id', 'Category') ?></th>
                        <th class="text-center"><?= $this->Paginator->sort(false, 'Anchors') ?></th>
                        <th class="text-center"><?= $this->Paginator->sort(false, 'Related') ?></th>
                        <th align="center"><?= $this->Paginator->sort('symbols') ?></th>
                        <th align="center"><?= $this->Paginator->sort('Sitemap') ?></th>
                        <th align="center" data-toggle="tooltip" data-placement="top" data-container="body"
                            title="Best Offers"><?= $this->Paginator->sort('show_best_offers', 'BO') ?></th>
                        <th align="center" data-toggle="tooltip" data-placement="top" data-container="body"
                            title="Noindex Best Offers"><?= $this->Paginator->sort('noindex_best_offers', 'NBO') ?></th>
                        <th align="center"><?= $this->Paginator->sort('lft', ' ') ?></th>
                        <th align="center"><?= $this->Paginator->sort('rght', ' ') ?></th>
                        <th><?= $this->Paginator->sort('created') ?></th>
                        <th><?= $this->Paginator->sort('modified') ?></th>
                        <th class="actions"></th>
                    </tr>
                    </thead>
                    <tbody>
                    <? foreach ($pages as $page):
                        $parentCount = count( $Page->getPath($page['Page']['id']) ) - 1 ?>
                        <tr>
                            <td class="text-muted small"><?= h($page['Page']['id']) ?></td>
                            <td style="padding-left: <?= $parentCount * 5 + 8 ?>px">
                                <? for ($i = 0; $i < $parentCount; $i++ ) echo '---- ' ?>
                                <?= h($page['Page']['title']) ?>
                            </td>
                            <td class="text-muted small">
                                <?= h($page['Page']['alias']) ?><br>
                            </td>
                            <td align="center" class="meta-edit text-muted small" data-toggle="tooltip" data-placement="top" data-container="body" title="<?= $page['Page']['h1'] ?>">
                                <div class="meta-text"><?= $page['Page']['h1_count'] ? $page['Page']['h1_count'] : '' ?></div>
                                <div class="meta-form" style="display: none">
                                    <?
                                    echo $this->Form->create('Page', array('role' => 'form', 'url' => '/admin/pages/edit_meta'));
                                    echo $this->Form->input('id', array(
                                        'type' => 'hidden',
                                        'value' => $page['Page']['id']
                                    ));
                                    echo $this->Form->input('h1', array(
                                        'type' => 'textarea',
                                        'class' => 'form-control',
                                        'div' => 'form-group',
                                        'label' => false,
                                        'value' => $page['Page']['h1']
                                    ));
                                    echo $this->Form->end();
                                    ?>
                                </div>
                            </td>
                            <td align="center" class="meta-edit text-muted small" data-toggle="tooltip" data-placement="top" data-container="body" title="<?= $page['Page']['anchor_title'] ?>">
                                <div class="meta-text"><?= $page['Page']['anchor_title_count'] ? $page['Page']['anchor_title_count'] : '' ?></div>
                                <div class="meta-form" style="display: none">
                                    <?
                                    echo $this->Form->create('Page', array('role' => 'form', 'url' => '/admin/pages/edit_meta'));
                                    echo $this->Form->input('id', array(
                                        'type' => 'hidden',
                                        'value' => $page['Page']['id']
                                    ));
                                    echo $this->Form->input('anchor_title', array(
                                        'type' => 'textarea',
                                        'class' => 'form-control',
                                        'div' => 'form-group',
                                        'label' => false,
                                        'value' => $page['Page']['anchor_title']
                                    ));
                                    echo $this->Form->end();
                                    ?>
                                </div>
                            </td>
                            <td align="center" class="meta-edit text-muted small" data-toggle="tooltip" data-placement="top" data-container="body" title="<?= $page['Page']['meta_title'] ?>">
                                <div class="meta-text"><?= $page['Page']['meta_title_count'] ? $page['Page']['meta_title_count'] : '' ?></div>
                                <div class="meta-form" style="display: none">
                                    <?
                                    echo $this->Form->create('Page', array('role' => 'form', 'url' => '/admin/pages/edit_meta'));
                                    echo $this->Form->input('id', array(
                                        'type' => 'hidden',
                                        'value' => $page['Page']['id']
                                    ));
                                    echo $this->Form->input('meta_title', array(
                                        'type' => 'textarea',
                                        'class' => 'form-control',
                                        'div' => 'form-group',
                                        'label' => false,
                                        'value' => $page['Page']['meta_title']
                                    ));
                                    echo $this->Form->end();
                                    ?>
                                </div>
                            </td>
                            <td align="center" class="meta-edit text-muted small" data-toggle="tooltip" data-placement="top" data-container="body" title="<?= $page['Page']['meta_description'] ?>">
                                <div class="meta-text"><?= $page['Page']['meta_description_count'] ? $page['Page']['meta_description_count'] : '' ?></div>
                                <div class="meta-form" style="display: none">
                                    <?
                                    echo $this->Form->create('Page', array('role' => 'form', 'url' => '/admin/pages/edit_meta'));
                                    echo $this->Form->input('id', array(
                                        'type' => 'hidden',
                                        'value' => $page['Page']['id']
                                    ));
                                    echo $this->Form->input('meta_description', array(
                                        'type' => 'textarea',
                                        'class' => 'form-control',
                                        'div' => 'form-group',
                                        'label' => false,
                                        'value' => $page['Page']['meta_description']
                                    ));
                                    echo $this->Form->end();
                                    ?>
                                </div>
                            </td>
                            <td align="center" class="meta-edit text-muted small" data-toggle="tooltip" data-placement="top" data-container="body" title="<?= $page['Page']['info'] ?>">
                                <div class="meta-text"><?= $page['Page']['info_count'] ? $page['Page']['info_count'] : '' ?></div>
                                <div class="meta-form" style="display: none">
                                    <?
                                        echo $this->Form->create('Page', array('role' => 'form', 'url' => '/admin/pages/edit_meta'));
                                        echo $this->Form->input('id', array(
                                            'type' => 'hidden',
                                            'value' => $page['Page']['id']
                                        ));
                                        echo $this->Form->input('info', array(
                                            'type' => 'textarea',
                                            'class' => 'form-control',
                                            'div' => 'form-group',
                                            'label' => false,
                                            'value' => $page['Page']['info']
                                        ));
                                        echo $this->Form->end();
                                    ?>
                                </div>
                            </td>
                            <td class="text-muted small text-center"><?= $this->Html->link($page['PageCategory']['name'], array('controller' => 'page_categories', 'action' => 'view', $page['PageCategory']['id'])) ?></td>
                            <td class="text-center small text-muted" >
                                <? if (!empty($page['Page']['anchors'])) { ?>
                                    <span class="pointer" data-container="body"
                                          data-toggle="tooltip" data-placement="top" title="<?= $page['Page']['anchors'] ?>">
                                        <?= $page['Page']['anchors_count'] ?>
                                    </span>
                                <? } ?>
                            </td>
                            <td class="text-center small text-muted" >
                                <? if (!empty($page['Page']['related_pages'])) { ?>
                                    <span class="pointer" data-container="body"
                                          data-toggle="tooltip" data-placement="top" title="<?= $page['Page']['related_pages'] ?>">
                                        <?= $page['Page']['related_pages_count'] ?>
                                    </span>
                                <? } ?>
                            </td>
                            <td class="text-center small text-muted"><?= $page['Page']['symbols'] ? $page['Page']['symbols'] : '' ?></td>
                            <td class="text-center small">
                                <? if ( $page['Page']['sitemap'] ) { ?>
                                    <span class="glyphicon glyphicon-ok"></span>
                                <? } ?>
                            </td>
                            <td class="text-center small">
                                <? if ( $page['Page']['show_best_offers'] ) { ?>
                                    <span class="glyphicon glyphicon-ok"></span>
                                <? } ?>
                            </td>
                            <td class="text-center small">
                                <? if ( $page['Page']['noindex_best_offers'] ) { ?>
                                    <span class="glyphicon glyphicon-ok"></span>
                                <? } ?>
                            </td>
                            <td class="text-center small text-muted"><?= $page['Page']['lft'] ?></td>
                            <td class="text-center small text-muted"><?= $page['Page']['rght'] ?></td>
                            <td class="text-center small text-muted">
                                <span data-toggle="tooltip"
                                      title="<?= $this->Time->format('H:i', $page['Page']['created'], null, null) ?>">
                                    <?= $this->Time->format('d.m.y', $page['Page']['created'], null, null) ?>
                                </span>
                            </td>
                            <td class="text-center small text-muted">
                                <span data-toggle="tooltip"
                                      title="<?= $this->Time->format('H:i', $page['Page']['modified'], null, null) ?>">
                                    <?= $this->Time->format('d.m.y', $page['Page']['modified'], null, null) ?>
                                </span>
                            </td>
                            <td class="actions">
                                <a href="http://<?= $page['Domain']['name'] . $Page->getUrl($page['Page']['id']) ?>"
                                   target="_blank"><span class="glyphicon glyphicon-link"></span></a>
                                <a href="<?= $this->Html->url(array('action' => 'moveup', $page['Page']['id'], 1)); ?>"><span class="glyphicon glyphicon-arrow-up"></span></a>
                                <a href="<?= $this->Html->url(array('action' => 'movedown', $page['Page']['id'], 1)); ?>"><span class="glyphicon glyphicon-arrow-down"></span></a>
                                <?= $this->Html->link(
                                    '<span class="glyphicon glyphicon-resize-small"></span>',
                                    array('controller' => 'related_pages', 'action' => 'index', $page['Page']['id']),
                                    array('escape' => false)) ?>
                                <?= $this->Html->link(
                                    '<span class="glyphicon glyphicon-link"></span>',
                                    array('controller' => 'anchors', 'action' => 'index', $page['Page']['id']),
                                    array('escape' => false)) ?>
                                <?= $this->Html->link(
                                    '<span class="glyphicon glyphicon-time"></span>',
                                    array('controller' => 'backups', 'action' => 'index', '?' => array('model' => 'Page', 'model_id' => $page['Page']['id'])),
                                    array('escape' => false)) ?>
                                <?= $this->Html->link(
                                    '<span class="glyphicon glyphicon-search"></span>',
                                    array('action' => 'view', $page['Page']['id']),
                                    array('escape' => false)) ?>
                                <?= $this->Html->link(
                                    '<span class="glyphicon glyphicon-edit"></span>',
                                    array('action' => 'edit', $page['Page']['id']),
                                    array('escape' => false)) ?>
                                <?= $this->Form->postLink(
                                    '<span class="glyphicon glyphicon-remove"></span>',
                                    array('action' => 'delete', $page['Page']['id']),
                                    array('escape' => false), __('Are you sure you want to delete # %s?', $page['Page']['id'])) ?>
                            </td>
                        </tr>
                    <? endforeach ?>
                    </tbody>
                </table>
            </div>
            <p>
                <small>
                    <?= $this->Paginator->counter(array(
                        'format' => __("Page {:page} of {:pages},
							showing {:current} records out of {:count} total,
							starting on record {:start}, ending on {:end}"
                        ))) ?>
                </small>
            </p>
            <? $params = $this->Paginator->params() ?>
            <? if ($params['pageCount'] > 1) { ?>
                <ul class="pagination pagination-sm">
                    <?= $this->Paginator->prev(
                        '&larr; Previous', array(
                        'class' => 'prev',
                        'tag' => 'li',
                        'escape' => false
                    ),
                        '<a onclick="return false">&larr; Previous</a>', array(
                            'class' => 'prev disabled',
                            'tag' => 'li',
                            'escape' => false
                        )
                    ) ?>
                    <?= $this->Paginator->numbers(array(
                        'separator' => '',
                        'tag' => 'li',
                        'currentClass' => 'active',
                        'currentTag' => 'a'
                    )) ?>
                    <?= $this->Paginator->next(
                        'Next &rarr;', array(
                        'class' => 'next',
                        'tag' => 'li',
                        'escape' => false),
                        '<a onclick="return false;">Next &rarr;</a>', array(
                        'class' => 'next disabled',
                        'tag' => 'li',
                        'escape' => false
                    )) ?>
                </ul>
            <? } ?>
        </div> <!-- end col md 12 -->
    </div><!-- end row -->
</div><!-- end index -->