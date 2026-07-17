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
				+ '<p style="margin:0 0 6px;"><i class="fa fa-exclamation-circle"></i> <strong>' + label + '</strong></p>'
				+ '<ul style="margin:0;padding-left:20px;">' + list + '</ul>'
				+ '</div>';

			var $target = $('#content > .container-fluid').first();
			if ($target.length) {
				$target.children('.alert').first().length
					? $target.children('.alert').first().after(html)
					: $target.prepend(html);
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
		var $popover = $element.data('bs.popover'); // element has bs popover?

		e.preventDefault();

		// destroy all image popovers
		$('a[data-toggle="image"]').popover('destroy');

		// remove flickering (do not re-add popover when clicking for removal)
		if ($popover) {
			return;
		}

		$element.popover({
			html: true,
			sanitize: false,
			placement: 'right',
			trigger: 'manual',
			content: function() {
				return '<div style="display:flex;gap:8px;"><button type="button" id="button-image" class="btn btn-primary"><i class="fa fa-pencil"></i></button><button type="button" id="button-clear" class="btn btn-danger"><i class="fa fa-trash-o"></i></button></div>';
			}
		});

		$element.popover('show');

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
							$icon.attr('class', 'fa fa-circle-o-notch fa-spin');
						}
					},
					complete: function() {
						$button.prop('disabled', false);

						if ($icon.length) {
							$icon.attr('class', 'fa fa-pencil');
						}
					},
					success: function(html) {
						$('body').append('<div id="modal-image" class="modal">' + html + '</div>');

						$('#modal-image').modal('show');
					}
				});

				$element.popover('destroy');
			});

			$('#button-clear').on('click', function() {
				$element.find('img').attr('src', $element.find('img').attr('data-placeholder'));

				$element.parent().find('input').val('');

				$element.popover('destroy');
			});
			
		}, 250); // end timeout fix
			
	});
});

// Autocomplete */
(function($) {
	$.fn.autocomplete = function(option) {
		return this.each(function() {
			var $this = $(this);
			var $dropdown = $('<ul class="dropdown-menu" />');
			var el = this;

			el.timer = null;
			el.items = [];

			$.extend(el, option);

			$this.attr('autocomplete', 'off');

			// Wrap input for clear button
			var $wrapper = $('<div class="dc-autocomplete-wrap" />');
			$this.wrap($wrapper);
			var $wrapperEl = $this.closest('.dc-autocomplete-wrap');
			$wrapperEl.toggleClass('has-value', !!$this.val());
			var $clearBtn = $('<button type="button" class="dc-autocomplete-clear" tabindex="-1"><i class="fa fa-times"></i></button>');
			$this.after($clearBtn);

			$clearBtn.on('mousedown', function(e) {
				e.preventDefault();
			});

			$clearBtn.on('click', function() {
				$this.val('').trigger('change');
				$wrapperEl.removeClass('has-value');
				el.hide();
				$this.focus();
			});

			// Focus
			$this.on('focus', function() {
				el.request();
			});

			// Blur
			$this.on('blur', function() {
				setTimeout(function() {
					el.hide();
				}, 200);
			});

			// Keydown
			$this.on('keydown', function(event) {
				switch(event.keyCode) {
					case 27: // escape
						el.hide();
						break;
					default:
						el.request();
						break;
				}
			});

			// Click
			el.click = function(event) {
				event.preventDefault();

				var value = $(event.target).parent().attr('data-value');

				if (value && el.items[value]) {
					el.select(el.items[value]);
					$wrapperEl.toggleClass('has-value', !!$this.val());
				}
			}

			// Show
			el.show = function() {
				var pos = $wrapperEl.position();

				$dropdown.css({
					top: pos.top + $wrapperEl.outerHeight(),
					left: pos.left
				});

				$dropdown.show();
			}

			// Hide
			el.hide = function() {
				$dropdown.hide();
			}

			// Request
			el.request = function() {
				clearTimeout(el.timer);

				el.timer = setTimeout(function() {
					el.source($(el).val(), $.proxy(el.response, el));
				}, 200);
			}

			// Response
			el.response = function(json) {
				var html = '';
				var category = {};
				var name;
				var i = 0, j = 0;

				if (json.length) {
					for (i = 0; i < json.length; i++) {
						el.items[json[i]['value']] = json[i];

						if (!json[i]['category']) {
							html += '<li data-value="' + json[i]['value'] + '"><a href="#">' + json[i]['label'] + '</a></li>';
						} else {
							name = json[i]['category'];
							if (!category[name]) {
								category[name] = [];
							}

							category[name].push(json[i]);
						}
					}

					for (name in category) {
						html += '<li class="dropdown-header">' + name + '</li>';

						for (j = 0; j < category[name].length; j++) {
							html += '<li data-value="' + category[name][j]['value'] + '"><a href="#">&nbsp;&nbsp;&nbsp;' + category[name][j]['label'] + '</a></li>';
						}
					}
				}

				if (html) {
					el.show();
				} else {
					el.hide();
				}

				$dropdown.html(html);
			}

			$this.on('input change', function() {
				$wrapperEl.toggleClass('has-value', !!$this.val());
			});

			$dropdown.on('click', '> li > a', $.proxy(el.click, el));
			$wrapperEl.after($dropdown);
		});
	}
})(window.jQuery);
