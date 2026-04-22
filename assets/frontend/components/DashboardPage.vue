<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute } from 'vue-router';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { WorkspacesApi } from '../api/workspaces';
import { nextSnoozeDueOn } from '../utils/workspaceFormatting';
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
const isSnoozingActionId = ref(null);
const MY_DAY_SWIPE_ACTION_SLOT_WIDTH = 56;
const myDaySwipe = ref({
  activeActionId: null,
  revealById: {},
  startX: 0,
  startY: 0,
  startReveal: 0,
  isDragging: false,
  suppressTapForId: null,
});
const myDaySwipeActionsWidthById = ref({});
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
  window.dispatchEvent(new CustomEvent('toastit:create-workspace-flow-state', { detail: { active: true } }));
};

const closeCreateWorkspaceModal = () => {
  isCreateWorkspaceModalOpen.value = false;
  window.dispatchEvent(new CustomEvent('toastit:create-workspace-flow-state', { detail: { active: false } }));
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
  if (isMobileViewport.value && route.name === 'dashboard') {
    router.push({
      path: route.path,
      query: {
        ...route.query,
        toastId: String(toastId),
      },
    });
    return;
  }

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

const myDayActions = computed(() => {
  return Array.isArray(payload.value?.myActions?.actions) ? payload.value.myActions.actions : [];
});
const mobileWorkspaceSummaries = computed(() => {
  const workspaces = Array.isArray(payload.value?.workspaces) ? payload.value.workspaces : [];

  return workspaces.filter((workspace) => {
    if (workspace?.isInboxWorkspace !== true) {
      return true;
    }

    return Number(workspace?.openItemCount ?? 0) > 0;
  });
});

const formatDateDisplay = (value) => {
  if (!(value instanceof Date) || Number.isNaN(value.getTime())) {
    return 'No due date';
  }

  const locale = typeof window !== 'undefined' && window.navigator?.language
    ? window.navigator.language
    : 'en-US';

  return new Intl.DateTimeFormat(locale, {
    day: '2-digit',
    month: 'short',
    year: 'numeric',
  }).format(value);
};

const refreshMyActionsSummary = () => {
  const actions = myDayActions.value;
  const workspaceIds = new Set();
  let lateCount = 0;
  let dueSoonCount = 0;

  for (const action of actions) {
    const workspaceId = Number(action?.workspace?.id ?? 0);
    if (Number.isFinite(workspaceId) && workspaceId > 0) {
      workspaceIds.add(workspaceId);
    }

    const status = actionDateStatus(action);
    if (status === 'late') {
      lateCount += 1;
    } else if (status === 'due-soon') {
      dueSoonCount += 1;
    }
  }

  if (!payload.value?.myActions?.summary) {
    return;
  }

  payload.value.myActions.summary.assignedCount = actions.length;
  payload.value.myActions.summary.lateCount = lateCount;
  payload.value.myActions.summary.dueSoonCount = dueSoonCount;
  payload.value.myActions.summary.workspaceCount = workspaceIds.size;
};

const mutateMyAction = (actionId, updater) => {
  const actions = Array.isArray(payload.value?.myActions?.actions) ? payload.value.myActions.actions : null;
  if (!actions) {
    return null;
  }

  const target = actions.find((candidate) => Number(candidate?.id) === Number(actionId));
  if (!target) {
    return null;
  }

  updater(target);
  return target;
};

const removeMyAction = (actionId) => {
  const actions = Array.isArray(payload.value?.myActions?.actions) ? payload.value.myActions.actions : null;
  if (!actions) {
    return;
  }

  const index = actions.findIndex((candidate) => Number(candidate?.id) === Number(actionId));
  if (index < 0) {
    return;
  }

  actions.splice(index, 1);
  closeMyDaySwipe(actionId);
  refreshMyActionsSummary();
};

const applyDueState = (action, dueOn) => {
  const dueAt = typeof dueOn === 'string' && dueOn !== '' ? new Date(`${dueOn}T00:00:00`) : null;
  const today = new Date();
  today.setHours(0, 0, 0, 0);
  const sevenDaysFromNow = new Date(today);
  sevenDaysFromNow.setDate(today.getDate() + 7);

  action.dueOn = dueOn;
  action.isLate = !!(dueAt && !Number.isNaN(dueAt.getTime()) && dueAt < today);
  action.isDueSoon = !!(dueAt && !Number.isNaN(dueAt.getTime()) && !action.isLate && dueAt <= sevenDaysFromNow);
};

const snoozeAction = async (action) => {
  const actionId = Number(action?.id ?? 0);
  if (!Number.isFinite(actionId) || actionId <= 0) {
    return;
  }

  isSnoozingActionId.value = actionId;
  const nextDueOn = nextSnoozeDueOn(action?.dueOn);
  const nextDueAt = new Date(`${nextDueOn}T12:00:00`);

  const { ok } = await workspacesApi.updateToast(actionId, {
    title: action?.title ?? '',
    description: action?.description ?? '',
    ownerId: action?.owner?.id ? String(action.owner.id) : '',
    dueOn: nextDueOn,
  });

  isSnoozingActionId.value = null;
  if (!ok) {
    return;
  }

  mutateMyAction(actionId, (target) => {
    applyDueState(target, nextDueOn);
    target.dueOnDisplay = formatDateDisplay(nextDueAt);
  });
  refreshMyActionsSummary();
};

const toggleVoteAction = async (action) => {
  const actionId = Number(action?.id ?? 0);
  if (!Number.isFinite(actionId) || actionId <= 0) {
    return;
  }

  const previousVoted = !!action.currentUserHasVoted;
  const previousVoteCount = Number(action.voteCount ?? 0);
  const nextVoted = !previousVoted;
  const nextVoteCount = Math.max(0, previousVoteCount + (nextVoted ? 1 : -1));

  mutateMyAction(actionId, (target) => {
    target.currentUserHasVoted = nextVoted;
    target.voteCount = nextVoteCount;
  });

  const response = await workspacesApi.toggleVote(actionId);
  if (!response.ok) {
    mutateMyAction(actionId, (target) => {
      target.currentUserHasVoted = previousVoted;
      target.voteCount = previousVoteCount;
    });
  }
};

const toggleBoostAction = async (action) => {
  const actionId = Number(action?.id ?? 0);
  if (!Number.isFinite(actionId) || actionId <= 0) {
    return;
  }

  const previousBoosted = !!action.isBoosted;
  mutateMyAction(actionId, (target) => {
    target.isBoosted = !previousBoosted;
  });

  const response = await workspacesApi.toggleBoost(actionId);
  if (!response.ok) {
    mutateMyAction(actionId, (target) => {
      target.isBoosted = previousBoosted;
    });
  }
  refreshMyActionsSummary();
};

const markDoneAction = async (action) => {
  const actionId = Number(action?.id ?? 0);
  if (!Number.isFinite(actionId) || actionId <= 0) {
    return;
  }
  const { ok } = await workspacesApi.setReady(actionId, true);
  if (!ok) {
    return;
  }

  mutateMyAction(actionId, (target) => {
    target.status = 'ready';
  });
};

const deleteAction = async (action) => {
  const actionId = Number(action?.id ?? 0);
  if (!Number.isFinite(actionId) || actionId <= 0) {
    return;
  }

  const response = await workspacesApi.toggleVeto(actionId);
  if (!response.ok) {
    return;
  }

  removeMyAction(actionId);
};

const myDaySwipeActionCount = (action) => {
  let count = 1; // Snooze
  if (action?.currentUserCanVote) {
    count += 1;
  }
  if (action?.currentUserCanBoost) {
    count += 1;
  }
  if (action?.currentUserCanMarkReady) {
    count += 1;
  }
  if (action?.currentUserCanDelete) {
    count += 1;
  }

  return count;
};

const myDaySwipeActionsWidth = (actionId) => {
  const measuredWidth = Number(myDaySwipeActionsWidthById.value?.[actionId] ?? 0);
  if (Number.isFinite(measuredWidth) && measuredWidth > 0) {
    return measuredWidth;
  }

  const action = myDayActions.value.find((candidate) => Number(candidate?.id) === Number(actionId));
  if (!action) {
    return MY_DAY_SWIPE_ACTION_SLOT_WIDTH;
  }

  return myDaySwipeActionCount(action) * MY_DAY_SWIPE_ACTION_SLOT_WIDTH;
};

const registerMyDaySwipeActionsRef = (actionId, element) => {
  const normalizedId = Number(actionId ?? 0);
  if (!Number.isFinite(normalizedId) || normalizedId <= 0) {
    return;
  }

  if (!(element instanceof HTMLElement)) {
    delete myDaySwipeActionsWidthById.value[normalizedId];
    return;
  }

  myDaySwipeActionsWidthById.value[normalizedId] = Math.round(element.getBoundingClientRect().width);
};

const getMyDayReveal = (actionId) => {
  const value = Number(myDaySwipe.value.revealById?.[actionId] ?? 0);
  if (!Number.isFinite(value) || value < 0) {
    return 0;
  }

  return Math.min(myDaySwipeActionsWidth(actionId), value);
};

const myDaySwipeStyle = (actionId) => ({
  transform: `translateX(-${getMyDayReveal(actionId)}px)`,
});

const closeMyDaySwipe = (actionId = null) => {
  if (actionId !== null) {
    myDaySwipe.value.revealById[actionId] = 0;
    if (myDaySwipe.value.activeActionId === actionId) {
      myDaySwipe.value.activeActionId = null;
    }
    return;
  }

  const activeActionId = myDaySwipe.value.activeActionId;
  if (activeActionId !== null) {
    myDaySwipe.value.revealById[activeActionId] = 0;
  }
  myDaySwipe.value.activeActionId = null;
};

const handleMyDayTouchStart = (actionId, event) => {
  const touch = event.touches?.[0];
  if (!touch) {
    return;
  }

  if (myDaySwipe.value.activeActionId !== null && myDaySwipe.value.activeActionId !== actionId) {
    closeMyDaySwipe();
  }

  myDaySwipe.value.activeActionId = actionId;
  myDaySwipe.value.startX = touch.clientX;
  myDaySwipe.value.startY = touch.clientY;
  myDaySwipe.value.startReveal = getMyDayReveal(actionId);
  myDaySwipe.value.isDragging = false;
};

const handleMyDayTouchMove = (actionId, event) => {
  const touch = event.touches?.[0];
  if (!touch || myDaySwipe.value.activeActionId !== actionId) {
    return;
  }

  const deltaX = touch.clientX - myDaySwipe.value.startX;
  const deltaY = touch.clientY - myDaySwipe.value.startY;
  if (!myDaySwipe.value.isDragging) {
    if (Math.abs(deltaY) > Math.abs(deltaX)) {
      return;
    }
    if (Math.abs(deltaX) < 8) {
      return;
    }
    myDaySwipe.value.isDragging = true;
  }

  if (event.cancelable) {
    event.preventDefault();
  }

  const nextReveal = myDaySwipe.value.startReveal - deltaX;
  myDaySwipe.value.revealById[actionId] = Math.min(
    myDaySwipeActionsWidth(actionId),
    Math.max(0, nextReveal),
  );
};

const handleMyDayTouchEnd = (actionId) => {
  if (myDaySwipe.value.activeActionId !== actionId) {
    return;
  }

  const reveal = getMyDayReveal(actionId);
  const width = myDaySwipeActionsWidth(actionId);
  const shouldStayOpen = reveal >= width * 0.38;
  myDaySwipe.value.revealById[actionId] = shouldStayOpen ? width : 0;
  myDaySwipe.value.suppressTapForId = myDaySwipe.value.isDragging ? actionId : null;
  myDaySwipe.value.activeActionId = shouldStayOpen ? actionId : null;
  myDaySwipe.value.isDragging = false;
};

const handleMyDayRowTap = (actionId) => {
  if (myDaySwipe.value.suppressTapForId === actionId) {
    myDaySwipe.value.suppressTapForId = null;
    return;
  }

  const reveal = getMyDayReveal(actionId);
  if (reveal > 0) {
    closeMyDaySwipe(actionId);
    return;
  }

  openToast(actionId);
};

const executeMyDayAction = async (actionId, callback) => {
  await callback();
  closeMyDaySwipe(actionId);
};

const actionBorderStyle = (action) => {
  const status = actionDateStatus(action);
  let borderLeftColor = 'transparent';

  if (action?.isBoosted) {
    borderLeftColor = 'rgb(148 163 184)';
  } else if (status === 'late') {
    borderLeftColor = 'rgb(239 68 68)';
  } else if (status === 'due-soon') {
    borderLeftColor = 'rgba(59, 130, 246, 0.62)';
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
  window.dispatchEvent(new CustomEvent('toastit:create-workspace-flow-state', { detail: { active: false } }));
});
</script>

<template>
  <section class="space-y-6">
    <div v-if="!isMobileViewport || mobileSection === 'toasts'" class="tw-toastit-card p-6">
        <div class="sticky top-0 z-20 -mx-6 mb-5 flex flex-col gap-4 bg-white/95 px-6 py-2 backdrop-blur lg:static lg:mx-0 lg:bg-transparent lg:px-0 lg:py-0 lg:flex-row lg:items-center lg:justify-between">
          <div>
            <h2 class="text-2xl font-semibold tracking-tight text-stone-950">My Day.</h2>
            <p class="mt-1 text-xs font-medium uppercase tracking-[0.14em] text-stone-500">Personal + Team execution</p>
          </div>
        </div>

        <EmptyState v-if="isLoading" message="Loading..." />
        <EmptyState v-else-if="!(myDayActions.length ?? 0)" message="No assigned actions right now." />
        <div v-else class="overflow-hidden -mx-6 lg:mx-0">
          <div class="space-y-3 bg-white py-4 lg:hidden">
            <div
              v-for="action in myDayActions"
              :key="action.id"
              class="relative overflow-hidden"
            >
              <div
                :ref="(el) => registerMyDaySwipeActionsRef(action.id, el)"
                class="absolute inset-y-0 right-0 z-0 flex items-stretch rounded-l-2xl border-y border-stone-200 bg-stone-100"
              >
                <button
                  v-if="action.currentUserCanVote"
                  type="button"
                  class="inline-grid w-14 place-items-center border-l border-stone-200 transition"
                  :class="action.currentUserHasVoted ? 'bg-amber-200 text-black hover:bg-amber-300' : 'bg-white text-amber-300 hover:bg-amber-50'"
                  @click.stop="executeMyDayAction(action.id, () => toggleVoteAction(action))"
                >
                  <i class="fa-solid fa-thumbs-up text-sm" aria-hidden="true"></i>
                </button>
                <button
                  v-if="action.currentUserCanBoost"
                  type="button"
                  class="inline-grid w-14 place-items-center border-l border-stone-200 transition"
                  :class="action.isBoosted ? 'bg-slate-400 text-black hover:bg-slate-300' : 'bg-white text-slate-700 hover:bg-slate-50'"
                  @click.stop="executeMyDayAction(action.id, () => toggleBoostAction(action))"
                >
                  <i class="fa-solid fa-star text-sm" aria-hidden="true"></i>
                </button>
                <button
                  v-if="action.currentUserCanMarkReady"
                  type="button"
                  class="inline-grid w-14 place-items-center border-l border-stone-200 transition"
                  :class="action.status === 'ready' ? 'bg-emerald-500 text-black hover:bg-emerald-400' : 'bg-white text-emerald-700 hover:bg-emerald-50'"
                  @click.stop="executeMyDayAction(action.id, () => markDoneAction(action))"
                >
                  <i class="fa-solid fa-check text-sm" aria-hidden="true"></i>
                </button>
                <button
                  v-if="action.currentUserCanDelete"
                  type="button"
                  class="inline-grid w-14 place-items-center border-l border-stone-200 bg-white text-red-700 transition hover:bg-red-50"
                  @click.stop="executeMyDayAction(action.id, () => deleteAction(action))"
                >
                  <i class="fa-solid fa-trash text-sm" aria-hidden="true"></i>
                </button>
                <button
                  type="button"
                  class="inline-grid w-14 place-items-center border-l border-stone-200 bg-white text-blue-600 transition hover:bg-blue-50"
                  :disabled="isSnoozingActionId === action.id"
                  @click.stop="executeMyDayAction(action.id, () => snoozeAction(action))"
                >
                  <i v-if="isSnoozingActionId !== action.id" class="fa-solid fa-clock text-sm" aria-hidden="true"></i>
                  <i v-else class="fa-solid fa-spinner animate-spin text-sm" aria-hidden="true"></i>
                </button>
              </div>
              <div
                class="relative z-10 cursor-pointer space-y-2 px-4 py-1 transition-all duration-150 hover:bg-stone-50"
                :class="getMyDayReveal(action.id) > 0 ? 'bg-stone-50' : 'bg-white'"
                :style="[actionBorderStyle(action), myDaySwipeStyle(action.id)]"
                @touchstart="handleMyDayTouchStart(action.id, $event)"
                @touchmove="handleMyDayTouchMove(action.id, $event)"
                @touchend="handleMyDayTouchEnd(action.id)"
                @touchcancel="handleMyDayTouchEnd(action.id)"
                @click="handleMyDayRowTap(action.id)"
              >
                <p class="block w-full text-left text-sm font-medium leading-5 text-stone-900 line-clamp-2">
                  {{ action.title }}
                </p>
                <p class="min-w-0 truncate text-xs leading-5 text-stone-600">
                  <i v-if="action.isBoosted" class="fa-solid fa-star mr-1 text-slate-400" aria-hidden="true"></i>
                  {{ action.workspace.name }} • {{ action.dueOnDisplay || 'No due date' }}
                </p>
              </div>
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
                v-for="action in myDayActions"
                :key="action.id"
                class="transition hover:bg-stone-50"
              >
                <td class="px-4 py-3 align-top" :style="actionBorderStyle(action)">
                  <button type="button" class="block w-full text-left" @click="openToast(action.id)">
                    <p class="block w-full truncate text-left font-medium text-stone-900">
                      {{ action.title }}
                    </p>
                    <p class="mt-1 truncate text-xs text-stone-600">
                      <i v-if="action.isBoosted" class="fa-solid fa-star mr-1 text-slate-400" aria-hidden="true"></i>
                      {{ action.workspace.name }}
                      <span class="xl:hidden"> • {{ action.dueOnDisplay || 'No due date' }}</span>
                    </p>
                  </button>
                  <div class="mt-2">
                    <button
                      type="button"
                      class="rounded-full border border-stone-200 bg-white px-3 py-1 text-[11px] font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
                      :disabled="isSnoozingActionId === action.id"
                      @click="snoozeAction(action)"
                    >
                      {{ isSnoozingActionId === action.id ? 'Snoozing…' : 'Snooze +1d' }}
                    </button>
                  </div>
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
      <EmptyState v-else-if="!(mobileWorkspaceSummaries.length ?? 0)" message="No workspace yet." />
      <div v-else class="-mx-6 space-y-3 bg-white py-4 lg:mx-0 lg:grid lg:gap-3 lg:space-y-0 lg:bg-transparent lg:py-0">
        <button
          v-for="workspace in mobileWorkspaceSummaries"
          :key="workspace.id"
          type="button"
          class="group block w-full border-l-[5px] border-transparent px-4 py-2 text-left transition hover:bg-stone-50 lg:rounded-2xl lg:border lg:border-stone-200 lg:bg-white lg:px-4 lg:py-3 lg:hover:border-amber-200 lg:hover:bg-amber-50/30"
          @click="openWorkspaceFromSummary(workspace)"
        >
          <div class="flex items-center justify-between gap-3">
            <div class="min-w-0">
              <p class="text-sm font-semibold leading-5 text-stone-950 line-clamp-2">
                {{ workspace.name }}
              </p>
              <span
                class="mt-1 inline-flex items-center rounded-full px-2 py-0.5 text-[10px] font-semibold uppercase tracking-[0.06em]"
                :class="workspaceModeBadgeClass(workspace)"
              >
                {{ workspaceModeLabel(workspace) }}
              </span>
              <p class="mt-1 text-xs leading-5 text-stone-500 line-clamp-2">
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
                  'inline-grid h-7 w-8 place-items-center text-[12px] font-semibold leading-none tabular-nums text-white',
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
