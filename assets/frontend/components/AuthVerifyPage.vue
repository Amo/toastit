<script setup>
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';
import PrimaryActionButton from './PrimaryActionButton.vue';
import TextInputField from './TextInputField.vue';

const props = defineProps({
  email: { type: String, default: '' },
  purpose: { type: String, default: 'login' },
  verifyAction: { type: String, required: true },
  flashes: { type: Object, required: true },
});
</script>

<template>
  <main class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <div class="space-y-6">
        <PageHero
          eyebrow="Verification"
          title="Enter the code you received by email."
          description="The magic link in the email signs you in directly. If that button does not work, use the backup OTP code."
        />

        <FlashMessages :success="flashes.success" :error="flashes.error" />

        <form method="post" :action="verifyAction" class="space-y-4">
          <input type="hidden" name="email" :value="email">
          <input type="hidden" name="purpose" :value="purpose">
          <TextInputField label="Code OTP" name="code" type="text" autocomplete="one-time-code" maxlength="6" required />
          <PrimaryActionButton type="submit">
            Verify code
          </PrimaryActionButton>
        </form>
      </div>
    </section>
  </main>
</template>
