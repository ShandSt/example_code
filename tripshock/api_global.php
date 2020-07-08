<?php

/**
 * Global API for the new architecture
*/

/**
 * @property page_comment page_comment
 * @property page_trip page_trip
 * @property page_activity page_activity
 * @property page_itinerary page_itinerary
 */
class page_api_global extends f2b2h {

	const DEFAULT_LIMIT = 10;

	private $default_offset = 0;
	private $default_limit = 10;


	// Отключаем шаблон
	public function __construct() {

		parent::__construct();

		if (f2b2h::$static['config']['page'] === 'api_global') $this->config('tpl_off', true);
	}


	/**
	 * Send JSON response
	 */
	public function send_response($response = array()) {

		header('Access-Control-Allow-Origin: *');
		header('Access-Control-Allow-Methods: POST, GET');
		header('Content-type: application/json');

		// Сохраняем в лог данные запроса
		if (empty($_POST)) $request_data = $_GET;
		else $request_data = $_POST;

		if(!empty($_POST)) {

			$this->log->api_save(
				$this->config('page'),
				$this->config('mode'),
				$request_data,
				$response
			);
		}

		exit(json_encode($response, JSON_PRETTY_PRINT));
	}


	// --------------------- Utils --------------------

	/**
	 * Common response for the deprecated methods
	 */
	public function deprecated() {

		$this->send_response(array('The method is deprecated'));
	}

	private function prepare_post_data() {

		$_POST = json_decode(file_get_contents('php://input'), true);
	}


	private function format_trip_data($trip_data) {

		$this->add_page('trip');

		$categories_raw = $this->page_trip->get_categories();
		// format categories as ID => name map
		$categories = [];

		foreach ($categories_raw as $category) {

			$categories[$category['id']] = $category['name'];
		}

		$trip_data['locations'] = array();
		for ($i = 1; $i<=6; $i++) {

			$pfx = (($i == 1) ? '' : $i);

			if (!empty($trip_data['city_id'.$pfx])) {

				$trip_data['locations'][] = array (
					'city_id'		=> $trip_data['city_id'.$pfx],
					'city_name'		=> $trip_data['city_name'.$pfx],
					'state_id'		=> $trip_data['state_id'.$pfx],
					'state_name'	=> $trip_data['state_name'.$pfx],
					'state_code'	=> $trip_data['state_code'.$pfx],
					'country_id'	=> $trip_data['country_id'.$pfx],
					'country_name'	=> $trip_data['country_name'.$pfx],
				);
			}

			if(!empty($pfx)) unset($trip_data['city_id'.$pfx]);
			if(!empty($pfx)) unset($trip_data['city_name'.$pfx]);

			unset($trip_data['state_id'.$pfx]);
			unset($trip_data['state_name'.$pfx]);
			unset($trip_data['state_code'.$pfx]);
			unset($trip_data['country_id'.$pfx]);
			unset($trip_data['country_name'.$pfx]);
		}

		$trip_data['activity_categories'] = array();
		for ($i = 1; $i<=3; $i++) {

			$sfx = (($i == 1) ? '' : $i);

			if (!empty($trip_data['activity'.$sfx.'_id'])) {

				$category = array (
					'id'	=> $trip_data['activity'.$sfx.'_id'],
					'name'	=> $trip_data['activity'.$sfx.'_name'],
				);
				$category['image_url'] = format_trip_activity_img_link($category);
				$category['url'] = $this->clear_url('/attractions/'.format_to_seourl($trip_data['city_name']).
					'/'.format_to_seourl($trip_data['activity'.$sfx.'_name']).'/');

				$trip_data['activity_categories'][] = $category;
			}

			unset($trip_data['activity'.$sfx.'_id']);
			unset($trip_data['activity'.$sfx.'_name']);
		}

		// trip categories
		$trip_data['tags'] = array();
		if (!empty($trip_data['categories'])) {

			$trip_data['tags'] = array_values(array_intersect_key (
				$categories,
				array_flip($trip_data['categories'])
			));
		}

		unset($trip_data['categories']);

		return $trip_data;
	}

	private function get_actual_city_activities($city_id, $is_featured = false, $make_short = false, $sort_by_top = false) {

		$this->add_page('activity');

		$result = [];

		// city data
		$this->db->query("
			SELECT
				*
			FROM
			 	cities
			WHERE
				cities.id = ?",
			$city_id
		);

		if (!($city = $this->db->fetch())) return $result;

		$city_data = $this->db->decode_data($city['data']);
		$top_activities = array();
		if ($city_data && isset($city_data['top_activities'])) {

			$top_activities = explode(",", $city_data['top_activities']);
		}

		// fetch top activities for this destination
		$this->db->query("
			SELECT p.activity_id, p.activity2_id, p.activity3_id
			FROM products p 
			WHERE p.city_id = ? AND p.is_approved = 1 AND p.disabled = 0",
			$city_id
		);
		$res = $this->db->result;

		$activities = array();
		while ($product = $this->db->fetch($res)) {

			foreach ($product as $activity_id) {

				if (
					$activity_id
						&&
					!in_array($activity_id, $activities)
						&&
					(
						!$is_featured
							||
						(
							$is_featured
								&&
							in_array($activity_id, $top_activities)
						)
					)
				) {

					$activities[] = $activity_id;
				}
			}
		}


		if($sort_by_top && $top_activities)
			$activities = array_diff($top_activities, array_diff( $top_activities, $activities));

		if ($make_short) return $activities;


		if ($activities) {

			$activities = $this->page_activity->get_list(array('activity_ids' => $activities));

			foreach ($activities as $activity) {

				$category = array(
					'id' => $activity['id'],
					'name' => $activity['name'],
				);
				$category['image_url'] = format_trip_activity_img_link($category);
				$category['url'] = $this->clear_url('/attractions/'.format_to_seourl($city['name']).
					'/'.format_to_seourl($category['name']).'/');

				$result[] = $category;
			}
		}

		return $result;
	}


	private function get_trip_reviews($trip_id, $offset, $limit, $partner_id = null) {

		$this->add_page('comment');
		$this->add_page('review');

		$reviews_count = 0;

		if('all' === $trip_id) {

		    $args = [
                'limit'			=> $limit,
                'offset'		=> $offset,
            ];

		    if($partner_id) $args['partner_id'] = (int)$partner_id;

            $commentsRaw = $this->page_comment->get_list($args);
        } else {

            $this->db->query(
                "SELECT 1 FROM comments WHERE comments.target_id = ? AND comments.is_yelp = 1",
                $trip_id
            );
            $db_res = $this->db->result;

            $isYelp = (bool)$this->db->fetch($db_res);

			$comments_first = $this->page_comment->get_list([
				'product_id'			=> $trip_id,
				'is_yelp'				=> $isYelp,
				'limit'					=> (($limit > 4) ? 4 : $limit),
				'offset'				=> 0,
				'rating_filter' 		=> [4,5],
				'skip_empty_content' 	=> true
			]);

			$exclude_ids = [];
			foreach ($comments_first as $cmt) $exclude_ids[] = $cmt['id'];

			$exclude_count = count($exclude_ids);
			if($limit > 4) {

				$commentsRaw = $this->page_comment->get_list([
					'product_id'			=> $trip_id,
					'is_yelp'				=> $isYelp,
					'limit'					=> (($offset === 0) ? ($limit - $exclude_count) : $limit),
					'offset'				=> (($offset > $exclude_count) ? ($offset - $exclude_count) : $offset),
					'exclude_ids'			=> $exclude_ids,
				]);

				$reviews_count = (int) $this->page_comment->get_list_count() + $exclude_count;

				if($offset === 0) $commentsRaw = array_merge($comments_first, $commentsRaw);

			} else {

				$commentsRaw = $comments_first;
			}
        }

		$comments = [];
		foreach ($commentsRaw as $item) {

			$user_name = (!empty($item['user_first_name']) ? $item['user_first_name'] : '');
			if (empty($item['hide_name']) && !empty($item['user_last_name'])) {

				$user_name .= ' '.substr($item['user_last_name'], 0 , 1);
			}

			$comments[$item['id']] = [
				'id'		=> $item['id'],
				'user'		=> $user_name,
				'time'		=> $item['time_format'],
				'rate'		=> $item['rate'],
				'title' 	=> $item['title'],
				'content'	=> $item['content'],
			];

			if(!empty($trip_id) && 'all' !== $trip_id) {

				$comments[$item['id']]['meta'] = [
					'title' 		=> (($item['title']) ? $item['title'].' | ' : '' ).(($reviews_count) ? $reviews_count.' ' : '').'Reviews of '.$item['target_name'].' - TripShock!',
					'description' 	=> ''
				];
			}
		}

		if (!empty($comments)) {
			$commentIds = array_keys($comments);
			$replies = $this->page_review->get_replies($commentIds);
			foreach ($comments as $commentId => &$comment) {
				if (isset($replies[$commentId])) {
					$comment['reply'] = [
						'time'		=> $replies[$commentId]['time_format'],
						'content'	=> $replies[$commentId]['content'],
					];
				}
			}
		}

		return $comments;
	}


	public function get_session_cart_id($session_id) {

		$this->db->query("
			SELECT
				cart_id
			FROM
				cart_session
			WHERE
				session_id = ?",
			$session_id
		);

		if ($cart_id = $this->db->fetch()) return $cart_id;
		else return false;
	}


	public function clear_url($url, $slash_end = true) {

		if(!$url) return '';

		$url_parts = parse_url($url);

		if(isset($url_parts['query'])) $url = $url_parts['path'];

		$url = rawurldecode($url);

		$url = str_replace(array('+'), 'plus', $url);
		$url = str_replace(array('&'), 'and', $url);
		$url = str_replace(array('é'), 'e', $url);

		// Заменяем все символы кроме букв, цифр и слеша "/" на пробел.
		$url = preg_replace('#[^\w^/]#su', ' ', $url);

		$trim_parts = explode('/', $url);
		$trim_parts = array_map('trim', $trim_parts);

		$url = implode('/', $trim_parts);
		$url = preg_replace('#\s+#su', '-', trim($url));

		$url = rtrim($url, '/') . (($slash_end) ? '/' : '');

		if(isset($url_parts['query'])) $url .= '?'.$url_parts['query'];

		return $url;
	}


	private function catch_promo_for_getters() {

		if ($pcode = $this->url->get_str('promocode')) {

			$this->db->query("
				SELECT
					promocodes.*
				FROM
					promocodes
				WHERE
					promocodes.code = ?
						AND
					promocodes.unit = 0",
				$pcode
			);
			$db_res = $this->db->result;

			if ($promo_amount = $this->db->fetch($db_res)) {

				if ($this->price->check_promo_status($promo_amount['id'])) {

					f2b2h::$static['config']['active_percent_discount'] = $promo_amount['amount'];
					f2b2h::$static['config']['active_percent_discount_total'] = $promo_amount;
				}
			}
		}
	}

