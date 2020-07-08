<div class="redirects view">
	<div class="row">
		<div class="col-md-12">
			<div class="page-header">
				<h1><?= __('Redirect') ?></h1>
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
								__('<span class="glyphicon glyphicon-edit"></span> Edit Redirect'),
								array('action' => 'edit', $redirect['Redirect']['id']),
								array('escape' => false)) ?>
							</li>
							<li>
								<?= $this->Form->postLink(
								__('<span class="glyphicon glyphicon-remove"></span> Delete Redirect'),
								array('action' => 'delete', $redirect['Redirect']['id']),
								array('escape' => false),
								__('Are you sure you want to delete # %s?', $redirect['Redirect']['id'])) ?>
							</li>
							<li>
								<?= $this->Html->link(
								__('<span class="glyphicon glyphicon-list"></span> List Redirects'),
								array('action' => 'index'),
								array('escape' => false)) ?>
							</li>
							<li><?= $this->Html->link(
								__('<span class="glyphicon glyphicon-plus"></span> New Redirect'),
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
						<?= h($redirect['Redirect']['id']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Source') ?></th>
					<td>
						<?= h($redirect['Redirect']['source']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Destination') ?></th>
					<td>
						<?= h($redirect['Redirect']['destination']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Created') ?></th>
					<td>
						<?= h($redirect['Redirect']['created']) ?>
					</td>
				</tr>
				<tr>
					<th><?= __('Modified') ?></th>
					<td>
						<?= h($redirect['Redirect']['modified']) ?>
					</td>
				</tr>
				</tbody>
			</table>
		</div><!-- end col md 9 -->
	</div>
</div>

