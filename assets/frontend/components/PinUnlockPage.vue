<script setup>
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
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Unlock</p>
          <h1 class="text-4xl font-semibold tracking-tight text-stone-950">Enter your PIN.</h1>
          <p class="text-base leading-7 text-stone-600">The session is authenticated for {{ email }}, but the PIN is required to access the app.</p>
        </div>

        <div class="space-y-3">
          <p v-for="(message, index) in flashes.success" :key="`success-${index}`" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ message }}</p>
          <p v-for="(message, index) in flashes.error" :key="`error-${index}`" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ message }}</p>
        </div>

        <form method="post" :action="unlockAction" class="space-y-4">
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>PIN</span>
            <input class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="password" inputmode="numeric" pattern="[0-9]{4}" name="pin" maxlength="4" required>
          </label>
          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" type="submit">
            Unlock
          </button>
        </form>

        <form method="post" :action="forgotPinAction">
          <button class="button toastit-button-notice" type="submit">I forgot my PIN</button>
        </form>
      </div>
    </section>
  </main>
</template>
