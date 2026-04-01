<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const payload = ref({ workspaces: [] });
const isLoading = ref(true);
const creatingWorkspace = ref(false);
const workspaceName = ref('');
const isCreateWorkspaceModalOpen = ref(false);

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

onMounted(fetchDashboard);
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <div class="flex items-start justify-between gap-4">
      <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Workspace</p>
        <h1 class="text-4xl font-semibold tracking-tight text-stone-950">Your workspaces.</h1>
        <p class="text-base leading-7 text-stone-600">Turn sticky notes into shared plans and real results. Every workspace keeps your toasts, follow-ups, and collaborators in the same flow.</p>
      </div>
      <button type="button" class="inline-flex h-12 items-center gap-2 rounded-full bg-amber-500 px-5 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="openCreateWorkspaceModal">
        <i class="fa-solid fa-plus" aria-hidden="true"></i>
        <span>New workspace</span>
      </button>
    </div>

    <div class="tw-toastit-card p-6">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-stone-950">Workspaces</h2>
      </div>

      <div v-if="isLoading" class="mt-6 text-sm text-stone-500">Loading...</div>
      <div v-else-if="!payload.workspaces.length" class="mt-6 text-sm text-stone-500">No workspaces yet.</div>
      <div v-else class="mt-6 overflow-hidden rounded-2xl border border-stone-200">
        <div class="grid grid-cols-[minmax(0,1.8fr)_7rem_7rem_7rem_8rem] gap-4 border-b border-stone-200 bg-stone-50 px-4 py-3 text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">
          <span>Name</span>
          <span>Members</span>
          <span>New</span>
          <span>Toasted</span>
          <span>Status</span>
        </div>
        <button
          v-for="workspace in payload.workspaces"
          :key="workspace.id"
          type="button"
          class="grid w-full grid-cols-[minmax(0,1.8fr)_7rem_7rem_7rem_8rem] items-center gap-4 border-b border-stone-100 px-4 py-4 text-left transition last:border-b-0 hover:bg-amber-50"
          @click="openWorkspace(workspace.id)"
        >
          <div class="min-w-0">
            <div class="flex flex-wrap items-center gap-2">
              <span class="truncate text-sm font-semibold text-stone-950">{{ workspace.name }}</span>
              <span v-if="workspace.isDefault" class="rounded-full bg-amber-100 px-2 py-0.5 text-[11px] font-semibold text-amber-700">Default</span>
            </div>
            <p class="mt-1 truncate text-sm text-stone-500">{{ workspace.isDefault ? 'Your default workspace' : 'Shared workspace' }}</p>
          </div>
          <span class="text-sm text-stone-700">{{ workspace.memberCount }}</span>
          <span class="text-sm text-stone-700">{{ workspace.openItemCount }}</span>
          <span class="text-sm text-stone-700">{{ workspace.resolvedItemCount }}</span>
          <span class="justify-self-start rounded-full px-3 py-1 text-xs font-semibold" :class="workspace.meetingMode === 'live' ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-700'">
            {{ workspace.meetingMode === 'live' ? 'Toasting' : 'Idle' }}
          </span>
        </button>
      </div>
    </div>

    <div v-if="isCreateWorkspaceModalOpen" class="!mt-0 fixed inset-0 z-[70] flex items-center justify-center bg-stone-950/20 backdrop-blur-sm px-4 py-[5vh]" @click.self="closeCreateWorkspaceModal">
      <div class="flex max-h-[90vh] w-full max-w-xl flex-col overflow-hidden rounded-[1.75rem] bg-white shadow-2xl">
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
      </div>
    </div>
  </section>
</template>
