window.DockercartGoogleTranslation = (function() {
    const state = {
        user_token: '',
        match_threshold: 90,
        force_overwrite: false,
        lastScanPayload: null,
        lastScannedTables: []
    };

    function byId(id) {
        return document.getElementById(id);
    }

    function setStatus(type, message) {
        const el = byId('gt-status');
        if (!el) return;
        el.className = 'alert alert-' + type;
        el.innerHTML = message;
    }

    function getPayload() {
        return {
            source_language_id: parseInt(byId('gt-source-language').value, 10),
            target_language_id: parseInt(byId('gt-target-language').value, 10),
            include_db: byId('gt-include-db').checked,
            include_files: byId('gt-include-files').checked,
            match_threshold: state.match_threshold,
            force_overwrite: !!(byId('input-force-overwrite') ? byId('input-force-overwrite').checked : state.force_overwrite)
        };
    }

    function getSelectedTables() {
        const checked = document.querySelectorAll('#gt-result .gt-table-select:checked');
        const tables = [];

        checked.forEach(function(el) {
            const tableName = el.getAttribute('data-table') || '';
            if (tableName) {
                tables.push(tableName);
            }
        });

        return tables;
    }

    function tableActionButtons() {
        return (
            '<div class="btn-group btn-group-sm" style="margin-bottom:8px;">' +
                '<button type="button" class="btn btn-default" id="gt-select-all-tables"><i class="fa fa-check-square-o"></i> Select all</button>' +
                '<button type="button" class="btn btn-default" id="gt-clear-all-tables"><i class="fa fa-square-o"></i> Clear</button>' +
            '</div>'
        );
    }

    function renderReport(report) {
        const target = byId('gt-result');
        if (!target) return;

        const dbRows = [];
        state.lastScannedTables = [];

        if (report.db) {
            Object.keys(report.db).forEach(function(tableName) {
                const row = report.db[tableName] || {};
                state.lastScannedTables.push(tableName);
                dbRows.push(
                    '<tr>' +
                    '<td><label style="margin:0;"><input type="checkbox" class="gt-table-select" data-table="' + escapeHtml(tableName) + '" checked="checked" /> <strong>' + escapeHtml(tableName) + '</strong></label></td>' +
                    '<td>' + (row.items || 0) + '</td>' +
                    '<td>' + (row.characters || 0) + '</td>' +
                    '<td class="text-right"><button type="button" class="btn btn-xs btn-success gt-translate-one" data-table="' + escapeHtml(tableName) + '"><i class="fa fa-language"></i> Translate</button></td>' +
                    '</tr>'
                );
            });
        }

        const files = report.files || {};

        target.innerHTML =
            '<div class="row">' +
                '<div class="col-sm-6">' +
                    '<div class="panel panel-default">' +
                        '<div class="panel-heading"><strong>Database tables (with language_id)</strong></div>' +
                        '<div class="panel-body">' +
                            (dbRows.length ? tableActionButtons() : '') +
                            '<table class="table table-bordered">' +
                                '<thead><tr><th>Table</th><th>Items</th><th>Chars</th><th style="width:130px;">Action</th></tr></thead>' +
                                '<tbody>' + (dbRows.length ? dbRows.join('') : '<tr><td colspan="4">No data</td></tr>') + '</tbody>' +
                            '</table>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
                '<div class="col-sm-6">' +
                    '<div class="panel panel-default">' +
                        '<div class="panel-heading"><strong>Language files</strong></div>' +
                        '<div class="panel-body">' +
                            '<p><strong>Files:</strong> ' + (files.total_files || 0) + '</p>' +
                            '<p><strong>Untranslated entries:</strong> ' + (files.untranslated_entries || 0) + '</p>' +
                            '<p><strong>Chars:</strong> ' + (files.characters || 0) + '</p>' +
                        '</div>' +
                    '</div>' +
                '</div>' +
            '</div>' +
            '<div class="alert alert-warning"><strong>Estimated cost:</strong> $' + (report.summary ? report.summary.estimated_cost : 0) + ' (' + (report.summary ? report.summary.characters : 0) + ' chars)</div>';

        const selectAllBtn = byId('gt-select-all-tables');
        if (selectAllBtn) {
            selectAllBtn.addEventListener('click', function() {
                document.querySelectorAll('#gt-result .gt-table-select').forEach(function(el) {
                    el.checked = true;
                });
            });
        }

        const clearAllBtn = byId('gt-clear-all-tables');
        if (clearAllBtn) {
            clearAllBtn.addEventListener('click', function() {
                document.querySelectorAll('#gt-result .gt-table-select').forEach(function(el) {
                    el.checked = false;
                });
            });
        }

        target.querySelectorAll('.gt-translate-one').forEach(function(btn) {
            btn.addEventListener('click', function() {
                const tableName = btn.getAttribute('data-table');
                if (!tableName) return;
                translateSingleTable(tableName).catch(function(err) {
                    setStatus('danger', '<i class="fa fa-exclamation-circle"></i> ' + escapeHtml(err.message || err));
                });
            });
        });
    }

    function escapeHtml(text) {
        return String(text || '').replace(/[&<>"']/g, function(m) {
            return {'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#039;'}[m];
        });
    }

    async function post(route, payload) {
        const response = await fetch('index.php?route=' + route + '&user_token=' + encodeURIComponent(state.user_token), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        return response.json();
    }

    async function scan() {
        const payload = getPayload();

        if (payload.source_language_id === payload.target_language_id) {
            setStatus('danger', '<i class="fa fa-exclamation-circle"></i> Source and target language must be different.');
            return;
        }

        setStatus('info', '<i class="fa fa-spinner fa-spin"></i> Scanning untranslated records...');

        const json = await post('extension/module/dockercart_google_translation/scan', payload);

        if (json.error) {
            setStatus('danger', '<i class="fa fa-exclamation-circle"></i> ' + escapeHtml(json.error));
            return;
        }

        state.lastScanPayload = payload;
        renderReport(json.report || {});
        setStatus('success', '<i class="fa fa-check-circle"></i> Scan completed.');
    }

    async function translate() {
        const payload = state.lastScanPayload || getPayload();

        if (payload.source_language_id === payload.target_language_id) {
            setStatus('danger', '<i class="fa fa-exclamation-circle"></i> Source and target language must be different.');
            return;
        }

        payload.translate_db = payload.include_db;
        payload.translate_files = payload.include_files;

        if (payload.translate_db) {
            payload.selected_tables = getSelectedTables();

            if (state.lastScannedTables.length > 0 && payload.selected_tables.length === 0) {
                setStatus('danger', '<i class="fa fa-exclamation-circle"></i> Select at least one database table to translate.');
                return;
            }
        }

        if (!confirm('Start translation now?')) {
            return;
        }

        setStatus('info', '<i class="fa fa-spinner fa-spin"></i> Translation in progress...');

        const json = await post('extension/module/dockercart_google_translation/translate', payload);

        if (json.error) {
            setStatus('danger', '<i class="fa fa-exclamation-circle"></i> ' + escapeHtml(json.error));
            return;
        }

        const result = json.result || {};
        setStatus('success', '<i class="fa fa-check-circle"></i> Translation completed: ' + (result.translated_items || 0) + ' items, ' + (result.translated_characters || 0) + ' chars. Updating counters...');

        await scan();

        setStatus('success', '<i class="fa fa-check-circle"></i> Translation completed and counters refreshed.');
    }

    async function translateSingleTable(tableName) {
        const payload = state.lastScanPayload || getPayload();

        if (payload.source_language_id === payload.target_language_id) {
            setStatus('danger', '<i class="fa fa-exclamation-circle"></i> Source and target language must be different.');
            return;
        }

        payload.translate_db = true;
        payload.translate_files = false;
        payload.selected_tables = [tableName];

        if (!confirm('Translate table: ' + tableName + '?')) {
            return;
        }

        setStatus('info', '<i class="fa fa-spinner fa-spin"></i> Translating table ' + escapeHtml(tableName) + '...');

        const json = await post('extension/module/dockercart_google_translation/translate', payload);

        if (json.error) {
            setStatus('danger', '<i class="fa fa-exclamation-circle"></i> ' + escapeHtml(json.error));
            return;
        }

        const result = json.result || {};
        setStatus('success', '<i class="fa fa-check-circle"></i> Table translated: ' + escapeHtml(tableName) + '. ' + (result.translated_items || 0) + ' items, ' + (result.translated_characters || 0) + ' chars. Updating counters...');

        await scan();

        setStatus('success', '<i class="fa fa-check-circle"></i> Table translated and counters refreshed: ' + escapeHtml(tableName) + '.');
    }

    return {
        init: function(config) {
            state.user_token = config.user_token || '';
            state.match_threshold = parseFloat(config.match_threshold || 90);
            state.force_overwrite = !!config.force_overwrite;

            const btnScan = byId('gt-button-scan');
            const btnTranslate = byId('gt-button-translate');

            if (btnScan) {
                btnScan.addEventListener('click', function() {
                    scan().catch(function(err) {
                        setStatus('danger', '<i class="fa fa-exclamation-circle"></i> ' + escapeHtml(err.message || err));
                    });
                });
            }

            if (btnTranslate) {
                btnTranslate.addEventListener('click', function() {
                    translate().catch(function(err) {
                        setStatus('danger', '<i class="fa fa-exclamation-circle"></i> ' + escapeHtml(err.message || err));
                    });
                });
            }
        }
    };
})();
