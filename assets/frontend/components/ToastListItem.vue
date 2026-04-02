<script setup>
const props = defineProps({
  indexLabel: { type: [String, Number], required: true },
  title: { type: String, required: true },
  description: { type: String, default: '' },
  variant: { type: String, default: 'active' },
  accentClass: { type: String, default: 'border-stone-200' },
});

const variantBadgeClass = {
  active: 'bg-amber-100 text-amber-700',
  vetoed: 'bg-stone-100 text-stone-600',
  resolved: 'bg-stone-100 text-stone-600',
};

const containerClass = {
  active: 'bg-white transition hover:shadow-toastit-panel',
  vetoed: 'border-stone-200 bg-white opacity-95 transition hover:shadow-toastit-panel',
  resolved: 'border-stone-200 bg-white opacity-95 transition hover:shadow-toastit-panel',
};
</script>

<template>
  <article
    class="overflow-hidden rounded-[1.35rem] border"
    :class="variant === 'active' ? [accentClass, 'bg-white transition hover:shadow-toastit-panel'] : containerClass[variant]"
  >
    <button type="button" class="flex w-full items-start justify-between gap-4 px-5 py-4 text-left" @click="$emit('open')">
      <div class="min-w-0 space-y-2">
        <div class="flex items-center gap-3">
          <span class="inline-grid h-8 w-8 place-items-center rounded-full font-semibold" :class="variantBadgeClass[variant]">{{ indexLabel }}</span>
          <p class="truncate text-lg font-semibold text-stone-950">{{ title }}</p>
        </div>
        <p v-if="description" class="text-sm text-stone-500">{{ description }}</p>
      </div>
      <div class="shrink-0">
        <slot name="actions" />
      </div>
    </button>
  </article>
</template>
