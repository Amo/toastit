<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue';
import { useRouter } from 'vue-router';
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
const isMobileViewport = ref(false);
const router = useRouter();
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

const syncViewport = () => {
  isMobileViewport.value = window.innerWidth < 1024;
};

const goBackToProfile = () => {
  router.push('/app/profile');
};

onMounted(() => {
  syncViewport();
  window.addEventListener('resize', syncViewport);
  fetchDashboard();
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
        <h1 class="line-clamp-2 text-xl font-semibold tracking-tight text-stone-950">Admin statistics</h1>
      </div>
    </div>

    <PageHeader
      v-if="!isMobileViewport"
      eyebrow="Admin"
      title="ROOT overview."
      :stats="statCards.map((item) => ({ label: `${item.value} ${item.label}`, className: 'bg-stone-100 text-stone-600 uppercase tracking-[0.18em] text-xs font-semibold' }))"
      :actions="[
        { id: 'users', label: 'User list', icon: 'fa-solid fa-users', theme: 'secondary' },
      ]"
      @action="(id) => { if (id === 'users') openUsers(); }"
    />

    <div :class="isMobileViewport ? 'tw-toastit-card rounded-none border-x-0 p-4' : 'tw-toastit-card p-6'">
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
