<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { WorkspacesApi } from '../api/workspaces';
import { defaultDueDateForPreset, renderToastDescription } from '../utils/workspaceFormatting';
import AvatarBadge from './AvatarBadge.vue';
import CommentComposer from './CommentComposer.vue';
import CommentThread from './CommentThread.vue';
import CompactDropdown from './CompactDropdown.vue';
import EmptyState from './EmptyState.vue';
import EyebrowLabel from './EyebrowLabel.vue';
import FollowUpEditor from './FollowUpEditor.vue';
import CreateToastModal from './CreateToastModal.vue';
import KeyboardHint from './KeyboardHint.vue';
import MemberListItem from './MemberListItem.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';
import PageHeader from './PageHeader.vue';
import SessionArchiveModal from './SessionArchiveModal.vue';
import ToastCurationModal from './ToastCurationModal.vue';
import ToastExecutionPlanPanel from './ToastExecutionPlanPanel.vue';
import ToastStatusBadge from './ToastStatusBadge.vue';
import ToastNavigationFooter from './ToastNavigationFooter.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
  standaloneToastId: { type: [String, Number], default: null },
  createOnlyMode: { type: Boolean, default: false },
});

const route = useRoute();
const router = useRouter();
const payload = ref(null);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const inviteEmail = ref('');
const itemForm = ref({ title: '', description: '', ownerId: '', dueOn: '' });
const currentToastFilter = ref('pending');
const currentAssigneeFilter = ref('');
const isApplyingFiltersFromRoute = ref(false);
const isSyncingFiltersToRoute = ref(false);
const isMobileStatusFilterModalOpen = ref(false);
const isMobileAssigneeFilterModalOpen = ref(false);
const vetoedVisibleCount = ref(20);
const resolvedVisibleCount = ref(20);
const isManageModalOpen = ref(false);
const isCreateToastModalOpen = ref(false);
const isMoveCopyToastModalOpen = ref(false);
const createToastModalRef = ref(null);
const editingToastId = ref(null);
const workspaceBackgroundFile = ref(null);
const workspaceBackgroundInput = ref(null);
const isWorkspaceBackgroundDragOver = ref(false);
const isDeletingWorkspace = ref(false);
const isDeleteWorkspaceConfirmOpen = ref(false);
const selectedToastModalId = ref(null);
const selectedToastModalCleanState = ref(null);
const toastModalNavigationBlocked = ref(false);
const selectedTargetWorkspaceId = ref('');
const workspaceSettingsForm = ref({ name: '', defaultDuePreset: 'next_week', isSoloWorkspace: false });
const executionPlanDraft = ref(null);
const executionPlanError = ref('');
const executionPlanNotice = ref('');
const isExecutionPlanGenerating = ref(false);
const executionPlanApplyingIndex = ref(-1);
const executionPlanActionStatuses = ref({});
const isToastCurationOpen = ref(false);
const toastCurationDraft = ref(null);
const toastCurationError = ref('');
const toastCurationNotice = ref('');
const isToastCurationGenerating = ref(false);
const toastCurationApplyingIndex = ref(-1);
const toastCurationActionStatuses = ref({});
const isToastDraftRefining = ref(false);
const TOAST_DRAFT_REFINE_TIMEOUT_MS = 30_000;
const toastDraftRefinementBackup = ref(null);
let toastDraftRefinementRequestId = 0;
const isSessionArchiveOpen = ref(false);
const selectedSessionArchiveId = ref(null);
const sessionArchiveError = ref('');
const sessionArchiveNotice = ref('');
const isSessionArchiveGenerating = ref(false);
const isSessionArchiveSaving = ref(false);
const isSessionArchiveSending = ref(false);
const isMobileViewport = ref(false);
const commentDrafts = ref({});
const workspaceBackgroundObjectUrl = ref('');
const inboxAddressCopied = ref(false);
const inboxAddressInput = ref(null);
const vetoedInfiniteLoader = ref(null);
const resolvedInfiniteLoader = ref(null);
const mobileCommentsSectionRef = ref(null);
const mobileActionBarDocked = ref(false);
const mobileActionBarScrollFrame = ref(0);
const isMobileCommentModalOpen = ref(false);
const mobileVetoConfirmToastId = ref(null);
let archivedToastObserver = null;
let inboxAddressCopiedTimeout = null;
const apiClient = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});
const workspacesApi = new WorkspacesApi(apiClient);

const workspace = computed(() => payload.value?.workspace ?? null);
const currentUser = computed(() => payload.value?.currentUser ?? null);
const standaloneMode = computed(() => null !== props.standaloneToastId && '' !== String(props.standaloneToastId));
const useDedicatedMobileToastView = computed(() => standaloneMode.value && isMobileViewport.value);
const TOAST_RETURN_TO_STORAGE_KEY = 'toastit:toast-return-to';
const otherWorkspaces = computed(() => payload.value?.otherWorkspaces ?? []);
const members = computed(() => payload.value?.memberships ?? []);
const participants = computed(() => payload.value?.participants ?? []);
const participantsLookup = computed(() => Object.fromEntries(
  participants.value.map((participant) => [participant.id, participant.displayName]),
));
const toastingSessions = computed(() => payload.value?.toastingSessions ?? []);
const agendaItems = computed(() => payload.value?.agendaItems ?? []);
const vetoedItems = computed(() => payload.value?.vetoedItems ?? []);
const resolvedItems = computed(() => payload.value?.resolvedItems ?? []);
const assigneeFilterOptions = computed(() => {
  const me = currentUser.value;
  const others = participants.value.filter((participant) => participant.id !== me?.id);

  return [
    { value: '', label: 'None' },
    ...(me ? [{
      value: String(me.id),
      label: 'Me',
      secondaryLabel: me.email ?? '',
      initials: me.initials ?? '',
      gravatarUrl: me.gravatarUrl ?? '',
      seed: me.id ?? me.displayName ?? me.email ?? 'me',
    }] : []),
    ...others.map((participant) => ({
      value: String(participant.id),
      label: participant.displayName,
      secondaryLabel: participant.email ?? '',
      initials: participant.initials ?? '',
      gravatarUrl: participant.gravatarUrl ?? '',
      seed: participant.id ?? participant.displayName ?? participant.email ?? '',
    })),
  ];
});

const statusFilterOptions = computed(() => [
  { value: 'pending', label: `New (${displayedPendingAgendaItems.value.length})` },
  { value: 'ready', label: `Ready (${displayedReadyAgendaItems.value.length})` },
  { value: 'discarded', label: `Declined (${displayedVetoedItems.value.length})` },
  { value: 'resolved', label: `Toasted (${displayedResolvedItems.value.length})` },
]);

const selectedStatusFilterLabel = computed(() => (
  statusFilterOptions.value.find((option) => option.value === currentToastFilter.value)?.label ?? 'Status'
));

const selectedAssigneeFilterLabel = computed(() => (
  assigneeFilterOptions.value.find((option) => option.value === currentAssigneeFilter.value)?.label ?? 'Assignee'
));

const resolveToastFilter = (value) => {
  const normalizedValue = typeof value === 'string' ? value : '';
  if (normalizedValue === 'active') {
    return 'pending';
  }

  return statusFilterOptions.value.some((option) => option.value === normalizedValue) ? normalizedValue : 'pending';
};

const resolveAssigneeFilter = (value) => {
  if (isSoloWorkspace.value) {
    return '';
  }

  const normalizedValue = typeof value === 'string' ? value : '';

  return assigneeFilterOptions.value.some((option) => option.value === normalizedValue) ? normalizedValue : '';
};

const applyFiltersFromRoute = () => {
  const resolvedToastFilter = resolveToastFilter(route.query.filter);
  const resolvedAssigneeFilter = resolveAssigneeFilter(route.query.assignee);

  if (resolvedToastFilter === currentToastFilter.value && resolvedAssigneeFilter === currentAssigneeFilter.value) {
    return;
  }

  isApplyingFiltersFromRoute.value = true;
  currentToastFilter.value = resolvedToastFilter;
  currentAssigneeFilter.value = resolvedAssigneeFilter;
  nextTick(() => {
    isApplyingFiltersFromRoute.value = false;
  });
};

const openMobileStatusFilterModal = () => {
  isMobileStatusFilterModalOpen.value = true;
};

const openMobileAssigneeFilterModal = () => {
  if (isSoloWorkspace.value) {
    return;
  }

  isMobileAssigneeFilterModalOpen.value = true;
};

const selectMobileStatusFilter = (value) => {
  currentToastFilter.value = resolveToastFilter(value);
  isMobileStatusFilterModalOpen.value = false;
};

const selectMobileAssigneeFilter = (value) => {
  currentAssigneeFilter.value = resolveAssigneeFilter(value);
  isMobileAssigneeFilterModalOpen.value = false;
};

const syncFiltersToRoute = async () => {
  if (isApplyingFiltersFromRoute.value || isSyncingFiltersToRoute.value) {
    return;
  }

  isSyncingFiltersToRoute.value = true;

  try {
  const nextQuery = { ...route.query };
  const resolvedToastFilter = resolveToastFilter(currentToastFilter.value);
  const resolvedAssigneeFilter = resolveAssigneeFilter(currentAssigneeFilter.value);
  const currentFilter = typeof route.query.filter === 'string' ? route.query.filter : undefined;
  const currentAssignee = typeof route.query.assignee === 'string' ? route.query.assignee : undefined;

  if (resolvedToastFilter === 'pending') {
    delete nextQuery.filter;
  } else {
    nextQuery.filter = resolvedToastFilter;
  }

  if (resolvedAssigneeFilter === '') {
    delete nextQuery.assignee;
  } else {
    nextQuery.assignee = resolvedAssigneeFilter;
  }

  if (currentFilter === nextQuery.filter && currentAssignee === nextQuery.assignee) {
    return;
  }

  await router.replace({ query: nextQuery });
  } finally {
    isSyncingFiltersToRoute.value = false;
  }
};

const resetArchivedToastPagination = () => {
  vetoedVisibleCount.value = 20;
  resolvedVisibleCount.value = 20;
};

const disconnectArchivedToastObserver = () => {
  if (!archivedToastObserver) {
    return;
  }

  archivedToastObserver.disconnect();
  archivedToastObserver = null;
};

const syncArchivedToastObserver = async () => {
  await nextTick();
  disconnectArchivedToastObserver();

  if (currentToastFilter.value === 'discarded' && hasMoreVetoedItems.value && vetoedInfiniteLoader.value) {
    archivedToastObserver = new IntersectionObserver((entries) => {
      if (!entries.some((entry) => entry.isIntersecting)) {
        return;
      }

      vetoedVisibleCount.value += 20;
    }, { rootMargin: '0px 0px 200px 0px' });
    archivedToastObserver.observe(vetoedInfiniteLoader.value);
    return;
  }

  if (currentToastFilter.value === 'resolved' && hasMoreResolvedItems.value && resolvedInfiniteLoader.value) {
    archivedToastObserver = new IntersectionObserver((entries) => {
      if (!entries.some((entry) => entry.isIntersecting)) {
        return;
      }

      resolvedVisibleCount.value += 20;
    }, { rootMargin: '0px 0px 200px 0px' });
    archivedToastObserver.observe(resolvedInfiniteLoader.value);
  }
};

const matchesAssignmentFilter = (item) => {
  if (!currentAssigneeFilter.value) {
    return true;
  }

  return String(item.owner?.id ?? '') === currentAssigneeFilter.value;
};
const displayedPendingAgendaItems = computed(() => (
  agendaItems.value.filter((item) => item.status !== 'ready').filter(matchesAssignmentFilter)
));
const displayedReadyAgendaItems = computed(() => (
  agendaItems.value.filter((item) => item.status === 'ready').filter(matchesAssignmentFilter)
));
const displayedAgendaItems = computed(() => (
  currentToastFilter.value === 'ready' ? displayedReadyAgendaItems.value : displayedPendingAgendaItems.value
));
const displayedVetoedItems = computed(() => vetoedItems.value.filter(matchesAssignmentFilter));
const displayedResolvedItems = computed(() => resolvedItems.value.filter(matchesAssignmentFilter));
const visibleVetoedItems = computed(() => displayedVetoedItems.value.slice(0, vetoedVisibleCount.value));
const visibleResolvedItems = computed(() => displayedResolvedItems.value.slice(0, resolvedVisibleCount.value));
const hasMoreVetoedItems = computed(() => visibleVetoedItems.value.length < displayedVetoedItems.value.length);
const hasMoreResolvedItems = computed(() => visibleResolvedItems.value.length < displayedResolvedItems.value.length);
const toastLookup = computed(() => Object.fromEntries(
  [
    ...agendaItems.value,
    ...vetoedItems.value,
    ...resolvedItems.value,
  ].map((item) => [item.id, item.title]),
));
const selectedToastModal = computed(() => {
  if (!selectedToastModalId.value) return null;

  return [
    ...agendaItems.value,
    ...vetoedItems.value,
    ...resolvedItems.value,
  ].find((item) => item.id === selectedToastModalId.value) ?? null;
});
const isToastingMode = computed(() => workspace.value?.meetingMode === 'live');
const newToastCount = computed(() => agendaItems.value.length);
const toastedToastCount = computed(() => resolvedItems.value.length);
const memberCount = computed(() => members.value.length);
const ownerCount = computed(() => members.value.filter((membership) => membership.isOwner).length);
const isInboxWorkspace = computed(() => workspace.value?.isInboxWorkspace === true);
const workspaceUrl = computed(() => {
  if (!workspace.value) {
    return props.dashboardUrl;
  }

  return isInboxWorkspace.value ? '/app/inbox' : `/app/workspaces/${workspace.value.id}`;
});

const sanitizeToastReturnToPath = (candidate) => {
  if (typeof candidate !== 'string' || !candidate.startsWith('/app')) {
    return null;
  }

  if (candidate.startsWith('/app/toasts/')) {
    return null;
  }

  return candidate;
};

const readStoredToastReturnToPath = () => {
  try {
    return sanitizeToastReturnToPath(window.sessionStorage.getItem(TOAST_RETURN_TO_STORAGE_KEY) ?? '');
  } catch {
    return null;
  }
};

const writeStoredToastReturnToPath = (path) => {
  const normalizedPath = sanitizeToastReturnToPath(path);
  if (!normalizedPath) {
    return;
  }

  try {
    window.sessionStorage.setItem(TOAST_RETURN_TO_STORAGE_KEY, normalizedPath);
  } catch {
    // Ignore session storage failures.
  }
};

