<script setup>
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';
import PrimaryActionButton from './PrimaryActionButton.vue';
import SecondaryActionButton from './SecondaryActionButton.vue';
import TextInputField from './TextInputField.vue';

const props = defineProps({
  email: { type: String, default: '' },
  loginAction: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  logoutUrl: { type: String, required: true },
  isAuthenticated: { type: Boolean, required: true },
  flashes: { type: Object, required: true },
});
</script>

<template>
  <main class="toastit-shell">
    <section class="tw-toastit-card mx-auto w-full max-w-xl p-8">
      <div class="space-y-6">
        <PageHero
          eyebrow="Toastit"
          title="Toast it. Get it done."
          description="Turn sticky notes into shared plans and real results. One workspace for solo work, 1:1s, and team meetings."
        />

        <FlashMessages :success="flashes.success" :error="flashes.error" />

        <div v-if="isAuthenticated" class="space-y-4">
          <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">You are already signed in.</p>
          <div class="flex flex-wrap gap-3">
            <PrimaryActionButton :href="dashboardUrl">
              Open app
            </PrimaryActionButton>
            <form method="post" :action="logoutUrl">
              <SecondaryActionButton type="submit">Sign out</SecondaryActionButton>
            </form>
          </div>
        </div>

        <form v-else method="post" :action="loginAction" class="space-y-4">
          <TextInputField label="Email address" type="email" name="email" :value="email" placeholder="hello@toastit.app" required />

          <PrimaryActionButton type="submit">
            Continue
          </PrimaryActionButton>
        </form>
      </div>
    </section>
  </main>
</template>
