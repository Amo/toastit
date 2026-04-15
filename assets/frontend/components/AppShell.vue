<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, useSlots, watch } from 'vue';
import { useRoute } from 'vue-router';
import { authState, authStore } from '../authStore';
import MobileAppShell from './MobileAppShell.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

const props = defineProps({
  currentSection: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  profileUrl: { type: String, required: true },
  user: { type: Object, default: null },
  contentHtml: { type: String, required: true },
  showAppNavigation: { type: Boolean, default: true },
  publicCtaLabel: { type: String, default: '' },
  publicCtaHref: { type: String, default: '' },
});
const emit = defineEmits(['public-cta-click']);
const route = useRoute();

const userMenuOpen = ref(false);
const keyboardShortcutsOpen = ref(false);
const mobileNavOpen = ref(false);
const contentRef = ref(null);
const isMobilePlatform = ref(false);
const slots = useSlots();
const navigationWorkspaces = ref([]);
const MOBILE_SHELL_OVERRIDE_KEY = 'toastit.mobileShellOverride';
const profileSections = [
  { key: 'infos', label: 'Infos' },
  { key: 'preferences', label: 'Preferences' },
  { key: 'api', label: 'API tokens' },
  { key: 'trash', label: 'Trash' },
  { key: 'account', label: 'Account' },
];

const currentWorkspaceId = computed(() => {
  if (route.name !== 'workspace') {
    return null;
  }

  const id = Number(route.params.id);
  return Number.isFinite(id) ? id : null;
});
const currentProfileSection = computed(() => {
  if (route.name !== 'profile') {
    return 'infos';
  }

  const section = typeof route.query.section === 'string' ? route.query.section : 'infos';
  return profileSections.some((item) => item.key === section) ? section : 'infos';
});

const navigationOpenCount = computed(() => navigationWorkspaces.value
  .reduce((total, workspace) => total + Number(workspace?.openItemCount ?? 0), 0));
const navigationAssignedCount = computed(() => navigationWorkspaces.value
  .reduce((total, workspace) => total + Number(workspace?.assignedOpenItemCount ?? 0), 0));
const listedNavigationWorkspaces = computed(() => navigationWorkspaces.value
  .filter((workspace) => workspace?.isInboxWorkspace !== true));
const inboxWorkspace = computed(() => navigationWorkspaces.value
  .find((workspace) => workspace?.isInboxWorkspace === true) ?? null);
const inboxOpenCount = computed(() => {
  const count = Number(inboxWorkspace.value?.openItemCount ?? 0);
  return Number.isFinite(count) && count > 0 ? count : 0;
});
const mobileAppModeActive = computed(() => props.showAppNavigation && isMobilePlatform.value);
const profileSectionHref = (sectionKey) => (
  sectionKey === 'infos' ? '/app/profile' : `/app/profile?section=${sectionKey}`
);
const workspaceHref = (workspace) => (
  workspace?.isInboxWorkspace ? '/app/inbox' : `/app/workspaces/${workspace.id}`
);
const isWorkspaceActive = (workspace) => {
  if (workspace?.isInboxWorkspace) {
    return route.name === 'inbox';
  }

  return currentWorkspaceId.value === workspace?.id;
};

const workspaceModeLabel = (workspace) => {
  if (workspace?.isInboxWorkspace) {
    return 'Inbox';
  }

  return workspace?.isSoloWorkspace ? 'Personal' : 'Team';
};

const workspaceModeBadgeClass = (workspace) => {
  if (workspace?.isInboxWorkspace) {
    return 'bg-sky-100 text-sky-700';
  }

  return workspace?.isSoloWorkspace ? 'bg-emerald-100 text-emerald-700' : 'bg-violet-100 text-violet-700';
};

