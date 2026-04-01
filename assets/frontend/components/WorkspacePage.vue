<script setup>
import { computed, onMounted, ref } from 'vue';

const props = defineProps({
  apiUrl: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  accessToken: { type: String, required: true },
});

const payload = ref(null);
const isLoading = ref(true);
const isSaving = ref(false);
const errorMessage = ref('');
const inviteEmail = ref('');
const itemForm = ref({ title: '', description: '' });
const currentAgendaIndex = ref(0);

const workspace = computed(() => payload.value?.workspace ?? null);
const members = computed(() => payload.value?.memberships ?? []);
const participants = computed(() => payload.value?.participants ?? []);
const agendaItems = computed(() => payload.value?.agendaItems ?? []);
const vetoedItems = computed(() => payload.value?.vetoedItems ?? []);
const resolvedItems = computed(() => payload.value?.resolvedItems ?? []);
const currentItem = computed(() => agendaItems.value[currentAgendaIndex.value] ?? null);

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
    errorMessage.value = 'Impossible de charger le workspace.';
    isLoading.value = false;
    return;
  }

  payload.value = await response.json();
  currentAgendaIndex.value = Math.min(currentAgendaIndex.value, Math.max(agendaItems.value.length - 1, 0));
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
  await fetch(`/api/workspaces/${workspace.value.id}/items`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify(itemForm.value),
  });
  itemForm.value = { title: '', description: '' };
  await fetchWorkspace();
};

const startMeetingMode = async () => {
  if (!workspace.value?.currentUserIsOrganizer) return;
  isSaving.value = true;
  await fetch(`/api/workspaces/${workspace.value.id}/meeting/start`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });
  await fetchWorkspace();
  isSaving.value = false;
};

const stopMeetingMode = async () => {
  if (!workspace.value?.currentUserIsOrganizer) return;
  isSaving.value = true;
  await fetch(`/api/workspaces/${workspace.value.id}/meeting/stop`, {
    method: 'POST',
    headers: authorizedHeaders(),
  });
  await fetchWorkspace();
  isSaving.value = false;
};

