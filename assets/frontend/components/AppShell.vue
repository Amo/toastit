<script setup>
import { nextTick, onMounted, onUnmounted, ref, useSlots } from 'vue';
import { authStore } from '../authStore';
import ModalDialog from './ModalDialog.vue';
import ModalHeader from './ModalHeader.vue';

const props = defineProps({
  currentSection: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  profileUrl: { type: String, required: true },
  user: { type: Object, default: null },
  contentHtml: { type: String, required: true },
  showAppNavigation: { type: Boolean, default: true },
  publicCtaLabel: { type: String, default: '' },
  publicCtaHref: { type: String, default: '' },
});
const emit = defineEmits(['public-cta-click']);

const userMenuOpen = ref(false);
const keyboardShortcutsOpen = ref(false);
const contentRef = ref(null);
const slots = useSlots();

const isTypingTarget = (target) => {
  if (!(target instanceof HTMLElement)) {
    return false;
  }

  const tagName = target.tagName.toLowerCase();

  return tagName === 'input'
    || tagName === 'textarea'
    || tagName === 'select'
    || target.isContentEditable;
};

const handleGlobalAppKeydown = (event) => {
  if (isTypingTarget(event.target) || event.metaKey || event.ctrlKey || event.altKey) {
    return;
  }

  if (event.key.toLowerCase() !== 'h') {
    return;
  }

  event.preventDefault();
  window.location.href = props.dashboardUrl;
};

const openInbox = () => {
  window.location.href = '/app/inbox';
};

const logout = () => {
  authStore.logout();
  window.location.href = '/';
};

const lockSession = () => {
  window.dispatchEvent(new CustomEvent('toastit:lock-session'));
};

onMounted(async () => {
  await nextTick();

  if (contentRef.value && window.Alpine?.initTree) {
    window.Alpine.initTree(contentRef.value);
  }

  window.addEventListener('keydown', handleGlobalAppKeydown);
});

onUnmounted(() => {
  window.removeEventListener('keydown', handleGlobalAppKeydown);
});
</script>