	private function get_available_trips($needed_trip_ids, $all_trips_ids, $limit, $date_from, $date_to) {

		if(!$needed_trip_ids || !is_array($all_trips_ids) || !$limit || !$date_from || !$date_to) return [];

		$fh_is_available = (strtotime("+30 days") < strtotime($date_to));

		// В данном случае $limit передается как offset
		$next_trips_ids = array_slice($all_trips_ids, $limit);

		// Получаем информацию по доступности для трипов после пагинации
		$trips_rates = $this->page_trip->get_trips_tickets_rates(
			$needed_trip_ids,
			$date_from,
			$date_to,
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
				products.id IN (".$this->db->in(($all_trips_ids)?: $needed_trip_ids).")
					AND
				products.fareharbor_id IS NOT NULL"
		);
		$db_res = $this->db->result;

		while ($fh_trip = $this->db->fetch($db_res)) $fh_trips[$fh_trip['id']] = $fh_trip['fareharbor_id'];

		// Для пагинационных подменяем доступность в общем  массиве на валидную
		// А остальные помним на случай если в найденных есть недоступные и будем брать новые
		foreach ($needed_trip_ids as $needed_trip_id) {

			if (isset($fh_trips[$needed_trip_id])) {

				if ($fh_is_available) {

					$trips_rates[$needed_trip_id] = array('not_avail' => false);
				}
				else {

					$fh_availability = $this->page_trip->get_fh_availability(
						$fh_trips[$needed_trip_id],
						$date_from,
						$date_to
					);

					if ($fh_availability) {

						// Поскольку в данном методе проверяется ключ not_avail, а для FH трипов мы всегда
						// форсим его 1, то тут подменяем его ради того, чтобы не переделывать проверки ниже
						$fh_availability['not_avail'] = $fh_availability['not_avail_ts'];

						$trips_rates[$needed_trip_id] = $fh_availability;
					}
				}
			}
		}

		$excluded_trips_count = 0;

		// Исключаем не доступные по всему периоду трипы
		foreach ($trips_rates as $trip_id => $days_info) {

			if ($days_info['not_avail']) {

				$excluded_trips_count++;

				unset($needed_trip_ids[array_search($trip_id, $needed_trip_ids)]);
			}
		}

		// Если кого-то исключили, то выбираем из дальнейших трипов по одному, пока не найдём достаточно доступных
		while ($next_trips_ids && $excluded_trips_count) {

			$current_trip = array_shift($next_trips_ids);

			if (isset($fh_trips[$current_trip])) {

				if ($fh_is_available) {

					$trip_rates = array('not_avail' => false);
				}
				else {

					$trip_rates = $this->page_trip->get_fh_availability(
						$fh_trips[$current_trip],
						$date_from,
						$date_to
					);

					if ($trip_rates) $trip_rates['not_avail'] = $trip_rates['not_avail_ts'];
				}
			}
			else {

				$trip_rates = $this->page_trip->get_trips_tickets_rates(
					$current_trip,
					$date_from,
					$date_to,
					true
				);
			}

			if ($trip_rates && !$trip_rates['not_avail']) {

				// Добавляем всегда в конец чтобы не нарушить упорядочивание, идущее из поиска
				array_push($needed_trip_ids, $current_trip);
				$excluded_trips_count--;
			}
		}

		return array_values($needed_trip_ids);
	}

	// ------------------ Destinations ----------------

	/**
	 * Get destinations list
	 */
	public function mode_get_destinations() {

		$this->catch_promo_for_getters();

		$this->add_page('photo');
		$this->add_page('trip');
		$this->add_page('activity');
		$this->add_page('search');

		$default_activities = $this->page_activity->get_list();

		$destinations = array();

		$dest_id = $this->url->get_int('id');
		$is_short = $this->url->get_int('is_short');

		$hidden_categories_dest_ids = [12];

		$where = [];
		$values = [];

		$where[] = "cities.view = 1";

		if($dest_id){

			$where[] = 'cities.id = ?';
			$values[] = $dest_id;
		}
		// На самом деле основной таблицей является city, но семантика более релевантна destinations
		$this->db->query("
			SELECT
				cities.id,
				cities.name,
				cities.state_id,
				cities.country_id,

				states.name AS state_name,
				states.code AS state_code,
				countries.name AS country_name,
				
				destination.id AS dest_id,
				destination.is_popular,
				destination.known_for,
				destination.visitor_guide_short,
				destination.visitor_guide
			FROM
				cities 
				JOIN states ON states.id = cities.state_id
				JOIN countries ON countries.id = cities.country_id
				LEFT JOIN destination ON destination.city_id = cities.id
			WHERE "
				.($where ? implode("\r\n AND ", $where) : "")."
			ORDER BY
				cities.name ASC",
			$values
		);
		$db_res = $this->db->result;

		while ($destination = $this->db->fetch($db_res)) {

			if (!is_null($destination['is_popular'])) {

				$destination['is_popular'] = ($destination['is_popular'] ? true : false);

				$destination['url'] = $this->clear_url(get_city_seo_link($destination['id'], 'destination'));
				$destination['url_search'] = $this->clear_url(get_city_seo_link($destination['id'], 'attractions', true, true));

				$destination['url_partners'] = $this->clear_url(get_city_seo_link($destination['id'], 'partners', true, true));
				$destination['url_stories'] = $this->clear_url(get_city_seo_link($destination['id'], 'traveler-stories'));
				$destination['url_blog'] = $this->clear_url(get_city_seo_link($destination['id'], 'blog'));

				$destination['image_url'] = $this->page_photo->get_photo_link($destination['dest_id'], null, null, 'destination');
				$destination['image_thumb_url'] = $this->page_photo->get_photo_link($destination['dest_id'], null, 't2', 'destination');
			}
			else {

				$destination['is_popular'] = false;
				$destination['known_for'] = '';
				$destination['visitor_guide_short'] = '';
				$destination['visitor_guide'] = '';
				$destination['url'] = '';
				$destination['url_search'] = $this->clear_url(get_city_seo_link($destination['id'], 'attractions', true, true));
				$destination['url_partners'] = '';
				$destination['url_stories'] = '';
				$destination['url_blog'] = '';
				$destination['image_url'] = '';
				$destination['image_thumb_url'] = '';
			}

			unset($destination['dest_id']);

			$trips_for_count = $this->page_search->search_trips(array (
				'status'				=> 1,
				'ignore_avail_by_rates'	=> 1,
				'city_id'				=> $destination['id'],
			), false );

			$trips_count = count($trips_for_count);

			if($trips_count >= 10 ) $meta_top_count = 10;
			elseif($trips_count >= 5) $meta_top_count = 5;
			else $meta_top_count = 0;

			// Мета информация для страниц типа: /destination/FL/Destin/
			$destination[($dest_id) ? 'meta' : 'meta_page'] = [
				'title' => $destination['name'].', '.$destination['state_code'].' Visitor\'s Guide, Best Selling Tours & Articles - TripShock!',
				'description' => 'Discover '.$destination['name'].', '.$destination['state_code'].' with our visitor\'s guide and travel articles, then experience it yourself with deals on '.($trips_count ? 'up to '.$trips_count.' ': '').'family-friendly '.$destination['name'].' things to do!'
			];

			if(!$dest_id) {

				// Мета информация для страниц типа: /attractions/Destin/
				$destination['meta_search'] = [
					'title' => 'Top '.($meta_top_count ? $meta_top_count.' ':'').'Attractions, Tours & Things To Do in '.$destination['name'].', '.$destination['state_code'].' - TripShock!',
					'description' => 'Find '.($trips_count ? $trips_count.'+ ' : '').'top attractions & tours in '.$destination['name'].', '.$destination['state_name'].' with photos, rates & availability. Book the best things to do today, this weekend, or in '.date("F").'.'
				];
			}

			unset($trips_for_count);

			// Не возвращаем категории для одного трипа
			if(!$dest_id) {

				$destination['activity_categories'] = $this->get_actual_city_activities($destination['id']);

				// Страницы с новыми категориями, для которых результатов в этом городе нет, нам всё равно нужны
				$hidden_activities = $default_activities;

				if($destination['activity_categories']) {

					foreach ($destination['activity_categories'] as &$activity_cat) {

						unset($hidden_activities[$activity_cat['id']]);

						$trips_for_count = $this->page_search->search_trips(array (
							'status'				=> 1,
							'ignore_avail_by_rates'	=> 1,
							'city_id'				=> $destination['id'],
							'activity_id'			=> $activity_cat['id']
						), false );

						$trips_count = count($trips_for_count);

						if($trips_count >= 10 ) $meta_top_count = 10;
						elseif($trips_count >= 5) $meta_top_count = 5;
						else $meta_top_count = 0;

						// Мета информация для страниц типа: /attractions/Destin/Dolphin-Cruises-&-Tours
						$activity_cat['meta'] = [
							'title' => 'Top '.($meta_top_count ? $meta_top_count.' ':'').$activity_cat['name'].' in '.$destination['name'].', '.$destination['state_code'].' '.date('Y').' - TripShock!',
							'description' => 'Find '.($trips_count ? $trips_count.' or more of ' : '').'the best '.$activity_cat['name'].' in '.$destination['name'].', '.$destination['state_name'].' all in one place. Compare photos, rates, and availability and save up to 30% today!',
						];

						unset($trips_for_count);

						if($is_short) {
							$activity_cat = [
								'id' 	=> $activity_cat['id'],
								'meta' 	=> [
									'number' => $trips_count
								]
							];
						}
					}
				}

				if(!$is_short || ($is_short && in_array($destination['id'], $hidden_categories_dest_ids))) {

					// TODO Ужасающий костыль - создаём поисковые страницы для старых категорий как будто они новые
					// TODO т.е. как будто они на самом деле как activities из БД
					$destination['hidden_activity_categories'] = $this->page_trip->get_categories();

					foreach ($destination['hidden_activity_categories'] as &$alt_category) {

						unset($alt_category['count_searches']);
						$alt_category['url'] = $this->clear_url('/attractions/'.format_to_seourl($destination['name']).
							'/'.format_to_seourl($alt_category['name']).'/');


						$trips_for_count = $this->page_search->search_trips(array (
							'status'				=> 1,
							'ignore_avail_by_rates'	=> 1,
							'city_id'				=> $destination['id'],
							'category_id'			=> $alt_category['id']
						), false );

						$trips_count = count($trips_for_count);

						if($trips_count >= 10 ) $meta_top_count = 10;
						elseif($trips_count >= 5) $meta_top_count = 5;
						else $meta_top_count = 0;

						if($is_short) {

							$alt_category = [
								'id' 	=> $alt_category['id'],
								'meta' 	=> [
									'number' => $trips_count
								]
							];
						} else {

							// Мета информация для страниц типа: /attractions/Destin/Dolphin-Cruises-&-Tours
							$alt_category['meta'] = [
								'title' => 'Top '.($meta_top_count ? $meta_top_count.' ':'').$alt_category['name'].' Activities in '.$destination['name'].', '.$destination['state_code'].' '.date('Y').' - TripShock!',
								'description' => 'Find '.($trips_count ? $trips_count.' or more of ' : '').'the best '.$alt_category['name'].' things to do in '.$destination['name'].', '.$destination['state_name'].' all in one place. Compare photos, rates, and availability and save up to 30% today!',
							];
						}

						unset($trips_for_count);
					}

					// TODO То же самое делаем для старых типов - там наоборот id 1,2,3 что тоже выпадает из валидных категорий
					foreach ($this->page_trip->types as $type_name => $type_id) {

						$alt_category = array (
							'id'	=> $type_id,
							'name'	=> $type_name,
							'url'	=> $this->clear_url('/attractions/'.format_to_seourl($destination['name']).
								'/'.format_to_seourl($type_name).'/'),
						);

						$trips_for_count = $this->page_search->search_trips(array (
							'status'				=> 1,
							'ignore_avail_by_rates'	=> 1,
							'city_id'				=> $destination['id'],
							'type_id'				=> $type_id
						), false );

						$trips_count = count($trips_for_count);

						if($trips_count >= 10 ) $meta_top_count = 10;
						elseif($trips_count >= 5) $meta_top_count = 5;
						else $meta_top_count = 0;

						if($is_short) {

							$alt_category = [
								'id' 	=> $alt_category['id'],
								'meta' 	=> [
									'number' => $trips_count
								]
							];
						} else {

							// Мета информация для страниц типа: /attractions/Destin/Dolphin-Cruises-&-Tours
							$alt_category['meta'] = [
								'title' => 'Top ' . ($meta_top_count ? $meta_top_count . ' ' : '') . $type_name . ' Activities in ' . $destination['name'] . ', ' . $destination['state_code'] . ' ' . date('Y') . ' - TripShock!',
								'description' => 'Find ' . ($trips_count ? $trips_count . ' or more of ' : '') . 'the best ' . $type_name . ' things to do in ' . $destination['name'] . ', ' . $destination['state_name'] . ' all in one place. Compare photos, rates, and availability and save up to 30% today!',
							];
						}
						unset($trips_for_count);

						$destination['hidden_activity_categories'][$type_id] = $alt_category;
					}

					//TODO А это уже нужно дело, но пользуемся костыльным решением - добавляем в скрытые категории
					//TODO те новые категории, для которых в этом городе не нашлось активных трипов
					foreach ($hidden_activities as $hidden_activity) {

						$alt_category = array (
							'id'	=> $hidden_activity['id'],
							'name'	=> $hidden_activity['name'],
							'url'	=> $this->clear_url('/attractions/'.format_to_seourl($destination['name']).
								'/'.format_to_seourl($hidden_activity['name']).'/'),
						);

						if($is_short) {

							$alt_category = [
								'id' 	=> $alt_category['id'],
								'meta' 	=> [
									'number' => $trips_count
								]
							];
						} else {

							// Мета информация для страниц типа: /attractions/Destin/Dolphin-Cruises-&-Tours
							$alt_category['meta'] = [
								'title' => 'Top ' . $hidden_activity['name'] . ' Activities in ' . $destination['name'] . ', ' . $destination['state_code'] . ' ' . date('Y') . ' - TripShock!',
								'description' => 'Find the best ' . $hidden_activity['name'] . ' things to do in ' . $destination['name'] . ', ' . $destination['state_name'] . ' all in one place. Compare photos, rates, and availability and save up to 30% today!',
							];
						}
						$destination['hidden_activity_categories'][$hidden_activity['id']] = $alt_category;
					}

					$destination['hidden_activity_categories'] = array_values($destination['hidden_activity_categories']);
				}
			}

			if($is_short) {

				$destination = [
					'id' 				=> $destination['id'],
					'name' 				=> $destination['name'],
					'state' 			=> [
						'name' => $destination['state_name'],
						'code' => $destination['state_code'],
					],
					'image' 			=> [
						'url' 		=> $destination['image_url'],
						'thumb_url' => $destination['image_thumb_url']
					],
					'meta' 				=> $destination['meta_page'],
					'slug' 				=> trim($this->clear_url($destination['name']), '/'),
					'categories' 		=> $destination['activity_categories'],
					'hidden_categories' => (isset($destination['hidden_activity_categories']))
											? $destination['hidden_activity_categories']
											: []
				];

			} elseif($dest_id) {

				$destination['slug'] = trim($this->clear_url($destination['name']), '/');
				$destination['total_activities'] = $trips_count;
				$destination['state'] = [
					'id' 	=> $destination['state_id'],
					'name' 	=> $destination['state_name'],
					'code' 	=> $destination['state_code'],
				];
				$destination['image'] = [
					'url' 		=> $destination['image_url'],
					'thumb_url' => $destination['image_thumb_url']
				];
				$destination['country'] = [
					'id' 	=> $destination['country_id'],
					'name' 	=> $destination['country_name']
				];
				$destination['visitor_guide'] = [
					'short' 	=> $destination['visitor_guide_short'],
					'full' 		=> $destination['visitor_guide']
				];

				unset($destination['state_id']);
				unset($destination['state_name']);
				unset($destination['state_code']);
				unset($destination['image_url']);
				unset($destination['image_thumb_url']);
				unset($destination['country_id']);
				unset($destination['country_name']);
				unset($destination['visitor_guide_short']);
			}

			$destinations[] = $destination;
		}

		if($dest_id)
			$this->send_response(($destinations) ? $destinations[0] : []);
		else
			$this->send_response(array('items' => $destinations));
	}

