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
    class="space-y-4 rounded-[1.5rem] border p-4 transition"
    :class="blocked ? 'border-red-300 bg-red-50/40' : 'border-stone-200 bg-white'"
  >
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div class="space-y-1">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Manual follow-ups</p>
        <p class="text-sm font-medium text-stone-700">Turn the decision into concrete toasts before closing the current one.</p>
      </div>
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
        <button type="button" class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="$emit('add')">Add step</button>
      </div>
    </div>

    <div v-if="!followUps.length" class="rounded-2xl border border-dashed border-stone-200 bg-stone-50 px-4 py-6 text-sm text-stone-500">
      No manual follow-up yet. Add one directly or generate a first draft from xAI.
    </div>

    <div
      v-for="(followUp, followUpIndex) in followUps"
      :key="followUpIndex"
      class="grid gap-3 rounded-[1.25rem] border bg-stone-50 p-4 transition xl:grid-cols-[auto_minmax(0,1.8fr)_minmax(0,1fr)_11rem_auto]"
      :class="blocked ? 'border-red-300' : 'border-stone-200'"
    >
      <div class="inline-grid h-10 w-10 place-items-center rounded-2xl bg-white text-sm font-semibold text-stone-500">
        {{ followUpIndex + 1 }}
      </div>
      <input
        class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"
        type="text"
        :value="followUp.title ?? ''"
        placeholder="Follow-up outcome or task"
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
