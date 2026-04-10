/**
 * DockerCart 1-Click Checkout Module
 * Frontend JavaScript
 * 
 * @package    DockerCart
 * @author     DockerCart Team
 * @copyright  2026 DockerCart
 * @license    Commercial
 * @version    1.0.0
 */

// Email validation function
function validateEmail(email) {
    if (!email) {
        return false;
    }
    
    // RFC 5322 simplified pattern
    var emailRegex = /^[a-zA-Z0-9.!#$%&'*+\/=?^_`{|}~-]+@[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?(?:\.[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?)*$/;
    
    if (!emailRegex.test(email)) {
        return false;
    }
    
    // Additional checks
    var parts = email.split('@');
    if (parts.length !== 2) {
        return false;
    }
    
    var local = parts[0];
    var domain = parts[1];
    
    // Local part length check (max 64 characters)
    if (local.length > 64 || local.length < 1) {
        return false;
    }
    
    // Domain length check (max 255 characters)
    if (domain.length > 255 || domain.length < 3) {
        return false;
    }
    
    // Check if domain has at least one dot
    if (domain.indexOf('.') === -1) {
        return false;
    }
    
    // Check for consecutive dots
    if (email.indexOf('..') !== -1) {
        return false;
    }
    
    return true;
}

// Telephone validation and normalization function
function validateAndNormalizeTelephone(telephone) {
    if (!telephone) {
        return { valid: false, normalized: '' };
    }
    
    // Remove all non-digit characters except plus sign at the beginning
    var cleaned = telephone.replace(/[^0-9+]/g, '');
    
    // If starts with +, keep it, otherwise remove all plus signs
    if (cleaned.indexOf('+') === 0) {
        cleaned = '+' + cleaned.substring(1).replace(/[^0-9]/g, '');
    } else {
        cleaned = cleaned.replace(/[^0-9]/g, '');
    }
    
    // Count digits only (without plus)
    var digitsOnly = cleaned.replace(/[^0-9]/g, '');
    var digitCount = digitsOnly.length;
    
    // Validate length (7-15 digits per ITU-T E.164)
    var minLength = 7;
    var maxLength = 15;
    
    if (digitCount < minLength || digitCount > maxLength) {
        return { valid: false, normalized: '' };
    }
    
    // Normalize common formats
    var normalized;
    
    // Replace leading 8 with +7 for Russian numbers (11 digits)
    if (digitsOnly.length === 11 && digitsOnly.charAt(0) === '8') {
        normalized = '+7' + digitsOnly.substring(1);
    } else if (cleaned.indexOf('+') === 0) {
        normalized = cleaned;
    } else {
        // Add + if number looks international (10+ digits)
        if (digitCount >= 10) {
            normalized = '+' + digitsOnly;
        } else {
            normalized = digitsOnly;
        }
    }
    
    return { valid: true, normalized: normalized };
}

(function() {
    var BACKDROP_ID = 'oneclickcheckout-backdrop';

    function getModal() {
        return document.getElementById('oneclickcheckout-modal');
    }

    function getForm() {
        return document.getElementById('oneclickcheckout-form');
    }

    function getErrorBox() {
        return document.getElementById('oneclickcheckout-error');
    }

    function hideElement(el) {
        if (!el) return;
        el.style.display = 'none';
    }

    function showElement(el) {
        if (!el) return;
        el.style.display = 'block';
    }

    function clearFormErrors(form) {
        if (!form) return;

        var withErrors = form.querySelectorAll('.has-error');
        for (var i = 0; i < withErrors.length; i++) {
            withErrors[i].classList.remove('has-error');
        }
    }

    function applyTheme(modal, theme) {
        if (!modal) return;

        var classes = modal.className.split(/\s+/);
        var filtered = [];

        for (var i = 0; i < classes.length; i++) {
            if (classes[i].indexOf('theme-') !== 0) {
                filtered.push(classes[i]);
            }
        }

        if (theme) {
            filtered.push(theme);
        }

        modal.className = filtered.join(' ').trim();
    }

    function clearEditableFields(form) {
        if (!form) return;

        var fields = form.querySelectorAll('input:not([readonly]):not([disabled]), textarea:not([readonly]):not([disabled]), select:not([disabled])');

        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var type = (field.type || '').toLowerCase();

            if (type === 'checkbox' || type === 'radio') {
                field.checked = false;
            } else if (field.tagName === 'SELECT') {
                field.selectedIndex = 0;
            } else {
                field.value = '';
            }
        }
    }

    function ensureBackdrop() {
        var existing = document.getElementById(BACKDROP_ID);
        if (existing) return existing;

        var backdrop = document.createElement('div');
        backdrop.id = BACKDROP_ID;
        backdrop.className = 'modal-backdrop fade in';
        document.body.appendChild(backdrop);
        return backdrop;
    }

    function removeBackdrop() {
        var backdrop = document.getElementById(BACKDROP_ID);
        if (backdrop && backdrop.parentNode) {
            backdrop.parentNode.removeChild(backdrop);
        }
    }

    function showModal() {
        var modal = getModal();
        if (!modal) return;

        modal.style.display = 'block';
        modal.classList.add('in');
        modal.setAttribute('aria-hidden', 'false');
        document.body.classList.add('modal-open');
        ensureBackdrop();
    }

    function hideModal() {
        var modal = getModal();
        if (!modal) return;

        modal.classList.remove('in');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        document.body.classList.remove('modal-open');
        removeBackdrop();
    }

    function initModalState() {
        var modal = getModal();
        if (!modal) return;

        if (modal.parentNode !== document.body) {
            document.body.appendChild(modal);
        }

        modal.classList.remove('in');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        removeBackdrop();
    }

    function loadCaptcha() {
        var modal = getModal();
        var captchaContainer = document.getElementById('oneclickcheckout-captcha');

        if (!modal || !captchaContainer) return;

        var captchaCode = modal.getAttribute('data-captcha');
        if (!captchaCode) return;

        fetch('index.php?route=extension/captcha/' + encodeURIComponent(captchaCode), {
            method: 'GET',
            credentials: 'same-origin'
        })
            .then(function(response) {
                return response.text();
            })
            .then(function(html) {
                captchaContainer.innerHTML = html;
            })
            .catch(function(error) {
                console.error('Captcha load error:', error);
            });
    }

    function showErrorMessages(messages) {
        var errorBox = getErrorBox();
        if (!errorBox) return;

        var html = '<ul>';
        for (var i = 0; i < messages.length; i++) {
            html += '<li>' + messages[i] + '</li>';
        }
        html += '</ul>';

        errorBox.innerHTML = html;
        showElement(errorBox);
    }

    function appendField(formData, key, value) {
        if (typeof value === 'undefined' || value === null) {
            value = '';
        }
        formData.append(key, value);
    }

    function collectFormData(form) {
        var data = new URLSearchParams();
        if (!form) return data;

        var fields = form.querySelectorAll('input, textarea, select');
        for (var i = 0; i < fields.length; i++) {
            var field = fields[i];
            var name = field.name;

            if (!name) continue;

            var tagName = field.tagName.toLowerCase();
            var type = (field.type || '').toLowerCase();

            if (tagName === 'select' && field.multiple) {
                for (var j = 0; j < field.options.length; j++) {
                    if (field.options[j].selected) {
                        appendField(data, name, field.options[j].value);
                    }
                }
                continue;
            }

            if (type === 'checkbox' || type === 'radio') {
                if (field.checked) {
                    appendField(data, name, field.value);
                }
                continue;
            }

            appendField(data, name, field.value);
        }

        return data;
    }

    function insertSuccessAlert(message) {
        try {
            // Fixed-position top-center alert to avoid affecting body layout
            var existing = document.getElementById('oneclickcheckout-top-alert');
            if (existing) {
                // update text and reset timeout
                var txt = existing.querySelector('.oneclick-text');
                if (txt) txt.innerHTML = message || '';
                // move to front
                existing.style.opacity = '1';
                if (existing._timeoutId) clearTimeout(existing._timeoutId);
                existing._timeoutId = setTimeout(function() {
                    try { if (existing && existing.parentNode) existing.parentNode.removeChild(existing); } catch(e){}
                }, 10000);
                return;
            }

            var alert = document.createElement('div');
            alert.id = 'oneclickcheckout-top-alert';
            alert.className = 'alert alert-success alert-dismissible dc-oneclick-success';
            alert.setAttribute('role', 'alert');
            // Tailwind-friendly utilities plus inline fixed positioning to avoid layout shift
            alert.style.position = 'fixed';
            alert.style.top = '16px';
            alert.style.left = '50%';
            alert.style.transform = 'translateX(-50%)';
            alert.style.zIndex = '1060';
            alert.style.maxWidth = '720px';
            alert.style.width = 'auto';
            alert.style.boxSizing = 'border-box';
            alert.style.background = '#f0fdf4';
            alert.style.border = '1px solid #bbf7d0';
            alert.style.color = '#065f46';
            alert.style.padding = '12px 16px';
            alert.style.borderRadius = '8px';
            alert.style.boxShadow = '0 6px 18px rgba(0,0,0,0.08)';

            var icon = document.createElement('span');
            icon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="vertical-align:middle;margin-right:8px"><circle cx="12" cy="12" r="10"></circle><path d="M9 12l2 2 4-4"></path></svg>';
            icon.style.marginRight = '8px';

            var text = document.createElement('span');
            text.className = 'oneclick-text';
            text.innerHTML = message || '';

            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.className = 'close';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.style.marginLeft = '12px';
            closeBtn.style.background = 'transparent';
            closeBtn.style.border = 'none';
            closeBtn.style.cursor = 'pointer';
            closeBtn.innerHTML = '&times;';
            closeBtn.addEventListener('click', function() {
                if (alert && alert.parentNode) alert.parentNode.removeChild(alert);
            });

            alert.appendChild(icon);
            alert.appendChild(text);
            alert.appendChild(closeBtn);

            document.body.appendChild(alert);

            // Auto-remove after 10s
            alert._timeoutId = setTimeout(function() {
                try { if (alert && alert.parentNode) alert.parentNode.removeChild(alert); } catch(e){}
            }, 10000);
        } catch (e) {
            console.error('Failed to insert success alert:', e);
        }
    }

    function showSuccessPopup(message, buttonLabel) {
        try {
            // Remove existing if present
            var existing = document.getElementById('oneclickcheckout-success-popup');
            if (existing && existing.parentNode) existing.parentNode.removeChild(existing);

            var backdrop = document.createElement('div');
            backdrop.id = 'oneclickcheckout-success-backdrop';
            // Tailwind backdrop classes (plus inline fallback styles in case Tailwind isn't available)
            backdrop.className = 'fixed inset-0 bg-black bg-opacity-50 z-50';
            backdrop.style.position = 'fixed';
            backdrop.style.top = '0';
            backdrop.style.left = '0';
            backdrop.style.right = '0';
            backdrop.style.bottom = '0';
            backdrop.style.backgroundColor = 'rgba(0,0,0,0.5)';
            backdrop.style.zIndex = '1050';

            var popup = document.createElement('div');
            popup.id = 'oneclickcheckout-success-popup';
            // Tailwind modal classes: centered, green background, white text
            // White background with green border (Tailwind utilities)
            // Tailwind modal classes plus inline fallback styles
            popup.className = 'fixed left-1/2 top-1/2 transform -translate-x-1/2 -translate-y-1/2 z-50 w-full max-w-md bg-white text-black border-2 border-green-500 rounded-lg shadow-lg p-6 text-center relative';
            popup.style.position = 'fixed';
            popup.style.left = '50%';
            popup.style.top = '50%';
            popup.style.transform = 'translate(-50%, -50%)';
            popup.style.zIndex = '1060';
            popup.style.width = '90%';
            popup.style.maxWidth = '520px';
            popup.style.background = '#ffffff';
            popup.style.color = '#000000';
            popup.style.border = '2px solid #10b981';
            popup.style.borderRadius = '8px';
            popup.style.boxShadow = '0 10px 30px rgba(0,0,0,0.12)';
            popup.style.padding = '1.25rem';
            popup.style.textAlign = 'center';
            popup.setAttribute('role', 'dialog');
            popup.setAttribute('aria-modal', 'true');

            // Large centered success icon
            var bigIcon = document.createElement('div');
            bigIcon.className = 'mx-auto mb-3';
            bigIcon.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="56" height="56" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10" fill="rgba(16,185,129,0.08)" stroke="#10b981"></circle><path d="M9 12l2 2 4-4" stroke="#10b981"></path></svg>';
            // Inline fallback to center the icon when Tailwind is not present
            bigIcon.style.display = 'flex';
            bigIcon.style.justifyContent = 'center';
            bigIcon.style.alignItems = 'center';

            var title = document.createElement('div');
            title.className = 'text-lg font-semibold mb-0';
            title.innerHTML = (message || 'Success');

            // Close (X) button top-right
            var closeBtn = document.createElement('button');
            closeBtn.type = 'button';
            closeBtn.setAttribute('aria-label', 'Close');
            closeBtn.className = 'absolute top-2 right-3 text-green-500 leading-none';
            closeBtn.style.background = 'transparent';
            closeBtn.style.border = 'none';
            closeBtn.style.cursor = 'pointer';
            closeBtn.style.padding = '0';
            closeBtn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#10b981" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="18" y1="6" x2="6" y2="18"></line><line x1="6" y1="6" x2="18" y2="18"></line></svg>';

            function removePopup() {
                try {
                    if (popup && popup.parentNode) popup.parentNode.removeChild(popup);
                    if (backdrop && backdrop.parentNode) backdrop.parentNode.removeChild(backdrop);
                    document.removeEventListener('keydown', keyHandler);
                } catch (e) {}
            }

            closeBtn.addEventListener('click', removePopup);
            backdrop.addEventListener('click', removePopup);

            // Close on ESC
            function keyHandler(e) {
                if (e.key === 'Escape') {
                    removePopup();
                }
            }

            document.addEventListener('keydown', keyHandler);

            popup.appendChild(bigIcon);
            popup.appendChild(title);
            popup.appendChild(closeBtn);

            document.body.appendChild(backdrop);
            document.body.appendChild(popup);

            return true;
        } catch (e) {
            console.error('Failed to show success popup:', e);
            return false;
        }
    }

    function formatTelephoneForDisplay(normalized) {
        var formatted = normalized;

        if (normalized.indexOf('+7') === 0 && normalized.length === 12) {
            formatted = '+7 (' + normalized.substring(2, 5) + ') ' +
                normalized.substring(5, 8) + '-' +
                normalized.substring(8, 10) + '-' +
                normalized.substring(10, 12);
        } else if (normalized.indexOf('+380') === 0 && normalized.length === 13) {
            formatted = '+380 (' + normalized.substring(4, 6) + ') ' +
                normalized.substring(6, 9) + '-' +
                normalized.substring(9, 11) + '-' +
                normalized.substring(11, 13);
        } else if (normalized.indexOf('+') === 0 && normalized.length >= 11) {
            var digits = normalized.substring(1);
            if (digits.length === 10) {
                formatted = normalized.substring(0, 2) + ' (' + digits.substring(0, 3) + ') ' +
                    digits.substring(3, 6) + '-' +
                    digits.substring(6, 8) + '-' +
                    digits.substring(8, 10);
            }
        }

        return formatted;
    }

    function handleOpenModal(button) {
        var form = getForm();
        var modal = getModal();

        if (!form || !modal) return;

        var productId = button.getAttribute('data-product-id');
        var theme = button.getAttribute('data-theme') || 'theme-purple';

        form.setAttribute('data-product-id', productId || '0');
        applyTheme(modal, theme);
        clearEditableFields(form);
        clearFormErrors(form);

        var errorBox = getErrorBox();
        if (errorBox) {
            errorBox.innerHTML = '';
            hideElement(errorBox);
        }

        loadCaptcha();
        showModal();
    }

    function handleSubmit() {
        var submitButton = document.getElementById('oneclickcheckout-submit');
        var form = getForm();
        var productId = form ? form.getAttribute('data-product-id') : '0';

        if (!submitButton || !form) return;

        var errors = [];
        clearFormErrors(form);

        var errorBox = getErrorBox();
        if (errorBox) {
            errorBox.innerHTML = '';
            hideElement(errorBox);
        }

        var emailInput = form.querySelector('input[name="email"]');
        if (emailInput && emailInput.value && !validateEmail(emailInput.value)) {
            errors.push('E-Mail address does not appear to be valid!');
            emailInput.classList.add('has-error');
        }

        var telephoneInput = form.querySelector('input[name="telephone"]');
        if (telephoneInput && telephoneInput.value) {
            var phoneValidation = validateAndNormalizeTelephone(telephoneInput.value);
            if (!phoneValidation.valid) {
                errors.push('Telephone number is invalid! Use format: +7 (XXX) XXX-XX-XX or similar.');
                telephoneInput.classList.add('has-error');
            } else {
                telephoneInput.value = phoneValidation.normalized;
            }
        }

        if (errors.length) {
            showErrorMessages(errors);
            return;
        }

        var originalLabel = submitButton.getAttribute('data-original-label') || submitButton.textContent;
        submitButton.setAttribute('data-original-label', originalLabel);
        submitButton.disabled = true;
        submitButton.innerHTML = 'Processing...';

        var payload = collectFormData(form);
        payload.set('product_id', productId || '0');

        var captchaInput = document.querySelector('input[name="captcha"]');
        if (captchaInput) {
            payload.set('captcha', captchaInput.value || '');
        }

        fetch('index.php?route=extension/module/dockercart_oneclickcheckout/submit', {
            method: 'POST',
            credentials: 'same-origin',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'X-Requested-With': 'XMLHttpRequest'
            },
            body: payload.toString()
        })
            .then(function(response) {
                return response.json();
            })
            .then(function(json) {
                submitButton.disabled = false;
                submitButton.textContent = originalLabel;

                if (json.error) {
                    var serverErrors = [];

                    if (typeof json.error === 'string') {
                        serverErrors.push(json.error);
                    } else {
                        for (var key in json.error) {
                            if (!Object.prototype.hasOwnProperty.call(json.error, key)) continue;

                            serverErrors.push(json.error[key]);

                            if (key === 'country_id') {
                                var country = document.getElementById('input-country');
                                if (country) country.classList.add('has-error');
                            } else {
                                var fields = form.querySelectorAll('input[name="' + key + '"], textarea[name="' + key + '"], select[name="' + key + '"]');
                                for (var i = 0; i < fields.length; i++) {
                                    fields[i].classList.add('has-error');
                                }
                            }
                        }
                    }

                    showErrorMessages(serverErrors);
                    loadCaptcha();
                    return;
                }

                if (json.success) {
                    // Change product one-click button(s) to disabled and localized success text
                    try {
                        var modal = getModal();
                        var successText = (modal && modal.getAttribute('data-success-text')) ? modal.getAttribute('data-success-text') : json.success;

                        // Find all one-click buttons on the page and disable them / change label
                        var oneClickButtons = document.querySelectorAll('#button-oneclickcheckout');
                        for (var i = 0; i < oneClickButtons.length; i++) {
                            try {
                                oneClickButtons[i].disabled = true;
                                // Preserve original label
                                if (!oneClickButtons[i].getAttribute('data-original-label')) {
                                    oneClickButtons[i].setAttribute('data-original-label', oneClickButtons[i].textContent);
                                }
                                oneClickButtons[i].textContent = successText;
                                oneClickButtons[i].classList.add('disabled');
                            } catch (errBtn) {
                                console.error('Failed to update one-click button:', errBtn);
                            }
                        }
                    } catch (err) {
                        console.error('Error applying success state to one-click button(s):', err);
                    }

                    hideModal();
                    // Try to show centered success popup (preferred). If popup fails, fall back to top alert.
                    try {
                        var modal = getModal();
                        var okLabel = (modal && modal.getAttribute('data-success-text')) ? modal.getAttribute('data-success-text') : 'OK';
                        var shown = showSuccessPopup(json.success, okLabel);
                        if (!shown) {
                            insertSuccessAlert(json.success);
                        }
                    } catch (e) {
                        console.error('Failed to show fallback success popup:', e);
                        insertSuccessAlert(json.success);
                    }
                }
            })
            .catch(function(error) {
                submitButton.disabled = false;
                submitButton.textContent = originalLabel;
                showErrorMessages(['Error: ' + (error && error.message ? error.message : 'Request failed')]);
                console.error(error);
            });
    }

    document.addEventListener('click', function(e) {
        var oneClickButton = e.target.closest('#button-oneclickcheckout');
        if (oneClickButton) {
            e.preventDefault();
            handleOpenModal(oneClickButton);
            return;
        }

        var submitButton = e.target.closest('#oneclickcheckout-submit');
        if (submitButton) {
            e.preventDefault();
            handleSubmit();
            return;
        }

        var dismissButton = e.target.closest('#oneclickcheckout-modal [data-dismiss="modal"]');
        if (dismissButton) {
            e.preventDefault();
            hideModal();
            return;
        }

        var alertClose = e.target.closest('.alert-dismissible .close[data-dismiss="alert"]');
        if (alertClose) {
            e.preventDefault();
            var alertBox = alertClose.closest('.alert-dismissible');
            if (alertBox && alertBox.parentNode) {
                alertBox.parentNode.removeChild(alertBox);
            }
        }
    });

    document.addEventListener('keyup', function(e) {
        if (e.key === 'Escape') {
            hideModal();
        }
    });

    document.addEventListener('click', function(e) {
        if (e.target && e.target.id === BACKDROP_ID) {
            hideModal();
        }

        var modal = getModal();
        if (modal && e.target === modal) {
            hideModal();
        }
    });

    document.addEventListener('input', function(e) {
        var field = e.target.closest('#oneclickcheckout-form input, #oneclickcheckout-form textarea, #oneclickcheckout-form select');
        if (field && !field.disabled) {
            field.classList.remove('has-error');
        }
    });

    document.addEventListener('change', function(e) {
        var field = e.target.closest('#oneclickcheckout-form input, #oneclickcheckout-form textarea, #oneclickcheckout-form select');
        if (field && !field.disabled) {
            field.classList.remove('has-error');
        }
    });

    document.addEventListener('focusout', function(e) {
        var telephoneField = e.target.closest('#oneclickcheckout-form input[name="telephone"]');
        if (telephoneField && telephoneField.value) {
            var phoneValidation = validateAndNormalizeTelephone(telephoneField.value);
            if (phoneValidation.valid) {
                telephoneField.value = formatTelephoneForDisplay(phoneValidation.normalized);
            }
        }

        var emailField = e.target.closest('#oneclickcheckout-form input[name="email"]');
        if (emailField) {
            if (emailField.value && !validateEmail(emailField.value)) {
                emailField.classList.add('has-error');
            } else {
                emailField.classList.remove('has-error');
            }
        }
    });

    document.addEventListener('keydown', function(e) {
        var input = e.target.closest('#oneclickcheckout-form input');
        if (!input) return;

        if (e.key === 'Enter') {
            e.preventDefault();
            handleSubmit();
        }
    });

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initModalState);
    } else {
        initModalState();
    }
})();
