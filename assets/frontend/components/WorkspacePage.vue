<script setup>
import { computed, nextTick, onMounted, onUnmounted, ref, watch } from 'vue';
import { useRouter } from 'vue-router';
import AvatarBadge from './AvatarBadge.vue';
import ModalDialog from './ModalDialog.vue';

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
const selectedToastModalId = ref(null);
const selectedTargetWorkspaceId = ref('');
const workspaceSettingsForm = ref({ name: '', defaultDuePreset: 'next_week', permalinkBackgroundUrl: '', isSoloWorkspace: false });
const commentDrafts = ref({});

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
const standaloneBackgroundStyle = computed(() => {
  const backgroundUrl = workspace.value?.permalinkBackgroundUrl;

  if (!standaloneMode.value || !backgroundUrl) {
    return {};
  }

  return {
    backgroundImage: `linear-gradient(rgba(28, 25, 23, 0.8), rgba(28, 25, 23, 0.1), rgba(28, 25, 23, 0.8)), url("${backgroundUrl}")`,
    backgroundSize: 'cover',
    backgroundPosition: 'center',
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

const todayDateString = () => {
  const now = new Date();
  const year = now.getFullYear();
  const month = String(now.getMonth() + 1).padStart(2, '0');
  const day = String(now.getDate()).padStart(2, '0');

  return `${year}-${month}-${day}`;
};

const isLateToast = (item) => !!item?.dueOn && item.dueOn < todayDateString();

const activeToastAccentClasses = (item) => {
  if (isLateToast(item)) {
    return 'border-l-4 border-l-red-600 border-t-red-200 border-r-red-200 border-b-red-200';
  }

  if (item.isBoosted) {
    return 'border-l-4 border-l-amber-500 border-t-amber-200 border-r-amber-200 border-b-amber-200';
  }

  return 'border-stone-200';
};

const escapeHtml = (value) => value
  .replaceAll('&', '&amp;')
  .replaceAll('<', '&lt;')
  .replaceAll('>', '&gt;')
  .replaceAll('"', '&quot;')
  .replaceAll("'", '&#39;');

const renderToastDescription = (value) => {
  if (!value) return '';

  let html = escapeHtml(value);
  html = html.replace(/\[([^\]]+)\]\((https?:\/\/[^\s)]+)\)/g, '<a href="$2" target="_blank" rel="noopener noreferrer" class="font-medium text-amber-700 underline">$1</a>');
  html = html.replace(/(^|[\s(>])((https?:\/\/|www\.)[^\s<]+)/g, (match, prefix, url) => {
    if (prefix.includes('href=')) {
      return match;
    }

    const href = url.startsWith('www.') ? `https://${url}` : url;

    return `${prefix}<a href="${href}" target="_blank" rel="noopener noreferrer" class="font-medium text-amber-700 underline">${url}</a>`;
  });
  html = html.replace(/\*\*([^*]+)\*\*/g, '<strong>$1</strong>');
  html = html.replace(/(^|[^*])\*([^*]+)\*/g, '$1<em>$2</em>');
  html = html.replace(/`([^`]+)`/g, '<code class="rounded bg-stone-100 px-1 py-0.5 text-[0.85em] text-stone-800">$1</code>');
  html = html.replace(/\n/g, '<br>');

  return html;
};

const truncateDescription = (value, limit = 140) => {
  if (!value) return '';

  const singleLineValue = value.replace(/\s+/g, ' ').trim();
  if (singleLineValue.length <= limit) {
    return singleLineValue;
  }

  return `${singleLineValue.slice(0, limit - 1).trimEnd()}…`;
};

const toDateInputValue = (date) => {
  const year = date.getFullYear();
  const month = String(date.getMonth() + 1).padStart(2, '0');
  const day = String(date.getDate()).padStart(2, '0');
  return `${year}-${month}-${day}`;
};

const nextMondayFrom = (date) => {
  const next = new Date(date);
  const day = next.getDay();
  const daysUntilMonday = ((8 - day) % 7) || 7;
  next.setDate(next.getDate() + daysUntilMonday);
  return next;
};

const defaultDueDateForPreset = (preset) => {
  const base = new Date();
  base.setHours(12, 0, 0, 0);

  switch (preset) {
    case 'tomorrow':
      base.setDate(base.getDate() + 1);
      return toDateInputValue(base);
    case 'in_2_weeks':
      base.setDate(base.getDate() + 14);
      return toDateInputValue(base);
    case 'next_monday':
      return toDateInputValue(nextMondayFrom(base));
    case 'first_monday_next_month': {
      const nextMonth = new Date(base.getFullYear(), base.getMonth() + 1, 1, 12, 0, 0, 0);
      const firstMonday = nextMondayFrom(new Date(nextMonth.getTime() - 24 * 60 * 60 * 1000));
      return toDateInputValue(firstMonday);
    }
    case 'next_week':
    default:
      base.setDate(base.getDate() + 7);
      return toDateInputValue(base);
  }
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

  const response = await fetch(props.apiUrl, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });

  if (!response.ok) {
    payload.value = null;
    errorMessage.value = 'Unable to load workspace.';
    isLoading.value = false;
    return;
  }

  payload.value = await response.json();
  workspaceSettingsForm.value = {
    name: payload.value.workspace?.name ?? '',
    defaultDuePreset: payload.value.workspace?.defaultDuePreset ?? 'next_week',
    permalinkBackgroundUrl: payload.value.workspace?.permalinkBackgroundUrl ?? '',
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

const authorizedHeaders = (json = false) => ({
  Accept: 'application/json',
  Authorization: `Bearer ${props.accessToken}`,
  ...(json ? { 'Content-Type': 'application/json' } : {}),
});

const inviteMember = async () => {
  if (!payload.value || !inviteEmail.value.trim()) return;
  await fetch(`/api/workspaces/${workspace.value.id}/invite`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify({ email: inviteEmail.value }),
  });
  inviteEmail.value = '';
  await fetchWorkspace();
};

const removeMember = async (memberId) => {
  if (!workspace.value) return;
  await fetch(`/api/workspaces/${workspace.value.id}/members/${memberId}`, {
    method: 'DELETE',
    headers: authorizedHeaders(),
  });
  await fetchWorkspace();
};

const createItem = async () => {
  if (!workspace.value || !itemForm.value.title.trim()) return;
  const response = await fetch(`/api/workspaces/${workspace.value.id}/items`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify(itemForm.value),
  });

  if (!response.ok) {
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
  await fetch(`/api/workspaces/${workspace.value.id}/meeting/start`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });
  await fetchWorkspace();
  openTopActiveToast();
  isSaving.value = false;
};

const stopMeetingMode = async () => {
  if (!workspace.value?.currentUserIsOwner) return;
  isSaving.value = true;
  await fetch(`/api/workspaces/${workspace.value.id}/meeting/stop`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });
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
  await nextTick();
  createToastTitleInput.value?.focus();
};

const closeCreateToastModal = () => {
  isCreateToastModalOpen.value = false;
};

const openToastModal = (item) => {
  selectedToastModalId.value = item.id;
  selectedTargetWorkspaceId.value = otherWorkspaces.value[0]?.id ? String(otherWorkspaces.value[0].id) : '';

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
      return;
    }

    openToastModal(item);
  }
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

  const response = await fetch(`/api/items/${itemId}/comments`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify({ content }),
  });

  if (!response.ok) {
    errorMessage.value = 'Unable to add comment.';
    return;
  }

  updateCommentDraft(itemId, '');
  await fetchWorkspace();
};

const saveWorkspaceSettings = async () => {
  if (!workspace.value?.currentUserIsOwner) return;

  const response = await fetch(`/api/workspaces/${workspace.value.id}/settings`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify(workspaceSettingsForm.value),
  });

  if (!response.ok) {
    errorMessage.value = 'Unable to save workspace settings.';
    return;
  }

  closeManageModal();
  await fetchWorkspace();
};

const promoteMember = async (memberId) => {
  if (!workspace.value?.currentUserIsOwner) return;
  const response = await fetch(`/api/workspaces/${workspace.value.id}/members/${memberId}/promote`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });

  if (!response.ok) {
    errorMessage.value = 'Unable to promote member.';
    return;
  }

  await fetchWorkspace();
};

const demoteMember = async (memberId) => {
  if (!workspace.value?.currentUserIsOwner) return;
  const response = await fetch(`/api/workspaces/${workspace.value.id}/members/${memberId}/demote`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });

  if (!response.ok) {
    errorMessage.value = 'Unable to demote owner.';
    return;
  }

  await fetchWorkspace();
};

const toggleVote = async (itemId) => {
  if (isToastingMode.value) return;
  await fetch(`/api/items/${itemId}/vote`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });
  await fetchWorkspace();
};

const toggleBoost = async (itemId) => {
  await fetch(`/app/items/${itemId}/boost`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });
  await fetchWorkspace();
};

const toggleVeto = async (itemId) => {
  await fetch(`/app/items/${itemId}/veto`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });
  await fetchWorkspace();
};

const toastItem = async (itemId) => {
  if (!isSoloWorkspace.value) return;

  const response = await fetch(`/api/items/${itemId}/toast`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });

  if (!response.ok) {
    errorMessage.value = 'Unable to toast this item.';
    return;
  }

  await fetchWorkspace();
};

const copyToast = async (targetWorkspaceId = null) => {
  if (!selectedToastModal.value) return;

  const response = await fetch(`/api/items/${selectedToastModal.value.id}/copy`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify({
      targetWorkspaceId: targetWorkspaceId ?? null,
    }),
  });

  if (!response.ok) {
    errorMessage.value = 'Unable to copy toast.';
    return;
  }

  const result = await response.json();

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

  const response = await fetch(`/api/items/${selectedToastModal.value.id}/transfer`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify({
      targetWorkspaceId: Number(selectedTargetWorkspaceId.value),
    }),
  });

  if (!response.ok) {
    errorMessage.value = 'Unable to transfer toast.';
    return;
  }

  const result = await response.json();
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

  const response = await fetch(`/api/items/${selectedToastModal.value.id}/discussion`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify({
      discussionNotes: selectedToastModal.value.discussionNotes,
      ownerId: selectedToastModal.value.owner?.id ?? null,
      dueOn: selectedToastModal.value.dueOn,
      followUpItems: ensureDraftFollowUps(selectedToastModal.value),
    }),
  });

  if (!response.ok) {
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
});

watch(() => props.apiUrl, fetchWorkspace);
</script>

<template>
  <section class="tw-toastit-shell relative space-y-6">
    <div
      v-if="standaloneMode && workspace?.permalinkBackgroundUrl"
      class="pointer-events-none fixed inset-0 z-0"
      :style="standaloneBackgroundStyle"
    ></div>
    <div v-if="isLoading" class="relative z-10 tw-toastit-card p-6 text-sm text-stone-500">Loading...</div>
    <div v-else-if="errorMessage" class="relative z-10 tw-toastit-card p-6 text-sm text-red-600">{{ errorMessage }}</div>
    <template v-else-if="workspace">
      <div class="relative z-10">
      <template v-if="!standaloneMode">
      <div class="space-y-2">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600" aria-label="Breadcrumb">
          <a :href="dashboardUrl" class="transition hover:text-amber-700">Home</a>
          <span class="text-amber-300">/</span>
          <span class="text-amber-700">{{ workspace.name }}</span>
        </nav>
        <div class="flex items-start justify-between gap-4">
          <div>
            <div class="flex flex-wrap items-center gap-3">
              <h1 class="inline-flex items-center gap-3 text-4xl font-semibold tracking-tight text-stone-950">
                <i v-if="isToastingMode && !isSoloWorkspace" class="fa-solid fa-gear animate-spin text-amber-600 [animation-duration:4s]" aria-hidden="true"></i>
                <span>{{ workspace.name }}</span>
              </h1>
              <span v-if="workspace.isDefault" class="rounded-full bg-amber-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Default workspace</span>
              <span v-if="isSoloWorkspace" class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold uppercase tracking-[0.18em] text-stone-700">Solo workspace</span>
            </div>
            <div class="mt-3 flex flex-wrap gap-3 text-sm text-stone-500">
              <span class="rounded-full bg-stone-100 px-3 py-1 font-medium text-stone-700">{{ newToastCount }} new toast<span v-if="newToastCount > 1">s</span></span>
              <span class="rounded-full bg-stone-100 px-3 py-1 font-medium text-stone-700">{{ toastedToastCount }} toasted toast<span v-if="toastedToastCount > 1">s</span></span>
              <span class="rounded-full bg-stone-100 px-3 py-1 font-medium text-stone-700">{{ memberCount }} member<span v-if="memberCount > 1">s</span></span>
            </div>
            <p v-if="workspace.isDefault" class="mt-2 text-sm text-stone-500">This workspace is created automatically for every user and stays available as the permanent default.</p>
          </div>
          <div v-if="workspace.currentUserIsOwner" class="flex flex-wrap items-center justify-end gap-3">
            <button
              type="button"
              class="inline-grid h-12 w-12 place-items-center rounded-full border border-stone-200 bg-white text-stone-700 shadow-sm transition hover:border-stone-300 hover:text-stone-950"
              @click="openManageModal"
            >
              <i class="fa-solid fa-gear" aria-hidden="true"></i>
              <span class="sr-only">Manage workspace</span>
            </button>
            <button
              v-if="!isSoloWorkspace"
              type="button"
              class="inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold shadow-sm transition disabled:opacity-60"
              :class="workspace.meetingMode === 'live' ? 'bg-stone-900 text-white hover:bg-stone-800' : 'bg-amber-500 text-stone-950 hover:bg-amber-400'"
              :disabled="isSaving"
              @click="workspace.meetingMode === 'live' ? stopMeetingMode() : startMeetingMode()"
            >
              <i v-if="workspace.meetingMode !== 'live'" class="fa-solid fa-bolt" aria-hidden="true"></i>
              <span>{{ workspace.meetingMode === 'live' ? 'Stop toasting mode' : 'Start toasting mode' }}</span>
            </button>
          </div>
        </div>
      </div>

      <div class="space-y-6">
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

            <div v-if="currentToastTab === 'active' && !agendaItems.length" class="text-sm text-stone-500">No active toasts.</div>
            <div v-else-if="currentToastTab === 'active'" class="space-y-3">
              <article
                v-for="(item, index) in agendaItems"
                :key="item.id"
                class="overflow-hidden rounded-[1.35rem] border bg-white transition"
                :class="[
                  activeToastAccentClasses(item),
                  'opacity-95 hover:shadow-toastit-panel',
                ]"
              >
                <button type="button" class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left" @click="openToastModal(item)">
                  <div class="space-y-2">
                    <div class="flex items-center gap-3">
                      <span
                        class="inline-grid h-8 w-8 place-items-center rounded-full font-semibold"
                        :class="isLateToast(item) ? 'bg-red-600 text-white' : 'bg-amber-100 text-amber-700'"
                      >
                        {{ index + 1 }}
                      </span>
                      <p class="text-lg font-semibold text-stone-950">{{ item.title }}</p>
                    </div>
                    <p v-if="item.description" class="text-sm text-stone-500">{{ truncateDescription(item.description) }}</p>
                  </div>
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
                </button>
              </article>
            </div>

            <div v-else-if="currentToastTab === 'vetoed' && vetoedItems.length" class="space-y-3">
              <article
                v-for="(item, index) in vetoedItems"
                :key="item.id"
                class="overflow-hidden rounded-[1.35rem] border border-stone-200 bg-white opacity-95 transition hover:shadow-toastit-panel"
              >
                <button type="button" class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left" @click="openToastModal(item)">
                  <div class="min-w-0 space-y-2">
                    <div class="flex items-center gap-3">
                      <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-stone-100 font-semibold text-stone-600">{{ index + 1 }}</span>
                      <p class="truncate text-lg font-semibold text-stone-950">{{ item.title }}</p>
                    </div>
                    <p v-if="item.description" class="text-sm text-stone-500">{{ truncateDescription(item.description) }}</p>
                  </div>
                  <div class="shrink-0 text-right">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Declined</p>
                    <p class="mt-2 text-sm font-medium text-stone-700">{{ item.statusChangedAtDisplay }}</p>
                  </div>
                </button>
              </article>
            </div>
            <div v-else-if="currentToastTab === 'vetoed'" class="text-sm text-stone-500">No declined toasts.</div>

            <div v-else-if="currentToastTab === 'resolved' && resolvedItems.length" class="space-y-3">
              <article
                v-for="(item, index) in resolvedItems"
                :key="item.id"
                class="overflow-hidden rounded-[1.35rem] border border-stone-200 bg-white opacity-95 transition hover:shadow-toastit-panel"
              >
                <button type="button" class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left" @click="openToastModal(item)">
                  <div class="min-w-0 space-y-2">
                    <div class="flex items-center gap-3">
                      <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-stone-100 font-semibold text-stone-600">{{ index + 1 }}</span>
                      <p class="truncate text-lg font-semibold text-stone-950">{{ item.title }}</p>
                    </div>
                    <p v-if="item.description" class="text-sm text-stone-500">{{ truncateDescription(item.description) }}</p>
                  </div>
                  <div class="shrink-0 text-right">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Toasted</p>
                    <p class="mt-2 text-sm font-medium text-stone-700">{{ item.statusChangedAtDisplay }}</p>
                  </div>
                </button>
              </article>
            </div>
            <div v-else-if="currentToastTab === 'resolved'" class="text-sm text-stone-500">No toasted toasts.</div>
        </div>
      </div>
      </template>
      </div>

      <ModalDialog v-if="isManageModalOpen" max-width-class="max-w-3xl" @close="closeManageModal">
          <div class="flex items-start justify-between gap-4 border-b border-stone-100 px-6 py-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Workspace settings</p>
              <h2 class="mt-2 text-2xl font-semibold text-stone-950">{{ workspace.name }}</h2>
              <p class="mt-2 text-sm text-stone-500">Manage members and review this workspace without leaving the toast board.</p>
            </div>
            <button type="button" class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 text-stone-500 transition hover:border-stone-300 hover:text-stone-800" @click="closeManageModal">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
              <span class="sr-only">Close modal</span>
            </button>
          </div>

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
                <label class="grid gap-2 text-sm font-medium text-stone-700">
                  <span>Permalink background image URL</span>
                  <input v-model="workspaceSettingsForm.permalinkBackgroundUrl" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" type="url" placeholder="https://example.com/background.jpg">
                </label>
                <label class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm font-medium text-stone-700">
                  <span>Solo workspace</span>
                  <input v-model="workspaceSettingsForm.isSoloWorkspace" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-amber-500 focus:ring-amber-400">
                </label>
                <div class="flex justify-end">
                  <button type="button" class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="saveWorkspaceSettings">Save settings</button>
                </div>
              </div>

              <div class="space-y-3">
                <div v-for="membership in members" :key="membership.id" class="flex items-center justify-between gap-4 rounded-xl border border-stone-200 px-4 py-3">
                  <div class="flex items-center gap-3">
                    <AvatarBadge
                      :seed="membership.user.id"
                      :initials="membership.user.initials"
                      :gravatar-url="membership.user.gravatarUrl"
                      :alt="membership.user.displayName"
                    />
                    <div>
                      <p class="font-medium text-stone-900">{{ membership.user.displayName }}</p>
                      <p class="text-sm text-stone-500">{{ membership.user.email }}</p>
                    </div>
                  </div>
                  <div class="flex flex-wrap items-center justify-end gap-2">
                    <span v-if="membership.isOwner" class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">Owner</span>
                    <template v-if="workspace.currentUserIsOwner">
                      <button
                        v-if="!membership.isOwner"
                        type="button"
                        class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
                        @click="promoteMember(membership.id)"
                      >
                        Promote
                      </button>
                      <button
                        v-else-if="ownerCount > 1"
                        type="button"
                        class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
                        @click="demoteMember(membership.id)"
                      >
                        Demote
                      </button>
                      <button
                        v-if="!membership.isOwner || ownerCount > 1"
                        type="button"
                        class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
                        @click="removeMember(membership.id)"
                      >
                        Remove
                      </button>
                    </template>
                  </div>
                </div>
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

      <ModalDialog v-if="isCreateToastModalOpen" max-width-class="max-w-2xl" @close="closeCreateToastModal">
          <div class="flex items-start justify-between gap-4 border-b border-stone-100 px-6 py-5">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">New toast</p>
              <h2 class="mt-2 text-2xl font-semibold text-stone-950">Toast details</h2>
            </div>
            <button type="button" class="inline-grid h-10 w-10 place-items-center rounded-full border border-stone-200 text-stone-500 transition hover:border-stone-300 hover:text-stone-800" @click="closeCreateToastModal">
              <i class="fa-solid fa-xmark" aria-hidden="true"></i>
              <span class="sr-only">Close modal</span>
            </button>
          </div>

          <div class="space-y-4 overflow-y-auto px-6 py-6" @keydown="handleCreateToastModalKeydown">
            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Title</span>
              <input ref="createToastTitleInput" v-model="itemForm.title" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text" placeholder="New toast">
            </label>

            <div class="grid gap-4 md:grid-cols-2">
              <label class="grid gap-2 text-sm font-medium text-stone-700">
                <span>Assignee</span>
                <select v-model="itemForm.ownerId" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm">
                  <option value="">Unassigned</option>
                  <option v-for="invitee in participants" :key="invitee.id" :value="String(invitee.id)">{{ invitee.displayName }}</option>
                </select>
              </label>
              <label class="grid gap-2 text-sm font-medium text-stone-700">
                <span>Date</span>
                <input v-model="itemForm.dueOn" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" type="date">
              </label>
            </div>

            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Details</span>
              <textarea v-model="itemForm.description" class="min-h-32 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" placeholder="Add details or description" />
            </label>

            <div class="flex items-center justify-between gap-3">
              <p class="text-xs text-stone-400">Press Cmd+Enter or Ctrl+Enter to create this toast.</p>
              <div class="flex justify-end gap-3">
                <button type="button" class="rounded-full border border-stone-200 bg-white px-5 py-3 text-sm font-semibold text-stone-700 transition hover:border-stone-300 hover:text-stone-950" @click="closeCreateToastModal">Cancel</button>
                <button type="button" class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="createItem">Create toast</button>
              </div>
            </div>
          </div>
      </ModalDialog>

      <ModalDialog v-if="selectedToastModal" max-width-class="max-w-4xl" @close="closeToastModal">
          <div class="flex items-start justify-between gap-6 border-b border-stone-100 px-6 py-5">
            <div class="min-w-0 space-y-3">
              <div class="flex flex-wrap items-center gap-3 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">
                <span>Toast details</span>
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
              <h2 class="mt-2 text-2xl font-semibold text-stone-950">{{ selectedToastModal.title }}</h2>
              <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm text-stone-500">
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
                <span class="inline-flex items-center gap-2">
                  <i class="fa-regular fa-circle-dot" :class="toastStatusTone(selectedToastModal)" aria-hidden="true"></i>
                  <span class="font-semibold" :class="toastStatusTone(selectedToastModal)">{{ displayToastStatus(selectedToastModal) }}</span>
                </span>
              </div>
            </div>
            <div class="flex items-center gap-2">
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

              <section v-if="selectedToastModal.status === 'open' && selectedToastModal.discussionStatus !== 'treated' && otherWorkspaces.length" class="space-y-3">
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
                  <textarea class="min-h-28 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="selectedToastModal.discussionNotes ?? ''" @input="updateItemField(selectedToastModal.id, 'discussionNotes', $event.target.value)" />
                </label>

                <div class="space-y-3">
                  <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-medium text-stone-700">Create follow-ups in this workspace</p>
                    <button type="button" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="addFollowUpDraft(selectedToastModal.id)">Add</button>
                  </div>

                  <div v-for="(followUp, followUpIndex) in ensureDraftFollowUps(selectedToastModal)" :key="followUpIndex" class="grid gap-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 xl:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)_11rem_auto]">
                    <input class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" type="text" :value="followUp.title ?? ''" placeholder="Follow-up title" @input="updateFollowUpDraft(selectedToastModal.id, followUpIndex, 'title', $event.target.value)">
                    <select class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" :value="followUp.ownerId ?? ''" @change="updateFollowUpDraft(selectedToastModal.id, followUpIndex, 'ownerId', $event.target.value)">
                      <option value="">Assignee</option>
                      <option v-for="invitee in participants" :key="invitee.id" :value="invitee.id">{{ invitee.displayName }}</option>
                    </select>
                    <input class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" type="date" :value="followUp.dueOn ?? ''" @input="updateFollowUpDraft(selectedToastModal.id, followUpIndex, 'dueOn', $event.target.value)">
                    <div class="flex items-end justify-end">
                      <button type="button" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="removeFollowUpDraft(selectedToastModal.id, followUpIndex)">Remove</button>
                    </div>
                  </div>
                </div>

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
                  <div v-if="selectedToastModal.comments?.length" class="space-y-3">
                    <div v-for="comment in selectedToastModal.comments" :key="comment.id" class="flex items-start gap-3">
                      <AvatarBadge
                        :seed="comment.author.id"
                        :initials="comment.author.initials"
                        :gravatar-url="comment.author.gravatarUrl"
                        :alt="comment.author.displayName"
                      />
                      <div class="min-w-0 flex-1 space-y-2">
                        <div class="rounded-2xl bg-stone-50 px-4 py-3">
                          <p class="text-sm leading-7 text-stone-700" v-html="renderToastDescription(comment.content)"></p>
                        </div>
                        <div class="px-1 text-xs text-stone-500">
                          {{ comment.author.displayName }} · {{ comment.createdAtDisplay }}
                        </div>
                      </div>
                    </div>
                  </div>
                  <p v-else class="text-sm text-stone-500">No comments.</p>

                  <div v-if="selectedToastModal.status === 'open' && selectedToastModal.discussionStatus !== 'treated'" class="mt-4 flex items-end gap-3 border-t border-stone-100 pt-4">
                    <AvatarBadge
                      :seed="currentUser?.id"
                      :initials="currentUser?.initials"
                      :gravatar-url="currentUser?.gravatarUrl"
                      :alt="currentUser?.displayName"
                    />
                    <textarea
                      class="min-h-[2.75rem] min-w-0 flex-1 resize-none overflow-hidden rounded-[1.4rem] border border-stone-200 bg-white px-4 py-3 text-sm leading-6"
                      :value="commentDraftFor(selectedToastModal.id)"
                      rows="1"
                      placeholder="Write a comment"
                      @input="handleCommentDraftInput(selectedToastModal.id, $event)"
                      @keydown="handleCommentDraftKeydown(selectedToastModal.id, $event)"
                    ></textarea>
                    <button type="button" class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950 transition hover:bg-amber-400" @click="createComment(selectedToastModal.id)">
                      Send
                    </button>
                  </div>
                </div>
              </section>
            </div>
          </div>
      </ModalDialog>
    </template>
  </section>
</template>
