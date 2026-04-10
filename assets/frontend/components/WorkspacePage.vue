<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { WorkspacesApi } from '../api/workspaces';
import { defaultDueDateForPreset, isLateToast, renderToastDescription } from '../utils/workspaceFormatting';
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
import ToastListItem from './ToastListItem.vue';
import ToastNavigationFooter from './ToastNavigationFooter.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
  standaloneToastId: { type: [String, Number], default: null },
});

const route = useRoute();
const router = useRouter();
const payload = ref(null);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const inviteEmail = ref('');
const itemForm = ref({ title: '', description: '', ownerId: '', dueOn: '' });
const currentToastFilter = ref('active');
const currentAssigneeFilter = ref('');
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
const toastDraftRefinementBackup = ref(null);
const isSessionArchiveOpen = ref(false);
const selectedSessionArchiveId = ref(null);
const sessionArchiveError = ref('');
const sessionArchiveNotice = ref('');
const isSessionArchiveGenerating = ref(false);
const isSessionArchiveSaving = ref(false);
const isSessionArchiveSending = ref(false);
const commentDrafts = ref({});
const workspaceBackgroundObjectUrl = ref('');
const vetoedInfiniteLoader = ref(null);
const resolvedInfiniteLoader = ref(null);
let archivedToastObserver = null;
const apiClient = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});
const workspacesApi = new WorkspacesApi(apiClient);

const workspace = computed(() => payload.value?.workspace ?? null);
const currentUser = computed(() => payload.value?.currentUser ?? null);
const standaloneMode = computed(() => null !== props.standaloneToastId && '' !== String(props.standaloneToastId));
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
  { value: 'active', label: `New (${displayedAgendaItems.value.length})` },
  { value: 'discarded', label: `Declined (${displayedVetoedItems.value.length})` },
  { value: 'resolved', label: `Toasted (${displayedResolvedItems.value.length})` },
]);

const resolveToastFilter = (value) => {
  const normalizedValue = typeof value === 'string' ? value : '';

  return statusFilterOptions.value.some((option) => option.value === normalizedValue) ? normalizedValue : 'active';
};

const resolveAssigneeFilter = (value) => {
  if (isSoloWorkspace.value) {
    return '';
  }

  const normalizedValue = typeof value === 'string' ? value : '';

  return assigneeFilterOptions.value.some((option) => option.value === normalizedValue) ? normalizedValue : '';
};

const applyFiltersFromRoute = () => {
  currentToastFilter.value = resolveToastFilter(route.query.filter);
  currentAssigneeFilter.value = resolveAssigneeFilter(route.query.assignee);
};

