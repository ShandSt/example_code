
var isIE;
var isIOS;

var isHTTPS;

var header_fn = {

	init: function() {

		if ($('.phone').length) {

			_googWcmGet('phone', config_site_phone);
		}
	}
};

$(document).ready(function(){
	
	isHTTPS = (document.location.protocol == 'https:');

	isIE = $.browser.msie;
	ieFix.init();

	isIOS = (navigator.userAgent.match(/(iPad|iPhone|iPod)/g) ? true : false);

	// watermarks for inputs
	watermarks.init($('input[type="text"], input[type="password"]'));

	// add some classes to engine-generated fields
	datepicker_custom.fix_date_fields();
	// calendar fire function
	datepicker_custom.init();

	// popups funcitions
	// if you want open some popup give link class 'popup_link'	and add data-attribute with additional class of popup. then add This class to popup div
	popup.init();

	// design changes
	designChanges.searchInput();
	designChanges.topLink();

	// checkbox and radion custom
	typeCustom.init($('input[type=checkbox]'));

	// Sidebar activity categories
	if ($('.browse_by .l_more').length) {

		$('.browse_by .l_more').on('click', function() {

			$(this).find('span').toggleClass('hidden');
			$(this).nextAll('.more_items').toggleClass('hidden');
		});
	}

	if ($('.detail_carousel').length) {

		// switchTime - speed of swithing slides
		$('.detail_carousel').detailCarousel({switchTime: 2}); 
	}

	if ($('#promo_applied_message').length) {

		setTimeout(function() {

			$("#promo_applied_message").bPopup();
		}, 4000);
	}

	if ($('#cart_countdown_time').length) {

		time_now = new Date();
		time_countdown = new Date( time_now.getTime() + parseInt($('#cart_countdown_time').html()) * 1000 );

		$('.sign_list .cart_countdown_description .fa').tooltip({
			relative: true,
			offset: [130, 0]
		});


		var cart_popup;
		var cart_popup_shown = false;

		$('#cart_countdown_timer, #cart_expiring_message .timer').countdown(time_countdown)
			.on('update.countdown', function(event) {

				var format = '%M:%S';

				if(event.offset.hours > 0) {

					format = '%H:%M:%S';
				}
				else if ((event.offset.minutes == 0) && !cart_popup_shown) {

					if (typeof(iframe_parent) !== "undefined") {

						cart_popup = $("#cart_expiring_message").bPopup({
							follow: [false, false],
							positionStyle: 'fixed'
						});
					}
					else {

						cart_popup = $("#cart_expiring_message").bPopup();
					}

					cart_popup_shown = true;
				}

				$(this).html(event.strftime(format));
			})
			.on('finish.countdown', function(event) {

				$('.top_header .sign_list a[href="/cart/list/"]').hide();
				if ($('.cart_list').length) $('.cart_list .checkout .green_button').addClass('disabled').removeAttr('href');

				$(this).html('Expired!').addClass('expired');
				$('#cart_expiring_message .button').hide();
				$('#cart_expiring_message .button.removed_products').css('display', 'inline-block');
				cart_popup.bPopup();

				var redirect_pages = '.cart_logon, .order_step1, .order_step2';

				if ($(redirect_pages).length) {

					location.pathname = '/cart/list/';
				}
			});
	}

	// run custom selects if exist
	select_design.apply($('select:not(#site-footer select):visible'));

	header_fn.init();

	if ($('.breadcrumbs').length) breadcrumbs.made_last_crumb();

	// Products search ajax
	if ($('.loading_products').length) search.init();

	// Products search form
	if ($('.form_search').length) search_form.init();

	// Header relevant search autocomplete
	if ($('.relevant_search').length) quickSearch.init();

	if ($('#user_info').length) ucp.init_register();

	if ($('.deal_item').length) book.init_common();
	if ($('.room_table').length) book.init_hotels();
	if ($('.book_trip').length) book.init_activities();

	if ($('.product_info .no_voucher').length) $('.product_info .no_voucher').tooltip();

	if ($('.cart_contents').length) {

		book.init_activities();

		cart.contents();
	}

	if ($('.page_cart').length) {	

		if ($('.page_cart .change_trip_id').val() != 0) book.show_popup_cart();
	}

	if ($('.page_order').length) page_order.init();

	if ($('#zoom-slider').length) zoom_slider_control.init();

	if ($('.login_form').length) ucp.init_logon();

	if ($('.glossary').length) glossary.init();

	if ($('.order_view, .ucp_profile').length) page_order.mode_view();

	if ($('.page_index').length) page_index.mode_default();

	if ($('.deal_view').length) page_deal.mode_view();

	if ($('.page_destination.destination_view').length) page_destination.mode_view();

	if ($('.page_comment').length) page_comment.init();

	if ($('.hotel_details, .trip_details').length) product_details.init();

	if ($('.trip_details').length) {	

		book.init_activities();

		if ($('.trip_details .change_trip_id').val() != 0) book.show_popup_cart();
	}

	if ($('.review_list').length) product_details.process_large_reviews();

	if ($('.itinerary_view').length) {

		send_search_results_to_analytics('.itinerary_view');
	}

	if ($('.partner_reviews_widget').length) partner.reviews_widget();

	// run tab function adn photo gallery
	if ($('.content_tabs').length){
		tabs.init();
		gallery.init();

		if ($('.single_review').length) tabs.change('reviews');
	}

	// run full text functionality right sidebar
	if ($('.full_text').length){
		showFull.init();
	}
	// run full text functionality right sidebar
	if ($('.full_click').length){
		showFullClick.init();
	}

	// run profile dots functionality
	if ($('.profile_detail').length){
		dots.init();
	}

	form_errors.init();

	bing_search.init();
});


var breadcrumbs = {

	made_last_crumb: function() {

		var container_length = $('.breadcrumbs').closest('div').width();
		var crumbs_length = $('.breadcrumbs').width();

		var difference = container_length - crumbs_length - 10;

		if (difference > 0) $('.breadcrumbs .is_last a').css('max-width', (crumbs_length+difference)+'px');
	}
};

var bing_search = {

	page_num: 1,
	search_query: '',

	$bing_form: $('#bing_search_form'),
	$bing_popup: $('.popup_bing_search_results'),

	request: function(query, page) {

		var _page = page || this.page_num;

		if (!query) return false;

		this.$bing_popup.find('.results').css({
			opacity: '0.6'
		});

		$.ajax({
			url: '/index.php?page=search&mode=bing_site_search&query=' + query + '&page_num=' + _page,
			type: 'get',
			dataType: 'html',
			success: function(html) {

				if (html.replace(/[\r|\n|\s]/gm, '') != '') {

					bing_search.$bing_popup.find('.results').html(html);
					bing_search.$bing_popup.addClass('active');
					bing_search.$bing_popup.find('.results').css({
						opacity: '1'
					});
				}
			}
		});
	},
	init: function() {

		if (this.$bing_form.length <= 0 || this.$bing_popup.length <= 0) return false;

		this.$bing_form.on('submit', function(e) {

			e.preventDefault();

			bing_search.search_query = bing_search.$bing_form.find('input[type="text"]').val();

			if ('' == bing_search.search_query) return false;

			bing_search.request(bing_search.search_query, bing_search.page_num);

			return false;
		});

		this.$bing_popup.on('click', '.popup_close', function() {

			bing_search.$bing_popup.find('.results').html('');
			bing_search.$bing_popup.removeClass('active');
		});

		this.$bing_popup.on('click', '.bing_pagination > span', function() {
            
			var $this = $(this),
				page = $this.data('page');

			$this.siblings().removeClass('current');
			$this.addClass('current');

			bing_search.request(bing_search.search_query, page);
		});
	}
};

var search = {

	page_num: 1,
	product_type: '',
	order_by: '',

	block_page_load: true,

	load_page: function() {

		if (search.page_num !== 1) {

			$('.search_controls .loading_products .search_results_placeholder').html('Please Wait, Loading More Activities...');
		}
		$('.search_controls .loading_products').show();

		search_page = '';
		order_by = '';

		if (this.product_type == 'trip') {

			search_page = 'page=trip&mode=search';
			search_params = search_common.get_url_params_string('trip');

			if (this.order_by) order_by = '&no_cache&order='+this.order_by;
		}
		else if (this.product_type == 'hotel') {

			search_page = 'page=hotel&mode=search_page';
			search_params = search_common.get_url_params_string('hotel');

			if (this.order_by) order_by = '&no_cache&order_by='+this.order_by;
		}

		if (!search_page) return;

		$.ajax({
			url: '/index.php?'+search_page+search_params+order_by+'&page_num='+search.page_num,
			type: 'get',
			dataType: 'html',
			success: function(html) {

				$('.loading_products').hide();

				if (html.replace(/[\r|\n|\s]/gm, '') != '') {

					search.page_num++;


					var $html = $(html);
					
					$('.top_deals .search_controls').before($html);
					
					book.init_common();

					if (search.product_type == 'hotel') {

						book.init_hotels();
					}
					else {

						book.init_activities();
					}

					if (typeof(iframe_parent) !== "undefined") prepare_links_for_iframe();

					send_search_results_to_analytics('.search_result');


					if (!$('.last_products').length) {

						search.block_page_load = false;

						$('.next_page').show();
					}
					else search.block_page_load = true;
				}
				else {

					$('.next_page').hide();

					if (search.page_num == 1) $('.nothing_found').show();
				}
			}
		});
	},
	init: function() {

		if ($('.trips_search').length) this.product_type = 'trip';
		else if ($('.hotel_search').length) this.product_type = 'hotel';

		this.load_page();

		$(window).scroll(function() {

			if (
				($('.deal_item:last').length > 0)
					&&
				($(window).scrollTop() > ($('.deal_item:last').position().top - 800))
					&&
				!search.block_page_load
				) {

				search.block_page_load = true;

				search.load_page();
			}
		});

		$('.search_order_by .tabs_list a').on('click', function() {

			if ($(this).parent().hasClass('active')) return;

			$('.search_order_by .tabs_list .active').removeClass('active');
			$(this).parent().addClass('active');

			search.page_num = 1;
			search.order_by = $(this).parent().data('searchOrder');

			$('.search_result .deal_item, .search_result .advertisement').remove();

			search.load_page();
		});
	}
};

