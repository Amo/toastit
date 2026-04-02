<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { WorkspacesApi } from '../api/workspaces';
import { defaultDueDateForPreset, isLateToast, renderToastDescription, truncateDescription } from '../utils/workspaceFormatting';
import AvatarBadge from './AvatarBadge.vue';
import CommentComposer from './CommentComposer.vue';
import CommentThread from './CommentThread.vue';
import EmptyState from './EmptyState.vue';
import EyebrowLabel from './EyebrowLabel.vue';
import FollowUpEditor from './FollowUpEditor.vue';
import CreateToastModal from './CreateToastModal.vue';
import KeyboardHint from './KeyboardHint.vue';
import MemberListItem from './MemberListItem.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';
import ToastStatusBadge from './ToastStatusBadge.vue';
import ToastListItem from './ToastListItem.vue';
import ToastNavigationFooter from './ToastNavigationFooter.vue';
import WorkspaceHeader from './WorkspaceHeader.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
  standaloneToastId: { type: [String, Number], default: null },
});

const router = useRouter();
const payload = ref(null);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const inviteEmail = ref('');
const itemForm = ref({ title: '', description: '', ownerId: '', dueOn: '' });
const currentToastTab = ref('active');
const isManageModalOpen = ref(false);
const isCreateToastModalOpen = ref(false);
const createToastTitleInput = ref(null);
const workspaceBackgroundFile = ref(null);
const workspaceBackgroundInput = ref(null);
const isWorkspaceBackgroundDragOver = ref(false);
const selectedToastModalId = ref(null);
const selectedToastModalCleanState = ref(null);
const toastModalNavigationBlocked = ref(false);
const selectedTargetWorkspaceId = ref('');
const workspaceSettingsForm = ref({ name: '', defaultDuePreset: 'next_week', isSoloWorkspace: false });
const commentDrafts = ref({});
const workspaceBackgroundObjectUrl = ref('');
const apiClient = new ToastitApiClient(props.accessToken);
const workspacesApi = new WorkspacesApi(apiClient);

const workspace = computed(() => payload.value?.workspace ?? null);
const currentUser = computed(() => payload.value?.currentUser ?? null);
const standaloneMode = computed(() => null !== props.standaloneToastId && '' !== String(props.standaloneToastId));
const otherWorkspaces = computed(() => payload.value?.otherWorkspaces ?? []);
const members = computed(() => payload.value?.memberships ?? []);
const participants = computed(() => payload.value?.participants ?? []);
const agendaItems = computed(() => payload.value?.agendaItems ?? []);
const vetoedItems = computed(() => payload.value?.vetoedItems ?? []);
const resolvedItems = computed(() => payload.value?.resolvedItems ?? []);
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
const workspaceUrl = computed(() => workspace.value ? `/app/workspaces/${workspace.value.id}` : props.dashboardUrl);
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
const duePresetOptions = [
  { value: 'tomorrow', label: 'Tomorrow' },
  { value: 'next_week', label: 'Next week' },
  { value: 'in_2_weeks', label: 'In 2 weeks' },
  { value: 'next_monday', label: 'Next Monday' },
  { value: 'first_monday_next_month', label: 'First Monday next month' },
];
const displayToastStatus = (item) => {
  if (item.status === 'vetoed') return 'Declined';
  if (item.discussionStatus === 'treated') return 'Toasted';
  return 'Open';
};

