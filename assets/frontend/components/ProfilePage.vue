<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { authStore } from '../authStore';
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
const profile = ref({
  displayName: '',
  firstName: '',
  lastName: '',
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
  { key: 'trash', label: 'Trash' },
  { key: 'account', label: 'Account' },
];

const normalizeProfileSection = (value) => (
  profileSections.some((section) => section.key === value) ? value : 'infos'
);

const currentProfileSection = computed(() => normalizeProfileSection(
  typeof route.query.section === 'string' ? route.query.section : 'infos',
));

const currentProfileSectionDescription = computed(() => {
  if (currentProfileSection.value === 'preferences') {
    return 'Configure how inbound email suggestions from xAI are applied automatically.';
  }

  if (currentProfileSection.value === 'trash') {
    return 'Review and restore your deleted workspaces.';
  }

  if (currentProfileSection.value === 'account') {
    return 'Manage irreversible account-level actions.';
  }

  return 'Set your first and last name, avatar, and inbound email address.';
});

const goToProfileSection = async (sectionKey) => {
  const nextSection = normalizeProfileSection(sectionKey);
  const nextQuery = { ...route.query };

  if (nextSection === 'infos') {
    delete nextQuery.section;
  } else {
    nextQuery.section = nextSection;
  }

  await router.replace({ query: nextQuery });
};
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
      inboundAiAutoApply,
      deletedWorkspaces: data.deletedWorkspaces ?? [],
    };
  }

  isLoading.value = false;
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

onMounted(fetchProfile);
onUnmounted(() => {
  if (preferencesSaveTimer) {
    window.clearTimeout(preferencesSaveTimer);
    preferencesSaveTimer = null;
  }

  clearPreferenceHighlight();
});
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <PageHero
      eyebrow="Profile"
      :title="profile.displayName || 'My profile'"
      :description="currentProfileSectionDescription"
    />

    <div class="tw-toastit-card p-6">
      <EmptyState v-if="isLoading" message="Loading..." />
      <div v-else class="grid gap-6 lg:grid-cols-[14rem_minmax(0,1fr)]">
        <aside class="space-y-2">
          <button
            v-for="section in profileSections"
            :key="section.key"
            type="button"
            class="flex w-full items-center justify-between rounded-2xl border px-4 py-3 text-left text-sm font-medium transition"
            :class="currentProfileSection === section.key
              ? 'border-amber-300 bg-amber-50 text-amber-900'
              : 'border-stone-200 bg-white text-stone-700 hover:border-stone-300 hover:bg-stone-50'"
            @click="goToProfileSection(section.key)"
          >
            <span>{{ section.label }}</span>
          </button>
        </aside>

        <div class="space-y-8">
          <template v-if="currentProfileSection === 'infos'">
            <div class="space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5">
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

                <label class="inline-flex cursor-pointer items-center justify-center rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400">
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

            <div class="space-y-4">
              <TextInputField v-model="profile.firstName" label="First name" />
              <TextInputField v-model="profile.lastName" label="Last name" />
              <PrimaryActionButton :disabled="isSaving" @click="saveProfile">
                {{ isSaving ? 'Saving...' : 'Save' }}
              </PrimaryActionButton>
            </div>

            <div v-if="profile.inboxEmailAddress" class="rounded-[1.5rem] border border-sky-200 bg-sky-50/70 p-5">
              <div class="space-y-3">
                <div>
                  <h3 class="text-base font-semibold text-sky-950">Inbound email</h3>
                  <p class="mt-1 text-sm text-sky-900">
                    Send an email to this address to create a new toast automatically in your hidden Inbox workspace.
                  </p>
                </div>

                <div class="rounded-2xl border border-sky-200 bg-white px-4 py-3">
                  <p class="text-xs font-semibold uppercase tracking-[0.18em] text-sky-700">Your Toastit inbox address</p>
                  <p class="mt-2 break-all font-mono text-sm text-sky-950">{{ profile.inboxEmailAddress }}</p>
                </div>
              </div>
            </div>
          </template>

          <template v-if="currentProfileSection === 'preferences'">
            <div class="rounded-[1.5rem] border border-stone-200 bg-stone-50/80 p-5">
              <h3 class="text-base font-semibold text-stone-950">Inbound xAI auto-apply</h3>
              <p class="mt-1 text-sm text-stone-600">
                Choose which xAI suggestions are automatically applied when a new toast is created from inbound email.
              </p>
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
              </div>
            </div>
          </template>

          <template v-if="currentProfileSection === 'trash'">
            <div class="space-y-4 rounded-[1.5rem] border border-stone-200 bg-stone-50 p-5">
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
            <div class="rounded-[1.5rem] border border-rose-200 bg-rose-50/60 p-5">
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
        </div>
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
