<script setup>
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';

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
            <a class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" :href="dashboardUrl">
              Open app
            </a>
            <form method="post" :action="logoutUrl">
              <button class="button toastit-button-notice" type="submit">Sign out</button>
            </form>
          </div>
        </div>

        <form v-else method="post" :action="loginAction" class="space-y-4">
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Email address</span>
            <input class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="email" name="email" :value="email" placeholder="hello@toastit.app" required>
          </label>

          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" type="submit">
            Continue
          </button>
        </form>
      </div>
    </section>
  </main>
</template>
