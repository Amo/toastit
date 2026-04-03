<script setup>
import { onMounted, onUnmounted, ref } from 'vue';
import AvatarBadge from './AvatarBadge.vue';

const model = defineModel({ default: '' });

const props = defineProps({
  options: { type: Array, default: () => [] },
  icon: { type: String, default: '' },
});

const open = ref(false);
const root = ref(null);

const selectedOption = () => props.options.find((option) => option.value === model.value) ?? null;

const closeOnOutsideClick = (event) => {
  if (!root.value || root.value.contains(event.target)) {
    return;
  }

  open.value = false;
};

const selectOption = (value) => {
  model.value = value;
  open.value = false;
};

const hasIdentity = (option) => !!option && (!!option.initials || !!option.gravatarUrl || !!option.secondaryLabel);

onMounted(() => {
  window.addEventListener('click', closeOnOutsideClick);
});

onUnmounted(() => {
  window.removeEventListener('click', closeOnOutsideClick);
});
</script>

<template>
  <div ref="root" class="relative inline-flex min-w-[11rem] flex-col">
    <button
      type="button"
      class="inline-flex items-center justify-between gap-3 rounded-full border border-stone-200 bg-white px-4 py-2.5 text-sm font-medium text-stone-700 transition hover:border-stone-300 hover:text-stone-950"
      @click="open = !open"
    >
      <span class="inline-flex items-center gap-2">
        <i
          v-if="icon && !(selectedOption() && hasIdentity(selectedOption()))"
          :class="icon"
          class="text-sm text-stone-400"
          aria-hidden="true"
        ></i>
        <AvatarBadge
          v-if="selectedOption() && hasIdentity(selectedOption())"
          :seed="selectedOption().seed ?? selectedOption().value ?? selectedOption().label"
          :initials="selectedOption().initials"
          :gravatar-url="selectedOption().gravatarUrl"
          :alt="selectedOption().label"
          :title="selectedOption().label"
          size-class="h-6 w-6 text-[0.65rem]"
        />
        <span>{{ selectedOption()?.label ?? '' }}</span>
      </span>
      <i class="fa-solid fa-chevron-down text-xs text-stone-400" aria-hidden="true"></i>
    </button>

    <div
      v-if="open"
      class="absolute left-0 top-[calc(100%+0.5rem)] z-20 min-w-full overflow-hidden rounded-2xl border border-stone-200 bg-white shadow-xl shadow-stone-200/60"
    >
      <button
        v-for="option in options"
        :key="option.value || option.label"
        type="button"
        class="flex w-full items-center justify-between gap-3 px-4 py-3 text-left text-sm text-stone-700 transition hover:bg-stone-50"
        :class="model === option.value ? 'bg-amber-50 text-amber-800' : ''"
        @click="selectOption(option.value)"
      >
        <span class="inline-flex min-w-0 items-center gap-3">
          <AvatarBadge
            v-if="hasIdentity(option)"
            :seed="option.seed ?? option.value ?? option.label"
            :initials="option.initials"
            :gravatar-url="option.gravatarUrl"
            :alt="option.label"
            :title="option.label"
            size-class="h-7 w-7 text-[0.65rem]"
          />
          <span class="min-w-0">
            <span class="block truncate">{{ option.label }}</span>
            <span v-if="option.secondaryLabel" class="block truncate text-xs text-stone-400">{{ option.secondaryLabel }}</span>
          </span>
        </span>
        <i v-if="model === option.value" class="fa-solid fa-check text-xs" aria-hidden="true"></i>
      </button>
    </div>
  </div>
</template>
