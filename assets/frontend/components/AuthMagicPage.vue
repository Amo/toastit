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
const readyToContinue = ref(false);
const continuePending = ref(false);

const api = new AuthApi(new ToastitApiClient(''));

const completeMagicLink = async () => {
  continuePending.value = true;
  const { ok, data } = await api.finalizeMagicLink(route.params.selector, route.params.token);
  continuePending.value = false;

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
};

onMounted(async () => {
  const { ok, data } = await api.consumeMagicLink(route.params.selector, route.params.token);

  if (!ok || !data) {
    errorMessage.value = 'This magic link is invalid or expired.';
    return;
  }

  readyToContinue.value = true;
});
</script>

<template>
  <main class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <p v-if="errorMessage" class="text-sm text-rose-700">{{ errorMessage }}</p>
      <template v-else-if="readyToContinue">
        <EmptyState message="Your link is valid. Continue to sign in." />
        <div class="mt-6 text-center">
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-full bg-amber-500 px-5 py-3 font-semibold text-stone-950 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="continuePending"
            @click="completeMagicLink"
          >
            {{ continuePending ? 'Continuing...' : 'Continue' }}
          </button>
        </div>
      </template>
      <EmptyState v-else message="Checking your link..." />
    </section>
  </main>
</template>
