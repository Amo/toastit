<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { AuthApi } from '../api/auth';
import { authStore } from '../authStore';
import CenteredAuthCard from './CenteredAuthCard.vue';
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';
import SegmentedCodeInput from './SegmentedCodeInput.vue';

defineEmits(['dismiss-flash']);

const props = defineProps({
  email: { type: String, default: '' },
  purpose: { type: String, default: 'login' },
  flashes: { type: Object, required: true },
});

const router = useRouter();
const api = new AuthApi(new ToastitApiClient(''));
const code = ref('');
const errorMessage = ref('');
const resendMessage = ref('');
const codeInputRef = ref(null);

const submit = async () => {
  if (code.value.length < 6) {
    return;
  }

  const { ok, data } = await api.verifyOtp(props.email, code.value, props.purpose);

  if (!ok || !data) {
    errorMessage.value = 'Invalid or expired code.';
    code.value = '';
    nextTick(() => codeInputRef.value?.focusFirst());
    return;
  }

  if (data.requiresPinSetup) {
    authStore.setPendingPinSetup(data.pinSetupToken, data.user, props.email);
    router.replace('/pin/setup');
    return;
  }

  if (data.requiresPinUnlock) {
    authStore.setPendingPinUnlock(data.pinUnlockToken, data.user, props.email);
    router.replace('/pin/unlock');
  }
};

const resendCode = async () => {
  const { ok } = await api.requestOtp(props.email);
  resendMessage.value = ok ? 'A fresh code has been sent.' : '';

  if (!ok) {
    errorMessage.value = 'Unable to resend the code right now.';
    return;
  }

  errorMessage.value = '';
};

const goBack = () => {
  router.replace('/');
};

onMounted(() => {
  codeInputRef.value?.focusFirst();
});
</script>

<template>
  <CenteredAuthCard>
      <PageHero
        eyebrow="Verification"
        title="Enter the code you received by email."
        :description="`We sent a 6-digit login code to ${email}.`"
      />

      <FlashMessages :success="flashes.success" :error="flashes.error" @dismiss="$emit('dismiss-flash', $event)" />
      <p v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</p>
      <p v-if="resendMessage" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ resendMessage }}</p>

      <form class="space-y-5" @submit.prevent="submit">
        <SegmentedCodeInput
          ref="codeInputRef"
          v-model="code"
          :length="6"
          autocomplete="one-time-code"
          input-type="tel"
          input-mode="numeric"
          :pattern="/[0-9]/"
          @complete="submit"
        />

        <div class="space-y-3 text-center text-sm">
          <button type="button" class="font-medium text-stone-500 transition hover:text-stone-900" @click="goBack">
            <i class="fa-solid fa-arrow-left mr-2"></i>Use another email
          </button>
          <div>
            <button type="button" class="font-medium text-amber-700 transition hover:text-amber-800" @click="resendCode">
              Resend the code
            </button>
          </div>
        </div>
      </form>
  </CenteredAuthCard>
</template>
