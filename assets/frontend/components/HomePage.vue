<script setup>
import PrimaryActionButton from './PrimaryActionButton.vue';
defineEmits(['open-login']);

const highlights = [
  {
    title: 'Personal Inbox Relief',
    description: 'Forward raw emails or messages and keep your personal stuff to do in one clean queue.',
  },
  {
    title: 'AI Auto Routing',
    description: 'Toastit routes each item to the right workspace and todo list automatically, with context preserved.',
  },
  {
    title: 'Meeting Execution for Pros',
    description: 'Prepare agenda, run the session, capture decisions, assign follow-ups, and auto-generate summaries.',
  },
  {
    title: 'MCP & API',
    description: 'Use Toastit from scripts, automations, chat tools, and custom workflows to track stuff to do from anywhere.',
  },
];

const flowImageUrl = '/asset/toastit-flow.jpeg';
const currentYear = new Date().getFullYear();
</script>

<template>
  <section class="mx-auto max-w-4xl space-y-10 pb-0 pt-10 lg:pt-14">
    <div class="space-y-4 text-center">
      <h1 class="text-4xl font-semibold tracking-tight text-stone-950 lg:text-5xl">From inbox chaos to accountable execution.</h1>
      <p class="mx-auto max-w-2xl text-base leading-7 text-stone-600">
        Toastit helps individual users clear inbox backlog with AI todo capture, and helps pro teams run meetings with clear agendas, decisions, and follow-up actions.
      </p>
      <div class="pt-2">
        <PrimaryActionButton @click="$emit('open-login')">Start with your inbox</PrimaryActionButton>
      </div>
    </div>

    <div class="relative left-1/2 right-1/2 w-screen -translate-x-1/2 px-4 lg:px-10">
      <div class="mx-auto w-full max-w-7xl overflow-hidden rounded-[1.75rem] border border-stone-200 bg-white shadow-xl shadow-stone-200/70">
        <img
          :src="flowImageUrl"
          alt="Toastit flow overview"
          class="block h-auto w-full"
        >
      </div>
    </div>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
      <article v-for="item in highlights" :key="item.title" class="rounded-[1.5rem] border border-stone-200 bg-white p-5">
        <h2 class="text-base font-semibold text-stone-950">{{ item.title }}</h2>
        <p class="mt-2 text-sm leading-6 text-stone-700">{{ item.description }}</p>
      </article>
    </div>

    <footer class="relative left-1/2 right-1/2 w-screen -translate-x-1/2 bg-stone-950 text-stone-100">
      <div class="mx-auto max-w-7xl px-6 py-8 lg:px-8 lg:py-10">
        <div class="grid gap-8 lg:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)_minmax(0,1fr)]">
          <div>
            <div class="flex items-center gap-3">
              <img :src="'/assets/logo.png'" alt="Toastit" class="h-9 w-auto object-contain">
              <span class="text-lg font-black tracking-[0.12em] text-white">TOASTIT</span>
            </div>
            <p class="mt-4 max-w-md text-sm leading-7 text-stone-300">
              Route inbox noise to actionable todos with AI, then run meetings with decisions, follow-ups, and clear accountability.
            </p>
            <button
              type="button"
              class="mt-5 inline-flex items-center justify-center rounded-full bg-amber-200 px-5 py-2.5 text-sm font-semibold text-amber-900 transition hover:bg-amber-300"
              @click="$emit('open-login')"
            >
              Login
            </button>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-400">Individuals</p>
            <ul class="mt-3 space-y-2 text-sm text-stone-200">
              <li>Forward any email to create a toast</li>
              <li>Receive your todo digest by email</li>
              <li>Get a 7-day operational summary</li>
            </ul>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-400">Pros & Power Users</p>
            <ul class="mt-3 space-y-2 text-sm text-stone-200">
              <li>AI-supported meeting execution</li>
              <li>Decision logs and follow-up tracking</li>
              <li>MCP + API automation from anywhere</li>
            </ul>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-400">Legal</p>
            <ul class="mt-3 space-y-2 text-sm text-stone-200">
              <li>
                <a
                  href="/gdpr"
                  class="underline decoration-stone-600 underline-offset-2 transition hover:text-white"
                >
                  Legal notice &amp; GDPR
                </a>
              </li>
              <li>
                Made with grit by
                <a
                  href="https://x.com/amo__"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="font-semibold text-stone-100 underline decoration-stone-500 underline-offset-2 transition hover:text-white"
                >
                  @amo__
                </a>
              </li>
            </ul>
          </div>

          <div>
            <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-400">Developers</p>
            <ul class="mt-3 space-y-2 text-sm text-stone-200">
              <li>
                <a
                  href="https://api.toastit.cc/doc"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="underline decoration-stone-600 underline-offset-2 transition hover:text-white"
                >
                  Public API documentation
                </a>
              </li>
              <li>
                <a
                  href="https://github.com/Amo/toastit-mcp"
                  target="_blank"
                  rel="noopener noreferrer"
                  class="underline decoration-stone-600 underline-offset-2 transition hover:text-white"
                >
                  MCP server code (GitHub)
                </a>
              </li>
            </ul>
          </div>
        </div>
        <div class="mt-8 border-t border-stone-800 pt-4 text-xs text-stone-400">
          © {{ currentYear }} Toastit. Built for inbox-to-execution.
        </div>
      </div>
    </footer>
  </section>
</template>
