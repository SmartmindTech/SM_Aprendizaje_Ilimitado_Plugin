<template>
  <div
    class="smgp-comments"
    :data-courseid="courseid"
    :data-cmid="cmid"
    :data-canpost="canPost ? '1' : '0'"
    :data-candeleteany="canDeleteAny ? '1' : '0'"
    :data-userid="currentUserId"
    :data-userfullname="currentUserFullname"
  >
    <!-- Header: title + count + sort -->
    <div class="smgp-comments__header">
      <div class="smgp-comments__title">
        <i class="icon-message-circle" />
        {{ $t('comments.title') || 'Comments' }}
        <span class="smgp-comments__count">({{ totalComments }})</span>
      </div>
      <div class="smgp-comments__sort">
        <div class="smgp-comments__sort-dropdown">
          <button
            type="button"
            class="smgp-comments__sort-trigger"
            :data-current="sortOrder"
            @click="showSortMenu = !showSortMenu"
          >
            <span class="smgp-comments__sort-trigger-label">
              {{ sortOrder === 'newest'
                ? ($t('comments.newest') || 'Newest')
                : ($t('comments.oldest') || 'Oldest')
              }}
            </span>
            <i class="icon-chevron-down" />
          </button>
          <div v-if="showSortMenu" class="smgp-comments__sort-menu">
            <button
              type="button"
              class="smgp-comments__sort-option"
              :class="{ 'smgp-comments__sort-option--active': sortOrder === 'newest' }"
              data-sort="newest"
              @click="changeSort('newest')"
            >
              {{ $t('comments.newest') || 'Newest first' }}
            </button>
            <button
              type="button"
              class="smgp-comments__sort-option"
              :class="{ 'smgp-comments__sort-option--active': sortOrder === 'oldest' }"
              data-sort="oldest"
              @click="changeSort('oldest')"
            >
              {{ $t('comments.oldest') || 'Oldest first' }}
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Loading spinner -->
    <div v-if="loading" class="smgp-comments__loading">
      <div class="smgp-comments__spinner" />
    </div>

    <!-- Comment list -->
    <div v-else-if="comments.length" class="smgp-comments__list">
      <CommentThread
        v-for="comment in comments"
        :key="comment.id"
        :comment="comment"
        :current-user-id="currentUserId"
        :can-post="canPost"
        :can-delete-any="canDeleteAny"
        @reply="handleReply"
        @delete="handleDelete"
        @update="handleUpdate"
      />
    </div>

    <!-- Empty state -->
    <div v-else class="smgp-comments__empty">
      <i class="icon-message-square" />
      <p>{{ $t('comments.empty') || 'No comments yet. Be the first to comment!' }}</p>
    </div>

    <!-- Load more button -->
    <div v-if="hasMore && !loading" class="smgp-comments__load-more-wrapper">
      <button
        type="button"
        class="smgp-comments__load-more"
        @click="loadMore"
      >
        {{ $t('comments.load_more') || 'Load more comments' }}
      </button>
    </div>

    <!-- Reply indicator -->
    <div v-if="replyingTo" class="smgp-comments__replying-to">
      <span>{{ $t('comments.replying_to') || 'Replying to' }} {{ replyingTo.userfullname }}</span>
      <button type="button" @click="replyingTo = null">
        <i class="icon-x" />
      </button>
    </div>

    <!-- Editor area (only if user can post) -->
    <div v-if="canPost" class="smgp-comments__editor-section">
      <div class="smgp-comments__editor">
        <textarea
          v-model="newComment"
          class="smgp-comments__editor-input"
          :placeholder="$t('comments.placeholder') || 'Write a comment...'"
          rows="3"
        />
      </div>
      <div class="smgp-comments__editor-actions">
        <button
          type="button"
          class="smgp-comments__submit"
          :disabled="!newComment.trim() || submitting"
          @click="submitComment"
        >
          {{ $t('comments.post') || 'Post' }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{
  courseid: number
  cmid?: number
  canPost: boolean
  canDeleteAny: boolean
  currentUserId: number
  currentUserFullname: string
}>()

const { getComments, addComment, updateComment, deleteComment } = useCommentsApi()

const comments = ref<any[]>([])
const loading = ref(true)
const submitting = ref(false)
const newComment = ref('')
const sortOrder = ref('newest')
const showSortMenu = ref(false)
const page = ref(0)
const perpage = 20
const totalComments = ref(0)
const hasMore = ref(false)
const replyingTo = ref<any>(null)

const fetchComments = async (reset = true) => {
  if (reset) {
    page.value = 0
    loading.value = true
  }
  const result = await getComments(
    props.courseid,
    props.cmid ?? 0,
    page.value,
    perpage,
    sortOrder.value,
  )
  if (!result.error && result.data) {
    const d = result.data as any
    if (reset) {
      comments.value = d.comments || []
    } else {
      comments.value.push(...(d.comments || []))
    }
    totalComments.value = d.total || 0
    hasMore.value = comments.value.length < totalComments.value
  }
  loading.value = false
}

const loadMore = async () => {
  page.value++
  await fetchComments(false)
}

const changeSort = (order: string) => {
  sortOrder.value = order
  showSortMenu.value = false
  fetchComments()
}

const submitComment = async () => {
  if (!newComment.value.trim()) return
  submitting.value = true
  const parentid = replyingTo.value?.id ?? 0
  await addComment(props.courseid, props.cmid ?? 0, newComment.value, parentid)
  newComment.value = ''
  replyingTo.value = null
  submitting.value = false
  await fetchComments()
}

const handleReply = (comment: any) => {
  replyingTo.value = comment
}

const handleDelete = async (comment: any) => {
  if (!confirm('Delete this comment?')) return
  await deleteComment(comment.id)
  await fetchComments()
}

const handleUpdate = async (comment: any) => {
  await updateComment(comment.id, comment.newContent)
  await fetchComments()
}

onMounted(fetchComments)
</script>
