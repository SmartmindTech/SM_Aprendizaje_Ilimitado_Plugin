/**
 * Course landing page — section accordion and AJAX enrolment.
 *
 * @module     local_sm_graphics_plugin/course_landing
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/ajax', 'core/str'], function(Ajax, Str) {

    /**
     * Set up section accordion (expand/collapse).
     */
    function initAccordion() {
        document.querySelectorAll('.smgp-landing__section-header').forEach(function(header) {
            header.addEventListener('click', function() {
                var section = header.closest('.smgp-landing__section');
                var isExpanded = section.classList.contains('smgp-landing__section--expanded');

                section.classList.toggle('smgp-landing__section--expanded', !isExpanded);
                header.setAttribute('aria-expanded', String(!isExpanded));
            });

            header.addEventListener('keydown', function(e) {
                if (e.key === 'Enter' || e.key === ' ') {
                    e.preventDefault();
                    header.click();
                }
            });
        });
    }

    /**
     * Set up the enrol button AJAX handler.
     *
     * @param {number} courseid The course ID.
     */
    function initEnrolButton(courseid) {
        var btn = document.getElementById('smgp-landing-enrol');
        if (!btn) {
            return;
        }

        btn.addEventListener('click', function() {
            btn.disabled = true;
            btn.innerHTML = '...';

            Ajax.call([{
                methodname: 'local_sm_graphics_plugin_enrol_user',
                args: {courseid: courseid},
            }])[0].then(function(result) {
                if (result.success) {
                    // Enrolled — reload to show Start button.
                    window.location.reload();
                }
                return null;
            }).catch(function(err) {
                btn.disabled = false;
                Str.get_string('landing_enrol', 'local_sm_graphics_plugin').then(function(enrolText) {
                    btn.innerHTML = '<i class="icon-user-plus"></i> ' + enrolText;
                });
                window.console.error('Enrol failed:', err);
            });
        });
    }

    /**
     * Initialize unenrol modal.
     */
    function initUnenrolModal() {
        var btn = document.getElementById('smgp-unenrol-btn');
        var modal = document.getElementById('smgp-unenrol-modal');
        if (!btn || !modal) return;

        var closeBtn = document.getElementById('smgp-unenrol-close');
        var cancelBtn = document.getElementById('smgp-unenrol-cancel');
        var backdrop = modal.querySelector('.smgp-modal__backdrop');

        var confirmBtn = document.getElementById('smgp-unenrol-confirm');

        btn.addEventListener('click', function() { modal.style.display = 'flex'; });
        closeBtn.addEventListener('click', function() { modal.style.display = 'none'; });
        cancelBtn.addEventListener('click', function() { modal.style.display = 'none'; });
        backdrop.addEventListener('click', function() { modal.style.display = 'none'; });

        if (confirmBtn) {
            confirmBtn.addEventListener('click', function() {
                var cid = parseInt(confirmBtn.getAttribute('data-courseid'), 10);
                confirmBtn.disabled = true;
                confirmBtn.textContent = '...';

                Ajax.call([{
                    methodname: 'local_sm_graphics_plugin_unenrol_user',
                    args: {courseid: cid}
                }])[0].then(function(result) {
                    if (result.success) {
                        // Stay on the landing page — reload to show enrol button.
                        window.location.reload();
                    } else {
                        modal.style.display = 'none';
                        confirmBtn.disabled = false;
                    }
                }).catch(function(err) {
                    modal.style.display = 'none';
                    confirmBtn.disabled = false;
                    window.console.error('Unenrol failed:', err);
                });
            });
        }
    }

    /**
     * Initialize admin activity management (add/delete) on the landing page.
     * @param {number} courseid
     */
    function initActivityManagement(courseid) {
        // --- Delete activity buttons ---
        document.querySelectorAll('.smgp-landing__delete-btn').forEach(function(btn) {
            btn.addEventListener('click', function(e) {
                e.stopPropagation();
                var cmid = parseInt(btn.getAttribute('data-cmid'), 10);
                if (!cmid) return;

                Str.get_string('landing_delete_confirm', 'local_sm_graphics_plugin').then(function(msg) {
                    if (!confirm(msg)) return;

                    btn.disabled = true;
                    Ajax.call([{
                        methodname: 'local_sm_graphics_plugin_delete_activity',
                        args: {cmid: cmid}
                    }])[0].then(function(result) {
                        if (result.success) {
                            var li = btn.closest('.smgp-landing__activity');
                            if (li) li.remove();
                            // Update activity count in section header.
                            var section = btn.closest('.smgp-landing__section');
                            if (section) {
                                var count = section.querySelectorAll('.smgp-landing__activity').length;
                                var subtitleEl = section.querySelector('.smgp-landing__section-subtitle');
                                if (subtitleEl) {
                                    // Update the elements count in subtitle.
                                    subtitleEl.textContent = subtitleEl.textContent.replace(/^\d+/, count);
                                }
                            }
                        }
                    }).catch(function(err) {
                        btn.disabled = false;
                        window.console.error('Delete failed:', err);
                    });
                });
            });
        });

        // --- Add activity buttons ---
        // All activity types with their Bootstrap Icons and Moodle module names.
        var activityTypes = [
            {mod: 'genially',         name: 'Genially',              icon: 'icon-presentation',        color: '#f97316', isGenially: true},
            {mod: 'video',            name: 'Video',                 icon: 'icon-film',                color: '#3b82f6', isVideo: true},
            {mod: 'resource',         name: 'Archivo',               icon: 'icon-file-up',             color: '#3b82f6'},
            {mod: 'label',            name: 'Área de texto y medios',icon: 'icon-type',                color: '#3b82f6'},
            {mod: 'data',             name: 'Base de datos',         icon: 'icon-database',            color: '#f97316'},
            {mod: 'folder',           name: 'Carpeta',               icon: 'icon-folder',              color: '#3b82f6'},
            {mod: 'choice',           name: 'Consulta',              icon: 'icon-circle-check',        color: '#f97316'},
            {mod: 'quiz',             name: 'Cuestionario',          icon: 'icon-circle-help',         color: '#f97316'},
            {mod: 'survey',           name: 'Encuesta',              icon: 'icon-clipboard-check',     color: '#f97316'},
            {mod: 'feedback',         name: 'Feedback',              icon: 'icon-message-square-text', color: '#f97316'},
            {mod: 'forum',            name: 'Foro',                  icon: 'icon-message-circle',      color: '#f97316'},
            {mod: 'glossary',         name: 'Glosario',              icon: 'icon-notebook-text',       color: '#f97316'},
            {mod: 'h5pactivity',      name: 'H5P',                   icon: 'icon-circle-play',         color: '#3b82f6'},
            {mod: 'lti',              name: 'Herramienta externa',    icon: 'icon-external-link',       color: '#f97316'},
            {mod: 'iomadcertificate', name: 'IOMAD Certificate',     icon: 'icon-award',               color: '#f97316'},
            {mod: 'lesson',           name: 'Lección',               icon: 'icon-graduation-cap',      color: '#f97316'},
            {mod: 'book',             name: 'Libro',                 icon: 'icon-book-open',           color: '#3b82f6'},
            {mod: 'page',             name: 'Página',                icon: 'icon-file-text',           color: '#3b82f6'},
            {mod: 'imscp',            name: 'Paquete IMS',           icon: 'icon-package',             color: '#3b82f6'},
            {mod: 'scorm',            name: 'Paquete SCORM',         icon: 'icon-box',                 color: '#f97316'},
            {mod: 'workshop',         name: 'Taller',                icon: 'icon-users',               color: '#f97316'},
            {mod: 'assign',           name: 'Tarea',                 icon: 'icon-file-text',           color: '#f97316'},
            {mod: 'trainingevent',    name: 'Training event',        icon: 'icon-video',               color: '#ec4899'},
            {mod: 'url',              name: 'URL',                   icon: 'icon-link',                color: '#3b82f6'},
            {mod: 'wiki',             name: 'Wiki',                  icon: 'icon-book-open',           color: '#f97316'},
        ];

        document.querySelectorAll('.smgp-landing__add-btn').forEach(function(btn) {
            btn.addEventListener('click', function() {
                var sectionnum = btn.getAttribute('data-sectionnum') || 0;

                // Toggle picker.
                var existing = btn.parentElement.querySelector('.smgp-add-picker');
                if (existing) {
                    existing.remove();
                    return;
                }

                var gridHtml = '<div class="smgp-add-grid">';
                activityTypes.forEach(function(act) {
                    gridHtml += '<button class="smgp-add-grid__item" data-mod="' + act.mod + '"' +
                        (act.isGenially ? ' data-genially="1"' : '') +
                        (act.isVideo ? ' data-video="1"' : '') + '>' +
                        '<i class="' + act.icon + ' smgp-add-grid__icon" style="color:' + act.color + ';"></i>' +
                        '<span class="smgp-add-grid__name">' + act.name + '</span></button>';
                });
                gridHtml += '</div>';

                var picker = document.createElement('div');
                picker.className = 'smgp-add-picker';
                picker.innerHTML = gridHtml;
                btn.parentElement.insertBefore(picker, btn.nextSibling);

                // Attach click handlers.
                picker.querySelectorAll('.smgp-add-grid__item').forEach(function(item) {
                    item.addEventListener('click', function() {
                        if (item.getAttribute('data-genially') === '1') {
                            picker.remove();
                            showGeniallyModal(sectionnum, 'genially', 'Genially',
                                'https://view.genial.ly/...');
                        } else if (item.getAttribute('data-video') === '1') {
                            picker.remove();
                            showGeniallyModal(sectionnum, 'url', 'Video',
                                'https://www.youtube.com/watch?v=... or direct video URL');
                        } else {
                            // Redirect to Moodle's standard add form for this module type.
                            var mod = item.getAttribute('data-mod');
                            window.location.href = M.cfg.wwwroot + '/course/modedit.php?add=' + mod +
                                '&type=&course=' + courseid + '&section=' + sectionnum + '&return=0';
                        }
                    });
                });
            });
        });

        // --- URL-based activity modal (Genially, Video URL) ---
        var geniallyModal = document.getElementById('smgp-genially-modal');
        if (!geniallyModal) return;

        var geniallyName = document.getElementById('smgp-genially-name');
        var geniallyUrl = document.getElementById('smgp-genially-url');
        var geniallySectionnum = document.getElementById('smgp-genially-sectionnum');
        var geniallySave = document.getElementById('smgp-genially-save');
        var geniallyClose = document.getElementById('smgp-genially-close');
        var geniallyCancel = document.getElementById('smgp-genially-cancel');
        var geniallyBackdrop = geniallyModal.querySelector('.smgp-modal__backdrop');
        var geniallyTitle = geniallyModal.querySelector('.smgp-modal__header h3');
        var geniallyUrlHint = geniallyModal.querySelector('.smgp-genially-url-hint');
        var geniallyUploadGroup = document.getElementById('smgp-genially-upload-group');
        var geniallyUrlGroup = document.getElementById('smgp-genially-url-group');
        var geniallyTabUrl = document.getElementById('smgp-video-tab-url');
        var geniallyTabUpload = document.getElementById('smgp-video-tab-upload');
        var geniallyVideoTabs = document.getElementById('smgp-video-tabs');

        var modalState = {type: 'genially', sectionnum: 0, mode: 'url'};

        function showGeniallyModal(sectionnum, type, title, placeholder) {
            modalState.type = type || 'genially';
            modalState.sectionnum = sectionnum;
            modalState.mode = 'url';
            geniallySectionnum.value = sectionnum;
            geniallyName.value = '';
            geniallyUrl.value = '';

            // Update modal title and hints.
            if (geniallyTitle) {
                geniallyTitle.innerHTML = '<i class="' +
                    (type === 'url' ? 'icon-film' : 'icon-presentation') +
                    '" style="color:' + (type === 'url' ? '#3b82f6' : '#f97316') +
                    ';margin-right:0.5rem;"></i> ' + (title || 'Genially');
            }
            if (geniallyUrl) {
                geniallyUrl.placeholder = placeholder || 'https://view.genial.ly/...';
            }

            // Show/hide video tabs (URL vs Upload).
            var isVideo = (type === 'url');
            if (geniallyVideoTabs) geniallyVideoTabs.style.display = isVideo ? '' : 'none';
            if (geniallyUrlGroup) geniallyUrlGroup.style.display = '';
            if (geniallyUploadGroup) geniallyUploadGroup.style.display = 'none';
            if (geniallyUrlHint) {
                geniallyUrlHint.textContent = isVideo
                    ? 'YouTube, Vimeo, or direct video URL (mp4, webm...)'
                    : 'Paste the Genially embed URL (e.g., https://view.genial.ly/...)';
            }

            geniallyModal.style.display = 'flex';
            geniallyName.focus();
        }

        function hideGeniallyModal() {
            geniallyModal.style.display = 'none';
        }

        // Video tabs: URL vs Upload.
        if (geniallyTabUrl) {
            geniallyTabUrl.addEventListener('click', function() {
                modalState.mode = 'url';
                geniallyTabUrl.classList.add('active');
                if (geniallyTabUpload) geniallyTabUpload.classList.remove('active');
                if (geniallyUrlGroup) geniallyUrlGroup.style.display = '';
                if (geniallyUploadGroup) geniallyUploadGroup.style.display = 'none';
            });
        }
        if (geniallyTabUpload) {
            geniallyTabUpload.addEventListener('click', function() {
                modalState.mode = 'upload';
                geniallyTabUpload.classList.add('active');
                if (geniallyTabUrl) geniallyTabUrl.classList.remove('active');
                if (geniallyUrlGroup) geniallyUrlGroup.style.display = 'none';
                if (geniallyUploadGroup) geniallyUploadGroup.style.display = '';
            });
        }

        geniallyClose.addEventListener('click', hideGeniallyModal);
        geniallyCancel.addEventListener('click', hideGeniallyModal);
        geniallyBackdrop.addEventListener('click', hideGeniallyModal);

        // --- Drag & drop upload zone ---
        var uploadZone = document.getElementById('smgp-upload-zone');
        var uploadInput = document.getElementById('smgp-upload-input');
        var uploadFilename = document.getElementById('smgp-upload-filename');
        var selectedFile = null;

        if (uploadZone && uploadInput) {
            // Click to open file dialog.
            uploadZone.addEventListener('click', function() {
                uploadInput.click();
            });

            uploadInput.addEventListener('change', function() {
                if (uploadInput.files && uploadInput.files[0]) {
                    selectedFile = uploadInput.files[0];
                    uploadFilename.textContent = selectedFile.name + ' (' + (selectedFile.size / 1048576).toFixed(1) + ' MB)';
                    uploadFilename.style.display = '';
                    uploadZone.classList.add('smgp-upload-zone--has-file');
                }
            });

            // Drag and drop.
            uploadZone.addEventListener('dragover', function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadZone.classList.add('smgp-upload-zone--dragover');
            });
            uploadZone.addEventListener('dragleave', function(e) {
                e.preventDefault();
                uploadZone.classList.remove('smgp-upload-zone--dragover');
            });
            uploadZone.addEventListener('drop', function(e) {
                e.preventDefault();
                e.stopPropagation();
                uploadZone.classList.remove('smgp-upload-zone--dragover');
                if (e.dataTransfer.files && e.dataTransfer.files[0]) {
                    selectedFile = e.dataTransfer.files[0];
                    uploadInput.files = e.dataTransfer.files;
                    uploadFilename.textContent = selectedFile.name + ' (' + (selectedFile.size / 1048576).toFixed(1) + ' MB)';
                    uploadFilename.style.display = '';
                    uploadZone.classList.add('smgp-upload-zone--has-file');
                }
            });
        }

        geniallySave.addEventListener('click', function() {
            var name = geniallyName.value.trim();
            var sectionnum = parseInt(geniallySectionnum.value, 10);

            if (modalState.mode === 'upload') {
                // Upload the file to Moodle's draft area, then create mod_resource.
                if (!selectedFile) return;
                if (!name) name = selectedFile.name.replace(/\.[^.]+$/, '');

                geniallySave.disabled = true;
                geniallySave.textContent = '...';

                // Upload to Moodle's draft area via the repository/upload endpoint.
                var formData = new FormData();
                formData.append('repo_upload_file', selectedFile);
                formData.append('sesskey', M.cfg.sesskey);
                formData.append('repo_id', ''); // Will be resolved.
                formData.append('itemid', 0);
                formData.append('author', '');
                formData.append('title', selectedFile.name);
                formData.append('overwrite', 0);

                // Use Moodle's simple file upload endpoint.
                fetch(M.cfg.wwwroot + '/repository/repository_ajax.php?action=upload', {
                    method: 'POST',
                    body: formData,
                }).then(function(resp) {
                    // Fallback: redirect to Moodle's resource form.
                    window.location.href = M.cfg.wwwroot + '/course/modedit.php?add=resource&type=&course=' +
                        courseid + '&section=' + sectionnum + '&return=0';
                }).catch(function() {
                    window.location.href = M.cfg.wwwroot + '/course/modedit.php?add=resource&type=&course=' +
                        courseid + '&section=' + sectionnum + '&return=0';
                });
                return;
            }

            var url = geniallyUrl.value.trim();
            if (!name || !url) return;

            geniallySave.disabled = true;
            geniallySave.textContent = '...';

            Ajax.call([{
                methodname: 'local_sm_graphics_plugin_add_activity',
                args: {
                    courseid: courseid,
                    sectionnum: sectionnum,
                    type: modalState.type,
                    name: name,
                    url: url
                }
            }])[0].then(function(result) {
                if (result.success) {
                    window.location.reload();
                }
            }).catch(function(err) {
                geniallySave.disabled = false;
                geniallySave.textContent = 'Save';
                window.console.error('Add activity failed:', err);
            });
        });
    }

    return {
        init: function(courseid) {
            initAccordion();
            initEnrolButton(courseid);
            initUnenrolModal();
            initActivityManagement(courseid);
        }
    };
});
