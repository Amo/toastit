<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  apiUrl: {
    type: String,
    required: true,
  },
  accessToken: {
    type: String,
    required: true,
  },
});

const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const payload = ref(null);
const currentAgendaIndex = ref(0);

const meeting = computed(() => payload.value?.meeting ?? null);
const agendaItems = computed(() => payload.value?.agendaItems ?? []);
const vetoedItems = computed(() => payload.value?.vetoedItems ?? []);
const invitees = computed(() => payload.value?.participants?.invitees ?? []);
const currentItem = computed(() => agendaItems.value[currentAgendaIndex.value] ?? null);
const teamPath = computed(() => meeting.value?.teamId ? `/app/teams/${meeting.value.teamId}` : '/app');
const archivesPath = computed(() => meeting.value?.teamId ? `/app/teams/${meeting.value.teamId}/archives` : '/app');

const fetchMeeting = async () => {
  isLoading.value = true;
  errorMessage.value = '';

  const response = await fetch(props.apiUrl, {
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });

  if (!response.ok) {
    errorMessage.value = 'Impossible de charger le meeting.';
    isLoading.value = false;
    return;
  }

  payload.value = await response.json();
  currentAgendaIndex.value = Math.min(currentAgendaIndex.value, Math.max(agendaItems.value.length - 1, 0));
  isLoading.value = false;
};

const replaceCurrentItem = (nextItem) => {
  if (!payload.value || !currentItem.value) {
    return;
  }

  payload.value.agendaItems = payload.value.agendaItems.map((item) => (
    item.id === nextItem.id ? nextItem : item
  ));
};

const closeMeeting = async () => {
  if (!meeting.value?.currentUserIsOrganizer) {
    return;
  }

  isSaving.value = true;
  errorMessage.value = '';

  const response = await fetch(`/api/meetings/${meeting.value.id}/close`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });

  if (!response.ok) {
    errorMessage.value = 'Impossible de cloturer le meeting.';
    isSaving.value = false;
    return;
  }

  window.location.reload();
};

const startMeeting = async () => {
  if (!meeting.value?.currentUserIsOrganizer) {
    return;
  }

  isSaving.value = true;
  errorMessage.value = '';

  const response = await fetch(`/api/meetings/${meeting.value.id}/start`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });

  if (!response.ok) {
    errorMessage.value = 'Impossible de demarrer le meeting.';
    isSaving.value = false;
    return;
  }

  window.location.reload();
};

const toggleVote = async () => {
  if (!currentItem.value) {
    return;
  }

  const response = await fetch(`/api/items/${currentItem.value.id}/vote`, {
    method: 'POST',
    headers: {
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
  });

  if (!response.ok) {
    errorMessage.value = 'Impossible de voter pour ce sujet.';
    return;
  }

  const result = await response.json();
  replaceCurrentItem({
    ...currentItem.value,
    currentUserHasVoted: result.voted,
    voteCount: result.voteCount,
  });
};

const ensureFollowUps = (item) => ({
  ...item,
  followUpItems: item.followUpItems?.length ? item.followUpItems : [{ title: '', ownerId: null, dueOn: null }],
});

const addFollowUp = () => {
  if (!currentItem.value) {
    return;
  }

  replaceCurrentItem(ensureFollowUps({
    ...currentItem.value,
    followUpItems: [...ensureFollowUps(currentItem.value).followUpItems, { title: '', ownerId: null, dueOn: null }],
  }));
};

const removeFollowUp = (index) => {
  if (!currentItem.value) {
    return;
  }

  const nextFollowUps = ensureFollowUps(currentItem.value).followUpItems.filter((_, currentIndex) => currentIndex !== index);
  replaceCurrentItem({
    ...currentItem.value,
    followUpItems: nextFollowUps.length ? nextFollowUps : [{ title: '', ownerId: null, dueOn: null }],
  });
};

const updateFollowUp = (index, key, value) => {
  if (!currentItem.value) {
    return;
  }

  const nextFollowUps = ensureFollowUps(currentItem.value).followUpItems.map((followUp, currentIndex) => (
    currentIndex === index ? { ...followUp, [key]: value || null } : followUp
  ));

  replaceCurrentItem({
    ...currentItem.value,
    followUpItems: nextFollowUps,
  });
};

const saveDiscussion = async () => {
  if (!currentItem.value) {
    return;
  }

  isSaving.value = true;
  errorMessage.value = '';

  const response = await fetch(`/api/items/${currentItem.value.id}/discussion`, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Accept: 'application/json',
      Authorization: `Bearer ${props.accessToken}`,
    },
    body: JSON.stringify({
      discussionStatus: currentItem.value.discussionStatus,
      discussionNotes: currentItem.value.discussionNotes,
      ownerId: currentItem.value.owner?.id ?? null,
      dueOn: currentItem.value.dueOn,
      followUpItems: ensureFollowUps(currentItem.value).followUpItems,
    }),
  });

  if (!response.ok) {
    errorMessage.value = 'Impossible d’enregistrer le suivi.';
    isSaving.value = false;
    return;
  }

  await fetchMeeting();
  isSaving.value = false;
};

