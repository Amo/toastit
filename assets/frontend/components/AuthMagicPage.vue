<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { AuthApi } from '../api/auth';
import { authStore } from '../authStore';
import EmptyState from './EmptyState.vue';

const route = useRoute();
const router = useRouter();
const errorMessage = ref('');

const api = new AuthApi(new ToastitApiClient(''));

onMounted(async () => {
  const { ok, data } = await api.consumeMagicLink(route.params.selector, route.params.token);

  if (!ok || !data) {
    errorMessage.value = 'This magic link is invalid or expired.';
    return;
  }

  if (data.requiresPinSetup) {
    authStore.setPendingPinSetup(data.pinSetupToken, data.user, data.user?.email);
    router.replace('/pin/setup');
    return;
  }

  if (data.requiresPinUnlock) {
    authStore.setPendingPinUnlock(data.pinUnlockToken, data.user, data.user?.email);
    router.replace('/pin/unlock');
    return;
  }
});
</script>

<template>
  <main class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <EmptyState v-if="!errorMessage" message="Signing you in..." />
      <p v-else class="text-sm text-rose-700">{{ errorMessage }}</p>
    </section>
  </main>
</template>
