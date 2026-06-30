/* global jQuery, mcInt */
(function ($) {
	'use strict';

	function mcEsc(s) { return $('<div>').text(s === undefined || s === null ? '' : String(s)).html(); }
	function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; }

	var ADD_BTN = '<a href="#" class="mc-add-row" title="Add field">+</a>';
	var REM_BTN = '<a href="#" class="mc-remove-row" title="Remove">−</a>';

	// ---- per-feed helpers (a "feed" = one .mc-feed form-mapping block) ----
	function refreshRowButtons($feed) {
		var $rows = $feed.find('.mc-map-list .mc-map-row').not('.mc-map-template');
		var n = $rows.length;
		$rows.each(function (i) {
			var $act = $(this).find('.action_btns').empty();
			if (i === n - 1) { $act.html(ADD_BTN); }
			else if (!$(this).hasClass('mc-email-row')) { $act.html(REM_BTN); }
		});
	}
	function selTags($feed) { var s = $feed.data('selTags'); if (!s) { s = {}; $feed.data('selTags', s); } return s; }
	function syncTagSummary($feed) {
		var n = Object.keys(selTags($feed)).length;
		$feed.find('.mc-tagdrop-btn').text(n ? (n + ' tag' + (n > 1 ? 's' : '') + ' selected') : 'Choose Tags');
	}
	function pinSelectedTags($feed) {
		var $opts = $feed.find('.mc-tag-options');
		var checked = $opts.find('input.mc-tag-cb:checked').map(function () { return $(this).closest('label')[0]; }).get();
		checked.reverse().forEach(function (lbl) { $opts.prepend(lbl); });
	}
	function initTagState($feed) {
		var s = {};
		$feed.find('.mc-tag-cb:checked').each(function () { var v = $(this).val(); s[v] = v; });
		$feed.data('selTags', s);
		syncTagSummary($feed);
	}
	function initFeed($feed) { refreshRowButtons($feed); initTagState($feed); }

	function showFeedback($cform, type, html) {
		$cform.find('.mc-save-feedback').first().removeClass('success error').addClass(type).html(html).show();
	}

	function collapseFeed($feed) {
		$feed.find('.mc-feed-body').stop(true, true).slideUp(180);
		$feed.find('.mc-feed-caret').removeClass('dashicons-arrow-up-alt2').addClass('dashicons-arrow-down-alt2');
	}
	function expandFeed($feed) {
		$feed.find('.mc-feed-body').stop(true, true).slideDown(180);
		$feed.find('.mc-feed-caret').removeClass('dashicons-arrow-down-alt2').addClass('dashicons-arrow-up-alt2');
	}
	// Show the "no form mappings yet" message only when this connector has no feeds.
	function updateEmptyState($cform) {
		var has = $cform.find('.mc-feeds .mc-feed').not('.mc-feed-template').length > 0;
		$cform.find('.mc-feeds-empty').toggle(!has);
	}

	$(function () {
		// Init each feed; start as an accordion (only the first feed open per connector).
		$('.mc-connector-form').each(function () {
			$(this).find('.mc-feeds .mc-feed').not('.mc-feed-template').each(function (i) {
				initFeed($(this));
				if (i === 0) { expandFeed($(this)); } else { $(this).find('.mc-feed-body').hide(); collapseFeed($(this)); }
			});
			updateEmptyState($(this));
		});
	});

	// Feed accordion: opening a feed closes the others (one open at a time).
	$(document).on('click', '.mc-feed-head', function (e) {
		if ($(e.target).closest('.mc-feed-enable, .mc-remove-feed').length) {
			return;
		}
		var $feed = $(this).closest('.mc-feed');
		var wasOpen = $feed.find('.mc-feed-body').is(':visible');
		$feed.closest('.mc-connector-form').find('.mc-feed').not('.mc-feed-template').each(function () { collapseFeed($(this)); });
		if (!wasOpen) { expandFeed($feed); }
	});

	// Generic dropdown open/close (Form / List / Tags).
	$(document).on('click', '.mc-dropdown-btn', function (e) {
		e.preventDefault();
		var $c = $(this).siblings('.dropdown-content');
		$('.dropdown-content').not($c).hide();
		$c.toggle();
		if ($c.is(':visible')) {
			if ($(this).hasClass('mc-tagdrop-btn')) { pinSelectedTags($(this).closest('.mc-feed')); }
			$c.find('.mc-drop-search').trigger('focus');
		}
	});
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.mc-dropdown').length) { $('.dropdown-content').hide(); }
	});

	// Add mapping row (green "+").
	$(document).on('click', '.mc-add-row', function (e) {
		e.preventDefault();
		var $feed = $(this).closest('.mc-feed');
		var $last = $feed.find('.mc-map-list .mc-map-row').last();
		if (!$.trim($last.find('.mc-field-key').val())) {
			$last.find('.mc-field-key').addClass('mc-error-field');
			return;
		}
		var $clone = $feed.find('.mc-map-template').first().clone().removeClass('mc-map-template');
		$clone.show().find('.mc-field-key').val('');
		$feed.find('.mc-map-list').append($clone);
		refreshRowButtons($feed);
	});
	$(document).on('focus input change', '.mc-field-key', function () { $(this).removeClass('mc-error-field'); });

	// Remove mapping row (red "-").
	$(document).on('click', '.mc-remove-row', function (e) {
		e.preventDefault();
		var $feed = $(this).closest('.mc-feed');
		$(this).closest('.mc-map-row').remove();
		refreshRowButtons($feed);
	});

	// List: pick option (per feed).
	$(document).on('click', '.mc-list-opt', function () {
		var $feed = $(this).closest('.mc-feed');
		$(this).closest('.mc-list-options').find('.mc-list-opt').removeClass('active');
		$(this).addClass('active');
		$feed.find('.mc-list-id').val($(this).data('id'));
		$feed.find('.mc-listdrop-btn').text($(this).text()).removeClass('mc-error-field');
		$feed.find('.mc-listdrop-content').hide();
	});
	// List: server-side search (per feed).
	var doListSearch = debounce(function ($search) {
		var $feed = $search.closest('.mc-feed');
		var $opts = $feed.find('.mc-list-options');
		var sel = String($feed.find('.mc-list-id').val() || '');
		$.post(mcInt.ajaxurl, { action: 'mailercloud_search_lists', _ajax_nonce: mcInt.nonce, q: $search.val() })
			.done(function (d) {
				var rows = (d && d.results) || [];
				if (!rows.length) { $opts.html('<div class="mc-empty">No lists found.</div>'); return; }
				$opts.empty();
				rows.forEach(function (r) {
					$('<label class="mc-opt mc-list-opt' + (String(r.id) === sel ? ' active' : '') + '"></label>').attr('data-id', r.id).text(r.text).appendTo($opts);
				});
			});
	}, 300);
	$(document).on('input', '.mc-list-search', function () { doListSearch($(this)); });

	// Form: pick a form (per feed) -> load its fields into this feed's row dropdowns.
	$(document).on('click', '.mc-form-opt', function () {
		var $feed = $(this).closest('.mc-feed');
		$(this).closest('.mc-form-options').find('.mc-form-opt').removeClass('active');
		$(this).addClass('active');
		var fid = String($(this).data('id'));
		$feed.find('.mc-form-id').val(fid);
		$feed.find('.mc-formdrop-btn').text($(this).text()).removeClass('mc-error-field');
		$feed.find('.mc-feed-title').text($(this).text());
		$feed.find('.mc-formdrop-content').hide();
		loadFormFields($feed, fid);
	});
	$(document).on('input', '.mc-form-search', function () {
		var q = $(this).val().toLowerCase();
		$(this).closest('.dropdown-content').find('.mc-form-opt').each(function () {
			$(this).toggle($(this).text().toLowerCase().indexOf(q) !== -1);
		});
	});
	function loadFormFields($feed, formId) {
		var slug = $feed.closest('.mc-connector-form').data('slug');
		$('.loader_mailercloud').show();
		$.post(mcInt.ajaxurl, { action: 'mailercloud_get_form_fields', _ajax_nonce: mcInt.nonce, slug: slug, form_id: formId })
			.done(function (d) {
				var fields = (d && d.fields) || [];
				$feed.find('select.mc-field-key').each(function () {
					var $s = $(this), cur = $s.val();
					$s.empty().append($('<option value="">— Select form field —</option>'));
					fields.forEach(function (f) { $s.append($('<option></option>').val(f.key).text(f.label)); });
					$s.val(cur);
					if (cur && $s.val() !== cur) { $s.append($('<option></option>').val(cur).text(cur)); $s.val(cur); }
				});
			})
			.always(function () { $('.loader_mailercloud').hide(); });
	}

	// Tags: toggle + search (per feed).
	$(document).on('change', '.mc-tag-cb', function () {
		var $feed = $(this).closest('.mc-feed');
		var s = selTags($feed), v = $(this).val();
		if ($(this).is(':checked')) { s[v] = v; } else { delete s[v]; }
		syncTagSummary($feed);
	});
	var doTagSearch = debounce(function ($search) {
		var $feed = $search.closest('.mc-feed');
		var $opts = $feed.find('.mc-tag-options');
		var s = selTags($feed);
		$.post(mcInt.ajaxurl, { action: 'mailercloud_search_tags', _ajax_nonce: mcInt.nonce, q: $search.val() })
			.done(function (d) {
				var rows = (d && d.results) || [];
				if (!rows.length) { $opts.html('<div class="mc-empty">No tags found.</div>'); return; }
				$opts.empty();
				rows.forEach(function (r) {
					var $cb = $('<input type="checkbox" class="mc-tag-cb">').val(r.text).prop('checked', s.hasOwnProperty(r.text));
					$opts.append($('<label></label>').append($cb).append(document.createTextNode(' ' + r.text)));
				});
			});
	}, 300);
	$(document).on('input', '.mc-tag-search', function () { doTagSearch($(this)); });

	// Add a feed (form-mapping). Remove is handled below (with a confirm).
	$(document).on('click', '.mc-add-feed', function (e) {
		e.preventDefault();
		var $cform = $(this).closest('.mc-connector-form');
		// Collapse existing feeds, append a fresh open one (accordion).
		$cform.find('.mc-feeds .mc-feed').not('.mc-feed-template').each(function () { collapseFeed($(this)); });
		var $clone = $cform.find('.mc-feed-template').first().clone().removeClass('mc-feed-template').show();
		$clone.removeData('selTags');
		$cform.find('.mc-feeds').append($clone);
		initFeed($clone);
		$clone.find('.mc-feed-body').show();
		expandFeed($clone);
		updateEmptyState($cform);
		$clone.get(0).scrollIntoView({ behavior: 'smooth', block: 'center' });
	});

	// Map a validation key to the field that should get the red border.
	function feedErrorTarget($feed, key) {
		if (key === 'form') { return $feed.find('.mc-formdrop-btn'); }
		if (key === 'list') { return $feed.find('.mc-listdrop-btn'); }
		if (key === 'email') { return $feed.find('.mc-email-row .mc-field-key'); }
		return $();
	}
	function clearFeedErrors($feed) {
		$feed.find('.mc-formdrop-btn, .mc-listdrop-btn, .mc-field-key').removeClass('mc-error-field');
	}

	// Collect + validate ONE feed (the per-feed Save only touches its own feed).
	function collectFeed($feed) {
		var enabled = $feed.find('.mc-feed-enabled').is(':checked') ? 1 : 0;
		var form_id = $feed.find('.mc-form-id').val() || '';
		var list_id = $feed.find('.mc-list-id').val() || '';
		var hasFormPicker = $feed.find('.mc-form-id').length > 0;
		var tags = Object.keys(selTags($feed));
		var mapping = [], emailKey = '';
		$feed.find('.mc-map-list .mc-map-row').not('.mc-map-template').each(function () {
			var key = $.trim($(this).find('.mc-field-key').val());
			var attr = $(this).find('.mc-field-attr').val();
			if (key && attr) { mapping.push({ field_key: key, mc_attr: attr }); if (attr === 'email') { emailKey = key; } }
		});
		var errors = [];
		if (enabled) {
			if (hasFormPicker && !form_id) { errors.push('form'); }
			if (!emailKey)                 { errors.push('email'); }
			if (!list_id)                  { errors.push('list'); }
		}
		return {
			errors: errors,
			feed: { id: $feed.find('.mc-feed-id').val() || '', form_id: form_id, enabled: enabled, list_id: list_id, mapping: mapping, tags: tags }
		};
	}

	// Per-feed Save button — validates + saves only this feed (merged server-side).
	$(document).on('click', '.mc-save-feed', function (e) {
		e.preventDefault();
		var $feed = $(this).closest('.mc-feed');
		var $cform = $feed.closest('.mc-connector-form');
		var $fb = $feed.find('.mc-save-feedback').first();
		clearFeedErrors($feed);
		var c = collectFeed($feed);
		if (c.errors.length) {
			// Red-border the specific missing fields (no message box) and reveal the first.
			c.errors.forEach(function (k) { feedErrorTarget($feed, k).addClass('mc-error-field'); });
			$fb.hide().empty();
			var $first = feedErrorTarget($feed, c.errors[0]);
			if ($first.length) { $first[0].scrollIntoView({ behavior: 'smooth', block: 'center' }); }
			return;
		}
		$('.loader_mailercloud').show();
		$fb.removeClass('error').addClass('success').text('Saving…').show();
		$.post(mcInt.ajaxurl, {
			action: 'mailercloud_save_connector_map',
			_ajax_nonce: $cform.find('input[name="_ajax_nonce"]').val(),
			slug: $cform.data('slug'),
			feed: c.feed
		})
			.done(function (resp) {
				if (resp && resp.success) {
					if (resp.data && resp.data.feed_id) { $feed.find('.mc-feed-id').val(resp.data.feed_id); }
					$fb.removeClass('error').addClass('success').text('Settings saved.').show();
				} else {
					$fb.removeClass('success').addClass('error').html((resp && resp.data && resp.data.message) ? mcEsc(resp.data.message) : 'Could not save. Please try again.').show();
				}
			})
			.fail(function () { $fb.removeClass('success').addClass('error').text('Could not save. Please try again.').show(); })
			.always(function () { $('.loader_mailercloud').hide(); });
	});
	$(document).on('submit', '.mc-connector-form', function (e) { e.preventDefault(); });

	// Remove a feed — Mailercloud (SweetAlert) confirm, then delete from the backend.
	$(document).on('click', '.mc-remove-feed', function (e) {
		e.preventDefault();
		var $feed = $(this).closest('.mc-feed');
		var $cform = $feed.closest('.mc-connector-form');
		var feedId = $feed.find('.mc-feed-id').val() || '';
		var after = function () { $feed.remove(); updateEmptyState($cform); };
		var doRemove = function () {
			if (feedId) {
				$('.loader_mailercloud').show();
				$.post(mcInt.ajaxurl, {
					action: 'mailercloud_delete_connector_feed',
					_ajax_nonce: $cform.find('input[name="_ajax_nonce"]').val(),
					slug: $cform.data('slug'),
					feed_id: feedId
				}).always(function () { $('.loader_mailercloud').hide(); after(); });
			} else {
				after();
			}
		};
		if (typeof Swal !== 'undefined') {
			Swal.fire({
				title: 'Remove this form mapping?',
				text: 'This form will stop sending submissions to Mailercloud.',
				icon: 'warning',
				showCancelButton: true,
				confirmButtonText: 'Remove',
				confirmButtonColor: '#d63638',
				cancelButtonText: 'Cancel'
			}).then(function (r) { if (r.isConfirmed) { doRemove(); } });
		} else if (window.confirm('Remove this form mapping?')) {
			doRemove();
		}
	});

	// ---- Create New Property modal (reuses the existing create-property AJAX) ----
	$(document).on('click', '.mc-new-property', function (e) { e.preventDefault(); $('#mc-int-prop-modal').css('display', 'block'); });
	$(document).on('click', '.mc-prop-close', function () { $('#mc-int-prop-modal').hide(); });
	$(document).on('click', '#mc-int-prop-modal', function (e) { if (e.target === this) { $(this).hide(); } });
	$(document).on('submit', '.mc-prop-form', function (e) {
		e.preventDefault();
		var $form = $(this), $fb = $form.find('.mc-prop-feedback'), $btn = $form.find('.mc-prop-create');
		var data = {
			action: 'mailercloud_create_new_property',
			_ajax_nonce: $form.find('input[name="_ajax_nonce"]').val(),
			name: $.trim($form.find('.mc-prop-name').val()),
			type: $form.find('.mc-prop-type').val(),
			description: $form.find('.mc-prop-desc').val()
		};
		if (!data.name) { $fb.removeClass('success').addClass('error').html('Please enter an attribute name.').show(); return; }
		$btn.prop('disabled', true);
		$('.loader_mailercloud').show();
		$fb.removeClass('success error').text('Creating…').show();
		$.post(mcInt.ajaxurl, data, null, 'json')
			.done(function (resp) {
				if (resp && resp.id) {
					$('.mc-map-row').not('.mc-email-row').find('.mc-field-attr').each(function () {
						$(this).append($('<option></option>').attr('value', 'custom_fields_' + resp.id).text(resp.name));
					});
					$fb.removeClass('error').addClass('success').html('<b>' + mcEsc(resp.message || 'Property created.') + '</b>');
					$form.find('.mc-prop-name, .mc-prop-desc').val('');
					setTimeout(function () { $('#mc-int-prop-modal').hide(); $fb.hide().empty(); }, 1500);
				} else {
					var msg = (resp && resp.errors && resp.errors.length && resp.errors[0].message) ? resp.errors[0].message : ((resp && resp.message) ? resp.message : 'Could not create property.');
					$fb.removeClass('success').addClass('error').html(mcEsc(msg));
				}
			})
			.fail(function () { $fb.removeClass('success').addClass('error').text('Could not create property.'); })
			.always(function () { $btn.prop('disabled', false); $('.loader_mailercloud').hide(); });
	});
})(jQuery);
