<?php

/*

f2b2h/lang/en/trip.php

*/

class page_trip extends f2b2h {

	public $id;

	public $types = array (
		'Air'		=> 1,
		'Water'		=> 2,
		'Land'		=> 3,
	);

	public $cut_off = array(
		0 => 'No',
		1 => '1 day',
		2 => '2 days',
		3 => '3 days',
		4 => '4 days',
		5 => '5 days',
		6 => '6 days',
		7 => '7 days',
	);

	public $schedule_cut_off_hour = array(
		0 => 'No',
		1 => '1 hour',
		2 => '2 hours',
		3 => '3 hours',
		4 => '4 hours',
		5 => '5 hours',
		6 => '6 hours',
		7 => '7 hours',
		8 => '8 hours',
		9 => '9 hours',
		10 => '10 hours',
		11 => '11 hours',
		12 => '12 hours',
		13 => '13 hours',
		14 => '14 hours',
		15 => '15 hours',
		16 => '16 hours',
		17 => '17 hours',
		18 => '18 hours',
		19 => '19 hours',
		20 => '20 hours',
	);

	private $last_validation_flags = array(
		'schedule_error'	=> null,
		'inventory_error'	=> null,
	);

	public $product_city_tiers = array(
		0 => 'None',
		1 => 'Category (Premium)',
		2 => 'Destination (Platinum)',
	);


	public function get_product_city_tier($trip_id, $city_id = null) {

		if(!$trip_id) return 0;

		$where = [];
		$values = [];

		$where[] = 'products_cities_tier.trip_id = ?';
		$values[] = $trip_id;

		if($city_id) {

			$where[] = 'products_cities_tier.city_id = ?';
			$values[] = $city_id;
		}

		$this->db->query("
			SELECT 
				tier,
				city_id
			FROM
				products_cities_tier"
			.($where ? " WHERE ".implode("\r\n AND ", $where) : ""),
			$values
		);

		$tiers = [];
		while($tier = $this->db->fetch()) {

			$tiers[$tier['city_id']] = $tier['tier'];
		}

		if($tiers) return ($city_id && $tiers[$city_id]) ? intval($tiers[$city_id]) : $tiers;

		return 0;
	}

	// Возвращаем флаги только если валидация прошла хотя бы раз
	// Возможно в дальнейшем логика будет более сложная тут
	public function get_last_validation_flags() {

		if (is_null($this->last_validation_flags['schedule_error'])) return null;
		else return $this->last_validation_flags;
	}

	// Поскольку имена параметров поиска трипов уже SEO-Friendly,
	// то если значения приходят литеральные, их нужно подменять цифрами в тех же параметрах.
	// Если нет входного массива, то работаем с $_GET
	public function get_seourl_params($data = null) {

		if (is_null($data)) $data = $_GET;

		if (!empty($data['activity']) && !is_numeric($data['activity'])) {

			$this->add_page('activity');

			$activities = $this->page_activity->get_list();

			$is_found = false;

			foreach ($activities as $activity) {

				if (!strcmp($data['activity'], format_to_seourl($activity['name'], true))) {

					$data['activity'] = $activity['id'];
					$is_found = true;
					break;
				}
			}

			if (!$is_found) unset($data['activity']);
		}

		if (!empty($data['category']) && !is_numeric($data['category'])) {

			$categories = $this->get_categories();

			$is_found = false;

			foreach ($categories as $category) {

				if (!strcmp($data['category'], format_to_seourl($category['name'], true))) {

					$data['category'] = $category['id'];
					$is_found = true;
					break;
				}
			}

			if (!$is_found) unset($data['category']);
		}

		if (!empty($data['type']) && !is_numeric($data['type'])) {

			$types = array_flip($this->types);

			$is_found = false;

			foreach ($types as $id => $type) {

				if (!strcmp($data['type'], format_to_seourl($type, true))) {

					$data['type'] = $id;
					$is_found = true;
					break;
				}
			}

			if (!$is_found) unset($data['type']);
		}

		if (!empty($data['city']) && !is_numeric($data['city'])) {

			$this->add_page('city');

			if ($city = $this->page_city->get(null, str_replace(array(' '), '_', $data['city']))) {

				$data['city'] = $city['id'];

				// Если город определён, то штат всегда берётся по городу
				if (isset($data['state'])) $data['state'] = '';
			}
			else unset($data['city']);
		}
		elseif (!empty($data['state']) && !is_numeric($data['state'])) {

			$this->add_page('state');

			if ($state = $this->page_state->get(null, str_replace(array(' '), '_', $data['state']))) {

				$data['state'] = $state['id'];
			}
			else unset($data['state']);
		}

		return $data;
	}


	// По доступным inventories пытаемся разложить все пришедшие билеты с учётом их длительности
	public function separate_tickets_inventories($trip_id, $tickets, $inventories) {

		$result = array (
			'separation'	=> array(),
			'errors'		=> array(),
		);

		// TODO При формировании билетов ниже если рознятся длительности и есть null билеты, то форсим 0
		// TODO При формировании лодок ниже если флаг 1, то выделяем подмассив теоретически подходящих
		$try_single_inventory = $this->product->get_additional_parameter($trip_id, 'single_inventory_separation');
		$single_inventory_slots = array();
		$single_inventory_duration = null;
		// TODO Далее делаем сам разбор (учитывать ли что могут быть разные по длительности группы?..)

		// Для лучшего распределения сортируем билеты в обратном порядке их заказанного числа
		// На первое место ставим те, у которых длительность NULL
		$null_tickets = array();
		$not_null_tickets = array();

		$sorted_tickets = array();

		foreach ($tickets as $ticket_id => $ticket) {

			if (is_null($ticket['duration'])) {

				$null_tickets[$ticket_id] = $ticket['booked'];
				$duration = 0;
			}
			else {

				$not_null_tickets[$ticket_id] = $ticket['booked'];
				$duration = $ticket['duration'];
			}


			if ($try_single_inventory && $ticket['booked']) {

				if ($duration && is_null($single_inventory_duration)) $single_inventory_duration = $duration;

				if (!isset($single_inventory_slots[$duration])) {

					$single_inventory_slots[$duration] = array (
						'total'		=> $ticket['booked'],
						'tickets'	=> array($ticket_id => $ticket['booked']),
					);
				}
				else {

					$single_inventory_slots[$duration]['total'] += $ticket['booked'];

					if (!isset($single_inventory_slots[$duration]['tickets'][$ticket_id])) {

						$single_inventory_slots[$duration]['tickets'][$ticket_id] = $ticket['booked'];
					}
					else {

						$single_inventory_slots[$duration]['tickets'][$ticket_id] += $ticket['booked'];
					}
				}
			}
		}
		// Если есть слоты с длительностью и без неё, то без длительности добавляем к первому с длительностью
		// Есть основания полагать/надежда что этот с длительностью окажется единственным
		if (isset($single_inventory_slots[0]) && !is_null($single_inventory_duration)) {

			$single_inventory_slots[$single_inventory_duration]['total'] += $single_inventory_slots[0]['total'];
			$single_inventory_slots[$single_inventory_duration]['tickets'] += $single_inventory_slots[0]['tickets'];
			unset($single_inventory_slots[0]);
		}


		// Здесь и сразу составляем список лодок у которых есть длителельность на случай полного перебора
		// И если нужно попробовать всё определить в одну лодку, то тут же это определяется
		$inventories_with_duration = array();
		$temp_corrections = array();
		foreach ($inventories as $inventory_id => $inventory) {

			// Сразу удаляем те, в которые ничего не разместим в любом случае
			if ($inventory['is_disabled'] || $inventory['in_use'] || !$inventory['avail']) {

				unset($inventories[$inventory_id]);
			}
			else {

				if (!is_null($inventory['duration'])) {

					$inventories_with_duration[$inventory_id] = intval($inventory['duration']);
				}

				if ($try_single_inventory && $single_inventory_slots) {

					// При рассмотрении очередного слота на цельное заполнение учитываем в лодках данные уже
					// распределённых слотов чтобы, например, оба слота не вписать в изначально null лодку
					if (isset($temp_corrections[$inventory_id])) {

						if (!is_null($temp_corrections[$inventory_id]['duration'])) {

							$inventory['duration'] = $temp_corrections[$inventory_id]['duration'];
						}
						$inventory['avail'] -= $temp_corrections[$inventory_id]['booked'];
					}

					foreach ($single_inventory_slots as $duration => $data) {

						if (
							$inventory['avail'] >= $data['total']
								&&
							(
								is_null($inventory['duration'])
									||
								!$duration
									||
								(
									!is_null($inventory['duration'])
										&&
									$duration
										&&
									(intval($inventory['duration']) == intval($duration))
								)
							)
						) {

							$result['separation'][$inventory_id] = $data['tickets'];
							$temp_corrections[$inventory_id] = array (
								'duration'	=> ($duration ? $duration : null),
								'booked'	=> $data['total'],
							);
							// Очередной слот распределён в текущую лодку - переходим к следующей
							unset($single_inventory_slots[$duration]);
							break;
						}
					}
				}
			}
		}


		// ВЫХОД - есть настройка целиком в одну лодку разместить все билеты и это удалось
		if ($try_single_inventory && $result['separation'] && !$single_inventory_slots) {

			return $result;
		}
		// Иначе зачищаем результирующий массив и делаем базовый алгоритм
		else {

			$result['separation'] = array();
		}


		// Если дошли сюда, значит предстоит полный перебор билетов - в первую очередь выстраиваем их в нужном порядке
		arsort($null_tickets);
		arsort($not_null_tickets);

		foreach ($null_tickets as $null_ticket_id => $null_ticket_booked) {

			$sorted_tickets[] = array (
				'ticket_id'	=> $null_ticket_id,
				'booked'	=> $null_ticket_booked,
				'duration'	=> null,
			);
		}
		foreach ($not_null_tickets as $not_null_ticket_id => $not_null_ticket_booked) {

			$sorted_tickets[] = array (
				'ticket_id'	=> $not_null_ticket_id,
				'booked'	=> $not_null_ticket_booked,
				'duration'	=> $tickets[$not_null_ticket_id]['duration'],
			);
		}

		// Основной цикл полного перебора
		foreach ($sorted_tickets as $ticket_index => $ticket) {

			$ticket_id = $ticket['ticket_id'];

			// Приводим значения к числовым чтобы правильно всё считать
			$ticket['booked'] = intval($ticket['booked']);
			if (!is_null($ticket['duration'])) {

				$ticket['duration'] = intval($ticket['duration']);

				// Если уже есть лодка с той же длительностью и подходящим объёмом, то сразу определяем в неё
				if ($inventory_ids = array_keys($inventories_with_duration, $ticket['duration'])) {

					if (is_array($inventory_ids)) {

						$chosen_inventory = null;

						foreach ($inventory_ids as $inventory_id) {

							if ($inventories[$inventory_id]['avail'] >= $ticket['booked']) {

								$chosen_inventory = $inventory_id;
								break;
							}
						}

						if (!is_null($chosen_inventory)) {

							if (!isset($result['separation'][$chosen_inventory])) $result['separation'][$chosen_inventory] = array();
							$result['separation'][$chosen_inventory][$ticket_id] = $ticket['booked'];

							$sorted_tickets[$ticket_index]['booked'] -= $ticket['booked'];
							$inventories[$chosen_inventory]['avail'] -= $ticket['booked'];

							continue;
						}
					}
				}
			}

			// Если сразу определить билет не удалось, то перебираем лодки в попытке его разместить
			foreach ($inventories as $inventory_id => $inventory) {

				// Маркеры в tickets_avail_by_duration актуальны всё время на случай перекрытия данных билетов другими расписаниями
				$not_available_tickets = array_keys($inventory['tickets_avail_by_duration'], false);

				// А в остальном нельзя использовать $inventory['tickets_avail_by_duration']
				// т.к. локально данные (и длительность лодок соответственно) будут меняться в процессе распределения
				if (
					!in_array($ticket_id, $not_available_tickets)
						&&
					(
						is_null($ticket['duration'])
							||
						is_null($inventory['duration'])
							||
						(
							!is_null($ticket['duration'])
								&&
							!is_null($inventory['duration'])
								&&
							($ticket['duration'] == $inventory['duration'])
						)
					)
				) {

					// Если в эту лодку все не помещаются, то помещаем максимум возможного
					if ($inventory['avail'] >= $ticket['booked']) {

						$tickets_to_inventory = $ticket['booked'];
					}
					else {

						$tickets_to_inventory = $inventory['avail'];
					}

					if (!isset($result['separation'][$inventory_id])) $result['separation'][$inventory_id] = array();
					$result['separation'][$inventory_id][$ticket_id] = $tickets_to_inventory;

					// Локально меняем основные данные в соответствии с полученным распределением
					if (!is_null($ticket['duration']) && is_null($inventory['duration'])) {

						$inventories[$inventory_id]['duration'] = $ticket['duration'];
					}
					$sorted_tickets[$ticket_index]['booked'] -= $tickets_to_inventory;
					$ticket['booked'] -= $tickets_to_inventory;
					$inventories[$inventory_id]['avail'] -= $tickets_to_inventory;
				}

				// Все билеты данного типа уже распределены
				if (!$sorted_tickets[$ticket_index]['booked']) break;
			}
		}

		// Если остались не распределённые билеты, то это полюбому ошибка
		foreach ($sorted_tickets as $ticket) {

			if ($ticket['booked']) {

				foreach ($inventories as $inventory) {

					if ($inventory['avail']) {

						if (
							(
								!is_api_request()
									&&
								!$this->user->is_admin()
							)
								||
							is_front_api_request()
						) {
							
							$result['errors'][] = 'There are not enough tickets available for this schedule time, please reduce ticket amount or choose another schedule time';
						} else {
						
							$result['errors'][] = 'Separation process error: too many different durations';
						}
						break(2);
					}
				}
				
				if (
					(
						!is_api_request()
							&&
						!$this->user->is_admin()
					)
						||
					is_front_api_request()
				) {
					$result['errors'][] = 'There are not enough tickets available for this schedule time, please reduce ticket amount or choose another schedule time';
				} else {
					$result['errors'][] = 'Separation process error: not enough places';
				}
				break;
			}
		}

		return $result;
	}


	// Данные билетов трипов для конкретного периода дней
	private function get_trips_days_tickets($trips_id, $date_from, $date_to) {

		if (!is_array($trips_id)) {

			$trips_id = array($trips_id);
		}
		else {

			sort($trips_id);
		}

		$trips_days_tickets = array();
		$trips_with_use_schedule_flag = array();
		$purge_data = $this->config('purge_availability');
		if (isset($purge_data['schedules_tickets'])) $purge_data = $purge_data['schedules_tickets'];
		else $purge_data = null;

		foreach ($trips_id as $trip_id) {

			$this->db->query("
				SELECT
					products.use_schedule,
					products.use_calendar
				FROM
					products
				WHERE
					products.id = ?",
				$trip_id
			);

			if ($trip_data = $this->db->fetch()) {

				if (!$trip_data['use_calendar']) continue;

				if ($trip_data['use_schedule']) {

					$this->db->query("
						SELECT
							products.id AS product_id,
							products.cut_off_time AS cut_off_time,

							calendar.id AS calendar_id,
							calendar.date,

							trip_ticket.id AS ticket_id,
							trip_ticket.name AS ticket_name,
							trip_ticket.rs_internal AS rs_internal,
							trip_schedule.id AS schedule_id,
							IFNULL(calendar_ticket.price, trip_ticket.price) AS ticket_price,
							IFNULL(calendar_ticket.price_rs, trip_ticket.price_rs) AS ticket_price_rs,
							IFNULL(calendar_ticket.strike_out, trip_ticket.strike_out) AS ticket_strike_out,
							IFNULL(calendar_ticket.comission, users.comission) AS ticket_comission,
							IFNULL(calendar_ticket.wholesale, trip_ticket.wholesale) AS ticket_wholesale,
							IFNULL(calendar_ticket.min, trip_ticket.min) AS ticket_min,
							products.cut_off AS ticket_cut_off,
							IFNULL(calendar_ticket.total, trip_ticket.total) AS ticket_total,
							IFNULL(calendar_ticket.total_ts, trip_ticket.total_ts) AS ticket_total_ts,
							IFNULL(calendar_ticket.sold, 0) AS ticket_sold,
							IFNULL(calendar_ticket.sold_ts, 0) AS ticket_sold_ts,
							IFNULL(calendar_ticket.is_disabled, 0) AS ticket_is_disabled,
							IF((trip_ticket.is_disabled_ts <> 0), trip_ticket.is_disabled_ts, IFNULL(calendar_ticket.is_disabled_ts, 0)) AS ticket_is_disabled_ts,
							IFNULL(calendar_ticket.disable_promos, 0) AS ticket_disable_promos,
							trip_ticket.is_default AS ticket_is_default,
							trip_ticket.duration AS ticket_duration
						FROM
							products
							JOIN users ON users.id = products.partner_id
							JOIN trip_ticket ON trip_ticket.trip_id = products.id
							JOIN calendar ON calendar.product_id = products.id
							JOIN trip_schedule ON trip_schedule.trip_id = products.id
							LEFT JOIN calendar_ticket ON (
								calendar_ticket.calendar_id = calendar.id
									AND
								calendar_ticket.ticket_id = trip_ticket.id
									AND
								calendar_ticket.schedule_id = trip_schedule.id
							)
						WHERE
							products.id = ?
								AND
							calendar.date BETWEEN ? AND ?
							".((!is_api_request() || is_mode('front_get_day_trip', 'get_month_availability')) ? " AND trip_ticket.rs_internal = 0" : "")."
						ORDER BY
							products.id,
							calendar.date,
							trip_schedule.time,
							trip_ticket.view_order,
							trip_ticket.id",
						$trip_id,
						$date_from,
						$date_to
					);

					while ($day_ticket = $this->db->fetch()) {

						// Для трипов с расписанием пропускаем параметры без расписания
						if (!$day_ticket['schedule_id']) continue;


						// Если в конфиге есть данные, которые нужно вычистить из доступности, то делаем это
						if (
							!empty($purge_data)
								&&
							isset($purge_data[$day_ticket['product_id']][$day_ticket['date']][$day_ticket['schedule_id']][$day_ticket['ticket_id']])
						) {

							$current_purge = $purge_data[$day_ticket['product_id']][$day_ticket['date']][$day_ticket['schedule_id']][$day_ticket['ticket_id']];

							$day_ticket['ticket_sold_ts'] -= $current_purge['ts'];
							$day_ticket['ticket_sold'] -= $current_purge['rs'];
						}


						// Для запросов с трипшока учитываем флаг is_disabled_ts
						// если (is_disabled_ts == 1), то это равноценно (is_disabled == 1)
						if (!is_api_request() && $day_ticket['ticket_is_disabled_ts']) {

							$day_ticket['ticket_is_disabled'] = $day_ticket['ticket_is_disabled_ts'];
						}

						// Кол-во доступных билетов для партнёрки
						$ticket_avail = $day_ticket['ticket_total'] - $day_ticket['ticket_sold'];

						if ($ticket_avail < 0) $ticket_avail = 0;

						// И для трипшока
						// кол-во доступных трипшоку билетов зависит от кол-ва свободных у партнёрки
						$ticket_avail_ts = $day_ticket['ticket_total_ts'] - $day_ticket['ticket_sold_ts'];
						if ($ticket_avail_ts > $ticket_avail) $ticket_avail_ts = $ticket_avail;

						if ($ticket_avail_ts < 0) $ticket_avail_ts = 0;

						if ($day_ticket['ticket_total_ts'] > $day_ticket['ticket_total']) {

							$day_ticket['ticket_total_ts'] = $day_ticket['ticket_total'];
						}

						$day_ticket_info = array (
							'id'				=> $day_ticket['ticket_id'],
							'name'				=> $day_ticket['ticket_name'],
							'price'				=> $day_ticket['ticket_price'],
							'price_rs'			=> $day_ticket['ticket_price_rs'],
							'strike_out'		=> $day_ticket['ticket_strike_out'],
							'comission'			=> $day_ticket['ticket_comission'],
							'wholesale'			=> $day_ticket['ticket_wholesale'],
							'min'				=> $day_ticket['ticket_min'],
							'duration'			=> $day_ticket['ticket_duration'],
							'cut_off'			=> $day_ticket['ticket_cut_off'],
							'cut_off_time'		=> ($day_ticket['cut_off_time'] ? $day_ticket['cut_off_time'] : $this->config('calendar_next_day_hour')),
							'total'				=> $day_ticket['ticket_total'],
							'total_ts'			=> $day_ticket['ticket_total_ts'],
							'avail'				=> $ticket_avail,
							'avail_ts'			=> $ticket_avail_ts,
							'sold'				=> $day_ticket['ticket_sold'],
							'sold_ts'			=> $day_ticket['ticket_sold_ts'],
							'is_disabled'		=> $day_ticket['ticket_is_disabled'],
							'disable_promos'	=> $day_ticket['ticket_disable_promos'],
							'is_default'		=> $day_ticket['ticket_is_default'],
							'rs_internal'		=> $day_ticket['rs_internal'],
						);

						$trips_days_tickets[$day_ticket['product_id']][$day_ticket['date']][$day_ticket['schedule_id']][$day_ticket['ticket_id']] = $day_ticket_info;
					}

					$trips_with_use_schedule_flag[$trip_id] = 1;
				}
				else {

					$this->db->query("
						SELECT
							products.id AS product_id,
							products.cut_off_time AS cut_off_time,

							calendar.id AS calendar_id,
							calendar.date,

							trip_ticket.id AS ticket_id,
							trip_ticket.name AS ticket_name,
							trip_ticket.rs_internal AS rs_internal,
							IFNULL(calendar_ticket.price, trip_ticket.price) AS ticket_price,
							IFNULL(calendar_ticket.price_rs, trip_ticket.price_rs) AS ticket_price_rs,
							IFNULL(calendar_ticket.strike_out, trip_ticket.strike_out) AS ticket_strike_out,
							IFNULL(calendar_ticket.comission, users.comission) AS ticket_comission,
							IFNULL(calendar_ticket.wholesale, trip_ticket.wholesale) AS ticket_wholesale,
							IFNULL(calendar_ticket.min, trip_ticket.min) AS ticket_min,
							products.cut_off AS ticket_cut_off,
							IFNULL(calendar_ticket.total, trip_ticket.total) AS ticket_total,
							IFNULL(calendar_ticket.total_ts, trip_ticket.total_ts) AS ticket_total_ts,
							IFNULL(calendar_ticket.sold, 0) AS ticket_sold,
							IFNULL(calendar_ticket.sold_ts, 0) AS ticket_sold_ts,
							IFNULL(calendar_ticket.is_disabled, 0) AS ticket_is_disabled,
							IF((trip_ticket.is_disabled_ts <> 0), trip_ticket.is_disabled_ts, IFNULL(calendar_ticket.is_disabled_ts, 0)) AS ticket_is_disabled_ts,
							IFNULL(calendar_ticket.disable_promos, 0) AS ticket_disable_promos,
							trip_ticket.is_default AS ticket_is_default,
							trip_ticket.duration AS ticket_duration
						FROM
							products
							JOIN users ON users.id = products.partner_id
							JOIN trip_ticket ON trip_ticket.trip_id = products.id
							JOIN calendar ON calendar.product_id = products.id
							LEFT JOIN calendar_ticket ON (
								calendar_ticket.calendar_id = calendar.id
									AND
								calendar_ticket.ticket_id = trip_ticket.id
									AND
								calendar_ticket.schedule_id = 0
							)
						WHERE
							products.id = ?
								AND
							calendar.date BETWEEN ? AND ?
							".((!is_api_request() || is_mode('front_get_day_trip', 'get_month_availability')) ? " AND trip_ticket.rs_internal = 0" : "")."
						ORDER BY
							products.id,
							calendar.date,
							trip_ticket.view_order,
							trip_ticket.id",
						$trip_id,
						$date_from,
						$date_to
					);

					while ($day_ticket = $this->db->fetch()) {

						// Если в конфиге есть данные, которые нужно вычистить из доступности, то делаем это
						if (
							!empty($purge_data)
							&&
							isset($purge_data[$day_ticket['product_id']][$day_ticket['date']][0][$day_ticket['ticket_id']])
						) {

							$current_purge = $purge_data[$day_ticket['product_id']][$day_ticket['date']][0][$day_ticket['ticket_id']];

							$day_ticket['ticket_sold_ts'] -= $current_purge['ts'];
							$day_ticket['ticket_sold'] -= $current_purge['rs'];
						}

						// Для запросов с трипшока учитываем флаг is_disabled_ts
						// если (is_disabled_ts == 1), то это равноценно (is_disabled == 1)
						if (!is_api_request() && $day_ticket['ticket_is_disabled_ts']) {

							$day_ticket['ticket_is_disabled'] = $day_ticket['ticket_is_disabled_ts'];
						}

						// Кол-во доступных билетов для партнёрки
						$ticket_avail = $day_ticket['ticket_total'] - $day_ticket['ticket_sold'];

						if ($ticket_avail < 0) $ticket_avail = 0;

						// И для трипшока
						// кол-во доступных трипшоку билетов зависит от кол-ва свободных у партнёрки
						$ticket_avail_ts = $day_ticket['ticket_total_ts'] - $day_ticket['ticket_sold_ts'];
						if ($ticket_avail_ts > $ticket_avail) $ticket_avail_ts = $ticket_avail;

						if ($ticket_avail_ts < 0) $ticket_avail_ts = 0;

						if ($day_ticket['ticket_total_ts'] > $day_ticket['ticket_total']) {

							$day_ticket['ticket_total_ts'] = $day_ticket['ticket_total'];
						}

						$day_ticket_info = array (
							'id'				=> $day_ticket['ticket_id'],
							'name'				=> $day_ticket['ticket_name'],
							'price'				=> $day_ticket['ticket_price'],
							'price_rs'			=> $day_ticket['ticket_price_rs'],
							'strike_out'		=> $day_ticket['ticket_strike_out'],
							'comission'			=> $day_ticket['ticket_comission'],
							'wholesale'			=> $day_ticket['ticket_wholesale'],
							'min'				=> $day_ticket['ticket_min'],
							'duration'			=> $day_ticket['ticket_duration'],
							'cut_off'			=> $day_ticket['ticket_cut_off'],
							'cut_off_time'		=> ($day_ticket['cut_off_time'] ? $day_ticket['cut_off_time'] : $this->config('calendar_next_day_hour')),
							'total'				=> $day_ticket['ticket_total'],
							'total_ts'			=> $day_ticket['ticket_total_ts'],
							'avail'				=> $ticket_avail,
							'avail_ts'			=> $ticket_avail_ts,
							'sold'				=> $day_ticket['ticket_sold'],
							'sold_ts'			=> $day_ticket['ticket_sold_ts'],
							'is_disabled'		=> $day_ticket['ticket_is_disabled'],
							'disable_promos'	=> $day_ticket['ticket_disable_promos'],
							'is_default'		=> $day_ticket['ticket_is_default'],
							'rs_internal'		=> $day_ticket['rs_internal'],
						);

						$trips_days_tickets[$day_ticket['product_id']][$day_ticket['date']][0][$day_ticket['ticket_id']] = $day_ticket_info;
					}

					$trips_with_use_schedule_flag[$trip_id] = 0;
				}
			}
		}


		if ($trips_days_tickets) $trips_days_tickets[0] = $trips_with_use_schedule_flag;

		return $trips_days_tickets;
	}

	// Данные расписаний трипов для конкретного периода дней
	private function get_trips_days_schedules($trips_with_use_schedule_flag, $date_from, $date_to) {

		$trips_schedules = array();

		$purge_data = $this->config('purge_availability');
		if (isset($purge_data['schedules_tickets'])) $purge_data = $purge_data['schedules_tickets'];
		else $purge_data = null;

		foreach ($trips_with_use_schedule_flag as $trip_id => $trip_use_schedule) {

			if ($trip_use_schedule) {

				$this->db->query("
					SELECT
						products.id AS product_id,

						calendar.date,

						trip_schedule.id AS schedule_id,
						trip_schedule.time AS schedule_time,
						IF(trip_schedule.is_disabled = 1, 1, IFNULL(calendar_schedule.is_disabled, trip_schedule.is_disabled)) AS schedule_is_disabled,
						IFNULL(calendar_schedule.min_tickets, trip_schedule.min_tickets) AS schedule_min_tickets,
						IFNULL(calendar_schedule.max_tickets, trip_schedule.max_tickets) AS schedule_max_tickets,
						IFNULL(calendar_schedule.sold, 0) AS schedule_sold,
						trip_schedule.cut_off_hour AS schedule_cut_off_hour
					FROM
						products
						JOIN calendar ON calendar.product_id = products.id
						JOIN trip_schedule ON trip_schedule.trip_id = products.id
						LEFT JOIN calendar_schedule ON (
							calendar_schedule.calendar_id = calendar.id
								AND
							calendar_schedule.schedule_id = trip_schedule.id
						)
					WHERE
						products.id = ?
							AND
						calendar.date BETWEEN ? AND ?
					ORDER BY
						calendar.date ASC,
						trip_schedule.time ASC",
					$trip_id,
					$date_from,
					$date_to
				);

				while ($day_schedule = $this->db->fetch()) {


					// Если в конфиге есть данные, которые нужно вычистить из доступности, то делаем это
					if (
						!empty($purge_data)
						&&
						isset($purge_data[$day_schedule['product_id']][$day_schedule['date']][$day_schedule['schedule_id']])
					) {

						$current_purge = $purge_data[$day_schedule['product_id']][$day_schedule['date']][$day_schedule['schedule_id']];

						foreach ($current_purge as $purge_ticket) {

							// Ключ rs всегда учитывает полный объём
							$day_schedule['schedule_sold'] -= $purge_ticket['rs'];
						}
					}


					// Если max_tickets не указан или 0, то доступно произвольное кол-во билетов (tickets_avail === null)
					$tickets_avail = null;
					$tickets_avail_for_update = null;

					if (!is_null($day_schedule['schedule_max_tickets'])) {

						$tickets_avail = $day_schedule['schedule_max_tickets'] - $day_schedule['schedule_sold'];
						$tickets_avail_for_update = $tickets_avail;

						if ($tickets_avail < $day_schedule['schedule_min_tickets']) {

							if ($tickets_avail > 0) $tickets_avail_for_update = $tickets_avail;
							$tickets_avail = 0;
						}
					}

					$trips_schedules[$day_schedule['product_id']][$day_schedule['date']][$day_schedule['schedule_id']] = array (
						'id'						=> $day_schedule['schedule_id'],
						'time'						=> $day_schedule['schedule_time'],
						'is_disabled'				=> $day_schedule['schedule_is_disabled'],
						'cut_off_hour'				=> $day_schedule['schedule_cut_off_hour'],

						'min_tickets'				=> $day_schedule['schedule_min_tickets'],
						'max_tickets'				=> $day_schedule['schedule_max_tickets'],
						'tickets_avail'				=> $tickets_avail,
						'tickets_avail_for_update'	=> $tickets_avail_for_update,
						'sold'						=> $day_schedule['schedule_sold'],
					);
				}
			}
			else {

				$this->db->query("
					SELECT
						products.id AS product_id,

						calendar.date,

						calendar_schedule.is_disabled AS schedule_is_disabled,
						calendar_schedule.min_tickets AS schedule_min_tickets,
						calendar_schedule.max_tickets AS schedule_max_tickets,
						IFNULL(calendar_schedule.sold, 0) AS schedule_sold
					FROM
						products
						JOIN calendar ON calendar.product_id = products.id
						LEFT JOIN calendar_schedule ON (
							calendar_schedule.calendar_id = calendar.id
								AND
							calendar_schedule.schedule_id = 0
						)
					WHERE
						products.id = ?
							AND
						calendar.date BETWEEN ? AND ?
					ORDER BY
						calendar.date ASC",
					$trip_id,
					$date_from,
					$date_to
				);

				while ($day_schedule = $this->db->fetch()) {

					// Если в конфиге есть данные, которые нужно вычистить из доступности, то делаем это
					if (
						!empty($purge_data)
						&&
						isset($purge_data[$day_schedule['product_id']][$day_schedule['date']][0])
					) {

						$current_purge = $purge_data[$day_schedule['product_id']][$day_schedule['date']][0];

						foreach ($current_purge as $purge_ticket) {

							// Ключ rs всегда учитывает полный объём
							$day_schedule['schedule_sold'] -= $purge_ticket['rs'];
						}
					}

					// Если max_tickets не указан или 0, то доступно произвольное кол-во билетов (tickets_avail === null)
					$tickets_avail = null;
					$tickets_avail_for_update = null;

					if (!is_null($day_schedule['schedule_max_tickets'])) {

						$tickets_avail = $day_schedule['schedule_max_tickets'] - $day_schedule['schedule_sold'];
						$tickets_avail_for_update = $tickets_avail;

						if ($tickets_avail < $day_schedule['schedule_min_tickets']) {

							if ($tickets_avail > 0) $tickets_avail_for_update = $tickets_avail;
							$tickets_avail = 0;
						}
					}

					$trips_schedules[$day_schedule['product_id']][$day_schedule['date']][0] = array (
						'id'						=> 0,
						'time'						=> 0,
						'is_disabled'				=> $day_schedule['schedule_is_disabled'],
						'cut_off_hour'				=> 0,

						'min_tickets'				=> $day_schedule['schedule_min_tickets'],
						'max_tickets'				=> $day_schedule['schedule_max_tickets'],
						'tickets_avail'				=> $tickets_avail,
						'tickets_avail_for_update'	=> $tickets_avail_for_update,
						'sold'						=> $day_schedule['schedule_sold'],
					);
				}
			}
		}

		return $trips_schedules;
	}

	// Данные inventory трипов для конкретного периода дней
	private function get_inventories_data($trips_id, $date_from, $date_to) {

		if (!is_array($trips_id)) $trips_id = array($trips_id);


		$inventories = array();
		$trips_inventories = array();
		$trips_booked_inventories = array();


		$purge_data = $this->config('purge_availability');
		$purge_inventories = (isset($purge_data['inventory_tickets']) ? $purge_data['inventory_tickets'] : null);


		$this->db->query("
			SELECT
				inventory.id,
				inventory.name,

				trip_inventory.trip_id,
				IFNULL(trip_inventory.description, inventory.description) AS description,
				IFNULL(trip_inventory.priority, inventory.priority) AS priority,
				IFNULL(calendar_inventory.max_places, IFNULL(trip_inventory.max_places, inventory.max_places)) AS max_places,
				calendar_inventory.duration AS duration,
				IFNULL(calendar_inventory.is_disabled, IFNULL(trip_inventory.is_disabled, inventory.is_disabled)) AS is_disabled,

				calendar.date,

				trip_schedule.id AS schedule_id,

				IFNULL(calendar_inventory.sold, 0) AS sold
			FROM
				inventory
				JOIN trip_inventory ON trip_inventory.inventory_id = inventory.id
				JOIN products ON products.id = trip_inventory.trip_id
				JOIN calendar ON calendar.product_id = products.id
				LEFT JOIN trip_schedule ON trip_schedule.trip_id = products.id
				LEFT JOIN calendar_inventory ON (
					calendar_inventory.inventory_id = inventory.id
						AND
					calendar_inventory.calendar_id = calendar.id
						AND
					calendar_inventory.schedule_id = trip_schedule.id
				)
			WHERE
				trip_inventory.trip_id IN (".$this->db->in($trips_id).")
					AND
				calendar.date BETWEEN ? AND ?
					AND
				products.use_calendar = 1
					AND
				products.use_schedule = 1
			ORDER BY
				trip_inventory.trip_id ASC,
				calendar.date ASC,
				IFNULL(trip_inventory.priority, inventory.priority) DESC,
				inventory.id",
			$date_from,
			$date_to
		);

		while ($day_inventory = $this->db->fetch()) {

			if (
				!empty($purge_inventories)
					&&
				isset($purge_inventories[$day_inventory['trip_id']][$day_inventory['date']][$day_inventory['schedule_id']][$day_inventory['id']])
			) {

				$day_inventory['sold'] -= $purge_inventories[$day_inventory['trip_id']][$day_inventory['date']][$day_inventory['schedule_id']][$day_inventory['id']];
				if ($day_inventory['sold'] <= 0) $day_inventory['duration'] = null;
			}

			$avail = ($day_inventory['max_places'] - $day_inventory['sold']);
			if ($avail < 0) $avail = 0;

			$trips_inventories[$day_inventory['trip_id']][$day_inventory['date']][$day_inventory['schedule_id']][$day_inventory['id']] = array (
				'id'				=> $day_inventory['id'],
				'name'				=> $day_inventory['name'],

				'description'		=> $day_inventory['description'],
				'priority'			=> $day_inventory['priority'],
				'max_places'		=> $day_inventory['max_places'],
				'duration'			=> $day_inventory['duration'],
				'is_disabled'		=> $day_inventory['is_disabled'],

				'avail'				=> $avail,
				'sold'				=> $day_inventory['sold'],
			);

			$inventories[$day_inventory['id']] = $day_inventory['id'];
		}



		if ($inventories) {

			// Благодаря набору трипов основной запрос делает на два порядка меньше выборок
			$this->db->query("
				SELECT
					trip_inventory.trip_id
				FROM
					trip_inventory
				WHERE
					trip_inventory.inventory_id IN (".$this->db->in($inventories).")
				GROUP BY
					trip_inventory.trip_id
				"
			);
			$db_res = $this->db->result;

			$related_trips = array();
			while ($trip_id = $this->db->fetch($db_res)) {

				$related_trips[] = $trip_id['trip_id'];
			}

			$this->db->query("
				SELECT
					calendar_inventory.inventory_id,
					calendar_inventory.duration,
					calendar_inventory.schedule_id,
					calendar_inventory.sold,
					trip_schedule.time,
					calendar.id AS calendar_id,
					calendar.product_id,
					calendar.date
				FROM
					calendar_inventory
					JOIN calendar ON calendar.id = calendar_inventory.calendar_id
					LEFT JOIN trip_schedule ON trip_schedule.id = calendar_inventory.schedule_id
				WHERE
					calendar.date BETWEEN ? AND ?
						AND
					calendar.product_id IN (".$this->db->in($related_trips).")
						AND
					calendar_inventory.inventory_id IN (".$this->db->in($inventories).")
						AND
					calendar_inventory.sold > 0
				",
				$date_from,
				$date_to
			);
			$db_res = $this->db->result;

			$items_schedules = array();

			while ($booked_inventory = $this->db->fetch($db_res)) {

				if (
					!empty($purge_inventories)
						&&
					isset($purge_inventories[$booked_inventory['product_id']][$booked_inventory['date']][$booked_inventory['schedule_id']][$booked_inventory['inventory_id']])
						&&
					($purge_inventories[$booked_inventory['product_id']][$booked_inventory['date']][$booked_inventory['schedule_id']][$booked_inventory['inventory_id']] == $booked_inventory['sold'])
				) {

					continue;
				}

				// Если расписание уже удалили, то инфу о нём можно только из какого-нибудь итема узнать
				if (
					is_null($booked_inventory['time'])
					&&
					!array_key_exists($booked_inventory['schedule_id'], $items_schedules)
				) {

					$this->db->query("
						SELECT
							cart_items.data
						FROM
							cart_items
						WHERE
							cart_items.calendar_id = ?
								AND
							cart_items.data LIKE '%\"schedule\":{\"id\":".$booked_inventory['schedule_id'].",%'",
						$booked_inventory['calendar_id']
					);
					$db_res1 = $this->db->result;

					if ($cart_item = $this->db->fetch($db_res1)) {

						$cart_item = $this->db->decode_data($cart_item['data']);

						$items_schedules[$booked_inventory['schedule_id']] = convert_hour($cart_item['schedule']['time']);
					}
				}

				$got_the_time = true;

				if (is_null($booked_inventory['time'])) {

					if (isset($items_schedules[$booked_inventory['schedule_id']])) {

						$booked_inventory['time'] = $items_schedules[$booked_inventory['schedule_id']];
					}
					else {

						// Не отражаем это в заказанных лодках т.к. получается что
						// на этот день на это расписание ничего нет в cart_items
						$got_the_time = false;
					}
				}

				if ($got_the_time) {

					$trips_booked_inventories[$booked_inventory['date']][$booked_inventory['inventory_id']]
					[$booked_inventory['time']][$booked_inventory['product_id']] = $booked_inventory['duration'];
				}
			}
		}


		return array (
			'trips_inventories'			=> $trips_inventories,
			'trips_booked_inventories'	=> $trips_booked_inventories,
		);
	}


	// Для каждого дня из [$date_from, $date_to] и каждого трипа из $trips_id получаем список билетов с точными параметрами
	// array|int $trips_id
	// date $date_from
	// date $date_to
	public function get_trips_tickets_rates($trips_id, $date_from, $date_to, $upper_level_data = false) {

		if (!($this->config('purge_availability'))) {

			if (($trips_days_info = $this->cache->load(array('trip_get_trips_tickets_rates', $trips_id, $date_from, $date_to, $upper_level_data))) !== null) return $trips_days_info;
		}

		if ($upper_level_data) return $this->get_trips_days_availability($trips_id, $date_from, $date_to);


		$time = time();


		// Информация о трипах
		$trips = $this->get_list(array(
			'ids'		=> $trips_id,
			'no_cities'	=> true,
		));

		// Парамеры билетов трипов для соответствующего периода
		$trips_days_tickets = $this->get_trips_days_tickets($trips_id, $date_from, $date_to);

		// Параметры расписаний трипов для соответствующего периода
		$trips_schedules = $this->get_trips_days_schedules(($trips_days_tickets ? $trips_days_tickets[0] : array()), $date_from, $date_to);
		unset($trips_days_tickets[0]);

		// Параметры inventory трипов для соответствующего периода
		$trips_inventories = $this->get_inventories_data($trips_id, $date_from, $date_to);

		$trips_days_booked_inventories = $trips_inventories['trips_booked_inventories'];
		$trips_inventories = $trips_inventories['trips_inventories'];


		// Данные дней периода, необходим calendar.id
		$this->add_page('calendar');
		$calendar_days = $this->page_calendar->get_days(array (
			'product_ids'			=> $trips_id,
			'date_from'				=> $date_from,
			'date_to'				=> $date_to,
			'product_use_calendar'	=> true,
		));

		$trips_calendar_days = array ();

		foreach ($calendar_days as $calendar_day) {

			$trips_calendar_days[$calendar_day['product_id']][$calendar_day['date']] = $calendar_day['id'];
		}


		$trips_days_info = array();

		// Теперь для каждого трипа-дня-билета добавляем параметров
		foreach ($trips as $trip) {

			if (empty($trips_days_tickets[$trip['id']])) continue;


			$trip_days = $trips_days_tickets[$trip['id']];

			$trip_is_delayed = false;
			if (
				is_mode('admin_get_day_trip', 'admin_get_day_trips', 'admin_cart_create', 'admin_get_trip_availability')
				&&
				$trip['is_delayed']
			) {

				$trip_is_delayed = true;
			}


			$trip_days_info = array(
				'not_avail'					=> false,
				'not_avail_ts'				=> false,

				'min_price'					=> null,
				'min_price_ticket_id'		=> null,
				'min_price_rs'				=> null,
				'min_price_rs_ticket_id'	=> null,

				'days_info'					=> array(),
			);

			// Кол-во недоступных дней
			$not_avail_days = 0;
			$not_avail_ts_days = 0;


			// Очередной день с расписаниями-билетами
			foreach ($trip_days as $date => $day_schedules) {

				// В какое время в этот день партнёр закрывается
				$day_data_closeout = $this->page_calendar->get_days_trip_data($trip['id'], 'close_out', array($date));
				$day_close_out = null;
				if (isset($day_data_closeout[$date]) && !is_null($day_data_closeout[$date]['close_out'])) {

					$day_close_out = $day_data_closeout[$date]['close_out'];
				}
				else if (!is_null($trip['close_out'])) {

					$day_close_out = $trip['close_out'];
				}

				$day_info = array (
					'calendar_id'				=> $trips_calendar_days[$trip['id']][$date],
					'date'						=> $date,

					'not_avail'					=> false,
					'not_avail_ts'				=> false,
					'has_avail_tickets'			=> false,
					'has_avail_schedules'		=> false,
					'stop_sell'					=> false,
					'avail_for_admin'			=> true,

					'tickets_avail'				=> 0,

					'min_price'					=> null,
					'min_price_ticket_id'		=> null,
					'min_price_rs'				=> null,
					'min_price_rs_ticket_id'	=> null,

					'schedules'					=> array(),
				);

				$stop_sell_schedules = array();
				$not_avail_schedules = array();
				$not_avail_ts_schedules = array();

				$day_has_avail_for_admin_schedules = false;

				foreach ($day_schedules as $schedule_id => $day_schedule_tickets) {

					$schedule = $trips_schedules[$trip['id']][$date][$schedule_id];

					$day_info['tickets_avail'] += $schedule['tickets_avail'];

					$day_schedule_info = array (
						'id'						=> ($schedule_id ? $schedule_id : 0),
						'time'						=> ($schedule_id ? convert_hour($schedule['time']) : ''),
						'is_disabled'				=> $schedule['is_disabled'],
						'cut_off_hour'				=> $schedule['cut_off_hour'],

						'min_tickets'				=> $schedule['min_tickets'],
						'max_tickets'				=> $schedule['max_tickets'],
						'tickets_avail'				=> $schedule['tickets_avail'],
						'tickets_avail_for_update'	=> $schedule['tickets_avail_for_update'],
						'sold'						=> $schedule['sold'],

						'not_avail'					=> false,
						'not_avail_ts'				=> false,
						'not_avail_by_close_out'	=> false,
						'has_avail_tickets'			=> false,
						'stop_sell'					=> false,
						'avail_for_admin'			=> true,

						'min_price'					=> null,
						'min_price_ticket_id'		=> null,
						'min_price_rs'				=> null,
						'min_price_rs_ticket_id'	=> null,

						'inventories'				=> array(),
						'tickets'					=> array(),
					);

					// Здесь сразу устонавливается доступность только по close_out
					// Доступность исходя из данных билетов определяется ниже
					if (
						!is_null($day_close_out)
						&&
						$schedule_id
						&&
						convert_hour_to_minutes($schedule['time']) > convert_hour_to_minutes($day_close_out)
					) {

						$day_schedule_info['not_avail'] = true;
						$day_schedule_info['not_avail_ts'] = true;
						$day_schedule_info['not_avail_by_close_out'] = true;
					}

					$tickets = array();

					$stop_sell_tickets = array();
					$not_avail_tickets = array();
					$not_avail_ts_tickets = array();
					$not_avail_by_close_out_tickets = array();

					$has_default_ticket = false;
					$has_default_rs_ticket = false;


					// Очередной билет в этот день
					foreach ($day_schedule_tickets as $ticket) {

						$close_out_check_fails = false;

						$avail = $ticket['avail'];
						$avail_ts = $ticket['avail_ts'];

						if (!is_null($day_schedule_info['max_tickets']) && ($avail > $day_schedule_info['tickets_avail'])) {

							// Доступность базово регулируется доступностью по версии расписания
							$avail = $day_schedule_info['tickets_avail'];
							// Базовый параметр расписания обнуляется если доступно билетов меньше его min-параметра
							// Однако для апдейта при этом билетов может быть больше нуля
							if ($day_schedule_info['tickets_avail'] < $day_schedule_info['tickets_avail_for_update']) {

								$avail = $day_schedule_info['tickets_avail_for_update'];
							}

							// И доступное даже для апдейта число билетов должно быть не больше разнницы
							// между всеми наличными и всеми уже проданными
							$low_level_avail = $ticket['total'] - $ticket['sold'];
							if ($avail > $low_level_avail) $avail = (($low_level_avail < 0) ? 0 : $low_level_avail);
						}
						if (!is_null($day_schedule_info['max_tickets']) && ($avail_ts > $day_schedule_info['tickets_avail'])) {

							$avail_ts = $day_schedule_info['tickets_avail'];
							if ($day_schedule_info['tickets_avail'] < $day_schedule_info['tickets_avail_for_update']) {

								$avail_ts = $day_schedule_info['tickets_avail_for_update'];
							}

							$low_level_avail = $ticket['total_ts'] - $ticket['sold_ts'];
							if ($avail_ts > $low_level_avail) $avail_ts = (($low_level_avail < 0) ? 0 : $low_level_avail);
						}

						if ($ticket['is_disabled']) $stop_sell_tickets[$ticket['id']] = $ticket['id'];

						$ticket_cut_off_time = strtotime(date("Y-m-d", $time + (12 - $ticket['cut_off_time'])*3600 + $ticket['cut_off']*3600*24 + 1));

						// Недоступность билета в этот день (для нового заказа)
						$not_avail = false;
						$not_avail_ts = false;
						// Недоступность билета для редактирования (меньше ограничений)
						$not_avail_update = false;
						$not_avail_update_ts = false;

						if (
							$ticket['is_disabled']
							||
							(
								// В RS актуально только для фронта
								(!is_api_request() || is_front_api_request())
								&&
								(strtotime($date) < $ticket_cut_off_time)
							)
						) {

							$not_avail = true;
							$not_avail_tickets[$ticket['id']] = $ticket['id'];
							$not_avail_update = true;

							$not_avail_ts = true;
							$not_avail_ts_tickets[$ticket['id']] = $ticket['id'];
							$not_avail_update_ts = true;
						}
						else {

							if (
								($avail <= 0)
								||
								($ticket['min'] && ($avail < $ticket['min']))
							) {

								$not_avail = true;
								$not_avail_tickets[$ticket['id']] = $ticket['id'];

								// Редактирование недоступно только если билетов вообще не осталось
								if ($avail <= 0) $not_avail_update = true;
							}

							if (
								($avail_ts <= 0)
								||
								($ticket['min'] && ($avail_ts < $ticket['min']))
							) {

								$not_avail_ts = true;
								$not_avail_ts_tickets[$ticket['id']] = $ticket['id'];

								if ($avail_ts <= 0) $not_avail_update_ts = true;
							}
						}

						// Доступность билета также должна учитывать close_out время дня
						if (!is_null($day_close_out) && $schedule_id && !is_null($ticket['duration'])) {

							$ticket_end_minutes = convert_hour_to_minutes($schedule['time']) + $ticket['duration'];

							if ($ticket_end_minutes > convert_hour_to_minutes($day_close_out)) {

								$not_avail = true;
								$not_avail_tickets[$ticket['id']] = $ticket['id'];
								$not_avail_ts = true;
								$not_avail_ts_tickets[$ticket['id']] = $ticket['id'];

								$close_out_check_fails = true;

								$not_avail_by_close_out_tickets[$ticket['id']] = $ticket['id'];
							}
						}

						// Доступность билета для админа
						$ticket_avail_for_admin = false;

						if (
							($avail_ts > 0) && !$ticket['is_disabled']
							&&
							!$close_out_check_fails
							&&
							(
								!$ticket['min']
								||
								($avail_ts >= $ticket['min'])
							)
						) {

							$ticket_avail_for_admin = true;

							$day_schedule_info['has_avail_tickets'] = true;
						}

						// Минимальную цену ищем только среди доступных билетов
						if (
							!$not_avail_ts
							&&
							($ticket['price'] > 0)
							&&
							!$ticket['rs_internal']
							&&
							(
								$ticket['is_default']
								||
								(
									!$has_default_ticket
									&&
									(
										($day_schedule_info['min_price'] === null)
										||
										($day_schedule_info['min_price'] > $ticket['price'])
									)
								)
							)
						) {

							$day_schedule_info['min_price'] = $ticket['price'];
							$day_schedule_info['min_price_ticket_id'] = $ticket['id'];

							if ($ticket['is_default']) $has_default_ticket = true;
						}

						if (
							!$not_avail
							&&
							!$ticket['rs_internal']
							&&
							($ticket['price_rs'] > 0)
							&&
							(
								$ticket['is_default']
								||
								(
									!$has_default_rs_ticket
									&&
									(
										($day_schedule_info['min_price_rs'] === null)
										||
										($day_schedule_info['min_price_rs'] > $ticket['price_rs'])
									)
								)
							)
						) {

							$day_schedule_info['min_price_rs'] = $ticket['price_rs'];
							$day_schedule_info['min_price_rs_ticket_id'] = $ticket['id'];

							if ($ticket['is_default']) $has_default_rs_ticket = true;
						}


						$tickets[$ticket['id']] = array (
							'id'				=> $ticket['id'],
							'name'				=> $ticket['name'],
							'price'				=> $ticket['price'],
							'price_rs'			=> $ticket['price_rs'],
							'strike_out'		=> $ticket['strike_out'],
							'comission'			=> $ticket['comission'],
							'wholesale'			=> $ticket['wholesale'],
							'min'				=> $ticket['min'],
							'duration'			=> $ticket['duration'],
							'cut_off'			=> $ticket['cut_off'],
							'total'				=> $ticket['total'],
							'total_ts'			=> $ticket['total_ts'],
							'avail'				=> $avail,
							'avail_ts'			=> $avail_ts,
							'sold'				=> $ticket['sold'],
							'sold_ts'			=> $ticket['sold_ts'],
							'is_disabled'		=> $ticket['is_disabled'],
							'disable_promos'	=> $ticket['disable_promos'],
							'is_default'		=> $ticket['is_default'],

							'not_avail'				=> $not_avail,
							'not_avail_ts'			=> $not_avail_ts,
							'not_avail_update'		=> $not_avail_update,
							'not_avail_update_ts'	=> $not_avail_update_ts,
							'avail_for_admin'		=> $ticket_avail_for_admin,
						);
					}


					$day_schedule_info['tickets'] = $tickets;

					$valid_schedule_time = true;
					$admin_valid_schedule_time = true;

					// Следующие проверки в RS актуальны только для фронта
					if ($schedule['id'] && (!is_api_request() || is_front_api_request())) {

						list($schedule_hour, $schedule_minutes) = explode(':', $schedule['time']);

						$schedule_time_with_cut_off = $schedule_hour - $schedule['cut_off_hour'];
						$schedule_today_start_time = mktime($schedule_time_with_cut_off, $schedule_minutes, 0, date("m", strtotime($date)), date("d", strtotime($date)), date("Y", strtotime($date)));

						if ($schedule_today_start_time < $time) $valid_schedule_time = false;

						$schedule_today_start_time = mktime($schedule_hour, $schedule_minutes, 0, date("m", strtotime($date)), date("d", strtotime($date)), date("Y", strtotime($date)));

						if ($schedule_today_start_time < $time) $admin_valid_schedule_time = false;
					}

					// Доступность расписания
					if (
						$day_schedule_info['is_disabled']
						||
						(!$trip_is_delayed && !$valid_schedule_time)
						||
						(
							$trip['use_schedule']
							&&
							!is_null($day_schedule_info['tickets_avail'])
							&&
							($day_schedule_info['tickets_avail'] <= 0)
						)
					) {

						$not_avail_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['not_avail'] = true;

						$not_avail_ts_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['not_avail_ts'] = true;
					}

					if (count($not_avail_tickets) == count($day_schedule_tickets)) {

						$not_avail_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['not_avail'] = true;

						if (count($not_avail_by_close_out_tickets) == count($day_schedule_tickets)) {

							$day_schedule_info['not_avail_by_close_out'] = true;
						}
					}

					if (
						(count($not_avail_ts_tickets) == count($day_schedule_tickets))
						&&
						!($this->user->is_admin() && $day_schedule_info['has_avail_tickets'])
					) {

						$not_avail_ts_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['not_avail_ts'] = true;
					}

					// Доступность расписания админу
					if (
						$day_schedule_info['is_disabled']
							||
						!$admin_valid_schedule_time
							||
						(
							(count($not_avail_ts_tickets) == count($day_schedule_tickets))
								&&
							!($this->user->is_admin() && $day_schedule_info['has_avail_tickets'])
						)
					) {

						$day_schedule_info['avail_for_admin'] = false;
					}
					else {

						$day_has_avail_for_admin_schedules = true;
					}


					// Inventories
					if (!empty($trips_inventories[$trip['id']][$date][$schedule['id']])) {

						$not_avail_inventories = 0;

						$tickets_durations = array();

						foreach ($day_schedule_info['tickets'] as $ticket) {

							// Для валидации по длительности интресуют только доступные для заказа билеты
							if (
								$ticket['is_disabled']
								||
								(
									is_api_request()
									&&
									$ticket['not_avail']
								)
								||
								(
									!is_api_request()
									&&
									$ticket['not_avail_ts']
								)
							) continue;

							$tickets_durations[$ticket['id']] = (!empty($ticket['duration']) ? $ticket['duration'] : 0);
						}

						foreach ($trips_inventories[$trip['id']][$date][$schedule['id']] as $inventory) {

							$inventory['is_avail'] = true;
							// inventoty занято на это время другим трипом
							$inventory['in_use'] = false;

							if (
								$inventory['is_disabled']
								||
								!$inventory['avail']
							) {

								$not_avail_inventories++;

								$inventory['is_avail'] = false;
							}

							// Определяем доступные на данные лодку-расписание билеты - ТОЛЬКО по длительности
							$inventory['tickets_avail_by_duration'] = array();

							foreach ($day_schedule_tickets as $ticket_duration) {

								if (empty($inventory['duration']) || empty($ticket_duration['duration'])) {

									$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = true;
								}
								else if ($inventory['duration'] === $ticket_duration['duration']) {

									$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = true;
								}
								else {

									$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = false;
								}
							}

							if (!empty($trips_days_booked_inventories[$date][$inventory['id']])) {

								$current_schedule_minutes = convert_hour_to_minutes($schedule['time']);

								$current_inventory_duration = (!empty($inventory['duration']) ? $inventory['duration'] : 0);

								// Если у лодки на текущее расписание длительности ещё нет,
								// то проверять будем по всем актуальным билетам
								if ($current_inventory_duration) {

									$current_inventory_end_minutes = $current_schedule_minutes + $current_inventory_duration;
								}
								else {

									$current_inventory_end_minutes = array();

									foreach ($tickets_durations as $ticket_id => $ticket_duration) {

										$current_inventory_end_minutes[$ticket_id] = $current_schedule_minutes + $ticket_duration;
									}
								}

								// Сортируем заказы на лодку по показателю расписания - таким образом в случае блока
								// и слева и справа всегда приоритет будет у in_use_by == start
								ksort($trips_days_booked_inventories[$date][$inventory['id']]);

								foreach ($trips_days_booked_inventories[$date][$inventory['id']] as $shedule_time => $trips) {

									foreach ($trips as $trip_id => $duration) {

										$schedule_minutes = convert_hour_to_minutes($shedule_time);

										$inventory_end_minutes = $schedule_minutes + $duration;

										$end_minutes_check = false;
										if (is_array($current_inventory_end_minutes)) {

											foreach ($current_inventory_end_minutes as $ticket_id => $ticket_end_minutes) {

												if ($schedule_minutes > $ticket_end_minutes) {

													$end_minutes_check = true;
												}
												else {

													// Если уже заказанный инвентарь на более раннее расписание, то такому билету ставим недоступность
													if ($inventory_end_minutes > $current_schedule_minutes) {

														$inventory['tickets_avail_by_duration'][$ticket_id] = false;
													}
												}
											}
										}
										else {

											$end_minutes_check = (($schedule_minutes > $current_inventory_end_minutes) ? true : false);
										}

										if (
											(
												($trip['id'] == $trip_id)
												&&
												($current_schedule_minutes == $schedule_minutes)
											)
											||
											$end_minutes_check
											||
											($inventory_end_minutes < $current_schedule_minutes)
										) continue;

										if ($inventory['is_avail']) {

											$not_avail_inventories++;
											$inventory['is_avail'] = false;
										}

										$inventory['in_use'] = true;

										if (
											($schedule_minutes < $current_schedule_minutes)
											&&
											($inventory_end_minutes >= $current_schedule_minutes)
										) {

											$inventory['in_use_by'] = 'start';
										}
										else {

											$inventory['in_use_by'] = 'end';
										}


										break 2;
									}
								}
							}


							// На случай если следующие расписания после текущего отключены
							// Корректируем доступность билетов по длительности и общий in_use лодки
							if (!$inventory['in_use'] && $inventory['is_avail']) {

								$current_schedule_minutes = convert_hour_to_minutes($schedule['time']);
								$still_avail = array();
								foreach ($inventory['tickets_avail_by_duration'] as $id => $value) {

									if ($value) $still_avail[$id] = $day_schedule_tickets[$id]['duration'];
								}

								foreach ($trips_schedules[$trip['id']][$date] as $checking_schedule	) {

									if ($checking_schedule['id'] == 0) continue;

									$checking_minutes = convert_hour_to_minutes($checking_schedule['time']);

									if (
										$trips_inventories[$trip['id']][$date][$checking_schedule['id']][$inventory['id']]['is_disabled']
										&&
										!$checking_schedule['is_disabled']
										&&
										($checking_minutes > $current_schedule_minutes)
									) {

										foreach ($still_avail as $key => $duration) {

											if (
												!is_null($duration)
												&&
												(($current_schedule_minutes + $duration) >= $checking_minutes)
											) {

												$inventory['tickets_avail_by_duration'][$key] = false;
												unset($still_avail[$key]);
											}

											// Если массив опустел значит недоступное расписание впереди перекрыло всё
											if (!$still_avail) {

												$inventory['in_use'] = true;
												$inventory['in_use_by'] = 'end';
												$inventory['is_avail'] = false;
												$not_avail_inventories++;

												break 2;
											}
										}
									}
								}
							}

							$day_schedule_info['inventories'][$inventory['id']] = $inventory;
						}

						if ($not_avail_inventories == count($trips_inventories[$trip['id']][$date][$schedule['id']])) {

							if (!$day_schedule_info['not_avail']) {

								$not_avail_schedules[$schedule_id] = $schedule_id;
								$day_schedule_info['not_avail'] = true;
							}

							if (!$day_schedule_info['not_avail_ts']) {

								$not_avail_ts_schedules[$schedule_id] = $schedule_id;
								$day_schedule_info['not_avail_ts'] = true;
							}
						}
					}


					if (
						!$day_schedule_info['is_disabled']
						&&
						(
							$valid_schedule_time
							||
							($this->user->is_admin() && $admin_valid_schedule_time)
						)
					) {

						$day_info['has_avail_schedules'] = true;
					}

					if (count($stop_sell_tickets) == count($day_schedule_tickets)) {

						$stop_sell_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['stop_sell'] = true;
					}

					// Есть доступные расписания с билетами в этот день
					if ($day_schedule_info['has_avail_tickets']) $day_info['has_avail_tickets'] = true;

					// Минимальная цена дня
					if (
						!$day_schedule_info['not_avail_ts']
						&&
						(
							($day_info['min_price'] === null)
							||
							(
								($day_schedule_info['min_price'] !== null)
								&&
								($day_info['min_price'] > $day_schedule_info['min_price'])
							)
						)
					) {

						$day_info['min_price'] = $day_schedule_info['min_price'];
						$day_info['min_price_ticket_id'] = $day_schedule_info['min_price_ticket_id'];
					}

					if (
						!$day_schedule_info['not_avail']
						&&
						(
							($day_info['min_price_rs'] === null)
							||
							(
								($day_schedule_info['min_price_rs'] !== null)
								&&
								($day_info['min_price_rs'] > $day_schedule_info['min_price_rs'])
							)
						)
					) {

						$day_info['min_price_rs'] = $day_schedule_info['min_price_rs'];
						$day_info['min_price_rs_ticket_id'] = $day_schedule_info['min_price_rs_ticket_id'];
					}


					$day_schedule_info['tickets'] = $tickets;

					$day_info['schedules'][$schedule_id] = $day_schedule_info;
				}


				$day_info['avail_for_admin'] = $day_has_avail_for_admin_schedules;

				if (count($not_avail_schedules) == count($day_info['schedules'])) {

					$not_avail_days++;
					$day_info['not_avail'] = true;
				}

				if (count($not_avail_ts_schedules) == count($day_info['schedules'])) {

					$not_avail_ts_days++;
					$day_info['not_avail_ts'] = true;
				}

				if (count($stop_sell_schedules) == count($day_info['schedules'])) {

					$day_info['stop_sell'] = true;
				}

				// Минимальная цена среди всех дней
				if (
					!$day_info['not_avail_ts']
					&&
					(
						($trip_days_info['min_price'] === null)
						||
						(
							($day_info['min_price'] !== null)
							&&
							($trip_days_info['min_price'] > $day_info['min_price'])
						)
					)
				) {

					$trip_days_info['min_price'] = $day_info['min_price'];
					$trip_days_info['min_price_ticket_id'] = $day_info['min_price_ticket_id'];
				}

				if (
					!$day_info['not_avail']
					&&
					(
						($trip_days_info['min_price_rs'] === null)
						||
						(
							($day_info['min_price_rs'] !== null)
							&&
							($trip_days_info['min_price_rs'] > $day_info['min_price_rs'])
						)
					)
				) {

					$trip_days_info['min_price_rs'] = $day_info['min_price_rs'];
					$trip_days_info['min_price_rs_ticket_id'] = $day_info['min_price_rs_ticket_id'];
				}


				$trip_days_info['days_info'][$date] = $day_info;
			}


			if ($not_avail_days == count($trip_days)) $trip_days_info['not_avail'] = true;
			if ($not_avail_ts_days == count($trip_days)) $trip_days_info['not_avail_ts'] = true;


			$trips_days_info[$trip['id']] = $trip_days_info;
		}

		if (!$trips_days_info) return array();

		if (!is_array($trips_id)) $trips_days_info = array_pop($trips_days_info);

		$this->cache->save(array('trip_get_trips_tickets_rates', $trips_id, $date_from, $date_to, $upper_level_data), $trips_days_info);

		return $trips_days_info;
	}

	// Минимизированная версия метода
	// Возвращает только данные доступности по трипам и их дням
	public function get_trips_days_availability($trips_id, $date_from, $date_to) {

		$time = time();

		$this->add_page('calendar');


		// Информацию о трипах ищем самостоятельно т.к. тут нужно всего 4 поля одно условие
		$trips = array();

		$this->db->query("
			SELECT
				products.id,
				products.is_delayed,
				products.close_out,
				products.use_schedule
			FROM
				products
			WHERE
				products.id IN (".$this->db->in($trips_id).")"
		);
		$db_res = $this->db->result;

		while ($trip = $this->db->fetch($db_res)) $trips[$trip['id']] = $trip;


		// Парамеры билетов трипов для соответствующего периода
		$trips_days_tickets = $this->get_trips_days_tickets($trips_id, $date_from, $date_to);

		// Остальные выборки делаем только для тех трипов, для которых они нужны и если это вообще ещё нужно
		$rest_trips_id = (is_array($trips_id) ? $trips_id : array($trips_id));
		foreach ($rest_trips_id as $index => $rest_trip_id) {

			if (empty($trips_days_tickets[$rest_trip_id])) unset($rest_trips_id[$index]);
		}

		if ($rest_trips_id) {

			// Параметры расписаний трипов для соответствующего периода
			$trips_schedules = $this->get_trips_days_schedules(($trips_days_tickets ? $trips_days_tickets[0] : array()), $date_from, $date_to);
			unset($trips_days_tickets[0]);

			// Параметры inventory трипов для соответствующего периода
			$trips_inventories = $this->get_inventories_data($trips_id, $date_from, $date_to);
			$trips_days_booked_inventories = $trips_inventories['trips_booked_inventories'];
			$trips_inventories = $trips_inventories['trips_inventories'];
		}



		$trips_days_info = array();

		// Теперь для каждого трипа-дня-билета добавляем параметров
		foreach ($trips as $trip) {

			if (empty($trips_days_tickets[$trip['id']])) continue;


			$trip_days = $trips_days_tickets[$trip['id']];

			$trip_is_delayed = false;
			if (
				is_mode('admin_get_day_trip', 'admin_get_day_trips', 'admin_cart_create', 'admin_get_trip_availability')
				&&
				$trip['is_delayed']
			) {

				$trip_is_delayed = true;
			}


			$trip_days_info = array(
				'not_avail'					=> false,
				'not_avail_ts'				=> false,

				'days_info'					=> array(),
			);

			// Кол-во недоступных дней
			$not_avail_days = 0;
			$not_avail_ts_days = 0;


			// Очередной день с расписаниями-билетами
			foreach ($trip_days as $date => $day_schedules) {

				// В какое время в этот день партнёр закрывается
				$day_data_closeout = $this->page_calendar->get_days_trip_data($trip['id'], 'close_out', array($date));
				$day_close_out = null;
				if (isset($day_data_closeout[$date]) && !is_null($day_data_closeout[$date]['close_out'])) {

					$day_close_out = $day_data_closeout[$date]['close_out'];
				}
				else if (!is_null($trip['close_out'])) {

					$day_close_out = $trip['close_out'];
				}

				$day_info = array (
					'date'						=> $date,

					'not_avail'					=> false,
					'not_avail_ts'				=> false,
					'has_avail_tickets'			=> false,
					'has_avail_schedules'		=> false,

					'schedules'					=> array(),
				);

				$not_avail_schedules = array();
				$not_avail_ts_schedules = array();

				$some_schedule_is_available = false;

				foreach ($day_schedules as $schedule_id => $day_schedule_tickets) {

					$schedule = $trips_schedules[$trip['id']][$date][$schedule_id];

					$day_schedule_info = array (
						'id'						=> ($schedule_id ? $schedule_id : 0),
						'time'						=> ($schedule_id ? convert_hour($schedule['time']) : ''),
						'is_disabled'				=> $schedule['is_disabled'],
						'cut_off_hour'				=> $schedule['cut_off_hour'],

						'min_tickets'				=> $schedule['min_tickets'],
						'max_tickets'				=> $schedule['max_tickets'],
						'tickets_avail'				=> $schedule['tickets_avail'],
						'tickets_avail_for_update'	=> $schedule['tickets_avail_for_update'],
						'sold'						=> $schedule['sold'],

						'not_avail'					=> false,
						'not_avail_ts'				=> false,
						'has_avail_tickets'			=> false,
						'avail_for_admin'			=> true,

						'inventories'				=> array(),
						'tickets'					=> array(),
					);

					// Здесь сразу устонавливается доступность только по close_out
					// Доступность исходя из данных билетов определяется ниже
					if (
						!is_null($day_close_out)
						&&
						$schedule_id
						&&
						convert_hour_to_minutes($schedule['time']) > convert_hour_to_minutes($day_close_out)
					) {

						$day_schedule_info['not_avail'] = true;
						$day_schedule_info['not_avail_ts'] = true;
					}

					$tickets = array();

					$not_avail_tickets = array();
					$not_avail_ts_tickets = array();


					// Очередной билет в этот день
					foreach ($day_schedule_tickets as $ticket) {

						$close_out_check_fails = false;

						$avail = $ticket['avail'];
						$avail_ts = $ticket['avail_ts'];

						if (!is_null($day_schedule_info['max_tickets']) && ($avail > $day_schedule_info['tickets_avail'])) {

							// Доступность базово регулируется доступностью по версии расписания
							$avail = $day_schedule_info['tickets_avail'];
							// Базовый параметр расписания обнуляется если доступно билетов меньше его min-параметра
							// Однако для апдейта при этом билетов может быть больше нуля
							if ($day_schedule_info['tickets_avail'] < $day_schedule_info['tickets_avail_for_update']) {

								$avail = $day_schedule_info['tickets_avail_for_update'];
							}

							// И доступное даже для апдейта число билетов должно быть не больше разнницы
							// между всеми наличными и всеми уже проданными
							$low_level_avail = $ticket['total'] - $ticket['sold'];
							if ($avail > $low_level_avail) $avail = (($low_level_avail < 0) ? 0 : $low_level_avail);
						}
						if (!is_null($day_schedule_info['max_tickets']) && ($avail_ts > $day_schedule_info['tickets_avail'])) {

							$avail_ts = $day_schedule_info['tickets_avail'];
							if ($day_schedule_info['tickets_avail'] < $day_schedule_info['tickets_avail_for_update']) {

								$avail_ts = $day_schedule_info['tickets_avail_for_update'];
							}

							$low_level_avail = $ticket['total_ts'] - $ticket['sold_ts'];
							if ($avail_ts > $low_level_avail) $avail_ts = (($low_level_avail < 0) ? 0 : $low_level_avail);
						}

						$ticket_cut_off_time = strtotime(date("Y-m-d", $time + (12 - $ticket['cut_off_time'])*3600 + $ticket['cut_off']*3600*24 + 1));

						// Недоступность билета в этот день (для нового заказа)
						$not_avail = false;
						$not_avail_ts = false;

						if (
							$ticket['is_disabled']
								||
							(
								// В RS актуально только для фронта
								(!is_api_request() || is_front_api_request())
									&&
								(strtotime($date) < $ticket_cut_off_time)
							)
						) {

							$not_avail = true;
							$not_avail_tickets[$ticket['id']] = $ticket['id'];

							$not_avail_ts = true;
							$not_avail_ts_tickets[$ticket['id']] = $ticket['id'];
						}
						else {

							if (
								($avail <= 0)
								||
								($ticket['min'] && ($avail < $ticket['min']))
							) {

								$not_avail = true;
								$not_avail_tickets[$ticket['id']] = $ticket['id'];
							}

							if (
								($avail_ts <= 0)
								||
								($ticket['min'] && ($avail_ts < $ticket['min']))
							) {

								$not_avail_ts = true;
								$not_avail_ts_tickets[$ticket['id']] = $ticket['id'];
							}
						}

						// Доступность билета также должна учитывать close_out время дня
						if (!is_null($day_close_out) && $schedule_id && !is_null($ticket['duration'])) {

							$ticket_end_minutes = convert_hour_to_minutes($schedule['time']) + $ticket['duration'];

							if ($ticket_end_minutes > convert_hour_to_minutes($day_close_out)) {

								$not_avail = true;
								$not_avail_tickets[$ticket['id']] = $ticket['id'];
								$not_avail_ts = true;
								$not_avail_ts_tickets[$ticket['id']] = $ticket['id'];

								$close_out_check_fails = true;
							}
						}

						if (
							($avail_ts > 0) && !$ticket['is_disabled']
							&&
							!$close_out_check_fails
							&&
							(
								!$ticket['min']
								||
								($avail_ts >= $ticket['min'])
							)
						) {

							$day_schedule_info['has_avail_tickets'] = true;
						}


						$tickets[$ticket['id']] = array (
							'id'				=> $ticket['id'],
							'duration'			=> $ticket['duration'],
							'is_disabled'		=> $ticket['is_disabled'],

							'not_avail'				=> $not_avail,
							'not_avail_ts'			=> $not_avail_ts,
						);
					}

					$valid_schedule_time = true;

					// Следующие проверки в RS актуальны только для фронта
					if ($schedule['id'] && (!is_api_request() || is_front_api_request())) {

						list($schedule_hour, $schedule_minutes) = explode(':', $schedule['time']);

						$schedule_time_with_cut_off = $schedule_hour - $schedule['cut_off_hour'];
						$schedule_today_start_time = mktime($schedule_time_with_cut_off, $schedule_minutes, 0, date("m", strtotime($date)), date("d", strtotime($date)), date("Y", strtotime($date)));

						if ($schedule_today_start_time < $time) $valid_schedule_time = false;
					}

					// Доступность расписания
					if (
						$day_schedule_info['is_disabled']
						||
						(!$trip_is_delayed && !$valid_schedule_time)
						||
						(
							$trip['use_schedule']
							&&
							!is_null($day_schedule_info['tickets_avail'])
							&&
							($day_schedule_info['tickets_avail'] <= 0)
						)
					) {

						$not_avail_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['not_avail'] = true;

						$not_avail_ts_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['not_avail_ts'] = true;
					}

					if (count($not_avail_tickets) == count($day_schedule_tickets)) {

						$not_avail_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['not_avail'] = true;
					}

					if (
						(count($not_avail_ts_tickets) == count($day_schedule_tickets))
						&&
						!($this->user->is_admin() && $day_schedule_info['has_avail_tickets'])
					) {

						$not_avail_ts_schedules[$schedule_id] = $schedule_id;
						$day_schedule_info['not_avail_ts'] = true;
					}



					// Inventories
					if (!empty($trips_inventories[$trip['id']][$date][$schedule['id']])) {

						$not_avail_inventories = 0;

						$tickets_durations = array();

						foreach ($tickets as $ticket) {

							// Для валидации по длительности интресуют только доступные для заказа билеты
							if (
								$ticket['is_disabled']
								||
								(
									is_api_request()
									&&
									$ticket['not_avail']
								)
								||
								(
									!is_api_request()
									&&
									$ticket['not_avail_ts']
								)
							) continue;

							$tickets_durations[$ticket['id']] = (!empty($ticket['duration']) ? $ticket['duration'] : 0);
						}

						foreach ($trips_inventories[$trip['id']][$date][$schedule['id']] as $inventory) {

							$inventory['is_avail'] = true;
							$inventory['in_use'] = false;

							if (
								$inventory['is_disabled']
								||
								!$inventory['avail']
							) {

								$not_avail_inventories++;
								// В данном методе нас интересует только доступность лодки
								continue;
							}

							// Определяем доступные на данные лодку-расписание билеты - ТОЛЬКО по длительности
							$inventory['tickets_avail_by_duration'] = array();

							foreach ($day_schedule_tickets as $ticket_duration) {

								if (empty($inventory['duration']) || empty($ticket_duration['duration'])) {

									$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = true;
								}
								else if ($inventory['duration'] === $ticket_duration['duration']) {

									$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = true;
								}
								else {

									$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = false;
								}
							}

							if (!empty($trips_days_booked_inventories[$date][$inventory['id']])) {

								$current_schedule_minutes = convert_hour_to_minutes($schedule['time']);

								$current_inventory_duration = (!empty($inventory['duration']) ? $inventory['duration'] : 0);

								// Если у лодки на текущее расписание длительности ещё нет,
								// то проверять будем по всем актуальным билетам
								if ($current_inventory_duration) {

									$current_inventory_end_minutes = $current_schedule_minutes + $current_inventory_duration;
								}
								else {

									$current_inventory_end_minutes = array();

									foreach ($tickets_durations as $ticket_id => $ticket_duration) {

										$current_inventory_end_minutes[$ticket_id] = $current_schedule_minutes + $ticket_duration;
									}
								}

								// Сортируем заказы на лодку по показателю расписания - таким образом в случае блока
								// и слева и справа всегда приоритет будет у in_use_by == start
								ksort($trips_days_booked_inventories[$date][$inventory['id']]);

								foreach ($trips_days_booked_inventories[$date][$inventory['id']] as $shedule_time => $trips_ids) {

									foreach ($trips_ids as $trip_id => $duration) {

										$schedule_minutes = convert_hour_to_minutes($shedule_time);

										$inventory_end_minutes = $schedule_minutes + $duration;

										$end_minutes_check = false;
										if (is_array($current_inventory_end_minutes)) {

											foreach ($current_inventory_end_minutes as $ticket_id => $ticket_end_minutes) {

												if ($schedule_minutes > $ticket_end_minutes) {

													$end_minutes_check = true;
												}
												else {

													// Если уже заказанный инвентарь на более раннее расписание, то такому билету ставим недоступность
													if ($inventory_end_minutes > $current_schedule_minutes) {

														$inventory['tickets_avail_by_duration'][$ticket_id] = false;
													}
												}
											}
										}
										else {

											$end_minutes_check = (($schedule_minutes > $current_inventory_end_minutes) ? true : false);
										}

										if (
											(
												($trip['id'] == $trip_id)
												&&
												($current_schedule_minutes == $schedule_minutes)
											)
											||
											$end_minutes_check
											||
											($inventory_end_minutes < $current_schedule_minutes)
										) continue;

										if ($inventory['is_avail']) {

											$not_avail_inventories++;
											$inventory['is_avail'] = false;
										}

										$inventory['in_use'] = true;

										break 2;
									}
								}
							}

							// На случай если следующие расписания после текущего отключены
							// Корректируем доступность билетов по длительности и общий in_use лодки
							if (!$inventory['in_use'] && $inventory['is_avail']) {

								$current_schedule_minutes = convert_hour_to_minutes($schedule['time']);
								$still_avail = array();
								foreach ($inventory['tickets_avail_by_duration'] as $id => $value) {

									if ($value) $still_avail[$id] = $day_schedule_tickets[$id]['duration'];
								}

								foreach ($trips_schedules[$trip['id']][$date] as $checking_schedule) {

									if ($checking_schedule['id'] == 0) continue;

									$checking_minutes = convert_hour_to_minutes($checking_schedule['time']);

									if (
										$trips_inventories[$trip['id']][$date][$checking_schedule['id']][$inventory['id']]['is_disabled']
											&&
										($checking_minutes > $current_schedule_minutes)
									) {

										foreach ($still_avail as $key => $duration) {

											if (
												!is_null($duration)
												&&
												(($current_schedule_minutes + $duration) >= $checking_minutes)
											) {

												$inventory['tickets_avail_by_duration'][$key] = false;
												unset($still_avail[$key]);
											}

											// Если массив опустел значит недоступное расписание впереди перекрыло всё
											if (!$still_avail) {

												$not_avail_inventories++;

												break 2;
											}
										}
									}
								}
							}
						}

						if ($not_avail_inventories == count($trips_inventories[$trip['id']][$date][$schedule['id']])) {

							if (!$day_schedule_info['not_avail']) {

								$not_avail_schedules[$schedule_id] = $schedule_id;
								$day_schedule_info['not_avail'] = true;
							}

							if (!$day_schedule_info['not_avail_ts']) {

								$not_avail_ts_schedules[$schedule_id] = $schedule_id;
								$day_schedule_info['not_avail_ts'] = true;
							}
						}
					}


					// Если доступно хоть одно расписание, то день доступен и дальше можно не смотреть
					if (!$day_schedule_info['not_avail'] && !$day_schedule_info['not_avail_ts']) {

						$some_schedule_is_available = true;

						break;
					}

					$day_info['schedules'][$schedule_id] = true;
				}

				if (!$some_schedule_is_available) {

					if (count($not_avail_schedules) == count($day_info['schedules'])) {

						$not_avail_days++;
						$day_info['not_avail'] = true;
					}

					if (count($not_avail_ts_schedules) == count($day_info['schedules'])) {

						$not_avail_ts_days++;
						$day_info['not_avail_ts'] = true;
					}
				}

				$trip_days_info['days_info'][$date] = $day_info;
			}


			if ($not_avail_days == count($trip_days)) $trip_days_info['not_avail'] = true;
			if ($not_avail_ts_days == count($trip_days)) $trip_days_info['not_avail_ts'] = true;


			$trips_days_info[$trip['id']] = $trip_days_info;
		}

		if (!$trips_days_info) return array();

		if (!is_array($trips_id)) $trips_days_info = array_pop($trips_days_info);

		$this->cache->save(array('trip_get_trips_tickets_rates', $trips_id, $date_from, $date_to, true), $trips_days_info);

		return $trips_days_info;
	}

	// Получаем дефолтные параметры билетов
	// $trip_ids - array() || int
	public function get_trips_tickets_default_rates($trip_ids, $with_tickets = true) {

		$trips = array();
		$trips_fh_ids = array();

		$trip_ids = (is_array($trip_ids) ? $trip_ids : array($trip_ids));

		$this->db->query("
			SELECT
				products.id AS product_id,
				products.fareharbor_id AS product_fh_id,
				products.use_calendar AS use_calendar,

				trip_ticket.id AS ticket_id,
				trip_ticket.fareharbor_id AS ticket_fh_id,
				trip_ticket.price AS ticket_price,
				trip_ticket.price_rs AS ticket_price_rs,
				trip_ticket.strike_out AS ticket_strike_out,
				trip_ticket.total AS ticket_total,
				trip_ticket.total_ts AS ticket_total_ts,
				trip_ticket.is_default AS ticket_is_default
			FROM
				products
				JOIN trip_ticket ON trip_ticket.trip_id = products.id
			WHERE
				products.id IN (".$this->db->in($trip_ids).")
					AND
				trip_ticket.rs_internal	= 0
			ORDER BY
				products.id ASC, trip_ticket.view_order ASC"
		);

		$default_ticket_id = null;
		$trips_data = array();

		while ($trip_ticket = $this->db->fetch()) {

			if (!is_null($trip_ticket['product_fh_id'])) {

				$trips_fh_ids[$trip_ticket['product_id']] = $trip_ticket['product_fh_id'];
			}

			if (
				(
					is_null($trip_ticket['product_fh_id'])
						&&
					($trip_ticket['ticket_fh_id'] != 0)
				)
					||
				(
					!is_null($trip_ticket['product_fh_id'])
					&&
					($trip_ticket['ticket_fh_id'] == 0)
				)
			) continue;

			if (!isset($trips_data[$trip_ticket['product_id']])) $trips_data[$trip_ticket['product_id']] = array (
				'has_default_ticket'	=> false,
				'default_ticket_id'		=> null,
			);

			if ($trip_ticket['ticket_is_default']) {

				$trips_data[$trip_ticket['product_id']]['has_default_ticket'] = true;
				$trips_data[$trip_ticket['product_id']]['default_ticket_id'] = $trip_ticket['ticket_id'];
			}

			if (!isset($trips_calendar_use[$trip_ticket['product_id']])) {

				$trips_data[$trip_ticket['product_id']]['use_calendar'] = $trip_ticket['use_calendar'];
			}

			$trips[$trip_ticket['product_id']][$trip_ticket['ticket_id']] = array (
				'id'			=> $trip_ticket['ticket_id'],
				'price'			=> $trip_ticket['ticket_price'],
				'price_rs'		=> $trip_ticket['ticket_price_rs'],
				'strike_out'	=> $trip_ticket['ticket_strike_out'],
				'total'			=> $trip_ticket['ticket_total'],
				'total_ts'		=> $trip_ticket['ticket_total_ts'],
				'is_default'	=> $trip_ticket['ticket_is_default'],
			);
		}

		$trips_tickets = array();

		// Обходим трипы
		foreach ($trips as $trip_id => $trip_tickets) {

			$trip_tickets_data = array(
				'min_price'					=> null,
				'min_price_ticket_id'		=> null,
				'min_price_rs'				=> null,
				'min_price_rs_ticket_id'	=> null,

				'use_calendar'				=> $trips_data[$trip_id]['use_calendar'],
				'default_ticket_id'			=> $trips_data[$trip_id]['default_ticket_id'],

				'fareharbor_id'				=> (isset($trips_fh_ids[$trip_id]) ? $trips_fh_ids[$trip_id] : null),

				'tickets'					=> array(),
			);

			$have_default_ticket_already = false;
			$rs_have_default_ticket_already = false;


			// Обходим билеты
			foreach ($trip_tickets as $ticket_id => $ticket) {

				// Отсутствие доступных билетов
				$ticket['not_avail'] = false;
				if (!$ticket['total_ts']) $ticket['not_avail'] = true;

				$ticket['not_avail_rs'] = false;
				if (!$ticket['total']) $ticket['not_avail_rs'] = true;


				if (
					!$have_default_ticket_already
						&&
					!$ticket['not_avail']
						&&
					($ticket['price'] > 0)
						&&
					(
						($trip_tickets_data['min_price'] == null)
							||
						$ticket['is_default']
							||
						(
							!$ticket['is_default']
								&&
							($trip_tickets_data['min_price'] > $ticket['price'])
						)
					)
				) {

					if ($ticket['is_default']) $have_default_ticket_already = true;

					$trip_tickets_data['min_price'] = $ticket['price'];
					$trip_tickets_data['min_price_ticket_id'] = $ticket_id;
					$trip_tickets_data['strike_out'] = $ticket['strike_out'];
				}

				if (
					!$rs_have_default_ticket_already
						&&
					!$ticket['not_avail_rs']
						&&
					($ticket['price_rs'] > 0)
						&&
					(
						($trip_tickets_data['min_price_rs'] == null)
							||
						$ticket['is_default']
							||
						(
							!$ticket['is_default']
								&&
							($trip_tickets_data['min_price_rs'] > $ticket['price_rs'])
						)
					)
					) {

					if ($ticket['is_default']) $rs_have_default_ticket_already = true;

					$trip_tickets_data['min_price_rs'] = $ticket['price_rs'];
					$trip_tickets_data['min_price_rs_ticket_id'] = $ticket_id;
				}


				$trip_tickets_data['tickets'][$ticket_id] = ($with_tickets ? $ticket : null);
			}


			$trips_tickets[$trip_id] = $trip_tickets_data;
		}

		// Если ни одного билета у трипа нет, то делаем заглушку
		foreach ($trip_ids as $trip_id) {

			if (!isset($trips_tickets[$trip_id])) {

				$trips_tickets[$trip_id] = array (
					'min_price'					=> null,
					'min_price_ticket_id'		=> null,
					'min_price_rs'				=> null,
					'min_price_rs_ticket_id'	=> null,

					'use_calendar'				=> 0,
					'default_ticket_id'			=> null,

					'tickets'					=> array(),
				);
			}
		}


		return $trips_tickets;
	}

	// Название историческое - по сути метод определяет цену дефолтного билета:
	// за день вызова метода, либо за первый день указанного периода
	// Цена берётся из календаря, а если её нет - то дефолтная из настроек трипа
	// Если дефолтный билет не назначен, то берём все билеты и выбираем мин. цену.
	// !Не учитываем при этом фактическую дотупность по имеющимся заказам!
	// $trip_ids - array() || int
	// $date_from, $date_to - 'Y-m-d' || null
	// Для одного трипа($trip_ids - int) возвращает конечный массив с ценой и ticket_id, для нескольких - общий
	// [$trip_id] => array ('min_price' => $min_price, 'ticket_id' => $min_price_ticket_id)
	public function get_trips_min_prices($trip_ids, $date_from = null, $date_to = null) {

		$cache_args = array('get_trips_min_prices', $trip_ids, $date_from, $date_to);

		if (($trips_min_prices = $this->cache->load_from_file($cache_args)) !== null) return $trips_min_prices;

		$trips = (!is_array($trip_ids) ? array($trip_ids) : $trip_ids);


		// Минимальные цены по дефолту
		$trips_min_prices = $this->get_trips_tickets_default_rates($trips, false);

		// Для тех, кто не использует календарь, больше ничего не ищем
		foreach ($trips as $index => $trip_id) {

			if (!$trips_min_prices[$trip_id]['use_calendar']) unset($trips[$index]);
		}

		if ($trips) {

			foreach ($trips as $trip_id) {

				$trip = $trips_min_prices[$trip_id];

				// По каким билетам искать
				$search_tickets = (!is_null($trip['default_ticket_id']) ? array(($trip['default_ticket_id'])) : array_keys($trip['tickets']));

				// Первый период анализа либо уже указан (значит период единственный), либо берём ближайшую неделю
				$search_date = (($date_from !== null) ? $date_from : format_date_for_db());

				$day_ticket_info_stack = array();


				if (is_null($trip['fareharbor_id'])) {

					$this->db->query("
						SELECT
							calendar.id,
							calendar.date,
	
							calendar_ticket.*
						FROM
							calendar
							JOIN calendar_ticket ON (
								calendar_ticket.calendar_id = calendar.id
									AND
								calendar_ticket.ticket_id IN (" . $this->db->in($search_tickets) . ")
							)
						WHERE
							calendar.date = ?
								AND
							calendar.product_id = ?",
						$search_date,
						$trip_id
					);
					$db_res = $this->db->result;

					while ($one_day_ticket_info = $this->db->fetch($db_res)) {

						$day_ticket_info_stack[] = $one_day_ticket_info;
					}
				}
				else if (empty($trip['min_price'])) {

					$fh_availability = $this->get_fh_availability($trip['fareharbor_id'], $search_date, $search_date);

					if (isset($fh_availability['days_info'][$search_date]['schedules'])) {

						foreach ($fh_availability['days_info'][$search_date]['schedules'] as $day_schedule) {

							foreach ($day_schedule['tickets'] as $ticket_id => $ticket) {

								$ticket['ticket_id'] = $ticket_id;
								$day_ticket_info_stack[] = $ticket;
							}
						}
					}
				}


				// Если билет один (дефолтный), то он просто запишется в эти структуры и у цикла будет один шаг
				// Если билетов несколько (нет дефолтного), то цикл запишет в эти переменные самый дешёвый из них
				$day_ticket = null;
				$day_ticket_rs = null;

				foreach ($day_ticket_info_stack as $day_ticket_info) {

					// Если текущий билет является носителем минимальной цены, определённым по дефолту,
					// а в текущей календарной ячейке у него цены нет, то ставим весто неё дефолтную
					// т.к. именно это и сделает алгоритм при заказе (то же самое при strike_out)
					if (
						empty($day_ticket_info['price'])
							&&
						($day_ticket_info['ticket_id'] == $trip['min_price_ticket_id'])
							&&
						!empty($trip['min_price'])
					) {

						$day_ticket_info['price'] = $trip['min_price'];
					}

					if (
						empty($day_ticket_info['strike_out'])
							&&
						($day_ticket_info['ticket_id'] == $trip['min_price_ticket_id'])
							&&
						!empty($trip['strike_out'])
							&&
						($trip['strike_out'] > $day_ticket_info['price'])
					) {

						$day_ticket_info['strike_out'] = $trip['strike_out'];
					}

					if (
						empty($day_ticket_info['price_rs'])
							&&
						($day_ticket_info['ticket_id'] == $trip['min_price_rs_ticket_id'])
							&&
						!empty($trip['min_price_rs'])
					) {

						$day_ticket_info['price_rs'] = $trip['min_price_rs'];
					}


					// Собственно сравнения
					if (
						!is_null($day_ticket_info['price'])
							&&
						(
							is_null($day_ticket)
								||
							($day_ticket_info['price'] < $day_ticket['min_price'])
						)
					) {
						if (is_null($day_ticket)) $day_ticket = array();

						$day_ticket['min_price'] = $day_ticket_info['price'];
						$day_ticket['min_price_ticket_id'] = $day_ticket_info['ticket_id'];
						if (!is_null($day_ticket_info['strike_out'])) $day_ticket['strike_out'] = $day_ticket_info['strike_out'];
					}
					if (
						!is_null($day_ticket_info['price_rs'])
							&&
						(
							is_null($day_ticket_rs)
								||
							($day_ticket_info['price_rs'] < $day_ticket_rs['min_price_rs'])
						)
					) {
						if (is_null($day_ticket_rs)) $day_ticket_rs = array();

						$day_ticket_rs['min_price_rs'] = $day_ticket_info['price_rs'];
						$day_ticket_rs['min_price_rs_ticket_id'] = $day_ticket_info['ticket_id'];
					}
				}

				if (!is_null($day_ticket)) {

					$trip['min_price'] = $day_ticket['min_price'];
					$trip['min_price_ticket_id'] = $day_ticket['min_price_ticket_id'];
					if (isset($day_ticket['strike_out'])) $trip['strike_out'] = $day_ticket['strike_out'];
				}

				if (!is_null($day_ticket_rs)) {

					$trip['min_price_rs'] = $day_ticket_rs['min_price_rs'];
					$trip['min_price_rs_ticket_id'] = $day_ticket_rs['min_price_rs_ticket_id'];
				}

				$trips_min_prices[$trip_id] = $trip;
			}
		}

		if (!is_array($trip_ids) && $trips_min_prices) $trips_min_prices = $trips_min_prices[$trip_ids];

		$this->cache->save_to_file($cache_args, $trips_min_prices);

		return $trips_min_prices;
	}


	// Актуальные данные доп. опций для конкретного дня
	public function get_day_current_options($trip_id, $date) {

		// Дефолтные опции для трипа
		$options = $this->get_options($trip_id);

		// Берём данные по опциям за определенный день
		$this->db->query("
			SELECT
				calendar_option.*
			FROM
				calendar
				LEFT JOIN calendar_option ON calendar_option.calendar_id = calendar.id
			WHERE
				calendar.product_id = ?
					AND
				calendar.date = ?",
			$trip_id,
			$date
		);

		while ($row = $this->db->fetch()) {

			foreach ($options as $option_id => $option) {

				if ($row['option_id'] == $option_id) {

					$option['is_disabled'] = $row['is_disabled'];
					$option['apply_tax'] = $row['apply_tax'];
				}

				foreach ($option['values'] as $value_id => $value) {

					if ($row['value_id'] == $value_id) {

						if ($row['price'] !== null) {

							$value['price'] = $row['price'];
						}
						if ($row['is_disabled'] !== null) {

							$value['is_disabled'] = $row['is_disabled'];
						}
						if ($row['apply_tax'] !== null) {

							$value['apply_tax'] = $row['apply_tax'];
						}
					}

					if (is_api_request()) $value['margin'] = 0;

					$option['values'][$value_id] = $value;
				}

				$options[$option_id] = $option;
			}
		}

		return $options;
	}

	// Ближайший доступный для заказа день
	public function get_first_avail_date($product_id, $date_after) {

		$first_avail_date = null;

		$this->add_page('trip');
		$this->add_page('calendar');

		$product = $this->product->get($product_id);
		$is_fh_trip = (is_null($product['fareharbor_id']) ? false : true);

		if (!$is_fh_trip) {

			$loop_counter = 52;
			$date_from = $date_after;
			$date_to = date("Y-m-d", strtotime('+1 week', strtotime($date_from)));
		}
		else {

			$fh_data = explode("|", $product['fareharbor_id']);

			$this->db->query("
				SELECT
					fh_availability_cache.date_from,
					fh_availability_cache.date_to
				FROM
					fh_availability_cache
				WHERE
					fh_availability_cache.fh_id = ?
						AND
					fh_availability_cache.date_to >= ?",
				$fh_data[1],
				format_date_for_db($date_after)
			);
			$db_res = $this->db->result;

			$cached_periods = array();
			while ($cached_period = $this->db->fetch($db_res)) {

				$cached_periods[] = array (
					'date_from'	=> $cached_period['date_from'],
					'date_to'	=> $cached_period['date_to'],
				);
			}

			// Если кеша нет, то по "живым" данным пока что не ищем
			if (!$cached_periods) {

				return null;
			}
			else {

				$loop_counter = count($cached_periods);
			}

			$partner_disabled_days = array();
		}


		while ($loop_counter && ($first_avail_date === null)) {

			if (!$is_fh_trip) {

				$trip_rates = $this->page_trip->get_trips_tickets_rates($product_id, $date_from, $date_to, true);

				$partner_disabled_days = $this->page_calendar->get_days_trip_data(
					$product_id,
					'front_booking_disabled',
					dates_from_interval($date_from, $date_to)
				);
			}
			else {

				$current_period = array_shift($cached_periods);

				$trip_rates = $this->page_trip->get_fh_availability(
					$product['fareharbor_id'],
					$current_period['date_from'],
					$current_period['date_to']
				);

				$partner_disabled_days = $this->page_calendar->get_days_trip_data(
					$product_id,
					'front_booking_disabled',
					dates_from_interval($current_period['date_from'], $current_period['date_to'])
				);
			}
			
			if(!is_api_request()) {

				if (isset($trip_rates['not_avail_ts']) && !$trip_rates['not_avail_ts']) {
	
					foreach ($trip_rates['days_info'] as $date => $day_info) {
	
						if (
							!(
								array_key_exists($date, $partner_disabled_days)
									&&
								!empty($partner_disabled_days[$date]['front_booking_disabled'])
							)
								&&
							!$day_info['not_avail_ts']
								&&
							($first_avail_date === null)
						) {
	
							$first_avail_date = $date;
							break;
						}
					}
				}
			}
			else {

				if (isset($trip_rates['not_avail']) && !$trip_rates['not_avail']) {
	
					foreach ($trip_rates['days_info'] as $date => $day_info) {
	
						if (
							!(
								array_key_exists($date, $partner_disabled_days)
								&&
								!empty($partner_disabled_days[$date]['front_booking_disabled'])
							)
								&&
							!$day_info['not_avail']
								&&
							($first_avail_date === null)
						) {
	
							$first_avail_date = $date;
							break;
						}
					}
				}
			}

			$loop_counter--;

			if (!$is_fh_trip) {

				$date_from = $date_to;
				$date_to = date("Y-m-d", strtotime('+1 week', strtotime($date_from)));
			}
		}

		return $first_avail_date;
	}

	// Проверяем, меняется ли цена на любой из билетов трипа в будущем
	public function is_price_changes_for_year($trip_id) {

		// Подарки пока что отключены поэтому тут заглушка
		return true;

		if (($result = $this->cache->load(array('is_price_changes_for_year', $trip_id))) !== null) return $result;

		$this->db->query("
			SELECT
				1
			FROM
				calendar
				JOIN trip_ticket ON trip_ticket.trip_id = calendar.product_id
				LEFT JOIN calendar_ticket ON (
					calendar_ticket.calendar_id = calendar.id
						AND
					calendar_ticket.ticket_id = trip_ticket.id
				)
			WHERE
				calendar.product_id = ?
					AND
				calendar.date > CURDATE()
					AND
				NOT calendar_ticket.price IS NULL
					AND
				calendar_ticket.price <> trip_ticket.price
			LIMIT 1",
			$trip_id
		);

		$result = ($this->db->count() ? true : false);

		$this->cache->save(array('is_price_changes_for_year', $trip_id), $result);

		return $result;
	}


	// Дополнительные опции трипа со значениями
	// $not_disabled - вернёт только включённые опции/значения
	public function get_options($trips_id, $option_id = null, $not_disabled = false, $ignore_fh = false) {

		if (!is_array($trips_id)) $search_trips_id = array($trips_id);
		else $search_trips_id = $trips_id;

		if (!$search_trips_id) return;

		$where = array();
		$values = array();

		if ($search_trips_id) {

			$where[] = "trips_options.product_id IN (".$this->db->in($search_trips_id).")";
		}

		if ($option_id) {

			$where[] = "trips_options.id = ?";
			$values[] = $option_id;
		}

		if (!$ignore_fh) {

			$where[] = "
			(
				(
					products.fareharbor_id IS NULL
						AND
					trips_options.fareharbor_id = 0
				)
					OR
				(
					products.fareharbor_id IS NOT NULL
						AND
					trips_options.fareharbor_id != 0
				)
			)
			";
		}
		else {

			$where[] = "trips_options.fareharbor_id = 0";
		}

		$trips_additional_options = array();

		$this->db->query("
			SELECT
				trips_options.id AS option_id,
				trips_options.fareharbor_id AS option_fh_id,
				trips_options.product_id AS trip_id,
				trips_options.name AS option_name,
				trips_options.is_disabled AS option_is_disabled,
				trips_options.required AS option_required,
				trips_options.type AS option_type,

				trips_options_values.id AS value_id,
				trips_options_values.fareharbor_id AS value_fh_id,
				trips_options_values.name AS value_name,
				trips_options_values.price AS value_price,
				trips_options_values.is_disabled AS value_is_disabled,
				trips_options_values.apply_tax AS value_apply_tax,

				users.comission AS partner_comission
			FROM
				trips_options
				LEFT JOIN trips_options_values ON trips_options_values.option_id = trips_options.id
				JOIN products ON products.id = trips_options.product_id
				JOIN users ON users.id = products.partner_id
			WHERE
				".implode("\r\n AND ", $where)."
			ORDER BY
				trips_options.id, trips_options_values.id",
			$values
		);

		while ($trip_option = $this->db->fetch()) {

			if (
				$not_disabled
					&&
				(
					($trip_option['value_id'] === null)
						||
					$trip_option['option_is_disabled']
						||
					$trip_option['value_is_disabled']
				)
				) continue;

			if (empty($trips_additional_options[$trip_option['trip_id']][$trip_option['option_id']])) {

				$trips_additional_options[$trip_option['trip_id']][$trip_option['option_id']] = array (
					'id'			=> $trip_option['option_id'],
					'fh_id'			=> $trip_option['option_fh_id'],
					'name'			=> $trip_option['option_name'],
					'is_disabled'	=> $trip_option['option_is_disabled'],
					'required'		=> $trip_option['option_required'],
					'type'			=> $trip_option['option_type'],
					'values'		=> array(),
				);
			}

			if ($trip_option['value_id']) {

				$trips_additional_options[$trip_option['trip_id']][$trip_option['option_id']]['values'][$trip_option['value_id']] = array (
					'id'			=> $trip_option['value_id'],
					'fh_id'			=> $trip_option['value_fh_id'],
					'name'			=> $trip_option['value_name'],
					'price'			=> $trip_option['value_price'],
					'is_disabled'	=> $trip_option['value_is_disabled'],
					'apply_tax'		=> $trip_option['value_apply_tax'],
					'comission'		=> $trip_option['partner_comission'],
				);
			}
		}

		$trips_without_tickets = array_diff($search_trips_id, array_keys($trips_additional_options));

		foreach ($trips_without_tickets as $trip_id) $trips_additional_options[$trip_id] = array();

		if (!is_array($trips_id) && $trips_additional_options) $trips_additional_options = array_pop($trips_additional_options);

		return $trips_additional_options;
	}

	
	// Получение количества заказов на расписание с заданным id
	public function get_schedule_orders($id) {

		$orders = 0;
				
		$where = array();
		$values = array();
		
		$where[] = "cart_items.data LIKE ?";
		$values[] = '%"schedule":{"id":"'.$id.'"%';
		
		$this->db->query("
			SELECT
				COUNT(orders.id) as orders
			FROM
				cart_items
				JOIN orders ON orders.cart_id = cart_items.cart_id
			".($where ? "WHERE ".implode("\r\n AND ", $where) : "")."",
			$values
		);
		$db_res = $this->db->result;
		
		if ($result = $this->db->fetch($db_res)) $orders = $result['orders'];
		
		return $orders;
	}
	
	// Расписание конкретного трипа
	// используем get_trips_schedules
	public function get_schedules($trip_id) {

		if (!$trip_id) return null;

		return $this->get_trips_schedules($trip_id);
	}

	// Получаем список расписаний для трипов
	// @param bool $show_inactive Выводить ли расписания трипов с отключенными расписанями(use_schedule == 0)
	public function get_trips_schedules($trips_id = array(), $partner_id = null, $show_inactive = true, $ignore_fh = false) {

		if (($trips_shedule = $this->cache->load(array('get_trips_schedules', $trips_id, $partner_id))) !== null) return $trips_shedule;

		if (!is_array($trips_id)) $search_trips_id = array($trips_id);
		else $search_trips_id = $trips_id;

		$where = array();
		$values = array();

		if ($search_trips_id) {

			$where[] = "trip_schedule.trip_id IN (".$this->db->in($search_trips_id).")";
		}

		if ($partner_id) {

			$where[] = "products.partner_id = ?";
			$values[] = $partner_id;
		}

		if (!$show_inactive) {

			$where[] = "products.use_schedule = 1";
		}


		if (!$ignore_fh) {

			$where[] = "
			(
				(
					products.fareharbor_id IS NULL
						AND
					trip_schedule.fareharbor_id = 0
				)
					OR
				(
					products.fareharbor_id IS NOT NULL
						AND
					trip_schedule.fareharbor_id != 0
				)
			)
			";
		}
		else {

			$where[] = "trip_schedule.fareharbor_id = 0";
		}


		$trips_shedules = array();

		$this->db->query("
			SELECT
				trip_schedule.*,

				products.name AS trip_name
			FROM
				trip_schedule
				JOIN products ON products.id = trip_schedule.trip_id
			".($where ? "WHERE ".implode("\r\n AND ", $where) : "")."
			ORDER BY
				trip_schedule.trip_id ASC,
				trip_schedule.time ASC",
			$values
		);

		while ($trip_schedule = $this->db->fetch()) {

			if (!$trip_schedule['min_tickets']) $trip_schedule['min_tickets'] = '';
			// Для макс. числа билетов 0 это 0, а null - бесконечность
			if (is_null($trip_schedule['max_tickets'])) $trip_schedule['max_tickets'] = '';

			// Конвертируем имя(время) расписания в 12-часовой формат
			// оставляя и 24х часовой в отдельном ключе
			$trip_schedule['time_24hour'] = $trip_schedule['time'];
			$trip_schedule['time'] = convert_hour($trip_schedule['time']);

			$trips_shedules[$trip_schedule['trip_id']][$trip_schedule['id']] = $trip_schedule;
		}

		// Для трипов без расписаний записываем пустые массивы
		$trips_without_schedules = array_diff($search_trips_id, array_keys($trips_shedules));

		foreach ($trips_without_schedules as $trip_id) $trips_shedules[$trip_id] = array();

		if (!is_array($trips_id) && $trips_shedules) $trips_shedules = array_pop($trips_shedules);

		$this->cache->save(array('trip_get_schedules', $trips_id), $trips_shedules);

		return $trips_shedules;
	}

	// Возвращает список добавленных к трипам inventory
	// если отсутствует прописанное у trip-inventory значение поля, то используется дефолтное значение от этого inventory
	public function get_trips_inventories($trips_id, $ignore_fh = false) {

		if (!is_array($trips_id)) $search_trips_id = array($trips_id);
		else $search_trips_id = $trips_id;

		if (!$trips_id) return null;


		$trips_inventories = array();

		$this->db->query("
			SELECT
				trip_inventory.trip_id,
				trip_inventory.inventory_id,

				inventory.name AS inventory_name,

				IFNULL(trip_inventory.description, inventory.description) AS description,
				IFNULL(trip_inventory.priority, inventory.priority) AS priority,
				IFNULL(trip_inventory.max_places, inventory.max_places) AS max_places,
				IFNULL(trip_inventory.is_disabled, inventory.is_disabled) AS is_disabled
			FROM
				trip_inventory
				JOIN inventory ON inventory.id = trip_inventory.inventory_id
				".(!$ignore_fh ? "JOIN products ON products.id = trip_inventory.trip_id" : "")."
			WHERE
				".(!$ignore_fh ? "products.fareharbor_id IS NULL AND " : "")."
				trip_inventory.trip_id IN (".$this->db->in($trips_id).")"
		);

		while ($trip_inventory = $this->db->fetch()) {

			$trips_inventories[$trip_inventory['trip_id']][$trip_inventory['inventory_id']] = array (
				'id'			=> $trip_inventory['inventory_id'],
				'name'			=> $trip_inventory['inventory_name'],

				'description'	=> $trip_inventory['description'],
				'priority'		=> $trip_inventory['priority'],
				'max_places'	=> $trip_inventory['max_places'],
				'is_disabled'	=> $trip_inventory['is_disabled'],
			);
		}


		if (!is_array($trips_id) && $trips_tickets) $trips_tickets = array_pop($trips_tickets);

		return $trips_inventories;
	}

	// Возвращает список билетов трипов с их параметрами
	public function get_tickets($trips_id, $ticket_id = null, $ignore_fh = false) {

		if (!is_array($trips_id)) $search_trips_id = array($trips_id);
		else $search_trips_id = $trips_id;

		$where = array();
		$values = array();

		$where[] = "trip_ticket.trip_id IN (".$this->db->in($search_trips_id).")";

		if ($ticket_id) {

			$where[] = "trip_ticket.id = ?";
			$values[] = $ticket_id;
		}

		if (!is_api_request() || is_mode('front_get_day_trip', 'get_month_availability')) {

			$where[] = "trip_ticket.rs_internal = ?";
			$values[] = 0;
		}


		if (!$ignore_fh) {

			$where[] = "
			(
				(
					products.fareharbor_id IS NULL
						AND
					trip_ticket.fareharbor_id = 0
				)
					OR
				(
					products.fareharbor_id IS NOT NULL
						AND
					trip_ticket.fareharbor_id != 0
				)
			)
			";
		}
		else {

			$where[] = "trip_ticket.fareharbor_id = 0";
		}


		$trips_tickets = array();

		$this->db->query("
			SELECT
				trip_ticket.*,

				users.comission AS comission,

				products.id AS trip_id,
				products.cut_off AS cut_off
			FROM
				trip_ticket
				JOIN products ON products.id = trip_ticket.trip_id
				JOIN users ON users.id = products.partner_id
			WHERE
				".implode("\r\n AND ", $where)."
			ORDER BY
				trip_ticket.view_order ASC,
				trip_ticket.id ASC",
			$values
		);

		while ($trip_ticket = $this->db->fetch()) {

			$trip_ticket['avail'] = $trip_ticket['total'] - $trip_ticket['sold'];
			if ($trip_ticket['avail'] < 0) $trip_ticket['avail'] = 0;

			$trip_ticket['avail_ts'] = min($trip_ticket['avail'], ($trip_ticket['total_ts'] - $trip_ticket['sold_ts']));
			if ($trip_ticket['avail_ts'] < 0) $trip_ticket['avail_ts'] = 0;

			$trips_tickets[$trip_ticket['trip_id']][$trip_ticket['id']] = $trip_ticket;
		}

		$trips_without_tickets = array_diff($search_trips_id, array_keys($trips_tickets));

		foreach ($trips_without_tickets as $trip_id) $trips_tickets[$trip_id] = array();

		if (!is_array($trips_id) && $trips_tickets) $trips_tickets = array_pop($trips_tickets);

		return ($ticket_id ? array_pop($trips_tickets) : $trips_tickets);
	}

	// Получаем параметр strike_out для конкретного билета-дня-расписания
	public function get_ticket_strike_out($trip_id, $ticket_id, $date, $schedule_id) {

		$strike_out = null;

		$this->db->query("
			SELECT
				calendar_ticket.strike_out
			FROM
				calendar_ticket
				JOIN calendar ON calendar.id = calendar_ticket.calendar_id
			WHERE
				calendar.date = ?
					AND
				calendar.product_id = ?
					AND
				calendar_ticket.ticket_id = ?
					AND
				calendar_ticket.schedule_id = ?",
			format_date_for_db($date),
			$trip_id,
			$ticket_id,
			$schedule_id
		);
		$db_res = $this->db->result;

		if ($ticket = $this->db->fetch($db_res)) {

			$strike_out = $ticket['strike_out'];
		}

		if (is_null($strike_out)) {

			$default_data = $this->get_tickets($trip_id, $ticket_id);

			$strike_out = $default_data['strike_out'];
		}

		return $strike_out;
	}

	// Все возможные категории трипов
	public function get_categories() {

		if (($categories = $this->cache->load_from_file(array('trip_get_categories'))) !== null) return $categories;


		$categories = array();

		$this->db->query("
			SELECT
				categories.*
			FROM
				categories
			ORDER BY
				categories.name"
		);

		while ($category = $this->db->fetch()) $categories[$category['id']] = $category;


		$this->cache->save_to_file(array('trip_get_categories'), $categories);

		return $categories;
	}


	// Возвращает данные о трипе
	// $with_ticket_types - список трипов и доп. опций
	// $with_categories - список категорий
	public function get($trip_id, $with_ticket_types = false, $with_categories = false, $partner_id = null, $ignore_fh = false) {

		if (!$trip_id) return null;

		$trip = $this->get_list(array (
			'id'					=> $trip_id,
			'partner_id'			=> $partner_id,
			'with_ticket_types'		=> $with_ticket_types,
			'with_categories'		=> $with_categories,
			'ignore_fh'				=> $ignore_fh,
		));

		if ($trip) {

			$trip = array_pop($trip);

			$trip['pseudo_name'] = $this->product->get_product_pseudo_name($trip_id);

			// При запросе из апи получаем дополнительно video_url
			if ($trip && is_api_request()) {

				// Видео трипа
				$this->add_page('video');
				$video = $this->page_video->get_list(array (
					'rs_product_id'		=> $trip_id,
					'no_links'			=> true,
				));

				$trip['video_url'] = '';
				if ($video && ($video = array_pop($video))) $trip['video_url'] = $video['code'];
			}
		}

		return ($trip ? $trip : null);
	}

	// Список трипов по параметрам поиска
	public function get_list($search_params = array()) {

		// Добавлеям в параметры id портала, чтобы отличался заголовок для функционального кеша
		// Это нужно т.к. в порталах теперь может отличаться список доступных городов
		if (is_portal()) {

			$portal_data = $this->config('portal');

			$search_params['portal_user_id'] = $portal_data['user_id'];
		}

		if (($trips = $this->cache->load(array('trip_get_list', $search_params))) !== null) return $trips;

		$trips = array();

		$select = array();
		$join = array();
		$where = array();
		$values = array();

		$where[] = "products.product_type_id = ?";
		$values[] = product_type('trip');


		// В общем случае исключаем трипы, предназначенинные только для портала
		if (!is_portal() && is_null($this->config('in_admin_zone'))) $where[] = "products.portal_only = 0";


		if (empty($search_params['no_cities'])) {

			// Названия основных активити трипов
			for ($table_index = 1; $table_index <= 3; $table_index++) {

				$index = '';
				if ($table_index != 1) $index = $table_index;

				$activity_column = 'activity'.$index.'_id';

				$activity_table = 'activities'.$index;

				$join[] = 'LEFT JOIN activities AS '.$activity_table.' ON '.$activity_table.'.id =  products.'.$activity_column;

				$select[] = $activity_table.'.name AS activity'.$index.'_name';
			}

			// Названия городов/штатов трипа
			for ($table_index = 1; $table_index <= 6; $table_index++) {

				$index = '';
				if ($table_index != 1) $index = $table_index;

				$city_column = 'city_id'.$index;

				$city_table = 'cities'.$index;
				$state_table = 'states'.$index;
				$country_table = 'countries'.$index;

				$join[] = 'LEFT JOIN cities AS '.$city_table.' ON '.$city_table.'.id =  products.'.$city_column;
				$join[] = 'LEFT JOIN states AS '.$state_table.' ON '.$state_table.'.id = '.$city_table.'.state_id';
				$join[] = 'LEFT JOIN countries AS '.$country_table.' ON '.$country_table.'.id = '.$city_table.'.country_id';

				$select[] = $city_table.'.name AS city_name'.$index;
				$select[] = $state_table.'.id AS state_id'.$index;
				$select[] = $state_table.'.name AS state_name'.$index;
				$select[] = $state_table.'.code AS state_code'.$index;
				$select[] = $country_table.'.id AS country_id'.$index;
				$select[] = $country_table.'.name AS country_name'.$index;
			}
		}

		// Конкретный id трипа
		if (!empty($search_params['id'])) {

			$where[] = "products.id = ?";
			$values[] = $search_params['id'];
		}
		// Несколько конкретных трипов
		if (!empty($search_params['ids'])) {

			$where[] = "products.id IN (".$this->db->in($search_params['ids']).")";
		}

		if (isset($search_params['product_type_id'])) {

			$where[] = "products.product_type_id = ?";
			$values[] = $search_params['product_type_id'];
		}

		if (!empty($search_params['partner_id'])) {

			$where[] = "products.partner_id = ?";
			$values[] = $search_params['partner_id'];
		}

		$select[] = "products.rs_booking_fee";


		$this->db->query("
			SELECT
				products.*,

				users.first_name,
				users.last_name,
				users.title,
				users.comission AS partner_comission,
				users.phone AS partner_phone,
				users.phone_client AS partner_phone_client,
				users.rs_only AS partner_rs_only

				".($select ? ', '.implode(",\r\n", $select) : "")."
			FROM
				products
				LEFT JOIN users ON users.id = products.partner_id
				".implode("\r\n", $join)."
			WHERE
				".implode("\r\n AND ", $where),
			$values
		);
		$db_res = $this->db->result;

		while ($trip = $this->db->fetch($db_res)) {

			if (!empty($search_params['with_categories'])) {

				$trip['categories'] = array();

				$this->db->query("
					SELECT
						*
					FROM
						products_categories
					WHERE
						product_id = ?",
					$trip['id']
				);

				while ($category = $this->db->fetch()) $trip['categories'][] = $category['category_id'];
			}

			if ($trip['weekly']) {

				$weekly = explode(',', $trip['weekly']);
				$trip['weekly'] = array();

				foreach ($weekly as $week_day) $trip['weekly'][$week_day] = $week_day;
			}
			else $trip['weekly'] = array();

			$trip['special_status'] = $this->product->get_actual_special_status($trip['id']);

			// У RS отдельные спец. статусы
			if (is_api_request()) {

				if ($rs_special_status = $this->product->get_special_statuses($trip['id'], true)) {

					// Пока что RS статус может быть только один и без дат
					$rs_special_status = array_shift($rs_special_status);

					$trip['rs_special_status'] = $rs_special_status['text'];
				}
				else {

					$trip['rs_special_status'] = '';
				}
			}

			if ($trip['partner_rs_only']) $trip['is_disabled_ts'] = true;


			$trips[$trip['id']] = $trip;
		}

		if (!empty($search_params['with_ticket_types'])) {

			$trips_id = array_keys($trips);

			$ignore_fh = false;

			if (!empty($search_params['ignore_fh'])) {

				$ignore_fh = $search_params['ignore_fh'];
			}

			$trips_tickets = $this->get_tickets($trips_id, null, $ignore_fh);
			$trips_options = $this->get_options($trips_id, null, false, $ignore_fh);
			$trips_schedules = $this->get_trips_schedules($trips_id, null, true, $ignore_fh);
			$trips_inventories = $this->get_trips_inventories($trips_id, $ignore_fh);

			foreach ($trips as $trip_id => $trip) {

				$trip['tickets'] = array();

				foreach ($trips_tickets[$trip_id] as $ticket_id => $ticket) {

					$trip['tickets'][$ticket_id] = array(
						'id'				=> $ticket['id'],
						'name'				=> $ticket['name'],
						'rs_internal'		=> $ticket['rs_internal'],
						'is_disabled_ts'	=> $ticket['is_disabled_ts'],
						'total'				=> $ticket['total'],
						'total_ts'			=> $ticket['total_ts'],
						'avail'				=> $ticket['avail'],
						'avail_ts'			=> $ticket['avail_ts'],
						'sold'				=> $ticket['sold'],
						'sold_ts'			=> $ticket['sold_ts'],
						'price'				=> $ticket['price'],
						'price_rs'			=> $ticket['price_rs'],
						'strike_out'		=> $ticket['strike_out'],
						'comission'			=> $ticket['comission'],
						'wholesale'			=> $ticket['wholesale'],
						'min'				=> $ticket['min'],
						'duration'			=> $ticket['duration'],
						'is_default'		=> $ticket['is_default'],
						'description'		=> $ticket['description'],
					);
				}

				$trip['additional_options'] = $trips_options[$trip_id];

				$trip['schedules'] = array();
				if (!empty($trips_schedules[$trip_id])) $trip['schedules'] = $trips_schedules[$trip_id];


				$trip['inventories'] = array();
				if (!empty($trips_inventories[$trip_id])) $trip['inventories'] = $trips_inventories[$trip_id];

				$trip['special_statuses'] = $this->product->get_special_statuses($trip_id);


				$trips[$trip_id] = $trip;
			}
		}

		// Если это портал, то исключаем трипы, запрещённые в конфиге портала
		if (is_portal()) {

			foreach (explode(",", $portal_data['exclusion']['product']) as $product_to_exclude) {

				if (array_key_exists($product_to_exclude, $trips)) unset($trips[$product_to_exclude]);
			}
		}

		$this->cache->save(array('trip_get_list', $search_params), $trips);

		return $trips;
	}


	public function is_available_front($trip) {

		return (!$trip['disabled'] && !$trip['is_disabled_ts']);
	}


	private function check_date_range_for_valid_days($from_time, $to_time, $trip_weekly) {

		$valid_days_exists = false;

		$cur_day = strtotime('+1 day', $from_time);

		while ($cur_day < $to_time) {

			if (in_array((date('w', $cur_day) + 1), $trip_weekly)) {

				$valid_days_exists = true;
			}

			$cur_day = strtotime('+1 day', $cur_day);
		}

		return $valid_days_exists;
	}


	// Список дней, не доступных для заказа обычному пользователю, но доступных админу
	// (партнёру-админу в RS) относительно текущего момента
	public function cut_off_days($cut_off, $cut_off_time = null) {

		if (!$cut_off_time) $cut_off_time = $this->config('calendar_next_day_hour');

		$next_day_indent = 12 - $cut_off_time;

		$cur_day = date("Y-m-d");
		$end_day = date("Y-m-d", time() + ($cut_off - 1)*3600*24 + $next_day_indent*3600 + 1);

		$cut_off_days = dates_from_interval($cur_day, $end_day);

		return $cut_off_days;
	}

	// Параметры конкретного дня/трипа с ценами билетов/опций
	// - $calendar_id, берём данные по конкретному дню для трипов с календарём
	// - $trip_id, берём дефолтные данные трипа, для трипа без календаря
	// - $is_gift, флаг подарка для трипов с календарём, возьмёт дефолтные данные
	public function get_calendar_day_data($calendar_id = null, $trip_id = null, $is_gift = false, $ignore_cache = false) {

		if (
			!$ignore_cache
				&&
			($result = $this->cache->load(array('get_calendar_day_data', $calendar_id, $trip_id, $is_gift))) !== null
		) {

			return $result;
		}

		if (!($calendar_id || $trip_id)) return null;

		$calendar_day = array();

		$this->db->query("
			SELECT
				calendar.id AS calendar_id,
				calendar.date,
				calendar_product.close_out,
				calendar_product.front_booking_disabled,

				products.id AS product_id,
				products.fareharbor_id AS fareharbor_id,
				products.disabled AS product_disabled,
				products.name AS product_name,
				products.use_calendar,
				products.use_schedule,
				products.weekly AS product_weekly,
				products.monthly AS product_monthly,
				products.yearly AS product_yearly,
				products.city_id AS product_city_id,
				products.cut_off AS product_cut_off,
				products.cut_off_time AS product_cut_off_time,
				products.close_out AS default_close_out
			FROM
				products
				LEFT JOIN calendar ON calendar.product_id = products.id
				LEFT JOIN calendar_product ON calendar_product.calendar_id = calendar.id
				JOIN users ON users.id = products.partner_id
			WHERE
				products.product_type_id = ?
				".($calendar_id ? " AND calendar.id = ".intval($calendar_id) : "")."
				".($trip_id ? " AND products.id = ".intval($trip_id) : ""),
			product_type('trip')
		);

		if ($calendar_day = $this->db->fetch()) {

			$is_fh_trip = (!is_null($calendar_day['fareharbor_id']) ? true : false);

			// На данном этапе во время редактирования заказа сюда код доходит только в том случае
			// если нужно провести валидацию по внутренним данным (даже если FH-трип, то это старая часть)
			if (is_page('order') && is_mode('edit')) $is_fh_trip = false;


			if (!$is_fh_trip) {

				// Это для FH-трипов пока что берётся из актуальной доступности (ниже)
				$trip_schedules = $this->get_trips_schedules($calendar_day['product_id'], null, true, true);
				if (!$trip_schedules || !$calendar_day['use_schedule']) $trip_schedules = array(array('id' => 0));

				$use_calendar = ($calendar_day['use_calendar'] && !$is_gift);
			}
			else {

				$use_calendar = 1;
			}

			$default_tickets = $this->get_tickets($calendar_day['product_id'], null, !$is_fh_trip);

			$schedules_tickets = array();
			
			$is_avail = true; // флаг доступности трипа для RS
			
			if ($use_calendar) {

				if ($is_fh_trip) {

					$day_data = $this->get_fh_availability (
						$calendar_day['fareharbor_id'],
						$calendar_day['date'],
						$calendar_day['date'],
						$default_tickets
					);

					if (isset($day_data['days_info']) && isset($day_data['days_info'][$calendar_day['date']])) {

						$trip_schedules = array();
						for ($i = 0; $i < count($day_data['days_info'][$calendar_day['date']]['schedules']); $i++) {

							$trip_schedules[$i] = array('id' => $i);
						}
					}
					else {

						$trip_schedules = array(array('id' => 0));
					}
				}
				else {

					$day_data = $this->get_trips_tickets_rates (
						$calendar_day['product_id'],
						$calendar_day['date'],
						$calendar_day['date']
					);
				}

				// далее устанавливаем флаг доступности для RS
				if (!empty($day_data['not_avail']) && ($day_data['not_avail'] == true)) $is_avail=false;
				
				if ($calendar_day['product_weekly'] != '') {
					
					$weekly = explode(',',$calendar_day['product_weekly']);
					
					$week_day = date("w", strtotime($calendar_day['date'])) + 1;
					if (!in_array($week_day, $weekly)) $is_avail=false;
				}

				if ($calendar_day['product_monthly'] != '') {
							
					$monthly = explode(',',$calendar_day['product_monthly']);
							
					$month_day = date("j", strtotime($calendar_day['date']));
					if (!in_array($month_day, $monthly)) $is_avail=false;
				}

				if ($calendar_day['product_yearly'] != '') {
							
					$yearly = explode(',',$calendar_day['product_yearly']);
							
					$year_day = date("n-j", strtotime($calendar_day['date']));
					if (!in_array($year_day, $yearly)) $is_avail=false;
				}

				// Защита от ошибок на случай, если у трипа включён календарь, но нет ни одного билета
				if (isset($day_data['days_info']) && isset($day_data['days_info'][$calendar_day['date']])) {

					$day_data = $day_data['days_info'][$calendar_day['date']];
				}

				foreach ($trip_schedules as $schedule) {

					if (empty($day_data['schedules'][$schedule['id']])) continue;

					$schedule_day_info = $day_data['schedules'][$schedule['id']];

					$schedules_tickets[$schedule['id']] = array (
						'id'						=> $schedule_day_info['id'],
						'fh_id'						=> ($is_fh_trip ? $schedule_day_info['fh_id'] : 0),
						'fh_tickets_config'			=> ($is_fh_trip ? $schedule_day_info['fh_tickets_config'] : 0),
						'time'						=> $schedule_day_info['time'],
						'is_disabled'				=> $schedule_day_info['is_disabled'],

						'min_tickets'				=> $schedule_day_info['min_tickets'],
						'max_tickets'				=> $schedule_day_info['max_tickets'],
						'tickets_avail'				=> $schedule_day_info['tickets_avail'],
						'tickets_avail_for_update'	=> $schedule_day_info['tickets_avail_for_update'],
						'sold'						=> $schedule_day_info['sold'],
						'min_price'					=> (isset($schedule_day_info['min_price']) ? $schedule_day_info['min_price'] : null),

						'not_avail'					=> $schedule_day_info['not_avail'],
						'not_avail_ts'				=> $schedule_day_info['not_avail_ts'],
						'not_avail_by_close_out'	=> $schedule_day_info['not_avail_by_close_out'],
						'has_avail_tickets'			=> $schedule_day_info['has_avail_tickets'],
						'stop_sell'					=> $schedule_day_info['stop_sell'],
						'avail_for_admin'			=> $schedule_day_info['avail_for_admin'],

						'inventories'				=> $schedule_day_info['inventories'],
						'tickets'					=> array(),
					);

					foreach ($default_tickets as $ticket_id => $default_ticket) {

						$ticket = $default_ticket;
						if (!empty($schedule_day_info['tickets'][$ticket_id])) {

							$ticket = $schedule_day_info['tickets'][$ticket_id];
						}
						// Сам получатель данных всегда пометит билет как доступный, но
						// если этого билета в актуальной доступности этого дня нет, то
						// помечаем его недоступным уже здесь
						else if ($is_fh_trip) {

							$ticket['not_avail'] = true;
							$ticket['not_avail_ts'] = true;
							$ticket['not_avail_update'] = true;
							$ticket['not_avail_update_ts'] = true;
							$ticket['avail_for_admin'] = false;
						}

						$schedules_tickets[$schedule['id']]['tickets'][$ticket_id] = array (
							'id'					=> $ticket_id,
							'name'					=> $default_ticket['name'],
							'rs_internal'			=> $default_ticket['rs_internal'],
							'description'			=> $default_ticket['description'],
							'is_default'			=> $default_ticket['is_default'],

							'min'					=> $ticket['min'],
							'cut_off'				=> $ticket['cut_off'],
							'comission'				=> (!is_api_request() ? $ticket['comission'] : 0),
							'wholesale'				=> (!is_api_request() ? $ticket['wholesale'] : 0),

							'duration'				=> $default_ticket['duration'],

							'price'					=> $ticket['price'],
							'price_rs'				=> $ticket['price_rs'],
							'strike_out'			=> $ticket['strike_out'],
							'total'					=> $ticket['total'],
							'total_ts'				=> $ticket['total_ts'],
							'avail'					=> (isset($ticket['avail']) ? $ticket['avail'] : $ticket['total']),
							'avail_ts'				=> (isset($ticket['avail_ts']) ? $ticket['avail_ts'] : $ticket['avail_ts']),
							'sold'					=> (isset($ticket['sold']) ? $ticket['sold'] : 0),
							'sold_ts'				=> (isset($ticket['sold_ts']) ? $ticket['sold_ts'] : 0),

							'not_avail'				=> (isset($ticket['not_avail']) ? $ticket['not_avail'] : false),
							'not_avail_ts'			=> (isset($ticket['not_avail_ts']) ? $ticket['not_avail_ts'] : false),
							'not_avail_update'		=> (isset($ticket['not_avail_update']) ? $ticket['not_avail_update'] : false),
							'not_avail_update_ts'	=> (isset($ticket['not_avail_update_ts']) ? $ticket['not_avail_update_ts'] : false),
							'is_disabled'			=> (isset($ticket['is_disabled']) ? $ticket['is_disabled'] : false),
							'disable_promos'		=> (isset($ticket['disable_promos']) ? $ticket['disable_promos'] : false),
							'avail_for_admin'		=> (isset($ticket['avail_for_admin']) ? $ticket['avail_for_admin'] : false),
						);
					}
				}
			}
			else {

				$tickets = array();

				$has_avail_tickets = false;
				$not_avail_tickets = 0;
				$not_avail_ts_tickets = 0;
				$not_avail_for_admin_tickets = 0;

				$min_price = null;

				foreach ($default_tickets as $ticket) {

					$ticket['is_disabled'] = 0;
					$ticket['not_avail'] = false;
					$ticket['not_avail_update'] = false;
					$ticket['not_avail_ts'] = false;
					$ticket['not_avail_update_ts'] = false;
					$ticket['avail_for_admin'] = true;


					if ($ticket['total_ts'] <= 0) {

						$ticket['avail_for_admin'] = false;

						$not_avail_for_admin_tickets++;
					}

					if ($ticket['total_ts'] > $ticket['total']) {

						$ticket['total_ts'] = $ticket['total'];
					}


					if ($calendar_day['use_calendar']) {

						$ticket['avail'] = $ticket['total'];
						$ticket['avail_ts'] = $ticket['total_ts'];
					}

					if (!$ticket['avail']) {

						$ticket['not_avail'] = true;
						$ticket['not_avail_update'] = true;
						$not_avail_tickets++;
						
					}
					else if(is_api_request()) {

						$has_avail_tickets = true;
					}

					if (!$ticket['avail_ts'] || $ticket['is_disabled_ts']) {

						$ticket['not_avail_ts'] = true;
						$ticket['not_avail_update_ts'] = true;
						$not_avail_ts_tickets++;
						
					}
					else if(!is_api_request()) {

						$has_avail_tickets = true;
					}


					if (is_api_request()) {

						$ticket['comission'] = 0;
						$ticket['wholesale'] = 0;
					}

					if (
						!$ticket['not_avail_ts']
							&&
						(
							is_null($min_price)
								||
							(
								!is_null($min_price)
									&&
								$ticket['price'] < $min_price
							)
						)
					) {

						$min_price = $ticket['price'];
					}

					$tickets[$ticket['id']] = $ticket;
				}
				
				if(!$has_avail_tickets) $is_avail=false; // если билеты закончились, то трип тоже не доступен

				$schedules_tickets = array (
					0 => array (
						'id'				=> 0,
						'time'				=> '',
						'is_disabled'		=> 0,

						'min_tickets'		=> 0,
						'max_tickets'		=> null,
						'tickets_avail'		=> 20,
						'sold'				=> 0,

						'min_price'			=> $min_price,

						'not_avail'			=> ($not_avail_tickets == count($tickets)),
						'not_avail_ts'		=> ($not_avail_ts_tickets == count($tickets)),
						'has_avail_tickets'	=> $has_avail_tickets,
						'stop_sell'			=> false,
						'avail_for_admin'	=> ($not_avail_for_admin_tickets != count($tickets)),

						'tickets'			=> $tickets,
					),
				);
			}

			if (!$is_fh_trip) {

				$additional_options = $this->get_day_current_options($calendar_day['product_id'], $calendar_day['date']);
			}
			else {

				$additional_options = (isset($day_data['fh_additional_options']) ? $day_data['fh_additional_options'] : array());
			}

			$cut_off_days = $this->cut_off_days($calendar_day['product_cut_off'], $calendar_day['product_cut_off_time']);

			$calendar_day = array(
				'id'						=> ($use_calendar ? $calendar_day['calendar_id'] : null),
				'product_id'				=> $calendar_day['product_id'],
				'product_disabled'			=> $calendar_day['product_disabled'],
				'product_name'				=> $calendar_day['product_name'],
				'product_city_id'			=> $calendar_day['product_city_id'],
				'date'						=> ($use_calendar ? $calendar_day['date'] : null),
				'date_formatted'			=> ($use_calendar ? format_time($calendar_day['date'], false) : null),
				'is_avail'					=> $is_avail,
				'is_cut_off'				=> ($use_calendar ? in_array($calendar_day['date'], $cut_off_days) : false),
				'close_out'					=> (isset($calendar_day['close_out']) ? $calendar_day['close_out'] : $calendar_day['default_close_out']),
				'front_booking_disabled'	=> (isset($calendar_day['front_booking_disabled']) ? $calendar_day['front_booking_disabled'] : null),
				'min_price'					=> ($use_calendar ? (isset($day_data['min_price']) ? $day_data['min_price'] : null) : $min_price),
				'default_tickets'			=> $default_tickets,
				'schedules'					=> $schedules_tickets,
				'additional_options'		=> $additional_options,
				'fh_options_config'			=> (isset($day_data['fh_options_config']) ? $day_data['fh_options_config'] : array()),
			);
		}

		if (!$ignore_cache) {

			$this->cache->save(array('get_calendar_day_data', $calendar_id, $trip_id, $is_gift), $calendar_day);
		}

		return $calendar_day;
	}


	/**
	 * Format raw values to final cart item data
	 *
	 * @param array data Cart item data params
	 * Required
	 * - [calendar_id]			int
	 * - [trip_id]				int
	 * - [fh_id]				int
	 * - [schedule_id]			int
	 * - [inventory_id]			int
	 * - [tickets]				array
	 * Optional
	 * - [is_gift]				bool
	 * - [additional_options]	array
	 * - [comment_guest]		string
	 * - [comment_partner]		string
	 *
	 * @return array Formated cart item data
	 */
	public function format_cart_item_data($data) {

		if (empty($data['is_gift'])) $data['is_gift'] = false;
		$is_fh_item = (!empty($data['fh_id']) ? true : false);


		$calendar_day = $this->get_calendar_day_data($data['calendar_id'], $data['trip_id'], $data['is_gift'], $is_fh_item);

		$trip = $this->get($calendar_day['product_id']);


		if (isset($data['actual_is_gift'])) {

			// При добавлении в корзину новой части параметр уже расчитан в реквесте - передаём его с осоновным массивом
			$is_gift = $data['actual_is_gift'];
		}
		else {

			$can_be_gift = !($this->is_price_changes_for_year($trip['id']));

			$is_gift = false;
			if ($data['is_gift'] && $can_be_gift) $is_gift = true;
		}


		// Расписание
		if (!$is_fh_item) {

			if (
				!$trip['use_calendar']
					||
				!$trip['use_schedule']
					||
				($trip['use_calendar'] && $trip['use_schedule'] && $is_gift)
			) {

				$data['schedule_id'] = 0;
			}

			$schedule = $calendar_day['schedules'][$data['schedule_id']];
		}
		else {

			$schedule = array();
			$data['schedule_id'] = 0;

			foreach ($calendar_day['schedules'] as $fh_schedule) {

				if ($fh_schedule['fh_id'] == $data['fh_id']) {

					$schedule = $fh_schedule;
					break;
				}
			}
		}


		// Билеты
		$tickets = array();

		$day_tickets = $schedule['tickets'];

		$ticket_price_field = 'price';
		if (is_api_request()) $ticket_price_field = 'price_rs';

		$total_booked = 0;
		// Длительность путешествия: определяется по билетам, но пишется в лодку (ниже)
		$trip_duration = null;

		foreach ($data['tickets'] as $ticket_id => $booked) {

			if (!$booked) continue;


			$ticket = $day_tickets[$ticket_id];

			$ticket_guests = array();
			if ($trip['need_tickets_guests']) $ticket_guests = $data['guests'][$ticket_id];

			$booked = intval($booked);
			// Стоимость билета
			$ticket_price = $ticket[$ticket_price_field];

			$total_booked += $booked;

			if (is_null($trip_duration) && !empty($ticket['duration'])) $trip_duration = $ticket['duration'];

			$tickets[$ticket_id] = array(
				'id'				=> $ticket['id'],
				'name'				=> $ticket['name'],
				'rs_internal'		=> ($ticket['rs_internal'] ? true : false),
				'description'		=> $ticket['description'],
				'duration'			=> (!empty($ticket['duration']) ? $ticket['duration'] : null),

				'comission'			=> (!is_api_request() ? $ticket['comission'] : 0),
				'wholesale'			=> (!is_api_request() ? $ticket['wholesale'] : 0),
				'price'				=> $ticket_price,

				'booked'			=> $booked,

				'barcodes'			=> array(),
				'guests'			=> $ticket_guests,
			);
		}


		// Inventory
		$choosed_inventory = array();

		if (
			!$is_fh_item
				&&
			$trip['use_calendar'] && $trip['use_schedule']
				&&
			!$is_gift
				&&
			$calendar_day['schedules'][$data['schedule_id']]['inventories']
			) {

			$inventories = $calendar_day['schedules'][$data['schedule_id']]['inventories'];

			if (empty($data['inventory_id'])) {

				// Делим билеты между лодками
				$separation_result = $this->separate_tickets_inventories($trip['id'], $tickets, $inventories);

				// Если лодка получилась одна, то обрабатываем её обычным способом
				if (count($separation_result['separation']) == 1) {

					$inventory_id = key($separation_result['separation']);

					// Если у лодки на это время уже есть длительность, то изменить её нельзя
					if (!is_null($inventories[$inventory_id]['duration'])) {

						$trip_duration = $inventories[$inventory_id]['duration'];
					}

					$choosed_inventory = array(
						'id'			=> $inventories[$inventory_id]['id'],
						'name'			=> $inventories[$inventory_id]['name'],
						'duration'		=> $trip_duration,
						'description'	=> $inventories[$inventory_id]['description'],
					);
				}
			}
			else {

				$inventory_id = $data['inventory_id'];

				if (!is_null($inventories[$inventory_id]['duration'])) {

					$trip_duration = $inventories[$inventory_id]['duration'];
				}

				$choosed_inventory = array(
					'id'			=> $inventories[$inventory_id]['id'],
					'name'			=> $inventories[$inventory_id]['name'],
					'duration'		=> $trip_duration,
					'description'	=> $inventories[$inventory_id]['description'],
				);
			}
		}


		// Выбранное расписание
		if (!$is_fh_item) {

			if ($trip['use_calendar'] && $trip['use_schedule'] && !$data['is_gift']) {

				$schedule = array(
					'id' => $schedule['id'],
					'time' => $schedule['time'],
				);
			}
			else $schedule = array();
		}
		else {

			$schedule = array(
				'id'	=> $data['fh_id'],
				'time'	=> $schedule['time'],
			);
		}


		// Опции
		$additional_options = array();

		if (!empty($data['additional_options'])) {

			$key_name = ($is_fh_item ? 'fh_id' : 'id');

			foreach ($data['additional_options'] as $option_id => $value_id) {

				if (!$value_id) continue;


				$option = $calendar_day['additional_options'][$option_id];

				if ($option['type'] == 'select') {

					$value = $option['values'][$value_id];


					$choosed_value = array(
						'id'		=> $value[$key_name],
						'name'		=> $value['name'],
						'price'		=> $value['price'],
						'comission'	=> (!is_api_request() ? $value['comission'] : 0),
						'apply_tax'	=> $value['apply_tax'],
					);
				}
				else {

					$choosed_value = array(
						'id'		=> null,
						'name'		=> $value_id,
						'price'		=> null,
						'comission'	=> 0,
						'apply_tax'	=> null,
					);
				}


				$additional_options[$option[$key_name]] = array(
					'id'		=> $option[$key_name],
					'name'		=> $option['name'],
					'type'		=> $option['type'],
					'value'		=> $choosed_value,
				);
			}
		}


		$comment_guest = '';
		if (!empty($data['comment_guest'])) $comment_guest = cut($data['comment_guest'], 2024, '');

		$comment_partner = '';
		if (is_api_request() && !empty($data['comment_partner'])) $comment_partner = $data['comment_partner'];


		if (is_api_request()) {

			if (is_front_api_request()) {

				if (!is_null($trip['rs_booking_fee'])) {

					$booking_fee = $trip['rs_booking_fee'];
				}
				else {

					$this->add_page('partner');

					$partner = $this->page_partner->get($trip['partner_id']);

					$booking_fee = $partner['rs_booking_fee'];
				}
			}
			else if (isset($data['booking_fee'])) {

				$booking_fee = intval($data['booking_fee']);
			}
			else {

				$booking_fee = 0;
			}
		}
		else if (!is_null($trip['booking_fee'])) {

			$booking_fee = $trip['booking_fee'];
		}
		else {

			$booking_fee = $this->config('booking_fee');
		}

		$book_info = array();

		// Если при пазделении получилось несколько лодок, то $book_info принимает первичные числовые индексы
		// В каждом из них - полная структура $book_info для создания отдельной cart_item
		if (isset($separation_result) && (count($separation_result['separation']) > 1)) {

			$is_first = true;

			foreach ($separation_result['separation'] as $inventory_id => $separated_tickets) {

				$current_tickets = array();

				// Для каждой лодки показатель длительности будет свой
				$trip_duration = null;

				foreach ($separated_tickets as $ticket_id => $booked) {

					$current_tickets[$ticket_id] = $tickets[$ticket_id];

					if (is_null($trip_duration) && !empty($current_tickets[$ticket_id]['duration'])) {

						$trip_duration = $current_tickets[$ticket_id]['duration'];
					}

					// Если не все билеты данного типа вошли в данную лодку
					if ($current_tickets[$ticket_id]['booked'] != $booked) {

						$current_tickets[$ticket_id]['booked'] = $booked;

						// Ставим в данный массив нужное количество имён гостей
						if ($trip['need_tickets_guests']) {

							$current_tickets[$ticket_id]['guests'] = array();

							$guest_number = 1;
							while ($booked) {

								$current_guest = array_shift($tickets[$ticket_id]['guests']);

								$current_tickets[$ticket_id]['guests'][$guest_number] = $current_guest;

								$guest_number += 1;
								$booked -= 1;
							}
						}
					}
				}

				if (!is_null($inventories[$inventory_id]['duration'])) {

					$trip_duration = $inventories[$inventory_id]['duration'];
				}

				$book_info[] = array(
					'product'				=> array(
						'id'					=> $trip['id'],
						'product_type'			=> 'trip',
						'name'					=> $trip['name'],
						'tax'					=> $trip['tax'],
						'booking_fee'			=> $booking_fee,
						'need_tickets_guests'	=> $trip['need_tickets_guests'],
					),
					'inventory'				=> array(
						'id'			=> $inventories[$inventory_id]['id'],
						'name'			=> $inventories[$inventory_id]['name'],
						'duration'		=> $trip_duration,
						'description'	=> $inventories[$inventory_id]['description'],
					),
					'schedule'				=> $schedule,
					'tickets'				=> $current_tickets,
					'additional_options'	=> ($is_first ? $additional_options : array()),
					'comments'				=> array(
						'guest'					=> ($is_first ? $comment_guest : ''),
						'partner'				=> ($is_first ? $comment_partner : ''),
					),
				);

				$is_first = false;
			}
		}
		else {

			$book_info[0] = array (
				'product'				=> array (
					'id'					=> $trip['id'],
					'product_type'			=> 'trip',
					'name'					=> $trip['name'],
					'tax'					=> $trip['tax'],
					'booking_fee'			=> $booking_fee,
					'need_tickets_guests'	=> $trip['need_tickets_guests'],
				),
				'fh_data'				=> (!$is_fh_item ? array() : array (
					// Данные трипа
					'fh_trip_data'			=> $data['fh_trip_data'],
					// Данные расписания FH (там это позиционируется как "доступность")
					'fh_schedule'			=> $schedule,
					// Соотвтествие конкретных билетов FH созданным при синхронизации TS-билетам
					'fh_tickets_config'		=> $data['fh_tickets_config'],
					// Внешняя типизация дополнительных опций FH
					'fh_options_config'		=> $data['fh_options_config'],
				)),
				'inventory'				=> $choosed_inventory,
				'schedule'				=> (!$is_fh_item ? $schedule : array()),
				'tickets'				=> $tickets,
				'additional_options'	=> $additional_options,
				'comments'				=> array (
					'guest'					=> $comment_guest,
					'partner'				=> $comment_partner,
				),
			);
		}

		return $book_info;
	}


	// Локальная корректировка данных доступности белетов/лодок перед валидацией при редактировании заказов
	private function revalidate_in_order_availability(&$data, &$calendar_day) {

		// Есть ли необходимость пересчитать перед валидацией доступность выбранной лодки
		if (!empty($data['inventory_id'])) {

			$need_to_revalidate_inventory = false;

			// В части заказа меняется расписание, а лодка остаётся та же: если лодка на новом расписании
			// блокируется одной только уходящей частью, то такой перенос валидатор должен разрешать
			if (
				!empty($data['is_schedule_changed'])
					&&
				empty($data['is_inventory_changed'])
					&&
				isset($data['old_cart_item']['schedule']['id'])
					&&
				// это всё также имеет смысл только если изменение происходит в рамках тех же для и трипа
				empty($data['is_trip_changed'])
					&&
				empty($data['is_day_changed'])
					&&
				// А также если лодка в трипе всё ещё существует
				isset($calendar_day['schedules'][$data['schedule_id']]['inventories'][$data['inventory_id']])
			) {

				$new_inventory = $calendar_day['schedules'][$data['schedule_id']]['inventories'][$data['inventory_id']];

				$old_schedule_id = $data['old_cart_item']['schedule']['id'];
				if (isset($calendar_day['schedules'][$old_schedule_id])) {

					$old_inventory = $calendar_day['schedules'][$old_schedule_id]['inventories'][$data['inventory_id']];
				}

				// Являются ли все заказанные на лодку билеты билетами уходящей части
				$total_booked = 0;
				foreach ($data['old_cart_item']['tickets'] as $ticket_id => $ticket_data) {

					$total_booked += $ticket_data['booked'];
				}

				if (isset($old_inventory) && ($old_inventory['sold'] == $total_booked)) {

					// Заблокирована ли лодка в новом расписании вообще, и если да, то со стороны ли освобождаемого расписания
					$old_minutes = convert_hour_to_minutes(convert_hour($calendar_day['schedules'][$old_schedule_id]['time']));
					$new_minutes = convert_hour_to_minutes(convert_hour($calendar_day['schedules'][$data['schedule_id']]['time']));
					$in_use_type = (($new_minutes > $old_minutes) ? 'start' : 'end');
					$not_avail_tickets = false;
					foreach ($new_inventory['tickets_avail_by_duration'] as $value) {

						if (!$value) {

							$not_avail_tickets = true;
							break;
						}
					}

					if (
						// Или лодка полностью заблокирована
						(
							!empty($new_inventory['in_use'])
							&&
							!strcmp($new_inventory['in_use_by'], $in_use_type)
						)
						||
						// Или не полностью, но перемещается влево и есть недоступные (в правую сторону) билеты
						(
							($in_use_type == 'end')
							&&
							$not_avail_tickets
						)
					) {

						// Освобождаем старую лодку
						$old_inventory['avail'] += $old_inventory['sold'];
						$old_inventory['sold'] = 0;
						$old_inventory['duration'] = null;
						// Прогонять данный массив не обязательно, но возможно пригодится на будущее
						foreach ($old_inventory['tickets_avail_by_duration'] as $ticket_id => $val) {

							$old_inventory['tickets_avail_by_duration'][$ticket_id] = true;
						}
						$calendar_day['schedules'][$old_schedule_id]['inventories'][$data['inventory_id']] = $old_inventory;

						// Заказываем повторную валидацию искомой лодки
						$need_to_revalidate_inventory = true;
					}
				}
			}

			// Локально корректируем данные доступности текущей лодки (в $calendar_day) в том случае
			// когда в части заказа есть лодка и меняется ТОЛЬКО набор билетов,
			// и меняется так, что полностью меняет длительность лодки
			if (
				empty($data['is_schedule_changed'])
				&&
				empty($data['is_inventory_changed'])
				&&
				empty($data['is_trip_changed'])
				&&
				empty($data['is_day_changed'])
			) {

				if (
					isset($calendar_day['schedules'][$data['schedule_id']])
					&&
					isset($calendar_day['schedules'][$data['schedule_id']]['inventories'][$data['inventory_id']])
				) {

					$inventory = $calendar_day['schedules'][$data['schedule_id']]['inventories'][$data['inventory_id']];
				}

				// Поменялся ли набор билетов так, что изменилась длительность
				$new_tickets_duration = null;
				foreach ($data['tickets_data'] as $ticket_id => $ticket_data) {

					if (
						isset($calendar_day['schedules'][$data['schedule_id']]['tickets'][$ticket_id])
						&&
						!empty($calendar_day['schedules'][$data['schedule_id']]['tickets'][$ticket_id]['duration'])
					) {

						$new_tickets_duration = $calendar_day['schedules'][$data['schedule_id']]['tickets'][$ticket_id]['duration'];
						break;
					}
				}

				if (
					!empty($new_tickets_duration)
					&&
					isset($inventory)
					&&
					!empty($inventory['duration'])
					&&
					($new_tickets_duration != $inventory['duration'])
				) {

					// Являются ли все заказанные на лодку билеты билетами, определяющими её длительность?
					$inventory_like = "'%\"inventory\":{\"id\":\"" . $inventory['id'] . "\"%'";
					$this->db->query("
					SELECT
						cart_items.id,
						cart_items.data
					FROM
						cart_items
					WHERE
						cart_items.calendar_id = ?
							AND
						cart_items.data LIKE " . $inventory_like,
						$data['calendar_id']
					);
					$db_res = $this->db->result;

					$duration_by_this_item_only = true;

					while ($inventory_cart_item = $this->db->fetch($db_res)) {

						$inventory_cart_item_data = $this->db->decode_data($inventory_cart_item['data']);

						// Если помимо изменяемой части на текущие день/лодку/расписание есть часть с длительностью
						// то продолжать мы не можем, т.к. текущая длительность лодки определена и той частью в т.ч.
						if (
							($inventory_cart_item_data['schedule']['id'] == $data['schedule_id'])
							&&
							($data['cart_item_id'] != $inventory_cart_item['id'])
							&&
							!is_null($inventory_cart_item_data['inventory']['duration'])
							&&
							$inventory_cart_item_data['tickets']
						) {

							$duration_by_this_item_only = false;
							break;
						}
					}

					if ($duration_by_this_item_only) {

						// Освобождаем лодку от старой длительности и старых билетов
						$inventory['avail'] += $inventory['sold'];
						$inventory['sold'] = 0;
						$inventory['duration'] = null;
						// Обнуляем также контрольный внешний параметр, сформированный по старой корзине
						$data['trip_duration'] = null;

						$calendar_day['schedules'][$data['schedule_id']]['inventories'][$data['inventory_id']] = $inventory;

						// Заказываем повторную валидацию искомой лодки
						$need_to_revalidate_inventory = true;
					}
				}
			}

			if ($need_to_revalidate_inventory) {

				$this->revalidate_inventory($calendar_day, $data['schedule_id'], $data['inventory_id']);
			}
		}

		// Пересматриваем количество доступних для расписания билетов и (если нужно) другие параметры
		// на основе изменённых данных внутри билетов этого расписания
		if (!empty($data['schedule_id'])) {

			// Пока что в освобождаемые включаем только те билеты, которые полностью исчезли из части заказа
			$tickest_to_restore = array();

			foreach ($data['old_cart_item']['tickets'] as $ticket_id => $ticket) {

				if (!isset($data['tickets_data'][$ticket_id])) {

					$tickest_to_restore[$ticket_id] = $ticket['booked'];
				}
			}

			// Второе условие может быть провалено если поменялся трип и, соответственно,
			// в $calendar_day пришли данные уже по новому трипу
			if ($tickest_to_restore && isset($calendar_day['schedules'][$data['schedule_id']])) {

				$this->db->query("
					SELECT
						orders.made_from_rs
					FROM
						orders
						JOIN cart_items ON cart_items.cart_id = orders.cart_id
					WHERE
						cart_items.id = ?",
					$data['cart_item_id']
				);
				$db_res = $this->db->result;
				$order = $this->db->fetch($db_res);

				$is_rs_order = ($order['made_from_rs'] ? true : false);
				$schedule = $calendar_day['schedules'][$data['schedule_id']];

				// Заметки по восстановлению билетов:
				// - не сравниваем полученные цифры с пороговыми значениями т.к. если билет уже был ранее заказан
				//   то и мето для него должно быть точно
				// - не устанавливаем пока что флаги т.к. они зависят не только от количества
				foreach ($tickest_to_restore as $ticket_id => $restored_count) {

					if (isset($schedule['tickets'][$ticket_id])) {

						$schedule['tickets'][$ticket_id]['sold'] -= $restored_count;
						if (!$is_rs_order) {

							$schedule['tickets'][$ticket_id]['sold_ts'] -= $restored_count;
						}

						$schedule['tickets'][$ticket_id]['avail'] += $restored_count;
						$schedule['tickets'][$ticket_id]['avail_ts'] += $restored_count;

						$schedule['sold'] -= $restored_count;
						// Обычный tickets_avail не трогаем т.к. он нужен только для новых заказов
						$schedule['tickets_avail_for_update'] += $restored_count;

						// Поскольку доступность расписания возросла, то нужно и доступность его билетов увеличить
						// на это же значение, но уже сравнив с максимумом для этого билета
						foreach ($schedule['tickets'] as $rest_ticket_id => $rest_ticket) {

							// Не добавляем билету, которому уже добавили
							if ($rest_ticket_id != $ticket_id) {

								$rest_ticket['avail'] += $restored_count;
								if ($rest_ticket['avail'] > $rest_ticket['total']) {

									$rest_ticket['avail'] = $rest_ticket['total'];
								}
								$rest_ticket['avail_ts'] += $restored_count;
								if ($rest_ticket['avail_ts'] > $rest_ticket['total_ts']) {

									$rest_ticket['avail_ts'] = $rest_ticket['total_ts'];
								}
							}

							// Но для всех билетов пересматриваем флаг недоступности для апдейта
							if (
								$rest_ticket['not_avail_update']
									&&
								(($rest_ticket['avail'] - $restored_count) == 0)
									&&
								!$rest_ticket['is_disabled']
									&&
								($rest_ticket['avail'] >= $rest_ticket['min'])
							) {

								$rest_ticket['not_avail_update'] = 0;
							}

							// И для TS
							if (
								$rest_ticket['not_avail_update_ts']
									&&
								(($rest_ticket['avail_ts'] - $restored_count) == 0)
									&&
								!$rest_ticket['is_disabled']
									&&
								($rest_ticket['avail_ts'] >= $rest_ticket['min'])
							) {

								$rest_ticket['not_avail_update_ts'] = 0;
							}

							$schedule['tickets'][$rest_ticket_id] = $rest_ticket;
						}
					}
				}

				$calendar_day['schedules'][$data['schedule_id']] = $schedule;
			}
		}
	}

	// Пересматриваем актуальность флагов доступности определённой лодки и билетов на неё на основе пришедших данных.
	// Проверка полная. Ревалидация достигается за счёт приоритета данных из $calendar_day над данными из БД
	// $calendar_day - новые данные (в которых, как правило, освобождена какая-то лодка)
	// $schedule_id, $inventory_id - расписание и сама лодка, которую заново проверяем
	private function revalidate_inventory(&$calendar_day, $schedule_id, $inventory_id) {

		$inventory = $calendar_day['schedules'][$schedule_id]['inventories'][$inventory_id];
		$inventory_start = convert_hour_to_minutes(convert_hour($calendar_day['schedules'][$schedule_id]['time']));

		// Данный массив необходимо переформировать
		$inventory['tickets_avail_by_duration'] = array();

		// Это пригодится ниже, а сформировать удобно здесь
		$tickets_durations = array();

		// Базовые данные в него идут по длительности билетов и искомой лодки
		foreach ($calendar_day['schedules'][$schedule_id]['tickets'] as $ticket_duration) {

			$tickets_durations[$ticket_duration['id']] = (!empty($ticket_duration['duration']) ? intval($ticket_duration['duration']) : 0);

			if (empty($inventory['duration']) || empty($ticket_duration['duration'])) {

				$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = true;
			}
			else if ($inventory['duration'] === $ticket_duration['duration']) {

				$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = true;
			}
			else {

				$inventory['tickets_avail_by_duration'][$ticket_duration['id']] = false;
			}
		}

		// Длительность лодки в текущем расписании (если её нет, то нужно будет перебирать все билеты)
		$inventory_duration = (!empty($inventory['duration']) ? $inventory['duration'] : 0);

		if ($inventory_duration) {

			$inventory_end = $inventory_start + $inventory_duration;
		}
		else {

			$inventory_end = array();

			foreach ($tickets_durations as $ticket_id => $ticket_duration) {

				$inventory_end[$ticket_id] = $inventory_start + $ticket_duration;
			}
		}

		// Для ревалидации выбираем из БД все заказы на искомую лодку на искомый день
		$this->db->query("
			SELECT
				schedule_id,
				sold,
				duration,

				trip_schedule.time
			FROM
				calendar_inventory
				JOIN calendar ON calendar.id = calendar_inventory.calendar_id
				JOIN trip_schedule ON trip_schedule.id = calendar_inventory.schedule_id
			WHERE
				calendar.date = ?
					AND
				inventory_id = ?
					AND
				sold IS NOT NULL",
			$calendar_day['date'],
			$inventory_id
		);
		$db_res = $this->db->result;

		$in_use = false;
		$in_use_by = '';

		while ($inventory_order = $this->db->fetch($db_res)) {

			$check_schedule = $inventory_order['schedule_id'];

			// Если это расписание есть в локальных данных (которые и корректируются для ревалидации) - берём его
			$from_db = (isset($calendar_day['schedules'][$check_schedule]) ? false : true);

			// Берём только расписания, на которые что-то заказано
			if (
				(
					$from_db
						&&
					!empty($inventory_order['sold'])
				)
					||
				(
					!$from_db
					&&
					!empty($calendar_day['schedules'][$check_schedule]['inventories'][$inventory_id]['sold'])
				)
			) {

				// Начало и конец проверяемого заказа на лодку
				if ($from_db) {

					$check_schedule_start = convert_hour_to_minutes($inventory_order['time']);
					$duration = $inventory_order['duration'];
					$check_schedule_end = $check_schedule_start + (!empty($duration) ? intval($duration) : 0);
				}
				else {

					$check_schedule_start = convert_hour_to_minutes(convert_hour (
						$calendar_day['schedules'][$check_schedule]['time']
					));
					$duration = $calendar_day['schedules'][$check_schedule]['inventories'][$inventory_id]['duration'];
					$check_schedule_end = $check_schedule_start + (!empty($duration) ? intval($duration) : 0);
				}


				// Текущее расписание после искомого
				if ($check_schedule_start > $inventory_start) {

					$end_minutes_check = false;

					if (is_array($inventory_end)) {

						foreach ($inventory_end as $ticket_id => $ticket_end_minutes) {

							if ($ticket_end_minutes < $check_schedule_start) {

								$end_minutes_check = true;
							}
							else {

								$inventory['tickets_avail_by_duration'][$ticket_id] = false;
							}
						}
					}
					else {

						$end_minutes_check = (($inventory_end < $check_schedule_start) ? true : false);
					}

					if (!$end_minutes_check) {

						$in_use = true;
						$in_use_by = 'end';
						break;
					}
				}
				// Текущее расписание до искомого
				else if ($check_schedule_start < $inventory_start) {

					if ($check_schedule_end >= $inventory_start) {

						$in_use = true;
						$in_use_by = 'start';
						break;
					}
				}
			}
		}


		if ($in_use) {

			$inventory['in_use'] = true;
			$inventory['in_use_by'] = $in_use_by;
			$inventory['is_avail'] = false;
		}
		else {

			$inventory['in_use'] = false;
			unset($inventory['in_use_by']);
			if (!$inventory['is_disabled']) $inventory['is_avail'] = true;
		}

		$calendar_day['schedules'][$schedule_id]['inventories'][$inventory_id] = $inventory;
	}

	/**
	 * Day booking validation
	 *
	 * @param array $data
	 * Required fields
	 * 	[calendar_id]			int		For calendar-use trips
	 * 	[trip_id]				int		For non-calendar trips or gift bookings
	 * 	[fh_id]					int		Fareharbor availability ID or 0 for the non FH trips
	 * 	[is_gift]				bool	Gift flag for calendar-use trips
	 * Optional fields
	 * 	[fh_tickets_config]		json	Connection between Fareharbor available tickets IDs and TS tickets IDs
	 * 	[fh_trip_data]			string	Fareharbor trip data for the requests
	 * 	[in_order]				bool	Skip some checks for ordered items
	 * 	[is_trip_changed]		bool	For ordered items
	 * 	[is_day_changed]		bool	For ordered items
	 * 	[is_schedule_changed]	bool	For ordered items
	 * 	[is_inventory_changed]	bool	For ordered items
	 * 	[schedule_id]			int		Choosed schedule
	 * 	[inventory_id]			int		Choosed inventory
	 * 	[tickets]				array	Choosed tickets and their count,
	 * 									for ordered items contains difference between old and new booked count
	 * 	[tickets_data]			array	Contains additional ticket data for already ordered items
	 * 	[additional_options]	array	Choosed options
	 * [tickets]format
	 * 	[
	 * 		[ticket_id] => booked_count,
	 * 		...
	 * 	]
	 * [tickets_data] format
	 * 	[
	 * 		[ticket_id] => [
	 * 			'total'		=> ticket_booked_total,
	 * 			'price'		=> ticket_price,
	 * 		],
	 * 		...
	 * 	]
	 * [additional_options] format
	 * 	[
	 * 		[option_id] => value_id,
	 * 		...
	 * 	]
	 *
	 * @return array List of errors or empty array on success validation
	 */
	public function valid_day_booking($data) {

		$in_order = !empty($data['in_order']);
		$is_fh_item = false;


		$errors = array();


		// У Fareharbor трипов собственная проверка, после которой сразу выходим
		// Все проверки ниже этого условия касаются только обычных трипов и RS трипов
		// Этого параметра fh_id нет вообще когда это запрос RS и когда редактируется
		// часть существующего заказа
		if (isset($data['fh_id']) && $data['fh_id']) {

			if (
				!$data['fh_tickets_config']
					||
				(
					$data['additional_options']
						&&
					!$data['fh_options_config']
				)
			) {

				$errors[] = 'FH misconfiguration. Call us for assistance';

				return $errors;
			}
			else {

				// В целях ускорения взаимодействия с FH валидацию не проводим.
				// Вместо неё будет сразу попытка создать заказ на step2

				// В готовом заказе FH части на данный момент влидировать смысла нет.
				if ($in_order) {

					return $errors;
				}
				else {

					$in_order = false;
					$is_fh_item = true;
				}
			}
		}



		$this->last_validation_flags['schedule_error'] = false;
		$this->last_validation_flags['inventory_error'] = false;


		if (!($calendar_day = $this->get_calendar_day_data($data['calendar_id'], $data['trip_id'], $data['is_gift']))) {

			$errors[] = $this->lang('valid_day_booking_day_error');

			return $errors;
		}

		if (!($trip = $this->get($calendar_day['product_id']))) {

			$errors[] = $this->lang('valid_day_booking_trip_error');

			return $errors;
		}

		if (!$in_order) {

			if (
				!is_api_request()
					&&
				(
					$trip['disabled']
						||
					$trip['disable_booking']
						||
					$trip['is_disabled_ts']
				)
				) {

				$errors[] = $this->lang('valid_day_booking_unavailable_error');

				return $errors;
			}


			// Gift
			if (isset($data['actual_is_gift'])) {

				// При добавлении в корзину новой части параметр уже расчитан в реквесте - передаём его с осоновным массивом
				$is_gift = $data['actual_is_gift'];
			}
			else {

				if (!$is_fh_item) {

					$can_be_gift = !($this->is_price_changes_for_year($trip['id']));

					$is_gift = false;
					if (!empty($data['is_gift']) && $can_be_gift) $is_gift = true;
				}
			}

			if (
				(
					$trip['use_calendar']
						&&
					!$is_gift
						&&
					empty($data['is_past'])
						&&
					(strtotime($calendar_day['date']) < strtotime(date("Y-m-d")))
				)
					||
				(
					$calendar_day['is_cut_off']
						&&
					(
						(
							!is_api_request()
								&&
							!$this->user->is_admin()
						)
							||
						is_front_api_request()
					)
				)
			) {

				$errors[] = $this->lang('valid_day_booking_unavailable_error');

				return $errors;
			}
		}
		else {

			$is_gift = $data['is_gift'];
		}


		// Не проводим валидацию если расходятся следующие параметры у текущего трипа и заказанного
		// - использование календаря(с учётом подарка)
		// - использование расписаний
		if (
			$in_order
				&&
			empty($data['is_trip_changed'])
				&&
			(
				($trip['use_calendar'] && !$data['calendar_id'] && !$is_gift)
					||
				(!$trip['use_calendar'] && $data['calendar_id'])
					||
				($trip['use_schedule'] && empty($data['schedule_id']))
					||
				(!$trip['use_schedule'] && !empty($data['schedule_id']))
			)
			) {

			return array();
		}


		// При редактировании заказа проходим по случаям, когда необходимо частично изменить базовую доступность
		// перед валидацией т.к. она частично определяется редактируемой сейчас частью заказа, и оттого валидатор
		// может иногда запрещать легитимные переносы (или др. переносы).
		// Пока что актуально только тогда, когда используются расписания
		if ($in_order  && $trip['use_schedule']) $this->revalidate_in_order_availability($data, $calendar_day);


		// Schedule
		$errors_counter = count($errors);

		if (
			!$is_fh_item
				&&
			$trip['use_calendar'] && $trip['use_schedule']
				&&
			!$is_gift
				&&
			(
				empty($data['schedule_id'])
					||
				!array_key_exists($data['schedule_id'], $calendar_day['schedules'])
					||
				(
					!$in_order
						&&
					(
						empty($data['is_past'])
							&&
						(
							(
								!is_api_request()
									&&
								$calendar_day['schedules'][$data['schedule_id']]['not_avail_ts']
							)
								||
							(
								is_api_request()
									&&
								$calendar_day['schedules'][$data['schedule_id']]['not_avail']
							)
						)
					)
				)
					||
				(
					!empty($data['is_schedule_changed'])
						&&
					$calendar_day['schedules'][$data['schedule_id']]['is_disabled']
				)
					||
				empty($calendar_day['schedules'][$data['schedule_id']]['tickets'])
			)
				&&
			!(
				$this->user->is_admin()
					&&
				array_key_exists($data['schedule_id'], $calendar_day['schedules'])
					&&
				$calendar_day['schedules'][$data['schedule_id']]['avail_for_admin']
					&&
				!$calendar_day['schedules'][$data['schedule_id']]['stop_sell']
			)
			) {

			// Если расписание в принципе есть - общая ошибка, а если его уже удалили - сообщаем это точно
			if (array_key_exists($data['schedule_id'], $calendar_day['schedules'])) {

				$errors[] = $this->lang('valid_day_booking_schedule_error');
			}
			else {

				$errors[] = $this->lang('valid_day_booking_removed_schedule_error');
			}

			$this->last_validation_flags['schedule_error'] = true;

			return $errors;
		}
		else if (
			!$trip['use_calendar']
				||
			!$trip['use_schedule']
				||
			($trip['use_schedule'] && $is_gift)
			) {

			$data['schedule_id'] = 0;
		}


		// Tickets
		$schedule = null;
		if (!$is_fh_item) {

			$schedule = $calendar_day['schedules'][$data['schedule_id']];
		}
		else {

			foreach ($calendar_day['schedules'] as $test_schedule) {

				if ($data['fh_id'] == $test_schedule['fh_id']) {

					$schedule = $test_schedule;
					break;
				}
			}
		}


		// This is happening for the FH trips only
		if (is_null($schedule)) {

			$errors[] = 'This Schedule Time Is Not Available';

			return $errors;
		}


		$day_tickets = $schedule['tickets'];

		// Всего купленных билетов
		$total_booked = 0;
		// Только новые билеты(для уже оформленного заказа)
		$new_booked = 0;
		$total_price = 0;
		$a_lot_of_tickets = false;

		// Данные параметры нужны для валидации лодок, но определяются по билетам
		$new_trip_duration = null;
		$tickets_have_different_durations = false;

		$try_to_change_ticket_notification = false;

		if (!empty($data['tickets'])) {

			$ticket_price_field = 'price';
			$ticket_avail_field = 'avail_ts';
			if (is_api_request()) {

				$ticket_price_field = 'price_rs';
				$ticket_avail_field = 'avail';
			}

			foreach ($data['tickets'] as $ticket_id => $booked) {

				// В заказанных частях могут быть уже удалённые из трипа билеты
				if (!array_key_exists($ticket_id, $day_tickets) && !$in_order) {

					$errors[] = 'There Is No Ticket with ID '.$ticket_id;

					continue;
				}

				// Если этот билет был ранее из системы удалён, то выполнять валидацию в этой части бессмысленно
				if (isset($day_tickets[$ticket_id])) {

					$ticket = $day_tickets[$ticket_id];

					if (!empty($ticket['duration'])) {

						if (is_null($new_trip_duration)) $new_trip_duration = $ticket['duration'];
						else if ($new_trip_duration !== $ticket['duration']) $tickets_have_different_durations = true;
					}

					// - $booked проверяется наличие доступных билетов
					// - $booked_final проверяется минимально необходимое кол-во билетов
					// при добавлении в корзину новой части эти значения равны
					if (!$in_order) {

						// Стоимость билета
						$ticket_price = $ticket[$ticket_price_field];

						$booked = abs(intval($booked));
						$booked_final = $booked;

						if (!$booked) continue;

						if ($ticket['min'] && ($booked_final < $ticket['min'])) {

							$errors[] = 'You Cannot Order Less Than ' . $ticket['min'] . ' Tickets for "' . $ticket['name'] . '" Ticket';
						}

						if (($booked > 0) && ($booked > $ticket[$ticket_avail_field])) {

							$errors[] = 'You Cannot Order More Than ' . $ticket[$ticket_avail_field] . ' Tickets for "' . $ticket['name'] . '" Ticket';
						}
					} else {

						if (($booked > 0) && ($booked > $ticket[$ticket_avail_field])) {

							$errors[] = 'You Cannot Order More Than ' . $ticket[$ticket_avail_field] . ' Additional Tickets for "' . $ticket['name'] . '" Ticket';
						}
						if ($ticket['min'] && ($data['tickets_data'][$ticket_id]['total'] < $ticket['min'])) {

							$errors[] = 'You Cannot Order Less Than ' . $ticket['min'] . ' Tickets for "' . $ticket['name'] . '" Ticket';
						}

						$ticket_price = $data['tickets_data'][$ticket_id]['price'];

						$booked_final = $data['tickets_data'][$ticket_id]['total'];
					}
				}
				// Эти данные, по идее, сохранятся даже для удалённого билета
				else {

					$ticket_price = $data['tickets_data'][$ticket_id]['price'];
					$booked_final = $data['tickets_data'][$ticket_id]['total'];
				}

				$not_avail_field = (!$in_order ? 'not_avail' : 'not_avail_update');
				$not_avail_ts_field = (!$in_order ? 'not_avail_ts' : 'not_avail_update_ts');

				if (
					($booked > 0)
						&&
					empty($data['is_past'])
						&&
					(
						!isset($day_tickets[$ticket_id])
							||
						(
							is_api_request()
								&&
							$day_tickets[$ticket_id][$not_avail_field]
						)
							||
						(
							!is_api_request()
								&&
							!$this->user->is_admin()
								&&
							$day_tickets[$ticket_id][$not_avail_ts_field]
						)
					)
				) {

					if (!isset($day_tickets[$ticket_id])) {

						$errors[] = 'This Ticket Is Not Available';
					}
					else {

						$errors[] = 'The "'.$day_tickets[$ticket_id]['name'].'" Ticket Is Not Available for This Reservation Options';
					}
				}


				// Закзам, сделанным через API и админам TS не ограничиваем число билетов, а остальным - до 30
				if (
					!is_mode('admin_cart_create', 'orders_save_data', 'admin_edit_cart')
						&&
					!(!is_api_request() && $this->user->is_admin())
						&&
					($booked_final > 30)
				) {

					$a_lot_of_tickets = true;
				}


				$total_booked += $booked_final;
				$new_booked += $booked;

				$total_price += $booked_final*$ticket_price;


				// Проверяем на заполненность и структурируем имена и фамилии
				$ticket_guests = array();

				if (
					!is_mode('set_partial_state')
						&&
					(!$in_order || !empty($data['is_trip_changed']))
						&&
					(
						!empty($data['need_tickets_guests'])
							||
						(!array_key_exists('need_tickets_guests', $data) && $trip['need_tickets_guests'])
					)
					) {

					$guests_error = false;

					if (!empty($data['guests'][$ticket_id])) {

						$ticket_guests = $data['guests'][$ticket_id];

						foreach ($data['guests'][$ticket_id] as $guest) {

							if (empty($guest['first_name']) || empty($guest['last_name'])) {

								$guests_error = true;
							}
						}
					}
					else $guests_error = true;

					if ($guests_error) {

						$errors[] = $this->lang('valid_day_booking_tickets_guests_error');
					}
				}
			}


			if ($a_lot_of_tickets) {

				$errors[] = $this->lang('valid_day_booking_many_tickets_error');
			}

			if (is_mode('admin_cart_create')) {

				$check_booked = $data['data']['for_validation']['total_booked'];
			}
			else if ($in_order && isset($data['for_validation']['schedule_tickets'][$schedule['id']])) {

				$check_booked = $data['for_validation']['schedule_tickets'][$schedule['id']];
			}
			else {

				$check_booked = $total_booked;
			}

			if ($schedule['min_tickets'] && $check_booked && ($check_booked < $schedule['min_tickets'])) {

				$errors[] = 'You Should Purchase At Least '.$schedule['min_tickets'].' Ticket(s) for This Day-Time';
			}

			$max_tickets = abs($schedule['max_tickets'] - $schedule['sold']);

			if ($in_order) {

				if (
					isset($schedule['max_tickets'])
						&&
					!is_null($schedule['max_tickets'])
						&&
					(
						(!empty($data['is_schedule_changed']) && ($total_booked > $max_tickets))
							||
						(empty($data['is_schedule_changed']) && ($new_booked > $max_tickets))
					)
				) {

					$errors[] = 'You Should Purchase No More Than '.$max_tickets.' Tickets for This Day-Time';
				}
			}
			else {

				if (
					isset($schedule['max_tickets'])
						&&
					!is_null($schedule['max_tickets'])
						&&
					($total_booked > $max_tickets)
				) {

					$errors[] = 'You Should Purchase No More Than '.$max_tickets.' Tickets for This Day-Time';
				}

				if (!$total_booked) {

					$errors[] = $this->lang('valid_day_booking_few_tickets_error');
				}
				if (!$total_price && !is_mode('admin_cart_create')) {

					$errors[] = 'Total Amount Must be Greater Than '.money(0);
				}
			}
		}
		// Если даже в части билетов не осталось, то это могло привести к тому, что мы спустились ниже мин. порога расписания
		else if ($in_order && isset($data['for_validation']['schedule_tickets'][$schedule['id']])) {

			$check_booked = $data['for_validation']['schedule_tickets'][$schedule['id']];

			if ($schedule['min_tickets'] && ($check_booked < $schedule['min_tickets'])) {

				$errors[] = 'You Should Purchase At Least '.$schedule['min_tickets'].' Ticket(s) for This Day-Time';
			}
		}

		// Если за этот промежуток кода ошибок добавилось, то не всегда, но зачастую это будет из-за расписания
		if ($errors_counter < count($errors)) $this->last_validation_flags['schedule_error'] = true;


		// Inventory
		$errors_counter = count($errors);

		if (
			!$is_fh_item
				&&
			!$errors
				&&
			$trip['use_calendar'] && $trip['use_schedule']
				&&
			(
				!$in_order || $new_booked
					||
				!empty($data['is_inventory_changed']) || !empty($data['is_schedule_changed'])
			)
				&&
			!$is_gift
				&&
			$calendar_day['schedules'][$data['schedule_id']]['inventories']
				&&
			!($in_order && empty($data['inventory_id']))
			) {

			// Будет true в тех случаях когда используются inventories
			// и пришедшие билеты нужно попытаться поделить между ними
			$try_separate = ((!is_mode('admin_cart_create') && empty($data['inventory_id'])) ? true : false);

			$this->add_page('calendar');

			if (
				// Флаг определяется по билетам (выше), но имеет силу только если используются лодки
				// Означает что во вновь заказанных билетах есть расхождение по времени
				(
					!$try_separate
						&&
					$tickets_have_different_durations
				)
					||
				// Итем редактируется и есть расхождение по времени с тем, что там уже есть
				(
					isset($data['trip_duration'])
						&&
					!is_null($data['trip_duration'])
						&&
					!is_null($new_trip_duration)
						&&
					($new_trip_duration != $data['trip_duration'])
				)
				) {

				$errors[] = $this->lang('valid_day_booking_duration_error');
			}

			$inventories = $calendar_day['schedules'][$data['schedule_id']]['inventories'];

			if (empty($data['inventory_id'])) {

				if ($try_separate) {

					$separation_tickets = array();

					foreach ($data['tickets'] as $ticket_id => $booked) {

						$separation_tickets[$ticket_id] = array (
							'booked'	=> $booked,
							'duration'	=> $day_tickets[$ticket_id]['duration'],
						);
					}

					$separation_result = $this->separate_tickets_inventories($trip['id'], $separation_tickets, $inventories);

					if ($separation_result['errors']) $errors = array_merge($errors, $separation_result['errors']);
				}
				else {

					$has_available_inventory = false;
					$max_avail = null;
					$disabled_inventories = 0;

					foreach ($inventories as $inventory) {

						// Отдельные билеты заказа могут не подходить данным лодке-расписанию по длительности самой лодки или др. заказов
						$ticket_is_not_available_by_duration = false;
						$not_available_tickets = array_keys($inventories[$inventory['id']]['tickets_avail_by_duration'], false);
						$booked_tickets = array_keys($data['tickets']);
						foreach ($booked_tickets as $booked_ticket_id) {

							if (in_array($booked_ticket_id, $not_available_tickets)) {

								$ticket_is_not_available_by_duration = true;
								break;
							}
						}

						if (!$inventory['is_avail'] || $ticket_is_not_available_by_duration) {

							$disabled_inventories++;
						} else {

							if (
								!$has_available_inventory
								&&
								($inventory['avail'] >= $new_booked)
							) {

								$has_available_inventory = true;
							}

							if (
								($max_avail === null)
								||
								($inventory['avail'] > $max_avail)
							) {

								$max_avail = $inventory['avail'];
							}
						}
					}

					if ($disabled_inventories == count($inventories)) {

						$errors[] = $this->lang('valid_day_booking_inventory_error');
					} else if (!$has_available_inventory) {

						$errors[] = 'You Should Purchase No More Than ' . $max_avail . ' Tickets for This Day-Time';
					}
				}
			}
			else {

				$inventory_id = $data['inventory_id'];

				// Итем только создаётся, но есть расхождение по времени с уже установленной длительностью лодки
				if (
					isset($inventories[$inventory_id]['duration'])
						&&
					!is_null($inventories[$inventory_id]['duration'])
						&&
					!is_null($new_trip_duration)
						&&
					($new_trip_duration != $inventories[$inventory_id]['duration'])

				) {

					$errors[] = 'Ticket With This Duration Cannot be Ordered';
				}

				// Отдельные билеты заказа могут не подходить данным лодке-расписанию по длительности самой лодки или др. заказов
				$ticket_is_not_available_by_duration = false;

				if (isset($inventories[$inventory_id])) {

					$not_available_tickets = array_keys($inventories[$inventory_id]['tickets_avail_by_duration'], false);
					$booked_tickets = array_keys($data['tickets']);
					foreach ($booked_tickets as $booked_ticket_id) {

						if (in_array($booked_ticket_id, $not_available_tickets)) {

							$ticket_is_not_available_by_duration = true;
							break;
						}
					}
				}

				if (
					!array_key_exists($inventory_id, $inventories)
						||
					$inventories[$inventory_id]['is_disabled']
						||
					$ticket_is_not_available_by_duration
						||
					(
						empty($data['is_inventory_changed'])
							&&
						($inventories[$inventory_id]['avail'] < $new_booked)
					)
						||
					(
						(!empty($data['is_inventory_changed']) || !empty($data['is_schedule_changed']))
							&&
						(
							($inventories[$inventory_id]['avail'] < $total_booked)
								||
							!empty($inventories[$inventory_id]['in_use'])
						)
					)
					) {

					if ($in_order) {

						$errors[] = 'Chosen Equipment Is Not Available or Does Not Have Extra Places';
					}
					else $errors[] = $this->lang('valid_day_booking_inventory_error');
				}
			}

			if ($errors_counter < count($errors)) {

				$this->last_validation_flags['inventory_error'] = true;
				$errors_counter = count($errors);
			}

			// Также проверяем, не выдаётся ли трип свой длительностью за close_out время трипа на этот день
			if (
				(
					!is_null($new_trip_duration)
						||
					isset($data['trip_duration'])
				)
					&&
				(
					isset($data['day'])
						||
					isset($data['calendar_id'])
				)
			) {

				$current_trip_duration = (!is_null($new_trip_duration) ? $new_trip_duration : $data['trip_duration']);
				if (isset($data['day'])) {

					$current_day = $data['day'];
				}
				else {

					$this->add_page('calendar');
					$full_day = $this->page_calendar->get_day($data['calendar_id']);
					$current_day = $full_day['date'];
				}
				$current_day = format_date_for_db($current_day);

				$this->add_page('calendar');

				// Close out время трипа устанавливается либо на конкретный день, либо по дефолту для всего трипа
				$close_out_time = null;

				$day_data = $this->page_calendar->get_days_trip_data($trip['id'], 'close_out', array($current_day));

				if (isset($day_data[$current_day]) && !is_null($day_data[$current_day]['close_out'])) {

					$close_out_time = $day_data[$current_day]['close_out'];
				}
				else if (!is_null($trip['close_out'])) {

					$close_out_time = $trip['close_out'];
				}


				if (!is_null($close_out_time)) {

					$trip_end_minutes = convert_hour_to_minutes($schedule['time']) + $current_trip_duration;
					$close_out_minutes = convert_hour_to_minutes($close_out_time);

					if ($trip_end_minutes > $close_out_minutes) {

						$errors[] = $this->lang('valid_day_booking_close_out_error');

						// Это хоть и проверяется в сегменте лодок, но по сути является ошибкой расписания
						if ($errors_counter < count($errors)) $this->last_validation_flags['schedule_error'] = true;
					}
				}
			}
		}


		// Пропускаем проверку опций для заказа, т.к. опция могла быть уже удалена, как и её значение,
		// а is_disabled и is_required нам не важны
		if (!empty($data['additional_options']) && !$in_order) {

			foreach ($data['additional_options'] as $option_id => $value) {

				if (!array_key_exists($option_id, $calendar_day['additional_options'])) {

					// В заказанных частях могут быть уже удалённые из трипа опции
					$errors[] = 'There is No Option With ID '.$option_id;

					continue;
				}

				if (!$value) continue;


				$option = $calendar_day['additional_options'][$option_id];

				if ($option['is_disabled']) {

					$errors[] = 'Option "'.$option['name'].'" is Not Available';
				}
				else if (
					($option['type'] == 'select')
						&&
					!array_key_exists($value, $option['values'])
					) {

					$errors[] = 'There is No Option "'.$option['name'].'" Value With ID '.$value;
				}
			}

			// required options
			foreach ($calendar_day['additional_options'] as $option) {

				if (
					!$option['is_disabled']
						&&
					$option['required']
						&&
					(
						(!$is_fh_item && empty($data['additional_options'][$option['id']]))
							||
						($is_fh_item && empty($data['additional_options'][$option['fh_id']]))
					)
					) {

					$errors[] = 'Option "'.$option['name'].'" is required';
				}
			}
		}


		return $errors;
	}


	public function validate_equipment_swap($swap_data = array()) {

		$errors = array();

		if (
			!$swap_data
				||
			!isset($swap_data['cart_items'])
				||
			!$swap_data['cart_items']
				||
			empty($swap_data['date'])
				||
			empty($swap_data['trip_id'])
				||
			(!isset($swap_data['new_cell']['schedule_id']) || empty($swap_data['new_cell']['schedule_id']))
				||
			(!isset($swap_data['new_cell']['inventory_id']) || empty($swap_data['new_cell']['inventory_id']))
				||
			!isset($swap_data['old_cell']['schedule_id'])
				||
			!isset($swap_data['old_cell']['inventory_id'])
		) {

			$errors[] = $this->lang('valid_batch_data');
		}


		if (!$errors) {

			$this->add_page('calendar');
			$calendar_id = $this->page_calendar->get_day(null, $swap_data['date'], $swap_data['trip_id']);

			if (is_null($calendar_id)) $errors[] = $this->lang('valid_day_booking_day_error');
			else $calendar_id = $calendar_id['id'];
		}


		// Пропускаем всё тело если входные данные были неверны в базовом смысле
		if (!$errors) {

			$this->db->query("
				SELECT
					cart_items.id,
					cart_items.data,
					cart_items.product_id,
					
					calendar.date,
					
					orders.made_from_rs
				FROM
					cart_items
					JOIN calendar ON calendar.id = cart_items.calendar_id
					JOIN orders ON orders.cart_id = cart_items.cart_id
				WHERE
					cart_items.id IN (".$this->db->in($swap_data['cart_items']).")
						AND
					is_fh_item = 0"
			);
			$db_res = $this->db->result;

			// Сюда пишутся в верхних ключах id продуктов -> даты -> id расписаний, а конечными подмассивами:
			// - для билетов: id билетов и число заказанного с разделениеим на TS и RS
			// - для лодок: id лодок и общее заказанное на них число билетов
			$tickets_to_purge = array();
			$inventories_to_purge = array();

			// Сколько минимум и максимум заказано реальных билетов в частях заказов
			// НА ДАННЫЙ момент нет ключей трипа и даты на верхнем уровне - считаем что всё одно
			$minimax_via_schedule = array();
			$items_duration = null;

			while ($cart_item = $this->db->fetch($db_res)) {

				$item_data = $this->db->decode_data($cart_item['data']);

				if (!isset($tickets_to_purge[$cart_item['product_id']])) {

					$tickets_to_purge[$cart_item['product_id']] = array();
				}
				if (!isset($tickets_to_purge[$cart_item['product_id']][$cart_item['date']])) {

					$tickets_to_purge[$cart_item['product_id']][$cart_item['date']] = array();
				}

				$schedule_id = (!empty($item_data['schedule']) ? $item_data['schedule']['id'] : 0);
				if ($item_data['schedule']['id'] != $swap_data['old_cell']['schedule_id']) {

					$errors[] = $this->lang('valid_batch_old_schedule');
					break;
				}

				if (!isset($tickets_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id])) {

					$tickets_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id] = array();
				}


				if ($item_data['inventory']) {

					if ($item_data['inventory']['id'] != $swap_data['old_cell']['inventory_id']) {

						$errors[] = $this->lang('valid_batch_old_inventory');
						break;
					}
					if (!is_null($item_data['inventory']['duration'])) {

						if (is_null($items_duration)) {

							$items_duration = $item_data['inventory']['duration'];
						}
						else if ($items_duration != $item_data['inventory']['duration']) {

							$errors[] = $this->lang('valid_batch_basic_duration');
							break;
						}
					}

					if (!isset($inventories_to_purge[$cart_item['product_id']])) {

						$inventories_to_purge[$cart_item['product_id']] = array();
					}
					if (!isset($inventories_to_purge[$cart_item['product_id']][$cart_item['date']])) {

						$inventories_to_purge[$cart_item['product_id']][$cart_item['date']] = array();
					}
					if (!isset($inventories_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id])) {

						$inventories_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id] = array();
					}
				}


				if (!isset($minimax_via_schedule[$schedule_id])) {

					$minimax_via_schedule[$schedule_id] = array (
						'min'		=> 0,
						'max'		=> 0,
						'tickets'	=> array()
					);
				}
				$booked_in_item = 0;


				foreach ($item_data['tickets'] as $ticket_id => $ticket_data) {

					$booked_in_item += $ticket_data['booked'];

					$data = array(
						'rs' => $ticket_data['booked'],
						'ts' => ($cart_item['made_from_rs'] ? 0 : $ticket_data['booked']),
					);

					if (!isset($tickets_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id][$ticket_id])) {

						$tickets_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id][$ticket_id] = $data;
					}
					else {

						$tickets_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id][$ticket_id] =
							array_add($tickets_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id][$ticket_id], $data);
					}

					if (!isset($inventories_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id][$item_data['inventory']['id']])) {

						$inventories_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id][$item_data['inventory']['id']] = $data['rs'];
					}
					else {

						$inventories_to_purge[$cart_item['product_id']][$cart_item['date']][$schedule_id][$item_data['inventory']['id']] += $data['rs'];
					}

					if (!isset($minimax_via_schedule[$schedule_id]['tickets'][$ticket_id])) {

						$minimax_via_schedule[$schedule_id]['tickets'][$ticket_id] = array (
							'min'	=> 0,
							'max'	=> 0,
						);
					}

					if (
						($minimax_via_schedule[$schedule_id]['tickets'][$ticket_id]['min'] == 0)
							||
						($ticket_data['booked'] < $minimax_via_schedule[$schedule_id]['tickets'][$ticket_id]['min'])
					) {

						$minimax_via_schedule[$schedule_id]['tickets'][$ticket_id]['min'] = $ticket_data['booked'];
					}
					if (
						($minimax_via_schedule[$schedule_id]['tickets'][$ticket_id]['max'] == 0)
							||
						($ticket_data['booked'] > $minimax_via_schedule[$schedule_id]['tickets'][$ticket_id]['max'])
					) {

						$minimax_via_schedule[$schedule_id]['tickets'][$ticket_id]['max'] = $ticket_data['booked'];
					}
				}

				if (
					($minimax_via_schedule[$schedule_id]['min'] == 0)
						||
					($booked_in_item < $minimax_via_schedule[$schedule_id]['min'])
				) {

					$minimax_via_schedule[$schedule_id]['min'] = $booked_in_item;
				}
				if (
					($minimax_via_schedule[$schedule_id]['max'] == 0)
						||
					($booked_in_item > $minimax_via_schedule[$schedule_id]['max'])
				) {

					$minimax_via_schedule[$schedule_id]['max'] = $booked_in_item;
				}
			}

			$this->config('purge_availability', array(
				'schedules_tickets' => $tickets_to_purge,
				'inventory_tickets' => $inventories_to_purge
			));

			if (!($calendar_day = $this->get_calendar_day_data($calendar_id, null, false, true))) {

				$errors[] = $this->lang('valid_day_booking_day_error');
			}

			$this->config('purge_availability', null);


			// TODO Несмотря на то, что выше данные собираются в универсальном виде
			// TODO (т.е. в т.ч. под условным расписанием 0 если расписаний нет)
			// TODO валидация дальше идёт с допущением, что и расписание и лодка есть
			// TODO Более универсальный вариант - дело этапа цельной валидации
			if (!$errors) {

				// TODO Тут уже проверяем, входят ли все перемещаемые части в новую ячейку расписания
				$new_schedule = $swap_data['new_cell']['schedule_id'];
				$old_schedule = $swap_data['old_cell']['schedule_id'];
				$new_inventory = $swap_data['new_cell']['inventory_id'];
				$is_schedule_changed = ($new_schedule != $swap_data['old_cell']['schedule_id']);
				$is_inventory_changed = ($new_inventory != $swap_data['old_cell']['inventory_id']);

				$booked_final_ts = 0;
				$booked_final_rs = 0;

				// Базовые проверки по новому расписанию
				// TODO Возможно всё это (до лодок) нужно проверять только если срасписание изменилось
				if (
					(
						!array_key_exists($new_schedule, $calendar_day['schedules'])
							||
						(
							$is_schedule_changed
								&&
							$calendar_day['schedules'][$new_schedule]['is_disabled']
						)
							||
						empty($calendar_day['schedules'][$new_schedule]['tickets'])
					)
						&&
					// В оригинальной валидации так проверяется только для админа, а для юзера при заказе
					// другие проверки по полям not_avail. Но данный метод исключительно для админов и для готовых заказов
					!(
						array_key_exists($new_schedule, $calendar_day['schedules'])
							&&
						$calendar_day['schedules'][$new_schedule]['avail_for_admin']
							&&
						!$calendar_day['schedules'][$new_schedule]['stop_sell']
					)
				) {

					// Если расписание в принципе есть - общая ошибка, а если его уже удалили - сообщаем это точно
					if (array_key_exists($new_schedule, $calendar_day['schedules'])) {

						$errors[] = $this->lang('valid_day_booking_schedule_error');
					}
					else {

						$errors[] = $this->lang('valid_day_booking_removed_schedule_error');
					}

					return $errors;
				}

				$schedule = $calendar_day['schedules'][$new_schedule];
				$day_tickets = $schedule['tickets'];
				$formatted_date = format_date_for_db($swap_data['date']);

				// Основные проверки по части билетов
				foreach ($tickets_to_purge[$swap_data['trip_id']][$formatted_date][$old_schedule] as $swap_ticket_id => $swap_ticket_data) {

					if (isset($day_tickets[$swap_ticket_id])) {

						$day_ticket = $day_tickets[$swap_ticket_id];

						$already_failed = false;
						if ($swap_ticket_data['ts'] > 0) {

							if ($swap_ticket_data['ts'] > $day_ticket['avail_ts']) {

								$errors[] = 'Destination cell have not enough available tickets for "' . $day_ticket['name'] . '" ticket';
								$already_failed = true;
							}
							else if ($day_ticket['is_disabled'] || $day_ticket['not_avail_update_ts']) {

								$errors[] = 'The "' . $day_ticket['name'] . '" ticket is not available for this reservation options';
								$already_failed = true;
							}
						}
						if (($swap_ticket_data['rs'] > 0) && !$already_failed) {

							if ($swap_ticket_data['rs'] > $day_ticket['avail']) {

								$errors[] = 'Destination cell have not enough available tickets for "' . $day_ticket['name'] . '" ticket';
							}
							else if ($day_ticket['is_disabled'] || $day_ticket['not_avail_update']) {

								$errors[] = 'The "' . $day_ticket['name'] . '" ticket is not available for this reservation options';
							}
						}
						if (
							$day_ticket['min']
								&&
							isset($minimax_via_schedule[$old_schedule]['tickets'][$swap_ticket_id]['min'])
								&&
							($minimax_via_schedule[$old_schedule]['tickets'][$swap_ticket_id]['min'] < $day_ticket['min'])
						) {

							$errors[] = 'You cannot order less than ' . $day_ticket['min'] . ' tickets for "' . $day_ticket['name'] . '" ticket';
						}
					}
					else {

						// Чтобы сработала данная ошибка нужно чтобы был заказан хоть один билет
						// Но доп. проверку не делаем т.к. по идее сюда попасть без единого билета было нельзя
						$errors[] = 'One of the tickets is unavailable';
					}

					$booked_final_ts += $swap_ticket_data['ts'];
					$booked_final_rs += $swap_ticket_data['rs'];
				}


				// Проверки по суммарным данным билетов и расписаний
				$check_booked = max($booked_final_ts, $booked_final_rs);

				if ($schedule['min_tickets'] && $check_booked && ($check_booked < $schedule['min_tickets'])) {

					$errors[] = 'You should purchase at least '.$schedule['min_tickets'].' ticket(s) for this day-time';
				}

				if (
					isset($schedule['max_tickets'])
						&&
					!is_null($schedule['max_tickets'])
				) {

					$max_tickets = abs($schedule['max_tickets'] - $schedule['sold']);

					if ($check_booked > $max_tickets) {

						$errors[] = 'You should purchase no more than '.$max_tickets.' tickets for this day-time';
					}
				}


				// Проверки по лодкам
				if (!$errors && isset($schedule['inventories'][$new_inventory])) {

					$inventory = $schedule['inventories'][$new_inventory];

					// Проверяем основные флаги а так же наличие мест
					if (
						$inventory['is_disabled']
							||
						!$inventory['is_avail']
							||
						$inventory['in_use']
							||
						(
							$check_booked
								&&
							$inventory['avail'] < $check_booked
						)
					) {

						$errors[] = $this->lang('valid_day_booking_inventory_error');
					}
					// Проверяем доступность в данной лодке отдельных билетов
					// По идее это не обязательно тут, но на всякий случай пусть будет
					else {

						foreach ($tickets_to_purge[$swap_data['trip_id']][$formatted_date][$old_schedule] as $swap_ticket_id => $swap_ticket_data) {

							if (
								!isset($inventory['tickets_avail_by_duration'][$swap_ticket_id])
									||
								!$inventory['tickets_avail_by_duration'][$swap_ticket_id]
							) {

								$errors[] = $this->lang('valid_day_booking_inventory_error');
								break;
							}
						}
					}

					// Длительность новой лодки в сравнении с длительностью старой
					if (
						!is_null($items_duration)
							&&
						!is_null($inventory['duration'])
							&&
						($inventory['duration'] != $items_duration)
					) {

						$errors[] = $this->lang('valid_batch_new_duration');
					}


					// Не выдаётся ли трип свой длительностью за close_out время трипа на этот день
					if ($is_schedule_changed && !is_null($items_duration)) {

						// Close out время трипа устанавливается либо на конкретный день, либо по дефолту для всего трипа
						$close_out_time = null;

						$day_data = $this->page_calendar->get_days_trip_data(
							$swap_data['trip_id'],
							'close_out',
							array($formatted_date)
						);

						if (isset($day_data[$formatted_date]) && !is_null($day_data[$formatted_date]['close_out'])) {

							$close_out_time = $day_data[$formatted_date]['close_out'];
						}
						else {

							// TODO По-хорошему нужно переменную $trip определять в начале метода и со всеми полями
							// TODO Однако пока делаем так для быстродействия. Если понадобится больше полей трипа - перенесём
							$this->db->query("
								SELECT
									id,
									close_out
								FROM
									products
								WHERE
									products.id = ?",
								$swap_data['trip_id']
							);

							$trip = $this->db->fetch();

							if (!is_null($trip['close_out'])) {

								$close_out_time = $trip['close_out'];
							}
						}

						if (!is_null($close_out_time)) {

							$trip_end_minutes = convert_hour_to_minutes($schedule['time']) + $items_duration;
							$close_out_minutes = convert_hour_to_minutes($close_out_time);

							if ($trip_end_minutes > $close_out_minutes) {

								$errors[] = $this->lang('valid_day_booking_close_out_error');
							}
						}
					}
				}
			}
		}

		return $errors;
	}


	// Получаем картинку для трипа. Картинка ожидается в $_FILES['file']
	// - если пришёл $_POST['product_id'], то сразу кладём в соответствующую папку продукта
	// ./photos/product/{product_id}/
	// - если пришёл $_POST['tmp_key'], то кладём в соответствующий временный каталог
	// ./photos/tmp/{tmp_key}/
	//
	// post image_upload(product_id, tmp_key)
	public function mode_image_upload() {

		$error = true;
		$messages = array();

		$old = false;
		if (isset($_POST['old'])) $old = true;

		$is_rs = (empty($_POST['is_rs']) ? false : true);

		if (!empty($_FILES['file']['name']) && file_exists($_FILES['file']['tmp_name'])) {

			$this->add_page('photo');

			$image_extension = pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION);

			if (in_array($image_extension, $this->page_photo->avaiable_types)) {

				if (!empty($_POST['tmp_key'])) {

					$tmp_key = $_POST['tmp_key'];

					if (preg_match('/^[a-zA-Z0-9]+?$/', $tmp_key)) {

						$upload_result = $this->page_photo->store_photo(
							$_FILES['file']['tmp_name'],
							$tmp_key,
							null,
							'product_temp',
							$image_extension
						);

						if ($upload_result) {

							$error = false;
							$messages[] = 'Image Uploaded';
						}
						else $messages[] = 'Image Uploading Failed';
					}
					else $messages[] = 'Wrong tmp_key';
				}
				else if (!empty($_POST['product_id'])) {

					$trip_id = intval($_POST['product_id']);

					if ($trip = $this->get($trip_id)) {

						$this->config('rs_image_uploading', $is_rs);

						$photo_id = $this->page_photo->save(array (
							'file'				=> $_FILES['file']['tmp_name'],
							'image_extension'	=> $image_extension,

							'user_id'			=> $trip['partner_id'],
							'content_type'		=> 'product',
							'target_id'			=> $trip['id'],
							'is_approved'		=> true,
						));

						$error = false;
						$messages[] = 'Image Uploaded';
					}
					else $messages[] = 'There is no trip with id '.$trip_id;
				}
				else $messages[] = 'There is no product_id and tmp_key';
			}
			else $messages[] = 'Image type '.$image_extension.' is not avail';
		}
		else $messages[] = 'File is missing';

		if ($old && (!empty($_SERVER['HTTP_REFERER']) || !empty($_POST['redirect_url']))) {

			if (!empty($_POST['redirect_url'])) {

				$this->url->go($_POST['redirect_url'].($error ? "&error=1" : ""));
			}
			else {

				$this->url->go($_SERVER['HTTP_REFERER']);
			}
		}
		else {

			header("Access-Control-Allow-Origin: *");
			header("Access-Control-Allow-Headers: Cache-Control, Content-Type, Authorization, X-Requested-With");

			ajax_info(array (
				'success'	=> !$error,
				'data'		=> array (),
				'msg'		=> $messages,
			));
		}
	}


	// Отправляем в шаблон данные для вывода формы поиска трипов
	public function sidebar_search_form_render() {

		$trip_search = new request_trip_search(array (
			'name'		=> 'trip_search',
			'method'	=> 'get',
			'vars'		=> array (
				'activity,<select>',
				'city,<select>',
				'date_filter,<checkbox>',
				'date_from,<text>',
				'date_to,<text>',
				'order,<select>'
			),
		));

		$this->tpl('trip_search_city_id', $trip_search->defaults['city']);
		$this->tpl('trip_search_activity_id', $trip_search->defaults['activity']);

		if (($cities_activities = $this->cache->load_from_file(array('sidebar_search_form_render'))) === null) {

			$cities = array(
				0 => array(
					'id' => 0,
					'name' => 'Any',
					'activities' => array(),
				)
			);

			$this->db->query("
				SELECT DISTINCT
					cities.id,
					cities.name
				FROM
					products
					JOIN trip_ticket ON trip_ticket.trip_id = products.id
					JOIN cities ON cities.id IN (products.city_id, products.city_id2, products.city_id3, products.city_id4, products.city_id5, products.city_id6)
				WHERE
					cities.view = 1
						AND
					products.disabled = 0
					".(!is_portal() ? "AND products.portal_only = 0" : "")."
						AND
					(
						products.valid_to IS NULL
							OR
						products.valid_to >= CURDATE()
					)
						AND
					products.product_type_id = ?
				ORDER BY
					IF(cities.ord = '', 1, 0), cities.ord",
				product_type('trip')
			);

			while ($city = $this->db->fetch()) {

				$cities[$city['id']] = array(
					'id'			=> $city['id'],
					'name'			=> $city['name'],
					'activities'	=> array(),
				);
			}

			// Города для разных поисковых табов
			// Сам список городов один на все табы
			$cities_sources = array(
				// Это будем заполнять всемии активностями
				'all'				=> $cities,
				// Это - только транспортировочными
				'transportation'	=> $cities,
			);

			$activities = array();

			$this->db->query("
				SELECT DISTINCT
					activities.id,
					activities.name
				FROM
					products
					JOIN trip_ticket ON trip_ticket.trip_id = products.id
					JOIN activities ON activities.id IN (products.activity_id, products.activity2_id, products.activity3_id)
				WHERE
					activities.view = 1
						AND
					products.disabled = 0
					".(!is_portal() ? "AND products.portal_only = 0" : "")."
						AND
					(
						products.valid_to IS NULL
							OR
						products.valid_to >= CURDATE()
					)
						AND
					products.product_type_id = ?
				ORDER BY
					activities.name",
				product_type('trip')
			);

			while ($activity = $this->db->fetch()) {

				$activities[$activity['id']] = array(
					'id' => $activity['id'],
					'name' => $activity['name'],
				);

				$cities[0]['activities'][] = $activity['id'];
				$cities_sources['all'][0]['activities'][] = $activity['id'];
			}

			$this->db->query("
				SELECT
					products.city_id,
					products.city_id2,
					products.city_id3,
					products.city_id4,
					products.city_id5,
					products.city_id6,

					products.activity_id,
					products.activity2_id,
					products.activity3_id
				FROM
					products
					JOIN trip_ticket ON trip_ticket.trip_id = products.id
				WHERE
					products.disabled = 0
					".(!is_portal() ? "AND products.portal_only = 0" : "")."
						AND
					(
						products.valid_to IS NULL
							OR
						products.valid_to >= CURDATE()
					)
						AND
					products.product_type_id = ?",
				product_type('trip')
			);

			while ($product = $this->db->fetch()) {

				$product_cities = array (
					$product['city_id'],
					$product['city_id2'],
					$product['city_id3'],
					$product['city_id4'],
					$product['city_id5'],
					$product['city_id6'],
				);

				$product_activities = array (
					$product['activity_id'],
					$product['activity2_id'],
					$product['activity3_id'],
				);

				foreach ($product_cities as $city_id) {

					foreach ($product_activities as $activity_id) {

						if (empty($cities[$city_id])) continue;

						if (!in_array($activity_id, $cities[$city_id]['activities'])) {

							$cities[$city_id]['activities'][] = $activity_id;
						}
					}
				}
			}

			foreach ($cities as $city_id => $city) {

				if (!$city_id || empty($cities[0])) continue;

				$sorted_activities = array();

				foreach ($cities[0]['activities'] as $activity_id) {

					if (in_array($activity_id, $city['activities'])) {

						$sorted_activities[] = $activity_id;
					}
				}

				$cities[$city_id]['activities'] = $sorted_activities;
			}

			// А в ключ 'all' - весь обысный список активностей
			$cities_sources['all'] = $cities;

			$cities_activities = array (
				'cities'			=> $cities,
				'cities_sources'	=> $cities_sources,
				'activities'		=> $activities,
			);

			$this->cache->save_to_file(array('sidebar_search_form_render'), $cities_activities);
		}

		$this->tpl_raw('search_cities', json_encode($cities_activities['cities']));
		// Для переключения содержимого в одном и том же табе (псевдотаб "Transportation")
		$this->tpl_raw('cities_sources', json_encode(
			isset($cities_activities['cities_sources']) ?
				$cities_activities['cities_sources'] :
				array()
			)
		);
		$this->tpl_raw('search_activities', json_encode($cities_activities['activities']));

		if (is_mobile()) {

			$default_params = array (
				'from'		=> '',
				'to'		=> '',

				'activity'		=> 0,
				'city'			=> 0,
			);

			if (!empty($_SESSION['last_search']['trips']['search_params'])) {

				$default_params = $_SESSION['last_search']['trips']['search_params'];
			}

			$this->tpl->batch('search_default', array (
				'from'			=> ($default_params['from'] ? format_time($default_params['from'], false) : ''),
				'to'			=> ($default_params['to'] ? format_time($default_params['to'], false) : ''),

				'default_from'	=> format_time(null, false),
				'default_to'	=> format_time(strtotime("+7 day"), false),

				'activity'		=> intval($default_params['activity']),
				'city'			=> intval($default_params['city']),
			));
		}
	}

	// Показываем в правом сайдбаре список категорий/активити/типов для текущих параметров поиска
	public function render_categories_side_list() {

		// Список id трипов уже должен лежать в сессии после поиска
		if (empty($_SESSION['search_trips']['avail'])) return;

		$trips_id = $_SESSION['search_trips']['avail'];

		$seourl_params = $this->get_seourl_params();

		$state_id = null;
		$state = array();
		$state_seo_name = '';

		$city_id = null;
		$city = array();
		$city_seo_name = '';

		if (!($state_id = $this->url->get_int('state')) && !empty($seourl_params['state']))  {

			$state_id = $seourl_params['state'];
		}
		if (!empty($state_id)) {

			$this->add_page('state');

			$state = $this->page_state->get($state_id);

			$state_seo_name = format_to_seourl($state['name']);
		}

		if (!($city_id = $this->url->get_int('city')) && !empty($seourl_params['city']))  {

			$city_id = $seourl_params['city'];
		}
		if (!empty($city_id)) {

			$this->add_page('city');

			$city = $this->page_city->get($city_id);

			$city_seo_name = format_to_seourl($city['name']);
			$state_seo_name = format_to_seourl($city['state_name']);
		}

		// Кол-во топ-категорий для вывода
		$top_count = 10;

		$where_state = '';
		if ($state_id) {

			$this->add_page('city');

			if ($cities = $this->page_city->get_list(array('state_id' => $state_id), false)) {

				$cities_id = array_keys($cities);

				$where_state = '
					(
						products.city_id IN ('.$this->db->in($cities_id).')
							OR
						products.city_id2 IN ('.$this->db->in($cities_id).')
							OR
						products.city_id3 IN ('.$this->db->in($cities_id).')
							OR
						products.city_id4 IN ('.$this->db->in($cities_id).')
							OR
						products.city_id5 IN ('.$this->db->in($cities_id).')
							OR
						products.city_id6 IN ('.$this->db->in($cities_id).')
					)
				';
			}
		}

		// Берём топ самых просматриваемых activity и категорий
		// и отдельно все остальные
		// Активити сортируем по параметру ord
		$top_activities = array();

		$this->db->query("
			SELECT DISTINCT
				activities.id,
				activities.name
			FROM
				products
				JOIN activities ON activities.id IN (products.activity_id, products.activity2_id, products.activity3_id)
			WHERE
				products.id IN (".$this->db->in($trips_id).")
				".($city_id ? "AND ".$city_id." IN (products.city_id, products.city_id2, products.city_id3, products.city_id4, products.city_id5, products.city_id6)" : "")."
				".($where_state ? "AND ".$where_state : "")."
			ORDER BY
				IF(activities.ord = '', 1, 0), activities.ord, activities.name",
			product_type('trip')
		);

		$counter = 0;

		while ($activity = $this->db->fetch()) {

			// Ссылка на activity
			$activity_search_link = seourl(array(
					1 			=> 'attractions',
					2 			=> 'activity-'.format_to_seourl($activity['name']),
					3 			=> ($state_seo_name ? 'state-'.$state_seo_name : null),
					4			=> ($city_seo_name ? 'city-'.$city_seo_name : null),
					'from'		=> ($this->url->get_str('from') ? format_date_for_front_search($this->url->get_str('from'), true) : null),
					'to'		=> ($this->url->get_str('to') ? format_date_for_front_search($this->url->get_str('to'), true) : null),
					'city'		=> null,
					'state'		=> null,
					'activity'	=> null,
					'type'		=> null,
					'category'	=> null,
					'page_num'	=> null,
				),
				null,
				true
			);

			if ($counter++ < $top_count) $tpl_loop = 'top_activities';
			else $tpl_loop = 'more_activities';

			$this->tpl->loop($tpl_loop, array(
				'href'		=> $activity_search_link,
				'name'		=> $activity['name'],
			));
		}


		// Список типов вида id =>  name
		$types = array_flip($this->types);

		// Выводим список типов, у которых есть хотя бы один трип с условиями поиска
		$this->db->query("
			SELECT DISTINCT
				products.type_id
			FROM
				products
			WHERE
				products.type_id <> 0
					AND
				products.id IN (".$this->db->in($trips_id).")
				".($city_id ? "AND ".$city_id." IN (products.city_id, products.city_id2, products.city_id3, products.city_id4, products.city_id5, products.city_id6)" : "")."
				".($where_state ? "AND ".$where_state : ""),
			product_type('trip')
		);

		while ($type = $this->db->fetch()) {

			// Ссылка на тип
			$type_search_link = seourl(array(
					1 			=> 'attractions',
					2 			=> 'type-'.format_to_seourl($types[$type['type_id']]),
					3 			=> ($state_seo_name ? 'state-'.$state_seo_name : null),
					4			=> ($city_seo_name ? 'city-'.$city_seo_name : null),
					'from'		=> ($this->url->get_str('from') ? format_date_for_front_search($this->url->get_str('from'), true) : null),
					'to'		=> ($this->url->get_str('to') ? format_date_for_front_search($this->url->get_str('to'), true) : null),
					'type'		=> null,
					'city'		=> null,
					'state'		=> null,
					'activity'	=> null,
					'category'	=> null,
					'page_num'	=> null,
				),
				null,
				true
			);

			$this->tpl->loop('types', array(
				'href'		=> $type_search_link,
				'name'		=> $types[$type['type_id']],
			));
		}

		// Список топ категорий
		$top_categories = array();

		$this->db->query("
			SELECT DISTINCT
				categories.id,
				categories.name
			FROM
				products
				JOIN products_categories ON products_categories.product_id = products.id
				JOIN categories ON categories.id = products_categories.category_id
			WHERE
				products.id IN (".$this->db->in($trips_id).")
				".($city_id ? "AND ".$city_id." IN (products.city_id, products.city_id2, products.city_id3, products.city_id4, products.city_id5, products.city_id6)" : "")."
				".($where_state ? "AND ".$where_state : "")."
			ORDER BY
				categories.count_searches DESC, categories.name",
			product_type('trip')
		);

		$counter = 0;

		while ($category = $this->db->fetch()) {

			// Ссылка на категорию
			$category_search_link = seourl(array(
					1 			=> 'attractions',
					2 			=> 'category-'.format_to_seourl($category['name']),
					3 			=> ($state_seo_name ? 'state-'.$state_seo_name : null),
					4			=> ($city_seo_name ? 'city-'.$city_seo_name : null),
					'from'		=> ($this->url->get_str('from') ? format_date_for_front_search($this->url->get_str('from'), true) : null),
					'to'		=> ($this->url->get_str('to') ? format_date_for_front_search($this->url->get_str('to'), true) : null),
					'state'		=> null,
					'city'		=> null,
					'category'	=> null,
					'type'		=> null,
					'activity'	=> null,
					'page_num'	=> null,
				),
				null,
				true
			);

			if ($counter++ < $top_count) $tpl_loop = 'top_categories';
			else $tpl_loop = 'more_categories';

			$this->tpl->loop($tpl_loop, array(
				'href'		=> $category_search_link,
				'name'		=> $category['name'],
			));
		}
	}

	// По основным фильтрам промо кода проверяем - может ли он быть применён к определённому трипу
	public function can_promo_be_applied($promo, $trip) {

		if (
			!$promo['product_type_id']
			||
			(
				$promo['product_type_id']
				&&
				($promo['product_type_id'] == $trip['product_type_id'])
			)
		) {

			if (
				!$promo['product_id']
				||
				(
					$promo['product_id']
					&&
					($promo['product_id'] == $trip['id'])
				)
			) {

				if (
					!$promo['partner_id']
					||
					(
						$promo['partner_id']
						&&
						($promo['partner_id'] == $trip['partner_id'])
					)
				) {

					$array_checked = false;

					if (
						$promo['activity_id']
						&&
						(
							($trip['activity_id'] && ($trip['activity_id'] == $promo['activity_id']))
							||
							($trip['activity2_id'] && ($trip['activity2_id'] == $promo['activity_id']))
							||
							($trip['activity3_id'] && ($trip['activity3_id'] == $promo['activity_id']))
						)
					) {

						$array_checked = true;
					}

					if (!$promo['activity_id'] || ($promo['activity_id'] && $array_checked)) {

						$array_checked = false;

						if (
							$promo['city_id']
							&&
							(
								($trip['city_id'] && ($trip['city_id'] == $promo['city_id']))
								||
								($trip['city_id2'] && ($trip['city_id2'] == $promo['city_id']))
								||
								($trip['city_id3'] && ($trip['city_id3'] == $promo['city_id']))
								||
								($trip['city_id4'] && ($trip['city_id4'] == $promo['city_id']))
								||
								($trip['city_id5'] && ($trip['city_id5'] == $promo['city_id']))
								||
								($trip['city_id6'] && ($trip['city_id6'] == $promo['city_id']))
							)
						) {

							$array_checked = true;
						}

						if (!$promo['city_id'] || ($promo['city_id'] && $array_checked)) {

							return true;
						}
					}
				}
			}
		}

		return false;
	}

	// Показываем информацию о трипе в поиске
	public function show_short_details($trip_id, $options = array()) {

		if (!($trip = $this->get($trip_id, false, true))) return false;

		// Load all links.
		$link_types = array('main', 'photo', 'map', 'location', 'order');
		if (!empty($trip['photo_count'])) $link_types[] = 'all_photos';
		if (!empty($trip['comment_count'])) $link_types[] = 'all_comments';
		if (!empty($trip['video_count'])) $link_types[] = 'all_videos';

		$thumb_type = (is_mobile() ? 'mob_hd' : (is_custom_template() ? 't3' : 't2'));

		$trip_links = $this->get_link($trip['id'], $link_types, array (
			'thumb_type'	=> $thumb_type,
		));

		foreach ($trip_links as $link_type => $link) {

			$trip['link_'.$link_type] = $link;
		}

		// Timthumb также перенесён в юрисдикцию альтернативного домена и является частью нашего CDN
		$mobile_trip_photo = str_replace('.com/photos/', '.com/photos/tmb/', $trip['link_photo']).'&h=500';

		$rights = $this->product->get_can($trip['id'], array('post_photo', 'post_comment', 'post_video'));

		$trip += $this->get_link($trip['id'], array_keys(array_filter($rights)));

		// Social buttons
		$trip['social_button_url'] = rawurlencode($this->config('website_url').$trip['link_main']);

		$tiket_name = null;
		$ticket_name = null;
		$price = null;

		$using_yelp = false;

		$this->add_page('partner');
		$yelp_data = $this->page_partner->get_yelp_data($trip['partner_id']);

		if (($trip['comment_count'] < 3) && $yelp_data) {

			$using_yelp = true;

			$trip['rating'] = $yelp_data['rating'];
			$trip['comment_count'] = $yelp_data['reviews'];
			$trip['rate_count'] = $yelp_data['reviews'];
		}

		$coordinates = null;
		if (
			isset($yelp_data['latitude'])
				&&
			!empty($yelp_data['latitude'])
				&&
			isset($yelp_data['longitude'])
				&&
			!empty($yelp_data['longitude'])
		) {

			$coordinates = $yelp_data['latitude'].','.$yelp_data['longitude'];
		}

		$map_location = null;
		if ($trip['address']) $map_location = $trip['address'];

		// Дополнительные фото в поиске показываем только для нового шаблона
		$sub_photos = array();
		if (is_custom_template()) {

			$sub_photos = $this->page_photo->get_list(array('product_id' => $trip['id']));
		}

		list($price, $saving) = $this->calculateMinPrice($trip, val($options, 'date_from'), val($options, 'date_to'));

		$this->tpl->loop((isset($options['prefix']) ? $options['prefix'] : '').'trip', array (
				'number'				=> (!empty($options['number']) ? $options['number'] : null),

				'id'							=> $trip['id'],

				'link_main'						=> $trip['link_main'],
				'use_calendar'					=> $trip['use_calendar'],
				'disable_booking'				=> ($trip['disable_booking'] ? true : false),

				'link_photo'					=> $trip['link_photo'],
				'is_have_sub_photos'			=> ($sub_photos ? true : false),
				'mobile_link_photo'				=> $mobile_trip_photo,
				'social_button_url'				=> (!empty($trip['social_button_url']) ? $trip['social_button_url'] : null),
				'partner_title'					=> $this->user->format_name($trip),
				'phone'							=> ($trip['phone'] ? $trip['phone'] : $trip['partner_phone']),
				'phone_client'					=> $trip['partner_phone_client'],

				'city_id'						=> $trip['city_id'],
				'address'						=> $trip['address'],
				'address_encoded'				=> urlencode($trip['address']),
				'city_name'						=> $trip['city_name'],
				'state_code'					=> $trip['state_code'],
				'coordinates'					=> urlencode($coordinates),

				'has_departure'					=> ($trip['departure'] ? true : false),
				'duration'						=> (!empty($trip['duration']) ? $trip['duration'] : null),
				'map_location'					=> $map_location,

				'rating'						=> $trip['rating'],
				'rate_count'					=> ($trip['rate_count'] ? $trip['rate_count'] : null),

				'photo_count'					=> $trip['photo_count'],
				'comment_count'					=> $trip['comment_count'],

				'link_all_photos'				=> (!empty($trip['link_all_photos']) ? $trip['link_all_photos'] : null),
				'link_all_comments'				=> (!empty($trip['link_all_comments']) ? $trip['link_all_comments'] : null),
				'link_all_videos'				=> (!empty($trip['link_all_videos']) ? $trip['link_all_videos'] : null),

				'link_post_photo'				=> (!empty($trip['post_photo']) ? $trip['post_photo'] : null),
				'link_post_comment'				=> (!empty($trip['post_comment']) ? $trip['post_comment'] : null),
				'link_post_video'				=> (!empty($trip['post_video']) ? $trip['post_video'] : null),

				'price'							=> (($price > 0) ? money(round($price), true) : null),
				'tax'							=> ($trip['tax'] ? $trip['tax'] : null),
				'saving'						=> ($saving ? money(round($saving), true) : null),
				'strike_out'					=> (($saving && ($price > 0)) ? money(round($saving+$price), true) : null),

				'special_status_text'			=> $trip['special_status']['text'],
				'special_status_text_mobile'	=> $trip['special_status']['text_mobile'],
				'show_status_banner_on_mobile'	=> $trip['special_status']['show_banner_on_mobile'],
			),
			array (
				'name'							=> ($trip['pseudo_name'] ? $trip['pseudo_name'] : $trip['name']),
				'description'					=> cut($trip['description'], 120),
				'google_data_category'			=> (!empty($trip['city_name']) ? $trip['city_name'] : 'Undefined').
					' - '.(!empty($trip['activity_name']) ? $trip['activity_name'] : 'Undefined'),
			)
		);

		if (is_page('details') && is_mobile()) $this->tpl('details_image', $trip['link_photo']);

		// Выводим звёзды рейтинга с ссылкой на ревью
		if ($trip['rating']) {

			for ($index = 0; $index < 5; $index++) {

				$star_type = 'half';
				if ($index < floor($trip['rating'])) $star_type = 'full';
				else if ($index > floor($trip['rating'])) $star_type = 'empty';

				$this->tpl->loop((isset($options['prefix']) ? $options['prefix'] : '').'trip.rating_star', array (
					'type'		=> $star_type,
				));
			}
		}

		// Для нового дизайна выводим три последних пятизвёздочных отзыва и три первые категории трипа
		// Делаем прямые запросы т.к. типовые решения (типа page_comment::get_list) слишком велики
		// А также показываем дополнительные фото
		if (is_custom_template()) {

			$this->db->query("
				SELECT
					comments.content
				FROM
					comments
					JOIN rates ON rates.id = comments.rate_id
				WHERE
					comments.target_id = ?
						AND
					comments.is_yelp = ?
						AND
					rates.value = 5
				ORDER BY
					comments.time DESC
				LIMIT 3",
				$trip['id'],
				($using_yelp ? 1 : 0)
			);
			$db_res = $this->db->result;

			while ($review = $this->db->fetch($db_res)) {

				$this->tpl->loop((isset($options['prefix']) ? $options['prefix'] : '').'trip.top_comments', array (
					'text'	=> cut($review['content'], 60),
				));
			}


			if ($trip['categories']) {

				if (count($trip['categories']) > 3) $trip['categories'] = array_slice($trip['categories'], 0, 3);

				$this->db->query("
				SELECT
					categories.*
				FROM
					categories
				WHERE
					categories.id IN (".$this->db->in($trip['categories']).")"
				);

				while ($category = $this->db->fetch()) {

					$image_link = strtolower(str_replace(array(" ","-/-","/","-&-","&"), "-", $category['name']));
					$image_link = '/img/design_2018/trip-related/categories/'.
						$category['id'].'_'.$image_link.'.png';

					$this->tpl->loop((isset($options['prefix']) ? $options['prefix'] : '').'trip.categories', array (
						'name'	=> cut($category['name'], 70),
						'image'	=> $image_link,
					));
				}
			}

			if (is_custom_template() && $sub_photos) {

				if (count($sub_photos) > 1) $photos_to_view = get_randoms_set(0, (count($sub_photos) - 1), 2);
				else $photos_to_view[0] = 0;

				foreach ($photos_to_view as $photo_index) {

					$this->tpl->loop((isset($options['prefix']) ? $options['prefix'] : '').'trip.sub_photos', array (
						'link'	=> $sub_photos[$photo_index]['link_thumb'],
					));
				}
			}
		}
	}

	public function get_short_details($trip_id, $options = array()) {

		if (!($trip = $this->get($trip_id, false, true))) return false;

		$price = null;

		$this->add_page('partner');
		$this->add_page('photo');

		$yelp_data = $this->page_partner->get_yelp_data($trip['partner_id']);

		if (($trip['comment_count'] < 3) && $yelp_data) {

			$trip['rating'] = $yelp_data['rating'];
			$trip['comment_count'] = $yelp_data['reviews'];
			$trip['rate_count'] = $yelp_data['reviews'];
		}

		list($price, $saving) = $this->calculateMinPrice($trip, val($options, 'date_from'), val($options, 'date_to'));

		$trip_details = array (
			'id'				=> $trip['id'],
			'name'				=> ($trip['pseudo_name'] ? $trip['pseudo_name'] : $trip['name']),
			'slug'				=> $trip['slug'],
			'link_main'			=> $this->product->get_main_link($trip['id']),
			'link_photo'		=> $this->page_photo->get_photo_link($trip['id'], null, 'mob_hd'),
			'rating'			=> $trip['rating'],
			'rate_count'		=> ($trip['rate_count'] ? $trip['rate_count'] : null),
			'price'				=> (($price > 0) ? trim(money(round($price), true), '$') : null),
		);

		return $trip_details;
	}

	/**
	 * @param int|array $trip
	 * @param null $dateFrom
	 * @param null $dateTo
	 * @return array
	 */
	public function calculateMinPrice($trip, $dateFrom = null, $dateTo = null)
	{
		if (is_int($trip)) {
			$trip = $this->get($trip, false, true);
		}
		if (!is_array($trip) || empty($trip)) {
			return false;
		}

		// get all available tickets for this trip
		$tripTickets = $this->get_tickets($trip['id']);

		$tripMinPrice = null;
		$price = null;

		// if trip has expired then show a default price
		if (empty($tripTickets) || (empty($dateFrom) && $trip['valid_to'] &&
			(strtotime($trip['valid_to']) < time())))
		{
			if (!empty($tripTickets) && $tripMinPrice = $this->get_trips_min_prices($trip['id'])) {
				$price = $tripMinPrice['min_price'];
			}
		}
		else {
			// search for the minimum price for the trip using the date range, if provided
			if (isset($dateFrom) && isset($dateTo)) {
				$dateFrom = format_date_for_db($dateFrom);
				$dateTo = format_date_for_db($dateTo);
			}
			else { // ... or search for the min price for the next week
				$dateFrom = format_date_for_db();
				$dateTo = format_date_for_db(strtotime('+1 week'));
			}

			// get min price
			if ($tripMinPrice = $this->get_trips_min_prices($trip['id'], $dateFrom, $dateTo)) {
				$price = $tripMinPrice['min_price'];
			}
		}

		$tuple = $this->apply_discount($trip, $price, val($tripMinPrice, 'strike_out'));

		return $tuple;
	}

	/**
	 * @param array $trip
	 * @param float $price
	 * @param float $strike_out
	 * @return array
	 */
	public function apply_discount($trip, $price, $strike_out)
	{
		// account for the strike out
		if (isset($strike_out) && ($strike_out > $price)) {
			$saving = $strike_out - $price;
		}
		else {
			$saving = 0;
		}

		// apply a promocode
		if (!is_null($this->config('active_percent_discount'))) {

			$promo = $this->config('active_percent_discount_total');

			// check if promocode can actually be applied
			if ($this->can_promo_be_applied($promo, $trip)) {
				$discount = $price * $this->config('active_percent_discount') / 100;
				$saving += $discount;
				$price -= $discount;
			}
		}

		return [$price, $saving];
	}


	// Возвращаем список необходимых ссылок
	// $link_types необходимые типы линков
	// - main
	// - photo
	// - all_comments, all_photos, all_videos
	// - post_photo, post_comment, post_video
	// - order
	// если пришла строка, то вернёт сразу ссылку, иначе массив ссылок
	// $options дополнительные опции для линков
	public function get_link($trip_id, $link_types = array(), $options = array()) {

		$types = $link_types;
		if (!is_array($link_types)) $types = array($types);

		if (!($trip = $this->get($trip_id))) return null;

		$links = array();

		// Параметры из урла
		$from = $this->url->get('from');
		$to = $this->url->get('to');

		foreach ($types as $link_type) {

			$link = null;

			switch ($link_type) {

				case 'main':

					$link = seourl(array(
							1		=> $trip['name'],
							2		=> 'details',
							3		=> $trip['id'],
							'from'	=> $from,
							'to'	=> $to,
						),
						'/'
					);

					break;

				case 'photo':

					$this->add_page('photo');

					if (!empty($options['thumb_type'])) $thumb_size_type = $options['thumb_type'];
					else if (in_array($this->config('page'), array('attractions', 'details', 'index'))) $thumb_size_type = 't2';
					else $thumb_size_type = 't1';

					$category = (($this->page_photo->main_mobile_image_exists($trip['id']) && is_mobile()) ? 'mobile' : null);

					if (is_rs_api_request()) $category = 'rs_main';

					$link = $this->page_photo->get_photo_link($trip['id'], $category, $thumb_size_type);

					break;

				case 'all_comments':

					$link = seourl(array(
							1			=> $trip['name'],
							2			=> 'details',
							3			=> $trip['id'],
							'from'		=> $from,
							'to'		=> $to,
						),
						'/'
					).'#reviews';

					break;

				case 'all_photos':

					$link = seourl(array(
							1			=> $trip['name'],
							2			=> 'details',
							3			=> $trip['id'],
							'from'		=> $from,
							'to'		=> $to,
						),
						'/'
					).'#photos';

					break;

				case 'all_videos':

					$link = seourl(array(
							1			=> $trip['name'],
							2			=> 'details',
							3			=> $trip['id'],
							'from'		=> $from,
							'to'		=> $to,
						),
						'/'
					).'#videos';

					break;

				case 'post_photo':

					$link = seourl(array('photo', 'add', $trip['id']), '/');

					break;

				case 'post_comment':

					$link = seourl(array('comment', 'add', $trip['id']), '/');

					break;

				case 'post_video':

					$link = seourl(array('video', 'add', $trip['id']), '/');

					break;

				case 'order':

					if ($trip['use_calendar']) {

						$link = seourl(array(
								1			=> $trip['name'],
								2			=> 'calendar',
								'trip'		=> $trip['id'],
								'from'		=> $from,
								'to'		=> $to,
							 ),
							'/'
						);
					}
					else {

						$link = seourl(array(
								1			=> $trip['name'],
								2			=> 'cart',
								3			=> 'create',
								'trip'		=> $trip['id'],
							 ),
							'/'
						);
					}

					break;
			}

			$links[$link_type] = $link;
		}

		return (is_array($link_types) ? $links : array_pop($links));
	}


	public function is_using_calendar($trip_id) {

		$result = false;

		if (!empty($trip_id)) {

			$this->db->query("
				SELECT
					use_calendar
				FROM
					products
				WHERE
					id = ?",
				$trip_id
			);

			if (($trip = $this->db->fetch()) && $trip['use_calendar']) $result = true;
		}

		return $result;
	}


	/**
	 * Deactivate trip for RS and TS
	 *
	 * Simply turns 'disabled' field to true
	 *
	 * @param int $trip_id
	 */
	public function deactivate($trip_id) {

		if ($trip = $this->get($trip_id)) {

			$this->db->insert_update('products', array (
				'id'				=> $trip['id'],
				'disabled'			=> true,
			));
		}
	}

	/**
	 * Completely remove trip and appropriate things
	 *
	 * Removes records from this tables
	 * - `calendar`, `calendar_ticket`
	 * - `trip_ticket`
	 * - `trip_schedule`
	 * - `comments`, `photos`, `video`
	 *
	 * and
	 * - image files ./photos/product/{trip_id}/*
	 * - search rankings positions
	 *
	 * @param int @trip_id
	 */
	public function delete($trip_id) {

		if ($trip = $this->get($trip_id)) {

			// Удаляем календарные записи и их билеты.
			$this->db->query("
				DELETE
					calendar.*,
					calendar_ticket.*
				FROM
					calendar
					LEFT JOIN calendar_ticket ON calendar_ticket.calendar_id = calendar.id
					LEFT JOIN cart_items ON cart_items.calendar_id = calendar.id
				WHERE
					calendar.product_id = ?
						AND
					cart_items.id IS NULL",
				$trip['id']
			);

			// Удаляем привязку к билетами
			$this->db->delete('trip_ticket', array (
				'trip_id'	=> $trip['id'],
			));

			// Удаляем расписания
			$this->db->delete('trip_schedule', array (
				'trip_id'		=> $trip['id'],
			));

			// Удаляем лодки
			$this->db->delete('trip_inventory', array (
				'trip_id'		=> $trip['id'],
			));

			// Удаляем категории
			$this->db->delete('products_categories', array (
				'product_id'	=> $trip['id'],
			));

			// Удаляем доп. данные
			$this->db->delete('product_data', array (
				'product_id'	=> $trip['id'],
			));

			// Удаляем спец. статусы
			$this->db->delete('special_status', array (
				'product_id'	=> $trip['id'],
			));

			// Удаляем доп. опции и их значения
			$this->db->query("
				SELECT
					trips_options.id
				FROM
					trips_options
				WHERE
					trips_options.product_id = ?",
				$trip['id']
			);
			$db_res = $this->db->result;

			while ($trip_option = $this->db->fetch($db_res)) {

				$this->db->delete('trips_options_values', array (
					'option_id'	=> $trip_option['id'],
				));
			}

			$this->db->delete('trips_options', array (
				'product_id'	=> $trip['id'],
			));



			// Комментарии/фото/видео
			$this->db->delete('comments', array (
				'target_id'		=> $trip['id'],
			));

			$this->db->delete('photos', array (
				'target_id'		=> $trip['id'],
				'content_type'	=> 'product',
			));

			$this->db->delete('video', array (
				'target_id'		=> $trip['id'],
				'content_type'	=> 'product',
			));


			// Удаляем картинки, видео и ревью
			if (file_exists($this->config('path').'/'.$this->config('dir_photos').'/'.$trip['id'].'/')) {

				remove_dir($this->config('path').'/'.$this->config('dir_photos').'/'.$trip['id'].'/');
			}

			// Удаляем положение в поиске
			$this->add_page('search');
			$this->page_search->remove_product_search_rankings($trip['id']);


			// Удаляем трип
			$this->db->delete('products', array (
				'id'			=> $trip['id'],
			));
		}
	}

	/**
	 * Completely replicate trip and appropriate properties
	 * Excluding calendar options and existing trip orders
	 *
	 * @param int @trip_id
	 *
	 * @return int New trip ID
	 */
	public function duplicate($trip_id) {

		$new_trip_id = 0;

		$this->db->query("
			SELECT
				*
			FROM
				products
			WHERE
				products.id = ?",
			$trip_id
		);
		$db_res = $this->db->result;

		// Создаём основную запись трипа на основе старой - кое что предварительно меняем
		if ($trip_fields = $this->db->fetch($db_res)) {

			$trip_fields['id'] = null;
			$trip_fields['name'] = '[replica]' . $trip_fields['name'];
			$trip_fields['rating'] = 0;
			$trip_fields['rate_count'] = 0;
			$trip_fields['comment_count'] = 0;
			$trip_fields['cnt_view'] = 0;
			$trip_fields['product_code'] = '';
			$trip_fields['valid_from'] = format_date_for_db(strtotime("-1 day"));
			$trip_fields['valid_to'] = format_date_for_db(strtotime("+1 year"));


			$new_trip_id = $this->db->insert_update('products', $trip_fields);


			// Наполняем календарь для нового трипа
			$this->add_page('utils');

			$this->page_utils->update_calendar($new_trip_id);


			// Форсим отключение трипа чтобы реплика изначально не была доступна нигде
			// Изначально этого не делаем т.к. календарь наполняется только для включённых трипов
			$trip_fields['id'] = $new_trip_id;
			$trip_fields['disabled'] = 1;
			$this->db->insert_update('products', $trip_fields);


			// Дублируем билеты
			$this->db->query("
				SELECT
					*
				FROM
					trip_ticket
				WHERE
					trip_ticket.trip_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			while ($trip_ticket = $this->db->fetch($db_res)) {

				$trip_ticket['id'] = null;
				$trip_ticket['trip_id'] = $new_trip_id;

				$this->db->insert_update('trip_ticket', $trip_ticket);
			}

			// Дублируем расписания
			$this->db->query("
				SELECT
					*
				FROM
					trip_schedule
				WHERE
					trip_schedule.trip_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			while ($trip_schedule = $this->db->fetch($db_res)) {

				$trip_schedule['id'] = null;
				$trip_schedule['trip_id'] = $new_trip_id;

				$this->db->insert_update('trip_schedule', $trip_schedule);
			}

			// Дублируем лодки
			$this->db->query("
				SELECT
					*
				FROM
					trip_inventory
				WHERE
					trip_inventory.trip_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			while ($trip_inventory = $this->db->fetch($db_res)) {

				$trip_inventory['trip_id'] = $new_trip_id;

				$this->db->insert_update_no_id('trip_inventory', $trip_inventory, array('trip_id','inventory_id'));
			}

			// Дублируем категории трипа
			$this->db->query("
				SELECT
					*
				FROM
					products_categories
				WHERE
					products_categories.product_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			while ($product_category = $this->db->fetch($db_res)) {

				$product_category['product_id'] = $new_trip_id;

				$this->db->insert_update_no_id('products_categories', $product_category, array (
					'product_id',
					'category_id'
				));
			}

			// Дублируем дополнительные данные
			$this->db->query("
				SELECT
					*
				FROM
					product_data
				WHERE
					product_data.product_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			if ($product_data = $this->db->fetch($db_res)) {

				$product_data['product_id'] = $new_trip_id;

				$this->db->insert_update_no_id('product_data', $product_data, array('product_id'));
			}

			// Дублируем дополнительные опции и их значения
			$this->db->query("
				SELECT
					*
				FROM
					trips_options
				WHERE
					trips_options.product_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			while ($trip_option = $this->db->fetch($db_res)) {

				$old_option_id = $trip_option['id'];

				$trip_option['id'] = null;
				$trip_option['product_id'] = $new_trip_id;

				$new_option_id = $this->db->insert_update('trips_options', $trip_option);

				$this->db->query("
					SELECT
						*
					FROM
						trips_options_values
					WHERE
						trips_options_values.option_id = ?",
					$old_option_id
				);
				$db_res1 = $this->db->result;

				while ($option_value = $this->db->fetch($db_res1)) {

					$option_value['id'] = null;
					$option_value['option_id'] = $new_option_id;

					$this->db->insert_update('trips_options_values', $option_value);
				}
			}

			// Дублируем специальные статусы
			$this->db->query("
				SELECT
					*
				FROM
					special_status
				WHERE
					special_status.product_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			while ($special_status = $this->db->fetch($db_res)) {

				$special_status['id'] = null;
				$special_status['product_id'] = $new_trip_id;

				$this->db->insert_update('special_status', $special_status);
			}

			// Дублируем видео и фото
			$this->db->query("
				SELECT
					*
				FROM
					video
				WHERE
					video.content_type = 'product'
						AND
					video.target_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			while ($video = $this->db->fetch($db_res)) {

				$video['id'] = null;
				$video['target_id'] = $new_trip_id;

				$this->db->insert_update('video', $video);
			}

			$this->db->query("
				SELECT
					*
				FROM
					photos
				WHERE
					photos.content_type = 'product'
						AND
					photos.target_id = ?",
				$trip_id
			);
			$db_res = $this->db->result;

			while ($photo = $this->db->fetch($db_res)) {

				$photo['id'] = null;
				$photo['target_id'] = $new_trip_id;

				$this->db->insert_update('photos', $photo);
			}

			// Физически копируем картинки
			$new_files_dir = $this->config('dir_photos').'/product/'.$new_trip_id.'/';
			mkdir($new_files_dir);

			$old_files_mask = $this->config('dir_photos').'/product/'.$trip_id.'/*';

			foreach (glob($old_files_mask, GLOB_NOSORT) as $old_file_name) {

				$old_file_name_parts = explode("/", $old_file_name);

				copy($old_file_name, $new_files_dir.array_pop($old_file_name_parts));
			}
		}

		return $new_trip_id;
	}


	public function __construct() {

		parent::__construct();

		// Подключаем страницы с необходимыми функциями
		$this->add_page('city');
		$this->add_page('activity');

		$this->id = $this->url->get_int('id');
	}


	// -------------------------------------
	//				Admin zone
	// -------------------------------------

	// Деактивация трипа
	public function mode_deactivate() {

		if (!$this->id || !$this->user->is_permitted('trip_edit')) $this->url->go(404);

		if ($trip = $this->get($this->id)) {

			$this->deactivate($trip['id']);

			$this->tpl->message_flag('info_success');
		}

		$this->url->go('/index.php?page=trip&mode=list');
	}

	// Удаление трипа
	public function mode_delete() {

		if (!$this->id || !$this->user->is_permitted('trip_edit')) $this->url->go(404);

		if ($trip = $this->get($this->id)) {

			$this->delete($trip['id']);

			$this->tpl->message_flag('info_success');
		}

		$this->url->go('/index.php?page=trip&mode=list');
	}

	// Дублирование трипа - создание полной копии
	public function mode_duplicate() {

		if (!$this->id || !$this->user->is_permitted('trip_edit')) $this->url->go(404);

		$new_trip_id = $this->duplicate($this->id);

		$this->tpl->message_flag('info_success');

		$this->url->go('/index.php?page=trip&mode=edit&id='.$new_trip_id);
	}

	// Список трипов в админке/партнёрке и поиск по нему
	public function mode_list() {

		if (!$this->user->is_permitted('trip_view')) $this->url->go(404);

		// Создаём форму для поиска трипов
		new request_trip_list(array (
			'name'		=> 'trip_list',
			'method'	=> 'get',
			'is_direct'	=> true,
			'vars'		=> array (
				'product_code,<text>',
				'activity,<select>,is_strict=false',
				'city,<select>,is_strict=false',
				'name,<text>',
				'partner,<select>,is_strict=false',
				'status,<select>,is_strict=false',
				'is_top_offer,<checkbox>',
				'page_num,<hidden>'
			),
		));
	}

	// Создание трипа
	public function mode_create() {

		if (!$this->user->is_permitted('trip_edit')) $this->url->go(404);

		$this->add_page('barcode');
		$this->add_page('city');
		$this->add_page('country');
		$this->add_page('partner');
		$this->add_page('photo');
		$this->add_page('state');

		$trip_edit = new request_trip_edit(array (
			'name'		=> 'trip_edit',
			'method'	=> 'post',
			'errors_tpl_loop_name' => 'trip_edit_errors',
			'vars'		=> array (
				'is_approved,<checkbox>',
				'disabled,<checkbox>',
				'disable_booking,<checkbox>',
				'is_top_offer,<checkbox>',
				'portal_only,<checkbox>',
				'autoconfirm_orders,<checkbox>',
				'fareharbor_id',
				'fh_negative_cutoff,<text>',
				'name,<text>,is_required=true',
				'slug,<text>'.(is_mode('edit') ? ',is_required=true' : ''),
				'pseudo_names[]',
				'description,<textarea>',
				'highlights,<textarea>',
				'expectations,<textarea>',
				'restrictions,<textarea>',
				'included,<textarea>',
				'excluded,<textarea>',
				'additional,<textarea>',
				'image,<file>',
				'mobile_image,<file>',
				'departure,<textarea>',
				'agreement,<textarea>',
				'voucher_only_text,<textarea>',
				'custom_sms_text,<text>',
				'address,<text>',
				'activity_id,<select>',
				'activity2_id,<select>',
				'activity3_id,<select>',
				'categories[]',
				'partner_id,<select>',
				'type_id,<select>',
				'tax,<text>',
				'booking_fee,<text>',
				'cart_lifetime,<text>',
				'country_id,<select>,is_strict=false',
				'country_id2,<select>,is_strict=false',
				'country_id3,<select>,is_strict=false',
				'country_id4,<select>,is_strict=false',
				'country_id5,<select>,is_strict=false',
				'country_id6,<select>,is_strict=false',
				'state_id,<select>,is_strict=false',
				'state_id2,<select>,is_strict=false',
				'state_id3,<select>,is_strict=false',
				'state_id4,<select>,is_strict=false',
				'state_id5,<select>,is_strict=false',
				'state_id6,<select>,is_strict=false',
				'city_id,<select>,is_required=true,is_strict=false',
				'city_id2,<select>,is_strict=false',
				'city_id3,<select>,is_strict=false',
				'city_id4,<select>,is_strict=false',
				'city_id5,<select>,is_strict=false',
				'city_id6,<select>,is_strict=false',
				'city_tier,<select>,is_strict=false',
				'city_tier2,<select>,is_strict=false',
				'city_tier3,<select>,is_strict=false',
				'city_tier4,<select>,is_strict=false',
				'city_tier5,<select>,is_strict=false',
				'city_tier6,<select>,is_strict=false',
				'is_evergreen,<checkbox>',
				'valid_from,<text>',
				'valid_to,<text>',
				'cut_off,<select>',
				'cut_off_time,<text>',
				'duration,<text>',
				'use_calendar,<checkbox>',
				'weekly[]',
				'monthly,<text>',
				'yearly,<text>',
				'special_statuses[]',
				'special_statuses_new[]',
				'related_products,<text>',
				'product_code,<text>',
				'need_tickets_guests,<checkbox>',
				'additional_options[]',
				'additional_options_new[]',
				'use_schedule,<checkbox>',
				'schedule_select_type,<radio>',
				'schedules[]',
				'schedules_new[]',
				'single_inventory_separation,<radio>',
				'inventories[]',
				'ticket_select_type,<radio>',
				'tickets[]',
				'tickets_new[]',
				'tickets_default',
			),
		));


		$this->tpl('trip_use_calendar', (isset($trip_edit->defaults['use_calendar']) ? 1 : 0));

		// Значения некоторых глобальных параметров для вывода плейсхолдера (если локальный параметр не указан)
		$this->tpl->batch('global', array (
			'booking_fee'	=> $this->config('booking_fee'),
			'cart_lifetime'	=> ($this->config('unpaid_cart_lifetime') ? $this->config('unpaid_cart_lifetime') : 0),
		));

		$this->tpl_raw('states', json_encode(array_merge(array(0 => '') + $this->page_state->get_list(array('with_cities' => true)))));
		$this->tpl_raw('cities', json_encode(array_merge(array(0 => '') + $this->page_city->get_list())));

		$this->tpl_raw('schedule_cut_off_hours', json_encode($this->schedule_cut_off_hour));

		$this->tpl_raw('portals', json_encode($this->portal->get_list()));

		// Список категорий
		$categories = $this->get_categories();

		$row_index = 0;
		foreach ($categories as $category_id => $category) {

			$checked = false;
			if (!empty($trip_edit->defaults['categories']) && in_array($category_id, $trip_edit->defaults['categories'])) $checked = true;

			$this->tpl->loop('category', array(), array (
				'checkbox'	=> $trip_edit->field('categories', 'checkbox', $category_id, $checked),
				'name'		=> $category['name'],
				'next_row'	=> (($row_index > 0) && ($row_index % 4 == 0) ? true : false),
			));

			$row_index++;
		}

		// Чекбоксы для дней недели
		$week_days = explode(',', $this->lang('weekdays_short'));

		foreach ($week_days as $week_day_num => $week_day) {

			$week_day_num = $week_day_num + 1;

			$checked = false;
			if (!empty($trip_edit->defaults['weekly']) && in_array($week_day_num, $trip_edit->defaults['weekly'])) $checked = true;

			$this->tpl->loop('weekly', array(), array (
				'checkbox'	=> $trip_edit->field('weekly', 'checkbox', $week_day_num, $checked),
				'name'		=> $week_day,
			));
		}

		$additional_options = array();
		$schedules = array();
		$inventories = array();
		$tickets = array();
		$special_statuses = array();

		// Только для редактирования
		if ($this->config('mode') == 'edit') {

			$special_statuses = $trip_edit->defaults['special_statuses'];

			if (!is_null($trip_edit->defaults['fareharbor_id'])) {

				$this->tpl('is_fh_trip', true);
			}
			else {

				// Inventory
				$this->add_page('inventory');
				$inventories = $this->page_inventory->get_trip_inventories($trip_edit->defaults['id']);
			}

			// Расписания переформируем, кладя в ключи время расписания чтобы не слетела сортировка
			$schedules = array();
			foreach ($trip_edit->defaults['schedules'] as $key => $schedule) $schedules[$schedule['time']] = $schedule;

			// Опции трипа
			$additional_options = $trip_edit->defaults['additional_options'];

			// Билеты трипа
			// Переформируем ключи массива, чтобы порядок не сбивался из-за численных ключей
			$tickets = array();
			// Для каждого билета проверяем наличие баркодов
			foreach	($trip_edit->defaults['tickets'] as $key => $ticket) {

				$ticket['has_barcodes'] = $this->page_barcode->has_trip_ticket_barcodes($this->id, $ticket['id']);

				$tickets[] = $ticket;
			}


			// Основная и мобильная картинки трипа, если есть
			if ($main_image = $this->page_photo->get_photo_link($this->id)) $this->tpl('main_image', $main_image);
			if ($mobile_main_image = $this->page_photo->get_photo_link($this->id, 'mobile')) {

				$this->tpl('mobile_main_image', $mobile_main_image);
			}

			$this->tpl->batch('trip', array (
				'id'				=> $trip_edit->defaults['id'],
				'use_calendar'		=> $trip_edit->defaults['use_calendar'],
				'partner_id'		=> $trip_edit->defaults['partner_id'],
				'old_link'			=> $this->get_link($trip_edit->defaults['id'], 'main'),
			));
		}

		// Опции, расписание, билеты и специальные статусы выводятся через js
		$this->tpl_raw('additional_options', json_encode($additional_options, JSON_FORCE_OBJECT));
		$this->tpl_raw('schedules', json_encode($schedules, JSON_FORCE_OBJECT));
		$this->tpl_raw('inventories', json_encode($inventories, JSON_FORCE_OBJECT));
		$this->tpl_raw('tickets', json_encode($tickets, JSON_FORCE_OBJECT));
		$this->tpl_raw('special_statuses', json_encode($special_statuses, JSON_FORCE_OBJECT));


		// Доступные для добавления inventories
		$this->add_page('inventory');
		$available_inventories = $this->page_inventory->get_list();

		$this->tpl_raw('available_inventories', json_encode($available_inventories, JSON_FORCE_OBJECT));
		if (!$available_inventories) $this->tpl('no_available_inventories', true);
	}

	// Редактирование параметров трипа
	public function mode_edit() {

		if (!$this->id || !$this->get($this->id, true, true)) $this->url->go(404);

		$this->mode_create();
	}
	
	// Возвращает ответ на ajax-запрос проверки наличия у расписания связанных заказов
	public function mode_get_schedule_orders() {

		if (!$this->user->is_permitted('trip_edit')) $this->url->go(404);
		
		$this->tpl('is_ajax', true);
		
		if ($schedule_id = $this->url->get_int('id')) {
		
			$schedule_orders = $this->get_schedule_orders($schedule_id);
			
			ajax_info(array (
				'success'			=> true,
				'orders_quantity'	=> $schedule_orders
			));
		}
		else {

			ajax_info(array (
				'success'	=> false
			));
		}
	}



	// Fareharbor

	// Набор FH-трипов, доступных аккаунту-трипшока (доступных для связывания)
	public function mode_get_fareharbor_trips() {

		if (!$this->user->is_permitted('trip_edit')) $this->url->go(404);

		// Данные для связывания с fareharbor
		require_once($this->config('path').'/inc/fareharbor.php');
		$fareharbor = new fareharbor();

		$fh_companies = $fareharbor->fh_request('companies');

		if ($fh_companies) {

			foreach ($fh_companies as $index => $fh_company) {

				$fh_company_trips = $fareharbor->fh_request('items', array('company' => $fh_company['shortname']));

				if ($fh_company_trips) {

					$fh_companies[$index]['trips'] = array();

					foreach ($fh_company_trips as $fh_company_trip) {

						$fh_companies[$index]['trips'][$fh_company_trip['pk']] = $fh_company_trip['name'];
					}
				}
			}
		}

		exit(json_encode(($fh_companies ? $fh_companies : array()), JSON_FORCE_OBJECT));
	}


	// Обрабатываем расписания и лодки для фронта, оставляя только актуальное
	public function process_front_schedules($schedules, $is_fh_trip = false) {

		// Составляем список билетов с кол-вом доступных и описанием
		$schedules_tickets = array();
		$schedules_inventories = array();
		$available_schedules = 0;

		// Определяем расписания с наиболее низкими ценами на билеты
		$is_lowest_price = false;
		$lowest_schedule_price = null;

		foreach ($schedules as $schedule) {

			$schedule_key_name = ($schedule['time'] ? $schedule['time'] : 0);

			$admin_only_schedule = false;

			// Пропускаем недоступные расписания
			if ($schedule['not_avail_ts']) {

				if (!($this->user->is_admin() && $schedule['avail_for_admin'])) continue;
				else $admin_only_schedule = true;
			}


			// Если используются лодки, то здесь проверям их влияние на доступность расписания с точки зрения
			// того, чтобы было доступно хоть что-то, а возможные проблемы при разделении билетов на лодки
			// отрабатываем уже на стороне JS
			$no_available_inventories = false;

			if (isset($schedule['inventories']) && $schedule['inventories']) {

				$schedule_inventories = array();

				foreach ($schedule['inventories'] as $inventory) {

					if (!$inventory['is_disabled'] && !$inventory['in_use']) {

						$schedule_inventories[$inventory['id']] = array (
							'id'			=> $inventory['id'],
							'avail'			=> $inventory['avail'],
							'duration'		=> $inventory['duration'],
						);
					}
				}

				if ($schedule_inventories) $schedules_inventories[$schedule_key_name] = $schedule_inventories;
				else $no_available_inventories = true;
			}


			if (!$no_available_inventories) {

				$schedule_tickets = array();
				$default_ticket = null;
				$min_price = null;
				$tickets_total_sum = 0;

				foreach ($schedule['tickets'] as $ticket_id => $ticket) {

					// Пропускаем недоступные билеты
					if (
						!empty($ticket['not_avail_ts'])
						&&
						!($ticket['avail_for_admin'] && $this->user->is_admin())
					) continue;

					$ticket_avail = min($ticket['avail_ts'], (!$this->user->is_admin() ? 30 : 300));
					$ticket_not_avail_part = $ticket_avail;

					// Билет также может не влазить ни в одну из доступных лодок (если они используются)
					if (isset($schedules_inventories[$schedule_key_name])) {

						$no_appropriate_inventory = true;

						foreach ($schedules_inventories[$schedule_key_name] as $inventory_id => $schedule_inventory) {

							if ($schedule['inventories'][$inventory_id]['tickets_avail_by_duration'][$ticket_id]) {

								$no_appropriate_inventory = false;

								$ticket_not_avail_part -= intval($schedule_inventory['avail']);

								if ($ticket_not_avail_part <= 0) break;
							}
						}

						if ($no_appropriate_inventory) continue;

						// Даже если подходящая лодка(и) нашлась, то не обязательно, что там можно разместить
						// всё доступное по расписанию и самому билету количество билетов
						if ($ticket_not_avail_part > 0) {

							if ($ticket_not_avail_part == $ticket_avail) continue;
							else $ticket_avail -= $ticket_not_avail_part;
						}
					}

					if(!$schedule['max_tickets'] && isset($ticket['total'])) {

						$tickets_total_sum += intval($ticket['total']);
					}

					if (
						!empty(intval($ticket['price']))
							&&
						(
							is_null($min_price)
								||
							(
								!is_null($min_price)
									&&
								($min_price > $ticket['price'])
							)
						)
					) {

						$min_price = $ticket['price'];
					}

					$schedule_tickets[$ticket['id']] = array (
						'id'			=> $ticket['id'],
						'name'			=> $ticket['name'],
						'name_format'	=> $ticket['name'] . ' ' . money($ticket['price'], true),
						'price'			=> $ticket['price'],
						'strike_out'	=> $ticket['strike_out'],
						'description'	=> (!empty($ticket['description']) ? $ticket['description'] : ''),
						'duration'		=> $ticket['duration'],
						'avail'			=> $ticket_avail,
						'min'			=> $ticket['min'],
					);

					// Дефолтный билет сохраняем отдельно, что поставить первым в списке
					if ($ticket['is_default']) {

						$default_ticket = $schedule_tickets[$ticket['id']];
						$default_ticket_id = $ticket['id'];
					}
				}

				// Это если из-за лодок отсеялись все билеты - чтобы не было пустых расписаний
				if (!$schedule_tickets) continue;

				// Дефолтный билет ставим первым
				if ($default_ticket) {

					if(!$is_fh_trip && $default_ticket['price']) {

						$min_price = $default_ticket['price'];
					}

					unset($schedule_tickets[$default_ticket_id]);

					$schedule_tickets = array($default_ticket_id => $default_ticket) + $schedule_tickets;
				}

				// Добавляем к ключам префикс, чтобы оставить нужный порядок и id билетов в ключах
				foreach ($schedule_tickets as $key => $schedule_ticket) {

					$schedule_tickets['id_' . $key] = $schedule_ticket;

					unset($schedule_tickets[$key]);
				}


				$schedules_tickets[$schedule_key_name] = array (
					'id'				=> $schedule['id'],
					'fh_id'				=> (!empty($schedule['fh_id']) ? $schedule['fh_id'] : 0),
					'fh_tickets_config'	=> (!empty($schedule['fh_tickets_config']) ? $schedule['fh_tickets_config'] : 0),
					'time'				=> $schedule['time'],
					'admin_only'		=> $admin_only_schedule,
					'tickets_avail'		=> $schedule['tickets_avail'],
					'tickets_total'		=> ($schedule['max_tickets']) ? (int)$schedule['max_tickets'] : $tickets_total_sum,
					'min_price'			=> $min_price,
					'has_lowest_price'	=> 0,

					'tickets' => $schedule_tickets,
				);

				if (!is_null($min_price) && is_null($lowest_schedule_price)) $lowest_schedule_price = $min_price;
				else if (!is_null($min_price) && !is_null($lowest_schedule_price)) {

					if ($min_price != $lowest_schedule_price) {

						if (!$is_lowest_price) $is_lowest_price = true;

						if ($lowest_schedule_price > $min_price) $lowest_schedule_price = $min_price;
					}
				}

				$available_schedules++;
			}
		}

		// По полученным данным ставим расписаниям маркер(ы) присутствия в них минимальной цены
		// Это нужно, чтобы выводить соответствующую индикацию в мобильной версии
		if ($available_schedules && $is_lowest_price) {

			foreach ($schedules_tickets as $key => $schedule) {

				if ($schedule['min_price'] == $lowest_schedule_price) {

					$schedule['has_lowest_price'] = 1;

					$schedules_tickets[$key] = $schedule;
				}
			}
		}

		return array (
			'schedules_tickets'		=> $schedules_tickets,
			'schedules_inventories'	=> $schedules_inventories,
			'available_schedules'	=> $available_schedules,
			'lowest_schedule_price'	=> $lowest_schedule_price,
		);
	}


	// Заглушка для invalidate_fh_cache() чтобы запрашивать его аяксом из админки
	public function mode_rebuild_fh_cache() {

		$this->config('tpl_off', true);

		if (!$this->user->is_permitted('calendar_edit')) $this->url->go(404);

		if (!($trip_id = $this->url->get_int('trip_id'))) {

			$result = false;
		}
		else {

			$result = $this->invalidate_fh_cache($trip_id, true);
		}

		ajax_info(array('success' => $result));
	}

	// Заглушка для sync_fh_schedules() чтобы запрашивать его аяксом из админки
	public function mode_sync_fh_schedules() {

		$this->config('tpl_off', true);

		if (!$this->user->is_permitted('calendar_edit')) $this->url->go(404);

		if (
			!($trip_id = $this->url->get_int('trip_id'))
				||
			!($month_count = $this->url->get_int('month_count'))
		) {

			$result = array();
		}
		else {

			$result = array();

			$new_schedules_added = $this->sync_fh_schedules (
				$trip_id,
				format_date_for_db(),
				format_date_for_db("+".$month_count." month")
			);

			if ($new_schedules_added) {

				$result = $this->get_schedules($trip_id);
			}
		}

		ajax_info(($result ? array('schedules' => $result, 'success' => true) : array('success' => false)));
	}

	// Заглушка для sync_fh_options() чтобы запрашивать его аяксом из админки
	public function mode_sync_fh_options() {

		$this->config('tpl_off', true);

		if (!$this->user->is_permitted('trip_edit')) $this->url->go(404);

		if (
			!($trip_id = $this->url->get_int('trip_id'))
				||
			!($month_count = $this->url->get_int('month_count'))
		) {

			$result = false;
		}
		else {

			$result = $this->sync_fh_options (
				$trip_id,
				format_date_for_db(),
				format_date_for_db("+".$month_count." month")
			);
		}

		ajax_info(array('success' => $result));
	}

	public function check_fh_connection($calendar_id = null, $trip_id = null) {

		$param = null;
		$where = '';

		if (!is_null($calendar_id)) {

			$where = 'calendar.id = ?';
			$param = $calendar_id;
		}
		else if (!is_null($trip_id)) {

			$where = 'products.id = ?';
			$param = $trip_id;
		}

		if (!is_null($param)) {

			$this->db->query("
				SELECT
					products.fareharbor_id
				FROM
					products
					JOIN calendar ON calendar.product_id = products.id
				WHERE
					".$where."
						AND
					products.fareharbor_id IS NOT NULL",
				$param
			);
			$db_res = $this->db->result;

			return (($result = $this->db->fetch($db_res)) ? $result['fareharbor_id'] : null);
		}

		return null;
	}

	// Аналог get_trips_tickets_rates для FH-трипов
	public function get_fh_availability($fh_trip_data, $date_from, $date_to, $trip_fh_tickets = array()) {

		$fh_trip_data_parts = explode("|", $fh_trip_data);

		$this->db->query("
			SELECT
				fh_availability_cache.cache,
				fh_availability_cache.creation_time
			FROM
				fh_availability_cache
			WHERE
				fh_id = ?
					AND
				date_from <= ?
					AND
				date_to >= ?",
			$fh_trip_data_parts[1],
			$date_from,
			$date_to
		);
		$db_res = $this->db->result;

		$cache_creation_time = null;

		if ($fh_cache = $this->db->fetch($db_res)) {

			$fh_trip_availabilities = $this->db->decode_data($fh_cache['cache']);
			$cache_creation_time = $fh_cache['creation_time'];
		}
		else {

			// Данные для связывания с fareharbor
			require_once($this->config('path') . '/inc/fareharbor.php');
			$fareharbor = new fareharbor();

			$fh_trip_availabilities = $fareharbor->fh_request('availability', array(
				'trip_data'	=> $fh_trip_data,
				'date_from'	=> $date_from,
				'date_to'	=> $date_to,
			));

			if (isset($fh_trip_availabilities['error'])) {

				$fail_message = "Error message: ".$fh_trip_availabilities['error'];

				alter_log('fh_availability_errors', "Error while getting an availability fot the FH ID ".$fh_trip_data_parts[1]."\r\n".
					$fail_message."\r\n"
				);

				$fh_trip_availabilities = null;
			}
			else {

				$fh_trip_availabilities = array_pop($fh_trip_availabilities);

				// Если кеша не было, то пишем его (но не более, чем на месяц)
				if ((strtotime($date_to) - strtotime($date_from)) < (60 * 60 * 24 * 31)) {

					$fh_trip_data_parts = explode("|", $fh_trip_data);

					$cache_data = array(
						'fh_id' => $fh_trip_data_parts[1],
						'date_from' => $date_from,
						'date_to' => $date_to,
						'creation_time' => format_date_for_db(null, true),
						'cache' => $this->db->encode_data(($fh_trip_availabilities) ? $fh_trip_availabilities : array())
					);

					$this->db->insert_update_no_id('fh_availability_cache', $cache_data, array(
						'fh_id',
						'date_from',
						'date_to'
					));
				}
			}
		}

		$result = array (
			'not_avail'			=> true,
			'not_avail_ts'		=> true,
			'avail_for_admin'	=> 0,
			'days_info'			=> array(),
		);

		$single_date = (($date_from == $date_to) ? true : false);

		if ($fh_trip_availabilities) {

			// Получаем внутренние для TS данные, связанные с трипом
			// ID календяря внутреннего трипа
			$this->db->query("
				SELECT
					calendar.id,
					calendar.date
				FROM
					calendar
					JOIN products ON products.id = calendar.product_id
				WHERE
					products.fareharbor_id = ?
						AND
					calendar.date BETWEEN ? AND ?",
				$fh_trip_data,
				$date_from,
				$date_to
			);
			$db_res = $this->db->result;
			$ts_trip_dates_ids = array();

			while ($calendar_row = $this->db->fetch($db_res)) {

				$ts_trip_dates_ids[$calendar_row['date']] = $calendar_row['id'];
			}

			// Комиссия партнёра
			$this->db->query("
				SELECT
					users.comission,
					products.id AS ts_trip_id
				FROM
					users
					JOIN products ON products.partner_id = users.id
				WHERE
					products.fareharbor_id = ?",
				$fh_trip_data
			);
			$db_res = $this->db->result;

			$general_data_row = $this->db->fetch($db_res);
			$global_comission = $general_data_row['comission'];
			$ts_trip_id = $general_data_row['ts_trip_id'];

			// Отдельные внутренние настройки только для FH-трипов
			$fh_ts_options = $this->product->get_additional_parameter($ts_trip_id, 'fh_options');
			$fh_ts_options = $this->db->decode_data($fh_ts_options);

			// TODO Использовать этот параметр ниже для соответствующей корректировки
			$negative_cutoff = (isset($fh_ts_options['fh_negative_cutoff']) ? $fh_ts_options['fh_negative_cutoff'] : 0);

			// Дефолтные билеты и расписания
			if (!$trip_fh_tickets) $trip_fh_tickets = $this->get_tickets($ts_trip_id);
			$trip_fh_schedules = $this->get_schedules($ts_trip_id);


			// Параметры билетов для конкретных дней/расписаний: определяем пока что здесь
			// т.к. пока что нет других мест, где это нужно
			$this->db->query("
				SELECT
					calendar_ticket.*,
					calendar.date
				FROM
					calendar_ticket
					JOIN calendar ON calendar.id = calendar_ticket.calendar_id
				WHERE
					calendar_id IN (".$this->db->in($ts_trip_dates_ids).")"
			);
			$db_res = $this->db->result;
			$ts_tickets_schedules = array();


			// Дополнительные опции
			$ts_additional_options = $this->get_options($ts_trip_id);
			$ts_fh_options_ids = extract_assoc_array_alter_keys($ts_additional_options, 'fh_id');

			while ($row = $this->db->fetch($db_res)) {

				if (!isset($ts_tickets_schedules[$row['date']])) $ts_tickets_schedules[$row['date']] = array();

				if (!isset($ts_tickets_schedules[$row['date']][$row['schedule_id']])) {

					$ts_tickets_schedules[$row['date']][$row['schedule_id']] = array();
				}

				$ts_tickets_schedules[$row['date']][$row['schedule_id']][$row['ticket_id']] = array (
					'id'			=> $row['ticket_id'],
					'price'			=> $row['price'],
					'strike_out'	=> $row['strike_out'],
					'comission'		=> $row['comission'],
				);
			}


			// Просматриваем текущие корзины трипшока - возможно в какой-то уже лежит некоторое кол-во билетов
			// Также, если работаем от кеша, то нужно учесть и уже заказанные на трипшоке с момента создание кеша билеты
			$tickets_in_carts = array();

			// Это то, что лежит в корзинах
			$order_where = "orders.id IS NULL";

			// А здесь добавим ещё и оформленные заказы после момента создания кеша (с не высвобожденной доступностью)
			if (!is_null($cache_creation_time)) {

				$order_where = "
					(
						(orders.id IS NULL)
							OR
						(
							orders.id IS NOT NULL
								AND
							cart_items.sold_reduced = 1
								AND
							orders.made > '".$cache_creation_time."'
						)
					)
				";
			}

			$this->db->query("
				SELECT
					cart_items.calendar_id,
					cart_items.data
				FROM
					cart_items
					LEFT JOIN orders ON orders.cart_id = cart_items.cart_id
				WHERE
					cart_items.is_fh_item = 1
						AND
					".$order_where."
						AND
					cart_items.product_id = ?",
				$ts_trip_id
			);
			$db_res = $this->db->result;

			while ($tickets_in_cart = $this->db->fetch($db_res)) {

				$cal_id = $tickets_in_cart['calendar_id'];


				$data = $this->db->decode_data($tickets_in_cart['data']);

				if (!isset($data['fh_data'])) continue;

				foreach ($data['tickets'] as $ticket) {

					$schedule_id = $data['fh_data']['fh_schedule']['id'];
					$ticket_fh_id = $data['fh_data']['fh_tickets_config'][$ticket['id']];

					if (!isset($tickets_in_carts[$cal_id])) $tickets_in_carts[$cal_id] = array();

					if (!isset($tickets_in_carts[$cal_id][$schedule_id])) {

						$tickets_in_carts[$cal_id][$schedule_id] = array();
					}

					if (!isset($tickets_in_carts[$cal_id][$schedule_id][$ticket_fh_id])) {

						$tickets_in_carts[$cal_id][$schedule_id][$ticket_fh_id] = intval($ticket['booked']);
					}
					else {

						$tickets_in_carts[$cal_id][$schedule_id][$ticket_fh_id] += intval($ticket['booked']);
					}
				}
			}


			foreach ($fh_trip_availabilities as $fh_trip_availability) {

				// Отсекаем от времени-даты из FH модификатор таймзоны чтобы расписания не менялись от летнего времени
				$fh_trip_availability['start_at'] = substr($fh_trip_availability['start_at'], 0, -5);

				$full_time = strtotime($fh_trip_availability['start_at']);

				$only_date = format_date_for_db($full_time);
				$only_time = format_time($full_time, true, false);

				// Запрос на один день, а в кеше целый месяц - игнорим все остальные дни
				if ($single_date && ($only_date != $date_from)) continue;

				// Избегаем рассинхронизации TS-FH по календарю
				if (!isset($ts_trip_dates_ids[$only_date])) continue;

				if (!isset($result['days_info'][$only_date])) {

					$result['days_info'][$only_date] = array (
						'calendar_id'			=> $ts_trip_dates_ids[$only_date],
						'date'					=> $only_date,
						'not_avail'				=> true,
						'not_avail_ts'			=> false,
						'has_avail_tickets'		=> true,
						'has_avail_schedules'	=> true,
						'avail_for_admin'		=> 1,
						'stop_sell'				=> false,
						'unavailable_schedules'	=> 0,
						'min_price'				=> null,
						'schedules'				=> array(),
						'fh_additional_options'	=> array(),
					);
				}

				// Избавляемся от ведущего нуля во времени
				$time = convert_hour(convert_hour($only_time));
				$ts_schedule_id = 0;

				foreach ($trip_fh_schedules as $trip_fh_schedule) {

					if ($trip_fh_schedule['time'] == $time) $ts_schedule_id = $trip_fh_schedule['id'];
				}

				$schedule_tickets = array();
				$fh_tickets_config = array();

				$unavailable_tickets = 0;
				$min_schedule_price = null;

				// На прошедшее время игнорируем билеты вообще - т.о. расписание точно будет недоступным
				if (
					(($full_time + ($negative_cutoff * 60)) > strtotime("now"))
						&&
					!(
						($ts_schedule_id != 0)
							&&
						$trip_fh_schedules[$ts_schedule_id]['is_disabled']
					)
				) {

					// TODO sold - поля тикетам и расписаниям не ставятся пока что (добавить если понадобится)
					foreach ($fh_trip_availability['customer_type_rates'] as $schedule_ticket) {

						if (!$schedule_ticket['capacity']) continue;

						$ts_ticket = array();

						foreach ($trip_fh_tickets as $trip_fh_ticket) {

							if ($trip_fh_ticket['fareharbor_id'] == $schedule_ticket['customer_prototype']['pk']) {

								$ts_ticket = $trip_fh_ticket;
								break;
							}
						}

						if (!$ts_ticket) continue;

						if ($ts_ticket['price'] != 0) $price = $ts_ticket['price'];
						else $price = round_price(floatval($schedule_ticket['total']) / 100);

						$comission = $global_comission;
						$strike_out = $ts_ticket['strike_out'];

						if (
							isset($ts_tickets_schedules[$only_date])
								&&
							isset($ts_tickets_schedules[$only_date][$ts_schedule_id])
								&&
							isset($ts_tickets_schedules[$only_date][$ts_schedule_id][$ts_ticket['id']])
						) {

							$current = $ts_tickets_schedules[$only_date][$ts_schedule_id][$ts_ticket['id']];

							if (!is_null($current['price'])) $price = $current['price'];
							if (!is_null($current['strike_out'])) $strike_out = $current['strike_out'];
							if (!is_null($current['comission'])) $comission = $current['comission'];
						}

						// Уменьшаем число доступных билетов, пришедшее от FH, на число таких же билетов,
						// которые уже лежат в наших пока что неоплаченных корзинах
						$avail = $schedule_ticket['capacity'];

						if (isset($tickets_in_carts[$ts_trip_dates_ids[$only_date]])) {

							$reserved_tickets = $tickets_in_carts[$ts_trip_dates_ids[$only_date]];

							if (
								isset($reserved_tickets[$fh_trip_availability['pk']])
									&&
								isset($reserved_tickets[$fh_trip_availability['pk']][$schedule_ticket['pk']])
							) {

								$avail -= $reserved_tickets[$fh_trip_availability['pk']][$schedule_ticket['pk']];

								if ($avail < 0) $avail = 0;
							}
						}

						$schedule_tickets[$ts_ticket['id']] = array(
							'id' => $ts_ticket['id'],
							'fh_id' => $schedule_ticket['customer_prototype']['pk'],
							'fh_booking_id' => $schedule_ticket['pk'],
							'name' => $ts_ticket['name'],
							'price' => $price,
							'price_rs' => 0,
							'strike_out' => $strike_out,
							'comission' => $comission,
							'wholesale' => $ts_ticket['wholesale'],
							'min' => (!$schedule_ticket['minimum_party_size'] ? 0 : $schedule_ticket['minimum_party_size']),
							'duration' => 0,
							'cut_off' => 0,
							'total' => 0,
							'total_ts' => $avail,
							'avail' => 0,
							'avail_ts' => $avail,
							'sold' => 0,
							'sold_ts' => 0,
							'is_disabled' => $ts_ticket['is_disabled_ts'],
							'is_disabled_ts' => $ts_ticket['is_disabled_ts'],
							'disable_promos' => 0,
							'is_default' => 0,
							'not_avail' => 1,
							'not_avail_ts' => (($avail && !$ts_ticket['is_disabled_ts']) ? 0 : 1),
							'not_avail_update' => 1,
							'not_avail_update_ts' => (($avail && !$ts_ticket['is_disabled_ts']) ? 0 : 1),
							'avail_for_admin' => (($avail && !$ts_ticket['is_disabled_ts']) ? 1 : 0),
						);

						if ($avail && !$ts_ticket['is_disabled_ts']) {

							if (is_null($min_schedule_price) || ($min_schedule_price > $price)) $min_schedule_price = $price;
						}
						else {

							$unavailable_tickets += 1;
						}

						$fh_tickets_config[$ts_ticket['id']] = $schedule_ticket['pk'];
					}
				}

				$tickets_is_unavailable = (count($schedule_tickets) == $unavailable_tickets);

				$result['days_info'][$only_date]['schedules'][] = array (
					'id'						=> $ts_schedule_id,
					'fh_id'						=> $fh_trip_availability['pk'],
					'fh_tickets_config'			=> $this->db->encode_data($fh_tickets_config),
					'time'						=> $time,
					'is_disabled'				=> 0,
					'min_tickets'				=> (empty($fh_trip_availability['minimum_party_size']) ? null : $fh_trip_availability['minimum_party_size']),
					'max_tickets'				=> (empty($fh_trip_availability['maximum_party_size']) ? null : $fh_trip_availability['maximum_party_size']),
					'tickets_avail'				=> $fh_trip_availability['capacity'],
					'tickets_avail_for_update'	=> $fh_trip_availability['capacity'],
					'min_price'					=> $min_schedule_price,
					'not_avail'					=> true,
					'not_avail_ts'				=> (($schedule_tickets && !$tickets_is_unavailable) ? false : true),
					'not_avail_by_close_out'	=> false,
					'has_avail_tickets'			=> (($schedule_tickets && !$tickets_is_unavailable) ? true : false),
					'sold'						=> 0,
					'cut_off_hour'				=> 0,
					'stop_sell'					=> 0,
					'avail_for_admin'			=> (($schedule_tickets && !$tickets_is_unavailable) ? 1 : 0),
					'inventories'				=> array(),
					'tickets'					=> $schedule_tickets,
				);
				if (!$schedule_tickets || $tickets_is_unavailable) {

					$result['days_info'][$only_date]['unavailable_schedules'] += 1;
				}
				if (
					!is_null($min_schedule_price)
						&&
					(
						is_null($result['days_info'][$only_date]['min_price'])
							||
						($result['days_info'][$only_date]['min_price'] > $min_schedule_price)
					)
				) {

					$result['days_info'][$only_date]['min_price'] = $min_schedule_price;
				}


				$fh_additional_options = array();
				$fh_options_config = array();

				if (isset($fh_trip_availability['custom_field_instances'])) {

					$fh_select_option_types = array (
						'extended-option',
						'yes-no',
					);

					foreach ($fh_trip_availability['custom_field_instances'] as $fh_additional_option) {

						$additional_option_data = $fh_additional_option['custom_field'];

						if (!isset($fh_additional_options[$additional_option_data['pk']])) {

							// Если у нас уже сохранена копия этой опции, то некоторые её параметры могут быть изменены
							// Далее проверяем эти параметры через isset того или иного ключа массива $internal_option
							$internal_option = array();
							if (in_array($additional_option_data['pk'], $ts_fh_options_ids)) {

								$internal_option_id = array_search($additional_option_data['pk'], $ts_fh_options_ids);
								$internal_option = $ts_additional_options[$internal_option_id];
							}

							$option_name = (($additional_option_data['name'] !== '') ? ucfirst($additional_option_data['name']) : '(Not set)');
							if (isset($internal_option['name'])) $option_name = $internal_option['name'];

							$fh_additional_options[$additional_option_data['pk']] = array (
								'id'			=> (isset($internal_option['id']) ? $internal_option['id'] : 0),
								'fh_id'			=> $additional_option_data['pk'],
								'name'			=> $option_name,
								'is_disabled'	=> (isset($internal_option['is_disabled']) ? $internal_option['is_disabled'] : 0),
								'required'		=> (!empty($additional_option_data['is_required']) ? 1 : 0),
								'type'			=> (in_array($additional_option_data['type'], $fh_select_option_types) ? 'select' : 'text'),
								'values'		=> array(),
							);

							$fh_options_config[$additional_option_data['pk']] = $additional_option_data['type'];

							if ($additional_option_data['type'] == 'yes-no') {

								$price = 0.00;

								if (
									($additional_option_data['modifier_kind'] == 'offset')
										&&
									(intval($additional_option_data['offset']) != 0)
								) {

									$price = round_price(floatval($additional_option_data['offset'])/100);
								}

								$fh_additional_options[$additional_option_data['pk']]['values'] = array (
									0	=> array (
										'id'			=> 0,
										'fh_id'			=> $additional_option_data['pk'],
										'name'			=> 'No',
										'price'			=> 0.00,
										'is_disabled'	=> 0,
										'apply_tax'		=> 0,
										'comission'		=> $global_comission,
									),
									1	=> array (
										'id'			=> 0,
										'fh_id'			=> $additional_option_data['pk'],
										'name'			=> 'Yes',
										'price'			=> $price,
										'is_disabled'	=> 0,
										'apply_tax'		=> (!empty($additional_option_data['is_taxable']) ? 1 : 0),
										'comission'		=> $global_comission,
									)
								);
							}
							else if ($additional_option_data['type'] == 'extended-option') {

								$ts_fh_values_ids = array();
								if (isset($internal_option['values']) && $internal_option['values']) {

									$ts_fh_values_ids = extract_assoc_array_alter_keys($internal_option['values'], 'fh_id');
								}

								foreach ($additional_option_data['extended_options'] as $option_value) {

									// Тот же принцип, что и с самой опцией
									$internal_value = array();
									if (in_array($option_value['pk'], $ts_fh_values_ids)) {

										$internal_value_id = array_search($option_value['pk'], $ts_fh_values_ids);
										$internal_value = $internal_option['values'][$internal_value_id];
									}

									$price = 0.00;

									if (
										($option_value['modifier_kind'] == 'offset')
											&&
										(intval($option_value['offset']) != 0)
									) {

										$price = round_price(floatval($option_value['offset'])/100);
									}

									$fh_additional_options[$additional_option_data['pk']]['values'][$option_value['pk']] = array (
										'id'			=> (isset($internal_value['id']) ? $internal_value['id'] : 0),
										'fh_id'			=> $option_value['pk'],
										'name'			=> (isset($internal_value['name']) ? $internal_value['name'] : $option_value['name']),
										'price'			=> $price,
										'is_disabled'	=> (isset($internal_value['is_disabled']) ? $internal_value['is_disabled'] : 0),
										'apply_tax'		=> (!empty($option_value['is_taxable']) ? 1 : 0),
										'comission'		=> $global_comission,
									);
								}
							}
						}
					}
				}

				if ($fh_additional_options) {

					$result['days_info'][$only_date]['fh_additional_options'] = $fh_additional_options;
				}
				$result['days_info'][$only_date]['fh_options_config'] = $fh_options_config;
			}
		}

		if ($result['days_info']) {

			$unavailable_days = 0;

			foreach ($result['days_info'] as $day => $day_data) {

				if ($day_data['unavailable_schedules'] == count($day_data['schedules'])) {

					$result['days_info'][$day]['not_avail_ts'] = true;
					$result['days_info'][$day]['has_avail_tickets'] = false;
					$result['days_info'][$day]['has_avail_schedules'] = false;
					$result['days_info'][$day]['avail_for_admin'] = 0;

					$unavailable_days += 1;
				}
			}

			if ($unavailable_days != count($result['days_info'])) {

				$result['not_avail_ts'] = false;
				$result['avail_for_admin'] = 1;
			}
		}

		return $result;
	}

	// Запрашиваем актуальные билеты FH-трипа и транслируем их во внутренние билеты
	public function sync_fh_tickets($trip_id) {

		$this->db->query("
			SELECT
				products.fareharbor_id
			FROM
				products
			WHERE
				products.id = ?
					AND
				products.fareharbor_id IS NOT NULL",
			$trip_id
		);
		$db_res = $this->db->result;

		if ($fh_trip_data = $this->db->fetch($db_res)) {

			$fh_trip_data = explode("|", $fh_trip_data['fareharbor_id']);

			require_once($this->config('path').'/inc/fareharbor.php');
			$fareharbor = new fareharbor();

			$control_tickets = array();

			if ($fh_items = $fareharbor->fh_request('items', array ('company' => $fh_trip_data[0]))) {

				if (is_array($fh_items)) {

					foreach ($fh_items as $fh_item) {

						if ($fh_item['pk'] == $fh_trip_data[1]) {

							$control_tickets = $fh_item['customer_prototypes'];
							break;
						}
					}
				}
			}

			$this->db->query("
				SELECT
					trip_ticket.id,
					trip_ticket.fareharbor_id
				FROM
					trip_ticket
				WHERE
					trip_ticket.trip_id = ?
						AND
					trip_ticket.fareharbor_id != 0",
				$trip_id
			);
			$db_res1 = $this->db->result;

			$trip_fh_tickets = array();

			while ($trip_fh_ticket = $this->db->fetch($db_res1)) {

				$trip_fh_tickets[$trip_fh_ticket['id']] = $trip_fh_ticket['fareharbor_id'];
			}


			foreach($control_tickets as $control_ticket) {

				// Пропускаем совпадающие
				if (in_array($control_ticket['pk'], $trip_fh_tickets)) {

					unset($trip_fh_tickets[array_search($control_ticket['pk'], $trip_fh_tickets)]);
				}
				// Добавляем новые
				else {

					$ticket_data = array (
						'id'			=> null,
						'trip_id'		=> $trip_id,
						'fareharbor_id'	=> $control_ticket['pk'],
						'name'			=> $control_ticket['display_name'],
						'rs_internal'	=> 0,
						'total'			=> 0,
						'total_ts'		=> 300, // Разрешённый сейчас максимум даже для админов
						'sold'			=> 0,
						'sold_ts'		=> 0,
						'price'			=> 0, // Установить тут др. значение - ответственность админа TS
						'price_rs'		=> 0,
						'strike_out'	=> 0,
						'wholesale'		=> 0,
						'min'			=> 0,
						'duration'		=> null,
						'is_default'	=> 0,
						'view_order'	=> 0,
						'description'	=> '',
					);

					$this->db->insert_update('trip_ticket', $ticket_data);
				}
			}

			// Удаляем отсутствующие
			// TODO Если это включается, то добавить защиту на случай если запрос на FH вернул пустой результат
			if ($trip_fh_tickets) {

				/*foreach ($trip_fh_tickets as $id_to_remove => $value) {

					$this->db->delete('trip_ticket', array('id' => $id_to_remove));
				}*/
			}
		}
	}

	// Запрашиваем дополнительные опции FH-трипа на определённый период (период т.к. получить эти опции вообще можно
	// только запросив доступность на определённый период) и транслируем их во внутренние комии чтобы иметь
	// возможность управлять их параметрами на нашей стороне
	public function sync_fh_options($trip_id, $date_from = null, $date_to = null) {

		if (empty($trip_id)) return false;

		$this->db->query("
			SELECT
				fareharbor_id
			FROM
				products
			WHERE
				id = ?
					AND
				fareharbor_id IS NOT NULL",
			$trip_id
		);
		if (!($fh_data = $this->db->fetch())) return false;

		if (is_null($date_from) || is_null($date_to)) $date_from = $date_to = format_date_for_db();

		$fh_availability = $this->get_fh_availability($fh_data['fareharbor_id'], $date_from, $date_to);

		$current_options = $this->get_options($trip_id);
		$current_options_ts_fh = extract_assoc_array_alter_keys($current_options, 'fh_id');

		$options_to_add = array();
		$new_option_added = false;


		if ($fh_availability['days_info']) {

			foreach ($fh_availability['days_info'] as $day) {

				if ($day['fh_additional_options']) {

					foreach ($day['fh_additional_options'] as $fh_additional_option_id => $fh_additional_option) {

						if (
							!isset($options_to_add[$fh_additional_option_id])
								&&
							!in_array($fh_additional_option_id, $current_options_ts_fh)
						) {

							$options_to_add[$fh_additional_option_id] = $fh_additional_option;
						}
					}
				}
			}

			if ($options_to_add) {

				$new_option_added = true;

				foreach ($options_to_add as $new_option_id => $new_option) {

					$option_data = array(
						'id'			=> null,
						'product_id'	=> $trip_id,
						'fareharbor_id'	=> $new_option['fh_id'],
						'name'			=> $new_option['name'],
						'is_disabled'	=> 0,
						'required'		=> $new_option['required'],
						'type'			=> $new_option['type'],
					);

					$new_option_ts_id = $this->db->insert_update('trips_options', $option_data);

					if (($new_option['type'] == 'select') && $new_option['values']) {

						foreach ($new_option['values'] as $new_option_value_id => $new_option_value) {

							$option_value_data = array(
								'id'			=> null,
								'option_id'		=> $new_option_ts_id,
								'fareharbor_id'	=> $new_option_value['fh_id'],
								'name'			=> $new_option_value['name'],
								'price'			=> $new_option_value['price'],
								'is_disabled'	=> 0,
								'apply_tax'		=> $new_option_value['apply_tax'],
							);

							$this->db->insert_update('trips_options_values', $option_value_data);
						}
					}
				}
			}
		}

		return $new_option_added;
	}

	// Запрашиваем актуальные расписания FH-трипа на определённый период и транслируем их во внутренние билеты
	// TS без привязки к датам т.к. мы их используем в календаре
	public function sync_fh_schedules($trip_id, $date_from = null, $date_to = null) {

		if (empty($trip_id)) return false;

		$this->db->query("
			SELECT
				fareharbor_id
			FROM
				products
			WHERE
				id = ?
					AND
				fareharbor_id IS NOT NULL",
			$trip_id
		);
		if (!($fh_data = $this->db->fetch())) return false;

		if (is_null($date_from) || is_null($date_to)) $date_from = $date_to = format_date_for_db();

		$fh_availability = $this->get_fh_availability($fh_data['fareharbor_id'], $date_from, $date_to);
		$current_schedules = $this->get_schedules($trip_id);

		$schedules_to_add = array();
		$new_schedule_added = false;

		foreach ($fh_availability['days_info'] as $day) {

			if (isset($day['schedules'])) {

				foreach ($day['schedules'] as $schedule) {

					if (
						!in_array($schedule['time'], $schedules_to_add)
							&&
						!isset($current_schedules[$schedule['id']])
					) {

						$schedules_to_add[] = $schedule['time'];
					}
				}
			}
		}

		if ($schedules_to_add) {

			$fh_data = explode("|", $fh_data['fareharbor_id']);
			$new_schedule_added = true;

			foreach ($schedules_to_add as $new_schedule_time) {

				$schedule_data = array (
					'id'			=> null,
					'trip_id'		=> $trip_id,
					'fareharbor_id'	=> $fh_data[1],
					'is_disabled'	=> 0,
					'time'			=> convert_hour($new_schedule_time),
					'min_tickets'	=> 0,
					'max_tickets'	=> null,
					'cut_off_hour'	=> 0,
				);

				$this->db->insert_update('trip_schedule', $schedule_data);
			}
		}

		return $new_schedule_added;
	}

	// Кешируем данные определённого трипа-периода
	public function make_fh_cache($cache_item) {

		if (
			!$cache_item
				||
			((strtotime($cache_item['date_to'])-strtotime($cache_item['date_from'])) > (60*60*24*31))
		) {

			return false;
		}

		require_once($this->config('path') . '/inc/fareharbor.php');
		$fareharbor = new fareharbor();

		$fh_trip_availabilities = $fareharbor->fh_request('availability', array(
			'trip_data'	=> $cache_item['fh_full_id'],
			'date_from'	=> $cache_item['date_from'],
			'date_to'	=> $cache_item['date_to'],
		));

		if (isset($fh_trip_availabilities['error'])) {

			$fail_message = "Error message: ".$fh_trip_availabilities['error'];

			alter_log('fh_availability_errors', "Error while getting an availability fot the FH ID ".$cache_item['fh_full_id']."\r\n".
				$fail_message."\r\n"
			);

			return false;
		}
		else {

			$fh_trip_availabilities = array_pop($fh_trip_availabilities);

			$cache_data = array(
				'fh_id' => $cache_item['fh_id'],
				'date_from' => $cache_item['date_from'],
				'date_to' => $cache_item['date_to'],
				'creation_time' => format_date_for_db(null, true),
				'cache' => $this->db->encode_data(($fh_trip_availabilities) ? $fh_trip_availabilities : array())
			);

			$this->db->insert_update_no_id('fh_availability_cache', $cache_data, array(
				'fh_id',
				'date_from',
				'date_to'
			));

			return true;
		}
	}

	// Удаляем кеш данных FH для определённого трипа (когда это нужно сделать раньше, чем cron)
	public function invalidate_fh_cache($trip_id, $rebuild_immediately = false) {

		if (empty($trip_id)) return false;

		$this->db->query("
			SELECT
				products.fareharbor_id
			FROM
				products
			WHERE
				products.id = ?
					AND
				products.fareharbor_id IS NOT NULL",
			$trip_id
		);
		$db_res = $this->db->result;

		if (!($fh_trip_data = $this->db->fetch($db_res))) return false;

		$fh_trip_id = explode("|", $fh_trip_data['fareharbor_id']);

		$this->db->query("
			SELECT
				fh_availability_cache.date_from,
				fh_availability_cache.date_to
			FROM
				fh_availability_cache
			WHERE
				fh_availability_cache.fh_id = ?",
			$fh_trip_id[1]
		);
		$db_res1 = $this->db->result;

		$was_old_cache = false;

		while ($cache_item = $this->db->fetch($db_res1)) {

			$was_old_cache = true;

			// Старый кеш в любом случае удаляется
			$this->db->query("
				DELETE FROM
					fh_availability_cache
				WHERE
					fh_availability_cache.fh_id = ?
						AND
					fh_availability_cache.date_from = ?
						AND
					fh_availability_cache.date_to = ?",
				$fh_trip_id[1],
				$cache_item['date_from'],
				$cache_item['date_to']
			);

			// Перестраиваем кеш на новый только для тех периодов, которые ещё не в прошлом
			if ($rebuild_immediately && (strtotime($cache_item['date_to']) >= strtotime("midnight"))) {

				$this->make_fh_cache(array (
					'fh_id'			=> $fh_trip_id[1],
					'fh_full_id'	=> $fh_trip_data['fareharbor_id'],
					'date_from'		=> $cache_item['date_from'],
					'date_to'		=> $cache_item['date_to'],
				));
			}
		}

		// Если новый кеш нужен, а старого ещё нет
		if ($rebuild_immediately && !$was_old_cache) {

			$this->add_page('utils');

			$this->page_utils->fh_generate_cache($trip_id);
		}

		// Отдельно проверяем билеты трипа
		if ($rebuild_immediately) {

			$this->sync_fh_tickets($trip_id);
		}

		return true;
	}




	// -------------------------------------
	//				Front-end
	// -------------------------------------

	// Форма поиска для мобильника
	public function mode_searchform() {

		$this->sidebar_search_form_render();
	}

	// Поиск в юзер части
	public function mode_search() {

		$this->tpl('is_ajax', true);

		new request_trip_search(array (
			'name'		=> 'trip_search',
			'method'	=> 'get',
			'vars'		=> array (
				'from',
				'to',
				'activity',
				'category',
				'type',
				'city',
				'state',
				'name',
				'order',
				'page_num',
			),
		));
	}

	// Календарь для трипов в юзер части
	public function mode_calendar() {

		$trip_id = $this->url->get_int('id');

		if (!is_mobile()) $this->tpl('is_ajax', true);

		$show_empty_calendar = false;

		if (
			!($trip = $this->product->get($trip_id))
				||
			!$this->is_available_front($trip)
				||
			(
				$trip['disable_booking']
					&&
				!is_portal()
			)
			) {

			if (is_mobile()) $this->url->go(404);
			else return;
		}
		else if ($trip['disable_booking'] && is_portal()) {

			// Во внешних виджетах попап всё равно должен открываться, но показывать недоступный календарь
			$show_empty_calendar = true;
		}

		$fh_data = $trip['fareharbor_id'];

		// Крошки
		$crumbs = bread_crumbs();
		$this->tpl('title', $crumbs['page_caption']);
		$this->tpl('meta_title', $crumbs['page_meta_title']);

		$this->tpl->batch(array (
			'trip_id'		=> $trip['id'],
			'trip_name'		=> (($pseudo_name = $this->product->get_product_pseudo_name($trip_id)) ? $pseudo_name : $trip['name']),
		));

		$min_date = mktime(0,0,0, date("m"), date("d"), date("Y"));
		$max_date = mktime(0,0,0, date("m"), date("d") + $this->config('calendar_user_period'), date("Y"));

		$month_time = time();
		if ($date_from = $this->url->get_str('from')) {

			$month_time = strtotime($date_from);
		}
		if ($month = $this->url->get_str('month')) {

			$month_time = strtotime('1-'.$month);

			if ($month_time < $min_date) $month_time = $min_date;
			else if ($month_time > $max_date) $month_time = $max_date - 1;
        }

		$firstday = mktime(0,0,0, date("m", $month_time), 1, date("Y", $month_time));
		$lastday = mktime(0,0,0, date("m", $month_time), date("t", $month_time), date("Y", $month_time));

		foreach (explode(',', $this->lang('weekdays_short')) as $weekday) {

			$this->tpl->loop('weekdays', array (
				'name'	=> strtoupper($weekday),
			));
		}

		$this->tpl('cur_year', date("Y", $month_time));
		$this->tpl('cur_month', date("F", $month_time));

		$calendar_grid = generate_calendar_grid($month_time, is_mobile());

		// Может пригодиться как если недоступна некая дата, так и если недоступен весь месяц
		$this->add_page('activity');
		if ($trip['activity_id']) $activity = $this->page_activity->get($trip['activity_id']);
		$this->add_page('city');
		if ($trip['city_id']) $city = $this->page_city->get($trip['city_id']);

		if (!$show_empty_calendar) {

			if (!is_null($fh_data)) {

				// Получаем информацию по каждому дню, откуда берём его доступность
				$days_data = $this->get_fh_availability(
					$fh_data,
					format_date_for_db($firstday),
					format_date_for_db($lastday)
				);

				// Отправляем флаг для передачи в mode_tickets
				$this->tpl('is_fh_trip', true);
			}
			else {

				$days_data = $this->get_trips_tickets_rates(
					$trip['id'],
					format_date_for_db($firstday),
					format_date_for_db($lastday)
				);
			}

			$cut_off_days = $this->cut_off_days($trip['cut_off'], $trip['cut_off_time']);

			$has_cut_off_days = false;

			$all_period_is_unavailable = true;

			// У партнёра могут быть целиком отключены некоторые дни
			$this->add_page('calendar');
			$partner_disabled_days = $this->page_calendar->get_days_trip_data(
				$trip['id'],
				'front_booking_disabled',
				dates_from_interval(format_date_for_db($firstday), format_date_for_db($lastday))
			);


			foreach ($calendar_grid as $date => $day_info) {

				$day_info += array(
					'calendar_id' => 0,
					'date' => $date,
					'is_avail' => false,
					'admin_cut_off' => false,
				);

				// Если есть хотя бы один не проданный билет - день доступен для заказа
				if (!empty($days_data['days_info'][$date])) {

					$day_data = $days_data['days_info'][$date];

					if (
						array_key_exists($date, $partner_disabled_days)
							&&
						!empty($partner_disabled_days[$date]['front_booking_disabled'])
					) {

						$day_info['is_avail'] = false;
					}
					else {

						$day_info['is_avail'] = !$day_data['not_avail_ts'];
					}

					$day_info['calendar_id'] = $day_data['calendar_id'];

					if (
						(
							in_array($date, $cut_off_days)
								||
							(
								$day_data['not_avail_ts']
									&&
								$day_data['avail_for_admin']
							)
						)
							&&
						empty($day_data['stop_sell'])
							&&
						$day_info['valid']
							&&
						$day_data['has_avail_schedules']
							&&
						$day_data['has_avail_tickets']
					) {

						$day_info['admin_cut_off'] = true;

						$has_cut_off_days = true;
					}

					if (
						(strtotime($date) == strtotime(date("Y-m-d")))
							&&
						!$day_info['is_avail']
							&&
						!($this->user->is_admin() && $day_info['admin_cut_off'])
					) {

						$day_info['same_day_booking_by_phone'] = true;
					}
				}

				$search_link_needed = true;
				if (strtotime($date) < strtotime(date("Y-m-d"))) {

					$day_info['is_avail'] = false;
					$search_link_needed = false;
				}


				$class = array('day');

				if (!$day_info['is_avail'] && !($this->user->is_admin() && $day_info['admin_cut_off'])) {

					$class[] = 'not_available';
					$day_info['is_avail'] = false;
				} else if ($this->user->is_admin() && $day_info['admin_cut_off']) {

					$day_info['is_avail'] = true;
					$class[] = 'cut_off';
				} else $class[] = 'is_avail';

				$day_info['class'] = implode(' ', $class);

				if (!$day_info['is_avail']) {

					if ($search_link_needed) {

						// Ссылка для поиска других трипов в этой же категории, которые доступны на этот день
						$day_info['search_link'] = $this->product->get_product_search_link('trip') .
							(isset($activity) ? 'activity-' . format_to_seourl($activity['name']) . '/' : '') .
							(isset($city) ? 'state-' . format_to_seourl($city['state_name']) . '/' : '') .
							(isset($city) ? 'city-' . format_to_seourl($city['name']) . '/' : '') .
							'?from=' . format_date_for_front_search($date, true) .
							'&to=' . format_date_for_front_search($date, true);
					}
				} else {

					$all_period_is_unavailable = false;
				}

				$this->tpl->loop('days', $day_info);
			}

			// Для дескотпа организуем выпадающий список с месяцами
			if (!is_mobile()) {

				for ($month_counter = 0; $month_counter < 12; $month_counter++) {

					$start_time = (($this->url->get_str('from')) ? strtotime($this->url->get_str('from')) : time());

					$current_month = mktime(0, 0, 0, date("m", $start_time) + $month_counter, 1, date("Y", $start_time));

					if ($current_month < $max_date) {

						$this->db->query("
						SELECT
							1
						FROM
							calendar
						WHERE
							product_id = ?
								AND
							date >= ?",
							$trip_id,
							format_date_for_db($current_month)
						);

						if ($row = $this->db->fetch()) {

							$this->tpl->loop('calendar_months', array(
								'name' => date('F', $current_month),
								'date' => date('F-Y', $current_month),
								'is_current' => ($month && !strcmp($month, date('F-Y', $current_month))),
							));
						}
					}
				}
			} // Для мобильника же оставляем ссылки вперёд/назад
			else {

				$mnum = date("m", $month_time);
				$ynum = date("Y", $month_time);
				$prev_month = mktime(0, 0, 0, $mnum, 0, $ynum);
				$next_month = mktime(0, 0, 0, $mnum + 1, 1, $ynum);

				$prevnext = array();

				$this->db->query("
					(
						SELECT
							1
					)
					UNION
					(
						SELECT
							'prev'
						FROM
							calendar
						WHERE
							product_id = ?
								AND
							date <= ?
						LIMIT 1
					)
					UNION
					(
						SELECT
							'next'
						FROM
							calendar
						WHERE
							product_id = ?
								AND
							date >= ?
					)",
					$trip_id,
					format_date_for_db($prev_month),
					$trip_id,
					format_date_for_db($next_month)
				);

				while ($row = $this->db->fetch()) $prevnext[] = $row[1];

				$prev = (in_array('prev', $prevnext) && ($prev_month > $min_date));
				$next = (in_array('next', $prevnext) && ($next_month < $max_date));

				$nav_links = array(
					'prev_month' => ($prev ? date('F', $prev_month) : null),
					'next_month' => ($next ? date('F', $next_month) : null),
					'prev_month_short' => ($prev ? date('M', $prev_month) : null),
					'next_month_short' => ($next ? date('M', $next_month) : null),
					'prev_month_date' => date('F-Y', $prev_month),
					'next_month_date' => date('F-Y', $next_month),
				);

				$this->tpl->batch($nav_links);
			}
		}
		else {

			$cut_off_days = array();

			$has_cut_off_days = false;

			$all_period_is_unavailable = true;

			foreach ($calendar_grid as $date => $day_info) {

				$day_info += array(
					'calendar_id' => 0,
					'date' => $date,
					'is_avail' => false,
					'admin_cut_off' => false,
				);

				$this->tpl->loop('days', $day_info);
			}
		}

		$start_date = date("Y-m-d");
		if ($cut_off_days) $start_date = array_pop($cut_off_days);

		if ($all_period_is_unavailable && !($this->user->is_admin() && $has_cut_off_days)) {

			$first_avail_date = ($show_empty_calendar ? null : $this->get_first_avail_date($trip_id, $start_date));

			$this->tpl->batch(array(
				'unavailable_ever'		=> is_null($first_avail_date),
				'avail_date'			=> format_time($first_avail_date, false),
				'avail_date_month'		=> date('F-Y', strtotime($first_avail_date)),
				'listing_link'			=> seourl(array (
						1	=> $trip['name'],
						2	=> 'details',
						3	=> $trip['id'],
					),
					'/'
				),
				'avail_date_link'		=> '',
				'similar_tours_link'	=> '/attractions/'.
					(isset($activity) ? 'activity-'.format_to_seourl($activity['name']).'/' : '').
					(isset($city) ? 'state-'.format_to_seourl($city['state_name']).'/' : '').
					(isset($city) ? 'city-'.format_to_seourl($city['name']).'/' : ''),
			));
		}
	}

	// Форма выбора билетов и опций для добавления в корзину
	public function mode_tickets() {

		if (!is_mobile()) $this->tpl('is_ajax', true);

		$calendar_id = $this->url->get_int('calendar_id');
		$trip_id = $this->url->get_int('id');

		$is_gift = false;

		$calendar_item = array();
		$calendar_day = array();
		$day_tickets = array();

		// Билеты и опции конкретного дня
		if ($calendar_id) {

			$is_fh_trip = $this->check_fh_connection($calendar_id);
			$is_fh_trip = (is_null($is_fh_trip) ? false : true);

			if (
				!($calendar_day = $this->get_calendar_day_data($calendar_id, null, false, $is_fh_trip))
					||
				(strtotime($calendar_day['date']) < strtotime(date("Y-m-d")))
					||
				($calendar_day['is_cut_off'] && !$this->user->is_admin())
					||
				!($trip = $this->get($calendar_day['product_id']))
				) {

				return;
			}
		}
		// Билеты и опции для трипа без календаря, либо с календарём но подарочные
		else {

			if (!($trip = $this->get($trip_id))) return;

			$is_fh_trip = false;

			$can_be_gift = ($trip['use_calendar'] ? !($this->is_price_changes_for_year($trip['id'])) : false);

			if (
				($trip['use_calendar'] && !$can_be_gift)
					||
				($trip['valid_to'] && (strtotime($trip['valid_to']) < strtotime(date("Y-m-d"))))
				) {

				return;
			}

			if ($trip['use_calendar'] && $can_be_gift) $is_gift = true;

			if (!($calendar_day = $this->get_calendar_day_data(null, $trip['id'], $is_gift))) return;
		}

		if (
			!$this->is_available_front($trip)
				||
			$trip['disable_booking']
			) {

			if (is_mobile()) $this->url->go(404);
			else return;
		}


		// Крошки
		$crumbs = bread_crumbs();
		$this->tpl('title', $crumbs['page_caption']);
		$this->tpl('meta_title', $crumbs['page_meta_title']);

		// Реквест класс тут нужен только для генерации полей
		$trip_show_tickets = new request_trip_add_to_cart(array(
			'name'		=> 'show_tickets',
			'is_direct'	=> false
		));

		$this->tpl->batch(
			array (
				'calendar_id'			=> ($calendar_day ? $calendar_day['id'] : null),
				'is_gift'				=> $is_gift,
				'trip_id'				=> $trip['id'],
				'trip_name'				=> ($trip['pseudo_name'] ? $trip['pseudo_name'] : $trip['name']),
				'trip_use_calendar'		=> $trip['use_calendar'],
				'day_date'				=> ($calendar_day ? date("M j", strtotime($calendar_day['date'])) : null),
				'day_year'				=> ($calendar_day ? date("Y", strtotime($calendar_day['date'])) : null),
				'need_tickets_guests'	=> $trip['need_tickets_guests'],
				'schedule_select_type'	=> $trip['schedule_select_type'],
				'ticket_select_type'	=> $trip['ticket_select_type'],
			),
			array (
				'trip_agreement'		=> $trip['agreement'],
			)
		);


		// Добавляем в статистику
		$this->add_page('statistic');
		$this->page_statistic->add_search_statistic(array (
			'product_type_id'	=> product_type('trip'),
			'date_from'			=> ($calendar_day ? $calendar_day['date'] : null),
			'date_to'			=> ($calendar_day ? $calendar_day['date'] : null),
			'product_id'		=> $trip['id'],
			'city_id'			=> $trip['city_id'],
		));


		// Составляем список билетов с кол-вом доступных и описанием
		$schedules_post_format = $this->process_front_schedules($calendar_day['schedules']);

		$schedules_tickets = $schedules_post_format['schedules_tickets'];
		$schedules_inventories = $schedules_post_format['schedules_inventories'];
		$available_schedules = $schedules_post_format['available_schedules'];



		if (!$available_schedules) {

			$this->tpl('booking_is_not_available', true);
		}
		else {

			// Отправляем всё в шаблон, где js выведет нужные селекты
			$this->tpl_raw('schedules_tickets', json_encode($schedules_tickets, JSON_FORCE_OBJECT));
			// TODO Это или убрать или использовать в JS для динамического изменения доступности
			$this->tpl_raw('schedules_inventories', json_encode($schedules_inventories, JSON_FORCE_OBJECT));

			// Если есть применённый к корзине промокод, то учитываем его
			if (!is_null($this->config('active_percent_discount'))) {

				$promo = $this->config('active_percent_discount_total');

				// Проверяем фильтры самого промо-кода
				if ($this->can_promo_be_applied($promo, $trip)) {

					$this->tpl('active_percent_discount', $this->config('active_percent_discount'));
				}
			}

			// Определяем в сколько колонок выводить расписания
			// Радио кнопки выводятся в две или больше (но не больше 4) колонок если кнопок больше 6
			if (count($schedules_tickets) > 6) {

				if (!is_mobile()) {

					if (count($schedules_tickets) > 24) $this->tpl('first_column', round(count($schedules_tickets) / 4) + 1);
					else $this->tpl('first_column', 6);
				}
				// В мобильнике м.б. только 2 колонки
				else {

					$this->tpl('first_column', round(count($schedules_tickets) / 2) + 1);
				}
			}

			// Для мобильника сразу выводим расписания
			if (is_mobile()) {

				if ($trip['use_calendar'] && $trip['use_schedule']) {

					foreach ($schedules_tickets as $schedule) {

						$this->tpl->loop('schedule', array (
							'id'		=> $schedule['id'],
							'time'		=> $schedule['time'],
						));
					}
				}
			}


			// Если есть доп. опции - выводим их вручную
			if ($is_fh_trip) $this->tpl("fh_options_config", $this->db->encode_data($calendar_day['fh_options_config']));

			foreach ($calendar_day['additional_options'] as $option_id => $option) {

				if ($option['is_disabled']) continue;

				if ($option['type'] == 'select') {

					// Составляем список опций для селекта из значений
					$values = array(0 => 'Select additional option...');

					foreach ($option['values'] as $value_id => $value) {

						if ($value['is_disabled']) continue;
						if (!floatval($value['price'])) {

							$values[$value_id] = $value['name'];
						}
						else {

							$values[$value_id] = $value['name'].' ('.money($value['price'], true).')';
						}
					}

					// Селект выводим только если есть хоть одно рабочее значение
					if (count($values) > 1) {

						$trip_show_tickets->lists['additional_options[' . $option_id . ']'] = $values;

						$this->tpl->loop('additional_option', array(), array(
							'name'			=> $option['name'],
							'is_select'		=> true,
							'select'		=> $trip_show_tickets->field('additional_options[' . $option_id . ']', 'select'),
							'is_required'	=> $option['required'],
						));
					}
				}

				else if ($option['type'] == 'text') {

					$this->tpl->loop('additional_option', array(), array(
						'id'			=> $option_id,
						'name'			=> $option['name'],
						'is_text'		=> true,
						'is_required'	=> $option['required'],
					));
				}
			}
		}
	}

	// Добавление выбранного в корзину
	public function mode_add_to_cart() {

		new request_trip_add_to_cart(array (
			'name'		=> 'add_to_cart',
			'method'	=> 'get',
			'vars'		=> array (
				'calendar_id',
				'trip_id',
				'fh_id',
				'fh_tickets_config',
				'fh_options_config',
				'is_gift',
				'schedule_id',
				'tickets[]',
				'additional_options[]',
				'guests[]',
				'agreement_confirm',
				'comment_guest',
			),
		));
	}

	public function mode_can_be_gift() {

		$this->tpl('is_ajax', true);

		if (!$trip_id = $this->url->get_int('trip_id')) {

			ajax_info(array('result' => false));
		}
		else {

			$result = array('result' => !($this->is_price_changes_for_year($trip_id)));

			ajax_info($result);
		}
	}
}

// Создание/редактирование трипа
class request_trip_edit extends f2b2h_request {

	protected function defaults() {

		if ($this->config('mode') == 'edit') {

			$this->page_trip->sync_fh_tickets($this->page_trip->id);

			$this->defaults = $this->page_trip->get($this->page_trip->id, true, true);

			// Доп. поля здесь нужны все
			if ($product_additional_data = $this->product->get_additional_data($this->page_trip->id)) {

				unset($product_additional_data['product_id']);

				if (isset($product_additional_data['pseudo_names'])) {

					$this->tpl_raw('trip_pseudo_names', $product_additional_data['pseudo_names']);

					unset($product_additional_data['pseudo_names']);
				}

				$product_additional_data['fh_options'] = $this->db->decode_data($product_additional_data['fh_options']);
				if ($product_additional_data['fh_options']) {

					$product_additional_data = array_merge($product_additional_data, $product_additional_data['fh_options']);
				}
				unset($product_additional_data['fh_options']);

				$this->defaults = array_merge($this->defaults, $product_additional_data);
			}

			if ($this->defaults['valid_from']) $this->defaults['valid_from'] = format_date_for_datepicker($this->defaults['valid_from']);
			if ($this->defaults['valid_to']) $this->defaults['valid_to'] = format_date_for_datepicker($this->defaults['valid_to']);

			if (is_null($this->defaults['valid_from']) && is_null($this->defaults['valid_to'])) $this->defaults['is_evergreen'] = 1;

			if (isset($this->defaults['fareharbor_id']) && !empty($this->defaults['fareharbor_id'])) {

				$fareharbor_parts = explode("|", $this->defaults['fareharbor_id']);
				$this->tpl('fareharbor_id', $fareharbor_parts[1]);
			}

			$this->defaults['is_approved'] = (($this->defaults['is_approved'] == 1) ? 0 : 1);

			$product_city_tiers = $this->page_trip->get_product_city_tier($this->page_trip->id);
			for($cid = 1; $cid <= 6; $cid++) {

				$psx = ($cid === 1) ? '' : $cid;

				if(isset($this->defaults['city_id'.$psx]) && $this->defaults['city_id'.$psx])
					$this->defaults['city_tier'.$psx] = (isset($product_city_tiers[$this->defaults['city_id'.$psx]]))
															? $product_city_tiers[$this->defaults['city_id'.$psx]]
															: 0;
			}
		}

		$this->defaults['submit'] = $this->lang('save');
	}

	protected function lists() {

		$this->add_page('activity');
		$this->lists['activity_id'] = array (0 => '') + $this->page_activity->get_formated_list();
		$this->lists['activity2_id'] = $this->lists['activity_id'];
		$this->lists['activity3_id'] = $this->lists['activity_id'];

		$this->lists['partner_id'] = $this->user->get_list(false, $this->user->trip_partner_group(), true);

		$this->lists['type_id'] = array_flip($this->page_trip->types);

		// Страна, штат и город трипа (основной город отдельно, а остальные циклом)
		$this->add_page('country');
		$this->lists['country_id'] = array(0 => '') + $this->page_country->get_list(array('with_states_and_cities' => true), false);

		$this->add_page('state');
		$this->lists['state_id'] = array(0 => '');
		if (!empty($this->defaults['country_id'])) {

			$this->lists['state_id'] = $this->page_state->get_list(array('country_id' => $this->defaults['country_id'], 'with_cities' => true), false);
		}

		$this->add_page('city');
		$this->lists['city_id'] = array(0 => 'None');
		if (!empty($this->defaults['state_id'])) $this->lists['city_id'] = $this->lists['city_id'] + $this->page_city->get_list(array('state_id' => $this->defaults['state_id']), false);

		for ($location_id = 2; $location_id <= 6; $location_id++) {

			$this->lists['country_id'.$location_id] = $this->lists['country_id'];

			$this->lists['state_id'.$location_id] = array(0 => '');
			if (!empty($this->defaults['country_id'.$location_id])) {

				$this->lists['state_id'.$location_id] = $this->page_state->get_list(array ('country_id' => $this->defaults['country_id'.$location_id], 'with_cities' => true), false);
			}

			$this->lists['city_id'.$location_id] = array(0 => 'None');
			if (!empty($this->defaults['state_id'.$location_id])) {

				$this->lists['city_id'.$location_id] = $this->lists['city_id'.$location_id] + $this->page_city->get_list(array('state_id' => $this->defaults['state_id'.$location_id]), false);
			}
		}

		$this->lists['city_tier'] = $this->page_trip->product_city_tiers;
		$this->lists['city_tier2'] = $this->page_trip->product_city_tiers;
		$this->lists['city_tier3'] = $this->page_trip->product_city_tiers;
		$this->lists['city_tier4'] = $this->page_trip->product_city_tiers;
		$this->lists['city_tier5'] = $this->page_trip->product_city_tiers;
		$this->lists['city_tier6'] = $this->page_trip->product_city_tiers;

		$this->lists['schedule_select_type'] = array (
			'radio'		=> 'Radial',
			'select'	=> 'Drop Down',
		);
		$this->lists['single_inventory_separation'] = array (
			0	=> 'Sequentially (basic separation)',
			1	=> 'By trying to place whole order into single inventory firstly',
		);
		$this->lists['ticket_select_type'] = array (
			'row'		=> 'Rows',
			'select'	=> 'Drop Down',
		);

		$this->lists['cut_off'] = $this->page_trip->cut_off;
	}


	// Единственное что не разрешаем - менять партнёра в трипе, который может быть представлен в RS
	protected function valid_partner_id($partner_id) {

		if (is_mode('create') || is_api_request()) return true;

		if ($partner_id != $this->defaults['partner_id']) {

			$this->db->query("
				SELECT
					users.is_rs_active
				FROM
					users
				WHERE
					users.id = ?",
				$this->defaults['partner_id']
			);
			$db_res = $this->db->result;

			if (($partner_flag = $this->db->fetch($db_res)) && $partner_flag['is_rs_active']) return false;
		}

		return true;
	}

	protected function valid_fh_negative_cutoff($fh_negative_cutoff) {

		if (empty($fh_negative_cutoff)) return true;

		if (!is_numeric($fh_negative_cutoff)) return false;

		// Проверили, чтобы значение было цифровым, но для страховки перед записью в БД приводим к целому
		$this->data['fh_negative_cutoff'] = intval($fh_negative_cutoff);

		return true;
	}

	protected function valid_image($image) {

		// Проверяем параметры пришедшей картинки
		if (!empty($_FILES['image']['size'])) {

			$tmp_file = null;

			$this->add_page('photo');
			$error = $this->page_photo->validate_image_upload('image', $tmp_file);

			if ($error) {

				$this->lang('trip_edit_image_error', $this->lang('trip_edit_image_error').', error: '.$error);
				return false;
			}

			$this->data['image_tmp_file'] = $tmp_file;
		}

		return true;
	}
	protected function valid_mobile_image($mobile_image) {

		if (!empty($_FILES['mobile_image']['size'])) {

			$tmp_file = null;

			$this->add_page('photo');
			$error = $this->page_photo->validate_image_upload('mobile_image', $tmp_file);

			if ($error) {

				$this->lang('trip_edit_image_error', $this->lang('trip_edit_mobile_image_error').', error: '.$error);
				return false;
			}

			$this->data['mobile_image_tmp_file'] = $tmp_file;
		}

		return true;
	}

	protected function valid_fareharbor_id($fareharbor_id) {

		if (is_null($fareharbor_id) || ($fareharbor_id == '') || ($fareharbor_id == '0')) {

			if (($fareharbor_id != '0')) unset($this->data['fareharbor_id']);
			return true;
		}
		else {

			$this->db->query("
				SELECT
					id
				FROM
					products
				WHERE
					products.fareharbor_id LIKE '%".$fareharbor_id."%'"
			);
			$db_res = $this->db->result;

			if ($this->db->fetch($db_res)) return false;

			$this->db->query("
				SELECT
					id
				FROM
					users
				WHERE
					users.is_rs_active = 1
						AND
					users.id = ?",
				$this->data['partner_id']
			);
			$db_res = $this->db->result;

			if ($this->db->fetch($db_res)) {

				$this->lang('trip_edit_fareharbor_id_error', 'Fareharbor connection is not allowed for the RS partner');
				return false;
			}
		}

		return true;
	}

	protected function valid_cart_lifetime($cart_lifetime) {

		// Будет дефолтное по сайту
		if (empty($cart_lifetime)) return true;

		return ((intval($cart_lifetime) >= 5) && (intval($cart_lifetime) <= 120));
	}
	// Это не валидируем, а просто обрезаем под размер сообщения
	protected function valid_custom_sms_text($custom_sms_text) {

		if (!empty($custom_sms_text) && (strlen($custom_sms_text) > $this->config('sms_max_len'))) {

			$this->data['custom_sms_text'] = substr($custom_sms_text, 0, ($this->config('sms_max_len') - 1));
		}

		return true;
	}

	// Проверяем псевдоимена на пустоту и заодно форматируем под запись
	protected function valid_pseudo_names($pseudo_names) {

		// Если псевдоимена переданы, то готовим их к записи
		if (!empty($pseudo_names) && $pseudo_names && isset($pseudo_names['portals'])) {

			$duplication_control = array();

			foreach ($pseudo_names['portals'] as $key => $pseudo_name) {

				if (
					empty($pseudo_name['name'])
						||
					!strcmp($pseudo_name['name'], '')
						||
					in_array($pseudo_name['name'], $duplication_control)
				) {

					return false;
				}

				$pseudo_names['portals'][$pseudo_name['self_id']] = $pseudo_name['name'];
				$duplication_control[] = $pseudo_name['name'];
				unset($pseudo_names['portals'][$key]);
			}

			$this->data['pseudo_names'] = $this->db->encode_data($pseudo_names);
		}

		return true;
	}

	// Другие проверки
	protected function valid($data) {

		// Проверяем не дублируются ли выбранные города
		$city_ids = [];
		for($cid = 1; $cid <= 6; $cid++) {

			$psx = ($cid === 1) ? '' : $cid;
			if(isset($data['city_id'.$psx]) && $data['city_id'.$psx]) {

				if(in_array($data['city_id'.$psx], $city_ids)) {

					$this->lang('trip_edit_error', 'Destinations should be unique');
					return false;

				} else $city_ids[] = $data['city_id'.$psx];
			}
		}

		// Проверяем файлы баркодов хотя бы по расширению
		if (!empty($_FILES['tickets'])) {

			foreach ($_FILES['tickets']['name'] as $original_name) {

				if (strcmp($original_name['barcodes'], '')) {

					$name_parts = explode(".", $original_name['barcodes']);

					if (strcmp("csv", array_pop($name_parts))) {

						$this->lang('trip_edit_error', 'Barcodes file have non CSV format');

						return false;
					}
				}
			}
		}

		return true;
	}



	private function save_categories($categories) {

		$this->db->delete('products_categories', array (
			'product_id'	=> $this->page_trip->id,
		));

		foreach ($categories as $category_id) {

			$trip_category = array (
				'product_id'	=> $this->page_trip->id,
				'category_id'	=> $category_id,
			);

			$this->db->insert_update_no_id(
				'products_categories',
				$trip_category,
				array('product_id', 'category_id')
			);
		}
	}

	// Добавление/обновление опции
	// возвращает id добавленной или обновлённой опции(для обновлённой просто берёт $option['id'])
	private function add_additional_option($option) {

		if (empty($option['name'])) return null;


		$option_data = array (
			'id'			=> (!empty($option['id']) ? $option['id'] : null),
			'product_id'	=> $this->page_trip->id,
			'name'			=> $option['name'],
			'is_disabled'	=> (!empty($option['is_disabled']) ? $option['is_disabled'] : 0),
			'required'		=> (!empty($option['required']) ? $option['required'] : 0),
		);

		if (array_key_exists('type', $option)) $option_data['type'] = $option['type'];
		else if (!$option_data['id']) $option_data['type'] = 'select';


		$option_id = $this->db->insert_update('trips_options', $option_data);


		return $option_id;
	}
	// Добавление/обновление значения опции
	private function add_additional_option_value($value) {

		$value_data = array (
			'id'			=> (!empty($value['id']) ? $value['id'] : null),
			'option_id'		=> $value['option_id'],
			'name'			=> (!empty($value['name']) ? $value['name'] : ''),
			'price'			=> (!empty($value['price']) ? $value['price'] : ''),
			'is_disabled'	=> (!empty($value['is_disabled']) ? $value['is_disabled'] : 0),
			'apply_tax'		=> (!empty($value['apply_tax']) ? $value['apply_tax'] : 0),
		);

		$value_id = $this->db->insert_update('trips_options_values', $value_data);
	}
	// Удаление опции (и все её значения)
	private function remove_additional_option($option_id) {

		$this->db->delete('trips_options_values', array (
			'option_id'	=> $option_id,
		));

		$this->db->delete('trips_options', array (
			'id'		=> $option_id,
		));
	}
	// Удаление значения опции
	private function remove_additional_option_value($value_id) {

		$this->db->delete('trips_options_values', array (
			'id'	=> $value_id,
		));
	}
	// Сохраняем/обновляем/удаляем доп опции и их значения
	private function save_additional_options($additional_options) {

		foreach ($additional_options as $additional_option) {

			if (!empty($additional_option['remove'])) {

				$this->remove_additional_option($additional_option['id']);
			}
			else if (!empty($additional_option['name'])) {

				$option_id = $this->add_additional_option($additional_option);

				if (!$option_id) continue;

				$option_values = array();
				if (!empty($additional_option['values'])) $option_values = array_merge($option_values, $additional_option['values']);
				if (!empty($additional_option['values_new'])) $option_values = array_merge($option_values, $additional_option['values_new']);

				foreach ($option_values as $value) {

					if (!empty($value['remove'])) {

						$this->remove_additional_option_value($value['id']);
					}
					else if (!empty($value['name'])) {

						$value['option_id'] = $option_id;
						$value['price'] = floatval($value['price']);

						$this->add_additional_option_value($value);
					}
				}
			}
		}
	}

	// Сохраняем/удаляем расписания
	private function save_schedules($schedules) {

		$schedules_times = array();

		// Для каждого имеющегося в бд, но не изменяемого в этот раз расписания,
		// берём время расписания, чтобы исключить добавление нового расписания с этим же временем
		$trip_schedules = $this->page_trip->get_schedules($this->page_trip->id);

		foreach ($trip_schedules as $schedule) $schedules_times[$schedule['time_24hour']] = $schedule['time_24hour'];

		foreach ($schedules as $key => $schedule) {

			$is_new = empty($schedule['id']);

			if (!empty($schedule['remove'])) {

				if (!isset($trip_schedules[$schedule['id']])) {

					alter_log('empty_schedules_error', "\r\n\r\n---~~~--- !!!EMPTY SCHEDULES!!! ---~~~---\r\n");
					alter_log('empty_schedules_error', "Is RS: ".(is_rs_api_request() ? "1" : "0")."\r\n");
					batch_to_alter_log('empty_schedules_error', $this->data);
				}

				unset($schedules_times[$trip_schedules[$schedule['id']]['time_24hour']]);

				continue;
			}

			if (
				(
					$is_new
						&&
					(
						!isset($schedule['time'])
							||
						!preg_match('/([1-9]|1[0-2]):([0-9][0-9])(AM|PM)/i', $schedule['time'])
					)
				)
					||
				(
					!$is_new
						&&
					empty($trip_schedules[$schedule['id']])
				)
				) {

				unset($schedules[$key]);
				continue;
			}

			// Если такое расписание уже есть - выходим с ошибкой
			if (
				$is_new
					&&
				!empty($schedules_times[convert_hour($schedule['time'])])
				) {

				return false;
			}
		}


		$removed_schedules = array();

		foreach ($schedules as $schedule) {

			if (!empty($schedule['remove'])) {

				$this->db->delete('trip_schedule', array (
					'id'	=> $schedule['id'],
				));

				$removed_schedules[] = $schedule['id'];
			}
			else {

				$schedule_max_tickets = null;

				if (
					isset($schedule['max_tickets'])
						&&
					!is_null($schedule['max_tickets'])
						&&
					($schedule['max_tickets'] != '')
				) {

					$schedule_max_tickets = $schedule['max_tickets'];
				}

				$schedule_data = array (
					'id'			=> (!empty($schedule['id']) ? $schedule['id'] : null),
					'trip_id'		=> $this->page_trip->id,
					'min_tickets'	=> (!empty($schedule['min_tickets']) ? $schedule['min_tickets'] : 0),
					'max_tickets'	=> $schedule_max_tickets,
					'cut_off_hour'	=> (!empty($schedule['cut_off_hour']) ? $schedule['cut_off_hour'] : 0),
					'is_disabled'	=> (!empty($schedule['is_disabled']) ? $schedule['is_disabled'] : 0),
				);

				if (empty($schedule['id'])) {

					$schedule_data['time'] = convert_hour($schedule['time']);
				}


				$this->db->insert_update('trip_schedule', $schedule_data);
			}
		}

		if ($removed_schedules) {

			$this->add_page('calendar');

			foreach ($removed_schedules as $removed_schedule) {

				$this->page_calendar->delete_calendar_schedule_settings($removed_schedule);
			}
		}

		return true;
	}

	// Удаляем билет
	private function remove_ticket($ticket_id) {

		$this->db->delete('trip_ticket', array (
			'id'		=> $ticket_id,
			'trip_id'	=> $this->page_trip->id,
		));

		// Удаляем связанные с билетом баркоды
		$this->add_page('barcode');
		$this->page_barcode->remove_ticket_barcodes($this->page_trip->id, $ticket_id);

		// Удаляем записи календаря
		$this->db->delete('calendar_ticket', array (
			'ticket_id'		=> $ticket_id,
		));
	}
	// Отмечаем дефолтный билет
	private function set_default_ticket($default_ticket_id) {

		// Сбрасываем флаг у всех билетов
		$this->db->query("
			UPDATE
				trip_ticket
			SET
				is_default = 0
			WHERE
				trip_id = ?",
			$this->page_trip->id
		);

		// И высставляем нужному
		$this->db->query("
			UPDATE
				trip_ticket
			SET
				is_default = 1
			WHERE
				trip_id = ?
					AND
				id = ?",
			$this->page_trip->id,
			$default_ticket_id
		);
	}
	// Сохраняем/обновляем/удаляем билеты
	private function save_tickets($tickets, $default_ticket_id = null) {

		$trip = $this->page_trip->get($this->page_trip->id, true);

		foreach ($tickets as $ticket) {

			if (!empty($ticket['id']) && !($this->page_trip->get_tickets($this->page_trip->id, $ticket['id']))) continue;

			$ticket_id = null;
			if (!empty($ticket['id'])) $ticket_id = $ticket['id'];

			if (!empty($ticket['remove'])) $this->remove_ticket($ticket_id);
			else {

				$old_data = array();
				if ($ticket_id && !empty($trip['tickets'][$ticket_id])) $old_data = $trip['tickets'][$ticket_id];

				if ((!$ticket_id || !is_api_request()) && empty($ticket['name'])) continue;


				$ticket_data = array (
					'id'			=> $ticket_id,
					'trip_id'		=> $this->page_trip->id,
				);

				$ticket_fields = array (
					'name',
					'rs_internal',
					'is_disabled_ts',
					'total',
					'total_ts',
					'price',
					'price_rs',
					'strike_out',
					'wholesale',
					'min',
					'duration',
					'view_order',
					'description',
				);

				foreach ($ticket_fields as $field) {

					if (isset($ticket[$field])) $ticket_data[$field] = $ticket[$field];
				}

				if (isset($ticket_data['price_rs']) && empty($ticket_data['price'])) {

					$ticket_data['price'] = $ticket_data['price_rs'];
				}

				if (
					isset($ticket_data['total_ts'])
						&&
					(
						(empty($ticket_data['total']) && (!$old_data || ($old_data['total'] < $ticket_data['total_ts'])))
							||
						(!empty($ticket_data['total']) && ($ticket_data['total'] < $ticket_data['total_ts']))
					)
					) {

					$ticket_data['total'] = $ticket_data['total_ts'];
				}

				if (isset($ticket_data['duration']) && ($ticket_data['duration'] == '')) {

					$ticket_data['duration'] = null;
				}

				$this->db->insert_update('trip_ticket', $ticket_data);
			}

			if (!empty($ticket['remove_barcodes'])) $this->page_barcode->remove_ticket_barcodes($this->page_trip->id, $ticket_id);
		}

		// Отмечаем дефолтный билет
		if ($default_ticket_id) $this->set_default_ticket($default_ticket_id);

		// Сохраняем баркоды из присланных файлов
		if (!empty($_FILES['tickets'])) $this->page_barcode->save_tickets_barcodes($this->page_trip->id, $_FILES['tickets']);
	}

	// Сохраняем/удаляем inventory
	private function save_inventories($inventories) {

		$this->add_page('inventory');

		foreach ($inventories as $inventory) {

			if (empty($inventory['id'])) continue;

			// Удаляем
			if (!empty($inventory['remove'])) {

				$this->page_inventory->remove_trip($inventory['id'], $this->page_trip->id);
			}
			// Добавляем/редактируем
			else {

				$this->page_inventory->add_trip(
					$inventory['id'],
					$this->page_trip->id,
					$inventory
				);
			}
		}
	}

	protected function success($data) {

		$trip_data = array (
			'id'					=> $this->page_trip->id,
			'product_type_id'		=> product_type('trip'),
		);


		if (array_key_exists('use_calendar', $data) && !$data['use_calendar']) {

			$trip_data['weekly'] = implode(',', array(1, 2, 3, 4, 5, 6, 7));
			$trip_data['monthly'] = '';
			$trip_data['yearly'] = '';

			$data['use_schedule'] = 0;
		}
		else {

			if (array_key_exists('yearly', $data)) {

				$year_days = explode(',', $data['yearly']);

				foreach ($year_days as $key => $month_day) {

					$month_day = explode('-', $month_day);

					if (
						(count($month_day) != 2)
							||
						(intval($month_day[0]) > 12) || (intval($month_day[0]) < 1)
							||
						(intval($month_day[1]) > 31) || (intval($month_day[1]) < 1)
						) {

						unset($year_days[$key]);
						continue;
					}

					$month_day[0] = intval($month_day[0]);
					$month_day[1] = intval($month_day[1]);

					$year_days[$key] = implode('-', $month_day);
				}

				$trip_data['yearly'] = implode(',', $year_days);
			}
			if (array_key_exists('monthly', $data)) {

				$month_days = explode(',', $data['monthly']);

				foreach ($month_days as $key => $day) {

					$day = intval($day);
					if (($day > 31) || ($day < 1)) {

						unset($month_days[$key]);
						continue;
					}

					$month_days[$key] = $day;
				}

				$trip_data['monthly'] = implode(',', $month_days);
			}
			if (array_key_exists('weekly', $data)) {

				$trip_data['weekly'] = implode(',', array_keys($data['weekly']));
			}
		}


		$available_fields = array (
			'fareharbor_id',
			'disabled',
			'is_approved',
			'slug',
			'disable_booking',
			'is_disabled_online',
			'is_disabled_ts',
			'is_delayed',
			'portal_only',
			'is_top_offer',
			'autoconfirm_orders',
			'use_schedule',
			'name',
			'description',
			'highlights',
			'expectations',
			'restrictions',
			'included',
			'excluded',
			'additional',
			'departure',
			'agreement',
			'agreement_rs',
			'voucher_only_text',
			'address',
			'activity_id',
			'activity2_id',
			'activity3_id',
			'partner_id',
			'type_id',
			'tax',
			'booking_fee',
			'rs_booking_fee',
			'city_id',
			'city_id2',
			'city_id3',
			'city_id4',
			'city_id5',
			'city_id6',
			'valid_from',
			'valid_to',
			'cut_off',
			'cut_off_time',
			'duration',
			'close_out',
			'use_calendar',
			'related_products',
			'product_code',
			'need_tickets_guests',
			'schedule_select_type',
			'ticket_select_type',
			'data',
		);

		foreach ($available_fields as $field) {

			if (isset($data[$field])) {

				$trip_data[$field] = str_replace("\r\n", "\n", $data[$field]);
			}
		}


		// Значения, которые нужно записать как null
		if (isset($trip_data['booking_fee']) && ($trip_data['booking_fee'] == '')) $trip_data['booking_fee'] = null;
		if (isset($trip_data['rs_booking_fee']) && ($trip_data['rs_booking_fee'] == '')) $trip_data['rs_booking_fee'] = null;
		if (isset($trip_data['close_out']) && ($trip_data['close_out'] == '')) $trip_data['close_out'] = null;

		if (!is_api_request()) {

			$this->add_page('api_global');
			if (empty($trip_data['slug'])) {

				$trip_data['slug'] = $this->page_api_global->clear_url(format_to_seourl($trip_data['name']), false);
			}
			else {

				$trip_data['slug'] = $this->page_api_global->clear_url(format_to_seourl($trip_data['slug']), false);
			}
		}

		if (!is_api_request()) {

			$text_data_array['text'] = array(
				'name' => "",
				'description' => "",
				'departure' => "",
				'highlights' => "",
				'expectations' => "",
				'restrictions' => "",
				'included' => "",
				'excluded' => "",
				'additional' => "",
				'agreement' => "",
				'voucher_only_text' => ""
			);

			// Преобразуем текст в html с Markdown
			foreach ($text_data_array['text'] as $field => &$val ) {

				$value = (isset($trip_data[$field])) ? $trip_data[$field] : $this->defaults[$field];
				$value = parsedown($value);

				if(is_null($value)) $value = '';

				$val = $value;
			}

			$trip_data['data'] = $this->db->encode_data($text_data_array);
		}

		// Соединён ли трип с Fareharbor (сейчас или ранее)
		$is_fh_trip = false;
		$fh_trip_disconnected = false;
		// Но не может быть никаких действий, связанных с появлением или поддержкой
		// связи с FH при работе из RS
		if (!is_rs_api_request()) {

			if (
				(
					isset($trip_data['fareharbor_id'])
						&&
					!is_null($trip_data['fareharbor_id'])
				)
				||
				(
					!isset($trip_data['fareharbor_id'])
						&&
					isset($this->defaults['fareharbor_id'])
						&&
					!is_null($this->defaults['fareharbor_id'])
				)
			) {

				$is_fh_trip = true;
			}

			// Если соединение с FH меняется или отменяется вообще, то удаляем созданные специально под FH трип билеты
			// а также расписания и дополнительные опции
			if (
				isset($this->defaults['fareharbor_id'])
					&&
				!is_null($this->defaults['fareharbor_id'])
					&&
				(
					(
						$is_fh_trip
							&&
						isset($trip_data['fareharbor_id'])
							&&
						strcmp($trip_data['fareharbor_id'], $this->defaults['fareharbor_id'])
					)
						||
					!$is_fh_trip
				)
			) {

				$fh_trip_disconnected = true;

				$this->page_trip->invalidate_fh_cache($this->page_trip->id);

				// Билеты
				$this->db->query("
					DELETE FROM
						trip_ticket
					WHERE
						trip_ticket.fareharbor_id != 0
							AND
						trip_ticket.trip_id = ?",
					$this->page_trip->id
				);

				// Расписания
				$this->db->query("
					DELETE FROM
						trip_schedule
					WHERE
						trip_schedule.fareharbor_id != 0
							AND
						trip_schedule.trip_id = ?",
					$this->page_trip->id
				);

				// Значения опций
				$this->db->query("
					SELECT
						trips_options_values.id
					FROM
						trips_options_values
						JOIN trips_options ON trips_options.id = trips_options_values.option_id
					WHERE
						trips_options.fareharbor_id != 0
							AND
						trips_options.product_id = ?",
					$this->page_trip->id
				);
				$db_values_res = $this->db->result;

				$values_to_delete = array();
				while ($value = $this->db->fetch($db_values_res)) {

					$values_to_delete[] = $value['id'];
				}

				if ($values_to_delete) {

					$this->db->query("
						DELETE FROM
							trips_options_values
						WHERE
							trips_options_values.id IN (".$this->db->in($values_to_delete).")"
					);
				}

				// Сами опции
				$this->db->query("
					DELETE FROM
						trips_options
					WHERE
						trips_options.fareharbor_id != 0
							AND
						trips_options.product_id = ?",
					$this->page_trip->id
				);
			}
		}

		// Это здесь чтобы проверки выше сработали
		if (isset($trip_data['fareharbor_id']) && empty($trip_data['fareharbor_id'])) $trip_data['fareharbor_id'] = null;


		if (is_mode('edit') && ($data['partner_id'] != $this->defaults['partner_id'])) {

			$this->db->query("
				DELETE FROM
					comments
				WHERE
					comments.target_id = ?
						AND
					comments.is_yelp = 1",
				$trip_data['id']
			);
		}


		// Приводим поля с датами к нужному виду
		if (!empty($trip_data['valid_from'])) $trip_data['valid_from'] = format_date_for_db($trip_data['valid_from']);
		else $trip_data['valid_from'] = null;

		if (!empty($trip_data['valid_to'])) $trip_data['valid_to'] = format_date_for_db($trip_data['valid_to']);
		else $trip_data['valid_to'] = null;

		// Этот флаг записыаем наоборот из-за более удобного названия в интерфейсе
		if (!is_api_request()) {

			$trip_data['is_approved'] = ((isset($trip_data['is_approved']) && $trip_data['is_approved'] == 1) ? 0 : 1);
		}

		// У FH-трипа всегда есть календарь и всегда есть расписания
		if ($is_fh_trip) {

			$trip_data['use_schedule'] = 1;
			$trip_data['use_calendar'] = 1;
		}


		$fields_to_save = array();
		$fields_difference_to_approve = array();

		if (!is_api_request()) {

			$fields_to_save = $trip_data;
		}
		else {

			$this->add_page('partner');
			$partner = $this->page_partner->get($data['partner_id']);

			if ($partner['rs_only'] || !$trip_data['id']) {

				$fields_to_save = $trip_data;

				// Если это новый трип из RS, то preview mode не может быть
				if (!$trip_data['id']) $fields_to_save['is_approved'] = 1;

				// Если это новый трип из RS, то массив аппрувов будет пустым и в RS трип сразу заработает,
				// но нужно сделать так, чтобы на TS трип появился только по аппруву - для этого форсим флаг
				// disabled и ставим на аппрув его отмену (только в том случае, если он и так не установлен)
				if (
					!$partner['rs_only']
					&&
					(
						!isset($trip_data['disabled'])
						||
						(
							isset($trip_data['disabled'])
							&&
							!$trip_data['disabled']
						)
					)
				) {

					$fields_to_save['disabled'] = 1;
					$fields_difference_to_approve['disabled'] = array (
						'old_value'		=> 1,
						'new_value'		=> 0,
					);
				}
			}
			else if ($trip_data['id']) {

				$non_approve_fields = array(
					'is_delayed',
					'close_out',
					'is_disabled_online',
					'portal_only',
					'is_top_offer',
					'use_schedule',
					'agreement_rs',
					'voucher_only_text',
					'activity_id',
					'activity2_id',
					'activity3_id',
					'partner_id',
					'type_id',
					'booking_fee',
					'rs_booking_fee',
					'city_id',
					'city_id2',
					'city_id3',
					'city_id4',
					'city_id5',
					'city_id6',
					'valid_from',
					'valid_to',
					'weekly',
					'monthly',
					'yearly',
					'cut_off',
					'cut_off_time',
					'duration',
					'use_calendar',
					'related_products',
					'product_code',
					'need_tickets_guests',
					'schedule_select_type',
					'ticket_select_type',
				);

				foreach ($trip_data as $field => $value) {

					if (in_array($field, $non_approve_fields)) {

						$fields_to_save[$field] = $value;

						unset($trip_data[$field]);
					}
				}


				$trip_old = $this->page_trip->get($trip_data['id']);
				$trip_old['weekly'] = implode(',', $trip_old['weekly']);

				$fields_difference_to_approve = get_arrays_difference($trip_old, $trip_data);
			}
		}

		// Для апи запроса полные изменения в бд вносим только при создании
		// при редактировании изменения применятся частично, а остальные - только при их аппруве в админке
		if ($fields_to_save) {

			if ($trip_data['id']) $fields_to_save['id'] = $trip_data['id'];

			$this->page_trip->id = $this->db->insert_update('products', $fields_to_save);

			if (!is_api_request()) {

				$cities_tiers = [];
				for ($cid = 1; $cid <= 6; $cid++) {

					$psx = ($cid === 1) ? '' : $cid;

					if (isset($fields_to_save['city_id' . $psx]) && $fields_to_save['city_id' . $psx])
						$cities_tiers[$fields_to_save['city_id' . $psx]] = $data['city_tier' . $psx];
				}

				$this->db->query("
					DELETE FROM 
						products_cities_tier
					WHERE
						products_cities_tier.partner_id = ?
							AND 
						products_cities_tier.trip_id = ?",
					$trip_data['partner_id'],
					$trip_data['id']
				);

				if ($cities_tiers) {

					$values = [];
					foreach ($cities_tiers as $city_id => $tier) {

						$values[] = '(' . $trip_data['partner_id'] . ',' . $trip_data['id'] . ',' . $city_id . ',' . $tier . ')';
					}

					$this->db->query("
						INSERT INTO
							products_cities_tier
							(partner_id, trip_id, city_id, tier)
						VALUES " . rtrim(implode(',', $values), ',')
					);
				}
			}
		}

		// Добавляем в очередь на аппрув изменений
		if (is_api_request() && $fields_difference_to_approve) {

			$stored_data = array();
			if ($fields_difference_to_approve) $stored_data['fields_difference'] = $fields_difference_to_approve;

			$this->add_page('reservation_system');
			$this->page_reservation_system->add_to_queue(
				'trip',
				$this->page_trip->id,
				// Событие 'create' отключено пока что т.к. его deny приводит к удалению трипа
				//($trip_data['id'] ? 'edit' : 'create'),
				'edit',
				$data['partner_id'],
				$stored_data
			);
		}


		// Сохраняем в отдельную таблицу дополнительные поля
		$additional_fields_to_save = array (
			'product_id'	=> $this->page_trip->id,
		);

		$available_additional_fields = array (
			'cart_lifetime'					=> null,
			'single_inventory_separation'	=> 0,
			'custom_sms_text'				=> null,
			'rs_calendar_custom_text'		=> null,
			'pseudo_names'					=> null,
		);

		foreach ($available_additional_fields as $field => $default_value) {

			if (isset($data[$field])) {

				$additional_fields_to_save[$field] = str_replace("\r\n", "\n", $data[$field]);
			}

			if (empty($additional_fields_to_save[$field])) $additional_fields_to_save[$field] = $default_value;
		}

		// TODO После деплоя приоритетной сепарации в одну лодку посмотреть, можно ли это доработать
		// Обрабатываем поля настроек, необходимых только для FH
		$fh_only_fields = array (
			'fh_negative_cutoff',
		);
		$fh_options = array();

		foreach ($fh_only_fields as $fh_field) {

			if (isset($data[$fh_field]) && !empty($data[$fh_field])) {

				$fh_options[$fh_field] = $data[$fh_field];
			}
		}
		$additional_fields_to_save['fh_options'] = $this->db->encode_data($fh_options);

		$this->db->insert_update_no_id('product_data', $additional_fields_to_save, array('product_id'));



		// Обновляем данные для поиска
		$this->add_page('search');
		$this->page_search->update_product_search_data($this->page_trip->id);

		// Обновляем записи календаря
		$this->add_page('utils');
		$this->page_utils->update_calendar($this->page_trip->id);

		$this->add_page('photo');

		// Обновляем картинку
		if (!empty($this->data['image_tmp_file']) && file_exists($this->data['image_tmp_file'])) {

			if (!is_rs_api_request()) {

				$this->page_photo->delete_thumbs($this->page_trip->id);
				$this->page_photo->store_photo($this->data['image_tmp_file'], $this->page_trip->id);
			}
			else {

				$this->page_photo->delete_thumbs($this->page_trip->id, 'rs_main');
				$this->page_photo->store_photo($this->data['image_tmp_file'], $this->page_trip->id, 'rs_main');

				// Для RS обязательно нужен тамб t3 главной картинки
				$this->page_photo->get_photo_link($this->page_trip->id, 'rs_main', 't3');

				$translate_rs_photo_to_ts = false;

				if (!is_mode('create_trip')) {

					$this->db->query("
						SELECT
							users.rs_only
						FROM
							users
						WHERE
							users.id = ?",
						$data['partner_id']
					);
					$db_res = $this->db->result;

					if ($user_data = $this->db->fetch($db_res))  {

						if (!$user_data['rs_only']) {

							$this->add_page('reservation_system');

							$this->page_reservation_system->add_to_queue(
								'photo',
								$this->page_trip->id,
								'create',
								$data['partner_id'],
								array(
									'fields_difference' => array()
								)
							);
						}
						else {

							// Дублируем это же фото для TS на случай если потом галочку rs_only снимут
							$translate_rs_photo_to_ts  = true;
						}
					}
				}
				else {

					// Когда же трип создаётся из RS, то полюбому дублируем фото и на TS-версию
					$translate_rs_photo_to_ts = true;
				}

				if ($translate_rs_photo_to_ts) {

					$target_path = $this->config('dir_photos').'/product/'.$this->page_trip->id.'/';

					$start_file = $target_path.'rs_main.jpg';
					$end_file = $target_path.'main.jpg';

					if (file_exists($start_file)) {

						$this->page_photo->delete_thumbs($this->page_trip->id);

						if (copy($start_file, $end_file)) {

							chmod($end_file, 0777);
						}
					}
				}
			}
		}
		// И отдельно - картинку для мобильной версии
		if (!empty($this->data['mobile_image_tmp_file']) && file_exists($this->data['mobile_image_tmp_file'])) {

			$this->page_photo->delete_thumbs($this->page_trip->id, 'mobile');
			$this->page_photo->store_photo($this->data['mobile_image_tmp_file'], $this->page_trip->id, 'mobile');
		}

		// Обновляем категории
		if (!empty($data['categories'])) $this->save_categories(array_keys($data['categories']));

		// Специальные тексты
		$special_statuses = array();

		if (!empty($data['special_statuses'])) $special_statuses = $data['special_statuses'];
		if (!empty($data['special_statuses_new'])) $special_statuses = array_merge($special_statuses, $data['special_statuses_new']);

		$this->product->save_special_statuses($special_statuses, $this->page_trip->id);

		// У RS свой спец. статус. Пока что он один, но возможно, что потом будет несколько
		if (is_api_request() && isset($data['rs_special_status'])) {

			$rs_special_status = array();

			if ($current_rs_special_status = $this->product->get_special_statuses($this->page_trip->id, true)) {

				$rs_special_status[0] = array_shift($current_rs_special_status);
				$rs_special_status[0]['text'] = $data['rs_special_status'];
				$rs_special_status[0]['rs_status'] = 1;
				unset($rs_special_status[0]['show_banner_on_mobile']);
			}
			else {

				$rs_special_status[0] = array (
					'id'					=> null,
					'text'					=> $data['rs_special_status'],
					'rs_status'				=> 1,
				);
			}

			$this->product->save_special_statuses($rs_special_status, $this->page_trip->id);
		}

		if (!$is_fh_trip) {

			// Дополнительные опции
			$additional_options = array();
			if (!empty($data['additional_options'])) $additional_options = $data['additional_options'];
			if (!empty($data['additional_options_new'])) $additional_options = array_merge($additional_options, $data['additional_options_new']);

			$this->save_additional_options($additional_options);

			// Расписание
			$schedules = array();
			if (!empty($data['schedules'])) $schedules = $data['schedules'];
			if (!empty($data['schedules_new'])) $schedules = array_merge($schedules, $data['schedules_new']);

			$schedules_save_error = $this->save_schedules($schedules);
			if (is_api_request() && !$schedules_save_error) {

				$this->errors[] = 'Schedule\'s time duplicate';
			}
		}
		else if (!$fh_trip_disconnected) {

			// Дополнительные опции (новых у FH не может быть с нашей стороны)
			$additional_options = array();
			if (!empty($data['additional_options'])) $additional_options = $data['additional_options'];

			$this->save_additional_options($additional_options);

			// Расписания тоже только после синхронизации (и не может быть ошибок дублирования)
			$schedules = array();
			if (!empty($data['schedules'])) $schedules = $data['schedules'];

			$this->save_schedules($schedules);
		}

		// Билеты
		$tickets = array();
		if (!empty($data['tickets'])) $tickets = array_merge($tickets, $data['tickets']);
		if (!empty($data['tickets_new'])) $tickets = array_merge($tickets, $data['tickets_new']);

		if ($tickets) {

			$this->save_tickets(
				$tickets,
				(!empty($data['tickets_default']) ? $data['tickets_default'] : null)
			);
		}


		if (!$is_fh_trip) {

			// Inventory
			$inventories = array();
			if (!empty($data['inventories']) && is_array($data['inventories'])) $inventories = $data['inventories'];

			$this->save_inventories($inventories);
		}

		// Если меняется имя, то его старый SEOURL вариант заносим в спец лог, чтобы потом делать редиректы
		if (($this->config('mode') == 'edit') && strcmp($this->defaults['name'], $data['name'])) {

			$seo_name_log = array (
				'name_type'	=> get_seo_name_type('trip'),
				'self_id'	=> $this->page_trip->id,
				'old_name'	=> url($this->defaults['name']),
			);

			$this->db->insert_update('seo_name_log', $seo_name_log);

			// Если новое название уже было раньше, то убираем его из лога
			if ($previous_names = get_previous_seo_names(get_seo_name_type('trip'), $this->page_trip->id)) {

				foreach ($previous_names as $id => $name) {

					if (!strcmp($name, url($data['name']))) $this->db->delete('seo_name_log',array('id' => $id));
				}
			}
		}


		$this->cache->clear();
		$this->cache->file_cache_clear();


		$trip = $this->page_trip->get($this->page_trip->id, true);

		// Если расписаний не осталось, а флаг use_schedule стоит, то принудительно убираем его
		if (!$is_fh_trip && !$trip['schedules'] && $trip['use_schedule']) {

			$this->db->insert_update('products', array (
				'id'			=> $trip['id'],
				'use_schedule'	=> 0,
			));
		}

		$sqs_msg = [];

		if(!$trip['disabled'] && !$trip['is_disabled_ts']) {

			$sqs_msg[] = [
				'type' 	=> 'activity',
				'id'	=> $trip['id']
			];

			if(
				isset($trip['is_top_offer'])
					&&
				(
					$trip['is_top_offer']
						||
					(
						isset($this->defaults['is_top_offer'])
							&&
						($trip['is_top_offer'] != $this->defaults['is_top_offer'])
					)
				)
			) {

				$sqs_msg[] = [
					'type' => 'home'
				];
			}
		}

		if(!empty($sqs_msg) && is_live()) $this->sqs->send('ts_ms_front-end_static_generator', $sqs_msg);

		if (!is_api_request()) {

			$this->tpl->message_flag('info_success');

			$this->url->go('/index.php?page=trip&mode=edit&id='.$trip['id']);
		}
	}
}

// Дополнительные проверки для вызова из апи
class request_api_rs_trip_edit extends request_trip_edit {

	// Заглушка для списков
	protected function defaults() {}
	protected function lists() {}


	protected function valid_id($trip_id) {

		return ($this->page_trip->get($trip_id, false, false, $this->data['partner_id']) ? true : false);
	}

	protected function failure($errors) {

		$this->page_api_rs->send_response($this->data, false, array_values($errors));
	}


	// Добавляем картинки из временной папки к трипу и удаляем папку
	private function add_images_from_temp($product_id, $tmp_key, $partner_id) {

		if (!preg_match('/^[a-zA-Z0-9]+?$/', $tmp_key)) return;

		$folder_path = $this->config('dir_photos').'/tmp/'.$tmp_key;

		if (file_exists($folder_path)) {

			$images = array_diff(scandir($folder_path), array('..', '.'));

			$this->add_page('photo');

			foreach ($images as $image_file) {

				$image_path = $folder_path.'/'.$image_file;
				$image_extension = pathinfo($image_path, PATHINFO_EXTENSION);

				$this->page_photo->save(array (
					'file'				=> $image_path,
					'image_extension'	=> $image_extension,

					'user_id'			=> $partner_id,
					'content_type'		=> 'product',
					'target_id'			=> $product_id,
					'is_approved'		=> true,
				));
			}

			// очищаем и удаляем временную папку
			rmdir($folder_path);
		}
	}

	protected function success($data) {

		if (!empty($data['id'])) $this->page_trip->id = $data['id'];

		$data['data']['partner_id'] = $data['partner_id'];

		$data = $data['data'];

		parent::success($data);


		// Добавляем/обновляем ссылку на видео, если пришла
		if (isset($data['video_url'])) {

			$this->add_page('video');

			// Получаем id текущего сохранённого видео(если оно есть)
			$video = $this->page_video->get_list(array (
				'rs_product_id'		=> $this->page_trip->id,
				'no_links'			=> true,
			));

			$video_id = null;
			if ($video && ($video = array_pop($video))) $video_id = $video['id'];

			if ($data['video_url'] && (!$video_id || ($video['code'] != $data['video_url']))) {

				$this->page_video->save(array (
					'id'				=> $video_id,
					'user_id'			=> $data['partner_id'],
					'content_type'		=> 'product_rs',
					'target_id'			=> $this->page_trip->id,
					'description'		=> '',
					'code'				=> $data['video_url'],
				));
			}
			else if ($video_id && !$data['video_url']) {

				$this->page_video->delete($video_id);
			}
		}

		// Добавляем картинки из временной папки
		if (!empty($data['tmp_key'])) {

			$this->add_images_from_temp($this->page_trip->id, $data['tmp_key'], $data['partner_id']);
		}

		if (!empty($data['deleting_images'])) {

			$this->add_page('photo');
			$trip_photos = $this->page_photo->get_list($this->page_trip->id);

			$rs_only = false;

			$this->db->query("
				SELECT
					users.rs_only
				FROM
					users
				WHERE
					users.id = ?",
				$data['partner_id']
			);
			$db_res = $this->db->result;

			if (($user_data = $this->db->fetch($db_res)) && $user_data['rs_only'])  {

				$rs_only = true;
			}

			foreach ($trip_photos as $photo) {

				if (in_array($photo['id'], $data['deleting_images'])) {

					// То, что и так уже демонстрируется только в RS, просто удаляем
					if ($rs_only || ($photo['assignment'] == 'rs')) {

						$this->page_photo->delete($photo['id']);
					}
					// На удаление того, что только в TS, уже нет права, а остальное ставим на аппрув
					else if ($photo['assignment'] != 'ts') {

						// Это изменение сразу же скроет фото из RS
						$this->db->insert_update('photos', array('id' => $photo['id'], 'assignment' => 'ts'));

						// А удаление из TS ставим уже на аппрув
						$this->add_page('reservation_system');

						$this->page_reservation_system->add_to_queue(
							'photo',
							$this->page_trip->id,
							'delete',
							$data['partner_id'],
							array (
								'photo_id'			=> $photo['id'],
								'fields_difference'	=> array(),
							)
						);
					}
				}
			}
		}


		$response_data = array();
		$errors = array();

		if (!($trip = $this->page_api_rs->get_trip($this->page_trip->id, $data['partner_id']))) {

			$errors[] = 'Cannot get trip with id '.$this->page_trip->id;
		}

		if (!$errors) {

			$response_data = $trip;
		}

		$this->page_api_rs->send_response($response_data, !$errors, array_merge($errors, $this->errors));
	}
}

// Поиск трипов в админке
class request_trip_list extends f2b2h_request {

	protected function defaults() {

		$this->defaults['page_num'] = 1;


		$this->defaults['submit'] = $this->lang('apply');
	}

	protected function lists() {

		// Список активити
		$this->lists['activity'] = array(0 => $this->lang('any')) + $this->page_activity->get_formated_list();

		// Список городов
		$this->lists['city'] = array(0 => $this->lang('any')) + $this->page_city->get_list(array('product_type_id' => product_type('trip')), false);

		$this->add_page('partner');
		// Список партнёров
		$this->lists['partner'] = array(0 => $this->lang('any')) + $this->page_partner->get_list(array (
					'product_type'	=> 'trip',
					'order'			=> 'title',
					'active_only'	=> 1,
				),
				false
			);

		// Список статусов
		$this->lists['status'] = array (
			0	=> 'All',
			1	=> 'Active',
			2	=> 'Disabled',
			3	=> 'Expired',
		);
	}


	protected function success($data) {

		// Партнер может смотреть только свои трипы.
		if ($this->user->is_partner()) $data['partner'] = $this->user->data('id');

		// Флаг поиска в админке
		$data['admin_search'] = true;

		// Ищем трипы по нужным параметрам
		$this->add_page('search');

		// Параметры поиска
		$search_params = array (
			'activity_id'		=> $data['activity'],
			'city_id'			=> $data['city'],
			'name'				=> $data['name'],
			'product_code'		=> $data['product_code'],
			'partner_id'		=> $data['partner'],
			'status'			=> $data['status'],
			'is_top_offer'		=> $data['is_top_offer'],
		);
		// Собственно найденные trip_ids
		$trips = $this->page_search->search_trips($search_params, false);

		// Учитываем пагинацию
		$page_num = ($data['page_num'] ? $data['page_num'] : 1);
		// Определяем первый выводимый трип
		$limit_from = ($page_num - 1)*$this->config('search_showby_default');

		// Если выбрана страница большая, чем страниц всего, то посылаем на 1ую с этими же параметрами поиска
		if (count($trips) < $limit_from) {

			$this->url->go(str_replace('page_num='.$page_num, 'page_num=1', $_SERVER['REQUEST_URI']));
		}

		// Собственно отправляем в шаблон необходимые трипы
		for ($i = 0; $i < $this->config('search_showby_default'); $i++) {

			if (!empty($trips[$limit_from + $i])) {

				$trip = $this->page_trip->get($trips[$limit_from + $i]);

				if ($data['name']) {

					$trip['name'] = str_replace($data['name'], "<b>".$data['name']."</b>", $trip['name']);
				}

				$trip['partner'] = $this->user->format_name($trip);

				$this->tpl->loop('trip', array(), $trip);
			}
		}

		// Пагинация
		$trips_count = count($trips);
		$url = $_SERVER['REQUEST_URI'];

		pagination_main(
			$trips_count,
			$this->config('search_showby_default'),
			$page_num,
			$url
		);
	}
}

// Поиск трипов в юзер части
class request_trip_search extends f2b2h_request {

	public $search_params;


	protected function defaults() {

		$this->defaults['city'] = $this->url->get_int('city');
		$this->defaults['activity'] = $this->url->get_int('activity');

		$date_from = $this->url->get_str('from');
		if (!$date_from) $date_from = 'Any date';
		$this->defaults['date_from'] = $date_from;

		$date_to = $this->url->get_str('to');
		if (!$date_to) $date_to = 'Any date';
		$this->defaults['date_to'] = $date_to;

		$this->defaults['order'] = $this->url->get_str('order');

		if ($this->url->get_int('city_id')) {

			$this->defaults['city'] = $this->url->get_int('city_id');
		}

		if (!empty($_SESSION['last_search']['trips']['search_params']['city'])) {

			$this->defaults['city'] = $_SESSION['last_search']['trips']['search_params']['city'];
		}
		if (!empty($_SESSION['last_search']['trips']['search_params']['activity'])) {

			$this->defaults['activity'] = $_SESSION['last_search']['trips']['search_params']['activity'];
		}
	}

	protected function lists() {

		$this->add_page('city');
		$cities = $this->page_city->get_list(array (
				'product_type_id'		=> product_type('trip'),
				'only_valid_products'	=> true,
				'only_visible'			=> true
			),
			false
		);

		$this->lists['city'] = array(0 => 'Select Destination') + $cities;

		// Варианта 'any' у mode_transportation не должно быть в селекте
		$this->lists['activity'] = ((!is_mode('transportation')) ? array('Any') : array());

		// Варианты сортировки
		$this->add_page('search');
		foreach ($this->page_search->trips_search_order_types as $order_name => $search_order) {

			$this->lists['order'][$order_name] = $search_order['caption'];
		}
	}


	// На основе параметров поиска генерируем и отправляем в шаблон текст meta-description
	// Чтобы не дублировать сам поиск, метод вызывается сразу после него
	// $trips_count - количество собственных результатов сайта (без экспедий)
	//
	// Шаблоны:
	// City Activity Search Pages (General):
	//
	// "Find %%number_of_activities_listings%% or more top attractions, tours,
	// and things to do in %%city_name%%, %%state_name%% on [TripShock.com|%%portal_domain%%].
	// View 5 star ratings and reviews, photos, videos and more."
	//
	// Activity Search Page within City
	//
	// "Find %%number_of_activities_listings%% or more %%acitivity_name%%
	// in %%city_name%%, %%state_name%% on [TripShock.com|%%portal_domain%%].
	// View 5 star ratings, reviews, photos and more."
	private function place_meta_description($trips_count,$rates) {

		$this->add_page('trip');
		$this->add_page('activity');
		$this->add_page('city');

		$meta_description = '';

		// Определяем дополнительные параметры
		$what_to_do = 'top attractions, tours, and things to do';
		$find_things = 'Find the best things to do today, this weekend, or in '.date('F').'.';
		$end = 'Check out'.($rates ? ' over '.$rates : '').' traveler reviews, discounts, coupons, photos, and more.';
		
		$what_to_do_parts = array();

		// Тип активности
		if ($this->data['activity'] && ($activity = $this->page_activity->get($this->data['activity']))) {

			$what_to_do_parts[] = $activity['name'];
		}

		// Тип трипа
		if ($this->data['type'] && array_search($this->data['type'], $this->page_trip->types)) {

			$what_to_do_parts[] = array_search($this->data['type'], $this->page_trip->types).' activities';
		}

		// Категория трипа
		if ($this->data['category'] && ($category = category_get($this->data['category']))) {

			$what_to_do_parts[] = $category['name'].' activities';
		}

		if (!empty($what_to_do_parts)) {

			$what_to_do = implode(', ', $what_to_do_parts);
			
			$find_things = '';
		}

		//Определяем локацию
		$location = 'Gulf Coast';

		if ($this->data['city'] && ($city = $this->page_city->get($this->data['city']))) {

			$city['state_name'];
			$city['name'];

			$location = $city['name'].', '.$city['state_name'];
		}

		$domain = (is_portal() ? f2b2h::$static['config']['portal']['domain'] : 'TripShock.com');

		$meta_description = 'Find '.$trips_count.' or more '.$what_to_do.' in '.$location.' on '.$domain.'. '.$find_things.' '.$end;

		$this->tpl('meta_description', $meta_description);
	}

	protected function success($data) {

		// Получаем параметры в цифровом виде
		$data = $this->page_trip->get_seourl_params($data);

		// На самой странице attractions (а не на вызываемых в ней аяксом страницах) даты игнорим всегда
		// т.к. здесь это не критично - это не те фактические результаты, которые видит юзер
		if ($this->config('page') == 'attractions') {

			$data['from'] = null;
			$data['to'] = null;
		}

		// Дата к общему виду Y-m-d
		if ($data['from']) {

			if (is_page('trip') && is_mode('calendar')) {

				$data['from'] = str_replace("-", " ", $data['from']);
				$data['to'] = str_replace("-", " ", $data['to']);
			}

			$date_from = strtotime($data['from']);
			$date_to = strtotime($data['to']);

			if (
				!$date_from
					||
				!$date_to
					||
				($date_from < strtotime("-1 day"))
					||
				($date_to < strtotime("today"))
					||
				(($date_to - $date_from)/(3600*24) > 60)
				) {

				return;
			}
		}

		// Параметры поиска
		$search_params = array (
			'dateless'				=> (!$data['from']),
			'date_from'				=> ($data['from'] ? format_date_for_db($data['from']) : null),
			'date_to'				=> ($data['to'] ? format_date_for_db($data['to']) : null),
			'activity_id'			=> (isset($data['activity']) ? $data['activity'] : null),
			'category_id'			=> (isset($data['category']) ? $data['category'] : null),
			'type_id'				=> (isset($data['type']) ? $data['type'] : null),
			'city_id'				=> (isset($data['city']) ? $data['city'] : null),
			'state_id'				=> (isset($data['state']) ? $data['state'] : null),
			'name'					=> $data['name'],
			'order'					=> $data['order'],
			'status'				=> 1,
			'ignore_avail_by_rates'	=> 1,
		);

		if( !empty($data['from']) && !empty($data['to']) ) $search_params['disable_booking'] = 0;

		$this->search_params = $search_params;


		$no_cache = (($this->url->get('no_cache') !== null) ? true : false);

		// Если страница не первая, то полные результаты поиска есть в сессии
		if (!$no_cache && !empty($_SESSION['search_trips']['avail'])) {

			$trips = $_SESSION['search_trips']['avail'];
		}
		else {

			$this->add_page('search');
			// Сначала ищем те трипы, которые заказываются на нашем сайте
			$trips = $this->page_search->search_trips($search_params);


			if ($this->config('mode') != 'list') {

				$_SESSION['search_trips']['avail'] = $trips;
			}
		}

		// Для пользовательского поиска добавляем meta_description
		if ($this->config('page') == 'attractions') {
			
			$this->db->query("
				SELECT
					SUM(rate_count) as rates
				FROM
					products
				WHERE
					id IN (".$this->db->in($trips).")"
			);
			$rates = (($city_rates = $this->db->fetch()) ? $city_rates['rates'] : 0);
			
			$this->place_meta_description(count($trips),$rates);
		}

		// Собственно вывод результатов срабатывает только при запросе на мод search
		if (!is_mode('search') || !is_page('trip')) return;
		else {

			// Страница минимум первая
			if (empty($data['page_num'])) $data['page_num'] = 1;
			$trips_per_page = $this->config('search_showby_default');
			// Определяем первый выводимый трип и первый за ним
			$limit_from = 0;
			$limit_to = $trips_per_page;
			// Если выбрана страница большая, чем страниц всего, то выходим
			if (count($trips) <= $limit_from) return;

			// Выделяем новый набор трипов для показа
			$trips_after_pagination = array_slice($trips, $limit_from, $trips_per_page);
			// Удаляем выделенный набор из основного списка, предварительно сохраняя полный список
			$full_trips_list = $trips;
			$trips = array_slice($trips, $limit_to);

			// Если указан период поиска, то проверяем доступность
			if ($search_params['date_from'] && $search_params['date_to']) {

				// Получаем информацию по доступности для трипов после пагинации
				$trips_rates = $this->page_trip->get_trips_tickets_rates(
					$trips_after_pagination,
					$search_params['date_from'],
					$search_params['date_to'],
					true
				);


				// Определяем какие из найденных трипов связаны с FH
				$fh_trips = array();

				$this->db->query("
					SELECT
						products.id,
						products.fareharbor_id
					FROM
						products
					WHERE
						products.id IN (".$this->db->in($full_trips_list).")
							AND
						products.fareharbor_id IS NOT NULL"
				);
				$db_res = $this->db->result;

				while ($fh_trip = $this->db->fetch($db_res)) $fh_trips[$fh_trip['id']] = $fh_trip['fareharbor_id'];

				// Для пагинационных подменяем доступность в общем  массиве на валидную
				// А остальные помним на случай если в найденных есть недоступные и будем брать новые
				foreach ($trips_after_pagination as $trip_after_pagination) {

					if (isset($fh_trips[$trip_after_pagination])) {

						$fh_availability = $this->page_trip->get_fh_availability(
							$fh_trips[$trip_after_pagination],
							$search_params['date_from'],
							$search_params['date_to']
						);

						if ($fh_availability) {

							// Поскольку в данном методе проверяется ключ not_avail, а для FH трипов мы всегда
							// форсим его 1, то тут подменяем его ради того, чтобы не переделывать проверки ниже
							$fh_availability['not_avail'] = $fh_availability['not_avail_ts'];

							$trips_rates[$trip_after_pagination] = $fh_availability;
						}
					}
				}


				$excluded_trips_count = 0;

				// Исключаем не доступные по всему периоду трипы
				foreach ($trips_rates as $trip_id => $days_info) {

					if ($days_info['not_avail']) {

						$excluded_trips_count++;

						unset($trips_after_pagination[array_search($trip_id, $trips_after_pagination)]);
					}
				}

				// Если кого-то исключили, то выбираем из дальнейших трипов по одному, пока не найдём достаточно доступных
				while ($trips && $excluded_trips_count) {

					$current_trip = array_shift($trips);

					if (isset($fh_trips[$current_trip])) {

						$trip_rates = $this->page_trip->get_fh_availability(
							$fh_trips[$current_trip],
							$search_params['date_from'],
							$search_params['date_to']
						);

						if ($trip_rates) $trip_rates['not_avail'] = $trip_rates['not_avail_ts'];
					}
					else {

						$trip_rates = $this->page_trip->get_trips_tickets_rates(
							$current_trip,
							$search_params['date_from'],
							$search_params['date_to'],
							true
						);
					}

					if ($trip_rates && !$trip_rates['not_avail']) {

						// Добавляем всегда в конец чтобы не нарушить упорядочивание, идущее из поиска
						array_push($trips_after_pagination, $current_trip);
						$excluded_trips_count--;
					}
				}

				// Даём строгий порядок индексов выводимым и ещё ожидающим трипам
				$trips_after_pagination = array_values($trips_after_pagination);
			}

			$trips = $_SESSION['search_trips']['avail'] = array_values($trips);

			// Отправляем в шаблон выделенные трипы
			foreach ($trips_after_pagination as $key => $trip_id) {

				$this->page_trip->show_short_details($trip_id, array(
					'number' => (($data['page_num'] - 1) * $trips_per_page + $key + 1),
					'in_list' => true,
					'date_from' => ($search_params['date_from'] ? strtotime($search_params['date_from']) : null),
					'date_to' => ($search_params['date_to'] ? strtotime($search_params['date_to']) : null),
				));
			}

			// Если последняя страница, то отправляем со списком скрытый див-флаг
			if ($trips_after_pagination && !$trips) {

				$this->tpl('last_products', true);
			}
		}
	}
}

// Форма выбора билетов и опций для добавления в корзину
class request_trip_add_to_cart extends f2b2h_request {

	private $rs_adding_to_order = false;
	private $added_to_order_items = array();

	private function get_fields_from_fh_linked_trip($fh_data) {

		$this->db->query("
			SELECT
				products.id,
				products.use_calendar
			FROM
				products
			WHERE
				products.fareharbor_id IS NOT NULL
					AND
				products.fareharbor_id LIKE '%".$fh_data."%'"
		);
		$db_res = $this->db->result;

		return (($result = $this->db->fetch($db_res)) ? $result : array());
	}

	protected function get_added_to_order_items() {

		return $this->added_to_order_items;
	}

	protected function failure($errors) {

		ajax_info_errors($errors);
	}


	// Добавляем в корзину и сообщение о успехе в ajax
	protected function success($data) {

		// Если это запрос аякса на получение формы - нам не надо ничего обрабатывать
		if (!is_mode('add_to_cart') && !is_api_request()) return true;

		// Итемы добавляются к уже готовому заказу - режим есть пока что только в RS
		if (is_mode('order_add_items')) $this->rs_adding_to_order = true;


		// Валидацию параметров пока проводим здесь
		$errors = array();


		if (!$data['agreement_confirm']) {

			$errors[] = array (
				'text'		=> 'Please confirm the agreement below to proceed',
				'field'		=> 'agreement_confirm',
			);
		}


		$is_fh_trip = false;

		if (isset($data['fh_id']) && !empty($data['fh_id'])) {

			$is_fh_error = false;

			if (!isset($data['calendar_id']) || empty($data['calendar_id'])) $is_fh_error = true;
			else {

				$fh_trip_data = $this->page_trip->check_fh_connection($data['calendar_id']);

				if (is_null($fh_trip_data) || !isset($data['fh_tickets_config'])) {

					$is_fh_error = true;
				}
				else {

					$is_fh_trip = true;

					$data['fh_trip_data'] = $fh_trip_data;
					$data['fh_tickets_config'] = $this->db->decode_data($data['fh_tickets_config']);
					$data['fh_options_config'] = $this->db->decode_data($data['fh_options_config']);
				}
			}

			if ($is_fh_error) $errors[]['text'] = 'FH data misconfiguration. Call us for assistance';
		}


		$is_gift = false;

		if (!$is_fh_trip) {

			$can_be_gift = !($this->page_trip->is_price_changes_for_year($data['trip_id']));

			if ($data['is_gift'] && $can_be_gift) $is_gift = true;
			$data['actual_is_gift'] = $is_gift;
		}
		else {

			$data['is_gift'] = $data['actual_is_gift'] = $is_gift;
		}


		if (!$errors) {

			$day_validation_errors = $this->page_trip->valid_day_booking($data);

			if ($day_validation_errors) {

				foreach ($day_validation_errors as $error) $errors[]['text'] = $error;
			}
		}


		// Падаем с ошибками
		if ($errors) $this->failure($errors);


		if (!$is_fh_trip) {

			if ($data['calendar_id']) {

				$this->add_page('calendar');
				$calendar_day = $this->page_calendar->get_day($data['calendar_id']);

				$trip = array (
					'id'			=> $calendar_day['product_id'],
					'use_calendar'	=> 1,
				);
			}
			else {

				$calendar_day = array('id' => null);

				$trip = array (
					'id'			=> $data['trip_id'],
					'use_calendar'	=> ($this->page_trip->is_using_calendar($data['trip_id']) ? 1 : 0),
				);
			}
		}
		else {

			$calendar_day = array('id' => $data['calendar_id']);

			$trip = $this->get_fields_from_fh_linked_trip($data['fh_trip_data']);
		}


		$book_info = $this->page_trip->format_cart_item_data($data);


		$this->add_page('cart');
		$this->add_page('cart_item');

		$added_items = array();
		$all_items_added = true;
		$new_order_with_failed_payment = false;
		$google_analytics_data = array();

		foreach ($book_info as $cart_item_book_info) {

			// Ранее мы ссылались на первый созданный итем т.к. в нём оригинальные доп. опции и коммент клиента
			// Но их попросили убрать (номер бага в трекере 11558)
			if ($added_items) {

				//$cart_item_book_info['comments']['partner'] = '[Created automatically from item #'.$added_items[0].']';
			}

			// Если корзина уже есть, используем её cart_id
			$cart_id = null;
			$allow_add_to_order = false;
			if (!empty($_SESSION['cart_id'])) {

				if ($cart = $this->page_cart->get($_SESSION['cart_id'])) {

					// В общем случае мы не можем добавлять новые части в корзину, с которой уже асоциирован заказ.
					// Однако пользователям RS позволено внедрять новые части в свои заказы а также на стороне TS
					// должна быть возможность добавить что-то в случае фэйла оплаты на step2 или отказа от оплаты PP
					if (
						!is_null($cart['order_id'])
							&&
						// Если заказ пытались сделать только что, то его ID будет в сессии
						isset($_SESSION['order_id'])
							&&
						// И будет совпадать с тем, что в корзине (проверка наверняка)
						!strcmp(strval($cart['order_id']), strval($_SESSION['order_id']))
					) {

						// Также у такого заказа будет один единственный статус и это made
						$this->db->query("
							SELECT
								orders_states.*
							FROM
								orders_states
							WHERE
								orders_states.order_id = ?",
							$cart['order_id']
						);

						if ($this->db->count() == 1) {

							$order_state = $this->db->fetch();

							if ($order_state['state'] == 'made') $new_order_with_failed_payment = true;
						}
					}

					if (!is_null($cart['order_id']) && !$new_order_with_failed_payment && !$this->rs_adding_to_order) {

						unset($_SESSION['cart_id']);
					}
					else {

						$allow_add_to_order = true;
						$cart_id = $cart['id'];
					}
				}
			}


			$cart_item_data = array(
				'product_type'			=> 'trip',
				'cart_id'				=> $cart_id,
				'allow_add_to_order'	=> $allow_add_to_order,
				'calendar_id'			=> $calendar_day['id'],
				'product_id'			=> $trip['id'],
				'is_fh_item'			=> ($is_fh_trip ? 1 : 0),
				'data'					=> $cart_item_book_info,

				'gift_code'				=> ($is_gift ? get_gift_code() : ''),
				'welcomeback_sent'		=> intval(($trip['use_calendar'] ? false : true))
			);

			if ($cart_item_id = $this->page_cart->add_item($cart_item_data)) {

				// Это на случай автоматического распределения на несколько итемов на TS
				$added_items[] = $cart_item_id;

				// Заполняем данные компонентов корзины для гугл аналитики чтобы отправлять их из JS
				if (!is_api_request()) {

					$google_analytics_data[$cart_item_id] = array(
						'name' => $cart_item_book_info['product']['name'],
						'id' => $trip['id'],
						// Это заполним только если всё ок т.к. это лишние запросы в БД
						'price' => 0.0,
						'brand' => '',
						'category' => '',
						'variant' => format_tickets_string($cart_item_book_info['tickets']),
						'quantity' => '1',
						'image' => '',
					);
				}

				// А это на случай если несколько итемов добавляется к уже существующему заказу
				if ($this->rs_adding_to_order) $this->added_to_order_items[] = $cart_item_id;
			}
			else {

				// Хоть одна не добавилась - все убираем
				$all_items_added = false;
				break;
			}
		}

		// Если добавлялось несколько частей, но прошли не все, то удаляем из корзины уже добавленные
		if (!$all_items_added) {

			foreach ($added_items as $cart_item_id) $this->page_cart_item->delete($cart_item_id);
		}
		// Если это TS и заказ уже есть, то ставим этим частям made статус и добавляем записи в лог заказа
		else if (!is_api_request() && $new_order_with_failed_payment) {

			$this->db->query("
				UPDATE
					cart_items
				SET
					state_name = 'made'
				WHERE
					id IN (".$this->db->in($added_items).")"
			);

			foreach ($added_items as $cart_item_id) {

				$this->log->save_order_log(
					'Added cart item <b>'.$cart_item_id.'</b>',
					// Если $new_order_with_failed_payment == true то и эта переменная должна существовать
					$_SESSION['order_id'],
					$cart_item_id
				);
			}
		}

		// Если всё прошло успешно - отправляем status = success
		if ($added_items && !is_api_request() && !is_page('api_global')) {

			// Добираем из БД недостающие для аналитики данные
			if ($google_analytics_data) {

				foreach ($google_analytics_data as $cart_item_id => $data_item) {

					$this->db->query("
						SELECT
							cart_items.total,
							users.title,
							cities.name,
							activities.name AS activity_name
						FROM
							cart_items
							JOIN products ON products.id = cart_items.product_id
							JOIN users ON users.id = products.partner_id
							LEFT JOIN cities ON cities.id = products.city_id
							LEFT JOIN activities ON activities.id = products.activity_id
						WHERE
							cart_items.id = ?",
						$cart_item_id
					);

					$cart_item = $this->db->fetch();

					$data_item['price'] = money($cart_item['total'], true);
					$data_item['brand'] = $cart_item['title'];
					$data_item['category'] = (!empty($cart_item['name']) ? $cart_item['name'] : 'Undefined').' - '.
						(!empty($cart_item['activity_name']) ? $cart_item['activity_name'] : 'Undefined');

					// Основное фото
					$this->add_page('photo');
					$data_item['image'] = $this->page_photo->get_photo_link($data_item['id']);

					$google_analytics_data[$cart_item_id] = $data_item;
				}
			}

			ajax_info(array (
				'status' => 'success',
				'google_analytics_data' => $google_analytics_data,
				'sesid' => session_id()
			));
		}
		// Иначе выдаём ошибку добавления элемента в корзину
		else if (!is_api_request() && !is_page('api_global')) $this->failure(array('Add to cart error'));
	}
}

class request_api_rs_trip_add_to_cart extends request_trip_add_to_cart {

	private $calendar_id;

	private $trip_use_calendar;

	private $item_being_added = null;

	private $adding_to_order = false;
	private $adding_to_order_cart_id = null;

	protected function valid_partner_id($partner_id) {

		if (!$this->user->valid_partner_id($partner_id)) {

			$this->lang('add_to_cart_partner_id_error', $this->lang('partner_validation_partner_id_error'));

			return false;
		}

		return true;
	}

	protected function valid_trip_id($trip_id) {

		if (!($trip = $this->page_trip->get($trip_id, false, false, $this->data['partner_id']))) return false;

		$this->trip_use_calendar = $trip['use_calendar'];

		return true;
	}

	protected function valid_day($day) {

		$this->add_page('calendar');

		if (
			$this->trip_use_calendar
				&&
			(
				!$day
					||
				!($calendar_day = $this->page_calendar->get_day(null, $day, $this->data['trip_id']))
			)
			) {

			return false;
		}

		if (!empty($calendar_day['id'])) $this->calendar_id = $calendar_day['id'];
		else $this->calendar_id = null;

		return true;
	}

	protected function valid_cart_id($cart_id) {

		if (!$cart_id) return true;

		$this->db->query("
			SELECT
				1
			FROM
				orders
			WHERE
				cart_id = ?",
			$cart_id
		);

		return ($this->db->fetch() ? false : true);
	}

	protected function valid_order_id($order_id) {

		if (!is_mode('order_add_items')) return true;
		else {

			if (!$order_id || !empty($this->data['cart_id'])) return false;
			else {

				$this->db->query("
					SELECT
						orders.cart_id
					FROM
						orders
					WHERE
						id = ?",
					$order_id
				);
				$db_res = $this->db->result;

				if (!($order = $this->db->fetch($db_res))) return false;
				else {

					$this->adding_to_order_cart_id = $order['cart_id'];
					return true;
				}
			}
		}
	}

	protected function failure($errors) {

		$prefix = (!is_null($this->item_being_added) ? '[Item '.($this->item_being_added + 1).'] ' : '');

		foreach ($errors as $key => $error) {

			if (is_array($error) && !empty($error['text'])) $errors[$key] = $prefix.$error['text'];
		}

		// Ошибка могла случиться не на первом из итемов
		if (($prefix != '') && (!empty($_SESSION['cart_id']))) {

			// Новую корзину удаляем полностью
			if (!$this->adding_to_order) {

				$this->add_page('cart');
				$this->page_cart->delete($_SESSION['cart_id']);
			}
			// Если же добавление было к существующему заказу, то удалить нужно только новые итемы
			else {

				$this->add_page('cart_item');

				foreach (parent::get_added_to_order_items() as $added_to_order_item) {

					$this->page_cart_item->delete($added_to_order_item);
				}
				unset($_SESSION['cart_id']);
			}

		}

		$this->page_api_rs->send_response($this->data, false, $errors);
	}


	// Выставляем статус новой части заказа
	// Если у партнёра выставлен флаг autoconfirm_orders, то confirmed, иначе - paid
	protected function set_new_item_state($cart_item_id) {

		$this->add_page('order');

		$this->db->query("
			SELECT
				cart_items.id
			FROM
				cart_items
				JOIN products ON products.id = cart_items.product_id
				JOIN users ON users.id = products.partner_id
			WHERE
				cart_items.id = ?
					AND
				users.autoconfirm_orders = 1",
			$cart_item_id
		);

		$db_res = $this->db->result;

		if ($this->db->fetch($db_res)) {

			$this->page_order->cart_item_set_state($cart_item_id, 'confirmed');
		}
		else {

			$this->page_order->cart_item_set_state($cart_item_id, 'paid');
		}
	}

	protected function add_one_item($data) {

		if (!empty($data['data']['schedule_id'])) $data['schedule_id'] = $data['data']['schedule_id'];
		if (!empty($data['data']['inventory_id'])) $data['inventory_id'] = $data['data']['inventory_id'];
		if (!empty($data['data']['booking_fee'])) $data['booking_fee'] = $data['data']['booking_fee'];
		if (!empty($data['data']['tickets'])) $data['tickets'] = $data['data']['tickets'];
		if (!empty($data['data']['guests'])) $data['guests'] = $data['data']['guests'];
		if (!empty($data['data']['additional_options'])) $data['additional_options'] = $data['data']['additional_options'];
		if (!empty($data['data']['comment_guest'])) $data['comment_guest'] = $data['data']['comment_guest'];
		if (!empty($data['data']['comment_partner'])) $data['comment_partner'] = $data['data']['comment_partner'];

		$data['calendar_id'] = $this->calendar_id;
		$data['is_gift'] = false;
		$data['agreement_confirm'] = true;

		// Если добавляем к заказу, то обязательно д.б. уже готовая корзна
		if ($this->adding_to_order) $_SESSION['cart_id'] = $this->adding_to_order_cart_id;
		// Если заказ новый, то может быть не первый итем
		else if ($data['cart_id']) $_SESSION['cart_id'] = $data['cart_id'];

		parent::success($data);

		// При добавлении к заказу воизбежание багов корзина в сессии существует только на время добавления
		if ($this->adding_to_order) unset($_SESSION['cart_id']);
	}

	protected function success($data) {

		// Итемы добавляются к уже готовому заказу
		if (is_mode('order_add_items')) $this->adding_to_order = true;

		$errors = array();

		// Метод admin_cart_create может передавать несколько итемов сразу
		if (isset($data['data'][0])) {

			$global_data = $data;

			// Определяем дополнительные параметры для проведения корректной валидации
			$global_data['for_validation']['total_booked'] = 0;
			foreach ($global_data['data'] as $index => $item_data) {

				if (isset($item_data['tickets'])) {

					foreach ($item_data['tickets'] as $item_data_ticket_booked) {

						$global_data['for_validation']['total_booked'] += intval($item_data_ticket_booked);
					}
				}
				else {

					$errors[] = 'Wrong data format';
				}
			}

			if (!$errors) {

				$main_data = $global_data['data'];

				foreach ($main_data as $index => $item_data) {

					$data = $global_data;
					$data['data'] = $item_data;

					$data['data']['for_validation'] = $data['for_validation'];
					unset($data['for_validation']);

					// Нужно чтобы было видно в каком именно из итемов произошла ошибка
					if (count($main_data) > 1) $this->item_being_added = $index;
					$this->add_one_item($data);
				}
			}
		}
		else {

			// "Обязываем" метод admin_cart_create передавать индексированный массив data
			if (!is_mode('admin_cart_create') && !$this->adding_to_order) $this->add_one_item($data);
			else $errors[] = 'Wrong data format';
		}

		$data = array();

		if (!$errors) {

			$this->add_page('cart');

			if ($this->adding_to_order) {

				if (empty($this->adding_to_order_cart_id) || !($cart = $this->page_cart->get($this->adding_to_order_cart_id))) {

					$errors[] = 'Invalid cart';
				}
			}
			else {

				if (empty($_SESSION['cart_id']) || !($cart = $this->page_cart->get($_SESSION['cart_id']))) {

					$errors[] = 'Invalid cart';
				}
			}

			if (!$errors) {

				if ($this->adding_to_order) {

					$this->add_page('order');

					// Ставим новым итемам первые статусы и добавляем записи в лог заказа о добавленных частях
					foreach (parent::get_added_to_order_items() as $added_to_order_item) {

						$this->log->save_order_log(
							'Added cart item <b>'.$added_to_order_item.'</b>',
							$this->data['order_id'],
							$added_to_order_item
						);

						$this->set_new_item_state($added_to_order_item);
					}

					// И общие суммы пересчитываем сами (не делаем этого в page_cart::add_item) чтобы шли записи в лог
					$this->page_order->update_order_totals($this->data['order_id']);

					// Если добавляем новые итемы, то в ответет должен быть лог изменений заказа
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
				}
				// Если это новая корзина, то только возвразаем её id
				else {

					$data['cart_id'] = $cart['id'];
				}
			}
		}

		$this->page_api_rs->send_response($data, !$errors, $errors);
	}
}

class request_api_global_trip_add_to_cart extends request_trip_add_to_cart {

	protected function valid_session_id($session_id) {

		if (!empty($session_id)) return true;

		$this->errors[] = array (
			'text'		=> 'Wrong Session ID',
		);

		return false;
	}

	protected function failure($errors) {

		unset($_SESSION['cart_id']);

		$response = array (
			'success'	=> false,
			'errors'	=> array()
		);

		foreach ($errors as $key => $error) {

			if (is_array($error) && !empty($error['text'])) $response['errors'][$key] = $error['text'];
		}

		$this->page_api_global->send_response($response);
	}

	protected function success($data) {

		// На время работы метода если корзина ужесуществует, то пишем её в сессию
		// А если не существует, то страхуемся чтобы её в сессии точно не было
		unset($_SESSION['cart_id']);

		$is_new_cart = true;
		$cart_id = $this->page_api_global->get_session_cart_id($data['session_id']);
		if ($cart_id !== false) {

			$is_new_cart = false;
			$_SESSION['cart_id'] = $cart_id;
		}


		// Собственно добавление
		parent::success($data['data']);


		if ($is_new_cart) {

			$this->db->insert_update_no_id('cart_session', array(
					'cart_id' => $_SESSION['cart_id'],
					'session_id' => $data['session_id']
				),
				array('session_id')
			);
		}

		$this->db->query("
			SELECT
				lifetime,
				update_time
			FROM
				carts
			WHERE
				carts.id = ?",
			$_SESSION['cart_id']
		);
		$timing = $this->db->fetch();

		if (!empty($timing['lifetime'])) {

			$lifetime = intval($timing['lifetime']);
			$time_difference = strtotime("+".$lifetime." min", strtotime($timing['update_time'])) - time();
		}
		else {

			$lifetime = 0;
			$time_difference = 0;
		}

		$response = array (
			'success'	=> true,
			'data'		=> array (
				'cart_id'		=> $_SESSION['cart_id'],
				'session_id'	=> $data['session_id'],
				'lifetime'		=> $lifetime,
				'time_left'		=> $time_difference,
			)
		);

		$this->page_api_global->send_response($response);

		unset($_SESSION['cart_id']);
	}
}

?>
