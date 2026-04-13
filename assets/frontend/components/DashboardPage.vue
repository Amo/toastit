<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { WorkspacesApi } from '../api/workspaces';
import EmptyState from './EmptyState.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';
import PageHeader from './PageHeader.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const payload = ref({ workspaces: [] });
const isLoading = ref(true);
const isSendingWeeklySummary = ref(false);
const summaryFeedback = ref('');
const creatingWorkspace = ref(false);
const workspaceName = ref('');
const isCreateWorkspaceModalOpen = ref(false);
const apiClient = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});
const router = useRouter();
const workspacesApi = new WorkspacesApi(apiClient);

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

const dashboardHeaderStats = computed(() => [{
  label: `${payload.value.myActions?.summary?.assignedCount ?? 0} assigned`,
  icon: 'fa-solid fa-list-check',
  className: 'bg-stone-100 text-stone-600 uppercase tracking-[0.18em] text-xs font-semibold',
}, {
  label: `${payload.value.workspaces.length} workspace${payload.value.workspaces.length > 1 ? 's' : ''}`,
  icon: 'fa-solid fa-layer-group',
  className: 'bg-stone-100 text-stone-600 uppercase tracking-[0.18em] text-xs font-semibold',
}]);

const dashboardHeaderActions = computed(() => {
  return [{
    id: 'send-weekly-summary',
    label: isSendingWeeklySummary.value ? 'Sending...' : 'Send 7-day summary',
    icon: 'fa-solid fa-envelope-open-text',
    theme: 'secondary',
    disabled: isSendingWeeklySummary.value,
  }, {
    id: 'create-workspace',
    label: 'New workspace',
    icon: 'fa-solid fa-plus',
    theme: 'primary',
  }];
});

const sendWeeklySummary = async () => {
  isSendingWeeklySummary.value = true;
  summaryFeedback.value = '';
  const { ok, data } = await workspacesApi.sendWeeklySummary();
  isSendingWeeklySummary.value = false;

  if (!ok || !data?.ok) {
    summaryFeedback.value = data?.message ?? 'Unable to send the weekly summary.';
    return;
  }

  summaryFeedback.value = 'Weekly summary sent by email.';
};

const handleDashboardHeaderAction = (actionId) => {
  if ('send-weekly-summary' === actionId) {
    sendWeeklySummary();
    return;
  }

  if ('create-workspace' === actionId) {
    openCreateWorkspaceModal();
  }
};

const openWorkspace = (workspaceId) => {
  window.location.href = `/app/workspaces/${workspaceId}`;
};

const openToast = (toastId) => {
  router.push(`/app/toasts/${toastId}`);
};

const openHome = () => {
  window.location.href = '/app';
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
  <section class="space-y-6">
    <div class="px-4 lg:px-6">
      <PageHeader
        eyebrow="Home"
        title="Your work."
        :stats="dashboardHeaderStats"
        :actions="dashboardHeaderActions"
        @action="handleDashboardHeaderAction"
      />
      <p v-if="summaryFeedback" class="mt-4 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm text-stone-700">
        {{ summaryFeedback }}
      </p>
    </div>

    <div class="tw-toastit-card overflow-hidden p-6">
        <div class="mb-5 flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-600">My actions</p>
            <h2 class="mt-2 text-2xl font-semibold tracking-tight text-stone-950">Focus now.</h2>
          </div>
          <div class="flex flex-wrap gap-2 text-sm">
            <span class="inline-flex items-center gap-2 rounded-full bg-stone-100 px-3 py-1 font-medium text-stone-700">
              <i class="fa-solid fa-list-check" aria-hidden="true"></i>
              <span>{{ payload.myActions?.summary?.assignedCount ?? 0 }} assigned</span>
            </span>
            <span
              class="inline-flex items-center gap-2 rounded-full px-3 py-1 font-medium"
              :class="(payload.myActions?.summary?.lateCount ?? 0) > 0 ? 'bg-red-600 text-white' : 'bg-stone-100 text-stone-700'"
            >
              <i class="fa-solid fa-triangle-exclamation" aria-hidden="true"></i>
              <span>{{ payload.myActions?.summary?.lateCount ?? 0 }} late</span>
            </span>
          </div>
        </div>

        <EmptyState v-if="isLoading" message="Loading..." />
        <EmptyState v-else-if="!(payload.myActions?.actions?.length ?? 0)" message="No assigned actions right now." />
        <div v-else class="overflow-hidden rounded-2xl border border-stone-200">
          <div class="divide-y divide-stone-100 bg-white lg:hidden">
            <div
              v-for="action in payload.myActions.actions"
              :key="action.id"
              class="cursor-pointer space-y-2 px-4 py-3 transition hover:bg-stone-50"
              @click="openToast(action.id)"
            >
              <p class="block w-full truncate text-left text-sm font-medium text-stone-900">
                {{ action.title }}
              </p>
              <p class="min-w-0 truncate text-xs text-stone-600">
                {{ action.workspace.name }} • {{ action.owner?.displayName || 'Unassigned' }} • {{ action.dueOnDisplay || 'No due date' }} • {{ action.isLate ? 'Late' : (action.isDueSoon ? 'Due soon' : 'On track') }}
              </p>
            </div>
          </div>
          <table class="hidden min-w-full divide-y divide-stone-200 bg-white text-sm lg:table">
            <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">
              <tr>
                <th class="px-4 py-3">Toast</th>
                <th class="px-4 py-3">Due</th>
                <th class="px-4 py-3">State</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-stone-100">
              <tr
                v-for="action in payload.myActions.actions"
                :key="action.id"
                class="cursor-pointer transition hover:bg-stone-50"
                @click="openToast(action.id)"
              >
                <td class="px-4 py-3">
                  <p class="block w-full truncate text-left font-medium text-stone-900">
                    {{ action.title }}
                  </p>
                  <p class="mt-1 truncate text-xs text-stone-600">
                    {{ action.workspace.name }} • {{ action.owner?.displayName || 'Unassigned' }}
                  </p>
                </td>
                <td class="px-4 py-3 text-stone-700">{{ action.dueOnDisplay || 'No due date' }}</td>
                <td class="px-4 py-3">
                  <span
                    v-if="action.isLate"
                    class="inline-flex rounded-full bg-red-100 px-2 py-0.5 text-xs font-semibold text-red-700"
                  >Late</span>
                  <span
                    v-else-if="action.isDueSoon"
                    class="inline-flex rounded-full bg-amber-100 px-2 py-0.5 text-xs font-semibold text-amber-700"
                  >Due soon</span>
                  <span
                    v-else
                    class="inline-flex rounded-full bg-stone-100 px-2 py-0.5 text-xs font-semibold text-stone-700"
                  >On track</span>
                </td>
              </tr>
            </tbody>
          </table>
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
            <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60" :disabled="creatingWorkspace" @click="createWorkspace">
              {{ creatingWorkspace ? 'Creating...' : 'Create workspace' }}
            </button>
          </div>
        </div>
    </ModalDialog>
  </section>
</template>