var search_form = {

	default_room: {
		adults: 1,
		children: 0,
		children_ages: []
	},

	update_city_activities_list: function(city_id) {

		activity_id = (activity_id == '' ? 0 : parseInt(activity_id));

		if (isUndefined(cities[city_id])) return;

		city_activities = cities[city_id]['activities'];

		if (!$('#search_type_transportation').closest('li').find('label:first').hasClass('active_link')) {

			activities_html = '<option value="0">Any</option>';
		}
		else {

			activities_html = '';
		}

		for (activity in city_activities) {

			if (isUndefined(activities[city_activities[activity]])) continue;

			activity = activities[city_activities[activity]];

			selected = '';
			if (activity['id'] == activity_id) selected = ' selected';

			activities_html += '<option value="'+activity['id']+'"'+selected+'>'+activity['name']+'</option>';
		};

		if (activities_html === '') activities_html = '<option value="0">No services found</option>';

		$('#trip_search_activity').empty().append(activities_html);
		$('#trip_search_activity').trigger("chosen:updated");
	},

	room_handlers: function(room) {

		$('.f_remove', room).unbind('click').on('click', function() {

			$(this).closest('.room').remove();

			var room_index = 1;
			$('.rooms_filter .room .room_number').each(function() {

				$(this).text(room_index);

				room_index++;
			});
		});

		$('.children select', room).on('change', function() {

			search_form.change_room_children_ages_inputs($(this).closest('.room'));
		});
	},
	change_room_children_ages_inputs: function(room, children_ages) {

		children = parseInt($('.children :selected', room).val());
		current_ages_inputs = $('.children_ages .child_age', room);
		current_ages_inputs_count = $(current_ages_inputs).length;

		if (current_ages_inputs_count < children) {

			for (var i = current_ages_inputs_count; i < children; i++) {

				child_age = '';
				if (!isUndefined(children_ages) && !isUndefined(children_ages[i])) child_age = children_ages[i];

				$('.children_ages', room).append('<input type="text" class="input_text child_age" value="'+child_age+'" />');
			}
		}
		else if (current_ages_inputs_count > children) {

			for (var i = (current_ages_inputs_count - 1); i >= children; i--) {

				$(current_ages_inputs[i]).remove();
			}
		}

		if ($('.children_ages .child_age', room).length) {

			$('.children_ages', room).show();

			watermarks.init($('.children_ages .child_age', room));
		}
		else $('.children_ages', room).hide();
	},
	add_room: function(room_data) {

		var is_hotel_details = $('.content_tabs .tab_rooms').length;

		room_number = $('.rooms_filter .room').length + 1;

		var new_html_room = '\
			<div class="room">\
				<label>\
					'+(room_number != 1 ? '<i class="fa fa-times-circle f_remove"></i>' : '')+'\
					Unit <span class="room_number">'+room_number+'</span>\
					'+(room_number == 1 ? ' <span>(adults / children)</span>:' : '')+'\
				</label>\
				<span class="room_container adults">\
					<select>\
						'+make_serial_select_options(1, 10, room_data.adults)+'\
					</select>\
				</span>\
				<span class="room_guests_separator">/</span>\
				<span class="room_container children">\
					<select>\
						'+make_serial_select_options(0, 8, room_data.children)+'\
					</select>\
				</span>'+
				((is_hotel_details && (room_number === 1)) ? '<input type="button" class="submit_gray add_room" value="+  Add unit" />' : '')
				+'<div class="children_ages hidden">\
					<label><span>children ages</span></label>\
				</div>\
				<div class="filter_separator room_separator"><!-- --></div>\
			</div>\
		';

		if (is_hotel_details) {

			$('.rooms_filter').append(new_html_room);
		}
		else {

			$('.rooms_filter .add_room').before(new_html_room);
		}

		select_design.apply($('.rooms_filter .room:last select'));

		this.change_room_children_ages_inputs($('.rooms_filter .room:last'), room_data.children_ages);
		this.room_handlers($('.rooms_filter .room:last'));
	},
	form_rooms_data_string: function() {

		rooms_string = '';
		has_errors = false;

		$('.rooms_filter .room').each(function() {

			var room_number = parseInt($('.room_number', this).text());

			adults = parseInt($('.adults :selected', this).val());

			if (!adults) {

				$('.adults .custom-select', this).addClass('wrong_data');
				has_errors = true;
			}
			else rooms_string += room_number+'_'+adults+',';

			$('.children_ages .child_age', this).each(function() {

				child_age = parseInt($(this).val());

				if (!child_age || (child_age < 1) || (child_age > 17)) {

					$(this).addClass('wrong_data');
					has_errors = true;
				}
				else rooms_string += room_number+'_'+child_age+',';
			});
		});

		if (!rooms_string) rooms_string = '1_1';
		else rooms_string = rooms_string.substr(0, rooms_string.length - 1);

		$('.rooms_data_string').val(rooms_string);

		return !has_errors;
	},
	change_active_sidebar_button: function(input) {

		$(input).closest('ul').find('li').each(function() {

			$(this).find('label:first').removeClass("active_link");
		});

		$(input).closest('li').find('label:first').addClass("active_link");
	},
	init: function() {

		// Activities
		if ($('.tab_search_activities').length) {

			city_id = (isUndefined(city_id) || (city_id == '') ? 0 : parseInt(city_id));

			search_form.update_city_activities_list(city_id);

			$('#trip_search_city').change(function() {

				search_form.update_city_activities_list($(this).val());
			});

			$('.tab_search_activities form').submit(function() {

				if (($('#trip_search_date_from').val() == 'Any date') || !$('#trip_search_date_from').val()) {

					$('#trip_search_date_from').removeAttr('name');
				}

				if (($('#trip_search_date_to').val() == 'Any date') || !$('#trip_search_date_to').val()) {

					$('#trip_search_date_to').removeAttr('name');
				}

				return true;
			});
		}

		// Hotels
		if ($('.tab_search_hotels').length || $('.content_tabs .tab_rooms').length) {

			if (rooms_data.length == 0) rooms_data.push(this.default_room);

			$.each(rooms_data, function(index, room_data) {

				search_form.add_room(room_data);
			});

			$('.rooms_filter .add_room').on('click', function() {

				search_form.add_room(search_form.default_room);
			});

			$('.tab_search_hotels form, .content_tabs .tab_rooms form').submit(function() {

				$('.rooms_filter .children').blur();

				if (!search_form.form_rooms_data_string()) return false;

				if (($('#hotel_search_check_in').val() == 'Any date') || !$('#hotel_search_check_in').val()) {

					$('#hotel_search_check_in').removeAttr('name');
				}

				if (($('#hotel_search_check_out').val() == 'Any date') || !$('#hotel_search_check_out').val()) {

					$('#hotel_search_check_out').removeAttr('name');
				}

				if ($('#hotel_search_city_id').val() == 0) {

					$('#hotel_search_city_id').removeAttr('name');
				}

				return true;
			});
		}

		on_load_tab = 'search_activities';
		if ($('.page_hotel').length) on_load_tab = 'search_hotels';

		help_links.change_tab(on_load_tab, $('.form_search'));

		if ($('.tab_search_activities').css('display') === 'none') {

			search_form.change_active_sidebar_button($('.form_search .help_links li:nth-child(2) input'));
		}
		else {

			search_form.change_active_sidebar_button($('.form_search .help_links li:first input'));
		}

		$('.form_search .help_links input').on('click', function() {

			search_form.change_active_sidebar_button(this);

			// For transportation tab only
			var current_source = $(this).data('source');

			// For all tabs
			var button_text = "Find hotels";

			if (current_source !== "") {

				if (current_source !== "all") {

					button_text = "Find " + current_source;

					$('.tab_search_activities .submit_orange span').css('background', 'url(/img/ico/find_trns.png) 1px 8px no-repeat')
				}
				else {

					button_text = "Find activities";

					$('.tab_search_activities .submit_orange span').css('background', 'url(/img/ico/find_act.png) 5px 8px no-repeat')
				}

				if (current_source !== previous_source) {

					previous_source = current_source;
					cities = cities_sources[current_source];
					city_id = 0;

					$('#trip_search_city').val(0);
					$('#trip_search_city').trigger("chosen:updated");

					search_form.update_city_activities_list(city_id);
				}
			}

			$('.form_search .submit_orange span').html('').append(button_text);

			// Change tab itself
			help_links.change_tab($(this).data('tab'), $('.form_search'));
		});
	}
};

var quickSearch = {

	init: function() {

		$('.relevant_search .input_text').autocomplete({
			source: "/index.php?page=search&mode=header_search_autocomplete",
			minLength: 2,
			response: function (event, ui) {

				$('.relevant_search .quick_results').html('\
					<ul class="quick_list"></ul>');

				$.each(ui.content, function (index, data) {

					$('.relevant_search .quick_results .quick_list').append('\
						<li><a>'+data.value+'</a></li>');
				});

				$('.relevant_search .quick_results li').on('click', function() {

					$('.relevant_search .input_text').val($('a', this).text());

					$('.quick_results').hide();
				});
			},
			open: function (event, ui) {

				$('.relevant_search .quick_results').show();

				$(this).data('uiAutocomplete').menu.element.css({
					'top': 0,
					'left': 0,
					'visibility': 'hidden'
				});
			}
		});

		$('.relevant_search .input_text').on('focus', function() {

			$('.search').addClass('quick');
		});
		$('.relevant_search .input_text').on('blur', function() {

			$('.search').removeClass('quick');
		});
	}
};

var ucp = {

	init_logon: function() {

		$('#logon').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				if (form_submit.is_disabled('logon')) return false;

				$('#logon .validate_errors').hide();

				form_submit.change_state('logon');
			},
			success: function(responseText, statusText) {

				if (responseText['errors']) {

					form_errors.show_validation(responseText['errors'], $('#logon'));
				}
				else if (responseText['status'] == 'success') {

					url = '/';
					if ($('.cart_logon').length) url = '/order/step1/';
					else if ($('.ucp_logon, .order_access').length) url = '/ucp/profile/';

					location.pathname = url;
				}

				if (responseText['status'] != 'success') form_submit.change_state('logon');
			}
		});

		form_submit.register('logon', $('#logon .green_button'));

		$('#login').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				if (form_submit.is_disabled('login')) return false;

				$('#login .validate_errors').hide();

				form_submit.change_state('login');
			},
			success: function(responseText, statusText) {

				ucp.submit_is_disabled = false;

				if (responseText['errors']) {

					form_errors.show_validation(responseText['errors'], $('#login'));
				}
				else if (responseText['status'] == 'success') {

					url = '/';
					if (!isUndefined(responseText['redirect'])) url = responseText['redirect'];

					document.location.href = url;
				}

				if (responseText['status'] != 'success') form_submit.change_state('login');
			}
		});

		form_submit.register('login', $('#login .green_button'));

		$('#restore_password').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				if (form_submit.is_disabled('restore_password')) return false;

				$('#restore_password .validate_errors').hide();

				form_submit.change_state('restore_password');
			},
			success: function(responseText, statusText) {

				if (responseText['errors']) {

					form_errors.show_validation(responseText['errors'], $('#restore_password'));
				}
				else if (responseText['status'] == 'success') {

					$('#restore_password .success').fadeIn();
				}

				if (responseText['status'] != 'success') form_submit.change_state('restore_password');
			}
		});

		form_submit.register('restore_password', $('#restore_password .green_button'));

		$('#restore_username').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				if (form_submit.is_disabled('restore_username')) return false;

				$('#restore_username .validate_errors').hide();

				form_submit.change_state('restore_username');
			},
			success: function(responseText, statusText) {

				ucp.submit_is_disabled = false;

				if (responseText['errors']) {

					form_errors.show_validation(responseText['errors'], $('#restore_username'));
				}
				else if (responseText['status'] == 'success') {

					$('#restore_username .success').fadeIn();
				}

				if (responseText['status'] != 'success') form_submit.change_state('restore_username');
			}
		});

		form_submit.register('restore_username', $('#restore_username .green_button'));

		$('.login_form .submit').on('click', function() {

			$(this).closest('form').submit();
		});

		$('.login_form .switch').on('click', function() {

			$('.login_form form').hide();

			tab_id = $(this).data('tab');

			$('.login_form #'+tab_id).show();
		});
	},
	init_register: function() {

		$('#user_info input[type=text], #user_info input[type=password]').addClass('input_text');
		$('#user_info input[type=email], #user_info input[type=tel]').addClass('input_text');

		$('#ucp_user_info_state_id').on('change', function() {

			if ($(':selected', this).val() == 0) $(this).closest('.form_line').next('.form_line').fadeIn();
			else $(this).closest('.form_line').next('.form_line').fadeOut();
		}).change();

		ucp.init_state_selection_by_country('ucp_user_info');

		var form_fields = {};

		$('#user_info').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				if (form_submit.is_disabled('user_info')) return false;

				$('.profile_form .validate_errors').hide();

				$.each(formData, function(index, data) {

					form_fields[data.name] = data.value;
				});

				form_submit.change_state('user_info');
			},
			success: function(responseText, statusText) {

				if ($('.order_step2').length) {

					if (responseText['errors']) {

						form_errors.show_validation(responseText['errors'], $('.popup.user_info'));
					}
					else if (responseText['status'] == 'success') {

						// Make contact info html
						if (form_fields.state_id == 0) state = form_fields.state_name;
						else state = $('#user_info #ucp_user_info_state_id :selected').text();

						country = $('#user_info #ucp_user_info_country_id :selected').text();

						contact_info = '\
							<span class="name">'+form_fields.first_name+' '+form_fields.last_name+'</span>\
							<br />#'+form_fields.phone+(form_fields.phone2 ? ', #'+form_fields.phone2 : '')+'\
							<br />'+form_fields.email;

						$('.profile_form .contact_info').html(contact_info);

						popup.closePopup($('.popup.user_info'));

						form_submit.change_state('user_info');
					}
				}
				else {

					if (responseText['errors']) {

						form_errors.show_validation(responseText['errors'], $('.profile_form'));

						$('html, body').animate({scrollTop : 100}, 'slow');
					}
					else if (responseText['status'] == 'success') {

						if ($('.ucp_register, .ucp_edit').length) {

							location.pathname = '/ucp/profile/';
						}
						else {

							location.pathname = '/order/step2/';
						}
					}
				}

				if (responseText['status'] != 'success') form_submit.change_state('user_info');
			}
		});

		form_submit.register('user_info', $('.bottom_controls .register_submit'));

		$('.bottom_controls .register_submit').on('click', function() {

			$('#user_info').submit();
		});
	},

	init_state_selection_by_country: function(prefix) {

		$('#'+prefix+'_country_id').on('change', function(event) {

			var country_name = $(':selected', this).html();
			var states_html = '<option class="italic">Select a state</option>';

			if ((typeof(countries_states) !== "undefined") && (typeof(countries_states[country_name]) !== "undefined")) {

				states_html += '<optgroup label="'+country_name+'">';

				$.each(countries_states[country_name], function(index, state) {

					var id = state['id'];
					var name = state['name'];

					if (!another_state && (typeof(state_choosed) !== "undefined") && (state_choosed == id)) {

						states_html += '<option value="'+id+'" selected>'+name+'</option>';
					}
					else {

						states_html += '<option value="'+id+'">'+name+'</option>';
					}
				});

				states_html += '</optgroup>';
			}

			if (another_state) {

				states_html += '<option value="0" selected>Another state</option>';
				another_state = false;
			}
			else {

				states_html += '<option value="0">Another state</option>';
				$('#'+prefix+'_state_name').val('');
			}

			$('#'+prefix+'_state_id').html(states_html).trigger("chosen:updated");
		});

		if ($('.page_ucp.ucp_register').length || $('.page_ucp.ucp_edit').length) {

			$('#'+prefix+'_country_id').change();
		}
	}
};

