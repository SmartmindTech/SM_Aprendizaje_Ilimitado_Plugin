<template>
  <div class="smgp-comment" :data-commentid="comment.id">
    <div class="smgp-comment__avatar">
      <span class="smgp-comment__avatar-circle">
        {{ comment.userfullname?.charAt(0)?.toUpperCase() }}
      </span>
    </div>

    <div class="smgp-comment__body">
      <div class="smgp-comment__header">
        <span class="smgp-comment__author">{{ comment.userfullname }}</span>
        <span class="smgp-comment__time">{{ comment.timecreated_human }}</span>
      </div>

      <!-- Display mode -->
      <div v-if="!editing" class="smgp-comment__content" v-html="comment.content" />

      <!-- Edit mode -->
      <div v-else class="smgp-comment__edit">
        <textarea
          v-model="editContent"
          class="smgp-comment__edit-input"
          rows="3"
        />
        <div class="smgp-comment__edit-actions">
          <button class="smgp-comment__edit-save" @click="saveEdit">
            {{ $t('comments.save') || 'Save' }}
          </button>
          <button class="smgp-comment__edit-cancel" @click="cancelEdit">
            {{ $t('comments.cancel') || 'Cancel' }}
          </button>
        </div>
      </div>

      <!-- Actions -->
      <div class="smgp-comment__actions">
        <button
          v-if="canReply"
          class="smgp-comment__action-btn"
          @click="$emit('reply', comment)"
        >
          <i class="icon-reply" />
          {{ $t('comments.reply') || 'Reply' }}
        </button>
        <button
          v-if="canEdit"
          class="smgp-comment__action-btn"
          @click="startEdit"
        >
          <i class="icon-edit" />
          {{ $t('comments.edit') || 'Edit' }}
        </button>
        <button
          v-if="canDelete"
          class="smgp-comment__action-btn smgp-comment__action-btn--danger"
          @click="$emit('delete', comment)"
        >
          <i class="icon-trash-2" />
          {{ $t('comments.delete') || 'Delete' }}
        </button>
      </div>

      <!-- Replies -->
      <div v-if="comment.replies && comment.replies.length" class="smgp-comment__replies">
        <CommentThread
          v-for="reply in comment.replies"
          :key="reply.id"
          :comment="reply"
          :current-user-id="currentUserId"
          :can-post="canPost"
          :can-delete-any="canDeleteAny"
          @reply="$emit('reply', $event)"
          @delete="$emit('delete', $event)"
          @update="$emit('update', $event)"
        />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
interface Comment {
  id: number
  userid: number
  userfullname: string
  content: string
  timecreated_human: string
  parentid: number
  replies?: Comment[]
}

const props = defineProps<{
  comment: Comment
  currentUserId: number
  canPost: boolean
  canDeleteAny: boolean
}>()

const emit = defineEmits<{
  reply: [comment: Comment]
  delete: [comment: Comment]
  update: [comment: Comment & { newContent: string }]
}>()

const editing = ref(false)
const editContent = ref('')

const canReply = computed(() => props.canPost)
const canEdit = computed(() => props.comment.userid === props.currentUserId)
const canDelete = computed(
  () => props.comment.userid === props.currentUserId || props.canDeleteAny,
)

const startEdit = () => {
  editContent.value = props.comment.content.replace(/<[^>]*>/g, '')
  editing.value = true
}

const cancelEdit = () => {
  editing.value = false
}

const saveEdit = () => {
  if (!editContent.value.trim()) return
  emit('update', { ...props.comment, newContent: editContent.value })
  editing.value = false
}
</script>
