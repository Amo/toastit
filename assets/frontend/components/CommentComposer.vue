<script setup>
import AvatarBadge from './AvatarBadge.vue';

defineProps({
  currentUser: { type: Object, default: null },
  value: { type: String, default: '' },
  blocked: { type: Boolean, default: false },
});

defineEmits(['input', 'keydown', 'submit']);
</script>

<template>
  <div class="mt-4 flex items-end gap-3 border-t border-stone-100 pt-4">
    <AvatarBadge
      :seed="currentUser?.id"
      :initials="currentUser?.initials"
      :gravatar-url="currentUser?.gravatarUrl"
      :alt="currentUser?.displayName"
    />
    <textarea
      class="min-h-[2.75rem] min-w-0 flex-1 resize-none overflow-hidden rounded-[1.4rem] border bg-white px-4 py-3 text-sm leading-6 transition"
      :class="blocked ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-200'"
      :value="value"
      rows="1"
      placeholder="Write a comment"
      @input="$emit('input', $event)"
      @keydown="$emit('keydown', $event)"
    ></textarea>
    <button type="button" class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400" @click="$emit('submit')">
      Send
    </button>
  </div>
</template>
