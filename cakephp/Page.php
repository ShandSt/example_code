<?php
App::uses('AppModel', 'Model');
/**
 * Page Model
 *
 * @property Domain $Domain
 */
class Page extends AppModel {

    public $actsAs = array('Tree');
	public $validate = array(
		'alias' => 'notBlank'
	);

    public function getFullTitle($page_id = null) {
        $path = $this->getPath($page_id, 'title');
        $name = '';
        foreach ($path as $key => $pathItem) {
            $separator = !$name ? '' : ' / ';
            $pathItem['Page']['title'] = $key ? mb_strtolower($pathItem['Page']['title']) : $pathItem['Page']['title'];
            $name .= $separator . $pathItem['Page']['title'];
        }
        return $name;
    }

    public function getPageById($page_id = null) {
        return $this->find('first', array(
            'conditions' => array(
                'Page.id' => $page_id
            ),
            'recursive' => -1
        ));
    }

    /**
     * Возвращает ссылку на страницу в виде /level1/level2
     *
     * @param null $page_id
     * @return string
     */
    public function getUrl($page_id = null) {
        $path = $this->getPath($page_id, 'alias');
        $url = '';
        foreach ($path as $pathItem) {
            $url .= '/' . $pathItem['Page']['alias'];
        }
        return $url;
    }

/**
 * belongsTo associations
 *
 * @var array
 */
	public $belongsTo = array(
        'PageParent' => array(
            'className' => 'Page',
            'foreignKey' => 'parent_id'
        ),
		'Domain' => array(
			'className' => 'Domain',
			'foreignKey' => 'domain_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'PageCategory' => array(
			'className' => 'PageCategory',
			'foreignKey' => 'page_category_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'FirstCompany' => array(
			'className' => 'FirstCompany',
			'foreignKey' => 'first_company_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
		'SecondCompany' => array(
			'className' => 'SecondCompany',
			'foreignKey' => 'second_company_id',
			'conditions' => '',
			'fields' => '',
			'order' => ''
		),
	);

    public $hasAndBelongsToMany = array(
        'Relative' => array(
            'className' => 'Page',
            'joinTable' => 'relative_pages',
            'foreignKey' => 'page_id',
            'associationForeignKey' => 'relative_page',
            'unique' => 'keepExisting',
            'conditions' => '',
            'fields' => '',
            'order' => '',
            'limit' => '',
            'offset' => '',
            'finderQuery' => '',
        )
    );

    public $hasMany = array(
        'Anchor' => array(
            'className' => 'Anchor',
            'foreignKey' => 'page_id',
            'dependent' => true,
            'conditions' => '',
            'fields' => '',
            'order' => '',
            'limit' => '',
            'offset' => '',
            'exclusive' => '',
            'finderQuery' => '',
            'counterQuery' => ''
        ),
        'RelatedPage' => array(
            'className' => 'RelatedPage',
            'foreignKey' => 'page_id',
            'dependent' => true,
            'conditions' => '',
            'fields' => '',
            'order' => '',
            'limit' => '',
            'offset' => '',
            'exclusive' => '',
            'finderQuery' => '',
            'counterQuery' => ''
        )
    );
}
