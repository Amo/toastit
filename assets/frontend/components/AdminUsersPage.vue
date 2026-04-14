<script setup>
import { onMounted, ref } from 'vue';
import { ToastitApiClient } from '../api/ToastitApiClient';
import EmptyState from './EmptyState.vue';
import PageHeader from './PageHeader.vue';

const props = defineProps({
  accessToken: { type: String, required: true },
});

const isLoading = ref(true);
const users = ref([]);
const prunableUsers = ref([]);
const deletingUserId = ref(null);
const api = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});

const fetchUsers = async () => {
  isLoading.value = true;
  const { ok, data } = await api.getJson('/api/admin/users');
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

const openOverview = () => {
  window.location.href = '/admin';
};

const openPrompts = () => {
  window.location.href = '/admin/prompts';
};

onMounted(() => {
  fetchUsers();
});
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <PageHeader
      eyebrow="Admin"
      title="ROOT users."
      :stats="[
        { label: `${users.length} connected users`, className: 'bg-stone-100 text-stone-600 uppercase tracking-[0.18em] text-xs font-semibold' },
        { label: `${prunableUsers.length} cleanup candidates`, className: 'bg-amber-100 text-amber-700 uppercase tracking-[0.18em] text-xs font-semibold' },
      ]"
      :actions="[
        { id: 'overview', label: 'Overview', icon: 'fa-solid fa-chart-column', theme: 'secondary' },
        { id: 'prompts', label: 'Prompts', icon: 'fa-solid fa-file-code', theme: 'secondary' },
      ]"
      @action="(id) => { if (id === 'overview') openOverview(); else if (id === 'prompts') openPrompts(); }"
    />

    <div class="tw-toastit-card overflow-hidden p-6">
      <EmptyState v-if="isLoading" message="Loading users..." />
      <EmptyState v-else-if="!users.length" message="No users found." />
      <div v-else class="overflow-x-auto">
        <table class="min-w-full text-left text-sm">
          <thead class="border-b border-stone-200 text-stone-500">
            <tr>
              <th class="px-3 py-3 font-medium">Email</th>
              <th class="px-3 py-3 font-medium">Created</th>
              <th class="px-3 py-3 font-medium">Last connection</th>
              <th class="px-3 py-3 font-medium">Workspaces</th>
              <th class="px-3 py-3 font-medium">Toasts</th>
              <th class="px-3 py-3 font-medium">Active toasts</th>
              <th class="px-3 py-3 font-medium">xAI calls</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="user in users" :key="user.id" class="border-b border-stone-100">
              <td class="px-3 py-3">{{ user.email }}</td>
              <td class="px-3 py-3">{{ user.createdAt }}</td>
              <td class="px-3 py-3">{{ user.lastConnectionAt ?? 'Never' }}</td>
              <td class="px-3 py-3">{{ user.workspaceCount }}</td>
              <td class="px-3 py-3">{{ user.totalToastCount }}</td>
              <td class="px-3 py-3">{{ user.activeToastCount }}</td>
              <td class="px-3 py-3">{{ user.totalXaiCallCount }}</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div class="tw-toastit-card overflow-hidden p-6">
      <div class="mb-4">
        <h3 class="text-base font-semibold text-stone-950">Inactive accounts cleanup</h3>
        <p class="mt-1 text-sm text-stone-600">Never connected, no toast, created at least 7 days ago.</p>
      </div>
      <EmptyState v-if="isLoading" message="Loading inactive accounts..." />
      <EmptyState v-else-if="!prunableUsers.length" message="No inactive account to clean up." />
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