var book = {

	add_to_cart_attempts: 0,

	// For schedules actions
	schedules: {},
	current_tickets: {},
	// For inventory actions
	inventories: null,
	separated_tickets: {},

	hotel_add_to_cart_handler: function() {

		$('.hotel_room_form').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				if (form_submit.is_disabled('hotel_add_to_cart')) return false;

				$('.validate_errors').hide();

				form_submit.change_state('hotel_add_to_cart');
			},
			success: function(responseText, statusText) {

				if (responseText['errors']) {

					form_errors.show_validation(responseText['errors'], $('.hotel_add_to_cart'));
				}
				else if (responseText['status'] == 'success') {

					location.pathname = '/cart/list/';
				}

				if (responseText['status'] != 'success') form_submit.change_state('hotel_add_to_cart');
			}
		});

		form_submit.register('hotel_add_to_cart', $('.popup.hotel_add_to_cart .add_to_cart'));

		$('.popup.hotel_add_to_cart .add_to_cart').on('click', function() {

			$('.hotel_room_form').submit();
		});
	},
	show_agreement: function(room) {

		params = {
			'hotel_id': $(room).closest('.hotel').data('hotelId'),
			'room_id': $(room).data('roomId')
		};

		if ($(room).closest('.is_affiliate').length) {

			params['affiliate_data[rateCode]'] = $(room).data('rateCode');
			params['affiliate_data[rateKey]'] = $(room).data('rateKey');
			params['affiliate_data[roomTypeCode]'] = $(room).data('roomTypeCode');
		}

		request_params = '';
		for (param_name in params) {

			request_params += '&'+param_name+'='+params[param_name];
		}

		search_params = search_common.get_url_params_string('hotel');

		$.ajax({
			url: '/index.php?page=hotel&mode=add_to_cart'+request_params+search_params,
			type: 'get',
			dataType: 'html',
			success: function(html) {

				if (html.replace(/[\r|\n|\s]/gm, '') != '') {

					$('.popup.hotel_add_to_cart').empty();
					$('.popup.hotel_add_to_cart').html(html);

					if ($('.popup.hotel_add_to_cart .text_agreement').is(':empty')) {

						agreement = $('.cancellation_policy a', room).data('title');

						$('.popup.hotel_add_to_cart .text_agreement').html(agreement);
					}

					popup.openPopup('hotel_add_to_cart');
					popup.handlers();

					typeCustom.init($('.popup.hotel_add_to_cart .agreement_confirm'));

					watermarks.init($('.popup.hotel_add_to_cart .rooms_guests .input_text'));

					book.hotel_add_to_cart_handler();
				}
			}
		});
	},
	init_hotels: function() {

		showFullClick.init();

		$('.room_table').unbind('click').on('click', function(e) {

			e.stopPropagation();
		});

		$('.room_table').each(function(index, room) {

			if (!($('.view_totals a', room).data('tooltip'))) {

				$('.view_totals .view', room).tooltip({
					relative: true,
					position: 'center right',
					offset: [0, 5]
				});

				$('.view_totals_tooltip .note', room).on('click', function() {

					tabs.change('overview');
				});
			}

			if (!$('.cancellation_policy a', room).data('tooltip') && (!$(this).closest('.is_affiliate').length || $('.hotel_details').length)) {

				$('.cancellation_policy a', room).tooltip({
					tipClass: 'tooltip cxl_policy_tooltip',
					position: 'center right',
					offset: [0, 5]
				});
			}

			$('.book_now', room).unbind('click').on('click', function(e) {

				e.stopPropagation();

				book.show_agreement($(this).closest('.room_table'));
			});
		});
	},

	// where popups starts
	init_activities: function() {

		$('.book_trip .book_now, .book_trip .book_now_alt').unbind('click').on('click', function() {

			trip_id = $(this).data('tripId');
			use_calendar = $(this).data('useCalendar');

			if ($('.trip_details').length) {

				send_tracking_data_to_analytics(
					'productCheckAvailability',
					google_trip,
					function () {

						if (use_calendar) book.show_calendar(trip_id, search_from_month_year);
						else book.show_tickets(null, trip_id);
					}
				);
			}
			else {

				var upper_container = 'div';

				if ($('.trips_search, .index_default, .itinerary_view').length) upper_container = '.deal_item';

				send_tracking_data_to_analytics(
					'productCheckAvailability',
					{
						name: $(this).closest(upper_container).find('.google_trip_name').val(),
						id: $(this).closest(upper_container).find('.google_trip_id').val(),
						brand: $(this).closest(upper_container).find('.google_trip_brand').val(),
						category: $(this).closest(upper_container).find('.google_trip_category').val()
					},
					function () {

						if (use_calendar) book.show_calendar(trip_id, search_from_month_year);
						else book.show_tickets(null, trip_id);
					}
				);
			}

			return false;
		});

		$('.cart_contents .change_button_accept').unbind('click').on('click', function() {

			book.show_popup_cart($(this).data('tripId'));
		});

		$('.book_trip .buy_as_gift').unbind('click').on('click', function() {

			trip_id = $(this).data('tripId');
			use_calendar = $(this).data('useCalendar');
			can_be_gift = $(this).data('canBeGift');
			trigger = $(this);

			send_tracking_data_to_analytics(
				'productBuyAsGift',
				google_trip,
				function () {

					if (!use_calendar) {

						book.show_tickets(null, trip_id);
					}
					else {

						if (can_be_gift !== -1) {

							if (can_be_gift) book.show_tickets(null, trip_id);
							else book.show_calendar(trip_id, search_from_month_year, true);

							return false;
						}
						else {

							$.ajax({
								url: '/index.php?page=trip&mode=can_be_gift&trip_id=' + trip_id,
								type: 'get',
								dataType: 'json',
								error: function () {

									$('.book_trip .buy_as_gift').click();
								},
								success: function (response) {

									trigger.data('canBeGift', ((response['result'] == true) ? 1 : 0));

									if (response['result'] == true) book.show_tickets(null, trip_id);
									else book.show_calendar(trip_id, search_from_month_year, true);

									return false;
								}
							});
						}
					}
				}
			);
		});
	},

	show_popup_cart: function (tripID) {

		var change_trip_id = $('.change_trip_id').val();

		if (change_trip_id == 0) {

			cart.remove_cart_item(tripID, 1);

			return false;
		}
		else {

			trip_id = change_trip_id;
			use_calendar = $('.change_trip_id').data('useCalendar');
		}

		if (use_calendar) book.show_calendar(trip_id, search_from_month_year);
		else book.show_tickets(null, trip_id);

		$('.change_trip_id').val(0);

		return false;
	},

	show_shedules: function() {

		var has_different_prices = false;

		if ($('#schedule_select_type').val() === "radio") {

			var schedule_options = '';
			var first = true;
			var counter = 0;

			$.each(book.schedules, function(index, schedule) {

				if (schedule.has_lowest_price == 1) has_different_prices = true;

				if (typeof($('#first_column').val()) !== "undefined") {

					if (counter == 0) {

						schedule_options += '<div class="schedule_options_column">';
					}
					else if ((counter % $('#first_column').val()) == 0) {

						schedule_options += '</div><div class="schedule_options_column">';
					}
				}

				schedule_options += '\
				<div class="schedule_option">\
					<input type="radio" class="schedule_id" name="schedule_id" value="'+schedule.id+'"'+(first ? ' checked' : '')+' data-schedule-time="'+schedule.time+'">\
					<span class="schedule_text'+(schedule.admin_only ? ' admin_only' : '')+'">'+schedule.time+'</span>\
					'+((schedule.has_lowest_price == 1) ? '<span class="schedule_lowest_price"><i class="fa fa-check-circle"></i></span>' : '')+'\
				</div>';

				first = false;
				counter++;
			});

			if (typeof($('#first_column').val()) !== "undefined") schedule_options += '</div>';
		}
		else {

			var schedule_options = '<select name="schedule_id">';

			$.each(book.schedules, function(index, schedule) {

				if (schedule.has_lowest_price == 1) has_different_prices = true;

				schedule_options += '\
				<option value="'+schedule['id']+'" class="schedule_option">'+schedule['time']+'</option>';
			});
			schedule_options += '</select>';
		}

		schedule_html = '\
			<div class="line schedule">\
				<span class="control_container schedule_name">\
					<label>Select Time</label>\
					<div class="schedules_notification hidden">\
						<i class="fa fa-exclamation-circle"></i>\
						<div>Trip options and/or pricing may vary based on departure time selection</div>\
					</div>\
					'+schedule_options+'\
					<div class="schedules_explanation hidden">\
						<i class="fa fa-check-circle"></i>\
						<div>Indicates lowest price tickets</div>\
					</div>\
				</span>\
				<span class="control_container amount">\
				</span>\
			</div>';


		$('.popup.trip_add_to_cart #schedule_select_type').after(schedule_html);

		// Works for select only
		select_design.apply($('.popup.trip_add_to_cart .schedule select'));

		if (has_different_prices) {

			$('.popup.trip_add_to_cart .schedule .schedules_notification').show();
			$('.popup.trip_add_to_cart .schedule .schedules_explanation').show();

			$('.popup.trip_add_to_cart .schedule select').on('chosen:showing_dropdown', function() {

				$('.schedule .chosen-container .chosen-drop li').each(function() {

					var schedule = $(this).html();

					if (!isUndefined(book.schedules[schedule]) && (book.schedules[schedule].has_lowest_price == 1)) {

						$(this).append('&nbsp;<i class="fa fa-check-circle"></i>');
					}
				});
			});
		}

		$('.popup.trip_add_to_cart .schedule select').on('change', function() {

			if (has_different_prices) {

				var schedule_span = $('.schedule .chosen-container .chosen-single span');
				var schedule_text = schedule_span.html();

				if (!isUndefined(book.schedules[schedule_text]) && (book.schedules[schedule_text].has_lowest_price == 1)) {

					$(schedule_span).append('&nbsp;<i class="fa fa-check-circle"></i>');
				}
			}

			book.change_schedule();
		}).change();


		// Works for radio only
		$('.popup.trip_add_to_cart .schedule .schedule_id').on('change', function() {

			book.change_schedule();
		}).change();
	},
	change_schedule: function() {

		delete book.inventories;
		book.separated_tickets = {};

		if ($('#schedule_select_type').val() === "radio") {

			selected_schedule_id = $('.schedule .schedule_option input:checked').data('scheduleTime');
		}
		else {

			selected_schedule_id = $('.popup.trip_add_to_cart .schedule :selected').text();
		}
		book.current_tickets = book.schedules[selected_schedule_id].tickets;


		if (book.schedules[selected_schedule_id]['fh_id'] != 0) {

			$('#fh_id').val(book.schedules[selected_schedule_id]['fh_id']);
			$('#fh_tickets_config').val(book.schedules[selected_schedule_id]['fh_tickets_config']);
		}


		$('.popup.trip_add_to_cart .ticket').remove();

		book.add_ticket();
	},

	add_ticket: function() {

		if ($('#ticket_select_type').val() === "row") {

			book.add_tickets_row();
		}
		// $('#ticket_select_type').val() === "select"
		else {

			book.add_ticket_select();
		}
	},

	add_tickets_row: function() {

		$('.popup.trip_add_to_cart .tickets').empty();

		if (!objIsEmpty(book.schedules) && isUndefined(book.schedules[0]) ) {

			if ($('#schedule_select_type').val() === "radio") {

				schedule_time = $('.schedule .schedule_option input:checked').data('scheduleTime');
			}
			else {

				schedule_time = $('.popup.trip_add_to_cart .schedule_name select :selected').text();
			}

			tickets = book.schedules[schedule_time].tickets;
		}
		else {

			tickets = book.current_tickets;
		}

		var ticket_price;

		$.each(tickets, function(index, ticket) {

			if (parseFloat(ticket.strike_out) > parseFloat(ticket.price)) {

				saving = parseFloat(ticket.strike_out) - parseFloat(ticket.price);
			}
			else {

				saving = 0;
			}

			if (typeof(active_percent_discount) !== "undefined") {

				discount = Math.round((parseFloat(ticket.price) * parseFloat(active_percent_discount)))/100;

				saving += discount;
				if (parseFloat(ticket.strike_out) <= parseFloat(ticket.price)) ticket.strike_out = ticket.price;
				ticket_price = parseFloat(ticket.price) - discount;
			}
			else {

				ticket_price = ticket.price;
			}

			ticket_html = '\
				<li data-id="'+ticket.id+'">\
					<input type="hidden" class="ticket_booked" name="tickets['+ticket.id+']" value="0" />\
					<div class="ticket_data">\
						<div class="ticket_name" title="'+ticket.description+'">'+ticket.name+'</div>\
						<br />';

			if (saving == 0) {

				ticket_html += '<em>$'+ticket_price+'</em>';
			}
			else {

				saving = Math.round(saving);
				strike_out = Math.round(ticket.strike_out);
				ticket_price = Math.round(ticket_price);

				ticket_html += '<em class="strike_out">$'+strike_out+'</em>&nbsp;<em class="price">$'+ticket_price+'</em>\
								<div class="saving">Save $'+saving+'!</div>';
			}

			ticket_html += '\
					</div>\
					<div class="counter" data-can-be-zero="1">\
						<input type="hidden" class="counter_min" value="'+ticket.min+'" />\
						<input type="hidden" class="counter_max" data-control="'+ticket.avail+'" value="'+ticket.avail+'" />\
						<input type="button" class="counter-minus hidden-minus" value="&ndash;" />\
						<div class="counter counter_value">0</div>\
						<input type="button" class="counter-plus" value="+" />\
					</div>\
					<div class="clear"></div>\
					<div class="guests"></div>\
				</li>\
			';

			$('.popup.trip_add_to_cart .tickets').append(ticket_html);
		});

		$('.popup.trip_add_to_cart .tickets .counter-plus').on('click', function() {

			var counter_obj = $(this).parent();
			var counter_min = ($('.counter_min', counter_obj).length ? parseInt($('.counter_min', counter_obj).val()) : 0);
			var cv = $('.counter', counter_obj);
			var nv = parseInt(cv.html());
			var ticket = $(this).closest('li');
			var ticket_id = ticket.data('id');

			var control = nv;

			nv++;
			if (nv < counter_min) nv = counter_min;

			cv.html(nv);
			book.row_check_edges(counter_obj);

			var ticket_booked = $(this).siblings('.counter').text();

			$('.ticket_booked', ticket).val(ticket_booked);

			book.row_check_tickets_guests(ticket);

			if ((control != nv) && !$.isEmptyObject(schedules_inventories)) book.add_ticket_to_inventory(ticket_id);
		});

		$('.popup.trip_add_to_cart .tickets .counter-minus').on('click', function() {

			var counter_obj = $(this).parent();
			var cv = $('.counter', counter_obj);
			var lowest_val = ($(counter_obj).data('canBeZero') ? 0 : 1);
			var counter_min = ($('.counter_min', counter_obj).length ? parseInt($('.counter_min', counter_obj).val()) : lowest_val);
			var nv = parseInt(cv.html());
			var ticket = $(this).closest('li');
			var ticket_id = ticket.data('id');

			var control = nv;

			nv -= 1;
			if ((nv == lowest_val) || (nv < counter_min)) nv = lowest_val;

			cv.html(nv);

			book.row_check_edges(counter_obj);

			ticket_booked = $(this).siblings('.counter').text();

			$('.ticket_booked', ticket).val(ticket_booked);

			book.row_check_tickets_guests(ticket);

			if ((control != nv) && !$.isEmptyObject(schedules_inventories)) book.remove_ticket_from_inventory(ticket_id);
		});


		$('.popup.trip_add_to_cart .ticket_name').tooltipster({
			animation: 'grow',
			delay: 100
		});

		popup.checkHeight($('.trip_add_to_cart'));
	},
	row_check_edges: function(counter_obj) {

		var lowest_val = ($(counter_obj).data('canBeZero') ? 0 : 1);
		var counter_min = ($('.counter_min', counter_obj).length ? parseInt($('.counter_min', counter_obj).val()) : 0);
		var counter_max = ($('.counter_max', counter_obj).length ? parseInt($('.counter_max', counter_obj).val()) : 0);

		counter_value = parseInt($('.counter_value', counter_obj).html());

		if (counter_max && (counter_value >= counter_max)) $('.counter-plus', counter_obj).hide();
		else $('.counter-plus', counter_obj).show();

		if ((counter_value == lowest_val) || (counter_value < counter_min)) $('.counter-minus', counter_obj).hide();
		else $('.counter-minus', counter_obj).attr('style', 'display: inline-block !important');
	},
	row_check_tickets_guests: function(ticket) {

		if (need_tickets_guests != 1) return;


		ticket_id = $(ticket).data('id');
		ticket_booked = parseInt($('.ticket_booked', ticket).val());
		guests = $('.guest', ticket).length;

		if (ticket_booked < guests) {

			for (var index = guests; index > ticket_booked; index--) {

				$('.guest:eq('+(index - 1)+')', ticket).remove();
			}
		}
		else if (ticket_booked > guests) {

			var more_guests_count = ticket_booked - guests;
			var field_name = 'guests['+ticket_id+']';

			for (index = 1; index <= more_guests_count; index++) {

				guest_index = guests + index;

				$('.guests', ticket).append('\
					<div class="guest">\
						<div clas="text">Ticket '+guest_index+'</div>\
						<input type="text" name="'+field_name+'['+guest_index+'][first_name]" class="first_name"\
								placeholder="First Name" />\
						<input type="text" name="'+field_name+'['+guest_index+'][last_name]" class="last_name"\
								placeholder="Last Name" />\
					</div>\
				');
			}
		}
	},

	add_ticket_to_inventory: function(ticket_id) {

		var duration = tickets['id_'+ticket_id].duration;

		// After schedule changed
		if ($.isEmptyObject(book.inventories)) {

			if ($('#schedule_select_type').val() === "radio") {

				schedule_time = $('.schedule .schedule_option input:checked').data('scheduleTime');
			}
			else {

				schedule_time = $('.popup.trip_add_to_cart .schedule_name select :selected').text();
			}

			book.inventories = copyObject(schedules_inventories[schedule_time]);
		}

		var inventories_totals = {};

		// Totals are helpful in separation and extremely helpful in rest tickets correction
		$.each(book.inventories, function(inventory_id, inventory_data) {

			if (typeof(inventories_totals[inventory_data.duration]) == "undefined") {

				inventories_totals[inventory_data.duration] = 0;
			}

			inventories_totals[inventory_data.duration] += inventory_data.avail;
		});

		// There is inventory with our duration
		if ((typeof(inventories_totals[duration]) != "undefined") && (inventories_totals[duration] != 0)) {

			$.each(book.inventories, function(inventory_id, inventory_data) {

				if ((inventory_data.duration == duration) && (inventory_data.avail > 0)) {

					chosen_id = inventory_id;
					return false;
				}
			});

			book.inventories[chosen_id].avail -= 1;
			inventories_totals[duration] -= 1;
		}
		// There is no inventory that conforms exactly but this is the null-ticket
		else if (duration == null) {

			$.each(book.inventories, function(inventory_id, inventory_data) {

				if (inventory_data.avail > 0) {

					chosen_id = inventory_id;
					return false;
				}
			});

			book.inventories[chosen_id].avail -= 1;
			inventories_totals[book.inventories[chosen_id].duration] -= 1;
		}
		// Worst case. There is no inventory that conforms exactly and this is the not-null-ticket
		else {

			$.each(book.inventories, function(inventory_id, inventory_data) {

				if ((inventory_data.duration == null) && (inventory_data.avail > 0)) {

					chosen_id = inventory_id;
					return false;
				}
			});

			if (typeof(inventories_totals[duration]) == "undefined") inventories_totals[duration] = 0;

			inventories_totals["null"] -= book.inventories[chosen_id].avail;
			book.inventories[chosen_id].avail -= 1;
			book.inventories[chosen_id].duration = duration;
			inventories_totals[duration] += book.inventories[chosen_id].avail;
		}


		// This is data for minus-button
		if (typeof(book.separated_tickets[ticket_id]) == "undefined") {

			book.separated_tickets[ticket_id] = {};
		}
		if (typeof(book.separated_tickets[ticket_id][chosen_id]) == "undefined") {

			book.separated_tickets[ticket_id][chosen_id] = 0;
		}
		book.separated_tickets[ticket_id][chosen_id] += 1;

		// Rest tickets correction
		$('.tickets_list .tickets li').each(function() {

			var counter_max = ($('.counter_max', this).length ? parseInt($('.counter_max', this).val()) : 0);

			if (counter_max != 0) {

				var counter_value = parseInt($('.counter_value', this).html());
				var ticket_id = $(this).data('id');

				var places_available = 0;

				if (tickets['id_' + ticket_id].duration == null) {

					$.each(inventories_totals, function (inv_duration, inv_avail) {
						places_available += inv_avail;
					});
				}
				else {

					$.each(inventories_totals, function (inv_duration, inv_avail) {

						if ((inv_duration == "null") || (inv_duration == tickets['id_' + ticket_id].duration)) {

							places_available += inv_avail;
						}
					});
				}

				places_available += counter_value;

				if (places_available < counter_max) {

					counter_max = places_available;

					$('.counter_max', this).val(counter_max);

					if (places_available == 0) {

						$(this).hide();
					}
					else {

						book.row_check_edges($('.counter', this));
					}
				}
			}
		});
	},
	remove_ticket_from_inventory: function(ticket_id) {

		if ($('#schedule_select_type').val() === "radio") {

			schedule_time = $('.schedule .schedule_option input:checked').data('scheduleTime');
		}
		else {

			schedule_time = $('.popup.trip_add_to_cart .schedule_name select :selected').text();
		}

		var inventories_base = schedules_inventories[schedule_time];


		// Witch inventory place to release
		chosen_id = null;

		$.each(book.separated_tickets[ticket_id], function(inventory_id, count) {

			if ((chosen_id == null) || (inventories_base[inventory_id].duration == null)) {

				chosen_id = inventory_id;

				if (inventories_base[inventory_id].duration == null) return false;
			}
		});

		// Corrections
		book.inventories[chosen_id].avail += 1;
		book.separated_tickets[ticket_id][chosen_id] -= 1;
		if (book.separated_tickets[ticket_id][chosen_id] == 0) delete book.separated_tickets[ticket_id][chosen_id];
		if ($.isEmptyObject(book.separated_tickets[ticket_id])) delete book.separated_tickets[ticket_id];


		// Do we need to cancel duration for initially null-inventory now?
		if ((book.inventories[chosen_id].duration != null) && (inventories_base[chosen_id].duration == null)) {

			var make_it_null = true;

			$.each(book.separated_tickets, function (ticket_id, ticket_inventories) {

				if ((typeof(ticket_inventories[chosen_id]) != "undefined") && (tickets['id_'+ticket_id].duration != null)) {

					make_it_null = false;
					return false;
				}
			});

			if (make_it_null) book.inventories[chosen_id].duration = null;
		}

		var inventories_totals = {};

		// In this case we need totals only for the rest tickets correction
		// And it's very convinient for us to calculate in after the main operations
		$.each(book.inventories, function(inventory_id, inventory_data) {

			if (typeof(inventories_totals[inventory_data.duration]) == "undefined") {

				inventories_totals[inventory_data.duration] = 0;
			}

			inventories_totals[inventory_data.duration] += inventory_data.avail;
		});


		// Rest tickets correction
		$('.tickets_list .tickets li').each(function() {

			var counter_max = ($('.counter_max', this).length ? parseInt($('.counter_max', this).val()) : 0);
			var counter_max_control = $('.counter_max', this).data('control');

			if (counter_max < counter_max_control) {

				var counter_value = parseInt($('.counter_value', this).html());
				var ticket_id = $(this).data('id');

				var places_available = 0;

				if (tickets['id_'+ticket_id].duration == null) {

					$.each(inventories_totals, function (inv_duration, inv_avail) {
						places_available += inv_avail;
					});
				}
				else {

					$.each(inventories_totals, function (inv_duration, inv_avail) {

						if ((inv_duration == "null") || (inv_duration == tickets['id_'+ticket_id].duration)) {

							places_available += inv_avail;
						}
					});
				}

				places_available += counter_value;

				if (places_available > 0) {

					$(this).show();

					if (places_available > counter_max) {

						counter_max = places_available;

						$('.counter_max', this).val(counter_max);

						book.row_check_edges($('.counter', this));
					}
				}
			}
		});
	},

	add_ticket_select: function() {

		is_first_ticket = ($('.popup.trip_add_to_cart .ticket').length == 0);

		if (!objIsEmpty(book.schedules) && isUndefined(book.schedules[0]) ) {

			if ($('#schedule_select_type').val() === "radio") {

				schedule_time = $('.schedule .schedule_option input:checked').data('scheduleTime');
			}
			else {

				schedule_time = $('.popup.trip_add_to_cart .schedule_name select :selected').text();
			}

			schedule_tickets_avail = parseInt(book.schedules[schedule_time].tickets_avail);
		}
		else schedule_tickets_avail = ((is_admin == 1) ? 300 : 30);

		var total_tickets_choosed = 0;
		$('.popup.trip_add_to_cart .ticket .amount select :selected').each(function(obj) {

			total_tickets_choosed += parseInt($(this).val());
		});

		var more_tickets_avail = schedule_tickets_avail - total_tickets_choosed;
		if (more_tickets_avail < 0) more_tickets_avail = 0;

		var already_added_tickets_id = get_values_array('.tickets_list .ticket .type select :selected');
		var ticket_types_select_options = '';
		var first_ticket_min = 1;

		$.each(book.current_tickets, function(index, ticket) {

			if (
				(already_added_tickets_id.indexOf(ticket.id) !== -1)
				||
				(ticket.min > more_tickets_avail)
			) return;

			if (!ticket_types_select_options && (ticket.min > 0)) first_ticket_min = ticket.min;

			ticket_types_select_options += '\
				<option value="'+ticket.id+'">'+ticket.name_format+'</option>';
		});

		if (!ticket_types_select_options) return;

		ticket_html = '\
			<div class="line ticket">\
				'+(is_first_ticket ? '' : '<i class="fa fa-times-circle remove_ticket"></i>')+'\
				<span class="control_container type">\
					'+(is_first_ticket ? '<label>Tickets</label>' : '')+'\
					<select>\
						'+ticket_types_select_options+'\
					</select>\
				</span>\
				<span class="control_container amount">\
					'+(is_first_ticket ? '<label>Amount</label>' : '')+'\
					<select>\
						<option value="'+first_ticket_min+'">'+first_ticket_min+'</option>\
					</select>\
				</span>\
				<span class="ticket_description" title="dummy">Ticket description</span>\
				<div class="tickets_guests"></div>\
			</div>';

		$('.popup.trip_add_to_cart .add_ticket').before(ticket_html);

		select_design.apply($('.popup.trip_add_to_cart .ticket:last .type select'));
		select_design.apply($('.popup.trip_add_to_cart .ticket:last .amount select'));

		$('.popup.trip_add_to_cart .ticket:last .type select,\
			.popup.trip_add_to_cart .ticket:last .amount select').on('change', function() {

			book.ticket_type_manager();
		});

		$('.popup.trip_add_to_cart .ticket:last .remove_ticket').on('click', function() {

			$(this).closest('.ticket').remove();

			book.ticket_type_manager();
		});

		$('.popup.trip_add_to_cart .ticket:last .ticket_description').tooltipster({
			animation: 'grow',
			delay: 100
		});

		book.ticket_type_manager();
	},
	ticket_type_manager: function() {

		schedule_tickets_avail = 0;
		if (!objIsEmpty(book.schedules) && isUndefined(book.schedules[0]) ) {

			if ($('#schedule_select_type').val() === "radio") {

				schedule_time = $('.schedule .schedule_option input:checked').data('scheduleTime');
			}
			else {

				schedule_time = $('.popup.trip_add_to_cart .schedule_name select :selected').text();
			}

			schedule_tickets_avail = parseInt(book.schedules[schedule_time].tickets_avail);
		}

		if (!schedule_tickets_avail) schedule_tickets_avail = ((is_admin == 1) ? 300 : 30);

		$('.popup.trip_add_to_cart .ticket').each(function() {

			ticket_type_select = $('.type select', this);
			ticket_amount_select = $('.amount select', this);

			current_ticket_id = parseInt($(':selected', ticket_type_select).val());
			current_ticket = book.current_tickets['id_'+current_ticket_id];

			current_ticket_amount = parseInt($(':selected', ticket_amount_select).val());

			// min tickets
			min_tickets = 1;
			if (current_ticket.min > 1) min_tickets = current_ticket.min;

			ticket_amount_select_options = '';

			for (var index = min_tickets; index <= schedule_tickets_avail; index++) {

				ticket_amount_select_options += '\
					<option value="'+index+'"'+(index == current_ticket_amount ? ' selected' : '')+'>'+index+'</option>';
			}

			$(ticket_amount_select).html(ticket_amount_select_options);
		});

		var total_tickets_choosed = 0;
		$('.popup.trip_add_to_cart .ticket .amount select :selected').each(function(obj) {

			total_tickets_choosed += parseInt($(this).val());
		});

		var more_tickets_avail = schedule_tickets_avail - total_tickets_choosed;
		if (more_tickets_avail < 0) more_tickets_avail = 0;

		var already_added_tickets_id = get_values_array('.popup.trip_add_to_cart .tickets_list .ticket .type select :selected');

		$('.popup.trip_add_to_cart .ticket').each(function() {

			ticket_type_select = $('.type select', this);
			ticket_amount_select = $('.amount select', this);

			current_ticket_id = parseInt($(':selected', ticket_type_select).val());
			current_ticket_amount = parseInt($(':selected', ticket_amount_select).val());
			current_ticket = book.current_tickets['id_'+current_ticket_id];

			// rebuild ticket type options, remove already added types
			ticket_type_select_options = '';

			$.each(book.current_tickets, function(index, ticket) {

				if (
					(ticket.id != current_ticket_id)
					&&
					(
					(already_added_tickets_id.indexOf(ticket.id) !== -1)
					||
					(ticket.min > (current_ticket_amount + more_tickets_avail))
					)
				) return;

				ticket_name = ticket.name_format;

				ticket_max = ticket.avail;
				if ((current_ticket_amount + more_tickets_avail) < ticket.avail) ticket_max = (current_ticket_amount + more_tickets_avail);

				ticket_type_select_options += '\
					<option value="'+ticket.id+'"'+(ticket.id == current_ticket_id ? ' selected' : '')+'>'+ticket_name+' ['+ticket_max+' available]</option>';
			});

			$(ticket_type_select).html(ticket_type_select_options);

			// ticket amount select name
			$(ticket_amount_select).attr('name', 'tickets['+current_ticket.id+']');

			// tickets amount select options

			// max_tickets
			max_tickets = (current_ticket_amount + more_tickets_avail);
			if (parseInt(current_ticket.avail) < max_tickets) max_tickets = parseInt(current_ticket.avail);
			max_tickets_limit = ((is_admin == 1) ? 300 : 30);
			if (max_tickets > max_tickets_limit) max_tickets = max_tickets_limit;

			$('option', ticket_amount_select).each(function() {

				option_value = parseInt($(this).val());

				if (option_value > max_tickets) $(this).remove();
			});

			// set ticket description
			$('.ticket_description', this).tooltipster('content', current_ticket.description);

			// update selects view
			$(ticket_type_select).trigger('chosen:updated');
			$(ticket_amount_select).trigger('chosen:updated');

			if (need_tickets_guests == 1) {

				var tickets_guests = '';
				var previous_first_name = '';
				var previous_last_name = '';

				for (ticket_number = 1; ticket_number <= current_ticket_amount; ticket_number++) {

					if ($('#first_name_'+current_ticket_id+'_'+ticket_number).length) {

						previous_first_name = 'value="'+$('#first_name_'+current_ticket_id+'_'+ticket_number).val()+'"';
					}
					else {

						previous_first_name = '';
					}
					if ($('#last_name_'+current_ticket_id+'_'+ticket_number).length) {

						previous_last_name = 'value="'+$('#last_name_'+current_ticket_id+'_'+ticket_number).val()+'"';
					}
					else {

						previous_last_name = '';
					}

					tickets_guests += '\
				<div class="ticket_guest">\
					<span class="guest_label">Ticket '+ticket_number+'</span> \
					<input type="text" id="first_name_'+current_ticket_id+'_'+ticket_number+
					'" name="guests['+current_ticket_id+']['+ticket_number+'][first_name]" placeholder="First name" '+previous_first_name+' />\
					<input type="text" id="last_name_'+current_ticket_id+'_'+ticket_number+
					'" name="guests['+current_ticket_id+']['+ticket_number+'][last_name]" placeholder="Last name" '+previous_last_name+' />\
				</div>';
				}

				$('.tickets_guests', this).html(tickets_guests);
			}
		});

		// check more ticket types avail
		var no_more_avail_tickets = true;

		$.each(book.current_tickets, function(index, ticket) {

			if (already_added_tickets_id.indexOf(ticket.id) !== -1) return;

			ticket_min = 1;
			if (ticket.min > 0) ticket_min = ticket.min;

			if (ticket_min <= more_tickets_avail) no_more_avail_tickets = false;
		});

		if (no_more_avail_tickets) $('.popup.trip_add_to_cart .tickets_list .add_ticket').hide();
		else $('.popup.trip_add_to_cart .tickets_list .add_ticket').show();

		popup.checkHeight($('.trip_add_to_cart'));
	},



	trip_add_to_cart_handler: function() {

		book.add_to_cart_attempts = 0;

		$('.tickets_form').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				if (book.add_to_cart_attempts == 0) {

					$('.popup .waiting').fadeIn();

					if (form_submit.is_disabled('trip_add_to_cart')) return false;

					$('.validate_errors').hide();

					form_submit.change_state('trip_add_to_cart');
				}
			},
			error: function(jqXHR, textStatus, errorThrown) {

				if (book.add_to_cart_attempts < 3) {

					book.add_to_cart_attempts++;

					$('.tickets_form').submit();
				}
				else {

					$('.popup .waiting').fadeOut();

					book.add_to_cart_attempts = 0;

					error_text = 'Network error. Please update the page and try again.';
					if (textStatus) error_text += ' Technical information: '+textStatus;

					form_errors.show_validation([{text: error_text}], $('.trip_add_to_cart'));

					form_submit.change_state('trip_add_to_cart');
				}
			},
			success: function(responseText, textStatus) {

				book.add_to_cart_attempts = 0;

				if (responseText['errors']) {

					$('.popup .waiting').fadeOut();

					form_errors.show_validation(responseText['errors'], $('.trip_add_to_cart'));
				}
				else if (responseText['status'] == 'success') {

					send_tracking_data_to_analytics('addToCart', {
							'google_analytics_data': responseText['google_analytics_data']
						},
						function () {

							location.pathname = '/cart/list/';
						}
					);
				}

				if (responseText['status'] != 'success') form_submit.change_state('trip_add_to_cart');
			}
		});

		form_submit.register('trip_add_to_cart', $('.popup.trip_add_to_cart .add_to_cart'));

		$('.popup.trip_add_to_cart .add_to_cart').on('click', function() {

			$('.tickets_form').submit();
		});
	},
	show_tickets: function(calendar_id, trip_id) {

		if (calendar_id) show_tickets_params = '&calendar_id='+calendar_id;
		else if (trip_id) show_tickets_params = '&id='+trip_id;

		$.ajax({
			url: '/index.php?page=trip&mode=tickets'+show_tickets_params,
			type: 'get',
			dataType: 'html',
			success: function(html) {

				if (html.replace(/[\r|\n|\s]/gm, '') != '') {

					if (calendar_id) $('.popup.calendar .waiting').fadeOut();

					$('.popup.trip_add_to_cart').empty();
					$('.popup.trip_add_to_cart').html(html);

					typeCustom.init($('.popup.trip_add_to_cart input[type=checkbox]'));

					$('.popup.trip_add_to_cart .choose_another_date').on('click', function() {

						if ($('.popup.calendar .gift_option .checked').length) {

							$('.popup.calendar .gift_option .type_custom').removeClass('checked');
							$('.popup.calendar .gift_option .is_gift').prop('checked', false);
						}

						popup.closePopup($('.popup.trip_add_to_cart'));

						popup.openPopup('calendar');
						popup.handlers();
					});

					popup.closePopup($('.popup.calendar'));

					popup.openPopup('trip_add_to_cart');
					popup.handlers();


					if (typeof schedules_tickets != 'undefined') {

						select_design.apply($('.popup.trip_add_to_cart select'));

						if (!isUndefined(schedules_tickets[0])) {

							book.schedules = {};
							book.current_tickets = schedules_tickets[0].tickets;

							book.add_ticket();
						}
						else {

							book.schedules = schedules_tickets;

							book.show_shedules();
						}

						$('.popup.trip_add_to_cart .add_ticket').on('click', function() {

							book.add_ticket();
						});

						book.trip_add_to_cart_handler();
					}
				}
			}
		});
	},
	show_calendar: function(trip_id, month, show_no_gift) {

		$.ajax({
			url: '/index.php?page=trip&mode=calendar&id='+trip_id+(month ? '&month='+month : ''),
			type: 'get',
			dataType: 'html',
			beforeSend: function () {

				var wait_html = '<div class="waiting">\
					<img src="https://www.tripshock.com/img/wait.gif">\
					<p class="description">\
					Verifying availability, one moment...\
				</p>\
				</div>';

				$('.popup.calendar').empty();
				$('.popup.calendar').html(wait_html);
				popup.openPopup('calendar');
				$('.popup.calendar').css('height','743px');
				$('.popup.calendar .waiting').css('display','block');
			},
			success: function(html) {

				if (html.replace(/[\r|\n|\s]/gm, '') != '') {

					$('.popup.calendar').css('height','auto');
					$('.popup.calendar').empty();
					$('.popup.calendar').html(html);

					typeCustom.init($('.popup.calendar input[type=checkbox]'));

					select_design.apply($('#calendar_month_selector'));

					$('#calendar_month_selector').on('change', function() {

						trip_id = parseInt($(this).closest('.calendar_content').find('.trip_id').val());

						book.show_calendar(trip_id, $(this).val());
					});

					$('.popup.calendar .gift_option .is_gift').on('change', function() {

						checked = $(this).is(':checked');
						trip_id = parseInt($(this).closest('.calendar_content').find('.trip_id').val());
						can_be_gift = $('.popup.calendar .gift_option').data('canBeGift');

						if (can_be_gift === -1) {

							$('.popup.calendar .waiting').fadeIn();

							$.ajax({
								url: '/index.php?page=trip&mode=can_be_gift&trip_id=' + trip_id,
								type: 'get',
								dataType: 'json',
								error: function () {

									$('.popup.calendar .gift_option .is_gift').change();
								},
								success: function (response) {

									$('.popup.calendar .gift_option').data('canBeGift', ((response['result'] == true) ? 1 : 0));

									if (response['result'] == false) {

										$('.popup.calendar .waiting').fadeOut();

										if (checked) $('.popup.calendar .no_gift_booking').fadeIn();
										else $('.popup.calendar .no_gift_booking').fadeOut();
									}
									else {

										book.show_tickets(null, trip_id);

										return false;
									}
								}
							});
						}
						else {

							if (!can_be_gift) {

								if (checked) $('.popup.calendar .no_gift_booking').fadeIn();
								else $('.popup.calendar .no_gift_booking').fadeOut();
							}
							else {

								book.show_tickets(null, trip_id);

								return false;
							}
						}
					});

					$('.popup.calendar .no_gift_booking .hide_block').on('click', function() {

						$('.popup.calendar .no_gift_booking').fadeOut();

						$('.popup.calendar .gift_option .is_gift').prop('checked', false);
						$('.popup.calendar .gift_option .type_custom').removeClass('checked');
					});

					$('.popup.calendar .day:not(.not_available)').on('click', function() {

						calendar_id = $(this).data('calendarId');

						if (calendar_id) {

							$('.popup.calendar .waiting').fadeIn();

							book.show_tickets(calendar_id);
						}
					});

					if ($('.popup.calendar .go_avail_date').length) {

						$('.popup.calendar .go_avail_date .to_avail_date').on('click', function() {

							trip_id = parseInt($(this).closest('.calendar_content').find('.trip_id').val());
							month = $(this).data('month');

							book.show_calendar(trip_id, month);
						});
					}

					popup.handlers();

					if (!isUndefined(show_no_gift)) {

						$('.popup.calendar .no_gift_booking').fadeIn();
					}

					prepare_links_for_iframe();
				}
			}
		});
	},



	init_common: function() {

		$('.low_rate').each(function() {

			if (!$(this).data('tooltip')) {

				$(this).tooltip({
					position: 'center right',
					offset: [0, 5],
					onShow: function () {

						if (typeof(iframe_parent) !== "undefined") prepare_links_for_iframe();
					}
				});
			}
		});

		$('.admin_info .show').each(function() {

			if (!$(this).data('tooltip')) {

				$(this).tooltip({
					relative: true
				});
			}
		});

		$('.product .post a').on('click', function() {

			document.location.href = $(this).attr('href');

			return false;
		});

		$('.product').on('click', function() {

			main_link = $(this).data('mainLink');
			if (typeof(iframe_parent) !== "undefined") {

				main_link = $('.info h3 a', this).data('link-origin');
			}

			if (!isUndefined(main_link) && main_link) {

				var upper_container = 'div';

				if ($('.trips_search, .index_default, .itinerary_view').length) upper_container = '.deal_item';

				send_tracking_data_to_analytics(
					'productView',
					{
						name: $(this).closest(upper_container).find('.google_trip_name').val(),
						id: $(this).closest(upper_container).find('.google_trip_id').val(),
						brand: $(this).closest(upper_container).find('.google_trip_brand').val(),
						category: $(this).closest(upper_container).find('.google_trip_category').val()
					},
					function () {

						document.location.href = main_link;
					}
				);

				return false;
			}
		});
	}
};