const toggleVote = async (itemId) => {
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

const updateCurrentItemField = (key, value) => {
  if (!payload.value || !currentItem.value) return;

  payload.value.agendaItems = payload.value.agendaItems.map((item) => (
    item.id === currentItem.value.id ? { ...item, [key]: value } : item
  ));
};

const ensureDraftFollowUps = (item) => item.draftFollowUps?.length
  ? item.draftFollowUps
  : [{ title: '', ownerId: null, dueOn: null }];

const addFollowUpDraft = () => {
  if (!currentItem.value) return;
  updateCurrentItemField('draftFollowUps', [...ensureDraftFollowUps(currentItem.value), { title: '', ownerId: null, dueOn: null }]);
};

const updateFollowUpDraft = (index, key, value) => {
  if (!currentItem.value) return;
  const nextDrafts = ensureDraftFollowUps(currentItem.value).map((followUp, currentIndex) => (
    currentIndex === index ? { ...followUp, [key]: value || null } : followUp
  ));
  updateCurrentItemField('draftFollowUps', nextDrafts);
};

const removeFollowUpDraft = (index) => {
  if (!currentItem.value) return;
  const nextDrafts = ensureDraftFollowUps(currentItem.value).filter((_, currentIndex) => currentIndex !== index);
  updateCurrentItemField('draftFollowUps', nextDrafts.length ? nextDrafts : [{ title: '', ownerId: null, dueOn: null }]);
};

const saveDiscussion = async () => {
  if (!currentItem.value) return;
  isSaving.value = true;

  const response = await fetch(`/api/items/${currentItem.value.id}/discussion`, {
    method: 'POST',
    headers: authorizedHeaders(true),
    body: JSON.stringify({
      discussionStatus: currentItem.value.discussionStatus,
      discussionNotes: currentItem.value.discussionNotes,
      ownerId: currentItem.value.owner?.id ?? null,
      dueOn: currentItem.value.dueOn,
      followUpItems: ensureDraftFollowUps(currentItem.value),
    }),
  });

  if (!response.ok) {
    errorMessage.value = 'Impossible d\'enregistrer le suivi.';
    isSaving.value = false;
    return;
  }

  await fetchWorkspace();
  isSaving.value = false;
};

onMounted(fetchWorkspace);
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <div v-if="isLoading" class="tw-toastit-card p-6 text-sm text-stone-500">Chargement...</div>
    <div v-else-if="errorMessage" class="tw-toastit-card p-6 text-sm text-red-600">{{ errorMessage }}</div>
    <template v-else-if="workspace">
      <div class="space-y-2">
        <nav class="flex flex-wrap items-center gap-2 text-xs font-semibold uppercase tracking-[0.22em] text-amber-600" aria-label="Breadcrumb">
          <a :href="dashboardUrl" class="transition hover:text-amber-700">Home</a>
          <span class="text-amber-300">/</span>
          <span class="text-amber-700">{{ workspace.name }}</span>
        </nav>
        <div class="flex items-start justify-between gap-4">
          <div>
            <h1 class="text-4xl font-semibold tracking-tight text-stone-950">{{ workspace.name }}</h1>
            <p class="text-base leading-7 text-stone-600">Membres invites, toasts partages, et meeting mode au fil de l'eau.</p>
          </div>
          <button
            v-if="workspace.currentUserIsOrganizer"
            type="button"
            class="rounded-full px-5 py-3 text-sm font-semibold shadow-sm transition disabled:opacity-60"
            :class="workspace.meetingMode === 'live' ? 'bg-stone-900 text-white hover:bg-stone-800' : 'bg-amber-500 text-stone-950 hover:bg-amber-400'"
            :disabled="isSaving"
            @click="workspace.meetingMode === 'live' ? stopMeetingMode() : startMeetingMode()"
          >
            {{ workspace.meetingMode === 'live' ? 'Stop meeting mode' : 'Start meeting mode' }}
          </button>
        </div>
      </div>

      <div class="grid gap-6 xl:grid-cols-[minmax(0,1fr)_24rem]">
        <div class="space-y-6">
          <div class="tw-toastit-card p-6 space-y-4">
            <div class="flex items-center justify-between gap-4">
              <h2 class="text-lg font-semibold text-stone-950">Toasts</h2>
              <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="workspace.meetingMode === 'live' ? 'bg-amber-100 text-amber-700' : 'bg-stone-100 text-stone-700'">
                {{ workspace.meetingMode === 'live' ? 'Live' : 'Idle' }}
              </span>
            </div>

            <div class="grid gap-3 md:grid-cols-[minmax(0,1.5fr)_minmax(0,1fr)_auto]">
              <input v-model="itemForm.title" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text" placeholder="Nouveau toast">
              <input v-model="itemForm.description" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="text" placeholder="Contexte optionnel">
              <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="createItem">Ajouter</button>
            </div>

            <div v-if="!agendaItems.length" class="text-sm text-stone-500">Aucun toast actif.</div>
            <div v-else class="space-y-3">
              <article
                v-for="(item, index) in agendaItems"
                :key="item.id"
                class="overflow-hidden rounded-[1.35rem] border border-stone-200 bg-white transition"
                :class="index === currentAgendaIndex ? 'shadow-toastit-panel ring-1 ring-amber-200' : 'opacity-95'"
              >
                <button type="button" class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left" @click="currentAgendaIndex = index">
                  <div class="space-y-2">
                    <div class="flex items-center gap-3">
                      <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-amber-100 font-semibold text-amber-700">{{ index + 1 }}</span>
                      <p class="text-lg font-semibold text-stone-950">{{ item.title }}</p>
                    </div>
                    <p v-if="item.description" class="text-sm text-stone-500">{{ item.description }}</p>
                  </div>
                  <div class="flex items-center gap-2">
                    <span class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">{{ item.voteCount }} vote<span v-if="item.voteCount > 1">s</span></span>
                    <span class="pt-1 text-amber-700 transition" :class="index === currentAgendaIndex ? 'rotate-180' : ''">▾</span>
                  </div>
                </button>

                <div v-if="index === currentAgendaIndex" class="space-y-5 border-t border-stone-100 px-5 pb-5 pt-4">
                  <div class="flex flex-wrap items-center gap-3 text-sm text-stone-500">
                    <span>Propose par {{ item.author.displayName }}</span>
                    <span v-if="item.owner">· Owner: {{ item.owner.displayName }}</span>
                    <span v-if="item.dueOnDisplay">· Echeance: {{ item.dueOnDisplay }}</span>
                  </div>

                  <div class="flex flex-wrap gap-2">
                    <button class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="toggleVote(item.id)">
                      {{ item.currentUserHasVoted ? 'Retirer mon vote' : 'Voter' }}
                    </button>
                    <template v-if="workspace.currentUserIsOrganizer && workspace.meetingMode === 'live'">
                      <button class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="toggleBoost(item.id)">
                        {{ item.isBoosted ? 'Unboost' : 'Boost' }}
                      </button>
                      <button class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="toggleVeto(item.id)">
                        {{ item.status === 'vetoed' ? 'Reprendre' : 'Veto' }}
                      </button>
                    </template>
                  </div>

                  <div v-if="item.followUpItems?.length" class="rounded-2xl bg-amber-50 p-4">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Follow-ups lies</p>
                    <div class="mt-3 space-y-2">
                      <p v-for="followUp in item.followUpItems" :key="followUp.id" class="text-sm text-stone-800">
                        <strong>{{ followUp.title }}</strong>
                        <span v-if="followUp.ownerName"> · {{ followUp.ownerName }}</span>
                        <span v-if="followUp.dueOnDisplay"> · {{ followUp.dueOnDisplay }}</span>
                      </p>
                    </div>
                  </div>

                  <div v-if="workspace.currentUserIsOrganizer && workspace.meetingMode === 'live'" class="space-y-4">
                    <label class="grid gap-2 text-sm font-medium text-stone-700">
                      <span>Statut</span>
                      <select class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="currentItem.discussionStatus" @change="updateCurrentItemField('discussionStatus', $event.target.value)">
                        <option value="pending">Pending</option>
                        <option value="treated">Treated</option>
                        <option value="postponed">Postponed</option>
                      </select>
                    </label>

                    <label class="grid gap-2 text-sm font-medium text-stone-700">
                      <span>Notes</span>
                      <textarea class="min-h-28 rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="currentItem.discussionNotes ?? ''" @input="updateCurrentItemField('discussionNotes', $event.target.value)" />
                    </label>

                    <div class="grid gap-4 md:grid-cols-2">
                      <label class="grid gap-2 text-sm font-medium text-stone-700">
                        <span>Owner</span>
                        <select class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" :value="currentItem.owner?.id ?? ''" @change="updateCurrentItemField('owner', participants.find((invitee) => String(invitee.id) === $event.target.value) ?? null)">
                          <option value="">Non assigne</option>
                          <option v-for="invitee in participants" :key="invitee.id" :value="invitee.id">{{ invitee.displayName }}</option>
                        </select>
                      </label>
                      <label class="grid gap-2 text-sm font-medium text-stone-700">
                        <span>Echeance</span>
                        <input class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm" type="date" :value="currentItem.dueOn ?? ''" @input="updateCurrentItemField('dueOn', $event.target.value || null)">
                      </label>
                    </div>

                    <div class="space-y-3">
                      <div class="flex items-center justify-between gap-4">
                        <p class="text-sm font-medium text-stone-700">Creer des follow-ups dans ce workspace</p>
                        <button type="button" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="addFollowUpDraft">Ajouter</button>
                      </div>

                      <div v-for="(followUp, followUpIndex) in ensureDraftFollowUps(currentItem)" :key="followUpIndex" class="grid gap-3 rounded-2xl border border-stone-200 bg-stone-50 p-4 xl:grid-cols-[minmax(0,1.8fr)_minmax(0,1fr)_11rem_auto]">
                        <input class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" type="text" :value="followUp.title ?? ''" placeholder="Titre du suivi" @input="updateFollowUpDraft(followUpIndex, 'title', $event.target.value)">
                        <select class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" :value="followUp.ownerId ?? ''" @change="updateFollowUpDraft(followUpIndex, 'ownerId', $event.target.value)">
                          <option value="">Assignee</option>
                          <option v-for="invitee in participants" :key="invitee.id" :value="invitee.id">{{ invitee.displayName }}</option>
                        </select>
                        <input class="rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm" type="date" :value="followUp.dueOn ?? ''" @input="updateFollowUpDraft(followUpIndex, 'dueOn', $event.target.value)">
                        <div class="flex items-end justify-end">
                          <button type="button" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="removeFollowUpDraft(followUpIndex)">Supprimer</button>
                        </div>
                      </div>
                    </div>

                    <div class="flex justify-end">
                      <button type="button" class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:opacity-60" :disabled="isSaving" @click="saveDiscussion">
                        {{ isSaving ? 'Enregistrement...' : 'Enregistrer' }}
                      </button>
                    </div>
                  </div>
                </div>
              </article>
            </div>

            <div v-if="vetoedItems.length" class="rounded-[1.25rem] border border-dashed border-stone-300 bg-stone-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Vetoed</p>
              <div class="mt-3 flex flex-wrap gap-2">
                <span v-for="item in vetoedItems" :key="item.id" class="rounded-full bg-red-100 px-3 py-1 text-sm font-medium text-red-700">{{ item.title }}</span>
              </div>
            </div>

            <div v-if="resolvedItems.length" class="rounded-[1.25rem] border border-stone-200 bg-stone-50 p-4">
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Resolved</p>
              <div class="mt-3 space-y-2">
                <p v-for="item in resolvedItems" :key="item.id" class="text-sm text-stone-700">{{ item.title }}</p>
              </div>
            </div>
          </div>
        </div>

        <div class="space-y-6">
          <div class="tw-toastit-card p-6">
            <h2 class="text-lg font-semibold text-stone-950">Membres</h2>
            <div class="mt-6 space-y-3">
              <div v-for="membership in members" :key="membership.id" class="flex items-center justify-between gap-4 rounded-xl border border-stone-200 px-4 py-3">
                <div class="flex items-center gap-3">
                  <span class="inline-grid h-8 w-8 place-items-center rounded-full bg-stone-100 font-semibold text-stone-700">{{ membership.user.initials }}</span>
                  <span class="font-medium text-stone-900">{{ membership.user.displayName }}</span>
                </div>
                <button v-if="workspace.currentUserIsOrganizer && membership.user.id !== workspace.organizerId" type="button" class="rounded-full border border-stone-200 px-4 py-2 text-sm font-medium text-stone-700" @click="removeMember(membership.id)">Retirer</button>
                <span v-else-if="membership.user.id === workspace.organizerId" class="rounded-full bg-stone-100 px-3 py-1 text-xs font-semibold text-stone-700">Organisateur</span>
              </div>
            </div>

            <div v-if="workspace.currentUserIsOrganizer" class="mt-6 space-y-3">
              <label class="grid gap-2 text-sm font-medium text-stone-700">
                <span>Inviter par email</span>
                <input v-model="inviteEmail" class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="email">
              </label>
              <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" @click="inviteMember">Inviter</button>
            </div>
          </div>
        </div>
      </div>
    </template>
  </section>
</template>
