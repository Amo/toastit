<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { AuthApi } from '../api/auth';
import { authStore } from '../authStore';

const props = defineProps({
  recaptchaSiteKey: { type: String, default: '' },
});

const route = useRoute();
const router = useRouter();
const errorMessage = ref('');
const readyToContinue = ref(false);
const continuePending = ref(false);
let autoSubmitTimerId = null;
const RECAPTCHA_ACTION = 'magic_link_consume';

const api = new AuthApi(new ToastitApiClient(''));

const ensureRecaptcha = async () => {
  if (!props.recaptchaSiteKey) {
    return true;
  }

  const recaptchaReady = () => typeof window.grecaptcha !== 'undefined'
    && typeof window.grecaptcha.enterprise !== 'undefined'
    && typeof window.grecaptcha.enterprise.execute === 'function';

  if (recaptchaReady()) {
    return true;
  }

  const existing = document.querySelector('script[data-toastit-recaptcha="true"]');
  if (!existing) {
    const script = document.createElement('script');
    script.src = `https://www.google.com/recaptcha/enterprise.js?render=${encodeURIComponent(props.recaptchaSiteKey)}`;
    script.async = true;
    script.defer = true;
    script.dataset.toastitRecaptcha = 'true';
    document.head.appendChild(script);
  }

  const startedAt = Date.now();
  while (!recaptchaReady()) {
    if (Date.now() - startedAt > 5000) {
      return false;
    }
    await new Promise((resolve) => window.setTimeout(resolve, 100));
  }

  return true;
};

const buildRecaptchaPayload = async () => {
  if (!props.recaptchaSiteKey) {
    return {};
  }

  const recaptchaOk = await ensureRecaptcha();
  if (!recaptchaOk) {
    return null;
  }

  await new Promise((resolve) => window.grecaptcha.enterprise.ready(resolve));
  const recaptchaToken = await window.grecaptcha.enterprise.execute(props.recaptchaSiteKey, { action: RECAPTCHA_ACTION });

  if (!recaptchaToken) {
    return null;
  }

  return {
    recaptchaToken,
    recaptchaAction: RECAPTCHA_ACTION,
  };
};

const completeMagicLink = async () => {
  if (continuePending.value) {
    return;
  }

  if (null !== autoSubmitTimerId) {
    window.clearTimeout(autoSubmitTimerId);
    autoSubmitTimerId = null;
  }

  continuePending.value = true;
  const recaptchaPayload = await buildRecaptchaPayload();

  if (null === recaptchaPayload) {
    continuePending.value = false;
    errorMessage.value = 'Unable to validate security check. Please retry.';
    return;
  }

  const { ok, data } = await api.finalizeMagicLink(route.params.selector, route.params.token, recaptchaPayload);
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
  if (props.recaptchaSiteKey) {
    await ensureRecaptcha();
  }
  autoSubmitTimerId = window.setTimeout(() => {
    completeMagicLink();
  }, 2000);
});

onUnmounted(() => {
  if (null !== autoSubmitTimerId) {
    window.clearTimeout(autoSubmitTimerId);
    autoSubmitTimerId = null;
  }
});
</script>

<template>
  <main class="toastit-shell">
    <section class="mx-auto w-full max-w-xl p-2 text-center">
      <p v-if="errorMessage" class="text-sm text-rose-700">{{ errorMessage }}</p>
      <template v-else>
        <p class="inline-flex items-center justify-center gap-2 text-sm text-stone-600">
          <i class="fa-solid fa-spinner animate-spin" aria-hidden="true"></i>
          You are being logged in...
        </p>
        <div class="mt-6 flex justify-center">
          <button
            type="button"
            class="inline-flex items-center justify-center rounded-full bg-amber-500 px-5 py-3 font-semibold text-stone-950 transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-60"
            :disabled="true"
          >
            <i class="fa-solid fa-spinner mr-2 animate-spin" aria-hidden="true"></i>
            Logging in...
          </button>
        </div>
        <button
          v-if="readyToContinue"
          type="button"
          class="mt-3 text-xs text-stone-500 underline decoration-stone-300 underline-offset-2 transition hover:text-stone-700"
          @click="completeMagicLink"
        >
          click if nothing happens (if JavaScript did not submit)
        </button>
      </template>
    </section>
  </main>
</template>
