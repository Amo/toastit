<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { EditorContent, useEditor } from '@tiptap/vue-3';
import StarterKit from '@tiptap/starter-kit';
import { Markdown } from '@tiptap/markdown';
import EmptyState from './EmptyState.vue';

const props = defineProps({
  notes: { type: Array, default: () => [] },
  workspace: { type: Object, required: true },
  currentUser: { type: Object, default: null },
  currentUserIsOwner: { type: Boolean, default: false },
  otherWorkspaces: { type: Array, default: () => [] },
  canCreateNote: { type: Boolean, default: true },
  createNote: { type: Function, required: true },
  fetchNote: { type: Function, required: true },
  updateNote: { type: Function, required: true },
  deleteNote: { type: Function, required: true },
  revertNote: { type: Function, required: true },
  transferNote: { type: Function, required: true },
});

const selectedNoteId = ref(null);
const noteTitle = ref('');
const noteBody = ref(null);
const isHistoryOpen = ref(false);
const isTransferFormOpen = ref(false);
const isSaving = ref(false);
const saveError = ref('');
const saveState = ref('idle');
const isMobileViewport = ref(false);
const transferTargetWorkspaceId = ref('');
const isSyncingEditorContent = ref(false);
let autosaveTimeout = null;
let saveStateTimeout = null;
let saveRequestId = 0;

const normalizeTitle = (value) => String(value ?? '').trim();
const normalizeBody = (value) => {
  const normalized = String(value ?? '').replace(/\r\n/g, '\n').trim();
  return normalized === '' ? null : normalized;
};

const sortedNotes = computed(() => [...props.notes]);
const selectedNote = computed(() => sortedNotes.value.find((note) => note.id === selectedNoteId.value) ?? null);
const selectedTitleSnapshot = computed(() => normalizeTitle(selectedNote.value?.title ?? ''));
const selectedBodySnapshot = computed(() => normalizeBody(selectedNote.value?.body ?? null));
const noteDirty = computed(() => (
  !!selectedNote.value
  && (
    normalizeTitle(noteTitle.value) !== selectedTitleSnapshot.value
    || normalizeBody(noteBody.value) !== selectedBodySnapshot.value
  )
));
const canSaveDraft = computed(() => !!selectedNote.value && noteDirty.value && normalizeTitle(noteTitle.value) !== '');
const historyEntries = computed(() => selectedNote.value?.versions ?? []);
const canTransferSelectedNote = computed(() => (
  !!selectedNote.value && props.currentUserIsOwner && props.otherWorkspaces.length > 0 && transferTargetWorkspaceId.value !== ''
));
const isNoteSheetOpen = computed(() => !!selectedNote.value);
const isMobileImmersiveActive = computed(() => isMobileViewport.value && isNoteSheetOpen.value);

