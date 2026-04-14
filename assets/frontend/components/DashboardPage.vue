<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { WorkspacesApi } from '../api/workspaces';
import EmptyState from './EmptyState.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const payload = ref({ workspaces: [] });
const isLoading = ref(true);
const creatingWorkspace = ref(false);
const workspaceName = ref('');
const isCreateWorkspaceModalOpen = ref(false);
const isMobileViewport = ref(false);
const apiClient = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});
const router = useRouter();
const route = useRoute();
const workspacesApi = new WorkspacesApi(apiClient);
const mobileSection = computed(() => {
  const section = typeof route.query.mobileSection === 'string' ? route.query.mobileSection : 'toasts';
  return section === 'workspaces' ? 'workspaces' : 'toasts';
});

const fetchDashboard = async () => {
  isLoading.value = true;
  const { ok, data } = await workspacesApi.getDashboard(props.apiUrl);
  payload.value = ok && data ? data : {
    myActions: { summary: { assignedCount: 0, lateCount: 0, dueSoonCount: 0, workspaceCount: 0 }, actions: [] },
    workspaces: [],
  };
  isLoading.value = false;
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

const handleExternalCreateWorkspaceRequest = () => {
  openCreateWorkspaceModal();
};

const openWorkspace = (workspaceId) => {
  window.location.href = `/app/workspaces/${workspaceId}`;
};

const openWorkspaceFromSummary = (workspace) => {
  if (workspace?.isInboxWorkspace) {
    window.location.href = '/app/inbox';
    return;
  }

  if (workspace?.id) {
    openWorkspace(workspace.id);
  }
};

const openToast = (toastId) => {
  const returnTo = route.fullPath.startsWith('/app') ? route.fullPath : '/app';

  try {
    window.sessionStorage.setItem('toastit:toast-return-to', returnTo);
  } catch {
    // Ignore session storage failures.
  }

  router.push({
    path: `/app/toasts/${toastId}`,
    query: { returnTo },
  });
};

const actionDateStatus = (action) => {
  const dueOn = typeof action?.dueOn === 'string' ? action.dueOn : '';
  if (!dueOn) {
    return 'on-track';
  }

  const dueAt = new Date(`${dueOn}T00:00:00`);
  if (Number.isNaN(dueAt.getTime())) {
    return action?.isLate ? 'late' : (action?.isDueSoon ? 'due-soon' : 'on-track');
  }

  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const sevenDaysFromNow = new Date(today);
  sevenDaysFromNow.setDate(today.getDate() + 7);

  if (dueAt < today) {
    return 'late';
  }

  if (dueAt <= sevenDaysFromNow) {
    return 'due-soon';
  }

  return 'on-track';
};

const actionBorderStyle = (action) => {
  const status = actionDateStatus(action);
  let borderLeftColor = 'transparent';

  if (action?.isBoosted) {
    borderLeftColor = 'rgb(148 163 184)';
  } else if (status === 'late') {
    borderLeftColor = 'rgb(239 68 68)';
  } else if (status === 'due-soon') {
    borderLeftColor = 'rgb(250 204 21)';
  }

  return {
    borderLeftWidth: '5px',
    borderLeftStyle: 'solid',
    borderLeftColor,
  };
};

const workspaceAssignedPriorityCounts = computed(() => {
  const countersByWorkspace = {};
  const actions = Array.isArray(payload.value?.myActions?.actions) ? payload.value.myActions.actions : [];

  for (const action of actions) {
    const workspaceId = Number(action?.workspace?.id ?? 0);
    if (!Number.isFinite(workspaceId) || workspaceId <= 0) {
      continue;
    }

    if (!countersByWorkspace[workspaceId]) {
      countersByWorkspace[workspaceId] = { late: 0, dueSoon: 0, boosted: 0 };
    }

    if (action?.isBoosted) {
      countersByWorkspace[workspaceId].boosted += 1;
      continue;
    }

    const dateStatus = actionDateStatus(action);
    if (dateStatus === 'late') {
      countersByWorkspace[workspaceId].late += 1;
      continue;
    }

    if (dateStatus === 'due-soon') {
      countersByWorkspace[workspaceId].dueSoon += 1;
    }
  }

  return countersByWorkspace;
});

const workspacePriorityCounts = (workspaceId) => {
  const normalizedWorkspaceId = Number(workspaceId ?? 0);
  if (!Number.isFinite(normalizedWorkspaceId) || normalizedWorkspaceId <= 0) {
    return { late: 0, dueSoon: 0, boosted: 0 };
  }

  return workspaceAssignedPriorityCounts.value[normalizedWorkspaceId] ?? { late: 0, dueSoon: 0, boosted: 0 };
};

const workspacePriorityBadges = (workspaceId) => {
  const counts = workspacePriorityCounts(workspaceId);
  const badges = [
    { key: 'late', value: counts.late, className: 'bg-red-500', title: 'Late assigned toasts' },
    { key: 'dueSoon', value: counts.dueSoon, className: 'bg-yellow-400', title: 'Due soon assigned toasts' },
    { key: 'boosted', value: counts.boosted, className: 'bg-slate-400', title: 'Boosted assigned toasts' },
  ];

  return badges.filter((badge) => Number(badge.value) > 0);
};

const workspaceOpenToastLabel = (workspace) => {
  const total = Number(workspace?.openItemCount ?? 0);
  const normalizedTotal = Number.isFinite(total) && total > 0 ? total : 0;

  return `${normalizedTotal} toast${normalizedTotal > 1 ? 's' : ''}`;
};

const workspaceSecondaryMeta = (workspace) => {
  const ownerDisplayName = typeof workspace?.ownerDisplayName === 'string' ? workspace.ownerDisplayName : '';
  const ownerLabel = ownerDisplayName ? `Owner: ${ownerDisplayName}` : 'Owner: unknown';

  if (workspace?.isInboxWorkspace) {
    return `Inbox workspace - ${workspaceOpenToastLabel(workspace)} - ${ownerLabel}`;
  }

  if (workspace?.isSoloWorkspace) {
    return `Solo workspace - ${workspaceOpenToastLabel(workspace)} - ${ownerLabel}`;
  }

  const memberCount = Number(workspace?.memberCount ?? 0);
  const memberLabel = `${memberCount} member${memberCount > 1 ? 's' : ''}`;

  return `${memberLabel} - ${workspaceOpenToastLabel(workspace)} - ${ownerLabel}`;
};

const openHome = () => {
  window.location.href = '/app';
};

const syncViewport = () => {
  isMobileViewport.value = window.innerWidth < 1024;
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

  if (event.key.toLowerCase() === 'h') {
    event.preventDefault();
    openHome();
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
  openWorkspaceFromSummary(workspace);
};

onMounted(() => {
  syncViewport();
  fetchDashboard();
  window.addEventListener('keydown', handleDashboardKeydown);
  window.addEventListener('toastit:create-workspace', handleExternalCreateWorkspaceRequest);
  window.addEventListener('resize', syncViewport);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleDashboardKeydown);
  window.removeEventListener('toastit:create-workspace', handleExternalCreateWorkspaceRequest);
  window.removeEventListener('resize', syncViewport);
});
</script>

<template>
  <section class="space-y-6">
    <div v-if="!isMobileViewport || mobileSection === 'toasts'" class="tw-toastit-card p-6">
        <div class="sticky top-0 z-20 -mx-6 mb-5 flex flex-col gap-4 bg-white/95 px-6 py-2 backdrop-blur lg:static lg:mx-0 lg:bg-transparent lg:px-0 lg:py-0 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-2xl font-semibold tracking-tight text-stone-950">Focus now.</h2>
          </div>
        </div>

        <EmptyState v-if="isLoading" message="Loading..." />
        <EmptyState v-else-if="!(payload.myActions?.actions?.length ?? 0)" message="No assigned actions right now." />
        <div v-else class="overflow-hidden -mx-6 lg:mx-0">
          <div class="space-y-3 bg-white py-4 lg:hidden">
            <div
              v-for="action in payload.myActions.actions"
              :key="action.id"
              class="cursor-pointer space-y-2 px-4 py-1 transition hover:bg-stone-50"
              :style="actionBorderStyle(action)"
              @click="openToast(action.id)"
            >
              <p class="block w-full truncate text-left text-sm font-medium text-stone-900">
                {{ action.title }}
              </p>
              <p class="min-w-0 truncate text-xs text-stone-600">
                <i v-if="action.isBoosted" class="fa-solid fa-star mr-1 text-slate-400" aria-hidden="true"></i>
                {{ action.workspace.name }} • {{ action.dueOnDisplay || 'No due date' }}
              </p>
            </div>
          </div>
          <table class="hidden min-w-full divide-y divide-stone-200 bg-white text-sm lg:table">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">
              <tr>
                <th class="px-4 py-3">Toast</th>
                <th class="hidden px-4 py-3 xl:table-cell">Due</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              <tr
                v-for="action in payload.myActions.actions"
                :key="action.id"
                class="cursor-pointer transition hover:bg-stone-50"
                @click="openToast(action.id)"
              >
                <td class="px-4 py-3" :style="actionBorderStyle(action)">
                  <p class="block w-full truncate text-left font-medium text-stone-900">
                    {{ action.title }}
                  </p>
                  <p class="mt-1 truncate text-xs text-stone-600">
                    <i v-if="action.isBoosted" class="fa-solid fa-star mr-1 text-slate-400" aria-hidden="true"></i>
                    {{ action.workspace.name }}
                    <span class="xl:hidden"> • {{ action.dueOnDisplay || 'No due date' }}</span>
                  </p>
                </td>
                <td class="hidden px-4 py-3 text-stone-700 xl:table-cell">{{ action.dueOnDisplay || 'No due date' }}</td>
              </tr>
            </tbody>
          </table>
        </div>
    </div>

    <div v-if="isMobileViewport && mobileSection === 'workspaces'" class="tw-toastit-card p-6">
      <div class="sticky top-0 z-20 -mx-6 mb-5 flex flex-col gap-2 bg-white/95 px-6 py-2 backdrop-blur lg:static lg:mx-0 lg:bg-transparent lg:px-0 lg:py-0 lg:flex-row lg:items-center lg:justify-between">
        <div>
          <h2 class="text-2xl font-semibold tracking-tight text-stone-950">All workspaces.</h2>
        </div>
      </div>

      <EmptyState v-if="isLoading" message="Loading..." />
      <EmptyState v-else-if="!(payload.workspaces?.length ?? 0)" message="No workspace yet." />
      <div v-else class="-mx-6 space-y-3 bg-white py-4 lg:mx-0 lg:grid lg:gap-3 lg:space-y-0 lg:bg-transparent lg:py-0">
        <button
          v-for="workspace in payload.workspaces"
          :key="workspace.id"
          type="button"
          class="group block w-full border-l-[5px] border-transparent px-4 py-2 text-left transition hover:bg-stone-50 lg:rounded-2xl lg:border lg:border-stone-200 lg:bg-white lg:px-4 lg:py-3 lg:hover:border-amber-200 lg:hover:bg-amber-50/30"
          @click="openWorkspaceFromSummary(workspace)"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="truncate text-sm font-semibold text-stone-950">
                {{ workspace.name }}
              </p>
              <p class="mt-1 truncate text-xs text-stone-500">
                {{ workspaceSecondaryMeta(workspace) }}
              </p>
            </div>
            <span
              v-if="workspacePriorityBadges(workspace.id).length > 0"
              class="ml-auto inline-flex items-center overflow-hidden rounded-full border border-stone-200/70 shadow-sm"
            >
              <span
                v-for="(badge, badgeIndex) in workspacePriorityBadges(workspace.id)"
                :key="`${workspace.id}-${badge.key}`"
                :class="[
                  'inline-flex min-w-6 items-center justify-center px-2 py-1 text-[11px] font-semibold text-white',
                  'min-w-7',
                  badge.className,
                  badgeIndex > 0 ? 'border-l border-white/50' : '',
                ]"
                :title="badge.title"
              >
                {{ badge.value }}
              </span>
            </span>
          </div>
        </button>
      </div>
    </div>

    <ModalDialog v-if="isCreateWorkspaceModalOpen" max-width-class="max-w-4xl" @close="closeCreateWorkspaceModal">
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
            <button class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300 disabled:opacity-60" :disabled="creatingWorkspace" @click="createWorkspace">
              {{ creatingWorkspace ? 'Creating...' : 'Create workspace' }}
            </button>
          </div>
        </div>
    </ModalDialog>
  </section>
</template>
