<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  updateUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const isLoading = ref(true);
const isSaving = ref(false);
const profile = ref({ displayName: '', firstName: '', lastName: '' });

const fetchProfile = async () => {
  isLoading.value = true;
  const response = await fetch(props.apiUrl, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });

  if (response.ok) {
    const payload = await response.json();
    profile.value = payload.user;
  }

  isLoading.value = false;
};

const saveProfile = async () => {
  isSaving.value = true;
  await fetch(props.updateUrl, {
    method: 'PUT',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify({
      firstName: profile.value.firstName,
      lastName: profile.value.lastName,
    }),
  });
  isSaving.value = false;
  await fetchProfile();
};

onMounted(fetchProfile);
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <div class="space-y-2">
      <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Profile</p>
      <h1 class="text-4xl font-semibold tracking-tight text-stone-950">{{ profile.displayName || 'My profile' }}</h1>
      <p class="text-base leading-7 text-stone-600">Set your first and last name to improve lists, avatars, and invitations.</p>
    </div>

    <div class="tw-toastit-card max-w-2xl p-6">
      <div v-if="isLoading" class="text-sm text-stone-500">Loading...</div>
      <div v-else class="space-y-4">
        <label class="grid gap-2 text-sm font-medium text-stone-700">
          <span>First name</span>
          <input v-model="profile.firstName" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text">
        </label>
        <label class="grid gap-2 text-sm font-medium text-stone-700">
          <span>Last name</span>
          <input v-model="profile.lastName" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text">
        </label>
        <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60" :disabled="isSaving" @click="saveProfile">
          {{ isSaving ? 'Saving...' : 'Save' }}
        </button>
      </div>
    </div>
  </section>
</template>
