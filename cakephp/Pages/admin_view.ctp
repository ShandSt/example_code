<div class="pages view">
	<div class="row">
		<div class="col-md-12">
			<div class="page-header">
				<h1><?= __('Page') ?></h1>
			</div>
		</div>
	</div>
	<div class="row">
		<div class="col-md-3">
			<div class="actions">
				<div class="panel panel-default">
					<div class="panel-heading">Actions</div>
						<div class="panel-body">
							<ul class="nav nav-pills nav-stacked">
								<li>
									<?= $this->Html->link(
										__('<span class="glyphicon glyphicon-edit"></span> Edit Page'),
										array('action' => 'edit', $page['Page']['id']),
										array('escape' => false)) ?>
								</li>
								<li>
									<?= $this->Form->postLink(
										__('<span class="glyphicon glyphicon-remove"></span> Delete Page'),
										array('action' => 'delete', $page['Page']['id']),
										array('escape' => false),
										__('Are you sure you want to delete # %s?', $page['Page']['id'])) ?>
								</li>
								<li>
									<?= $this->Html->link(
										__('<span class="glyphicon glyphicon-list"></span> List Pages'),
										array('action' => 'index'),
										array('escape' => false)) ?>
								</li>
								<li><?= $this->Html->link(
										__('<span class="glyphicon glyphicon-plus"></span> New Page'),
										array('action' => 'add'),
										array('escape' => false)) ?>
								</li>
							</ul>
						</div><!-- end body -->
				</div><!-- end panel -->
			</div><!-- end actions -->
		</div><!-- end col md 3 -->

		<div class="col-md-9">
			<table cellpadding="0" cellspacing="0" class="table table-striped">
				<tbody>
				<tr>
					<th><?= __('Id') ?></th>
					<td>
						<?= h($page['Page']['id']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Domain') ?></th>
					<td>
						<?= $this->Html->link($page['Domain']['name'], array('controller' => 'domains', 'action' => 'view', $page['Domain']['id'])) ?>
			 
					</td>
				</tr>
				<tr>
					<th><?= __('PageCategory') ?></th>
					<td>
						<?= $this->Html->link($page['PageCategory']['name'], array('controller' => 'page_categories', 'action' => 'view', $page['PageCategory']['id'])) ?>

					</td>
				</tr>
				<tr>
					<th><?= __('Title') ?></th>
					<td>
						<?= h($page['Page']['title']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('H1') ?></th>
					<td>
						<?= h($page['Page']['h1']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Alias') ?></th>
					<td>
						<?= h($page['Page']['alias']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Text') ?></th>
					<td>
						<h1><?= h($page['Page']['h1']) ?></h1>
						<?= $page['Page']['text'] ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Meta Title') ?></th>
					<td>
						<?= h($page['Page']['meta_title']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Meta Description') ?></th>
					<td>
						<?= h($page['Page']['meta_description']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Created') ?></th>
					<td>
						<?= h($page['Page']['created']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Modified') ?></th>
					<td>
						<?= h($page['Page']['modified']) ?>
					</td>
				</tr>
				</tbody>
			</table>
		</div><!-- end col md 9 -->
	</div>
</div>

