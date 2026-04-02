<script setup>
import { nextTick, onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { AuthApi } from '../api/auth';
import { authState, authStore } from '../authStore';
import CenteredAuthCard from './CenteredAuthCard.vue';
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';
import SecondaryActionButton from './SecondaryActionButton.vue';
import SegmentedCodeInput from './SegmentedCodeInput.vue';

defineEmits(['dismiss-flash']);

const props = defineProps({
  email: { type: String, default: '' },
  flashes: { type: Object, required: true },
});

const router = useRouter();
const api = new AuthApi(new ToastitApiClient(''));
const pin = ref('');
const errorMessage = ref('');
const pinInputRef = ref(null);

const submit = async () => {
  if (pin.value.length < 4) {
    return;
  }

  const { ok, data } = await api.unlockPin({
    pin: pin.value,
    refreshToken: authState.pendingPinUnlockToken ? '' : authState.refreshToken,
    pinUnlockToken: authState.pendingPinUnlockToken,
  });

  if (!ok || !data) {
    errorMessage.value = 'Invalid PIN.';
    pin.value = '';
    nextTick(() => pinInputRef.value?.focusFirst());
    return;
  }

  authStore.setAuthenticated(data);
  router.replace(authStore.consumeReturnToPath());
};

const forgotPin = async () => {
  await api.requestPinReset(props.email);
  router.replace({ path: '/connexion/verifier', query: { email: props.email, purpose: 'reset_pin' } });
};

const logout = () => {
  authStore.logout();
  window.location.href = '/';
};

onMounted(() => {
  pinInputRef.value?.focusFirst();
});
</script>

<template>
  <CenteredAuthCard>
      <PageHero
        eyebrow="Unlock"
        title="Enter your PIN."
        :description="`The session is authenticated for ${email}, but the PIN is required to access the app.`"
      />

      <FlashMessages :success="flashes.success" :error="flashes.error" @dismiss="$emit('dismiss-flash', $event)" />
      <p v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</p>

      <form class="space-y-5" @submit.prevent="submit">
        <SegmentedCodeInput
          ref="pinInputRef"
          v-model="pin"
          :length="4"
          input-type="tel"
          input-mode="numeric"
          autocomplete="one-time-code"
          :pattern="/[0-9]/"
          :mask="true"
          @complete="submit"
        />
      </form>

      <div class="flex flex-wrap items-center justify-center gap-3">
        <SecondaryActionButton @click="forgotPin">I forgot my PIN</SecondaryActionButton>
        <SecondaryActionButton @click="logout">Log out</SecondaryActionButton>
      </div>
  </CenteredAuthCard>
</template>
