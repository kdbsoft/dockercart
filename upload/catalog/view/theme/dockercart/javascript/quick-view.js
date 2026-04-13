/**
 * Quick View Module — Shared functionality for product quick view across all theme modules
 * Used by: Featured products, Best Sellers, Category products, etc.
 */

(function() {
  'use strict';

  const QuickView = {
    /**
     * Initialize Quick View for a specific product grid
     * @param {Object} options Configuration options
     *   - modalId: ID of the modal element (default: 'qv-modal')
     *   - panelId: ID of the panel element (default: 'qv-panel')
     *   - prefix: Prefix for element IDs (default: empty string)
     *   - cardSelector: Selector for product cards (default: '.product-card')
     */
    init: function(options = {}) {
      this.options = {
        modalId: options.modalId || 'qv-modal',
        panelId: options.panelId || 'qv-panel',
        prefix: options.prefix ? options.prefix + '-' : '',
        cardSelector: options.cardSelector || '.product-card'
      };
    },

    /**
     * Open quick view modal for a product
     * @param {string|number} productId Product ID
     */
    open: function(productId) {
      const card = document.querySelector(`${this.options.cardSelector}[data-id="${productId}"]`);
      if (!card) return;

      const modal = this._getModal();
      const panel = this._getPanel();

      // Populate data from card
      this._populateFromCard(productId, card);

      // Show modal with animation
      modal.classList.remove('opacity-0', 'pointer-events-none');
      
      setTimeout(() => {
        panel.classList.remove('translate-y-6', 'opacity-0');
      }, 10);

      // Lucide icons
      if (window.lucide) {
        lucide.createIcons();
      }
    },

    /**
     * Close quick view modal
     */
    close: function() {
      const modal = this._getModal();
      const panel = this._getPanel();

      if (!modal || !panel) return;

      panel.classList.add('translate-y-6', 'opacity-0');
      
      setTimeout(() => {
        modal.classList.add('opacity-0', 'pointer-events-none');
      }, 280);
    },

    /**
     * Extract data from product card DOM and populate modal
     * @private
     */
    _populateFromCard: function(productId, card) {
      const imgEl = card.querySelector('img');
      const nameEl = card.querySelector('h3');
      const priceEl = card.querySelector('[class*="text-base"], [class*="text-lg"]');
      const oldPriceCardEl = card.querySelector('.line-through');
      const discountEl = card.querySelector('[class*="bg-red"]');
      
      const image = imgEl ? imgEl.src : '';
      const name = nameEl ? nameEl.innerText.trim() : '';
      const price = priceEl ? priceEl.innerText.trim() : '';
      const oldPrice = oldPriceCardEl ? oldPriceCardEl.innerText.trim() : '';
      const discountPct = discountEl ? discountEl.innerText.trim() : '';
      const rating = parseFloat(card.dataset.rating || '0');
      const description = card.dataset.description || '';
      const brand = card.dataset.brand || card.dataset.manufacturer || '';
      const model = card.dataset.model || '';
      const stock = (card.dataset.stock || '').trim();
      const isInStock = (String(card.dataset.isInStock || '').toLowerCase() === '1' || String(card.dataset.isInStock || '').toLowerCase() === 'true');
      const inWishlist = card.dataset.inWishlist === '1';

      // Set image
      const imgModal = document.getElementById('qv-img');
      if (imgModal) imgModal.src = image;

      // Set basic info
      this._setElementContent('qv-category', brand);
      // Use localized prefix if available
      const modelPrefix = (window.dcLang && window.dcLang.text_model_prefix) ? window.dcLang.text_model_prefix : 'Product Code: ';
      this._setElementContent('qv-model', model ? (modelPrefix + model) : '');
      this._setElementContent('qv-name', name);
      this._setElementContent('qv-price', price);
      
      // Set description
      const descEl = document.getElementById('qv-description');
      if (descEl) {
        descEl.textContent = description;
      }

      // Set stock status
      const stockWrapEl = document.getElementById('qv-stock-wrap');
      const stockTextEl = document.getElementById('qv-stock');
      const stockIconEl = document.getElementById('qv-stock-icon');

      if (stockWrapEl && stockTextEl) {
        if (stock) {
          stockTextEl.textContent = stock;
          stockWrapEl.classList.remove('hidden', 'text-emerald-600', 'text-red-500');
          stockWrapEl.classList.add(isInStock ? 'text-emerald-600' : 'text-red-500');

          if (stockIconEl) {
            stockIconEl.classList.toggle('hidden', !isInStock);
          }
        } else {
          stockWrapEl.classList.add('hidden');
        }
      }

      // Set localized features (dynamic list)
      const featuresList = document.getElementById('qv-features');
      if (featuresList) {
        const fallbackFeatures = [
          { icon: 'truck', title: '', text: (window.dcLang && window.dcLang.text_qv_feature_delivery) ? window.dcLang.text_qv_feature_delivery : 'Free shipping on this item', sort_order: 0 },
          { icon: 'shield-check', title: '', text: (window.dcLang && window.dcLang.text_qv_feature_warranty) ? window.dcLang.text_qv_feature_warranty : '2-year manufacturer warranty', sort_order: 1 },
          { icon: 'refresh-ccw', title: '', text: (window.dcLang && window.dcLang.text_qv_feature_returns) ? window.dcLang.text_qv_feature_returns : '30-day hassle-free returns', sort_order: 2 }
        ];

        const configured = (window.dcLang && Array.isArray(window.dcLang.quickview_features)) ? window.dcLang.quickview_features : fallbackFeatures;
        featuresList.innerHTML = this._renderFeatures(configured);
      }

      // Wire wishlist button
      const wishBtn = document.getElementById('qv-wishlist-btn');
      if (wishBtn) {
        wishBtn.dataset.inWishlist = inWishlist ? '1' : '0';
        this._applyWishlistState(wishBtn, inWishlist);
        wishBtn.onclick = (e) => {
          e.stopPropagation();
          dcAddToWishlist(productId, wishBtn);
        };
      }

      // Set rating
      const starsHtml = this._renderStars(rating);
      const starsEl = document.getElementById('qv-stars');
      if (starsEl) starsEl.innerHTML = starsHtml;
      
      const ratingEl = document.getElementById('qv-rating');
      if (ratingEl) {
        if (rating > 0) {
          ratingEl.textContent = rating.toFixed(1);
          ratingEl.style.display = '';
        } else {
          ratingEl.textContent = '';
          ratingEl.style.display = 'none';
        }
      }

      // Set old price and discount if available
      const oldPriceEl = document.getElementById('qv-old-price');
      const badgeEl = document.getElementById('qv-badge');
      
      if (oldPrice && price) {
        if (oldPriceEl) oldPriceEl.textContent = oldPrice;
        this._setElementContent('qv-price', price);
        if (badgeEl && discountPct) {
          badgeEl.textContent = discountPct;
          badgeEl.style.display = '';
        }
        if (oldPriceEl) oldPriceEl.style.display = '';
      } else {
        if (oldPriceEl) oldPriceEl.style.display = 'none';
        if (badgeEl) badgeEl.style.display = 'none';
      }

      // Wire add to cart button
      const addBtn = document.getElementById('qv-add-btn');
      if (addBtn) {
        addBtn.onclick = () => {
          if (typeof cart !== 'undefined' && cart.add) {
            cart.add(productId);
          } else if (window.cart && window.cart.add) {
            window.cart.add(productId);
          }
          this.close();
        };
        // Localize add button text if available
        try {
          addBtn.textContent = (window.dcLang && window.dcLang.button_cart) ? window.dcLang.button_cart : (window.button_cart ? window.button_cart : 'Add to Cart');
        } catch (e) {}
      }
    },

    /**
     * Get modal element — create if doesn't exist
     * @private
     */
    _getModal: function() {
      let modal = document.getElementById(this.options.modalId);
      
      if (!modal) {
        modal = document.createElement('div');
        modal.id = this.options.modalId;
        modal.className = 'fixed inset-0 z-[65] flex items-center justify-center p-4 opacity-0 pointer-events-none transition-opacity duration-300';
        
        // Overlay
        const overlay = document.createElement('div');
        overlay.className = 'absolute inset-0 bg-black/50 backdrop-blur-sm';
        overlay.onclick = () => this.close();
        modal.appendChild(overlay);
        
        document.body.appendChild(modal);
      }
      
      return modal;
    },

    /**
     * Get panel element inside modal — create if doesn't exist
     * @private
     */
    _getPanel: function() {
      let modal = this._getModal();
      let panel = modal.querySelector(`#${this.options.panelId}`);

      if (!panel) {
        // Create wrapper for panel content
        const wrapper = document.createElement('div');
        wrapper.innerHTML = this._buildPanelHTML();
        panel = wrapper.firstElementChild;
        panel.id = this.options.panelId;
        modal.appendChild(panel);
      }

      return panel;
    },

    /**
     * Build panel HTML structure (matches index.html design)
     * @private
     */
    _buildPanelHTML: function() {
      return `<div id="qv-panel" class="relative bg-white rounded-3xl shadow-2xl w-full max-w-2xl overflow-hidden transform transition-all translate-y-6 opacity-0">
  <!-- Close button -->
  <button onclick="QuickView.close()" class="absolute top-4 right-4 z-10 w-9 h-9 rounded-xl bg-gray-100 hover:bg-gray-200 flex items-center justify-center transition">
    <i data-lucide="x" class="w-4 h-4 text-gray-600"></i>
  </button>
  
  <!-- Content -->
  <div class="flex flex-col sm:flex-row max-h-[90vh]">
    <!-- Image -->
    <div class="sm:w-56 flex-shrink-0 bg-gray-50 flex items-center justify-center">
      <img id="qv-img" src="" alt="" class="w-full h-56 sm:h-full object-cover" />
    </div>
    
    <!-- Details -->
    <div class="p-6 flex flex-col gap-3 flex-1 overflow-y-auto">
      <span id="qv-category" class="text-xs text-blue-600 font-bold uppercase tracking-widest"></span>
      <h2 id="qv-name" class="text-xl font-extrabold text-gray-900 leading-snug"></h2>
      <p id="qv-model" class="text-xs text-gray-500 -mt-1"></p>
      
      <!-- Stars & Rating -->
      <div class="flex items-center gap-2">
        <div id="qv-stars" class="flex text-base"></div>
        <span id="qv-rating" class="text-sm text-gray-500"></span>
      </div>
      
      <!-- Price -->
      <div class="flex items-baseline gap-2">
        <span id="qv-price" class="text-2xl font-extrabold text-gray-900"></span>
        <span id="qv-old-price" class="text-base text-red-400 line-through" style="display:none;"></span>
        <span id="qv-badge" class="text-xs font-bold bg-red-500 text-white px-2 py-0.5 rounded-lg" style="display:none;"></span>
      </div>

      <!-- Stock status -->
      <div id="qv-stock-wrap" class="hidden flex items-center gap-1.5 text-sm font-semibold">
        <i id="qv-stock-icon" data-lucide="check" class="w-4 h-4"></i>
        <span id="qv-stock"></span>
      </div>
      
      <!-- Description -->
      <p id="qv-description" class="text-gray-500 text-sm leading-relaxed"></p>
      
      <!-- Features (icons on the left, text injected by JS) -->
      <ul id="qv-features" class="text-sm text-gray-600 space-y-1.5"></ul>
      
      <!-- Actions -->
      <div class="flex gap-3 mt-4">
        <button id="qv-add-btn" class="flex-1 py-3 bg-blue-600 hover:bg-blue-700 text-white font-bold rounded-xl transition text-sm shadow-md shadow-blue-200">Add to Cart</button>
        <button id="qv-wishlist-btn" class="w-11 h-11 rounded-xl border border-gray-200 hover:border-rose-300 hover:bg-rose-50 flex items-center justify-center transition flex-shrink-0">
          <i data-lucide="heart" class="w-5 h-5 text-gray-400"></i>
        </button>
      </div>
    </div>
  </div>
</div>`;
    },

    /**
     * Set element content (text or attribute)
     * @private
     */
    _setElementContent: function(elementId, content, attrName = null) {
      const el = document.getElementById(elementId);
      if (!el) return;

      if (attrName) {
        el.setAttribute(attrName, content);
      } else {
        el.textContent = content;
      }
    },

    /**
     * Render star rating HTML
     * @private
     */
    _renderStars: function(rating = 0) {
      let html = '';
      const full = Math.floor(rating);

      for (let i = 0; i < 5; i++) {
        if (i < full) {
          html += '<span class="text-amber-400">★</span>';
        } else {
          html += '<span class="text-gray-300">★</span>';
        }
      }
      return html;
    },

    _renderFeatures: function(features) {
      if (!Array.isArray(features)) {
        return '';
      }

      const sorted = features.slice().sort(function(a, b) {
        return (parseInt(a && a.sort_order, 10) || 0) - (parseInt(b && b.sort_order, 10) || 0);
      });

      const rows = sorted.map((feature) => {
        if (!feature || typeof feature !== 'object') {
          return '';
        }

        const icon = this._safeIconName(feature.icon || 'check');
        const title = (feature.title || '').toString().trim();
        const text = (feature.text || '').toString().trim();
        const content = (title && text) ? (title + ': ' + text) : (text || title);

        if (!content) {
          return '';
        }

        return `<li class="flex items-center gap-3"><i data-lucide="${icon}" class="w-4 h-4 text-teal-500 flex-shrink-0"></i><span class="qv-feature-text">${this._escapeHtml(content)}</span></li>`;
      });

      return rows.join('');
    },

    _safeIconName: function(iconName) {
      const normalized = String(iconName || '').toLowerCase();
      return /^[a-z0-9\-]+$/.test(normalized) ? normalized : 'check';
    },

    _escapeHtml: function(value) {
      return String(value)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
    }
  };

  // Apply wishlist active state to a button element
  QuickView._applyWishlistState = function(btn, active) {
    const icon = btn.querySelector('[data-lucide="heart"]');
    if (active) {
      btn.classList.add('bg-rose-50', 'border-rose-200');
      btn.classList.remove('border-gray-200');
      if (icon) { icon.classList.add('text-rose-500', 'fill-rose-400'); icon.classList.remove('text-gray-400', 'text-gray-500'); }
    } else {
      btn.classList.remove('bg-rose-50', 'border-rose-200');
      btn.classList.add('border-gray-200');
      if (icon) { icon.classList.remove('text-rose-500', 'fill-rose-400'); icon.classList.add('text-gray-400'); }
    }
  };

  // Global function for onclick handlers
  window.openQuickView = function(productId) {
    QuickView.open(productId);
  };

  window.closeQuickView = function() {
    QuickView.close();
  };

  // Global helper: toggle wishlist state for a product
  window.dcAddToWishlist = function(productId, clickedBtn) {
    // Determine current state from the clicked button or any matching card
    const card = document.querySelector(`.product-card[data-id="${productId}"]`);
    const isInWishlist = clickedBtn
      ? (clickedBtn.dataset.inWishlist === '1' || clickedBtn.closest('.product-card') && clickedBtn.closest('.product-card').dataset.inWishlist === '1')
      : (card && card.dataset.inWishlist === '1');

    if (isInWishlist) {
      // Remove from wishlist
      fetch('index.php?route=account/wishlist/remove', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: 'product_id=' + encodeURIComponent(productId)
      }).then(function() {
        // Update all matching card buttons
        document.querySelectorAll(`.product-card[data-id="${productId}"]`).forEach(function(c) {
          c.dataset.inWishlist = '0';
          const btn = c.querySelector('.wishlist-btn');
          if (btn) { btn.dataset.inWishlist = '0'; QuickView._applyWishlistState(btn, false); }
        });
        if (clickedBtn) { clickedBtn.dataset.inWishlist = '0'; QuickView._applyWishlistState(clickedBtn, false); }
        const qvBtn = document.getElementById('qv-wishlist-btn');
        if (qvBtn) { qvBtn.dataset.inWishlist = '0'; QuickView._applyWishlistState(qvBtn, false); }
      });
    } else {
      // Add to wishlist
      if (typeof wishlist !== 'undefined' && wishlist.add) {
        wishlist.add(productId);
      } else if (window.wishlist && window.wishlist.add) {
        window.wishlist.add(productId);
      }
      // Update all matching card buttons
      document.querySelectorAll(`.product-card[data-id="${productId}"]`).forEach(function(c) {
        c.dataset.inWishlist = '1';
        const btn = c.querySelector('.wishlist-btn');
        if (btn) { btn.dataset.inWishlist = '1'; QuickView._applyWishlistState(btn, true); }
      });
      if (clickedBtn) { clickedBtn.dataset.inWishlist = '1'; QuickView._applyWishlistState(clickedBtn, true); }
      const qvBtn = document.getElementById('qv-wishlist-btn');
      if (qvBtn) { qvBtn.dataset.inWishlist = '1'; QuickView._applyWishlistState(qvBtn, true); }
    }
  };

  // Expose QuickView globally
  window.QuickView = QuickView;

  // Auto-init when window loads
  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
      QuickView.init();
    });
  } else {
    QuickView.init();
  }

  // Close on Escape key
  document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
      QuickView.close();
    }
  });

})();
