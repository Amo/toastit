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
  await fetch('/api/workspaces', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify({ name: workspaceName.value }),
  });
  workspaceName.value = '';
  creatingWorkspace.value = false;
  await fetchDashboard();
};

onMounted(fetchDashboard);
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <div class="space-y-2">
      <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Workspace</p>
      <h1 class="text-4xl font-semibold tracking-tight text-stone-950">Vos workspaces.</h1>
      <p class="text-base leading-7 text-stone-600">Un seul espace pour inviter les gens, centraliser le toasts, puis passer en meeting mode quand vous etes prets.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_22rem]">
      <div class="tw-toastit-card p-6">
        <div class="flex items-center justify-between">
          <h2 class="text-lg font-semibold text-stone-950">Workspaces</h2>
        </div>

        <div v-if="isLoading" class="mt-6 text-sm text-stone-500">Chargement...</div>
        <div v-else-if="!payload.workspaces.length" class="mt-6 text-sm text-stone-500">Aucun workspace pour l'instant.</div>
        <div v-else class="mt-6 space-y-4">
          <article v-for="workspace in payload.workspaces" :key="workspace.id" class="rounded-2xl border border-stone-200 p-4">
            <div class="flex items-start justify-between gap-4">
              <div>
                <h3 class="text-lg font-semibold text-stone-950">{{ workspace.name }}</h3>
                <p class="text-sm text-stone-500">{{ workspace.memberCount }} membre<span v-if="workspace.memberCount > 1">s</span> · {{ workspace.openItemCount }} toast<span v-if="workspace.openItemCount > 1">s</span> actif<span v-if="workspace.openItemCount > 1">s</span></p>
              </div>
              <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="workspace.meetingMode === 'live' ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-700'">
                {{ workspace.meetingMode === 'live' ? 'Live' : 'Idle' }}
              </span>
            </div>

            <div class="mt-4 flex items-center justify-between gap-4">
              <p class="text-sm text-stone-500">{{ workspace.resolvedItemCount }} toast<span v-if="workspace.resolvedItemCount > 1">s</span> resolu<span v-if="workspace.resolvedItemCount > 1">s</span></p>
              <a class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950" :href="`/app/workspaces/${workspace.id}`">Ouvrir</a>
            </div>
          </article>
        </div>
      </div>

      <div class="tw-toastit-card p-6 space-y-4">
        <h2 class="text-lg font-semibold text-stone-950">Creer un workspace</h2>
        <label class="grid gap-2 text-sm font-medium text-stone-700">
          <span>Nom</span>
          <input v-model="workspaceName" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text">
        </label>
        <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60" :disabled="creatingWorkspace" @click="createWorkspace">
          {{ creatingWorkspace ? 'Creation...' : 'Creer le workspace' }}
        </button>
      </div>
    </div>
  </section>
</template>
