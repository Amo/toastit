<script setup>
import { nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import DatePickerField from './DatePickerField.vue';
import KeyboardHint from './KeyboardHint.vue';
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

const emit = defineEmits(['close', 'create', 'refine', 'undo-refine', 'title-input', 'title-keydown', 'update:title', 'update:ownerId', 'update:dueOn', 'update:description']);

const titleInput = ref(null);
const descriptionInput = ref(null);
const isMobileViewport = ref(false);
const isSpeechSupported = ref(false);
const isRecordingSpeech = ref(false);
const speechError = ref('');
let speechRecognition = null;
let speechFinalTranscript = '';

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

const emitSpeechText = (value) => {
  if (isMobileViewport.value) {
    emit('update:description', value);
    return;
  }

  emit('update:title', value);
  nextTick(() => {
    resizeTitleField();
  });
};

const getCurrentSpeechTargetValue = () => (
  isMobileViewport.value
    ? String(descriptionInput.value?.value ?? props.itemForm?.description ?? '')
    : String(titleInput.value?.value ?? props.itemForm?.title ?? '')
);

const stopSpeechCapture = () => {
  if (speechRecognition && isRecordingSpeech.value) {
    speechRecognition.stop();
  }
};

const toggleSpeechCapture = () => {
  if (!speechRecognition) {
    speechError.value = 'Speech recognition is not supported in this browser.';
    return;
  }

  if (isRecordingSpeech.value) {
    stopSpeechCapture();
    return;
  }

  speechError.value = '';
  speechFinalTranscript = getCurrentSpeechTargetValue();
  speechRecognition.start();
};

const focusTitle = async () => {
  await nextTick();
  resizeTitleField();
  titleInput.value?.focus();
};

const emitRefineRequest = () => {
  const currentTitle = titleInput.value?.value ?? props.itemForm?.title ?? '';
  const currentDescription = descriptionInput.value?.value ?? props.itemForm?.description ?? '';

  emit('refine', {
    title: currentTitle,
    description: currentDescription,
    ownerId: props.itemForm?.ownerId ?? '',
    dueOn: props.itemForm?.dueOn ?? '',
  });
};

watch(() => props.open, async (isOpen) => {
  if (!isOpen) {
    stopSpeechCapture();
    return;
  }

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

  const RecognitionCtor = window.SpeechRecognition || window.webkitSpeechRecognition;
  if (!RecognitionCtor) {
    isSpeechSupported.value = false;
    return;
  }

  isSpeechSupported.value = true;
  speechRecognition = new RecognitionCtor();
  speechRecognition.continuous = true;
  speechRecognition.interimResults = true;
  speechRecognition.lang = navigator.language || 'en-US';

  speechRecognition.onstart = () => {
    isRecordingSpeech.value = true;
  };

  speechRecognition.onend = () => {
    isRecordingSpeech.value = false;
  };

  speechRecognition.onerror = (event) => {
    isRecordingSpeech.value = false;
    speechError.value = event?.error === 'not-allowed'
      ? 'Microphone permission denied.'
      : 'Speech recognition failed.';
  };

  speechRecognition.onresult = (event) => {
    const chunks = [];
    for (let index = event.resultIndex; index < event.results.length; index += 1) {
      const transcript = event.results[index]?.[0]?.transcript;
      if (!transcript) {
        continue;
      }

      chunks.push(transcript.trim());
      if (event.results[index].isFinal) {
        speechFinalTranscript = `${speechFinalTranscript} ${transcript.trim()}`.trim();
      }
    }

    const interim = chunks.join(' ').trim();
    emitSpeechText(interim ? `${speechFinalTranscript} ${interim}`.trim() : speechFinalTranscript);
  };
});

onUnmounted(() => {
  window.removeEventListener('resize', syncViewport);
  if (speechRecognition) {
    speechRecognition.onstart = null;
    speechRecognition.onend = null;
    speechRecognition.onerror = null;
    speechRecognition.onresult = null;
    speechRecognition.stop();
    speechRecognition = null;
  }
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
            <textarea
              ref="descriptionInput"
              class="min-h-52 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base leading-6"
              :value="itemForm.description"
              placeholder="Describe what needs to be done..."
              @input="$emit('update:description', $event.target.value)"
            />
          </label>
        </template>
        <template v-else>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Toast</span>
            <textarea
              ref="descriptionInput"
              class="min-h-52 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base leading-6"
              :value="itemForm.description"
              autofocus
              placeholder="Describe what needs to be done..."
              @input="$emit('update:description', $event.target.value)"
              @keydown="$emit('title-input', $event)"
            />
          </label>
        </template>

        <div class="flex items-center justify-end">
          <button
            v-if="isSpeechSupported"
            type="button"
            :class="['inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition', isRecordingSpeech ? 'border-red-300 bg-red-50 text-red-700' : 'border-stone-200 bg-white text-stone-700 hover:border-stone-300 hover:text-stone-950']"
            @click="toggleSpeechCapture"
          >
            <i :class="isRecordingSpeech ? 'fa-solid fa-stop' : 'fa-solid fa-microphone'" aria-hidden="true"></i>
            <span>{{ isRecordingSpeech ? 'Stop recording' : 'Dictate' }}</span>
          </button>
        </div>
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
        <div class="flex items-center justify-end">
          <button
            v-if="isSpeechSupported"
            type="button"
            :class="['inline-flex items-center gap-2 rounded-full border px-3 py-2 text-xs font-semibold transition', isRecordingSpeech ? 'border-red-300 bg-red-50 text-red-700' : 'border-stone-200 bg-white text-stone-700 hover:border-stone-300 hover:text-stone-950']"
            @click="toggleSpeechCapture"
          >
            <i :class="isRecordingSpeech ? 'fa-solid fa-stop' : 'fa-solid fa-microphone'" aria-hidden="true"></i>
            <span>{{ isRecordingSpeech ? 'Stop recording' : 'Dictate' }}</span>
          </button>
        </div>

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
          <textarea ref="descriptionInput" class="min-h-48 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="itemForm.description" placeholder="Add details or description" @input="$emit('update:description', $event.target.value)" />
        </label>
      </template>

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
      <div v-if="speechError" class="inline-flex items-center gap-2 rounded-full border border-rose-200 bg-rose-50 px-3 py-1.5 text-xs font-medium text-rose-800">
        <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
        <span>{{ speechError }}</span>
      </div>
    </div>
  </ModalDialog>
</template>
