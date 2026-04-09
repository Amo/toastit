<script setup>
import { computed, onMounted, ref } from 'vue';
import { ToastitApiClient } from '../api/ToastitApiClient';
import EmptyState from './EmptyState.vue';
import PageHeader from './PageHeader.vue';
import PrimaryActionButton from './PrimaryActionButton.vue';
import SecondaryActionButton from './SecondaryActionButton.vue';

const props = defineProps({
  accessToken: { type: String, required: true },
});

const isLoading = ref(true);
const isSaving = ref(false);
const isRollingBackVersion = ref(null);
const prompts = ref([]);
const selectedPromptCode = ref('');
const systemEditorValue = ref('');
const userEditorValue = ref('');
const feedbackMessage = ref('');
const feedbackError = ref('');

const api = new ToastitApiClient(props.accessToken, {
  onUnauthorized: () => {
    window.location.href = '/';
  },
});

const selectedPrompt = computed(() => prompts.value.find((prompt) => prompt.code === selectedPromptCode.value) ?? null);
const selectedPromptVersions = computed(() => selectedPrompt.value?.versions ?? []);
const selectedSystemPromptVariables = computed(() => selectedPrompt.value?.availableVariables ?? []);
const selectedUserPromptVariables = computed(() => selectedPrompt.value?.availableUserVariables ?? []);
const formatTwigVariable = (name) => `{{ ${name} }}`;

const fetchPromptList = async () => {
  const { ok, data } = await api.getJson('/api/admin/prompts');
  if (!ok || !data) {
    prompts.value = [];
    return;
  }

  prompts.value = data.prompts ?? [];
  if (!selectedPromptCode.value && prompts.value.length) {
    selectedPromptCode.value = prompts.value[0].code;
  }
};

const fetchPromptDetail = async (code) => {
  if (!code) {
    return;
  }

  const { ok, data } = await api.getJson(`/api/admin/prompts/${encodeURIComponent(code)}`);
  if (!ok || !data?.ok || !data.prompt) {
    return;
  }

  const index = prompts.value.findIndex((prompt) => prompt.code === code);
  if (index >= 0) {
    prompts.value[index] = data.prompt;
  } else {
    prompts.value.push(data.prompt);
  }
  systemEditorValue.value = data.prompt.currentSystemPrompt ?? '';
  userEditorValue.value = data.prompt.currentUserPromptTemplate ?? '';
};

const selectPrompt = async (code) => {
  selectedPromptCode.value = code;
  feedbackMessage.value = '';
  feedbackError.value = '';
  await fetchPromptDetail(code);
};

const savePrompt = async () => {
  if (!selectedPromptCode.value) {
    return;
  }

  isSaving.value = true;
  feedbackMessage.value = '';
  feedbackError.value = '';

  const { ok, data } = await api.putJson(`/api/admin/prompts/${encodeURIComponent(selectedPromptCode.value)}`, {
    systemPrompt: systemEditorValue.value,
    userPromptTemplate: userEditorValue.value,
  });

  isSaving.value = false;

  if (!ok || !data?.ok || !data.prompt) {
    feedbackError.value = 'Unable to save prompt version.';
    return;
  }

  const index = prompts.value.findIndex((prompt) => prompt.code === selectedPromptCode.value);
  if (index >= 0) {
    prompts.value[index] = data.prompt;
  }
  systemEditorValue.value = data.prompt.currentSystemPrompt ?? '';
  userEditorValue.value = data.prompt.currentUserPromptTemplate ?? '';
  feedbackMessage.value = `Saved as v${data.prompt.currentVersionNumber}.`;
};

const rollbackPrompt = async (versionNumber) => {
  if (!selectedPromptCode.value) {
    return;
  }

  isRollingBackVersion.value = versionNumber;
  feedbackMessage.value = '';
  feedbackError.value = '';

  const { ok, data } = await api.postJson(`/api/admin/prompts/${encodeURIComponent(selectedPromptCode.value)}/rollback/${versionNumber}`, {});
  isRollingBackVersion.value = null;

  if (!ok || !data?.ok || !data.prompt) {
    feedbackError.value = 'Unable to rollback prompt version.';
    return;
  }

  const index = prompts.value.findIndex((prompt) => prompt.code === selectedPromptCode.value);
  if (index >= 0) {
    prompts.value[index] = data.prompt;
  }
  systemEditorValue.value = data.prompt.currentSystemPrompt ?? '';
  userEditorValue.value = data.prompt.currentUserPromptTemplate ?? '';
  feedbackMessage.value = `Rolled back from v${versionNumber}. New head is v${data.prompt.currentVersionNumber}.`;
};

const openOverview = () => {
  window.location.href = '/admin';
};

onMounted(async () => {
  isLoading.value = true;
  await fetchPromptList();
  if (selectedPromptCode.value) {
    await fetchPromptDetail(selectedPromptCode.value);
  }
  isLoading.value = false;
});
</script>

