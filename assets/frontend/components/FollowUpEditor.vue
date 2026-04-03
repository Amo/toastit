<script setup>
import DatePickerField from './DatePickerField.vue';

defineProps({
  followUps: { type: Array, default: () => [] },
  participants: { type: Array, default: () => [] },
  blocked: { type: Boolean, default: false },
  canGenerate: { type: Boolean, default: false },
  isGenerating: { type: Boolean, default: false },
});

defineEmits(['add', 'remove', 'update', 'generate']);
</script>

<template>
  <div
    class="space-y-3 rounded-[1.25rem] border p-3 transition"
    :class="blocked ? 'border-red-300 bg-red-50/40' : 'border-transparent'"
  >
    <div class="flex items-center justify-between gap-4">
      <p class="text-sm font-medium text-stone-700">Create follow-ups in this workspace</p>
      <div class="flex items-center gap-3">
        <button
          v-if="canGenerate"
          type="button"
          :class="['tw-ai-rainbow-action inline-grid h-10 w-10 place-items-center rounded-full border text-sm transition disabled:opacity-60', isGenerating ? 'tw-ai-pending' : '']"
          :disabled="isGenerating"
          title="Generate follow-up plan with xAI"
          @click="$emit('generate')"
        >
          <i class="fa-solid fa-wand-sparkles" aria-hidden="true"></i>
          <span class="sr-only">Generate follow-up plan with xAI</span>
        </button>
        <button type="button" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="$emit('add')">Add</button>
      </div>
    </div>

    <div v-if="!followUps.length" class="rounded-2xl border border-dashed border-stone-200 bg-stone-50 px-4 py-5 text-sm text-stone-500">
      No manual follow-up yet.
    </div>

    <div
      v-for="(followUp, followUpIndex) in followUps"
      :key="followUpIndex"
      class="grid gap-3 rounded-2xl border bg-stone-50 p-4 transition xl:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)_11rem_auto]"
      :class="blocked ? 'border-red-300' : 'border-stone-200'"
    >
      <input
        class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"
        type="text"
        :value="followUp.title ?? ''"
        placeholder="Follow-up title"
        @input="$emit('update', { index: followUpIndex, key: 'title', value: $event.target.value })"
      >
      <select
        class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"
        :value="followUp.ownerId ?? ''"
        @change="$emit('update', { index: followUpIndex, key: 'ownerId', value: $event.target.value })"
      >
        <option value="">Assignee</option>
        <option v-for="invitee in participants" :key="invitee.id" :value="invitee.id">{{ invitee.displayName }}</option>
      </select>
      <DatePickerField
        :model-value="followUp.dueOn ?? ''"
        label="Date"
        @update:model-value="$emit('update', { index: followUpIndex, key: 'dueOn', value: $event })"
      />
      <div class="flex items-end justify-end">
        <button type="button" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="$emit('remove', followUpIndex)">Remove</button>
      </div>
    </div>
  </div>
</template>