const toastStatusTone = (item) => {
  if (item.status === 'vetoed') {
    return 'text-stone-400';
  }

  if (item.discussionStatus === 'treated') {
    return 'text-amber-700';
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
  const { ok } = await workspacesApi.createToast(workspace.value.id, itemForm.value);

  if (!ok) {
    errorMessage.value = 'Unable to create toast.';
    return;
  }

  resetItemForm();
  isCreateToastModalOpen.value = false;
  await fetchWorkspace();
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
  await workspacesApi.stopMeetingMode(workspace.value.id);
  await fetchWorkspace();
  isSaving.value = false;
};

const openManageModal = () => {
  isManageModalOpen.value = true;
};

const closeManageModal = () => {
  isManageModalOpen.value = false;
};

const openCreateToastModal = async () => {
  isCreateToastModalOpen.value = true;
};

const closeCreateToastModal = () => {
  isCreateToastModalOpen.value = false;
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
  if (item.status === 'vetoed') return 'Declined';
  if (item.discussionStatus === 'treated') return 'Toasted';
  return 'Active';
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

  if (agendaItems.value.some((item) => item.id === selectedToastModal.value.id)) {
    return agendaItems.value;
  }

  if (vetoedItems.value.some((item) => item.id === selectedToastModal.value.id)) {
    return vetoedItems.value;
  }

  if (resolvedItems.value.some((item) => item.id === selectedToastModal.value.id)) {
    return resolvedItems.value;
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

const copyToast = async (targetWorkspaceId = null) => {
  if (!selectedToastModal.value) return;

  const { ok, data } = await workspacesApi.copyToast(selectedToastModal.value.id, targetWorkspaceId ?? null);

  if (!ok || !data) {
    errorMessage.value = 'Unable to copy toast.';
    return;
  }
  const result = data;

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
  : [{ title: '', ownerId: null, dueOn: null }];

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
  updateItemField(itemId, 'draftFollowUps', nextDrafts.length ? nextDrafts : [{ title: '', ownerId: null, dueOn: null }]);
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
  fetchWorkspace();
  window.addEventListener('keydown', handleWorkspaceKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleWorkspaceKeydown);
  revokeWorkspaceBackgroundObjectUrl();
});

watch(() => props.apiUrl, fetchWorkspace);
watch(() => workspace.value?.permalinkBackgroundUrl, loadWorkspaceBackground);
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
        <WorkspaceHeader
          :workspace="workspace"
          :is-toasting-mode="isToastingMode"
          :is-solo-workspace="isSoloWorkspace"
          :new-toast-count="newToastCount"
          :toasted-toast-count="toastedToastCount"
          :member-count="memberCount"
          :background-visible="!!resolvedWorkspaceBackgroundUrl"
          :workspace-url="workspaceUrl"
          :dashboard-url="dashboardUrl"
          @open-manage="openManageModal"
          @start-meeting="startMeetingMode"
          @stop-meeting="stopMeetingMode"
        />
      </div>

      <div class="mt-4 space-y-0">
        <div class="tw-toastit-card p-6 space-y-4">
            <div class="grid gap-3 md:grid-cols-[minmax(0,1fr)_auto_auto]">
              <input v-model="itemForm.title" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text" placeholder="New toast" @keydown.enter.prevent="createItem">
              <button type="button" class="inline-grid h-[3.125rem] place-items-center rounded-full border border-stone-200 bg-white px-4 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="openCreateToastModal">
                <i class="fa-solid fa-gear" aria-hidden="true"></i>
                <span class="sr-only">Open toast details</span>
              </button>
              <button class="inline-grid h-[3.125rem] place-items-center rounded-full bg-amber-500 px-5 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="createItem">
                <i class="fa-solid fa-plus" aria-hidden="true"></i>
                <span class="sr-only">Add toast</span>
              </button>
            </div>

            <div class="flex flex-wrap gap-2 pt-4">
              <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="currentToastTab === 'active' ? 'bg-amber-500 text-stone-950' : 'bg-stone-100 text-stone-700 hover:bg-stone-200'"
                @click="currentToastTab = 'active'"
              >
                Active
              </button>
              <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="currentToastTab === 'vetoed' ? 'bg-amber-500 text-stone-950' : 'bg-stone-100 text-stone-700 hover:bg-stone-200'"
                @click="currentToastTab = 'vetoed'"
              >
                Declined
              </button>
              <button
                type="button"
                class="rounded-full px-4 py-2 text-sm font-semibold transition"
                :class="currentToastTab === 'resolved' ? 'bg-amber-500 text-stone-950' : 'bg-stone-100 text-stone-700 hover:bg-stone-200'"
                @click="currentToastTab = 'resolved'"
              >
                Toasted
              </button>
            </div>

            <EmptyState v-if="currentToastTab === 'active' && !agendaItems.length" message="No active toasts." />
            <div v-else-if="currentToastTab === 'active'" class="space-y-3">
              <ToastListItem
                v-for="(item, index) in agendaItems"
                :key="item.id"
                variant="active"
                :index-label="index + 1"
                :title="item.title"
                :description="item.description ? truncateDescription(item.description) : ''"
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
                        :class="item.status === 'vetoed' ? 'border-red-300 bg-red-100 text-red-700' : 'border-stone-200 bg-white text-stone-600 hover:border-red-200 hover:text-red-700'"
                        @click.stop="toggleVeto(item.id)"
                      >
                        <i class="fa-solid fa-ban text-xs" aria-hidden="true"></i>
                        <span class="sr-only">{{ item.status === 'vetoed' ? 'Restore toast' : 'Decline toast' }}</span>
                      </button>
                    </template>
                  </div>
                </template>
              </ToastListItem>
            </div>

            <div v-else-if="currentToastTab === 'vetoed' && vetoedItems.length" class="space-y-3">
              <ToastListItem
                v-for="(item, index) in vetoedItems"
                :key="item.id"
                variant="vetoed"
                :index-label="index + 1"
                :title="item.title"
                :description="item.description ? truncateDescription(item.description) : ''"
                @open="openToastModal(item)"
              >
                <template #actions>
                  <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Declined</p>
                    <p class="mt-2 text-sm font-medium text-stone-700">{{ item.statusChangedAtDisplay }}</p>
                  </div>
                </template>
              </ToastListItem>
            </div>
            <EmptyState v-else-if="currentToastTab === 'vetoed'" message="No declined toasts." />

            <div v-else-if="currentToastTab === 'resolved' && resolvedItems.length" class="space-y-3">
              <ToastListItem
                v-for="(item, index) in resolvedItems"
                :key="item.id"
                variant="resolved"
                :index-label="index + 1"
                :title="item.title"
                :description="item.description ? truncateDescription(item.description) : ''"
                @open="openToastModal(item)"
              >
                <template #actions>
                  <div class="text-right">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Toasted</p>
                    <p class="mt-2 text-sm font-medium text-stone-700">{{ item.statusChangedAtDisplay }}</p>
                  </div>
                </template>
              </ToastListItem>
            </div>
            <EmptyState v-else-if="currentToastTab === 'resolved'" message="No toasted toasts." />
        </div>
      </div>
      </template>
      </div>

      <ModalDialog v-if="isManageModalOpen" max-width-class="max-w-3xl" @close="closeManageModal">
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
                <h3 class="text-lg font-semibold text-stone-950">Members</h3>
                <p class="mt-1 text-sm leading-6 text-stone-600">Invite the right people so every toast stays in the right context, with the right collaborators.</p>
              </div>

              <div class="space-y-3 rounded-[1.25rem] border border-stone-200 bg-stone-50 p-4">
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
            </div>
          </div>
      </ModalDialog>

      <CreateToastModal
        :open="isCreateToastModalOpen"
        :item-form="itemForm"
        :participants="participants"
        @close="closeCreateToastModal"
        @create="createItem"
        @title-keydown="handleCreateToastModalKeydown"
        @update:title="itemForm.title = $event"
        @update:owner-id="itemForm.ownerId = $event"
        @update:due-on="itemForm.dueOn = $event"
        @update:description="itemForm.description = $event"
      />

      <ModalDialog v-if="selectedToastModal" max-width-class="max-w-4xl" @close="closeToastModal">
        <div class="border-b border-stone-100">
          <ModalHeader eyebrow="Toast details" :title="selectedToastModal.title" @close="closeToastModal">
            <div class="mt-3 flex flex-wrap items-center gap-3">
              <a
                v-if="permalinkUrl"
                :href="permalinkUrl"
                class="inline-flex items-center text-amber-600 transition hover:text-amber-700"
                title="Open standalone toast permalink"
              >
                <i class="fa-solid fa-link" aria-hidden="true"></i>
                <span class="sr-only">Open standalone toast permalink</span>
              </a>
              <a
                v-if="standaloneMode"
                :href="workspaceUrl"
                class="inline-flex items-center gap-2 text-amber-600 transition hover:text-amber-700"
              >
                <i class="fa-solid fa-arrow-up-right-from-square" aria-hidden="true"></i>
                <span>{{ workspace.name }}</span>
              </a>
            </div>
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
              <ToastStatusBadge :label="displayToastStatus(selectedToastModal)" :tone-class="toastStatusTone(selectedToastModal)" />
            </div>
          </ModalHeader>
          <div class="flex items-center justify-end gap-2 px-6 pb-5">
              <button
                v-if="selectedToastModal.status === 'open' && selectedToastModal.discussionStatus !== 'treated'"
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
                v-if="workspace.currentUserIsOwner && isSoloWorkspace && selectedToastModal.status === 'open' && selectedToastModal.discussionStatus !== 'treated'"
                type="button"
                class="inline-grid h-8 w-8 place-items-center rounded-full border border-emerald-200 bg-white text-emerald-700 transition hover:border-emerald-300 hover:bg-emerald-50"
                @click="toastItem(selectedToastModal.id)"
              >
                <i class="fa-solid fa-check text-xs" aria-hidden="true"></i>
                <span class="sr-only">Mark as toasted</span>
              </button>
              <template v-if="workspace.currentUserIsOwner && selectedToastModal.status === 'open' && selectedToastModal.discussionStatus !== 'treated' && !isToastingMode">
                <button
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
                  :class="selectedToastModal.status === 'vetoed' ? 'border-red-300 bg-red-100 text-red-700' : 'border-stone-200 bg-white text-stone-600 hover:border-red-200 hover:text-red-700'"
                  @click="toggleVeto(selectedToastModal.id)"
                >
                  <i class="fa-solid fa-ban text-xs" aria-hidden="true"></i>
                  <span class="sr-only">{{ selectedToastModal.status === 'vetoed' ? 'Restore toast' : 'Decline toast' }}</span>
                </button>
              </template>
            <button type="button" class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 text-stone-500 transition hover:border-stone-300 hover:text-stone-800" @click="closeToastModal">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
              <span class="sr-only">Close modal</span>
            </button>
          </div>
        </div>

          <div class="overflow-y-auto px-6 py-6">
            <div class="space-y-6">
              <div>
                <p v-if="selectedToastModal.description" class="text-lg leading-8 text-stone-700" v-html="renderToastDescription(selectedToastModal.description)"></p>
                <p v-else class="text-lg text-stone-500">No description</p>
              </div>

              <section v-if="selectedToastModal.status === 'open' && selectedToastModal.discussionStatus !== 'treated' && otherWorkspaces.length && !isToastingMode" class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Move or copy</p>
                <div class="flex flex-wrap items-center gap-3 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4">
                  <select v-model="selectedTargetWorkspaceId" class="min-w-[14rem] rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm text-stone-700">
                    <option value="" disabled>Select workspace</option>
                    <option v-for="candidate in otherWorkspaces" :key="candidate.id" :value="String(candidate.id)">{{ candidate.name }}</option>
                  </select>
                  <button type="button" class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" :disabled="!selectedTargetWorkspaceId" @click="copyToast(Number(selectedTargetWorkspaceId))">
                    Copy
                  </button>
                  <button
                    v-if="workspace.currentUserIsOwner"
                    type="button"
                    class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
                    :disabled="!selectedTargetWorkspaceId"
                    @click="transferToast"
                  >
                    Transfer
                  </button>
                </div>
              </section>

              <section v-if="selectedToastModal.status === 'vetoed' || selectedToastModal.discussionStatus === 'treated'" class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Action</p>
                <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50 p-4">
                  <button type="button" class="rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="copyToast()">
                    Copy as new
                  </button>
                </div>
              </section>

              <div v-if="selectedToastModal.previousItem" class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Created from</p>
                <button type="button" class="mt-2 text-left text-sm text-stone-800 transition hover:text-amber-700" @click="openToastById(selectedToastModal.previousItem.id)">
                  <strong>{{ selectedToastModal.previousItem.title }}</strong>
                  <span class="font-semibold" :class="toastStatusTone(selectedToastModal.previousItem)"> · {{ relatedToastStatusLabel(selectedToastModal.previousItem) }}</span>
                </button>
              </div>

              <div v-if="workspace.currentUserIsOwner && isToastingMode && selectedToastModal.status === 'open' && selectedToastModal.discussionStatus !== 'treated'" class="space-y-4">
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
                  @add="addFollowUpDraft(selectedToastModal.id)"
                  @remove="removeFollowUpDraft(selectedToastModal.id, $event)"
                  @update="updateFollowUpDraft(selectedToastModal.id, $event.index, $event.key, $event.value)"
                />

                <div class="flex justify-end">
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

              <section v-if="selectedToastModal.discussionNotes && (!isToastingMode || selectedToastModal.discussionStatus === 'treated' || !workspace.currentUserIsOwner)" class="space-y-3">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Decision</p>
                <div class="rounded-[1.5rem] border border-amber-200 bg-amber-50 px-5 py-4">
                  <p class="text-base leading-7 text-stone-800" v-html="renderToastDescription(selectedToastModal.discussionNotes)"></p>
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
                    v-if="selectedToastModal.status === 'open' && selectedToastModal.discussionStatus !== 'treated'"
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
    </template>
  </section>
</template>
