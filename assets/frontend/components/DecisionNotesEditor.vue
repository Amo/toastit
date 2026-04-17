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

const replaceCurrentLine = (transformer) => {
  const element = textareaRef.value;
  if (!element) {
    return;
  }

  const value = props.modelValue ?? '';
  const selectionStart = element.selectionStart ?? value.length;
  const selectionEnd = element.selectionEnd ?? value.length;
  const lineStart = value.lastIndexOf('\n', Math.max(selectionStart - 1, 0)) + 1;
  let lineEnd = value.indexOf('\n', selectionEnd);
  if (lineEnd < 0) {
    lineEnd = value.length;
  }

  const currentLine = value.slice(lineStart, lineEnd);
  const nextLine = transformer(currentLine);
  const nextValue = `${value.slice(0, lineStart)}${nextLine}${value.slice(lineEnd)}`;
  const nextCaret = lineStart + nextLine.length;

  updateValue(nextValue);

  nextTick(() => {
    if (!textareaRef.value) {
      return;
    }

    textareaRef.value.focus();
    textareaRef.value.setSelectionRange(nextCaret, nextCaret);
  });
};

const toggleLinePrefix = (prefix) => {
  replaceCurrentLine((line) => (line.startsWith(prefix) ? line.slice(prefix.length) : `${prefix}${line}`));
};

const clearLineFormatting = () => {
  replaceCurrentLine((line) => line.replace(/^(#{1,6}\s+|[-*]\s+)/, ''));
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
  <div class="space-y-3">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <span class="text-sm font-medium text-stone-700">Decision notes</span>
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-stone-500 transition hover:border-stone-300 hover:text-stone-800"
          @click="toggleLinePrefix('## ')"
        >
          Title
        </button>
        <button
          type="button"
          class="rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-stone-500 transition hover:border-stone-300 hover:text-stone-800"
          @click="toggleLinePrefix('- ')"
        >
          List
        </button>
        <button
          type="button"
          class="rounded-full border border-stone-200 bg-white px-3 py-1.5 text-xs font-semibold uppercase tracking-[0.16em] text-stone-500 transition hover:border-stone-300 hover:text-stone-800"
          @click="clearLineFormatting"
        >
          Text
        </button>
        <span
          class="text-xs font-medium"
          :class="{
            'text-stone-400': saveState === 'idle',
            'text-amber-700': saveState === 'saving',
            'text-emerald-600': saveState === 'saved',
            'text-red-600': saveState === 'error',
          }"
        >
          {{ saveState === 'saving' ? 'Saving…' : saveState === 'saved' ? 'Saved' : saveState === 'error' ? 'Autosave failed' : 'Autosave on' }}
        </span>
      </div>
    </div>

    <textarea
      ref="textareaRef"
      class="min-h-28 w-full resize-none overflow-hidden rounded-2xl border bg-white px-4 py-3 text-sm leading-7 transition"
      :class="blocked ? 'border-red-400 ring-2 ring-red-100' : 'border-stone-200'"
      :value="modelValue"
      placeholder="Capture decisions, open questions, owners, and next steps."
      @input="handleInput"
    />

    <p v-if="saveError" class="text-sm text-red-600">{{ saveError }}</p>
  </div>
</template>
