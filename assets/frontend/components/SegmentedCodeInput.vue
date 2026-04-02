<script setup>
import { computed, nextTick, ref, watch } from 'vue';

const props = defineProps({
  modelValue: { type: String, default: '' },
  length: { type: Number, required: true },
  inputType: { type: String, default: 'text' },
  inputMode: { type: String, default: 'text' },
  autocomplete: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
  mask: { type: Boolean, default: false },
  pattern: { type: RegExp, default: () => /./ },
});

const emit = defineEmits(['update:modelValue', 'complete']);
const inputRefs = ref([]);

const chars = computed(() => {
  const raw = (props.modelValue ?? '').slice(0, props.length).split('');
  return Array.from({ length: props.length }, (_, index) => raw[index] ?? '');
});

const focusIndex = async (index) => {
  await nextTick();
  inputRefs.value[index]?.focus();
  inputRefs.value[index]?.select();
};

const sanitize = (value) => value
  .toUpperCase()
  .split('')
  .filter((char) => props.pattern.test(char))
  .slice(0, props.length)
  .join('');

const updateValue = (value) => {
  const sanitized = sanitize(value);
  emit('update:modelValue', sanitized);

  if (sanitized.length === props.length) {
    emit('complete', sanitized);
  }
};

const handleInput = (index, event) => {
  const target = event.target;
  const incoming = sanitize(target.value);

  if (!incoming) {
    const nextChars = [...chars.value];
    nextChars[index] = '';
    updateValue(nextChars.join(''));
    return;
  }

  const nextChars = [...chars.value];
  incoming.split('').forEach((char, offset) => {
    if ((index + offset) < props.length) {
      nextChars[index + offset] = char;
    }
  });

  updateValue(nextChars.join(''));

  const nextIndex = Math.min(index + incoming.length, props.length - 1);

  if ((index + incoming.length) < props.length) {
    focusIndex(nextIndex);
  } else {
    target.blur();
  }
};

const handleKeydown = (index, event) => {
  if (event.key === 'Backspace' && !chars.value[index] && index > 0) {
    const nextChars = [...chars.value];
    nextChars[index - 1] = '';
    updateValue(nextChars.join(''));
    focusIndex(index - 1);
    event.preventDefault();
    return;
  }

  if (event.key === 'ArrowLeft' && index > 0) {
    focusIndex(index - 1);
    event.preventDefault();
    return;
  }

  if (event.key === 'ArrowRight' && index < props.length - 1) {
    focusIndex(index + 1);
    event.preventDefault();
  }
};

const handlePaste = (event) => {
  const pasted = sanitize(event.clipboardData?.getData('text') ?? '');

  if (!pasted) {
    return;
  }

  event.preventDefault();
  updateValue(pasted);
};

defineExpose({
  focusFirst() {
    focusIndex(0);
  },
});

watch(() => props.modelValue, async (value) => {
  if (!value) {
    await nextTick();
    inputRefs.value[0]?.focus();
  }
});
</script>

<template>
  <div class="flex items-center justify-center gap-2 sm:gap-3" @paste="handlePaste">
    <input
      v-for="(_, index) in chars"
      :key="index"
      :ref="(element) => { inputRefs[index] = element; }"
      :type="mask ? 'password' : inputType"
      :inputmode="inputMode"
      :autocomplete="index === 0 ? autocomplete : 'off'"
      :value="chars[index]"
      :placeholder="placeholder"
      :disabled="disabled"
      maxlength="1"
      class="h-14 w-11 rounded-2xl border border-stone-300 bg-white text-center text-xl font-semibold tracking-[0.06em] text-stone-950 outline-none transition focus:border-amber-400 focus:ring-4 focus:ring-amber-100 disabled:cursor-not-allowed disabled:bg-stone-100 sm:h-16 sm:w-12"
      @input="handleInput(index, $event)"
      @keydown="handleKeydown(index, $event)"
      @focus="$event.target.select()"
    >
  </div>
</template>
