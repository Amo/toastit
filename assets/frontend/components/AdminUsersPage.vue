<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { authStore } from '../authStore';
import EmptyState from './EmptyState.vue';
import PageHeader from './PageHeader.vue';

const props = defineProps({
  accessToken: { type: String, required: true },
});

const isLoading = ref(true);
const isMobileViewport = ref(false);
const users = ref([]);
const prunableUsers = ref([]);
const currentUser = ref(null);
const deletingUserId = ref(null);
const togglingAdvancedAiUserId = ref(null);
const impersonatingUserId = ref(null);
const router = useRouter();
const api = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});

const canManageAdvancedAi = computed(() => currentUser.value?.isRoot === true);
const canOpenOverview = computed(() => currentUser.value?.isRoot === true);
const canOpenPrompts = computed(() => currentUser.value?.isRoot === true);

const roleBadges = (user) => {
  const badges = [];

  if (user?.isRoot) {
    badges.push({ label: 'ROOT', className: 'bg-stone-900 text-white' });
  }

  if (user?.isRoute) {
    badges.push({ label: 'ROUTE', className: 'bg-sky-100 text-sky-700' });
  }

  if (user?.advancedAiModelEnabled) {
    badges.push({ label: 'Grok 4.20', className: 'bg-amber-100 text-amber-800' });
  }

  return badges;
};

const fetchUsers = async () => {
  isLoading.value = true;
  const { ok, data } = await api.getJson('/api/admin/users');
  currentUser.value = ok && data ? data.currentUser ?? null : null;
  users.value = ok && data ? data.users ?? [] : [];
  prunableUsers.value = ok && data ? data.prunableUsers ?? [] : [];
  isLoading.value = false;
};

const deletePrunableUser = async (userId) => {
  if (!Number.isFinite(Number(userId))) {
    return;
  }

  if (!window.confirm('Delete this inactive account? This action cannot be undone.')) {
    return;
  }

  deletingUserId.value = Number(userId);
  const { ok } = await api.request(`/api/admin/users/${Number(userId)}`, { method: 'DELETE' });
  deletingUserId.value = null;

  if (ok) {
    await fetchUsers();
  }
};

const toggleAdvancedAiModel = async (user) => {
  if (!canManageAdvancedAi.value || !Number.isFinite(Number(user?.id))) {
    return;
  }

  togglingAdvancedAiUserId.value = Number(user.id);
  const { ok, data } = await api.putJson(`/api/admin/users/${Number(user.id)}/advanced-ai-model`, {
    enabled: !(user.advancedAiModelEnabled === true),
  });
  togglingAdvancedAiUserId.value = null;

  if (!ok || !data?.user) {
    return;
  }

  users.value = users.value.map((candidate) => (
    candidate.id === data.user.id ? data.user : candidate
  ));
};

const impersonateUser = async (user) => {
  if (!Number.isFinite(Number(user?.id))) {
    return;
  }

  impersonatingUserId.value = Number(user.id);
  const { ok, data } = await api.postJson(`/api/admin/users/${Number(user.id)}/impersonate`, {});
  impersonatingUserId.value = null;

  if (!ok || !data?.accessToken || !data?.user) {
    return;
  }

  authStore.startImpersonation(data);
  window.location.href = '/app';
};

const openOverview = () => {
  window.location.href = '/admin';
};

const openPrompts = () => {
  window.location.href = '/admin/prompts';
};

const syncViewport = () => {
  isMobileViewport.value = window.innerWidth < 1024;
};

const goBackToProfile = () => {
  router.push('/app/profile');
};

onMounted(() => {
  syncViewport();
  window.addEventListener('resize', syncViewport);
  fetchUsers();
});

onUnmounted(() => {
  window.removeEventListener('resize', syncViewport);
});
</script>

