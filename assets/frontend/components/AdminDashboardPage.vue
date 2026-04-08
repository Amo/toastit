<script setup>
import { computed, onMounted, ref } from 'vue';
import { ToastitApiClient } from '../api/ToastitApiClient';
import EmptyState from './EmptyState.vue';
import PageHeader from './PageHeader.vue';

const props = defineProps({
  accessToken: { type: String, required: true },
});

const payload = ref({
  overview: {
    otpRequestsOverTime: [],
    emailsReceivedOverTime: [],
    xaiCallsOverTime: [],
  },
  users: [],
});
const isLoading = ref(true);
const api = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});

const fetchDashboard = async () => {
  isLoading.value = true;
  const { ok, data } = await api.getJson('/api/admin/dashboard');
  payload.value = ok && data ? data : payload.value;
  isLoading.value = false;
};

const statCards = computed(() => [
  {
    label: 'OTP requests',
    value: payload.value.overview.otpRequestsOverTime.reduce((sum, item) => sum + item.count, 0),
  },
  {
    label: 'Inbound emails',
    value: payload.value.overview.emailsReceivedOverTime.reduce((sum, item) => sum + item.count, 0),
  },
  {
    label: 'xAI calls',
    value: payload.value.overview.xaiCallsOverTime.reduce((sum, item) => sum + item.count, 0),
  },
  {
    label: 'Users',
    value: payload.value.users.length,
  },
]);

const openUsers = () => {
  window.location.href = '/admin/users';
};

onMounted(() => {
  fetchDashboard();
});
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <PageHeader
      eyebrow="Admin"
      title="ROOT overview."
      :stats="statCards.map((item) => ({ label: `${item.value} ${item.label}`, className: 'bg-stone-100 text-stone-600 uppercase tracking-[0.18em] text-xs font-semibold' }))"
      :actions="[{ id: 'users', label: 'User list', icon: 'fa-solid fa-users', theme: 'secondary' }]"
      @action="openUsers"
    />

    <div class="tw-toastit-card p-6">
      <EmptyState v-if="isLoading" message="Loading admin overview..." />
      <div v-else class="grid gap-6 lg:grid-cols-3">
        <section class="space-y-3">
          <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-500">OTP requests</h2>
          <div class="rounded-2xl border border-stone-200 bg-stone-50">
            <div v-for="item in payload.overview.otpRequestsOverTime" :key="item.day" class="flex items-center justify-between border-b border-stone-200 px-4 py-3 text-sm last:border-b-0">
              <span>{{ item.day }}</span>
              <span class="font-semibold">{{ item.count }}</span>
            </div>
          </div>
        </section>

        <section class="space-y-3">
          <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-500">Inbound emails</h2>
          <div class="rounded-2xl border border-stone-200 bg-stone-50">
            <div v-for="item in payload.overview.emailsReceivedOverTime" :key="item.day" class="flex items-center justify-between border-b border-stone-200 px-4 py-3 text-sm last:border-b-0">
              <span>{{ item.day }}</span>
              <span class="font-semibold">{{ item.count }}</span>
            </div>
          </div>
        </section>

        <section class="space-y-3">
          <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-stone-500">xAI calls</h2>
          <div class="rounded-2xl border border-stone-200 bg-stone-50">
            <div v-for="item in payload.overview.xaiCallsOverTime" :key="item.day" class="flex items-center justify-between border-b border-stone-200 px-4 py-3 text-sm last:border-b-0">
              <span>{{ item.day }}</span>
              <span class="font-semibold">{{ item.count }}</span>
            </div>
          </div>
        </section>
      </div>
    </div>
  </section>
</template>
