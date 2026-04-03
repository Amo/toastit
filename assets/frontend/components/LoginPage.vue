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
import TextInputField from './TextInputField.vue';

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
  'From idea to owner in minutes',
  'Great for solo work, 1:1s, team rituals',
  'Keeps decisions and follow-ups in the same place',
];

const featureCards = [
  {
    icon: 'fa-solid fa-bread-slice',
    title: 'Capture the right toast',
    description: 'Collect issues, ideas, decisions, follow-ups, and context before they vanish in chat or sticky notes.',
  },
  {
    icon: 'fa-solid fa-users',
    title: 'Toast together',
    description: 'Run structured meetings with votes, boosts, ownership, deadlines, and a clear record of what was decided.',
  },
  {
    icon: 'fa-regular fa-calendar-check',
    title: 'Turn momentum into execution',
    description: 'Carry the action forward with due dates, responsible owners, history, and the next follow-up already lined up.',
  },
];

const outcomes = [
  'Fewer “who owns this?” moments',
  'Cleaner weekly meetings and executive reviews',
  'Visible follow-through instead of forgotten action items',
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
        title="Turn rough notes into shared decisions, owners, and real follow-through."
        description="ToastIt gives your ideas a place to land, your meetings a structure to run, and your team a lightweight system to make decisions stick."
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
                class="absolute right-2 top-1/2 inline-flex h-12 w-12 -translate-y-1/2 items-center justify-center rounded-full bg-amber-500 text-stone-950 shadow-sm transition hover:bg-amber-400"
                aria-label="Continue"
              >
                <i class="fa-solid fa-arrow-right" aria-hidden="true"></i>
              </button>
            </label>
          </form>
        </div>
      </div>
    </aside>

    <div class="space-y-8">
      <div class="grid gap-4 md:grid-cols-3">
        <article
          v-for="card in featureCards"
          :key="card.title"
          class="rounded-[1.75rem] border border-stone-200 bg-white/90 p-5 shadow-sm shadow-stone-200/60"
        >
          <div class="inline-grid h-11 w-11 place-items-center rounded-2xl bg-stone-950 text-sm text-white">
            <i :class="card.icon" aria-hidden="true"></i>
          </div>
          <h2 class="mt-4 text-lg font-semibold text-stone-950">{{ card.title }}</h2>
          <p class="mt-2 text-sm leading-6 text-stone-600">{{ card.description }}</p>
        </article>
      </div>

      <div class="grid gap-4 lg:grid-cols-3">
        <div class="rounded-[2rem] border border-stone-200 bg-[linear-gradient(135deg,#fff7ed_0%,#ffffff_48%,#f5f5f4_100%)] p-6">
          <div class="flex items-start justify-between gap-6">
            <div>
              <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">What teams achieve</p>
              <ul class="mt-4 space-y-3">
                <li v-for="outcome in outcomes" :key="outcome" class="flex items-start gap-3 text-sm leading-6 text-stone-700">
                  <span class="mt-1 inline-block h-2.5 w-2.5 shrink-0 rounded-full bg-amber-400"></span>
                  <span>{{ outcome }}</span>
                </li>
              </ul>
            </div>
          </div>
        </div>

        <div class="rounded-[2rem] border border-stone-900 bg-stone-950 p-6 text-stone-50 shadow-xl shadow-stone-900/15">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-400">Why it works</p>
          <p class="mt-4 text-2xl font-semibold leading-tight">One flow for capture, prioritization, toasting, and follow-up.</p>
          <p class="mt-4 text-sm leading-6 text-stone-300">
            No split between “brain dump” and “project tracking”. ToastIt keeps the messy beginning and the accountable ending in the same workspace.
          </p>
        </div>

        <div class="rounded-[2rem] border border-amber-200 bg-amber-500 p-6 text-stone-950 shadow-xl shadow-amber-200/50">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-950/70">Why ToastIt</p>
          <p class="mt-4 text-2xl font-semibold leading-tight">It starts like a Post-it, but it does not stop at the idea.</p>
          <p class="mt-4 text-sm leading-6 text-amber-950/85">
            ToastIt is the moment a rough note gets prepared, selected, cooked, and turned into something that creates real value. A thought lands fast, then gets clarified, prioritized, assigned, and followed through until it delivers.
          </p>
        </div>
      </div>
    </div>
  </section>
</template>
