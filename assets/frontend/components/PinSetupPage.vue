<script setup>
import { onMounted, ref } from 'vue';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { AuthApi } from '../api/auth';
import { authState, authStore } from '../authStore';
import CenteredAuthCard from './CenteredAuthCard.vue';
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';
import PrimaryActionButton from './PrimaryActionButton.vue';
import SegmentedCodeInput from './SegmentedCodeInput.vue';

defineEmits(['dismiss-flash']);

const props = defineProps({
  flashes: { type: Object, required: true },
});

const router = useRouter();
const api = new AuthApi(new ToastitApiClient(''));
const pin = ref('');
const pinConfirmation = ref('');
const errorMessage = ref('');
const pinInputRef = ref(null);

const submit = async () => {
  const { ok, data } = await api.setupPin(authState.pendingPinSetupToken, pin.value, pinConfirmation.value);
  if (!ok || !data) {
    errorMessage.value = 'Unable to save PIN.';
    return;
  }

  authStore.setAuthenticated(data);
  router.replace(authStore.consumeReturnToPath());
};

onMounted(() => {
  pinInputRef.value?.focusFirst();
});
</script>

<template>
  <CenteredAuthCard>
      <PageHero
        eyebrow="PIN"
        title="Set your 4-digit PIN."
        description="This PIN will be required for each new session and whenever the session locks again."
      />

      <FlashMessages :error="flashes.error" @dismiss="$emit('dismiss-flash', $event)" />
      <p v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</p>

      <form class="space-y-5" @submit.prevent="submit">
        <div class="space-y-3">
          <p class="text-center text-sm font-medium text-stone-500">PIN</p>
          <SegmentedCodeInput
            ref="pinInputRef"
            v-model="pin"
            :length="4"
            input-type="tel"
            input-mode="numeric"
            autocomplete="one-time-code"
            :pattern="/[0-9]/"
            :mask="true"
          />
        </div>
        <div class="space-y-3">
          <p class="text-center text-sm font-medium text-stone-500">Confirmation</p>
          <SegmentedCodeInput
            v-model="pinConfirmation"
            :length="4"
            input-type="tel"
            input-mode="numeric"
            autocomplete="one-time-code"
            :pattern="/[0-9]/"
            :mask="true"
          />
        </div>
        <PrimaryActionButton type="submit">
          Save PIN
        </PrimaryActionButton>
      </form>
  </CenteredAuthCard>
</template>
