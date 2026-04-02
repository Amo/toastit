<script setup>
import AvatarBadge from './AvatarBadge.vue';

defineProps({
  comments: { type: Array, default: () => [] },
  renderComment: { type: Function, required: true },
});
</script>

<template>
  <div v-if="comments.length" class="space-y-3">
    <div v-for="comment in comments" :key="comment.id" class="flex items-start gap-3">
      <AvatarBadge
        :seed="comment.author.id"
        :initials="comment.author.initials"
        :gravatar-url="comment.author.gravatarUrl"
        :alt="comment.author.displayName"
      />
      <div class="min-w-0 flex-1 space-y-2">
        <div class="rounded-2xl bg-stone-50 px-4 py-3">
          <p class="text-sm leading-7 text-stone-700" v-html="renderComment(comment.content)"></p>
        </div>
        <div class="px-1 text-xs text-stone-500">
          {{ comment.author.displayName }} · {{ comment.createdAtDisplay }}
        </div>
      </div>
    </div>
  </div>
  <p v-else class="text-sm text-stone-500">No comments.</p>
</template>
