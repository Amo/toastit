<script setup>
import { computed } from 'vue';
import { renderToastDescription } from '../utils/workspaceFormatting';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  draft: { type: Object, default: null },
  toastLookup: { type: Object, default: () => ({}) },
  isGenerating: { type: Boolean, default: false },
  applyingIndex: { type: Number, default: -1 },
  actionStatuses: { type: Object, default: () => ({}) },
  errorMessage: { type: String, default: '' },
  noticeMessage: { type: String, default: '' },
});

defineEmits(['close', 'generate', 'apply-item']);

const actions = computed(() => props.draft?.actions ?? []);

const actionTypeLabel = (type) => ({
  update_toast: 'Update toast',
  add_comment: 'Add comment',
  boost_toast: 'Boost toast',
  veto_toast: 'Decline toast',
  create_follow_up: 'Create follow-up',
}[type] ?? type);

const actionStatusLabel = (status) => ({
  applied: 'Applied',
  skipped: 'Skipped',
}[status] ?? '');
</script>

<template>
  <ModalDialog v-if="open" max-width-class="max-w-5xl" @close="$emit('close')">
    <ModalHeader
      eyebrow="Toast curation"
      title="Curate active toasts"
      description="Generate a draft plan of curation actions, review it, then apply it explicitly."
      @close="$emit('close')"
    />

    <div class="space-y-5 overflow-y-auto px-6 py-6">
      <div class="flex flex-wrap items-center justify-between gap-3 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-5">
        <div class="space-y-1">
          <p class="text-sm font-medium text-stone-700">Use xAI to propose a focused curation plan on active toasts.</p>
          <p v-if="draft" class="text-sm text-stone-500">{{ draft.activeToastCount }} active toast{{ draft.activeToastCount > 1 ? 's' : '' }} inspected.</p>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            :class="['rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60', isGenerating ? 'tw-ai-pending' : '']"
            :disabled="isGenerating"
            @click="$emit('generate')"
          >
            {{ isGenerating ? 'Generating...' : (draft ? 'Regenerate draft' : 'Generate draft') }}
          </button>
        </div>
      </div>

      <div v-if="errorMessage" class="rounded-[1.25rem] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
        {{ errorMessage }}
      </div>

      <div v-else-if="noticeMessage" class="rounded-[1.25rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
        {{ noticeMessage }}
      </div>

      <div v-if="draft" class="space-y-5">
        <section class="rounded-[1.5rem] border border-stone-200 bg-white p-5">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Draft summary</p>
          <div class="mt-3 tw-markdown text-stone-800" v-html="renderToastDescription(draft.summary)"></div>
        </section>

        <section class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Proposed actions</p>
          <div v-if="!actions.length" class="rounded-[1.5rem] border border-stone-200 bg-white px-5 py-6 text-sm text-stone-500">
            No curation action was proposed.
          </div>
          <div v-else class="space-y-3">
            <article
              v-for="(action, index) in actions"
              :key="`${action.type}-${action.toastId}-${index}`"
              class="rounded-[1.5rem] border border-stone-200 bg-white p-5"
            >
              <div class="flex flex-wrap items-center gap-3">
                <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-stone-700">{{ actionTypeLabel(action.type) }}</span>
                <a
                  class="text-sm font-medium text-amber-700 underline transition hover:text-amber-800"
                  :href="`/app/toasts/${action.toastId}`"
                  target="_blank"
                  rel="noopener noreferrer"
                >
                  Toast #{{ action.toastId }}
                </a>
                <span v-if="toastLookup[action.toastId]" class="text-sm text-stone-600">· {{ toastLookup[action.toastId] }}</span>
              </div>
              <p v-if="action.reason" class="mt-3 text-sm leading-6 text-stone-700">{{ action.reason }}</p>
              <dl class="mt-4 grid gap-3 text-sm text-stone-700 md:grid-cols-2">
                <div v-if="action.title">
                  <dt class="font-semibold text-stone-900">Title</dt>
                  <dd class="mt-1">{{ action.title }}</dd>
                </div>
                <div v-if="action.ownerId !== undefined">
                  <dt class="font-semibold text-stone-900">Owner ID</dt>
                  <dd class="mt-1">{{ action.ownerId ?? 'Unassigned' }}</dd>
                </div>
                <div v-if="action.dueOn">
                  <dt class="font-semibold text-stone-900">Due date</dt>
                  <dd class="mt-1">{{ action.dueOn }}</dd>
                </div>
                <div v-if="action.content" class="md:col-span-2">
                  <dt class="font-semibold text-stone-900">Comment</dt>
                  <dd class="mt-1">{{ action.content }}</dd>
                </div>
                <div v-if="action.description" class="md:col-span-2">
                  <dt class="font-semibold text-stone-900">Description</dt>
                  <dd class="mt-1 whitespace-pre-wrap">{{ action.description }}</dd>
                </div>
              </dl>
              <div class="mt-4 flex items-center justify-end gap-3">
                <span
                  v-if="actionStatuses[index]"
                  class="text-sm font-medium"
                  :class="actionStatuses[index] === 'applied' ? 'text-emerald-700' : 'text-stone-500'"
                >
                  {{ actionStatusLabel(actionStatuses[index]) }}
                </span>
                <button
                  type="button"
                  class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-60"
                  :disabled="applyingIndex === index || actionStatuses[index] === 'applied'"
                  @click="$emit('apply-item', { action, index })"
                >
                  {{ applyingIndex === index ? 'Applying...' : (actionStatuses[index] === 'applied' ? 'Applied' : 'Apply') }}
                </button>
              </div>
            </article>
          </div>
        </section>
      </div>
    </div>
  </ModalDialog>
</template>
