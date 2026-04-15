<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

const props = defineProps({
  dashboardUrl: { type: String, required: true },
  profileUrl: { type: String, required: true },
  user: { type: Object, default: null },
  contentHtml: { type: String, required: true },
  navigationOpenCount: { type: Number, default: 0 },
  navigationAssignedCount: { type: Number, default: 0 },
  navigationWorkspaces: { type: Array, default: () => [] },
});

const route = useRoute();
const router = useRouter();
const workspacePickerOpen = ref(false);
const workspaceCreateFlowActive = ref(false);

const tabs = computed(() => [
  {
    key: 'toasts',
    label: 'Toasts',
    icon: 'fa-list-check',
    to: { path: '/app', query: { mobileSection: 'toasts' } },
    primaryBadge: props.navigationAssignedCount,
  },
  {
    key: 'workspaces',
    label: 'Workspaces',
    icon: 'fa-table-columns',
    to: { path: '/app', query: { mobileSection: 'workspaces' } },
    primaryBadge: props.navigationOpenCount,
  },
  {
    key: 'profile',
    label: 'Profile',
    icon: 'fa-user',
    to: props.profileUrl,
  },
]);

const activeTabKey = computed(() => {
  const name = String(route.name ?? '');

  if (name === 'profile' || ['admin-dashboard', 'admin-users', 'admin-prompts'].includes(name)) {
    return 'profile';
  }

  if (name === 'dashboard') {
    const section = typeof route.query.mobileSection === 'string' ? route.query.mobileSection : 'toasts';
    return section === 'workspaces' ? 'workspaces' : 'toasts';
  }

  if (name === 'workspace' || name === 'inbox') {
    return 'workspaces';
  }

  if (name === 'workspace-create-toast' || name === 'inbox-create-toast') {
    return 'workspaces';
  }

  if (name === 'toast') {
    return 'toasts';
  }

  return 'toasts';
});

const createToastTarget = computed(() => {
  const routeName = String(route.name ?? '');

  if (routeName === 'workspace') {
    const workspaceId = Number(route.params.id);
    if (Number.isFinite(workspaceId)) {
      return `/app/workspaces/${workspaceId}`;
    }
  }

  if (routeName === 'inbox') {
    return '/app/inbox';
  }

  if (routeName === 'workspace-create-toast') {
    const workspaceId = Number(route.params.id);
    if (Number.isFinite(workspaceId)) {
      return `/app/workspaces/${workspaceId}`;
    }
  }

  if (routeName === 'inbox-create-toast') {
    return '/app/inbox';
  }

  return null;
});

const showFloatingCreateButton = computed(() => {
  const routeName = String(route.name ?? '');

  if (routeName === 'workspace' || routeName === 'inbox') {
    return true;
  }

  if (routeName === 'workspace-create-toast' || routeName === 'inbox-create-toast') {
    return true;
  }

  return routeName === 'dashboard' && activeTabKey.value === 'toasts';
});

const showFloatingCreateWorkspaceButton = computed(() => {
  const routeName = String(route.name ?? '');
  return routeName === 'dashboard' && activeTabKey.value === 'workspaces' && !workspaceCreateFlowActive.value;
});

const workspaceOptions = computed(() => (props.navigationWorkspaces ?? [])
  .filter((workspace) => Number.isFinite(Number(workspace?.id)))
  .map((workspace) => ({
    id: Number(workspace.id),
    name: workspace.name ?? `Workspace #${workspace.id}`,
    isInboxWorkspace: workspace.isInboxWorkspace === true,
  })));

const requestCreateToastInCurrentWorkspace = () => {
  window.dispatchEvent(new CustomEvent('toastit:create-toast'));
};

const openWorkspacePicker = () => {
  if (workspaceOptions.value.length < 1) {
    router.push({ path: '/app', query: { mobileSection: 'workspaces' } });
    return;
  }

  workspacePickerOpen.value = true;
};

const goToWorkspaceCreate = (workspaceId) => {
  const selectedWorkspace = workspaceOptions.value.find((workspace) => workspace.id === workspaceId);

  if (selectedWorkspace?.isInboxWorkspace) {
    router.push('/app/inbox/new-toast');
    return;
  }

  router.push(`/app/workspaces/${workspaceId}/new-toast`);
};

