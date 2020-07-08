<?php
App::uses('AppController', 'Controller');
/**
 * Pages Controller
 *
 * @property Page $Page
 * @property PaginatorComponent $Paginator
 */
class PagesController extends AppController {

/**
 * Components
 *
 * @var array
 */
	public $components = array('Paginator', 'Cookie');
	public $uses = array('Page', 'Backup');

	public function beforeFilter() {
		parent::beforeFilter();
		if ( in_array($this->action, array('admin_edit_meta', 'admin_edit', 'admin_add')) ) {
			$this->Security->csrfCheck = false;
			$this->Security->validatePost = false;
		}
	}

	/**
	 * AJAX редактирование мета на странице admin/pages/index
	 *
	 * @return void
	 */
	public function admin_edit_meta() {
		$this->autoRender = false;
		$this->Page->save($this->request->data);
	}
/**
 * admin_index method
 *
 * @return void
 */
	public function admin_index() {
		$this->Page->recursive = 0;
		$domainsList = $this->Page->Domain->find('list');

		$domain_id = $this->request->query('domain_id');
		if ($this->Page->Domain->exists($domain_id)) {
            $this->Cookie->write('domain_id', $this->request->query['domain_id']);
        } else {
            $this->redirect('/admin/pages/index?domain_id=' . key($domainsList));
        }

		$currentDomain = $this->request->query['domain_id'];
		$this->Page->virtualFields = array(
			'symbols' => 'CHAR_LENGTH(Page.text)',
			'meta_title_count' => 'CHAR_LENGTH(Page.meta_title)',
			'meta_description_count' => 'CHAR_LENGTH(Page.meta_description)',
			'meta_keywords_count' => 'CHAR_LENGTH(Page.meta_keywords)',
			'h1_count' => 'CHAR_LENGTH(Page.h1)',
			'info_count' => 'CHAR_LENGTH(Page.info)',
			'anchor_title_count' => 'CHAR_LENGTH(Page.anchor_title)'
			);
		$this->Paginator->settings = array(
			'limit' => 40,
			'conditions' => array(
				'Page.domain_id' => $currentDomain
				),
            'recursive' => -1,
            'contain' => array(
                'PageParent', 'Domain', 'PageCategory',
                'Anchor', 'RelatedPage' => array('Anchor')
            ),
            'order' => array('Page.lft' => 'ASC')
        );

		if ( isset($this->request->query['query']) && $this->request->query['query'] != '' ) {
			$this->Paginator->settings['conditions']['OR']['Page.title LIKE'] = '%' . $this->request->query['query'] . '%';
			$this->Paginator->settings['conditions']['OR']['Page.h1 LIKE']    = '%' . $this->request->query['query'] . '%';
			$this->Paginator->settings['conditions']['OR']['Page.alias LIKE'] = '%' . $this->request->query['query'] . '%';
			$this->Paginator->settings['conditions']['OR']['Page.text LIKE']  = '%' . $this->request->query['query'] . '%';
		}

        $this->Page->Behaviors->load('Containable');
        $pages = $this->Paginator->paginate();
        foreach ($pages as $key => $page) {
            foreach ($page['Anchor'] as $anchor) {
                $pages[$key]['Page']['anchors'] = isset($pages[$key]['Page']['anchors']) ? $pages[$key]['Page']['anchors'] . '<br />' . $anchor['name'] : $anchor['name'];
                $pages[$key]['Page']['anchors_count'] = isset($pages[$key]['Page']['anchors_count']) ? $pages[$key]['Page']['anchors_count'] + 1 : 1;
            }
            foreach ($page['RelatedPage'] as $relatedPage) {
                $pages[$key]['Page']['related_pages'] =
                    isset($pages[$key]['Page']['related_pages']) ? $pages[$key]['Page']['related_pages'] . '<br />' . $relatedPage['Anchor']['name'] : $relatedPage['Anchor']['name'];
                $pages[$key]['Page']['related_pages_count'] = isset($pages[$key]['Page']['related_pages_count']) ? $pages[$key]['Page']['related_pages_count'] + 1 : 1;
            }
        }


		$this->set('pages', $pages);
		$this->set('domains', $domainsList);
		$this->set('currentDomain', $currentDomain);
		$this->set('query', isset($this->request->query['query']) ? $this->request->query['query'] : '');
		$this->set('Page', $this->Page);
	}

    public function admin_reorder() {
        $this->autoRender = false;
        $this->Page->recover();
        $this->redirect(array('action' => 'index'));
    }

    public function admin_moveup($id = null, $delta = null) {
        $this->Page->id = $id;
        if (!$this->Page->exists()) {
            throw new NotFoundException(__('Invalid page'));
        }
        if ($delta > 0) {
            $this->Page->moveUp($this->Page->id, abs($delta));
        } else {
            $this->Session->setFlash(
                'Please provide a number of positions the category should' .
                'be moved up.'
            );
        }
        return $this->redirect( $this->referer() );
    }