<template>
  <div class="min-h-screen bg-stone-50">
    <header class="sticky top-0 z-50 border-b border-stone-200/80 bg-white/90 backdrop-blur">
      <div class="tw-toastit-shell">
        <div class="flex flex-col gap-3 py-4 lg:flex-row lg:items-center lg:justify-between">
          <div :class="showAppNavigation ? 'flex items-center justify-between gap-4' : 'flex w-full items-center justify-between gap-4'">
            <a :href="dashboardUrl" class="inline-flex items-center gap-0 text-stone-950">
              <span class="inline-grid h-10 w-10 place-items-center rounded-2xl border border-amber-200 bg-amber-100 text-sm font-black text-amber-700 shadow-[0_6px_16px_rgba(180,83,9,0.18)]">T</span>
              <span class="-ml-1 text-2xl font-bold tracking-[0.04em] text-stone-950">oastIt</span>
            </a>

            <a
              v-if="!showAppNavigation && publicCtaHref && publicCtaLabel"
              :href="publicCtaHref"
              class="inline-flex items-center justify-center rounded-full bg-amber-500 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
            >
              {{ publicCtaLabel }}
            </a>
            <button
              v-else-if="!showAppNavigation && publicCtaLabel"
              type="button"
              class="inline-flex items-center justify-center rounded-full bg-amber-500 px-5 py-2.5 text-sm font-semibold text-stone-950 transition hover:bg-amber-400"
              @click="emit('public-cta-click')"
            >
              {{ publicCtaLabel }}
            </button>

            <button
              v-if="showAppNavigation"
              type="button"
              class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-700 lg:hidden"
              @click="userMenuOpen = !userMenuOpen"
            >
              <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
              <span>Account</span>
            </button>
          </div>

          <div v-if="showAppNavigation" class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6">
            <div class="flex items-center gap-3">
              <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full border transition"
                :class="currentSection === 'inbox'
                  ? 'border-sky-300 bg-sky-100 text-sky-700'
                  : 'border-stone-200 bg-white text-stone-600 hover:border-stone-300 hover:text-stone-950'"
                @click="openInbox"
                title="Inbox"
              >
                <i class="fa-solid fa-inbox" aria-hidden="true"></i>
                <span class="sr-only">Open inbox</span>
              </button>

              <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                @click="lockSession"
                title="Lock session"
              >
                <i class="fa-solid fa-lock" aria-hidden="true"></i>
                <span class="sr-only">Lock session</span>
              </button>

              <button
                type="button"
                class="inline-flex h-11 w-11 items-center justify-center rounded-full border border-stone-200 bg-white text-stone-600 transition hover:border-stone-300 hover:text-stone-950"
                @click="keyboardShortcutsOpen = true"
                title="Keyboard shortcuts"
              >
                <i class="fa-solid fa-keyboard" aria-hidden="true"></i>
                <span class="sr-only">Open keyboard shortcuts</span>
              </button>

              <div class="relative hidden lg:block">
              <button
                class="inline-flex items-center gap-3 rounded-full bg-white px-4 py-2.5 text-sm font-medium text-stone-700 transition hover:bg-stone-50"
                type="button"
                @click="userMenuOpen = !userMenuOpen"
              >
                <span class="inline-grid h-9 w-9 place-items-center rounded-full bg-stone-100 text-sm font-semibold text-stone-700">
                  {{ user?.displayName?.slice(0, 2)?.toUpperCase() ?? 'CO' }}
                </span>
                <span class="max-w-[12rem] truncate">{{ user?.displayName ?? 'Account' }}</span>
                <i class="fa-solid fa-chevron-down text-xs text-stone-400" aria-hidden="true"></i>
              </button>

              <div v-if="userMenuOpen" class="absolute right-0 top-[calc(100%+0.75rem)] w-72 rounded-3xl border border-stone-200 bg-white p-4 shadow-2xl shadow-stone-200/60">
                <div class="space-y-1 pb-4">
                  <p class="text-base font-semibold text-stone-950">{{ user?.displayName ?? 'Account' }}</p>
                </div>
                <div class="space-y-2">
                  <a v-if="user?.isRoot" href="/admin" class="flex items-center rounded-2xl px-4 py-3 text-sm font-medium text-stone-700 transition hover:bg-stone-100">Admin</a>
                  <a :href="profileUrl" class="flex items-center rounded-2xl px-4 py-3 text-sm font-medium text-stone-700 transition hover:bg-stone-100">My profile</a>
                  <button class="flex w-full items-center justify-center rounded-2xl bg-amber-500 px-4 py-3 text-sm font-semibold text-stone-950 transition hover:bg-amber-400" type="button" @click="logout">
                    Sign out
                  </button>
                </div>
              </div>
            </div>
            </div>

            <div v-if="userMenuOpen" class="grid gap-3 rounded-3xl border border-stone-200 bg-white p-4 shadow-sm lg:hidden">
              <div class="space-y-1">
                <p class="text-base font-semibold text-stone-950">{{ user?.displayName ?? 'Account' }}</p>
              </div>
              <a v-if="user?.isRoot" href="/admin" class="rounded-2xl border border-stone-200 px-4 py-3 text-sm font-medium text-stone-700">Admin</a>
              <a :href="profileUrl" class="rounded-2xl border border-stone-200 px-4 py-3 text-sm font-medium text-stone-700">My profile</a>
              <button class="w-full rounded-2xl bg-amber-500 px-4 py-3 text-sm font-semibold text-stone-950" type="button" @click="logout">Sign out</button>
            </div>
          </div>
        </div>
      </div>
    </header>

    <ModalDialog v-if="showAppNavigation && keyboardShortcutsOpen" max-width-class="max-w-4xl" @close="keyboardShortcutsOpen = false">
      <ModalHeader
        eyebrow="Keyboard"
        title="Keyboard shortcuts"
        description="Shortcuts currently available across the app."
        @close="keyboardShortcutsOpen = false"
      />

      <div class="space-y-6 overflow-y-auto px-6 py-6">
        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Global</p>
          <div class="grid gap-3">
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Go to dashboard</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">H</span>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Close any modal</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">Esc</span>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Dashboard</p>
          <div class="grid gap-3">
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Open workspace by position in the list</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">1…9</span>
            </div>
          </div>
        </div>

        <div class="space-y-3">
          <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Workspace</p>
          <div class="grid gap-3">
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Open advanced new toast modal</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">T</span>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Go to previous or next toast in the open modal</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">← →</span>
            </div>
            <div class="flex items-center justify-between gap-4 rounded-2xl border border-stone-200 bg-stone-50 px-4 py-3">
              <span class="text-sm text-stone-700">Submit the new toast modal form</span>
              <span class="rounded-full bg-white px-3 py-1 text-xs font-semibold text-stone-700">Cmd/Ctrl + Enter</span>
            </div>
          </div>
        </div>
      </div>
    </ModalDialog>

    <main class="py-6">
      <div v-if="slots.default" class="tw-toastit-shell">
        <slot />
      </div>
      <div v-else class="tw-toastit-shell" ref="contentRef" v-html="contentHtml"></div>
    </main>
  </div>
</template>
