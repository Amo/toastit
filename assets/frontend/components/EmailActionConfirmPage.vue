<script setup>
import { onMounted, ref } from 'vue';

const props = defineProps({
  token: { type: String, required: true },
});

const isLoading = ref(true);
const isSuccess = ref(false);
const message = ref('Applying confirmation...');

onMounted(async () => {
  try {
    const response = await fetch(`/api/inbound/action/${encodeURIComponent(props.token)}/confirm`, {
      method: 'GET',
      headers: {
        Accept: 'application/json',
      },
    });

    const payload = await response.json();
    isSuccess.value = Boolean(payload?.ok);
    message.value = String(payload?.message ?? 'Could not process this confirmation link.');
  } catch (error) {
    isSuccess.value = false;
    message.value = 'Could not process this confirmation link.';
  } finally {
    isLoading.value = false;
  }
});
</script>

<template>
  <section class="flex min-h-[60vh] items-center justify-center px-6">
    <div class="w-full max-w-xl rounded-3xl border border-stone-200 bg-white p-8 text-center shadow-sm">
      <p class="text-xs font-semibold uppercase tracking-[0.18em]" :class="isLoading ? 'text-stone-500' : (isSuccess ? 'text-emerald-600' : 'text-red-600')">
        {{ isLoading ? 'Working' : (isSuccess ? 'Confirmed' : 'Unable to confirm') }}
      </p>
      <h1 class="mt-3 text-2xl font-semibold tracking-tight text-stone-950">
        {{ isLoading ? 'Applying your action...' : message }}
      </h1>
    </div>
  </section>
</template>
