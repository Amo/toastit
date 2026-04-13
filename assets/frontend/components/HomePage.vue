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
</script>

<template>
  <section class="mx-auto max-w-4xl space-y-10 py-10 lg:py-14">
    <div class="space-y-4 text-center">
      <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Toastit</p>
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
  </section>
</template>