const editor = useEditor({
  extensions: [
    StarterKit,
    Markdown,
  ],
  content: '',
  contentType: 'markdown',
  editorProps: {
    attributes: {
      class: 'note-editor-content min-h-[18rem] outline-none',
    },
  },
  onUpdate: ({ editor: currentEditor }) => {
    if (isSyncingEditorContent.value) {
      return;
    }

    noteBody.value = normalizeBody(currentEditor.getMarkdown());
  },
  onBlur: () => {
    persistSelectedNote({ immediate: true });
  },
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

const syncEditorFromState = () => {
  if (!editor.value) {
    return;
  }

  isSyncingEditorContent.value = true;
  editor.value.commands.setContent(noteBody.value ?? '', { contentType: 'markdown' });
  isSyncingEditorContent.value = false;
};

const syncDraftFromSelectedNote = () => {
  noteTitle.value = selectedTitleSnapshot.value;
  noteBody.value = selectedBodySnapshot.value;
  transferTargetWorkspaceId.value = props.otherWorkspaces[0]?.id ? String(props.otherWorkspaces[0].id) : '';
  isTransferFormOpen.value = false;
  saveError.value = '';
  saveState.value = 'idle';
  syncEditorFromState();
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
      title: normalizeTitle(noteTitle.value),
      body: normalizeBody(noteBody.value),
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
  await persistSelectedNote({ immediate: true });
  const refreshedNote = await props.fetchNote(noteId);
  selectedNoteId.value = refreshedNote?.id ?? noteId;
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
  editor.value?.commands.focus('end');
};

const toggleImportant = async () => {
  if (!selectedNote.value) {
    return;
  }

  const updatedNote = await props.updateNote(selectedNote.value.id, {
    title: normalizeTitle(noteTitle.value) || selectedNote.value.title,
    body: normalizeBody(noteBody.value),
    isImportant: !selectedNote.value.isImportant,
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
  selectedNoteId.value = null;
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

const handleTransferNote = async () => {
  if (!selectedNote.value || !canTransferSelectedNote.value) {
    return;
  }

  const persisted = await persistSelectedNote({ immediate: true });
  if (!persisted) {
    return;
  }

  const targetWorkspace = props.otherWorkspaces.find((workspace) => String(workspace.id) === transferTargetWorkspaceId.value);
  const targetWorkspaceName = targetWorkspace?.name ?? 'the target workspace';
  const confirmed = window.confirm(`Move "${selectedNote.value.title}" to ${targetWorkspaceName}?`);
  if (!confirmed) {
    return;
  }

  await props.transferNote(selectedNote.value.id, Number(transferTargetWorkspaceId.value));
  selectedNoteId.value = null;
  isHistoryOpen.value = false;
  isTransferFormOpen.value = false;
};

const closeNoteSheet = async () => {
  const persisted = await persistSelectedNote({ immediate: true });
  if (!persisted) {
    return;
  }

  selectedNoteId.value = null;
  isHistoryOpen.value = false;
};

const handleGlobalKeydown = (event) => {
  if (event.key !== 'Escape' || !isNoteSheetOpen.value) {
    return;
  }

  event.preventDefault();
  closeNoteSheet();
};

const syncViewport = () => {
  if (typeof window === 'undefined') {
    isMobileViewport.value = false;
    return;
  }

  isMobileViewport.value = window.innerWidth < 1024;
};

const syncMobileImmersiveState = () => {
  window.dispatchEvent(new CustomEvent('toastit:mobile-immersive-state', {
    detail: {
      active: isMobileImmersiveActive.value,
    },
  }));
};

watch(sortedNotes, (notes) => {
  if (!notes.length) {
    selectedNoteId.value = null;
    noteTitle.value = '';
    noteBody.value = null;
    syncEditorFromState();
    return;
  }

  if (!notes.some((note) => note.id === selectedNoteId.value)) {
    selectedNoteId.value = null;
  }
}, { immediate: true });

watch(selectedNote, () => {
  syncDraftFromSelectedNote();
}, { immediate: true });

watch(editor, (currentEditor) => {
  if (currentEditor) {
    syncEditorFromState();
  }
});

watch(noteTitle, () => {
  scheduleAutosave();
});

watch(noteBody, () => {
  scheduleAutosave();
});

watch([isMobileImmersiveActive, isNoteSheetOpen], () => {
  syncMobileImmersiveState();
});

onMounted(() => {
  syncViewport();
  syncMobileImmersiveState();
  window.addEventListener('keydown', handleGlobalKeydown);
  window.addEventListener('resize', syncViewport);
});

onBeforeUnmount(() => {
  window.removeEventListener('keydown', handleGlobalKeydown);
  window.removeEventListener('resize', syncViewport);
  window.dispatchEvent(new CustomEvent('toastit:mobile-immersive-state', { detail: { active: false } }));
  clearAutosaveTimeout();
  clearSaveStateTimeout();
});
</script>

<template>
  <div class="relative">
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
          :class="selectedNoteId === note.id && isNoteSheetOpen ? 'bg-amber-50/70' : 'bg-white'"
          @click="selectNote(note.id)"
        >
          <span class="mt-1 inline-grid h-8 w-8 shrink-0 place-items-center rounded-full border text-sm" :class="note.isImportant ? 'border-amber-300 bg-amber-100 text-amber-700' : 'border-stone-200 bg-white text-stone-300'">
            <i class="fa-solid fa-star" aria-hidden="true"></i>
          </span>
          <span class="min-w-0 flex-1">
            <span class="block truncate text-sm font-semibold text-stone-900">{{ note.title }}</span>
            <span class="mt-1 block text-xs font-medium text-stone-500">Updated {{ note.updatedAtDisplay }}</span>
          </span>
          <span class="mt-1 inline-grid h-8 w-8 shrink-0 place-items-center rounded-full text-stone-400">
            <i class="fa-solid fa-arrow-right text-xs" aria-hidden="true"></i>
          </span>
        </button>
      </div>
    </aside>

    <transition
      enter-active-class="transition duration-200 ease-out"
      enter-from-class="opacity-0"
      enter-to-class="opacity-100"
      leave-active-class="transition duration-150 ease-in"
      leave-from-class="opacity-100"
      leave-to-class="opacity-0"
    >
      <div
        v-if="isNoteSheetOpen"
        class="fixed inset-0 z-40 bg-stone-950/35 backdrop-blur-[1px]"
        @click.self="closeNoteSheet"
      ></div>
    </transition>

    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="translate-x-full opacity-0"
      enter-to-class="translate-x-0 opacity-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="translate-x-0 opacity-100"
      leave-to-class="translate-x-full opacity-0"
    >
      <section
        v-if="selectedNote"
        class="fixed inset-y-0 right-0 z-50 flex w-full max-w-[min(100vw,84rem)] flex-col border-l border-stone-200 bg-stone-50 shadow-[-24px_0_60px_rgba(28,25,23,0.18)]"
      >
        <div class="border-b border-stone-200 bg-white px-5 py-5">
          <div class="flex items-start justify-between gap-4">
            <button
              v-if="isMobileViewport"
              type="button"
              class="inline-grid h-10 w-10 shrink-0 place-items-center rounded-full border border-stone-200 bg-white text-stone-500 transition hover:border-stone-300 hover:text-stone-900"
              @click="closeNoteSheet"
            >
              <i class="fa-solid fa-arrow-left text-sm" aria-hidden="true"></i>
              <span class="sr-only">Back to notes</span>
            </button>

            <div class="flex items-center gap-2">
              <button
                v-if="currentUserIsOwner && otherWorkspaces.length"
                type="button"
                class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 bg-white text-stone-500 transition hover:border-stone-300 hover:text-stone-900"
                @click="isTransferFormOpen = !isTransferFormOpen"
              >
                <i class="fa-solid fa-right-left text-sm" aria-hidden="true"></i>
                <span class="sr-only">{{ isTransferFormOpen ? 'Hide move note form' : 'Move note' }}</span>
              </button>
              <button
                type="button"
                class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 bg-white text-stone-500 transition hover:border-stone-300 hover:text-stone-900"
                @click="isHistoryOpen = !isHistoryOpen"
              >
                <i class="fa-solid fa-clock-rotate-left text-sm" aria-hidden="true"></i>
                <span class="sr-only">{{ isHistoryOpen ? 'Hide history' : 'Show history' }}</span>
              </button>
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
              <button
                v-if="!isMobileViewport"
                type="button"
                class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 bg-white text-stone-500 transition hover:border-stone-300 hover:text-stone-900"
                @click="closeNoteSheet"
              >
                <i class="fa-solid fa-xmark text-base" aria-hidden="true"></i>
                <span class="sr-only">Close note</span>
              </button>
            </div>
          </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto">
          <div class="space-y-4 px-5 py-5">
              <div class="min-w-0 space-y-2">
                <input
                  v-model="noteTitle"
                  type="text"
                  class="w-full rounded-[1.25rem] border border-stone-200 bg-white px-4 py-3 text-2xl font-semibold tracking-tight text-stone-950 outline-none transition focus:border-amber-300"
                  placeholder="Note title"
                  @blur="persistSelectedNote({ immediate: true })"
                />
              </div>

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

              <div v-if="currentUserIsOwner && otherWorkspaces.length && isTransferFormOpen" class="flex flex-wrap items-center gap-3 rounded-[1.25rem] border border-stone-200 bg-white px-4 py-4">
                <div class="min-w-0 flex-1">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Move note</p>
                  <p class="mt-1 text-sm text-stone-600">Available only because you are admin of this workspace.</p>
                </div>
                <select v-model="transferTargetWorkspaceId" class="min-w-[13rem] rounded-2xl border border-stone-200 bg-white px-4 py-2.5 text-sm text-stone-700">
                  <option value="" disabled>Select workspace</option>
                  <option v-for="candidate in otherWorkspaces" :key="candidate.id" :value="String(candidate.id)">{{ candidate.name }}</option>
                </select>
                <button
                  type="button"
                  class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-50"
                  :disabled="!canTransferSelectedNote"
                  @click="handleTransferNote"
                >
                  Move
                </button>
              </div>

              <section v-if="isHistoryOpen" class="space-y-3">
                <div class="flex items-center justify-between gap-3">
                  <div>
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">History</p>
                    <p class="mt-1 text-sm text-stone-600">{{ historyEntries.length }} saved version{{ historyEntries.length > 1 ? 's' : '' }}</p>
                  </div>
                  <button
                    type="button"
                    class="rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
                    @click="isHistoryOpen = false"
                  >
                    Hide
                  </button>
                </div>

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
              </section>

              <div class="space-y-3">
                <div class="rounded-[1.5rem] border border-stone-200 bg-white px-6 py-5">
                  <div class="flex flex-wrap items-center justify-between gap-3 border-b border-stone-100 pb-3">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Body</p>
                    <p class="text-xs text-stone-500">Markdown shortcuts are enabled: `#`, `-`, `1.`, `>`, `**bold**`, `*italic*`.</p>
                  </div>
                  <EditorContent
                    v-if="editor"
                    :editor="editor"
                    class="note-editor mt-4 text-[1.02rem] leading-8 text-stone-900"
                  />
                </div>
              </div>
          </div>
        </div>
      </section>
    </transition>
  </div>
</template>

<style scoped>
.note-editor :deep(.tiptap) {
  color: rgb(28 25 23);
  min-height: 18rem;
  white-space: pre-wrap;
}

.note-editor :deep(.tiptap > * + *) {
  margin-top: 0.75rem;
}

.note-editor :deep(.tiptap h1),
.note-editor :deep(.tiptap h2),
.note-editor :deep(.tiptap h3),
.note-editor :deep(.tiptap h4),
.note-editor :deep(.tiptap h5) {
  color: rgb(12 10 9);
  font-weight: 700;
  letter-spacing: -0.02em;
  line-height: 1.1;
}

.note-editor :deep(.tiptap h1) {
  font-size: 2rem;
  margin-top: 1.75rem;
}

.note-editor :deep(.tiptap h2) {
  font-size: 1.6rem;
  margin-top: 1.5rem;
}

.note-editor :deep(.tiptap h3) {
  font-size: 1.3rem;
  margin-top: 1.25rem;
}

.note-editor :deep(.tiptap h4) {
  font-size: 1.1rem;
  margin-top: 1rem;
}

.note-editor :deep(.tiptap h5) {
  font-size: 1rem;
  margin-top: 1rem;
  text-transform: uppercase;
  letter-spacing: 0.06em;
}

.note-editor :deep(.tiptap p) {
  line-height: 1.8;
}

.note-editor :deep(.tiptap strong) {
  color: rgb(12 10 9);
  font-weight: 700;
}

.note-editor :deep(.tiptap em) {
  font-style: italic;
}

.note-editor :deep(.tiptap a) {
  color: rgb(180 83 9);
  text-decoration: underline;
  text-decoration-thickness: 1.5px;
  text-underline-offset: 0.15em;
}

.note-editor :deep(.tiptap ul),
.note-editor :deep(.tiptap ol) {
  padding-left: 1.5rem;
}

.note-editor :deep(.tiptap li) {
  margin-top: 0.35rem;
}

.note-editor :deep(.tiptap ul) {
  list-style: disc;
}

.note-editor :deep(.tiptap ol) {
  list-style: decimal;
}

.note-editor :deep(.tiptap blockquote) {
  border-left: 3px solid rgb(251 191 36);
  color: rgb(87 83 78);
  padding-left: 1rem;
}

.note-editor :deep(.tiptap hr) {
  border: 0;
  border-top: 1px solid rgb(231 229 228);
  margin: 1.5rem 0;
}

.note-editor :deep(.tiptap code) {
  background: rgb(245 245 244);
  border-radius: 0.375rem;
  font-size: 0.92em;
  padding: 0.15rem 0.35rem;
}

.note-editor :deep(.tiptap pre) {
  background: rgb(28 25 23);
  border-radius: 1rem;
  color: white;
  overflow-x: auto;
  padding: 1rem;
}
</style>
