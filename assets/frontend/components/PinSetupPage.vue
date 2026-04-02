<script setup>
import PageHero from './PageHero.vue';

const props = defineProps({
  setupAction: { type: String, required: true },
  flashes: { type: Object, required: true },
});
</script>

<template>
  <main class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <div class="space-y-6">
        <PageHero
          eyebrow="PIN"
          title="Set your 4-digit PIN."
          description="This PIN will be required for each new session and whenever the session locks again."
        />

        <p v-for="(message, index) in flashes.error" :key="index" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ message }}</p>

        <form method="post" :action="setupAction" class="space-y-4">
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>PIN</span>
            <input class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="password" inputmode="numeric" pattern="[0-9]{4}" name="pin" maxlength="4" required>
          </label>
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Confirmation</span>
            <input class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="password" inputmode="numeric" pattern="[0-9]{4}" name="pin_confirmation" maxlength="4" required>
          </label>
          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" type="submit">
            Save PIN
          </button>
        </form>
      </div>
    </section>
  </main>
</template>