var cart = {

	remove_cart_item: function(cart_item_id, change_button) {

		$.getJSON(
			'/index.php?page=cart&mode=delete_item',
			{
				cart_item_id: cart_item_id,
				change_button: change_button
			},
			function (data) {

				if (data.errors.length > 0) {

					for (var i = 0; i < data.errors.length; i++) alert(data.errors[i]);
				}
				else {

					send_tracking_data_to_analytics('removeFromCart', {
							'google_analytics_data': {
								0: google_analytics_data[cart_item_id]
							}
						},
						function () {

							window.location.assign('/cart/list/');
						}
					);
				}
			}
		);
	},

	add_cart_item: function(cart_item_id) {

		$.getJSON(
			'/index.php?page=cart&mode=add_item',
			{
				cart_item_id: cart_item_id,	
			},
			function (data) {

				if (data.errors.length > 0) {

					for (var i = 0; i < data.errors.length; i++) alert(data.errors[i]);
				}
				else {

					window.location.assign(data.link);
				}
			}
		);
	},

	contents: function() {

		if ($('.view_cart').length) {

			$('.view_cart').tooltip({
				position: 'bottom center',
				offset: [0, -250]
			});
		}


		$('.cart_contents .deal_item .remove_button_accept').on('click', function() {

			cart.remove_cart_item($(this).data('id'));
		});

		$('.cart_contents .deal_item .add_button_accept').on('click', function() {

			cart.add_cart_item($(this).data('id'));
		});

		if ($('.promo_seal').length) {

			$('.promo_seal').on('click', function() {

				$('.cart_promo').show();
				$(this).hide();
			});
		}

		$('.cart_contents .deal_item .rooms_count,\
			.cart_contents .deal_item .avg_rate .show,\
			.cart_contents .deal_item .cancellation .show,\
			.cart_contents .deal_item .surcharges .show,\
			.basket_totals .pay_delay .show').tooltip({
			relative: true,
			offset: [0, 0]
		});

		$('.cart_contents .deal_item .ticket').each(function () {

			if ($('.ticket_guest', this).length) {

				$('.show', this).tooltip({
					relative: true,
					offset: [0, 0]
				});
			}
		});
	}
};


