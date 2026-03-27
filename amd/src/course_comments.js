/**
 * Course Comments — Main orchestrator for Udemy-style threaded comments.
 *
 * Features: CRUD, threading, @mentions, activity tags, position detection,
 * sort, pagination, inline reply, edit modal, delete confirmation.
 *
 * @module     local_sm_graphics_plugin/course_comments
 * @copyright  2026 SmartMind
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
define([
    'core/ajax',
    'core/str',
    'local_sm_graphics_plugin/rich_editor'
], function(Ajax, Str, RichEditor) {

    // --- State ---
    var state = {
        courseid: 0,
        cmid: 0,
        activityName: '',
        activityType: '',
        counterLabel: '',
        isVideo: false,
        canPost: false,
        canDeleteAny: false,
        currentUserId: 0,
        currentUserFullname: '',
        sortOrder: 'newest',
        comments: [],
        page: 0,
        perpage: 20,
        totalComments: 0,
        editor: null,
        replyEditors: {},
        replyingTo: null,
        editingComment: null,
        positionIndex: 0,
        positionTimestamp: 0,
        inCoursePage: false,
        // String cache.
        strings: {}
    };

    var container = null;
    var commentsList = null;

    // --- Initialization ---

    var init = function() {
        var source = document.getElementById('smgp-comments-source');
        if (!source) {
            return;
        }

        // Read context data attributes from the source container.
        var inner = source.querySelector('.smgp-comments');
        if (!inner) {
            return;
        }

        state.courseid = parseInt(inner.getAttribute('data-courseid'), 10) || 0;
        state.cmid = parseInt(inner.getAttribute('data-cmid'), 10) || 0;
        state.activityName = inner.getAttribute('data-activityname') || '';
        state.activityType = inner.getAttribute('data-activitytype') || '';
        state.canPost = inner.getAttribute('data-canpost') === '1';
        state.canDeleteAny = inner.getAttribute('data-candeleteany') === '1';
        state.currentUserId = parseInt(inner.getAttribute('data-userid'), 10) || 0;
        state.currentUserFullname = inner.getAttribute('data-userfullname') || '';
        state.inCoursePage = !!document.getElementById('smgp-course-page-comments-target');

        if (!state.courseid) {
            return;
        }

        // Find injection point.
        // If inside the course page comments tab, the source is already placed there.
        var coursePageTarget = document.getElementById('smgp-course-page-comments-target');
        if (coursePageTarget && coursePageTarget.contains(source)) {
            // Already positioned inside the course page tab — just make it visible.
            source.id = 'smgp-comments-container';
        } else {
            var target = document.querySelector('.course-content') ||
                         document.getElementById('region-main-box') ||
                         document.getElementById('region-main');
            if (!target) {
                return;
            }

            // Move the comments container from the hidden source to the visible location.
            source.style.display = '';
            source.removeAttribute('style');
            target.parentNode.insertBefore(source, target.nextSibling);
            source.id = 'smgp-comments-container';
        }

        container = inner;
        commentsList = container.querySelector('.smgp-comments__list');

        // Detect activity position.
        detectPosition();

        // Load language strings.
        loadStrings().then(function() {
            // Initialize the editor.
            initEditor();

            // Bind events.
            bindEvents();

            // Fetch initial comments.
            fetchComments();
        });
    };

    /**
     * Load required language strings.
     */
    function loadStrings() {
        var keys = [
            {key: 'comments_newest', component: 'local_sm_graphics_plugin'},
            {key: 'comments_oldest', component: 'local_sm_graphics_plugin'},
            {key: 'comments_empty', component: 'local_sm_graphics_plugin'},
            {key: 'comments_load_more', component: 'local_sm_graphics_plugin'},
            {key: 'comments_post', component: 'local_sm_graphics_plugin'},
            {key: 'comments_post_reply', component: 'local_sm_graphics_plugin'},
            {key: 'comments_write', component: 'local_sm_graphics_plugin'},
            {key: 'comments_write_reply', component: 'local_sm_graphics_plugin'},
            {key: 'comments_edit', component: 'local_sm_graphics_plugin'},
            {key: 'comments_delete', component: 'local_sm_graphics_plugin'},
            {key: 'comments_delete_confirm', component: 'local_sm_graphics_plugin'},
            {key: 'comments_edited', component: 'local_sm_graphics_plugin'},
            {key: 'comments_reply', component: 'local_sm_graphics_plugin'},
            {key: 'comments_replies', component: 'local_sm_graphics_plugin'},
            {key: 'comments_just_now', component: 'local_sm_graphics_plugin'},
            {key: 'comments_minutes_ago', component: 'local_sm_graphics_plugin'},
            {key: 'comments_hours_ago', component: 'local_sm_graphics_plugin'},
            {key: 'comments_days_ago', component: 'local_sm_graphics_plugin'},
            {key: 'comments_search_users', component: 'local_sm_graphics_plugin'},
            {key: 'comments_no_users', component: 'local_sm_graphics_plugin'},
            {key: 'comments_slide', component: 'local_sm_graphics_plugin'},
            {key: 'comments_question', component: 'local_sm_graphics_plugin'},
            {key: 'comments_chapter', component: 'local_sm_graphics_plugin'},
            {key: 'comments_page', component: 'local_sm_graphics_plugin'},
        ];

        return Str.get_strings(keys).then(function(results) {
            keys.forEach(function(k, i) {
                state.strings[k.key] = results[i];
            });
        });
    }

    /**
     * Initialize the main comment editor.
     */
    function initEditor() {
        var editorContainer = container.querySelector('.smgp-comments__editor');
        if (!editorContainer || !state.canPost) {
            return;
        }

        // In course page context, always show the tag button (it updates dynamically).
        var showTagBtn = state.inCoursePage || (state.cmid > 0 && state.activityName);
        var tagLabel = buildTagLabel();

        state.editor = RichEditor.create(editorContainer, {
            placeholder: state.strings.comments_write || 'Write a comment...',
            maxLength: 5000,
            showMentionButton: true,
            showActivityTagButton: showTagBtn,
            activityTagLabel: tagLabel,
            onMentionClick: function(btn) {
                openMentionDropdown(state.editor, btn);
            },
            onActivityTagClick: function() {
                insertCurrentActivityTag(state.editor);
            },
            onSubmit: function() {
                submitComment();
            }
        });

        // Submit button.
        var submitBtn = container.querySelector('.smgp-comments__submit');
        if (submitBtn) {
            submitBtn.addEventListener('click', function(e) {
                e.preventDefault();
                submitComment();
            });
        }
    }

    /**
     * Bind global event listeners.
     */
    function bindEvents() {
        // Sort dropdown.
        var sortTrigger = container.querySelector('.smgp-comments__sort-trigger');
        var sortMenu = container.querySelector('.smgp-comments__sort-menu');
        var sortOptions = container.querySelectorAll('.smgp-comments__sort-option');

        if (sortTrigger && sortMenu) {
            sortTrigger.addEventListener('click', function(e) {
                e.preventDefault();
                e.stopPropagation();
                var isOpen = sortMenu.style.display !== 'none';
                sortMenu.style.display = isOpen ? 'none' : 'block';
                sortTrigger.classList.toggle('smgp-comments__sort-trigger--open', !isOpen);
            });

            sortOptions.forEach(function(opt) {
                opt.addEventListener('click', function(e) {
                    e.preventDefault();
                    var newSort = opt.getAttribute('data-sort');
                    sortMenu.style.display = 'none';
                    sortTrigger.classList.remove('smgp-comments__sort-trigger--open');

                    if (newSort !== state.sortOrder) {
                        state.sortOrder = newSort;
                        state.page = 0;
                        sortOptions.forEach(function(o) { o.classList.remove('smgp-comments__sort-option--active'); });
                        opt.classList.add('smgp-comments__sort-option--active');
                        var label = sortTrigger.querySelector('.smgp-comments__sort-trigger-label');
                        if (label) {
                            label.textContent = opt.textContent.trim();
                        }
                        fetchComments();
                    }
                });
            });

            // Close dropdown on outside click.
            document.addEventListener('click', function(e) {
                if (!sortTrigger.contains(e.target) && !sortMenu.contains(e.target)) {
                    sortMenu.style.display = 'none';
                    sortTrigger.classList.remove('smgp-comments__sort-trigger--open');
                }
            });
        }

        // Load more.
        var loadMoreBtn = container.querySelector('.smgp-comments__load-more');
        if (loadMoreBtn) {
            loadMoreBtn.addEventListener('click', function(e) {
                e.preventDefault();
                state.page++;
                fetchComments(true);
            });
        }

        // Delegated clicks on comment actions.
        if (commentsList) {
            commentsList.addEventListener('click', function(e) {
                var target = e.target.closest('[data-action]');
                if (!target) {
                    // Check for mention click.
                    var mention = e.target.closest('.smgp-mention');
                    if (mention) {
                        e.preventDefault();
                        handleMentionClick(mention);
                        return;
                    }
                    // Check for activity tag click.
                    var tag = e.target.closest('.smgp-activity-tag');
                    if (tag) {
                        e.preventDefault();
                        handleActivityTagClick(tag);
                        return;
                    }
                    // Check for position badge click.
                    var badge = e.target.closest('.smgp-comment__position-badge--clickable');
                    if (badge) {
                        e.preventDefault();
                        handlePositionBadgeClick(badge);
                        return;
                    }
                    return;
                }

                e.preventDefault();
                var action = target.getAttribute('data-action');
                var commentId = parseInt(target.getAttribute('data-comment-id'), 10);

                switch (action) {
                    case 'reply':
                        openReplyEditor(commentId);
                        break;
                    case 'edit':
                        openEditModal(commentId);
                        break;
                    case 'delete':
                        openDeleteModal(commentId);
                        break;
                    case 'toggle-replies':
                        toggleReplies(commentId);
                        break;
                }
            });
        }

        // Close modals on overlay click.
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('smgp-comments__modal-overlay')) {
                closeAllModals();
            }
            // Close mention dropdown on outside click.
            var dropdown = container.querySelector('.smgp-mention-dropdown');
            if (dropdown && dropdown.style.display !== 'none') {
                if (!dropdown.contains(e.target) && !e.target.closest('.smgp-editor__btn--mention')) {
                    dropdown.style.display = 'none';
                }
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeAllModals();
                var dropdown = container.querySelector('.smgp-mention-dropdown');
                if (dropdown) {
                    dropdown.style.display = 'none';
                }
            }
        });
    }

    // --- AJAX Operations ---

    /**
     * Fetch comments from server.
     * @param {boolean} append Whether to append to existing list.
     */
    function fetchComments(append) {
        if (!commentsList) {
            return;
        }

        if (!append) {
            showLoading();
        }

        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_get_comments',
            args: {
                courseid: state.courseid,
                parentid: 0,
                cmid: 0,
                sort: state.sortOrder,
                page: state.page,
                perpage: state.perpage
            }
        }])[0].done(function(result) {
            state.totalComments = result.total;

            if (!append) {
                commentsList.innerHTML = '';
            }

            if (result.comments.length === 0 && !append) {
                showEmpty();
            } else {
                hideEmpty();
                result.comments.forEach(function(c) {
                    var el = buildCommentElement(c);
                    commentsList.appendChild(el);
                });
            }

            hideLoading();
            updateLoadMoreVisibility();
            updateCommentCount();
        }).fail(function(err) {
            hideLoading();
            // eslint-disable-next-line no-console
            console.error('Failed to fetch comments:', err);
        });
    }

    /**
     * Submit a new top-level comment.
     */
    function submitComment() {
        if (!state.editor) {
            return;
        }

        var content = state.editor.getContent();
        if (!content || !content.trim() || isEmptyHtml(content)) {
            return;
        }

        var submitBtn = container.querySelector('.smgp-comments__submit');
        if (submitBtn) {
            submitBtn.disabled = true;
        }

        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_add_comment',
            args: {
                courseid: state.courseid,
                content: content,
                parentid: 0,
                cmid: state.cmid,
                positionindex: state.positionIndex,
                positiontimestamp: state.positionTimestamp,
                activityname: state.activityName,
                activitytype: state.activityType
            }
        }])[0].done(function(result) {
            state.editor.clear();
            if (submitBtn) {
                submitBtn.disabled = false;
            }

            // Build new comment data.
            var newComment = {
                id: result.id,
                courseid: state.courseid,
                userid: state.currentUserId,
                parentid: 0,
                content: result.content,
                cmid: state.cmid,
                positionindex: state.positionIndex,
                positiontimestamp: state.positionTimestamp,
                activityname: state.activityName,
                activitytype: state.activityType,
                replycount: 0,
                timecreated: result.timecreated,
                timemodified: result.timecreated,
                userfullname: result.userfullname,
                userinitials: result.userinitials,
                edited: false
            };

            // Prepend to list.
            hideEmpty();
            var el = buildCommentElement(newComment);
            if (state.sortOrder === 'newest' && commentsList.firstChild) {
                commentsList.insertBefore(el, commentsList.firstChild);
            } else {
                commentsList.appendChild(el);
            }
            state.totalComments++;
            updateCommentCount();
        }).fail(function(err) {
            if (submitBtn) {
                submitBtn.disabled = false;
            }
            // eslint-disable-next-line no-console
            console.error('Failed to add comment:', err);
        });
    }

    /**
     * Submit a reply to a comment.
     * @param {number} parentId Parent comment ID.
     */
    function submitReply(parentId) {
        var editor = state.replyEditors[parentId];
        if (!editor) {
            return;
        }

        var content = editor.getContent();
        if (!content || !content.trim() || isEmptyHtml(content)) {
            return;
        }

        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_add_comment',
            args: {
                courseid: state.courseid,
                content: content,
                parentid: parentId,
                cmid: state.cmid,
                positionindex: 0,
                positiontimestamp: 0,
                activityname: state.activityName,
                activitytype: state.activityType
            }
        }])[0].done(function(result) {
            editor.destroy();
            delete state.replyEditors[parentId];

            var commentEl = commentsList.querySelector('[data-comment-id="' + parentId + '"]');
            if (!commentEl) {
                return;
            }

            // Remove reply form.
            var replyForm = commentEl.querySelector('.smgp-reply-form');
            if (replyForm) {
                replyForm.remove();
            }

            // Add reply to replies container.
            var repliesContainer = commentEl.querySelector('.smgp-comment-replies');
            if (!repliesContainer) {
                repliesContainer = document.createElement('div');
                repliesContainer.className = 'smgp-comment-replies';
                commentEl.querySelector('.smgp-comment__content').appendChild(repliesContainer);
            }

            var replyData = {
                id: result.id,
                courseid: state.courseid,
                userid: state.currentUserId,
                parentid: parentId,
                content: result.content,
                cmid: state.cmid,
                positionindex: 0,
                positiontimestamp: 0,
                activityname: '',
                activitytype: '',
                replycount: 0,
                timecreated: result.timecreated,
                timemodified: result.timecreated,
                userfullname: result.userfullname,
                userinitials: result.userinitials,
                edited: false
            };

            var replyEl = buildCommentElement(replyData, true);
            repliesContainer.appendChild(replyEl);

            // Update parent reply count display.
            var toggleBtn = commentEl.querySelector('[data-action="toggle-replies"]');
            var currentCount = parseInt(commentEl.getAttribute('data-replycount'), 10) || 0;
            currentCount++;
            commentEl.setAttribute('data-replycount', currentCount);
            if (toggleBtn) {
                var label = currentCount === 1 ? state.strings.comments_reply : state.strings.comments_replies;
                toggleBtn.textContent = currentCount + ' ' + label;
            }

            repliesContainer.style.display = '';
        }).fail(function(err) {
            // eslint-disable-next-line no-console
            console.error('Failed to add reply:', err);
        });
    }

    /**
     * Update a comment.
     * @param {number} commentId Comment ID.
     * @param {string} content New HTML content.
     */
    function updateCommentAjax(commentId, content) {
        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_update_comment',
            args: {
                commentid: commentId,
                content: content
            }
        }])[0].done(function(result) {
            closeAllModals();
            // Update the comment body in DOM.
            var commentEl = commentsList.querySelector('[data-comment-id="' + commentId + '"]');
            if (commentEl) {
                var body = commentEl.querySelector('.smgp-comment__body');
                if (body) {
                    body.innerHTML = result.content;
                }
                // Show edited badge.
                var header = commentEl.querySelector('.smgp-comment__header');
                if (header && !header.querySelector('.smgp-comment__edited')) {
                    var editedSpan = document.createElement('span');
                    editedSpan.className = 'smgp-comment__edited';
                    editedSpan.textContent = '(' + (state.strings.comments_edited || 'edited') + ')';
                    header.appendChild(editedSpan);
                }
            }
        }).fail(function(err) {
            // eslint-disable-next-line no-console
            console.error('Failed to update comment:', err);
        });
    }

    /**
     * Delete a comment.
     * @param {number} commentId Comment ID.
     */
    function deleteCommentAjax(commentId) {
        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_delete_comment',
            args: {commentid: commentId}
        }])[0].done(function() {
            closeAllModals();
            var commentEl = commentsList.querySelector('[data-comment-id="' + commentId + '"]');
            if (commentEl) {
                commentEl.remove();
                state.totalComments--;
                updateCommentCount();
                if (state.totalComments <= 0) {
                    showEmpty();
                }
            }
        }).fail(function(err) {
            // eslint-disable-next-line no-console
            console.error('Failed to delete comment:', err);
        });
    }

    // --- DOM Building ---

    /**
     * Build a comment element from data.
     * @param {Object} data Comment data from API.
     * @param {boolean} isReply Whether this is a reply.
     * @return {HTMLElement}
     */
    function buildCommentElement(data, isReply) {
        var el = document.createElement('div');
        el.className = 'smgp-comment' + (isReply ? ' smgp-comment--reply' : '');
        el.setAttribute('data-comment-id', data.id);
        el.setAttribute('data-replycount', data.replycount || 0);

        // Avatar.
        var avatar = document.createElement('div');
        avatar.className = 'smgp-comment__avatar';
        avatar.textContent = data.userinitials || '??';
        el.appendChild(avatar);

        // Content wrapper.
        var contentWrapper = document.createElement('div');
        contentWrapper.className = 'smgp-comment__content';

        // Header: name + time + edited.
        var header = document.createElement('div');
        header.className = 'smgp-comment__header';

        var authorSpan = document.createElement('span');
        authorSpan.className = 'smgp-comment__author';
        authorSpan.textContent = data.userfullname;
        header.appendChild(authorSpan);

        var dot = document.createElement('span');
        dot.className = 'smgp-comment__dot';
        dot.textContent = '\u00B7';
        header.appendChild(dot);

        var timeSpan = document.createElement('span');
        timeSpan.className = 'smgp-comment__time';
        timeSpan.textContent = relativeTime(data.timecreated);
        header.appendChild(timeSpan);

        if (data.edited) {
            var editedSpan = document.createElement('span');
            editedSpan.className = 'smgp-comment__edited';
            editedSpan.textContent = '(' + (state.strings.comments_edited || 'edited') + ')';
            header.appendChild(editedSpan);
        }

        contentWrapper.appendChild(header);

        // Body.
        var body = document.createElement('div');
        body.className = 'smgp-comment__body';
        body.innerHTML = data.content;

        // Remove inline activity tags from body when a position badge will be shown,
        // to avoid duplicating the same info.
        if ((data.positionindex > 0 || data.cmid > 0) && data.activityname) {
            var inlineTags = body.querySelectorAll('.smgp-activity-tag');
            inlineTags.forEach(function(t) { t.remove(); });
        }

        contentWrapper.appendChild(body);

        // Position badge (clickable — navigates to the activity/position).
        if (data.positionindex > 0 && data.activityname) {
            var badge = document.createElement('div');
            badge.className = 'smgp-comment__position-badge smgp-comment__position-badge--clickable';
            badge.setAttribute('data-cmid', data.cmid);
            badge.setAttribute('data-position', data.positionindex);
            badge.setAttribute('data-type', data.activitytype || '');
            badge.setAttribute('role', 'button');
            badge.setAttribute('tabindex', '0');
            var posLabel = getPositionLabel(data.activitytype, data.positionindex);
            badge.innerHTML = '<i class="icon-bookmark"></i> ' +
                escapeHtml(data.activityname) + ' &mdash; ' + escapeHtml(posLabel);
            contentWrapper.appendChild(badge);
        } else if (data.cmid > 0 && data.activityname) {
            var actBadge = document.createElement('div');
            actBadge.className = 'smgp-comment__position-badge smgp-comment__position-badge--clickable';
            actBadge.setAttribute('data-cmid', data.cmid);
            actBadge.setAttribute('data-position', '0');
            actBadge.setAttribute('data-type', data.activitytype || '');
            actBadge.setAttribute('role', 'button');
            actBadge.setAttribute('tabindex', '0');
            actBadge.innerHTML = '<i class="icon-bookmark"></i> ' + escapeHtml(data.activityname);
            contentWrapper.appendChild(actBadge);
        }

        // Actions.
        var actions = document.createElement('div');
        actions.className = 'smgp-comment__actions';

        if (!isReply && state.canPost) {
            var replyBtn = document.createElement('button');
            replyBtn.type = 'button';
            replyBtn.className = 'smgp-comment-btn';
            replyBtn.setAttribute('data-action', 'reply');
            replyBtn.setAttribute('data-comment-id', data.id);
            replyBtn.textContent = state.strings.comments_reply || 'Reply';
            actions.appendChild(replyBtn);
        }

        if (data.userid === state.currentUserId) {
            var editBtn = document.createElement('button');
            editBtn.type = 'button';
            editBtn.className = 'smgp-comment-btn';
            editBtn.setAttribute('data-action', 'edit');
            editBtn.setAttribute('data-comment-id', data.id);
            editBtn.textContent = state.strings.comments_edit || 'Edit';
            actions.appendChild(editBtn);
        }

        if (data.userid === state.currentUserId || state.canDeleteAny) {
            var deleteBtn = document.createElement('button');
            deleteBtn.type = 'button';
            deleteBtn.className = 'smgp-comment-btn smgp-comment-btn--danger';
            deleteBtn.setAttribute('data-action', 'delete');
            deleteBtn.setAttribute('data-comment-id', data.id);
            deleteBtn.textContent = state.strings.comments_delete || 'Delete';
            actions.appendChild(deleteBtn);
        }

        contentWrapper.appendChild(actions);

        // Reply count toggle button.
        if (!isReply && data.replycount > 0) {
            var toggleBtn = document.createElement('button');
            toggleBtn.type = 'button';
            toggleBtn.className = 'smgp-comment-btn smgp-comment-btn--replies';
            toggleBtn.setAttribute('data-action', 'toggle-replies');
            toggleBtn.setAttribute('data-comment-id', data.id);
            var label = data.replycount === 1 ? state.strings.comments_reply : state.strings.comments_replies;
            toggleBtn.textContent = data.replycount + ' ' + (label || 'replies');
            contentWrapper.appendChild(toggleBtn);
        }

        // Replies container (hidden by default, loaded on toggle).
        if (!isReply) {
            var repliesContainer = document.createElement('div');
            repliesContainer.className = 'smgp-comment-replies';
            repliesContainer.style.display = 'none';
            contentWrapper.appendChild(repliesContainer);
        }

        el.appendChild(contentWrapper);
        return el;
    }

    // --- Reply System ---

    /**
     * Open inline reply editor below a comment.
     * @param {number} parentId Parent comment ID.
     */
    function openReplyEditor(parentId) {
        // Close any existing reply editor.
        closeAllReplyEditors();

        var commentEl = commentsList.querySelector('[data-comment-id="' + parentId + '"]');
        if (!commentEl) {
            return;
        }

        var contentWrapper = commentEl.querySelector('.smgp-comment__content');
        var replyForm = document.createElement('div');
        replyForm.className = 'smgp-reply-form';

        var editorContainer = document.createElement('div');
        editorContainer.className = 'smgp-reply-form__editor';
        replyForm.appendChild(editorContainer);

        var btnRow = document.createElement('div');
        btnRow.className = 'smgp-reply-form__actions';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'smgp-comment-btn';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.addEventListener('click', function() {
            closeAllReplyEditors();
        });
        btnRow.appendChild(cancelBtn);

        var replySubmitBtn = document.createElement('button');
        replySubmitBtn.type = 'button';
        replySubmitBtn.className = 'smgp-comments__submit smgp-comments__submit--reply';
        replySubmitBtn.textContent = state.strings.comments_post_reply || 'Post reply';
        replySubmitBtn.addEventListener('click', function() {
            submitReply(parentId);
        });
        btnRow.appendChild(replySubmitBtn);

        replyForm.appendChild(btnRow);
        contentWrapper.appendChild(replyForm);

        // Create reply editor.
        var replyEditor = RichEditor.create(editorContainer, {
            placeholder: state.strings.comments_write_reply || 'Write a reply...',
            maxLength: 5000,
            showMentionButton: true,
            showActivityTagButton: false,
            onMentionClick: function(btn) {
                openMentionDropdown(replyEditor, btn);
            },
            onSubmit: function() {
                submitReply(parentId);
            }
        });

        state.replyEditors[parentId] = replyEditor;

        // Auto-insert @mention of the parent author.
        var authorName = commentEl.querySelector('.smgp-comment__author');
        var authorUserId = commentEl.getAttribute('data-comment-id'); // We'd need user ID.
        // For simplicity, just focus the editor.
        replyEditor.focus();
    }

    function closeAllReplyEditors() {
        Object.keys(state.replyEditors).forEach(function(key) {
            if (state.replyEditors[key]) {
                state.replyEditors[key].destroy();
            }
        });
        state.replyEditors = {};
        var forms = commentsList ? commentsList.querySelectorAll('.smgp-reply-form') : [];
        forms.forEach(function(f) { f.remove(); });
    }

    // --- Replies Toggle ---

    /**
     * Toggle replies visibility, lazy-loading on first open.
     * @param {number} commentId Parent comment ID.
     */
    function toggleReplies(commentId) {
        var commentEl = commentsList.querySelector('[data-comment-id="' + commentId + '"]');
        if (!commentEl) {
            return;
        }

        var repliesContainer = commentEl.querySelector('.smgp-comment-replies');
        if (!repliesContainer) {
            return;
        }

        if (repliesContainer.style.display === 'none') {
            // Show and load if empty.
            repliesContainer.style.display = '';
            if (repliesContainer.children.length === 0) {
                loadReplies(commentId, repliesContainer);
            }
        } else {
            repliesContainer.style.display = 'none';
        }
    }

    /**
     * Load replies for a comment.
     * @param {number} parentId Parent comment ID.
     * @param {HTMLElement} container Replies container.
     */
    function loadReplies(parentId, repliesEl) {
        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_get_comments',
            args: {
                courseid: state.courseid,
                parentid: parentId,
                cmid: 0,
                sort: 'oldest',
                page: 0,
                perpage: 100
            }
        }])[0].done(function(result) {
            repliesEl.innerHTML = '';
            result.comments.forEach(function(c) {
                var el = buildCommentElement(c, true);
                repliesEl.appendChild(el);
            });
        }).fail(function(err) {
            // eslint-disable-next-line no-console
            console.error('Failed to load replies:', err);
        });
    }

    // --- Edit Modal ---

    /**
     * Open the edit modal for a comment.
     * @param {number} commentId Comment ID.
     */
    function openEditModal(commentId) {
        var commentEl = commentsList.querySelector('[data-comment-id="' + commentId + '"]');
        if (!commentEl) {
            return;
        }

        var body = commentEl.querySelector('.smgp-comment__body');
        var currentContent = body ? body.innerHTML : '';

        // Build modal.
        var overlay = document.createElement('div');
        overlay.className = 'smgp-comments__modal-overlay';

        var modal = document.createElement('div');
        modal.className = 'smgp-comments__edit-modal';

        var modalHeader = document.createElement('div');
        modalHeader.className = 'smgp-comments__modal-header';
        modalHeader.innerHTML = '<h3>' + escapeHtml(state.strings.comments_edit || 'Edit comment') + '</h3>';

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'smgp-comments__modal-close';
        closeBtn.innerHTML = '<i class="icon-x"></i>';
        closeBtn.addEventListener('click', function() { overlay.remove(); });
        modalHeader.appendChild(closeBtn);
        modal.appendChild(modalHeader);

        var editorContainer = document.createElement('div');
        editorContainer.className = 'smgp-comments__modal-editor';
        modal.appendChild(editorContainer);

        var btnRow = document.createElement('div');
        btnRow.className = 'smgp-comments__modal-actions';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'smgp-comment-btn';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.addEventListener('click', function() { overlay.remove(); });
        btnRow.appendChild(cancelBtn);

        var saveBtn = document.createElement('button');
        saveBtn.type = 'button';
        saveBtn.className = 'smgp-comments__submit';
        saveBtn.textContent = state.strings.comments_edit || 'Save';
        btnRow.appendChild(saveBtn);
        modal.appendChild(btnRow);

        overlay.appendChild(modal);
        document.body.appendChild(overlay);

        // Create editor inside modal with current content.
        var editEditor = RichEditor.create(editorContainer, {
            placeholder: '',
            maxLength: 5000,
            showMentionButton: true,
            showActivityTagButton: false,
            onMentionClick: function(btn) {
                openMentionDropdown(editEditor, btn);
            }
        });
        editEditor.setContent(currentContent);
        editEditor.focus();

        saveBtn.addEventListener('click', function() {
            var newContent = editEditor.getContent();
            if (!newContent || !newContent.trim() || isEmptyHtml(newContent)) {
                return;
            }
            editEditor.destroy();
            updateCommentAjax(commentId, newContent);
            overlay.remove();
        });
    }

    // --- Delete Modal ---

    /**
     * Open the delete confirmation modal.
     * @param {number} commentId Comment ID.
     */
    function openDeleteModal(commentId) {
        var overlay = document.createElement('div');
        overlay.className = 'smgp-comments__modal-overlay';

        var modal = document.createElement('div');
        modal.className = 'smgp-comments__edit-modal smgp-comments__delete-modal';

        var msg = document.createElement('p');
        msg.className = 'smgp-comments__modal-message';
        msg.textContent = state.strings.comments_delete_confirm || 'Are you sure you want to delete this comment?';
        modal.appendChild(msg);

        var btnRow = document.createElement('div');
        btnRow.className = 'smgp-comments__modal-actions';

        var cancelBtn = document.createElement('button');
        cancelBtn.type = 'button';
        cancelBtn.className = 'smgp-comment-btn';
        cancelBtn.textContent = 'Cancel';
        cancelBtn.addEventListener('click', function() { overlay.remove(); });
        btnRow.appendChild(cancelBtn);

        var confirmBtn = document.createElement('button');
        confirmBtn.type = 'button';
        confirmBtn.className = 'smgp-comments__submit smgp-comments__submit--danger';
        confirmBtn.textContent = state.strings.comments_delete || 'Delete';
        confirmBtn.addEventListener('click', function() {
            overlay.remove();
            deleteCommentAjax(commentId);
        });
        btnRow.appendChild(confirmBtn);

        modal.appendChild(btnRow);
        overlay.appendChild(modal);
        document.body.appendChild(overlay);
    }

    function closeAllModals() {
        document.querySelectorAll('.smgp-comments__modal-overlay').forEach(function(o) { o.remove(); });
    }

    // --- @Mention System ---

    var mentionSearchTimeout = null;

    /**
     * Open the mention dropdown near the given button.
     * @param {Object} editor RichEditor instance.
     * @param {HTMLElement} anchorBtn The @ button.
     */
    function openMentionDropdown(editor, anchorBtn) {
        editor.saveSelection();

        // Remove any existing dropdown.
        var existing = document.querySelector('.smgp-mention-dropdown');
        if (existing) {
            existing.remove();
        }

        var dropdown = document.createElement('div');
        dropdown.className = 'smgp-mention-dropdown';

        var searchInput = document.createElement('input');
        searchInput.type = 'text';
        searchInput.className = 'smgp-mention-dropdown__search';
        searchInput.placeholder = state.strings.comments_search_users || 'Search users...';
        dropdown.appendChild(searchInput);

        var resultsList = document.createElement('div');
        resultsList.className = 'smgp-mention-dropdown__results';
        resultsList.innerHTML = '<div class="smgp-mention-dropdown__empty">' +
            escapeHtml(state.strings.comments_search_users || 'Type to search...') + '</div>';
        dropdown.appendChild(resultsList);

        // Position near the anchor button.
        var rect = anchorBtn.getBoundingClientRect();
        dropdown.style.position = 'absolute';
        dropdown.style.top = (rect.bottom + window.scrollY + 4) + 'px';
        dropdown.style.left = (rect.left + window.scrollX) + 'px';
        dropdown.style.display = '';

        document.body.appendChild(dropdown);
        searchInput.focus();

        // Search on input with debounce.
        searchInput.addEventListener('input', function() {
            clearTimeout(mentionSearchTimeout);
            mentionSearchTimeout = setTimeout(function() {
                var query = searchInput.value.trim();
                if (query.length < 1) {
                    resultsList.innerHTML = '<div class="smgp-mention-dropdown__empty">' +
                        escapeHtml(state.strings.comments_search_users || 'Type to search...') + '</div>';
                    return;
                }
                searchUsers(query, resultsList, editor, dropdown);
            }, 200);
        });

        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                dropdown.remove();
            }
        });
    }

    /**
     * Search users via AJAX and display results.
     */
    function searchUsers(query, resultsList, editor, dropdown) {
        Ajax.call([{
            methodname: 'local_sm_graphics_plugin_search_course_users',
            args: {courseid: state.courseid, query: query, limit: 10}
        }])[0].done(function(result) {
            resultsList.innerHTML = '';
            if (result.users.length === 0) {
                resultsList.innerHTML = '<div class="smgp-mention-dropdown__empty">' +
                    escapeHtml(state.strings.comments_no_users || 'No users found') + '</div>';
                return;
            }
            result.users.forEach(function(user) {
                var item = document.createElement('div');
                item.className = 'smgp-mention-dropdown__item';
                item.innerHTML = '<span class="smgp-mention-dropdown__avatar">' + escapeHtml(user.initials) + '</span>' +
                    '<span class="smgp-mention-dropdown__name">' + escapeHtml(user.fullname) + '</span>';
                item.addEventListener('click', function() {
                    editor.insertMention(user.id, user.fullname);
                    dropdown.remove();
                });
                resultsList.appendChild(item);
            });
        }).fail(function() {
            resultsList.innerHTML = '<div class="smgp-mention-dropdown__empty">Error</div>';
        });
    }

    /**
     * Handle click on @mention in comment body.
     * @param {HTMLElement} mention The .smgp-mention span.
     */
    function handleMentionClick(mention) {
        // Scroll to the reply form / main editor and pre-fill mention.
        var editorContainer = container.querySelector('.smgp-comments__editor');
        if (editorContainer && state.editor) {
            editorContainer.scrollIntoView({behavior: 'smooth'});
            var userId = mention.getAttribute('data-user-id');
            var userName = mention.textContent.replace('@', '');
            setTimeout(function() {
                state.editor.focus();
                state.editor.insertMention(userId, userName);
            }, 300);
        }
    }

    // --- Activity Tag System ---

    /**
     * Insert current activity tag at cursor in given editor.
     * @param {Object} editor RichEditor instance.
     */
    function insertCurrentActivityTag(editor) {
        if (!state.cmid || !state.activityName) {
            return;
        }

        var label;
        var position = state.positionIndex;

        // For video activities, capture current playback time.
        if (state.isVideo) {
            var video = document.querySelector('#smgp-course-content-area video');
            if (video && video.currentTime > 0) {
                var time = formatVideoTime(video.currentTime);
                label = '[' + state.activityName + ']-Video-' + time;
                position = Math.floor(video.currentTime);
            } else {
                label = '[' + state.activityName + ']';
            }
        } else if (state.positionIndex > 0 && state.counterLabel) {
            label = '[' + state.activityName + ']-' + state.counterLabel + '-' + state.positionIndex;
        } else {
            label = '[' + state.activityName + ']';
        }

        editor.insertActivityTag(state.cmid, position, state.activityType, label);
    }

    /**
     * Handle click on activity tag in comment body.
     * @param {HTMLElement} tag The .smgp-activity-tag span.
     */
    function handleActivityTagClick(tag) {
        var cmid = tag.getAttribute('data-cmid');
        var position = tag.getAttribute('data-position');
        var type = tag.getAttribute('data-type');

        // Fallback for legacy tags where HTMLPurifier stripped data-* attributes.
        // Parse from label text: "[ActivityName]-Slide-16" or "[ActivityName]-Page-3".
        if (!cmid) {
            var label = (tag.textContent || '').trim();
            var match = label.match(/^\[(.+?)\]-(\w+)-(\d+)$/);
            if (match && state.inCoursePage) {
                var tagActivityName = match[1];
                position = match[3];
                type = state.activityType || '';
                cmid = state.cmid;
            } else if (!match) {
                return;
            }
            if (!cmid) {
                return;
            }
        }

        // In course page: dispatch CustomEvent for in-player navigation.
        if (state.inCoursePage) {
            document.dispatchEvent(new CustomEvent('smgp-navigate-to-activity', {
                detail: {
                    cmid: parseInt(cmid, 10),
                    position: parseInt(position, 10) || 0,
                    type: type || ''
                }
            }));
            return;
        }

        // Fallback: standalone page → navigate to activity URL.
        var url = '/mod/' + (type || 'resource') + '/view.php?id=' + cmid;
        if (position && parseInt(position, 10) > 0) {
            if (type === 'quiz') {
                url += '&page=' + (parseInt(position, 10) - 1);
            } else if (type === 'book') {
                url += '&chapterid=' + position;
            }
        }

        window.location.href = url;
    }

    /**
     * Handle click on position badge below comment.
     * @param {HTMLElement} badge The .smgp-comment__position-badge--clickable element.
     */
    function handlePositionBadgeClick(badge) {
        var cmid = badge.getAttribute('data-cmid');
        var position = badge.getAttribute('data-position');
        var type = badge.getAttribute('data-type');

        if (!cmid) {
            return;
        }

        if (state.inCoursePage) {
            document.dispatchEvent(new CustomEvent('smgp-navigate-to-activity', {
                detail: {
                    cmid: parseInt(cmid, 10),
                    position: parseInt(position, 10) || 0,
                    type: type || ''
                }
            }));
            return;
        }

        // Fallback: standalone page → navigate to activity URL.
        var url = '/mod/' + (type || 'resource') + '/view.php?id=' + cmid;
        if (position && parseInt(position, 10) > 0) {
            if (type === 'quiz') {
                url += '&page=' + (parseInt(position, 10) - 1);
            } else if (type === 'book') {
                url += '&chapterid=' + position;
            }
        }

        window.location.href = url;
    }

    // --- Position Detection ---

    /**
     * Detect activity position from URL and page state.
     */
    function detectPosition() {
        if (!state.cmid) {
            return;
        }

        var params = new URLSearchParams(window.location.search);

        switch (state.activityType) {
            case 'quiz':
                if (params.has('page')) {
                    state.positionIndex = parseInt(params.get('page'), 10) + 1;
                } else {
                    // Count .que elements on page.
                    var questions = document.querySelectorAll('.que');
                    if (questions.length > 0) {
                        state.positionIndex = questions.length;
                    }
                }
                break;
            case 'book':
                if (params.has('chapterid')) {
                    // Find chapter index from TOC.
                    var tocItems = document.querySelectorAll('.book_toc_numbered li');
                    var chapterId = params.get('chapterid');
                    tocItems.forEach(function(item, idx) {
                        var link = item.querySelector('a');
                        if (link && link.href && link.href.indexOf('chapterid=' + chapterId) !== -1) {
                            state.positionIndex = idx + 1;
                        }
                    });
                    if (!state.positionIndex) {
                        state.positionIndex = parseInt(chapterId, 10);
                    }
                }
                break;
            case 'lesson':
                if (params.has('pageid')) {
                    state.positionIndex = parseInt(params.get('pageid'), 10);
                }
                break;
            case 'scorm':
                // Whole-activity only for now.
                break;
            default:
                break;
        }
    }

    /**
     * Get human-readable position label.
     * @param {string} type Activity type.
     * @param {number} index Position index.
     * @return {string} Label like "Slide 5", "Question 3", etc.
     */
    function getPositionLabel(type, index) {
        switch (type) {
            case 'scorm':
                return (state.strings.comments_slide || 'Slide') + '-' + index;
            case 'quiz':
                return (state.strings.comments_question || 'Question') + '-' + index;
            case 'book':
                return (state.strings.comments_chapter || 'Chapter') + '-' + index;
            case 'lesson':
                return (state.strings.comments_page || 'Page') + '-' + index;
            default:
                return '#' + index;
        }
    }

    /**
     * Build the activity tag button label based on current state.
     * @return {string} Label for the tag button.
     */
    function buildTagLabel() {
        if (!state.activityName) {
            return '';
        }
        if (state.isVideo) {
            return '[' + state.activityName + ']-Video';
        }
        if (state.positionIndex > 0 && state.counterLabel) {
            return '[' + state.activityName + ']-' + state.counterLabel + '-' + state.positionIndex;
        }
        return '[' + state.activityName + ']';
    }

    /**
     * Format seconds into HH:MM:SS string for video activity tags.
     * @param {number} seconds Total seconds.
     * @return {string} Formatted time string.
     */
    function formatVideoTime(seconds) {
        if (!seconds || isNaN(seconds) || !isFinite(seconds)) {
            return '00:00:00';
        }
        var h = Math.floor(seconds / 3600);
        var m = Math.floor((seconds % 3600) / 60);
        var s = Math.floor(seconds % 60);
        return (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    }

    /**
     * Update activity context from the course page player.
     * Called when the user navigates to a different activity.
     *
     * @param {Object} data Context data.
     * @param {number} data.cmid Course module ID.
     * @param {string} data.activityName Activity name.
     * @param {string} data.activityType Activity type (modname).
     * @param {number} data.positionIndex Current position (1-based, 0 = no counter).
     * @param {string} data.counterLabel Counter label (Slide, Chapter, Question, Page).
     * @param {boolean} data.isVideo Whether the current activity is a video.
     */
    function updateContext(data) {
        if (!data) {
            return;
        }

        state.cmid = data.cmid || 0;
        state.activityName = data.activityName || '';
        state.activityType = data.activityType || '';
        state.positionIndex = data.positionIndex || 0;
        state.counterLabel = data.counterLabel || '';
        state.isVideo = !!data.isVideo;

        // Update the tag button label in the editor.
        if (container) {
            var tagBtnEl = container.querySelector('.smgp-editor__btn--tag');
            if (tagBtnEl) {
                var newLabel = buildTagLabel();
                var labelSpan = tagBtnEl.querySelector('.smgp-editor__btn-label');
                if (labelSpan) {
                    labelSpan.textContent = newLabel;
                } else if (newLabel) {
                    labelSpan = document.createElement('span');
                    labelSpan.className = 'smgp-editor__btn-label';
                    labelSpan.textContent = newLabel;
                    tagBtnEl.appendChild(labelSpan);
                }
                tagBtnEl.style.display = state.cmid > 0 ? '' : 'none';
            }
        }
    }

    // --- UI Helpers ---

    function showLoading() {
        var spinner = container.querySelector('.smgp-comments__loading');
        if (spinner) {
            spinner.style.display = '';
        }
    }

    function hideLoading() {
        var spinner = container.querySelector('.smgp-comments__loading');
        if (spinner) {
            spinner.style.display = 'none';
        }
    }

    function showEmpty() {
        var empty = container.querySelector('.smgp-comments__empty');
        if (empty) {
            empty.style.display = '';
        }
    }

    function hideEmpty() {
        var empty = container.querySelector('.smgp-comments__empty');
        if (empty) {
            empty.style.display = 'none';
        }
    }

    function updateLoadMoreVisibility() {
        var loadMoreBtn = container.querySelector('.smgp-comments__load-more');
        if (loadMoreBtn) {
            var loaded = (state.page + 1) * state.perpage;
            loadMoreBtn.style.display = loaded < state.totalComments ? '' : 'none';
        }
    }

    function updateCommentCount() {
        var countEl = container.querySelector('.smgp-comments__count');
        if (countEl) {
            countEl.textContent = '(' + state.totalComments + ')';
        }
    }

    /**
     * Get relative time string.
     * @param {number} timestamp Unix timestamp.
     * @return {string}
     */
    function relativeTime(timestamp) {
        var now = Math.floor(Date.now() / 1000);
        var diff = now - timestamp;

        if (diff < 60) {
            return state.strings.comments_just_now || 'Just now';
        }
        if (diff < 3600) {
            var mins = Math.floor(diff / 60);
            return mins + ' ' + (state.strings.comments_minutes_ago || 'min ago');
        }
        if (diff < 86400) {
            var hours = Math.floor(diff / 3600);
            return hours + ' ' + (state.strings.comments_hours_ago || 'hours ago');
        }
        var days = Math.floor(diff / 86400);
        return days + ' ' + (state.strings.comments_days_ago || 'days ago');
    }

    function isEmptyHtml(html) {
        var tmp = document.createElement('div');
        tmp.innerHTML = html;
        return !tmp.textContent.trim();
    }

    function escapeHtml(str) {
        var div = document.createElement('div');
        div.textContent = str || '';
        return div.innerHTML;
    }

    return {
        init: init,
        updateContext: updateContext
    };
});
