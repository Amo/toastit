<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const payload = ref({ teams: [], adHocMeetings: [], archivedAdHocMeetings: [] });
const isLoading = ref(true);
const creatingTeam = ref(false);
const creatingMeeting = ref(false);
const teamName = ref('');
const adHocMeeting = ref({ title: '', scheduledAt: '', videoLink: '', isRecurring: false, recurrenceQuantity: '1', recurrenceUnit: 'W' });

const fetchDashboard = async () => {
  isLoading.value = true;
  const response = await fetch(props.apiUrl, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });
  payload.value = response.ok ? await response.json() : { teams: [], adHocMeetings: [], archivedAdHocMeetings: [] };
  isLoading.value = false;
};

const createTeam = async () => {
  if (!teamName.value.trim()) return;
  creatingTeam.value = true;
  await fetch('/api/teams', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify({ name: teamName.value }),
  });
  teamName.value = '';
  creatingTeam.value = false;
  await fetchDashboard();
};

const createAdHocMeeting = async () => {
  if (!adHocMeeting.value.title.trim() || !adHocMeeting.value.scheduledAt) return;
  creatingMeeting.value = true;
  await fetch('/api/meetings/ad-hoc', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify(adHocMeeting.value),
  });
  adHocMeeting.value = { title: '', scheduledAt: '', videoLink: '', isRecurring: false, recurrenceQuantity: '1', recurrenceUnit: 'W' };
  creatingMeeting.value = false;
  await fetchDashboard();
};

onMounted(fetchDashboard);
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <div class="space-y-2">
      <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Workspace</p>
      <h1 class="text-4xl font-semibold tracking-tight text-stone-950">Vos equipes et meetings.</h1>
      <p class="text-base leading-7 text-stone-600">Premier noyau metier de Toastit: creer une equipe, planifier un meeting, ajouter des sujets et voter.</p>
    </div>

    <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
      <div class="space-y-6">
        <div class="tw-toastit-card p-6">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-semibold text-stone-950">Meetings par equipe</h2>
          </div>

          <div v-if="isLoading" class="mt-6 text-sm text-stone-500">Chargement...</div>
          <div v-else-if="!payload.teams.length" class="mt-6 text-sm text-stone-500">Aucune equipe pour l'instant.</div>
          <div v-else class="mt-6 space-y-4">
            <article v-for="team in payload.teams" :key="team.id" class="rounded-2xl border border-stone-200 p-4">
              <div class="flex items-start justify-between gap-4">
                <div>
                  <h3 class="text-lg font-semibold text-stone-950">{{ team.name }}</h3>
                  <p class="text-sm text-stone-500">{{ team.meetingCount }} meeting<span v-if="team.meetingCount > 1">s</span> · {{ team.itemCount }} sujet<span v-if="team.itemCount > 1">s</span></p>
                </div>
                <div class="flex items-center gap-2">
                  <a class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" :href="`/app/teams/${team.id}/archives`">Archives</a>
                  <a class="rounded-full bg-amber-500 px-4 py-2 text-sm font-semibold text-stone-950" :href="`/app/teams/${team.id}`">Equipe</a>
                </div>
              </div>

              <div class="mt-4 space-y-3">
                <div v-if="!team.meetings.length" class="text-sm text-stone-500">Aucun meeting pour cette equipe.</div>
                <a
                  v-for="meeting in team.meetings"
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
            </article>
          </div>
        </div>

        <div class="tw-toastit-card p-6">
          <h2 class="text-lg font-semibold text-stone-950">Meetings ad-hoc</h2>
          <div v-if="isLoading" class="mt-6 text-sm text-stone-500">Chargement...</div>
          <div v-else-if="!payload.adHocMeetings.length" class="mt-6 text-sm text-stone-500">Aucun meeting ad-hoc pour l'instant.</div>
          <div v-else class="mt-6 space-y-3">
            <a
              v-for="meeting in payload.adHocMeetings"
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

          <div v-if="payload.archivedAdHocMeetings?.length" class="mt-6 space-y-3 rounded-2xl bg-stone-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Archives</p>
            <a
              v-for="meeting in payload.archivedAdHocMeetings"
              :key="meeting.id"
              class="flex items-center justify-between gap-4 rounded-xl border border-stone-200 bg-white px-4 py-3 transition hover:border-stone-300"
              :href="`/app/meetings/${meeting.id}`"
            >
              <div>
                <p class="font-medium text-stone-700">{{ meeting.title }}</p>
                <p class="text-sm text-stone-500">{{ meeting.scheduledAtDisplay }}</p>
              </div>
              <span class="rounded-full bg-stone-200 px-3 py-1 text-xs font-semibold text-stone-600">Cloture</span>
            </a>
          </div>
        </div>
      </div>

      <div class="space-y-6">
        <div class="tw-toastit-card p-6 space-y-4">
          <h2 class="text-lg font-semibold text-stone-950">Creer une equipe</h2>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Nom de l'equipe</span>
            <input v-model="teamName" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text">
          </label>
          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60" :disabled="creatingTeam" @click="createTeam">
            {{ creatingTeam ? 'Creation...' : "Creer l'equipe" }}
          </button>
        </div>

        <div class="tw-toastit-card p-6 space-y-4">
          <h2 class="text-lg font-semibold text-stone-950">Creer un meeting ad-hoc</h2>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Titre</span>
            <input v-model="adHocMeeting.title" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text">
          </label>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Date et heure</span>
            <input v-model="adHocMeeting.scheduledAt" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="datetime-local">
          </label>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Lien visio</span>
            <input v-model="adHocMeeting.videoLink" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="url">
          </label>
          <label class="flex items-center gap-3 text-sm font-medium text-stone-700">
            <input v-model="adHocMeeting.isRecurring" type="checkbox">
            <span>Meeting recurrent</span>
          </label>
          <div class="grid gap-4 md:grid-cols-2">
            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Quantite</span>
              <input v-model="adHocMeeting.recurrenceQuantity" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="number" min="1" max="10">
            </label>
            <label class="grid gap-2 text-sm font-medium text-stone-700">
              <span>Intervalle ISO</span>
              <select v-model="adHocMeeting.recurrenceUnit" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base">
                <option value="D">Jour(s)</option>
                <option value="W">Semaine(s)</option>
                <option value="M">Mois</option>
                <option value="Y">Annee(s)</option>
              </select>
            </label>
          </div>
          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60" :disabled="creatingMeeting" @click="createAdHocMeeting">
            {{ creatingMeeting ? 'Creation...' : 'Creer le meeting' }}
          </button>
        </div>
      </div>
    </div>
  </section>
</template>
