<script setup>
import { nextTick, ref } from 'vue';
import DatePickerField from './DatePickerField.vue';
import KeyboardHint from './KeyboardHint.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

defineProps({
  open: { type: Boolean, required: true },
  itemForm: { type: Object, required: true },
  participants: { type: Array, default: () => [] },
  title: { type: String, default: 'Toast details' },
  actionLabel: { type: String, default: 'Create toast' },
  isRefining: { type: Boolean, default: false },
  canUndoRefinement: { type: Boolean, default: false },
});

defineEmits(['close', 'create', 'refine', 'undo-refine', 'title-input', 'title-keydown', 'update:title', 'update:ownerId', 'update:dueOn', 'update:description']);

const titleInput = ref(null);

const focusTitle = async () => {
  await nextTick();
  titleInput.value?.focus();
};

defineExpose({
  focusTitle,
});
</script>

<template>
  <ModalDialog v-if="open" max-width-class="max-w-4xl" z-index-class="z-[110]" @close="$emit('close')">
    <ModalHeader eyebrow="New toast" :title="title" @close="$emit('close')" />

    <div class="relative space-y-4 overflow-y-auto px-6 py-6" @keydown="$emit('title-keydown', $event)">
      <div
        v-if="isRefining"
        class="absolute inset-0 z-10 flex flex-col items-center justify-center gap-2 rounded-2xl bg-white/75 text-center text-stone-700 backdrop-blur-[1px]"
      >
        <i class="fa-solid fa-wand-sparkles tw-ai-pending text-xl" aria-hidden="true"></i>
        <p class="text-sm font-medium">Improving your draft with AI…</p>
      </div>

      <label class="grid gap-2 text-sm font-medium text-stone-700">
        <span>Title</span>
        <input
          ref="titleInput"
          class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base"
          type="text"
          :value="itemForm.title"
          :disabled="isRefining"
          placeholder="New toast"
          @input="$emit('update:title', $event.target.value)"
          @keydown="$emit('title-input', $event)"
        >
      </label>

      <div class="grid gap-4 md:grid-cols-2">
        <label class="grid gap-2 text-sm font-medium text-stone-700">
          <span>Assignee</span>
          <select class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="itemForm.ownerId" :disabled="isRefining" @change="$emit('update:ownerId', $event.target.value)">
            <option value="">Unassigned</option>
            <option v-for="invitee in participants" :key="invitee.id" :value="String(invitee.id)">{{ invitee.displayName }}</option>
          </select>
        </label>
        <DatePickerField
          :model-value="itemForm.dueOn"
          label="Date"
          @update:model-value="$emit('update:dueOn', $event)"
        />
      </div>

      <label class="grid gap-2 text-sm font-medium text-stone-700">
        <span>Details</span>
        <textarea class="min-h-48 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="itemForm.description" :disabled="isRefining" placeholder="Add details or description" @input="$emit('update:description', $event.target.value)" />
      </label>

      <div class="flex items-center justify-between gap-3">
        <KeyboardHint>Press Cmd+Enter or Ctrl+Enter to create this toast.</KeyboardHint>
        <div class="flex justify-end gap-3">
          <button
            type="button"
            :class="['inline-grid h-12 w-12 place-items-center rounded-full border border-stone-200 bg-white text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60', isRefining ? 'tw-ai-pending' : '']"
            :disabled="isRefining"
            title="Improve draft with xAI"
            @click="$emit('refine')"
          >
            <i class="fa-solid fa-wand-sparkles" aria-hidden="true"></i>
            <span class="sr-only">Improve draft with xAI</span>
          </button>
          <button
            v-if="canUndoRefinement"
            type="button"
            class="rounded-full border border-stone-200 bg-white px-4 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
            :disabled="isRefining"
            @click="$emit('undo-refine')"
          >
            Undo AI change
          </button>
          <button type="button" class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60" :disabled="isRefining" @click="$emit('close')">Cancel</button>
          <button type="button" class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300 disabled:opacity-60" :disabled="isRefining" @click="$emit('create')">{{ actionLabel }}</button>
        </div>
      </div>
    </div>
  </ModalDialog>
</template>
