<script setup>
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';
import PrimaryActionButton from './PrimaryActionButton.vue';
import SecondaryActionButton from './SecondaryActionButton.vue';
import TextInputField from './TextInputField.vue';

const props = defineProps({
  email: { type: String, default: '' },
  unlockAction: { type: String, required: true },
  forgotPinAction: { type: String, required: true },
  flashes: { type: Object, required: true },
});
</script>

<template>
  <main class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <div class="space-y-6">
        <PageHero
          eyebrow="Unlock"
          title="Enter your PIN."
          :description="`The session is authenticated for ${email}, but the PIN is required to access the app.`"
        />

        <FlashMessages :success="flashes.success" :error="flashes.error" />

        <form method="post" :action="unlockAction" class="space-y-4">
          <TextInputField label="PIN" type="password" inputmode="numeric" pattern="[0-9]{4}" name="pin" maxlength="4" required />
          <PrimaryActionButton type="submit">
            Unlock
          </PrimaryActionButton>
        </form>

        <form method="post" :action="forgotPinAction">
          <SecondaryActionButton type="submit">I forgot my PIN</SecondaryActionButton>
        </form>
      </div>
    </section>
  </main>
</template>
