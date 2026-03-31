<script setup>
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
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.22em] text-amber-600">Toastit</p>
          <h1 class="text-4xl font-semibold tracking-tight text-stone-950">Une interface mobile-first pour agir vite, sans surcharge.</h1>
          <p class="text-base leading-7 text-stone-600">
            Toastit centralise l'essentiel dans une experience legere: le serveur fait le gros du travail, et le front reste net, tactile et immediat.
          </p>
        </div>

        <div class="space-y-3">
          <p v-for="(message, index) in flashes.success" :key="`success-${index}`" class="rounded-2xl border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-700">{{ message }}</p>
          <p v-for="(message, index) in flashes.error" :key="`error-${index}`" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ message }}</p>
        </div>

        <div v-if="isAuthenticated" class="space-y-4">
          <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">Vous etes deja connecte.</p>
          <div class="flex flex-wrap gap-3">
            <a class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" :href="dashboardUrl">
              Ouvrir l'application
            </a>
            <form method="post" :action="logoutUrl">
              <button class="button toastit-button-notice" type="submit">Se deconnecter</button>
            </form>
          </div>
        </div>

        <form v-else method="post" :action="loginAction" class="space-y-4">
          <label class="grid gap-2 text-sm font-medium text-stone-700">
            <span>Adresse email</span>
            <input class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base" type="email" name="email" :value="email" placeholder="hello@toastit.app" required>
          </label>

          <button class="rounded-full bg-amber-500 px-5 py-3 text-sm font-semibold text-stone-950 shadow-sm transition hover:bg-amber-400" type="submit">
            Continuer
          </button>
        </form>
      </div>
    </section>
  </main>
</template>