var page_order = {

	// prevent form double submit
	submit_is_disabled: false,

	mode_step1: function() {

		$('#user_info .create_account input').on('change', function() {

			if ($(this).is(':checked')) $('#user_info .form_line.password').fadeIn();
			else $('#user_info .form_line.password').fadeOut();
		}).change();

		send_tracking_data_to_analytics('step1', {
				'google_analytics_data': google_analytics_data
			},
			function () {}
		);
	},
	step2_card_forms_setup: function(max_cards) {

		var card_id;

		var card_forms_html = '';

		var expire_month_html_ids = {
			1:'01 (January)',
			2:'02 (February)',
			3:'03 (March)',
			4:'04 (April)',
			5:'05 (May)',
			6:'06 (June)',
			7:'07 (July)',
			8:'08 (August)',
			9:'09 (September)',
			10:'10 (October)',
			11:'11 (November)',
			12:'12 (December)'
		};

		// Generating HTML forms for multiple cards
		for (card_id = 1; card_id <= max_cards; card_id++) {

			card_forms_html += '<div class="card_form' + card_id;
			if (card_id != 1) {

				card_forms_html += ' secondary_card">';
			}
			else {

				card_forms_html += '">';
			}

			card_forms_html += '<div class="form_group credit">';

			if (max_cards > 1) {

				card_forms_html += '\
			<div class="card_form_header">Credit Card ' + card_id + '</div>';
			}

			card_forms_html += '\
			<div class="info_text">\
				<span>Credit card number</span>, <span>card holder\'s name</span> and <span>expiration date</span> are usually located on the front of credit card\
				<br /><br />\
				Credit card <span>type</span> will be detected automaticly\
				<br /><br />\
				<span>CVV2</span> is a 3 digit code from the back of your card (4 digit on front of AMEX).\
			</div>';

			var amount_to_charge = 0;
			var card_type = '';
			var card_number = '';
			var card_first_name = '';
			var card_last_name = '';
			var card_csc = '';
			var card_expire_month = -1;
			var card_expire_year = -1;

			if (card_id == 1) {

				if (typeof(order_total) !== 'undefined') {

					order_total = order_total.toFixed(2);

					amount_to_charge = order_total;
				}
				else {

					amount_to_charge = 0;
				}

				if (typeof(user_data['card_number']) !== 'undefined') card_number = user_data['card_number'];
				if (typeof(user_data['card_type']) !== 'undefined') card_type = user_data['card_type'];

				if (typeof(user_data['card_first_name']) !== 'undefined') {

					card_first_name = user_data['card_first_name'];
				}
				else {

					card_first_name = user_data['first_name'];
				}

				if (typeof(user_data['card_last_name']) !== 'undefined') {

					card_last_name = user_data['card_last_name'];
				}
				else {

					card_last_name = user_data['last_name'];
				}

				if (typeof(user_data['card_csc']) !== 'undefined') card_csc = user_data['card_csc'];
				if (typeof(user_data['card_expire_month']) !== 'undefined') card_expire_month = user_data['card_expire_month'];
				if (typeof(user_data['card_expire_year']) !== 'undefined') card_expire_year = user_data['card_expire_year'];
			}

			card_forms_html += '\
			<input type="hidden" name="card' + card_id + '[card_id]" value="' + card_id + '" />\
			<span class="error_payment_id">\
				<input type="hidden" name="card' + card_id + '[error_payment_id]" value="0" />\
			</span>\
			<div class="form_line amount_to_charge">\
				<label>Amount To Charge</label>\
				<span class="input_container">\
					<input type="text" class="input_text" name="card' + card_id + '[amount_to_charge]" value="' + amount_to_charge + '">\
					<span class="required active">*</span>\
				</span>\
			</div>\
			<div class="form_line card_number">\
				<label>Credit card number</label>\
				<span class="input_container">\
					<input type="text" class="input_text ccard_number" name="card' + card_id + '[card_number]" value="' + card_number + '">\
					<span class="required active">*</span>\
				</span>\
				<span class="card_type_visible">\
					<i class="fa fa-cc-amex" data-type-name="American Express" aria-hidden="true"></i>\
					<i class="fa fa-cc-discover" data-type-name="Discover" aria-hidden="true"></i>\
					<i class="fa fa-cc-visa" data-type-name="Visa" aria-hidden="true"></i>\
					<i class="fa fa-cc-mastercard" data-type-name="MasterCard" aria-hidden="true"></i>\
				</span>\
				<input type="hidden" class="card_type_invisible" name="card' + card_id + '[card_type]" value="undefined" />\
			</div>\
			\
			<div class="form_line">\
				<label>Card holder\'s first name</label>\
				<span class="input_container">\
					<input type="text" class="input_text" name="card' + card_id + '[card_first_name]" value="' + card_first_name + '">\
					<span class="required active">*</span>\
				</span>\
			</div>\
			<div class="form_line">\
				<label>Card holder\'s last name</label>\
				<span class="input_container">\
					<input type="text" class="input_text" name="card' + card_id + '[card_last_name]" value="' + card_last_name + '">\
					<span class="required active">*</span>\
				</span>\
			</div>\
			<div class="form_line short_second">\
				<label>CVV2</label>\
				<span class="input_container">\
					<input type="text" class="input_text" name="card' + card_id + '[card_csc]" value="' + card_csc + '">\
					<span class="required active">*</span>\
				</span>\
			</div>\
			\
			<div class="form_line expiration">\
				<label>Expiration date</label>\
				<span class="input_container">\
					<span class="month_select">\
						<select name="card' + card_id + '[card_expire_month]">';

			$.each(expire_month_html_ids, function (month_id, month_name) {

				if (month_id != card_expire_month) {

					card_forms_html += '\
						<option value="' + month_id + '">' + month_name + '</option>';
				}
				else {

					card_forms_html += '\
						<option value="' + month_id + '" selected>' + month_name + '</option>';
				}
			});

			card_forms_html += '\
						</select>\
					</span>\
					<span class="year_select">\
						<select name="card' + card_id + '[card_expire_year]">';

			$.each(card_years, function (index, card_year) {

				if (card_year != '') {

					if (card_year != card_expire_year) {

						card_forms_html += '\
							<option value="' + card_year + '">' + card_year + '</option>';
					}
					else {

						card_forms_html += '\
							<option value="' + card_year + '" selected>' + card_year + '</option>';
					}
				}
			});

			card_forms_html += '\
						</select>\
					</span>\
					<span class="required active">*</span>\
				</span>\
			</div>';

			card_forms_html += '</div>'; // .credit

			card_forms_html += '\
			<div class="form_group billing">';

			var address = '';
			if (typeof(user_data['address']) !== 'undefined') address = user_data['address'];

			var city = '';
			if (typeof(user_data['city']) !== 'undefined') city = user_data['city'];

			var zip = '';
			if (typeof(user_data['zip']) !== 'undefined') zip = user_data['zip'];

			var country = '';
			var country_id = -1;
			if (typeof(user_data['country_id']) !== 'undefined') {

				country = countries[user_data['country_id']];
				country_id = user_data['country_id'];
			}

			var state = '';
			var state_id = -1;
			if ((typeof(user_data['state_name']) !== 'undefined') && user_data['state_name'] != '') {

				state = user_data['state_name'];
				state_id = 0;
			}
			else if (typeof(user_data['state_id']) !== 'undefined' && user_data['state_id'] != 0) {

				$.each(countries_states, function (country, states) {

					$.each(states, function (index, current_state) {

						if (current_state['id'] == user_data['state_id']) {

							state = current_state['name'];
							state_id = current_state['id'];
						}
					});
				});
			}


			card_forms_html += '\
				<div class="billing_address">\
				<div class="form_line">\
						<label>Billing Country</label>\
						<span class="input_container">\
							<select id="ucp_user_info'+card_id+'_country_id" name="card'+card_id+'[country_id]" size="1">';

			$.each(sort_countries, function (internal_id, value) {

				var id = value['id'];
				var name = value['name'];

				if (name !== '') {

					if (id !== country_id) {

						card_forms_html += '\
							<option value="' + id + '">' + name + '</option>';
					}
					else {

						card_forms_html += '\
							<option value="' + id + '" selected>' + name + '</option>';
					}
				}
			});

			var required_html = ((typeof(is_admin) !== "undefined") ? '' : '<span class="required active">*</span>');

			card_forms_html += '\
							</select>\
							'+required_html+'\
						</span>\
					</div>\
					<div class="form_line">\
						<label>Billing Street Address</label>\
						<span class="input_container">\
							<input type="text" class="input_text" name="card' + card_id + '[address]" value="' + address + '">\
							'+required_html+'\
						</span>\
					</div>\
					<div class="form_line">\
						<label>Billing City</label>\
						<span class="input_container">\
							<input type="text" class="input_text" name="card' + card_id + '[city]" value="' + city + '">\
							'+required_html+'\
						</span>\
					</div>\
					<div class="form_line">\
						<label>Billing State</label>\
						<span class="input_container">\
							<select id="ucp_user_info'+card_id+'_state_id" class="state_id_selecror" name="card' + card_id + '[state_id]">\
								<option class="italic">Select a state</option>';

			$.each(countries_states, function (country, states) {

				if (country != '') {

					card_forms_html += '\
								<optgroup label="' + country + '">';

					$.each(states, function (index, current_state) {

						var id = current_state['id'];
						var state = current_state['name'];

						if (id != state_id) {

							card_forms_html += '\
									<option value="' + id + '">' + state + '</option>';
						}
						else {

							card_forms_html += '\
									<option value="' + id + '" selected>' + state + '</option>';
						}
					});

					card_forms_html += '\
								</optgroup>';
				}
			});

			card_forms_html += '\
								<option value="0">Another state</option>\
							</select>\
							'+required_html+'\
						</span>\
					</div>';

			if (state_id != 0) state = '';

			card_forms_html += '\
			<div class="form_line">\
				<label>State name</label>\
				<span class="input_container">\
					<input type="text" class="input_text" name="card' + card_id + '[state_name]" value="' + state + '">\
					<span class="required active">*</span>\
				</span>\
			</div>\
			<div class="form_line">\
				<label>Billing Zip/Postal Code</label>\
				<span class="input_container short_second">\
					<input type="text" class="input_text" name="card' + card_id + '[zip]" value="' + zip + '">\
					<span class="required active">*</span>\
				</span>\
			</div>\
			';

			card_forms_html += '</div>'; // .billing_address

			card_forms_html += '</div>'; // .billing

			card_forms_html += '</div>'; // '.card_form' + card_id
		}


		// Rendering
		$('#order_step2 .credit_cards').append(card_forms_html);


		// Making our checkboxes nice
		typeCustom.init($('.billing input[type=checkbox]'));

		// Applying "Choosen" plug-in to selectors
		select_design.apply($('#order_step2 .credit select'));
		select_design.apply($('#order_step2 .billing select'));

		for (i = 1; i <= max_cards; i++) {

			$('.card_form' + i + ' .chosen-container').attr('style','width: 170px');
			$('.card_form' + i + ' .year_select .chosen-container').attr('style','width: 100px');
			$('.card_form' + i + ' .billing .chosen-container').attr('style','width: 323px');

			ucp.init_state_selection_by_country('ucp_user_info'+i);
		}

		$('.order_step2 .ccard_number').on('keyup', function() {

			var type = ccard_type_by_number($(this).val());

			$(this).closest('.form_line').find('.card_type_invisible').val(type);

			$(this).closest('.card_number').find('.card_type_visible .fa').each(function() {

				$(this).removeClass('active');
			});

			$(this).closest('.card_number').find('.card_type_visible .fa').each(function() {

				if ($(this).data('typeName') == type) $(this).addClass('active');
			});
		}).keyup();


		// Unknown state handler
		$('.billing .state_id_selecror').on('change', function() {

			if ($(':selected', this).val() == 0) {

				$(this).closest('.form_line').next('.form_line').fadeIn();
			}
			else {

				$(this).closest('.form_line').next('.form_line').fadeOut();
			}
		}).change();

		if (max_cards == 1) $('.card_form1 .amount_to_charge').hide();

		if (max_cards > 1) {

			// Split selector handler
			$('#order_step2_split_payment').on('change', function () {

				var cards_number = $(this).val();

				for (card_id = 2; card_id <= cards_number; card_id++) {

					if (!$('#order_step2 .card_form' + card_id).is(':visible')) {

						$('#order_step2 .card_form' + card_id).show();
					}
				}

				for (card_id = Number(cards_number) + 1; card_id <= max_cards; card_id++) {

					if ($('#order_step2 .card_form' + card_id).is(':visible')) {

						$('#order_step2 .card_form' + card_id).hide();
					}
				}

				if (cards_number == 1) {

					$('.card_form1 .amount_to_charge input').val(order_total);
				}
			}).change();

			// Disable multiple cards for deposit payment options
			$('#order_step2_payment_option').on('change', function () {

				var current_option = $(this).val();

				if (current_option != 0) {

					for (card_id = 2; card_id <= max_cards; card_id++) {

						if ($('#order_step2 .card_form' + card_id).is(':visible')) {

							$('#order_step2 .card_form' + card_id).hide();
						}
					}

					if ($('.card_form1 .amount_to_charge').is(':visible')) {

						$('.card_form1 .amount_to_charge').hide();
					}

					$('#order_step2_split_payment').val(1).trigger("chosen:updated");

					$('.split').hide();
				}
				else {

					if (!$('.card_form1 .amount_to_charge').is(':visible')) {

						$('.card_form1 .amount_to_charge').show();

						$('.card_form1 .amount_to_charge input').val(order_total);
					}

					$('.split').show();
				}
			});

			// Amount to charge handler
			var current_amount = 0;
			var total_amount = 0;

			$('.credit_cards .amount_to_charge input').on('focus', function () {

				current_amount = $(this).val();
			});

			$('.credit_cards .amount_to_charge input').on('change', function () {

				if (isNaN($(this).val())) {

					alert('Numbers only allowed!');
					$(this).val(current_amount);
				}
				else {

					if ($('#order_step2_split_payment').val() == 1) {

						$(this).val(current_amount);
					}
					else {

						for (card_id = 1; card_id <= max_cards; card_id++) {

							total_amount += Number($('.card_form' + card_id + ' .amount_to_charge input').val());
						}

						if (total_amount.toFixed(2) > order_total) {

							alert('Total amount should not be larger than ' + order_total);
							$(this).val(current_amount);
						}
						else {

							var current_card = $(this).closest('.form_group.credit').find('input:first').val();
							var next_card = Number(current_card) + 1;
							var visible_cards = Number($('#order_step2_split_payment').val());

							if (next_card <= visible_cards) {

								if (Number($('.card_form'+next_card+' .amount_to_charge input').val()) == 0) {

									var amount_left = order_total - total_amount.toFixed(2);

									$('.card_form'+next_card+' .amount_to_charge input').val(amount_left.toFixed(2));
								}
							}
						}

						total_amount = 0;
					}
				}
			});
		}
	},
	step2_payment_errors: function(errors_data) {

		$.each(errors_data.cards, function (card_id, payment_id) {

			$('.card_form' + card_id + ' .error_payment_id input').val(payment_id);
		});

		if (errors_data.partial_success == 1) {

			var active_cards = $('#order_step2_split_payment').val();

			$('#order_step2_split_payment').prop('disabled', true);
			$('#order_step2_split_payment').trigger("chosen:updated");
			$('#order_step2_split_payment').prop('disabled', false);

			$('#order_step2_payment_option').prop('disabled', true);
			$('#order_step2_payment_option').trigger("chosen:updated");
			$('#order_step2_payment_option').prop('disabled', false);

			for (var card_id = 1; card_id <= active_cards; card_id++) {

				var current_amount;

				$('#partial_success').val(1);

				if (card_id in errors_data.cards) {

					$('.card_form' + card_id + ' .amount_to_charge input').unbind();

					$('.card_form' + card_id + ' .amount_to_charge input').on('focus', function() {

						current_amount = $(this).val();
					});

					$('.card_form' + card_id + ' .amount_to_charge input').on('change', function() {

						$(this).val(current_amount);
					});

					$('.card_form' + card_id + ' .card_form_header').attr('style', 'color: #ff6666');
				}
				else {

					$('.card_form' + card_id).hide();
				}
			}
		}
	},
	mode_step2: function() {

		send_tracking_data_to_analytics('step2', {
				'google_analytics_data': google_analytics_data
			},
			function () {}
		);

		var form_fields = {};

		if (typeof(is_admin) !== "undefined") {

			page_order.step2_card_forms_setup(5);
		}
		else {

			$('#order_step2_split_payment').closest('.form_group').hide();
			page_order.step2_card_forms_setup(1);
		}

		$('.payment_method label').on('click', function () {

			$('.payment_method label').removeClass('active_link');
			$(this).addClass('active_link');

			var method = $(this).data('method');

			$('#order_step2_payment_method').val(method);

			if (method != "direct") {

				$("#order_step2 .credit_cards").hide();
				$("#order_step2 .paypal_disclamer").show();
			}
			else {

				$("#order_step2 .paypal_disclamer").hide();
				$("#order_step2 .credit_cards").show();
			}
		});


		$('.order_step2 .previous_info .edit').on('click', function() {

			popup.openPopup('user_info');

			select_design.apply($('.popup.user_info select'));
		});


		$('#order_step2_state_id').on('change', function() {

			if ($(':selected', this).val() == 0) $('#order_step2_state_name').closest('.form_line').fadeIn();
			else $('#order_step2_state_name').closest('.form_line').fadeOut();
		}).change();


		$('#ucp_user_info_country_id').on('change', function(event) {

			var country_name = $(':selected', this).html();
			var states_html = '<option class="italic">Select a state</option>';

			if ((typeof(countries_states) !== "undefined") && (typeof(countries_states[country_name]) !== "undefined")) {

				states_html += '<optgroup label="'+country_name+'">';

				$.each(countries_states[country_name], function(index, state) {

					var id = state['id'];
					var name = state['name'];

					if (!another_state && (typeof(state_choosed) !== "undefined") && (state_choosed == id)) {

						states_html += '<option value="'+id+'" selected>'+name+'</option>';
					}
					else {

						states_html += '<option value="'+id+'">'+name+'</option>';
					}
				});

				states_html += '</optgroup>';
			}

			if (another_state) {

				states_html += '<option value="0" selected>Another state</option>';
				another_state = false;
			}
			else {

				states_html += '<option value="0">Another state</option>';
				$('#ucp_user_info_state_name').val('');
			}

			$('#ucp_user_info_state_id').html(states_html).trigger("chosen:updated");
		}).change();


		$('#order_step2').ajaxForm({
			dataType: 'json',
			beforeSubmit: function(formData, jqForm, options) {

				if (form_submit.is_disabled('order_step2')) return false;

				$('.profile_form .validate_errors').hide();

				$.each(formData, function(index, data) {

					form_fields[data.name] = data.value;
				});

				form_submit.change_state('order_step2');

				$('.processing_message').fadeIn();
			},
			error: function(jqXHR, textStatus, errorThrown) {

				location.reload();
			},
			success: function(responseText, statusText) {

				if (responseText['status'] != 'success') form_submit.change_state('order_step2');

				if (responseText['errors']) {

					$('.processing_message').fadeOut();

					for (var key in responseText['errors']) {

						if (typeof(responseText['errors'][key].text) === 'object') {

							var card_errors = responseText['errors'][key].text;

							delete responseText['errors'][key];
						}
					}

					if (typeof(card_errors) !== 'undefined') {

						page_order.step2_payment_errors(card_errors);
					}

					form_errors.show_validation(responseText['errors'], $('.profile_form'));

					$('html, body').animate({scrollTop : 100}, 'slow');
				}
				else if (responseText['status'] === 'success') {

					if (responseText['pay_by'] === 'card') {

						location.pathname = '/order/view/' + responseText['order_id'] + '/';
					}
					else if (responseText['pay_by'] === 'paypal') {

						if (window!=window.top) {

							window.top.postMessage({go_away: responseText['redirect_link']}, iframe_parent);
						}
						else {

							location.href = responseText['redirect_link'];
						}
					}
				}
				else {

					location.reload();
				}
			}
		});

		form_submit.register('order_step2', $('.next_step'), true);

		$('.next_step').on('click', function() {

			$('#order_step2').submit();
		});
	},
	mode_access: function() {

		$('.access_key .submit').on('click', function() {

			$(this).closest('form').submit();
		});
	},
	mode_view: function() {

		$('.access_key .explain span').tooltip({
			relative: true,
			offset: [0, 5]
		});

		$('.share_block_buttons .facebook_share').popupWindow({
			width:550,
			height:400,
			centerBrowser:1
		});

		if ($('.share_content_select').length) {

			$('.share_content_select li').on('click', function() {

				$('.share_content_select li').prop('class', 'share_tab');
				$(this).prop('class', 'active active_share_tab');

				$('.single_product_share_block').hide();
				$('#share_block_'+this.id).show();

			});

			$('.share_content_select li:first').click();
		}
	},
	init: function() {

		$('.page_order form input[type=text], .page_order form input[type=password]').addClass('input_text');
		$('.popup.user_info input[type=text]').addClass('input_text');

		if ($('.order_step1').length) this.mode_step1();
		if ($('.order_step2').length) this.mode_step2();
		if ($('.order_access').length) this.mode_access();
	}
};

