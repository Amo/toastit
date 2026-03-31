<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  teamUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const payload = ref(null);
const isLoading = ref(true);

const fetchTeam = async () => {
  isLoading.value = true;
  const response = await fetch(props.apiUrl, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });
  payload.value = response.ok ? await response.json() : null;
  isLoading.value = false;
};

onMounted(fetchTeam);
</script>

<template>
  <section v-if="payload" class="tw-toastit-shell space-y-6">
    <div class="space-y-2">
      <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600" aria-label="Breadcrumb">
        <a :href="dashboardUrl" class="transition hover:text-amber-700">Home</a>
        <span class="text-amber-300">/</span>
        <a :href="teamUrl" class="transition hover:text-amber-700">{{ payload.team.name }}</a>
        <span class="text-amber-300">/</span>
        <span class="text-amber-700">Archives</span>
      </nav>
      <h1 class="text-4xl font-semibold tracking-tight text-stone-950">{{ payload.team.name }}</h1>
      <p class="text-base leading-7 text-stone-600">Meetings clôturés de l'équipe.</p>
    </div>

    <div class="flex flex-wrap gap-3">
      <a :href="teamUrl" class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950">Equipe</a>
      <a :href="dashboardUrl" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700">Dashboard</a>
    </div>

    <div class="tw-toastit-card p-6">
      <h2 class="text-lg font-semibold text-stone-950">Meetings archivés</h2>
      <div v-if="isLoading" class="mt-6 text-sm text-stone-500">Chargement...</div>
      <div v-else-if="!payload.archivedMeetings?.length" class="mt-6 text-sm text-stone-500">Aucune archive pour cette équipe.</div>
      <div v-else class="mt-6 space-y-3">
        <a
          v-for="meeting in payload.archivedMeetings"
          :key="meeting.id"
          class="flex items-center justify-between gap-4 rounded-xl border border-stone-200 px-4 py-3 transition hover:border-stone-300 hover:bg-stone-50"
          :href="`/app/meetings/${meeting.id}`"
        >
          <div>
            <p class="font-medium text-stone-900">{{ meeting.title }}</p>
            <p class="text-sm text-stone-500">{{ meeting.scheduledAtDisplay }}</p>
          </div>
          <span class="rounded-full bg-stone-200 px-3 py-1 text-xs font-semibold text-stone-600">Cloture</span>
        </a>
      </div>
    </div>
  </section>
</template>
