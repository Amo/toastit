<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { authState, authStore } from '../authStore';
import { ProfileApi } from '../api/profile';
import AvatarBadge from './AvatarBadge.vue';
import EmptyState from './EmptyState.vue';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';
import PageHero from './PageHero.vue';
import PrimaryActionButton from './PrimaryActionButton.vue';
import SegmentedCodeInput from './SegmentedCodeInput.vue';
import SecondaryActionButton from './SecondaryActionButton.vue';
import TextInputField from './TextInputField.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  updateUrl: { type: String, required: true },
  deleteUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
  publicApiDocUrl: { type: String, default: '/doc' },
});

const isLoading = ref(true);
const isSaving = ref(false);
const isPreferencesSaving = ref(false);
const isUploadingAvatar = ref(false);
const isDeleting = ref(false);
const isRequestingDeletionOtp = ref(false);
const deleteModalOpen = ref(false);
const deleteConfirmation = ref('');
const deleteOtp = ref('');
const deletionOtpSent = ref(false);
const deleteErrorMessage = ref('');
const isRestoringWorkspaceId = ref(null);
const restoreErrorMessage = ref('');
const avatarErrorMessage = ref('');
const preferencesErrorMessage = ref('');
const highlightedPreferenceKey = ref('');
const highlightedPreferenceState = ref('');
const personalTokens = ref([]);
const isLoadingPersonalTokens = ref(false);
const isCreatingPersonalToken = ref(false);
const isRevokingPersonalTokenId = ref(null);
const personalTokenErrorMessage = ref('');
const newPersonalTokenName = ref('');
const newPersonalTokenExpiresAt = ref('');
const newlyCreatedPersonalToken = ref('');
const isCopyingPersonalToken = ref(false);
const profile = ref({
  displayName: '',
  firstName: '',
  lastName: '',
  inboundRewordLanguage: 'auto',
  inboundRewordLanguageChoices: [],
  timezone: 'auto',
  timezoneChoices: [],
  inboundAiAutoApply: {
    reword: true,
    assignee: true,
    dueDate: true,
    workspace: true,
  },
  deletedWorkspaces: [],
});
const apiClient = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});
const route = useRoute();
const router = useRouter();
const profileApi = new ProfileApi(apiClient);
const MIN_AVATAR_SIZE = 64;
const MAX_AVATAR_SIZE = 256;
const profileSections = [
  { key: 'infos', label: 'Infos' },
  { key: 'preferences', label: 'Preferences' },
  { key: 'api', label: 'API tokens' },
  { key: 'trash', label: 'Trash' },
  { key: 'account', label: 'Account' },
];

const normalizeProfileSection = (value) => (
  profileSections.some((section) => section.key === value) ? value : 'infos'
);