var zoom_slider_control = {
	zoomSlider: null,

	set_handlers: function() {

		$('.image_slider .controls .nav_prev').on('click', function() {

			zoom_slider_control.zoomSlider.previous();
		});
		$('.image_slider .controls .nav_next').on('click', function() {

			zoom_slider_control.zoomSlider.next();
		});
		$('.image_slider .controls .nav_auto').on('click', function() {

			zoom_slider_control.zoomSlider.switchAuto();

			$(this).toggleClass('fa-pause');
			$(this).toggleClass('fa-play-circle');
		});
	},
	init: function() {

		var zoomSliderOptions = {
			sliderId: 'zoom-slider',
			slideInterval: 5000,
			autoAdvance: true,
			captionOpacity: 0.5,
			captionEffect: 'fade',
			thumbnailsWrapperId: 'thumbs',
			thumbEffect: 0.5,
			license: 'b2r67'
		};

		zoom_slider_control.zoomSlider = new ZoomSlider(zoomSliderOptions);

		this.set_handlers();
	}
}

var page_deal = {

	mode_view: function() {

		$('.deal_item .photo .container').each(function() {

			$(this).imgLiquid();
		});

		$('.active_deal_container .adce_value').each(function() {

			date = $(this).data('date');

			$(this).internal_countdown({
				endDate: date,
				obj: this
			});
		});

        var deals_counter = 0;

		$('.deal_item').each(function() {

            deals_counter = deals_counter + 1;

            $(this).find('.number').html(deals_counter);
        });

		send_search_results_to_analytics('.deals_result');
	}
};

