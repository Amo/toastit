<script setup>
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
});
</script>

<template>
  <FormField :label="label" :help="help">
    <input
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
