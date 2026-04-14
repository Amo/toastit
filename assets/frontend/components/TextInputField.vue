<script setup>
import { nextTick, onMounted, ref, watch } from 'vue';
import FormField from './FormField.vue';

const model = defineModel({ default: '' });

const props = defineProps({
  label: { type: String, required: true },
  type: { type: String, default: 'text' },
  placeholder: { type: String, default: '' },
  name: { type: String, default: '' },
  help: { type: String, default: '' },
  autocomplete: { type: String, default: '' },
  required: { type: Boolean, default: false },
  maxlength: { type: [String, Number], default: null },
  inputmode: { type: String, default: '' },
  pattern: { type: String, default: '' },
  value: { type: String, default: undefined },
  autosize: { type: Boolean, default: true },
});

const textAreaRef = ref(null);

const usesAutosizeTextarea = () => props.type === 'text' && props.autosize;

const resizeTextArea = () => {
  if (!usesAutosizeTextarea() || !(textAreaRef.value instanceof HTMLTextAreaElement)) {
    return;
  }

  textAreaRef.value.style.height = 'auto';
  textAreaRef.value.style.height = `${Math.max(textAreaRef.value.scrollHeight, 52)}px`;
};

const handleAutosizeInput = (event) => {
  if (props.value !== undefined) {
    return;
  }

  model.value = event.target.value;
  resizeTextArea();
};

watch(() => model.value, async () => {
  await nextTick();
  resizeTextArea();
});

watch(() => props.value, async () => {
  await nextTick();
  resizeTextArea();
});

onMounted(() => {
  resizeTextArea();
});
</script>

<template>
  <FormField :label="label" :help="help">
    <textarea
      v-if="usesAutosizeTextarea()"
      ref="textAreaRef"
      class="min-h-[3.25rem] resize-none overflow-hidden rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base leading-6"
      :name="name || undefined"
      :placeholder="placeholder || undefined"
      :autocomplete="autocomplete || undefined"
      :required="required"
      :maxlength="maxlength ?? undefined"
      :inputmode="inputmode || undefined"
      :pattern="pattern || undefined"
      rows="1"
      :value="value !== undefined ? value : model"
      @input="handleAutosizeInput"
    />
    <input
      v-else
      class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-base"
      :type="type"
      :name="name || undefined"
      :placeholder="placeholder || undefined"
      :autocomplete="autocomplete || undefined"
      :required="required"
      :maxlength="maxlength ?? undefined"
      :inputmode="inputmode || undefined"
      :pattern="pattern || undefined"
      :value="value !== undefined ? value : model"
      @input="value === undefined ? (model = $event.target.value) : undefined"
    >
  </FormField>
</template>
