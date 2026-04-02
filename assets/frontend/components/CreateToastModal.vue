<script setup>
import KeyboardHint from './KeyboardHint.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

defineProps({
  open: { type: Boolean, required: true },
  itemForm: { type: Object, required: true },
  participants: { type: Array, default: () => [] },
});

defineEmits(['close', 'create', 'title-input', 'title-keydown', 'update:title', 'update:ownerId', 'update:dueOn', 'update:description']);
</script>

<template>
  <ModalDialog v-if="open" max-width-class="max-w-2xl" @close="$emit('close')">
    <ModalHeader eyebrow="New toast" title="Toast details" @close="$emit('close')" />

    <div class="space-y-4 overflow-y-auto px-6 py-6" @keydown="$emit('title-keydown', $event)">
      <label class="grid gap-2 text-sm font-medium text-stone-700">
        <span>Title</span>
        <input
          class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base"
          type="text"
          :value="itemForm.title"
          placeholder="New toast"
          @input="$emit('update:title', $event.target.value)"
          @keydown="$emit('title-input', $event)"
        >
      </label>

      <div class="grid gap-4 md:grid-cols-2">
        <label class="grid gap-2 text-sm font-medium text-stone-700">
          <span>Assignee</span>
          <select class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="itemForm.ownerId" @change="$emit('update:ownerId', $event.target.value)">
            <option value="">Unassigned</option>
            <option v-for="invitee in participants" :key="invitee.id" :value="String(invitee.id)">{{ invitee.displayName }}</option>
          </select>
        </label>
        <label class="grid gap-2 text-sm font-medium text-stone-700">
          <span>Date</span>
          <input class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" type="date" :value="itemForm.dueOn" @input="$emit('update:dueOn', $event.target.value)">
        </label>
      </div>

      <label class="grid gap-2 text-sm font-medium text-stone-700">
        <span>Details</span>
        <textarea class="min-h-32 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="itemForm.description" placeholder="Add details or description" @input="$emit('update:description', $event.target.value)" />
      </label>

      <div class="flex items-center justify-between gap-3">
        <KeyboardHint>Press Cmd+Enter or Ctrl+Enter to create this toast.</KeyboardHint>
        <div class="flex justify-end gap-3">
          <button type="button" class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="$emit('close')">Cancel</button>
          <button type="button" class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="$emit('create')">Create toast</button>
        </div>
      </div>
    </div>
  </ModalDialog>
</template>
