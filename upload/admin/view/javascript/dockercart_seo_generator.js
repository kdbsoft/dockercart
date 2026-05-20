/**
 * DockerCart SEO Generator JavaScript (ES6+)
 * - Class-based structure
 * - Uses async/await for fetch calls
 * - Keeps jQuery event bindings for compatibility with OpenCart admin
 */

const DockercartSeoGenerator = (function () {
    const instance = {
        config: { user_token: '', languages: [] }
    };

    // Store instance reference for global access
    const self = instance;

    // Simple helpers
    function q(selector, root = document) { return root.querySelector(selector); }
    function qAll(selector, root = document) { return Array.from(root.querySelectorAll(selector)); }
    function byId(id) { return document.getElementById(id); }
    function show(el) { if (!el) return; el.style.display = ''; }
    function hide(el) { if (!el) return; el.style.display = 'none'; }

    // Event delegation helper
    function delegate(event, selector, handler) {
        document.addEventListener(event, function (e) {
            const target = e.target.closest(selector);
            if (target) handler.call(instance, e, target);
        });
    }

    // Helper to read preview values with multiple fallback keys
    function previewVal(obj, ...keys) {
        for (let k of keys) {
            if (obj == null) continue;
            if (Object.prototype.hasOwnProperty.call(obj, k) && obj[k] != null) return obj[k];
            // also try camelCase variant
            const camel = k.replace(/_([a-z])/g, (m, p1) => p1.toUpperCase());
            if (Object.prototype.hasOwnProperty.call(obj, camel) && obj[camel] != null) return obj[camel];
        }
        return '';
    }

    instance.init = function (config = {}) {
        Object.assign(this.config, config);
        
        // Convert languages object to array if needed
        if (this.config.languages && typeof this.config.languages === 'object') {
            if (!Array.isArray(this.config.languages)) {
                // It's an object, convert to array
                const langArray = [];
                for (let key in this.config.languages) {
                    if (this.config.languages.hasOwnProperty(key)) {
                        langArray.push(this.config.languages[key]);
                    }
                }
                this.config.languages = langArray;
                console.log('Converted languages object to array:', this.config.languages);
            }
        }
        
        console.log('Initialized config:', this.config);
        this.bindEvents();

        // Auto-verify license on load if license key is present
        setTimeout(() => {
            const licenseInput = byId('input-license-key');
            if (licenseInput && licenseInput.value.trim()) {
                try { this.verifyLicense(); } catch (e) { console.error(e); }
            }
        }, 500);
    };
    instance.bindEvents = function () {
        // Delegated clicks for preview / generate buttons

        // Fallback resolver in case data attributes are missing
        function resolveFromElement(el, entityType, languageId) {
            let et = entityType || (el.dataset ? el.dataset.entity : null);
            let lid = languageId || (el.dataset ? el.dataset.language : null);

            if (!et) {
                const container = el.closest('.entity-generator');
                if (container && container.dataset && container.dataset.entityType) et = container.dataset.entityType;
            }

            if (!lid) {
                const container = el.closest('.entity-generator');
                if (container) {
                    // try to find active tab id inside container
                    const activeTab = container.querySelector('.tab-pane.active');
                    if (activeTab && activeTab.id) {
                        const parts = activeTab.id.split('-');
                        lid = parts[parts.length - 1];
                    }
                }
            }

            // Always return languageId as numeric for consistency with server
            return { entityType: et || 'product', languageId: parseInt(lid || '1', 10) };
        }

        delegate('click', '.btn-preview', (e, el) => { e.preventDefault(); const res = resolveFromElement(el, el.dataset ? el.dataset.entity : null, el.dataset ? el.dataset.language : null); this.showPreview(res.entityType, res.languageId); });
        delegate('click', '.btn-generate-url', (e, el) => { e.preventDefault(); const res = resolveFromElement(el, el.dataset ? el.dataset.entity : null, el.dataset ? el.dataset.language : null); this.startGeneration(res.entityType, res.languageId, 'url'); });
        delegate('click', '.btn-generate-meta', (e, el) => { e.preventDefault(); const res = resolveFromElement(el, el.dataset ? el.dataset.entity : null, el.dataset ? el.dataset.language : null); this.startGeneration(res.entityType, res.languageId, 'meta'); });
        delegate('click', '.btn-generate-all', (e, el) => { e.preventDefault(); const res = resolveFromElement(el, el.dataset ? el.dataset.entity : null, el.dataset ? el.dataset.language : null); this.startGeneration(res.entityType, res.languageId, 'all'); });

        // Verify license (direct binding to the button id)
        const verifyBtn = byId('button-verify-license');
        if (verifyBtn) verifyBtn.addEventListener('click', (e) => { e.preventDefault(); this.verifyLicense(); });
    };

    instance.showPreview = async function (entityType, languageId) {
        // Ensure languageId is numeric for consistency
        languageId = parseInt(languageId || '1', 10);
        const templates = this.getTemplates(entityType, languageId);
        const previewEl = byId(`${entityType}-preview-${languageId}`);
        if (previewEl) previewEl.innerHTML = '<div class="text-center"><i class="fa fa-spinner fa-spin fa-3x"></i></div>', show(previewEl);

        try {
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/preview&user_token=${this.config.user_token}`, {
                method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ entity_type: entityType, language_id: languageId, templates })
            });
            const json = await resp.json();
            if (json.error) { alert(json.error); if (previewEl) hide(previewEl); return; }
            this.displayPreview(entityType, languageId, json.previews || []);
        } catch (err) { alert('Error: ' + (err.message || err)); if (previewEl) hide(previewEl); console.error(err); }
    };

    instance.displayPreview = function (entityType, languageId, previews = []) {
        // Ensure languageId is numeric for consistency
        languageId = parseInt(languageId || '1', 10);
        let html = '<h4>Preview (examples)</h4>' + '<div class="table-responsive"><table class="table table-bordered table-hover"><thead><tr>' +
            '<th>ID</th><th>Name</th><th>SEO URL</th><th>Meta Title</th><th>Meta Description</th><th>Meta Keywords</th>' +
            '</tr></thead><tbody>';

        if (previews.length) {
            previews.forEach(p => {
                const name = previewVal(p, 'name');
                const seo = previewVal(p, 'seo_url', 'seoUrl');
                const metaTitle = previewVal(p, 'meta_title', 'metaTitle', 'title');
                const metaDesc = previewVal(p, 'meta_description', 'metaDescription', 'description');
                const metaKeyword = previewVal(p, 'meta_keyword', 'meta_keywords', 'metaKeyword', 'keywords');

                html += `<tr><td>${previewVal(p, 'id')}</td>` +
                    `<td>${this.escapeHtml(name)}</td>` +
                    `<td><code>${this.escapeHtml(seo)}</code></td>` +
                    `<td>${this.escapeHtml(metaTitle)}</td>` +
                    `<td>${this.truncate(this.escapeHtml(metaDesc), 100)}</td>` +
                    `<td>${this.truncate(this.escapeHtml(metaKeyword), 50)}</td></tr>`;
            });
        } else {
            html += '<tr><td colspan="6" class="text-center">No data for preview</td></tr>';
        }

        html += '</tbody></table></div>';
        const previewEl = byId(`${entityType}-preview-${languageId}`);
        if (previewEl) { previewEl.innerHTML = html; show(previewEl); }
    };

    instance.startGeneration = async function (entityType, languageId, generateType) {
        // Ensure languageId is numeric for consistency
        languageId = parseInt(languageId || '1', 10);
        const overwriteUrlEl = q(`#${entityType}-lang-${languageId} .overwrite-url`);
        const overwriteMetaEl = q(`#${entityType}-lang-${languageId} .overwrite-meta`);
        const overwriteUrl = overwriteUrlEl ? overwriteUrlEl.checked : false;
        const overwriteMeta = overwriteMetaEl ? overwriteMetaEl.checked : false;

        let filterEmptyUrl = !overwriteUrl;
        let filterEmptyMeta = !overwriteMeta;

        // Keep total counting and generation filters consistent
        if (generateType === 'url') {
            filterEmptyMeta = false;
        } else if (generateType === 'meta') {
            filterEmptyUrl = false;
        }

        const templates = this.getTemplates(entityType, languageId);
        this.showProgress(entityType, languageId);

        try {
            const params = new URLSearchParams({ entity_type: entityType, language_id: languageId, generate_type: generateType, filter_empty_url: filterEmptyUrl ? 1 : 0, filter_empty_meta: filterEmptyMeta ? 1 : 0, overwrite_url: overwriteUrl ? 1 : 0, overwrite_meta: overwriteMeta ? 1 : 0 });
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/getTotal&user_token=${this.config.user_token}&${params.toString()}`, { method: 'GET' });
            const json = await resp.json();
            const total = json.total || 0;
            if (total === 0) { alert('No records to process with selected filters!'); this.hideProgress(entityType, languageId); return; }
            const totalEl = q(`#${entityType}-progress-${languageId} .total-count`);
            if (totalEl) totalEl.textContent = total;
            await this.processGeneration(entityType, languageId, generateType, templates, filterEmptyUrl, filterEmptyMeta, overwriteUrl, overwriteMeta, 0, total);
        } catch (err) { alert('Error: ' + (err.message || err)); this.hideProgress(entityType, languageId); console.error(err); }
    };

    instance.processGeneration = async function (entityType, languageId, generateType, templates, filterEmptyUrl, filterEmptyMeta, overwriteUrl, overwriteMeta, offset, total) {
        try {
            const body = { entity_type: entityType, language_id: languageId, generate_type: generateType, templates, filter_empty_url: filterEmptyUrl ? 1 : 0, filter_empty_meta: filterEmptyMeta ? 1 : 0, overwrite_url: overwriteUrl ? 1 : 0, overwrite_meta: overwriteMeta ? 1 : 0, offset };
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/generate&user_token=${this.config.user_token}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(body) });
            const json = await resp.json();
            if (json.error) { alert(json.error); this.hideProgress(entityType, languageId); return; }
            const processed = json.offset || 0;
            const percentage = Math.round((processed / total) * 100);
            this.updateProgress(entityType, languageId, processed, total, percentage);
            if (json.has_more) { await this.processGeneration(entityType, languageId, generateType, templates, filterEmptyUrl, filterEmptyMeta, overwriteUrl, overwriteMeta, json.offset, total); } else { this.completeGeneration(entityType, languageId, processed, generateType); }
        } catch (err) { alert('Error: ' + (err.message || err)); this.hideProgress(entityType, languageId); console.error(err); }
    };

    instance.completeGeneration = function (entityType, languageId, processed, generateType) {
        this.hideProgress(entityType, languageId);
        let message = 'Generation completed successfully!\n';
        message += 'Processed records: ' + processed + '\n';
        if (generateType === 'url') message += 'Type: SEO URL'; else if (generateType === 'meta') message += 'Type: Meta Tags'; else message += 'Type: SEO URL and Meta Tags';
        const resultEl = byId(`${entityType}-result-${languageId}`);
        if (resultEl) { resultEl.innerHTML = `<div class="alert alert-success"><i class="fa fa-check-circle"></i> ${message}</div>`; show(resultEl); }
    };

    instance.showProgress = function (entityType, languageId) {
        const previewEl = byId(`${entityType}-preview-${languageId}`);
        const resultEl = byId(`${entityType}-result-${languageId}`);
        const progressEl = byId(`${entityType}-progress-${languageId}`);
        if (previewEl) hide(previewEl);
        if (resultEl) hide(resultEl);
        if (progressEl) show(progressEl);
        const bar = q(`#${entityType}-progress-${languageId} .progress-bar`);
        const text = q(`#${entityType}-progress-${languageId} .progress-text`);
        const processedEl = q(`#${entityType}-progress-${languageId} .processed-count`);
        const totalEl = q(`#${entityType}-progress-${languageId} .total-count`);
        if (bar) bar.style.width = '0%'; if (text) text.textContent = '0%'; if (processedEl) processedEl.textContent = '0'; if (totalEl) totalEl.textContent = '0';
    };

    instance.updateProgress = function (entityType, languageId, processed, total, percentage) {
        const bar = q(`#${entityType}-progress-${languageId} .progress-bar`);
        const text = q(`#${entityType}-progress-${languageId} .progress-text`);
        const processedEl = q(`#${entityType}-progress-${languageId} .processed-count`);
        const totalEl = q(`#${entityType}-progress-${languageId} .total-count`);
        if (bar) bar.style.width = percentage + '%';
        if (text) text.textContent = percentage + '%';
        if (processedEl) processedEl.textContent = processed;
        if (totalEl) totalEl.textContent = total;
    };

    instance.hideProgress = function (entityType, languageId) { const el = byId(`${entityType}-progress-${languageId}`); if (el) hide(el); };

    instance.getTemplates = function (entityType, languageId) {
        const templates = {};
        const fields = ['seo_url', 'meta_title', 'meta_description', 'meta_keyword'];
        fields.forEach(f => { const fieldName = `module_dockercart_seo_generator_${entityType}_${f}_${languageId}`; const input = q(`[name="${fieldName}"]`); templates[f] = input ? input.value : ''; });
        return templates;
    };

    instance.escapeHtml = function (text) { if (!text) return ''; const map = { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;' }; return String(text).replace(/[&<>"']/g, m => map[m]); };

    instance.truncate = function (text, length) { if (!text) return ''; return text.length > length ? text.substring(0, length) + '...' : text; };

    instance.verifyLicense = async function () {
        const licenseInput = byId('input-license-key');
        const publicKeyInput = byId('input-public-key');
        const btnVerify = byId('button-verify-license');
        const licenseStatus = byId('license-status');
        const licenseInfo = byId('license-info');
        const licenseDetails = byId('license-details');
        if (!licenseInput || !btnVerify || !licenseStatus) return;
        const licenseKey = licenseInput.value.trim();
        const publicKey = publicKeyInput ? publicKeyInput.value.trim() : '';
        if (!licenseKey) { licenseStatus.innerHTML = '<span class="label label-danger">Please enter license key</span>'; return; }
        if (!publicKey) { licenseStatus.innerHTML = '<span class="label label-danger">Please enter public key</span>'; return; }
        btnVerify.disabled = true; const originalText = btnVerify.innerHTML; btnVerify.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Verifying...'; licenseStatus.innerHTML = ''; if (licenseInfo) licenseInfo.style.display = 'none';
        try {
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/verifyLicenseAjax&user_token=${this.config.user_token}`, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify({ license_key: licenseKey, public_key: publicKey }) });
            const data = await resp.json();
            btnVerify.disabled = false; btnVerify.innerHTML = originalText;
            if (data.valid) {
                licenseStatus.innerHTML = '<span class="label label-success"><i class="fa fa-check"></i> Valid</span>';
                if (licenseDetails) { let infoHtml = '<strong>Status:</strong> Active<br>'; infoHtml += `<strong>Domain:</strong> ${data.domain || window.location.hostname}<br>`; infoHtml += data.expires_formatted ? `<strong>Expires:</strong> ${data.expires_formatted}<br>` : '<strong>Type:</strong> Lifetime License<br>'; if (data.license_id) infoHtml += `<strong>License ID:</strong> ${data.license_id}`; licenseDetails.innerHTML = infoHtml; if (licenseInfo) licenseInfo.style.display = 'block'; }
            } else { licenseStatus.innerHTML = '<span class="label label-danger"><i class="fa fa-times"></i> Invalid</span>'; if (data.error && licenseDetails) { licenseDetails.innerHTML = `<strong>Error:</strong> ${data.error}`; if (licenseInfo) licenseInfo.style.display = 'block'; } }
        } catch (err) { btnVerify.disabled = false; btnVerify.innerHTML = originalText; licenseStatus.innerHTML = `<span class="label label-danger">Error: ${err.message || err}</span>`; console.error(err); }
    };

    
    // expose instance
    return instance;
})();

// Keep backward compatibility global name
window.DockercartSeoGenerator = DockercartSeoGenerator;

