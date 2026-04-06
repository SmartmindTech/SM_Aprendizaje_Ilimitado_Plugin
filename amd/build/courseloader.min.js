/**
 * Course Loader from SharePoint — AJAX scan, import, and autocomplete search.
 *
 * @module     local_sm_graphics_plugin/courseloader
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    var TYPE_LABELS = {
        mbz:               'Backup Moodle (MBZ)',
        scorm:             'Paquetes SCORM',
        pdf:               'Documentos PDF',
        documents:         'Documentos plataforma',
        evaluations_aiken: 'Evaluaciones AIKEN',
        evaluations_gift:  'Evaluaciones GIFT'
    };

    var el = {};
    var urlMode = false;
    var searchTimer = null;
    var selectedCourseUrl = '';

    /**
     * Strip diacritics/accents for accent-insensitive matching.
     */
    function stripAccents(str) {
        return str.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
    }

    function cacheElements() {
        el.courseInput  = document.getElementById('smgp-cl-course-input');
        el.courseUrl    = document.getElementById('smgp-cl-course-url');
        el.dropdown    = document.getElementById('smgp-cl-course-dropdown');
        el.searchMode  = document.getElementById('smgp-cl-search-mode');
        el.urlMode     = document.getElementById('smgp-cl-url-mode');
        el.toggleUrlBtn = document.getElementById('smgp-cl-toggle-url');
        el.toggleListBtn = document.getElementById('smgp-cl-toggle-list');
        el.url         = document.getElementById('smgp-cl-url');
        el.companySearch = document.getElementById('smgp-cl-company-search');
        el.companyTable = document.getElementById('smgp-cl-company-table');
        el.selectAll   = document.getElementById('smgp-cl-select-all');
        el.scanBtn     = document.getElementById('smgp-cl-scan-btn');
        el.importBtn   = document.getElementById('smgp-cl-import-btn');
        el.importRow   = document.getElementById('smgp-cl-import-row');
        el.scanning    = document.getElementById('smgp-cl-scanning');
        el.results     = document.getElementById('smgp-cl-results');
        el.folderName  = document.getElementById('smgp-cl-folder-name');
        el.resultsBody = document.getElementById('smgp-cl-results-body');
        el.warnings    = document.getElementById('smgp-cl-warnings');
        el.warningsList = document.getElementById('smgp-cl-warnings-list');
        el.progress    = document.getElementById('smgp-cl-progress');
        el.log         = document.getElementById('smgp-cl-log');
        el.logContent  = document.getElementById('smgp-cl-log-content');
        el.success     = document.getElementById('smgp-cl-success');
        el.courseLink   = document.getElementById('smgp-cl-course-link');
        el.error       = document.getElementById('smgp-cl-error');
        el.errorMsg    = document.getElementById('smgp-cl-error-msg');
    }

    function show(e) { if (e) { e.classList.remove('d-none'); } }
    function hide(e) { if (e) { e.classList.add('d-none'); } }

    function resetUI() {
        hide(el.results); hide(el.warnings); hide(el.progress);
        hide(el.log); hide(el.success); hide(el.error); hide(el.importRow);
        if (el.resultsBody) { el.resultsBody.innerHTML = ''; }
        if (el.warningsList) { el.warningsList.innerHTML = ''; }
        if (el.logContent) { el.logContent.textContent = ''; }
    }

    function formatSize(bytes) {
        if (bytes === 0) { return '0 B'; }
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    }

    function addResultRow(label, files) {
        if (!files || files.length === 0) { return; }
        var row = document.createElement('tr');
        var names = files.map(function(f) {
            return f.name + ' (' + formatSize(f.size) + ')';
        }).join(', ');
        row.innerHTML = '<td>' + label + '</td>'
            + '<td class="text-center">' + files.length + '</td>'
            + '<td><small>' + names + '</small></td>';
        el.resultsBody.appendChild(row);
    }

    function getSelectedUrl() {
        if (urlMode) { return (el.url ? el.url.value.trim() : ''); }
        return selectedCourseUrl;
    }

    function getSelectedCategoryId() {
        var checked = document.querySelectorAll('.smgp-cl-company-cb:checked');
        return checked.length > 0 ? parseInt(checked[0].dataset.catid, 10) : 0;
    }

    function getSelectedCompanyIds() {
        var ids = [];
        document.querySelectorAll('.smgp-cl-company-cb:checked').forEach(function(cb) {
            ids.push(parseInt(cb.value, 10));
        });
        return ids;
    }

    // ── Autocomplete ──

    function searchCourses(term) {
        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_sharepoint_list_courses',
            args: {search: term || ''}
        }])[0].then(function(data) {
            renderDropdown(data.courses || []);
            return null;
        }).catch(function() {
            renderDropdown([]);
        });
    }

    function renderDropdown(courses) {
        if (!el.dropdown) { return; }
        el.dropdown.innerHTML = '';
        if (courses.length === 0) {
            el.dropdown.innerHTML = '<div class="smgp-cl-dropdown-empty">No se encontraron cursos</div>';
            el.dropdown.classList.add('open');
            return;
        }
        var query = stripAccents((el.courseInput ? el.courseInput.value : '').trim().toLowerCase());
        courses.forEach(function(c) {
            var item = document.createElement('div');
            item.className = 'smgp-cl-dropdown-item';
            // Highlight matching portion.
            if (query) {
                var nameLower = stripAccents(c.name.toLowerCase());
                var idx = nameLower.indexOf(query);
                if (idx !== -1) {
                    item.innerHTML = escapeHtml(c.name.substring(0, idx))
                        + '<strong style="color:#10b981;">' + escapeHtml(c.name.substring(idx, idx + query.length)) + '</strong>'
                        + escapeHtml(c.name.substring(idx + query.length));
                } else {
                    item.textContent = c.name;
                }
            } else {
                item.textContent = c.name;
            }
            item.dataset.url = c.web_url;
            item.addEventListener('mousedown', function(e) {
                e.preventDefault();
                selectCourse(c.name, c.web_url);
            });
            el.dropdown.appendChild(item);
        });
        el.dropdown.classList.add('open');
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str;
        return div.innerHTML;
    }

    function selectCourse(name, url) {
        selectedCourseUrl = url;
        if (el.courseInput) { el.courseInput.value = name; }
        if (el.courseUrl) { el.courseUrl.value = url; }
        closeDropdown();
    }

    function closeDropdown() {
        if (el.dropdown) { el.dropdown.classList.remove('open'); }
    }

    function setupAutocomplete() {
        if (!el.courseInput) { return; }

        el.courseInput.addEventListener('input', function() {
            selectedCourseUrl = '';
            if (el.courseUrl) { el.courseUrl.value = ''; }
            clearTimeout(searchTimer);
            searchTimer = setTimeout(function() {
                searchCourses(el.courseInput.value.trim());
            }, 150);
        });

        el.courseInput.addEventListener('focus', function() {
            if (!el.dropdown.classList.contains('open')) {
                searchCourses(el.courseInput.value.trim());
            }
        });

        el.courseInput.addEventListener('blur', function() {
            // Small delay to allow click on dropdown item.
            setTimeout(closeDropdown, 200);
        });

        // Keyboard navigation.
        el.courseInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeDropdown();
            }
            if (e.key === 'Enter') {
                e.preventDefault();
                var active = el.dropdown.querySelector('.smgp-cl-dropdown-item.active');
                if (active) {
                    selectCourse(active.textContent, active.dataset.url);
                } else {
                    var first = el.dropdown.querySelector('.smgp-cl-dropdown-item');
                    if (first) {
                        selectCourse(first.textContent, first.dataset.url);
                    }
                }
            }
            if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                var items = el.dropdown.querySelectorAll('.smgp-cl-dropdown-item');
                if (items.length === 0) { return; }
                var current = el.dropdown.querySelector('.smgp-cl-dropdown-item.active');
                var idx = -1;
                items.forEach(function(it, i) { if (it === current) { idx = i; } });
                if (current) { current.classList.remove('active'); }
                if (e.key === 'ArrowDown') { idx = (idx + 1) % items.length; }
                else { idx = idx <= 0 ? items.length - 1 : idx - 1; }
                items[idx].classList.add('active');
                items[idx].scrollIntoView({block: 'nearest'});
            }
        });
    }

    // ── Scan & Import ──

    function onScan() {
        var url = getSelectedUrl();
        if (!url) {
            if (urlMode && el.url) { el.url.focus(); }
            else if (el.courseInput) { el.courseInput.focus(); }
            return;
        }
        resetUI();
        show(el.scanning);
        el.scanBtn.disabled = true;

        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_sharepoint_scan',
            args: {folder_url: url}
        }])[0].then(function(data) {
            hide(el.scanning);
            el.scanBtn.disabled = false;
            if (!data.success) {
                show(el.error);
                el.errorMsg.textContent = data.warnings.length > 0 ? data.warnings[0] : 'Error desconocido.';
                return;
            }
            el.folderName.textContent = data.folder_name;
            addResultRow(TYPE_LABELS.mbz, data.mbz);
            addResultRow(TYPE_LABELS.scorm, data.scorm);
            addResultRow(TYPE_LABELS.pdf, data.pdf);
            addResultRow(TYPE_LABELS.documents, data.documents);
            addResultRow(TYPE_LABELS.evaluations_aiken, data.evaluations_aiken);
            addResultRow(TYPE_LABELS.evaluations_gift, data.evaluations_gift);
            show(el.results);
            if (data.warnings && data.warnings.length > 0) {
                data.warnings.forEach(function(w) {
                    var li = document.createElement('li');
                    li.textContent = w;
                    el.warningsList.appendChild(li);
                });
                show(el.warnings);
            }
            if (data.mbz && data.mbz.length > 0) { show(el.importRow); }
        }).catch(function(err) {
            hide(el.scanning);
            el.scanBtn.disabled = false;
            Notification.exception(err);
        });
    }

    function onImport() {
        var url = getSelectedUrl();
        var categoryid = getSelectedCategoryId();
        if (!url) { return; }
        if (!categoryid) {
            alert('Selecciona al menos una empresa de destino.');
            return;
        }
        hide(el.importRow);
        hide(el.results);
        show(el.progress);
        el.scanBtn.disabled = true;

        // Download MBZ from SharePoint and redirect to native restore wizard.
        var companyIds = getSelectedCompanyIds();
        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_sharepoint_prepare_restore',
            args: {folder_url: url, categoryid: categoryid, companyids: JSON.stringify(companyIds)}
        }])[0].then(function(data) {
            if (data.success) {
                // Redirect to Moodle's restore wizard step 1 (Confirm).
                window.location.href = M.cfg.wwwroot
                    + '/backup/restore.php?contextid=' + data.contextid
                    + '&stage=1&filename=' + encodeURIComponent(data.filename);
            } else {
                hide(el.progress);
                el.scanBtn.disabled = false;
                show(el.error);
                el.errorMsg.textContent = data.error || 'Error desconocido.';
            }
        }).catch(function(err) {
            hide(el.progress);
            el.scanBtn.disabled = false;
            Notification.exception(err);
        });
    }

    // ── Company table ──

    function setupCompanyTable() {
        if (el.companySearch) {
            el.companySearch.addEventListener('input', function() {
                var q = el.companySearch.value.toLowerCase().trim();
                el.companyTable.querySelectorAll('tbody tr').forEach(function(row) {
                    var text = (row.dataset.search || '').toLowerCase();
                    row.style.display = (!q || text.indexOf(q) !== -1) ? '' : 'none';
                });
            });
        }
        if (el.selectAll) {
            el.selectAll.addEventListener('change', function() {
                var checked = el.selectAll.checked;
                document.querySelectorAll('.smgp-cl-company-cb').forEach(function(cb) {
                    var row = cb.closest('tr');
                    if (row && row.style.display !== 'none') { cb.checked = checked; }
                });
            });
        }
    }

    // ── Sync via SSE ──

    function setupSync() {
        var syncBtn = document.getElementById('smgp-cl-sync-btn');
        var syncLog = document.getElementById('smgp-cl-sync-log');
        var syncLogContent = document.getElementById('smgp-cl-sync-log-content');
        if (!syncBtn) { return; }

        syncBtn.addEventListener('click', function() {
            var url = syncBtn.dataset.url;
            if (!url) { return; }

            syncBtn.disabled = true;
            syncBtn.innerHTML = '<span class="dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#10b981;animation:smgp-pulse 1s infinite;margin-right:1px;"></span>'
                + '<span class="dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#10b981;animation:smgp-pulse 1s infinite 0.15s;margin-right:1px;"></span>'
                + '<span class="dot" style="display:inline-block;width:5px;height:5px;border-radius:50%;background:#10b981;animation:smgp-pulse 1s infinite 0.3s;margin-right:4px;"></span>'
                + 'Sincronizando...';
            syncLogContent.textContent = '';
            show(syncLog);

            var source = new EventSource(url);
            source.onmessage = function(e) {
                if (e.data === '[DONE]') {
                    source.close();
                    syncBtn.disabled = false;
                    syncBtn.textContent = '↻ Sincronizar';
                    // Refresh cache stats and autocomplete.
                    searchCourses('');
                    var info = document.getElementById('smgp-cl-cache-info');
                    if (info) { info.textContent = 'Sincronización completada. Recargando...'; }
                    setTimeout(function() { window.location.reload(); }, 1500);
                    return;
                }
                syncLogContent.textContent += e.data + '\n';
                syncLogContent.scrollTop = syncLogContent.scrollHeight;
            };
            source.onerror = function() {
                source.close();
                syncBtn.disabled = false;
                syncBtn.textContent = '↻ Sincronizar';
                syncLogContent.textContent += '\n[Error de conexión]\n';
            };
        });
    }

    // ── Init ──

    return {
        init: function() {
            cacheElements();
            if (!el.scanBtn) { return; }

            el.scanBtn.addEventListener('click', onScan);
            el.importBtn.addEventListener('click', onImport);

            if (el.toggleUrlBtn) {
                el.toggleUrlBtn.addEventListener('click', function() {
                    urlMode = true;
                    hide(el.searchMode);
                    show(el.urlMode);
                });
            }
            if (el.toggleListBtn) {
                el.toggleListBtn.addEventListener('click', function() {
                    urlMode = false;
                    show(el.searchMode);
                    hide(el.urlMode);
                });
            }

            if (el.url) {
                el.url.addEventListener('keydown', function(e) {
                    if (e.key === 'Enter') { e.preventDefault(); onScan(); }
                });
            }

            setupAutocomplete();
            setupCompanyTable();
            setupSync();
        }
    };
});