	/**
	 * Get categories (activities) list
	 */
	public function mode_get_categories() {

		$this->add_page('activity');
		$this->add_page('trip');

		$activities = array();

		$limit = null;
		if (!($offset = $this->url->get_int('offset'))) $offset = $this->default_offset;
		//if (!($limit = $this->url->get_int('limit'))) $limit = $this->default_limit;

		$is_all = $this->url->get_int('all');

		if($is_all) {

			$activities_format = $this->page_activity->get_list([
				'format' => true
			]);

			foreach($activities_format as $id => $name) {
				$activities[] = [
					'id' 	=> $id,
					'name' 	=> $name,
					'slug' 	=> trim($this->clear_url(format_to_seourl($name)), '/')
				];
			}

			$hidden_activity_categories = $this->page_trip->get_categories();
			foreach ($hidden_activity_categories as $alt_category) {

				$activities[] = [
					'id' 	=> $alt_category['id'],
					'name' 	=> $alt_category['name'],
					'slug' 	=> trim($this->clear_url(format_to_seourl($alt_category['name'])), '/')
				];
			}

			foreach ($this->page_trip->types as $type_name => $type_id) {

				$activities[] = array (
					'id'	=> $type_id,
					'name'	=> $type_name,
					'url'	=> trim($this->clear_url(format_to_seourl($type_name)), '/')
				);
			}

		} else {

			$destination_id = $this->url->get_int('destination_id');
			if (($is_featured = $this->url->get_int('is_featured')) && !empty($is_featured)) $is_featured = true;
			else $is_featured = false;

			$params = array();
			if ($destination_id) {

				$params['activity_ids'] = $this->get_actual_city_activities($destination_id, $is_featured, true, true);
				$params['order_by_ids'] = true;

				if ($params['activity_ids']) $activities = $this->page_activity->get_list($params);
			}
			else {

				$activities = $this->page_activity->get_list();
			}

			if (!is_null($limit)) $activities = array_slice($activities, $offset, $limit);

			if($destination_id) {

				$this->db->query("
				SELECT
					cities.name
				FROM
					cities
				WHERE
					cities.id = ?",
					$destination_id
				);

				$destination = $this->db->fetch();
			} else {

				$destination = null;
			}


			foreach ($activities as $id => $activity) {

				$activities[$id]['image_url'] = format_trip_activity_img_link($activity);
				$activities[$id]['url'] = '';

				if($destination) {

					$activities[$id]['url'] = $this->clear_url('/attractions/'.format_to_seourl($destination['name']).
						'/'.format_to_seourl($activity['name']).'/');
				}
			}
		}

		$this->send_response(array('items' => ($activities ? array_values($activities) : array())));
	}

	// ------------------- Activities -----------------

	/**
	 * Get activities (trips) list
	 */
	public function mode_get_activities() {

		$this->catch_promo_for_getters();

		if (($is_full = $this->url->get_int('is_full')) && !empty($is_full)) $is_full = true;
		else $is_full = false;
		if (($get_excluded = $this->url->get_int('get_excluded')) && !empty($get_excluded)) $get_excluded = true;
		else $get_excluded = false;

		$short_list = array (
			'id',
			'name',
			'previewDescription',
			'highlights',
			'url',
			'price',
			'price_strike_out',
			'rating',
			'comment_count',
			'duration',
			'tags',
			'title',
			'is_active'
		);

		$this->add_page('trip');
		$this->add_page('search');
		$this->add_page('photo');
		$this->add_page('comment');
		$this->add_page('city');
		$this->add_page('itinerary');

		$errors = array();

		$category_id = $this->url->get_int('category_id');
		// TODO Ужасный костыль - ищем категорию среди старых категорий или старых типов если она имеет к ним отношение
		// TODO Подход основан на том, что ID activities и categories не пересекаются с разрывом в 300 единиц
		// TODO А types при этом имеют только id 1,2,3 чего тоже нет в activities
		$this->db->query("
			SELECT
				id
			FROM
				categories
			WHERE
				categories.id = ?",
			$category_id
		);

		$is_old_category = false;
		if ($this->db->fetch()) $is_old_category = true;
		$is_old_type = false;
		if (in_array($category_id, $this->page_trip->types)) $is_old_type = true;



		$region_id = $this->url->get_int('region_id');
		$destination_id = $this->url->get_int('destination_id');
		$activity_id = $this->url->get_int('id');
		$itinerary_id = $this->url->get_int('itinerary_id');
		$preview = $this->url->get_int('preview');

		$is_user_search = true;
		$offset = 0;
		$limit = $this->default_limit;
		if (!($session_id = $this->url->get_str('session_id'))) {

			$is_user_search = false;
			if (!($offset = $this->url->get_int('offset'))) $offset = $this->default_offset;
			if (!($limit = $this->url->get_int('limit'))) $limit = $this->default_limit;
		}
		$new_search = $this->url->get_int('new_search');

		if(!empty($activity_id)) $short_list[] = 'city_id';

		$cities = array();
		if ($region_id) {
			$this->db->query("
				SELECT DISTINCT c.id
				FROM cities c
				WHERE c.region_id = ?",
				$region_id
			);

			while ($city = $this->db->fetch()) {
				$cities[] = $city['id'];
			}
		}
		elseif ($destination_id) {
			$cities[] = $destination_id;
		}

		$total_trips = 0;

		if (!$errors) {

			$date_from = ((($temp_date = $this->url->get_str('from')) && ($temp_date != '')) ? $temp_date : null);
			$temp_date = null;
			$date_to = ((($temp_date = $this->url->get_str('to')) && ($temp_date != '')) ? $temp_date : null);

			$dated_search = true;
			if (is_null($date_from) || is_null($date_to)) {

				$dated_search = false;
				$date_from = null;
				$date_to = null;
			} elseif((strtotime($date_from) < strtotime("-1 day"))) {

				$date_from = date("Y-m-d");
			}

			if (
				$is_user_search
					&&
				!is_null($date_from)
					&&
				(
					(strtotime($date_to) < strtotime("today"))
						||
					((strtotime($date_to) - strtotime($date_from))/(3600*24) > 60)
				)
			) {

				$this->send_response([
					'total' => 0,
					'items' => array()
				]);
			}


			$trip_ids = array();
			$no_results = false;

			if(!empty($itinerary_id)) {

				$itinerary = $this->page_itinerary->get($itinerary_id);
				$itinerary_products = json_decode($itinerary['products'], true);

				if(isset($itinerary_products['trips']) && !empty($itinerary_products['trips'])) {

					$search_params = array (
						'status' 				=> 1,
						'ignore_valid_to' 		=> true,
						'product_ids' 			=> $itinerary_products['trips'],
						'ignore_approvement'	=> $preview
					);
				} else {

					$this->send_response([
						'total' => 0,
						'items' => array()
					]);
				}

			} elseif ($is_user_search) {

				$params_hash = md5(strval(json_encode(array (
					'session_id'		=> $session_id,
					'destination_id'	=> $destination_id,
					'region_id'			=> $region_id,
					'category_id'		=> $category_id,
					'from'				=> $date_from,
					'to'				=> $date_to
				))));

				$search_params = array (
					'dateless'				=> (empty($date_from)),
					'date_from'				=> (!empty($date_from) ? format_date_for_db($date_from) : null),
					'date_to'				=> (!empty($date_to) ? format_date_for_db($date_to) : null),
					'activity_id'			=> (($is_old_category || $is_old_type) ? null : $category_id),
					'category_id'			=> ($is_old_category ? $category_id : null),
					'type_id'				=> ($is_old_type ? $category_id : null),
					'city_id'				=> $destination_id,
					'status'				=> 1,
					'ignore_avail_by_rates'	=> 1,
					'ignore_approvement'	=> $preview
				);

				if (!$new_search) {

					$this->db->query("
						SELECT
							search_results
						FROM
							user_search
						WHERE
							hash = ?",
						$params_hash
					);
					if ($saved_search = $this->db->fetch()) {

						if ($saved_search['search_results'] == '') $no_results = true;
						$trip_ids = explode(",", $saved_search['search_results']);
					}
				}
			}
			else {

				$search_params = array (
					'status' 				=> (!empty($activity_id) ? '' : 1),
					'activity_id'			=> (($is_old_category || $is_old_type) ? null : $category_id),
					'category_id'			=> ($is_old_category ? $category_id : null),
					'type_id'				=> ($is_old_type ? $category_id : null),
					'product_id' 			=> (!empty($activity_id) ? $activity_id : null),
					'cities_ids' 			=> $cities ? $cities : null,
					'ignore_valid_to'		=> true,
					'ignore_approvement'	=> (!empty($activity_id) ? 1 : $preview),
					/*'from' 			=> $date_from,
					'to' 			=> $date_to,*/
				);
			}

			// Исключаем disable_booking=1 трипы из результата в случае поиска по датам
			if($dated_search) $search_params['disable_booking'] = 0;

			// Поиск необходимых трипов (Если мы на данный момент ещё не достали их из кеша в БД)
			if (!$trip_ids) {

				$trip_ids = $this->page_search->search_trips($search_params);
			}
			$total_trips = count($trip_ids);


			if ($get_excluded) {

				$this->db->query("
					SELECT
						id
					FROM
						products
					WHERE
						product_type_id = ?
							AND
						is_disabled_ts = 0
							AND
						portal_only = 0
							AND
						id NOT IN (".$this->db->in($trip_ids).")",
					product_type('trip')
				);

				$trip_ids = array();
				while ($trip = $this->db->fetch()) $trip_ids[] = $trip['id'];
				$total_trips = count($trip_ids);
			}

			// Срезаем нужную часть массива
			$needed_trip_ids = array_slice($trip_ids, $offset, $limit);

			// Но если это фронт-поиск с датами, то анализируем доступность и удаляем недоступные
			if ($is_user_search && !is_null($date_from)) {

				$needed_trip_ids = $this->get_available_trips($needed_trip_ids, $trip_ids, $limit, $date_from, $date_to);
				if (count($needed_trip_ids) > 0) $limit = array_search($needed_trip_ids[count($needed_trip_ids)-1], $trip_ids) + 1;

				$trip_ids = array_slice($trip_ids, $limit);
			}
			else if ($is_user_search) {

				$trip_ids = array_slice($trip_ids, $limit);
			}

			if ($is_user_search) {

				$this->db->query("
					INSERT INTO
						user_search
						(
							hash,
							search_results
						)
					VALUES
						(?,?)
					ON DUPLICATE KEY UPDATE
						search_results = ?",
					$params_hash,
					implode(",", $trip_ids),
					implode(",", $trip_ids)
				);
			}

			unset($trip_ids);


			// Ничего не нашлось или закончились результаты в сессии юзер-поиска
			if (!$needed_trip_ids || $no_results) {

				$this->send_response([
					'total' => 0,
					'items' => array()
				]);
			}


			// Получаем полную информацию по выделенным трипам
			$trips = $this->page_trip->get_list(array(
				'ids' => $needed_trip_ids,
				'with_categories' => true,
			));


			// Собираем для сформированного набора $needed_trip_ids все данные и пишем их в результат

			$trip_min_prices = $this->page_trip->get_trips_min_prices($needed_trip_ids, $date_from, $date_to);

			// collect user reviews for each trip
			$comments = array();

			if ($is_full) {

				foreach ($needed_trip_ids as $trip_id) {

					$comments[$trip_id] = $this->get_trip_reviews($trip_id, 0, 10);
					$comments[$trip_id] = array_values($comments[$trip_id]);
				}
			}
			else {

				$comments = $this->page_comment->get_list_bulk($needed_trip_ids, 3);
			}

			$result = [];
			foreach ($needed_trip_ids as $trip_id) {

				// trip prices
				$price = $saving = null;
				if (!empty($trip_min_prices[$trip_id])) {
					list($price, $saving) = $this->page_trip->apply_discount(
						$trips[$trip_id],
						$trip_min_prices[$trip_id]['min_price'],
						val($trip_min_prices[$trip_id], 'strike_out')
					);
				}

				$trips[$trip_id]['price'] = ($price > 0 ? round($price) : null);
				$trips[$trip_id]['price_strike_out'] = (($saving && ($price > 0)) ? round($saving + $price) : null);

				$trips[$trip_id]['url'] = $this->clear_url($this->product->get_main_link($trip_id));
				$trips[$trip_id]['url'] = $this->replace_slug($trips[$trip_id]['url'], $trips[$trip_id]['slug']);
				$trips[$trip_id]['url_search'] = $this->clear_url('/attractions/'.format_to_seourl($trips[$trip_id]['city_name']).'/');

				$trips[$trip_id]['previewDescription'] = cut($trips[$trip_id]['description'], 120);

				if(!empty($activity_id))
					$trips[$trip_id]['is_active'] = (int)$this->page_trip->is_available_front($trips[$trip_id]);

				// formatting activity locations, categories (former activities) and tags (former categories)
				$trips[$trip_id] = $this->format_trip_data($trips[$trip_id]);

				if (!$is_full) {

					$data_trip = $this->db->decode_data($trips[$trip_id]['data']);
					$trips[$trip_id]['highlights'] = cut($data_trip['text']['highlights'], 120);

					$short_data = array();

					foreach ($short_list as $key) {

						if (isset($trips[$trip_id][$key])) $short_data[$key] = $trips[$trip_id][$key];
					}

					$trips[$trip_id] = $short_data;
				}
				else {

					$exclude_fields = [
						'description',
						'departure',
						'highlights',
						'expectations',
						'restrictions',
						'included',
						'excluded',
						'additional',
						'agreement',
						'voucher_only_text',
					];

					foreach ($exclude_fields as $field) {

						if(isset($trips[$trip_id][$field])) unset($trips[$trip_id][$field]);
					}
				}

				// trip reviews
				if (!empty($comments[$trip_id])) {
					$trips[$trip_id]['reviews'] = $comments[$trip_id];
				}

				// Photo
				if ($is_full) {

					$trips[$trip_id]['main_photo_link'] = $this->page_photo->get_photo_link($trip_id);
				}
				$trips[$trip_id]['main_photo_thumb'] = $this->page_photo->get_photo_link($trip_id, null, 't3');

				$trips[$trip_id]['user_photos'] = array();

				$photos = $this->page_photo->get_list(array(
					'product_id' => $trip_id,
					'link_medium' => true,
					'link_icon' => true,
				));

				if ($photos) {

					$counter = 1;

					foreach ($photos as $photo) {

						if (!$is_full && $counter > 2) break;

						$trips[$trip_id]['user_photos'][] = array(
							'full' => $photo['link_main'],
							'thumb' => $photo['link_thumb'],
						);

						$counter += 1;
					}
				}

				if ($is_full) {

					$this->add_page('video');
					$trips[$trip_id]['videos'] = array();

					$videos = $this->page_video->get_list(array(
						'product_id' => $trip_id,
					));

					if ($videos) {

						foreach ($videos as $video) {

							$video_url = $video['code'];

							if ('youtube' === $video['embed_service']) {
								$video_url = "https://www.youtube.com/watch?v=".$video['embed_id'];
							}

							$trips[$trip_id]['videos'][] = array(
								'title' => $video['title'],
								'link' => $video_url,
								'time' => $video['time'],
								'embed_service' => $video['embed_service'],
								'embed_id' => $video['embed_id'],
							);
						}
					}

					$city = $this->page_city->get($trips[$trip_id]['city_id']);

					$trips[$trip_id]['meta'] = [
						'title' => $trips[$trip_id]['name']." - TripShock!",
						'description' => "We guarantee best rates on the ".$trips[$trip_id]['name']." in ".$city['name'].", ".$city['state_code'].". View photos, availability & Book Now; 7 day live support.",
					];

					$trips[$trip_id]['meta_reviews_page'] = [
						'title' => $trips[$trip_id]['rate_count']." Reviews of ".$trips[$trip_id]['name']." - TripShock!",
					];
				}

				$result[] = $trips[$trip_id];
			}

			$this->send_response([
				'total' => $total_trips,
				'items' => $result
			]);
		}
	}

