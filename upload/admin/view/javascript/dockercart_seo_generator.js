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

        const filterEmptyUrl = !overwriteUrl;
        const filterEmptyMeta = !overwriteMeta;
        const templates = this.getTemplates(entityType, languageId);
        this.showProgress(entityType, languageId);

        try {
            const params = new URLSearchParams({ entity_type: entityType, language_id: languageId, filter_empty_url: filterEmptyUrl ? 1 : 0, filter_empty_meta: filterEmptyMeta ? 1 : 0, overwrite_url: overwriteUrl ? 1 : 0, overwrite_meta: overwriteMeta ? 1 : 0 });
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

    /**
     * Controllers functionality
     */
    instance.scanControllers = async function () {
        console.log('scanControllers() called');
        console.log('User token:', this.config.user_token);
        console.log('Config languages:', this.config.languages);
        
        const btn = byId('btn-scan-controllers');
        if (!btn) {
            console.error('Button #btn-scan-controllers not found!');
            return;
        }
        
        btn.disabled = true;
        const originalText = btn.innerHTML;
        btn.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Scanning...';
        
        try {
            const url = `index.php?route=extension/module/dockercart_seo_generator/scanControllers&user_token=${this.config.user_token}`;
            console.log('Fetching:', url);
            
            const resp = await fetch(url, {
                method: 'GET'
            });
            
            console.log('Response status:', resp.status);
            
            const data = await resp.json();
            console.log('Response data:', data);
            
            btn.disabled = false;
            btn.innerHTML = originalText;
            
            if (data.success && data.controllers && data.controllers.length > 0) {
                console.log('Found controllers:', data.controllers.length);
                console.log('First controller:', data.controllers[0]);
                console.log('this.config:', this.config);
                console.log('About to call displayFoundControllers');
                this.displayFoundControllers(data.controllers);
                console.log('displayFoundControllers completed');
            } else {
                const msg = data.error || 'No controllers found without SEO URLs';
                console.log('No controllers or error:', msg);
                alert(msg);
            }
        } catch (err) {
            console.error('Error in scanControllers:', err);
            btn.disabled = false;
            btn.innerHTML = originalText;
            alert('Error scanning controllers: ' + (err.message || err));
        }
    };
    
    instance.displayFoundControllers = function (controllers) {
        console.log('=== DISPLAY FOUND CONTROLLERS CALLED ===');
        console.log('Received controllers:', controllers);
        console.log('Number of controllers:', controllers ? controllers.length : 'undefined');
        
        const listEl = document.getElementById('found-controllers-list');
        const tbody = document.getElementById('controllers-tbody');
        
        console.log('List element found:', !!listEl);
        console.log('Tbody element found:', !!tbody);
        
        if (!listEl || !tbody) {
            console.error('ERROR: List or tbody element not found!');
            console.error('listEl:', listEl);
            console.error('tbody:', tbody);
            return;
        }
        
        console.log('About to clear tbody');
        tbody.innerHTML = '';
        
        if (!controllers || controllers.length === 0) {
            console.warn('No controllers to display');
            tbody.innerHTML = '<tr><td colspan="10">No controllers found</td></tr>';
            return;
        }
        
        // Сортируем контроллеры: сначала с SEO URLs, потом без
        const sortedControllers = controllers.sort((a, b) => {
            const aHasSeoUrl = a.seo_urls && Object.values(a.seo_urls).some(seo => seo.keyword && seo.keyword.trim());
            const bHasSeoUrl = b.seo_urls && Object.values(b.seo_urls).some(seo => seo.keyword && seo.keyword.trim());
            
            // Если у одного есть, а у другого нет - тот с SEO URL идет вверх
            if (aHasSeoUrl && !bHasSeoUrl) return -1;
            if (!aHasSeoUrl && bHasSeoUrl) return 1;
            // Если оба имеют или оба не имеют - сортируем по названию маршрута
            return a.route.localeCompare(b.route);
        });
        
        console.log('Starting to add rows, config.languages:', this.config.languages);
        
        sortedControllers.forEach((ctrl, index) => {
            console.log(`Processing controller ${index}:`, ctrl.route);
            const row = document.createElement('tr');
            
            // Добавляем чекбокс
            const checkboxCell = document.createElement('td');
            checkboxCell.className = 'text-center';
            const checkbox = document.createElement('input');
            checkbox.type = 'checkbox';
            checkbox.className = 'controller-checkbox';
            checkbox.setAttribute('data-route', ctrl.route);
            checkbox.setAttribute('data-title', ctrl.title);
            checkboxCell.appendChild(checkbox);
            row.appendChild(checkboxCell);
            
            // Добавляем route
            const routeCell = document.createElement('td');
            routeCell.textContent = ctrl.route;
            row.appendChild(routeCell);
            
            // Добавляем title (read-only)
            const titleCell = document.createElement('td');
            titleCell.textContent = ctrl.title;
            row.appendChild(titleCell);
            
            // Добавляем SEO URLs для всех языков в порядке из config.languages
            if (this.config.languages && this.config.languages.length > 0) {
                console.log(`Adding URLs for ${this.config.languages.length} languages`);
                this.config.languages.forEach((lang, langIdx) => {
                    const langId = lang.language_id;
                    console.log(`  Language ${langIdx}: ID=${langId}, name=${lang.name}`);
                    
                    const seoData = (ctrl.seo_urls && ctrl.seo_urls[langId]) ? ctrl.seo_urls[langId] : {
                        language_id: langId,
                        language_code: lang.code,
                        language_name: lang.name,
                        keyword: ''
                    };
                    
                    const keyword = seoData.keyword || '';
                    
                    const urlCell = document.createElement('td');
                    const inputGroup = document.createElement('div');
                    inputGroup.className = 'input-group';
                    
                    // Input для URL
                    const input = document.createElement('input');
                    input.type = 'text';
                    input.className = 'form-control controller-seo-url';
                    input.value = keyword;
                    input.setAttribute('data-route', ctrl.route);
                    input.setAttribute('data-language-id', langId);
                    
                    // Leave empty fields with default/neutral styling (no red highlight)
                    if (!keyword || !keyword.trim()) {
                        // ensure no inline error styles are present
                        input.style.backgroundColor = '';
                        input.style.borderColor = '';
                    }
                    
                    // Кнопка Save
                    const btnSpan = document.createElement('span');
                    btnSpan.className = 'input-group-btn';

                    const saveBtn = document.createElement('button');
                    saveBtn.type = 'button';
                    saveBtn.className = 'btn btn-primary btn-save-url';
                    saveBtn.setAttribute('data-route', ctrl.route);
                    saveBtn.setAttribute('data-language-id', langId);
                    saveBtn.title = 'Save';
                    saveBtn.innerHTML = '<i class="fa fa-save"></i>';

                    const deleteBtn = document.createElement('button');
                    deleteBtn.type = 'button';
                    deleteBtn.className = 'btn btn-danger btn-delete-url';
                    deleteBtn.setAttribute('data-route', ctrl.route);
                    deleteBtn.setAttribute('data-language-id', langId);
                    deleteBtn.title = 'Delete';
                    deleteBtn.style.marginLeft = '4px';
                    deleteBtn.innerHTML = '<i class="fa fa-trash"></i>';

                    btnSpan.appendChild(saveBtn);
                    btnSpan.appendChild(deleteBtn);
                    inputGroup.appendChild(input);
                    inputGroup.appendChild(btnSpan);
                    urlCell.appendChild(inputGroup);
                    row.appendChild(urlCell);
                });
            } else {
                console.warn('No languages configured!');
            }
            
            // Добавляем колонку Actions с кнопкой Generate
            const actionsCell = document.createElement('td');
            const generateBtn = document.createElement('button');
            generateBtn.type = 'button';
            generateBtn.className = 'btn btn-success btn-sm btn-generate-single';
            generateBtn.setAttribute('data-route', ctrl.route);
            generateBtn.innerHTML = '<i class="fa fa-cog"></i> Generate URLs';
            actionsCell.appendChild(generateBtn);
            row.appendChild(actionsCell);
            
            tbody.appendChild(row);
        });
        
        console.log('Showing list element');
        if (listEl.style.display === 'none') {
            listEl.style.display = '';
        }
        console.log('=== DISPLAY FOUND CONTROLLERS COMPLETE ===');
    };
    

    
    // Bind controller events
    instance.bindControllerEvents = function () {
        // Use event delegation for all controller buttons
        delegate('click', '#btn-scan-controllers', (e, el) => { 
            e.preventDefault(); 
            console.log('Scan Controllers button clicked');
            this.scanControllers(); 
        });
        

        delegate('click', '.btn-save-url', (e, el) => { 
            e.preventDefault(); 
            console.log('Save URL button clicked');
            const route = el.dataset.route;
            const languageId = el.dataset.languageId;
            if (route && languageId) {
                this.saveControllerUrl(route, languageId, el);
            } else {
                console.error('Missing route or languageId!');
            }
        });
        
        delegate('click', '.btn-delete-url', (e, el) => {
            e.preventDefault();
            console.log('Delete URL button clicked');
            const route = el.dataset.route;
            const languageId = el.dataset.languageId;
            if (route && languageId) {
                this.deleteControllerUrl(route, languageId, el);
            } else {
                console.error('Missing route or languageId for delete!');
            }
        });

        delegate('click', '.btn-generate-single', (e, el) => { 
            e.preventDefault(); 
            console.log('Generate Single Controller button clicked');
            const route = el.dataset.route;
            if (route) {
                this.generateSingleController(route);
            } else {
                console.error('Missing route!');
            }
        });
        
        // Массовая генерация
        delegate('click', '#btn-generate-selected', (e, el) => {
            e.preventDefault();
            console.log('Generate Selected button clicked');
            this.generateSelectedControllers();
        });
        
        // Массовое удаление
        delegate('click', '#btn-delete-selected', (e, el) => {
            e.preventDefault();
            console.log('Delete Selected button clicked');
            this.deleteSelectedControllers();
        });
        
        // Select All
        delegate('click', '#btn-select-all', (e, el) => {
            e.preventDefault();
            qAll('.controller-checkbox').forEach(cb => cb.checked = true);
            this.updateSelectedCount();
        });
        
        // Deselect All
        delegate('click', '#btn-deselect-all', (e, el) => {
            e.preventDefault();
            qAll('.controller-checkbox').forEach(cb => cb.checked = false);
            this.updateSelectedCount();
        });
        
        // Select All checkbox в заголовке
        delegate('change', '#select-all-checkbox', (e, el) => {
            const checked = el.checked;
            qAll('.controller-checkbox').forEach(cb => cb.checked = checked);
            this.updateSelectedCount();
        });
        
        // Отслеживаем изменения чекбоксов
        delegate('change', '.controller-checkbox', (e, el) => {
            this.updateSelectedCount();
        });
    };
    
    // Обновление счетчика выбранных контроллеров
    instance.updateSelectedCount = function () {
        const selected = qAll('.controller-checkbox:checked');
        const count = selected.length;
        const countEl = q('#selected-count');
        const countDelEl = q('#selected-count-delete');
        const btnGenerate = q('#btn-generate-selected');
        const btnDelete = q('#btn-delete-selected');
        
        if (countEl) {
            countEl.textContent = count;
        }
        
        if (countDelEl) {
            countDelEl.textContent = count;
        }
        
        if (btnGenerate) {
            btnGenerate.disabled = count === 0;
        }
        
        if (btnDelete) {
            btnDelete.disabled = count === 0;
        }
        
        // Обновляем состояние Select All checkbox
        const selectAllCheckbox = q('#select-all-checkbox');
        const allCheckboxes = qAll('.controller-checkbox');
        if (selectAllCheckbox && allCheckboxes.length > 0) {
            selectAllCheckbox.checked = count === allCheckboxes.length;
            selectAllCheckbox.indeterminate = count > 0 && count < allCheckboxes.length;
        }
    };
    
    // Массовая генерация SEO URLs для выбранных контроллеров
    instance.generateSelectedControllers = async function () {
        const selectedCheckboxes = qAll('.controller-checkbox:checked');
        
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one controller');
            return;
        }
        
        const controllers = [];
        selectedCheckboxes.forEach(cb => {
            controllers.push({
                route: cb.dataset.route,
                title: cb.dataset.title
            });
        });
        
        // Получаем значение флажка "Overwrite"
        const overwriteCheckbox = q('#checkbox-overwrite-urls');
        const overwrite = overwriteCheckbox ? overwriteCheckbox.checked : false;
        
        const confirmMsg = overwrite 
            ? `Generate SEO URLs for ${controllers.length} controller(s)?\n\nExisting URLs WILL BE OVERWRITTEN.`
            : `Generate SEO URLs for ${controllers.length} controller(s)?\n\nExisting URLs will NOT be overwritten.`;
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        const btnGenerate = q('#btn-generate-selected');
        const originalHtml = btnGenerate.innerHTML;
        btnGenerate.disabled = true;
        btnGenerate.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Generating...';
        
        try {
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/generateControllers&user_token=${this.config.user_token}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    language_id: 0, // All languages
                    overwrite: overwrite,
                    controllers: controllers
                })
            });
            
            const data = await resp.json();
            
            if (data.success) {
                alert(`Success! Generated SEO URLs for ${controllers.length} controller(s).`);
                // Перезагружаем список
                this.scanControllers();
            } else {
                alert(data.error || 'Error generating SEO URLs');
            }
        } catch (err) {
            alert('Error: ' + (err.message || err));
        } finally {
            btnGenerate.disabled = false;
            btnGenerate.innerHTML = originalHtml;
        }
    };
    
    // Удаление SEO URLs для выбранных контроллеров
    instance.deleteSelectedControllers = async function () {
        const selectedCheckboxes = qAll('.controller-checkbox:checked');
        if (selectedCheckboxes.length === 0) {
            alert('Please select at least one controller');
            return;
        }
        
        const controllers = [];
        selectedCheckboxes.forEach(cb => {
            controllers.push({
                route: cb.dataset.route,
                title: cb.dataset.title
            });
        });
        
        // Confirmation dialog
        const confirmMsg = `Delete SEO URLs for ${controllers.length} controller(s)?\n\nControllers:\n${controllers.map(c => c.title).join(', ')}\n\nThis action cannot be undone.`;
        if (!confirm(confirmMsg)) {
            return;
        }
        
        const btnDelete = q('#btn-delete-selected');
        const originalHtml = btnDelete.innerHTML;
        
        try {
            btnDelete.disabled = true;
            btnDelete.innerHTML = '<i class="fa fa-spinner fa-spin"></i> Deleting...';
            
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/deleteControllers&user_token=${this.config.user_token}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                },
                body: JSON.stringify({
                    controllers: controllers
                })
            });
            
            const data = await resp.json();
            
            if (data.success) {
                alert(`Success! Deleted SEO URLs for ${controllers.length} controller(s).`);
                // Перезагружаем список
                this.scanControllers();
            } else {
                alert(data.error || 'Error deleting SEO URLs');
            }
        } catch (err) {
            alert('Error: ' + (err.message || err));
        } finally {
            btnDelete.disabled = false;
            btnDelete.innerHTML = originalHtml;
        }
    };
    
    // Сохранение SEO URL для конкретного контроллера и языка
    instance.saveControllerUrl = async function (route, languageId, buttonEl) {
        // Находим input с SEO URL
        const input = q(`.controller-seo-url[data-route="${route}"][data-language-id="${languageId}"]`);
        if (!input) {
            alert('Error: URL input not found');
            return;
        }
        
        const seoUrl = input.value.trim();
        if (!seoUrl) {
            alert('SEO URL cannot be empty');
            return;
        }
        
        // Disable button during save
        const originalHtml = buttonEl.innerHTML;
        buttonEl.disabled = true;
        buttonEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';
        
        try {
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/updateControllerUrl&user_token=${this.config.user_token}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    route: route,
                    seo_url: seoUrl,
                    language_id: parseInt(languageId, 10)
                })
            });
            
            const data = await resp.json();
            
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalHtml;
            
            if (data.success) {
                // Show success feedback
                buttonEl.classList.remove('btn-primary');
                buttonEl.classList.add('btn-success');
                setTimeout(() => {
                    buttonEl.classList.remove('btn-success');
                    buttonEl.classList.add('btn-primary');
                }, 2000);
            } else {
                alert(data.error || 'Error saving SEO URL');
            }
        } catch (err) {
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalHtml;
            alert('Error: ' + (err.message || err));
            console.error(err);
        }
    };
    
    // Удаление SEO URL для конкретного контроллера и языка
    instance.deleteControllerUrl = async function (route, languageId, buttonEl) {
        if (!confirm('Delete SEO URL for this controller/language? This action cannot be undone.')) {
            return;
        }

        const input = q(`.controller-seo-url[data-route="${route}"][data-language-id="${languageId}"]`);
        if (!input) {
            alert('Error: URL input not found');
            return;
        }

        const originalHtml = buttonEl.innerHTML;
        buttonEl.disabled = true;
        buttonEl.innerHTML = '<i class="fa fa-spinner fa-spin"></i>';

        try {
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/deleteControllerUrl&user_token=${this.config.user_token}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ route: route, language_id: parseInt(languageId, 10) })
            });

            const data = await resp.json();

            buttonEl.disabled = false;
            buttonEl.innerHTML = originalHtml;

            if (data.success) {
                // Clear input and reset inline styles to neutral
                input.value = '';
                input.style.backgroundColor = '';
                input.style.borderColor = '';

                // Visual feedback
                buttonEl.classList.remove('btn-danger');
                buttonEl.classList.add('btn-success');
                setTimeout(() => {
                    buttonEl.classList.remove('btn-success');
                    buttonEl.classList.add('btn-danger');
                }, 1500);
            } else {
                alert(data.error || 'Error deleting SEO URL');
            }
        } catch (err) {
            buttonEl.disabled = false;
            buttonEl.innerHTML = originalHtml;
            alert('Error: ' + (err.message || err));
            console.error(err);
        }
    };

    // Генерация URL для одного контроллера (всех языков)
    instance.generateSingleController = async function (route) {
        // Получаем значение флажка "Overwrite"
        const overwriteCheckbox = q('#checkbox-overwrite-urls');
        const overwrite = overwriteCheckbox ? overwriteCheckbox.checked : false;
        
        const confirmMsg = overwrite 
            ? 'Generate SEO URLs for all languages for this controller?\n\nExisting URLs WILL BE OVERWRITTEN.'
            : 'Generate SEO URLs for all languages for this controller?\n\nExisting URLs will NOT be overwritten.';
        
        if (!confirm(confirmMsg)) {
            return;
        }
        
        // Получаем title контроллера
        const titleInput = q(`.controller-title[data-route="${route}"]`);
        const title = titleInput ? titleInput.value : route;
        
        try {
            const resp = await fetch(`index.php?route=extension/module/dockercart_seo_generator/generateControllers&user_token=${this.config.user_token}`, {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    language_id: 0, // All languages
                    overwrite: overwrite,
                    controllers: [{
                        route: route,
                        title: title
                    }]
                })
            });
            
            const data = await resp.json();
            
            if (data.success) {
                let message = `Successfully generated SEO URLs!\n\nProcessed: ${data.processed}`;
                if (data.skipped > 0) {
                    message += `\nSkipped (already exist): ${data.skipped}`;
                }
                alert(message);
                // Rescan to update the table
                this.scanControllers();
            } else {
                alert(data.error || 'Error generating SEO URLs');
            }
        } catch (err) {
            alert('Error: ' + (err.message || err));
            console.error(err);
        }
    };
    
    // Update bindEvents to include controller events
    const originalBindEvents = instance.bindEvents;
    instance.bindEvents = function () {
        originalBindEvents.call(this);
        this.bindControllerEvents();
    };
    
    // expose instance
    return instance;
})();

// Keep backward compatibility global name
window.DockercartSeoGenerator = DockercartSeoGenerator;

