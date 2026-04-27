/**
 * Cart Auto-Update Script
 * Automatically updates the cart when quantity changes or products are removed
 */

document.addEventListener('DOMContentLoaded', function() {
	// Auto-update quantity when input changes
	document.querySelectorAll('input[name^="quantity["]').forEach(function(input) {
		input.addEventListener('change', function() {
			const cartId = this.name.match(/\d+/)[0];
			const minimum = parseQuantity(this.dataset.minimum || this.min || '1', 1);
			const step = parseQuantity(this.dataset.step || this.step || '1', 1);
			const quantity = normalizeByStep(this.value, minimum, step);

			this.value = formatQuantity(quantity);
			
			if (quantity > 0) {
				updateCartItem(cartId, quantity);
			} else {
				// If quantity is 0 or invalid, trigger remove instead
				removeCartItem(cartId);
			}
		});

		// Also trigger update on blur for better UX
		input.addEventListener('blur', function() {
			const minimum = parseQuantity(this.dataset.minimum || this.min || '1', 1);
			const step = parseQuantity(this.dataset.step || this.step || '1', 1);
			const quantity = normalizeByStep(this.value, minimum, step);

			this.value = formatQuantity(quantity);
			this.dispatchEvent(new Event('change'));
		});
	});

	// Remove product buttons
	document.querySelectorAll('[onclick*="cart.remove"]').forEach(function(btn) {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			
			// Extract cart_id from the onclick attribute
			const match = this.getAttribute('onclick').match(/cart\.remove\('([^']+)'\)/);
			if (match) {
				const cartId = match[1];
				removeCartItem(cartId);
			}
			return false;
		});
	});

	// Remove voucher buttons
	document.querySelectorAll('[onclick*="voucher.remove"]').forEach(function(btn) {
		btn.addEventListener('click', function(e) {
			e.preventDefault();
			
			// Extract voucher key from the onclick attribute
			const match = this.getAttribute('onclick').match(/voucher\.remove\('([^']+)'\)/);
			if (match) {
				const voucherKey = match[1];
				removeVoucher(voucherKey);
			}
			return false;
		});
	});
});

function parseQuantity(value, fallback = 0) {
	const normalized = String(value).replace(',', '.').trim();
	const parsed = parseFloat(normalized);

	if (Number.isNaN(parsed)) {
		return fallback;
	}

	return Math.round(parsed * 100) / 100;
}

function formatQuantity(value) {
	const fixed = (Math.round(value * 100) / 100).toFixed(2);
	return fixed.replace(/\.00$/, '').replace(/(\.\d*[1-9])0$/, '$1');
}

function normalizeByStep(rawValue, minimum, step) {
	let qty = parseQuantity(rawValue, minimum);
	const safeStep = step > 0 ? step : 1;
	const safeMinimum = minimum > 0 ? minimum : 1;

	if (qty < safeMinimum) {
		qty = safeMinimum;
	}

	const units = Math.round((qty * 100) / Math.round(safeStep * 100));
	let snapped = units * safeStep;
	if (snapped < safeMinimum) {
		snapped = safeMinimum;
	}

	return Math.round(snapped * 100) / 100;
}

/**
 * Update cart item quantity via AJAX
 */
function updateCartItem(cartId, quantity) {
	const baseUrl = document.querySelector('base')?.getAttribute('href') || '/';
	
	fetch(baseUrl + 'index.php?route=checkout/cart/edit', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-Requested-With': 'XMLHttpRequest'
		},
		body: `key=${encodeURIComponent(cartId)}&quantity=${encodeURIComponent(formatQuantity(quantity))}`
	})
	.then(response => response.json())
	.then(json => {
		if (json.success || json.success === true) {
			// Update cart totals if they're present in the response
			if (json.totals) {
				updateCartTotals(json.totals);
				// If a grand total value is provided, also update header/compare totals
				if (json.total !== undefined) {
					updateHeaderTotals(json.total);
				}
			}
			
			// Refresh the cart header/drawer
			refreshCartHeader();
			
			// Show success notification
			showNotification('success', 'Товар обновлен');

			// If we're on the cart page (sidebar totals present), reload to ensure row totals render correctly
			if (document.querySelector('[data-cart-totals]')) {
				setTimeout(function() { location.reload(); }, 300);
			}
		} else if (json.error) {
			showNotification('error', json.error);
		}
	})
	.catch(error => {
		console.error('Error updating cart:', error);
		showNotification('error', 'Ошибка при обновлении корзины');
	});
}

/**
 * Remove item from cart via AJAX
 */
function removeCartItem(cartId) {
	const baseUrl = document.querySelector('base')?.getAttribute('href') || '/';
	
	fetch(baseUrl + 'index.php?route=checkout/cart/remove', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-Requested-With': 'XMLHttpRequest'
		},
		body: `key=${encodeURIComponent(cartId)}`
	})
	.then(response => response.json())
	.then(json => {
		if (json.success || json.success === true) {
			// Find and remove the product row from the DOM
			const productRow = document.querySelector(`[data-cart-id="${cartId}"]`);
			if (productRow) {
				// Add fade-out animation
				productRow.style.opacity = '0';
				productRow.style.transition = 'opacity 0.3s ease-out';
				setTimeout(() => {
					productRow.remove();
					
					// Check if cart is now empty
					const remainingProducts = document.querySelectorAll('[data-cart-id]');
					if (remainingProducts.length === 0) {
						// Reload page to show empty cart message
						location.reload();
					} else {
						// Update totals
						if (json.totals) {
							updateCartTotals(json.totals);
						}
						if (json.total !== undefined) {
							updateHeaderTotals(json.total);
						}
						refreshCartHeader();
						showNotification('success', 'Товар удален из корзины');
					}
				}, 300);
			}
		} else if (json.error) {
			showNotification('error', json.error);
		}
	})
	.catch(error => {
		console.error('Error removing cart item:', error);
		showNotification('error', 'Ошибка при удалении товара');
	});
}

