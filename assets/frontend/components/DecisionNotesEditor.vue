<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  blocked: { type: Boolean, default: false },
  saveState: { type: String, default: 'idle' },
  saveError: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);
const textareaRef = ref(null);

const autosize = () => {
  if (!textareaRef.value) {
    return;
  }

  textareaRef.value.style.height = '0px';
  textareaRef.value.style.height = `${textareaRef.value.scrollHeight}px`;
};

const updateValue = (nextValue) => {
  emit('update:modelValue', nextValue);
  nextTick(() => {
    autosize();
  });
};

const handleInput = (event) => {
  updateValue(event.target.value);
};

onMounted(() => {
  nextTick(() => {
    autosize();
  });
});

watch(() => props.modelValue, () => {
  nextTick(() => {
    autosize();
  });
});
</script>

<template>
  <div class="space-y-2">
    <textarea
      ref="textareaRef"
      class="min-h-28 w-full resize-none overflow-hidden rounded-2xl border bg-white px-4 py-3 text-sm leading-7 transition"
      :class="blocked ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-200'"
      :value="modelValue"
      aria-label="Decision notes"
      placeholder="Capture decisions, open questions, owners, and next steps."
      @input="handleInput"
    />

    <div class="flex flex-wrap items-center justify-between gap-x-4 gap-y-1 px-1">
      <p class="text-xs leading-5 text-stone-400">Markdown: `##` title, `-` list, `**bold**`, `*italic*`.</p>
      <p
        class="text-xs font-medium"
        :class="{
          'text-stone-400': saveState === 'idle',
          'text-amber-700': saveState === 'saving',
          'text-emerald-600': saveState === 'saved',
          'text-red-600': saveState === 'error',
        }"
      >
        {{ saveState === 'saving' ? 'Saving…' : saveState === 'saved' ? 'Saved' : saveState === 'error' ? 'Autosave failed' : 'Autosave on' }}
      </p>
    </div>

    <p v-if="saveError" class="px-1 text-sm text-red-600">{{ saveError }}</p>
  </div>
</template>
