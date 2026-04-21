<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import EmptyState from './EmptyState.vue';
import { renderToastDescription } from '../utils/workspaceFormatting';

const props = defineProps({
  notes: { type: Array, default: () => [] },
  workspace: { type: Object, required: true },
  currentUser: { type: Object, default: null },
  canCreateNote: { type: Boolean, default: true },
  createNote: { type: Function, required: true },
  updateNote: { type: Function, required: true },
  deleteNote: { type: Function, required: true },
  revertNote: { type: Function, required: true },
});

const selectedNoteId = ref(null);
const documentDraft = ref('');
const isHistoryOpen = ref(false);
const isSaving = ref(false);
const saveError = ref('');
const saveState = ref('idle');
let autosaveTimeout = null;
let saveStateTimeout = null;
let saveRequestId = 0;

const normalizeDocument = (title, body) => {
  const cleanTitle = String(title ?? '').trim();
  const cleanBody = String(body ?? '').trim();

  if (!cleanBody) {
    return cleanTitle;
  }

  return `${cleanTitle}\n\n${cleanBody}`;
};

const parseDocument = (value) => {
  const normalized = String(value ?? '').replace(/\r\n/g, '\n');
  const [firstLine = '', ...rest] = normalized.split('\n');
  const title = firstLine.trim();
  const body = rest.join('\n').trim();

  return {
    title,
    body: body || null,
    isValid: title !== '',
  };
};

const sortedNotes = computed(() => [...props.notes]);
const selectedNote = computed(() => sortedNotes.value.find((note) => note.id === selectedNoteId.value) ?? null);
const selectedDocumentSnapshot = computed(() => (
  selectedNote.value ? normalizeDocument(selectedNote.value.title, selectedNote.value.body) : ''
));
const parsedDocumentDraft = computed(() => parseDocument(documentDraft.value));
const noteDirty = computed(() => selectedNote.value && documentDraft.value !== selectedDocumentSnapshot.value);
const canSaveDraft = computed(() => selectedNote.value && noteDirty.value && parsedDocumentDraft.value.isValid);
const historyEntries = computed(() => selectedNote.value?.versions ?? []);
const previewBody = computed(() => {
  const body = parsedDocumentDraft.value.body ?? '';
  return body.trim() ? renderToastDescription(body) : '';
});

const clearAutosaveTimeout = () => {
  if (autosaveTimeout) {
    window.clearTimeout(autosaveTimeout);
    autosaveTimeout = null;
  }
};

const clearSaveStateTimeout = () => {
  if (saveStateTimeout) {
    window.clearTimeout(saveStateTimeout);
    saveStateTimeout = null;
  }
};

const syncDraftFromSelectedNote = () => {
  documentDraft.value = selectedDocumentSnapshot.value;
  saveError.value = '';
  saveState.value = 'idle';
};

const persistSelectedNote = async ({ immediate = false } = {}) => {
  if (!canSaveDraft.value || !selectedNote.value) {
    return true;
  }

  clearAutosaveTimeout();
  clearSaveStateTimeout();
  saveRequestId += 1;
  const requestId = saveRequestId;
  isSaving.value = true;
  saveState.value = immediate ? 'saving' : 'autosaving';
  saveError.value = '';

  try {
    const updatedNote = await props.updateNote(selectedNote.value.id, {
      title: parsedDocumentDraft.value.title,
      body: parsedDocumentDraft.value.body,
      isImportant: !!selectedNote.value.isImportant,
    });

    if (requestId !== saveRequestId) {
      return false;
    }

    selectedNoteId.value = updatedNote?.id ?? selectedNote.value.id;
    saveState.value = 'saved';
    saveStateTimeout = window.setTimeout(() => {
      if (saveState.value === 'saved') {
        saveState.value = 'idle';
      }
    }, 1500);

    return true;
  } catch (error) {
    if (requestId !== saveRequestId) {
      return false;
    }

    saveState.value = 'error';
    saveError.value = error instanceof Error ? error.message : 'Unable to save note.';

    return false;
  } finally {
    if (requestId === saveRequestId) {
      isSaving.value = false;
    }
  }
};

const scheduleAutosave = () => {
  if (!selectedNote.value || !noteDirty.value) {
    clearAutosaveTimeout();
    return;
  }

  clearAutosaveTimeout();
  saveState.value = 'idle';
  saveError.value = '';
  autosaveTimeout = window.setTimeout(() => {
    persistSelectedNote();
  }, 10000);
};

const selectNote = async (noteId) => {
  if (noteId === selectedNoteId.value) {
    return;
  }

  await persistSelectedNote({ immediate: true });
  selectedNoteId.value = noteId;
  isHistoryOpen.value = false;
};