/**
 * Remove voucher from cart via AJAX
 */
function removeVoucher(voucherKey) {
	const baseUrl = document.querySelector('base')?.getAttribute('href') || '/';
	
	fetch(baseUrl + 'index.php?route=checkout/cart/remove', {
		method: 'POST',
		headers: {
			'Content-Type': 'application/x-www-form-urlencoded',
			'X-Requested-With': 'XMLHttpRequest'
		},
		body: `key=${encodeURIComponent(voucherKey)}`
	})
	.then(response => response.json())
	.then(json => {
		if (json.success || json.success === true) {
			// Find and remove the voucher row from the DOM
			const voucherRow = document.querySelector(`[data-voucher-key="${voucherKey}"]`);
			if (voucherRow) {
				voucherRow.style.opacity = '0';
				voucherRow.style.transition = 'opacity 0.3s ease-out';
				setTimeout(() => {
					voucherRow.remove();
					
					// Update totals
					if (json.totals) {
						updateCartTotals(json.totals);
					}
					if (json.total !== undefined) {
						updateHeaderTotals(json.total);
					}
					refreshCartHeader();
					showNotification('success', 'Сертификат удален из корзины');
				}, 300);
			}
		} else if (json.error) {
			showNotification('error', json.error);
		}
	})
	.catch(error => {
		console.error('Error removing voucher:', error);
		showNotification('error', 'Ошибка при удалении сертификата');
	});
}

/**
 * Update cart totals in the sidebar
 */
function updateCartTotals(totalsData) {
	const totalsContainer = document.querySelector('[data-cart-totals]');
	if (!totalsContainer) return;
	
	const totalRows = Array.from(totalsContainer.querySelectorAll('[data-total-row]'));

	// Try to match totals by title text first (safer when extensions change order)
	totalsData.forEach((total, index) => {
		const title = (total.title || '').trim();
		let updated = false;

		for (let row of totalRows) {
			const titleEl = row.querySelector('span');
			if (!titleEl) continue;
			if (titleEl.textContent.trim() === title) {
				const totalValue = row.querySelector('[data-total-value]');
				if (totalValue) totalValue.textContent = total.text;
				updated = true;
				break;
			}
		}

		// Fallback to index-based update if no title match
		if (!updated && totalRows[index]) {
			const totalValue = totalRows[index].querySelector('[data-total-value]');
			if (totalValue) totalValue.textContent = total.text;
		}
	});
}

/**
 * Update header/cart totals (compare icon and cart button) when server returns json.total
 */
function updateHeaderTotals(totalText) {
    // Update compare total (if present)
    try {
        const compareEl = document.querySelector('#compare-total span');
        if (compareEl) compareEl.textContent = totalText;

        // Update cart total button (legacy/OpenCart id)
        const cartTotalBtn = document.querySelector('#cart > button #cart-total');
        if (cartTotalBtn) cartTotalBtn.textContent = totalText;

        // Also update any element with id 'cart-total' directly
        const cartTotalRaw = document.getElementById('cart-total');
        if (cartTotalRaw) cartTotalRaw.innerHTML = '<i class="fa fa-shopping-cart"></i> ' + totalText;
    } catch (e) {
        // silent
    }
}

/**
 * Refresh cart header/drawer
 */
function refreshCartHeader() {
	// If jQuery and the old cart.js functions exist, use them to update the header
	if (typeof $ !== 'undefined' && typeof cart !== 'undefined' && cart.add) {
		// Reload the cart header
		if ($('#cart > ul').length) {
			$('#cart > ul').load('index.php?route=common/cart/info ul li');
		}
	}
}

/**
 * Show temporary notification
 */
function showNotification(type, message) {
	// Remove any existing notification
	const existing = document.querySelector('[data-notification]');
	if (existing) {
		existing.remove();
	}
	
	// Determine styling based on type
	let bgColor = 'bg-green-50';
	let borderColor = 'border-green-200';
	let textColor = 'text-green-700';
	let icon = 'check-circle';
	
	if (type === 'error') {
		bgColor = 'bg-red-50';
		borderColor = 'border-red-200';
		textColor = 'text-red-700';
		icon = 'alert-circle';
	}
	
	// Create notification element
	const notification = document.createElement('div');
	notification.setAttribute('data-notification', '');
	notification.className = `${bgColor} border ${borderColor} ${textColor} rounded-2xl px-5 py-4 text-sm flex items-center gap-3 fixed top-4 right-4 z-50 shadow-lg max-w-md`;
	notification.innerHTML = `
		<i data-lucide="${icon}" class="w-5 h-5 flex-shrink-0"></i>
		<span>${message}</span>
		<button type="button" onclick="this.parentElement.remove()" class="ml-auto text-inherit opacity-70 hover:opacity-100">
			<i data-lucide="x" class="w-4 h-4"></i>
		</button>
	`;
	
	document.body.appendChild(notification);
	
	// If lucide icons are available, reload them
	if (typeof lucide !== 'undefined' && lucide.createIcons) {
		lucide.createIcons();
	}
	
	// Auto-remove after 4 seconds
	setTimeout(() => {
		if (notification.parentElement) {
			notification.style.opacity = '0';
			notification.style.transition = 'opacity 0.3s ease-out';
			setTimeout(() => notification.remove(), 300);
		}
	}, 4000);
}
