<script setup>
import { computed } from 'vue';
import { renderToastDescription } from '../utils/workspaceFormatting';

const props = defineProps({
  draft: { type: Object, default: null },
  participantsLookup: { type: Object, default: () => ({}) },
  isGenerating: { type: Boolean, default: false },
  applyingIndex: { type: Number, default: -1 },
  actionStatuses: { type: Object, default: () => ({}) },
  errorMessage: { type: String, default: '' },
  noticeMessage: { type: String, default: '' },
});

defineEmits(['generate', 'apply-item']);

const actions = computed(() => props.draft?.actions ?? []);
</script>

<template>
  <section class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-5">
      <div class="space-y-1">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Execution plan</p>
        <p class="text-sm font-medium text-stone-700">Use xAI to turn the saved decision notes into actionable follow-up toasts.</p>
      </div>
      <button
        type="button"
        :class="['rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60', isGenerating ? 'tw-ai-pending' : '']"
        :disabled="isGenerating"
        @click="$emit('generate')"
      >
        {{ isGenerating ? 'Generating...' : (draft ? 'Regenerate plan' : 'Generate plan') }}
      </button>
    </div>

    <div v-if="errorMessage" class="rounded-[1.25rem] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
      {{ errorMessage }}
    </div>

    <div v-else-if="noticeMessage" class="rounded-[1.25rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
      {{ noticeMessage }}
    </div>

    <div v-if="draft" class="space-y-5">
      <section class="rounded-[1.5rem] border border-stone-200 bg-white p-5">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Plan summary</p>
        <div class="mt-3 tw-markdown text-stone-800" v-html="renderToastDescription(draft.summary)"></div>
      </section>

      <section class="space-y-3">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Suggested follow-up toasts</p>
        <div v-if="!actions.length" class="rounded-[1.5rem] border border-stone-200 bg-white px-5 py-6 text-sm text-stone-500">
          No follow-up toast was suggested.
        </div>
        <div v-else class="space-y-3">
          <article
            v-for="(action, index) in actions"
            :key="`${action.title}-${index}`"
            class="rounded-[1.5rem] border border-stone-200 bg-white p-5"
          >
            <div class="space-y-3">
              <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-stone-700">Create follow-up</span>
                <span v-if="participantsLookup[action.ownerId]" class="text-sm text-stone-600">Owner · {{ participantsLookup[action.ownerId] }}</span>
                <span v-else-if="action.ownerId === null || action.ownerId === undefined" class="text-sm text-stone-400">Owner · Unassigned</span>
                <span v-if="action.dueOn" class="text-sm text-stone-600">Due · {{ action.dueOn }}</span>
              </div>
              <p class="text-base font-semibold text-stone-950">{{ action.title }}</p>
              <p v-if="action.reason" class="text-sm leading-6 text-stone-700">{{ action.reason }}</p>
              <div v-if="action.description" class="rounded-2xl bg-stone-50 px-4 py-3 text-sm text-stone-700 whitespace-pre-wrap">{{ action.description }}</div>
              <div class="flex items-center justify-end gap-3">
                <span
                  v-if="actionStatuses[index]"
                  class="text-sm font-medium"
                  :class="actionStatuses[index] === 'applied' ? 'text-emerald-700' : 'text-stone-500'"
                >
                  {{ actionStatuses[index] === 'applied' ? 'Applied' : 'Skipped' }}
                </span>
                <button
                  type="button"
                  class="rounded-full bg-amber-200 px-4 py-2 text-sm font-semibold text-amber-900 transition hover:bg-amber-300 disabled:opacity-60"
                  :disabled="applyingIndex === index || actionStatuses[index] === 'applied'"
                  @click="$emit('apply-item', { action, index })"
                >
                  {{ applyingIndex === index ? 'Applying...' : (actionStatuses[index] === 'applied' ? 'Applied' : 'Apply') }}
                </button>
              </div>
            </div>
          </article>
        </div>
      </section>
    </div>
  </section>
</template>
