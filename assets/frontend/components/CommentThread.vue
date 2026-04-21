<script setup>
import AvatarBadge from './AvatarBadge.vue';

defineProps({
  comments: { type: Array, default: () => [] },
  renderComment: { type: Function, required: true },
  mobile: { type: Boolean, default: false },
});
</script>

<template>
  <div v-if="comments.length" :class="mobile ? 'space-y-2.5' : 'space-y-3'">
    <div v-for="comment in comments" :key="comment.id" :class="mobile ? 'flex items-start gap-2.5' : 'flex items-start gap-3'">
      <AvatarBadge
        :seed="comment.author.id"
        :initials="comment.author.initials"
        :gravatar-url="comment.author.gravatarUrl"
        :alt="comment.author.displayName"
      />
      <div class="min-w-0 flex-1 space-y-2">
        <div :class="mobile ? 'rounded-2xl border border-stone-200 bg-white px-3 py-2.5 shadow-sm' : 'rounded-2xl bg-stone-50 px-4 py-3'">
          <div
            class="tw-markdown text-stone-700"
            :class="mobile ? 'text-sm leading-6' : 'text-sm leading-7'"
            v-html="renderComment(comment.content)"
          ></div>
        </div>
        <div :class="mobile ? 'px-1 text-[11px] text-stone-500' : 'px-1 text-xs text-stone-500'">
          {{ comment.author.displayName }} · {{ comment.createdAtDisplay }}
        </div>
      </div>
    </div>
  </div>
  <p v-else :class="mobile ? 'rounded-2xl border border-dashed border-stone-300 bg-white px-4 py-5 text-center text-sm text-stone-500' : 'text-sm text-stone-500'">No comments.</p>
</template>
