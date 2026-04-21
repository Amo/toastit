<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import DatePickerField from './DatePickerField.vue';
import KeyboardHint from './KeyboardHint.vue';
import MarkdownRichTextEditor from './MarkdownRichTextEditor.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

const props = defineProps({
  open: { type: Boolean, required: true },
  itemForm: { type: Object, required: true },
  participants: { type: Array, default: () => [] },
  title: { type: String, default: 'Toast details' },
  actionLabel: { type: String, default: 'Create toast' },
  isRefining: { type: Boolean, default: false },
  canUndoRefinement: { type: Boolean, default: false },
  isEditing: { type: Boolean, default: false },
  aiRefinementPending: { type: Boolean, default: false },
});

const emit = defineEmits(['close', 'create', 'refine', 'undo-refine', 'title-input', 'title-keydown', 'update:title', 'update:ownerId', 'update:dueOn', 'update:description', 'update:aiImproveEnabled']);

const titleInput = ref(null);
const isMobileViewport = ref(false);

const syncViewport = () => {
  isMobileViewport.value = window.innerWidth < 1024;
};

const resizeTitleField = (target = titleInput.value) => {
  if (!(target instanceof HTMLElement)) {
    return;
  }

  target.style.height = 'auto';
  target.style.height = `${Math.max(target.scrollHeight, 48)}px`;
};

const handleTitleInput = (event) => {
  emit('update:title', event.target.value);
  resizeTitleField(event.target);
};

const focusTitle = async () => {
  await nextTick();
  resizeTitleField();
  titleInput.value?.focus();
};

const emitRefineRequest = () => {
  const currentTitle = titleInput.value?.value ?? props.itemForm?.title ?? '';
  const currentDescription = props.itemForm?.description ?? '';

  emit('refine', {
    title: currentTitle,
    description: currentDescription,
    ownerId: props.itemForm?.ownerId ?? '',
    dueOn: props.itemForm?.dueOn ?? '',
  });
};

watch(() => props.open, async (isOpen) => {
  if (!isOpen) return;

  await nextTick();
  window.setTimeout(() => {
    resizeTitleField();
    titleInput.value?.focus();
  }, 50);
});

watch(() => props.itemForm?.title, async () => {
  await nextTick();
  resizeTitleField();
});

defineExpose({
  focusTitle,
});

onMounted(() => {
  syncViewport();
  window.addEventListener('resize', syncViewport);
});

onUnmounted(() => {
  window.removeEventListener('resize', syncViewport);
});
</script>

<template>
  <ModalDialog v-if="open" max-width-class="max-w-4xl" z-index-class="z-[110]" @close="$emit('close')">
    <ModalHeader eyebrow="New toast" :title="title" @close="$emit('close')" />

    <div class="relative space-y-4 overflow-y-auto px-6 py-6" @keydown="$emit('title-keydown', $event)">
      <template v-if="isMobileViewport">
        <template v-if="isEditing">
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Title</span>
            <textarea
              ref="titleInput"
              class="min-h-12 resize-none overflow-hidden rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base leading-6"
              :value="itemForm.title"
              rows="1"
              autofocus
              placeholder="Toast title"
              @input="handleTitleInput"
              @keydown="$emit('title-input', $event)"
            />
          </label>

          <div class="flex flex-wrap items-center gap-2">
            <span
              v-if="aiRefinementPending"
              class="inline-flex items-center rounded-full border border-sky-200 bg-sky-50 px-3 py-1 text-xs font-semibold uppercase tracking-[0.14em] text-sky-700"
            >
              IA pending
            </span>
          </div>

          <div class="grid gap-4">
            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Assignee</span>
              <select class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="itemForm.ownerId" @change="$emit('update:ownerId', $event.target.value)">
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
            <MarkdownRichTextEditor
              :model-value="itemForm.description"
              min-height-class="min-h-52"
              placeholder="Describe what needs to be done..."
              @update:model-value="$emit('update:description', $event)"
            />
          </label>
        </template>
        <template v-else>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Toast</span>
            <MarkdownRichTextEditor
              :model-value="itemForm.description"
              min-height-class="min-h-52"
              placeholder="Describe what needs to be done..."
              @update:model-value="$emit('update:description', $event)"
              @keydown="$emit('title-input', $event)"
            />
          </label>
        </template>

      </template>
      <template v-else>
        <label class="grid gap-2 text-sm font-medium text-stone-700">
          <span>Title</span>
          <textarea
            ref="titleInput"
            class="min-h-12 resize-none overflow-hidden rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base leading-6"
            :value="itemForm.title"
            rows="1"
            autofocus
            placeholder="New toast"
            @input="handleTitleInput"
            @keydown="$emit('title-input', $event)"
          />
        </label>
        <div class="grid gap-4 md:grid-cols-2">
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Assignee</span>
            <select class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="itemForm.ownerId" @change="$emit('update:ownerId', $event.target.value)">
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
          <MarkdownRichTextEditor
            :model-value="itemForm.description"
            min-height-class="min-h-48"
            placeholder="Add details or description"
            @update:model-value="$emit('update:description', $event)"
          />
        </label>
      </template>

      <label v-if="isEditing" class="inline-flex items-center gap-3 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3 text-sm font-medium text-stone-700">
        <input
          class="h-4 w-4 rounded border-stone-300 text-amber-500 focus:ring-amber-400"
          type="checkbox"
          :checked="!!itemForm.aiImproveEnabled"
          @change="$emit('update:aiImproveEnabled', $event.target.checked)"
        >
        <span>IA improve</span>
      </label>

      <div class="flex items-center justify-between gap-3">
        <KeyboardHint>Press Cmd+Enter or Ctrl+Enter to create this toast.</KeyboardHint>
        <div class="flex justify-end gap-3">
          <template v-if="!isMobileViewport">
            <button
              type="button"
              :class="['inline-grid h-12 w-12 place-items-center rounded-full border border-stone-200 bg-white text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60', isRefining ? 'tw-ai-pending' : '']"
              :disabled="isRefining"
              title="Improve draft with xAI"
              @click="emitRefineRequest"
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
          </template>
          <button type="button" class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="$emit('close')">Cancel</button>
          <button type="button" class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300" @click="$emit('create')">{{ actionLabel }}</button>
        </div>
      </div>
      <div v-if="isRefining" class="inline-flex items-center gap-2 rounded-full border border-amber-200 bg-amber-50 px-3 py-1.5 text-xs font-medium text-amber-900">
        <i class="fa-solid fa-wand-sparkles tw-ai-pending" aria-hidden="true"></i>
        <span>AI is refining the toast before save…</span>
      </div>
    </div>
  </ModalDialog>
</template>
