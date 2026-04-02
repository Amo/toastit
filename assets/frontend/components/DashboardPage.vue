<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import AvatarBadge from './AvatarBadge.vue';
import ModalDialog from './ModalDialog.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const payload = ref({ workspaces: [] });
const isLoading = ref(true);
const creatingWorkspace = ref(false);
const workspaceName = ref('');
const isCreateWorkspaceModalOpen = ref(false);
const draggedWorkspaceId = ref(null);
const armedWorkspaceDragId = ref(null);

const fetchDashboard = async () => {
  isLoading.value = true;
  const response = await fetch(props.apiUrl, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });
  payload.value = response.ok ? await response.json() : { workspaces: [] };
  isLoading.value = false;
};

const persistWorkspaceOrder = async () => {
  await fetch('/api/workspaces/reorder', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify({
      workspaceIds: payload.value.workspaces.map((workspace) => workspace.id),
    }),
  });
};

const reorderWorkspaces = async (targetWorkspaceId) => {
  const sourceWorkspaceId = draggedWorkspaceId.value;

  if (!sourceWorkspaceId || sourceWorkspaceId === targetWorkspaceId) {
    draggedWorkspaceId.value = null;
    return;
  }

  const currentIndex = payload.value.workspaces.findIndex((workspace) => workspace.id === sourceWorkspaceId);
  const targetIndex = payload.value.workspaces.findIndex((workspace) => workspace.id === targetWorkspaceId);

  if (currentIndex < 0 || targetIndex < 0) {
    draggedWorkspaceId.value = null;
    return;
  }

  const nextWorkspaces = [...payload.value.workspaces];
  const [draggedWorkspace] = nextWorkspaces.splice(currentIndex, 1);
  nextWorkspaces.splice(targetIndex, 0, draggedWorkspace);
  payload.value = {
    ...payload.value,
    workspaces: nextWorkspaces,
  };
  draggedWorkspaceId.value = null;
  await persistWorkspaceOrder();
};

const armWorkspaceDrag = (workspaceId) => {
  armedWorkspaceDragId.value = workspaceId;
};

const onWorkspaceDragStart = (event, workspaceId) => {
  if (armedWorkspaceDragId.value !== workspaceId) {
    event.preventDefault();
    return;
  }

  draggedWorkspaceId.value = workspaceId;
  armedWorkspaceDragId.value = null;
};

const createWorkspace = async () => {
  if (!workspaceName.value.trim()) return;
  creatingWorkspace.value = true;
  const response = await fetch('/api/workspaces', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify({ name: workspaceName.value }),
  });

  if (response.ok) {
    const result = await response.json();

    if (result.workspaceId) {
      window.location.href = `/app/workspaces/${result.workspaceId}`;
      return;
    }
  }

  workspaceName.value = '';
  creatingWorkspace.value = false;
  isCreateWorkspaceModalOpen.value = false;
  await fetchDashboard();
};

const openCreateWorkspaceModal = () => {
  isCreateWorkspaceModalOpen.value = true;
};

const closeCreateWorkspaceModal = () => {
  isCreateWorkspaceModalOpen.value = false;
};

const openWorkspace = (workspaceId) => {
  window.location.href = `/app/workspaces/${workspaceId}`;
};

const isTypingTarget = (target) => {
  if (!(target instanceof HTMLElement)) {
    return false;
  }

  const tagName = target.tagName.toLowerCase();

  return tagName === 'input'
    || tagName === 'textarea'
    || tagName === 'select'
    || target.isContentEditable;
};

const handleDashboardKeydown = (event) => {
  if (isTypingTarget(event.target) || event.metaKey || event.ctrlKey || event.altKey) {
    return;
  }

  if (!/^[1-9]$/.test(event.key)) {
    return;
  }

  const index = Number(event.key) - 1;
  const workspace = payload.value.workspaces[index];

  if (!workspace) {
    return;
  }

  event.preventDefault();
  openWorkspace(workspace.id);
};

