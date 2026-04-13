<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { AuthApi } from '../api/auth';
import { authStore } from '../authStore';

const props = defineProps({
  email: { type: String, default: '' },
});

const router = useRouter();
const api = new AuthApi(new ToastitApiClient(''));
const emailValue = ref(props.email || authStore.getLastLoginEmail());
const errorMessage = ref('');
const pending = ref(false);

const submit = async () => {
  pending.value = true;
  const { ok, data } = await api.requestOtp(emailValue.value);
  pending.value = false;

  if (!ok) {
    errorMessage.value = data?.error === 'invalid_email' ? 'Please enter a valid email address.' : 'Unable to request a code.';
    return;
  }

  authStore.rememberLastLoginEmail(emailValue.value);
  authStore.setPendingPinUnlock('', null, emailValue.value);
  router.push({ path: '/connexion/verifier', query: { email: emailValue.value, purpose: 'login' } });
};
</script>

<template>
  <form class="space-y-4 px-6 py-6" @submit.prevent="submit">
    <p v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</p>

    <label class="relative block rounded-[1.75rem] border border-stone-200 bg-white p-2 transition focus-within:border-amber-400">
      <span class="sr-only">Email address</span>
      <input
        v-model="emailValue"
        class="h-12 w-full appearance-none border-0 bg-transparent px-4 pr-20 text-base leading-none outline-none shadow-none ring-0 focus:border-0 focus:ring-0"
        type="email"
        name="email"
        placeholder="fullname@company.com"
        required
      >

      <button
        type="submit"
        class="absolute right-2 top-1/2 inline-flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-amber-500 text-stone-950 shadow-sm transition hover:bg-amber-400 disabled:cursor-not-allowed disabled:opacity-60"
        :disabled="pending"
        aria-label="Continue"
      >
        <i v-if="!pending" class="fa-solid fa-arrow-right" aria-hidden="true"></i>
        <i v-else class="fa-solid fa-spinner animate-spin" aria-hidden="true"></i>
      </button>
    </label>
  </form>
</template>
