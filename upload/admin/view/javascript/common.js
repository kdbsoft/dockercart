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

		if (element.hasClass('form-group')) {
			element.addClass('has-error');
		}
	});

	// Error summary at the top of the form
	var $errors = $('#content .text-danger').filter(function() {
		var text = $.trim($(this).text());
		if (!text.length) return false;
		if (text === '*') return false;
		if ($(this).closest('.modal').length) return false;
		if (!$(this).closest('.form-group').length) return false;
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
				$field = $(this).closest('.form-group').find('input, textarea, select').first();
			}

			if ($field.length && $field.attr('id')) {
				list += '<li style="padding:3px 0;"><a href="javascript:void(0);" data-error-target="' + $field.attr('id') + '" style="color:#a94442;font-weight:500;text-decoration:underline;cursor:pointer;">' + msg + '</a></li>';
			} else {
				list += '<li style="padding:3px 0;">' + msg + '</li>';
			}
		});

		if (list) {
			var count = Object.keys(seen).length;
			var label = count === 1
				? 'Please correct the error below:'
				: 'Please correct the ' + count + ' errors below:';

			var html = '<div id="dcx-error-summary" class="alert alert-danger alert-dismissible">'
				+ '<button type="button" class="close" data-dismiss="alert">&times;</button>'
				+ '<p style="margin:0 0 6px;"><i data-lucide="circle-alert" width="16" height="16"></i> <strong>' + label + '</strong></p>'
				+ '<ul style="margin:0;padding-left:20px;">' + list + '</ul>'
				+ '</div>';

			var $target = $('#content > .container-fluid').first();
			if ($target.length) {
				$target.children('.alert').first().length
					? $target.children('.alert').first().after(html)
					: $target.prepend(html);
				lucide.createIcons();
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
				var $tabLink = $('.nav-tabs a[href="#' + paneId + '"]');
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
	
	$('#button-menu').on('click', function(e) {
		e.preventDefault();
		
		$('#column-left').toggleClass('active');
	});

	// Set last page opened on the menu
	$('#menu a[href]').on('click', function() {
		sessionStorage.setItem('menu', $(this).attr('href'));
	});

	if (!sessionStorage.getItem('menu')) {
		$('#menu #dashboard').addClass('active');
	} else {
		// Sets active and open to selected page in the left column menu.
		$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parent().addClass('active');
	}
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('li > a').removeClass('collapsed');
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('ul').addClass('in');
	
	$('#menu a[href=\'' + sessionStorage.getItem('menu') + '\']').parents('li').addClass('active');
	
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
