/**
 * Rich Editor — Custom contenteditable-based rich text editor.
 *
 * Provides a TipTap-like experience using execCommand + Selection/Range API.
 * Used for course comments with toolbar, @mentions, and activity tags.
 *
 * @module     local_sm_graphics_plugin/rich_editor
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([], function() {

    /**
     * Create a new RichEditor instance.
     *
     * @param {HTMLElement} container Container element to build inside.
     * @param {Object} options Configuration options.
     * @param {string} [options.placeholder='Write a comment...'] Placeholder text.
     * @param {number} [options.maxLength=5000] Max content length.
     * @param {boolean} [options.showMentionButton=true] Show @ mention button.
     * @param {boolean} [options.showActivityTagButton=false] Show & activity tag button.
     * @param {string} [options.activityTagLabel=''] Label for the tag button.
     * @param {Function} [options.onMentionClick] Callback when @ button is clicked.
     * @param {Function} [options.onActivityTagClick] Callback when & button is clicked.
     * @param {Function} [options.onChange] Callback on content change.
     * @param {Function} [options.onSubmit] Callback on Ctrl+Enter.
     * @return {Object} Editor instance with public API.
     */
    var create = function(container, options) {
        options = options || {};
        var placeholder = options.placeholder || 'Write a comment...';
        var maxLength = options.maxLength || 5000;
        var showMentionBtn = options.showMentionButton !== false;
        var showTagBtn = options.showActivityTagButton || false;

        // Build the editor DOM.
        var wrapper = document.createElement('div');
        wrapper.className = 'smgp-editor';

        // Toolbar.
        var toolbar = document.createElement('div');
        toolbar.className = 'smgp-editor__toolbar';

        var toolbarButtons = [
            {cmd: 'bold', icon: 'icon-bold', title: 'Bold'},
            {cmd: 'italic', icon: 'icon-italic', title: 'Italic'},
            {cmd: 'strikethrough', icon: 'icon-strikethrough', title: 'Strikethrough'},
            {cmd: 'separator'},
            {cmd: 'insertUnorderedList', icon: 'icon-list', title: 'Bullet list'},
            {cmd: 'insertOrderedList', icon: 'icon-list-ordered', title: 'Numbered list'},
            {cmd: 'blockquote', icon: 'icon-quote', title: 'Blockquote'},
        ];

        var buttons = {};

        toolbarButtons.forEach(function(btn) {
            if (btn.cmd === 'separator') {
                var sep = document.createElement('span');
                sep.className = 'smgp-editor__separator';
                toolbar.appendChild(sep);
                return;
            }

            var el = document.createElement('button');
            el.type = 'button';
            el.className = 'smgp-editor__btn';
            el.title = btn.title;
            el.innerHTML = '<i class="' + btn.icon + '"></i>';
            el.setAttribute('data-cmd', btn.cmd);

            el.addEventListener('mousedown', function(e) {
                e.preventDefault(); // Prevent losing focus.
            });

            el.addEventListener('click', function(e) {
                e.preventDefault();
                contentEl.focus();
                if (btn.cmd === 'blockquote') {
                    document.execCommand('formatBlock', false, 'blockquote');
                } else {
                    document.execCommand(btn.cmd, false, null);
                }
                updateToolbarState();
                fireChange();
            });

            buttons[btn.cmd] = el;
            toolbar.appendChild(el);
        });

        // Separator before special buttons.
        if (showMentionBtn || showTagBtn) {
            var sep2 = document.createElement('span');
            sep2.className = 'smgp-editor__separator';
            toolbar.appendChild(sep2);
        }

        // @ Mention button.
        var mentionBtn = null;
        if (showMentionBtn) {
            mentionBtn = document.createElement('button');
            mentionBtn.type = 'button';
            mentionBtn.className = 'smgp-editor__btn smgp-editor__btn--mention';
            mentionBtn.title = '@Mention';
            mentionBtn.innerHTML = '<i class="icon-at-sign"></i>';
            mentionBtn.addEventListener('mousedown', function(e) {
                e.preventDefault();
            });
            mentionBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof options.onMentionClick === 'function') {
                    options.onMentionClick(mentionBtn);
                }
            });
            toolbar.appendChild(mentionBtn);
        }

        // & Activity tag button.
        var tagBtn = null;
        if (showTagBtn) {
            tagBtn = document.createElement('button');
            tagBtn.type = 'button';
            tagBtn.className = 'smgp-editor__btn smgp-editor__btn--tag';
            tagBtn.title = 'Activity tag';
            tagBtn.innerHTML = '<i class="icon-bookmark"></i>';
            if (options.activityTagLabel) {
                tagBtn.innerHTML += ' <span class="smgp-editor__btn-label">' + escapeHtml(options.activityTagLabel) + '</span>';
            }
            tagBtn.addEventListener('mousedown', function(e) {
                e.preventDefault();
            });
            tagBtn.addEventListener('click', function(e) {
                e.preventDefault();
                if (typeof options.onActivityTagClick === 'function') {
                    options.onActivityTagClick(tagBtn);
                }
            });
            toolbar.appendChild(tagBtn);
        }

        wrapper.appendChild(toolbar);

        // Content area.
        var contentEl = document.createElement('div');
        contentEl.className = 'smgp-editor__content';
        contentEl.contentEditable = 'true';
        contentEl.setAttribute('role', 'textbox');
        contentEl.setAttribute('aria-multiline', 'true');
        contentEl.setAttribute('data-placeholder', placeholder);

        contentEl.addEventListener('input', function() {
            // Enforce max length.
            var text = contentEl.textContent || '';
            if (text.length > maxLength) {
                contentEl.textContent = text.substring(0, maxLength);
                // Move caret to end.
                placeCaretAtEnd(contentEl);
            }
            updateToolbarState();
            fireChange();
        });

        contentEl.addEventListener('keydown', function(e) {
            // Ctrl/Cmd + Enter = submit.
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                if (typeof options.onSubmit === 'function') {
                    options.onSubmit();
                }
            }
        });

        contentEl.addEventListener('paste', function(e) {
            e.preventDefault();
            var text = (e.clipboardData || window.clipboardData).getData('text/plain');
            document.execCommand('insertText', false, text);
        });

        wrapper.appendChild(contentEl);
        container.appendChild(wrapper);

        // --- Internal helpers ---

        function updateToolbarState() {
            ['bold', 'italic', 'strikethrough', 'insertUnorderedList', 'insertOrderedList'].forEach(function(cmd) {
                if (buttons[cmd]) {
                    if (document.queryCommandState(cmd)) {
                        buttons[cmd].classList.add('smgp-editor__btn--active');
                    } else {
                        buttons[cmd].classList.remove('smgp-editor__btn--active');
                    }
                }
            });
        }

        function fireChange() {
            if (typeof options.onChange === 'function') {
                options.onChange(contentEl.innerHTML);
            }
        }

        function placeCaretAtEnd(el) {
            var range = document.createRange();
            range.selectNodeContents(el);
            range.collapse(false);
            var sel = window.getSelection();
            sel.removeAllRanges();
            sel.addRange(range);
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.textContent = str;
            return div.innerHTML;
        }

        /**
         * Save the current selection/range so we can restore it later.
         */
        var savedRange = null;

        function saveSelection() {
            var sel = window.getSelection();
            if (sel.rangeCount > 0) {
                savedRange = sel.getRangeAt(0).cloneRange();
            }
        }

        function restoreSelection() {
            if (savedRange) {
                var sel = window.getSelection();
                sel.removeAllRanges();
                sel.addRange(savedRange);
            }
        }

        contentEl.addEventListener('blur', function() {
            saveSelection();
        });

        // --- Public API ---

        return {
            /** Get HTML content. */
            getContent: function() {
                return contentEl.innerHTML;
            },

            /** Set HTML content. */
            setContent: function(html) {
                contentEl.innerHTML = html;
                fireChange();
            },

            /** Clear the editor. */
            clear: function() {
                contentEl.innerHTML = '';
                fireChange();
            },

            /** Focus the editor. */
            focus: function() {
                contentEl.focus();
            },

            /**
             * Insert an @mention at the current cursor position.
             *
             * @param {number} userId User ID.
             * @param {string} userName User display name.
             */
            insertMention: function(userId, userName) {
                contentEl.focus();
                restoreSelection();

                var span = document.createElement('span');
                span.className = 'smgp-mention';
                span.setAttribute('data-user-id', userId);
                span.contentEditable = 'false';
                span.textContent = '@' + userName;

                var sel = window.getSelection();
                if (sel.rangeCount > 0) {
                    var range = sel.getRangeAt(0);
                    range.deleteContents();
                    range.insertNode(span);

                    // Insert a zero-width space after the mention for continued typing.
                    var spacer = document.createTextNode('\u200B ');
                    range.setStartAfter(span);
                    range.insertNode(spacer);
                    range.setStartAfter(spacer);
                    range.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                fireChange();
            },

            /**
             * Insert an activity tag at the current cursor position.
             *
             * @param {number} cmid Course module ID.
             * @param {number} position Position index.
             * @param {string} type Activity type.
             * @param {string} label Display label.
             */
            insertActivityTag: function(cmid, position, type, label) {
                contentEl.focus();
                restoreSelection();

                var span = document.createElement('span');
                span.className = 'smgp-activity-tag';
                span.setAttribute('data-cmid', cmid);
                span.setAttribute('data-position', position);
                span.setAttribute('data-type', type);
                span.contentEditable = 'false';
                span.textContent = label;

                var sel = window.getSelection();
                if (sel.rangeCount > 0) {
                    var range = sel.getRangeAt(0);
                    range.deleteContents();
                    range.insertNode(span);

                    var spacer = document.createTextNode('\u200B ');
                    range.setStartAfter(span);
                    range.insertNode(spacer);
                    range.setStartAfter(spacer);
                    range.collapse(true);
                    sel.removeAllRanges();
                    sel.addRange(range);
                }
                fireChange();
            },

            /** Get reference to the mention button for positioning. */
            getMentionButton: function() {
                return mentionBtn;
            },

            /** Get reference to the content element. */
            getContentElement: function() {
                return contentEl;
            },

            /** Get reference to the wrapper element. */
            getWrapper: function() {
                return wrapper;
            },

            /** Save current selection (for restoring after dropdown interaction). */
            saveSelection: saveSelection,

            /** Restore saved selection. */
            restoreSelection: restoreSelection,

            /** Destroy the editor. */
            destroy: function() {
                if (wrapper.parentNode) {
                    wrapper.parentNode.removeChild(wrapper);
                }
            }
        };
    };

    return {
        create: create
    };
});
