<?php

/*

f2b2h/lang/en/order.php

f2b2h/lang/en/email/order_send_user_item_confirmation_number.html - отправка юзеру нового confirmation number
f2b2h/lang/en/email/order_user_cart_item_cancellation_notice.html - сообщаем покупателю о части заказа
f2b2h/lang/en/email/order_partner_cart_item_cancellation_notice.html - сообщаем партнёру о отмене части заказа

*/

class page_order extends f2b2h {

	public $id;

	public $cart_id;

	public $max_cards;

	// Список статусов заказов/их частей и табов
	// Все статусы
	public $states_name = array (
		0	=> 'paid',
		1	=> 'confirmed',
		2	=> 'unconfirmed',
		3	=> 'cancelled',
		4	=> 'completed',
		5	=> 'deleted',
		6	=> 'arrival7days',
		7	=> 'made',
		8	=> 'checked_in',
		9	=> 'card_hold',
		10	=> 'no_show',
	);
	private $tabs = array (
		'all'				=> array(0, 1, 8, 2, 10, 3, 4),
		'rs_only'			=> array(9),
		'active' 			=> array(0, 1, 2),
		'completed'			=> array(4),
		'cancelled'			=> array(3),
		'deleted'			=> array(5),
		'made'				=> array(7),
		'confirmed'			=> array(1),
		'checked_in'		=> array(8),
		'unconfirmed'		=> array(2),
		'no_show'			=> array(10),
		'paid'				=> array(0),
		'payment_due'		=> array(0, 1, 2, 4),
		'payment_received'	=> array(0, 1, 2, 4),
		'arrival7days'		=> array(6),
	);

	// Доступные для заказов TS
	public $ts_available_states = array (
		0	=> 'paid',
		1	=> 'confirmed',
		2	=> 'unconfirmed',
		3	=> 'cancelled',
		4	=> 'completed',
		5	=> 'deleted',
		6	=> 'arrival7days',
		7	=> 'made',
		8	=> 'checked_in',
		9	=> 'no_show',
	);
	// RS
	public $rs_view_available_states = array (
		'made',
		'paid',
		'confirmed',
		'checked_in',
		'card_hold',
		'unconfirmed',
		'no_show',
		'cancelled',
		'completed',
	);

	public $rs_edit_available_states = array (
		'made',
		'paid',
		'confirmed',
		'checked_in',
		'card_hold',
		'unconfirmed',
		'no_show',
		'completed',
		'cancelled',
	);

	// Кол-во заказов на страницу списка
	public $orders_per_page = 50;


	// Считаем коммиссию, которая полагается держателю портала за заказ, сделанный через этот портал
	public function calculate_portal_commission($order_id = null, $cart_totals = null, $affiliate_id = null, $with_items = false) {

		$portal_user_commission = 0;
		$base_data = array();

		$result_commission = array (
			'total'			=> 0,
			'cart_items'	=> array(),
		);

		// Это в случае step2 - в этом случае $cart_totals уже передан, а наличие комиссии проверяем по тому,
		// происходит ли ситуация в портале, или по тому, что админом явно передан $affiliate_id
		if (is_null($order_id)) {

			if (!is_null($affiliate_id)) {

				$this->db->query("
				SELECT
					users.affiliate_comission
				FROM
					users
					JOIN portal ON portal.user_id = users.id
				WHERE
					portal.affiliate_id = ?",
					$affiliate_id
				);
				$db_res = $this->db->result;

				if (($portal_user = $this->db->fetch($db_res)) && $portal_user['affiliate_comission']) {

					$portal_user_commission = $portal_user['affiliate_comission'];
				}
			}
			else if (is_portal()) {

				$this->db->query("
				SELECT
					users.affiliate_comission
				FROM
					users
				WHERE
					users.id = ?",
					get_portal_param('user_id')
				);
				$db_res = $this->db->result;

				if (($portal_user = $this->db->fetch($db_res)) && $portal_user['affiliate_comission']) {

					$portal_user_commission = $portal_user['affiliate_comission'];
				}
			}
		}
		// Это в случае редактирования заказа - проверяем наличие комиссии по наличию affiliate_id в заказе
		// $cart_totals в этом случае нужно ещё получить
		else {

			$this->db->query("
				SELECT
					users.affiliate_comission,

					orders.cart_id
				FROM
					users
					JOIN portal ON portal.user_id = users.id
					JOIN orders ON orders.affiliate_id = portal.affiliate_id
				WHERE
					orders.affiliate_id != ''
						AND
					orders.id = ?",
				$order_id
			);
			$db_res = $this->db->result;

			if ($portal_user = $this->db->fetch($db_res)) {

				if ($portal_user['affiliate_comission']) {

					$portal_user_commission = $portal_user['affiliate_comission'];
				}

				$this->add_page('cart_item');
				$cart_totals = $this->page_cart_item->get_cart_totals($portal_user['cart_id']);
			}
		}

		// Коммисия нашлась - значит нужно считать
		if ($portal_user_commission) {

			// Есть ли скидка
			$is_discount = ($cart_totals['discount'] ? true : false);

			// Считаем только тоталы, или ещё и каждый итем в отдельности
			$base_data['total'] = $cart_totals['total'];
			if ($with_items) {

				foreach ($cart_totals['cart_items'] as $item_id => $item_data) {

					$base_data[$item_id] = $item_data['total'];
				}
			}


			// Собственно рассчёт
			foreach ($base_data as $data_key => $data_values) {

				// Сумма нашей выгоды без booking fee
				if ($is_discount) {

					$portal_commission = $data_values['comission_with_tax_discount'] - $data_values['booking_fee_amount'];
				}
				else {

					$portal_commission = $data_values['comission_with_tax'];
				}

				// Комиссия для выплаты партнёру
				$portal_commission *= floatval($portal_user_commission) / 100;

				// Округляем
				$portal_commission = round($portal_commission, 2);

				if ($portal_commission < 0) $portal_commission = 0;

				if (is_numeric($data_key)) $result_commission['cart_items'][$data_key] = $portal_commission;
				else $result_commission[$data_key] = $portal_commission;
			}
		}
		else if (!empty($portal_user) && $with_items) {

			$cart_items = array_keys($cart_totals['cart_items']);

			foreach ($cart_items as $item_id) {

				$result_commission['cart_items'][$item_id] = 0;
			}
		}

		return ($with_items ? $result_commission : $result_commission['total']);
	}


	// Проверяем, нет ли пользователей, редактирующих текущий заказ в текущий момент времени
	// Возвращаем null, если такого пользователя нет, или его имя
	public function check_user_edit_presence() {

		$current_user = null;

		$period = 70;

		$this->db->query("
			SELECT
				order_user_edit.user_id,

				users.first_name,
				users.last_name
			FROM
				order_user_edit
				JOIN users ON users.id = order_user_edit.user_id
			WHERE
				order_user_edit.order_id = ?
					AND
				order_user_edit.user_id <> ?
					AND
				order_user_edit.last_update + INTERVAL ? SECOND > NOW()",
			$this->id,
			$this->user->data['id'],
			$period
		);

		if ($user = $this->db->fetch()) {

			$current_user = $user['first_name'].' '.$user['last_name'];
		}

		return $current_user;
	}

	// Проверка на возможность юзером(менджеру) редактировать заказ или его часть
	// - $state, состояние заказа или его части
	public function is_user_permitted_to_edit_state($state) {

		if ($this->user->is_main_admin()) return true;

		if (!$this->user->is_permitted('order_edit')) return false;

		if (($state == 'completed') && $this->user->is_permitted('order_completed_edit')) return true;

		return false;
	}


	public function get($order_id, $partner_id = null) {

		if (!$order_id) return null;


		$this->db->query("
			SELECT
				orders.*,

				orders_states.state AS order_state,

				orders_users_data.first_name AS customer_first_name,
				orders_users_data.last_name AS customer_last_name,
				orders_users_data.email AS customer_email,
				orders_users_data.phone AS customer_phone,
				orders_users_data.phone2 AS customer_phone2,
				orders_users_data.address AS customer_address,
				orders_users_data.city AS customer_city,
				orders_users_data.zip AS customer_zip,
				orders_users_data.state_id AS customer_state_id,
				orders_users_data.state_name AS customer_state_name,
				orders_users_data.country_id AS customer_country_id,
				
				users.rs_order_prefix AS prefix
			FROM
				orders
				LEFT JOIN orders_states ON orders_states.id = orders.last_state_id
				LEFT JOIN orders_users_data ON orders_users_data.order_id = orders.id
				JOIN cart_items ON cart_items.cart_id = orders.cart_id
				JOIN products ON products.id = cart_items.product_id
				JOIN users ON users.id = products.partner_id 
			WHERE
				orders.id = ?
				".($partner_id ? " AND products.partner_id = ".intval($partner_id) : "")."
			GROUP BY
				orders.id",
			$order_id
		);

		if ($order = $this->db->fetch()) {

			$payment_responses = '';
			$payment_errors = '';
			$stripe_customers = '';

			if ($order_payments = $this->get_payment(null, $order_id)) {

				$card = 1;
				$pay_by = 'card';

				foreach ($order_payments as $order_payment) {

					// Собираем данные страйпа для доп. списаний в дашборде (пока что по идее страйп-платёж один)
					$payment_data = $this->db->decode_data($order_payment['data']);
					if (isset($payment_data['stripe']['customer_id'])) {

						$stripe_customers .= $payment_data['stripe']['customer_id'].' ';
					}

					// Выглядит, будто оно перезаписывается, но в случае с PP платёж всегда один
					if ($order_payment['type'] == 'paypal') {

						$pay_by = 'paypal';

						$payment_responses = $order_payment['last_payment_response'];
						$payment_errors = $order_payment['last_payment_errors'];
					}
					// Оплата одной или несколькими кредитками
					else {

						if (!empty($order_payment['last_payment_response'])) {

							$payment_responses .= 'Card' . $card . ': ' . $order_payment['last_payment_response'] . ' ';
						}

						if (!empty($order_payment['last_payment_errors'])) {

							$payment_errors .= 'Card' . $card . ': ' . $order_payment['last_payment_errors'] . ' ';
						}

						$card++;
					}
				}
			}
			$order['last_payment_response'] = $payment_responses;
			$order['last_payment_errors'] = $payment_errors;
			$order['last_payment_type'] = (isset($pay_by) ? $pay_by : '');
			if ($stripe_customers !== '') $order['stripe_customers'] = $stripe_customers;

			$this->db->query("
				SELECT
					group_order.id
				FROM
					group_order
				WHERE
					group_order.order_id LIKE '%".$order['id']."%'"
			);
			$order['group_order_id'] = (($group_order = $this->db->fetch()) ? $group_order['id'] : null);

			$order['format_id'] = ($order['made_from_rs'] ? $order['prefix'].'-' : 'TRS-').$order['id'];
		}

		return $order;
	}

	public function get_payment($payment_id = null, $order_id = null) {

		if (!$payment_id && !$order_id) return null;

		$payments = array();

		$where = array();
		$values = array();

		if ($payment_id) {

			$where[] = 'payments.id = ?';
			$values[] = $payment_id;
		}

		if ($order_id) {

			$where[] = 'orders.id = ?';
			$values[] = $order_id;
		}

		$this->db->query("
			SELECT
				payments.*,

				orders.id AS order_id
			FROM
				payments
				LEFT JOIN orders ON orders.id = payments.order_id
			WHERE
				".implode(" AND \r\n", $where),
			$values
		);

		while ($payment = $this->db->fetch()) $payments[$payment['id']] = $payment;

		return ($payments ? $payments : null);
	}


	// Проверяет возможность использования опции pay_delay и возвращает сумму для первой оплаты
	public function pay_delay_get_amount($cart_id) {

		// Рассматриваем только части корзины с нек-й датой приезда(cart_items.calendar_id IS NOT NULL)
		$this->db->query("
			SELECT
				calendar.date AS calendar_date,

				products.use_calendar,
				products.affiliate_id,

				carts.total AS cart_total
			FROM
				cart_items
				JOIN carts ON carts.id = cart_items.cart_id
				JOIN products ON products.id = cart_items.product_id
				JOIN calendar ON calendar.id = cart_items.calendar_id
			WHERE
				cart_items.cart_id = ?",
			$cart_id
		);

		if (!$this->db->count()) return null;

		while ($cart_item = $this->db->fetch()) {

			// Если хоть для одной части заказа день прибытия ближе, чем через 2 недели, то опция не доступна
			if ((strtotime($cart_item['calendar_date']) - time()) < 14*24*3600) return null;

			// Если хоть одна часть является партнёрским отелем - выходим
			if ($cart_item['affiliate_id']) return null;
		}

		$this->add_page('cart');
		$cart = $this->page_cart->get($cart_id);
		$cart_total = $cart['total'];

		if ($cart_total < $this->config('pay_delay_min_price')) return null;

		$pay_delay = array (
			'cart_total'		=> $cart_total,
			'pay_delay_amount'	=> round_price($cart_total/2),
		);

		// Если общая сумма больше минимальной необходимой, возвращаем половину стоимости заказа для первой оплаты
		return $pay_delay;
	}

	// Последний день для оплаты по pay_delay
	// return date("Y-m-d")
	public function pay_delay_payment_last_day($order_id) {

		$min_date = null;

		$this->db->query("
			SELECT
				orders.id,
				MIN(calendar.date) AS date
			FROM
				cart_items
				JOIN calendar ON calendar.id = cart_items.calendar_id
				JOIN products ON products.id = cart_items.product_id
				JOIN orders ON orders.cart_id = cart_items.cart_id
			WHERE
				orders.id = ?
					AND
				NOT cart_items.state_name IN ('cancelled', 'deleted')",
			$order_id
		);

		if ($cart_item = $this->db->fetch()) $min_date = $cart_item['date'];
		
		if(!is_null($min_date)) return date("Y-m-d", strtotime('-1 week', strtotime($min_date)));
		else return false;
	}

	// Смена статуса заказа
	public function set_state($order_id, $state, $confirmation = null, $partner_id = 0) {

		if (
			!in_array($state, $this->states_name)
				||
			(
				$this->user->is_partner()
					&&
				in_array($state, array('cancelled', 'deleted'))
			)
			) {

			return;
		}

		if ($order = $this->get($order_id)) {

			$this->log->save_order_log(
				'Order state is changed to <b>'.ucfirst($state).'</b>',
				$order['id'],
				null,
				($partner_id ? $partner_id : null)
			);

			// Get cart_item ids which is owned by current user.
			$this->db->query("
				SELECT
					cart_items.id,
					cart_items.state_name,
					cart_items.confirmation,

					products.product_type_id,
					products.affiliate_id,
					products.partner_id
				FROM
					orders
					JOIN cart_items ON cart_items.cart_id = orders.cart_id
					JOIN products ON products.id = cart_items.product_id
				WHERE
					orders.id = ?",
				$order['id']
			);

			// Cart items owned by current user (client/partner/admin).
			$owned_cart_item_ids = array();

			while ($cart_item = $this->db->fetch()) {

				if (
					($cart_item['state_name'] != $state)
						&&
					// Общая смена статуса заказа не может влиять на статус оменённых/удалённых частей
					(!in_array($cart_item['state_name'], array('cancelled', 'deleted')))
						&&
					(
						// Admin.
						$this->user->is_admin()
							||
						// Partner-owner.
						($partner_id && ($cart_item['partner_id'] == $partner_id))
							||
						// Partner-ordermaker
						($partner_id && ($state == 'made'))
							||
						// Partner-ordermaker
						($partner_id && !empty($_SESSION['order_id']))
							||
						// User-ordermaker (client).
						(!$this->user->is_admin() && !$partner_id)
					)
					) {

					$owned_cart_item_ids[] = $cart_item;
				}
			}

			if ($confirmation) $confirmation = explode(',', $confirmation);
			else $confirmation = array();

			$confirmation = array_map('trim', $confirmation);

			foreach ($owned_cart_item_ids as $cart_item) {

				$cart_item_confirmation = null;
				if ((product_type($cart_item['product_type_id']) == 'hotel') && ($state == 'confirmed') && !$cart_item['affiliate_id']) {

					if (!empty($cart_item['confirmation']) && in_array($cart_item['confirmation'], $confirmation)) {

						unset($confirmation[array_search($cart_item['confirmation'], $confirmation)]);

						$cart_item_confirmation = $cart_item['confirmation'];
					}
					else if (!empty($confirmation)) {

						$cart_item_confirmation = array_shift($confirmation);
					}
					else if (!empty($cart_item['confirmation'])) {

						$cart_item_confirmation = $cart_item['confirmation'];
					}
				}

				$this->cart_item_set_state(
					$cart_item['id'],
					$state,
					$cart_item_confirmation,
					($partner_id ? $partner_id : null),
					true
				);
			}

			$this->sync_state($order['id']);
		}
	}

	// Смена статуса части заказа
	public function cart_item_set_state($cart_item_id, $state, $confirmation_number = null, $partner_id = 0, $dont_update_order_state = false) {

		if (!in_array($state, $this->states_name)) return null;

		$result = array (
			'general'				=> true,
			'restoration_performed'	=> false,
			'errors'				=> array(),
		);

		// Узнаём order_id  заказа для последующей синхронизации его статуса,
		// а так же записываем в лог о изменении статуса
		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.calendar_id,
				cart_items.is_fh_item,
				cart_items.data,
				cart_items.state_name,
				cart_items.gift_code,
				cart_items.sold_reduced,

				orders.id AS order_id,
				orders.made_from_rs,

				products.partner_id AS partner_id,

				calendar.date
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				JOIN products ON products.id = cart_items.product_id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
			WHERE
				cart_items.id = ?",
			$cart_item_id
		);

		if (!($cart_item = $this->db->fetch())) return;

		$book_info = $this->db->decode_data($cart_item['data']);


		// Если часть возвращается из статуса отменённой/удалённой, то выполняем валидацию
		if (is_cancelled_state($cart_item['state_name']) && !is_cancelled_state($state)) {

			// Пока что делаем это только для трипов
			if ($book_info['product']['product_type'] == 'trip') {

				$validation_errors = array();

				if (!$cart_item['is_fh_item']) {

					$this->add_page('trip');

					$is_past = null;
					if ($cart_item['date'] && (strtotime($cart_item['date']) < strtotime("midnight"))) {

						$is_past = true;
					}

					$day_data = array(
						'in_order' => false,

						'calendar_id' => ($cart_item['calendar_id'] ? $cart_item['calendar_id'] : null),
						'trip_id' => $book_info['product']['id'],
						'need_tickets_guests' => $book_info['product']['need_tickets_guests'],
						'is_gift' => (($cart_item['gift_code'] != '') ? true : false),
						'actual_is_gift' => (($cart_item['gift_code'] != '') ? true : false),
						'schedule_id' => ($book_info['schedule'] ? $book_info['schedule']['id'] : null),
						'inventory_id' => ($book_info['inventory'] ? $book_info['inventory']['id'] : null),
						'tickets' => array(),
						'additional_options' => array(),

						'is_past' => $is_past,
					);

					foreach ($book_info['tickets'] as $ticket_data) {

						$day_data['tickets'][$ticket_data['id']] = $ticket_data['booked'];
					}

					if ($book_info['additional_options']) {

						foreach ($book_info['additional_options'] as $option_data) {

							if ($option_data['type'] == 'text') {

								$day_data['additional_options'][$option_data['id']] = $option_data['value']['name'];
							}
							else if ($option_data['type'] == 'select') {

								$day_data['additional_options'][$option_data['id']] = $option_data['value']['id'];
							}
						}
					}

					$validation_errors = $this->page_trip->valid_day_booking($day_data);
				}
				else {

					// Данные для связывания с fareharbor
					require_once($this->config('path').'/inc/fareharbor.php');
					$fareharbor = new fareharbor();

					$fh_tickets = array();

					foreach ($book_info['tickets'] as $ticket_id => $ticket) {

						$fh_tickets[$ticket_id] = $ticket['booked'];
					}


					$fh_result = $fareharbor->fh_request('validate', array (
						'fh_id'					=> $book_info['fh_data']['fh_schedule']['id'],
						'fh_trip_data'			=> $book_info['fh_data']['fh_trip_data'],
						'tickets'				=> $fh_tickets,
						'additional_options'	=> $book_info['additional_options'],
						'fh_tickets_config'		=> $book_info['fh_data']['fh_tickets_config'],
						'comment_guest'			=> $book_info['comments']['guest'],
					));

					if (is_null($fh_result) || $fh_result['is_bookable'] == false) {

						if (is_null($fh_result)) {

							$validation_errors[] = 'FH is not responding. Please try later';
						}
						else {

							$validation_errors[] = '[Fareharbor]: '.$fh_result['error'];
						}
					}
				}

				$result['restoration_performed'] = true;
				$result['general'] = ($validation_errors ? false : true);
				$result['errors'] = $validation_errors;
			}
		}


		if ($result['general']) {

			$this->add_page('cart_item');

			// На этапе step2 залогиненый партнёр "имеет право" устанавливать сотояния любым частям (как и любой гость),
			// т.к. он просто делает заказ. В этом случае в функцию прийдёт его $partner_id
			$ignore_partner = false;

			if ($partner_id && is_mode('step2') && ($partner_id == $this->user->data('id'))) $ignore_partner = true;


			if (
				($cart_item['state_name'] != $state)
				&&
				!($this->user->is_partner() && is_cancelled_state($cart_item['state_name']))
			) {

				if ($this->user->is_partner()) $partner_id = $this->user->data('id');

				// Выставляем состояние элементу заказа
				$this->db->query("
				UPDATE
					cart_items
					JOIN products ON products.id = cart_items.product_id
				SET
					cart_items.state_name = ?,
					cart_items.state_time = ?
				WHERE
					cart_items.id = ?
					" . (($partner_id && !$ignore_partner) ? "AND products.partner_id = " . $this->db->escape($partner_id) : "") . "
				",
					$state,
					format_date_for_db(null, true),
					$cart_item['id']
				);

				$this->log->save_order_log(
					'Cart item state changed from <b>' . ucfirst($cart_item['state_name']) . '</b> to <b>' . ucfirst($state) . '</b>',
					$cart_item['order_id'],
					$cart_item['id'],
					($partner_id ? $partner_id : $this->user->data('id'))
				);

				if (!empty($book_info['cancellation_data']) && ($state != 'cancelled')) {

					unset($book_info['cancellation_data']);

					$this->db->insert_update('cart_items', array(
						'id' => $cart_item['id'],
						'data' => $this->db->encode_data($book_info),
					));
				}


				if ($book_info['product']['product_type'] == 'trip') {

					if (is_cancelled_state($state)) {

						// Вычищаем баркоды, если они были
						foreach ($book_info['tickets'] as $key => $ticket) {

							$book_info['tickets'][$key]['barcodes'] = array();
						}
						$this->db->insert_update('cart_items', array(
							'id' => $cart_item['id'],
							'data' => $this->db->encode_data($book_info),
						));

						if (!$cart_item['is_fh_item']) {

							$this->page_cart_item->restore_trip_tickets($cart_item['id']);
						}
						else {

							$this->fh_delete_item($cart_item['id']);

							// Перестраиваем кеш трипа
							$this->add_page('trip');
							$this->page_trip->invalidate_fh_cache($book_info['product']['id'], true);
						}
					}
					else if (is_cancelled_state($cart_item['state_name'])) {

						// Если мы уходим из отменённого состояния, то уже больше рефандить не будем
						// Сам же рефанд делается в trip_item_update_cancelled_data т.к. только там есть сумма рефанда
						if (!$cart_item['made_from_rs']) {

							$this->db->insert_update_no_id('refunds', array(
								'cart_item_id' => $cart_item['id'],
								'status' => 'block',
							),
								array('cart_item_id')
							);
						}

						if (!$cart_item['is_fh_item']) {

							$this->page_cart_item->reduce_trip_tickets($cart_item['id']);
						}
						else {

							$this->fh_rebook_cart_item($cart_item['id']);
						}

						$this->update_order_totals($cart_item['order_id']);
					}


				}


				if (
					($cart_item['state_name'] == 'made')
						&&
					($state != 'made')
						&&
					!$cart_item['sold_reduced']
				) {

					if ($book_info['product']['product_type'] == 'trip') {

						if (!$cart_item['is_fh_item']) {

							$this->page_cart_item->reduce_trip_tickets($cart_item['id']);
						}
						else {

							$this->fh_rebook_cart_item($cart_item['id']);
						}
					}
					else if ($book_info['product']['product_type'] == 'hotel') {

						$this->page_cart_item->reduce_hotel_rooms($cart_item['id']);
					}
				}
			}

			if ($confirmation_number) {

				$this->set_item_confirmation_number($cart_item['id'], $confirmation_number, true);
			}

			if (!$dont_update_order_state) $this->sync_state($cart_item['order_id']);
		}

		if (!is_cancelled_state($cart_item['state_name']) && is_cancelled_state($state)) {

			push_partner_event('order_cancel', array (
				'partner_id'	=> $cart_item['partner_id'],
				'object_id'		=> $cart_item['order_id'],
			));
		}

		return $result;
	}

	// устанавливаем confirmation number для части заказа
	public function set_item_confirmation_number($cart_item_id, $confirmation_number, $dont_update_state = false) {

		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.cart_id,
				cart_items.confirmation,
				cart_items.state_name,

				orders.id AS order_id
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
			WHERE
				cart_items.id = ?",
			$cart_item_id
		);

		// Делаем запись в логе и отправляем письмо клиенту
		if ($cart_item = $this->db->fetch()) {

			$old_confirmation_number = $cart_item['confirmation'];

			// Обновляем confirmation number
			$this->db->query("
				UPDATE
					cart_items
				SET
					confirmation = ?
				WHERE
					id = ?",
				$confirmation_number,
				$cart_item['id']
			);

			// Выставляем статус confirmed, если ещё не выставлено
			if (!$dont_update_state && ($cart_item['state_name'] != 'confirmed')) {

				$this->cart_item_set_state($cart_item['id'], 'confirmed');
			}

			if ($old_confirmation_number != $confirmation_number) {

				$this->log->save_order_log(
					'Confirmation number '.(empty($old_confirmation_number) ? 'is set to' : 'changed from <b>'.$old_confirmation_number.'</b> to').' <b>'.$confirmation_number.'</b>',
					$cart_item['order_id'],
					$cart_item['id']
				);

				// Отправляем письмо юзеру с confirmation number
				$this->db->query("
					SELECT
						products.name AS hotel_name,

						orders.accesskey AS order_accesskey,

						orders_users_data.first_name AS customer_first_name,
						orders_users_data.email AS customer_email
					FROM
						cart_items
						JOIN products ON products.id = cart_items.product_id
						JOIN orders ON orders.cart_id = cart_items.cart_id
						JOIN orders_users_data ON orders_users_data.order_id = orders.id
					WHERE
						cart_items.id = ?",
					$cart_item['id']
				);
				$cart_item = $this->db->fetch();

				$this->mail->send($cart_item['customer_email'], 'order_send_user_item_confirmation_number', array (
					'hotel_name'			=> $cart_item['hotel_name'],
					'customer_name'			=> ($cart_item['customer_first_name'] ? $cart_item['customer_first_name'] : 'Customer'),
					'confirmation_number'	=> $confirmation_number,
					'website_url'			=> $this->config('website_url'),
					'access_key'			=> $cart_item['order_accesskey'],
					'site_phone'			=> $this->config('site_phone'),
				));
			}
		}
	}

	// Синхронизируем статус заказа согласно статусам его частей
	// $force - флаг принудительного обновления статуса
	public function sync_state($order_id, $force = false) {

		if (!($order = $this->get($order_id))) return;

		$old_order_state = $order['order_state'];

		$cart_items_states = array();

		$this->db->query("
			SELECT DISTINCT
				cart_items.state_name
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
			WHERE
				orders.id = ?",
			$order['id']
		);

		while ($cart_item = $this->db->fetch()) $cart_items_states[] = $cart_item['state_name'];

		if (!$cart_items_states) return;

		$new_order_state = null;

		if (count($cart_items_states) == 1) $new_order_state = $cart_items_states[0];
		else if (in_array('card_hold', $cart_items_states)) $new_order_state = 'card_hold';
		else if ((count($cart_items_states) == 2) && in_array('cancelled', $cart_items_states)) {

			$key = array_search('cancelled', $cart_items_states);
			if ($key !== false) unset($cart_items_states[$key]);

			$new_order_state = array_pop($cart_items_states);
		}
		else $new_order_state = 'paid';

		if (($new_order_state != $old_order_state) || $force) {

			$order_state_id = $this->db->insert_update('orders_states', array (
				'order_id'	=> $order['id'],
				'time'		=> format_date_for_db(null, true),
				'state'		=> $new_order_state,
			));

			$this->save(array(
				'id'			=> $order['id'],
				'closed'		=> in_array(strtolower($new_order_state), array('confirmed', 'unconfirmed', 'cancelled', 'completed')),
				'last_state_id'	=> $order_state_id,
			));
		}
	}

	// Отсылаем письма клиенту и партнёру с информацией о отмене части заказа
	public function cancelled_item_send_mails($cart_item_id, $send_emails, $send_sms) {

		/*
			Письмо клиенту
		*/

		// Берём совокупные данные юзера и партнёра, к которым относится часть заказа
		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.data,
				cart_items.is_fh_item,

				orders.id AS order_id,
				orders.accesskey AS order_accesskey,
				orders.made_from_rs AS order_made_from_rs,
				orders.affiliate_id AS order_affiliate_id,

				carts.count AS order_items_count,

				products.product_type_id,
				products.name AS product_name,

				orders_users_data.email AS user_email,
				orders_users_data.phone AS user_phone,
				orders_users_data.first_name AS user_first_name,
				orders_users_data.last_name AS user_last_name,
				orders_users_data.last_name AS user_last_name,

				partners.id AS partner_id,
				partners.email,
				partners.phone AS partner_phone,
				partners.phone2 AS partner_phone2,
				partners.first_name,
				partners.last_name,
				partners.name,
				partners.title,
				partners.rs_order_prefix AS order_prefix,
				partners.data AS partner_data,

				calendar.date AS arrival_date,
				IFNULL(calendar2.date, '') AS check_out_date
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				JOIN carts ON carts.id = cart_items.cart_id
				JOIN orders_users_data ON orders_users_data.order_id = orders.id
				JOIN products ON products.id = cart_items.product_id
				JOIN users AS partners ON partners.id = products.partner_id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2
			WHERE
				cart_items.id = ?",
			$cart_item_id
		);
		if (!($cart_item = $this->db->fetch())) return;

		$cart_item['order_id'] = ($cart_item['order_made_from_rs'] ? $cart_item['order_prefix'].'-' : 'TRS-').$cart_item['order_id'];

		$book_info = $this->db->decode_data($cart_item['data']);
		$cart_item['partner_data'] = $this->db->decode_data($cart_item['partner_data']);


		$cancel_fee = $book_info['cancellation_data']['site_fee'] + $book_info['cancellation_data']['partner_fee'];
		$cancel_reason = '-';
		if (!empty($book_info['cancellation_data']['cancel_reason'])) {

			$cancel_reason = $book_info['cancellation_data']['cancel_reason'];
		}

		$return_path = '';

		if (!empty($cart_item['partner_data']['amazon']['amazon_verified_email'])) {

			$return_path = $cart_item['partner_data']['amazon']['amazon_verified_email'];
		}

		// Отправляем письмо клиенту
		if ($send_emails) {

			$this->mail->send($cart_item['user_email'].'|'.$return_path, 'order_user_cart_item_cancellation_notice', array(
				'customer_name'			=> ($cart_item['user_first_name'] ? $cart_item['user_first_name'] : 'Customer'),
				'order_confirmation'	=> order_confirmation_number($cart_item['order_id']),
				'order_part'			=> (($cart_item['order_items_count'] > 1) ? ' part' : ''),
				'product_name'			=> $cart_item['product_name'],
				'cancel_fee'			=> money($cancel_fee, false),
				'refund_amount'			=> money($book_info['cancellation_data']['refund_amount'], false),
				'cancel_reason'			=> $cancel_reason,
				'site_phone'			=> $this->config('site_phone'),
			));
		}
		// СМС отправляем только если партнёр не запретил этого
		if ($send_sms && $cart_item['partner_data']['flags']['send_sms']) {

			if ($cart_item['order_made_from_rs']) {

				send_sms($cart_item['user_phone'], 'sms_customer_cancellation', array(
						'conf' => $cart_item['order_id'],
						'order_part' => (($cart_item['order_items_count'] > 1) ? ' part' : ''),
						'link_order' => 'https://reservations.bookwithrs.com/access/'.
							$cart_item['partner_id'].'/'.
							$cart_item['order_id'].'/user-'.
							md5($cart_item['order_accesskey']),
					),
					true
				);
			}
			else {

				$external_domain = null;

				if ($cart_item['order_affiliate_id']) {

					if ($portal = $this->portal->get(array('affiliate_id' => $cart_item['order_affiliate_id']))) {

						$portal = array_merge($portal, $this->db->decode_data($portal['data']));

						if (isset($portal['iframe']) && !$portal['iframe']['is_disabled']) {

							$external_domain = $portal['iframe']['parent'];
						}
					}
				}

				send_sms($cart_item['user_phone'], 'sms_customer_cancellation', array(
						'conf' => $cart_item['order_id'],
						'order_part' => (($cart_item['order_items_count'] > 1) ? ' part' : ''),
						'link_order' => (is_null($external_domain) ? $this->config('website_url').'/' : $external_domain.'#').
							'order/view/'.$cart_item['order_id'].'/user-'.md5($cart_item['order_accesskey']),
					),
					false
				);
			}
		}

		// Письмо партнёру
		$cart_item_html = '';

		if ($send_emails) {

			// И отдельно по типу продукта остальное
			if (product_type($cart_item['product_type_id']) == 'trip') {

				$schedule_html = '';
				if ($cart_item['is_fh_item']) {

					$schedule_html = '<br />Schedule time: ' . $book_info['fh_data']['fh_schedule']['time'];
				}
				else if ($book_info['schedule']) {

					$schedule_html = '<br />Schedule time: ' . $book_info['schedule']['time'];
				}

				// Информация о доп. опциях
				$additional_options_html = '';

				foreach ($book_info['additional_options'] as $option) {

					$additional_options_html .= '<br />' . $option['name'] . ': ' . $option['value']['name'];

					if ($option['type'] == 'select') {

						$additional_options_html .= ' (' . money($option['value']['price'], true) . ')';
					}
				}

				$cart_item_html .= '
' . ($cart_item['arrival_date'] ? 'Arrival date: ' . format_time($cart_item['arrival_date'], false, true, true) : '') . '
' . ($schedule_html ? $schedule_html : '') . '
<br />Tickets: ' . format_tickets_string($book_info['tickets']) . '
' . ($additional_options_html ? '<br />Additional options: ' . $additional_options_html : '');

			} else if (product_type($cart_item['product_type_id']) == 'hotel') {

				// Цены по дням
				$days_rates = '';
				foreach ($book_info['days_info'] as $date => $day_info) {

					$days_rates .= '<br />' . date("n/j/Y", strtotime($date)) . ': ' . ($day_info['free_night'] ? 'FREE' : money($day_info['day_rate'], true));
				}

				$cart_item_html .= '
Check-in: ' . format_time($cart_item['arrival_date'], false, true) . '
<br />Check-out: ' . format_time($cart_item['check_out_date'], false, true) . '
<br />Rooms: ' . format_rooms_string($book_info, true) . '
<br />Rates:' . $days_rates;
			}

			$this->mail->send($cart_item['email'], 'order_partner_cart_item_cancellation_notice', array(
				'customer_name' => $this->user->format_name(array('first_name' => $cart_item['user_first_name'], 'last_name' => $cart_item['user_last_name'])),
				'partner_name' => $this->user->format_name($cart_item),
				'product_name' => $cart_item['product_name'],
				'order_confirmation' => order_confirmation_number($cart_item['order_id']),
				'cart_item_id' => $cart_item['id'],
				'formatted_arrival' => ($cart_item['arrival_date'] ? '(For '.date("l, F jS, Y", strtotime($cart_item['arrival_date'])).')' : ''),
				'cart_item_data' => $cart_item_html,
				'partner_fee' => money($book_info['cancellation_data']['partner_fee'], false),
				'site_phone' => $this->config('site_phone'),
			));
		}

		if ($send_sms && $cart_item['partner_data']['flags']['send_sms']) {

			$phones = $cart_item['partner_phone2'];
			if (strpos($cart_item['partner_phone2'], ",")) $phones = explode(",", $cart_item['partner_phone2']);

			$partner_items_in_order = $this->get_order_products($cart_item['order_id'], $cart_item['partner_id']);

			send_sms($phones, 'sms_partner_cancellation', array(
				'conf'			=> $cart_item['order_id'],
				'order_part'	=> ((count($partner_items_in_order) > 1) ? ' part' : ''),
			));
		}

		if ($send_emails) {

			$this->log->save_order_log(
				'Sent cancellation emails.',
				$cart_item['order_id'],
				$cart_item['id']
			);
		}
		if ($send_sms && $cart_item['partner_data']['flags']['send_sms']) {

			$this->log->save_order_log(
				'Sent cancellation texts.',
				$cart_item['order_id'],
				$cart_item['id']
			);
		}
	}

	// Меняем суммы и причину штрафа для части заказа
	public function set_item_fee($cart_item_id, $site_fee = null, $partner_fee = null, $refund_amount = null, $cancel_reason = null) {

		// Текущие данные из бд
		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.data,

				orders.id AS order_id
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
			WHERE
				cart_items.id = ?",
			$cart_item_id
		);

		if ($cart_item = $this->db->fetch()) {

			$book_info = $this->db->decode_data($cart_item['data']);

			$need_update = false;
			$changed_prices = false;
			$log_text = array();


			if (empty($book_info['cancellation_data'])) {

				$book_info['cancellation_data'] = array (
					'site_fee'			=> 0,
					'partner_fee'		=> 0,
					'refund_amount'		=> 0,
					'cancel_reason'		=> '',
				);

				$need_update = true;
				$changed_prices = true;
			}

			if (!is_null($site_fee) && ($site_fee != $book_info['cancellation_data']['site_fee'])) {

				$log_text[] = 'TripShock Cxl fee changed from <b>'.money($book_info['cancellation_data']['site_fee'], true).'</b> to <b>'.money($site_fee, true).'</b>';

				$book_info['cancellation_data']['site_fee'] = round_price($site_fee);

				$need_update = true;
				$changed_prices = true;
			}
			if (!is_null($partner_fee) && ($partner_fee != $book_info['cancellation_data']['partner_fee'])) {

				$log_text[] = 'Vendor Cxl fee changed from <b>'.money($book_info['cancellation_data']['partner_fee'], true).'</b> to <b>'.money($partner_fee, true).'</b>';

				$book_info['cancellation_data']['partner_fee'] = round_price($partner_fee);

				$need_update = true;
				$changed_prices = true;
			}
			if (!is_null($refund_amount) && ($refund_amount != $book_info['cancellation_data']['refund_amount'])) {

				$log_text[] = 'Refund Amount changed from <b>'.money($book_info['cancellation_data']['refund_amount'], true).'</b> to <b>'.money($refund_amount, true).'</b>';

				$book_info['cancellation_data']['refund_amount'] = round_price($refund_amount);

				$need_update = true;
			}
			if (!is_null($cancel_reason) && ($cancel_reason != $book_info['cancellation_data']['cancel_reason'])) {

				$log_text[] = 'Reason for cancelling changed from <b>'.$book_info['cancellation_data']['cancel_reason'].'</b> to <b>'.$cancel_reason.'</b>';

				$book_info['cancellation_data']['cancel_reason'] = $cancel_reason;

				$need_update = true;
			}


			if ($need_update) {

				$this->db->insert_update('cart_items', array (
					'id'		=> $cart_item['id'],
					'data'		=> $this->db->encode_data($book_info),
				));


				if ($log_text) {

					$this->log->save_order_log(
						implode('<br />', $log_text),
						$cart_item['order_id'],
						$cart_item['id']
					);
				}


				if ($changed_prices) {

					$this->update_order_totals($cart_item['order_id']);
				}
			}
		}
	}


	// Сохраняем данные карты оплаты
	public function save_payment($payment_data) {

		if (!is_api_request()) {

			// При оплате paypal данные карты не запоняем, зато после успешной оплаты заполняем ID транзакции
			if (isset($payment_data['paypal_payment'])) {

				$payment = array (
					'order_id' => $payment_data['order_id'],
					'type' => 'paypal',
					'last_payment_response' => $payment_data['last_payment_response'],
				);

				if (isset($payment_data['transaction_id'])) {

					$payment['transaction_id'] = $payment_data['transaction_id'];
				}
			}
			else {

				$payment_data['card_number'] = substr($payment_data['card_number'], -4);

				$payment = array (
					'order_id' => $payment_data['order_id'],
					'type' => $payment_data['card_type'],
					'number' => $payment_data['card_number'],
					'first_name' => $payment_data['card_first_name'],
					'last_name' => $payment_data['card_last_name'],
				);
			}
		}

		// Если была ошибка платежа, и его снова пытаются провести, то это поможет избежать дублирования записей в payments
		if (isset($payment_data['error_payment_id']) && ($payment_data['error_payment_id'] != 0)) {

			$payment['id'] =  $payment_data['error_payment_id'];
		}

		// Для мобильника и партнёрки один заказ-один платёж. Значит проверяем здесь наличие платежа по данному заказу
		// Если он есть, то была ошибка платежа, и данные нового платежа нужно записать поверх старых
		if (
			is_mobile()
				||
			is_api_request()
				||
			isset($payment_data['paypal_payment'])
		) {

			$this->db->query("
				SELECT
					payments.id
				FROM
					payments
				WHERE
					payments.order_id = ?",
				$payment_data['order_id']
			);

			if ($previous_payment = $this->db->fetch()) {

				if (!is_api_request()) {

					$payment['id'] = $previous_payment['id'];
				}
				else {

					$payment_data['id'] = $previous_payment['id'];
				}
			}
		}

		$payment_id = $this->db->insert_update('payments', (is_api_request() ? $payment_data : $payment));

		return $payment_id;
	}

	// Сохраняем данные заказа
	public function save($order_data) {

		if (empty($order_data['id']) && empty($order_data['made'])) $order_data['made'] = date("Y-m-d H:i:s");

		$allowed_fields = array (
			'id',
			'user_id',
			'order_by_user_id',
			'cart_id',
			'made_from_rs',
			'is_office',
			'rs_agent',
			'payment_method',
			'accesskey',
			'made',
			'closed',
			'comment_admin',
			'total',
			'tax',
			'last_state_id',
			'was_refunded',
			'refund_amount',
			'gift_certificate',
			'affiliate_id',
			'affiliate_commission',
		);

		$save_fields = array();

		foreach ($order_data as $key => $value) {

			if (in_array($key, $allowed_fields)) $save_fields[$key] = $value;
		}

		$order_id = $this->db->insert_update('orders', $save_fields);

		if (empty($order_data['id'])) {

			$this->log_order_items_contents($order_id);

			$partner_id = ($this->user->is_partner() ? $this->user->data('id') : 0);

			$this->set_state($order_id, 'made', null, $partner_id);
		}

		return $order_id;
	}

	// Удаляем заказ и связанные с ним сущности
	public function delete($order_id) {

		$this->db->query("
			SELECT
				orders.*,

				users.id AS user_id,
				users.group_id AS user_group_id
			FROM
				orders
				LEFT JOIN users ON users.id = orders.user_id
			WHERE
				orders.id = ?",
			$order_id
		);

		if (!($order = $this->db->fetch())) return;

		// Удаляем платежные реквизиты и состояния платежей
		$this->db->delete('payments', array('order_id' => $order['id']));

		// Удаляем статусы
		$this->db->delete('orders_states', array('order_id' => $order['id']));

		// Удаляем лог изменения заказа
		$this->db->delete('order_log', array('order_id' => $order['id']));

		// Удаляем сам заказ
		$this->db->delete('orders', array('id' => $order['id']));

		// Удаляем ассоциированную корзину.
		$this->add_page('cart');
		$this->page_cart->delete($order['cart_id']);
	}


	// Логируем первый конфиг частей заказа в основной лог самого заказа
	public function log_order_items_contents($order_id) {

		if (empty($order_id)) return false;

		// Пишем логи для частей заказа
		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.data,
				
				calendar.date
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
			WHERE
				orders.id = ?",
			$order_id
		);
		$db_res = $this->db->result;

		while ($cart_item = $this->db->fetch($db_res)) {

			$general_data = '<b>Initial booking</b>: ';

			$item_data = $this->db->decode_data($cart_item['data']);

			$general_data .= 'Trip: "<b>'.$item_data['product']['name'].'</b>"';

			if (!empty($item_data['inventory'])) {

				$general_data .= ' Equipment: "<b>'.$item_data['inventory']['name'].'</b>"';
			}

			if (!empty($item_data['schedule'])) {

				$general_data .= ' Schedule: "<b>'.$item_data['schedule']['time'].'</b>"';
			}

			if (!empty($cart_item['date'])) {

				$general_data .= ' Date of Trip: "<b>'.format_date_for_front_search($cart_item['date'], true).'</b>"';
			}

			$this->log->save_order_log(
				$general_data,
				$order_id,
				$cart_item['id'],
				$this->user->data('id')
			);
		}

		// Отдельно логируем данные уровня всего заказа (пока что это только промо)
		$this->db->query("
			SELECT
				carts.data
			FROM
				carts
				JOIN orders ON orders.cart_id = carts.id
			WHERE
				orders.id = ?",
			$order_id
		);
		$db_res = $this->db->result;

		while ($cart = $this->db->fetch($db_res)) {

			$cart_data = $this->db->decode_data($cart['data']);
			//{"discount":{"total":70,"total_with_tax":70,"promo":{"id":"31800","name":"OM Trip promo","code":"HFISHD70"
			if (isset($cart_data['discount']['promo'])) {

				$general_data = 'Applied promo: "<b>'.$cart_data['discount']['promo']['name'].'</b>"'.
					' ('.$cart_data['discount']['promo']['code'].')';

				$this->log->save_order_log(
					$general_data,
					$order_id,
					null,
					$this->user->data('id')
				);
			}
		}

		return true;
	}


	// Обновляем общие данные заказа-корзины и частей
	// сначала обновляется скидка, потом суммы по частям и заказу-корзине
	// при наличии изменений пишутся соответствующие записи в лог
	public function update_order_totals($order_id) {

		if (!($order = $this->get($order_id))) return;


		// Текущие данные по скидке
		$this->add_page('cart');
		$cart = $this->page_cart->get($order['cart_id']);

		$old_cart_discount = array();
		if (!empty($cart['data']['discount'])) $old_cart_discount = $cart['data']['discount'];

		// Пересчитываем скидку
		$cart_discount = $this->price->cart_get_discount($cart['id']);

		// Если скидка изменилась, то пишем в лог и обновляем данные корзины
		if (
			($cart_discount['total'] || $old_cart_discount)
				&&
			($cart_discount != $old_cart_discount)
			) {

			foreach ($cart_discount['cart_items'] as $cart_item_id => $cart_item_discount) {

				if (empty($old_cart_discount['cart_items'][$cart_item_id])) {

					$this->log->save_order_log(
						'Added item discount <b>'.$cart_item_discount['discount'].'</b>',
						$order['id'],
						$cart_item_id
					);
				}
				else if ($cart_item_discount['discount'] != $old_cart_discount['cart_items'][$cart_item_id]['discount']) {

					$this->log->save_order_log(
						'Changed item discount from <b>'.$old_cart_discount['cart_items'][$cart_item_id]['discount'].'</b> to <b>'.$cart_item_discount['discount'].'</b>',
						$order['id'],
						$cart_item_id
					);
				}
			}

			// Учитываем части потерявшие скидку
			foreach ($old_cart_discount['cart_items'] as $cart_item_id => $old_cart_item_discount) {

				if (!empty($cart_discount['cart_items'][$cart_item_id])) continue;

				$this->log->save_order_log(
					'Removed item discount',
					$order['id'],
					$cart_item_id
				);
			}


			$cart['data']['discount'] = $cart_discount;

			$this->db->insert_update('carts', array (
				'id'		=> $cart['id'],
				'data'		=> $this->db->encode_data($cart['data']),
			));
		}


		// Обновляем суммы частей и заказа-корзины
		$this->add_page('cart_item');
		$cart_totals = $this->page_cart_item->get_cart_totals($cart['id']);

		$discount_text = '';
		if (!empty($cart_totals['discount'])) $discount_text = '(with discount)';


		$cart_items = $this->page_cart_item->get_list(array (
			'cart_id'		=> $cart['id'],
			'product_info'	=> true,
		));

		foreach ($cart_items as $cart_item) {

			if ($cart_item['affiliate_id']) continue;


			$need_update = false;

			$cart_item_total = $cart_item['total'];
			$cart_item_comission = $cart_item['tax'];

			$cart_item_totals = $cart_totals['cart_items'][$cart_item['id']]['total'];

			if ((string)$cart_item_totals['gross_with_tax_discount'] != $cart_item_total) {

				$cart_item_total = $cart_item_totals['gross_with_tax_discount'];

				$this->log->save_order_log(
					'Changed total'.$discount_text.' from <b>'.$cart_item['total'].'</b> to <b>'.$cart_item_total.'</b>',
					$order['id'],
					$cart_item['id']
				);

				$need_update = true;
			}

			if ((string)$cart_item_totals['comission_with_tax_discount'] != $cart_item_comission) {

				$cart_item_comission = $cart_item_totals['comission_with_tax_discount'];

				$this->log->save_order_log(
					'Changed commission'.$discount_text.' from <b>'.$cart_item['tax'].'</b> to <b>'.$cart_item_comission.'</b>',
					$order['id'],
					$cart_item['id']
				);

				$need_update = true;
			}


			if ($need_update) {

				$this->db->insert_update('cart_items', array (
					'id'		=> $cart_item['id'],
					'total'		=> $cart_item_total,
					'tax'		=> $cart_item_comission,
				));
			}
		}


		$need_update = false;

		$order_total = $order['total'];
		$order_comission = $order['tax'];

		if ((string)$cart_totals['total']['gross_with_tax_discount'] != $order_total) {

			$order_total = $cart_totals['total']['gross_with_tax_discount'];

			$this->log->save_order_log(
				'Changed order total'.$discount_text.' from <b>'.$order['total'].'</b> to <b>'.$order_total.'</b>',
				$order['id']
			);

			$need_update = true;
		}

		if ((string)$cart_totals['total']['comission_with_tax_discount'] != $order_comission) {

			$order_comission = $cart_totals['total']['comission_with_tax_discount'];

			$this->log->save_order_log(
				'Changed order commission'.$discount_text.' from <b>'.$order['tax'].'</b> to <b>'.$order_comission.'</b>',
				$order['id']
			);

			$need_update = true;
		}

		if ($need_update) {

			$this->db->insert_update('carts', array (
				'id'		=> $cart['id'],
				'total'		=> $order_total,
				'tax'		=> $order_comission,
			));

			$this->db->insert_update('orders', array (
				'id'		=> $order['id'],
				'total'		=> $order_total,
				'tax'		=> $order_comission,
			));

			$portal_commission = $this->calculate_portal_commission($order['id']);

			$this->db->insert_update('orders', array (
				'id'					=> $order['id'],
				'affiliate_commission'	=> $portal_commission,
			));
		}
	}

	public function send_sms_customer_new_reservation($phone, $conf, $voucher_link, $order_products, $is_rs = false, $is_update = false) {

		if(!$phone || !$conf || !$voucher_link) return false;

		send_sms($phone, ($is_update) ? 'sms_customer_upd_reservation' : 'sms_customer_new_reservation', [
			'conf' 			=> $conf,
			'link_voucher' 	=> $voucher_link,
		], $is_rs);

		if($order_products) {

			$this->db->query("
				SELECT 
					products.name,
					product_data.custom_sms_text
				
				FROM
					products
					JOIN product_data ON products.id = product_data.product_id
				WHERE 
					products.id IN (".$this->db->in($order_products).")");

			$db_res = $this->db->result;

			while ($product_data = $this->db->fetch($db_res)) {

				if(empty($product_data['name']) || empty($product_data['custom_sms_text'])) continue;

				send_sms($phone, 'sms_customer_new_reservation_add', [
					'trip_name' 	=> $product_data['name'],
					'append_text'	=> $product_data['custom_sms_text']
				], $is_rs);
			}
		}
	}

	// Отправляем юзеру письмо-подтверждени
	public function mail_user_order_confirmation($order_id) {

		if (!($order = $this->get($order_id))) return;

		// Если массив не пустой, то заказ сделан через портал.
		// Не вполне точное название is_portal нужно для унификации с шаблонами
		$is_portal = array();

		if ($order['affiliate_id']) {

			$this->add_page('partner');

			$is_portal = $this->portal->get(array('affiliate_id' => $order['affiliate_id']));

			$is_portal = array_merge($is_portal, $this->db->decode_data($is_portal['data']));
		}


		$order_data = array (
			'main_url'				=> ($is_portal ? $is_portal['domain'] : $this->config('website_url')),
			'site_logo'				=> ($is_portal ? $is_portal['voucher_logo'] : $this->config('website_url').'/img/email/logo.png'),
			'access_path'			=> ($is_portal ? $is_portal['domain'] : $this->config('website_url')).'/order/access/',
			'terms_url'				=> ($is_portal ? $is_portal['domain'] : $this->config('website_url')).'/terms.html',
			'header_email'			=> ($is_portal ? '' : '<br />customercare@tripshock.com'),
			'site_name'				=> ($is_portal ? $is_portal['company_name'] : 'TripShock!'),
			'alt_description'		=> $this->lang('mail_purchased_body'),
			'accesskey'				=> null,
			'promo_text'			=> '',
			'confirmation'			=> order_confirmation_number($order['format_id']),
			'total'					=> null,
			'tax'					=> null,
			'pay_delay'				=> '',
			'has_expedia_hotel'		=> false,
			'customer_name'			=> null,
			'customer_email'		=> null,
			'customer_phone'		=> null,
			'customer_address1'		=> null,
			'customer_address2'		=> null,
			'vouchers'				=> array(),

			'header_background_td'	=> ($is_portal ? '<td style="margin: 0; padding: 0;" align="left" valign="middle">' : '<td style="background: #00a651; margin: 0; padding: 20px;" align="left" bgcolor="#00a651" valign="middle">'),
			'header_text_color'		=> ($is_portal ? '#000' : '#fff'),
		);

		$this->add_page('promo');


		$this->add_page('cart_item');
		$cart_totals = $this->page_cart_item->get_cart_totals($order['cart_id']);

		$order_data['tax'] = round_price($cart_totals['total']['gross_with_tax_discount'] - $cart_totals['total']['gross_with_discount']);
		$order_data['total'] = $cart_totals['total']['gross_with_tax_discount'];


		if ($order['special_promo_id'] && $this->price->check_promo_status($order['special_promo_id'])) {

			$promo = $this->page_promo->get($order['special_promo_id']);

			$promo_text = 'Special Offer: Use code <b>'.$promo['code'].'</b>';

			if ($promo['date_stop']) {

				if (!is_numeric($promo['date_stop'])) $promo['date_stop'] = strtotime($promo['date_stop']);

				$promo_text .= ' within  '.round((($promo['date_stop'] - time()) / 86400)).' days for ';
			}

			if ($this->page_promo->unit[$promo['unit']] == '$') $promo_text .= money($promo['amount'], true).' off';
			else $promo_text .= $promo['amount'].'% off';

			if ($promo['min_price']) $promo_text .= ' your next order of '.money($promo['min_price'], true). ' or more';

			// Здесь и ниже напрямую работаем с "некрасивым кодом", т.к. у шаблона письма нет условий,
			// а у gmail и прочих нет поддержки классов в тегах
			$order_data['promo_text'] = '
			<table class="twelve" style="border-spacing: 0; border-collapse: collapse; vertical-align: middle; text-align: left; width: 700px; padding: 0;"><tr style="vertical-align: middle; text-align: left; padding: 0;" align="left"><td class="panel center" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: middle; text-align: center; color: #d2232a; font-family: \'Helvetica\', \'Arial\', sans-serif; font-weight: normal; line-height: 19px; font-size: 16px; background: #fffccd; margin: 0; padding: 20px; border: 1px solid #f9e2e3;" align="center" bgcolor="#fffccd" valign="middle">
				'.$promo_text.'
			</td>
			</tr></table>';

		}

		$has_avail_items = false;

		$this->db->query("
			SELECT
				orders.accesskey,
				orders.pay_delay_amount AS delay_amount,
				orders.pay_delay_received AS received_date,

				orders_users_data.*,

				cart_items.id AS cart_item_id,
				cart_items.state_name AS cart_item_state,

				IFNULL(states.code, orders_users_data.state_name) AS state,

				products.affiliate_id AS product_affiliate_id,

				users.data AS users_data
			FROM
				orders
				JOIN orders_users_data ON orders_users_data.order_id = orders.id
				JOIN cart_items ON cart_items.cart_id = orders.cart_id
				JOIN products ON products.id = cart_items.product_id
				JOIN users ON users.id = products.partner_id
				LEFT JOIN states ON states.id = orders_users_data.state_id
			WHERE
				orders.id = ?",
			$order_id
		);

		$db_res = $this->db->result;

		while ($order_part = $this->db->fetch($db_res)) {

			if (in_array($order_part['cart_item_state'], array('cancelled', 'deleted'))) continue;


			$has_avail_items = true;

			if (!$order_data['accesskey']) {

				$order_data['accesskey'] = $order_part['accesskey'];

				if ($order_part['first_name']) {

					if ($order_part['last_name']) {

						$order_data['customer_name'] = $order_part['first_name'].' '.$order_part['last_name'];
					}
					else {

						$order_data['customer_name'] = $order_part['first_name'];
					}
				}
				else {

					$order_data['customer_name'] = 'Customer';
				}

				if ($order_part['delay_amount'] && !$order_part['received_date']) {

					$payment_last_day = format_time($this->pay_delay_payment_last_day($order['id']), false);
					
					if($payment_last_day) {
					
						$order_data['pay_delay'] = '
						<tr style="vertical-align: middle; text-align: left; padding: 0;" align="left"><td class="six right-text-pad text-right" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: right; width: 50%; color: #222222; font-family: \'Helvetica\', \'Arial\', sans-serif; font-weight: normal; line-height: 19px; font-size: 16px; margin: 0; padding: 0px 10px 10px 0px;" align="right" valign="top">Balance Due:</td>
						<td class="six left-text-pad" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; width: 50%; color: #222222; font-family: \'Helvetica\', \'Arial\', sans-serif; font-weight: normal; line-height: 19px; font-size: 16px; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top"><b>$'.$order_part['delay_amount'].'</b><span class="cyr" style="color: #b2b2b2;"> USD</span></td>
					</tr>
						<tr style="vertical-align: middle; text-align: left; padding: 0;" align="left">
							<td class="twelve left-text-pad" style="word-break: break-word; -webkit-hyphens: auto; -moz-hyphens: auto; hyphens: auto; border-collapse: collapse !important; vertical-align: top; text-align: left; width: 50%; color: #ff0000; font-family: \'Helvetica\', \'Arial\', sans-serif; font-weight: normal; line-height: 19px; font-size: 16px; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top" colspan="2">Your final payment is due by '.$payment_last_day.'</td>
						</tr>';
					} else {
						
						$order_data['pay_delay'] = '';
					}
				}
				else {

					$order_data['pay_delay'] = '';
				}

				$order_data['customer_email'] = $order_part['email'];
				$order_data['customer_phone'] = $order_part['phone'];

				$order_data['customer_address1'] = $order_part['address'];
				$order_data['customer_address2'] = $order_part['city'].' '.$order_part['state'].' '.$order_part['zip'];

				if ($payments = $this->get_payment(null, $order_id)) {

					if (count($payments) > 1) {

						$order_data['multiple_cards'] = '<div style="text-align: center;"><b>Multiple Payments Used</b></div>';
						$order_data['payment_info_card'] = '';
						$order_data['payment_info_address'] = '';
					}
					else {

						$payment = array_shift($payments);

						$order_data['payment_type'] = $payment['type'];
						$order_data['card_number'] = $payment['number'];

						$order_data['multiple_cards'] = '';

						$order_data['payment_info_card'] = '
						<table style="border-spacing: 0; margin: 0 auto; padding: 0;">
							<tr style="padding: 0;" align="left">
								<td style="vertical-align: top; text-align: right; width: 50%; margin: 0; padding: 0px 10px 10px 0px;" align="right" valign="top">
									Payment Method:
								</td>
								<td style="vertical-align: top; width: 50%; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top">
									<b>'.($order['is_office'] ? 'N/A' : $order_data['payment_type']).'</b>
								</td>
							</tr>';
						if ($order_data['payment_type'] != 'paypal') {

							$order_data['payment_info_card'] .= '
							<tr style="padding: 0;" align="left">
								<td style="vertical-align: top; text-align: right; width: 50%; margin: 0; padding: 0px 10px 10px 0px;" align="right" valign="top">
									Card Number:
								</td>
								<td style="vertical-align: top; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top">
									<b>'.($order['is_office'] ? 'N/A' : '*********'.$order_data['card_number']).'</b>
								</td>
							</tr>';
						}
						$order_data['payment_info_card'] .= '
							<tr style="padding: 0;" align="left">
								<td style="vertical-align: top; text-align: right; width: 50%; margin: 0; padding: 0px 10px 10px 0px;" align="right" valign="top">
									Amount Charged:
								</td>
								<td style="vertical-align: top; width: 50%; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top">
									<b>';

						if ($order['is_office']) {

							$order_data['payment_info_card'] .= 'N/A';
						}
						else {

							if ($order_data['pay_delay'] == '') {

								$order_data['payment_info_card'] .= '$'.$order_data['total'];
							}
							else {

								$order_data['payment_info_card'] .= '$'.($order_data['total'] - $order_part['delay_amount']);
							}

							$order_data['payment_info_card'] .= '
								</b> <span style="color: #b2b2b2;"> USD</span>';
						}

						$order_data['payment_info_card'] .= '
								</td>
							</tr>
							'.$order_data['pay_delay'].'
						</table>
						';

						$order_data['payment_info_address'] = '
						<table style="border-spacing: 0; margin: 0 auto; padding: 0;">
							<tr style="padding: 0;" align="left">
								<td style="vertical-align: top; text-align: right; width: 50%; margin: 0; padding: 0px 10px 10px 0px;" align="right" valign="top">
									Billing Name:
								</td>
								<td style="vertical-align: top; width: 50%; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top">
									<b>'.$order_data['customer_name'].'</b>
								</td>
							</tr>
							<tr style="padding: 0;" align="left">
								<td style="vertical-align: top; text-align: right; width: 50%; margin: 0; padding: 0px 10px 10px 0px;" align="right" valign="top">
									Billing Address:
								</td>
								<td style="vertical-align: top; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top">';

						if ($order_data['payment_type'] != 'paypal') {

							$order_data['payment_info_address'] .= '<b>'.$order_data['customer_address1'].',</b>
								<br />
								'.$order_data['customer_address2'];
						}
						else{

							$order_data['payment_info_address'] .= 'N/A';
						}

						$order_data['payment_info_address'] .= '</td>
							</tr>
							<tr style="padding: 0;" align="left">
								<td style="vertical-align: top; text-align: right; width: 50%; margin: 0; padding: 0px 10px 10px 0px;" align="right" valign="top">
									Phone Number:
								</td>
								<td style="vertical-align: top; width: 50%; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top">
									<a href="" style="color: #48a7e4;">'.$order_data['customer_phone'].'</a>
								</td>
							</tr>
							<tr style="padding: 0;" align="left">
								<td style="vertical-align: top; text-align: right; width: 50%; margin: 0; padding: 0px 10px 10px 0px;" align="right" valign="top">
									Email Address:
								</td>
								<td style="vertical-align: top; width: 50%; margin: 0; padding: 0px 0px 10px 10px;" align="left" valign="top">
									<a href="" style="color: #48a7e4;">'.$order_data['customer_email'].'</a>
								</td>
							</tr>
						</table>
						';
					}
				}
			}

			if ($order_part['product_affiliate_id']) {

				$order_data['has_expedia_hotel'] = true;
				continue;
			}


			$data_amazon = $this->db->decode_data($order_part['users_data']);

			$return_path = '';

			if (!empty($data_amazon['amazon']['amazon_verified_email'])) {

				$return_path = $data_amazon['amazon']['amazon_verified_email'];
			}

			// Для каждой части заказа генерируем воучер в виде пдф чтобы прикрепить к письму
			$order_data['vouchers'][$order_part['cart_item_id']] = make_voucher_pdf($order_part['cart_item_id']);
		}

		if (!$has_avail_items) return;

		if ($order_data['has_expedia_hotel']) {

			$order_data['alt_description'] = $this->lang('mail_purchased_body_with_expedia_hotels');
		}

		$order_data['site_phone'] = $this->config('site_phone');

		$message = $this->mail->make_message('order_confirmation', $order_data);

		$this->mail->sendmail(
			$order_data['customer_email'],
			$message['subject'],
			$message['body'],
			'|'.$return_path,
			($is_portal ? $is_portal['company_name'] : ''),
			$order_data['vouchers'],
			(!$this->user->is_tech_support() ? explode(',', $this->config('order_confirmation_bcc_email')) : array())
		);

		// Удаляем локальные пдф после отправки
		foreach ($order_data['vouchers'] as $voucher_file) unlink($voucher_file);
	}


	// Ищет заказы по куче параметров
	// Возвращает их id
	public function get_order_list($options) {

		// Собираем условия запроса
		$select = array();
		$join = array();
		$where = array();
		$values = array();
		$order_by = array();

		$join_tables = array();
		$join_tables_query = array (
			'orders_states'			=> 'JOIN orders_states ON orders_states.id = orders.last_state_id',
			'orders_users_data'		=> 'JOIN orders_users_data ON orders_users_data.order_id = orders.id',
			'calendar'				=> 'LEFT JOIN calendar ON calendar.id = cart_items.calendar_id',
			'calendar2'				=> 'LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2',
			'products'				=> 'JOIN products ON products.id = cart_items.product_id',
			'cities'				=> 'JOIN cities ON products.city_id = cities.id',
			'payments'				=> 'JOIN payments ON payments.order_id = orders.id',
			'users'					=> 'JOIN users ON users.id = orders.order_by_user_id'
		);

		// Поиск конкретного заказа
		if (!empty($options['accesskey'])) {

			$where[] = 'orders.accesskey = ?';
			$values[] = $options['accesskey'];
		}

		if (empty($options['tab'])) $options['tab'] = 'all';

		// Условия из $options берём для всех табов, кроме arrival7days
		if ($options['tab'] != 'arrival7days') {

			if (
				!empty($options['count_tabs_orders'])
					&&
				$options['extended_mode']
					&&
				($options['tab'] != 'payment_due')
				) {

				$select[] = 'cart_items.id';
			}
			else {

				$select[] = 'orders.id';
			}

			// Табы payment_due и payment_received не используют временные рамки
			// для таба payment_due дополнительное условие
			if (!empty($options['tab']) && ($options['tab'] == 'payment_due')) {

				$where[] = 'orders.pay_delay_amount IS NOT NULL';
				$where[] = 'orders.pay_delay_received IS NULL';

				$join[] = 'JOIN calendar ON calendar.id = cart_items.calendar_id';
				$order_by[] = 'MIN(calendar.date) ASC';
			}
			// как и для payment_received
			else if (!empty($options['tab']) && ($options['tab'] == 'payment_received')) {

				$where[] = 'orders.pay_delay_amount IS NOT NULL';
				$where[] = 'orders.pay_delay_received IS NOT NULL';
			}
			else {

				// Ищем для списка заказов с affiliate_id
				if (!empty($options['affiliate_orders'])) {

					$where[] = "orders.affiliate_id <> ''";
				}
				// Ищем список заказов для конкретного affiliate_id
				if (!empty($options['affiliate_id'])) {

					$where[] = "orders.affiliate_id = ?";
					$values[] = $options['affiliate_id'];
				}

				// 0 - только заказы с сайта
				// 1 - только с партнёрки
				if (isset($options['made_from_rs'])) {

					$where[] = 'orders.made_from_rs = ?';
					$values[] = ($options['made_from_rs'] ? 1 : 0);
				}

				// Поиск конкретного заказа
				if (!empty($options['confirmation_number'])) {

					$where[] = 'orders.id = ?';
					$values[] = intval($options['confirmation_number']);
				}

				// Поиск заказов сделаных по телефну
				if (!empty($options['is_office'])) $where[] = 'orders.is_office = 1';

				// Product code
				// TODO: Возможно нужно удалить
				if (!empty($options['product_code'])) {

					$join_tables[] = 'calendar';
					$join_tables[] = 'products';

					$where[] = "products.product_code = ?";
					$values[] = $options['product_code'];
				}

				// Указан период для даты создания заказа
				if (!empty($options['made_from'])) {

					$where[] = "DATE(orders.made) >= ?";
					$values[] = format_date_for_db($options['made_from']);
				}
				if (!empty($options['made_to'])) {

					$where[] = "DATE(orders.made) <= ?";
					$values[] = format_date_for_db($options['made_to']);
				}

				// Указан период для даты прибытия
				if (!empty($options['arrival_from'])) {

					$join_tables[] = 'calendar';
					$join_tables[] = 'products';

					$where[] = "calendar.date >= ?";
					$values[] = format_date_for_db($options['arrival_from']);
				}
				if (!empty($options['arrival_to'])) {

					$join_tables[] = 'calendar';
					$join_tables[] = 'products';

					$where[] = "calendar.date <= ?";
					$values[] = format_date_for_db($options['arrival_to']);
				}

				// Ищем по дате отбытия(отели)
				// TODO: Возможно нужно удалить проверку даты
				if (!empty($options['check_out_from'])) {

					$join_tables[] = 'products';
					$join_tables[] = 'calendar2';

					$where[] = "products.product_type_id = ?";
					$values[] = product_type('hotel');

					$where[] = "calendar2.date >= ?";
					$values[] = format_date_for_db($options['check_out_from']);
				}
				if (!empty($options['check_out_to'])) {

					$join_tables[] = 'products';
					$join_tables[] = 'calendar2';

					$where[] = "products.product_type_id = ?";
					$values[] = product_type('hotel');

					$where[] = "calendar2.date <= ?";
					$values[] = format_date_for_db($options['check_out_to']);
				}

				if (!empty($options['cancelled_from']) && !empty($options['cancelled_to'])) {

					$cancelled_from_timestamp = format_date_for_db($options['cancelled_from']);
					$cancelled_to_timestamp = format_date_for_db(strtotime("+1 day", strtotime($options['cancelled_to'])) - 1);

					$where[] = "cart_items.state_time >= ?";
					$where[] = "cart_items.state_time <= ?";

					$values[] = $cancelled_from_timestamp;
					$values[] = $cancelled_to_timestamp;

					$options['tab'] = 'cancelled';
				}

				// Для таба made выводим только то, что было заказано более 2х часов назад
				if (!empty($options['tab']) && ($options['tab'] == 'made')) {

					$where[] = "orders.made < ?";
					$values[] = date("Y-m-d H:i:s", time() - 2*3600);
				}
			}

			// Поиск по имени покупателя
			if (!empty($options['username'])) {

				$join_tables[] = 'orders_users_data';

				$where[] = 'CONCAT(orders_users_data.first_name, " ", orders_users_data.last_name) LIKE ?';
				$values[] = '%'.$options['username'].'%';
			}

			// Поиск по номеру телефона покупателя
			if (!empty($options['phone_number'])) {

				$join_tables[] = 'orders_users_data';

				$where[] = 'orders_users_data.phone = ?';
				$values[] = $options['phone_number'];
			}
			
			// Мульти поиск в RS (по нескольким полям и маске)
			if (!empty($options['multi_search'])) {
				
				// если только цифры - проверям номер заказа или телефонный номер
				if (ctype_digit($options['multi_search'])) {
					
					$join_tables[] = 'orders_users_data';
					
					$where[] = "(orders.id = '".$options['multi_search']."' OR orders_users_data.phone LIKE '%".$options['multi_search']."%')";
				} else {
				
					// удаляем телефонные спец.символы
					$notPhoneChars = preg_replace("/[0-9]|\+|\(|\)|-/", '', $options['multi_search']);
					
					$join_tables[] = 'orders_users_data';
					
					if(!$notPhoneChars) {
						
						$cleanPhone = preg_replace("/\(|\)|-/", '', $options['multi_search']);
						
						// проверям только по телефонному номеру
						$where[] = "(orders_users_data.phone = '".$options['multi_search']."' OR orders_users_data.phone LIKE '%".$cleanPhone."%')";
					} else {
					
						if (strpos($notPhoneChars, '@') !== false) {
						   
							// проверям только по email
							$where[] = 'orders_users_data.email LIKE ?';
							$values[] = '%'.$options['multi_search'].'%';
						} else {
							
							// проверям только по имени
							$where[] = 'CONCAT(orders_users_data.first_name, " ", orders_users_data.last_name) LIKE ?';
							$values[] = '%'.$options['multi_search'].'%';
						}
					}
				}
			}

			// Поиск по Email покупателя
			if (!empty($options['email'])) {

				$join_tables[] = 'orders_users_data';

				$where[] = 'orders_users_data.email = ?';
				$values[] = $options['email'];
			}

			// Поиск по ID части заказа
			if (!empty($options['cart_item_id'])) {

				$where[] = 'cart_items.id = ?';
				$values[] = $options['cart_item_id'];
			}

			// Конкретное состояние заказа
			if (empty($options['single_order'])) {

				$states = array ();

				if (!empty($options['state'])) $options['states'] = array($options['state']);

				if (!empty($options['states']) && is_array($options['states'])) {

					foreach ($options['states'] as $state_name) {

						if (in_array($state_name, $this->states_name)) $states[] = $state_name;
					}
				}
				else {

					// Для разных табов берём заказы/части заказов с определённым состоянием
					foreach ($this->tabs[$options['tab']] as $state_id) $states[] = $this->states_name[$state_id];
				}

				if (is_api_request() && ($options['tab'] == 'all') && empty($options['states'])) {

					$states[] = 'made';

					foreach ($this->tabs['rs_only'] as $id) $states[] = $this->states_name[$id];
				}

				if ($states) {

					$where[] = "cart_items.state_name IN (".$this->db->in($states).")";
				}
			}

			// Основной город отеля или трипа
			if (!empty($options['city_id'])) {

				$join_tables[] = 'products';

				$where[] = 'products.city_id = ?';
				$values[] = $options['city_id'];
			}

			// Тип продукта
			if (!empty($options['product_type_id'])) {

				$join_tables[] = 'products';

				if ($options['product_type_id'] != product_type('hotel_expedia')) {

					$where[] = 'products.product_type_id = ?';
					$values[] = $options['product_type_id'];
				}
				else {

					$where[] = 'products.affiliate_id <> 0';
				}
			}

			// Поиск по партнёру
			if (!empty($options['partner_id'])) {

				$join_tables[] = 'products';

				$where[] = 'products.partner_id = ?';
				$values[] = $options['partner_id'];
			}

			// Id продукта
			if (!empty($options['product_id'])) {

				$join_tables[] = 'products';

				$where[] = 'products.id = ?';
				$values[] = $options['product_id'];
			}

			// Id нескольких продуктов
			if (!empty($options['products_ids'])) {

				$join_tables[] = 'products';

				$where[] = 'products.id IN ('.$this->db->in($options['products_ids']).')';
			}

			// Id нескольких продуктов
			if (!empty($options['inventory_id'])) {

				$where[] = "cart_items.data LIKE '%\"inventory\":{\"id\":\"".intval($options['inventory_id'])."\"%'";
			}

			// Менеджер, создавший заказ
			if (!empty($options['order_by_user_id'])) {

			if ($options['order_by_user_id'] == -1) {

					$join_tables[] = 'users';

					$where[] = 'users.group_id IN (1, 5, 6)';

				} else {

					$where[] = 'orders.order_by_user_id = ?';
					$values[] = $options['order_by_user_id'];
			}
			}

			// Поиск по активити
			if (!empty($options['activity_id'])) {

				$join_tables[] = 'products';

				$where[] = '? IN (products.activity_id, products.activity2_id, products.activity3_id)';
				$values[] = $options['activity_id'];
			}

			// Способ оплаты
			if (!empty($options['payment_method'])) {

				$where[] = 'orders.payment_method = ?';
				$values[] = $options['payment_method'];

				$check_card = array('pay_now','pay_online');

				// Это имеет смысл только при определённых методах оплаты (актуально для RS)
				if (in_array($options['payment_method'], $check_card) && !empty($options['cc_types'])) {

					$join_tables[] = 'payments';

					$where[] = 'payments.type IN ('.$this->db->in($options['cc_types']).')';
				}
			}

			// Было ли отправлено welcomeback-письмо
			if (isset($options['welcomeback_sent']) && !is_null($options['welcomeback_sent'])) {

				$where[] = 'cart_items.welcomeback_sent = ?';
				$values[] = $options['welcomeback_sent'];
			}
			// Было ли отправлено reminder-письмо
			if (isset($options['reminder_sent']) && !is_null($options['reminder_sent'])) {

				$where[] = 'cart_items.reminder_sent = ?';
				$values[] = $options['reminder_sent'];
			}
			
			// Источник заказа. Может быть "tripshock","online","office"
			if (isset($options['rs_agent'])) {
				
				if($options['rs_agent']=='tripshock' || $options['rs_agent']=='online') {
				
					$where[] = 'orders.rs_agent = ?';
					$values[] = $options['rs_agent'];
				} else {
				
					$where[] = "orders.rs_agent NOT IN ('tripshock', 'online')";
				}
			}
			
			// ORDER BY
			$sql_order = 'orders.made '.(!empty($options['order_direction']) ? 'ASC' : 'DESC');

			if (!empty($options['order_by'])) {

				$sql_orders = array (
					'order_date'					=> 'orders.made',
					'calendar_date'					=> 'calendar.date',
					'order_confirmation_number'		=> 'orders.id',
					'sub_gross_total'				=> 'orders.total',
					'sub_tax'						=> 'orders.comission',
					'sub_net_total'					=> 'orders.total - orders.comission',
				);
				$options['order_by'] = $sql_orders[$options['order_by']];

				$sql_order = $options['order_by'].' '.(!empty($options['order_dir']) ? $options['order_dir'] : 'ASC');
			}

			// Сортировка
			$order_by[] = $sql_order;
		}
		else {

			$join_tables[] = 'calendar';
			$join_tables[] = 'products';

			$select[] = 'cart_items.id';
			$select[] = 'orders.id';

			// Для партнёров учитываем только их заказы
			if ($this->user->is_partner()) {

				$where[] = 'products.partner_id = ?';
				$values[] = $this->user->data('id');
			}

			// Приезд в пределах недели
			$where[] = 'calendar.date BETWEEN ? AND ?';
			$values[] = format_date_for_db();
			$values[] = format_date_for_db(strtotime("+1 week"));

			// Для подсчёта кол-ва нам нужны только не подтверждённые
			if (!empty($options['count_tabs_orders'])) {

				$where[] = "cart_items.state_name IN ('paid', 'unconfirmed')";
			}
			// Для самого таба выводим всё кроме отменённых/удалённых/не оплаченных
			else {

				$where[] = "cart_items.state_name NOT IN ('', 'made', 'cancelled', 'deleted')";
			}

			// Для продуктов, использующих календарь
			$where[] = 'products.use_calendar = 1';

			$order_by[] = 'calendar.date';
		}

		// Регион, по идее, нужно учесть при любом варианте
		if (!empty($options['region_id'])) {

			$join_tables[] = 'products';
			$join_tables[] = 'cities';

			$where[] = 'cities.region_id = ?';
			$values[] = $options['region_id'];
		}

		// Для не-апи запросов поулчаем заказы оформленные только через трипшок
		if (!is_api_request()) {

			$where[] = 'orders.made_from_rs = 0';
		}

		// Удаляем повторы из join_tables
		$join_tables = array_unique($join_tables);
		// По собранным join_tables составляем итоговый $join массив
		foreach ($join_tables as $table_name) $join[] = $join_tables_query[$table_name];

		// Если не указан блокирующий флаг - делаем запрос с пагинацией
		if (empty($options['no_pagination'])) {

			if (empty($options['page_num'])) $options['page_num'] = 1;

			// Кол-во заказов на страницу
			if (!empty($options['orders_per_page'])) $orders_per_page = $options['orders_per_page'];
			else $orders_per_page = $this->orders_per_page;

			// Limit для пагинации
			if (isset($options['limit_from'])) $limit_from = $options['limit_from'];
			else $limit_from = ($options['page_num'] - 1)*$orders_per_page;
		}


		// Основной запрос на получение списка заказов с учётом пагинации
		$orders = array();

		$this->db->query("
			SELECT SQL_CALC_FOUND_ROWS
				".implode(", \r\n", $select)."
			FROM
				orders
				JOIN cart_items ON cart_items.cart_id = orders.cart_id
				".implode("\r\n", $join)."
			WHERE
				".implode("\r\n AND ", $where).
			(empty($options['count_items_states']) ? "
			GROUP BY
				orders.id" : "")."
			ORDER BY
				".implode(", ", $order_by)."
			".(empty($options['no_pagination']) ? "LIMIT ".$limit_from.", ".$orders_per_page : ""),
			$values
		);

		while ($order = $this->db->fetch()) $orders[$order['id']] = $order['id'];

		if (empty($options['no_pagination'])) {

			$this->db->query("
				SELECT FOUND_ROWS() AS total_orders"
			);

			$row = $this->db->fetch();
			$orders_count = $row['total_orders'];

			$pagination = array(
				'orders_count'	=> $orders_count,
				'from'			=> $limit_from + 1,
				'to'			=> ((($limit_from + $orders_per_page) > $orders_count) ? $orders_count : ($limit_from + $orders_per_page)),
				'pages'			=> ceil($orders_count/$orders_per_page),
			);
		}
		else {

			$pagination = array(
				'orders_count'	=> count($orders),
				'from'			=> 0,
				'to'			=> count($orders),
				'pages'			=> 1,
			);
		}

		return array (
			'pagination'	=> $pagination,
			'orders_list'	=> $orders,
		);
	}

	// По параметрам и списку id-заказов возвращает всю нужную инфу
	public function get_orders($order_ids, $options = array()) {

		$orders = array();

		if (empty($order_ids)) return $orders;

		$where = array();
		$values = array();
		$order_by = array();

		// Берём данные только среди определённых заказов
		$where[] = 'orders.id IN ('.$this->db->in($order_ids).')';

		if (empty($options['tab'])) $options['tab'] = 'all';

		if ($options['tab'] != 'arrival7days') {

			// 0 - только заказы с сайта
			// 1 - только с партнёрки
			if (isset($options['made_from_rs'])) {

				$where[] = 'orders.made_from_rs = ?';
				$values[] = ($options['made_from_rs'] ? 1 : 0);
			}

			// Период прибытия
			// В режиме get_day_orders и get_days_orders (это RS) нужно сохранить общие суммы заказа, но показать только те
			// cart_items, которые соответствуют arrival_date. Поэтому исключаем их не на уровне SQL, а в коде.
			if (!is_mode('get_day_orders', 'get_days_orders')) {

				if (!empty($options['arrival_from'])) {

					$where[] = "products.use_calendar = 1";

					$where[] = "calendar.date >= ?";
					$values[] = date("Y-m-d", strtotime($options['arrival_from']));
				}
				if (!empty($options['arrival_to'])) {

					$where[] = "products.use_calendar = 1";

					$where[] = "calendar.date <= ?";
					$values[] = date("Y-m-d", strtotime($options['arrival_to']));
				}
			}

			// Период отъезда(только для отелей)
			// TODO: Возможно нужно удалить проверку даты
			if (!empty($options['check_out_from'])) {

				$where[] = "products.product_type_id = ?";
				$values[] = product_type('hotel');

				$where[] = "calendar2.date >= ?";
				$values[] = date("Y-m-d", strtotime($options['check_out_from']));
			}
			if (!empty($options['check_out_to'])) {

				$where[] = "products.product_type_id = ?";
				$values[] = product_type('hotel');

				$where[] = "calendar2.date <= ?";
				$values[] = date("Y-m-d", strtotime($options['check_out_to']));
			}

			if (!empty($options['cancelled_from']) && !empty($options['cancelled_to'])) {

				$cancelled_from_timestamp = format_date_for_db($options['cancelled_from']);
				$cancelled_to_timestamp = format_date_for_db(strtotime("+1 day", strtotime($options['cancelled_to'])) - 1);

				$where[] = "cart_items.state_time >= ?";
				$where[] = "cart_items.state_time <= ?";

				$values[] = $cancelled_from_timestamp;
				$values[] = $cancelled_to_timestamp;

				$options['tab'] = 'cancelled';
			}

			// Активити трипа
			if (!empty($options['activity_id'])) {

				$where[] = "? IN (products.activity_id, products.activity2_id, products.activity3_id)";
				$values[] = $options['activity_id'];
			}

			// Основной город отеля или трипа
			if (!empty($options['city_id'])) {

				$where[] = 'products.city_id = ?';
				$values[] = $options['city_id'];
			}

			// Регион
			if (!empty($options['region_id'])) {

				$where[] = 'cities.region_id = ?';
				$values[] = $options['region_id'];
			}

			// Тип продукта
			if (!empty($options['product_type_id'])) {

				if ($options['product_type_id'] != product_type('hotel_expedia')) {

					$where[] = 'products.product_type_id = ?';
					$values[] = $options['product_type_id'];
				}
				else {

					$where[] = 'products.affiliate_id <> 0';
				}
			}

			// Поиск по партнёру
			if (!empty($options['partner_id'])) {

				$where[] = 'products.partner_id = ?';
				$values[] = $options['partner_id'];
			}

			// Конкретный продукт
			if (!empty($options['product_id'])) {

				$where[] = "products.id = ?";
				$values[] = $options['product_id'];
			}

			// Id нескольких продуктов
			if (!empty($options['products_ids'])) {

				$where[] = 'products.id IN ('.$this->db->in($options['products_ids']).')';
			}

			// Id нескольких продуктов
			if (!empty($options['inventory_id'])) {

				$where[] = "cart_items.data LIKE '%\"inventory\":{\"id\":\"".intval($options['inventory_id'])."\"%'";
			}

			// Способ оплаты
			if (!empty($options['payment_method'])) {

				$where[] = 'orders.payment_method = ?';
				$values[] = $options['payment_method'];
			}

			// Статусы учитываем только для расширенного режима
			if (empty($options['single_order']) && empty($options['affiliate_orders'])) {

				$states = array();

				if (!empty($options['state'])) $options['states'] = array($options['state']);

				if (!empty($options['states']) && is_array($options['states'])) {

					foreach ($options['states'] as $state_name) {

						if (in_array($state_name, $this->states_name)) $states[] = $state_name;
					}
				}
				else if (!empty($options['tab'])) {

					foreach ($this->tabs[$options['tab']] as $id) $states[] = $this->states_name[$id];
				}

				if (is_api_request() && ($options['tab'] == 'all') && empty($options['states'])) {

					$states[] = 'made';

					foreach ($this->tabs['rs_only'] as $id) $states[] = $this->states_name[$id];
				}

				if ($states) {

					$where[] = "cart_items.state_name IN (".$this->db->in($states).")";
				}
			}

			$order_by[] = 'order_id DESC';
		}
		else {

			// Приезд в пределах недели
			$where[] = 'calendar.date BETWEEN ? AND ?';
			$values[] = date("Y-m-d");
			$values[] = date("Y-m-d", strtotime("+1 week"));

			// Для партнёров учитываем только их заказы
			if ($this->user->is_partner()) {

				$where[] = 'products.partner_id = ?';
				$values[] = $this->user->data('id');
			}

			// Не отменены/удалены, оплачены
			$where[] = "cart_items.state_name NOT IN ('', 'made', 'cancelled', 'deleted')";

			// Для продуктов, использующих календарь
			$where[] = 'products.use_calendar = 1';

			$order_by[] = 'calendar.date';
		}

		// Получаем список
		$this->db->query("
			SELECT
				orders.id AS order_id,
				orders.made_from_rs AS order_made_from_rs,
				orders.payment_method AS order_payment_method,
				orders.rs_agent AS order_rs_agent,
				UNIX_TIMESTAMP(orders.made) AS order_made,
				orders.accesskey AS order_accesskey,
				orders.total AS order_total,
				orders.tax AS order_comission,
				(orders.total - orders.tax) AS order_net,
				orders.pay_delay_received AS order_pay_delay_received,
				orders.pay_delay_amount AS order_pay_delay_amount,
				orders.was_refunded AS order_was_refunded,
				orders.refund_amount AS order_refund_amount,
				orders.gift_certificate AS order_gift_certificate,
				orders.is_office,
				orders.comment_admin,
				orders.cart_id AS cart_id,
				orders.affiliate_id AS order_affiliate_id,
				orders.affiliate_commission AS order_affiliate_commission,

				carts.data AS cart_data,
				carts.count AS items_count,

				orders_states.state AS order_state,
				UNIX_TIMESTAMP(orders_states.time) AS order_state_time,

				orders.user_id AS customer_id,
				orders_users_data.last_name AS customer_last_name,
				orders_users_data.first_name AS customer_first_name,
				orders_users_data.email AS customer_email,
				".(!empty($options['affiliate_orders']) ? "users.notify AS customer_notify," : "")."
				orders_users_data.phone AS customer_phone,
				orders_users_data.phone2 AS customer_phone2,
				orders_users_data.ip AS customer_ip,
				orders_users_data.address AS customer_address,
				orders_users_data.city AS customer_city,
				orders_users_data.zip AS customer_zip,
				orders_users_data.state_id AS customer_state_id,
				orders_users_data.state_name AS customer_state_name,
				orders_users_data.country_id AS customer_country_id,

				calendar.date AS arrival_date,
				calendar2.date AS arrival_date2,

				products.id AS product_id,
				products.affiliate_id AS product_affiliate_id,
				products.name AS product_name,
				products.product_type_id,
				products.partner_id AS partner_id,
				products.need_tickets_guests AS product_need_tickets_guests,

				cart_items.id AS cart_item_id,
				cart_items.is_fh_item AS cart_is_fh_item,
				cart_items.data AS data,
				cart_items.total AS cart_item_total,
				cart_items.tax AS cart_item_comission,
				(cart_items.total - cart_items.tax) AS cart_item_net,
				cart_items.confirmation AS cart_item_confirmation,
				cart_items.on_request AS cart_item_on_request,
				cart_items.state_name AS cart_item_state,
				cart_items.gift_code AS gift_code,
				cart_items.welcomeback_sent AS cart_item_welcomeback_sent,

				cities.name as city_name,
				vendor.rs_order_prefix as order_prefix,
				our_users.group_id,
				our_users.name AS booked_by_name,
				user_groups.name AS booked_by_group
			FROM
				cart_items
				JOIN carts ON carts.id = cart_items.cart_id
				JOIN products ON products.id = cart_items.product_id
				JOIN orders ON orders.cart_id = cart_items.cart_id
				JOIN orders_states ON orders_states.id = orders.last_state_id
				JOIN orders_users_data ON orders_users_data.order_id = orders.id
				".(!empty($options['affiliate_orders']) ? "JOIN users ON users.id = orders.user_id" : "")."
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2
				LEFT JOIN cities ON products.city_id = cities.id
				LEFT JOIN users AS vendor ON vendor.id = products.partner_id
				LEFT JOIN users AS our_users ON our_users.id = orders.order_by_user_id
				LEFT JOIN user_groups ON user_groups.id = our_users.group_id
			WHERE
				".implode("\r\n AND ", $where)."
			ORDER BY
				".implode(", ", $order_by),
			$values
		);
		$db_res = $this->db->result;

		// Для таба payment_due у нас будет дополнительный массив с днями последней оплаты, чтобы корректно сделать сорировку по ним
		$payment_due_last_days = array();
		// Так же для установки флага has_various_partners составляем список партнёров для каждого заказа
		$orders_partners = array();
		// Для установки флага has_different_days составляем список дат прибытия для каждого заказа
		$arrivals = array();

		while ($row = $this->db->fetch($db_res)) {

			// Сохраняем новый заказ, если общих данных о нём ещё нет
			// Все суммы округляем по 2ой знак после запятой, т.к. формат хранения float
			if (empty($orders[$row['order_id']])) {

				$this->db->query("
					SELECT
						group_order.id
					FROM
						group_order
					WHERE
						group_order.order_id LIKE '%".$row['order_id']."%'"
				);
				$row['group_order_id'] = (($group_order = $this->db->fetch()) ? $group_order['id'] : null);

				$cart_data = $this->db->decode_data($row['cart_data']);

				$custom_total = null;

				if ($row['order_made_from_rs'] && isset($cart_data['custom_total'])) {

					$custom_total = $cart_data['custom_total'];
				}

				$order_discount = array();
				if (!empty($cart_data['discount'])) $order_discount = $cart_data['discount'];

				$format_id_prefix = ($row['order_made_from_rs'] ? $row['order_prefix'].'-' : 'TRS-');

				$order = array (
					'id'							=> $row['order_id'],
					'format_id'						=> $format_id_prefix.$row['order_id'],
					'group_order_id'				=> $row['group_order_id'],
					'cart_id'						=> $row['cart_id'],
					'made_from_rs'					=> $row['order_made_from_rs'],
					'payment_method'				=> $row['order_payment_method'],
					'rs_agent'						=> $row['order_rs_agent'],
					'made'							=> $row['order_made'],
					'accesskey'						=> $row['order_accesskey'],
					'custom_total'					=> $custom_total,
					'total'							=> 0,
					'net'							=> 0,
					'comission'						=> 0,
					'_total'						=> 0,
					'_net'							=> 0,
					'_comission'					=> 0,
					'pay_delay_received'			=> $row['order_pay_delay_received'],
					'pay_delay_amount'				=> $row['order_pay_delay_amount'],
					'was_refunded'					=> $row['order_was_refunded'],
					'refund_amount'					=> $row['order_refund_amount'],
					'gift_certificate'				=> $row['order_gift_certificate'],
					'affiliate_id'					=> $row['order_affiliate_id'],
					'affiliate_commission'			=> $row['order_affiliate_commission'],

					'is_office'						=> $row['is_office'],
					'group_id'						=> $row['group_id'],
					'booked_by_name'				=> $row['booked_by_name'],
					'booked_by_group'				=> $row['booked_by_group'],
					'state'							=> $row['order_state'],
					'state_time'					=> $row['order_state_time'],

					'cusomer_id'					=> $row['customer_id'],
					'customer_last_name'			=> $row['customer_last_name'],
					'customer_first_name'			=> $row['customer_first_name'],
					'customer_email'				=> $row['customer_email'],
					'customer_notify'				=> (isset($row['customer_notify']) ? $row['customer_notify'] : null),
					'customer_phone'				=> $row['customer_phone'],
					'customer_phone2'				=> $row['customer_phone2'],
					'customer_ip'					=> $row['customer_ip'],
					'customer_address'				=> $row['customer_address'],
					'customer_city'					=> $row['customer_city'],
					'customer_zip'					=> $row['customer_zip'],
					'customer_state_id'				=> $row['customer_state_id'],
					'customer_state_name'			=> $row['customer_state_name'],
					'customer_country_id'			=> $row['customer_country_id'],

					'comment_admin'					=> $row['comment_admin'],

					'discount'						=> $order_discount,

					'confirmation_numbers'			=> array(),
					'expedia_confirmations'			=> array(),
					'expedia_itinerary'				=> array(),
					'has_hotel_items'				=> false,
					'has_various_partners'			=> false,
					'has_different_days'			=> false,
					'items_count'					=> $row['items_count'],
					'cities_names'					=> array(),
					'cart_items'					=> array(),
				);

				// Если есть сумма по промокоду - обнуляем её и заполняем отдельно по полученным из бд cart_items
				if (!empty($order['discount']['total'])) {

					$order['discount']['total'] = 0;
					$order['discount']['total_with_tax'] = 0;
				}

				// Для отменённого заказа скидка = 0, но информацию о ней оставляем
				if ($order['state'] == 'cancelled') {

					$order['discount']['total'] = 0;
					$order['discount']['total_with_tax'] = 0;
				}

				// Для таба payment_due сразу получаем последний день оплаты
				if ($options['tab'] == 'payment_due') {

					$payment_last_day = $this->pay_delay_payment_last_day($order['id']);

					$payment_due_last_days[$order['id']] = $payment_last_day;
					$order['payment_last_day'] = $payment_last_day;
				}

				$orders[$order['id']] = $order;
			}
			
			if( $row['arrival_date'] ) $arrivals[$row['order_id']][] = $row['arrival_date'];
			
			if (
				!is_mode('get_day_orders', 'get_days_orders')
					||
				(
					is_mode('get_day_orders')
						&&
					(strtotime($row['arrival_date']) == strtotime($options['arrival_from']))
				)
					||
				(
					is_mode('get_days_orders')
						&&
					(strtotime($row['arrival_date']) >= strtotime($options['arrival_from']))
						&&
					(strtotime($row['arrival_date']) <= strtotime($options['arrival_to']))
				)
			) {

				// Добавляем id партнёра заказанного продукта к списку
				$orders_partners[$row['order_id']][$row['partner_id']] = $row['partner_id'];


				$book_info = $this->db->decode_data($row['data']);

				$cancellation_data = array();
				if (!empty($book_info['cancellation_data'])) $cancellation_data = $book_info['cancellation_data'];

				// Всякое для отелей экспедии
				$expedia_confirmations = array();
				$expedia_itinerary = null;
				if ($row['product_affiliate_id'] && !empty($book_info['expedia_data']['confirmationNumbers'])) {

					// confirmation numbers складываем отдельно
					$expedia_confirmations = $book_info['expedia_data']['confirmationNumbers'];
					if (!is_array($expedia_confirmations)) $expedia_confirmations = array($expedia_confirmations);

					$orders[$row['order_id']]['expedia_confirmations'] = array_merge($orders[$row['order_id']]['expedia_confirmations'], $expedia_confirmations);

					// itinerary_id
					$expedia_itinerary = $book_info['expedia_data']['itineraryId'];
					$orders[$row['order_id']]['expedia_itinerary'][] = $expedia_itinerary;
				}


				$discount = array();
				if (!empty($orders[$row['order_id']]['discount']['cart_items'][$row['cart_item_id']])) {

					$discount = $orders[$row['order_id']]['discount']['cart_items'][$row['cart_item_id']];
				}


				// И сохраняем очередной элемент заказа
				$cart_item = array(
					'id' => $row['cart_item_id'],
					'is_fh_item' => $row['cart_is_fh_item'],
					'is_affiliate' => ($row['product_affiliate_id'] ? true : false),
					'arrival_date' => $row['arrival_date'],
					'arrival_date2' => $row['arrival_date2'],
					'state' => $row['cart_item_state'],
					'product_id' => $row['product_id'],
					'product_name' => $row['product_name'],
					'product_type_id' => $row['product_type_id'],
					'product_need_tickets_guests' => $row['product_need_tickets_guests'],

					'book_info' => $book_info,

					'total' => $row['cart_item_total'],
					'net' => $row['cart_item_net'],
					'comission' => $row['cart_item_comission'],
					// Исходные суммы, до изменений(ex: штрафы за отмену)
					'_total' => $row['cart_item_total'],
					'_net' => $row['cart_item_net'],
					'_comission' => $row['cart_item_comission'],

					'gift_code' => $row['gift_code'],
					'confirmation_number' => $row['cart_item_confirmation'],
					'expedia_confirmations' => $expedia_confirmations,
					'expedia_itinerary' => $expedia_itinerary,
					'on_request' => $row['cart_item_on_request'],
					'cart_item_welcomeback_sent' => $row['cart_item_welcomeback_sent'],

					'site_fee' => ($cancellation_data ? $cancellation_data['site_fee'] : 0),
					'partner_fee' => ($cancellation_data ? $cancellation_data['partner_fee'] : 0),
					'refund_amount' => ($cancellation_data ? $cancellation_data['refund_amount'] : 0),
					'cancel_reason' => ($cancellation_data ? $cancellation_data['cancel_reason'] : ''),

					'discount' => ($discount ? $discount['discount'] : 0),
					'discount_with_tax' => ($discount ? $discount['discount_with_tax'] : 0),
					'city_name' => $row['city_name'],
				);

				$orders[$row['order_id']]['cities_names'][] = $row['city_name'];

				$orders[$row['order_id']]['_total'] += $cart_item['_total'];
				$orders[$row['order_id']]['_net'] += $cart_item['_net'];
				$orders[$row['order_id']]['_comission'] += $cart_item['_comission'];


				$orders[$row['order_id']]['total'] += $cart_item['total'];
				$orders[$row['order_id']]['net'] += $cart_item['net'];
				$orders[$row['order_id']]['comission'] += $cart_item['comission'];


				// Для отменённой части заказа скидка = 0, но информацию о ней оставляем
				if (($row['cart_item_state'] == 'cancelled') && ($cart_item['discount'] > 0)) {

					$orders[$row['order_id']]['discount']['cart_items'][$row['cart_item_id']]['total'] = 0;
					$orders[$row['order_id']]['discount']['cart_items'][$row['cart_item_id']]['total_with_tax'] = 0;
				} // Для не отменённой просто пересчитываем сумму промокода за весь заказ
				else if ($cart_item['discount'] > 0) {

					$orders[$row['order_id']]['discount']['total'] += $cart_item['discount'];
					$orders[$row['order_id']]['discount']['total_with_tax'] += $cart_item['discount_with_tax'];
				}


				// Флаг того, что в заказе есть отель
				if (
					(product_type($row['product_type_id']) == 'hotel')
					&&
					!$row['product_affiliate_id']
				) {

					$orders[$row['order_id']]['has_hotel_items'] = true;
				}

				// Добавляем confirmation_number(если есть) к списку у заказа
				if ($row['cart_item_confirmation']) {

					$orders[$row['order_id']]['confirmation_numbers'][] = $row['cart_item_confirmation'];
				}


				$orders[$row['order_id']]['cart_items'][$cart_item['id']] = $cart_item;

				// Заполняем поля платёжной карты заказа (если она одна)
				// Если карт несколько - то тип будет "Visa", а номер - "Multiple payment"
				// А партнёрке нужно, чтобы это всегда был массив с определёнными ключами
				if ($payments = $this->get_payment(null, $row['order_id'])) {

					if (is_api_request()) {

						$counter = 0;

						foreach ($payments as $payment) {

							$orders[$row['order_id']]['transaction_info'][$counter]['card_type'] = $payment['type'];
							$orders[$row['order_id']]['transaction_info'][$counter]['card_number'] = $payment['number'];
							$orders[$row['order_id']]['transaction_info'][$counter]['transaction_id'] = $payment['transaction_id'];

							$counter++;
						}
					} else {

						if (count($payments) > 1) {

							$orders[$row['order_id']]['payment_type'] = 'Visa';
							$orders[$row['order_id']]['payment_number'] = 'Multiple payment';
							$orders[$row['order_id']]['payment_transaction_id'] = '';
						} else {

							$payment = array_shift($payments);

							$orders[$row['order_id']]['payment_type'] = $payment['type'];
							$orders[$row['order_id']]['payment_number'] = $payment['number'];
							$orders[$row['order_id']]['payment_transaction_id'] = $payment['transaction_id'];
						}
					}
				} else {

					if (is_api_request()) {

						$orders[$row['order_id']]['transaction_info'][0]['card_type'] = null;
						$orders[$row['order_id']]['transaction_info'][0]['card_number'] = null;
						$orders[$row['order_id']]['transaction_info'][0]['transaction_id'] = null;
					} else {

						$orders[$row['order_id']]['payment_type'] = null;
						$orders[$row['order_id']]['payment_number'] = null;
						$orders[$row['order_id']]['payment_transaction_id'] = null;
					}
				}

				if (is_mode('get_day_orders', 'get_days_orders')) {

					if (!isset($orders[$row['order_id']]['cart_items_cumulative'])) {

						$orders[$row['order_id']]['cart_items_cumulative'] = array(
							'count' => 0,
							'all_with_inventories' => true,
						);
					}

					if ($row['cart_item_state'] != 'cancelled') {

						$orders[$row['order_id']]['cart_items_cumulative']['count'] += 1;

						if ($orders[$row['order_id']]['cart_items_cumulative']['all_with_inventories']) {

							if (empty($book_info['inventory'])) $orders[$row['order_id']]['cart_items_cumulative']['all_with_inventories'] = false;
						}
					}
				}
			}
			// Если get_day_orders и очередной cart_item не подходит по arrival_date, то нужны только общие суммы заказа
			else {

				$orders[$row['order_id']]['total'] += $row['cart_item_total'];
				$orders[$row['order_id']]['net'] += $row['cart_item_net'];
				$orders[$row['order_id']]['comission'] += $row['cart_item_comission'];
			}
		}

		// Для всех заказов с больше чем 1 партнёром проставляем соответствующий флаг
		foreach ($orders_partners as $order_id => $partners) {

			if (count($partners) > 1) $orders[$order_id]['has_various_partners'] = true;
		}

		// А так же пересчитываем статус заказа для партнёра
		if ($this->user->is_partner() || !empty($options['revise_orders_state'])) {

			foreach ($orders as $id => $order) {

				$new_state = null;

				foreach ($order['cart_items'] as $cart_item) {

					// Отменённые заказы не влияют на общий статус
					if ($cart_item['state'] != 'cancelled') {

						// Первый элемент, берём его статус за отправной
						if (empty($new_state)) $new_state = $cart_item['state'];
						// Если хотя бы у одного элемента статус не такой же, как у первого найденного
						// то ставим общий статус paid
						else if ($cart_item['state'] != $new_state) $new_state = 'paid';

						if ($cart_item['state'] == 'card_hold') {

							$new_state = 'card_hold';
							break;
						}
					}
				}

				// Пустой будет в случае, если все части заказа имеют статус cancelled
				if ($new_state === null) $new_state = 'cancelled';

				$orders[$id]['state'] = $new_state;
			}
		}
		
		// проверка наличия в заказе частей с разной датой прибытия (только RS)
		if (is_api_request()) {

			foreach ($orders as $id => $order) {

				if (isset($arrivals[$id])) {

					$arrivals[$id] = array_unique($arrivals[$id]);
				
					if (count($arrivals[$id]) > 1) $orders[$id]['has_different_days'] = true;
				}
			}
		}
		
		// Сортировка для payment_due
		if ($options['tab'] == 'payment_due') {

			// Сортируем по последнему дню оплаты
			asort($payment_due_last_days);

			// И соответственно составляем новый массив
			$orders_new = array();
			foreach ($payment_due_last_days as $order_id => $payment_last_day) {

				$orders_new[$order_id] = $orders[$order_id];
			}

			$orders = $orders_new;
		}

		return $orders;
	}

	// Продукты определённого заказа
	public function get_order_products($order_id, $partner_id = null) {

		$order_products = array();
		$query_values = array();

		$query_values[] = $order_id;
		if (!empty($partner_id)) $query_values[] = $partner_id;

		if (!empty($order_id)) {

			$this->db->query("
				SELECT
					cart_items.product_id
				FROM
					cart_items
					JOIN orders ON orders.cart_id = cart_items.cart_id
					".(!empty($partner_id) ? "JOIN products ON products.id = cart_items.product_id" : "")."
				WHERE
					orders.id = ?
					".(!empty($partner_id) ? "AND products.partner_id = ?" : ""),
				$query_values
			);
			$db_res = $this->db->result;

			while ($cart_item = $this->db->fetch($db_res)) {

				if (!in_array($cart_item['product_id'], $order_products)) $order_products[] = $cart_item['product_id'];
			}
		}

		return $order_products;
	}

	public function get_applied_promo_code($order_id) {

		$promo = null;

		$this->db->query("
			SELECT
				carts.*
			FROM
				carts
				JOIN orders ON orders.cart_id = carts.id
			WHERE
				orders.id = ?",
			$order_id
		);
		$db_res = $this->db->result;

		if ($cart = $this->db->fetch($db_res)) {

			$cart = $this->db->decode_data($cart['data']);

			if ($cart && isset($cart['discount'])) {

				$promo = $cart['discount']['promo'];
			}
		}

		return $promo;
	}



	// Выставляем статус confirmed всем частям заказа, у партнёра которых
	// выставлен флаг autoconfirm_orders
	public function autoconfirm_order_items($order_id) {

		$this->db->query("
			SELECT
				cart_items.id
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				JOIN products ON products.id = cart_items.product_id
				JOIN users ON users.id = products.partner_id
			WHERE
				orders.id = ?
					AND
				(
					users.autoconfirm_orders = 1
						OR
					(
						orders.made_from_rs = 0
							AND
						products.autoconfirm_orders = 1
					)
				)",
			$order_id
		);

		$db_res = $this->db->result;

		while ($cart_item = $this->db->fetch($db_res)) {

			$this->cart_item_set_state($cart_item['id'], 'confirmed');
		}
	}

	// Всё, что нужно сделать после того, как заказ был успешно оплачен
	public function complete_after_payment_actions($complete_data) {

		// При оплате не через PP сюда попадаем почти сразу после поиска ошибок, а если это PP
		// то как раз до этого момента юзер может вернуться на трипшок и что-то изменить
		unset($_SESSION['cart_id']);
		unset($_SESSION['order_step1_data']);

		if (!($this->fh_book_order($complete_data['order_id'], $complete_data))) {

			$_SESSION['order_rejected_from_fh'] = 1;
		}

		$_SESSION['google_conversion_total'] = $complete_data['order_total'];

		$partner_id = ($this->user->is_partner() ? $this->user->data('id') : 0);

		$this->set_state($complete_data['order_id'], 'paid', null, $partner_id);

		$this->price->order_save_promo($complete_data['order_id']);

		alter_log('order_data_log', "Promo saved");


		// Создаём промокод для заказа, если включена опция в конфиге
		if (!$this->config('after_order_promo_is_disabled')) {

			$this->add_page('promo');
			$this->page_promo->generate_promo_code($complete_data['order_id']);
		}

		alter_log('order_data_log', "Promo generated", true, false);

		// Запоминаем, хочет ли юзер получать новости.
		$this->db->insert_update('users', array(
			'id'		=> $complete_data['user_id'],
			'notify'	=> ($complete_data['user_notify'] ? true : false),
		));

		if ($complete_data['user_notify'] && !is_portal()) {

			$this->add_page('notification');
			$this->page_notification->add_email_to_mailchimp($complete_data['user_email'], $complete_data['user_name']);
		}

		alter_log('order_data_log', "Notifications ended", true, false);

		// Конфирмим части заказов, у партнёра которых стоит соответствующий флаг
		$this->autoconfirm_order_items($complete_data['order_id']);

		alter_log('order_data_log', "---~~~--- END AFTER ACTIONS ---~~~---", true, false);
	}


	// Получаем и выводим данные о заказе и его составе
	public function show_order_info($order_id, $just_made_order = false) {

		$this->add_page('cart');
		$this->add_page('photo');
		$this->add_page('promo');

		$order = $this->get($order_id);

		// Общая информаия о заказе
		$order['made_format'] = format_time($order['made']);

		$applied_promo = $this->get_applied_promo_code($order['id']);
		$order['applied_promo'] = (is_null($applied_promo) ? '' : $applied_promo['code']);

		$this->tpl->batch('order', $order);

		if ($order['special_promo_id'] && $this->price->check_promo_status($order['special_promo_id'])) {

			$promo = $this->page_promo->get($order['special_promo_id']);

			$promo_text = '-';
			if ($this->page_promo->unit[$promo['unit']] == '$') $promo_text .= money($promo['amount'], true);
			else $promo_text .= $promo['amount'].'%';

			if ($promo['min_price']) $promo_text .= ', for items from '.money($promo['min_price'], true);

			if ($promo['date_stop']) $promo_text .= ', valid to  '.format_time($promo['date_stop'], false);

			$this->tpl->loop('order_promo', array (
				'code'			=> $promo['code'],
				'text'			=> $promo_text,
			));
		}

		// Данные для Shopper Approved с отзывом после заказа
		if ($just_made_order) {
		
			$this->add_page('country');
			$this->add_page('state');
			$this->add_page('cart_item');
			
			$country = $this->page_country->get($order['customer_country_id']);
			$country = $country['name'];
			
			if (!$order['customer_state_name']) {
			
				$state = $this->page_state->get($order['customer_state_id']);
				$state = $state['code'];
			}
			else {

				$state = $order['customer_state_name'];
			}
			
			$customer_name = $order['customer_first_name'].' '.$order['customer_last_name'];
			
			$this->tpl('sa_order_id', $order['id']);
			$this->tpl->batch('sa_customer', array (
				'email'		=> $order['customer_email'],
				'name'		=> $customer_name,
				'state'		=> $state,
				'country'	=> $country,
			));

			$cart_totals = $this->page_cart_item->get_cart_totals($order['cart_id']);

			$zaius_purchace_data = array(
				'type' => 'order',
				'data' => array(
					'action'	=> 'purchase',
					'email'		=> $order['customer_email'],
					'order'		=> array(
						'order_id'	=> $order_id,
						'total'			=> $cart_totals['total']['gross'],
						'discount'		=> $cart_totals['total']['gross'] - $cart_totals['total']['gross_with_discount'],
						'subtotal'		=> $cart_totals['total']['gross_with_discount'],
						'commission'	=> $cart_totals['total']['comission'],
						'booking_fee'	=> $cart_totals['total']['booking_fee_amount'],
						'tax'			=> $cart_totals['total']['comission_with_tax'],
						'coupon_code'	=> $promo['code'],
						'items'			=> array()
					)
				)
			);
		}

		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.data,
				cart_items.total,
				cart_items.state_name,
				cart_items.gift_code,

				calendar.date AS arrival_date,
				calendar2.date AS checkout_date,

				orders.id AS order_id,
				orders.user_id AS order_user_id,

				products.id AS product_id,
				products.partner_id AS product_partner_id,
				products.product_type_id,
				products.name AS product_name,
				products.description AS product_description,
				products.affiliate_id AS product_affiliate_id,

				states.code AS product_state_code
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2
				JOIN products ON products.id = cart_items.product_id
				JOIN cities ON cities.id = products.city_id
				JOIN states ON states.id = cities.state_id
			WHERE
				orders.id = ?
			ORDER BY
				cart_items.total DESC",
			$order_id
		);
		$db_res = $this->db->result;

		// Отпраляем в шаблон количество продуктов, чтобы знать, нужно ли выводить селектор в социальном блоке
		$this->tpl('cart_items_count', $this->db->count());

		// Для отслеживания дублирующихся продуктов
		$products_id = array ();
		$share_products_count = 0;

		$index = 0;

		while ($cart_item = $this->db->fetch(0, $db_res)) {

			$book_info = $this->db->decode_data($cart_item['data']);

			$past_arrival_date = false;

			if (product_type($cart_item['product_type_id']) == 'trip') {

				$booked_name = format_tickets_string($book_info['tickets']);

				if (
					$cart_item['arrival_date']
						&&
					(strtotime('+1 day', strtotime($cart_item['arrival_date'])) < time())
					) {

					$past_arrival_date = true;
				}
			}
			else if (product_type($cart_item['product_type_id']) == 'hotel') {

				$booked_name = format_rooms_string($book_info);

				if (strtotime($cart_item['checkout_date']) < time()) $past_arrival_date = true;
			}

			$can_post_comment = $this->product->get_can($cart_item['product_id'], 'post_comment', $cart_item['id']);

			$product_link = $this->product->get_main_link($cart_item['product_id']);

			$product = $this->product->get($cart_item['product_id']);

			// Ссылка для социального блока на случай, если это iframe-портал
			$iframe_link = '';
			if ($portal = $this->config('portal')) {

				if ($portal['iframe']) {

					$iframe_link = $portal['iframe']['parent'].'#'.substr($product_link, 1, (strlen($product_link) - 2));
				}
			}

			$product_image = $this->page_photo->get_photo_link($cart_item['product_id'], 0, 't2');

			$product_type = product_type($cart_item['product_type_id']);

			$partner_title = '';
			$activity_name = '';
			$category_name = '';
			if ($just_made_order) {

				$this->add_page('activity');
				$this->add_page('partner');
				$this->add_page('city');
				$this->add_page('cart_item');

				$activity = $this->page_activity->get($product['activity_id']);
				$activity_name = $activity['name'];
				$city = $this->page_city->get($product['city_id']);
				$city_name = $city['name'];

				$category_name = (!empty($city_name) ? $city_name : 'Undefined').
					' - '.(!empty($activity_name) ? $activity_name : 'Undefined');

				$partner = $this->page_partner->get($product['partner_id']);
				$partner_title = $partner['title'];

				$cart_item_total = $this->page_cart_item->get_booked_trip_totals($book_info);

				array_push($zaius_purchace_data['data']['order']['items'], array(
							'product_id'	=> $cart_item['product_id'],
							'price'			=> $cart_item['total'],
							'quantity'		=> 1,
							'arrival_date'	=> strtotime($cart_item['arrival_date']),
							'discount'		=> $cart_item_total['total']['gross'] - $cart_item_total['total']['gross_with_discount'],
							'subtotal'		=> $cart_item_total['total']['gross_with_discount'],
				));
			}

			$this->tpl->loop('cart_item', array (
					'cart_item_id'			=> $cart_item['id'],
					'product_id'			=> $cart_item['product_id'],
					'product_type'			=> $product_type,
					'partner_id'			=> $cart_item['product_partner_id'],
					'product_text'			=> cut($cart_item['product_description'], 100),
					'product_link'			=> $product_link,
					'iframe_link'			=> $iframe_link,
					'product_image'			=> $product_image,
					'product_is_duplicate'	=> (in_array($cart_item['product_id'], $products_id) ? 1 : 0),

					'is_affiliate'			=> ($cart_item['product_affiliate_id'] ? true : false),
					'is_cancelled'			=> ($cart_item['state_name'] == 'cancelled'),
					'is_confirmed'			=> ($cart_item['state_name'] == 'confirmed'),
					'past_arrival_date'		=> $past_arrival_date,
					'can_post_comment'		=> $can_post_comment,

					'booked'				=> 1,
					'price'					=> $cart_item['total'],

					'new_row'				=> (($index++) % 3 == 0),

					'code'					=> md5($this->config('vendor_item_confirm_salt').$cart_item['product_partner_id'].$cart_item['id']),
				),
				array(
					'product_name'			=> ($product['pseudo_name'] ? $product['pseudo_name'] : $cart_item['product_name']),
					'product_partner'		=> $partner_title,
					'product_activity'		=> $category_name,

					'name'					=> $booked_name,
			));

			// Отели(не экспедии) отправляем в дополнительный цикл для учёта статистикой HeBS Sales
			if (
				($cart_item['product_type_id'] == product_type('hotel'))
					&&
				!$cart_item['product_affiliate_id']
					&&
				$just_made_order && !$this->user->is_tech_support()
				) {

				$this->tpl->loop('cart_item_hotel', array (
					'order_id'			=> $cart_item['order_id'],
					'confirmation'		=> order_confirmation_number($cart_item['order_id']),
					'nights'			=> $book_info['nights'],
					'check_in'			=> $book_info['date_from'],
					'user_id'			=> $cart_item['order_user_id'],
					'booked'			=> $book_info['booked'],
					'hotel_id'			=> $cart_item['product_id'],
					'state'				=> $cart_item['product_state_code'],
					'total'				=> $cart_item['total'],
				));
			}

			if (!in_array($cart_item['product_id'], $products_id)) $share_products_count++;
			$products_id [$cart_item['product_id']] = $cart_item['product_id'];
		}

		if ($just_made_order && !is_local()) post_remote_data_curl_zaius(json_encode($zaius_purchace_data));

		// Данные для вывода блока шаринга
		$this->tpl_raw('product_id_list', json_encode($products_id));
		$this->tpl('share_products_count', $share_products_count);
	}



	// Fareharbor

	// Получаем новые события лога изменений FH
	public function fh_get_change_log($new_only = true) {

		$change_log = array();

		$this->db->query("
			SELECT
				fh_items_log.*,

				orders.id AS order_id
			FROM
				fh_items_log
				JOIN cart_items ON cart_items.id = fh_items_log.cart_item_id
				JOIN orders ON orders.cart_id = cart_items.cart_id
			".($new_only ? "WHERE fh_items_log.status = 1" : "")."
			ORDER BY
				fh_items_log.id DESC");
		$db_res = $this->db->result;

		while ($log_row = $this->db->fetch($db_res)) {

			$change_log[$log_row['id']] = $log_row;
		}

		return $change_log;
	}

	// Формируем основную информацию из FH-структуры заказ
	public function fh_format_external_order_data($order_data) {

		$short_data = array();

		if (isset($order_data['booking'])) {

			$order_data = $order_data['booking'];
		}

		// Дата/время
		$short_data['date_time'] = $order_data['availability']['start_at'];

		// Общая цена заказа
		$short_data['amount_paid'] = $order_data['amount_paid'];

		// Внешний статус (ничего технически общего со статусом на трипшоке, однако семантика та же)
		$short_data['status'] = $order_data['status'];

		// Билеты
		$short_data['tickets'] = array();

		foreach ($order_data['customers'] as $ticket) {

			$ticket = $ticket['customer_type_rate'];

			if (!isset($short_data['tickets'][$ticket['customer_prototype']['pk']])) {

				$short_data['tickets'][$ticket['customer_prototype']['pk']] = array (
					'name'		=> $ticket['customer_prototype']['display_name'],
					'booked'	=> 1,
					'pk'		=> $ticket['pk'],
				);
			}
			else {

				$short_data['tickets'][$ticket['customer_prototype']['pk']]['booked'] += 1;
			}
		}

		// Дополнительные опции
		$short_data['options'] = array();

		foreach ($order_data['custom_field_values'] as $option) {

			if (!empty($option['value'])) {

				if ($option['custom_field']['type'] == 'extended-option') {

					foreach ($option['custom_field']['extended_options'] as $value) {

						if ($value['pk'] == $option['value']) $option['value'] = $value['name'];
					}
				}

				$short_data['options'][$option['custom_field']['pk']] = array (
					'name'	=> $option['custom_field']['name'],
					'value'	=> $option['value'],
				);
			}
		}


		return $short_data;
	}

	// Добавляем основную информацию из FH-структуры заказа себе в спец. таблицу
	// для того, чтобы потом вести лог изменений
	public function fh_add_basic_data_for_log($cart_item_id, $order_data) {

		$fh_data = $order_data['booking']['company']['shortname'].'|'.
			$order_data['booking']['availability']['item']['pk'].'||'.
			$order_data['booking']['uuid'];

		$fh_item_data = array (
			'cart_item_id'		=> $cart_item_id,
			'control_date'		=> format_date_for_db($order_data['booking']['availability']['start_at']),
			'last_check_date'	=> format_date_for_db(),
			'fh_data'			=> $fh_data,
			'order_data'		=> $this->db->encode_data($this->fh_format_external_order_data($order_data)),
		);

		$this->db->insert_update_no_id('fh_items_data', $fh_item_data, array('cart_item_id'));
	}

	// Если в заказе есть FH-части, то заказываем их и отправляем флаг (успех/провал)
	public function fh_book_order($order_id, $order_data) {

		$result = true;

		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.is_fh_item,
				cart_items.data,
				
				calendar.date
			FROM
				cart_items
				JOIN calendar ON calendar.id = cart_items.calendar_id
				JOIN orders ON orders.cart_id = cart_items.cart_id
			WHERE
				orders.id = ?
					AND
				cart_items.is_fh_item = 1",
			$order_id
		);
		$db_res = $this->db->result;

		// Данные для связывания с fareharbor
		require_once($this->config('path').'/inc/fareharbor.php');
		$fareharbor = new fareharbor();

		while ($fh_cart_item = $this->db->fetch($db_res)) {

			$attempt_failed = false;
			$fail_message = '';

			$fh_cart_item['data'] = $this->db->decode_data($fh_cart_item['data']);

			$fh_cart_item['order_data'] = array (
				'user_name'			=> $order_data['user_name'],
				'user_last_name'	=> $order_data['user_last_name'],
				'user_phone'		=> $order_data['user_phone'],
				'user_email'		=> $order_data['user_email'],
			);

			$order_result = $fareharbor->fh_request('order', $fh_cart_item);

			if ($order_result && isset($order_result['error'])) {

				$fail_message = "Error message: ".$order_result['error'];

				$attempt_failed = true;
			}
			else if (!$order_result) {

				$fail_message = "FH server is not responding";

				$attempt_failed = true;
			}
			else {

				unset($fh_cart_item['order_data']);

				$fh_cart_item['data']['fh_data']['fh_order'] = array (
					'pk'				=> $order_result['booking']['pk'],
					'uuid'				=> $order_result['booking']['uuid'],
					'confirmation_url'	=> urlencode($order_result['booking']['confirmation_url']),
				);

				$this->db->insert_update('cart_items', array (
					'id'			=> $fh_cart_item['id'],
					'data'			=> $this->db->encode_data($fh_cart_item['data']),
					'sold_reduced'	=> true,
				));

				$this->fh_add_basic_data_for_log($fh_cart_item['id'], $order_result);

				//$fh_items_already_sent[] = $fh_cart_item['id'];
			}

			if ($attempt_failed) {

				alter_log('fh_orders_errors', "Item ".$fh_cart_item['id']." has been rejected from the FH\r\n".
					"Order ID is ".$order_id."\r\n".
					$fail_message."\r\n"
				);

				/*if ($fh_items_already_sent) {

					foreach ($fh_items_already_sent as $fh_item_id) {

						$this->fh_delete_item($fh_item_id, 'STEP2');
					}
				}*/

				$this->add_page('trip');
				$this->page_trip->invalidate_fh_cache($fh_cart_item['data']['product']['id']);

				$result = false;
			}
		}

		return $result;
	}

	// Если в переданной корзине есть FH-части, то валидируем их и отправляем результаты: флаг (успех/провал) и сообщение
	public function fh_validate_order($cart_id) {

		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.is_fh_item,
				cart_items.data,
				
				calendar.date
			FROM
				cart_items
				JOIN calendar ON calendar.id = cart_items.calendar_id
			WHERE
				cart_items.cart_id = ?
					AND
				cart_items.is_fh_item = 1",
			$cart_id
		);
		$db_res = $this->db->result;

		// Данные для связывания с fareharbor
		require_once($this->config('path').'/inc/fareharbor.php');
		$fareharbor = new fareharbor();


		while ($fh_cart_item = $this->db->fetch($db_res)) {

			$attempt_failed = false;
			$fail_message = '';

			$book_info = $this->db->decode_data($fh_cart_item['data']);

			$fh_tickets = array();

			foreach ($book_info['tickets'] as $ticket_id => $ticket) {

				$fh_tickets[$ticket_id] = $ticket['booked'];
			}


			$fh_result = $fareharbor->fh_request('validate', array (
				'fh_id'					=> $book_info['fh_data']['fh_schedule']['id'],
				'fh_trip_data'			=> $book_info['fh_data']['fh_trip_data'],
				'tickets'				=> $fh_tickets,
				'additional_options'	=> $book_info['additional_options'],
				'fh_tickets_config'		=> $book_info['fh_data']['fh_tickets_config'],
				'comment_guest'			=> $book_info['comments']['guest'],
			));


			if (is_null($fh_result) || !isset($fh_result['is_bookable']) || $fh_result['is_bookable'] == false) {

				$attempt_failed = true;

				if (is_null($fh_result)) {

					$fail_message = 'FH is not responding. Please try later';
				}
				else if (!isset($fh_result['is_bookable'])) {

					$fail_message = 'Unsupported validation error type. For more information please refer to the tech support';
					batch_to_alter_log('fh_unsupported_validation_error', $fh_result, 1, 'Response');
				}
				else {

					$fail_message = '[Fareharbor]: '.$fh_result['error'];
				}
			}

			if ($attempt_failed) {

				$failed_trip_data = '"'.$book_info['product']['name'].'" on '.format_date_for_datepicker($fh_cart_item['date']).
					' at '.$book_info['fh_data']['fh_schedule']['time'].' '.
					($this->user->is_admin() ? 'Technical message: "'.$fail_message.'"' : '');

				$this->add_page('trip');
				$this->page_trip->invalidate_fh_cache($book_info['product']['id']);

				if ($this->user->is_admin()) {

					return array (
						'success' => false,
						'error' => 'The Fareharbor activity has been rejected. '.$failed_trip_data
					);
				}
				else {

					return array (
						'success' => false,
						'error' => 'We cannot confirm the activity '.$failed_trip_data.'because the availability has recently changed.'.
							' Please remove the item from your shopping cart and select a different day or schedule time.' .
							' You may also call '.$this->config('site_phone').' for agent assistance.'
					);
				}
			}
		}

		return array('success' => true, 'error' => '');
	}

	// Удаляем уже отправленный на сторону FH заказ
	public function fh_delete_item($cart_item_id, $log_header = 'CANCEL') {

		$this->add_page('cart_item');

		if ($cart_item = $this->page_cart_item->get($cart_item_id)) {

			$book_info = $this->db->decode_data($cart_item['data']);

			// Данные для связывания с fareharbor
			require_once($this->config('path') . '/inc/fareharbor.php');
			$fareharbor = new fareharbor();

			$order_delete = $fareharbor->fh_request('delete', array (
				'trip_data'	=> $book_info['fh_data']['fh_trip_data'],
				'uuid'		=> $book_info['fh_data']['fh_order']['uuid'],
			));

			if ($order_delete && isset($order_delete['error'])) {

				alter_log('fh_orders_deletion_fails', $log_header."\r\nItem ".$cart_item['id']."\r\n".
					"Error message: ".$order_delete['error']."\r\n"
				);
			}
			else {

				unset($book_info['fh_data']['fh_order']['confirmation_url']);
			}


			$this->db->insert_update('cart_items', array (
				'id' => $cart_item['id'],
				'data' => $this->db->encode_data($book_info),
				'sold_reduced' => false,
			));

			$this->db->query("
				DELETE FROM
					fh_items_data
				WHERE
					fh_items_data.cart_item_id = ?",
				$cart_item['id']
			);

			return true;
		}

		return false;
	}

	// Отправляем некую часть заказа в HF как заказ
	// Это нужно если сразу часть не заказалась или при восстановлении ранее отменённой
	private function fh_rebook_cart_item($cart_item_id) {

		if (empty($cart_item_id)) return false;

		// Данные для связывания с fareharbor
		require_once($this->config('path').'/inc/fareharbor.php');
		$fareharbor = new fareharbor();

		$this->add_page('cart_item');

		$cart_item = $this->page_cart_item->get($cart_item_id);
		$book_info = $this->db->decode_data($cart_item['data']);

		$this->db->query("
			SELECT
				order_id,
				first_name,
				last_name,
				phone,
				email
			FROM
				orders_users_data
				JOIN orders ON orders.id = orders_users_data.order_id
				JOIN cart_items ON cart_items.cart_id = orders.cart_id
			WHERE
				cart_items.id = ?",
			$cart_item['id']
		);
		$db_res = $this->db->result;

		$order_data = $this->db->fetch($db_res);


		$cart_item['order_id'] = $order_data['order_id'];

		$order_result = $fareharbor->fh_request('order', array (
			'id'			=> $cart_item['id'],
			'data'			=> $book_info,
			'order_data'	=> array (
				'user_name'			=> $order_data['first_name'],
				'user_last_name'	=> $order_data['last_name'],
				'user_phone'		=> $order_data['phone'],
				'user_email'		=> $order_data['email'],
			)
		));

		if ($order_result && isset($order_result['error'])) {

			alter_log('fh_orders_errors', "Item ".$cart_item['id']." has been rejected from the FH during the restoration process\r\n".
				"Order ID is ".$cart_item['order_id']."\r\n".
				"Error message: ".$order_result['error']."\r\n"
			);

			return false;
		}
		else {

			$book_info['fh_data']['fh_order'] = array (
				'pk'				=> $order_result['booking']['pk'],
				'uuid'				=> $order_result['booking']['uuid'],
				'confirmation_url'	=> urlencode($order_result['booking']['confirmation_url']),
			);

			$this->db->insert_update('cart_items', array (
				'id'			=> $cart_item['id'],
				'data'			=> $this->db->encode_data($book_info),
				'sold_reduced'	=> true,
			));

			$this->fh_add_basic_data_for_log($cart_item['id'], $order_result);

			// Добавляем отметку в лог внешних изменений чтобы не было новых изменений сразу после отметки об удалении
			$this->db->insert_update('fh_items_log', array (
				'cart_item_id'	=> $cart_item['id'],
				'txt'			=> 'Item has been rebooked from TS',
				'time'			=> format_date_for_db(null, true),
			));

			// Перестраиваем кеш трипа
			$this->add_page('trip');
			$this->page_trip->invalidate_fh_cache($book_info['product']['id'], true);

			return true;
		}
	}


	// TODO Вывести методы работы со Stripe в инструмент если понадобится доступ к ним из др. классов
	public function stripe_call($method, $params) {

		$result = array (
			'success'	=> true,
			'data'		=> array(),
			'error'		=> '',
		);


		require_once($this->config('path').'/inc/stripe-php/init.php');
		// dev
		//\Stripe\Stripe::setApiKey("sk_test_s8lijTzS8COhWi1hdqSRl3Gx");
		// live
		\Stripe\Stripe::setApiKey("sk_live_EVVzStaZzVhcggV2OmnnCkHu");


		$error_branch = null;
		$error_body = array();

		try {

			switch ($method) {

				case 'get_token':

					$result['data'] = \Stripe\Token::retrieve($params);

					break;

				case 'customer':

					$result['data'] = \Stripe\Customer::create($params);

					break;

				case 'charge':

					$result['data'] = \Stripe\Charge::create($params);

					break;

				case 'refund':

					$result['data'] = \Stripe\Refund::create($params);

					break;
			}

		} catch(\Stripe\Error\Card $e) {

			// Since it's a decline, \Stripe\Error\Card will be caught
			$error_branch = 'Card';
			$body = $e->getJsonBody();
			$error_body = $body['error'];

		} catch (\Stripe\Error\RateLimit $e) {

			// Too many requests made to the API too quickly
			$error_branch = 'RateLimit';
			$body = $e->getJsonBody();
			$error_body = $body['error'];

		} catch (\Stripe\Error\InvalidRequest $e) {

			// Invalid parameters were supplied to Stripe's API
			$error_branch = 'InvalidRequest';
			$body = $e->getJsonBody();
			$error_body = $body['error'];

		} catch (\Stripe\Error\Authentication $e) {

			// Authentication with Stripe's API failed
			// (maybe you changed API keys recently)
			$error_branch = 'Authentication';
			$body = $e->getJsonBody();
			$error_body = $body['error'];

		} catch (\Stripe\Error\ApiConnection $e) {

			// Network communication with Stripe failed
			$error_branch = 'ApiConnection';
			$body = $e->getJsonBody();
			$error_body = $body['error'];

		} catch (\Stripe\Error\Base $e) {

			// Display a very generic error to the user, and maybe send
			// yourself an email
			$error_branch = 'Base';
			$body = $e->getJsonBody();
			$error_body = $body['error'];

		} catch (Exception $e) {

			// Something else happened, completely unrelated to Stripe
			$error_branch = 'Unknown';
			$error_body = array('message' => 'Unknown error');

		}

		if (!is_null($error_branch)) {

			// TODO Помимо основного кода логировать здесь подробные данные фейла со стороны Stripe
			$result['success'] = false;

			if (!empty($error_body['message'])) $result['error'] = $error_body['message'];
			else $result['error'] = '"'.$error_branch.'" type error';
		}

		return $result;
	}


	public function __construct() {

		parent::__construct();

		$this->id = $this->url->get_int('id');

		if (in_array($this->config('mode'), array('step1', 'step2')) && !empty($_SESSION['cart_id'])) {

			$this->add_page('cart');
			if (!($cart = $this->page_cart->get(intval($_SESSION['cart_id'])))) {

				$this->tpl->message_flag('cart_expired');

				$this->url->go('/cart/list/');
			}

			if (!$cart['count']) $this->url->go('/cart/list');

			$this->cart_id = $cart['id'];
		}

		$this->max_cards = 0;
	}


	// -------------------------------------
	//				Admin zone
	// -------------------------------------

	// Список заказов
	public function mode_list() {

		if (!$this->user->is_permitted('order_view') && !$this->user->is_partner()) $this->url->go(404);

		// Меняем статус у элемента корзины
		if ($mode_ext = $this->url->get_str('mode_ext')) {

			$order_id = $this->url->get_int('id');
			$cart_item_id = $this->url->get_int('item_id');

			if ($order_id && (($mode_ext == 'edit') || ($mode_ext == 'view'))) {

				$this->url->go('/index.php?page=order&mode='.$mode_ext.'&id='.$order_id);
			}
			else if ($order_id && $cart_item_id) {

				$confirmation_number = $this->url->get_str('confirmation_number');

				$this->cart_item_set_state($cart_item_id, $mode_ext, $confirmation_number);

				$this->url->go('referer');
			}
		}

		new request_order_search(array (
			'name'		=> 'order_search',
			'method'	=> 'get',
			'is_direct'	=> true,
			'vars'		=> array (
				'page,<hidden>',
				'mode,<hidden>',
				'confirmation_number,<text>',
				'made_from,<text>',
				'made_to,<text>',
				'arrival_from,<text>',
				'arrival_to,<text>',
				'cancelled_from,<text>',
				'cancelled_to,<text>',
				'username,<text>',
				'product_type_id,<select>',
				'partner_id,<select>',
				'product_id,<select>',
				'city_id,<select>',
				'order_by_user_id,<select>',
				'activity_id,<select>',
				'is_office,<checkbox>',
				'extended_mode,<checkbox>',
				'orders_per_page,<select>',
				'tab,<hidden>',
				'page_num,<hidden>',
				'phone_number,<text>',
				'email,<text>',
				'cart_item_id,<text>',
				'region_id,<select>',
			),
		));

		$this->tpl('is_hotel_partner', $this->user->is_hotel_partner());
	}

	// Обрабатывает аякс запросы на смену таба
	public function mode_tab_load() {

		if (!$this->user->is_permitted('order_view') && !$this->user->is_partner()) $this->url->go(404);

		$this->tpl('is_ajax', true);

		// Вся работа идёт в реквест классе
		new request_order_search(array(
			'name'		=> 'order_tab_load',
			'method'	=> 'post',
			'vars'		=> array (
				'confirmation_number',
				'made_from',
				'made_to',
				'arrival_from',
				'arrival_to',
				'cancelled_from',
				'cancelled_to',
				'username',
				'product_type_id',
				'partner_id',
				'product_id',
				'city_id',
				'order_by_user_id',
				'activity_id',
				'is_office',
				'extended_mode',
				'orders_per_page',
				'page_num',
				'tab',
				'count_tabs_orders',
				'order_by',
				'order_dir',
				'phone_number',
				'email',
				'cart_item_id',
				'region_id',
			),
		));
	}

	// Та же суть, что и у tab_load, но для списка заказов с affiliate_id
	public function mode_affiliate_tab_load()
	{

		if (!$this->user->is_permitted('order_view') && !$this->user->is_partner()) $this->url->go(404);

		$this->tpl('is_ajax', true);

		// Вся работа идёт в реквест классе
		new request_order_search(array(
			'name'		=> 'order_tab_load',
			'method'	=> 'post',
			'vars'		=> array (
				'made_from',
				'made_to',
				'arrival_from',
				'arrival_to',
				'affiliate_id',
				'orders_per_page',
				'page_num',
				'tab',
				'order_by',
				'order_dir',
			),
		));
	}


	// Отмена части заказа, учитываем возможный штраф
	public function mode_cancel() {

		if (!$this->user->is_permitted('order_edit')) $this->url->go(404);

		// Всё в реквест классе
		new request_order_cancel_fee(array (
			'name'			=> 'order_cancel_fee',
			'method'		=> 'post',
			'is_direct'		=> true,
			'vars'			=> array (
				'cart_item_id,is_required=true',
				'site_fee',
				'partner_fee',
				'refund_amount',
				'cancel_reason',
			),
		));
	}

	// Редактирование заказа
	public function mode_edit() {

		if(!$this->id || !($order = $this->get($this->id))) $this->url->go(404);

		if($user_access_key = $this->url->get_str('user')) {

			$this->tpl('hide_voucher_column', true);

			$this->db->query("
				SELECT
					products.partner_id
				FROM
					products
					JOIN cart_items ON cart_items.product_id = products.id
				WHERE
					cart_items.cart_id = ?",
				$order['cart_id']
			);

			if($partner = $this->db->fetch()) {

				if (!strcmp($user_access_key, md5($order['accesskey'].$partner['partner_id']))) {

					$_SESSION['no_mobile'] = true;

					if(!$this->user->is_partner()) {

						$this->user->login($partner['partner_id']);

						// Даем доступ партнеру только к старнице /index.php?page=order&mode=edit
						$_SESSION['allow_pages'] = [
							'order' => [
								'edit'
							]
						];

						$this->url->go();
					}
				}
			}

		}

		if (!($this->user->is_permitted('order_view') || $this->user->is_partner())) $this->url->go(404);

		if ($this->user->is_partner() && (in_array($order['order_state'], array('deleted')))) {

			$this->url->go(404);
		}

		$order['made'] = format_time($order['made']);
		$order['confirmation'] = order_confirmation_number($order['format_id']);

		$this->tpl->batch('order', $order);

		// Не редактируется ли заказ другим пользователем
		if ($currently_editing_user = $this->check_user_edit_presence()) {

			$this->tpl('currently_editing_user', $currently_editing_user);
		}

		$this->add_page('cart_item');
		$this->add_page('country');
		$this->add_page('state');

		$order_edit = new request_order_edit(array (
			'name'		=> 'order_edit',
			'method'	=> 'post',
			'errors_tpl_loop_name' => 'order_edit_errors',
			'vars'		=> array (
				'customer_first_name,<text>',
				'customer_last_name,<text>',
				'customer_email,<text>',
				'customer_phone,<text>',
				'customer_phone2,<text>',
				'customer_address,<text>',
				'customer_city,<text>',
				'customer_zip,<text>',
				'customer_state_id',
				'customer_state_name,<text>',
				'customer_country_id,<select>,is_strict=false',

				'comment_admin,<textarea>',

				'resend_confirm,<checkbox>',
				'resend_confirm_text,<checkbox>',

				'pay_delay_amount,<text>',
				'pay_delay_status,<select>,is_strict=false',

				'order[]',
				'trip_items[]',
				'hotel_items[]',
			),
		));

		$countries_states = make_state_select($order_edit, 'customer_state_id', 'customer_country_id');
		$this->tpl_raw('countries_states', json_encode($countries_states));


		// Содержимое заказа
		$this->add_page('cart_item');
		$this->add_page('hotel');
		$this->add_page('trip');

		$cart_totals = $this->page_cart_item->get_cart_totals($order['cart_id']);

		$made_by_user = '-';
		if ($order['order_by_user_id']) {

			$this->add_page('user');
			$user = $this->page_user->get($order['order_by_user_id']);
			$made_by_user = $user['name'].' ('.$user['group_name'].')';
		}
		// Основные данные и суммы заказа
		$this->tpl->batch('order', array (
			'id'						=> $order['id'],
			'cart_id'					=> $order['cart_id'],
			'made_by_user'				=> $made_by_user,
			'made_from_rs'				=> $order['made_from_rs'],

			'gross_total'				=> $cart_totals['total']['gross_with_tax'],
			'net_total'					=> $cart_totals['total']['net_with_tax'],
			'comission'					=> $cart_totals['total']['comission_with_tax'],

			'grand_total_gross'			=> $cart_totals['total']['gross_with_tax_discount'],
			'grand_total_net'			=> $cart_totals['total']['net_with_tax_discount'],
			'grand_total_comission'		=> $cart_totals['total']['comission_with_tax_discount'],

			'booking_fee_amount'		=> $cart_totals['total']['booking_fee_amount'],

			'state'						=> $order['order_state'],
			'last_payment_type'			=> $order['last_payment_type'],
			'last_payment_response'		=> $order['last_payment_response'],
			'last_payment_errors'		=> $order['last_payment_errors'],
			'stripe_customers'			=> (isset($order['stripe_customers']) ? $order['stripe_customers'] : null),
		));


		// Данные о отложенной оплате
		if ($order['pay_delay_amount']) {

			$payment_last_day = $this->pay_delay_payment_last_day($order['id']);

			$this->tpl->batch('pay_delay', array (
				'payment_last_day'	=> format_time($payment_last_day, false),
				'received'			=> ($order['pay_delay_received'] ? format_time($order['pay_delay_received']) : null),
			));
		}


		// Получаем данные по промокоду и скидкам
		$discount = $cart_totals['discount'];
		$promo = array();

		if ($discount) {

			$promo = $discount['promo'];

			if (!empty($promo['arrival_from'])) $promo['arrival_from'] = format_date_for_datepicker($promo['arrival_from']);
			if (!empty($promo['arrival_to'])) $promo['arrival_to'] = format_date_for_datepicker($promo['arrival_to']);
			
			// Список билетов
			$promo_base_tickets = array();
			$ticket_names = '';
			foreach ($promo['tickets'] as $key => $ticket) {
				
				if (!$ticket['rs_internal'] || $order['made_from_rs']) {
					
					$ticket_names .= $ticket['name'].( $key != count($promo['tickets'])-1 ? '/' : '' );
					$promo_base_tickets[] = $ticket['id'];
				}
			}
			
			if (empty($promo_base_tickets) && !empty($promo['tickets'])) {
			
				// rs_internal билеты админу не сообщаем
				$ticket_names = 'None';
			}
			
			$promo['tickets'] = $ticket_names;
				
			foreach ($promo['schedule_ranges'] as $schedule_range) {
   
			   $this->tpl->loop('schedule_range', array(
			      'time_from' => $schedule_range['time_from'],
			      'time_to' => $schedule_range['time_to'],
			   ));
			}

			if ($order['made_from_rs'] || (isset($discount['use_formula']) && ($discount['use_formula'] == 2))) {

				$promo['absorbed_by'] = 'Partner';
			}
			else {

				$promo['absorbed_by'] = 'Tripshock';
			}

			$promo['blackouts_present'] = !empty($promo['blackouts']);

			$this->tpl->loop('promo', $promo);

			if (isset($discount['use_formula'])) $this->tpl('use_discount_formula', $discount['use_formula']);


			$this->tpl->batch('order', array (
				'discount'					=> $discount['total'],
				'discount_with_tax'			=> $discount['total_with_tax'],
			));
		}

		// Список состояний элемента корзины(для соответствующих селектов)
		$cart_items_states = array (
			'paid'			=> 'Paid',
			'confirmed'		=> 'Confirmed',
			'checked_in'	=> 'Checked In',
			'unconfirmed'	=> 'Unconfirmed',
			'no_show'		=> 'No Show',
		);

		if ($this->user->is_admin()) {

			// Для админа доп. варианты
			$cart_items_states = array_merge($cart_items_states, array (
				'made'			=> 'Made',
				'cancelled'		=> 'Cancelled',
				'completed'		=> 'Completed',
				'deleted'		=> 'Deleted',
			));
		}


		$trip_items = array();

		$can_edit_order = false;

		$this->db->query("
			SELECT
				cart_items.id AS cart_item_id,
				cart_items.calendar_id AS calendar_id,
				cart_items.is_fh_item AS is_fh_item,

				cart_items.total AS cart_item_total,
				(cart_items.total - cart_items.tax) AS cart_item_net,
				cart_items.tax AS cart_item_comission,

				cart_items.welcomeback_sent,
				cart_items.state_name,
				cart_items.data,
				cart_items.gift_code,
				cart_items.confirmation,

				orders.total AS order_total,
				orders.tax AS order_comission,

				orders_users_data.email AS user_email,

				products.id AS product_id,
				products.name AS product_name,
				products.product_type_id,
				products.city_id,
				products.city_id2,
				products.city_id3,
				products.city_id4,
				products.city_id5,
				products.city_id6,
				products.activity_id,
				products.activity2_id,
				products.activity3_id,

				calendar.date AS date,
				calendar2.date AS date2,

				partner.id AS partner_id,
				partner.title,
				partner.first_name AS first_name,
				partner.last_name AS last_name,

				deduct.state_name AS deduct_state_name,
				deduct.amount AS deduct_amount,
				
				refunds.status AS refund_status
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				JOIN orders_users_data ON orders_users_data.order_id = orders.id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2
				JOIN products ON products.id = cart_items.product_id
				JOIN users AS partner ON partner.id = products.partner_id
				LEFT JOIN deduct ON deduct.cart_item_id = cart_items.id
				LEFT JOIN refunds ON refunds.cart_item_id = cart_items.id
			WHERE
				cart_items.cart_id = ?
				".($this->user->is_partner() ? " AND products.partner_id = ".$this->user->data('id') : '')."
			ORDER BY
				cart_items.id DESC",
			$order['cart_id']
		);
		$db_res = $this->db->result;

		while ($cart_item = $this->db->fetch(0, $db_res)) {

			$raw_cart_item = array();

			$cart_item_is_cancelled = ($cart_item['state_name'] == 'cancelled');
			$cart_item_is_deleted = ($cart_item['state_name'] == 'deleted');

			$can_edit_cart_item = false;

			if (
				(
					($cart_item['state_name'] != 'completed')
						&&
					($this->user->is_permitted('order_edit') || $this->user->is_partner())
				)
					||
				$this->is_user_permitted_to_edit_state($cart_item['state_name'])
				) {

				$can_edit_order = true;
				$can_edit_cart_item = true;
			}

			// Список состояний части корзины с выбранным текущим
			$cart_item_states = array();
			$is_state_completed = false;

			if (in_array($cart_item['state_name'], array_keys($cart_items_states))) {

				foreach ($cart_items_states as $state => $state_name) {

					$cart_item_states[] = array (
						'state'			=> $state,
						'state_name'	=> $state_name,
						'is_selected'	=> ($state == $cart_item['state_name']),
					);

					if ($cart_item['state_name'] == 'completed') $is_state_completed = true;
				}
			}


			$book_info = $this->db->decode_data($cart_item['data']);

			$cart_item_totals = $cart_totals['cart_items'][$cart_item['cart_item_id']];

			// Комментарии есть во всех итемах, независимо от их типа
			$cart_item['comment_guest'] = $book_info['comments']['guest'];
			$cart_item['comment_partner'] = $book_info['comments']['partner'];

			$can_be_discounted = true;

			if ($discount) {

				// Проверка на возможность применения скидки по параметрам продукта
				// product_type_id, product_id, city_id(все 6), activity_id(все 3), partner_id
				if (!$this->price->cart_item_can_be_discounted($cart_item, $promo)) {

					$can_be_discounted = false;
				}
			}

			if (product_type($cart_item['product_type_id']) == 'trip') {

				$is_fh_item = ($cart_item['is_fh_item'] ? true : false);

				if ($is_fh_item) $this->tpl('is_fh_items', true);

				$tickets = $book_info['tickets'];
				$additional_options = $book_info['additional_options'];


				// список всех возможных расписаний, билетов и опций у заказанного трипа
				$trip = $this->page_trip->get_list(array (
					'id'					=> $cart_item['product_id'],
					'partner_id'			=> null,
					'with_ticket_types'		=> true,
					'with_categories'		=> false,
					'ignore_fh'				=> !$is_fh_item,
				));
				$trip = array_pop($trip);


				$trip_schedules = $trip['schedules'];
				$trip_inventories = $trip['inventories'];
				$trip_tickets = $trip['tickets'];
				$trip_options = $trip['additional_options'];


				// Выбранное время
				if ($book_info['schedule']) {

					if (empty($trip_schedules[$book_info['schedule']['id']])) {

						$trip_schedules[$book_info['schedule']['id']] = $book_info['schedule'];
					}
				}

				// Выбранное inventory
				if ($book_info['inventory']) {

					if (empty($trip_inventories[$book_info['inventory']['id']])) {

						$trip_inventories[$book_info['inventory']['id']] = $book_info['inventory'];
					}
				}


				// Информация для админа
				if ($this->user->is_admin()) {

					$cart_item_tickets = array();
					$gross_pre_discount = 0;

					foreach ($tickets as $ticket) {

						// Заменяем уже имющийся билет трипа на сохранённый на случай разных названий
						// или добавляем уже откреплённый от трипа, но когда-то купленный билет
						$trip_tickets[$ticket['id']] = $ticket;


						$ticket_totals = $cart_item_totals['tickets']['data'][$ticket['id']];

						$ticket_barcodes = '';
						if (!empty($ticket['barcodes'])) $ticket_barcodes = implode(', ', $ticket['barcodes']);

						$cart_item_tickets[] = array (
							'field_name'		=> 'trip_items['.$cart_item['cart_item_id'].'][tickets]['.$ticket['id'].']',

							'id'				=> $ticket['id'],
							'name'				=> $ticket['name'],

							'booked'			=> $ticket['booked'],
							'guests'			=> (!empty($ticket['guests']) ? $ticket['guests'] : array()),

							'price'				=> $ticket['price'],
							'wholesale'			=> $ticket['wholesale'],
							'comission'			=> $ticket['comission'],

							'total_rate'		=> $ticket_totals['gross'],
							'total_comission'	=> $ticket_totals['comission'],

							'barcodes'			=> $ticket_barcodes,
						);
						
						if(empty($promo_base_tickets) || in_array($ticket['id'],$promo_base_tickets)) {
				
							$gross_pre_discount += $ticket['price']*$ticket['booked'];
						}
					}

					$trip_items[$cart_item['cart_item_id']]['tickets'] = $trip_tickets;


					// Доп. опции
					$cart_item_options = array();

					foreach ($additional_options as $option) {

						// Заменяем уже имеющуюся опцию трипа на сохранённую на случай разных названий
						// или добавляем уже откреплённую от трипа, но когда-то купленную опцию
						$trip_options[$option['id']]['id'] = $option['id'];
						$trip_options[$option['id']]['name'] = $option['name'];
						$trip_options[$option['id']]['type'] = $option['type'];

						if ($option['type'] == 'select') {

							$trip_options[$option['id']]['values'][$option['value']['id']] = array (
								'id'			=> $option['value']['id'],
								'name'			=> $option['value']['name'],
								'price'			=> $option['value']['price'],
								'comission'		=> $option['value']['comission'],
								'apply_tax'		=> $option['value']['apply_tax'],
							);
							
							$gross_pre_discount += $option['value']['price'];
						}

						$cart_item_options[] = array (
							'field_name'		=> 'trip_items['.$cart_item['cart_item_id'].'][additional_options]['.$option['id'].']',
							'id'				=> $option['id'],
							'name'				=> $option['name'],
							'type'				=> $option['type'],
							'value_id'			=> $option['value']['id'],
							'value_name'		=> $option['value']['name'],

							'price'				=> $option['value']['price'],
							'comission'			=> $option['value']['comission'],

							'total_comission'	=> $cart_item_totals['additional_options']['data'][$option['id']]['comission'],
							'apply_tax'			=> $option['value']['apply_tax'],
						);
					}

					$trip_items[$cart_item['cart_item_id']]['additional_options'] = $trip_options;


					// Общие суммы без налога и с ним
					$cart_item['total'] = $cart_item_totals['total']['gross'];
					$cart_item['net'] = $cart_item_totals['total']['net'];
					$cart_item['comission'] = $cart_item_totals['total']['comission'];
					
					$cart_item['pre_discount'] = $gross_pre_discount;
					
					$cart_item['total_with_tax'] = $cart_item_totals['total']['gross_with_tax'];
					$cart_item['net_with_tax'] = $cart_item_totals['total']['net_with_tax'];
					$cart_item['comission_with_tax'] = $cart_item_totals['total']['comission_with_tax'];


					// Даже если скидки нет или не действует на этот элемент - отправляем инфу
					$cart_item['discount'] = 0;
					$cart_item['discount_with_tax'] = 0;
					$cart_item['can_be_discounted'] = $can_be_discounted;

					if (!empty($discount['cart_items'][$cart_item['cart_item_id']])) {

						$cart_item['discount'] = $discount['cart_items'][$cart_item['cart_item_id']]['discount'];
						$cart_item['discount_with_tax'] = $discount['cart_items'][$cart_item['cart_item_id']]['discount_with_tax'];
					}

					// Суммы заказа с учётом скидки
					$cart_item['total_with_discount'] = $cart_item_totals['total']['gross_with_tax_discount'];
					$cart_item['net_with_discount'] = $cart_item_totals['total']['net_with_tax_discount'];
					$cart_item['comission_with_discount'] = $cart_item_totals['total']['comission_with_tax_discount'];


					// Процент и сумма сбора с заказанного
					$cart_item['booking_fee'] = $book_info['product']['booking_fee'];
					$cart_item['booking_fee_amount'] = $cart_item_totals['total']['booking_fee_amount'];
				}
				// Информация для партнёра
				else if ($this->user->is_partner()) {

					$cart_item['net_total'] = money($cart_item['cart_item_net'], true);

					if ($is_fh_item) {

						$cart_item['schedule_time'] = $book_info['fh_data']['fh_schedule']['time'];
					}
					else {

						$cart_item['schedule_time'] = ($book_info['schedule'] ? $book_info['schedule']['time'] : null);
					}

					// Строка со списком билетов
					$cart_item['tickets_string'] = format_tickets_string($tickets);
					$raw_cart_item['tickets_guests_text'] = format_tickets_guests($tickets);

					// Строка со списком опций
					$additional_options_string = '';
					foreach ($additional_options as $option) {

						if ($option['type'] == 'select') {

							$additional_options_string .= ' + '.$option['value']['name'].' ('.money($option['value']['price']).')';
						}
						else {

							$additional_options_string .= ' + ['.$option['name'].' : '.$option['value']['name'].']';
						}
					}

					$cart_item['additional_options_string'] = $additional_options_string;
				}


				$cart_item['trip_tax'] = $book_info['product']['tax'];
				$cart_item['arrival_date'] = ($cart_item['date'] ? format_date_for_datepicker($cart_item['date']) : '');

				$cart_item['use_calendar'] = ($cart_item['calendar_id'] || $cart_item['gift_code'] ? true : false);

				// Выводим имя партнёра и ссылку на его редактирование
				$cart_item['partner'] = $this->user->format_name($cart_item);

				$cart_item['can_edit'] = $can_edit_cart_item;
				$cart_item['is_cancelled'] = $cart_item_is_cancelled;

				$cart_item['avail_restored'] = ($cart_item_is_cancelled || $cart_item_is_deleted);

				$cart_item['site_fee'] = ($cart_item_is_cancelled ? $book_info['cancellation_data']['site_fee'] : 0);
				$cart_item['partner_fee'] = ($cart_item_is_cancelled ? $book_info['cancellation_data']['partner_fee'] : 0);
				$cart_item['refund_amount'] = ($cart_item_is_cancelled ? $book_info['cancellation_data']['refund_amount'] : 0);
				$cart_item['cancel_reason'] = ($cart_item_is_cancelled ? $book_info['cancellation_data']['cancel_reason' ] : '');

				$cart_item['product_has_additional_options'] = ($trip_options ? true : false);


				// Данные Fareharbor для связанного трипа
				if ($is_fh_item) {

					$fh_book_info = $book_info['fh_data'];

					$cart_item['is_fh_item'] = true;


					if (isset($fh_book_info['fh_order']) && isset($fh_book_info['fh_order']['confirmation_url'])) {

						$raw_cart_item['fh_confirmation_url'] = urldecode($fh_book_info['fh_order']['confirmation_url']);
					}
				}

				//вычеты
				$cart_items_deducts_states = array (
							'null'					=> 'No Deduct',
							'lost_dispute'			=> 'Lost Dispute',
							'cancel_after_payment'	=> 'Cancellation After Payment',
							'booking_error'			=> 'Booking Error',
				);

				$cart_item_deduct_states = array();
				$is_selected_deduct = false;

				foreach ($cart_items_deducts_states as $state => $state_name) {

					$cart_item_deduct_states[] = array (
						'state'			=> $state,
						'state_name'	=> $state_name,
						'is_selected'	=> ($state == $cart_item['deduct_state_name']),
					);

					if ($state == $cart_item['deduct_state_name']) $is_selected_deduct = true;
				}

				$cart_item['deduct_is_selected'] = $is_selected_deduct;
				$cart_item['deduct_is_completed'] = $is_state_completed;



				// Основная информация о трипе
				$this->tpl->loop('trip', $cart_item, $raw_cart_item);


				//статусы вычетов
				foreach ($cart_item_deduct_states as $cart_item_deduct_state) {

					$this->tpl->loop('trip.state_deduct', $cart_item_deduct_state);
				}

				// Состояния
				foreach ($cart_item_states as $cart_item_state) $this->tpl->loop('trip.state', $cart_item_state);


				if ($this->user->is_admin()) {

					// Расписания
					if ($book_info['schedule']) {

						foreach ($trip_schedules as $schedule) {

							$is_selected = false;
							if ($schedule['id'] == $book_info['schedule']['id']) {

								$is_selected = true;
								$schedule['time'] = $book_info['schedule']['time'];
							}

							$this->tpl->loop('trip.schedule', array (
								'id'			=> $schedule['id'],
								'time'			=> $schedule['time'],
								'is_selected'	=> $is_selected,
							));
						}
					}
					else if ($is_fh_item) {

						// Основная переменная расписаний для FH будет всегда пустой
						$this->tpl->loop('trip.schedule', array (
							'id'			=> $fh_book_info['fh_schedule']['id'],
							'time'			=> $fh_book_info['fh_schedule']['time'],
							'is_selected'	=> true,
						));
					}

					// Inventory
					if ($book_info['inventory']) {

						foreach ($trip_inventories as $inventory) {

							$is_selected = false;
							if ($inventory['id'] == $book_info['inventory']['id']) {

								$is_selected = true;
								$inventory['name'] = $book_info['inventory']['name'];
							}

							$this->tpl->loop('trip.inventory', array (
								'id'			=> $inventory['id'],
								'name'			=> $inventory['name'],
								'is_selected'	=> $is_selected,
							));
						}
					}


					// Заказанные билеты
					foreach ($cart_item_tickets as $cart_item_ticket) {

						$this->tpl->loop('trip.ticket', $cart_item_ticket);

						if ($book_info['product']['need_tickets_guests']) {

							$trip_items[$cart_item['cart_item_id']]['guests'][$cart_item_ticket['id']] = $cart_item_ticket['guests'];
						}

						// Список билетов трипа для селекта, с выделенным купленным
						foreach ($trip_tickets as $trip_ticket) {
							
							$can_be_discounted = (empty($promo_base_tickets) || in_array($trip_ticket['id'],$promo_base_tickets) ? true : false);
							$trip_items[$cart_item['cart_item_id']]['tickets'][$trip_ticket['id']]['can_be_discounted'] = $can_be_discounted;
							
							$this->tpl->loop('trip.ticket.tickets_list', array (
								'id'				=> $trip_ticket['id'],
								'name'				=> $trip_ticket['name'],
								'is_selected'		=> ($trip_ticket['id'] == $cart_item_ticket['id']),
								'can_be_discounted'	=> $can_be_discounted,
							));
						}
					}

					// Заказанные опции
					foreach ($cart_item_options as $cart_item_option) {

						$this->tpl->loop('trip.option', $cart_item_option);

						// Список опций для селекта
						foreach ($trip_options as $trip_option) {

							$this->tpl->loop('trip.option.options_list', array (
								'id'			=> $trip_option['id'],
								'name'			=> $trip_option['name'],
								'is_selected'	=> ($trip_option['id'] == $cart_item_option['id']),
							));
						}

						if ($cart_item_option['type'] == 'select') {

							$choosed_option_values = $trip_options[$cart_item_option['id']]['values'];

							foreach ($choosed_option_values as $option_value) {

								$this->tpl->loop('trip.option.values_list', array (
									'id'			=> $option_value['id'],
									'name'			=> $option_value['name'],
									'is_selected'	=> ($option_value['id'] == $cart_item_option['value_id']),
								));
							}
						}
					}
				}
			}
			else if (product_type($cart_item['product_type_id']) == 'hotel') {

				// Обычные отели
				if (empty($book_info['affiliate_data'])) {

					$hotel_total = $cart_item_totals['total']['gross'];
					$hotel_net = $cart_item_totals['total']['net'];
					$hotel_comission = $cart_item_totals['total']['comission'];

					// Для админа выводим всё
					if ($this->user->is_admin()) {

						// Включения
						$inclusions = array();

						foreach ($book_info['inclusions'] as $inclusion_id => $inclusion) {

							$inclusion_type = array();

							foreach ($this->page_hotel->inclusion_type as $type_id => $type_name) {

								$inclusion_type[] = array (
									'id'			=> $type_id,
									'name'			=> $type_name,
									'is_selected'	=> ($type_id == $inclusion['type']),
								);
							}


							$inclusion_frequency = array();

							foreach ($this->page_hotel->inclusion_frequency as $frequency_id => $frequency_name) {

								$inclusion_frequency[] = array (
									'id'			=> $frequency_id,
									'name'			=> $frequency_name,
									'is_selected'	=> ($frequency_id == $inclusion['frequency']),
								);
							}


							if (isset($cart_item_totals['inclusions']['data'][$inclusion['id']])) {

								$inclusion_totals = $cart_item_totals['inclusions']['data'][$inclusion['id']];
							}
							else {

								$inclusion_totals = array('gross_with_tax' => 0);
							}

							$inclusions[] = array (
								'field_name'	=> 'hotel_items['.$cart_item['cart_item_id'].'][inclusions]['.$inclusion_id.']',
								'name'			=> $inclusion['name'],
								'value'			=> $inclusion['value'],
								'is_taxed'		=> $inclusion['is_taxed'],
								'total'			=> $inclusion_totals['gross_with_tax']*$book_info['booked'],
								'type'			=> $inclusion_type,
								'frequency'		=> $inclusion_frequency,
							);
						}

						$rates = array();

						foreach ($book_info['days_info'] as $date => $day_info) {

							$rebate_rate = 0;
							if (!empty($day_info['rebate_rate'])) $rebate_rate = $day_info['rebate_rate'];

							$day_totals = $cart_item_totals['days_info']['data'][$date];

							$rates[] = array (
								'field_name'		=> 'hotel_items['.$cart_item['cart_item_id'].'][day_rates]['.$date.']',
								'date'				=> date("d M Y", strtotime($date)),
								'day_rate'			=> ($day_info['day_rate'] + $rebate_rate),
								'rebate_rate'		=> $rebate_rate,

								'day_total'			=> $day_totals['gross'],
								'day_net'			=> $day_totals['net'],
								'day_comission'		=> $day_totals['comission'],

								'margin'			=> $day_info['comission'],
								'wholesale'			=> $day_info['wholesale'],
							);
						}
					}
					// Партнёру только часть и в упрощённом виде
					else {

						// Включения
						$inclusions = array();

						foreach ($book_info['inclusions'] as $inclusion_id => $inclusion) {

							$inclusions[] = array (
								'name'		=> $inclusion['name'],
								'total'		=> money($inclusion['total'], true),
							);
						}

						// Цены по дням для партнёра
						$rates = array();

						foreach ($book_info['days_info'] as $date => $day_info) {

							$rebate_rate = 0;
							if (!empty($day_info['rebate_rate'])) $rebate_rate = $day_info['rebate_rate'];

							$day_totals = $cart_item_totals['days_info']['data'][$date];

							$rates[] = array (
								'date'				=> date("d M Y", strtotime($date)),
								'day_rate'			=> money($day_info['day_rate'] + $rebate_rate, true),

								'day_total'			=> money($day_totals['gross'], true),
								'day_net'			=> money($day_totals['net'], true),
								'day_net_with_tax'	=> money($day_totals['net_with_tax'], true),
							);
						}
					}


					// Скидка
					$hotel_discount = 0;
					$hotel_discount_with_tax = 0;

					if (!empty($discount['cart_items'][$cart_item['cart_item_id']])) {

						$hotel_discount = $discount['cart_items'][$cart_item['cart_item_id']]['discount'];
						$hotel_discount_with_tax = $discount['cart_items'][$cart_item['cart_item_id']]['discount_with_tax'];
					}


					// Акции
					$promotions = array();

					if (!empty($book_info['promotions'])) {

						foreach ($book_info['promotions'] as $promotion) {

							if (
								!$promotion['description']
									||
								($this->user->is_partner() && in_array($this->page_hotel->promotion_types[$promotion['type']], array('rebate_$', 'rebate_%')))
								) continue;

							$promotions[] = array (
								'description'		=> $promotion['description'],
							);
						}
					}


					// Состав комнат
					$rooms_data = array();

					foreach ($book_info['rooms_data'] as $room_number => $room_data) {

						$rooms_data[] = array (
							'number'		=> $room_number,
							'adults'		=> $room_data['adults'],
							'children'		=> $room_data['children'],
							'children_ages'	=> ($room_data['children'] ? implode(', ', $room_data['children_ages']) : null),
							'last_name'		=> (!empty($room_data['last_name']) ? $room_data['last_name'] : null),
							'first_name'	=> (!empty($room_data['first_name']) ? $room_data['first_name'] : null),
						);
					}


					// Общая информация о заказанном
					$this->tpl->loop('hotel', array (
						'cart_item_id'				=> $cart_item['cart_item_id'],
						'date_from'					=> format_time($cart_item['date'], false),
						'date_to'					=> format_time($cart_item['date2'], false),

						'hotel_id'					=> $book_info['product']['id'],
						'hotel_name'				=> $book_info['product']['name'],

						'hotel_tax'					=> $book_info['product']['tax'],
						'partner_comission'			=> $book_info['partner_comission'],

						'total'						=> $hotel_total,
						'net'						=> $hotel_net,
						'comission'					=> $hotel_comission,
						'total_with_tax'			=> $cart_item_totals['total']['gross_with_tax'],
						'net_with_tax'				=> $cart_item_totals['total']['net_with_tax'],
						'comission_with_tax'		=> $cart_item_totals['total']['comission_with_tax'],

						'can_be_discounted'			=> $can_be_discounted,
						'discount'					=> $hotel_discount,
						'discount_with_tax'			=> $hotel_discount_with_tax,
						'total_with_discount'		=> $cart_item_totals['total']['gross_with_tax_discount'],
						'net_with_discount'			=> $cart_item_totals['total']['net_with_tax_discount'],
						'comission_with_discount'	=> $cart_item_totals['total']['comission_with_tax_discount'],

						'booking_fee'				=> $book_info['product']['booking_fee'],
						'booking_fee_amount'		=> $cart_item_totals['total']['booking_fee_amount'],

						'confirmation'				=> $cart_item['confirmation'],
						'state_name'				=> $cart_item['state_name'],
						'can_edit'					=> $can_edit_cart_item,
						'comment_guest'				=> $cart_item['comment_guest'],
						'comment_partner'			=> $cart_item['comment_partner'],

						'is_cancelled'				=> $cart_item_is_cancelled,
						'site_fee'					=> ($cart_item_is_cancelled ? $book_info['cancellation_data']['site_fee'] : 0),
						'partner_fee'				=> ($cart_item_is_cancelled ? $book_info['cancellation_data']['partner_fee'] : 0),
						'refund_amount'				=> ($cart_item_is_cancelled ? $book_info['cancellation_data']['refund_amount'] : 0),
						'cancel_reason'				=> ($cart_item_is_cancelled ? $book_info['cancellation_data']['cancel_reason'] : ''),

						'partner_id'				=> $cart_item['partner_id'],
						'partner_name'				=> $this->user->format_name($cart_item),

						'welcomeback_sent'			=> $cart_item['welcomeback_sent'],

						'room_id'					=> $book_info['room']['id'],
						'room_name'					=> $book_info['room']['name'],
						'room_description'			=> (!empty($book_info['room']['description']) ? $book_info['room']['description'] : null),
						'rooms'						=> $book_info['booked'],
					));

					// Акции
					foreach ($promotions as $promotion) $this->tpl->loop('hotel.promotion', $promotion);

					// Состав комнат
					foreach ($rooms_data as $room) $this->tpl->loop('hotel.room', $room);

					// Параметры дней
					foreach ($rates as $rate) $this->tpl->loop('hotel.rate', $rate);

					// Включения
					foreach ($inclusions as $inclusion) {

						if ($this->user->is_admin()) {

							$inclusion_type = $inclusion['type'];
							$inclusion_frequency = $inclusion['frequency'];

							unset($inclusion['type']);
							unset($inclusion['frequency']);
						}

						$this->tpl->loop('hotel.inclusion', $inclusion);

						if ($this->user->is_admin()) {

							foreach ($inclusion_type as $type) $this->tpl->loop('hotel.inclusion.type', $type);
							foreach ($inclusion_frequency as $frequency) $this->tpl->loop('hotel.inclusion.frequency', $frequency);
						}
					}

					// Состояния
					foreach ($cart_item_states as $cart_item_state) $this->tpl->loop('hotel.state', $cart_item_state);
				}
				// Партнёрские отели(экспедия)
				else {

					$reservation = $book_info['expedia_data'];

					if (!empty($reservation['RateInfos']['RateInfo'])) $rate_info = $reservation['RateInfos']['RateInfo'];
					else $rate_info = $reservation['RateInfo'];

					$confirmation = null;
					$rates = array();
					$rooms_data = array();

					if (!empty($reservation['confirmationNumbers'])) {

						if (!empty($rate_info['RoomGroup'])) $room_group = $rate_info['RoomGroup'];
						else $room_group = $reservation['RoomGroup'];

						if (!empty($reservation['numberOfRoomsBooked']) && ($reservation['numberOfRoomsBooked'] == 1)) $rooms = array($room_group['Room']);
						else $rooms = $room_group['Room'];

						// Состав гостей по комнатам
						$rooms_data = array ();

						foreach ($rooms as $room_number => $room_data) {

							$children_ages = null;
							if ($room_data['numberOfChildren'] > 1) $children_ages = implode(', ', $room_data['childAges']);
							else if ($room_data['numberOfChildren'] == 1) $children_ages = $room_data['childAges'];

							$rooms_data[] = array (
								'number'		=> ($room_number + 1),
								'adults'		=> $room_data['numberOfAdults'],
								'children'		=> $room_data['numberOfChildren'],
								'children_ages'	=> $children_ages,
								'last_name'		=> $room_data['lastName'],
								'first_name'	=> $room_data['firstName'],
							);
						}

						if ($rate_info['ChargeableRateInfo']['NightlyRatesPerRoom']['@size'] == 1) {

							$nightly_rates = array($rate_info['ChargeableRateInfo']['NightlyRatesPerRoom']['NightlyRate']);
						}
						else {

							$nightly_rates = $rate_info['ChargeableRateInfo']['NightlyRatesPerRoom']['NightlyRate'];
						}

						// Цены по дням
						$rates = array();
						$current_day = strtotime($reservation['arrivalDate']);

						foreach ($nightly_rates as $day_rate) {

							$rates[] = array (
								'date'		=> date("d M Y", $current_day),
								'rate'		=> money($day_rate['@rate'], true)
							);

							$current_day = strtotime('+1 day', $current_day);
						}

						if (is_array($reservation['confirmationNumbers'])) $confirmation = implode(', ', $reservation['confirmationNumbers']);
						else $confirmation = $reservation['confirmationNumbers'];
					}

					$this->tpl->loop('expedia_hotel', array (
						'cart_item_id'				=> $cart_item['cart_item_id'],
						'date_from'					=> format_time($cart_item['date'], false),
						'date_to'					=> format_time($cart_item['date2'], false),

						'hotel_id'					=> $cart_item['product_id'],
						'hotel_name'				=> $cart_item['product_name'],

						'total'						=> money(round_price($rate_info['ChargeableRateInfo']['@nightlyRateTotal']), true),
						'surcharges'				=> money(round_price($rate_info['ChargeableRateInfo']['@surchargeTotal']), true),
						'total_w_surcharges'		=> round_price($rate_info['ChargeableRateInfo']['@total']),

						'confirmation'				=> $confirmation,
						'state_name'				=> $cart_item['state_name'],

						'itineraryId'				=> (!empty($reservation['itineraryId']) ? $reservation['itineraryId'] : null),

						'partner_id'				=> $cart_item['partner_id'],
						'partner_name'				=> $this->user->format_name($cart_item),

						'comment_guest'				=> $cart_item['comment_guest'],

						'welcomeback_sent'			=> $cart_item['welcomeback_sent'],

						'room_name'					=> $book_info['room']['name'],
						'rooms'						=> $book_info['booked'],
					));

					foreach ($rates as $rate) $this->tpl->loop('expedia_hotel.rate', $rate);

					foreach ($rooms_data as $room) $this->tpl->loop('expedia_hotel.room', $room);
				}
			}
		}


		$this->tpl('can_edit_order', $can_edit_order);

		// Билет/опции/расписания/лодки заказанных трипов
		$this->tpl_raw('trip_items', json_encode($trip_items));


		// Лог заказа
		$order_log = $this->log->get_order_log($order['id']);

		foreach ($order_log as $log_item) $this->tpl->loop('log', array(), $log_item);
	}

	public function mode_affiliate_orders() {

		if (!$this->user->is_permitted('order_view') && !$this->user->is_partner()) $this->url->go(404);

		new request_order_search(array (
			'name'		=> 'order_search',
			'method'	=> 'get',
			'is_direct'	=> true,
			'vars'		=> array (
				'made_from,<text>',
				'made_to,<text>',
				'arrival_from,<text>',
				'arrival_to,<text>',
				'affiliate_id,<select>',
				'orders_per_page,<select>',
				'tab,<hidden>',
				'page_num,<hidden>',
			),
		));
	}

	// Отправляем приглашение оставить ревью
	public function mode_send_invitation() {

		if (!$this->user->is_permitted('order_edit') || !($cart_item_id = $this->url->get_int('id'))) $this->url->go(404);

		$this->add_page('utils');
		$this->page_utils->welcomeback_notifications($cart_item_id);

		$this->url->go('referer');
	}


	// Смена статуса заказа
	// confirmed
	public function mode_confirm() {

		if (!($this->user->is_permitted('order_edit') || $this->user->is_partner())) $this->url->go(404);

		$data = f2b2h_request::clean($_GET);

		if (!empty($data['id'])) {

			$confirmation = (!empty($data['confirmation_number']) ? $data['confirmation_number'] : null);

			$this->set_state($data['id'], 'confirmed', $confirmation);
		}

		$this->url->go('referer');
	}
	// unconfirmed
	public function mode_unconfirm() {

		if (!($this->user->is_permitted('order_edit') || $this->user->is_partner())) $this->url->go(404);

		$data = f2b2h_request::clean($_GET);

		if (!empty($data['id'])) {

			$this->set_state($data['id'], 'unconfirmed');
		}

		$this->url->go('referer');
	}
	// deleted
	public function mode_delete() {

		if (!$this->user->is_permitted('order_edit')) $this->url->go(404);

		$data = f2b2h_request::clean($_GET);

		if (!empty($data['id'])) {

			$this->set_state($data['id'], 'deleted');
		}

		$this->url->go('referer');
	}
	// completed
	public function mode_complete() {

		if (!$this->user->is_permitted('order_edit')) $this->url->go(404);

		$data = f2b2h_request::clean($_GET);

		if (!empty($data['id'])) {

			$this->set_state($data['id'], 'completed');
		}

		$this->url->go('referer');
	}


	// Fareharbor

	// Просмотр лога внешних изменений в заказах FH
	public function mode_fh_change_log() {

		if (!$this->user->is_permitted('order_edit')) $this->url->go(404);

		$log_rows = $this->fh_get_change_log();

		foreach ($log_rows as $log_row) {

			$this->tpl->loop('log', array (
					'self_id'		=> $log_row['id'],
					'cart_item_id'	=> $log_row['cart_item_id'],
					'order_id'		=> $log_row['order_id'],
					'time'			=> $log_row['time'],
				),
				array (
					'txt'			=> $log_row['txt'],
				)
			);
		}
	}

	// Убираем уже не нужные элементы лога из видимого списка
	public function mode_fh_hide_log() {

		if (!$this->user->is_permitted('order_edit')) $this->url->go(404);

		if (($type = $this->url->get_str('type')) && ($self_id = $this->url->get_int('id'))) {

			if ($type == 'row') {

				$this->db->insert_update('fh_items_log', array (
					'id'		=> $self_id,
					'status'	=> 0,
				));
			}
			else if ($type == 'item') {

				$this->db->query("
					UPDATE
						fh_items_log
					SET
						status = 0
					WHERE
						cart_item_id = ?",
					$self_id
				);
			}
			else if ($type == 'order') {

				$cart_items = array();

				$this->db->query("
					SELECT
						cart_items.id
					FROM
						cart_items
						JOIN orders ON orders.cart_id = cart_items.cart_id
					WHERE
						orders.id = ?",
					$self_id
				);
				$db_res = $this->db->result;

				while ($cart_item = $this->db->fetch($db_res)) $cart_items[] = $cart_item['id'];


				if ($cart_items) {

					$this->db->query("
						UPDATE
							fh_items_log
						SET
							status = 0
						WHERE
							cart_item_id IN (".$this->db->in($cart_items).")"
					);
				}
			}
		}

		$this->url->go('referer');
	}


	// Назначеаем некоторому заказу портал (или наоборот - убираем его)
	public function mode_assign_to_portal() {

		if (!($this->user->is_admin())) $this->url->go(404);

		$data = f2b2h_request::clean($_GET);

		if (empty($data['id'])) $this->url->go(404);

		$this->db->query("
			UPDATE
				orders
			SET
				affiliate_id = ?
			WHERE
				orders.id = ?",
			(!empty($data['affiliate_id']) ? $data['affiliate_id'] : ''),
			$data['id']
		);

		// Портальному заказу положенно насчитать комиссию
		$portal_commission = $this->calculate_portal_commission($data['id']);

		$this->db->insert_update('orders', array (
			'id'					=> $data['id'],
			'affiliate_commission'	=> $portal_commission,
		));

		$this->url->go('referer');
	}

	// Массовая смена статуса заказов или их частей
	// так же рассылка писем с подтверждением заказа
	public function mode_bulk_edit() {

		if (!($this->user->is_permitted('order_edit') || $this->user->is_partner())) $this->url->go(404);

		$avail_states = array (
			'confirm'		=> 'confirmed',
			'unconfirm'		=> 'unconfirmed',
		);

		if ($this->user->is_admin()) {

			$avail_states = array_merge($avail_states, array (
				'complete'		=> 'completed',
				'delete'		=> 'deleted',
			));
		}

		$data = f2b2h_request::clean($_POST);

		if (!empty($data['action']) && !empty($data['ids'])) {

			foreach ($data['ids'] as $id) {

				// Письма с подтверждением
				if ($this->user->is_admin() && ($data['action'] == 'resend') && empty($data['cart_items'])) {

					$this->mail_user_order_confirmation($id);
				}
				// Состояние заказа
				else if (in_array($data['action'], array_keys($avail_states)) && empty($data['cart_items'])) {

					$this->set_state($id, $avail_states[$data['action']]);
				}
				// Состояние частей заказа
				else if (in_array($data['action'], array_keys($avail_states)) && !empty($data['cart_items'])) {

					$this->cart_item_set_state($id, $avail_states[$data['action']]);
				}
			}
		}

		$this->url->go('referer');
	}

	// Получена полная оплата заказа с PayDelay
	public function mode_received() {

		if (!$this->user->is_permitted('order_edit')) $this->url->go(404);

		if ($order_id = $this->url->get_int('id')) {

			$this->db->query("
				UPDATE
					orders
				SET
					pay_delay_received = ?
				WHERE
					id = ?",
				date("Y-m-d H:i:s"),
				$order_id
			);
		}

		$this->url->go('referer');
	}

	// Обновляем присутствие текущего пользователя на странице редактирования текущего заказа
	public function mode_update_user_edit_presence() {

		if (!($this->user->is_permitted('order_view') || $this->user->is_partner())) return;

		if (!$this->id) return;

		$order_user_edit = array (
			'order_id'		=> $this->id,
			'user_id'		=> $this->user->data['id'],
			'last_update'	=> format_date_for_db(null, true),
		);

		$this->db->insert_update_no_id('order_user_edit', $order_user_edit, array('order_id','user_id'));
	}

	// -------------------------------------
	//				Front-end
	// -------------------------------------

	// Оформление заказа. Шаг 1. Данные о покупателе
	// Часть проверок, общая для step1/step2, в коммоне
	public function mode_step1() {

		$this->add_page('cart');
		if (!($cart = $this->page_cart->get($this->cart_id)) || !$cart['count']) $this->url->go(404);

		// Если заказ проводит уже залогиненный юзер(не админ/партнёр), то надо сделать логаут
		// чтобы на одного юзера не вешалось несколько заказов
		if (
			$this->user->is_temp_user()
				&&
			empty($_SESSION['order_step1_data'])
			) {

			$this->user->logout();
			$this->url->go();
		}

		if (empty($_SESSION['order_step1_data']) && $this->user->is_regular_user()) {

			$this->add_page('country');
			$country = $this->page_country->get($this->user->data('country_id'));

			$_SESSION['order_user_id'] = $this->user->data('id');
			$_SESSION['order_step1_data'] = array (
				'first_name'		=> $this->user->data('first_name'),
				'last_name'			=> $this->user->data('last_name'),
				'phone'				=> $this->user->data('phone'),
				'email'				=> $this->user->data('email'),
				'address'			=> $this->user->data('address'),
				'city'				=> $this->user->data('city'),
				'zip'				=> $this->user->data('zip'),
				'state_id'			=> $this->user->data('state_id'),
				'state_name'		=> $this->user->data('state_name'),
				'country_id'		=> $this->user->data('country_id'),
			);
		}

		// Если в сессии висит order_id, то оставляем его только для одного случая
		// заказ был создан, но не был оформлен до конца. Т.е. order_state == 'made'
		if (!empty($_SESSION['order_id'])) {

			$this->db->query("
				SELECT
					orders_states.state
				FROM
					orders
					LEFT JOIN orders_states ON orders_states.id = orders.last_state_id
				WHERE
					orders.id = ?
						AND
					orders_states.state IN (NULL, 'made')",
				$_SESSION['order_id']
			);

			if (!($order = $this->db->fetch())) {

				unset($_SESSION['order_id']);
				unset($_SESSION['order_user_id']);
			}
		}

		if (empty($_SESSION['order_step1_data']) && !empty($_SESSION['order_user_id'])) {

			unset($_SESSION['order_user_id']);
		}

		// Cписок содержимого корзины
		$this->page_cart->show_contents($cart['id']);


		// Флаг возможности создать полноценного юзера при заказе
		// только для админов/партнёров и незалогиненных
		$can_create_new_user = (!$this->user->data('id') || $this->user->is_admin() || $this->user->is_partner());
		$is_regular_user = $this->user->is_regular_user();

		if (!empty($_SESSION['order_user_id'])) {

			$this->add_page('user');
			$order_user = $this->page_user->get($_SESSION['order_user_id']);

			if ($order_user['group_id'] == $this->user->regular_user_group()) {

				$can_create_new_user = false;
				$is_regular_user = true;
			}
		}

		$this->add_page('ucp');

		$ucp_user_info = new request_ucp_user_info(array (
			'name'		=> 'ucp_user_info',
			'method'	=> 'post',
			'vars'		=> array (
				'first_name,<text>',
				'last_name,<text>',
				'phone,<tel>',
				'send_sms,<checkbox>',
				'phone2,<tel>',
				'email,<email>',
			),
		));
	}

	// Оформление заказа. Шаг 2. Данные по кредитке, комментарий к заказу
	// Часть проверок, общая для step1/step2, в коммоне
	public function mode_step2() {

		// Расширяем лимит времени чтобы заказ не грохнулся в процессе оформления
		set_time_limit(300);

		if (isset($_SESSION['send_ga_step1_user']) && isset($_SESSION['order_step1_data'])) {

			$this->tpl->batch('ga_step1_user', array (
				'email'	=> $_SESSION['order_step1_data']['email'],
				'fname'	=> $_SESSION['order_step1_data']['first_name'],
				'lname'	=> $_SESSION['order_step1_data']['last_name'],
				'phone'	=> $_SESSION['order_step1_data']['phone'],
				'sms'	=> (!empty($_SESSION['send_new_reservation_sms']) && $_SESSION['send_new_reservation_sms']),
			));

			unset($_SESSION['send_ga_step1_user']);
		}

		// Максимальное количество карт, на которое можно разделить платёж, и массив для их имён
		$this->max_cards = ($this->user->is_admin() ? 5 : 1);
		$cards = array();

		$this->add_page('cart');

		if (!($cart = $this->page_cart->get($this->cart_id)) || !$cart['count']) {

			if (!empty($_SESSION['order_id'])) {

				if (($order = $this->get($_SESSION['order_id']))) $this->url->go('/order/view/'.$order['id'].'/');
			}

			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "\r\n[".date("d-M-Y h:i:s T")."]---~~~--- GOING TO 404 ---~~~---\r\n", FILE_APPEND);
			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Real ip: ".getUserRealIp()."\r\n", FILE_APPEND);
			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Sess ip: ".(isset($_SESSION['user_ip']) ? $_SESSION['user_ip'] : "" )."\r\n", FILE_APPEND);
			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]From step2\r\n", FILE_APPEND);
			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Cart id: ".$this->cart_id."\r\n", FILE_APPEND);

			$this->url->go(404);
		}

		if (empty($_SESSION['order_step1_data']) || empty($_SESSION['order_user_id'])) $this->url->go('/order/step1/');

		$this->add_page('cart_item');
		$this->add_page('country');
		$this->add_page('hotel_expedia');
		$this->add_page('state');
		$this->add_page('ucp');
		$this->add_page('user');

		// Если уже был создан заказ(неоплаченный), то проверяем его наличие в бд
		if (isset($_SESSION['order_id'])) {

			$this->db->query("
				SELECT
					orders.cart_id,
					orders_states.state
				FROM
					orders
					LEFT JOIN orders_states ON orders_states.id = orders.last_state_id
				WHERE
					orders.id = ?
						AND
					orders_states.state IN (NULL, 'made')
						AND
					orders.cart_id = ?",
				$_SESSION['order_id'],
				$cart['id']
			);

			if (!($order = $this->db->fetch())) unset($_SESSION['order_id']);
		}

		// Cписок содержимого корзины
		$this->page_cart->show_contents($cart['id']);

		// Итоговая сумма заказа
		$cart_totals = $this->page_cart_item->get_cart_totals($cart['id']);
		$order_total = $cart_totals['total']['gross_with_tax_discount'];

		// Общую сумму заказа передаём в шаблон для распределения между картами
		$this->tpl('order_total', number_format($order_total, 2, '.', ''));

		// Поля для формы Step2
		$order_step2_vars = array (
			'notify,<checkbox>',
			'agreement_confirm,<checkbox>,is_required=true',
		);
		// Полный набор для заказа с ненулевой суммой
		if ($order_total != 0) {

			$order_step2_vars = array_merge($order_step2_vars, array (
				'payment_method',
				'payment_option,<select>',
			));

			if (is_mobile()) {

				$order_step2_vars = array_merge($order_step2_vars, array (
					'affiliate_id,<text>',
					'card_type',
					'card_number,<tel>',
					'card_id',
					'card_first_name,<text>',
					'card_last_name,<text>',
					'card_csc,<tel>',
					'card_expire_month,<select>',
					'card_expire_year,<select>',
					'same_billing_address,<checkbox>',
					'address,<text>',
					'city,<text>',
					'zip,<text>',
					'state_name,<text>',
					'state_id',
					'country_id,<select>,is_strict=false',
				));
			}
			else {

				$order_step2_vars = array_merge($order_step2_vars, array (
					'affiliate_id,<text>',
					'split_payment,<select>',
					'total_to_charge',
					'partial_success',
				));

				// Добавляем основную информацию о кредитках
				for ($card_id = 1; $card_id <= $this->max_cards; $card_id++) $cards[] = 'card' . $card_id . '[]';

				$order_step2_vars = array_merge($order_step2_vars, $cards);
			}
		}
		else {

			$this->tpl('zero_amount_order', true);

			$order_step2_vars = array_merge($order_step2_vars, array ('zero_amount_order'));
		}

		// Форма с данными карточки и сам процесс оплаты и завершения заказа
		$order_step2 = new request_order_step2(array (
			'name'		=> 'order_step2',
			'method'	=> 'post',
			'errors_tpl_loop_name' => 'order_step2_errors',
			'vars'		=> $order_step2_vars,
		));

		// Класс 1ого шага вызывается для получения полей с инфой о юзере
		// Пока что получается, что здесь мы не используем эти данные т.к. адреса всё равно нет
		$ucp_user_info = new request_ucp_user_info(array (
			'name'		=> 'ucp_user_info',
			'method'	=> 'post',
			'vars'		=> array (
				'first_name,<text>',
				'last_name,<text>',
				'phone,<text>',
				'phone2,<text>',
				'email,<text>',
				'address,<text>',
				'city,<text>',
				'zip,<text>',
				'state_name,<text>',
				'state_id',
				'country_id,<select>,is_strict=false',
			),
		));

		// Так же отдельно значения этих полей для текстового списка
		$user_data = $ucp_user_info->defaults;

		if ($user_data['state_id']) {

			$state = $this->page_state->get($user_data['state_id']);
			$user_data['state'] = $state['name'];
		}
		else $user_data['state'] = $user_data['state_name'];

		$country = $this->page_country->get($user_data['country_id']);
		$user_data['country'] = $country['name'];

		$this->tpl->batch('order_user', $user_data);
	}

	// Принимаем ответ от попытки оплаты через PayPal и реагируем соответственно
	public function mode_paypal_response() {

		$this->config('tpl_off', true);

		require_once($this->config('path').'/inc/paypal.php');

		$available_submodes = array (
			'return',
			'cancel'
		);

		// Поскольку этот метот акцептует оплату, то всё проверяем
		// Проверям субмод
		if (!($submode = $this->url->get_str('submode')) || !in_array($submode, $available_submodes)) {

			$this->url->go(404);
		}

		// Проверяем токен
		// Чтобы был и чтобы был похож на токен
		if (
			!isset($_GET['token'])
				||
			empty($_GET['token'])
				||
			(preg_match("/[A-Z]{2}-[0-9A-Z]+$/", $_GET['token']) != 1)
		) {

			$this->url->go(404);
		}
		// Чтобы присутствовал в таблице внешних платежей
		else {

			$this->db->query("
				SELECT
					*
				FROM
					external_payments
				WHERE
					external_id = ?",
				$_GET['token']
			);
			$db_res = $this->db->result;

			// Заодно теперь у нас есть внутренние данные внешнего платежа в массиве $external_payment
			if (!($external_payment = $this->db->fetch($db_res))) {

				alter_log('paypal_errors', '!!!Error while validating token '.$_GET['token'].' (no row in table)');
				$this->url->go(404);
			}
		}

		$delete_external_payment = false;

		switch ($submode) {

			case 'return':

				// Получаем детали оплаты, включая информацию о покупателе.
				$paypal = new Paypal();
				$checkout_details = $paypal->request('GetExpressCheckoutDetails', array('TOKEN' => $_GET['token']));

				if (!isset($checkout_details['PAYERID']) || strcmp($checkout_details['PAYERID'], $_GET['PayerID'])) {

					alter_log('paypal_errors', '!!!Error while getting approve data! Token '.$_GET['token'].' PayerID: '.$_GET['PayerID']);
					batch_to_alter_log('paypal_errors', $checkout_details);

					$this->url->go(404);
				}

				// Завершаем транзакцию
				$request_params = array(
					'PAYERID' => $_GET['PayerID'],
					'TOKEN' => $_GET['token'],
					'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
					'PAYMENTREQUEST_0_AMT' => $external_payment['order_total'],
				);

				$response = $paypal->request('DoExpressCheckoutPayment', $request_params);

				if (is_array($response) && $response['ACK'] == 'Success') {

					// Обновляем данные платежа, добавляя ID транзакции
					$this->save_payment(array(
						'paypal_payment'		=> true,
						'order_id'				=> $external_payment['order_id'],
						'last_payment_response'	=> 'Payment approved',
						'transaction_id'		=> $response['PAYMENTINFO_0_TRANSACTIONID'],
					));

					// Завершаем внутренний процесс оформления заказа, что для кредитки происходит сразу же
					$external_payment['total'] = $external_payment['order_total'];
					$this->complete_after_payment_actions($external_payment);

					$delete_external_payment = true;

					// Если уж данный человек оплатил заказ, то логиним его, чтобы он наверняка этот заказ увидел
					$this->user->logout();
					$this->user->login($external_payment['user_id']);

					$redirect = '/order/view/'.$external_payment['order_id'];
				}
				else {

					alter_log('paypal_errors', '!!!Error while approving token '.$_GET['token']);
					batch_to_alter_log('paypal_errors', $response);

					$this->url->go(404);
				}

				break;

			case 'cancel':

				$delete_external_payment = true;

				// Пока что больше ничего не делаем
				/*$this->set_state($external_payment['order_id'], 'deleted');

				// На всякий случай
				unset($_SESSION['order_id']);
				unset($_SESSION['cart_id']);

				$_SESSION['payment_cancelled'] = true;*/

				$redirect = '/cart/list/';

				break;
		}

		// Если было дейстывие, то удаляем запись внешнего платежа, чтобы по ней уже ничего не проводилось
		if ($delete_external_payment) {

			$this->db->query("
				DELETE FROM
					external_payments
				WHERE
					external_id = ?",
				$_GET['token']
			);
		}

		// Если дошли до этого места, то переменная $redirect уже заполнена
		$this->url->go($redirect);
	}

	// Страница с подтверждением заказа
	public function mode_view() {

		// Расширяем лимит времени чтобы отправка писем для новых заказов не обрывалась
		set_time_limit(300);

		$order_id = $this->url->get_int('id');

		if (
			!($order = $this->get($order_id))
				||
			in_array($order['order_state'], array('made', 'deleted'))
				||
			(
				!($this->user->is_admin() || $this->user->is_partner())
					&&
				$this->user->data('id')
					&&
				($order['user_id'] != $this->user->data('id'))
			)
				||
			(
				$this->user->is_partner()
					&&
				$this->user->data('id')
					&&
				($order['order_by_user_id'] != $this->user->data('id'))
			)
				||
			(
				!$this->user->data('id')
					&&
				!$this->url->get_str('user')
			)
		) {

			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "\r\n[".date("d-M-Y h:i:s T")."]---~~~--- GOING TO 404 ---~~~---\r\n", FILE_APPEND);
			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Real ip: ".getUserRealIp()."\r\n", FILE_APPEND);
			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Sess ip: ".(isset($_SESSION['user_ip']) ? $_SESSION['user_ip'] : "" )."\r\n", FILE_APPEND);
			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]From mode_view\r\n", FILE_APPEND);
			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Order id: ".$order_id."\r\n", FILE_APPEND);

			$this->url->go(404);
		}

		if (isset($_SESSION['order_rejected_from_fh']) && $_SESSION['order_rejected_from_fh']) {

			$this->tpl('order_rejected_from_fh', true);
		}

		if (
			!$this->user->data('id')
			&&
			$this->url->get_str('user')
		) {

			$customer_access = false;
			$partner_access = false;

			if (!strcmp($this->url->get_str('user'), md5($order['accesskey']))) $customer_access = true;
			else {

				$this->db->query("
				SELECT
					products.partner_id
				FROM
					products
					JOIN cart_items ON cart_items.product_id = products.id
				WHERE
					cart_items.cart_id = ?",
					$order['cart_id']
				);
				$db_res = $this->db->result;

				while ($partner = $this->db->fetch($db_res)) {

					if (!strcmp($this->url->get_str('user'), md5($order['accesskey'].$partner['partner_id']))) {

						// Если партнёр использует desktop-версию, то и авторизацию пройти он в состоянии
						if (!is_mobile()) $this->url->go($this->config('extranet_url'));

						$partner_access = true;

						$this->tpl('partner_limited_access', $partner['partner_id']);
					}
				}
			}

			if (!$customer_access && !$partner_access) $this->url->go(404);

			if ($customer_access) {

				$this->tpl('mobile_access_header', 'Booking Confirmation');
			}
			else if ($partner_access) {

				if (!($cancelled_item = $this->url->get_int('citem'))) {

					$this->tpl('mobile_access_header', 'New TripShock Booking');
				}
				// Если партнёр пришёл по СМС с отменённой части то заголовок другой и покажем только эту часть
				else {

					$this->tpl('mobile_access_header', 'Order Cancellation');
					$this->tpl('show_one_item_only', $cancelled_item);
				}
			}
		}


		// Если ограниченный доступ по ключу в запросе, то передаём этот ключ в шаблон для ссылки на ваучер
		if ($this->url->get_str('user')) $this->tpl('user_access_key', $this->url->get_str('user'));

		$just_made_order = false;
		if (!empty($_SESSION['google_conversion_total'])) {

			$just_made_order = true;

			$this->tpl('google_conversion_total', $_SESSION['google_conversion_total']);

			if (is_live() && (!empty($_COOKIE['jamcom']) || !empty($_COOKIE['jamtracker']))) {

				$amount = $order['total'];
				if ($this->user->is_tech_support()) $amount = 0;

				if (!empty($_COOKIE['jamcom'])) {

					$cookie_jamcom = $_COOKIE['jamcom'];

					$cookie_jamcom_parts = explode ("-", $cookie_jamcom);
					$member_id = $cookie_jamcom_parts[0];
				}

				if (!empty($_COOKIE['jamtracker'])) {

					$jamtracker = $_COOKIE['jamtracker'];
				}

				$jam_url = "http://affiliates.tripshock.com/sale"
					."/amount/".$amount
					."/trans_id/".$order['id']
					.(!empty($_COOKIE['jamcom']) ? "/member_id/".$member_id."/custom_mid/".$cookie_jamcom : "")
					.(!empty($_COOKIE['jamtracker']) ? "/tracking_id/".$jamtracker : "");

				$response = get_remote_data_curl($jam_url);

				if ($this->user->is_tech_support()) {

					log_error('jam request "'.$jam_url.'" jam_response "'.$response.'"');
				}
			}

			unset($_SESSION['google_conversion_total']);
			unset($_SESSION['order_user_id']);
			unset($_SESSION['order_id']);
		}

		$this->tpl('just_made_order', $just_made_order);

		// Содержимое заказа
		$this->add_page('cart');
		$this->page_cart->show_contents($order['cart_id'], true);

		// Данные карточки
		$this->db->query("
			SELECT
				payments.*
			FROM
				payments
				LEFT JOIN orders ON orders.id = payments.order_id
			WHERE
				orders.id = ?",
			$order['id']
		);

		if ($this->db->count() > 1) {

			$this->tpl('multiple_cards_used', 1);
		}
		else if ($payment = $this->db->fetch()) {

			$this->tpl->batch('card', $payment);
		}

		// Информация о неполном платеже
		if (($order['pay_delay_amount'] > 0) && !($order['pay_delay_received'] > 0)) {

			$pay_delay_last_day = $this->pay_delay_payment_last_day($order['id']);

			$this->tpl('pay_delay_amount', money($order['pay_delay_amount'], true));
			$this->tpl('pay_delay_last_day', format_time($pay_delay_last_day, false));
		}

		// Если для заказа есть спец. промокод - выводим его
		if ($order['special_promo_id']) {

			$this->db->query("
				SELECT
					*
				FROM
					promocodes
				WHERE
					id = ?",
				$order['special_promo_id']
			);

			if (($promocode = $this->db->fetch()) && ($promocode['uses'])) {

				$promo_amount = '';

				if ($promocode['unit'] == 0) {

					$promo_amount = $promocode['amount'].'%';
				}
				else {

					$promo_amount = money($promocode['amount']);
				}

				$this->tpl->batch('special_promo', array (
					'caption'			=> $this->config('after_order_promo_caption'),
					'code'				=> $promocode['code'],
					'min_price'			=> money($promocode['min_price']),
					'amount'			=> $promo_amount,
					'expire_in_days'	=> $this->config('after_order_promo_expire_in_days'),
				));
			}
		}

		// Содержимое заказа и информация о нём
		$this->show_order_info($order['id'], $just_made_order);

		// Если заказ только сделан, то отправляем соответствующие письма клиенту и партнёру
		if ($just_made_order) {

			$this->tpl('analytics_user_type', ($this->user->is_admin() ? 'employee' : 'traveler'));

			// Письмо с подтверждением юзеру
			$this->mail_user_order_confirmation($order['id']);

			alter_log('order_data_log', "After user mail", true, false);

			// Вступительная СМС клиенту отправляется только по его личному желанию
			if (!empty($_SESSION['send_new_reservation_sms']) && $_SESSION['send_new_reservation_sms']) {

				$link_voucher = $this->config('website_url').'/order/access/?key='.$order['accesskey'];

				if (is_portal()) {

					$external_domain = (($iframe_data = get_portal_param('iframe')) && !$iframe_data['is_disabled'])
											? $iframe_data['parent'] : null;

					$link_voucher = (is_null($external_domain)
										? $this->config('website_url').'/'
										: $external_domain.'#').'order/view/'.$order['id'].'/user-'.md5($order['accesskey']);
				}

				$this->send_sms_customer_new_reservation(
					$order['customer_phone'],
					$order['format_id'],
					$link_voucher,
					$this->get_order_products($order['id'])
				);
			}

			unset($_SESSION['send_new_reservation_sms']);

			// Письмо и СМС партнёру
			if (!(is_live() && $this->user->is_tech_support())) send_order_info_to_vendor($order['id']);

			alter_log('order_data_log', "After vendor mail", true, false);

			alter_log('order_data_log', "---~~~--- END ---~~~---", true, false);
		}
	}

	// Доступ к заказу по accesskey или логин в профиль регулярным юзерам
	public function mode_access() {

		// Полноценных юзеров отправляем сразу в профиль
		if ($this->user->is_regular_user()) $this->url->go('/ucp/profile/');

		// Временных юзеров отправляем на просмотр соответствующего заказа
		if ($this->user->is_temp_user()) {

			$this->db->query("
				SELECT
					id
				FROM
					orders
				WHERE
					user_id = ?",
				$this->user->data('id')
			);

			if ($order = $this->db->fetch()) $this->url->go('/order/view/'.$order['id'].'/');
		}

		$this->add_page('ucp');

		new request_ucp_login(array (
			'name'		=> 'ucp_login',
			'method'	=> 'post',
			'vars'		=> array (
				'email',
				'password',
			),
		));

		new request_order_access(array (
			'name'		=> 'order_access',
			'method'	=> 'post',
			'vars'		=> array (
				'access_key,<text>',
			),
		));
	}

	// Воучер
	public function mode_voucher() {

		$cart_item_id = null;
		if ($this->url->get_int('id')) $cart_item_id = $this->url->get_int('id');

		$call_from_code = (($this->url->get('from_code') !== null) ? true : false);

		if (
			!$call_from_code
				&&
			(
				!$cart_item_id
					||
				(
					!$this->user->data('id')
						&&
					!$this->url->get_str('user')
				)
			)
			) {

			$this->url->go('/order/access/');
		}

		if ($this->url->get('no_header') !== null) $this->tpl('no_header', true);

		$this->tpl->batch(array (
			'popup_mode'		=> true,
			'call_from_code'	=> $call_from_code,
			'now'				=> format_time(),
		));

		$this->add_page('photo');

		$this->db->query("
			SELECT
				cart_items.*,
				
				calendar.date,
				calendar2.date AS date_to,

				products.duration AS duration,
				products.id AS product_id,
				products.name AS product_name,
				products.agreement,
				products.address,
				products.partner_id,
				products.product_type_id,
				IF(products.phone, products.phone, partner.phone) AS product_phone,
				products.product_code,
				products.need_tickets_guests,
				products.data as product_data,

				orders.id AS order_id,
				orders.user_id,
				orders.made,
				orders.affiliate_id,
				orders.accesskey,

				orders_users_data.first_name AS user_first_name,
				orders_users_data.last_name AS user_last_name,

				partner.title AS partner_title,
				partner.address AS partner_address,
				partner.phone AS partner_phone,
				partner.phone_client AS partner_phone_client
			FROM
				cart_items
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2
				JOIN products ON products.id = cart_items.product_id
				JOIN carts ON carts.id = cart_items.cart_id
				JOIN orders ON orders.cart_id = carts.id
				JOIN orders_users_data ON orders_users_data.order_id = orders.id
				JOIN users AS partner ON partner.id = products.partner_id
			WHERE
				cart_items.id = ?
					AND
				products.affiliate_id = 0
				".($this->user->is_partner() ? "AND partner.id = ".$this->user->data('id') : "")."
				".(!$call_from_code && !$this->user->is_partner() && !$this->user->is_admin() && !$this->url->get_str('user') ? "AND orders.user_id = ".$this->user->data('id') : "")."
			",
			$cart_item_id
		);

		if ($cart_item = $this->db->fetch()) {

			if (
				(
					!$this->user->is_admin() && !$this->user->is_partner()
						&&
					in_array($cart_item['state_name'], array('cancelled', 'deleted'))
				)
					||
				(
					$this->url->get_str('user')
						&&
					strcmp($this->url->get_str('user'), md5($cart_item['accesskey']))
				)
			) {

				$this->url->go(404);
			}

			// Если заказ сделан с affiliate_id, то ваучер оформляется согласно соответствующему порталу
			if ($call_from_code && !empty($cart_item['affiliate_id'])) {

				$portal = $this->portal->get(array('affiliate_id' => $cart_item['affiliate_id']));

				$this->tpl('is_portal', true);
				$this->tpl_raw('portal_voucher_logo', $portal['voucher_logo']);
			}

			if ($pseudo_name = $this->product->get_product_pseudo_name($cart_item['product_id'], (isset($portal) ? $portal['user_id'] : null))) {

				$cart_item['product_name'] = $pseudo_name;
			}

			$book_info = $this->db->decode_data($cart_item['data']);

			$schedule_time = null;
			if ($book_info['schedule']) $schedule_time = $book_info['schedule']['time'];
			else if ($cart_item['is_fh_item']) $schedule_time = $book_info['fh_data']['fh_schedule']['time'];

			if (product_type($cart_item['product_type_id']) == 'trip') {

				$this->tpl->batch(array (
					'product_type_trip'		=> true,

					'arrival_date'			=> ($cart_item['date'] ? format_time($cart_item['date'], false, true, true) : null),
					'is_gift'				=> ($cart_item['gift_code'] && !$cart_item['date']),
					'schedule_time'			=> $schedule_time,
					'tickets'				=> format_tickets_string($book_info['tickets']),

					'product_tax'			=> $book_info['product']['tax'],
				));


				foreach ($book_info['tickets'] as $ticket) {

					$ticket_description = $ticket['description'];

					// Пропускаем билеты без описаний и баркодов
					if (
						!$ticket_description
							&&
						empty($cart_item_barcodes[$ticket['id']])
							&&
						!$cart_item['need_tickets_guests']
					) continue;

					// Выводим баркоды билета
					$barcodes_images = array();

					if (!empty($ticket['barcodes'])) {

						foreach ($ticket['barcodes'] as $barcode) {

							$barcode_image = '/index.php?page=barcode&mode=image&text='.$barcode.'&size=40';
							// Если воучер генерится для пдф, то картинки сейвим локально и потом показываем
							if ($call_from_code) {

								$barcode_image_local_file = 'barcode_'.$barcode.'.png';

								$image_data = file_get_contents($this->config('api_global_endpoint').$barcode_image);

								if (file_put_contents($this->config('path').'/../img/cache/'.$barcode_image_local_file, $image_data)) {

									$barcode_image = '/img/cache/'.$barcode_image_local_file;
								}
							}

							$barcodes_images[] = '<img src="'.$barcode_image.'" />';
						}
					}

					$this->tpl->loop('tickets_description', array (
						'name'				=> $ticket['name'],
						'description'		=> $ticket_description,
					));

					if ($cart_item['need_tickets_guests']) {

						foreach ($ticket['guests'] as $guest_number => $guest) {

							$this->tpl->loop('tickets_description.guests', array (
								'number'	=> $guest_number,
								'name'		=> $guest['first_name'].' '.$guest['last_name'],
							));
						}
					}

					foreach ($barcodes_images as $barcode_image) {

						$this->tpl->loop('tickets_description.barcode', array (), array (
							'image'		=> $barcode_image,
						));
					}
				}

				// Доп. опции
				if ($book_info['additional_options']) {

					foreach ($book_info['additional_options'] as $option) {

						$this->tpl->loop('additional_option', array (
							'option_name'	=> $option['name'],
							'value_name'	=> $option['value']['name'],
							'value_price'	=> (($option['type'] == 'select') ? money($option['value']['price'], true) : ''),
						));
					}
				}
			}
			else if (product_type($cart_item['product_type_id']) == 'hotel') {

				$this->tpl('product_tax', $book_info['product']['tax']);

				$this->add_page('hotel');

				// Картинка отеля
				$this->tpl('product_img', $this->page_photo->get_photo_link($cart_item['product_id'], 0, 't2'));

				// Даты пребывания
				$this->tpl('calendar_check_in', format_time($cart_item['date'], false, true, true));
				$this->tpl('calendar_check_out', format_time($cart_item['date_to'], false, true, true));

				// Информация по комнате и заказу
				$this->tpl('room_type', $book_info['room']['name']);
				$this->tpl('rooms', $book_info['booked']);

				$rooms_data = array();

				foreach ($book_info['rooms_data'] as $room_number => $room_data) {

					$children = '';
					if ($room_data['children'] > 0) {

						$children = ', '.$room_data['children'].' Children';

						if ($room_data['children_ages']) {

							$children .= ', Children Ages - '.implode(', ', $room_data['children_ages']);
						}
					}

					$rooms_data[] = 'Room '.$room_number.': '.$room_data['adults'].' Adults'.$children;
				}

				$this->tpl_raw('rooms_data', implode("<br />", $rooms_data));

				// Цены по дням
				$week = 1;
				$count = 1;
				foreach ($book_info['days_info'] as $date => $day_info) {

					// Проверка на начало строки с новой неделей
					$next_row = false;
					if ($count > 7) {

						$count = 1;
						$next_row = true;
						$week++;
					}

					$this->tpl->loop('days_rate', array (
						'date'		=> format_time($date, false),
						'rate'		=> money($day_info['day_rate']),
						'week'		=> $week,
						'next_row'	=> $next_row,
					));

					// Перед первой неделей выводим названия дней недели
					if ($week == 1) {

						$this->tpl->loop('week_days', array (
							'day'	=> date("D", strtotime($date)),
						));
					}

					$count++;
				}

				// Показываем номера недель если их больше одной
				if ($week > 1) $this->tpl('days_rate_show_weeks', true);

				// Включения
				foreach ($book_info['inclusions'] as $inclusion) {

					$this->tpl->loop('inclusions', array (
						'name'		=> $inclusion['name'],
						'price'		=> money($inclusion['total'], true),
					));
				}

				// Описания действующих скидок
				if (!empty($book_info['promotions'])) {

					foreach ($book_info['promotions'] as $promotion) {

						if ($promotion['description']) {

							$this->tpl->loop('promotions_description', array(), array (
								'description'	=> $promotion['description'],
							));
						}
					}
				}


				// Описание комнаты
				$this->tpl_raw('room_description', $book_info['room']['description']);
				// Телефон отеля
				$this->tpl('product_phone', $cart_item['product_phone']);
				// local confirmation number
				$this->tpl('local_confirmation', $cart_item['confirmation']);
			}

			$product_address = $cart_item['address'];

			if (!empty($cart_item['partner_id'])) {

				$this->tpl->batch('partner', array(
					'name'      => $cart_item['partner_title'],
					'address'   => !empty($cart_item['partner_address']) ? nl2br($cart_item['partner_address']) : '',
					'phone'     => !empty($cart_item['partner_phone_client']) ? $cart_item['partner_phone_client'] : (!empty($cart_item['partner_phone']) ? $cart_item['partner_phone'] : ''),
				));

				if ($cart_item['partner_address'] && !$product_address) $product_address = $cart_item['partner_address'];
			}

			$product_data = $this->db->decode_data($cart_item['product_data']);

			$this->tpl->batch(array (
				'order_number'					=> $cart_item['id'],
				'order_confirmation_number'		=> 'TRS-'.order_confirmation_number($cart_item['order_id']),
				'order_made_date'				=> format_time($cart_item['made']),
				'order_user_name'				=> $cart_item['user_first_name'].' '.$cart_item['user_last_name'],
				'total'							=> money($cart_item['total'], false),
			));

			$this->tpl->batch('product',
				array (
					'name'						=> $cart_item['product_name'],
					'duration'					=> $cart_item['duration'],
					'code'						=> $cart_item['product_code'],
					'address'					=> $product_address,
					'img'						=> $this->page_photo->get_photo_link($cart_item['product_id'], 0, 't2'),
				),
				array (
					'departure'					=> $product_data['text']['departure'],			// Departure Details
					'voucher_only_text'			=> $product_data['text']['voucher_only_text'],	// Arrival Instructions
					'included'					=> $product_data['text']['included'],			// What's Included
					'excluded'					=> $product_data['text']['excluded'],			// What to Bring
					'restrictions'				=> $product_data['text']['restrictions'],		// Restrictions
					'additional'				=> $product_data['text']['additional'],			// Additional Information
					'agreement'					=> $product_data['text']['agreement'],			// Cancellation Info
				)
			);



			// Общая для всего сайта информация.
			$this->tpl->batch('site', array(
				'name'      => $this->config('site_name'),
				'phone'     => $this->config('site_phone'),
			));
		}
		else {

			// В случае той или иной ошибки выводим сообщение и пишем отладку в файл
			$this->tpl('wrong_data', true);

			$error_log_file_name = $this->config('path').'/../backup/voucher_open_errors';
			$error_data = '['.date("Y-m-d G:i:s").'] [$cart_item_id:'.var_export($cart_item_id, true).'] [$this->user->data(\'id\'):'.var_export($this->user->data('id'), true).'] [$this->user->is_partner():'.var_export($this->user->is_partner(), true).'] [$this->user->is_admin():'.var_export($this->user->is_admin(), true).']';

			// Пишем данные
			$error_log_file = fopen($error_log_file_name, "a");
			fwrite($error_log_file, $error_data);
			fclose($error_log_file);
		}
	}
}

// Редактирование заказа
class request_order_edit extends f2b2h_request {

	protected $order;

	// Данные про заказ, нужные для валидации в каждом из итемов
	// TODO сделать валидацию общей - по всему заказу, а не по итемам
	protected $general_validation_data = array();


	protected function defaults() {

		$this->defaults = $this->page_order->get($this->page_order->id);

		$this->tpl->batch('order_edit_value', $this->defaults);


		$this->defaults['pay_delay_status'] = 'not_received';
		if ($this->defaults['pay_delay_received']) {

			$this->defaults['pay_delay_status'] = 'received';
		}


		$this->defaults['submit'] = $this->lang('save');
	}

	protected function lists() {

		$this->lists['customer_country_id'] = $this->page_country->get_list(array('view' => 1), false);

		$this->lists['pay_delay_status'] = array (
			'not_received'	=> 'Not received',
			'received'		=> 'Received',
		);
	}


	// Составляем из итемов общие данные, нужные для валидации
	public function make_additional_validation_data($cart_items) {

		// Считаем число всех заказанных билетов по каждому из расписаний
		foreach ($cart_items as $cart_item_id => $cart_item) {

			if (isset($cart_item['schedule_id']) && isset($cart_item['tickets'])) {

				$schedule_id = $cart_item['schedule_id'];

				foreach ($cart_item['tickets'] as $ticket) {

					if (!isset($ticket['remove'])) {

						if (!isset($this->general_validation_data['schedule_tickets'][$schedule_id])) {

							$this->general_validation_data['schedule_tickets'][$schedule_id] = 0;
						}

						// Ищем ощибку исчезнувшего индекса
						if (!isset($ticket['booked'])) batch_to_alter_log("missing_booked_index", $cart_items);

						$this->general_validation_data['schedule_tickets'][$schedule_id] += intval($ticket['booked']);
					}
				}
			}
		}
	}

	// Данные юзера
	// - $partner_id, используется для записи лога при изменений через апи
	protected function save_user_data($user_data, $partner_id = null) {

		$update_fields = array();

		$user_data_fields = array (
			'first_name',
			'last_name',
			'email',
			'phone',
			'phone2',
			'address',
			'city',
			'zip',
			'state_id',
			'state_name',
			'country_id',
		);

		foreach ($user_data_fields as $field) {

			$field_value = null;
			if (is_api_request() && isset($user_data['customer_'.$field])) $field_value = $user_data['customer_'.$field];
			else if (isset($user_data[$field])) $field_value = $user_data[$field];

			if ($field_value === null) continue;


			$data_field = 'customer_'.$field;

			if (isset($this->order[$data_field]) && ($this->order[$data_field] != $field_value)) {

				$update_fields[$field] = $field_value;

				$field_old_value = $this->order[$data_field];

				if ($field == 'state_id') {

					$this->add_page('state');
					if (!($state = $this->page_state->get($field_value))) continue;

					$old_state = $this->page_state->get($this->order[$data_field]);

					$field_old_value = $old_state['name'];
					$field_value = $state['name'];
				}
				if ($field == 'country_id') {

					$this->add_page('country');
					if (!($country = $this->page_country->get($field_value))) continue;

					$old_country = $this->page_country->get($this->order[$data_field]);

					$field_old_value = $old_country['name'];
					$field_value = $country['name'];
				}

				$this->log->save_order_log(
					'Changed customer\'s '.$field.' from <b>'.$field_old_value.'</b> to <b>'.$field_value.'</b>',
					$this->order['id'],
					null,
					$partner_id
				);
			}
		}

		if ($update_fields) {

			$update_fields['order_id'] = $this->order['id'];

			$this->db->insert_update_no_id(
				'orders_users_data',
				$update_fields,
				array('order_id')
			);
		}
	}


	protected function trip_item_update_cancelled_data($cart_item_id, $cart_item, $partner_id = null) {

		$this->db->query("
			SELECT
				cart_items.*,

				calendar.date,

				orders.id AS order_id,
				orders.made_from_rs,
				
				refunds.status AS refund_status
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				JOIN products ON products.id = cart_items.product_id
				LEFT JOIN refunds ON refunds.cart_item_id = cart_items.id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2
			WHERE
				cart_items.id = ?
				".($partner_id ? " AND products.partner_id = ".$partner_id : ""),
			$cart_item_id
		);

		if (!($cart_item_old = $this->db->fetch())) return;


		// Для отменённых частей
		if (
			(!is_api_request() && !empty($cart_item['state']) && ($cart_item['state'] == 'cancelled'))
				||
			(is_api_request() && ($cart_item_old['state_name'] == 'cancelled'))
			) {

			// Рефандим только TS-заказы и только если это первая попытка
			// Это здесь т.к. блок прямо связан только с отменами и тут уже есть сумма рефанда
			if (
				is_null($cart_item_old['refund_status'])
					&&
				!$cart_item_old['made_from_rs']
					&&
				isset($cart_item['refund_amount'])
			) {

				$this->db->query("
					SELECT
						data
					FROM
						payments
					WHERE
						order_id = ?",
					$cart_item_old['order_id']
				);
				$payment_data = $this->db->fetch();
				$payment_data = $this->db->decode_data($payment_data['data']);

				if (isset($payment_data['stripe']['charge_id'])) {

					$refund_response = $this->page_order->stripe_call('refund', array(
						'charge' => $payment_data['stripe']['charge_id'],
						'amount' => intval(round(floatval($cart_item['refund_amount'])*100)),
					));

					if (!$refund_response['success'] || empty($refund_response['data']['id'])) {

						alter_log('refund_fail',
							"Item ".$cart_item_old['id']." refund has been failed with message: ".
							($refund_response['success'] ? '[No ID]' : $refund_response['error'])
						);

						$refund = array (
							'cart_item_id'	=> $cart_item_old['id'],
							'status' 		=> 'fail',
						);
					}
					else {

						$refund = array (
							'cart_item_id'	=> $cart_item_old['id'],
							'status' 		=> 'success',
							'refund_id' 	=> $refund_response['data']['id'],
							'amount' 		=> $cart_item['refund_amount'],
						);
					}

					$this->db->insert_update_no_id('refunds', $refund, array('cart_item_id'));
				}
			}

			// Не пустые значения site_fee/partner_fee - записываем их в бд
			if (
				isset($cart_item['partner_fee'])
					||
				(!is_api_request() && (isset($cart_item['site_fee']) || isset($cart_item['cancel_reason'])))
				) {

				$this->page_order->set_item_fee(
					$cart_item_id,
					((isset($cart_item['site_fee']) && !is_api_request()) ? $cart_item['site_fee'] : null),
					(isset($cart_item['partner_fee']) ? $cart_item['partner_fee'] : null),
					(isset($cart_item['refund_amount']) ? $cart_item['refund_amount'] : null),
					((isset($cart_item['cancel_reason']) && !is_api_request()) ? $cart_item['cancel_reason'] : null)
				);
			}

			// Отправляем письмо клиенту и партнёру о отмене части заказа
			if (
				!empty($cart_item['send_cancellation_email'])
					||
				!empty($cart_item['send_cancellation_sms'])
					||
				// For API cancellations
				!empty($cart_item['send_sms'])
			) {

				$this->page_order->cancelled_item_send_mails(
					$cart_item_id,
					(!empty($cart_item['send_cancellation_email']) ? true : false),
					((!empty($cart_item['send_cancellation_sms']) || !empty($cart_item['send_sms'])) ? true : false)
				);
			}
		}
	}

	// - $cart_item_old, текущие параметры части корзины
	// - $arrival_date, новая дата приезда
	private function trip_item_change_arrival_date($cart_item_old, $arrival_date) {

		$this->add_page('calendar');
		$this->add_page('cart_item');

		// Получаем id нового дня
		$calendar_day = $this->page_calendar->get_day(
			null,
			$arrival_date,
			$cart_item_old['product_id']
		);

		// Если id нет, то создаём запись
		if (!$calendar_day) {

			$calendar_day_data = array (
				'product_id'	=> $cart_item_old['product_id'],
				'date'			=> $arrival_date,
			);

			$calendar_id = $this->db->insert_update('calendar', $calendar_day_data);
		}
		else $calendar_id = $calendar_day['id'];


		return $calendar_id;
	}

	protected function trip_item_valid_day($cart_item, $cart_item_old) {

		$errors = array();

		if (isset($cart_item['move_trip']) && $cart_item['move_trip']) {

			$book_info = $cart_item_old['data'];
		}
		else {

			$book_info = $this->db->decode_data($cart_item_old['data']);
		}

		$new_trip = null;
		if (isset($cart_item['trip_id']) && ($cart_item['trip_id'] !== $book_info['product']['id'])) {

			$new_trip = $this->page_trip->get($cart_item['trip_id']);

			if (!$new_trip) {

				$errors[] = 'Wrong new trip';
			}
		}

		$day_data = array (
			'in_order'				=> true,
			'is_trip_changed'		=> ($new_trip ? true : false),
			'is_day_changed'		=> ($new_trip ? true : false),
			'is_schedule_changed'	=> ($new_trip ? true : false),
			'is_inventory_changed'	=> ($new_trip ? true : false),

			'calendar_id'			=> null,
			'trip_id'				=> ($new_trip ? $new_trip['id'] : $book_info['product']['id']),
			'need_tickets_guests'	=> ($new_trip ? $new_trip['need_tickets_guests'] : $book_info['product']['need_tickets_guests']),
			'is_gift'				=> ($cart_item_old['gift_code'] ? true : false),
			'schedule_id'			=> null,
			'inventory_id'			=> null,
			'tickets'				=> array(),
			'tickets_data'			=> array(),
			'guests'				=> array(),
			'additional_options'	=> array(),

			'is_past'				=> (!empty($cart_item['is_past']) ? $cart_item['is_past'] : null),
		);

		$has_changes = ($new_trip ? true : false);


		// Дата приезда
		if ($cart_item_old['calendar_id'] || $day_data['is_gift'] || $new_trip) {

			if (is_api_request() && empty($cart_item['arrival_date']) && !$new_trip) {

				$day_data['calendar_id'] = $cart_item_old['calendar_id'];
			}
			else if (empty($cart_item['arrival_date']) && !$day_data['is_gift'] && !$new_trip) {

				$errors[] = $this->lang('valid_day_booking_day_error');

				return $errors;
			}
			else if (!empty($cart_item['arrival_date'])) {

				if (!$new_trip && (format_date_for_db($cart_item['arrival_date']) == $cart_item_old['date'])) {

					$day_data['calendar_id'] = $cart_item_old['calendar_id'];
				}
				else {

					$this->add_page('calendar');

					// Получаем id нового дня
					$calendar_day = $this->page_calendar->get_day(
						null,
						$cart_item['arrival_date'],
						$day_data['trip_id']
					);

					if (!$calendar_day) {

						$errors[] = $this->lang('valid_day_booking_day_error');

						return $errors;
					}

					$day_data['calendar_id'] = $calendar_day['id'];

					$day_data['is_day_changed'] = true;

					$has_changes = true;
				}
			}
		}


		if (!$new_trip) {

			// Расписание
			if (is_api_request() && empty($cart_item['schedule_id'])) {

				if ($book_info['schedule']) $day_data['schedule_id'] = $book_info['schedule']['id'];
			}
			else if ($book_info['schedule'] && empty($cart_item['schedule_id'])) {

				$errors[] = $this->lang('valid_day_booking_schedule_error');

				return $errors;
			}
			else if ($book_info['schedule']) {

				$day_data['schedule_id'] = $cart_item['schedule_id'];

				if ($cart_item['schedule_id'] != $book_info['schedule']['id']) {

					$day_data['is_schedule_changed'] = true;

					$has_changes = true;
				}
				// Если даже расписание не поменялось, но поменялся день, то валидация нужна т.к. доступность уже другая
				else if ((isset($day_data['is_day_changed'])) && ($day_data['is_day_changed'] == true)) {

					$day_data['is_schedule_changed'] = true;
				}
			}


			// Inventory
			$find_new_duration = false;
			if (is_api_request() && empty($cart_item['inventory_id'])) {

				if ($book_info['inventory']) {

					$day_data['inventory_id'] = $book_info['inventory']['id'];
					// Если лодка в заказе есть и не изменилась, то её duration для нас эталон
					$day_data['trip_duration'] = $book_info['inventory']['duration'];
				}
			}
			else if ($book_info['inventory'] && empty($cart_item['inventory_id'])) {

				$errors[] = $this->lang('valid_day_booking_inventory_error');

				return $errors;
			}
			else if ($book_info['inventory']) {

				$day_data['inventory_id'] = $cart_item['inventory_id'];

				if ($cart_item['inventory_id'] != $book_info['inventory']['id']) {

					$day_data['is_inventory_changed'] = true;
					$has_changes = true;

					$find_new_duration = true;
				}
				else {

					// Если лодка не менялась, то её duration для нас эталон,
					// либо из заказа, либо из БД если поменялось расписание
					if (isset($day_data['is_schedule_changed']) && ($day_data['is_schedule_changed'] == true)) {

						$find_new_duration = true;
					}
					else {

						$day_data['trip_duration'] = $book_info['inventory']['duration'];
					}

					// Если даже лодка не поменялась, но поменялся день, то валидация нужна т.к. доступность уже другая
					if (isset($day_data['is_day_changed']) && ($day_data['is_day_changed'] == true)) {

						$day_data['is_inventory_changed'] = true;

						$find_new_duration = true;
					}
				}
			}

			// Если изменилась лодка либо её положение в календарной сетке,
			// то берём её календарный duration в качестве trip_duration
			if ($find_new_duration) {

				$this->add_page('calendar');

				$calendar_day = $this->page_calendar->get_day($day_data['calendar_id']);

				$inventory_day_info = $this->page_calendar->get_days_inventory_info(
					$day_data['trip_id'],
					$day_data['inventory_id'],
					$day_data['schedule_id'],
					$calendar_day['date']
				);

				if ($inventory_day_info) {

					$inventory_day_info = array_shift($inventory_day_info);
					$day_data['trip_duration'] = $inventory_day_info['duration'];
				}
			}
		}
		else {

			if (!empty($cart_item['schedule_id'])) $day_data['schedule_id'] = $cart_item['schedule_id'];
			if (!empty($cart_item['inventory_id'])) $day_data['inventory_id'] = $cart_item['inventory_id'];
		}


		// Билеты
		if (!empty($cart_item['tickets'])) {

			foreach ($cart_item['tickets'] as $old_ticket_id => $ticket) {

				if (
					!empty($ticket['remove'])
						||
					(
						empty($ticket['ticket_id'])
							&&
						empty($ticket['id'])
					)
						||
					empty($ticket['booked'])
				) {

					continue;
				}

				$ticket_id = (empty($ticket['ticket_id']) ? $ticket['id'] : $ticket['ticket_id']);

				$ticket_price = 0;
				$already_booked = 0;

				if (
					!$new_trip
						&&
					!empty($book_info['tickets'][$ticket_id])
						&&
					empty($day_data['is_schedule_changed'])
				) {

					$already_booked = $book_info['tickets'][$ticket_id]['booked'];

					$ticket_price = $book_info['tickets'][$ticket_id]['price'];
				}
				else if (!empty($ticket['price'])) {

					$ticket_price = $ticket['price'];
				}

				$booked_difference = $ticket['booked'] - $already_booked;


				$day_data['tickets'][$ticket_id] = $booked_difference;
				$day_data['tickets_data'][$ticket_id]['total'] = $ticket['booked'];
				$day_data['tickets_data'][$ticket_id]['price'] = $ticket_price;


				if (!empty($ticket['guests'])) {

					$day_data['guests'][$ticket_id] = $ticket['guests'];
				}
			}

			$has_changes = true;
		}

		// Опции
		if (!empty($cart_item['additional_options'])) {

			foreach ($cart_item['additional_options'] as $option) {

				if (
					!empty($option['remove'])
						||
					empty($option['id'])
						||
					empty($option['value_id'])
					) {

					continue;
				}

				$day_data['additional_options'][$option['id']] = $option['value_id'];
			}

			$has_changes = true;
		}


		if ($has_changes) {

			$this->add_page('trip');

			// Некоторые общие данные заказа для правильной валидации
			if ($this->general_validation_data) {

				$day_data['for_validation'] = $this->general_validation_data;
			}

			// Нужно для получения некоторых спец. разрешений по лодкам с учётом длительностей
			$day_data['old_cart_item'] = $book_info;
			$day_data['cart_item_id'] = $cart_item_old['id'];

			$errors = $this->page_trip->valid_day_booking($day_data);
		}

		return $errors;
	}

	protected function trip_item_change_product($cart_item, $cart_item_old) {

		$this->add_page('cart_item');

		// Возвращаем старые проданные
		if ($cart_item_old['calendar_id']) {

			$this->page_cart_item->restore_trip_tickets($cart_item_old['id']);
		}


		$trip = $this->page_trip->get($cart_item['trip_id'], true);


		// Получаем calendar.id нового дня
		$calendar_id = null;

		if (!empty($cart_item['arrival_date'])) {

			$this->add_page('calendar');

			$calendar_day = $this->page_calendar->get_day(
				null,
				$cart_item['arrival_date'],
				$trip['id']
			);

			$calendar_id = $calendar_day['id'];
		}


		// Немного переформируем массивы билетов, опций и (если нужно) имён гостей
		$tickets = array();
		$guests = array();

		if (!empty($cart_item['tickets'])) {

			foreach ($cart_item['tickets'] as $ticket_id => $ticket) {

				$tickets[$ticket_id] = $ticket['booked'];

				if ($trip['need_tickets_guests']) $guests[$ticket_id] = $ticket['guests'];
			}
		}

		$additional_options = array();

		if (!empty($cart_item['additional_options'])) {

			foreach ($cart_item['additional_options'] as $option_id => $option) {

				// select опция
				if (!empty($option['value_id'])) {

					$additional_options[$option_id] = $option['value_id'];
				}
				// text опция
				else if (!empty($option['value'])) {

					$additional_options[$option_id] = $option['value'];
				}
			}
		}


		$book_info = $this->page_trip->format_cart_item_data(array(
			'calendar_id'			=> $calendar_id,
			'trip_id'				=> $trip['id'],
			'schedule_id'			=> (!empty($cart_item['schedule_id']) ? $cart_item['schedule_id'] : null),
			'inventory_id'			=> (!empty($cart_item['inventory_id']) ? $cart_item['inventory_id'] : null),
			'booking_fee'			=> (!empty($cart_item['booking_fee']) ? $cart_item['booking_fee'] : null),
			'tickets'				=> $tickets,
			'guests'				=> $guests,
			'additional_options'	=> $additional_options,
		));

		// Берём индекс 0 т.к. конкретно тут не предполагается мультилодок (пока что)
		$book_info = array_values_to_string($book_info);

		$this->db->insert_update('cart_items', array(
			'id'			=> $cart_item_old['id'],
			'calendar_id'	=> $calendar_id,
			'product_id'	=> $trip['id'],
			'data'			=> $this->db->encode_data($book_info[0]),
		));

		$this->cache->clear();


		$this->page_cart_item->reduce_trip_tickets($cart_item_old['id']);


		$book_info_old = $this->db->decode_data($cart_item_old['data']);

		// Запись в лог
		$this->log->save_order_log(
			'Changed item product from <b>'.$book_info_old['product']['name'].'</b> to <b>'.$trip['name'].'</b>',
			$cart_item_old['order_id'],
			$cart_item_old['id']
		);
	}

	// Изменения в суммах заказанного и составе билетов/опций
	// - $cart_item, пришедшие параметры части корзины
	protected function trip_item_check_content($cart_item_id, $cart_item, $partner_id = null) {

		$this->add_page('cart');
		$this->add_page('cart_item');
		$this->add_page('trip');

		// Метод API в котором редактируется только корзина, а заказа ещё вообще нет
		$cart_edit_only = is_mode('admin_edit_cart');

		if ($this->user->is_partner()) $partner_id = $this->user->data('id');

		$this->db->query("
			SELECT
				cart_items.*,

				".($cart_edit_only ? "" : "orders.id AS order_id,")."

				calendar.date
			FROM
				cart_items
				".($cart_edit_only ? "" : "JOIN orders ON orders.cart_id = cart_items.cart_id")."
				JOIN products ON products.id = cart_items.product_id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2
			WHERE
				cart_items.id = ?
				".($partner_id ? " AND products.partner_id = ".$partner_id : ""),
			$cart_item_id
		);

		if (!($cart_item_old = $this->db->fetch())) return;

		if (
			(
				($cart_item_old['state_name'] != 'completed')
					&&
				!$this->user->is_permitted('order_edit')
					&&
				!is_api_request()
					&&
				!$this->user->is_partner()
			)
				||
			(
				($cart_item_old['state_name'] == 'completed')
					&&
				!$this->page_order->is_user_permitted_to_edit_state($cart_item_old['state_name'])
			)
			) {

			return;
		}


		$book_info = $this->db->decode_data($cart_item_old['data']);

		if (isset($cart_item['comment_partner']) && strcmp($cart_item['comment_partner'], $book_info['comments']['partner'])) {

			$this->log->save_order_log(
				'Changed partner comment from <b>'.$book_info['comments']['partner'].'</b> to <b>'.$cart_item['comment_partner'].'</b>',
				$cart_item_old['order_id'],
				$cart_item_old['id']
			);

			$book_info['comments']['partner'] = $cart_item['comment_partner'];
		}

		$need_to_change_sold = false;


		// Остальное только админу или при запросе с апи партнёрки
		// Также не обрабатываем изменения в данных отменённых/удалённых частей
		// (актуально только для TS, а в RS - только для фичи Move trip)
		// Также не обрабатываем части, связанные с Fareharbor
		if (
			(
				is_api_request()
					&&
				!(
					isset($cart_item['move_trip'])
						&&
					$cart_item['move_trip']
						&&
					in_array($cart_item_old['state_name'], array('cancelled','deleted'))
				)
			)
				||
			(
				!(
					isset($cart_item['state'])
						&&
					in_array($cart_item['state'], array('cancelled','deleted'))
						&&
					in_array($cart_item_old['state_name'], array('cancelled','deleted'))
				)
					&&
				$this->user->is_admin()
					&&
				!$cart_item['is_fh_item']
			)
			) {

			// Этот спец. флаг явно задействован только в реквесте request_api_rs_order_edit
			if (isset($cart_item['move_trip'])) unset($cart_item['move_trip']);

			$errors = $this->trip_item_valid_day($cart_item, $cart_item_old);

			if ($errors) return $errors;


			if (
				!empty($cart_item['trip_id'])
					&&
				($cart_item['trip_id'] != $book_info['product']['id'])
					&&
				!is_page('api_mobile')
			) {

				$this->trip_item_change_product($cart_item, $cart_item_old);

				return;
			}


			// Последний параметр заставляет игнорировать данные FH т.к. тут могут только старые части таких трипов м.б.
			$trip = $this->page_trip->get($cart_item_old['product_id'], true, false, null, true);

			$trip_schedules = $trip['schedules'];
			$trip_inventories = $trip['inventories'];
			$trip_tickets = $trip['tickets'];
			$trip_options = $trip['additional_options'];

			$arrival_date = null;
			$new_arrival_date = null;
			if (!empty($cart_item['arrival_date'])) $arrival_date = format_date_for_db($cart_item['arrival_date']);


			if (
				$arrival_date
					&&
				($cart_item_old['date'] || $cart_item_old['gift_code'])
					&&
				($arrival_date != $cart_item_old['date'])
				) {

				// Записываем в лог
				$this->log->save_order_log(
					'Changed arrival date '.($cart_item_old['date'] ? 'from <b>'.format_time($cart_item_old['date'], false).'</b>' : '').' to <b>'.format_time($arrival_date, false).'</b>',
					$this->order['id'],
					$cart_item_old['id']
				);

				if ($new_arrival_date = $this->trip_item_change_arrival_date($cart_item_old, $arrival_date)) {

					$need_to_change_sold = true;
				}
			}


			// Расписание
			if (
				$book_info['schedule'] && !empty($cart_item['schedule_id'])
					&&
				!empty($trip_schedules[$cart_item['schedule_id']])
					&&
				($book_info['schedule']['id'] != $cart_item['schedule_id'])
				) {

				$new_schedule = $trip_schedules[$cart_item['schedule_id']];

				$this->log->save_order_log(
					'Changed schedule from <b>'.$book_info['schedule']['time'].'</b> to <b>'.$new_schedule['time'].'</b>',
					$cart_item_old['order_id'],
					$cart_item_old['id']
				);

				$book_info['schedule'] = array(
					'id' => $new_schedule['id'],
					'time' => $new_schedule['time'],
				);

				$need_to_change_sold = true;
			}


			$tickets_was_checked = false;
			// Tickets and Options
			// API не редактирует заказы TS || Но редактирует заказы RS (кроме моб. апи) || Редактирует корзину без заказа
			if (!is_api_request() || ($this->order['made_from_rs'] && !is_page('api_mobile')) || $cart_edit_only) {

				$tickets_was_checked = true;

				// В $tickets_old массив со старыми билетами
				$tickets_old = $book_info['tickets'];
				// В $tickets_new новые. Если изменений не было - будут совпадать
				$tickets_new = $tickets_old;


				// На случай, если изменилось Inventory (определяется ниже) и при этом изменились ещё и билеты,
				// нужно заранее определить длительность по этим изменённым билетам
				$trip_duration = null;

				// Обрабатываем пришедшие параметры билетов
				if (!empty($cart_item['tickets'])) {

					foreach ($cart_item['tickets'] as $ticket_id => $ticket) {

						$ticket_info = array();

						// Новый билет
						if (
							!empty($ticket['id'])
								&&
							($ticket_id == $ticket['id'])
								&&
							!array_key_exists($ticket['id'], $tickets_old)
								&&
							array_key_exists($ticket['id'], $trip_tickets)
							) {

							$ticket_data = $trip_tickets[$ticket['id']];

							// Длительность определяется по первому билету т.к. валидация тут уже прошла успешно
							if (
								is_null($trip_duration)
									&&
								!empty($ticket_data['duration'])
									&&
								// Те, которых нет по сути, на длительность не повлияют
								($ticket['booked'] != 0)
							) {

								$trip_duration = $ticket_data['duration'];
							}

							$ticket_price = $ticket_data['price'];
							if ($this->order['made_from_rs']) $ticket_price = $ticket_data['price_rs'];

							$ticket_comission = $ticket_data['comission'];
							if ($this->order['made_from_rs']) $ticket_comission = 0;

							$ticket_wholesale = $ticket_data['wholesale'];
							if ($this->order['made_from_rs']) $ticket_wholesale = 0;

							$ticket_info = array (
								'id'			=> $ticket['id'],
								'name'			=> $ticket_data['name'],
								'rs_internal'	=> ($ticket_data['rs_internal'] ? true : false),
								'description'	=> $ticket_data['description'],

								'comission'		=> (isset($ticket['comission']) ? $ticket['comission'] : $ticket_comission),
								'wholesale'		=> (isset($ticket['wholesale']) ? $ticket['wholesale'] : $ticket_wholesale),
								'price'			=> (isset($ticket['price']) ? $ticket['price'] : $ticket_price),

								'booked'		=> (isset($ticket['booked']) ? $ticket['booked'] : 0),

								'barcodes'		=> array(),
								'guests'		=> (isset($ticket['guests']) ? $ticket['guests'] : array()),
							);


							$need_to_change_sold = true;


							$this->log->save_order_log(
								'Added new ticket <b>'.$ticket_info['name'].'</b> with price <b>'.$ticket_info['price'].'</b> and booked <b>'.$ticket_info['booked'].'</b>',
								$cart_item_old['order_id'],
								$cart_item_old['id']
							);
						}
						else if (array_key_exists($ticket_id, $tickets_old)) {

							// Инфа билета из бд
							$ticket_info = $tickets_old[$ticket_id];

							// Длительность определяется по первому билету т.к. валидация тут уже прошла успешно
							if (
								is_null($trip_duration)
									&&
								// Удалённые билеты уже на длительность не влияют
								empty($ticket['remove'])
									&&
								(
									!empty($trip_tickets[$ticket_id]['duration'])
										||
									// Это если поменялся тип билета
									(
										!empty($ticket['id'])
											&&
										!empty($trip_tickets[$ticket['id']]['duration'])
									)
								)
									&&
								// Так же, как и те, которых нет по сути
								isset($ticket['booked'])
									&&
								($ticket['booked'] != 0)
							) {

								// Если тип билета поменялся, то и длительность молга поменяться
								if (!empty($ticket['id']) && !empty($trip_tickets[$ticket['id']]['duration'])) {

									$trip_duration = $trip_tickets[$ticket['id']]['duration'];
								}
								else {

									$trip_duration = $trip_tickets[$ticket_id]['duration'];
								}
							}

							// Удаляем билет пропуская его
							if (!empty($ticket['remove'])) {

								$this->log->save_order_log(
									'Ticket <b>'.$ticket_info['name'].'</b> has been removed',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								unset($tickets_new[$ticket_id]);

								$need_to_change_sold = true;

								continue;
							}


							// Изменился тип билета
							if (
								!empty($ticket['id'])
									&&
								($ticket['id'] != $ticket_id)
									&&
								array_key_exists($ticket['id'], $trip_tickets)
								) {

								unset($tickets_new[$ticket_id]);

								$new_ticket = $trip_tickets[$ticket['id']];

								$this->log->save_order_log(
									'Changed ticket type from <b>'.$ticket_info['name'].'</b> to <b>'.$new_ticket['name'].'</b>',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								$ticket_info['id'] = $ticket['id'];
								$ticket_info['name'] = $new_ticket['name'];
								$ticket_info['description'] = $new_ticket['description'];
								$ticket_info['duration'] = $new_ticket['duration'];

								$ticket_id = $ticket_info['id'];

								$need_to_change_sold = true;
							}

							// Изменилось кол-во заказанных билетов
							if (isset($ticket['booked']) && ($ticket['booked'] != $ticket_info['booked'])) {

								// Если изменилось колличество билетов, то меняем и количество гостей в исходном массиве
								// Это нужно, чтобы следующая проверка по гостям выполнялась корректно
								if (isset($ticket['guests'])) {

									if ($ticket['booked'] < $ticket_info['booked']) {

										for ($ticket_number = ($ticket['booked'] + 1); $ticket_number <= $ticket_info['booked']; $ticket_number++) {

											unset($ticket_info['guests'][$ticket_number]);
										}
									}
									else {

										for ($ticket_number = ($ticket_info['booked'] + 1); $ticket_number <= $ticket['booked']; $ticket_number++) {

											$ticket_info['guests'][$ticket_number] = $ticket['guests'][$ticket_number];
										}
									}
								}


								if (!$cart_edit_only) {

									$this->log->save_order_log(
										'Changed tickets count from <b>' . $ticket_info['booked'] . '</b> to <b>' . $ticket['booked'] . '</b> for ticket <b>' . $ticket_info['name'] . '</b>',
										$cart_item_old['order_id'],
										$cart_item_old['id']
									);
								}

								$ticket_info['booked'] = $ticket['booked'];

								$need_to_change_sold = true;
							}

							// Изменились имена гостей в билетах
							if (isset($ticket['guests'])) {

								foreach ($ticket['guests'] as $ticket_number => $guest) {

									if (
										!array_key_exists($ticket_number, $ticket_info['guests'])
											||
										(strcmp($guest['first_name'], $ticket_info['guests'][$ticket_number]['first_name']) != 0)
											||
										(strcmp($guest['last_name'], $ticket_info['guests'][$ticket_number]['last_name']) != 0)
										) {

										$old_first_name = '';
										if (!empty($ticket_info['guests'][$ticket_number]['first_name'])) {

											$old_first_name = $ticket_info['guests'][$ticket_number]['first_name'];
										}

										$old_last_name = '';
										if (!empty($ticket_info['guests'][$ticket_number]['last_name'])) {

											$old_last_name = $ticket_info['guests'][$ticket_number]['last_name'];
										}

										$this->log->save_order_log(
											'Changed ticket #'.$ticket_number.' guest from <b>'.$old_first_name.
											' '.$old_last_name.'</b> to <b>'.$guest['first_name'].' '.$guest['last_name'].
											'</b> for ticket <b>'.$ticket_info['name'].'</b>',
											$cart_item_old['order_id'],
											$cart_item_old['id']
										);

										$ticket_info['guests'][$ticket_number]['first_name'] = $guest['first_name'];
										$ticket_info['guests'][$ticket_number]['last_name'] = $guest['last_name'];
									}
								}
							}

							// Изменилась стоимость билета
							if (isset($ticket['price']) && ($ticket['price'] != $ticket_info['price'])) {

								if (!$cart_edit_only) {

									$this->log->save_order_log(
										'Changed ticket price from <b>' . $ticket_info['price'] . '</b> to <b>' . $ticket['price'] . '</b> for ticket <b>' . $ticket_info['name'] . '</b>',
										$cart_item_old['order_id'],
										$cart_item_old['id']
									);
								}

								$ticket_info['price'] = $ticket['price'];
							}

							// Изменение процента комиссии и оптовой цены не доступно для RS
							if (!is_api_request()) {

								// Изменилась закупочная цена билета
								if (isset($ticket['wholesale']) && ($ticket['wholesale'] != $ticket_info['wholesale'])) {

									$this->log->save_order_log(
										'Changed ticket wholesale from <b>'.$ticket_info['wholesale'].'</b> to <b>'.$ticket['wholesale'].'</b> for ticket <b>'.$ticket_info['name'].'</b>',
										$cart_item_old['order_id'],
										$cart_item_old['id']
									);

									$ticket_info['wholesale'] = $ticket['wholesale'];
								}

								// Изменился процент комиссии билета
								if (
									isset($ticket['comission'])
										&&
									(empty($ticket_info['comission']) || ($ticket['comission'] != $ticket_info['comission']))
									) {

									if (!empty($ticket_info['comission'])) {

										$this->log->save_order_log(
											'Changed tickets margin from <b>'.$ticket_info['comission'].'</b> to <b>'.$ticket['comission'].'</b>',
											$cart_item_old['order_id'],
											$cart_item_old['id']
										);
									}

									$ticket_info['comission'] = $ticket['comission'];
								}
							}


						}

						// Сохраняем очередной билет с новыми(или старыми) параметрами
						if ($ticket_info) $tickets_new[$ticket_id] = $ticket_info;
					}
				}

				// Обновляем массив с билетами
				$book_info['tickets'] = $tickets_new;


				// Текущие доп. опции
				$additional_options_old = $book_info['additional_options'];
				// Новый массив с опциями
				$additional_options = $additional_options_old;

				if (!empty($cart_item['additional_options'])) {

					// Обрабатываем пришедшие параметры доп. опций
					foreach ($cart_item['additional_options'] as $option_id => $option) {

						$option_current = array();

						// Новая опция
						if (
							!empty($option['id'])
								&&
							($option_id == $option['id'])
								&&
							!array_key_exists($option['id'], $additional_options)
								&&
							array_key_exists($option['id'], $trip_options)
							) {

							$option_data = $trip_options[$option['id']];

							if ($option_data['type'] == 'select') {

								if (
									empty($option['value_id'])
										||
									!array_key_exists($option['value_id'], $option_data['values'])
									) continue;


								$value_data = $option_data['values'][$option['value_id']];

								$price = $value_data['price'];
								if (isset($option['price'])) $price = $option['price'];

								$comission = $value_data['comission'];
								if ($this->order['made_from_rs']) $comission = 0;
								else if (isset($option['price'])) $price = $option['price'];

								$apply_tax = $value_data['apply_tax'];
								if (isset($option['apply_tax'])) $apply_tax = $option['apply_tax'];


								$option_current = array (
									'id'		=> $option_data['id'],
									'name'		=> $option_data['name'],
									'type'		=> 'select',
									'value'		=> array (
										'id'		=> $value_data['id'],
										'name'		=> $value_data['name'],
										'price'		=> $price,
										'comission'	=> $comission,
										'apply_tax'	=> $apply_tax,
									),
								);
							}
							else {

								if (empty($option['value'])) continue;


								$option_current = array (
									'id'		=> $option_data['id'],
									'name'		=> $option_data['name'],
									'type'		=> 'text',
									'value'		=> array (
										'id'		=> null,
										'name'		=> $option['value'],
										'price'		=> null,
										'comission'	=> null,
										'apply_tax'	=> null,
									),
								);
							}


							$this->log->save_order_log(
								'Added new option <b>'.$option_current['name'].'</b> with value <b>'.$option_current['value']['name'].'</b>'
									.(($option_current['type'] == 'select') ? ' and price <b>'.$option_current['value']['price'].'</b>' : ''),
								$cart_item_old['order_id'],
								$cart_item_old['id']
							);
						}
						else if (array_key_exists($option_id, $additional_options_old)) {

							$option_current = $additional_options_old[$option_id];
							$value_current = $option_current['value'];

							// Удаляем опцию
							if (!empty($option['remove'])) {

								$this->log->save_order_log(
									'Option <b>'.$option_current['name'].'</b> with value <b>'.$option_current['value']['name'].'</b> has been removed',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								unset($additional_options[$option_id]);

								$option_current = array();
							}
							else if (!empty($option['id'])) {

								$option_changed = false;


								if (
									(($option_id == $option['id']) && ($option_current['type'] == 'select'))
										||
									(($option_id != $option['id']) && ($trip_options[$option['id']]['type'] == 'select'))
									) {

									$option['type'] = 'select';

									unset($option['value']);
								}
								else {

									$option['type'] = 'text';

									unset($option['value_id']);
									unset($option['price']);
									unset($option['comission']);
									unset($option['apply_tax']);
								}


								// Изменилась опция
								// Здесь и дальше игнорируется ситуация, когда пришла пустая текстовая опция
								if (
									(
										($option['id'] != $option_current['id'])
											&&
										array_key_exists($option['id'], $trip_options)
									)
										&&
									!(
										($option['type'] == 'text')
											&&
										empty($option['value'])
									)
									) {

									unset($additional_options[$option_id]);

									$new_option = $trip_options[$option['id']];

									$this->log->save_order_log(
										'Changed option from <b>'.$option_current['name'].'</b> to <b>'.$new_option['name'].'</b>',
										$cart_item_old['order_id'],
										$cart_item_old['id']
									);

									$option_current['id'] = $new_option['id'];
									$option_current['name'] = $new_option['name'];

									$option_id = $option_current['id'];
									$option_changed = true;
								}

								// Что отслеживать в изменениях значений опции зависит от того,
								// какой у опции тип, и было ли изменение типа

								// Был и остался селект (наиболее частый случай)
								if (($option_current['type'] == 'select') && ($option['type'] == 'select')) {

									// Изменилось значение опции
									if (
										isset($option['value_id'])
											&&
										($option['value_id'] != $value_current['id'])
											&&
										!empty($trip_options[$option_current['id']]['values'][$option['value_id']])
										) {

										$new_value = $trip_options[$option_current['id']]['values'][$option['value_id']];

										$this->log->save_order_log(
											'Changed value from <b>'.$value_current['name'].'</b> to <b>'.$new_value['name'].'</b> for option <b>'.$option_current['name'].'</b>',
											$cart_item_old['order_id'],
											$cart_item_old['id']
										);

										$option_current['value']['id'] = $new_value['id'];
										$option_current['value']['name'] = $new_value['name'];
									}

									if (isset($option['price']) && ($option['price'] != $value_current['price'])) {

										$this->log->save_order_log(
											'Changed option value price <b>'.$value_current['price'].'</b> to <b>'.$option['price'].'</b> for option <b>'.$option_current['name'].'</b>',
											$cart_item_old['order_id'],
											$cart_item_old['id']
										);

										$option_current['value']['price'] = $option['price'];
									}

									if (isset($option['apply_tax']) && ($option['apply_tax'] != $value_current['apply_tax'])) {

										$this->log->save_order_log(
											'Changed option value apply_tax from <b>'.$value_current['apply_tax'].'</b> to <b>'.$option['apply_tax'].'</b> for option <b>'.$option_current['name'].'</b>',
											$cart_item_old['order_id'],
											$cart_item_old['id']
										);

										$option_current['value']['apply_tax'] = $option['apply_tax'];
									}

									// Изменение комиссии билета недоступно для RS
									if (!is_api_request()) {

										if (isset($option['comission']) && ($option['comission'] != $value_current['comission'])) {

											$this->log->save_order_log(
												'Changed option margin from <b>'.$value_current['comission'].'</b> to <b>'.$option['comission'].'</b>',
												$cart_item_old['order_id'],
												$cart_item_old['id']
											);

											$option_current['value']['comission'] = $option['comission'];
										}
									}
								}

								// Была и осталась текстовая опция (сравниваем строковое значение)
								if (($option_current['type'] == 'text') && ($option['type'] == 'text')) {

									if (!empty($option['value']) && strcmp($option['value'], $value_current['name'])) {

										$this->log->save_order_log(
											'Changed option value from <b>'.$value_current['name'].'</b> to <b>'.$option['value'].'</b> for option <b>'.$option_current['name'].'</b>',
											$cart_item_old['order_id'],
											$cart_item_old['id']
										);

										$option_current['value']['name'] = $option['value'];
									}
								}
								// Был селект, а стала текстовая
								if (($option_current['type'] == 'select') && ($option['type'] == 'text') && !empty($option['value'])) {

									$this->log->save_order_log(
										'Changed option value from <b>'.$option_current['value']['name'].'</b> to <b>'.$option['value'].'</b> for option <b>'.$option_current['name'].'</b>',
										$cart_item_old['order_id'],
										$cart_item_old['id']
									);

									$option_current['value'] = array (
										'id'		=> null,
										'name'		=> $option['value'],
										'price'		=> null,
										'comission'	=> null,
										'apply_tax'	=> null,
									);
								}
								// Была текстовая, а стал селект (строка value заменяется на массив)
								if (($option_current['type'] == 'text') && ($option['type'] == 'select')) {

									$this->log->save_order_log(
										'Changed option value from <b>'.$option_current['value']['name'].'</b> to <b>'.$trip_options[$option['id']]['values'][$option['value_id']]['name'].'</b> for option <b>'.$option_current['name'].'</b>',
										$cart_item_old['order_id'],
										$cart_item_old['id']
									);

									$option_current['value'] = array (
										'id'		=> $option['value_id'],
										'name'		=> $trip_options[$option['id']]['values'][$option['value_id']]['name'],
										'price'		=> $option['price'],
										'comission'	=> $option['comission'],
										'apply_tax'	=> $option['apply_tax'],
									);
								}
							}
						}


						if ($option_current) $additional_options[$option_id] = $option_current;
					}
				}


				// Обновляем массив с опциями
				$book_info['additional_options'] = $additional_options;
			}


			// Inventory
			if (
				!is_page('api_mobile')
					&&
				$book_info['inventory'] && !empty($cart_item['inventory_id'])
					&&
				!empty($trip_inventories[$cart_item['inventory_id']])
					&&
				($book_info['inventory']['id'] != $cart_item['inventory_id'])
			) {

				$new_inventory = $trip_inventories[$cart_item['inventory_id']];

				$this->log->save_order_log(
					'Changed equipment from <b>'.$book_info['inventory']['name'].'</b> to <b>'.$new_inventory['name'].'</b>',
					$cart_item_old['order_id'],
					$cart_item_old['id']
				);

				$book_info['inventory'] = array(
					'id'			=> $new_inventory['id'],
					'name'			=> $new_inventory['name'],
					'duration'		=> ($tickets_was_checked ? $trip_duration : $book_info['inventory']['duration']),
					'description'	=> $new_inventory['description'],
				);

				$need_to_change_sold = true;
			}

			// Даже если лодка не поменялась, её duration могла измениться на основе изменений в билетах
			if (
				$book_info['inventory']
					&&
				$tickets_was_checked
			) {

				$book_info['inventory']['duration'] = $trip_duration;
			}



			if (!$cart_edit_only) {

				if (!empty($cart_item['state_deduct']) && ($cart_item['state_deduct'] != 'null')) {

					//Находим id user поратала, если он есть
					$this->db->query("
						SELECT
							portal.user_id AS parent_id
						FROM
							portal
							JOIN orders ON orders.affiliate_id = portal.affiliate_id
						WHERE
							orders.affiliate_id != ''
								AND
							orders.id = ?",
						$cart_item_old['order_id']
					);

					$portal_user = $this->db->fetch();


					$this->db->insert_update_no_id('deduct',
						array (
							'cart_item_id' => $cart_item_id,
							'state_name' => $cart_item['state_deduct'],
							'amount' => $cart_item['amount_deduct'],
							'partner_id' => $trip['partner_id'],
							'partner_portal_id' => (!empty($portal_user) ? $portal_user['parent_id'] : 0),
						),
						array('cart_item_id')
					);
				}
				else {

					$this->db->delete('deduct', array(
						'cart_item_id' => $cart_item_id,
					));
				}
			}
		}



		// Прямое изменение booking_fee
		if (
			isset($cart_item['booking_fee'])
				&&
			strcmp(strval($book_info['product']['booking_fee']), strval($cart_item['booking_fee']))
		) {

			// Записываем в лог
			if (!$cart_edit_only) {

				$this->log->save_order_log(
					'Changed booking fee from <b>' . $book_info['product']['booking_fee'] . '</b> to <b>' . $cart_item['booking_fee'] . '</b>',
					$this->order['id'],
					$cart_item_old['id']
				);
			}

			$book_info['product']['booking_fee'] = $cart_item['booking_fee'];
		}
		if (
			isset($cart_item['booking_tax'])
				&&
			strcmp(strval($book_info['product']['tax']), strval($cart_item['booking_tax']))
		) {

			// Записываем в лог
			if (!$cart_edit_only) {

				$this->log->save_order_log(
					'Changed tax from <b>' . $book_info['product']['tax'] . '</b> to <b>' . $cart_item['booking_tax'] . '</b>',
					$this->order['id'],
					$cart_item_old['id']
				);
			}

			$book_info['product']['tax'] = $cart_item['booking_tax'];
		}



		$book_info = array_values_to_string($book_info);

		if ($need_to_change_sold) $this->page_cart_item->restore_trip_tickets($cart_item_old['id']);


		// Сохраняем изменённые(или те же самые) параметры билетов и опций
		// а так же сумму и комиссию за эту часть заказа
		$cart_item_data = array (
			'id'		=> $cart_item_old['id'],
			'data'		=> $this->db->encode_data($book_info),
		);

		if (isset($new_arrival_date) && !is_null($new_arrival_date)) {

			$cart_item_data['calendar_id'] = $new_arrival_date;
		}

		$this->db->insert_update('cart_items', $cart_item_data);


		if ($need_to_change_sold) {

			$this->cache->clear();

			$this->page_cart_item->reduce_trip_tickets($cart_item_old['id']);
		}
	}

	// Изменения в суммах, комнатах и заказанных днях
	protected function hotel_item_check_content($cart_item_id, $cart_item) {

		$this->add_page('calendar');

		$this->db->query("
			SELECT
				cart_items.*,

				calendar.date,
				calendar2.date AS check_out_date,

				orders.id AS order_id
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				JOIN products ON products.id = cart_items.product_id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				LEFT JOIN calendar AS calendar2 ON calendar2.id = cart_items.calendar_id2
			WHERE
				cart_items.id = ?
				".($this->user->is_partner() ? " AND products.partner_id = ".$this->user->data('id') : ""),
			$cart_item_id
		);

		if (!($cart_item_old = $this->db->fetch())) return;

		if (
			(
				($cart_item_old['state_name'] != 'completed')
					&&
				!$this->user->is_permitted('order_edit')
					&&
				!$this->user->is_partner()
			)
				||
			(
				($cart_item_old['state_name'] == 'completed')
					&&
				!$this->page_order->is_user_permitted_to_edit_state($cart_item_old['state_name'])
			)
			) {

			continue;
		}


		if ($this->user->is_partner() || $this->user->is_admin()) {

			// Информация о комнатах и заказанном
			$book_info = $this->db->decode_data($cart_item_old['data']);

			// На уровне данного метода партнёр может изменить только свой комментарий
			if (isset($cart_item['comment_partner']) && strcmp($cart_item['comment_partner'], $book_info['comments']['partner'])) {

				$this->log->save_order_log(
					'Changed partner comment from <b>'.$book_info['comments']['partner'].'</b> to <b>'.$cart_item['comment_partner'].'</b>',
					$cart_item_old['order_id'],
					$cart_item_old['id']
				);

				$book_info['comments']['partner'] = $cart_item['comment_partner'];
			}

			// Остальное - только админу
			// Также не обрабатываем изменения в данных отменённых/удалённых частей
			if (
				!(
					in_array($cart_item['state'], array('cancelled','deleted'))
					&&
					in_array($cart_item_old['state_name'], array('cancelled','deleted'))
				)
				&&
				$this->user->is_admin()
			) {

				// Изменилось кол-во заказанных комнат
				if (isset($cart_item['rooms']) && ($cart_item['rooms'] != $book_info['booked'])) {

					$this->log->save_order_log(
						'Changed rooms count from <b>'.$book_info['booked'].'</b> to <b>'.$cart_item['rooms'].'</b>',
						$cart_item_old['order_id'],
						$cart_item_old['id']
					);

					$book_info['booked'] = $cart_item['rooms'];
				}

				$dates_changed = false;

				// Параметры дней
				if (!empty($cart_item['day_rates'])) {

					$days_info = array();

					foreach ($cart_item['day_rates'] as $day_date => $day) {

						$tax_modifier = (1 + $book_info['product']['tax'] / 100);

						// Новый день
						if (!empty($day['new_day'])) {

							$date = format_date_for_db($day['date']);

							// Получаем calendar_id для этого дня
							$calendar_day = $this->page_calendar->get_day(
								null,
								$date,
								$book_info['product']['id']
							);

							if ($calendar_day) {

								// Составляем массив с информацией о дне
								$new_day_info = array(
									'calendar_id' => $calendar_day['id'],
									'disabled' => null,
									'no_arrival' => null,
									'price' => $day['rate'],
									'comission' => $day['margin'],
									'wholesale' => $day['wholesale'],
									'xtraa' => null,
									'xtrac' => null,
									'avail' => null,
									'cut_off' => null,
									'min' => null,
									'max' => null,
									'sold' => null,
									'not_avail' => null,
									'free_night' => (($day['rate'] > 0) ? false : true),
									'day_rate' => $day['rate'],
									'day_total' => round_price($day['rate'] * $tax_modifier),
									'day_comission' => round_price($day['comission'] * $tax_modifier),
									'strike_off' => null,
									'discount' => array(),
								);

								$days_info[$date] = $new_day_info;

								$this->log->save_order_log(
									'Added new day <b>'.format_time($date, false).'</b> with price <b>'.$new_day_info['price'].'</b> and commission <b>'.$day['comission'].'</b>',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								$dates_changed = true;
							}
						} else {

							// Текущая информация дня, в ней будем обновлять изменённые поля
							if (empty($book_info['days_info'][$day_date])) continue;

							$day_info = $book_info['days_info'][$day_date];

							// День удалён
							if (!empty($day['remove'])) {

								$this->log->save_order_log(
									'Day <b>'.format_time($day_date, false).'</b> has been removed',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								$dates_changed = true;

								// Переходим к следующему дню, не занося этот в новый список дней
								continue;
							}

							// Новая(вероятно) дата
							$day_date_new = format_date_for_db($day['date']);

							// У дня сменилась дата
							if ($day_date_new != $day_date) {

								// Получаем calendar_id для этого дня
								$calendar_day = $this->page_calendar->get_day(
									null,
									$day_date_new,
									$book_info['product']['id']
								);

								if ($calendar_day) {

									$day_info['calendar_id'] = $calendar_day['id'];

									$this->log->save_order_log(
										'Day changed from <b>'.format_time($day_date, false).'</b> to <b>'.format_time($day_date_new, false).'</b>',
										$cart_item_old['order_id'],
										$cart_item_old['id']
									);

									$dates_changed = true;
								}
							}

							$day_rebate = 0;
							if (!empty($day_info['rebate_rate'])) $day_rebate = $day_info['rebate_rate'];


							$day_comission_without_tax = round_price(($day_info['day_comission'] + $day_rebate) / $tax_modifier) - $day_rebate;

							// Изменилась rebate rate
							if (($day['rebate_rate'] != $day_rebate) || empty($day_info['rebate_rate'])) {

								if ($day_rebate || $day['rebate_rate']) {

									$this->log->save_order_log(
										'Changed day rebate rate for <b>'.format_time($day_date_new, false).'</b> from <b>'.$day_rebate.'</b> to <b>'.$day['rebate_rate'].'</b>',
										$cart_item_old['order_id'],
										$cart_item_old['id']
									);
								}

								$day_info['rebate_rate'] = round_price($day['rebate_rate']);
							}

							// Изменилась сумма за день
							if ($day['rate'] != $day_info['day_rate']) {

								$this->log->save_order_log(
									'Changed day total rate for <b>'.format_time($day_date_new, false).'</b> from <b>'.$day_info['day_rate'].'</b> to <b>'.$day['rate'].'</b>',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								// Если сумма дня = 0, то выставляем флаг free_night
								if (!($day['rate'] > 0)) $day_info['free_night'] = true;
								else $day_info['free_night'] = false;

								$day_info['day_rate'] = $day['rate'];
								$day_info['day_total'] = round_price(($day['rate']) * $tax_modifier);
							}

							// Изменилась комиссия за день
							if ($day['comission'] != $day_comission_without_tax) {

								$this->log->save_order_log(
									'Changed day commission for <b>'.format_time($day_date_new, false).'</b> from <b>'.$day_comission_without_tax.'</b> to <b>'.$day['comission'].'</b>',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								$day_info['day_comission'] = round_price(($day['comission']) * $tax_modifier);
							}

							// Изменился процент комиссии
							if ($day['margin'] != $day_info['comission']) {

								if (!$day['margin']) $day['margin'] = 0;

								$this->log->save_order_log(
									'Changed day margin for <b>'.format_time($day_date_new, false).'</b> from <b>'.$day_info['comission'].'</b> to <b>'.$day['margin'].'</b>',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								$day_info['comission'] = $day['margin'];
							}

							// Изменилась оптовая цена
							if ($day['wholesale'] != $day_info['wholesale']) {

								if (!$day['wholesale']) $day['wholesale'] = 0;

								$this->log->save_order_log(
									'Changed day net for <b>'.format_time($day_date_new, false).'</b> from <b>'.$day_info['wholesale'].'</b> to <b>'.$day['wholesale'].'</b>',
									$cart_item_old['order_id'],
									$cart_item_old['id']
								);

								$day_info['wholesale'] = $day['wholesale'];
							}

							$days_info[$day_date_new] = $day_info;
						}
					}

					$book_info['days_info'] = $days_info;
				}

				// Изменились параметры включений
				if (!empty($cart_item['inclusions'])) {

					foreach ($cart_item['inclusions'] as $inclusion_id => $inclusion) {

						$inclusion_old = $book_info['inclusions'][$inclusion_id];

						if (isset($inclusion['type']) && ($inclusion['type'] != $inclusion_old['type'])) {

							$this->log->save_order_log(
								'Changed inclusion <b>'.$inclusion_old['name'].'</b> type from <b>'.$this->page_hotel->inclusion_type[$inclusion_old['type']].'</b> to <b>'.$this->page_hotel->inclusion_type[$inclusion['type']].'</b>',
								$cart_item_old['order_id'],
								$cart_item_old['id']
							);

							$inclusion_old['type'] = $inclusion['type'];
						}

						if (isset($inclusion['value']) && ($inclusion['value'] != $inclusion_old['value'])) {

							$this->log->save_order_log(
								'Changed inclusion <b>'.$inclusion_old['name'].'</b> value from <b>'.$inclusion_old['value'].'</b> to <b>'.$inclusion['value'].'</b>',
								$cart_item_old['order_id'],
								$cart_item_old['id']
							);

							$inclusion_old['value'] = $inclusion['value'];
						}

						if (isset($inclusion['frequency']) && ($inclusion['frequency'] != $inclusion_old['frequency'])) {

							$this->log->save_order_log(
								'Changed inclusion <b>'.$inclusion_old['name'].'</b> frequency from <b>'.$this->page_hotel->inclusion_frequency[$inclusion_old['frequency']].'</b> to <b>'.$this->page_hotel->inclusion_frequency[$inclusion['frequency']].'</b>',
								$cart_item_old['order_id'],
								$cart_item_old['id']
							);

							$inclusion_old['frequency'] = $inclusion['frequency'];
						}

						if (isset($inclusion['is_taxed']) && ($inclusion['is_taxed'] != $inclusion_old['is_taxed'])) {

							$this->log->save_order_log(
								'Changed inclusion <b>'.$inclusion_old['name'].'</b> taxed from <b>'.$inclusion_old['is_taxed'].'</b> to <b>'.$inclusion['is_taxed'].'</b>',
								$cart_item_old['order_id'],
								$cart_item_old['id']
							);

							$inclusion_old['is_taxed'] = $inclusion['is_taxed'];
						}

						if (isset($inclusion['is_taxed']) && ($inclusion['total'] != $inclusion_old['total'])) {

							$this->log->save_order_log(
								'Changed inclusion <b>'.$inclusion_old['name'].'</b> total(all rooms) from <b>'.$inclusion_old['total'].'</b> to <b>'.$inclusion['total'].'</b>',
								$cart_item_old['order_id'],
								$cart_item_old['id']
							);

							$inclusion_old['total'] = $inclusion['total'];
						}

						$book_info['inclusions'][$inclusion_id] = $inclusion_old;
					}
				}

				// Изменены дни приезда и отъезда
				if ($dates_changed) {

					$new_dates = array_values(array_keys($book_info['days_info']));

					$first_day_date = $new_dates[0];
					$last_day_date = $new_dates[count($new_dates) - 1];

					// Т.к. 2ая дата(cart_items.calendar_id2) - дата отъезда, то прибавляем к $last_day_date один день
					$last_day_date = format_date_for_db(strtotime("+1 day", strtotime($last_day_date)));

					$book_info['date_from'] = $first_day_date;
					$book_info['date_to'] = $last_day_date;

					$this->db->query("
					UPDATE
						cart_items,
						calendar
					SET
						cart_items.calendar_id = calendar.id
					WHERE
						calendar.date = ?
							AND
						calendar.product_id = ?
							AND
						cart_items.id = ?",
						$first_day_date,
						$book_info['product']['id'],
						$cart_item_old['id']
					);

					$this->db->query("
					UPDATE
						cart_items,
						calendar
					SET
						cart_items.calendar_id2 = calendar.id
					WHERE
						calendar.date = ?
							AND
						calendar.product_id = ?
							AND
						cart_items.id = ?",
						$last_day_date,
						$book_info['product']['id'],
						$cart_item_old['id']
					);
				}

				if ($book_info['nights'] != nights_from_interval($book_info['date_from'], $book_info['date_to'])) {

					$this->log->save_order_log(
						'Changed nights count from <b>'.$book_info['nights'].'</b> to <b>'.nights_from_interval($book_info['date_from'], $book_info['date_to']).'</b>',
						$cart_item_old['order_id'],
						$cart_item_old['id']
					);

					$book_info['nights'] = nights_from_interval($book_info['date_from'], $book_info['date_to']);
				}
			}


			$book_info = array_values_to_string($book_info);


			$this->db->insert_update('cart_items', array (
				'id'		=> $cart_item_old['id'],
				'data'		=> $this->db->encode_data($book_info),
			));
		}
	}

	protected function success($data) {

		$this->order = $this->page_order->get($this->page_order->id);

		$this->add_page('trip');
		$this->add_page('hotel');

		// Только для админа
		// обновляем данные юзера
		if ($this->user->is_admin() && $this->user->is_permitted('order_edit')) {

			// Обновляем данные юзера
			$user_data = array (
				'first_name'	=> $data['customer_first_name'],
				'last_name'		=> $data['customer_last_name'],
				'email'			=> $data['customer_email'],
				'phone'			=> $data['customer_phone'],
				'phone2'		=> $data['customer_phone2'],
				'address'		=> $data['customer_address'],
				'city'			=> $data['customer_city'],
				'zip'			=> $data['customer_zip'],
				'state_id'		=> $data['customer_state_id'],
				'state_name'	=> $data['customer_state_name'],
				'country_id'	=> $data['customer_country_id'],
			);

			$this->save_user_data($user_data);
		}

		if ($this->user->is_permitted('order_edit') || $this->user->is_partner()) {

			// Обновляем общие данные заказа
			$order_data = array (
				'id'				=> $this->order['id'],
				'comment_admin'		=> $data['comment_admin'],
			);

			if ($this->user->is_partner()) unset($order_data['comment_admin']);

			$this->page_order->save($order_data);

			// Если были изменения в полях заказа - пишем лог
			foreach ($order_data as $field => $value) {

				if (isset($this->order[$field]) && ($this->order[$field] != $value)) {

					$this->log->save_order_log(
						'Changed '.$field.' from <b>'.$this->order[$field].'</b> to <b>'.$value.'</b>',
						$this->order['id']
					);
				}
			}
		}


		// Изменения в частях заказа
		$this->add_page('cart_item');
		$order_cart_items = $this->page_cart_item->get_list(array (
			'cart_id'	=> $this->order['cart_id'],
		));

		// Список cart_item_id у которых что-то обновилось
		$updated_cart_items = array();

		// Части заказа с трипами
		if (!empty($data['trip_items'])) {

			$this->make_additional_validation_data($data['trip_items']);

			foreach ($data['trip_items'] as $cart_item_id => $cart_item) {

				if (empty($order_cart_items[$cart_item_id])) continue;

				$cart_item_old = $order_cart_items[$cart_item_id];

				// Анализ изменений в части должен учитывать связь с FH в любом случае
				// но передавать этот флаг извне небезопасно - смотрим по старым частям
				$cart_item['is_fh_item'] = $cart_item_old['is_fh_item'];


				if (
					!empty($cart_item['state'])
						&&
					($cart_item_old['state_name'] == 'completed')
						&&
					strcmp($cart_item_old['state_name'], $cart_item['state'])
				) {

					$errors = array('The item has been completed already');
				}
				else {

					$state_result = null;
					// Если новое состояние не совпадает со старым - обновляем
					if (!empty($cart_item['state']) && ($cart_item_old['state_name'] != $cart_item['state'])) {

						// Для партнёра проверяем, что выставлен один из разрешённых статусов
						// и что он не меняется статус отменённой части заказа
						if (
							$this->user->is_admin()
							||
							(
								$this->user->is_partner()
								&&
								in_array($cart_item['state'], array('paid', 'confirmed', 'unconfirmed'))
								&&
								($cart_item_old['state_name'] != 'cancelled')
							)
						) {

							$state_result = $this->page_order->cart_item_set_state($cart_item_old['id'], $cart_item['state']);
						}
					}

					$this->trip_item_update_cancelled_data($cart_item_id, $cart_item);


					// Если часть была восстановлена из удалённого/отменённого состояния
					// то её валидация уже была выполнена, статус уже изменён
					// а другие поля измениться не могли, т.е. нам важна только успешность валидации
					if (!is_null($state_result) && $state_result['restoration_performed']) {

						$errors = ((!$state_result['general']) ? $state_result['errors'] : array());
					}
					// Иначе прогоняем через обычный механизм
					else {

						$errors = $this->trip_item_check_content($cart_item_id, $cart_item);
					}
				}


				if ($errors) {

					$this->errors = array_merge($this->errors, $errors);
				}
				else {

					// Отправляем партнёру письмо с изменениями и меняем статус на unconfirmed
					if (!empty($cart_item['send_update_email'])) $updated_cart_items[] = $cart_item_id;
				}
			}
		}

		// Части заказа с отелями
		if (!empty($data['hotel_items'])) {

			foreach ($data['hotel_items'] as $cart_item_id => $cart_item) {

				if (empty($order_cart_items[$cart_item_id])) continue;

				$cart_item_old = $order_cart_items[$cart_item_id];


				// Отправляем партнёру письмо с изменениями и меняем статус на unconfirmed
				if (!empty($cart_item['send_update_email'])) $updated_cart_items[] = $cart_item_id;

				// Если новое состояние не совпадает со старым - обновляем
				if (isset($cart_item['state']) && ($cart_item_old['state_name'] != $cart_item['state'])) {

					// Для партнёра проверяем, что выставлен один из разрешённых статусов
					// что он не меняется статус отменённой части заказа
					// и что при подтверждении указан confirmation number
					if (
						$this->user->is_admin()
							||
						(
							$this->user->is_partner()
								&&
							in_array($cart_item['state'], array('paid', 'confirmed', 'unconfirmed'))
								&&
							($cart_item_old['state_name'] != 'cancelled')
								&&
							(
								($cart_item['state'] != 'confirmed')
									||
								!empty($cart_item['confirmation'])
							)
						)
						) {

						$this->page_order->cart_item_set_state($cart_item_old['id'], $cart_item['state']);
					}
				}

				// Ввели\изменили confirmation number
				// только для не удалённых частей
				if (
					isset($cart_item['confirmation'])
						&&
					($cart_item['confirmation'] != $cart_item_old['confirmation'])
						&&
					(
						$this->user->is_admin()
							||
						(
							$this->user->is_partner()
								&&
							($cart_item_old['state_name'] != 'cancelled')
						)
					)
					) {

					$this->page_order->set_item_confirmation_number($cart_item_old['id'], $cart_item['confirmation']);
				}

				if ($this->user->is_admin()) {

					// Если состояние 'cancelled' и не пустые значения site_fee/partner_fee - записываем их в бд
					if (
						isset($cart_item['state'])
							&&
						($cart_item['state'] == 'cancelled')
						) {

						$this->page_order->set_item_fee(
							$cart_item_id,
							(isset($cart_item['site_fee']) ? $cart_item['site_fee'] : null),
							(isset($cart_item['partner_fee']) ? $cart_item['partner_fee'] : null),
							(isset($cart_item['refund_amount']) ? $cart_item['refund_amount'] : null),
							(isset($cart_item['cancel_reason']) ? $cart_item['cancel_reason'] : null)
						);
					}

					// Отправляем письмо клиенту и партнёру о отмене части заказа
					if (
						isset($cart_item['state'])
							&&
						($cart_item['state'] == 'cancelled')
							&&
						(
							!empty($cart_item['send_cancellation_email'])
								||
							!empty($cart_item['send_cancellation_sms'])
						)
						) {

						$this->page_order->cancelled_item_send_mails(
							$cart_item_id,
							(!empty($cart_item['send_cancellation_email']) ? true : false),
							(!empty($cart_item['send_cancellation_sms']) ? true : false)
						);
					}
				}


				// Изменения в суммах, комнатах и заказанных днях
				$this->hotel_item_check_content($cart_item_id, $cart_item);
			}
		}


		// Обновляем total/tax частей заказа и заказа/корзины в целом
		$this->page_order->update_order_totals($this->order['id']);


		// Только для админа
		if ($this->user->is_admin()) {

			// Отправка юзеру письма-подтверждения
			if ($data['resend_confirm']) {

				$this->page_order->mail_user_order_confirmation($this->order['id']);

				$this->log->save_order_log(
					'Order confirmation has been sent to customer',
					$this->order['id']
				);
			}

			// В данном случае отправка СМС длля клиентов RS происходит в отдельном методе API
			if ($data['resend_confirm_text'] && !$this->order['made_from_rs']) {

				// Тут нужен адрес портала чтобы клиент видел свою страницу именно на портале
				$is_portal = array();

				if ($this->order['affiliate_id']) {

					$is_portal = $this->portal->get(array('affiliate_id' => $this->order['affiliate_id']));

					$is_portal = array_merge($is_portal, $this->db->decode_data($is_portal['data']));
				}
				$website_url = ($is_portal ? $is_portal['domain'] : $this->config('website_url'));

				$link_voucher = $this->config('website_url').'/order/access/?key='.$this->order['accesskey'];

				if ($is_portal) {

					$external_domain = null;

					if (isset($is_portal['iframe']) && !$is_portal['iframe']['is_disabled']) {

						$external_domain = $is_portal['iframe']['parent'];
					}

					$link_voucher = (is_null($external_domain) ? $website_url.'/' : $external_domain.'#').
						'order/view/'.$this->order['id'].'/user-'.md5($this->order['accesskey']);
				}

				$this->page_order->send_sms_customer_new_reservation(
					$user_data['phone'],
					'TRS-'.$this->order['id'],
					$link_voucher,
					$this->page_order->get_order_products($this->order['id']),
					false,
					true
				);

				$this->log->save_order_log(
					'Order confirmation text has been sent to customer',
					$this->order['id']
				);
			}

			// Изменилось значение pay_delay_amount
			if (isset($data['pay_delay_amount']) && ($this->order['pay_delay_amount'] != $data['pay_delay_amount'])) {

				$this->log->save_order_log(
					'Changed PayDelay amount from <b>'.$this->order['pay_delay_amount'].'</b> to <b>'.$data['pay_delay_amount'].'</b>',
					$this->order['id']
				);

				$this->db->insert_update('orders', array (
					'id'				=> $this->order['id'],
					'pay_delay_amount'	=> $data['pay_delay_amount'],
				));
			}

			// Сменили статус pay_delay
			if (!empty($data['pay_delay_status'])) {

				if (($data['pay_delay_status'] == 'received') && !($this->order['pay_delay_received'] > 0)) {

					$this->log->save_order_log(
						'Changed PayDelay status to <b>Received</b>',
						$this->order['id']
					);

					$this->db->insert_update('orders', array (
						'id'					=> $this->order['id'],
						'pay_delay_received'	=> format_date_for_db(null, true),
					));
				}
				else if (($data['pay_delay_status'] == 'not_received') && ($this->order['pay_delay_received'] > 0)) {

					$this->log->save_order_log(
						'Changed PayDelay status to <b>Not received</b>',
						$this->order['id']
					);

					$this->db->insert_update('orders', array (
						'id'					=> $this->order['id'],
						'pay_delay_received'	=> null,
					));
				}
			}
		}


		// Письмо партнёрам с обновлёнными заказа
		// выставляем изменённым частям заказа статус unconfirmed
		if ($updated_cart_items) {

			send_order_info_to_vendor($this->order['id'], $updated_cart_items);

			foreach ($updated_cart_items as $cart_item_id) {

				$this->log->save_order_log(
					'A message to vendor has been sent',
					$this->order['id'],
					$cart_item_id
				);

				$this->page_order->cart_item_set_state($cart_item_id, 'unconfirmed');
			}
		}


		if ($this->errors) return;
		else {

			$this->tpl->message_flag('info_success');

			$this->url->go('/index.php?page=order&mode=edit&id='.$this->order['id']);
		}
	}
}

// Переопределяем часть методов для работы с апи

// В редактировании заказов АПИ ограничены:
// RS полностью редактирует только свои заказы и ограничена в редактировании закзов TS
// Mobile может менять только расписание, дату прибытия и комментарий к любым заказам
class request_api_order_edit extends request_order_edit {

	// Заглушки
	protected function defaults() {}
	protected function lists() {}


	protected function valid_order_id($order_id) {

		if (!($this->order = $this->page_order->get($order_id, $this->data['partner_id']))) return false;

		return true;
	}

	protected function failure($errors) {

		if (!is_page('api_mobile')) {

			$this->page_api_rs->send_response($this->data, false, $errors);
		}
		else {

			$this->page_api_mobile->send_response($this->data, false, $errors);
		}
	}


	// Обновляем общие данные заказа
	private function save_order_data($data, $partner_id) {

		$order_available_fields = array (
			'payment_method',
			'gift_certificate',
			'was_refunded',
			'refund_amount',
		);

		$order_data = array ();

		foreach ($order_available_fields as $field) {

			if (isset($data[$field])) $order_data[$field] = $data[$field];
		}

		if ($order_data) {

			$order_data = array_merge(array('id' => $this->order['id']), $order_data);

			$this->page_order->save($order_data);

			// Если были изменения в полях заказа - пишем лог
			foreach ($order_data as $field => $value) {

				if (isset($this->order[$field]) && ($this->order[$field] != $value)) {

					$this->log->save_order_log(
						'Changed '.$field.' from <b>'.$this->order[$field].'</b> to <b>'.$value.'</b>',
						$this->order['id'],
						null,
						$partner_id
					);
				}
			}
		}
	}

	// Обновляем данные оплаты
	private function save_payment_data($data, $partner_id) {

		$payment_data = array();

		if (isset($data[0]['card_type'])) $payment_data['type'] = $data[0]['card_type'];
		if (isset($data[0]['card_number'])) $payment_data['number'] = $data[0]['card_number'];
		if (isset($data[0]['transaction_id'])) $payment_data['transaction_id'] = $data[0]['transaction_id'];

		if ($payment_data) {

			// Сама get_payment() возвращает массив, т.к. на один заказ м.б. несколько платежей
			$payment = $this->page_order->get_payment(null, $this->order['id']);
			// Но т.к. это api, то тут пока что работаем только в случаях если платёж точно один, либо его вообще нет.
			if (!is_null($payment)) {

				if (count($payment) == 1) {

					$payment = array_shift($payment);
				}
				else {

					return;
				}
			}
			else {

				$payment = array (
					'id'				=> null,
					'type'				=> '',
					'number'			=> '',
					'transaction_id'	=> '',
				);
			}

			foreach ($payment_data as $field => $value) {

				if ($payment[$field] != $value) {

					$this->log->save_order_log(
						'Changed payment '.$field.' from <b>'.$payment[$field].'</b> to <b>'.$value.'</b>',
						$this->order['id'],
						null,
						$partner_id
					);
				}
			}


			$payment_data['id'] = $payment['id'];
			$payment_data['order_id'] = $this->order['id'];

			$this->page_order->save_payment($payment_data);
		}
	}

	// Обновляем данные корзины
	private function save_cart_data($data, $partner_id) {

		$this->add_page('cart');
		$cart = $this->page_cart->get($this->order['cart_id']);

		$custom_total = null;

		if (!isset($data['custom_total'])) return;

		if (empty($data['custom_total'])) {

			if ($data['custom_total'] == '') {

				$custom_total = $this->order['total'];

				$this->log->save_order_log(
					'Changed custom total to <b>' . $this->order['total'] . '</b> - gross total order value',
					$this->order['id'],
					null,
					$partner_id
				);
			}
			else {

				$custom_total = $data['custom_total'];

				$this->log->save_order_log(
					'Changed custom total from <b>' . $cart['data']['custom_total'] . '</b> to <b>' . $custom_total . '</b>',
					$this->order['id'],
					null,
					$partner_id
				);
			}
		}
		else {

			$custom_total = $data['custom_total'];

			if (!isset($cart['data']['custom_total']) || ($cart['data']['custom_total'] != $custom_total)) {

				$old_custom_total = null;
				if (!empty($cart['data']['custom_total'])) $old_custom_total = $cart['data']['custom_total'];

				$this->log->save_order_log(
					'Changed custom total' . ($old_custom_total ? ' from <b>' . $old_custom_total . '</b>' : '') . ' to <b>' . $custom_total . '</b>',
					$this->order['id'],
					null,
					$partner_id
				);
			}
		}


		$this->page_cart->update_data($cart['id'], array (
			'custom_total'		=> $custom_total,
		));
	}

	protected function success($data) {

		$errors = array();

		if (!is_page('api_mobile')) {

			// Данные юзера
			$this->save_user_data($data['order_data'], $data['partner_id']);

			// Общие данные заказа
			$this->save_order_data($data['order_data'], $data['partner_id']);

			// Данные оплаты
			if (!empty($data['order_data']['transaction_info'])) {

				$this->save_payment_data($data['order_data']['transaction_info'], $data['partner_id']);
			}

			// Данные корзины
			$this->save_cart_data($data['order_data'], $data['partner_id']);
		}


		// Части заказа с трипами
		// не даём редактировать данные частей для заказа с трипшока
		$move_trip_solution = array();

		if (!empty($data['order_data']['trip_items'])) {

			$this->make_additional_validation_data($data['order_data']['trip_items']);

			$this->add_page('trip');

			foreach ($data['order_data']['trip_items'] as $cart_item_id => $cart_item) {

				$this->trip_item_update_cancelled_data($cart_item_id, $cart_item, $data['partner_id']);

				$trip_item_errors = $this->trip_item_check_content(
					$cart_item_id,
					$cart_item,
					$data['partner_id']
				);

				if ($trip_item_errors) {

					$errors = array_merge($errors, $trip_item_errors);

					// Если это была попытка быстрого переноса трипа с лодкой/расписанием и она провалилась,
					// и провалилась по оценке валидатора именно по вине лодки/расписания, то перебираем
					// остальные лодки (и/или расписания) до конца либо до первого доступного (предлагаем это в ответе)
					if (
						isset($cart_item['move_trip'])
							&&
						$cart_item['move_trip']
							&&
						(
							isset($cart_item['inventory_id'])
								||
							isset($cart_item['schedule_id'])
						)
							&&
						($validation_flags = $this->page_trip->get_last_validation_flags())
							&&
						(
							$validation_flags['schedule_error']
								||
							$validation_flags['inventory_error']
						)
					) {

						$cart_item_solution = null;

						// На основе сравнения того, что менялось с тем, что вызвало ошибки валидации
						$is_day_changed = false;
						$is_schedule_changed = false;
						$is_inventory_changed = false;

						$this->add_page('cart_item');

						$cart_item_old = $this->page_cart_item->get_list(array (
							'cart_item_id'	=> $cart_item_id,
							'with_order_id'	=> true,
							'with_date'		=> true,
						));

						if ($cart_item_old) {

							$cart_item_old = array_pop($cart_item_old);
							$cart_item_old['data'] = $this->db->decode_data($cart_item_old['data']);

							if (strcmp(format_date_for_db($cart_item['arrival_date']), $cart_item_old['date'])) {

								$is_day_changed = true;
								if (isset($cart_item['schedule_id'])) $is_schedule_changed = true;
								if (isset($cart_item['inventory_id'])) $is_inventory_changed = true;
							}
							else {

								if (
									isset($cart_item['schedule_id'])
										&&
									($cart_item['schedule_id'] != $cart_item_old['data']['schedule']['id'])
								) {

									$is_schedule_changed = true;
								}

								if (
									isset($cart_item['inventory_id'])
										&&
									($cart_item['inventory_id'] != $cart_item_old['data']['inventory']['id'])
								) {

									$is_inventory_changed = true;
								}
							}


							$search_schedule_ids = array();
							$consider_inventories = false;
							$consider_schedules = false;

							if (
								(
									$is_day_changed
										||
									(
										!$is_day_changed
											&&
										!($is_schedule_changed && $is_inventory_changed)
									)
								)
									&&
								(
									$validation_flags['inventory_error']
										&&
									!$validation_flags['schedule_error']
								)
							) {

								$consider_inventories = true;
								$search_schedule_ids[] = $cart_item['schedule_id'];
							}
							else if (
								(!$is_day_changed && $is_schedule_changed && $is_inventory_changed)
									&&
								(
									$validation_flags['inventory_error']
										||
									$validation_flags['schedule_error']
								)
							) {

								$consider_schedules = true;
							}


							if ($consider_schedules) {

								// TODO В целях оптимизации можно тут получить не просто список расписаний, а сразу с актуальными
								// TODO данными из календаря и предварительно отсеять задисейбленные как минимум
								$trip_all_schedules = $this->page_trip->get_schedules($cart_item_old['product_id']);

								$current_schedule_id = $cart_item['schedule_id'];

								$trip_schedules = array();
								$rest_schedules = array();

								$before = true;
								

								// Опуская то расписание, на котором и так провалились, будем брать сначала все
								// ближайшие расписания вперёд, а затем более ранние в обратном порядке
								foreach ($trip_all_schedules as $current_schedule) {

									if ($current_schedule['id'] == $current_schedule_id) {

										$before = false;

										// Если искомая валидация провалилась не по расписанию, то сначала проверим
										// остальные лодки этого же расписания и только после этого перейдём к другим
										if (!$validation_flags['schedule_error']) {

											$trip_schedules[] = $current_schedule['id'];
										}
									}
									else {

										// Эту часть расписаний (более ранние) нужно заранее выстроить в обратном порядке
										if ($before) {

											$rest_schedules = array_merge (
												array(0 => $current_schedule['id']),
												$rest_schedules
											);
										}
										else {

											$trip_schedules[] = $current_schedule['id'];
										}
									}
								}

								foreach ($rest_schedules as $rest_schedule) $trip_schedules[] = $rest_schedule;

								$trip_schedules = array_values($trip_schedules);

								if ($trip_schedules) {

									$search_schedule_ids = $trip_schedules;
									$consider_inventories = true;
								}
							}


							if ($consider_inventories && $search_schedule_ids && !empty($cart_item['inventory_id'])) {

								$this->add_page('inventory');

								// TODO В целях оптимизации можно тут получить не просто список лодок, а сразу с актуальными
								// TODO данными из календаря и предварительно отсеять задисейбленные как минимум
								$trip_inventories = $this->page_inventory->get_trip_inventories(
									$cart_item_old['product_id'],
									true
								);

								// Не берём в расчёт лодку с которой смещаемся, если день и расписание не изменились
								if (!$is_day_changed && !$is_schedule_changed) {

									unset($trip_inventories[$cart_item_old['data']['inventory']['id']]);
								}

								foreach ($search_schedule_ids as $current_schedule_id) {

									$try_cart_item = $cart_item;

									$try_cart_item['schedule_id'] = $current_schedule_id;

									foreach ($trip_inventories as $trip_inventory) {

										// Не проверяем ту, на которую пытались перенести при первом нажатии "Move Trip"
										if (
											($current_schedule_id == $cart_item['schedule_id'])
												&&
											($trip_inventory['id'] == $cart_item['inventory_id'])
										) {

											continue;
										}

										$try_cart_item['inventory_id'] = $trip_inventory['id'];

										$try_errors = $this->trip_item_valid_day($try_cart_item, $cart_item_old);

										if (!$try_errors) {

											$cart_item_solution['inventory_id'] = $trip_inventory['id'];

											if ($current_schedule_id != $cart_item['schedule_id']) {

												$cart_item_solution['schedule_id'] = $current_schedule_id;
											}

											break(2);
										}
									}
								}
							}

							if (!is_null($cart_item_solution)) $move_trip_solution[$cart_item_id] = $cart_item_solution;
						}
					}
				}
			}
		}

		// Обновляем общие суммы заказа, если они изменились
		$this->page_order->update_order_totals($this->order['id']);


		$data = array(
			'log'	=> array(),
		);

		$order_changes_log = $this->log->get_cache();

		foreach ($order_changes_log as $log_item) {

			$data['log'][] = array (
				'item_id'		=> $log_item['cart_item_id'],
				'date_time'		=> $log_item['time'],
				'txt'			=> $log_item['txt'],
			);
		}


		if ($move_trip_solution) $data = array_merge($data, array('move_trip_solution' => $move_trip_solution));


		if (!is_page('api_mobile')) {

			$this->page_api_rs->send_response($data, true, $errors);
		}
		else {

			$this->page_api_mobile->send_response($data, true, $errors);
		}
	}
}

// Отдельный реквест чтобы редактировать корзину, под которую ещё не создан заказ
class request_api_rs_cart_edit extends request_api_order_edit {

	private $cart;

	protected function valid_cart_id($cart_id) {

		if (!($this->cart = $this->page_cart->get($cart_id))) return false;

		return true;
	}

	protected function failure($errors) {

		$this->page_api_rs->send_response($this->data, false, $errors);
	}

	// Изменяет только custom_total, но переопределяет родительскую т.к. у той уже немного другая логика
	private function save_cart_data($data, $partner_id) {

		if (array_key_exists('custom_total', $data) && ($data['custom_total'] !== '')) {

			$this->page_cart->update_data($this->cart['id'], array(
				'custom_total' => $data['custom_total'],
			));
		}
	}

	protected function success($data) {

		$this->add_page('cart');

		$errors = array();

		// custom_total
		$this->save_cart_data($data, $data['partner_id']);


		// Части заказа с трипами
		// на данный момент изменению поддаётся только количество уже заказанных билетов
		if (!empty($data['cart_data']['trip_items'])) {

			$this->make_additional_validation_data($data['cart_data']['trip_items']);

			foreach ($data['cart_data']['trip_items'] as $cart_item_id => $cart_item) {

				$trip_item_errors = $this->trip_item_check_content(
					$cart_item_id,
					$cart_item,
					$data['partner_id']
				);

				if ($trip_item_errors) $errors = array_merge($errors, $trip_item_errors);
			}
		}

		// Обновляем общие суммы заказа, если они изменились
		$this->page_cart->update_totals($this->cart['id']);

		$this->page_api_rs->send_response($data, !$errors, $errors);
	}
}

// Step2, форма данных о кредитке
// процесс оплаты и оформления заказа
class request_order_step2 extends f2b2h_request {

	// Ошибки оплаты через пейтрейс
	private $paytrace_errors;

	// Не сработавшие в пейтрейсе карты
	private $paytrace_error_cards;

	// Глобальный буфер для валидации полей нескольких кредиток
	private $multiple_cards = array (
		'errors'					=> array(),
		'real_card_number_error'	=> null,
		'current_card_type'			=> null,
		'current_state_id'			=> null,
	);

	protected function defaults() {

		if (!empty($_SESSION['order_step1_data'])) {

			$this->defaults += $_SESSION['order_step1_data'];
		}

		// Автоматически заполняем поля для тех юзера
		if ($this->user->is_tech_support()) {

			$this->defaults += array (
				'card_type'			=> 'Discover',
				'card_number'		=> '6011229052299160',
				'card_first_name'	=> $this->user->data('first_name'),
				'card_last_name'	=> $this->user->data('last_name'),
				'card_csc'			=> '123',
				'card_expire_month'	=> '10',
				'card_expire_year'	=> '2025',
				'address'			=> $this->user->data('address'),
				'city'				=> $this->user->data('city'),
				'zip'				=> $this->user->data('zip'),
				'notify'			=> false,
				'agreement_confirm'	=> true,
			);
		}
		else {

			$this->defaults['notify'] = true;
		}

		$this->tpl_raw('user_data', json_encode($this->defaults));
	}

	protected function lists() {

		$max_cards = ($this->user->is_admin() ? 5 : 1);

		for ($i = 1; $i <= $max_cards; $i++) $this->lists['split_payment'][$i] = $i.' Credit Card'.($i != 1 ? 's' : '');

		for ($year_index = 0; $year_index < 15; $year_index++) {

			$year = date("Y", strtotime("+".$year_index." year"));
			$years[] = $year;
		}
		$this->tpl_raw('card_years', json_encode($years));

		$countries = $this->page_country->get_list(array('view' => 1), false);
		$this->tpl_raw('countries', json_encode($countries));

		// На десктопе селектор стран выводится через JQ, а оно строго упорядочивает по ключам массива, что неправильно
		if (!is_mobile()) {

			$sort_countries = array_values($countries);
			foreach ($sort_countries as $key => $country_name) {

				$sort_countries[$key] = array(
					'name' => $country_name,
					'id' => array_search($country_name, $countries),
				);
			}

			$this->tpl_raw('sort_countries', json_encode($sort_countries));
		}

		$this->lists['country_id'] = $this->page_country->get_list(array('view' => 1), false);

		if (
			($pay_delay = $this->page_order->pay_delay_get_amount($this->page_order->cart_id))
				&&
			!$this->page_hotel_expedia->cart_has_expedia_hotel($this->page_order->cart_id)
			) {

			$payment_option = array (
				0	=> 'Full Amount of '.money($pay_delay['cart_total'], true),
				1	=> '50% Deposit of '.money($pay_delay['pay_delay_amount'], true),
			);

			// Если включён депозит - выводим его как вариант оплаты
			if (!$this->config('deposit_is_disabled') && ($this->config('deposit_amount') > 0)) {

				$payment_option[2] = 'Secure your Trip for '.money($this->config('deposit_amount'), true);
			}
		}
		else {

			$payment_option = array(0 => 'Full Amount');
			$this->tpl('no_payment_options', true);
		}

		$this->lists['payment_option'] = $payment_option;

		for ($year_index = 0; $year_index < 15; $year_index++) {

			$year = date("Y", strtotime("+".$year_index." year"));
			$this->lists['card_expire_year'][$year] = $year;
		}

		if (is_mobile()) {

			$this->lists['card_expire_month'] = array (
				1	=> '01 (January)',
				2	=> '02 (February)',
				3	=> '03 (March)',
				4	=> '04 (April)',
				5	=> '05 (May)',
				6	=> '06 (June)',
				7	=> '07 (July)',
				8	=> '08 (August)',
				9	=> '09 (September)',
				10	=> '10 (October)',
				11	=> '11 (November)',
				12	=> '12 (December)',
			);
		}
	}


	// Валидация

	protected function valid_payment_method($payment_method) {

		if (empty($payment_method)) {

			$this->data['payment_method'] = 'direct';
		}

		return true;
	}

	protected function valid_affiliate_id($affiliate_id) {

		if (empty($affiliate_id)) {

			return true;
		}
		else {

			if ($portal = $this->portal->get(array('affiliate_id' => $affiliate_id))) return true;

			return false;
		}
	}

	protected function valid_card_type($card_type) {

		if ($this->user->is_tech_support()) return true;

		if ($this->data['payment_method'] != 'direct') return true;

		if (
			$this->config('office_password') && isset($this->data['card_number'])
			&&
			((string)$this->config('office_password') === (string)$this->data['card_number'])
		) {

			return true;
		}

		if (!$card_type || !in_array($card_type, get_credit_card_name_list())) return false;

		return true;
	}

	protected function valid_card_number($card_number) {

		if ($this->user->is_tech_support()) return true;

		if ($this->data['payment_method'] != 'direct') return true;

		if (
			$this->config('office_password') && $card_number
				&&
			((string)$this->config('office_password') === (string)$card_number)
			) return true;

		$error = '';
		$errornum = 0;

		if (!is_mobile()) {

			check_ccnumber($card_number, $this->multiple_cards['current_card_type'], $errornum, $error);
		}
		else {

			check_ccnumber($card_number, $this->data['card_type'], $errornum, $error);
		}

		if (!empty($error)) $this->multiple_cards['real_card_number_error'] = $error;

		return (empty($error) ? true : false);
	}
	protected function valid_card_first_name($card_first_name) {

		if ($this->user->is_tech_support()) return true;

		if ($this->data['payment_method'] != 'direct') return true;

		return (!empty($card_first_name) ? true : false);
	}
	protected function valid_card_last_name($card_last_name) {

		if ($this->user->is_tech_support()) return true;

		if ($this->data['payment_method'] != 'direct') return true;

		return (!empty($card_last_name) ? true : false);
	}
	protected function valid_card_csc($card_csc) {

		if ($this->user->is_tech_support()) return true;

		if ($this->data['payment_method'] != 'direct') return true;

		return (!empty($card_csc) ? true : false);
	}

	protected function valid_card_expire_month($card_expire_month) {

		if ($this->data['payment_method'] != 'direct') return true;

		if ((intval($card_expire_month) < 1) || (intval($card_expire_month) > 12)) return false;

		return true;
	}

	protected function valid_state_id($state_id) {

		if ($this->data['payment_method'] != 'direct') return true;

		if (($state_id == 0) || ($state = $this->page_state->get($state_id))) return true;

		return false;
	}

	protected function valid_state_name($state_name) {

		if ($this->data['payment_method'] != 'direct') return true;

		if (!is_mobile()) {

			if (($this->multiple_cards['current_state_id'] != 0) || $state_name) return true;
		}
		else {

			if (($this->data['state_id'] != 0) || $state_name) return true;
		}

		return false;
	}

	protected function valid_country_id($country_id) {

		if ($this->data['payment_method'] != 'direct') return true;

		if ($country = $this->page_country->get($country_id)) return true;

		return false;
	}

	protected function valid($data) {

		$result = true;

		if (
			($this->data['payment_method'] == 'direct')
				&&
			$this->page_order->max_cards
				&&
			!is_mobile()
				&&
			!isset($data['zero_amount_order'])
		) {

			$current_card = array();

			$pre_total = 0;


			for ($card_id = 1; $card_id <= $data['split_payment']; $card_id++) {

				$current_card = $data['card'.$card_id];

				if (!isset($current_card['country_id']) || !isset($current_card['card_expire_month'])) {

					alter_log('step2_errors', "\r\n\r\n---~~~--- !!!EMPTY FIELDS!!! ---~~~---\r\n");
					alter_log('step2_errors', "Card ".$card_id);
					batch_to_alter_log('step2_errors', $current_card);
					alter_log('step2_errors', "\r\nSession:");
					batch_to_alter_log('step2_errors', $_SESSION);
					alter_log('step2_errors', "\r\nWhole data:");
					batch_to_alter_log('step2_errors', $data);
				}


				// Проверка на нулевые суммы
				if (
					empty($current_card['amount_to_charge'])
					||
					!is_numeric($current_card['amount_to_charge'])
					||
					($current_card['amount_to_charge'] == 0)
				) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = 'Zero amount or wrong format';
				}

				// Пользуемся имеющимися методами проверки полей
				if (!$this->valid_card_type($current_card['card_type'])) {

					if (
						!$this->config('office_password')
							||
						!$current_card['card_number']
							||
						((string)$this->config('office_password') !== (string)$current_card['card_number'])
					) {

						$result = false;

						$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_card_type_error');
					}
				}

				$this->multiple_cards['current_card_type'] = $current_card['card_type'];

				if (!$this->valid_card_number($current_card['card_number'])) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = $this->multiple_cards['real_card_number_error'];
				}

				if (!$this->valid_card_expire_month($current_card['card_expire_month'])) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_card_expire_month_error');
				}

				if (!$this->valid_state_id($current_card['state_id'])) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_state_id_error');
				}

				$this->multiple_cards['current_state_id'] = $current_card['state_id'];

				if (!$this->valid_state_name($current_card['state_name'])) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_state_name_error');
				}

				if (!$this->valid_country_id($current_card['country_id'])) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_country_id_error');
				}


				// Теперь проверки, которые реквест сделал бы по умолчанию
				if (empty($current_card['card_first_name'])) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_card_first_name_error');
				}

				if (empty($current_card['card_last_name'])) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_card_last_name_error');
				}

				if (empty($current_card['card_csc']) || !is_numeric($current_card['card_csc'])) {

					$result = false;

					$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_card_csc_error');
				}

				if (!isset($current_card['same_billing_address'])) {

					if (!$this->user->is_admin()) {

						if (empty($current_card['address'])) {

							$result = false;

							$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_address_error');
						}
					}

					if (empty($current_card['zip'])) {

						$result = false;

						$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_zip_error');
					}

					if (!$this->user->is_admin()) {

						if (empty($current_card['city'])) {

							$result = false;

							$this->multiple_cards['errors'][$card_id][] = $this->lang('order_step2_city_error');
						}
					}
				}

				$pre_total += $current_card['amount_to_charge'];
			}

			$pre_total = sprintf('%01.2f', $pre_total);//для перевода в формат $2.80

			if (strcmp($pre_total, $data['total_to_charge'])) {

				$result = false;

				$this->multiple_cards['errors'][0][] = 'Incorrect total amount value: need to be '.$data['total_to_charge'].' (now: '.$pre_total.')';
			}
		}


		return $result;
	}

	protected function failure($validate_errors) {

		$errors = array();

		foreach ($validate_errors as $field => $error) {

			$errors[] = array (
				'text'				=> $error,
			);
		}

		// Если платёжных карт несколько и есть ошибки валидации их данных
		if ($this->multiple_cards['errors']) {

			foreach ($this->multiple_cards['errors'] as $card_number => $card_errors) {

				foreach ($card_errors as $index => $error) {

					if (
						(
							((count($this->multiple_cards['errors'])) == 1)
								&&
							($card_number == 1)
						)
							||
						($card_number == 0)
					) {

						$errors[] = array(
							'text' => $error,
						);
					}
					else {

						$errors[] = array(
							'text' => 'Card '.$card_number.': '.$error,
						);
					}
				}
			}
		}

		ajax_info_errors($errors);
	}


	// Оплата через paytrace(кроме отелей экспедии)
	//	[payment_data] => array (
	//		[order_id]				=> int,
	//		[order_payment_amount]	=> float,
	//		[order_comments]		=> string,
	//		[card_data]				=> array,
	//		[user_data]				=> array,
	//	)
	private function pay_via_paytrace($payment_data) {

		require_once($this->config('path').'/inc/paytrace.php');
		$paytrace = new Paytrace(
			$this->config('payment_paytrace_username'),
			$this->config('payment_paytrace_pass'),
			$this->config('payment_testmode')
		);

		$international = (($payment_data['user_data']['country'] != 'USA') ? true : false);

		// Код сраны в формате ISO_3166-2
		$country_code = 'US';
		if ($international) {

			$this->db->query("
			SELECT
				countries.code
			FROM
				countries
			WHERE
				countries.name = ?",
				$payment_data['user_data']['country']
			);

			if ($country = $this->db->fetch()) $country_code = $country['code'];
		}

		$description_fields = array(
			'last_name'     => 'Last Name',
			'first_name'    => 'First Name',
			'email'         => 'Email',
			'phone'         => 'Phone',
			'zip'           => 'Zip',
			'country'       => 'Country',
			'state'         => 'State',
			'city'          => 'City',
			'address'       => 'Address',
		);

		$user_description = array();
		foreach ($description_fields as $key => $caption) {

			if (!empty($payment_data['user_data'][$key])) $user_description[] = $payment_data['user_data'][$key];
		}

		$description = "Customer Information:\r\n".implode(";\r\n", $user_description).".\r\n";
		if ($payment_data['order_comments']) $description .= "Order Comments:".$payment_data['order_comments'];

		// Удаляем из описания левые символы
		$description = preg_replace('/([^[:alnum:]\s])/', '', $description);
		// и обрезаем по 255 символов
		$description = substr($description, 0, 255);

		$paytrace_data = array (
			'amount'			=> $payment_data['order_payment_amount'],
			'cc'				=> $payment_data['card_data']['card_number'],
			'csc'				=> $payment_data['card_data']['card_csc'],
			'expire_month'		=> $payment_data['card_data']['card_expire_month'],
			'expire_year'		=> $payment_data['card_data']['card_expire_year'],
			'billing_name'		=>
				(empty($payment_data['card_data']['card_first_name']) ? '' : $payment_data['card_data']['card_first_name']).
				(!empty($payment_data['card_data']['card_last_name']) && !empty($payment_data['card_data']['card_first_name']) ? ' ' : '').
				(empty($payment_data['card_data']['card_last_name']) ? '' : $payment_data['card_data']['card_last_name']),
			'billing_address'	=> $payment_data['user_data']['address'],
			'billing_country'	=> $country_code,
			'billing_state'		=> $payment_data['user_data']['state_code'],
			'billing_city'		=> $payment_data['user_data']['city'],
			'billing_zip'		=> $payment_data['user_data']['zip'],
			'description'		=> $description,
			'invoice'			=> $payment_data['order_id'],
		);

		if ($international) {

			alter_log('paytrace_data_log', "---~~~--- !!!NEW INTERNATIONAL PAYTRACE SESSION!!! ---~~~---\r\n");
			batch_to_alter_log('paytrace_data_log', $payment_data);
			alter_log('paytrace_data_log', "Paytrace data:");
			batch_to_alter_log('paytrace_data_log', $paytrace_data);
		}

		$paytrace_result = $paytrace->process($paytrace_data);

		// Отслеживаем ситуацию, когда сработала защита от мошенничества
		// Почему-то отклоняя в этом случае транзакцию paytrace не шлёт никаких ошибок
		if ($paytrace_result && !strcmp($paytrace->response['APPMSG'], 'VOIDED')) {

			$paytrace_result = false;
			$paytrace->errors[] = 'Please verify billing and credit card credentials and try again.';
		}

		if ($international) {

			alter_log('paytrace_data_log', "Response:");
			batch_to_alter_log('paytrace_data_log', $paytrace->response);
			alter_log('paytrace_data_log', "Errors:");
			batch_to_alter_log('paytrace_data_log', $paytrace->errors);
		}

		$this->db->query("
			UPDATE
				payments
			SET
				last_payment_response = ?,
				last_payment_errors = ?
			WHERE
				id = ?",
			implode('|', (array)$paytrace->response),
			implode('|', $paytrace->errors),
			$payment_data['payment_id']
		);

		if (!$paytrace_result) {

			alter_log('paytrace_data_log', "Is errors:");
			batch_to_alter_log('paytrace_data_log', $paytrace->errors);

			$this->paytrace_errors[substr($payment_data['card_data']['card_number'], -4)] = $paytrace->errors;

			$this->paytrace_error_cards[$payment_data['card_id']] = $payment_data['payment_id'];
		}

		return $paytrace_result;
	}

	// Получаем в виде строки набор комментариев клиента по частям заказа
	private function get_order_comments($cart_id) {

		$order_comments = '';

		$this->add_page('cart_item');

		$cart_items = $this->page_cart_item->get_list(array('cart_id' => $cart_id));

		foreach ($cart_items as $cart_item) {

			$book_info = $this->db->decode_data($cart_item['data']);

			if (!empty($book_info['comments']['guest'])) {

				$order_comments .= "\r\n(".$book_info['product']['name']."): ".$book_info['comments']['guest'];
			}
		}

		return $order_comments;
	}

	// Оплачиваем заказ (его часть) кредитной картой (одной из карт)
	private function make_one_card_payment($data, $order_user_data, $order_id, $split_payments = false) {

		$this->add_page('cart_item');
		$this->add_page('state');
		$this->add_page('country');

		$result = array (
			'errors'				=> array(),
			'paytrace_result'		=> false,
			'expedia_hotels_error'	=> false,
		);

		$cart_totals = $this->page_cart_item->get_cart_totals($this->page_order->cart_id, true, true);
		$order_total = $cart_totals['total']['gross_with_tax_discount'];
		// Флаг заказа из офиса
		$office_order = ($order_total && $this->config('office_password') && ((string)$this->config('office_password') === (string)$data['card_number']));

		// Обрезаем текстовые поля
		$cut_fields = array (
			'card_first_name'	=> 30,
			'card_last_name'	=> 50,
			'address'			=> 100,
			'zip'				=> 20,
			'state_name'		=> 50,
			'city'				=> 50,
		);

		foreach ($cut_fields as $field_name => $cut_length) {

			$data[$field_name] = substr($data[$field_name], 0, $cut_length);
		}

		// Если для оплаты те же поля что указаны на step1, то забиваем их из $user
		if (isset($data['same_billing_address'])) {

			$data['address'] = $order_user_data['address'];
			$data['zip'] = $order_user_data['zip'];
			$data['country_id'] = $order_user_data['country_id'];
			$data['state_id'] = $order_user_data['state_id'];
			$data['state_name'] = $order_user_data['state_name'];
			$data['city'] = $order_user_data['city'];
		}

		$data['state_code'] = '';

		if ($data['state_id']) {

			$state = $this->page_state->get($data['state_id']);
			$data['state_code'] = $state['code'];
			$data['state'] = $state['name'];
		}
		else $data['state'] = $data['state_name'];

		$country = $this->page_country->get($data['country_id']);
		$data['country'] = $country['name'];


		// Сохраняем данные карточки
		$data['order_id'] = $order_id;
		$payment_id = $this->page_order->save_payment($data);



		// Оплата заказа
		if (!$split_payments) {

			// Доступность опции pay_delay
			$pay_delay = $this->page_order->pay_delay_get_amount($this->page_order->cart_id);
			// Наличие отелей экспедии в заказе
			$cart_has_expedia_hotel = $this->page_hotel_expedia->cart_has_expedia_hotel($this->page_order->cart_id);
			// Сумма заказа для оплаты
			$order_payment_amount = 0;

			// Оплата только половины суммы
			if (!$cart_has_expedia_hotel && $pay_delay && ($data['payment_option'] == 1)) {

				$this->db->query("
				UPDATE
					orders
				SET
					pay_delay_amount = ?
				WHERE
					id = ?",
					$pay_delay['pay_delay_amount'],
					$order_id
				);

				$order_payment_amount = $pay_delay['pay_delay_amount'];
			} // только депозита
			else if (!$cart_has_expedia_hotel && $pay_delay && !$this->config('deposit_is_disabled') && ($data['payment_option'] == 2)) {

				$this->db->query("
				UPDATE
					orders
				SET
					pay_delay_amount = ?
				WHERE
					id = ?",
					($order_total - $this->config('deposit_amount')),
					$order_id
				);

				$order_payment_amount = $this->config('deposit_amount');
			} // полная сумма оплаты, без учёта отелей экспедии
			else {

				$this->db->query("
				UPDATE
					orders
				SET
					pay_delay_amount = NULL
				WHERE
					id = ?",
					$order_id
				);

				$order_payment_amount = $cart_totals['total']['gross_with_tax_discount'];
			}
		}
		// Если платежи делятся, то может быть только полная оплата без экспедий и сумма идёт вместе с данными карты
		else {

			$this->db->query("
				UPDATE
					orders
				SET
					pay_delay_amount = NULL
				WHERE
					id = ?",
				$order_id
			);

			$order_payment_amount = $data['amount_to_charge'];
		}


		$card_data = array (
			'card_type'				=> $data['card_type'],
			'card_number'			=> $data['card_number'],
			'card_first_name'		=> $data['card_first_name'],
			'card_last_name'		=> $data['card_last_name'],
			'card_csc'				=> $data['card_csc'],
			'card_expire_month'		=> $data['card_expire_month'],
			'card_expire_year'		=> $data['card_expire_year'],
		);

		$user_data = array (
			'first_name'	=> $order_user_data['first_name'],
			'last_name'		=> $order_user_data['last_name'],
			'email'			=> $order_user_data['email'],
			'phone'			=> $order_user_data['phone'],
			'address'		=> $data['address'],
			'city'			=> $data['city'],
			'zip'			=> $data['zip'],
			'state'			=> $data['state'],
			'state_code'	=> $data['state_code'],
			'country'		=> $data['country'],
		);

		// Отдельно оплачиваем отели экспедии, если они имеются в заказе
		if (!$split_payments && $cart_has_expedia_hotel) {

			$expedia_booking_errors = $this->page_hotel_expedia->book_hotels($order_id, array (
				'card'		=> $card_data,
				'user'		=> $user_data,
			));
			if ($expedia_booking_errors) {

				$result['errors'] = array_merge($result['errors'], $expedia_booking_errors);
				$result['expedia_hotels_error'] = true;
			}
		}

		// Оплачиваем заказ(кроме отелей экспедии)

		// Заказ из офиса
		if ($office_order) {

			$this->db->query("
				UPDATE
					orders
				SET
					is_office = 1
				WHERE
					id = ?",
				$order_id
			);

			$result['paytrace_result'] = true;
		}
		else if (is_live() && !$this->user->is_tech_support() && !$result['errors']) {

			$order_comments = $this->get_order_comments($this->page_order->cart_id);

			$result['paytrace_result'] = $this->pay_via_paytrace(array (
				'order_id'				=> $order_id,
				'card_id'				=> $data['card_id'],
				'payment_id'			=> $payment_id,
				'order_payment_amount'	=> $order_payment_amount,
				'card_data'				=> $card_data,
				'user_data'				=> $user_data,
				'order_comments'		=> $order_comments,
			));
		}

		return $result;
	}

	// Создаём платёж на сервере PP и возвращаем ссылку на него
	private function build_paypal_payment($cart_data, $order_id) {

		$paypal_payment = null;

		// Сохраняем
		$data = array (
			'paypal_payment'		=> true,
			'order_id'				=> $order_id,
			'last_payment_response'	=> 'Waiting for approve from paypal'
		);

		$this->page_order->save_payment($data);

		// Подключаем обработчик запросов PP
		require_once($this->config('path').'/inc/paypal.php');

		$url_base = $this->config('website_url').'/';
		$image = $this->config('website_url').'/img/tripshock-logo.png';
		$brandname = $this->config('site_name');

		if (is_portal()) {

			$this->add_page('photo');

			// Для чекаута используем картинку ваучера
			$image = $this->page_photo->get_photo_link(get_portal_param('id'), 'voucher', null, 'portal');
			// А если её нет, то не используем картинку
			if (!strpos($image, 'default')) $image = '';

			$brandname = (get_portal_param('company_name') ? get_portal_param('company_name') : '');

			if (is_iframe_portal()) {

				$iframe_data = get_portal_param('iframe');
				$url_base = $iframe_data['parent'] . '#';
			}
		}

		// Параметры запроса
		$requestParams = array(
			'RETURNURL'		=> $url_base.'index.php?page=order&mode=paypal_response&submode=return',
			'CANCELURL'		=> $url_base.'index.php?page=order&mode=paypal_response&submode=cancel',
			'NOSHIPPING'	=> '1',
			'HDRIMG'		=> $image,
			'BRANDNAME'		=> $brandname,
		);

		// Параметры всего заказа
		$orderParams = array(
			'PAYMENTREQUEST_0_PAYMENTACTION' => 'Sale',
			'PAYMENTREQUEST_0_AMT' => $cart_data['total']['gross_with_tax_discount'],
			'PAYMENTREQUEST_0_CURRENCYCODE' => 'USD',
			'PAYMENTREQUEST_0_ITEMAMT' => $cart_data['total']['gross_with_tax_discount'],
			'PAYMENTREQUEST_0_DESC' => (!is_portal() ? $this->config('website_name').' order #' : 'Order #').$order_id,
		);

		// Параметры частей заказа
		$items = array();
		$items_for_now = 0;
		$amount_for_now = 0;

		foreach ($cart_data['cart_items'] as $cart_item_id => $cart_item) {

			// Если в данном заказе более 10 частей, то в последнюю вставляем все остальные
			// это сделано т.к. PP в одном реквесте предлагает до 10 частей вствалять
			if (($items_for_now == 9) && (count($cart_data['cart_items']) > 10)) {

				$items = array_merge($items, array(
					'L_PAYMENTREQUEST_0_NAME9' => 'Remained '.(count($cart_data['cart_items']) - 9).' items',
					'L_PAYMENTREQUEST_0_DESC9' => '',
					'L_PAYMENTREQUEST_0_AMT9' => $cart_data['total']['gross_with_tax_discount'] - $amount_for_now,
					'L_PAYMENTREQUEST_0_QTY9' => '1'
				));
			}
			else {

				$this->db->query("
				SELECT
					data
				FROM
					cart_items
				WHERE
					id = ?",
					$cart_item_id
				);
				$db_res = $this->db->result;

				$cart_item_data = $this->db->fetch($db_res);
				$cart_item_data = $this->db->decode_data($cart_item_data['data']);

				if ($cart_item_data['product']['product_type'] == 'trip') {

					$desc_string = format_tickets_string($cart_item_data['tickets']);
				}
				else {

					$desc_string = format_rooms_string($cart_item_data);
				}

				$items = array_merge($items, array(
					'L_PAYMENTREQUEST_0_NAME'.$items_for_now => substr(cut($cart_item_data['product']['name'], 127), 0, 127),
					'L_PAYMENTREQUEST_0_DESC'.$items_for_now => substr(cut($desc_string, 127), 0, 127),
					'L_PAYMENTREQUEST_0_AMT'.$items_for_now => $cart_item['total']['gross_with_tax_discount'],
					'L_PAYMENTREQUEST_0_QTY'.$items_for_now => '1'
				));

				$items_for_now += 1;
				$amount_for_now += $cart_item['total']['gross_with_tax_discount'];
			}
		}

		$paypal = new Paypal();
		$response = $paypal->request('SetExpressCheckout',$requestParams + $orderParams + $items);

		// Запрос был успешно принят
		if (is_array($response) && $response['ACK'] == 'Success') {

			$paypal_payment['token'] = $response['TOKEN'];

			if (is_live()) {

				$link = 'https://www.paypal.com/webscr?cmd=_express-checkout&token='.urlencode($response['TOKEN']);
			}
			else {

				$link = 'https://www.sandbox.paypal.com/webscr?cmd=_express-checkout&token='.urlencode($response['TOKEN']);
			}

			$paypal_payment['link'] = $link;
		}
		else {

			alter_log('paypal_errors', '!!!Error while building PayPal request');
			batch_to_alter_log('paypal_errors', $response);
		}

		return $paypal_payment;
	}

	protected function success($data) {

		// В случае тех. работ не даём оформить заказ, выдавая соответствующее предупреждение
		if ($this->config('maintenance_mode') && !$this->user->is_tech_support()) {

			$this->lang('order_step2_success_error', $this->lang('maintenance'));
			return false;
		}


		// Если есть части Fareharbor, то их оформляем у себя только после завершения оплаты
		// А сейчас все их только валидируем
		$fh_validation = $this->page_order->fh_validate_order($this->page_order->cart_id);
		if (!$fh_validation['success']) {

			$this->lang('order_step2_success_error', $fh_validation['error']);

			return false;
		}


		$pay_by_card = (($data['payment_method'] == 'direct') ? true : false);

		file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "\r\n[".date("d-M-Y h:i:s T")."]---~~~--- START ---~~~---\r\n", FILE_APPEND);

		// Информация о корзине
		$this->add_page('cart');
		if (!($cart = $this->page_cart->get($this->page_order->cart_id))) return false;

		// На случай, если у юзера в сессии висит флаг отказа FH после сделанного только что заказа
		unset($_SESSION['order_rejected_from_fh']);

		// Логируем всю корзину на случай, если какой-то заказ опять потеряется
		$cart_data_log = weekly_log_name('full_cart_data');
		alter_log($cart_data_log, "---~~~--- !!!CART ".$this->page_order->cart_id."!!! ---~~~---\r\n");
		batch_to_alter_log($cart_data_log, $this->page_cart->get_contents($this->page_order->cart_id));
		alter_log('order_data_log', "Full cart ".$this->page_order->cart_id." data saved");

		$this->add_page('cart_item');

		// Итоговая сумма заказа
		$cart_totals = $this->page_cart_item->get_cart_totals($this->page_order->cart_id);
		$order_total = $cart_totals['total']['gross_with_tax_discount'];
		// id заказа, если он уже был оформлен
		$order_id = (!empty($_SESSION['order_id']) ? intval($_SESSION['order_id']) : null);
		$order_already_exists = ($order_id ? true : false);
		// id юзера, на которого оформляется заказа
		$user_id = $_SESSION['order_user_id'];
		// И данные о нём
		$order_user_data = $_SESSION['order_step1_data'];
		// Сразу дополняем их данными биллинг-адреса первой карты т.к. step1 больше не запрашивает адрес
		$order_user_data['address'] = (is_mobile() ? $data['address'] : $data['card1']['address']);
		$order_user_data['city'] = (is_mobile() ? $data['city'] : $data['card1']['city']);
		$order_user_data['zip'] = (is_mobile() ? $data['zip'] : $data['card1']['zip']);
		$order_user_data['state_id'] = (is_mobile() ? $data['state_id'] : $data['card1']['state_id']);
		$order_user_data['state_name'] = (is_mobile() ? $data['state_name'] : $data['card1']['state_name']);
		$order_user_data['country_id'] = (is_mobile() ? $data['country_id'] : $data['card1']['country_id']);
		// id юзера, проводящего заказ(админ/партнёр)
		$order_by_user_id = (($this->user->is_admin() || $this->user->is_partner()) ? $this->user->data('id') : 0);

		// Действия на случай если к существующему заказу добавляется
		if ($order_already_exists) {

			// Это если был платёж PP, то удаляем запись в таблице внешних платежей
			$this->db->query("
				DELETE FROM
					external_payments
				WHERE
					external_payments.order_id = ?",
				$order_id
			);

			// Это на случай, если сначала пытались заплатить PP, а после фейла снова платят, но уже картой
			// или наоборот
			$this->db->query("
				DELETE FROM
					payments
				WHERE
					payments.order_id = ?
						AND
					payments.type ".($pay_by_card ? "=" : "!=")." 'paypal'",
				$order_id
			);
		}

		// Для порталов отдельно считаем комиссию
		$portal_commission = $this->page_order->calculate_portal_commission (
			null,
			$cart_totals,
			(!empty($data['affiliate_id']) ? $data['affiliate_id'] : null)
		);

		// Сохраняем сам заказ
		$order_data = array (
			'id'					=> $order_id,
			'user_id'				=> $user_id,
			'order_by_user_id'		=> $order_by_user_id,
			'cart_id'				=> $cart['id'],
			'accesskey'				=> generate_access_key(),
			'total'					=> $cart['total'],
			'tax'					=> $cart['tax'],
			'rs_agent'				=> 'tripshock',
			'payment_method'		=> 'tripshock',
			'affiliate_id'			=> (!empty($data['affiliate_id']) ? $data['affiliate_id'] : ''),
			'affiliate_commission'	=> $portal_commission,
		);

		file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Order_data:\r\n\t", FILE_APPEND);
		foreach ($order_data as $key => $value) {

			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", $key.":".$value." | ", FILE_APPEND);
		}
		file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "\r\n", FILE_APPEND);

		$order_id = $this->page_order->save($order_data);

		file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Order_id: ".$order_id."\r\n", FILE_APPEND);

		// Сохраняем id заказа в сессии
		$_SESSION['order_id'] = $order_id;

		// Запись в orders_users_data с данными юзера при заказе(step1)
		$order_user_data['order_id'] = $order_id;
		$order_user_data['ip'] = getUserRealIp();

		$this->db->insert_update_no_id('orders_users_data', $order_user_data, array('order_id'));

		// В случае проведения заказа залогиненным админ/партнёр-пользователем записываем в лог нового заказа информацию об этом
		if ($order_by_user_id) {

			$this->log->save_order_log(
				'Reservation created on '.format_time(time()),
				$order_id
			);
		}

		$errors = array();
		$paytrace_result = false;
		$expedia_hotels_error = false;

		// Оплата заказа
		if ($order_total != 0) {

			// Оплата кредиткой (на нашем сайте через paytrace)
			if ($pay_by_card) {

				// Для мобильника все данные уже в $data
				if (is_mobile()) {

					$one_payment_result = $this->make_one_card_payment($data, $order_user_data, $order_id);

					$errors = $one_payment_result['errors'];
					$paytrace_result = $one_payment_result['paytrace_result'];
					$expedia_hotels_error = $one_payment_result['expedia_hotels_error'];

					if ($this->paytrace_errors) $this->paytrace_errors = array_shift($this->paytrace_errors);
				} // Если десктоп, но карта всего одна, то процедура обычная и со всеми возможными опциями (только заполняем $data нужными полями карты)
				else if ($data['split_payment'] == 1) {

					$one_payment_result = $this->make_one_card_payment(array_merge($data, $data['card1']), $order_user_data, $order_id);

					$errors = $one_payment_result['errors'];
					$paytrace_result = $one_payment_result['paytrace_result'];
					$expedia_hotels_error = $one_payment_result['expedia_hotels_error'];

					if ($this->paytrace_errors) {

						$this->paytrace_errors = array_shift($this->paytrace_errors);

						$this->paytrace_errors['error_cards']['partial_success'] = 0;
						$this->paytrace_errors['error_cards']['cards'] = $this->paytrace_error_cards;
					}
				} // Если карт несколько, то прогоняем их по очереди
				else {

					$summary_paytrace_result = true;

					for ($card_id = 1; $card_id <= $data['split_payment']; $card_id++) {

						if (
							!$data['partial_success']
							||
							($data['partial_success'] && $data['card' . $card_id]['error_payment_id'])
						) {

							$main_info = array_merge($data, $data['card' . $card_id]);

							$one_payment_result = $this->make_one_card_payment($main_info, $order_user_data, $order_id, true);

							if (!$one_payment_result['paytrace_result']) $summary_paytrace_result = false;
						}
					}

					$paytrace_result = $summary_paytrace_result;

					if (!$paytrace_result && $this->paytrace_errors) {

						$errors_pack = array();

						foreach ($this->paytrace_errors as $card_number => $card_errors) {

							foreach ($card_errors as $card_error) {

								$errors_pack[] = '(Card #... ' . $card_number . ') ' . $card_error;
							}
						}

						if ($this->paytrace_error_cards) {

							$errors_pack['error_cards']['cards'] = $this->paytrace_error_cards;

							if (count($this->paytrace_error_cards) < $data['split_payment']) {

								$errors_pack['error_cards']['partial_success'] = 1;
							} else {

								$errors_pack['error_cards']['partial_success'] = 0;
							}
						}

						$this->paytrace_errors = $errors_pack;
					}

				}
			}
			// Оплата счётом PayPal (на внешнем портале оплаты PP)
			else {

				if (!($paypal_payment = $this->build_paypal_payment($cart_totals, $order_id))) {

					$errors[] = 'PayPal order build process error';
				}
				else {

					// Платёж PP сформирован - значит ошибок paytrace точно нет
					$paytrace_result = true;
				}
			}
		}


		// Ошибки оплаты, выходим
		if (
			$errors
				||
			(is_live() && !$paytrace_result && ($order_total != 0) && (!$this->user->is_tech_support() || $expedia_hotels_error))
			) {

			file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]IS ERRORS\r\n", FILE_APPEND);

			if ($this->paytrace_errors) {

				foreach ($this->paytrace_errors as $key => $value) {

					if (!strcmp($key, 'error_cards')) {

						$errors[] = $value;
					}
					else {

						$errors[] = 'Payment error. ' . $value;
					}
				}
			}

			$this->errors = array_merge($this->errors, $errors);

			return false;
		}


		file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Session AFTER step2:\r\n\t", FILE_APPEND);
		foreach ($_SESSION as $key => $value) {

			if (!is_array($value)) {

				file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", $key . ":" . $value . " | ", FILE_APPEND);
			}
			else {

				file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", $key . ":Array(" . count($value) . ") | ", FILE_APPEND);
			}
		}
		file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "\r\n", FILE_APPEND);

		file_put_contents(f2b2h::$static['config']['path'] . "/../backup/order_data_log", "[".date("d-M-Y h:i:s T")."]Order_id AFTER: ".$order_id."\r\n", FILE_APPEND);

		// Для трекера аналитики
		$state_code = null;
		if (!empty($order_user_data['state_id'])) {

			$this->add_page('state');
			if ($state = $this->page_state->get($order_user_data['state_id'])) $state_code = $state['code'];
		}
		else if (!empty($order_user_data['state_name'])) {

			$state_code = $order_user_data['state_name'];
		}

		$country_name = null;
		$this->add_page('country');
		if ($country = $this->page_country->get($order_user_data['country_id'])) $country_name = $country['name'];

		$_SESSION['ga_user_billing_info'] = array (
			'type'			=> 'billing_info',
			'customer_id'	=> $user_id,
			'email'			=> $order_user_data['email'],
			'phone'			=> $order_user_data['phone'],
			'country'		=> $country_name,
			'street1'		=> $order_user_data['address'],
			'city'			=> $order_user_data['city'],
			'state'			=> $state_code,
			'zip'			=> $order_user_data['zip'],
		);

		// Заказ успешно оплачен/оформлен
		$complete_data = array (
			'order_id'			=> $order_id,
			'order_total'		=> $order_data['total'],
			'user_id'			=> $user_id,
			'user_notify'		=> ($data['notify'] ? true : false),
			'user_email'		=> $order_user_data['email'],
			'user_phone'		=> $order_user_data['phone'],
			'user_name'			=> $order_user_data['first_name'],
			'user_last_name'	=> $order_user_data['last_name'],
		);

		$success_result = array('status' => 'success', 'pay_by' => ($pay_by_card ? 'card' : 'paypal'));

		// Если оплата была картой, то в рамках данного же метода всё и заканчивается
		if ($pay_by_card) {

			$this->page_order->complete_after_payment_actions($complete_data);

			// Для лайва и тех юзера сразу меняем статус на удалённый
			if (is_live() && $this->user->is_tech_support()) $this->page_order->set_state($order_id, 'deleted');

			$success_result['order_id'] = $order_id;
		}
		// Если оплата через PayPal, то тут только передаём ссылку для редиректа
		// и запоминаем важную для завершения заказа информацию
		else {

			$complete_data['external_id'] = $paypal_payment['token'];

			$this->db->insert_update_no_id (
				'external_payments',
				$complete_data,
				array('order_id')
			);

			$success_result['redirect_link'] = $paypal_payment['link'];
		}

		alter_log('order_data_log', "---~~~--- END STEP2 ---~~~---", true, false);

		ajax_info($success_result);
	}
}

// Отмечаем оплату корзины через апи партнёрки
class request_api_rs_order_payment extends request_order_step2 {

	protected function defaults() {}
	protected function lists() {}

	protected function valid_rs_agent($rs_agent) {

		if (
			($this->config('mode') == 'admin_pay_order')
				&&
			(
				empty($rs_agent)
					||
				($rs_agent == '')
			)
		) {

			return false;
		}

		return true;
	}

	protected function valid_cart_id($cart_id) {

		$this->add_page('cart');

		if (!($cart = $this->page_cart->get($cart_id)) || !$cart['count']) return false;

		$this->db->query("
			SELECT
				id
			FROM
				orders
			WHERE
				cart_id = ?",
			$cart['id']
		);

		if ($order = $this->db->fetch()) {

			$this->errors[] = 'This cart is already paid for the order #'.$order['id'];
			return false;
		}

		return true;
	}

	protected function valid_customer_info($customer_info) {

		$required_customer_fields = array();

		if ($this->config('mode') == 'front_pay_order') {

			$required_customer_fields = array (
				'first_name',
				'last_name',
				'address',
				'zip',
				'country_id',
				'city',
				'phone',
				'email',
			);
		}
		else if ($this->config('mode') == 'admin_pay_order') {

			$required_customer_fields = array (
				'first_name',
				'last_name',
				'phone',
			);
		}

		$customer_info = $this->data['customer_info'];

		if (!is_array($customer_info)) return false;

		// Если даже на фронте state_id отсутствует (т.е. равен 0), то возможно передан кастомный штат (state_name)
		if ($this->config('mode') == 'front_pay_order') {

			if (
				!isset($customer_info['state_id'])
					||
				(
					empty($customer_info['state_id'])
						&&
					(
						empty($customer_info['state_name'])
							||
						!strcmp($customer_info['state_name'], '')
					)
				)
			) {
				$this->errors['state_id'] = 'Wrong state_id field data';
			}
		}

		foreach ($required_customer_fields as $field_name) {

			$field_error_text = 'Wrong '.$field_name.' field data';

			if (empty($customer_info[$field_name])) $this->errors[$field_name] = $field_error_text;
		}

		return true;
	}

	protected function failure($errors) {

		$this->page_api_rs->send_response($this->data, false, $errors);
	}


	protected function success($data) {

		$this->add_page('partner');

		$cart = $this->page_cart->get($data['cart_id']);

		$rs_agent = '';

		if ($this->config('mode') == 'admin_pay_order') {

			$rs_agent = $data['rs_agent'];
		}
		else if (empty($data['rs_agent']) || ($data['rs_agent'] == '')) {

			$rs_agent = 'online';
		}
		else {

			$rs_agent = $data['rs_agent'];
		}

		$order_data = array (
			'id'				=> null,
			'user_id'			=> 0,
			'payment_id'		=> 0,
			'cart_id'			=> $cart['id'],
			'made_from_rs'		=> true,
			'payment_method'	=> ($data['payment_method'] ? $data['payment_method'] : 'pay_online'),
			'rs_agent'			=> $rs_agent,
		);


		$user_data_fields = array (
			'first_name',
			'last_name',
			'phone',
			'phone2',
			'email',
			'address',
			'city',
			'state_id',
			'state_name',
			'country_id',
		);

		$this->db->query("
			SELECT
				id,
				user_id
			FROM
				orders
			WHERE
				cart_id = ?",
			$cart['id']
		);

		$payment_data = array();

		if ($order = $this->db->fetch()) {

			$order_data['id'] = $order['id'];
			$order_data['user_id'] = $order['user_id'];
		}
		else {

			// Для нового заказа дополнительные поля
			$order_data += array (
				'accesskey'			=> generate_access_key(),
				'total'				=> $cart['total'],
				'tax'				=> $cart['tax'],
			);

			if (
				($order_data['payment_method'] == 'gift')
					&&
				!empty($data['gift_certificate'])
				) {

				$order_data['gift_certificate'] = $data['gift_certificate'];
			}

			// Если есть информация о оплате, то сохраняем
			if (!empty($data['transaction_info'])) {

				$transaction_info = $data['transaction_info'];

				if (isset($transaction_info['card_type'])) $payment_data['type'] = $transaction_info['card_type'];
				if (isset($transaction_info['card_number'])) $payment_data['number'] = $transaction_info['card_number'];
				if (isset($transaction_info['transaction_id'])) $payment_data['transaction_id'] = $transaction_info['transaction_id'];
			}

			$this->add_page('user');

			$user_data = array (
				'group_id'		=> $this->user->temp_user_group(),
				'dt_reg'		=> format_date_for_db(null, true),
			);

			foreach ($user_data_fields as $field) {

				if (!empty($data['customer_info'][$field])) {

					$user_data[$field] = $data['customer_info'][$field];
				}
				else $user_data[$field] = '';
			}

			// Создаём нового юзера с пришедшеми данными
			$order_data['user_id'] = $this->page_user->save($user_data);
		}

		$order_id = $this->page_order->save($order_data);

		// Устанавливаем правильный custom_total исходя из разных условий
		if ($data['paid'] === 'false') {

			$this->page_cart->update_data($cart['id'], array('custom_total' => '0'));
		}
		else if (!isset($cart['data']['custom_total']) || is_null($cart['data']['custom_total'])) {

			$this->page_cart->update_data($cart['id'], array('custom_total' => $order_data['total']));
		}


		// Вынесено сюда, т.к. $order_id до этого момента может и не существовать
		if ($payment_data) {

			$payment_data['order_id'] = $order_id;
			$this->page_order->save_payment($payment_data);
		}

		// Сохраняем данные юзера для конкретно этого заказа
		$order_user_data = array (
			'order_id'		=> $order_id,
			'ip'			=> getUserRealIp(),
		);

		foreach ($user_data_fields as $field) {

			if (!empty($data['customer_info'][$field])) {

				$order_user_data[$field] = $data['customer_info'][$field];
			}
			else $order_user_data[$field] = '';
		}

		$this->db->insert_update_no_id('orders_users_data', $order_user_data, array('order_id'));


		if (!$order_data['id']) {

			$this->log->save_order_log(
				'Reservation created via reservation system api',
				$order_id
			);

			if (!in_array($order_data['payment_method'], array('hold', 'hold_online'))) {

				$this->page_order->set_state($order_id, 'paid');

				// Конфирмим части заказов, у партнёра которых стоит соответствующий флаг
				$this->page_order->autoconfirm_order_items($order_id);
			}
			else {

				$this->page_order->set_state($order_id, 'card_hold');
				
				$this->page_order->autoconfirm_order_items($order_id);
			}

			// Сохраняем промокод для заказа
			$this->price->order_save_promo($order_id);
		}

		// для удобства отладки через трипшок убираем корзину из сессии
		unset($_SESSION['cart_id']);


		// Данные созданного заказа и его частей
		$search_params = array (
			'confirmation_number'	=> $order_id,

			'extended_mode'			=> true,
			'revise_orders_state'	=> true,
			'single_order'			=> true,
		);

		$orders_result = $this->api->get_orders($search_params, false);

		$order = array_pop($orders_result['orders']);

		// И СМС, если позволяет флаг партнёра
		$partner = $this->page_partner->get($data['partner_id']);
		$partner['data'] = $this->db->decode_data($partner['data']);

		// Также нужно, чтобы юзер дал явное согласие на первое СМС, а при оплате админом отсутствие флага - также согласие
		if (!is_mode('front_pay_order') && !isset($data['customer_info']['send_sms'])) {

			$entry_sms_allowed = true;
		}
		else if (!empty($data['customer_info']['send_sms']) && $data['customer_info']['send_sms']) {

			$entry_sms_allowed = true;
		}
		else {

			$entry_sms_allowed = false;
		}

		$ignore_partner_flag = (is_mode('admin_pay_order') && isset($data['customer_info']['send_sms']));

		if (
			$entry_sms_allowed
				&&
			(
				$ignore_partner_flag
					||
				(
					!$ignore_partner_flag
						&&
					$partner['data']['flags']['send_sms']
				)
			)
		) {

			$link_voucher = 'https://reservations.bookwithrs.com/access/'.
							$data['partner_id'].'/'.
							$order['id'].'/user-'.
							md5($order['accesskey']);

			$this->page_order->send_sms_customer_new_reservation(
				$order_user_data['phone'],
				$partner['rs_order_prefix'].'-'.$order['id'],
				$link_voucher,
				$this->page_order->get_order_products($order['id']),
				true
			);
		}

		$order['order_id'] = $order['id'];

		$data = $order;

		$this->page_api_rs->send_response($data);
	}
}

// Выполняем оплату заказа, сформированную на фронте и сохраняем заказ "со всеми вытекающими" в случае успеха
class request_api_global_order_payment extends f2b2h_request {

	private $walk_in;
	private $booked_by_id;

	protected function defaults() {}
	protected function lists() {}

	protected function block_payment($session_id, $unblock = false) {

		if(empty($session_id)) return false;

		$this->db->query("
			UPDATE
				cart_session
			SET
				is_blocked = ?
			WHERE
				session_id = ?",
			(($unblock) ? 0 : 1),
			$session_id
		);
	}

	protected function valid_session_id($session_id) {

		$this->add_page('cart');

		if (empty($session_id)) {

			$this->errors[] = 'Wrong Session ID';
			return false;
		}
		else {

			$cart_id = $this->page_api_global->get_session_cart_id($session_id);

			if ($cart_id === false) {

				$this->errors[] = 'The Session Contains no Cart';
				return false;
			}
			else if (!($cart = $this->page_cart->get($cart_id))) {

				$this->errors[] = 'Invalid Cart';
				return false;

				// TODO Если корзина просрочена, то удаляем и ошибка. иначе продлеваем её

			}

			$this->db->query("
				SELECT
					is_blocked
				FROM
					cart_session
				WHERE
					is_blocked = 1
						AND
					session_id = ?",
				$session_id
			);

			if($is_blocked = $this->db->fetch()) {

				$this->errors[] = 'The Session is blocked for pay';
				return false;

			}
			else {

				// Блокируем сессию для избежания снятия средств с карты несколько раз
				$this->block_payment($session_id);
			}

		}

		// TODO М.б. тут нужно не всю корзину писать, а только некоторые данные
		$this->data['cart_data'] = $cart;

		return true;
	}

	// Данные этого поля сами по себе ошибку не провоцируют, но он очень удобен чтобы разобрать его на части:
	// в одной части может быть walk-in pass, а в другой - логин юзера, который оформляет заказ
	protected function valid_special_row($special_row) {

		$this->walk_in = false;
		$this->booked_by_id = 0;

		if (empty($special_row)) return true;


		$special_row_parts = explode("|", $special_row);

		foreach ($special_row_parts as $special_row_part) {

			$special_row_part = trim($special_row_part);

			if (
				$this->config('office_password')
					&&
				((string)$this->config('office_password') === (string)$special_row_part)
			) {

				$this->walk_in = true;
			}
			else if (!$this->booked_by_id) {

				$this->db->query("
					SELECT
						id
					FROM
						users
					WHERE
						name = ?",
					$special_row_part
				);

				if ($booked_by = $this->db->fetch()) $this->booked_by_id = $booked_by['id'];
			}
		}

		return true;
	}

	protected function valid_stripe_token($stripe_token) {

		if (empty($stripe_token)) {

			// Walk-in оплата
			if ($this->walk_in) {

				return true;
			}
			else {

				$this->errors[] = 'Wrong Payment Data';
				return false;
			}
		}
		else {

			$this->walk_in = false;

			$stripe_data = $this->page_order->stripe_call('get_token', $stripe_token);

			if (!$stripe_data['success']) {

				$this->errors[] = $stripe_data['error'];
				return false;
			}
			else {

				$this->data['stripe_data'] = $stripe_data['data'];
				return true;
			}
		}
	}

	protected function valid_user_id($user_id) {

		$this->add_page('user');

		if (empty($user_id) || !($user = $this->page_user->get($user_id))) {

			$this->errors[] = 'Account info has not been filled properly';

			return false;
		}
		else {

			$this->data['customer_info'] = $user;
			return true;
		}
	}

	protected function failure($errors) {

		// Разблокируем сессию в случае возникновения ошибок
		$this->block_payment($this->data['session_id'], true);

		foreach ($errors as $error_id => $error) {

			if (!is_numeric($error_id)) unset($errors[$error_id]);
		}

		$this->page_api_global->send_response(array (
			'success'	=> false,
			'errors'	=> $errors,
		));
	}



	// Здесь объединены сам платёж и запись данных о платеже в БД
	// Запись в БД это упрощённый и модифицированный под Stripe вариант page_order::save_payment
	// Ориентировочно этот код должен со временем заменить собой этого "предка"
	protected function make_payment($data) {

		$result = array (
			'success'	=> true,
			'errors'	=> array()
		);

		// Предварительно сохраняем (пересохраняем) платёж
		if ($data['is_office']) {

			$payment = array (
				'order_id'				=> $data['order_id'],
				'type'					=> 'undefined',
				'last_payment_response'	=> '',
				'last_payment_errors'	=> '',
				'number'				=> 'cash',
				'first_name'			=> '',
				'last_name'				=> '',
				'data'					=> '{}',
			);
			$payment_data = array();
		}
		else {

			$payment = array (
				'order_id'				=> $data['order_id'],
				'type'					=> (!empty($data['stripe_data']['card']['brand']) ? $data['stripe_data']['card']['brand'] : ''),
				'last_payment_response'	=> '',
				'last_payment_errors'	=> '',
				'number'				=> (!empty($data['stripe_data']['card']['last4']) ? $data['stripe_data']['card']['last4'] : ''),
				'first_name'			=> (!empty($data['stripe_data']['card']['name']) ? $data['stripe_data']['card']['name'] : ''),
				'last_name'				=> '',
				'data'					=> '{}',
			);
			$payment_data = array (
				'stripe' => array (
					'token_id' => $data['stripe_token']
				)
			);
		}

		$this->db->query("
			SELECT
				payments.id
			FROM
				payments
			WHERE
				payments.order_id = ?",
			$data['order_id']
		);

		if ($previous_payment = $this->db->fetch()) {

			$payment['id'] = $previous_payment['id'];
		}

		$payment_id = $this->db->insert_update('payments', $payment);


		if (!$data['is_office']) {

			// Создаём пользователя в Stripe чтобы иметь больше функций потом
			$customer_response = $this->page_order->stripe_call('customer', array (
				'email' => $data['customer_info']['email'],
				'source' => $data['stripe_data']['id'],
			));

			if (!$customer_response['success'] || empty($customer_response['data']['id'])) {

				$result['success'] = false;
				$result['errors'][] = 'The payment has been rejected by the gateway';

				if (!$customer_response['success']) {

					$payment = array(
						'id' => $payment_id,
						'last_payment_errors' => '(Customer) '.
							($customer_response['success'] ? '[No ID]' : $customer_response['error']),
					);
				}
			}
			else {

				$payment_data['stripe']['customer_id'] = $customer_response['data']['id'];

				// Собственно попытка оплаты
				$payment_response = $this->page_order->stripe_call('charge', array(
					'amount' => intval($data['cart_data']['total'] * 100),
					'currency' => 'usd',
					'capture' => true,
					'customer' => $customer_response['data']['id'],
					'description' => 'Order ID: TRS-' . $data['order_id']
				));

				if (!$payment_response['success'] || !empty($payment_response['data']['failure_code'])) {

					$result['success'] = false;
					$result['errors'][] = 'The payment has been rejected by the gateway';

					if (!$payment_response['success']) {

						$payment = array(
							'id' => $payment_id,
							'last_payment_errors' => '(Charge) '.$payment_response['error'],
						);
					}
					else {

						$payment = array(
							'id' => $payment_id,
							'last_payment_errors' => '(Charge) '.$payment_response['data']['outcome']['seller_message'],
						);
					}
				}
				else {

					$payment_data['stripe']['charge_id'] = $payment_response['data']['id'];

					$payment = array(
						'id' => $payment_id,
						'last_payment_response' => $payment_response['data']['outcome']['seller_message'],
						'transaction_id' => $payment_response['data']['id'],
					);
				}
			}

			$payment['data'] = $this->db->encode_data($payment_data);

			// Обновляем данные платёжной записи в БД перед выходом из метода
			$this->db->insert_update('payments', $payment);
		}


		return $result;
	}



	protected function success($data) {

		$office_order = $this->walk_in;

		$response = array (
			'success' => false,
			'data' => array (
				'errors'	=> array(),
				'messages'	=> array(),
			)
		);


		// Если есть части Fareharbor, то их оформляем у себя только после завершения оплаты
		// А сейчас все их только валидируем
		$fh_validation = $this->page_order->fh_validate_order($data['cart_data']['id']);
		if (!$fh_validation['success']) {

			$this->errors[] = $fh_validation['error'];

			return false;
		}


		$order_data = array (
			'id'				=> null,
			'user_id'			=> $data['customer_info']['id'],
			'order_by_user_id'	=> $this->booked_by_id,
			'payment_id'		=> 0,
			'cart_id'			=> $data['cart_data']['id'],
			'made_from_rs'		=> 0,
			'is_office'			=> ($office_order ? 1 : 0),
			'payment_method'	=> 'tripshock',
			'rs_agent'			=> 'tripshock',
		);


		$this->db->query("
			SELECT
				id,
				user_id,
				accesskey
			FROM
				orders
			WHERE
				cart_id = ?",
			$data['cart_data']['id']
		);

		if ($order = $this->db->fetch()) {

			$order_data['id'] = $order['id'];
			$order_data['user_id'] = $order['user_id'];
			$order_data['accesskey'] = $order['accesskey'];
		}
		else {

			// Для нового заказа дополнительные поля
			$order_data += array (
				'accesskey'			=> generate_access_key(),
				'total'				=> $data['cart_data']['total'],
				'tax'				=> $data['cart_data']['tax'],
			);
		}


		// Логиним менеджера в системе бек-энда чтобы логи писались от его имени
		if ($this->booked_by_id) {

			$this->user->login($this->booked_by_id);
			$this->user->fill_data();
		}

		$order_id = $this->page_order->save($order_data);
		$data['order_id'] = $order_id;



		// Сохраняем данные юзера для конкретно этого заказа
		$order_user_data = array (
			'order_id'		=> $order_id,
			'ip'			=> getUserRealIp(),
			'first_name'	=> (!empty($data['customer_info']['first_name']) ? $data['customer_info']['first_name'] : ''),
			'last_name'		=> (!empty($data['customer_info']['last_name']) ? $data['customer_info']['last_name'] : ''),
			'phone'			=> (!empty($data['customer_info']['phone']) ? $data['customer_info']['phone'] : ''),
			'phone2'		=> (!empty($data['customer_info']['phone2']) ? $data['customer_info']['phone2'] : ''),
			'email'			=> (!empty($data['customer_info']['email']) ? $data['customer_info']['email'] : ''),
			'address'		=> '',
			'city'			=> '',
			'state_id'		=> 0,
			'state_name'	=> '',
			'country_id'	=> 5,
		);

		$this->db->insert_update_no_id('orders_users_data', $order_user_data, array('order_id'));

		$data['is_office'] = $office_order;


		// Пробуем оплатить
		$payment_response = $this->make_payment($data);



		// Платёж прошёл - заказ обретает статус оформлденного и делаем все пост-действия
		if ($payment_response['success']) {

			// Удаляем связь с сессией  т.к. больше прямого доступа к корзине нет
			$this->db->delete('cart_session', array (
				'cart_id'	=> $data['cart_data']['id'],
			));


			if (!($this->page_order->fh_book_order($order_id, array (
				'user_name'			=> $order_user_data['first_name'],
				'user_last_name'	=> $order_user_data['last_name'],
				'user_phone'		=> $order_user_data['phone'],
				'user_email'		=> $order_user_data['email'],
			)))) {

				$response['data']['messages'] = 'Warning! Your order has not been completed due to third party service error! Please call '.
					$this->config('site_phone').' to complete the order';
			}

			$this->page_order->set_state($order_id, 'paid');

			// Конфирмим части заказов, у партнёра которых стоит соответствующий флаг
			$this->page_order->autoconfirm_order_items($order_id);

			// Сохраняем промокод для заказа
			$this->price->order_save_promo($order_id);

			// Создаём промокод для заказа, если включена опция в конфиге
			if (!$this->config('after_order_promo_is_disabled')) {

				$this->add_page('promo');
				$this->page_promo->generate_promo_code($order_id);
			}


			// Письмо с подтверждением юзеру
			$this->page_order->mail_user_order_confirmation($order_id);
			// Письмо и СМС партнёру
			if (is_live()) send_order_info_to_vendor($order_id);


			if (is_live() && (!empty($data['jamcom']) || !empty($data['jamtracker']))) {

				$amount = $order_data['total'];
				if ($this->user->is_tech_support()) $amount = 0;

				if (!empty($data['jamcom'])) {

					$cookie_jamcom = $data['jamcom'];

					$cookie_jamcom_parts = explode ("-", $cookie_jamcom);
					$member_id = $cookie_jamcom_parts[0];
				}

				if (!empty($data['jamtracker'])) {

					$jamtracker = $data['jamtracker'];
				}

				$jam_url = "http://affiliates.tripshock.com/sale"
					."/amount/".$amount
					."/trans_id/".$order_id
					.(!empty($data['jamcom']) ? "/member_id/".$member_id."/custom_mid/".$cookie_jamcom : "")
					.(!empty($data['jamtracker']) ? "/tracking_id/".$jamtracker : "");

				$jam_response = get_remote_data_curl($jam_url);
			}

			$order = array (
				'id'		=> $order_id,
				'format_id'	=> 'TRS-'.$order_id,
				'accesskey'	=> $order_data['accesskey'],
			);

			if (!empty($data['send_sms']) && $data['send_sms']) {

				$this->page_order->send_sms_customer_new_reservation(
					$order_user_data['phone'],
					$order['format_id'],
					$this->config('website_url').'/order/access/?key='.$order['accesskey'],
					$this->page_order->get_order_products($order_id)
				);
			}

			$response['success'] = true;
			$response['data'] = $order;
		}
		// Платёж не прошёл - возвращаем ошибку, а заказ остаётся в статусе made
		else {

			// Разблокируем сессию в случае возникновения ошибок
			$this->block_payment($this->data['session_id'], true);

			$response['data']['errors'] = $payment_response['errors'];
		}

		$this->user->logout();

		$this->page_api_global->send_response($response);
	}
}

// Поиск заказов для необходимого таба
// и вывод формы с параметрами поиска
// Также используется в "усечённом" виде для page_order::mode_affiliate_orders
class request_order_search extends f2b2h_request {

	protected function defaults() {

		$this->defaults['made_from'] = format_time(strtotime("-1 month"), false);
		$this->defaults['made_to'] = format_time(null, false);

		$this->defaults['orders_per_page'] = 50;

		$this->defaults['tab'] = 'all';
		$this->defaults['page_num'] = 1;

		if (!$this->data) $this->data = $this->defaults;

		$this->defaults['submit'] = $this->lang('search');
	}

	protected function lists() {

		// Пустой элемент произвольного списка
		$any_item = array(0 => 'Any');

		$this->lists['product_type_id'] = $any_item + product_types();

		// Партнёры по трипам
		$trip_partners = $this->user->get_list(false, $this->user->trip_partner_group(), true, 'title,last_name');

		$this->lists['partner_id'] = $any_item + $trip_partners;

		// Список продуктов(трипов и отелей)
		$products = array();

		$this->db->query("
			SELECT
				id,
				name,
				product_type_id
			FROM
				products
			WHERE
				disabled = 0
				".($this->user->is_partner() ? " AND partner_id = ".$this->user->data('id') : "")."
				".($this->user->is_trip_partner() ? " AND product_type_id = ".product_type('trip') : "")."
				".($this->user->is_hotel_partner() ? " AND product_type_id = ".product_type('hotel') : "")."
			ORDER BY
				product_type_id, name"
		);
		while ($product = $this->db->fetch()) {

			if (
				(
					!$this->user->is_partner()
						&&
					(product_type($product['product_type_id']) != 'trip')
				)
					||
				($product['name'] == '')
			) {

				continue;
			}

			$products[$product['id']] = $product['name'];
		}

		$this->lists['product_id'] = $any_item + $products;

		// Список городов
		$this->add_page('city');
		$this->lists['city_id'] = array(0 => 'Any') + $this->page_city->get_list(array('only_visible' => true), false);

		// Список регионов
		$this->add_page('region');
		$this->lists['region_id'] = array(0 => 'Any') + $this->page_region->get_list(array(), false);

		// Список для поиска по менеджеру, создавшему заказ, составляем из списка админ групп
		$managers = array();
		$managers_groups = array (
			'admin'			=> $this->user->group_main_admin(),
			'manager'		=> $this->user->group_manager(),
			'reservations'	=> $this->user->group_reservations(),
		);

		foreach ($managers_groups as $manager_type => $manager_group) {

			$manager_group_users = $this->user->get_list(false, $manager_group, true, 'title,last_name');

			foreach ($manager_group_users as $user_id => $manager_name) {

				$managers[$user_id] = ucfirst($manager_type).' - '.$manager_name;
			}
		}

		$this->lists['order_by_user_id'] = $any_item + array(-1 => 'All') + $managers;

		$this->add_page('activity');
		$this->lists['activity_id'] = $any_item + $this->page_activity->get_formated_list();

		if (is_mode('affiliate_orders', 'affiliate_tab_load')) {

			$this->lists['affiliate_id'] = array(0 => 'Any');

			if ($portals_ids = $this->portal->get_list()) {

				$portals_ids = array_keys($portals_ids);

				foreach ($portals_ids as $portal_id) {

					$portal = $this->portal->get(array(
						'partner_id' => $portal_id,
						'full' => true,
					));

					$affiliate_name = $portal['affiliate']['name'] .
						' (' . $portal['affiliate']['first_name']
						. ' ' . $portal['affiliate']['last_name'] . ')';

					$this->lists['affiliate_id'][$portal['affiliate_id']] = $affiliate_name;
				}
			}
		}

		$this->lists['orders_per_page'] = array (
			25		=> '25',
			50		=> '50',
			100		=> '100',
			150		=> '150',
			250		=> '250',
			500		=> '500',
			1000	=> '1000',
		);
	}


	protected function success($data) {

		// Режим списка affiliate-заказов
		$affiliates = is_mode('affiliate_orders', 'affiliate_tab_load');

		// Проверяем пришедшие даты и пустые заменяем реальными
		$date_fields = array(
			'made',
			'arrival',
		);

		if (!$affiliates) {
			$date_fields += array(
				'cancelled',
			);
		}

		foreach ($date_fields as $date_field) {

			// Если указан только один из краёв периода - выставляем второй
			if (!$data[$date_field.'_from'] && $data[$date_field.'_to']) {

				$data[$date_field.'_from'] = format_date_for_db(strtotime("-20 year"));
			}
			else if (!$data[$date_field.'_to'] && $data[$date_field.'_from']) {

				$data[$date_field.'_to'] = format_date_for_db(strtotime("+20 year"));
			}

			// Обнуляем соответствующие поля, если между ними нет валидного временного периода
			if (strtotime($data[$date_field.'_from']) > strtotime($data[$date_field.'_to'])) {

				$data[$date_field.'_from'] = null;
				$data[$date_field.'_to'] = null;
			}
		}

		if (!$affiliates) {

			// Для табов payment_due и payment_received
			if (in_array($data['tab'], array('payment_due', 'payment_received'))) {

				// ставим соответствующий флаг, т.к. формат вывода разный
				$this->tpl('payment_due', true);
				// Для таба payment_received дополнительный флаг, чтобы подкорректировать вывод
				if ($data['tab'] == 'payment_received') {

					$this->tpl('payment_received', true);
				}

				// режим вывода только обычный
				$data['extended_mode'] = false;
			}

			// Для arrival7days
			if (in_array($data['tab'], array('arrival7days'))) {

				$this->tpl('arrival7days', true);

				// режим вывода только cart_items
				$data['extended_mode'] = true;
			}

			$this->tpl('extended_mode', $data['extended_mode']);
			$this->tpl('is_hotel_partner', $this->user->is_hotel_partner());

			// В случае расширенного режима к нам могут прийти параметры направления и типа сортировки
			if ($data['extended_mode']) {

				$order_by = $data['order_by'];
				$order_dir = $data['order_dir'];
				$order_by_translation = array(
					'confirmation_number' => 'order_confirmation_number',
					'order_date' => 'order_date',
					'customer_name' => 'customer_last_name',
					'status' => 'order_state',
					'trip_name' => 'trip_name',
					'calendar_date' => 'calendar_date',
					'gross_total' => 'sub_gross_total',
					'net_total' => 'sub_net_total',
					'tax' => 'sub_tax',
				);

				$order_by = isset($order_by_translation[$order_by]) ? $order_by_translation[$order_by] : null;

				if (empty($order_dir)) $order_dir = 'desc';

				if (!in_array($order_dir, array('asc', 'desc'))) $order_dir = 'desc';

				$this->tpl('current_order_by', array_search($order_by, $order_by_translation));
				$this->tpl('current_order_dir', strtolower($order_dir));
			} else {

				$order_by = 'order_date';
				$order_dir = 'desc';
			}

			$data['order_by'] = $order_by;
			$data['order_dir'] = $order_dir;
		}
		else {

			$order_by = 'order_date';
			$order_dir = 'desc';
			$data['order_by'] = $order_by;
			$data['order_dir'] = $order_dir;

			$data['affiliate_orders'] = true;
		}

		if (!$data['orders_per_page']) $data['orders_per_page'] = $this->page_order->orders_per_page;

		if ($this->user->is_partner()) {

			if (!$affiliates) {

				$data['partner_id'] = $this->user->data('id');
			}
			else {

				$data['affiliate_id'] = $this->user->data('portal_affiliate_id');
			}
		}

		// Получаем список id заказов, попадающих под условия, и параметры для пагинации
		$items = $this->page_order->get_order_list($data);

		// Параметры пагинации
		$pagination = $items['pagination'];
		// Сами id заказов
		$orders_id = $items['orders_list'];

		$data['order_by'] = $order_by;
		$data['order_dir'] = $order_dir;

		// Получаем всю остальную информацию для таблицы
		$orders = $this->page_order->get_orders($orders_id, $data);

		$items = array();

		if (!$affiliates) {

			// Считаем общие суммы по заказам
			$orders_total = array(
				'gross_total' => 0,
				'net_total' => 0,
				'comission' => 0,
			);


			// Отправляем в шаблон список порталов для случая, если будет доступна опция назначения заказа на портал
			$this->db->query("
				SELECT
					portal.user_id,
					portal.affiliate_id,

					users.name,
					users.first_name,
					users.last_name
				FROM
					portal
					JOIN users ON users.id = portal.user_id
				WHERE
					portal.is_disabled = 0"
			);
			$db_res = $this->db->result;

			while ($portal_user = $this->db->fetch($db_res)) {

				$this->tpl->loop('portal_user', array (
					'affiliate_id'	=> $portal_user['affiliate_id'],
					'name'			=> $portal_user['name'].' ('.$portal_user['first_name'].' '.$portal_user['last_name'].')',
				));
			}



			// Флаг для таба arrival7days
			$arrival7days = ($data['tab'] == 'arrival7days');

			// Для каждого заказа формируем данные для отправки в шаблон.
			// В массив $items построчно складываем информацию о заказах и их элементах, если включён расширенный режим,
			// и уже этот массив отправляем в шаблон.
			foreach ($orders as $order) {

				// Собираем названия трипов и даты прибытия для тултипа
				$cart_items_short_list = '';
				// Собираем комментарии гостей и партнёров по частям заказа
				$comment_guest_partner = '';
				// Список статусов для адекватного отображения основной строки заказа в табах
				$status_list = array();
				// Смотрим есть ли подарочный трип в заказе
				$gift = false;
				// Если все части заказа со статусом on_request, то подсвечиваем основную строку
				$on_request_count = 0;

				// Заранее проходим по частям для сбора доп. информации, необходимой и в обычном режиме
				foreach ($order['cart_items'] as $id => $cart_item) {

					$book_info = $cart_item['book_info'];

					if (!empty($book_info['comments']['guest']) || !empty($book_info['comments']['partner'])) {

						$comment_guest_partner .= '<div><div class="caption">' . $book_info['product']['name'] . '</div>';

						if (!empty($book_info['comments']['guest'])) {

							$comment_guest_partner .= '<strong>Guest:</strong> ' . escape($book_info['comments']['guest']) . '<br />';
						}
						if (!empty($book_info['comments']['partner'])) {

							$comment_guest_partner .= '<strong>Partner:</strong> ' . escape($book_info['comments']['partner']) . '<br />';
						}

						$comment_guest_partner .= '</div>';
					}

					// Дата прибытия и название продукта для тултипа
					if ($cart_item['arrival_date']) {

						$cart_items_short_list .= '
						<br />
						' . $cart_item['product_name'] . '
						<br />
						Arrival on ' . format_time($cart_item['arrival_date'], false) . '
						<br />';
					} else {

						$cart_items_short_list .= '
						<br />
						' . $cart_item['product_name'] . '
						<br />';
					}

					// Статусы главной строки для табов
					if (!in_array('item_' . $cart_item['state'], $status_list)) {

						$status_list[] = 'item_' . $cart_item['state'];
					}

					// Вешаем значок подарка, если есть хоть один подарочный трип
					if (!empty($cart_item['gift_code'])) $gift = true;

					if ($cart_item['on_request']) $on_request_count++;
				}

				$order_on_request = false;
				if (($on_request_count == count($order['cart_items'])) && ($order['state'] == 'paid')) {

					$order_on_request = true;
				}

				// Для таба payment_due получаем последний день оплаты
				$payment_overdue_class = '';
				$payment_last_day = null;
				if ($data['tab'] == 'payment_due') {

					$payment_last_day = $order['payment_last_day'];

					// Если последний день оплаты прошёл, то выделяем строку красным бордером
					if (date("Y-m-d") >= $payment_last_day) {

						$payment_overdue_class = ' payment_overdue';
					}

					$payment_last_day = format_time($payment_last_day, false);
				} else if ($data['tab'] == 'payment_received') {

					$payment_last_day = format_time($order['pay_delay_received']);
				}


				$orders_total['gross_total'] += $order['total'];
				$orders_total['net_total'] += $order['net'];
				$orders_total['comission'] += $order['comission'];


				// Для таба arrival7days сами заказы нам не нужны
				if (!$arrival7days) {

					// Если заказ сделан из портала, то ставим соответствующую отметку
					if (!empty($order['affiliate_id'])) {

						$order_affiliate_user = '';

						$this->db->query("
							SELECT
								portal.user_id,

								users.name
							FROM
								portal
								JOIN users ON users.id = portal.user_id
							WHERE
								portal.affiliate_id = ?",
							$order['affiliate_id']
						);
						$db_res = $this->db->result;

						if ($portal_user = $this->db->fetch($db_res)) $order_affiliate_user = $portal_user['name'];
					}

					$items[] = array(
						'order_id' => $order['id'],
						'group_order_id' => (!empty($order['group_order_id']) ? $order['group_order_id'] : null),
						'affiliate_id' => (!empty($order['affiliate_id']) ? $order_affiliate_user : null),
						'cart_item_id' => null,
						'is_head' => true,
						'class' => $order['state'] . ($data['extended_mode'] ? ' ' . implode(" ", $status_list) : ' item_' . $order['state']) . $payment_overdue_class,
						'order_confirmation_number' => order_confirmation_number($order['format_id']),
						'cart_items_short_list' => $cart_items_short_list,
						'order_date' => format_time($order['made']),
						'customer_name' => $this->user->format_name(array('first_name' => $order['customer_first_name'], 'last_name' => $order['customer_last_name'])),
						'customer_ip' => $order['customer_ip'],
						'is_office' => $order['is_office'] ? true : false,
						'our_user' => ($order['group_id'] == 1 || $order['group_id'] == 5 || $order['group_id'] == 6) ? $order['booked_by_name'] : null,
						'comment_guest_partner' => $comment_guest_partner,
						'comment_admin' => ($this->user->is_admin() ? escape($order['comment_admin']) : ''),
						'status' => ucfirst($order['state']) . ' on ' . format_time($order['state_time']),
						'is_cancelled' => ($order['state'] == 'cancelled'),
						'is_completed' => ($order['state'] == 'completed'),
						'product_name' => null,
						'gift' => $gift,
						'arrival_date' => null,
						'tickets' => null,
						'promo_id' => !empty($order['discount']['promo']['id']) ? $order['discount']['promo']['id'] : null,
						'promo_name' => !empty($order['discount']['promo']['name']) ? $order['discount']['promo']['name'] : null,
						'promo_value' => !empty($order['discount']['total']) ? money($order['discount']['total']) : null,
						'gross_total' => money($order['total']),
						'net_total' => money($order['net']),
						'comission' => money($order['comission']),
						'pay_delay_amount' => $order['pay_delay_amount'] ? money($order['pay_delay_amount']) : null,
						'payment_last_day' => $payment_last_day,
						'confirmation' => !empty($order['confirmation_numbers']) ? implode(", ", $order['confirmation_numbers']) : '',
						'expedia_confirmations' => ($order['expedia_confirmations'] ? implode(", ", $order['expedia_confirmations']) : ''),
						'expedia_itinerary' => implode(', ', $order['expedia_itinerary']),
						'show_confirmation_input' => null,
						'has_hotel_items' => $order['has_hotel_items'],
						'on_request' => $order_on_request,
						'has_various_partners' => $order['has_various_partners'],
						'cities_names' => trim(implode(',', $order['cities_names']), ','),
					);
				}

				// В случае расширенного режима выводим строки с информацией о частях заказа
				if ($data['extended_mode']) {

					foreach ($order['cart_items'] as $cart_item) {

						// Если есть доп. опции c ненулевой ценой - составляем их в виде строки для вывода вместе с билетами
						$additional_options_string = '';
						if (!empty($cart_item['book_info']['additional_options'])) {

							foreach ($cart_item['book_info']['additional_options'] as $option) {

								if ($option['type'] == 'select') {

									$additional_options_string .= ' + ' . $option['value']['name'] . ' (' . money($option['value']['price'], true) . ')';
								} else {

									$additional_options_string .= ' + [' . $option['name'] . ' : ' . $option['value']['name'] . ']';
								}
							}
						}

						if (product_type($cart_item['product_type_id']) == 'trip') {

							$booked_items = format_tickets_string($cart_item['book_info']['tickets']) . $additional_options_string;
						} else if (product_type($cart_item['product_type_id']) == 'hotel') {

							$booked_items = format_rooms_string($cart_item['book_info']);
						}

						// Если отменён весь заказ, или только его текущая часть - сумма скидки = 0
						if (($order['state'] == 'cancelled') || ($cart_item['state'] == 'cancelled')) {

							unset($order['discount']['cart_items'][$cart_item['id']]);
						}

						$items[] = array(
							'order_id' => $order['id'],
							'cart_item_id' => $cart_item['id'],
							'is_head' => false,
							'class' => (($cart_item['state'] == $order['state']) && !$arrival7days ? '' : $cart_item['state'] . ' ') . 'item_' . $cart_item['state'],
							'order_confirmation_number' => null,
							'cart_items_short_list' => null,
							'order_date' => null,
							'customer_name' => (!$arrival7days ? null : $this->user->format_name(array('first_name' => $order['customer_first_name'], 'last_name' => $order['customer_last_name']))),
							'customer_ip' => null,
							'is_office' => null,
							'comment_guest' => (!$arrival7days ? '' : $cart_item['book_info']['comments']['guest']),
							'comment_partner' => (!$arrival7days ? '' : $cart_item['book_info']['comments']['partner']),
							'comment_admin' => (!$arrival7days && $this->user->is_admin() ? null : $order['comment_admin']),
							'status' => null,
							'is_cancelled' => ($cart_item['state'] == 'cancelled'),
							'is_completed' => ($cart_item['state'] == 'completed'),
							'product_name' => $cart_item['product_name'],
							'gift' => !empty($cart_item['gift_code']) ? 1 : 0,
							'arrival_date' => ($cart_item['arrival_date'] ? format_time($cart_item['arrival_date'], false) : ''),
							'tickets' => $booked_items,
							'promo_id' => null,
							'promo_name' => null,
							'promo_value' => !empty($order['discount']['cart_items'][$cart_item['id']]) ? money($order['discount']['cart_items'][$cart_item['id']]['discount']) : null,
							'gross_total' => money($cart_item['total']),
							'net_total' => $cart_item['net'],
							'comission' => money($cart_item['comission']),
							'pay_delay_amount' => null,
							'payment_last_day' => null,
							'confirmation' => $cart_item['confirmation_number'],
							'expedia_confirmations' => ($cart_item['expedia_confirmations'] ? implode(", ", $order['expedia_confirmations']) : null),
							'expedia_itinerary' => $cart_item['expedia_itinerary'],
							'show_confirmation_input' => (!$cart_item['is_affiliate'] && (product_type($cart_item['product_type_id']) == 'hotel')) ? true : false,
							'has_hotel_items' => $order['has_hotel_items'],
							'on_request' => $order_on_request ? false : ($cart_item['on_request'] && ($cart_item['state'] == 'paid')),
							'has_various_partners' => null,
							'city_name' => $cart_item['city_name'],
						);
					}
				}
			}
		}
		// Для списка партнёрских заказов информации нужно намного меньше
		else {

			$this->add_page('partner');
			$this->add_page('cart_item');

			// Общие суммы
			$orders_total = array(
				'gross_total' => 0,
				'commission' => 0,
			);

			// Для каждого заказа формируем данные для отправки в шаблон.
			// В массив $items построчно складываем информацию о заказах и уже этот массив отправляем в шаблон.
			foreach ($orders as $order) {

				// Портал с дополнительными данными
				$portal = $this->portal->get(array (
					'affiliate_id'	=> $order['affiliate_id'],
					'full'			=> true,
				));

				// Общая сумма заказа и сумма комиссии портала
				$order_totals = $this->page_cart_item->get_cart_totals($order['cart_id']);

				if ($order['items_count'] == count($order['cart_items'])) {

					$commission = $order['affiliate_commission'];
				}
				else {

					$portal_commission = $this->page_order->calculate_portal_commission($order['id'], null, null, true);

					$cart_items = array_keys($order['cart_items']);
					$commission = 0;

					foreach ($cart_items as $cart_item) {

						$commission += $portal_commission['cart_items'][$cart_item];
					}

				}

				$orders_total['gross_total'] += $order_totals['total']['gross_with_tax'];
				$orders_total['commission'] += $commission;


				$arrival_date = '';
				$booked_products = '';

				// Если у заказа несколько частей, то будет несколько arrival_dates и названий продуктов
				foreach ($order['cart_items'] as $id => $cart_item) {

					$product_name = '('.product_type_format($cart_item['product_type_id']).') "'.$cart_item['product_name'].'"';

					$arrival_date .= ($arrival_date == '' ? '' : '<br />') . format_time($cart_item['arrival_date'], false);
					$booked_products .= ($booked_products == '' ? '' : '<br />') . $product_name;
				}

				$affiliate_name = $portal['affiliate']['name'].
									' ('.$portal['affiliate']['first_name']
									.' '.$portal['affiliate']['last_name'].')';

				$items[] = array(
					'order_id'		=> $order['format_id'],
					'state'			=> $order['state'],
					'affiliate'		=> $affiliate_name,
					'products'		=> $booked_products,
					'email'			=> ($order['customer_notify'] ? $order['customer_email'] : null),
					'class'			=> $order['state'],
					'gross_total'	=> money($order_totals['total']['gross_with_tax']),
					'comission'		=> money($commission),
					'order_date'	=> format_time($order['made']),
					'arrival_date'	=> $arrival_date,
				);
			}
		}

		// Выдаём список заказов построчно в шаблон
		foreach ($items as $item) $this->tpl->loop('items', array(), $item);

		// И общие суммы
		$this->tpl->loop('total_row', array_map('money', $orders_total));


		// Пагинация
		pagination_main(
			$pagination['orders_count'],
			$data['orders_per_page'],
			$data['page_num']
		);

		if (!$affiliates) {

			$count_tabs_orders = $this->url->get_int('count_tabs_orders');
			if ($data['cancelled_from']) $count_tabs_orders = false;

			// Если надо - считаем кол-во заказов для разных табов
			if ($count_tabs_orders) {

				// Флаг в функцию поиска заказов, для корректировки
				$data['count_tabs_orders'] = true;

				$orders_count_tab = array(
					'unconfirmed' => 0,
					'paid' => 0,
					'payment_due' => 0,
					'made' => 0,
					'arrival7days' => 0,
				);
				foreach ($orders_count_tab as $tab_name => $count) {

					$data['tab'] = $tab_name;

					$items = $this->page_order->get_order_list($data);

					$orders_count_tab[$tab_name] = $items['pagination']['orders_count'];
				}

				$orders_count_tab['active'] = $orders_count_tab['unconfirmed'] + $orders_count_tab['paid'];

				foreach ($orders_count_tab as $tab_name => $orders_count) {

					$this->tpl->loop('orders_count', array(
						'tab_name' => $tab_name,
						'count' => $orders_count,
					));
				}
			}
		}
	}
}

class request_api_order_search extends f2b2h_request {

	protected function send_api_response($response_data) {

		// TODO Redeclare in extenders
	}

	protected function valid($data) {

		$errors = array();

		$is_core = (is_mode('core_get_orders') ? true : false);

		if (!$is_core && !$this->user->valid_partner_id($data['partner_id'], 'trip')) {

			$errors[] = $this->lang('partner_validation_partner_id_error');
		}
		if (!strcmp($data['offset'],'')) $errors[] = 'Offset cannot be empty';
		if (!$data['limit']) $errors[] = 'Limit cannot be empty';

		if ($data['state'] && !in_array($data['state'], $this->page_order->rs_view_available_states)) {

			$errors[] = 'Wrong state';
		}


		$this->errors = $errors;

		return true;
	}


	protected function success($data) {

		$orders = array();

		$is_core = (is_mode('core_get_orders') ? true : false);
		$welcomeback_sent = null;
		if (isset($data['search_params']['welcomeback_sent']) && ($data['search_params']['welcomeback_sent'] != '')) {

			$welcomeback_sent = $data['search_params']['welcomeback_sent'];
		}
		$reminder_sent = null;
		if (isset($data['search_params']['reminder_sent']) && ($data['search_params']['reminder_sent'] != '')) {

			$reminder_sent = $data['search_params']['reminder_sent'];
		}

		$search_params = array (
			'tab'					=> 'all',
			'extended_mode'			=> true,
			'revise_orders_state'	=> true,

			'partner_id'			=> (!$is_core ? $data['partner_id'] : null),
			'limit_from'			=> $data['offset'],
			'orders_per_page'		=> $data['limit'],

			'state'					=> ($data['state'] ? $data['state'] : null),
			'made_from_rs'			=> ($data['is_direct'] ? $data['is_direct'] : null),
			'payment_method'		=> (!empty($data['search_params']['payment_method']) ? $data['search_params']['payment_method'] : null),
			'cc_types'				=> (!empty($data['search_params']['cc_types']) ? $data['search_params']['cc_types'] : null),
			'confirmation_number'	=> (!empty($data['search_params']['confirmation_number']) ? $data['search_params']['confirmation_number'] : null),
			'multi_search'			=> (!empty($data['search_params']['multi_search']) ? $data['search_params']['multi_search'] : null),
			'rs_agent'				=> (!empty($data['search_params']['rs_agent']) ? $data['search_params']['rs_agent'] : null),
			'username'				=> (!empty($data['search_params']['customer_name']) ? $data['search_params']['customer_name'] : null),
			'phone_number'			=> (!empty($data['search_params']['customer_phone']) ? $data['search_params']['customer_phone'] : null),
			'product_id'			=> (!empty($data['search_params']['product_id']) ? $data['search_params']['product_id'] : null),
			'inventory_id'			=> (!empty($data['search_params']['inventory_id']) ? $data['search_params']['inventory_id'] : null),
			'welcomeback_sent'		=> $welcomeback_sent,
			'reminder_sent'			=> $reminder_sent,
		);

		if (!empty($data['search_params']['made']['from'])) $search_params['made_from'] = $data['search_params']['made']['from'];
		if (!empty($data['search_params']['made']['to'])) $search_params['made_to'] = $data['search_params']['made']['to'];

		if (!empty($data['search_params']['arrival']['from'])) $search_params['arrival_from'] = $data['search_params']['arrival']['from'];
		if (!empty($data['search_params']['arrival']['to'])) $search_params['arrival_to'] = $data['search_params']['arrival']['to'];


		$orders_result = $this->api->get_orders($search_params);

		$data = array (
			'total_orders'	=> $orders_result['total_orders'],
			'states_items'	=> $orders_result['states_items'],
			'orders'		=> $orders_result['orders'],
		);

		$this->send_api_response($data);
	}
}

class request_api_rs_order_search extends request_api_order_search {

	protected function send_api_response($response_data) {

		$this->page_api_rs->send_response($response_data);
	}

	protected function failure($errors) {

		$this->page_api_rs->send_response($this->data, false, $errors);
	}
}

class request_api_mobile_order_search extends request_api_order_search {

	protected function send_api_response($response_data) {

		$this->page_api_mobile->send_response($response_data);
	}

	protected function failure($errors) {

		$this->page_api_mobile->send_response($this->data, false, $errors);
	}
}

class request_api_rs_financial_report extends f2b2h_request {

	protected function valid($data) {

		$this->add_page('trip');


		if (!$data['partner_id'] || !$this->user->valid_partner_id($data['partner_id'], 'trip')) {

			$this->errors[] = $this->lang('partner_validation_partner_id_error');
		}

		if (!empty($data['search_params']['trips']) && is_array($data['search_params']['trips'])) {

			$trips_info = $this->page_trip->get_list(array (
				'ids'			=> $data['search_params']['trips'],
				'partner_id'	=> $data['partner_id'],
			));

			foreach ($data['search_params']['trips'] as $trip_id) {

				if (!array_key_exists($trip_id, $trips_info)) {

					$this->errors[] = 'There is not trip with id '.$trip_id.' for provided partner';
				}
			}
		}
		else $this->data['search_params']['trips'] = array();

		if (!empty($data['search_params']['schedules']) && !is_array($data['search_params']['schedules'])) {

			$this->errors[] = 'Wrong schedules list';
		}


		return true;
	}

	protected function failure($errors) {

		$this->page_api_rs->send_response($this->data, false, $errors);
	}


	protected function success($data) {

		$this->add_page('order');
		$this->add_page('cart_item');


		// Для выборки частей заказов в подробностях (по дате прибыния)
		$where = array();
		$values = array();
		// Для выборки частей заказов для net сумм (по дате заказа)
		$where_totals = array();
		$values_totals = array();


		$where_totals[] = $where[] = 'products.partner_id = ?';
		$values_totals[] = $values[] = $data['partner_id'];

		$where_totals[] = $where[] = "NOT (
			cart_items.state_name = 'made'
				AND
			cart_items.sold_reduced = 0
		)";

		// Для этой выборки интересует только заказанное через TS
		$where_totals[] = "orders.made_from_rs = 0";


		if (!empty($data['search_params']['trips'])) {

			$where[] = "products.id IN (".$this->db->in($data['search_params']['trips']).")";
		}

		if (!empty($data['search_params']['booked_by'])) {

			$booked_by = array();

			foreach ($data['search_params']['booked_by'] as $agent) {

				$booked_by[] = "orders.rs_agent = ?";

				$values[] = $agent;
			}

			if ($booked_by) $where[] = "(".implode("\r\n OR ", $booked_by).")";
		}

		if (!empty($data['search_params']['payment_methods'])) {

			$where[] = "orders.payment_method IN (".$this->db->in($data['search_params']['payment_methods']).")";
		}

		// Итемы заказов с payment_method == 'void' метод должен игнорировать
		$where[] = "orders.payment_method NOT IN ('void')";

		if (!empty($data['search_params']['period']['from']) && !empty($data['search_params']['period']['to'])) {

			$where[] = "((calendar.date BETWEEN ? AND ?) OR ((calendar.date IS NULL) AND (DATE(orders.made) BETWEEN ? AND ?)))";
			$where_totals[] = "DATE(orders.made) BETWEEN ? AND ?";			
			$values_totals[] = $values[] = format_date_for_db($data['search_params']['period']['from']);
			$values_totals[] = $values[] = format_date_for_db($data['search_params']['period']['to']);
			
			$values[] = format_date_for_db($data['search_params']['period']['from']);
			$values[] = format_date_for_db($data['search_params']['period']['to']);
			
			// Подарки будут видны только в поиске по дате заказа (не удалять)
			//$where[] = "cart_items.gift_code = ''";
		}

		// deleted-части не видны в принципе
		$where[] = "cart_items.state_name <> 'deleted'";

		// cancelled-части видны только по запросу
		if (empty($data['search_params']['with_cancelled'])) {

			$where[] = "cart_items.state_name <> 'cancelled'";
		}

		// В total-выборке не нужно отменённых в принципе
		$where_totals[] = "cart_items.state_name NOT IN ('deleted','cancelled')";


		$item_index = 0;

		$cart_items_data = array();
		$already_saved_items = array();

		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.data,

				carts.data AS cart_data,

				orders.id AS order_id,
				orders.payment_method AS order_payment_method,
				orders.made_from_rs AS order_made_from_rs,
				orders.rs_agent AS order_booked_by,
				orders.made AS order_made,

				orders_users_data.first_name AS customer_first_name,
				orders_users_data.last_name AS customer_last_name,
				orders_users_data.email AS customer_email,
				orders_users_data.phone AS customer_phone,
				orders_users_data.phone2 AS customer_phone2,
				orders_users_data.ip AS customer_ip,
				orders_users_data.address AS customer_address,
				orders_users_data.city AS customer_city,
				orders_users_data.zip AS customer_zip,
				orders_users_data.state_id AS customer_state_id,
				orders_users_data.country_id AS customer_country_id,

				products.id AS product_id,
				products.name AS product_name,

				calendar.date AS arrival_date
			FROM
				orders
				JOIN orders_users_data ON orders_users_data.order_id = orders.id
				JOIN cart_items ON cart_items.cart_id = orders.cart_id
				LEFT JOIN calendar ON calendar.id = cart_items.calendar_id
				JOIN products ON cart_items.product_id = products.id
				JOIN carts ON carts.id = orders.cart_id
			WHERE
				".implode(" AND \r\n", $where)."
			ORDER BY
				cart_items.id DESC",
			$values
		);

		$db_res = $this->db->result;

		while ($cart_item = $this->db->fetch($db_res)) {

			$book_info = $this->db->decode_data($cart_item['data']);


			// Проверка на schedule_id проводится здесь, т.к. нужны запакованные данные
			if (
				!empty($data['search_params']['schedules'])
					&&
				(
					empty($book_info['schedule']['id'])
						||
					!in_array($book_info['schedule']['id'], $data['search_params']['schedules'])
				)
				) continue;

			// Проверка на inventory_id
			if (
				!empty($data['search_params']['inventories'])
					&&
				(
					empty($book_info['inventory']['id'])
						||
					!in_array($book_info['inventory']['id'], $data['search_params']['inventories'])
				)
				) continue;



			$cart_totals = $this->page_cart_item->get_booked_trip_totals($book_info);

			$subtotal = $cart_totals['total']['net'];
			$net_total = $cart_totals['total']['net_with_tax'];
			$subtotal_tax = $net_total - $subtotal;

			$cart_item_discount = array();
			// Если есть скидка для этой части и это заказ с RS - вычитаем её
			// Также для этих заказов добавляем сборы rs_booking_fee
			$subtotal_fee = 0;

			if ($cart_item['order_made_from_rs']) {

				$cart_data = $this->db->decode_data($cart_item['cart_data']);

				if (!empty($cart_data['discount']['cart_items'][$cart_item['id']])) {

					$cart_item_discount = $cart_data['discount']['cart_items'][$cart_item['id']];

					$subtotal -= $cart_item_discount['discount'];
					$net_total -= $cart_item_discount['discount_with_tax'];
					$subtotal_tax = $net_total - $subtotal;
				}

				$subtotal_fee = $net_total * $book_info['product']['booking_fee'] / 100;
				$net_total += $subtotal_fee;
			}

			if ($payments = $this->page_order->get_payment(null, $cart_item['order_id'])) $payments = array_values($payments);


			$inventory = array();
			if (!empty($book_info['inventory'])) $inventory = $book_info['inventory'];

			$tickets = array();

			foreach ($book_info['tickets'] as $ticket_id => $ticket) {

				$tickets[$ticket['id']] = array (
					'id'		=> $ticket['id'],
					'name'		=> $ticket['name'],
					'price'		=> ($cart_item['order_payment_method'] != 'comp' ? $ticket['price'] : 0),
					'booked'	=> $ticket['booked'],
					'comission'	=> $ticket['comission'],
					'guests'	=> $ticket['guests'],
				);
				
				if($cart_item['order_payment_method'] == 'comp') $book_info['tickets'][$ticket_id]['price'] = 0;
			}
			
			foreach ($book_info['additional_options'] as $option_id => $option) {
				
				if($cart_item['order_payment_method'] == 'comp') $book_info['additional_options'][$option_id]['value']['price'] = 0;
			}
			
			$booked_format = format_tickets_string($book_info['tickets']).format_additional_options_string($book_info['additional_options']);
			
			$cart_item_payments = array();

			if (!$payments) {

				$cart_item_payments = array (
					0 => array (
						'card_type'			=> null,
						'card_number'		=> null,
						'transaction_id'	=> null,
					),
				);
			}
			else {

				foreach ($payments as $index => $payment) {

					$cart_item_payments[$index] = array (
						'card_type'			=> $payment['type'],
						'card_number'		=> $payment['number'],
						'transaction_id'	=> $payment['transaction_id'],
					);
				}
			}


			$cart_items_data[] = array (
				'id'						=> $cart_item['id'],
				'order_id'					=> $cart_item['order_id'],

				'trip_id'					=> $cart_item['product_id'],
				'trip_name'					=> $cart_item['product_name'],
				'tax'						=> $book_info['product']['tax'],
				'booking_fee'				=> ($cart_item['order_made_from_rs'] ? $book_info['product']['booking_fee'] : 0),

				'made'						=> format_date_for_db($cart_item['order_made'], true),
				'arrival_date'				=> ($cart_item['arrival_date'] ? format_date_for_db($cart_item['arrival_date']) : null),

				'schedule_id'				=> (!empty($book_info['schedule']) ? $book_info['schedule']['id'] : ''),
				'schedule_time'				=> (!empty($book_info['schedule']) ? $book_info['schedule']['time'] : ''),

				'customer'					=> $cart_item['customer_first_name'].' '.$cart_item['customer_last_name'],
				'customer_last_name'		=> $cart_item['customer_last_name'],
				'customer_first_name'		=> $cart_item['customer_first_name'],
				'customer_email'			=> $cart_item['customer_email'],
				'customer_phone'			=> $cart_item['customer_phone'],
				'customer_phone2'			=> $cart_item['customer_phone2'],
				'customer_address'			=> $cart_item['customer_address'],
				'customer_city'				=> $cart_item['customer_city'],
				'customer_zip'				=> $cart_item['customer_zip'],
				'customer_state_id'			=> $cart_item['customer_state_id'],
				'customer_country_id'		=> $cart_item['customer_country_id'],

				'inventory'					=> $inventory,
				'booked_format'				=> $booked_format,
				'discount'					=> $cart_item_discount,
				'tickets'					=> $tickets,
				'additional_options'		=> $book_info['additional_options'],

				'payment_method'			=> $cart_item['order_payment_method'],
				'transaction_info'			=> $cart_item_payments,

				'subtotal'					=> ($cart_item['order_payment_method'] != 'comp' ? round_price($subtotal) : 0),
				'subtotal_tax'				=> ($cart_item['order_payment_method'] != 'comp' ? round_price($subtotal_tax) : 0),
				'subtotal_fee'				=> ($cart_item['order_payment_method'] != 'comp' ? round_price($subtotal_fee) : 0),
				'net_total'					=> ($cart_item['order_payment_method'] != 'comp' ? round_price($net_total) : 0),

				'booked_by'					=> $cart_item['order_booked_by'],
			);

			$already_saved_items[] = $cart_item['id'];

			$item_index++;
		}


		// Теперь определяем суммы для totals
		$totals = array (
			'net_total'				=> 0,
			'net_total_after_tax'	=> 0,
		);

		$this->db->query("
			SELECT
				cart_items.id,
				cart_items.data
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
				JOIN products ON cart_items.product_id = products.id
			WHERE
				".implode(" AND \r\n", $where_totals),
			$values_totals
		);

		$db_res_totals = $this->db->result;

		while ($cart_item_totals = $this->db->fetch($db_res_totals)) {
			
			// Помимо фильтрации исключаем те, которые уже учтены в первой части отчёта
			if (!in_array($cart_item_totals['id'], $already_saved_items)) {

				$book_info = $this->db->decode_data($cart_item_totals['data']);
				$current_totals = $this->page_cart_item->get_booked_trip_totals($book_info);

				$totals['net_total'] += $current_totals['total']['net'];
				$totals['net_total_after_tax'] += $current_totals['total']['net_with_tax'];
			}
		}

		$response_data = array (
			'cart_items'	=> $cart_items_data,
			'totals'		=> $totals,
			'total_items'	=> $item_index,
		);

		$this->page_api_rs->send_response($response_data);
	}
}

// Отменяем часть заказа
class request_order_cancel_fee extends f2b2h_request {

	protected function success($data) {

		$this->db->query("
			SELECT
				orders.id
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
			WHERE
				cart_items.id = ?",
			$data['cart_item_id']
		);

		if (!($order = $this->db->fetch())) return;


		// Смена состояния
		$this->page_order->cart_item_set_state($data['cart_item_id'], 'cancelled');

		// Изменение суммы штрафа/причины
		$this->page_order->set_item_fee(
			$data['cart_item_id'],
			$data['site_fee'],
			$data['partner_fee'],
			$data['refund_amount'],
			$data['cancel_reason']
		);


		$this->page_order->update_order_totals($order['id']);


		$this->url->go('referer');
	}
}

class request_order_access extends f2b2h_request {

	private $order;

	protected function defaults() {

		if (!is_mobile()) {

			if ($this->url->get_str('key')) $this->defaults['access_key'] = $this->url->get_str('key');
			else $this->defaults['access_key'] = 'Access key';
		}
	}


	protected function valid_access_key($access_key) {

		$this->db->query("
			SELECT
				id,
				user_id,
				order_by_user_id
			FROM
				orders
			WHERE
				accesskey = ?",
			$access_key
		);

		if (!($this->order = $this->db->fetch())) return false;

		return true;
	}


	protected function success($data) {

		if (
			!($this->user->is_partner() && ($this->order['order_by_user_id'] == $this->user->data('id')))
				&&
			!$this->user->is_admin()
			) {

			$this->user->logout();

			$this->user->login($this->order['user_id']);
		}

		$this->url->go('/order/view/'.$this->order['id']);
	}
}

// Форма логина в просмотр заказа
class request_order_view extends f2b2h_request {

	protected function valid_accesskey($accesskey) {

		$this->db->query("
			SELECT
				1
			FROM
				orders
			WHERE
				accesskey = ?",
			$accesskey
		);

		return $this->db->fetch() ? true : false;
	}

	protected function success($data) {

		$this->db->query("
			SELECT
				orders.id,
				orders.cart_id,
				orders.user_id
			FROM
				orders
			WHERE
				orders.accesskey = ?
			ORDER BY
				orders.made DESC
			LIMIT 1",
			$data['accesskey']
		);

		if (!$order = $this->db->fetch()) $this->url->go('referer');

		if (!($this->user->is_admin() || $this->user->is_partner())) {

			$this->user->login($order['user_id']);
			$this->user->fill_data();

			$_SESSION['order_user_id'] = $this->user->data('id');
		}
		else {

			$_SESSION['order_user_id'] = $order['user_id'];
		}

		$_SESSION['order_id'] = $order['id'];

		$this->url->go();
	}
}


class request_order_equipment_swap extends f2b2h_request {

	protected function valid_partner_id($partner_id) {

		if (!$this->user->valid_partner_id($partner_id)) {

			$this->lang('add_to_cart_partner_id_error', $this->lang('partner_validation_partner_id_error'));

			return false;
		}

		return true;
	}

	protected function valid_trip_id($trip_id) {

		$this->add_page('trip');

		if (!($trip = $this->page_trip->get($trip_id, false, false, $this->data['partner_id']))) return false;

		return true;
	}

	protected function failure($errors) {

		$this->page_api_rs->send_response(array(), false, $errors);
	}


	protected function success($data) {

		$errors = array();
		$res_data = array();

		$this->add_page('trip');
		$this->add_page('inventory');


		// Валидация переноса
		$result = $this->page_trip->validate_equipment_swap($data);


		if (!$result) {

			// Существование и валидность всех элементов входного массива данных уже проверены выше
			$is_schedule_changed = ($data['new_cell']['schedule_id'] != $data['old_cell']['schedule_id']);
			$is_inventory_changed = ($data['new_cell']['inventory_id'] != $data['old_cell']['inventory_id']);
			$new_schedule = array();
			$new_inventory = array();

			$log_messages = array();

			$inventories = $this->page_inventory->get_list(array('trip_id' => $data['trip_id']));
			$new_inventory = $inventories[$data['new_cell']['inventory_id']];

			if ($is_inventory_changed) {

				$log_messages[] = 'Changed inventory from <b>'.$inventories[$data['old_cell']['inventory_id']]['name'].
					'</b> to <b>'.$new_inventory['name'].'</b>';
			}

			$schedules = $this->page_trip->get_schedules($data['trip_id']);
			$new_schedule = $schedules[$data['new_cell']['schedule_id']];

			if ($is_schedule_changed) {

				$log_messages[] = 'Changed schedule from <b>'.$schedules[$data['old_cell']['schedule_id']]['time'].
					'</b> to <b>'.$new_schedule['time'].'</b>';
			}

			$res_data = array (
				'schedule'	=> array (
					'id'	=> $schedules[$data['new_cell']['schedule_id']],
					'time'	=> $new_schedule['time'],
				),
				'inventory'	=> array (
					'id'	=> $inventories[$data['new_cell']['inventory_id']],
					'name'	=> $new_inventory['name'],
				)
			);


			// Основной цикл переноса частей
			$this->add_page('cart_item');
			$this->add_page('calendar');
			$calendar_id = $this->page_calendar->get_day(null, $data['date'], $data['trip_id']);


			$manual_item = array (
				'inventory_id'	=> $data['old_cell']['inventory_id'],
				'schedule_id'	=> $data['old_cell']['schedule_id'],
				'calendar_id'	=> $calendar_id['id'],
				'product_id'	=> $data['trip_id'],
				'cart_items'	=> $data['cart_items'],
				'tickets'		=> array(),
			);
			$order_ids = array();

			foreach ($data['cart_items'] as $cart_item_id) {

				// Cначала делаем запрос в БД на основные поля
				$this->db->query("
					SELECT
						cart_items.id,
						cart_items.data,
						
						orders.id AS order_id,
						orders.made_from_rs AS order_made_from_rs
					FROM
						cart_items
						JOIN orders ON orders.cart_id = cart_items.cart_id
					WHERE
						cart_items.id = ?",
					$cart_item_id
				);
				$db_res = $this->db->result;

				$cart_item = $this->db->fetch($db_res);
				$cart_item_data = $this->db->decode_data($cart_item['data']);

				$order_ids[$cart_item_id] = $cart_item['order_id'];

				foreach ($cart_item_data['tickets'] as $ticket) {

					if (!isset($manual_item['tickets'][$ticket['id']])) {

						$manual_item['tickets'][$ticket['id']] = array (
							'booked'	=> $ticket['booked'],
							'booked_ts'	=> 0,
							'barcodes'	=> array(),
						);
					}
					else {

						$manual_item['tickets'][$ticket['id']]['booked'] += $ticket['booked'];
					}

					if (!$cart_item['order_made_from_rs']) {

						$manual_item['tickets'][$ticket['id']]['booked_ts'] += $ticket['booked'];
					}

					foreach ($ticket['barcodes'] as $barcode) {

						array_push($manual_item['tickets'][$ticket['id']]['barcodes'], $barcode);
					}
				}


				// Делаем правки в самом data по сути переноса и пишем в БД, сбрасывая флаг sold_reduced
				if ($is_inventory_changed) {

					$cart_item_data['inventory'] = array (
						'id'			=> $new_inventory['id'],
						'name'			=> $new_inventory['name'],
						// Длительность берём из старых данных чтобы не делать доп. запросов (по идее она точно неизменна)
						'duration'		=> $cart_item_data['inventory']['duration'],
						'description'	=> $new_inventory['description'],
					);
				}

				if ($is_schedule_changed) {

					$cart_item_data['schedule'] = array (
						'id'	=> $new_schedule['id'],
						'time'	=> $new_schedule['time'],
					);
				}

				$this->db->insert_update('cart_items', array (
					'id'			=> $cart_item_id,
					'data'			=> $this->db->encode_data($cart_item_data),
					'sold_reduced'	=> 0
				));
			}

			// Выполняем один большой restore_trip_tickets на всех
			$this->page_cart_item->restore_trip_tickets(null, $manual_item);

			// А теперь каждому по отдельности делаем reduce_trip_tickets и пишем сообщение(я) в лог части
			foreach ($data['cart_items'] as $cart_item_id) {

				$this->page_cart_item->reduce_trip_tickets($cart_item_id);

				foreach ($log_messages as $log_message) {

					$this->log->save_order_log(
						$log_message,
						$order_ids[$cart_item_id],
						$cart_item_id
					);
				}
			}
		}
		else {

			$errors = $result;
		}

		$this->page_api_rs->send_response((!$errors ? $res_data : array()), !$errors, $errors);
	}
}

?>