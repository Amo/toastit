<script setup>
import { computed, ref, watch } from 'vue';

const props = defineProps({
  seed: { type: [String, Number], default: '' },
  initials: { type: String, default: '' },
  gravatarUrl: { type: String, default: '' },
  alt: { type: String, default: '' },
  title: { type: String, default: '' },
});

const imageFailed = ref(false);

watch(
  () => props.gravatarUrl,
  () => {
    imageFailed.value = false;
  },
);

const shouldShowImage = computed(() => !!props.gravatarUrl && !imageFailed.value);
const fallbackInitials = computed(() => props.initials || '?');
const pastelPalette = [
  { background: 'bg-amber-100', text: 'text-amber-800' },
  { background: 'bg-rose-100', text: 'text-rose-800' },
  { background: 'bg-orange-100', text: 'text-orange-800' },
  { background: 'bg-lime-100', text: 'text-lime-800' },
  { background: 'bg-emerald-100', text: 'text-emerald-800' },
  { background: 'bg-teal-100', text: 'text-teal-800' },
  { background: 'bg-cyan-100', text: 'text-cyan-800' },
  { background: 'bg-sky-100', text: 'text-sky-800' },
  { background: 'bg-indigo-100', text: 'text-indigo-800' },
  { background: 'bg-fuchsia-100', text: 'text-fuchsia-800' },
];

const fallbackTone = computed(() => {
  const seed = String(props.seed || props.alt || props.initials || '?');
  let hash = 0;

  for (let index = 0; index < seed.length; index += 1) {
    hash = (hash * 31 + seed.charCodeAt(index)) >>> 0;
  }

  return pastelPalette[hash % pastelPalette.length];
});

const onImageError = () => {
  imageFailed.value = true;
};
</script>

<template>
  <span
    class="inline-grid h-8 w-8 shrink-0 place-items-center overflow-hidden rounded-full text-xs font-semibold uppercase"
    :class="shouldShowImage ? 'bg-stone-100 text-stone-700' : [fallbackTone.background, fallbackTone.text]"
    :title="title || alt"
  >
    <img
      v-if="shouldShowImage"
      :src="gravatarUrl"
      :alt="alt"
      class="h-full w-full object-cover"
      @error="onImageError"
    >
    <span v-else>{{ fallbackInitials }}</span>
  </span>
</template>
