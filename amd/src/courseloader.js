/**
 * Course Loader from SharePoint — AJAX scan and import.
 *
 * @module     local_sm_graphics_plugin/courseloader
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/notification'], function(Ajax, Notification) {

    /** File type labels for the scan results table. */
    var TYPE_LABELS = {
        mbz:               'Backup Moodle (MBZ)',
        scorm:             'Paquetes SCORM',
        pdf:               'Documentos PDF',
        documents:         'Documentos plataforma',
        evaluations_aiken: 'Evaluaciones AIKEN',
        evaluations_gift:  'Evaluaciones GIFT'
    };

    /** DOM element cache. */
    var el = {};

    /**
     * Cache DOM elements.
     */
    function cacheElements() {
        el.url         = document.getElementById('smgp-cl-url');
        el.category    = document.getElementById('smgp-cl-category');
        el.scanBtn     = document.getElementById('smgp-cl-scan-btn');
        el.importBtn   = document.getElementById('smgp-cl-import-btn');
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

    /**
     * Show an element (remove d-none).
     * @param {HTMLElement} element
     */
    function show(element) {
        if (element) {
            element.classList.remove('d-none');
        }
    }

    /**
     * Hide an element (add d-none).
     * @param {HTMLElement} element
     */
    function hide(element) {
        if (element) {
            element.classList.add('d-none');
        }
    }

    /**
     * Reset all result/status sections.
     */
    function resetUI() {
        hide(el.results);
        hide(el.warnings);
        hide(el.progress);
        hide(el.log);
        hide(el.success);
        hide(el.error);
        hide(el.importBtn);
        el.resultsBody.innerHTML = '';
        el.warningsList.innerHTML = '';
        el.logContent.textContent = '';
    }

    /**
     * Format file size for display.
     * @param {number} bytes
     * @return {string}
     */
    function formatSize(bytes) {
        if (bytes === 0) {
            return '0 B';
        }
        var units = ['B', 'KB', 'MB', 'GB'];
        var i = Math.floor(Math.log(bytes) / Math.log(1024));
        return (bytes / Math.pow(1024, i)).toFixed(1) + ' ' + units[i];
    }

    /**
     * Add a row to the results table.
     * @param {string} label Type label.
     * @param {Array} files Array of file objects.
     */
    function addResultRow(label, files) {
        if (!files || files.length === 0) {
            return;
        }
        var row = document.createElement('tr');
        var names = files.map(function(f) {
            return f.name + ' (' + formatSize(f.size) + ')';
        }).join(', ');
        row.innerHTML = '<td>' + label + '</td>'
            + '<td class="text-center">' + files.length + '</td>'
            + '<td><small>' + names + '</small></td>';
        el.resultsBody.appendChild(row);
    }

    /**
     * Handle the scan button click.
     */
    function onScan() {
        var url = el.url.value.trim();
        if (!url) {
            el.url.focus();
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

            // Show results.
            el.folderName.textContent = data.folder_name;
            addResultRow(TYPE_LABELS.mbz, data.mbz);
            addResultRow(TYPE_LABELS.scorm, data.scorm);
            addResultRow(TYPE_LABELS.pdf, data.pdf);
            addResultRow(TYPE_LABELS.documents, data.documents);
            addResultRow(TYPE_LABELS.evaluations_aiken, data.evaluations_aiken);
            addResultRow(TYPE_LABELS.evaluations_gift, data.evaluations_gift);
            show(el.results);

            // Show warnings if any.
            if (data.warnings && data.warnings.length > 0) {
                data.warnings.forEach(function(w) {
                    var li = document.createElement('li');
                    li.textContent = w;
                    el.warningsList.appendChild(li);
                });
                show(el.warnings);
            }

            // Show import button if we have at least an MBZ.
            if (data.mbz && data.mbz.length > 0) {
                show(el.importBtn);
            }

        }).catch(function(err) {
            hide(el.scanning);
            el.scanBtn.disabled = false;
            Notification.exception(err);
        });
    }

    /**
     * Handle the import button click.
     */
    function onImport() {
        var url = el.url.value.trim();
        var categoryid = parseInt(el.category.value, 10);

        if (!url || !categoryid) {
            return;
        }

        hide(el.importBtn);
        hide(el.results);
        show(el.progress);

        el.scanBtn.disabled = true;

        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_sharepoint_import',
            args: {folder_url: url, categoryid: categoryid}
        }])[0].then(function(data) {
            hide(el.progress);
            el.scanBtn.disabled = false;

            // Show log.
            if (data.log && data.log.length > 0) {
                el.logContent.textContent = data.log.join('\n');
                show(el.log);
            }

            if (data.success) {
                el.courseLink.href = data.course_url;
                show(el.success);
            } else {
                show(el.error);
                el.errorMsg.textContent = data.log.length > 0 ? data.log[data.log.length - 1] : 'Error desconocido.';
            }

        }).catch(function(err) {
            hide(el.progress);
            el.scanBtn.disabled = false;
            Notification.exception(err);
        });
    }

    return {
        init: function() {
            cacheElements();

            if (!el.scanBtn) {
                return; // Not configured, nothing to wire up.
            }

            el.scanBtn.addEventListener('click', onScan);
            el.importBtn.addEventListener('click', onImport);

            // Allow pressing Enter in the URL field to trigger scan.
            el.url.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    onScan();
                }
            });
        }
    };
});
