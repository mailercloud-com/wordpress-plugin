/* global jQuery, mcInt */
(function ($) {
	'use strict';

	function mcEsc(s) { return $('<div>').text(s === undefined || s === null ? '' : String(s)).html(); }
	function debounce(fn, ms) { var t; return function () { var a = arguments, c = this; clearTimeout(t); t = setTimeout(function () { fn.apply(c, a); }, ms); }; }

	var ADD_BTN = '<a href="#" class="mc-add-row" title="Add field">+</a>';
	var REM_BTN = '<a href="#" class="mc-remove-row" title="Remove">−</a>'; // minus

	function refreshRowButtons($form) {
		var $rows = $form.find('.mc-map-row').not('.mc-map-template');
		var n = $rows.length;
		$rows.each(function (i) {
			var $act = $(this).find('.action_btns').empty();
			if (i === n - 1) { $act.html(ADD_BTN); }
			else if (!$(this).hasClass('mc-email-row')) { $act.html(REM_BTN); }
		});
	}

	// Per-form tag selection state (id -> name), so the selection survives searching.
	function selTags($form) { var s = $form.data('selTags'); if (!s) { s = {}; $form.data('selTags', s); } return s; }
	function syncTagSummary($form) {
		var n = Object.keys(selTags($form)).length;
		$form.find('.mc-tagdrop-btn').text(n ? (n + ' tag' + (n > 1 ? 's' : '') + ' selected') : 'Choose Tags');
	}

	// Move the currently-ticked tags to the top of the list (called when the dropdown opens).
	function pinSelectedTags($form) {
		var $opts = $form.find('.mc-tag-options');
		var checked = $opts.find('input.mc-tag-cb:checked').map(function () { return $(this).closest('label')[0]; }).get();
		checked.reverse().forEach(function (lbl) { $opts.prepend(lbl); });
	}
	function initTagState($form) {
		var s = {};
		$form.find('.mc-tag-cb:checked').each(function () { s[$(this).val()] = $(this).attr('data-name') || $.trim($(this).parent().text()); });
		$form.data('selTags', s);
		syncTagSummary($form);
	}

	function showFeedback($form, type, html) {
		$form.find('.mc-save-feedback').removeClass('success error').addClass(type).html(html).show();
	}

	$(function () {
		$('.mc-connector-form').each(function () { refreshRowButtons($(this)); initTagState($(this)); });
	});

	// Accordion.
	$(document).on('click', '.mc-int-card.mc-active .mc-int-head', function () {
		var $card = $(this).closest('.mc-int-card');
		$card.find('.mc-int-body').stop(true, true).slideToggle(220);
		$card.find('.mc-int-caret').toggleClass('dashicons-arrow-down-alt2 dashicons-arrow-up-alt2');
	});

	// Generic dropdown open/close (List + Tags).
	$(document).on('click', '.mc-dropdown-btn', function (e) {
		e.preventDefault();
		var $c = $(this).siblings('.dropdown-content');
		$('.dropdown-content').not($c).hide();
		$c.toggle();
		if ($c.is(':visible')) {
			if ($(this).hasClass('mc-tagdrop-btn')) { pinSelectedTags($(this).closest('.mc-connector-form')); }
			$c.find('.mc-drop-search').trigger('focus');
		}
	});
	$(document).on('click', function (e) {
		if (!$(e.target).closest('.mc-dropdown').length) { $('.dropdown-content').hide(); }
	});

	// "+ Add field" — blocked until the current last row has a field key.
	$(document).on('click', '.mc-add-row', function (e) {
		e.preventDefault();
		var $form = $(this).closest('.mc-connector-form');
		var $last = $form.find('.mc-map-row').not('.mc-map-template').last();
		if (!$.trim($last.find('.mc-field-key').val())) {
			$last.find('.mc-field-key').addClass('mc-error-field');
			return;
		}
		var $clone = $form.find('.mc-map-template').first().clone().removeClass('mc-map-template');
		$clone.show().find('.mc-field-key').val('');
		$form.find('.mc-map-list').append($clone);
		refreshRowButtons($form);
	});
	// Clear the validation border as soon as the field is focused or edited.
	$(document).on('focus input', '.mc-field-key', function () { $(this).removeClass('mc-error-field'); });

	// Remove row.
	$(document).on('click', '.mc-remove-row', function (e) {
		e.preventDefault();
		var $form = $(this).closest('.mc-connector-form');
		$(this).closest('.mc-map-row').remove();
		refreshRowButtons($form);
	});

	// List: pick an option (single select).
	$(document).on('click', '.mc-list-opt', function () {
		var $form = $(this).closest('.mc-connector-form');
		$(this).closest('.mc-list-options').find('.mc-list-opt').removeClass('active');
		$(this).addClass('active');
		$form.find('.mc-list-id').val($(this).data('id'));
		$form.find('.mc-listdrop-btn').text($(this).text());
		$form.find('.mc-listdrop-content').hide();
	});

	// List: server-side search.
	var doListSearch = debounce(function ($search) {
		var $form = $search.closest('.mc-connector-form');
		var $opts = $form.find('.mc-list-options');
		var sel = String($form.find('.mc-list-id').val() || '');
		$.post(mcInt.ajaxurl, { action: 'mailercloud_search_lists', _ajax_nonce: mcInt.nonce, q: $search.val() })
			.done(function (d) {
				var rows = (d && d.results) || [];
				if (!rows.length) { $opts.html('<div class="mc-empty">No lists found.</div>'); return; }
				$opts.empty();
				rows.forEach(function (r) {
					$('<label class="mc-opt mc-list-opt' + (String(r.id) === sel ? ' active' : '') + '"></label>')
						.attr('data-id', r.id).text(r.text).appendTo($opts);
				});
			});
	}, 300);
	$(document).on('input', '.mc-list-search', function () { doListSearch($(this)); });

	// Tags: checkbox toggle (updates the persistent state).
	$(document).on('change', '.mc-tag-cb', function () {
		var $form = $(this).closest('.mc-connector-form');
		var s = selTags($form);
		if ($(this).is(':checked')) { s[$(this).val()] = $(this).attr('data-name') || $.trim($(this).parent().text()); }
		else { delete s[$(this).val()]; }
		syncTagSummary($form);
	});

	// Tags: server-side search (re-checks anything already selected).
	var doTagSearch = debounce(function ($search) {
		var $form = $search.closest('.mc-connector-form');
		var $opts = $form.find('.mc-tag-options');
		var s = selTags($form);
		$.post(mcInt.ajaxurl, { action: 'mailercloud_search_tags', _ajax_nonce: mcInt.nonce, q: $search.val() })
			.done(function (d) {
				var rows = (d && d.results) || [];
				if (!rows.length) { $opts.html('<div class="mc-empty">No tags found.</div>'); return; }
				$opts.empty();
				rows.forEach(function (r) {
					var $cb = $('<input type="checkbox" class="mc-tag-cb">').val(r.id).attr('data-name', r.text).prop('checked', s.hasOwnProperty(String(r.id)));
					$opts.append($('<label></label>').append($cb).append(document.createTextNode(' ' + r.text)));
				});
			});
	}, 300);
	$(document).on('input', '.mc-tag-search', function () { doTagSearch($(this)); });

	// Save.
	$(document).on('submit', '.mc-connector-form', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $btn = $form.find('.mc-save-connector');
		var enabled = $form.find('input[name="enabled"]').is(':checked') ? 1 : 0;
		var list_id = $form.find('.mc-list-id').val() || '';
		var tags = Object.keys(selTags($form));

		var mapping = [];
		var emailKey = '';
		$form.find('.mc-map-row').not('.mc-map-template').each(function () {
			var key = $.trim($(this).find('.mc-field-key').val());
			var attr = $(this).find('.mc-field-attr').val();
			if (key && attr) { mapping.push({ field_key: key, mc_attr: attr }); if (attr === 'email') { emailKey = key; } }
		});

		if (enabled) {
			if (!list_id) { showFeedback($form, 'error', 'Please choose a list to add contacts to.'); return; }
			if (!emailKey) { showFeedback($form, 'error', 'Please map a form field to <b>Email</b> — it is required.'); return; }
		}

		var data = {
			action: 'mailercloud_save_connector_map',
			_ajax_nonce: $form.find('input[name="_ajax_nonce"]').val(),
			slug: $form.data('slug'),
			enabled: enabled, list_id: list_id, mapping: mapping, tags: tags
		};
		$btn.prop('disabled', true);
		$('.loader_mailercloud').show();
		showFeedback($form, 'success', 'Saving…');
		$.post(mcInt.ajaxurl, data)
			.done(function (resp) {
				if (resp && resp.success) {
					showFeedback($form, 'success', enabled ? 'Settings saved. This form will now send submissions to Mailercloud.' : 'Settings saved.');
				} else {
					showFeedback($form, 'error', (resp && resp.data && resp.data.message) ? mcEsc(resp.data.message) : 'Could not save. Please try again.');
				}
			})
			.fail(function () { showFeedback($form, 'error', 'Could not save. Please try again.'); })
			.always(function () { $btn.prop('disabled', false); $('.loader_mailercloud').hide(); });
	});

	// ----- Create New Property modal (reuses the existing create-property AJAX) -----
	$(document).on('click', '.mc-new-property', function (e) { e.preventDefault(); $('#mc-int-prop-modal').css('display', 'block'); });
	$(document).on('click', '.mc-prop-close', function () { $('#mc-int-prop-modal').hide(); });
	$(document).on('click', '#mc-int-prop-modal', function (e) { if (e.target === this) { $(this).hide(); } });

	$(document).on('submit', '.mc-prop-form', function (e) {
		e.preventDefault();
		var $form = $(this);
		var $fb = $form.find('.mc-prop-feedback');
		var $btn = $form.find('.mc-prop-create');
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
					// Add the new property to every (non-email) Mailercloud-field dropdown.
					$('.mc-map-row').not('.mc-email-row').find('.mc-field-attr').each(function () {
						$(this).append($('<option></option>').attr('value', 'custom_fields_' + resp.id).text(resp.name));
					});
					$fb.removeClass('error').addClass('success').html('<b>' + mcEsc(resp.message || 'Property created.') + '</b>');
					$form.find('.mc-prop-name, .mc-prop-desc').val('');
					setTimeout(function () { $('#mc-int-prop-modal').hide(); $fb.hide().empty(); }, 1500);
				} else {
					var msg = (resp && resp.errors && resp.errors.length && resp.errors[0].message) ? resp.errors[0].message
						: ((resp && resp.message) ? resp.message : 'Could not create property.');
					$fb.removeClass('success').addClass('error').html(mcEsc(msg));
				}
			})
			.fail(function () { $fb.removeClass('success').addClass('error').text('Could not create property.'); })
			.always(function () { $btn.prop('disabled', false); $('.loader_mailercloud').hide(); });
	});
})(jQuery);
