<script setup>
import { computed, ref } from 'vue';
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
const touchStart = ref(null);
const workspacePickerOpen = ref(false);
const profileMenuOpen = ref(false);

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
    label: 'Profil',
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
  return routeName === 'dashboard' && activeTabKey.value === 'workspaces';
});

const workspaceOptions = computed(() => (props.navigationWorkspaces ?? [])
  .filter((workspace) => Number.isFinite(Number(workspace?.id)))
  .map((workspace) => ({
    id: Number(workspace.id),
    name: workspace.name ?? `Workspace #${workspace.id}`,
    isInboxWorkspace: workspace.isInboxWorkspace === true,
  })));

const profileMenuItems = [
  { label: 'Infos', to: '/app/profile' },
  { label: 'Preferences', to: '/app/profile?section=preferences' },
  { label: 'API tokens', to: '/app/profile?section=api' },
  { label: 'Trash', to: '/app/profile?section=trash' },
  { label: 'Account', to: '/app/profile?section=account' },
];

const adminMenuItems = [
  { label: 'Statistics', to: '/admin' },
  { label: 'Users', to: '/admin/users' },
  { label: 'Prompts', to: '/admin/prompts' },
];

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

const navigateToTab = (tabKey) => {
  if (tabKey === 'profile') {
    profileMenuOpen.value = true;
    return;
  }

  const tab = tabs.value.find((item) => item.key === tabKey);
  if (!tab) {
    return;
  }

  router.push(tab.to);
};

const openProfileMenuItem = (target) => {
  profileMenuOpen.value = false;
  router.push(target);
};

const shouldIgnoreSwipe = (event) => {
  if (!(event.target instanceof HTMLElement)) {
    return false;
  }

  return !!event.target.closest('input, textarea, select, button, a, [role="button"], [data-no-swipe]');
};

const handleTouchStart = (event) => {
  if (shouldIgnoreSwipe(event)) {
    touchStart.value = null;
    return;
  }

  const [touch] = event.changedTouches;
  if (!touch) {
    return;
  }

  touchStart.value = { x: touch.clientX, y: touch.clientY };
};

const handleTouchEnd = (event) => {
  if (!touchStart.value || shouldIgnoreSwipe(event)) {
    return;
  }

  const [touch] = event.changedTouches;
  if (!touch) {
    touchStart.value = null;
    return;
  }

  const dx = touch.clientX - touchStart.value.x;
  const dy = touch.clientY - touchStart.value.y;
  touchStart.value = null;

  if (Math.abs(dx) < 70 || Math.abs(dx) < Math.abs(dy) * 1.2) {
    return;
  }

  const swipeOrder = ['toasts', 'workspaces', 'profile'];
  const currentIndex = swipeOrder.indexOf(activeTabKey.value);
  if (currentIndex < 0) {
    return;
  }

  const direction = dx < 0 ? 1 : -1;
  const targetKey = swipeOrder[currentIndex + direction];
  if (!targetKey) {
    return;
  }

  navigateToTab(targetKey);
};
</script>

<template>
  <div class="tw-mobile-app-shell">
    <Transition name="tw-mobile-page-slide" mode="out-in">
      <section
        :key="route.fullPath"
        class="tw-mobile-app-shell__content"
        @touchstart.passive="handleTouchStart"
        @touchend.passive="handleTouchEnd"
      >
        <button
          v-if="showFloatingCreateButton"
          type="button"
          class="tw-mobile-create-fab"
          @click="openCreateToast"
        >
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          <span class="sr-only">Créer un toast</span>
        </button>

        <button
          v-if="showFloatingCreateWorkspaceButton"
          type="button"
          class="tw-mobile-create-fab"
          @click="openCreateWorkspace"
        >
          <i class="fa-solid fa-plus" aria-hidden="true"></i>
          <span class="sr-only">Créer un workspace</span>
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
        description="Select a workspace before creating your toast."
        @close="workspacePickerOpen = false"
      />
      <div class="space-y-4 overflow-y-auto px-6 py-6">
        <div class="space-y-2">
          <button
            v-for="workspace in workspaceOptions"
            :key="workspace.id"
            type="button"
            class="flex w-full items-center justify-between rounded-2xl border border-stone-200 bg-white px-4 py-3 text-left text-sm font-medium text-stone-800 transition hover:border-amber-300 hover:bg-amber-50/40"
            @click="pickWorkspace(workspace.id)"
          >
            <span class="min-w-0 truncate">{{ workspace.name }}</span>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
          </button>
        </div>
        <div class="flex justify-end">
          <button
            type="button"
            class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
            @click="workspacePickerOpen = false"
          >
            Cancel
          </button>
        </div>
      </div>
    </ModalDialog>

    <ModalDialog v-if="profileMenuOpen" max-width-class="max-w-2xl" @close="profileMenuOpen = false">
      <ModalHeader
        eyebrow="Profile"
        title="My profile"
        description="Choose where to go."
        @close="profileMenuOpen = false"
      />
      <div class="space-y-6 overflow-y-auto px-6 py-6">
        <section class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">My profile</p>
          <button
            v-for="item in profileMenuItems"
            :key="item.to"
            type="button"
            class="flex w-full items-center justify-between rounded-2xl border border-stone-200 bg-white px-4 py-3 text-left text-sm font-medium text-stone-800 transition hover:border-amber-300 hover:bg-amber-50/40"
            @click="openProfileMenuItem(item.to)"
          >
            <span>{{ item.label }}</span>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
          </button>
        </section>

        <section v-if="props.user?.isRoot" class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">Administration</p>
          <button
            v-for="item in adminMenuItems"
            :key="item.to"
            type="button"
            class="flex w-full items-center justify-between rounded-2xl border border-stone-200 bg-white px-4 py-3 text-left text-sm font-medium text-stone-800 transition hover:border-amber-300 hover:bg-amber-50/40"
            @click="openProfileMenuItem(item.to)"
          >
            <span>{{ item.label }}</span>
            <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
          </button>
        </section>
      </div>
    </ModalDialog>
  </div>
</template>
