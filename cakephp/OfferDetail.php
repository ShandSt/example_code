<?php
App::uses('AppModel', 'Model');
/**
 * OfferDetail Model
 *
 * @property PayMethod $PayMethod
 */
class OfferDetail extends AppModel {

	public $validate = array(
		'alias' => 'notBlank'
	);

	public function recountRating($offer_detail_id = null) {
        $countReviews = $this->query("SELECT COUNT(id) as count FROM reviews WHERE offer_detail_id = $offer_detail_id AND published = 1");
        $countReviews = isset($countReviews[0][0]['count']) ? $countReviews[0][0]['count'] : '';

        $avgRate = $this->query("SELECT AVG(rating) as avg FROM reviews WHERE offer_detail_id = $offer_detail_id AND published = 1");
        $avgRate = isset($avgRate[0][0]['avg']) ? $avgRate[0][0]['avg'] : '';
        $this->id = $offer_detail_id;
        $this->saveField('rating', $avgRate);
        $this->saveField('reviews_count', $countReviews);
    }

/**
 * hasAndBelongsToMany associations
 *
 * @var array
 */
	public $hasAndBelongsToMany = array(
		'PayMethod' => array(
			'className' => 'PayMethod',
			'joinTable' => 'offer_details_pay_methods',
			'foreignKey' => 'offer_detail_id',
			'associationForeignKey' => 'pay_method_id',
			'unique' => 'keepExisting',
			'conditions' => '',
			'fields' => '',
			'order' => '',
			'limit' => '',
			'offset' => '',
			'finderQuery' => '',
		)
	);

	public $belongsTo = array(
		'Domain' => array(
			'className' => 'Domain',
			'foreignKey' => 'domain_id',
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

    public $hasMany = array(
        'Review' => array(
            'className' => 'Review',
            'foreignKey' => 'offer_detail_id',
            'dependent' => false,
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