	/**
	 * Get activities (trips) list for v4
	 */
	public function mode_activity() {

		$this->catch_promo_for_getters();

		if (($is_full = $this->url->get_int('is_full')) && !empty($is_full)) $is_full = true;
		else $is_full = false;

		if (($with_tickets = $this->url->get_int('with_tickets')) && !empty($with_tickets)) $with_tickets = true;
		else $with_tickets = false;

		$short_list = array (
			'id',
			'partner_id',
			'name',
			'previewDescription',
			'highlights',
			'url',
			'price',
			'price_strike_out',
			'rating',
			'comment_count',
			'duration',
			'tags',
			'title',
			'is_active'
		);

		$this->add_page('trip');
		$this->add_page('search');
		$this->add_page('photo');
		$this->add_page('comment');
		$this->add_page('city');
		$this->add_page('itinerary');
		$this->add_page('partner');

		$errors = array();

		$category_id = $this->url->get_int('category_id');
		// TODO Ужасный костыль - ищем категорию среди старых категорий или старых типов если она имеет к ним отношение
		// TODO Подход основан на том, что ID activities и categories не пересекаются с разрывом в 300 единиц
		// TODO А types при этом имеют только id 1,2,3 чего тоже нет в activities
		$this->db->query("
			SELECT
				id
			FROM
				categories
			WHERE
				categories.id = ?",
			$category_id
		);

		$is_old_category = false;
		if ($this->db->fetch()) $is_old_category = true;
		$is_old_type = false;
		if (in_array($category_id, $this->page_trip->types)) $is_old_type = true;

		$destination_id = $this->url->get_int('destination_id');
		$activity_id 	= $this->url->get_int('id');
		$itinerary_id 	= $this->url->get_int('itinerary_id');
		$preview 		= $this->url->get_int('preview');
		$internal 		= $this->url->get_int('internal');
		$partner_slug 	= $this->url->get_str('partner_slug');
		$partner_id 	= $this->url->get_int('partner_id');

		if (!($offset = $this->url->get_int('offset'))) $offset = $this->default_offset;
		if (!($limit = $this->url->get_int('limit')) || $limit > $this->default_limit) $limit = $this->default_limit;

		if(!empty($activity_id)) $short_list[] = 'city_id';

		$total_trips = 0;

		if (!$errors) {

			$date_from = ((($temp_date = $this->url->get_str('from')) && ($temp_date != '')) ? $temp_date : null);
			$temp_date = null;
			$date_to = ((($temp_date = $this->url->get_str('to')) && ($temp_date != '')) ? $temp_date : null);

			$dated_search = true;
			if (is_null($date_from) || is_null($date_to)) {

				$dated_search = false;
				$date_from = null;
				$date_to = null;
			} elseif ((strtotime($date_from) < strtotime("-1 day"))) {

				$date_from = date("Y-m-d");
			}

			if (
				$dated_search
				&&
				(
					(strtotime($date_to) < strtotime("today"))
					||
					((strtotime($date_to) - strtotime($date_from))/(3600*24) > 60)
				)
			) {

				$this->send_response([
					'total' => 0,
					'items' => array()
				]);
			}

			if(!empty($itinerary_id)) {

				$itinerary = $this->page_itinerary->get($itinerary_id);
				$itinerary_products = json_decode($itinerary['products'], true);

				if(isset($itinerary_products['trips']) && !empty($itinerary_products['trips'])) {

					$search_params = array (
						'status' 				=> 1,
						'ignore_valid_to' 		=> true,
						'product_ids' 			=> $itinerary_products['trips'],
						'ignore_approvement'	=> $preview
					);
				} else {

					$this->send_response([
						'total' => 0,
						'items' => array()
					]);
				}

			} elseif ($dated_search) {

				$search_params = array (
					'date_from'				=> format_date_for_db($date_from),
					'date_to'				=> format_date_for_db($date_to),
					'activity_id'			=> (($is_old_category || $is_old_type) ? null : $category_id),
					'category_id'			=> ($is_old_category ? $category_id : null),
					'type_id'				=> ($is_old_type ? $category_id : null),
					'city_id'				=> $destination_id,
					'status'				=> 1,
					'ignore_avail_by_rates'	=> 1,
					'ignore_approvement'	=> $preview,
					'disable_booking'		=> 0
				);

			} else {

				$search_params = array (
					'status' 				=> (!empty($activity_id) ? '' : 1),
					'activity_id'			=> (($is_old_category || $is_old_type) ? null : $category_id),
					'category_id'			=> ($is_old_category ? $category_id : null),
					'type_id'				=> ($is_old_type ? $category_id : null),
					'product_id' 			=> (!empty($activity_id) ? $activity_id : null),
					'city_id'				=> $destination_id,
					'ignore_valid_to'		=> true,
					'ignore_approvement'	=> (!empty($activity_id) ? 1 : $preview),
				);
			}

			if($partner_slug && $partner_id) {

				if(($partner = $this->page_partner->get($partner_id)) && $this->clear_url($partner['title'], false) === $partner_slug) {

					$search_params['partner_id'] = $partner_id;
				} else {

					$this->send_response([
						'total' => 0,
						'items' => array()
					]);
				}
			}

			// Исключаем из выдачи fareharbor трипы если передан параметр internal
			if($internal) $search_params['exclude_fareharbor_trips'] = true;

			$trip_ids = $this->page_search->search_trips($search_params);

			$total_trips = count($trip_ids);

			// Срезаем нужную часть массива
			$needed_trip_ids = array_slice($trip_ids, $offset, $limit);

			// Но если это фронт-поиск с датами, то анализируем доступность и удаляем недоступные
			if ($dated_search) {

				// Передаем пустой массив вторым параметром чтобы исключить добор доступных трипов
				$needed_trip_ids = $this->get_available_trips($needed_trip_ids, [], $limit, $date_from, $date_to);
			}

			unset($trip_ids);

			// Ничего не нашлось
			if (!$needed_trip_ids) {

				$this->send_response([
					'total' => 0,
					'items' => array()
				]);
			}

			// Получаем полную информацию по выделенным трипам
			$trips = $this->page_trip->get_list(array(
				'ids' => $needed_trip_ids,
				'with_categories' => true,
			));

			// Собираем для сформированного набора $needed_trip_ids все данные и пишем их в результат

			$trip_min_prices = $this->page_trip->get_trips_min_prices($needed_trip_ids, $date_from, $date_to);

			// collect user reviews for each trip
			$comments = array();

			if ($is_full) {

				foreach ($needed_trip_ids as $trip_id) {

					$comments[$trip_id] = $this->get_trip_reviews($trip_id, 0, 10);
					$comments[$trip_id] = array_values($comments[$trip_id]);
				}
			}
			else {

				$comments = $this->page_comment->get_list_bulk($needed_trip_ids, 3);
			}

			$result = [];
			foreach ($needed_trip_ids as $trip_id) {

				// trip prices
				$price = $saving = null;
				if (!empty($trip_min_prices[$trip_id])) {
					list($price, $saving) = $this->page_trip->apply_discount(
						$trips[$trip_id],
						$trip_min_prices[$trip_id]['min_price'],
						val($trip_min_prices[$trip_id], 'strike_out')
					);
				}

				$trips[$trip_id]['price'] = ($price > 0 ? round($price) : null);
				$trips[$trip_id]['price_strike_out'] = (($saving && ($price > 0)) ? round($saving + $price) : null);

				$trips[$trip_id]['url'] = $this->clear_url($this->product->get_main_link($trip_id));
				$trips[$trip_id]['url'] = $this->replace_slug($trips[$trip_id]['url'], $trips[$trip_id]['slug']);
				$trips[$trip_id]['url_search'] = $this->clear_url('/attractions/'.format_to_seourl($trips[$trip_id]['city_name']).'/');

				$trips[$trip_id]['previewDescription'] = cut($trips[$trip_id]['description'], 120);

				if(!empty($activity_id))
					$trips[$trip_id]['is_active'] = (int)$this->page_trip->is_available_front($trips[$trip_id]);

				// formatting activity locations, categories (former activities) and tags (former categories)
				$trips[$trip_id] = $this->format_trip_data($trips[$trip_id]);

				if (!$is_full) {

					$data_trip = $this->db->decode_data($trips[$trip_id]['data']);
					$trips[$trip_id]['highlights'] = cut($data_trip['text']['highlights'], 120);

					$short_data = array();

					foreach ($short_list as $key) {

						if (isset($trips[$trip_id][$key])) $short_data[$key] = $trips[$trip_id][$key];
					}

					$trips[$trip_id] = $short_data;
				}
				else {

					$exclude_fields = [
						'description',
						'departure',
						'highlights',
						'expectations',
						'restrictions',
						'included',
						'excluded',
						'additional',
						'agreement',
						'voucher_only_text',
					];

					foreach ($exclude_fields as $field) {

						if(isset($trips[$trip_id][$field])) unset($trips[$trip_id][$field]);
					}
				}

				// trip reviews
				if (!empty($comments[$trip_id])) {
					$trips[$trip_id]['reviews'] = $comments[$trip_id];
				}

				// Photo
				if ($is_full) {

					$trips[$trip_id]['main_photo_link'] = $this->page_photo->get_photo_link($trip_id);
				}
				$trips[$trip_id]['main_photo_thumb'] = $this->page_photo->get_photo_link($trip_id, null, 't3');

				$trips[$trip_id]['user_photos'] = array();

				$photos = $this->page_photo->get_list(array(
					'product_id' => $trip_id,
					'link_medium' => true,
					'link_icon' => true,
				));

				if ($photos) {

					$counter = 1;

					foreach ($photos as $photo) {

						if (!$is_full && $counter > 2) break;

						$trips[$trip_id]['user_photos'][] = array(
							'full' => $photo['link_main'],
							'thumb' => $photo['link_thumb'],
						);

						$counter += 1;
					}
				}

				if ($is_full) {

					$this->add_page('video');
					$trips[$trip_id]['videos'] = array();

					$videos = $this->page_video->get_list(array(
						'product_id' => $trip_id,
					));

					if ($videos) {

						foreach ($videos as $video) {

							$video_url = $video['code'];

							if ('youtube' === $video['embed_service']) {
								$video_url = "https://www.youtube.com/watch?v=".$video['embed_id'];
							}

							$trips[$trip_id]['videos'][] = array(
								'title' => $video['title'],
								'link' => $video_url,
								'time' => $video['time'],
								'embed_service' => $video['embed_service'],
								'embed_id' => $video['embed_id'],
							);
						}
					}

					$city = $this->page_city->get($trips[$trip_id]['city_id']);

					$trips[$trip_id]['meta'] = [
						'title' => $trips[$trip_id]['name']." - TripShock!",
						'description' => "We guarantee best rates on the ".$trips[$trip_id]['name']." in ".$city['name'].", ".$city['state_code'].". View photos, availability & Book Now; 7 day live support.",
					];

					$trips[$trip_id]['meta_reviews_page'] = [
						'title' => $trips[$trip_id]['rate_count']." Reviews of ".$trips[$trip_id]['name']." - TripShock!",
					];
				}

				if ($with_tickets) {

					$this->add_page('trip');
					$trips[$trip_id]['tickets'] = $this->page_trip->get_tickets($trip_id);
				}

				$result[] = $trips[$trip_id];
			}

			$this->send_response([
				'total' => $total_trips,
				'items' => $result
			]);
		}
	}