const resolveToastReturnToPath = () => {
  const routeQueryReturnTo = Array.isArray(route.query.returnTo) ? route.query.returnTo[0] : route.query.returnTo;
  const fromQuery = sanitizeToastReturnToPath(routeQueryReturnTo);
  if (fromQuery) {
    return fromQuery;
  }

  const fromStorage = readStoredToastReturnToPath();
  if (fromStorage) {
    return fromStorage;
  }

  if (['dashboard', 'workspace', 'workspace-create-toast', 'inbox', 'inbox-create-toast'].includes(String(route.name ?? ''))) {
    return sanitizeToastReturnToPath(route.fullPath) ?? workspaceUrl.value;
  }

  return workspaceUrl.value;
};

const openToastWithReturnTo = (toastId, returnToOverride = null) => {
  const explicitReturnTo = sanitizeToastReturnToPath(returnToOverride);
  const currentRouteName = String(route.name ?? '');
  const currentRouteReturnTo = sanitizeToastReturnToPath(route.fullPath);
  const returnTo = explicitReturnTo
    ?? (currentRouteName === 'toast' ? resolveToastReturnToPath() : (currentRouteReturnTo ?? resolveToastReturnToPath()));

  writeStoredToastReturnToPath(returnTo);

  router.push({
    path: `/app/toasts/${toastId}`,
    query: returnTo ? { returnTo } : {},
  });
};

const syncToastReturnToFromRoute = () => {
  if (String(route.name ?? '') !== 'toast') {
    return;
  }

  const routeQueryReturnTo = Array.isArray(route.query.returnTo) ? route.query.returnTo[0] : route.query.returnTo;
  const fromQuery = sanitizeToastReturnToPath(routeQueryReturnTo);
  if (fromQuery) {
    writeStoredToastReturnToPath(fromQuery);
  }
};

const closeMobileStandaloneToast = () => {
  router.push(resolveToastReturnToPath());
};
const isSoloWorkspace = computed(() => workspace.value?.isSoloWorkspace === true);
const resolvedWorkspaceBackgroundUrl = computed(() => workspaceBackgroundObjectUrl.value || workspace.value?.permalinkBackgroundUrl || '');

const workspaceHeaderBackgroundStyle = computed(() => {
  const backgroundUrl = resolvedWorkspaceBackgroundUrl.value;

  if (!backgroundUrl) {
    return {};
  }

  return {
    backgroundImage: `linear-gradient(rgba(19, 36, 68, 0.44), rgba(32, 74, 135, 0.14), rgba(17, 24, 39, 0.48)), url("${backgroundUrl}")`,
    backgroundSize: 'cover',
    backgroundPosition: 'center',
  };
});

const workspaceHeaderStats = computed(() => [
  {
    label: isMobileViewport.value
      ? String(newToastCount.value)
      : `${newToastCount.value} new toast${newToastCount.value > 1 ? 's' : ''}`,
    icon: 'fa-solid fa-check',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700',
  },
  {
    label: isMobileViewport.value
      ? String(toastedToastCount.value)
      : `${toastedToastCount.value} toasted toast${toastedToastCount.value > 1 ? 's' : ''}`,
    icon: 'fa-regular fa-calendar-check',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700',
  },
  {
    label: isMobileViewport.value
      ? String(isInboxWorkspace.value ? memberCount.value : memberCount.value)
      : (isInboxWorkspace.value ? 'inbox' : (isSoloWorkspace.value ? 'solo' : `${memberCount.value} member${memberCount.value > 1 ? 's' : ''}`)),
    icon: isSoloWorkspace.value ? 'fa-regular fa-user' : 'fa-solid fa-users',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700',
  },
  ...(!isMobileViewport.value && workspace.value?.isDefault ? [{
    label: 'Default workspace',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white uppercase tracking-[0.18em] text-xs font-semibold' : 'bg-amber-100 text-amber-700 uppercase tracking-[0.18em] text-xs font-semibold',
  }] : []),
]);

const workspaceHeaderActions = computed(() => {
  if (!workspace.value) {
    return [];
  }

  if (isMobileViewport.value) {
    return [];
  }

  if (isInboxWorkspace.value) {
    return [];
  }

  return [
    ...(!isSoloWorkspace.value && workspace.value.currentUserIsOwner ? [{
      id: 'toast-curation',
      icon: 'fa-solid fa-wand-sparkles',
      theme: 'secondary',
      iconOnly: true,
      srLabel: 'Open toast curation',
      className: 'tw-ai-rainbow-action',
    }] : []),
    ...(!isSoloWorkspace.value ? [{
      id: 'session-archive',
      icon: 'fa-solid fa-clock-rotate-left',
      theme: 'secondary',
      iconOnly: true,
      srLabel: 'Open toasting session history',
    }] : []),
    ...(workspace.value.currentUserIsOwner ? [
      {
        id: 'manage',
        icon: 'fa-solid fa-gear',
        theme: 'secondary',
        iconOnly: true,
        srLabel: 'Manage workspace',
      },
      ...(!isSoloWorkspace.value ? [{
        id: workspace.value.meetingMode === 'live' ? 'stop-meeting' : 'start-meeting',
        icon: workspace.value.meetingMode === 'live' ? '' : 'fa-solid fa-bolt',
        label: workspace.value.meetingMode === 'live' ? 'Stop toasting mode' : 'Start toasting mode',
        theme: workspace.value.meetingMode === 'live' ? 'secondary' : 'primary',
        disabled: isSaving.value,
        blinking: workspace.value.meetingMode === 'live' && isSaving.value,
      }] : []),
    ] : []),
  ];
});

const handleWorkspaceHeaderAction = (actionId) => {
  if (actionId === 'toast-curation') {
    openToastCuration();
    return;
  }

  if (actionId === 'session-archive') {
    openSessionArchive();
    return;
  }

  if (actionId === 'manage') {
    openManageModal();
    return;
  }

  if (actionId === 'start-meeting') {
    startMeetingMode();
    return;
  }

  if (actionId === 'stop-meeting') {
    stopMeetingMode();
  }
};
const duePresetOptions = [
  { value: 'tomorrow', label: 'Tomorrow' },
  { value: 'next_week', label: 'Next week' },
  { value: 'in_2_weeks', label: 'In 2 weeks' },
  { value: 'next_monday', label: 'Next Monday' },
  { value: 'first_monday_next_month', label: 'First Monday next month' },
];
const displayToastStatus = (item) => {
  if (item.status === 'discarded') return 'Declined';
  if (item.status === 'toasted') return 'Toasted';
  if (item.status === 'ready') return 'Ready';
  return 'In progress';
};

const isActiveToast = (item) => item?.status === 'pending' || item?.status === 'ready';

const toastStatusTone = (item) => {
  if (item.status === 'discarded') {
    return 'text-stone-400';
  }

  if (item.status === 'toasted') {
    return 'text-amber-700';
  }

  if (item.status === 'ready') {
    return 'text-emerald-700';
  }

  return 'text-amber-600';
};

