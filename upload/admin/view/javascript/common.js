function getURLVar(key) {
	var value = [];

	var query = String(document.location).split('?');

	if (query[1]) {
		var part = query[1].split('&');

		for (i = 0; i < part.length; i++) {
			var data = part[i].split('=');

			if (data[0] && data[1]) {
				value[data[0]] = data[1];
			}
		}

		if (value[key]) {
			return value[key];
		} else {
			return '';
		}
	}
}

$(document).ready(function() {
	//Form Submit for IE Browser
	$('button[type=\'submit\']').on('click', function() {
		$("form[id*='form-']").submit();
	});

	// Highlight any found errors
	$('.text-danger').each(function() {
		var element = $(this).parent().parent();

		// Skip the "required field" asterisk — it's a marker, not an error
		if ($.trim($(this).text()) === '*') return;

		if (element.hasClass('form-group') || element.hasClass('dcx-field')) {
			element.addClass('has-error');
		}
	});

	// Error summary at the top of the form
	var $errors = $('#content .text-danger').filter(function() {
		var text = $.trim($(this).text());
		if (!text.length) return false;
		if (text === '*') return false;
		if ($(this).closest('.modal').length) return false;
		if (!$(this).closest('.form-group, .dcx-field').length) return false;
		return true;
	});

	if ($errors.length) {
		var seen = {};
		var list = '';

		$errors.each(function() {
			var msg = $.trim($(this).text());
			if (seen[msg]) return;
			seen[msg] = true;

			var $field = $(this).siblings('input, textarea, select').first();
			if (!$field.length) {
				$field = $(this).closest('.form-group, .dcx-field').find('input, textarea, select').first();
			}

			if ($field.length && $field.attr('id')) {
				list += '<li style="padding:3px 0;"><a href="javascript:void(0);" data-error-target="' + $field.attr('id') + '" style="color:#a94442;font-weight:500;text-decoration:underline;cursor:pointer;">' + msg + '</a></li>';
			} else {
				list += '<li style="padding:3px 0;">' + msg + '</li>';
			}
		});

		if (list) {
			var count = Object.keys(seen).length;
			var label = '';
			if (count === 1) {
				label = $('body').attr('data-error-summary-one') || 'Please correct the error below:';
			} else {
				var many = $('body').attr('data-error-summary-many') || 'Please correct the ' + count + ' errors below:';
				label = many.replace('%s', count);
			}

			var html = '<div id="dcx-error-summary" class="alert alert-danger alert-dismissible">'
				+ '<button type="button" class="close" data-dismiss="alert">&times;</button>'
				+ '<p style="margin:0 0 6px;"><i data-lucide="circle-alert" width="16" height="16"></i> <strong>' + label + '</strong></p>'
				+ '<ul style="margin:0;padding-left:20px;">' + list + '</ul>'
				+ '</div>';

			var $target = $('#content .page-header .container-fluid').first().length
				? $('#content .page-header .container-fluid').first()
				: $('#content > .container-fluid').first();
			if ($target.length) {
				var $alertRow = $target.children('.row').filter(function() {
					return $(this).find('.alert').length > 0;
				}).first();

				// Copy the column classes from the existing alert row so the
				// summary lines up with the other alerts in the page header
				var colClass = 'col-sm-12';
				if ($alertRow.length) {
					var $alertCol = $alertRow.find('.alert').first().closest('[class*="col-"]');
					if ($alertCol.length && $alertCol.attr('class')) {
						colClass = $alertCol.attr('class').replace(/\s+/g, ' ').trim();
					}
				}

				var $summaryRow = $('<div class="row"><div class="' + colClass + '">' + html + '</div></div>');

				if ($alertRow.length) {
					$alertRow.after($summaryRow);
				} else {
					$target.children().first().after($summaryRow);
				}
				if (typeof lucide !== 'undefined' && typeof lucide.createIcons === 'function') {
					lucide.createIcons();
				}
			}
		}
	}

	// Error summary: activate tabs and scroll to field
	$(document).on('click', '#dcx-error-summary a[data-error-target]', function(e) {
		e.preventDefault();
		var targetId = $(this).data('error-target');
		var $field = $('#' + targetId);
		if (!$field.length) return;

		var $panes = $field.parents('.tab-pane');
		$($panes.get().reverse()).each(function() {
			var paneId = $(this).attr('id');
			if (paneId) {
				var $tabLink = $('.nav-tabs a[href="#' + paneId + '"], .dcx-pills a[href="#' + paneId + '"]');
				if ($tabLink.length) {
					$tabLink.tab('show');
				}
			}
		});

		$('html, body').animate({scrollTop: $field.offset().top - 50}, 300);
	});

	// tooltips on hover
	$('[data-toggle=\'tooltip\']').tooltip({container: 'body', html: true});

	// Makes tooltips work on ajax generated content
	$(document).ajaxStop(function() {
		$('[data-toggle=\'tooltip\']').tooltip({container: 'body'});
	});

	// https://github.com/opencart/opencart/issues/2595
	$.event.special.remove = {
		remove: function(o) {
			if (o.handler) {
				o.handler.apply(this, arguments);
			}
		}
	}
	
	// tooltip remove
	$('[data-toggle=\'tooltip\']').on('remove', function() {
		$(this).tooltip('destroy');
	});

	// Tooltip remove fixed
	$(document).on('click', '[data-toggle=\'tooltip\']', function(e) {
		$('body > .tooltip').remove();
	});

	// Show/hide selection-dependent header buttons (class 'dc-btn-selection')
	function dcUpdateSelectionButtons() {
		var checked = $('input[name^=\'selected\']:checked').length > 0;
		$('.dc-btn-selection').toggle(checked);
	}

	$(document).on('change', 'input[name^=\'selected\']', function() {
		dcUpdateSelectionButtons();
	});

	// Header select-all sets rows via .prop() which fires no change events;
	// recompute selection buttons after its inline onclick has run
	$(document).on('click change', 'thead input[type=\'checkbox\']', function() {
		dcUpdateSelectionButtons();
	});

	dcUpdateSelectionButtons();

	// Show/hide buttons whose disabled state changes (class 'dc-btn-disabled')
	function dcSyncDisabledButtons() {
		$('.dc-btn-disabled').each(function() {
			$(this).toggle(!$(this).prop('disabled'));
		});
	}

	$(document).on('change', 'input, select, textarea', dcSyncDisabledButtons);
	$(document).ajaxStop(dcSyncDisabledButtons);
	dcSyncDisabledButtons();
	
	$('#button-menu').on('click', function(e) {
		e.preventDefault();
		
		$('#column-left').toggleClass('active');
	});

	// Highlight the menu item matching the current page route
	var dcRoute = getURLVar('route');

	if (dcRoute) {
		// Pages whose route differs from the menu link they belong to
		var dcRouteAlias = {
			'setting/setting': 'setting/store'
		};

		var dcCurrent = dcRouteAlias[dcRoute] || dcRoute;
		var dcBestLink = null;
		var dcBestLength = 0;

		$('#menu a[href]').each(function() {
			var href = String($(this).attr('href'));

			if (href.charAt(0) === '#') {
				return; // collapse toggle, not a page
			}

			var match = href.match(/[?&]route=([^&]+)/);

			if (!match) {
				return;
			}

			var menuRoute = decodeURIComponent(match[1]);

			// Segment-boundary prefix match so e.g. sale/order_detail or
			// catalog/product/edit highlights their list-page menu entry,
			// but catalog/option_set does not highlight catalog/option
			if (menuRoute === dcCurrent || dcCurrent.indexOf(menuRoute + '/') === 0 || dcCurrent.indexOf(menuRoute + '_') === 0 || dcCurrent.indexOf(menuRoute + '.') === 0) {
				if (menuRoute.length > dcBestLength) {
					dcBestLength = menuRoute.length;
					dcBestLink = this;
				}
			}
		});

		if (dcBestLink) {
			var $link = $(dcBestLink);

			$link.parent().addClass('active');
			$link.closest('#menu > li').addClass('active');

			// Expand the submenus leading to the active item
			$link.parents('#menu ul.collapse').addClass('in');
			$link.parents('#menu li').children('a.parent').removeClass('collapsed');
		}
	} else if ($('#menu #menu-dashboard').length) {
		$('#menu #menu-dashboard').addClass('active');
	}
	
	// Image Manager
	$(document).on('click', 'a[data-toggle=\'image\']', function(e) {
		var $element = $(this);

		e.preventDefault();

		// clean up any previous outside-click handler
		$(document).off('click.image-popover');

		// destroy all image popovers
		$('a[data-toggle="image"]').popover('destroy');

		// remove flickering (do not re-add popover when clicking for removal)
		if ($element.data('bs.popover')) {
			return;
		}

		$element.popover({
			container: 'body',
			html: true,
			sanitize: false,
			placement: 'right',
			trigger: 'manual',
			content: function() {
				return '<div style="display:flex;gap:8px;"><button type="button" id="button-image" class="btn btn-primary"><i data-lucide="pencil" width="14" height="14"></i></button><button type="button" id="button-clear" class="btn btn-danger"><i data-lucide="trash-2" width="14" height="14"></i></button></div>';
			}
		});

		$element.popover('show');
		lucide.createIcons();

		$(document).on('click.image-popover', function(e) {
			if ($(e.target).closest('.popover').length || $(e.target).closest($element).length) {
				return;
			}
			$('a[data-toggle="image"]').popover('destroy');
			$(document).off('click.image-popover');
		});

		setTimeout(function(){ // fix bind events on new popover when 

			$('#button-image').on('click', function() {
				var $button = $(this);
				var $icon   = $button.find('> i');

				$('#modal-image').remove();

				var fileType = $element.attr('data-type') || 'image';

				$.ajax({
					url: 'index.php?route=common/filemanager&user_token=' + getURLVar('user_token') + '&target=' + $element.parent().find('input').attr('id') + '&thumb=' + $element.attr('id') + '&type=' + fileType,
					dataType: 'html',
					beforeSend: function() {
						$button.prop('disabled', true);
						if ($icon.length) {
							$icon.addClass('dc-spin');
						}
					},
					complete: function() {
						$button.prop('disabled', false);

						if ($icon.length) {
							$icon.removeClass('dc-spin');
						}
					},
					success: function(html) {
						$('body').append('<div id="modal-image" class="modal">' + html + '</div>');

						$('#modal-image').modal('show');
					}
				});

			$(document).off('click.image-popover');
			$element.popover('destroy');
		});

		$('#button-clear').on('click', function() {
			$element.find('img').attr('src', $element.find('img').attr('data-placeholder'));

			$element.parent().find('input').val('');

			$(document).off('click.image-popover');
			$element.popover('destroy');
		});
			
		}, 250); // end timeout fix
			
	});
});

// Whole-row navigation for list tables
var inlineEditOpen = false;

$(document).on('mousedown', 'tr[data-href]', function(e) {
	if ($(e.target).closest('a, button, input, select, textarea, .inline-edit-icon, .inline-edit-input, .inline-edit-name-input, .inline-edit-name-save, .inline-edit-names, .inline-edit').length) {
		return;
	}

	inlineEditOpen = $('.inline-edit.editing').length > 0;
});

$(document).on('click', 'tr[data-href]', function(e) {
	// An inline-edit handler may have replaced the clicked element's cell
	// (e.g. when opening an editor), which detaches e.target — in that case
	// the click belongs to the editor interaction, not to row navigation
	if (!e.target.isConnected) {
		return;
	}

	if ($(e.target).closest('a, button, input, select, textarea, .inline-edit-icon, .inline-edit-input, .inline-edit-name-input, .inline-edit-name-save, .inline-edit-names, .inline-edit').length) {
		return;
	}

	// If an inline editor was open on mousedown, this click only closes it;
	// the next click navigates
	if (inlineEditOpen) {
		inlineEditOpen = false;
		return;
	}

	window.location = $(this).data('href');
});