    public function admin_movedown($id = null, $delta = null) {
        $this->Page->id = $id;
        if (!$this->Page->exists()) {
            throw new NotFoundException(__('Invalid page'));
        }
        if ($delta > 0) {
            $this->Page->moveDown($this->Page->id, abs($delta));
        } else {
            $this->Session->setFlash(
                'Please provide the number of positions the field should be' .
                'moved down.'
            );
        }
        return $this->redirect( $this->referer() );
    }

/**
 * admin_view method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_view($id = null) {
		if (!$this->Page->exists($id)) {
			throw new NotFoundException(__('Invalid page'));
		}
		$options = array('conditions' => array('Page.' . $this->Page->primaryKey => $id));
		$this->set('page', $this->Page->find('first', $options));
	}

/**
 * admin_add method
 *
 * @return void
 */
	public function admin_add() {
		if ($this->request->is('post')) {
			$this->Page->create();
			if ($this->Page->save($this->request->data)) {
			    //
                $page_id = $this->Page->getLastInsertID();
                $page = $this->Page->getPageById($page_id);
                $this->Backup->saveBackup('Page', $page);
                //
				$this->Session->setFlash(__('The page has been saved.'), 'default', array('class' => 'alert alert-success'));
				return $this->redirect(array('action' => 'index', '?' => array('domain_id' => $this->request->data['Page']['domain_id'])));
			} else {
				$this->Session->setFlash(__('The page could not be saved. Please, try again.'), 'default', array('class' => 'alert alert-danger'));
			}
		}
		$domains = $this->Page->Domain->find('list');
		$pageCategories = $this->Page->PageCategory->generateTreeList(null, null, null, ' --- ');
        $parents = $this->Page->generateTreeList(array(
            'Page.domain_id' => $this->request->query('domain_id')
        ), null, null, ' --- ');
		$firstCompanies = $this->Page->FirstCompany->find('list');
		$secondCompanies = $this->Page->SecondCompany->find('list');
		$this->set(compact('domains', 'pageCategories', 'parents', 'firstCompanies', 'secondCompanies'));
	}

/**
 * admin_edit method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_edit($id = null) {
		if (!$this->Page->exists($id)) {
			throw new NotFoundException(__('Invalid page'));
		}
		//
		$page_old = $this->Page->getPageById($id);
        $this->Backup->savePreBackup('Page', $page_old);
        //
		if ($this->request->is(array('post', 'put'))) {
			if ($this->Page->save($this->request->data)) {
                //
                $page_new = $this->Page->getPageById($id);
                $this->Backup->saveBackup('Page', $page_new);
                //
				$this->Session->setFlash(__('The page has been saved.'), 'default', array('class' => 'alert alert-success'));
				$this->redirect(array('action' => 'index', '?' => array('domain_id' => $this->request->data['Page']['domain_id'])));
			} else {
				$this->Session->setFlash(__('The page could not be saved. Please, try again.'), 'default', array('class' => 'alert alert-danger'));
			}
		} else {
			$options = array('conditions' => array('Page.' . $this->Page->primaryKey => $id));
			$this->request->data = $this->Page->find('first', $options);
		}
		$domains = $this->Page->Domain->find('list');
        $pageCategories = $this->Page->PageCategory->generateTreeList(null, null, null, ' --- ');
        $parents = $this->Page->generateTreeList(array(
            'Page.domain_id' => $this->request->data['Page']['domain_id']
        ), null, null, ' --- ');
        $relatives = $parents;
		$firstCompanies = $this->Page->FirstCompany->find('list');
		$secondCompanies = $this->Page->SecondCompany->find('list');
		$this->set(compact('domains', 'pageCategories', 'parents', 'relatives', 'firstCompanies', 'secondCompanies'));
	}

/**
 * admin_delete method
 *
 * @throws NotFoundException
 * @param string $id
 * @return void
 */
	public function admin_delete($id = null) {
		$this->Page->id = $id;
		$domain_id = $this->Page->field('domain_id', array('Page.id' => $id));
		if (!$this->Page->exists()) {
			throw new NotFoundException(__('Invalid page'));
		}
		$this->request->onlyAllow('post', 'delete');
		if ($this->Page->delete()) {
			$this->Session->setFlash(__('The page has been deleted.'), 'default', array('class' => 'alert alert-success'));
		} else {
			$this->Session->setFlash(__('The page could not be deleted. Please, try again.'), 'default', array('class' => 'alert alert-danger'));
		}
		return $this->redirect(array('action' => 'index', '?' => array('domain_id' => $domain_id)));
	}
}
