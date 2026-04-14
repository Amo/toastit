<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { ToastitApiClient } from '../api/ToastitApiClient';
import { AuthApi } from '../api/auth';
import { authStore } from '../authStore';
import FlashMessages from './FlashMessages.vue';
import PageHero from './PageHero.vue';
import PrimaryActionButton from './PrimaryActionButton.vue';
import SecondaryActionButton from './SecondaryActionButton.vue';

defineEmits(['dismiss-flash']);

const props = defineProps({
  email: { type: String, default: '' },
  dashboardUrl: { type: String, required: true },
  isAuthenticated: { type: Boolean, required: true },
  flashes: { type: Object, required: true },
});

const router = useRouter();
const api = new AuthApi(new ToastitApiClient(''));
const emailValue = ref(props.email);
const errorMessage = ref('');

const benefitPills = [
  'Meeting todo tracking built-in',
  'AI powered summaries',
  'AI assistant for execution',
  'Email commands to update tasks fast',
  'Secure confirmations for uncertain actions',
  'My Actions first dashboard',
];

const changelogHighlights = [
  {
    icon: 'fa-solid fa-square-check',
    title: 'Meeting todo flow',
    description: 'Capture decisions and follow-ups during meetings, with clear owners and due dates.',
  },
  {
    icon: 'fa-solid fa-sparkles',
    title: 'AI powered summary',
    description: 'Generate concise meeting summaries from the full discussion context.',
  },
  {
    icon: 'fa-solid fa-robot',
    title: 'AI assistant',
    description: 'Get guided next actions and execution support directly in your workflow.',
  },
  {
    icon: 'fa-solid fa-envelope-open-text',
    title: 'Command-based email workflow',
    description: 'Reply with concise commands like assign, due, comment, move, update, and reword.',
  },
  {
    icon: 'fa-solid fa-shield-halved',
    title: 'Confidence-gated actions',
    description: 'Clear commands apply instantly, and lower-confidence actions are confirmed with authenticated links.',
  },
  {
    icon: 'fa-solid fa-list-check',
    title: 'Simplified dashboard',
    description: 'My Actions is now the main focus, with a compact workspace rail for quick navigation.',
  },
];

const submit = async () => {
  const { ok, data } = await api.requestOtp(emailValue.value);

  if (!ok) {
    errorMessage.value = data?.error === 'invalid_email' ? 'Please enter a valid email address.' : 'Unable to request a code.';
    return;
  }

  authStore.setPendingPinUnlock('', null, emailValue.value);
  router.push({ path: '/connexion/verifier', query: { email: emailValue.value, purpose: 'login' } });
};

const logout = () => {
  authStore.logout();
  window.location.href = '/';
};
</script>

<template>
  <section class="space-y-10 py-8 lg:space-y-12 lg:py-12">
    <div class="mx-auto max-w-4xl space-y-6">
      <PageHero
        eyebrow="Toastit"
        title="Decisions, owners, and follow-through. Without process overhead."
        description="Toastit keeps the path from note to accountable action simple, and now works faster from both inbox and dashboard."
      />

      <div class="flex flex-wrap justify-center gap-2.5">
        <span
          v-for="pill in benefitPills"
          :key="pill"
          class="rounded-full border border-amber-200 bg-amber-50 px-4 py-2 text-sm font-medium text-amber-800"
        >
          {{ pill }}
        </span>
      </div>
    </div>

    <aside class="mx-auto w-full max-w-5xl rounded-[2rem] border border-stone-200 bg-white p-6 shadow-xl shadow-stone-200/70 lg:p-7">
      <div class="grid gap-6 lg:grid-cols-2 lg:items-center">
        <div class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Access</p>
          <h2 class="text-2xl font-semibold tracking-tight text-stone-950">Open your workspace.</h2>
          <p class="text-sm leading-6 text-stone-600">Use your email to receive a login code or magic link. No password to remember.</p>
        </div>

        <div class="w-full space-y-4">
          <FlashMessages :success="flashes.success" :error="flashes.error" @dismiss="$emit('dismiss-flash', $event)" />
          <p v-if="errorMessage" class="rounded-2xl border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-700">{{ errorMessage }}</p>

          <div v-if="isAuthenticated" class="space-y-4">
            <p class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-700">You are already signed in.</p>
            <div class="flex flex-wrap gap-3">
              <PrimaryActionButton :href="dashboardUrl">
                Open app
              </PrimaryActionButton>
              <SecondaryActionButton @click="logout">Sign out</SecondaryActionButton>
            </div>
          </div>

          <form v-else class="w-full" @submit.prevent="submit">
            <label class="relative block rounded-[1.75rem] border border-stone-200 bg-white p-2 transition focus-within:border-amber-400">
              <span class="sr-only">Email address</span>
              <input
                v-model="emailValue"
                class="h-12 w-full appearance-none border-0 bg-transparent px-4 pr-20 text-base leading-none outline-none shadow-none ring-0 focus:border-0 focus:ring-0"
                type="email"
                name="email"
                placeholder="fullname@company.com"
                required
              >

              <button
                type="submit"
                class="absolute right-2 top-1/2 inline-flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-amber-200 text-amber-900 shadow-sm transition hover:bg-amber-300"
                aria-label="Continue"
              >
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
              </button>
            </label>
          </form>
        </div>
      </div>
    </aside>

    <aside class="mx-auto w-full max-w-5xl space-y-4 rounded-[2rem] border border-stone-200 bg-white p-6 shadow-sm shadow-stone-200/70 lg:p-7">
      <div class="space-y-2">
        <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">What changed recently</p>
        <h2 class="text-2xl font-semibold tracking-tight text-stone-950">Latest product updates</h2>
      </div>
      <div class="grid gap-4 md:grid-cols-3">
        <article
          v-for="highlight in changelogHighlights"
          :key="highlight.title"
          class="rounded-[1.5rem] border border-stone-200 bg-white p-5"
        >
          <div class="inline-grid h-11 w-11 place-items-center rounded-2xl bg-stone-950 text-sm text-white">
            <i :class="highlight.icon" aria-hidden="true"></i>
          </div>
          <h2 class="mt-4 text-lg font-semibold text-stone-950">{{ highlight.title }}</h2>
          <p class="mt-2 text-sm leading-6 text-stone-600">{{ highlight.description }}</p>
        </article>
      </div>
    </aside>
  </section>
</template>
