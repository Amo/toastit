<script setup>
import EyebrowLabel from './EyebrowLabel.vue';

defineProps({
  eyebrow: { type: String, default: '' },
  title: { type: String, required: true },
  stats: { type: Array, default: () => [] },
  actions: { type: Array, default: () => [] },
  inverted: { type: Boolean, default: false },
});

defineEmits(['action']);

const actionClass = (action, inverted) => {
  const base = action.iconOnly
    ? 'inline-grid h-12 w-12 place-items-center rounded-full border transition disabled:opacity-60'
    : 'inline-flex items-center gap-2 rounded-full px-5 py-3 text-sm font-semibold transition disabled:opacity-60';

  if (action.theme === 'primary') {
    return inverted
      ? `${base} bg-white text-stone-950 hover:bg-white/90`
      : `${base} bg-amber-500 text-stone-950 hover:bg-amber-400`;
  }

  return inverted
    ? `${base} border-white/30 bg-white/15 text-white hover:border-white/50 hover:bg-white/20`
    : `${base} border-stone-200 bg-white text-stone-700 hover:border-stone-300 hover:text-stone-950`;
};
</script>

<template>
  <div class="space-y-2">
    <EyebrowLabel v-if="eyebrow" :tone-class="inverted ? 'text-white/90' : 'text-amber-600'">{{ eyebrow }}</EyebrowLabel>

    <div class="flex items-start justify-between gap-4">
      <div>
        <div class="flex flex-wrap items-center gap-3">
          <h1 class="text-4xl font-semibold tracking-tight" :class="inverted ? 'text-white' : 'text-stone-950'">{{ title }}</h1>
        </div>
        <div v-if="stats.length" class="mt-3 flex flex-wrap gap-3 text-sm" :class="inverted ? 'text-white/85' : 'text-stone-500'">
          <span
            v-for="(stat, index) in stats"
            :key="`${stat.label}-${index}`"
            class="inline-flex items-center gap-2 rounded-full px-3 py-1 font-medium"
            :class="stat.className ?? (inverted ? 'bg-white/15 text-white' : 'bg-stone-100 text-stone-700')"
          >
            <i v-if="stat.icon" :class="stat.icon" aria-hidden="true"></i>
            <span>{{ stat.label }}</span>
          </span>
        </div>
      </div>

      <div v-if="actions.length" class="flex flex-wrap items-center justify-end gap-3">
        <button
          v-for="action in actions"
          :key="action.id"
          type="button"
          :class="actionClass(action, inverted)"
          :disabled="action.disabled"
          @click="$emit('action', action.id)"
        >
          <i v-if="action.icon" :class="action.icon" aria-hidden="true"></i>
          <span v-if="action.label">{{ action.label }}</span>
          <span v-if="action.iconOnly" class="sr-only">{{ action.srLabel || action.label || action.id }}</span>
        </button>
      </div>
    </div>
  </div>
</template>
