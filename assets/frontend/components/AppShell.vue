<script setup>
import { nextTick, onMounted, ref, useSlots } from 'vue';

const props = defineProps({
  currentSection: { type: String, required: true },
  dashboardUrl: { type: String, required: true },
  profileUrl: { type: String, required: true },
  logoutUrl: { type: String, required: true },
  user: { type: Object, default: null },
  contentHtml: { type: String, required: true },
});

const userMenuOpen = ref(false);
const contentRef = ref(null);
const slots = useSlots();

onMounted(async () => {
  await nextTick();

  if (contentRef.value && window.Alpine?.initTree) {
    window.Alpine.initTree(contentRef.value);
  }
});
</script>

<template>
  <div class="min-h-screen bg-stone-50">
    <header class="sticky top-0 z-50 border-b border-stone-200/80 bg-white/90 backdrop-blur">
      <div class="tw-toastit-shell">
        <div class="flex flex-col gap-3 py-4 lg:flex-row lg:items-center lg:justify-between">
          <div class="flex items-center justify-between gap-4">
            <a :href="dashboardUrl" class="inline-flex items-center gap-3 text-stone-950">
              <span class="inline-grid h-10 w-10 place-items-center rounded-2xl bg-amber-100 text-sm font-black text-amber-700">T</span>
              <span class="text-lg font-semibold tracking-tight">Toastit</span>
            </a>

            <button
              type="button"
              class="inline-flex items-center gap-2 rounded-full border border-stone-200 bg-white px-4 py-2 text-sm font-medium text-stone-700 lg:hidden"
              @click="userMenuOpen = !userMenuOpen"
            >
              <i class="fa-solid fa-circle-user" aria-hidden="true"></i>
              <span>Account</span>
            </button>
          </div>

          <div class="flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between lg:gap-6">
            <nav class="flex flex-wrap items-center gap-2" aria-label="Navigation principale">
              <a
                :href="dashboardUrl"
                class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition"
                :class="currentSection === 'workspace' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 ring-1 ring-stone-200 hover:bg-stone-100'"
              >
                Workspace
              </a>
              <a
                :href="profileUrl"
                class="inline-flex items-center rounded-full px-4 py-2 text-sm font-medium transition"
                :class="currentSection === 'profile' ? 'bg-stone-900 text-white' : 'bg-white text-stone-600 ring-1 ring-stone-200 hover:bg-stone-100'"
              >
                Profile
              </a>
            </nav>

            <div class="relative hidden lg:block">
              <button
                class="inline-flex items-center gap-3 rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm font-medium text-stone-700 shadow-sm transition hover:bg-stone-50"
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
                  <p class="text-sm text-stone-500">{{ user?.isRoot ? 'ROOT user' : 'User' }}</p>
                </div>
                <div class="space-y-2">
                  <a :href="profileUrl" class="flex items-center rounded-2xl px-4 py-3 text-sm font-medium text-stone-700 transition hover:bg-stone-100">My profile</a>
                  <form method="post" :action="logoutUrl">
                    <button class="flex w-full items-center justify-center rounded-2xl bg-stone-900 px-4 py-3 text-sm font-semibold text-white transition hover:bg-stone-800" type="submit">
                      Sign out
                    </button>
                  </form>
                </div>
              </div>
            </div>

            <div v-if="userMenuOpen" class="grid gap-3 rounded-3xl border border-stone-200 bg-white p-4 shadow-sm lg:hidden">
              <div class="space-y-1">
                <p class="text-base font-semibold text-stone-950">{{ user?.displayName ?? 'Account' }}</p>
                <p class="text-sm text-stone-500">{{ user?.isRoot ? 'ROOT user' : 'User' }}</p>
              </div>
              <a :href="profileUrl" class="rounded-2xl border border-stone-200 px-4 py-3 text-sm font-medium text-stone-700">My profile</a>
              <form method="post" :action="logoutUrl">
                <button class="w-full rounded-2xl bg-stone-900 px-4 py-3 text-sm font-semibold text-white" type="submit">Sign out</button>
              </form>
            </div>
          </div>
        </div>
      </div>
    </header>

    <main class="py-6">
      <div v-if="slots.default" class="tw-toastit-shell">
        <slot />
      </div>
      <div v-else class="tw-toastit-shell" ref="contentRef" v-html="contentHtml"></div>
    </main>
  </div>
</template>
