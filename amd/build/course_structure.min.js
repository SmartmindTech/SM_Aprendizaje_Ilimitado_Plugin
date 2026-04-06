/**
 * Course structure editor for the restore schema step (step 4).
 *
 * @module     local_sm_graphics_plugin/course_structure
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    var GREEN = '#10b981';
    var sections = [];
    var spActivities = []; // SharePoint palette (separate from course structure).
    var hiddenInput = null;
    var mainContainer = null;
    var dragState = {type: null, srcSection: null, srcActivity: null};
    var selectedActivities = []; // Multi-select with Ctrl+click.
    var scrollInterval = null;

    // ── Auto-scroll during drag ──
    // Large scroll zone (150px from edge) with fast speed for long-distance dragging.
    function startAutoScroll() {
        if (scrollInterval) return;
        scrollInterval = setInterval(function() {
            var y = lastDragY;
            if (y < 0) return;
            var zone = 150;
            var maxSpeed = 25;
            if (y < zone) {
                var pct = 1 - (y / zone);
                window.scrollBy(0, -(maxSpeed * pct * pct + 3));
            } else if (y > window.innerHeight - zone) {
                var pct = 1 - ((window.innerHeight - y) / zone);
                window.scrollBy(0, maxSpeed * pct * pct + 3);
            }
        }, 16);
    }
    function stopAutoScroll() {
        if (scrollInterval) { clearInterval(scrollInterval); scrollInterval = null; }
    }
    var lastDragY = -1;

    // ============================================================
    // Init
    // ============================================================

    function init() {
        mainContainer = document.getElementById('id_coursesettingscontainer');
        if (!mainContainer) {
            return;
        }
        var currentStep = document.querySelector('.backup_stage_current');
        if (!currentStep) {
            return;
        }
        var stepText = currentStep.textContent.trim();
        var isSchema = (stepText.indexOf('4') !== -1 || stepText.indexOf('Schema') !== -1 || stepText.indexOf('Esquema') !== -1);
        if (!isSchema) {
            return;
        }

        hiddenInput = document.querySelector('input[name="smgp_course_structure"]');
        if (!hiddenInput) {
            hiddenInput = document.createElement('input');
            hiddenInput.type = 'hidden';
            hiddenInput.name = 'smgp_course_structure';
            mainContainer.appendChild(hiddenInput);
        }

        parseSections();
        loadSharePointExtras();
        injectStyles();
        hideSelectAllNone();
        renderAll();
        syncToHidden();

        // Global dragover for auto-scroll near edges.
        document.addEventListener('dragover', function(e) {
            lastDragY = e.clientY;
            startAutoScroll();
        });
        document.addEventListener('dragend', function() {
            lastDragY = -1;
            stopAutoScroll();
            selectedActivities = [];
            mainContainer.querySelectorAll('.smgp-act-selected').forEach(function(el) {
                el.classList.remove('smgp-act-selected');
            });
        });
    }

    /**
     * Hide the "Select All / None (Show type options)" block injected by Moodle's backup JS.
     * It can be inside a fieldset or directly inside the container.
     */
    function hideSelectAllNone() {
        // Moodle inserts this as a .backup-course-selector or as a bare div with "All / None" links.
        // It sits inside the fieldset that wraps #id_coursesettingscontainer.
        var fieldset = mainContainer.closest('fieldset');
        if (fieldset) {
            // Hide any element after the fcontainer that contains "All / None".
            fieldset.querySelectorAll('.grouped_settings, .backup-course-selector, .bcs-selector').forEach(function(el) {
                if (!el.classList.contains('section_level') && !el.classList.contains('activity_level')) {
                    el.style.display = 'none';
                }
            });
        }
        // Also hide elements by text content already in the DOM.
        if (fieldset) {
            fieldset.querySelectorAll('div, span, p').forEach(function(el) {
                var t = el.textContent || '';
                if (((t.indexOf('All') !== -1 && t.indexOf('None') !== -1) ||
                    (t.indexOf('Todos') !== -1 && t.indexOf('Ninguno') !== -1) ||
                    (t.indexOf('Show type') !== -1) || (t.indexOf('Mostrar tipo') !== -1)) &&
                    el.querySelectorAll('a').length >= 2) {
                    el.style.display = 'none';
                }
            });
        }
        // Also try MutationObserver to catch dynamically injected elements.
        var observer = new MutationObserver(function(mutations) {
            mutations.forEach(function(m) {
                m.addedNodes.forEach(function(node) {
                    if (node.nodeType !== 1) {
                        return;
                    }
                    if (node.classList && (node.classList.contains('backup-course-selector') ||
                        node.classList.contains('bcs-selector'))) {
                        node.style.display = 'none';
                    }
                    // Also hide any element containing "All/None" or "Todos/Ninguno" links.
                    if (node.textContent && fieldset && fieldset.contains(node)) {
                        var t = node.textContent;
                        if ((t.indexOf('All') !== -1 && t.indexOf('None') !== -1) ||
                            (t.indexOf('Todos') !== -1 && t.indexOf('Ninguno') !== -1) ||
                            (t.indexOf('Show type') !== -1) || (t.indexOf('Mostrar tipo') !== -1)) {
                            node.style.display = 'none';
                        }
                    }
                });
            });
        });
        if (fieldset) {
            observer.observe(fieldset, {childList: true, subtree: true});
        }
    }

    /**
     * Load SharePoint extras (SCORM, PDFs, evaluations) from session manifest
     * and add them as a new section at the end of the sections list.
     */
    function loadSharePointExtras() {
        var manifestEl = document.getElementById('smgp-sp-manifest-data');
        if (!manifestEl || !manifestEl.value) {
            return;
        }
        var raw = manifestEl.value;
        try {
            // The value may be double-encoded (JSON string of JSON).
            var manifest = JSON.parse(raw);
            if (typeof manifest === 'string') {
                manifest = JSON.parse(manifest);
            }
        } catch (e) {
            return;
        }

        spActivities = [];
        var iconMap = {
            scorm: 'icon-box',
            pdf: 'icon-file-text',
            documents: 'icon-file',
            evaluations_aiken: 'icon-circle-help',
            evaluations_gift: 'icon-circle-help'
        };
        var labelMap = {
            scorm: 'Paquete SCORM',
            pdf: 'PDF',
            documents: 'Documento',
            evaluations_aiken: 'Evaluación AIKEN',
            evaluations_gift: 'Evaluación GIFT'
        };

        ['scorm', 'pdf', 'documents', 'evaluations_aiken', 'evaluations_gift'].forEach(function(type) {
            var files = manifest[type] || [];
            files.forEach(function(f, i) {
                var name = f.name.replace(/\.[^/.]+$/, '');
                spActivities.push({
                    name: name,
                    origName: name,
                    actKey: 'sp_' + type + '_' + i,
                    modname: labelMap[type],
                    modicon: '',
                    iconClass: iconMap[type], // Lucide icon class for SP activities.
                    el: null,
                    checked: true
                });
            });
        });

        // spActivities is now stored at module level, rendered separately.
    }

    // ============================================================
    // Parse Moodle DOM
    // ============================================================

    function parseSections() {
        var sectionEls = mainContainer.querySelectorAll(':scope > .grouped_settings.section_level');
        sections = [];
        sectionEls.forEach(function(secEl) {
            var secData = parseOneSection(secEl);
            if (secData) {
                sections.push(secData);
            }
        });
    }

    function parseOneSection(secEl) {
        var secCheckbox = secEl.querySelector('.include_setting.section_level input[type="checkbox"]');
        if (!secCheckbox) {
            return null;
        }
        var secLabel = secEl.querySelector('.include_setting.section_level label');
        var secName = secLabel ? secLabel.textContent.trim() : 'Section';
        var secKey = secCheckbox.name;
        var activities = [];
        var actEls = secEl.querySelectorAll(':scope > .grouped_settings.activity_level');
        actEls.forEach(function(actEl) {
            var actData = parseOneActivity(actEl);
            if (actData) {
                activities.push(actData);
            }
        });
        return {
            name: secName,
            origName: secName,
            sectionKey: secKey,
            el: secEl,
            checked: secCheckbox.checked,
            activities: activities
        };
    }

    function parseOneActivity(actEl) {
        var actCheckbox = actEl.querySelector('.include_setting.activity_level input[type="checkbox"]');
        if (!actCheckbox) {
            return null;
        }
        var actLabel = actEl.querySelector('.include_setting.activity_level label');
        var actName = '';
        var modicon = '';
        var modname = '';
        if (actLabel) {
            var img = actLabel.querySelector('img');
            if (img) {
                modicon = img.src;
                modname = img.alt || img.title || '';
            }
            actName = '';
            actLabel.childNodes.forEach(function(n) {
                if (n.nodeType === 3) {
                    actName += n.textContent;
                }
            });
            actName = actName.trim();
        }
        return {
            name: actName,
            origName: actName,
            actKey: actCheckbox.name,
            modname: modname,
            modicon: modicon,
            el: actEl,
            checked: actCheckbox.checked
        };
    }

    // ============================================================
    // Styles
    // ============================================================

    function injectStyles() {
        var style = document.createElement('style');
        style.textContent = ''
            // Hide original Moodle elements.
            + '#id_coursesettingscontainer > .grouped_settings.section_level { display: none !important; }'
            // Hide "Select All / None (Show type options)" — multiple possible containers.
            + '#id_coursesettingscontainer > .grouped_settings:not(.section_level):not(.activity_level) { display: none !important; }'
            + '.backup-course-selector { display: none !important; }'
            + '#page-backup-restore .bcs-selector { display: none !important; }'

            // === Toggle switch (replaces checkboxes) ===
            + '.smgp-toggle { position: relative; display: inline-block; width: 36px; height: 20px; flex-shrink: 0; }'
            + '.smgp-toggle input { opacity: 0; width: 0; height: 0; }'
            + '.smgp-toggle__slider {'
            + '  position: absolute; inset: 0; background: #cbd5e1; border-radius: 20px;'
            + '  cursor: pointer; transition: background 0.2s;'
            + '}'
            + '.smgp-toggle__slider::before {'
            + '  content: ""; position: absolute; width: 16px; height: 16px;'
            + '  left: 2px; bottom: 2px; background: #fff; border-radius: 50%;'
            + '  transition: transform 0.2s; box-shadow: 0 1px 3px rgba(0,0,0,0.15);'
            + '}'
            + '.smgp-toggle input:checked + .smgp-toggle__slider { background: ' + GREEN + '; }'
            + '.smgp-toggle input:checked + .smgp-toggle__slider::before { transform: translateX(16px); }'

            // === Section cards ===
            + '.smgp-section-card {'
            + '  background: #fff; border: 1px solid #e5e7eb; border-left: 3px solid ' + GREEN + ';'
            + '  border-radius: 12px; margin-bottom: 0.75rem; overflow: hidden;'
            + '  box-shadow: 0 1px 4px rgba(0,0,0,0.03);'
            + '  transition: box-shadow 0.2s, transform 0.2s;'
            + '}'
            + '.smgp-section-card:hover { box-shadow: 0 4px 12px rgba(0,0,0,0.06); }'
            + '.smgp-section-card.dragging { opacity: 0.4; transform: scale(0.98); }'
            + '.smgp-section-card.drag-over { box-shadow: 0 0 0 2px ' + GREEN + ', 0 4px 12px rgba(16,185,129,0.15); }'

            // === Section header ===
            + '.smgp-section-header {'
            + '  display: flex; align-items: center; gap: 0.65rem;'
            + '  padding: 0.85rem 1.1rem;'
            + '}'
            + '.smgp-section-header__handle {'
            + '  cursor: grab; color: #c4c9d4; font-size: 0.85rem; user-select: none; flex-shrink: 0;'
            + '  transition: color 0.15s;'
            + '}'
            + '.smgp-section-header__handle:hover { color: ' + GREEN + '; }'
            + '.smgp-section-header__name {'
            + '  font-weight: 600; font-size: 0.95rem; color: #1e293b; flex: 1;'
            + '  cursor: text; border-radius: 6px; padding: 3px 8px;'
            + '  transition: background 0.15s;'
            + '}'
            + '.smgp-section-header__name:hover { background: #f1f5f9; }'
            + '.smgp-section-header__name-input {'
            + '  font-weight: 600; font-size: 0.95rem; color: #1e293b; flex: 1;'
            + '  border: 1.5px solid ' + GREEN + '; border-radius: 6px; padding: 3px 8px;'
            + '  outline: none; box-shadow: 0 0 0 3px rgba(16,185,129,0.1);'
            + '}'
            + '.smgp-section-header__badge {'
            + '  font-size: 0.7rem; font-weight: 500; color: #64748b; background: #f1f5f9;'
            + '  padding: 2px 8px; border-radius: 99px; white-space: nowrap;'
            + '}'

            // === Activity list (div-based, not table) ===
            + '.smgp-activities-list { padding: 0; }'
            + '.smgp-activity-row {'
            + '  display: flex; align-items: center; gap: 0.6rem;'
            + '  padding: 0.6rem 1.1rem 0.6rem 2rem;'
            + '  border-top: 1px solid #f3f4f6;'
            + '  transition: background 0.1s;'
            + '}'
            + '.smgp-activity-row:nth-child(even) { background: #fafbfc; }'
            + '.smgp-activity-row:hover { background: #f0fdf4; }'
            + '.smgp-activity-row.dragging { opacity: 0.35; }'
            + '.smgp-activity-row.smgp-act-selected { background: #ecfdf5 !important; box-shadow: inset 3px 0 0 ' + GREEN + '; }'
            + '.smgp-activity-row.drag-over { box-shadow: inset 0 -2px 0 ' + GREEN + '; }'
            + '.smgp-act-handle { cursor: grab; color: #d1d5db; font-size: 0.8rem; flex-shrink: 0; }'
            + '.smgp-act-handle:hover { color: ' + GREEN + '; }'
            + '.smgp-act-icon {'
            + '  width: 28px; height: 28px; border-radius: 6px; background: #f1f5f9;'
            + '  display: flex; align-items: center; justify-content: center; flex-shrink: 0;'
            + '}'
            + '.smgp-act-icon img { width: 16px; height: 16px; }'
            + '.smgp-act-icon i { background: none !important; width: auto !important; height: auto !important; padding: 0 !important; margin: 0 !important; }'
            + '.smgp-act-info { flex: 1; min-width: 0; text-align: left; }'
            + '.smgp-act-name {'
            + '  font-weight: 600; font-size: 0.875rem; color: #1e293b;'
            + '  cursor: text; border-radius: 4px; padding: 1px 6px;'
            + '  display: block; transition: background 0.15s; text-align: left;'
            + '}'
            + '.smgp-act-name:hover { background: #f1f5f9; }'
            + '.smgp-act-name-input {'
            + '  font-weight: 600; font-size: 0.875rem; color: #1e293b;'
            + '  border: 1.5px solid ' + GREEN + '; border-radius: 4px; padding: 1px 6px;'
            + '  outline: none; width: 100%; box-shadow: 0 0 0 3px rgba(16,185,129,0.1);'
            + '}'
            + '.smgp-act-modlabel { font-size: 0.75rem; color: #94a3b8; margin-top: 1px; padding-left: 6px; text-align: left; }'

            // === Empty section drop zone ===
            + '.smgp-empty-drop {'
            + '  padding: 1.25rem; text-align: center; color: #94a3b8; font-size: 0.8rem;'
            + '  border-top: 1px solid #f3f4f6; font-style: italic;'
            + '}'
            + '.smgp-empty-drop.drag-over { background: #f0fdf4; color: ' + GREEN + '; }'

            // === Add buttons ===
            + '.smgp-add-section-btn, .smgp-add-activity-btn {'
            + '  display: inline-flex; align-items: center; gap: 0.35rem;'
            + '  background: none; border: 1.5px dashed ' + GREEN + '; border-radius: 8px;'
            + '  color: ' + GREEN + '; font-weight: 600; font-size: 0.8rem;'
            + '  padding: 0.4rem 1rem; cursor: pointer; transition: all 0.15s;'
            + '}'
            + '.smgp-add-section-btn:hover, .smgp-add-activity-btn:hover {'
            + '  background: rgba(16,185,129,0.06); border-style: solid;'
            + '}'
            + '.smgp-add-activity-btn { margin: 0.4rem 1.1rem 0.65rem 2rem; padding: 0.3rem 0.75rem; }'

            // === Structure heading ===
            + '.smgp-structure-heading {'
            + '  font-weight: 600; font-size: 1.05rem; color: #1e293b; margin: 1.75rem 0 0.75rem;'
            + '  padding-bottom: 0.5rem; border-bottom: 2px solid #e2e8f0;'
            + '  display: flex; align-items: center; gap: 0.4rem;'
            + '}'
            + '';
        document.head.appendChild(style);
    }

    // ============================================================
    // Rendering
    // ============================================================

    function renderAll() {
        mainContainer.querySelectorAll('.smgp-section-card, .smgp-structure-heading, .smgp-add-section-btn, .smgp-sp-palette').forEach(function(el) {
            el.remove();
        });

        var smgpFields = mainContainer.querySelector('.smgp-restore-fields');
        var insertAfter = smgpFields || mainContainer.lastElementChild;

        var heading = document.createElement('h4');
        heading.className = 'smgp-structure-heading';
        heading.innerHTML = '<i class="icon-layers" style="color:' + GREEN + ';"></i> Estructura del curso';
        insertAfter.after(heading);

        var lastEl = heading;
        sections.forEach(function(sec, si) {
            var card = createSectionCard(sec, si);
            lastEl.after(card);
            lastEl = card;
        });

        var addSecBtn = document.createElement('button');
        addSecBtn.type = 'button';
        addSecBtn.className = 'smgp-add-section-btn';
        addSecBtn.innerHTML = '<span style="font-size:1.1em;">+</span> Añadir sección';
        addSecBtn.addEventListener('click', function() {
            var scrollY = window.scrollY;
            sections.push({
                name: 'Nueva sección',
                origName: '',
                sectionKey: 'smgp_new_section_' + sections.length,
                el: null,
                checked: true,
                activities: []
            });
            renderAll();
            syncToHidden();
            // Scroll to the newly added section instead of jumping to top.
            requestAnimationFrame(function() {
                var cards = mainContainer.querySelectorAll('.smgp-section-card');
                var lastCard = cards[cards.length - 1];
                if (lastCard) {
                    lastCard.scrollIntoView({behavior: 'smooth', block: 'center'});
                } else {
                    window.scrollTo(0, scrollY);
                }
            });
        });
        lastEl.after(addSecBtn);

        // Render SharePoint palette as a separate section (not part of course structure).
        if (spActivities.length > 0) {
            var spHeading = document.createElement('h4');
            spHeading.className = 'smgp-structure-heading';
            spHeading.innerHTML = '<i class="icon-database" style="color:' + GREEN + ';"></i> Contenido SharePoint'
                + '<span style="font-size:0.7rem;font-weight:500;color:#64748b;background:#f1f5f9;padding:2px 8px;border-radius:99px;margin-left:auto;">'
                + spActivities.length + ' archivos</span>';

            var spCard = document.createElement('div');
            spCard.className = 'smgp-section-card smgp-sp-palette';
            spCard.style.borderLeftColor = '#94a3b8'; // Grey border to distinguish from course sections.

            var spList = document.createElement('div');
            spList.className = 'smgp-activities-list';

            spActivities.forEach(function(act, ai) {
                var row = createActivityRow(act, ai, -1); // sectionIndex = -1 (not a real section).
                spList.appendChild(row);
            });

            spCard.appendChild(spList);
            addSecBtn.after(spHeading);
            spHeading.after(spCard);
        }
    }

    function createSectionCard(sec, sectionIndex) {
        var card = document.createElement('div');
        card.className = 'smgp-section-card';
        card.draggable = true;
        card.dataset.sectionIndex = sectionIndex;

        // === Header ===
        var header = document.createElement('div');
        header.className = 'smgp-section-header';

        var handle = document.createElement('span');
        handle.className = 'smgp-section-header__handle';
        handle.innerHTML = '&#x2630;';
        header.appendChild(handle);

        // Toggle switch (skip for SP palette section).
        if (!sec.isSPSection) {
            var toggle = createToggle(sec.checked, function(checked) {
                sec.checked = checked;
                sec.activities.forEach(function(a) { a.checked = checked; });
                card.querySelectorAll('.smgp-toggle input').forEach(function(inp) {
                    inp.checked = checked;
                });
                syncToHidden();
            });
            header.appendChild(toggle);
        }

        // Editable name.
        var nameSpan = document.createElement('span');
        nameSpan.className = 'smgp-section-header__name';
        nameSpan.textContent = sec.name;
        nameSpan.addEventListener('click', function() {
            startEditName(nameSpan, sec.name, function(newName) {
                sec.name = newName;
                syncToHidden();
            });
        });
        header.appendChild(nameSpan);

        // Activity count badge.
        var badge = document.createElement('span');
        badge.className = 'smgp-section-header__badge';
        badge.textContent = sec.activities.length + (sec.activities.length === 1 ? ' actividad' : ' actividades');
        header.appendChild(badge);

        card.appendChild(header);

        // === Activities list ===
        var listDiv = document.createElement('div');
        listDiv.className = 'smgp-activities-list';
        listDiv.dataset.sectionIndex = sectionIndex;

        if (sec.activities.length > 0) {
            sec.activities.forEach(function(act, ai) {
                var row = createActivityRow(act, ai, sectionIndex);
                listDiv.appendChild(row);
            });
        } else {
            // Empty drop zone for dragging activities into empty sections.
            var emptyZone = document.createElement('div');
            emptyZone.className = 'smgp-empty-drop';
            emptyZone.textContent = 'Arrastra actividades aquí';
            listDiv.appendChild(emptyZone);
        }

        // Drop handlers on the list container (for empty section drops).
        listDiv.addEventListener('dragover', function(e) {
            if (dragState.type === 'activity') {
                e.preventDefault();
                e.stopPropagation();
                e.dataTransfer.dropEffect = 'move';
                var emptyDrop = listDiv.querySelector('.smgp-empty-drop');
                if (emptyDrop) {
                    emptyDrop.classList.add('drag-over');
                }
            }
        });
        listDiv.addEventListener('dragleave', function(e) {
            if (!listDiv.contains(e.relatedTarget)) {
                var emptyDrop = listDiv.querySelector('.smgp-empty-drop');
                if (emptyDrop) {
                    emptyDrop.classList.remove('drag-over');
                }
            }
        });
        listDiv.addEventListener('drop', function(e) {
            if (dragState.type !== 'activity') {
                return;
            }
            // Only handle if dropped on the list container (not on an activity row which handles its own drop).
            var targetRow = e.target.closest('.smgp-activity-row');
            if (targetRow) {
                return; // Let the row's drop handler deal with it.
            }
            e.preventDefault();
            e.stopPropagation();
            var items = collectDragItems();
            items.forEach(function(item) {
                sections[sectionIndex].activities.push(item);
            });
            selectedActivities = [];
            renderAll();
            syncToHidden();
        });

        card.appendChild(listDiv);

        // === Add activity button ===
        var addActBtn = document.createElement('button');
        addActBtn.type = 'button';
        addActBtn.className = 'smgp-add-activity-btn';
        addActBtn.innerHTML = '<span>+</span> Añadir actividad';
        addActBtn.addEventListener('click', function() {
            showActivityPicker(sectionIndex);
        });
        card.appendChild(addActBtn);

        // === Section drag handlers ===
        card.addEventListener('dragstart', function(e) {
            if (e.target !== card) {
                return;
            }
            dragState = {type: 'section', srcSection: sectionIndex, srcActivity: null};
            card.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', 'section:' + sectionIndex);
        });
        card.addEventListener('dragover', function(e) {
            if (dragState.type === 'section') {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'move';
                card.classList.add('drag-over');
            }
        });
        card.addEventListener('dragleave', function(e) {
            if (!card.contains(e.relatedTarget)) {
                card.classList.remove('drag-over');
            }
        });
        card.addEventListener('drop', function(e) {
            if (dragState.type === 'section' && dragState.srcSection !== sectionIndex) {
                e.preventDefault();
                card.classList.remove('drag-over');
                var item = sections.splice(dragState.srcSection, 1)[0];
                sections.splice(sectionIndex, 0, item);
                renderAll();
                syncToHidden();
            }
        });
        card.addEventListener('dragend', function() {
            card.classList.remove('dragging');
            mainContainer.querySelectorAll('.drag-over').forEach(function(el) {
                el.classList.remove('drag-over');
            });
            dragState = {type: null, srcSection: null, srcActivity: null};
        });

        return card;
    }

    function createActivityRow(act, actIndex, sectionIndex) {
        var row = document.createElement('div');
        row.className = 'smgp-activity-row';
        row.draggable = true;
        row.dataset.actIndex = actIndex;
        row.dataset.sectionIndex = sectionIndex;

        // Ctrl+click to select multiple activities.
        row.addEventListener('click', function(e) {
            if (e.ctrlKey || e.metaKey) {
                e.preventDefault();
                row.classList.toggle('smgp-act-selected');
                var key = sectionIndex + ':' + actIndex;
                var idx = selectedActivities.indexOf(key);
                if (idx === -1) {
                    selectedActivities.push(key);
                } else {
                    selectedActivities.splice(idx, 1);
                }
            }
        });

        // Handle.
        var handle = document.createElement('span');
        handle.className = 'smgp-act-handle';
        handle.innerHTML = '&#x2630;';
        row.appendChild(handle);

        // Icon.
        var iconDiv = document.createElement('div');
        iconDiv.className = 'smgp-act-icon';
        if (act.modicon) {
            var img = document.createElement('img');
            img.src = act.modicon;
            img.alt = act.modname;
            iconDiv.appendChild(img);
        } else if (act.iconClass) {
            var iconEl = document.createElement('i');
            iconEl.className = act.iconClass;
            iconEl.style.cssText = 'color:#10b981;font-size:0.85rem;';
            iconDiv.appendChild(iconEl);
        }
        row.appendChild(iconDiv);

        // Name + module label.
        var infoDiv = document.createElement('div');
        infoDiv.className = 'smgp-act-info';
        var nameSpan = document.createElement('span');
        nameSpan.className = 'smgp-act-name';
        nameSpan.textContent = act.name;
        nameSpan.addEventListener('click', function() {
            startEditName(nameSpan, act.name, function(newName) {
                act.name = newName;
                syncToHidden();
            });
        });
        infoDiv.appendChild(nameSpan);
        if (act.modname) {
            var modLabel = document.createElement('div');
            modLabel.className = 'smgp-act-modlabel';
            modLabel.textContent = act.modname;
            infoDiv.appendChild(modLabel);
        }
        row.appendChild(infoDiv);

        // Toggle (skip only when activity is in the SP palette, not when dragged into a course section).
        if (sectionIndex !== -1) {
            var toggle = createToggle(act.checked, function(checked) {
                act.checked = checked;
                syncToHidden();
            });
            row.appendChild(toggle);
        }

        // Activity drag handlers.
        row.addEventListener('dragstart', function(e) {
            e.stopPropagation();
            // If this row is part of a multi-selection, drag all selected.
            var key = sectionIndex + ':' + actIndex;
            if (selectedActivities.length > 0 && selectedActivities.indexOf(key) === -1) {
                // Dragging a non-selected row clears selection.
                selectedActivities = [];
                mainContainer.querySelectorAll('.smgp-act-selected').forEach(function(el) {
                    el.classList.remove('smgp-act-selected');
                });
            }
            dragState = {type: 'activity', srcSection: sectionIndex, srcActivity: actIndex, multi: selectedActivities.slice()};
            row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', 'activity:' + sectionIndex + ':' + actIndex);
        });
        row.addEventListener('dragover', function(e) {
            if (dragState.type === 'activity') {
                e.preventDefault();
                e.stopPropagation();
                e.dataTransfer.dropEffect = 'move';
                row.classList.add('drag-over');
            }
        });
        row.addEventListener('dragleave', function() {
            row.classList.remove('drag-over');
        });
        row.addEventListener('drop', function(e) {
            e.preventDefault();
            e.stopPropagation();
            row.classList.remove('drag-over');
            if (dragState.type === 'activity') {
                var items = collectDragItems();
                if (sectionIndex >= 0) {
                    items.forEach(function(item, i) {
                        sections[sectionIndex].activities.splice(actIndex + i, 0, item);
                    });
                }
                selectedActivities = [];
                renderAll();
                syncToHidden();
            }
        });
        row.addEventListener('dragend', function() {
            row.classList.remove('dragging');
            mainContainer.querySelectorAll('.drag-over').forEach(function(el) {
                el.classList.remove('drag-over');
            });
            dragState = {type: null, srcSection: null, srcActivity: null};
        });

        return row;
    }

    // ============================================================
    // Toggle switch helper
    // ============================================================

    function createToggle(checked, onChange) {
        var label = document.createElement('label');
        label.className = 'smgp-toggle';
        var input = document.createElement('input');
        input.type = 'checkbox';
        input.checked = checked;
        input.addEventListener('change', function() {
            onChange(input.checked);
        });
        var slider = document.createElement('span');
        slider.className = 'smgp-toggle__slider';
        label.appendChild(input);
        label.appendChild(slider);
        return label;
    }

    /**
     * Collect items being dragged (single or multi-select).
     * Removes items from source sections (or copies from SP palette).
     */
    function collectDragItems() {
        var items = [];
        var multiKeys = dragState.multi || [];

        if (multiKeys.length > 1) {
            // Multi-select: collect all selected items, sorted by section then index descending
            // (reverse order so splice indices stay valid).
            var parsed = multiKeys.map(function(k) {
                var parts = k.split(':');
                return {sec: parseInt(parts[0]), act: parseInt(parts[1])};
            }).sort(function(a, b) {
                return a.sec !== b.sec ? a.sec - b.sec : b.act - a.act;
            });
            // Collect items (remove from source in reverse order).
            var collected = [];
            parsed.forEach(function(p) {
                if (p.sec === -1) {
                    var copy = JSON.parse(JSON.stringify(spActivities[p.act]));
                    copy.actKey = copy.actKey + '_' + Date.now() + '_' + p.act;
                    collected.push(copy);
                } else if (sections[p.sec]) {
                    collected.push(sections[p.sec].activities.splice(p.act, 1)[0]);
                }
            });
            items = collected.reverse(); // Restore original order.
        } else {
            // Single drag.
            var srcSec = dragState.srcSection;
            var srcAct = dragState.srcActivity;
            if (srcSec === -1) {
                var copy = JSON.parse(JSON.stringify(spActivities[srcAct]));
                copy.actKey = copy.actKey + '_' + Date.now();
                items.push(copy);
            } else if (sections[srcSec]) {
                items.push(sections[srcSec].activities.splice(srcAct, 1)[0]);
            }
        }
        return items;
    }

    // ============================================================
    // Inline name editing
    // ============================================================

    function startEditName(spanEl, currentValue, onSave) {
        var input = document.createElement('input');
        input.type = 'text';
        input.className = spanEl.className.replace(/__name(?!-)/, '__name-input');
        input.value = currentValue;
        spanEl.replaceWith(input);
        input.focus();
        input.select();

        function finish() {
            var newVal = input.value.trim() || currentValue;
            onSave(newVal);
            var newSpan = document.createElement('span');
            newSpan.className = spanEl.className;
            newSpan.textContent = newVal;
            newSpan.addEventListener('click', function() {
                startEditName(newSpan, newVal, onSave);
            });
            input.replaceWith(newSpan);
        }

        input.addEventListener('blur', finish);
        input.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                input.blur();
            }
            if (e.key === 'Escape') {
                input.value = currentValue;
                input.blur();
            }
        });
    }

    // ============================================================
    // Activity picker
    // ============================================================

    function showActivityPicker(sectionIndex) {
        var existing = document.querySelector('.smgp-act-picker-overlay');
        if (existing) {
            existing.remove();
        }

        var activityTypes = [
            {mod: 'genially', name: 'Genially', icon: 'icon-presentation'},
            {mod: 'video', name: 'Video', icon: 'icon-film'},
            {mod: 'resource', name: 'Archivo', icon: 'icon-file-up'},
            {mod: 'label', name: 'Área de texto y medios', icon: 'icon-type'},
            {mod: 'data', name: 'Base de datos', icon: 'icon-database'},
            {mod: 'folder', name: 'Carpeta', icon: 'icon-folder'},
            {mod: 'choice', name: 'Consulta', icon: 'icon-circle-check'},
            {mod: 'quiz', name: 'Cuestionario', icon: 'icon-circle-help'},
            {mod: 'survey', name: 'Encuesta', icon: 'icon-clipboard-check'},
            {mod: 'feedback', name: 'Feedback', icon: 'icon-message-square-text'},
            {mod: 'forum', name: 'Foro', icon: 'icon-message-circle'},
            {mod: 'glossary', name: 'Glosario', icon: 'icon-notebook-text'},
            {mod: 'h5pactivity', name: 'H5P', icon: 'icon-circle-play'},
            {mod: 'lti', name: 'Herramienta externa', icon: 'icon-external-link'},
            {mod: 'iomadcertificate', name: 'IOMAD Certificate', icon: 'icon-award'},
            {mod: 'lesson', name: 'Lección', icon: 'icon-graduation-cap'},
            {mod: 'book', name: 'Libro', icon: 'icon-book-open'},
            {mod: 'page', name: 'Página', icon: 'icon-file-text'},
            {mod: 'imscp', name: 'Paquete IMS', icon: 'icon-package'},
            {mod: 'scorm', name: 'Paquete SCORM', icon: 'icon-box'},
            {mod: 'workshop', name: 'Taller', icon: 'icon-users'},
            {mod: 'assign', name: 'Tarea', icon: 'icon-file-text'},
            {mod: 'trainingevent', name: 'Training event', icon: 'icon-video'},
            {mod: 'url', name: 'URL', icon: 'icon-link'},
            {mod: 'wiki', name: 'Wiki', icon: 'icon-book-open'},
        ];

        var overlay = document.createElement('div');
        overlay.className = 'smgp-act-picker-overlay';
        overlay.style.cssText = 'position:fixed;inset:0;background:rgba(0,0,0,0.35);z-index:9999;display:flex;align-items:center;justify-content:center;backdrop-filter:blur(2px);';

        var modal = document.createElement('div');
        modal.style.cssText = 'background:#fff;border-radius:16px;padding:1.75rem;max-width:680px;width:94%;box-shadow:0 25px 60px rgba(0,0,0,0.2);';

        var title = document.createElement('h4');
        title.style.cssText = 'margin:0 0 1.25rem;font-weight:600;color:#1e293b;font-size:1.05rem;display:flex;align-items:center;gap:0.4rem;';
        title.innerHTML = '<i class="icon-plus-circle" style="color:' + GREEN + ';"></i> Añadir actividad';
        modal.appendChild(title);

        var grid = document.createElement('div');
        grid.style.cssText = 'display:grid;grid-template-columns:repeat(5,1fr);gap:0.5rem;';

        activityTypes.forEach(function(at) {
            var btn = document.createElement('button');
            btn.type = 'button';
            btn.style.cssText = 'display:flex;flex-direction:column;align-items:center;gap:0.35rem;padding:0.85rem 0.5rem;'
                + 'border:1.5px solid #e5e7eb;border-radius:10px;background:#fff;cursor:pointer;transition:all 0.15s;'
                + 'font-size:0.78rem;color:#475569;font-weight:500;';
            btn.innerHTML = '<i class="' + at.icon + '" style="font-size:1.4rem;color:' + GREEN + ';"></i>' + at.name;
            btn.addEventListener('mouseenter', function() {
                btn.style.borderColor = GREEN;
                btn.style.background = 'rgba(16,185,129,0.04)';
                btn.style.transform = 'translateY(-1px)';
            });
            btn.addEventListener('mouseleave', function() {
                btn.style.borderColor = '#e5e7eb';
                btn.style.background = '#fff';
                btn.style.transform = 'none';
            });
            btn.addEventListener('click', function() {
                sections[sectionIndex].activities.push({
                    name: at.name,
                    origName: '',
                    actKey: 'smgp_new_act_' + Date.now(),
                    modname: at.name,
                    modicon: '',
                    el: null,
                    checked: true
                });
                overlay.remove();
                renderAll();
                syncToHidden();
            });
            grid.appendChild(btn);
        });
        modal.appendChild(grid);

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.style.cssText = 'margin-top:1rem;width:100%;padding:0.55rem;border:1.5px solid #e5e7eb;border-radius:8px;'
            + 'background:#fff;color:#64748b;cursor:pointer;font-weight:500;font-size:0.85rem;transition:all 0.15s;';
        cancelBtn.textContent = 'Cancelar';
        cancelBtn.addEventListener('mouseenter', function() { cancelBtn.style.borderColor = '#94a3b8'; });
        cancelBtn.addEventListener('mouseleave', function() { cancelBtn.style.borderColor = '#e5e7eb'; });
        cancelBtn.addEventListener('click', function() { overlay.remove(); });
        modal.appendChild(cancelBtn);

        overlay.appendChild(modal);
        overlay.addEventListener('click', function(e) { if (e.target === overlay) { overlay.remove(); } });
        document.body.appendChild(overlay);
    }

    // ============================================================
    // Data sync
    // ============================================================

    function syncToHidden() {
        if (!hiddenInput) {
            return;
        }
        var data = sections.map(function(sec) {
            return {
                name: sec.name,
                origName: sec.origName,
                sectionKey: sec.sectionKey,
                checked: sec.checked,
                activities: sec.activities.map(function(act) {
                    return {
                        name: act.name,
                        origName: act.origName,
                        actKey: act.actKey,
                        modname: act.modname,
                        checked: act.checked
                    };
                })
            };
        });
        hiddenInput.value = JSON.stringify(data);
    }

    return {
        init: init
    };
});