<template>
  <section class="tw-toastit-shell space-y-6">
    <PageHeader
      eyebrow="Admin"
      title="ROOT prompts."
      :stats="[{ label: `${prompts.length} prompts`, className: 'bg-stone-100 text-stone-600 uppercase tracking-[0.18em] text-xs font-semibold' }]"
      :actions="[{ id: 'overview', label: 'Overview', icon: 'fa-solid fa-chart-column', theme: 'secondary' }]"
      @action="openOverview"
    />

    <div class="tw-toastit-card p-6">
      <EmptyState v-if="isLoading" message="Loading prompts..." />
      <EmptyState v-else-if="!prompts.length" message="No prompt found." />
      <div v-else class="grid gap-6 lg:grid-cols-[18rem_minmax(0,1fr)]">
        <aside class="space-y-2">
          <button
            v-for="prompt in prompts"
            :key="prompt.code"
            type="button"
            class="w-full rounded-2xl border px-4 py-3 text-left text-sm transition"
            :class="selectedPromptCode === prompt.code
              ? 'border-amber-300 bg-amber-50 text-amber-900'
              : 'border-stone-200 bg-white text-stone-700 hover:border-stone-300 hover:bg-stone-50'"
            @click="selectPrompt(prompt.code)"
          >
            <p class="font-semibold">{{ prompt.label }}</p>
            <p class="mt-1 text-xs text-stone-500">{{ prompt.code }}</p>
            <p class="mt-1 text-xs text-stone-500">v{{ prompt.latestVersionNumber ?? '-' }}</p>
          </button>
        </aside>

        <div v-if="selectedPrompt" class="space-y-4">
          <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Template variables</p>
            <p class="mt-1 text-sm text-stone-600">Prompt source is a Twig template. Available variables for this prompt:</p>

            <div class="mt-4 space-y-3">
              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">System prompt variables</p>
                <div v-if="selectedSystemPromptVariables.length" class="mt-2 space-y-2">
                  <div v-for="variable in selectedSystemPromptVariables" :key="`system-${variable.name}`" class="rounded-xl border border-stone-200 bg-white p-3 text-sm">
                    <p><strong v-text="formatTwigVariable(variable.name)" /></p>
                    <p class="text-stone-600">{{ variable.description }}</p>
                    <p class="mt-1 text-xs text-stone-500">Example: {{ variable.example }}</p>
                  </div>
                </div>
                <p v-else class="mt-2 text-sm text-stone-500">No system variable declared for this prompt.</p>
              </div>

              <div>
                <p class="text-xs font-semibold uppercase tracking-[0.16em] text-stone-500">User prompt variables</p>
                <div v-if="selectedUserPromptVariables.length" class="mt-2 space-y-2">
                  <div v-for="variable in selectedUserPromptVariables" :key="`user-${variable.name}`" class="rounded-xl border border-stone-200 bg-white p-3 text-sm">
                    <p><strong v-text="formatTwigVariable(variable.name)" /></p>
                    <p class="text-stone-600">{{ variable.description }}</p>
                    <p class="mt-1 text-xs text-stone-500">Example: {{ variable.example }}</p>
                  </div>
                </div>
                <p v-else class="mt-2 text-sm text-stone-500">No user variable declared for this prompt.</p>
              </div>
            </div>
          </div>

          <div class="rounded-2xl border border-stone-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">System prompt template</p>
            <textarea
              v-model="systemEditorValue"
              class="mt-3 h-80 w-full rounded-2xl border border-stone-200 p-4 font-mono text-sm text-stone-900 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
            />
          </div>

          <div class="rounded-2xl border border-stone-200 bg-white p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">User prompt template</p>
            <textarea
              v-model="userEditorValue"
              class="mt-3 h-80 w-full rounded-2xl border border-stone-200 p-4 font-mono text-sm text-stone-900 focus:border-amber-400 focus:outline-none focus:ring-2 focus:ring-amber-200"
            />
            <div class="mt-3 flex items-center justify-between gap-3">
              <p v-if="feedbackError" class="text-sm text-rose-700">{{ feedbackError }}</p>
              <p v-else-if="feedbackMessage" class="text-sm text-emerald-700">{{ feedbackMessage }}</p>
              <span v-else />
              <PrimaryActionButton :disabled="isSaving" @click="savePrompt">
                {{ isSaving ? 'Saving...' : 'Save new version' }}
              </PrimaryActionButton>
            </div>
          </div>

          <div class="rounded-2xl border border-stone-200 bg-stone-50 p-4">
            <p class="text-xs font-semibold uppercase tracking-[0.18em] text-stone-500">Version history</p>
            <div class="mt-3 space-y-2">
              <div v-for="version in selectedPromptVersions" :key="version.versionNumber" class="flex items-center justify-between rounded-xl border border-stone-200 bg-white px-3 py-2 text-sm">
                <div>
                  <p class="font-semibold">v{{ version.versionNumber }}</p>
                  <p class="text-xs text-stone-500">{{ version.changedAt }} · {{ version.changedBy ?? 'system' }}</p>
                </div>
                <SecondaryActionButton
                  :disabled="isRollingBackVersion === version.versionNumber || version.versionNumber === selectedPrompt.currentVersionNumber"
                  @click="rollbackPrompt(version.versionNumber)"
                >
                  {{ isRollingBackVersion === version.versionNumber ? 'Rolling back...' : 'Rollback' }}
                </SecondaryActionButton>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>