onMounted(() => {
  fetchDashboard();
  window.addEventListener('keydown', handleDashboardKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleDashboardKeydown);
});
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <div class="flex items-start justify-between gap-4">
      <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Workspace</p>
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-4xl font-semibold tracking-tight text-stone-950">Your workspaces.</h1>
          <div class="inline-flex items-center gap-2 rounded-full bg-stone-100 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span>{{ payload.workspaces.length }} workspace<span v-if="payload.workspaces.length > 1">s</span></span>
          </div>
        </div>
        <p class="text-base leading-7 text-stone-600">Turn sticky notes into shared plans and real results. Every workspace keeps your toasts, follow-ups, and collaborators in the same flow.</p>
      </div>
      <button type="button" class="inline-flex h-12 w-fit shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-amber-500 px-5 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="openCreateWorkspaceModal">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <span>New workspace</span>
      </button>
    </div>

    <div class="tw-toastit-card overflow-hidden p-6">
      <div v-if="isLoading" class="text-sm text-stone-500">Loading...</div>
      <div v-else-if="!payload.workspaces.length" class="text-sm text-stone-500">No workspaces yet.</div>
      <div v-else class="space-y-4">
        <div
          v-for="workspace in payload.workspaces"
          :key="workspace.id"
          class="flex items-stretch gap-3"
          :draggable="armedWorkspaceDragId === workspace.id"
          @dragstart="onWorkspaceDragStart($event, workspace.id)"
          @dragend="draggedWorkspaceId = null; armedWorkspaceDragId = null"
          @dragover.prevent
          @drop.prevent="reorderWorkspaces(workspace.id)"
        >
          <div
            class="flex w-6 shrink-0 items-center justify-center text-stone-400/50 transition hover:text-stone-500/70"
            data-drag-handle
            @pointerdown="armWorkspaceDrag(workspace.id)"
          >
            <i class="fa-solid fa-grip-lines text-sm" aria-hidden="true"></i>
            <span class="sr-only">Drag to reorder workspace</span>
          </div>

          <button
            type="button"
            class="group relative block w-full overflow-hidden rounded-[1.75rem] border px-5 py-4 text-left transition"
            :class="workspace.isSoloWorkspace ? 'border-stone-200 bg-stone-50 hover:border-stone-300' : 'border-amber-100 bg-amber-50/60 hover:border-amber-200'"
            @click="openWorkspace(workspace.id)"
          >
          <div class="relative z-10 flex flex-wrap items-center gap-x-6 gap-y-3 lg:flex-nowrap">
            <div class="min-w-0 flex-1">
              <div class="flex items-center gap-3">
                <div class="flex items-center gap-2">
                  <span
                    class="inline-flex h-10 min-w-10 items-center justify-center rounded-full px-3 text-sm font-semibold leading-none"
                    :class="workspace.lateOpenItemCount > 0 ? 'bg-red-600 text-white' : 'border border-stone-200 bg-white/70 text-transparent'"
                  >
                    {{ workspace.lateOpenItemCount > 0 ? workspace.lateOpenItemCount : '0' }}
                  </span>
                  <span
                    class="inline-flex h-10 min-w-10 items-center justify-center rounded-full bg-amber-100 px-3 text-sm font-semibold leading-none text-amber-800"
                  >
                    {{ workspace.isSoloWorkspace ? workspace.openItemCount : workspace.assignedOpenItemCount }}
                  </span>
                </div>
                <h3 class="truncate text-lg font-semibold text-stone-950">{{ workspace.name }}</h3>
                <span v-if="workspace.isDefault" class="rounded-full bg-amber-100 px-2.5 py-1 text-[11px] font-semibold uppercase tracking-[0.16em] text-amber-700">Default</span>
              </div>
            </div>

            <template v-if="workspace.isSoloWorkspace">
              <div class="w-0 overflow-hidden"></div>
            </template>

            <template v-else>
              <div class="flex items-center gap-3 self-center text-sm font-semibold text-stone-800">
                <div class="flex h-10 items-center">
                  <div
                    v-for="(member, index) in workspace.membersPreview"
                    :key="member.id ?? `${workspace.id}-${index}`"
                    class="relative inline-flex h-10 w-10 shrink-0 items-center justify-center rounded-full border-2 border-white bg-white"
                    :class="index > 0 ? '-ml-3' : ''"
                    :style="{ zIndex: workspace.membersPreview.length - index }"
                  >
                    <AvatarBadge
                      :seed="member.id ?? member.displayName"
                      :initials="member.initials"
                      :gravatar-url="member.gravatarUrl"
                      :alt="member.displayName"
                      :title="member.displayName || member.email || ''"
                    />
                  </div>
                  <span
                    v-if="workspace.memberCount > 7"
                    class="ml-3 whitespace-nowrap text-sm font-medium text-stone-500"
                  >
                    ... +{{ workspace.memberCount - 7 }} members
                  </span>
                </div>
              </div>
            </template>

          </div>
          </button>
        </div>
      </div>
    </div>

    <ModalDialog v-if="isCreateWorkspaceModalOpen" max-width-class="max-w-xl" @close="closeCreateWorkspaceModal">
        <div class="flex items-start justify-between gap-4 border-b border-stone-100 px-6 py-5">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">New workspace</p>
            <h2 class="mt-2 text-2xl font-semibold text-stone-950">Create a workspace</h2>
            <p class="mt-2 text-sm leading-6 text-stone-600">Create a solo, duo, or group workspace and start turning ideas into accountable action.</p>
          </div>
          <button type="button" class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 text-stone-500 transition hover:border-stone-300 hover:text-stone-800" @click="closeCreateWorkspaceModal">
            <i class="fa-solid fa-xmark" aria-hidden="true"></i>
            <span class="sr-only">Close modal</span>
          </button>
        </div>

        <div class="space-y-4 overflow-y-auto px-6 py-6">
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Name</span>
            <input v-model="workspaceName" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text">
          </label>
          <div class="flex justify-end gap-3">
            <button type="button" class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="closeCreateWorkspaceModal">Cancel</button>
            <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60" :disabled="creatingWorkspace" @click="createWorkspace">
              {{ creatingWorkspace ? 'Creating...' : 'Create workspace' }}
            </button>
          </div>
        </div>
    </ModalDialog>
  </section>
</template>