const profileMenuItems = [
  { label: 'Infos', to: '/app/profile?section=infos' },
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

const shouldForceProfileMenuFromQuery = computed(() => {
  const menuQuery = typeof route.query.menu === 'string' ? route.query.menu.toLowerCase() : '';
  return ['1', 'true', 'on'].includes(menuQuery);
});

const currentProfileSection = computed(() => {
  const sectionQuery = typeof route.query.section === 'string' ? route.query.section : '';
  if (isMobileViewport.value && !sectionQuery) {
    return 'menu';
  }

  return normalizeProfileSection(sectionQuery || 'infos');
});

const currentProfileSectionLabel = computed(() => (
  profileSections.find((section) => section.key === currentProfileSection.value)?.label ?? 'My profile'
));

const isMobileViewport = ref(false);

const syncViewport = () => {
  isMobileViewport.value = window.innerWidth < 1024;
};

const isMobileProfileMenu = computed(() => {
  if (!isMobileViewport.value) {
    return false;
  }

  if (String(route.name ?? '') !== 'profile') {
    return false;
  }

  if (shouldForceProfileMenuFromQuery.value) {
    return true;
  }

  return currentProfileSection.value === 'menu';
});

const canAccessAdminSection = computed(() => {
  if (profile.value?.isRoot === true) {
    return true;
  }

  return authState.user?.isRoot === true;
});

const goBackFromProfileSection = () => {
  router.push('/app/profile');
};

const openProfileMenuItem = (target) => {
  router.push(target);
};

const currentProfileSectionDescription = computed(() => {
  if (currentProfileSection.value === 'preferences') {
    return 'Configure how inbound email suggestions from xAI are applied automatically.';
  }

  if (currentProfileSection.value === 'trash') {
    return 'Review and restore your deleted workspaces.';
  }

  if (currentProfileSection.value === 'api') {
    return 'Create and revoke personal access tokens for the public API.';
  }

  if (currentProfileSection.value === 'account') {
    return 'Manage irreversible account-level actions.';
  }

  return 'Set your first and last name, avatar, and inbound email address.';
});

const inboundRewordLanguageLabel = computed(() => {
  const selectedCode = profile.value.inboundRewordLanguage ?? 'auto';
  const choices = Array.isArray(profile.value.inboundRewordLanguageChoices)
    ? profile.value.inboundRewordLanguageChoices
    : [];

  const matchedChoice = choices.find((choice) => choice.code === selectedCode);
  if (!matchedChoice) {
    return 'Auto (match email language)';
  }

  return matchedChoice.label;
});

const inboundRewordLanguageBadgeLabel = computed(() => {
  if ((profile.value.inboundRewordLanguage ?? 'auto') === 'auto') {
    return 'Auto (detected)';
  }

  return `Forced: ${inboundRewordLanguageLabel.value}`;
});

let preferencesSaveTimer = null;
let preferencesHighlightTimer = null;
let preferencesSaveSequence = 0;

const preferenceRowClass = (preferenceKey) => {
  if (highlightedPreferenceKey.value !== preferenceKey) {
    return 'border-stone-200 bg-white';
  }

  if (highlightedPreferenceState.value === 'pending') {
    return 'border-amber-300 bg-amber-50';
  }

  if (highlightedPreferenceState.value === 'saved') {
    return 'border-emerald-300 bg-emerald-50';
  }

  if (highlightedPreferenceState.value === 'error') {
    return 'border-rose-300 bg-rose-50';
  }

  return 'border-stone-200 bg-white';
};

const clearPreferenceHighlight = () => {
  if (preferencesHighlightTimer) {
    window.clearTimeout(preferencesHighlightTimer);
    preferencesHighlightTimer = null;
  }

  highlightedPreferenceKey.value = '';
  highlightedPreferenceState.value = '';
};

const saveInboundPreferences = async (preferenceKey) => {
  isPreferencesSaving.value = true;
  preferencesErrorMessage.value = '';
  highlightedPreferenceKey.value = preferenceKey;
  highlightedPreferenceState.value = 'pending';

  const currentSequence = ++preferencesSaveSequence;
  const { ok, data } = await profileApi.saveProfile(props.updateUrl, {
    inboundAiAutoApply: profile.value.inboundAiAutoApply,
    inboundRewordLanguage: profile.value.inboundRewordLanguage,
    timezone: profile.value.timezone,
  });

  if (currentSequence !== preferencesSaveSequence) {
    return;
  }

  isPreferencesSaving.value = false;

  if (!ok || !data?.user?.inboundAiAutoApply) {
    highlightedPreferenceState.value = 'error';
    preferencesErrorMessage.value = 'Unable to save preferences.';
    return;
  }

  profile.value.inboundAiAutoApply = {
    reword: data.user.inboundAiAutoApply.reword ?? profile.value.inboundAiAutoApply.reword,
    assignee: data.user.inboundAiAutoApply.assignee ?? profile.value.inboundAiAutoApply.assignee,
    dueDate: data.user.inboundAiAutoApply.dueDate ?? profile.value.inboundAiAutoApply.dueDate,
    workspace: data.user.inboundAiAutoApply.workspace ?? profile.value.inboundAiAutoApply.workspace,
  };
  profile.value.inboundRewordLanguage = data.user.inboundRewordLanguage ?? profile.value.inboundRewordLanguage;
  profile.value.inboundRewordLanguageChoices = data.user.inboundRewordLanguageChoices ?? profile.value.inboundRewordLanguageChoices;
  profile.value.timezone = data.user.timezone ?? profile.value.timezone;
  profile.value.timezoneChoices = data.user.timezoneChoices ?? profile.value.timezoneChoices;

  highlightedPreferenceState.value = 'saved';
  if (preferencesHighlightTimer) {
    window.clearTimeout(preferencesHighlightTimer);
  }
  preferencesHighlightTimer = window.setTimeout(() => {
    clearPreferenceHighlight();
  }, 1400);
};

const onPreferenceToggle = (preferenceKey) => {
  if (preferencesSaveTimer) {
    window.clearTimeout(preferencesSaveTimer);
  }

  preferencesSaveTimer = window.setTimeout(() => {
    saveInboundPreferences(preferenceKey);
  }, 180);
};

const loadImageElement = (file) => {
  const objectUrl = URL.createObjectURL(file);

  return new Promise((resolve, reject) => {
    const image = new Image();

    image.onload = () => resolve({ image, objectUrl });
    image.onerror = () => {
      URL.revokeObjectURL(objectUrl);
      reject(new Error('Unable to read image.'));
    };
    image.src = objectUrl;
  });
};

const canvasToBlob = (canvas, mimeType) => new Promise((resolve, reject) => {
  canvas.toBlob((blob) => {
    if (!blob) {
      reject(new Error('Unable to process image.'));
      return;
    }

    resolve(blob);
  }, mimeType);
});

const buildProcessedAvatarFile = async (file) => {
  const { image, objectUrl } = await loadImageElement(file);

  try {
    const cropSize = Math.min(image.naturalWidth, image.naturalHeight);
    const sourceX = Math.floor((image.naturalWidth - cropSize) / 2);
    const sourceY = Math.floor((image.naturalHeight - cropSize) / 2);
    const targetSize = Math.min(MAX_AVATAR_SIZE, Math.max(MIN_AVATAR_SIZE, cropSize));
    const canvas = document.createElement('canvas');
    canvas.width = targetSize;
    canvas.height = targetSize;

    const context = canvas.getContext('2d');

    if (!context) {
      throw new Error('Unable to process image.');
    }

    context.imageSmoothingEnabled = true;
    context.imageSmoothingQuality = 'high';
    context.drawImage(
      image,
      sourceX,
      sourceY,
      cropSize,
      cropSize,
      0,
      0,
      targetSize,
      targetSize,
    );

    const outputMimeType = ['image/jpeg', 'image/png', 'image/webp'].includes(file.type)
      ? file.type
      : 'image/png';
    const outputExtension = outputMimeType === 'image/jpeg'
      ? 'jpg'
      : outputMimeType === 'image/webp'
        ? 'webp'
        : 'png';
    const blob = await canvasToBlob(canvas, outputMimeType);

    return new File([blob], `avatar.${outputExtension}`, { type: outputMimeType });
  } finally {
    URL.revokeObjectURL(objectUrl);
  }
};

const formatDateTime = (value) => {
  if (!value) {
    return 'Never';
  }

  const parsedDate = new Date(value);
  if (Number.isNaN(parsedDate.getTime())) {
    return value;
  }

  return new Intl.DateTimeFormat(undefined, {
    year: 'numeric',
    month: 'short',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit',
  }).format(parsedDate);
};

const buildExpiresAtPayload = (localDateTime) => {
  if (!localDateTime) {
    return null;
  }

  const parsedDate = new Date(localDateTime);
  if (Number.isNaN(parsedDate.getTime())) {
    return null;
  }

  return parsedDate.toISOString();
};

const fetchPersonalTokens = async () => {
  isLoadingPersonalTokens.value = true;
  personalTokenErrorMessage.value = '';

  const { ok, data } = await profileApi.listPersonalTokens('/api/profile/personal-tokens');
  isLoadingPersonalTokens.value = false;

  if (!ok || !Array.isArray(data?.tokens)) {
    personalTokenErrorMessage.value = 'Unable to load personal access tokens.';
    return;
  }

  personalTokens.value = data.tokens;
};

const fetchProfile = async () => {
  isLoading.value = true;
  const { ok, data } = await profileApi.getProfile(props.apiUrl);

  if (ok && data) {
    const inboundAiAutoApply = {
      reword: data.user?.inboundAiAutoApply?.reword ?? true,
      assignee: data.user?.inboundAiAutoApply?.assignee ?? true,
      dueDate: data.user?.inboundAiAutoApply?.dueDate ?? true,
      workspace: data.user?.inboundAiAutoApply?.workspace ?? true,
    };

    profile.value = {
      ...data.user,
      inboundRewordLanguage: data.user?.inboundRewordLanguage ?? 'auto',
      inboundRewordLanguageChoices: data.user?.inboundRewordLanguageChoices ?? [],
      timezone: data.user?.timezone ?? 'auto',
      timezoneChoices: data.user?.timezoneChoices ?? [],
      inboundAiAutoApply,
      deletedWorkspaces: data.deletedWorkspaces ?? [],
    };
  }

  isLoading.value = false;
  await fetchPersonalTokens();
};

const saveProfile = async () => {
  isSaving.value = true;
  await profileApi.saveProfile(props.updateUrl, {
    firstName: profile.value.firstName,
    lastName: profile.value.lastName,
  });
  isSaving.value = false;
  await fetchProfile();
};

const uploadAvatar = async (event) => {
  const [file] = event.target.files ?? [];
  event.target.value = '';
  avatarErrorMessage.value = '';

  if (!file) {
    return;
  }

  isUploadingAvatar.value = true;

  let processedFile;

  try {
    processedFile = await buildProcessedAvatarFile(file);
  } catch (error) {
    isUploadingAvatar.value = false;
    avatarErrorMessage.value = error instanceof Error ? error.message : 'Unable to process image.';
    return;
  }

  const formData = new FormData();
  formData.append('avatar', processedFile);

  const { ok, data } = await profileApi.uploadAvatar(`${props.updateUrl}/avatar`, formData);
  isUploadingAvatar.value = false;

  if (!ok || !data?.ok) {
    avatarErrorMessage.value = data?.message ?? 'Unable to upload avatar.';
    return;
  }

  await fetchProfile();
};

const deleteAccount = async () => {
  isDeleting.value = true;
  deleteErrorMessage.value = '';

  const { ok, data } = await profileApi.deleteProfile(props.deleteUrl, {
    confirmation: deleteConfirmation.value,
    otp: deleteOtp.value,
  });

  isDeleting.value = false;

  if (!ok || !data?.ok) {
    deleteErrorMessage.value = data?.error === 'invalid_confirmation'
      ? 'Type DELETE exactly to confirm.'
      : 'Unable to delete your account.';
    return;
  }

  authStore.logout();
  window.location.href = '/';
};

const requestDeletionOtp = async () => {
  isRequestingDeletionOtp.value = true;
  deleteErrorMessage.value = '';
  const { ok } = await profileApi.requestDeletionOtp(`${props.deleteUrl}/delete-request`);
  isRequestingDeletionOtp.value = false;

  if (!ok) {
    deleteErrorMessage.value = 'Unable to send the confirmation code.';
    return;
  }

  deletionOtpSent.value = true;
};

const openDeleteModal = () => {
  deleteConfirmation.value = '';
  deleteOtp.value = '';
  deletionOtpSent.value = false;
  deleteErrorMessage.value = '';
  deleteModalOpen.value = true;
};

const restoreWorkspace = async (workspaceId) => {
  isRestoringWorkspaceId.value = workspaceId;
  restoreErrorMessage.value = '';

  const { ok } = await apiClient.request(`/api/workspaces/${workspaceId}/restore`, { method: 'POST' })
    .then((response) => apiClient.parseJsonResponse(response));

  isRestoringWorkspaceId.value = null;

  if (!ok) {
    restoreErrorMessage.value = 'Unable to restore this workspace.';
    return;
  }

  await fetchProfile();
};

const createPersonalToken = async () => {
  const name = newPersonalTokenName.value.trim();
  if (!name) {
    personalTokenErrorMessage.value = 'Token title is required.';
    return;
  }

  const expiresAt = buildExpiresAtPayload(newPersonalTokenExpiresAt.value.trim());
  if (newPersonalTokenExpiresAt.value.trim() && !expiresAt) {
    personalTokenErrorMessage.value = 'Expiration date is invalid.';
    return;
  }

  isCreatingPersonalToken.value = true;
  personalTokenErrorMessage.value = '';
  newlyCreatedPersonalToken.value = '';

  const payload = { name };
  if (expiresAt) {
    payload.expiresAt = expiresAt;
  }

  const { ok, data } = await profileApi.createPersonalToken('/api/profile/personal-tokens', payload);
  isCreatingPersonalToken.value = false;

  if (!ok || !data?.token?.plainTextToken) {
    personalTokenErrorMessage.value = 'Unable to create personal access token.';
    return;
  }

  newlyCreatedPersonalToken.value = data.token.plainTextToken;
  newPersonalTokenName.value = '';
  newPersonalTokenExpiresAt.value = '';
  await fetchPersonalTokens();
};

const revokePersonalToken = async (tokenId) => {
  isRevokingPersonalTokenId.value = tokenId;
  personalTokenErrorMessage.value = '';

  const { ok } = await profileApi.revokePersonalToken(`/api/profile/personal-tokens/${tokenId}`);
  isRevokingPersonalTokenId.value = null;

  if (!ok) {
    personalTokenErrorMessage.value = 'Unable to revoke this token.';
    return;
  }

  if (newlyCreatedPersonalToken.value) {
    newlyCreatedPersonalToken.value = '';
  }

  await fetchPersonalTokens();
};

const copyNewPersonalToken = async () => {
  if (!newlyCreatedPersonalToken.value || !navigator?.clipboard?.writeText) {
    return;
  }

  isCopyingPersonalToken.value = true;
  try {
    await navigator.clipboard.writeText(newlyCreatedPersonalToken.value);
  } finally {
    isCopyingPersonalToken.value = false;
  }
};

onMounted(() => {
  syncViewport();
  fetchProfile();
  window.addEventListener('resize', syncViewport);
});
onUnmounted(() => {
  if (preferencesSaveTimer) {
    window.clearTimeout(preferencesSaveTimer);
    preferencesSaveTimer = null;
  }

  clearPreferenceHighlight();
  window.removeEventListener('resize', syncViewport);
});
</script>

<template>
  <section :class="isMobileViewport && !isMobileProfileMenu ? 'space-y-0' : 'space-y-6'">
    <div
      v-if="isMobileViewport && !isMobileProfileMenu"
      class="sticky top-0 z-40 border-b border-stone-200/80 bg-white/95 px-3 pb-3 backdrop-blur"
      :style="{ paddingTop: 'calc(0.5rem + env(safe-area-inset-top))' }"
    >
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="inline-grid h-9 w-9 shrink-0 place-items-center rounded-full border border-stone-200 bg-white text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
          @click="goBackFromProfileSection"
        >
          <i class="fa-solid fa-arrow-left text-sm" aria-hidden="true"></i>
          <span class="sr-only">Back</span>
        </button>
        <h1 class="line-clamp-2 text-xl font-semibold tracking-tight text-stone-950">
          {{ currentProfileSectionLabel }}
        </h1>
      </div>
    </div>

    <PageHero
      v-if="!isMobileViewport"
      eyebrow="Profile"
      :title="profile.displayName || 'My profile'"
      :description="currentProfileSectionDescription"
    />

    <div :class="isMobileViewport && isMobileProfileMenu ? 'tw-toastit-card p-6' : (isMobileViewport ? '' : 'tw-toastit-card p-6')">
      <EmptyState v-if="isLoading" message="Loading..." />
      <div v-else :class="isMobileViewport ? 'space-y-6' : 'space-y-8'">
          <template v-if="isMobileProfileMenu">
            <div class="space-y-5">
              <div class="sticky top-0 z-20 -mx-6 -mt-1 mb-2 bg-white/95 px-6 py-2 backdrop-blur">
                <h2 class="text-2xl font-semibold tracking-tight text-stone-950">My profile.</h2>
              </div>

              <section class="space-y-2">
                <p class="px-4 text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">My profile</p>
                <div class="-mx-6 overflow-hidden border-y border-stone-200 bg-white">
                  <button
                    v-for="item in profileMenuItems"
                    :key="item.to"
                    type="button"
                    class="flex w-full items-center justify-between border-b border-stone-200 px-6 py-3 text-left text-sm font-medium text-stone-800 transition last:border-b-0 hover:bg-stone-50"
                    @click="openProfileMenuItem(item.to)"
                  >
                    <span>{{ item.label }}</span>
                    <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
                  </button>
                </div>
              </section>

              <section v-if="canAccessAdminSection" class="space-y-2">
                <p class="px-4 text-xs font-semibold uppercase tracking-[0.14em] text-stone-500">Administration</p>
                <div class="-mx-6 overflow-hidden border-y border-stone-200 bg-white">
                  <button
                    v-for="item in adminMenuItems"
                    :key="item.to"
                    type="button"
                    class="flex w-full items-center justify-between border-b border-stone-200 px-6 py-3 text-left text-sm font-medium text-stone-800 transition last:border-b-0 hover:bg-stone-50"
                    @click="openProfileMenuItem(item.to)"
                  >
                    <span>{{ item.label }}</span>
                    <i class="fa-solid fa-chevron-right text-xs text-stone-400" aria-hidden="true"></i>
                  </button>
                </div>
              </section>
            </div>
          </template>

          <template v-else>
          <template v-if="currentProfileSection === 'infos'">
            <div :class="isMobileViewport ? 'space-y-4 border-b border-stone-200 bg-white px-5 py-4' : 'space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5'">
              <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-4">
                  <AvatarBadge
                    :seed="profile.id ?? profile.displayName"
                    :initials="profile.initials"
                    :gravatar-url="profile.gravatarUrl"
                    :alt="profile.displayName"
                    :title="profile.displayName || profile.email || ''"
                    size-class="h-16 w-16 text-lg"
                  />
                  <div>
                    <h3 class="text-base font-semibold text-stone-950">Avatar</h3>
                    <p class="mt-1 text-sm text-stone-600">The image is cropped to a centered square and resampled to stay between 64x64 and 256x256 pixels.</p>
                  </div>
                </div>

                <label class="inline-flex cursor-pointer items-center justify-center rounded-full bg-amber-200 px-5 py-3 text-sm font-semibold text-amber-900 shadow-sm transition hover:bg-amber-300">
                  {{ isUploadingAvatar ? 'Uploading...' : 'Upload avatar' }}
                  <input
                    class="sr-only"
                    type="file"
                    accept="image/png,image/jpeg,image/webp,image/gif"
                    :disabled="isUploadingAvatar"
                    @change="uploadAvatar"
                  >
                </label>
              </div>

              <p v-if="avatarErrorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ avatarErrorMessage }}</p>
            </div>

            <div :class="isMobileViewport ? 'space-y-4 px-5' : 'space-y-4'">
              <TextInputField v-model="profile.firstName" label="First name" />
              <TextInputField v-model="profile.lastName" label="Last name" />
              <PrimaryActionButton :disabled="isSaving" @click="saveProfile">
                {{ isSaving ? 'Saving...' : 'Save' }}
              </PrimaryActionButton>
            </div>

            <div v-if="profile.inboxEmailAddress" :class="isMobileViewport ? 'border-y border-amber-200 bg-amber-50/70 px-4 py-4' : 'rounded-[1.5rem] border border-amber-200 bg-amber-50/70 p-5'">
              <div class="space-y-3">
                <div>
                  <h3 class="text-base font-semibold text-amber-950">Inbound email</h3>
                  <p class="mt-1 text-sm text-amber-900">
                    Send an email to this address to create a new toast automatically in your hidden Inbox workspace.
                  </p>
                </div>

                <div class="rounded-2xl border border-amber-200 bg-white px-4 py-3">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Your Toastit inbox address</p>
                  <p class="mt-2 break-all font-mono text-sm text-amber-950">{{ profile.inboxEmailAddress }}</p>
                </div>
              </div>
            </div>
          </template>

          <template v-if="currentProfileSection === 'preferences'">
            <div :class="isMobileViewport ? 'border-b border-stone-200 bg-white px-4 py-4' : 'rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5'">
              <h3 class="text-base font-semibold text-stone-950">Inbound xAI auto-apply</h3>
              <p class="mt-1 text-sm text-stone-600">
                Choose which xAI suggestions are automatically applied when a new toast is created from inbound email.
              </p>
              <div class="mt-3 inline-flex items-center rounded-full border border-amber-200 bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-800">
                Reword language: {{ inboundRewordLanguageBadgeLabel }}
              </div>
              <p v-if="preferencesErrorMessage" class="mt-3 rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ preferencesErrorMessage }}</p>
              <p v-else-if="isPreferencesSaving" class="mt-3 text-sm font-medium text-amber-700">Saving preferences...</p>

              <div class="mt-4 space-y-3">
                <label
                  class="flex items-center justify-between gap-4 rounded-2xl border px-4 py-3 transition"
                  :class="preferenceRowClass('reword')"
                >
                  <span class="flex items-center gap-2 text-sm font-medium text-stone-900">
                    <span>Reword title and description</span>
                    <span v-if="highlightedPreferenceKey === 'reword' && highlightedPreferenceState === 'saved'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Saved</span>
                  </span>
                  <input v-model="profile.inboundAiAutoApply.reword" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500" @change="onPreferenceToggle('reword')">
                </label>
                <label
                  class="flex items-center justify-between gap-4 rounded-2xl border px-4 py-3 transition"
                  :class="preferenceRowClass('assignee')"
                >
                  <span class="flex items-center gap-2 text-sm font-medium text-stone-900">
                    <span>Apply suggested assignee</span>
                    <span v-if="highlightedPreferenceKey === 'assignee' && highlightedPreferenceState === 'saved'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Saved</span>
                  </span>
                  <input v-model="profile.inboundAiAutoApply.assignee" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500" @change="onPreferenceToggle('assignee')">
                </label>
                <label
                  class="flex items-center justify-between gap-4 rounded-2xl border px-4 py-3 transition"
                  :class="preferenceRowClass('dueDate')"
                >
                  <span class="flex items-center gap-2 text-sm font-medium text-stone-900">
                    <span>Apply suggested due date</span>
                    <span v-if="highlightedPreferenceKey === 'dueDate' && highlightedPreferenceState === 'saved'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Saved</span>
                  </span>
                  <input v-model="profile.inboundAiAutoApply.dueDate" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500" @change="onPreferenceToggle('dueDate')">
                </label>
                <label
                  class="flex items-center justify-between gap-4 rounded-2xl border px-4 py-3 transition"
                  :class="preferenceRowClass('workspace')"
                >
                  <span class="flex items-center gap-2 text-sm font-medium text-stone-900">
                    <span>Apply suggested workspace</span>
                    <span v-if="highlightedPreferenceKey === 'workspace' && highlightedPreferenceState === 'saved'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Saved</span>
                  </span>
                  <input v-model="profile.inboundAiAutoApply.workspace" type="checkbox" class="h-4 w-4 rounded border-stone-300 text-amber-500 focus:ring-amber-500" @change="onPreferenceToggle('workspace')">
                </label>

                <label
                  :class="[
                    'rounded-2xl border px-4 py-3 transition',
                    isMobileViewport ? 'flex flex-col items-start gap-2' : 'flex items-center justify-between gap-4',
                    preferenceRowClass('language'),
                  ]"
                >
                  <span class="flex items-center gap-2 text-sm font-medium text-stone-900">
                    <span>Reword language</span>
                    <span v-if="highlightedPreferenceKey === 'language' && highlightedPreferenceState === 'saved'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Saved</span>
                  </span>
                  <select
                    v-model="profile.inboundRewordLanguage"
                    class="max-w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                    :class="isMobileViewport ? 'w-full' : ''"
                    @change="onPreferenceToggle('language')"
                  >
                    <option
                      v-for="choice in profile.inboundRewordLanguageChoices"
                      :key="choice.code"
                      :value="choice.code"
                    >
                      {{ choice.label }}
                    </option>
                  </select>
                </label>

                <label
                  :class="[
                    'rounded-2xl border px-4 py-3 transition',
                    isMobileViewport ? 'flex flex-col items-start gap-2' : 'flex items-center justify-between gap-4',
                    preferenceRowClass('timezone'),
                  ]"
                >
                  <span class="flex items-center gap-2 text-sm font-medium text-stone-900">
                    <span>Timezone</span>
                    <span v-if="highlightedPreferenceKey === 'timezone' && highlightedPreferenceState === 'saved'" class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-semibold text-emerald-700">Saved</span>
                  </span>
                  <select
                    v-model="profile.timezone"
                    class="max-w-full rounded-xl border border-stone-300 bg-white px-3 py-2 text-sm text-stone-900 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
                    :class="isMobileViewport ? 'w-full' : ''"
                    @change="onPreferenceToggle('timezone')"
                  >
                    <option
                      v-for="choice in profile.timezoneChoices"
                      :key="choice.code"
                      :value="choice.code"
                    >
                      {{ choice.label }}
                    </option>
                  </select>
                </label>
              </div>
            </div>
          </template>

          <template v-if="currentProfileSection === 'api'">
            <div :class="isMobileViewport ? 'space-y-4 border-b border-stone-200 bg-white px-4 py-4' : 'space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5'">
              <div>
                <h3 class="text-base font-semibold text-stone-950">Personal access tokens</h3>
                <p class="mt-1 text-sm text-stone-600">
                  Use these tokens to call the public API with the <code class="rounded bg-stone-100 px-1 py-0.5 text-xs">Authorization: Bearer ...</code> header.
                </p>
                <div class="mt-3 flex flex-col gap-2">
                  <a
                    :href="publicApiDocUrl"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 text-sm font-medium text-amber-700 transition hover:text-amber-800"
                  >
                    <i class="fa-solid fa-arrow-up-right-from-square text-xs" aria-hidden="true"></i>
                    Open public API documentation
                  </a>
                  <a
                    href="https://github.com/Amo/toastit-mcp"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="inline-flex items-center gap-2 text-sm font-medium text-amber-700 transition hover:text-amber-800"
                  >
                    <i class="fa-brands fa-github text-sm" aria-hidden="true"></i>
                    Open MCP server code (GitHub)
                  </a>
                </div>
              </div>

              <div class="grid gap-4 rounded-2xl border border-stone-200 bg-white p-4">
                <TextInputField
                  v-model="newPersonalTokenName"
                  label="Token title"
                  placeholder="CI integration"
                />
                <TextInputField
                  v-model="newPersonalTokenExpiresAt"
                  type="datetime-local"
                  label="Expiration date (optional)"
                />
                <div class="flex justify-end">
                  <PrimaryActionButton :disabled="isCreatingPersonalToken" @click="createPersonalToken">
                    {{ isCreatingPersonalToken ? 'Creating...' : 'Create token' }}
                  </PrimaryActionButton>
                </div>
              </div>

              <div v-if="newlyCreatedPersonalToken" class="rounded-2xl border border-amber-200 bg-amber-50 p-4">
                <p class="text-sm font-semibold text-amber-900">Copy this token now: it will not be shown again.</p>
                <p class="mt-2 break-all rounded-xl border border-amber-200 bg-white px-3 py-2 font-mono text-xs text-amber-950">{{ newlyCreatedPersonalToken }}</p>
                <div class="mt-3 flex justify-end gap-2">
                  <button
                    type="button"
                    class="rounded-full border border-amber-300 bg-white px-4 py-2 text-sm font-semibold text-amber-800 transition hover:bg-amber-100 disabled:opacity-60"
                    :disabled="isCopyingPersonalToken"
                    @click="copyNewPersonalToken"
                  >
                    {{ isCopyingPersonalToken ? 'Copying...' : 'Copy token' }}
                  </button>
                  <button
                    type="button"
                    class="rounded-full border border-stone-300 bg-white px-4 py-2 text-sm font-semibold text-stone-700 transition hover:bg-stone-100"
                    @click="newlyCreatedPersonalToken = ''"
                  >
                    Hide
                  </button>
                </div>
              </div>

              <p v-if="personalTokenErrorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ personalTokenErrorMessage }}</p>

              <EmptyState v-if="isLoadingPersonalTokens" message="Loading tokens..." />
              <div v-else-if="personalTokens.length" class="space-y-3">
                <div
                  v-for="token in personalTokens"
                  :key="token.id"
                  class="flex flex-col gap-3 rounded-2xl border border-stone-200 bg-white px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
                >
                  <div class="min-w-0 space-y-1">
                    <p class="font-medium text-stone-950">{{ token.name }}</p>
                    <p class="text-xs text-stone-500">Created: {{ formatDateTime(token.createdAt) }}</p>
                    <p class="text-xs text-stone-500">Last used: {{ formatDateTime(token.lastUsedAt) }}</p>
                    <p class="text-xs text-stone-500">Expires: {{ formatDateTime(token.expiresAt) }}</p>
                    <p v-if="token.revokedAt" class="text-xs font-semibold text-rose-700">Revoked: {{ formatDateTime(token.revokedAt) }}</p>
                  </div>
                  <SecondaryActionButton
                    :disabled="Boolean(token.revokedAt) || isRevokingPersonalTokenId === token.id"
                    @click="revokePersonalToken(token.id)"
                  >
                    {{ token.revokedAt ? 'Revoked' : (isRevokingPersonalTokenId === token.id ? 'Revoking...' : 'Revoke') }}
                  </SecondaryActionButton>
                </div>
              </div>
              <EmptyState v-else message="No personal access tokens yet." />
            </div>
          </template>

          <template v-if="currentProfileSection === 'trash'">
            <div :class="isMobileViewport ? 'space-y-4 border-b border-stone-200 bg-white px-4 py-4' : 'space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-5'">
              <div>
                <h3 class="text-base font-semibold text-stone-950">Deleted workspaces</h3>
                <p class="mt-1 text-sm text-stone-600">Only owners can see and restore deleted workspaces.</p>
              </div>

              <p v-if="restoreErrorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ restoreErrorMessage }}</p>

              <div v-if="profile.deletedWorkspaces?.length" class="space-y-3">
                <div
                  v-for="deletedWorkspace in profile.deletedWorkspaces"
                  :key="deletedWorkspace.id"
                  class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-white px-4 py-4"
                >
                  <div class="min-w-0">
                    <p class="font-medium text-stone-950">{{ deletedWorkspace.name }}</p>
                    <p class="mt-1 text-sm text-stone-500">Deleted on {{ deletedWorkspace.deletedAtDisplay }}</p>
                  </div>
                  <SecondaryActionButton :disabled="isRestoringWorkspaceId === deletedWorkspace.id" @click="restoreWorkspace(deletedWorkspace.id)">
                    {{ isRestoringWorkspaceId === deletedWorkspace.id ? 'Restoring...' : 'Restore' }}
                  </SecondaryActionButton>
                </div>
              </div>

              <EmptyState v-else message="No deleted workspaces." />
            </div>
          </template>

          <template v-if="currentProfileSection === 'account'">
            <div :class="isMobileViewport ? 'border-b border-rose-200 bg-rose-50/60 px-4 py-4' : 'rounded-[1.5rem] border border-rose-200 bg-rose-50/60 p-5'">
              <div class="space-y-3">
                <div>
                  <h3 class="text-base font-semibold text-rose-900">Delete my account</h3>
                  <p class="mt-1 text-sm text-rose-800">
                    This permanently disables your account and cannot be recovered.
                  </p>
                </div>
                <SecondaryActionButton @click="openDeleteModal">Delete my account</SecondaryActionButton>
              </div>
            </div>
          </template>
          </template>
      </div>
    </div>

    <ModalDialog v-if="deleteModalOpen" max-width-class="max-w-4xl" @close="deleteModalOpen = false">
      <ModalHeader
        eyebrow="Danger zone"
        title="Delete your account"
        description="This action is permanent. No recovery can be done."
        @close="deleteModalOpen = false"
      />

      <div class="space-y-6 px-6 py-6">
        <div class="space-y-3 text-sm text-stone-700">
          <p>Your account will be permanently deleted.</p>
          <p>If a shared workspace has no owner left, ownership will be transferred to the first remaining member.</p>
          <p>Your name will appear as <strong>Deleted user</strong> in existing toasts and comments.</p>
          <p>A confirmation code will be sent to <strong>{{ profile.email }}</strong>.</p>
        </div>

        <TextInputField
          v-model="deleteConfirmation"
          label='Type "DELETE" to confirm'
          placeholder="DELETE"
        />

        <div class="space-y-3">
          <div class="flex items-center justify-between gap-3">
            <p class="text-sm font-medium text-stone-700">Email confirmation code</p>
            <button type="button" class="text-sm font-medium text-amber-700 transition hover:text-amber-800 disabled:opacity-60" :disabled="isRequestingDeletionOtp" @click="requestDeletionOtp">
              {{ deletionOtpSent ? 'Resend code' : (isRequestingDeletionOtp ? 'Sending...' : 'Send code') }}
            </button>
          </div>
          <SegmentedCodeInput
            v-model="deleteOtp"
            :length="6"
            autocomplete="one-time-code"
            input-type="tel"
            input-mode="numeric"
            :pattern="/[0-9]/"
          />
        </div>

        <p v-if="deleteErrorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ deleteErrorMessage }}</p>

        <div class="flex items-center justify-end gap-3">
          <SecondaryActionButton @click="deleteModalOpen = false">Cancel</SecondaryActionButton>
          <button
            type="button"
            class="rounded-full bg-rose-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-rose-500 disabled:opacity-60"
            :disabled="isDeleting || deleteConfirmation !== 'DELETE' || deleteOtp.length !== 6"
            @click="deleteAccount"
          >
            {{ isDeleting ? 'Deleting...' : 'Delete my account' }}
          </button>
        </div>
      </div>
    </ModalDialog>
  </section>
</template>