const syncMobilePlatform = () => {
  const mobileQuery = typeof route.query.mobile === 'string' ? route.query.mobile.toLowerCase() : '';
  if (['1', 'true', 'on'].includes(mobileQuery)) {
    localStorage.setItem(MOBILE_SHELL_OVERRIDE_KEY, '1');
  } else if (['0', 'false', 'off'].includes(mobileQuery)) {
    localStorage.removeItem(MOBILE_SHELL_OVERRIDE_KEY);
  }

  const forcedMobileShell = localStorage.getItem(MOBILE_SHELL_OVERRIDE_KEY) === '1';
  if (forcedMobileShell) {
    isMobilePlatform.value = true;
    return;
  }

  const ua = window.navigator.userAgent || '';
  const userAgentDataMobile = window.navigator.userAgentData?.mobile === true;
  const touchDevice = window.navigator.maxTouchPoints > 1;
  const mobileUa = /Android|webOS|iPhone|iPod|BlackBerry|IEMobile|Opera Mini|Mobile/i.test(ua);
  const ipadOs = /iPad/i.test(ua)
    || (window.navigator.platform === 'MacIntel' && window.navigator.maxTouchPoints > 1);

  isMobilePlatform.value = userAgentDataMobile || mobileUa || ipadOs || (touchDevice && window.innerWidth <= 1024);
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

const handleGlobalAppKeydown = (event) => {
  if (isTypingTarget(event.target) || event.metaKey || event.ctrlKey || event.altKey) {
    return;
  }

  if (event.key.toLowerCase() === 'h') {
    event.preventDefault();
    window.location.href = props.dashboardUrl;
    return;
  }

  if (event.key.toLowerCase() === 'c') {
    event.preventDefault();
    openGlobalQuickAdd();
  }
};

const openInbox = () => {
  window.location.href = '/app/inbox';
};

const openGlobalQuickAdd = () => {
  const fallbackWorkspace = listedNavigationWorkspaces.value[0] ?? inboxWorkspace.value;
  const targetWorkspace = inboxWorkspace.value ?? fallbackWorkspace;
  if (!targetWorkspace?.id) {
    window.location.href = props.dashboardUrl;
    return;
  }

  try {
    window.sessionStorage.setItem('toastit:quick-add-start-ms', String(Date.now()));
  } catch {
    // Ignore storage failures.
  }

  window.location.href = `/app/workspaces/${targetWorkspace.id}?create=1`;
};

const logout = () => {
  authStore.logout();
  window.location.href = '/';
};

const lockSession = () => {
  window.dispatchEvent(new CustomEvent('toastit:lock-session'));
};

const loadNavigationWorkspaces = async () => {
  if (!props.showAppNavigation || !authState.accessToken) {
    navigationWorkspaces.value = [];
    return;
  }

  try {
    const response = await fetch('/api/dashboard', {
      headers: {
        Accept: 'application/json',
        Authorization: `Bearer ${authState.accessToken}`,
      },
    });

    if (!response.ok) {
      return;
    }

    const payload = await response.json();
    navigationWorkspaces.value = Array.isArray(payload?.workspaces) ? payload.workspaces : [];
  } catch {
    navigationWorkspaces.value = [];
  }
};

onMounted(async () => {
  await nextTick();

  if (contentRef.value && window.Alpine?.initTree) {
    window.Alpine.initTree(contentRef.value);
  }

  window.addEventListener('keydown', handleGlobalAppKeydown);
  syncMobilePlatform();
  window.addEventListener('resize', syncMobilePlatform);
  window.addEventListener('orientationchange', syncMobilePlatform);
  await loadNavigationWorkspaces();
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalAppKeydown);
  window.removeEventListener('resize', syncMobilePlatform);
  window.removeEventListener('orientationchange', syncMobilePlatform);
});

watch(() => authState.accessToken, loadNavigationWorkspaces);
watch(() => props.showAppNavigation, loadNavigationWorkspaces);
watch(() => route.fullPath, () => {
  mobileNavOpen.value = false;
  syncMobilePlatform();
});
</script>