const handleCreateNote = async () => {
  if (!props.canCreateNote) {
    return;
  }

  const createdNote = await props.createNote({
    title: 'Untitled note',
    body: null,
    isImportant: false,
  });

  selectedNoteId.value = createdNote?.id ?? null;
  isHistoryOpen.value = false;
  await nextTick();
  syncDraftFromSelectedNote();
};

const toggleImportant = async () => {
  if (!selectedNote.value) {
    return;
  }

  const nextIsImportant = !selectedNote.value.isImportant;
  const updatedNote = await props.updateNote(selectedNote.value.id, {
    title: parsedDocumentDraft.value.title || selectedNote.value.title,
    body: parsedDocumentDraft.value.body,
    isImportant: nextIsImportant,
  });

  selectedNoteId.value = updatedNote?.id ?? selectedNote.value.id;
};

const handleDeleteNote = async () => {
  if (!selectedNote.value || !selectedNote.value.currentUserCanDelete) {
    return;
  }

  const confirmed = window.confirm(`Delete "${selectedNote.value.title}"?`);
  if (!confirmed) {
    return;
  }

  const deletedId = selectedNote.value.id;
  await props.deleteNote(deletedId);
  const remainingNotes = sortedNotes.value.filter((note) => note.id !== deletedId);
  selectedNoteId.value = remainingNotes[0]?.id ?? null;
  isHistoryOpen.value = false;
};

const handleRevertVersion = async (versionId) => {
  if (!selectedNote.value) {
    return;
  }

  const revertedNote = await props.revertNote(selectedNote.value.id, versionId);
  selectedNoteId.value = revertedNote?.id ?? selectedNote.value.id;
  isHistoryOpen.value = false;
};

watch(sortedNotes, (notes) => {
  if (!notes.length) {
    selectedNoteId.value = null;
    documentDraft.value = '';
    return;
  }

  if (!notes.some((note) => note.id === selectedNoteId.value)) {
    selectedNoteId.value = notes[0].id;
  }
}, { immediate: true });

watch(selectedNote, () => {
  syncDraftFromSelectedNote();
}, { immediate: true });

watch(documentDraft, () => {
  scheduleAutosave();
});

onBeforeUnmount(() => {
  clearAutosaveTimeout();
  clearSaveStateTimeout();
});
</script>

