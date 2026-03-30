/**
 * Learning objectives drag-and-drop editor for the course edit form.
 *
 * Reads/writes a JSON array from/to a hidden input [name="smgp_objectives_data"].
 * Renders an interactive list inside #smgp-objectives-container with:
 *   - Drag handle for reordering (HTML5 Drag and Drop)
 *   - Text input per objective
 *   - Remove button per row
 *   - "Add objective" button at the bottom
 *
 * @module     local_sm_graphics_plugin/course_objectives
 * @package    local_sm_graphics_plugin
 * @copyright  2026 SmartMind Technologies
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define(['core/str'], function(Str) {

    var objectives = [];
    var container = null;
    var hiddenInput = null;
    var dragSrcIndex = null;
    var strings = {};

    /**
     * Load lang strings then initialize the editor.
     */
    function init() {
        container = document.getElementById('smgp-objectives-container');
        hiddenInput = document.querySelector('input[name="smgp_objectives_data"]');
        if (!container || !hiddenInput) {
            return;
        }

        // Parse existing objectives from the hidden input.
        try {
            var parsed = JSON.parse(hiddenInput.value);
            if (Array.isArray(parsed)) {
                objectives = parsed;
            }
        } catch (e) {
            objectives = [];
        }

        // Load lang strings then render.
        Str.get_strings([
            {key: 'objectives_add', component: 'local_sm_graphics_plugin'},
            {key: 'objectives_placeholder', component: 'local_sm_graphics_plugin'},
            {key: 'objectives_remove', component: 'local_sm_graphics_plugin'},
            {key: 'objectives_drag', component: 'local_sm_graphics_plugin'}
        ]).then(function(results) {
            strings.add = results[0];
            strings.placeholder = results[1];
            strings.remove = results[2];
            strings.drag = results[3];
            render();
            return null;
        }).catch(function() {
            // Fallback strings.
            strings.add = 'Add objective';
            strings.placeholder = 'Type a learning objective...';
            strings.remove = 'Remove';
            strings.drag = 'Drag to reorder';
            render();
        });
    }

    /**
     * Render the full editor UI.
     */
    function render() {
        container.innerHTML = '';

        objectives.forEach(function(text, index) {
            var row = createRow(text, index);
            container.appendChild(row);
        });

        // Add button.
        var addBtn = document.createElement('button');
        addBtn.type = 'button';
        addBtn.className = 'smgp-objectives-editor__add';
        addBtn.innerHTML = '<span style="font-size:1.1em;">+</span> ' + strings.add;
        addBtn.addEventListener('click', function() {
            objectives.push('');
            render();
            // Focus the new input.
            var inputs = container.querySelectorAll('.smgp-objectives-editor__input');
            if (inputs.length) {
                inputs[inputs.length - 1].focus();
            }
        });
        container.appendChild(addBtn);

        syncToHidden();
    }

    /**
     * Create a single objective row element.
     *
     * @param {string} text  The objective text.
     * @param {number} index The array index.
     * @return {HTMLElement}
     */
    function createRow(text, index) {
        var row = document.createElement('div');
        row.className = 'smgp-objectives-editor__row';
        row.draggable = true;
        row.dataset.index = index;

        // Drag handle.
        var handle = document.createElement('span');
        handle.className = 'smgp-objectives-editor__handle';
        handle.title = strings.drag;
        handle.innerHTML = '&#x2630;'; // Hamburger icon (☰).
        row.appendChild(handle);

        // Text input.
        var input = document.createElement('input');
        input.type = 'text';
        input.className = 'smgp-objectives-editor__input';
        input.value = text;
        input.placeholder = strings.placeholder;
        input.addEventListener('input', function() {
            objectives[index] = input.value;
            syncToHidden();
        });
        row.appendChild(input);

        // Remove button.
        var removeBtn = document.createElement('button');
        removeBtn.type = 'button';
        removeBtn.className = 'smgp-objectives-editor__remove';
        removeBtn.title = strings.remove;
        removeBtn.innerHTML = '&times;';
        removeBtn.addEventListener('click', function() {
            objectives.splice(index, 1);
            render();
        });
        row.appendChild(removeBtn);

        // Drag and drop handlers.
        row.addEventListener('dragstart', function(e) {
            dragSrcIndex = index;
            row.classList.add('dragging');
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(index));
        });

        row.addEventListener('dragover', function(e) {
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            row.classList.add('drag-over');
        });

        row.addEventListener('dragleave', function() {
            row.classList.remove('drag-over');
        });

        row.addEventListener('drop', function(e) {
            e.preventDefault();
            row.classList.remove('drag-over');
            var targetIndex = index;
            if (dragSrcIndex !== null && dragSrcIndex !== targetIndex) {
                // Move item from dragSrcIndex to targetIndex.
                var item = objectives.splice(dragSrcIndex, 1)[0];
                objectives.splice(targetIndex, 0, item);
                render();
            }
        });

        row.addEventListener('dragend', function() {
            row.classList.remove('dragging');
            // Clean up any lingering drag-over classes.
            container.querySelectorAll('.drag-over').forEach(function(el) {
                el.classList.remove('drag-over');
            });
            dragSrcIndex = null;
        });

        return row;
    }

    /**
     * Serialize objectives array back to the hidden input.
     */
    function syncToHidden() {
        if (hiddenInput) {
            hiddenInput.value = JSON.stringify(objectives);
        }
    }

    return {
        init: init
    };
});
