/**
 * DcLinkField — reusable banner/link-type field component.
 *
 * Handles custom URL vs entity autocomplete toggle, autocomplete search,
 * and hidden field sync for internal route links.
 *
 * Usage:
 *   DcLinkField.init($container, { user_token: '...', ... })
 *
 * Defaults match the category-form layout. Banner-form overrides scope.
 */
var DcLinkField = (function($) {
	'use strict';

	var defaults = {
		typeSelector:        '.dc-link-type',
		customSelector:      '.dc-link-custom',
		entitySelector:      '.dc-link-entity',
		hiddenSelector:      '.dc-link-hidden',
		customInputSelector: '.dc-link-custom-input',
		autocompleteSelector:'.dc-link-autocomplete',
		hiddenClass:         'dc-link-hidden-field',
		user_token:          null
	};

	function getUserToken(opts) {
		if (opts.user_token) return opts.user_token;
		var body = document.body;
		return (body && body.getAttribute('data-user-token')) || '';
	}

	/* ── maps ── */

	function getAutocompleteRoute(type) {
		var map = {
			'product':      'catalog/product/autocomplete',
			'category':     'catalog/category/autocomplete',
			'manufacturer': 'catalog/manufacturer/autocomplete',
			'information':  'catalog/information/autocomplete',
			'blog':         'extension/module/dockercart_blog_post/autocomplete'
		};
		return map[type] || '';
	}

	function getAutocompleteIdKey(type) {
		var map = {
			'product':      'product_id',
			'category':     'category_id',
			'manufacturer': 'manufacturer_id',
			'information':  'information_id',
			'blog':         'blog_post_id'
		};
		return map[type] || '';
	}

	function getEntityRoute(type) {
		var map = {
			'product':      'route=product/product&product_id=',
			'category':     'route=product/category&path=',
			'manufacturer': 'route=product/manufacturer/info&manufacturer_id=',
			'information':  'route=information/information&information_id=',
			'blog':         'route=blog/post&blog_post_id='
		};
		return map[type] || '';
	}

	/* ── scope helpers ── */

	function findScope($el) {
		var $scope = $el.data('dc-link-scope');
		if ($scope) return $scope;
		var $select = $el.closest('.dc-link-row').find('.dc-link-type');
		return $select.length ? $select.data('dc-link-scope') : $();
	}

	function showHide($el, visible, opts) {
		if (opts.hiddenClass) {
			$el.toggleClass(opts.hiddenClass, !visible);
		} else {
			$el.toggle(visible);
		}
	}

	/* ── toggle ── */

	function toggleType($select, isInit, opts) {
		var $scope = findScope($select);
		var val = $select.val();

		showHide($scope.find(opts.customSelector), val === 'custom', opts);
		showHide($scope.find(opts.entitySelector), val !== 'custom', opts);

		if (!isInit) {
			$scope.find(opts.customInputSelector).val('');
			$scope.find(opts.autocompleteSelector).val('');
			$scope.find(opts.hiddenSelector).val('');
		}
	}

	/* ── autocomplete ── */

	function initAutocomplete($input, opts) {
		$input.autocomplete({
			'source': function(request, response) {
				var $scope = findScope($input);
				var type = $scope.find(opts.typeSelector).val();
				var route = getAutocompleteRoute(type);
				if (!route || type === 'custom') {
					response([]);
					return;
				}
				$.ajax({
					url: 'index.php?route=' + route + '&user_token=' + getUserToken(opts) + '&filter_name=' + encodeURIComponent(request),
					dataType: 'json',
					success: function(json) {
						response($.map(json, function(item) {
							return {
								label: item['name'] || item['title'],
								value: item[getAutocompleteIdKey(type)]
							};
						}));
					}
				});
			},
			'select': function(item) {
				var $scope = findScope($input);
				var type = $scope.find(opts.typeSelector).val();
				$scope.find(opts.hiddenSelector).val(getEntityRoute(type) + item.value);
				$input.val(item['label']);
			}
		});
	}

	/* ── init ── */

	function init(container, opts) {
		opts = $.extend({}, defaults, opts || {});

		container.find(opts.typeSelector).data('dc-link-scope', container);

		container.find(opts.typeSelector).off('change.dcLink').on('change.dcLink', function() {
			toggleType($(this), false, opts);
		});

		container.find(opts.customInputSelector).off('input.dcLink').on('input.dcLink', function() {
			var $scope = findScope($(this));
			if ($scope.find(opts.typeSelector).val() === 'custom') {
				$scope.find(opts.hiddenSelector).val($(this).val());
			}
		});

		container.find(opts.autocompleteSelector).each(function() {
			initAutocomplete($(this), opts);
		});

		container.find(opts.typeSelector).each(function() {
			toggleType($(this), true, opts);
		});
	}

	/* ── public API ── */

	return {
		init: init,
		getAutocompleteRoute: getAutocompleteRoute,
		getAutocompleteIdKey: getAutocompleteIdKey,
		getEntityRoute: getEntityRoute
	};

})(window.jQuery);
