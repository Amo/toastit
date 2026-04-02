<script setup>
import { onMounted, ref } from 'vue';
import { ToastitApiClient } from '../api/ToastitApiClient';
import PageHero from './PageHero.vue';
import PrimaryActionButton from './PrimaryActionButton.vue';
import TextInputField from './TextInputField.vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  updateUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const isLoading = ref(true);
const isSaving = ref(false);
const profile = ref({ displayName: '', firstName: '', lastName: '' });
const apiClient = new ToastitApiClient(props.accessToken);

const fetchProfile = async () => {
  isLoading.value = true;
  const { ok, data } = await apiClient.getJson(props.apiUrl);

  if (ok && data) {
    profile.value = data.user;
  }

  isLoading.value = false;
};

const saveProfile = async () => {
  isSaving.value = true;
  await apiClient.putJson(props.updateUrl, {
    firstName: profile.value.firstName,
    lastName: profile.value.lastName,
  });
  isSaving.value = false;
  await fetchProfile();
};

onMounted(fetchProfile);
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <PageHero eyebrow="Profile" :title="profile.displayName || 'My profile'" description="Set your first and last name to improve lists, avatars, and invitations." />

    <div class="tw-toastit-card max-w-2xl p-6">
      <div v-if="isLoading" class="text-sm text-stone-500">Loading...</div>
      <div v-else class="space-y-4">
        <TextInputField v-model="profile.firstName" label="First name" />
        <TextInputField v-model="profile.lastName" label="Last name" />
        <PrimaryActionButton :disabled="isSaving" @click="saveProfile">
          {{ isSaving ? 'Saving...' : 'Save' }}
        </PrimaryActionButton>
      </div>
    </div>
  </section>
</template>