const pickWorkspace = (workspaceId) => {
  if (!Number.isFinite(workspaceId)) {
    return;
  }

  workspacePickerOpen.value = false;
  goToWorkspaceCreate(workspaceId);
};

const openCreateToast = () => {
  if (createToastTarget.value) {
    requestCreateToastInCurrentWorkspace();
    return;
  }

  if (workspaceOptions.value.length === 1) {
    goToWorkspaceCreate(workspaceOptions.value[0].id);
    return;
  }

  openWorkspacePicker();
};

const openCreateWorkspace = () => {
  window.dispatchEvent(new CustomEvent('toastit:create-workspace'));
};

const syncWorkspaceCreateFlowState = (event) => {
  const nextState = event instanceof CustomEvent && event.detail?.active === true;
  workspaceCreateFlowActive.value = nextState;
};

const navigateToTab = (tabKey) => {
  if (tabKey === 'profile') {
    router.push('/app/profile');
    return;
  }

  const tab = tabs.value.find((item) => item.key === tabKey);
  if (!tab) {
    return;
  }

  router.push(tab.to);
};

onMounted(() => {
  window.addEventListener('toastit:create-workspace-flow-state', syncWorkspaceCreateFlowState);
});

onUnmounted(() => {
  window.removeEventListener('toastit:create-workspace-flow-state', syncWorkspaceCreateFlowState);
});

</script>

<template>
  <div class="tw-mobile-app-shell">
    <Transition name="tw-mobile-page-slide" mode="out-in">
      <section
        :key="route.fullPath"
        class="tw-mobile-app-shell__content"
      >
        <button
          v-if="showFloatingCreateButton"
          type="button"
          class="tw-mobile-create-fab"
          @click="openCreateToast"
        >
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          <span class="sr-only">Create toast</span>
        </button>

        <button
          v-if="showFloatingCreateWorkspaceButton"
          type="button"
          class="tw-mobile-create-fab"
          @click="openCreateWorkspace"
        >
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          <span class="sr-only">Create workspace</span>
        </button>

        <slot v-if="$slots.default" />
        <div v-else v-html="contentHtml"></div>
      </section>
    </Transition>

    <nav class="tw-mobile-app-shell__nav" aria-label="Mobile navigation">
      <button
        v-for="tab in tabs"
        :key="tab.key"
        type="button"
        class="tw-mobile-app-shell__tab"
        :class="{ 'is-active': activeTabKey === tab.key }"
        @click="navigateToTab(tab.key)"
      >
        <span class="tw-mobile-app-shell__tab-icon-wrap">
          <i class="fa-solid" :class="tab.icon" aria-hidden="true"></i>
          <span
            v-if="tab.primaryBadge !== undefined"
            class="tw-mobile-app-shell__tab-badge"
            :class="{ 'tw-mobile-app-shell__tab-badge--neutral': tab.key === 'workspaces' }"
          >
            {{ tab.primaryBadge ?? 0 }}
          </span>
        </span>
        <span class="tw-mobile-app-shell__tab-label">{{ tab.label }}</span>
      </button>
    </nav>

    <ModalDialog v-if="workspacePickerOpen" max-width-class="max-w-2xl" @close="workspacePickerOpen = false">
      <ModalHeader
        eyebrow="New toast"
        title="Choose workspace"
        @close="workspacePickerOpen = false"
      />
      <div class="space-y-4 overflow-y-auto px-6 py-6">
        <div class="overflow-hidden border-y border-stone-200 bg-white">
          <button
            v-for="workspace in workspaceOptions"
            :key="workspace.id"
            type="button"
            class="flex w-full items-center justify-between border-b border-stone-200 px-1 py-3 text-left text-sm font-medium text-stone-800 transition last:border-b-0 hover:bg-stone-50"
            @click="pickWorkspace(workspace.id)"
          >
            <span class="min-w-0 truncate px-3">{{ workspace.name }}</span>
          </button>
        </div>
      </div>
    </ModalDialog>

  </div>
</template>