onMounted(fetchMeeting);
</script>

<template>
  <section class="tw-toastit-shell">
    <div v-if="isLoading" class="tw-toastit-card p-6 text-sm text-stone-500">
      Chargement du meeting...
    </div>

    <div v-else-if="errorMessage" class="tw-toastit-card p-6 text-sm text-red-600">
      {{ errorMessage }}
    </div>

    <div v-else-if="meeting?.status === 'live' && currentItem" class="space-y-6">
      <section class="grid gap-2">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600" aria-label="Breadcrumb">
          <a href="/app" class="transition hover:text-amber-700">Home</a>
          <template v-if="meeting.teamId">
            <span class="text-amber-300">/</span>
            <a :href="teamPath" class="transition hover:text-amber-700">{{ meeting.teamName }}</a>
          </template>
          <span class="text-amber-300">/</span>
          <span class="text-amber-700">{{ meeting.title }} - {{ meeting.scheduledOnDisplay }}</span>
        </nav>
        <div class="flex items-start justify-between gap-4">
          <div>
            <h1 class="flex items-center gap-3 text-3xl font-semibold tracking-tight text-stone-950">
              <span class="text-amber-600" aria-hidden="true">●</span>
              <span>{{ meeting.title }}</span>
            </h1>
          </div>
          <button
            v-if="meeting.currentUserIsOrganizer"
            type="button"
            class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60"
            :disabled="isSaving"
            @click="closeMeeting"
          >
            Cloturer
          </button>
        </div>
        <p class="text-sm text-stone-500">
          {{ agendaItems.length }} items to discuss — {{ meeting.scheduledAtDisplay }}
        </p>
      </section>

      <section class="space-y-3">
        <div class="flex items-center justify-between">
          <span class="rounded-full bg-stone-100 px-3 py-1 text-sm font-medium text-stone-700">
            {{ currentAgendaIndex + 1 }} / {{ agendaItems.length }}
          </span>
          <p v-if="vetoedItems.length" class="text-sm text-stone-500">
            {{ vetoedItems.length }} sujet<span v-if="vetoedItems.length > 1">s</span> hors agenda
          </p>
        </div>

        <div class="space-y-3">
          <article
            v-for="(item, index) in agendaItems"
            :key="item.id"
            class="overflow-hidden rounded-[1.35rem] border border-stone-200 bg-white transition"
            :class="index === currentAgendaIndex ? 'shadow-toastit-panel ring-1 ring-amber-200' : 'opacity-90'"
          >
            <button
              type="button"
              class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left"
              @click="currentAgendaIndex = index"
            >
              <div class="space-y-2">
                <div class="flex items-center gap-3">
                  <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-amber-100 font-semibold text-amber-700">
                    {{ index + 1 }}
                  </span>
                  <p class="text-lg font-semibold text-stone-950">{{ item.title }}</p>
                </div>
                <blockquote v-if="item.description" class="border-l-2 border-amber-200 pl-4 text-sm italic leading-7 text-stone-500">
                  “{{ item.description }}”
                </blockquote>
              </div>
              <span class="pt-1 text-amber-700 transition" :class="index === currentAgendaIndex ? 'rotate-180' : ''">
                ▾
              </span>
            </button>

            <div v-if="index === currentAgendaIndex" class="space-y-5 border-t border-stone-100 px-5 pb-5 pt-4">
              <div class="flex items-center gap-3 text-sm text-stone-500">
                <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-stone-100 font-semibold text-stone-700">
                  {{ item.author.initials }}
                </span>
                <span>Propose par {{ item.author.displayName }}</span>
              </div>

              <div v-if="item.followUpItems?.some((followUp) => followUp.title)" class="rounded-2xl bg-amber-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Sujets de suivi</p>
                <div class="mt-3 space-y-2">
                  <p v-for="(followUp, followUpIndex) in item.followUpItems" :key="`memory-${followUpIndex}`" class="text-sm text-stone-800">
                    <strong>{{ followUp.title }}</strong>
                    <span v-if="followUp.ownerName"> · {{ followUp.ownerName }}</span>
                    <span v-if="followUp.dueOnDisplay"> · {{ followUp.dueOnDisplay }}</span>
                  </p>
                </div>
              </div>

              <div v-if="meeting.currentUserIsOrganizer" class="space-y-4">
                <label class="grid gap-2 text-sm font-medium text-stone-700">
                  <span>Notes optionnelles</span>
                  <textarea
                    class="min-h-28 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm"
                    :value="currentItem.discussionNotes ?? ''"
                    @input="replaceCurrentItem({ ...currentItem, discussionNotes: $event.target.value })"
                  />
                </label>

                <div class="space-y-3">
                  <div class="flex items-center justify-between gap-4">
                    <p class="text-sm font-medium text-stone-700">
                      {{ meeting.isRecurring ? 'Sujets de suivi pour la prochaine occurrence' : 'Sujets de suivi' }}
                    </p>
                    <button
                      type="button"
                      class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
                      @click="addFollowUp"
                    >
                      Ajouter un suivi
                    </button>
                  </div>

                  <div class="space-y-3">
                    <div
                      v-for="(followUp, followUpIndex) in ensureFollowUps(currentItem).followUpItems"
                      :key="followUpIndex"
                      class="grid gap-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 xl:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)_11rem_auto]"
                    >
                      <input
                        class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"
                        type="text"
                        :value="followUp.title ?? ''"
                        placeholder="Titre du suivi"
                        @input="updateFollowUp(followUpIndex, 'title', $event.target.value)"
                      >

                      <select
                        class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"
                        :value="followUp.ownerId ?? ''"
                        @change="updateFollowUp(followUpIndex, 'ownerId', $event.target.value)"
                      >
                        <option value="">Assignee</option>
                        <option v-for="invitee in invitees" :key="invitee.id" :value="invitee.id">
                          {{ invitee.displayName }}
                        </option>
                      </select>

                      <input
                        class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm"
                        type="date"
                        :value="followUp.dueOn ?? ''"
                        placeholder="Date"
                        @input="updateFollowUp(followUpIndex, 'dueOn', $event.target.value)"
                      >

                      <div class="flex items-end justify-end">
                        <button
                          type="button"
                          class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700"
                          @click="removeFollowUp(followUpIndex)"
                        >
                          Supprimer
                        </button>
                      </div>
                    </div>
                  </div>
                </div>

                <div class="flex justify-end">
                  <button
                    type="button"
                    class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60"
                    :disabled="isSaving"
                    @click="saveDiscussion"
                  >
                    {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                  </button>
                </div>
              </div>
            </div>
          </article>
        </div>

        <div v-if="vetoedItems.length" class="rounded-[1.25rem] border border-dashed border-stone-300 bg-stone-50 p-4">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Hors agenda</p>
          <div class="mt-3 flex flex-wrap gap-2">
            <span v-for="item in vetoedItems" :key="item.id" class="rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700">
              {{ item.title }}
            </span>
          </div>
        </div>
      </section>
    </div>

    <div v-else-if="meeting" class="space-y-6">
      <section class="grid gap-2">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600" aria-label="Breadcrumb">
          <a href="/app" class="transition hover:text-amber-700">Home</a>
          <template v-if="meeting.teamId">
            <span class="text-amber-300">/</span>
            <a :href="teamPath" class="transition hover:text-amber-700">{{ meeting.teamName }}</a>
            <template v-if="meeting.status === 'closed'">
              <span class="text-amber-300">/</span>
              <a :href="archivesPath" class="transition hover:text-amber-700">Archives</a>
            </template>
          </template>
          <span class="text-amber-300">/</span>
          <span class="text-amber-700">{{ meeting.title }} - {{ meeting.scheduledOnDisplay }}</span>
        </nav>
        <div class="flex items-start justify-between gap-4">
          <div class="space-y-2">
            <h1 class="flex items-center gap-3 text-3xl font-semibold tracking-tight text-stone-950">
              <span v-if="meeting.status === 'live'" class="text-amber-600" aria-hidden="true">●</span>
              <span>{{ meeting.title }}</span>
            </h1>
            <p class="text-sm text-stone-500">
              {{ meeting.scheduledAtDisplay }}
              <span v-if="meeting.videoLink"> · {{ meeting.videoLink }}</span>
            </p>
          </div>
          <div class="flex items-center gap-2">
            <span v-if="meeting.isRecurring" class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">
              {{ meeting.recurrenceDisplay }}
            </span>
            <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="meeting.status === 'scheduled' ? 'bg-stone-100 text-stone-700' : 'bg-stone-900 text-white'">
              {{ meeting.status === 'scheduled' ? 'Planifie' : 'Cloture' }}
            </span>
          </div>
        </div>
      </section>

      <section class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
          <div class="tw-toastit-card p-6">
            <div class="flex items-center justify-between">
              <h2 class="text-lg font-semibold text-stone-950">Agenda</h2>
              <button
                v-if="meeting.status === 'scheduled' && meeting.currentUserIsOrganizer"
                type="button"
                class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60"
                :disabled="isSaving"
                @click="startMeeting"
              >
                {{ isSaving ? 'Demarrage...' : 'Demarrer le meeting' }}
              </button>
            </div>

            <div v-if="!agendaItems.length && !vetoedItems.length" class="mt-6 text-sm text-stone-500">
              Aucun sujet partage pour ce meeting.
            </div>

            <div v-else class="mt-6 space-y-3">
              <article
                v-for="item in agendaItems"
                :key="item.id"
                class="rounded-2xl border border-stone-200 px-4 py-4"
              >
                <div class="space-y-2">
                  <p class="text-base font-semibold text-stone-950">{{ item.title }}</p>
                  <p v-if="item.description" class="text-sm leading-6 text-stone-600">{{ item.description }}</p>
                  <div class="flex flex-wrap gap-2 text-xs text-stone-500">
                    <span>Par {{ item.author.displayName }}</span>
                    <span>·</span>
                    <span>{{ item.voteCount }} vote<span v-if="item.voteCount > 1">s</span></span>
                    <span v-if="item.isBoosted">·</span>
                    <span v-if="item.isBoosted">Boost</span>
                  </div>
                </div>
              </article>

              <div v-if="vetoedItems.length" class="rounded-2xl border border-dashed border-stone-300 bg-stone-50 p-4">
                <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Sujets vetoes</p>
                <div class="mt-3 flex flex-wrap gap-2">
                  <span
                    v-for="item in vetoedItems"
                    :key="item.id"
                    class="rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700"
                  >
                    {{ item.title }}
                  </span>
                </div>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="tw-toastit-card p-6">
            <h2 class="text-lg font-semibold text-stone-950">Participants</h2>
            <div class="mt-6 space-y-3">
              <div class="flex items-center gap-3 rounded-xl border border-stone-200 px-4 py-3">
                <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-stone-100 font-semibold text-stone-700">
                  {{ meeting.currentUserIsOrganizer ? (meeting.title?.slice(0, 2)?.toUpperCase() || 'OR') : 'OR' }}
                </span>
                <span class="font-medium text-stone-900">{{ payload?.participants?.organizer?.displayName }} · Organisateur</span>
              </div>
              <div
                v-for="invitee in invitees"
                :key="invitee.id"
                class="flex items-center gap-3 rounded-xl border border-stone-200 px-4 py-3"
              >
                <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-stone-100 font-semibold text-stone-700">
                  {{ invitee.initials }}
                </span>
                <span class="font-medium text-stone-900">{{ invitee.displayName }}</span>
              </div>
            </div>
          </div>
        </div>
      </section>
    </div>
  </section>
</template>