const syncFiltersToRoute = async () => {
  const nextQuery = { ...route.query };
  const resolvedToastFilter = resolveToastFilter(currentToastFilter.value);
  const resolvedAssigneeFilter = resolveAssigneeFilter(currentAssigneeFilter.value);
  const currentFilter = typeof route.query.filter === 'string' ? route.query.filter : undefined;
  const currentAssignee = typeof route.query.assignee === 'string' ? route.query.assignee : undefined;

  if (resolvedToastFilter === 'active') {
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
const displayedAgendaItems = computed(() => agendaItems.value.filter(matchesAssignmentFilter));
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
const permalinkUrl = computed(() => selectedToastModal.value ? `/app/toasts/${selectedToastModal.value.id}` : null);
const isSoloWorkspace = computed(() => workspace.value?.isSoloWorkspace === true);
const resolvedWorkspaceBackgroundUrl = computed(() => workspaceBackgroundObjectUrl.value || workspace.value?.permalinkBackgroundUrl || '');

const standaloneBackgroundStyle = computed(() => {
  const backgroundUrl = resolvedWorkspaceBackgroundUrl.value;

  if (!standaloneMode.value || !backgroundUrl) {
    return {};
  }

  return {
    backgroundImage: `linear-gradient(rgba(28, 25, 23, 0.8), rgba(28, 25, 23, 0.1), rgba(28, 25, 23, 0.8)), url("${backgroundUrl}")`,
    backgroundSize: 'cover',
    backgroundPosition: 'center',
  };
});

const workspacePageBackgroundStyle = computed(() => {
  const backgroundUrl = resolvedWorkspaceBackgroundUrl.value;

  if (standaloneMode.value || !backgroundUrl) {
    return {};
  }

  return {
    backgroundImage: `linear-gradient(rgba(19, 36, 68, 0.44), rgba(32, 74, 135, 0.14), rgba(17, 24, 39, 0.48)), url("${backgroundUrl}")`,
    backgroundSize: 'cover',
    backgroundPosition: 'center',
    filter: 'blur(15px) saturate(0.95)',
    transform: 'scale(1.03)',
  };
});

const workspaceHeaderStats = computed(() => [
  {
    label: `${newToastCount.value} new toast${newToastCount.value > 1 ? 's' : ''}`,
    icon: 'fa-solid fa-bread-slice',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700',
  },
  {
    label: `${toastedToastCount.value} toasted toast${toastedToastCount.value > 1 ? 's' : ''}`,
    icon: 'fa-regular fa-calendar-check',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700',
  },
  {
    label: isInboxWorkspace.value ? 'inbox' : (isSoloWorkspace.value ? 'solo' : `${memberCount.value} member${memberCount.value > 1 ? 's' : ''}`),
    icon: isSoloWorkspace.value ? 'fa-regular fa-user' : 'fa-solid fa-users',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700',
  },
  ...(workspace.value?.isInboxWorkspace ? [{
    label: 'Hidden workspace',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white uppercase tracking-[0.18em] text-xs font-semibold' : 'bg-sky-100 text-sky-700 uppercase tracking-[0.18em] text-xs font-semibold',
  }] : []),
  ...(workspace.value?.isDefault ? [{
    label: 'Default workspace',
    className: resolvedWorkspaceBackgroundUrl.value ? 'bg-white/15 text-white uppercase tracking-[0.18em] text-xs font-semibold' : 'bg-amber-100 text-amber-700 uppercase tracking-[0.18em] text-xs font-semibold',
  }] : []),
]);

const workspaceHeaderActions = computed(() => {
  if (!workspace.value) {
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

const activeToastAccentClasses = (item) => {
  if (isLateToast(item)) {
    return 'border-l-4 border-l-red-600 border-t-red-200 border-r-red-200 border-b-red-200';
  }

  if (item.isBoosted) {
    return 'border-l-4 border-l-amber-500 border-t-amber-200 border-r-amber-200 border-b-amber-200';
  }

  if (item.status === 'ready') {
    return 'border-l-4 border-l-emerald-500 border-t-emerald-200 border-r-emerald-200 border-b-emerald-200';
  }

  return 'border-stone-200';
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

const createItem = async () => {
  if (!workspace.value || !itemForm.value.title.trim()) return;
  const { ok } = editingToastId.value
    ? await workspacesApi.updateToast(editingToastId.value, itemForm.value)
    : await workspacesApi.createToast(workspace.value.id, itemForm.value);

  if (!ok) {
    errorMessage.value = editingToastId.value ? 'Unable to update toast.' : 'Unable to create toast.';
    return;
  }

  resetItemForm();
  isCreateToastModalOpen.value = false;
  editingToastId.value = null;
  await fetchWorkspace();
};

const refineToastDraft = async () => {
  if (!workspace.value) {
    return;
  }

  isToastDraftRefining.value = true;
  errorMessage.value = '';

  const previousDraft = {
    title: itemForm.value.title ?? '',
    description: itemForm.value.description ?? '',
    ownerId: itemForm.value.ownerId ?? '',
    dueOn: itemForm.value.dueOn ?? '',
  };

  const { ok, data } = await workspacesApi.refineToastDraft(workspace.value.id, {
    title: previousDraft.title,
    description: previousDraft.description,
  });

  if (!ok || !data?.ok || !data.draft) {
    errorMessage.value = data?.message ?? 'Unable to improve this toast draft.';
    isToastDraftRefining.value = false;
    return;
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
  isCreateToastModalOpen.value = false;
  editingToastId.value = null;
  isToastDraftRefining.value = false;
  toastDraftRefinementBackup.value = null;
  resetItemForm();
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
    router.push(`/app/toasts/${item.id}`);
  }
};

const closeToastModal = () => {
  closeMoveCopyToastModal();

  if (standaloneMode.value) {
    window.location.href = workspaceUrl.value;
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
      router.push(`/app/toasts/${toastId}`);
      selectedToastModalId.value = toastId;
      selectedToastModalCleanState.value = serializeToastModalState(item);
      toastModalNavigationBlocked.value = false;
      return;
    }

    openToastModal(item);
  }
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

  if (!content) return;

  const { ok } = await workspacesApi.addComment(itemId, content);

  if (!ok) {
    errorMessage.value = 'Unable to add comment.';
    return;
  }

  updateCommentDraft(itemId, '');
  await fetchWorkspace();
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

const toggleVote = async (itemId) => {
  if (isToastingMode.value) return;
  await workspacesApi.toggleVote(itemId);
  await fetchWorkspace();
};

const toggleBoost = async (itemId) => {
  await workspacesApi.toggleBoost(itemId);
  await fetchWorkspace();
};

const toggleVeto = async (itemId) => {
  await workspacesApi.toggleVeto(itemId);
  await fetchWorkspace();
};

const toastItem = async (itemId) => {
  if (!isSoloWorkspace.value) return;

  const { ok } = await workspacesApi.toastItem(itemId);

  if (!ok) {
    errorMessage.value = 'Unable to toast this item.';
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
      window.location.href = `/app/toasts/${result.toastId}`;
      return;
    }
    selectedToastModalId.value = result.toastId;
    return;
  }

  window.location.href = `/app/toasts/${result.toastId}`;
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
  window.location.href = `/app/toasts/${result.toastId}`;
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

onMounted(() => {
  applyFiltersFromRoute();
  fetchWorkspace();
  window.addEventListener('keydown', handleWorkspaceKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleWorkspaceKeydown);
  disconnectArchivedToastObserver();
  revokeWorkspaceBackgroundObjectUrl();
});

watch(() => props.apiUrl, fetchWorkspace);
watch(() => workspace.value?.permalinkBackgroundUrl, loadWorkspaceBackground);
watch(() => route.query.filter, applyFiltersFromRoute);
watch(() => route.query.assignee, applyFiltersFromRoute);
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
  currentAssigneeFilter.value = resolveAssigneeFilter(currentAssigneeFilter.value);
  syncFiltersToRoute();
});
watch(assigneeFilterOptions, () => {
  currentAssigneeFilter.value = resolveAssigneeFilter(currentAssigneeFilter.value);
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
</script>

<template>
  <section class="tw-toastit-shell relative space-y-6">
    <div
      v-if="standaloneMode && workspace?.permalinkBackgroundUrl"
      class="pointer-events-none fixed inset-0 z-0"
      :style="standaloneBackgroundStyle"
    ></div>
    <div
      v-if="!standaloneMode && resolvedWorkspaceBackgroundUrl"
      class="pointer-events-none fixed inset-0 z-0"
    >
      <div class="absolute inset-0" :style="workspacePageBackgroundStyle"></div>
      <div class="absolute inset-0 bg-white/8"></div>
    </div>
    <div v-if="isLoading" class="relative z-10 tw-toastit-card p-6"><EmptyState message="Loading..." /></div>
    <div v-else-if="errorMessage" class="relative z-10 tw-toastit-card p-6 text-sm text-red-600">{{ errorMessage }}</div>
    <template v-else-if="workspace">
      <div class="relative z-10">
      <template v-if="!standaloneMode">
      <div class="relative">
        <PageHeader
          :title="workspace.name"
          :stats="workspaceHeaderStats"
          :actions="workspaceHeaderActions"
          :inverted="!!resolvedWorkspaceBackgroundUrl"
          @action="handleWorkspaceHeaderAction"
        />
      </div>

      <div class="mt-4 space-y-0">
        <div v-if="isInboxWorkspace" class="mb-4 tw-toastit-card border border-sky-100 bg-sky-50/80 p-5 text-sm text-sky-900">
          <p class="font-semibold">Email-to-toast inbox</p>
          <p class="mt-2 text-sky-800">
            Forward email to
            <span class="font-mono">{{ currentUser?.inboxEmailAddress }}</span>
            to create a new toast automatically.
          </p>
          <p class="mt-2 text-sky-700">This workspace is hidden from the regular workspace list and stays read-only from a configuration standpoint.</p>
        </div>

        <div class="tw-toastit-card p-6 space-y-4">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
              <input v-model="itemForm.title" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text" placeholder="New toast" @keydown.enter.prevent="createItem">
              <button type="button" class="inline-grid h-[3.125rem] place-items-center rounded-full border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="openCreateToastModal">
                <i class="fa-solid fa-ellipsis" aria-hidden="true"></i>
                <span class="sr-only">Open toast details</span>
              </button>
              <button class="inline-grid h-[3.125rem] place-items-center rounded-full bg-amber-500 px-5 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="createItem">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span class="sr-only">Add toast</span>
              </button>
            </div>

            <div class="flex flex-wrap items-start gap-3 pt-4">
              <div
                v-if="isSoloWorkspace"
                class="inline-flex flex-wrap items-center gap-2 rounded-full border border-stone-200 bg-white p-1"
              >
                <button
                  v-for="option in statusFilterOptions"
                  :key="option.value"
                  type="button"
                  class="rounded-full px-4 py-2 text-sm font-medium transition"
                  :class="currentToastFilter === option.value ? 'bg-amber-500 text-stone-950 shadow-sm' : 'text-stone-600 hover:bg-stone-100 hover:text-stone-950'"
                  @click="currentToastFilter = option.value"
                >
                  {{ option.label }}
                </button>
              </div>
              <CompactDropdown v-else v-model="currentToastFilter" icon="fa-solid fa-filter" :options="statusFilterOptions" />
              <CompactDropdown v-if="!isSoloWorkspace" v-model="currentAssigneeFilter" icon="fa-solid fa-user-check" :options="assigneeFilterOptions" />
            </div>

            <EmptyState v-if="currentToastFilter === 'active' && !displayedAgendaItems.length" message="No new toasts." />
            <div v-else-if="currentToastFilter === 'active'" class="space-y-3">
              <ToastListItem
                v-for="(item, index) in displayedAgendaItems"
                :key="item.id"
                variant="active"
                :title="item.title"
                :owner="item.owner"
                :due-on-display="item.dueOnDisplay"
                :author="item.author"
                :comments-count="item.comments?.length ?? 0"
                :accent-class="activeToastAccentClasses(item)"
                @open="openToastModal(item)"
              >
                <template #actions>
                  <div class="flex items-center gap-2">
                    <button
                      v-if="!isSoloWorkspace"
                      type="button"
                      class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition"
                      :class="item.currentUserHasVoted ? 'bg-amber-500 text-stone-950' : 'bg-stone-100 text-stone-700'"
                      :disabled="isToastingMode"
                      @click.stop="toggleVote(item.id)"
                    >
                      <span>{{ item.voteCount }}</span>
                      <i class="fa-solid fa-thumbs-up text-[0.7rem]" aria-hidden="true"></i>
                    </button>
                    <button
                      v-if="item.currentUserCanMarkReady"
                      type="button"
                      class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition"
                      :class="item.status === 'ready' ? 'bg-emerald-500 text-white' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'"
                      :disabled="isToastingMode"
                      @click.stop="setReady(item.id, item.status !== 'ready')"
                    >
                      <i :class="item.status === 'ready' ? 'fa-solid fa-rotate-left text-[0.7rem]' : 'fa-solid fa-check text-[0.7rem]'" aria-hidden="true"></i>
                      <span>{{ item.status === 'ready' ? 'In progress' : 'Ready' }}</span>
                    </button>
                    <button
                      v-if="workspace.currentUserIsOwner && isSoloWorkspace"
                      type="button"
                      class="inline-grid h-8 w-8 place-items-center rounded-full border border-emerald-200 bg-white text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50"
                      @click.stop="toastItem(item.id)"
                    >
                      <i class="fa-solid fa-check text-xs" aria-hidden="true"></i>
                      <span class="sr-only">Mark as toasted</span>
                    </button>
                    <template v-if="workspace.currentUserIsOwner && !isToastingMode">
                      <button
                        v-if="!isSoloWorkspace"
                        type="button"
                        class="inline-grid h-8 w-8 place-items-center rounded-full border transition"
                        :class="item.isBoosted ? 'border-amber-500 bg-amber-500 text-stone-950' : 'border-stone-200 bg-white text-stone-600 hover:border-amber-200 hover:text-amber-700'"
                        @click.stop="toggleBoost(item.id)"
                      >
                        <i class="fa-solid fa-rocket text-xs" aria-hidden="true"></i>
                        <span class="sr-only">{{ item.isBoosted ? 'Remove boost' : 'Boost' }}</span>
                      </button>
                      <button
                        type="button"
                        class="inline-grid h-8 w-8 place-items-center rounded-full border transition"
                        :class="item.status === 'discarded' ? 'border-red-300 bg-red-100 text-red-700' : 'border-stone-200 bg-white text-stone-600 hover:border-red-200 hover:text-red-700'"
                        @click.stop="toggleVeto(item.id)"
                      >
                        <i class="fa-solid fa-ban text-xs" aria-hidden="true"></i>
                        <span class="sr-only">{{ item.status === 'discarded' ? 'Restore toast' : 'Decline toast' }}</span>
                      </button>
                    </template>
                  </div>
                </template>
              </ToastListItem>
            </div>

            <div v-else-if="currentToastFilter === 'discarded' && displayedVetoedItems.length" class="space-y-3">
              <ToastListItem
                v-for="item in visibleVetoedItems"
                :key="item.id"
                variant="discarded"
                :title="item.title"
                :owner="item.owner"
                :due-on-display="item.dueOnDisplay"
                :author="item.author"
                :comments-count="item.comments?.length ?? 0"
                @open="openToastModal(item)"
              >
                <template #actions>
                  <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Declined</p>
                    <p class="mt-2 text-sm font-medium text-stone-700">{{ item.statusChangedAtDisplay }}</p>
                  </div>
                </template>
              </ToastListItem>
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
              <ToastListItem
                v-for="item in visibleResolvedItems"
                :key="item.id"
                variant="resolved"
                :title="item.title"
                :owner="item.owner"
                :due-on-display="item.dueOnDisplay"
                :author="item.author"
                :comments-count="item.comments?.length ?? 0"
                @open="openToastModal(item)"
              >
                <template #actions>
                  <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Toasted</p>
                    <p class="mt-2 text-sm font-medium text-stone-700">{{ item.statusChangedAtDisplay }}</p>
                  </div>
                </template>
              </ToastListItem>
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
                  <button type="button" class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="saveWorkspaceSettings">Save settings</button>
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
                  <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="inviteMember">Invite</button>
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

      <ModalDialog v-if="selectedToastModal" max-width-class="max-w-4xl" @close="closeToastModal">
        <div class="relative border-b border-stone-100">
          <ModalHeader eyebrow="Toast details" :title="selectedToastModal.title" @close="closeToastModal">
            <template #eyebrow>
              <ToastStatusBadge :label="displayToastStatus(selectedToastModal)" :tone-class="toastStatusTone(selectedToastModal)" />
              <a
                v-if="permalinkUrl"
                :href="permalinkUrl"
                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-600 transition hover:text-amber-700"
                title="Open standalone toast permalink"
              >
                <i class="fa-solid fa-link" aria-hidden="true"></i>
                <span>Permalink</span>
              </a>
              <a
                v-if="standaloneMode"
                :href="workspaceUrl"
                class="inline-flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.18em] text-amber-600 transition hover:text-amber-700"
              >
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                <span>{{ workspace.name }}</span>
              </a>
            </template>
            <div class="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-stone-500">
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
          <div class="absolute right-20 top-5 flex items-center gap-2">
              <button
                v-if="selectedToastModal.status === 'discarded' || selectedToastModal.status === 'toasted'"
                type="button"
                class="inline-grid h-8 w-8 place-items-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                title="Copy as new"
                @click="copyToast()"
              >
                <i class="fa-regular fa-copy text-xs" aria-hidden="true"></i>
                <span class="sr-only">Copy as new</span>
              </button>
              <button
                v-if="selectedToastModal.currentUserCanEdit && isActiveToast(selectedToastModal) && !isToastingMode"
                type="button"
                class="inline-grid h-8 w-8 place-items-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                @click="openEditToastModal(selectedToastModal)"
              >
                <i class="fa-solid fa-pen text-xs" aria-hidden="true"></i>
                <span class="sr-only">Edit toast</span>
              </button>
              <button
                v-if="isActiveToast(selectedToastModal) && otherWorkspaces.length && !isToastingMode"
                type="button"
                class="inline-grid h-8 w-8 place-items-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                @click="openMoveCopyToastModal"
              >
                <i class="fa-solid fa-right-left text-xs" aria-hidden="true"></i>
                <span class="sr-only">Move or copy toast</span>
              </button>
              <button
                v-if="!isSoloWorkspace && isActiveToast(selectedToastModal)"
                type="button"
                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition"
                :class="selectedToastModal.currentUserHasVoted ? 'bg-amber-500 text-stone-950' : 'bg-stone-100 text-stone-700'"
                :disabled="isToastingMode"
                @click="toggleVote(selectedToastModal.id)"
              >
                <span>{{ selectedToastModal.voteCount }}</span>
                <i class="fa-solid fa-thumbs-up text-[0.7rem]" aria-hidden="true"></i>
              </button>
              <button
                v-if="selectedToastModal.currentUserCanMarkReady"
                type="button"
                class="inline-flex items-center gap-2 rounded-full px-3 py-1 text-xs font-semibold transition"
                :class="selectedToastModal.status === 'ready' ? 'bg-emerald-500 text-white' : 'bg-emerald-100 text-emerald-800 hover:bg-emerald-200'"
                :disabled="isToastingMode"
                @click="setReady(selectedToastModal.id, selectedToastModal.status !== 'ready')"
              >
                <i :class="selectedToastModal.status === 'ready' ? 'fa-solid fa-rotate-left text-[0.7rem]' : 'fa-solid fa-check text-[0.7rem]'" aria-hidden="true"></i>
                <span>{{ selectedToastModal.status === 'ready' ? 'Mark in progress' : 'Mark ready' }}</span>
              </button>
              <button
                v-if="workspace.currentUserIsOwner && isSoloWorkspace && isActiveToast(selectedToastModal)"
                type="button"
                class="inline-grid h-8 w-8 place-items-center rounded-full border border-emerald-200 bg-white text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50"
                @click="toastItem(selectedToastModal.id)"
              >
                <i class="fa-solid fa-check text-xs" aria-hidden="true"></i>
                <span class="sr-only">Mark as toasted</span>
              </button>
              <template v-if="workspace.currentUserIsOwner && isActiveToast(selectedToastModal) && !isToastingMode">
                <button
                  v-if="!isSoloWorkspace"
                  type="button"
                  class="inline-grid h-8 w-8 place-items-center rounded-full border transition"
                  :class="selectedToastModal.isBoosted ? 'border-amber-500 bg-amber-500 text-stone-950' : 'border-stone-200 bg-white text-stone-600 hover:border-amber-200 hover:text-amber-700'"
                  @click="toggleBoost(selectedToastModal.id)"
                >
                  <i class="fa-solid fa-rocket text-xs" aria-hidden="true"></i>
                  <span class="sr-only">{{ selectedToastModal.isBoosted ? 'Remove boost' : 'Boost' }}</span>
                </button>
                <button
                  type="button"
                  class="inline-grid h-8 w-8 place-items-center rounded-full border transition"
                  :class="selectedToastModal.status === 'discarded' ? 'border-red-300 bg-red-100 text-red-700' : 'border-stone-200 bg-white text-stone-600 hover:border-red-200 hover:text-red-700'"
                  @click="toggleVeto(selectedToastModal.id)"
                >
                  <i class="fa-solid fa-ban text-xs" aria-hidden="true"></i>
                  <span class="sr-only">{{ selectedToastModal.status === 'discarded' ? 'Restore toast' : 'Decline toast' }}</span>
                </button>
              </template>
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
                  <button type="button" class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60" :disabled="isSaving" @click="saveDiscussion">
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
              class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60"
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
