<div class="offerDetails form">
    <div class="row">
        <div class="col-md-12">
            <ul class="nav nav-pills pull-right">
                <li>
                    <?= $this->Html->link(
                        __('<span class="glyphicon glyphicon-list"></span> List Offer Details'),
                        array('action' => 'index'),
                        array('escape' => false)) ?>
                </li>
            </ul>
            <div class="page-header">
                <h1><?= __('Admin Edit Offer Detail') ?></h1>
            </div>
        </div>
    </div>
    <?= $this->Form->create('OfferDetail', array('role' => 'form')) ?>
    <?= $this->Form->input('id', array(
        'class' => 'form-control',
        'div' => 'form-group'
    )) ?>
    <div class="row">
        <div class="col-sm-4">
            <?= $this->Form->input('name', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('h1', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('alias', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('site', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>

            <?= $this->Form->input('info_widget_ids', array(
            'class' => 'form-control',
            'div' => 'form-group'
            )) ?>
        </div>
        <div class="col-sm-4">
			<?= $this->Form->input('ogrn', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('regnum', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('director', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('address', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('second_company_id', array(
            'empty' => '-',
            'class' => 'form-control',
            'div' => 'form-group'
            )) ?>
        </div>
        <div class="col-sm-4">
            <?= $this->Form->input('phone', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('email', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('color', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>

            <?= $this->Form->input('field_1', array(
            'class' => 'form-control',
            'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('field_2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>

        </div>
    </div>
    <?= $this->Form->input('stroke_text', array(
        'class' => 'form-control',
        'div' => 'form-group'
    )) ?>
    <?= $this->Form->input('text', array(
        'class' => 'form-control ckeditor',
        'div' => 'form-group'
    )) ?>
    <?= $this->Form->input('text_demands', array(
        'class' => 'form-control ckeditor',
        'div' => 'form-group'
    )) ?>
    <?= $this->Form->input('text_receive_and_return', array(
        'class' => 'form-control ckeditor',
        'div' => 'form-group'
    )) ?>
	<?= $this->Form->input('metrika_cel', array(
		'class' => 'form-control',
		'div' => 'form-group'
	)) ?>
    <div class="row">
        <div class="col-sm-4">
            <div class="row">
                <div class="col-sm-6">
                    <?= $this->Form->input('percent_min', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-6">
                    <?= $this->Form->input('percent_max', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $this->Form->input('summ_min', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-6">
                    <?= $this->Form->input('summ_max', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $this->Form->input('age_min', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-6">
                    <?= $this->Form->input('age_max', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $this->Form->input('terms_min', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-6">
                    <?= $this->Form->input('terms_max', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
            </div>
            <div class="row">
                <div class="col-sm-6">
                    <?= $this->Form->input('repeat_zayem_min', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
                <div class="col-sm-6">
                    <?= $this->Form->input('repeat_zayem_max', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                </div>
            </div>
             
            <?= $this->Form->input('place', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('vozrast', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('docs', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
        </div>
        <div class="col-sm-6 col-sm-offset-1">
            <div class="row">
                <div class="col-sm-6">
                    <?= $this->Form->input('mfo_name', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                    <?= $this->Form->input('time_to_consider', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                    <?= $this->Form->input('rating', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>
                    <?= $this->Form->input('approved_percent', array(
                        'class' => 'form-control',
                        'div' => 'form-group'
                    )) ?>

                </div>
                <div class="col-sm-6">
                    <?= $this->Form->input('leads_offer_id', array(
                        'class' => 'form-control',
                        'div' => 'form-group',
                        'type' => 'text',
                        'label' => 'Leads Offed Id'
                    )) ?>
                    <?= $this->Form->input('domain_id', array(
                        'class' => 'form-control',
                        'div' => 'form-group',
                        'disabled' => true
                    )) ?>
                    <?= $this->Form->input('PayMethod', array(
                        'class' => 'form-control',
                        'div' => 'form-group',
                        'size' => count($payMethods)
                    )) ?>
                </div>
            </div>
            <?= $this->Form->input('licenziya', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('ofrm_online', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('rasmotr_zayv', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('prolongaciya', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('time_work', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
			
            <?= $this->Form->input('tracking_url', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
        </div>
    </div>
    <div class="row">
        <div class="col-sm-4">
            <?= $this->Form->input('meta_title', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
        </div>
        <div class="col-sm-offset-1 col-sm-6">
            <?= $this->Form->input('img', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
        </div>
	</div>
    <?= $this->Form->input('descr_text', array(
        'class' => 'form-control',
        'div' => 'form-group'
    )) ?>
    <?= $this->Form->input('meta_description', array(
        'class' => 'form-control',
        'div' => 'form-group'
    )) ?>

    <div class="row">
        <div class="col-sm-12">
                <h1><?= __('Условия') ?></h1>            
        </div>
    </div>	    
    <div class="row">
        <div class=" col-sm-6">
            <?= $this->Form->input('summ_min2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('terms_min2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('percent_min2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('vozrast2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('docs2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
        </div>
        <div class="col-sm-6">
            <?= $this->Form->input('identif2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('rasmotr_zayv2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('sposobu_poluch2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('form_pog2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
            <?= $this->Form->input('time_work2', array(
                'class' => 'form-control',
                'div' => 'form-group'
            )) ?>
        </div>
    </div>

    <?= $this->Form->submit(__('Submit'), array('class' => 'btn btn-default')) ?>
    <?= $this->Form->end() ?>
</div>