<template>
  <div class="grid gap-4 lg:grid-cols-[20rem_minmax(0,1fr)]">
    <aside class="overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white">
      <div class="flex items-center justify-between border-b border-stone-200 px-4 py-4">
        <div>
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Workspace notes</p>
          <p class="mt-1 text-sm text-stone-600">{{ sortedNotes.length }} note{{ sortedNotes.length > 1 ? 's' : '' }}</p>
        </div>
        <button
          type="button"
          class="inline-grid h-10 w-10 place-items-center rounded-full bg-amber-200 text-amber-900 shadow-sm transition hover:bg-amber-300 disabled:cursor-not-allowed disabled:opacity-50"
          :disabled="!canCreateNote"
          @click="handleCreateNote"
        >
          <i class="fa-solid fa-plus text-sm" aria-hidden="true"></i>
          <span class="sr-only">Create note</span>
        </button>
      </div>

      <div v-if="!sortedNotes.length" class="p-5">
        <EmptyState :message="canCreateNote ? 'No note yet.' : 'No note yet. Manual creation is disabled during live toasting.'" />
      </div>

      <div v-else class="divide-y divide-stone-100">
        <button
          v-for="note in sortedNotes"
          :key="note.id"
          type="button"
          class="flex w-full items-start gap-3 px-4 py-4 text-left transition hover:bg-stone-50"
          :class="selectedNoteId === note.id ? 'bg-amber-50/70' : 'bg-white'"
          @click="selectNote(note.id)"
        >
          <span class="mt-1 inline-grid h-8 w-8 shrink-0 place-items-center rounded-full border text-sm" :class="note.isImportant ? 'border-amber-300 bg-amber-100 text-amber-700' : 'border-stone-200 bg-white text-stone-300'">
            <i class="fa-solid fa-star" aria-hidden="true"></i>
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-stone-900">{{ note.title }}</span>
            <span class="mt-1 block line-clamp-2 text-sm text-stone-600">{{ note.body || 'No content yet.' }}</span>
            <span class="mt-2 block text-xs font-medium text-stone-500">Updated {{ note.updatedAtDisplay }}</span>
          </span>
        </button>
      </div>
    </aside>

    <section class="overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white">
      <div v-if="selectedNote" class="grid min-h-[38rem] lg:grid-cols-[minmax(0,1fr)_18rem]">
        <div class="border-b border-stone-200 lg:border-b-0 lg:border-r">
          <div class="flex flex-wrap items-start justify-between gap-3 border-b border-stone-200 px-5 py-5">
            <div class="space-y-1">
              <p class="text-sm font-semibold text-stone-900">{{ selectedNote.author.displayName }}</p>
              <p class="text-xs text-stone-500">Created {{ selectedNote.createdAtDisplay }}</p>
              <button type="button" class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.16em] text-stone-500 transition hover:text-stone-900" @click="isHistoryOpen = !isHistoryOpen">
                <i class="fa-solid fa-clock-rotate-left text-[11px]" aria-hidden="true"></i>
                <span>History</span>
              </button>
            </div>

            <div class="flex items-center gap-2">
              <button
                type="button"
                class="inline-grid h-10 w-10 place-items-center rounded-full border transition"
                :class="selectedNote.isImportant ? 'border-amber-300 bg-amber-100 text-amber-700' : 'border-stone-200 bg-white text-stone-400 hover:text-amber-700'"
                @click="toggleImportant"
              >
                <i class="fa-solid fa-star text-sm" aria-hidden="true"></i>
                <span class="sr-only">{{ selectedNote.isImportant ? 'Unmark important' : 'Mark important' }}</span>
              </button>
              <button
                v-if="selectedNote.currentUserCanDelete"
                type="button"
                class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 bg-white text-stone-500 transition hover:border-red-200 hover:text-red-700"
                @click="handleDeleteNote"
              >
                <i class="fa-solid fa-trash text-sm" aria-hidden="true"></i>
                <span class="sr-only">Delete note</span>
              </button>
            </div>
          </div>

          <div class="space-y-4 px-5 py-5">
            <div class="flex flex-wrap items-center justify-between gap-3">
              <div class="text-xs font-medium text-stone-500">
                <span v-if="saveState === 'autosaving'">Autosaving…</span>
                <span v-else-if="saveState === 'saving'">Saving…</span>
                <span v-else-if="saveState === 'saved'">Saved</span>
                <span v-else-if="saveState === 'error'" class="text-red-600">{{ saveError }}</span>
                <span v-else>Updated {{ selectedNote.updatedAtDisplay }}</span>
              </div>
              <button
                type="button"
                class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-50"
                :disabled="!canSaveDraft || isSaving"
                @click="persistSelectedNote({ immediate: true })"
              >
                Save now
              </button>
            </div>

            <textarea
              v-model="documentDraft"
              class="min-h-[24rem] w-full resize-none rounded-[1.5rem] border border-stone-200 bg-stone-50 px-5 py-5 text-base leading-7 text-stone-900 outline-none transition focus:border-amber-300 focus:bg-white"
              placeholder="Title on the first line, then the note body in Markdown."
              @blur="persistSelectedNote({ immediate: true })"
            ></textarea>

            <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50/70 px-5 py-5">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Preview</p>
              <h2 class="mt-3 text-2xl font-semibold tracking-tight text-stone-950">{{ parsedDocumentDraft.title || 'Untitled note' }}</h2>
              <div v-if="previewBody" class="mt-5 tw-markdown text-stone-800" v-html="previewBody"></div>
              <p v-else class="mt-5 text-sm text-stone-500">No body content yet.</p>
            </div>
          </div>
        </div>

        <aside class="border-t border-stone-200 bg-stone-50/80 lg:border-t-0" :class="isHistoryOpen ? '' : 'hidden lg:block'">
          <div class="border-b border-stone-200 px-5 py-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">History</p>
            <p class="mt-1 text-sm text-stone-600">{{ historyEntries.length }} saved version{{ historyEntries.length > 1 ? 's' : '' }}</p>
          </div>

          <div class="max-h-[38rem] overflow-y-auto px-4 py-4">
            <div v-if="!historyEntries.length" class="rounded-2xl border border-dashed border-stone-200 bg-white px-4 py-5 text-sm text-stone-500">
              No history yet.
            </div>

            <div v-else class="space-y-3">
              <div
                v-for="version in historyEntries"
                :key="version.id"
                class="rounded-[1.25rem] border border-stone-200 bg-white px-4 py-4"
              >
                <div class="flex items-start justify-between gap-3">
                  <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-stone-900">{{ version.title }}</p>
                    <p class="mt-1 text-xs text-stone-500">{{ version.author.displayName }} · {{ version.recordedAtDisplay }}</p>
                  </div>
                  <button
                    type="button"
                    class="rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
                    @click="handleRevertVersion(version.id)"
                  >
                    Revert
                  </button>
                </div>
                <p class="mt-3 line-clamp-4 whitespace-pre-wrap text-sm text-stone-600">{{ version.body || 'No body content.' }}</p>
              </div>
            </div>
          </div>
        </aside>
      </div>

      <div v-else class="p-8">
        <EmptyState :message="canCreateNote ? 'Select a note or create one.' : 'No note selected.'" />
      </div>
    </section>
  </div>
</template>