	/**
	 * Get actual activity availability with full data for the certain dates period
	 */
	public function mode_get_availability() {

		$this->catch_promo_for_getters();

		$this->add_page('trip');

		$data = array();

		$trip_id = $this->url->get_int('id');
		$is_export = $this->url->get_int('export');

		$this->db->query("
			SELECT
				*
			FROM
				products
			WHERE
				products.id = ?",
			$trip_id
		);

		if ($trip = $this->db->fetch()) {

			$date_from = $this->url->get_str('from');
			$date_to = $this->url->get_str('to');

			$make_a_week = false;

			if (empty($date_from)) {

				$date_from = format_date_for_db();
				$make_a_week = true;
			}
			else if (empty($date_to)) {

				$make_a_week = true;
			}

			if ($make_a_week) {

				$date_to = format_date_for_db(strtotime("+6 day", strtotime($date_from)));
			}

			$control_dates = dates_from_interval($date_from, $date_to);

			$max_days = ($is_export ? 30 : 7);
			if (count($control_dates) > $max_days) $control_dates = array_slice($control_dates, 0, $max_days);

			$data = array (
				'is_fh_trip'	=> (!is_null($trip['fareharbor_id']) ? 1 : 0),
				'use_calendar'	=> $trip['use_calendar'],
				'use_schedule'	=> $trip['use_schedule'],
				'days_info'		=> array(),
			);

			if ($is_export) {

				$data['partner_id'] = $trip['partner_id'];
			}

			$calendar_ids = array();
			if ($trip['use_calendar'] && $control_dates) {

				$this->db->query("
					SELECT
						*
					FROM
						calendar
					WHERE
						calendar.product_id = ?
							AND
						calendar.date IN (".$this->db->in($control_dates).")",
					$trip['id']
				);

				while ($calendar_day = $this->db->fetch()) $calendar_ids[$calendar_day['date']] = $calendar_day['id'];
			}

			foreach ($control_dates as $control_date) {

				$day_data = $this->page_trip->get_calendar_day_data (
					(($trip['use_calendar'] && isset($calendar_ids[$control_date])) ? $calendar_ids[$control_date] : null),
					(!$trip['use_calendar'] ? $trip['id'] : null),
					false,
					($data['is_fh_trip'] ? true : false)
				);

				if (!empty($day_data)) {

					if (
						$trip['disable_booking']
							||
						$trip['disabled']
							||
						$trip['is_disabled_ts']
							||
						$day_data['front_booking_disabled']
					) {

						$day_data['schedules'] = array();
						$day_data['additional_options'] = array();
					}

					unset($day_data['is_avail']);

					unset($day_data['default_tickets']);
					unset($day_data['product_disabled']);
					unset($day_data['date_formatted']);
					unset($day_data['is_cut_off']);
					unset($day_data['close_out']);
					unset($day_data['front_booking_disabled']);

					$data['days_info'][$control_date] = $day_data;
				}

				if (!$trip['use_calendar']) break;
			}
		}

		// Пост-обработка данных доступности для нужд фронт-энда
		if (isset($data['days_info']) && !empty($data['days_info'])) {

			foreach ($data['days_info'] as $date => $date_data) {

				$data['days_info'][$date]['is_avail'] = false;

				if (!isset($date_data['schedules']) || is_null($date_data['schedules'])) alter_log('absent_schedules', 'Trip ID: '.$trip_id);

				$post_process_data = $this->page_trip->process_front_schedules($date_data['schedules'], $data['is_fh_trip']);
				$date_data['schedules'] = $data['days_info'][$date]['schedules'] = array_values($post_process_data['schedules_tickets']);
				$date_data['min_price'] = $post_process_data['lowest_schedule_price'];


				$price = $saving = null;
				list($price, $saving) = $this->page_trip->apply_discount(
					$trip,
					$date_data['min_price'],
					0
				);
				$data['days_info'][$date]['min_price'] = ($price > 0 ? round_price($price) : 0);


				$tickets_avail = 0;

				if (isset($date_data['schedules']) && !empty($date_data['schedules'])) {

					$data['days_info'][$date]['is_avail'] = true;

					if (is_null($trip['fareharbor_id'])) {

						$data['days_info'][$date]['additional_options'] = $this->page_trip->get_day_current_options(
							$trip_id,
							$date
						);
					}
					else {

						foreach ($date_data['schedules'] as $index => $schedule) {

							$schedule['fh_tickets_config'] = $this->db->decode_data($schedule['fh_tickets_config']);

							$data['days_info'][$date]['schedules'][$index] = $schedule;

							$data['days_info'][$date]['schedules'][$index]['tickets'] = array_values($schedule['tickets']);
						}
					}

					foreach ($data['days_info'][$date]['schedules'] as $index => $schedule) {

						// TODO это костыль т.к. с календарём и без расписаний общее число билетов считается null, т.е. сколько угодно
						// TODO Но V3 при этом ориентируется на tickets_avail что является унифицированным подходом
						if (
							$trip['use_calendar']
								&&
							(
								!$trip['use_schedule']
									||
								is_null($schedule['tickets_avail'])
							)
								&&
							isset($schedule['tickets'])
						) {

							$schedule['tickets_avail'] = 0;
							foreach ($schedule['tickets'] as $schedule_ticket) {

								$schedule['tickets_avail'] += (($schedule_ticket['avail']) ? $schedule_ticket['avail'] : 0);
							}

							$data['days_info'][$date]['schedules'][$index]['tickets_avail'] = $schedule['tickets_avail'];
						}
						// TODO Конец костыля

						$tickets_avail += $schedule['tickets_avail'];


						$price = $saving = null;
						list($price, $saving) = $this->page_trip->apply_discount(
							$trip,
							$schedule['min_price'],
							0
						);

						$data['days_info'][$date]['schedules'][$index]['min_price'] = ($price > 0 ? round_price($price) : 0);


						$data['days_info'][$date]['schedules'][$index]['inventories'] = array();
						if ($post_process_data['schedules_inventories']) {

							foreach ($post_process_data['schedules_inventories'] as $time => $schedule_inventories) {

								if ($schedule['time'] === $time) {

									$data['days_info'][$date]['schedules'][$index]['inventories'] = array_values($schedule_inventories);
								}
							}
						}

						foreach ($schedule['tickets'] as &$ticket) {

							$price = $saving = null;
							list($price, $saving) = $this->page_trip->apply_discount(
								$trip,
								$ticket['price'],
								$ticket['strike_out']
							);

							$ticket['price'] = ($price > 0 ? round_price($price) : 0);
							$ticket['strike_out'] = (($saving && ($price > 0)) ? round_price($saving + $price) : 0);
						}

						$data['days_info'][$date]['schedules'][$index]['tickets'] = array_values($schedule['tickets']);
					}
				}

				$data['days_info'][$date]['tickets_avail'] = $tickets_avail;


				if (!empty($data['days_info'][$date]['additional_options'])) {

					foreach ($data['days_info'][$date]['additional_options'] as $option_id => $option) {

						if ($option['is_disabled']) {

							unset($data['days_info'][$date]['additional_options'][$option_id]);
						}
						else if ($option['type'] == 'select') {

							foreach ($option['values'] as $value_id => $value) {

								if ($value['is_disabled']) unset($option['values'][$value_id]);
							}

							if ($option['values']) {

								$data['days_info'][$date]['additional_options'][$option_id]['values'] = array_values($option['values']);
							}
							else {

								unset($data['days_info'][$date]['additional_options'][$option_id]);
							}
						}
					}

					$data['days_info'][$date]['additional_options'] = array_values($data['days_info'][$date]['additional_options']);
				}
			}

			$data['days_info'] = array_values($data['days_info']);
		}

		$this->send_response($data);
	}

