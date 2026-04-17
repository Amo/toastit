<script setup>
import AvatarBadge from './AvatarBadge.vue';

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
    <textarea
      class="min-h-[2.75rem] min-w-0 flex-1 resize-none overflow-hidden text-sm leading-6 transition"
      :class="[
        mobile ? 'rounded-2xl border bg-white px-4 py-3' : 'rounded-[1.4rem] border bg-white px-4 py-3',
        blocked ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-200'
      ]"
      :value="value"
      rows="1"
      placeholder="Write a comment"
      @focus="$emit('focus', $event)"
      @input="$emit('input', $event)"
      @keydown="$emit('keydown', $event)"
    ></textarea>
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
