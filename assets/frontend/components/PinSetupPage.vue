<script setup>
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';
import TextInputField from './TextInputField.vue';

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

        <FlashMessages :error="flashes.error" />

        <form method="post" :action="setupAction" class="space-y-4">
          <TextInputField label="PIN" type="password" inputmode="numeric" pattern="[0-9]{4}" name="pin" maxlength="4" required />
          <TextInputField label="Confirmation" type="password" inputmode="numeric" pattern="[0-9]{4}" name="pin_confirmation" maxlength="4" required />
          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" type="submit">
            Save PIN
          </button>
        </form>
      </div>
    </section>
  </main>
</template>