	/**
	 * Get book-unavailable activity dates for the certain month
	 */
	public function mode_get_month_availability() {

		$this->add_page('trip');

		$data = array();

		$trip_id = $this->url->get_int('activity_id');

		$this->db->query("
			SELECT
				*
			FROM
				products
			WHERE
				products.id = ?",
			$trip_id
		);

		if (($trip = $this->db->fetch()) && $trip['use_calendar'] && ($month = $this->url->get_str('month'))) {

			$format_month = date('Y-m',strtotime($month));
			$current_month = date('Y-m',strtotime('now'));
			$analyse_from = 1;
			$analyse_to = date('t',strtotime($month));

			// Вообще недоступен трип
			if ($trip['disable_booking'] || $trip['disabled'] || $trip['is_disabled_ts']) {

				$analyse_from = intval(($analyse_to + 1));
			}
			// Месяц текущий и до сегодняшнего дня
			else if (!strcmp($format_month,$current_month)) {

				$analyse_from = intval(date('j',strtotime('now')));
			}

			// Проставляем недоступные дни пакетом
			if ($analyse_from > 1) for ($day = 1; $day < $analyse_from; $day++) $data[] = $day;

			$control_dates = array();
			if ($analyse_to >= $analyse_from) {

				$control_dates = dates_from_interval(
					$format_month.(($analyse_from < 10) ? '-0' : '-').$analyse_from,
					$format_month.'-'.$analyse_to
				);
			}

			if ($control_dates) {

				// TODO После того, как заработают интеграции, вызывать здесь не get_calendar_day_data а более
				// TODO низкоуровневый get_trips_tickets_rates - только нужна полная убеждённость, что он всё
				// TODO проанализирует. Или какой-то его предок, который будет делать универсальный анализ лоступности
				$this->db->query("
					SELECT
						*
					FROM
						calendar
					WHERE
						calendar.product_id = ?
							AND
						calendar.date IN (".$this->db->in($control_dates).")",
					$trip['id']
				);

				$calendar_ids = array();
				while ($calendar_day = $this->db->fetch()) $calendar_ids[$calendar_day['date']] = $calendar_day['id'];

				foreach ($control_dates as $control_date) {

					if (isset($calendar_ids[$control_date])) {

						$day_data = $this->page_trip->get_calendar_day_data($calendar_ids[$control_date], null, false, false);

						if (!empty($day_data)) {

							$post_process_data = $this->page_trip->process_front_schedules($day_data['schedules']);

							if (empty($post_process_data['schedules_tickets'])) {

								$data[] = intval(date('j', strtotime($control_date)));
							}
						}
						else {

							$data[] = intval(date('j', strtotime($control_date)));
						}
					}
					else {

						$data[] = intval(date('j', strtotime($control_date)));
					}
				}
			}
		}

		$this->send_response($data);
	}


	/**
	 * Returns N top activities for the provided destinations
	 */
	public function mode_get_recommended_activities()
	{
		$this->catch_promo_for_getters();

		$destId = $this->url->get_int('destination_id');
		$catId = $this->url->get_int('category_id');

		$date_from = $this->url->get_str('from');
		$date_to = $this->url->get_str('to');

		$dated_search = (!empty($date_from) && !empty($date_to));

		if (empty($destId)) {
			$this->send_response();
		}

		if (!($limit = $this->url->get_int('limit')) || $limit > self::DEFAULT_LIMIT) $limit = self::DEFAULT_LIMIT;

		$this->add_page('trip');

		$where = [];
		$values = [];
		$join = [];

		$join[] = "INNER JOIN activities ON products.activity_id = activities.id";

		$where[] = "? IN (products.city_id, products.city_id2, products.city_id3, products.city_id4, products.city_id5, products.city_id6)";
		$values[] = $destId;

		$where[] = 'products.is_approved = 1';
		$where[] = 'products.disabled = 0';
		$where[] = 'products.is_disabled_ts = 0';
		$where[] = 'activities.is_approved = 1';
		$where[] = 'activities.view = 1';

		$join[] = 'INNER JOIN
						products_cities_tier ON products_cities_tier.trip_id = products.id
							AND
						products_cities_tier.city_id = '.$destId;

		if($dated_search) {

			$join['calendar'] = "JOIN calendar ON calendar.product_id = products.id";

			$where[] = "(
							(
								(products.use_calendar = 1)
									AND
								(calendar.date BETWEEN ? AND ?)
							)
								OR
							(products.use_calendar = 0)
						)";

			$values[] = $date_from;
			$values[] = $date_to;
		}

		$all_items = [];

		if($catId) {

			$where[] = "products_cities_tier.tier IN (1,2)";

//			$where[] = "products.partner_id IN (
//							SELECT DISTINCT
//								users.id
//							FROM users
//							WHERE users.tier = 2)";

			$where[] = "products.activity_id = ?";
			$values[] = $catId;
		} else {

			$where[] = "products_cities_tier.tier = 2";

//			$where[] = "products.partner_id IN (
//							SELECT DISTINCT
//								users.id
//							FROM users
//							WHERE users.tier = 3)";
		}

		$this->db->query("
			SELECT 
				activities.id, 
				activities.name,
				
				products.id AS product_id, 
				products.partner_id, 
				products.name AS product_name, 
				products.duration, 
				products.rating, 
				products.rate_count,
				products.slug
				
			FROM products 
			".implode(" \r\n ", $join)."
			".($where ? " WHERE ".implode("\r\n AND ", $where) : "")."
			ORDER BY RAND()",
			$values
		);

		$res = $this->db->result;

		while ($activity = $this->db->fetch($res))
			$all_items[$activity['product_id']] = $activity;

		$return_items = [];

		if($all_items) {

			if($dated_search) {

				$all_trips_ids = array_keys($all_items);
				$needed_trip_ids = array_slice($all_trips_ids, 0, $limit);

				$needed_trip_ids = $this->get_available_trips($needed_trip_ids, $all_trips_ids, $limit, $date_from, $date_to);

				if($needed_trip_ids) {

					foreach ($all_items as $trip_id => $trip_data) {

						if(!in_array($trip_id, $needed_trip_ids)) unset($all_items[$trip_id]);
					}
				} else {

					$all_items = [];
				}

			} elseif($limit < count($all_items)) {

				$all_items = array_slice($all_items, 0, $limit);
			}

			foreach($all_items as $activity) {
				list($price, $saving) = $this->page_trip->calculateMinPrice((int)$activity['product_id']);
				$tripLinks = $this->page_trip->get_link((int)$activity['product_id'], ['main', 'photo'], ['thumb_type' => 'utfull']);

				$analytics_data = $this->product->add_products_to_google_data($activity['product_id']);
				$analytics_data = array_pop($analytics_data);

				$activity_url = $this->clear_url($tripLinks['main']);
				$activity_url = $this->replace_slug($activity_url, $activity['slug']);

				$return_items[] = [
					'name'				=> $activity['product_name'],
					'price'				=> ($price > 0 ? round($price) : null),
					'price_strike_out'	=> (($saving && ($price > 0)) ? round($saving + $price) : null),
					'duration'			=> $activity['duration'],
					'rating'			=> $activity['rating'],
					'reviews_cnt'		=> $activity['rate_count'],
					'url'				=> $activity_url,
					'image_url'			=> $tripLinks['photo'],
					'analytics_data'	=> $analytics_data,
				];
			}
		}

		$this->send_response(['items' => $return_items]);
	}

