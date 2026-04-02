<script setup>
import FormField from './FormField.vue';

const model = defineModel({ default: '' });

const props = defineProps({
  label: { type: String, required: true },
  help: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  rows: { type: [String, Number], default: null },
  minHeightClass: { type: String, default: '' },
});
</script>

<template>
  <FormField :label="label" :help="help">
    <textarea
      class="rounded-2xl border border-stone-200 bg-white px-4 py-3 text-sm"
      :class="minHeightClass"
      :rows="rows ?? undefined"
      :placeholder="placeholder || undefined"
      :value="model"
      @input="model = $event.target.value"
    />
  </FormField>
</template>