var page_destination = {

	mode_view: function() {

		designChanges.fullText();

		gallery.init();

		default_height = 160;
		min_height = default_height;

		$('.item_images img:visible').each(function() {

			if (this.clientHeight < min_height) min_height = this.clientHeight;
		});

		if (min_height != default_height) $('.item_images a').css('height', min_height);

		$('.show_photo_video').on('click', function() {

			popup.openPopup('destination_photo_video');

			help_links.change_tab('', $('.popup.destination_photo_video'));
		});

		$('.popup.destination_photo_video .help_links a').on('click', function() {

			help_links.change_tab($(this).data('tab'), $('.popup.destination_photo_video'));
		});

		$('.item_images a').on('click', function() {

			photo_id = $(this).data('id');

			popup.openPopup('destination_photo_video');

			help_links.change_tab('photos', $('.popup.destination_photo_video'));

			$('.destination_photo_video .gallery_preview #photo_'+photo_id+'_thumb img').click();
		});

		$('.top_deals .help_links a').on('click', function() {

			help_links.change_tab($(this).data('tab'), $('.top_deals'));
		});

		help_links.change_tab('', $('.top_deals'));

		if ($('.top_deals .tab_trips').length) $('.book_info .low_rate').tooltip();

		if ($('.top_deals .tab_trip').length) send_search_results_to_analytics('.top_deals .tab_trip');
	}
};

var page_index = {

	mode_default: function() {

		help_links.change_tab('', $('.top_deals'));

		$('.top_deals .help_links a').on('click', function() {

			help_links.change_tab($(this).data('tab'), $('.top_deals'));
		});
	}
};

var glossary = {

	init: function() {

		$('.glossary_view .book .city').unbind('change').on('change', function() {

			$('.glossary_view .book .trips').empty();

			activity_id = $(this).data('activityId');
			city_id = $(':selected', this).val();

			$.ajax({
				url: '/index.php?page=glossary&mode=get_trips&activity_id='+activity_id+'&city_id='+city_id,
				type: 'get',
				dataType: 'html',
				success: function(html) {

					if (html.replace(/[\r|\n|\s]/gm, '') != '') {

						$('.glossary_view .book .trips').html(html);

						book.init_activities();
					}
				}
			});
		}).change();
	}
};

var product_details = {

	delete_content: function(obj, content_type) {

		var id = $(obj).data('id');
		link = $(obj).data('link');

		if (link) {

			$.get(
				link,
				function (response, textStatus) {

					if ((textStatus != 'success') || !response) {

						alert('Connection problems. Please try again later.');
					}
					else {

						if (response.errors) alert(response.errors);
						else {

							if (content_type == 'photo') {

								alert('Deleted successfully.');

								location.reload();
							}
							else {

								$('#'+content_type+'_'+id).hide('slow', function () {
									$(this).remove();
									alert('Deleted successfully.');
								});
							}
						}
					}
				}
			);
		}
	},

	process_large_reviews: function() {

		if ($('.read_more_internal_link').length) {

			$('.full_review_text').hide();

			$('.read_more_internal_link').on('click', function() {

				$(this).closest('.review').find('.full_review_text').show();
				$(this).closest('.review').find('.short_review_text').hide();

				return false;
			});
		}
	},

	init: function() {

		$('.tab_reviews .delete').on('click', function() {

			product_details.delete_content(this, 'comment');
		});

		$('.tab_photos .delete').on('click', function() {

			product_details.delete_content(this, 'photo');
		});

		$('.tab_videos .delete').on('click', function() {

			product_details.delete_content(this, 'video');
		});

		if ($('.hotel_details').length) {

			if ($('.tab_rooms .info').length) {

				search_params = search_common.get_url_params_string('hotel', true);

				link = $('.tab_rooms .info a').attr('href') + search_params;

				$('.tab_rooms .info a').attr('href', link);
			}

			if ($('.rooms_content').length) {

				$('.rooms_content .room_table .room_photos div').each(function() {

					$(this).bjqs({
						'height' : 144,
						'width' : 187,
						'responsive' : true,
						'nexttext' : '<i class="fa fa-chevron-right next_photo"></i>',
						'prevtext' : '<i class="fa fa-chevron-left previous_photo"></i>',
						'showmarkers' : false,
						'keyboardnav' : false,
						'usecaptions' : false
					});
				});
			}
		}

		if ($('.details_block .main_photo').length) {

			$('.details_block .main_photo').imgLiquid();
		}

		if ($('.photo_icons').length) {

			$('.photo_icons .photo_icon').each(function() {

				$(this).imgLiquid();
			});

			$('.photo_icons .photo_icon').on("click", function() {

				var image = $(this).closest('.details_block').find('.main_photo img');
				$(image).attr('src', $(this).data('big-pic'));
				$(image).on('load',function() {

					$('.details_block .main_photo').imgLiquid();
				});
			});
		}

		if ($('.related_trips').length) {

			$('.related_trips .related_trip').each(function () {

				var image = $(this).find('.related_photo');
				$(image).imgLiquid();
			});
		}
		if ($('.related_hotels').length) {

			$('.related_hotels .related_hotel').each(function () {

				var image = $(this).find('.related_photo');
				$(image).imgLiquid();
			});
		}

		// For iframe portals only
		if (window!=window.top) {

			if (
				(getUrlParameterByName('page_num', true) !== "")
				||
				(getUrlParameterByName('star_filter', true) !== "")
			) {

				tabs.init();
				tabs.change('reviews');
			}
		}
	}
};

var page_comment = {

	init: function() {

		$('.page_comment input[type="text"]').addClass('input_text');

		$('.page_comment #review_edit_title').attr('placeholder', 'Tell us about your travel experience in one sentence.');

		$('.page_comment #review_edit_content').attr(
			'placeholder',
			'Quality reviews will provide a better travel experience for future customers. Share the pros and cons along with additional commentary regarding your trip.'
		);
	}
};

