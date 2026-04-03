<script setup>
import { computed, ref, watch } from 'vue';
import { renderSessionSummary } from '../utils/workspaceFormatting';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

const props = defineProps({
  open: { type: Boolean, default: false },
  sessions: { type: Array, default: () => [] },
  selectedSessionId: { type: [Number, String, null], default: null },
  currentUserIsOwner: { type: Boolean, default: false },
  isGenerating: { type: Boolean, default: false },
  isSaving: { type: Boolean, default: false },
  isSending: { type: Boolean, default: false },
  errorMessage: { type: String, default: '' },
  noticeMessage: { type: String, default: '' },
});

const emit = defineEmits(['close', 'select', 'generate', 'save', 'send']);

const isEditing = ref(false);
const draftSummary = ref('');

const selectedSession = computed(() => props.sessions.find((session) => session.id === props.selectedSessionId) ?? props.sessions[0] ?? null);

watch(selectedSession, (session) => {
  isEditing.value = false;
  draftSummary.value = session?.summary ?? '';
}, { immediate: true });

const startEditing = () => {
  if (!selectedSession.value || !props.currentUserIsOwner) {
    return;
  }

  draftSummary.value = selectedSession.value.summary ?? '';
  isEditing.value = true;
};

const cancelEditing = () => {
  isEditing.value = false;
  draftSummary.value = selectedSession.value?.summary ?? '';
};

const save = () => {
  if (!selectedSession.value) {
    return;
  }

  emit('save', {
    sessionId: selectedSession.value.id,
    summary: draftSummary.value,
  });
};
</script>

<template>
  <ModalDialog v-if="open" max-width-class="max-w-6xl" @close="$emit('close')">
    <ModalHeader
      eyebrow="Session archive"
      title="Toasting sessions"
      description="Browse previous sessions, review the persisted recap, and edit it if you own the workspace."
      @close="$emit('close')"
    />

    <div class="grid min-h-0 flex-1 gap-6 overflow-hidden px-6 py-6 lg:grid-cols-[20rem_minmax(0,1fr)]">
      <aside class="min-h-0 space-y-3 overflow-y-auto pr-1">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Sessions</p>
        <div v-if="!sessions.length" class="rounded-[1.25rem] border border-stone-200 bg-stone-50 px-4 py-5 text-sm text-stone-500">
          No toasting sessions yet.
        </div>
        <div v-else class="space-y-2">
          <button
            v-for="session in sessions"
            :key="session.id"
            type="button"
            class="block w-full rounded-[1.25rem] border px-4 py-3 text-left transition"
            :class="session.id === selectedSession?.id ? 'border-amber-300 bg-amber-50' : 'border-stone-200 bg-white hover:border-stone-300 hover:bg-stone-50'"
            @click="$emit('select', session.id)"
          >
            <div class="flex items-center justify-between gap-3">
              <span class="text-sm font-semibold text-stone-900">Session #{{ session.id }}</span>
              <span
                class="rounded-full px-2 py-1 text-[0.65rem] font-semibold uppercase tracking-[0.16em]"
                :class="session.isActive ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-600'"
              >
                {{ session.isActive ? 'Live' : 'Closed' }}
              </span>
            </div>
            <p class="mt-2 text-sm text-stone-600">{{ session.startedAtDisplay }}</p>
            <p v-if="session.endedAtDisplay" class="mt-1 text-xs text-stone-500">Ended {{ session.endedAtDisplay }}</p>
            <p class="mt-2 text-xs font-medium" :class="session.summary ? 'text-amber-700' : 'text-stone-400'">
              {{ session.summary ? 'Summary saved' : 'No summary yet' }}
            </p>
          </button>
        </div>
      </aside>

      <section class="min-h-0 space-y-4 overflow-y-auto pr-1">
        <div v-if="errorMessage" class="rounded-[1.25rem] border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-700">
          {{ errorMessage }}
        </div>
        <div v-else-if="noticeMessage" class="rounded-[1.25rem] border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">
          {{ noticeMessage }}
        </div>

        <div v-if="selectedSession" class="space-y-4 pb-1">
          <div class="flex flex-wrap items-start justify-between gap-3 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-5">
            <div class="space-y-2">
              <h3 class="text-xl font-semibold text-stone-950">Session #{{ selectedSession.id }}</h3>
              <div class="flex flex-wrap gap-x-4 gap-y-2 text-sm text-stone-600">
                <span>Started {{ selectedSession.startedAtDisplay }}</span>
                <span v-if="selectedSession.endedAtDisplay">Ended {{ selectedSession.endedAtDisplay }}</span>
                <span>By {{ selectedSession.startedBy.displayName }}</span>
                <span v-if="selectedSession.summaryUpdatedAtDisplay">Edited {{ selectedSession.summaryUpdatedAtDisplay }}</span>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
              <button
                v-if="currentUserIsOwner"
                type="button"
                :class="['rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60', isGenerating ? 'tw-ai-pending' : '']"
                :disabled="isGenerating"
                @click="$emit('generate', selectedSession.id)"
              >
                {{ isGenerating ? 'Generating...' : (selectedSession.summary ? 'Regenerate with Grok' : 'Generate with Grok') }}
              </button>
              <button
                v-if="currentUserIsOwner && selectedSession.summary"
                type="button"
                class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60"
                :disabled="isSending"
                @click="$emit('send', selectedSession.id)"
              >
                {{ isSending ? 'Sending...' : 'Send recap by email' }}
              </button>
              <button
                v-if="currentUserIsOwner && !isEditing"
                type="button"
                class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
                @click="startEditing"
              >
                Edit summary
              </button>
            </div>
          </div>

          <div v-if="isEditing" class="space-y-3 rounded-[1.5rem] border border-stone-200 bg-white p-5">
            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Editable recap</span>
              <textarea
                v-model="draftSummary"
                class="min-h-72 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm text-stone-800"
              ></textarea>
            </label>
            <div class="flex justify-end gap-3">
              <button
                type="button"
                class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
                @click="cancelEditing"
              >
                Cancel
              </button>
              <button
                type="button"
                class="rounded-full bg-amber-500 px-5 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400 disabled:opacity-60"
                :disabled="isSaving"
                @click="save"
              >
                {{ isSaving ? 'Saving...' : 'Save summary' }}
              </button>
            </div>
          </div>

          <div v-else-if="selectedSession.summary" class="rounded-[1.5rem] border border-stone-200 bg-white p-5">
            <div class="tw-markdown text-stone-800" v-html="renderSessionSummary(selectedSession.summary)"></div>
          </div>

          <div v-else class="rounded-[1.5rem] border border-dashed border-stone-300 bg-stone-50 px-5 py-8 text-sm text-stone-500">
            No persisted summary yet for this session.
          </div>
        </div>
      </section>
    </div>
  </ModalDialog>
</template>
