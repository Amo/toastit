<script setup>
import DatePickerField from './DatePickerField.vue';

defineProps({
  followUps: { type: Array, default: () => [] },
  participants: { type: Array, default: () => [] },
  blocked: { type: Boolean, default: false },
  canGenerate: { type: Boolean, default: false },
  isGenerating: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
  noticeMessage: { type: String, default: '' },
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
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Follow-ups</p>
        <p class="text-sm font-medium text-stone-700">Generate drafts from the decision notes, then adjust the follow-up toasts you want to create.</p>
      </div>
      <div class="flex items-center gap-3">
        <button
          type="button"
          :class="['tw-ai-rainbow-action rounded-full border px-4 py-2 text-sm font-semibold transition disabled:opacity-60', isGenerating ? 'tw-ai-pending' : '']"
          :disabled="!canGenerate || isGenerating"
          @click="$emit('generate')"
        >
          {{ isGenerating ? 'Generating…' : 'Generate with AI' }}
        </button>
        <button type="button" class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="$emit('add')">Add step</button>
      </div>
    </div>

    <div v-if="errorMessage" class="rounded-[1.25rem] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ errorMessage }}
    </div>

    <div v-else-if="noticeMessage" class="rounded-[1.25rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      {{ noticeMessage }}
    </div>

    <div v-if="!followUps.length" class="rounded-2xl border border-dashed border-stone-200 bg-stone-50 px-4 py-6 text-sm text-stone-500">
      No follow-up yet. Generate drafts from the decision notes, or add one directly.
    </div>

    <div
      v-for="(followUp, followUpIndex) in followUps"
      :key="followUpIndex"
      class="grid gap-3 rounded-[1.25rem] border bg-stone-50 p-4 transition xl:grid-cols-[auto_minmax(0,1.8fr)_minmax(0,1fr)_11rem_auto]"
      :class="blocked ? 'border-red-300' : 'border-stone-200'"
    >
      <div
        class="inline-grid h-10 w-10 place-items-center rounded-2xl text-sm font-semibold"
        :class="followUp.aiGenerated ? 'tw-ai-rainbow-action border border-transparent text-white shadow-sm' : 'bg-white text-stone-500'"
      >
        {{ followUpIndex + 1 }}
      </div>
      <div class="space-y-2">
        <input
          class="w-full rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"
          type="text"
          :value="followUp.title ?? ''"
          placeholder="Follow-up outcome or task"
          @input="$emit('update', { index: followUpIndex, key: 'title', value: $event.target.value })"
        >
        <div v-if="followUp.aiGeneratedReason || followUp.aiGenerated" class="flex flex-wrap items-center gap-2">
          <span
            v-if="followUp.aiGenerated"
            class="tw-ai-rainbow-action inline-flex items-center rounded-full border border-transparent px-2 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-white"
          >
            AI draft
          </span>
          <p v-if="followUp.aiGeneratedReason" class="text-xs leading-5 text-stone-500">
            {{ followUp.aiGeneratedReason }}
          </p>
        </div>
      </div>
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
