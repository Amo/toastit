<script setup>
import AvatarBadge from './AvatarBadge.vue';
import MarkdownRichTextEditor from './MarkdownRichTextEditor.vue';

defineProps({
  currentUser: { type: Object, default: null },
  value: { type: String, default: '' },
  blocked: { type: Boolean, default: false },
  mobile: { type: Boolean, default: false },
});

defineEmits(['focus', 'input', 'keydown', 'submit']);
</script>

<template>
  <div :class="mobile ? 'flex items-end gap-2' : 'mt-4 flex items-end gap-3 border-t border-stone-100 pt-4'">
    <AvatarBadge
      v-if="!mobile"
      :seed="currentUser?.id"
      :initials="currentUser?.initials"
      :gravatar-url="currentUser?.gravatarUrl"
      :alt="currentUser?.displayName"
    />
    <MarkdownRichTextEditor
      class="min-w-0 flex-1"
      :class="mobile ? 'comment-composer-mobile' : ''"
      :model-value="value"
      :blocked="blocked"
      :compact="true"
      :min-height-class="mobile ? 'min-h-[1.75rem]' : 'min-h-[10rem]'"
      placeholder="Write a comment"
      @focus="$emit('focus', $event)"
      @update:model-value="$emit('input', $event)"
      @keydown="$emit('keydown', $event)"
    />
    <button
      type="button"
      :class="mobile ? 'inline-grid h-11 w-11 shrink-0 place-items-center rounded-2xl bg-amber-200 text-amber-900 shadow-sm transition hover:bg-amber-300' : 'rounded-full bg-amber-200 px-4 py-2 text-sm font-semibold text-amber-900 transition hover:bg-amber-300'"
      @click="$emit('submit')"
    >
      <i v-if="mobile" class="fa-solid fa-paper-plane text-sm" aria-hidden="true"></i>
      <span v-else>Send</span>
      <span v-if="mobile" class="sr-only">Send</span>
    </button>
  </div>
</template>

<style scoped>
.comment-composer-mobile :deep(.markdown-rich-editor) {
  border-radius: 1rem;
  padding: 0.7rem 0.9rem;
}

.comment-composer-mobile :deep(.ProseMirror) {
  min-height: 1.75rem;
}
</style>
