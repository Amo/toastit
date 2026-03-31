<script setup>
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
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Verification</p>
          <h1 class="text-4xl font-semibold tracking-tight text-stone-950">Entrez le code recu par email.</h1>
          <p class="text-base leading-7 text-stone-600">Le lien magique de l'email vous connecte directement. Si le bouton ne fonctionne pas, utilisez le code OTP de secours.</p>
        </div>

        <div class="space-y-3">
          <p v-for="(message, index) in flashes.success" :key="`success-${index}`" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ message }}</p>
          <p v-for="(message, index) in flashes.error" :key="`error-${index}`" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ message }}</p>
        </div>

        <form method="post" :action="verifyAction" class="space-y-4">
          <input type="hidden" name="email" :value="email">
          <input type="hidden" name="purpose" :value="purpose">
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Code OTP</span>
            <input class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base tracking-[0.4em] uppercase" type="text" name="code" maxlength="6" autocomplete="one-time-code" required>
          </label>
          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" type="submit">
            Verifier le code
          </button>
        </form>
      </div>
    </section>
  </main>
</template>