const workspaceMobileItemDateStatus = (item) => {
  const dueOn = typeof item?.dueOn === 'string' ? item.dueOn : '';
  if (!dueOn) {
    return 'on-track';
  }

  const dueAt = new Date(`${dueOn}T00:00:00`);
  if (Number.isNaN(dueAt.getTime())) {
    return 'on-track';
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

const workspaceMobileItemBorderStyle = (item) => {
  const dateStatus = workspaceMobileItemDateStatus(item);
  let borderLeftColor = 'transparent';

  if (item?.status === 'ready') {
    borderLeftColor = 'rgb(16 185 129)';
  } else if (item?.isBoosted) {
    borderLeftColor = 'rgb(148 163 184)';
  } else if (dateStatus === 'late') {
    borderLeftColor = 'rgb(239 68 68)';
  } else if (dateStatus === 'due-soon') {
    borderLeftColor = 'rgb(250 204 21)';
  }

  return {
    borderLeftWidth: '5px',
    borderLeftStyle: 'solid',
    borderLeftColor,
  };
};


const resetItemForm = () => {
  itemForm.value = {
    title: '',
    description: '',
    ownerId: currentUser.value?.id ? String(currentUser.value.id) : '',
    dueOn: defaultDueDateForPreset(workspace.value?.defaultDuePreset ?? 'next_week'),
  };
};

const openTopActiveToast = () => {
  const firstItem = agendaItems.value[0];

  if (!firstItem) {
    selectedToastModalId.value = null;
    return;
  }

  openToastModal(firstItem);
};

const openNextActiveToastAtIndex = (index) => {
  const nextItem = agendaItems.value[index] ?? null;

  if (!nextItem) {
    selectedToastModalId.value = null;
    return;
  }

  openToastModal(nextItem);
};

const fetchWorkspace = async () => {
  isLoading.value = true;
  errorMessage.value = '';
  sessionArchiveError.value = '';
  sessionArchiveNotice.value = '';
  toastCurationError.value = '';

  const { ok, data } = await workspacesApi.getWorkspace(props.apiUrl);

  if (!ok || !data) {
    payload.value = null;
    errorMessage.value = 'Unable to load workspace.';
    isLoading.value = false;
    return;
  }

  payload.value = data;
  workspaceSettingsForm.value = {
    name: payload.value.workspace?.name ?? '',
    defaultDuePreset: payload.value.workspace?.defaultDuePreset ?? 'next_week',
    isSoloWorkspace: payload.value.workspace?.isSoloWorkspace ?? false,
  };
  selectedTargetWorkspaceId.value = payload.value.otherWorkspaces?.[0]?.id ? String(payload.value.otherWorkspaces[0].id) : '';
  const preferredToastId = Number(props.standaloneToastId ?? payload.value.selectedToastId ?? selectedToastModalId.value ?? 0);
  if (preferredToastId) {
    selectedToastModalId.value = preferredToastId;
  }
  resetItemForm();
  isLoading.value = false;
};

const revokeWorkspaceBackgroundObjectUrl = () => {
  if (workspaceBackgroundObjectUrl.value.startsWith('blob:')) {
    URL.revokeObjectURL(workspaceBackgroundObjectUrl.value);
  }

  workspaceBackgroundObjectUrl.value = '';
};

const loadWorkspaceBackground = async () => {
  revokeWorkspaceBackgroundObjectUrl();

  const backgroundUrl = workspace.value?.permalinkBackgroundUrl;

  if (!backgroundUrl) {
    return;
  }

  if (backgroundUrl.startsWith('http://') || backgroundUrl.startsWith('https://')) {
    workspaceBackgroundObjectUrl.value = backgroundUrl;
    return;
  }

  const { ok, blob } = await workspacesApi.getWorkspaceBackground(backgroundUrl);

  if (!ok || !blob) {
    return;
  }

  workspaceBackgroundObjectUrl.value = URL.createObjectURL(blob);
};

const inviteMember = async () => {
  if (!payload.value || !inviteEmail.value.trim()) return;
  await workspacesApi.inviteMember(workspace.value.id, inviteEmail.value);
  inviteEmail.value = '';
  await fetchWorkspace();
};

const removeMember = async (memberId) => {
  if (!workspace.value) return;
  await workspacesApi.removeMember(workspace.value.id, memberId);
  await fetchWorkspace();
};

const buildFallbackToastTitle = (description) => {
  const normalized = String(description ?? '')
    .replace(/\s+/g, ' ')
    .trim();

  if (!normalized) {
    return '';
  }

  if (normalized.length <= 72) {
    return normalized;
  }

  return `${normalized.slice(0, 69)}...`;
};

const refineCreatedToastInBackground = async (workspaceId, toastId, draftSnapshot) => {
  const snapshotTitle = String(draftSnapshot?.title ?? '').trim();
  const snapshotDescription = String(draftSnapshot?.description ?? '').trim();
  if (!workspaceId || !toastId || (!snapshotTitle && !snapshotDescription)) {
    return;
  }

  const { ok, data } = await workspacesApi.refineToastDraft(workspaceId, {
    title: snapshotTitle,
    description: snapshotDescription,
  });

  if (!ok || !data?.ok || !data?.draft) {
    return;
  }

  const refinedTitle = String(data.draft.title ?? '').trim();
  const refinedDescription = String(data.draft.description ?? '').trim();
  if (!refinedTitle) {
    return;
  }

  const updatePayload = {
    title: refinedTitle,
    description: refinedDescription || snapshotDescription,
    ownerId: data.draft.ownerId ? String(data.draft.ownerId) : '',
    dueOn: data.draft.dueOn ?? '',
  };

  await workspacesApi.updateToast(toastId, updatePayload);
};

const createItem = async () => {
  if (!workspace.value) return;

  const isEditingToast = !!editingToastId.value;
  let shouldRefineAfterCreateInBackground = false;
  let draftSnapshotForBackgroundRefine = null;
  if (!isEditingToast && isMobileViewport.value && !itemForm.value.title.trim() && itemForm.value.description.trim()) {
    shouldRefineAfterCreateInBackground = true;
    draftSnapshotForBackgroundRefine = {
      title: itemForm.value.title ?? '',
      description: itemForm.value.description ?? '',
      ownerId: itemForm.value.ownerId ?? '',
      dueOn: itemForm.value.dueOn ?? '',
    };

    itemForm.value = {
      ...itemForm.value,
      title: buildFallbackToastTitle(itemForm.value.description),
    };
  }

  if (!itemForm.value.title.trim()) return;
  const { ok, data } = isEditingToast
    ? await workspacesApi.updateToast(editingToastId.value, itemForm.value)
    : await workspacesApi.createToast(workspace.value.id, itemForm.value);

  if (!ok) {
    errorMessage.value = editingToastId.value ? 'Unable to update toast.' : 'Unable to create toast.';
    return;
  }

  resetItemForm();
  isCreateToastModalOpen.value = false;
  editingToastId.value = null;
  const createdItemId = Number(data?.itemId ?? 0);

  if (shouldRefineAfterCreateInBackground && Number.isFinite(createdItemId) && createdItemId > 0 && draftSnapshotForBackgroundRefine) {
    void refineCreatedToastInBackground(workspace.value.id, createdItemId, draftSnapshotForBackgroundRefine);
  }

  if (props.createOnlyMode && !isEditingToast) {
    if (Number.isFinite(createdItemId) && createdItemId > 0) {
      openToastWithReturnTo(createdItemId, workspaceUrl.value);
      return;
    }

    router.push(workspaceUrl.value);
    return;
  }

  await fetchWorkspace();
};

const refineToastDraft = async (draftSnapshot = null, options = {}) => {
  if (!workspace.value || isToastDraftRefining.value) {
    return false;
  }

  const forceApply = options?.forceApply === true;
  const requestId = ++toastDraftRefinementRequestId;
  isToastDraftRefining.value = true;
  errorMessage.value = '';

  if (draftSnapshot && typeof draftSnapshot === 'object') {
    itemForm.value = {
      ...itemForm.value,
      title: typeof draftSnapshot.title === 'string' ? draftSnapshot.title : itemForm.value.title,
      description: typeof draftSnapshot.description === 'string' ? draftSnapshot.description : itemForm.value.description,
      ownerId: typeof draftSnapshot.ownerId === 'string' ? draftSnapshot.ownerId : itemForm.value.ownerId,
      dueOn: typeof draftSnapshot.dueOn === 'string' ? draftSnapshot.dueOn : itemForm.value.dueOn,
    };
  }

  const previousDraft = {
    title: itemForm.value.title ?? '',
    description: itemForm.value.description ?? '',
    ownerId: itemForm.value.ownerId ?? '',
    dueOn: itemForm.value.dueOn ?? '',
  };

  let timeoutHandle = null;
  const timeoutResultPromise = new Promise((resolve) => {
    timeoutHandle = window.setTimeout(() => {
      resolve({
        ok: false,
        data: { message: 'AI rewrite timed out after 30 seconds. Please try again.' },
      });
    }, TOAST_DRAFT_REFINE_TIMEOUT_MS);
  });

  const { ok, data } = await Promise.race([
    workspacesApi.refineToastDraft(workspace.value.id, {
      title: previousDraft.title,
      description: previousDraft.description,
    }),
    timeoutResultPromise,
  ]);

  if (timeoutHandle !== null) {
    window.clearTimeout(timeoutHandle);
  }

  if (requestId !== toastDraftRefinementRequestId) {
    isToastDraftRefining.value = false;
    return false;
  }

  if (!ok || !data?.ok || !data.draft) {
    errorMessage.value = data?.message ?? 'Unable to improve this toast draft.';
    isToastDraftRefining.value = false;
    return false;
  }

  const draftWasEditedSinceRequest = (
    (itemForm.value.title ?? '') !== previousDraft.title
    || (itemForm.value.description ?? '') !== previousDraft.description
    || (itemForm.value.ownerId ?? '') !== previousDraft.ownerId
    || (itemForm.value.dueOn ?? '') !== previousDraft.dueOn
  );
  if (draftWasEditedSinceRequest && !forceApply) {
    isToastDraftRefining.value = false;
    return true;
  }

  toastDraftRefinementBackup.value = previousDraft;
  itemForm.value = {
    ...itemForm.value,
    title: data.draft.title ?? previousDraft.title,
    description: data.draft.description ?? previousDraft.description,
    ownerId: data.draft.ownerId ? String(data.draft.ownerId) : '',
    dueOn: data.draft.dueOn ?? '',
  };
  isToastDraftRefining.value = false;
  return true;
};

const undoToastDraftRefinement = () => {
  if (!toastDraftRefinementBackup.value) {
    return;
  }

  itemForm.value = {
    ...itemForm.value,
    title: toastDraftRefinementBackup.value.title,
    description: toastDraftRefinementBackup.value.description,
    ownerId: toastDraftRefinementBackup.value.ownerId,
    dueOn: toastDraftRefinementBackup.value.dueOn,
  };
  toastDraftRefinementBackup.value = null;
};

const handleCreateToastModalKeydown = (event) => {
  if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
    event.preventDefault();
    createItem();
  }
};

const startMeetingMode = async () => {
  if (!workspace.value?.currentUserIsOwner) return;
  isSaving.value = true;
  await workspacesApi.startMeetingMode(workspace.value.id);
  await fetchWorkspace();
  openTopActiveToast();
  isSaving.value = false;
};

const stopMeetingMode = async () => {
  if (!workspace.value?.currentUserIsOwner) return;
  isSaving.value = true;
  const { ok, data } = await workspacesApi.stopMeetingMode(workspace.value.id);
  if (!ok) {
    errorMessage.value = 'Unable to stop toasting mode.';
    isSaving.value = false;
    return;
  }

  await fetchWorkspace();
  if (data?.sessionId) {
    selectedSessionArchiveId.value = data.sessionId;
  }
  if (data?.summaryError?.message) {
    sessionArchiveError.value = data.summaryError.message;
  }
  openSessionArchive();
  isSaving.value = false;
};

const openToastCuration = () => {
  isToastCurationOpen.value = true;
  if (!toastCurationDraft.value && !isToastCurationGenerating.value) {
    generateToastCurationDraft();
  }
};

const closeToastCuration = () => {
  isToastCurationOpen.value = false;
};

const generateToastCurationDraft = async () => {
  if (!workspace.value) {
    return;
  }

  isToastCurationGenerating.value = true;
  toastCurationError.value = '';
  toastCurationNotice.value = '';
  toastCurationActionStatuses.value = {};
  toastCurationApplyingIndex.value = -1;

  const { ok, data } = await workspacesApi.generateToastCurationDraft(workspace.value.id);

  if (!ok || !data?.ok || !data.draft) {
    toastCurationDraft.value = null;
    toastCurationError.value = data?.message ?? 'Unable to generate a curation draft.';
    isToastCurationGenerating.value = false;
    return;
  }

  toastCurationDraft.value = data.draft;
  isToastCurationGenerating.value = false;
};

const applyToastCurationAction = async ({ action, index }) => {
  if (!workspace.value || !action) {
    return;
  }

  toastCurationApplyingIndex.value = index;
  toastCurationError.value = '';
  toastCurationNotice.value = '';

  const { ok, data } = await workspacesApi.applyToastCurationDraft(workspace.value.id, [action]);

  if (!ok || !data?.ok || !data.result) {
    toastCurationError.value = data?.message ?? 'Unable to apply the curation draft.';
    toastCurationApplyingIndex.value = -1;
    return;
  }

  const appliedCount = data.result.applied?.length ?? 0;
  const skippedCount = data.result.skipped?.length ?? 0;
  toastCurationActionStatuses.value = {
    ...toastCurationActionStatuses.value,
    [index]: appliedCount > 0 ? 'applied' : 'skipped',
  };
  toastCurationNotice.value = appliedCount > 0
    ? 'Action applied.'
    : (skippedCount > 0 ? 'Action skipped.' : 'No action applied.');
  await fetchWorkspace();
  toastCurationApplyingIndex.value = -1;
};

const saveDecisionNotes = async () => {
  if (!selectedToastModal.value) return;

  isSaving.value = true;
  errorMessage.value = '';

  const { ok } = await workspacesApi.saveDecisionNotes(selectedToastModal.value.id, {
    discussionNotes: selectedToastModal.value.discussionNotes,
    ownerId: selectedToastModal.value.owner?.id ?? null,
    dueOn: selectedToastModal.value.dueOn,
  });

  if (!ok) {
    errorMessage.value = 'Unable to save decision notes.';
    isSaving.value = false;
    return;
  }

  const currentToastId = selectedToastModal.value.id;
  await fetchWorkspace();
  selectedToastModalId.value = currentToastId;
  if (selectedToastModal.value) {
    selectedToastModalCleanState.value = serializeToastModalState(selectedToastModal.value);
  }
  isSaving.value = false;
};

const generateExecutionPlan = async () => {
  if (!selectedToastModal.value) {
    return;
  }

  isExecutionPlanGenerating.value = true;
  executionPlanError.value = '';
  executionPlanNotice.value = '';
  executionPlanActionStatuses.value = {};
  executionPlanApplyingIndex.value = -1;

  const { ok, data } = await workspacesApi.generateExecutionPlan(selectedToastModal.value.id);

  if (!ok || !data?.ok || !data.draft) {
    executionPlanDraft.value = null;
    executionPlanError.value = data?.message ?? 'Unable to generate the execution plan.';
    isExecutionPlanGenerating.value = false;
    return;
  }

  executionPlanDraft.value = data.draft;
  isExecutionPlanGenerating.value = false;
};

const applyExecutionPlanAction = async ({ action, index }) => {
  if (!selectedToastModal.value || !action) {
    return;
  }

  executionPlanApplyingIndex.value = index;
  executionPlanError.value = '';
  executionPlanNotice.value = '';

  const { ok, data } = await workspacesApi.applyExecutionPlanAction(selectedToastModal.value.id, action);

  if (!ok || !data?.ok || !data.result) {
    executionPlanError.value = data?.message ?? 'Unable to apply this execution plan item.';
    executionPlanApplyingIndex.value = -1;
    return;
  }

  executionPlanActionStatuses.value = {
    ...executionPlanActionStatuses.value,
    [index]: (data.result.applied?.length ?? 0) > 0 ? 'applied' : 'skipped',
  };
  executionPlanNotice.value = (data.result.applied?.length ?? 0) > 0 ? 'Execution item applied.' : 'Execution item skipped.';
  await fetchWorkspace();
  executionPlanApplyingIndex.value = -1;
};

const openSessionArchive = () => {
  isSessionArchiveOpen.value = true;
  if (!selectedSessionArchiveId.value) {
    selectedSessionArchiveId.value = toastingSessions.value[0]?.id ?? null;
  }
};

const closeSessionArchive = () => {
  isSessionArchiveOpen.value = false;
};

const selectSessionArchive = (sessionId) => {
  selectedSessionArchiveId.value = sessionId;
};

const generateSessionArchiveSummary = async (sessionId) => {
  if (!workspace.value || !sessionId) {
    return;
  }

  isSessionArchiveGenerating.value = true;
  sessionArchiveError.value = '';
  sessionArchiveNotice.value = '';

  const { ok, data } = await workspacesApi.generateSessionSummary(workspace.value.id, sessionId);

  if (!ok || !data?.ok || !data.summary) {
    sessionArchiveError.value = data?.message ?? 'Unable to generate the session recap.';
    isSessionArchiveGenerating.value = false;
    return;
  }

  await fetchWorkspace();
  selectedSessionArchiveId.value = sessionId;
  isSessionArchiveGenerating.value = false;
};

const saveSessionArchiveSummary = async ({ sessionId, summary }) => {
  if (!workspace.value || !sessionId) {
    return;
  }

  isSessionArchiveSaving.value = true;
  sessionArchiveError.value = '';
  sessionArchiveNotice.value = '';

  const { ok, data } = await workspacesApi.updateSessionSummary(workspace.value.id, sessionId, summary);

  if (!ok || !data?.ok || !data.summary) {
    sessionArchiveError.value = data?.message ?? 'Unable to save the session recap.';
    isSessionArchiveSaving.value = false;
    return;
  }

  await fetchWorkspace();
  selectedSessionArchiveId.value = sessionId;
  isSessionArchiveSaving.value = false;
};

const sendSessionArchiveSummary = async (sessionId) => {
  if (!workspace.value || !sessionId) {
    return;
  }

  isSessionArchiveSending.value = true;
  sessionArchiveError.value = '';
  sessionArchiveNotice.value = '';

  const { ok, data } = await workspacesApi.sendSessionSummary(workspace.value.id, sessionId);

  if (!ok || !data?.ok) {
    sessionArchiveError.value = data?.message ?? 'Unable to send the session recap by email.';
    isSessionArchiveSending.value = false;
    return;
  }

  sessionArchiveNotice.value = `Recap sent by email to ${data.recipientCount} participant${data.recipientCount > 1 ? 's' : ''}.`;
  isSessionArchiveSending.value = false;
};

const openManageModal = () => {
  isManageModalOpen.value = true;
};

const closeManageModal = () => {
  isManageModalOpen.value = false;
};

const openDeleteWorkspaceConfirm = () => {
  isDeleteWorkspaceConfirmOpen.value = true;
};

const closeDeleteWorkspaceConfirm = () => {
  isDeleteWorkspaceConfirmOpen.value = false;
};

const deleteWorkspace = async () => {
  if (!workspace.value) {
    return;
  }

  isDeletingWorkspace.value = true;
  const { ok } = await workspacesApi.deleteWorkspace(workspace.value.id);
  isDeletingWorkspace.value = false;

  if (!ok) {
    errorMessage.value = 'Unable to delete workspace.';
    return;
  }

  closeDeleteWorkspaceConfirm();
  window.location.href = props.dashboardUrl;
};

const openCreateToastModal = async () => {
  editingToastId.value = null;
  resetItemForm();
  isCreateToastModalOpen.value = true;
  await nextTick();
  await createToastModalRef.value?.focusTitle?.();
};

const closeCreateToastModal = () => {
  toastDraftRefinementRequestId += 1;
  isCreateToastModalOpen.value = false;
  editingToastId.value = null;
  isToastDraftRefining.value = false;
  toastDraftRefinementBackup.value = null;
  resetItemForm();

  if (props.createOnlyMode) {
    router.push(workspaceUrl.value);
  }
};

const hasCreateToastRouteIntent = () => {
  const createIntent = typeof route.query.create === 'string' ? route.query.create.toLowerCase() : '';
  return ['1', 'true', 'on'].includes(createIntent);
};

const consumeCreateToastRouteIntent = async () => {
  if (!workspace.value || !hasCreateToastRouteIntent() || isCreateToastModalOpen.value) {
    return;
  }

  await openCreateToastModal();

  const nextQuery = { ...route.query };
  delete nextQuery.create;
  await router.replace({ query: nextQuery });
};

const handleExternalCreateToastRequest = () => {
  if (!workspace.value || isCreateToastModalOpen.value) {
    return;
  }

  openCreateToastModal();
};

const syncCreateOnlyMode = async () => {
  if (!props.createOnlyMode || !workspace.value || isCreateToastModalOpen.value) {
    return;
  }

  await openCreateToastModal();
};

const openEditToastModal = async (item) => {
  selectedToastModalId.value = null;
  selectedToastModalCleanState.value = null;
  toastModalNavigationBlocked.value = false;
  editingToastId.value = item.id;
  itemForm.value = {
    title: item.title ?? '',
    description: item.description ?? '',
    ownerId: item.owner?.id ? String(item.owner.id) : '',
    dueOn: item.dueOn ?? '',
  };
  toastDraftRefinementBackup.value = null;
  isCreateToastModalOpen.value = true;
  await nextTick();
  await createToastModalRef.value?.focusTitle?.();
};

const openMoveCopyToastModal = () => {
  if (!selectedToastModal.value) {
    return;
  }

  selectedTargetWorkspaceId.value = otherWorkspaces.value[0]?.id ? String(otherWorkspaces.value[0].id) : '';
  isMoveCopyToastModalOpen.value = true;
};

const closeMoveCopyToastModal = () => {
  isMoveCopyToastModalOpen.value = false;
};

const normalizeDraftFollowUps = (item) => ensureDraftFollowUps(item)
  .map((followUp) => ({
    title: (followUp.title ?? '').trim(),
    ownerId: followUp.ownerId ? String(followUp.ownerId) : '',
    dueOn: followUp.dueOn ?? '',
  }))
  .filter((followUp) => followUp.title !== '' || followUp.ownerId !== '' || followUp.dueOn !== '');

const serializeToastModalState = (item) => JSON.stringify({
  discussionNotes: item?.discussionNotes ?? '',
  draftFollowUps: normalizeDraftFollowUps(item),
  commentDraft: item?.id ? commentDraftFor(item.id).trim() : '',
});

const isSelectedToastModalDirty = computed(() => {
  if (!selectedToastModal.value || !isToastingMode.value) {
    return false;
  }

  return serializeToastModalState(selectedToastModal.value) !== selectedToastModalCleanState.value;
});

const selectedToastModalInitialState = computed(() => {
  if (!selectedToastModalCleanState.value) {
    return { discussionNotes: '', draftFollowUps: [], commentDraft: '' };
  }

  return JSON.parse(selectedToastModalCleanState.value);
});

const isDecisionNotesDirty = computed(() => {
  if (!selectedToastModal.value || !isToastingMode.value) {
    return false;
  }

  return (selectedToastModal.value.discussionNotes ?? '') !== selectedToastModalInitialState.value.discussionNotes;
});

const isFollowUpsDirty = computed(() => {
  if (!selectedToastModal.value || !isToastingMode.value) {
    return false;
  }

  return JSON.stringify(normalizeDraftFollowUps(selectedToastModal.value)) !== JSON.stringify(selectedToastModalInitialState.value.draftFollowUps ?? []);
});

const isCommentDraftDirty = computed(() => {
  if (!selectedToastModal.value || !isToastingMode.value) {
    return false;
  }

  return commentDraftFor(selectedToastModal.value.id).trim() !== (selectedToastModalInitialState.value.commentDraft ?? '');
});

const triggerToastModalNavigationBlockedFeedback = () => {
  toastModalNavigationBlocked.value = true;
  window.clearTimeout(triggerToastModalNavigationBlockedFeedback.timeoutId);
  triggerToastModalNavigationBlockedFeedback.timeoutId = window.setTimeout(() => {
    toastModalNavigationBlocked.value = false;
  }, 900);
};

triggerToastModalNavigationBlockedFeedback.timeoutId = 0;

const openToastModal = (item) => {
  selectedToastModalId.value = item.id;
  selectedTargetWorkspaceId.value = otherWorkspaces.value[0]?.id ? String(otherWorkspaces.value[0].id) : '';
  selectedToastModalCleanState.value = serializeToastModalState(item);
  toastModalNavigationBlocked.value = false;

  if (standaloneMode.value) {
    openToastWithReturnTo(item.id);
  }
};

const closeToastModal = () => {
  closeMoveCopyToastModal();
  closeMobileCommentModal();
  closeMobileVetoConfirmModal();

  if (standaloneMode.value) {
    router.push(resolveToastReturnToPath());
    return;
  }

  selectedToastModalId.value = null;
  selectedToastModalCleanState.value = null;
  toastModalNavigationBlocked.value = false;
};

const relatedToastStatusLabel = (item) => {
  if (!item) return '';
  if (item.status === 'discarded') return 'Declined';
  if (item.status === 'toasted') return 'Toasted';
  if (item.status === 'ready') return 'Ready';
  return 'New';
};

const openToastById = (toastId) => {
  const item = [
    ...agendaItems.value,
    ...vetoedItems.value,
    ...resolvedItems.value,
  ].find((candidate) => candidate.id === toastId);

  if (item) {
    if (standaloneMode.value) {
      openToastWithReturnTo(toastId);
      selectedToastModalId.value = toastId;
      selectedToastModalCleanState.value = serializeToastModalState(item);
      toastModalNavigationBlocked.value = false;
      return;
    }

    openToastModal(item);
  }
};

const openToastPermalink = (toastId) => {
  openToastWithReturnTo(toastId);
};

const currentToastSequence = () => {
  if (!selectedToastModal.value) {
    return [];
  }

  if (displayedAgendaItems.value.some((item) => item.id === selectedToastModal.value.id)) {
    return displayedAgendaItems.value;
  }

  if (displayedVetoedItems.value.some((item) => item.id === selectedToastModal.value.id)) {
    return displayedVetoedItems.value;
  }

  if (displayedResolvedItems.value.some((item) => item.id === selectedToastModal.value.id)) {
    return displayedResolvedItems.value;
  }

  return [];
};

const navigateSelectedToast = (direction) => {
  if (isSelectedToastModalDirty.value) {
    triggerToastModalNavigationBlockedFeedback();
    return;
  }

  const sequence = currentToastSequence();

  if (!sequence.length || !selectedToastModal.value) {
    return;
  }

  const currentIndex = sequence.findIndex((item) => item.id === selectedToastModal.value.id);

  if (currentIndex < 0) {
    return;
  }

  const nextToast = sequence[currentIndex + direction];

  if (!nextToast) {
    return;
  }

  openToastById(nextToast.id);
};

const canNavigateSelectedToast = (direction) => {
  const sequence = currentToastSequence();

  if (!sequence.length || !selectedToastModal.value) {
    return false;
  }

  const currentIndex = sequence.findIndex((item) => item.id === selectedToastModal.value.id);

  if (currentIndex < 0) {
    return false;
  }

  return !!sequence[currentIndex + direction] && !isSelectedToastModalDirty.value;
};

const commentDraftFor = (itemId) => commentDrafts.value[itemId] ?? '';

const autosizeTextarea = (element) => {
  if (!element) return;

  element.style.height = '0px';
  element.style.height = `${element.scrollHeight}px`;
};

const updateCommentDraft = (itemId, value) => {
  commentDrafts.value = {
    ...commentDrafts.value,
    [itemId]: value,
  };
};

const handleCommentDraftInput = (itemId, event) => {
  updateCommentDraft(itemId, event.target.value);
  autosizeTextarea(event.target);
};

const handleCommentDraftKeydown = (itemId, event) => {
  if ((event.metaKey || event.ctrlKey) && event.key === 'Enter') {
    event.preventDefault();
    createComment(itemId);
  }
};

const createComment = async (itemId) => {
  const content = commentDraftFor(itemId).trim();

  if (!content) return false;

  const { ok } = await workspacesApi.addComment(itemId, content);

  if (!ok) {
    errorMessage.value = 'Unable to add comment.';
    return false;
  }

  updateCommentDraft(itemId, '');
  await fetchWorkspace();
  return true;
};

const openMobileCommentModal = () => {
  if (!selectedToastModal.value || !isActiveToast(selectedToastModal.value)) {
    return;
  }

  isMobileCommentModalOpen.value = true;
};

const closeMobileCommentModal = () => {
  isMobileCommentModalOpen.value = false;
};

const submitMobileComment = async () => {
  if (!selectedToastModal.value) {
    return;
  }

  const created = await createComment(selectedToastModal.value.id);
  if (created) {
    closeMobileCommentModal();
  }
};

const closeMobileVetoConfirmModal = () => {
  mobileVetoConfirmToastId.value = null;
};

const confirmMobileVeto = async () => {
  const toastId = Number(mobileVetoConfirmToastId.value);
  if (!Number.isFinite(toastId) || toastId <= 0) {
    closeMobileVetoConfirmModal();
    return;
  }

  closeMobileVetoConfirmModal();
  await toggleVeto(toastId);
};

const saveWorkspaceSettings = async () => {
  if (!workspace.value?.currentUserIsOwner) return;

  const { ok } = await workspacesApi.saveWorkspaceSettings(workspace.value.id, workspaceSettingsForm.value);

  if (!ok) {
    errorMessage.value = 'Unable to save workspace settings.';
    return;
  }

  if (workspaceBackgroundFile.value) {
    const formData = new FormData();
    formData.append('background', workspaceBackgroundFile.value);

    const { ok: uploadOk } = await workspacesApi.uploadWorkspaceBackground(workspace.value.id, formData);

    if (!uploadOk) {
      errorMessage.value = 'Unable to upload workspace background.';
      return;
    }

    workspaceBackgroundFile.value = null;
  }

  closeManageModal();
  await fetchWorkspace();
  await loadWorkspaceBackground();
};

const handleWorkspaceBackgroundChange = (event) => {
  workspaceBackgroundFile.value = event.target.files?.[0] ?? null;
};

const openWorkspaceBackgroundBrowse = () => {
  workspaceBackgroundInput.value?.click();
};

const handleWorkspaceBackgroundDrop = (event) => {
  isWorkspaceBackgroundDragOver.value = false;
  workspaceBackgroundFile.value = event.dataTransfer?.files?.[0] ?? null;
};

const promoteMember = async (memberId) => {
  if (!workspace.value?.currentUserIsOwner) return;
  const { ok } = await workspacesApi.promoteMember(workspace.value.id, memberId);

  if (!ok) {
    errorMessage.value = 'Unable to promote member.';
    return;
  }

  await fetchWorkspace();
};

const demoteMember = async (memberId) => {
  if (!workspace.value?.currentUserIsOwner) return;
  const { ok } = await workspacesApi.demoteMember(workspace.value.id, memberId);

  if (!ok) {
    errorMessage.value = 'Unable to demote owner.';
    return;
  }

  await fetchWorkspace();
};

const setVoteStateLocally = (itemId, voted, voteCount) => {
  const apply = (items) => {
    const target = items.find((candidate) => candidate.id === itemId);
    if (!target) {
      return;
    }

    target.currentUserHasVoted = voted;
    target.voteCount = voteCount;
  };

  apply(agendaItems.value);
  apply(vetoedItems.value);
  apply(resolvedItems.value);
};

const toggleVote = async (itemId) => {
  if (isToastingMode.value) return;

  const currentItem = [
    ...agendaItems.value,
    ...vetoedItems.value,
    ...resolvedItems.value,
  ].find((candidate) => candidate.id === itemId);

  if (!currentItem) {
    const response = await workspacesApi.toggleVote(itemId);
    if (!response.ok) {
      errorMessage.value = 'Unable to update vote.';
    }
    return;
  }

  const previousVoted = !!currentItem.currentUserHasVoted;
  const previousCount = Number(currentItem.voteCount ?? 0);
  const nextVoted = !previousVoted;
  const nextCount = Math.max(0, previousCount + (nextVoted ? 1 : -1));

  setVoteStateLocally(itemId, nextVoted, nextCount);

  const response = await workspacesApi.toggleVote(itemId);
  if (!response.ok) {
    setVoteStateLocally(itemId, previousVoted, previousCount);
    errorMessage.value = 'Unable to update vote.';
  }
};

const toggleBoost = async (itemId) => {
  await workspacesApi.toggleBoost(itemId);
  await fetchWorkspace();
};

const toggleVeto = async (itemId) => {
  await workspacesApi.toggleVeto(itemId);
  await fetchWorkspace();
};

const requestMobileToastVeto = async (itemId) => {
  const target = [
    ...agendaItems.value,
    ...vetoedItems.value,
    ...resolvedItems.value,
  ].find((candidate) => candidate.id === itemId);

  if (!target) {
    return;
  }

  if (target.status !== 'discarded') {
    mobileVetoConfirmToastId.value = target.id;
    return;
  }

  await toggleVeto(itemId);
};

const toastItem = async (itemId) => {
  if (!isSoloWorkspace.value) return;

  const { ok } = await workspacesApi.toastItem(itemId);

  if (!ok) {
    errorMessage.value = 'Unable to toast this item.';
    return;
  }

  if (standaloneMode.value && isMobileViewport.value) {
    router.push(resolveToastReturnToPath());
    return;
  }

  await fetchWorkspace();
};

const setReady = async (itemId, ready) => {
  const { ok } = await workspacesApi.setReady(itemId, ready);

  if (!ok) {
    errorMessage.value = ready ? 'Unable to mark this toast as ready.' : 'Unable to mark this toast as in progress.';
    return;
  }

  await fetchWorkspace();
};

const copyToast = async (targetWorkspaceId = null) => {
  if (!selectedToastModal.value) return;

  const { ok, data } = await workspacesApi.copyToast(selectedToastModal.value.id, targetWorkspaceId ?? null);

  if (!ok || !data) {
    errorMessage.value = 'Unable to copy toast.';
    return;
  }
  const result = data;
  closeMoveCopyToastModal();

  if (result.workspaceId === workspace.value?.id) {
    await fetchWorkspace();
    if (standaloneMode.value) {
      openToastWithReturnTo(result.toastId);
      return;
    }
    selectedToastModalId.value = result.toastId;
    return;
  }

  openToastWithReturnTo(result.toastId);
};

const transferToast = async () => {
  if (!selectedToastModal.value || !selectedTargetWorkspaceId.value) return;

  const { ok, data } = await workspacesApi.transferToast(selectedToastModal.value.id, Number(selectedTargetWorkspaceId.value));

  if (!ok || !data) {
    errorMessage.value = 'Unable to transfer toast.';
    return;
  }
  const result = data;
  closeMoveCopyToastModal();
  openToastWithReturnTo(result.toastId);
};

const copyInboxAddress = async () => {
  const address = String(currentUser.value?.inboxEmailAddress ?? '').trim();
  if (!address) {
    return;
  }

  const markCopied = () => {
    inboxAddressCopied.value = true;
    if (inboxAddressCopiedTimeout) {
      clearTimeout(inboxAddressCopiedTimeout);
    }
    inboxAddressCopiedTimeout = window.setTimeout(() => {
      inboxAddressCopied.value = false;
      inboxAddressCopiedTimeout = null;
    }, 3000);
  };

  if (navigator?.clipboard?.writeText) {
    try {
      await navigator.clipboard.writeText(address);
      markCopied();
      return;
    } catch {
      // Fallback below.
    }
  }

  try {
    const textarea = document.createElement('textarea');
    textarea.value = address;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'absolute';
    textarea.style.left = '-9999px';
    document.body.appendChild(textarea);
    textarea.select();
    const copied = document.execCommand('copy');
    document.body.removeChild(textarea);
    if (copied) {
      markCopied();
    }
  } catch {
    // Keep button unchanged on copy failure.
  }
};

const selectInboxAddress = () => {
  inboxAddressInput.value?.focus();
  inboxAddressInput.value?.select();
};

const updateItemField = (itemId, key, value) => {
  if (!payload.value) return;

  payload.value.agendaItems = payload.value.agendaItems.map((item) => (
    item.id === itemId ? { ...item, [key]: value } : item
  ));
};

const ensureDraftFollowUps = (item) => item.draftFollowUps?.length
  ? item.draftFollowUps
  : [];

const addFollowUpDraft = (itemId) => {
  const item = agendaItems.value.find((candidate) => candidate.id === itemId);
  if (!item) return;
  updateItemField(itemId, 'draftFollowUps', [...ensureDraftFollowUps(item), { title: '', ownerId: null, dueOn: null }]);
};

const updateFollowUpDraft = (itemId, index, key, value) => {
  const item = agendaItems.value.find((candidate) => candidate.id === itemId);
  if (!item) return;
  const nextDrafts = ensureDraftFollowUps(item).map((followUp, currentIndex) => (
    currentIndex === index ? { ...followUp, [key]: value || null } : followUp
  ));
  updateItemField(itemId, 'draftFollowUps', nextDrafts);
};

const removeFollowUpDraft = (itemId, index) => {
  const item = agendaItems.value.find((candidate) => candidate.id === itemId);
  if (!item) return;
  const nextDrafts = ensureDraftFollowUps(item).filter((_, currentIndex) => currentIndex !== index);
  updateItemField(itemId, 'draftFollowUps', nextDrafts);
};

const saveDiscussion = async () => {
  if (!selectedToastModal.value) return;
  isSaving.value = true;
  const currentToastIndex = agendaItems.value.findIndex((item) => item.id === selectedToastModal.value.id);

  const { ok } = await workspacesApi.saveDiscussion(selectedToastModal.value.id, {
    discussionNotes: selectedToastModal.value.discussionNotes,
    ownerId: selectedToastModal.value.owner?.id ?? null,
    dueOn: selectedToastModal.value.dueOn,
    followUpItems: ensureDraftFollowUps(selectedToastModal.value),
  });

  if (!ok) {
    errorMessage.value = 'Unable to save follow-up.';
    isSaving.value = false;
    return;
  }

  await fetchWorkspace();
  openNextActiveToastAtIndex(currentToastIndex >= 0 ? currentToastIndex : 0);
  isSaving.value = false;
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

const handleWorkspaceKeydown = (event) => {
  if (!isTypingTarget(event.target) && !event.metaKey && !event.ctrlKey && !event.altKey && event.key.toLowerCase() === 'h') {
    event.preventDefault();
    window.location.href = dashboardUrl;
    return;
  }

  if (selectedToastModal.value && !isTypingTarget(event.target) && !event.metaKey && !event.ctrlKey && !event.altKey) {
    if (event.key === 'ArrowLeft') {
      event.preventDefault();
      if (isSelectedToastModalDirty.value) {
        triggerToastModalNavigationBlockedFeedback();
        return;
      }
      navigateSelectedToast(-1);
      return;
    }

    if (event.key === 'ArrowRight') {
      event.preventDefault();
      if (isSelectedToastModalDirty.value) {
        triggerToastModalNavigationBlockedFeedback();
        return;
      }
      navigateSelectedToast(1);
      return;
    }
  }

  if (isTypingTarget(event.target) || event.metaKey || event.ctrlKey || event.altKey) {
    return;
  }

  if (event.key.toLowerCase() !== 't' || !workspace.value) {
    return;
  }

  event.preventDefault();
  openCreateToastModal();
};

const syncViewport = () => {
  isMobileViewport.value = window.innerWidth < 1024;
};

const syncMobileActionBarDockState = () => {
  if (!useDedicatedMobileToastView.value || !selectedToastModal.value || !mobileCommentsSectionRef.value) {
    mobileActionBarDocked.value = false;
    return;
  }

  const commentsTop = mobileCommentsSectionRef.value.getBoundingClientRect().top;
  const triggerLine = window.innerHeight - 170;
  const hysteresisPx = 24;
  const dockThreshold = triggerLine - hysteresisPx;
  const undockThreshold = triggerLine + hysteresisPx;

  if (mobileActionBarDocked.value) {
    if (commentsTop > undockThreshold) {
      mobileActionBarDocked.value = false;
    }
    return;
  }

  if (commentsTop <= dockThreshold) {
    mobileActionBarDocked.value = true;
  }
};

const handleViewportOrScrollForMobileActionBar = () => {
  syncViewport();
  syncMobileActionBarDockState();
};

const handleMobileActionBarScroll = () => {
  if (mobileActionBarScrollFrame.value) {
    return;
  }

  mobileActionBarScrollFrame.value = window.requestAnimationFrame(() => {
    mobileActionBarScrollFrame.value = 0;
    syncMobileActionBarDockState();
  });
};

onMounted(() => {
  syncViewport();
  syncToastReturnToFromRoute();
  applyFiltersFromRoute();
  fetchWorkspace();
  window.addEventListener('keydown', handleWorkspaceKeydown);
  window.addEventListener('toastit:create-toast', handleExternalCreateToastRequest);
  window.addEventListener('resize', handleViewportOrScrollForMobileActionBar);
  window.addEventListener('scroll', handleMobileActionBarScroll, { passive: true });
});

onUnmounted(() => {
  if (inboxAddressCopiedTimeout) {
    clearTimeout(inboxAddressCopiedTimeout);
  }
  window.removeEventListener('keydown', handleWorkspaceKeydown);
  window.removeEventListener('toastit:create-toast', handleExternalCreateToastRequest);
  window.removeEventListener('resize', handleViewportOrScrollForMobileActionBar);
  window.removeEventListener('scroll', handleMobileActionBarScroll);
  if (mobileActionBarScrollFrame.value) {
    window.cancelAnimationFrame(mobileActionBarScrollFrame.value);
    mobileActionBarScrollFrame.value = 0;
  }
  disconnectArchivedToastObserver();
  revokeWorkspaceBackgroundObjectUrl();
});

watch(() => props.apiUrl, fetchWorkspace);
watch(() => route.query.returnTo, syncToastReturnToFromRoute);
watch(() => route.name, syncToastReturnToFromRoute);
watch(() => workspace.value?.permalinkBackgroundUrl, loadWorkspaceBackground);
watch(() => route.query.create, consumeCreateToastRouteIntent);
watch(() => workspace.value?.id, consumeCreateToastRouteIntent);
watch(() => props.createOnlyMode, syncCreateOnlyMode);
watch(() => workspace.value?.id, syncCreateOnlyMode);
watch(() => [route.query.filter, route.query.assignee], applyFiltersFromRoute);
watch(currentToastFilter, syncFiltersToRoute);
watch(currentAssigneeFilter, syncFiltersToRoute);
watch(currentToastFilter, () => {
  resetArchivedToastPagination();
  syncArchivedToastObserver();
});
watch(currentAssigneeFilter, () => {
  resetArchivedToastPagination();
  syncArchivedToastObserver();
});
watch(isSoloWorkspace, () => {
  const normalizedAssigneeFilter = resolveAssigneeFilter(currentAssigneeFilter.value);
  if (normalizedAssigneeFilter !== currentAssigneeFilter.value) {
    currentAssigneeFilter.value = normalizedAssigneeFilter;
    return;
  }
  syncFiltersToRoute();
});
watch(assigneeFilterOptions, () => {
  const normalizedAssigneeFilter = resolveAssigneeFilter(currentAssigneeFilter.value);
  if (normalizedAssigneeFilter !== currentAssigneeFilter.value) {
    currentAssigneeFilter.value = normalizedAssigneeFilter;
    return;
  }
  syncFiltersToRoute();
});
watch(displayedVetoedItems, () => {
  vetoedVisibleCount.value = Math.min(Math.max(vetoedVisibleCount.value, 20), displayedVetoedItems.value.length || 20);
  syncArchivedToastObserver();
});
watch(displayedResolvedItems, () => {
  resolvedVisibleCount.value = Math.min(Math.max(resolvedVisibleCount.value, 20), displayedResolvedItems.value.length || 20);
  syncArchivedToastObserver();
});
watch(hasMoreVetoedItems, syncArchivedToastObserver);
watch(hasMoreResolvedItems, syncArchivedToastObserver);
watch([useDedicatedMobileToastView, selectedToastModal], async () => {
  await nextTick();
  syncMobileActionBarDockState();
});
watch(isMobileViewport, (isMobile) => {
  if (isMobile) {
    return;
  }

  isMobileStatusFilterModalOpen.value = false;
  isMobileAssigneeFilterModalOpen.value = false;
});
</script>

<template>
  <section class="relative space-y-6">
    <div v-if="isLoading" class="relative z-10 tw-toastit-card p-6"><EmptyState message="Loading..." /></div>
    <div v-else-if="errorMessage" class="relative z-10 tw-toastit-card p-6 text-sm text-red-600">{{ errorMessage }}</div>
    <template v-else-if="workspace">
      <div class="relative z-10">
      <template v-if="!standaloneMode">
      <div
        class="relative px-4 lg:px-6"
        :class="[
          resolvedWorkspaceBackgroundUrl ? 'rounded-none lg:rounded-3xl py-4 lg:py-6' : 'py-4 lg:py-0',
          isMobileViewport ? 'sticky top-0 z-30 bg-white/95 backdrop-blur' : '',
        ]"
        :style="resolvedWorkspaceBackgroundUrl ? workspaceHeaderBackgroundStyle : {}"
      >
        <PageHeader
          :title="workspace.name"
          :stats="workspaceHeaderStats"
          :actions="workspaceHeaderActions"
          :inverted="!!resolvedWorkspaceBackgroundUrl"
          :tight="isMobileViewport"
          @action="handleWorkspaceHeaderAction"
        />
      </div>

      <div class="mt-0 space-y-0 lg:mt-4">
        <div v-if="isInboxWorkspace" class="mb-4 tw-toastit-card border border-amber-200 bg-amber-50/80 p-5 text-sm text-amber-900">
          <p class="font-semibold">Email-to-toast inbox</p>
          <p class="mt-2 text-amber-800">
            Forward email to
          </p>
          <div class="mt-2 inline-flex w-full max-w-2xl items-center gap-2 rounded-2xl border border-amber-200 bg-white px-3 py-2">
            <input
              ref="inboxAddressInput"
              :value="currentUser?.inboxEmailAddress ?? ''"
              type="text"
              readonly
              class="w-full border-0 bg-transparent p-0 font-mono text-sm text-amber-950 outline-none ring-0 focus:ring-0"
              @click="selectInboxAddress"
              @focus="selectInboxAddress"
            >
            <button
              type="button"
              class="inline-grid h-7 w-7 place-items-center rounded-full border transition"
              :class="inboxAddressCopied ? 'border-emerald-300 bg-emerald-50 text-emerald-600' : 'border-amber-200 bg-white text-amber-800 hover:border-amber-300 hover:text-amber-950'"
              @click="copyInboxAddress"
            >
              <i :class="inboxAddressCopied ? 'fa-solid fa-check text-xs' : 'fa-regular fa-copy text-xs'" aria-hidden="true"></i>
              <span class="sr-only">{{ inboxAddressCopied ? 'Copied' : 'Copy inbox address' }}</span>
            </button>
          </div>
          <p class="mt-2 text-amber-800">to use inbound email features:</p>
          <ul class="mt-2 list-disc space-y-1 pl-5 text-amber-800">
            <li><span class="font-semibold">toast creation</span>: send any email to create a new toast automatically.</li>
            <li>
              <span class="font-semibold">todo list</span>:
              send an email with title
              <span class="inline-flex rounded-full bg-amber-200 px-2 py-0.5 font-mono text-xs font-semibold uppercase tracking-[0.12em] text-amber-900">todo</span>
              to receive your current todo digest.
            </li>
            <li>
              <span class="font-semibold">summary</span>:
              send an email with title
              <span class="inline-flex rounded-full bg-amber-200 px-2 py-0.5 font-mono text-xs font-semibold uppercase tracking-[0.12em] text-amber-900">summary</span>
              to receive your 7-day operational recap.
            </li>
          </ul>
        </div>

        <div class="tw-toastit-card p-6 space-y-4">
            <div class="hidden gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto] lg:grid">
              <input v-model="itemForm.title" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text" placeholder="New toast" @keydown.enter.prevent="createItem">
              <button type="button" class="inline-grid h-[3.125rem] place-items-center rounded-full border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="openCreateToastModal">
                <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                <span class="sr-only">Open toast details</span>
              </button>
              <button class="inline-grid h-[3.125rem] place-items-center rounded-full bg-amber-200 px-5 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300" @click="createItem">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span class="sr-only">Add toast</span>
              </button>
            </div>

            <div class="flex flex-nowrap items-start gap-2 pt-4">
              <template v-if="isMobileViewport">
                <button
                  type="button"
                  class="inline-flex h-11 min-w-0 flex-1 items-center justify-between gap-2 rounded-2xl border border-stone-200 bg-white px-4 text-sm font-medium text-stone-700"
                  @click="openMobileStatusFilterModal"
                >
                  <span class="truncate">{{ selectedStatusFilterLabel }}</span>
                  <i class="fa-solid fa-chevron-down text-xs text-stone-400" aria-hidden="true"></i>
                </button>
                <button
                  v-if="!isSoloWorkspace"
                  type="button"
                  class="inline-flex h-11 min-w-0 flex-1 items-center justify-between gap-2 rounded-2xl border border-stone-200 bg-white px-4 text-sm font-medium text-stone-700"
                  @click="openMobileAssigneeFilterModal"
                >
                  <span class="truncate">{{ selectedAssigneeFilterLabel }}</span>
                  <i class="fa-solid fa-chevron-down text-xs text-stone-400" aria-hidden="true"></i>
                </button>
              </template>
              <template v-else>
                <CompactDropdown v-model="currentToastFilter" class="min-w-0 flex-1" icon="fa-solid fa-filter" :options="statusFilterOptions" />
                <CompactDropdown v-if="!isSoloWorkspace" v-model="currentAssigneeFilter" class="min-w-0 flex-1" icon="fa-solid fa-user-check" :options="assigneeFilterOptions" />
              </template>
            </div>

            <EmptyState
              v-if="(currentToastFilter === 'pending' || currentToastFilter === 'ready') && !displayedAgendaItems.length"
              :message="currentToastFilter === 'ready' ? 'No ready toasts.' : 'No new toasts.'"
            />
            <div v-else-if="currentToastFilter === 'pending' || currentToastFilter === 'ready'" class="overflow-hidden -mx-6 lg:mx-0">
              <div class="space-y-3 bg-white py-4 lg:hidden">
                <div
                  v-for="item in displayedAgendaItems"
                  :key="item.id"
                  class="cursor-pointer space-y-2 px-4 py-1 transition hover:bg-stone-50"
                  :style="workspaceMobileItemBorderStyle(item)"
                  @click="openToastPermalink(item.id)"
                >
                  <p class="block w-full text-left text-sm font-medium leading-5 text-stone-900 line-clamp-2">
                    {{ item.title }}
                  </p>
                  <div class="flex items-center justify-between gap-3">
                    <p class="min-w-0 truncate text-xs leading-5 text-stone-600">
                      <i v-if="item.isBoosted" class="fa-solid fa-star mr-1 text-slate-400" aria-hidden="true"></i>
                      {{ item.dueOnDisplay ?? 'No due date' }} • {{ item.comments?.length ?? 0 }} comment<span v-if="(item.comments?.length ?? 0) > 1">s</span>
                    </p>
                    <button
                      v-if="!isSoloWorkspace"
                      type="button"
                      class="inline-grid h-8 min-w-8 place-items-center rounded-full border px-1.5 text-xs font-semibold transition"
                      :class="item.currentUserHasVoted ? 'border-amber-300 bg-amber-200 text-amber-900' : 'border-stone-200 bg-white text-stone-700 hover:border-stone-300'"
                      :disabled="isToastingMode"
                      title="Vote"
                      @click.stop="toggleVote(item.id)"
                    >
                      {{ item.voteCount }}
                    </button>
                  </div>
                </div>
              </div>
              <table class="hidden min-w-full divide-y divide-stone-200 bg-white text-sm lg:table">
                <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">
                  <tr>
                    <th class="px-4 py-3">Title</th>
                    <th class="px-4 py-3">Owner</th>
                    <th class="px-4 py-3">Due</th>
                    <th class="px-4 py-3">State</th>
                    <th class="px-4 py-3">Comments</th>
                    <th class="px-4 py-3 text-right">Actions</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-stone-100">
                  <tr
                    v-for="item in displayedAgendaItems"
                    :key="item.id"
                    class="cursor-pointer transition hover:bg-stone-50"
                    @click="openToastPermalink(item.id)"
                  >
                    <td class="px-4 py-3">
                      <span class="text-left font-medium text-stone-900">
                        {{ item.title }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-stone-700">{{ item.owner?.displayName ?? 'Unassigned' }}</td>
                    <td class="px-4 py-3 text-stone-700">{{ item.dueOnDisplay ?? 'No due date' }}</td>
                    <td class="px-4 py-3">
                      <span
                        class="inline-flex rounded-full px-2 py-0.5 text-xs font-semibold"
                        :class="item.status === 'ready' ? 'bg-emerald-100 text-emerald-700' : 'bg-stone-100 text-stone-700'"
                      >
                        {{ item.status === 'ready' ? 'Ready' : 'In progress' }}
                      </span>
                    </td>
                    <td class="px-4 py-3 text-stone-700">{{ item.comments?.length ?? 0 }}</td>
                    <td class="px-4 py-3">
                      <div class="flex items-center justify-end gap-2.5">
                        <button
                          v-if="!isSoloWorkspace"
                          type="button"
                          class="inline-grid h-10 min-w-10 place-items-center rounded-full border px-2 text-sm font-semibold transition"
                          :class="item.currentUserHasVoted ? 'border-amber-300 bg-amber-200 text-amber-900' : 'border-stone-200 bg-white text-stone-700 hover:border-stone-300'"
                          :disabled="isToastingMode"
                          title="Vote"
                          @click.stop="toggleVote(item.id)"
                        >
                          {{ item.voteCount }}
                        </button>
                        <button
                          v-if="item.currentUserCanMarkReady"
                          type="button"
                          class="inline-grid h-10 w-10 place-items-center rounded-full border transition"
                          :class="item.status === 'ready' ? 'border-emerald-300 bg-emerald-100 text-emerald-700' : 'border-stone-200 bg-white text-stone-600 hover:border-emerald-300 hover:text-emerald-700'"
                          :disabled="isToastingMode"
                          title="Toggle ready"
                          @click.stop="setReady(item.id, item.status !== 'ready')"
                        >
                          <i :class="item.status === 'ready' ? 'fa-solid fa-rotate-left text-sm' : 'fa-solid fa-check text-sm'" aria-hidden="true"></i>
                          <span class="sr-only">{{ item.status === 'ready' ? 'Set in progress' : 'Set ready' }}</span>
                        </button>
                        <button
                          v-if="workspace.currentUserIsOwner && isSoloWorkspace"
                          type="button"
                          class="inline-grid h-10 w-10 place-items-center rounded-full border border-amber-200 bg-white text-amber-700 transition hover:border-amber-300 hover:bg-amber-50"
                          title="Mark toasted"
                          @click.stop="toastItem(item.id)"
                        >
                          <i class="fa-solid fa-check text-sm" aria-hidden="true"></i>
                          <span class="sr-only">Mark as toasted</span>
                        </button>
                        <button
                          v-if="workspace.currentUserIsOwner && !isToastingMode && !isSoloWorkspace"
                          type="button"
                          class="inline-grid h-10 w-10 place-items-center rounded-full border transition"
                          :class="item.isBoosted ? 'border-slate-400 bg-slate-400 text-white' : 'border-stone-200 bg-white text-slate-600 hover:border-slate-300 hover:text-slate-700'"
                          title="Boost"
                          @click.stop="toggleBoost(item.id)"
                        >
                          <i class="fa-solid fa-star text-sm" aria-hidden="true"></i>
                          <span class="sr-only">{{ item.isBoosted ? 'Remove boost' : 'Boost' }}</span>
                        </button>
                        <button
                          v-if="workspace.currentUserIsOwner && !isToastingMode"
                          type="button"
                          class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-red-200 hover:text-red-700"
                          title="Decline toast"
                          @click.stop="toggleVeto(item.id)"
                        >
                          <i class="fa-solid fa-trash text-sm" aria-hidden="true"></i>
                          <span class="sr-only">Decline toast</span>
                        </button>
                      </div>
                    </td>
                  </tr>
                </tbody>
              </table>
            </div>

            <div v-else-if="currentToastFilter === 'discarded' && displayedVetoedItems.length" class="space-y-3">
              <div class="overflow-hidden rounded-2xl border border-stone-200">
                <div class="divide-y divide-stone-100 bg-white lg:hidden">
                  <div
                    v-for="item in visibleVetoedItems"
                    :key="item.id"
                    class="cursor-pointer space-y-2 px-4 py-3 transition hover:bg-stone-50"
                    @click="openToastPermalink(item.id)"
                  >
                    <p class="block w-full text-left text-sm font-medium leading-5 text-stone-900 line-clamp-2">
                      {{ item.title }}
                    </p>
                    <p class="min-w-0 truncate text-xs leading-5 text-stone-600">
                      {{ item.owner?.displayName ?? 'Unassigned' }} • {{ item.statusChangedAtDisplay }}
                    </p>
                  </div>
                </div>
                <table class="hidden min-w-full divide-y divide-stone-200 bg-white text-sm lg:table">
                  <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">
                    <tr>
                      <th class="px-4 py-3">Title</th>
                      <th class="px-4 py-3">Owner</th>
                      <th class="px-4 py-3">Declined at</th>
                      <th class="px-4 py-3 text-right">Actions</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-stone-100">
                    <tr
                      v-for="item in visibleVetoedItems"
                      :key="item.id"
                      class="cursor-pointer transition hover:bg-stone-50"
                      @click="openToastPermalink(item.id)"
                    >
                      <td class="px-4 py-3">
                        <span class="text-left font-medium text-stone-900">
                          {{ item.title }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-stone-700">{{ item.owner?.displayName ?? 'Unassigned' }}</td>
                      <td class="px-4 py-3 text-stone-700">{{ item.statusChangedAtDisplay }}</td>
                      <td class="px-4 py-3">
                        <div class="flex items-center justify-end gap-2">
                          <button
                            v-if="workspace.currentUserIsOwner && !isToastingMode"
                            type="button"
                            class="inline-grid h-8 w-8 place-items-center rounded-full border border-emerald-200 bg-white text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50"
                            title="Restore toast"
                            @click.stop="toggleVeto(item.id)"
                          >
                            <i class="fa-solid fa-rotate-left text-xs" aria-hidden="true"></i>
                            <span class="sr-only">Restore toast</span>
                          </button>
                        </div>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div
                v-if="hasMoreVetoedItems"
                ref="vetoedInfiniteLoader"
                class="flex w-full items-center justify-center rounded-2xl border border-dashed border-stone-200 bg-stone-50 px-4 py-4 text-sm font-medium text-stone-500"
              >
                <i class="fa-solid fa-spinner mr-3 animate-spin text-stone-400" aria-hidden="true"></i>
                <span>Loading more declined toasts...</span>
              </div>
            </div>
            <EmptyState v-else-if="currentToastFilter === 'discarded'" message="No declined toasts." />

            <div v-else-if="currentToastFilter === 'resolved' && displayedResolvedItems.length" class="space-y-3">
              <div class="overflow-hidden rounded-2xl border border-stone-200">
                <div class="divide-y divide-stone-100 bg-white lg:hidden">
                  <div
                    v-for="item in visibleResolvedItems"
                    :key="item.id"
                    class="tw-toasted-item-highlight cursor-pointer space-y-2 bg-amber-200/90 px-4 py-3 text-amber-950 transition hover:bg-amber-200"
                    @click="openToastPermalink(item.id)"
                  >
                    <p class="flex items-center gap-2 text-left text-sm font-semibold leading-5">
                      <i class="fa-solid fa-check text-amber-700" aria-hidden="true"></i>
                      <span class="block min-w-0 flex-1 line-clamp-2">{{ item.title }}</span>
                    </p>
                    <p class="min-w-0 truncate text-xs leading-5 text-amber-900/80">
                      {{ item.owner?.displayName ?? 'Unassigned' }} • {{ item.statusChangedAtDisplay }}
                    </p>
                  </div>
                </div>
                <table class="hidden min-w-full divide-y divide-stone-200 bg-white text-sm lg:table">
                  <thead class="bg-stone-50 text-left text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">
                    <tr>
                      <th class="px-4 py-3">Title</th>
                      <th class="px-4 py-3">Owner</th>
                      <th class="px-4 py-3">Toasted at</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-stone-100">
                    <tr
                      v-for="item in visibleResolvedItems"
                      :key="item.id"
                      class="cursor-pointer transition hover:bg-stone-50"
                      @click="openToastPermalink(item.id)"
                    >
                      <td class="px-4 py-3">
                        <span class="text-left font-medium text-stone-900">
                          {{ item.title }}
                        </span>
                      </td>
                      <td class="px-4 py-3 text-stone-700">{{ item.owner?.displayName ?? 'Unassigned' }}</td>
                      <td class="px-4 py-3 text-stone-700">{{ item.statusChangedAtDisplay }}</td>
                    </tr>
                  </tbody>
                </table>
              </div>
              <div
                v-if="hasMoreResolvedItems"
                ref="resolvedInfiniteLoader"
                class="flex w-full items-center justify-center rounded-2xl border border-dashed border-stone-200 bg-stone-50 px-4 py-4 text-sm font-medium text-stone-500"
              >
                <i class="fa-solid fa-spinner mr-3 animate-spin text-stone-400" aria-hidden="true"></i>
                <span>Loading more toasted toasts...</span>
              </div>
            </div>
            <EmptyState v-else-if="currentToastFilter === 'resolved'" message="No toasted toasts." />
        </div>
      </div>
      </template>
      <template v-else-if="useDedicatedMobileToastView">
        <div class="space-y-0 pb-28 pt-0">
          <div class="sticky top-0 z-40 border-b border-stone-200/80 bg-white/95 px-3 pb-3 backdrop-blur" :style="{ paddingTop: 'calc(0.5rem + env(safe-area-inset-top))' }">
            <div class="flex items-center gap-3">
              <button
                type="button"
                class="inline-grid h-9 w-9 shrink-0 place-items-center rounded-full border border-stone-200 bg-white text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
                @click="closeMobileStandaloneToast"
              >
                <i class="fa-solid fa-arrow-left text-sm" aria-hidden="true"></i>
                <span class="sr-only">Back to workspace</span>
              </button>
              <h1
                v-if="selectedToastModal"
                class="min-w-0 text-xl font-semibold leading-tight tracking-tight text-stone-950"
                style="display: -webkit-box; -webkit-line-clamp: 3; -webkit-box-orient: vertical; overflow: hidden;"
              >
                {{ selectedToastModal.title }}
              </h1>
              <h1 class="min-w-0 text-xl font-semibold leading-tight tracking-tight text-stone-950" v-else>Loading toast...</h1>
            </div>
          </div>

          <div class="tw-toastit-card rounded-none border-l-0 border-r-0 border-t-0 p-4">
            <template v-if="selectedToastModal">
              <div class="flex items-center">
                <ToastStatusBadge
                  :label="displayToastStatus(selectedToastModal)"
                  :tone-class="toastStatusTone(selectedToastModal)"
                />
              </div>

              <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-xs text-stone-500">
                <span class="inline-flex items-center gap-2">
                  <i class="fa-regular fa-user" aria-hidden="true"></i>
                  <span>{{ selectedToastModal.author.displayName }}</span>
                </span>
                <span v-if="selectedToastModal.owner" class="inline-flex items-center gap-2">
                  <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                  <span>{{ selectedToastModal.owner.displayName }}</span>
                </span>
                <span v-if="selectedToastModal.dueOnDisplay" class="inline-flex items-center gap-2">
                  <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                  <span>{{ selectedToastModal.dueOnDisplay }}</span>
                </span>
              </div>

              <div v-if="selectedToastModal.description" class="mt-5 tw-markdown text-stone-700" v-html="renderToastDescription(selectedToastModal.description)"></div>
            </template>
            <EmptyState v-else message="Loading toast..." />
          </div>

          <div
            v-if="selectedToastModal"
            class="z-[96] overflow-hidden border border-stone-200/80 bg-white/80 backdrop-blur transition-all duration-300"
            :class="mobileActionBarDocked ? 'tw-mobile-toast-actions--docked sticky left-0 right-0 w-full rounded-none border-x-0 border-t-0 shadow-sm' : 'tw-mobile-toast-actions--floating fixed right-4 rounded-2xl shadow-lg'"
            :style="{ bottom: 'calc(5.9rem + env(safe-area-inset-bottom) + 0.75rem)' }"
          >
            <div class="flex items-stretch divide-x divide-stone-200/80">
              <button
                v-if="!isSoloWorkspace && isActiveToast(selectedToastModal)"
                type="button"
                class="inline-flex min-h-12 min-w-[3.75rem] flex-1 items-center justify-center gap-1.5 px-3.5 py-2.5 text-sm font-semibold transition"
                :class="selectedToastModal.currentUserHasVoted ? 'bg-amber-200 text-amber-900' : 'bg-transparent text-stone-700'"
                :disabled="isToastingMode"
                @click="toggleVote(selectedToastModal.id)"
              >
                <span>{{ selectedToastModal.voteCount }}</span>
                <i class="fa-solid fa-thumbs-up text-sm" aria-hidden="true"></i>
              </button>
              <button
                v-if="workspace.currentUserIsOwner && !isToastingMode && !isSoloWorkspace && isActiveToast(selectedToastModal)"
                type="button"
                class="inline-grid min-h-12 min-w-[3.75rem] flex-1 place-items-center px-3.5 py-2.5 transition"
                :class="selectedToastModal.isBoosted ? 'bg-slate-400 text-white' : 'bg-transparent text-slate-600'"
                @click="toggleBoost(selectedToastModal.id)"
              >
                <i class="fa-solid fa-star text-sm" aria-hidden="true"></i>
                <span class="sr-only">{{ selectedToastModal.isBoosted ? 'Remove boost' : 'Boost' }}</span>
              </button>
              <button
                v-if="selectedToastModal.currentUserCanMarkReady"
                type="button"
                class="inline-grid min-h-12 min-w-[3.75rem] flex-1 place-items-center px-3.5 py-2.5 transition"
                :class="selectedToastModal.status === 'ready' ? 'bg-emerald-500 text-white' : 'bg-transparent text-emerald-800'"
                :disabled="isToastingMode"
                @click="setReady(selectedToastModal.id, selectedToastModal.status !== 'ready')"
              >
                <i :class="selectedToastModal.status === 'ready' ? 'fa-solid fa-rotate-left text-sm' : 'fa-solid fa-check text-sm'" aria-hidden="true"></i>
                <span class="sr-only">{{ selectedToastModal.status === 'ready' ? 'In progress' : 'Ready' }}</span>
              </button>
              <button
                v-if="selectedToastModal.currentUserCanEdit && isActiveToast(selectedToastModal) && !isToastingMode"
                type="button"
                class="inline-grid min-h-12 min-w-[3.75rem] flex-1 place-items-center px-3.5 py-2.5 text-stone-700 transition hover:text-stone-950"
                @click="openEditToastModal(selectedToastModal)"
              >
                <i class="fa-solid fa-pen text-sm" aria-hidden="true"></i>
                <span class="sr-only">Edit toast</span>
              </button>
              <button
                v-if="workspace.currentUserIsOwner && isSoloWorkspace && isActiveToast(selectedToastModal)"
                type="button"
                class="inline-grid min-h-12 min-w-[3.75rem] flex-1 place-items-center bg-transparent px-3.5 py-2.5 text-amber-700 transition"
                @click="toastItem(selectedToastModal.id)"
              >
                <i class="fa-solid fa-check text-sm" aria-hidden="true"></i>
                <span class="sr-only">Mark as done</span>
              </button>
              <button
                v-if="workspace.currentUserIsOwner && !isToastingMode && isActiveToast(selectedToastModal)"
                type="button"
                class="inline-grid min-h-12 min-w-[3.75rem] flex-1 place-items-center bg-transparent px-3.5 py-2.5 text-stone-600 transition hover:text-red-700"
                @click="requestMobileToastVeto(selectedToastModal.id)"
              >
                <i class="fa-solid fa-trash text-sm" aria-hidden="true"></i>
                <span class="sr-only">{{ selectedToastModal.status === 'discarded' ? 'Restore toast' : 'Decline toast' }}</span>
              </button>
            </div>
          </div>

          <div ref="mobileCommentsSectionRef" v-if="selectedToastModal" class="tw-toastit-card rounded-none border-l-0 border-r-0 border-t-0 bg-stone-50/70 p-4">
            <div class="flex items-center justify-between">
              <h2 class="text-sm font-semibold uppercase tracking-[0.16em] text-stone-500">Comments</h2>
              <span class="inline-flex min-w-6 items-center justify-center rounded-full bg-white px-2 py-1 text-xs font-semibold text-stone-600">{{ selectedToastModal.comments?.length ?? 0 }}</span>
            </div>
            <div class="mt-3 space-y-4">
              <CommentThread :comments="selectedToastModal.comments ?? []" :render-comment="renderToastDescription" mobile />
              <button
                v-if="isActiveToast(selectedToastModal)"
                type="button"
                class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-amber-200 px-4 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300"
                @click="openMobileCommentModal"
              >
                <i class="fa-regular fa-message text-sm" aria-hidden="true"></i>
                <span>Add a comment</span>
              </button>
            </div>
          </div>
        </div>
      </template>
      </div>

      <ModalDialog v-if="isManageModalOpen" max-width-class="max-w-4xl" @close="closeManageModal">
        <ModalHeader
          eyebrow="Workspace settings"
          :title="workspace.name"
          description="Manage members and review this workspace without leaving the toast board."
          @close="closeManageModal"
        />

          <div class="overflow-y-auto px-6 py-6">
            <div class="space-y-4">
              <div v-if="workspace.isDefault" class="rounded-[1.25rem] border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
                This is the default workspace for this account.
              </div>

              <div>
                <h3 class="text-lg font-semibold text-stone-950">Workspace settings</h3>
                <p class="mt-1 text-sm leading-6 text-stone-600">Configure naming, defaults, background, and behavior for this workspace.</p>
              </div>

              <div class="space-y-3">
                <label class="grid gap-2 text-sm font-medium text-stone-700">
                  <span>Workspace name</span>
                  <input v-model="workspaceSettingsForm.name" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text">
                </label>
                <label class="grid gap-2 text-sm font-medium text-stone-700">
                  <span>Default due date for new toasts</span>
                  <select v-model="workspaceSettingsForm.defaultDuePreset" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm">
                    <option v-for="option in duePresetOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                  </select>
                </label>
              <div class="grid gap-2 text-sm font-medium text-stone-700">
                <span>Permalink background image</span>
                <input
                  ref="workspaceBackgroundInput"
                  class="sr-only"
                  type="file"
                  accept="image/png,image/jpeg,image/webp,image/gif"
                  @change="handleWorkspaceBackgroundChange"
                >
                <button
                  type="button"
                  class="flex min-h-28 flex-col items-center justify-center gap-2 rounded-2xl border border-dashed bg-white px-4 py-5 text-center transition"
                  :class="isWorkspaceBackgroundDragOver ? 'border-amber-400 bg-amber-50' : 'border-stone-300 hover:border-stone-400 hover:bg-stone-50'"
                  @click="openWorkspaceBackgroundBrowse"
                  @dragenter.prevent="isWorkspaceBackgroundDragOver = true"
                  @dragover.prevent="isWorkspaceBackgroundDragOver = true"
                  @dragleave.prevent="isWorkspaceBackgroundDragOver = false"
                  @drop.prevent="handleWorkspaceBackgroundDrop"
                >
                  <i class="fa-regular fa-image text-lg text-stone-400" aria-hidden="true"></i>
                  <p class="text-sm font-medium text-stone-700">
                    <span v-if="workspaceBackgroundFile">{{ workspaceBackgroundFile.name }}</span>
                    <span v-else>Drag and drop an image here or click to browse</span>
                  </p>
                  <p class="text-xs text-stone-400">PNG, JPG, WebP or GIF. One private background image per workspace.</p>
                </button>
              </div>
                <label class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-700">
                  <span>Solo workspace</span>
                  <input v-model="workspaceSettingsForm.isSoloWorkspace" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-amber-500 focus:ring-amber-400">
                </label>
                <div class="flex justify-end">
                  <button type="button" class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300" @click="saveWorkspaceSettings">Save settings</button>
                </div>
              </div>

              <div>
                <h3 class="text-lg font-semibold text-stone-950">Members</h3>
                <p class="mt-1 text-sm leading-6 text-stone-600">Invite the right people so every toast stays in the right context, with the right collaborators.</p>
              </div>

              <div class="space-y-3">
                <MemberListItem
                  v-for="membership in members"
                  :key="membership.id"
                  :membership="membership"
                  :workspace-current-user-is-owner="workspace.currentUserIsOwner"
                  :owner-count="ownerCount"
                  @promote="promoteMember"
                  @demote="demoteMember"
                  @remove="removeMember"
                />
              </div>

              <div v-if="workspace.currentUserIsOwner" class="space-y-3 rounded-[1.25rem] border border-stone-200 bg-stone-50 p-4">
                <label class="grid gap-2 text-sm font-medium text-stone-700">
                  <span>Invite by email</span>
                  <input v-model="inviteEmail" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="email">
                </label>
                <div class="flex justify-end">
                  <button class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300" @click="inviteMember">Invite</button>
                </div>
              </div>

              <div v-if="workspace.currentUserIsOwner" class="rounded-[1.25rem] border border-rose-200 bg-rose-50 p-4">
                <div class="space-y-3">
                  <div>
                    <h3 class="text-lg font-semibold text-rose-900">Delete workspace</h3>
                    <p class="mt-1 text-sm leading-6 text-rose-800">This is a soft delete. The workspace becomes hidden from the app and can later be restored from your profile.</p>
                  </div>
                  <div class="flex justify-end">
                    <button
                      type="button"
                      class="rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-60"
                      :disabled="isDeletingWorkspace"
                      @click="openDeleteWorkspaceConfirm"
                    >
                      {{ isDeletingWorkspace ? 'Deleting...' : 'Delete workspace' }}
                    </button>
                  </div>
                </div>
              </div>
            </div>
          </div>
      </ModalDialog>

      <ModalDialog v-if="isDeleteWorkspaceConfirmOpen" max-width-class="max-w-4xl" @close="closeDeleteWorkspaceConfirm">
        <ModalHeader
          eyebrow="Danger zone"
          title="Delete workspace"
          description="This is a soft delete. The workspace can later be restored from your profile."
          @close="closeDeleteWorkspaceConfirm"
        />

        <div class="space-y-6 px-6 py-6">
          <div class="space-y-3 text-sm text-stone-700">
            <p>This workspace will disappear from the app for everyone except owners.</p>
            <p>Only owners will still see it from their profile page, with a single restore action.</p>
          </div>

          <div class="flex items-center justify-end gap-3">
            <button
              type="button"
              class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
              @click="closeDeleteWorkspaceConfirm"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-60"
              :disabled="isDeletingWorkspace"
              @click="deleteWorkspace"
            >
              {{ isDeletingWorkspace ? 'Deleting...' : 'Delete workspace' }}
            </button>
          </div>
        </div>
      </ModalDialog>

      <SessionArchiveModal
        :open="isSessionArchiveOpen"
        :sessions="toastingSessions"
        :selected-session-id="selectedSessionArchiveId"
        :current-user-is-owner="workspace.currentUserIsOwner"
        :is-generating="isSessionArchiveGenerating"
        :is-saving="isSessionArchiveSaving"
        :is-sending="isSessionArchiveSending"
        :error-message="sessionArchiveError"
        :notice-message="sessionArchiveNotice"
        @close="closeSessionArchive"
        @select="selectSessionArchive"
        @generate="generateSessionArchiveSummary"
        @save="saveSessionArchiveSummary"
        @send="sendSessionArchiveSummary"
      />

      <ToastCurationModal
        :open="isToastCurationOpen"
        :draft="toastCurationDraft"
        :toast-lookup="toastLookup"
        :is-generating="isToastCurationGenerating"
        :applying-index="toastCurationApplyingIndex"
        :action-statuses="toastCurationActionStatuses"
        :error-message="toastCurationError"
        :notice-message="toastCurationNotice"
        @close="closeToastCuration"
        @generate="generateToastCurationDraft"
        @apply-item="applyToastCurationAction"
      />

      <CreateToastModal
        ref="createToastModalRef"
        :open="isCreateToastModalOpen"
        :item-form="itemForm"
        :participants="participants"
        :title="editingToastId ? 'Edit toast' : 'Toast details'"
        :action-label="editingToastId ? 'Save changes' : 'Create toast'"
        :is-refining="isToastDraftRefining"
        :can-undo-refinement="!!toastDraftRefinementBackup"
        @close="closeCreateToastModal"
        @create="createItem"
        @refine="refineToastDraft"
        @undo-refine="undoToastDraftRefinement"
        @title-keydown="handleCreateToastModalKeydown"
        @update:title="itemForm.title = $event"
        @update:owner-id="itemForm.ownerId = $event"
        @update:due-on="itemForm.dueOn = $event"
        @update:description="itemForm.description = $event"
      />

      <ModalDialog v-if="selectedToastModal && !useDedicatedMobileToastView" max-width-class="max-w-4xl" @close="closeToastModal">
        <div class="relative border-b border-stone-100">
          <ModalHeader eyebrow="Toast details" :title="selectedToastModal.title" @close="closeToastModal">
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-stone-500">
              <ToastStatusBadge :label="displayToastStatus(selectedToastModal)" :tone-class="toastStatusTone(selectedToastModal)" />
              <span class="inline-flex items-center gap-2">
                <i class="fa-regular fa-user" aria-hidden="true"></i>
                <span>{{ selectedToastModal.author.displayName }}</span>
              </span>
              <span v-if="selectedToastModal.owner" class="inline-flex items-center gap-2">
                <i class="fa-solid fa-user-check" aria-hidden="true"></i>
                <span>{{ selectedToastModal.owner.displayName }}</span>
              </span>
              <span v-if="selectedToastModal.dueOnDisplay" class="inline-flex items-center gap-2">
                <i class="fa-regular fa-calendar" aria-hidden="true"></i>
                <span>{{ selectedToastModal.dueOnDisplay }}</span>
              </span>
            </div>
          </ModalHeader>
          <div class="mt-4 border-t border-stone-100 px-6 pb-4 pt-4">
            <div class="flex flex-wrap items-center gap-2">
              <div class="flex min-h-11 flex-wrap items-stretch overflow-hidden rounded-2xl border border-stone-200">
                <button
                  v-if="!isSoloWorkspace && isActiveToast(selectedToastModal)"
                  type="button"
                  class="inline-flex min-h-11 items-center justify-center gap-2 border-r border-stone-200 px-4 text-sm font-semibold transition last:border-r-0"
                  :class="selectedToastModal.currentUserHasVoted ? 'bg-amber-200 text-amber-900' : 'bg-white text-stone-700 hover:bg-stone-50'"
                  :disabled="isToastingMode"
                  @click="toggleVote(selectedToastModal.id)"
                >
                  <span>{{ selectedToastModal.voteCount }}</span>
                  <i class="fa-solid fa-thumbs-up text-sm" aria-hidden="true"></i>
                </button>
                <button
                  v-if="workspace.currentUserIsOwner && !isToastingMode && !isSoloWorkspace && isActiveToast(selectedToastModal)"
                  type="button"
                  class="inline-grid min-h-11 min-w-12 place-items-center border-r border-stone-200 px-4 transition"
                  :class="selectedToastModal.isBoosted ? 'bg-slate-400 text-white' : 'bg-white text-slate-600 hover:bg-stone-50'"
                  @click="toggleBoost(selectedToastModal.id)"
                >
                  <i class="fa-solid fa-star text-sm" aria-hidden="true"></i>
                  <span class="sr-only">{{ selectedToastModal.isBoosted ? 'Remove boost' : 'Boost' }}</span>
                </button>
                <button
                  v-if="selectedToastModal.currentUserCanMarkReady"
                  type="button"
                  class="inline-flex min-h-11 items-center justify-center gap-2 border-r border-stone-200 px-4 text-sm font-semibold transition"
                  :class="selectedToastModal.status === 'ready' ? 'bg-emerald-500 text-white' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'"
                  :disabled="isToastingMode"
                  @click="setReady(selectedToastModal.id, selectedToastModal.status !== 'ready')"
                >
                  <i :class="selectedToastModal.status === 'ready' ? 'fa-solid fa-rotate-left text-sm' : 'fa-solid fa-check text-sm'" aria-hidden="true"></i>
                  <span>{{ selectedToastModal.status === 'ready' ? 'Mark in progress' : 'Mark ready' }}</span>
                </button>
                <button
                  v-if="selectedToastModal.currentUserCanEdit && isActiveToast(selectedToastModal) && !isToastingMode"
                  type="button"
                  class="inline-grid min-h-11 min-w-12 place-items-center border-r border-stone-200 bg-white px-4 text-stone-600 transition hover:bg-stone-50 hover:text-stone-950"
                  @click="openEditToastModal(selectedToastModal)"
                >
                  <i class="fa-solid fa-pen text-sm" aria-hidden="true"></i>
                  <span class="sr-only">Edit toast</span>
                </button>
                <button
                  v-if="workspace.currentUserIsOwner && isActiveToast(selectedToastModal) && !isToastingMode"
                  type="button"
                  class="inline-grid min-h-11 min-w-12 place-items-center bg-white px-4 transition hover:bg-stone-50"
                  :class="selectedToastModal.status === 'discarded' ? 'text-red-700' : 'text-stone-600 hover:text-red-700'"
                  @click="toggleVeto(selectedToastModal.id)"
                >
                  <i class="fa-solid fa-trash text-sm" aria-hidden="true"></i>
                  <span class="sr-only">{{ selectedToastModal.status === 'discarded' ? 'Restore toast' : 'Decline toast' }}</span>
                </button>
              </div>

              <div
                v-if="(selectedToastModal.status === 'discarded' || selectedToastModal.status === 'toasted') || (isActiveToast(selectedToastModal) && otherWorkspaces.length && !isToastingMode) || (workspace.currentUserIsOwner && isSoloWorkspace && isActiveToast(selectedToastModal))"
                class="flex min-h-11 flex-wrap items-stretch overflow-hidden rounded-2xl border border-stone-200"
              >
                <button
                  v-if="isActiveToast(selectedToastModal) && otherWorkspaces.length && !isToastingMode"
                  type="button"
                  class="inline-grid min-h-11 min-w-12 place-items-center border-r border-stone-200 bg-white px-4 text-stone-600 transition hover:bg-stone-50 hover:text-stone-950"
                  @click="openMoveCopyToastModal"
                >
                  <i class="fa-solid fa-right-left text-sm" aria-hidden="true"></i>
                  <span class="sr-only">Move or copy toast</span>
                </button>
                <button
                  v-if="selectedToastModal.status === 'discarded' || selectedToastModal.status === 'toasted'"
                  type="button"
                  class="inline-grid min-h-11 min-w-12 place-items-center border-r border-stone-200 bg-white px-4 text-stone-600 transition hover:bg-stone-50 hover:text-stone-950"
                  title="Copy as new"
                  @click="copyToast()"
                >
                  <i class="fa-regular fa-copy text-sm" aria-hidden="true"></i>
                  <span class="sr-only">Copy as new</span>
                </button>
                <button
                  v-if="workspace.currentUserIsOwner && isSoloWorkspace && isActiveToast(selectedToastModal)"
                  type="button"
                  class="inline-grid min-h-11 min-w-12 place-items-center bg-white px-4 text-amber-700 transition hover:bg-amber-50"
                  @click="toastItem(selectedToastModal.id)"
                >
                  <i class="fa-solid fa-check text-sm" aria-hidden="true"></i>
                  <span class="sr-only">Mark as toasted</span>
                </button>
              </div>
            </div>
          </div>
        </div>

          <div class="overflow-y-auto px-6 py-6">
            <div class="space-y-6">
              <div>
                <div v-if="selectedToastModal.description" class="tw-markdown text-stone-700" v-html="renderToastDescription(selectedToastModal.description)"></div>
                <p v-else class="text-lg text-stone-500">No description</p>
              </div>

              <div v-if="selectedToastModal.previousItem" class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Created from</p>
                <button type="button" class="mt-2 text-left text-sm text-stone-800 transition hover:text-amber-700" @click="openToastById(selectedToastModal.previousItem.id)">
                  <strong>{{ selectedToastModal.previousItem.title }}</strong>
                  <span class="font-semibold" :class="toastStatusTone(selectedToastModal.previousItem)"> · {{ relatedToastStatusLabel(selectedToastModal.previousItem) }}</span>
                </button>
              </div>

              <div v-if="workspace.currentUserIsOwner && isToastingMode && isActiveToast(selectedToastModal)" class="space-y-4">
                <label class="grid gap-2 text-sm font-medium text-stone-700">
                  <span>Decision notes</span>
                  <textarea
                    class="min-h-28 rounded-2xl border bg-white px-4 py-3 text-sm transition"
                    :class="toastModalNavigationBlocked && isDecisionNotesDirty ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-200'"
                    :value="selectedToastModal.discussionNotes ?? ''"
                    @input="updateItemField(selectedToastModal.id, 'discussionNotes', $event.target.value)"
                  />
                </label>

                <FollowUpEditor
                  :follow-ups="ensureDraftFollowUps(selectedToastModal)"
                  :participants="participants"
                  :blocked="toastModalNavigationBlocked && isFollowUpsDirty"
                  :can-generate="!!selectedToastModal.discussionNotes?.trim() && !isDecisionNotesDirty"
                  :is-generating="isExecutionPlanGenerating"
                  @add="addFollowUpDraft(selectedToastModal.id)"
                  @remove="removeFollowUpDraft(selectedToastModal.id, $event)"
                  @update="updateFollowUpDraft(selectedToastModal.id, $event.index, $event.key, $event.value)"
                  @generate="generateExecutionPlan"
                />

                <ToastExecutionPlanPanel
                  :draft="executionPlanDraft"
                  :participants-lookup="participantsLookup"
                  :is-generating="isExecutionPlanGenerating"
                  :applying-index="executionPlanApplyingIndex"
                  :action-statuses="executionPlanActionStatuses"
                  :error-message="executionPlanError"
                  :notice-message="executionPlanNotice"
                  @generate="generateExecutionPlan"
                  @apply-item="applyExecutionPlanAction"
                />

                <div class="flex flex-wrap justify-end gap-3">
                  <button
                    type="button"
                    class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950 disabled:opacity-60"
                    :disabled="isSaving || !selectedToastModal.discussionNotes?.trim()"
                    @click="saveDecisionNotes"
                  >
                    {{ isSaving ? 'Saving...' : 'Save notes' }}
                  </button>
                  <button type="button" class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300 disabled:opacity-60" :disabled="isSaving" @click="saveDiscussion">
                    {{ isSaving ? 'Saving...' : 'Toast it' }}
                  </button>
                </div>
              </div>

              <section v-if="selectedToastModal.followUpItems?.length" class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Follow-up toasts</p>
                <div class="rounded-[1.5rem] border border-stone-200 bg-white p-4">
                  <div class="space-y-2">
                    <button
                      v-for="followUp in selectedToastModal.followUpItems"
                      :key="followUp.id"
                      type="button"
                      class="block w-full rounded-2xl px-3 py-3 text-left text-sm text-stone-800 transition hover:bg-stone-50 hover:text-amber-700"
                      @click="openToastById(followUp.id)"
                    >
                      <strong>{{ followUp.title }}</strong>
                      <span class="font-semibold" :class="toastStatusTone(followUp)"> · {{ relatedToastStatusLabel(followUp) }}</span>
                      <span v-if="followUp.ownerName"> · {{ followUp.ownerName }}</span>
                      <span v-if="followUp.dueOnDisplay"> · {{ followUp.dueOnDisplay }}</span>
                    </button>
                  </div>
                </div>
              </section>

              <section v-if="selectedToastModal.discussionNotes && (!isToastingMode || selectedToastModal.status === 'toasted' || !workspace.currentUserIsOwner)" class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Decision</p>
                <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-5 py-4">
                  <div class="tw-markdown text-stone-800" v-html="renderToastDescription(selectedToastModal.discussionNotes)"></div>
                </div>
              </section>

              <section class="space-y-3">
                <div class="flex items-center justify-between gap-4">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Comments</p>
                  <span class="text-xs font-medium text-stone-500">{{ selectedToastModal.comments?.length ?? 0 }}</span>
                </div>

                <div class="rounded-[1.5rem] border border-stone-200 bg-white p-4">
                  <CommentThread :comments="selectedToastModal.comments ?? []" :render-comment="renderToastDescription" />

                  <CommentComposer
                    v-if="isActiveToast(selectedToastModal)"
                    :current-user="currentUser"
                    :value="commentDraftFor(selectedToastModal.id)"
                    :blocked="toastModalNavigationBlocked && isCommentDraftDirty"
                    @input="handleCommentDraftInput(selectedToastModal.id, $event)"
                    @keydown="handleCommentDraftKeydown(selectedToastModal.id, $event)"
                    @submit="createComment(selectedToastModal.id)"
                  />
                </div>
              </section>

              <ToastNavigationFooter
                :can-navigate-previous="canNavigateSelectedToast(-1)"
                :can-navigate-next="canNavigateSelectedToast(1)"
                @previous="navigateSelectedToast(-1)"
                @next="navigateSelectedToast(1)"
              />
            </div>
          </div>
      </ModalDialog>

      <ModalDialog v-if="isMobileViewport && isMobileStatusFilterModalOpen" max-width-class="max-w-4xl" @close="isMobileStatusFilterModalOpen = false">
        <ModalHeader
          title="Status filter"
          @close="isMobileStatusFilterModalOpen = false"
        />
        <div class="space-y-4 px-6 py-6">
          <div class="overflow-hidden border-y border-stone-200 bg-white">
            <button
              v-for="option in statusFilterOptions"
              :key="option.value"
              type="button"
              class="flex w-full items-center justify-between border-b border-stone-200 px-4 py-3 text-left text-sm font-medium text-stone-800 transition last:border-b-0 hover:bg-stone-50"
              @click="selectMobileStatusFilter(option.value)"
            >
              <span>{{ option.label }}</span>
              <i v-if="currentToastFilter === option.value" class="fa-solid fa-check text-amber-700" aria-hidden="true"></i>
            </button>
          </div>
        </div>
      </ModalDialog>

      <ModalDialog v-if="isMobileViewport && !isSoloWorkspace && isMobileAssigneeFilterModalOpen" max-width-class="max-w-4xl" @close="isMobileAssigneeFilterModalOpen = false">
        <ModalHeader
          title="Assignee filter"
          @close="isMobileAssigneeFilterModalOpen = false"
        />
        <div class="space-y-4 px-6 py-6">
          <div class="overflow-hidden border-y border-stone-200 bg-white">
            <button
              v-for="option in assigneeFilterOptions"
              :key="option.value"
              type="button"
              class="flex w-full items-center justify-between border-b border-stone-200 px-4 py-3 text-left text-sm font-medium text-stone-800 transition last:border-b-0 hover:bg-stone-50"
              @click="selectMobileAssigneeFilter(option.value)"
            >
              <span>{{ option.label }}</span>
              <i v-if="currentAssigneeFilter === option.value" class="fa-solid fa-check text-amber-700" aria-hidden="true"></i>
            </button>
          </div>
        </div>
      </ModalDialog>

      <ModalDialog v-if="useDedicatedMobileToastView && selectedToastModal && isMobileCommentModalOpen" max-width-class="max-w-4xl" @close="closeMobileCommentModal">
        <ModalHeader
          eyebrow="Comment"
          title="Add a comment"
          @close="closeMobileCommentModal"
        />
        <div class="space-y-4 px-6 py-6">
          <textarea
            class="min-h-36 w-full rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm leading-6 text-stone-800 transition focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-100"
            :value="commentDraftFor(selectedToastModal.id)"
            placeholder="Write your comment"
            @input="updateCommentDraft(selectedToastModal.id, $event.target.value)"
            @keydown="handleCommentDraftKeydown(selectedToastModal.id, $event)"
          />
          <div class="flex items-center justify-end gap-3">
            <button
              type="button"
              class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
              @click="closeMobileCommentModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300"
              @click="submitMobileComment"
            >
              Add comment
            </button>
          </div>
        </div>
      </ModalDialog>

      <ModalDialog v-if="useDedicatedMobileToastView && selectedToastModal && mobileVetoConfirmToastId" max-width-class="max-w-4xl" @close="closeMobileVetoConfirmModal">
        <ModalHeader
          eyebrow="Confirmation"
          title="Decline this toast?"
          description="This action will move the toast to declined."
          @close="closeMobileVetoConfirmModal"
        />
        <div class="space-y-4 px-6 py-6">
          <div class="flex items-center justify-end gap-3">
            <button
              type="button"
              class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
              @click="closeMobileVetoConfirmModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300"
              @click="confirmMobileVeto"
            >
              Decline toast
            </button>
          </div>
        </div>
      </ModalDialog>

      <ModalDialog v-if="selectedToastModal && isMoveCopyToastModalOpen" max-width-class="max-w-4xl" @close="closeMoveCopyToastModal">
        <ModalHeader
          eyebrow="Toast action"
          title="Move or copy toast"
          description="Choose another workspace, then copy this toast or transfer it there."
          @close="closeMoveCopyToastModal"
        />

        <div class="space-y-6 px-6 py-6">
          <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-5">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Target workspace</p>
            <select v-model="selectedTargetWorkspaceId" class="mt-3 w-full rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm text-stone-700">
              <option value="" disabled>Select workspace</option>
              <option v-for="candidate in otherWorkspaces" :key="candidate.id" :value="String(candidate.id)">{{ candidate.name }}</option>
            </select>
          </div>

          <div class="flex flex-wrap justify-end gap-3">
            <button
              type="button"
              class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
              @click="closeMoveCopyToastModal"
            >
              Cancel
            </button>
            <button
              type="button"
              class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
              :disabled="!selectedTargetWorkspaceId"
              @click="copyToast(Number(selectedTargetWorkspaceId))"
            >
              Copy
            </button>
            <button
              v-if="workspace.currentUserIsOwner"
              type="button"
              class="rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300 disabled:opacity-60"
              :disabled="!selectedTargetWorkspaceId"
              @click="transferToast"
            >
              Transfer
            </button>
          </div>
        </div>
      </ModalDialog>
    </template>
  </section>
</template>