<template>
  <section :class="isMobileViewport ? 'space-y-4' : 'tw-toastit-shell space-y-6'">
    <div
      v-if="isMobileViewport"
      class="sticky top-0 z-40 border-b border-stone-200/80 bg-white/95 px-3 pb-3 backdrop-blur"
      :style="{ paddingTop: 'calc(0.5rem + env(safe-area-inset-top))' }"
    >
      <div class="flex items-center gap-3">
        <button
          type="button"
          class="inline-grid h-9 w-9 shrink-0 place-items-center rounded-full border border-stone-200 bg-white text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
          @click="goBackToProfile"
        >
          <i class="fa-solid fa-arrow-left text-sm" aria-hidden="true"></i>
          <span class="sr-only">Back</span>
        </button>
        <h1 class="line-clamp-2 text-xl font-semibold tracking-tight text-stone-950">Users routing</h1>
      </div>
    </div>

    <PageHeader
      v-if="!isMobileViewport"
      eyebrow="Admin"
      title="Users routing."
      :stats="[
        { label: `${users.length} connected users`, className: 'bg-stone-100 text-stone-600 uppercase tracking-[0.18em] text-xs font-semibold' },
        { label: `${prunableUsers.length} cleanup candidates`, className: 'bg-amber-100 text-amber-700 uppercase tracking-[0.18em] text-xs font-semibold' },
      ]"
      :actions="[
        ...(canOpenOverview ? [{ id: 'overview', label: 'Overview', icon: 'fa-solid fa-chart-column', theme: 'secondary' }] : []),
        ...(canOpenPrompts ? [{ id: 'prompts', label: 'Prompts', icon: 'fa-solid fa-file-code', theme: 'secondary' }] : []),
      ]"
      @action="(id) => { if (id === 'overview') openOverview(); else if (id === 'prompts') openPrompts(); }"
    />

    <div :class="isMobileViewport ? 'tw-toastit-card rounded-none border-x-0 p-4' : 'tw-toastit-card overflow-hidden p-6'">
      <EmptyState v-if="isLoading" message="Loading users..." />
      <EmptyState v-else-if="!users.length" message="No users found." />
      <div v-else-if="isMobileViewport" class="space-y-3">
        <article
          v-for="user in users"
          :key="`mobile-user-${user.id}`"
          class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm"
        >
          <p class="font-semibold text-stone-900">{{ user.email }}</p>
          <div class="mt-2 flex flex-wrap gap-2">
            <span v-for="badge in roleBadges(user)" :key="`${user.id}-${badge.label}`" class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="badge.className">{{ badge.label }}</span>
          </div>
          <p class="mt-1 text-xs text-stone-600">Created: {{ user.createdAt }}</p>
          <p class="mt-1 text-xs text-stone-600">Last connection: {{ user.lastConnectionAt ?? 'Never' }}</p>
          <p class="mt-1 text-xs text-stone-600">Workspaces: {{ user.workspaceCount }} • Toasts: {{ user.totalToastCount }} • Active: {{ user.activeToastCount }} • xAI: {{ user.totalXaiCallCount }}</p>
          <div class="mt-3 flex flex-wrap justify-end gap-2">
            <button
              type="button"
              class="rounded-full border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-semibold text-stone-700 transition hover:bg-stone-100 disabled:opacity-60"
              :disabled="impersonatingUserId === user.id"
              @click="impersonateUser(user)"
            >
              {{ impersonatingUserId === user.id ? 'Opening…' : 'Impersonate' }}
            </button>
            <button
              v-if="canManageAdvancedAi"
              type="button"
              class="rounded-full border px-3 py-1.5 text-xs font-semibold transition disabled:opacity-60"
              :class="user.advancedAiModelEnabled ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-stone-200 bg-white text-stone-700 hover:bg-stone-50'"
              :disabled="togglingAdvancedAiUserId === user.id"
              @click="toggleAdvancedAiModel(user)"
            >
              {{ togglingAdvancedAiUserId === user.id ? 'Saving…' : (user.advancedAiModelEnabled ? 'Disable Grok 4.20' : 'Enable Grok 4.20') }}
            </button>
          </div>
        </article>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="border-b border-stone-200 text-stone-500">
            <tr>
              <th class="px-3 py-3 font-medium">Email</th>
              <th class="px-3 py-3 font-medium">Flags</th>
              <th class="px-3 py-3 font-medium">Created</th>
              <th class="px-3 py-3 font-medium">Last connection</th>
              <th class="px-3 py-3 font-medium">Workspaces</th>
              <th class="px-3 py-3 font-medium">Toasts</th>
              <th class="px-3 py-3 font-medium">Active toasts</th>
              <th class="px-3 py-3 font-medium">xAI calls</th>
              <th class="px-3 py-3 font-medium text-right">Actions</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-b border-stone-100">
              <td class="px-3 py-3">{{ user.email }}</td>
              <td class="px-3 py-3">
                <div class="flex flex-wrap gap-2">
                  <span v-for="badge in roleBadges(user)" :key="`${user.id}-${badge.label}`" class="rounded-full px-2 py-1 text-[11px] font-semibold" :class="badge.className">{{ badge.label }}</span>
                </div>
              </td>
              <td class="px-3 py-3">{{ user.createdAt }}</td>
              <td class="px-3 py-3">{{ user.lastConnectionAt ?? 'Never' }}</td>
              <td class="px-3 py-3">{{ user.workspaceCount }}</td>
              <td class="px-3 py-3">{{ user.totalToastCount }}</td>
              <td class="px-3 py-3">{{ user.activeToastCount }}</td>
              <td class="px-3 py-3">{{ user.totalXaiCallCount }}</td>
              <td class="px-3 py-3 text-right">
                <div class="flex justify-end gap-2">
                  <button
                    type="button"
                    class="rounded-full border border-stone-200 bg-stone-50 px-3 py-1.5 text-xs font-semibold text-stone-700 transition hover:bg-stone-100 disabled:opacity-60"
                    :disabled="impersonatingUserId === user.id"
                    @click="impersonateUser(user)"
                  >
                    {{ impersonatingUserId === user.id ? 'Opening…' : 'Impersonate' }}
                  </button>
                  <button
                    v-if="canManageAdvancedAi"
                    type="button"
                    class="rounded-full border px-3 py-1.5 text-xs font-semibold transition disabled:opacity-60"
                    :class="user.advancedAiModelEnabled ? 'border-amber-200 bg-amber-50 text-amber-800 hover:bg-amber-100' : 'border-stone-200 bg-white text-stone-700 hover:bg-stone-50'"
                    :disabled="togglingAdvancedAiUserId === user.id"
                    @click="toggleAdvancedAiModel(user)"
                  >
                    {{ togglingAdvancedAiUserId === user.id ? 'Saving…' : (user.advancedAiModelEnabled ? 'Disable Grok 4.20' : 'Enable Grok 4.20') }}
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="currentUser?.isRoot" :class="isMobileViewport ? 'tw-toastit-card rounded-none border-x-0 p-4' : 'tw-toastit-card overflow-hidden p-6'">
      <div class="mb-4">
        <h3 class="text-base font-semibold text-stone-950">Inactive accounts cleanup</h3>
        <p class="mt-1 text-sm text-stone-600">Never connected, no toast, created at least 7 days ago.</p>
      </div>
      <EmptyState v-if="isLoading" message="Loading inactive accounts..." />
      <EmptyState v-else-if="!prunableUsers.length" message="No inactive account to clean up." />
      <div v-else-if="isMobileViewport" class="space-y-3">
        <article
          v-for="user in prunableUsers"
          :key="`mobile-prunable-${user.id}`"
          class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm"
        >
          <p class="font-semibold text-stone-900">{{ user.email }}</p>
          <p class="mt-1 text-xs text-stone-600">Created: {{ user.createdAt }}</p>
          <div class="mt-3 flex justify-end">
            <button
              type="button"
              class="rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100 disabled:opacity-60"
              :disabled="deletingUserId === user.id"
              @click="deletePrunableUser(user.id)"
            >
              {{ deletingUserId === user.id ? 'Deleting...' : 'Delete account' }}
            </button>
          </div>
        </article>
      </div>
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="border-b border-stone-200 text-stone-500">
            <tr>
              <th class="px-3 py-3 font-medium">Email</th>
              <th class="px-3 py-3 font-medium">Created</th>
              <th class="px-3 py-3 font-medium text-right">Action</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in prunableUsers" :key="`prunable-${user.id}`" class="border-b border-stone-100">
              <td class="px-3 py-3">{{ user.email }}</td>
              <td class="px-3 py-3">{{ user.createdAt }}</td>
              <td class="px-3 py-3 text-right">
                <button
                  type="button"
                  class="rounded-full border border-red-200 bg-red-50 px-3 py-1.5 text-xs font-semibold text-red-700 transition hover:bg-red-100 disabled:opacity-60"
                  :disabled="deletingUserId === user.id"
                  @click="deletePrunableUser(user.id)"
                >
                  {{ deletingUserId === user.id ? 'Deleting...' : 'Delete account' }}
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </section>
</template>