var partner = {

	current_review: 1,

	reviews_widget: function() {

		setInterval(function() {

				var switch_to = partner.current_review + 1;

				if (partner.current_review == total_reviews) {

					switch_to = 1;
				}

				$('.reviews .review:nth-child('+partner.current_review+')').fadeOut(400, function () {

					$('.reviews .review:nth-child('+switch_to+')').fadeIn();
				});

				partner.current_review = switch_to;
			},
			5000
		);
	}
};


var datepicker_custom = {

	fix_date_fields: function() {

		$('.dates input[type=text]').addClass('date input_text');
	},
	init: function(){

		$('input.date').datepicker({
			dateFormat: 'm/d/yy',
			minDate: '+0d',
			onSelect: function(dateText, inst) { datepicker_custom.active($(inst.input)); },
			beforeShow: function(input, inst) { datepicker_custom.openDate($(input)); },
			onClose: function(dateText, inst) { datepicker_custom.closeDate($(inst.input)); },
			onSelect: function(selectedDate) {

				is_date_from = $(this).parent().hasClass('date_from');

				var option = (is_date_from ? 'minDate' : 'maxDate');
				instance = $(this).data('datepicker');
				choosed_date = $.datepicker.parseDate(
					instance.settings.dateFormat ||
					$.datepicker._defaults.dateFormat,
					selectedDate,
					instance.settings
				);

				$(this).closest('.dates').find('input.date').not(this).datepicker('option', option, choosed_date);
			}
		});

		datepicker_custom.handlers();

		$('.date').siblings('.erase_date').on('click', function() {

			is_date_from = $(this).parent().hasClass('date_from');

			another_datepicker = $(this).closest('.dates').find('input.date').not(this);

			if (is_date_from) another_datepicker.datepicker('option', 'minDate', '+0d');
			else another_datepicker.datepicker('option', 'maxDate', '');

			$(this).siblings('.date').val('Any date');
			$(this).siblings('.date').data('default', 'Any date');
			$(this).siblings('.date').removeClass('focus');
		});
	},
	handlers: function(){
		$('.date_picker').on('click', function(){ $(this).prev().datepicker('show');});
	},
	active: function($input){
		$input.addClass('focus');
	},
	openDate: function ($input){
		$input.addClass('active');
		$input.next().addClass('active');
	},
	closeDate: function ($input){
		$input.removeClass('active');
		$input.next().removeClass('active');
	}
};

var ieFix = {
	init: function(){
		$('.green_button').each(function(){
			$('<ins class="ie"></ins>').appendTo($(this));
		});
	}
};

var popup = {
	init: function(){
		if ($('.overlay').is(':visible')){
			$('.overlay').css('height', this.calculateHeight());
			$('.popup').each(function(){
				if (!$(this).is(':visible')) return
				$(this).css('margin-top', -parseInt($(this).outerHeight())/2);
			});
		}
		this.handlers();
	},
	handlers: function(){
		$('.popup_link').unbind('click').on('click', function(e){
			popup.openPopup($(this).data('popup'));
			e.preventDefault();
		});
		$('.close', '.popup').unbind('click').on('click', function(e){
			popup.closePopup($(this));
			e.preventDefault();
		});
		$('.overflow_content').scroll(function(){
			var $this = $(this);
			var top = $this.scrollTop()
			$('.header_content', $this).css('top', top);
			$('.form_search', $this).css('top', top);
		});

		$('.overlay').on('click', function() {

			popup.closePopup($('.popup:visible'));
		});
	},
	openPopup: function(popup){
		$popup = $('.popup.'+popup+'');
		$overlay = $('.overlay');
		var winH = this.calculateHeight();
		$overlay.css('height', winH);

		if (isIE) $overlay.show();
		else $overlay.fadeIn();

		$popup.fadeIn();
		this.checkHeight($popup);
	},
	checkHeight: function($popup){

		height = $popup.outerHeight();

		if (height > $(window).height()) {

			$('.overflow_content', $popup).css('height', parseInt($('.overflow_content', $popup).height() - (height - $(window).height())));
		}

		$popup.css('top', $(window).scrollTop());
	},
	closePopup: function($close){
		$('.overlay').fadeOut();
		$close.closest('.popup').fadeOut();
	},
	calculateHeight: function(){
		var heightWindow = parseInt($(window).height());
		var documentH = parseInt($('body').height());
		if (documentH < heightWindow) documentH = heightWindow;
		return documentH;
	}
}

var dots = {
	init: function(){
		$('.last_orders li').each(function(){
			$this = $(this);
			widthTotal = parseInt($('.total', $this).outerWidth()) + parseInt($('.date', $this).outerWidth()) + parseInt($('.year', $this).outerWidth());
			$('.dots', $this).css('width', 230 - widthTotal);
		});
		$('.total_order li').each(function(){
			$this = $(this);
			widthTotal = parseInt($('.total', $this).outerWidth()) + parseInt($('.title', $this).outerWidth());
			$('.dots', $this).css('width', 230 - widthTotal);
		});
	}
};

var showFullClick = {

	init: function(){

		$('.full_click').unbind('click').on('click', function(e) {

			e.stopPropagation();

			$(this).toggleClass('active');
			e.preventDefault();
		});
	}
}

var showFull = {
	init: function(){
		$('.show_full').on('click', function(e){
			$(this).parent().addClass('active');
			e.preventDefault();
		});
		$('.hide_full').on('click', function(e){
			$(this).parent().removeClass('active');
			e.preventDefault();
		});
	}
}

var gallery = {
	speed: 300,
	init: function(){
		items = 0;
		active = 1;
		$tab = $('.tab_photos');
		$preview = $('.gallery_preview', $tab);
		$full = $('.gallery_middle', $tab);
		$left = $('.left_gal', $tab);
		$right = $('.right_gal', $tab);

		this.prepare();
	},
	prepare: function(){
		items = $('li', $preview).length;
		$preview.css('width', (items * 140) + (items * 6));
		$('li:eq(0)', $full).nextAll().hide();
		$('li:eq(0)', $preview).addClass('active');
		$('.photo_description .description:eq(0)').show();
		if (items <= 4){
			$left.hide();
			$right.hide();
		}
		this.handlers();
	},
	handlers: function(){
		$('img', $preview).on('click', function(){
			gallery.change($(this).parent().index());
		});
		$left.on('click', function(e){
			gallery.slide(-2);
			e.preventDefault();
		});
		$right.on('click', function(e){
			gallery.slide(2);
			e.preventDefault();
		});
	},
	change: function(index){
		$('li:eq('+index+')', $preview).addClass('active').siblings('.active').removeClass('active');
		$('li:visible', $full).fadeOut(gallery.speed, function(){
			$('li:eq('+index+')', $full).fadeIn(gallery.speed);
		});

		if ($('.photo_description').length) {

			photo_id = $('li:eq('+index+')', $preview).data('id');
			description_selector = '#photo_'+photo_id+'_description';

			$('.photo_description .description').not(description_selector).hide();
			$('.photo_description '+description_selector).show();
		}
	},
	slide: function(direction){
		if (((active + direction) <= 0) || ((active + direction) > (items - 3))) { return false; }
		active = active + direction;
		left = parseInt($preview.css('left')) + ((-1) * direction*146);

		$preview.animate({
			'left': left
		});
	}
};

var tabs = {

	available_tabs: [
		'overview',
		'reviews',
		'photos',
		'videos',
		'rooms'
	],

	init: function(){

		var tab = 'overview';

		$tabList = $('.tabs_list');
		$tabContent = $('.tabs_content');

		// can be used hash for links
		if (window.location.hash){
			var hash = window.location.hash.replace('#','');
			if (this.available_tabs.indexOf(hash) !== -1) tab = hash;
		}

		$('li#'+tab, $tabList).addClass('active');
		$('div.tab_'+tab, $tabContent).prevAll().hide().end().nextAll().hide();

		// for hotels in portals
		if ($('.rooms_content').length) current_tab = 'rooms';

		if ((typeof current_tab != 'undefined') && current_tab) tabs.change(current_tab);

		this.handlers();
	},
	handlers: function(){
		$('a', $tabList).on('click', function(e){
			if ($(this).hasClass('active')) return false;
			tabs.change($(this).parent().attr('id'));
			e.preventDefault();
		});
	},
	change: function(tab_name){
		$('li#'+tab_name, $tabList).addClass('active').siblings().removeClass('active');
		$(' > div.tab_'+tab_name, $tabContent).show().nextAll().hide().end().prevAll().hide();

		var scrollmem = document.documentElement.scrollTop || document.body.scrollTop;
		window.location.hash = tab_name;
		$('html,body').scrollTop(scrollmem);

		if (window!=window.top) window.top.postMessage({scroll: {top: $('li#'+tab_name).offset().top}}, iframe_parent);
		else $('html,body').animate({scrollTop : $('li#'+tab_name).offset().top}, 'slow');
	}
};

var typeCustom = {
	init: function($el){
		$el.each(function(){
			var $this = $(this);
			var $span = $this.parent();
			$span.addClass('active');

			if ($this.attr('checked')){
				$span.addClass('checked');
			}

			$span.on('click', function(){
				typeCustom.change($this, $span);
			});
		});
	},

	change: function($input, $parent){
		$parent.toggleClass('checked');
		if ($input.prop('checked')){
			$input.prop('checked', false);
		}else {
			$input.prop('checked', true);
		}
		$input.triggerHandler('change');
	}
};

var select_design = {

	apply: function(obj) {

		$(obj).chosen({
			max_selected_options: 5,
			disable_search_threshold: 50,
			search_contains: true
		});
	}
};

var watermarks = {
	size: 10,
	padding: 17,

	init: function(e){
		e.each(function(){
			var $this = $(this);
			$this.attr('data-default', $(this).attr('value'));
		});
		this.handlers(e);
	},
	handlers: function(e){
		$(e).on('focus', function(){

			var $this = $(this);
			$this.addClass('focus');
			if ($this.val() == $this.data('default')) $this.val('');
			$this.next('.add_text').css('left', $this.val().length * watermarks.size + watermarks.padding);
		});

		$(e).on('blur', function(){
			if ($(this).hasClass('.date')) return;
			var $this = $(this);
			$this.removeClass('focus');
			if ($this.val() == '') $this.val($this.data('default'));

			$this.next('.add_text').css('left', $this.val().length * watermarks.size + watermarks.padding);
		});
	},
};

var designChanges = {
	// Search help text
	searchInput: function(obj){

		if (isUndefined(obj)) var $search = $('.search');
		else var $search = obj;
		var $searchInput = $('.input_text', $search);
		var $searchHelp = $('.help_text', $search);
		$searchInput.on('focus', function(){
			$searchHelp.fadeOut();
		});
		$searchInput.on('blur', function(){
			$searchHelp.fadeIn();
		});
	},

	// Top link functionality
	topLink: function(){
		$('.top_link').on('click', function(e){
			$('html, body').animate({scrollTop : 0}, 'slow');
			e.preventDefault();
		});
	},

	// Full text slide
	fullText: function(){
		$('.text_full').on('click', '.full, .preview', function(e){
			$('.text_full').toggleClass('active');
			$('.text_full .text').slideToggle();
			e.preventDefault();
		});
	},

	// Right nav functionality
	rightSlide: function(){
		$('.right_nav').on('click', '.current', function(e){
			$(this).parents('.right_nav').toggleClass('open');
			$(this).nextAll().slideToggle();
			e.preventDefault();
		});
	}
}

$.fn.promoCarousel = function(options){
	var sTime = options.showTime;
	var cTime = options.switchTime;
	this.each(function(){
		var $carouselContainer = this;
		var $carousel = $('ul', $carouselContainer);
		var itemsNumber = $('li', $carousel).length;
		var itemsTimer;
		var currentItem = 1;
		var nextItem = null;

		if (itemsNumber > 1){
			$('li', $carousel).each(function(){
				$(this).hide();
				$('.description', $(this)).hide();
			});
			$('li:nth-child(1)', $carousel).fadeIn(cTime * 1000, function(){
				$('.description', $(this)).slideDown(300);
				$('.arrow', $carouselContainer).fadeIn();
			});
		}

		function switchItem() {
			clearInterval(itemsTimer);
			$('.description', $('li:nth-child(' + currentItem + ')', $carousel)).stop(true).slideUp(300, function(){
				$('li:nth-child(' + currentItem + ')', $carousel).fadeOut(cTime * 200);
					if (nextItem == null) {
						currentItem++;
					} else {
						currentItem = nextItem;
					}
					nextItem = null;
					if ((currentItem > itemsNumber) || (currentItem < 1)) { currentItem = 1; }
					$('li:nth-child(' + currentItem + ')', $carousel).fadeIn(cTime * 500, function(){
						$('.description', $('li:nth-child(' + currentItem + ')', $carousel)).slideDown(300);
					});
					itemsTimer = setInterval(switchItem, sTime * 1000);

			});
		}

		$('.previous', $carouselContainer).on('click', function(e) {
				nextItem = currentItem - 1;
				switchItem();
				e.preventDefault();
		 });
		$('.next', $carouselContainer).on('click', function(e) {
				nextItem = currentItem + 1;
				switchItem();
				e.preventDefault();
		});
		itemsTimer = setInterval(switchItem, sTime * 1000);
	});
};

$.fn.detailCarousel = function(options){
	var cTime = options.switchTime;
	this.each(function(){
		var $carouselContainer = this;
		var $full = $('.full_image', $carouselContainer);
		var $preview = $('.preview_image', $carouselContainer);
		var start = 0;

		prepare();

		function prepare(){
			$('li:eq('+start+')', $full).nextAll().hide();
			$('li:eq('+start+')', $preview).addClass('active');
		}

		function change(index){
			$('.active', $preview).removeClass('active');
			$('li:eq('+index+')', $preview).addClass('active');
			$('li:visible', $full).fadeOut(cTime * 100, function(){
				$('li:eq('+index+')', $full).fadeIn(cTime * 100);
			});
		}

		$('img', $preview).on('click', function(e) {
			if ($(this).parent().hasClass('active')) return false;
			if ($('li', $full).is(':animated')) return false;
			change($(this).parent().index());
			e.preventDefault();
		});
	});
};

