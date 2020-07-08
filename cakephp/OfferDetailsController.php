<?php
App::uses('AppController', 'Controller');
/**
 * OfferDetails Controller
 *
 * @property OfferDetail $OfferDetail
 * @property PaginatorComponent $Paginator
 */
class OfferDetailsController extends AppController {

/**
 * Components
 *
 * @var array
 */
	public $components = array('Paginator', 'Cookie');

	public function admin_reorder($domain_id) {
        ClassRegistry::init('Offer')->recountPositions($domain_id);
        $this->redirect( $this->referer() );
    }

/**
 * admin_index method
 *
 * @return void
 */
	public function admin_index()
    {
        $domainsList = $this->OfferDetail->Domain->find('list');
        if ( empty($this->request->query['domain_id']) ) {
            if ( $this->Cookie->read('domain_id') ) {
                $this->redirect('/admin/offer_details?domain_id=' . $this->Cookie->read('domain_id'));
            } else {
                $this->redirect('/admin/offer_details?domain_id=' . key($domainsList));
            }
        } else {
            $this->Cookie->write('domain_id', $this->request->query['domain_id']);
        }
        $currentDomain = $this->request->query['domain_id'];

		$this->OfferDetail->recursive = 0;
        $this->OfferDetail->virtualFields = array(
            'place_exists' => 'ISNULL(OfferDetail.place)'
        );
        $this->Paginator->settings = array(
            'fields' => array('Offer.*', 'Domain.*', 'OfferDetail.*'),
            'conditions' => array('OfferDetail.domain_id' => $currentDomain),
            'joins' => array(
                array(
                    'table' => 'offers',
                    'alias' => 'Offer',
                    'type' => 'LEFT',
                    'conditions' => array(
                        'Offer.leads_offer_id = OfferDetail.leads_offer_id',
                        'Offer.domain_id = OfferDetail.domain_id'
                    ))
            ),
            'order' => array('OfferDetail.place_exists', 'OfferDetail.place' => 'ASC')
        );
		$this->set('offerDetails', $this->Paginator->paginate());
        $this->set('domains', $domainsList);
        $this->set('currentDomain', $currentDomain);
	}

/**
 * admin_view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_view($id = null) {
		if (!$this->OfferDetail->exists($id)) {
			throw new NotFoundException(__('Invalid offer detail'));
		}
		$options = array('conditions' => array('OfferDetail.' . $this->OfferDetail->primaryKey => $id));
		$this->set('offerDetail', $this->OfferDetail->find('first', $options));
	}

/**
 * admin_add method
 *
 * @return void
 */
	public function admin_add() {

        if ($this->request->is(array('post', 'put'))) {

			if(!empty($this->request->query['offer_add']) && $this->request->query['offer_add'] == 1) $this->request->data['OfferDetail']['no_api'] = 1;

			$this->OfferDetail->create();
			if ($this->OfferDetail->save($this->request->data)) {
				$this->Session->setFlash(__('The offer detail has been saved.'), 'default', array('class' => 'alert alert-success'));
				$domain_id = $this->request->query['domain_id'];
				$this->redirect(array('action' => 'index', '?' => array('domain_id' => $domain_id)));
			} else {
				$this->Session->setFlash(__('The offer detail could not be saved. Please, try again.'), 'default', array('class' => 'alert alert-danger'));
			}
		}
		$domains = $this->OfferDetail->Domain->find('list');
		$payMethods = $this->OfferDetail->PayMethod->find('list');
		$secondCompanies = $this->OfferDetail->SecondCompany->find('list');
		$this->set(compact('domains', 'payMethods', 'secondCompanies'));

		if (!empty($this->request->query['leads_offer_id']) && empty($this->request->query['offer_add'])) {
				$this->request->data = $this->OfferDetail->find('first', array(
					'conditions' => array(
						'OfferDetail.leads_offer_id' => $this->request->query['leads_offer_id']
						),
					'order' => array('OfferDetail.created' => 'DESC')
				));
		}

		unset($this->request->data['OfferDetail']['id']);
		unset($this->request->data['OfferDetail']['h1']);
		unset($this->request->data['OfferDetail']['text']);
		unset($this->request->data['OfferDetail']['rating']);
		unset($this->request->data['OfferDetail']['meta_title']);
		unset($this->request->data['OfferDetail']['meta_description']);
	}

/**
 * admin_edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_edit($id = null) {
		if (!$this->OfferDetail->exists($id)) {
			throw new NotFoundException(__('Invalid offer detail'));
		}
		if ($this->request->is(array('post', 'put'))) {
			if ($this->OfferDetail->save($this->request->data)) {
				$this->Session->setFlash(__('The offer detail has been saved.'), 'default', array('class' => 'alert alert-success'));
				$domain_id = $this->request->query['domain_id'];
                $this->redirect(array('action' => 'index', '?' => array('domain_id' => $domain_id)));
			} else {
				$this->Session->setFlash(__('The offer detail could not be saved. Please, try again.'), 'default', array('class' => 'alert alert-danger'));
			}
		} else {
			$options = array('conditions' => array('OfferDetail.' . $this->OfferDetail->primaryKey => $id));
			$this->request->data = $this->OfferDetail->find('first', $options);
		}
		$domains = $this->OfferDetail->Domain->find('list');
		$payMethods = $this->OfferDetail->PayMethod->find('list');
		$secondCompanies = $this->OfferDetail->SecondCompany->find('list');
		$this->set(compact('domains', 'payMethods','secondCompanies'));
	}

/**
 * admin_delete method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_delete($id = null) {
		$this->OfferDetail->id = $id;
		if (!$this->OfferDetail->exists()) {
			throw new NotFoundException(__('Invalid offer detail'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->OfferDetail->delete()) {
			$this->Session->setFlash(__('The offer detail has been deleted.'), 'default', array('class' => 'alert alert-success'));
		} else {
			$this->Session->setFlash(__('The offer detail could not be deleted. Please, try again.'), 'default', array('class' => 'alert alert-danger'));
		}
		return $this->redirect(array('action' => 'index'));
	}
}