<template>
  <div class="min-h-screen bg-stone-50">
    <header v-if="!showAppNavigation" class="sticky top-0 z-50 border-b border-stone-200/80 bg-white/90 backdrop-blur">
      <div class="tw-toastit-shell px-4 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-3 py-4 lg:flex-row lg:items-center lg:justify-between">
          <div :class="showAppNavigation ? 'flex items-center justify-between gap-4' : 'flex w-full items-center justify-between gap-4'">
            <a :href="dashboardUrl" class="inline-flex items-center gap-2 text-stone-950">
              <img :src="'/assets/logo.png'" alt="Toastit" class="h-10 w-auto object-contain">
              <span class="text-lg font-black tracking-[0.12em] text-stone-950">TOASTIT</span>
            </a>

            <a
              v-if="!showAppNavigation && publicCtaHref && publicCtaLabel"
              :href="publicCtaHref"
              class="inline-flex items-center justify-center rounded-full bg-amber-200 px-5 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-300"
            >
              {{ publicCtaLabel }}
            </a>
            <button
              v-else-if="!showAppNavigation && publicCtaLabel"
              type="button"
              class="inline-flex items-center justify-center rounded-full bg-amber-200 px-5 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-300"
              @click="emit('public-cta-click')"
            >
              {{ publicCtaLabel }}
            </button>

            <button
              v-if="showAppNavigation"
              type="button"
              class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-700 lg:hidden"
              @click="userMenuOpen = !userMenuOpen"
            >
              <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
              <span>Account</span>
            </button>
          </div>

          <div v-if="showAppNavigation" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6">
            <div class="flex items-center gap-3">
              <button
                type="button"
                class="relative inline-flex h-11 w-11 items-center justify-center rounded-full border transition"
                :class="currentSection === 'inbox'
                  ? 'border-amber-300 bg-amber-100 text-amber-700'
                  : 'border-stone-200 bg-white text-stone-600 hover:border-stone-300 hover:text-stone-950'"
                @click="openInbox"
                title="Inbox"
              >
                <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                <span
                  v-if="inboxOpenCount > 0"
                  class="tw-inbox-attention-badge"
                >
                  {{ inboxOpenCount }}
                </span>
                <span class="sr-only">Open inbox</span>
              </button>

              <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                @click="lockSession"
                title="Lock session"
              >
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <span class="sr-only">Lock session</span>
              </button>

              <button
                type="button"
                class="inline-flex h-11 items-center gap-2 rounded-full border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
                @click="openGlobalQuickAdd"
                title="Quick add toast"
              >
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span>Quick add</span>
              </button>

              <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                @click="keyboardShortcutsOpen = true"
                title="Keyboard shortcuts"
              >
                <i class="fa-solid fa-keyboard" aria-hidden="true"></i>
                <span class="sr-only">Open keyboard shortcuts</span>
              </button>

              <div class="relative hidden lg:block">
              <button
                class="inline-flex items-center gap-3 rounded-full bg-white px-4 py-2.5 text-sm font-medium text-stone-700 transition hover:bg-stone-50"
                type="button"
                @click="userMenuOpen = !userMenuOpen"
              >
                <span class="inline-grid h-9 w-9 place-items-center rounded-full bg-stone-100 text-sm font-semibold text-stone-700">
                  {{ user?.displayName?.slice(0, 2)?.toUpperCase() ?? 'CO' }}
                </span>
                <span class="max-w-[12rem] truncate">{{ user?.displayName ?? 'Account' }}</span>
                <i class="fa-solid fa-chevron-down text-xs text-stone-400" aria-hidden="true"></i>
              </button>

              <div v-if="userMenuOpen" class="absolute right-0 top-[calc(100%+0.75rem)] w-72 rounded-3xl border border-stone-200 bg-white p-4 shadow-2xl shadow-stone-200/60">
                <div class="space-y-1 pb-4">
                  <p class="text-base font-semibold text-stone-950">{{ user?.displayName ?? 'Account' }}</p>
                </div>
                <div class="space-y-2">
                  <a v-if="user?.isRoot" href="/admin" class="flex items-center rounded-2xl px-4 py-3 text-sm font-medium text-stone-700 transition hover:bg-stone-100">Admin</a>
                  <a :href="profileUrl" class="flex items-center rounded-2xl px-4 py-3 text-sm font-medium text-stone-700 transition hover:bg-stone-100">My profile</a>
                  <button class="flex w-full items-center justify-center rounded-2xl bg-amber-200 px-4 py-3 text-sm font-semibold text-amber-900 transition hover:bg-amber-300" type="button" @click="logout">
                    Sign out
                  </button>
                </div>
              </div>
            </div>
            </div>

            <div v-if="userMenuOpen" class="grid gap-3 rounded-3xl border border-stone-200 bg-white p-4 shadow-sm lg:hidden">
              <div class="space-y-1">
                <p class="text-base font-semibold text-stone-950">{{ user?.displayName ?? 'Account' }}</p>
              </div>
              <a v-if="user?.isRoot" href="/admin" class="rounded-2xl border border-stone-200 px-4 py-3 text-sm font-medium text-stone-700">Admin</a>
              <a :href="profileUrl" class="rounded-2xl border border-stone-200 px-4 py-3 text-sm font-medium text-stone-700">My profile</a>
              <button class="w-full rounded-2xl bg-amber-200 px-4 py-3 text-sm font-semibold text-amber-900" type="button" @click="logout">Sign out</button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <ModalDialog v-if="showAppNavigation && keyboardShortcutsOpen" max-width-class="max-w-4xl" @close="keyboardShortcutsOpen = false">
      <ModalHeader
        eyebrow="Keyboard"
        title="Keyboard shortcuts"
        description="Shortcuts currently available across the app."
        @close="keyboardShortcutsOpen = false"
      />

      <div class="space-y-6 overflow-y-auto px-6 py-6">
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Global</p>
          <div class="grid gap-3">
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Go to dashboard</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">H</span>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Quick add a toast</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">C</span>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Close any modal</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">Esc</span>
            </div>
            <div class="rounded-2xl border border-sky-200 bg-sky-50 px-4 py-3 text-sm text-sky-900">
              Email capture: use your personal inbox address from <a href="/app/profile?section=infos" class="font-semibold underline">Profile</a> to create toasts by email.
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Dashboard</p>
          <div class="grid gap-3">
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Open workspace by position in the list</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">1…9</span>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Workspace</p>
          <div class="grid gap-3">
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Open advanced new toast modal</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">T</span>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Go to previous or next toast in the open modal</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">← →</span>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Submit the new toast modal form</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">Cmd/Ctrl + Enter</span>
            </div>
          </div>
        </div>
      </div>
    </ModalDialog>

    <main :class="showAppNavigation && !mobileAppModeActive ? 'py-0 lg:py-6' : 'py-0'">
      <template v-if="showAppNavigation && !mobileAppModeActive">
        <div class="px-0 lg:px-6">
          <div class="mb-0 flex items-center justify-between rounded-none border border-stone-200 bg-white px-4 py-3 lg:hidden">
            <a :href="dashboardUrl" class="inline-flex items-center text-stone-950">
              <img :src="'/assets/logo.png'" alt="Toastit" class="h-9 w-auto object-contain">
            </a>
            <button
              type="button"
              class="inline-grid h-9 w-9 place-items-center rounded-full border border-stone-200 bg-white text-stone-700 transition hover:border-stone-300"
              @click="mobileNavOpen = true"
            >
              <i class="fa-solid fa-bars text-sm" aria-hidden="true"></i>
              <span class="sr-only">Open navigation</span>
            </button>
          </div>
          <div class="grid gap-6 lg:grid-cols-[17rem_minmax(0,1fr)]">
            <aside class="hidden lg:block">
              <div class="space-y-4 rounded-3xl border border-stone-200 bg-white p-4 shadow-sm">
                <a :href="dashboardUrl" class="inline-flex items-center text-stone-950">
                  <img :src="'/assets/logo.png'" alt="Toastit" class="h-10 w-auto object-contain">
                </a>
                <div class="my-1 h-px bg-stone-100"></div>
                <p class="px-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Navigation</p>
                <a
                  :href="dashboardUrl"
                  class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                  :class="currentSection === 'workspace' ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-100'"
                >
                  <span class="inline-flex items-center gap-3">
                    <i class="fa-solid fa-table-columns w-4 text-center" aria-hidden="true"></i>
                    <span>Workspaces</span>
                  </span>
                  <span class="inline-flex items-center gap-1">
                    <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-600">
                      {{ navigationOpenCount }}
                    </span>
                    <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                      {{ navigationAssignedCount }}
                    </span>
                  </span>
                </a>
                <div class="space-y-1 pl-5">
                  <a
                    v-for="workspace in listedNavigationWorkspaces"
                    :key="workspace.id"
                    :href="workspaceHref(workspace)"
                    class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm transition"
                    :class="isWorkspaceActive(workspace) ? 'bg-amber-50 font-semibold text-amber-900' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'"
                  >
                    <span class="flex min-w-0 items-center gap-2">
                      <span class="truncate">{{ workspace.name }}</span>
                      <span
                        class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.06em]"
                        :class="workspaceModeBadgeClass(workspace)"
                      >
                        {{ workspaceModeLabel(workspace) }}
                      </span>
                    </span>
                    <span class="inline-flex items-center gap-1">
                      <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-600">
                        {{ workspace.openItemCount ?? 0 }}
                      </span>
                      <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                        {{ workspace.assignedOpenItemCount ?? 0 }}
                      </span>
                    </span>
                  </a>
                </div>
                <a
                  href="/app/inbox"
                  class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                  :class="currentSection === 'inbox' ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-100'"
                >
                  <span class="inline-flex items-center gap-3">
                    <i class="fa-solid fa-inbox w-4 text-center" aria-hidden="true"></i>
                    <span>Inbox</span>
                  </span>
                  <span
                    v-if="inboxOpenCount > 0"
                    class="tw-inbox-attention-badge tw-inbox-attention-badge--inline"
                  >
                    {{ inboxOpenCount }}
                  </span>
                </a>
                <a
                  :href="profileUrl"
                  class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                  :class="currentSection === 'profile' ? 'bg-stone-100 text-stone-900' : 'text-stone-700 hover:bg-stone-100'"
                >
                  <i class="fa-solid fa-user w-4 text-center" aria-hidden="true"></i>
                  <span>My profile</span>
                </a>
                <div v-if="currentSection === 'profile'" class="space-y-1 pl-5">
                  <a
                    v-for="section in profileSections"
                    :key="section.key"
                    :href="profileSectionHref(section.key)"
                    class="flex items-center rounded-xl px-3 py-2 text-sm transition"
                    :class="currentProfileSection === section.key ? 'bg-stone-100 font-semibold text-stone-900' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'"
                  >
                    <span class="truncate">{{ section.label }}</span>
                  </a>
                </div>
                <a
                  v-if="user?.isRoot"
                  href="/admin"
                  class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100"
                >
                  <i class="fa-solid fa-shield-halved w-4 text-center" aria-hidden="true"></i>
                  <span>Admin</span>
                </a>
                <div class="my-1 h-px bg-stone-100"></div>
                <div class="flex items-center justify-between gap-2 px-1">
                  <button
                    type="button"
                    class="inline-grid h-9 w-9 place-items-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                    title="Shortcuts"
                    @click="keyboardShortcutsOpen = true"
                  >
                    <i class="fa-solid fa-keyboard text-sm" aria-hidden="true"></i>
                    <span class="sr-only">Shortcuts</span>
                  </button>
                  <button
                    type="button"
                    class="inline-grid h-9 w-9 place-items-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                    title="Lock session"
                    @click="lockSession"
                  >
                    <i class="fa-solid fa-lock text-sm" aria-hidden="true"></i>
                    <span class="sr-only">Lock session</span>
                  </button>
                  <button
                    type="button"
                    class="inline-grid h-9 w-9 place-items-center rounded-full bg-amber-200 text-amber-900 transition hover:bg-amber-300"
                    title="Sign out"
                    @click="logout"
                  >
                    <i class="fa-solid fa-right-from-bracket text-sm" aria-hidden="true"></i>
                    <span class="sr-only">Sign out</span>
                  </button>
                </div>
              </div>
            </aside>

            <div class="min-w-0">
              <div v-if="slots.default">
                <slot />
              </div>
              <div v-else ref="contentRef" v-html="contentHtml"></div>
            </div>
          </div>

          <div v-if="mobileNavOpen" class="fixed inset-0 z-50 lg:hidden">
            <button
              type="button"
              class="absolute inset-0 bg-stone-950/45"
              @click="mobileNavOpen = false"
            >
              <span class="sr-only">Close navigation</span>
            </button>
            <aside class="absolute inset-y-0 left-0 w-[18rem] overflow-y-auto border-r border-stone-200 bg-white p-4 shadow-2xl">
              <div class="mb-4 flex items-center justify-between">
                <a :href="dashboardUrl" class="inline-flex items-center text-stone-950">
                  <img :src="'/assets/logo.png'" alt="Toastit" class="h-9 w-auto object-contain">
                </a>
                <button
                  type="button"
                  class="inline-grid h-8 w-8 place-items-center rounded-full border border-stone-200 bg-white text-stone-700"
                  @click="mobileNavOpen = false"
                >
                  <i class="fa-solid fa-xmark text-sm" aria-hidden="true"></i>
                  <span class="sr-only">Close navigation</span>
                </button>
              </div>
              <div class="space-y-4">
                <div class="my-1 h-px bg-stone-100"></div>
                <p class="px-2 text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Navigation</p>
                <a
                  :href="dashboardUrl"
                  class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                  :class="currentSection === 'workspace' ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-100'"
                >
                  <span class="inline-flex items-center gap-3">
                    <i class="fa-solid fa-table-columns w-4 text-center" aria-hidden="true"></i>
                    <span>Workspaces</span>
                  </span>
                  <span class="inline-flex items-center gap-1">
                    <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-600">
                      {{ navigationOpenCount }}
                    </span>
                    <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                      {{ navigationAssignedCount }}
                    </span>
                  </span>
                </a>
                <div class="space-y-1 pl-5">
                  <a
                    v-for="workspace in listedNavigationWorkspaces"
                    :key="workspace.id"
                    :href="workspaceHref(workspace)"
                    class="flex items-center justify-between gap-2 rounded-xl px-3 py-2 text-sm transition"
                    :class="isWorkspaceActive(workspace) ? 'bg-amber-50 font-semibold text-amber-900' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'"
                  >
                    <span class="flex min-w-0 items-center gap-2">
                      <span class="truncate">{{ workspace.name }}</span>
                      <span
                        class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.06em]"
                        :class="workspaceModeBadgeClass(workspace)"
                      >
                        {{ workspaceModeLabel(workspace) }}
                      </span>
                    </span>
                    <span class="inline-flex items-center gap-1">
                      <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-stone-100 px-1.5 py-0.5 text-[10px] font-semibold text-stone-600">
                        {{ workspace.openItemCount ?? 0 }}
                      </span>
                      <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-amber-100 px-1.5 py-0.5 text-[10px] font-semibold text-amber-700">
                        {{ workspace.assignedOpenItemCount ?? 0 }}
                      </span>
                    </span>
                  </a>
                </div>
                <a
                  href="/app/inbox"
                  class="flex items-center justify-between gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                  :class="currentSection === 'inbox' ? 'bg-amber-100 text-amber-900' : 'text-stone-700 hover:bg-stone-100'"
                >
                  <span class="inline-flex items-center gap-3">
                    <i class="fa-solid fa-inbox w-4 text-center" aria-hidden="true"></i>
                    <span>Inbox</span>
                  </span>
                  <span
                    v-if="inboxOpenCount > 0"
                    class="tw-inbox-attention-badge tw-inbox-attention-badge--inline"
                  >
                    {{ inboxOpenCount }}
                  </span>
                </a>
                <a
                  :href="profileUrl"
                  class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium transition"
                  :class="currentSection === 'profile' ? 'bg-stone-100 text-stone-900' : 'text-stone-700 hover:bg-stone-100'"
                >
                  <i class="fa-solid fa-user w-4 text-center" aria-hidden="true"></i>
                  <span>My profile</span>
                </a>
                <div v-if="currentSection === 'profile'" class="space-y-1 pl-5">
                  <a
                    v-for="section in profileSections"
                    :key="section.key"
                    :href="profileSectionHref(section.key)"
                    class="flex items-center rounded-xl px-3 py-2 text-sm transition"
                    :class="currentProfileSection === section.key ? 'bg-stone-100 font-semibold text-stone-900' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-900'"
                  >
                    <span class="truncate">{{ section.label }}</span>
                  </a>
                </div>
                <a
                  v-if="user?.isRoot"
                  href="/admin"
                  class="flex items-center gap-3 rounded-2xl px-3 py-2.5 text-sm font-medium text-stone-700 transition hover:bg-stone-100"
                >
                  <i class="fa-solid fa-shield-halved w-4 text-center" aria-hidden="true"></i>
                  <span>Admin</span>
                </a>
                <div class="my-1 h-px bg-stone-100"></div>
                <div class="flex items-center justify-between gap-2 px-1">
                  <button
                    type="button"
                    class="inline-grid h-9 w-9 place-items-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                    title="Shortcuts"
                    @click="keyboardShortcutsOpen = true"
                  >
                    <i class="fa-solid fa-keyboard text-sm" aria-hidden="true"></i>
                    <span class="sr-only">Shortcuts</span>
                  </button>
                  <button
                    type="button"
                    class="inline-grid h-9 w-9 place-items-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                    title="Lock session"
                    @click="lockSession"
                  >
                    <i class="fa-solid fa-lock text-sm" aria-hidden="true"></i>
                    <span class="sr-only">Lock session</span>
                  </button>
                  <button
                    type="button"
                    class="inline-grid h-9 w-9 place-items-center rounded-full bg-amber-200 text-amber-900 transition hover:bg-amber-300"
                    title="Sign out"
                    @click="logout"
                  >
                    <i class="fa-solid fa-right-from-bracket text-sm" aria-hidden="true"></i>
                    <span class="sr-only">Sign out</span>
                  </button>
                </div>
              </div>
            </aside>
          </div>
        </div>
      </template>
      <template v-else-if="showAppNavigation && mobileAppModeActive">
        <MobileAppShell
          :dashboard-url="dashboardUrl"
          :profile-url="profileUrl"
          :user="user"
          :content-html="contentHtml"
          :navigation-open-count="navigationOpenCount"
          :navigation-assigned-count="navigationAssignedCount"
          :navigation-workspaces="navigationWorkspaces"
        >
          <slot v-if="slots.default" />
        </MobileAppShell>
      </template>
      <template v-else>
        <div v-if="slots.default" class="tw-toastit-shell px-4 sm:px-6 lg:px-8">
          <slot />
        </div>
        <div v-else class="tw-toastit-shell px-4 sm:px-6 lg:px-8" ref="contentRef" v-html="contentHtml"></div>
      </template>
    </main>
  </div>
</template>