//	public function mode_get_recommended_activities()
//	{
//		$destId = $this->url->get_int('destination_id');
//		$catId = $this->url->get_int('category_id');
//
//		if (empty($destId)) {
//			$this->send_response();
//		}
//
//		if (!($limit = $this->url->get_int('limit')) || $limit > self::DEFAULT_LIMIT) $limit = self::DEFAULT_LIMIT;
//
//		$this->add_page('trip');
//
//		$where = [];
//		$values = [];
//
//		$where[] = 'products.city_id = ?';
//		$values[] = $destId;
//
//		$where[] = 'products.is_approved = 1';
//		$where[] = 'products.disabled = 0';
//		$where[] = 'activities.is_approved = 1';
//		$where[] = 'activities.view = 1';
//		$where[] = 'users.tier IN (2,3)';
//
//		$items = [];
//
//		if($catId) {
//
//			$where[] = "? IN (products.activity_id, products.activity2_id, products.activity3_id)";
//			$values[] = $catId;
//		}
//
//		// fetch top activities for the given destinations
//		$this->db->query("
//			SELECT
//				activities.id,
//				activities.name,
//
//				products.id AS product_id,
//				products.partner_id,
//				products.name AS product_name,
//				products.duration,
//				products.rating,
//				products.rate_count
//			FROM products
//			INNER JOIN activities ON products.activity_id = activities.id
//			INNER JOIN users ON products.partner_id = users.id
//			".($where ? " WHERE ".implode("\r\n AND ", $where) : "")."
//			ORDER BY RAND(), users.tier DESC, products.partner_id
//			LIMIT ".$limit,
//			$values
//		);
//
//		$res = $this->db->result;
//		while ($activity = $this->db->fetch($res))
//			$items[$activity['partner_id']][] = $activity;
//
//		if(!$catId && ($cnt = count($items)) < $limit) {
//
//			$this->db->query("
//			SELECT
//				activities.id,
//				activities.name,
//
//				products.id AS product_id,
//				products.partner_id,
//				products.name AS product_name,
//				products.duration,
//				products.rating,
//				products.rate_count
//			FROM products
//			INNER JOIN activities ON products.activity_id = activities.id
//			INNER JOIN users ON products.partner_id = users.id
//			WHERE products.city_id <> ?
//				AND products.is_approved = 1 AND products.disabled = 0 AND activities.is_approved = 1 AND activities.view = 1
//				AND users.tier IN (2,3)
//			ORDER BY users.tier DESC, products.partner_id
//			LIMIT ".($limit - $cnt),
//				$destId
//			);
//
//			$res = $this->db->result;
//			while ($activity = $this->db->fetch($res))
//				$items[$activity['partner_id']][] = $activity;
//
//		}
//
//		$return_items = array();
//
//		if($items) {
//
//			// Выбираем рандомный трип если у партнера их несколько
//			foreach($items as $partner_id => $activities)
//				$items[$partner_id] = $activities[array_rand($activities)];
//
//			foreach($items as $partner_id => $activity) {
//				list($price, $saving) = $this->page_trip->calculateMinPrice((int)$activity['product_id']);
//				$tripLinks = $this->page_trip->get_link((int)$activity['product_id'], ['main', 'photo'], ['thumb_type' => 't2']);
//
//				$return_items[] = [
//					'name'				=> $activity['product_name'],
//					'price'				=> ($price > 0 ? round($price) : null),
//					'price_strike_out'	=> (($saving && ($price > 0)) ? round($saving + $price) : null),
//					'duration'			=> $activity['duration'],
//					'rating'			=> $activity['rating'],
//					'reviews_cnt'		=> $activity['rate_count'],
//					'url'				=> $tripLinks['main'],
//					'image_url'			=> $tripLinks['photo'],
//				];
//			}
//		}
//
//		$this->send_response(['items' => $return_items]);
//	}

    /**
     * Returns N user reviews for a trip
     */
    public function mode_get_reviews()
    {

		$review_id = $this->url->get_int('id');

		if (!empty($review_id)) {

			$this->add_page('comment');

			if (
				($comment = $this->page_comment->get($review_id))
					&&
				($activity_id = $this->url->get_int('activity_id'))
					&&
				isset($comment['target_id'])
					&&
				($comment['target_id'] == $activity_id)
			) {

				$user_name = (!empty($comment['firstname']) ? $comment['firstname'] : '');
				if (!empty($comment['lastname'])) $user_name .= ' '.substr($comment['lastname'], 0 , 1);

				$comment['user'] = $user_name;
				$comment['time'] = get_time_ago($comment['time']);

				if (!$comment['parent_id']) {

					$this->db->query("
						SELECT
							comments.time,
							comments.content
						FROM
							comments
						WHERE
							comments.parent_id = ?",
						$review_id
					);

					$reply = $this->db->fetch();

					$comment['reply']['time'] = get_time_ago($reply['time']);
					$comment['reply']['content'] = $reply['content'];
				}

				unset($comment['cart_item_id'], $comment['parent_id'], $comment['target_id'], $comment['rate_id'],
					$comment['is_approved'], $comment['is_approved'], $comment['is_yelp'], $comment['hide_name'],
					$comment['location'], $comment['lastname'], $comment['firstname'], $comment['link'],
					$comment['product_type_id'], $comment['product_owner_id'], $comment['user_id']);
			}
			else {

				$comment = array();
			}

			$result = ['items' => $comment];
			$this->send_response($result);
		}

        $trip_id = $this->url->get_int('activity_id');
        $partner_id = $this->url->get_int('partner_id');

        $this->add_page('trip');

        if (!($offset = $this->url->get_int('offset'))) $offset = 0;
        if (!($limit = $this->url->get_int('limit'))) $limit = self::DEFAULT_LIMIT;

        if (!empty($trip_id) && ($trip = $this->page_trip->get($trip_id)))
            $comments = $this->get_trip_reviews($trip['id'], $offset, $limit);

        elseif(!empty($partner_id))
            $comments = $this->get_trip_reviews('all', $offset, $limit, $partner_id);

        else
            $comments = $this->get_trip_reviews('all', $offset, $limit);

		$result = [
			'items' => array_values($comments)
		];
        $this->send_response($result);
    }


	/**
	 * Adding traveler review to the trip
	 */
    public function mode_add_review() {

		$this->prepare_post_data();

		$this->add_page('comment');

		new request_api_global_comment_edit(array (
			'name'		=> 'api_comment_edit',
			'method'	=> 'post',
			'is_direct'	=> true,
			'vars'		=> array (
				'cart_item_id',
				'key',
				'review_id',
				'review_info[]'
			),
		));
	}


	public function mode_get_promocode() {

		$promocode = $this->url->get_str('promocode');

		if (!$promocode) $this->send_response();

		$this->add_page('promo');
		$promos = $this->page_promo->get(null, $promocode);

		$result = [
			'name' => $promos['name']
		];

		$this->send_response($result);
	}

	public function mode_get_trips_price() {

    	$this->catch_promo_for_getters();

    	$trip_ids = $this->url->get_str('ids');

    	if(!$trip_ids) $this->send_response();

 		$trip_ids = explode( ',', $trip_ids );

 		$this->add_page('trip');

 		$return = [];
 		if($trip_ids) {

			foreach ($trip_ids as $trip_id) {

				list($price, $saving) = $this->page_trip->calculateMinPrice(intval($trip_id));
				$return[] = [
					'id' => $trip_id,
					'price' => ($price > 0 ? round($price) : 0)
				];
 			}
		}

		$this->send_response($return);
	}


	// -------------------- Cart ----------------------

	/**
	 * Add one item to the shopping cart
	 */
	public function mode_add_to_cart() {

		$this->prepare_post_data();

		$this->add_page('trip');

		new request_api_global_trip_add_to_cart(array (
			'name'		=> 'add_to_cart',
			'method'	=> 'post',
			'is_direct'	=> true,
			'vars'		=> array (
				'session_id',
				'data[]',
			),
		));
	}

	/**
	 * Get the shopping cart full contents
	 */
	public function mode_get_cart() {

		$this->add_page('cart');

		$data = array();
		$errors = array();

		if (!($session_id = $this->url->get_str('session_id'))) {

			$errors[] = 'Wrong Session ID';
		}
		else {

			$cart_id = $this->get_session_cart_id($session_id);

			if ($cart_id === false) {

				$errors[] = 'The Session Contains no Cart';
			}
			else if (!($cart = $this->page_cart->get($cart_id))) {

				$errors[] = 'Invalid Cart';
			}
		}

		if (!$errors) {

			if (!empty($cart['lifetime'])) {

				$lifetime = intval($cart['lifetime']);
				$time_difference = strtotime("+".$lifetime." min", strtotime($cart['update_time'])) - time();
			}
			else {

				$lifetime = 0;
				$time_difference = 0;
			}

			$cart_is_expired = false;

			if (empty($cart['order_id']) && $lifetime && ($time_difference <= 0)) {

				$cart_is_expired = true;
			}

			if (!$cart_is_expired) {

				$cart_contents = $this->page_cart->get_contents($cart['id'], 'utfull');

				$data = $cart_contents;
				$data['lifetime'] = $lifetime;
				$data['time_left'] = $time_difference;

				if (isset($data['cart_items'])) {

					foreach ($data['cart_items'] as $index => $cart_item_data) {

						$data['cart_items'][$index]['booked_format'] = format_tickets_string($cart_item_data['tickets']) .
							format_additional_options_string($cart_item_data['additional_options']);

						if ($cart_item_data['additional_options']) {

							$data['cart_items'][$index]['additional_options'] = array_values($cart_item_data['additional_options']);
						}
						if ($cart_item_data['tickets']) {

							$data['cart_items'][$index]['tickets'] = array_values($cart_item_data['tickets']);
						}

						$data['cart_items'][$index]['product_url'] = $this->clear_url($cart_item_data['product_url']);
						$data['cart_items'][$index]['product_url'] = $this->replace_slug($cart_item_data['product_url'], $cart_item_data['product_slug']);
					}
				}
			}
			else {

				$this->page_cart->delete($cart['id']);
				$errors[] = 'Expired Cart';
				$data = array();
			}
		}

		$this->send_response(array (
			'success'	=> ($errors ? false : true),
			'errors'	=> $errors,
			'data'		=> $data,
		));
	}

	/**
	 * Delete the shopping cart and all its items
	 */
	public function mode_cart_delete() {

		$this->add_page('cart');

		$errors = array();

		if (!($session_id = $this->url->get_str('session_id'))) {

			$errors[] = 'Wrong Session ID';
		}
		else {

			$cart_id = $this->get_session_cart_id($session_id);
		}

		if ($cart_id === false) $errors[] = 'The Session Contains no Cart';
		else if (!($cart = $this->page_cart->get($cart_id))) $errors[] = 'Invalid Cart';
		else if (isset($cart['order_id']) && !is_null($cart['order_id'])) $errors[] = 'The Cart Has Been Paid Already';

		if (!$errors) $this->page_cart->delete($cart['id']);

		$this->send_response(array (
			'success'	=> ($errors ? false : true),
			'errors'	=> $errors
		));
	}

	/**
	 * Delete the one cart item
	 */
	public function mode_cart_item_delete() {

		$this->add_page('cart');
		$this->add_page('cart_item');
		$this->add_page('order');

		$order = null;

		$errors = array();

		if (
			!($session_id = $this->url->get_str('session_id'))
				||
			!($cart_item_id = $this->url->get_int('cart_item_id'))
		) {

			$errors[] = 'Wrong Data';
		}
		else {

			$cart_id = $this->get_session_cart_id($session_id);

			if ($cart_id === false) $errors[] = 'The Session Contains no Cart';
			else if (!($cart = $this->page_cart->get($cart_id))) $errors[] = 'Invalid Cart';
			else if (
						isset($cart['order_id'])
							&&
						($order = $this->page_order->get($cart['order_id']))
							&&
						'made' !== $order['order_state']
					) $errors[] = 'The Cart Has Been Paid Already';

			if (
				(
					!($cart_item = $this->page_cart_item->get($cart_item_id))
						||
					(
						!empty($cart)
							&&
						($cart_item['cart_id'] != $cart['id'])
					)
				)
			) {

				$errors[] = 'Invalid Cart Item';
			}
		}

		if (!$errors) {

			$this->page_cart_item->delete($cart_item['id']);

			// Получаем данные корзины после удаления части
			$cart = $this->page_cart->get($cart_id);

			// Удаляем пустую корзину
			if (!$cart['count']) {

				if('made' === $order['order_state']) {

					$this->page_order->delete($order['id']);
				}
				else {

					$this->page_cart->delete($cart['id']);
				}
			}
		}

		$this->send_response(array (
			'success'	=> ($errors ? false : true),
			'errors'	=> $errors
		));
	}

	/**
	 * Extend the shopping cart lifetime for a 5 more minutes
	 */
	public function mode_cart_extend() {

		$this->add_page('cart');

		$errors = array();

		if (!($session_id = $this->url->get_str('session_id'))) {

			$errors[] = 'Wrong Session ID';
		}
		else {

			$cart_id = $this->get_session_cart_id($session_id);
		}

		if ($cart_id === false) $errors[] = 'The Session Contains no Cart';
		else if (!($cart = $this->page_cart->get($cart_id))) $errors[] = 'Invalid Cart';
		else if (isset($cart['order_id']) && !is_null($cart['order_id'])) $errors[] = 'The Cart Has Been Paid Already';

		if (!$errors) {

			// min * sec
			$extend_time = 5 * 60;

			$time_to_die = $cart['lifetime']*60 - (time() - strtotime($cart['update_time']));

			if ($time_to_die > 0) {

				$update_time = strtotime($cart['update_time']) + $extend_time;

				if ($update_time > time()) $update_time = time();

				$this->page_cart->update_lifetime($cart['id'], $cart['lifetime'], $update_time);
			}
			else {

				$errors[] = 'This cart can not be extended';
			}
		}

		$this->send_response(array (
			'success'	=> ($errors ? false : true),
			'errors'	=> $errors
		));
	}


	// -------------------- Promo ---------------------


	/**
	 * Adding a particular promo code to the cart
	 */
	public function mode_promo_apply() {

		$this->add_page('cart');
		$this->add_page('promo');

		$errors = array();

		if (!($session_id = $this->url->get_str('session_id'))) {

			$errors[] = 'Wrong Session ID';
		}
		else {

			$cart_id = $this->get_session_cart_id($session_id);

			if ($cart_id === false) {

				$errors[] = 'The Session Contains no Cart';
			}
			else if (!($cart = $this->page_cart->get($cart_id))) {

				$errors[] = 'Invalid Cart';
			}
		}
		if (
			!($promo_code = $this->url->get_str('code'))
				||
			!($promocode = $this->page_promo->get(null, $promo_code)))  {

			$errors[] = 'There is no promo code '.$promo_code;
		}


		if (!$errors) {

			$status = $this->page_promo->apply($promocode['code'], $cart_id);

			if ($status !== true) $errors[] = $this->lang('promo_apply_error_'.$status);
		}


		$this->send_response(array (
			'success'	=> ($errors ? false : true),
			'errors'	=> $errors,
		));
	}

	/**
	 * Removing any promo code to the cart
	 */
	public function mode_promo_remove() {

		$this->add_page('cart');

		$errors = array();

		if (!($session_id = $this->url->get_str('session_id'))) {

			$errors[] = 'Wrong Session ID';
		}
		else {

			$cart_id = $this->get_session_cart_id($session_id);

			if ($cart_id === false) {

				$errors[] = 'The Session Contains no Cart';
			}
			else if (!($cart = $this->page_cart->get($cart_id))) {

				$errors[] = 'Invalid Cart';
			}
		}


		if (!$errors) {

			$this->page_cart->remove_promo($cart_id);
		}


		$this->send_response(array (
			'success'	=> ($errors ? false : true),
			'errors'	=> $errors,
		));
	}


	// -------------------- User ----------------------


	/**
	 * Saving pseudo user for order payment
	 */
	public function mode_save_user() {

		$this->prepare_post_data();

		$this->add_page('user');

		new request_api_user_save(array (
			'name'		=> 'api_user_save',
			'method'	=> 'post',
			'is_direct'	=> true,
			'vars'		=> array (
				'user_id',
				'user_info[]',
				'agreement_confirm',
			),
		));
	}


	// ------------------- Order ----------------------


	/**
	 * Paying an order, saving all the data and making post-payment actions
	 */
	public function mode_pay_order() {

		// Time limit fail protection
		set_time_limit(300);

		$this->prepare_post_data();

		$this->add_page('order');

		new request_api_global_order_payment(array (
			'name'		=> 'order_payment',
			'method'	=> 'post',
			'is_direct'	=> true,
			'vars'		=> array (
				'session_id',
				'special_row',
				'stripe_token',
				'user_id',
				'send_sms',
				'jamcom',
				'jamtracker',
			),
		));
	}

	/**
	 * Retrieving full order/voucher info
	 */
	public function mode_get_order() {

		$result = array (
			'success'	=> true,
			'data'		=> array(),
			'errors'	=> array(),
		);

		if (!($access_key = $this->url->get_str('accesskey'))) {

			$result['success'] = false;
			$result['errors'][] = 'Wrong access key';
		}
		else {

			// Данные созданного заказа и его частей
			$search_params = array(
				'accesskey' => $access_key,

				'extended_mode' => true,
				'revise_orders_state' => true,
				'single_order' => true,
			);

			$orders_result = $this->api->get_orders($search_params, false);

			if($orders_result['orders']) {

				$order = array_pop($orders_result['orders']);

				if(!empty($order['cart_items'])) {

					foreach ($order['cart_items'] as &$cart_item) {

						foreach ($cart_item['tickets'] as &$ticket) {

							if(!empty($ticket['barcodes'])) {

								foreach ($ticket['barcodes'] as &$barcode) {

									$barcode_image = '/index.php?page=barcode&mode=image&text='.$barcode.'&size=40';
									$image_base64 = base64_encode(file_get_contents($this->config('api_global_endpoint').$barcode_image));

									$barcode = [
										'number' => $barcode,
										'image_base64' => 'data:image/jpeg;base64,'.$image_base64
									];
								}
							}
						}
					}
				}

				$result['data'] = $order;

			} else {

				$result['success'] = false;
				$result['errors'][] = 'Wrong access key';
			}

		}

		$this->send_response($result);
	}


	// ---------------- Static page -------------------


	/**
	 * Get a static page by ID
	 */
	public function mode_get_static_page()
	{
		$this->add_page('static_page');

		$pageId = $this->url->get_int('id');

		if (empty($pageId) || !($page = $this->page_static_page->get($pageId))) {
			$this->send_response();
		}

		$this->send_response($page);
	}



	// ----------------- Deprecated -------------------


	/**
	 * Get blog posts by destinations (cities)
	 */
	public function mode_get_blog_categories()
	{
		$this->deprecated();

		$this->add_page('photo');

		$cityIds = $this->url->get('ids');
		// convert all elements to integer
		$cityIds = array_map('intval', explode(',', $cityIds));
		// filter out empty elements
		$cityIds = array_filter($cityIds, function ($el) { return !empty($el); });
		if (empty($cityIds)) {
			$this->send_response();
		}
		// limit the number of cities
		array_chunk($cityIds, 20);

		if (!($limit = $this->url->get_int('limit')) || $limit > self::DEFAULT_LIMIT) $limit = self::DEFAULT_LIMIT;

		$this->db->query("
			SELECT c.id, c.name, s.name AS state_name, s.code AS state_code, d.id AS dest_id
			FROM cities c  
			JOIN states s ON s.id = c.state_id
			JOIN countries cnt ON cnt.id = c.country_id
			LEFT JOIN destination d ON d.city_id = c.id
			WHERE c.id IN (".join(',', $cityIds).") AND c.view = 1
			ORDER BY c.name ASC"
		);
		$db_res = $this->db->result;

		$data = [];
		while ($dest = $this->db->fetch($db_res)) {
			$item = array (
				'name'	=> $dest['name'].', '.$dest['state_code'],
				'url'	=> get_city_seo_link($dest['id'], 'blog')
			);
			$item['destination'] = [
				'name'		=> $dest['name'],
				'id'		=> $dest['id'],
				'state'		=> [
					'name'	=> $dest['state_name'],
					'code'	=> $dest['state_code']
				],
				'url' 		=> get_city_seo_link($dest['id'], 'destination'),
				'image_url' => $this->page_photo->get_photo_link($dest['dest_id'], null, null, 'destination'),
			];

			$this->db->query("
				SELECT p.id, p.title, p.publish_date, p.seo_url, p.content
				FROM blog_page p
				WHERE p.city_id = ? AND p.is_published = 1
				ORDER BY p.publish_date DESC",
				$dest['id']
			);
			$res = $this->db->result;

			$item['posts'] = [];
			while ($post = $this->db->fetch($res)) {
				$item['posts'][] = [
					'id'        		=> $post['id'],
					'title'     		=> $post['title'],
					'date'      		=> format_date_for_datepicker($post['publish_date']),
					'tag'       		=> 'article',
					'image_url' 		=> $this->page_photo->get_photo_link($post['id'], null, null, 'blog'),
					'url'				=> '/blog/'.$post['seo_url'].'.html',
					'previewContent'	=> substr($post['content'], 0, strpos($post['content'], '[readmore]')),
				];
			}

			$data[] = $item;
		}

		$this->send_response(array('items' => $data));
	}

	/**
	 * Get a blog post by ID
	 */
	public function mode_get_blog_post()
	{
		$this->deprecated();

		$this->add_page('blog');

		$blogId = $this->url->get_int('id');

		if (empty($blogId) || !($blog = $this->page_blog->get($blogId))) {
			$this->send_response();
		}

		$this->send_response($blog);
	}

	/**
	 * Get a list of blog posts
	 */
	public function mode_get_blog_posts()
	{
		$this->deprecated();

		$this->add_page('blog');

		$this->db->query("
			SELECT COUNT(*) AS total
			FROM
				blog_page
			WHERE
				is_published = 1"
		);
		$total = $this->db->fetch();

		if (!($limit = $this->url->get_int('limit'))) $limit = self::DEFAULT_LIMIT;
		if (!($offset = $this->url->get_int('offset'))) $offset = 0;

		$searchParams = [
			'is_published' 			=> true,
			'order_by_publish_date' => true,
			'limit'					=> $limit,
			'offset'				=> $offset,
		];

		if (!($blogs = $this->page_blog->get_list($searchParams))) {
			$this->send_response();
		}

		$this->send_response(['total' => $total['total'], 'items' => array_values($blogs)]);
	}


	// ---------------- Media -------------------


	/**
	 * Get a list of photos\videos
	 */
	public function mode_get_media()
	{
		$partner_id = $this->url->get_int('partner_id');

		if (!($limit = $this->url->get_int('limit'))) $limit = 25;
		if (!($offset = $this->url->get_int('offset'))) $offset = 0;

		$this->add_page('photo');

		$return = [
			'photos' => [],
			'videos' => [],
		];

		$args = [
			'limit' 		=> $limit,
			'offset' 		=> $offset,
			'order_by' 		=> 'time',
		];

		if($partner_id) $args['partner_id'] = $partner_id;

		$photos = $this->page_photo->get_list($args);
		if($photos) {

			foreach ($photos as $photo) {

				$return['photos'][] = [
					'full' => $photo['link_main'],
					'thumb' => $photo['link_thumb'],
				];
			}
		}

		$this->add_page('video');
		$videos = $this->page_video->get_list($args + [
			'is_approved' 	=> true,
		]);

		if ($videos) {

			foreach ($videos as $video) {

				$return['videos'][] = array(
					'title' 			=> $video['title'],
					'link' 				=> $video['code'],
					'time' 				=> $video['time'],
					'embed_service' 	=> $video['embed_service'],
					'embed_id' 			=> $video['embed_id'],
				);
			}
		}

		$this->send_response($return);
	}


    // ---------------- Partners -------------------


	/**
	 * Get a list of partners
	 */
	public function mode_get_partners()
	{
		if (!($limit = $this->url->get_int('limit'))) $limit = self::DEFAULT_LIMIT;
		if (!($offset = $this->url->get_int('offset'))) $offset = 0;

		$this->add_page('partner');

		$return = array();
		$partners = $this->page_partner->get_list([
			'active_only'   => true,
			'product_type' 	=> 'trip',
			'limit'         => $limit,
			'offset'        => $offset
		]);

		if($partners) {

			foreach ($partners as $partner) {

				$return[] = [
					'title' => $partner['title'],
					'address' => $partner['address'],
					'url' => $this->clear_url('/partners/'.$partner['id'].'/'.$partner['title'].'/'),
				];
			}
		}

		$this->send_response($return);
	}



    /**
     * Get a long URL by short code
     */
    public function mode_get_url_by_shortcode()
	{

		$shortcode = $this->url->get('shortcode');

		$return = [
			'success' => false,
			'link' => ''
		];

		if (!empty($shortcode) && preg_match("/^([A-Z0-9]{6,8})$/", $shortcode, $matches)) {

			$this->db->query("
				SELECT
					short_code.url
				FROM
					short_code
				WHERE
					short_code.code = ?",
				$shortcode
			);

			$db_res = $this->db->result;

			if ($long_url = $this->db->fetch($db_res)) {

				$return['success'] = true;
				$return['link'] = $long_url['url'];
			}
		}

		$this->send_response($return);
	}



    /**
     * Get a list of cities\trips for homepage
     */
	public function mode_get_homepage_destinations()
	{

		$this->add_page('trip');
		$this->add_page('search');
		$this->add_page('destination');

		$this->db->query("
			SELECT
				products.id,
				products.product_type_id,
				products.city_id,

				cities.name AS city_name,

				states.name AS state_name,
				states.code AS state_code,
				
				destination.id AS destination_id
			FROM
				products
				JOIN cities ON cities.id = products.city_id
				JOIN states ON states.id = cities.state_id
				JOIN users ON users.id = products.partner_id
				LEFT JOIN destination ON destination.city_id = cities.id
			WHERE
				products.disabled = 0
					AND
				products.is_disabled_ts = 0
					AND
				products.product_type_id = 1
					AND
				users.rs_only = 0
					AND 
				products.portal_only = 0
					AND
				(
					products.valid_to IS NULL
						OR
					products.valid_to > CURDATE()
				)
					AND
				products.is_top_offer = 1
			ORDER BY
				cities.name ASC, products.name ASC"
		);

		$db_res = $this->db->result;
		$this->add_page('photo');

		$return = [
			'meta' => [
				'title' => "TripShock! - Book The Best Activities, Tours & Things To Do",
				'description' => "The USA's #1 booking website for activities, tours, and things to do. Browse reviews, deals, photos and more from over 1,000+ experiences. Low price guarantee on every booking.",
			]
		];

		while ($product = $this->db->fetch($db_res)) {

			if (empty($return['destinations'][$product['city_id']])) {

				$destination_image = null;
				if (!empty($product['destination_id'])) {

					$destination_image = $this->page_photo->get_photo_link(
						$product['destination_id'],
						null,
						't4',
						'destination'
					);
				}

				// Такой набор параметров точно дублирует тот, что используется в page_destination для аналогичного
				// подсчёта: ставим его таким в надежде на кеш БД и просто для унификации
				$trips_for_count = $this->page_search->search_trips(array (
					'rooms_data'	=> '1_1',
					'status'		=> 1,
					'no_rankings'	=> 1,
					'city_id'		=> $product['city_id'],
				),
					false
				);

				$return['destinations'][$product['city_id']] = array (
					'id'			=> $product['city_id'],
					'link'			=> $this->clear_url(get_city_seo_link($product['city_id'], 'destination')),
					'experiences'	=> count($trips_for_count).' experience'.((count($trips_for_count) > 1) ? 's' : ''),
					'd_image'		=> $destination_image,
					'name'			=> $product['city_name'],
					'state_name'	=> $product['state_name'],
					'state_code'	=> $product['state_code'],
				);
			}

			$short_details = $this->page_trip->get_short_details($product['id']);
			$short_details['link_main'] = $this->clear_url($short_details['link_main']);
			$short_details['link_main'] = $this->replace_slug($short_details['link_main'], $short_details['slug']);

			$analytics_data = $this->product->add_products_to_google_data($product['id']);
			$analytics_data = array_pop($analytics_data);
			$short_details['analytics_data'] = $analytics_data;

			$return['destinations'][$product['city_id']]['activities'][] = $short_details;
		}

		if(!empty($return['destinations'])) $return['destinations'] = array_values($return['destinations']);

		$this->send_response($return);
	}


	// ---------------- Itineraries -------------------


	/**
	 * Get a list of itineraries
	 */
	public function mode_get_itineraries()
	{
		if (!($limit = $this->url->get_int('limit'))) $limit = self::DEFAULT_LIMIT;
		if (!($offset = $this->url->get_int('offset'))) $offset = 0;

		$slug = $this->url->get_str('slug');

		$this->add_page('itinerary');

		$itineraries = $this->page_itinerary->get_list([
			'limit' 	=> $limit,
			'offset' 	=> $offset,
			'slug' 		=> ($slug?:''),
		]);

		foreach ($itineraries as &$itinerary) {

			unset($itinerary['products']);
			unset($itinerary['seo_name']);

			$itinerary['link'] = $this->clear_url($itinerary['link']);

			$itinerary['meta'] = [
				'title' => $itinerary['name'].' - Itinerary - TripShock!',
				'description' => $itinerary['meta_description']
			];

			unset($itinerary['meta_description']);
		}

		$this->send_response([
			'items' => array_values($itineraries)
		]);
	}

	public function mode_healthcheck() {

		$this->send_response([
			'success' => true
		]);
	}

	private function replace_slug($url, $slug)
	{

		$url = explode('/', $url);
		if(!empty($slug)) $url[1] = $slug;
		$url = implode('/', $url);

		return $url;
	}
}

?>