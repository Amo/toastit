<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { WorkspacesApi } from '../api/workspaces';
import EmptyState from './EmptyState.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';
import PageHero from './PageHero.vue';
import WorkspaceRow from './WorkspaceRow.vue';

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
const apiClient = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});
const workspacesApi = new WorkspacesApi(apiClient);

const fetchDashboard = async () => {
  isLoading.value = true;
  const { ok, data } = await workspacesApi.getDashboard(props.apiUrl);
  payload.value = ok && data ? data : { workspaces: [] };
  isLoading.value = false;
};

const persistWorkspaceOrder = async () => {
  await workspacesApi.reorderWorkspaceList(payload.value.workspaces.map((workspace) => workspace.id));
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
  const { ok, data } = await workspacesApi.createWorkspace(workspaceName.value);

  if (ok && data) {
    if (data.workspaceId) {
      window.location.href = `/app/workspaces/${data.workspaceId}`;
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
      <div>
        <PageHero eyebrow="Workspace" title="Your workspaces." />
        <div class="flex flex-wrap items-center gap-3">
          <div class="inline-flex items-center gap-2 rounded-full bg-stone-100 px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.18em] text-stone-600">
            <i class="fa-solid fa-layer-group" aria-hidden="true"></i>
            <span>{{ payload.workspaces.length }} workspace<span v-if="payload.workspaces.length > 1">s</span></span>
          </div>
        </div>
      </div>
      <button type="button" class="inline-flex h-12 w-fit shrink-0 items-center gap-2 whitespace-nowrap rounded-full bg-amber-500 px-5 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="openCreateWorkspaceModal">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <span>New workspace</span>
      </button>
    </div>

    <div class="tw-toastit-card overflow-hidden p-6">
      <EmptyState v-if="isLoading" message="Loading..." />
      <EmptyState v-else-if="!payload.workspaces.length" message="No workspaces yet." />
      <div v-else class="space-y-4">
        <WorkspaceRow
          v-for="workspace in payload.workspaces"
          :key="workspace.id"
          :workspace="workspace"
          :is-armed-for-drag="armedWorkspaceDragId === workspace.id"
          @open="openWorkspace(workspace.id)"
          @arm-drag="armWorkspaceDrag(workspace.id)"
          @drag-start="onWorkspaceDragStart($event, workspace.id)"
          @drag-end="draggedWorkspaceId = null; armedWorkspaceDragId = null"
          @drop="reorderWorkspaces(workspace.id)"
        />
      </div>
    </div>

    <ModalDialog v-if="isCreateWorkspaceModalOpen" max-width-class="max-w-xl" @close="closeCreateWorkspaceModal">
      <ModalHeader
        eyebrow="New workspace"
        title="Create a workspace"
        description="Create a solo, duo, or group workspace and start turning ideas into accountable action."
        @close="closeCreateWorkspaceModal"
      />

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
