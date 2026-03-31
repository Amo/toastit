<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const payload = ref(null);
const isLoading = ref(true);
const inviteEmail = ref('');
const meetingForm = ref({ title: '', scheduledAt: '', videoLink: '', isRecurring: false, recurrenceQuantity: '1', recurrenceUnit: 'W' });

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

const inviteMember = async () => {
  if (!inviteEmail.value.trim() || !payload.value) return;
  await fetch(`/api/teams/${payload.value.team.id}/invite`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify({ email: inviteEmail.value }),
  });
  inviteEmail.value = '';
  await fetchTeam();
};

const removeMember = async (memberId) => {
  if (!payload.value) return;
  await fetch(`/api/teams/${payload.value.team.id}/members/${memberId}`, {
    method: 'DELETE',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });
  await fetchTeam();
};

const createMeeting = async () => {
  if (!payload.value || !meetingForm.value.title.trim() || !meetingForm.value.scheduledAt) return;
  await fetch(`/api/teams/${payload.value.team.id}/meetings`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify(meetingForm.value),
  });
  meetingForm.value = { title: '', scheduledAt: '', videoLink: '', isRecurring: false, recurrenceQuantity: '1', recurrenceUnit: 'W' };
  await fetchTeam();
};

onMounted(fetchTeam);
</script>

<template>
  <section v-if="payload" class="tw-toastit-shell space-y-6">
    <div class="space-y-2">
      <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600" aria-label="Breadcrumb">
        <a :href="dashboardUrl" class="transition hover:text-amber-700">Home</a>
        <span class="text-amber-300">/</span>
        <span class="text-amber-700">{{ payload.team.name }}</span>
      </nav>
      <h1 class="text-4xl font-semibold tracking-tight text-stone-950">{{ payload.team.name }}</h1>
      <p class="text-base leading-7 text-stone-600">Parking lot partage, meetings a venir et votes de priorisation.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
      <div class="space-y-6">
        <div class="tw-toastit-card p-6">
          <h2 class="text-lg font-semibold text-stone-950">Membres</h2>
          <div class="mt-6 space-y-3">
            <div
              v-for="membership in payload.memberships"
              :key="membership.id"
              class="flex items-center justify-between gap-4 rounded-xl border border-stone-200 px-4 py-3"
            >
              <div class="flex items-center gap-3">
                <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-stone-100 font-semibold text-stone-700">{{ membership.user.initials }}</span>
                <span class="font-medium text-stone-900">{{ membership.user.displayName }}</span>
              </div>
              <button
                v-if="membership.user.id !== payload.team.organizerId"
                type="button"
                class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
                @click="removeMember(membership.id)"
              >
                Retirer
              </button>
              <span v-else class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">Organisateur</span>
            </div>
          </div>

          <div class="mt-6 space-y-3">
            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Invite by email</span>
              <input v-model="inviteEmail" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="email">
            </label>
            <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="inviteMember">
              Invite
            </button>
          </div>
        </div>

        <div class="tw-toastit-card p-6">
          <div class="flex items-center justify-between gap-4">
            <h2 class="text-lg font-semibold text-stone-950">Meetings</h2>
            <a :href="`/app/teams/${payload.team.id}/archives`" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700">Archives</a>
          </div>
          <div v-if="!payload.meetings.length" class="mt-6 text-sm text-stone-500">Aucun meeting pour l'instant.</div>
          <div v-else class="mt-6 space-y-3">
            <a
              v-for="meeting in payload.meetings"
              :key="meeting.id"
              class="flex items-center justify-between gap-4 rounded-xl border border-stone-200 px-4 py-3 transition hover:border-amber-200 hover:bg-amber-50"
              :href="`/app/meetings/${meeting.id}`"
            >
              <div>
                <p class="flex items-center gap-2 font-medium text-stone-900">
                  <span v-if="meeting.status === 'live'" class="text-amber-600" aria-hidden="true">●</span>
                  <span>{{ meeting.title }}</span>
                </p>
                <p class="text-sm text-stone-500">{{ meeting.scheduledAtDisplay }}</p>
              </div>
              <span v-if="meeting.isRecurring" class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">{{ meeting.recurrenceDisplay }}</span>
            </a>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="tw-toastit-card p-6 space-y-4">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-stone-950">Creer un meeting</h2>
            <a class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" :href="dashboardUrl">Retour au dashboard</a>
          </div>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Titre</span>
            <input v-model="meetingForm.title" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text">
          </label>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Date et heure</span>
            <input v-model="meetingForm.scheduledAt" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="datetime-local">
          </label>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Lien visio</span>
            <input v-model="meetingForm.videoLink" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="url">
          </label>
          <label class="flex items-center gap-3 text-sm font-medium text-stone-700">
            <input v-model="meetingForm.isRecurring" type="checkbox">
            <span>Meeting recurrent</span>
          </label>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Quantite</span>
              <input v-model="meetingForm.recurrenceQuantity" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="number" min="1" max="10">
            </label>
            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Intervalle ISO</span>
              <select v-model="meetingForm.recurrenceUnit" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base">
                <option value="D">Jour(s)</option>
                <option value="W">Semaine(s)</option>
                <option value="M">Mois</option>
                <option value="Y">Annee(s)</option>
              </select>
            </label>
          </div>
          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="createMeeting">
            Creer le meeting
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
