<div class="offerDetails index">
    <div class="row">
        <div class="col-md-12">
            <div class="page-header pull-left">
                <h1><?= __('Offer Details') ?></h1>
            </div>
            <div class="pull-right">
                <ul class="nav nav-pills">
                    <li>
                        <a href="/admin/offer_details/reorder/<?= $currentDomain ?>"><span class="glyphicon glyphicon-sort-by-order"></span> Пересчитать позиции по EPC</a>
                    </li>
                </ul>
            </div>
            <div class="domain-select pull-right">
                <form class="submit-on-change" method="GET">
                    <?= $this->Form->input('domain_id', array(
                        'type' => 'select',
                        'class' => 'form-control',
                        'label' => false,
                        'name' => 'domain_id',
                        'options' => $domains,
                        'value' => $currentDomain
                    )) ?>
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
						<th align="center"><?= $this->Paginator->sort('place') ?></th>
						<th align="center"><?= $this->Paginator->sort('epc', 'EPC') ?></th>
                        <th align="center"><?= $this->Paginator->sort('img') ?></th>
                        <th align="center"><?= $this->Paginator->sort('tracking_url', 'Link') ?></th>
                        <th align="center"><?= $this->Paginator->sort('stroke_text', 'Stroke') ?></th>
                        <th><?= $this->Paginator->sort('name') ?></th>
                        <th><?= $this->Paginator->sort('alias') ?></th>
						<th align="center"><?= $this->Paginator->sort('leads_offer_id') ?></th>
						<th align="center"><?= $this->Paginator->sort('domain_id') ?></th>
						<th align="center"><?= $this->Paginator->sort('rating') ?></th>
						<th><?= $this->Paginator->sort('mfo_name') ?></th>
						<th align="center"><?= $this->Paginator->sort('created') ?></th>
						<th align="center"><?= $this->Paginator->sort('modified') ?></th>
						<th class="actions"></th>
					</tr>
				</thead>
				<tbody>
				<? foreach ($offerDetails as $offerDetail): ?>
					<tr class="<?= $offerDetail['Offer']['id'] ? 'success' : 'warning' ?>">
						<td><?= h($offerDetail['OfferDetail']['id']) ?></td>
						<td align="center"><?= h($offerDetail['OfferDetail']['place']) ?></td>
						<td align="center"><?= h($offerDetail['Offer']['epc']) ?></td>
                        <td align="center">
                            <? if ( $offerDetail['OfferDetail']['img'] ) { ?>
                                <span class="glyphicon glyphicon-picture"></span>
                            <? } ?>
                        </td>
                        <td align="center">
                            <? if ( $offerDetail['OfferDetail']['tracking_url'] ) { ?>
                                <a href="<?= $offerDetail['OfferDetail']['tracking_url'] ?>" target="_blank">
                                    <span class="glyphicon glyphicon-link"></span>
                                </a>
                            <? } ?>
                        </td>
                        <td align="center">
                            <? if ( $offerDetail['OfferDetail']['stroke_text'] ) { ?>
                                <span class="glyphicon glyphicon-font"></span>
                            <? } ?>
                        </td>
                        <td><?= h($offerDetail['OfferDetail']['name']) ?></td>
                        <td><?= h($offerDetail['OfferDetail']['alias']) ?></td>
						<td align="center"><?= h($offerDetail['OfferDetail']['leads_offer_id']) ?></td>
                        <td align="center"><?= $this->Html->link($offerDetail['Domain']['name'], array('controller' => 'domains', 'action' => 'view', $offerDetail['Domain']['id'])) ?></td>

                        <td align="center">
                            <? if ( $offerDetail['OfferDetail']['rating'] > 0 ) { ?>
                                <?= h($offerDetail['OfferDetail']['rating']) ?>
                            <? } ?>
                        </td>
						<td><?= h($offerDetail['OfferDetail']['mfo_name']) ?></td>
						<td align="center">
							<span data-toggle="tooltip" title="<?= $this->Time->format('H:i', $offerDetail['OfferDetail']['created'], null, null) ?>">
								<?= $this->Time->format('d.m.y', $offerDetail['OfferDetail']['created'], null, null) ?>
							</span>
						</td>
						<td align="center">
							<span data-toggle="tooltip" title="<?= $this->Time->format('H:i', $offerDetail['OfferDetail']['modified'], null, null) ?>">
								<?= $this->Time->format('d.m.y', $offerDetail['OfferDetail']['modified'], null, null) ?>
							</span>
						</td>
						<td align="center" class="actions">
							<?= $this->Html->link(
								'<span class="glyphicon glyphicon-edit"></span>',
								array('action' => 'edit', $offerDetail['OfferDetail']['id'], '?' => array('domain_id' => $offerDetail['OfferDetail']['domain_id'])),
								array('escape' => false)) ?>
							<?= $this->Form->postLink(
								'<span class="glyphicon glyphicon-remove"></span>',
								array('action' => 'delete', $offerDetail['OfferDetail']['id']),
								array('escape' => false), __('Are you sure you want to delete # %s?', $offerDetail['OfferDetail']['id'])) ?>
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
							'class'  => 'prev',
							'tag' 	 => 'li',
							'escape' => false
							),
						'<a onclick="return false">&larr; Previous</a>', array(
							'class'  => 'prev disabled',
							'tag'    => 'li',
							'escape' => false
							)
						) ?>
				<?= $this->Paginator->numbers(array(
						'separator'    => '',
						'tag' 		   => 'li',
						'currentClass' => 'active',
						'currentTag'   => 'a'
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